<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


// Auth Endpoints
Route::group([
    'prefix' => 'v1',
    'middleware' => 'cors'
], function($router){
    Route::group([
        'prefix' => '/auth'
    ], function ($router) {
        Route::post('login', 'UserController@login');
        Route::post('register', 'UserController@registerUser');
        Route::get('pending-accounts', 'UserController@getPendingAccounts');
        Route::post('update-user-account', 'UserController@updateAccountStatus');
        Route::post('deactivate-user', 'UserController@deactivateUser');
        Route::post('block-user', 'UserController@updateBlockStatus');
        Route::get('get-all-users', 'UserController@getAllUsers');
        Route::post('register-user-by-admin', 'UserController@registerUserByAdmin');
        Route::get('search-user', 'UserController@searchUser');
        Route::post('update-my-password', 'UserController@updatePassword')->middleware('auth-token');
        Route::post('update-user-info', 'UserController@updateUserInfo');
    });
        Route::post('upload-profile-image', 'UserProfileController@uploadImage');
        Route::get('get-user-profile', 'UserProfileController@index')->middleware('auth-token');
        Route::post('update-user-profile', 'UserProfileController@store')->middleware('auth-token');


    Route::group([
        'prefix' => '/lead'
    ], function ($router) {
        Route::post('/create', 'LeadController@createLead');
        Route::get('/list', 'LeadController@getLeads');
        Route::post('/bulk-upload', 'LeadController@bulkCreateLead')->middleware('auth-token');
        Route::get('/search', 'LeadController@searchLead');
    });
    Route::get('/get-lead-by-id', 'LeadController@getLeadById');

    Route::group([
        'prefix' => '/agent'
    ], function ($router) {
        Route::post('/call', 'AgentController@makeOutgoingCall');
        Route::get('/get-token', 'AgentController@getToken');
        Route::post('/create-outgoing-call', 'AgentController@createOutgoingCall')->middleware('auth-token');
        Route::get('/get-previous-call-details', 'AgentController@getAgentCallDetails');
        Route::post('/update-call-details', 'AgentController@updateCallDetails');
        Route::post('/add-lead-comments', 'AgentController@updateLeadComments');
        Route::get('/get-all-agents', 'AgentController@getAllAgents')->middleware('auth-token');
        Route::post('/assign-agent', 'AgentController@assignAgent')->middleware('auth-token');
        Route::post('/unassign-agent', 'AgentController@unassignAgent')->middleware('auth-token');
    });
    Route::post('/create-event', 'EventController@createEvent')->middleware('auth-token');
    Route::post('/get-event-list', 'EventController@getEvents')->middleware('auth-token');
    Route::post('/update-event', 'EventController@updateEvent')->middleware('auth-token');

    Route::group([
        'prefix' => '/supervisor'
    ], function ($router) {
        Route::post('/', 'SupervisorController@getSupervisor');
        Route::get('get-available-supervisors', 'SupervisorController@getAvailableSupervisors');
    });
});
