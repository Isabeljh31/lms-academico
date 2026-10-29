<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Estudiante\PropuestasBot;
use App\Livewire\EventoCalendario\Index as Calendario;
use App\Models\Curso;
use App\Models\EventoCalendario;
use App\Models\Modulo;
use App\Models\PropuestaBot;
use App\Models\Tarea;
use App\Models\User;
use App\Services\Bot\PropuestaBotService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Módulo 7 — el bot nunca debe tener un camino de código que modifique
 * eventos_calendario sin pasar por una propuesta aprobada explícitamente
 * por el humano (ver ARQUITECTURA.md, sección de gobernanza).
 */
class PropuestaBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Rol::cases() as $rol) {
            Role::firstOrCreate(['name' => $rol->value]);
        }
    }

    protected function estudianteConTareaAtrasada(): array
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now()->subWeek()]);

        $tarea = Tarea::factory()->create([
            'curso_id' => $curso->id,
            'modulo_id' => $modulo->id,
            'fecha_entrega' => now()->subDays(3),
        ]);

        return [$estudiante, $curso, $tarea];
    }

    public function test_detectar_atrasos_crea_una_propuesta_pendiente_y_no_toca_el_calendario(): void
    {
        [$estudiante, $curso, $tarea] = $this->estudianteConTareaAtrasada();

        $creadas = app(PropuestaBotService::class)->detectarAtrasos();

        $this->assertSame(1, $creadas);
        $this->assertDatabaseHas('propuestas_bot', [
            'usuario_id' => $estudiante->id,
            'tipo' => 'redistribucion_plan',
            'estado' => 'pendiente',
            'aprobado_por' => null,
        ]);
        $this->assertSame(0, EventoCalendario::count());
    }

    public function test_detectar_atrasos_no_duplica_propuestas_ya_pendientes(): void
    {
        [$estudiante, $curso, $tarea] = $this->estudianteConTareaAtrasada();

        app(PropuestaBotService::class)->detectarAtrasos();
        $segundaPasada = app(PropuestaBotService::class)->detectarAtrasos();

        $this->assertSame(0, $segundaPasada);
        $this->assertSame(1, PropuestaBot::count());
    }

    public function test_rechazar_una_propuesta_no_modifica_el_calendario(): void
    {
        [$estudiante, $curso, $tarea] = $this->estudianteConTareaAtrasada();
        app(PropuestaBotService::class)->detectarAtrasos();
        $propuesta = PropuestaBot::where('usuario_id', $estudiante->id)->firstOrFail();

        Livewire::actingAs($estudiante)
            ->test(PropuestasBot::class)
            ->call('rechazar', $propuesta->id)
            ->assertHasNoErrors();

        $propuesta->refresh();
        $this->assertSame('rechazado', $propuesta->estado);
        $this->assertSame($estudiante->id, $propuesta->aprobado_por);
        $this->assertSame(0, EventoCalendario::count());
    }

    public function test_aceptar_una_propuesta_crea_exactamente_un_evento_de_calendario(): void
    {
        [$estudiante, $curso, $tarea] = $this->estudianteConTareaAtrasada();
        app(PropuestaBotService::class)->detectarAtrasos();
        $propuesta = PropuestaBot::where('usuario_id', $estudiante->id)->firstOrFail();

        Livewire::actingAs($estudiante)
            ->test(PropuestasBot::class)
            ->call('aceptar', $propuesta->id)
            ->assertHasNoErrors();

        $propuesta->refresh();
        $this->assertSame('aprobado', $propuesta->estado);
        $this->assertSame($estudiante->id, $propuesta->aprobado_por);
        $this->assertNotNull($propuesta->fecha_respuesta);

        $this->assertSame(1, EventoCalendario::count());
        $this->assertDatabaseHas('eventos_calendario', [
            'usuario_id' => $estudiante->id,
            'tipo' => 'recordatorio',
        ]);
    }

    public function test_un_estudiante_no_puede_responder_la_propuesta_de_otro_estudiante(): void
    {
        [$estudiante, $curso, $tarea] = $this->estudianteConTareaAtrasada();
        app(PropuestaBotService::class)->detectarAtrasos();
        $propuesta = PropuestaBot::where('usuario_id', $estudiante->id)->firstOrFail();

        $intruso = User::factory()->create()->assignRole(Rol::Estudiante->value);

        // La query ya está acotada por usuario_id: para el intruso, la propuesta
        // de otro estudiante ni siquiera existe (404), no solo está prohibida.
        try {
            Livewire::actingAs($intruso)
                ->test(PropuestasBot::class)
                ->call('aceptar', $propuesta->id);

            $this->fail('Se esperaba una ModelNotFoundException.');
        } catch (ModelNotFoundException) {
            // esperado
        }

        $this->assertSame(0, EventoCalendario::count());
    }

    public function test_ejecutar_propuesta_aprobada_rechaza_una_propuesta_que_no_esta_aprobada(): void
    {
        [$estudiante, $curso, $tarea] = $this->estudianteConTareaAtrasada();
        app(PropuestaBotService::class)->detectarAtrasos();
        $propuesta = PropuestaBot::where('usuario_id', $estudiante->id)->firstOrFail();

        $this->assertSame('pendiente', $propuesta->estado);

        try {
            app(PropuestaBotService::class)->ejecutarPropuestaAprobada($propuesta);
            $this->fail('Se esperaba que ejecutarPropuestaAprobada() rechazara una propuesta no aprobada.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }

        $this->assertSame(0, EventoCalendario::count());
    }

    public function test_no_puede_volver_a_responder_una_propuesta_ya_resuelta(): void
    {
        [$estudiante, $curso, $tarea] = $this->estudianteConTareaAtrasada();
        app(PropuestaBotService::class)->detectarAtrasos();
        $propuesta = PropuestaBot::where('usuario_id', $estudiante->id)->firstOrFail();

        Livewire::actingAs($estudiante)->test(PropuestasBot::class)->call('aceptar', $propuesta->id);

        // Ya está "aprobado": un segundo intento de responder debe fallar (policy exige "pendiente").
        Livewire::actingAs($estudiante)
            ->test(PropuestasBot::class)
            ->call('rechazar', $propuesta->id)
            ->assertForbidden();

        $this->assertSame(1, EventoCalendario::count());
    }

    public function test_la_bandeja_precarga_la_fecha_propuesta_y_usa_el_color_del_curso_del_calendario(): void
    {
        [$estudiante, $curso, $tarea] = $this->estudianteConTareaAtrasada();
        app(PropuestaBotService::class)->detectarAtrasos();
        $propuesta = PropuestaBot::where('usuario_id', $estudiante->id)->firstOrFail();
        $fechaPropuesta = substr($propuesta->datos_propuesta['nueva_fecha_recordatorio'], 0, 16);
        $colorDelCalendario = Livewire::actingAs($estudiante)->test(Calendario::class)->instance()->coloresCursos[$curso->id];

        Livewire::actingAs($estudiante)
            ->test(PropuestasBot::class)
            ->assertSet("fechasAjustadas.{$propuesta->id}", $fechaPropuesta)
            ->assertSee('Por revisar')
            ->assertSee('Atraso detectado')
            ->assertSee("background-color: {$colorDelCalendario}", false)
            ->assertSee(route('evento-calendario.index', [
                'fecha' => substr($fechaPropuesta, 0, 10),
                'evento' => 'tarea-'.$tarea->id,
                'desde' => 'propuestas',
            ]));
    }

    public function test_aceptar_con_fecha_ajustada_crea_el_recordatorio_en_esa_fecha_y_lo_enlaza(): void
    {
        [$estudiante] = $this->estudianteConTareaAtrasada();
        app(PropuestaBotService::class)->detectarAtrasos();
        $propuesta = PropuestaBot::where('usuario_id', $estudiante->id)->firstOrFail();

        Livewire::actingAs($estudiante)
            ->test(PropuestasBot::class)
            ->set("fechasAjustadas.{$propuesta->id}", '2026-12-01T09:30')
            ->call('aceptar', $propuesta->id)
            ->assertHasNoErrors()
            ->assertSee('Estás al día')
            ->assertSee('Resueltas');

        $evento = EventoCalendario::sole();
        $this->assertSame('2026-12-01 09:30', $evento->fecha->format('Y-m-d H:i'));
        $this->assertSame($evento->id, $propuesta->fresh()->datos_propuesta['evento_calendario_id']);

        Livewire::actingAs($estudiante)
            ->test(PropuestasBot::class)
            ->assertSee(route('evento-calendario.index', ['fecha' => '2026-12-01', 'evento' => 'evento-'.$evento->id, 'desde' => 'propuestas']));
    }

    public function test_una_fecha_invalida_no_acepta_la_propuesta_ni_toca_el_calendario(): void
    {
        [$estudiante] = $this->estudianteConTareaAtrasada();
        app(PropuestaBotService::class)->detectarAtrasos();
        $propuesta = PropuestaBot::where('usuario_id', $estudiante->id)->firstOrFail();

        Livewire::actingAs($estudiante)
            ->test(PropuestasBot::class)
            ->set("fechasAjustadas.{$propuesta->id}", 'no-es-una-fecha')
            ->call('aceptar', $propuesta->id)
            ->assertHasErrors("fechasAjustadas.{$propuesta->id}");

        $this->assertSame('pendiente', $propuesta->fresh()->estado);
        $this->assertSame(0, EventoCalendario::count());
    }
}
