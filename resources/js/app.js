import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import Chart from 'chart.js/auto';

// Expuesto globalmente para que las vistas Blade/Livewire puedan inicializar
// FullCalendar (Módulo 5) y Chart.js (Módulo 9) sin un bundle por página.
window.FullCalendar = { Calendar, dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin };
window.Chart = Chart;
