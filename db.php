<?php
/* Conexión a la base de datos y utilidades. No necesitas editar nada aquí. */
require_once __DIR__ . '/config.php';

function db() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'No se pudo conectar a la base de datos. Revisa config.php.']);
            exit;
        }
    }
    return $pdo;
}

/* Devuelve un valor de la tabla config, con valor por defecto. */
function cfg_get($clave, $default = null) {
    $st = db()->prepare('SELECT valor FROM config WHERE clave = ?');
    $st->execute([$clave]);
    $row = $st->fetch();
    return $row ? $row['valor'] : $default;
}

/* Guarda (inserta o actualiza) un valor en la tabla config. */
function cfg_set($clave, $valor) {
    $st = db()->prepare('INSERT INTO config (clave, valor) VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
    $st->execute([$clave, $valor]);
}

/* Responde JSON y termina. */
function responder($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
