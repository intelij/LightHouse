<?php

namespace App\Http\Controllers;

use App\User;
use App\UserArchive;
use Illuminate\Http\Request;

use App\Http\Requests;

class UserArchiveController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * show user archive form
     * GET user-archive
     * @param Request $request
     * @return mixed
     * @internal param int $id
     */
    public function showArchiveForm(Request $request)
    {
        $id = $request->user()->id;
        $user_archive = UserArchive::where('id', $id)->first();
        return view('user/user-archive', ['user' => $user_archive]);
    }

    /**
     * add or update user archive of user
     * GET user-archive
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|string
     */
    public function addOrUpdate(Request $request)
    {
        //TODO
        $user_archive = UserArchive::find($request->user()->id);
        $this->validate($request,[
            'name'=>'required',
            'first-name'=>'required|alpha',
            'last-name'=>'required|alpha',
            'high-school'=>'max:255'
        ]);
        $request->user()->name=$request->input('name');
        $user_archive->FirstName = $request->input('first-name');
        $user_archive->LastName = $request->input('last-name');
        $user_archive->HighSchool = $request->input('high-school');
        $user_archive->University = $request->input('university');
        if($user_archive->save()){
            return redirect('user-archive');
        }
        else
            return ('404');
    }

}
