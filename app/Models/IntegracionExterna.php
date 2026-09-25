<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Empresa/institucion cliente que se conecta a LearnUp via API.
 * ERS RF-100 a RF-106.
 */
class IntegracionExterna extends Model
{
    protected $table = 'integraciones_externas';

    protected $fillable = [
        'nombre',
        'api_key_hash',
        'api_key_prefix',
        'activo',
        'descripcion',
        'ultimo_uso',
    ];

    protected $hidden = ['api_key_hash'];

    protected function casts(): array
    {
        return [
            'activo'     => 'boolean',
            'ultimo_uso' => 'datetime',
        ];
    }

    // ==========================================
    // FACTORY: generar nueva API Key (ERS RF-101)
    // ==========================================

    /**
     * Genera una nueva API Key, la hashea y devuelve
     * [modelo_guardado, api_key_plana].
     * La api_key_plana solo se muestra UNA vez al crearla.
     */
    public static function generarApiKey(string $nombre, ?string $descripcion = null): array
    {
        $apiKeyPlana = 'LU_' . Str::random(40);
        $prefix = substr($apiKeyPlana, 0, 8);

        $modelo = static::create([
            'nombre'        => $nombre,
            'api_key_hash'  => Hash::make($apiKeyPlana),
            'api_key_prefix' => $prefix,
            'descripcion'   => $descripcion,
        ]);

        return [$modelo, $apiKeyPlana];
    }

    /** Verifica si la API Key plana corresponde a esta integracion. */
    public function verificarApiKey(string $apiKeyPlana): bool
    {
        return $this->activo && Hash::check($apiKeyPlana, $this->api_key_hash);
    }

    /** Registra el ultimo uso de la integracion. */
    public function registrarUso(): void
    {
        $this->update(['ultimo_uso' => now()]);
    }

    // ==========================================
    // RELACIONES
    // ==========================================

    public function logs(): HasMany
    {
        return $this->hasMany(LogIntegracionApi::class, 'integracion_id');
    }
}
