#!/usr/bin/env php
<?php
/* Prueba SMTP en el VPS:
   php ~/avesyflores-src/scripts/test-mail.php */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Solo CLI\n");
}

$webRoot = getenv('WEB_ROOT') ?: (getenv('HOME') . '/calleavesyflores.manizalescomparte.com');
$config  = $webRoot . '/config.php';

if (!is_file($config)) {
    fwrite(STDERR, "ERROR: no existe $config\n");
    exit(1);
}

require $webRoot . '/db.php';

if (!smtp_configurado()) {
    fwrite(STDERR, "ERROR: define SMTP_PASS en config.php (contraseña del buzón notificaciones@...)\n");
    exit(1);
}

$destino = CORREO_DESTINO;
$asunto  = 'Prueba SMTP · La Calle de las Aves · ' . date('Y-m-d H:i:s');
$plain   = "Si recibes esto, SMTP funciona.\n\nHost: " . smtp_host() . ':' . smtp_port() . "\nUsuario: " . smtp_usuario() . "\nDestino: $destino\n";
$html    = '<p>Si recibes esto, <b>SMTP funciona</b>.</p><p>Destino: ' . htmlspecialchars($destino) . '</p>';

$ok = smtp_enviar($destino, $asunto, $html, $plain);

echo $ok ? "OK: correo SMTP enviado a $destino\n" : "ERROR: SMTP falló (revisa SMTP_PASS y logs PHP)\n";
echo "Revisa bandeja y spam de $destino (debería llegar en 1–3 min)\n";
