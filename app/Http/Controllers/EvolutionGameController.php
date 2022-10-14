<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EvolutionGame;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;
use App\Classes\Payment;

class EvolutionGameController extends Controller
{

    private $CasinoKey = "q1toemwfyrneusip";
    private $APIKey = "316205f73b9ba5dd5d54ff7783c991ac";
    private $HOST = "https://api.luckylivegames.com";

    const CONTROLLER_NAME = 'EvolutionGameController';
    public static function routes()
    {
        Route::get('/evolution/{username}', self::CONTROLLER_NAME . '@loginGame');
        Route::post('/evolution/check', self::CONTROLLER_NAME . '@CheckUserRequest');
        Route::post('/evolution/balance', self::CONTROLLER_NAME . '@BalanceRequest');
        Route::post('/evolution/debit', self::CONTROLLER_NAME . '@DebitRequest');
        Route::post('/evolution/credit', self::CONTROLLER_NAME . '@CreditRequest');
        Route::post('/evolution/cancel', self::CONTROLLER_NAME . '@CancelRequest');
    }

    public function loginGame(Request $request, $username)
    {
        $user = User::where('username', $username)->first();
        if (empty($user)) {
            $response = ["message" => "Oops! The user does not exist"];
            return response($response, 401);
            exit();
        }

        $client = new Client([
            'headers'          => [
                'Accept'   => 'application/json',
                'Content-Type'   => 'application/json',
            ]
        ]);

        $res = $client->request("POST", "{$this->HOST}/ua/v1/{$this->CasinoKey}/{$this->APIKey}", [
            "json" => [
                "uuid" =>  $user->token,
                "player" => [
                    "id" => $user->username,
                    "update" => true,
                    "firstName" => $user->first_name,
                    "lastName" => $user->last_name,
                    "country" => "TH",
                    "language" => "TH",
                    "currency" => $user->currency,
                    "session" => [
                        "id" => Uuid::uuid4(),
                        "ip" => $request->ip()
                    ]
                ],
                "config" => [
                    "brand" => [
                        "id" => "1",
                        "skin" => "1"
                    ],
                    "game" => [
                        "category" => "top_games",
                        // "interface" => "view1",
                        // "table" => [
                        //     "id" => "vip-roulette-123"
                        // ]
                    ]
                ],
                "channel" => [
                    "wrapped" => false,
                    "mobile" => false
                ],
                // "urls" => [
                //     "cashier" => "http://www.chs.ee",
                //     "responsibleGaming" => "http://www.RGam.ee",
                //     "lobby" => "http://www.lobb.ee",
                //     "sessionTimeout" => "http://www.sesstm.ee"
                // ]
            ]
        ]);

        $response = $res->getBody();
        // $response = $res->getBody()->getContents();
        // return $response_status;

        $response_status = $res->getStatusCode();

        if ($response_status == 200) {
            $json = json_decode($response, true);
            return redirect($json["entry"]);
        } else {
            return response(["message" => "Oops! Request body is invalid"], 500);
        }
    }
    public function CheckUserRequest(Request $request)
    {
        Log::info("Evolution CheckUserRequest==================>");
        Log::debug($request);
        $username = $request->userId;

        try {
            if (empty($username)) {
                throw new \Exception('INVALID_TOKEN_ID', 500);
                exit();
            }

            $user = User::where('username', $username)->first();
            if (empty($user)) {
                throw new \Exception('INVALID_TOKEN_ID', 500);
                exit();
            }
            return [
                "status" => "OK",
                "sid" => Uuid::uuid4(),
                "uuid" => $user->token
            ];
        } catch (\Exception $e) {
            return [
                "status" => $e->getMessage(),
                "sid" => $request->sid,
                "uuid" => $request->uuid
            ];
        }
    }

    public function BalanceRequest(Request $request)
    {
        // Log::info("Evolution BalanceRequest==================>");
        // Log::debug($request);
        $username = $request->userId;
        try {
            $member = User::where('username', '=', $username)->first();
            if (!$member) {
                throw new \Exception('INVALID_TOKEN_ID', 500);
            }

            return [
                "status" => "OK",
                "balance" => number_format((float) $member->main_wallet, 2, '.', ''),
                "bonus" => 0.00,
                "uuid" =>  $request->uuid
            ];
        } catch (\Exception $e) {
            return [
                "status" => $e->getMessage(),
                "balance" => 0.00,
                "bonus" => 0.00,
                "uuid" => $request->uuid
            ];
        }
    }


