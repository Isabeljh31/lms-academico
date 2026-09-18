<?php

use App\Livewire\BotSugerencias\Bandeja;
use App\Livewire\BotSugerencias\ResumenSemanal;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('bot')
    ->name('bot.')
    ->group(function () {
        Route::get('propuestas', Bandeja::class)
            ->middleware('role:estudiante')
            ->name('propuestas');

        Route::get('resumen', ResumenSemanal::class)
            ->middleware('role:profesor')
            ->name('resumen');
    });
