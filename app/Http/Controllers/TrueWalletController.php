<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use Exception;
use Illuminate\Http\Request;
use App\Classes\TMNOne;

class TrueWalletController extends Controller
{
    private $secretKey;

    public function __construct()
    {
        $this->secretKey = env('BANK_SECRET_KEY', '');
    }

    public function getTransaction(Request $request)
    {
        $uuid = $request->input('uuid', null);

        if (!$this->isKeyValid($request)) {
            return ['message' => 'Your secret key is not valid'];
        }

        // 32 = Bank Support Id
        $account = $this->isAccountValid($uuid, ['allowBank' => 32]);

        if (!$account) {
            return ['message' => 'Account error'];
        }

        $TMNOne = new TMNOne();
        $TMNOne->setData($account->tmn_key, $account->account_number, $account->tmn_token, $account->tmn_id);
        $TMNOne->loginWithPin6($account->pin);

        $balance = (float)$TMNOne->getBalance();

        $transactions = $TMNOne->fetchTransactionHistory(date('Y-m-d', time() - 86400), date('Y-m-d', time() + 86400));

        $formattedTransactions = $this->formatTransactions($transactions);

        return ['availableBalance' => $balance, 'transactions' => $formattedTransactions];
    }

    private function isAccountValid($uuid, $options)
    {
        $account = Bank::where('uuid', $uuid)->first();
        if (!$account || $account->bank_support_id != $options['allowBank'] || $account->status == 0) {
            return false;
        }
        return $account;
    }

    private function isKeyValid(Request $request)
    {
        $key = $request->input('key', null);
        return ($key == $this->secretKey);
    }

    private function formatTransactions($transactions)
    {
        $formattedTransactions = [];
        // return $transactions;

        foreach ($transactions as $transaction) {

            $explodedDate = explode('/', substr($transaction['date_time'], 0, 8));
            $time = substr($transaction['date_time'], 9, 14);
            $explodedName = explode(' ', str_replace('*', "", $transaction['sub_title']));

            $transaction['uuid'] = md5(json_encode($transaction));
            $transaction['full_name'] = $explodedName[0] . ' ' . $explodedName[1];
            $transaction['first_name'] = $explodedName[0];
            $transaction['last_name'] = $explodedName[1];
            $transaction['amount'] = (float)$transaction['amount'];
            $transaction['date_time'] = date('Y-m-d H:i:s', strtotime($explodedDate[2] . '-' . $explodedDate[1] . '-' . $explodedDate[0] . ' ' . $time));
            if (isset($transaction['transaction_reference_id'])) {
                $transaction['phone_number'] = str_replace('-', '', $transaction['transaction_reference_id']);
                unset($transaction['transaction_reference_id']);
            }

            unset($transaction['logo_url']);
            unset($transaction['sub_title']);

            $formattedTransactions[] = $transaction;
        }

        return $formattedTransactions;
    }
}
