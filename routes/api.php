<?php

use App\Http\Controllers\Api\N8nController;
use Illuminate\Support\Facades\Route;

// n8n WhatsApp workflow (token-protected, no session or CSRF).
Route::prefix('webhooks/n8n')->middleware(['n8n.token', 'throttle:120,1'])->group(function () {
    Route::post('/replies', [N8nController::class, 'replies']);
    Route::get('/followups/due', [N8nController::class, 'due']);
    Route::post('/followups/{schedule}/result', [N8nController::class, 'result'])->whereNumber('schedule');
});
