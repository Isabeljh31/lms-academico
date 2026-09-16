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

    protected $fillable = [
        'curso_id',
        'modulo_id',
        'titulo',
        'descripcion',
        'fecha_entrega',
        'peso_nota',
    ];

    protected function casts(): array
    {
        return [
            'fecha_entrega' => 'datetime',
            'peso_nota' => 'decimal:2',
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
}
