<?php

namespace App\Console\Commands;

use App\Models\Comunicado;
use App\Notifications\NuevoComunicado;
use App\Services\ConfiguracionPlataforma;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * ERS RF-87 y RF-84: notifica los comunicados institucionales programados
 * cuando llega su fecha y hora de publicación. Su visibilidad ya depende de
 * la fecha (Comunicado::scopeActivos); este comando solo envía el aviso.
 */
class PublicarComunicadosProgramados extends Command
{
    protected $signature = 'comunicados:publicar';

    protected $description = 'Notifica a los destinatarios los comunicados programados cuya fecha de publicación ya llegó';

    public function handle(ConfiguracionPlataforma $configuracion): int
    {
        $configuracion->aplicarZonaHoraria();

        $pendientes = Comunicado::activos()
            ->whereNull('curso_id')
            ->where('notificado', false)
            ->get();

        foreach ($pendientes as $comunicado) {
            Notification::send($comunicado->destinatarios(), new NuevoComunicado($comunicado));
            $comunicado->update(['notificado' => true]);
        }

        $this->info("Comunicados publicados: {$pendientes->count()}");

        return self::SUCCESS;
    }
}
