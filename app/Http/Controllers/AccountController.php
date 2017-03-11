<?php

namespace App\Http\Controllers;

use App\Account;
use App\Transactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;


class AccountController extends Controller
{

    public function index() {
        return view('bank');
    }

    public function store(Request $request) {
        //Define the Form validation rules
        $rules = array(
            'cAccountName','cAccountIdentity', 'fDAmount' => 'required'
        );

        //Then run the form validation
        $validation = Validator::make(Input::all(),$rules);

        //If validation fails, return to the main page with error info
        if($validation->fails()) {
            return Redirect::to('/')
                ->withInput()
                ->with('hash', 'All fields are required');
        }

        //Check if we already have the Unique Identity in our DB. If so, we alert the user
        $id = Account::where('Account_Identity' , '=' , $request->input('cAccountIdentity'))->first();
        if($id) {
            return Redirect::to('/')
                ->withInput()
                ->with('hash', 'ID No.' . $id->Account_Identity . ' is already registered!');
        }

        $account = new Account();
        $account->Account_Name = $request->input('cAccountName');
        $account->Account_Identity = $request->input('cAccountIdentity');

        //Generate a random account number
        $newAccount = 'AC/' . Str::random(6);
        $account->Account_Number = $newAccount;
        $account->Account_Balance = $request->input('fDAmount');

        //Save to the DB
        $account->save();

        $mytime = Carbon::now();
        $T = $mytime->toDateTimeString();

        echo "Carbon now: " . Carbon::now();

        //Alert user of successful account creation and give the Account Number
        return Redirect::to('/')
            ->withInput()
            ->with('hash', ' Account successfully created with account number ' . $account->Account_Number);
    }

    public function deposit(Request $request){

        $Max_deposit_per_day = 150000;
        $Max_deposit_per_transaction = 40000;
        $Max_deposit_frequency = 4;

        //We first define the Form validation rule(s)
        $rules = array(
            'dAccountNumber', 'dAmount' => 'required'
        );

        //Then we run the form validation
        $validation = Validator::make(Input::all(),$rules);

        //If validation fails, we return to the main page with an error info
        if($validation->fails()) {
            return Redirect::to('/')
                ->withInput()
                ->with('hash', 'All fields are required');
        }

        //Now let's check if the account exists in our database.
        $ac = Account::where('Account_Number' , '=' , $request->input('dAccountNumber'))->first();

        //If not exist, alert user
        if(!$ac) {
            return Redirect::to('/')
                ->withInput()
                ->with('hash', 'Account Number Does Not Exist');
        }

        $id = $ac->id;
        $balance = $ac->Account_Balance;
        $deposit = $request->input('dAmount');
        if($deposit > $Max_deposit_per_transaction){
            return Redirect::to('/')
                ->withInput()
                ->with('hash', 'Maximum deposit per transaction is KSH.' .$Max_deposit_per_transaction);
        }

        $newBalance = $balance + $deposit;

        $account = Account::find($id);

        $account->Account_Balance = $newBalance;
        $account->save();

        $transaction = new Transactions();
        $transaction->Transaction_Account = $request->input('dAccountNumber');
        $transaction->Transaction_Type = 'Deposit';
        $transaction->Transaction_Amount = $request->input('dAmount');
        $transaction->save();

        return Redirect::to('/')
            ->withInput()
            ->with('hash',
                   'You have succesfully deposited KSH.' .
                   $deposit . ' into your account! New balance : KSH. ' .
                   $newBalance);

    }

    public function withdraw(Request $request){

        $Max_withrawal_per_day = 50000;
        $Max_withrawal_per_transaction = 20000;
        $Max_withrawal_frequency = 3;

        //We first define the Form validation rule(s)
        $rules = array(
            'wAccountNumber', 'wAmount' => 'required'
        );

        //Then we run the form validation
        $validation = Validator::make(Input::all(),$rules);

        //If validation fails, we return to the main page with an error info
        if($validation->fails()) {
            return Redirect::to('/')
                ->withInput()
                ->with('hash', 'All fields are required');
        }

        //Now let's check if the account exists in our database.
        $ac = Account::where('Account_Number' , '=' , $request->input('wAccountNumber'))->first();

        //If not exist, alert user
        if(!$ac) {
            return Redirect::to('/')
                ->withInput()
                ->with('hash', 'Account Number Does Not Exist');
        }

        //Check if account has reached maximum transactions
//        $freq = Transactions::where('created_at', '>=', Carbon::now()->subDay()->toDateTimeString());
//
//        dd($freq);

        $trans = Transactions::where('Transaction_Account' , '=' , $request->input('wAccountNumber') , 'Transaction_Type' , '=' , 'Withdraw' );

        if(8 >= $Max_withrawal_frequency ) {
            return Redirect::to('/')
                ->withInput()
                ->with('hash', 'You have reached maximum withdrawal limit. Kindly check tomorrow.');
        }
//
//        $dtOttawa = Carbon::createFromDate(2000, 1, 1, 'America/Toronto');
//        $dtVancouver = Carbon::createFromDate(2000, 1, 1, 'America/Vancouver');
//        echo $dtOttawa->diffInHours($dtVancouver);                             // 3
//
//        echo $dtOttawa->diffInHours($dtVancouver, false);                      // 3
//        echo $dtVancouver->diffInHours($dtOttawa, false);                      // -3


        //Select all transactions for an AC for the last 24Hrs


        $id = $ac->id;
        $balance = $ac->Account_Balance;
        $withdrawn = $request->input('wAmount');
        if($withdrawn > $balance){
            return Redirect::to('/')
                ->withInput()
                ->with('hash', 'Your have insufficient funds. Your balance is KSH.' .$balance);
        }
        if($withdrawn > $Max_withrawal_per_transaction){
            return Redirect::to('/')
                    ->withInput()
                    ->with('hash', 'Maximum withdrawal per transaction is KSH.' .$Max_withrawal_per_transaction);
        }
        $newBalance = $balance - $withdrawn;
        $account = Account::find($id);
        $account->Account_Balance = $newBalance;
        $account->save();

        $transaction = new Transactions();
        $transaction->Transaction_Account = $request->input('wAccountNumber');
        $transaction->Transaction_Type = 'Withdraw';
        $transaction->Transaction_Amount = $request->input('wAmount');
        $transaction->save();

        return Redirect::to('/')
                ->withInput()
                ->with('hash','You have withdrawn KSH.' .
                $withdrawn . ' from your account! New balance : KSH.' .
                $newBalance);
    }

    public function balance(Request $request){

        //We first define the Form validation rule(s)
        $rules = array(
            'bAccountNumber' => 'required'
        );

        //Then we run the form validation
        $validation = Validator::make(Input::all(),$rules);

        //If validation fails, we return to the main page with an error info
        if($validation->fails()) {
            return Redirect::to('/')
                ->withInput()
                ->with('hash', 'Your account number is required');
        }

        //Now let's check if the account exists in our database.
        $ac = Account::where('Account_Number' , '=' , $request->input('bAccountNumber'))->first();

        //If not exist, alert user
        if(!$ac) {
            return Redirect::to('/')
                ->withInput()
                ->with('hash', 'Account Number Does Not Exist');
        }
        $balance = $ac->Account_Balance;

        return Redirect::to('/')
            ->withInput()
            ->with('hash', 'You balance is KSH.' . $balance . '/= Thank you for banking with My Bank.');
    }
}
