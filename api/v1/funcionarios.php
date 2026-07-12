<?php
// Guard: acesso direto bloqueado.
if (!function_exists('api_auth_exigir')) {
    http_response_code(403); header('Content-Type: application/json');
    echo json_encode(['status'=>'error','message'=>'Acesso direto não permitido.']); exit;
}
require_once __DIR__ . '/../../app/middleware/modo_restrito.php'; // Categoria 22
/**
 * Desffrut — API v1: Funcionários (RH)
 *
 * GET    /api/v1/funcionarios                  → lista funcionários
 * POST   /api/v1/funcionarios                   → admitir funcionário (cria ou vincula usuário)
 * GET    /api/v1/funcionarios/usuarios-busca     → busca usuários existentes p/ vincular (?q=)
 * GET    /api/v1/funcionarios/{id}               → ficha completa
 * PUT    /api/v1/funcionarios/{id}               → editar dados (funcionário + usuário vinculado)
 * DELETE /api/v1/funcionarios/{id}               → desligar (demitido_em + ativo=0)
 *
 * Cargo → role do sistema (cargos sem acesso ao painel viram role 'colaborador'):
 *   Gerente          → gerente
 *   RH               → rh_financeiro
 *   Caixa/Atendente  → caixa
 *   Entregador       → entregador
 *   Motorista        → colaborador (sem acesso ao painel)
 *   Auxiliar (CEASA) → colaborador (sem acesso ao painel)
 */

const CARGO_ROLE_MAP = [
    'Gerente'          => 'gerente',
    'RH'               => 'rh_financeiro',
    'Caixa/Atendente'  => 'caixa',
    'Entregador'       => 'entregador',
    'Motorista'        => 'colaborador',
    'Auxiliar (CEASA)' => 'colaborador',
];

