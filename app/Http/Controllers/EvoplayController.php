<?php

namespace App\Http\Controllers;

use App\Classes\Payment;
use Illuminate\Http\Request;
use App\Models\EvoplayGame;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;

class EvoplayController extends Controller
{

    private $SYSTEM_ID = "7720";
    private $VERSION = 1;
    private $SYSTEM_KEY = "3eb6347e2eab667d9ed27056aec92444";
    private $HOST = "https://api.8provider.com";


    const CONTROLLER_NAME = 'EvoplayController';
    public static function routes()
    {
        Route::get('/evoplay/', self::CONTROLLER_NAME . '@getGameList');
        Route::get('/evoplay/{username}/{game_id}', self::CONTROLLER_NAME . '@loginGame');
        Route::post('/evoplay', self::CONTROLLER_NAME . '@gamePlayer');
    }

    /**
     * $system_id - your project system ID (number)
     * $version - callback or API version
     * $args - array with API method or callback parameters. API parameters list you can find in API method description
     * $system_key - your system key
     */
    function getSignature($system_id, $version, array $args, $system_key)
    {
        $md5 = array();
        $md5[] = $system_id;
        $md5[] = $version;
        foreach ($args as $required_arg) {
            $arg = $required_arg;
            if (is_array($arg)) {
                if (count($arg)) {
                    $recursive_arg = '';
                    array_walk_recursive($arg, function ($item) use (&$recursive_arg) {
                        if (!is_array($item)) {
                            $recursive_arg .= ($item . ':');
                        }
                    });
                    $md5[] = substr($recursive_arg, 0, strlen($recursive_arg) - 1); // get rid of last colon-sign
                } else {
                    $md5[] = '';
                }
            } else {
                $md5[] = $arg;
            }
        };
        $md5[] = $system_key;
        $md5_str = implode('*', $md5);
        $md5 = md5($md5_str);
        return $md5;
    }

    public function getGameList()
    {
        $signature = $this->getSignature($this->SYSTEM_ID, $this->VERSION, [
            "need_extra_data" => 1
        ], $this->SYSTEM_KEY);

        $client = new Client([
            // 'exceptions'       => false,
            // 'verify'           => false,
            'headers'          => [
                'Accept'   => 'application/json',
                'Content-Type'   => 'application/json',
            ]
        ]);
        $res = $client->request("GET", "{$this->HOST}/Game/getList?project={$this->SYSTEM_ID}&version={$this->VERSION}&signature={$signature}&need_extra_data=1", []);

        $response = $res->getBody();
        // $response = $res->getBody()->getContents();
        return $response;
    }

    public function loginGame(Request $request, $username, $game_id)
    {
        $user = User::where('username', $username)->first();
        if (empty($user)) {
            $response = ["message" => "Oops! The user does not exist"];
            return response($response, 401);
            exit();
        }

        $paras = [
            "token" => $user->token, //Gamingsessiontoken
            "game" => $game_id,
            "settings" => [
                "user_id" => $user->username,
                "exit_url" => 'https://mm777bet.com',
                "cash_url" => 'https://mm777bet.com'
            ],
            "denomination" => 1,
            "currency" => $user->currency,
            "return_url_info" => 1,
            "callback_version" => 2,
        ];
        // return http_build_query($paras);
        $signature = $this->getSignature($this->SYSTEM_ID, $this->VERSION, $paras, $this->SYSTEM_KEY);

        $client = new Client([
            // 'exceptions'       => false,
            // 'verify'           => false,
            'headers'          => [
                'Accept'   => 'application/json',
                'Content-Type'   => 'application/json',
            ]
        ]);
        $res = $client->request("GET", "{$this->HOST}/Game/getURL?project={$this->SYSTEM_ID}&version={$this->VERSION}&signature={$signature}&token={$paras['token']}&" . http_build_query($paras), [
            // 'debug' => true,
            'form_params' => $paras
        ]);

        $response = $res->getBody();
        // $response = $res->getBody()->getContents();
        // return $response;

        $response_status = $res->getStatusCode();

        if ($response_status == 200) {
            $json = json_decode($response, true);
            if ($json['status'] == 'ok') {
                return redirect($json["data"]["link"]);
            } else {
                return response(["message" => "Oops! Request body is invalid"], 500);
            }
        } else {
            return response(["message" => "Oops! Request body is invalid"], 500);
        }
    }

