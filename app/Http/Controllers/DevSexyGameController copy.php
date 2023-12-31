<?php

namespace App\Http\Controllers;

use App\Classes\Payment;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\SexyGame;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DevSexyGameController extends Controller
{
    private $host = "https://tttint.onlinegames22.com";
    private $certCode = "heVfNAYIoldStY4TSw5";
    private $agentId = "cullinan";
    private $currencyCode = "MMK";
    private $language = "th";
    private $betLimit = '{"SEXYBCRT":{"LIVE":{"limitId":[260901,260902,260903,260904,260905]}}}';

    public function login($username, $gameType)
    {
        $form_params = [];
        $method = 'login';
        if ($gameType == 'sexy') {
            $method = 'doLoginAndLaunchGame';
            $form_params = [
                'cert' =>  $this->certCode,
                'agentId' =>  $this->agentId,
                'userId' =>  $username,
                'gameCode'   => 'MX-LIVE-001',
                'gameType' =>  'LIVE',
                'platform' =>  'SEXYBCRT',
                'isMobileLogin' =>  true,
                'gameForbidden' => '{"KINGMAKER":{"ALL":["ALL"]},"RT":{"ALL":["ALL"]},"JILI":{"ALL":["ALL"]}}',
                'hall' => 'SEXY',
                'language' =>  $this->language,
            ];
        } else if ($gameType == 'rt') {
            $form_params = [
                'agentId' =>  $this->agentId,
                'cert' =>  $this->certCode,
                'userId' =>  $username,
                'gameCode'   => '',
                'gameType' =>  'SLOT',
                'platform' =>  'RT',
                'isMobileLogin' =>  true,
                'gameForbidden' => '{"SEXYBCRT":{"ALL":["ALL"]},"KINGMAKER":{"ALL":["ALL"]},"RT":{"TABLE":["ALL"]},"JILI":{"ALL":["ALL"]}}',
                'language' =>  $this->language,
            ];
        } else if ($gameType == 'km') {
            $form_params = [
                'agentId' =>  $this->agentId,
                'cert' =>  $this->certCode,
                'userId' =>  $username,
                'gameCode'   => '',
                'gameType' =>  'TABLE',
                'platform' =>  'KINGMAKER',
                'isMobileLogin' =>  true,
                'gameForbidden' => '{"SEXYBCRT":{"ALL":["ALL"]},"RT":{"SLOT":["ALL"]},"JILI":{"SLOT":["ALL"]},"KINGMAKER":{"TABLE":["KM-TABLE-033"]}}',
                'language' =>  $this->language,
            ];
        }

        $user = User::where('username', $username)->first();
        if (empty($user)) {
            $response = ["message" => 'Oops! The user does not exist'];
            return response($response, 401);
            exit();
        }

        try {
            $client = new Client();
            $res = $client->request('POST', $this->host . '/wallet/' . $method, [
                'form_params' => $form_params
            ]);
            $response = $res->getBody();
            if ($response) {
                $json = json_decode($response);
                if ($json->status == '0000') {
                    return redirect($json->url);
                } else if ($json->status == '1028') {
                    return $this->login($username, $gameType);
                } else if ($json->status == '1002') {
                    return $responsenewmember = $this->createMember($username);
                    if (!$responsenewmember) {
                        return "error create member";
                    } else if ($responsenewmember->status == '0000') {
                        return $this->login($username, $gameType);
                    } else {
                        return $responsenewmember;
                    }
                } else {
                    return $json;
                }
            }
        } catch (BadResponseException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ]);
        }
    }

    private function createMember($username)
    {
        try {
            $betLimit = $this->betLimit;
            if ($this->currencyCode == 'MMK') {
                $betLimit = '{"SEXYBCRT":{"LIVE":{"limitId":[262501,262502,262503,262504,262505,262506]}}}';
            }
            $client = new Client();
            $url = $this->host . '/wallet/createMember';
            $form_params = [
                'cert' =>  $this->certCode,
                'agentId' =>  $this->agentId,
                'userId' =>  $username,
                'currency' =>  $this->currencyCode,
                'language' =>  $this->language,
                'betLimit' =>  $betLimit,
            ];
            $res = $client->request('POST', $url, [
                'form_params' => $form_params
            ]);
            $response = $res->getBody()->getContents();
            if ($response) {
                $json = json_decode($response);
                return $json;
            } else {
                return false;
            }
        } catch (BadResponseException $e) {
            return false;
        }
    }

    public function tsDateISOString($keepOffset = false)
    {
        $date = Carbon::now();
        if (!$date->isValid()) {
            return null;
        }
        $yearFormat = $date->year < 0 || $date->year > 9999 ? 'YYYYYY' : 'YYYY';
        $tzFormat = $keepOffset ? 'Z' : '[Z]';
        $date = $keepOffset ? $date : $date->avoidMutation()->utc();

        return $date->isoFormat("$yearFormat-MM-DD[T]HH:mm:ss.SSS$tzFormat");
    }

    public function getBalance(Request $request)
    {

        $message = json_decode($request->message, true);
        $wallet_amount_after = 0;
        $action = $message['action'];
        if ($action != "getBalance") {
            Log::debug($request);
        }
        switch ($action) {
            case "getBalance":;
                try {
                    $username = $message["userId"];
                    $userWallet = User::select('main_wallet')->where('username', $username)->first();
                    if ($userWallet) {
                        $main_wallet = $userWallet->main_wallet;
                    } else {
                        throw new \Exception('Invalid user Id', 1000);
                    }
                    return [
                        "userId" => $username,
                        "balance" => $main_wallet,
                        "balanceTs" =>  $this->tsDateISOString(),
                        "status" => "0000"
                    ];
                } catch (\Exception $e) {
                    return [
                        "status" => "" . $e->getCode(),
                        "desc" => $e->getMessage()
                    ];
                }
                break;
            case "bet":;
                DB::beginTransaction();
                try {
                    foreach ($message['txns'] as $element) {
                        $userWallet = User::where('username', $element['userId'])->lockForUpdate()->first();
                        if ($userWallet) {
                            $wallet_amount_before = $userWallet->main_wallet;
                            $wallet_amount_after = $userWallet->main_wallet;

                            $betTransaction = $this->checkTransactionHistory('bet', $element);
                            if (!$betTransaction) {
                                if ($wallet_amount_before > 0 && $wallet_amount_before >= $element["betAmount"]) {
                                    /// cancel bet before bet 
                                    if (!$this->checkTransactionHistory('cancelBet', $element)) {
                                        $wallet_amount_after = $wallet_amount_after - $element['betAmount'];
                                    }

                                    User::where('username', $element['userId'])->update([
                                        'main_wallet' => $wallet_amount_after
                                    ]);

                                    (new Payment())->payAll($userWallet->id, $element['betAmount'], 'CASINO');
                                    $payload = $element;
                                    (new Payment())->saveLog([
                                        'amount' => $payload['betAmount'],
                                        'before_balance' => $wallet_amount_before,
                                        'after_balance' => $wallet_amount_before - $payload['betAmount'],
                                        'action' => 'BET',
                                        'provider' => $payload["platform"],
                                        'game_type' => !empty($payload["gameType"]) ? $payload["gameType"] : null,
                                        'game_ref' => !empty($payload["gameName"]) ? $payload["gameName"] : null,
                                        'transaction_ref' => !empty($payload["platformTxId"]) ? $payload["platformTxId"] : $payload["promotionTxId"],
                                        'player_username' => $payload['userId'],
                                    ]);

                                    if (!$this->savaTransaction($wallet_amount_before, $wallet_amount_after, $element, $message)) {
                                        throw new \Exception('Fail (System Error)', 9999);
                                    }

                                    // Log::info([
                                    //     'action' => $action,
                                    //     'wallet_amount_before' => $wallet_amount_before,
                                    //     'betAmount' => $element["betAmount"],
                                    //     'wallet_amount_after' => $wallet_amount_after,
                                    // ]);
                                } else {
                                    throw new \Exception('Not Enough Balance', 1018);
                                }
                            }
                        } else {
                            throw new \Exception('Invalid user Id', 1000);
                        }
                    }

                    DB::commit();
                    return [
                        "balance" => $wallet_amount_after,
                        "balanceTs" => $this->tsDateISOString(),
                        "status" => "0000"
                    ];
                } catch (\Exception $e) {
                    DB::rollBack();
                    return [
                        "status" => "" . $e->getCode(),
                        "desc" => $e->getMessage()
                    ];
                }
                break;
            case "cancelBet":; // *!!!!!!!!!!333333333333333333333333333333333333333333
                DB::beginTransaction();
                try {
                    foreach ($message['txns'] as $element) {
                        $userWallet = User::where('username', $element['userId'])->lockForUpdate()->first();
                        if ($userWallet) {
                            $wallet_amount_before = $userWallet->main_wallet;
                            $wallet_amount_after = $userWallet->main_wallet;

                            $betTransaction = $this->checkTransactionHistory('bet', $element);
                            if ($betTransaction) {
                                $cancelBetTransaction = $this->checkTransactionHistory('cancelBet', $element);
                                if (!$cancelBetTransaction) {
                                    $wallet_amount_after = $wallet_amount_after + $betTransaction->betAmount;

                                    User::where('username', $element['userId'])->update([
                                        'main_wallet' => $wallet_amount_after
                                    ]);

                                    Log::info([
                                        'action' => $action,
                                        'wallet_amount_before' => $wallet_amount_before,
                                        'wallet_amount_after' => $wallet_amount_after,

                                        'element' => $element,
                                        'message' => $message,
                                    ]);
                                    if (!$this->savaTransaction($wallet_amount_before, $wallet_amount_after, $element, $message)) {
                                        throw new \Exception('Fail (System Error)', 9999);
                                    }
                                }
                            } else {
                                if (!$this->savaTransaction($wallet_amount_before, $wallet_amount_after, $element, $message)) {
                                    throw new \Exception('Fail (System Error)', 9999);
                                }
                            }
                        } else {
                            throw new \Exception('Invalid user Id', 1000);
                        }
                    }
                    DB::commit();
                    return [
                        "balance" => $wallet_amount_after,
                        "balanceTs" => $this->tsDateISOString(),
                        "status" => "0000"
                    ];
                } catch (\Exception $e) {
                    DB::rollBack();
                    return [
                        "status" => "" . $e->getCode(),
                        "desc" => $e->getMessage()
                    ];
                }
                break;
            case "voidBet":;
                DB::beginTransaction();
                try {
                    foreach ($message['txns'] as $element) {
                        $userWallet = User::where('username', $element['userId'])->lockForUpdate()->first();
                        if ($userWallet) {
                            $wallet_amount_before = $userWallet->main_wallet;
                            $wallet_amount_after = $userWallet->main_wallet;

                            $betTransaction = $this->checkTransactionHistory('bet', $element);
                            if ($betTransaction) {
                                if ($element["betAmount"] == $betTransaction->betAmount) {
                                    $voidBetTransaction = $this->checkTransactionHistory('voidBet', $element);
                                    if (!$voidBetTransaction) {
                                        $wallet_amount_after = $wallet_amount_after + $element["betAmount"];

                                        User::where('username', $element['userId'])->update([
                                            'main_wallet' => $wallet_amount_after
                                        ]);

                                        if (!$this->savaTransaction($wallet_amount_before, $wallet_amount_after, $element, $message)) {
                                            throw new \Exception('Fail (System Error)', 9999);
                                        }
                                    }
                                }
                            }
                        } else {
                            throw new \Exception('Invalid user Id', 1000);
                        }
                    }
                    DB::commit();
                    return [
                        "status" => "0000"
                    ];
                } catch (\Exception $e) {
                    DB::rollBack();
                    return [
                        "status" => "" . $e->getCode(),
                        "desc" => $e->getMessage()
                    ];
                }
                break;
            case "unvoidBet":;
                DB::beginTransaction();
                try {
                    foreach ($message['txns'] as $element) {
                        $userWallet = User::where('username', $element['userId'])->lockForUpdate()->first();
                        if ($userWallet) {
                            $wallet_amount_before = $userWallet->main_wallet;
                            $wallet_amount_after = $userWallet->main_wallet;

                            $voidBetTransaction = $this->checkTransactionHistory('voidBet', $element);
                            if ($voidBetTransaction) {
                                $unVoidBetTransaction = $this->checkTransactionHistory('unvoidBet', $element);
                                if (!$unVoidBetTransaction) {
                                    $wallet_amount_after = $wallet_amount_after - $voidBetTransaction->betAmount;

                                    User::where('username', $element['userId'])->update([
                                        'main_wallet' => $wallet_amount_after
                                    ]);

                                    if (!$this->savaTransaction($wallet_amount_before, $wallet_amount_after, $element, $message)) {
                                        throw new \Exception('Fail (System Error)', 9999);
                                    }
                                }
                            }
                        } else {
                            throw new \Exception('Invalid user Id', 1000);
                        }
                    }
                    DB::commit();
                    return [
                        "status" => "0000"
                    ];
                } catch (\Exception $e) {
                    DB::rollBack();
                    return [
                        "status" => "" . $e->getCode(),
                        "desc" => $e->getMessage()
                    ];
                }
                break;
            case "adjustBet":;
                try {
                    $username = !empty($message["userId"]) ? $message["userId"] : $message["txns"][0]["userId"];
                    $userWallet = User::select('main_wallet')->where('username', $username)->first();
                    if ($userWallet) {
                        $main_wallet = $userWallet->main_wallet;
                    } else {
                        throw new \Exception('Invalid user Id', 1000);
                    }
                    return [
                        "balance" => $main_wallet,
                        "balanceTs" => $this->tsDateISOString(),
                        "status" => "0000"
                    ];
                } catch (\Exception $e) {
                    return [
                        "status" => "" . $e->getCode(),
                        "desc" => $e->getMessage()
                    ];
                }
                break;
            case "settle":;
                DB::beginTransaction();
                try {
                    foreach ($message['txns'] as $element) {
                        $userWallet = User::where('username', $element['userId'])->lockForUpdate()->first();
                        if ($userWallet) {
                            $wallet_amount_before = $userWallet->main_wallet;
                            $wallet_amount_after = $userWallet->main_wallet;

                            $settleTransaction = $this->checkTransactionHistory('settle', $element);
                            if ($settleTransaction) {
                                $lastAction = $this->checkTransactionHistory('', $element);
                                if ($lastAction->action == "unsettle") {
                                    $settleTransaction = false; /// re settle
                                }
                            }

                            if (!$settleTransaction) {
                                $wallet_amount_after = $wallet_amount_after + $element["winAmount"];

                                User::where('username', $element['userId'])->update([
                                    'main_wallet' => $wallet_amount_after
                                ]);

                                $payload = $element;
                                $winloss = !empty($payload["winAmount"]) ? $payload["winAmount"] : 0;
                                (new Payment())->saveLog([
                                    'amount' => $winloss,
                                    'before_balance' => $wallet_amount_before,
                                    'after_balance' => $wallet_amount_before + $winloss,
                                    'action' => 'SETTLE',
                                    'provider' => $payload["platform"],
                                    'game_type' => !empty($payload["gameType"]) ? $payload["gameType"] : null,
                                    'game_ref' => !empty($payload["gameName"]) ? $payload["gameName"] : null,
                                    'transaction_ref' => !empty($payload["platformTxId"]) ? $payload["platformTxId"] : $payload["promotionTxId"],
                                    'player_username' => $payload['userId'],
                                ]);

                                Log::info([
                                    "action" => $action,
                                    // "userWallet" => $userWallet,
                                    "wallet_amount_before" => $wallet_amount_before,
                                    "wallet_amount_after" => $wallet_amount_after,
                                    "main_wallet"=>  $userWallet->main_wallet,
                                    "winAmount"=> $element["winAmount"],
                                    "betAmount"=> $element["betAmount"]


                                ]);
                                if (!$this->savaTransaction($wallet_amount_before, $wallet_amount_after, $element, $message)) {
                                    throw new \Exception('Fail (System Error)', 9999);
                                }
                            }
                        } else {
                            throw new \Exception('Invalid user Id', 1000);
                        }
                    }
                    DB::commit();
                    return [
                        "status" => "0000"
                    ];
                } catch (\Exception $e) {
                    DB::rollBack();
                    return [
                        "status" => "" . $e->getCode(),
                        "desc" => $e->getMessage()
                    ];
                }
                break;
            case "unsettle":;
                DB::beginTransaction();
                try {
                    foreach ($message['txns'] as $element) {
                        $userWallet = User::where('username', $element['userId'])->lockForUpdate()->first();
                        if ($userWallet) {
                            $wallet_amount_before = $userWallet->main_wallet;
                            $wallet_amount_after = $userWallet->main_wallet;

                            $settleTransaction = $this->checkTransactionHistory('settle', $element);
                            if ($settleTransaction) {
                                if ($element["betAmount"] == $settleTransaction->betAmount) {
                                    $unSettleTransaction = $this->checkTransactionHistory('unsettle', $element);

                                    if ($unSettleTransaction) {
                                        $lastAction = $this->checkTransactionHistory('', $element);
                                        if ($lastAction->action == "settle") {
                                            $unSettleTransaction = false; /// re settle
                                        }
                                    }

                                    if (!$unSettleTransaction) {
                                        $wallet_amount_after = $wallet_amount_after - $settleTransaction->winAmount;

                                        User::where('username', $element['userId'])->update([
                                            'main_wallet' => $wallet_amount_after
                                        ]);

                                        if (!$this->savaTransaction($wallet_amount_before, $wallet_amount_after, $element, $message)) {
                                            throw new \Exception('Fail (System Error)', 9999);
                                        }
                                    }
                                } else {
                                    throw new \Exception('Invalid amount', 1010);
                                }
                            }
                        } else {
                            throw new \Exception('Invalid user Id', 1000);
                        }
                    }
                    DB::commit();
                    return [
                        "status" => "0000"
                    ];
                } catch (\Exception $e) {
                    DB::rollBack();
                    return [
                        "status" => "" . $e->getCode(),
                        "desc" => $e->getMessage()
                    ];
                }
                break;
            case "voidSettle":;
                DB::beginTransaction();
                try {
                    foreach ($message['txns'] as $element) {
                        $userWallet = User::where('username', $element['userId'])->lockForUpdate()->first();
                        if ($userWallet) {
                            $wallet_amount_before = $userWallet->main_wallet;
                            $wallet_amount_after = $userWallet->main_wallet;

                            $settleTransaction = $this->checkTransactionHistory('settle', $element);
                            if ($settleTransaction) {
                                if ($element["betAmount"] == $settleTransaction->betAmount) {
                                    $voidSettleTransaction = $this->checkTransactionHistory('voidSettle', $element);

                                    if ($voidSettleTransaction) {
                                        $lastAction = $this->checkTransactionHistory('', $element);
                                        if ($lastAction->action == "settle") {
                                            $voidSettleTransaction = false; /// re settle
                                        }
                                    }

                                    if (!$voidSettleTransaction) {
                                        $wallet_amount_after = $wallet_amount_after - $settleTransaction->winAmount + $element["betAmount"];

                                        User::where('username', $element['userId'])->update([
                                            'main_wallet' => $wallet_amount_after
                                        ]);

                                        if (!$this->savaTransaction($wallet_amount_before, $wallet_amount_after, $element, $message)) {
                                            throw new \Exception('Fail (System Error)', 9999);
                                        }
                                    }
                                } else {
                                    throw new \Exception('Invalid amount', 1010);
                                }
                            }
                        } else {
                            throw new \Exception('Invalid user Id', 1000);
                        }
                    }
                    DB::commit();
                    return [
                        "status" => "0000"
                    ];
                } catch (\Exception $e) {
                    DB::rollBack();
                    return [
                        "status" => "" . $e->getCode(),
                        "desc" => $e->getMessage()
                    ];
                }
                break;
            case "unvoidSettle":;
                DB::beginTransaction();
                try {
                    foreach ($message['txns'] as $element) {
                        $userWallet = User::where('username', $element['userId'])->lockForUpdate()->first();
                        if ($userWallet) {
                            $wallet_amount_before = $userWallet->main_wallet;
                            $wallet_amount_after = $userWallet->main_wallet;

                            $voidSettleTransaction = $this->checkTransactionHistory('voidSettle', $element);
                            if ($voidSettleTransaction) {
                                $unvoidSettleTransaction = $this->checkTransactionHistory('unvoidSettle', $element);

                                if ($unvoidSettleTransaction) {
                                    $lastAction = $this->checkTransactionHistory('', $element);
                                    if ($lastAction->action == "voidSettle") {
                                        $voidSettleTransaction = false; /// re settle
                                    }
                                }

                                if (!$unvoidSettleTransaction) {
                                    $wallet_amount_after = $wallet_amount_after + $voidSettleTransaction->winAmount - $element["betAmount"];

                                    User::where('username', $element['userId'])->update([
                                        'main_wallet' => $wallet_amount_after
                                    ]);

                                    if (!$this->savaTransaction($wallet_amount_before, $wallet_amount_after, $element, $message)) {
                                        throw new \Exception('Fail (System Error)', 9999);
                                    }
                                }
                            }
                        } else {
                            throw new \Exception('Invalid user Id', 1000);
                        }
                    }
                    DB::commit();
                    return [
                        "status" => "0000"
                    ];
                } catch (\Exception $e) {
                    DB::rollBack();
                    return [
                        "status" => "" . $e->getCode(),
                        "desc" => $e->getMessage()
                    ];
                }
                break;
            case "betNSettle":; // *!!!!!!!!!!44444444444444444444444444444444444
                DB::beginTransaction();
                try {
                    foreach ($message['txns'] as $element) {
                        $userWallet = User::where('username', $element['userId'])->lockForUpdate()->first();
                        if ($userWallet) {
                            $wallet_amount_before = $userWallet->main_wallet;
                            $wallet_amount_after = $userWallet->main_wallet;

                            if ($wallet_amount_before < $element["betAmount"]) {
                                throw new \Exception('Not Enough Balance', 1018);
                            } else if ($element["betAmount"] != $element["winAmount"]) {
                                $betNSettleTransaction = $this->checkTransactionHistory('betNSettle', $element);
                                if (!$betNSettleTransaction) {

                                    if (!$this->checkTransactionHistory('cancelBetNSettle', $element)) {
                                        $wallet_amount_after = $wallet_amount_after - $element["betAmount"] + $element["winAmount"];
                                    }

                                    User::where('username', $element['userId'])->update([
                                        'main_wallet' => $wallet_amount_after
                                    ]);

                                    if (!$this->savaTransaction($wallet_amount_before, $wallet_amount_after, $element, $message)) {
                                        throw new \Exception('Fail (System Error)', 9999);
                                    }
                                }
                            }
                        } else {
                            throw new \Exception('Invalid user Id', 1000);
                        }
                    }
                    DB::commit();
                    return [
                        "balance" => $wallet_amount_after,
                        "balanceTs" => $this->tsDateISOString(),
                        "status" => "0000"
                    ];
                } catch (\Exception $e) {
                    DB::rollBack();
                    return [
                        "status" => "" . $e->getCode(),
                        "desc" => $e->getMessage()
                    ];
                }
                break;
            case "cancelBetNSettle":; // *!!!!!!!!!!5555555555555555555555555555555555
                DB::beginTransaction();
                try {
                    foreach ($message['txns'] as $element) {
                        $userWallet = User::where('username', $element['userId'])->lockForUpdate()->first();
                        if ($userWallet) {
                            $wallet_amount_before = $userWallet->main_wallet;
                            $wallet_amount_after = $userWallet->main_wallet;

                            $betNSettle = $this->checkTransactionHistory('betNSettle', $element);
                            if ($betNSettle) {
                                $cancelBetNSettle = $this->checkTransactionHistory('cancelBetNSettle', $element);
                                if (!$cancelBetNSettle) {
                                    $wallet_amount_after = $wallet_amount_after + $betNSettle->betAmount;

                                    User::where('username', $element['userId'])->update([
                                        'main_wallet' => $wallet_amount_after
                                    ]);

                                    if (!$this->savaTransaction($wallet_amount_before, $wallet_amount_after, $element, $message)) {
                                        throw new \Exception('Fail (System Error)', 9999);
                                    }
                                }
                            } else {
                                if (!$this->savaTransaction($wallet_amount_before, $wallet_amount_after, $element, $message)) {
                                    throw new \Exception('Fail (System Error)', 9999);
                                }
                            }
                        } else {
                            throw new \Exception('Invalid user Id', 1000);
                        }
                    }
                    DB::commit();
                    return [
                        "balance" => $wallet_amount_after,
                        "balanceTs" => $this->tsDateISOString(),
                        "status" => "0000"
                    ];
                } catch (\Exception $e) {
                    DB::rollBack();
                    return [
                        "status" => "" . $e->getCode(),
                        "desc" => $e->getMessage()
                    ];
                }
                break;
            case "freeSpin":;
                return [
                    'status' => '0000'
                ];
                break;
            case "give":;
                DB::beginTransaction();
                try {
                    foreach ($message['txns'] as $element) {
                        $userWallet = User::where('username', $element['userId'])->lockForUpdate()->first();
                        if ($userWallet) {
                            $wallet_amount_before = $userWallet->main_wallet;
                            $wallet_amount_after = $userWallet->main_wallet;

                            $give = $this->checkTransactionHistory('give', $element);
                            if (!$give) {
                                $wallet_amount_after = $wallet_amount_after + $element["amount"];

                                User::where('username', $element['userId'])->update([
                                    'main_wallet' => $wallet_amount_after
                                ]);

                                if (!$this->savaTransaction($wallet_amount_before, $wallet_amount_after, $element, $message)) {
                                    throw new \Exception('Fail (System Error)', 9999);
                                }
                            }
                        } else {
                            throw new \Exception('Invalid user Id', 1000);
                        }
                    }
                    DB::commit();
                    return [
                        "balance" => $wallet_amount_after,
                        "balanceTs" => $this->tsDateISOString(),
                        "status" => "0000",
                        "desc" => "success",
                    ];
                } catch (\Exception $e) {
                    DB::rollBack();
                    return [
                        "status" => "" . $e->getCode(),
                        "desc" => $e->getMessage()
                    ];
                }
                break;
            case "tip":;
                DB::beginTransaction();
                try {
                    foreach ($message['txns'] as $element) {
                        $userWallet = User::where('username', $element['userId'])->lockForUpdate()->first();
                        if ($userWallet) {

                            if ($userWallet->main_wallet <= 0) {
                                throw new \Exception('Not Enough Balance', 1018);
                            }

                            $wallet_amount_before = $userWallet->main_wallet;
                            $wallet_amount_after = $userWallet->main_wallet;

                            $tip = $this->checkTransactionHistory('tip', $element);
                            if (!$tip) {
                                if (!$this->checkTransactionHistory('cancelTip', $element)) {
                                    $wallet_amount_after = $wallet_amount_after - $element["tip"];
                                }

                                User::where('username', $element['userId'])->update([
                                    'main_wallet' => $wallet_amount_after
                                ]);

                                if (!$this->savaTransaction($wallet_amount_before, $wallet_amount_after, $element, $message)) {
                                    throw new \Exception('Fail (System Error)', 9999);
                                }
                            }
                        } else {
                            throw new \Exception('Invalid user Id', 1000);
                        }
                    }
                    DB::commit();
                    return [
                        "balance" => $wallet_amount_after,
                        "balanceTs" => $this->tsDateISOString(),
                        "status" => "0000",
                        "desc" => "success",
                    ];
                } catch (\Exception $e) {
                    DB::rollBack();
                    return [
                        "status" => "" . $e->getCode(),
                        "desc" => $e->getMessage()
                    ];
                }
                break;
            case "cancelTip":;
                DB::beginTransaction();
                try {
                    foreach ($message['txns'] as $element) {
                        $userWallet = User::where('username', $element['userId'])->lockForUpdate()->first();
                        if ($userWallet) {
                            $wallet_amount_before = $userWallet->main_wallet;
                            $wallet_amount_after = $userWallet->main_wallet;

                            $tip = $this->checkTransactionHistory('tip', $element);
                            if ($tip) {
                                $cancelTip = $this->checkTransactionHistory('cancelTip', $element);
                                if (!$cancelTip) {
                                    $wallet_amount_after = $wallet_amount_after + $tip->betAmount;

                                    User::where('username', $element['userId'])->update([
                                        'main_wallet' => $wallet_amount_after
                                    ]);

                                    if (!$this->savaTransaction($wallet_amount_before, $wallet_amount_after, $element, $message)) {
                                        throw new \Exception('Fail (System Error)', 9999);
                                    }
                                }
                            } else {
                                if (!$this->savaTransaction($wallet_amount_before, $wallet_amount_after, $element, $message)) {
                                    throw new \Exception('Fail (System Error)', 9999);
                                }
                            }
                        } else {
                            throw new \Exception('Invalid user Id', 1000);
                        }
                    }
                    DB::commit();
                    return [
                        "balance" => $wallet_amount_after,
                        "balanceTs" => $this->tsDateISOString(),
                        "status" => "0000",
                        "desc" => "success",
                    ];
                } catch (\Exception $e) {
                    DB::rollBack();
                    return [
                        "status" => "" . $e->getCode(),
                        "desc" => $e->getMessage()
                    ];
                }
                break;
            default:;
                return [
                    'status' => '9999',
                    'desc' => 'Fail (some data not found)'
                ];
                break;
        }
    }
    private function checkTransactionHistory($action, $payload)
    {
        $platformTxId = !empty($payload["platformTxId"]) ? $payload["platformTxId"] : $payload["promotionTxId"];

        $sexyGame = SexyGame::where('platform', '=', $payload['platform'])
            ->where('username', '=', $payload['userId']);
        if ($action) {
            $sexyGame = $sexyGame->where('action', $action);
        }

        if ($action == "settle") {
            if (isset($payload["settleType"]) && $payload["settleType"] == "roundId") {
                $sexyGame = $sexyGame->where('roundId', '=', $payload["roundId"]);
            } else {
                $sexyGame = $sexyGame->where('platformTxId', '=', $platformTxId);
            }
        } else {
            $sexyGame = $sexyGame->where('platformTxId', '=', $platformTxId);
        }
        $sexyGame = $sexyGame->orderByDesc('id')->first();
        // Log::debug($sexyGame);
        return $sexyGame;
    }

    private function savaTransaction($wallet_amount_before, $wallet_amount_after, $payload, $body)
    {
        $betAmount = 0;
        if (!empty($payload["betAmount"])) {
            $betAmount = $payload["betAmount"];
        } else if (!empty($payload["tip"])) {
            $betAmount = $payload["tip"];
        }

        $platformTxId = !empty($payload["platformTxId"]) ? $payload["platformTxId"] : $payload["promotionTxId"];
        try {
            $sexyGame = new SexyGame();
            $sexyGame->username = $payload["userId"];
            $sexyGame->action = $body["action"];
            $sexyGame->wallet_amount_before = $wallet_amount_before;
            $sexyGame->wallet_amount_after = $wallet_amount_after;
            $sexyGame->gameType = !empty($payload["gameType"]) ? $payload["gameType"] : null;
            $sexyGame->gameName = !empty($payload["gameName"]) ? $payload["gameName"] : null;
            $sexyGame->gameCode = !empty($payload["gameCode"]) ? $payload["gameCode"] : null;
            $sexyGame->platform = $payload["platform"];
            $sexyGame->platformTxId = $platformTxId;
            $sexyGame->roundId =  !empty($payload["roundId"]) ? $payload["roundId"] : null;
            $sexyGame->betType =  !empty($payload["betType"]) ? $payload["betType"] : null;
            $sexyGame->betTime =  !empty($payload["betTime"]) ? $payload["betTime"] : null;
            $sexyGame->betAmount =  $betAmount;
            $sexyGame->winAmount = !empty($payload["winAmount"]) ? $payload["winAmount"] : 0;
            $sexyGame->turnover = !empty($payload["turnover"]) ? $payload["turnover"] : 0;
            $sexyGame->gameInfo = !empty($payload["gameInfo"]) ? json_encode($payload["gameInfo"]) : null;
            // $sexyGame->log_reqs = json_encode($body);
            $sexyGame->save();
            return $sexyGame->id;
        } catch (\Exception $e) {
            // echo $e;
            return false;
        }
    }
}
