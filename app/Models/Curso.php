<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Curso extends Model
{
    use HasFactory;

    protected $table = 'cursos';

    // estados: borrador | publicado | despublicado | archivado
    const ESTADOS = ['borrador', 'publicado', 'despublicado', 'archivado'];

    protected $fillable = [
        'nombre',
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
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin'    => 'date',
        ];
    }

    // ==========================================
    // HELPERS DE ESTADO (ERS RF-22)
    // ==========================================

    public function estaPublicado(): bool
    {
        return $this->estado === 'publicado';
    }

    public function estaFinalizado(): bool
    {
        return $this->estado === 'archivado';
    }

    // ==========================================
    // RELACIONES
    // ==========================================

    public function profesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesor_id');
    }

    public function modulos(): HasMany
    {
        return $this->hasMany(Modulo::class, 'curso_id')->orderBy('orden');
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'curso_id');
    }

    /** Solo inscripciones activas (no dados de baja). */
    public function inscripcionesActivas(): HasMany
    {
        return $this->inscripciones()->where('estado', 'activo');
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

    public function sesionesClase(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SesionClase::class)->orderBy('fecha');
    }

    public function mensajes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Mensaje::class);
    }

    public function bancoPreguntasCurso(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BancoPregunta::class);
    }

    /**
     * ERS RF-20/21: Clonar curso (contenido + esquema, SIN estudiantes/notas).
     */
    public function clonar(string $nuevoNombre, ?int $profesorId = null): self
    {
        $nuevo = $this->replicate(['id', 'created_at', 'updated_at']);
        $nuevo->nombre     = $nuevoNombre;
        $nuevo->estado     = 'borrador';
        $nuevo->profesor_id = $profesorId ?? $this->profesor_id;
        $nuevo->save();

        // Clonar módulos y lecciones
        foreach ($this->modulos()->with('lecciones')->get() as $modulo) {
            $nuevoModulo = $modulo->replicate(['id', 'curso_id', 'created_at', 'updated_at']);
            $nuevoModulo->curso_id = $nuevo->id;
            $nuevoModulo->save();

            foreach ($modulo->lecciones as $leccion) {
                $nuevaLeccion = $leccion->replicate(['id', 'modulo_id', 'created_at', 'updated_at']);
                $nuevaLeccion->modulo_id = $nuevoModulo->id;
                $nuevaLeccion->estado    = 'borrador';
                $nuevaLeccion->save();
            }
        }

        // ERS RF-61: Clonar Esquema de Evaluación
        $esquema = $this->esquemaEvaluacion()->with('rubros')->first();
        if ($esquema) {
            $nuevoEsquema = $esquema->replicate(['id', 'curso_id', 'created_at', 'updated_at']);
            $nuevoEsquema->curso_id = $nuevo->id;
            $nuevoEsquema->save();

            foreach ($esquema->rubros as $rubro) {
                $nuevoRubro = $rubro->replicate(['id', 'esquema_id', 'created_at', 'updated_at']);
                $nuevoRubro->esquema_id = $nuevoEsquema->id;
                $nuevoRubro->save();
            }
        }

        return $nuevo;
    }

}