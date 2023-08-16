<?php

namespace App\Http\Controllers;

use App\Classes\Payment;
use Illuminate\Http\Request;

use App\Models\Rich88Game;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\User;


class Rich88GameController extends Controller
{
    private $HOST = "https://lobbycenter.ark8899.com";
    private $PrivateKey = "VvfIMzN1J90x53wsPvrHvxJsidUchTiJ";
    private $PFID = "acthb_777B";

    const CONTROLLER_NAME = 'Rich88GameController';

    public static function routes()
    {
        Route::get('/rich88/', self::CONTROLLER_NAME . '@dev');
        Route::get('/rich88/session_id', self::CONTROLLER_NAME . '@getSessionId');
        Route::get('/rich88/{username}', self::CONTROLLER_NAME . '@loginGame');
        Route::get('/rich88/balance/{account}', self::CONTROLLER_NAME . '@getBalance');
        Route::post('/rich88/transfer', self::CONTROLLER_NAME . '@transfer');
        Route::post('/rich88/award_activity', self::CONTROLLER_NAME . '@bonusWin');
        Route::post('/rich88/v2/platform/single_wallet/rollback', self::CONTROLLER_NAME . '@cancelWithdraw');
    }

    public function dev()
    {
        $api_key_payload = $this->PFID . $this->PrivateKey . "1637604437372452";
        $api_key = hash('sha256', $api_key_payload);
        return $api_key;
    }

    private function encryptBody()
    {
        date_default_timezone_set('America/New_York');
        $current_timestamp = Carbon::now()->timestamp;
        $api_key_payload = $this->PFID . $this->PrivateKey . $current_timestamp;
        $api_key = hash('sha256', $api_key_payload);
        return [
            "api_key" => $api_key,
            "current_timestamp" => $current_timestamp,
        ];
    }

    public function loginGame($username)
    {
        $user = User::where('username', $username)->first();
        if (empty($user)) {
            $response = ["message" => "Oops! The user does not exist"];
            return response($response, 401);
            exit();
        }

        $obj_encryptBody = $this->encryptBody();
        $api_key = $obj_encryptBody["api_key"];
        $current_timestamp = $obj_encryptBody["current_timestamp"];

        $client = new Client([
            'headers' => [
                'Accept'   => 'application/json',
                'Content-Type'   => 'application/json',
                'api_key' => $api_key,
                'pf_id' => $this->PFID,
                'timestamp' => $current_timestamp,
            ]
        ]);

        // return [
        //     'Accept'   => 'application/json',
        //     'Content-Type'   => 'application/json',
        //     'api_key' => $api_key,
        //     'pf_id' => $this->PFID,
        //     'timestamp' => $current_timestamp,
        // ];

        // return "{$this->HOST}/v2/platform/login";

        $res = $client->request("POST", "{$this->HOST}/v2/platform/login", [
            // 'debug' => true,
            'json' => [
                'account' =>  $username,
            ]
        ]);

        // return [
        //     'account' =>  $username,
        // ];

        $response = $res->getBody()->getContents();
        $json = json_decode($response, true);
        // return $res->getStatusCode();
        if ($json["code"] == 0) {
            $lobby_url = $json["data"]["url"];
            return redirect($lobby_url);
        } else {
            return response($response, 500);
        }
    }

    public function gameList()
    {
        // $username = "Test88";
        date_default_timezone_set('America/New_York');
        $current_timestamp = Carbon::now()->timestamp;
        // $api_key_payload = "acthb_NASADLUYRLliab7d2JWzhGRrAIOrHk5gD9ic1637215815";

        $api_key_payload = $this->PFID . $this->PrivateKey . $current_timestamp;
        $api_key = hash('sha256', $api_key_payload);

        // echo "current_timestamp ==>" . $current_timestamp . "<br/>";
        // echo "api_key_payload ==>" . $api_key_payload . "<br/>";
        // echo "api_key ==>" . $api_key . "<br/>";
        // 1605671558
        // 1637215566
        $client = new Client([
            'exceptions'       => false,
            'verify'           => false,
            'headers'          => [
                'Accept'   => 'application/json',
                'Content-Type'   => 'application/json',
                'api_key' => $api_key,
                'pf_id' => $this->PFID,
                'timestamp' => $current_timestamp,
            ]
        ]);
        // $res = $client->request("POST", "{$this->HOST}/v2/platform/login", [
        $res = $client->request("GET", "https://lobbycenter.ark8899.com/v2/platform/gamelist?active_only=true", [
            'form_params' => []
        ]);
        $response = $res->getBody()->getContents();
        // $json = json_decode($response, true);
        return $response;
    }


