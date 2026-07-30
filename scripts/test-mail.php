#!/usr/bin/env php
<?php
/* Prueba de correo en el VPS: php scripts/test-mail.php */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Solo CLI\n");
}

require dirname(__DIR__) . '/db.php';

$destino = CORREO_DESTINO;
$from = CORREO_ORIGEN;
$asunto = 'Prueba correo · La Calle de las Aves · ' . date('Y-m-d H:i:s');
$cuerpo = "Si recibes esto, mail() funciona en DreamHost.\n\nOrigen: $from\nDestino: $destino\n";

ini_set('sendmail_from', $from);
$headers = "From: La Calle de las Aves <$from>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
$ok = mail($destino, $asunto, $cuerpo, $headers, '-f' . $from);

echo $ok ? "OK: correo enviado a $destino\n" : "ERROR: mail() devolvió false\n";
echo "Revisa bandeja y spam de $destino\n";
