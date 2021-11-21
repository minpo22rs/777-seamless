<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Rich88Game;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

date_default_timezone_set('America/New_York');

class Rich88GameController extends Controller
{
    private $HOST = "https://betacenter.ark8899.com";
    private $PrivateKey = "DLUYRLliab7d2JWzhGRrAIOrHk5gD9ic";
    private $PFID = "acthb_NASA";

    const CONTROLLER_NAME = 'Rich88GameController';

    public static function routes()
    {
        Route::get('/rich88/dev', self::CONTROLLER_NAME . '@loginGame');
        Route::get('/rich88/dev/list', self::CONTROLLER_NAME . '@gameList');

        Route::get('/rich88/dev/rich88/balance/{account}', self::CONTROLLER_NAME . '@getBalance');
        Route::get('/rich88/dev/rich88/session_id', self::CONTROLLER_NAME . '@getBalance');

        Route::post('/rich88/dev/rich88/transfer', self::CONTROLLER_NAME . '@getBalance');
        Route::post('/rich88/dev/rich88/award_activity', self::CONTROLLER_NAME . '@getBalance');


        // 4-3：https://https://nasavg.com/apiseamless/rich88/dev/rich88/session_id
        // 4-4：https://https://nasavg.com/apiseamless/rich88/dev/rich88/balance/{account}
        // 4-5：https://https://nasavg.com/apiseamless/rich88/dev/rich88/transfer
        // 4-6：https://https://nasavg.com/apiseamless/rich88/dev/rich88/award_activity
    }

    public function index()
    {
        $current_timestamp = Carbon::now()->timestamp;
        return $current_timestamp;
    }

    private function encryptBody()
    {
        $current_timestamp = Carbon::now()->timestamp;
        $api_key_payload = $this->PFID . $this->PrivateKey . $current_timestamp;
        $api_key = hash('sha256', $api_key_payload);
        return [
            "api_key" => $api_key,
            "current_timestamp" => $current_timestamp,
        ];
    }

    public function loginGame()
    {
        $username = "0933197072";

        $obj_encryptBody = $this->encryptBody();
        $api_key = $obj_encryptBody["api_key"];
        $current_timestamp = $obj_encryptBody["current_timestamp"];

        $client = new Client([
            'headers' => [
                'Accept'   => 'application/json',
                'Content-Type'   => 'application/json',
                'api_key' => $api_key,
                'pf_id' => $this->PFID,
                'timestamp' => $current_timestamp,
            ]
        ]);
        $res = $client->request("POST", "{$this->HOST}/v2/platform/login", [
            // 'debug' => true,
            'json' => [
                'account' =>  $username,
            ]
        ]);
        $response = $res->getBody()->getContents();
        // $json = json_decode($response, true);
        // echo $res->getStatusCode();
        return $response;
    }

    public function gameList()
    {
        // $username = "Test88";
        $current_timestamp = Carbon::now()->timestamp;
        // $api_key_payload = "acthb_NASADLUYRLliab7d2JWzhGRrAIOrHk5gD9ic1637215815";

        $api_key_payload = $this->PFID . $this->PrivateKey . $current_timestamp;
        $api_key = hash('sha256', $api_key_payload);

        // echo "current_timestamp ==>" . $current_timestamp . "<br/>";
        // echo "api_key_payload ==>" . $api_key_payload . "<br/>";
        // echo "api_key ==>" . $api_key . "<br/>";
        // 1605671558
        // 1637215566
        $client = new Client([
            'exceptions'       => false,
            'verify'           => false,
            'headers'          => [
                'Accept'   => 'application/json',
                'Content-Type'   => 'application/json',
                'api_key' => $api_key,
                'pf_id' => $this->PFID,
                'timestamp' => $current_timestamp,
            ]
        ]);
        // $res = $client->request("POST", "{$this->HOST}/v2/platform/login", [
        $res = $client->request("GET", "https://betacenter.ark8899.com/v2/platform/gamelist?active_only=true", [
            'form_params' => []
        ]);
        $response = $res->getBody()->getContents();
        // $json = json_decode($response, true);
        return $response;
    }


    public function getBalance(Request $request)
    {
        Log::debug("getBalance");
        return [
            "code" => 0,
            "msg" => "Success",
            "data" => [
                "balance" => 100000
            ]
        ];
    }
}
