<?php

namespace App\Models;

use Database\Factories\TipoSalonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tipo de salón (aula teórica, laboratorio de cómputo, ...): catálogo
 * editable, no un enum fijo. Una clase solo se reprograma a un salón de su
 * mismo tipo.
 */
class TipoSalon extends Model
{
    /** @use HasFactory<TipoSalonFactory> */
    use HasFactory;

    protected $table = 'tipos_salon';

    protected $fillable = ['nombre', 'activo'];

    protected $attributes = ['activo' => true];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function salones(): HasMany
    {
        return $this->hasMany(Salon::class);
    }
}
