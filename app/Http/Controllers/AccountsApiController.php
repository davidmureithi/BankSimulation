<?php
/**
 * Created by PhpStorm.
 * User: raech
 * Date: 3/11/17
 * Time: 8:19 AM
 */

namespace App\Http\Controllers;

use App\Account;
use App\Http\Requests\AccountsApiRequest;
use App\Transactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AccountsApiController
{
    public function index() {
        return view('bank');
    }

    public function store(Request $request) {
        $account = new Account();
        $account->Account_Name = $request->input('cAccountName');
        $account->Account_Identity = $request->input('cAccountIdentity');
        $account->Account_Balance = $request->input('fDAmount');
        $newAccount = 'AC/' . Str::random(6); //Generate a random account number
        $account->Account_Number = $newAccount;

        //Check if we already have the Unique Identity in our DB. If so, we alert the user
        $id = Account::where('Account_Identity' , '=' , $request->input('cAccountIdentity'))->first();
        if($id) {
            return response()->json([
                'status' => 'Identity Already Registered',
                'id' => $account->Account_Identity
            ]);
        }

        //Check for null values
        if(($account->Account_Name || $account->Account_Identity || $account->Account_Balance) == null) {
            return response()->json([
                'status' => 'All fields are required',
            ], 401);
        }

        //Save to the DB
        $account->save();

        if(!$account->save()) {
            throw new HttpException(500);
        }

        if($account->save()) {
            return response()->json([
                'status' => 'Account successfully created with AC/No:' . $account->Account_Number,
                'data' => $account
            ], 201);
        }
    }

    public function deposit(Request $request){

        $Max_deposit_per_day = 150000;
        $Max_deposit_per_transaction = 40000;
        $Max_deposit_frequency = 4;

        if( $request->input('dAmount') == '') {
            return response()->json([
                'status' => 'Enter deposit amount',
            ], 401);
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
        if(!$ac || $request->input('dAccountNumber') == '') {
            return response()->json([
                'status' => 'Account Number, '. $request->input('dAccountNumber') . ' Does Not Exist. Please double check the account entered.',
            ], 201);
        }

        $id = $ac->id;
        $balance = $ac->Account_Balance;
        $deposit = $request->input('dAmount');
        $newBalance = $balance + $deposit;
        $account = Account::find($id);
        $account->Account_Balance = $newBalance;
        $account->save();

        $transaction = new Transactions();
        $transaction->Transaction_Account = $request->input('dAccountNumber');
        $transaction->Transaction_Type = 'Deposit';
        $transaction->Transaction_Amount = $request->input('dAmount');
        $transaction->save();

        if(!$account->save()) {
            throw new HttpException(500);
        }

        if($account->save()) {
            return response()->json([
                'status' => 'You have succesfully deposited KSH.' .
                    $deposit . ' into your account! New balance : KSH. ' .
                    $newBalance,
                'data' => $account
            ], 201);
        }
    }

    public function withdraw(Request $request){

        $Max_withdrawal_per_day = 50000;
        $Max_withdrawal_per_transaction = 20000;
        $Max_withdrawal_frequency = 3;

        if(($request->input('wAccountNumber') || $request->input('wAmount')) == null) {
            return response()->json([
                'status' => 'All fields are required',
            ], 401);
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
            return response()->json([
                'status' => 'Account Number Does Not Exist'
            ]);
        }

        $id = $ac->id;
        $balance = $ac->Account_Balance;
        $withdrawn = $request->input('wAmount');

        if($withdrawn > $balance){
            return response()->json([
                'status' => 'Your have insufficient funds. Your balance is KSH.' .$balance
            ]);
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

        return response()->json([
            'status' => 'You have withdrawn KSH.' .
                        $withdrawn .' from your account! New balance : KSH.' .
                        $newBalance
        ]);
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