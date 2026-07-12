<?php
/**
 * Desffrut — API v1: Leads / CRM (Fase 10)
 *
 * POST   /api/v1/leads/novo          → público — formulário de parcerias
 * GET    /api/v1/leads               → lista paginada (auth: gerente/super_admin)
 * GET    /api/v1/leads/{id}          → lead individual (auth)
 * PATCH  /api/v1/leads/{id}          → atualiza fase / dados (auth — Kanban drag)
 * DELETE /api/v1/leads/{id}          → remove lead (auth: super_admin)
 * POST   /api/v1/leads/importar      → importa CSV (auth: gerente/super_admin)
 */

// Guard de acesso direto
if (!function_exists('json_response')) {
    http_response_code(403); header('Content-Type: application/json');
    echo json_encode(['status'=>'error','message'=>'Acesso direto não permitido.']); exit;
}

$pdo  = db();
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── POST /leads/novo — público, sem auth ──────────────────────────────────────
if ($id === 'novo' && $method === 'POST') {
    $nome     = sanitize($body['nome']     ?? '');
    $telefone = preg_replace('/\D/', '', $body['telefone'] ?? '');
    $email    = trim($body['email']        ?? '');
    $empresa  = sanitize($body['empresa']  ?? '');
    $bairro   = sanitize($body['bairro']   ?? '');
    $mensagem = sanitize($body['mensagem'] ?? '');

    if (!$nome || !$telefone) {
        json_response(['status'=>'error','message'=>'Nome e telefone são obrigatórios.'], 422);
    }
    if (strlen($telefone) < 10) {
        json_response(['status'=>'error','message'=>'Telefone inválido (mínimo 10 dígitos).'], 422);
    }
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['status'=>'error','message'=>'E-mail inválido.'], 422);
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO leads (nome, telefone, email, empresa, bairro, mensagem, origem, fase)
            VALUES (:n, :t, :e, :emp, :b, :m, 'formulario', 'novo')
        ");
        $stmt->execute([
            ':n'   => $nome,
            ':t'   => $telefone,
            ':e'   => $email  ?: null,
            ':emp' => $empresa ?: null,
            ':b'   => $bairro  ?: null,
            ':m'   => $mensagem ?: null,
        ]);
        $novo_id = (int) $pdo->lastInsertId();
    } catch (\PDOException $ex) {
        // Duplicate telefone
        if ($ex->getCode() === '23000') {
            json_response(['status'=>'error','message'=>'Este telefone já está registrado. Entraremos em contato em breve!'], 409);
        }
        json_response(['status'=>'error','message'=>'Erro ao registrar. Tente novamente.'], 500);
    }

    registrar_log(null, 'lead_formulario', 'leads', $novo_id, ['nome'=>$nome,'origem'=>'formulario']);
    json_response(['status'=>'ok','message'=>'Obrigado! Em breve entraremos em contato.','data'=>['id'=>$novo_id]], 201);
}

// ── A partir daqui: autenticação obrigatória ──────────────────────────────────
$u = api_auth_role(['super_admin', 'gerente']);

// ── GET /leads — lista paginada ───────────────────────────────────────────────
if ($id === null && $method === 'GET') {
    $fase_f  = sanitize($_GET['fase']  ?? '');
    $busca_f = sanitize($_GET['q']     ?? '');
    $pg      = max(1, (int)($_GET['pg'] ?? 1));
    $por_pg  = 20;
    $offset  = ($pg - 1) * $por_pg;

    $where  = ['1=1'];
    $params = [];

    if ($fase_f && in_array($fase_f, ['novo','contato','proposta','negociacao','fechado','perdido'], true)) {
        $where[]          = 'fase = :fase';
        $params[':fase']  = $fase_f;
    }
    if ($busca_f) {
        $where[]          = '(nome LIKE :b OR empresa LIKE :b2 OR telefone LIKE :b3)';
        $params[':b']     = "%{$busca_f}%";
        $params[':b2']    = "%{$busca_f}%";
        $params[':b3']    = "%{$busca_f}%";
    }
    $w = implode(' AND ', $where);

    $total = (int)$pdo->prepare("SELECT COUNT(*) FROM leads WHERE {$w}")
                      ->execute($params) ? 0 : 0; // Replaced below
    $st = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE {$w}");
    $st->execute($params);
    $total = (int)$st->fetchColumn();

    $stmt = $pdo->prepare("SELECT * FROM leads WHERE {$w} ORDER BY criado_em DESC LIMIT {$por_pg} OFFSET {$offset}");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    json_response(['status'=>'ok','data'=>[
        'leads'    => $rows,
        'total'    => $total,
        'pagina'   => $pg,
        'paginas'  => (int)ceil($total / $por_pg),
        'por_pagina'=> $por_pg,
    ]]);
}

