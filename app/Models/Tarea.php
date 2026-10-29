<?php

namespace App\Models;

use App\Models\Concerns\TieneEstadoPublicacion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * ERS RF-40 a RF-43, RF-49, RF-76: Tarea de un curso.
 */
class Tarea extends Model
{
    use HasFactory, TieneEstadoPublicacion;

    protected $table = 'tareas';

    /**
     * ERS RF-43: estados de una tarea para un estudiante.
     *
     * @var array<string, string>
     */
    public const ESTADOS_ESTUDIANTE = [
        'pendiente' => 'Pendiente',
        'entregado' => 'Entregado',
        'fuera_de_plazo' => 'Fuera de plazo',
        'devuelto' => 'Devuelto para corrección',
        'calificado' => 'Calificado',
        'no_entregado' => 'No entregado',
    ];

    // Espejo del default(true) de la migración: sin esto, un modelo recién
    // creado en memoria (p. ej. justo después de Tarea::create(...), antes
    // de recargarlo de la BD) queda con visible = null, que las policies
    // tratan como "falso" y bloquean el acceso a una tarea que en la BD sí
    // está visible.
    protected $attributes = [
        'visible' => true,
        'estado_contenido' => 'publicado',
        'intentos_permitidos' => 1,
        'tipo_entrega' => 'individual',
    ];

    protected $fillable = [
        'curso_id',
        'modulo_id',
        'titulo',
        'descripcion',
        'instrucciones',
        'fecha_inicio',
        'fecha_entrega',
        'peso_nota',
        'intentos_permitidos',
        'formatos_permitidos',
        'tipo_entrega',
        'grupo_asignado_id',
        'visible',
        'estado_contenido',
        'publicar_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'datetime',
            'fecha_entrega' => 'datetime',
            'publicar_en' => 'datetime',
            'peso_nota' => 'decimal:2',
            'intentos_permitidos' => 'integer',
            'formatos_permitidos' => 'array',
            'visible' => 'boolean',
        ];
    }

    protected function dependeDeVisibilidadYModulo(): bool
    {
        return true;
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'modulo_id');
    }

    public function grupoAsignado(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_asignado_id');
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class, 'tarea_id');
    }

    public function entregaDe(User $estudiante): ?Entrega
    {
        return $this->relationLoaded('entregas')
            ? $this->entregas->firstWhere('estudiante_id', $estudiante->id)
            : $this->entregas()->where('estudiante_id', $estudiante->id)->first();
    }

    public function estaVencida(): bool
    {
        return $this->fecha_entrega->isPast();
    }

    public function haIniciado(): bool
    {
        return $this->fecha_inicio === null || $this->fecha_inicio->lte(now());
    }

    public function esGrupal(): bool
    {
        return $this->tipo_entrega === 'grupal';
    }

    /**
     * ERS RF-41: extensiones permitidas para la entrega (vacío = las
     * permitidas por defecto en config/archivos.php).
     *
     * @return array<int, string>
     */
    public function formatosPermitidos(): array
    {
        return array_values(array_filter(array_map(
            fn ($formato) => strtolower(trim((string) $formato, " .\t\n\r")),
            $this->formatos_permitidos ?? []
        )));
    }

    /**
     * ERS RF-43: estado calculado de la tarea para un estudiante.
     *
     * - Calificado: la entrega tiene nota.
     * - Devuelto para corrección: el profesor la devolvió (RF-49).
     * - Entregado: entregó dentro del plazo.
     * - Fuera de plazo: entregó después de la fecha límite.
     * - Pendiente: no entregó y el plazo sigue abierto.
     * - No entregado: no entregó y el plazo venció.
     */
    public function estadoPara(?Entrega $entrega, ?Carbon $ahora = null): string
    {
        $ahora ??= now();

        if ($entrega) {
            if ($entrega->estado === 'calificado' && $entrega->nota !== null) {
                return 'calificado';
            }

            if ($entrega->estado === 'devuelto') {
                return 'devuelto';
            }

            $momentoEntrega = $entrega->updated_at ?? $entrega->created_at ?? $ahora;

            return $momentoEntrega->gt($this->fecha_entrega) ? 'fuera_de_plazo' : 'entregado';
        }

        return $this->fecha_entrega->lt($ahora) ? 'no_entregado' : 'pendiente';
    }

    public static function etiquetaEstado(string $estado): string
    {
        return self::ESTADOS_ESTUDIANTE[$estado] ?? ucfirst(str_replace('_', ' ', $estado));
    }

    /**
     * Solo se puede eliminar de verdad si nadie entregó todavía: borrarla
     * con entregas de por medio se llevaría esas notas/feedback en cascada
     * (ver migración de `entregas`, onDelete cascade). Con entregas, la
     * única opción es ocultarla (ver campo `visible`).
     */
    public function puedeEliminarse(): bool
    {
        if ($this->rubrosEvaluacion()->exists()) {
            return false;
        }

        return $this->relationLoaded('entregas')
            ? $this->entregas->isEmpty()
            : $this->entregas()->doesntExist();
    }

    public function rubrosEvaluacion(): BelongsToMany
    {
        return $this->belongsToMany(RubroEvaluacion::class, 'rubro_tarea', 'tarea_id', 'rubro_id')->withPivot('peso');
    }
}
