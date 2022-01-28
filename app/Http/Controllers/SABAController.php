<?php

namespace App\Http\Controllers;

use App\Classes\Payment;
use App\Models\SABA;
use App\Models\User;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

class SABAController extends Controller
{
    const CONTROLLER_NAME = 'SABAController';

    const DOMAIN = 'c9s5api.bw6688.com';
    const API_URL = 'http://c9s5api.bw6688.com/api';
    const VENDOR_ID = 'orjr6g5ha3';
    const CURRENCY_ID = 4;
    const PREFIX = 'CB_';

    protected function buildFailedValidationResponse(Request $request, $errors)
    {
        return response()->json(['status' => '999', 'msg' => "System Error"]);
    }

    public static function routes()
    {

        Route::get('/ibc/launch/{token}/{mode}', self::CONTROLLER_NAME . '@launch');
        Route::get('/ibc', self::CONTROLLER_NAME . '@index');
        Route::post('/ibc/getbalance', self::CONTROLLER_NAME . '@getBalance');
        Route::post('/ibc/placebet', self::CONTROLLER_NAME . '@placeBet');
        Route::post('/ibc/confirmbet', self::CONTROLLER_NAME . '@confirmBet');
        Route::post('/ibc/cancelbet', self::CONTROLLER_NAME . '@cancelBet');
        Route::post('/ibc/settle', self::CONTROLLER_NAME . '@settle');
        Route::post('/ibc/resettle', self::CONTROLLER_NAME . '@reSettle');
        Route::post('/ibc/unsettle', self::CONTROLLER_NAME . '@unSettle');
        Route::post('/ibc/placebetparlay', self::CONTROLLER_NAME . '@placeBetParlay');
        Route::post('/ibc/confirmbetparlay', self::CONTROLLER_NAME . '@confirmBetParlay');
    }

    public function confirmBetParlay(Request $request)
    {
        $payload = $request->post();
        $message = $payload['message'];
        $this->log("confirmBetParlay", $payload);
        $txns = $message['txns'];
        $userId = str_replace(self::PREFIX, '', $message['userId']);

        $player = User::firstWhere('username', $userId);
        if (!$player) {
            return ['status' => '203', 'msg' => 'Account is not exist'];
        }

        if ($player->main_wallet < 0) {
            return ['status' => '307', 'msg' => "Invalid Amount"];
        }

        DB::beginTransaction();
        try {
            foreach ($txns as $txn) {
                $creditAmount = $txn['creditAmount'];
                $debitAmount = $txn['debitAmount'];
                $refId = $txn['refId'];
                $saba = SABA::firstWhere('ref', $refId);
                if ($saba) {
                    // หักเครดิต
                    if ($creditAmount > 0) {
                        $player->increment('main_wallet', $creditAmount);
                    }
                    if ($debitAmount > 0) {
                        $player->decrement('main_wallet', $debitAmount);
                    }
                    $saba->action = 'CONFIRMBET';
                    $saba->save();
                }
            }
            DB::commit();
            return ["status" => 0, "balance" => round($player->main_wallet, 2)];
        } catch (Exception $e) {
            DB::rollBack();
            return ['status' => '999', 'msg' => "System Error"];
        }
    }

    public function placeBetParlay(Request $request)
    {
        $payload = $request->post();
        $message = $payload['message'];
        $this->log("placeBetParlay", $payload);
        $txns = $message['txns'];
        $userId = str_replace(self::PREFIX, '', $message['userId']);

        $player = User::firstWhere('username', $userId);
        if (!$player) {
            return ['status' => '203', 'msg' => 'Account is not exist'];
        }
        $before_balance = $player->main_wallet;

        if ($player->main_wallet < 0) {
            return ['status' => '307', 'msg' => "Invalid Amount"];
        }

        $resTxns = [];
        DB::beginTransaction();
        try {
            foreach ($txns as $txn) {
                $creditAmount = $txn['creditAmount'];
                $debitAmount = $txn['debitAmount'];
                $txnRefId = $this->generateTxnRefId();
                $refId = $txn['refId'];
                $parlayType = $txn['parlayType'];
                $saba = SABA::firstWhere('ref', $txn['refId']);
                if (!$saba) {
                    // หักเครดิต
                    if ($creditAmount > 0) {
                        $player->increment('main_wallet', $creditAmount);
                    }
                    if ($debitAmount > 0) {
                        $player->decrement('main_wallet', $debitAmount);
                        (new Payment())->payAll($player->id, $debitAmount, 'SPORTSBOOK');
                        (new Payment())->saveLog([
                            'amount' => $debitAmount,
                            'before_balance' => $before_balance,
                            'after_balance' => $before_balance - $debitAmount,
                            'action' => 'BET',
                            'provider' => 'IBC',
                            'game_type' => 'SPORTBOOK',
                            'game_ref' => $parlayType,
                            'transaction_ref' => $refId,
                            'player_username' => $player->username,
                        ]);
                    }
                    // บันทึกประวัติ
                    $transaction = new SABA();
                    $transaction->operator_ref = $txnRefId;
                    $transaction->ref = $refId;
                    $transaction->action = 'BET';
                    $transaction->bet_amount = $debitAmount;
                    $transaction->profit = $debitAmount * -1;
                    $transaction->payload = json_encode($txn);
                    $transaction->save();
                    array_push($resTxns, [
                        "refId"          => $refId,
                        "licenseeTxId"   => $txnRefId
                    ]);
                }
            }
            DB::commit();
            return ["status" => 0, "txns" => $resTxns];
        } catch (Exception $e) {
            DB::rollBack();
            return ['status' => '999', 'msg' => "System Error"];
        }
    }

