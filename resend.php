<?php
/* Envío transaccional vía Resend API (https://resend.com). */

function resend_configurado(): bool {
    return defined('RESEND_API_KEY')
        && RESEND_API_KEY !== ''
        && str_starts_with(RESEND_API_KEY, 're_');
}

function resend_remitente(): string {
    $nombre = defined('CORREO_NOMBRE') && CORREO_NOMBRE !== ''
        ? CORREO_NOMBRE
        : 'La Calle de las Aves y las Flores';
    return $nombre . ' <' . CORREO_ORIGEN . '>';
}

function resend_enviar(string $destino, string $asunto, string $html, string $plain, ?string $replyTo = null): bool {
    if (!resend_configurado()) {
        error_log('aves-mc Resend: falta RESEND_API_KEY en config.php');
        return false;
    }
    if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
        error_log('aves-mc Resend: destino inválido');
        return false;
    }

    $payload = [
        'from'    => resend_remitente(),
        'to'      => [$destino],
        'subject' => $asunto,
        'html'    => $html,
        'text'    => $plain,
    ];
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $payload['reply_to'] = $replyTo;
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        error_log('aves-mc Resend: no se pudo codificar el payload');
        return false;
    }

    $headers = [
        'Authorization: Bearer ' . RESEND_API_KEY,
        'Content-Type: application/json',
    ];

    $respBody = null;
    $httpCode = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $respBody = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($respBody === false) {
            error_log('aves-mc Resend: curl error — ' . curl_error($ch));
            curl_close($ch);
            return false;
        }
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => implode("\r\n", $headers),
                'content' => $json,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);
        $respBody = @file_get_contents('https://api.resend.com/emails', false, $ctx);
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $httpCode = (int) $m[1];
        }
    }

    $data = $respBody ? json_decode($respBody, true) : null;

    if ($httpCode >= 200 && $httpCode < 300 && !empty($data['id'])) {
        return true;
    }

    $msg = is_array($data) ? ($data['message'] ?? json_encode($data)) : (string) $respBody;
    error_log("aves-mc Resend: HTTP $httpCode — $msg");
    return false;
}
