<?php
/**
 * Desffrut — API v1: Caixas
 *
 * GET  /api/v1/caixas?loja_id=X&status=aberto  → caixa aberto da loja
 * POST /api/v1/caixas                           → abrir caixa { loja_id, fundo_troco }
 * POST /api/v1/caixas/{id}/fechar               → fechar caixa { total_dinheiro }
 * POST /api/v1/caixas/{id}/sangria              → registrar sangria/suprimento { tipo, valor, justificativa }
 * GET  /api/v1/caixas/fechamentos               → histórico de fechamentos (dashboard módulo Caixa)
 * GET  /api/v1/caixas/sangrias                  → histórico de sangrias/suprimentos
 * GET  /api/v1/caixas/historico                 → auditoria de abertura/fechamento (quem abriu/fechou, diferença de caixa)
 *
 * Roles:
 *   caixa       → abre, fecha e registra sangrias — restrito à sua própria loja
 *   gerente     → abre, fecha e registra sangrias em QUALQUER loja (é responsável
 *                 por todas as lojas, não há um gerente por loja) + consulta histórico
 *   rh_financeiro → consulta histórico (view-only), todas as lojas
 *   super_admin → acesso total + pode editar/estornar, todas as lojas
 */

$u = api_auth_exigir();
api_auth_role(['caixa', 'gerente', 'super_admin', 'rh_financeiro']);

