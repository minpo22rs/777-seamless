<?php

namespace App\Http\Controllers;

use App\Models\HuayDragon;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

class HuayDragonController extends Controller
{

    const BYPASS_SIGNATURE = true;
    const CONTROLLER_NAME = 'HuayDragonController';

    const API_URL = 'https://api-huaydragon.com';
    const SECRET_KEY = '6216657e4f1b45263a347ec2';
    const USERNAME = 'mm777betprd';
    const PASSWORD = 'Welcome1!';
    const AGENT = 'mm777betprd';
    const PREFIX = 'MM7_';

    protected function buildFailedValidationResponse(Request $request, $errors)
    {
        return response()->json(['message' => 'error']);
    }

    public static function routes()
    {
        Route::get('/huaydragon/test', self::CONTROLLER_NAME . '@index');
        Route::get('/huaydragon/launch/{username}', self::CONTROLLER_NAME . '@launch');
        Route::post('/huaydragon/balance', self::CONTROLLER_NAME . '@getBalance');
        Route::post('/huaydragon/bet', self::CONTROLLER_NAME . '@bet');
        Route::post('/huaydragon/payout', self::CONTROLLER_NAME . '@payout');
        Route::post('/huaydragon/cancel', self::CONTROLLER_NAME . '@cancel');
        Route::post('/huaydragon/void', self::CONTROLLER_NAME . '@void');
        Route::post('/huaydragon/cancel_number', self::CONTROLLER_NAME . '@cancelNumber');
    }

    public function cancelNumber(Request $request)
    {
        $payload = $request->post();
        $header = $request->header();
        $signature = isset($header['x-signature']) ? $header['x-signature'][0] : null;
        $requestUsername = $payload['username'];
        $username = str_replace(self::PREFIX, '', $requestUsername);

        $this->log("Cancel Number", ['signature' => $signature, 'payload' => $payload]);

        // Verify signature
        if (!$this->verifySignature($signature, $payload)) {
            return $this->errorResponse('INVALID_REQUEST_DATA');
        }

        // Find player
        $player = User::firstWhere('username', $username);
        if (!$player) {
            return $this->errorResponse('INVALID_REQUEST_DATA');
        }

        // Verify poy
        $poyId = $payload['poyId'];
        $transaction = HuayDragon::firstWhere('poyId', $poyId);

        if (!$transaction) {
            return $this->errorResponse('INVALID_REQUEST_DATA');
        }

        // Verify the number has been canceled yet 
        $number = $payload['number'];
        $explodeCancelNumber = explode(',', $transaction->cancel_number);
        if (in_array($number, $explodeCancelNumber)) {
            return $this->errorResponse('DUPLICATE_POY_ID');
        }

        // Other require variable
        $refId = $payload['refId'];
        $amount = $payload['amount'];

        DB::beginTransaction();
        try {
            // Increment player balance
            $beforeBalance = $player->main_wallet;
            $player->increment('main_wallet', $amount);
            $afterBalance = $player->main_wallet;
            $lastUpdate = $player->updated_at;
            // Update transaction
            $transaction->increment('cancel', $amount);
            $transaction->decrement('winlose', $amount * -1);
            $cancelNumber = $transaction->cancel_number;
            if (!$cancelNumber) {
                $transaction->cancel_number = $number;
            } else {
                $transaction->cancel_number = $cancelNumber . ",$number";
            }
            $transaction->save();
            // Commit DB
            DB::commit();
            // Return response
            $okData = [
                'username' => $requestUsername,
                'wallet' => [
                    'balance' => $afterBalance,
                    'lastUpdate' => $lastUpdate,
                ],
                'balance' => [
                    'before' => $beforeBalance,
                    'after' => $afterBalance,
                ],
                'refId' => $refId
            ];
            return $this->okResponse($okData);
        } catch (Exception $e) {
            DB::rollBack();
            $this->log("Void Error", $e->getMessage());
            return $this->errorResponse('SERVICE_NOT_AVAILABLE');
        }
    }

