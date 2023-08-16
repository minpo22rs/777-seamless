<?php

namespace App\Http\Controllers;

use App\Classes\Payment;
use App\Models\SBO;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

class SBOController extends Controller
{
    const CONTROLLER_NAME = 'SBOController';

    const API_URL = 'https://ex-api-yy.xxttgg.com';
    const COMPANY_KEY = 'B81A5BE4485E4FCAB6289A98B2EB511A';

    const NO_ERROR = 0;
    const KEY_FAILED = 4;
    const INTERNAL_FAILED = 7;
    const ACCOUNT_NOT_EXISTS = 1;
    const NOT_ENOUGHT_BALANCE = 5;
    const BET_NOT_EXIST = 6;
    const ALREADY_ROOLBACK = 2003;
    const PREFIX = '777B';

    protected function buildFailedValidationResponse(Request $request, $errors)
    {
        return response()->json(['ErrorCode' => self::INTERNAL_FAILED, 'ErrorMessage' => 'Internal Error']);
    }

    public static function routes()
    {
        Route::get('/sbobet/launch/{token}/{mode}', self::CONTROLLER_NAME . '@launch');
        Route::get('/sbobet/create-agent', self::CONTROLLER_NAME . '@createAgent');
        Route::get('/sbobet/update-bet-limit/{username}/{max}/{maxPerMatch}', self::CONTROLLER_NAME . '@updateBetlimit');
        Route::get('/sbobet/update-agent-bet-limit', self::CONTROLLER_NAME . '@updateAgentBetlimit');
        Route::get('/sbobet/get-player-bet-setting/{username}', self::CONTROLLER_NAME . '@getPlayerBetSetting');
        Route::post('/sbobet/GetBalance', self::CONTROLLER_NAME . '@getBalance');
        Route::post('/sbobet/Deduct', self::CONTROLLER_NAME . '@deduct');
        Route::post('/sbobet/Settle', self::CONTROLLER_NAME . '@settle');
        Route::post('/sbobet/Rollback', self::CONTROLLER_NAME . '@rollback');
        Route::post('/sbobet/Cancel', self::CONTROLLER_NAME . '@cancel');
        Route::post('/sbobet/Tip', self::CONTROLLER_NAME . '@tip');
        Route::post('/sbobet/Bonus', self::CONTROLLER_NAME . '@bonus');
        Route::post('/sbobet/ReturnStake', self::CONTROLLER_NAME . '@returnStake');
        Route::post('/sbobet/LiveCoinTransaction', self::CONTROLLER_NAME . '@deduct');
        Route::post('/sbobet/GetBetStatus', self::CONTROLLER_NAME . '@getBetStatus');
    }

    public function createAgent()
    {
        $url = self::API_URL . '/web-root/restricted/agent/register-agent.aspx';

        $payload = [
            'CompanyKey' => self::COMPANY_KEY,
            'ServerId' => (string) time(),
            'Username' => '777bet_',
            'Password' => '12345Aa',
            'Currency' => 'THB',
            'Min' => 1,
            'Max' => 20000,
            'MaxPerMatch' => 200000,
            'CasinoTableLimit' => 3,
        ];

        $response = Http::post($url, $payload);

        return $response;
    }

    public function launch($token, $mode)
    {
        $player = User::firstWhere('token', $token);
        $mode = 'desktop';

        $username = self::PREFIX . $player->username;
        $loggedIn = $this->login($username);

        if ($loggedIn['error']['id']) {
            $registered = $this->register($username);
            if ($registered['error']['id'] == 0) {
                $loggedIn = $this->login($username);
            }
        }
        $mode = $mode == 'desktop' ? 'd' : 'm';
        $launchUrl = 'https:' . $loggedIn['url'] . '&lang=th-th&oddstyle=MY&theme=sbo&oddsmode=double&device=' . $mode;

        return redirect($launchUrl);
        // return response()->json([
        //     'code'      => 0,
        //     'message'   => 'success',
        //     'url'       => $launchUrl
        // ]);
    }

    public function login($username)
    {
        $url = self::API_URL . '/web-root/restricted/player/login.aspx';

        $payload = [
            'CompanyKey' => self::COMPANY_KEY,
            'ServerId' => (string) time(),
            'Username' => $username,
            'Portfolio' => 'SportsBook',
        ];

        $response = Http::post($url, $payload);
        return $response;
    }

    public function register($username)
    {
        $url = self::API_URL . '/web-root/restricted/player/register-player.aspx';

        $payload = [
            'CompanyKey' => self::COMPANY_KEY,
            'ServerId' => (string) time(),
            'Username' => $username,
            'Agent' => '777bet_',
        ];

        $response = Http::post($url, $payload);
        return $response;
    }

    public function getPlayerBetSetting($username)
    {
        $url = self::API_URL . '/web-root/restricted/player/get-member-bet-settings-with-sportid-and-markettype.aspx';

        $payload = [
            'CompanyKey' => self::COMPANY_KEY,
            'ServerId' => (string) time(),
            'Username' => $username,
        ];

        $response = Http::post($url, $payload);
        return $response;
    }

    public function updateAgentBetlimit()
    {
        $url = self::API_URL . '/web-root/restricted/agent/update-agent-preset-bet-settings.aspx';

        $payload = [
            'CompanyKey' => self::COMPANY_KEY,
            'ServerId' => (string) time(),
            'Username' => '777bet_',
            'Min' => 1,
            'Max' => 5000,
            'MaxPerMatch' => 20000,
            'CasinoTableLimit' => 4
        ];

        $response = Http::post($url, $payload);
        return $response;
    }


    public function updateBetlimit($username, $max, $maxPerMatch)
    {
        $url = self::API_URL . '/web-root/restricted/player/update-player-bet-settings.aspx';

        $payload = [
            'CompanyKey' => self::COMPANY_KEY,
            'ServerId' => (string) time(),
            'Username' => self::PREFIX . $username,
            'Min' => 1,
            'Max' => $max,
            'MaxPerMatch' => $maxPerMatch,
            'CasinoTableLimit' => 4
        ];

        $response = Http::post($url, $payload);
        if ($response['error']['id'] == 0) {
            return  ['message' => 'แก้ไข BET LIMIT สำเร็จ'];
        }
        return $response;
    }

