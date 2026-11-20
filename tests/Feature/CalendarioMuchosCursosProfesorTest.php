<?php

namespace Tests\Feature;

use App\Livewire\EventoCalendario\Index as Calendario;
use App\Models\Curso;
use App\Models\Modulo;
use App\Models\SesionClase;
use App\Models\Tarea;
use App\Models\User;
use App\Services\Calendario\SeccionesProfesor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Calendario del Profesor con muchos cursos y secciones (más secciones que
 * colores): modo "muchos cursos" con buscador, atajos y grupos colapsados, el
 * curso en texto en cada actividad, y el mismo número de consultas sin
 * importar cuántos cursos tenga.
 */
class CalendarioMuchosCursosProfesorTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    public const MATERIAS = ['Matemática 1', 'Comunicación', 'Física I', 'Química General', 'Programación Web', 'Estadística Aplicada', 'Historia del Perú', 'Inglés Técnico'];

    /** @var array<string, int> */
    protected array $secuencia = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearRoles();
        $this->travelTo(Carbon::parse('2026-10-28 07:00'));
    }

    /**
     * Secciones repartidas entre las 8 materias, cada una con una tarea y una
     * clase esta semana.
     *
     * @return list<Curso>
     */
    protected function seccionesDe(User $profesor, int $cantidad, array $atributos = []): array
    {
        return collect(range(0, $cantidad - 1))->map(function (int $i) use ($profesor, $atributos) {
            $materia = self::MATERIAS[$i % count(self::MATERIAS)];
            $numero = $this->secuencia[$materia] = ($this->secuencia[$materia] ?? 0) + 1;
            $curso = Curso::factory()->create([
                'profesor_id' => $profesor->id, 'nombre' => $materia, 'codigo' => sprintf('%s-26-%02d', Curso::prefijoDesdeNombre($materia), $numero),
                'fecha_inicio' => '2026-09-07', 'usa_asistencia' => true, 'modalidad' => 'virtual', ...$atributos,
            ]);
            $modulo = Modulo::factory()->create(['curso_id' => $curso->id, 'semana' => 8]);
            Tarea::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id, 'titulo' => 'Práctica '.$numero, 'fecha_entrega' => '2026-10-29 23:59']);
            SesionClase::create(['curso_id' => $curso->id, 'titulo' => 'Sesión 8', 'fecha' => '2026-10-29 08:00']);

            return $curso;
        })->all();
    }

    public function test_25_secciones_de_8_materias_con_codocencia_y_estados_mezclados(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $otro = $this->usuarioConRol('profesor');
        $propias = $this->seccionesDe($profesor, 20);
        $borradores = $this->seccionesDe($profesor, 2, ['estado' => 'borrador']);
        $despublicada = $this->seccionesDe($profesor, 1, ['estado' => 'despublicado']);
        $archivadas = $this->seccionesDe($profesor, 2, ['estado' => 'archivado']);
        $ajenas = $this->seccionesDe($otro, 3);
        // Co-docencia: dicta 2 secciones de otro profesor como adicional.
        $ajenas[0]->profesoresAdicionales()->attach($profesor);
        $ajenas[1]->profesoresAdicionales()->attach($profesor);

        $calendario = Livewire::actingAs($profesor)->test(Calendario::class)->instance();
        $cursos = collect($calendario->semanaPorCurso['cursos']);
        $esperados = collect([...$propias, ...$borradores, ...$despublicada, $ajenas[0], $ajenas[1]])->pluck('id');

        // 25 secciones visibles: las suyas activas y las de co-docencia; nunca las archivadas ni la ajena.
        $this->assertCount(25, $cursos);
        $this->assertEqualsCanonicalizing($esperados->all(), $cursos->pluck('id')->all());
        $this->assertNotContains($ajenas[2]->id, $cursos->pluck('id'));
        $this->assertEmpty(array_intersect(collect($archivadas)->pluck('id')->all(), $cursos->pluck('id')->all()));
        $this->assertTrue($calendario->semanaPorCurso['muchos']);
        $this->assertSame(8, $cursos->pluck('grupo')->unique()->count());
        // Orden del helper (nombre y código) y los nombres y códigos que usa toda la pantalla.
        $this->assertSame(array_keys($calendario->secciones->posiciones()), $cursos->pluck('id')->all());
        $this->assertSame('calendario-profesor-cursos-'.$profesor->id, $calendario->semanaPorCurso['claveSeleccion']);
    }

    public function test_la_busqueda_encuentra_por_nombre_sin_tildes_o_por_codigo(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $this->seccionesDe($profesor, 10);

        $cursos = collect(Livewire::actingAs($profesor)->test(Calendario::class)->instance()->semanaPorCurso['cursos']);
        $fisica = $cursos->firstWhere('seccion', 'FIS-26-01');

        // El navegador busca el texto escrito (sin tildes ni mayúsculas) dentro de este campo.
        $this->assertStringContainsString('fisica i', $fisica['busqueda']);
        $this->assertStringContainsString('fis-26-01', $fisica['busqueda']);
        $this->assertSame(2, $cursos->filter(fn ($c) => str_contains($c['busqueda'], 'matematica'))->count());
    }

    public function test_cada_actividad_lleva_su_curso_en_texto_desde_tres_cursos(): void
    {
        $dos = $this->usuarioConRol('profesor');
        [$a] = $this->seccionesDe($dos, 2);
        $tres = $this->usuarioConRol('profesor');
        [$b] = $this->seccionesDe($tres, 3);

        $tareaDe = fn (User $profesor, Curso $curso) => collect(Livewire::actingAs($profesor)->test(Calendario::class)->instance()->eventos)
            ->first(fn ($e) => $e['extendedProps']['tipo'] === 'tarea' && $e['extendedProps']['cursoId'] === $curso->id);

        // Con 1 o 2 cursos, como siempre; con 3 o más, el código del curso (los colores pueden repetirse).
        $this->assertSame('Tarea: '.$a->tareas()->value('titulo'), $tareaDe($dos, $a)['title']);
        $this->assertSame('Tarea: '.$b->tareas()->value('titulo').' · '.$b->codigo, $tareaDe($tres, $b)['title']);
    }

    public function test_el_numero_de_consultas_no_crece_con_la_cantidad_de_cursos(): void
    {
        $consultasPara = function (int $cantidad): int {
            $profesor = $this->usuarioConRol('profesor');
            $this->seccionesDe($profesor, $cantidad);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($profesor)->get(route('evento-calendario.index'))->assertOk();
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        // Primera carga del proceso: lee la configuración de la plataforma (que luego queda en memoria); no se compara.
        $consultasPara(1);
        $conDos = $consultasPara(2);

        $this->assertSame($conDos, $consultasPara(10));
        $this->assertSame($conDos, $consultasPara(25));
    }

    public function test_con_muchos_cursos_aparecen_buscador_y_atajos_y_la_vista_compila_completa(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $this->seccionesDe($profesor, SeccionesProfesor::MUCHOS_CURSOS);

        $html = $this->actingAs($profesor)->get(route('evento-calendario.index'))
            ->assertOk()
            ->assertSee('Buscar curso por nombre o código')
            ->assertSeeInOrder(['Todos', 'Ninguno', 'Con actividad esta semana'])
            ->assertSee('No hay cursos que coincidan con la búsqueda.')
            ->assertSee('Expandir todo')
            ->getContent();

        // Ningún componente Blade sin compilar (p. ej. un atributo mal escrito deja "<x-icono" en la página).
        $this->assertStringNotContainsString('<x-', $html);
    }

    public function test_con_pocos_cursos_el_filtro_se_ve_como_antes(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $this->seccionesDe($profesor, 2);

        $calendario = Livewire::actingAs($profesor)->test(Calendario::class)
            ->assertSee('Muestra u oculta las actividades de cada curso.')
            ->assertDontSee('Buscar curso por nombre o código')
            ->assertDontSee('Con actividad esta semana')
            ->instance();

        $this->assertFalse($calendario->semanaPorCurso['muchos']);
    }

    public function test_el_estudiante_con_muchos_cursos_conserva_su_calendario(): void
    {
        $profesor = $this->usuarioConRol('profesor');
        $estudiante = $this->usuarioConRol('estudiante');
        $cursos = $this->seccionesDe($profesor, 10);
        foreach ($cursos as $curso) {
            $this->matricular($estudiante, $curso);
        }

        $calendario = Livewire::actingAs($estudiante)->test(Calendario::class)
            ->assertSee('Muestra u oculta las actividades de cada curso.')
            ->assertDontSee('Buscar curso por nombre o código')
            ->assertDontSee('Expandir todo')
            ->instance();

        $this->assertNull($calendario->semanaPorCurso);
        $tarea = collect($calendario->eventos)->first(fn ($e) => $e['extendedProps']['tipo'] === 'tarea');
        $this->assertStringNotContainsString(' · ', $tarea['title']);
    }
}
