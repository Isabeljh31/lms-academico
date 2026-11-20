{{--
    Lectura en 12 h de un campo de fecha del Profesor (Cursos\Concerns\FechasDeActividad).
    El servidor pinta el texto inicial y Alpine lo actualiza al elegir otra fecha
    (sin ida al servidor), con la misma regla: hoy / mañana / ayer / el dd/mm/aaaa.
    Parámetros: $campo, $prefijo y $vacio (texto sin fecha).
--}}
<p class="mt-1 text-xs font-medium text-slate-600 dark:text-slate-300" aria-live="polite"
    x-data="{
        leer(valor) {
            if (! valor) return @js($vacio);
            const fecha = new Date(valor);
            if (isNaN(fecha)) return @js($vacio);
            const dia = new Date(fecha); dia.setHours(0, 0, 0, 0);
            const hoy = new Date(); hoy.setHours(0, 0, 0, 0);
            const dias = Math.round((dia - hoy) / 86400000);
            const dos = (n) => String(n).padStart(2, '0');
            const cuando = dias === 0 ? 'hoy' : dias === 1 ? 'mañana' : dias === -1 ? 'ayer'
                : `el ${dos(fecha.getDate())}/${dos(fecha.getMonth() + 1)}/${fecha.getFullYear()}`;
            const hora = fecha.getHours() % 12 || 12;
            return `${@js($prefijo)} ${cuando} ${hora === 1 ? 'a la' : 'a las'} ${hora}:${dos(fecha.getMinutes())} ${fecha.getHours() < 12 ? 'a. m.' : 'p. m.'}`;
        },
    }"
    x-text="leer($wire.{{ $campo }})">{{ $this->lecturaFecha($this->{$campo}, $prefijo, $vacio) }}</p>