    public function getBetStatus(Request $request)
    {
        $payload = $request->post();
        $this->log("getBetStatus", $payload);

        $company_key = $payload['CompanyKey'];
        if (!$this->verifyKey($company_key)) {
            return ['ErrorCode' => self::KEY_FAILED, 'ErrorMessage' => 'CompanyKey Error'];
        }

        $transfer_code = $payload['TransferCode'];
        $transaction_id = $payload['TransactionId'];

        if (preg_match('/9/', $payload['ProductType'])) {
            $trx = SBO::where('transfer_code', $transfer_code)->first();
            if ($trx) {
                $Txns3RdPartyList =  json_decode($trx->txns_3rd_party, true);
                if (isset($Txns3RdPartyList[$transaction_id])) {
                    return [
                        'TransferCode' => $transfer_code,
                        'TransactionId' => $transaction_id,
                        'Status' => $Txns3RdPartyList[$transaction_id]['status'],
                        'WinLoss' => $trx->winloss,
                        'Stake' => $trx->amount,
                        'ErrorCode' => 0,
                        'ErrorMessage' => 'No Error'
                    ];
                } else {
                    return [
                        'TransferCode' => $transfer_code,
                        'TransactionId' => $transaction_id,
                        'Status' => '',
                        'WinLoss' => 0,
                        'Stake' => 0,
                        'ErrorCode' => 6,
                        'ErrorMessage' => 'Bet not exists'
                    ];
                }
            } else {
                return [
                    'TransferCode' => $transfer_code,
                    'TransactionId' => $transaction_id,
                    'Status' => '',
                    'WinLoss' => 0,
                    'Stake' => 0,
                    'ErrorCode' => 6,
                    'ErrorMessage' => 'Bet not exists'
                ];
            }
        }

        $trx = SBO::where('transfer_code', $transfer_code)->where('transaction_id', $transaction_id)->first();
        if (!$trx) {
            return ['ErrorCode' => self::BET_NOT_EXIST, 'ErrorMessage' => 'Bet not exists'];
        }

        return [
            'ErrorCode' => self::NO_ERROR,
            'ErrorMessage' => 'No Error',
            'Status' => $trx->status,
            'TransferCode' => $transfer_code,
            'TransactionId' => $transaction_id,
            'WinLoss' => $trx->winloss,
            'Stake' => $trx->amount,
        ];
    }

    public function returnStake(Request $request)
    {
        $payload = $request->post();
        $this->log("returnStake", $payload);

        $company_key = $payload['CompanyKey'];
        if (!$this->verifyKey($company_key)) {
            return ['ErrorCode' => self::KEY_FAILED, 'ErrorMessage' => 'CompanyKey Error'];
        }

        $stake = $payload['CurrentStake'];
        $username = str_replace(self::PREFIX, '', $payload['Username']);
        $transfer_code = $payload['TransferCode'];
        $product_type = $payload['ProductType'];

        $player = User::firstWhere('username', $username);
        if (!$player) {
            return ['ErrorCode' => self::ACCOUNT_NOT_EXISTS, 'ErrorMessage' => 'Member not exist'];
        }

        $trx = SBO::where('transfer_code', $transfer_code)->first();

        if (!$trx) {
            return [
                'AccountName' => self::PREFIX . $player->username,
                'Balance' => $player->main_wallet,
                'ErrorCode' => 6,
                'ErrorMessage' => 'Stake not exists',
            ];
        }

        if ($trx->is_return_stake == 0) {

            $player->increment('main_wallet', $stake);
            $trx->transfer_code = $transfer_code;
            $trx->transaction_id = $transfer_code;
            $trx->product_type = $product_type;

            $trx->return_stake = $stake;
            $trx->username = $player->username;
            $trx->is_return_stake = 1;
            $trx->save();

            return [
                'AccountName' => self::PREFIX . $player->username,
                'Balance' => $player->main_wallet,
                'ErrorCode' => 0,
                'ErrorMessage' => 'No Error',
            ];
        } else {
            if ($trx->status == 'void') {
                return [
                    'AccountName' => self::PREFIX . $player->username,
                    'Balance' => $player->main_wallet,
                    'ErrorCode' => 5003,
                    'ErrorMessage' => 'Stake Already Canceled',
                ];
            } else {
                return [
                    'AccountName' => self::PREFIX . $player->username,
                    'Balance' => $player->main_wallet,
                    'ErrorCode' => 5008,
                    'ErrorMessage' => 'Stake Already Canceled',
                ];
            }
        }
    }

    public function bonus(Request $request)
    {
        $payload = $request->post();
        $this->log("tip", $payload);

        $company_key = $payload['CompanyKey'];
        if (!$this->verifyKey($company_key)) {
            return ['ErrorCode' => self::KEY_FAILED, 'ErrorMessage' => 'CompanyKey Error'];
        }

        $amount = $payload['Amount'];
        $username = str_replace(self::PREFIX, '', $payload['Username']);
        $transfer_code = $payload['TransferCode'];
        $product_type = $payload['ProductType'];

        $player = User::firstWhere('username', $username);
        if (!$player) {
            return ['ErrorCode' => self::ACCOUNT_NOT_EXISTS, 'ErrorMessage' => 'Member not exist'];
        }

        if ($player->main_wallet < $amount) {
            return [
                'AccountName' => self::PREFIX . $player->username,
                'Balance' => $player->main_wallet,
                'ErrorCode' => 5,
                'ErrorMessage' => 'Not enough balance',
            ];
        }

        $trx = SBO::where('transfer_code', $transfer_code)->first();
        if (!$trx) {
            $player->increment('main_wallet', $amount);
            $trx = new SBO();
            $trx->transfer_code = $transfer_code;
            $trx->transaction_id = $transfer_code;
            $trx->product_type = $product_type;
            $trx->status = 'bonus';
            $trx->amount = $amount;
            $trx->username = $player->username;
            $trx->action = 'bonus';
            $trx->payload = json_encode($payload);
            $trx->save();
            return [
                'AccountName' => self::PREFIX . $player->username,
                'Balance' => $player->main_wallet,
                'ErrorCode' => 0,
                'ErrorMessage' => 'No Error'
            ];
        } else {
            return [
                'AccountName' => self::PREFIX . $player->username,
                'Balance' => $player->main_wallet,
                'ErrorCode' => 5003,
                'ErrorMessage' => 'Bonus Already Exist',
            ];
        }
    }

