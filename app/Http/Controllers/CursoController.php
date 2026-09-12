<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Curso; // Importamos tu modelo de cursos

class CursoController extends Controller
{
    public function index()
    {
        // Por ahora, podemos retornar un texto de prueba o traer los cursos de la base de datos
        $cursos = Curso::all();
        
        return view('cursos.index', compact('cursos'));
        // O si prefieres una respuesta rápida de prueba: 
        // return "Módulo de Cursos Funcionando 🚀";
    }
}