    public function getBalance(Request $request, $account)
    {
        Log::alert("==============>getBalance");
        $header = $request->header('Authorization');
        Log::debug($header);
        if ((empty($header)) || $header == "xyz") {
            $response = ["message" => "Oops! The user does not exist"];
            return response($response, 401);
            exit();
        }



        $member = User::where('username', '=', $account)->first();
        if ($member) {
            return [
                "code" => 0,
                "msg" => "Success",
                "data" => [
                    "balance" => (float) $member->main_wallet
                ]
            ];
        } else {
            return [
                "code" => 16002,
                "msg" => "Token authentication error",
                "data" => [
                    "balance" => 0
                ]
            ];
        }
    }

    public function getSessionId(Request $request)
    {
        Log::alert("==============>getSessionId");
        $auth_api_key = $request->header('api-key');
        $auth_timestamp = $request->header('timestamp');

        Log::debug([
            "api-key" => $auth_api_key,
            "pf-id" => $request->header('pf-id'),
            "timestamp" => $auth_timestamp,
        ]);

        $api_key_payload = $this->PFID . $this->PrivateKey . $auth_timestamp;
        // echo "api_key_payload ==>" . $api_key_payload . "<br/>";

        $api_key = hash('sha256', $api_key_payload);
        // echo "api_key ==>" . $api_key . "<br/>";
        // echo "auth_api_key ==>" . $auth_api_key . "<br/>";
        date_default_timezone_set('America/New_York');
        if ($api_key == $auth_api_key) {
            $current_timestamp = Carbon::now()->timestamp;
            return [
                "code" => 0,
                "msg" => "Success",
                "data" => [
                    "sid" => 'nasa-sid-' . $current_timestamp
                ]
            ];
        } else {
            return [
                "code" => 0,
                "msg" => "Success",
                "data" => [
                    "sid" => "xyz"
                ]
            ];
        }
    }