    public function gamePlayer(Request $request)
    {
        Log::info("EvoPlay ==================>");
        Log::debug($request);

        if (!isset($request)) {
            echo json_encode([
                'status' => 'error',
                'error' => array(
                    'scope' => 'user',
                    'message' => 'Invalid parameters'
                ),
            ]);
            exit();
        }

        $action = $request->name;
        $token = $request->token;

        $member = User::where('token', '=', $token)->first();
        if (!$member) {
            return [
                "status" => "error",
                "error" => [
                    "scope" => "user",
                    "message" => "Invalid parameters",
                ]
            ];
            exit();
        }
        $username = $member->username;
        $wallet_amount_before = $member->main_wallet;
        $wallet_amount_after = $member->main_wallet;

        $req_data = $request->data;
        $callback_id = $request->callback_id;

        if ($action != "init") {
            $game = json_decode($request->data["details"], true);
            $game_id = $game["game"]["game_id"];
        }

        DB::beginTransaction();

        switch ($action) {
            case 'init':
                return [
                    "status" => "ok",
                    "data" => [
                        "balance" => number_format((float) $wallet_amount_after, 2, '.', ''),
                        "currency" => $member->currency
                    ]
                ];
                break;
            case 'bet':
                try {
                    if ($wallet_amount_before < $req_data['amount']) {
                        return [
                            "status" => "error",
                            "error" => [
                                "scope" => "user",
                                "no_refund" => "1",
                                "message" => 'Not enough money',
                            ]
                        ];
                    } else {
                        $check_transaction = EvoplayGame::select('id')
                            ->where('username', $username)
                            ->where('action', 'bet')
                            ->where('callback_id', $callback_id)
                            ->first();

                        if (!$check_transaction) {
                            $wallet_amount_after = $wallet_amount_after - $req_data["amount"];
                            User::where('username', $username)->update([
                                'main_wallet' => $wallet_amount_after
                            ]);

                            $logres = $this->savaTransaction("bet", $wallet_amount_before, $wallet_amount_after, $request, $username);
                            if (!$logres) {
                                throw new \Exception('SYSTEM_ERROR', 500);
                            }
                        } else {
                            throw new \Exception('BET_ALREADY_EXIST', 500);
                        }
                    }

                    (new Payment())->payAll($member->id, $req_data["amount"], 'SLOT');
                    (new Payment())->saveLog([
                        'amount' => $req_data["amount"],
                        'before_balance' => $wallet_amount_before,
                        'after_balance' => $wallet_amount_after,
                        'action' => 'BET',
                        'provider' => 'EVOPLAY',
                        'game_type' => 'SLOT',
                        'game_ref' => $game_id,
                        'transaction_ref' =>  $callback_id,
                        'player_username' => $username,
                    ]);


                    DB::commit();

                    return [
                        "status" => "ok",
                        "data" => [
                            "balance" => number_format((float) $wallet_amount_after, 2, '.', ''),
                            "currency" => $member->currency
                        ]
                    ];
                } catch (\Exception $e) {
                    DB::rollBack();
                    return [
                        "status" => "error",
                        "error" => [
                            "scope" => "user",
                            "message" => $e->getMessage(),
                        ]
                    ];
                }
                break;
            case 'win':
                try {
                    // $check_transaction_bet = EvoplayGame::select('id')
                    //     ->where('username', $username)
                    //     ->where('action', 'bet')
                    //     ->where('callback_id', $callback_id)
                    //     ->first();

                    // if ($check_transaction_bet) {
                    $check_transaction = EvoplayGame::select('id')
                        ->where('username', $username)
                        ->where('action', 'settle')
                        ->where('callback_id', $callback_id)
                        ->first();

                    if (!$check_transaction) {
                        if ($req_data["amount"] > 0) {
                            $wallet_amount_after = $wallet_amount_after + $req_data["amount"];
                            User::where('username', $username)->update([
                                'main_wallet' => $wallet_amount_after
                            ]);
                        }

                        $logres = $this->savaTransaction("settle", $wallet_amount_before, $wallet_amount_after, $request, $username);
                        if (!$logres) {
                            throw new \Exception('SYSTEM_ERROR', 500);
                        }
                    } else {
                        throw new \Exception('WIN_ALREADY_EXIST', 500);
                    }
                    // }

                    (new Payment())->saveLog([
                        'amount' => $req_data["amount"],
                        'before_balance' => $wallet_amount_before,
                        'after_balance' => $wallet_amount_after,
                        'action' => 'SETTLE',
                        'provider' => 'EVOPLAY',
                        'game_type' => 'SLOT',
                        'game_ref' => $game_id,
                        'transaction_ref' =>  $callback_id,
                        'player_username' => $username,
                    ]);

                    DB::commit();
                    return  [
                        "status" => "ok",
                        "data" => [
                            "balance" => number_format((float) $wallet_amount_after, 2, '.', ''),
                            "currency" => $member->currency
                        ]
                    ];
                } catch (\Exception $e) {
                    DB::rollBack();
                    return [
                        "status" => "error",
                        "error" => [
                            "scope" => "user",
                            "message" => $e->getMessage(),
                        ]
                    ];
                }
                break;
            case 'refund':
                try {
                    $check_transaction_bet = EvoplayGame::select('id')
                        ->where('username', $username)
                        ->where('action', 'bet')
                        ->where('callback_id', $req_data["refund_callback_id"])
                        ->first();

                    if ($check_transaction_bet) {
                        $check_transaction = EvoplayGame::select('id')
                            ->where('username', $username)
                            ->where('action', 'refund')
                            ->where('refund_callback_id', $req_data["refund_callback_id"])
                            ->first();

                        if (!$check_transaction) {
                            $wallet_amount_after = $wallet_amount_after + $req_data["amount"];
                            User::where('username', $username)->update([
                                'main_wallet' => $wallet_amount_after
                            ]);

                            $logres = $this->savaTransaction("refund", $wallet_amount_before, $wallet_amount_after, $request, $username);
                            if (!$logres) {
                                throw new \Exception('SYSTEM_ERROR', 500);
                            }
                        } else {
                            throw new \Exception('REFUND_ALREADY_EXIST', 500);
                        }
                    }
                    DB::commit();
                    return   [
                        "status" => "ok",
                        "data" => [
                            "balance" => number_format((float) $wallet_amount_after, 2, '.', ''),
                            "currency" => $member->currency
                        ]
                    ];
                } catch (\Exception $e) {
                    DB::rollBack();
                    return [
                        "status" => "error",
                        "error" => [
                            "scope" => "user",
                            "message" => $e->getMessage(),
                        ]
                    ];
                }
                break;
            default:
                # code...
                break;
        }
    }


