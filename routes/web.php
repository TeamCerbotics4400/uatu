<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\MatchesController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\MXTaskController;
use App\Http\Controllers\Api\ServiceTaskController;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('api')->group(function () {
    // Users routes
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    // Matches routes
    Route::get('/matches', [MatchesController::class, 'index']);
    Route::post('/matches', [MatchesController::class, 'store']);
    Route::get('/matches/{id}', [MatchesController::class, 'show']);
    Route::put('/matches/{id}', [MatchesController::class, 'update']);
    Route::delete('/matches/{id}', [MatchesController::class, 'destroy']);

    // Teams routes
    Route::get('/teams', [TeamController::class, 'index']);
    Route::post('/teams', [TeamController::class, 'store']);
    Route::get('/teams/{id}', [TeamController::class, 'show']);
    Route::put('/teams/{id}', [TeamController::class, 'update']);
    Route::delete('/teams/{id}', [TeamController::class, 'destroy']);

    // MXTasks routes
    Route::get('/mxtasks', [MXTaskController::class, 'index']);
    Route::post('/mxtasks', [MXTaskController::class, 'store']);
    Route::get('/mxtasks/{id}', [MXTaskController::class, 'show']);
    Route::put('/mxtasks/{id}', [MXTaskController::class, 'update']);
    Route::delete('/mxtasks/{id}', [MXTaskController::class, 'destroy']);

    // ServiceTasks routes
    Route::get('/service-tasks', [ServiceTaskController::class, 'index']);
    Route::post('/service-tasks', [ServiceTaskController::class, 'store']);
    Route::get('/service-tasks/{id}', [ServiceTaskController::class, 'show']);
    Route::put('/service-tasks/{id}', [ServiceTaskController::class, 'update']);
    Route::delete('/service-tasks/{id}', [ServiceTaskController::class, 'destroy']);
});