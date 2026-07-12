<?php
/**
 * Desffrut — Conexão com o banco de dados (PDO)
 * Lê as credenciais do arquivo db.credentials.php (não rastreado pelo git).
 */

require_once __DIR__ . '/config.php';

$credentials_file = __DIR__ . '/db.credentials.php';

if (!file_exists($credentials_file)) {
    $msg = AMBIENTE === 'local'
        ? 'Arquivo de credenciais não encontrado. Copie db.credentials.exemplo.php para db.credentials.php e configure.'
        : 'Erro de configuração do servidor. Contate o administrador.';
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => $msg]));
}

require_once $credentials_file;

/**
 * Retorna a conexão PDO (singleton por requisição).
 */
function db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $pdo;
}
