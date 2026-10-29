<?php

use App\Http\Controllers\Reportes\BoletaPdfController;
use App\Livewire\Compartido\Reportes\Dashboard;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->name('reportes.')
    ->group(function () {
        Route::get('/reportes', Dashboard::class)->name('dashboard');
        Route::get('/cursos/{curso}/boleta.pdf', BoletaPdfController::class)->name('boleta');
    });
