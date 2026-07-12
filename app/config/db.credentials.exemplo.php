<?php
/**
 * EXEMPLO de credenciais do banco de dados — 3 ambientes do projeto Desffrut.
 *
 * INSTRUÇÕES:
 * 1. Copie este arquivo para:  app/config/db.credentials.php
 * 2. Deixe ativo (sem "//") apenas o bloco do ambiente onde essa cópia vai
 *    rodar, e comente os outros dois. Preencha os dados reais.
 * 3. O arquivo db.credentials.php está no .gitignore — nunca sobe para o
 *    GitHub. Cada servidor mantém sua própria cópia.
 *
 * Os 3 cenários do projeto:
 *   1. LOCAL      — XAMPP (localhost)
 *   2. TESTE      — apresentação ao cliente em roberiodiogenes.online
 *   3. DEFINITIVO — produção final em desffrut.com.br (HostGator)
 *
 * app/config/config.php detecta sozinho qual AMBIENTE está ativo a partir do
 * domínio da requisição — não é preciso configurar isso aqui.
 */

// ═══════════════════════════════════════════════════════════════════════════
// 1) LOCAL — XAMPP
//    host: localhost | user: root | pass: (vazio) | db: desffrut_dev
// ═══════════════════════════════════════════════════════════════════════════
define('DB_HOST', 'localhost');
define('DB_NAME', 'desffrut_dev');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ═══════════════════════════════════════════════════════════════════════════
// 2) TESTE — roberiodiogenes.online (dados fornecidos no painel dessa hospedagem)
// ═══════════════════════════════════════════════════════════════════════════
// define('DB_HOST', 'localhost');
// define('DB_NAME', 'SUBSTITUIR_banco_teste');
// define('DB_USER', 'SUBSTITUIR_usuario_teste');
// define('DB_PASS', 'SUBSTITUIR_senha_teste');
// define('DB_CHARSET', 'utf8mb4');

// ═══════════════════════════════════════════════════════════════════════════
// 3) DEFINITIVO — HostGator / desffrut.com.br (dados fornecidos no cPanel)
// ═══════════════════════════════════════════════════════════════════════════
// define('DB_HOST', 'localhost');
// define('DB_NAME', 'SUBSTITUIR_banco_definitivo');
// define('DB_USER', 'SUBSTITUIR_usuario_definitivo');
// define('DB_PASS', 'SUBSTITUIR_senha_definitiva');
// define('DB_CHARSET', 'utf8mb4');