    public function tip(Request $request)
    {
        $payload = $request->post();
        $this->log("tip", $payload);

        $company_key = $payload['CompanyKey'];
        if (!$this->verifyKey($company_key)) {
            return ['ErrorCode' => self::KEY_FAILED, 'ErrorMessage' => 'CompanyKey Error'];
        }

        $amount = $payload['Amount'];
        $username = str_replace(self::PREFIX, '', $payload['Username']);
        $transfer_code = $payload['TransferCode'];
        $product_type = $payload['ProductType'];

        $player = User::firstWhere('username', $username);
        if (!$player) {
            return ['ErrorCode' => self::ACCOUNT_NOT_EXISTS, 'ErrorMessage' => 'Member not exist'];
        }

        if ($player->main_wallet < $amount) {
            return [
                'AccountName' => self::PREFIX . $player->username,
                'Balance' => $player->main_wallet,
                'ErrorCode' => 5,
                'ErrorMessage' => 'Not enough balance',
            ];
        }

        $trx = SBO::where('transfer_code', $transfer_code)->first();
        if (!$trx) {
            $player->decrement('main_wallet', $amount);
            $trx = new SBO();
            $trx->transfer_code = $transfer_code;
            $trx->transaction_id = $transfer_code;
            $trx->product_type = $product_type;
            $trx->status = 'tip';
            $trx->amount = $amount;
            $trx->username = $player->username;
            $trx->action = 'tip';
            $trx->payload = json_encode($payload);
            $trx->save();
            return [
                'AccountName' => self::PREFIX . $player->username,
                'Balance' => $player->main_wallet,
                'ErrorCode' => 0,
                'ErrorMessage' => 'No Error'
            ];
        } else {
            return [
                'AccountName' => self::PREFIX . $player->username,
                'Balance' => $player->main_wallet,
                'ErrorCode' => 5003,
                'ErrorMessage' => 'Tip Already Exist',
            ];
        }
    }

    public function cancel(Request $request)
    {
        $payload = $request->post();
        $this->log("cancel", $payload);
        $payload = $request->post();
        $this->log("settle", $payload);

        $company_key = $payload['CompanyKey'];
        if (!$this->verifyKey($company_key)) {
            return ['ErrorCode' => self::KEY_FAILED, 'ErrorMessage' => 'CompanyKey Error'];
        }

        $username = str_replace(self::PREFIX, '', $payload['Username']);
        $transfer_code = $payload['TransferCode'];
        $transaction_id = $payload['TransactionId'];

        $player = User::firstWhere('username', $username);
        if (!$player) {
            return ['ErrorCode' => self::ACCOUNT_NOT_EXISTS, 'ErrorMessage' => 'Member not exist'];
        }

        if (preg_match('/1|3|5|7|10/', $payload['ProductType'])) {
            $trx = SBO::where('transfer_code', $transfer_code)->first();
            if ($trx) {
                if ($trx->status == 'settled') {
                    $current_amount = $trx->amount;
                    $current_profit = $trx->profit;
                    $winloss = $current_amount + $current_profit;
                    $refund = $current_amount - $winloss;
                    $player->increment('main_wallet', $refund);
                    $trx->status = 'void';
                    $trx->save();
                    return [
                        'AccountName' => self::PREFIX . $player->username,
                        'Balance' => $player->main_wallet,
                        'ErrorCode' => 0,
                        'ErrorMessage' => 'No Error',
                    ];
                } else if ($trx->status == 'running') {
                    $refund = $trx->amount;
                    $player->increment('main_wallet', $refund);
                    $trx->status = 'void';
                    $trx->save();
                    return [
                        'AccountName' => self::PREFIX . $player->username,
                        'Balance' => $player->main_wallet,
                        'ErrorCode' => 0,
                        'ErrorMessage' => 'No Error',
                    ];
                } else if ($trx->status == 'void') {
                    return [
                        'AccountName' => self::PREFIX . $player->username,
                        'Balance' => $player->main_wallet,
                        'ErrorCode' => 2002,
                        'ErrorMessage' => 'Bet Already Canceled',
                    ];
                }
            } else {
                return ['ErrorCode' => 6, 'ErrorMessage' => 'Bet not exists', 'Balance' => 0];
            }
        } else if (preg_match('/9/', $payload['ProductType'])) {
            $trx = SBO::where('transfer_code', $transfer_code)->first();
            if ($trx) {
                $Txns3RdPartyList = json_decode($trx->txns_3rd_party, true);
                if ($payload['IsCancelAll'] == false) {
                    if (isset($Txns3RdPartyList[$transaction_id])) {
                        $current_TxnsAmount = $Txns3RdPartyList[$transaction_id]['amount'];
                        $current_TxnsStatus = $Txns3RdPartyList[$transaction_id]['status'];
                        if ($current_TxnsStatus == 'running') {

                            $current_Amount = $trx->amount;
                            $Txns3RdPartyList[$transaction_id]['status'] = 'void';

                            $Txns3RdParty = json_encode($Txns3RdPartyList);

                            $check_Txns3RdPartyListStatus = [];
                            foreach ($Txns3RdPartyList as $tx) {
                                $status = $tx['status'];
                                array_push($check_Txns3RdPartyListStatus, $status);
                            }

                            if (count(array_unique($check_Txns3RdPartyListStatus)) == 1 && $check_Txns3RdPartyListStatus[0] != 'running') {
                                $status = 'void';
                            } else {
                                $status = 'running';
                            }

                            $new_Amount = $current_Amount - $current_TxnsAmount;
                            $new_ProfitAmount = $new_Amount * -1;

                            $player->increment('main_wallet', $current_TxnsAmount);
                            $trx->status = $status;
                            $trx->amount = $new_Amount;
                            $trx->profit = $new_ProfitAmount;
                            $trx->txns_3rd_party = $Txns3RdParty;
                            $trx->save();

                            return [
                                'AccountName' => self::PREFIX . $player->username,
                                'Balance' => $player->main_wallet,
                                'ErrorCode' => 0,
                                'ErrorMessage' => 'No Error',
                            ];
                        } elseif ($current_TxnsStatus == 'void') {
                            return [
                                'AccountName' => self::PREFIX . $player->username,
                                'Balance' => $player->main_wallet,
                                'ErrorCode' => 2002,
                                'ErrorMessage' => 'Bet Already Canceled',
                            ];
                        }
                    } else {
                        return [
                            'AccountName' => self::PREFIX . $player->username,
                            'Balance' => $player->main_wallet,
                            'ErrorCode' => 6,
                            'ErrorMessage' => 'Bet Not Exist',
                        ];
                    }
                } else {
                    if ($trx->status == 'settled') {

                        $current_TxAmount = 0;
                        foreach ($Txns3RdPartyList as $k => $tx) {
                            $Txns3RdPartyList[$k]['status'] = 'void';
                            $current_TxAmount = $current_TxAmount + $tx['amount'];
                        }

                        $Txns3RdParty = json_encode($Txns3RdPartyList);

                        $current_Amount = $trx->amount;
                        $current_ProfitAmount = $trx->profit;
                        $WinLoseAmount = $current_Amount + $current_ProfitAmount;
                        $RefundAmount = $current_Amount - $WinLoseAmount;

                        if ($trx->is_return_stake == 1) {
                            $RefundAmount = $RefundAmount - $trx->return_stake;
                        }

                        $new_Amount = $current_Amount - $current_TxAmount;
                        $new_ProfitAmount = $new_Amount * -1;

                        $player->increment('main_wallet', $RefundAmount);
                        $trx->status = 'void';
                        $trx->amount = $new_Amount;
                        $trx->profit = $new_ProfitAmount;
                        $trx->txns_3rd_party = $Txns3RdParty;
                        $trx->save();

                        return [
                            'AccountName' => self::PREFIX . $player->username,
                            'Balance' => $player->main_wallet,
                            'ErrorCode' => 0,
                            'ErrorMessage' => 'No Error',
                        ];
                    } else if ($trx->status == 'void') {
                        return [
                            'AccountName' => self::PREFIX . $player->username,
                            'Balance' => $player->main_wallet,
                            'ErrorCode' => 0,
                            'ErrorMessage' => 'No Error',
                        ];
                    }
                }
            } else {
                $response = [
                    'AccountName' => self::PREFIX . $player->username,
                    'Balance' => $player->main_wallet,
                    'ErrorCode' => 6,
                    'ErrorMessage' => 'Bet Not Exist',
                ];
                return $response;
            }
        }
    }

