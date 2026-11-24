<?php

namespace Tests\Feature;

use App\Livewire\Admin\SedesSalones\Index;
use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\Facultad;
use App\Models\Salon;
use App\Models\Sede;
use App\Models\SesionClase;
use App\Models\TipoSalon;
use App\Models\User;
use App\Services\Asistencia\AsignacionSalones;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Módulo del Supervisor «Sedes y salones»: acceso, CRUD con bitácora,
 * activar/desactivar, borrado protegido y la asignación exacta de salones a
 * las clases futuras de un curso.
 */
class SedesSalonesSupervisorTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    private User $supervisor;

    private Sede $sede;

    private TipoSalon $tipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearRoles();
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00'));
        $this->supervisor = $this->usuarioConRol('supervisor');
        $this->sede = Sede::factory()->create(['nombre' => 'Los Olivos', 'codigo' => 'LO']);
        $this->tipo = TipoSalon::factory()->create(['nombre' => 'Aula teórica']);
    }

    public function test_solo_el_supervisor_entra_y_la_pantalla_usa_su_diseno(): void
    {
        $this->actingAs($this->usuarioConRol('profesor'))->get(route('admin.sedes-salones.index'))->assertForbidden();
        $this->actingAs($this->usuarioConRol('estudiante'))->get(route('admin.sedes-salones.index'))->assertForbidden();
        $this->expectsAuthorization(fn () => app(AsignacionSalones::class)->asignar($this->usuarioConRol('profesor'), Curso::factory()->create()));

        $this->actingAs($this->supervisor)->get(route('admin.sedes-salones.index'))->assertOk()->assertSee('Sedes, salones y facultades');

        $html = Livewire::actingAs($this->supervisor)->test(Index::class)->call('nuevo', 'salon')->html();
        foreach (['var(--p900)', 'var(--p700)', 'var(--mint)', '#093B3B', 'tema-profesor', '<select'] as $marcaAjena) {
            $this->assertStringNotContainsString($marcaAjena, $html);
        }
        $this->assertStringContainsString('border-arena-medio', $html);
    }

    public function test_crea_edita_y_desactiva_con_bitacora_y_valida_codigos_unicos(): void
    {
        $pantalla = Livewire::actingAs($this->supervisor)->test(Index::class)
            ->call('nuevo', 'sede')->set('sedeNombre', 'Comas')->set('sedeCodigo', ' co ')->call('guardar')->assertHasNoErrors()
            ->call('nuevo', 'tipo')->set('tipoNombre', 'Laboratorio de cómputo')->call('guardar')->assertHasNoErrors()
            ->call('nuevo', 'salon')
            ->set('salonSedeId', (string) $this->sede->id)->set('salonTipoId', (string) $this->tipo->id)
            ->set('salonCodigo', 'LO-2-04')->set('salonNombre', 'Aula 204')->set('salonCapacidad', '45')
            ->call('guardar')->assertHasNoErrors();

        $this->assertSame('CO', Sede::where('nombre', 'Comas')->sole()->codigo);
        $salon = Salon::where('codigo', 'LO-2-04')->sole();
        $this->assertSame(45, $salon->capacidad);

        $pantalla->call('nuevo', 'salon')
            ->set('salonSedeId', (string) $this->sede->id)->set('salonTipoId', (string) $this->tipo->id)
            ->set('salonCodigo', 'LO-2-04')->set('salonNombre', 'Otro')->set('salonCapacidad', '0')
            ->call('guardar')->assertHasErrors(['salonCodigo', 'salonCapacidad'])
            ->call('editar', 'salon', $salon->id)->assertSet('salonCodigo', 'LO-2-04')
            ->set('salonCapacidad', '50')->call('guardar')->assertHasNoErrors()
            ->call('alternarActivo', 'salon', $salon->id);

        $this->assertSame(50, $salon->fresh()->capacidad);
        $this->assertFalse($salon->fresh()->activo);
        $this->assertSame([
            'Alta de sede «CO · Comas».',
            'Alta de tipo de salón «Laboratorio de cómputo».',
            'Alta de salón «LO-2-04 · Aula 204».',
            'Cambio de salón «LO-2-04 · Aula 204».',
            'Desactivación de salón «LO-2-04 · Aula 204».',
        ], BitacoraAuditoria::where('tipo_evento', 'catalogo_salones')->orderBy('id')->pluck('descripcion')->all());
    }

    public function test_las_facultades_se_registran_por_sede_y_con_cursos_no_se_borran(): void
    {
        $pantalla = Livewire::actingAs($this->supervisor)->test(Index::class)
            ->call('verPestana', 'facultades')
            ->call('nuevo', 'facultad')
            ->set('facultadSedeId', (string) $this->sede->id)->set('facultadNombre', 'Facultad de Ingeniería')->set('facultadCodigo', ' fi-lo ')
            ->call('guardar')->assertHasNoErrors()
            ->assertSee('Facultad de Ingeniería')
            ->call('nuevo', 'facultad')
            ->set('facultadSedeId', '')->set('facultadNombre', 'Otra')->set('facultadCodigo', 'FI-LO')
            ->call('guardar')->assertHasErrors(['facultadSedeId', 'facultadCodigo']);

        $facultad = Facultad::sole();
        $this->assertSame(['FI-LO', $this->sede->id], [$facultad->codigo, $facultad->sede_id]);

        Curso::factory()->create(['sede_id' => $this->sede->id, 'facultad_id' => $facultad->id]);
        $pantalla->call('eliminar', 'facultad', $facultad->id)->assertSee('Esta facultad tiene cursos')
            ->call('eliminar', 'sede', $this->sede->id)->assertSee('Esta sede tiene salones, facultades o cursos')
            ->call('alternarActivo', 'facultad', $facultad->id);

        $this->assertModelExists($facultad);
        $this->assertFalse($facultad->fresh()->activa);
        $this->assertContains('Alta de facultad «FI-LO · Facultad de Ingeniería».', BitacoraAuditoria::where('tipo_evento', 'catalogo_salones')->pluck('descripcion')->all());
    }

    public function test_un_salon_con_clases_futuras_no_se_borra_y_sin_ellas_si(): void
    {
        $salon = $this->salon('A-201');
        $curso = Curso::factory()->create();
        $futura = $this->clase($curso, '2026-11-20 08:00', 'Aula: A-201', $salon);

        Livewire::actingAs($this->supervisor)->test(Index::class)
            ->call('eliminar', 'salon', $salon->id)
            ->assertSee('Este salón tiene clases futuras asignadas: no se puede borrar, desactívalo.');
        $this->assertModelExists($salon);

        $futura->update(['fecha' => '2026-11-10 08:00']);
        Livewire::actingAs($this->supervisor)->test(Index::class)->call('eliminar', 'salon', $salon->id);
        $this->assertModelMissing($salon);
        $this->assertNull($futura->fresh()->salon_id);

        $otro = $this->salon('B-208');
        Livewire::actingAs($this->supervisor)->test(Index::class)
            ->call('eliminar', 'sede', $this->sede->id)->assertSee('Esta sede tiene salones, facultades o cursos')
            ->call('eliminar', 'tipo', $this->tipo->id)->assertSee('Este tipo tiene salones');
        $this->assertModelExists($otro->sede);
        $this->assertModelExists($this->tipo);
    }

    public function test_los_filtros_por_sede_y_tipo_limitan_la_tabla(): void
    {
        $this->salon('A-201');
        Salon::factory()->create(['codigo' => 'CO-1-01']);

        Livewire::actingAs($this->supervisor)->test(Index::class)
            ->assertSee('A-201')->assertSee('CO-1-01')
            ->set('filtroSede', (string) $this->sede->id)
            ->assertSee('A-201')->assertDontSee('CO-1-01')
            ->set('filtroSede', '')->set('filtroTipo', (string) $this->tipo->id)
            ->assertSee('A-201')->assertDontSee('CO-1-01');
    }

    public function test_la_vista_previa_y_la_asignacion_solo_toman_coincidencias_exactas_de_clases_futuras(): void
    {
        Storage::fake('local');
        $a201 = $this->salon('A-201');
        $lab = $this->salon('Lab 1');
        $curso = Curso::factory()->create(['nombre' => 'Bases de Datos']);
        $exacta = $this->clase($curso, '2026-11-20 08:00', "Aula: A-201\nTraer laptop.");
        $lab1 = $this->clase($curso, '2026-11-21 08:00', 'Aula: Lab 1');
        $minusculas = $this->clase($curso, '2026-11-22 08:00', 'Aula: a-201');
        $desconocida = $this->clase($curso, '2026-11-23 08:00', 'Aula: Z-999');
        $pasada = $this->clase($curso, '2026-11-10 08:00', 'Aula: A-201');

        Livewire::actingAs($this->supervisor)->test(Index::class)
            ->call('verPestana', 'asignar')
            ->set('cursoAsignar', (string) $curso->id)
            ->assertSee('Cambiarían')
            ->assertSee('Aula «a-201» sin salón con ese código')
            ->assertSee('Aula «Z-999» sin salón con ese código')
            ->assertViewHas('vistaPrevia', fn (array $vista) => $vista['asignables']->pluck('sesion.id')->all() === [$exacta->id, $lab1->id])
            ->call('asignarSalones')
            ->assertHasNoErrors()
            ->assertSee('Se asignó salón a 2 clases futuras de Bases de Datos.');

        $this->assertSame($a201->id, $exacta->fresh()->salon_id);
        $this->assertSame("Aula: A-201\nTraer laptop.", $exacta->fresh()->descripcion);
        $this->assertSame($lab->id, $lab1->fresh()->salon_id);
        $this->assertNull($minusculas->fresh()->salon_id);
        $this->assertNull($desconocida->fresh()->salon_id);
        $this->assertNull($pasada->fresh()->salon_id);
        $this->assertSame($this->sede->id, $curso->fresh()->sede_id);

        $respaldo = BitacoraAuditoria::where('tipo_evento', 'catalogo_salones')->sole();
        $ruta = json_decode($respaldo->valor_nuevo, true)['respaldo'];
        Storage::disk('local')->assertExists($ruta);
        $this->assertSame([$exacta->id, $lab1->id], array_column(json_decode(Storage::disk('local')->get($ruta), true)['sesiones'], 'id'));
    }

    public function test_con_salones_de_sedes_distintas_no_cambia_la_sede_del_curso(): void
    {
        Storage::fake('local');
        $this->salon('A-201');
        Salon::factory()->create(['codigo' => 'CO-1-01']);
        $curso = Curso::factory()->create();
        $this->clase($curso, '2026-11-20 08:00', 'Aula: A-201');
        $this->clase($curso, '2026-11-21 08:00', 'Aula: CO-1-01');

        $resultado = app(AsignacionSalones::class)->asignar($this->supervisor, $curso);

        $this->assertSame(2, $resultado['asignadas']);
        $this->assertTrue($resultado['sedes_distintas']);
        $this->assertNull($curso->fresh()->sede_id);
    }

    private function salon(string $codigo): Salon
    {
        return Salon::factory()->create(['codigo' => $codigo, 'sede_id' => $this->sede->id, 'tipo_salon_id' => $this->tipo->id]);
    }

    private function clase(Curso $curso, string $fecha, ?string $descripcion, ?Salon $salon = null): SesionClase
    {
        return $curso->sesionesClase()->create([
            'titulo' => 'Clase', 'fecha' => $fecha, 'duracion_minutos' => 90,
            'numero_sesion' => $curso->sesionesClase()->count() + 1, 'descripcion' => $descripcion, 'salon_id' => $salon?->id,
        ]);
    }

    private function expectsAuthorization(callable $accion): void
    {
        try {
            $accion();
            $this->fail('Se esperaba un rechazo de autorización.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }
}
