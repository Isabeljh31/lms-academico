<?php

namespace App\Models;

use App\Rules\NombrePersona;
use App\Services\Multimedia\SubidorArchivos;
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
        // "name" es el nombre completo; se calcula solo a partir de nombres y apellidos.
        'name',
        // ERS RF-10: nombres y apellidos por separado, y DNI único.
        'nombres',
        'apellidos',
        'dni',
        'codigo',
        'especialidad',
        'celular',
        'anio_nacimiento',
        'correo_personal',
        'apoderado_nombre',
        'apoderado_celular',
        'grado_academico',
        'colegiatura',
        'fecha_inicio_labores',
        'cv_path',
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
            'fecha_inicio_labores' => 'date',
        ];
    }

    /** Edad calculada desde el año de nacimiento; null si no se registró. */
    public function edad(): ?int
    {
        return $this->anio_nacimiento ? now()->year - $this->anio_nacimiento : null;
    }

    /** Enlace temporal para ver/descargar el CV subido, o null si no tiene. */
    public function cvUrl(): ?string
    {
        return SubidorArchivos::enlace($this->cv_path);
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

    protected function nombres(): Attribute
    {
        return Attribute::make(
            set: fn (?string $valor) => $valor === null ? null : (NombrePersona::normalizar($valor) ?: null),
        );
    }

    protected function apellidos(): Attribute
    {
        return Attribute::make(
            set: fn (?string $valor) => $valor === null ? null : (NombrePersona::normalizar($valor) ?: null),
        );
    }

    protected function dni(): Attribute
    {
        return Attribute::make(
            set: fn (?string $valor) => $valor === null ? null : (trim($valor) ?: null),
        );
    }

    /**
     * "name" (nombre completo) se mantiene sincronizado con nombres y
     * apellidos. Si solo se indica "name" (seeders, factories antiguas), se
     * separa: la primera palabra va a nombres y el resto a apellidos.
     */
    protected static function booted(): void
    {
        static::saving(function (User $usuario) {
            if ($usuario->isDirty(['nombres', 'apellidos']) && filled($usuario->nombres)) {
                $usuario->name = trim($usuario->nombres.' '.$usuario->apellidos);

                return;
            }

            if ($usuario->isDirty('name')) {
                $partes = preg_split('/\s+/u', NombrePersona::normalizar((string) $usuario->name), 2);
                $usuario->nombres = $partes[0] ?? null;
                $usuario->apellidos = $partes[1] ?? null;
            }
        });
    }

    /**
     * Foto de perfil con enlace temporal (ERS RNF-06), o un avatar con las
     * iniciales si el usuario no subió foto.
     */
    public function fotoUrl(string $colorFondo = '7FB3AE'): string
    {
        return SubidorArchivos::enlace($this->avatar_url)
            ?? 'https://ui-avatars.com/api/?name='.urlencode((string) $this->name)."&background={$colorFondo}&color=fff";
    }

    /** Para listados ordenados por apellido: "Pérez Salinas, Juan". */
    public function nombreFormal(): string
    {
        return filled($this->apellidos) ? "{$this->apellidos}, {$this->nombres}" : (string) $this->name;
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
     * Cada cuenta tiene un único rol. Solo se permite corregirlo si la cuenta
     * todavía no se usó en ningún curso: sin matrículas, entregas, quizzes,
     * notas, asistencias ni cursos a su cargo.
     */
    public function tieneHistorialAcademico(): bool
    {
        return $this->inscripciones()->exists()
            || $this->entregas()->exists()
            || $this->notasRubro()->exists()
            || ResultadoQuiz::where('estudiante_id', $this->id)->exists()
            || Asistencia::where('estudiante_id', $this->id)->exists()
            || $this->cursosComoProfesor()->exists()
            || $this->cursosComoProfesorAdicional()->exists();
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
