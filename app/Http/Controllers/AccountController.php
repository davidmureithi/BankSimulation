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

        if($this->maxDepositFrequency($request->input('dAccountNumber')) >= $Max_deposit_frequency) {
            return response()->json([
                'status' => 'You have reached maximum deposit limit for today. Kindly check tomorrow.',
                'day' => Carbon::today()->toDayDateTimeString(),
                'deposit count' => $this->maxDepositFrequency($request->input('dAccountNumber')),
                'total deposit' => $this->maxDeposit($request->input('dAccountNumber'))
            ]);
        }

        if($this->maxDeposit($request->input('dAccountNumber')) >= $Max_deposit_per_day) {
            return response()->json([
                'status' => 'You have reached maximum deposit limit for today. Kindly check tomorrow.',
                'day' => Carbon::today()->toDayDateTimeString(),
                'deposit count' => $this->maxDepositFrequency($request->input('dAccountNumber')),
                'total deposit' => $this->maxDeposit($request->input('dAccountNumber'))
            ]);
        }

        if($request->input('dAmount') > $Max_deposit_per_transaction){
            return response()->json([
                'status' => 'Maximum deposit per transaction is KSH.' .$Max_deposit_per_transaction,
                'deposit amount' => $request->input('dAmount')
            ], 201);
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

        $Max_withdrawal_per_day = 50000;
        $Max_withdrawal_per_transaction = 20000;
        $Max_withdrawal_frequency = 3;

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

        if($this->maxWithdrawalFrequency($request->input('wAccountNumber')) >= $Max_withdrawal_frequency) {
            return response()->json([
                'status' => 'You have reached maximum withdrawal limit for today. Kindly check tomorrow.',
                'day' => Carbon::today()->toDayDateTimeString(),
                'withdrawal count' => $this->maxWithdrawalFrequency($request->input('wAccountNumber')),
            ]);
        }

        if($this->maxWithdrawal($request->input('wAccountNumber')) >= $Max_withdrawal_per_day) {
            return response()->json([
                'status' => 'You have reached maximum withdrawal limit for today. Kindly check tomorrow.',
                'day' => Carbon::today()->toDayDateTimeString(),
                'withdrawal count' => $this->maxDepositFrequency($request->input('wAccountNumber')),
                'total withdrawal' => $this->maxWithdrawal($request->input('wAccountNumber')),
            ]);
        }

        if($request->input('wAmount') > $Max_withdrawal_per_transaction){
            return response()->json([
                'status' => 'Maximum withdrawal per transaction is KSH.' .$Max_withdrawal_per_transaction
            ]);
        }
        //Now let's check if the account exists in our database.
        $ac = Account::where('Account_Number' , '=' , $request->input('wAccountNumber'))->first();

        //If not exist, alert user
        if(!$ac) {
            return Redirect::to('/')
                ->withInput()
                ->with('hash', 'Account Number Does Not Exist');
        }

        $id = $ac->id;
        $balance = $ac->Account_Balance;
        $withdrawn = $request->input('wAmount');
        if($withdrawn > $balance){
            return Redirect::to('/')
                ->withInput()
                ->with('hash', 'Your have insufficient funds. Your balance is KSH.' .$balance);
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

        //Make sure user supplied an account number
        if($request->input('bAccountNumber') == null) {
            return response()->json([
                'status' => 'Your account number is required',
            ], 401);
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

        return response()->json([
            'status' => 'You balance is KSH.' . $balance . '/= Thank you for banking with My Bank.',
        ], 401);


//        return Redirect::to('/')
//            ->withInput()
//            ->with('hash', 'You balance is KSH.' . $balance . '/= Thank you for banking with My Bank.');
    }

    public function maxDepositFrequency($request){

        //Select all transactions for an AC for the last 24Hrs
        $trans_today = Transactions::where('Transaction_Account', '=', $request)
            ->where('Transaction_Type', '=', 'Deposit')
            ->where('created_at', '>=', Carbon::today())->count();
        return $trans_today;
    }

    public function maxWithdrawalFrequency($request){

        $trans_today = Transactions::where('Transaction_Account', '=', $request)
            ->where('Transaction_Type', '=', 'Withdraw')
            ->where('created_at', '>=', Carbon::today())->count();
        return $trans_today;
    }

    public function maxDeposit($request){

        $trans_amount_today = Transactions::where('Transaction_Account', '=', $request)
            ->where('Transaction_Type', '=', 'Deposit')
            ->where('created_at', '>=', Carbon::today())->sum('Transaction_Amount');

        return $trans_amount_today;
    }

    public function maxWithdrawal($request){

        $trans_amount_today = Transactions::where('Transaction_Account', '=', $request)
            ->where('Transaction_Type', '=', 'Withdraw')
            ->where('created_at', '>=', Carbon::today())->sum('Transaction_Amount');

        return $trans_amount_today;
    }
}
