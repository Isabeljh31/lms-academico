<?php

namespace App\Console\Commands;

use App\Enums\ModoCalculo;
use App\Enums\Rol;
use App\Livewire\Profesor\PorCalificar\Index as PorCalificar;
use App\Models\BancoPregunta;
use App\Models\Comunicado;
use App\Models\Curso;
use App\Models\Debate;
use App\Models\DebateComentario;
use App\Models\Entrega;
use App\Models\EsquemaEvaluacion;
use App\Models\EventoCalendario;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Mensaje;
use App\Models\Modulo;
use App\Models\Quiz;
use App\Models\RubroEvaluacion;
use App\Models\Tarea;
use App\Models\User;
use App\Services\ConfiguracionPlataforma;
use App\Services\Evaluacion\AsignadorEvaluacion;
use App\Services\Evaluacion\CalculadoraNotas;
use App\Services\Evaluacion\PracticasEnEvaluaciones;
use App\Services\Reportes\CursosProfesor;
use App\Services\Reportes\PanelProfesor;
use App\Services\Usuarios\GestorCuentas;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;
use Throwable;

/**
 * Siembra datos de demostración actuales (relativos a hoy) y sin atrasos en
 * una base sin cursos: ~40 estudiantes nuevos, 12 cursos con contenido en
 * las 4 secciones de cada semana, tareas con todas las entregas a tiempo,
 * quizzes rendidos, asistencia tomada, anuncios, debates y mensajes.
 *
 * - Solo INSERT con los modelos del proyecto (sin los servicios que escriben
 *   en la bitácora inmutable o envían correos/notificaciones; Mail,
 *   Notification y Queue se simulan). No toca esquema, roles, permisos,
 *   migraciones ni los usuarios existentes: se verifica antes y después.
 * - Sin --apply simula en una transacción con rollback y no escribe archivos.
 *   Con --apply guarda storage/app/backups-demo/<fecha-hora>_siembra/manifest.json
 *   con los ids creados; --limpiar=<carpeta> los borra (simula sin --apply).
 */
class SembrarDemoActual extends Command
{
    protected $signature = 'app:sembrar-demo-actual
        {--apply : Escribe de verdad (sin esta opción solo simula)}
        {--forzar : Siembra aunque ya existan cursos}
        {--semilla=2026 : Semilla del contenido aleatorio}
        {--limpiar= : Carpeta _siembra (ruta o nombre en storage/app/backups-demo) cuyo contenido se borra}
        {--sin-paginas : No prueba las pantallas (para las pruebas automáticas)}';

    protected $description = 'Siembra cursos y estudiantes de demostración actuales y sin atrasos (simula por defecto)';

    protected const PASSWORD = 'password123';

    protected const DOMINIO = 'lms.com';

    protected const CORREO_GERONIMO = 'geronimo@lms.com';

    protected const PROFESORES = ['pepito' => 'Pepito Reyes Diaz', 'jose' => 'José Cuya Camara', 'alan' => 'Alan Garcia', 'gerardo' => 'Gerardo Sarmiento'];

    /**
     * codigo, nombre, titular, semana actual (null = borrador que aún no empieza), modalidad,
     * matriculados, horario [día ISO, inicio, fin, aula], opciones, co-docente.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: ?int, 4: string, 5: int, 6: list<array{0: int, 1: string, 2: string, 3: ?string}>, 7: list<string>, 8: ?string}>
     */
    protected const CURSOS = [
        ['EST-26-01', 'Estadística Descriptiva', 'pepito', 9, 'presencial', 34, [[1, '08:00', '10:00', 'A-201'], [3, '08:00', '09:30', 'A-201']], ['geronimo', 'temprana'], 'gerardo'],
        ['ANA-26-01', 'Análisis Matemático I', 'pepito', 7, 'remoto', 30, [[2, '18:30', '20:00', null], [4, '18:30', '20:00', null]], ['quiz_futuro'], null],
        ['PRO-26-01', 'Programación I', 'pepito', 11, 'virtual', 32, [], ['grupos', 'geronimo', 'debate'], null],
        ['BDA-26-01', 'Bases de Datos', 'pepito', 6, 'presencial', 28, [[2, '10:00', '12:00', 'Lab 3'], [5, '10:00', '11:30', 'B-105']], ['grupos', 'quiz_futuro', 'debate'], null],
        ['FIS-26-01', 'Física I', 'pepito', 10, 'presencial', 26, [[1, '14:00', '16:00', 'C-302'], [4, '14:00', '15:30', 'Lab 1']], ['practicas', 'temprana', 'debate'], null],
        ['RED-26-01', 'Redes de Computadoras', 'pepito', null, 'presencial', 0, [[3, '16:00', '18:00', 'Lab 2'], [5, '16:00', '17:30', 'Lab 2']], [], null],
        ['CON-26-01', 'Contabilidad General', 'jose', 8, 'presencial', 31, [[2, '08:00', '10:00', 'B-208'], [4, '08:00', '09:30', 'B-208']], ['geronimo', 'quiz_futuro', 'debate'], null],
        ['MKT-26-01', 'Fundamentos de Marketing', 'jose', 12, 'virtual', 36, [], ['grupos', 'debate', 'debate_doble'], null],
        ['DER-26-01', 'Introducción al Derecho', 'alan', 5, 'remoto', 27, [[1, '19:00', '20:30', null], [3, '19:00', '20:30', null]], ['quiz_futuro', 'temprana'], null],
        ['BIO-26-01', 'Biología General', 'alan', 10, 'presencial', 29, [[3, '10:00', '12:00', 'Lab 4'], [5, '08:00', '09:30', 'A-110']], ['debate', 'debate_doble'], null],
        ['ECO-26-01', 'Microeconomía', 'gerardo', 6, 'virtual', 25, [], ['quiz_futuro'], null],
        ['QUI-26-01', 'Química General', 'gerardo', 8, 'remoto', 33, [[2, '16:00', '17:30', null], [5, '14:00', '15:30', null]], ['grupos', 'quiz_futuro'], null],
    ];

    /** @var array<string, list<string>> 16 temas por curso, uno por semana. */
    protected const TEMAS = [
        'EST-26-01' => ['Introducción a la estadística', 'Tipos de variables y escalas de medición', 'Recolección y organización de datos', 'Tablas de distribución de frecuencias', 'Gráficos estadísticos', 'Medidas de tendencia central', 'Medidas de posición: cuartiles y percentiles', 'Repaso para el examen parcial', 'Medidas de dispersión', 'Asimetría y curtosis', 'Diagrama de caja y datos atípicos', 'Distribuciones bidimensionales', 'Correlación lineal', 'Regresión lineal simple', 'Números índice', 'Repaso final'],
        'ANA-26-01' => ['Números reales y desigualdades', 'Valor absoluto', 'Funciones reales', 'Gráficas y transformaciones de funciones', 'Límites de funciones', 'Límites laterales e infinitos', 'Continuidad', 'Repaso para el examen parcial', 'La derivada', 'Reglas de derivación', 'Regla de la cadena', 'Derivación implícita', 'Máximos y mínimos', 'Razón de cambio', 'Teorema del valor medio', 'Repaso final'],
        'PRO-26-01' => ['Algoritmos y pseudocódigo', 'Entorno de desarrollo y primer programa', 'Variables, tipos de datos y operadores', 'Entrada y salida de datos', 'Estructuras condicionales', 'Bucles con while', 'Bucles con for', 'Repaso para el examen parcial', 'Funciones y parámetros', 'Arreglos unidimensionales', 'Cadenas de caracteres', 'Matrices', 'Recursividad', 'Archivos de texto', 'Proyecto integrador', 'Repaso final'],
        'BDA-26-01' => ['Introducción a los sistemas de bases de datos', 'Modelo entidad-relación', 'Modelo relacional', 'Normalización', 'SQL: creación de tablas', 'SQL: consultas con SELECT', 'SQL: JOIN entre tablas', 'Repaso para el examen parcial', 'Funciones de agregación y GROUP BY', 'Subconsultas', 'Vistas e índices', 'Transacciones y concurrencia', 'Procedimientos almacenados y triggers', 'Seguridad y respaldos', 'Bases de datos NoSQL', 'Repaso final'],
        'FIS-26-01' => ['Magnitudes, unidades y vectores', 'Cinemática en una dimensión', 'Movimiento en dos dimensiones', 'Leyes de Newton', 'Aplicaciones de las leyes de Newton', 'Trabajo y energía cinética', 'Conservación de la energía', 'Repaso para el examen parcial', 'Cantidad de movimiento y choques', 'Rotación de cuerpos rígidos', 'Torque y equilibrio', 'Gravitación', 'Oscilaciones', 'Ondas mecánicas', 'Mecánica de fluidos', 'Repaso final'],
        'RED-26-01' => ['Introducción a las redes', 'Modelos OSI y TCP/IP', 'Medios de transmisión', 'Capa de enlace y Ethernet', 'Direccionamiento IPv4', 'Subredes y VLSM', 'Enrutamiento estático', 'Repaso para el examen parcial', 'Enrutamiento dinámico', 'Capa de transporte: TCP y UDP', 'Servicios DNS y DHCP', 'Redes inalámbricas', 'IPv6', 'Seguridad en redes', 'Diseño de una red pequeña', 'Repaso final'],
        'CON-26-01' => ['La contabilidad y la empresa', 'La ecuación contable', 'Cuentas y plan contable', 'La partida doble', 'El libro diario', 'El libro mayor', 'Balance de comprobación', 'Repaso para el examen parcial', 'Ajustes contables', 'Inventarios y kárdex', 'Activo fijo y depreciación', 'Planillas de remuneraciones', 'Impuestos: IGV y renta', 'Estado de situación financiera', 'Estado de resultados', 'Repaso final'],
        'MKT-26-01' => ['¿Qué es el marketing?', 'El entorno de marketing', 'Comportamiento del consumidor', 'Investigación de mercados', 'Segmentación de mercados', 'Público objetivo y posicionamiento', 'Propuesta de valor', 'Repaso para el examen parcial', 'Producto y marca', 'Estrategias de precio', 'Canales de distribución', 'Comunicación y promoción', 'Marketing digital', 'Redes sociales y contenidos', 'Plan de marketing', 'Repaso final'],
        'DER-26-01' => ['El derecho y sus fuentes', 'La norma jurídica', 'El ordenamiento jurídico peruano', 'La Constitución Política', 'Derechos fundamentales', 'La persona en el derecho', 'El acto jurídico', 'Repaso para el examen parcial', 'Las obligaciones', 'Los contratos', 'Derecho de familia', 'Nociones de derecho penal', 'Derecho laboral', 'Derecho administrativo', 'El proceso judicial', 'Repaso final'],
        'BIO-26-01' => ['La biología como ciencia', 'Biomoléculas', 'La célula', 'Membrana celular y transporte', 'Metabolismo celular', 'Fotosíntesis', 'Respiración celular', 'Repaso para el examen parcial', 'Ciclo celular y mitosis', 'Meiosis', 'Genética mendeliana', 'ADN y síntesis de proteínas', 'Evolución', 'Biodiversidad', 'Ecología y ecosistemas', 'Repaso final'],
        'ECO-26-01' => ['El problema económico y la escasez', 'Oferta y demanda', 'Equilibrio de mercado', 'Elasticidad', 'Teoría del consumidor', 'Utilidad y curvas de indiferencia', 'Teoría de la producción', 'Repaso para el examen parcial', 'Costos de producción', 'Competencia perfecta', 'Monopolio', 'Competencia monopolística', 'Oligopolio', 'Mercados de factores', 'Fallas de mercado', 'Repaso final'],
        'QUI-26-01' => ['La materia y sus propiedades', 'Estructura atómica', 'Configuración electrónica', 'La tabla periódica', 'Enlace químico', 'Nomenclatura inorgánica', 'Reacciones químicas', 'Repaso para el examen parcial', 'Estequiometría', 'Gases', 'Soluciones', 'Equilibrio químico', 'Ácidos y bases', 'Electroquímica', 'Química orgánica básica', 'Repaso final'],
    ];

