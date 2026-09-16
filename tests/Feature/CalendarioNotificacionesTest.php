<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\EventoCalendario\Index;
use App\Livewire\Tareas\Detalle;
use App\Livewire\Tareas\Formulario;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\EventoCalendario;
use App\Models\Modulo;
use App\Models\Tarea;
use App\Models\User;
use App\Notifications\NuevaTareaAsignada;
use App\Notifications\TareaCalificada;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CalendarioNotificacionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Rol::cases() as $rol) {
            Role::firstOrCreate(['name' => $rol->value]);
        }
    }

    public function test_usuario_puede_crear_un_evento_propio_en_su_calendario(): void
    {
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        Livewire::actingAs($estudiante)
            ->test(Index::class)
            ->set('nuevoTitulo', 'Repasar unidad 2')
            ->set('nuevaFecha', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('nuevoTipo', 'bloque_estudio')
            ->call('crear')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('eventos_calendario', [
            'usuario_id' => $estudiante->id,
            'titulo' => 'Repasar unidad 2',
        ]);
    }

    public function test_usuario_no_puede_eliminar_el_evento_de_otro_usuario(): void
    {
        $victima = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $atacante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        $evento = EventoCalendario::create([
            'usuario_id' => $victima->id,
            'titulo' => 'Evento privado',
            'fecha' => now()->addDay(),
            'tipo' => 'recordatorio',
        ]);

        try {
            Livewire::actingAs($atacante)
                ->test(Index::class)
                ->call('eliminar', $evento->id);

            $this->fail('Se esperaba una ModelNotFoundException.');
        } catch (ModelNotFoundException) {
            // esperado: el evento no pertenece al atacante.
        }

        $this->assertDatabaseHas('eventos_calendario', ['id' => $evento->id]);
    }

    public function test_estudiante_inscrito_ve_las_fechas_de_entrega_de_sus_tareas_en_el_calendario(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $tarea = Tarea::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id, 'titulo' => 'Práctica X']);

        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now()]);

        $eventos = Livewire::actingAs($estudiante)
            ->test(Index::class)
            ->get('eventos');

        $titulos = collect($eventos)->pluck('title')->implode(' ');
        $this->assertStringContainsString('Práctica X', $titulos);
    }

    public function test_se_notifica_a_los_inscritos_cuando_se_crea_una_tarea(): void
    {
        Notification::fake();

        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now()]);

        Livewire::actingAs($profesor)
            ->test(Formulario::class, ['curso' => $curso])
            ->set('titulo', 'Tarea nueva')
            ->set('modulo_id', $modulo->id)
            ->set('fecha_entrega', now()->addWeek()->format('Y-m-d\TH:i'))
            ->set('peso_nota', 5)
            ->call('guardar');

        Notification::assertSentTo($estudiante, NuevaTareaAsignada::class);
    }

    public function test_se_notifica_al_estudiante_cuando_se_califica_su_entrega(): void
    {
        Notification::fake();

        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $tarea = Tarea::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id]);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $entrega = Entrega::factory()->create(['tarea_id' => $tarea->id, 'estudiante_id' => $estudiante->id]);

        Livewire::actingAs($profesor)
            ->test(Detalle::class, ['tarea' => $tarea])
            ->set("notas.{$entrega->id}", '15')
            ->call('calificar', $entrega->id);

        Notification::assertSentTo($estudiante, TareaCalificada::class);
    }
}
