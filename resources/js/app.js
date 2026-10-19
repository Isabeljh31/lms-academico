import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import esLocale from '@fullcalendar/core/locales/es';
import Chart from 'chart.js/auto';

// Expuesto globalmente para que las vistas Blade/Livewire puedan inicializar
// FullCalendar (Módulo 5) y Chart.js (Módulo 9) sin un bundle por página.
window.FullCalendar = { Calendar, dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin, esLocale };
window.Chart = Chart;

// Selector de fecha y hora del sistema (Flatpickr desde cdnjs, en español).
// Se carga una sola vez y solo en las pantallas que lo usan: el Calendario y
// el componente <x-selector-fecha-hora>.
let flatpickrCargado = null;
window.cargarFlatpickr = () => {
    if (window.flatpickr?.l10ns?.es) {
        return Promise.resolve();
    }

    flatpickrCargado ??= new Promise((resolve) => {
        const base = 'https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/';
        const css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = base + 'flatpickr.min.css';
        document.head.appendChild(css);

        const script = document.createElement('script');
        script.src = base + 'flatpickr.min.js';
        script.onload = () => {
            const locale = document.createElement('script');
            locale.src = base + 'l10n/es.js';
            locale.onload = () => resolve();
            document.head.appendChild(locale);
        };
        document.head.appendChild(script);
    });

    return flatpickrCargado;
};
