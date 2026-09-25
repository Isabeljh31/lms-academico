<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EsquemaEvaluacion extends Model
{
    protected $table = 'esquemas_evaluacion';

    protected $fillable = ['curso_id', 'bloqueado'];

    protected function casts(): array
    {
        return ['bloqueado' => 'boolean'];
    }

    // ==========================================
    // HELPERS (ERS RF-56, RF-62)
    // ==========================================

    /**
     * Valida que la suma de los pesos de los rubros sea exactamente 100%.
     * ERS RF-56: validacion de backend obligatoria.
     */
    public function pesosValidos(): bool
    {
        $suma = $this->rubros()->sum('peso');
        return abs($suma - 100.0) < 0.01;
    }

    public function sumaPesos(): float
    {
        return (float) $this->rubros()->sum('peso');
    }

    public function estaBloqueado(): bool
    {
        return (bool) $this->bloqueado;
    }

    // ==========================================
    // RELACIONES
    // ==========================================

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    public function rubros(): HasMany
    {
        return $this->hasMany(RubroEvaluacion::class, 'esquema_id')->orderBy('orden');
    }
}
