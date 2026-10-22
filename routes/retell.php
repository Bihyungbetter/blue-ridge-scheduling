<?php

use App\Http\Controllers\Retell\FunctionCallController;
use App\Http\Controllers\Retell\InboundController;
use App\Http\Controllers\Retell\WebhookController;
use Illuminate\Support\Facades\Route;

// Registered in bootstrap/app.php under /retell with VerifyRetellSignature
// and without the web group (no sessions, CSRF or Inertia).

Route::post('functions', FunctionCallController::class)->name('functions');
Route::post('webhook', WebhookController::class)->name('webhook');
Route::post('inbound', InboundController::class)->name('inbound');