    public function void(Request $request)
    {
        $payload = $request->post();
        $header = $request->header();
        $signature = isset($header['x-signature']) ? $header['x-signature'][0] : null;
        $requestUsername = $payload['username'];
        $username = str_replace(self::PREFIX, '', $requestUsername);

        $this->log("Void", ['signature' => $signature, 'payload' => $payload]);

        // Verify signature
        if (!$this->verifySignature($signature, $payload)) {
            return $this->errorResponse('INVALID_REQUEST_DATA');
        }

        // Find player
        $player = User::firstWhere('username', $username);
        if (!$player) {
            return $this->errorResponse('INVALID_REQUEST_DATA');
        }

        // Verify poy
        $poyId = $payload['poyId'];
        $transaction = HuayDragon::firstWhere('poyId', $poyId);

        if (!$transaction) {
            return $this->errorResponse('INVALID_REQUEST_DATA');
        }

        if ($transaction && $transaction->status == 'VOID') {
            return $this->errorResponse('DUPLICATE_POY_ID');
        }

        // Other require variable
        $refId = $payload['refId'];
        $amount = $payload['amount'] * -1;

        DB::beginTransaction();
        try {
            // Deduct player balance
            $beforeBalance = $player->main_wallet;
            $player->decrement('main_wallet', $amount);
            $afterBalance = $player->main_wallet;
            $lastUpdate = $player->updated_at;
            // Update transaction
            $transaction->payout = 0;
            $transaction->winlose = ($transaction->amount - $transaction->cancel) * -1;
            $transaction->status = 'VOID';
            $transaction->save();
            // Commit DB
            DB::commit();
            // Return response
            $okData = [
                'username' => $requestUsername,
                'wallet' => [
                    'balance' => $afterBalance,
                    'lastUpdate' => $lastUpdate,
                ],
                'balance' => [
                    'before' => $beforeBalance,
                    'after' => $afterBalance,
                ],
                'refId' => $refId
            ];
            return $this->okResponse($okData);
        } catch (Exception $e) {
            DB::rollBack();
            $this->log("Void Error", $e->getMessage());
            return $this->errorResponse('SERVICE_NOT_AVAILABLE');
        }
    }

    public function cancel(Request $request)
    {
        $payload = $request->post();
        $header = $request->header();
        $signature = isset($header['x-signature']) ? $header['x-signature'][0] : null;
        $requestUsername = $payload['username'];
        $username = str_replace(self::PREFIX, '', $requestUsername);

        $this->log("Cancel", ['signature' => $signature, 'payload' => $payload]);

        // Verify signature
        if (!$this->verifySignature($signature, $payload)) {
            return $this->errorResponse('INVALID_REQUEST_DATA');
        }

        // Find player
        $player = User::firstWhere('username', $username);
        if (!$player) {
            return $this->errorResponse('INVALID_REQUEST_DATA');
        }

        // Verify poy
        $poyId = $payload['poyId'];
        $transaction = HuayDragon::firstWhere('poyId', $poyId);

        if (!$transaction) {
            return $this->errorResponse('INVALID_REQUEST_DATA');
        }

        if ($transaction && $transaction->status == 'CANCEL') {
            return $this->errorResponse('DUPLICATE_POY_ID');
        }

        // Other require variable
        $refId = $payload['refId'];
        $amount = $payload['amount'];

        DB::beginTransaction();
        try {
            // Increment player balance
            $beforeBalance = $player->main_wallet;
            $player->increment('main_wallet', $amount);
            $afterBalance = $player->main_wallet;
            $lastUpdate = $player->updated_at;
            // Update transaction
            $transaction->payout = 0;
            $transaction->cancel = $transaction->amount;
            $transaction->winlose = 0;
            $transaction->status = 'CANCEL';
            $transaction->save();
            // Commit DB
            DB::commit();
            // Return response
            $okData = [
                'username' => $requestUsername,
                'wallet' => [
                    'balance' => $afterBalance,
                    'lastUpdate' => $lastUpdate,
                ],
                'balance' => [
                    'before' => $beforeBalance,
                    'after' => $afterBalance,
                ],
                'refId' => $refId
            ];
            return $this->okResponse($okData);
        } catch (Exception $e) {
            DB::rollBack();
            $this->log("Cancel Error", $e->getMessage());
            return $this->errorResponse('SERVICE_NOT_AVAILABLE');
        }
    }

