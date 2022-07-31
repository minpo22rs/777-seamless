<?php

/** @var \Laravel\Lumen\Routing\Router $router */

use App\Classes\SCBEasyAPI;
use App\Http\Controllers\DreamGamingController;
use App\Http\Controllers\Rich88GameController;
use App\Http\Controllers\SBOController;
use App\Http\Controllers\EvolutionGameController;
use App\Http\Controllers\EvoplayController;
use App\Http\Controllers\HuayDragonController;
use App\Http\Controllers\SABAController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/hello-world', function () use ($router) {
    return " 🌏 Hello, World!";
});


DreamGamingController::routes();
Rich88GameController::routes();
EvolutionGameController::routes();
EvoplayController::routes();
SBOController::routes();
SABAController::routes();
HuayDragonController::routes();

$router->get('/pragmatic/launch/{userToken}/{gameId}', 'PPGameController@login');
$router->get('/pragmatic', 'PPGameController@index');
$router->post('/pragmatic/authenticate', 'PPGameController@auth');
$router->post('/pragmatic/balance', 'PPGameController@balance');
$router->post('/pragmatic/bet', 'PPGameController@bet');
$router->post('/pragmatic/result', 'PPGameController@settle');
$router->post('/pragmatic/bonusWin', 'PPGameController@bonusWin');
$router->post('/pragmatic/jackpotWin', 'PPGameController@jackpotWin');
$router->post('/pragmatic/refund', 'PPGameController@cancelBet');
$router->post('/pragmatic/promoWin', 'PPGameController@promoWin');

$router->get('/awc/launch/{username}/{gameType}', 'SexyGameController@login');
$router->post('/awc', 'SexyGameController@getBalance');

$router->get('/jili', 'JiliGamController@index');
$router->get('/jili/play/{userToken}', 'JiliGamController@login');
$router->post('/jili/auth', 'JiliGamController@auth');
$router->post('/jili/bet', 'JiliGamController@bet');
$router->post('/jili/cancelBet', 'JiliGamController@cancelBet');
$router->post('/jili/sessionBet', 'JiliGamController@sessionBet');
$router->post('/jili/cancelSessionBet', 'JiliGamController@cancelSessionBet');

$router->get('/joker/launch/{username}/{gameCode}', 'JokerController@play');
$router->get('/joker', 'JokerController@index');
$router->post('/joker/authenticate-token', 'JokerController@auth');
$router->post('/joker/balance', 'JokerController@getbalance');
$router->post('/joker/bet', 'JokerController@bet');
$router->post('/joker/cancel-bet', 'JokerController@cancelBet');
$router->post('/joker/settle-bet', 'JokerController@settle');
$router->post('/joker/bonus-win', 'JokerController@bonusWin');
$router->post('/joker/jackpot-win', 'JokerController@jackpotWin');
$router->post('/joker/transaction', 'JokerController@transaction');
$router->post('/joker/withdraw', 'JokerController@withdraw');
$router->post('/joker/deposit', 'JokerController@deposit');

$router->get('/amb/launch/{token}', 'AmbController@getLobbyGame');
$router->post('/amb/balance', 'AmbController@getbalance');
$router->post('/amb/bet', 'AmbController@bet');
$router->post('/amb/payout', 'AmbController@settle');
$router->post('/amb/cancel', 'AmbController@cancelBet');
$router->post('/amb/void', 'AmbController@voidSettle');

$account = [
    'deposit' => [
        'deviceId' => '604a4f9c-5024-4af6-9035-4cca5f38f392',
        'pin' => '252525',
        'accountNo' => '1922431236',
    ],
    'withdraw' => [
        'deviceId' => 'd9c1df03-d37f-4c82-ac94-8d0cf6b272fd',
        'pin' => '252525',
        'accountNo' => '1222391241',
    ],
    // ใช้สำหรับอ่านชื่อบัญชี
    'another1' => [
        'deviceId' => '604a4f9c-5024-4af6-9035-4cca5f38f392',
        'pin' => '252525',
        'accountNo' => '1922431236',
    ],
];

$bankSecretKey = env('BANK_SECRET_KEY', '');

$router->get('/secret/bank/scb/verify-account', function (Request $request) use ($bankSecretKey, $account) {
    // สำหรับดึงรายการฝาก
    if ($request->secretKey != $bankSecretKey) {
        return ['message' => 'Your secret key is not valid'];
    }

    $bank = $request->bank;
    $accountNo = $request->accountNo;

    $scb = new SCBEasyAPI();
    $scb->setAccount($account[$request->accountType]['deviceId'], $account[$request->accountType]['pin'], $account[$request->accountType]['accountNo']);
    if ($scb->login()) {
        return json_encode($scb->verifyAccount($bank, $accountNo));
    }
});

$router->get('/secret/bank/scb', function (Request $request) use ($bankSecretKey, $account) {
    // สำหรับดึงรายการฝาก
    if ($request->secretKey != $bankSecretKey) {
        return ['message' => 'Your secret key is not valid'];
    }

    $scb = new SCBEasyAPI();
    $scb->setAccount($account[$request->accountType]['deviceId'], $account[$request->accountType]['pin'], $account[$request->accountType]['accountNo']);
    if ($scb->login()) {
        return json_encode($scb->transactions());
    }
});

$router->post('/secret/bank/scb', function (Request $request) use ($bankSecretKey, $account) {
    // สำหรับถอนเงิน
    if ($request->secretKey != $bankSecretKey) {
        return ['message' => 'Your secret key is not valid'];
    }

    $scb = new SCBEasyAPI();
    $scb->setAccount($account[$request->accountType]['deviceId'], $account[$request->accountType]['pin'], $account[$request->accountType]['accountNo']);
    if ($scb->login()) {
        return json_encode($scb->transfer($request->bank, $request->accountNumber, $request->amount));
    }
    
});