    public function transfer(Request $request)
    {
        Log::alert("==============>transfer");
        Log::debug($request);

        $action = $request->action;
        $username = $request->account;
        $amount = $request->money;
        $record_id = $request->record_id;
        $round_id = $request->round_id;

        DB::beginTransaction();
        try {
            $userWallet = User::where('username', $username)->lockForUpdate()->first();
            if (!$userWallet) {
                throw new \Exception('Token authentication error', 16002);
            }
            $wallet_amount_before = $userWallet->main_wallet;
            $wallet_amount_after = $userWallet->main_wallet;

            if ($action == "withdraw") {
                if ($wallet_amount_before > 0 && $wallet_amount_before >= $amount) {
                    $check_transaction = Rich88Game::select('id')
                        ->where('username', $username)
                        ->where('action', 'withdraw')
                        ->where('record_id', $record_id)
                        ->first();
                    if (!$check_transaction) {
                        $wallet_amount_after = $wallet_amount_after - $amount;
                        User::where('username', $username)->update([
                            'main_wallet' => $wallet_amount_after
                        ]);

                        Payment::updatePlayerWinLossReport([
                            'report_type' => 'Hourly',
                            'player_id' => $userWallet->id,
                            'partner_id' => $userWallet->partner_id,
                            'provider_id' => 1,
                            'provider_name' => 'Rich88',
                            'game_id' => $request->game_code,
                            'game_name' => $request->game_code,
                            'game_type' => 'Slot',
                            'loss' => $amount,
                        ]);

                        (new Payment())->payAll($userWallet->id, $amount, 'SLOT');
                        (new Payment())->saveLog([
                            'amount' => $amount,
                            'before_balance' => $wallet_amount_before,
                            'after_balance' => $wallet_amount_before - $amount,
                            'action' => 'BET',
                            'provider' => 'RICH88',
                            'game_type' => 'SLOT',
                            'game_ref' => 'NO REFERENCE',
                            'transaction_ref' => $request->record_id,
                            'player_username' => $username,
                        ]);

                        /// save log 
                        $logres = $this->savaTransaction("withdraw", $wallet_amount_before, $wallet_amount_after, $request);
                        if (!$logres) {
                            throw new \Exception('Fail (System Error)', 22008);
                        }
                    }
                } else {
                    throw new \Exception('Money is not enough', 22007);
                }
            } else if ($action == "deposit") {
                $check_transaction_bet = Rich88Game::select('id')
                    ->where('username', $username)
                    ->where('action', 'withdraw')
                    ->where('round_id', $round_id)
                    ->first();
                if ($check_transaction_bet) {
                    $check_transaction = Rich88Game::select('id')
                        ->where('username', $username)
                        ->where('action', 'deposit')
                        ->where('record_id', $record_id)
                        ->first();
                    if (!$check_transaction) {
                        $wallet_amount_after = $wallet_amount_after + $amount;

                        User::where('username', $username)->update([
                            'main_wallet' => $wallet_amount_after
                        ]);

                        Payment::updatePlayerWinLossReport([
                            'report_type' => 'Hourly',
                            'player_id' => $userWallet->id,
                            'partner_id' => $userWallet->partner_id,
                            'provider_id' => 1,
                            'provider_name' => 'Rich88',
                            'game_id' => $request->game_code,
                            'game_name' => $request->game_code,
                            'game_type' => 'Slot',
                            'win' => $amount,
                        ]);

                        (new Payment())->saveLog([
                            'amount' => $amount,
                            'before_balance' => $wallet_amount_before,
                            'after_balance' => $wallet_amount_before + $amount,
                            'action' => 'SETTLE',
                            'provider' => 'RICH88',
                            'game_type' => 'SLOT',
                            'game_ref' => 'NO REFERENCE',
                            'transaction_ref' => $request->record_id,
                            'player_username' => $username,
                        ]);
                        /// save log 
                        $logres = $this->savaTransaction("deposit", $wallet_amount_before, $wallet_amount_after, $request);
                        if (!$logres) {
                            throw new \Exception('Fail (System Error)', 22008);
                        }
                    }
                } else {
                    throw new \Exception('Withdraw transfer ID is non-existent', 22005);
                }
            } else {
                throw new \Exception('Invalid params', 20008);
            }

            DB::commit();
            return [
                "code" => 0,
                "msg" => "Success"
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
            ];
        }
    }

    public function bonusWin(Request $request)
    {
        // Log::alert("==============>bonusWin");
        // Log::debug($request);
        $username = $request->account;
        $amount = $request->money;
        $award_id = $request->award_id;
        $activity_type = $request->activity_type;
        $game_code = $request->game_code;

        DB::beginTransaction();
        try {
            $userWallet = User::where('username', $username)->lockForUpdate()->first();
            if (!$userWallet) {
                throw new \Exception('Token authentication error', 16002);
            }
            $wallet_amount_before = $userWallet->main_wallet;
            $wallet_amount_after = $userWallet->main_wallet;

            $check_transaction = Rich88Game::select('id')
                ->where('action', 'bonusWin')
                ->where('award_id', $award_id)
                ->where('award_activity_type', $activity_type)
                ->first();

            if (!$check_transaction) {
                $wallet_amount_after = $wallet_amount_after + $amount;
                User::where('username', $username)->update([
                    'main_wallet' => $wallet_amount_after
                ]);
                /// save log 
                $logres = $this->savaTransaction("bonusWin", $wallet_amount_before, $wallet_amount_after, $request);
                if (!$logres) {
                    throw new \Exception('Fail (System Error)', 22008);
                }
            }

            Payment::updatePlayerWinLossReport([
                'report_type' => 'Hourly',
                'player_id' => $userWallet->id,
                'partner_id' => $userWallet->partner_id,
                'provider_id' => 1,
                'provider_name' => 'Rich88',
                'game_id' => $game_code,
                'game_name' => 'Unknown',
                'game_type' => 'Slot',
                'win' => $amount,
            ]);

            (new Payment())->saveLog([
                'amount' => $amount,
                'before_balance' => $wallet_amount_before,
                'after_balance' => $wallet_amount_after,
                'action' => 'BONUS_WIN',
                'provider' => 'RICH88',
                'game_type' => 'SLOT',
                'game_ref' => $activity_type,
                'transaction_ref' => $award_id,
                'player_username' => $username,
            ]);

            DB::commit();
            return [
                "code" => 0,
                "msg" => "Success"
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
            ];
        }
    }


