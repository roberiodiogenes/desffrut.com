<?php
// Guard: acesso direto pela URL retorna 403.
if (!function_exists('api_auth_exigir')) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Acesso direto não permitido. Use /api/v1/pedidos']);
    exit;
}

/**
 * Desffrut — API v1: Pedidos de Tele-Entrega (Fase 6)
 *
 * GET  /api/v1/pedidos                → listar pedidos (staff) ou pedidos do cliente logado
 * POST /api/v1/pedidos                → criar pedido (cliente logado); canal_origem=whatsapp retorna wa_url
 * GET  /api/v1/pedidos/{id}           → detalhes (staff ou dono)
 * PATCH /api/v1/pedidos/{id}          → atualizar status / atribuir entregador (staff)
 *
 * Status flow (web): aguardando → preparando → saiu_para_entrega → entregue | cancelado
 * Status flow (WA):  aguardando_validacao → preparando → saiu_para_entrega → entregue | cancelado
 * Rastreamento via polling de 20 s (sem WebSocket — incompatível com HostGator).
 * Fase 11: WhatsApp Híbrido — gerador de mensagem + token de validação em 1 clique (24h, uso único).
 */

$u   = api_auth_exigir();
$pdo = db();
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ─── Helper: retorna SQL/params de filial (não usado para 'gerente' — ele é
// responsável por todas as lojas, não há um gerente por loja) ────────────────
function _pedido_loja_where(array $u): array {
    return ['sql' => '', 'params' => []];
}

// ─── Helper: verifica se usuário pode ver o pedido ───────────────────────────
function _pode_ver_pedido(array $u, array $p): bool {
    if (in_array($u['role'], ['super_admin', 'gerente', 'caixa'], true)) return true;
    if ($u['role'] === 'entregador' && (int) ($p['entregador_id'] ?? 0) === (int) $u['id']) return true;
    if ($u['role'] === 'cliente' && (int) $p['cliente_id'] === (int) $u['id']) return true;
    return false;
}

// ─── GET /pedidos ─────────────────────────────────────────────────────────────
if ($id === null && $method === 'GET') {

    // Cliente: apenas seus próprios pedidos
    if ($u['role'] === 'cliente') {
        $stmt = $pdo->prepare("
            SELECT p.id, p.status, p.total, p.forma_pagamento,
                   p.endereco_entrega, p.bairro, p.created_at, p.updated_at,
                   l.nome AS loja_nome
            FROM pedidos p
            JOIN lojas l ON l.id = p.loja_id
            WHERE p.cliente_id = :cid
            ORDER BY p.created_at DESC
            LIMIT 50
        ");
        $stmt->execute(['cid' => $u['id']]);
        json_response(['status' => 'ok', 'data' => $stmt->fetchAll()]);
    }

    // Staff: lista com filtros por status/loja
    api_auth_role(['super_admin', 'gerente', 'caixa', 'entregador']);

    $status_filtro = sanitize($_GET['status'] ?? '');
    $loja_filtro   = filter_input(INPUT_GET, 'loja_id', FILTER_VALIDATE_INT) ?: null;

    $where  = 'WHERE 1=1';
    $params = [];

    // Filtra por status (pode ser lista separada por vírgula)
    if ($status_filtro) {
        $opcoes_validas = ['aguardando_validacao','aguardando','preparando','saiu_para_entrega','entregue','cancelado'];
        $lista = array_filter(explode(',', $status_filtro), fn($s) => in_array(trim($s), $opcoes_validas, true));
        if ($lista) {
            $placeholders = implode(',', array_map(fn($i) => ":s$i", array_keys($lista)));
            $where .= " AND p.status IN ($placeholders)";
            foreach (array_values($lista) as $i => $s) { $params["s$i"] = trim($s); }
        }
    }

    // Entregador: apenas pedidos atribuídos a ele
    if ($u['role'] === 'entregador') {
        $where .= ' AND p.entregador_id = :eid';
        $params['eid'] = $u['id'];
    }

    // Gerente é responsável por todas as lojas — mesma visão multi-loja do
    // super_admin, com filtro opcional de loja_id vindo da query string.
    if ($loja_filtro) {
        $where .= ' AND p.loja_id = :loja_id';
        $params['loja_id'] = $loja_filtro;
    }

    $stmt = $pdo->prepare("
        SELECT
            p.id, p.status, p.total, p.pontos_ganhos,
            p.forma_pagamento, p.troco_para,
            p.endereco_entrega, p.numero, p.complemento, p.bairro, p.telefone,
            p.observacoes, p.motivo_cancelamento, p.created_at, p.updated_at,
            c.nome AS cliente_nome, c.telefone AS cliente_telefone,
            e.nome AS entregador_nome,
            l.nome AS loja_nome
        FROM pedidos p
        JOIN  usuarios c ON c.id = p.cliente_id
        JOIN  lojas    l ON l.id = p.loja_id
        LEFT JOIN usuarios e ON e.id = p.entregador_id
        {$where}
        ORDER BY p.created_at DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll();

    // Adiciona itens a cada pedido (inclui ids para edição em preparo)
    foreach ($pedidos as &$p) {
        $s2 = $pdo->prepare("
            SELECT ip.id AS item_id, ip.produto_id, ip.quantidade, ip.preco_unitario, ip.subtotal,
                   pr.nome AS produto_nome, pr.unidade_medida AS unidade
            FROM itens_pedido ip JOIN produtos pr ON pr.id = ip.produto_id
            WHERE ip.pedido_id = :pid
            ORDER BY pr.nome
        ");
        $s2->execute(['pid' => $p['id']]);
        $p['itens'] = $s2->fetchAll();
    }
    unset($p);

    json_response(['status' => 'ok', 'data' => $pedidos]);
}

// ─── Helper: gerador de mensagem WhatsApp ────────────────────────────────────
function _gerar_msg_wa(
    int $pedido_id, float $total, string $fp, ?float $troco,
    string $endereco, string $numero, string $complemento, string $bairro, string $telefone,
    string $obs, array $itens, array $loja, string $token
): string {
    $fp_label = [
        'dinheiro_na_entrega'    => 'Dinheiro na entrega',
        'cartao_debito_entrega'  => 'Débito na entrega',
        'cartao_credito_entrega' => 'Crédito na entrega',
        'pix'                    => 'Pix',
    ][$fp] ?? $fp;

    $msg  = "🛒 *NOVO PEDIDO — DESFFRUT*\n";
    $msg .= "🏪 Filial: {$loja['nome']}\n";
    $msg .= "🔢 Pedido: #{$pedido_id}\n\n";
    $msg .= "📋 *ITENS:*\n";
    foreach ($itens as $it) {
        $qtd = ($it['unidade'] === 'kg')
            ? number_format($it['quantidade'], 3, ',', '.') . ' kg'
            : ((int)$it['quantidade']) . ' un';
        $sub = 'R$ ' . number_format($it['subtotal'], 2, ',', '.');
        $msg .= "• {$it['produto_nome']} ({$qtd}) — {$sub}\n";
    }
    $msg .= "\n💰 *TOTAL: R$ " . number_format($total, 2, ',', '.') . "*\n";
    $msg .= "💳 {$fp_label}";
    if ($troco) $msg .= ' · troco para R$ ' . number_format($troco, 2, ',', '.');
    $msg .= "\n\n📍 *ENTREGA:*\n";
    $end_str = trim("{$endereco}, {$numero}");
    if ($complemento) $end_str .= " — {$complemento}";
    $msg .= $end_str . "\n";
    $msg .= "Bairro: {$bairro}\n";
    if ($telefone) $msg .= "📞 {$telefone}\n";
    if ($obs) $msg .= "💬 {$obs}\n";
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    $msg .= "\n────────────────────\n";
    $msg .= "✅ *VALIDAR PEDIDO (1 clique):*\n";
    $msg .= "{$base}/pedidos/validar/{$token}\n";
    $msg .= "────────────────────\n";
    $msg .= "_Token válido por 24h — uso único_";
    return $msg;
}

// ─── POST /pedidos — criar pedido (cliente) ───────────────────────────────────
if ($id === null && $method === 'POST') {
    api_auth_role(['cliente']);

    // Valida campos obrigatórios
    $campos = ['itens', 'endereco_entrega', 'forma_pagamento', 'loja_id'];
    foreach ($campos as $c) {
        if (empty($body[$c])) {
            json_response(['status' => 'error', 'message' => "Campo '$c' obrigatório.", 'data' => null], 422);
        }
    }

    $itens           = $body['itens'];
    $loja_id         = (int) $body['loja_id'];
    $forma_pagamento = sanitize($body['forma_pagamento']);
    $endereco        = sanitize($body['endereco_entrega']);
    $numero          = sanitize($body['numero'] ?? '');
    $complemento     = sanitize($body['complemento'] ?? '');
    $bairro          = sanitize($body['bairro'] ?? '');
    $telefone        = sanitize($body['telefone'] ?? '');
    $observacoes     = sanitize($body['observacoes'] ?? '');
    $troco_para      = !empty($body['troco_para']) ? (float) $body['troco_para'] : null;
    $canal_origem    = ($body['canal_origem'] ?? '') === 'whatsapp' ? 'whatsapp' : 'web';
    $origem_utm      = !empty($_SESSION['utm']) ? json_encode($_SESSION['utm']) : null;

    $formas_validas  = ['dinheiro_na_entrega','cartao_debito_entrega','cartao_credito_entrega','pix'];
    if (!in_array($forma_pagamento, $formas_validas, true)) {
        json_response(['status' => 'error', 'message' => 'Forma de pagamento inválida.', 'data' => null], 422);
    }
    if (!is_array($itens) || count($itens) === 0) {
        json_response(['status' => 'error', 'message' => 'Sacola vazia.', 'data' => null], 422);
    }

    $pdo->beginTransaction();
    try {
        // Calcular total com preços reais do banco (não confia no front-end)
        $total = 0.0;
        $itens_validados = [];
        foreach ($itens as $item) {
            $pid = (int) ($item['produto_id'] ?? 0);
            if (!$pid) throw new \Exception('produto_id inválido.');

            // Busca preço real na tabela precos
            $s = $pdo->prepare("
                SELECT p.nome, p.unidade_medida AS unidade,
                       COALESCE(
                           CASE WHEN pr.promo_preco IS NOT NULL
                                     AND (pr.promo_inicio IS NULL OR pr.promo_inicio <= NOW())
                                     AND (pr.promo_fim   IS NULL OR pr.promo_fim   >= NOW())
                                THEN pr.promo_preco ELSE NULL END,
                           pr.preco_venda
                       ) AS preco
                FROM produtos p
                LEFT JOIN precos pr ON pr.produto_id = p.id AND pr.loja_id = :loja_id
                WHERE p.id = :pid AND p.ativo = 1
            ");
            $s->execute(['pid' => $pid, 'loja_id' => $loja_id]);
            $prod = $s->fetch();
            if (!$prod || !$prod['preco']) throw new \Exception("Produto #$pid não encontrado ou sem preço.");

            $qtd      = (float) ($item['quantidade'] ?? 1);
            $sub      = round($prod['preco'] * $qtd, 2);
            $total   += $sub;
            $itens_validados[] = [
                'produto_id'    => $pid,
                'quantidade'    => $qtd,
                'preco_unitario'=> (float) $prod['preco'],
                'subtotal'      => $sub,
            ];
        }

        // Gera token de validação para pedidos via WhatsApp
        $wa_token     = null;
        $wa_expira_em = null;
        $status_inicial = 'aguardando';
        if ($canal_origem === 'whatsapp') {
            $wa_token     = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0,0xffff), mt_rand(0,0xffff),
                mt_rand(0,0xffff),
                mt_rand(0,0x0fff)|0x4000,
                mt_rand(0,0x3fff)|0x8000,
                mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));
            $wa_expira_em = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $status_inicial = 'aguardando_validacao';
        }

        // Inserir pedido
        $stmt = $pdo->prepare("
            INSERT INTO pedidos
                (cliente_id, loja_id, status, total, pontos_ganhos,
                 forma_pagamento, troco_para,
                 endereco_entrega, numero, complemento, bairro, telefone, observacoes,
                 canal_origem, wa_token, wa_token_expira_em, origem_utm)
            VALUES
                (:cid, :lid, :status, :total, :pts,
                 :fp, :troco,
                 :end, :num, :comp, :bairro, :tel, :obs,
                 :canal, :token, :expira, :utm)
        ");
        $pts = (int) floor($total); // R$1 = 1 ponto
        $stmt->execute([
            'cid'    => $u['id'],
            'lid'    => $loja_id,
            'status' => $status_inicial,
            'total'  => $total,
            'pts'    => $pts,
            'fp'     => $forma_pagamento,
            'troco'  => $troco_para,
            'end'    => $endereco,
            'num'    => $numero,
            'comp'   => $complemento,
            'bairro' => $bairro,
            'tel'    => $telefone,
            'obs'    => $observacoes,
            'canal'  => $canal_origem,
            'token'  => $wa_token,
            'expira' => $wa_expira_em,
            'utm'    => $origem_utm,
        ]);
        $pedido_id = (int) $pdo->lastInsertId();

        // Inserir itens
        $ins = $pdo->prepare("
            INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario, subtotal)
            VALUES (:pid, :prod, :qtd, :pu, :sub)
        ");
        foreach ($itens_validados as $it) {
            $ins->execute([
                'pid'  => $pedido_id,
                'prod' => $it['produto_id'],
                'qtd'  => $it['quantidade'],
                'pu'   => $it['preco_unitario'],
                'sub'  => $it['subtotal'],
            ]);
        }

        $pdo->commit();

        $resp_data = ['pedido_id' => $pedido_id, 'total' => $total, 'canal_origem' => $canal_origem];

        // Para pedidos WA: gera URL wa.me com mensagem pré-preenchida
        if ($canal_origem === 'whatsapp' && $wa_token) {
            $loja_row = $pdo->prepare("SELECT nome, telefone FROM lojas WHERE id = :id");
            $loja_row->execute(['id' => $loja_id]);
            $loja = $loja_row->fetch();

            // Recarrega itens com nome do produto
            $si = $pdo->prepare("
                SELECT ip.quantidade, ip.subtotal, pr.nome AS produto_nome, pr.unidade_medida AS unidade
                FROM itens_pedido ip JOIN produtos pr ON pr.id = ip.produto_id
                WHERE ip.pedido_id = :pid
            ");
            $si->execute(['pid' => $pedido_id]);
            $itens_completos = $si->fetchAll();

            $msg = _gerar_msg_wa($pedido_id, $total, $forma_pagamento, $troco_para,
                                  $endereco, $numero, $complemento, $bairro, $telefone,
                                  $observacoes, $itens_completos, $loja, $wa_token);
            $tel_limpo = preg_replace('/\D/', '', $loja['telefone'] ?? '');
            // Adiciona código do país Brasil se não tiver
            if (strlen($tel_limpo) <= 11) $tel_limpo = '55' . $tel_limpo;
            $resp_data['wa_url']  = 'https://wa.me/' . $tel_limpo . '?text=' . rawurlencode($msg);
            $resp_data['wa_token'] = $wa_token;
        }

        json_response(['status' => 'ok', 'message' => 'Pedido criado com sucesso.', 'data' => $resp_data], 201);

    } catch (\Exception $e) {
        $pdo->rollBack();
        json_response(['status' => 'error', 'message' => $e->getMessage(), 'data' => null], 422);
    }
}

// ─── GET /pedidos/{id} — detalhes ────────────────────────────────────────────
if ($id !== null && $sub === null && $method === 'GET') {
    $stmt = $pdo->prepare("
        SELECT p.*,
               c.nome AS cliente_nome, c.telefone AS cliente_telefone,
               e.nome AS entregador_nome,
               l.nome AS loja_nome
        FROM pedidos p
        JOIN  usuarios c ON c.id = p.cliente_id
        JOIN  lojas    l ON l.id = p.loja_id
        LEFT JOIN usuarios e ON e.id = p.entregador_id
        WHERE p.id = :id
    ");
    $stmt->execute(['id' => (int) $id]);
    $pedido = $stmt->fetch();
    if (!$pedido) json_response(['status' => 'error', 'message' => 'Pedido não encontrado.', 'data' => null], 404);
    if (!_pode_ver_pedido($u, $pedido)) json_response(['status' => 'error', 'message' => 'Acesso negado.', 'data' => null], 403);

    // Itens
    $si = $pdo->prepare("
        SELECT ip.quantidade, ip.preco_unitario, ip.subtotal,
               pr.nome AS produto_nome, pr.unidade_medida AS unidade
        FROM itens_pedido ip JOIN produtos pr ON pr.id = ip.produto_id
        WHERE ip.pedido_id = :pid
    ");
    $si->execute(['pid' => $pedido['id']]);
    $pedido['itens'] = $si->fetchAll();

    json_response(['status' => 'ok', 'data' => $pedido]);
}

// ─── PATCH /pedidos/{id} — atualizar status / entregador ──────────────────────
if ($id !== null && ($sub === null || $sub === 'status') && $method === 'PATCH') {
    api_auth_role(['super_admin', 'gerente', 'caixa', 'entregador']);

    // Busca pedido atual
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = :id");
    $stmt->execute(['id' => (int) $id]);
    $pedido = $stmt->fetch();
    if (!$pedido) json_response(['status' => 'error', 'message' => 'Pedido não encontrado.', 'data' => null], 404);

    // Gerente é responsável por todas as lojas e pode atualizar pedidos de qualquer uma.

    $novo_status          = sanitize($body['status'] ?? '');
    $entregador_id        = isset($body['entregador_id']) ? (int) $body['entregador_id'] : null;
    $motivo_cancelamento  = isset($body['motivo_cancelamento']) ? sanitize($body['motivo_cancelamento']) : null;

    // Mapa de transições permitidas por role
    $transicoes = [
        'aguardando_validacao' => ['preparando', 'cancelado'],
        'aguardando'           => ['preparando', 'cancelado'],
        'preparando'           => ['saiu_para_entrega', 'cancelado'],
        'saiu_para_entrega'    => ['entregue', 'cancelado'],
        'entregue'             => [],
        'cancelado'            => [],
    ];

    $updates  = [];
    $params   = ['id' => (int) $id];

    if ($novo_status) {
        $status_atual = $pedido['status'];
        if (!in_array($novo_status, $transicoes[$status_atual] ?? [], true)) {
            json_response(['status' => 'error', 'message' => "Transição '$status_atual' → '$novo_status' inválida.", 'data' => null], 422);
        }

        // Entregador pode confirmar entrega ou registrar não-entrega (cancelado)
        if ($u['role'] === 'entregador' && !in_array($novo_status, ['entregue', 'cancelado'], true)) {
            json_response(['status' => 'error', 'message' => 'Entregador só pode confirmar entrega ou registrar não-entrega.', 'data' => null], 403);
        }

        // Quando cancela: exige motivo do entregador
        if ($novo_status === 'cancelado' && $u['role'] === 'entregador' && empty($motivo_cancelamento)) {
            json_response(['status' => 'error', 'message' => 'Informe o motivo da não-entrega.', 'data' => null], 422);
        }

        $updates[] = 'status = :status';
        $params['status'] = $novo_status;

        // Salva motivo de cancelamento quando fornecido
        if ($motivo_cancelamento) {
            $updates[]                    = 'motivo_cancelamento = :motivo';
            $params['motivo']             = $motivo_cancelamento;
        }

        // Ao entregar: crédita pontos + registra histórico (tudo em try/catch para não bloquear a resposta)
        if ($novo_status === 'entregue') {
            try {
                $pts = (int) $pedido['pontos_ganhos'];
                if ($pts > 0) {
                    $pdo->prepare("UPDATE usuarios SET pontos_fidelidade = pontos_fidelidade + :pts WHERE id = :uid")
                        ->execute(['pts' => $pts, 'uid' => $pedido['cliente_id']]);
                    try {
                        $pdo->prepare("
                            INSERT INTO historico_pontos (cliente_id, operacao, pontos, referencia_id, referencia_tipo)
                            VALUES (:cid, 'credito', :pts_hp, :ref, 'pedido')
                        ")->execute(['cid' => $pedido['cliente_id'], 'pts_hp' => $pts, 'ref' => $pedido['id']]);
                    } catch (\Throwable $_) { /* tabela ainda não existe — migração 16 pendente */ }
                }

                // Fase 12 — Bônus ao indicador (1ª entrega do cliente indicado)
                $cli_ref = $pdo->prepare("SELECT indicado_por_id, indicacao_bonus_pago FROM usuarios WHERE id = :uid");
                $cli_ref->execute(['uid' => $pedido['cliente_id']]);
                $cli_dados = $cli_ref->fetch();
                if ($cli_dados && $cli_dados['indicado_por_id'] && !$cli_dados['indicacao_bonus_pago']) {
                    $pts_cfg = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'pontos_indicacao'")->fetchColumn();
                    $pts_ind = (int) ($pts_cfg ?: 100);
                    $pdo->prepare("UPDATE usuarios SET pontos_fidelidade = pontos_fidelidade + :pts WHERE id = :uid")
                        ->execute(['pts' => $pts_ind, 'uid' => $cli_dados['indicado_por_id']]);
                    $pdo->prepare("UPDATE usuarios SET indicacao_bonus_pago = 1 WHERE id = :uid")
                        ->execute(['uid' => $pedido['cliente_id']]);
                    try {
                        $pdo->prepare("
                            INSERT INTO historico_pontos (cliente_id, operacao, pontos, referencia_id, referencia_tipo)
                            VALUES (:cid, 'credito', :pts_ind, :ref, 'indicacao')
                        ")->execute(['cid' => $cli_dados['indicado_por_id'], 'pts_ind' => $pts_ind, 'ref' => $pedido['id']]);
                    } catch (\Throwable $_) { /* tabela historico_pontos — migração 16 pendente */ }
                }
            } catch (\Throwable $e_pts) {
                // Não bloqueia a confirmação de entrega por falha no crédito de pontos
                error_log('[pedidos PATCH] Erro ao creditar pontos: ' . $e_pts->getMessage());
            }
        }
    }

    if ($entregador_id && in_array($u['role'], ['super_admin', 'gerente', 'caixa'], true)) {
        // Valida se é realmente entregador
        $chk = $pdo->prepare("SELECT id FROM usuarios WHERE id = :id AND role = 'entregador' AND ativo = 1");
        $chk->execute(['id' => $entregador_id]);
        if (!$chk->fetch()) json_response(['status' => 'error', 'message' => 'Entregador inválido.', 'data' => null], 422);
        $updates[] = 'entregador_id = :eid';
        $params['eid'] = $entregador_id;
    }

    if (empty($updates)) {
        json_response(['status' => 'error', 'message' => 'Nada para atualizar.', 'data' => null], 422);
    }

    $sql = 'UPDATE pedidos SET ' . implode(', ', $updates) . ' WHERE id = :id';
    $pdo->prepare($sql)->execute($params);

    json_response(['status' => 'ok', 'message' => 'Pedido atualizado.', 'data' => ['id' => (int) $id, 'status' => $novo_status ?: $pedido['status']]]);
}

// ─── PATCH /pedidos/{id}/itens — editar itens do pedido (operador em preparo) ──
if ($id !== null && $sub === 'itens' && $method === 'PATCH') {
    api_auth_role(['super_admin', 'gerente', 'caixa']);

    $stmt = $pdo->prepare("SELECT id, status, loja_id FROM pedidos WHERE id = :id");
    $stmt->execute(['id' => (int) $id]);
    $ped = $stmt->fetch();
    if (!$ped) json_response(['status' => 'error', 'message' => 'Pedido não encontrado.', 'data' => null], 404);
    if ($ped['status'] !== 'preparando') json_error('Só é possível editar itens de pedidos "Em Preparo".', 422);

    $novos_itens = $body['itens'] ?? [];
    if (empty($novos_itens)) json_error('itens não pode ser vazio.', 422);

    $pdo->beginTransaction();
    try {
        // Remove todos os itens atuais
        $pdo->prepare("DELETE FROM itens_pedido WHERE pedido_id = :pid")->execute(['pid' => (int) $id]);

        $total_novo = 0.0;
        $ins = $pdo->prepare("
            INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario, subtotal)
            VALUES (:pid, :prod, :qtd, :pu, :sub)
        ");
        foreach ($novos_itens as $it) {
            $pid_item = (int)   ($it['produto_id']    ?? 0);
            $qtd      = (float) ($it['quantidade']     ?? 0);
            $preco    = (float) ($it['preco_unitario'] ?? 0);
            if (!$pid_item || $qtd <= 0) continue;
            $sub = round($qtd * $preco, 2);
            $total_novo += $sub;
            $ins->execute(['pid' => (int) $id, 'prod' => $pid_item, 'qtd' => $qtd, 'pu' => $preco, 'sub' => $sub]);
        }

        // Recalcula total do pedido
        $pdo->prepare("UPDATE pedidos SET total = :total WHERE id = :id")->execute(['total' => $total_novo, 'id' => (int) $id]);

        $pdo->commit();
        json_response(['status' => 'ok', 'message' => 'Itens atualizados.', 'data' => ['total' => $total_novo]]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_error('Erro ao atualizar itens: ' . $e->getMessage(), 500);
    }
}

json_response(['status' => 'error', 'message' => 'Método não suportado.', 'data' => null], 405);
