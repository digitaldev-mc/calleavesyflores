<?php
/* Cliente SMTP para DreamHost (smtp.dreamhost.com:587 + STARTTLS). */

function smtp_configurado(): bool {
    return defined('SMTP_PASS')
        && SMTP_PASS !== ''
        && SMTP_PASS !== 'CAMBIA-ESTA-CLAVE';
}

function smtp_host(): string {
    return defined('SMTP_HOST') ? SMTP_HOST : 'smtp.dreamhost.com';
}

function smtp_port(): int {
    return defined('SMTP_PORT') ? (int) SMTP_PORT : 587;
}

function smtp_usuario(): string {
    return (defined('SMTP_USER') && SMTP_USER !== '') ? SMTP_USER : CORREO_ORIGEN;
}

function smtp_leer_respuesta($fp): string {
    $out = '';
    while ($line = fgets($fp, 8192)) {
        $out .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    return $out;
}

function smtp_codigo(string $resp): int {
    return (int) substr($resp, 0, 3);
}

function smtp_cmd($fp, ?string $cmd, array $codigosOk): bool {
    if ($cmd !== null) {
        fwrite($fp, $cmd . "\r\n");
    }
    $resp = smtp_leer_respuesta($fp);
    $code = smtp_codigo($resp);
    foreach ($codigosOk as $ok) {
        if ($code === (int) $ok) {
            return true;
        }
    }
    error_log('aves-mc SMTP: respuesta inesperada'
        . ($cmd !== null ? " tras [$cmd]" : '')
        . " → " . trim(str_replace("\r\n", ' | ', $resp)));
    return false;
}

function smtp_dot_stuff(string $data): string {
    return preg_replace('/^\./m', '..', $data);
}

function smtp_enviar(string $destino, string $asunto, string $html, string $plain, ?string $replyTo = null): bool {
    if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
        error_log('aves-mc SMTP: destino inválido');
        return false;
    }
    if (!smtp_configurado()) {
        error_log('aves-mc SMTP: falta SMTP_PASS en config.php');
        return false;
    }

    $host = smtp_host();
    $port = smtp_port();
    $user = smtp_usuario();
    $from = CORREO_ORIGEN;
    $fromName = 'La Calle de las Aves y las Flores';

    $fp = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 30);
    if (!$fp) {
        error_log("aves-mc SMTP: no conecta a {$host}:{$port} — {$errstr} ({$errno})");
        return false;
    }
    stream_set_timeout($fp, 30);

    $ehloHost = preg_replace('/[^a-zA-Z0-9.-]/', '', $_SERVER['SERVER_NAME'] ?? 'localhost') ?: 'localhost';

    if (!smtp_cmd($fp, null, [220])) {
        fclose($fp);
        return false;
    }
    if (!smtp_cmd($fp, "EHLO {$ehloHost}", [250])) {
        fclose($fp);
        return false;
    }
    if (!smtp_cmd($fp, 'STARTTLS', [220])) {
        fclose($fp);
        return false;
    }
    if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        error_log('aves-mc SMTP: STARTTLS falló');
        fclose($fp);
        return false;
    }

    $ok = smtp_cmd($fp, "EHLO {$ehloHost}", [250])
        && smtp_cmd($fp, 'AUTH LOGIN', [334])
        && smtp_cmd($fp, base64_encode($user), [334])
        && smtp_cmd($fp, base64_encode(SMTP_PASS), [235])
        && smtp_cmd($fp, "MAIL FROM:<{$from}>", [250])
        && smtp_cmd($fp, "RCPT TO:<{$destino}>", [250, 251])
        && smtp_cmd($fp, 'DATA', [354]);

    if (!$ok) {
        fclose($fp);
        return false;
    }

    $boundary = 'b_' . bin2hex(random_bytes(8));
    $headers = [
        "From: {$fromName} <{$from}>",
        "To: <{$destino}>",
        'Subject: =?UTF-8?B?' . base64_encode($asunto) . '?=',
        'MIME-Version: 1.0',
        "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
    ];
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = "Reply-To: {$replyTo}";
    }

    $cuerpo  = implode("\r\n", $headers) . "\r\n\r\n";
    $cuerpo .= "--{$boundary}\r\n";
    $cuerpo .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $cuerpo .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $cuerpo .= $plain . "\r\n\r\n";
    $cuerpo .= "--{$boundary}\r\n";
    $cuerpo .= "Content-Type: text/html; charset=UTF-8\r\n";
    $cuerpo .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $cuerpo .= $html . "\r\n\r\n";
    $cuerpo .= "--{$boundary}--\r\n";

    fwrite($fp, smtp_dot_stuff($cuerpo) . "\r\n.\r\n");

    if (!smtp_cmd($fp, null, [250])) {
        fclose($fp);
        return false;
    }

    smtp_cmd($fp, 'QUIT', [221]);
    fclose($fp);
    return true;
}
