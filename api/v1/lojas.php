<?php
/**
 * Desffrut — API v1: Lojas
 * GET /api/v1/lojas        → lista lojas ativas (rota pública)
 * PUT /api/v1/lojas/{id}   → atualiza horario_funcionamento e whatsapp_link (super_admin) [Fase 8]
 */
require_once __DIR__ . '/../../app/models/Loja.php';

if ($method === 'GET') {
    // ?todas=1 → retorna ativas + inativas (requer super_admin)
    if (!empty($_GET['todas'])) {
        $u = api_auth_exigir();
        api_auth_role(['super_admin']);
        $pdo   = db();
        $lojas = $pdo->query("SELECT * FROM lojas ORDER BY ativo DESC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $lojas = (new Loja())->listarAtivas();
    }
    json_response(['status' => 'ok', 'data' => $lojas, 'message' => '']);
}

// ── PUT /lojas/{id} — atualizar dados públicos da loja (Fase 8) ───────────────
if ($method === 'PUT' && $id) {
    $u = api_auth_exigir();
    api_auth_role(['super_admin', 'gerente']);

    $d        = json_decode(file_get_contents('php://input'), true) ?? [];
    $horario  = sanitize($d['horario_funcionamento'] ?? '');
    $whatsapp = sanitize($d['whatsapp_link']         ?? '');
    $loja_id  = (int)$id;

    $pdo = db();
    $ok  = $pdo->prepare("UPDATE lojas SET horario_funcionamento=:h, whatsapp_link=:w WHERE id=:id")
               ->execute([':h'=>$horario, ':w'=>$whatsapp, ':id'=>$loja_id]);

    if ($ok) {
        registrar_log($u['id'], 'loja_dados_atualizados', 'lojas', $loja_id,
            ['horario'=>$horario,'whatsapp'=>$whatsapp]);
        json_response(['status'=>'ok','message'=>'Dados da loja atualizados.']);
    } else {
        json_response(['status'=>'error','message'=>'Erro ao atualizar.'],500);
    }
}

// ── POST /lojas — criar nova loja (Fase 8) ────────────────────────────────────
if ($method === 'POST' && $id === null) {
    $u = api_auth_exigir();
    api_auth_role(['super_admin']); // somente super_admin cria lojas

    $d       = json_decode(file_get_contents('php://input'), true) ?? [];
    $nome    = sanitize($d['nome']                   ?? '');
    $end     = sanitize($d['endereco']               ?? '');
    $tel     = sanitize($d['telefone']               ?? '');
    $horario = sanitize($d['horario_funcionamento']  ?? '');
    $whats   = sanitize($d['whatsapp_link']          ?? '');

    if (!$nome) json_response(['status'=>'error','message'=>'Nome da loja é obrigatório.'],422);

    $pdo = db();
    $pdo->prepare("INSERT INTO lojas (nome, endereco, telefone, ativo, horario_funcionamento, whatsapp_link)
        VALUES (:n, :e, :t, 1, :h, :w)")
        ->execute([':n'=>$nome,':e'=>$end,':t'=>$tel,':h'=>$horario,':w'=>$whats]);
    $nova_id = (int)$pdo->lastInsertId();
    registrar_log($u['id'], 'loja_criada', 'lojas', $nova_id, ['nome'=>$nome]);
    json_response(['status'=>'ok','message'=>'Loja criada com sucesso.','data'=>['id'=>$nova_id]]);
}

// ── PATCH /lojas/{id} — ativar ou desativar loja ─────────────────────────────
if ($method === 'PATCH' && $id) {
    $u = api_auth_exigir();
    api_auth_role(['super_admin']);

    $d    = json_decode(file_get_contents('php://input'), true) ?? [];
    $ativo = isset($d['ativo']) ? (int)(bool)$d['ativo'] : null;
    if ($ativo === null) json_response(['status'=>'error','message'=>'Campo ativo é obrigatório.'],422);

    $pdo = db();
    $pdo->prepare("UPDATE lojas SET ativo = :a WHERE id = :id")->execute([':a'=>$ativo,':id'=>(int)$id]);
    $acao = $ativo ? 'loja_ativada' : 'loja_desativada';
    registrar_log($u['id'], $acao, 'lojas', (int)$id);
    json_response(['status'=>'ok','message'=>$ativo ? 'Loja ativada.' : 'Loja desativada.']);
}

// ── DELETE /lojas/{id} — excluir loja (protegida) ────────────────────────────
if ($method === 'DELETE' && $id) {
    $u = api_auth_exigir();
    api_auth_role(['super_admin']);

    $pdo = db();
    $loja_id = (int)$id;

    // Verifica vínculos: funcionários ou pedidos
    $tem_func = (int)$pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE loja_id=:id")->execute([':id'=>$loja_id])
        ? $pdo->query("SELECT COUNT(*) FROM usuarios WHERE loja_id=$loja_id")->fetchColumn() : 0;
    $tem_pedidos = (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE loja_id=$loja_id")->fetchColumn();

    if ($tem_func > 0 || $tem_pedidos > 0) {
        json_response(['status'=>'error','message'=>'Loja possui funcionários ou pedidos vinculados. Desative-a em vez de excluir.'],409);
    }

    $pdo->prepare("DELETE FROM lojas WHERE id=:id")->execute([':id'=>$loja_id]);
    registrar_log($u['id'], 'loja_excluida', 'lojas', $loja_id);
    json_response(['status'=>'ok','message'=>'Loja excluída com sucesso.']);
}

json_response(['status' => 'error', 'message' => 'Método não permitido.', 'data' => null], 405);
