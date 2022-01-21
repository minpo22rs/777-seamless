<?php

/** @var \Laravel\Lumen\Routing\Router $router */

use App\Classes\Payment;
use App\Http\Controllers\DevDreamGamingController;
use App\Http\Controllers\DevEvolutionGameController;
use App\Http\Controllers\DevRich88GameController;
use App\Http\Controllers\DreamGamingController;
use App\Http\Controllers\Rich88GameController;
use App\Http\Controllers\SBOController;
use App\Http\Controllers\EvolutionGameController;
use App\Http\Controllers\EvoplayController;
use App\Http\Controllers\SABAController;
use App\Http\Controllers\SABADevController;
use App\Http\Controllers\SBODevController;

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

$router->get('/', function () use ($router) {
    return " 🌏 Hello, World!";
});



// https://zap88.com/seamless/pp/dev/login/ppadmin/game/vs25pandagold
$router->group(['prefix' => 'seamless'], function () use ($router) {
});

$router->group(['prefix' => 'apiseamless'], function () use ($router) {
    // PP Dev
    $router->get('/pragmaticplay/dev', 'DevPPGameController@index');
    $router->get('/pragmaticplay/dev/launch/{userToken}/{gameId}', 'DevPPGameController@login');
    $router->post('/pragmaticplay/dev/authenticate.html', 'DevPPGameController@auth');
    $router->post('/pragmaticplay/dev/balance.html', 'DevPPGameController@balance');
    $router->post('/pragmaticplay/dev/bet.html', 'DevPPGameController@bet');
    $router->post('/pragmaticplay/dev/result.html', 'DevPPGameController@settle');
    $router->post('/pragmaticplay/dev/bonusWin.html', 'DevPPGameController@bonusWin');
    $router->post('/pragmaticplay/dev/jackpotWin.html', 'DevPPGameController@jackpotWin');
    $router->post('/pragmaticplay/dev/refund.html', 'DevPPGameController@cancelBet');
    $router->post('/pragmaticplay/dev/promoWin.html', 'DevPPGameController@promoWin');

    // PP Prod
    $router->get('/pragmaticplay/launch/{userToken}/{gameId}', 'PPGameController@login');
    $router->get('/pragmaticplay', 'PPGameController@index');
    $router->post('/pragmaticplay/authenticate.html', 'PPGameController@auth');
    $router->post('/pragmaticplay/balance.html', 'PPGameController@balance');
    $router->post('/pragmaticplay/bet.html', 'PPGameController@bet');
    $router->post('/pragmaticplay/result.html', 'PPGameController@settle');
    $router->post('/pragmaticplay/bonusWin.html', 'PPGameController@bonusWin');
    $router->post('/pragmaticplay/jackpotWin.html', 'PPGameController@jackpotWin');
    $router->post('/pragmaticplay/refund.html', 'PPGameController@cancelBet');
    $router->post('/pragmaticplay/promoWin.html', 'PPGameController@promoWin');

    //Sexy game
    $router->get('/sexygaming/test', function () {
        return ['balance' => round(0.55555555555, 2)];
    });

    $router->get('/sexygaming/dev/login/{username}/{gameType}', 'DevSexyGameController@login');
    $router->post('/sexygaming/dev', 'DevSexyGameController@getBalance');

    $router->get('/sexygaming/login/{username}/{gameType}', 'SexyGameController@login');
    $router->post('/sexygaming', 'SexyGameController@getBalance');

    // Jili Dev
    $router->get('/jili/dev', 'DevJiliGamController@index');
    $router->get('/jili/dev/play/{userToken}', 'DevJiliGamController@login');
    $router->post('/jili/dev/auth', 'DevJiliGamController@auth');
    $router->post('/jili/dev/bet', 'DevJiliGamController@bet');
    $router->post('/jili/dev/cancelBet', 'DevJiliGamController@cancelBet');
    $router->post('/jili/dev/sessionBet', 'DevJiliGamController@sessionBet');
    $router->post('/jili/dev/cancelSessionBet', 'DevJiliGamController@cancelSessionBet');

    // Jili Prod
    $router->get('/jili', 'JiliGamController@index');
    $router->get('/jili/play/{userToken}', 'JiliGamController@login');
    $router->post('/jili/auth', 'JiliGamController@auth');
    $router->post('/jili/bet', 'JiliGamController@bet');
    $router->post('/jili/cancelBet', 'JiliGamController@cancelBet');
    $router->post('/jili/sessionBet', 'JiliGamController@sessionBet');
    $router->post('/jili/cancelSessionBet', 'JiliGamController@cancelSessionBet');

    //Joker 

    // https://www.gwc688.net/PlayGame?token=39cf2e9a-2e17-42ee-b900-b097299c0cce&appid=TGFV&gameCode=dc7sh3dfmjpio&language=en&mobile=false&redirectUrl=https://nasavg.com


    // Dev
    $router->get('/joker/dev/launch/{username}/{gameCode}', 'DevJokerController@play');
    $router->get('/joker/dev', 'DevJokerController@index');
    $router->post('/joker/dev/authenticate-token', 'DevJokerController@auth');
    $router->post('/joker/dev/balance', 'DevJokerController@getbalance');
    $router->post('/joker/dev/bet', 'DevJokerController@bet');
    $router->post('/joker/dev/cancel-bet', 'DevJokerController@cancelBet');
    $router->post('/joker/dev/settle-bet', 'DevJokerController@settle');

    $router->post('/joker/dev/bonus-win', 'DevJokerController@bonusWin');
    $router->post('/joker/dev/jackpot-win', 'DevJokerController@jackpotWin');

    $router->post('/joker/dev/transaction', 'DevJokerController@transaction');
    $router->post('/joker/dev/withdraw', 'DevJokerController@withdraw');
    $router->post('/joker/dev/deposit', 'DevJokerController@deposit');

    // Prod
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


    $router->get('/amb/dev/launch/{token}', 'DevAmbController@getLobbyGame');
    $router->post('/amb/dev/balance', 'DevAmbController@getbalance');
    $router->post('/amb/dev/bet', 'DevAmbController@bet');
    $router->post('/amb/dev/payout', 'DevAmbController@settle');
    $router->post('/amb/dev/cancel', 'DevAmbController@cancelBet');
    $router->post('/amb/dev/void', 'DevAmbController@voidSettle');

    $router->get('/amb/launch/{token}', 'AmbController@getLobbyGame');
    $router->post('/amb/balance', 'AmbController@getbalance');
    $router->post('/amb/bet', 'AmbController@bet');
    $router->post('/amb/payout', 'AmbController@settle');
    $router->post('/amb/cancel', 'AmbController@cancelBet');
    $router->post('/amb/void', 'AmbController@voidSettle');

    $router->get('/amb/time', function () {
        $mytime = Carbon\Carbon::now();
        echo $mytime->toDateTimeString() . "</br>";

        echo phpinfo();
        $value = config('app.timezone');
        echo $value . "</br>";
        echo date('Y-m-d H:i:s') . "</br>";
        echo date_default_timezone_get() . "</br>";
    });

    DevDreamGamingController::routes();
    DreamGamingController::routes();

    SBOController::routes();
    SBODevController::routes();

    SABADevController::routes();
    SABAController::routes();

    DevRich88GameController::routes();
    Rich88GameController::routes();

    DevEvolutionGameController::routes();
    EvolutionGameController::routes();
    
    EvoplayController::routes();
});
