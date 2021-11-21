<?php

namespace App\Http\Controllers;

use App\Models\SBO;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Exception\JsonException;

class SBOController extends Controller
{
    const CONTROLLER_NAME = 'SBOController';

    const API_URL = 'https://ex-api-demo-yy.568win.com';
    const COMPANY_KEY = 'CD35FAC44E474FF7A6E657A3575FA44E';

    const NO_ERROR = 0;
    const KEY_FAILED = 4;
    const INTERNAL_FAILED = 7;
    const ACCOUNT_NOT_EXISTS = 1;
    const NOT_ENOUGHT_BALANCE = 5;
    const BET_NOT_EXIST = 6;
    const ALREADY_ROOLBACK = 2003;

    protected function buildFailedValidationResponse(Request $request, $errors)
    {
        return response()->json(['ErrorCode' => self::INTERNAL_FAILED, 'ErrorMessage' => 'Internal Error']);
    }

    public static function routes()
    {
        Route::get('/sbobet/dev', self::CONTROLLER_NAME . '@index');
        Route::post('/sbobet/dev/GetBalance', self::CONTROLLER_NAME . '@getBalance');
        Route::post('/sbobet/dev/Deduct', self::CONTROLLER_NAME . '@deduct');
        Route::post('/sbobet/dev/Settle', self::CONTROLLER_NAME . '@settle');
        Route::post('/sbobet/dev/Rollback', self::CONTROLLER_NAME . '@rollback');
        Route::post('/sbobet/dev/Cancel', self::CONTROLLER_NAME . '@cancel');
        Route::post('/sbobet/dev/Tip', self::CONTROLLER_NAME . '@tip');
        Route::post('/sbobet/dev/Bonus', self::CONTROLLER_NAME . '@bonus');
        Route::post('/sbobet/dev/ReturnStake', self::CONTROLLER_NAME . '@returnStake');
        Route::post('/sbobet/dev/LiveCoinTransaction', self::CONTROLLER_NAME . '@liveCoinTransaction');
        Route::post('/sbobet/dev/GetBetStatus', self::CONTROLLER_NAME . '@getBetStatus');
    }

    public function index()
    {
        $transfer_code = '3515495';
        $trx = SBO::where('transfer_code', $transfer_code)->first();
        if (!$trx) {
            return ['ErrorCode' => self::INTERNAL_FAILED, 'ErrorMessage' => 'Internal Error', 'Balance' => 0];
        }

        $trx->status = 'running';
        $trx->save();

        return $trx;
        return ['message' => 'Hello, World.'];
    }

    public function getBetStatus(Request $request)
    {
        $payload = $request->post();
        $this->log("getBetStatus", $payload);

        $company_key = $payload['CompanyKey'];
        if (!$this->verifyKey($company_key)) {
            return ['ErrorCode' => self::KEY_FAILED, 'ErrorMessage' => 'CompanyKey Error'];
        }

        $transfer_code = $payload['TransferCode'];
        $transaction_id = $payload['TransactionId'];

        $trx = SBO::firstWhere('transfer_code', $transfer_code);
        if (!$trx) {
            return ['ErrorCode' => self::BET_NOT_EXIST, 'ErrorMessage' => 'Bet not exists'];
        }

        $response =  [
            'ErrorCode' => self::NO_ERROR,
            'ErrorMessage' => 'No Error',
            'Status' => $trx->status,
            'TransferCode' => $transfer_code,
            'TransactionId' => $transaction_id,
            'WinLoss' => $trx->winloss,
            'Stake' => $trx->amount,
        ];
        return $response;
    }

    public function returnStake(Request $request)
    {
        $payload = $request->post();
        $this->log("returnStake", $payload);
        return ['message' => 'Hello, World'];
    }

    public function liveCoinTransaction(Request $request)
    {
        $payload = $request->post();
        $this->log("liveCoinTransaction", $payload);
        return ['message' => 'Hello, World'];
    }

