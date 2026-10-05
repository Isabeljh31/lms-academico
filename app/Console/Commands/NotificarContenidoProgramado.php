<?php

namespace App\Console\Commands;

use App\Models\Curso;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\Quiz;
use App\Models\Tarea;
use App\Models\User;
use App\Notifications\NuevaTareaAsignada;
use App\Notifications\NuevoContenidoPublicado;
use App\Notifications\NuevoQuizPublicado;
use App\Services\ConfiguracionPlataforma;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification as Notificacion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

/**
 * ERS RF-84 (nuevo contenido, nueva tarea, nuevo quiz) y RF-37: el contenido
 * Programado se vuelve visible solo al llegar su fecha (sin intervención del
 * Profesor), así que este comando envía el aviso que en una publicación
 * manual se envía al momento. Cada elemento se avisa una sola vez
 * (aviso_programado_en). Se ejecuta cada cinco minutos (routes/console.php).
 */
class NotificarContenidoProgramado extends Command
{
    protected $signature = 'contenido:notificar-programado';

    protected $description = 'Avisa a los estudiantes del contenido programado que ya se publicó';

    public function handle(ConfiguracionPlataforma $configuracion): int
    {
        $configuracion->aplicarZonaHoraria();

        $enviados = 0;

        $modulos = Modulo::query()->with('curso')
            ->where('estado_contenido', 'programado')->where('publicar_en', '<=', now())->whereNull('aviso_programado_en')->get();

        foreach ($modulos as $modulo) {
            $enviados += $this->avisar($modulo, $modulo->curso, $modulo->esVisibleParaEstudiante(), fn () => new NuevoContenidoPublicado($modulo));
        }

        $lecciones = Leccion::query()->with('modulo.curso')
            ->where('estado', 'programado')->where('fecha_publicacion', '<=', now())->whereNull('aviso_programado_en')->get();

        foreach ($lecciones as $leccion) {
            $visible = $leccion->esVisibleParaEstudiante() && $leccion->modulo?->esVisibleParaEstudiante();
            $enviados += $this->avisar($leccion, $leccion->modulo?->curso, (bool) $visible, fn () => new NuevoContenidoPublicado($leccion));
        }

        $tareas = Tarea::query()->with('curso', 'modulo')
            ->where('estado_contenido', 'programado')->where('publicar_en', '<=', now())->whereNull('aviso_programado_en')->get();

        foreach ($tareas as $tarea) {
            $enviados += $this->avisar($tarea, $tarea->curso, $tarea->esVisibleParaEstudiante(), fn () => new NuevaTareaAsignada($tarea));
        }

        $quizzes = Quiz::query()->with('curso', 'modulo')
            ->where('estado_contenido', 'programado')->where('publicar_en', '<=', now())->whereNull('aviso_programado_en')->get();

        foreach ($quizzes as $quiz) {
            $enviados += $this->avisar($quiz, $quiz->curso, $quiz->esVisibleParaEstudiante(), fn () => new NuevoQuizPublicado($quiz));
        }

        $this->info("Avisos de contenido programado enviados: {$enviados}");

        return self::SUCCESS;
    }

    /**
     * Si el elemento todavía no es visible (curso en borrador, módulo o tarea
     * ocultos), no se marca: se avisará cuando lo sea.
     *
     * @param  callable(): Notificacion  $notificacion
     */
    protected function avisar(Model $contenido, ?Curso $curso, bool $visible, callable $notificacion): int
    {
        if (! $curso || ! $visible || ! $curso->esVisibleParaEstudiantes()) {
            return 0;
        }

        $destinatarios = $this->estudiantesQuePuedenVer($curso, $contenido);

        if ($destinatarios->isNotEmpty()) {
            Notification::send($destinatarios, $notificacion());
        }

        $contenido->forceFill(['aviso_programado_en' => now()])->saveQuietly();

        return 1;
    }

    /**
     * Estudiantes con matrícula activa. En tareas y quizzes se respeta además
     * la política de visibilidad (p. ej. tareas grupales de un grupo concreto).
     *
     * @return Collection<int, User>
     */
    protected function estudiantesQuePuedenVer(Curso $curso, Model $contenido): Collection
    {
        $estudiantes = $curso->inscripcionesActivas()->with('estudiante')->get()
            ->pluck('estudiante')->filter(fn (?User $estudiante) => $estudiante?->activo);

        if ($contenido instanceof Tarea || $contenido instanceof Quiz) {
            return $estudiantes->filter(fn (User $estudiante) => Gate::forUser($estudiante)->allows('ver', $contenido))->values();
        }

        return $estudiantes->values();
    }
}
