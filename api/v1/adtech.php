<?php
if (!function_exists('api_auth_exigir')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Acesso direto não permitido.']);
    exit;
}

require_once __DIR__ . '/../../app/middleware/modo_restrito.php'; // Categoria 22
/**
 * Desffrut — API v1: AdTech & Marketing (Fase 12)
 *
 * GET  /adtech/roi          → ROI por UTM campaign (super_admin, gerente)
 * GET  /adtech/indicacoes   → Ranking de indicadores (super_admin)
 * GET  /adtech/meu-codigo   → Gera/retorna código de indicação do cliente logado
 * POST /adtech/config       → Salva pixel_meta_id e gtag_id (super_admin)
 */

$u   = api_auth_exigir();
$pdo = db();
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ─── GET /adtech/roi — ROI por campanha UTM ──────────────────────────────────
if ($id === 'roi' && $method === 'GET') {
    api_auth_role(['super_admin', 'gerente']);

    $inicio = sanitize($_GET['inicio'] ?? date('Y-m-01'));
    $fim    = sanitize($_GET['fim']    ?? date('Y-m-d'));

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(JSON_UNQUOTE(JSON_EXTRACT(origem_utm, '$.utm_source')),   '(direto)') AS utm_source,
            COALESCE(JSON_UNQUOTE(JSON_EXTRACT(origem_utm, '$.utm_medium')),   '—')        AS utm_medium,
            COALESCE(JSON_UNQUOTE(JSON_EXTRACT(origem_utm, '$.utm_campaign')), '—')        AS utm_campaign,
            COUNT(*)      AS total_pedidos,
            SUM(total)    AS receita_total,
            AVG(total)    AS ticket_medio
        FROM pedidos
        WHERE status NOT IN ('cancelado')
          AND DATE(created_at) BETWEEN :inicio AND :fim
          AND origem_utm IS NOT NULL
        GROUP BY utm_source, utm_medium, utm_campaign
        ORDER BY receita_total DESC
        LIMIT 100
    ");
    $stmt->execute(['inicio' => $inicio, 'fim' => $fim]);
    json_response(['status' => 'ok', 'data' => $stmt->fetchAll()]);
}

// ─── GET /adtech/indicacoes — ranking de indicadores ─────────────────────────
if ($id === 'indicacoes' && $method === 'GET') {
    api_auth_role(['super_admin', 'gerente']);

    $stmt = $pdo->query("
        SELECT
            ind.id, ind.nome, ind.email,
            ind.codigo_indicacao,
            ind.pontos_fidelidade,
            COUNT(ind2.id)                       AS total_indicados,
            SUM(ind2.indicacao_bonus_pago)        AS bonus_pagos
        FROM usuarios ind
        LEFT JOIN usuarios ind2 ON ind2.indicado_por_id = ind.id
        WHERE ind.codigo_indicacao IS NOT NULL
          AND ind.role = 'cliente'
        GROUP BY ind.id
        ORDER BY total_indicados DESC, bonus_pagos DESC
        LIMIT 50
    ");
    json_response(['status' => 'ok', 'data' => $stmt->fetchAll()]);
}

// ─── GET /adtech/meu-codigo — código de indicação do cliente ─────────────────
if ($id === 'meu-codigo' && $method === 'GET') {
    // Acessível a qualquer role autenticado, mas faz sentido apenas para cliente
    $stmt = $pdo->prepare("
        SELECT codigo_indicacao,
               (SELECT COUNT(*) FROM usuarios WHERE indicado_por_id = :uid) AS total_indicados,
               (SELECT COUNT(*) FROM usuarios WHERE indicado_por_id = :uid AND indicacao_bonus_pago = 1) AS bonus_pagos
        FROM usuarios WHERE id = :uid2
    ");
    $stmt->execute(['uid' => $u['id'], 'uid2' => $u['id']]);
    $row = $stmt->fetch();

    $codigo = $row['codigo_indicacao'] ?? null;

    // Gera código se ainda não tiver
    if (!$codigo) {
        $chars  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $tentativas = 0;
        do {
            $tentativas++;
            $codigo = '';
            for ($i = 0; $i < 8; $i++) {
                $codigo .= $chars[random_int(0, strlen($chars) - 1)];
            }
            // Verifica colisão
            $chk = $pdo->prepare("SELECT id FROM usuarios WHERE codigo_indicacao = :c");
            $chk->execute(['c' => $codigo]);
        } while ($chk->fetch() && $tentativas < 20);

        $pdo->prepare("UPDATE usuarios SET codigo_indicacao = :c WHERE id = :uid")
            ->execute(['c' => $codigo, 'uid' => $u['id']]);
    }

    $pts_cfg = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'pontos_indicacao'")->fetchColumn();

    json_response([
        'status' => 'ok',
        'data'   => [
            'codigo'          => $codigo,
            'total_indicados' => (int) ($row['total_indicados'] ?? 0),
            'bonus_pagos'     => (int) ($row['bonus_pagos']     ?? 0),
            'pontos_por_indicacao' => (int) ($pts_cfg ?: 100),
        ],
    ]);
}

// ─── POST /adtech/config — salva IDs de Pixel/GA4 ────────────────────────────
if ($id === 'config' && $method === 'POST') {
    api_auth_role(['super_admin']);

    $campos = ['pixel_meta_id', 'gtag_id', 'pontos_indicacao'];
    $ins = $pdo->prepare("
        INSERT INTO configuracoes (chave, valor)
        VALUES (:k, :v)
        ON DUPLICATE KEY UPDATE valor = :v2
    ");
    foreach ($campos as $campo) {
        if (array_key_exists($campo, $body)) {
            $ins->execute(['k' => $campo, 'v' => sanitize($body[$campo]), 'v2' => sanitize($body[$campo])]);
        }
    }

    json_response(['status' => 'ok', 'message' => 'Configurações salvas.', 'data' => null]);
}

json_response(['status' => 'error', 'message' => 'Endpoint adtech não reconhecido.', 'data' => null], 404);
