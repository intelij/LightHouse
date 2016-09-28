<?php

namespace App\Http\Controllers;

use App\Committee;
use App\Delegation;

use App\Http\Requests\DelegationRequest;
use App\Http\Requests\SeatExchangeRequest;
use App\Seat;
use App\SeatExchange;
use App\SeatExchangeRecord;
use App\User;
use Auth;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

use App\Http\Requests;
use Illuminate\Support\Facades\Input;

class DelegationController extends Controller
{

    public function showCreateForm()
    {
        $committees = Committee::all();
        $users = User::all();
        $delegates = $users->filter(function (User $user) {
            return $user->hasRole('HEADDEL');
        });

        return view('delegation/create-delegation', compact("committees", "delegates"));
    }

    public function showDelegationSeatExchangeRuleForm()
    {
        $committees = Committee::all();
        return view('delegation/rules', compact("committees"));
    }

    public function showDelegations()
    {
        $committee_names = Committee::all("abbreviation");
        $names = new Collection();
        foreach ($committee_names as $committee_name) {
            $names->add($committee_name->abbreviation);
        }
        return view("delegation/delegations", ["delegations" => Delegation::all(), 'committee_names' => $names->toArray()]);
    }

    public function showUpdateForm(Request $request, $id)
    {
        $delegation = Delegation::find($id);
        $committee_seats = $delegation->committee_seats;
        $users = User::all();
        $delegates = $users->filter(function (User $user) {
            return $user->hasRole('HEADDEL');
        });

        return view('delegation/edit', compact("delegation", "committee_seats", "delegates"));
    }

    public function delete(Request $request, $id)
    {
        $delegation = Delegation::find($id);
        $seats = $delegation->seats;
        foreach ($seats as $seat) {
            $seat->is_distributed = false;
            $seat->delegation_id = null;
            $seat->save();
        }//释放席位
        $status = $delegation->delete();
        return $status ? response("", 200) : response("", 404);

    }

    public function showCommitteesLimitForm()
    {
        $committees = Committee::all();

        return view('delegation/limit', compact('committees'));
    }

    /**
     * @param Request $request
     * @return mixed
     * 各个委员会限额，该参数会在代表团自主进行交换时进行限制
     */
    public function updateCommitteeLimit(Request $request)
    {
        $committee_names = Committee::all('abbreviation')->pluck('abbreviation')->toArray();
        $rules = [];
        foreach ($committee_names as $committee_name) {
            $rules[$committee_name] = "required|integer|min:0";
        }
        $this->validate($request, $rules, [
            'required' => ':attribute 为必填项',
            'integer' => ':attribute 必须是数字',
            'min' => ':attribute 必须是正数'
        ]);

        //更新各个会场限额
        $committee_ids = Committee::all('id')->pluck('id')->toArray();
        foreach ($committee_ids as $committee_id) {
            $committee = Committee::find($committee_id);
            $committee->limit = $request->input($committee->abbreviation);
            $committee->save();
        }

        return redirect("committees/limit");
    }

    /**
     * @param DelegationRequest $request
     * @param $id
     * @return mixed
     * @middleware role:OT
     * 完成OT层面的代表团信息修改，主要包括
     * 1.修改领队信息
     * 2.修改代表团名称等
     * 3.修改席位信息（在此处添加或删除席位不受【每个代表团在各个会场席位数量上限】 的限制，便于OT进行奖励性分配
     *
     * 尚未实现日志记录功能
     */
    public function edit(DelegationRequest $request, $id)
    {
        $committees = Committee::all();


        $delegation = Delegation::all()->find($id);
        if ($delegation->head_delegate->id != $request->input("head_delegate_id")) {
            //更改领队
            $delegation->head_delegate()->dissociate();
            $delegation->head_delegate()->associate(User::find($request->input("head_delegate_id")));
        }
        $delegation->name = $request->input("name");
        $delegation->delegate_number = $request->input("delegate_number");
        $delegation->seat_number = $request->input("delegate_number");

        //修改会场
        for ($i = 0; $i < count($committees); $i++) {
            $committee_id = $committees[$i]->id;
            $committee_abbr = $committees[$i]->abbreviation;
            $current_seat = $delegation->seats->where("committee_id", $committee_id);
            $difference = $current_seat->count() - $request->input($committee_abbr);
            if ($difference > 0) {
                //需要从代表团中删除席位
                foreach ($current_seat->take($difference) as $seat) {
                    $seat->delegation()->dissociate();//删除的席位将回到席位池中
                    $seat->is_distributed = false;
                    $seat->save();
                }
            } else if ($difference < 0) {
                //需要增加席位
                $difference = -$difference;
                $seats = Seat::where("committee_id", $committee_id)->where("is_distributed", 0)->take($difference)->get();
                if ($seats->count() < $difference) {
                    //如果剩余席位不够分配
                    //回滚
                    $error = new Collection();
                    $error->add($committees[$i]->chinese_name . "席位不足");
                    return redirect('/delegation/' . $committee_id . '/edit')->with("errors", $error);
                }
                foreach ($seats as $seat) {
                    $seat->is_distributed = true;
                    $seat->save();
                }
                $delegation->seats()->saveMany($seats);
            }
        }
        $delegation->save();

        return redirect("/delegations");
    }

