<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\TenantAssetController;

Route::get('/', function () {
    return view('welcome');
});

// Tenant Assets Route
Route::get('/tenant-assets/{tenantId}/{path}', [TenantAssetController::class, 'show'])->where('path', '.*');
