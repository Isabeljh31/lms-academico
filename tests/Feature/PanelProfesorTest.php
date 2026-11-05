<?php

namespace Tests\Feature;

use App\Livewire\Docente\Panel;
use App\Livewire\Estudiante\PanelInicio;
use App\Livewire\Reportes\PanelGestion;
use App\Models\Comunicado;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\EsquemaEvaluacion;
use App\Models\Grupo;
use App\Models\Modulo;
use App\Models\Quiz;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Panel del Profesor con layout propio (barra inferior): indicadores,
 * "Para hacer ahora", "Lo que se viene" y "Comunicados" (la lista de cursos
 * está en la pantalla Cursos, enlazada desde el indicador), con
 * topes fijos que no crecen con la cantidad de cursos. Estudiante y
 * Supervisor siguen con su panel de siempre.
 */
class PanelProfesorTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearRoles();
        // Miércoles 4 de noviembre de 2026, a media mañana.
        $this->travelTo(Carbon::parse('2026-11-04 10:00:00'));
    }

    protected function cursoDe(User $profesor, array $atributos = []): Curso
    {
        return Curso::factory()->create(['profesor_id' => $profesor->id, 'modalidad' => 'virtual', ...$atributos]);
    }

    protected function entrega(Tarea $tarea, ?Grupo $grupo = null, ?Carbon $fecha = null): Entrega
    {
        $entrega = Entrega::factory()->create([
            'tarea_id' => $tarea->id,
            'estudiante_id' => $this->usuarioConRol('estudiante')->id,
            'grupo_id' => $grupo?->id,
            'estado' => 'entregado',
        ]);

        if ($fecha) {
            $entrega->forceFill(['updated_at' => $fecha])->saveQuietly();
        }

        return $entrega;
    }

    public function test_el_profesor_ve_su_panel_con_el_layout_de_barra_inferior(): void
    {
        $profesor = $this->usuarioConRol('profesor', ['name' => 'Rosaura Quispe Mamani']);
        $this->cursoDe($profesor);

        $this->actingAs($profesor)->get(route('dashboard'))
            ->assertOk()
            ->assertSeeLivewire(Panel::class)
            ->assertDontSeeLivewire(PanelGestion::class)
            ->assertSee('Navegación principal')
            ->assertSee('aria-current="page"', false)
            ->assertSee('Cursos a cargo')
            ->assertSee('name="_token"', false)
            ->assertDontSee('sidebarAbierto')
            // Menú de usuario: primer nombre en la barra; nombre completo y rol dentro del menú.
            ->assertSee('>Rosaura</span>', false)
            ->assertSee('aria-label="Menú de usuario de Rosaura Quispe Mamani"', false)
            ->assertSeeInOrder(['id="menu-usuario"', 'Rosaura Quispe Mamani', 'Rol: Profesor', 'Mi perfil', 'Cerrar sesión'], false);
    }

    public function test_estudiante_y_supervisor_siguen_con_su_panel_de_siempre(): void
    {
        $escenario = $this->escenario();

        $this->actingAs($escenario['estudiante'])->get(route('dashboard'))
            ->assertOk()
            ->assertSeeLivewire(PanelInicio::class)
            ->assertDontSeeLivewire(Panel::class)
            ->assertSee('sidebarAbierto')
            ->assertDontSee('Navegación principal');

        $this->actingAs($escenario['supervisor'])->get(route('dashboard'))
            ->assertOk()
            ->assertSeeLivewire(PanelGestion::class)
            ->assertDontSeeLivewire(Panel::class)
            ->assertSee('Tasa de finalización')
            ->assertDontSee('Navegación principal');
    }

    public function test_el_componente_del_panel_rechaza_a_estudiantes_y_supervisores(): void
    {
        Livewire::actingAs($this->usuarioConRol('estudiante'))->test(Panel::class)->assertForbidden();
        Livewire::actingAs($this->usuarioConRol('supervisor'))->test(Panel::class)->assertForbidden();
    }

    public function test_los_indicadores_cuentan_las_entregas_grupales_una_vez_por_grupo(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $curso = $this->cursoDe($profesor);
        $coDocencia = $this->cursoDe($this->usuarioConRol('profesor'));
        $coDocencia->profesoresAdicionales()->attach($profesor->id);
        $this->cursoDe($profesor, ['estado' => 'archivado']);

        foreach ([$curso, $coDocencia] as $cursoDictado) {
            $this->matricular($this->usuarioConRol('estudiante'), $cursoDictado);
        }

        $grupal = Tarea::factory()->create(['curso_id' => $curso->id, 'tipo_entrega' => 'grupal']);
        $grupo = Grupo::create(['curso_id' => $curso->id, 'nombre' => 'Grupo 1']);
        $this->entrega($grupal, $grupo);
        $this->entrega($grupal, $grupo);
        $this->entrega($grupal, $grupo);
        $individual = Tarea::factory()->create(['curso_id' => $coDocencia->id]);
        $this->entrega($individual);

        $indicadores = Livewire::actingAs($profesor)->test(Panel::class)->viewData('indicadores');

        $this->assertSame(2, $indicadores['cursos']);
        $this->assertSame(2, $indicadores['estudiantes']);
        $this->assertSame(2, $indicadores['porCalificar']);
        $this->assertNull($indicadores['promedio']);
    }

    public function test_para_hacer_ahora_lista_lo_mas_antiguo_primero_y_enlaza_a_calificar(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $curso = $this->cursoDe($profesor);
        $reciente = Tarea::factory()->create(['curso_id' => $curso->id, 'titulo' => 'Informe reciente']);
        $antigua = Tarea::factory()->create(['curso_id' => $curso->id, 'titulo' => 'Informe antiguo']);
        $this->entrega($reciente, fecha: now()->subDay());
        $this->entrega($antigua, fecha: now()->subDays(6));
        $this->entrega($antigua, fecha: now()->subDays(2));

        $componente = Livewire::actingAs($profesor)->test(Panel::class)
            ->assertSee('Calificar la más antigua')
            ->assertSeeInOrder(['Informe antiguo', 'hace 6 días', 'Informe reciente', 'hace 1 día'])
            ->assertSee(route('cursos.tareas.show', [$curso, $antigua]), false)
            ->assertSee('Calificar 2');

        $this->assertSame($antigua->id, $componente->viewData('pendientes')['masAntigua']['tarea_id']);
    }

    public function test_sin_pendientes_muestra_estas_al_dia(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $this->cursoDe($profesor);

        Livewire::actingAs($profesor)->test(Panel::class)
            ->assertSee('Estás al día')
            ->assertDontSee('Calificar la más antigua');
    }

    public function test_un_profesor_sin_cursos_ve_los_estados_vacios(): void
    {
        Livewire::actingAs($this->usuarioConRol('profesor'))->test(Panel::class)
            ->assertSee('Aún no tienes cursos asignados')
            ->assertSee('Sin fechas próximas')
            ->assertSee('Sin comunicados')
            ->assertSee('Sin notas');
    }

    public function test_lo_que_se_viene_solo_muestra_fechas_futuras_y_ordenadas(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $curso = $this->cursoDe($profesor);
        Tarea::factory()->create(['curso_id' => $curso->id, 'titulo' => 'Tarea vencida', 'fecha_entrega' => now()->subHour()]);
        Tarea::factory()->create(['curso_id' => $curso->id, 'titulo' => 'Tarea lejana', 'fecha_entrega' => now()->addDays(9)]);
        Tarea::factory()->create(['curso_id' => $curso->id, 'titulo' => 'Tarea cercana', 'fecha_entrega' => now()->addDays(2)]);
        Quiz::factory()->create(['curso_id' => $curso->id, 'tema' => 'Quiz intermedio', 'fecha_limite' => now()->addDays(5)]);
        Quiz::factory()->create(['curso_id' => $curso->id, 'tema' => 'Quiz cerrado', 'fecha_limite' => now()->subDay()]);

        $componente = Livewire::actingAs($profesor)->test(Panel::class)
            ->assertSeeInOrder(['Tarea cercana', 'en 2 días', 'Quiz intermedio', 'en 5 días', 'Tarea lejana', 'en 9 días'])
            ->assertDontSee('Tarea vencida')
            ->assertDontSee('Quiz cerrado');

        foreach ($componente->viewData('proximas')['filas'] as $item) {
            $this->assertTrue($item['fecha']->isFuture());
        }
    }

    public function test_una_semana_sin_contenido_aparece_y_desaparece_al_subir_contenido(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        // Empieza el lunes 2/11: la semana 2 empieza el lunes 9/11 (en 5 días).
        $curso = $this->cursoDe($profesor, ['fecha_inicio' => '2026-11-02', 'fecha_fin' => '2027-02-28', 'semanas_duracion' => 16]);

        Livewire::actingAs($profesor)->test(Panel::class)
            ->assertSee('La semana 2 empieza el lunes 09/11 y aún no tiene contenido')
            ->assertSee('Subir contenido')
            ->assertDontSee('La semana 1 empieza')
            ->assertDontSee('La semana 4 empieza');

        $semana = Modulo::factory()->create(['curso_id' => $curso->id, 'semana' => 2]);
        $semana->lecciones()->create(['titulo' => 'Lectura de la semana', 'estado' => 'publicado', 'orden' => 1]);

        Livewire::actingAs($profesor)->test(Panel::class)
            ->assertDontSee('La semana 2 empieza')
            ->assertSee('La semana 3 empieza el lunes 16/11');
    }

    public function test_un_curso_sin_fecha_de_inicio_no_rompe_el_panel(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $this->cursoDe($profesor, ['nombre' => 'Curso sin fechas', 'fecha_inicio' => null, 'fecha_fin' => null]);

        Livewire::actingAs($profesor)->test(Panel::class)
            ->assertOk()
            ->assertViewHas('indicadores', fn (array $indicadores) => $indicadores['cursos'] === 1)
            ->assertDontSee('aún no tiene contenido');
    }

    public function test_avisa_la_evaluacion_cercana_sin_actividades_asignadas(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $curso = $this->cursoDe($profesor, ['fecha_inicio' => '2026-11-02', 'fecha_fin' => '2027-02-28']);
        $esquema = EsquemaEvaluacion::create(['curso_id' => $curso->id]);
        $esquema->rubros()->create(['nombre' => 'Primer parcial', 'semana' => 3, 'peso' => 40, 'orden' => 1]);
        $esquema->rubros()->create(['nombre' => 'Parcial lejano', 'semana' => 12, 'peso' => 60, 'orden' => 2]);

        Livewire::actingAs($profesor)->test(Panel::class)
            ->assertSee('La evaluación «Primer parcial» (semana 3) aún no tiene actividades asignadas')
            ->assertSee(route('cursos.actividades', $curso), false)
            ->assertDontSee('Parcial lejano');
    }

    public function test_los_filtros_de_lo_que_se_viene_y_de_pendientes_funcionan(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $fisica = $this->cursoDe($profesor, ['nombre' => 'Física Aplicada', 'fecha_inicio' => '2026-11-02']);
        $arte = $this->cursoDe($profesor, ['nombre' => 'Arte Moderno']);
        Tarea::factory()->create(['curso_id' => $arte->id, 'titulo' => 'Ensayo de arte', 'fecha_entrega' => now()->addDays(3)]);
        $this->entrega(Tarea::factory()->create(['curso_id' => $fisica->id, 'titulo' => 'Laboratorio uno', 'fecha_entrega' => now()->subDay()]));
        $this->entrega(Tarea::factory()->create(['curso_id' => $arte->id, 'titulo' => 'Boceto libre', 'fecha_entrega' => now()->subDay()]));

        Livewire::actingAs($profesor)->test(Panel::class)
            ->assertSee('Ensayo de arte')
            ->assertSee('La semana 2 empieza')
            ->set('tipoProximo', 'contenido')
            ->assertDontSee('Ensayo de arte')
            ->assertSee('La semana 2 empieza')
            ->set('tipoProximo', 'todo')
            ->set('cursoProximo', $arte->id)
            ->assertSee('Ensayo de arte')
            ->assertDontSee('La semana 2 empieza')
            ->set('cursoPendientes', $fisica->id)
            ->assertSee('Laboratorio uno')
            ->assertDontSee('Boceto libre')
            ->set('tipoProximo', 'cualquiera')
            ->assertSet('tipoProximo', 'todo');
    }

    public function test_un_curso_ajeno_en_el_filtro_se_ignora(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $this->cursoDe($profesor);
        $ajeno = $this->cursoDe($this->usuarioConRol('profesor'));

        Livewire::actingAs($profesor)->test(Panel::class)
            ->set('cursoPendientes', $ajeno->id)
            ->assertSet('cursoPendientes', 0);
    }

    public function test_el_panel_ya_no_muestra_mis_cursos_y_el_indicador_enlaza_a_cursos(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $this->cursoDe($profesor, ['nombre' => 'Biología Marina']);
        $this->cursoDe($profesor, ['nombre' => 'Historia del Perú']);

        Livewire::actingAs($profesor)->test(Panel::class)
            ->assertDontSee('Mis cursos')
            ->assertDontSee('titulo-mis-cursos', false)
            ->assertDontSee('Biología Marina')
            ->assertDontSee('Buscar entre')
            ->assertViewMissing('misCursos')
            ->assertSee('href="'.route('cursos.index').'"', false)
            ->assertSee('aria-label="Cursos a cargo: 2. Ver mis cursos"', false);
    }

    public function test_la_ayuda_de_por_calificar_es_un_icono_accesible(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $this->cursoDe($profesor);

        Livewire::actingAs($profesor)->test(Panel::class)
            ->assertSee('aria-label="Más información sobre Por calificar"', false)
            ->assertSee('aria-describedby="ayuda-indicador-2"', false)
            ->assertSeeInOrder(['id="ayuda-indicador-2"', 'role="tooltip"', 'Cada grupo cuenta como 1.'], false);
    }

    public function test_los_comunicados_urgentes_van_primero_dentro_del_tope(): void
    {
        $supervisor = $this->usuarioConRol('supervisor');
        $profesor = $this->usuarioConRol('profesor');
        $base = ['user_id' => $supervisor->id, 'contenido' => 'Detalle', 'destinatario' => 'todos', 'activo' => true, 'prioridad' => 'informativo'];

        $urgente = Comunicado::create([...$base, 'titulo' => 'Urgente más antiguo', 'prioridad' => 'urgente']);
        $urgente->forceFill(['created_at' => now()->subWeeks(3)])->saveQuietly();
        foreach (['Aviso de hace 3 días' => 3, 'Aviso de hace 2 días' => 2, 'Aviso de ayer' => 1, 'Aviso de hoy' => 0] as $titulo => $dias) {
            Comunicado::create([...$base, 'titulo' => $titulo])->forceFill(['created_at' => now()->subDays($dias)])->saveQuietly();
        }

        Livewire::actingAs($profesor)->test(Panel::class)
            ->assertSeeInOrder(['Urgente más antiguo', 'Aviso de hoy', 'Aviso de ayer'])
            ->assertDontSee('Aviso de hace 2 días')
            ->assertSee('Ver los 5 comunicados');
    }

    public function test_cada_comunicado_aparece_una_sola_vez_y_se_filtra(): void
    {
        $supervisor = $this->usuarioConRol('supervisor');
        $profesor = $this->usuarioConRol('profesor');
        $base = ['user_id' => $supervisor->id, 'contenido' => 'Detalle del aviso', 'destinatario' => 'profesores', 'activo' => true];
        Comunicado::create([...$base, 'titulo' => 'Aviso urgente único', 'prioridad' => 'urgente']);
        Comunicado::create([...$base, 'titulo' => 'Aviso informativo único', 'prioridad' => 'informativo']);
        Comunicado::create([...$base, 'titulo' => 'Solo para estudiantes', 'prioridad' => 'informativo', 'destinatario' => 'estudiantes']);

        $html = $this->actingAs($profesor)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'Aviso urgente único'));
        $this->assertSame(1, substr_count($html, 'Aviso informativo único'));
        $this->assertStringNotContainsString('Solo para estudiantes', $html);

        Livewire::actingAs($profesor)->test(Panel::class)
            ->assertSee('Todos 2')
            ->assertSee('Urgentes 1')
            ->assertSee('Institucionales 1')
            ->set('filtroComunicados', 'urgentes')
            ->assertSee('Aviso urgente único')
            ->assertDontSee('Aviso informativo único');
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function cantidadesDeCursos(): array
    {
        return ['1 curso' => [1], '6 cursos' => [6], '25 cursos' => [25]];
    }

    #[DataProvider('cantidadesDeCursos')]
    public function test_los_topes_se_respetan_y_los_totales_son_reales(int $cantidad): void
    {
        $profesor = $this->usuarioConRol('profesor');

        for ($i = 1; $i <= $cantidad; $i++) {
            $curso = $this->cursoDe($profesor, ['nombre' => "Materia {$i}", 'fecha_inicio' => '2026-11-02']);
            $tarea = Tarea::factory()->create(['curso_id' => $curso->id, 'fecha_entrega' => now()->addDays(3)]);
            $this->entrega($tarea);
            $this->entrega($tarea);
        }

        $componente = Livewire::actingAs($profesor)->test(Panel::class);
        $pendientes = $componente->viewData('pendientes');
        $proximas = $componente->viewData('proximas');

        $this->assertSame($cantidad * 2, $componente->viewData('indicadores')['porCalificar']);
        $this->assertSame($cantidad * 2, $pendientes['total']);
        $this->assertCount(min($cantidad, Panel::MAX_PENDIENTES), $pendientes['filas']);
        $this->assertSame(max(0, $cantidad - Panel::MAX_PENDIENTES), $pendientes['restantes']);
        $this->assertLessThanOrEqual(Panel::MAX_PROXIMAS, $proximas['filas']->count());
        // Por curso: el cierre de su tarea y las semanas 2 y 3 (empiezan en 5 y 12 días) sin contenido.
        $this->assertSame($cantidad, $proximas['conteos']['cierre']);
        $this->assertSame($cantidad * 2, $proximas['conteos']['contenido']);
        $this->assertSame($cantidad * 3, $proximas['conteos']['todo']);
        $this->assertSame(max(0, $cantidad * 3 - Panel::MAX_PROXIMAS), $proximas['restantes']);
        $this->assertSame($cantidad, $componente->viewData('indicadores')['cursos']);

        if ($cantidad > Panel::MAX_CHIPS_CURSO) {
            $componente->assertSee('panel-curso-pendientes');
        } else {
            $componente->assertDontSee('panel-curso-pendientes');
        }
    }

    public function test_el_numero_de_consultas_no_crece_con_la_cantidad_de_cursos(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $medir = function () use ($profesor): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::actingAs($profesor->fresh())->test(Panel::class);
            $consultas = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $consultas;
        };

        $crear = function (int $desde, int $hasta) use ($profesor): void {
            for ($i = $desde; $i <= $hasta; $i++) {
                $curso = $this->cursoDe($profesor, ['nombre' => "Materia {$i}", 'fecha_inicio' => '2026-11-02']);
                $this->matricular($this->usuarioConRol('estudiante'), $curso);
                $this->entrega(Tarea::factory()->create(['curso_id' => $curso->id, 'fecha_entrega' => now()->addDays(3)]));
                Quiz::factory()->create(['curso_id' => $curso->id, 'fecha_limite' => now()->addDays(4)]);
            }
        };

        $crear(1, 1);
        // La configuración de la plataforma se lee una vez por proceso; se descarta esa primera medición.
        $medir();
        $conUno = $medir();
        $crear(2, 25);
        $conVeinticinco = $medir();

        $this->assertSame($conUno, $conVeinticinco);
    }
}
