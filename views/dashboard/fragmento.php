<?php
/**
 * Desffrut — Servidor de Fragmentos do Dashboard
 * Retorna HTML puro (sem layout) para ser injetado via AJAX no dashboard.
 *
 * Query params:
 *   frag      — ID do fragmento (whitelist abaixo)
 *   titulo    — Título do módulo (usado pelo placeholder)
 *   descricao — Descrição (usado pelo placeholder)
 *   fase      — Número da fase (usado pelo placeholder)
 */
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/config/database.php';
iniciar_sessao();

$u = usuario_logado();
if (!$u) {
    http_response_code(401);
    echo '<div class="alert alert-warning m-3">Sessão expirada. <a href="' . BASE_PATH . '/login">Faça login</a>.</div>';
    exit;
}

$frag = trim($_GET['frag'] ?? '');

// ── Whitelist: fragmento → roles permitidos ───────────────────────────────────
$catalogo = [
    'produtos'    => ['super_admin', 'gerente'],
    'estoque'     => ['super_admin', 'gerente'],
    'quebras'     => ['super_admin', 'gerente'],
    'relatorios'       => ['super_admin', 'gerente', 'rh_financeiro'],
    'vendas_relatorio' => ['super_admin', 'gerente', 'rh_financeiro'],
    'caixa'       => ['super_admin', 'gerente', 'rh_financeiro'],   // Fase 4
    'ceasa'       => ['super_admin', 'gerente'],                     // Fase 4
    'hardware'    => ['super_admin', 'gerente'],                                              // Fase 5
    'pedidos'     => ['super_admin', 'gerente', 'caixa', 'entregador'],                       // Fase 6
    'rh'          => ['super_admin', 'rh_financeiro'],                                        // Fase 7
    'financeiro'  => ['super_admin', 'rh_financeiro'],                                        // Fase 7
    'bi'          => ['super_admin', 'gerente', 'rh_financeiro'],                              // Fase 7
    'auditoria'      => ['super_admin'],                                                       // Fase 7
    'caixas_abertos' => ['super_admin', 'dev_admin'],                                          // Categoria 19
    'cms_identidade' => ['super_admin', 'gerente'],                                            // Fase 8
    'cms_banners'    => ['super_admin', 'gerente'],                                            // Fase 8
    'cms_lojas'      => ['super_admin', 'gerente'],                                            // Fase 8
    'cms_campanhas'  => ['super_admin', 'gerente'],                                            // Fase 8
    'crm'            => ['super_admin', 'gerente'],                                            // Fase 10
    'adtech'         => ['super_admin', 'gerente'],                                            // Fase 12
    'placeholder' => ['super_admin', 'gerente', 'caixa', 'entregador', 'rh_financeiro'],
];

if (!array_key_exists($frag, $catalogo)) {
    http_response_code(404);
    echo '<div class="alert alert-danger m-3">Fragmento não encontrado.</div>';
    exit;
}

if (!in_array($u['role'], $catalogo[$frag], true)) {
    http_response_code(403);
    echo '<div class="alert alert-warning m-3">Acesso não permitido para este módulo.</div>';
    exit;
}

// ── Inclui o fragmento ────────────────────────────────────────────────────────
require __DIR__ . '/fragmentos/' . $frag . '.php';
