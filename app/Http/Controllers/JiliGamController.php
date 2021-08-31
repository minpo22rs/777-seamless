<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


class JiliGamController extends Controller
{
    //
    private $host = "https://uat-wb-api.jlfafafa2.com";
    private $agentId = "ZF084_NasaVG";
    private $agentKey = "9619530419d418d8cbdd437e381d67e440c9ce5d";
    private $currencyCode = "THB";
    private $gameLang = "en-US";

    public function index()
    {
        $currentTime =  Carbon::now()->format('ymd');
        $keyG = md5($currentTime . $this->agentId . $this->agentKey);
        $queryString = "AgentId=$this->agentId";

        $md5string = md5($queryString . $keyG);
        $key = Str::random(6) . $md5string . Str::random(6);
        try {
            $client = new Client();
            $res = $client->request('POST', $this->host . '/api1/GetGameList?AgentId=$this->agentId&Key=$key', [

                'form_params' => [
                    'AgentId' =>  $this->agentId,
                    'Key' =>  $key,
                ]
            ]);
            // echo $res->getStatusCode();
            // echo $res->getHeader('content-type')[0];
            // echo $res->getBody();
            $response = $res->getBody();
            if ($response) {
                return $response;
            }
        } catch (\Exception $e) {
            return $e;
        }
    }

    public function login($username)
    {
        $user = User::where('username', $username)->first();
        if (empty($user)) {
            $response = ["message" => 'Oops! The user does not exist'];
            return response($response, 401);
            exit();
        }
        $userToken = $user->token;

        $currentTime =  Carbon::now()->format('ymd');
        $keyG = md5($currentTime . $this->agentId . $this->agentKey);
        $queryString = "Token=$userToken&GameId=1&Lang=$this->gameLang&AgentId=$this->agentId";
        $md5string = md5($queryString . $keyG);
        $key = Str::random(6) . $md5string . Str::random(6);

        // echo $queryString;

        try {
            $client = new Client();
            $res = $client->request('POST', $this->host . '/singleWallet/LoginWithoutRedirect?' . $queryString . '&Key=' . $key, [
                'form_params' => [
                    'AgentId' =>  $this->agentId,
                    'Key' =>  $key,
                ]
            ]);
            echo $res->getStatusCode();
            echo $res->getHeader('content-type')[0];
            echo $res->getBody();
            $response = $res->getBody();
            if ($response) {
                // return $response;
            }
        } catch (\Exception $e) {
            return $e;
        }

        // var options = {
        //     // method: "GET",
        //     method: "POST",
        //     uri: "https://uat-wb-api.jlfafafa2.com/singleWallet/LoginWithoutRedirect?" + queryString + "&Key=" + key,
        //     // uri: `https://uat-wb-api.jlfafafa2.com/api1/GetGameList?${queryString}&Key=${key}`,
        //     headers: {
        //         "Content-Type": "application/x-www-form-urlencoded",
        //     },
        //     form: {
        //         AgentId: this.agentId,
        //         Key: key,
        //     }
        // };
        // try {
        //     let response = await request(options);
        //     return response;
        // } catch (err) {
        //     return err.message;
        // }
    }

    public function auth(Request $request)
    {
        // Log::debug($request);
        $reqId = $request->reqId;
        $userToken = $request->token;
        if (empty($reqId) || empty($reqId)) {
            return [
                "errorCode" => 5,
                "message" => "Invalid parameters"
            ];
        }

        $user = User::where('token', $userToken)->first();
        if (!$user) {
            return [
                "errorCode" => 5,
                "message" => "Invalid user"
            ];
        }
        return [
            'username'=>$user->username,
            'currency'=>$user->main,
            'balance'=>'',
            'token'=>'',
        ];
    }

    public function getBalance(Request $request)
    {
        Log::debug($request);
    }
}
