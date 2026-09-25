<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_url',
        // ERS RF-15: activar/desactivar cuenta (logica, sin borrar historial)
        'activo',
        // ERS RF-05, RF-06: autenticacion mejorada
        'primer_login',
        'bloqueado_hasta',
        'intentos_fallidos',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'activo'            => 'boolean',
            'primer_login'      => 'boolean',
            'bloqueado_hasta'   => 'datetime',
        ];
    }

    // ==========================================
    // HELPERS DE AUTENTICACION (ERS RF-06)
    // ==========================================

    /** Indica si la cuenta está bloqueada por intentos fallidos. */
    public function estaBloqueada(): bool
    {
        return $this->bloqueado_hasta !== null && $this->bloqueado_hasta->isFuture();
    }

    /** Incrementa el contador de intentos y bloquea si supera el límite (3 intentos → 15 min). */
    public function registrarIntentoFallido(): void
    {
        $intentos = $this->intentos_fallidos + 1;
        $data = ['intentos_fallidos' => $intentos];
        if ($intentos >= 3) {
            $data['bloqueado_hasta'] = now()->addMinutes(15);
        }
        $this->update($data);
    }

    /** Reinicia el contador tras un login exitoso. */
    public function limpiarIntentosFallidos(): void
    {
        $this->update(['intentos_fallidos' => 0, 'bloqueado_hasta' => null]);
    }

    // ==========================================
    // RELACIONES ELOQUENT DEL LMS
    // ==========================================

    public function cursosComoProfesor(): HasMany
    {
        return $this->hasMany(Curso::class, 'profesor_id');
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'estudiante_id');
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class, 'estudiante_id');
    }

    public function eventosCalendario(): HasMany
    {
        return $this->hasMany(EventoCalendario::class, 'usuario_id');
    }

    public function propuestasBot(): HasMany
    {
        return $this->hasMany(PropuestaBot::class, 'usuario_id');
    }

    public function comunicados(): HasMany
    {
        return $this->hasMany(Comunicado::class, 'user_id');
    }

    public function notasRubro(): HasMany
    {
        return $this->hasMany(NotaRubro::class, 'estudiante_id');
    }

    public function accionesAuditoria(): HasMany
    {
        return $this->hasMany(BitacoraAuditoria::class, 'ejecutado_por');
    }

    // ==========================================
    // HELPERS DE ROL
    // ==========================================

    public function rolPrincipal(): ?string
    {
        return $this->getRoleNames()->first();
    }

    /**
     * El grupo del estudiante dentro de un curso (o null si no está en ninguno).
     * Un estudiante pertenece a lo más un grupo por curso.
     */
    public function grupoEnCurso(Curso $curso): ?Grupo
    {
        return Grupo::where('curso_id', $curso->id)
            ->whereHas('estudiantes', fn ($q) => $q->where('users.id', $this->id))
            ->first();
    }
}
