<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClaimController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DevicesController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);

Route::post('/device/pair', [DeviceController::class, 'pair']);
Route::post('/claims', [ClaimController::class, 'store']);
Route::post('/claims/{code}/report', [ClaimController::class, 'report']);
Route::get('/claims/{code}/playback', [ClaimController::class, 'playback']);
Route::get('/claims/{code}/role', [ClaimController::class, 'role']);
Route::get('/claims/{code}/items/{publicationItem}/file', [ClaimController::class, 'playbackFile']);
Route::get('/claims/{code}', [ClaimController::class, 'show']);
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

    Route::get('/branches', [BranchController::class, 'index']);
    Route::post('/branches', [BranchController::class, 'store']);
    Route::put('/branches/{branch}', [BranchController::class, 'update']);
    Route::delete('/branches/{branch}', [BranchController::class, 'destroy']);

    Route::get('/devices', [DevicesController::class, 'index']);
    Route::post('/devices', [DevicesController::class, 'store']);
    Route::put('/devices/{device}', [DevicesController::class, 'update']);
    Route::delete('/devices/{device}', [DevicesController::class, 'destroy']);
    Route::post('/devices/{device}/pairing-code', [DevicesController::class, 'regeneratePairingCode']);
    Route::post('/devices/{device}/release', [DevicesController::class, 'release']);
    Route::post('/claims/{code}/accept', [ClaimController::class, 'accept']);

    Route::get('/playlists', [PlaylistController::class, 'index']);
    Route::post('/playlists', [PlaylistController::class, 'store']);
    Route::put('/playlists/{playlist}', [PlaylistController::class, 'update']);
    Route::post('/playlists/{playlist}/items', [PlaylistController::class, 'addItem']);
    Route::put('/playlists/{playlist}/items', [PlaylistController::class, 'syncItems']);
    Route::post('/playlists/{playlist}/publish', [PlaylistController::class, 'publish']);
    Route::delete('/playlists/{playlist}', [PlaylistController::class, 'destroy']);
});
