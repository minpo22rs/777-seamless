<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\SexyGame;
use Faker\Core\Number;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Support\Carbon;


class SexyGameController extends Controller
{
    private $host = "https://tttint.onlinegames22.com";
    private $certCode = "N19V3PqBl1QJtAyK85e";
    private $agentId = "nasavg";
    private $currencyCode = "THB";
    private $language = "th";
    private $betLimit = '{"SEXYBCRT":{"LIVE":{"limitId":[260901,260902,260903,260904,260905]}}} ';

    public function login(Request $request, $username)
    {
        $user = User::where('username', $username)->first();
        if ($user) {
            try {
                $client = new Client();
                $res = $client->request('POST', $this->host . '/wallet/createMember', [
                    'form_params' => [
                        'cert' =>  $this->certCode,
                        'agentId' =>  $this->agentId,
                        'userId' =>  $this->username,
                        'isMobileLogin' =>  false,
                        'externalURL' =>  'https://www.google.com.tw/',
                        'gameForbidden' =>  '{"JDBFISH":{"FH":["ALL"]}}',
                        'gameType' =>  'SLOT',
                        'platform' =>  'RT',
                        'language' =>  $this->language,
                        'betLimit' =>  $this->betLimit,
                    ]
                ]);
                echo $res->getStatusCode();
                // "200"
                echo $res->getHeader('content-type')[0];
                // 'application/json; charset=utf8'
                echo $res->getBody();

                // if (jsonObject.status == '0000') {
                //     return response.redirect(`${jsonObject.url}`)
                // } else if (jsonObject.status == '1002') {
                //     $responsenewmember = await this.create_member(username);
                //     console.log(responsenewmember);
                //     if (responsenewmember.status != '0000') return responsenewmember; /// error create
                //     if (!params.recall) {
                //         params.recall = 1;
                //         await this.login({ response, params });
                //         return false;
                //     }
                // } else {
                //     return jsonObject;
                // }

            } catch (BadResponseException $e) {
                return response()->json([
                    'message' => $e->getMessage()
                ]);
            }
        } else {
            $response = ["message" => 'Oops! The user does not exist'];
            return response($response, 401);
        }
    }

    public function create_member($username)
    {
        try {
            $client = new Client();
            $res = $client->request('POST', $this->host . '/wallet/createMember', [
                'form_params' => [
                    'cert' =>  $this->certCode,
                    'agentId' =>  $this->agentId,
                    'userId' =>  $this->username,
                    'isMobileLogin' =>  false,
                    'externalURL' =>  'https://www.google.com.tw/',
                    'gameForbidden' =>  '{"JDBFISH":{"FH":["ALL"]}}',
                    'gameType' =>  'SLOT',
                    'platform' =>  'RT',
                    'language' =>  $this->language,
                    'betLimit' =>  $this->betLimit,
                ]
            ]);
            echo $res->getStatusCode();
            // "200"
            echo $res->getHeader('content-type')[0];
            // 'application/json; charset=utf8'
            echo $res->getBody();
        } catch (BadResponseException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ]);
        }
    }

    public function getBalance(Request $request)
    {
        $body = $request->body;

        $username = '';
        if ($body->action == "getBalance") {
            $username = $body->userId;
        } else {
            $username = $body->txns[0]->userId;
        }
        if (!$username) {
            return [
                "status" => "1000",
                'desc' => 'Invalid user Id',
            ];
        }

        $user = User::where('username', $username)->first();
        if (!$user) {
            return [
                "status" => "1000",
                'desc' => 'Invalid user Id',
            ];
        }

        $wallet_amount_before = (int) $user->main_wallet;
        $wallet_amount_after = (int) $user->main_wallet;


        //
        $chkDuplicatedata = false;
        $chkbetTransaction = false;
        $chkWalletAmount = false;
        $chkNoUser = false;
        $hasError = false;

        switch ($body->action) {
            case 'getBalance':
                return [
                    "status" => "0000",
                    "userId" => $username,
                    "balance" => $wallet_amount_before,
                    "balanceTs" => Carbon::now()->format('Y-m-d H:m:s')
                ];
                break;
            case 'bet':
                foreach ($body->txns as $txn) {
                    $status = false;
                    $userWallet = User::where('username', $txn['userId'])->first();
                    if (!$userWallet) $chkNoUser = true;

                    $betTransaction = $this->hasTransactionHistory('bet', $txn);
                    if (!$betTransaction) {
                    } else {
                        $chkDuplicatedata = true;
                    }
                    // return {
                    //     "balance": latestBalance,
                    //     "balanceTs": moment().toISOString(),
                    //     "status": "0000"
                    // }
                }
                break;
            case 'cancelBet':
                break;
            case 'voidBet':
                break;
            case 'unvoidBet':
                break;
            case 'adjustBet':
                break;
            case 'settle':
                break;
            case 'unsettle':
                break;
            case 'voidSettle':
                break;
            case 'unvoidSettle':
                break;
            case 'betNSettle':
                break;
            case 'cancelBetNSettle':
                break;
            case 'freeSpin':
                break;
            case 'give':
                break;
            default:
                return [
                    'status' => '9999',
                    'desc' => 'Fail (System Error)'
                ];
                break;
        }
    }
    private function hasTransactionHistory($action, $payload)
    {
        $platformTxId = $payload->platformTxId ? $payload->platformTxId : $payload->promotionTxId;
        return  $SexyGame = SexyGame::select('platformTxId')
            ->where('action', '=', $action)
            ->where('platformTxId', '=', $platformTxId)
            ->where('platform', '=', $payload->platform)
            ->where('username', '=', $payload->userId)
            ->orderBy('id', 'desc')
            ->first();
    }

    private function  responseError()
    {
        return [
            "status" => "1000",
            'desc' => 'Invalid user Id',
        ];
    }
}
