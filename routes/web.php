<?php

use App\Livewire\Actions\Logout;
use App\Livewire\Docente\PorCalificar\Index as PorCalificarIndex;
use App\Livewire\EventoCalendario\Index;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::post('logout', function (Request $request, Logout $logout) {
    $logout();

    return redirect('/');
})->middleware(['auth'])->name('logout');

Route::get('/evento-calendario', Index::class)
    ->middleware(['auth'])
    ->name('evento-calendario.index');

Route::get('/por-calificar', PorCalificarIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('docente.por-calificar');

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/cursos.php';
require __DIR__.'/bot.php';
require __DIR__.'/reportes.php';

// ERS RF-54: Esquema de Evaluacion por curso
Route::middleware(['auth', 'verified'])
    ->group(function () {
        Route::get('cursos/{curso}/esquema', App\Livewire\Cursos\EsquemaEvaluacion::class)
            ->name('cursos.esquema');
    });

// ERS RF-05: Cambio obligatorio de contraseña en primer login
Route::middleware(['auth', 'verified'])
    ->group(function () {
        Route::get('primer-login', App\Livewire\Auth\CambiarPasswordPrimerLogin::class)
            ->name('primer-login');
    });

// ERS RF-59, RF-63: Boleta de notas por rubro para el estudiante
Route::middleware(['auth', 'verified'])
    ->group(function () {
        Route::get('cursos/{curso}/boleta', App\Livewire\Cursos\BoletaEstudiante::class)
            ->name('cursos.boleta');
    });

// ERS RF-26, RF-29: Gestión de Matrícula (manual + baja lógica)
Route::middleware(['auth', 'verified'])
    ->group(function () {
        Route::get('cursos/{curso}/matriculas', App\Livewire\Cursos\GestionMatricula::class)
            ->name('cursos.matriculas');
    });

// ERS RF-11: Importar usuarios CSV
Route::middleware(['auth', 'verified'])
    ->group(function () {
        Route::get('usuarios/importar', App\Livewire\Usuarios\ImportarCSV::class)
            ->name('usuarios.importar');
    });

// ==================== FASE 2 ====================
Route::middleware(['auth', 'verified'])->group(function () {
    // Lecciones (RF-35)
    Route::get('modulos/{modulo}/lecciones', App\Livewire\Lecciones\Index::class)->name('lecciones.index');

    // Asistencia (RF-70)
    Route::get('cursos/{curso}/asistencia', App\Livewire\Asistencia\Index::class)->name('asistencia.index');

    // Mensajería (RF-80)
    Route::get('mensajeria', App\Livewire\Mensajeria\Bandeja::class)->name('mensajeria.bandeja');
});

    // Fase 3: Métricas y Riesgo
    Route::get('admin/metricas', App\Livewire\Admin\Metricas\Index::class)->name('admin.metricas');
