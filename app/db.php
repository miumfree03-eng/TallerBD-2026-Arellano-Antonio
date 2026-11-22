<?php
// Conexión a MariaDB con PDO.
// - ERRMODE_EXCEPTION: cualquier error de SQL lanza una excepción (no falla en silencio).
// - EMULATE_PREPARES false: las consultas preparadas las resuelve MariaDB (protege contra inyección SQL).
// - utf8mb4: acentos y ñ correctos.

function db(): PDO
{
    static $pdo = null;              // una sola conexión por petición
    if ($pdo === null) {
        $cfg = require __DIR__ . '/../config/config.php';
        $dsn = "mysql:host={$cfg['db_host']};port={$cfg['db_port']};dbname={$cfg['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// Atajo para consultas parametrizadas: consulta('SELECT ... WHERE id = ?', [$id])
function consulta(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
