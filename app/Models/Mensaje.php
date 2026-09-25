<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ERS RF-80: Mensajería privada Estudiante ↔ Profesor.
 * RF-82: Soporte para adjuntos (archivo_url).
 * RF-83: Notificación de mensaje nuevo (leido_at = NULL).
 */
class Mensaje extends Model
{
    protected $fillable = [
        'remitente_id', 'destinatario_id', 'curso_id',
        'asunto', 'cuerpo', 'archivo_url', 'leido_at',
    ];

    protected $casts = [
        'leido_at' => 'datetime',
    ];

    public function remitente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remitente_id');
    }

    public function destinatario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinatario_id');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function estaLeido(): bool
    {
        return $this->leido_at !== null;
    }

    public function marcarComoLeido(): void
    {
        if (!$this->leido_at) {
            $this->update(['leido_at' => now()]);
        }
    }
}
