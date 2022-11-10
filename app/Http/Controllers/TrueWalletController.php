<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use Exception;
use Illuminate\Http\Request;
use App\Classes\TMNOne;

class TrueWalletController extends Controller
{
    private $secretKey = null;

    public function __construct()
    {
        $this->secretKey = env('BANK_SECRET_KEY', '');
    }

    public function getTransaction(Request $request)
    {
        try {
            $uuid = isset($request->uuid) ? $request->uuid : null;
            $isKeyValid = $this->isKeyValid($request);
            if ($isKeyValid) return $isKeyValid;

            // 32 = Bank Support Id
            $isAccountValid = $this->isAccountValid($uuid, ['allowBank' => 32]);
            if (isset($isAccountValid['message'])) return $isAccountValid;
            $account = $isAccountValid;

            $TMNOne = new TMNOne();
            $TMNOne->setData($account->tmn_key, $account->account_number, $account->tmn_token, $account->tmn_id);
            $TMNOne->loginWithPin6($account->pin); //Login เข้าระบบ Wallet ด้วย PIN

            $balance = $TMNOne->getBalance(); //ตรวจสอบยอดเงินคงเหลือ
            $transactions = $TMNOne->fetchTransactionHistory(date('Y-m-d', time() - 86400), date('Y-m-d', time() + 86400)); //ดึงรายการเงินเข้าออก

            return ['availableBalance' => $balance, 'transactions' => $transactions];
        } catch (Exception $e) {
            return ['message' => $e->getMessage()];
        }
    }

    private function isAccountValid($uuid, $options)
    {
        $account = Bank::where('uuid', $uuid)->first();
        if (!$account) {
            return ['message' => 'Your account is not valid'];
        }
        if ($account->bank_support_id != $options['allowBank']) {
            return ['message' => 'Suuport only TrueWallet account'];
        }
        if ($account->status == 0) {
            return ['message' => 'Account is disabled'];
        }
        return $account;
    }

    private function isKeyValid(Request $request)
    {
        $key = isset($request->key) ? $request->key : null;
        $uuid = isset($request->uuid) ? $request->uuid : null;
        if ($key != $this->secretKey) {
            return ['message' => 'Your secret key is not valid'];
        }
        return false;
    }
}
