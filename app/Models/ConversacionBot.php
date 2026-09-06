<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversacionBot extends Model
{
    protected $table = 'conversaciones_bot';

    protected $fillable = [
        'usuario_id',
        'curso_id',
        'mensajes',
    ];

    protected function casts(): array
    {
        return [
            'mensajes' => 'array', // Historial de chat estructurado en JSON[cite: 1]
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }
}