    /** @var array<string, list<array{0: string, 1: list<string>}>> 8 preguntas por curso; la opción correcta es la primera (se rota al sembrar). */
    protected const PREGUNTAS = [
        'EST-26-01' => [
            ['¿Qué tipo de variable es «número de hijos»?', ['Cuantitativa discreta', 'Cuantitativa continua', 'Cualitativa nominal', 'Cualitativa ordinal']],
            ['El nivel educativo (primaria, secundaria, superior) se mide en escala:', ['Ordinal', 'Nominal', 'De intervalo', 'De razón']],
            ['La frecuencia relativa de una clase se obtiene:', ['Dividiendo su frecuencia absoluta entre el total de datos', 'Sumando todas las frecuencias absolutas', 'Multiplicando la frecuencia por la amplitud', 'Restando la frecuencia acumulada anterior']],
            ['¿Qué gráfico conviene para una variable continua agrupada en intervalos?', ['Histograma', 'Gráfico de sectores', 'Pictograma', 'Diagrama de Venn']],
            ['La mediana de 3, 7, 8, 12 y 15 es:', ['8', '7', '9', '12']],
            ['¿Qué medida es más sensible a los valores extremos?', ['La media aritmética', 'La mediana', 'La moda', 'El primer cuartil']],
            ['El percentil 50 coincide con:', ['La mediana', 'La media', 'La moda', 'El rango']],
            ['El rango intercuartílico se calcula como:', ['Q3 − Q1', 'Q2 − Q1', 'Máximo − mínimo', 'P90 − P10']],
        ],
        'ANA-26-01' => [
            ['La solución de 2x − 3 > 5 es:', ['x > 4', 'x > 1', 'x < 4', 'x > −4']],
            ['|−7| + |3| es igual a:', ['10', '4', '−4', '−10']],
            ['El dominio de f(x) = √(x − 2) es:', ['x ≥ 2', 'x > 0', 'Todos los reales', 'x ≤ 2']],
            ['La gráfica de f(x) + 3, respecto de la de f(x), se desplaza:', ['3 unidades hacia arriba', '3 unidades a la derecha', '3 unidades hacia abajo', '3 unidades a la izquierda']],
            ['El límite de (x² − 4)/(x − 2) cuando x tiende a 2 es:', ['4', '0', '2', 'No existe']],
            ['El límite de 1/x cuando x tiende a 0 por la derecha es:', ['+∞', '0', '1', '−∞']],
            ['Una función es continua en x = a si:', ['Existen f(a) y el límite en a, y ambos coinciden', 'Solo existe f(a)', 'Solo existe el límite en a', 'Su gráfica tiene un salto en a']],
            ['La derivada de f(x) = x³ es:', ['3x²', 'x²', '3x', 'x⁴/4']],
        ],
        'PRO-26-01' => [
            ['Un algoritmo es:', ['Una secuencia finita de pasos para resolver un problema', 'Un lenguaje de programación', 'Un tipo de dato', 'Un error de compilación']],
            ['¿Qué tipo de dato conviene para guardar 3.75?', ['Real (float)', 'Entero', 'Booleano', 'Carácter']],
            ['El resultado de 17 % 5 es:', ['2', '3', '3.4', '12']],
            ['¿Qué estructura se usa para elegir entre dos caminos?', ['if-else', 'for', 'while', 'return']],
            ['¿Cuántas veces se ejecuta el cuerpo de for i in range(0, 5)?', ['5', '4', '6', 'Infinitas']],
            ['Un bucle while se detiene cuando:', ['Su condición es falsa', 'Su condición es verdadera', 'Llega a 100 iteraciones', 'Imprime un valor']],
            ['En C o Java, una función que no devuelve valor se declara como:', ['void', 'int', 'null', 'empty']],
            ['El índice del primer elemento de un arreglo en C, Java o Python es:', ['0', '1', '−1', 'Depende del tamaño']],
        ],
        'BDA-26-01' => [
            ['Un SGBD es:', ['El software que administra la base de datos', 'Una tabla con datos', 'Un lenguaje de consulta', 'Un archivo de texto']],
            ['En el modelo entidad-relación, el rombo representa:', ['Una relación', 'Una entidad', 'Un atributo', 'Una clave']],
            ['La clave primaria de una tabla:', ['Identifica de forma única cada fila', 'Puede repetirse', 'Siempre es de texto', 'Es opcional en el modelo relacional']],
            ['Una tabla está en primera forma normal cuando:', ['Todos sus atributos son atómicos', 'No tiene clave primaria', 'Tiene grupos repetidos', 'Tiene más de diez columnas']],
            ['¿Qué sentencia crea una tabla?', ['CREATE TABLE', 'INSERT INTO', 'SELECT', 'ALTER VIEW']],
            ['¿Qué cláusula filtra filas en un SELECT?', ['WHERE', 'ORDER BY', 'GROUP BY', 'FROM']],
            ['INNER JOIN devuelve:', ['Solo las filas con coincidencia en ambas tablas', 'Todas las filas de la tabla izquierda', 'El producto cartesiano completo', 'Solo las filas sin coincidencia']],
            ['La función COUNT(*) devuelve:', ['El número de filas', 'La suma de una columna', 'El valor máximo', 'El promedio']],
        ],
        'FIS-26-01' => [
            ['La unidad de fuerza en el Sistema Internacional es el:', ['Newton', 'Joule', 'Watt', 'Pascal']],
            ['Un auto parte del reposo y acelera a 2 m/s² durante 5 s. Su velocidad final es:', ['10 m/s', '5 m/s', '7 m/s', '25 m/s']],
            ['En un tiro parabólico sin rozamiento, la componente horizontal de la velocidad:', ['Permanece constante', 'Aumenta', 'Disminuye', 'Se anula en el punto más alto']],
            ['La primera ley de Newton también se llama ley de:', ['Inercia', 'Acción y reacción', 'Gravitación', 'Hooke']],
            ['Sobre un cuerpo de 4 kg actúa una fuerza neta de 20 N. Su aceleración es:', ['5 m/s²', '80 m/s²', '0.2 m/s²', '24 m/s²']],
            ['El trabajo de una fuerza perpendicular al desplazamiento es:', ['Cero', 'Máximo', 'Negativo', 'Igual a la fuerza']],
            ['La energía cinética de un cuerpo de 2 kg que se mueve a 3 m/s es:', ['9 J', '3 J', '6 J', '18 J']],
            ['Si solo actúan fuerzas conservativas, la energía mecánica:', ['Se conserva', 'Aumenta', 'Disminuye', 'Se convierte en masa']],
        ],
        'CON-26-01' => [
            ['La ecuación contable fundamental es:', ['Activo = Pasivo + Patrimonio', 'Activo = Pasivo − Patrimonio', 'Pasivo = Activo + Patrimonio', 'Patrimonio = Activo + Pasivo']],
            ['Una compra de mercadería al crédito aumenta:', ['El activo y el pasivo', 'Solo el patrimonio', 'El activo y el patrimonio', 'El pasivo y los gastos']],
            ['En el Plan Contable General Empresarial, la cuenta 10 es:', ['Efectivo y equivalentes de efectivo', 'Mercaderías', 'Cuentas por pagar comerciales', 'Capital']],
            ['Según la partida doble:', ['Todo cargo tiene un abono por el mismo importe', 'Cada operación se registra dos veces en el diario', 'Los activos siempre se abonan', 'Solo se registran las ventas']],
            ['El libro diario registra las operaciones:', ['En orden cronológico', 'Agrupadas por cuenta', 'Solo al cierre del año', 'Solo las de caja']],
            ['El libro mayor agrupa los movimientos:', ['Por cuenta', 'Por fecha de pago', 'Por cliente', 'Por proveedor']],
            ['En el balance de comprobación, la suma de los débitos debe ser:', ['Igual a la suma de los créditos', 'Mayor que la de los créditos', 'Menor que la de los créditos', 'Igual al patrimonio']],
            ['El saldo deudor de una cuenta de activo indica:', ['Que el recurso existe en la empresa', 'Una pérdida del periodo', 'Una deuda con terceros', 'Un error de registro']],
        ],
        'MKT-26-01' => [
            ['El marketing busca principalmente:', ['Crear valor para el cliente y captar valor a cambio', 'Vender a cualquier precio', 'Reducir el número de clientes', 'Fabricar más productos']],
            ['Un factor del macroentorno es:', ['La inflación', 'Los proveedores', 'Los empleados', 'Los intermediarios']],
            ['La pirámide de Maslow ayuda a explicar:', ['Las necesidades del consumidor', 'Los costos de producción', 'La logística', 'La fijación de precios']],
            ['Una encuesta aplicada a 300 clientes es una fuente de datos:', ['Primaria', 'Secundaria', 'Terciaria', 'Contable']],
            ['Segmentar por edad e ingresos es una segmentación:', ['Demográfica', 'Psicográfica', 'Conductual', 'Geográfica']],
            ['El posicionamiento se refiere a:', ['El lugar que ocupa la marca en la mente del consumidor', 'La ubicación de la tienda', 'Tener el precio más bajo', 'El tamaño del empaque']],
            ['Una propuesta de valor responde a la pregunta:', ['¿Por qué un cliente debería elegirnos?', '¿Cuánto cuesta producir?', '¿Quién dirige la empresa?', '¿Cuántos empleados tenemos?']],
            ['Las 4P del marketing mix son:', ['Producto, precio, plaza y promoción', 'Producto, personas, procesos y precio', 'Precio, publicidad, plaza y prensa', 'Producto, planta, precio y promoción']],
        ],
        'DER-26-01' => [
            ['Una fuente formal del derecho en el Perú es:', ['La ley', 'La opinión de un particular', 'Un rumor difundido', 'Un artículo periodístico']],
            ['Una característica de la norma jurídica es la:', ['Coercibilidad', 'Voluntariedad', 'Informalidad', 'Unilateralidad moral']],
            ['La norma de mayor jerarquía en el Perú es:', ['La Constitución', 'El decreto supremo', 'La ordenanza municipal', 'La resolución ministerial']],
            ['La Constitución Política vigente del Perú es de:', ['1993', '1979', '1933', '2001']],
            ['El derecho a la vida es un derecho:', ['Fundamental', 'Patrimonial', 'Tributario', 'Procesal']],
            ['El concebido es sujeto de derecho:', ['Para todo cuanto le favorece', 'Solo desde los 18 años', 'Solo al nacer con DNI', 'En ningún caso']],
            ['Un elemento esencial del acto jurídico es:', ['La manifestación de voluntad', 'El precio', 'La firma notarial', 'Un testigo']],
            ['Una persona jurídica es:', ['Una organización reconocida como sujeto de derechos', 'Un abogado colegiado', 'Un juez', 'Cualquier mayor de edad']],
        ],
        'BIO-26-01' => [
            ['El método científico comienza con:', ['La observación', 'La conclusión', 'La publicación', 'La ley científica']],
            ['Las proteínas están formadas por:', ['Aminoácidos', 'Nucleótidos', 'Ácidos grasos', 'Monosacáridos']],
            ['El organelo donde ocurre la respiración celular es:', ['La mitocondria', 'El ribosoma', 'El aparato de Golgi', 'La vacuola']],
            ['El paso de agua a través de la membrana se llama:', ['Ósmosis', 'Fagocitosis', 'Exocitosis', 'Transcripción']],
            ['El ATP es:', ['La principal molécula energética de la célula', 'Una enzima digestiva', 'Un tipo de lípido', 'Una hormona']],
            ['La fotosíntesis ocurre en:', ['El cloroplasto', 'El núcleo', 'El lisosoma', 'La pared celular']],
            ['La glucólisis se realiza en:', ['El citoplasma', 'La mitocondria', 'El núcleo', 'El cloroplasto']],
            ['Un producto de la fotosíntesis es:', ['El oxígeno', 'El dióxido de carbono', 'El nitrógeno', 'El metano']],
        ],
        'ECO-26-01' => [
            ['El problema económico fundamental es:', ['La escasez', 'La inflación', 'El desempleo', 'Los impuestos']],
            ['Si sube el precio de un bien (ceteris paribus), su cantidad demandada:', ['Disminuye', 'Aumenta', 'No cambia', 'Se duplica']],
            ['En el equilibrio de mercado:', ['La cantidad ofrecida es igual a la demandada', 'Hay exceso de demanda', 'Hay exceso de oferta', 'El precio es cero']],
            ['La demanda es elástica cuando el valor absoluto de la elasticidad es:', ['Mayor que 1', 'Igual a 0', 'Menor que 1', 'Igual a 1']],
            ['La utilidad marginal es:', ['La satisfacción adicional de consumir una unidad más', 'La utilidad total', 'El precio del bien', 'El ingreso del consumidor']],
            ['Una curva de indiferencia muestra:', ['Combinaciones de bienes que dan la misma utilidad', 'El costo de producir', 'La oferta del mercado', 'El ingreso nacional']],
            ['El producto marginal es:', ['La producción adicional de una unidad más de factor', 'La producción total', 'El costo fijo', 'El precio de venta']],
            ['Un costo fijo es aquel que:', ['No varía con la cantidad producida', 'Aumenta con la producción', 'Solo existe en el largo plazo', 'Siempre es cero']],
        ],
        'QUI-26-01' => [
            ['Una propiedad intensiva de la materia es:', ['La densidad', 'La masa', 'El volumen', 'El peso']],
            ['El número atómico indica el número de:', ['Protones', 'Neutrones', 'Enlaces', 'Isótopos']],
            ['La configuración electrónica del sodio (Z = 11) termina en:', ['3s¹', '3p¹', '2p⁶', '4s¹']],
            ['Los elementos de un mismo grupo tienen:', ['Propiedades químicas parecidas', 'La misma masa', 'El mismo número de neutrones', 'El mismo número atómico']],
            ['El enlace entre Na y Cl en la sal común es:', ['Iónico', 'Covalente apolar', 'Metálico', 'Puente de hidrógeno']],
            ['El nombre de H₂SO₄ es:', ['Ácido sulfúrico', 'Ácido sulfuroso', 'Ácido sulfhídrico', 'Sulfato de sodio']],
            ['En la reacción 2H₂ + O₂ → 2H₂O, los reactivos son:', ['H₂ y O₂', 'Solo H₂O', 'H₂O y O₂', 'Solo H₂']],
            ['La masa molar del agua es aproximadamente:', ['18 g/mol', '16 g/mol', '10 g/mol', '32 g/mol']],
        ],
    ];

