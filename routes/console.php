<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Módulo 7: detecta atrasos y genera propuestas pendientes en propuestas_bot
// todos los días. Nunca ejecuta cambios en el calendario por sí sola.
Schedule::command('bot:detectar-atrasos')->dailyAt('06:00');

// ERS RF-87: publica y notifica los comunicados programados.
Schedule::command('comunicados:publicar')->everyFiveMinutes();

// ERS RF-84: avisa del contenido programado (módulos, lecciones, tareas, quizzes) al publicarse.
Schedule::command('contenido:notificar-programado')->everyFiveMinutes();
