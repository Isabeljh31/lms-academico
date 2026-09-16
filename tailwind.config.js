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

    // Modo oscuro dirigido por preferencia del sistema (prefers-color-scheme),
    // funcional en toda la plataforma sin necesidad de un toggle manual.
    darkMode: 'media',

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Neutros cálidos (stone) en vez del gris frío por defecto de Tailwind.
                gray: colors.stone,
                // Identidad LMS Académico — paleta pastel cálida (ver ARQUITECTURA.md / sección 5 del brief).
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

                // Paleta pastel fría — rol Estudiante (2026-09-16, ver
                // ARQUITECTURA.md § nueva paleta de color). La paleta cálida
                // de arriba (menta/terracota/arena/café/éxito) se mantiene
                // intacta porque Profesor/Administrador todavía la usan; se
                // migrará en un pase aparte.
                azul: {
                    fondo: '#7FA9C9',
                    oscuro: '#2F5A78',
                    'superficie-oscura': '#12232F',
                    'borde-oscuro': '#1A3242',
                    'pagina-oscura': '#0B1620',
                },
                celeste: '#B6D5E5',
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
                fondo: '#F7F9FB',
                texto: '#374151',
            },
        },
    },

    plugins: [forms],
};
