<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ERS RF-106: registro de solicitudes entrantes al endpoint de integracion. */
class LogIntegracionApi extends Model
{
    protected $table = 'logs_integracion_api';

    public $timestamps = false;
    const CREATED_AT = 'created_at';

    protected $fillable = [
        'integracion_id',
        'ip',
        'metodo',
        'endpoint',
        'status_code',
        'payload_recibido',
        'error_mensaje',
    ];

    protected function casts(): array
    {
        return [
            'payload_recibido' => 'array',
            'created_at'       => 'datetime',
        ];
    }

    public function integracion(): BelongsTo
    {
        return $this->belongsTo(IntegracionExterna::class, 'integracion_id');
    }
}
