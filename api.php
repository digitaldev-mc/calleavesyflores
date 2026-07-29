<?php
/* =====================================================================
   API · La Calle de las Aves y las Flores
   Endpoint único. No necesitas editar nada aquí.
   ===================================================================== */
require_once __DIR__ . '/db.php';

session_start();

$accion = $_GET['action'] ?? '';

/* Lee el cuerpo JSON de las peticiones POST. */
function cuerpo() {
    static $b = null;
    if ($b === null) {
        $raw = file_get_contents('php://input');
        $b = $raw ? (json_decode($raw, true) ?: []) : [];
    }
    return $b;
}

/* ¿La sesión actual es de administrador? */
function es_admin() {
    return !empty($_SESSION['admin']);
}

/* Exige sesión de administrador + token CSRF válido. */
function exigir_admin() {
    if (!es_admin()) responder(['error' => 'No autorizado.'], 401);
    $b = cuerpo();
    $token = $b['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$token)) {
        responder(['error' => 'Token de seguridad inválido. Recarga la página e inténtalo de nuevo.'], 403);
    }
}

/* Arma el bloque de contenido público (lo que ve la página). */
function contenido_publico() {
    $pdo = db();
    $murales = $pdo->query('SELECT id, num, nombre, cientifico, descripcion AS `desc`, tags, color, img, seleccionado_por AS seleccionadoPor FROM murales ORDER BY orden, id')->fetchAll();
    foreach ($murales as &$m) { $m['tags'] = $m['tags'] ? explode('||', $m['tags']) : []; }
    unset($m);

    $aliados = $pdo->query('SELECT id, nombre, img FROM aliados ORDER BY orden, id')->fetchAll();
    $videos  = array_map(fn($r) => $r['youtube_id'], $pdo->query('SELECT youtube_id FROM videos ORDER BY orden, id')->fetchAll());
    $resenas = $pdo->query('SELECT id, texto, quien, negocio, estrellas FROM resenas ORDER BY orden, id')->fetchAll();
    foreach ($resenas as &$r) { $r['estrellas'] = (int)$r['estrellas']; }
    unset($r);

    $codigos = [];
    foreach ($pdo->query('SELECT codigo, pct FROM codigos')->fetchAll() as $c) {
        $codigos[$c['codigo']] = (int)$c['pct'];
    }

    return [
        'config' => [
            'precioM2'          => (float) cfg_get('precioM2', 250000),
            'pctComparte'       => (float) cfg_get('pctComparte', 15),
            'pctInstitucional'  => (float) cfg_get('pctInstitucional', 15),
            'correoDestino'     => cfg_get('correoDestino', CORREO_DESTINO),
            'condiciones'       => cfg_get('condiciones', ''),
        ],
        'murales'  => $murales,
        'aliados'  => $aliados,
        'videos'   => $videos,
        'resenas'  => $resenas,
        'codigos'  => $codigos,
    ];
}

