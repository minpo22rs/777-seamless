<?php

namespace App\Http\Controllers;

use App\Classes\Payment;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\AmbGame;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AmbController extends Controller
{
    private $host = "https://ap.ambpoker-api.com";
    private $key = "5088a9dbb1c9f13684c3bdb1fd8ca774";
    private $agentId = "mm777bet";
    private $whiteListIPs = ['54.255.110.219', '54.179.29.18', '13.213.115.59'];

    public function devdev()
    {
        return 123456;
    }


    public function getGameList()
    {
        try {
            $client = new Client();
            $res = $client->request("GET", "{$this->host}/seamless/games", [
                'form_params' => [
                    'agent' => $this->agentId
                ]
            ]);
            $response = $res->getBody();
            return $response;
        } catch (\Exception $e) {
            return $e;
        }
    }
    public function createMember($userName)
    {
        try {
            $paras = array(
                "username" => $userName,
                "password" => $userName,
                "agent" => $this->agentId,
            );
            $iterations = 1000;
            $x_amb_signature = base64_encode(hash_pbkdf2("sha512", json_encode($paras), $this->key, $iterations, 64, true));
            // echo base64_encode($hash);

            $client = new Client([
                'exceptions'       => false,
                'verify'           => false,
                'headers'          => [
                    'Content-Type'   => 'application/json',
                    'x-amb-signature' => $x_amb_signature
                ]
            ]);
            $res = $client->request("POST", "{$this->host}/seamless/create", [
                'form_params' => $paras
            ]);
            $response = $res->getBody()->getContents();
            $json = json_decode($response, true);
            return $json;
        } catch (\Exception $e) {
            return $e;
        }
    }
    public function getLobbyGame($token)
    {
        // return $this->createMember($username);
        // exit();
        // return $username;
        $user = User::where('token', $token)->first();
        if (empty($user)) {
            $response = ["message" => 'Oops! The user does not exist'];
            return response($response, 401);
            exit();
        }
        $username = $user->username;

        try {
            $paras = array(
                "username" => $username,
                "agent" => $this->agentId,
            );
            $iterations = 1000;
            $x_amb_signature = base64_encode(hash_pbkdf2("sha512", json_encode($paras), $this->key, $iterations, 64, true));

            $client = new Client([
                'exceptions'       => false,
                'verify'           => false,
                'headers'          => [
                    'Content-Type'   => 'application/json',
                    'x-amb-signature' => $x_amb_signature
                ]
            ]);
            $res = $client->request("POST", "{$this->host}/seamless/launch", [
                'form_params' => $paras
            ]);
            $response = $res->getBody()->getContents();
            $json = json_decode($response, true);

            // return $response;
            if ($json["status"]["code"] == 0) {
                $lobby_url = $json["data"]["url"];
                return redirect($lobby_url);
            } else if ($json["status"]["code"] == 908) {
                $responsenewmember = $this->createMember($username);
                if (!$responsenewmember) {
                    return "error create member";
                } else if ($responsenewmember["status"]["code"] == 0) {
                    return $this->getLobbyGame($username);
                } else {
                    return $responsenewmember;
                }
            } else {
                return $response;
            }
            // return $json["status"]["code"];

        } catch (\Exception $e) {
            return $e;
        }
    }

    public function getbalance(Request $request)
    {
        // Log::alert("==============>getbalance");
        // Log::debug($request);
        if (isset($request->username)) {
            $member = User::where('username', '=', $request->username)->first();
            if ($member) {
                return  [
                    'status' => [
                        'code' => 0,
                        'message' => 'Success'
                    ],
                    'data' => [
                        'balance' => number_format((float) $member->main_wallet, 2, '.', '')
                    ]
                ];
            } else {
                return  [
                    'status' => [
                        'code' => 997,
                        'message' => 'Invalid request data'
                    ],
                    'data' => []
                ];
            }
        } else {
            return  [
                'status' => [
                    'code' => 997,
                    'message' => 'Invalid request data'
                ],
                'data' => []
            ];
        }
    }

    public function bet(Request $request)
    {
        Log::alert("AMB==============>bet");
        Log::debug($request);
        $clientIP = $request->ip();
        Log::debug('ip => ' . $clientIP);

        if(!in_array($clientIP, $this->whiteListIPs)) {
            return "Not Allow";
        }

        $username = $request->username;
        $amount = $request->amount;
        $roundId = $request->roundId;
        $refId = $request->refId;

        DB::beginTransaction();
        try {
            $userWallet = User::where('username', $username)->lockForUpdate()->first();
            if (!$userWallet) {
                throw new \Exception('Invalid request data', 997);
            }

            $wallet_amount_before = $userWallet->main_wallet;
            $wallet_amount_after = $userWallet->main_wallet;

            if ($wallet_amount_before > 0 && $wallet_amount_before >= $amount) {
                //CancelBet before bet 
                $check_transaction = AmbGame::select('id')->whereIn('action', ['bet', 'cancelBet'])
                    ->where('roundId', $roundId)
                    // ->where('refId', $refId)
                    ->orderByDesc('id')->first();

                if (!$check_transaction) {
                    $wallet_amount_after = $wallet_amount_after - $amount;
                    User::where('username', $username)->update([
                        'main_wallet' => $wallet_amount_after
                    ]);
                    /// save log 
                    $logres = $this->savaTransaction("bet", $wallet_amount_before, $wallet_amount_after, $request);
                    if (!$logres) {
                        throw new \Exception('Service not available', 999);
                    }
                } else {
                    throw new \Exception('Duplicate Round Id', 806);
                }
            } else {
                throw new \Exception('Balance insufficient', 800);
            }

            if($amount <= 0) {
                $userWallet->is_free_spin = 1;
            }else {
                $userWallet->is_free_spin = 0;
            }

            $userWallet->save();

            (new Payment())->payAll($userWallet->id, $amount, 'SLOT');
            (new Payment())->saveLog([
                'amount' => $amount,
                'before_balance' => $wallet_amount_before,
                'after_balance' => $wallet_amount_before - $amount,
                'action' => 'BET',
                'provider' => 'AMB',
                'game_type' => 'SLOT',
                'game_ref' => $request->gameId . ', ' . $request->roundId,
                'transaction_ref' => $request->gameId,
                'player_username' => $request->username,
            ]);


            DB::commit();
            return [
                'status' => [
                    'code' => 0,
                    'message' => 'Success'
                ],
                'data' => [
                    'username' => $username,
                    'wallet' => [
                        'balance' => number_format((float) $wallet_amount_after, 2, '.', ''),
                        'lastUpdate' => $this->tsDateISOString()
                    ],
                    'balance' => [
                        'before' => number_format((float) $wallet_amount_before, 2, '.', ''),
                        'after' => number_format((float) $wallet_amount_after, 2, '.', ''),
                    ],
                    'refId' => $refId
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'status' => [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage()
                ],
                'data' => []
            ];
        }
    }


    public function settle(Request $request)
    {
        Log::alert("AMB==============>settle");
        Log::debug($request);
        
        $clientIP = $request->ip();
        Log::debug('ip => ' . $clientIP);

        if(!in_array($clientIP, $this->whiteListIPs)) {
            return "Not Allow";
        }

        $username = $request->username;
        $amount = $request->amount;
        $roundId = $request->roundId;
        $refId = $request->refId;

        DB::beginTransaction();
        try {
            $userWallet = User::where('username', $username)->lockForUpdate()->first();
            if (!$userWallet) {
                throw new \Exception('Invalid request data', 997);
            }

            $wallet_amount_before = $userWallet->main_wallet;
            $wallet_amount_after = $userWallet->main_wallet;


            $check_transaction_bet = AmbGame::select('id')->where('action', 'bet')
                ->where('roundId', $roundId)
                ->orderByDesc('id')->first();

            if ($check_transaction_bet) {
                $check_transaction = AmbGame::select('id')->whereIn('action', ['settle', 'voidSettle'])
                    ->where('roundId', $roundId)
                    ->orderByDesc('id')->first();
                if (!$check_transaction) {

                    $wallet_amount_after = $wallet_amount_after + $amount;
                    User::where('username', $username)->update([
                        'main_wallet' => $wallet_amount_after
                    ]);
                    /// save log 
                    $logres = $this->savaTransaction("settle", $wallet_amount_before, $wallet_amount_after, $request);
                    if (!$logres) {
                        throw new \Exception('Service not available', 999);
                    }
                } else {
                    throw new \Exception('Duplicate Round Id', 806);
                }
            }

            (new Payment())->saveLog([
                'amount' => $amount,
                'before_balance' => $wallet_amount_before,
                'after_balance' => $wallet_amount_before + $amount,
                'action' => 'SETTLE',
                'provider' => 'AMB',
                'game_type' => 'SLOT',
                'game_ref' => $request->gameId . ', ' . $request->roundId,
                'transaction_ref' => $request->gameId,
                'player_username' => $request->username,
            ]); 


            DB::commit();
            return [
                'status' => [
                    'code' => 0,
                    'message' => 'Success'
                ],
                'data' => [
                    'username' => $username,
                    'wallet' => [
                        'balance' => number_format((float) $wallet_amount_after, 2, '.', ''),
                        'lastUpdate' => $this->tsDateISOString()
                    ],
                    'balance' => [
                        'before' => number_format((float) $wallet_amount_before, 2, '.', ''),
                        'after' => number_format((float) $wallet_amount_after, 2, '.', ''),
                    ],
                    'refId' => $refId
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'status' => [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage()
                ],
                'data' => []
            ];
        }
    }

    public function cancelBet(Request $request)
    {
        Log::alert("==============>actionCancel");
        Log::debug($request);

        $clientIP = $request->ip();
        Log::debug('ip => ' . $clientIP);

        if(!in_array($clientIP, $this->whiteListIPs)) {
            return "Not Allow";
        }

        $username = $request->username;
        $amount = $request->amount;
        $roundId = $request->roundId;
        $refId = $request->refId;

        DB::beginTransaction();
        try {
            $userWallet = User::where('username', $username)->lockForUpdate()->first();
            if (!$userWallet) {
                throw new \Exception('Invalid request data', 997);
            }

            $wallet_amount_before = $userWallet->main_wallet;
            $wallet_amount_after = $userWallet->main_wallet;


            $transaction_bet = AmbGame::select('id', 'amount')->where('action', 'bet')
                ->where('roundId', $roundId)
                ->orderByDesc('id')->first();

            if ($transaction_bet) {
                $check_transaction = AmbGame::select('id')->where('action', 'cancelBet')
                    ->where('roundId', $roundId)
                    ->orderByDesc('id')->first();
                if (!$check_transaction) {

                    $wallet_amount_after = $wallet_amount_after + $transaction_bet->amount;
                    User::where('username', $username)->update([
                        'main_wallet' => $wallet_amount_after
                    ]);
                    /// save log 
                    $logres = $this->savaTransaction("cancelBet", $wallet_amount_before, $wallet_amount_after, $request);
                    if (!$logres) {
                        throw new \Exception('Service not available', 999);
                    }
                } else {
                    throw new \Exception('Duplicate Round Id', 806);
                }
            } else {
                /// save log 
                $logres = $this->savaTransaction("cancelBet", $wallet_amount_before, $wallet_amount_after, $request);
                if (!$logres) {
                    throw new \Exception('Service not available', 999);
                }
            }


            DB::commit();
            return [
                'status' => [
                    'code' => 0,
                    'message' => 'Success'
                ],
                'data' => [
                    'username' => $username,
                    'wallet' => [
                        'balance' => number_format((float) $wallet_amount_after, 2, '.', ''),
                        'lastUpdate' => $this->tsDateISOString()
                    ],
                    'balance' => [
                        'before' => number_format((float) $wallet_amount_before, 2, '.', ''),
                        'after' => number_format((float) $wallet_amount_after, 2, '.', ''),
                    ],
                    'refId' => $refId
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'status' => [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage()
                ],
                'data' => []
            ];
        }
    }

    public function voidSettle(Request $request)
    {
        Log::alert("==============>actionVoid");
        Log::debug($request);

        $clientIP = $request->ip();
        Log::debug('ip => ' . $clientIP);

        if(!in_array($clientIP, $this->whiteListIPs)) {
            return "Not Allow";
        }

        $username = $request->username;
        $amount = $request->amount;
        $roundId = $request->roundId;
        $refId = $request->refId;

        DB::beginTransaction();
        try {
            $userWallet = User::where('username', $username)->lockForUpdate()->first();
            if (!$userWallet) {
                throw new \Exception('Invalid request data', 997);
            }

            $wallet_amount_before = $userWallet->main_wallet;
            $wallet_amount_after = $userWallet->main_wallet;


            $transaction_settle = AmbGame::select('id', 'amount')->where('action', 'settle')
                ->where('roundId', $roundId)
                ->orderByDesc('id')->first();

            if ($transaction_settle) {
                $check_transaction = AmbGame::select('id')->where('action', 'voidSettle')
                    ->where('roundId', $roundId)
                    ->orderByDesc('id')->first();
                if (!$check_transaction) {
                    // $wallet_amount_after = $wallet_amount_after + $transaction_bet->amount;
                    $wallet_amount_after = $wallet_amount_after - $transaction_settle->amount + abs($amount);

                    User::where('username', $username)->update([
                        'main_wallet' => $wallet_amount_after
                    ]);
                    /// save log 
                    $logres = $this->savaTransaction("voidSettle", $wallet_amount_before, $wallet_amount_after, $request);
                    if (!$logres) {
                        throw new \Exception('Service not available', 999);
                    }
                } else {
                    throw new \Exception('Duplicate Round Id', 806);
                }
            } else {
                /// save log 
                $logres = $this->savaTransaction("voidSettle", $wallet_amount_before, $wallet_amount_after, $request);
                if (!$logres) {
                    throw new \Exception('Service not available', 999);
                }
            }

            DB::commit();
            return [
                'status' => [
                    'code' => 0,
                    'message' => 'Success'
                ],
                'data' => [
                    'username' => $username,
                    'wallet' => [
                        'balance' => number_format((float) $wallet_amount_after, 2, '.', ''),
                        'lastUpdate' => $this->tsDateISOString()
                    ],
                    'balance' => [
                        'before' => number_format((float) $wallet_amount_before, 2, '.', ''),
                        'after' => number_format((float) $wallet_amount_after, 2, '.', ''),
                    ],
                    'refId' => $refId
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'status' => [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage()
                ],
                'data' => []
            ];
        }
    }


    private function savaTransaction($action, $wallet_amount_before, $wallet_amount_after, $payload)
    {
        try {

            $transaction  = new AmbGame();
            $transaction->action = $action;
            $transaction->wallet_amount_before = $wallet_amount_before;
            $transaction->wallet_amount_after = $wallet_amount_after;
            $transaction->username = $payload->username;
            $transaction->amount = $payload->amount;
            $transaction->commission = $payload->commission;
            $transaction->winlose = $payload->winlose;

            $transaction->game = $payload->game;
            $transaction->gameId = $payload->gameId;
            $transaction->roundId = $payload->roundId;
            $transaction->refId = $payload->refId;
            $transaction->timestamp = $payload->timestamp;
            $transaction->outStanding = $payload->outStanding;

            if ($transaction->save()) {
                return $transaction;
            }
        } catch (\Exception $e) {
            // echo $e;
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
}
