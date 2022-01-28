<?php

namespace App\Http\Controllers;

use App\Classes\Payment;
use App\Models\User;
use App\Models\DreamGaming;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

class DreamGamingController extends Controller
{
    const CONTROLLER_NAME = 'DreamGamingController';

    const ERROR_VERIFICATION_TOKEN_FAILED = 2;
    const ERROR_TRANSFER_FAILED = 324;
    const ERROR_ACCOUNT_NOT_EXISTS = 102;

    const API_URL = 'https://api.dg0.co';
    const API_KEY = '1a59bc54c6994a6da73086bf921f356e';
    const AGENT_CODE = 'DG10063605';

    public static function routes()
    {
        Route::get('/dream/launch/{token}', self::CONTROLLER_NAME . '@launch');
        Route::post('/dream/user/getBalance/{agentCode}', self::CONTROLLER_NAME . '@getBalance');
        Route::post('/dream/account/transfer/{agentCode}', self::CONTROLLER_NAME . '@transfer');
        Route::post('/dream/account/checkTransfer/{agentCode}', self::CONTROLLER_NAME . '@checkTransfer');
        Route::post('/dream/account/inform/{agentCode}', self::CONTROLLER_NAME . '@inform');
        Route::post('/dream/account/order/{agentCode}', self::CONTROLLER_NAME . '@order');
        Route::post('/dream/account/unsettle/{agentCode}', self::CONTROLLER_NAME . '@unsettle');
    }

    public function transfer(Request $request)
    {
        $payload = $request->post();
        $this->log("transfer", $payload);
        $ticket_id = $payload['data'];
        $token = $payload['token'];
        $username = $payload['member']['username'];
        $amount = $payload['member']['amount'];
        $ref = $payload['data'];
        $action = $amount < 0 ? 'TRANSFER_OUT' : 'TRANSFER_IN';

        if (!$this->verifyToken($token)) {
            return ['token' => $token, 'codeId' => self::ERROR_VERIFICATION_TOKEN_FAILED];
        }

        $transaction = DreamGaming::firstWhere('ticket_id', $ticket_id);
        if ($transaction) {
            return ['token' => $token, 'codeId' => self::ERROR_TRANSFER_FAILED];
        }

        $player = User::firstWhere('username', $username);
        if (!$player) {
            return ['token' => $token, 'codeId' => self::ERROR_ACCOUNT_NOT_EXISTS];
        }

        $before_balance = $player->main_wallet;
        $player->increment('main_wallet', $amount);
        $after_balance = $player->main_wallet;

        if ($action == 'TRANSFER_OUT') {
            (new Payment())->payAll($player->id, $amount * -1, 'CASINO');
        }

        (new Payment())->saveLog([
            'amount' => $amount,
            'before_balance' => $before_balance,
            'after_balance' => $after_balance,
            'action' => $amount < 0 ? 'BET' : 'SETTLE',
            'provider' => 'DG',
            'game_type' => 'CASINO',
            'game_ref' => $ticket_id,
            'transaction_ref' => $ref,
            'player_username' => $player->username,
        ]);

        $transaction = new DreamGaming();
        $transaction->ticket_id = $ticket_id;
        $transaction->username = $username;
        $transaction->amount = $amount;
        $transaction->before_balance = $before_balance;
        $transaction->after_balancee = $after_balance;
        $transaction->action = $action;
        $transaction->save();

        $response = [
            'codeId' => 0,
            'token' => $token,
            'data' => $payload['data'],
            'member' => [
                'username' => $username,
                'amount' => $amount,
                'balance' => $before_balance
            ]
        ];
        return $response;
    }

    public function checkTransfer(Request $request)
    {
        $payload = $request->post();
        $this->log("checkTransfer income", $payload);
        $ticket_id = $payload['data'];
        $token = $payload['token'];

        if (!$this->verifyToken($token)) {
            return ['token' => $token, 'codeId' => self::ERROR_VERIFICATION_TOKEN_FAILED];
        }

        $transaction = DreamGaming::firstWhere('ticket_id', $ticket_id);
        if ($transaction) {
            return ['token' => $token, 'codeId' => 0];
        } else {
            return ['token' => $token, 'codeId' => 98];
        }
    }

