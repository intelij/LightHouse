@extends('layout')

@section('content')
    <section>
        <div class="section-header">
            <ol class="breadcrumb">
                <li class="active">
                    代表团信息
                </li>
            </ol>
        </div>
        <div class="section-body contain-lg">
            <div class="row">
                <div class="col-lg-6">
                    <div class="card card-bordered style-primary">
                        <div class="card-head">
                            <header>
                                代表团名称
                            </header>
                        </div>
                        <div class="card-body style-default-bright">
                            <ul class="list">
                                <li class="tile">
                                    <div class="tile-content">
                                        <div class="tile-text">{{$delegation->name}}</div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card card-bordered style-primary">
                        <div class="card-head">
                            <header>
                                代表团领队名称
                            </header>
                        </div>
                        <div class="card-body style-default-bright">
                            <ul class="list">
                                <li class="tile">
                                    <div class="tile-content">
                                        <div class="tile-text">{{$delegation->head_delegate->name}}</div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-12">
                    <div class="card style-primary">
                        <div class="card-body">
                            <table class="table table-hover no-margin">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>会场名称</th>
                                    <th>席位数</th>
                                    <th>待确认席位数</th>
                                    <th>操作</th>
                                </tr>
                                </thead>
                                <tbody>

                                @foreach($committees as $committee)
                                    <tr>
                                        <td>{{$committee->id}}</td>
                                        <td>{{$committee->abbreviation}}</td>
                                        <td>{{$seats[$committee->abbreviation]}}</td>
                                        <td>0</td>
                                        <td><a href="javascript:void(0)" data-target="{{$committee->id}}"><i
                                                        class="fa fa-eye"></i></a></td>
                                    </tr>
                                @endforeach

                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('js')
    @include('partial/form-error')
    <script>
        $('table tr td a i.fa.fa-eye').click(function(e){
            $dialog = new BootstrapDialog();
            $dialog.setType("type-info");
            $dialog.setSize("size-wide");
            $dialog.setTitle("会场名额分布");
            $dialog.open();

        })
    </script>
@endsection