    public function payout(Request $request)
    {
        $payload = $request->post();
        $header = $request->header();
        $signature = isset($header['x-signature']) ? $header['x-signature'][0] : null;
        $requestUsername = $payload['username'];
        $username = str_replace(self::PREFIX, '', $requestUsername);

        $this->log("Payout", ['signature' => $signature, 'payload' => $payload]);

        // Verify signature
        if (!$this->verifySignature($signature, $payload)) {
            return $this->errorResponse('INVALID_REQUEST_DATA');
        }

        // Find player
        $player = User::firstWhere('username', $username);
        if (!$player) {
            return $this->errorResponse('INVALID_REQUEST_DATA');
        }

        // Verify poy
        $poyId = $payload['poyId'];
        $transaction = HuayDragon::firstWhere('poyId', $poyId);

        if (!$transaction) {
            return $this->errorResponse('INVALID_REQUEST_DATA');
        }

        if ($transaction && $transaction->status == 'PAYOUT') {
            return $this->errorResponse('DUPLICATE_POY_ID');
        }

        // Other require variable
        $refId = $payload['refId'];
        $amount = $payload['amount'];

        DB::beginTransaction();
        try {
            // Increment player balance
            $beforeBalance = $player->main_wallet;
            $player->increment('main_wallet', $amount);
            $afterBalance = $player->main_wallet;
            $lastUpdate = $player->updated_at;
            // Update transaction
            $transaction->increment('winlose', $amount);
            $transaction->payout = $amount;
            $transaction->status = 'PAYOUT';
            $transaction->save();
            // Commit DB
            DB::commit();
            // Return response
            $okData = [
                'username' => $requestUsername,
                'wallet' => [
                    'balance' => $afterBalance,
                    'lastUpdate' => $lastUpdate,
                ],
                'balance' => [
                    'before' => $beforeBalance,
                    'after' => $afterBalance,
                ],
                'refId' => $refId
            ];
            return $this->okResponse($okData);
        } catch (Exception $e) {
            DB::rollBack();
            $this->log("Payout Error", $e->getMessage());
            return $this->errorResponse('SERVICE_NOT_AVAILABLE');
        }
    }

    public function bet(Request $request)
    {
        $payload = $request->post();
        $header = $request->header();
        $signature = isset($header['x-signature']) ? $header['x-signature'][0] : null;
        $requestUsername = $payload['username'];
        $username = str_replace(self::PREFIX, '', $requestUsername);

        $this->log("Bet", ['signature' => $signature, 'payload' => $payload]);

        // Verify signature
        if (!$this->verifySignature($signature, $payload)) {
            return $this->errorResponse('INVALID_REQUEST_DATA');
        }

        // Find player
        $player = User::firstWhere('username', $username);
        if (!$player) {
            return $this->errorResponse('INVALID_REQUEST_DATA');
        }

        // Verify player's balance
        $playerCurrentBalance = $player->main_wallet;
        $amount = $payload['amount'];
        if ($playerCurrentBalance < $amount) {
            return $this->errorResponse('BALANCE_INSUFFICIENT');
        }

        // Verify poy
        $poyId = $payload['poyId'];
        $transaction = HuayDragon::firstWhere('poyId', $poyId);
        if ($transaction) {
            return $this->errorResponse('DUPLICATE_POY_ID');
        }

        // Other require variable
        $refId = $payload['refId'];
        $roundId = $payload['roundId'];
        $gameName = $payload['gameName'];
        $category = $payload['category'];

        DB::beginTransaction();
        try {
            // Deduct player balance
            $beforeBalance = $player->main_wallet;
            $player->decrement('main_wallet', $amount);
            $afterBalance = $player->main_wallet;
            $lastUpdate = $player->updated_at;
            // Store transaction
            $transaction = new HuayDragon();
            $transaction->refId = $refId;
            $transaction->poyId = $poyId;
            $transaction->roundId = $roundId;
            $transaction->username = $username;
            $transaction->amount = $amount;
            $transaction->winlose = $amount * -1;
            $transaction->gameName = $gameName;
            $transaction->category = $category;
            $transaction->status = 'BET';
            $transaction->save();
            // Commit DB
            DB::commit();
            // Return response
            $okData = [
                'username' => $requestUsername,
                'wallet' => [
                    'balance' => $afterBalance,
                    'lastUpdate' => $lastUpdate,
                ],
                'balance' => [
                    'before' => $beforeBalance,
                    'after' => $afterBalance,
                ],
                'refId' => $refId
            ];
            return $this->okResponse($okData);
        } catch (Exception $e) {
            DB::rollBack();
            $this->log("Bet Error", $e->getMessage());
            return $this->errorResponse('SERVICE_NOT_AVAILABLE');
        }
    }

