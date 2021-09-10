<?php

/** @var \Laravel\Lumen\Routing\Router $router */

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

$router->group(['prefix' => 'apiseamless'], function () use ($router) {
    //Sexy game
    $router->get('/sexygaming/test', function () use ($router) {
        return " 🌏 Hello, World!";
    });
    $router->get('/sexygaming/dev/login/{username}', 'SexyGameController@login');
    $router->post('/sexygaming/dev', 'SexyGameController@getBalance');

    $router->get('/sexygaming/login/{username}', 'SexyGameController@login');
    $router->post('/sexygaming', 'SexyGameController@getBalance');



    $router->get('/jili/dev/login/{username}/game/{gameId}', 'JiliGamController@login');
    $router->get('/jili/dev', 'JiliGamController@index');
    $router->post('/jili/dev/auth', 'JiliGamController@auth');
    $router->post('/jili/dev/bet', 'JiliGamController@bet');
    $router->post('/jili/dev/cancelBet', 'JiliGamController@cancelBet');
    $router->post('/jili/dev/sessionBet', 'JiliGamController@sessionBet');
    $router->post('/jili/dev/cancelSessionBet', 'JiliGamController@cancelSessionBet');


    $router->get('/ppgaming/dev', 'PPGameController@index');
    $router->get('/ppgaming/dev/login/{username}/game/{gameId}', 'PPGameController@login');
    $router->post('/ppgaming/dev', 'PPGameController@auth');
    $router->post('/ppgaming/dev/auth', 'PPGameController@auth');
    $router->post('/ppgaming/auth', 'PPGameController@auth');
    $router->post('/ppgaming', 'PPGameController@auth');
    $router->post('/auth', 'PPGameController@auth');
    $router->post('/', 'PPGameController@auth');

    //Joker
    $router->get('/joker/test', function () use ($router) {
        return " 🌏 Hello, Joker!";
    });
    $router->get('/joker/dev/login/{username}', 'JokerController@login');
    $router->get('/joker/dev', 'JokerController@testt');
});