    public function unSettle(Request $request)
    {
        $payload = $request->post();
        $message = $payload['message'];
        $this->log("UnSettle", $payload);
        $txns = $message['txns'];

        DB::beginTransaction();
        try {
            foreach ($txns as $txn) {
                $userId = str_replace(self::PREFIX, '', $txn['userId']);
                $creditAmount = $txn['creditAmount'];
                $debitAmount = $txn['debitAmount'];
                $saba = SABA::firstWhere('ref', $txn['refId']);
                if ($saba) {
                    $player = User::firstWhere('username', $userId);
                    if ($player) {
                        if ($creditAmount > 0) {
                            $player->increment('main_wallet', $creditAmount);
                            $saba->winlose =  $creditAmount;
                            $saba->profit = $saba->profit + $creditAmount;
                        }
                        if ($debitAmount > 0) {
                            $player->decrement('main_wallet', $debitAmount);
                            $saba->winlose =  $saba->winlose - $debitAmount;
                            $saba->profit = $saba->profit - ($saba->profit - $creditAmount);
                        }
                        $saba->action = 'UNSETTLE';
                        $saba->save();
                    }
                }
            }
            DB::commit();
            return ['status' => 0];
        } catch (Exception $e) {
            DB::rollBack();
            return ['status' => '999', 'msg' => "System Error"];
        }
    }

    public function reSettle(Request $request)
    {
        $payload = $request->post();
        $message = $payload['message'];
        $this->log("ReSettle", $payload);
        $txns = $message['txns'];

        DB::beginTransaction();
        try {
            foreach ($txns as $txn) {
                $userId = str_replace(self::PREFIX, '', $txn['userId']);
                $creditAmount = $txn['creditAmount'];
                $debitAmount = $txn['debitAmount'];
                $saba = SABA::firstWhere('ref', $txn['refId']);
                if ($saba) {
                    $player = User::firstWhere('username', $userId);
                    if ($player) {
                        if ($creditAmount > 0) {
                            $player->increment('main_wallet', $creditAmount);
                            $saba->winlose =  $creditAmount;
                            $saba->profit = $saba->profit + $creditAmount;
                        }
                        if ($debitAmount > 0) {
                            $player->decrement('main_wallet', $debitAmount);
                            $saba->winlose =  $saba->winlose - $debitAmount;
                            $saba->profit = $saba->profit - ($saba->profit - $creditAmount);
                        }
                        $saba->action = 'RESETTLE';
                        $saba->save();
                    }
                }
            }
            DB::commit();
            return ['status' => 0];
        } catch (Exception $e) {
            DB::rollBack();
            return ['status' => '999', 'msg' => "System Error"];
        }
    }

    public function settle(Request $request)
    {
        $payload = $request->post();
        $message = $payload['message'];
        $this->log("Settle", $payload);
        $txns = $message['txns'];

        DB::beginTransaction();
        try {
            foreach ($txns as $txn) {
                $refId = $txn['refId'];
                $userId = str_replace(self::PREFIX, '', $txn['userId']);
                $creditAmount = $txn['creditAmount'];
                $debitAmount = $txn['debitAmount'];
                $saba = SABA::firstWhere('ref', $refId);
                if ($saba) {
                    $player = User::firstWhere('username', $userId);
                    if ($player) {
                        $before_balance = $player->main_wallet;
                        if ($creditAmount > 0) {
                            $player->increment('main_wallet', $creditAmount);
                            $saba->winlose =  $creditAmount;
                            $saba->profit = $saba->profit + $creditAmount;
                            (new Payment())->saveLog([
                                'amount' => $creditAmount,
                                'before_balance' => $before_balance,
                                'after_balance' => $before_balance + $creditAmount,
                                'action' => 'SETTLE',
                                'provider' => 'IBC',
                                'game_type' => 'SPORTBOOK',
                                'game_ref' => "NO REF",
                                'transaction_ref' => $refId,
                                'player_username' => $player->username,
                            ]);
                        }
                        if ($debitAmount > 0) {
                            $player->decrement('main_wallet', $debitAmount);
                            $saba->winlose =  $saba->winlose - $debitAmount;
                            $saba->profit = $saba->profit - ($saba->profit - $creditAmount);
                        }
                        $saba->action = 'SETTLE';
                        $saba->save();
                    }
                }
            }
            DB::commit();
            return ['status' => 0];
        } catch (Exception $e) {
            DB::rollBack();
            return ['status' => '999', 'msg' => "System Error"];
        }
    }

