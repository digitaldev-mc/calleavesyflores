<?php
/* Envío de correos HTML. DreamHost: crea la cuenta CORREO_ORIGEN en el panel (Mail). */

function fmt_cop(float $n): string {
    return '$' . number_format($n, 0, ',', '.');
}

function site_url_base(): string {
    if (defined('SITE_URL') && SITE_URL !== '') {
        return rtrim(SITE_URL, '/');
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'calleavesyflores.manizalescomparte.com';
    return 'https://' . $host;
}

function mural_para_correo(string $muralNum): ?array {
    $num = str_pad(preg_replace('/\D/', '', $muralNum), 2, '0', STR_PAD_LEFT);
    if ($num === '00') {
        return null;
    }
    $st = db()->prepare('SELECT num, nombre, cientifico, color, img FROM murales WHERE num = ? LIMIT 1');
    $st->execute([$num]);
    $row = $st->fetch();
    return $row ?: null;
}

function url_imagen_mural(string $muralNum): ?string {
    $num = str_pad(preg_replace('/\D/', '', $muralNum), 2, '0', STR_PAD_LEFT);
    if ($num === '00' || !mural_para_correo($num)) {
        return null;
    }
    return site_url_base() . '/api.php?action=mural_img&num=' . rawurlencode($num);
}

function data_uri_a_imagen(string $dataUri): ?array {
    if (!preg_match('#^data:image/(jpeg|png|webp|gif);base64,(.+)$#s', $dataUri, $m)) {
        return null;
    }
    $bin = base64_decode($m[2], true);
    if ($bin === false) {
        return null;
    }
    $type = $m[1];
    return [
        'mime' => 'image/' . ($type === 'jpeg' ? 'jpeg' : $type),
        'data' => $bin,
        'ext'  => $type === 'jpeg' ? 'jpg' : $type,
    ];
}

function optimizar_imagen_correo(string $binary, int $maxW = 560): ?array {
    if (!function_exists('imagecreatefromstring')) {
        return ['mime' => 'image/jpeg', 'data' => $binary, 'ext' => 'jpg'];
    }
    $img = @imagecreatefromstring($binary);
    if (!$img) {
        return ['mime' => 'image/jpeg', 'data' => $binary, 'ext' => 'jpg'];
    }
    $w = imagesx($img);
    $h = imagesy($img);
    if ($w > $maxW) {
        $nh = max(1, (int) round($h * ($maxW / $w)));
        $out = imagecreatetruecolor($maxW, $nh);
        imagecopyresampled($out, $img, 0, 0, 0, 0, $maxW, $nh, $w, $h);
        imagedestroy($img);
        $img = $out;
    }
    ob_start();
    imagejpeg($img, null, 82);
    $data = ob_get_clean();
    imagedestroy($img);
    return ['mime' => 'image/jpeg', 'data' => $data, 'ext' => 'jpg'];
}

function servir_imagen_mural(string $num): void {
    $mural = mural_para_correo($num);
    if (!$mural || empty($mural['img'])) {
        http_response_code(404);
        exit;
    }
    $raw = data_uri_a_imagen($mural['img']);
    if (!$raw) {
        http_response_code(404);
        exit;
    }
    $opt = optimizar_imagen_correo($raw['data']);
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=604800');
    header('Content-Length: ' . strlen($opt['data']));
    echo $opt['data'];
    exit;
}

function esc_html(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fila_correo(string $label, string $valor): string {
    return '<tr>
      <td style="padding:10px 0;border-bottom:1px solid #E8E4DC;color:#98999C;font-size:13px;width:42%;vertical-align:top;">'
        . esc_html($label) . '</td>
      <td style="padding:10px 0;border-bottom:1px solid #E8E4DC;color:#1B1B1F;font-size:14px;font-weight:500;vertical-align:top;">'
        . $valor . '</td>
    </tr>';
}

function html_solicitud_email(array $b, string $numero, string $fecha, ?array $mural, ?string $urlImagen): string {
    $fmt = 'fmt_cop';
    $codigoTxt = ($b['codigo'] ?? '')
        ? esc_html($b['codigo']) . ' (' . (int) ($b['codigoPct'] ?? 0) . '%) · ' . esc_html($fmt((float) ($b['dcto'] ?? 0)))
        : 'No aplicó';
    $correoCliente = esc_html($b['correo']);
    $muralNombre = esc_html(($b['muralNum'] ?? '') . ' · ' . ($b['muralNombre'] ?? ''));
    $cientifico = $mural ? esc_html($mural['cientifico'] ?? '') : '';
    $accent = $mural && preg_match('/^#[0-9A-Fa-f]{6}$/', $mural['color'] ?? '') ? $mural['color'] : '#52B9AA';

    $bloqueImagen = $urlImagen
        ? '<img src="' . esc_html($urlImagen) . '" alt="Mural seleccionado" width="520" style="display:block;width:100%;max-width:520px;height:auto;border-radius:14px;border:0;">'
        : '<div style="background:linear-gradient(135deg,#0B1530,#121E42);border-radius:14px;padding:48px 24px;text-align:center;color:#FFD122;font-size:18px;">🎨 ' . $muralNombre . '</div>';

    return '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#ECE8E0;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#ECE8E0;padding:32px 16px;">
    <tr><td align="center">
      <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;">
        <tr><td style="background:#000;border-radius:18px 18px 0 0;padding:28px 32px;text-align:center;">
          <div style="font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#52B9AA;font-weight:bold;margin-bottom:8px;">Manizales Comparte</div>
          <div style="font-size:22px;font-weight:bold;color:#FFD122;line-height:1.2;">La Calle de las Aves y las Flores</div>
          <div style="font-size:13px;color:rgba(255,255,255,.75);margin-top:8px;">Nueva solicitud de cotización</div>
        </td></tr>
        <tr><td style="background:#F5F1E9;padding:24px 32px 0;">
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
            <tr><td style="background:#0B1530;border-radius:12px;padding:16px 20px;">
              <div style="font-size:11px;color:#52B9AA;letter-spacing:2px;text-transform:uppercase;">N° cotización</div>
              <div style="font-size:20px;font-weight:bold;color:#FFD122;margin-top:4px;">' . esc_html($numero) . '</div>
              <div style="font-size:13px;color:rgba(255,255,255,.7);margin-top:4px;">' . esc_html($fecha) . '</div>
            </td></tr>
          </table>
        </td></tr>
        <tr><td style="background:#F5F1E9;padding:24px 32px 0;">
          <div style="border-radius:14px;overflow:hidden;box-shadow:0 12px 40px rgba(11,21,48,.15);border:3px solid ' . esc_html($accent) . ';">' . $bloqueImagen . '</div>
          <div style="margin-top:14px;">
            <div style="font-size:11px;letter-spacing:2px;color:#52B9AA;text-transform:uppercase;font-weight:bold;">Mural elegido</div>
            <div style="font-size:18px;font-weight:bold;color:#1B1B1F;margin-top:4px;">' . $muralNombre . '</div>'
            . ($cientifico ? '<div style="font-size:13px;color:#98999C;font-style:italic;margin-top:2px;">' . $cientifico . '</div>' : '') .
          '</div>
        </td></tr>
        <tr><td style="background:#F5F1E9;padding:28px 32px 0;">
          <div style="font-size:12px;letter-spacing:2px;color:#52B9AA;text-transform:uppercase;font-weight:bold;margin-bottom:12px;">Datos del solicitante</div>
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0">'
            . fila_correo('Nombre', esc_html($b['nombre']))
            . fila_correo('Identificación', esc_html($b['identificacion']))
            . fila_correo('Negocio', esc_html($b['negocio']))
            . fila_correo('Dirección', esc_html($b['direccion']))
            . fila_correo('Teléfono', esc_html($b['telefono']))
            . fila_correo('Correo', '<a href="mailto:' . $correoCliente . '" style="color:#52B9AA;">' . $correoCliente . '</a>')
          . '</table>
        </td></tr>
        <tr><td style="background:#F5F1E9;padding:28px 32px;">
          <div style="font-size:12px;letter-spacing:2px;color:#52B9AA;text-transform:uppercase;font-weight:bold;margin-bottom:12px;">Detalle de la cotización</div>
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0">'
            . fila_correo('Medidas', esc_html($b['ancho'] . ' m × ' . $b['alto'] . ' m (' . $b['m2'] . ' m²)'))
            . fila_correo('Valor total', esc_html($fmt((float) ($b['total'] ?? 0))))
            . fila_correo('Apoyo Manizales Comparte', esc_html($fmt((float) ($b['aComparte'] ?? 0))))
            . fila_correo('Apoyo institucional', esc_html($fmt((float) ($b['aInst'] ?? 0))))
            . fila_correo('Código descuento', $codigoTxt)
          . '</table>
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:20px;">
            <tr><td style="background:linear-gradient(135deg,#E6323C,#c42830);border-radius:14px;padding:20px 24px;text-align:center;">
              <div style="font-size:11px;color:rgba(255,255,255,.85);letter-spacing:2px;text-transform:uppercase;">Valor a pagar</div>
              <div style="font-size:32px;font-weight:bold;color:#fff;margin-top:6px;">' . esc_html($fmt((float) ($b['pagar'] ?? 0))) . '</div>
            </td></tr>
          </table>
        </td></tr>
        <tr><td style="background:#0B1530;border-radius:0 0 18px 18px;padding:24px 32px;text-align:center;">
          <div style="font-size:12px;color:rgba(255,255,255,.55);line-height:1.6;">Responde a este correo para contactar al cliente.<br>La Calle de las Aves y las Flores · Manizales Comparte</div>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>';
}

function texto_solicitud_email(array $b, string $numero, string $fecha): string {
    $fmt = 'fmt_cop';
    return implode("\n", [
        'Nueva solicitud · La Calle de las Aves y las Flores', '',
        "N° cotización: $numero", "Fecha: $fecha", '',
        "Nombre: {$b['nombre']}", "Identificación: {$b['identificacion']}",
        "Negocio: {$b['negocio']}", "Dirección: {$b['direccion']}",
        "Teléfono: {$b['telefono']}", "Correo: {$b['correo']}", '',
        "Mural: {$b['muralNum']} · {$b['muralNombre']}",
        "Medidas: {$b['ancho']} m x {$b['alto']} m ({$b['m2']} m²)",
        'Valor total: ' . $fmt((float) ($b['total'] ?? 0)),
        'Apoyo Manizales Comparte: ' . $fmt((float) ($b['aComparte'] ?? 0)),
        'Apoyo institucional: ' . $fmt((float) ($b['aInst'] ?? 0)),
        'Código: ' . (($b['codigo'] ?? '') ? $b['codigo'] . ' (' . ($b['codigoPct'] ?? 0) . '%)' : 'No aplicó'),
        'VALOR A PAGAR: ' . $fmt((float) ($b['pagar'] ?? 0)),
    ]);
}

function enviar_correo_plano(string $destino, string $asunto, string $cuerpo, ?string $replyTo = null): bool {
    if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $from = CORREO_ORIGEN;
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    ini_set('sendmail_from', $from);
    $headers = [
        'From: La Calle de las Aves <' . $from . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    ];
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }
    $asuntoEnc = '=?UTF-8?B?' . base64_encode($asunto) . '?=';
    return mail($destino, $asuntoEnc, $cuerpo, implode("\r\n", $headers), '-f' . $from);
}

function enviar_correo_html(string $destino, string $asunto, string $html, string $plain, ?string $replyTo = null): bool {
    if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
        error_log('aves-mc: destino de correo inválido');
        return false;
    }
    $from = CORREO_ORIGEN;
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        error_log('aves-mc: CORREO_ORIGEN inválido — revisa config.php');
        return false;
    }

    ini_set('sendmail_from', $from);
    $boundary = 'b_' . bin2hex(random_bytes(8));
    $headers = [
        'From: La Calle de las Aves <' . $from . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    $body  = '--' . $boundary . "\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $plain . "\r\n\r\n";
    $body .= '--' . $boundary . "\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $html . "\r\n\r\n";
    $body .= '--' . $boundary . "--\r\n";

    $asuntoEnc = '=?UTF-8?B?' . base64_encode($asunto) . '?=';
    $ok = mail($destino, $asuntoEnc, $body, implode("\r\n", $headers), '-f' . $from);
    if (!$ok) {
        error_log('aves-mc: mail() HTML falló hacia ' . $destino);
    }
    return $ok;
}

function notificar_solicitud(array $b, string $numero, string $fecha): bool {
    $destino = cfg_get('correoDestino', CORREO_DESTINO);
    $asunto  = 'Nueva solicitud de mural · ' . $b['negocio'] . ' · ' . $numero;
    $mural   = mural_para_correo((string) ($b['muralNum'] ?? ''));
    $urlImg  = url_imagen_mural((string) ($b['muralNum'] ?? ''));
    $plain   = texto_solicitud_email($b, $numero, $fecha);
    $html    = html_solicitud_email($b, $numero, $fecha, $mural, $urlImg);

    $ok = enviar_correo_html($destino, $asunto, $html, $plain, $b['correo'] ?? null);
    if (!$ok) {
        error_log('aves-mc: reintento con correo texto plano');
        $ok = enviar_correo_plano($destino, $asunto, $plain, $b['correo'] ?? null);
    }
    return $ok;
}
