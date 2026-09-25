<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RubroEvaluacion extends Model
{
    protected $table = 'rubros_evaluacion';

    protected $fillable = [
        'esquema_id',
        'nombre',
        'semana',
        'peso',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'peso'   => 'decimal:2',
            'semana' => 'integer',
            'orden'  => 'integer',
        ];
    }

    // ==========================================
    // RELACIONES
    // ==========================================

    public function esquema(): BelongsTo
    {
        return $this->belongsTo(EsquemaEvaluacion::class, 'esquema_id');
    }

    public function notas(): HasMany
    {
        return $this->hasMany(NotaRubro::class, 'rubro_id');
    }

    public function notaDeEstudiante(User $estudiante): ?NotaRubro
    {
        return $this->notas()->where('estudiante_id', $estudiante->id)->first();
    }
}
