<?php

namespace Tests\Feature\Seguridad;

use App\Enums\Rol;
use App\Models\Curso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * ERS RNF-03 y matriz de permisos (sección 5): cada pantalla responde según el
 * rol. Estudiante matriculado y profesor titular del curso del escenario.
 */
class AccesoPorRolTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    /**
     * Ruta, parámetros del escenario y código esperado para
     * [supervisor, profesor titular, estudiante matriculado].
     *
     * @return array<string, array{0: string, 1: array<int, string>, 2: array{0: int, 1: int, 2: int}}>
     */
    public static function rutas(): array
    {
        return [
            'panel' => ['dashboard', [], [200, 200, 200]],
            'perfil' => ['profile', [], [200, 200, 200]],
            'calendario' => ['evento-calendario.index', [], [200, 200, 200]],
            'reportes' => ['reportes.dashboard', [], [200, 200, 200]],
            'mensajería (selector)' => ['mensajeria.selector', [], [403, 200, 200]],
            'mensajería' => ['cursos.mensajeria', ['curso'], [403, 200, 200]],
            // Matriz ERS § 5: registrar notas y crear contenido es solo del Profesor del curso.
            'por calificar' => ['profesor.por-calificar', [], [403, 200, 403]],
            'propuestas del bot' => ['bot.propuestas', [], [403, 403, 200]],
            'resumen del bot' => ['bot.resumen', [], [403, 200, 403]],
            'admin usuarios' => ['supervisor.estudiantes.index', [], [200, 403, 403]],
            'admin profesores' => ['supervisor.profesores.index', [], [200, 403, 403]],
            'admin comunicados' => ['supervisor.comunicados.index', [], [200, 403, 403]],
            'admin métricas' => ['supervisor.dashboard-institucional', [], [200, 403, 403]],
            'admin riesgo' => ['supervisor.riesgo-abandono', [], [200, 403, 403]],
            'admin configuración' => ['supervisor.configuracion', [], [200, 403, 403]],
            'admin integraciones' => ['supervisor.integraciones', [], [200, 403, 403]],
            'admin auditoría' => ['supervisor.auditoria', [], [200, 403, 403]],
            'importar usuarios' => ['supervisor.estudiantes.importar', [], [200, 403, 403]],
            'cursos' => ['cursos.index', [], [200, 200, 200]],
            'crear curso' => ['cursos.crear', [], [200, 403, 403]],
            'detalle curso' => ['cursos.show', ['curso'], [200, 200, 200]],
            'editar curso' => ['cursos.editar', ['curso'], [200, 403, 403]],
            'esquema' => ['cursos.esquema', ['curso'], [200, 403, 403]],
            'matrícula' => ['cursos.matriculas', ['curso'], [200, 200, 403]],
            'libro de calificaciones' => ['cursos.libro-calificaciones', ['curso'], [200, 200, 403]],
            'grupos' => ['cursos.grupos.index', ['curso'], [200, 200, 403]],
            'asistencia' => ['asistencia.index', ['curso'], [200, 200, 403]],
            'mi asistencia' => ['cursos.mi-asistencia', ['curso'], [403, 403, 200]],
            'boleta' => ['cursos.boleta', ['curso'], [403, 403, 200]],
            'banco de preguntas' => ['cursos.banco-preguntas', ['curso'], [403, 200, 403]],
            // Ruta antigua: solo redirige a la pantalla del curso, donde se gestiona el contenido.
            'lecciones' => ['lecciones.index', ['modulo'], [403, 302, 403]],
            'tareas' => ['cursos.tareas.index', ['curso'], [200, 200, 200]],
            'crear tarea' => ['cursos.tareas.crear', ['curso'], [403, 200, 403]],
            'detalle tarea' => ['cursos.tareas.show', ['curso', 'tarea'], [200, 200, 200]],
            'editar tarea' => ['cursos.tareas.editar', ['curso', 'tarea'], [403, 200, 403]],
            'quizzes' => ['cursos.quizzes.index', ['curso'], [200, 200, 200]],
            'crear quiz' => ['cursos.quizzes.crear', ['curso'], [403, 200, 403]],
            'rendir quiz' => ['cursos.quizzes.show', ['curso', 'quiz'], [200, 200, 200]],
            'editar quiz' => ['cursos.quizzes.editar', ['curso', 'quiz'], [403, 200, 403]],
            'estadísticas quiz' => ['cursos.quizzes.estadisticas', ['curso', 'quiz'], [200, 200, 403]],
        ];
    }

    /**
     * @param  array<int, string>  $parametros
     * @param  array{0: int, 1: int, 2: int}  $esperados
     */
    #[DataProvider('rutas')]
    public function test_cada_rol_recibe_el_codigo_esperado(string $ruta, array $parametros, array $esperados): void
    {
        $escenario = $this->escenario();
        $url = route($ruta, array_map(fn (string $clave) => $escenario[$clave], $parametros));

        foreach (['supervisor', 'profesor', 'estudiante'] as $indice => $rol) {
            $this->actingAs($escenario[$rol])
                ->get($url)
                ->assertStatus($esperados[$indice]);
        }
    }

    /**
     * @param  array<int, string>  $parametros
     */
    #[DataProvider('rutas')]
    public function test_un_invitado_es_redirigido_al_login(string $ruta, array $parametros): void
    {
        $escenario = $this->escenario();

        $this->get(route($ruta, array_map(fn (string $clave) => $escenario[$clave], $parametros)))
            ->assertRedirect(route('login'));
    }

    public function test_un_profesor_no_accede_a_los_cursos_de_otro_profesor(): void
    {
        $escenario = $this->escenario();
        $otroProfesor = $this->usuarioConRol(Rol::Profesor->value);

        foreach (['cursos.show', 'cursos.tareas.index', 'cursos.libro-calificaciones', 'asistencia.index', 'cursos.grupos.index', 'cursos.matriculas'] as $ruta) {
            $this->actingAs($otroProfesor)->get(route($ruta, $escenario['curso']))->assertForbidden();
        }

        $this->actingAs($otroProfesor)->get(route('lecciones.index', $escenario['modulo']))->assertForbidden();
        $this->actingAs($otroProfesor)->get(route('cursos.tareas.show', [$escenario['curso'], $escenario['tarea']]))->assertForbidden();
    }

    public function test_un_estudiante_no_matriculado_no_ve_el_curso_ni_su_contenido(): void
    {
        $escenario = $this->escenario();
        $ajeno = $this->usuarioConRol(Rol::Estudiante->value);

        $this->actingAs($ajeno)->get(route('cursos.show', $escenario['curso']))->assertForbidden();
        $this->actingAs($ajeno)->get(route('cursos.tareas.show', [$escenario['curso'], $escenario['tarea']]))->assertForbidden();
        $this->actingAs($ajeno)->get(route('cursos.boleta', $escenario['curso']))->assertForbidden();
    }

    public function test_un_estudiante_dado_de_baja_pierde_el_acceso_al_curso(): void
    {
        $escenario = $this->escenario();
        $escenario['curso']->inscripciones()->where('estudiante_id', $escenario['estudiante']->id)->update(['estado' => 'inactivo', 'activo_local' => false]);

        $this->actingAs($escenario['estudiante'])->get(route('cursos.show', $escenario['curso']))->assertForbidden();
    }

    public function test_un_estudiante_no_ve_cursos_en_borrador_aunque_este_matriculado(): void
    {
        $escenario = $this->escenario();
        $borrador = Curso::factory()->borrador()->create(['profesor_id' => $escenario['profesor']->id]);
        $this->matricular($escenario['estudiante'], $borrador);

        $this->actingAs($escenario['estudiante'])->get(route('cursos.show', $borrador))->assertForbidden();
        $this->actingAs($escenario['estudiante'])->get(route('cursos.index'))->assertDontSee($borrador->nombre);
    }
}