    /** @var list<array{0: string, 1: string}> nombres y apellidos de los estudiantes nuevos */
    protected const ESTUDIANTES = [
        ['Valentina', 'Ríos Paredes'], ['Santiago', 'Mendoza Quispe'], ['Camila', 'Torres Huamán'], ['Mateo', 'Flores Castillo'],
        ['Lucía', 'Vargas Rojas'], ['Sebastián', 'Chávez Salazar'], ['Daniela', 'Gutiérrez Medina'], ['Diego', 'Ramos Cárdenas'],
        ['Valeria', 'Herrera Aguilar'], ['Nicolás', 'Castro Espinoza'], ['Mariana', 'Silva Vega'], ['Alejandro', 'Morales Palomino'],
        ['Isabella', 'Ortiz Navarro'], ['Joaquín', 'Reyes Campos'], ['Gabriela', 'Cruz Benites'], ['Andrés', 'Delgado Huanca'],
        ['Sofía', 'Romero Zapata'], ['Emilio', 'Paredes Lozano'], ['Renata', 'Guzmán Arce'], ['Tomás', 'Núñez Ccori'],
        ['Ximena', 'Córdova Ramírez'], ['Rodrigo', 'Salinas Mamani'], ['Antonella', 'Vásquez León'], ['Fernando', 'Cabrera Ruiz'],
        ['Paula', 'Acosta Villanueva'], ['Martín', 'Bravo Cáceres'], ['Carolina', 'Peña Montoya'], ['Bruno', 'Ibáñez Tello'],
        ['Natalia', 'Fuentes Rivas'], ['Adrián', 'Sandoval Poma'], ['Florencia', 'Lara Condori'], ['Gonzalo', 'Espinoza Farfán'],
        ['Julieta', 'Miranda Quiroz'], ['Ignacio', 'Valdez Ochoa'], ['Micaela', 'Rosales Gamarra'], ['Esteban', 'Campos Saavedra'],
        ['Abril', 'Tapia Bustamante'], ['Lorenzo', 'Aguirre Pacheco'], ['Regina', 'Soto Alvarado'], ['Felipe', 'Carrillo Meza'],
    ];

    protected const RETROALIMENTACION = ['Buen trabajo; revisa el último ejercicio.', 'Desarrollo claro y ordenado.', 'Falta justificar algunos pasos.', 'Excelente análisis, sigue así.', 'Cuida las unidades y la notación.', 'Bien planteado; mejora la redacción de las conclusiones.', 'Correcto. Agrega un ejemplo propio la próxima vez.', 'Revisa el procedimiento del segundo ejercicio.'];

    /** Tablas cuyo contenido no debe cambiar (huella antes y después). */
    protected const TABLAS_INTOCABLES = ['roles', 'permissions', 'role_has_permissions', 'migrations', 'configuraciones_plataforma'];

    /** Orden de borrado de --limpiar (hijos antes que padres). */
    protected const ORDEN_LIMPIEZA = ['lecciones_completadas', 'debate_comentarios', 'resultados_quiz', 'asistencias', 'entregas', 'grupo_estudiante', 'tareas', 'quizzes', 'grupos', 'lecciones', 'banco_preguntas', 'rubros_evaluacion', 'esquemas_evaluacion', 'sesiones_clase', 'debates', 'comunicados', 'mensajes', 'inscripciones', 'curso_profesor', 'modulos', 'cursos', 'eventos_calendario', 'users'];

    protected Randomizer $azar;

    protected Carbon $ahora;

    protected User $supervisor;

    protected User $geronimo;

    /** @var array<string, User> */
    protected array $docentes = [];

    /** @var array<int, float> estudiante_id => desvío de su nota respecto de la media */
    protected array $habilidad = [];

    /** @var list<int> */
    protected array $cursosCreados = [];

    /** @var list<int> */
    protected array $usuariosCreados = [];

    /** @var list<int> */
    protected array $eventosCreados = [];

    /** @var array<int, array{lecciones: int, tareas: int, quizzes: int}> modulo_id => lo que se sembró en él */
    protected array $esperadoPorModulo = [];

    /** @var array<int, int> curso_id => semana actual */
    protected array $semanaActual = [];

    /** @var array<int, list<int>> curso_id => estudiantes con asistencia baja (prueba de riesgo) */
    protected array $enRiesgo = [];

    public function handle(ConfiguracionPlataforma $configuracion): int
    {
        if (app()->environment('production')) {
            $this->error('Este comando no se ejecuta con APP_ENV=production.');

            return self::FAILURE;
        }

        $configuracion->aplicarZonaHoraria();
        $aplicar = (bool) $this->option('apply');

        if ($this->option('limpiar')) {
            return $this->limpiar((string) $this->option('limpiar'), $aplicar);
        }

        $cursosExistentes = DB::table('cursos')->count();

        if ($cursosExistentes > 0 && ! $this->option('forzar')) {
            $this->error("Ya hay {$cursosExistentes} cursos en la base: no se siembra nada. Vacía la base primero o usa --forzar.");

            return self::FAILURE;
        }

        // Nada sale de la aplicación durante la siembra.
        config(['mail.default' => 'log']);
        Mail::fake();
        Notification::fake();
        Queue::fake();

        $this->ahora = now();
        $this->prepararUsuarios();

        DB::beginTransaction();

        try {
            $antes = $this->foto();
            $sueltasAntes = ['bitacoras_auditoria' => DB::table('bitacoras_auditoria')->count(), 'notifications' => DB::table('notifications')->count()];

            $this->sembrar();

            $errores = $this->verificar();
            $paginas = $this->option('sin-paginas') ? [] : $this->probarPaginas();
            foreach ($paginas as [$quien, $ruta, $estado]) {
                $estado === 200 || $errores[] = "{$quien} {$ruta} respondió {$estado}";
            }

            $despues = $this->foto();
            $errores = [...$errores, ...$this->compararFotos($antes, $despues)];
            $sueltas = collect($sueltasAntes)->map(fn (int $n, string $tabla) => DB::table($tabla)->count() - $n)->all();

            $this->reportar($antes, $despues, $sueltas, $paginas);

            if ($errores !== []) {
                DB::rollBack();
                $this->error('Verificación fallida; se hizo rollback y no se escribió nada:');
                foreach ($errores as $error) {
                    $this->line("  - {$error}");
                }

                return self::FAILURE;
            }

            $this->info('Verificación OK: sin atrasos, sin pendientes de más de 6 días, sin tareas/quizzes vencidos sin entregar, sin sesiones pasadas sin lista; esquema, roles, permisos, migraciones y usuarios existentes idénticos.');

            if (! $aplicar) {
                DB::rollBack();
                $this->info('SIMULACIÓN: se hizo rollback, la base no cambió y no se escribió ningún archivo. Para sembrar: --apply');

                return self::SUCCESS;
            }

            $carpeta = $this->escribirManifiesto();
            DB::commit();
        } catch (Throwable $error) {
            DB::rollBack();
            throw $error;
        }

        $this->info('APLICADO.');
        $this->line("Manifest: {$carpeta}/manifest.json");
        $this->line("Para limpiar lo sembrado: php artisan app:sembrar-demo-actual --limpiar=\"{$carpeta}\" --apply");
        $this->warn('La base es compartida: avisa al equipo.');

        return self::SUCCESS;
    }

    // ==========================================
    // USUARIOS
    // ==========================================

    protected function prepararUsuarios(): void
    {
        $this->supervisor = User::role(Rol::Supervisor->value)->orderBy('id')->first()
            ?? throw new RuntimeException('No hay ningún supervisor.');

        foreach (self::PROFESORES as $clave => $nombre) {
            $encontrados = User::role(Rol::Profesor->value)->where('name', $nombre)->get();
            $this->exigir($encontrados->count() === 1, "se esperaba exactamente un profesor llamado \"{$nombre}\"; hay {$encontrados->count()}");
            $this->docentes[$clave] = $encontrados->first();
        }

        $this->geronimo = User::role(Rol::Estudiante->value)->where('email', self::CORREO_GERONIMO)->first()
            ?? throw new RuntimeException('No existe el estudiante '.self::CORREO_GERONIMO.'.');
    }

    /**
     * Estudiantes nuevos con los mismos campos que GestorCuentas::crear (código
     * E-26-NNN, DNI único, rol, correo verificado), pero con la contraseña de
     * demostración, sin primer login, sin correo de bienvenida y sin bitácora.
     *
     * @return Collection<int, User>
     */
    protected function crearEstudiantes(Carbon $altaEn): Collection
    {
        $estudiantes = collect();
        $dnis = [];

        foreach (self::ESTUDIANTES as $i => [$nombres, $apellidos]) {
            $primerApellido = Str::before($apellidos, ' ');
            $email = collect([$nombres.$primerApellido, $nombres.str_replace(' ', '', $apellidos)])
                ->map(fn (string $base) => Str::of($base)->ascii()->lower()->replaceMatches('/[^a-z]/', '').'@'.self::DOMINIO)
                ->first(fn (string $correo) => ! User::where('email', $correo)->exists());
            $this->exigir($email !== null, "los correos de {$nombres} {$apellidos} ya existen");

            do {
                $dni = (string) $this->azar->getInt(70000000, 79999999);
            } while (isset($dnis[$dni]) || User::where('dni', $dni)->exists());
            $dnis[$dni] = true;

            $momento = $altaEn->copy()->addMinutes($i * 7);
            $usuario = $this->crear(User::class, [
                'nombres' => $nombres, 'apellidos' => $apellidos, 'dni' => $dni,
                'codigo' => GestorCuentas::sugerirCodigo(Rol::Estudiante->value), 'email' => $email,
                'password' => self::PASSWORD, 'activo' => true, 'primer_login' => false,
                'email_verified_at' => $momento, 'created_at' => $momento, 'updated_at' => $momento,
            ]);
            $usuario->assignRole(Rol::Estudiante->value);
            $this->usuariosCreados[] = $usuario->id;
            $estudiantes->push($usuario);
        }

        foreach ([...$estudiantes, $this->geronimo] as $estudiante) {
            $this->habilidad[$estudiante->id] = $this->azar->getInt(-30, 25) / 10;
        }

        return $estudiantes;
    }

    // ==========================================
    // SIEMBRA
    // ==========================================

    protected function sembrar(): void
    {
        $this->azar = new Randomizer(new Mt19937((int) $this->option('semilla')));
        $lunes = $this->ahora->copy()->startOfWeek();
        $semanaMaxima = max(array_filter(array_column(self::CURSOS, 3)));
        $estudiantes = $this->crearEstudiantes($lunes->copy()->subWeeks($semanaMaxima - 1)->subDays(25)->setTime(10, 0));

        foreach (self::CURSOS as $indice => $datos) {
            $this->cursosCreados[] = $this->crearCurso($datos, $indice, $estudiantes)->id;
        }

        $this->eventosDeProfesores();
    }

    /**
     * @param  array{0: string, 1: string, 2: string, 3: ?int, 4: string, 5: int, 6: list<array{0: int, 1: string, 2: string, 3: ?string}>, 7: list<string>, 8: ?string}  $datos
     * @param  Collection<int, User>  $estudiantes
     */
    protected function crearCurso(array $datos, int $indice, Collection $estudiantes): Curso
    {
        [$codigo, $nombre, $titularClave, $actual, $modalidad, $matriculados, $horario, $opciones, $codocente] = $datos;
        $titular = $this->docentes[$titularClave];
        $lunesActual = $this->ahora->copy()->startOfWeek();
        $inicio = $actual === null ? $lunesActual->copy()->addWeeks(4) : $lunesActual->copy()->subWeeks($actual - 1);
        $conHorario = in_array($modalidad, Curso::MODALIDADES_CON_HORARIO, true);
        $this->exigir(preg_match(Curso::FORMATO_CODIGO, $codigo) === 1 && ! Curso::prefijoPerteneceAOtraMateria($codigo, $nombre), "código inválido o con prefijo de otra materia: {$codigo}");
        $creado = $inicio->copy()->subDays(30)->setTime(9, 0);

        $curso = $this->crear(Curso::class, [
            'nombre' => $nombre, 'codigo' => $codigo, 'profesor_id' => $titular->id, 'estado' => 'borrador',
            'descripcion' => "Curso de {$nombre}: ".mb_strtolower(self::TEMAS[$codigo][0]).', '.mb_strtolower(self::TEMAS[$codigo][1]).' y más, con práctica semanal.',
            'fecha_inicio' => $inicio->toDateString(), 'fecha_fin' => $inicio->copy()->addWeeks(16)->subDay()->toDateString(),
            'semanas_duracion' => 16, 'cupo_maximo' => 40, 'modalidad' => $modalidad,
            'horario_bloques' => $conHorario ? array_map(fn (array $b) => ['dia' => $b[0], 'hora_inicio' => $b[1], 'hora_fin' => $b[2], 'aula' => $b[3]], $horario) : null,
            'usa_asistencia' => $conHorario, 'tamano_maximo_grupo' => 4, 'tiene_practicas_campo' => in_array('practicas', $opciones, true),
            'created_at' => $creado, 'updated_at' => $creado,
        ]);
        $curso->generarSesionesDesdeHorario();

        if ($codocente) {
            DB::table('curso_profesor')->insert(['curso_id' => $curso->id, 'profesor_id' => $this->docentes[$codocente]->id, 'created_at' => $creado, 'updated_at' => $creado]);
        }

        $rubros = $this->esquema($curso, $creado);

        if ($actual === null) {
            $this->crear(Modulo::class, ['curso_id' => $curso->id, 'semana' => 1, 'titulo' => 'Semana 1', 'materiales' => [], 'estado_contenido' => 'borrador', 'created_at' => $creado, 'updated_at' => $creado]);

            return $curso;
        }

        $this->semanaActual[$curso->id] = $actual;
        $inscritos = $this->matricular($curso, $estudiantes, $matriculados, in_array('geronimo', $opciones, true));
        $modulos = $this->semanas($curso, $actual);
        $this->exigir($curso->modulos()->exists() && $curso->inscripcionesActivas()->exists(), "{$codigo} no cumple la regla para publicar");
        $publicado = $inicio->copy()->subDays(3)->setTime(18, 0);
        $curso->forceFill(['estado' => 'publicado', 'updated_at' => $publicado])->save();

        $grupos = in_array('grupos', $opciones, true) ? $this->grupos($curso, $inscritos) : collect();
        $actividades = $this->tareas($curso, $modulos, $actual, $inscritos, $grupos, $opciones);
        $actividades = [...$actividades, ...$this->quizzes($curso, $modulos, $actual, $inscritos, $titular, in_array('quiz_futuro', $opciones, true))];

        if ($curso->tiene_practicas_campo) {
            $this->practicasDeCampo($curso, $rubros, $actual, $inscritos);
        }

        $this->asignarEvaluaciones($curso, $actividades);
        $this->asistencia($curso, $inscritos, $titular);
        $this->leccionesCompletadas($curso, $inscritos);
        $this->comunicados($curso, $titular, $actual);
        $this->debates($curso, $titular, $inscritos, $actual, $opciones);
        $this->mensajes($curso, $titular, $inscritos);

        return $curso;
    }

