<?php

namespace Tests\Feature;

use App\Models\Comunicado;
use App\Models\NotaRubro;
use App\Models\ResultadoQuiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Fase 4: cada pantalla de cada rol carga con datos reales, muestra su
 * contenido clave y no contiene emojis (regla de iconos SVG).
 *
 * Con la variable de entorno CAPTURAR_PANTALLAS=<carpeta>, además guarda el
 * HTML de cada pantalla (con los assets apuntando a public/build) para
 * revisarla en un navegador.
 */
class RevisionPantallasTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    /** Emojis y pictogramas (rangos Unicode de símbolos gráficos). */
    public const PATRON_EMOJI = '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}]/u';

    /**
     * @return array<string, array{0: string, 1: string, 2: array<int, string>, 3: string}>
     */
    public static function pantallas(): array
    {
        return [
            'E panel' => ['estudiante', 'dashboard', [], 'Curso de prueba'],
            'E cursos' => ['estudiante', 'cursos.index', [], 'Curso de prueba'],
            'E curso' => ['estudiante', 'cursos.show', ['curso'], 'Lección visible'],
            'E tareas' => ['estudiante', 'cursos.tareas.index', ['curso'], 'Tarea de prueba'],
            'E tarea' => ['estudiante', 'cursos.tareas.show', ['curso', 'tarea'], 'Entregar tarea'],
            'E quizzes' => ['estudiante', 'cursos.quizzes.index', ['curso'], 'Quiz de prueba'],
            'E quiz' => ['estudiante', 'cursos.quizzes.show', ['curso', 'quiz'], 'Quiz de prueba'],
            'E boleta' => ['estudiante', 'cursos.boleta', ['curso'], 'Boleta de Calificaciones'],
            'E mi asistencia' => ['estudiante', 'cursos.mi-asistencia', ['curso'], 'Mi asistencia'],
            'E reportes' => ['estudiante', 'reportes.dashboard', [], 'nota final'],
            'E calendario' => ['estudiante', 'evento-calendario.index', [], 'Calendario'],
            'E mensajería (selector)' => ['estudiante', 'mensajeria.selector', [], 'Curso de prueba'],
            'E mensajería' => ['estudiante', 'cursos.mensajeria', ['curso'], 'Mensaje'],
            'E propuestas' => ['estudiante', 'bot.propuestas', [], 'ropuesta'],
            'E perfil' => ['estudiante', 'profile', [], 'Perfil'],
            'P panel' => ['profesor', 'dashboard', [], 'Cursos a cargo'],
            'P cursos' => ['profesor', 'cursos.index', [], 'Curso de prueba'],
            'P curso' => ['profesor', 'cursos.show', ['curso'], 'Contenido por semanas'],
            'P lecciones' => ['profesor', 'lecciones.index', ['modulo'], 'Lección visible'],
            'P tareas' => ['profesor', 'cursos.tareas.index', ['curso'], 'Tarea de prueba'],
            'P nueva tarea' => ['profesor', 'cursos.tareas.crear', ['curso'], 'Formatos de archivo permitidos'],
            'P tarea' => ['profesor', 'cursos.tareas.show', ['curso', 'tarea'], 'Sin entregar'],
            'P quizzes' => ['profesor', 'cursos.quizzes.index', ['curso'], 'Quiz de prueba'],
            'P editar quiz' => ['profesor', 'cursos.quizzes.editar', ['curso', 'quiz'], 'ya rindieron este quiz'],
            'P estadísticas' => ['profesor', 'cursos.quizzes.estadisticas', ['curso', 'quiz'], 'Estadísticas'],
            'P banco' => ['profesor', 'cursos.banco-preguntas', ['curso'], 'Banco de preguntas'],
            'P libro' => ['profesor', 'cursos.libro-calificaciones', ['curso'], 'Nota final'],
            'P grupos' => ['profesor', 'cursos.grupos.index', ['curso'], 'Grupos'],
            'P asistencia' => ['profesor', 'asistencia.index', ['curso'], 'Clase 1'],
            'P matrícula' => ['profesor', 'cursos.matriculas', ['curso'], 'Las matrículas las registra el Supervisor'],
            'P por calificar' => ['profesor', 'docente.por-calificar', [], 'Por calificar'],
            'P reportes' => ['profesor', 'reportes.dashboard', [], 'Detalle por curso'],
            'P calendario' => ['profesor', 'evento-calendario.index', [], 'Calendario'],
            'S calendario' => ['supervisor', 'evento-calendario.index', [], 'Cursos en marcha'],
            'P mensajería (selector)' => ['profesor', 'mensajeria.selector', [], 'Curso de prueba'],
            'P mensajería' => ['profesor', 'cursos.mensajeria', ['curso'], 'Mensaje'],
            'P resumen bot' => ['profesor', 'bot.resumen', [], 'Resumen'],
            'S panel' => ['supervisor', 'dashboard', [], 'Tasa de finalización'],
            'S usuarios' => ['supervisor', 'admin.usuarios.index', [], 'Gestión de usuarios'],
            'S importar' => ['supervisor', 'usuarios.importar', [], 'Formato del archivo'],
            'S profesores' => ['supervisor', 'admin.profesores.index', [], 'Profesores y carga académica'],
            'S cursos' => ['supervisor', 'cursos.index', [], 'Nuevo curso'],
            'S nuevo curso' => ['supervisor', 'cursos.crear', [], 'Profesor titular'],
            'S editar curso' => ['supervisor', 'cursos.editar', ['curso'], 'Registrar asistencia en este curso'],
            'S curso' => ['supervisor', 'cursos.show', ['curso'], 'Esquema de evaluación'],
            'S esquema' => ['supervisor', 'cursos.esquema', ['curso'], 'Suma de pesos'],
            'S matrícula' => ['supervisor', 'cursos.matriculas', ['curso'], 'Matricular estudiante'],
            'S comunicados' => ['supervisor', 'admin.comunicados.index', [], 'Aviso institucional'],
            'S dashboard' => ['supervisor', 'admin.metricas', [], 'Tasa de abandono'],
            'S riesgo' => ['supervisor', 'admin.analitica-riesgo', [], 'Estudiantes en riesgo'],
            'S configuración' => ['supervisor', 'admin.configuracion', [], 'Nota mínima aprobatoria'],
            'S integraciones' => ['supervisor', 'admin.integraciones', [], 'Contrato de integración'],
            'S auditoría' => ['supervisor', 'admin.auditoria', [], 'Bitácora de auditoría'],
            'S reportes' => ['supervisor', 'reportes.dashboard', [], 'Detalle por curso'],
        ];
    }

    /**
     * Escenario con datos en todas las secciones para evitar revisar solo estados vacíos.
     *
     * @return array<string, mixed>
     */
    protected function escenarioCompleto(): array
    {
        $escenario = $this->escenario();
        $escenario['tarea']->update(['titulo' => 'Tarea de prueba']);
        $escenario['quiz']->update(['tema' => 'Quiz de prueba']);
        $escenario['modulo']->lecciones()->create(['titulo' => 'Lección visible', 'estado' => 'publicado', 'orden' => 1, 'descripcion' => 'Descripción de la lección']);
        $rubros = $this->esquemaValido($escenario['curso'])->rubros;
        NotaRubro::create(['rubro_id' => $rubros[0]->id, 'estudiante_id' => $escenario['estudiante']->id, 'nota' => 14]);
        $escenario['curso']->sesionesClase()->create(['titulo' => 'Clase 1', 'fecha' => now()->subDay(), 'numero_sesion' => 1]);
        ResultadoQuiz::create(['quiz_id' => $escenario['quiz']->id, 'estudiante_id' => $this->usuarioConRol('estudiante')->id, 'intento' => 1, 'respuestas' => [0, 1], 'puntaje' => 20]);
        Comunicado::create(['user_id' => $escenario['supervisor']->id, 'titulo' => 'Aviso institucional', 'contenido' => 'Contenido', 'prioridad' => 'informativo', 'destinatario' => 'todos', 'activo' => true]);

        return $escenario;
    }

    /**
     * @param  array<int, string>  $parametros
     */
    #[DataProvider('pantallas')]
    public function test_la_pantalla_carga_con_su_contenido_y_sin_emojis(string $rol, string $ruta, array $parametros, string $textoClave): void
    {
        $escenario = $this->escenarioCompleto();

        $respuesta = $this->actingAs($escenario[$rol])
            ->get(route($ruta, array_map(fn (string $clave) => $escenario[$clave], $parametros)))
            ->assertOk()
            ->assertSee($textoClave);

        $html = $respuesta->getContent();

        $this->assertDoesNotMatchRegularExpression(self::PATRON_EMOJI, $html, "La pantalla {$ruta} ({$rol}) contiene emojis.");

        if ($carpeta = getenv('CAPTURAR_PANTALLAS')) {
            File::ensureDirectoryExists($carpeta);
            $base = 'file:///'.str_replace('\\', '/', public_path('build')).'/';
            File::put($carpeta.'/'.$rol.'-'.str_replace('.', '-', $ruta).'.html', str_replace(url('build').'/', $base, $html));
        }
    }
}
