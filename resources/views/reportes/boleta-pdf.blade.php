<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; color: #44403c; font-size: 12px; }
        h1 { color: #3C7A75; font-size: 18px; margin-bottom: 0; }
        h2 { color: #57534e; font-size: 14px; margin-top: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #e7d8c9; padding: 6px 8px; text-align: left; }
        th { background: #ecf8f8; color: #3C7A75; }
        .resumen { margin-top: 16px; }
        .resumen span { display: inline-block; margin-right: 24px; font-weight: bold; }
        .pie { margin-top: 32px; font-size: 10px; color: #a8a29e; }
    </style>
</head>
<body>
    <h1>LMS Académico — Boleta de notas</h1>
    <p>{{ $progreso['curso']->nombre }} — {{ now()->format('d/m/Y') }}</p>
    <p>Estudiante: <strong>{{ $estudiante->name }}</strong> ({{ $estudiante->email }})</p>

    <div class="resumen">
        <span>Promedio de tareas: {{ $progreso['promedio_tareas'] ?? 'N/A' }}</span>
        <span>Promedio de quizzes: {{ $progreso['promedio_quizzes'] ?? 'N/A' }}</span>
    </div>

    <h2>Tareas</h2>
    <table>
        <thead>
            <tr><th>Tarea</th><th>Peso</th><th>Nota</th><th>Estado</th></tr>
        </thead>
        <tbody>
            @forelse ($progreso['tareas'] as $tarea)
                <tr>
                    <td>{{ $tarea['titulo'] }}</td>
                    <td>{{ $tarea['peso'] }}</td>
                    <td>{{ $tarea['nota'] ?? '—' }}</td>
                    <td>{{ ucfirst($tarea['estado']) }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Sin tareas registradas.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Quizzes</h2>
    <table>
        <thead>
            <tr><th>Quiz</th><th>Puntaje</th></tr>
        </thead>
        <tbody>
            @forelse ($progreso['quizzes'] as $quiz)
                <tr>
                    <td>{{ $quiz['tema'] }}</td>
                    <td>{{ $quiz['puntaje'] ?? 'Sin resolver' }}</td>
                </tr>
            @empty
                <tr><td colspan="2">Sin quizzes registrados.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="pie">Generado automáticamente por LMS Académico.</p>
</body>
</html>
