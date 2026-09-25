<?php

use App\Livewire\Cursos\Formulario;
use App\Livewire\Cursos\Index;
use App\Livewire\Cursos\LibroCalificaciones;
use App\Livewire\Cursos\Show;
use App\Livewire\Grupos\Index as GruposIndex;
use App\Livewire\Quizzes\Estadisticas;
use App\Livewire\Quizzes\Resolver;
use App\Livewire\Tareas\Detalle;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('cursos')
    ->name('cursos.')
    ->group(function () {
        Route::get('/', Index::class)->name('index');
        Route::get('/crear', Formulario::class)->name('crear');
        Route::get('/{curso}/editar', Formulario::class)->name('editar');

        Route::prefix('{curso}/tareas')
            ->name('tareas.')
            ->scopeBindings()
            ->group(function () {
                Route::get('/', App\Livewire\Tareas\Index::class)->name('index');
                Route::get('/crear', App\Livewire\Tareas\Formulario::class)->name('crear');
                Route::get('/{tarea}/editar', App\Livewire\Tareas\Formulario::class)->name('editar');
                Route::get('/{tarea}', Detalle::class)->name('show');
            });

        Route::prefix('{curso}/quizzes')
            ->name('quizzes.')
            ->scopeBindings()
            ->group(function () {
                Route::get('/', App\Livewire\Quizzes\Index::class)->name('index');
                Route::get('/crear', App\Livewire\Quizzes\Formulario::class)->name('crear');
                Route::get('/{quiz}/editar', App\Livewire\Quizzes\Formulario::class)->name('editar');
                Route::get('/{quiz}/estadisticas', Estadisticas::class)->name('estadisticas');
                Route::get('/{quiz}', Resolver::class)->name('show');
            });

        Route::get('/{curso}/grupos', GruposIndex::class)->name('grupos.index');
        Route::get('/{curso}/libro-calificaciones', LibroCalificaciones::class)->name('libro-calificaciones');
        Route::get('/{curso}', Show::class)->name('show');
    });
