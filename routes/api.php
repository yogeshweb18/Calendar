<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Acme\Calendar\Http\Controllers\CalendarController;
/*
|--------------------------------------------------------------------------
| Tool API Routes
|--------------------------------------------------------------------------
|
| Here is where you may register API routes for your tool. These routes
| are loaded by the ServiceProvider of your tool. They are protected
| by your tool's "Authorize" middleware by default. Now, go build!
|
*/

// Route::get('/', function (Request $request) {
//     //
// });
Route::get('/calendar','Acme\Calendar\Http\Controllers\CalendarController@fetchData');
Route::post('/submitresult', 'Acme\Calendar\Http\Controllers\CalendarController@submitResult');
Route::post('/notifyIfFail','Acme\Calendar\Http\Controllers\CalendarController@notifyIfFail');
Route::post('/saveFailCovenant','Acme\Calendar\Http\Controllers\CalendarController@saveFailCovenant');
Route::post('/encryptKey', 'Acme\Calendar\Http\Controllers\CalendarController@encryptKey');