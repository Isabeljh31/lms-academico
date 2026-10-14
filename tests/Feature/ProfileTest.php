<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');

        $response
            ->assertOk()
            ->assertSeeVolt('profile.update-profile-information-form')
            ->assertSeeVolt('profile.update-password-form')
            ->assertDontSee('Eliminar cuenta');
    }

    public function test_el_perfil_muestra_nombres_apellidos_dni_y_correo_sin_permitir_editarlos(): void
    {
        $user = User::factory()->create([
            'nombres' => 'María José',
            'apellidos' => 'Pérez Salinas',
            'dni' => '07123456',
            'email' => 'maria.perez@lms.edu.pe',
        ]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertSeeInOrder(['María José', 'Pérez Salinas', '07123456', 'maria.perez@lms.edu.pe'])
            ->assertSee('solicita la corrección al Supervisor')
            ->assertDontSee('wire:model="email"', false);
    }

    public function test_el_usuario_cambia_su_foto_de_perfil(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('profile.update-profile-information-form')
            ->set('foto', UploadedFile::fake()->image('foto.png'))
            ->call('updateProfileInformation')
            ->assertHasNoErrors()
            ->assertDispatched('profile-updated');

        $this->assertNotNull($user->fresh()->avatar_url);
    }

    public function test_guardar_sin_elegir_una_foto_muestra_un_mensaje_claro(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('profile.update-profile-information-form')
            ->call('updateProfileInformation')
            ->assertHasErrors(['foto' => 'Selecciona una imagen para tu foto de perfil.']);
    }

    /**
     * ERS RF-17: ninguna cuenta se elimina físicamente desde la interfaz;
     * solo el Supervisor puede desactivarla. El formulario de Breeze para
     * borrar la propia cuenta se retiró.
     */
    public function test_el_usuario_no_puede_eliminar_su_propia_cuenta(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')->assertOk()->assertDontSeeVolt('profile.delete-user-form');

        $this->assertFalse(view()->exists('livewire.profile.delete-user-form'));
        $this->assertNotNull($user->fresh());
    }
}