    public function rollback(Request $request)
    {
        $payload = $request->post();
        $this->log("rollback", $payload);
        $payload = $request->post();
        $this->log("settle", $payload);

        $company_key = $payload['CompanyKey'];
        if (!$this->verifyKey($company_key)) {
            return ['ErrorCode' => self::KEY_FAILED, 'ErrorMessage' => 'CompanyKey Error'];
        }

        $username = str_replace(self::PREFIX, '', $payload['Username']);
        $transfer_code = $payload['TransferCode'];

        $player = User::firstWhere('username', $username);
        if (!$player) {
            return ['ErrorCode' => self::ACCOUNT_NOT_EXISTS, 'ErrorMessage' => 'Member not exist'];
        }

        if (preg_match('/1|3|5|7|10/', $payload['ProductType'])) {
            $trx = SBO::where('transfer_code', $transfer_code)->first();
            if ($trx) {
                if ($trx->status == 'void') {

                    $current_amount = $trx->amount;
                    $current_winloss = $trx->winloss;
                    $current_profit = $trx->profit;
                    $new_winloss = $current_winloss;
                    $new_profit = $current_profit - $current_winloss;

                    $player->decrement('main_wallet', $current_amount);

                    $trx->winloss = $new_winloss;
                    $trx->profit = $new_profit;
                    $trx->status = 'running';
                    $trx->save();

                    return [
                        'AccountName' => self::PREFIX . $player->username,
                        'Balance' => $player->main_wallet,
                        'ErrorCode' => 0,
                        'ErrorMessage' => 'No Error',
                    ];
                } else if ($trx->status == 'settled') {

                    $current_amount = $trx->amount;
                    $current_winloss = $trx->winloss;
                    $current_profit = $trx->profit;
                    $new_winloss = $current_winloss;
                    $new_profit = $current_profit - $current_winloss;

                    $player->decrement('main_wallet', $current_amount + $current_profit);

                    $trx->winloss = $new_winloss;
                    $trx->profit = $new_profit;
                    $trx->status = 'running';
                    $trx->save();

                    return [
                        'AccountName' => self::PREFIX . $player->username,
                        'Balance' => $player->main_wallet,
                        'ErrorCode' => 0,
                        'ErrorMessage' => 'No Error',
                    ];
                } else if ($trx->status == 'running') {
                    return [
                        'AccountName' => self::PREFIX . $player->username,
                        'Balance' => $player->main_wallet,
                        'ErrorCode' => 2003,
                        'ErrorMessage' => 'Bet Already Rollback',
                    ];
                }
            } else {
                return ['ErrorCode' => 6, 'ErrorMessage' => 'Bet not exists', 'Balance' => 0];
            }
        } else if (preg_match('/9/', $payload['ProductType'])) {
            $trx = SBO::where('transfer_code', $transfer_code)->first();
            if ($trx) {
                if ($trx->status == 'void') {
                    $Txns3RdPartyList = json_decode($trx->txns_3rd_party, true);

                    $new_Amount = 0;
                    foreach ($Txns3RdPartyList as $k => $tx) {
                        $new_Amount = $new_Amount + $tx['amount'];
                        $Txns3RdPartyList[$k]['status'] = 'running';
                    }

                    $Txns3RdParty = json_encode($Txns3RdPartyList);

                    $new_ProfitAmount = $new_Amount * -1;

                    $player->decrement('main_wallet', $new_Amount);

                    $trx->profit = $new_ProfitAmount;
                    $trx->status = 'running';
                    $trx->txns_3rd_party = $Txns3RdParty;
                    $trx->save();

                    return [
                        'AccountName' => self::PREFIX . $player->username,
                        'Balance' => $player->main_wallet,
                        'ErrorCode' => 0,
                        'ErrorMessage' => 'No Error',
                    ];
                } else if ($trx->status == 'running') {
                    return [
                        'AccountName' => self::PREFIX . $player->username,
                        'Balance' => $player->main_wallet,
                        'ErrorCode' => 2003,
                        'ErrorMessage' => 'Bet Already Rollback',
                    ];
                }
            } else {
                return [
                    'AccountName' => self::PREFIX . $player->username,
                    'Balance' => $player->main_wallet,
                    'ErrorCode' => 6,
                    'ErrorMessage' => 'Bet Not Exist',
                ];
            }
        }
    }

