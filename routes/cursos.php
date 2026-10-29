<?php

use App\Http\Controllers\RecursoLeccionController;
use App\Livewire\Compartido\Cursos\Index;
use App\Livewire\Compartido\Cursos\LibroCalificaciones;
use App\Livewire\Compartido\Cursos\Show;
use App\Livewire\Compartido\Debates\Index as DebatesIndex;
use App\Livewire\Compartido\Debates\Show as DebatesShow;
use App\Livewire\Compartido\Grupos\Index as GruposIndex;
use App\Livewire\Compartido\Mensajeria\Bandeja;
use App\Livewire\Compartido\Quizzes\Estadisticas;
use App\Livewire\Compartido\Quizzes\Resolver;
use App\Livewire\Compartido\Tareas\Detalle;
use App\Livewire\Estudiante\MiAsistencia;
use App\Livewire\Profesor\Actividades;
use App\Livewire\Profesor\BancoPreguntas;
use App\Livewire\Supervisor\Cursos\Formulario;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('cursos')
    ->name('cursos.')
    ->group(function () {
        Route::get('/', Index::class)->name('index');
        Route::get('/crear', Formulario::class)->middleware('role:supervisor')->name('crear');
        Route::get('/{curso}/editar', Formulario::class)->middleware('role:supervisor')->name('editar');

        Route::prefix('{curso}/tareas')
            ->name('tareas.')
            ->scopeBindings()
            ->group(function () {
                Route::get('/', App\Livewire\Compartido\Tareas\Index::class)->name('index');
                Route::get('/crear', App\Livewire\Compartido\Tareas\Formulario::class)->name('crear');
                Route::get('/{tarea}/editar', App\Livewire\Compartido\Tareas\Formulario::class)->name('editar');
                Route::get('/{tarea}', Detalle::class)->name('show');
            });

        Route::prefix('{curso}/quizzes')
            ->name('quizzes.')
            ->scopeBindings()
            ->group(function () {
                Route::get('/', App\Livewire\Compartido\Quizzes\Index::class)->name('index');
                Route::get('/crear', App\Livewire\Compartido\Quizzes\Formulario::class)->name('crear');
                Route::get('/{quiz}/editar', App\Livewire\Compartido\Quizzes\Formulario::class)->name('editar');
                Route::get('/{quiz}/estadisticas', Estadisticas::class)->name('estadisticas');
                Route::get('/{quiz}', Resolver::class)->name('show');
            });

        // Actividades del Profesor del curso: tareas, quizzes y banco de preguntas en un solo apartado.
        Route::get('/{curso}/actividades', Actividades::class)
            ->middleware('role:profesor')
            ->name('actividades');
        // ERS RF-47: banco de preguntas por tema (Profesor del curso)
        Route::get('/{curso}/banco-preguntas', BancoPreguntas::class)
            ->middleware('role:profesor')
            ->name('banco-preguntas');
        // ERS RF-72: historial de asistencia del propio estudiante
        Route::get('/{curso}/mi-asistencia', MiAsistencia::class)
            ->middleware('role:estudiante')
            ->name('mi-asistencia');
        Route::get('/{curso}/grupos', GruposIndex::class)
            ->middleware('role:profesor|supervisor')
            ->name('grupos.index');
        Route::get('/{curso}/libro-calificaciones', LibroCalificaciones::class)
            ->middleware('role:profesor|supervisor')
            ->name('libro-calificaciones');
        // ERS RF-80 a RF-83: mensajería del curso (la matriz de permisos excluye al Supervisor)
        Route::get('/{curso}/mensajeria', Bandeja::class)
            ->middleware('role:estudiante|profesor')
            ->name('mensajeria');
        // ERS RF-36/RF-67: abre el video o material de una lección (y la completa si así se configuró).
        Route::get('/{curso}/lecciones/{leccion}/{recurso}', RecursoLeccionController::class)
            ->whereIn('recurso', ['video', 'material'])
            ->name('lecciones.recurso');
        // Debates del curso (extensión fuera del ERS): los permisos están en DebatePolicy.
        Route::get('/{curso}/debates', DebatesIndex::class)->name('debates.index');
        Route::get('/{curso}/debates/{debate}', DebatesShow::class)
            ->scopeBindings()
            ->name('debates.show');
        Route::get('/{curso}', Show::class)->name('show');
    });