try {
    switch ($accion) {

        /* ---------- PÚBLICO ---------- */
        case 'content':
            responder(contenido_publico());
            break;

        case 'session':
            if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
            responder(['admin' => es_admin(), 'csrf' => $_SESSION['csrf']]);
            break;

        case 'login': {
            $b = cuerpo();
            $pass = (string)($b['password'] ?? '');
            $hash = cfg_get('admin_hash', '');
            if ($hash && password_verify($pass, $hash)) {
                $_SESSION['admin'] = true;
                $_SESSION['csrf']  = bin2hex(random_bytes(16));
                responder(['ok' => true, 'csrf' => $_SESSION['csrf']]);
            }
            usleep(400000); // pequeña demora contra fuerza bruta
            responder(['error' => 'Contraseña incorrecta.'], 401);
            break;
        }

        case 'logout':
            $_SESSION = [];
            session_destroy();
            responder(['ok' => true]);
            break;

        case 'solicitud': {
            $b = cuerpo();
            if (!empty($b['website'])) responder(['ok' => true]); // honeypot: bot detectado, ignorar
            $req = ['nombre','identificacion','negocio','direccion','telefono','correo','muralNum','muralNombre'];
            foreach ($req as $k) {
                if (empty(trim((string)($b[$k] ?? '')))) responder(['error' => 'Faltan datos obligatorios.'], 422);
            }
            if (!filter_var($b['correo'], FILTER_VALIDATE_EMAIL)) responder(['error' => 'Correo no válido.'], 422);

            $numero = 'COT-' . date('Ymd') . '-' . str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
            $fecha  = date('j') . ' de ' . ['','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'][(int)date('n')] . ' de ' . date('Y');

            $st = db()->prepare('INSERT INTO solicitudes
                (numero, fecha, nombre, identificacion, negocio, direccion, telefono, correo,
                 mural_num, mural_nombre, ancho, alto, m2, total, a_comparte, a_inst, codigo, codigo_pct, dcto, pagar)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $st->execute([
                $numero, $fecha,
                trim($b['nombre']), trim($b['identificacion']), trim($b['negocio']), trim($b['direccion']),
                trim($b['telefono']), trim($b['correo']),
                $b['muralNum'], $b['muralNombre'],
                (float)($b['ancho'] ?? 0), (float)($b['alto'] ?? 0), (float)($b['m2'] ?? 0),
                (float)($b['total'] ?? 0), (float)($b['aComparte'] ?? 0), (float)($b['aInst'] ?? 0),
                (string)($b['codigo'] ?? ''), (int)($b['codigoPct'] ?? 0),
                (float)($b['dcto'] ?? 0), (float)($b['pagar'] ?? 0),
            ]);

            // Envío del correo al equipo
            $fmt = fn($n) => '$' . number_format((float)$n, 0, ',', '.');
            $destino = cfg_get('correoDestino', CORREO_DESTINO);
            $asunto  = 'Nueva solicitud de mural · ' . $b['negocio'] . ' · ' . $numero;
            $lineas = [
                "Nueva solicitud desde la página La Calle de las Aves y las Flores", "",
                "N° cotización: $numero", "Fecha: $fecha", "",
                "Nombre: {$b['nombre']}", "Identificación: {$b['identificacion']}",
                "Negocio: {$b['negocio']}", "Dirección: {$b['direccion']}",
                "Teléfono: {$b['telefono']}", "Correo del cliente: {$b['correo']}", "",
                "Mural elegido: {$b['muralNum']} · {$b['muralNombre']}",
                "Medidas: {$b['ancho']} m x {$b['alto']} m ({$b['m2']} m²)",
                "Valor total: " . $fmt($b['total'] ?? 0),
                "Apoyo Manizales Comparte: " . $fmt($b['aComparte'] ?? 0),
                "Apoyo institucional: " . $fmt($b['aInst'] ?? 0),
                "Código: " . (($b['codigo'] ?? '') ? $b['codigo'] . ' (' . ($b['codigoPct'] ?? 0) . '%) = ' . $fmt($b['dcto'] ?? 0) : 'No aplicó'),
                "VALOR A PAGAR: " . $fmt($b['pagar'] ?? 0),
            ];
            $cuerpoMail = implode("\n", $lineas);
            $headers  = 'From: La Calle de las Aves <' . CORREO_ORIGEN . ">\r\n";
            $headers .= 'Reply-To: ' . $b['correo'] . "\r\n";
            $headers .= "Content-Type: text/plain; charset=utf-8\r\n";
            @mail($destino, $asunto, $cuerpoMail, $headers);

            responder(['ok' => true, 'numero' => $numero, 'fecha' => $fecha]);
            break;
        }

        /* ---------- ADMINISTRACIÓN ---------- */
        case 'solicitudes_list':
            exigir_admin();
            responder(['solicitudes' => db()->query(
                'SELECT id, numero, fecha, nombre, identificacion, negocio, direccion, telefono, correo,
                        mural_num AS muralNum, mural_nombre AS muralNombre, ancho, alto, m2, total,
                        a_comparte AS aComparte, a_inst AS aInst, codigo, codigo_pct AS codigoPct, dcto, pagar
                 FROM solicitudes ORDER BY id DESC')->fetchAll()]);
            break;

        case 'solicitud_delete':
            exigir_admin();
            db()->prepare('DELETE FROM solicitudes WHERE id = ?')->execute([(int)(cuerpo()['id'] ?? 0)]);
            responder(['ok' => true]);
            break;

        case 'mural_add': {
            exigir_admin();
            $b = cuerpo();
            if (empty(trim($b['nombre'] ?? '')) || empty($b['img'] ?? '')) responder(['error' => 'La imagen y el nombre son obligatorios.'], 422);
            $n = (int) db()->query('SELECT COUNT(*) c FROM murales')->fetch()['c'] + 1;
            $st = db()->prepare('INSERT INTO murales (num, nombre, cientifico, descripcion, tags, color, img, seleccionado_por, orden)
                                 VALUES (?,?,?,?,?,?,?,"",?)');
            $tags = implode('||', array_slice(array_filter(array_map('trim', explode(',', $b['tags'] ?? ''))), 0, 3));
            $st->execute([
                str_pad((string)$n, 2, '0', STR_PAD_LEFT),
                trim($b['nombre']), trim($b['cientifico'] ?? ''),
                trim($b['desc'] ?? '') ?: 'Nuevo diseño disponible para la Calle de las Aves y las Flores.',
                $tags, $b['color'] ?? '#52B9AA', $b['img'], $n,
            ]);
            responder(['ok' => true]);
            break;
        }

        case 'mural_marcar': {
            exigir_admin();
            $b = cuerpo();
            $negocio = trim($b['negocio'] ?? '');
            if ($negocio === '') responder(['error' => 'Escribe el nombre del negocio.'], 422);
            db()->prepare('UPDATE murales SET seleccionado_por = ? WHERE id = ?')->execute([$negocio, (int)($b['id'] ?? 0)]);
            responder(['ok' => true]);
            break;
        }

        case 'mural_reabrir':
            exigir_admin();
            db()->prepare('UPDATE murales SET seleccionado_por = "" WHERE id = ?')->execute([(int)(cuerpo()['id'] ?? 0)]);
            responder(['ok' => true]);
            break;

        case 'mural_delete':
            exigir_admin();
            db()->prepare('DELETE FROM murales WHERE id = ?')->execute([(int)(cuerpo()['id'] ?? 0)]);
            responder(['ok' => true]);
            break;

        case 'aliado_add': {
            exigir_admin();
            $b = cuerpo();
            if (empty($b['img'] ?? '')) responder(['error' => 'Selecciona el logo.'], 422);
            db()->prepare('INSERT INTO aliados (nombre, img, orden) VALUES (?,?,?)')
                ->execute([trim($b['nombre'] ?? '') ?: 'Empresa aliada', $b['img'], time()]);
            responder(['ok' => true]);
            break;
        }

        case 'aliado_delete':
            exigir_admin();
            db()->prepare('DELETE FROM aliados WHERE id = ?')->execute([(int)(cuerpo()['id'] ?? 0)]);
            responder(['ok' => true]);
            break;

        case 'video_add': {
            exigir_admin();
            $id = trim(cuerpo()['youtube_id'] ?? '');
            if ($id === '') responder(['error' => 'Pega la URL o el ID del video.'], 422);
            db()->prepare('INSERT INTO videos (youtube_id, orden) VALUES (?,?)')->execute([$id, time()]);
            responder(['ok' => true]);
            break;
        }

        case 'video_delete':
            exigir_admin();
            db()->prepare('DELETE FROM videos WHERE youtube_id = ?')->execute([trim(cuerpo()['youtube_id'] ?? '')]);
            responder(['ok' => true]);
            break;

        case 'resena_add': {
            exigir_admin();
            $b = cuerpo();
            if (empty(trim($b['texto'] ?? ''))) responder(['error' => 'Escribe el texto de la reseña.'], 422);
            db()->prepare('INSERT INTO resenas (texto, quien, negocio, estrellas, orden) VALUES (?,?,?,?,?)')
                ->execute([
                    trim($b['texto']),
                    trim($b['quien'] ?? '') ?: 'Comerciante',
                    trim($b['negocio'] ?? '') ?: 'Establecimiento de la calle',
                    max(1, min(5, (int)($b['estrellas'] ?? 5))),
                    time(),
                ]);
            responder(['ok' => true]);
            break;
        }

        case 'resena_delete':
            exigir_admin();
            db()->prepare('DELETE FROM resenas WHERE id = ?')->execute([(int)(cuerpo()['id'] ?? 0)]);
            responder(['ok' => true]);
            break;

        case 'codigo_add': {
            exigir_admin();
            $b = cuerpo();
            $cod = strtoupper(trim($b['codigo'] ?? ''));
            $pct = (int)($b['pct'] ?? 0);
            if ($cod === '' || $pct < 1 || $pct > 100) responder(['error' => 'Código o porcentaje no válido.'], 422);
            db()->prepare('INSERT INTO codigos (codigo, pct) VALUES (?,?)
                           ON DUPLICATE KEY UPDATE pct = VALUES(pct)')->execute([$cod, $pct]);
            responder(['ok' => true]);
            break;
        }

        case 'codigo_delete':
            exigir_admin();
            db()->prepare('DELETE FROM codigos WHERE codigo = ?')->execute([strtoupper(trim(cuerpo()['codigo'] ?? ''))]);
            responder(['ok' => true]);
            break;

        case 'config_save': {
            exigir_admin();
            $b = cuerpo();
            cfg_set('precioM2',         (string)max(0, (float)($b['precioM2'] ?? 250000)));
            cfg_set('pctComparte',      (string)max(0, min(100, (float)($b['pctComparte'] ?? 15))));
            cfg_set('pctInstitucional', (string)max(0, min(100, (float)($b['pctInstitucional'] ?? 15))));
            cfg_set('correoDestino',    trim($b['correoDestino'] ?? '') ?: CORREO_DESTINO);
            cfg_set('condiciones',      trim($b['condiciones'] ?? ''));
            if (!empty(trim($b['nuevaPass'] ?? ''))) {
                cfg_set('admin_hash', password_hash(trim($b['nuevaPass']), PASSWORD_DEFAULT));
            }
            responder(['ok' => true]);
            break;
        }

        default:
            responder(['error' => 'Acción no reconocida.'], 404);
    }
} catch (Throwable $e) {
    responder(['error' => 'Ocurrió un error en el servidor.'], 500);
}
