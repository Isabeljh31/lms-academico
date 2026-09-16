<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

// <-- 1. Importar Spatie

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable; // <-- 2. Agregar HasRoles aquí[cite: 1]

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_url', // <-- 3. Permitir guardar la URL de foto de Cloudinary[cite: 1]
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
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

    public function rolPrincipal(): ?string
    {
        return $this->getRoleNames()->first();
    }
}
