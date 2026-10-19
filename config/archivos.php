<?php

/*
|--------------------------------------------------------------------------
| Subida de archivos
|--------------------------------------------------------------------------
| Disco, duración de los enlaces temporales, límite de tamaño (10 MB) y
| extensiones permitidas por contexto. El límite produce un error de
| validación claro en lugar de un error 500.
*/

return [

    /*
    | ERS RNF-06: disco donde se guardan fotos, portadas, materiales, entregas
    | y adjuntos. "supabase" es el bucket privado compartido por el equipo;
    | "local" (storage/app/private) sirve para trabajar sin credenciales y en
    | las pruebas. En ambos casos los archivos son privados y se abren con
    | enlaces temporales firmados.
    */
    'disco' => env('ARCHIVOS_DISCO', 'local'),

    /* Minutos que dura un enlace temporal antes de vencer. */
    'minutos_enlace' => (int) env('ARCHIVOS_MINUTOS_ENLACE', 30),

    'max_kb' => (int) env('ARCHIVOS_MAX_KB', 10240),

    /*
    | Extensiones permitidas por contexto. Nunca se aceptan scripts ejecutables
    | (php, phtml, phar, js, html, svg, exe, sh...).
    */
    'extensiones' => [
        'documentos' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip', 'rar', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'mp4', 'mp3'],
        'imagenes' => ['png', 'jpg', 'jpeg', 'gif', 'webp'],
    ],

];
