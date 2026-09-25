<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaRubro extends Model
{
    protected $table = 'notas_rubro';

    protected $fillable = [
        'rubro_id',
        'estudiante_id',
        'nota',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return ['nota' => 'decimal:1'];
    }

    // ==========================================
    // RELACIONES
    // ==========================================

    public function rubro(): BelongsTo
    {
        return $this->belongsTo(RubroEvaluacion::class, 'rubro_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
