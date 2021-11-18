<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Joker;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;

class JokerController extends Controller
{
    private $lobbyURL = "https://nasavg.com/play/joker";
    private $host = "http://api688.net/seamless";
    // Dev
    private $AppID = "TGFV";
    private $SecretKey = "qc8y9k63x55wr";
    // Prod
    // private $AppID = "F5K5M";
    // private $SecretKey = "wzqjkthq8bbje";

    private function encryptBody($array_params)
    {
        $array = array_filter($array_params);
        $array = array_change_key_case($array, CASE_LOWER);
        ksort($array);

        // print_r($array);

        $rawData = '';
        foreach ($array as $Key => $Value) {
            $rawData .=  $Key . '=' . $Value . '&';
        }

        $rawData = substr($rawData, 0, -1);
        $rawData .= $this->SecretKey;

        // echo "rawData ==>" . $rawData . "<br/>";

        $hash = md5($rawData);
        $postData = $array;
        $postData['Hash'] = $hash;
        return $postData;
    }

    public function devdev(Request $request)
    {
        // $rawData =  "appid=TFJN&timestamp=1591948666039&username=S1688000083n3hioot5guhc";
        // return  $bodyContent = $request->getContent();
        // return md5($rawData);
    }

    public function play($username, $gameCode)
    {
        $url = "https://www.gwc688.net/PlayGame?token=$username&appid={$this->AppID}&gameCode=$gameCode&language=en&mobile=false&redirectUrl={$this->lobbyURL}";
        return redirect($url);
    }

    public function index()
    {
        $seconds = round((microtime(true) * 1000));
        try {
            $client = new Client();
            $res = $client->request("POST", "{$this->host}/list-games", [
                'form_params' => $this->encryptBody(array(
                    "Timestamp" => $seconds,
                    "AppID" => $this->AppID,
                ))
            ]);
            $response = $res->getBody();
            return $response;
        } catch (\Exception $e) {
            return $e;
        }
    }

    public function auth(Request $request)
    {
        // Log::debug($request);
        if (isset($request->token)) {
            $member = User::where('token', '=', $request->token)->first();
            // Log::debug($member);
            if ($member) {
                $mResponse =  [
                    'Username' => $member->username,
                    'Balance' => number_format((float) $member->main_wallet, 2, '.', ''),
                    'Message' => 'Success',
                    'Status' => 0,
                ];
                // Log::alert($mResponse);
                return $mResponse;
            } else {
                return [
                    'Username' => 'null',
                    'Balance' => 0,
                    'Message' => 'Invalid Token',
                    'Status' => 3,
                ];
            }
        } else {
            return [
                'Username' => 'null',
                'Balance' => 0,
                'Message' => 'Invalid Parameters',
                'Status' => 4,
            ];
        }
    }

    public function validateSignature($payload)
    {
        $signature = $payload;
        $signature['secretKey'] = $this->SecretKey;
        function ksort_recursive(&$array)
        {
            ksort($array);
            foreach ($array as &$a) {
                is_array($a) && ksort_recursive($a);
            }
        }
        ksort_recursive($signature);
        return http_build_query($signature);
    }


    public function getbalance(Request $request)
    {
        // Log::alert("==============>getbalance");
        // Log::debug($request);
        $hasSignature = $this->encryptBody(array(
            "appid" => $request->appid,
            "timestamp" => $request->timestamp,
            "username" => $request->username,
        ));
        // return  $bodyContent = $request->getContent();
        // return $hasSignature["Hash"];
        if ($hasSignature["Hash"] != $request->hash) {
            return [
                'Balance' => 0.0,
                'Message' => 'InvalidSignature',
                'Status' => 5,
            ];
        }

        if (isset($request->username)) {
            $member = User::where('username', '=', $request->username)->first();
            if ($member) {
                return [
                    'Balance' => number_format((float) $member->main_wallet, 2, '.', ''),
                    'Message' => 'Success',
                    'Status' => 0,
                ];
            } else {
                return  [
                    'Balance' => 0.0,
                    'Message' => 'Invalid Username',
                    'Status' => 7,
                ];
            }
        } else {
            return [
                'Balance' => 0.0,
                'Message' => 'Invalid Parameters',
                'Status' => 4,
            ];
        }
    }