    public function settle(Request $request)
    {
        $payload = $request->post();
        $this->log("settle", $payload);

        $company_key = $payload['CompanyKey'];
        if (!$this->verifyKey($company_key)) {
            return ['ErrorCode' => self::KEY_FAILED, 'ErrorMessage' => 'CompanyKey Error'];
        }

        $username = str_replace(self::PREFIX, '', $payload['Username']);
        $winloss = $payload['WinLoss'];
        $transfer_code = $payload['TransferCode'];

        $player = User::firstWhere('username', $username);
        $before_balance = $player->main_wallet;
        if (!$player) {
            return ['ErrorCode' => self::ACCOUNT_NOT_EXISTS, 'ErrorMessage' => 'Member not exist'];
        }

        if (preg_match('/1|3|5|7|10/', $payload['ProductType'])) {
            $trx = SBO::where('transfer_code', $transfer_code)->first();
            if ($trx) {
                if ($trx->status == 'running') {
                    $current_profit = $trx->profit;
                    $profit = $current_profit + $winloss;
                    $player->increment('main_wallet', $winloss);
                    $trx->profit = $profit;
                    $trx->status = 'settled';
                    $trx->save();

                    Payment::updatePlayerWinLossReport([
                        'report_type' => 'Hourly',
                        'player_id' => $player->id,
                        'partner_id' => $player->partner_id,
                        'provider_id' => 1,
                        'provider_name' => 'SBO',
                        'game_id' => 'SBO',
                        'game_name' => 'Unknown',
                        'game_type' => 'Sport',
                        'win' => $winloss,
                    ]);

                    (new Payment())->saveLog([
                        'amount' => $winloss,
                        'before_balance' => $before_balance,
                        'after_balance' => $before_balance + $winloss,
                        'tranref'   => $player->tranref,
                        'action' => 'SETTLE',
                        'provider' => 'SBO',
                        'game_type' => 'FOOTBALL',
                        'game_ref' => 'NO REFERENCE',
                        'transaction_ref' => $transfer_code,
                        'player_username' => $player->username,
                    ]);

                    return [
                        'AccountName' => self::PREFIX . $player->username,
                        'Balance' => $player->main_wallet,
                        'ErrorCode' => 0,
                        'ErrorMessage' => 'No Error',
                    ];
                } else if ($trx->status == 'settled') {
                    return [
                        'AccountName' => self::PREFIX . $player->username,
                        'Balance' => $player->main_wallet,
                        'ErrorCode' => 2001,
                        'ErrorMessage' => 'Bet Already Settled'
                    ];
                } else if ($trx->status == 'void') {
                    if ($payload['ProductType'] == '3' || $payload['ProductType'] == '5') {
                        return [
                            'AccountName' => self::PREFIX . $player->username,
                            'Balance' => $player->main_wallet,
                            'ErrorCode' => 2002,
                            'ErrorMessage' => 'Bet Already Canceled',
                        ];
                    } else {
                        return [
                            'AccountName' => self::PREFIX . $player->username,
                            'Balance' => $player->main_wallet,
                            'ErrorCode' => 2002,
                            'ErrorMessage' => 'Bet Already Canceled',
                        ];
                    }
                }
            } else {
                return ['ErrorCode' => 6, 'ErrorMessage' => 'Bet not exists', 'Balance' => 0];
            }
        } else {
            $trx = SBO::where('transfer_code', $transfer_code)->first();
            if ($trx) {
                if ($trx->status == 'running') {
                    $Txns3RdPartyList = json_decode($trx->txns_3rd_party, true);

                    foreach ($Txns3RdPartyList as $k => $tx) {
                        $Txns3RdPartyList[$k]['status'] = 'settled';
                    }

                    $Txns3RdParty = json_encode($Txns3RdPartyList);

                    $WinLoss = $payload['WinLoss'];
                    $current_ProfitAmount = $trx->profit;
                    $ProfitAmount = $current_ProfitAmount + $WinLoss;

                    $player->increment('main_wallet', $WinLoss);
                    $trx->profit = $ProfitAmount;
                    $trx->txns_3rd_party = $Txns3RdParty;
                    $trx->status = 'settled';
                    $trx->save();

                    Payment::updatePlayerWinLossReport([
                        'report_type' => 'Hourly',
                        'player_id' => $player->id,
                        'partner_id' => $player->partner_id,
                        'provider_id' => 1,
                        'provider_name' => 'SBO',
                        'game_id' => 'SBO',
                        'game_name' => 'Unknown',
                        'game_type' => 'Sport',
                        'win' => $winloss,
                    ]);

                    (new Payment())->saveLog([
                        'amount' => $winloss,
                        'before_balance' => $before_balance,
                        'after_balance' => $before_balance + $winloss,
                        'tranref'   => $player->tranref,
                        'action' => 'SETTLE',
                        'provider' => 'SBO',
                        'game_type' => 'FOOTBALL',
                        'game_ref' => 'NO REFERENCE',
                        'transaction_ref' => $transfer_code,
                        'player_username' => $player->username,
                    ]);

                    return [
                        'AccountName' => self::PREFIX . $player->username,
                        'Balance' => $player->main_wallet,
                        'ErrorCode' => 0,
                        'ErrorMessage' => 'No Error',
                    ];
                } else if ($trx->status == 'settled') {
                    return [
                        'AccountName' => self::PREFIX . $player->username,
                        'Balance' => $player->main_wallet,
                        'ErrorCode' => 2001,
                        'ErrorMessage' => 'Bet Already Settled',
                    ];
                } else if ($trx->status == 'void') {
                    $still_running = 0;
                    $Txns3RdPartyList = json_decode($trx->txns_3rd_party, true);
                    foreach ($Txns3RdPartyList as $key => $item) {
                        if ($item['status'] == 'running') {
                            $Txns3RdPartyList[$key]['status'] = 'settled';
                            $still_running = 1;
                        }
                    }
                    if ($still_running) {
                        $Txns3RdParty = json_encode($Txns3RdPartyList);

                        $WinLoss = $payload['WinLoss'];
                        $current_ProfitAmount = $trx->profit;
                        $ProfitAmount = $current_ProfitAmount + $WinLoss;

                        $player->increment('main_wallet', $WinLoss);
                        $trx->profit = $ProfitAmount;
                        $trx->txns_3rd_party = $Txns3RdParty;
                        $trx->status = 'settled';
                        $trx->save();
                        return [
                            'AccountName' => self::PREFIX . $player->username,
                            'Balance' => $player->main_wallet,
                            'ErrorCode' => 0,
                            'ErrorMessage' => 'No Error',
                        ];
                    } else {
                        return [
                            'AccountName' => self::PREFIX . $player->username,
                            'Balance' => $player->main_wallet,
                            'ErrorCode' => 2002,
                            'ErrorMessage' => 'Bet Already Canceled',
                        ];
                    }
                }
            } else {
                return [
                    'AccountName' => self::PREFIX . $player->username,
                    'Balance' => $player->main_wallet,
                    'ErrorCode' => 6,
                    'ErrorMessage' => 'Bet Not Exist',
                ];
            }
        }
    }