    /**
     * Esquema de 4 evaluaciones que suma 100 %; con prácticas de campo, 30 %
     * repartido entre ellas (reparto sugerido de PracticasEnEvaluaciones).
     *
     * @return Collection<int, RubroEvaluacion>
     */
    protected function esquema(Curso $curso, Carbon $momento): Collection
    {
        $rubros = [['Evaluación continua 1', 4, 20], ['Examen parcial', 8, 25], ['Evaluación continua 2', 12, 20], ['Examen final', 16, 35]];
        $this->exigir(app(CalculadoraNotas::class)->pesosSumanCien(array_column($rubros, 2)), "pesos inválidos en {$curso->codigo}");

        $esquema = $this->crear(EsquemaEvaluacion::class, ['curso_id' => $curso->id, 'peso_practicas_campo' => $curso->tiene_practicas_campo ? 30 : null, 'created_at' => $momento, 'updated_at' => $momento]);

        foreach ($rubros as $orden => [$nombre, $semana, $peso]) {
            $this->crear(RubroEvaluacion::class, ['esquema_id' => $esquema->id, 'nombre' => $nombre, 'semana' => $semana, 'peso' => $peso, 'orden' => $orden + 1, 'created_at' => $momento, 'updated_at' => $momento]);
        }

        $guardados = $esquema->rubros()->orderBy('orden')->get();

        if ($curso->tiene_practicas_campo) {
            foreach (app(PracticasEnEvaluaciones::class)->repartoSugerido($guardados, 16, 30.0) as $rubroId => $tramo) {
                $guardados->firstWhere('id', $rubroId)->forceFill(['practica_desde' => $tramo['desde'], 'practica_hasta' => $tramo['hasta'], 'peso_practicas' => $tramo['peso'], 'modo_calculo' => ModoCalculo::Promedio])->save();
            }
        }

        return $guardados->fresh();
    }

    /**
     * Matrícula activa (como GestorMatriculas::matricularManual) con fecha
     * anterior al inicio del curso; sin notificación ni bitácora.
     *
     * @param  Collection<int, User>  $estudiantes
     * @return Collection<int, User>
     */
    protected function matricular(Curso $curso, Collection $estudiantes, int $cantidad, bool $conGeronimo): Collection
    {
        $elegidos = collect($this->azar->shuffleArray($estudiantes->all()))->take($conGeronimo ? $cantidad - 1 : $cantidad);
        $inscritos = ($conGeronimo ? $elegidos->push($this->geronimo) : $elegidos)->sortBy('id')->values();
        $filas = $inscritos->map(fn (User $estudiante) => [
            'curso_id' => $curso->id, 'estudiante_id' => $estudiante->id, 'origen' => 'manual', 'estado' => 'activo', 'activo_local' => true,
            'fecha_inscripcion' => $curso->fecha_inicio->copy()->subDays($this->azar->getInt(2, 12))->setTime($this->azar->getInt(9, 19), $this->azar->getInt(0, 59))->format('Y-m-d H:i:s'),
        ])->all();
        Inscripcion::insert($filas);

        $candidatos = $inscritos->reject(fn (User $u) => $u->id === $this->geronimo->id)->values()->all();
        $this->enRiesgo[$curso->id] = collect($this->azar->pickArrayKeys($candidatos, 2))->map(fn (int $i) => $candidatos[$i]->id)->all();

        return $inscritos;
    }

    /**
     * Semanas 1..actual publicadas y la siguiente programada, con las 4
     * secciones; la grabación solo en semanas ya dictadas de cursos con clases.
     *
     * @return array<int, Modulo> semana => módulo
     */
    protected function semanas(Curso $curso, int $actual): array
    {
        $temas = self::TEMAS[$curso->codigo];
        $sesiones = $curso->sesionesClase()->get(['fecha', 'duracion_minutos']);
        $modulos = [];
        $filas = [];

        foreach (range(1, min($actual + 1, 16)) as $numero) {
            [$lunes] = $curso->rangoSemana($numero);
            $programado = $numero > $actual;
            $creado = $lunes->copy()->subDays(3)->setTime(16, 0)->max($curso->created_at);
            $tema = $temas[$numero - 1];
            $modulo = $this->crear(Modulo::class, [
                'curso_id' => $curso->id, 'semana' => $numero, 'titulo' => "Semana {$numero}: {$tema}", 'materiales' => [],
                'estado_contenido' => $programado ? 'programado' : 'publicado', 'publicar_en' => $programado ? $lunes->copy()->setTime(7, 0) : null,
                'created_at' => $creado, 'updated_at' => $creado,
            ]);
            $modulos[$numero] = $modulo;

            $deLaSemana = $sesiones->filter(fn ($s) => $s->fecha->between($lunes, $lunes->copy()->endOfWeek()));
            $dictada = $deLaSemana->isNotEmpty() && $deLaSemana->every(fn ($s) => $s->fecha->copy()->addMinutes($s->duracion_minutos)->lt($this->ahora));
            $lecciones = $this->contenidoDeSemana($curso, $numero, $tema, $numero > 1 ? $temas[$numero - 2] : null, $dictada ? $deLaSemana->sortBy('fecha')->last()->fecha : null);

            foreach ($lecciones as $orden => $leccion) {
                $filas[] = $leccion + [
                    'modulo_id' => $modulo->id, 'orden' => $orden + 1, 'condicion_completitud' => 'marcar',
                    'estado' => $programado ? 'programado' : 'publicado', 'fecha_publicacion' => $programado ? $lunes->copy()->setTime(7, 0)->format('Y-m-d H:i:s') : null,
                    'created_at' => $creado->format('Y-m-d H:i:s'), 'updated_at' => $creado->format('Y-m-d H:i:s'),
                ];
            }

            $this->esperadoPorModulo[$modulo->id] = ['lecciones' => count($lecciones), 'tareas' => 0, 'quizzes' => 0];
        }

        foreach (array_chunk($filas, 100) as $tanda) {
            Leccion::insert($tanda);
        }

        return $modulos;
    }

    /**
     * Mensaje de inicio, 2-4 recursos, 1-2 actividades y, si la semana ya se dictó, su grabación.
     *
     * @return list<array<string, ?string>>
     */
    protected function contenidoDeSemana(Curso $curso, int $numero, string $tema, ?string $anterior, ?Carbon $ultimaClase): array
    {
        $busqueda = fn (string $texto) => urlencode("{$texto} {$curso->nombre}");
        $vacia = ['video_url' => null, 'material_url' => null, 'material_nombre' => null];
        $repaso = $anterior ? ' Repasa lo visto sobre '.mb_strtolower($anterior).', porque lo usaremos.' : ' Revisa el sílabo y el cronograma del curso.';
        $mensajes = [
            "¡Hola a todos! Esta semana trabajamos {$tema}.\nObjetivo: comprender sus conceptos clave y aplicarlos en ejercicios guiados.\nAntes de la clase revisa los recursos de aprendizaje.{$repaso}",
            "Bienvenidos a la semana {$numero}: {$tema}.\nAl terminar podrás explicar el tema con tus palabras y resolver problemas tipo.{$repaso}\nTraigan sus dudas a la clase.",
            "Semana {$numero}: {$tema}.\nEmpezaremos con un caso práctico y luego formalizaremos los conceptos.\nLee la guía de estudio y mira el video antes de la sesión.{$repaso}",
        ];
        $recursos = [
            ['titulo' => "Presentación de la clase: {$tema}", 'descripcion' => 'Diapositivas con los conceptos y ejemplos desarrollados en clase.', 'material_url' => 'https://www.google.com/search?q='.$busqueda("{$tema} diapositivas"), 'material_nombre' => "Semana {$numero} - {$tema}.pptx", 'video_url' => null],
            ['titulo' => "Video explicativo: {$tema}", 'descripcion' => 'Explicación paso a paso con ejemplos resueltos (15-20 min).', 'video_url' => 'https://www.youtube.com/results?search_query='.$busqueda($tema), 'material_url' => null, 'material_nombre' => null],
            ['titulo' => "Lectura recomendada: {$tema}", 'descripcion' => 'Lectura introductoria; identifica las ideas principales y anota tus dudas.', 'material_url' => 'https://es.wikipedia.org/w/index.php?search='.urlencode($tema), 'material_nombre' => null, 'video_url' => null],
            ['titulo' => "Guía de estudio: semana {$numero}", 'descripcion' => "Resumen del tema {$tema} con ejercicios resueltos y propuestos.", 'material_url' => 'https://www.google.com/search?q='.$busqueda("{$tema} guía de estudio pdf"), 'material_nombre' => "Guía de estudio - Semana {$numero}.pdf", 'video_url' => null],
        ];
        $practico = in_array($curso->codigo, ['PRO-26-01', 'BDA-26-01'], true) ? 'Laboratorio guiado' : 'Caso práctico';
        $actividades = [
            ['titulo' => "Ejercicios propuestos: {$tema}", 'descripcion' => 'Resuelve los ejercicios de la guía (sin nota). El solucionario se publica el viernes.'],
            ['titulo' => "{$practico}: {$tema}", 'descripcion' => "Aplica {$tema} a una situación real y compara tu resultado con el de un compañero."],
        ];

        $lecciones = [['tipo_recurso' => 'mensaje_inicio', 'titulo' => "Bienvenida a la semana {$numero}", 'descripcion' => $mensajes[$this->azar->getInt(0, 2)]] + $vacia];

        foreach (array_slice($recursos, 0, $this->azar->getInt(2, 4)) as $recurso) {
            $lecciones[] = ['tipo_recurso' => 'recursos'] + $recurso;
        }

        foreach (array_slice($actividades, 0, $this->azar->getInt(1, 2)) as $actividad) {
            $lecciones[] = ['tipo_recurso' => 'actividades'] + $actividad + $vacia;
        }

        if ($ultimaClase) {
            $lecciones[] = ['tipo_recurso' => 'grabacion', 'titulo' => "Grabación de la clase - Semana {$numero}", 'descripcion' => "Sesión del {$ultimaClase->format('d/m/Y')} sobre {$tema}.",
                'video_url' => 'https://drive.google.com/file/d/'.substr(md5("{$curso->codigo}-{$numero}"), 0, 24).'/view', 'material_url' => null, 'material_nombre' => null];
        }

        return $lecciones;
    }

    /**
     * Grupos de 3-4 integrantes con todos los inscritos.
     *
     * @param  Collection<int, User>  $inscritos
     * @return Collection<int, array{grupo: Grupo, miembros: Collection<int, User>}>
     */
    protected function grupos(Curso $curso, Collection $inscritos): Collection
    {
        $cantidad = (int) ceil($inscritos->count() / 4);
        $base = intdiv($inscritos->count(), $cantidad);
        $extra = $inscritos->count() % $cantidad;
        $momento = $curso->fecha_inicio->copy()->addDays(3)->setTime(11, 0);
        $grupos = collect();
        $desde = 0;

        foreach (range(1, $cantidad) as $n) {
            $tamano = $base + ($n <= $extra ? 1 : 0);
            $miembros = $inscritos->slice($desde, $tamano)->values();
            $desde += $tamano;
            $grupo = $this->crear(Grupo::class, ['curso_id' => $curso->id, 'nombre' => "Grupo {$n}", 'created_at' => $momento, 'updated_at' => $momento]);
            DB::table('grupo_estudiante')->insert($miembros->map(fn (User $u) => ['grupo_id' => $grupo->id, 'estudiante_id' => $u->id, 'created_at' => $momento, 'updated_at' => $momento])->all());
            $grupos->push(['grupo' => $grupo, 'miembros' => $miembros]);
        }

        return $grupos;
    }

