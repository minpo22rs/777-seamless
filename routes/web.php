<?php

/** @var \Laravel\Lumen\Routing\Router $router */

use App\Http\Controllers\DreamGamingController;
use App\Http\Controllers\SBOController;

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
    $router->get('/pragmaticplay/dev', 'PPGameController@index');
    $router->get('/pragmaticplay/dev/play/{userToken}/{gameId}', 'PPGameController@login');
    $router->post('/pragmaticplay/dev/authenticate.html', 'PPGameController@auth');
    $router->post('/pragmaticplay/dev/balance.html', 'PPGameController@balance');
    $router->post('/pragmaticplay/dev/bet.html', 'PPGameController@bet');
    $router->post('/pragmaticplay/dev/result.html', 'PPGameController@settle');
    $router->post('/pragmaticplay/dev/bonusWin.html', 'PPGameController@bonusWin');
    $router->post('/pragmaticplay/dev/jackpotWin.html', 'PPGameController@jackpotWin');
    $router->post('/pragmaticplay/dev/refund.html', 'PPGameController@cancelBet');
    $router->post('/pragmaticplay/dev/promoWin.html', 'PPGameController@promoWin');

    // PP Prod
    $router->get('/pragmaticplay/play/{userToken}/{gameId}', 'PPGameController@login');
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
    $router->get('/sexygaming/test', function () use ($router) {
        return " 🌏 Hello, World!";
    });
    $router->get('/sexygaming/dev/login/{username}/{gameType}', 'SexyGameController@login');
    $router->post('/sexygaming/dev', 'SexyGameController@getBalance');

    $router->get('/sexygaming/login/{username}', 'SexyGameController@login');
    $router->post('/sexygaming', 'SexyGameController@getBalance');

    // Jili Dev
    $router->get('/jili/dev', 'JiliGamController@index');
    $router->get('/jili/dev/play/{userToken}', 'JiliGamController@login');
    $router->post('/jili/dev/auth', 'JiliGamController@auth');
    $router->post('/jili/dev/bet', 'JiliGamController@bet');
    $router->post('/jili/dev/cancelBet', 'JiliGamController@cancelBet');
    $router->post('/jili/dev/sessionBet', 'JiliGamController@sessionBet');
    $router->post('/jili/dev/cancelSessionBet', 'JiliGamController@cancelSessionBet');

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

    $router->get('/joker/play/{username}/{gameCode}', 'JokerController@play');

    // Dev
    $router->get('/joker/dev', 'JokerController@index');
    $router->post('/joker/dev/authenticate-token', 'JokerController@auth');
    $router->post('/joker/dev/balance', 'JokerController@getbalance');
    $router->post('/joker/dev/bet', 'JokerController@bet');
    $router->post('/joker/dev/cancel-bet', 'JokerController@cancelBet');
    $router->post('/joker/dev/settle-bet', 'JokerController@settle');

    $router->post('/joker/dev/bonus-win', 'JokerController@bonusWin');
    $router->post('/joker/dev/jackpot-win', 'JokerController@jackpotWin');

    $router->post('/joker/dev/transaction', 'JokerController@transaction');
    $router->post('/joker/dev/withdraw', 'JokerController@withdraw');
    $router->post('/joker/dev/deposit', 'JokerController@deposit');
    // Prod
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


    //AMB
    // $router->get('/amb/dev', 'AmbController@getGameList');
    // $router->get('/amb/dev/dev', 'AmbController@devdev');
    $router->get('/amb/play/{token}', 'AmbController@getLobbyGame');
    $router->get('/amb/dev/{token}', 'AmbController@getLobbyGame');
    $router->post('/amb/dev/balance', 'AmbController@getbalance');
    $router->post('/amb/dev/bet', 'AmbController@bet');
    $router->post('/amb/dev/payout', 'AmbController@settle');
    $router->post('/amb/dev/cancel', 'AmbController@cancelBet');
    $router->post('/amb/dev/void', 'AmbController@voidSettle');

    DreamGamingController::routes();
    SBOController::routes();
});