$pdo  = db();
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── GET /caixas/fechamentos ────────────────────────────────────────────────────
if ($id === 'fechamentos' && $method === 'GET') {
    api_auth_role(['gerente', 'super_admin', 'rh_financeiro']);

    $loja_id  = filter_input(INPUT_GET, 'loja_id',  FILTER_VALIDATE_INT) ?: null;
    $data_ini = sanitize($_GET['data_ini'] ?? date('Y-m-01'));
    $data_fim = sanitize($_GET['data_fim'] ?? date('Y-m-d'));

    // Gerente é responsável por todas as lojas (não há um gerente por loja) —
    // tem a mesma visão multi-loja do super_admin, com filtro opcional de loja_id.

    $where  = 'WHERE c.status = "fechado"';
    $params = [];
    if ($loja_id) { $where .= ' AND c.loja_id = :loja_id'; $params['loja_id'] = $loja_id; }
    $where .= ' AND DATE(c.aberto_em) BETWEEN :di AND :df';
    $params['di'] = $data_ini;
    $params['df'] = $data_fim;

    $stmt = $pdo->prepare("
        SELECT
            c.id, c.fundo_troco, c.aberto_em, c.fechado_em,
            l.nome AS loja_nome,
            u.nome AS operador_nome,
            COALESCE(SUM(v.total_final), 0) AS total_vendas,
            COUNT(v.id)                      AS qtd_vendas,
            COALESCE(
                (SELECT SUM(mv.valor) FROM movimentos_caixa mv
                 WHERE mv.caixa_id = c.id AND mv.tipo IN ('sangria')), 0
            ) AS total_sangrias,
            COALESCE(
                (SELECT SUM(mv.valor) FROM movimentos_caixa mv
                 WHERE mv.caixa_id = c.id AND mv.tipo IN ('suprimento')), 0
            ) AS total_suprimentos
        FROM caixas c
        JOIN lojas    l ON l.id = c.loja_id
        JOIN usuarios u ON u.id = c.usuario_id
        LEFT JOIN vendas v ON v.caixa_id = c.id AND v.status = 'finalizada'
        {$where}
        GROUP BY c.id
        ORDER BY c.aberto_em DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    json_response(['status' => 'ok', 'data' => $stmt->fetchAll(), 'message' => '']);
}

// ── GET /caixas/sangrias ───────────────────────────────────────────────────────
if ($id === 'sangrias' && $method === 'GET') {
    api_auth_role(['gerente', 'super_admin', 'rh_financeiro']);

    $loja_id  = filter_input(INPUT_GET, 'loja_id',  FILTER_VALIDATE_INT) ?: null;
    $data_ini = sanitize($_GET['data_ini'] ?? date('Y-m-01'));
    $data_fim = sanitize($_GET['data_fim'] ?? date('Y-m-d'));
    $tipo     = sanitize($_GET['tipo']     ?? '');

    // Gerente é responsável por todas as lojas — mesma visão multi-loja do super_admin.

    $where  = 'WHERE mc.tipo IN ("sangria","suprimento")';
    $params = [];
    if ($loja_id) { $where .= ' AND c.loja_id = :loja_id'; $params['loja_id'] = $loja_id; }
    if (in_array($tipo, ['sangria', 'suprimento'])) {
        $where .= ' AND mc.tipo = :tipo'; $params['tipo'] = $tipo;
    }
    $where .= ' AND DATE(mc.created_at) BETWEEN :di AND :df';
    $params['di'] = $data_ini;
    $params['df'] = $data_fim;

    $stmt = $pdo->prepare("
        SELECT
            mc.id, mc.tipo, mc.valor, mc.justificativa, mc.created_at,
            c.id   AS caixa_id, l.nome AS loja_nome,
            u.nome AS operador_nome
        FROM movimentos_caixa mc
        JOIN caixas   c ON c.id  = mc.caixa_id
        JOIN lojas    l ON l.id  = c.loja_id
        JOIN usuarios u ON u.id  = mc.usuario_id
        {$where}
        ORDER BY mc.created_at DESC
        LIMIT 500
    ");
    $stmt->execute($params);
    json_response(['status' => 'ok', 'data' => $stmt->fetchAll(), 'message' => '']);
}

// ── GET /caixas — caixa aberto da loja ────────────────────────────────────────
if ($id === null && $method === 'GET') {
    $loja_id = filter_input(INPUT_GET, 'loja_id', FILTER_VALIDATE_INT);
    $status  = sanitize($_GET['status'] ?? 'aberto');

    // Caixa (operador) é sempre da sua própria loja. Gerente/super_admin/rh_financeiro
    // são responsáveis por todas as lojas e precisam informar loja_id explicitamente.
    if ($u['role'] === 'caixa') {
        $loja_id = (int) ($u['loja_id'] ?? 0);
    }
    if (!$loja_id) {
        json_response(['status' => 'error', 'message' => 'loja_id obrigatório.', 'data' => null], 422);
    }

    $stmt = $pdo->prepare("
        SELECT c.*, u.nome AS operador_nome,
               COALESCE(SUM(v.total_final), 0)  AS total_vendas,
               COUNT(v.id)                       AS qtd_vendas
        FROM caixas c
        JOIN usuarios u ON u.id = c.usuario_id
        LEFT JOIN vendas v ON v.caixa_id = c.id AND v.status = 'finalizada'
        WHERE c.loja_id = :loja_id AND c.status = :status
        GROUP BY c.id
        ORDER BY c.aberto_em DESC
        LIMIT 1
    ");
    $stmt->execute(['loja_id' => $loja_id, 'status' => $status]);
    $caixa = $stmt->fetch();

    json_response(['status' => 'ok', 'data' => $caixa ?: null, 'message' => '']);
}

// ── POST /caixas — abrir caixa ────────────────────────────────────────────────
if ($id === null && $method === 'POST') {
    api_auth_role(['caixa', 'gerente', 'super_admin']);

    $loja_id    = (int)   ($body['loja_id']    ?? $u['loja_id'] ?? 0);
    $fundo_troco = (float) ($body['fundo_troco'] ?? 0);
    $justificativa = sanitize($body['justificativa'] ?? '');

    if (!$loja_id) {
        json_response(['status' => 'error', 'message' => 'loja_id obrigatório.', 'data' => null], 422);
    }
    if ($fundo_troco < 0) {
        json_response(['status' => 'error', 'message' => 'Fundo de troco não pode ser negativo.', 'data' => null], 422);
    }

    // Gerente/super_admin não são o operador do caixa — quando abrem em nome de um
    // operador ausente (ou um caixa esquecido aberto que precisa ser reaberto em
    // outra loja), a justificativa é obrigatória para fins de auditoria.
    $eh_operador_normal = ($u['role'] === 'caixa');
    if (!$eh_operador_normal && $justificativa === '') {
        json_response(['status' => 'error', 'message' => 'Justificativa obrigatória: informe o motivo de abrir o caixa no lugar do operador.', 'data' => null], 422);
    }

    // Verifica se já há caixa aberto nesta loja
    $stmt = $pdo->prepare('SELECT id FROM caixas WHERE loja_id = :lid AND status = "aberto" LIMIT 1');
    $stmt->execute(['lid' => $loja_id]);
    if ($stmt->fetch()) {
        json_response(['status' => 'error', 'message' => 'Já existe um caixa aberto para esta loja. Feche-o antes de abrir outro.', 'data' => null], 409);
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare('
            INSERT INTO caixas (loja_id, usuario_id, justificativa_abertura, fundo_troco, status)
            VALUES (:loja_id, :uid, :just, :fundo, "aberto")
        ')->execute([
            'loja_id' => $loja_id,
            'uid'     => (int) $u['id'],
            'just'    => $justificativa ?: null,
            'fundo'   => $fundo_troco,
        ]);
        $caixa_id = (int) $pdo->lastInsertId();

        // Registra movimento de abertura
        $pdo->prepare('
            INSERT INTO movimentos_caixa (caixa_id, tipo, valor, justificativa, usuario_id)
            VALUES (:cid, "abertura", :valor, :just, :uid)
        ')->execute([
            'cid'  => $caixa_id,
            'valor'=> $fundo_troco,
            'just' => $justificativa ?: 'Abertura de caixa',
            'uid'  => (int) $u['id'],
        ]);

        $pdo->commit();

        registrar_log((int) $u['id'], 'abertura_caixa', 'caixas', $caixa_id,
            ['loja_id' => $loja_id, 'fundo_troco' => $fundo_troco, 'justificativa' => $justificativa]);

        json_response([
            'status'  => 'ok',
            'data'    => ['caixa_id' => $caixa_id],
            'message' => 'Caixa aberto com sucesso.',
        ], 201);

    } catch (Throwable $e) {
        $pdo->rollBack();
        json_response(['status' => 'error', 'message' => 'Erro ao abrir caixa: ' . $e->getMessage(), 'data' => null], 500);
    }
}

// ── POST /caixas/{id}/fechar — fechar caixa ───────────────────────────────────
if ($id !== null && $sub === 'fechar' && $method === 'POST') {
    api_auth_role(['caixa', 'gerente', 'super_admin']);

    $caixa_id      = (int) $id;
    $total_dinheiro = (float) ($body['total_dinheiro'] ?? 0);
    // Aceita 'justificativa' (nome novo) ou 'observacoes' (nome legado usado pelo PDV) — o
    // que vier preenchido primeiro é usado.
    $justificativa = sanitize($body['justificativa'] ?? $body['observacoes'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM caixas WHERE id = :id AND status = "aberto"');
    $stmt->execute(['id' => $caixa_id]);
    $caixa = $stmt->fetch();
    if (!$caixa) {
        json_response(['status' => 'error', 'message' => 'Caixa não encontrado ou já fechado.', 'data' => null], 404);
    }

    // Caixa (operador) só pode fechar caixa da sua própria loja. Gerente é
    // responsável por todas as lojas e pode fechar o caixa de qualquer uma.
    if ($u['role'] === 'caixa' && (int) $caixa['loja_id'] !== (int) ($u['loja_id'] ?? 0)) {
        json_response(['status' => 'error', 'message' => 'Permissão negada para esta loja.', 'data' => null], 403);
    }

    // Gerente/super_admin não são o operador do caixa — fechar em nome de um
    // operador ausente (ou um caixa esquecido aberto) exige justificativa.
    $eh_operador_normal = ($u['role'] === 'caixa');
    if (!$eh_operador_normal && $justificativa === '') {
        json_response(['status' => 'error', 'message' => 'Justificativa obrigatória: informe o motivo de fechar o caixa no lugar do operador.', 'data' => null], 422);
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare('
            UPDATE caixas
            SET status = "fechado", fechado_em = NOW(), fechado_por = :uid,
                total_contado = :total, justificativa_fechamento = :just
            WHERE id = :id
        ')->execute([
            'id'    => $caixa_id,
            'uid'   => (int) $u['id'],
            'total' => $total_dinheiro,
            'just'  => $justificativa ?: null,
        ]);

        $pdo->prepare('
            INSERT INTO movimentos_caixa (caixa_id, tipo, valor, justificativa, usuario_id)
            VALUES (:cid, "fechamento", :valor, :obs, :uid)
        ')->execute([
            'cid'   => $caixa_id,
            'valor' => $total_dinheiro,
            'obs'   => $justificativa ?: 'Fechamento de caixa',
            'uid'   => (int) $u['id'],
        ]);

        $pdo->commit();

        registrar_log((int) $u['id'], 'fechamento_caixa', 'caixas', $caixa_id,
            ['total_dinheiro' => $total_dinheiro, 'justificativa' => $justificativa]);

        json_response(['status' => 'ok', 'data' => null, 'message' => 'Caixa fechado com sucesso.']);

    } catch (Throwable $e) {
        $pdo->rollBack();
        json_response(['status' => 'error', 'message' => 'Erro ao fechar caixa: ' . $e->getMessage(), 'data' => null], 500);
    }
}

// ── POST /caixas/{id}/sangria — sangria ou suprimento ─────────────────────────
if ($id !== null && $sub === 'sangria' && $method === 'POST') {
    api_auth_role(['caixa', 'gerente', 'super_admin']);

    $caixa_id     = (int)    $id;
    $tipo         = sanitize($body['tipo']         ?? 'sangria'); // sangria | suprimento
    $valor        = (float)  ($body['valor']        ?? 0);
    $justificativa = sanitize($body['justificativa'] ?? '');

    if (!in_array($tipo, ['sangria', 'suprimento'])) {
        json_response(['status' => 'error', 'message' => 'Tipo deve ser "sangria" ou "suprimento".', 'data' => null], 422);
    }
    if ($valor <= 0) {
        json_response(['status' => 'error', 'message' => 'Valor deve ser maior que zero.', 'data' => null], 422);
    }
    if ($tipo === 'sangria' && empty($justificativa)) {
        json_response(['status' => 'error', 'message' => 'Justificativa obrigatória para sangria.', 'data' => null], 422);
    }

    $stmt = $pdo->prepare('SELECT * FROM caixas WHERE id = :id AND status = "aberto"');
    $stmt->execute(['id' => $caixa_id]);
    $caixa = $stmt->fetch();
    if (!$caixa) {
        json_response(['status' => 'error', 'message' => 'Caixa não encontrado ou não está aberto.', 'data' => null], 404);
    }

    if ($u['role'] === 'caixa' && (int) $caixa['loja_id'] !== (int) ($u['loja_id'] ?? 0)) {
        json_response(['status' => 'error', 'message' => 'Permissão negada para esta loja.', 'data' => null], 403);
    }

    $pdo->prepare('
        INSERT INTO movimentos_caixa (caixa_id, tipo, valor, justificativa, usuario_id)
        VALUES (:cid, :tipo, :valor, :just, :uid)
    ')->execute([
        'cid'  => $caixa_id,
        'tipo' => $tipo,
        'valor' => $valor,
        'just' => $justificativa ?: null,
        'uid'  => (int) $u['id'],
    ]);
    $mov_id = (int) $pdo->lastInsertId();

    registrar_log((int) $u['id'], $tipo . '_caixa', 'movimentos_caixa', $mov_id,
        ['caixa_id' => $caixa_id, 'valor' => $valor, 'justificativa' => $justificativa]);

    json_response([
        'status'  => 'ok',
        'data'    => ['id' => $mov_id],
        'message' => ucfirst($tipo) . ' registrada com sucesso.',
    ]);
}