    /**
     * Una tarea por semana (algunas grupales). Las vencidas: todos entregan a
     * tiempo; solo la de la semana actual (venció hace 1-6 días) deja algunas
     * sin calificar. La de la semana siguiente y la "temprana" vencen en 2-14 días.
     *
     * @param  array<int, Modulo>  $modulos
     * @param  Collection<int, User>  $inscritos
     * @param  Collection<int, array{grupo: Grupo, miembros: Collection<int, User>}>  $grupos
     * @param  list<string>  $opciones
     * @return list<Tarea>
     */
    protected function tareas(Curso $curso, array $modulos, int $actual, Collection $inscritos, Collection $grupos, array $opciones): array
    {
        $temas = self::TEMAS[$curso->codigo];
        $tareas = [];
        $plazoActual = 2 + array_search($curso->codigo, array_column(self::CURSOS, 0), true) % 4;

        foreach ($modulos as $numero => $modulo) {
            [$lunes] = $curso->rangoSemana($numero);
            $tema = $temas[$numero - 1];
            $grupal = $grupos->isNotEmpty() && $numero % 3 === 0;
            $examen = str_starts_with($tema, 'Repaso para el examen');
            $titulo = match (true) {
                $examen => 'Simulacro de examen parcial',
                $grupal => "Trabajo grupal: {$tema}",
                default => ['Práctica calificada', 'Ejercicios resueltos', 'Informe', 'Control de lectura'][$numero % 4].": {$tema}",
            };
            $cierre = $numero > $actual
                ? $lunes->copy()->addDays(3 + $numero % 2)->setTime(23, 59)
                : $lunes->copy()->addDays($numero === $actual ? $plazoActual : 3 + $numero % 3)->setTime(23, 59);
            $cierre = $numero === $actual ? $cierre->min($this->ahora->copy()->subHours(2)) : $cierre;

            $tarea = $this->nuevaTarea($curso, $modulo, $titulo, $grupal, $lunes->copy()->setTime(8, 0), $cierre, $tema);
            $tareas[] = $tarea;

            if ($numero <= $actual) {
                $unidades = $grupal ? $grupos->count() : $inscritos->count();
                $this->entregas($tarea, $inscritos, $grupos, $numero === $actual ? $this->azar->getInt(5, min(9, $unidades)) : 0);
            }
        }

        if (in_array('temprana', $opciones, true)) {
            $tema = $temas[$actual - 1];
            [$lunes] = $curso->rangoSemana($actual);
            $tarea = $this->nuevaTarea($curso, $modulos[$actual], "Avance de proyecto: {$tema}", false, $lunes->copy()->setTime(8, 0), $this->ahora->copy()->addDays($this->azar->getInt(3, 6))->setTime(23, 59), $tema);
            $tareas[] = $tarea;
            $this->entregasTempranas($tarea, $inscritos->take(-$this->azar->getInt(1, 2)));
        }

        return $tareas;
    }

    protected function nuevaTarea(Curso $curso, Modulo $modulo, string $titulo, bool $grupal, Carbon $apertura, Carbon $cierre, string $tema, ?int $practica = null): Tarea
    {
        $creada = $apertura->copy()->subDays(2)->max($curso->created_at);
        $tarea = $this->crear(Tarea::class, [
            'curso_id' => $curso->id, 'modulo_id' => $modulo->id, 'titulo' => $titulo,
            'descripcion' => $grupal ? "En grupo, desarrollen un informe breve aplicando {$tema} a un caso real." : "Resuelve los ejercicios sobre {$tema} y sube tu desarrollo.",
            'instrucciones' => $grupal ? 'Un integrante sube el enlace al documento compartido; todos figuran como autores.' : 'Entrega un solo PDF con tu nombre completo en la primera página y el procedimiento completo.',
            'fecha_inicio' => $apertura, 'fecha_entrega' => $cierre, 'peso_nota' => 1, 'intentos_permitidos' => 1,
            'tipo_entrega' => $grupal ? 'grupal' : 'individual', 'visible' => true, 'estado_contenido' => 'publicado', 'practica' => $practica,
            'created_at' => $creada, 'updated_at' => $creada,
        ]);
        $this->esperadoPorModulo[$modulo->id]['tareas'] = ($this->esperadoPorModulo[$modulo->id]['tareas'] ?? 0) + 1;

        return $tarea;
    }

    /**
     * Todos entregan a tiempo (en las horas previas al cierre). Las primeras
     * $pendientes unidades (al azar) quedan sin calificar y entregadas en los
     * últimos 6 días; el resto, calificadas.
     *
     * @param  Collection<int, User>  $inscritos
     * @param  Collection<int, array{grupo: Grupo, miembros: Collection<int, User>}>  $grupos
     */
    protected function entregas(Tarea $tarea, Collection $inscritos, Collection $grupos, int $pendientes): void
    {
        $unidades = $tarea->esGrupal()
            ? $grupos->map(fn (array $g) => ['grupo' => $g['grupo']->id, 'miembros' => $g['miembros']])->values()->all()
            : $inscritos->map(fn (User $u) => ['grupo' => null, 'miembros' => collect([$u])])->values()->all();
        $sinCalificar = array_flip($pendientes > 0 ? (array) $this->azar->pickArrayKeys($unidades, $pendientes) : []);
        $filas = [];

        foreach ($unidades as $i => $unidad) {
            $pendiente = isset($sinCalificar[$i]);
            $envio = $this->momentoEntrega($tarea, $pendiente);
            $revision = $envio->copy()->addHours(2)->max($tarea->fecha_entrega->copy()->addHours($this->azar->getInt(4, 96)))->min($this->ahora->copy()->subMinutes(15));
            $nota = $pendiente ? null : $this->nota($unidad['miembros']->pluck('id')->all());
            $feedback = ! $pendiente && $this->azar->getInt(1, 100) <= 45 ? self::RETROALIMENTACION[$this->azar->getInt(0, count(self::RETROALIMENTACION) - 1)] : null;

            foreach ($unidad['miembros'] as $miembro) {
                $filas[] = [
                    'tarea_id' => $tarea->id, 'estudiante_id' => $miembro->id, 'grupo_id' => $unidad['grupo'],
                    'respuesta_texto' => $unidad['grupo'] ? 'Informe del grupo en el documento compartido.' : 'Adjunto el desarrollo completo de los ejercicios.',
                    'enlace' => $unidad['grupo'] ? 'https://docs.google.com/document/d/'.substr(md5("{$tarea->id}-{$unidad['grupo']}"), 0, 24).'/edit' : null,
                    'estado' => $pendiente ? 'entregado' : 'calificado', 'nota' => $nota, 'feedback' => $feedback, 'intentos_usados' => 1,
                    'created_at' => $envio->format('Y-m-d H:i:s'), 'updated_at' => ($pendiente ? $envio : $revision)->format('Y-m-d H:i:s'),
                ];
            }
        }

        foreach (array_chunk($filas, 200) as $tanda) {
            DB::table('entregas')->insert($tanda);
        }
    }

    /**
     * Hora de entrega realista (0-2 días antes del cierre, sobre todo de
     * 18:00 a 23:59), nunca después del cierre ni antes de la apertura; las
     * pendientes, dentro de los últimos 6 días.
     */
    protected function momentoEntrega(Tarea $tarea, bool $pendiente): Carbon
    {
        $limite = $tarea->fecha_entrega->copy()->min($this->ahora->copy()->subMinutes(30));
        $desde = $tarea->fecha_inicio->copy()->addHour();
        $desde = $pendiente ? $desde->max($this->ahora->copy()->subDays(6)->addHours(2)) : $desde;
        $dia = $limite->copy()->subDays($this->azar->getInt(0, 2));
        $momento = $this->azar->getInt(1, 100) <= 70
            ? $dia->setTime($this->azar->getInt(18, 23), $this->azar->getInt(0, 59))
            : $dia->setTime($this->azar->getInt(10, 17), $this->azar->getInt(0, 59));

        return $momento->max($desde)->min($limite)->startOfMinute()->max($desde->copy()->ceilMinute());
    }

    /** @param  Collection<int, User>  $estudiantes */
    protected function entregasTempranas(Tarea $tarea, Collection $estudiantes): void
    {
        foreach ($estudiantes as $estudiante) {
            $envio = $this->ahora->copy()->subHours($this->azar->getInt(3, 30))->max($tarea->fecha_inicio->copy()->addHour())->startOfMinute();
            DB::table('entregas')->insert(['tarea_id' => $tarea->id, 'estudiante_id' => $estudiante->id, 'respuesta_texto' => 'Adjunto el avance del proyecto.',
                'estado' => 'entregado', 'intentos_usados' => 1, 'created_at' => $envio->format('Y-m-d H:i:s'), 'updated_at' => $envio->format('Y-m-d H:i:s')]);
        }
    }

    /**
     * Nota 0-20 alrededor de 16 (80 %) según la habilidad del estudiante (promedio en grupo).
     *
     * @param  list<int>  $estudianteIds
     */
    protected function nota(array $estudianteIds): float
    {
        $habilidad = array_sum(array_map(fn (int $id) => $this->habilidad[$id] ?? 0, $estudianteIds)) / count($estudianteIds);

        return max(8.0, min(20.0, round((16.3 + $habilidad + $this->azar->getInt(-15, 15) / 10) * 2) / 2));
    }

    /**
     * Banco de 8 preguntas; un quiz cerrado (semana actual - 2) que rindieron
     * todos y, si corresponde, uno programado para la semana siguiente.
     *
     * @param  array<int, Modulo>  $modulos
     * @param  Collection<int, User>  $inscritos
     * @return list<Quiz>
     */
    protected function quizzes(Curso $curso, array $modulos, int $actual, Collection $inscritos, User $titular, bool $conFuturo): array
    {
        $banco = collect(self::PREGUNTAS[$curso->codigo])->map(function (array $pregunta, int $i) use ($curso, $titular) {
            [$enunciado, $opciones] = $pregunta;
            $giro = ($i + crc32($curso->codigo)) % 4;
            $rotadas = [...array_slice($opciones, $giro), ...array_slice($opciones, 0, $giro)];
            $momento = $curso->created_at->copy()->addDays(2);

            return $this->crear(BancoPregunta::class, [
                'curso_id' => $curso->id, 'tema' => self::TEMAS[$curso->codigo][min($i, 7)], 'pregunta' => $enunciado, 'tipo' => 'opcion_unica',
                'opciones' => $rotadas, 'respuestas_correctas' => [(4 - $giro) % 4], 'dificultad' => $i < 4 ? 'facil' : 'media', 'puntaje' => 4,
                'unidad' => $i < 4 ? 'Unidad 1' : 'Unidad 2', 'creado_por' => $titular->id, 'created_at' => $momento, 'updated_at' => $momento,
            ]);
        });

        $quizzes = [];
        $cerrado = $actual - 2;
        [$lunes] = $curso->rangoSemana($cerrado);
        $quiz = $this->nuevoQuiz($curso, $modulos[$cerrado], "Quiz 1: semanas 1 a {$cerrado}", $banco->slice(0, 5), $lunes->copy()->setTime(8, 0), $lunes->copy()->addDays(6)->setTime(23, 59));
        $this->resultados($quiz, $inscritos);
        $quizzes[] = $quiz;

        if ($conFuturo && $actual + 1 <= 16) {
            [$lunes] = $curso->rangoSemana($actual + 1);
            $quizzes[] = $this->nuevoQuiz($curso, $modulos[$actual + 1], 'Quiz 2: semanas '.($cerrado + 1).' a '.($actual + 1), $banco->slice(3, 5), $lunes->copy()->addDays(2)->setTime(8, 0), $lunes->copy()->addDays(4)->setTime(23, 59));
        }

        return $quizzes;
    }

    /** @param  Collection<int, BancoPregunta>  $preguntas */
    protected function nuevoQuiz(Curso $curso, Modulo $modulo, string $tema, Collection $preguntas, Carbon $inicio, Carbon $limite): Quiz
    {
        $creado = $inicio->copy()->subDays(3)->max($curso->created_at);
        $quiz = $this->crear(Quiz::class, [
            'curso_id' => $curso->id, 'modulo_id' => $modulo->id, 'tema' => $tema,
            'preguntas' => $preguntas->map(fn (BancoPregunta $p) => $p->aPreguntaDeQuiz())->values()->all(),
            'configuracion' => ['tiempo_limite_min' => 20, 'intentos' => 1, 'aleatorizar' => false, 'mostrar_retroalimentacion' => true, 'puntaje_total' => 20],
            'fecha_inicio' => $inicio, 'fecha_limite' => $limite, 'visible' => true, 'estado_contenido' => 'publicado',
            'created_at' => $creado, 'updated_at' => $creado,
        ]);
        $this->esperadoPorModulo[$modulo->id]['quizzes'] = ($this->esperadoPorModulo[$modulo->id]['quizzes'] ?? 0) + 1;

        return $quiz;
    }

    /**
     * Un intento por estudiante, autocalificado con Quiz::calcularPuntaje.
     *
     * @param  Collection<int, User>  $inscritos
     */
    protected function resultados(Quiz $quiz, Collection $inscritos): void
    {
        $filas = [];
        $ventana = (int) $quiz->fecha_inicio->diffInMinutes($quiz->fecha_limite->copy()->min($this->ahora));

        foreach ($inscritos as $estudiante) {
            $acierto = (int) round(100 * max(0.45, min(0.97, 0.8 + ($this->habilidad[$estudiante->id] ?? 0) * 0.05)));
            $respuestas = array_map(function (array $pregunta) use ($acierto) {
                $correcta = $pregunta['respuesta_correcta'];

                return $this->azar->getInt(1, 100) <= $acierto ? $correcta : ($correcta + $this->azar->getInt(1, 3)) % 4;
            }, $quiz->preguntas);
            $momento = $quiz->fecha_inicio->copy()->addMinutes($this->azar->getInt(60, max(61, $ventana - 60)));
            $filas[] = ['quiz_id' => $quiz->id, 'estudiante_id' => $estudiante->id, 'intento' => 1, 'respuestas' => json_encode($respuestas),
                'puntaje' => $quiz->calcularPuntaje($respuestas), 'tiempo_usado_seg' => $this->azar->getInt(300, 1150),
                'created_at' => $momento->format('Y-m-d H:i:s'), 'updated_at' => $momento->format('Y-m-d H:i:s')];
        }

        DB::table('resultados_quiz')->insert($filas);
    }

