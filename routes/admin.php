<?php

use App\Livewire\Supervisor\Auditoria;
use App\Livewire\Supervisor\Comunicados\Index as ComunicadosIndex;
use App\Livewire\Supervisor\Configuracion;
use App\Livewire\Supervisor\DashboardInstitucional\Index as DashboardInstitucional;
use App\Livewire\Supervisor\Estudiantes\ImportarCSV as EstudiantesImportarCsv;
use App\Livewire\Supervisor\Estudiantes\Index as EstudiantesIndex;
use App\Livewire\Supervisor\Estudiantes\Show as EstudiantesShow;
use App\Livewire\Supervisor\Integraciones;
use App\Livewire\Supervisor\Profesores\ImportarCsv as ProfesoresImportarCsv;
use App\Livewire\Supervisor\Profesores\Index as ProfesoresIndex;
use App\Livewire\Supervisor\Profesores\Show as ProfesoresShow;
use App\Livewire\Supervisor\RiesgoAbandono\Index as RiesgoAbandono;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:supervisor'])
    ->prefix('supervisor')
    ->name('supervisor.')
    ->group(function () {
        Route::get('profesores', ProfesoresIndex::class)->name('profesores.index');
        Route::get('profesores/importar', ProfesoresImportarCsv::class)->name('profesores.importar');
        Route::get('profesores/{profesor}', ProfesoresShow::class)->name('profesores.show');
        Route::get('comunicados', ComunicadosIndex::class)->name('comunicados.index');
        Route::get('estudiantes', EstudiantesIndex::class)->name('estudiantes.index');
        // ERS RF-11 a RF-13: importación masiva de usuarios (solo Supervisor)
        Route::get('estudiantes/importar', EstudiantesImportarCsv::class)->name('estudiantes.importar');
        Route::get('estudiantes/{estudiante}', EstudiantesShow::class)->name('estudiantes.show');
        // ERS RF-90/RF-91: dashboard institucional y reportes administrativos
        Route::get('dashboard-institucional', DashboardInstitucional::class)->name('dashboard-institucional');
        // ERS 11.3: analítica de riesgo de abandono (Fase 3)
        Route::get('riesgo-abandono', RiesgoAbandono::class)->name('riesgo-abandono');
        // ERS RF-107/RF-108: configuración general
        Route::get('configuracion', Configuracion::class)->name('configuracion');
        // ERS RF-100 a RF-106 y RNF-04: integración externa (API Keys)
        Route::get('integraciones', Integraciones::class)->name('integraciones');
        // ERS RF-97: bitácora de auditoría (solo Supervisor)
        Route::get('auditoria', Auditoria::class)->name('auditoria');
    });
