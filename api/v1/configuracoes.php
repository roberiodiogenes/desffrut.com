<?php
// Guard
if (!function_exists('api_auth_exigir')) {
    http_response_code(403); header('Content-Type: application/json');
    echo json_encode(['status'=>'error','message'=>'Acesso direto não permitido.']); exit;
}
/**
 * Desffrut — API v1: Configurações & Campanhas (Fase 8)
 *
 * GET  /api/v1/configuracoes                  → todas as chaves (autenticado)
 * POST /api/v1/configuracoes                  → salva/atualiza chave (super_admin|gerente)
 *
 * GET  /api/v1/configuracoes/campanhas        → lista campanhas
 * POST /api/v1/configuracoes/campanhas        → cria campanha
 * PUT  /api/v1/configuracoes/campanhas/{n}    → edita campanha
 * PATCH /api/v1/configuracoes/campanhas/{n}   → ativa/desativa
 * DELETE /api/v1/configuracoes/campanhas/{n}  → exclui
 *
 * GET  /api/v1/configuracoes/banners          → lista banners (autenticado)
 * POST /api/v1/configuracoes/banners          → cria banner
 * PATCH /api/v1/configuracoes/banners/{n}     → atualiza campos
 * DELETE /api/v1/configuracoes/banners/{n}    → exclui
 *
 * GET  /api/v1/configuracoes/banners-publicos → banners ativos sem auth (para a home)
 */

