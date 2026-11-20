<?php

namespace Tests\Feature;

use App\Livewire\EventoCalendario\Index as Calendario;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\EsquemaEvaluacion;
use App\Models\Modulo;
use App\Models\SesionClase;
use App\Models\Tarea;
use App\Models\User;
use App\Services\Calendario\SeccionesProfesor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Calendario del Profesor con varias secciones: una sección es un curso
 * (registro) con el mismo nombre que otros y su propio código. Tarjetas,
 * filtro, cuadrícula y bloque semanal usan el mismo nombre, sección y orden
 * (SeccionesProfesor).
 */
class CalendarioSeccionesProfesorTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    /**
     * Matemática 1 con tres secciones (inicios distintos, creadas en desorden),
     * Comunicación con una sola, una sección de otro profesor y una archivada.
     *
     * @return array<string, mixed>
     */
    protected function secciones(): array
    {
        $this->crearRoles();
        $this->travelTo(Carbon::parse('2026-10-28 10:00'));
        $profesor = $this->usuarioConRol('profesor');
        $otro = $this->usuarioConRol('profesor');
        $curso = fn (array $datos) => Curso::factory()->create(['profesor_id' => $profesor->id, 'modalidad' => 'virtual', 'semanas_duracion' => 16, 'usa_asistencia' => true, ...$datos]);

        return [
            'profesor' => $profesor,
            'otro' => $otro,
            'mat3' => $curso(['nombre' => 'Matemática 1', 'codigo' => 'MAT1-26-03', 'fecha_inicio' => '2026-09-21']),
            'mat1' => $curso(['nombre' => 'Matemática 1', 'codigo' => 'MAT1-26-01', 'fecha_inicio' => '2026-09-07']),
            'mat2' => $curso(['nombre' => 'matematica 1', 'codigo' => 'MAT1-26-02', 'fecha_inicio' => '2026-09-14']),
            'com' => $curso(['nombre' => 'Comunicación', 'codigo' => 'COM-26-01', 'fecha_inicio' => '2026-09-07']),
            'ajena' => Curso::factory()->create(['profesor_id' => $otro->id, 'nombre' => 'Matemática 1', 'codigo' => 'MAT1-26-04', 'fecha_inicio' => '2026-09-07', 'modalidad' => 'virtual']),
            'archivada' => $curso(['nombre' => 'Matemática 1', 'codigo' => 'MAT1-25-01', 'fecha_inicio' => '2025-09-08', 'estado' => 'archivado']),
        ];
    }

    public function test_nombre_visible_seccion_y_orden_salen_de_un_solo_criterio(): void
    {
        $e = $this->secciones();
        $secciones = new SeccionesProfesor(collect([$e['com'], $e['mat3'], $e['mat1'], $e['mat2']]));

        $this->assertSame([$e['com']->id, $e['mat1']->id, $e['mat2']->id, $e['mat3']->id], array_keys($secciones->posiciones()));
        $this->assertSame('Matemática 1 · MAT1-26-02', $secciones->nombreVisible($e['mat2']->id));
        $this->assertSame('MAT1-26-02', $secciones->seccion($e['mat2']->id));
        // Una sola sección con ese nombre: se ve como hoy.
        $this->assertSame('Comunicación', $secciones->nombreVisible($e['com']->id));
        $this->assertNull($secciones->seccion($e['com']->id));
        $this->assertSame(['Comunicación', 'Matemática 1'], $secciones->grupos()->pluck('nombre')->all());
        $this->assertTrue($secciones->haySecciones());
    }

    public function test_una_seccion_sin_codigo_se_distingue_por_su_numero_de_seccion(): void
    {
        $e = $this->secciones();
        $sinCodigo = Curso::factory()->create(['profesor_id' => $e['profesor']->id, 'nombre' => 'Comunicación', 'codigo' => null]);
        $secciones = new SeccionesProfesor(collect([$e['com'], $sinCodigo]));

        $this->assertSame('Comunicación · Sección 1', $secciones->nombreVisible($sinCodigo->id));
        $this->assertSame('Comunicación · COM-26-01', $secciones->nombreVisible($e['com']->id));
    }

    public function test_cada_seccion_usa_su_propia_semana_y_solo_aparecen_las_que_dicta_y_estan_activas(): void
    {
        $e = $this->secciones();

        $calendario = Livewire::actingAs($e['profesor'])->test(Calendario::class)->instance();
        $cursos = collect($calendario->semanaPorCurso['cursos']);

        // Orden: nombre y luego código; la sección ajena y la archivada no están.
        $this->assertSame([$e['com']->id, $e['mat1']->id, $e['mat2']->id, $e['mat3']->id], $cursos->pluck('id')->all());
        $this->assertSame(['2026-09-07', '2026-09-14', '2026-09-21'], $cursos->where('grupo', 'matematica 1')->pluck('inicio')->values()->all());
        $this->assertSame(['MAT1-26-01', 'MAT1-26-02', 'MAT1-26-03'], $cursos->where('grupo', 'matematica 1')->pluck('seccion')->values()->all());
        $this->assertTrue($cursos->firstWhere('id', $e['mat1']->id)['variasSecciones']);
        $this->assertFalse($cursos->firstWhere('id', $e['com']->id)['variasSecciones']);
        $this->assertNotContains($e['ajena']->id, $cursos->pluck('id'));
        $this->assertNotContains($e['archivada']->id, $cursos->pluck('id'));
    }

    public function test_la_misma_actividad_en_dos_secciones_se_muestra_en_cada_una_con_sus_propios_contadores(): void
    {
        $e = $this->secciones();
        $estudiante = $this->usuarioConRol('estudiante');
        $tareas = collect(['mat1', 'mat2'])->mapWithKeys(fn ($clave) => [$clave => Tarea::factory()->create([
            'curso_id' => $e[$clave]->id, 'modulo_id' => Modulo::factory()->create(['curso_id' => $e[$clave]->id])->id,
            'titulo' => 'Práctica 3', 'fecha_entrega' => '2026-10-29 23:59',
        ])]);
        Entrega::create(['tarea_id' => $tareas['mat1']->id, 'estudiante_id' => $estudiante->id, 'estado' => 'entregado', 'respuesta_texto' => 'Listo']);

        $eventos = collect(Livewire::actingAs($e['profesor'])->test(Calendario::class)->instance()->eventos);
        $enMat1 = $eventos->firstWhere('id', 'tarea-'.$tareas['mat1']->id);
        $enMat2 = $eventos->firstWhere('id', 'tarea-'.$tareas['mat2']->id);

        $this->assertSame('Tarea: Práctica 3 · MAT1-26-01 · 1 por calificar', $enMat1['title']);
        $this->assertSame('Tarea: Práctica 3 · MAT1-26-02', $enMat2['title']);
        $this->assertSame([1, 0], [$enMat1['extendedProps']['porCalificar'], $enMat2['extendedProps']['porCalificar']]);
        $this->assertSame([$e['mat1']->id, $e['mat2']->id], [$enMat1['extendedProps']['cursoId'], $enMat2['extendedProps']['cursoId']]);
    }

    public function test_las_tarjetas_y_el_filtro_muestran_la_misma_seccion(): void
    {
        $e = $this->secciones();
        SesionClase::create(['curso_id' => $e['mat2']->id, 'titulo' => 'Sesión 7', 'fecha' => '2026-10-29 08:00']);
        EsquemaEvaluacion::firstOrCreate(['curso_id' => $e['mat3']->id])->rubros()->create(['nombre' => 'Parcial', 'semana' => 6, 'peso' => 30, 'orden' => 1]);

        $this->actingAs($e['profesor'])->get(route('evento-calendario.index'))
            ->assertOk()
            // a) próximas clases y c) evaluaciones por registrar (semana de esa sección).
            ->assertSee('Matemática 1 · MAT1-26-02')
            ->assertSee('Matemática 1 · MAT1-26-03 - semana 6')
            // d) filtro agrupado con sus secciones y acciones.
            ->assertSeeInOrder(['Mis cursos', 'Todos', 'Ninguno', 'Comunicación', 'Matemática 1', '3 secciones', 'MAT1-26-01', 'MAT1-26-02', 'MAT1-26-03'])
            ->assertDontSee('MAT1-26-04')
            ->assertDontSee('MAT1-25-01');
    }

    public function test_con_una_sola_seccion_por_nombre_todo_se_ve_como_antes(): void
    {
        $escenario = $this->escenario();

        $calendario = Livewire::actingAs($escenario['profesor'])->test(Calendario::class)
            ->assertSee('Muestra u oculta las actividades de cada curso.')
            ->assertDontSee('Muestra u oculta las actividades de cada sección.')
            ->assertDontSee('>Ninguno</button>', false)
            ->instance();

        $tarea = collect($calendario->eventos)->firstWhere('id', 'tarea-'.$escenario['tarea']->id);
        $this->assertSame('Tarea: '.$escenario['tarea']->titulo, $tarea['title']);
    }

    public function test_el_codocente_solo_ve_las_secciones_que_dicta(): void
    {
        $e = $this->secciones();
        $codocente = $this->usuarioConRol('profesor');
        $e['mat2']->profesoresAdicionales()->attach($codocente);

        $calendario = Livewire::actingAs($codocente)->test(Calendario::class)->instance();

        $this->assertSame([$e['mat2']->id], collect($calendario->semanaPorCurso['cursos'])->pluck('id')->all());
        // Entre SUS cursos es la única sección de esa materia: se ve su nombre, sin código.
        $this->assertSame($e['mat2']->nombre, $calendario->secciones->nombreVisible($e['mat2']->id));
        $this->assertNull($calendario->secciones->seccion($e['mat2']->id));
    }

    public function test_el_estudiante_de_dos_secciones_conserva_su_calendario(): void
    {
        $e = $this->secciones();
        $e['mat1']->update(['estado' => 'publicado']);
        $e['mat2']->update(['estado' => 'publicado']);
        $estudiante = $this->usuarioConRol('estudiante');
        $this->matricular($estudiante, $e['mat1']);
        $this->matricular($estudiante, $e['mat2']);
        $tarea = Tarea::factory()->create(['curso_id' => $e['mat1']->id, 'modulo_id' => Modulo::factory()->create(['curso_id' => $e['mat1']->id])->id, 'titulo' => 'Práctica 3', 'fecha_entrega' => '2026-10-29 23:59']);

        $calendario = Livewire::actingAs($estudiante)->test(Calendario::class)
            ->assertSee('Muestra u oculta las actividades de cada curso.')
            ->assertDontSee('Muestra u oculta las actividades de cada sección.')
            ->assertDontSee('>Ninguno</button>', false)
            ->instance();

        $this->assertNull($calendario->secciones);
        $this->assertSame('Tarea: Práctica 3', collect($calendario->eventos)->firstWhere('id', 'tarea-'.$tarea->id)['title']);
    }

    public function test_el_profesor_no_ve_el_curso_si_no_lo_dicta(): void
    {
        $e = $this->secciones();

        $this->assertFalse(User::find($e['profesor']->id)->can('gestionar', $e['ajena']));
        $this->assertNotContains($e['ajena']->id, collect(Livewire::actingAs($e['profesor'])->test(Calendario::class)->instance()->eventos)->pluck('extendedProps.cursoId'));
    }
}