    private function savaTransaction($action, $wallet_amount_before, $wallet_amount_after, $payload, $username)
    {
        try {
            $transaction  = new EvoplayGame();
            $transaction->action = $action;
            $transaction->wallet_amount_before = $wallet_amount_before;
            $transaction->wallet_amount_after = $wallet_amount_after;

            $transaction->username = $username;
            $transaction->token = $payload->token;

            $transaction->amount = $payload->data["amount"];
            $transaction->callback_id = $payload->callback_id;

            if ($action == "refund") {
                $transaction->action_id = $payload->data["refund_action_id"];
                $transaction->round_id = $payload->data["refund_round_id"];
            } else {
                $transaction->action_id = $payload->data["action_id"];
                $transaction->round_id = !empty($payload->data["round_id"]) ? $payload->data["round_id"] : 0;
            }
            $transaction->refund_callback_id = !empty($payload->data["refund_callback_id"]) ? $payload->data["refund_callback_id"] : 0;

            $final_action = !empty($payload->final_action) ? $payload->final_action : 0;
            if ($final_action == 0) {
                $final_action = !empty($payload->data["final_action"]) ? $payload->data["final_action"] : 0;
            }
            $transaction->final_action = $final_action;
            $transaction->game = json_encode($payload->data);

            if ($transaction->save()) {
                return $transaction;
            }
        } catch (\Exception $e) {
            // echo $e;
            Log::debug([
                "Evolution Error",
                $e
            ]);
            return false;
        }
    }
}
