<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RoleAndUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Roles del sistema (ERS v2 sección 2.3: supervisor, profesor, estudiante).
        foreach (Rol::cases() as $rol) {
            Role::firstOrCreate(['name' => $rol->value]);
        }

        // 2. Supervisor de prueba.
        $admin = User::firstOrCreate(
            ['email' => 'admin@lms.edu.pe'],
            [
                'name' => 'Supervisor General',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles([Rol::Supervisor->value]);

        // 3. Profesor de prueba.
        $profesor = User::firstOrCreate(
            ['email' => 'profesor@lms.edu.pe'],
            [
                'name' => 'Juan Pérez Salinas',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );
        $profesor->syncRoles([Rol::Profesor->value]);

        // 4. Estudiante de prueba.
        $estudiante = User::firstOrCreate(
            ['email' => 'estudiante@lms.edu.pe'],
            [
                'name' => 'Estudiante Demo',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );
        $estudiante->syncRoles([Rol::Estudiante->value]);
    }
}
