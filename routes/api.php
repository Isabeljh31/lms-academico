<?php

use App\Http\Controllers\Api\MatriculaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — ERS LearnUp v2
|--------------------------------------------------------------------------
| ERS RF-100: endpoint de integración externa para sincronizar matrículas.
| Autenticación: Bearer <api_key> — middleware ValidarApiKey (RF-101, RF-102).
*/

Route::prefix('v1')
    ->middleware(App\Http\Middleware\ValidarApiKey::class)
    ->group(function () {
        // ERS RF-100: sincronizar matrículas desde sistema externo (SIS/ERP)
        Route::post('/matriculas', [MatriculaController::class, 'sincronizar']);
    });
