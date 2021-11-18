<?php

namespace App\Http\Controllers;

use App\Models\JiliGam;
use App\Models\User;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Constant;

date_default_timezone_set('America/New_York');
class JiliGamController extends Controller
{
    //
    private $host = "https://wb-api.jlfafafa2.com";
    private $agentId = "ZF084_NasaVG";
    private $agentKey = "8fc9781160422a4caae0e18107e4e27f41f287aa";
    private $currencyCode = "THB";
    private $gameLang = "en-US";

    private function encryptBody($queryString)
    {
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
            // echo $res->getStatusCode();
            // echo $res->getHeader("content-type")[0];
            // echo $res->getBody();
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
        Log::debug($request);
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
            "username" => $user->username,
            "currency" => $this->currencyCode,
            "balance" => $user->main_wallet,
            "token" => $user->token,
        ];
    }

    public function bet(Request $request)
    {
        Log::info("JILI bet==================>");
        Log::debug($request);

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

            if ($wallet_amount_before < $betAmount) {
                throw new \Exception('Not Enough Balance', 1018);
            } else if ($betAmount != $winloseAmount) {
                $bet = $this->checkTransactionHistory('bet', $token, $reqId);
                if (!$bet) {
                    $wallet_amount_after = $wallet_amount_after - $betAmount + $winloseAmount;

                    // จ่ายคืนยอดเสีย
                    if($userWallet->is_promotion == 0) {
                        $percentRefund = Constant::where('variable', 'PERCENT_REFUND')->first()->value;
                        $userWallet->refund_wallet = $userWallet->refund_wallet + ($betAmount * $percentRefund / 100);
                    }
                    $userWallet->turnover = $userWallet->turnover + $betAmount;
                    $userWallet->save();
                    // จ่าย AFF
                    if($userWallet->invitor_token) {
                        $invitor = User::where('token', $userWallet->invitor_token)->lockForUpdate()->first();
                        if($invitor) {
                            $percentAffiliate = Constant::where('variable', 'PERCENT_AFFILIATE')->first()->value;
                            $invitor->aff_wallet = $invitor->aff_wallet + ($betAmount * $percentAffiliate / 100);
                            $invitor->save();
                            if($invitor->invitor_token) {
                                $children = User::where('token', $invitor->invitor_token)->lockForUpdate()->first();
                                if($children) {
                                    $percentAffiliateStep2 = Constant::where('variable', 'PERCENT_AFFILIATE_STEP_2')->first()->value;
                                    $children->aff_wallet = $children->aff_wallet + ($betAmount * $percentAffiliateStep2 / 100);
                                    $children->save();
                                }
                            }
                        }
                    }

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
            DB::commit();
            return [
                "errorCode" => 0,
                "message" => "success",
                "username" => $username,
                "currency" => $this->currencyCode,
                "balance" => $wallet_amount_after,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                "errorCode" => $e->getCode(),
                "message" => $e->getMessage(),
                "username" => $username,
                "currency" => $this->currencyCode,
                "balance" => $main_wallet,
            ];
        }
    }
    public function cancelBet(Request $request)
    {
        Log::info("JILI cancelBet==================>");
        Log::debug($request);

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
                    $wallet_amount_after = $wallet_amount_after + $betAmount - $winloseAmount;
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
            DB::commit();
            return [
                "errorCode" => 0,
                "message" => "success",
                "username" => $username,
                "currency" => $this->currencyCode,
                "balance" => $wallet_amount_after,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                "errorCode" => $e->getCode(),
                "message" => $e->getMessage(),
                "username" => $username,
                "currency" => $this->currencyCode,
                "balance" => $main_wallet,
            ];
        }
    }

    public function sessionBet(Request $request)
    {
        Log::info("JILI sessionBet==================>");
        Log::debug($request);
    }

    public function cancelSessionBet(Request $request)
    {
        Log::info("JILI cancelSessionBet==================>");
        Log::debug($request);
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
