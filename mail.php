<?php
/* Envío de correos. DreamHost: crea la cuenta CORREO_ORIGEN en el panel (Mail). */

function enviar_correo(string $destino, string $asunto, string $cuerpo, ?string $replyTo = null): bool {
    if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
        error_log('aves-mc: destino de correo inválido');
        return false;
    }

    $from = CORREO_ORIGEN;
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        error_log('aves-mc: CORREO_ORIGEN inválido en config.php — crea la cuenta en DreamHost');
        return false;
    }

    $headers = [
        'From: La Calle de las Aves <' . $from . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    $asuntoEnc = '=?UTF-8?B?' . base64_encode($asunto) . '?=';
    $headerStr = implode("\r\n", $headers);
    // -f fija el remitente SMTP (requerido en muchos hosts compartidos/VPS)
    $ok = mail($destino, $asuntoEnc, $cuerpo, $headerStr, '-f' . $from);
    if (!$ok) {
        error_log('aves-mc: mail() falló al enviar a ' . $destino);
    }
    return $ok;
}

function notificar_solicitud(array $b, string $numero, string $fecha): bool {
    $fmt = fn($n) => '$' . number_format((float) $n, 0, ',', '.');
    $destino = cfg_get('correoDestino', CORREO_DESTINO);
    $asunto  = 'Nueva solicitud de mural · ' . $b['negocio'] . ' · ' . $numero;
    $lineas = [
        'Nueva solicitud desde la página La Calle de las Aves y las Flores',
        '',
        "N° cotización: $numero",
        "Fecha: $fecha",
        '',
        "Nombre: {$b['nombre']}",
        "Identificación: {$b['identificacion']}",
        "Negocio: {$b['negocio']}",
        "Dirección: {$b['direccion']}",
        "Teléfono: {$b['telefono']}",
        "Correo del cliente: {$b['correo']}",
        '',
        "Mural elegido: {$b['muralNum']} · {$b['muralNombre']}",
        "Medidas: {$b['ancho']} m x {$b['alto']} m ({$b['m2']} m²)",
        'Valor total: ' . $fmt($b['total'] ?? 0),
        'Apoyo Manizales Comparte: ' . $fmt($b['aComparte'] ?? 0),
        'Apoyo institucional: ' . $fmt($b['aInst'] ?? 0),
        'Código: ' . (($b['codigo'] ?? '') ? $b['codigo'] . ' (' . ($b['codigoPct'] ?? 0) . '%) = ' . $fmt($b['dcto'] ?? 0) : 'No aplicó'),
        'VALOR A PAGAR: ' . $fmt($b['pagar'] ?? 0),
    ];

    return enviar_correo($destino, $asunto, implode("\n", $lineas), $b['correo'] ?? null);
}