$u    = api_auth_exigir();
$pdo  = db();
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── /configuracoes/campanhas ─────────────────────────────────────────────────
if ($id === 'campanhas') {
    // $sub vem do roteador: /configuracoes/campanhas/{sub}
    $campanha_id = (isset($sub) && is_numeric($sub)) ? (int)$sub : null;

    // GET — lista (qualquer role autenticado)
    if ($method === 'GET') {
        $apenas_ativas = filter_input(INPUT_GET, 'ativas', FILTER_VALIDATE_BOOLEAN);
        $sql = "SELECT * FROM campanhas";
        $params = [];
        if ($apenas_ativas) {
            $sql .= " WHERE ativo = 1 AND data_inicio <= NOW() AND data_fim >= NOW()";
        }
        $sql .= " ORDER BY data_inicio DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        json_response(['status'=>'ok','data'=>$stmt->fetchAll()]);
    }

    api_auth_role(['super_admin', 'gerente']);

    // POST — criar
    if ($method === 'POST' && $campanha_id === null) {
        $nome     = sanitize($body['nome']           ?? '');
        $tipo     = sanitize($body['tipo']           ?? 'cupom_global');
        $desconto = isset($body['valor_desconto']) && $body['valor_desconto'] !== null
                        ? (float)$body['valor_desconto'] : null;
        $classe   = sanitize($body['classe_css']     ?? '');
        $inicio   = sanitize($body['data_inicio']    ?? '');
        $fim      = sanitize($body['data_fim']       ?? '');

        if (!$nome || !$inicio || !$fim) {
            json_response(['status'=>'error','message'=>'Campos obrigatórios: nome, data_inicio, data_fim.'],422);
        }

        $pdo->prepare("INSERT INTO campanhas
                (nome, tipo, valor_desconto, classe_css, data_inicio, data_fim, ativo, criado_por)
            VALUES (:nome,:tipo,:desc,:css,:ini,:fim,1,:uid)")
            ->execute([':nome'=>$nome,':tipo'=>$tipo,':desc'=>$desconto,':css'=>$classe,
                ':ini'=>$inicio,':fim'=>$fim,':uid'=>$u['id']]);

        $novo_id = (int)$pdo->lastInsertId();
        registrar_log($u['id'], 'campanha_criada', 'campanhas', $novo_id, ['nome'=>$nome]);
        json_response(['status'=>'ok','message'=>'Campanha criada.','data'=>['id'=>$novo_id]]);
    }

    // PUT — editar
    if ($method === 'PUT' && $campanha_id) {
        $pdo->prepare("UPDATE campanhas
            SET nome=:nome, tipo=:tipo, valor_desconto=:desc, classe_css=:css,
                data_inicio=:ini, data_fim=:fim
            WHERE id=:id")
            ->execute([
                ':nome' => sanitize($body['nome']           ?? ''),
                ':tipo' => sanitize($body['tipo']           ?? 'cupom_global'),
                ':desc' => isset($body['valor_desconto']) && $body['valor_desconto'] !== null
                               ? (float)$body['valor_desconto'] : null,
                ':css'  => sanitize($body['classe_css']     ?? ''),
                ':ini'  => sanitize($body['data_inicio']    ?? ''),
                ':fim'  => sanitize($body['data_fim']       ?? ''),
                ':id'   => $campanha_id,
            ]);
        registrar_log($u['id'], 'campanha_editada', 'campanhas', $campanha_id, $body);
        json_response(['status'=>'ok','message'=>'Campanha atualizada.']);
    }

    // PATCH — ativar/desativar
    if ($method === 'PATCH' && $campanha_id) {
        if (!isset($body['ativo'])) {
            json_response(['status'=>'error','message'=>'Campo ativo obrigatório.'],422);
        }
        $ativo = (int)(bool)$body['ativo'];
        $pdo->prepare("UPDATE campanhas SET ativo=:a WHERE id=:id")
            ->execute([':a'=>$ativo,':id'=>$campanha_id]);
        json_response(['status'=>'ok','message'=>$ativo ? 'Campanha ativada.' : 'Campanha desativada.']);
    }

    // DELETE
    if ($method === 'DELETE' && $campanha_id) {
        $pdo->prepare("DELETE FROM campanhas WHERE id=:id")->execute([':id'=>$campanha_id]);
        registrar_log($u['id'], 'campanha_excluida', 'campanhas', $campanha_id, []);
        json_response(['status'=>'ok','message'=>'Campanha excluída.']);
    }

    json_response(['status'=>'error','message'=>'Método não suportado.'],405);
}

// ── /configuracoes/banners-publicos — sem role check (home pública) ──────────
if ($id === 'banners-publicos' && $method === 'GET') {
    $tipo = sanitize($_GET['tipo'] ?? 'desktop');
    $stmt = $pdo->prepare("SELECT imagem_path, link_destino FROM banners
        WHERE ativo = 1 AND tipo = :tipo
          AND (exibe_de  IS NULL OR exibe_de  <= NOW())
          AND (exibe_ate IS NULL OR exibe_ate >= NOW())
        ORDER BY ordem ASC");
    $stmt->execute([':tipo'=>$tipo]);
    json_response(['status'=>'ok','data'=>$stmt->fetchAll()]);
}

// ── /configuracoes/banners ────────────────────────────────────────────────────
if ($id === 'banners') {
    $banner_id = (isset($sub) && is_numeric($sub)) ? (int)$sub : null;

    // GET — lista
    if ($method === 'GET') {
        $tipo_filtro = sanitize($_GET['tipo'] ?? '');
        $sql = "SELECT * FROM banners";
        $params = [];
        if ($tipo_filtro) { $sql .= " WHERE tipo = :tipo"; $params[':tipo'] = $tipo_filtro; }
        $sql .= " ORDER BY ordem ASC, id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        json_response(['status'=>'ok','data'=>$stmt->fetchAll()]);
    }

    api_auth_role(['super_admin', 'gerente']);

    // POST — criar
    if ($method === 'POST' && $banner_id === null) {
        $path  = sanitize($body['imagem_path']  ?? '');
        $link  = sanitize($body['link_destino'] ?? '');
        $tipo  = sanitize($body['tipo']         ?? 'desktop');
        $ordem = (int)($body['ordem']           ?? 0);
        $ativo = isset($body['ativo']) ? (int)(bool)$body['ativo'] : 1;
        $de    = sanitize($body['exibe_de']     ?? '');
        $ate   = sanitize($body['exibe_ate']    ?? '');
        if (!$path) json_response(['status'=>'error','message'=>'imagem_path obrigatório.'],422);

        $pdo->prepare("INSERT INTO banners
                (imagem_path, link_destino, tipo, ordem, ativo, exibe_de, exibe_ate, criado_por)
            VALUES (:p,:l,:t,:o,:a,:de,:ate,:uid)")
            ->execute([':p'=>$path,':l'=>$link,':t'=>$tipo,':o'=>$ordem,':a'=>$ativo,
                ':de'=>$de?:null,':ate'=>$ate?:null,':uid'=>$u['id']]);
        json_response(['status'=>'ok','message'=>'Banner criado.',
            'data'=>['id'=>(int)$pdo->lastInsertId()]]);
    }

    // PATCH — atualizar campos
    if ($method === 'PATCH' && $banner_id) {
        $sets = []; $params = [':id'=>$banner_id];
        if (isset($body['ativo']))        { $sets[] = 'ativo=:a';        $params[':a']=$body['ativo']?(int)1:0; }
        if (isset($body['ordem']))        { $sets[] = 'ordem=:o';        $params[':o']=(int)$body['ordem']; }
        if (isset($body['link_destino'])) { $sets[] = 'link_destino=:l'; $params[':l']=sanitize($body['link_destino']); }
        if (isset($body['tipo']))         { $sets[] = 'tipo=:t';         $params[':t']=sanitize($body['tipo']); }
        if (!$sets) json_response(['status'=>'error','message'=>'Nada a atualizar.'],422);
        $pdo->prepare("UPDATE banners SET ".implode(',',$sets)." WHERE id=:id")->execute($params);
        json_response(['status'=>'ok','message'=>'Banner atualizado.']);
    }

    // DELETE
    if ($method === 'DELETE' && $banner_id) {
        $pdo->prepare("DELETE FROM banners WHERE id=:id")->execute([':id'=>$banner_id]);
        json_response(['status'=>'ok','message'=>'Banner excluído.']);
    }

    json_response(['status'=>'error','message'=>'Método não suportado.'],405);
}

// ── GET /configuracoes — retorna todas as chaves ──────────────────────────────
if ($id === null && $method === 'GET') {
    $stmt = $pdo->query("SELECT chave, valor FROM configuracoes ORDER BY chave");
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    json_response(['status'=>'ok','data'=>$rows]);
}

// ── POST /configuracoes — salva/atualiza chave ────────────────────────────────
if ($id === null && $method === 'POST') {
    api_auth_role(['super_admin', 'gerente']);
    $chave = sanitize($body['chave'] ?? '');
    $valor = $body['valor'] ?? '';
    if (!$chave) json_response(['status'=>'error','message'=>'Campo chave obrigatório.'],422);

    $pdo->prepare("INSERT INTO configuracoes (chave, valor, atualizado_por)
            VALUES (:c, :v, :u)
            ON DUPLICATE KEY UPDATE valor=:v2, atualizado_por=:u2, atualizado_em=NOW()")
        ->execute([':c'=>$chave,':v'=>$valor,':u'=>$u['id'],':v2'=>$valor,':u2'=>$u['id']]);

    registrar_log($u['id'], 'configuracao_alterada', 'configuracoes', null, ['chave'=>$chave]);
    json_response(['status'=>'ok','message'=>'Configuração salva.']);
}

json_response(['status'=>'error','message'=>'Rota não encontrada.'],404);
