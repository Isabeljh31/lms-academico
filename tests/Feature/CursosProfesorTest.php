<?php

namespace Tests\Feature;

use App\Livewire\Cursos\Show;
use App\Livewire\Docente\Cursos\EncabezadoCurso;
use App\Livewire\Docente\Cursos\EvaluacionesCurso;
use App\Livewire\Docente\Cursos\ListaCursos;
use App\Models\Comunicado;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\EsquemaEvaluacion;
use App\Models\Modulo;
use App\Models\Quiz;
use App\Models\Tarea;
use App\Models\User;
use App\Services\Reportes\CursosProfesor;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Pantalla Cursos y detalle del curso del Profesor (layout de barra
 * inferior): filtros, búsqueda, orden, cuadrícula o lista, próximo cierre y
 * avance; el detalle conserva las acciones de Cursos\Show. Supervisor y
 * Estudiante siguen con sus pantallas de siempre.
 */
class CursosProfesorTest extends TestCase
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

    protected function entregaPendiente(Tarea $tarea): Entrega
    {
        return Entrega::factory()->create([
            'tarea_id' => $tarea->id,
            'estudiante_id' => $this->usuarioConRol('estudiante')->id,
            'estado' => 'entregado',
        ]);
    }

    /** Tarea visible para los estudiantes, en una semana publicada del curso. */
    protected function tarea(Curso $curso, string $titulo, string $cierre, array $atributos = []): Tarea
    {
        $semana = Modulo::firstOrCreate(['curso_id' => $curso->id, 'semana' => 1], ['titulo' => 'Semana 1', 'materiales' => []]);

        return Tarea::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $semana->id, 'titulo' => $titulo, 'fecha_entrega' => $cierre, ...$atributos]);
    }

    public function test_el_profesor_ve_su_pantalla_cursos_con_el_layout_de_barra_inferior(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $this->cursoDe($profesor, ['nombre' => 'Química Orgánica']);

        $this->actingAs($profesor)->get(route('cursos.index'))
            ->assertOk()
            ->assertSeeLivewire(ListaCursos::class)
            ->assertSee('<title>Cursos - LearnUp</title>', false)
            ->assertSee('Navegación principal')
            ->assertSee('Rol: Profesor')
            ->assertSee('Química Orgánica')
            ->assertDontSee('sidebarAbierto');
    }

    public function test_supervisor_y_estudiante_siguen_con_sus_pantallas_de_siempre(): void
    {
        $escenario = $this->escenario();

        foreach (['supervisor', 'estudiante'] as $rol) {
            $this->actingAs($escenario[$rol])->get(route('cursos.index'))
                ->assertOk()
                ->assertDontSeeLivewire(ListaCursos::class)
                ->assertDontSee('Navegación principal');

            $this->actingAs($escenario[$rol])->get(route('cursos.show', $escenario['curso']))
                ->assertOk()
                ->assertDontSeeLivewire(EncabezadoCurso::class)
                ->assertDontSee('Volver a Cursos');
        }
    }

    public function test_el_componente_de_la_lista_rechaza_a_estudiantes_y_supervisores(): void
    {
        Livewire::actingAs($this->usuarioConRol('estudiante'))->test(ListaCursos::class)->assertForbidden();
        Livewire::actingAs($this->usuarioConRol('supervisor'))->test(ListaCursos::class)->assertForbidden();
    }

    public function test_un_profesor_solo_ve_sus_cursos_incluida_la_codocencia(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $otro = $this->usuarioConRol('profesor');
        $this->cursoDe($profesor, ['nombre' => 'Química Orgánica']);
        $this->cursoDe($otro, ['nombre' => 'Curso de otro profesor'])->profesoresAdicionales()->attach($profesor->id);
        $this->cursoDe($otro, ['nombre' => 'Curso ajeno']);

        Livewire::actingAs($profesor)->test(ListaCursos::class)
            ->assertSee('Química Orgánica')
            ->assertSee('Curso de otro profesor')
            ->assertDontSee('Curso ajeno')
            ->assertSee('2 cursos');
    }

    public function test_los_estados_tienen_conteos_reales_y_el_filtro_y_la_busqueda_funcionan(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $this->cursoDe($profesor, ['nombre' => 'Química Orgánica', 'codigo' => 'QUI-210']);
        $this->cursoDe($profesor, ['nombre' => 'Química General', 'codigo' => 'QUI-101']);
        $this->cursoDe($profesor, ['nombre' => 'Historia del Perú', 'estado' => 'borrador']);
        $this->cursoDe($profesor, ['nombre' => 'Biología Celular', 'estado' => 'archivado']);

        $componente = Livewire::actingAs($profesor)->test(ListaCursos::class)
            ->assertViewHas('conteos', fn ($conteos) => $conteos->all() === ['todos' => 4, 'publicado' => 2, 'borrador' => 1, 'despublicado' => 0, 'archivado' => 1])
            // Sin cursos despublicados, ese chip no aparece.
            ->assertDontSee('Despublicados');

        $componente->set('estado', 'borrador')
            ->assertViewHas('cursos', fn ($cursos) => $cursos->pluck('nombre')->all() === ['Historia del Perú'])
            ->set('estado', 'archivado')
            ->assertSee('Biología Celular')
            ->assertSee('Curso cerrado')
            ->assertDontSee('Química Orgánica');

        // Búsqueda sin tildes ni mayúsculas, por nombre o por código; los conteos siguen a la búsqueda.
        $componente->set('estado', 'todos')->set('busqueda', 'quimica')
            ->assertViewHas('conteos', fn ($conteos) => $conteos['todos'] === 2 && $conteos['borrador'] === 0)
            ->assertSee('Química General')
            ->assertDontSee('Historia del Perú')
            ->set('busqueda', 'qui-101')
            ->assertViewHas('cursos', fn ($cursos) => $cursos->pluck('nombre')->all() === ['Química General']);

        // Un valor manipulado vuelve al predeterminado.
        $componente->set('estado', 'inventado')->assertSet('estado', 'todos');
    }

    public function test_sin_resultados_muestra_un_estado_vacio_con_limpiar_filtros(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        foreach (['Química Orgánica', 'Botánica', 'Cívica', 'Arte Peruano'] as $nombre) {
            $this->cursoDe($profesor, ['nombre' => $nombre]);
        }

        Livewire::actingAs($profesor)->test(ListaCursos::class)
            ->set('estado', 'borrador')
            ->set('busqueda', 'astronomía')
            ->assertSee('Ningún curso coincide con el filtro')
            ->assertSee('Limpiar filtros')
            ->call('limpiarFiltros')
            ->assertSet('busqueda', '')
            ->assertSet('estado', 'todos')
            ->assertSee('Química Orgánica');
    }

    public function test_con_tres_cursos_o_menos_no_hay_controles_y_con_cuatro_si(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        foreach (['Química Orgánica', 'Botánica', 'Cívica'] as $nombre) {
            $this->cursoDe($profesor, ['nombre' => $nombre]);
        }

        Livewire::actingAs($profesor)->test(ListaCursos::class)
            ->assertViewHas('conControles', false)
            ->assertDontSee('Filtrar por estado')
            ->assertDontSee('Buscar curso por nombre o código')
            ->assertDontSee('Ordenar:')
            ->assertDontSee('aria-label="Vista"', false)
            ->assertDontSee('Mostrando')
            // Un filtro que no se puede quitar desde la pantalla no se aplica.
            ->set('busqueda', 'astronomía')
            ->assertSee('Química Orgánica');

        $this->cursoDe($profesor, ['nombre' => 'Arte Peruano']);

        Livewire::actingAs($profesor)->test(ListaCursos::class)
            ->assertViewHas('conControles', true)
            ->assertSee('Filtrar por estado')
            ->assertSee('Buscar curso por nombre o código')
            ->assertSee('Ordenar:')
            ->assertSee('aria-label="Vista"', false)
            ->assertSee('Mostrando 4 de 4 cursos');
    }

    /** @return list<string> tipos de "Para hacer" del curso, en orden */
    protected function tiposParaHacer(User $profesor, Curso $curso): array
    {
        $resumen = Livewire::actingAs($profesor)->test(ListaCursos::class)->viewData('resumen');

        return array_column($resumen[$curso->id]['para_hacer'], 'tipo');
    }

    public function test_con_uno_o_dos_cursos_la_tarjeta_es_ancha_con_para_hacer(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        // Empieza el lunes 19/10: hoy (4/11) es la semana 3 de 5, que no tiene contenido.
        $curso = $this->cursoDe($profesor, ['nombre' => 'Química Orgánica', 'fecha_inicio' => '2026-10-19', 'semanas_duracion' => 5]);
        $this->entregaPendiente($this->tarea($curso, 'Informe de laboratorio', '2026-10-23 23:59'));
        $esquema = EsquemaEvaluacion::create(['curso_id' => $curso->id]);
        $esquema->rubros()->create(['nombre' => 'Primer parcial', 'semana' => 2, 'peso' => 40, 'orden' => 1]);
        $esquema->rubros()->create(['nombre' => 'Examen final', 'semana' => 5, 'peso' => 60, 'orden' => 2]);

        Livewire::actingAs($profesor)->test(ListaCursos::class)
            ->assertViewHas('tarjetaAncha', true)
            ->assertSeeInOrder(['Semana 3 de 5 · 60 %', 'Semana 3 · 02/11 – 08/11'])
            ->assertSeeInOrder([
                'Para hacer',
                'La Semana 3 (actual) no tiene contenido', 'Agregar contenido',
                // La semana 2 (Primer parcial) ya terminó: solo la evaluación pendiente.
                'Examen final · 16/11 – 22/11 · sin actividades', 'Crear actividad',
                '1 entrega por calificar', 'Calificar',
            ])
            ->assertSee(route('cursos.show', ['curso' => $curso, 'semana' => 3]), false)
            ->assertDontSee('Primer parcial · ')
            ->assertDontSee('Semanas del curso');

        $this->cursoDe($profesor, ['nombre' => 'Botánica']);
        Livewire::actingAs($profesor)->test(ListaCursos::class)->assertViewHas('tarjetaAncha', true);

        $this->cursoDe($profesor, ['nombre' => 'Cívica']);
        Livewire::actingAs($profesor)->test(ListaCursos::class)
            ->assertViewHas('tarjetaAncha', false)
            ->assertDontSee('Para hacer');
    }

    public function test_para_hacer_prioriza_la_semana_actual_vacia_sobre_la_siguiente(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $curso = $this->cursoDe($profesor, ['nombre' => 'Química Orgánica', 'fecha_inicio' => '2026-10-19', 'semanas_duracion' => 5]);

        // Semana actual (3) y siguiente (4) vacías: solo se avisa de la actual.
        $this->assertSame(['semana_actual'], $this->tiposParaHacer($profesor, $curso));

        // Con contenido en la actual, se avisa de la siguiente.
        $semana3 = Modulo::create(['curso_id' => $curso->id, 'semana' => 3, 'titulo' => 'Semana 3', 'materiales' => []]);
        $semana3->lecciones()->create(['titulo' => 'Lectura', 'estado' => 'publicado', 'orden' => 1]);
        Livewire::actingAs($profesor)->test(ListaCursos::class)
            ->assertSee('La Semana 4 empieza el lunes 09/11 y aún no tiene contenido')
            ->assertSee(route('cursos.show', ['curso' => $curso, 'semana' => 4]), false);

        // Con contenido también en la siguiente, y sin nada más: todo al día.
        $semana4 = Modulo::create(['curso_id' => $curso->id, 'semana' => 4, 'titulo' => 'Semana 4', 'materiales' => []]);
        $semana4->lecciones()->create(['titulo' => 'Guía', 'estado' => 'borrador', 'orden' => 1]);
        Livewire::actingAs($profesor)->test(ListaCursos::class)
            ->assertViewHas('resumen', fn (array $resumen) => $resumen[$curso->id]['para_hacer'] === [])
            ->assertSee('Todo al día');
    }

    public function test_para_hacer_ordena_por_urgencia_y_muestra_como_maximo_tres(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $curso = $this->cursoDe($profesor, ['nombre' => 'Química Orgánica', 'fecha_inicio' => '2026-10-19', 'semanas_duracion' => 5]);
        $this->entregaPendiente($this->tarea($curso, 'Informe', '2026-10-23 23:59'));
        $esquema = EsquemaEvaluacion::create(['curso_id' => $curso->id]);
        $esquema->rubros()->create(['nombre' => 'Segundo parcial', 'semana' => 4, 'peso' => 40, 'orden' => 1]);
        $esquema->rubros()->create(['nombre' => 'Examen final', 'semana' => 5, 'peso' => 60, 'orden' => 2]);

        // Semana vacía, dos evaluaciones sin actividades y entregas: se cortan en 3 (calificar queda fuera).
        $this->assertSame(['semana_actual', 'evaluacion', 'evaluacion'], $this->tiposParaHacer($profesor, $curso));

        // Solo entregas por calificar.
        $semana = Modulo::create(['curso_id' => $curso->id, 'semana' => 3, 'titulo' => 'Semana 3', 'materiales' => []]);
        $semana->lecciones()->create(['titulo' => 'Lectura', 'estado' => 'publicado', 'orden' => 1]);
        Modulo::create(['curso_id' => $curso->id, 'semana' => 4, 'titulo' => 'Semana 4', 'materiales' => []])
            ->lecciones()->create(['titulo' => 'Guía', 'estado' => 'publicado', 'orden' => 1]);
        $esquema->rubros()->delete();
        $this->assertSame(['calificar'], $this->tiposParaHacer($profesor, $curso));
    }

    public function test_la_siguiente_fecha_limite_se_entiende_sola_con_fecha_relativa(): void
    {
        $servicio = app(CursosProfesor::class);
        $ahora = Carbon::parse('2026-11-04 10:00:00');

        $this->assertSame('Práctica 1 · vence hoy', $servicio->textoCierre(['titulo' => 'Práctica 1', 'fecha' => Carbon::parse('2026-11-04 23:59'), 'tipo' => 'actividad'], $ahora));
        $this->assertSame('Práctica 1 · vence mañana', $servicio->textoCierre(['titulo' => 'Práctica 1', 'fecha' => Carbon::parse('2026-11-05 08:00'), 'tipo' => 'actividad'], $ahora));
        $this->assertSame('Práctica 1 · vence el 07/11 (en 3 días)', $servicio->textoCierre(['titulo' => 'Práctica 1', 'fecha' => Carbon::parse('2026-11-07 23:59'), 'tipo' => 'actividad'], $ahora));
        $this->assertSame('Evaluación 2 · termina el 22/11 (en 18 días)', $servicio->textoCierre(['titulo' => 'Evaluación 2', 'fecha' => Carbon::parse('2026-11-22 23:59'), 'tipo' => 'evaluacion'], $ahora));
    }

    public function test_el_proximo_cierre_usa_la_evaluacion_del_supervisor_si_no_hay_actividades(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $curso = $this->cursoDe($profesor, ['nombre' => 'Química Orgánica', 'fecha_inicio' => '2026-10-19', 'semanas_duracion' => 5]);
        $esquema = EsquemaEvaluacion::create(['curso_id' => $curso->id]);
        $esquema->rubros()->create(['nombre' => 'Primer parcial', 'semana' => 1, 'peso' => 40, 'orden' => 1]);
        $esquema->rubros()->create(['nombre' => 'Examen final', 'semana' => 5, 'peso' => 60, 'orden' => 2]);
        $this->cursoDe($profesor, ['nombre' => 'Botánica']);

        // b) Sin tareas ni quizzes por cerrar: el fin de la semana 5 (domingo 22/11); la semana 1 ya pasó.
        Livewire::actingAs($profesor)->test(ListaCursos::class)
            ->assertSee('Examen final · termina el 22/11 (en 18 días)')
            ->assertDontSee('· Primer parcial')
            // c) Sin actividades ni evaluaciones: "Sin fechas".
            ->assertSee('Sin fechas');

        Livewire::actingAs($profesor)->test(EncabezadoCurso::class, ['curso' => $curso])
            ->assertSeeInOrder(['Próximo cierre', '22/11', 'Examen final', '· Semana 5']);

        // a) Una tarea futura gana a la evaluación.
        $this->tarea($curso, 'Informe de laboratorio', '2026-11-12 23:59');
        Livewire::actingAs($profesor)->test(ListaCursos::class)
            ->assertSee('Informe de laboratorio · vence el 12/11 (en 8 días)')
            ->assertDontSee('Examen final · termina');
    }

    /** Semana 1 con dos recursos y una actividad complementaria, en ese orden. */
    protected function semanaConElementos(Curso $curso): array
    {
        $semana = Modulo::firstOrCreate(['curso_id' => $curso->id, 'semana' => 1], ['titulo' => 'Semana 1', 'materiales' => []]);
        $guia = $semana->lecciones()->create(['titulo' => 'Guía de práctica', 'estado' => 'publicado', 'orden' => 1, 'tipo_recurso' => 'recursos']);
        $ejercicio = $semana->lecciones()->create(['titulo' => 'Ejercicios resueltos', 'estado' => 'programado', 'fecha_publicacion' => '2026-11-20 08:00', 'orden' => 2, 'tipo_recurso' => 'actividades']);
        $lectura = $semana->lecciones()->create(['titulo' => 'Lectura obligatoria', 'estado' => 'publicado', 'orden' => 3, 'tipo_recurso' => 'recursos']);

        return [$guia, $ejercicio, $lectura];
    }

    public function test_reordenar_elementos_dentro_de_una_seccion_guarda_y_se_respeta_al_recargar(): void
    {
        $escenario = $this->escenario();
        [$guia, $ejercicio, $lectura] = $this->semanaConElementos($escenario['curso']);

        // Sin reordenar: el orden guardado de siempre.
        Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']])
            ->assertSeeInOrder(['Guía de práctica', 'Lectura obligatoria'])
            ->assertSee('Arrastra para cambiar el orden')
            ->call('ordenarSeccion', [$lectura->id, $guia->id])
            ->assertHasNoErrors();

        // La otra sección conserva su elemento, su estado y su fecha de publicación.
        $this->assertSame([$lectura->id, $guia->id], $guia->modulo->lecciones()->where('tipo_recurso', 'recursos')->pluck('id')->all());
        $this->assertSame('programado', $ejercicio->fresh()->estado);
        $this->assertSame('2026-11-20 08:00', $ejercicio->fresh()->fecha_publicacion->format('Y-m-d H:i'));

        Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']])
            ->assertSeeInOrder(['Lectura obligatoria', 'Guía de práctica']);
        // El estudiante ve el mismo orden (su vista ordena por la misma columna).
        Livewire::actingAs($escenario['estudiante'])->test(Show::class, ['curso' => $escenario['curso']])
            ->assertSeeInOrder(['Lectura obligatoria', 'Guía de práctica']);
    }

    public function test_no_se_pueden_mover_elementos_a_otra_seccion_ni_reordenar_un_curso_ajeno(): void
    {
        $escenario = $this->escenario();
        [$guia, $ejercicio, $lectura] = $this->semanaConElementos($escenario['curso']);
        $ordenOriginal = $guia->modulo->lecciones()->pluck('id')->all();

        // Mezclar secciones o dejar fuera un elemento de la sección se rechaza.
        Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']])
            ->call('ordenarSeccion', [$ejercicio->id, $guia->id, $lectura->id])
            ->assertStatus(422);
        Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']])
            ->call('ordenarSeccion', [$lectura->id])
            ->assertStatus(422);

        // Otro profesor, desde su propio curso, no encuentra los elementos ajenos.
        $otro = $this->usuarioConRol('profesor');
        $suyo = $this->cursoDe($otro);
        try {
            Livewire::actingAs($otro)->test(Show::class, ['curso' => $suyo])->call('ordenarSeccion', [$lectura->id, $guia->id]);
            $this->fail('Se esperaba que no encontrara los elementos de otro curso.');
        } catch (ModelNotFoundException) {
            // Correcto: solo se buscan elementos del curso abierto.
        }

        $this->assertSame($ordenOriginal, $guia->modulo->lecciones()->pluck('id')->all());
    }

    public function test_un_profesor_sin_cursos_ve_un_estado_vacio(): void
    {
        Livewire::actingAs($this->usuarioConRol('profesor'))->test(ListaCursos::class)
            ->assertSee('Aún no tienes cursos a cargo')
            ->assertSee('0 cursos')
            ->assertDontSee('Filtrar por estado');
    }

    public function test_el_orden_por_defecto_es_mas_por_calificar_y_se_puede_cambiar(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $arte = $this->cursoDe($profesor, ['nombre' => 'Arte Peruano']);
        $botanica = $this->cursoDe($profesor, ['nombre' => 'Botánica']);
        $civica = $this->cursoDe($profesor, ['nombre' => 'Cívica']);

        $this->entregaPendiente($this->tarea($botanica, 'Herbario', '2026-10-30 23:59'));
        $tarea = $this->tarea($civica, 'Ensayo', '2026-10-30 23:59');
        $this->entregaPendiente($tarea);
        $this->entregaPendiente($tarea);
        $this->tarea($arte, 'Mural', '2026-11-06 18:00');
        $this->tarea($botanica, 'Vivero', '2026-11-20 18:00');

        $nombres = fn (string $orden) => fn ($cursos) => $cursos->pluck('nombre')->all() === match ($orden) {
            'pendientes' => ['Cívica', 'Botánica', 'Arte Peruano'],
            'nombre' => ['Arte Peruano', 'Botánica', 'Cívica'],
            'cierre' => ['Arte Peruano', 'Botánica', 'Cívica'],
        };

        Livewire::actingAs($profesor)->test(ListaCursos::class)
            ->assertSet('orden', 'pendientes')
            ->assertViewHas('cursos', $nombres('pendientes'))
            ->assertSee('Calificar 2')
            ->set('orden', 'nombre')
            ->assertViewHas('cursos', $nombres('nombre'))
            // Próximo cierre: el más cercano primero; sin fechas al final.
            ->set('orden', 'cierre')
            ->assertViewHas('cursos', $nombres('cierre'));
    }

    public function test_hasta_ocho_cursos_se_ven_en_tarjetas_y_con_mas_en_lista_con_tandas_de_doce(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        foreach (range(1, 8) as $i) {
            $this->cursoDe($profesor, ['nombre' => "Materia {$i}"]);
        }

        Livewire::actingAs($profesor)->test(ListaCursos::class)
            ->assertViewHas('vistaActual', 'cuadricula')
            ->assertSee('Cursos en cuadrícula')
            ->set('vista', 'lista')
            ->assertSee('Cursos en lista');

        foreach (range(9, 13) as $i) {
            $this->cursoDe($profesor, ['nombre' => "Materia {$i}"]);
        }

        Livewire::actingAs($profesor)->test(ListaCursos::class)
            ->assertViewHas('vistaActual', 'lista')
            ->assertViewHas('cursos', fn ($cursos) => $cursos->count() === 12)
            ->assertSee('Mostrando 12 de 13 cursos')
            ->assertSee('Ver el curso restante')
            ->call('verMas')
            ->assertViewHas('cursos', fn ($cursos) => $cursos->count() === 13)
            ->assertDontSee('Ver el curso restante')
            // El interruptor manda sobre el umbral.
            ->set('vista', 'cuadricula')
            ->assertViewHas('vistaActual', 'cuadricula');
    }

    public function test_el_proximo_cierre_es_la_siguiente_fecha_futura_visible(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $curso = $this->cursoDe($profesor, ['nombre' => 'Química Orgánica']);
        $this->tarea($curso, 'Informe vencido', '2026-11-03 23:59');
        $this->tarea($curso, 'Informe oculto', '2026-11-05 23:59', ['visible' => false]);
        $this->tarea($curso, 'Informe de laboratorio', '2026-11-12 23:59');
        $semana = Modulo::where('curso_id', $curso->id)->where('semana', 1)->first();
        Quiz::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $semana->id, 'tema' => 'Quiz de nomenclatura', 'fecha_limite' => '2026-11-08 20:00']);
        $this->cursoDe($profesor, ['nombre' => 'Botánica']);

        Livewire::actingAs($profesor)->test(ListaCursos::class)
            ->assertSee('Quiz de nomenclatura · vence el 08/11 (en 4 días)')
            ->assertDontSee('Informe vencido')
            ->assertDontSee('Informe oculto')
            ->assertSee('Sin fechas');

        Livewire::actingAs($profesor)->test(EncabezadoCurso::class, ['curso' => $curso])
            ->assertSeeInOrder(['Próximo cierre', '08/11', 'Quiz de nomenclatura', '· Semana 1']);
    }

    public function test_el_avance_usa_la_misma_semana_actual_que_el_detalle(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        // Empieza el lunes 19/10: hoy (miércoles 4/11) es la semana 3 de 5.
        $curso = $this->cursoDe($profesor, ['nombre' => 'Química Orgánica', 'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-11-22', 'semanas_duracion' => 5]);
        $this->cursoDe($profesor, ['nombre' => 'Botánica', 'fecha_inicio' => '2027-01-18', 'fecha_fin' => '2027-02-21', 'semanas_duracion' => 5]);

        Livewire::actingAs($profesor)->test(ListaCursos::class)
            ->assertSeeInOrder(['Química Orgánica', 'Semana 3 de 5', '60 %'])
            ->assertSee('Empieza el 18/01/2027')
            ->assertSee('en 75 días');

        $this->actingAs($profesor)->get(route('cursos.show', $curso))
            ->assertOk()
            ->assertSeeInOrder(['Semana 3 de 5', 'Contenido por semanas', 'Semana 2', 'Semana 3', 'Semana actual', 'Semana 4', 'Programada']);

        Livewire::actingAs($profesor)->test(Show::class, ['curso' => $curso])
            ->assertSet('semanasAbiertas', [3]);
    }

    public function test_la_tarjeta_normal_muestra_un_solo_aviso_con_la_misma_prioridad(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        // La semana actual (1, del 2 al 8/11) está vacía y además es borrador: gana la semana.
        $quimica = $this->cursoDe($profesor, ['nombre' => 'Química Orgánica', 'estado' => 'borrador', 'fecha_inicio' => '2026-11-02', 'semanas_duracion' => 16]);
        $historia = $this->cursoDe($profesor, ['nombre' => 'Historia del Perú', 'estado' => 'borrador']);
        // Solo entregas por calificar: no se repite como aviso (ya está la cifra y el botón "Calificar").
        $civica = $this->cursoDe($profesor, ['nombre' => 'Cívica']);
        $this->entregaPendiente($this->tarea($civica, 'Ensayo', '2026-10-30 23:59'));

        Livewire::actingAs($profesor)->test(ListaCursos::class)
            ->assertViewHas('tarjetaAncha', false)
            ->assertViewHas('resumen', fn (array $resumen) => $resumen[$quimica->id]['aviso'] === 'La Semana 1 (actual) no tiene contenido'
                && $resumen[$historia->id]['aviso'] === 'Aún es un borrador: los estudiantes no lo ven'
                && $resumen[$civica->id]['aviso'] === null)
            ->assertSee('Calificar 1')
            ->assertDontSee('Para hacer');
    }

    public function test_el_detalle_muestra_encabezado_semanas_evaluaciones_y_anuncios(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $curso = $this->cursoDe($profesor, ['nombre' => 'Química Orgánica', 'fecha_inicio' => '2026-10-19', 'semanas_duracion' => 5, 'cupo_maximo' => 30]);
        $this->matricular($this->usuarioConRol('estudiante'), $curso);
        $this->entregaPendiente($this->tarea($curso, 'Informe de laboratorio', '2026-11-02 23:59'));
        $esquema = EsquemaEvaluacion::create(['curso_id' => $curso->id]);
        $esquema->rubros()->create(['nombre' => 'Primer parcial', 'semana' => 4, 'peso' => 40, 'orden' => 1]);
        $curso->comunicados()->create(['user_id' => $profesor->id, 'titulo' => 'Cambio de aula', 'contenido' => 'Nos vemos en el laboratorio 2.', 'prioridad' => 'normal', 'destinatario' => 'estudiantes', 'activo' => true]);

        $this->actingAs($profesor)->get(route('cursos.show', $curso))
            ->assertOk()
            ->assertSeeLivewire(EncabezadoCurso::class)
            ->assertSeeLivewire(EvaluacionesCurso::class)
            ->assertSee('Navegación principal')
            ->assertSee('Volver a Cursos')
            ->assertSee('Calificar 1 entrega')
            ->assertSeeInOrder(['Matriculados', '1', 'de 30', 'cupos ocupados', 'Por calificar', '1', 'la más antigua'])
            ->assertSee(route('cursos.actividades', $curso), false)
            ->assertSee(route('cursos.matriculas', $curso), false)
            ->assertSee(route('cursos.grupos.index', $curso), false)
            ->assertSee(route('cursos.debates.index', $curso), false)
            ->assertSee(route('asistencia.index', $curso), false)
            ->assertSee(route('cursos.libro-calificaciones', $curso), false)
            ->assertSee(route('cursos.mensajeria', $curso), false)
            ->assertSeeInOrder(['Evaluaciones', 'Primer parcial', 'Semana 4 · 40 % de la nota', 'Aún sin actividades asignadas'])
            ->assertSeeInOrder(['Anuncios del curso', 'Cambio de aula', 'Nuevo anuncio'])
            ->assertSee('Información del curso')
            ->assertDontSee('Promedio');
    }

    public function test_el_detalle_conserva_las_acciones_del_contenido_y_los_anuncios(): void
    {
        $escenario = $this->escenario();
        $curso = $escenario['curso'];

        Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $curso])
            ->assertViewIs('livewire.docente.cursos.detalle')
            ->assertSee('aria-label="Agregar prácticas de campo"', false)
            ->assertSee('Mensaje de inicio')
            ->call('agregarLeccion', 'recursos', 1)
            ->assertSet('mostrarFormularioLeccion', true)
            ->call('cerrarFormularioLeccion')
            ->call('prepararSeccion', 2)
            ->assertSet('mostrarModalModulo', true)
            ->set('nuevoAnuncioTitulo', 'Recordatorio de práctica')
            ->set('nuevoAnuncioTexto', 'Traigan su bata.')
            ->call('publicarAnuncio')
            ->assertHasNoErrors()
            ->assertSee('Recordatorio de práctica');

        $this->assertTrue(Comunicado::where('curso_id', $curso->id)->where('titulo', 'Recordatorio de práctica')->exists());
        $this->assertTrue(Modulo::where('curso_id', $curso->id)->where('semana', 2)->exists());

        // El supervisor sigue viendo la vista compartida.
        Livewire::actingAs($escenario['supervisor'])->test(Show::class, ['curso' => $curso])
            ->assertViewIs('livewire.cursos.show');
    }

    public function test_el_numero_de_consultas_de_la_lista_no_crece_con_la_cantidad_de_cursos(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $medir = function () use ($profesor): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::actingAs($profesor->fresh())->test(ListaCursos::class);
            $consultas = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $consultas;
        };

        $crear = function (int $desde, int $hasta) use ($profesor): void {
            for ($i = $desde; $i <= $hasta; $i++) {
                $curso = $this->cursoDe($profesor, ['nombre' => "Materia {$i}", 'fecha_inicio' => '2026-11-02']);
                $this->matricular($this->usuarioConRol('estudiante'), $curso);
                $this->entregaPendiente($this->tarea($curso, "Tarea {$i}", '2026-11-10 23:59'));
                $this->esquemaValido($curso);
            }
        };

        $crear(1, 1);
        // La configuración de la plataforma se lee una vez por proceso; se descarta esa primera medición.
        $medir();
        $conUno = $medir();
        $crear(2, 15);

        $this->assertSame($conUno, $medir());
    }
}
