<?php

namespace App\Http\Controllers;

use App\Classes\Payment;
use App\Models\JiliGam;
use App\Models\User;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class JiliGamController extends Controller
{
    //
    private $host = "https://wb-api.jlfafafa2.com";
    private $agentId = "ZF094_ 777bet";
    private $agentKey = "0cb55ba8492209aaa5ca4dffe7d9b9741f46450b";
    private $gameLang = "th-TH";
    private $PREFIX = '77B';

    private function encryptBody($queryString)
    {
        date_default_timezone_set('America/New_York');
        $date = new \DateTime(Carbon::now());
        // echo $date->format("Y-m-d H:i:sP") . "\n";

        $currentTime =  $date->format('ymj');
        // echo "currentTime ==>" . $currentTime . "<br/>";
        $keyG = md5($currentTime . $this->agentId . $this->agentKey);

        $md5string = md5($queryString . $keyG);
        $key = Str::random(6) . $md5string . Str::random(6);
        return $key;
    }

    public function index()
    {
        $queryString = "AgentId=$this->agentId";
        $key = $this->encryptBody($queryString);

        try {
            $client = new Client();
            $res = $client->request("POST", $this->host . "/api1/GetGameList?AgentId={$this->agentId}&Key={$key}", [
                "form_params" => [
                    "AgentId" =>  $this->agentId,
                    "Key" =>  $key,
                ]
            ]);
            // echo $res->getStatusCode();
            // echo $res->getHeader("content-type")[0];
            // echo $res->getBody();
            $response = $res->getBody();
            return $response;
        } catch (\Exception $e) {
            return $e;
        }
    }

    public function login($userToken)
    {
        $gameId = 80;
        $user = User::where('token', $userToken)->first();
        if (empty($user)) {
            $response = ["message" => "Oops! The user does not exist"];
            return response($response, 401);
            exit();
        }
        $userToken = $user->token;
        $currency = $user->currency;
        if ($currency != 'THB') {
            $this->gameLang = 'en-US';
        }
        // $userToken = $user->current;

        $queryString = "Token={$userToken}&GameId={$gameId}&Lang={$this->gameLang}&AgentId={$this->agentId}";
        $key = $this->encryptBody($queryString);

        try {
            $client = new Client();
            $res = $client->request("POST", $this->host . "/singleWallet/LoginWithoutRedirect?{$queryString}&Key={$key}", [
                "form_params" => [
                    "AgentId" =>  $this->agentId,
                    "Key" =>  $key,
                ]
            ]);
            // return ['queryString' => $queryString, 'url' => $this->host . "/singleWallet/LoginWithoutRedirect?{$queryString}&Key={$key}", "form_params" => [
            //     "AgentId" =>  $this->agentId,
            //     "Key" =>  $key,
            // ]];
            // echo $res->getStatusCode();
            // echo $res->getHeader("content-type")[0];
            // return $res->getBody();
            $response = $res->getBody();
            // return $response;

            $json = json_decode($response);
            if ($json->ErrorCode == 0) {
                return redirect($json->Data);
            } else {
                return $response;
            }
        } catch (\Exception $e) {
            return $e;
        }
    }

    public function auth(Request $request)
    {
        // Log::debug($request);
        $reqId = $request->reqId;
        $userToken = $request->token;
        if (empty($reqId) || empty($userToken)) {
            return [
                "errorCode" => 5,
                "message" => "Invalid parameters"
            ];
        }

        $user = User::where("token", $userToken)->first();
        if (!$user) {
            return [
                "errorCode" => 5,
                "message" => "Invalid user"
            ];
        }
        return [
            "errorCode" => 0,
            "message" => "success",
            "username" => $this->PREFIX . $user->username,
            "currency" => $user->currency,
            "balance" => $user->main_wallet,
            "token" => $user->token,
        ];
    }

    public function bet(Request $request)
    {
        return [
            "errorCode" => 1018,
            "message" => "Not Enough Balance"
        ];
        // Log::info("JILI bet==================>");
        // Log::debug($request);

        $reqId = $request->reqId;
        $token = $request->token;
        $betAmount = $request->betAmount;
        $winloseAmount = $request->winloseAmount;

        $user = User::where("token", $token)->first();
        if (!$user) {
            return [
                "errorCode" => 5,
                "message" => "Invalid user"
            ];
        }
        $username = $user->username;
        $main_wallet = $user->main_wallet;

        // if ($user->partner_id != 3) {
        //     return [
        //         "errorCode" => 1018,
        //         "message" => "Not Enough Balance"
        //     ];
        // }

        DB::beginTransaction();
        try {
            // ** ถ้ามีการวางเดิมพันมากกว่า 150 ไม่ให้วางเดิมพัน
            // if ($betAmount > 150) {
            //     throw new \Exception('Not Enough Balance', 1018);
            // }
            $userWallet = User::where('username', $username)->lockForUpdate()->first();
            $wallet_amount_before = $userWallet->main_wallet;
            $wallet_amount_after = $userWallet->main_wallet;

            if ($wallet_amount_before < $betAmount) {
                throw new \Exception('Not Enough Balance', 1018);
            } else if ($betAmount != $winloseAmount) {
                $bet = $this->checkTransactionHistory('bet', $token, $reqId);
                if (!$bet) {

                    $wallet_amount_after = $wallet_amount_after - $betAmount + $winloseAmount;

                    User::where('username', $username)->update([
                        'main_wallet' => $wallet_amount_after
                    ]);

                    if (!$this->savaTransaction('bet', $wallet_amount_before, $wallet_amount_after, $request, $username)) {
                        throw new \Exception('Fail (System Error)', 5);
                    }
                } else {
                    throw new \Exception('Already accepted', 1);
                }
            }

            Payment::updatePlayerWinLossReport([
                'report_type' => 'Hourly',
                'player_id' => $user->id,
                'partner_id' => $user->partner_id,
                'provider_id' => 1,
                'provider_name' => 'Jili',
                'game_id' => $request->game,
                'game_name' => 'Unknown',
                'game_type' => 'Slot',
                'win' => $request->winloseAmount,
                'loss' => $request->betAmount,
            ]);

            (new Payment())->payAll($userWallet->id, $betAmount, 'SLOT');
            (new Payment())->saveLog([
                'amount' => $request->betAmount,
                'before_balance' => $wallet_amount_before,
                'after_balance' => $wallet_amount_before - $request->betAmount + $request->winloseAmount,
                'action' => 'BET',
                'provider' => 'JILI',
                'game_type' => 'SLOT',
                'game_ref' => $request->game . ' [ชนะ: ' . number_format($request->winloseAmount, 2) . ']',
                'transaction_ref' => $request->round,
                'player_username' => $username,
            ]);

            DB::commit();
            return [
                "errorCode" => 0,
                "message" => "success",
                "username" => $username,
                "currency" => $user->currency,
                "balance" => $wallet_amount_after,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                "errorCode" => $e->getCode(),
                "message" => $e->getMessage(),
                "username" => $username,
                "currency" => $user->currency,
                "balance" => $main_wallet,
            ];
        }
    }
    public function cancelBet(Request $request)
    {
        // Log::info("JILI cancelBet==================>");
        // return [
        //     "errorCode" => 5,
        //     "message" => "Fail (System Error)"
        // ];
        // Log::debug($request);

        $reqId = $request->reqId;
        $token = $request->token;
        $betAmount = $request->betAmount;
        $winloseAmount = $request->winloseAmount;

        $user = User::where("token", $token)->first();
        if (!$user) {
            return [
                "errorCode" => 5,
                "message" => "Invalid user"
            ];
        }
        $username = $user->username;
        $main_wallet = $user->main_wallet;

        DB::beginTransaction();
        try {
            $userWallet = User::where('username', $username)->lockForUpdate()->first();
            $wallet_amount_before = $userWallet->main_wallet;
            $wallet_amount_after = $userWallet->main_wallet;

            if ($betAmount != $winloseAmount) {
                $cancelBet = $this->checkTransactionHistory('cancelBet', $token, $reqId);
                if (!$cancelBet) {
                    $wallet_amount_after = $wallet_amount_after + $betAmount;
                    User::where('username', $username)->update([
                        'main_wallet' => $wallet_amount_after
                    ]);
                    if (!$this->savaTransaction('cancelBet', $wallet_amount_before, $wallet_amount_after, $request, $username)) {
                        throw new \Exception('Fail (System Error)', 5);
                    }
                } else {
                    throw new \Exception('Already accepted', 1);
                }
            }

            Payment::updatePlayerWinLossReport([
                'report_type' => 'Hourly',
                'player_id' => $user->id,
                'partner_id' => $user->partner_id,
                'provider_id' => 1,
                'provider_name' => 'Jili',
                'game_id' => $request->game,
                'game_name' => 'Unknown',
                'game_type' => 'Slot',
                'cancel' => $betAmount,
            ]);

            (new Payment())->saveLog([
                'amount' => $request->betAmount,
                'before_balance' => $wallet_amount_before,
                'after_balance' => $wallet_amount_after,
                'action' => 'CANCEL',
                'provider' => 'JILI',
                'game_type' => 'SLOT',
                'game_ref' => 'คืนเงิน: ' . $request->betAmount,
                'transaction_ref' => '',
                'player_username' => $username,
            ]);

            DB::commit();
            return [
                "errorCode" => 0,
                "message" => "success",
                "username" => $username,
                "currency" => $user->currency,
                "balance" => $wallet_amount_after,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                "errorCode" => $e->getCode(),
                "message" => $e->getMessage(),
                "username" => $username,
                "currency" => $user->currency,
                "balance" => $main_wallet,
            ];
        }
    }

    public function sessionBet(Request $request)
    {
        // Log::info("JILI sessionBet==================>");
        // Log::debug($request);
    }

    public function cancelSessionBet(Request $request)
    {
        // Log::info("JILI cancelSessionBet==================>");
        // Log::debug($request);
    }


    private function checkTransactionHistory($action, $token, $reqId)
    {
        $transaction  = JiliGam::where('reqId', '=', $reqId)
            ->where('token', '=', $token);
        if ($action) {
            $transaction = $transaction->where('action', $action);
        }
        $transaction = $transaction->orderByDesc('id')->first();
        // Log::debug($transaction);
        return $transaction;
    }
    private function savaTransaction($action, $wallet_amount_before, $wallet_amount_after, $payload, $username)
    {

        date_default_timezone_set("Asia/Bangkok");
        $dtTemp = gmdate("Y-m-d H:i:s", $payload->wagersTime);
        $now = new \DateTime($dtTemp);
        $now->add(new \DateInterval("PT7H"));
        $dateTime = $now->format("Y-m-d H:i:s");

        try {
            $transaction  = new JiliGam();
            $transaction->action = $action;
            $transaction->wallet_amount_before = $wallet_amount_before;
            $transaction->wallet_amount_after = $wallet_amount_after;
            $transaction->token = $payload->token;
            $transaction->username = $username;
            $transaction->reqId = $payload->reqId;
            $transaction->game = $payload->game;
            $transaction->round = $payload->round;
            $transaction->betAmount = $payload->betAmount;
            $transaction->winloseAmount = $payload->winloseAmount;
            $transaction->wagersTime = $dateTime;
            if ($transaction->save()) {
                return $transaction->id;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            echo $e;
            return false;
        }
    }
}
