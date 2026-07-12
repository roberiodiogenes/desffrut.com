<?php
/**
 * Desffrut — API v1: Sincronização do PDV Offline (Background Sync)
 *
 * GET  /api/v1/sync/carga    → download em lote: produtos+preços+clientes → IndexedDB
 * POST /api/v1/sync/upload   → upload de cupons pendentes gravados offline
 *
 * Política de conflito (Seção 3.3 do briefing):
 *   - Preço: snapshot do cupom prevalece (imutável).
 *   - Estoque: deduzido pela quantidade vendida.
 *   - Estoque negativo resultante: registrado com alerta para o gerente (não bloqueia).
 *
 * O roteador (api/index.php) expõe $id e $sub:
 *   /api/v1/sync/carga   → $id = 'carga'
 *   /api/v1/sync/upload  → $id = 'upload'
 */

$u = api_auth_exigir();
api_auth_role(['caixa', 'gerente', 'super_admin']);

$pdo  = db();
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── GET /sync/carga — snapshot completo para o IndexedDB ──────────────────────
if ($id === 'carga' && $method === 'GET') {
    $loja_id = filter_input(INPUT_GET, 'loja_id', FILTER_VALIDATE_INT)
            ?: (int) ($u['loja_id'] ?? 0);

    if (!$loja_id) {
        json_response(['status' => 'error', 'message' => 'loja_id obrigatório.', 'data' => null], 422);
    }

    // ── Produtos ativos + preço vigente para a loja ───────────────────────────
    $stmtProd = $pdo->prepare('
        SELECT
            p.id, p.nome, p.ean, p.categoria, p.unidade_medida, p.foto,
            COALESCE(
                CASE
                    WHEN pr.promo_preco IS NOT NULL
                         AND NOW() BETWEEN pr.promo_inicio AND pr.promo_fim
                    THEN pr.promo_preco
                    ELSE pr.preco_venda
                END,
                0
            ) AS preco_vigente,
            pr.preco_venda,
            pr.promo_preco,
            pr.promo_inicio,
            pr.promo_fim,
            COALESCE(e.quantidade, 0) AS estoque
        FROM produtos p
        LEFT JOIN precos  pr ON pr.produto_id = p.id AND pr.loja_id = :loja_id
        LEFT JOIN estoque e  ON e.produto_id  = p.id AND e.loja_id  = :loja_id2
        WHERE p.ativo = 1
        ORDER BY p.categoria, p.nome
    ');
    $stmtProd->execute(['loja_id' => $loja_id, 'loja_id2' => $loja_id]);
    $produtos = $stmtProd->fetchAll();

    // ── Base mínima de clientes (id, cpf, nome, pontos) para busca offline ────
    $stmtClientes = $pdo->prepare('
        SELECT id, nome, cpf, pontos_fidelidade
        FROM usuarios
        WHERE role = "cliente" AND ativo = 1
        ORDER BY nome
        LIMIT 10000
    ');
    $stmtClientes->execute();
    $clientes = $stmtClientes->fetchAll();

    json_response([
        'status'  => 'ok',
        'data'    => [
            'loja_id'   => $loja_id,
            'gerado_em' => date('Y-m-d H:i:s'),
            'produtos'  => $produtos,
            'clientes'  => $clientes,
        ],
        'message' => count($produtos) . ' produtos e ' . count($clientes) . ' clientes carregados.',
    ]);
}

// ── POST /sync/upload — processa cupons offline ────────────────────────────────
if ($id === 'upload' && $method === 'POST') {

    $cupons = $body['cupons'] ?? [];
    if (empty($cupons) || !is_array($cupons)) {
        json_response(['status' => 'error', 'message' => 'Nenhum cupom para sincronizar.', 'data' => null], 422);
    }

    $resultados = [];

    foreach ($cupons as $cupom) {
        $cupom_uuid      = sanitize($cupom['cupom_uuid']      ?? '');
        $caixa_id        = (int)    ($cupom['caixa_id']       ?? 0);
        $loja_id         = (int)    ($cupom['loja_id']        ?? $u['loja_id'] ?? 0);
        $cliente_id      = isset($cupom['cliente_id']) && $cupom['cliente_id'] ? (int) $cupom['cliente_id'] : null;
        $forma_pagamento = sanitize($cupom['forma_pagamento'] ?? 'dinheiro');
        $desconto        = (float)  ($cupom['desconto']       ?? 0);
        $pontos_usados   = (int)    ($cupom['pontos_usados']  ?? 0);
        $itens           = $cupom['itens'] ?? [];
        $criado_em       = sanitize($cupom['created_at']      ?? date('Y-m-d H:i:s'));

        // Validações mínimas
        if (empty($cupom_uuid) || !$loja_id || empty($itens)) {
            $resultados[] = ['uuid' => $cupom_uuid, 'status' => 'erro', 'mensagem' => 'Dados incompletos.'];
            continue;
        }

        // Idempotência: já sincronizado?
        $stmt = $pdo->prepare('SELECT id FROM vendas WHERE cupom_uuid = :uuid');
        $stmt->execute(['uuid' => $cupom_uuid]);
        if ($existente = $stmt->fetch()) {
            $resultados[] = ['uuid' => $cupom_uuid, 'status' => 'ignorado', 'mensagem' => 'Já sincronizado.', 'venda_id' => $existente['id']];
            continue;
        }

        // Calcula totais a partir do snapshot (preço prevalece)
        $total_bruto = 0.0;
        $itens_ok    = true;
        $itens_proc  = [];
        foreach ($itens as $item) {
            $pid   = (int)   ($item['produto_id']              ?? 0);
            $qty   = (float) ($item['quantidade']              ?? 0);
            $preco = (float) ($item['preco_unitario_snapshot'] ?? 0);
            if (!$pid || $qty <= 0 || $preco <= 0) { $itens_ok = false; break; }
            $sub          = round($qty * $preco, 2);
            $total_bruto += $sub;
            $itens_proc[] = ['produto_id' => $pid, 'quantidade' => $qty, 'preco_snap' => $preco, 'subtotal' => $sub];
        }
        if (!$itens_ok) {
            $resultados[] = ['uuid' => $cupom_uuid, 'status' => 'erro', 'mensagem' => 'Item inválido no cupom.'];
            continue;
        }

        $desconto_total = round($desconto, 2);
        $total_final    = max(0, round($total_bruto - $desconto_total, 2));
        $pontos_ganhos  = $cliente_id ? calcular_pontos($total_final) : 0;

        try {
            $pdo->beginTransaction();

            // Insere a venda com data original do cupom offline
            $pdo->prepare('
                INSERT INTO vendas
                    (caixa_id, loja_id, cliente_id, total, desconto, forma_pagamento,
                     pontos_ganhos, status, cupom_uuid, synced_at, created_at)
                VALUES
                    (:cid, :lid, :clid, :total, :desc, :fp, :pts,
                     "finalizada", :uuid, NOW(), :criado_em)
            ')->execute([
                'cid'       => $caixa_id ?: null,
                'lid'       => $loja_id,
                'clid'      => $cliente_id,
                'total'     => $total_bruto,
                'desc'      => $desconto_total,
                'fp'        => $forma_pagamento,
                'pts'       => $pontos_ganhos,
                'uuid'      => $cupom_uuid,
                'criado_em' => $criado_em,
            ]);
            $venda_id = (int) $pdo->lastInsertId();

            // Itens + estoque
            $alertas = [];
            foreach ($itens_proc as $item) {
                $pdo->prepare('
                    INSERT INTO itens_venda (venda_id, produto_id, quantidade, preco_unitario_snapshot, subtotal)
                    VALUES (:vid, :pid, :qty, :preco, :sub)
                ')->execute([
                    'vid' => $venda_id, 'pid' => $item['produto_id'],
                    'qty' => $item['quantidade'], 'preco' => $item['preco_snap'], 'sub' => $item['subtotal'],
                ]);

                // Deduz estoque (política: quantidade, não preço)
                $pdo->prepare('
                    UPDATE estoque
                       SET quantidade = GREATEST(0, quantidade - :qty)
                     WHERE produto_id = :pid AND loja_id = :lid
                ')->execute(['qty' => $item['quantidade'], 'pid' => $item['produto_id'], 'lid' => $loja_id]);

                // Detecta estoque negativo (zerou)
                $chk = $pdo->prepare('SELECT quantidade FROM estoque WHERE produto_id = :pid AND loja_id = :lid');
                $chk->execute(['pid' => $item['produto_id'], 'lid' => $loja_id]);
                $estq = $chk->fetchColumn();
                if ($estq !== false && (float) $estq <= 0) {
                    $alertas[] = $item['produto_id'];
                }
            }

            // Pontos
            if ($cliente_id && $pontos_ganhos > 0) {
                $pdo->prepare('
                    INSERT INTO pontos_fidelidade (cliente_id, operacao, pontos, referencia_id, referencia_tipo)
                    VALUES (:cid, "credito", :pts, :rid, "venda")
                ')->execute(['cid' => $cliente_id, 'pts' => $pontos_ganhos, 'rid' => $venda_id]);
                $pdo->prepare('UPDATE usuarios SET pontos_fidelidade = pontos_fidelidade + :pts WHERE id = :id')
                    ->execute(['pts' => $pontos_ganhos, 'id' => $cliente_id]);
            }

            $pdo->commit();

            registrar_log((int) $u['id'], 'sync_venda_offline', 'vendas', $venda_id, [
                'uuid'    => $cupom_uuid,
                'alertas' => $alertas,
            ]);

            $resultados[] = [
                'uuid'      => $cupom_uuid,
                'status'    => 'sincronizado',
                'venda_id'  => $venda_id,
                'alertas_estoque' => $alertas,
                'mensagem'  => empty($alertas) ? 'OK' : count($alertas) . ' produto(s) com estoque zerado.',
            ];

        } catch (Throwable $e) {
            $pdo->rollBack();
            $resultados[] = ['uuid' => $cupom_uuid, 'status' => 'erro', 'mensagem' => $e->getMessage()];
        }
    }

    $sincronizados = count(array_filter($resultados, fn($r) => $r['status'] === 'sincronizado'));
    json_response([
        'status'  => 'ok',
        'data'    => ['resultados' => $resultados, 'sincronizados' => $sincronizados],
        'message' => "{$sincronizados} de " . count($cupons) . " cupons sincronizados.",
    ]);
}

json_response(['status' => 'error', 'message' => 'Endpoint não encontrado.', 'data' => null], 404);
