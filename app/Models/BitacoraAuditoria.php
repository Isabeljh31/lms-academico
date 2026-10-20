<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Registro inmutable de acciones críticas del sistema.
 * ERS RF-95 a RF-99: auditoría de notas y acciones administrativas.
 *
 * RF-99: una vez creado, un registro no puede modificarse ni eliminarse
 * desde la aplicación (los eventos updating/deleting lanzan excepción).
 */
class BitacoraAuditoria extends Model
{
    protected $table = 'bitacoras_auditoria';

    // ERS RF-99: inmutable — no hay updated_at
    public $timestamps = false;

    const CREATED_AT = 'created_at';

    /**
     * Tipos de evento con su etiqueta para la interfaz (filtro RF-98).
     *
     * @var array<string, string>
     */
    public const TIPOS = [
        'eliminacion_cuenta' => 'Eliminación de cuenta',
        'cambio_nota' => 'Cambio de nota',
        'creacion_cuenta' => 'Creación de cuenta',
        'edicion_datos_usuario' => 'Edición de cuenta',
        'cambio_rol' => 'Cambio de rol',
        'desactivacion_cuenta' => 'Desactivación de cuenta',
        'reactivacion_cuenta' => 'Reactivación de cuenta',
        'matricula_manual' => 'Matrícula manual',
        'matricula_api' => 'Matrícula por API',
        'baja_estudiante' => 'Baja de estudiante',
        'modificacion_curso' => 'Creación o edición de curso',
        'asignacion_profesor' => 'Asignación de profesor',
        'clonacion_curso' => 'Clonación de curso',
        'modificacion_esquema' => 'Modificación de esquema',
        'recalculo_quiz' => 'Recálculo de quiz',
        'comunicado' => 'Comunicado',
        'configuracion' => 'Configuración de plataforma',
        'integracion_api' => 'Integración API',
        'cambio_password_inicial' => 'Cambio de contraseña inicial',
        'otro_admin' => 'Otra acción',
    ];

    protected $fillable = [
        'ejecutado_por',
        'tipo_evento',
        'curso_id',
        'usuario_afectado_id',
        'entidad',
        'entidad_id',
        'valor_anterior',
        'valor_nuevo',
        'motivo',
        'descripcion',
        'ip',
        'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (BitacoraAuditoria $registro) {
            $registro->created_at ??= now();
        });

        static::updating(function () {
            throw new LogicException('La bitácora de auditoría es inmutable: no se permite modificar registros.');
        });

        static::deleting(function () {
            throw new LogicException('La bitácora de auditoría es inmutable: no se permite eliminar registros.');
        });
    }

    public function etiquetaTipo(): string
    {
        return self::TIPOS[$this->tipo_evento] ?? ucfirst(str_replace('_', ' ', $this->tipo_evento));
    }

    // ==========================================
    // FACTORY METHODS (ERS RF-95, RF-96)
    // ==========================================

    public static function registrarCambioNota(
        ?User $ejecutor,
        ?Curso $curso,
        User $estudiante,
        string $entidad,
        int $entidadId,
        mixed $valorAnterior,
        mixed $valorNuevo,
        ?string $motivo = null
    ): static {
        return static::create([
            'ejecutado_por' => $ejecutor?->id,
            'tipo_evento' => 'cambio_nota',
            'curso_id' => $curso?->id,
            'usuario_afectado_id' => $estudiante->id,
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'valor_anterior' => self::nota($valorAnterior),
            'valor_nuevo' => self::nota($valorNuevo),
            'motivo' => $motivo,
            'ip' => request()->ip(),
        ]);
    }

    /**
     * @param  User|null  $ejecutor  null cuando la acción la origina un sistema externo (API).
     */
    public static function registrarAccionAdmin(
        ?User $ejecutor,
        string $tipoEvento,
        string $descripcion,
        ?User $usuarioAfectado = null,
        ?Curso $curso = null,
        mixed $valorAnterior = null,
        mixed $valorNuevo = null,
        ?string $motivo = null,
    ): static {
        return static::create([
            'ejecutado_por' => $ejecutor?->id,
            'tipo_evento' => $tipoEvento,
            'descripcion' => $descripcion,
            'curso_id' => $curso?->id,
            'usuario_afectado_id' => $usuarioAfectado?->id,
            'valor_anterior' => self::serializar($valorAnterior),
            'valor_nuevo' => self::serializar($valorNuevo),
            'motivo' => $motivo,
            'ip' => request()->ip(),
        ]);
    }

    /**
     * Nota en texto sin ceros de relleno (12.0 → "12", 12.50 → "12.5").
     */
    protected static function nota(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return is_numeric($valor) ? (string) (float) $valor : (string) $valor;
    }

    protected static function serializar(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        return is_scalar($valor) ? (string) $valor : json_encode($valor, JSON_UNESCAPED_UNICODE);
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
