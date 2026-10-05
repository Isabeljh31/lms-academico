<?php

/*
|--------------------------------------------------------------------------
| Subida de archivos
|--------------------------------------------------------------------------
| Límite único para todas las subidas. Cloudinary (plan gratuito) rechaza
| archivos de más de 10 MB, así que el límite se alinea a ese valor para que
| el usuario reciba un error de validación claro en lugar de un error 500.
| Ver docs/AUDITORIA.md.
*/

return [

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
