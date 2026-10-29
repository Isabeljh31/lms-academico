<?php

use App\Http\Controllers\LeccionesSemanaController;
use App\Livewire\Actions\Logout;
use App\Livewire\Auth\CambiarPasswordPrimerLogin;
use App\Livewire\Cursos\GestionMatricula;
use App\Livewire\Estudiante\BoletaCurso;
use App\Livewire\EventoCalendario\Index;
use App\Livewire\Mensajeria\Selector as MensajeriaSelector;
use App\Livewire\Profesor\PorCalificar\Index as PorCalificarIndex;
use App\Livewire\Supervisor\Cursos\EsquemaEvaluacion;
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

// ERS RF-78/RF-79: calendario propio de cada usuario
Route::get('/evento-calendario', Index::class)
    ->middleware(['auth', 'verified'])
    ->name('evento-calendario.index');

// ERS RF-49: bandeja de entregas por calificar del profesor
Route::get('/por-calificar', PorCalificarIndex::class)
    ->middleware(['auth', 'verified', 'role:profesor'])
    ->name('profesor.por-calificar');

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/cursos.php';
require __DIR__.'/bot.php';
require __DIR__.'/reportes.php';

// ERS RF-05: cambio obligatorio de contraseña en el primer ingreso
Route::get('primer-login', CambiarPasswordPrimerLogin::class)
    ->middleware(['auth', 'verified'])
    ->name('primer-login');

Route::middleware(['auth', 'verified'])->group(function () {
    // ERS RF-54 a RF-56, RF-62: esquema de evaluación (solo Supervisor)
    Route::get('cursos/{curso}/esquema', EsquemaEvaluacion::class)
        ->middleware('role:supervisor')
        ->name('cursos.esquema');

    // ERS RF-59: boleta de notas por rubro del estudiante
    Route::get('cursos/{curso}/boleta', BoletaCurso::class)
        ->name('cursos.boleta');

    // ERS RF-26 a RF-33: matrícula del curso (el Profesor solo consulta)
    Route::get('cursos/{curso}/matriculas', GestionMatricula::class)
        ->middleware('role:supervisor|profesor')
        ->name('cursos.matriculas');

    // Compatibilidad: la antigua pantalla de lecciones de una semana lleva a la pantalla del curso,
    // donde ahora se gestiona el contenido (ERS RF-35 a RF-38).
    Route::get('modulos/{modulo}/lecciones', LeccionesSemanaController::class)
        ->middleware('role:profesor')
        ->name('lecciones.index');

    // ERS RF-70, RF-71, RF-73: registro de asistencia (Profesor del curso)
    Route::get('cursos/{curso}/asistencia', App\Livewire\Asistencia\Index::class)
        ->middleware('role:profesor|supervisor')
        ->name('asistencia.index');

    // ERS RF-80 a RF-83: selector de curso para entrar a su mensajería
    Route::get('mensajeria', MensajeriaSelector::class)
        ->middleware('role:estudiante|profesor')
        ->name('mensajeria.selector');
});
