<?php

namespace App\Models;

use Database\Factories\SolicitudCambioClaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solicitud de cambio de clase (extensión fuera del ERS): el Profesor del
 * curso pide reprogramar una clase o dictarla en remoto. Si cumple las
 * reglas de DisponibilidadSalones se aplica sola (origen «automatica»); si
 * no, queda pendiente («En revisión») y la decide el Supervisor. Guarda la
 * clase tal como estaba para poder revertirla. La lógica está en
 * App\Services\Asistencia\SolicitudesCambioClase.
 */
class SolicitudCambioClase extends Model
{
    /** @use HasFactory<SolicitudCambioClaseFactory> */
    use HasFactory;

    protected $table = 'solicitudes_cambio_clase';

    /** @var array<string, string> */
    public const TIPOS = [
        'reprogramar' => 'Reprogramar a otra fecha',
        'remoto' => 'Dictarla en remoto en la misma fecha y hora',
    ];

    /** @var array<string, string> */
    public const MOTIVOS = [
        'salud' => 'Salud',
        'personal' => 'Asunto personal',
        'institucional' => 'Institucional',
        'otro' => 'Otro',
    ];

    /** Modalidad de la clase (sesiones_clase.modalidad; null = la del curso). @var array<string, string> */
    public const MODALIDADES = [
        'presencial' => 'Presencial',
        'remoto' => 'Remota',
    ];

    /** @var array<string, string> */
    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'aprobada' => 'Aprobada',
        'rechazada' => 'Rechazada',
        'cancelada' => 'Cancelada',
        'revertida' => 'Revertida',
    ];

    /** Quién aplicó una reprogramación aprobada. @var array<string, string> */
    public const ORIGENES = [
        'automatica' => 'Automática',
        'supervisor' => 'Supervisor',
    ];

    protected $fillable = [
        'sesion_id', 'curso_id', 'solicitado_por', 'tipo', 'motivo', 'detalle', 'modalidad_solicitada',
        'fecha_propuesta', 'fecha_original', 'duracion_original', 'estado', 'decidido_por', 'decidido_en',
        'comentario_decision', 'fecha_nueva', 'duracion_nueva', 'modalidad_nueva', 'aula_nueva',
        'origen', 'salon_original_id', 'descripcion_original', 'modalidad_original', 'salon_nuevo_id',
        'razon_revision', 'sustento_pedido', 'sustento_pedido_en', 'sustento_respuesta', 'sustento_archivo',
        'sustento_respondido_en', 'revertida_por', 'revertida_en', 'comentario_reversion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_propuesta' => 'datetime',
            'fecha_original' => 'datetime',
            'decidido_en' => 'datetime',
            'fecha_nueva' => 'datetime',
            'sustento_pedido_en' => 'datetime',
            'sustento_respondido_en' => 'datetime',
            'revertida_en' => 'datetime',
            'duracion_original' => 'integer',
            'duracion_nueva' => 'integer',
        ];
    }

    public function sesion(): BelongsTo
    {
        return $this->belongsTo(SesionClase::class, 'sesion_id');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    public function decisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decidido_por');
    }

    public function salonOriginal(): BelongsTo
    {
        return $this->belongsTo(Salon::class, 'salon_original_id');
    }

    public function salonNuevo(): BelongsTo
    {
        return $this->belongsTo(Salon::class, 'salon_nuevo_id');
    }

    public function revertidor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revertida_por');
    }

    /**
     * Pendiente con sustento pedido y aún sin respuesta: se muestra como
     * «Sustento solicitado» (no es un estado aparte, así el índice único
     * parcial de pendientes sigue valiendo).
     */
    public function esperaSustento(): bool
    {
        return $this->estaPendiente() && $this->sustento_pedido_en !== null && $this->sustento_respondido_en === null;
    }

    public function estaPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }

    /** @param  Builder<SolicitudCambioClase>  $query */
    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', 'pendiente');
    }
}