    public function bonus(Request $request)
    {
        $payload = $request->post();
        $this->log("bonus", $payload);
        return ['message' => 'Hello, World'];
    }

    public function tip(Request $request)
    {
        $payload = $request->post();
        $this->log("tip", $payload);
        return ['message' => 'Hello, World'];
    }

    public function cancel(Request $request)
    {
        $payload = $request->post();
        $this->log("cancel", $payload);

        $company_key = $payload['CompanyKey'];
        if (!$this->verifyKey($company_key)) {
            return ['ErrorCode' => self::KEY_FAILED, 'ErrorMessage' => 'CompanyKey Error'];
        }

        $transfer_code = $payload['TransferCode'];
        $trx = SBO::where('transfer_code', $transfer_code)->first();
        // $trx = SBO::where('transfer_code', $transfer_code)->where('status', 'running')->orWhere('status', 'settled')->first();
        if (!$trx) {
            return ['ErrorCode' => 6, 'ErrorMessage' => 'Bet not exists', 'Balance' => 0];
        }

        $username = $payload['Username'];
        $player = User::firstWhere('username', $username);
        if (!$player) {
            return ['ErrorCode' => self::ACCOUNT_NOT_EXISTS, 'ErrorMessage' => 'Member not exist'];
        }

        if ($trx->status == 'settled') {
            // $current_Amount = $trx->amount;
            // $current_ProfitAmount = $trx->winloss - $trx->amount;
            // $WinLoseAmount = $current_Amount + $current_ProfitAmount;
            // $RefundAmount = $current_Amount - $WinLoseAmount;
            // $player->increment('main_wallet', $RefundAmount);
            $player->decrement('main_wallet', ($trx->winloss - $trx->amount));
            $trx->status = 'void';
            $trx->save();
            $response =  [
                'ErrorCode' => self::NO_ERROR,
                'ErrorMessage' => 'No Error',
                'AccountName' => $player->username,
                'Balance' => $player->main_wallet
            ];
            return $response;
        }

        if ($trx->status == 'void') {
            $response =  [
                'ErrorCode' => 2002,
                'ErrorMessage' => 'Bet Already Canceled',
                'AccountName' => $player->username,
                'Balance' => $player->main_wallet
            ];
            return $response;
        }

        if ($trx->status == 'running') {
            $amount = $trx->amount;
            $player->increment('main_wallet', $amount);
        } else {
            if ($trx->is_rollback == 1) {
                $amount = $trx->amount;
                $player->increment('main_wallet', $amount);
            } else {
                $amount = $trx->amount - $trx->winloss;
                $player->increment('main_wallet', $amount);
            }
        }

        $after_balance = $player->main_wallet;

        $trx->status = 'void';
        $trx->save();

        $response =  [
            'ErrorCode' => self::NO_ERROR,
            'ErrorMessage' => 'No Error',
            'AccountName' => $player->username,
            'Balance' => $after_balance
        ];
        return $response;
    }

    public function rollback(Request $request)
    {
        $payload = $request->post();
        $this->log("rollback", $payload);

        $company_key = $payload['CompanyKey'];
        if (!$this->verifyKey($company_key)) {
            return ['ErrorCode' => self::KEY_FAILED, 'ErrorMessage' => 'CompanyKey Error'];
        }

        $transfer_code = $payload['TransferCode'];
        $trx = SBO::where('transfer_code', $transfer_code)->first();
        if (!$trx) {
            return ['ErrorCode' => 6, 'ErrorMessage' => 'Bet not exists', 'Balance' => 0];
        }

        if ($trx->is_rollback == 1) {
            return ['ErrorCode' => self::ALREADY_ROOLBACK, 'ErrorMessage' => 'Bet Already Rollback', 'Balance' => 0];
        }

        $username = $payload['Username'];
        $player = User::firstWhere('username', $username);
        if (!$player) {
            return ['ErrorCode' => self::ACCOUNT_NOT_EXISTS, 'ErrorMessage' => 'Member not exist', 'Balance' => 0];
        }

        $amount = $trx->amount;
        if ($trx->status == 'settled') {
            $player->decrement('main_wallet', $trx->winloss);
        } else {
            $player->decrement('main_wallet', $amount);
        }
        $after_balance = $player->main_wallet;

        $trx->status = 'running';
        $trx->is_rollback = 1;
        $trx->save();

        $response =  [
            'ErrorCode' => self::NO_ERROR,
            'ErrorMessage' => 'No Error',
            'AccountName' => $player->username,
            'Balance' => $after_balance
        ];
        return $response;
    }

