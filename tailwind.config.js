import defaultTheme from 'tailwindcss/defaultTheme';
import colors from 'tailwindcss/colors';
import forms from '@tailwindcss/forms';

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
            },
            colors: {
                // Neutros cálidos (stone) en vez del gris frío por defecto de Tailwind.
                gray: colors.stone,
                // Identidad LearnUp — paleta pastel cálida (ver ARQUITECTURA.md / sección 5 del brief).
                menta: {
                    fondo: '#ECF8F8',
                    base: '#7FB3AE',
                    oscuro: '#3C7A75',
                    // Superficies de modo oscuro con matiz de marca (no gris/negro
                    // genérico) — ver ARQUITECTURA.md § pulido del rol estudiante.
                    'superficie-oscura': '#16302c',
                    'borde-oscuro': '#20433c',
                },
                arena: {
                    claro: '#EEE4E1',
                    medio: '#E7D8C9',
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
                    fondo: '#1768AC', // Ocean Deep — acento secundario
                    oscuro: '#2541B2', // Persian Blue — acento principal (botones, activo, iconos)
                    'superficie-oscura': '#12232F',
                    'borde-oscuro': '#1A3242',
                    'pagina-oscura': '#0B1620',
                },
                celeste: '#EBF0FB', // fondo/borde suave azulado (equivalente al --color-border del Figma)
                imperial: '#03256C', // extremo oscuro del degradado del hero + logo
                cielo: {
                    DEFAULT: '#06BEE1', // Sky Surge — tercer acento (rotación de cursos, degradado del hero)
                    claro: '#E0F7FD',
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
                fondo: '#F5F7FA', // superficie de página (igual al Figma)
                texto: '#1F2937', // color de títulos (igual al Figma)
            },
        },
    },

    plugins: [forms],
};
