<?php

namespace App\Models;

use App\Services\Multimedia\SubidorArchivos;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    /** Virtual es asíncrono (sin horario fijo); presencial y remoto sí tienen horario semanal. */
    const MODALIDADES = ['presencial', 'virtual', 'remoto'];

    const MODALIDADES_CON_HORARIO = ['presencial', 'remoto'];

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
        'cupo_maximo',
        'modalidad',
        // Lista de bloques {dia, hora_inicio, hora_fin, aula}: lunes y viernes
        // pueden tener horas (y aula, si es presencial) distintas entre sí.
        'horario_bloques',
        // ERS RF-73: asistencia opcional
        'usa_asistencia',
    ];

    protected $attributes = [
        'estado' => 'borrador',
        'usa_asistencia' => true,
        'modalidad' => 'presencial',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'usa_asistencia' => 'boolean',
            'horario_bloques' => 'array',
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

    /** Imagen de portada con enlace temporal (ERS RNF-06), o null si no tiene. */
    public function portadaUrl(): ?string
    {
        return SubidorArchivos::enlace($this->imagen_portada_url);
    }

    public static function normalizarCodigo(?string $codigo): ?string
    {
        $codigo = mb_strtoupper(trim((string) $codigo));

        return $codigo === '' ? null : $codigo;
    }

    /** Nombre sin tildes, en minúsculas y sin espacios de más, para comparar "Matemática 1" con "matemática  1". */
    public static function normalizarNombre(string $nombre): string
    {
        return (string) Str::of($nombre)->ascii()->lower()->squish();
    }

    /**
     * Código sugerido para un curso nuevo: el prefijo se hereda de otro curso
     * con el mismo nombre si ya existe (misma materia, nueva sección), o se
     * arma desde el texto del nombre si es la primera vez. El año es el de
     * creación (no el de inicio de clases, para no depender de una fecha que
     * el Supervisor todavía puede cambiar) y la secuencia es automática.
     */
    public static function sugerirCodigo(string $nombre): ?string
    {
        $nombre = trim($nombre);

        if ($nombre === '') {
            return null;
        }

        $existente = self::buscarPorNombreNormalizado($nombre);
        $prefijo = $existente ? Str::before((string) $existente->codigo, '-') : self::prefijoDesdeNombre($nombre);

        if ($prefijo === '') {
            return null;
        }

        $anio = now()->format('y');
        $siguiente = self::query()
            ->where('codigo', 'like', "{$prefijo}-{$anio}-%")
            ->pluck('codigo')
            ->map(fn (string $codigo) => (int) Str::afterLast($codigo, '-'))
            ->max() ?? 0;

        return sprintf('%s-%s-%02d', $prefijo, $anio, $siguiente + 1);
    }

    /** El curso con código más antiguo que tenga este mismo nombre (normalizado), para heredar su prefijo. */
    public static function buscarPorNombreNormalizado(string $nombre): ?self
    {
        $buscado = self::normalizarNombre($nombre);

        return self::query()->whereNotNull('codigo')->orderBy('id')->get(['id', 'nombre', 'codigo'])
            ->first(fn (self $curso) => self::normalizarNombre($curso->nombre) === $buscado);
    }

    /** Primeras letras de la primera palabra del nombre + el primer número que aparezca (p. ej. "Matemática 1" -> "MAT1"). */
    public static function prefijoDesdeNombre(string $nombre): string
    {
        $limpio = Str::of($nombre)->ascii()->upper()->replaceMatches('/[^A-Z0-9 ]+/', ' ')->squish();
        $primeraPalabra = (string) $limpio->explode(' ')->first(fn (string $palabra) => $palabra !== '', '');
        $base = Str::substr($primeraPalabra, 0, 3);

        preg_match('/\d+/', (string) $limpio, $coincidencia);

        return $base.($coincidencia[0] ?? '');
    }

    /**
     * Si el prefijo de $codigo ya pertenece a OTRO curso con un nombre
     * distinto, devuelve el nombre de esa materia (cada prefijo debe ser de
     * una sola materia). Devuelve null si no hay conflicto.
     */
    public static function prefijoPerteneceAOtraMateria(string $codigo, string $nombreActual, ?int $ignorarCursoId = null): ?string
    {
        $prefijo = Str::before(self::normalizarCodigo($codigo) ?? '', '-');

        if ($prefijo === '') {
            return null;
        }

        $normalizado = self::normalizarNombre($nombreActual);

        $otro = self::query()
            ->where('codigo', 'like', "{$prefijo}-%")
            ->when($ignorarCursoId, fn (Builder $q) => $q->whereKeyNot($ignorarCursoId))
            ->get(['nombre'])
            ->first(fn (self $curso) => self::normalizarNombre($curso->nombre) !== $normalizado);

        return $otro?->nombre;
    }

    /**
     * Nombre ya registrado más parecido a este (para sugerir "¿quisiste decir...?"
     * en vez de crear sin querer una materia duplicada). Null si no hay nada
     * parecido o si ya coincide exactamente con uno existente.
     */
    public static function nombreParecido(string $nombre): ?string
    {
        $nombre = trim($nombre);

        if (mb_strlen($nombre) < 3) {
            return null;
        }

        $normalizado = self::normalizarNombre($nombre);

        foreach (self::query()->distinct()->pluck('nombre')->filter() as $candidato) {
            $candidatoNormalizado = self::normalizarNombre($candidato);

            if ($candidatoNormalizado === $normalizado) {
                return null;
            }

            similar_text($normalizado, $candidatoNormalizado, $porcentaje);

            if ($porcentaje >= 80) {
                return $candidato;
            }
        }

        return null;
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

    /** true si ya se alcanzó el cupo máximo de estudiantes (null en cupo_maximo = sin límite). */
    public function cupoLleno(): bool
    {
        return $this->cupo_maximo !== null && $this->inscripcionesActivas()->count() >= $this->cupo_maximo;
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

    /** "Información del curso" (RF administrativo): carpetas que arma el Supervisor. */
    public function carpetas(): HasMany
    {
        return $this->hasMany(CarpetaCurso::class, 'curso_id')->orderBy('orden');
    }

    /** Debates del curso (extensión fuera del ERS). */
    public function debates(): HasMany
    {
        return $this->hasMany(Debate::class, 'curso_id');
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

    /**
     * Genera una sesión de clase por cada bloque de horario (día, hora de
     * inicio, hora de fin y, si es presencial, aula) entre fecha_inicio y
     * fecha_fin. Lunes y viernes pueden tener horas y aulas distintas, cada
     * bloque es independiente. Solo aplica en modalidad presencial o remoto
     * (virtual es asíncrono, sin horario) y si el curso registra asistencia.
     * Se usa una sola vez, al crear el curso; el Profesor puede editar o
     * borrar sesiones puntuales después desde el módulo de Asistencia.
     */
    /**
     * Resumen legible del horario semanal para pantallas de solo lectura
     * (ej. "Lun 08:00-10:00 (Aula 204), Vie 14:00-16:00"), o null si el
     * curso es virtual o aún no tiene bloques de horario.
     */
    public function horarioResumen(): ?string
    {
        if (! in_array($this->modalidad, self::MODALIDADES_CON_HORARIO, true) || blank($this->horario_bloques)) {
            return null;
        }

        $dias = ['1' => 'Lun', '2' => 'Mar', '3' => 'Mié', '4' => 'Jue', '5' => 'Vie', '6' => 'Sáb', '7' => 'Dom'];

        $bloques = collect($this->horario_bloques)
            ->filter(fn (array $b) => filled($b['dia'] ?? null) && filled($b['hora_inicio'] ?? null) && filled($b['hora_fin'] ?? null))
            ->map(function (array $b) use ($dias) {
                $texto = ($dias[(string) $b['dia']] ?? '?')." {$b['hora_inicio']}-{$b['hora_fin']}";

                return $this->modalidad === 'presencial' && filled($b['aula'] ?? null) ? "{$texto} ({$b['aula']})" : $texto;
            });

        return $bloques->isEmpty() ? null : $bloques->join(', ');
    }

    public function generarSesionesDesdeHorario(): int
    {
        if (! in_array($this->modalidad, self::MODALIDADES_CON_HORARIO, true)
            || ! $this->usa_asistencia
            || blank($this->horario_bloques)
            || ! $this->fecha_inicio
            || ! $this->fecha_fin) {
            return 0;
        }

        $bloquesPorDia = collect($this->horario_bloques)
            ->filter(fn (array $bloque) => filled($bloque['dia'] ?? null) && filled($bloque['hora_inicio'] ?? null) && filled($bloque['hora_fin'] ?? null))
            ->keyBy(fn (array $bloque) => (int) $bloque['dia']);

        if ($bloquesPorDia->isEmpty()) {
            return 0;
        }

        $numero = 0;
        $filas = [];

        foreach (CarbonPeriod::create($this->fecha_inicio, $this->fecha_fin) as $fecha) {
            $bloque = $bloquesPorDia->get($fecha->isoWeekday());

            if (! $bloque) {
                continue;
            }

            $horaInicio = Carbon::parse($bloque['hora_inicio']);
            $duracionMinutos = $horaInicio->diffInMinutes(Carbon::parse($bloque['hora_fin']), false);

            if ($duracionMinutos <= 0) {
                continue;
            }

            $numero++;
            $filas[] = [
                'curso_id' => $this->id,
                'titulo' => "Sesión {$numero}",
                'descripcion' => filled($bloque['aula'] ?? null) ? "Aula: {$bloque['aula']}" : null,
                'fecha' => $fecha->copy()->setTimeFromTimeString($horaInicio->format('H:i:s')),
                'duracion_minutos' => $duracionMinutos,
                'numero_sesion' => $numero,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($filas !== []) {
            SesionClase::insert($filas);
        }

        return count($filas);
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
                    'tema', 'pregunta', 'tipo', 'opciones', 'respuestas_correctas', 'explicacion', 'dificultad', 'puntaje', 'creado_por',
                ]));
            }

            return $nuevo;
        });
    }
}
