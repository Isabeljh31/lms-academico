<?php

use App\Livewire\Admin\Comunicados\Index as ComunicadosIndex;
use App\Livewire\Admin\Profesores\Index as ProfesoresIndex;
use App\Livewire\Cursos\EsquemaEvaluacion;
use App\Livewire\PanelAdmin\Auditoria;
use App\Livewire\PanelAdmin\Configuracion;
use App\Livewire\PanelAdmin\Metricas;
use App\Livewire\Usuarios\Index;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:supervisor'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('profesores', ProfesoresIndex::class)->name('profesores.index');
        Route::get('comunicados', ComunicadosIndex::class)->name('comunicados.index');
        Route::get('usuarios', Index::class)->name('usuarios.index');
        Route::get('metricas', Metricas::class)->name('metricas');
        Route::get('configuracion', Configuracion::class)->name('configuracion');
        // ERS RF-97: bitácora de auditoría (solo Supervisor)
        Route::get('auditoria', Auditoria::class)->name('auditoria');
    });