    public function cancelBet(Request $request)
    {
        $payload = $request->post();
        $message = $payload['message'];
        $this->log("Cancel", $payload);
        $txns = $message['txns'];
        $userId = str_replace(self::PREFIX, '', $message['userId']);

        $player = User::firstWhere('username', $userId);
        if (!$player) {
            return ['status' => '203', 'msg' => 'Account is not exist'];
        }

        DB::beginTransaction();
        try {
            foreach ($txns as $txn) {
                $creditAmount = $txn['creditAmount'];
                $debitAmount = $txn['debitAmount'];
                // ค้นหา Transaction
                $saba = SABA::firstWhere('ref', $txn['refId']);
                if ($saba) {
                    if ($creditAmount > 0) {
                        $player->increment('main_wallet', $creditAmount);
                        $saba->winlose =  $creditAmount;
                        $saba->profit = $saba->profit + $creditAmount;
                    }
                    if ($debitAmount > 0) {
                        $player->decrement('main_wallet', $debitAmount);
                        $saba->winlose =  $saba->winlose - $debitAmount;
                        $saba->profit = $saba->profit - ($saba->profit - $creditAmount);
                    }
                    $saba->action = 'CANCELBET';
                    $saba->save();
                }
            }
            DB::commit();
            $response =  [
                'status' => '0',
                'balance' => $player->main_wallet
            ];
            $this->log("CancelBet Response", $response);
            return $response;
        } catch (Exception $e) {
            DB::rollBack();
            return ['status' => '999', 'msg' => "System Error"];
        }
    }

    public function confirmBet(Request $request)
    {
        $payload = $request->post();
        $message = $payload['message'];
        $this->log("Confirm", $payload);
        $txnIn = $message['txns'][0];
        $userId = str_replace(self::PREFIX, '', $message['userId']);

        $txn = SABA::firstWhere('operator_ref', $txnIn['licenseeTxId']);
        if (!$txn) {
            return  ['status' => '-1', 'msg' => "bet not exist"];
        }

        $player = User::firstWhere('username', $userId);
        if (!$player) {
            return ['status' => '203', 'msg' => 'Account is not exist'];
        }

        if ($txn->action == 'CONFIRMBET') {
            $response =  [
                'status' => '0',
                'balance' => $player->main_wallet
            ];
            $this->log("ConfrimBet Response", $response);
            return $response;
        }

        DB::beginTransaction();
        try {
            if ($txn->action == 'BET') {

                if ($txnIn['isOddsChanged']) {
                    $debitAmount = $txnIn['debitAmount'];
                    $player->increment('main_wallet', $debitAmount);
                    $txn->action = 'CANCEL';
                    $txn->profit = 0;
                    $txn->winlose = 0;
                    $txn->save();

                    $response =  [
                        'status' => '0',
                        'balance' => $player->main_wallet
                    ];
                    $this->log("ConfrimBet Response", $response);
                    return $response;
                }

                $txn->action = 'CONFIRMBET';
                $txn->save();
                DB::commit();
                $response =  [
                    'status' => '0',
                    'balance' => $player->main_wallet
                ];
                $this->log("ConfrimBet Response", $response);
                return $response;
            } else {
                return ['status' => '501', 'msg' => "Permission Denied"];
            }
        } catch (Exception $e) {
            DB::rollBack();
            return ['status' => '501', 'msg' => "Permission Denied"];
        }
    }

