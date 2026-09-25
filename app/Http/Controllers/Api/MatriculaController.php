<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\User;
use App\Notifications\NuevaTareaAsignada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * ERS RF-100 a RF-106: endpoint de integración externa para sincronizar matrículas.
 * POST /api/v1/matriculas
 * Autenticación: Bearer <api_key> (validada por middleware ValidarApiKey)
 */
class MatriculaController extends Controller
{
    /**
     * ERS RF-103: payload mínimo requerido:
     *   usuario: {nombres, apellidos, correo, rol}
     *   curso:   {id o codigo}
     *   matricula: {estado}  → 'activo' | 'inactivo'
     */
    public function sincronizar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'usuario.nombres'   => 'required|string|max:100',
            'usuario.apellidos' => 'required|string|max:100',
            'usuario.correo'    => 'required|email|max:255',
            'usuario.rol'       => 'sometimes|string|in:estudiante,profesor',
            'curso.id'          => 'required|exists:cursos,id',
            'matricula.estado'  => 'required|string|in:activo,inactivo',
        ]);

        $integracion = $request->attributes->get('integracion');

        $datosUsuario = $validated['usuario'];
        $nombreCompleto = trim($datosUsuario['nombres'] . ' ' . $datosUsuario['apellidos']);
        $correo = $datosUsuario['correo'];
        $rolNombre = $datosUsuario['rol'] ?? 'estudiante';
        $cursoId = $validated['curso']['id'];
        $estadoMatricula = $validated['matricula']['estado'];

        // ERS RF-104: crear cuenta si no existe
        $usuarioCreado = false;
        $user = User::where('email', $correo)->first();

        if (!$user) {
            $passwordTemporal = Str::random(12);
            $user = User::create([
                'name'              => $nombreCompleto,
                'email'             => $correo,
                'password'          => Hash::make($passwordTemporal),
                'email_verified_at' => now(),
                'activo'            => true,
                'primer_login'      => true,
            ]);
            $user->assignRole($rolNombre);
            $usuarioCreado = true;

            // TODO ERS RF-04: enviar correo de bienvenida con $passwordTemporal
            // Mail::to($correo)->send(new BienvenidaMail($user, $passwordTemporal));
        }

        $curso = Curso::findOrFail($cursoId);

        // ERS RF-32: el sistema externo es la fuente de verdad
        $inscripcion = Inscripcion::where('curso_id', $cursoId)
            ->where('estudiante_id', $user->id)
            ->first();

        if ($estadoMatricula === 'activo') {
            if (!$inscripcion) {
                $inscripcion = Inscripcion::create([
                    'curso_id'         => $cursoId,
                    'estudiante_id'    => $user->id,
                    'fecha_inscripcion' => now(),
                    'origen'           => 'api',
                    'estado'           => 'activo',
                    'activo_local'     => true,
                ]);

                // ERS RF-33: notificar al estudiante
                // $user->notify(new MatriculadoEnCurso($curso));

                BitacoraAuditoria::registrarAccionAdmin(
                    ejecutor: $user, // no hay usuario auth en API; usamos el estudiante como referencia
                    tipoEvento: 'matricula_api',
                    descripcion: "Matrícula creada por integración API (integracion_id: {$integracion->id}) en curso: {$curso->nombre}",
                    usuarioAfectado: $user,
                    curso: $curso
                );
            } else {
                // Reactivar si estaba inactiva
                $inscripcion->update(['estado' => 'activo', 'activo_local' => true]);
            }
        } else {
            // ERS RF-32: desactivar acceso local
            if ($inscripcion) {
                $inscripcion->update(['activo_local' => false]);
            }
        }

        return response()->json([
            'ok'             => true,
            'usuario_creado' => $usuarioCreado,
            'usuario_id'     => $user->id,
            'matricula_id'   => $inscripcion?->id,
            'estado'         => $estadoMatricula,
        ], 201);
    }
}
