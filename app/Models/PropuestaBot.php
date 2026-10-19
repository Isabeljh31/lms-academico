<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropuestaBot extends Model
{
    use HasFactory;

    protected $table = 'propuestas_bot';

    /**
     * Presentación de cada tipo de propuesta en la bandeja del estudiante.
     * Hoy el bot genera un solo tipo (atraso detectado → recordatorio); un
     * tipo nuevo se agrega aquí con su propia etiqueta e ícono.
     *
     * @var array<string, array{etiqueta: string, icono: string, accion: string}>
     */
    public const TIPOS = [
        'redistribucion_plan' => [
            'etiqueta' => 'Atraso detectado',
            'icono' => 'alerta',
            'accion' => 'Recordatorio para ponerte al día',
        ],
    ];

    /** Presentación para un tipo sin configuración propia. */
    public const TIPO_GENERICO = [
        'etiqueta' => 'Sugerencia del asistente',
        'icono' => 'bombilla',
        'accion' => 'Sugerencia para tu plan de estudio',
    ];

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

    /**
     * @return array{etiqueta: string, icono: string, accion: string}
     */
    public function presentacion(): array
    {
        return self::TIPOS[$this->tipo] ?? self::TIPO_GENERICO;
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
