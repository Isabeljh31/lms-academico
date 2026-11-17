<?php

use App\Http\Controllers\SustentoReprogramacionController;
use App\Livewire\Admin\Comunicados\Index as ComunicadosIndex;
use App\Livewire\Admin\Metricas\Index as AnaliticaRiesgo;
use App\Livewire\Admin\Profesores\ImportarCsv as ProfesoresImportarCsv;
use App\Livewire\Admin\Profesores\Index as ProfesoresIndex;
use App\Livewire\Admin\Profesores\Show as ProfesoresShow;
use App\Livewire\Admin\SedesSalones\Index as SedesSalonesIndex;
use App\Livewire\Admin\SolicitudesCambioClase\Index as SolicitudesCambioClaseIndex;
use App\Livewire\PanelAdmin\Auditoria;
use App\Livewire\PanelAdmin\Configuracion;
use App\Livewire\PanelAdmin\Integraciones;
use App\Livewire\PanelAdmin\Metricas;
use App\Livewire\Usuarios\Index;
use App\Livewire\Usuarios\Show as UsuariosShow;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:supervisor'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('profesores', ProfesoresIndex::class)->name('profesores.index');
        Route::get('profesores/importar', ProfesoresImportarCsv::class)->name('profesores.importar');
        Route::get('profesores/{profesor}', ProfesoresShow::class)->name('profesores.show');
        Route::get('comunicados', ComunicadosIndex::class)->name('comunicados.index');
        // Solicitudes de cambio de clase de los profesores (extensión fuera del ERS)
        Route::get('solicitudes-cambio-clase', SolicitudesCambioClaseIndex::class)->name('solicitudes-cambio-clase.index');
        // Catálogo de sedes, tipos de salón y salones para reprogramar clases
        Route::get('sedes-salones', SedesSalonesIndex::class)->name('sedes-salones.index');
        Route::get('usuarios', Index::class)->name('usuarios.index');
        Route::get('usuarios/{estudiante}', UsuariosShow::class)->name('usuarios.show');
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

// Descarga del sustento de una reprogramación: el Supervisor o el Profesor dueño
// (SolicitudCambioClasePolicy::verSustento), vía enlace firmado que vence.
Route::get('reprogramaciones/{solicitud}/sustento', SustentoReprogramacionController::class)
    ->middleware(['auth', 'verified'])
    ->name('reprogramaciones.sustento');
