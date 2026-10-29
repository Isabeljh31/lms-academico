<?php

namespace App\Models;

use Database\Factories\DebateComentarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Comentario de un debate. Con parent_id es una respuesta a un comentario
 * principal (un solo nivel de respuesta).
 */
class DebateComentario extends Model
{
    /** @use HasFactory<DebateComentarioFactory> */
    use HasFactory;

    protected $table = 'debate_comentarios';

    protected $fillable = ['debate_id', 'user_id', 'parent_id', 'contenido', 'editado_en'];

    protected function casts(): array
    {
        return ['editado_en' => 'datetime'];
    }

    public function debate(): BelongsTo
    {
        return $this->belongsTo(Debate::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function esRespuesta(): bool
    {
        return $this->parent_id !== null;
    }
}