// ── GET /leads/{id} ───────────────────────────────────────────────────────────
if ($id && is_numeric($id) && $method === 'GET') {
    $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $lead = $stmt->fetch();
    if (!$lead) json_response(['status'=>'error','message'=>'Lead não encontrado.'], 404);
    json_response(['status'=>'ok','data'=>$lead]);
}

// ── PATCH /leads/{id} — atualizar fase ou dados ───────────────────────────────
if ($id && is_numeric($id) && $method === 'PATCH') {
    $stmt = $pdo->prepare("SELECT id, fase FROM leads WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $lead = $stmt->fetch();
    if (!$lead) json_response(['status'=>'error','message'=>'Lead não encontrado.'], 404);

    $sets   = [];
    $params = [':id' => $id];

    $fases_validas = ['novo','contato','proposta','negociacao','fechado','perdido'];

    if (isset($body['fase']) && in_array($body['fase'], $fases_validas, true)) {
        $sets[]         = 'fase = :fase';
        $params[':fase']= $body['fase'];
    }
    if (isset($body['nome'])) {
        $sets[]         = 'nome = :nome';
        $params[':nome']= sanitize($body['nome']);
    }
    if (isset($body['empresa'])) {
        $sets[]          = 'empresa = :empresa';
        $params[':empresa'] = sanitize($body['empresa']);
    }
    if (isset($body['telefone'])) {
        $sets[]           = 'telefone = :tel';
        $params[':tel']   = preg_replace('/\D/', '', $body['telefone']);
    }
    if (isset($body['email'])) {
        $sets[]           = 'email = :email';
        $params[':email'] = trim($body['email']) ?: null;
    }
    if (isset($body['bairro'])) {
        $sets[]           = 'bairro = :bairro';
        $params[':bairro']= sanitize($body['bairro']);
    }
    if (isset($body['mensagem'])) {
        $sets[]              = 'mensagem = :mensagem';
        $params[':mensagem'] = sanitize($body['mensagem']);
    }
    if (isset($body['atribuido_a'])) {
        $sets[]                = 'atribuido_a = :atrib';
        $params[':atrib']      = filter_var($body['atribuido_a'], FILTER_VALIDATE_INT) ?: null;
    }

    if (!$sets) {
        json_response(['status'=>'error','message'=>'Nada para atualizar.'], 422);
    }

    $pdo->prepare("UPDATE leads SET " . implode(', ', $sets) . " WHERE id = :id")
        ->execute($params);

    $det = [];
    if (isset($params[':fase'])) $det['fase_anterior'] = $lead['fase'];
    registrar_log($u['id'], 'lead_atualizado', 'leads', (int)$id, $det);
    json_response(['status'=>'ok','message'=>'Lead atualizado.']);
}

// ── DELETE /leads/{id} ────────────────────────────────────────────────────────
if ($id && is_numeric($id) && $method === 'DELETE') {
    api_auth_role(['super_admin']); // apenas super_admin exclui
    $stmt = $pdo->prepare("SELECT id, nome FROM leads WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $lead = $stmt->fetch();
    if (!$lead) json_response(['status'=>'error','message'=>'Lead não encontrado.'], 404);

    $pdo->prepare("DELETE FROM leads WHERE id = :id")->execute([':id' => $id]);
    registrar_log($u['id'], 'lead_removido', 'leads', (int)$id, ['nome'=>$lead['nome']]);
    json_response(['status'=>'ok','message'=>'Lead removido.']);
}

// ── POST /leads/importar — upload CSV ────────────────────────────────────────
if ($id === 'importar' && $method === 'POST') {
    // Recebe via multipart/form-data
    if (empty($_FILES['csv']['tmp_name'])) {
        json_response(['status'=>'error','message'=>'Arquivo CSV não enviado.'], 422);
    }

    $tmp  = $_FILES['csv']['tmp_name'];
    $ext  = strtolower(pathinfo($_FILES['csv']['name'] ?? '', PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        json_response(['status'=>'error','message'=>'Apenas arquivos .csv são aceitos.'], 422);
    }

    $fh = fopen($tmp, 'r');
    if (!$fh) json_response(['status'=>'error','message'=>'Não foi possível ler o arquivo.'], 500);

    // Detecta e descarta BOM UTF-8
    $bom = fread($fh, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($fh);

    // Primeira linha = cabeçalho
    $header = fgetcsv($fh, 0, ';') ?: fgetcsv($fh, 0, ',');
    if (!$header) {
        fclose($fh);
        json_response(['status'=>'error','message'=>'CSV vazio ou inválido.'], 422);
    }

    // Normaliza cabeçalhos (lower + sem espaço)
    $header = array_map(fn($h) => strtolower(trim($h)), $header);

    // Mapeamento flexível de colunas
    $mapa = [
        'nome'     => ['nome','name','contato'],
        'telefone' => ['telefone','fone','phone','cel','celular','whatsapp'],
        'bairro'   => ['bairro','bairro/cidade','neighborhood'],
        'empresa'  => ['empresa','company','razao','razão social'],
        'email'    => ['email','e-mail','mail'],
    ];

    $col = [];
    foreach ($mapa as $campo => $aliases) {
        foreach ($aliases as $alias) {
            $pos = array_search($alias, $header, true);
            if ($pos !== false) { $col[$campo] = $pos; break; }
        }
    }

    if (!isset($col['nome']) || !isset($col['telefone'])) {
        fclose($fh);
        json_response(['status'=>'error','message'=>'CSV precisa ter colunas "nome" e "telefone".'], 422);
    }

    $inseridos = 0;
    $ignorados = 0;
    $erros     = 0;
    $sep       = ';'; // default

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO leads (nome, telefone, email, empresa, bairro, origem, fase)
        VALUES (:n, :t, :e, :emp, :b, 'csv', 'novo')
    ");

    while (($row = fgetcsv($fh, 0, $sep)) !== false) {
        if (count($row) < 2) { $ignorados++; continue; }

        $nome     = sanitize(trim($row[$col['nome']]     ?? ''));
        $telefone = preg_replace('/\D/', '', trim($row[$col['telefone']] ?? ''));
        $email    = trim($row[$col['email']    ?? -1] ?? '');
        $empresa  = sanitize(trim($row[$col['empresa']  ?? -1] ?? ''));
        $bairro   = sanitize(trim($row[$col['bairro']   ?? -1] ?? ''));

        if (!$nome || strlen($telefone) < 10) { $ignorados++; continue; }
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';

        try {
            $stmt->execute([
                ':n'   => $nome,
                ':t'   => $telefone,
                ':e'   => $email   ?: null,
                ':emp' => $empresa  ?: null,
                ':b'   => $bairro   ?: null,
            ]);
            if ($stmt->rowCount() > 0) $inseridos++;
            else $ignorados++;
        } catch (\Throwable $e) {
            $erros++;
        }
    }
    fclose($fh);

    registrar_log($u['id'], 'leads_csv_importado', 'leads', null,
        ['inseridos'=>$inseridos,'ignorados'=>$ignorados,'erros'=>$erros]);

    json_response(['status'=>'ok','message'=>"Importação concluída: {$inseridos} inseridos, {$ignorados} ignorados, {$erros} erros.",'data'=>[
        'inseridos' => $inseridos,
        'ignorados' => $ignorados,
        'erros'     => $erros,
    ]]);
}

json_response(['status'=>'error','message'=>'Rota de leads não encontrada.'], 404);