    public function create(DelegationRequest $request)
    {
        $committees = Committee::all();

        //创建代表团
        $delegation = new Delegation();
        $user = User::find($request->input("head_delegate_id"));
        $delegation->head_delegate()->associate($user);
        $delegation->name = $request->input("name");
        $delegation->delegate_number = $request->input("delegate_number");
        $delegation->seat_number = $request->input("delegate_number");
        $delegation->save();

        //创建相应代表
        //不论领队是否代表都创建新用户，在填写代表信息页面将其席位进行转换
        $delegation_id = $delegation->id;

//        $delegates = [];
        for ($i = 0; $i < count($committees); $i++) {
            $committee_id = $committees[$i]->id;
            $committee_abbr = $committees[$i]->abbreviation;
            $seats = Seat::all()->where("committee_id", $committee_id)->where("is_distributed", 0)->take($request->input($committee_abbr));
            if ($seats->count() != $request->input($committee_abbr)) {
                //如果剩余席位不够分配
                //回滚
                $delegation->delete();
                $error = new Collection();
                $error->add($committees[$i]->chinese_name . "席位不足");
                return redirect('create-delegation')->with("errors", $error);
            }
            foreach ($seats as $seat) {
                $seat->is_distributed = true;
                $seat->update();
            }
            $delegation->seats()->saveMany($seats);
        }
        return redirect("delegations");
    }

    /*
     * 以上方法适用于OT登录的情况下，
     * 以下适用于代表团领队
     */

    public function showDelegationInformation(Request $request, $id)
    {
        $delegations = Delegation::all();
        $delegation = Delegation::find($id);
        $delegates = $delegation->delegates;
        $seat_collection = $delegation->seats;
        $committees = Committee::all("id", "abbreviation", "limit");
        $seats = [];
        foreach ($committees as $committee) {
            $seats[$committee->abbreviation] = $seat_collection->where("committee_id", $committee->id)->count();
        }

        //名额交换数据
        $as_targets = SeatExchange::where("target", $id)->get();
        $target_requests = [];
        foreach ($as_targets as $item) {
            $record = [];
            $record['id'] = $item->id;
            $record['initiator'] = $delegations->find($item->initiator)->name;
            $record['target'] = $delegations->find($id)->name;
            $delta = $item->delta;
            foreach($committees as $committee){
                $record[$committee->abbreviation] = -$delta[$committee->abbreviation];
            }
            $record['status'] = $item->status;
            $target_requests[] = $record;
        }
        $as_initiators = SeatExchange::where("initiator",$id)->get();
        $initiator_requests = [];
        foreach ($as_initiators as $item) {
            $record = [];
            $record['id'] = $item->id;
            $record['target'] = $delegations->find($item->target)->name;
            $record['initiator'] = $delegations->find($id)->name;
            $delta = $item->delta;
            foreach($committees as $committee){
                $record[$committee->abbreviation] = $delta[$committee->abbreviation];
            }
            $record['status'] = $item->status;
            $initiator_requests[] = $record;
        }


        return view("delegation/delegation", compact("seats", "committees", "delegation","initiator_requests","target_requests"));

    }

    public function showSeatExchange()
    {
        $committees = Committee::all();
        $committees_name = $committees->pluck("abbreviation");
        $delegations = Delegation::all();

        return view("delegation/seat-exchange", compact("committees", "committees_name", "delegations"));
    }