    public function settle(Request $request)
    {
        $payload = $request->post();
        $this->log("settle", $payload);

        $company_key = $payload['CompanyKey'];
        if (!$this->verifyKey($company_key)) {
            return ['ErrorCode' => self::KEY_FAILED, 'ErrorMessage' => 'CompanyKey Error'];
        }

        $transfer_code = $payload['TransferCode'];
        // $trx = SBO::where('transfer_code', $transfer_code)->where('status', 'running')->first();
        $trx = SBO::where('transfer_code', $transfer_code)->first();
        if (!$trx) {
            return ['ErrorCode' => 6, 'ErrorMessage' => 'Bet not exists', 'Balance' => 0];
        }

        if ($trx->status == 'settled') {
            return ['ErrorCode' => 2001, 'ErrorMessage' => 'Bet Already Settled', 'Balance' => 0];
        }

        if ($trx->status == 'void') {
            return ['ErrorCode' => 2002, 'ErrorMessage' => 'Bet Already Canceled', 'Balance' => 0];
        }

        $username = $payload['Username'];
        $player = User::firstWhere('username', $username);
        if (!$player) {
            return ['ErrorCode' => self::ACCOUNT_NOT_EXISTS, 'ErrorMessage' => 'Member not exist'];
        }

        $amount = $payload['WinLoss'];
        $current_profit = $trx->profit;
        $profit = $current_profit + $amount;

        $player->increment('main_wallet', $amount);
        $after_balance = $player->main_wallet;

        $trx->winloss = $amount;
        $trx->profit = $profit;
        $trx->status = 'settled';
        $trx->save();

        $response =  [
            'ErrorCode' => self::NO_ERROR,
            'ErrorMessage' => 'No Error',
            'AccountName' => $player->username,
            'Balance' => $after_balance
        ];
        return $response;
    }