    public function DebitRequest(Request $request)
    {
        Log::info("Evolution DebitRequest==================>");
        Log::debug($request);

        DB::beginTransaction();
        $username = $request->userId;
        $transaction = $request->transaction;

        try {
            throw new \Exception('UNKNOWN_ERROR', 500);
            $userWallet = User::where('username', $username)->lockForUpdate()->first();
            if (!$userWallet) {
                throw new \Exception('INVALID_TOKEN_ID', 500);
            }
            $wallet_amount_before = $userWallet->main_wallet;
            $wallet_amount_after = $userWallet->main_wallet;

            if ($wallet_amount_before > 0 && $wallet_amount_before >= $transaction["amount"]) {
                $check_transaction = EvolutionGame::select('id')
                    ->where('username', $username)
                    ->where('action', 'bet')
                    ->where('refId', $transaction["refId"])
                    ->first();

                if (!$check_transaction) {
                    $wallet_amount_after = $wallet_amount_after - $transaction["amount"];
                    User::where('username', $username)->update([
                        'main_wallet' => $wallet_amount_after
                    ]);

                    $logres = $this->savaTransaction("bet", $wallet_amount_before, $wallet_amount_after, $request);
                    if (!$logres) {
                        throw new \Exception('UNKNOWN_ERROR', 500);
                    }
                } else {
                    throw new \Exception('BET_ALREADY_EXIST', 500);
                }
            } else {
                throw new \Exception('INSUFFICIENT_FUNDS', 500);
            }


            (new Payment())->payAll($userWallet->id, $transaction["amount"], 'CASINO');
            (new Payment())->saveLog([
                'amount' => $transaction["amount"],
                'before_balance' => $wallet_amount_before,
                'after_balance' => $wallet_amount_after,
                'action' => 'BET',
                'provider' => 'EVOLUTION',
                'game_type' => 'CASINO',
                'game_ref' => $request->game["id"],
                'transaction_ref' =>  $transaction["refId"],
                'player_username' => $username,
            ]);

            DB::commit();
            return [
                "status" => "OK",
                "balance" => number_format((float) $wallet_amount_after, 2, '.', ''),
                "bonus" => 0.00,
                "uuid" => $request->uuid
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                "status" => $e->getMessage(),
                "balance" => 0.00,
                "bonus" => 0.00,
                "uuid" => $request->uuid
            ];
        }
    }
    public function CreditRequest(Request $request)
    {
        Log::info("Evolution CreditRequest==================>");
        Log::debug($request);

        DB::beginTransaction();
        $username = $request->userId;
        $transaction = $request->transaction;

        try {
            throw new \Exception('UNKNOWN_ERROR', 500);
            $userWallet = User::where('username', $username)->lockForUpdate()->first();
            if (!$userWallet) {
                throw new \Exception('INVALID_TOKEN_ID', 500);
            }
            $wallet_amount_before = $userWallet->main_wallet;
            $wallet_amount_after = $userWallet->main_wallet;

            $check_transaction_bet = EvolutionGame::select('id')
                ->where('username', $username)
                ->where('action', 'bet')
                ->where('refId', $transaction["refId"])
                ->first();

            if ($check_transaction_bet) {
                $check_transaction = EvolutionGame::select('id')
                    ->where('username', $username)
                    ->where('action', 'settle')
                    ->where('refId', $transaction["refId"])
                    ->first();

                if (!$check_transaction) {
                    $wallet_amount_after = $wallet_amount_after + $transaction["amount"];
                    User::where('username', $username)->update([
                        'main_wallet' => $wallet_amount_after
                    ]);

                    $logres = $this->savaTransaction("settle", $wallet_amount_before, $wallet_amount_after, $request);
                    if (!$logres) {
                        throw new \Exception('UNKNOWN_ERROR', 500);
                    }
                } else {
                    throw new \Exception('BET_ALREADY_EXIST', 500);
                }
            }
            (new Payment())->saveLog([
                'amount' => $transaction["amount"],
                'before_balance' => $wallet_amount_before,
                'after_balance' => $wallet_amount_after,
                'action' => 'SETTLE',
                'provider' => 'EVOLUCTION',
                'game_type' => 'CASINO',
                'game_ref' => $request->game["id"],
                'transaction_ref' =>  $transaction["refId"],
                'player_username' => $username,
            ]);

            DB::commit();
            return [
                "status" => "OK",
                "balance" => number_format((float) $wallet_amount_after, 2, '.', ''),
                "bonus" => 0.00,
                "uuid" => $request->uuid
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                "status" => $e->getMessage(),
                "balance" => 0.00,
                "bonus" => 0.00,
                "uuid" => $request->uuid
            ];
        }
    }

    public function CancelRequest(Request $request)
    {
        Log::info("Evolution CancelRequest==================>");
        Log::debug($request);

        DB::beginTransaction();
        $username = $request->userId;
        $transaction = $request->transaction;

        try {
            $userWallet = User::where('username', $username)->lockForUpdate()->first();
            if (!$userWallet) {
                throw new \Exception('INVALID_TOKEN_ID', 500);
            }
            $wallet_amount_before = $userWallet->main_wallet;
            $wallet_amount_after = $userWallet->main_wallet;

            $check_transaction = EvolutionGame::select('id')
                ->where('username', $username)
                ->where('action', 'cancelBet')
                ->where('refId', $transaction["refId"])
                ->first();

            if (!$check_transaction) {
                $wallet_amount_after = $wallet_amount_after + $transaction["amount"];
                User::where('username', $username)->update([
                    'main_wallet' => $wallet_amount_after
                ]);

                $logres = $this->savaTransaction("cancelBet", $wallet_amount_before, $wallet_amount_after, $request);
                if (!$logres) {
                    throw new \Exception('UNKNOWN_ERROR', 500);
                }
            } else {
                throw new \Exception('BET_ALREADY_EXIST', 500);
            }

            DB::commit();
            return [
                "status" => "OK",
                "balance" => number_format((float) $wallet_amount_after, 2, '.', ''),
                "bonus" => 0.00,
                "uuid" => $request->uuid
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                "status" => $e->getMessage(),
                "balance" => 0.00,
                "bonus" => 0.00,
                "uuid" => $request->uuid
            ];
        }
    }


    private function savaTransaction($action, $wallet_amount_before, $wallet_amount_after, $payload)
    {
        try {
            $transaction  = new EvolutionGame();
            $transaction->action = $action;
            $transaction->wallet_amount_before = $wallet_amount_before;
            $transaction->wallet_amount_after = $wallet_amount_after;

            $transaction->username = $payload->userId;
            $transaction->amount = $payload->transaction["amount"];
            $transaction->txn_id = $payload->transaction["id"];
            $transaction->refId = $payload->transaction["refId"];
            $transaction->game = !empty($payload->game) ? json_encode($payload->game) : null;


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
