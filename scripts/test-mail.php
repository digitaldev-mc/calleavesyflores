#!/usr/bin/env php
<?php
/* Prueba de correo en el VPS:
   php ~/avesyflores-src/scripts/test-mail.php
   php ~/avesyflores-src/scripts/test-mail.php otro@correo.com */
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

$destino = CORREO_DESTINO;
foreach ($argv as $arg) {
    if (str_contains($arg, '@')) {
        $destino = $arg;
    }
}

$asunto = 'Prueba Resend · La Calle de las Aves · ' . date('Y-m-d H:i:s');
$plain  = "Prueba de correo vía Resend.\nDestino: $destino\nHora: " . date('c') . "\n";
$html   = '<p>Prueba <b>Resend</b> desde el VPS.</p><p>Destino: ' . htmlspecialchars($destino) . '</p>';

echo "Destino: $destino\n";
echo "Remitente: " . resend_remitente() . "\n\n";

if (!resend_configurado()) {
    fwrite(STDERR, "ERROR: define RESEND_API_KEY en config.php (empieza con re_)\n");
    exit(1);
}

$ok = resend_enviar($destino, $asunto, $html, $plain);

echo $ok ? "OK: Resend aceptó el envío\n" : "ERROR: Resend rechazó el envío (revisa logs PHP)\n";
echo "Debería llegar en 1–2 minutos. Revisa bandeja y spam.\n";
