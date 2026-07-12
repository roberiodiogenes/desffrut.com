<?php
/**
 * Desffrut — API v1: Vendas (PDV)
 *
 * POST /api/v1/vendas              → registrar venda (online ou sync offline)
 * GET  /api/v1/vendas?caixa_id=X  → histórico de vendas do caixa aberto
 * PUT  /api/v1/vendas/{id}         → cancelamento (requer validação de senha do gerente)
 *
 * Política de conflito no sync (Seção 3.3 do briefing):
 *   - Preço: snapshot do cupom prevalece (imutável).
 *   - Estoque: ajustado pela quantidade vendida.
 *   - Estoque negativo resultante: registrado com alerta (não bloqueia o sync).
 */

$u = api_auth_exigir();
api_auth_role(['caixa', 'gerente', 'super_admin']);

$pdo  = db();
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── GET /vendas?caixa_id=X ────────────────────────────────────────────────────
if ($id === null && $method === 'GET') {
    $caixa_id = filter_input(INPUT_GET, 'caixa_id', FILTER_VALIDATE_INT);
    $loja_id  = filter_input(INPUT_GET, 'loja_id',  FILTER_VALIDATE_INT) ?: null;
    $data     = sanitize($_GET['data'] ?? date('Y-m-d'));

    if (!$caixa_id && !$loja_id) {
        json_response(['status' => 'error', 'message' => 'caixa_id ou loja_id obrigatório.', 'data' => null], 422);
    }

    $where  = 'WHERE v.status = "finalizada"';
    $params = [];
    if ($caixa_id) { $where .= ' AND v.caixa_id = :caixa_id'; $params['caixa_id'] = $caixa_id; }
    if ($loja_id)  { $where .= ' AND v.loja_id  = :loja_id';  $params['loja_id']  = $loja_id; }
    if ($data)     { $where .= ' AND DATE(v.created_at) = :data'; $params['data'] = $data; }

    // Gerente é responsável por todas as lojas (mesma visão multi-loja do
    // super_admin) — filtra por loja_id apenas se ele informar explicitamente.

    $stmt = $pdo->prepare("
        SELECT
            v.id, v.cupom_uuid, v.total, v.desconto, v.total_final,
            v.forma_pagamento, v.pontos_ganhos, v.synced_at, v.created_at,
            c.nome AS cliente_nome, c.cpf AS cliente_cpf,
            u.nome AS operador_nome
        FROM vendas v
        LEFT JOIN usuarios c ON c.id = v.cliente_id
        LEFT JOIN usuarios u ON u.id = (
            SELECT usuario_id FROM caixas WHERE id = v.caixa_id LIMIT 1
        )
        {$where}
        ORDER BY v.created_at DESC
        LIMIT 300
    ");
    $stmt->execute($params);
    $vendas = $stmt->fetchAll();

    // Agrega totais
    $totais = [
        'qtd'     => count($vendas),
        'bruto'   => 0.0,
        'desconto' => 0.0,
        'liquido' => 0.0,
    ];
    foreach ($vendas as $v) {
        $totais['bruto']   += (float) $v['total'];
        $totais['desconto'] += (float) $v['desconto'];
        $totais['liquido'] += (float) $v['total_final'];
    }

    json_response(['status' => 'ok', 'data' => ['vendas' => $vendas, 'totais' => $totais], 'message' => '']);
}

// ── POST /vendas — registrar venda ────────────────────────────────────────────
if ($id === null && $method === 'POST') {

    // ── Validações de entrada ─────────────────────────────────────────────────
    $caixa_id        = (int)    ($body['caixa_id']        ?? 0);
    $loja_id         = (int)    ($body['loja_id']         ?? $u['loja_id'] ?? 0);
    $cliente_id      = isset($body['cliente_id']) && $body['cliente_id'] ? (int) $body['cliente_id'] : null;
    $forma_pagamento = sanitize($body['forma_pagamento']  ?? '');
    $desconto        = (float)  ($body['desconto']        ?? 0);
    $pontos_usados   = (int)    ($body['pontos_usados']   ?? 0);  // pontos resgatados como desconto
    $itens           = $body['itens'] ?? [];
    $cupom_uuid      = sanitize($body['cupom_uuid']       ?? '');  // UUID gerado offline

    $formas_validas = ['dinheiro', 'debito', 'credito', 'pix', 'pontos', 'misto'];
    if (!in_array($forma_pagamento, $formas_validas)) {
        json_response(['status' => 'error', 'message' => 'Forma de pagamento inválida.', 'data' => null], 422);
    }
    if (!$loja_id) {
        json_response(['status' => 'error', 'message' => 'loja_id obrigatório.', 'data' => null], 422);
    }
    if (empty($itens) || !is_array($itens)) {
        json_response(['status' => 'error', 'message' => 'Venda deve conter ao menos um item.', 'data' => null], 422);
    }

    // UUID obrigatório para idempotência (gerado pelo JS, online ou offline)
    if (empty($cupom_uuid)) {
        json_response(['status' => 'error', 'message' => 'cupom_uuid obrigatório.', 'data' => null], 422);
    }

    // Idempotência: cupom já sincronizado?
    $stmt = $pdo->prepare('SELECT id FROM vendas WHERE cupom_uuid = :uuid LIMIT 1');
    $stmt->execute(['uuid' => $cupom_uuid]);
    if ($existente = $stmt->fetch()) {
        json_response([
            'status'  => 'ok',
            'data'    => ['id' => $existente['id'], 'ja_sincronizado' => true],
            'message' => 'Cupom já registrado anteriormente.',
        ]);
    }

    // ── Calcula total a partir dos itens (preço snapshot) ─────────────────────
    $total_bruto = 0.0;
    $itens_processados = [];
    foreach ($itens as $item) {
        $produto_id = (int)   ($item['produto_id']              ?? 0);
        $quantidade = (float) ($item['quantidade']              ?? 0);
        $preco_snap = (float) ($item['preco_unitario_snapshot'] ?? 0);

        if (!$produto_id || $quantidade <= 0 || $preco_snap <= 0) {
            json_response(['status' => 'error', 'message' => "Item inválido: produto_id={$produto_id}.", 'data' => null], 422);
        }
        $subtotal      = round($quantidade * $preco_snap, 2);
        $total_bruto  += $subtotal;
        $itens_processados[] = [
            'produto_id' => $produto_id,
            'quantidade' => $quantidade,
            'preco_snap' => $preco_snap,
            'subtotal'   => $subtotal,
        ];
    }

    // Desconto por pontos (100 pts = R$ 1,00)
    $desconto_pontos = 0.0;
    if ($pontos_usados > 0 && $cliente_id) {
        $desconto_pontos = pontos_para_reais($pontos_usados);
    }
    $desconto_total = round($desconto + $desconto_pontos, 2);
    $total_final    = max(0, round($total_bruto - $desconto_total, 2));

    // Pontos ganhos nesta venda (sobre valor líquido)
    $pontos_ganhos = $cliente_id ? calcular_pontos($total_final) : 0;

    // ── Persiste em transação ──────────────────────────────────────────────────
    try {
        $pdo->beginTransaction();

        // Registra a venda
        $pdo->prepare('
            INSERT INTO vendas
                (caixa_id, loja_id, cliente_id, total, desconto, forma_pagamento,
                 pontos_ganhos, status, cupom_uuid, synced_at)
            VALUES
                (:caixa_id, :loja_id, :cliente_id, :total, :desconto, :fp,
                 :pts, "finalizada", :uuid, NOW())
        ')->execute([
            'caixa_id'   => $caixa_id ?: null,
            'loja_id'    => $loja_id,
            'cliente_id' => $cliente_id,
            'total'      => $total_bruto,
            'desconto'   => $desconto_total,
            'fp'         => $forma_pagamento,
            'pts'        => $pontos_ganhos,
            'uuid'       => $cupom_uuid,
        ]);
        $venda_id = (int) $pdo->lastInsertId();

        // Registra os itens e desconta estoque
        $stmtItem   = $pdo->prepare('
            INSERT INTO itens_venda (venda_id, produto_id, quantidade, preco_unitario_snapshot, subtotal)
            VALUES (:vid, :pid, :qty, :preco, :sub)
        ');
        $stmtEstoque = $pdo->prepare('
            UPDATE estoque
               SET quantidade = GREATEST(0, quantidade - :qty)
             WHERE produto_id = :pid AND loja_id = :loja_id
        ');
        // Alerta de estoque negativo: identifica produtos que ficaram em 0
        $stmtAlerta = $pdo->prepare('
            SELECT id, quantidade FROM estoque
             WHERE produto_id = :pid AND loja_id = :loja_id
        ');

        $alertas_estoque = [];
        foreach ($itens_processados as $item) {
            $stmtItem->execute([
                'vid'   => $venda_id,
                'pid'   => $item['produto_id'],
                'qty'   => $item['quantidade'],
                'preco' => $item['preco_snap'],
                'sub'   => $item['subtotal'],
            ]);
            $stmtEstoque->execute(['qty' => $item['quantidade'], 'pid' => $item['produto_id'], 'loja_id' => $loja_id]);
            // Verifica se zerou
            $stmtAlerta->execute(['pid' => $item['produto_id'], 'loja_id' => $loja_id]);
            $est = $stmtAlerta->fetch();
            if ($est && (float) $est['quantidade'] <= 0) {
                $alertas_estoque[] = $item['produto_id'];
            }
        }

        // Crédito/débito de pontos do cliente
        if ($cliente_id) {
            if ($pontos_ganhos > 0) {
                $pdo->prepare('
                    INSERT INTO pontos_fidelidade (cliente_id, operacao, pontos, referencia_id, referencia_tipo)
                    VALUES (:cid, "credito", :pts, :rid, "venda")
                ')->execute(['cid' => $cliente_id, 'pts' => $pontos_ganhos, 'rid' => $venda_id]);
                $pdo->prepare('UPDATE usuarios SET pontos_fidelidade = pontos_fidelidade + :pts WHERE id = :id')
                    ->execute(['pts' => $pontos_ganhos, 'id' => $cliente_id]);
            }
            if ($pontos_usados > 0) {
                $pdo->prepare('
                    INSERT INTO pontos_fidelidade (cliente_id, operacao, pontos, referencia_id, referencia_tipo)
                    VALUES (:cid, "debito", :pts, :rid, "resgate")
                ')->execute(['cid' => $cliente_id, 'pts' => $pontos_usados, 'rid' => $venda_id]);
                $pdo->prepare('UPDATE usuarios SET pontos_fidelidade = GREATEST(0, pontos_fidelidade - :pts) WHERE id = :id')
                    ->execute(['pts' => $pontos_usados, 'id' => $cliente_id]);
            }
        }

        $pdo->commit();

        registrar_log((int) $u['id'], 'venda_registrada', 'vendas', $venda_id, [
            'total_final'     => $total_final,
            'forma_pagamento' => $forma_pagamento,
            'itens'           => count($itens_processados),
            'alertas_estoque' => $alertas_estoque,
        ]);

        json_response([
            'status'  => 'ok',
            'data'    => [
                'id'              => $venda_id,
                'total_final'     => $total_final,
                'pontos_ganhos'   => $pontos_ganhos,
                'alertas_estoque' => $alertas_estoque, // lista de produto_ids com estoque zerado
            ],
            'message' => 'Venda registrada com sucesso.',
        ], 201);

    } catch (Throwable $e) {
        $pdo->rollBack();
        json_response(['status' => 'error', 'message' => 'Erro ao registrar venda: ' . $e->getMessage(), 'data' => null], 500);
    }
}

// ── PUT /vendas/{id} — cancelamento ──────────────────────────────────────────
if ($id !== null && $method === 'PUT') {
    $venda_id       = (int)    $id;
    $senha_gerente  = sanitize($body['senha_gerente'] ?? '');

    if (empty($senha_gerente) && $u['role'] !== 'super_admin') {
        json_response(['status' => 'error', 'message' => 'Senha do gerente obrigatória para cancelamento.', 'data' => null], 422);
    }

    // Valida senha do gerente (qualquer gerente ou super_admin da mesma loja)
    if ($u['role'] !== 'super_admin') {
        $stmt = $pdo->prepare('
            SELECT id FROM usuarios
             WHERE role IN ("gerente","super_admin")
               AND loja_id = :loja_id
               AND ativo = 1
             LIMIT 100
        ');
        $stmt->execute(['loja_id' => (int) ($u['loja_id'] ?? 0)]);
        $gerentes = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $autorizado = false;
        foreach ($gerentes as $gid) {
            $sg = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id = :id');
            $sg->execute(['id' => $gid]);
            $row = $sg->fetch();
            if ($row && password_verify($senha_gerente, $row['senha_hash'])) {
                $autorizado = true;
                break;
            }
        }
        if (!$autorizado) {
            json_response(['status' => 'error', 'message' => 'Senha do gerente inválida.', 'data' => null], 403);
        }
    }

    // Busca a venda
    $stmt = $pdo->prepare('SELECT * FROM vendas WHERE id = :id AND status = "finalizada"');
    $stmt->execute(['id' => $venda_id]);
    $venda = $stmt->fetch();
    if (!$venda) {
        json_response(['status' => 'error', 'message' => 'Venda não encontrada ou já cancelada.', 'data' => null], 404);
    }

    try {
        $pdo->beginTransaction();

        // Cancela a venda
        $pdo->prepare('UPDATE vendas SET status = "cancelada" WHERE id = :id')
            ->execute(['id' => $venda_id]);

        // Devolve estoque
        $itens = $pdo->prepare('SELECT produto_id, quantidade FROM itens_venda WHERE venda_id = :vid');
        $itens->execute(['vid' => $venda_id]);
        foreach ($itens->fetchAll() as $item) {
            $pdo->prepare('
                UPDATE estoque
                   SET quantidade = quantidade + :qty
                 WHERE produto_id = :pid AND loja_id = :lid
            ')->execute([
                'qty' => $item['quantidade'],
                'pid' => $item['produto_id'],
                'lid' => $venda['loja_id'],
            ]);
        }

        // Estorna pontos do cliente
        if ($venda['cliente_id'] && $venda['pontos_ganhos'] > 0) {
            $pdo->prepare('
                INSERT INTO pontos_fidelidade (cliente_id, operacao, pontos, referencia_id, referencia_tipo)
                VALUES (:cid, "estorno", :pts, :rid, "estorno_venda")
            ')->execute(['cid' => $venda['cliente_id'], 'pts' => $venda['pontos_ganhos'], 'rid' => $venda_id]);
            $pdo->prepare('UPDATE usuarios SET pontos_fidelidade = GREATEST(0, pontos_fidelidade - :pts) WHERE id = :id')
                ->execute(['pts' => $venda['pontos_ganhos'], 'id' => $venda['cliente_id']]);
        }

        $pdo->commit();

        registrar_log((int) $u['id'], 'cancelamento_venda', 'vendas', $venda_id, [
            'total_final' => $venda['total_final'],
        ]);

        json_response(['status' => 'ok', 'data' => null, 'message' => 'Venda cancelada e estoque devolvido.']);

    } catch (Throwable) {
        $pdo->rollBack();
        json_response(['status' => 'error', 'message' => 'Erro ao cancelar venda.', 'data' => null], 500);
    }
}

json_response(['status' => 'error', 'message' => 'Endpoint não encontrado.', 'data' => null], 404);
