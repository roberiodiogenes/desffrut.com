<?php
// Guard
if (!function_exists('api_auth_exigir')) {
    http_response_code(403); header('Content-Type: application/json');
    echo json_encode(['status'=>'error','message'=>'Acesso direto não permitido.']); exit;
}
/**
 * Desffrut — API v1: Dev Mode (Fase 9)
 * ATENÇÃO: todos os endpoints exigem role dev_admin estritamente.
 * Nem super_admin tem acesso — verificação manual (sem usar api_auth_role).
 *
 * GET  /api/v1/dev/status          → flags do sistema + contadores
 * POST /api/v1/dev/manutencao      → ativa/desativa modo manutenção
 * POST /api/v1/dev/inadimplencia   → ativa/desativa aviso de inadimplência
 * POST /api/v1/dev/reset-senha     → reset forçado de senha + envio por e-mail
 * GET  /api/v1/dev/auditoria       → log forense paginado (com filtro por IP)
 * GET  /api/v1/dev/usuarios        → lista usuários para o selector de reset
 */

// ── Autenticação estrita — apenas dev_admin ───────────────────────────────────
$u = api_auth_exigir();
if ($u['role'] !== 'dev_admin') {
    json_response(['status'=>'error','message'=>'Área restrita ao administrador de sistema.'], 403);
}
$pdo  = db();
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── GET /dev/status ───────────────────────────────────────────────────────────
if ($id === 'status' && $method === 'GET') {
    $cfg = [];
    try {
        $rows = $pdo->query("SELECT chave, valor FROM configuracoes")->fetchAll(PDO::FETCH_KEY_PAIR);
        $cfg  = $rows;
    } catch (Throwable $e) { }

    $counts = [];
    try {
        $counts['usuarios']    = (int)$pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
        $counts['pedidos_hoje']= (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE DATE(criado_em) = CURDATE()")->fetchColumn();
        $counts['logs_hoje']   = (int)$pdo->query("SELECT COUNT(*) FROM logs_auditoria WHERE DATE(criado_em) = CURDATE()")->fetchColumn();
        $counts['lojas']       = (int)$pdo->query("SELECT COUNT(*) FROM lojas WHERE ativo = 1")->fetchColumn();
    } catch (Throwable $e) { }

    json_response(['status'=>'ok','data'=>[
        'manutencao_ativa'    => ($cfg['manutencao_ativa']    ?? '0') === '1',
        'manutencao_msg'      => $cfg['manutencao_msg']       ?? '',
        'inadimplencia_ativa' => ($cfg['inadimplencia_ativa'] ?? '0') === '1',
        'inadimplencia_msg'   => $cfg['inadimplencia_msg']    ?? '',
        'modo_restrito'       => ($cfg['modo_restrito']        ?? '0') === '1',
        'motivo_restricao'    => $cfg['motivo_restricao']      ?? '',
        'counts'              => $counts,
        'servidor_php'        => PHP_VERSION,
        'now'                 => date('Y-m-d H:i:s'),
    ]]);
}

// ── POST /dev/manutencao — toggle modo manutenção ─────────────────────────────
if ($id === 'manutencao' && $method === 'POST') {
    $ativo = isset($body['ativo']) ? ($body['ativo'] ? '1' : '0') : null;
    $msg   = isset($body['msg']) ? trim($body['msg']) : null;

    if ($ativo !== null) {
        $pdo->prepare("INSERT INTO configuracoes (chave, valor, atualizado_por)
            VALUES ('manutencao_ativa',:v,:u)
            ON DUPLICATE KEY UPDATE valor=:v2, atualizado_por=:u2, atualizado_em=NOW()")
            ->execute([':v'=>$ativo,':u'=>$u['id'],':v2'=>$ativo,':u2'=>$u['id']]);
    }
    if ($msg !== null && $msg !== '') {
        $pdo->prepare("INSERT INTO configuracoes (chave, valor, atualizado_por)
            VALUES ('manutencao_msg',:v,:u)
            ON DUPLICATE KEY UPDATE valor=:v2, atualizado_por=:u2, atualizado_em=NOW()")
            ->execute([':v'=>$msg,':u'=>$u['id'],':v2'=>$msg,':u2'=>$u['id']]);
    }

    $acao = $ativo === '1' ? 'manutencao_ativada' : 'manutencao_desativada';
    registrar_log($u['id'], $acao, 'configuracoes', null, ['dev_admin'=>$u['nome']]);
    json_response(['status'=>'ok','message'=> $ativo === '1' ? 'Modo manutenção ATIVADO.' : 'Modo manutenção DESATIVADO.']);
}

// ── POST /dev/inadimplencia — toggle aviso de inadimplência ──────────────────
if ($id === 'inadimplencia' && $method === 'POST') {
    $ativo = isset($body['ativo']) ? ($body['ativo'] ? '1' : '0') : null;
    $msg   = isset($body['msg']) ? trim($body['msg']) : null;

    if ($ativo !== null) {
        $pdo->prepare("INSERT INTO configuracoes (chave, valor, atualizado_por)
            VALUES ('inadimplencia_ativa',:v,:u)
            ON DUPLICATE KEY UPDATE valor=:v2, atualizado_por=:u2, atualizado_em=NOW()")
            ->execute([':v'=>$ativo,':u'=>$u['id'],':v2'=>$ativo,':u2'=>$u['id']]);
    }
    if ($msg !== null && $msg !== '') {
        $pdo->prepare("INSERT INTO configuracoes (chave, valor, atualizado_por)
            VALUES ('inadimplencia_msg',:v,:u)
            ON DUPLICATE KEY UPDATE valor=:v2, atualizado_por=:u2, atualizado_em=NOW()")
            ->execute([':v'=>$msg,':u'=>$u['id'],':v2'=>$msg,':u2'=>$u['id']]);
    }

    $acao = $ativo === '1' ? 'inadimplencia_ativada' : 'inadimplencia_desativada';
    registrar_log($u['id'], $acao, 'configuracoes', null, ['dev_admin'=>$u['nome']]);
    json_response(['status'=>'ok','message'=> $ativo === '1' ? 'Aviso de inadimplência ATIVADO.' : 'Aviso de inadimplência DESATIVADO.']);
}

// ── POST /dev/modo-restrito — toggle modo restrito (Categoria 22) ───────────
if ($id === 'modo-restrito' && $method === 'POST') {
    $ativo  = isset($body['ativo'])  ? ($body['ativo'] ? '1' : '0') : null;
    $motivo = isset($body['motivo']) ? trim($body['motivo'])          : null;

    if ($ativo !== null) {
        $pdo->prepare("INSERT INTO configuracoes (chave, valor, atualizado_por)
            VALUES ('modo_restrito',:v,:u)
            ON DUPLICATE KEY UPDATE valor=:v2, atualizado_por=:u2, atualizado_em=NOW()")
            ->execute([':v'=>$ativo,':u'=>$u['id'],':v2'=>$ativo,':u2'=>$u['id']]);
    }
    if ($motivo !== null) {
        $pdo->prepare("INSERT INTO configuracoes (chave, valor, atualizado_por)
            VALUES ('motivo_restricao',:v,:u)
            ON DUPLICATE KEY UPDATE valor=:v2, atualizado_por=:u2, atualizado_em=NOW()")
            ->execute([':v'=>$motivo,':u'=>$u['id'],':v2'=>$motivo,':u2'=>$u['id']]);
    }

    if ($ativo !== null) {
        registrar_log($u['id'], $ativo === '1' ? 'modo_restrito_ativado' : 'modo_restrito_desativado',
            'configuracoes', null, ['dev_admin'=>$u['nome']]);
    }
    $msg = $ativo === '1' ? '🔒 Modo Restrito ATIVADO.'
         : ($ativo === '0' ? '🔓 Modo Restrito DESATIVADO.' : '✅ Motivo salvo.');
    json_response(['status'=>'ok','message'=>$msg]);
}

// ── POST /dev/reset-senha ─────────────────────────────────────────────────────
if ($id === 'reset-senha' && $method === 'POST') {
    $usuario_id = filter_var($body['usuario_id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$usuario_id) {
        json_response(['status'=>'error','message'=>'usuario_id inválido.'], 422);
    }

    // Busca o usuário alvo
    $stmt = $pdo->prepare("SELECT id, nome, email, role FROM usuarios WHERE id = :id AND ativo = 1 LIMIT 1");
    $stmt->execute([':id'=>$usuario_id]);
    $alvo = $stmt->fetch();
    if (!$alvo) {
        json_response(['status'=>'error','message'=>'Usuário não encontrado ou inativo.'], 404);
    }

    // Gera senha temporária: 12 caracteres alfanuméricos mistos
    $chars  = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#';
    $senha  = '';
    for ($i = 0; $i < 12; $i++) {
        $senha .= $chars[random_int(0, strlen($chars) - 1)];
    }
    $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);

    // Salva hash + flag de troca obrigatória
    $pdo->prepare("UPDATE usuarios SET senha_hash = :h, trocar_senha_prox_login = 1 WHERE id = :id")
        ->execute([':h'=>$hash, ':id'=>$usuario_id]);

    // Log de auditoria (não armazena senha em texto)
    registrar_log($u['id'], 'reset_senha_forcado', 'usuarios', $usuario_id,
        ['alvo_email'=>$alvo['email'], 'alvo_role'=>$alvo['role'], 'dev_admin'=>$u['nome']]);

    // Tenta enviar e-mail
    $email_ok = false;
    $assunto  = '[Desffrut] Redefinição de senha solicitada';
    $corpo    = "Olá, {$alvo['nome']}!\n\n"
              . "O administrador do sistema redefiniu sua senha de acesso ao Desffrut.\n\n"
              . "Sua nova senha temporária é:\n\n"
              . "    {$senha}\n\n"
              . "Você será solicitado a criar uma nova senha no próximo acesso.\n\n"
              . "Se você não reconhece esta ação, entre em contato com o suporte imediatamente.\n\n"
              . "— Equipe Desffrut";
    $headers  = "From: noreply@desffrut.com.br\r\nContent-Type: text/plain; charset=UTF-8\r\n";

    try {
        $email_ok = mail($alvo['email'], $assunto, $corpo, $headers);
    } catch (Throwable $e) { $email_ok = false; }

    json_response(['status'=>'ok', 'data'=>[
        'email_enviado' => $email_ok,
        'email_destino' => $alvo['email'],
        // Retorna a senha apenas se o e-mail falhou, para que o dev possa repassar manualmente
        'senha_temp'    => $email_ok ? null : $senha,
        'aviso'         => $email_ok
            ? 'Senha redefinida e e-mail enviado ao usuário.'
            : 'Senha redefinida. E-mail NÃO enviado (configure o servidor de e-mail). Copie a senha abaixo.',
    ]]);
}

// ── GET /dev/usuarios — lista para selector ───────────────────────────────────
if ($id === 'usuarios' && $method === 'GET') {
    $busca = sanitize($_GET['q'] ?? '');
    $sql   = "SELECT id, nome, email, role, ativo FROM usuarios";
    $params = [];
    if ($busca) {
        $sql .= " WHERE (nome LIKE :b OR email LIKE :b2) AND ativo = 1";
        $params = [':b'=>"%{$busca}%", ':b2'=>"%{$busca}%"];
    } else {
        $sql .= " WHERE ativo = 1";
    }
    $sql .= " ORDER BY nome LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    json_response(['status'=>'ok','data'=>$stmt->fetchAll()]);
}

// ── GET /dev/auditoria — log forense paginado ────────────────────────────────
if ($id === 'auditoria' && $method === 'GET') {
    $pg      = max(1, (int)($_GET['pg']     ?? 1));
    $por_pg  = 50;
    $offset  = ($pg - 1) * $por_pg;
    $ip_f    = sanitize($_GET['ip']         ?? '');
    $acao_f  = sanitize($_GET['acao']       ?? '');
    $de_f    = sanitize($_GET['de']         ?? '');
    $ate_f   = sanitize($_GET['ate']        ?? '');

    $where   = ['1=1'];
    $params  = [];

    if ($ip_f)   { $where[] = 'la.ip LIKE :ip';           $params[':ip']  = "%{$ip_f}%"; }
    if ($acao_f) { $where[] = 'la.acao = :acao';          $params[':acao']= $acao_f; }
    if ($de_f)   { $where[] = 'la.criado_em >= :de';      $params[':de']  = $de_f.' 00:00:00'; }
    if ($ate_f)  { $where[] = 'la.criado_em <= :ate';     $params[':ate'] = $ate_f.' 23:59:59'; }

    $where_sql = implode(' AND ', $where);

    $total = (int)$pdo->prepare("SELECT COUNT(*) FROM logs_auditoria la WHERE {$where_sql}")
                      ->execute($params) ? $pdo->prepare("SELECT COUNT(*) FROM logs_auditoria la WHERE {$where_sql}")
                                               ->execute($params) : 0;
    // Contagem
    $stmt_c = $pdo->prepare("SELECT COUNT(*) FROM logs_auditoria la WHERE {$where_sql}");
    $stmt_c->execute($params);
    $total = (int)$stmt_c->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT la.id, la.acao, la.tabela_afetada, la.registro_id,
               la.detalhes_json, la.ip, la.criado_em,
               u.nome AS usuario_nome, u.email AS usuario_email, u.role AS usuario_role
        FROM logs_auditoria la
        LEFT JOIN usuarios u ON u.id = la.usuario_id
        WHERE {$where_sql}
        ORDER BY la.id DESC
        LIMIT {$por_pg} OFFSET {$offset}
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    // Decodifica detalhes_json para objeto
    foreach ($logs as &$log) {
        if ($log['detalhes_json']) {
            $log['detalhes'] = json_decode($log['detalhes_json'], true);
        }
        unset($log['detalhes_json']);
    }
    unset($log);

    json_response(['status'=>'ok','data'=>[
        'logs'       => $logs,
        'total'      => $total,
        'pagina'     => $pg,
        'por_pagina' => $por_pg,
        'paginas'    => (int)ceil($total / $por_pg),
    ]]);
}

json_response(['status'=>'error','message'=>'Rota dev não encontrada.'], 404);