    /**
     * Una práctica de campo por semana (1..actual) en la sección sin semana;
     * todas entregadas a tiempo, la de esta semana con algunas sin calificar.
     * Se vinculan a su tramo con PracticasEnEvaluaciones::sincronizar.
     *
     * @param  Collection<int, RubroEvaluacion>  $rubros
     * @param  Collection<int, User>  $inscritos
     */
    protected function practicasDeCampo(Curso $curso, Collection $rubros, int $actual, Collection $inscritos): void
    {
        $seccion = $this->crear(Modulo::class, ['curso_id' => $curso->id, 'tipo_seccion' => Modulo::TIPO_PRACTICAS, 'titulo' => 'Prácticas de campo', 'materiales' => [], 'created_at' => $curso->created_at, 'updated_at' => $curso->created_at]);
        $this->esperadoPorModulo[$seccion->id] = ['lecciones' => 0, 'tareas' => 0, 'quizzes' => 0];

        foreach (range(1, $actual) as $numero) {
            [$lunes] = $curso->rangoSemana($numero);
            $practica = $this->nuevaTarea($curso, $seccion, Tarea::etiquetaPractica($numero), false, $lunes->copy()->setTime(8, 0), $lunes->copy()->addDays(5)->setTime(23, 59)->min($this->ahora->copy()->subHours(2)), self::TEMAS[$curso->codigo][$numero - 1], $numero);
            $practica->forceFill(['descripcion' => 'Informe de la práctica de laboratorio de la semana: '.self::TEMAS[$curso->codigo][$numero - 1].'.'])->saveQuietly();
            $this->entregas($practica, $inscritos, collect(), $numero === $actual ? $this->azar->getInt(3, 5) : 0);
        }

        app(PracticasEnEvaluaciones::class)->sincronizar($curso->fresh());
    }

    /**
     * Cada actividad cuenta para la evaluación sugerida (la primera desde su
     * semana), con partes iguales del % que dejan las prácticas de campo, en
     * modo Promedio: el mismo resultado que AsignadorEvaluacion, sin bitácora.
     *
     * @param  list<Tarea|Quiz>  $actividades
     */
    protected function asignarEvaluaciones(Curso $curso, array $actividades): void
    {
        $rubros = $curso->esquemaEvaluacion()->first()->rubros()->orderBy('orden')->get();
        $asignador = app(AsignadorEvaluacion::class);
        $porRubro = collect($actividades)->groupBy(fn (Tarea|Quiz $a) => $asignador->sugerida($rubros, Modulo::find($a->modulo_id)?->semana)->id);

        foreach ($porRubro as $rubroId => $deLaEvaluacion) {
            $rubro = $rubros->firstWhere('id', $rubroId);
            $pesos = $this->partesIguales($rubro->pesoParaActividades(), $deLaEvaluacion->count());
            $rubro->forceFill(['modo_calculo' => ModoCalculo::Promedio])->save();

            foreach ($deLaEvaluacion->values() as $i => $actividad) {
                DB::table($actividad instanceof Tarea ? 'rubro_tarea' : 'rubro_quiz')->insert(['rubro_id' => $rubro->id, ($actividad instanceof Tarea ? 'tarea_id' : 'quiz_id') => $actividad->id, 'peso' => $pesos[$i]]);
            }
        }
    }

    /** @return list<float> partes iguales que suman exactamente $total (el redondeo va a la última) */
    protected function partesIguales(float $total, int $partes): array
    {
        $parte = floor($total / $partes * 100) / 100;

        return [...array_fill(0, $partes - 1, $parte), round($total - $parte * ($partes - 1), 2)];
    }

    /**
     * Lista tomada en todas las sesiones ya dictadas: ~93 % de asistencia y
     * dos estudiantes por curso con asistencia baja (bajo el 70 %: en riesgo).
     *
     * @param  Collection<int, User>  $inscritos
     */
    protected function asistencia(Curso $curso, Collection $inscritos, User $titular): void
    {
        $filas = [];

        foreach ($curso->sesionesClase()->where('fecha', '<', $this->ahora)->orderBy('fecha')->get() as $sesion) {
            $registro = $sesion->fecha->copy()->addMinutes($sesion->duracion_minutos)->min($this->ahora)->format('Y-m-d H:i:s');

            foreach ($inscritos as $estudiante) {
                $tiro = $this->azar->getInt(1, 100);
                $estado = in_array($estudiante->id, $this->enRiesgo[$curso->id], true)
                    ? match (true) {
                        $tiro <= 50 => 'presente', $tiro <= 58 => 'tardanza', $tiro <= 66 => 'justificado', default => 'ausente'
                    }
                : match (true) {
                    $tiro <= 88 => 'presente', $tiro <= 93 => 'tardanza', $tiro <= 96 => 'justificado', default => 'ausente'
                };
                $filas[] = ['sesion_id' => $sesion->id, 'estudiante_id' => $estudiante->id, 'estado' => $estado, 'observacion' => $estado === 'justificado' ? 'Presentó justificación médica.' : null,
                    'registrado_por' => $titular->id, 'created_at' => $registro, 'updated_at' => $registro];
            }
        }

        foreach (array_chunk($filas, 300) as $tanda) {
            DB::table('asistencias')->insert($tanda);
        }
    }

    /**
     * Casi todo lo de semanas pasadas completado; la mitad de lo de esta semana.
     *
     * @param  Collection<int, User>  $inscritos
     */
    protected function leccionesCompletadas(Curso $curso, Collection $inscritos): void
    {
        $actual = $this->semanaActual[$curso->id];
        $lecciones = DB::table('lecciones')->join('modulos', 'modulos.id', '=', 'lecciones.modulo_id')
            ->where('modulos.curso_id', $curso->id)->where('modulos.semana', '<=', $actual)->get(['lecciones.id', 'modulos.semana']);
        $filas = [];

        foreach ($lecciones as $leccion) {
            [$lunes] = $curso->rangoSemana($leccion->semana);

            foreach ($inscritos as $estudiante) {
                $riesgo = in_array($estudiante->id, $this->enRiesgo[$curso->id], true);
                $probabilidad = $leccion->semana < $actual ? ($riesgo ? 60 : 92) : ($riesgo ? 30 : 55);

                if ($this->azar->getInt(1, 100) > $probabilidad) {
                    continue;
                }

                $momento = $lunes->copy()->addMinutes($this->azar->getInt(120, 6 * 24 * 60))->min($this->ahora->copy()->subMinutes(10));
                $filas[] = ['leccion_id' => $leccion->id, 'estudiante_id' => $estudiante->id, 'completada_en' => $momento->format('Y-m-d H:i:s')];
            }
        }

        foreach (array_chunk($filas, 500) as $tanda) {
            DB::table('lecciones_completadas')->insert($tanda);
        }
    }

    /** 2-3 anuncios del curso de los últimos días. */
    protected function comunicados(Curso $curso, User $titular, int $actual): void
    {
        $tema = self::TEMAS[$curso->codigo][$actual - 1];
        $proxima = Tarea::where('curso_id', $curso->id)->where('fecha_entrega', '>', $this->ahora)->orderBy('fecha_entrega')->first();
        $lugar = $curso->modalidad === 'presencial' ? 'en el aula del curso' : 'por videollamada (enlace en la sección del curso)';
        $anuncios = [
            ["Material de la semana {$actual} disponible", "Ya pueden revisar los recursos de {$tema}. Lean la guía de estudio antes de la clase.", 'informativo'],
            ['Horario de asesoría', "Esta semana habrá asesoría el jueves de 17:00 a 18:00 {$lugar}. Traigan sus dudas.", 'informativo'],
            ['Notas de la semana '.($actual - 1).' publicadas', 'Ya están las notas y la retroalimentación de la tarea de la semana '.($actual - 1).'. Revisen los comentarios.', 'importante'],
        ];

        if ($proxima) {
            $anuncios[] = ["Recordatorio: {$proxima->titulo}", "La entrega vence el {$proxima->fecha_entrega->format('d/m/Y')} a las 23:59. Revisen las instrucciones con tiempo.", 'importante'];
        }

        foreach (array_slice($this->azar->shuffleArray($anuncios), 0, $this->azar->getInt(2, 3)) as $i => [$titulo, $contenido, $prioridad]) {
            $momento = $this->ahora->copy()->subDays($this->azar->getInt(0, 4))->setTime($this->azar->getInt(8, 20), $this->azar->getInt(0, 59))->min($this->ahora->copy()->subMinutes(30 + $i));
            $this->crear(Comunicado::class, ['user_id' => $titular->id, 'curso_id' => $curso->id, 'titulo' => $titulo, 'contenido' => $contenido, 'prioridad' => $prioridad,
                'destinatario' => 'estudiantes', 'activo' => true, 'notificado' => true, 'created_at' => $momento, 'updated_at' => $momento]);
        }
    }

    /**
     * 1-2 debates recientes con 3-8 comentarios (algunas respuestas y una del profesor).
     *
     * @param  Collection<int, User>  $inscritos
     * @param  list<string>  $opciones
     */
    protected function debates(Curso $curso, User $titular, Collection $inscritos, int $actual, array $opciones): void
    {
        if (! in_array('debate', $opciones, true)) {
            return;
        }

        $textos = ['Lo veo en mi trabajo: lo usamos para tomar decisiones con datos.', 'Me ayudó a entender un caso de mi barrio que comentamos en clase.', 'Coincido; además se relaciona con lo visto la semana pasada.',
            'Tengo una duda: ¿aplica igual cuando los datos están incompletos?', 'Un ejemplo es cómo se organiza el presupuesto familiar cada mes.', 'Creo que el ejemplo de la clase lo explica mejor que el libro.',
            'Agrego un enlace a un artículo que encontré sobre este tema.', 'En mi caso lo apliqué en el proyecto del curso y funcionó.'];

        foreach (array_slice([$actual - 1, $actual - 3], 0, in_array('debate_doble', $opciones, true) ? 2 : 1) as $semana) {
            $tema = self::TEMAS[$curso->codigo][$semana - 1];
            [$lunes] = $curso->rangoSemana($semana);
            $publicado = $lunes->copy()->addDay()->setTime(9, 0);
            $debate = $this->crear(Debate::class, ['curso_id' => $curso->id, 'autor_id' => $titular->id, 'titulo' => '¿Dónde aplicas '.mb_strtolower($tema).' fuera del aula?',
                'consigna' => 'Comparte un ejemplo concreto de tu vida diaria o tu trabajo y comenta el aporte de al menos un compañero.', 'estado' => 'publicado',
                'publicado_en' => $publicado, 'created_at' => $publicado, 'updated_at' => $publicado]);
            $momento = $publicado->copy();
            $primero = null;

            foreach (collect($this->azar->shuffleArray($inscritos->all()))->take($this->azar->getInt(3, 7))->values() as $i => $estudiante) {
                $momento = $momento->copy()->addMinutes($this->azar->getInt(90, 900))->min($this->ahora->copy()->subMinutes(20));
                $comentario = $this->crear(DebateComentario::class, ['debate_id' => $debate->id, 'user_id' => $estudiante->id, 'parent_id' => $i === 2 ? $primero?->id : null,
                    'contenido' => $textos[($i + $semana) % count($textos)], 'created_at' => $momento, 'updated_at' => $momento]);
                $primero ??= $comentario;
            }

            $momento = $momento->copy()->addHours(3)->min($this->ahora->copy()->subMinutes(10));
            $this->crear(DebateComentario::class, ['debate_id' => $debate->id, 'user_id' => $titular->id, 'parent_id' => $primero->id,
                'contenido' => 'Muy buen ejemplo. Tráiganlo a la próxima clase para discutirlo entre todos.', 'created_at' => $momento, 'updated_at' => $momento]);
        }
    }

    /**
     * Consultas recientes de estudiantes al profesor, algunas ya respondidas.
     *
     * @param  Collection<int, User>  $inscritos
     */
    protected function mensajes(Curso $curso, User $titular, Collection $inscritos): void
    {
        $consultas = [['Consulta sobre la tarea', '¿Se puede entregar el desarrollo en un solo PDF con fotos del cuaderno?', 'Sí, un solo PDF está bien; revisa que las fotos se lean.'],
            ['Duda del tema de la semana', 'Profesor, no me quedó claro el último ejemplo de la clase. ¿Podría recomendarme material?', 'Revisa la guía de estudio de la semana; el ejemplo 3 es muy parecido.'],
            ['Inasistencia a la clase', 'No pude asistir a la última clase por un tema de salud. ¿Dónde reviso lo avanzado?', 'Revisa la grabación y la presentación de la semana; cualquier duda me escribes.']];

        foreach (collect($this->azar->shuffleArray($inscritos->all()))->take(3)->values() as $i => $estudiante) {
            [$asunto, $cuerpo, $respuesta] = $consultas[$i];
            $enviado = $this->ahora->copy()->subDays($this->azar->getInt(1, 5))->setTime($this->azar->getInt(8, 22), $this->azar->getInt(0, 59));
            $respondido = $i < 2 ? $enviado->copy()->addHours($this->azar->getInt(2, 20))->min($this->ahora->copy()->subMinutes(15)) : null;
            $this->crear(Mensaje::class, ['remitente_id' => $estudiante->id, 'destinatario_id' => $titular->id, 'curso_id' => $curso->id, 'asunto' => $asunto, 'cuerpo' => $cuerpo,
                'leido_at' => $respondido, 'created_at' => $enviado, 'updated_at' => $respondido ?? $enviado]);

            if ($respondido) {
                $this->crear(Mensaje::class, ['remitente_id' => $titular->id, 'destinatario_id' => $estudiante->id, 'curso_id' => $curso->id, 'asunto' => "Re: {$asunto}", 'cuerpo' => $respuesta,
                    'leido_at' => $i === 0 ? $respondido->copy()->addHour()->min($this->ahora) : null, 'created_at' => $respondido, 'updated_at' => $respondido]);
            }
        }
    }

