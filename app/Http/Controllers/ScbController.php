<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use Exception;
use Illuminate\Http\Request;
use App\Classes\SCBEasyAPI;

class ScbController extends Controller
{
    private $secretKey = null;

    public function __construct()
    {
        $this->secretKey = env('BANK_SECRET_KEY', '');
    }

    public function transfer(Request $request)
    {
        try {
            $uuid = isset($request->uuid) ? $request->uuid : null;
            $bankCode = isset($request->bank_code) ? $request->bank_code : null;
            $accountNumber = isset($request->account_number) ? $request->account_number : null;
            $amount = isset($request->amount) ? $request->amount : null;

            $isKeyValid = $this->isKeyValid($request);
            if ($isKeyValid) return $isKeyValid;

            $isAccountValid = $this->isAccountValid($uuid, ['allowBank' => 8 /* SCB */]);
            if (isset($isAccountValid['message'])) return $isAccountValid;

            $scb = new SCBEasyAPI();
            $scb->setAccount($isAccountValid->device_id, $isAccountValid->pin, $isAccountValid->account_number);

            if ($scb->login()) {
                return json_encode($scb->transfer($bankCode, $accountNumber, $amount));
            } else {
                return ['message' => 'Unable to login'];
            }
            return ['message' => 'Unable to process'];
        } catch (Exception $e) {
            return ['message' => $e->getMessage()];
        }
    }

    public function getTransaction(Request $request)
    {
        try {
            $uuid = isset($request->uuid) ? $request->uuid : null;
            $isKeyValid = $this->isKeyValid($request);
            if ($isKeyValid) return $isKeyValid;

            $isAccountValid = $this->isAccountValid($uuid, ['allowBank' => 8 /* SCB */]);
            if (isset($isAccountValid['message'])) return $isAccountValid;

            $scb = new SCBEasyAPI();
            $scb->setAccount($isAccountValid->device_id, $isAccountValid->pin, $isAccountValid->account_number);

            // return ['message' => 'Hello, World'];

            if ($scb->login()) {
                return json_encode($scb->transactions());
            } else {
                return ['message' => 'Unable to login'];
            }
            return ['message' => 'Unable to process'];
        } catch (Exception $e) {
            return ['message' => $e->getMessage()];
        }
    }

    public function getAccountInfo(Request $request)
    {
        try {
            $uuid = isset($request->uuid) ? $request->uuid : null;
            $bankCode = isset($request->bank_code) ? $request->bank_code : null;
            $accountNumber = isset($request->account_number) ? $request->account_number : null;

            $isKeyValid = $this->isKeyValid($request);
            if ($isKeyValid) return $isKeyValid;

            $isAccountValid = $this->isAccountValid($uuid, ['allowBank' => 8 /* SCB */]);
            if (isset($isAccountValid['message'])) return $isAccountValid;

            $scb = new SCBEasyAPI();
            $scb->setAccount($isAccountValid->device_id, $isAccountValid->pin, $isAccountValid->account_number);

            if ($scb->login()) {
                return json_encode($scb->verifyAccount($bankCode, $accountNumber));
            } else {
                return ['message' => 'Unable to login'];
            }
            return ['message' => 'Unable to process'];
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
            return ['message' => 'Suuport only SCB account'];
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
