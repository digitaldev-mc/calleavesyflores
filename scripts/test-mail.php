#!/usr/bin/env php
<?php
/* Prueba SMTP en el VPS:
   php ~/avesyflores-src/scripts/test-mail.php
   php ~/avesyflores-src/scripts/test-mail.php --verbose
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

if (!smtp_configurado()) {
    fwrite(STDERR, "ERROR: define SMTP_PASS en config.php\n");
    exit(1);
}

$verbose = in_array('--verbose', $argv, true) || in_array('-v', $argv, true);
$destino = CORREO_DESTINO;
foreach ($argv as $arg) {
    if (str_contains($arg, '@')) {
        $destino = $arg;
    }
}

$asunto = 'Prueba SMTP · La Calle de las Aves · ' . date('Y-m-d H:i:s');
$plain  = "Prueba SMTP desde el VPS.\nDestino: $destino\nHora: " . date('c') . "\n";
$html   = '<p>Prueba <b>SMTP</b> desde el VPS.</p><p>Destino: ' . htmlspecialchars($destino) . '</p>';

echo "Enviando a: $destino\n";
echo "Desde: " . CORREO_ORIGEN . " vía " . smtp_host() . ':' . smtp_port() . "\n\n";

$ok = smtp_enviar($destino, $asunto, $html, $plain, null, $verbose);

echo $ok ? "\nOK: DreamHost aceptó el envío\n" : "\nERROR: SMTP falló antes de aceptar\n";
echo "\nSi OK pero no llega en 5 min:\n";
echo "  1) Busca en Gmail: from:notificaciones@manizalescomparte.com (incl. spam)\n";
echo "  2) Entra a webmail DreamHost como notificaciones@ → carpeta Enviados\n";
echo "  3) Prueba otro destino: php scripts/test-mail.php tu-correo-personal@gmail.com\n";