    /** 3-5 eventos futuros (próximos 1-12 días) en el calendario de cada profesor. */
    protected function eventosDeProfesores(): void
    {
        foreach ($this->docentes as $clave => $profesor) {
            $cursos = Curso::whereIn('id', $this->cursosCreados)->where('estado', 'publicado')->where('profesor_id', $profesor->id)->orderBy('id')->get();
            $principal = $cursos->first();
            $eventos = [
                ['Reunión de coordinación académica', 'evento'],
                ["Revisar entregas pendientes de {$principal->codigo}", 'recordatorio'],
                ["Preparar el examen parcial de {$principal->nombre}", 'bloque_estudio'],
                ['Subir el material de la próxima semana de '.($cursos->last()->codigo), 'recordatorio'],
                ["Clase de reforzamiento: {$principal->nombre}", 'evento'],
            ];

            foreach (array_slice($eventos, 0, $clave === 'pepito' ? 5 : $this->azar->getInt(3, 4)) as $i => [$titulo, $tipo]) {
                $fecha = $this->ahora->copy()->addDays(1 + $i * 2 + $this->azar->getInt(0, 1))->setTime([9, 15, 10, 18, 16][$i], 0);
                $creado = $this->ahora->copy()->subDays($this->azar->getInt(1, 3));
                $this->eventosCreados[] = $this->crear(EventoCalendario::class, ['usuario_id' => $profesor->id, 'titulo' => $titulo, 'fecha' => $fecha, 'tipo' => $tipo, 'created_at' => $creado, 'updated_at' => $creado])->id;
            }
        }
    }

    // ==========================================
    // VERIFICACIÓN
    // ==========================================

    /** @return list<string> */
    protected function verificar(): array
    {
        $errores = [];
        $cursos = Curso::whereIn('id', $this->cursosCreados)->get();
        $publicados = $cursos->where('estado', 'publicado');

        foreach ($this->docentes as $profesor) {
            $dock = app(PanelProfesor::class)->totalPorCalificar($profesor);
            $servicio = app(CursosProfesor::class);
            $suyos = $servicio->cursos($profesor);
            $hero = (int) collect($servicio->resumen($suyos, $suyos))->sum('pendientes');
            $dock === $hero || $errores[] = "Por calificar de {$profesor->name}: dock {$dock} ≠ hero {$hero}";
        }

        $tareas = Tarea::whereIn('curso_id', $this->cursosCreados)->with('entregas')->get();
        $fueraDePlazo = $tareas->sum(fn (Tarea $t) => $t->entregas->filter(fn (Entrega $e) => $t->estadoPara($e, $this->ahora) === 'fuera_de_plazo')->count());
        $fueraDePlazo === 0 || $errores[] = "{$fueraDePlazo} entregas se muestran «Fuera de plazo»";

        $esperas = $this->pendientes()->filter(fn (Entrega $e) => PorCalificar::diasEsperando($e->updated_at, $this->ahora) > 6)->count();
        $esperas === 0 || $errores[] = "{$esperas} entregas esperan calificación más de 6 días";

        $inscritos = DB::table('inscripciones')->whereIn('curso_id', $this->cursosCreados)->where('estado', 'activo')->get(['curso_id', 'estudiante_id'])->groupBy('curso_id');
        $sinEntregar = $tareas->filter(fn (Tarea $t) => $t->fecha_entrega->lt($this->ahora))
            ->sum(fn (Tarea $t) => collect($inscritos[$t->curso_id] ?? [])->pluck('estudiante_id')->diff($t->entregas->pluck('estudiante_id'))->count());
        $sinEntregar === 0 || $errores[] = "{$sinEntregar} entregas faltantes en tareas vencidas";

        $sinRendir = Quiz::whereIn('curso_id', $this->cursosCreados)->where('fecha_limite', '<', $this->ahora)->with('resultados:id,quiz_id,estudiante_id')->get()
            ->sum(fn (Quiz $q) => collect($inscritos[$q->curso_id] ?? [])->pluck('estudiante_id')->diff($q->resultados->pluck('estudiante_id'))->count());
        $sinRendir === 0 || $errores[] = "{$sinRendir} intentos faltantes en quizzes vencidos";

        $sinLista = DB::table('sesiones_clase')->whereIn('curso_id', $this->cursosCreados)->where('fecha', '<=', $this->ahora)
            ->whereNotExists(fn ($q) => $q->from('asistencias')->whereColumn('asistencias.sesion_id', 'sesiones_clase.id'))->count();
        $sinLista === 0 || $errores[] = "{$sinLista} sesiones pasadas sin lista";

        $antesDelInicio = DB::table('entregas')->join('tareas', 'tareas.id', '=', 'entregas.tarea_id')->whereIn('tareas.curso_id', $this->cursosCreados)
            ->where(fn ($q) => $q->where('entregas.created_at', '>', $this->ahora)->orWhereColumn('entregas.created_at', '<', 'tareas.fecha_inicio'))->count();
        $antesDelInicio === 0 || $errores[] = "{$antesDelInicio} entregas antes de la apertura de su tarea o en el futuro";

        foreach ($publicados as $curso) {
            ($curso->modulos()->exists() && $curso->inscripcionesActivas()->exists()) || $errores[] = "{$curso->codigo} publicado sin módulo o sin matrícula";
            $curso->inscripciones()->where('fecha_inscripcion', '>=', $curso->fecha_inicio)->exists() && $errores[] = "{$curso->codigo} tiene matrículas posteriores al inicio";
            // Semanas pasadas: las 4 secciones (3 en cursos virtuales, sin grabación). La actual: al menos 3 (la grabación solo si ya se dictó).
            $completas = in_array($curso->modalidad, Curso::MODALIDADES_CON_HORARIO, true) ? 4 : 3;

            foreach ($curso->modulos()->where('tipo_seccion', Modulo::TIPO_SEMANA)->where('semana', '<=', $this->semanaActual[$curso->id])->with('lecciones:id,modulo_id,tipo_recurso')->get() as $modulo) {
                $secciones = $modulo->semana < $this->semanaActual[$curso->id] ? $completas : 3;
                $modulo->lecciones->pluck('tipo_recurso')->unique()->count() >= $secciones || $errores[] = "{$curso->codigo} semana {$modulo->semana} no tiene sus {$secciones} secciones";
            }
        }

        foreach (Modulo::whereIn('curso_id', $this->cursosCreados)->withCount(['lecciones', 'tareas', 'quizzes'])->get() as $modulo) {
            $esperado = $this->esperadoPorModulo[$modulo->id] ?? ['lecciones' => 0, 'tareas' => 0, 'quizzes' => 0];
            [$modulo->lecciones_count, $modulo->tareas_count, $modulo->quizzes_count] === [$esperado['lecciones'], $esperado['tareas'], $esperado['quizzes']]
                || $errores[] = "módulo {$modulo->id}: el encabezado muestra {$modulo->lecciones_count}/{$modulo->tareas_count}/{$modulo->quizzes_count} y se sembraron {$esperado['lecciones']}/{$esperado['tareas']}/{$esperado['quizzes']}";
        }

        foreach (EsquemaEvaluacion::whereIn('curso_id', $this->cursosCreados)->with(['rubros.tareas', 'rubros.quizzes'])->get() as $esquema) {
            abs($esquema->rubros->sum('peso') - 100) < 0.01 || $errores[] = "el esquema del curso {$esquema->curso_id} no suma 100 %";

            foreach ($esquema->rubros as $rubro) {
                $actividades = $rubro->tareas->whereNull('practica')->sum('pivot.peso') + $rubro->quizzes->sum('pivot.peso');
                ($actividades == 0 || abs($actividades - $rubro->pesoParaActividades()) < 0.01) || $errores[] = "{$rubro->nombre} (curso {$esquema->curso_id}): actividades suman {$actividades} y deberían sumar {$rubro->pesoParaActividades()}";
            }
        }

        return $errores;
    }

    /** @return Collection<int, Entrega> pendientes de calificar de los cursos sembrados */
    protected function pendientes(): Collection
    {
        return Entrega::query()->whereHas('tarea', fn ($q) => $q->whereIn('curso_id', $this->cursosCreados))->where('estado', 'entregado')
            ->with(['estudiante:id,name', 'tarea.curso', 'grupo'])->get();
    }

    /**
     * Pantallas con sesión en memoria, dentro de un savepoint que se deshace.
     *
     * @return list<array{0: string, 1: string, 2: int}>
     */
    protected function probarPaginas(): array
    {
        config(['session.driver' => 'array']);
        $kernel = app(HttpKernel::class);
        $resultado = [];
        $visitas = [];

        foreach ($this->docentes as $profesor) {
            $suyos = Curso::whereIn('id', $this->cursosCreados)->where('estado', 'publicado')->where('profesor_id', $profesor->id)->orderBy('id')->get();
            $conClases = $suyos->first(fn (Curso $c) => $c->usa_asistencia);
            $visitas[] = [$profesor, ['/dashboard', '/cursos', '/por-calificar', "/cursos/{$suyos->first()->id}", ...($conClases ? ["/cursos/{$conClases->id}/asistencia"] : [])]];
        }

        $estudiante = User::whereIn('id', $this->usuariosCreados)->withCount(['inscripciones' => fn ($q) => $q->where('estado', 'activo')])->orderByDesc('inscripciones_count')->first();
        $visitas[] = [$estudiante, ['/dashboard', '/cursos/'.$estudiante->inscripciones()->value('curso_id')]];
        $visitas[] = [$this->supervisor, ['/dashboard', '/cursos']];

        DB::beginTransaction();

        try {
            foreach ($visitas as [$usuario, $rutas]) {
                foreach ($rutas as $ruta) {
                    app('auth')->forgetGuards();
                    app('auth')->guard('web')->setUser($usuario);
                    $respuesta = $kernel->handle(Request::create($ruta, 'GET'));
                    $resultado[] = [$usuario->name, $ruta, $respuesta->getStatusCode()];
                }
            }
        } finally {
            DB::rollBack();
            app('auth')->forgetGuards();
        }

        return $resultado;
    }

    /**
     * Huella del esquema y de lo que no debe cambiar: tablas, columnas,
     * índices, triggers, funciones, roles/permisos/migraciones y los usuarios
     * existentes (filas completas: nombre, correo, contraseña) con sus roles.
     *
     * @return array{conteos: array<string, int>, huellas: array<string, string>}
     */
    protected function foto(): array
    {
        $existentes = User::whereNotIn('id', $this->usuariosCreados)->orderBy('id')->pluck('id')->all();

        if (DB::getDriverName() === 'pgsql') {
            $consultas = [
                'tablas' => "select table_name from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE' order by 1",
                'columnas' => "select table_name, column_name, data_type, is_nullable, column_default from information_schema.columns where table_schema = 'public' order by 1, 2",
                'índices' => "select tablename, indexname, indexdef from pg_indexes where schemaname = 'public' order by 1, 2",
                'restricciones' => "select table_name, constraint_name, constraint_type from information_schema.table_constraints where table_schema = 'public' order by 1, 2",
                'triggers' => "select c.relname, t.tgname, pg_get_triggerdef(t.oid) as d from pg_trigger t join pg_class c on c.oid = t.tgrelid join pg_namespace n on n.oid = c.relnamespace where n.nspname = 'public' and not t.tgisinternal order by 1, 2",
                'funciones' => "select p.proname, md5(p.prosrc) as h from pg_proc p join pg_namespace n on n.oid = p.pronamespace where n.nspname = 'public' order by 1, 2",
            ];
            $filas = collect($consultas)->map(fn (string $sql) => DB::select($sql));
        } else {
            $tablas = collect(Schema::getTables())->pluck('name')->sort()->values();
            $filas = collect([
                'tablas' => $tablas->all(),
                'columnas' => $tablas->flatMap(fn (string $t) => collect(Schema::getColumns($t))->map(fn (array $c) => [$t, $c['name'], $c['type'], $c['nullable']]))->all(),
                'índices' => $tablas->flatMap(fn (string $t) => collect(Schema::getIndexes($t))->map(fn (array $i) => [$t, $i['name'], $i['columns']]))->all(),
                'restricciones' => $tablas->flatMap(fn (string $t) => collect(Schema::getForeignKeys($t))->map(fn (array $f) => [$t, $f['columns'], $f['foreign_table']]))->all(),
                'triggers' => DB::select("select name, sql from sqlite_master where type = 'trigger' order by name"),
                'funciones' => [],
            ]);
        }

        $contenido = collect(self::TABLAS_INTOCABLES)->filter(fn (string $t) => Schema::hasTable($t))
            ->mapWithKeys(fn (string $t) => [$t => DB::table($t)->get()->map(fn ($f) => (array) $f)->sortBy(fn (array $f) => json_encode($f))->values()->all()]);
        $usuarios = DB::table('users')->whereIn('id', $existentes)->orderBy('id')->get();
        $roles = DB::table('model_has_roles')->whereIn('model_id', $existentes)->where('model_type', (new User)->getMorphClass())->orderBy('model_id')->orderBy('role_id')->get();

        return [
            'conteos' => [...$filas->map(fn ($lista) => count($lista))->all(), ...$contenido->map(fn (array $lista) => count($lista))->all(), 'usuarios existentes' => $usuarios->count()],
            'huellas' => [...$filas->map(fn ($lista) => md5(json_encode($lista)))->all(), ...$contenido->map(fn (array $lista) => md5(json_encode($lista)))->all(),
                'usuarios existentes' => md5($usuarios->toJson()), 'roles de usuarios existentes' => md5($roles->toJson())],
        ];
    }

