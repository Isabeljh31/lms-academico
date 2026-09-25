<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tarea extends Model
{
    use HasFactory;

    protected $table = 'tareas';

    // Espejo del default(true) de la migración: sin esto, un modelo recién
    // creado en memoria (p. ej. justo después de Tarea::create(...), antes
    // de recargarlo de la BD) queda con visible = null, que las policies
    // tratan como "falso" y bloquean el acceso a una tarea que en la BD sí
    // está visible.
    protected $attributes = [
        'visible' => true,
    ];

    protected $fillable = [
        'curso_id',
        'modulo_id',
        'titulo',
        'descripcion',
        'fecha_entrega',
        'peso_nota',
        'tipo_entrega',
        'visible',
    ];

    protected function casts(): array
    {
        return [
            'fecha_entrega' => 'datetime',
            'peso_nota' => 'decimal:2',
            'visible' => 'boolean',
        ];
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'modulo_id');
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

    public function esGrupal(): bool
    {
        return $this->tipo_entrega === 'grupal';
    }

    /**
     * Solo se puede eliminar de verdad si nadie entregó todavía: borrarla
     * con entregas de por medio se llevaría esas notas/feedback en cascada
     * (ver migración de `entregas`, onDelete cascade). Con entregas, la
     * única opción es ocultarla (ver campo `visible`).
     */
    public function puedeEliminarse(): bool
    {
        return $this->relationLoaded('entregas')
            ? $this->entregas->isEmpty()
            : $this->entregas()->doesntExist();
    }
}