    public function deduct(Request $request)
    {
        $payload = $request->post();
        $this->log("Deduct", $payload);

        $company_key = $payload['CompanyKey'];
        if (!$this->verifyKey($company_key)) {
            return ['ErrorCode' => self::KEY_FAILED, 'ErrorMessage' => 'CompanyKey Error'];
        }

        $transfer_code = $payload['TransferCode'];
        $transaction_id = $payload['TransactionId'];
        $product_type = $payload['ProductType'];
        $amount = $payload['Amount'];
        $trx = SBO::firstWhere('transfer_code', $transfer_code);

        $username = $payload['Username'];
        $player = User::firstWhere('username', $username);
        if (!$player) {
            return ['ErrorCode' => self::ACCOUNT_NOT_EXISTS, 'ErrorMessage' => 'Member not exist'];
        }

        if ($trx) {
            if ($trx->status == 'running') {
                if ($amount > $trx->amount) {
                    $raise_credit = $amount - $trx->amount;
                    $trx->amount = $raise_credit;
                    $trx->save();
                    $player->decrement('main_wallet', $raise_credit);

                    $response =  [
                        'ErrorCode' => self::NO_ERROR,
                        'ErrorMessage' => 'No Error',
                        'AccountName' => $player->username,
                        'BetAmount' => $raise_credit,
                        'Balance' => $player->main_wallet
                    ];
                    return $response;
                }
            } else if ($trx->status == 'settled') {
                return ['ErrorCode' => 2001, 'ErrorMessage' => 'Bet Already Settled', 'Balance' => 0];
            }
            return ['ErrorCode' => self::INTERNAL_FAILED, 'ErrorMessage' => 'Internal Error', 'Balance' => 0];
            // $username = $payload['Username'];
            // $player = User::firstWhere('username', $username);
            // if (!$player) {
            //     return ['ErrorCode' => self::ACCOUNT_NOT_EXISTS, 'ErrorMessage' => 'Member not exist'];
            // }
            // if ($trx->status == 'running') {
            //     $trxAmount = $trx->amount;
            //     $raiseAmount = $trxAmount - $amount;
            //     if ($player->main_wallet >= $raiseAmount) {
            //         $trx->amount = $raiseAmount;
            //         $trx->save();
            //         $player->decrement('main_wallet', $raiseAmount);
            //         $after_balance = $player->main_wallet;

            //         $response =  [
            //             'ErrorCode' => self::NO_ERROR,
            //             'ErrorMessage' => 'No Error',
            //             'AccountName' => $player->username,
            //             'BetAmount' => $amount,
            //             'Balance' => $after_balance
            //         ];
            //         return $response;
            //     }
            // } else {
            //     return ['ErrorCode' => self::INTERNAL_FAILED, 'ErrorMessage' => 'Internal Error', 'Balance' => 0];
            // }
        }

        $amount = $payload['Amount'];
        $before_balance = $player->main_wallet;

        if ($before_balance < $payload['Amount']) {
            return ['ErrorCode' => self::NOT_ENOUGHT_BALANCE, 'ErrorMessage' => 'Not enough balance', 'Balance' => 0];
        }

        if ($before_balance <= 0) {
            return ['ErrorCode' => self::NOT_ENOUGHT_BALANCE, 'ErrorMessage' => 'Not enough balance', 'Balance' => 0];
        }

        $player->decrement('main_wallet', $amount);
        $after_balance = $player->main_wallet;

        $trx = new SBO();
        $trx->transfer_code = $transfer_code;
        $trx->transaction_id = $transaction_id;
        $trx->product_type = $product_type;
        $trx->status = 'running';
        $trx->amount = $amount;
        $trx->profit = $amount * -1;
        $trx->username = $player->username;
        $trx->action = 'BET';
        $trx->payload = json_encode($payload);
        $trx->save();

        $response =  [
            'ErrorCode' => self::NO_ERROR,
            'ErrorMessage' => 'No Error',
            'AccountName' => $player->username,
            'BetAmount' => $amount,
            'Balance' => $after_balance
        ];
        return $response;
    }

    public function getBalance(Request $request)
    {
        $payload = $request->post();
        $this->log("Get Balance", $payload);

        $company_key = $payload['CompanyKey'];
        $username = $payload['Username'];

        if (!$this->verifyKey($company_key)) {
            return ['ErrorCode' => self::KEY_FAILED, 'ErrorMessage' => 'CompanyKey Error', 'Balance' => 0];
        }

        if ($username == '') {
            return ['ErrorCode' => 3, 'ErrorMessage' => 'Username empty', 'Balance' => 0];
        }

        $player = User::firstWhere('username', $username);
        if (!$player) {
            return ['ErrorCode' => self::ACCOUNT_NOT_EXISTS, 'ErrorMessage' => 'Member not exist', 'Balance' => 0];
        }

        $response =  [
            'AccountName' => $player->username,
            'Balance' => $player->main_wallet,
            'ErrorCode' => self::NO_ERROR,
            'ErrorMessage' => 'No Error'
        ];
        return $response;
    }

    private function verifyKey($key)
    {
        return $key === self::COMPANY_KEY;
    }

    private function log($message, $payload)
    {
        Log::debug(self::CONTROLLER_NAME);
        Log::debug($message);
        Log::debug(json_encode($payload));
        Log::debug('-----------------------------------------------');
    }
}