    /**
     * @param  array{conteos: array<string, int>, huellas: array<string, string>}  $antes
     * @param  array{conteos: array<string, int>, huellas: array<string, string>}  $despues
     * @return list<string>
     */
    protected function compararFotos(array $antes, array $despues): array
    {
        return collect($antes['huellas'])->filter(fn (string $huella, string $clave) => ($despues['huellas'][$clave] ?? null) !== $huella)
            ->keys()->map(fn (string $clave) => "cambió: {$clave}")->values()->all();
    }

    // ==========================================
    // REPORTE Y MANIFEST
    // ==========================================

    /**
     * @param  array{conteos: array<string, int>, huellas: array<string, string>}  $antes
     * @param  array{conteos: array<string, int>, huellas: array<string, string>}  $despues
     * @param  array<string, int>  $sueltas
     * @param  list<array{0: string, 1: string, 2: int}>  $paginas
     */
    protected function reportar(array $antes, array $despues, array $sueltas, array $paginas): void
    {
        $ids = $this->idsSembrados();
        $this->info('1) Filas creadas por tabla');
        $this->table(['Tabla', 'Filas'], collect($ids)->except('pivotes')->map(fn (array $lista, string $tabla) => [$tabla, count($lista)])->values()
            ->concat(collect($ids['pivotes'])->map(fn (int $n, string $tabla) => [$tabla, $n])->values())->all());

        $pendientes = $this->pendientes();
        $filas = PorCalificar::filasPendientes($pendientes, $this->ahora);
        $this->info('2) Cursos');
        $this->table(['Código', 'Curso', 'Profesor', 'Modalidad', 'Estado', 'Semana', 'Matrículas', 'Tareas', 'Quizzes', 'Sesiones', 'Por calificar'],
            Curso::whereIn('id', $this->cursosCreados)->with(['profesor', 'profesoresAdicionales'])->withCount(['inscripcionesActivas', 'tareas', 'quizzes', 'sesionesClase'])->orderBy('id')->get()
                ->map(fn (Curso $c) => [$c->codigo, $c->nombre, $c->profesor->name.($c->profesoresAdicionales->isNotEmpty() ? ' + '.$c->profesoresAdicionales->pluck('name')->join(', ') : ''), $c->modalidad, $c->estado,
                    isset($this->semanaActual[$c->id]) ? "{$this->semanaActual[$c->id]} de 16" : 'empieza el '.$c->fecha_inicio->format('d/m'),
                    $c->inscripciones_activas_count, $c->tareas_count, $c->quizzes_count, $c->sesiones_clase_count,
                    $filas->filter(fn (array $f) => $f['entrega']->tarea->curso_id === $c->id)->count()])->all());

        $this->info('3) Por calificar por profesor (una fila por entrega individual o por grupo)');
        $this->table(['Profesor', 'Dock', 'Hero', 'Verdes (0-2 d)', 'Ámbar (3-7 d)', 'Rojas (>7 d)'], collect($this->docentes)->map(function (User $p) use ($filas) {
            $suyos = Curso::dictadosPor($p)->pluck('id');
            $propias = $filas->filter(fn (array $f) => $suyos->contains($f['entrega']->tarea->curso_id));
            $servicio = app(CursosProfesor::class);
            $cursos = $servicio->cursos($p);

            return [$p->name, app(PanelProfesor::class)->totalPorCalificar($p), (int) collect($servicio->resumen($cursos, $cursos))->sum('pendientes'),
                $propias->where('urgencia', 'baja')->count(), $propias->where('urgencia', 'media')->count(), $propias->where('urgencia', 'alta')->count()];
        })->values()->all());

        $this->info('4) Integridad (antes → después)');
        $this->table(['Elemento', 'Antes', 'Después', '¿Idéntico?'], collect($antes['conteos'])->map(fn (int $n, string $clave) => [$clave, $n, $despues['conteos'][$clave] ?? '—',
            ($despues['huellas'][$clave] ?? null) === ($antes['huellas'][$clave] ?? null) ? 'sí' : 'NO'])->values()
            ->push(['roles de usuarios existentes', '', '', $despues['huellas']['roles de usuarios existentes'] === $antes['huellas']['roles de usuarios existentes'] ? 'sí' : 'NO'])->all());
        $this->line('Filas nuevas por efecto de la siembra: bitácora '.$sueltas['bitacoras_auditoria'].', notifications '.$sueltas['notifications'].'.');

        if ($paginas !== []) {
            $this->info('5) Pantallas (sesión en memoria)');
            $this->table(['Usuario', 'Ruta', 'Estado'], $paginas);
        }

        $estudiante = User::whereIn('id', $this->usuariosCreados)->orderBy('id')->take(3)->pluck('email')->join(', ');
        $this->line('Contraseña de demostración de los estudiantes nuevos: '.self::PASSWORD." (sin primer login). Ejemplos: {$estudiante}.");
        $this->line('No se ejecutaron (modelos directos): correo de bienvenida y bitácora de GestorCuentas::crear; notificación y bitácora de GestorMatriculas::matricularManual; bitácora de AsignadorEvaluacion y PracticasEnEvaluaciones::guardar.');
    }

    /** @return array<string, mixed> ids por tabla de todo lo sembrado */
    protected function idsSembrados(): array
    {
        $ids = fn (string $tabla, string $columna, array $valores) => collect($valores)->chunk(500)
            ->flatMap(fn (Collection $tanda) => DB::table($tabla)->whereIn($columna, $tanda->values()->all())->pluck('id'))->sort()->values()->all();
        $cursos = $this->cursosCreados;
        $modulos = $ids('modulos', 'curso_id', $cursos);
        $tareas = $ids('tareas', 'curso_id', $cursos);
        $quizzes = $ids('quizzes', 'curso_id', $cursos);
        $grupos = $ids('grupos', 'curso_id', $cursos);
        $sesiones = $ids('sesiones_clase', 'curso_id', $cursos);
        $esquemas = $ids('esquemas_evaluacion', 'curso_id', $cursos);
        $rubros = $ids('rubros_evaluacion', 'esquema_id', $esquemas);
        $debates = $ids('debates', 'curso_id', $cursos);
        $lecciones = $ids('lecciones', 'modulo_id', $modulos);

        return [
            'users' => $this->usuariosCreados,
            'cursos' => $cursos,
            'curso_profesor' => $ids('curso_profesor', 'curso_id', $cursos),
            'inscripciones' => $ids('inscripciones', 'curso_id', $cursos),
            'esquemas_evaluacion' => $esquemas,
            'rubros_evaluacion' => $rubros,
            'modulos' => $modulos,
            'lecciones' => $lecciones,
            'lecciones_completadas' => $ids('lecciones_completadas', 'leccion_id', $lecciones),
            'grupos' => $grupos,
            'grupo_estudiante' => $ids('grupo_estudiante', 'grupo_id', $grupos),
            'tareas' => $tareas,
            'entregas' => $ids('entregas', 'tarea_id', $tareas),
            'banco_preguntas' => $ids('banco_preguntas', 'curso_id', $cursos),
            'quizzes' => $quizzes,
            'resultados_quiz' => $ids('resultados_quiz', 'quiz_id', $quizzes),
            'sesiones_clase' => $sesiones,
            'asistencias' => $ids('asistencias', 'sesion_id', $sesiones),
            'comunicados' => $ids('comunicados', 'curso_id', $cursos),
            'debates' => $debates,
            'debate_comentarios' => $ids('debate_comentarios', 'debate_id', $debates),
            'mensajes' => $ids('mensajes', 'curso_id', $cursos),
            'eventos_calendario' => $this->eventosCreados,
            'pivotes' => [
                'model_has_roles' => DB::table('model_has_roles')->whereIn('model_id', $this->usuariosCreados)->where('model_type', (new User)->getMorphClass())->count(),
                'rubro_tarea' => DB::table('rubro_tarea')->whereIn('rubro_id', $rubros)->count(),
                'rubro_quiz' => DB::table('rubro_quiz')->whereIn('rubro_id', $rubros)->count(),
            ],
        ];
    }

    protected function escribirManifiesto(): string
    {
        $carpeta = storage_path('app/backups-demo/'.$this->ahora->format('Y-m-d_His').'_siembra');
        File::ensureDirectoryExists($carpeta);
        $datos = ['modo' => 'apply', 'fecha' => $this->ahora->toIso8601String(), 'semilla' => (int) $this->option('semilla'), 'ids' => $this->idsSembrados()];
        $datos['sha256_ids'] = hash('sha256', json_encode($datos['ids']));
        File::put("{$carpeta}/manifest.json", json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $carpeta;
    }

    // ==========================================
    // LIMPIEZA
    // ==========================================

    /**
     * Borra exactamente lo que registró el manifest (hijos antes que padres).
     * Se niega si algo creado después cuelga de lo sembrado (p. ej. una
     * entrega nueva): en ese caso hay que usar app:vaciar-datos-prueba.
     */
    protected function limpiar(string $carpeta, bool $aplicar): int
    {
        $carpeta = is_dir($carpeta) ? $carpeta : storage_path('app/backups-demo/'.$carpeta);
        $this->exigir(is_file("{$carpeta}/manifest.json"), "no hay manifest.json en {$carpeta}");
        $manifiesto = json_decode(File::get("{$carpeta}/manifest.json"), true);
        $this->exigir(hash('sha256', json_encode($manifiesto['ids'])) === ($manifiesto['sha256_ids'] ?? null), 'el manifest fue modificado (sha256 distinto)');
        $ids = $manifiesto['ids'];

        $ajenas = collect([
            'entregas' => DB::table('entregas')->whereIn('tarea_id', $ids['tareas'])->count() - count($ids['entregas']),
            'resultados_quiz' => DB::table('resultados_quiz')->whereIn('quiz_id', $ids['quizzes'])->count() - count($ids['resultados_quiz']),
            'asistencias' => DB::table('asistencias')->whereIn('sesion_id', $ids['sesiones_clase'])->count() - count($ids['asistencias']),
            'inscripciones' => DB::table('inscripciones')->whereIn('estudiante_id', $ids['users'])->count() - DB::table('inscripciones')->whereIn('estudiante_id', $ids['users'])->whereIn('id', $ids['inscripciones'])->count(),
            'modulos' => DB::table('modulos')->whereIn('curso_id', $ids['cursos'])->count() - count($ids['modulos']),
            'notifications' => DB::table('notifications')->whereIn('notifiable_id', $ids['users'])->where('notifiable_type', (new User)->getMorphClass())->count(),
            'sessions' => Schema::hasTable('sessions') ? DB::table('sessions')->whereIn('user_id', $ids['users'])->count() : 0,
        ])->filter(fn (int $n) => $n > 0);

        if ($ajenas->isNotEmpty()) {
            $this->error('Hay datos creados después de la siembra que cuelgan de ella ('.$ajenas->map(fn ($n, $t) => "{$t}: {$n}")->join(', ').'). No se borró nada; usa app:vaciar-datos-prueba.');

            return self::FAILURE;
        }

        DB::beginTransaction();

        try {
            $borradas = [
                'rubro_tarea' => DB::table('rubro_tarea')->whereIn('rubro_id', $ids['rubros_evaluacion'])->delete(),
                'rubro_quiz' => DB::table('rubro_quiz')->whereIn('rubro_id', $ids['rubros_evaluacion'])->delete(),
                'model_has_roles' => DB::table('model_has_roles')->whereIn('model_id', $ids['users'])->where('model_type', (new User)->getMorphClass())->delete(),
            ];

            foreach (self::ORDEN_LIMPIEZA as $tabla) {
                // Se comprueba lo que queda y no lo que devuelve delete(): una respuesta de debate puede caer en cascada con su comentario padre.
                $tandas = collect($ids[$tabla])->chunk(500)->map(fn (Collection $tanda) => $tanda->values()->all());
                $tandas->each(fn (array $tanda) => DB::table($tabla)->whereIn('id', $tanda)->delete());
                $quedan = $tandas->sum(fn (array $tanda) => DB::table($tabla)->whereIn('id', $tanda)->count());
                $this->exigir($quedan === 0, "{$tabla}: quedaron {$quedan} filas del manifest sin borrar");
                $borradas[$tabla] = count($ids[$tabla]);
            }

            $this->table(['Tabla', 'Borradas'], collect($borradas)->map(fn (int $n, string $t) => [$t, $n])->values()->all());
            $aplicar ? DB::commit() : DB::rollBack();
        } catch (Throwable $error) {
            DB::rollBack();
            throw $error;
        }

        $this->info($aplicar ? 'LIMPIADO.' : 'SIMULACIÓN de limpieza: rollback, la base no cambió. Usa --apply para borrar.');

        return self::SUCCESS;
    }

    // ==========================================
    // AUXILIARES
    // ==========================================

    /**
     * Crea un modelo con todos sus atributos (incluidas fechas) sin pasar por
     * $fillable; los eventos del modelo (p. ej. Modulo::creating) sí se ejecutan.
     *
     * @template T of Model
     *
     * @param  class-string<T>  $clase
     * @param  array<string, mixed>  $atributos
     * @return T
     */
    protected function crear(string $clase, array $atributos): Model
    {
        $modelo = new $clase;
        $modelo->forceFill($atributos)->save();

        return $modelo;
    }

    protected function exigir(bool $condicion, string $mensaje): void
    {
        if (! $condicion) {
            throw new RuntimeException($mensaje);
        }
    }
}