    public function bet(Request $request)
    {
        // Log::alert("==============>bet");
        // Log::debug($request);

        $hasSignature = $this->encryptBody(array(
            "amount" => $request->amount,
            "appid" => $request->appid,
            "gamecode" => $request->gamecode,
            "id" => $request->id,
            "roundid" => $request->roundid,
            "timestamp" => $request->timestamp,
            "username" => $request->username,
        ));
        // return  $bodyContent = $request->getContent();
        // return $hasSignature["Hash"];
        if ($hasSignature["Hash"] != $request->hash) {
            return [
                'Balance' => 0.0,
                'Message' => 'InvalidSignature',
                'Status' => 5,
            ];
        }

        $username = $request->username;
        $amount = $request->amount;
        $roundid = $request->roundid;
        $txnRefId = $request->id;

        DB::beginTransaction();
        try {
            $userWallet = User::where('username', $username)->lockForUpdate()->first();
            if (!$userWallet) {
                throw new \Exception('Invalid Username', 7);
            }
            $wallet_amount_before = $userWallet->main_wallet;
            $wallet_amount_after = $userWallet->main_wallet;

            $response_msg = "Success";
            if ($wallet_amount_before > 0 && $wallet_amount_before >= $amount) {
                //CancelBet before bet 
                $cancelBet_transaction = Joker::select('id')->where('action', 'cancelBet')
                    ->where('roundId', $roundid)
                    ->where('txnRefId', "Cancel:{$txnRefId}")
                    ->orderByDesc('id')->first();
                if (!$cancelBet_transaction) {
                    $check_transaction = Joker::select('id')->where('action', 'bet')
                        ->where('roundId', $roundid)
                        ->where('txnRefId', $txnRefId)
                        ->orderByDesc('id')->first();
                    if (!$check_transaction) {
                        $wallet_amount_after = $wallet_amount_after - $amount;
                        User::where('username', $username)->update([
                            'main_wallet' => $wallet_amount_after
                        ]);
                        /// save log 
                        $logres = $this->savaTransaction("bet", $wallet_amount_before, $wallet_amount_after, $request);
                        if (!$logres) {
                            throw new \Exception('Fail (System Error)', 1000);
                        }
                    } else {
                        $response_msg = "The Bet already existed";
                    }
                }
            }

            DB::commit();
            return [
                'Balance' => number_format((float) $wallet_amount_after, 2, '.', ''),
                'Message' => $response_msg,
                'Status' => 0,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'Balance' => 0.0,
                'Message' => $e->getMessage(),
                'Status' => $e->getCode(),
            ];
        }
    }

    public function cancelBet(Request $request)
    {
        // Log::alert("==============>cancelBet");
        // Log::debug($request);

        $username = $request->username;
        $roundid = $request->roundid;
        $betid = $request->betid;
        $cancelBetid = $request->id;

        DB::beginTransaction();
        try {
            $userWallet = User::where('username', $username)->lockForUpdate()->first();
            if (!$userWallet) {
                throw new \Exception('Invalid Username', 7);
            }
            $wallet_amount_before = $userWallet->main_wallet;
            $wallet_amount_after = $userWallet->main_wallet;
            $response_msg = "Success";

            $transaction_bet = Joker::select('id', 'amount')->where('action', 'bet')
                ->where('txnRefId', $betid)
                ->where('roundId', $roundid)
                ->first();
            if ($transaction_bet) {
                $check_transaction = Joker::select('id')->where('action', 'cancelBet')
                    ->where('txnRefId', $cancelBetid)
                    ->where('roundId', $roundid)
                    ->first();
                if (!$check_transaction) {
                    $wallet_amount_after = $wallet_amount_after + $transaction_bet->amount;
                    User::where('username', $username)->update([
                        'main_wallet' => $wallet_amount_after
                    ]);
                    /// save log 
                    $logres = $this->savaTransaction("cancelBet", $wallet_amount_before, $wallet_amount_after, $request);
                    if (!$logres) {
                        throw new \Exception('Fail (System Error)', 1000);
                    }
                } else {
                    $response_msg = "The CancelBet already existed";
                }
            } else {
                /// save log 
                $logres = $this->savaTransaction("cancelBet", $wallet_amount_before, $wallet_amount_after, $request);
                if (!$logres) {
                    throw new \Exception('Fail (System Error)', 1000);
                }
            }

            DB::commit();
            return [
                'Balance' => number_format((float) $wallet_amount_after, 2, '.', ''),
                'Message' => $response_msg,
                'Status' => 0,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'Balance' => 0.0,
                'Message' => $e->getMessage(),
                'Status' => $e->getCode(),
            ];
        }
    }

