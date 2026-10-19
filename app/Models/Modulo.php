<?php

namespace App\Models;

use App\Models\Concerns\TieneEstadoPublicacion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ERS RF-34: sección de contenido de un Curso.
 *
 * El contenido se organiza por semanas: cada semana del curso (1..N, N =
 * `cursos.semanas_duracion`) es un módulo con `semana` = número de semana y
 * `titulo` = tema de la semana. Además existe una sección "Prácticas de
 * campo" (`tipo_seccion` = practicas_campo, sin semana) para actividades que
 * no siguen la numeración semanal; se muestra antes de las semanas.
 *
 * Las semanas de contenido no son las Sesiones de Clase (RF-40): una sesión
 * es un encuentro con fecha y asistencia; la semana agrupa material. Se
 * relacionan solo por fecha (Curso::rangoSemana), sin clave entre ambas.
 */
class Modulo extends Model
{
    use HasFactory, TieneEstadoPublicacion;

    protected $table = 'modulos';

    /**
     * ERS RF-67: condición que define el Profesor para dar el módulo por completado.
     *
     * @var array<string, string>
     */
    public const CONDICIONES = [
        'actividades' => 'Completar sus lecciones, entregar sus tareas y rendir sus quizzes',
        'lecciones' => 'Completar solo sus lecciones',
        'aprobacion' => 'Completar sus lecciones y aprobar sus tareas y quizzes (nota mínima)',
    ];

    public const TIPO_SEMANA = 'semana';

    public const TIPO_PRACTICAS = 'practicas_campo';

    protected $attributes = [
        'estado_contenido' => 'publicado',
        'condicion_completitud' => 'actividades',
        'tipo_seccion' => self::TIPO_SEMANA,
    ];

    protected $fillable = [
        'curso_id',
        'titulo',
        'orden',
        'materiales',
        'estado_contenido',
        'publicar_en',
        'condicion_completitud',
        'tipo_seccion',
        'semana',
    ];

    protected static function booted(): void
    {
        // Código que crea módulos sin indicar la semana (clonación, datos de
        // prueba): se ubican en la siguiente semana libre del curso. El orden
        // siempre sigue a la semana; las prácticas de campo van primero.
        static::creating(function (Modulo $modulo) {
            if ($modulo->esPracticas()) {
                $modulo->semana = null;
                $modulo->orden = 0;

                return;
            }

            $modulo->semana ??= ((int) static::where('curso_id', $modulo->curso_id)->max('semana')) + 1;
            $modulo->orden = $modulo->semana;
        });
    }

    public function esPracticas(): bool
    {
        return $this->tipo_seccion === self::TIPO_PRACTICAS;
    }

    /** "Semana 3" o "Prácticas de campo". */
    public function etiqueta(): string
    {
        return $this->esPracticas() ? 'Prácticas de campo' : 'Semana '.$this->semana;
    }

    /**
     * Tema de la semana, sin repetir la etiqueta ni el prefijo "Unidad N:" de
     * los cursos creados con el esquema anterior. Null si no tiene tema propio.
     */
    public function tema(): ?string
    {
        $tema = trim((string) preg_replace('/^(unidad|m[oó]dulo|semana)\s+\d+\s*[:.\-–]\s*/iu', '', (string) $this->titulo));

        return $tema === '' || mb_strtolower($tema) === mb_strtolower($this->etiqueta()) ? null : $tema;
    }

    /** "Semana 3: La derivada", para listas y selectores. */
    public function nombreCompleto(): string
    {
        return $this->etiqueta().($this->tema() ? ': '.$this->tema() : '');
    }

    protected function casts(): array
    {
        return [
            'materiales' => 'array',
            'publicar_en' => 'datetime',
        ];
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    public function tareas(): HasMany
    {
        return $this->hasMany(Tarea::class, 'modulo_id');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class, 'modulo_id');
    }

    public function lecciones(): HasMany
    {
        return $this->hasMany(Leccion::class, 'modulo_id')->orderBy('orden');
    }

    public function leccionesVisibles(): HasMany
    {
        return $this->lecciones()->publicadas();
    }

    /**
     * Un módulo con entregas o resultados de quiz no se elimina: borrarlo se
     * llevaría en cascada notas y entregas de los estudiantes (regla de
     * negocio 3, historial académico).
     */
    public function tieneHistorialAcademico(): bool
    {
        return Entrega::whereIn('tarea_id', $this->tareas()->select('id'))->exists()
            || ResultadoQuiz::whereIn('quiz_id', $this->quizzes()->select('id'))->exists();
    }
}
