#!/usr/bin/env php
<?php
/* Actualiza config.php en producción con secrets del entorno (solo en deploy). */
if (php_sapi_name() !== 'cli') {
    exit(1);
}

$webRoot = getenv('WEB_ROOT') ?: (getenv('HOME') . '/calleavesyflores.manizalescomparte.com');
$configPath = $webRoot . '/config.php';

if (!is_file($configPath)) {
    fwrite(STDERR, "ERROR: no existe $configPath\n");
    exit(1);
}

$src = file_get_contents($configPath);
$changed = false;

function patch_define(string &$src, string $name, string $value, bool &$changed): void {
    $escaped = addcslashes($value, "'\\");
    $line = "define('$name', '$escaped');";
    if (preg_match("/define\\('$name'\\s*,\\s*'[^']*'\\s*\\)\\s*;/", $src)) {
        $new = preg_replace("/define\\('$name'\\s*,\\s*'[^']*'\\s*\\)\\s*;/", $line, $src, 1, $count);
        if ($count && $new !== $src) {
            $src = $new;
            $changed = true;
        }
        return;
    }
    $anchor = "define('CORREO_ORIGEN'";
    if (str_contains($src, $anchor)) {
        $src = str_replace($anchor, $line . "\n" . $anchor, $src);
        $changed = true;
    }
}

$resend = getenv('RESEND_API_KEY') ?: '';
if ($resend !== '' && str_starts_with($resend, 're_')) {
    patch_define($src, 'RESEND_API_KEY', $resend, $changed);
}

$nombre = getenv('CORREO_NOMBRE') ?: '';
if ($nombre !== '') {
    patch_define($src, 'CORREO_NOMBRE', $nombre, $changed);
}

if (!$changed) {
    echo "patch-config: sin cambios\n";
    exit(0);
}

$tmp = $configPath . '.tmp.' . getmypid();
file_put_contents($tmp, $src);
chmod($tmp, 0600);
rename($tmp, $configPath);
echo "patch-config: config.php actualizado\n";