    public function placeBet(Request $request)
    {
        $payload = $request->post();
        $message = $payload['message'];
        $this->log("Place Bet", $payload);
        $txnRefId = $this->generateTxnRefId();
        $refId = $message['refId'];
        $userId = str_replace(self::PREFIX, '', $message['userId']);
        $debitAmount = $message['debitAmount'];
        $leagueName = $message['leagueName_en'];

        $transaction = SABA::firstWhere('ref', $refId);
        if ($transaction) {
            return ['status' => '1', 'msg' => "Duplicate Transaction"];
        }

        $player = User::firstWhere('username', $userId);
        if (!$player) {
            return ['status' => '203', 'msg' => 'Account is not exist'];
        }
        $before_balance = $player->main_wallet;

        if ($player->main_wallet < $debitAmount) {
            return ['status' => '307', 'msg' => "Invalid Amount"];
        }

        DB::beginTransaction();
        try {
            if ($message['action'] == 'bet') {
                $player->decrement('main_wallet', $debitAmount);
                $transaction = new SABA();
                $transaction->operator_ref = $txnRefId;
                $transaction->ref = $refId;
                $transaction->action = 'BET';
                $transaction->bet_amount = $debitAmount;
                $transaction->profit = $debitAmount * -1;
                $transaction->payload = json_encode($message);
                $transaction->save();

                (new Payment())->payAll($player->id, $debitAmount, 'SPORTSBOOK');
                (new Payment())->saveLog([
                    'amount' => $debitAmount,
                    'before_balance' => $before_balance,
                    'after_balance' => $before_balance - $debitAmount,
                    'action' => 'BET',
                    'provider' => 'IBC',
                    'game_type' => 'SPORTBOOK',
                    'game_ref' => $leagueName,
                    'transaction_ref' => $refId,
                    'player_username' => $player->username,
                ]);

                DB::commit();
                return [
                    "status"         => "0",
                    "refId"          => $refId,
                    "licenseeTxId"   => $txnRefId
                ];
            } else {
                return ['status' => '309', 'msg' => "Invalid Transaction Status"];
            }
        } catch (Exception $error) {
            DB::rollBack();
            $this->log("Error", $error->getMessage());
            return ['status' => '999', 'msg' => "System Error"];
        }
    }

    public function getBalance(Request $request)
    {
        $payload = $request->post();
        $message = $payload['message'];
        $this->log("Get Balance", $payload);
        $userId = str_replace(self::PREFIX, '', $message['userId']);

        $player = User::firstWhere('username', $userId);
        if (!$player) {
            return ['status' => '203', 'msg' => 'Account is not exist'];
        }

        $d = DateTime::createFromFormat('U.u', microtime(TRUE))->format('Y-m-d');
        $t = DateTime::createFromFormat('U.u', microtime(TRUE))->format('H:i:s.u');
        $ISO8601 = $d . 'T' . $t . '-4:00';

        $response =  [
            'status' => '0',
            'userId' => $userId,
            'balance' => $player->main_wallet,
            'balanceTs' => $ISO8601,
            'msg' => null,
        ];

        $this->log("Get Balance Response", $response);
        return $response;
    }

    public function index()
    {
        // return $this->createMember();
        // return $this->login();
        return ['message' => 'Hello, World'];
    }

    public function launch($token, $mode)
    {
        $player = User::firstWhere('token', $token);
        if (!$player) {
            return response()->json(['message' => 'Invalid Token'], 400);
        }
        $username = $player->username;
        $created = $this->createMember($username);
        $loggedIn = null;
        if ($created['error_code'] == 0) {
            $loggedIn = $this->login($username, $mode);
        } else {
            $loggedIn = $this->login($username, $mode);
        }
        $launchUrl = $loggedIn['Data'] . "&lang=th";
        return redirect($launchUrl);
    }

    public function createMember($username)
    {
        $url = self::API_URL . '/CreateMember';

        $payload =  [
            "vendor_id" => self::VENDOR_ID,
            "vendor_member_id" => self::PREFIX . $username,
            "operatorid" => "ZhiFeng084",
            "username" => self::PREFIX . $username,
            "oddstype" => "1",
            "currency" => self::CURRENCY_ID,
            "maxtransfer" => 1000000,
            "mintransfer" => 1,
        ];

        $response = Http::asForm()->post($url, $payload);
        return $response;
    }

    public function login($username, $mode)
    {
        $url = self::API_URL . '/GetSabaUrl';

        $payload = [
            'vendor_id' => self::VENDOR_ID,
            'platform' => $mode == 'desktop' ? 1 : 2,
            'vendor_member_id' => self::PREFIX . $username,
        ];

        $response = Http::asForm()->post($url, $payload);
        return $response;
    }

    private function generateTxnRefId($addtime = 0)
    {
        $n = rand(111, 999);
        $time = time();

        $output = date("d");
        $output .= date("m");
        $output .= $time + $addtime;
        $output .= $n;
        return $output;
    }

    private function log($message, $payload)
    {
        Log::debug(self::CONTROLLER_NAME);
        Log::debug($message);
        Log::debug(json_encode($payload));
        Log::debug('-----------------------------------------------');
    }
}