    public function deduct(Request $request)
    {
        $payload = $request->post();
        $this->log("Deduct", $payload);

        $company_key = $payload['CompanyKey'];
        if (!$this->verifyKey($company_key)) {
            return ['ErrorCode' => self::KEY_FAILED, 'ErrorMessage' => 'CompanyKey Error'];
        }

        $username = str_replace(self::PREFIX, '', $payload['Username']);
        $transfer_code = $payload['TransferCode'];
        $transaction_id = $payload['TransactionId'];
        $product_type = $payload['ProductType'];
        $amount = $payload['Amount'];

        $player = User::firstWhere('username', $username);
        $before_balance = $player->main_wallet;
        if (!$player) {
            return ['ErrorCode' => self::ACCOUNT_NOT_EXISTS, 'ErrorMessage' => 'Member not exist'];
        }

        if ($player->main_wallet < $amount) {
            return [
                'AccountName' => self::PREFIX . $player->username,
                'Balance' => $player->main_wallet,
                'ErrorCode' => 5,
                'ErrorMessage' => 'Not enough balance',
                'BetAmount' => 0
            ];
        }

        if (preg_match('/1|5/', $payload['ProductType'])) {

            $trx = SBO::firstWhere('transfer_code', $transfer_code);
            if ($trx) {
                if ($trx->status == 'running') {
                    return [
                        'AccountName' => self::PREFIX . $player->username,
                        'Balance' => $player->main_wallet,
                        'ErrorCode' => 5003,
                        'ErrorMessage' => 'Bet Already Exist',
                        'BetAmount' => 0
                    ];
                } else if ($trx->status == 'settled') {
                    return [
                        'AccountName' => self::PREFIX . $player->username,
                        'Balance' => $player->main_wallet,
                        'ErrorCode' => 5003,
                        'ErrorMessage' => 'Bet Already Settled',
                        'BetAmount' => 0
                    ];
                } else if ($trx->status == 'void') {
                    return [
                        'AccountName' => self::PREFIX . $player->username,
                        'Balance' => $player->main_wallet,
                        'ErrorCode' => 5003,
                        'ErrorMessage' => 'Bet Already Canceled',
                        'BetAmount' => 0
                    ];
                }
            }

            Payment::updatePlayerWinLossReport([
                'report_type' => 'Hourly',
                'player_id' => $player->id,
                'partner_id' => $player->partner_id,
                'provider_id' => 1,
                'provider_name' => 'SBO',
                'game_id' => 'SBO',
                'game_name' => 'Unknown',
                'game_type' => 'Sport',
                'loss' => $amount,
            ]);

            (new Payment())->saveLog([
                'amount' => $amount,
                'before_balance' => $before_balance,
                'after_balance' => $before_balance - $amount,
                'tranref'   => $player->tranref,
                'action' => 'BET',
                'provider' => 'SBO',
                'game_type' => 'FOOTBALL',
                'game_ref' => 'NO REFERENCE',
                'transaction_ref' => $transfer_code . ', ' . $transaction_id,
                'player_username' => $player->username,
            ]);

            $player->decrement('main_wallet', $amount);

            $trx = new SBO();
            $trx->transfer_code = $transfer_code;
            $trx->transaction_id = $transaction_id;
            $trx->product_type = $product_type;
            $trx->status = 'running';
            $trx->amount = $amount;
            $trx->profit = $amount * -1;
            $trx->username = $player->username;
            $trx->action = 'bet';
            $trx->payload = json_encode($payload);
            $trx->save();

            return [
                'AccountName' => self::PREFIX . $player->username,
                'Balance' => $player->main_wallet,
                'ErrorCode' => 0,
                'ErrorMessage' => 'No Error',
                'BetAmount' => $amount
            ];
        } else if (preg_match('/3|7/', $payload['ProductType'])) {
            $trx = SBO::firstWhere('transfer_code', $transfer_code);
            if (!$trx) {
                $player->decrement('main_wallet', $amount);

                $trx = new SBO();
                $trx->transfer_code = $transfer_code;
                $trx->transaction_id = $transaction_id;
                $trx->product_type = $product_type;
                $trx->status = 'running';
                $trx->amount = $amount;
                $trx->profit = $amount * -1;
                $trx->username = $player->username;
                $trx->action = 'BET';
                $trx->payload = json_encode($payload);
                $trx->save();

                return [
                    'AccountName' => self::PREFIX . $player->username,
                    'Balance' => $player->main_wallet,
                    'ErrorCode' => 0,
                    'ErrorMessage' => 'No Error',
                    'BetAmount' => $amount
                ];
            } else {
                if ($trx->status == 'running') {
                    $current_amount = $trx->amount;
                    if ($amount > $current_amount) {

                        $update_amount = $amount - $current_amount;
                        $new_amount = $amount;
                        $new_profit = $amount * -1;

                        $player->decrement('main_wallet', $update_amount);
                        $trx->amount = $new_amount;
                        $trx->profit = $new_profit;
                        $trx->save();

                        $response = [
                            'AccountName' => self::PREFIX . $player->username,
                            'Balance' => $player->main_wallet,
                            'ErrorCode' => 0,
                            'ErrorMessage' => 'No Error',
                            'BetAmount' => $new_amount
                        ];
                        return $response;
                    } else {
                        return [
                            'AccountName' => self::PREFIX . $player->username,
                            'Balance' => $player->main_wallet,
                            'ErrorCode' => 7,
                            'ErrorMessage' => 'Deduct amount must be greater than 1st Deduct',
                            'BetAmount' => 0
                        ];
                    }
                } else if ($trx->status == 'settled') {
                    return [
                        'AccountName' => self::PREFIX . $player->username,
                        'Balance' => $player->main_wallet,
                        'ErrorCode' => 5003,
                        'ErrorMessage' => 'Bet Already Settled',
                        'BetAmount' => 0
                    ];
                } else if ($trx->status == 'void') {
                    return [
                        'AccountName' => self::PREFIX . $player->username,
                        'Balance' => $player->main_wallet,
                        'ErrorCode' => 5003,
                        'ErrorMessage' => 'Bet Already Canceled',
                        'BetAmount' => 0
                    ];
                }
            }
        } else if (preg_match('/9/', $payload['ProductType'])) {
            $trx = SBO::firstWhere('transfer_code', $transfer_code);
            if (!$trx) {
                $player->decrement('main_wallet', $amount);

                $TransactionId = [];
                $TransactionId[$payload['TransactionId']] = [
                    'amount' => $payload['Amount'],
                    'status' => 'running'
                ];

                $txns_3rd_party = json_encode($TransactionId);

                $trx = new SBO();
                $trx->transfer_code = $transfer_code;
                $trx->transaction_id = $transaction_id;
                $trx->product_type = $product_type;
                $trx->status = 'running';
                $trx->amount = $amount;
                $trx->profit = $amount * -1;
                $trx->username = $player->username;
                $trx->txns_3rd_party = $txns_3rd_party;
                $trx->action = 'bet';
                $trx->payload = json_encode($payload);
                $trx->save();

                return [
                    'AccountName' => self::PREFIX . $player->username,
                    'Balance' => $player->main_wallet,
                    'ErrorCode' => 0,
                    'ErrorMessage' => 'No Error',
                    'BetAmount' => $amount
                ];
            } else {
                if ($trx->status == 'running') {
                    $txns_3rd_party = json_decode($trx->txns_3rd_party, true);
                    if (!isset($txns_3rd_party[$transaction_id])) {
                        $current_amount = $trx->amount;
                        $new_amount = $current_amount + $amount;
                        $new_profit = $new_amount * -1;

                        $txns_3rd_party[$transaction_id] = [
                            'amount' => $amount,
                            'status' => 'running'
                        ];

                        $new_txns_3rd_party = json_encode($txns_3rd_party);

                        $player->decrement('main_wallet', $amount);
                        $trx->amount = $new_amount;
                        $trx->txns_3rd_party = $new_txns_3rd_party;
                        $trx->profit = $new_profit;
                        $trx->save();

                        $response = [
                            'AccountName' => self::PREFIX . $player->username,
                            'Balance' => $player->main_wallet,
                            'ErrorCode' => 0,
                            'ErrorMessage' => 'No Error',
                            'BetAmount' => $new_amount
                        ];
                        return $response;
                    } else {
                        return [
                            'AccountName' => self::PREFIX . $player->username,
                            'Balance' => $player->main_wallet,
                            'ErrorCode' => 5003,
                            'ErrorMessage' => 'Bet Already Exist',
                            'BetAmount' => 0
                        ];
                    }
                } else if ($trx->status == 'settled') {
                    return [
                        'AccountName' => self::PREFIX . $player->username,
                        'Balance' => $player->main_wallet,
                        'ErrorCode' => 2001,
                        'ErrorMessage' => 'Bet Already Settled',
                        'BetAmount' => 0
                    ];
                } else if ($trx->status == 'void') {
                    $txns_3rd_party = json_decode($trx->txns_3rd_party, true);
                    if (!isset($txns_3rd_party[$transaction_id])) {
                        $current_amount = $trx->amount;
                        $new_amount = $current_amount + $amount;
                        $new_profit = $new_amount * -1;

                        $txns_3rd_party[$transaction_id] = [
                            'amount' => $amount,
                            'status' => 'running'
                        ];

                        $new_txns_3rd_party = json_encode($txns_3rd_party);

                        $player->decrement('main_wallet', $amount);
                        $trx->amount = $new_amount;
                        $trx->txns_3rd_party = $new_txns_3rd_party;
                        $trx->profit = $new_profit;
                        $trx->save();

                        $response = [
                            'AccountName' => self::PREFIX . $player->username,
                            'Balance' => $player->main_wallet,
                            'ErrorCode' => 0,
                            'ErrorMessage' => 'No Error',
                            'BetAmount' => $new_amount
                        ];
                        return $response;
                    } else {
                        return [
                            'AccountName' => self::PREFIX . $player->username,
                            'Balance' => $player->main_wallet,
                            'ErrorCode' => 2002,
                            'ErrorMessage' => 'Bet Already Canceled',
                            'BetAmount' => 0
                        ];
                    }
                }
            }
        } else if (preg_match('/10/', $payload['ProductType'])) {
            $trx = SBO::firstWhere('transfer_code', $transfer_code);
            if (!$trx) {
                $player->decrement('main_wallet', $amount);

                $TransactionId = [];
                $TransactionId[$payload['TransactionId']] = [
                    'amount' => $payload['Amount'],
                    'status' => 'running'
                ];

                $txns_3rd_party = json_encode($TransactionId);

                $trx = new SBO();
                $trx->transfer_code = $transfer_code;
                $trx->transaction_id = $transaction_id;
                $trx->product_type = $product_type;
                $trx->status = 'running';
                $trx->amount = $amount;
                $trx->profit = $amount * -1;
                $trx->username = $player->username;
                $trx->txns_3rd_party = $txns_3rd_party;
                $trx->action = 'bet';
                $trx->payload = json_encode($payload);
                $trx->save();

                return [
                    'AccountName' => self::PREFIX . $player->username,
                    'Balance' => $player->main_wallet,
                    'ErrorCode' => 0,
                    'ErrorMessage' => 'No Error',
                    'BetAmount' => $amount
                ];
            } else {
                if ($trx->status == 'running') {
                    $txns_3rd_party = json_decode($trx->txns_3rd_party, true);
                    if (!isset($txns_3rd_party[$transaction_id])) {
                        $current_amount = $trx->amount;
                        $new_amount = $current_amount + $amount;
                        $new_profit = $new_amount * -1;

                        $txns_3rd_party[$transaction_id] = [
                            'amount' => $amount,
                            'status' => 'running'
                        ];

                        $new_txns_3rd_party = json_encode($txns_3rd_party);

                        $player->decrement('main_wallet', $amount);
                        $trx->amount = $new_amount;
                        $trx->txns_3rd_party = $new_txns_3rd_party;
                        $trx->profit = $new_profit;
                        $trx->save();

                        $response = [
                            'AccountName' => self::PREFIX . $player->username,
                            'Balance' => $player->main_wallet,
                            'ErrorCode' => 0,
                            'ErrorMessage' => 'No Error',
                            'BetAmount' => $new_amount
                        ];
                        return $response;
                    } else {
                        if ($txns_3rd_party[$transaction_id]) {
                            return [
                                'AccountName' => self::PREFIX . $player->username,
                                'Balance' => $player->main_wallet,
                                'ErrorCode' => 999,
                                'ErrorMessage' => 'Bet Already Exist',
                                'BetAmount' => 0
                            ];
                        } else {
                            return [
                                'AccountName' => self::PREFIX . $player->username,
                                'Balance' => $player->main_wallet,
                                'ErrorCode' => 5003,
                                'ErrorMessage' => 'Bet Already Exist',
                                'BetAmount' => 0
                            ];
                        }
                    }
                } else if ($trx->status == 'settled') {
                    return [
                        'AccountName' => self::PREFIX . $player->username,
                        'Balance' => $player->main_wallet,
                        'ErrorCode' => 2001,
                        'ErrorMessage' => 'Bet Already Settled',
                        'BetAmount' => 0
                    ];
                } else if ($trx->status == 'void') {
                    $txns_3rd_party = json_decode($trx->txns_3rd_party, true);
                    if (!isset($txns_3rd_party[$transaction_id])) {
                        $current_amount = $trx->amount;
                        $new_amount = $current_amount + $amount;
                        $new_profit = $new_amount * -1;

                        $txns_3rd_party[$transaction_id] = [
                            'amount' => $amount,
                            'status' => 'running'
                        ];

                        $new_txns_3rd_party = json_encode($txns_3rd_party);

                        $player->decrement('main_wallet', $amount);
                        $trx->amount = $new_amount;
                        $trx->txns_3rd_party = $new_txns_3rd_party;
                        $trx->profit = $new_profit;
                        $trx->save();

                        $response = [
                            'AccountName' => self::PREFIX . $player->username,
                            'Balance' => $player->main_wallet,
                            'ErrorCode' => 0,
                            'ErrorMessage' => 'No Error',
                            'BetAmount' => $new_amount
                        ];
                        return $response;
                    } else {
                        return [
                            'AccountName' => self::PREFIX . $player->username,
                            'Balance' => $player->main_wallet,
                            'ErrorCode' => 2002,
                            'ErrorMessage' => 'Bet Already Canceled',
                            'BetAmount' => 0
                        ];
                    }
                }
            }
        }
    }

