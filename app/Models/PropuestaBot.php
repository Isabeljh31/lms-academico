<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropuestaBot extends Model
{
    use HasFactory;

    protected $table = 'propuestas_bot';

    protected $fillable = [
        'usuario_id',
        'tipo',
        'datos_propuesta',
        'estado',
        'aprobado_por',
        'fecha_respuesta',
    ];

    protected function casts(): array
    {
        return [
            'datos_propuesta' => 'array', // Acciones sugeridas por Groq/Llama[cite: 1]
            'fecha_respuesta' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }
}
