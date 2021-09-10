<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PPGame;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


class PPGameController extends Controller
{
    //
    private $host2 = "https://zap88.com/seamless/pp/dev";
    private $host = "https://api.prerelease-env.biz/IntegrationService/v3/http/CasinoGameAPI";
    private $secureLogin = "tg168_zap88";
    private $SecretKey = "testKey";

    private function encryptBody($queryString)
    {
        $key = md5($queryString . $this->SecretKey);
        return $key;
    }

    public function index()
    {
        $queryString = "secureLogin={$this->secureLogin}";
        $key = $this->encryptBody($queryString);
        try {
            $client = new Client();
            $res = $client->request("POST", "{$this->host}/getCasinoGames?secureLogin={$this->secureLogin}&hash={$key}", [
                // "form_params" => [
                //     "secureLogin" =>  "tg168_zap88",
                //     "hash" =>  $key,
                // ]
            ]);
            // echo $res->getStatusCode();
            // echo $res->getHeader("content-type")[0];
            // echo $res->getBody();
            $response = $res->getBody();
            return $response;
        } catch (\Exception $e) {
            return $e;
        }
    }

    public function login($username, $gameId)
    {
        $user = User::where('username', $username)->first();
        if (empty($user)) {
            $response = ["message" => "Oops! The user does not exist"];
            return response($response, 401);
            exit();
        }
        // $userToken = $user->token;
        // echo "userToken ==>" . $userToken . "<br/>";

        // $queryString = "token=bab3574a-49a8-442a-947f-bae82b17a3b2&symbol=vswaysyumyum&language=en&technology=html5&platform=WEB&ocashierUrl=https://nasavg.com/apiseamless/ppgaming/dev&lobbyUrl=https://nasavg.com/apiseamless/ppgaming/dev";
        // $key = $this->encryptBody($queryString);

        // echo "key ==>" . $key . "<br/>";

        // exit();
        // https: //{game_server_domain}/gs2c/playGame.do?key=token%3D{token}%26symbol%3D{symbol}%26technology%3D{technology}%26platform%3D{platform}%26language%3D{language}%26cashierUrl%3DcashierUrl %26lobbyUrl%3DlobbyUrl&stylename={secureLogin}

        // https://tg168.prerelease-env.biz/gs2c/common/js/lobby/GameLib.js?token=bab3574a-49a8-442a-947f-bae82b17a3b2&playersymbol=vswaysyumyum&technology=html5&platform=WEB&language=en&ocashierUrl=https://nasavg.com/apiseamless/ppgaming/dev&lobbyUrl=https://nasavg.com/apiseamless/ppgaming/dev&sidesecureLogin=testKey

        try {
            $client = new Client();
            $res = $client->request(
                "get",
                "https://tg168.prerelease-env.biz/gs2c/playGame.do?key=token%3Dbab3574a-49a8-442a-947f-bae82b17a3b2%26symbol%3Dvswaysyumyum%26language%3Den%26technology%3DH5%26platform%3DWEB%26cashierUrl%3Dhttps%3A%2F%2Fnasavg.com%2Fapiseamless%2Fppgaming%2Fdev%26lobbyUrl%3Dhttps%3A%2F%2Fnasavg.com%2Fapiseamless%2Fppgaming%2Fdev&stylename=tg168_zap88",
                [
                    // "form_params" => [
                    //     "secureLogin" =>  "tg168_zap88",
                    //     "hash" =>  $key,
                    // ]
                ]
            );
            // echo $res->getStatusCode();
            // echo $res->getHeader("content-type")[0];
            // echo $res->getBody();
            $response = $res->getBody();
            return $response;

            // $json = json_decode($response);
            // if ($json->ErrorCode == 0) {
            //     return redirect($json->Data);
            // } else {
            //     return $response;
            // }
        } catch (\Exception $e) {
            return $e;
        }
    }
    public function login_v($username, $gameId)
    {
        $user = User::where('username', $username)->first();
        if (empty($user)) {
            $response = ["message" => "Oops! The user does not exist"];
            return response($response, 401);
            exit();
        }
        $userToken = $user->token;
        $queryString = "token={$userToken}&providerId={$gameId}";
        $key = $this->encryptBody($queryString);

        try {
            $client = new Client();
            $res = $client->request(
                "POST",
                $this->host . "/authenticate.html?hash={$key}&token={$userToken}&providerId={$gameId}"
                // "https://api.prerelease-env.biz/IntegrationService/v3/http/CasinoGameAPI/authenticate.html?hash={$key}&token={$userToken}&providerId={$gameId}"
                // // providerId=pragmaticplay&hash=e1467eb30743fb0a180ed141a26c58f7&token=5v93mto7jr
                // // $res = $client->request("POST", $this->host . "/authenticate.html?providerId={$gameId}&hash={$key}&token={$userToken}"
                ,
                [
                    "form_params" => [
                        "secureLogin" =>  "tg168_zap88",
                        "hash" =>  $key,
                    ]
                ]
            );
            // echo $res->getStatusCode();
            // echo $res->getHeader("content-type")[0];
            // echo $res->getBody();
            $response = $res->getBody();
            return $response;

            // $json = json_decode($response);
            // if ($json->ErrorCode == 0) {
            //     return redirect($json->Data);
            // } else {
            //     return $response;
            // }
        } catch (\Exception $e) {
            return $e;
        }
    }
    public function auth(Request $request)
    {
        Log::debug($request);
        return [
            "userId" => "421",
            "currency" => "USD",
            "cash" => 99999.99,
            "bonus" => 99.99,
            "country" => "GB",
            "jurisdiction" => "UK",
            "betLimits" => [
                "defaultBet" => 0.10,
                "minBet" => 0.02,
                "maxBet" => 10.00,
                "minTotalBet" => 0.50,
                "maxTotalBet" => 250.00,
            ],
            "error" => 0,
            "description" => "Success"
        ];
    }
}
