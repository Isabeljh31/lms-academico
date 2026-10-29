<?php

namespace Tests\Feature\Seguridad;

use App\Enums\Rol;
use App\Livewire\Cursos\Index as CursosIndex;
use App\Livewire\Supervisor\Comunicados\Index as Comunicados;
use App\Livewire\Supervisor\Estudiantes\Index as UsuariosIndex;
use App\Livewire\Supervisor\Profesores\Index as ProfesoresIndex;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Validación de entradas: XSS, inyección SQL, CSRF y asignación masiva.
 */
class ValidacionEntradasTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    public const SCRIPT = "<script>alert('xss')</script>";

    public function test_escapa_el_html_de_un_anuncio_del_curso_para_el_estudiante(): void
    {
        $escenario = $this->escenario();
        $escenario['curso']->comunicados()->create([
            'user_id' => $escenario['profesor']->id,
            'titulo' => 'Aviso '.self::SCRIPT,
            'contenido' => 'Texto '.self::SCRIPT,
            'prioridad' => 'normal',
            'destinatario' => 'estudiantes',
            'activo' => true,
        ]);

        $this->actingAs($escenario['estudiante'])
            ->get(route('cursos.show', $escenario['curso']))
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee(self::SCRIPT, false);
    }

    public function test_escapa_el_nombre_de_un_usuario_en_el_listado_del_supervisor(): void
    {
        $this->crearRoles();
        $supervisor = $this->usuarioConRol(Rol::Supervisor->value);
        $this->usuarioConRol(Rol::Estudiante->value, ['name' => 'Ana '.self::SCRIPT]);

        $this->actingAs($supervisor)
            ->get(route('supervisor.estudiantes.index'))
            ->assertOk()
            ->assertDontSee(self::SCRIPT, false);
    }

    public function test_escapa_el_nombre_del_profesor_en_la_ventana_de_confirmacion(): void
    {
        $this->crearRoles();
        $supervisor = $this->usuarioConRol(Rol::Supervisor->value);
        $profesor = $this->usuarioConRol(Rol::Profesor->value, ['name' => 'Ana '.self::SCRIPT]);

        Livewire::actingAs($supervisor)->test(ProfesoresIndex::class)
            ->call('confirmarEliminarProfesor', $profesor->id)
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee(self::SCRIPT, false);
    }

    public function test_escapa_el_contenido_de_un_comunicado_institucional(): void
    {
        $this->crearRoles();
        $supervisor = $this->usuarioConRol(Rol::Supervisor->value);

        Livewire::actingAs($supervisor)->test(Comunicados::class)
            ->set('titulo', 'Titulo '.self::SCRIPT)
            ->set('contenido', 'Cuerpo '.self::SCRIPT)
            ->call('crearComunicado')
            ->assertDontSee(self::SCRIPT, false);
    }

    public function test_las_vistas_solo_imprimen_html_sin_escapar_en_puntos_revisados(): void
    {
        // {!! !!} solo se permite en estos archivos, cada uno revisado a mano:
        // - components/icono.blade.php: rutas SVG fijas del propio componente, no imprime datos del usuario.
        // - livewire/supervisor/profesores/_modal-confirmar.blade.php y livewire/supervisor/estudiantes/_modal-confirmar.blade.php:
        //   el texto es fijo en PHP (GestionaProfesor / GestionaEstudiante); los únicos valores dinámicos
        //   (nombre, curso) pasan por e() antes de envolverse en <strong>, así que llegan ya escapados.
        $permitidos = [
            'components/icono.blade.php',
            'livewire/supervisor/profesores/_modal-confirmar.blade.php',
            'livewire/supervisor/estudiantes/_modal-confirmar.blade.php',
        ];

        $sinEscapar = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($archivo) => str_contains($archivo->getContents(), '{!!'))
            ->map(fn ($archivo) => str_replace('\\', '/', $archivo->getRelativePathname()))
            ->reject(fn ($ruta) => in_array($ruta, $permitidos, true))
            ->values()
            ->all();

        $this->assertSame([], $sinEscapar);
    }

    public function test_una_busqueda_con_sql_no_altera_ni_filtra_datos(): void
    {
        $escenario = $this->escenario();
        $inyeccion = "' OR 1=1 --";

        Livewire::actingAs($escenario['estudiante'])->test(CursosIndex::class)
            ->set('busqueda', $inyeccion)
            ->assertDontSee($escenario['curso']->nombre);

        Livewire::actingAs($escenario['supervisor'])->test(UsuariosIndex::class)
            ->set('busqueda', "x'; DROP TABLE users; --")
            ->assertOk();

        $this->assertSame(3, User::count());
    }

    public function test_el_grupo_web_verifica_el_token_csrf(): void
    {
        $this->assertContains(PreventRequestForgery::class, app('router')->getMiddlewareGroups()['web']);
        $this->assertContains('web', Route::getRoutes()->getByName('logout')->gatherMiddleware());
    }

    public function test_los_modelos_sensibles_no_aceptan_asignacion_masiva_de_campos_protegidos(): void
    {
        $this->crearRoles();
        $usuario = User::factory()->create();

        $usuario->fill(['id' => 999, 'remember_token' => 'x', 'email_verified_at' => null, 'intentos_fallidos' => 50]);

        $this->assertNotSame(999, $usuario->id);
        $this->assertNotNull($usuario->email_verified_at);
        $this->assertSame(0, (int) $usuario->intentos_fallidos);
        $this->assertNotContains('password', array_keys($usuario->toArray()));
    }
}