    public function seatExchange(SeatExchangeRequest $request)
    {
        $request->session()->put("errors", new Collection());
        $committees = Committee::all();
        $committee_rules = $committees->pluck("limit");
        $committee_abbreviations = $committees->pluck("abbreviation");

        $initiator = Delegation::findOrFail(Auth::user()->delegation->id);
        $target = Delegation::findOrFail(Input::get("target"));

        $errors = [];


        //检查是否是一个已经存在的交换申请
        //两个代表团之间只能同时进行一次交换
        $seat_exchange = SeatExchange::all()->where("initiator", $target->id)->where("target", $initiator->id)->where("status", "padding");
        if ($seat_exchange->count() == 1) {
            //已经存在至少一个申请
            $seats = $seat_exchange->first()->seat_exchange_records;
            $is_corresponding = true;
            foreach ($committees as $committee) {
                $in = $request->input($committee->abbreviation . "-in");
                $out = $request->input($committee->abbreviation . "-out");
                if ($seats->where("committee_id", $committee->id)->count() == 0) {
                    if ($in == 0 && $out == 0) {
                        continue;
                    }
                    $is_corresponding = false;
                } else
                    if ($seats->where("committee_id", $committee->id)->first()->in != $out || $seats->where("committee_id", $committee->id)->first()->out != $in) {
                        //数据库中记录与正在处理的request中的数据不符合
                        $is_corresponding = false;
                    }
            }
            if (!$is_corresponding) {
//                $this->errorHandle($request,["与目标代表团提交的名额交换申请存在出入"]);
                return response(["与目标代表团提交的名额交换申请存在出入"], 400);
            } else {
                $this->exchangeSeats($seat_exchange->first());
            }
        } else {
            //如果没有对应的申请
            //检查本代表团是否有足够名额
            $initiator_in_faults = [];//本代表团超限的会场
            $target_out_faults = [];//目标代表团名额不足的会场
            $target_in_faults = [];//目标代表团超限的会场
            $initiator_out_faults = [];//本代表团名额不足的会场
            foreach ($committees as $committee) {
                $current = $initiator->seats->where("committee_id", $committee->id)->count();
                $target_current = $target->seats->where("committee_id", $committee->id)->count();
                $in = $request->input($committee->abbreviation . "-in");
                $out = $request->input($committee->abbreviation . "-out");
                if ($current + $in > $committee->limit) {
                    $initiator_in_faults[] = $committee->abbreviation;
                }
                if ($target_current < $in) {
                    $target_out_faults[] = $committee->abbreviation;
                }
                if ($target_current + $out > $committee->limit) {
                    $target_in_faults[] = $committee->abbreviation;
                }
                if ($current < $out) {
                    $initiator_out_faults[] = $committee->abbreviation;
                }
            }
            if (count($initiator_in_faults) != 0 || count($initiator_out_faults) != 0 || count($target_in_faults) != 0 || count($target_out_faults) != 0) {
                $error_messages = new Collection();
                foreach ($initiator_in_faults as $item) {
                    $error_messages->add("名额交换之后本代表团" . $item . "会场名额超过限制");
                }
                foreach ($initiator_out_faults as $item) {
                    $error_messages->add("本代表团" . $item . "会场名额不足");
                }
                foreach ($target_in_faults as $item) {
                    $error_messages->add("名额交换之后目标代表团" . $item . "会场名额超过限制");
                }
                foreach ($target_out_faults as $item) {
                    $error_messages->add("目标代表团" . $item . "会场名额不足");
                }
//                $this->errorHandle($request,$error_messages);
                return response($error_messages, 400);
            }


            //在SeatExchange中创建相应的数据项目
            $seat_exchange_request = new SeatExchange();
            $seat_exchange_request->initiator = $initiator->id;
            $seat_exchange_request->target = $target->id;
            $seat_exchange_request->status = "padding";
            $seat_exchange_request->save();

            $seat_exchange_records = [];
            foreach ($committees as $committee) {
                if ($request->input($committee->abbreviation . "-in") != 0 || $request->input($committee->abbreviation . "-out") != 0) {
                    $seat_exchange_records[] = SeatExchangeRecord::create([
                        'committee_id' => $committee->id,
                        'in' => $request->input($committee->abbreviation . "-in"),
                        "out" => $request->input($committee->abbreviation . "-out")
                    ]);
                }
            }
            $seat_exchange_request->seat_exchange_records()->saveMany($seat_exchange_records);
        }
        return response("", 200);
    }


    //helper functions
    /**
     * @param SeatExchange $exchange_request
     */
    private function exchangeSeats(SeatExchange $exchange_request)
    {
        //在完成验证之后处理席位交换的函数
        $initiator = Delegation::findOrFail($exchange_request->initiator);
        $target = Delegation::findOrFail($exchange_request->target);

        $records = $exchange_request->seat_exchange_records;
        foreach ($records as $record) {
            if ($record->out != 0) {
                $target->seats()->saveMany($initiator->seats->where("committee_id", $record->committee_id)->take($record->out));
            }
            if ($record->in != 0) {
                $initiator->seats()->saveMany($target->seats->where("committee_id", $record->committee_id)->take($record->in));
            }
        }
        $exchange_request->status = "success";
        $initiator->seat_number = $initiator->seats->count();
        $initiator->delegate_number = $initiator->seats->count();
        $target->seat_number = $target->seats->count();
        $target->delegate_number = $target->seats->count();
        $initiator->save();
        $target->save();
        $exchange_request->save();

    }


}
