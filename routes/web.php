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


    $router->get('/jili/dev/login/{username}', 'JiliGamController@login');
    $router->get('/jili/dev', 'JiliGamController@index');
    $router->post('/jili/dev', 'JiliGamController@getBalance');
    $router->post('/jili/dev/auth', 'JiliGamController@auth');


    //Joker
    $router->get('/joker/test', function () use ($router) {
        return " 🌏 Hello, Joker!";
    });
    $router->get('/joker/dev/login/{username}', 'JokerController@login');
    $router->post('/joker/dev', 'JokerController@getBalance');
});
