<?php

namespace App\Classes;

use Exception;

date_default_timezone_set("Asia/Bangkok");

class SCBEasyAPI
{

    public $availableBalance = 0;
    private $apiUrl = 'https://fasteasy.scbeasy.com:8443';
    private $scbVersion = '3.66.2/6960';
    private $deviceId = '';
    private $accountNumber = '';
    private $pin = '';
    private $fileToken = '';
    private $timeout = 13;


    public function setAccount($deviceId, $pin, $accountNumber)
    {
        $this->deviceId = $deviceId;
        $this->accountNumber = $accountNumber;
        $this->pin = $pin;
        $this->fileToken = "/var/www/seamless/public/scb-access-token-" . $accountNumber . ".txt";
    }

    private function Curl($method, $url, $header, $data, $cookie = false)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, "Android/11;FastEasy/3.66.2/6960");
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        // curl_setopt($ch, CURLOPT_PROXY, $this->proxys);
        // curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->pass);

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        if ($cookie) {
            curl_setopt($ch, CURLOPT_COOKIESESSION, true);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
        }

        return curl_exec($ch);
    }

    private function CurlForAuth($method, $url, $header, $data = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, "Android/11;FastEasy/3.66.2/6960");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0); //waiting response timeout in seconds
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); //waiting connection timeout in seconds
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // curl_setopt($ch, CURLOPT_PROXY, $this->proxys);
        // curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->pass);

        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            // Log::error("SCB - AUTH : ".$error_msg);
            echo $error_msg;
            exit;
        }

        curl_close($ch);
        return $response;
    }

    public function login()
    {
        if (!$this->isLoggedIn()) {

            $url = 'https://fasteasy.scbeasy.com:8443/v3/login/preloadandresumecheck';
            $headers = array(
                'Accept-Language: th',
                'scb-channel: APP',
                'User-Agent: Android/11;FastEasy/' . $this->scbVersion,
                'Content-Type: application/json; charset=UTF-8',
                'Connection: close'
            );
            $data = json_encode(['deviceId'  => $this->deviceId, 'jailbreak' => 0, 'tilesVersion'  => 39, 'userMode'  => 'INDIVIDUAL']);
            $res = $this->CurlForAuth('POST', $url, $headers, $data);
            preg_match_all('/(?<=Api-Auth: ).+/', $res, $Auth);
            if (!isset($Auth[0][0])) {
                throw new Exception('ไม่สามารถเข้าใช้งานได้');
            }
            $Auth = $Auth[0][0];
            if ($Auth == "") {
                return false;
            }

            $url = 'https://fasteasy.scbeasy.com/isprint/soap/preAuth';
            $headers = array(
                'Api-Auth: ' . $Auth,
                'Content-Type: application/json'
            );
            $data = json_encode(["loginModuleId" => "PseudoFE"]);
            $res = $this->Curl('POST', $url, $headers, $data);
            $data = json_decode($res, true);
            // print_r($data);

            $hashType = $data['e2ee']['pseudoOaepHashAlgo'];
            $Sid = $data['e2ee']['pseudoSid'];
            $ServerRandom = $data['e2ee']['pseudoRandom'];
            $pubKey = $data['e2ee']['pseudoPubKey'];

            // ------------------- encrypt pin ------------------------------
            $url = 'http://localhost:7777/pin/encrypt';
            $headers = array(
                "Content-Type: application/x-www-form-urlencoded"
            );
            $data = "Sid=" . $Sid . "&ServerRandom=" . $ServerRandom . "&pubKey=" . $pubKey . "&pin=" . $this->pin . "&hashType=" . $hashType;
            $data = http_build_query([
                'Sid'       => $Sid,
                'ServerRandom' => $ServerRandom,
                'pubKey'    => $pubKey,
                'pin'       => $this->pin,
                'hashType'  => $hashType
            ]);
            $res = $this->Curl('POST', $url, $headers, $data);

            // -------------------- Login ---------------------------------
            $url = 'https://fasteasy.scbeasy.com/v1/fasteasy-login';
            $headers = array(
                'Api-Auth: ' . $Auth,
                'Content-Type: application/json',
                'user-agent: Android/11;FastEasy/3.64.1/5742',
            );
            $data = json_encode([
                'deviceId'  => $this->deviceId,
                'pseudoPin' => $res,
                'pseudoSid' => $Sid
            ]);
            $res = $this->CurlForAuth('POST', $url, $headers, $data);
            preg_match_all('/(?<=Api-Auth:).+/', $res, $Auth_result);
            // print_r($res);
            $Auth1 = $Auth_result[0][0];
            if ($Auth1 == "") {
                return false;
            }

            $accessToken = trim($Auth1);
            file_put_contents($this->fileToken, $accessToken);

            return true;
        }

        return true;
    }

    public function isLoggedIn()
    {

        try {
            $accessToken = file_get_contents($this->fileToken);
        } catch (Exception $e) {
            $accessToken = '';
        }
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "{$this->apiUrl}/v2/deposits/casa/details");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        $headers = array();
        $headers[] = "Api-Auth: $accessToken";
        // $headers[] = 'User-Agent: Android/11;FastEasy/3.35.0/3906';
        $headers[] = 'User-Agent: Android/11;FastEasy/3.66.2/6960';
        $headers[] = 'Accept-Language: th';
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{"accountNo": "' . $this->accountNumber . '"}');

        $result = json_decode(curl_exec($ch));
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close($ch);

        if ($result->status->code == 1000) {
            $this->availableBalance = $result->availableBalance;
            return true;
        } else {
            return false;
        }
    }

    public function transactionPage($page = 1)
    {

        // $accessToken = file_get_contents("scb-access-token.txt");
        try {
            $accessToken = file_get_contents($this->fileToken);
        } catch (Exception $e) {
            $accessToken = '';
        }
        $today = date("Y-m-d", time());
        // $yesterday = date("Y-m-d", time() - 60 * 60 * 24);
        $yesterday  = date("Y-m-d", strtotime(date("Y-m-d") . "-2 days"));
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "{$this->apiUrl}/v2/deposits/casa/transactions");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        $headers = array();
        $headers[] = "Api-Auth: $accessToken";
        $headers[] = 'User-Agent: Android/11;FastEasy/3.66.2/6960';
        $headers[] = 'Accept-Language: th';
        $headers[] = 'Content-Type: application/json; charset=UTF-8';
        // $headers[] = 'Content-Type: application/json';
        $headers[] = 'scb-channel: APP';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{"accountNo": "' . $this->accountNumber . '","endDate": "' . $today . '","pageNumber": "' . $page . '","pageSize": 50,"productType": "2","startDate": "' . $yesterday . '"}');

        $result = json_decode(curl_exec($ch));
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close($ch);
        return $result;
    }

    public function transactions()
    {
        $transactions = [];
        $i = 0;
        $limitTime = 3;  // 150 trxs.
        $page = 1;
        $minutes = 60;
        while ($page != null || $page != '') {
            if ($i == $limitTime) {
                break;
            }
            try {
                $result = $this->transactionPage($page);
                if ($result->status->code != 1000) {
                    return $result;
                }
                $page = $result->data->nextPageNumber;
                foreach ($result->data->txnList as $transaction) {
                    $dateTime = date_create(date('Y-m-d H:i:s', strtotime($transaction->txnDateTime)));
                    $dateTimeNow = date_create(date('Y-m-d H:i:s'));

                    if ($dateTime->diff($dateTimeNow)->format('%h') > 6) {
                        $dateTime = date_create(date("Y-m-d H:i:s", strtotime($transaction->txnDateTime . "-1 days")))->format('Y-m-d H:i:s');
                        // $dateTime = date('Y-m-d') . ' ' . $dateTime->format('H:i:s');
                    } else {
                        $dateTime = date('Y-m-d H:i:s', strtotime($transaction->txnDateTime));
                    }

                    // $dateTimeCheck = new DateTime($dateTime);
                    // $dateTime = date_create(date("Y-m-d H:i:s", strtotime($dateTime . "-" . $minutes . " minutes")));
                    $dateTimeWithoutSecond = date('Y-m-d H:i:00', strtotime($dateTime));
                    $transactions[] = [
                        'dateTime' => $dateTime,
                        'amount' => $transaction->txnAmount,
                        'txRemark' => $transaction->txnRemark,
                        'txHash' => md5("[$transaction->txnAmount] [$dateTimeWithoutSecond] [$transaction->txnRemark]"),
                        'remark' => $this->remarkDescription($transaction->txnRemark),
                        'channel' => $transaction->txnChannel,
                        'type' => $transaction->txnCode,
                        'nextPageNumber' => $page
                    ];
                }
            } catch (\Exception $exception) {
                // Do nothing.
            }
            $i++;
        }

        $dt = array();
        $dt = array_column($transactions, 'dateTime');
        array_multisort($dt, SORT_DESC, $transactions);

        return ['availableBalance' => $this->availableBalance, 'transactions' => $transactions];
    }

    public function verifyAccount($bank, $accountNumber)
    {
        // $accessToken = file_get_contents("scb-access-token.txt");
        try {
            $accessToken = file_get_contents($this->fileToken);
        } catch (Exception $e) {
            $accessToken = '';
        }
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://fasteasy.scbeasy.com:8443/v2/transfer/verification');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        if (is_numeric($bank)) {
            $bankCode = $bank;
        } else {
            $bankCode = $this->bankCode($bank);
        }
        $transferType = ($bank == 'SCB') ? '3RD' : (($bank == '014') ? '3RD' : 'ORFT');
        $headers = array();
        $headers[] = "Api-Auth: $accessToken";
        $headers[] = 'User-Agent: Android/10;FastEasy/3.66.2/6960';
        $headers[] = 'Accept-Language: th';
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{"accountFrom": "' . $this->accountNumber . '","accountFromType": "2","accountTo": "' . $accountNumber . '","accountToBankCode": "' . $bankCode . '","amount": "1","annotation": null,"transferType": "' . $transferType . '"}');

        $result = json_decode(curl_exec($ch));

        if ($result->status->code != 1000 && $result->status->code != 9003) {
            curl_close($ch);
            return ['code' => 404, 'desc' => 'ไม่พบบัญชี'];
        }

        $accountToName = $result->data->accountToName;
        $transferType = $result->data->transferType;
        $accountToName = str_replace('  ', ' ', $accountToName);

        $arrName = explode(' ', $accountToName);
        $prefix = '';
        $firstName = '';
        $lastName = '';
        if (sizeof($arrName) < 3) {
            $firstName = $this->getName($accountToName);
            $lastName = $arrName[1];
        } else {
            $prefix = $arrName[0];
            $firstName = $arrName[1];
            $lastName = $arrName[2];
        }

        curl_close($ch);
        return ['fullName' => $accountToName, 'prefix' => $prefix, 'firstName' => $firstName, 'lastName' => $lastName];
    }

    public function transfer($bank, $transferTo, $amount)
    {

        try {
            $accessToken = file_get_contents($this->fileToken);
        } catch (Exception $e) {
            $accessToken = '';
        }
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://fasteasy.scbeasy.com:8443/v2/transfer/verification');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        $bankCode = $this->bankCode($bank);
        $transferType = $bank == 'SCB' ? '3RD' : 'ORFT';
        $headers = array();
        $headers[] = "Api-Auth: $accessToken";
        $headers[] = 'User-Agent: Android/11;FastEasy/3.66.2/6960';
        $headers[] = 'Accept-Language: th';
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{"accountFrom": "' . $this->accountNumber . '","accountFromType": "2","accountTo": "' . $transferTo . '","accountToBankCode": "' . $bankCode . '","amount": "' . $amount . '","annotation": null,"transferType": "' . $transferType . '"}');

        $result = json_decode(curl_exec($ch));
        if ($result->status->code != 1000 && $result->status->code != 9003) {
            return $result;
        }

        $accountFromName = $result->data->accountFromName;
        $accountTo = $result->data->accountTo;
        $accountToBankCode = $result->data->accountToBankCode;
        $accountToName = $result->data->accountToName;
        $transactionToken = $result->data->transactionToken;
        $terminalNo = $result->data->terminalNo;
        $sequence = $result->data->sequence;
        $transferType = $result->data->transferType;
        $pccTraceNo = $result->data->pccTraceNo;
        $feeType = $result->data->feeType;

        $confirmData = [
            'accountFromName' => $accountFromName,
            'accountFromType' => '2',
            'accountTo' => $accountTo,
            'accountToBankCode' => $accountToBankCode,
            'accountToName' => $accountToName,
            'amount' => $amount,
            'botFee' => 0.0,
            'channelFee' => 0.0,
            'fee' => 0.0,
            'feeType' => $feeType,
            'pccTraceNo' => $pccTraceNo,
            'scbFee' => 0.0,
            'sequence' => $sequence,
            'terminalNo' => $terminalNo,
            'transactionToken' => $transactionToken,
            'transferType' => $transferType
        ];

        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }

        curl_setopt($ch, CURLOPT_URL, 'https://fasteasy.scbeasy.com:8443/v3/transfer/confirmation');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($confirmData));

        $result = json_decode(curl_exec($ch));

        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close($ch);

        return $result->data;
    }

    public function transferOld($bank, $transferTo, $amount, $fname, $lname)
    {
        // $accessToken = file_get_contents("scb-access-token.txt");
        try {
            $accessToken = file_get_contents($this->fileToken);
        } catch (Exception $e) {
            $accessToken = '';
        }
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://fasteasy.scbeasy.com:8443/v2/transfer/verification');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        if (is_numeric($bank)) {
            $bankCode = $bank;
        } else {
            $bankCode = $this->bankCode($bank);
        }
        $transferType = ($bank == 'SCB') ? '3RD' : (($bank == '014') ? '3RD' : 'ORFT');
        $headers = array();
        $headers[] = "Api-Auth: $accessToken";
        $headers[] = 'User-Agent: Android/10;FastEasy/3.66.2/6960';
        $headers[] = 'Accept-Language: th';
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{"accountFrom": "' . $this->accountNumber . '","accountFromType": "2","accountTo": "' . $transferTo . '","accountToBankCode": "' . $bankCode . '","amount": "' . $amount . '","annotation": null,"transferType": "' . $transferType . '"}');

        $result = json_decode(curl_exec($ch));

        if ($result->status->code != 1000 && $result->status->code != 9003) {
            return $result;
        }

        $accountFromName = $result->data->accountFromName;
        $accountTo = $result->data->accountTo;
        $accountToBankCode = $result->data->accountToBankCode;
        $accountToName = $result->data->accountToName;
        $transactionToken = $result->data->transactionToken;
        $terminalNo = $result->data->terminalNo;
        $sequence = $result->data->sequence;
        $transferType = $result->data->transferType;
        $pccTraceNo = $result->data->pccTraceNo;
        $feeType = $result->data->feeType;

        // $firstName = explode(' ', $accountToName)[1];
        // $lastName = explode(' ', $accountToName)[2];

        if (strpos($accountToName, $fname) === false || strpos($accountToName, $lname) === false) {
            // if ($fname !== $firstName || $lname !== $lastName) {
            $arr['status']['code'] = 404;
            $arr['status']['sysName'] = $fname . ' ' . $lname;
            $arr['status']['scbName'] = $accountToName;
            $arr['status']['description'] = 'ชื่อบัญชีปลายทางไม่ตรงกับระบบ';
            return $arr;
            exit();
        }

        $confirmData = [
            'accountFromName' => $accountFromName,
            'accountFromType' => '2',
            'accountTo' => $accountTo,
            'accountToBankCode' => $accountToBankCode,
            'accountToName' => $accountToName,
            'amount' => '1.00',
            'botFee' => 0.0,
            'channelFee' => 0.0,
            'fee' => 0.0,
            'feeType' => $feeType,
            'pccTraceNo' => $pccTraceNo,
            'scbFee' => 0.0,
            'sequence' => $sequence,
            'terminalNo' => $terminalNo,
            'transactionToken' => $transactionToken,
            'transferType' => $transferType
        ];

        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }

        curl_setopt($ch, CURLOPT_URL, 'https://fasteasy.scbeasy.com:8443/v3/transfer/confirmation');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($confirmData));

        $result = json_decode(curl_exec($ch));

        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close($ch);

        return $result;
    }

    private function bankCode($bank)
    {
        $bankCode = [
            'SCB' => '014',
            'BBL' => '002',
            'KK' => '069',
            'KBANK' => '004',
            'KTB' => '006',
            'BAAC' => '034', // ธกส.
            'TMB' => '011',
            'TTB' => '011',
            'ICBC' => '070',
            'BAY' => '025',
            'CIMBT' => '022',
            'CIMB' => '022',
            'TBANK' => '065',
            'GSB' => '030',
            'LH' => '073',
            'LHBA' => '073',
            'UOBT' => '024',
            'GHB' => '033'
        ];
        return $bankCode[$bank];
    }

    private function remarkDescription($text)
    {
        if (preg_match('/\((.*?)\) \/X(.*)/', $text)) {
            preg_match('/\((.*?)\) \/X(.*)/', $text, $temp);
            return [
                'bank' => $temp[1],
                'number' => str_replace(" ", "", $temp[2]),
                'name' => null
            ];
        } else if (preg_match('/ (.*?) x(.*?) (.*)/', $text)) {
            preg_match('/ (.*?) x(.*?) (.*)/', $text, $temp);
            return [
                'bank' => $temp[1],
                'number' => str_replace(" ", "", $temp[2]),
                'name' => $temp[3]
            ];
        } else {
            return [
                'bank' => null,
                'number' => null,
                'name' => null
            ];
        }
    }

    private function getName($name)
    {
        $titles = [
            'น.ส.',
            'นางสาว',
            'นาง',
            'นาย',
            'นพ.',
            'พญ.',
            'พล.อ.',
            'พล.ท.',
            'พล.ต.',
            'พ.อ.',
            'พ.ท.',
            'พ.ต.',
            'ร.อ.',
            'ร.ท.',
            'ร.ต.',
            'จ.ส.อ.',
            'จ.ส.ท.',
            'จ.ส.ต.',
            'ส.อ.',
            'ส.ท.',
            'ส.ต.',
            'พลฯ',
            'นนร.',
            'พล.ร.อ.',
            'พล.ร.ท.',
            'พล.ร.ต.',
            'พ.จ.อ.',
            'พ.จ.ท.',
            'พ.จ.ต.',
            'จ.อ.',
            'จ.ท.',
            'จ.ต.',
            'พลฯ',
            'นนร.',
            'พล.อ.อ.',
            'พล.อ.ท.',
            'พล.อ.ต.',
            'น.อ.',
            'น.ท.',
            'น.ต.',
            'ร.อ.',
            'ร.ท.',
            'ร.ต.',
            'พ.อ.อ.',
            'พ.อ.ท.',
            'พ.อ.ต.',
            'จ.อ.',
            'จ.ท.',
            'จ.ต.',
            'พลฯ',
            'นนอ.',
            'นจอ.',
            'พล.ต.อ.',
            'พล.ต.ท.',
            'พล.ต.ต.',
            'พ.ต.อ.',
            'พ.ต.ท.',
            'พ.ต.ต.',
            'ร.ต.อ.',
            'ร.ต.ท.',
            'ร.ต.ต.',
            'ด.ต.',
            'จ.ส.ต.',
            'ส.ต.อ.',
            'ส.ต.ท.',
            'ส.ต.ต.',
        ];

        $results = [];

        $temp = str_replace($titles, '<> ', $name);
        $temp = mb_ereg_replace('/\s+/', '\s', $temp);
        $temp = array_values(array_filter(explode(' ', $temp)));

        if ($temp[0] == '<>') {
            $results['title'] = substr($name, 0, strpos($name, $temp[1]));
        } else {
            $results['title'] = '';
            array_unshift($temp, '');
        }

        $results['firstname'] = $temp[1];
        if (count($temp) == 4) {
            $results['middlename'] = $temp[2];
            $results['lastname'] = $temp[3];
        } else {
            unset($temp[0], $temp[1]);
            $results['middlename'] = '';
            $results['lastname'] = implode(' ', $temp);
        }

        if (strpos(explode(' ', $name)[0], 'นายนาย') !== false) {
            $name = explode(' ', $name)[0];
            $results['firstname'] = substr($name, 9, strlen($name) - 9);
        }

        return $results['firstname'];
    }

    private function getFullName($name)
    {
        $titles = [
            'น.ส.',
            'นางสาว',
            'นาง',
            'นาย',
            'นพ.',
            'พญ.',
            'พล.อ.',
            'พล.ท.',
            'พล.ต.',
            'พ.อ.',
            'พ.ท.',
            'พ.ต.',
            'ร.อ.',
            'ร.ท.',
            'ร.ต.',
            'จ.ส.อ.',
            'จ.ส.ท.',
            'จ.ส.ต.',
            'ส.อ.',
            'ส.ท.',
            'ส.ต.',
            'พลฯ',
            'นนร.',
            'พล.ร.อ.',
            'พล.ร.ท.',
            'พล.ร.ต.',
            'พ.จ.อ.',
            'พ.จ.ท.',
            'พ.จ.ต.',
            'จ.อ.',
            'จ.ท.',
            'จ.ต.',
            'พลฯ',
            'นนร.',
            'พล.อ.อ.',
            'พล.อ.ท.',
            'พล.อ.ต.',
            'น.อ.',
            'น.ท.',
            'น.ต.',
            'ร.อ.',
            'ร.ท.',
            'ร.ต.',
            'พ.อ.อ.',
            'พ.อ.ท.',
            'พ.อ.ต.',
            'จ.อ.',
            'จ.ท.',
            'จ.ต.',
            'พลฯ',
            'นนอ.',
            'นจอ.',
            'พล.ต.อ.',
            'พล.ต.ท.',
            'พล.ต.ต.',
            'พ.ต.อ.',
            'พ.ต.ท.',
            'พ.ต.ต.',
            'ร.ต.อ.',
            'ร.ต.ท.',
            'ร.ต.ต.',
            'ด.ต.',
            'จ.ส.ต.',
            'ส.ต.อ.',
            'ส.ต.ท.',
            'ส.ต.ต.',
        ];

        $results = [];

        $temp = str_replace($titles, '<> ', $name);
        $temp = mb_ereg_replace('/\s+/', '\s', $temp);
        $temp = array_values(array_filter(explode(' ', $temp)));

        if ($temp[0] == '<>') {
            $results['title'] = substr($name, 0, strpos($name, $temp[1]));
        } else {
            $results['title'] = '';
            array_unshift($temp, '');
        }

        $results['firstname'] = $temp[1];
        if (count($temp) == 4) {
            $results['middlename'] = $temp[2];
            $results['lastname'] = $temp[3];
        } else {
            unset($temp[0], $temp[1]);
            $results['middlename'] = '';
            $results['lastname'] = implode(' ', $temp);
        }

        if (strpos(explode(' ', $name)[0], 'นายนาย') !== false) {
            $name = explode(' ', $name)[0];
            $results['firstname'] = substr($name, 9, strlen($name) - 9);
        }

        return $results['firstname'];
    }
}
