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
    return $router->app->version();
});

$router->group(['middleware' => 'cors'], function () use ($router) {

    // Auth endpoints
    $router->post('auth/login', 'AuthController@login');
    $router->post('auth/refresh', 'AuthController@refresh');
    $router->group(['middleware' => ['auth:admin,editor']], function () use ($router) {
        $router->get('auth/me', 'AuthController@me');
        $router->post('auth/logout', 'AuthController@logout');
    });

    $router->group(['middleware' => 'guest.token'], function () use ($router) {
        $router->get('/guests/{token}', 'GuestController@showByToken');
        $router->patch('/guests/{token}', 'GuestController@updateByToken');
        $router->get('/rsvp/{token}', 'RsvpController@showByToken');
        $router->patch('/rsvp/{token}', 'RsvpController@updateByToken');
        $router->get('guests/{token}/details',             'RsvpController@details');
    });

    // Admin endpoints protected by JWT
    $router->group(['prefix' => 'admin', 'middleware' => ['auth:admin,editor']], function () use ($router) {
        // Dashboard statistics
        $router->get('dashboard/stats', 'DashboardController@statistics');
        
        // Guests CRUD
        $router->get('guests',          'GuestController@index');
        $router->get('guests/export',   'GuestController@export');
        $router->post('guests',         'GuestController@store');
        $router->get('guests/{id}',     'GuestController@show');
        $router->patch('guests/{id}',   'GuestController@update');
        $router->delete('guests/{id}',  'GuestController@destroy');
        $router->get('guests/{id}/logs','GuestController@logs');

        // Companion management
        $router->get('guests/{guestId}/companion',    'CompanionController@show');
        $router->post('guests/{guestId}/companion',   'CompanionController@upsert');
        $router->delete('guests/{guestId}/companion', 'CompanionController@destroy');
    });
});
