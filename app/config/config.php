<?php
/**
 * Desffrut — Configurações globais do sistema
 * Fase 0: constantes de ambiente, limites e regras de negócio fixas.
 */

// ─── Identificação ────────────────────────────────────────────────────────────
define('NOME_SISTEMA',  'Desffrut');
define('VERSAO_SISTEMA', '1.0.0');

// ─── Ambiente ─────────────────────────────────────────────────────────────────
// O projeto roda em 3 cenários possíveis (ver app/config/db.credentials.php para
// as credenciais de banco de cada um):
//   1. local      — XAMPP (localhost / 127.0.0.1)
//   2. teste      — apresentação ao cliente em roberiodiogenes.online
//   3. definitivo — produção final em desffrut.com.br
// AMBIENTE é detectado automaticamente pelo domínio da requisição — não precisa
// editar nada aqui ao migrar de um cenário para outro.
$_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_host);

if ($_host === 'localhost' || $_host === '127.0.0.1' || str_contains($_host, 'localhost')) {
    define('AMBIENTE', 'local');
} elseif (str_contains($_host, 'roberiodiogenes.online')) {
    define('AMBIENTE', 'teste');
} else {
    define('AMBIENTE', 'definitivo'); // desffrut.com.br (produção final)
}

define('EM_PRODUCAO', AMBIENTE !== 'local'); // true em 'teste' e 'definitivo' (ex.: cookies seguros, HTTPS)

// URL canônica fixa do domínio definitivo — usada em SEO (sitemap, JSON-LD) para
// nunca publicar URLs do domínio de teste por engano.
define('URL_CANONICA_DEFINITIVO', 'https://desffrut.com.br');

// Caminho base relativo ao document root (ex: '/desffrut.com' no XAMPP, '' em produção)
// Usado para montar URLs absolutas de API no JS sem hardcodar o subdiretório.
$_doc  = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$_proj = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/');
define('BASE_PATH', str_replace($_doc, '', $_proj));   // ex: '/desffrut.com' ou ''
define('API_ROOT',  BASE_PATH . '/api/v1');            // ex: '/desffrut.com/api/v1'
unset($_doc, $_proj);

// ─── Fuso horário ─────────────────────────────────────────────────────────────
date_default_timezone_set('America/Fortaleza');

// ─── Upload de fotos de produtos ──────────────────────────────────────────────
define('MAX_UPLOAD_BYTES', 6 * 1024 * 1024);  // 6 MB (tamanho aceito no upload)
define('MAX_FOTO_KB',      90);                 // 90 KB (tamanho final após conversão WebP)
define('DIR_UPLOAD_PRODUTOS', __DIR__ . '/../../uploads/produtos/');

// ─── Programa de Fidelidade ───────────────────────────────────────────────────
define('PONTOS_POR_REAL',      1);     // R$ 1,00 = 1 ponto
define('REAIS_POR_100_PONTOS', 1.00); // 100 pontos = R$ 1,00 de desconto

// ─── PDV / Polling de status ──────────────────────────────────────────────────
define('POLLING_INTERVALO_MS', 20000); // 20 segundos (entre 15 e 30 s definidos no briefing)

// ─── Sessão ───────────────────────────────────────────────────────────────────
define('SESSION_NAME',   'desffrut_sess');
define('SESSION_EXPIRE', 60 * 60 * 8); // 8 horas
