<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\BotSugerencias\Widget;
use App\Livewire\Cursos\Show;
use App\Livewire\Estudiante\PanelInicio;
use App\Models\Curso;
use App\Models\EventoCalendario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Checklist de pulido final del rol estudiante: mini-calendario del panel
 * y el acceso rápido al bot. El resto del checklist es puramente visual
 * (colores, iconos, espaciado) y se verificó manualmente + por inspección
 * de Blade (ver ARQUITECTURA.md).
 */
class PulidoEstudianteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Rol::cases() as $rol) {
            Role::firstOrCreate(['name' => $rol->value]);
        }
    }

    public function test_el_mini_calendario_agrupa_los_eventos_del_mes_por_dia(): void
    {
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        $diaConEvento = now()->startOfMonth()->addDays(4);
        EventoCalendario::create([
            'usuario_id' => $estudiante->id,
            'titulo' => 'Repasar unidad 3',
            'fecha' => $diaConEvento,
            'tipo' => 'bloque_estudio',
        ]);

        Livewire::actingAs($estudiante)
            ->test(PanelInicio::class)
            ->assertSee('Repasar unidad 3')
            ->assertSee(now()->startOfMonth()->translatedFormat('F'));
    }

    public function test_el_acceso_rapido_del_bot_abre_el_widget_en_el_componente_hermano(): void
    {
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        Livewire::actingAs($estudiante)
            ->test(Widget::class)
            ->assertSet('abierto', false)
            ->dispatch('abrir-bot')
            ->assertSet('abierto', true);
    }

    public function test_curso_sin_portada_muestra_iniciales_en_vez_de_bloque_vacio(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id, 'nombre' => 'Física Aplicada', 'imagen_portada_url' => null]);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now()]);

        Livewire::actingAs($estudiante)
            ->test(Show::class, ['curso' => $curso])
            ->assertSee('FA'); // iniciales de "Física Aplicada"
    }
}