    public function getBalance(Request $request)
    {
        $payload = $request->post();
        $this->log("Get Balance", $payload);

        $company_key = $payload['CompanyKey'];
        $username = str_replace(self::PREFIX, '', $payload['Username']);

        if (!$this->verifyKey($company_key)) {
            return ['ErrorCode' => self::KEY_FAILED, 'ErrorMessage' => 'CompanyKey Error', 'Balance' => 0];
        }

        if ($username == '') {
            return ['ErrorCode' => 3, 'ErrorMessage' => 'Username empty', 'Balance' => 0];
        }

        $player = User::firstWhere('username', $username);
        if (!$player) {
            return ['ErrorCode' => self::ACCOUNT_NOT_EXISTS, 'ErrorMessage' => 'Member not exist', 'Balance' => 0];
        }

        $response =  [
            'AccountName' => self::PREFIX . $player->username,
            'Balance' => $player->main_wallet,
            'ErrorCode' => self::NO_ERROR,
            'ErrorMessage' => 'No Error'
        ];
        return $response;
    }

    private function verifyKey($key)
    {
        return $key === self::COMPANY_KEY;
    }

    private function log($message, $payload)
    {
        Log::debug(self::CONTROLLER_NAME);
        Log::debug($message);
        Log::debug(json_encode($payload));
        Log::debug('-----------------------------------------------');
    }
}
