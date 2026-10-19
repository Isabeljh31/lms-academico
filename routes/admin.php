<?php

use App\Livewire\Admin\Comunicados\Index as ComunicadosIndex;
use App\Livewire\Admin\Metricas\Index as AnaliticaRiesgo;
use App\Livewire\Admin\Profesores\Index as ProfesoresIndex;
use App\Livewire\PanelAdmin\Auditoria;
use App\Livewire\PanelAdmin\Configuracion;
use App\Livewire\PanelAdmin\Integraciones;
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
        // ERS RF-90/RF-91: dashboard institucional y reportes administrativos
        Route::get('metricas', Metricas::class)->name('metricas');
        // ERS 11.3: analítica de riesgo de abandono (Fase 3)
        Route::get('analitica-riesgo', AnaliticaRiesgo::class)->name('analitica-riesgo');
        // ERS RF-107/RF-108: configuración general
        Route::get('configuracion', Configuracion::class)->name('configuracion');
        // ERS RF-100 a RF-106 y RNF-04: integración externa (API Keys)
        Route::get('integraciones', Integraciones::class)->name('integraciones');
        // ERS RF-97: bitácora de auditoría (solo Supervisor)
        Route::get('auditoria', Auditoria::class)->name('auditoria');
    });
