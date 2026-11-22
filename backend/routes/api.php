<?php

use App\Http\Controllers\HerdController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok', 'mode' => 'development']));
Route::get('/openapi.json', fn () => response()->file(resource_path('openapi.json'), ['Content-Type' => 'application/json']));
Route::get('/docs', fn () => response()->view('docs'));

Route::prefix('api/v1')->controller(HerdController::class)->group(function () {
    Route::get('/animals', 'animals');
    Route::post('/animals', 'createAnimal');
    Route::get('/animals/{id}', 'animal');
    Route::get('/events', 'events');
    Route::post('/events', 'createEvent');
    Route::get('/events/{id}', 'event');
    Route::get('/groups', 'groups');
    Route::get('/paddocks', 'paddocks');
});
