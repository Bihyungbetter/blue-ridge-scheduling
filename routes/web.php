<?php

use App\Http\Controllers\AgentTestController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('appointments', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');

    Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');

    Route::get('agent-test', [AgentTestController::class, 'show'])->name('agent-test');
    // Each one costs money and reaches a real phone: keep it slow.
    Route::middleware('throttle:6,1')->group(function () {
        Route::post('agent-test/call', [AgentTestController::class, 'call'])->name('agent-test.call');
        Route::post('agent-test/text', [AgentTestController::class, 'text'])->name('agent-test.text');
        Route::post('agent-test/web-call', [AgentTestController::class, 'webCall'])->name('agent-test.web-call');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
