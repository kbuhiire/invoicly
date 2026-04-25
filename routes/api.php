<?php

use Illuminate\Support\Facades\Route;

/*
 * API routes are registered in bootstrap/app.php with the 'api' prefix
 * and the 'api' middleware group applied automatically.
 * Versioned endpoints live under /api/v1/.
 */
Route::prefix('v1')->group(base_path('routes/api_v1.php'));
