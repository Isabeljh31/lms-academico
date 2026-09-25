<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro inmutable de acciones criticas del sistema.
 * ERS RF-95 a RF-99: auditoria de notas y acciones administrativas.
 *
 * Tipos de evento:
 *   cambio_nota, baja_estudiante, creacion_cuenta, desactivacion_cuenta,
 *   matricula_manual, matricula_api, modificacion_esquema, otro_admin
 */
class BitacoraAuditoria extends Model
{
    protected $table = 'bitacoras_auditoria';

    // ERS RF-99: inmutable — no hay updated_at
    public $timestamps = false;
    const CREATED_AT = 'created_at';

    // No se permite update() ni delete() desde la capa de aplicacion
    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    // ==========================================
    // FACTORY METHODS (ERS RF-95, RF-96)
    // ==========================================

    public static function registrarCambioNota(
        User $ejecutor,
        ?Curso $curso,
        User $estudiante,
        string $entidad,
        int $entidadId,
        mixed $valorAnterior,
        mixed $valorNuevo,
        ?string $motivo = null
    ): static {
        return static::create([
            'ejecutado_por'       => $ejecutor->id,
            'tipo_evento'         => 'cambio_nota',
            'curso_id'            => $curso?->id,
            'usuario_afectado_id' => $estudiante->id,
            'entidad'             => $entidad,
            'entidad_id'          => $entidadId,
            'valor_anterior'      => $valorAnterior !== null ? (string) $valorAnterior : null,
            'valor_nuevo'         => $valorNuevo !== null ? (string) $valorNuevo : null,
            'motivo'              => $motivo,
            'ip'                  => request()->ip(),
        ]);
    }

    public static function registrarAccionAdmin(
        User $ejecutor,
        string $tipoEvento,
        string $descripcion,
        ?User $usuarioAfectado = null,
        ?Curso $curso = null
    ): static {
        return static::create([
            'ejecutado_por'       => $ejecutor->id,
            'tipo_evento'         => $tipoEvento,
            'descripcion'         => $descripcion,
            'curso_id'            => $curso?->id,
            'usuario_afectado_id' => $usuarioAfectado?->id,
            'ip'                  => request()->ip(),
        ]);
    }

    // ==========================================
    // RELACIONES
    // ==========================================

    public function ejecutadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ejecutado_por');
    }

    public function usuarioAfectado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_afectado_id');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }
}
