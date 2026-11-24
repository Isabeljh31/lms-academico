<?php

use App\Http\Controllers\SustentoReprogramacionController;
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
use App\Livewire\Admin\SedesSalones\Index as SedesSalonesIndex;
use App\Livewire\Admin\SolicitudesCambioClase\Index as SolicitudesCambioClaseIndex;
use Illuminate\Support\Facades\Route;

// Rutas con prefijo "admin." — así las tenía la rama de José, y así las siguen
// llamando muchas de sus vistas y tests (route('admin.profesores.index'), etc.).
// Se mantiene este nombre/prefijo tal cual para no tener que tocar esos
// archivos; "supervisor.*" (más abajo) son los mismos componentes con el
// nombre que usa el resto del refactor por roles.
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
        Route::get('usuarios', EstudiantesIndex::class)->name('usuarios.index');
        Route::get('usuarios/{estudiante}', EstudiantesShow::class)->name('usuarios.show');
        // ERS RF-90/RF-91: dashboard institucional y reportes administrativos
        Route::get('metricas', DashboardInstitucional::class)->name('metricas');
        // ERS 11.3: analítica de riesgo de abandono (Fase 3)
        Route::get('analitica-riesgo', RiesgoAbandono::class)->name('analitica-riesgo');
        // ERS RF-107/RF-108: configuración general
        Route::get('configuracion', Configuracion::class)->name('configuracion');
        // ERS RF-100 a RF-106 y RNF-04: integración externa (API Keys)
        Route::get('integraciones', Integraciones::class)->name('integraciones');
        // ERS RF-97: bitácora de auditoría (solo Supervisor)
        Route::get('auditoria', Auditoria::class)->name('auditoria');
    });

// Alias "supervisor.*" de los mismos componentes de arriba, para el código del
// refactor por roles que ya llama a las rutas con este nombre.
Route::middleware(['auth', 'verified', 'role:supervisor'])
    ->prefix('supervisor')
    ->name('supervisor.')
    ->group(function () {
        Route::get('profesores', ProfesoresIndex::class)->name('profesores.index');
        Route::get('profesores/importar', ProfesoresImportarCsv::class)->name('profesores.importar');
        Route::get('profesores/{profesor}', ProfesoresShow::class)->name('profesores.show');
        Route::get('comunicados', ComunicadosIndex::class)->name('comunicados.index');
        Route::get('estudiantes', EstudiantesIndex::class)->name('estudiantes.index');
        Route::get('estudiantes/importar', EstudiantesImportarCsv::class)->name('estudiantes.importar');
        Route::get('estudiantes/{estudiante}', EstudiantesShow::class)->name('estudiantes.show');
        Route::get('dashboard-institucional', DashboardInstitucional::class)->name('dashboard-institucional');
        Route::get('riesgo-abandono', RiesgoAbandono::class)->name('riesgo-abandono');
        Route::get('configuracion', Configuracion::class)->name('configuracion');
        Route::get('integraciones', Integraciones::class)->name('integraciones');
        Route::get('auditoria', Auditoria::class)->name('auditoria');
    });

// Descarga del sustento de una reprogramación: el Supervisor o el Profesor dueño
// (SolicitudCambioClasePolicy::verSustento), vía enlace firmado que vence.
Route::get('reprogramaciones/{solicitud}/sustento', SustentoReprogramacionController::class)
    ->middleware(['auth', 'verified'])
    ->name('reprogramaciones.sustento');
