<?php
// Guard
if (!function_exists('api_auth_exigir')) {
    http_response_code(403); header('Content-Type: application/json');
    echo json_encode(['status'=>'error','message'=>'Acesso direto não permitido.']); exit;
}
require_once __DIR__ . '/../../app/middleware/modo_restrito.php'; // Categoria 22
/**
 * Desffrut — API v1: Ponto / Jornada (RH)
 *
 * GET  /api/v1/ponto?funcionario_id=X&mes=YYYY-MM  → registros do mês
 * POST /api/v1/ponto                                → registrar batimento
 * GET  /api/v1/ponto/resumo?funcionario_id=X&mes=  → banco de horas do mês
 * GET  /api/v1/ponto/folha                          → lista folhas de pagamento
 * POST /api/v1/ponto/folha                          → gerar/salvar folha do mês
 * PATCH /api/v1/ponto/folha/{id}                   → marcar folha como paga
 */

$u   = api_auth_exigir();
api_auth_role(['super_admin', 'rh_financeiro']);
$pdo  = db();
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── GET /ponto/resumo ─────────────────────────────────────────────────────────
if ($id === 'resumo' && $method === 'GET') {
    $fid = filter_input(INPUT_GET, 'funcionario_id', FILTER_VALIDATE_INT);
    $mes = sanitize($_GET['mes'] ?? date('Y-m'));
    if (!$fid) json_response(['status'=>'error','message'=>'funcionario_id obrigatório.','data'=>null],422);

    $stmt = $pdo->prepare("
        SELECT tipo, registrado_em
        FROM registro_ponto
        WHERE funcionario_id = :fid
          AND DATE_FORMAT(registrado_em, '%Y-%m') = :mes
        ORDER BY registrado_em
    ");
    $stmt->execute(['fid'=>$fid, 'mes'=>$mes]);
    $registros = $stmt->fetchAll();

    // Calcula horas trabalhadas por dia
    $dias = [];
    foreach ($registros as $r) {
        $dia = date('Y-m-d', strtotime($r['registrado_em']));
        $dias[$dia][] = $r;
    }
    $resumo_dias = [];
    $total_minutos = 0;
    foreach ($dias as $dia => $regs) {
        $entrada = null; $saida = null; $ei = null; $si = null;
        foreach ($regs as $r) {
            if ($r['tipo'] === 'entrada')            $entrada = strtotime($r['registrado_em']);
            if ($r['tipo'] === 'saida')              $saida   = strtotime($r['registrado_em']);
            if ($r['tipo'] === 'entrada_intervalo')  $ei      = strtotime($r['registrado_em']);
            if ($r['tipo'] === 'saida_intervalo')    $si      = strtotime($r['registrado_em']);
        }
        $minutos = 0;
        if ($entrada && $saida) {
            $bruto    = ($saida - $entrada) / 60;
            $intervalo = ($ei && $si) ? ($ei - $si) / 60 : 0; // si < ei → negativo correto
            $minutos  = $bruto + $intervalo; // intervalo já é negativo (saida_intervalo > entrada_intervalo)
            // Recalcula corretamente
            $minutos = $bruto;
            if ($si && $ei) $minutos -= ($si - $ei) / 60; // tempo do intervalo
        }
        $total_minutos += max(0, $minutos);
        $resumo_dias[] = [
            'dia'             => $dia,
            'entrada'         => $entrada ? date('H:i', $entrada) : null,
            'saida_intervalo' => $si ? date('H:i', $si) : null,
            'entrada_intervalo'=> $ei ? date('H:i', $ei) : null,
            'saida'           => $saida ? date('H:i', $saida) : null,
            'horas_trabalhadas'=> round(max(0,$minutos)/60, 2),
        ];
    }

    // Carga horária mensal esperada (busca do funcionario)
    $sf = $pdo->prepare("SELECT carga_horaria FROM funcionarios WHERE id = :id");
    $sf->execute(['id' => $fid]);
    $func = $sf->fetch();
    $dias_uteis = 22; // estimativa fixa; pode ser calculada
    $esperado_min = ($func['carga_horaria'] ?? 8) * $dias_uteis * 60;
    $saldo_min = $total_minutos - $esperado_min;

    json_response(['status'=>'ok','data'=>[
        'mes'              => $mes,
        'total_horas'      => round($total_minutos/60, 2),
        'esperado_horas'   => round($esperado_min/60, 2),
        'saldo_horas'      => round($saldo_min/60, 2),
        'dias'             => $resumo_dias,
    ]]);
}

// ── GET /ponto/folha — lista folhas ──────────────────────────────────────────
if ($id === 'folha' && $sub === null && $method === 'GET') {
    $fid = filter_input(INPUT_GET, 'funcionario_id', FILTER_VALIDATE_INT) ?: null;
    $mes = sanitize($_GET['mes'] ?? '');
    $where = 'WHERE 1=1'; $params = [];
    if ($fid) { $where .= ' AND fp.funcionario_id = :fid'; $params['fid'] = $fid; }
    if ($mes) { $where .= ' AND fp.mes_referencia = :mes'; $params['mes'] = $mes; }
    $stmt = $pdo->prepare("
        SELECT fp.*, u.nome AS funcionario_nome, l.nome AS loja_nome
        FROM folha_pagamento fp
        JOIN funcionarios f ON f.id = fp.funcionario_id
        JOIN usuarios u ON u.id = f.usuario_id
        JOIN lojas l ON l.id = f.loja_id
        {$where}
        ORDER BY fp.mes_referencia DESC, u.nome
    ");
    $stmt->execute($params);
    json_response(['status'=>'ok','data'=>$stmt->fetchAll()]);
}

// ── POST /ponto/folha — gerar folha ──────────────────────────────────────────
if ($id === 'folha' && $sub === null && $method === 'POST') {
    $obrig = ['funcionario_id','mes_referencia','salario_base'];
    foreach ($obrig as $c) if (empty($body[$c])) json_response(['status'=>'error','message'=>"Campo '$c' obrigatório.",'data'=>null],422);
    $stmt = $pdo->prepare("
        INSERT INTO folha_pagamento
            (funcionario_id, mes_referencia, salario_base, horas_extras, valor_extras, descontos, observacoes, criado_por)
        VALUES (:fid, :mes, :sal, :he, :ve, :des, :obs, :criado)
        ON DUPLICATE KEY UPDATE
            salario_base=VALUES(salario_base), horas_extras=VALUES(horas_extras),
            valor_extras=VALUES(valor_extras), descontos=VALUES(descontos),
            observacoes=VALUES(observacoes)
    ");
    $stmt->execute([
        'fid'    => (int) $body['funcionario_id'],
        'mes'    => sanitize($body['mes_referencia']),
        'sal'    => (float) $body['salario_base'],
        'he'     => (float) ($body['horas_extras'] ?? 0),
        've'     => (float) ($body['valor_extras']  ?? 0),
        'des'    => (float) ($body['descontos']      ?? 0),
        'obs'    => sanitize($body['observacoes']    ?? ''),
        'criado' => $u['id'],
    ]);
    json_response(['status'=>'ok','message'=>'Folha salva.','data'=>['id'=>(int)$pdo->lastInsertId()]], 201);
}

// ── PATCH /ponto/folha/{id} — marcar paga ────────────────────────────────────
if ($id === 'folha' && $sub !== null && $method === 'PATCH') {
    $folha_id = (int) $sub;
    $pago_em  = sanitize($body['pago_em'] ?? date('Y-m-d'));
    $pdo->prepare("UPDATE folha_pagamento SET status='pago', pago_em=:pago WHERE id=:id")
        ->execute(['pago'=>$pago_em,'id'=>$folha_id]);
    registrar_log($u['id'],'pagamento_folha','folha_pagamento',$folha_id,['pago_em'=>$pago_em]);
    json_response(['status'=>'ok','message'=>'Folha marcada como paga.','data'=>null]);
}

// ── GET /ponto?funcionario_id=X&mes=YYYY-MM ───────────────────────────────────
if ($id === null && $method === 'GET') {
    $fid = filter_input(INPUT_GET, 'funcionario_id', FILTER_VALIDATE_INT);
    $mes = sanitize($_GET['mes'] ?? date('Y-m'));
    if (!$fid) json_response(['status'=>'error','message'=>'funcionario_id obrigatório.','data'=>null],422);
    $stmt = $pdo->prepare("
        SELECT rp.*, u.nome AS registrado_por_nome
        FROM registro_ponto rp
        LEFT JOIN usuarios u ON u.id = rp.registrado_por
        WHERE rp.funcionario_id = :fid
          AND DATE_FORMAT(rp.registrado_em, '%Y-%m') = :mes
        ORDER BY rp.registrado_em
    ");
    $stmt->execute(['fid'=>$fid,'mes'=>$mes]);
    json_response(['status'=>'ok','data'=>$stmt->fetchAll()]);
}

// ── POST /ponto — registrar batimento ────────────────────────────────────────
if ($id === null && $method === 'POST') {
    $obrig = ['funcionario_id','tipo'];
    foreach ($obrig as $c) if (empty($body[$c])) json_response(['status'=>'error','message'=>"Campo '$c' obrigatório.",'data'=>null],422);
    $tipos_validos = ['entrada','saida','entrada_intervalo','saida_intervalo'];
    if (!in_array($body['tipo'], $tipos_validos, true)) json_response(['status'=>'error','message'=>'Tipo de ponto inválido.','data'=>null],422);
    $stmt = $pdo->prepare("
        INSERT INTO registro_ponto (funcionario_id, tipo, registrado_em, registrado_por, observacao)
        VALUES (:fid, :tipo, :dt, :por, :obs)
    ");
    $stmt->execute([
        'fid'  => (int) $body['funcionario_id'],
        'tipo' => $body['tipo'],
        'dt'   => sanitize($body['registrado_em'] ?? date('Y-m-d H:i:s')),
        'por'  => $u['id'],
        'obs'  => sanitize($body['observacao'] ?? ''),
    ]);
    json_response(['status'=>'ok','message'=>'Ponto registrado.','data'=>['id'=>(int)$pdo->lastInsertId()]], 201);
}

json_response(['status'=>'error','message'=>'Método não suportado.','data'=>null],405);
