<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EvolutionGame;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EvolutionGameController extends Controller
{

    private $CasinoKey = "k0ljjn7sywnogl16";
    private $APIKey = "4adc9bcb1e4f1eae20ce73f7a7ad8291";
    private $HOST = "http://staging.evolution.asia-live.com";



    const CONTROLLER_NAME = 'EvolutionGameController';
    public static function routes()
    {
        Route::get('/evolution/dev', self::CONTROLLER_NAME . '@index');
        // Route::post('/evolution/dev/GetBalance', self::CONTROLLER_NAME . '@getBalance');
    }
    public function index()
    {
        return 1234;
    }

    public function loginGame()
    {
        $username = "Test88";
        $current_timestamp = Carbon::now()->timestamp;
        $client = new Client([
            // 'exceptions'       => false,
            // 'verify'           => false,
            'headers'          => [
                'Accept'   => 'application/json',
                'Content-Type'   => 'application/json',
                // 'api_key' => $this->APIKey,
                // 'pf_id' => $this->PFID,
                // 'timestamp' => $current_timestamp,
            ]
        ]);

        $res = $client->request("POST", "http://staging.evolution.asia-live.com/ua/v1/k0ljjn7sywnogl16/4adc9bcb1e4f1eae20ce73f7a7ad8291", [
            "form_params" => [
                "uuid" =>  "048668af-3008-44c0-abcd-c97e9113bca1",
                "player" => [
                    "id" => "0833402202",
                    "update" => true,
                    "firstName" => "Test",
                    "lastName" => "Test",
                    "country" => "TH",
                    "language" => "TH",
                    "currency" => "THB",
                    "session" => [
                        "id" => "111ssss3333rrrrr45555",
                        "ip" => "192.168.0.1"
                    ]
                ]
            ]
        ]);
    }

    public function getBalance(Request $request)
    {
        Log::debug($request);
        return [
            "code" => 0,
            "msg" => "Success",
            "data" => [
                "balance" => 100000
            ]
        ];
    }



    // https://<hostname>/ua/v1/{casino.key}/{api.token}
    // {
    //     "uuid": "unique request identifier",
    //     "player": {
    //         "id": "a1a2a3a4",
    //         "update": true,
    //         "firstName": "firstName",
    //         "lastName": "lastName",
    //         "nickname": "nickname",
    //         "country": "DE",
    //         "language": "de",
    //         "currency": "EUR",
    //         "session": {
    //             "id": "111ssss3333rrrrr45555",
    //             "ip": "192.168.0.1"
    //         }
    //     },
    //     "config": {
    //         "brand": {
    //         "id": "1",
    //         "skin": "1"
    //     },
    //     "game": {
    //             "category": "roulette",
    //             "interface": "view1",
    //             "table": {
    //             "id": "vip-roulette-123"
    //         }
    //     },
    //     "channel": {
    //             "wrapped": false,
    //             "mobile": false
    //         },
    //         "urls": {
    //             "cashier": "http://www.chs.ee",
    //             "responsibleGaming": "http://www.RGam.ee",
    //             "lobby": "http://www.lobb.ee",
    //             "sessionTimeout": "http://www.sesstm.ee"
    //         }
    //     }
    // }
}
