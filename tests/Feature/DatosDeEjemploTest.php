<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\NotaRubro;
use App\Models\User;
use App\Services\Evaluacion\CalculadoraNotas;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Los datos de ejemplo (DemoAcademicoSeeder) respetan las reglas de negocio del
 * ERS y se pueden cargar más de una vez sin duplicarse.
 */
class DatosDeEjemploTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_los_datos_de_ejemplo_cumplen_las_reglas_de_negocio(): void
    {
        $calculadora = app(CalculadoraNotas::class);

        foreach (Curso::whereNotNull('codigo')->with('esquemaEvaluacion.rubros')->get() as $curso) {
            // Regla 1: los pesos suman exactamente 100 %.
            $this->assertTrue($calculadora->pesosSumanCien($curso->esquemaEvaluacion->rubros->pluck('peso')), $curso->codigo);

            // RF-25: un curso publicado tiene al menos un módulo.
            if ($curso->estado === 'publicado') {
                $this->assertTrue($curso->modulos()->exists(), $curso->codigo);
            }
        }

        $this->assertSame('archivado', Curso::where('codigo', 'MAT-101-2026I')->value('estado'));
        $this->assertTrue(NotaRubro::whereHas('rubro.esquema.curso', fn ($q) => $q->where('codigo', 'MAT-101-2026I'))->exists());
        $this->assertCount(1, Curso::where('codigo', 'MAT-201')->firstOrFail()->profesoresAdicionales);

        // Regla 3: una baja lógica que conserva la fila de la matrícula.
        $this->assertTrue(Inscripcion::where('estado', 'inactivo')->exists());
        // Regla 6: una matrícula que llegó por API.
        $this->assertTrue(Inscripcion::where('origen', 'api')->exists());
        // Notas bajo y sobre la mínima para ver el resaltado en rojo.
        $this->assertTrue(NotaRubro::where('nota', '<', 10.5)->exists());
        $this->assertTrue(NotaRubro::where('nota', '>=', 10.5)->exists());
    }

    public function test_las_cuentas_de_demostracion_ingresan_sin_cambio_obligatorio(): void
    {
        foreach (['admin@lms.edu.pe', 'profesor@lms.edu.pe', 'estudiante@lms.edu.pe'] as $correo) {
            $this->assertFalse(User::where('email', $correo)->firstOrFail()->primer_login, $correo);
        }

        $this->assertTrue(User::where('primer_login', true)->exists());
        $this->assertTrue(User::where('activo', false)->exists());
    }

    public function test_volver_a_cargar_los_datos_no_los_duplica(): void
    {
        $cursos = Curso::count();
        $usuarios = User::count();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($cursos, Curso::count());
        $this->assertSame($usuarios, User::count());
    }
}
