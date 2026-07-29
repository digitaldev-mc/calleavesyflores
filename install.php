<?php
if (!defined('AVES_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

function asegurar_instalacion() {
    $pdo = db();
    $pdo->exec('SET NAMES utf8mb4');

    $pdo->exec("CREATE TABLE IF NOT EXISTS config (
        clave VARCHAR(64) PRIMARY KEY,
        valor LONGTEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS murales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        num VARCHAR(8), nombre VARCHAR(160), cientifico VARCHAR(160),
        descripcion TEXT, tags VARCHAR(255), color VARCHAR(16),
        img LONGTEXT, seleccionado_por VARCHAR(160) DEFAULT '', orden INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS aliados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(160), img LONGTEXT, orden INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS videos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        youtube_id VARCHAR(64), orden INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS resenas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        texto TEXT, quien VARCHAR(160), negocio VARCHAR(160),
        estrellas TINYINT DEFAULT 5, orden INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS codigos (
        codigo VARCHAR(64) PRIMARY KEY, pct INT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS solicitudes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        numero VARCHAR(32), fecha VARCHAR(64),
        nombre VARCHAR(160), identificacion VARCHAR(64), negocio VARCHAR(160),
        direccion VARCHAR(255), telefono VARCHAR(64), correo VARCHAR(160),
        mural_num VARCHAR(8), mural_nombre VARCHAR(160),
        ancho DECIMAL(10,2), alto DECIMAL(10,2), m2 DECIMAL(12,2),
        total DECIMAL(14,2), a_comparte DECIMAL(14,2), a_inst DECIMAL(14,2),
        codigo VARCHAR(64), codigo_pct INT, dcto DECIMAL(14,2), pagar DECIMAL(14,2),
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (cfg_get('instalado') === '1') {
        return;
    }

    $seed = require __DIR__ . '/seed_data.php';

    cfg_set('admin_hash', password_hash(ADMIN_PASSWORD_INICIAL, PASSWORD_DEFAULT));
    cfg_set('precioM2', '250000');
    cfg_set('pctComparte', '15');
    cfg_set('pctInstitucional', '15');
    cfg_set('correoDestino', CORREO_DESTINO);
    cfg_set('condiciones', $seed['condiciones']);

    if ((int) $pdo->query('SELECT COUNT(*) c FROM murales')->fetch()['c'] === 0) {
        $st = $pdo->prepare('INSERT INTO murales (orden, num, nombre, cientifico, descripcion, tags, color, img, seleccionado_por)
                             VALUES (?,?,?,?,?,?,?,?,"")');
        foreach ($seed['murales'] as $m) {
            $st->execute($m);
        }
    }

    if ((int) $pdo->query('SELECT COUNT(*) c FROM resenas')->fetch()['c'] === 0) {
        $st = $pdo->prepare('INSERT INTO resenas (texto, quien, negocio, estrellas, orden) VALUES (?,?,?,?,?)');
        foreach ($seed['resenas'] as $r) {
            $st->execute($r);
        }
    }

    $stCod = $pdo->prepare('INSERT IGNORE INTO codigos (codigo, pct) VALUES (?, ?)');
    foreach ($seed['codigos'] as $c) {
        $stCod->execute($c);
    }

    cfg_set('instalado', '1');
}
