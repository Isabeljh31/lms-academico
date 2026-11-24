<?php

namespace Tests\Feature;

use App\Models\SesionClase;
use App\Models\User;
use App\Services\Asistencia\EnlacesClaseRemota;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Matriz final de roles del enlace de clases remotas: quién lo lee, quién lo
 * guarda o quita (del curso y de una clase) y a quién se le ofrece el botón
 * «Unirse a la clase». Las acciones se prueban en el servicio (la vía que usan
 * todas las pantallas) y la lectura en las páginas reales.
 */
class EnlaceRemotoMatrizRolesTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    private const ENLACE_CURSO = 'https://meet.google.com/abc-defg-hij';

    private const ENLACE_NUEVO = 'https://teams.microsoft.com/l/meetup-join/nueva-reunion';

    /** Parte de la URL que no es el dominio: nunca debe verse como texto. */
    private const RUTA_ENLACE = 'abc-defg-hij';

    /** @var array<string, mixed> */
    private array $escenario;

    private SesionClase $sesion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-18 10:00:00'));
        $this->escenario = $this->escenario();
        $this->escenario['curso']->update(['modalidad' => 'remoto', 'usa_asistencia' => true, 'estado' => 'publicado', 'enlace_remoto' => self::ENLACE_CURSO]);
        $this->escenario['codocente'] = $this->usuarioConRol('profesor');
        $this->escenario['curso']->profesoresAdicionales()->attach($this->escenario['codocente']->id);
        $this->escenario['otro_profesor'] = $this->usuarioConRol('profesor');
        $this->escenario['no_matriculado'] = $this->usuarioConRol('estudiante');
        $this->sesion = $this->escenario['curso']->sesionesClase()->create([
            'titulo' => 'Clase en vivo', 'fecha' => '2026-11-18 10:00:00', 'duracion_minutos' => 90, 'numero_sesion' => 1,
        ]);
    }

    private function usuario(string $rol): ?User
    {
        return match ($rol) {
            'sin sesión' => null,
            'estudiante matriculado' => $this->escenario['estudiante'],
            'profesor dueño' => $this->escenario['profesor'],
            default => $this->escenario[str_replace([' ', '-'], '_', $rol)],
        };
    }

    /**
     * Rol => [lee el enlace, lo guarda o quita, ve «Unirse», estado HTTP del detalle del curso].
     *
     * @return array<string, array{string, bool, bool, bool, int}>
     */
    public static function matriz(): array
    {
        return [
            'sin sesión' => ['sin sesión', false, false, false, 302],
            'estudiante no matriculado' => ['no matriculado', false, false, false, 403],
            'estudiante matriculado' => ['estudiante matriculado', true, false, true, 200],
            'otro profesor' => ['otro profesor', false, false, false, 403],
            'profesor dueño' => ['profesor dueño', true, true, false, 200],
            'co-docente' => ['codocente', true, true, false, 200],
            'supervisor' => ['supervisor', true, false, false, 200],
        ];
    }

    #[DataProvider('matriz')]
    public function test_lectura_del_enlace_segun_el_rol(string $rol, bool $lee, bool $edita, bool $unirse, int $estado): void
    {
        $curso = $this->escenario['curso'];

        $this->assertSame($lee, Gate::forUser($this->usuario($rol))->allows('verEnlaceRemoto', $curso));
        $this->assertSame($edita, Gate::forUser($this->usuario($rol))->allows('editarEnlaceRemoto', $curso));
    }

    #[DataProvider('matriz')]
    public function test_guardar_y_quitar_el_enlace_del_curso_segun_el_rol(string $rol, bool $lee, bool $edita): void
    {
        $usuario = $this->usuario($rol);
        $curso = $this->escenario['curso'];
        $enlaces = app(EnlacesClaseRemota::class);

        if ($usuario === null) {
            $this->assertFalse(Gate::forUser(null)->allows('editarEnlaceRemoto', $curso));

            return;
        }

        if (! $edita) {
            $this->expectException(AuthorizationException::class);
        }

        $enlaces->guardarDelCurso($usuario, $curso, self::ENLACE_NUEVO);
        $this->assertSame(self::ENLACE_NUEVO, $curso->fresh()->enlace_remoto);
        $enlaces->guardarDelCurso($usuario, $curso->fresh(), null);
        $this->assertNull($curso->fresh()->enlace_remoto);
    }

    #[DataProvider('matriz')]
    public function test_enlace_propio_de_la_clase_segun_el_rol(string $rol, bool $lee, bool $edita): void
    {
        $usuario = $this->usuario($rol);
        $enlaces = app(EnlacesClaseRemota::class);

        if ($usuario === null) {
            $this->assertFalse(Gate::forUser(null)->allows('editarEnlaceRemoto', $this->escenario['curso']));

            return;
        }

        try {
            $enlaces->guardarDeClase($usuario, $this->sesion, self::ENLACE_NUEVO);
            $this->assertTrue($edita, 'Este rol no debería poder guardar el enlace de la clase.');
            $this->assertSame(self::ENLACE_NUEVO, $this->sesion->fresh()->enlace_remoto);
        } catch (AuthorizationException) {
            $this->assertFalse($edita, 'Este rol debería poder guardar el enlace de la clase.');
            $this->assertNull($this->sesion->fresh()->enlace_remoto);
        }
    }

    #[DataProvider('matriz')]
    public function test_boton_unirse_y_paginas_segun_el_rol(string $rol, bool $lee, bool $edita, bool $unirse, int $estado): void
    {
        $usuario = $this->usuario($rol);
        $usuario && $this->actingAs($usuario);

        $calendario = $this->get(route('evento-calendario.index'));
        $detalle = $this->get(route('cursos.show', $this->escenario['curso']));

        $detalle->assertStatus($estado)->assertDontSee(self::RUTA_ENLACE, false);

        if ($usuario === null) {
            $calendario->assertRedirect(route('login'));

            return;
        }

        $calendario->assertOk();
        if ($unirse) {
            $calendario->assertSee('Unirse a la clase')
                ->assertSee('href="'.self::ENLACE_CURSO.'" target="_blank" rel="noopener noreferrer"', false)
                ->assertDontSee(self::RUTA_ENLACE.'</', false);
        } else {
            $calendario->assertDontSee('Unirse a la clase')->assertDontSee(self::RUTA_ENLACE, false);
        }
    }
}