    public function getBalance(Request $request)
    {
        $payload = $request->post();
        $header = $request->header();
        $signature = isset($header['x-signature']) ? $header['x-signature'][0] : null;
        $username = str_replace(self::PREFIX, '', $payload['username']);

        $this->log("Get Balance", ['signature' => $signature, 'payload' => $payload]);

        // Verify signature
        if (!$this->verifySignature($signature, $payload)) {
            return $this->errorResponse('INVALID_REQUEST_DATA');
        }

        // Find player
        $player = User::firstWhere('username', $username);
        if (!$player) {
            return $this->errorResponse('INVALID_REQUEST_DATA');
        }

        // Return response
        $okData = [
            "balance" => $player->main_wallet
        ];
        return $this->okResponse($okData);
    }

    public function launch($username)
    {
        $url = self::API_URL . '/seamless/launch';

        $payload =  [
            "username" =>  self::PREFIX . $username,
            "agent" => self::AGENT,
        ];

        $header = [
            'x-signature' => $this->encryptSignature($payload),
        ];

        $response = Http::withHeaders($header)->post($url, $payload);

        return redirect($response['data']['url']);
    }

    private function verifySignature($signature, $payload)
    {
        $compareSignature = $this->encryptSignature($payload);
        $this->log("Verify Signature", ['signature' => $signature, 'compareSignature' => $compareSignature]);
        if (self::BYPASS_SIGNATURE) {
            return true;
        }
        if ($signature == $compareSignature) {
            return true;
        } else {
            return false;
        }
    }

    private function encryptSignature($payload)
    {
        $iterations = 1000;
        $hash = hash_pbkdf2("sha512", json_encode($payload), self::SECRET_KEY, $iterations, 64, true);
        return base64_encode($hash);
    }

    private function errorResponse($type)
    {
        $environment = [
            "SERVICE_NOT_AVAILABLE" => [
                "code" => 999,
                "message" => 'Service not available'
            ],
            "INVALID_AGENT_ID" => [
                "code" => 998,
                "message" => 'Invalid agent id'
            ],
            "INVALID_REQUEST_DATA" => [
                "code" => 997,
                "message" => 'Invalid request data'
            ],
            "DUPLICATE_POY_ID" => [
                "code" => 806,
                "message" => 'Duplicate Poy Id'
            ],
            "BALANCE_INSUFFICIENT" => [
                "code" => 800,
                "message" => 'Balance insufficient'
            ],
        ];
        return [
            'status' => $environment[$type]
        ];
    }

    private function okResponse($payload)
    {
        return [
            'status' => [
                'code' => 0,
                'message' => 'Success'
            ],
            'data' => $payload
        ];
    }

    private function log($message, $payload)
    {
        Log::debug(self::CONTROLLER_NAME);
        Log::debug($message);
        Log::debug(json_encode($payload));
        Log::debug('-----------------------------------------------');
    }
}
