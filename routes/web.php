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
