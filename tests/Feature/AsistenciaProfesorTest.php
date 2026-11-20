<?php

namespace Tests\Feature;

use App\Livewire\Asistencia\Index;
use App\Livewire\Profesor\Asistencia\Tablero;
use App\Models\Asistencia;
use App\Models\Curso;
use App\Models\LeccionCompletada;
use App\Models\Modulo;
use App\Models\SesionClase;
use App\Models\User;
use App\Services\Asistencia\CalculadoraAsistencia;
use App\Services\Reportes\AsistenciaProfesor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Asistencia del Profesor (/cursos/{id}/asistencia, maquetas «Asistencia»):
 * semana por defecto y en la URL, clases por día, «sin lista», indicadores,
 * resumen por estudiante con el criterio oficial (CalculadoraAsistencia),
 * curso virtual y acceso. El Supervisor conserva su pantalla.
 */
class AsistenciaProfesorTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    protected User $profesor;

    protected Curso $curso;

    /** @var array<string, User> */
    protected array $estudiantes = [];

    /** @var array<string, SesionClase> */
    protected array $sesiones = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearRoles();
        // Miércoles 11 de noviembre de 2026: el curso que empezó el lunes 19/10 está en su semana 4.
        $this->travelTo(Carbon::parse('2026-11-11 10:00:00'));

        $this->profesor = $this->usuarioConRol('profesor');
        $this->curso = Curso::factory()->create(['profesor_id' => $this->profesor->id, 'nombre' => 'Estadística I', 'estado' => 'publicado',
            'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-11-29', 'semanas_duracion' => 6, 'modalidad' => 'presencial', 'usa_asistencia' => true,
            'horario_bloques' => [['dia' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '10:00', 'aula' => '204']]]);

        foreach (['ana' => 'Ana Activa', 'beto' => 'Beto Bajo', 'ciro' => 'Ciro Cruz', 'rita' => 'Rita Retirada'] as $clave => $nombre) {
            $this->estudiantes[$clave] = $this->usuarioConRol('estudiante', ['name' => $nombre]);
            $this->matricular($this->estudiantes[$clave], $this->curso, $clave === 'rita' ? ['estado' => 'inactivo'] : []);
        }

        $sesion = fn (string $titulo, string $fecha, ?string $descripcion = null, int $minutos = 120) => SesionClase::create(['curso_id' => $this->curso->id,
            'titulo' => $titulo, 'fecha' => $fecha, 'duracion_minutos' => $minutos, 'descripcion' => $descripcion]);

        $this->sesiones = [
            's1' => $sesion('Teoría 1', '2026-10-19 08:00', 'Aula: 204'),
            's2' => $sesion('Teoría 2', '2026-10-26 08:00', 'Aula: 204'),
            // Semana 4: dos clases el mismo lunes; la segunda ya pasó y no tiene lista.
            's3' => $sesion('Teoría 4', '2026-11-09 08:00', 'Aula: 204'),
            's4' => $sesion('Laboratorio 4', '2026-11-09 10:00', 'Laboratorio de cómputo 3'),
            's5' => $sesion('Teoría 5', '2026-11-16 08:00', 'Aula: 204'),
            's6' => $sesion('Teoría 6', '2026-11-23 08:00', 'Aula: 204'),
        ];

        $this->marcar('s1', ['ana' => 'presente', 'beto' => 'presente', 'ciro' => 'ausente', 'rita' => 'presente']);
        $this->marcar('s2', ['ana' => 'presente', 'beto' => 'tardanza', 'ciro' => 'justificado']);
        $this->marcar('s3', ['ana' => 'presente', 'beto' => 'ausente', 'ciro' => 'presente']);
    }

    /**
     * @param  array<string, string>  $estados
     */
    protected function marcar(string $sesion, array $estados): void
    {
        foreach ($estados as $estudiante => $estado) {
            Asistencia::create(['sesion_id' => $this->sesiones[$sesion]->id, 'estudiante_id' => $this->estudiantes[$estudiante]->id, 'estado' => $estado]);
        }
    }

    public function test_el_profesor_ve_la_pantalla_nueva_con_la_semana_actual_y_sus_dos_clases_del_mismo_dia(): void
    {
        $this->actingAs($this->profesor)->get(route('asistencia.index', $this->curso))
            ->assertOk()
            ->assertSee('Modo: lista por clase')
            ->assertSee('Volver a Cursos')
            ->assertSeeInOrder(['Semana 4', 'LUNES 9 NOV · 2 clases', 'Teoría 4', '08:00 – 10:00', 'Aula 204', 'Laboratorio 4', '10:00 – 12:00', 'Aún no se pasó lista'])
            // La descripción que no tiene el formato «Aula: X» no se muestra como aula.
            ->assertDontSee('Aula Laboratorio de cómputo 3')
            ->assertDontSee('Nueva sesión')
            ->assertSee('Nueva clase')
            ->assertSee('Omitir asistencia en este curso');
    }

    public function test_la_semana_va_en_la_url_y_cambiar_de_semana_muestra_solo_sus_clases(): void
    {
        Livewire::withQueryParams(['semana' => 1])->actingAs($this->profesor)->test(Tablero::class, ['curso' => $this->curso])
            ->assertSet('semana', 1)
            ->assertSee('Teoría 1')
            ->assertDontSee('Teoría 4')
            ->call('irASemana', 4)
            ->assertSet('semana', 4)
            ->assertSee('Teoría 4')
            ->assertDontSee('Teoría 1')
            ->call('semanaSiguiente')
            ->assertSet('semana', 5)
            ->call('irAEstaSemana')
            ->assertSet('semana', 4);

        // Una semana fuera de rango vuelve a la semana actual.
        Livewire::withQueryParams(['semana' => 99])->actingAs($this->profesor)->test(Tablero::class, ['curso' => $this->curso])
            ->assertSet('semana', 4);
    }

    public function test_los_porcentajes_replican_el_criterio_oficial_de_la_calculadora(): void
    {
        $servicio = app(AsistenciaProfesor::class);
        $resumen = $servicio->resumen($this->curso);
        $filas = collect($servicio->estudiantes($this->curso, $resumen['clases'])->items())->keyBy('nombre');
        $calculadora = app(CalculadoraAsistencia::class);

        foreach (['ana', 'beto', 'ciro'] as $clave) {
            $estudiante = $this->estudiantes[$clave];
            $this->assertSame($calculadora->porcentajeDe($estudiante, $this->curso), $filas[$estudiante->name]['porcentaje'], $estudiante->name);
        }

        // Ana 3/4 = 75; Beto 2/4 = 50 (la clase sin lista cuenta como ausente); Ciro 1/3 (justificado fuera del total).
        $this->assertSame(75.0, $filas['Ana Activa']['porcentaje']);
        $this->assertSame(50.0, $filas['Beto Bajo']['porcentaje']);
        $this->assertSame(33.3, $filas['Ciro Cruz']['porcentaje']);
        $this->assertSame(1, $filas['Ciro Cruz']['justificados']);
        $this->assertSame(2, $filas['Ciro Cruz']['ausentes']);
        // La estudiante retirada no aparece ni cuenta.
        $this->assertArrayNotHasKey('Rita Retirada', $filas->all());
        $this->assertSame(3, $resumen['activos']);
        $this->assertSame(52.8, $resumen['promedio']);
        $this->assertSame(['riesgo' => 2, 'atencion' => 1, 'al_dia' => 0], $resumen['niveles']);
    }

    public function test_los_indicadores_y_la_semana_cuentan_igual_la_clase_sin_lista(): void
    {
        $resumen = app(AsistenciaProfesor::class)->resumen($this->curso);

        $this->assertSame([$this->sesiones['s4']->id], $resumen['sin_lista']->pluck('id')->all());
        $this->assertSame(1, $resumen['semanas'][4]['sin_lista']);
        $this->assertSame(3, $resumen['realizadas']);
        $this->assertSame($this->sesiones['s5']->id, $resumen['proxima']['id']);
        $this->assertSame('programada', $resumen['clases']->firstWhere('id', $this->sesiones['s6']->id)['estado']);
        $this->assertSame(['completa', 'completa', 'sin_clases', 'pendiente', 'proxima', 'programada'],
            collect($resumen['semanas'])->pluck('estado')->values()->all());
        // Asistencia de la semana 4: solo la clase con lista (2 de 3).
        $this->assertSame(66.7, $resumen['semanas'][4]['porcentaje']);

        Livewire::actingAs($this->profesor)->test(Tablero::class, ['curso' => $this->curso])
            ->assertSeeInOrder(['Sin pasar lista', '1', 'Laboratorio 4', 'Pasar lista ahora']);
    }

    public function test_el_resumen_filtra_por_riesgo_y_ordena_de_menor_a_mayor_asistencia(): void
    {
        Livewire::actingAs($this->profesor)->test(Tablero::class, ['curso' => $this->curso])
            ->call('verPestana', 'resumen')
            ->assertSeeInOrder(['Ciro Cruz', 'Beto Bajo', 'Ana Activa'])
            ->assertDontSee('Rita Retirada')
            ->call('verNivel', 'riesgo')
            ->assertSee('Ciro Cruz')
            ->assertDontSee('Ana Activa')
            ->set('buscar', 'beto')
            ->assertSee('Beto Bajo')
            ->assertDontSee('Ciro Cruz');
    }

    public function test_pasar_lista_desde_la_pantalla_nueva_usa_las_acciones_existentes(): void
    {
        Livewire::actingAs($this->profesor)->test(Index::class, ['curso' => $this->curso])
            ->call('abrirPaseLista', $this->sesiones['s4']->id)
            ->assertSee('Guardar asistencia')
            ->call('marcarTodos', 'presente')
            ->call('guardarAsistencia')
            ->assertHasNoErrors();

        $this->assertSame(3, Asistencia::where('sesion_id', $this->sesiones['s4']->id)->where('estado', 'presente')->count());
        $this->assertSame(0, app(AsistenciaProfesor::class)->resumen($this->curso)['sin_lista']->count());
    }

    public function test_las_consultas_no_crecen_con_los_estudiantes_ni_con_la_semana(): void
    {
        $contar = function (callable $accion): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $accion();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $componente = Livewire::actingAs($this->profesor)->test(Tablero::class, ['curso' => $this->curso]);

        $semana = $contar(fn () => $componente->call('irASemana', 1));
        $resumen = $contar(fn () => $componente->call('verPestana', 'resumen'));

        foreach (range(1, 6) as $numero) {
            $this->matricular($this->usuarioConRol('estudiante', ['name' => "Extra {$numero}"]), $this->curso);
        }

        $this->assertSame($resumen, $contar(fn () => $componente->call('gotoPage', 1, 'pagina')));
        $componente->call('verPestana', 'semana');
        $this->assertSame($semana, $contar(fn () => $componente->call('irASemana', 4)));
    }

    public function test_un_curso_remoto_se_trata_como_presencial(): void
    {
        $this->curso->update(['modalidad' => 'remoto']);

        $this->actingAs($this->profesor)->get(route('asistencia.index', $this->curso))
            ->assertOk()
            ->assertSee('Modo: lista por clase')
            ->assertSee('Teoría 4')
            ->assertSee('Sin pasar lista');
    }

    public function test_un_curso_virtual_muestra_el_aviso_y_las_lecciones_completadas_de_la_semana(): void
    {
        $this->curso->update(['modalidad' => 'virtual']);
        $modulo = Modulo::create(['curso_id' => $this->curso->id, 'semana' => 4, 'titulo' => 'Semana 4', 'materiales' => [], 'estado' => 'publicado']);
        $lecciones = collect(['Lectura 1', 'Video 1'])->map(fn (string $titulo, int $orden) => $modulo->lecciones()->create(['titulo' => $titulo, 'estado' => 'publicado', 'orden' => $orden]));
        LeccionCompletada::create(['leccion_id' => $lecciones[0]->id, 'estudiante_id' => $this->estudiantes['ana']->id, 'completada_en' => now()]);

        $this->actingAs($this->profesor)->get(route('asistencia.index', $this->curso))
            ->assertOk()
            ->assertSee('Este curso no registra asistencia por clase')
            ->assertSee('Lecciones completadas')
            ->assertSeeInOrder(['Ana Activa', '1 de 2'])
            ->assertDontSee('Estudiantes en riesgo')
            ->assertDontSee('Pasar lista');
    }

    public function test_solo_el_profesor_del_curso_entra_y_el_supervisor_conserva_su_pantalla(): void
    {
        $otroProfesor = $this->usuarioConRol('profesor');
        $supervisor = $this->usuarioConRol('supervisor');

        $this->actingAs($otroProfesor)->get(route('asistencia.index', $this->curso))->assertForbidden();
        $this->actingAs($this->estudiantes['ana'])->get(route('asistencia.index', $this->curso))->assertForbidden();
        Livewire::actingAs($otroProfesor)->test(Tablero::class, ['curso' => $this->curso])->assertForbidden();
        Livewire::actingAs($supervisor)->test(Tablero::class, ['curso' => $this->curso])->assertForbidden();

        $this->actingAs($supervisor)->get(route('asistencia.index', $this->curso))
            ->assertOk()
            ->assertSee('sesiones registradas')
            ->assertDontSee('Modo: lista por clase')
            ->assertDontSee('tema-profesor', false);

        // El Supervisor sigue consultando cualquier lista, también la de una clase futura (solo lectura).
        Livewire::actingAs($supervisor)->test(Index::class, ['curso' => $this->curso])
            ->call('abrirPaseLista', $this->sesiones['s6']->id)
            ->assertSet('vista', 'pase_lista')
            ->assertSee('Sesión #');
    }

    public function test_la_pantalla_y_el_pase_de_lista_usan_el_layout_del_profesor_sin_marcadores_sueltos(): void
    {
        $this->actingAs($this->profesor)->get(route('asistencia.index', $this->curso))
            ->assertOk()
            ->assertSee('class="tema-profesor', false)
            ->assertDontSee('< wire:id', false);

        Livewire::actingAs($this->profesor)->test(Index::class, ['curso' => $this->curso])
            ->call('abrirPaseLista', $this->sesiones['s3']->id)
            ->assertSee('Volver a la semana')
            ->assertSee('Teoría 4')
            ->assertSeeText('3 de 3 marcados')
            ->assertSee('Guardar asistencia')
            ->assertDontSee('< wire:id', false)
            ->assertDontSee('Solo lectura');
    }

    public function test_guardar_conserva_cada_uno_de_los_cuatro_estados(): void
    {
        foreach (['presente', 'tardanza', 'justificado', 'ausente'] as $estado) {
            Livewire::actingAs($this->profesor)->test(Index::class, ['curso' => $this->curso])
                ->call('abrirPaseLista', $this->sesiones['s4']->id)
                ->call('marcarTodos', $estado)
                ->call('guardarAsistencia')
                ->assertHasNoErrors();

            $this->assertSame([$estado], Asistencia::where('sesion_id', $this->sesiones['s4']->id)->distinct()->pluck('estado')->all());
        }
    }

    public function test_no_se_toma_ni_modifica_la_lista_de_una_clase_futura(): void
    {
        $futura = $this->sesiones['s5'];

        Livewire::actingAs($this->profesor)->test(Index::class, ['curso' => $this->curso])
            ->call('abrirPaseLista', $futura->id)
            ->assertSet('vista', 'sesiones')
            ->assertSet('sesionActivaId', null)
            ->assertSee(SesionClase::MENSAJE_CLASE_FUTURA);

        // El id de la sesión activa está bloqueado: no se puede apuntar a otra clase desde el navegador.
        $this->assertThrows(fn () => Livewire::actingAs($this->profesor)->test(Index::class, ['curso' => $this->curso])
            ->set('sesionActivaId', $futura->id));

        // Si la clase se movió a un día futuro con el pase de lista abierto, guardar y marcar todos se rechazan.
        $componente = Livewire::actingAs($this->profesor)->test(Index::class, ['curso' => $this->curso])
            ->call('abrirPaseLista', $this->sesiones['s4']->id);
        $this->sesiones['s4']->update(['fecha' => '2026-11-12 08:00']);
        $componente->call('marcarTodos', 'presente')
            ->assertSee(SesionClase::MENSAJE_CLASE_FUTURA)
            ->set('marcas', collect($this->estudiantes)->except('rita')->mapWithKeys(fn (User $e) => [$e->id => 'presente'])->all())
            ->call('guardarAsistencia')
            ->assertSee(SesionClase::MENSAJE_CLASE_FUTURA);

        $this->assertSame(0, Asistencia::whereIn('sesion_id', [$futura->id, $this->sesiones['s4']->id])->count());
    }

    public function test_una_clase_de_hoy_aunque_sea_mas_tarde_ya_admite_lista(): void
    {
        $hoy = SesionClase::create(['curso_id' => $this->curso->id, 'titulo' => 'Repaso de hoy', 'fecha' => '2026-11-11 18:00', 'duracion_minutos' => 60]);

        Livewire::actingAs($this->profesor)->test(Index::class, ['curso' => $this->curso])
            ->call('abrirPaseLista', $hoy->id)
            ->assertSet('vista', 'pase_lista')
            ->call('marcarTodos', 'presente')
            ->call('guardarAsistencia')
            ->assertHasNoErrors();

        $this->assertSame(3, Asistencia::where('sesion_id', $hoy->id)->count());
    }

    public function test_la_semana_no_ofrece_lista_para_clases_futuras_ni_las_cuenta_sin_lista(): void
    {
        $resumen = app(AsistenciaProfesor::class)->resumen($this->curso);
        $proxima = $resumen['clases']->firstWhere('id', $this->sesiones['s5']->id);

        $this->assertFalse($proxima['permite_lista']);
        $this->assertNotContains($this->sesiones['s5']->id, $resumen['sin_lista']->pluck('id')->all());

        Livewire::withQueryParams(['semana' => 5])->actingAs($this->profesor)->test(Tablero::class, ['curso' => $this->curso])
            ->assertSee('Teoría 5')
            ->assertSee('Disponible el día de la clase')
            ->assertDontSee('abrirPaseLista('.$this->sesiones['s5']->id.')', false);
    }

    public function test_el_pase_de_lista_es_de_solo_lectura_en_un_curso_archivado(): void
    {
        $this->curso->update(['estado' => 'archivado']);

        Livewire::actingAs($this->profesor)->test(Index::class, ['curso' => $this->curso])
            ->call('abrirPaseLista', $this->sesiones['s3']->id)
            ->assertSee('Solo lectura')
            ->assertSeeHtml('disabled')
            ->assertDontSee('Guardar asistencia')
            ->assertDontSee('Todos presentes');
    }

    public function test_un_curso_sin_clases_ni_estudiantes_muestra_estados_vacios(): void
    {
        $vacio = Curso::factory()->create(['profesor_id' => $this->profesor->id, 'estado' => 'borrador', 'fecha_inicio' => '2026-11-02',
            'semanas_duracion' => 4, 'modalidad' => 'presencial', 'usa_asistencia' => true]);

        Livewire::actingAs($this->profesor)->test(Tablero::class, ['curso' => $vacio])
            ->assertSee('Este curso aún no tiene clases')
            ->call('verPestana', 'resumen')
            ->assertSee('No hay estudiantes matriculados');
    }

    public function test_un_curso_archivado_solo_permite_ver_las_listas(): void
    {
        $this->curso->update(['estado' => 'archivado']);

        Livewire::actingAs($this->profesor)->test(Tablero::class, ['curso' => $this->curso])
            ->assertSee('Ver lista')
            ->assertDontSee('Pasar lista ahora')
            ->assertDontSee('Eliminar clase');
    }

    public function test_con_la_asistencia_omitida_se_ofrece_reactivarla(): void
    {
        $this->curso->update(['usa_asistencia' => false]);

        $this->actingAs($this->profesor)->get(route('asistencia.index', $this->curso))
            ->assertOk()
            ->assertSee('Asistencia omitida en este curso')
            ->assertSee('Reactivar el control de asistencia');
    }
}
