<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\ScreenController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);

Route::post('/device/pair', [DeviceController::class, 'pair']);
Route::get('/media/file/{publicationItem}', [DeviceController::class, 'file'])->name('media.file');

Route::middleware('device.token')->group(function () {
    Route::get('/device/manifest', [DeviceController::class, 'manifest']);
    Route::post('/device/heartbeat', [DeviceController::class, 'heartbeat']);
});

Route::middleware('jwt.verify')->group(function () {
    Route::get('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/organizations', [OrganizationController::class, 'index']);
    Route::post('/organizations', [OrganizationController::class, 'store']);
    Route::put('/organizations/{organization}', [OrganizationController::class, 'update']);
    Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy']);

    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);

    Route::get('/screens', [ScreenController::class, 'index']);
    Route::post('/screens', [ScreenController::class, 'store']);
    Route::put('/screens/{screen}', [ScreenController::class, 'update']);
    Route::delete('/screens/{screen}', [ScreenController::class, 'destroy']);
    Route::post('/screens/{screen}/pairing-code', [ScreenController::class, 'regeneratePairingCode']);

    Route::get('/media', [MediaController::class, 'index']);
    Route::post('/media', [MediaController::class, 'store']);
    Route::delete('/media/{mediaAsset}', [MediaController::class, 'destroy']);

    Route::get('/playlists', [PlaylistController::class, 'index']);
    Route::post('/playlists', [PlaylistController::class, 'store']);
    Route::put('/playlists/{playlist}', [PlaylistController::class, 'update']);
    Route::put('/playlists/{playlist}/items', [PlaylistController::class, 'syncItems']);
    Route::post('/playlists/{playlist}/publish', [PlaylistController::class, 'publish']);
    Route::delete('/playlists/{playlist}', [PlaylistController::class, 'destroy']);
});