    public function inform(Request $request)
    {
        $payload = $request->post();
        $this->log("inform", $payload);
        $token = $payload['token'];
        $ticket_id = $payload['data'];
        $username = $payload['member']['username'];
        $amount = $payload['member']['amount'];

        if (!$this->verifyToken($token)) {
            return ['token' => $token, 'codeId' => self::ERROR_VERIFICATION_TOKEN_FAILED];
        }

        $player = User::firstWhere('username', $username);
        if (!$player) {
            return ['token' => $token, 'codeId' => self::ERROR_ACCOUNT_NOT_EXISTS];
        }

        $transaction = DreamGaming::firstWhere('ticket_id', $ticket_id);

        if ($amount < 0) {
            $transaction->delete();
        } else {
            if (!$transaction) {
                $transaction = new DreamGaming();
                $transaction->ticket_id = $ticket_id;
                $transaction->username = $username;
                $transaction->amount = 0;
                $transaction->before_balance = $player->main_wallet;
                $transaction->after_balancee = $player->main_wallet;
                $transaction->action = 'UNKOWN';
                $transaction->save();
            }
        }

        $response = [
            'codeId' => 0,
            'token' => $token,
            'data' => $ticket_id,
            'member' => [
                'username' => $username,
                'balance' => $player->main_wallet
            ]
        ];
        return $response;
    }

    public function order(Request $request)
    {
        $payload = $request->post();
        $this->log("order", $payload);
    }

    public function unsettle(Request $request)
    {
        $payload = $request->post();
        $this->log("unsettle", $payload);
    }

    public function getBalance(Request $request)
    {
        $payload = $request->post();
        $this->log("getBalance", $payload);
        $token = $payload['token'];
        $username = $payload['member']['username'];

        if (!$this->verifyToken($token)) {
            return ['codeId' => self::ERROR_VERIFICATION_TOKEN_FAILED];
        }

        $player = User::firstWhere('username', $username);
        if (!$player) {
            return ['codeId' => self::ERROR_ACCOUNT_NOT_EXISTS];
        }

        return [
            'codeId' => 0,
            'token' => $token,
            'member' =>
            [
                'username' => $player->username,
                'balance' => $player->main_wallet
            ]
        ];
    }

    public function launch($token)
    {
        $player = User::firstWhere('token', $token);
        if (!$player) {
            return response()->json(['message' => 'Invalid Token'], 400);
        }
        $username = $player->username;
        $loggedIn = $this->login($username);

        if ($loggedIn['codeId'] == 102) {
            $created = $this->createPlayer($username);
            if ($created['codeId'] == 0) {
                $loggedIn = $this->login($username);
            }
        }

        $token = $loggedIn['token'];
        $launchUrl = $loggedIn['list'][0] . $token . "&language=th";
        return redirect($launchUrl);
    }

    private function login($username)
    {
        $url = self::API_URL . '/user/login/' . self::AGENT_CODE;
        $random = $this->random();
        $token = $this->token($random);

        $payload = [
            'token' => $token,
            'random' => $random,
            'lang' => 'th',
            'member' => [
                'username' => $username
            ]
        ];

        $response = Http::post($url, $payload);
        return $response;
    }

    private function createPlayer($username)
    {
        $url = self::API_URL . '/user/signup/' . self::AGENT_CODE;
        $random = $this->random();
        $token = $this->token($random);

        $payload = [
            'token' => $token,
            'random' => $random,
            'data' => 'P',
            'member' => [
                'username' => $username,
                'password' => $random,
                'currencyName' => 'THB',
                'winLimit' => 1000000
            ]
        ];

        $response = Http::post($url, $payload);
        return $response;
    }

    private function verifyToken($incomeToken)
    {
        return $incomeToken === $this->token();
    }

    private function token($random = null)
    {
        return md5(self::AGENT_CODE . self::API_KEY . $random);
    }

    private function random()
    {
        return md5(time() * rand());
    }

    private function log($message, $payload)
    {
        Log::debug(self::CONTROLLER_NAME);
        Log::debug($message);
        Log::debug(json_encode($payload));
        Log::debug('-----------------------------------------------');
    }
}