    public function settle(Request $request)
    {
        // Log::alert("==============>settle");
        // Log::debug($request);
        $username = $request->username;
        $amount = $request->amount;
        $roundid = $request->roundid;

        DB::beginTransaction();
        try {
            $userWallet = User::where('username', $username)->lockForUpdate()->first();
            if (!$userWallet) {
                throw new \Exception('Invalid Username', 7);
            }
            $wallet_amount_before = $userWallet->main_wallet;
            $wallet_amount_after = $userWallet->main_wallet;

            $check_transaction_bet = Joker::select('id')->where('action', 'bet')
                ->where('roundId', $roundid)
                ->orderByDesc('id')->first();

            if ($check_transaction_bet) {
                $check_transaction = Joker::select('id')->where('action', 'settle')
                    ->where('roundId', $roundid)->orderByDesc('id')->first();
                if (!$check_transaction) {
                    $wallet_amount_after = $wallet_amount_after + $amount;
                    User::where('username', $username)->update([
                        'main_wallet' => $wallet_amount_after
                    ]);
                    /// save log 
                    $logres = $this->savaTransaction("settle", $wallet_amount_before, $wallet_amount_after, $request);
                    if (!$logres) {
                        throw new \Exception('Fail (System Error)', 1000);
                    }
                }
            }
            DB::commit();
            return [
                'Balance' => number_format((float) $wallet_amount_after, 2, '.', ''),
                'Message' => 'Success',
                'Status' => 0,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'Balance' => 0.0,
                'Message' => $e->getMessage(),
                'Status' => $e->getCode(),
            ];
        }
    }


    public function bonusWin(Request $request)
    {
        // Log::alert("==============>bonusWin");
        // Log::debug($request);
        $username = $request->username;
        $amount = $request->amount;
        $roundid = $request->roundid;

        DB::beginTransaction();
        try {
            $userWallet = User::where('username', $username)->lockForUpdate()->first();
            if (!$userWallet) {
                throw new \Exception('Invalid Username', 7);
            }
            $wallet_amount_before = $userWallet->main_wallet;
            $wallet_amount_after = $userWallet->main_wallet;


            $check_transaction = Joker::select('id')->where('action', 'bonusWin')
                ->where('roundId', $roundid)->orderByDesc('id')->first();

            if (!$check_transaction) {
                $wallet_amount_after = $wallet_amount_after + $amount;
                User::where('username', $username)->update([
                    'main_wallet' => $wallet_amount_after
                ]);
                /// save log 
                $logres = $this->savaTransaction("bonusWin", $wallet_amount_before, $wallet_amount_after, $request);
                if (!$logres) {
                    throw new \Exception('Fail (System Error)', 1000);
                }
            }
            DB::commit();
            return [
                'Balance' => number_format((float) $wallet_amount_after, 2, '.', ''),
                'Message' => 'Success',
                'Status' => 0,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'Balance' => 0.0,
                'Message' => $e->getMessage(),
                'Status' => $e->getCode(),
            ];
        }
    }

