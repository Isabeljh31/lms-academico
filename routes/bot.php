<?php

use App\Livewire\Estudiante\PropuestasBot;
use App\Livewire\Profesor\ResumenSemanal;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('bot')
    ->name('bot.')
    ->group(function () {
        Route::get('propuestas', PropuestasBot::class)
            ->middleware('role:estudiante')
            ->name('propuestas');

        Route::get('resumen', ResumenSemanal::class)
            ->middleware('role:profesor')
            ->name('resumen');
    });
