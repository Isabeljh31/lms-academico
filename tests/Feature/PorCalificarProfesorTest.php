<?php

namespace Tests\Feature;

use App\Livewire\Docente\Cursos\EncabezadoCurso;
use App\Livewire\Docente\PorCalificar\Index as PorCalificar;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\Grupo;
use App\Models\Modulo;
use App\Models\Tarea;
use App\Models\User;
use App\Services\Reportes\PanelProfesor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Por calificar del Profesor: lista de tareas con pendientes a la izquierda y
 * entregas de la tarea elegida a la derecha. Días de espera en enteros,
 * urgencia por antigüedad, enlaces profundos y entregas grupales contadas una
 * vez por grupo, igual que la insignia de la barra inferior.
 */
class PorCalificarProfesorTest extends TestCase
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
        return Curso::factory()->create(['profesor_id' => $profesor->id, ...$atributos]);
    }

    protected function entrega(Tarea $tarea, Carbon $fecha, ?Grupo $grupo = null, ?string $nombre = null): Entrega
    {
        $estudiante = $this->usuarioConRol('estudiante');
        if ($nombre) {
            $estudiante->update(['name' => $nombre]);
        }

        $entrega = Entrega::factory()->create([
            'tarea_id' => $tarea->id,
            'estudiante_id' => $estudiante->id,
            'grupo_id' => $grupo?->id,
            'estado' => 'entregado',
            'nota' => null,
        ]);
        $entrega->forceFill(['updated_at' => $fecha])->saveQuietly();

        return $entrega;
    }

    protected function calificada(Tarea $tarea, Carbon $fecha, ?Grupo $grupo = null, float $nota = 15, ?string $nombre = null): Entrega
    {
        $entrega = $this->entrega($tarea, $fecha, $grupo, $nombre);
        $entrega->forceFill(['estado' => 'calificado', 'nota' => $nota, 'updated_at' => $fecha])->saveQuietly();

        return $entrega;
    }

    /** Tarea en la semana N del curso (el módulo de esa semana se crea si no existe). */
    protected function tareaEnSemana(Curso $curso, int $semana, array $atributos = []): Tarea
    {
        $modulo = Modulo::firstOrCreate(['curso_id' => $curso->id, 'semana' => $semana], ['titulo' => "Semana {$semana}"]);

        return Tarea::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id, ...$atributos]);
    }

    /** Práctica de campo N: vive en la sección "Prácticas de campo", sin semana. */
    protected function practicaDeCampo(Curso $curso, int $numero, array $atributos = []): Tarea
    {
        $curso->update(['tiene_practicas_campo' => true]);
        $modulo = Modulo::firstOrCreate(['curso_id' => $curso->id, 'tipo_seccion' => Modulo::TIPO_PRACTICAS], ['titulo' => 'Prácticas de campo']);

        return Tarea::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id, 'practica' => $numero, ...$atributos]);
    }

    public function test_los_dias_de_espera_son_enteros(): void
    {
        $ahora = now();

        $this->assertSame('hace 37 días', PorCalificar::textoEspera(PorCalificar::diasEsperando($ahora->copy()->subHours(37 * 24 + 13), $ahora)));
        $this->assertSame('hace 1 día', PorCalificar::textoEspera(PorCalificar::diasEsperando($ahora->copy()->subHours(30), $ahora)));
        $this->assertSame('hoy', PorCalificar::textoEspera(PorCalificar::diasEsperando($ahora->copy()->subHours(20), $ahora)));

        // En pantalla tampoco aparecen decimales.
        $profesor = $this->usuarioConRol('profesor');
        $tarea = Tarea::factory()->create(['curso_id' => $this->cursoDe($profesor)->id, 'titulo' => 'Informe final']);
        $this->entrega($tarea, now()->subHours(37 * 24 + 13));

        $html = Livewire::actingAs($profesor)->test(PorCalificar::class)
            ->assertSee('la más antigua espera hace 37 días')
            ->assertSee('37 días')
            ->html();
        $this->assertDoesNotMatchRegularExpression('/\d+\.\d+ días/', $html);
    }

    public function test_la_urgencia_cambia_en_los_3_y_en_los_7_dias_y_se_muestra_como_punto(): void
    {
        $this->assertSame('baja', PorCalificar::urgencia(2));
        $this->assertSame('media', PorCalificar::urgencia(3));
        $this->assertSame('media', PorCalificar::urgencia(7));
        $this->assertSame('alta', PorCalificar::urgencia(8));

        $profesor = $this->usuarioConRol('profesor');
        $tarea = Tarea::factory()->create(['curso_id' => $this->cursoDe($profesor)->id]);
        foreach ([1, 3, 7, 8, 30] as $dias) {
            $this->entrega($tarea, now()->subDays($dias));
        }

        Livewire::actingAs($profesor)->test(PorCalificar::class)
            ->assertViewHas('urgentes', 2)
            ->assertViewHas('filasPagina', fn ($filas) => $filas->pluck('urgencia')->all() === ['alta', 'alta', 'media', 'media', 'baja'])
            // Ya no hay chips de antigüedad.
            ->assertDontSee('Mostrar entregas que esperan')
            ->assertDontSee('Más de 7 días');
    }

    public function test_elige_por_defecto_la_tarea_con_la_entrega_mas_antigua(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $curso = $this->cursoDe($profesor);
        $reciente = Tarea::factory()->create(['curso_id' => $curso->id, 'titulo' => 'Práctica reciente']);
        $antigua = Tarea::factory()->create(['curso_id' => $curso->id, 'titulo' => 'Práctica antigua']);
        $this->entrega($reciente, now()->subDays(2));
        $this->entrega($antigua, now()->subDays(9));
        $this->entrega($antigua, now()->subDays(5));

        Livewire::actingAs($profesor)->test(PorCalificar::class)
            ->assertSet('tarea', $antigua->id)
            ->assertViewHas('filasPagina', fn ($filas) => $filas->pluck('dias')->all() === [9, 5])
            // Si la tarea elegida deja de verse con los filtros, se elige otra vez la más antigua visible.
            ->set('semana', (string) PorCalificar::semanaDe($reciente))
            ->assertSet('tarea', $reciente->id);
    }

    public function test_los_enlaces_profundos_eligen_curso_y_tarea(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $quimica = $this->cursoDe($profesor, ['nombre' => 'Química General']);
        $fisica = $this->cursoDe($profesor, ['nombre' => 'Física General']);
        $tareaQuimica = Tarea::factory()->create(['curso_id' => $quimica->id, 'titulo' => 'Enlace químico']);
        $otraQuimica = Tarea::factory()->create(['curso_id' => $quimica->id, 'titulo' => 'Estequiometría']);
        $tareaFisica = Tarea::factory()->create(['curso_id' => $fisica->id, 'titulo' => 'Rotación']);
        $this->entrega($tareaFisica, now()->subDays(20));
        $this->entrega($tareaQuimica, now()->subDays(5));
        $this->entrega($otraQuimica, now()->subDays(2));

        Livewire::withQueryParams(['curso' => $quimica->id])->actingAs($profesor)->test(PorCalificar::class)
            ->assertSet('curso', $quimica->id)
            ->assertSet('tarea', $tareaQuimica->id)
            ->assertViewHas('tareas', fn ($tareas) => $tareas->pluck('tarea.id')->all() === [$tareaQuimica->id, $otraQuimica->id]);

        Livewire::withQueryParams(['tarea' => $otraQuimica->id])->actingAs($profesor)->test(PorCalificar::class)
            ->assertSet('tarea', $otraQuimica->id)
            ->assertSee('Estequiometría');

        // Una tarea ajena o sin pendientes no se abre: vuelve a la más antigua.
        Livewire::withQueryParams(['tarea' => 999999])->actingAs($profesor)->test(PorCalificar::class)
            ->assertSet('tarea', $tareaFisica->id);

        // Los atajos del curso llevan a la bandeja filtrada.
        Livewire::actingAs($profesor)->test(EncabezadoCurso::class, ['curso' => $quimica])
            ->assertSee(route('docente.por-calificar', ['curso' => $quimica->id]), false);
    }

    public function test_la_busqueda_encuentra_estudiantes_y_tareas(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $curso = $this->cursoDe($profesor);
        $informe = Tarea::factory()->create(['curso_id' => $curso->id, 'titulo' => 'Informe de laboratorio']);
        $ensayo = Tarea::factory()->create(['curso_id' => $curso->id, 'titulo' => 'Ensayo final']);
        $this->entrega($informe, now()->subDays(4), nombre: 'Lucía Salazar');
        $this->entrega($ensayo, now()->subDays(3), nombre: 'Diego Mendoza');

        Livewire::actingAs($profesor)->test(PorCalificar::class)
            ->set('busqueda', 'lucía')
            ->assertViewHas('tareas', fn ($tareas) => $tareas->pluck('tarea.titulo')->all() === ['Informe de laboratorio'])
            ->set('busqueda', 'ENSAYO')
            ->assertSet('tarea', $ensayo->id)
            ->assertSee('Diego Mendoza')
            ->set('busqueda', 'nadie')
            ->assertSee('No hay entregas con esos filtros')
            ->call('limpiarFiltros')
            ->assertSet('busqueda', '')
            ->assertViewHas('tareas', fn ($tareas) => $tareas->count() === 2);
    }

    public function test_al_guardar_abre_la_siguiente_de_la_misma_tarea(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $curso = $this->cursoDe($profesor);
        $tarea = Tarea::factory()->create(['curso_id' => $curso->id]);
        $otra = Tarea::factory()->create(['curso_id' => $curso->id]);
        $primera = $this->entrega($tarea, now()->subDays(6));
        $segunda = $this->entrega($tarea, now()->subDays(4));
        $this->entrega($otra, now()->subDays(5));

        $componente = Livewire::actingAs($profesor)->test(PorCalificar::class)
            ->call('abrirCalificar', $primera->id)
            ->set('nota', '17')
            ->call('guardarCalificacion')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalCalificar', true)
            ->assertSet('entregaSeleccionadaId', $segunda->id);

        // Era la última de la tarea: el modal se cierra (no salta a otra tarea).
        $componente->set('nota', '15')->call('guardarCalificacion')
            ->assertSet('mostrarModalCalificar', false)
            ->assertSet('entregaSeleccionadaId', null);

        $this->assertSame('calificado', $primera->fresh()->estado);
        $this->assertSame('calificado', $segunda->fresh()->estado);

        // Con el interruptor apagado se cierra al guardar.
        $tercera = $this->entrega($tarea, now()->subDays(2));
        $this->entrega($tarea, now()->subDay());
        Livewire::actingAs($profesor)->test(PorCalificar::class)
            ->set('siguienteAlGuardar', false)
            ->call('abrirCalificar', $tercera->id)
            ->set('nota', '12')
            ->call('guardarCalificacion')
            ->assertSet('mostrarModalCalificar', false);
    }

    public function test_la_tarea_grupal_muestra_una_fila_por_grupo_y_la_nota_se_aplica_a_todos(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $curso = $this->cursoDe($profesor);
        $tarea = Tarea::factory()->create(['curso_id' => $curso->id, 'titulo' => 'Trabajo grupal', 'tipo_entrega' => 'grupal']);
        $grupo = Grupo::create(['curso_id' => $curso->id, 'nombre' => 'Grupo 3']);
        $entregas = collect(['Carolina Vargas Vargas', 'Gonzalo Paredes Ríos', 'Milagros Vega Lozano', 'Fabián Zapata Ramírez'])
            ->map(fn (string $nombre) => $this->entrega($tarea, now()->subDays(9), $grupo, $nombre));

        Livewire::actingAs($profesor)->test(PorCalificar::class)
            ->assertViewHas('filasPagina', fn ($filas) => $filas->count() === 1)
            ->assertSee('Grupo 3')
            ->assertSee('4 integrantes')
            ->assertSee('Carolina Vargas, Gonzalo Paredes +2')
            ->assertSee('title="Carolina Vargas Vargas, Gonzalo Paredes Ríos, Milagros Vega Lozano, Fabián Zapata Ramírez"', false)
            ->assertSee('La nota de un grupo se aplica a todos sus integrantes.')
            ->call('abrirCalificar', $entregas->first()->id)
            ->set('nota', '18')
            ->call('guardarCalificacion')
            ->assertSet('mostrarModalCalificar', false);

        foreach ($entregas as $entrega) {
            $this->assertSame('calificado', $entrega->fresh()->estado);
            $this->assertEquals(18, (float) $entrega->fresh()->nota);
        }
    }

    public function test_sin_pendientes_muestra_que_esta_al_dia(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $this->cursoDe($profesor);

        $this->actingAs($profesor)->get(route('docente.por-calificar'))
            ->assertOk()
            ->assertSee('Navegación principal')
            ->assertSee('Estás al día, no tienes entregas por calificar')
            ->assertDontSee('Entregó el')
            ->assertDontSee('Calificar la más antigua')
            ->assertDontSee('Ver notas puestas');

        // Al día pero con notas puestas: se pueden revisar con el mismo modal.
        $tarea = $this->tareaEnSemana(Curso::where('profesor_id', $profesor->id)->first(), 1);
        $calificada = $this->calificada($tarea, now()->subDays(3), nota: 12);
        Livewire::actingAs($profesor)->test(PorCalificar::class)
            ->assertSee('Estás al día, no tienes entregas por calificar')
            ->assertSee('Ver notas puestas')
            ->set('incluirCalificadas', true)
            ->assertSet('tarea', $tarea->id)
            ->assertSee('Ver / editar nota')
            ->assertViewHas('filasPagina', fn ($filas) => $filas->pluck('entrega.id')->all() === [$calificada->id]);
    }

    public function test_la_cifra_de_la_cabecera_es_la_misma_que_la_de_la_barra_inferior(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $curso = $this->cursoDe($profesor);
        $grupal = Tarea::factory()->create(['curso_id' => $curso->id, 'tipo_entrega' => 'grupal']);
        $grupo = Grupo::create(['curso_id' => $curso->id, 'nombre' => 'Grupo 1']);
        $this->entrega($grupal, now()->subDays(2), $grupo);
        $this->entrega($grupal, now()->subDays(2), $grupo);
        $individual = Tarea::factory()->create(['curso_id' => $curso->id]);
        $this->entrega($individual, now()->subDays(3));
        $this->entrega($individual, now()->subDays(1));

        $total = app(PanelProfesor::class)->totalPorCalificar($profesor);
        $this->assertSame(3, $total);

        $this->actingAs($profesor)->get(route('docente.por-calificar'))
            ->assertOk()
            ->assertSee('aria-label="Por calificar, 3 entregas por calificar"', false)
            ->assertSee('<span class="text-[var(--lime)]">3</span> entregas por calificar', false);

        Livewire::actingAs($profesor)->test(PorCalificar::class)->assertViewHas('totalPorCalificar', $total);
    }

    public function test_la_tabla_pagina_sobre_las_entregas_cargadas(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $tarea = Tarea::factory()->create(['curso_id' => $this->cursoDe($profesor)->id]);
        foreach (range(1, 25) as $hora) {
            $this->entrega($tarea, now()->subHours($hora));
        }

        Livewire::actingAs($profesor)->test(PorCalificar::class)
            ->assertViewHas('filasPagina', fn ($filas) => $filas->count() === 20)
            ->assertSee('Mostrando 1–20 de 25 entregas')
            ->call('irAPagina', 2)
            ->assertViewHas('filasPagina', fn ($filas) => $filas->count() === 5)
            ->assertSee('Mostrando 21–25 de 25 entregas')
            ->set('porPagina', 50)
            ->assertSet('pagina', 1)
            ->assertViewHas('filasPagina', fn ($filas) => $filas->count() === 25)
            ->set('porPagina', 7)
            ->assertSet('porPagina', 20);
    }

    public function test_agrupa_por_curso_y_semana_con_sin_semana_al_final(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $curso = $this->cursoDe($profesor);
        $semana3 = $this->tareaEnSemana($curso, 3, ['titulo' => 'Informe semana 3']);
        $semana1 = $this->tareaEnSemana($curso, 1, ['titulo' => 'Informe semana 1']);
        $campo = $this->practicaDeCampo($curso, 1, ['titulo' => 'Salida al río']);
        $this->entrega($semana3, now()->subDays(9));
        $this->calificada($semana3, now()->subDays(10));
        $this->entrega($semana1, now()->subDays(2));
        $this->entrega($campo, now()->subDays(4));

        Livewire::actingAs($profesor)->test(PorCalificar::class)
            ->assertViewHas('cursosLista', function ($cursos) use ($semana1, $semana3, $campo) {
                $semanas = $cursos->first()['semanas'];

                return $semanas->pluck('etiqueta')->all() === ['Semana 1', 'Semana 3', 'Sin semana']
                    && $semanas->map(fn ($semana) => $semana['tareas']->pluck('tarea.id')->all())->all() === [[$semana1->id], [$semana3->id], [$campo->id]]
                    && $semanas[1]['calificadas'] === 1 && $semanas[1]['total'] === 2;
            })
            // La tarea de la entrega más antigua sigue siendo el foco por defecto.
            ->assertSet('tarea', $semana3->id)
            ->assertSee('1 de 2 calificadas')
            ->assertSee('Semana 3');
    }

    public function test_filtra_por_semana_modalidad_y_tipo(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $curso = $this->cursoDe($profesor);
        $individual = $this->tareaEnSemana($curso, 2, ['titulo' => 'Individual S2']);
        $grupal = $this->tareaEnSemana($curso, 5, ['titulo' => 'Grupal S5', 'tipo_entrega' => 'grupal']);
        $campo = $this->practicaDeCampo($curso, 1, ['titulo' => 'Práctica de campo 1']);
        $grupo = Grupo::create(['curso_id' => $curso->id, 'nombre' => 'Grupo A']);
        $this->entrega($individual, now()->subDays(3));
        $this->entrega($grupal, now()->subDays(6), $grupo);
        $this->entrega($campo, now()->subDays(1));

        $ids = fn (array $esperados) => fn ($tareas) => $tareas->pluck('tarea.id')->sort()->values()->all() === collect($esperados)->sort()->values()->all();

        Livewire::actingAs($profesor)->test(PorCalificar::class)
            ->assertViewHas('opcionesSemana', fn ($opciones) => array_keys($opciones) === [2, 5, PorCalificar::SIN_SEMANA])
            ->assertViewHas('mostrarTipo', true)
            ->set('semana', '2')->assertViewHas('tareas', $ids([$individual->id]))->assertSet('tarea', $individual->id)
            ->set('semana', PorCalificar::SIN_SEMANA)->assertViewHas('tareas', $ids([$campo->id]))
            ->set('semana', '')
            ->set('modalidad', 'grupal')->assertViewHas('tareas', $ids([$grupal->id]))
            ->set('modalidad', 'individual')->assertViewHas('tareas', $ids([$individual->id, $campo->id]))
            ->set('modalidad', '')
            ->set('tipo', 'campo')->assertViewHas('tareas', $ids([$campo->id]))->assertSee('Práctica de campo')
            ->set('tipo', 'semanal')->assertViewHas('tareas', $ids([$individual->id, $grupal->id]))
            // Valores inexistentes vuelven a "todas".
            ->set('semana', 'abc')->assertSet('semana', '')
            ->set('modalidad', 'mixta')->assertSet('modalidad', '')
            ->set('tipo', 'examen')->assertSet('tipo', '');

        // Sin prácticas de campo en los cursos, el filtro de tipo no aparece.
        $otro = $this->usuarioConRol('profesor');
        $this->entrega(Tarea::factory()->create(['curso_id' => $this->cursoDe($otro)->id]), now()->subDay());
        Livewire::actingAs($otro)->test(PorCalificar::class)->assertViewHas('mostrarTipo', false);
    }

    public function test_el_avance_cuenta_entregas_en_individuales_y_grupos_en_grupales(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $curso = $this->cursoDe($profesor);
        $individual = $this->tareaEnSemana($curso, 1, ['titulo' => 'Ensayo']);
        $this->calificada($individual, now()->subDays(5));
        $this->calificada($individual, now()->subDays(5));
        $this->entrega($individual, now()->subDays(4));

        $grupal = $this->tareaEnSemana($curso, 1, ['titulo' => 'Proyecto', 'tipo_entrega' => 'grupal']);
        [$a, $b, $c] = collect(['A', 'B', 'C'])->map(fn ($letra) => Grupo::create(['curso_id' => $curso->id, 'nombre' => "Grupo {$letra}"]))->all();
        foreach (range(1, 3) as $integrante) {
            $this->calificada($grupal, now()->subDays(8), $a);
            $this->entrega($grupal, now()->subDays(7), $b);
        }
        // Un grupo con una nota ya puesta y otra entrega aún sin calificar sigue pendiente.
        $this->calificada($grupal, now()->subDays(6), $c);
        $this->entrega($grupal, now()->subDays(6), $c);

        Livewire::actingAs($profesor)->test(PorCalificar::class)
            ->assertViewHas('tareas', function ($tareas) use ($individual, $grupal) {
                $porId = $tareas->keyBy('tarea.id');

                return $porId[$individual->id]['avance'] === ['calificadas' => 2, 'total' => 3]
                    && $porId[$grupal->id]['avance'] === ['calificadas' => 1, 'total' => 3]
                    && $porId[$grupal->id]['total'] === 2;
            })
            ->assertViewHas('cursosLista', fn ($cursos) => $cursos->first()['semanas']->first()['calificadas'] === 3
                && $cursos->first()['semanas']->first()['total'] === 6)
            ->assertSee('2 de 3 calificadas')
            ->assertSee('1 de 3 grupos calificados')
            ->assertSee('Semana 1')
            ->assertSee('3 de 6 calificadas');
    }

    public function test_incluir_ya_calificadas_las_muestra_al_final_y_no_cuentan_para_la_siguiente(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $curso = $this->cursoDe($profesor);
        $tarea = $this->tareaEnSemana($curso, 1);
        $calificada = $this->calificada($tarea, now()->subDays(20), nota: 17, nombre: 'Ana Calificada');
        $pendiente = $this->entrega($tarea, now()->subDays(3), nombre: 'Beto Pendiente');

        $componente = Livewire::actingAs($profesor)->test(PorCalificar::class)
            ->assertViewHas('filasPagina', fn ($filas) => $filas->pluck('entrega.id')->all() === [$pendiente->id])
            ->assertDontSee('Ana Calificada')
            ->set('incluirCalificadas', true)
            // Pendientes primero aunque la calificada sea más antigua.
            ->assertViewHas('filasPagina', fn ($filas) => $filas->pluck('entrega.id')->all() === [$pendiente->id, $calificada->id])
            ->assertSee('Nota 17/20')
            ->assertSee('Ver / editar nota')
            ->assertSee('wire:click="abrirCalificar('.$pendiente->id.')"', false);

        // Calificar la única pendiente no salta a la ya calificada.
        $componente->call('abrirCalificar', $pendiente->id)->set('nota', '14')->call('guardarCalificacion')
            ->assertSet('mostrarModalCalificar', false);

        // Revisar una nota puesta usa el mismo modal y no avanza.
        $otra = $this->entrega($tarea, now()->subDay());
        $componente->call('abrirCalificar', $calificada->id)
            ->assertSet('nota', '17')
            ->set('nota', '18')
            ->call('guardarCalificacion')
            ->assertSet('mostrarModalCalificar', false)
            ->assertSet('entregaSeleccionadaId', null);
        $this->assertEquals(18, (float) $calificada->fresh()->nota);
        $this->assertSame('entregado', $otra->fresh()->estado);
    }

    public function test_la_tarea_calificada_del_todo_se_oculta_o_se_muestra_atenuada(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $curso = $this->cursoDe($profesor);
        $completa = $this->tareaEnSemana($curso, 1, ['titulo' => 'Tarea completa']);
        $this->calificada($completa, now()->subDays(10));
        $conPendientes = $this->tareaEnSemana($curso, 2, ['titulo' => 'Tarea con pendientes']);
        $ultima = $this->entrega($conPendientes, now()->subDays(4));
        $otra = $this->tareaEnSemana($curso, 3, ['titulo' => 'Otra tarea']);
        $this->entrega($otra, now()->subDays(2));

        Livewire::actingAs($profesor)->test(PorCalificar::class)
            ->assertViewHas('tareas', fn ($tareas) => $tareas->pluck('tarea.id')->all() === [$conPendientes->id, $otra->id])
            ->set('incluirCalificadas', true)
            ->assertViewHas('tareas', fn ($tareas) => $tareas->pluck('tarea.id')->all() === [$conPendientes->id, $otra->id, $completa->id]
                && $tareas->last()['completa'] === true)
            ->assertSee('opacity-70')
            ->set('incluirCalificadas', false)
            // Al calificar la última de la tarea abierta queda el estado vacío positivo.
            ->call('abrirCalificar', $ultima->id)->set('nota', '16')->call('guardarCalificacion')
            ->assertSet('tarea', $conPendientes->id)
            ->assertSee('Todo calificado en esta tarea ✓')
            ->assertSee('Ver notas')
            ->call('$set', 'incluirCalificadas', true)
            ->assertViewHas('filasPagina', fn ($filas) => $filas->pluck('entrega.id')->all() === [$ultima->id]);

        // Enlace profundo a una tarea calificada del todo.
        Livewire::withQueryParams(['tarea' => $completa->id])->actingAs($profesor)->test(PorCalificar::class)
            ->assertSet('tarea', $completa->id)
            ->assertSee('Todo calificado en esta tarea ✓');
    }

    public function test_la_cabecera_y_la_barra_inferior_no_cambian_con_los_filtros(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $curso = $this->cursoDe($profesor);
        $grupal = $this->tareaEnSemana($curso, 1, ['tipo_entrega' => 'grupal']);
        $grupo = Grupo::create(['curso_id' => $curso->id, 'nombre' => 'Grupo 1']);
        $this->entrega($grupal, now()->subDays(9), $grupo);
        $this->entrega($grupal, now()->subDays(9), $grupo);
        $individual = $this->tareaEnSemana($curso, 2);
        $this->entrega($individual, now()->subDays(3));
        $this->calificada($individual, now()->subDays(3));

        $total = app(PanelProfesor::class)->totalPorCalificar($profesor);
        $this->assertSame(2, $total);

        Livewire::actingAs($profesor)->test(PorCalificar::class)
            ->set('modalidad', 'individual')
            ->set('semana', '2')
            ->set('incluirCalificadas', true)
            ->assertViewHas('totalPorCalificar', $total)
            ->assertViewHas('urgentes', 1)
            ->assertViewHas('tareas', fn ($tareas) => $tareas->pluck('tarea.id')->all() === [$individual->id]);

        // La página completa (cabecera y barra inferior) con los filtros en la URL.
        $this->actingAs($profesor)->get(route('docente.por-calificar', ['modalidad' => 'individual', 'semana' => '2', 'incluirCalificadas' => 1]))
            ->assertOk()
            ->assertSee('<span class="text-[var(--lime)]">2</span> entregas por calificar', false)
            ->assertSee('aria-label="Por calificar, 2 entregas por calificar"', false);
    }

    public function test_los_enlaces_profundos_aplican_semana_modalidad_tipo_y_calificadas(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $curso = $this->cursoDe($profesor);
        $grupal = $this->tareaEnSemana($curso, 4, ['tipo_entrega' => 'grupal', 'titulo' => 'Grupal S4']);
        $grupo = Grupo::create(['curso_id' => $curso->id, 'nombre' => 'Grupo 1']);
        $this->entrega($grupal, now()->subDays(2), $grupo);
        $this->calificada($grupal, now()->subDays(2), Grupo::create(['curso_id' => $curso->id, 'nombre' => 'Grupo 2']));
        $this->entrega($this->tareaEnSemana($curso, 1), now()->subDays(8));

        Livewire::withQueryParams(['semana' => '4', 'modalidad' => 'grupal', 'tipo' => 'semanal', 'incluirCalificadas' => '1'])
            ->actingAs($profesor)->test(PorCalificar::class)
            ->assertSet('semana', '4')
            ->assertSet('modalidad', 'grupal')
            ->assertSet('tipo', 'semanal')
            ->assertSet('incluirCalificadas', true)
            ->assertSet('tarea', $grupal->id)
            ->assertViewHas('filasPagina', fn ($filas) => $filas->pluck('grupo')->all() === ['Grupo 1', 'Grupo 2']);
    }

    public function test_sin_resultados_con_los_filtros_limpiar_los_reinicia_todos(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $curso = $this->cursoDe($profesor);
        $this->entrega($this->tareaEnSemana($curso, 1), now()->subDays(2));

        Livewire::actingAs($profesor)->test(PorCalificar::class)
            ->set('semana', '1')
            ->set('modalidad', 'grupal')
            ->set('incluirCalificadas', true)
            ->assertSee('No hay entregas con esos filtros')
            ->assertSee('Limpiar filtros')
            ->assertViewHas('seleccionada', null)
            ->call('limpiarFiltros')
            ->assertSet('semana', '')
            ->assertSet('modalidad', '')
            ->assertSet('tipo', '')
            ->assertSet('incluirCalificadas', false)
            ->assertViewHas('tareas', fn ($tareas) => $tareas->count() === 1);
    }

    public function test_los_desplegables_propios_reemplazan_a_los_select_y_conservan_sus_valores(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $calculo = $this->cursoDe($profesor, ['nombre' => 'Cálculo 2', 'codigo' => 'CAL2-26-01']);
        $tarea = $this->tareaEnSemana($calculo, 2);
        $this->entrega($tarea, now()->subDays(2));
        $this->entrega($this->practicaDeCampo($calculo, 1), now()->subDay());
        $pantalla = Livewire::actingAs($profesor)->test(PorCalificar::class)
            ->assertDontSeeHtml('<select')
            ->assertSeeHtml('aria-haspopup="listbox"')
            ->assertSeeHtml('aria-labelledby="desplegable-curso-etiqueta desplegable-curso-boton"')
            ->assertSeeHtml('data-valor="'.$calculo->id.'"')
            ->assertSeeHtml('· CAL2-26-01');

        foreach (['', '2', PorCalificar::SIN_SEMANA] as $valor) {
            $pantalla->assertSeeHtml('data-valor="'.$valor.'"');
        }

        $pantalla
            ->set('curso', (string) $calculo->id)->assertSet('curso', $calculo->id)
            ->set('semana', '2')->assertSet('semana', '2')->assertViewHas('tareas', fn ($tareas) => $tareas->pluck('tarea.id')->all() === [$tarea->id])
            ->set('modalidad', 'individual')->assertSet('modalidad', 'individual')
            ->set('tipo', 'semanal')->assertSet('tipo', 'semanal')
            ->set('orden', 'pendientes')->assertSet('orden', 'pendientes')
            ->set('porPagina', '50')->assertSet('porPagina', 50);

        // La opción elegida se marca como seleccionada y las demás no.
        $html = $pantalla->html();
        foreach ([['curso', (string) $calculo->id], ['semana', '2'], ['modalidad', 'individual'], ['tipo', 'semanal'], ['orden', 'pendientes'], ['porpagina', '50']] as [$modelo, $valor]) {
            $this->assertMatchesRegularExpression('/id="desplegable-'.$modelo.'-opcion-\d+" role="option" data-valor="'.$valor.'"\s+aria-selected="true"/', $html, $modelo);
            $this->assertSame(1, preg_match_all('/id="desplegable-'.$modelo.'-opcion-\d+"[^>]*\s+aria-selected="true"/', $html), $modelo);
        }

        $pantalla->set('curso', '')->assertSet('curso', null);
    }
}
