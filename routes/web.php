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

$router->get('/secret/v2/bank/scb', 'ScbController@getTransaction');
$router->post('/secret/v2/bank/scb', 'ScbController@transfer');
$router->get('/secret/v2/bank/scb/get-account-info', 'ScbController@getAccountInfo');

$router->get('/launcher/joker', 'LauncherController@joker');

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

$router->get('/awc/dev/launch/{username}/{gameType}', 'DevSexyGameController@login');
$router->post('/awc/dev', 'SexyGameController@getBalance');

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

$router->post('/amb/void', 'AmbController@voidSettle');

$account = [
    'deposit' => [
        'deviceId' => '86cc7cfd-01b6-4458-bfff-1c0e249bef7b',
        'pin' => '252525',
        'accountNo' => '1222391241',
    ],
    'withdraw' => [
        'deviceId' => 'f29f7a74-7c55-4476-a6e9-9ca8eadaab61',
        'pin' => '222522',
        'accountNo' => '1922431105',
    ],
    'test' => [
        'deviceId' => 'e69f3401-5e93-4bc4-9de6-cd57712fcac9',
        'pin' => '272727',
        'accountNo' => '4351356988',
    ],
    'another1' => [
        'deviceId' => 'f29f7a74-7c55-4476-a6e9-9ca8eadaab61',
        'pin' => '221689',
        'accountNo' => '1922315587',
    ],
    'godbetDeposit' => [
        'deviceId' => '4c00571b-00f4-40eb-9600-b2afa77dcb94',
        'pin' => '112531',
        'accountNo' => '4161409882',
    ],
    'godbetWithdraw' => [
        'deviceId' => '4c00571b-00f4-40eb-9600-b2afa77dcb94',
        'pin' => '112531',
        'accountNo' => '4161413302',
    ],
];

// $account = [
//     'deposit' => [
//         'deviceId' => '0391778a-32ee-4029-8d6e-df6a6bfd62c5',
//         'pin' => '252525',
//         'accountNo' => '1922431236',
//     ],
//     'withdraw' => [
//         'deviceId' => '86cc7cfd-01b6-4458-bfff-1c0e249bef7b',
//         'pin' => '252525',
//         'accountNo' => '1222391241',
//     ],
//     'test' => [
//         'deviceId' => 'e69f3401-5e93-4bc4-9de6-cd57712fcac9',
//         'pin' => '272727',
//         'accountNo' => '4351356988',
//     ],
//     'another1' => [
//         'deviceId' => 'D3C9DE95-3348-4C18-A9F4-60014148E3A1',
//         'pin' => '159691',
//         'accountNo' => '4190351844',
//     ],
// ];

$bankSecretKey = env('BANK_SECRET_KEY', '');

$router->get('/secret/bank/scb/verify-account', function (Request $request) use ($bankSecretKey, $account) {
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
    if ($request->secretKey != $bankSecretKey) {
        return ['message' => 'Your secret key is not valid'];
    }

    $scb = new SCBEasyAPI();
    $scb->setAccount($account[$request->accountType]['deviceId'], $account[$request->accountType]['pin'], $account[$request->accountType]['accountNo']);
    if ($scb->login()) {
        return json_encode($scb->transfer($request->bank, $request->accountNumber, $request->amount));
    }
});