/** Gera senha temporária legível (12 caracteres). */
function _rh_gerar_senha_temporaria(): string {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#';
    $senha = '';
    for ($i = 0; $i < 12; $i++) {
        $senha .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $senha;
}

/** Traduz erro de chave duplicada (1062) do MySQL em mensagem amigável em PT-BR. */
function _rh_mensagem_duplicidade(PDOException $e): string {
    if (!preg_match("/for key '([^']+)'/", $e->getMessage(), $m)) {
        return 'Erro ao salvar: registro duplicado. ' . $e->getMessage();
    }
    $chave = $m[1];
    $campo = match (true) {
        str_contains($chave, 'cpf')        => 'Este CPF',
        str_contains($chave, 'email')      => 'Este e-mail',
        str_contains($chave, 'usuario_id') => 'Este usuário',
        default                            => "O campo '$chave'",
    };
    $motivo = str_contains($chave, 'usuario_id')
        ? 'já possui um registro de funcionário vinculado.'
        : 'já está cadastrado em outro usuário.';
    return "$campo $motivo";
}

$u   = api_auth_exigir();
api_auth_role(['super_admin', 'rh_financeiro']);
$pdo  = db();
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── GET /funcionarios/usuarios-busca — autocomplete p/ vincular usuário existente ──
if ($id === 'usuarios-busca' && $method === 'GET') {
    $q = trim(sanitize($_GET['q'] ?? ''));
    if (strlen($q) < 2) {
        json_response(['status' => 'ok', 'data' => []]);
    }
    $stmt = $pdo->prepare("
        SELECT id, nome, email, role, loja_id
        FROM usuarios
        WHERE ativo = 1 AND (nome LIKE :q1 OR email LIKE :q2)
        ORDER BY nome
        LIMIT 15
    ");
    $stmt->execute(['q1' => '%' . $q . '%', 'q2' => '%' . $q . '%']);
    json_response(['status' => 'ok', 'data' => $stmt->fetchAll()]);
}

// ── GET /funcionarios ─────────────────────────────────────────────────────────
if ($id === null && $method === 'GET') {
    $ativos  = ($_GET['ativos'] ?? '1') !== '0';
    $loja_id = filter_input(INPUT_GET, 'loja_id', FILTER_VALIDATE_INT) ?: null;
    $where   = 'WHERE 1=1';
    $params  = [];
    if ($ativos)  { $where .= ' AND f.ativo = 1'; }
    if ($loja_id) { $where .= ' AND f.loja_id = :loja_id'; $params['loja_id'] = $loja_id; }

    $stmt = $pdo->prepare("
        SELECT f.id, f.usuario_id, f.cargo, f.tipo_contrato, f.carga_horaria, f.salario_base,
               f.admitido_em, f.demitido_em, f.ativo, f.observacoes,
               u.nome, u.email, u.role, u.cpf, u.telefone, u.whatsapp, u.foto_perfil,
               l.nome AS loja_nome
        FROM funcionarios f
        JOIN usuarios u ON u.id = f.usuario_id
        JOIN lojas    l ON l.id = f.loja_id
        {$where}
        ORDER BY u.nome
    ");
    $stmt->execute($params);
    json_response(['status' => 'ok', 'data' => $stmt->fetchAll()]);
}

// ── POST /funcionarios — admitir (cria novo usuário OU vincula existente) ────
if ($id === null && $method === 'POST') {
    $cargo = sanitize($body['cargo'] ?? '');
    if (!array_key_exists($cargo, CARGO_ROLE_MAP)) {
        json_response(['status' => 'error', 'message' => 'Cargo inválido. Selecione uma opção da lista.', 'data' => null], 422);
    }
    foreach (['loja_id', 'salario_base', 'admitido_em'] as $c) {
        if (empty($body[$c])) {
            json_response(['status' => 'error', 'message' => "Campo '$c' obrigatório.", 'data' => null], 422);
        }
    }

    $loja_id = (int) $body['loja_id'];
    $role    = CARGO_ROLE_MAP[$cargo];
    $senha_gerada = null;

    try {
        $pdo->beginTransaction();

        $usuario_id_vinculado = !empty($body['usuario_id']) ? (int) $body['usuario_id'] : null;

        if ($usuario_id_vinculado) {
            // ── Vincula a um usuário já existente ──
            $chk = $pdo->prepare('SELECT id FROM usuarios WHERE id = :id');
            $chk->execute(['id' => $usuario_id_vinculado]);
            if (!$chk->fetch()) {
                throw new RuntimeException('Usuário selecionado não encontrado.');
            }
            $uid = $usuario_id_vinculado;

            $sets = ['role = :role', 'loja_id = :loja_id_u'];
            $params = ['role' => $role, 'loja_id_u' => $loja_id, 'id' => $uid];
            foreach (['telefone','whatsapp','cpf','data_nascimento','endereco','numero','complemento','bairro','cidade','foto_perfil'] as $campo) {
                if (!empty($body[$campo])) {
                    $sets[] = "$campo = :$campo";
                    $params[$campo] = sanitize((string) $body[$campo]);
                }
            }
            $pdo->prepare('UPDATE usuarios SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);

        } else {
            // ── Cria um novo usuário (login) para o funcionário ──
            $nome  = sanitize($body['nome'] ?? '');
            $email = strtolower(trim($body['email'] ?? ''));
            if (!$nome || !$email) {
                throw new RuntimeException('Nome e e-mail são obrigatórios para um novo cadastro.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('E-mail inválido.');
            }
            $existe = $pdo->prepare('SELECT id FROM usuarios WHERE email = :e');
            $existe->execute(['e' => $email]);
            if ($existe->fetch()) {
                throw new RuntimeException('Já existe um usuário com este e-mail. Use a busca para vincular o cadastro existente.');
            }

            $cpf_informado = sanitize($body['cpf'] ?? '');
            if ($cpf_informado !== '') {
                $existeCpf = $pdo->prepare('SELECT id, nome, email FROM usuarios WHERE cpf = :cpf');
                $existeCpf->execute(['cpf' => $cpf_informado]);
                $donoCpf = $existeCpf->fetch();
                if ($donoCpf) {
                    throw new RuntimeException(
                        "Este CPF já pertence ao cadastro de \"{$donoCpf['nome']}\" ({$donoCpf['email']}). " .
                        "Se for a mesma pessoa, use \"Vincular usuário existente\" e busque por esse nome/e-mail; " .
                        "se for outra pessoa, confira se o CPF foi digitado corretamente."
                    );
                }
            }

            $senha_gerada = _rh_gerar_senha_temporaria();
            $hash = password_hash($senha_gerada, PASSWORD_BCRYPT, ['cost' => 12]);

            $stmt = $pdo->prepare('
                INSERT INTO usuarios
                    (nome, email, cpf, telefone, whatsapp, data_nascimento,
                     endereco, numero, complemento, bairro, cidade,
                     foto_perfil, senha_hash, role, loja_id, ativo, trocar_senha_prox_login, lgpd_aceito_em)
                VALUES
                    (:nome, :email, :cpf, :telefone, :whatsapp, :data_nascimento,
                     :endereco, :numero, :complemento, :bairro, :cidade,
                     :foto_perfil, :senha_hash, :role, :loja_id, 1, 1, NOW())
            ');
            $stmt->execute([
                'nome'            => $nome,
                'email'           => $email,
                'cpf'             => sanitize($body['cpf'] ?? '') ?: null,
                'telefone'        => sanitize($body['telefone'] ?? '') ?: null,
                'whatsapp'        => sanitize($body['whatsapp'] ?? '') ?: null,
                'data_nascimento' => !empty($body['data_nascimento']) ? $body['data_nascimento'] : null,
                'endereco'        => sanitize($body['endereco'] ?? '') ?: null,
                'numero'          => sanitize($body['numero'] ?? '') ?: null,
                'complemento'     => sanitize($body['complemento'] ?? '') ?: null,
                'bairro'          => sanitize($body['bairro'] ?? '') ?: null,
                'cidade'          => sanitize($body['cidade'] ?? '') ?: null,
                'foto_perfil'     => sanitize($body['foto_perfil'] ?? '') ?: null,
                'senha_hash'      => $hash,
                'role'            => $role,
                'loja_id'         => $loja_id,
            ]);
            $uid = (int) $pdo->lastInsertId();
        }

        $stmt = $pdo->prepare('
            INSERT INTO funcionarios
                (usuario_id, loja_id, cargo, tipo_contrato, carga_horaria, salario_base, admitido_em, observacoes)
            VALUES
                (:uid, :lid, :cargo, :tipo, :ch, :sal, :adm, :obs)
        ');
        $stmt->execute([
            'uid'   => $uid,
            'lid'   => $loja_id,
            'cargo' => $cargo,
            'tipo'  => in_array($body['tipo_contrato'] ?? 'clt', ['clt','pj','autonomo','estagio'], true) ? $body['tipo_contrato'] : 'clt',
            'ch'    => (int) ($body['carga_horaria'] ?? 8),
            'sal'   => (float) $body['salario_base'],
            'adm'   => $body['admitido_em'],
            'obs'   => sanitize($body['observacoes'] ?? ''),
        ]);
        $fid = (int) $pdo->lastInsertId();

        $pdo->commit();

        registrar_log($u['id'], 'admissao_funcionario', 'funcionarios', $fid, ['usuario_id' => $uid, 'cargo' => $cargo]);

        json_response(['status' => 'ok', 'message' => 'Funcionário admitido.', 'data' => [
            'id'               => $fid,
            'usuario_id'       => $uid,
            'senha_temporaria' => $senha_gerada, // null quando vinculou usuário já existente
        ]], 201);

    } catch (PDOException $e) {
        $pdo->rollBack();
        if ((string) $e->getCode() === '23000') {
            json_response(['status' => 'error', 'message' => _rh_mensagem_duplicidade($e), 'data' => null], 422);
        }
        json_response(['status' => 'error', 'message' => 'Erro ao admitir funcionário: ' . $e->getMessage(), 'data' => null], 500);
    } catch (RuntimeException $e) {
        $pdo->rollBack();
        json_response(['status' => 'error', 'message' => $e->getMessage(), 'data' => null], 422);
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_response(['status' => 'error', 'message' => 'Erro ao admitir funcionário: ' . $e->getMessage(), 'data' => null], 500);
    }
}

// ── GET /funcionarios/{id} — ficha completa ───────────────────────────────────
if ($id !== null && $id !== 'usuarios-busca' && $sub === null && $method === 'GET') {
    $stmt = $pdo->prepare("
        SELECT f.*, u.nome, u.email, u.cpf, u.telefone, u.whatsapp, u.data_nascimento,
               u.endereco, u.numero, u.complemento, u.bairro, u.cidade, u.foto_perfil, u.role,
               l.nome AS loja_nome
        FROM funcionarios f
        JOIN usuarios u ON u.id = f.usuario_id
        JOIN lojas    l ON l.id = f.loja_id
        WHERE f.id = :id
    ");
    $stmt->execute(['id' => (int) $id]);
    $f = $stmt->fetch();
    if (!$f) json_response(['status'=>'error','message'=>'Funcionário não encontrado.','data'=>null],404);
    json_response(['status'=>'ok','data'=>$f]);
}

// ── PUT /funcionarios/{id} — editar (funcionário + usuário vinculado) ────────
if ($id !== null && $sub === null && $method === 'PUT') {
    $fid = (int) $id;
    $existente = $pdo->prepare('SELECT usuario_id FROM funcionarios WHERE id = :id');
    $existente->execute(['id' => $fid]);
    $reg = $existente->fetch();
    if (!$reg) json_response(['status'=>'error','message'=>'Funcionário não encontrado.','data'=>null],404);
    $uid = (int) $reg['usuario_id'];

    try {
        $pdo->beginTransaction();

        // Campos do funcionário
        $campos_func = ['cargo','tipo_contrato','carga_horaria','salario_base','loja_id','observacoes'];
        $sets = []; $params = ['id' => $fid];
        foreach ($campos_func as $c) {
            if (array_key_exists($c, $body)) {
                if ($c === 'cargo' && !array_key_exists($body['cargo'], CARGO_ROLE_MAP)) {
                    throw new RuntimeException('Cargo inválido. Selecione uma opção da lista.');
                }
                $sets[] = "$c = :$c";
                $params[$c] = is_string($body[$c]) ? sanitize($body[$c]) : $body[$c];
            }
        }
        if ($sets) {
            $pdo->prepare('UPDATE funcionarios SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
        }

        // Campos do usuário vinculado (contato/pessoais/foto)
        $campos_usr = ['nome','telefone','whatsapp','cpf','data_nascimento','endereco','numero','complemento','bairro','cidade','foto_perfil'];
        $setsU = []; $paramsU = ['id' => $uid];
        foreach ($campos_usr as $c) {
            if (array_key_exists($c, $body)) {
                $setsU[] = "$c = :$c";
                $paramsU[$c] = is_string($body[$c]) ? sanitize($body[$c]) : $body[$c];
            }
        }
        // Se o cargo mudou, atualiza o role do usuário também
        if (isset($body['cargo']) && array_key_exists($body['cargo'], CARGO_ROLE_MAP)) {
            $setsU[] = 'role = :role';
            $paramsU['role'] = CARGO_ROLE_MAP[$body['cargo']];
        }
        if ($setsU) {
            $pdo->prepare('UPDATE usuarios SET ' . implode(', ', $setsU) . ' WHERE id = :id')->execute($paramsU);
        }

        if (!$sets && !$setsU) {
            $pdo->rollBack();
            json_response(['status'=>'error','message'=>'Nada para atualizar.','data'=>null],422);
        }

        $pdo->commit();
        registrar_log($u['id'], 'edicao_funcionario', 'funcionarios', $fid);
        json_response(['status'=>'ok','message'=>'Funcionário atualizado.','data'=>null]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        if ((string) $e->getCode() === '23000') {
            json_response(['status'=>'error','message'=>_rh_mensagem_duplicidade($e),'data'=>null], 422);
        }
        json_response(['status'=>'error','message'=>'Erro ao atualizar: '.$e->getMessage(),'data'=>null], 500);
    } catch (RuntimeException $e) {
        $pdo->rollBack();
        json_response(['status'=>'error','message'=>$e->getMessage(),'data'=>null], 422);
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_response(['status'=>'error','message'=>'Erro ao atualizar: '.$e->getMessage(),'data'=>null], 500);
    }
}

// ── DELETE /funcionarios/{id} — desligar ─────────────────────────────────────
if ($id !== null && $method === 'DELETE') {
    $data_demissao = sanitize($body['demitido_em'] ?? date('Y-m-d'));
    $pdo->prepare("UPDATE funcionarios SET ativo=0, demitido_em=:dem WHERE id=:id")
        ->execute(['dem' => $data_demissao, 'id' => (int) $id]);
    registrar_log($u['id'], 'demissao_funcionario', 'funcionarios', (int)$id, ['demitido_em' => $data_demissao]);
    json_response(['status'=>'ok','message'=>'Funcionário desligado.','data'=>null]);
}

json_response(['status'=>'error','message'=>'Método não suportado.','data'=>null],405);
