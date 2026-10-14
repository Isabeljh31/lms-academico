<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Models\IntegracionExterna;
use App\Models\User;
use App\Rules\Dni;
use App\Rules\NombrePersona;
use App\Services\Matricula\GestorMatriculas;
use App\Services\Usuarios\GestorCuentas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * ERS RF-100 a RF-106: endpoint de integración externa para sincronizar matrículas.
 * POST /api/v1/matriculas
 * Autenticación: Bearer <api_key> (middleware ValidarApiKey).
 */
class MatriculaController extends Controller
{
    /**
     * ERS RF-103: payload mínimo (JSON, RNF-21):
     *   usuario:   {nombres, apellidos, correo, rol}
     *   curso:     {id} o {codigo}
     *   matricula: {estado}  → activo | inactivo
     */
    public function sincronizar(Request $request, GestorCuentas $cuentas, GestorMatriculas $matriculas): JsonResponse
    {
        if (! $request->isJson()) {
            return response()->json(['error' => 'El cuerpo de la solicitud debe ser JSON (Content-Type: application/json).'], 415);
        }

        $correoRecibido = User::normalizarEmail((string) $request->input('usuario.correo', ''));

        $validated = $request->validate([
            'usuario' => ['required', 'array'],
            'usuario.nombres' => ['required', 'string', new NombrePersona],
            'usuario.apellidos' => ['required', 'string', new NombrePersona],
            // El contrato del ERS (3.17) no exige el DNI; si llega, debe ser válido y no pertenecer a otra cuenta.
            'usuario.dni' => ['sometimes', 'nullable', new Dni, Rule::unique('users', 'dni')->where(fn ($q) => $q->where('email', '!=', $correoRecibido))],
            'usuario.correo' => ['required', 'email', 'max:255'],
            'usuario.rol' => ['sometimes', 'string', 'in:estudiante'],
            'curso' => ['required', 'array'],
            'curso.id' => ['required_without:curso.codigo', 'nullable', 'integer'],
            'curso.codigo' => ['required_without:curso.id', 'nullable', 'string', 'max:50'],
            'matricula' => ['required', 'array'],
            'matricula.estado' => ['required', 'string', 'in:activo,inactivo'],
        ], [
            'usuario.rol.in' => 'La API solo sincroniza matrículas de estudiantes.',
            'usuario.dni.unique' => 'El DNI ya pertenece a otra cuenta de LearnUp.',
        ], [
            'usuario.nombres' => 'nombres',
            'usuario.apellidos' => 'apellidos',
        ]);

        /** @var IntegracionExterna $integracion */
        $integracion = $request->attributes->get('integracion');

        $curso = Curso::query()
            ->when($validated['curso']['id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->when(! ($validated['curso']['id'] ?? null), fn ($q) => $q->where('codigo', strtoupper($validated['curso']['codigo'])))
            ->first();

        if (! $curso) {
            throw ValidationException::withMessages(['curso' => 'El curso indicado no existe en LearnUp.']);
        }

        $datos = $validated['usuario'];
        $correo = $correoRecibido;

        $resultado = DB::transaction(function () use ($cuentas, $matriculas, $datos, $correo, $curso, $validated, $integracion) {
            $usuario = User::where('email', $correo)->first();
            $usuarioCreado = false;

            // ERS RF-104: crear la cuenta si no existe (contraseña temporal + correo de bienvenida).
            if (! $usuario) {
                $usuario = $cuentas->crear(
                    nombres: $datos['nombres'],
                    apellidos: $datos['apellidos'],
                    email: $correo,
                    rol: 'estudiante',
                    ejecutor: null,
                    origen: 'API '.$integracion->nombre,
                    dni: $datos['dni'] ?? null,
                )['usuario'];
                $usuarioCreado = true;
            }

            $inscripcion = $matriculas->sincronizarDesdeApi($curso, $usuario, $validated['matricula']['estado'], $integracion->nombre);

            return compact('usuario', 'usuarioCreado', 'inscripcion');
        });

        return response()->json([
            'ok' => true,
            'usuario_creado' => $resultado['usuarioCreado'],
            'usuario_id' => $resultado['usuario']->id,
            'curso_id' => $curso->id,
            'matricula_id' => $resultado['inscripcion']?->id,
            'estado' => $validated['matricula']['estado'],
            'acceso_local' => (bool) ($resultado['inscripcion']?->activo_local ?? false),
        ], $resultado['usuarioCreado'] ? 201 : 200);
    }
}
