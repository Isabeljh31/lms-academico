<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Espejo de los default de la migración, para que un modelo recién
     * creado en memoria tenga los mismos valores que la fila en la BD.
     */
    protected $attributes = [
        'activo' => true,
        'primer_login' => true,
        'intentos_fallidos' => 0,
    ];

    /**
     * Los campos de seguridad (bloqueo e intentos) no son asignables en masa:
     * solo se cambian con forceFill desde los métodos de este modelo.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_url',
        // ERS RF-15: activar/desactivar cuenta (logica, sin borrar historial)
        'activo',
        // ERS RF-05: cambio obligatorio en el primer ingreso
        'primer_login',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'primer_login' => 'boolean',
            'bloqueado_hasta' => 'datetime',
        ];
    }

    /**
     * El correo se guarda siempre en minúsculas y sin espacios en los extremos:
     * "Ana@LMS.edu.pe" y "ana@lms.edu.pe" son la misma cuenta en todas las
     * vías de alta (formulario, importación, API) y en el inicio de sesión.
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $valor) => $valor === null ? null : self::normalizarEmail($valor),
        );
    }

    public static function normalizarEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    // ==========================================
    // HELPERS DE AUTENTICACION (ERS RF-06)
    // ==========================================

    /** Indica si la cuenta está bloqueada por intentos fallidos. */
    public function estaBloqueada(): bool
    {
        return $this->bloqueado_hasta !== null && $this->bloqueado_hasta->isFuture();
    }

    /**
     * Incrementa el contador de intentos y bloquea al alcanzar el límite
     * configurado (config/auth.php → bloqueo_login). Si un bloqueo anterior
     * ya expiró, el conteo empieza de nuevo.
     */
    public function registrarIntentoFallido(): void
    {
        $bloqueoExpirado = $this->bloqueado_hasta !== null && ! $this->estaBloqueada();
        $intentos = ($bloqueoExpirado ? 0 : (int) $this->intentos_fallidos) + 1;

        $this->forceFill([
            'intentos_fallidos' => $intentos,
            'bloqueado_hasta' => $intentos >= config('auth.bloqueo_login.intentos')
                ? now()->addMinutes(config('auth.bloqueo_login.minutos'))
                : null,
        ])->save();
    }

    /** Reinicia el contador tras un login exitoso. */
    public function limpiarIntentosFallidos(): void
    {
        $this->forceFill(['intentos_fallidos' => 0, 'bloqueado_hasta' => null])->save();
    }

    /** Inscripción activa (no dada de baja y con acceso local) en el curso. */
    public function estaMatriculadoEn(Curso $curso): bool
    {
        return $this->inscripciones()
            ->where('curso_id', $curso->id)
            ->where('estado', 'activo')
            ->where('activo_local', true)
            ->exists();
    }

    // ==========================================
    // RELACIONES ELOQUENT DEL LMS
    // ==========================================

    /** Cursos donde es profesor titular. */
    public function cursosComoProfesor(): HasMany
    {
        return $this->hasMany(Curso::class, 'profesor_id');
    }

    /** ERS RF-23: cursos donde es profesor adicional (co-docente). */
    public function cursosComoProfesorAdicional(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'curso_profesor', 'profesor_id', 'curso_id')->withTimestamps();
    }

    /**
     * Todos los cursos que dicta, como titular o como profesor adicional.
     *
     * @return Builder<Curso>
     */
    public function cursosComoDocente(): Builder
    {
        return Curso::query()->dictadosPor($this);
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
