@extends('layouts.layout')

@section('content')
    <section id="form">
        <div class="container">
            @if(Session::has('hash'))
                <h3 class="success">
                    {{Session::get('hash')}}
                </h3>
            @endif
            <div class="row">
                <div class="col-sm-3 col-sm-offset-1">
                    <div class="login-form">
                        <h2>Create an account</h2>
                        <form method="POST" action="{{url('create_account')}}">
                            {!! csrf_field() !!}
                            <input type="text" name="cAccountName" id="cAccountName" placeholder="Your Name" />
                            <input type="number" name="cAccountIdentity" id="cAccountIdentity" placeholder="Your ID Number" />
                            <input type="number" name="fDAmount" id="fDAmount" placeholder="First Deposit Amount" />
                            <button type="submit" class="btn btn-default">Create Account</button>
                        </form>
                    </div>
                </div>
                <div class="col-sm-1">
                    <h2 class="or">OR</h2>
                </div>
                <div class="col-sm-3">
                    <div class="login-form">
                        <h2>Deposit to your account</h2>
                        <form method="POST" action="{{url('deposit')}}">
                            {!! csrf_field() !!}
                            <input type="text" name="dAccountNumber" id="dAccountNumber" placeholder="Account Number" />
                            <input type="number" name="dAmount" id="dAmount" placeholder="Amount" />
                            <button type="submit" class="btn btn-default">Deposit</button>
                        </form>
                    </div>
                </div>
                <div class="col-sm-1">
                    <h2 class="or">OR</h2>
                </div>
                <div class="col-sm-3">
                    <div class="signup-form">
                        <h2>Withdraw from your account</h2>
                        <form method="POST" action="{{url('withdraw')}}">
                            {!! csrf_field() !!}
                            <input type="text" name="wAccountNumber" placeholder="Account Number"/>
                            <input type="number" name="wAmount" placeholder="Amount">
                            <button type="submit" class="btn btn-default">Withdraw</button>
                        </form>
                    </div>
                </div>
                <div class="col-sm-1">
                    <h2 class="or">OR</h2>
                </div>
                <div class="col-sm-3">
                    <div class="signup-form">
                        <h2>Check your balance</h2>
                        <form method="POST" action="{{url('balance')}}">
                            {!! csrf_field() !!}
                            <input type="text" name="bAccountNumber" placeholder="Account Number"/>
                            <button type="submit" class="btn btn-default">Check Balance</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection