<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PPGame;
use App\Models\Constant;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PPGameController extends Controller
{
    //
    private $hostGame = "https://tg168-sg0.ppgames.net";
    private $host = "https://api-sg0.ppgames.net/IntegrationService/v3/http/CasinoGameAPI";
    private $secureLogin = "tg168_nasavg";
    private $SecretKey = "D08b2bD268214a0c";
    private $currencyCode = "THB";

    private function encryptBody($queryString)
    {
        $key = md5($queryString . $this->SecretKey);
        return $key;
    }

    public function index()
    {
        $queryString = "secureLogin={$this->secureLogin}";
        $key = $this->encryptBody($queryString);
        try {
            $client = new Client();
            $res = $client->request("POST", "{$this->host}/getCasinoGames?secureLogin={$this->secureLogin}&hash={$key}", []);
            $response = $res->getBody();
            return $response;
        } catch (\Exception $e) {
            return $e;
        }
    }

    public function login($userToken, $gameId)
    {
        $user = User::where('token', $userToken)->first();
        if (empty($user)) {
            $response = ["message" => "Oops! The user does not exist"];
            return response($response, 401);
            exit();
        }
        $userToken = $user->token;

        $urlValue =  urlencode("token={$userToken}&symbol={$gameId}&language=th&technology=H5&platform=WEB&cashierUrl=&lobbyUrl=https://nasavg.com/lobby/pp");

        $url = "{$this->hostGame}/gs2c/playGame.do?key={$urlValue}&stylename={$this->secureLogin}";
        return redirect($url);


        // $url = "https://demogamesfree-asia.pragmaticplay.net/gs2c/openGame.do?token={$userToken}&lang=en&cur=THB&gameSymbol={$gameId}&jurisdiction=THB&lobbyURL=https://zap88.com/seamless/pp/dev&stylename={$this->secureLogin}";
        // return redirect($url);
    }


    public function auth(Request $request)
    {
        Log::debug($request);
        $user = User::where('token', '=', $request->token)->first();
        if (!$user) {
            return [
                "error" => 4,
                "description" => "Player authentication failed due to invalid, not found or expired token."
            ];
        } else {
            return [
                "userId" => $user->username,
                "currency" => $this->currencyCode,
                // "cash" => $user->main_wallet,
                "cash" => number_format((float) $user->main_wallet, 2, '.', ''),
                "bonus" => 0,
                "error" => 0,
                "description" => "Success"
            ];
        }
    }

    public function balance(Request $request)
    {
        Log::debug("balance===========>");
        Log::debug($request);

        $userId = $request->userId;
        $user = User::where("username", $userId)->first();
        if (!$user) {
            return [
                "error" => 2,
                "description" => "Player not found or is logged out."
            ];
        } else {
            return [
                "userId" => $user->username,
                "currency" => $this->currencyCode,
                "cash" => number_format((float) $user->main_wallet, 2, '.', ''),
                "bonus" => 0,
                "error" => 0,
                "description" => "Success"
            ];
        }
    }


    public function bet(Request $request)
    {
        Log::debug("bet===========>");
        Log::debug($request);

        $providerId = $request->providerId;
        $userId = $request->userId;
        $amount = $request->amount;
        $reference = $request->reference;
        $roundId = $request->roundId;

        $user = User::where("username", $userId)->first();
        if (!$user) {
            return [
                "error" => 2,
                "description" => "Player not found or is logged out."
            ];
        }

        $username = $user->username;
        $main_wallet = $user->main_wallet;

        DB::beginTransaction();
        try {
            $userWallet = User::where('username', $username)->lockForUpdate()->first();
            $wallet_amount_before = $userWallet->main_wallet;
            $wallet_amount_after = $userWallet->main_wallet;
            $transactionId = 0;

            // จ่ายคืนยอดเสีย
            if ($userWallet->is_promotion == 0) {
                $percentRefund = Constant::where('variable', 'PERCENT_REFUND')->first()->value;
                $userWallet->refund_wallet = $userWallet->refund_wallet + ($amount * $percentRefund / 100);
            }
            $userWallet->turnover = $userWallet->turnover + $amount;
            $userWallet->save();
            // จ่าย AFF
            if ($userWallet->invitor_token) {
                $invitor = User::where('token', $userWallet->invitor_token)->lockForUpdate()->first();
                if ($invitor) {
                    $percentAffiliate = Constant::where('variable', 'PERCENT_AFFILIATE')->first()->value;
                    $invitor->aff_wallet = $invitor->aff_wallet + ($amount * $percentAffiliate / 100);
                    $invitor->save();
                    if ($invitor->invitor_token) {
                        $children = User::where('token', $invitor->invitor_token)->lockForUpdate()->first();
                        if ($children) {
                            $percentAffiliateStep2 = Constant::where('variable', 'PERCENT_AFFILIATE_STEP_2')->first()->value;
                            $children->aff_wallet = $children->aff_wallet + ($amount * $percentAffiliateStep2 / 100);
                            $children->save();
                        }
                    }
                }
            }

            if ($wallet_amount_before > $amount) {
                $betTransaction  = PPGame::select('id')
                    ->where('action', '=', 'bet')
                    ->where('reference', '=', $reference)
                    ->where('roundId', '=', $roundId)
                    ->where('providerId', '=', $providerId)
                    ->where('username', '=', $username)
                    ->orderByDesc('id')
                    ->first();

                if (!$betTransaction) {
                    $wallet_amount_after = $wallet_amount_after - $amount;
                    User::where('username', $username)->update([
                        'main_wallet' => $wallet_amount_after
                    ]);

                    $transactionLog = $this->savaTransaction('bet', $wallet_amount_before, $wallet_amount_after, $request, $username);

                    if (!$transactionLog) {
                        throw new \Exception('Fail (System Error)', 120);
                    }
                    $transactionId = $transactionLog->id;
                } else {
                    $transactionId = $betTransaction->id;
                }
            }

            DB::commit();

            return [
                "error" => 0,
                "description" => "Success",
                "transactionId" => $transactionId,
                "currency" => $this->currencyCode,
                "cash" => number_format((float) $wallet_amount_after, 2, '.', ''),
                "bonus" => 0,
                "usedPromo" => 0,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                "error" => $e->getCode(),
                "description" => $e->getMessage(),
            ];
        }
    }

    public function cancelBet(Request $request)
    {
        Log::debug("cancelBet===========>");
        Log::debug($request);

        $providerId = $request->providerId;
        $userId = $request->userId;
        $reference = $request->reference;

        $user = User::where("username", $userId)->first();
        if (!$user) {
            return [
                "error" => 2,
                "description" => "Player not found or is logged out."
            ];
        }
        $username = $user->username;
        $main_wallet = $user->main_wallet;

        DB::beginTransaction();
        try {
            $userWallet = User::where('username', $username)->lockForUpdate()->first();
            $wallet_amount_before = $userWallet->main_wallet;
            $wallet_amount_after = $userWallet->main_wallet;
            $transactionId = 0;

            $betTransaction  = PPGame::select('amount')
                ->where('action', '=', 'bet')
                ->where('reference', '=', $reference) // Reference from theoriginal bet transaction.
                ->where('providerId', '=', $providerId)
                ->where('username', '=', $username)
                ->orderByDesc('id')
                ->first();

            if ($betTransaction) {
                $cancelBetTransaction  = PPGame::select('id')
                    ->where('action', '=', 'cancelBet')
                    ->where('reference', '=', $reference)
                    ->where('providerId', '=', $providerId)
                    ->where('username', '=', $username)
                    ->orderByDesc('id')
                    ->first();
                if (!$cancelBetTransaction) {
                    $wallet_amount_after = $wallet_amount_after + $betTransaction->amount;
                    User::where('username', $username)->update([
                        'main_wallet' => $wallet_amount_after
                    ]);

                    $transactionLog = $this->savaTransaction('cancelBet', $wallet_amount_before, $wallet_amount_after, $request, $username);

                    if (!$transactionLog) {
                        throw new \Exception('Fail (System Error)', 120);
                    }
                    $transactionId = $transactionLog->id;
                } else {
                    $transactionId = $cancelBetTransaction->id;
                }
            }

            DB::commit();

            return [
                "error" => 0,
                "description" => "Success",
                "transactionId" => $transactionId,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                "error" => $e->getCode(),
                "description" => $e->getMessage(),
            ];
        }
    }

    public function settle(Request $request)
    {
        Log::debug("settle===========>");
        Log::debug($request);

        $providerId = $request->providerId;
        $userId = $request->userId;
        $amount = $request->amount;
        $reference = $request->reference;
        $roundId = $request->roundId;
        $promoWinAmount = $request->promoWinAmount;

        $user = User::where("username", $userId)->first();
        if (!$user) {
            return [
                "error" => 2,
                "description" => "Player not found or is logged out."
            ];
        }
        $username = $user->username;
        $main_wallet = $user->main_wallet;

        DB::beginTransaction();
        try {
            $userWallet = User::where('username', $username)->lockForUpdate()->first();
            $wallet_amount_before = $userWallet->main_wallet;
            $wallet_amount_after = $userWallet->main_wallet;
            $transactionId = 0;

            $settleTransaction  = PPGame::select('id')
                ->where('action', '=', 'settle')
                ->where('roundId', '=', $roundId)
                ->where('reference', '=', $reference)
                ->where('providerId', '=', $providerId)
                ->where('username', '=', $username)
                ->orderByDesc('id')
                ->first();

            if (!$settleTransaction) {
                $wallet_amount_after = $wallet_amount_after + $amount + $promoWinAmount;

                User::where('username', $username)->update([
                    'main_wallet' => $wallet_amount_after
                ]);

                $transactionLog = $this->savaTransaction('settle', $wallet_amount_before, $wallet_amount_after, $request, $username);

                if (!$transactionLog) {
                    throw new \Exception('Fail (System Error)', 120);
                }
                $transactionId = $transactionLog->id;
            } else {
                $transactionId = $settleTransaction->id;
            }

            DB::commit();

            return [
                "error" => 0,
                "description" => "Success",
                "transactionId" => $transactionId,
                "currency" => $this->currencyCode,
                "cash" => number_format((float) $wallet_amount_after, 2, '.', ''),
                "bonus" => 0,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                "error" => $e->getCode(),
                "description" => $e->getMessage(),
            ];
        }
    }

    public function bonusWin(Request $request)
    {
        Log::debug("bonusWin===========>");
        Log::debug($request);

        $providerId = $request->providerId;
        $userId = $request->userId;
        $amount = $request->amount;
        $reference = $request->reference;

        $user = User::where("username", $userId)->first();
        if (!$user) {
            return [
                "error" => 2,
                "description" => "Player not found or is logged out."
            ];
        }

        $username = $user->username;
        $main_wallet = $user->main_wallet;
        $transactionId = 0;

        $bonusWinTransaction  = PPGame::select('id')
            ->where('action', '=', 'bonusWin')
            ->where('reference', '=', $reference)
            ->where('providerId', '=', $providerId)
            ->where('username', '=', $username)
            ->orderByDesc('id')
            ->first();

        if (!$bonusWinTransaction) {
            $transactionLog = $this->savaTransaction('bonusWin', $main_wallet, $main_wallet, $request, $username);
            if (!$transactionLog) {
                return [
                    "error" => 120,
                    "description" => "Fail (System Error)"
                ];
            }
            $transactionId = $transactionLog->id;
        } else {
            $transactionId = $bonusWinTransaction->id;
        }

        return [
            "error" => 0,
            "description" => "Success",
            "transactionId" => $transactionId,
            "currency" => $this->currencyCode,
            "cash" => number_format((float) $main_wallet, 2, '.', ''),
            "bonus" => 0,
        ];
    }

    public function jackpotWin(Request $request)
    {
        Log::debug("jackpotWin===========>");
        Log::debug($request);

        $providerId = $request->providerId;
        $userId = $request->userId;
        $amount = $request->amount;
        $reference = $request->reference;
        $jackpotId = $request->jackpotId;

        $user = User::where("username", $userId)->first();
        if (!$user) {
            return [
                "error" => 2,
                "description" => "Player not found or is logged out."
            ];
        }
        $username = $user->username;
        $main_wallet = $user->main_wallet;

        DB::beginTransaction();
        try {
            $userWallet = User::where('username', $username)->lockForUpdate()->first();
            $wallet_amount_before = $userWallet->main_wallet;
            $wallet_amount_after = $userWallet->main_wallet;
            $transactionId = 0;

            $jackpotWinTransaction  = PPGame::select('id')
                ->where('action', '=', 'jackpotWin')
                ->where('reference', '=', $reference)
                ->where('providerId', '=', $providerId)
                ->where('jackpotId', '=', $jackpotId)
                ->where('username', '=', $username)
                ->orderByDesc('id')
                ->first();

            if (!$jackpotWinTransaction) {
                $wallet_amount_after = $wallet_amount_after + $amount;

                User::where('username', $username)->update([
                    'main_wallet' => $wallet_amount_after
                ]);
                $transactionLog = $this->savaTransaction('jackpotWin', $wallet_amount_before, $wallet_amount_after, $request, $username);

                if (!$transactionLog) {
                    throw new \Exception('Fail (System Error)', 120);
                }
                $transactionId = $transactionLog->id;
            } else {
                $transactionId = $jackpotWinTransaction->id;
            }
            DB::commit();

            return [
                "error" => 0,
                "description" => "Success",
                "transactionId" => $transactionId,
                "currency" => $this->currencyCode,
                "cash" => number_format((float) $wallet_amount_after, 2, '.', ''),
                "bonus" => 0,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                "error" => $e->getCode(),
                "description" => $e->getMessage(),
            ];
        }
    }

    public function promoWin(Request $request)
    {
        Log::debug("promoWin===========>");
        Log::debug($request);

        $providerId = $request->providerId;
        $userId = $request->userId;
        $amount = $request->amount;
        $reference = $request->reference;
        $campaignId = $request->campaignId;

        $user = User::where("username", $userId)->first();
        if (!$user) {
            return [
                "error" => 2,
                "description" => "Player not found or is logged out."
            ];
        }
        $username = $user->username;
        $main_wallet = $user->main_wallet;

        DB::beginTransaction();
        try {
            $userWallet = User::where('username', $username)->lockForUpdate()->first();
            $wallet_amount_before = $userWallet->main_wallet;
            $wallet_amount_after = $userWallet->main_wallet;
            $transactionId = 0;


            $promoWinTransaction  = PPGame::select('id')
                ->where('action', '=', 'promoWin')
                ->where('reference', '=', $reference)
                ->where('providerId', '=', $providerId)
                ->where('campaignId', '=', $campaignId)
                ->where('username', '=', $username)
                ->orderByDesc('id')
                ->first();

            if (!$promoWinTransaction) {
                $wallet_amount_after = $wallet_amount_after + $amount;

                User::where('username', $username)->update([
                    'main_wallet' => $wallet_amount_after
                ]);

                $transactionLog = $this->savaTransaction('promoWin', $wallet_amount_before, $wallet_amount_after, $request, $username);

                if (!$transactionLog) {
                    throw new \Exception('Fail (System Error)', 120);
                }
                $transactionId = $transactionLog->id;
            } else {
                $transactionId = $promoWinTransaction->id;
            }
            DB::commit();

            return [
                "error" => 0,
                "description" => "Success",
                "transactionId" => $transactionId,
                "currency" => $this->currencyCode,
                "cash" => number_format((float) $wallet_amount_after, 2, '.', ''),
                "bonus" => 0,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                "error" => $e->getCode(),
                "description" => $e->getMessage(),
            ];
        }
    }

    private function savaTransaction($action, $wallet_amount_before, $wallet_amount_after, $payload, $username)
    {
        try {
            $transaction  = new PPGame();
            $transaction->action = $action;
            $transaction->wallet_amount_before = $wallet_amount_before;
            $transaction->wallet_amount_after = $wallet_amount_after;
            $transaction->username = $username;
            $transaction->amount = $payload->amount;
            $transaction->promoWinAmount = !empty($payload->promoWinAmount) ? $payload->promoWinAmount : 0;
            $transaction->gameId = $payload->gameId;
            $transaction->hash = $payload->hash;
            $transaction->providerId = $payload->providerId;
            $transaction->reference = $payload->reference;

            $transaction->roundId = !empty($payload->roundId) ? $payload->roundId : null;
            $transaction->roundDetails = !empty($payload->roundDetails) ? $payload->roundDetails : null;

            $transaction->jackpotId = !empty($payload->jackpotId) ? $payload->jackpotId : null;
            $transaction->jackpotDetails = !empty($payload->jackpotDetails) ? json_encode($payload->jackpotDetails) : null;

            $transaction->campaignId = !empty($payload->campaignId) ? $payload->campaignId : null;
            $transaction->campaignType = !empty($payload->campaignType) ? json_encode($payload->campaignType) : null;

            $transaction->timestamp = $payload->timestamp;
            if ($transaction->save()) {
                return $transaction;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            // echo $e;
            return false;
        }
    }
}
