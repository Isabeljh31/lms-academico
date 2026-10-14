<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Curso del ERS (equivale a "Oferta de Curso" en versiones previas del documento).
 */
class Curso extends Model
{
    use HasFactory;

    protected $table = 'cursos';

    // estados: borrador | publicado | despublicado | archivado
    const ESTADOS = ['borrador', 'publicado', 'despublicado', 'archivado'];

    /** Estados en los que el estudiante matriculado puede entrar al curso. */
    const ESTADOS_VISIBLES_ESTUDIANTE = ['publicado', 'archivado'];

    /** Formato del código: letras A-Z y números, en bloques separados por un guion (p. ej. MAT-201). */
    const FORMATO_CODIGO = '/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/';

    /** Duración típica de un ciclo académico, usada si el Supervisor aún no definió la del curso. */
    const SEMANAS_PREDETERMINADAS = 16;

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
        'profesor_id',
        'imagen_portada_url',
        // ERS RF-22: estado de publicacion
        'estado',
        // ERS RF-19: fechas, duracion y sesiones
        'fecha_inicio',
        'fecha_fin',
        'semanas_duracion',
        'sesiones_clase_count',
        // ERS RF-73: asistencia opcional
        'usa_asistencia',
    ];

    protected $attributes = [
        'estado' => 'borrador',
        'usa_asistencia' => true,
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'usa_asistencia' => 'boolean',
        ];
    }

    /**
     * El código se guarda siempre en mayúsculas y sin espacios en los extremos,
     * venga de donde venga (formulario, clonación, API o seeders), para que
     * "mat-201" y "MAT-201" sean el mismo curso. Vacío se guarda como null.
     */
    protected function codigo(): Attribute
    {
        return Attribute::make(
            set: fn (?string $valor) => self::normalizarCodigo($valor),
        );
    }

    public static function normalizarCodigo(?string $codigo): ?string
    {
        $codigo = mb_strtoupper(trim((string) $codigo));

        return $codigo === '' ? null : $codigo;
    }

    // ==========================================
    // HELPERS DE ESTADO (ERS RF-22, RNF-14)
    // ==========================================

    public function estaPublicado(): bool
    {
        return $this->estado === 'publicado';
    }

    /**
     * Un curso finalizado es el que el Supervisor archivó. Desde ese momento
     * no admite cambios retroactivos (regla de negocio 8, RF-62, RF-65, RNF-14).
     */
    public function estaFinalizado(): bool
    {
        return $this->estado === 'archivado';
    }

    public function esVisibleParaEstudiantes(): bool
    {
        return in_array($this->estado, self::ESTADOS_VISIBLES_ESTUDIANTE, true);
    }

    /** Cursos que dicta el usuario, como titular o como profesor adicional. */
    public function scopeDictadosPor(Builder $query, User|int $usuario): Builder
    {
        $id = $usuario instanceof User ? $usuario->id : $usuario;

        return $query->where(fn (Builder $q) => $q
            ->where('profesor_id', $id)
            ->orWhereHas('profesoresAdicionales', fn (Builder $docentes) => $docentes->whereKey($id)));
    }

    public function scopeVisiblesParaEstudiantes(Builder $query): Builder
    {
        return $query->whereIn('estado', self::ESTADOS_VISIBLES_ESTUDIANTE);
    }

    // ==========================================
    // RELACIONES
    // ==========================================

    public function profesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesor_id');
    }

    /**
     * ERS RF-23: profesores adicionales (co-docentes). El titular sigue en profesor_id.
     */
    public function profesoresAdicionales(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'curso_profesor', 'curso_id', 'profesor_id')->withTimestamps();
    }

    /** Titular o profesor adicional del curso. */
    public function esDocente(User|int|null $usuario): bool
    {
        $id = $usuario instanceof User ? $usuario->id : $usuario;

        if ($id === null) {
            return false;
        }

        if ($this->profesor_id === $id) {
            return true;
        }

        return $this->relationLoaded('profesoresAdicionales')
            ? $this->profesoresAdicionales->contains('id', $id)
            : $this->profesoresAdicionales()->whereKey($id)->exists();
    }

    /**
     * Todos los docentes del curso (titular primero).
     *
     * @return Collection<int, User>
     */
    public function docentes(): Collection
    {
        return collect([$this->profesor])->filter()->concat($this->profesoresAdicionales)->unique('id')->values();
    }

    public function modulos(): HasMany
    {
        return $this->hasMany(Modulo::class, 'curso_id')->orderBy('orden');
    }

    /**
     * Semanas en que se organiza el contenido: la duración que definió el
     * Supervisor (16 si aún no la definió), o más si hay contenido ubicado
     * después (cursos migrados del esquema de Unidades).
     */
    public function totalSemanas(): int
    {
        return max(
            (int) ($this->semanas_duracion ?: self::SEMANAS_PREDETERMINADAS),
            (int) $this->modulos()->max('semana'),
        );
    }

    /**
     * Lunes y domingo de la semana N del curso (null si el curso no tiene fecha
     * de inicio). Mismo criterio que el Calendario y el esquema de evaluación.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public function rangoSemana(int $semana): ?array
    {
        if (! $this->fecha_inicio) {
            return null;
        }

        $inicio = $this->fecha_inicio->copy()->startOfWeek()->addWeeks($semana - 1);

        return [$inicio, $inicio->copy()->endOfWeek()];
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'curso_id');
    }

    /** Solo inscripciones activas (no dadas de baja y con acceso local). */
    public function inscripcionesActivas(): HasMany
    {
        return $this->inscripciones()->where('estado', 'activo')->where('activo_local', true);
    }

    public function tareas(): HasMany
    {
        return $this->hasMany(Tarea::class, 'curso_id');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class, 'curso_id');
    }

    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class, 'curso_id');
    }

    public function comunicados(): HasMany
    {
        return $this->hasMany(Comunicado::class, 'curso_id')->orderByDesc('created_at');
    }

    /** ERS RF-54: Esquema de Evaluacion del curso. */
    public function esquemaEvaluacion(): HasOne
    {
        return $this->hasOne(EsquemaEvaluacion::class, 'curso_id');
    }

    public function sesionesClase(): HasMany
    {
        return $this->hasMany(SesionClase::class)->orderBy('fecha');
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(Mensaje::class);
    }

    public function bancoPreguntasCurso(): HasMany
    {
        return $this->hasMany(BancoPregunta::class);
    }

    /**
     * ERS RF-20, RF-21, RF-61 y flujo 9.4: clona el curso para un nuevo ciclo.
     *
     * Copia: datos del curso, módulos, lecciones, tareas, quizzes y el
     * Esquema de Evaluación (como plantilla editable).
     * NO copia: matrículas, notas de rubro, entregas, resultados de quiz,
     * asistencia, grupos, mensajes ni comunicados (regla de negocio 4).
     * Todo el contenido copiado queda en Borrador para que el Supervisor y el
     * Profesor lo revisen antes de publicarlo.
     */
    public function clonar(string $nuevoNombre, ?int $profesorId = null): self
    {
        return DB::transaction(function () use ($nuevoNombre, $profesorId) {
            $nuevo = $this->replicate(['codigo', 'created_at', 'updated_at']);
            $nuevo->nombre = $nuevoNombre;
            $nuevo->estado = 'borrador';
            $nuevo->profesor_id = $profesorId ?? $this->profesor_id;
            $nuevo->save();

            $this->loadMissing(['modulos.lecciones', 'modulos.tareas', 'modulos.quizzes']);

            foreach ($this->modulos as $modulo) {
                $nuevoModulo = $modulo->replicate(['curso_id', 'created_at', 'updated_at']);
                $nuevoModulo->curso_id = $nuevo->id;
                $nuevoModulo->estado_contenido = 'borrador';
                $nuevoModulo->publicar_en = null;
                $nuevoModulo->save();

                foreach ($modulo->lecciones as $leccion) {
                    $nuevaLeccion = $leccion->replicate(['modulo_id', 'created_at', 'updated_at']);
                    $nuevaLeccion->modulo_id = $nuevoModulo->id;
                    $nuevaLeccion->estado = 'borrador';
                    $nuevaLeccion->fecha_publicacion = null;
                    $nuevaLeccion->save();
                }

                foreach ($modulo->tareas as $tarea) {
                    $nuevaTarea = $tarea->replicate(['curso_id', 'modulo_id', 'grupo_asignado_id', 'created_at', 'updated_at']);
                    $nuevaTarea->curso_id = $nuevo->id;
                    $nuevaTarea->modulo_id = $nuevoModulo->id;
                    $nuevaTarea->visible = false;
                    $nuevaTarea->estado_contenido = 'borrador';
                    $nuevaTarea->publicar_en = null;
                    $nuevaTarea->save();
                }

                foreach ($modulo->quizzes as $quiz) {
                    $nuevoQuiz = $quiz->replicate(['curso_id', 'modulo_id', 'created_at', 'updated_at']);
                    $nuevoQuiz->curso_id = $nuevo->id;
                    $nuevoQuiz->modulo_id = $nuevoModulo->id;
                    $nuevoQuiz->visible = false;
                    $nuevoQuiz->estado_contenido = 'borrador';
                    $nuevoQuiz->publicar_en = null;
                    $nuevoQuiz->save();
                }
            }

            // ERS RF-61: el esquema se copia como plantilla editable (sin notas).
            $esquema = $this->esquemaEvaluacion()->with('rubros')->first();

            if ($esquema) {
                $nuevoEsquema = $nuevo->esquemaEvaluacion()->create(['bloqueado' => false]);

                foreach ($esquema->rubros as $rubro) {
                    $nuevoEsquema->rubros()->create($rubro->only(['nombre', 'semana', 'peso', 'orden']));
                }
            }

            // RF-47: el banco de preguntas es contenido reutilizable del curso.
            foreach ($this->bancoPreguntasCurso()->get() as $pregunta) {
                $nuevo->bancoPreguntasCurso()->create($pregunta->only([
                    'tema', 'pregunta', 'tipo', 'opciones', 'respuestas_correctas', 'explicacion', 'dificultad', 'creado_por',
                ]));
            }

            return $nuevo;
        });
    }
}
