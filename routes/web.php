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


DreamGamingController::routes();
Rich88GameController::routes();
EvolutionGameController::routes();
EvoplayController::routes();
SBOController::routes();
SABAController::routes();

$router->get('/pragmatic/launch/{userToken}/{gameId}', 'PPGameController@login');
$router->get('/pragmatic', 'PPGameController@index');
$router->post('/pragmatic/authenticate.html', 'PPGameController@auth');
$router->post('/pragmatic/balance.html', 'PPGameController@balance');
$router->post('/pragmatic/bet.html', 'PPGameController@bet');
$router->post('/pragmatic/result.html', 'PPGameController@settle');
$router->post('/pragmatic/bonusWin.html', 'PPGameController@bonusWin');
$router->post('/pragmatic/jackpotWin.html', 'PPGameController@jackpotWin');
$router->post('/pragmatic/refund.html', 'PPGameController@cancelBet');
$router->post('/pragmatic/promoWin.html', 'PPGameController@promoWin');

$router->get('/sexy-tiger-kingmaker/login/{username}/{gameType}', 'SexyGameController@login');
$router->post('/sexy-tiger-kingmaker', 'SexyGameController@getBalance');

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