// ── GET /caixas/abertos — todos os caixas atualmente abertos ─────────────────
if ($id === 'abertos' && $method === 'GET') {
    api_auth_role(['gerente', 'super_admin', 'rh_financeiro']);

    // Gerente é responsável por todas as lojas — mesma visão multi-loja do super_admin.
    $loja_id = filter_input(INPUT_GET, 'loja_id', FILTER_VALIDATE_INT) ?: null;

    $where  = 'WHERE c.status = "aberto"';
    $params = [];
    if ($loja_id) { $where .= ' AND c.loja_id = :loja_id'; $params['loja_id'] = $loja_id; }

    $stmt = $pdo->prepare("
        SELECT
            c.id, c.fundo_troco, c.aberto_em, c.loja_id,
            l.nome AS loja_nome,
            u.nome AS operador_nome,
            TIMESTAMPDIFF(HOUR,   c.aberto_em, NOW()) AS horas_aberto,
            TIMESTAMPDIFF(MINUTE, c.aberto_em, NOW()) AS minutos_aberto,
            COALESCE(SUM(v.total_final), 0) AS total_vendas,
            COUNT(v.id)                     AS qtd_vendas
        FROM caixas c
        JOIN lojas    l ON l.id = c.loja_id
        JOIN usuarios u ON u.id = c.usuario_id
        LEFT JOIN vendas v ON v.caixa_id = c.id AND v.status = 'finalizada'
        {$where}
        GROUP BY c.id
        ORDER BY c.aberto_em ASC
    ");
    $stmt->execute($params);
    json_response(['status' => 'ok', 'data' => $stmt->fetchAll()]);
}

// ── GET /caixas/historico — auditoria de abertura/fechamento ─────────────────
// Mostra loja, quem abriu, quem fechou, datas/horas e a diferença entre o
// valor contado no fechamento e o valor esperado em caixa (fundo + vendas em
// dinheiro + suprimentos - sangrias) — usado para apurar sobra/falta de caixa.
if ($id === 'historico' && $method === 'GET') {
    api_auth_role(['gerente', 'super_admin', 'rh_financeiro']);

    $loja_id  = filter_input(INPUT_GET, 'loja_id', FILTER_VALIDATE_INT) ?: null;
    $status   = sanitize($_GET['status'] ?? '');   // 'aberto' | 'fechado' | '' (todos)
    $busca    = trim(sanitize($_GET['busca'] ?? '')); // nome de quem abriu ou fechou
    $data_ini = sanitize($_GET['data_ini'] ?? date('Y-m-01'));
    $data_fim = sanitize($_GET['data_fim'] ?? date('Y-m-d'));

    // Gerente é responsável por todas as lojas — mesma visão multi-loja do super_admin.

    $where  = 'WHERE DATE(c.aberto_em) BETWEEN :di AND :df';
    $params = ['di' => $data_ini, 'df' => $data_fim];

    if ($loja_id) { $where .= ' AND c.loja_id = :loja_id'; $params['loja_id'] = $loja_id; }
    if (in_array($status, ['aberto', 'fechado'], true)) {
        $where .= ' AND c.status = :status'; $params['status'] = $status;
    }
    if ($busca !== '') {
        // PDO::ATTR_EMULATE_PREPARES=false: named params não podem repetir, usar nomes distintos
        $where .= ' AND (u_abriu.nome LIKE :busca1 OR u_fechou.nome LIKE :busca2)';
        $params['busca1'] = '%' . $busca . '%';
        $params['busca2'] = '%' . $busca . '%';
    }

    $stmt = $pdo->prepare("
        SELECT
            c.id, c.status, c.loja_id, l.nome AS loja_nome,
            c.fundo_troco, c.aberto_em, c.fechado_em, c.total_contado,
            c.justificativa_abertura, c.justificativa_fechamento,
            u_abriu.nome  AS abriu_nome, u_abriu.role  AS abriu_role,
            u_fechou.nome AS fechou_nome, u_fechou.role AS fechou_role,
            COALESCE((
                SELECT SUM(v.total_final) FROM vendas v
                WHERE v.caixa_id = c.id AND v.status = 'finalizada' AND v.forma_pagamento = 'dinheiro'
            ), 0) AS vendas_dinheiro,
            COALESCE((
                SELECT SUM(mv.valor) FROM movimentos_caixa mv
                WHERE mv.caixa_id = c.id AND mv.tipo = 'sangria'
            ), 0) AS total_sangrias,
            COALESCE((
                SELECT SUM(mv.valor) FROM movimentos_caixa mv
                WHERE mv.caixa_id = c.id AND mv.tipo = 'suprimento'
            ), 0) AS total_suprimentos
        FROM caixas c
        JOIN lojas      l        ON l.id = c.loja_id
        JOIN usuarios   u_abriu  ON u_abriu.id  = c.usuario_id
        LEFT JOIN usuarios u_fechou ON u_fechou.id = c.fechado_por
        {$where}
        ORDER BY c.aberto_em DESC
        LIMIT 300
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Tolerância de quebra de caixa (arredondamento de troco em dinheiro), configurada
    // globalmente pelo dono em `configuracoes` (chave 'tolerancia_quebra_caixa').
    $tol_stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'tolerancia_quebra_caixa'");
    $tol_stmt->execute();
    $tolerancia = (float) ($tol_stmt->fetchColumn() ?: 3.00);

    // Calcula valor esperado em caixa e a diferença vs. o valor contado no fechamento
    foreach ($rows as &$r) {
        $esperado = (float) $r['fundo_troco'] + (float) $r['vendas_dinheiro']
                  + (float) $r['total_suprimentos'] - (float) $r['total_sangrias'];
        $r['esperado']  = round($esperado, 2);
        $r['diferenca'] = ($r['status'] === 'fechado' && $r['total_contado'] !== null)
            ? round((float) $r['total_contado'] - $esperado, 2)
            : null;
        // Dentro da tolerância = arredondamento normal de troco em dinheiro, não conta
        // como falta/sobra real.
        $r['tolerancia_aplicada'] = $tolerancia;
        $r['dentro_tolerancia']   = ($r['diferenca'] !== null) && (abs($r['diferenca']) <= $tolerancia);
    }
    unset($r);

    json_response(['status' => 'ok', 'data' => $rows, 'message' => '']);
}

// ── GET /caixas/pagamentos — breakdown por forma de pagamento ─────────────────
if ($id === 'pagamentos' && $method === 'GET') {
    api_auth_role(['gerente', 'super_admin', 'rh_financeiro']);

    $loja_id  = filter_input(INPUT_GET, 'loja_id',  FILTER_VALIDATE_INT) ?: null;
    $data_ini = sanitize($_GET['data_ini'] ?? date('Y-m-01'));
    $data_fim = sanitize($_GET['data_fim'] ?? date('Y-m-d'));

    // Gerente é responsável por todas as lojas — mesma visão multi-loja do super_admin.

    $where  = 'WHERE v.status = "finalizada" AND DATE(c.aberto_em) BETWEEN :di AND :df';
    $params = ['di' => $data_ini, 'df' => $data_fim];
    if ($loja_id) { $where .= ' AND c.loja_id = :loja_id'; $params['loja_id'] = $loja_id; }

    $stmt = $pdo->prepare("
        SELECT
            v.forma_pagamento,
            COUNT(v.id)        AS qtd,
            SUM(v.total_final) AS total
        FROM vendas v
        JOIN caixas c ON c.id = v.caixa_id
        {$where}
        GROUP BY v.forma_pagamento
        ORDER BY total DESC
    ");
    $stmt->execute($params);
    json_response(['status' => 'ok', 'data' => $stmt->fetchAll()]);
}

json_response(['status' => 'error', 'message' => 'Endpoint não encontrado.', 'data' => null], 404);