    public function cancelWithdraw(Request $request)
    {
        $record_id = $request->record_id;
        $game_code = $request->game_code;

        DB::beginTransaction();
        try {
            $transaction_bet = Rich88Game::select('id', 'amount', 'username')
                ->where('action', 'withdraw')
                ->where('record_id', $record_id)
                ->first();
            if ($transaction_bet) {

                $userWallet = User::where('username', $transaction_bet->username)->lockForUpdate()->first();
                if (!$userWallet) {
                    throw new \Exception('Token authentication error', 16002);
                }
                $wallet_amount_before = $userWallet->main_wallet;
                $wallet_amount_after = $userWallet->main_wallet;

                $check_transaction = Rich88Game::select('id')
                    ->where('action', 'cancelWithdraw')
                    ->where('record_id', $record_id)
                    ->first();
                if (!$check_transaction) {
                    $wallet_amount_after = $wallet_amount_after + $transaction_bet->amount;

                    User::where('username', $transaction_bet->username)->update([
                        'main_wallet' => $wallet_amount_after
                    ]);

                    /// save log 
                    $logres = $this->savaTransaction("cancelWithdraw", $wallet_amount_before, $wallet_amount_after, (object) array(
                        'account' => $transaction_bet->username,
                        'record_id' => $record_id,
                        'game_code' => $game_code,
                    ));
                    if (!$logres) {
                        throw new \Exception('Fail (System Error)', 22008);
                    }
                }
            } else {
                throw new \Exception('Withdraw transfer ID is non-existent', 22005);
            }

            Payment::updatePlayerWinLossReport([
                'report_type' => 'Hourly',
                'player_id' => $userWallet->id,
                'partner_id' => $userWallet->partner_id,
                'provider_id' => 1,
                'provider_name' => 'Rich88',
                'game_id' => $game_code,
                'game_name' => $game_code,
                'game_type' => 'Slot',
                'cancel' => $transaction_bet->amount,
            ]);

            (new Payment())->saveLog([
                'amount' => $transaction_bet->amount,
                'before_balance' => $wallet_amount_before,
                'after_balance' => $wallet_amount_after,
                'action' => 'CANCEL',
                'provider' => 'RICH88',
                'game_type' => 'SLOT',
                'game_ref' => $game_code,
                'transaction_ref' => $record_id,
                'player_username' => $transaction_bet->username,
            ]);


            DB::commit();
            return [
                "code" => 0,
                "msg" => "Success"
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
            ];
        }
    }


    private function savaTransaction($action, $wallet_amount_before, $wallet_amount_after, $payload)
    {
        try {
            $transaction  = new Rich88Game();
            $transaction->action = $action;
            $transaction->wallet_amount_before = $wallet_amount_before;
            $transaction->wallet_amount_after = $wallet_amount_after;

            $transaction->username = $payload->account;
            $transaction->amount = $payload->money ? $payload->money : 0;
            $transaction->game_code = $payload->game_code;
            $transaction->currency = $payload->currency;

            $transaction->round_id = $payload->round_id;
            $transaction->transfer_no = $payload->transfer_no;
            $transaction->record_id = $payload->record_id;


            // Bouns
            $transaction->award_id = $payload->award_id;
            $transaction->award_event_id = $payload->event_id;
            $transaction->award_activity_type = $payload->activity_type;

            if ($transaction->save()) {
                return $transaction;
            }
        } catch (\Exception $e) {
            // echo $e;
            return false;
        }
    }
}
