<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RoleAndUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Roles del sistema según documento[cite: 1]
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $profesorRole = Role::firstOrCreate(['name' => 'profesor']);
        $estudianteRole = Role::firstOrCreate(['name' => 'estudiante']);

        // 2. Administrador de prueba[cite: 1]
        $admin = User::firstOrCreate(
            ['email' => 'admin@lms.edu.pe'],
            [
                'name' => 'Administrador General',
                'password' => Hash::make('password123'),
            ]
        );
        $admin->syncRoles([$adminRole]);

        // 3. Profesor de prueba[cite: 1]
        $profesor = User::firstOrCreate(
            ['email' => 'profesor@lms.edu.pe'],
            [
                'name' => 'Prof. Juan Perez',
                'password' => Hash::make('password123'),
            ]
        );
        $profesor->syncRoles([$profesorRole]);

        // 4. Estudiante de prueba[cite: 1]
        $estudiante = User::firstOrCreate(
            ['email' => 'estudiante@lms.edu.pe'],
            [
                'name' => 'Estudiante Demo',
                'password' => Hash::make('password123'),
            ]
        );
        $estudiante->syncRoles([$estudianteRole]);
    }
}
