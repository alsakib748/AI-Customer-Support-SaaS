<?php

use App\Http\Controllers\Api\V1\TenantAssetController;
use App\Http\Controllers\Public\WidgetController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Tenant Assets Route
Route::get('/tenant-assets/{tenantId}/{path}', [TenantAssetController::class, 'show'])->where('path', '.*');

// Widget Routes (No Authentication)
Route::get('/widget/chat.js', function () {
    return response()->file(public_path('widget/chat.js'), [
        'Content-Type' => 'application/javascript',
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->name('widget.script');

Route::get('/widget/app', function () {
    return response()->file(public_path('widget/index.html'), [
        'Content-Type' => 'text/html',
        'Cache-Control' => 'no-cache',
    ]);
})->name('widget.app');

Route::get('/widget/embed', [WidgetController::class, 'embed'])->name('widget.embed');