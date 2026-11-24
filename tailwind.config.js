import defaultTheme from 'tailwindcss/defaultTheme';
import colors from 'tailwindcss/colors';
import forms from '@tailwindcss/forms';

// Tokens de marca como variables CSS (canales RGB en resources/css/app.css):
// el valor por defecto es la paleta azul de siempre y el tema del Profesor
// (.tema-profesor) los redefine, así todas sus pantallas cambian de color sin
// tocar cada vista.
const variable = (nombre) => `rgb(var(--lu-${nombre}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    // Modo oscuro solo por clase explícita (nada la activa hoy): el rediseño
    // 2026-09-16 del rol estudiante (paleta "LearnUp" azul corporativo,
    // guiada por el Figma en /DISEñO FIGMA) es una versión clara fija, sin
    // depender de prefers-color-scheme del sistema del usuario.
    darkMode: 'class',

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                // Tema del Profesor: títulos en serif y rótulos en monoespaciada (fuentes cargadas en layouts/panel-profesor).
                serif: ['"Instrument Serif"', ...defaultTheme.fontFamily.serif],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                // Neutros cálidos (stone) en vez del gris frío por defecto de Tailwind.
                gray: colors.stone,
                // Identidad LearnUp — paleta pastel cálida (ver ARQUITECTURA.md / sección 5 del brief).
                // menta y arena: acento y superficies del Supervisor, como variables (el tema .tema-supervisor las redefine).
                menta: {
                    fondo: variable('menta-fondo'), // #ECF8F8
                    base: variable('menta-base'), // #7FB3AE
                    oscuro: variable('menta-oscuro'), // #3C7A75
                    // Superficies de modo oscuro con matiz de marca (no gris/negro
                    // genérico) — ver ARQUITECTURA.md § pulido del rol estudiante.
                    'superficie-oscura': '#16302c',
                    'borde-oscuro': '#20433c',
                },
                arena: {
                    claro: variable('arena-claro'), // #EEE4E1
                    medio: variable('arena-medio'), // #E7D8C9
                },
                terracota: {
                    fondo: '#E6BEAE',
                    base: '#CB8A72',
                    oscuro: '#B2604A',
                },
                cafe: {
                    DEFAULT: '#B2967D',
                    arcilla: '#B2967D',
                },
                exito: {
                    base: '#6B9E78',
                    oscuro: '#3F6B4A',
                },

                // Paleta "azul corporativo" — rol Estudiante (rediseño
                // 2026-09-16, guiado por /DISEñO FIGMA). Reemplaza los tonos
                // de la paleta pastel fría anterior por los mismos hex del
                // Figma (Imperial/Persian/Ocean/Sky) sin renombrar las
                // clases, así todas las vistas que ya usaban azul-oscuro/
                // celeste/fondo/texto heredan el color nuevo automáticamente.
                // La paleta cálida de arriba (menta/terracota/arena/café/
                // éxito) se mantiene intacta porque Profesor/Administrador
                // todavía la usan.
                azul: {
                    fondo: variable('azul-fondo'), // Ocean Deep #1768AC — acento secundario
                    oscuro: variable('azul-oscuro'), // Persian Blue #2541B2 — acento principal (botones, activo, iconos)
                    'superficie-oscura': '#12232F',
                    'borde-oscuro': '#1A3242',
                    'pagina-oscura': '#0B1620',
                },
                celeste: variable('celeste'), // #EBF0FB fondo/borde suave azulado (equivalente al --color-border del Figma)
                imperial: variable('imperial'), // #03256C extremo oscuro del degradado del hero + logo
                cielo: {
                    DEFAULT: variable('cielo'), // #06BEE1 Sky Surge — tercer acento (rotación de cursos, degradado del hero)
                    claro: variable('cielo-claro'), // #E0F7FD
                },
                lavanda: {
                    fondo: '#B8B0D8',
                    oscuro: '#5B4E8A',
                },
                verde: {
                    fondo: '#B8D8C8',
                    oscuro: '#3F6B4A',
                },
                coral: {
                    fondo: '#E8A9A0',
                    oscuro: '#A14B3F',
                },
                fondo: variable('fondo'), // #F5F7FA superficie de página (igual al Figma)
                texto: variable('texto'), // #1F2937 color de títulos (igual al Figma)
            },
        },
    },

    plugins: [forms],
};
