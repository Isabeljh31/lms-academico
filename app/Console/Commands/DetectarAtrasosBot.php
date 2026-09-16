<?php

namespace App\Console\Commands;

use App\Services\Bot\PropuestaBotService;
use Illuminate\Console\Command;

class DetectarAtrasosBot extends Command
{
    protected $signature = 'bot:detectar-atrasos';

    protected $description = 'Detecta estudiantes atrasados y genera propuestas pendientes en propuestas_bot (nunca modifica el calendario directamente)';

    public function handle(PropuestaBotService $servicio): int
    {
        $creadas = $servicio->detectarAtrasos();

        $this->info("Propuestas nuevas creadas: {$creadas}");

        return self::SUCCESS;
    }
}