    public function jackpotWin(Request $request)
    {
        // Log::alert("==============>jackpotWin");
        // Log::debug($request);

        $username = $request->username;
        $amount = $request->amount;
        $roundid = $request->roundid;

        DB::beginTransaction();
        try {
            $userWallet = User::where('username', $username)->lockForUpdate()->first();
            if (!$userWallet) {
                throw new \Exception('Invalid Username', 7);
            }
            $wallet_amount_before = $userWallet->main_wallet;
            $wallet_amount_after = $userWallet->main_wallet;

            $check_transaction = Joker::select('id')->where('action', 'jackpotWin')
                ->where('roundId', $roundid)->orderByDesc('id')->first();
            if (!$check_transaction) {
                $wallet_amount_after = $wallet_amount_after + $amount;
                User::where('username', $username)->update([
                    'main_wallet' => $wallet_amount_after
                ]);
                /// save log 
                $logres = $this->savaTransaction("jackpotWin", $wallet_amount_before, $wallet_amount_after, $request);
                if (!$logres) {
                    throw new \Exception('Fail (System Error)', 1000);
                }
            }
            DB::commit();
            return [
                'Balance' => number_format((float) $wallet_amount_after, 2, '.', ''),
                'Message' => 'Success',
                'Status' => 0,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'Balance' => 0.0,
                'Message' => $e->getMessage(),
                'Status' => $e->getCode(),
            ];
        }
    }
    public function withdraw(Request $request)
    {
        // Log::alert("==============>withdraw");
        // Log::debug($request);

        $username = $request->username;
        $amount = $request->amount;
        $txnRefId = $request->id;

        DB::beginTransaction();
        try {
            $userWallet = User::where('username', $username)->lockForUpdate()->first();
            if (!$userWallet) {
                throw new \Exception('Invalid Username', 7);
            }
            $wallet_amount_before = $userWallet->main_wallet;
            $wallet_amount_after = $userWallet->main_wallet;

            if ($wallet_amount_before > 0 && $wallet_amount_before >= $amount) {
                $check_transaction = Joker::select('id')->where('action', 'withdraw')
                    ->where('txnRefId', $txnRefId)->orderByDesc('id')->first();

                if (!$check_transaction) {
                    $wallet_amount_after = $wallet_amount_after - $amount;
                    User::where('username', $username)->update([
                        'main_wallet' => $wallet_amount_after
                    ]);
                    /// save log 
                    $logres = $this->savaTransaction("withdraw", $wallet_amount_before, $wallet_amount_after, $request);
                    if (!$logres) {
                        throw new \Exception('Fail (System Error)', 1000);
                    }
                }
            } else {
                DB::commit();
                return [
                    'Balance' => number_format((float) $wallet_amount_after, 2, '.', ''),
                    'Message' => 'Insufficient Balance',
                    'Status' => 100,
                ];
            }
            DB::commit();
            return [
                'Balance' => number_format((float) $wallet_amount_after, 2, '.', ''),
                'Message' => 'Success',
                'Status' => 0,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'Balance' => 0.0,
                'Message' => $e->getMessage(),
                'Status' => $e->getCode(),
            ];
        }
    }
    public function deposit(Request $request)
    {
        // Log::alert("==============>deposit");
        // Log::debug($request);

        $username = $request->username;
        $amount = $request->amount;
        $txnRefId = $request->id;

        DB::beginTransaction();
        try {
            $userWallet = User::where('username', $username)->lockForUpdate()->first();
            if (!$userWallet) {
                throw new \Exception('Invalid Username', 7);
            }
            $wallet_amount_before = $userWallet->main_wallet;
            $wallet_amount_after = $userWallet->main_wallet;

            $check_transaction = Joker::select('id')->where('action', 'deposit')
                ->where('txnRefId', $txnRefId)->orderByDesc('id')->first();

            if (!$check_transaction) {
                $wallet_amount_after = $wallet_amount_after + $amount;
                User::where('username', $username)->update([
                    'main_wallet' => $wallet_amount_after
                ]);
                /// save log 
                $logres = $this->savaTransaction("deposit", $wallet_amount_before, $wallet_amount_after, $request);
                if (!$logres) {
                    throw new \Exception('Fail (System Error)', 1000);
                }
            }
            DB::commit();
            return [
                'Balance' => number_format((float) $wallet_amount_after, 2, '.', ''),
                'Message' => 'Success',
                'Status' => 0,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'Balance' => 0.0,
                'Message' => $e->getMessage(),
                'Status' => $e->getCode(),
            ];
        }
    }
    public function transaction(Request $request)
    {
        // Log::alert("==============>transaction");
        // Log::debug($request);
        $username = $request->username;
        $amount = $request->amount;
        $roundid = $request->roundid;

        DB::beginTransaction();
        try {
            $userWallet = User::where('username', $username)->lockForUpdate()->first();
            if (!$userWallet) {
                throw new \Exception('Invalid Username', 7);
            }
            $wallet_amount_before = $userWallet->main_wallet;
            $wallet_amount_after = $userWallet->main_wallet;

            $check_transaction = Joker::select('id')->where('action', 'transaction')
                ->where('roundid', $roundid)->orderByDesc('id')->first();

            if (!$check_transaction) {
                /// save log 
                $logres = $this->savaTransaction("transaction", $wallet_amount_before, $wallet_amount_after, $request);
                if (!$logres) {
                    throw new \Exception('Fail (System Error)', 1000);
                }
            }
            DB::commit();
            return [
                'Balance' => number_format((float) $wallet_amount_after, 2, '.', ''),
                'Message' => 'Success',
                'Status' => 0,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'Balance' => 0.0,
                'Message' => $e->getMessage(),
                'Status' => $e->getCode(),
            ];
        }
    }


    private function savaTransaction($action, $wallet_amount_before, $wallet_amount_after, $payload)
    {
        try {
            $transaction  = new Joker();
            $transaction->action = $action;
            $transaction->wallet_amount_before = $wallet_amount_before;
            $transaction->wallet_amount_after = $wallet_amount_after;
            $transaction->username = $payload->username;
            $transaction->amount = $payload->amount;
            $transaction->appid = $payload->appid;
            $transaction->description = $payload->description;
            $transaction->gamecode = $payload->gamecode;
            $transaction->txnRefId = $payload->id;
            $transaction->roundid = $payload->roundid;
            $transaction->timestamp = $this->formatMilliSecondToDateTime($payload->timestamp, 7);
            $transaction->type = $payload->type;
            if ($transaction->save()) {
                return $transaction;
            }
        } catch (\Exception $e) {
            // echo $e;
            return false;
        }
    }
    private function formatMilliSecondToDateTime($millisecond, $n = 0)
    {
        $second = $millisecond / 1000 + ((60 * 60) * $n);
        return date("Y-m-d H:i:s", $second);
    }
}
