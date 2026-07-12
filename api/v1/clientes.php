<?php
/**
 * Desffrut — API v1: Clientes
 *
 * GET    /api/v1/clientes/pontos    → saldo de pontos do cliente autenticado
 * GET    /api/v1/clientes/compras   → histórico de compras
 * PUT    /api/v1/clientes/perfil    → editar nome, telefone, whatsapp
 * POST   /api/v1/clientes/cadastro  → cadastro de cliente no balcão (PDV) — caixa/gerente/rh_financeiro
 * DELETE /api/v1/clientes/excluir   → anonimização LGPD
 */
require_once __DIR__ . '/../../app/models/Usuario.php';

// ─── POST /clientes/cadastro — cadastro de cliente feito pelo operador no PDV ──
// Campos vêm via multipart/form-data (permite anexar foto no mesmo request).
if ($id === 'cadastro' && $method === 'POST') {
    $u = api_auth_exigir();
    api_auth_role(['caixa', 'gerente', 'rh_financeiro']);

    $pdo = db();

    $nome        = sanitize($_POST['nome'] ?? '');
    $cpf         = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
    $telefone    = preg_replace('/\D/', '', $_POST['telefone'] ?? '');
    $whatsapp    = preg_replace('/\D/', '', $_POST['whatsapp'] ?? '');
    $endereco    = sanitize($_POST['endereco'] ?? '');
    $numero      = sanitize($_POST['numero'] ?? '');
    $complemento = sanitize($_POST['complemento'] ?? '');
    $bairro      = sanitize($_POST['bairro'] ?? '');
    $cidade      = sanitize($_POST['cidade'] ?? '');

    if ($nome === '') {
        json_response(['status' => 'error', 'message' => 'O nome é obrigatório.', 'data' => null], 422);
    }
    if ($cpf !== '' && strlen($cpf) !== 11) {
        json_response(['status' => 'error', 'message' => 'CPF inválido. Confira os números digitados.', 'data' => null], 422);
    }

    if ($cpf !== '') {
        $existeCpf = $pdo->prepare('SELECT id, nome FROM usuarios WHERE cpf = :cpf');
        $existeCpf->execute(['cpf' => $cpf]);
        $dono = $existeCpf->fetch();
        if ($dono) {
            json_response([
                'status'  => 'error',
                'message' => "Este CPF já pertence ao cadastro de \"{$dono['nome']}\". Busque o cliente por CPF em vez de cadastrar novamente.",
                'data'    => ['cliente_id' => (int) $dono['id']],
            ], 422);
        }
    }

    // Não há coleta de e-mail no balcão: gera identificador interno único (a conta não é usada para login).
    $email_interno = 'cliente.' . bin2hex(random_bytes(8)) . '@pdv.desffrut.local';
    $senha_hash    = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT, ['cost' => 12]);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('
            INSERT INTO usuarios
                (nome, email, cpf, telefone, whatsapp,
                 endereco, numero, complemento, bairro, cidade,
                 senha_hash, role, ativo)
            VALUES
                (:nome, :email, :cpf, :telefone, :whatsapp,
                 :endereco, :numero, :complemento, :bairro, :cidade,
                 :senha_hash, \'cliente\', 1)
        ');
        $stmt->execute([
            'nome'        => $nome,
            'email'       => $email_interno,
            'cpf'         => $cpf ?: null,
            'telefone'    => $telefone ?: null,
            'whatsapp'    => $whatsapp ?: null,
            'endereco'    => $endereco ?: null,
            'numero'      => $numero ?: null,
            'complemento' => $complemento ?: null,
            'bairro'      => $bairro ?: null,
            'cidade'      => $cidade ?: null,
            'senha_hash'  => $senha_hash,
        ]);
        $novo_id = (int) $pdo->lastInsertId();

        // Foto opcional (mesma conversão/pasta usada no autoatendimento do cliente)
        $foto_caminho = null;
        if (!empty($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $mime = mime_content_type($_FILES['foto']['tmp_name']);
            if (str_starts_with($mime, 'image/')) {
                $dir = __DIR__ . '/../../uploads/perfis/';
                if (!is_dir($dir)) { mkdir($dir, 0755, true); }
                $destino = $dir . $novo_id . '.webp';
                if (converter_para_webp($_FILES['foto']['tmp_name'], $destino, 60)) {
                    $foto_caminho = 'uploads/perfis/' . $novo_id . '.webp';
                    $pdo->prepare('UPDATE usuarios SET foto_perfil = :f WHERE id = :id')
                        ->execute(['f' => $foto_caminho, 'id' => $novo_id]);
                }
            }
        }

        $pdo->commit();
        registrar_log($u['id'], 'cadastro_cliente_pdv', 'usuarios', $novo_id, ['cpf' => $cpf ?: null]);

        json_response(['status' => 'ok', 'message' => 'Cliente cadastrado com sucesso.', 'data' => [
            'id'                => $novo_id,
            'nome'              => $nome,
            'cpf'               => $cpf ?: null,
            'pontos_fidelidade' => 0,
            'foto_perfil'       => $foto_caminho,
        ]], 201);

    } catch (PDOException $e) {
        $pdo->rollBack();
        if ((string) $e->getCode() === '23000') {
            json_response(['status' => 'error', 'message' => 'Este CPF já está cadastrado em outro cliente.', 'data' => null], 422);
        }
        json_response(['status' => 'error', 'message' => 'Erro ao cadastrar cliente: ' . $e->getMessage(), 'data' => null], 500);
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_response(['status' => 'error', 'message' => 'Erro ao cadastrar cliente: ' . $e->getMessage(), 'data' => null], 500);
    }
}

// ─── GET /clientes/pontos ────────────────────────────────────────────────────
if ($id === 'pontos' && $method === 'GET') {
    $u   = api_auth_exigir();
    api_auth_role(['cliente', 'super_admin']);
    $uid = (int) $u['id'];

    $stmt = db()->prepare('SELECT pontos_fidelidade FROM usuarios WHERE id = ?');
    $stmt->execute([$uid]);
    $row = $stmt->fetch();

    if (!$row) {
        json_response(['status' => 'error', 'message' => 'Usuário não encontrado.', 'data' => null], 404);
    }

    $pontos = (int) $row['pontos_fidelidade'];
    json_response([
        'status'  => 'ok',
        'data'    => [
            'pontos'   => $pontos,
            'em_reais' => pontos_para_reais($pontos),
        ],
        'message' => '',
    ]);
}

// ─── GET /clientes/compras ───────────────────────────────────────────────────
if ($id === 'compras' && $method === 'GET') {
    $u   = api_auth_exigir();
    api_auth_role(['cliente', 'super_admin']);
    $uid = (int) $u['id'];

    try {
        $pdo = db();
        $historico = [];

        // 1. Pedidos online (checkout/delivery)
        $stmt1 = $pdo->prepare("
            SELECT
                p.id,
                NULL           AS cupom_uuid,
                p.total        AS total_final,
                p.pontos_ganhos,
                p.status,
                p.created_at   AS criado_em,
                l.nome         AS loja_nome,
                'delivery'     AS tipo,
                p.forma_pagamento
            FROM pedidos p
            LEFT JOIN lojas l ON l.id = p.loja_id
            WHERE p.cliente_id = :uid
            ORDER BY p.created_at DESC
            LIMIT 50
        ");
        $stmt1->execute(['uid' => $uid]);
        $historico = $stmt1->fetchAll();

        // 2. Vendas PDV (caixa físico) — tabela pode não existir ainda
        try {
            $stmt2 = $pdo->prepare("
                SELECT
                    v.id,
                    v.cupom_uuid,
                    v.total_final,
                    v.pontos_ganhos,
                    v.status,
                    v.created_at   AS criado_em,
                    l.nome         AS loja_nome,
                    'pdv'          AS tipo,
                    NULL           AS forma_pagamento
                FROM vendas v
                LEFT JOIN lojas l ON l.id = v.loja_id
                WHERE v.cliente_id = :uid2
                ORDER BY v.created_at DESC
                LIMIT 50
            ");
            $stmt2->execute(['uid2' => $uid]);
            $historico = array_merge($historico, $stmt2->fetchAll());
        } catch (Throwable $_) { /* tabela vendas pode não existir */ }

        // Ordena por data desc e limita a 100
        usort($historico, fn($a, $b) => strcmp($b['criado_em'], $a['criado_em']));
        $historico = array_slice($historico, 0, 100);

        json_response(['status' => 'ok', 'data' => $historico, 'message' => '']);
    } catch (Throwable $e) {
        json_response(['status' => 'error', 'message' => 'Erro ao buscar compras.', 'data' => null], 500);
    }
}

// ─── PUT /clientes/perfil ────────────────────────────────────────────────────
if ($id === 'perfil' && $method === 'PUT') {
    $u   = api_auth_exigir();
    api_auth_role(['cliente', 'super_admin']);
    $uid = (int) $u['id'];

    $body        = json_decode(file_get_contents('php://input'), true) ?? [];
    $nome        = sanitize($body['nome']        ?? '');
    $whatsapp    = preg_replace('/\D/', '', $body['whatsapp'] ?? '');
    $telefone    = preg_replace('/\D/', '', $body['telefone'] ?? '');
    // Campos de endereço (Fase 6: salvar endereço do checkout no perfil)
    $endereco    = sanitize($body['endereco']    ?? '');
    $numero      = sanitize($body['numero']      ?? '');
    $complemento = sanitize($body['complemento'] ?? '');
    $bairro      = sanitize($body['bairro']      ?? '');

    // nome é obrigatório apenas quando editando perfil completo
    if (array_key_exists('nome', $body) && empty($nome)) {
        json_response(['status' => 'error', 'message' => 'O nome é obrigatório.', 'data' => null], 422);
    }

    try {
        // Monta SET dinâmico: atualiza apenas os campos enviados
        $sets   = [];
        $params = ['id' => $uid];
        if ($nome)        { $sets[] = 'nome = :nome';               $params['nome']        = $nome; }
        if ($whatsapp)    { $sets[] = 'whatsapp = :whatsapp';       $params['whatsapp']    = $whatsapp; }
        if ($telefone)    { $sets[] = 'telefone = :telefone';       $params['telefone']    = $telefone; }
        if ($endereco)    { $sets[] = 'endereco = :endereco';       $params['endereco']    = $endereco; }
        if ($numero !== '')      { $sets[] = 'numero = :numero';    $params['numero']      = $numero; }
        if ($complemento !== '') { $sets[] = 'complemento = :comp'; $params['comp']        = $complemento; }
        if ($bairro)      { $sets[] = 'bairro = :bairro';           $params['bairro']      = $bairro; }

        if (!empty($sets)) {
            $sets[] = 'updated_at = NOW()';
            db()->prepare("UPDATE usuarios SET " . implode(', ', $sets) . " WHERE id = :id")
                ->execute($params);
        }

        // Atualiza sessão
        iniciar_sessao();
        if (isset($_SESSION['usuario'])) {
            foreach (['nome','whatsapp','telefone','endereco','numero','complemento','bairro'] as $f) {
                if (isset($params[$f])) $_SESSION['usuario'][$f] = $params[$f];
            }
        }

        registrar_log($uid, 'edicao_perfil', 'usuarios', $uid);
        json_response(['status' => 'ok', 'data' => null, 'message' => 'Dados atualizados com sucesso.']);
    } catch (Throwable $e) {
        json_response(['status' => 'error', 'message' => 'Erro ao atualizar perfil.', 'data' => null], 500);
    }
}

// ─── POST /clientes/foto — upload de foto de perfil ─────────────────────────
if ($id === 'foto' && $method === 'POST') {
    $u   = api_auth_exigir();
    api_auth_role(['cliente', 'super_admin']);
    $uid = (int) $u['id'];

    if (empty($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        $err = $_FILES['foto']['error'] ?? -1;
        json_response(['status' => 'error', 'message' => 'Nenhum arquivo enviado ou erro no upload (código ' . $err . ').', 'data' => null], 422);
    }

    $tmp  = $_FILES['foto']['tmp_name'];
    $mime = mime_content_type($tmp);

    if (!str_starts_with($mime, 'image/')) {
        json_response(['status' => 'error', 'message' => 'Arquivo deve ser uma imagem.', 'data' => null], 422);
    }

    // Cria diretório de perfis se não existir
    $dir_perfis = __DIR__ . '/../../uploads/perfis/';
    if (!is_dir($dir_perfis)) {
        mkdir($dir_perfis, 0755, true);
    }

    $destino = $dir_perfis . $uid . '.webp';

    if (!converter_para_webp($tmp, $destino, 60)) {
        json_response(['status' => 'error', 'message' => 'Não foi possível converter a imagem. Verifique se a extensão GD está ativa.', 'data' => null], 500);
    }

    $caminho_relativo = 'uploads/perfis/' . $uid . '.webp';

    try {
        db()->prepare("UPDATE usuarios SET foto_perfil = :foto, updated_at = NOW() WHERE id = :id")
           ->execute(['foto' => $caminho_relativo, 'id' => $uid]);

        iniciar_sessao();
        if (isset($_SESSION['usuario'])) {
            $_SESSION['usuario']['foto_perfil'] = $caminho_relativo;
        }

        registrar_log($uid, 'foto_perfil_atualizada', 'usuarios', $uid);
        json_response([
            'status'  => 'ok',
            'data'    => ['foto_url' => $caminho_relativo . '?v=' . time()],
            'message' => 'Foto atualizada com sucesso.',
        ]);
    } catch (Throwable $e) {
        json_response(['status' => 'error', 'message' => 'Erro ao salvar foto no banco.', 'data' => null], 500);
    }
}

// ─── DELETE /clientes/excluir ────────────────────────────────────────────────
if ($id === 'excluir' && $method === 'DELETE') {
    $u   = api_auth_exigir();
    api_auth_role(['cliente', 'super_admin']);
    $uid = (int) $u['id'];

    $model = new Usuario();
    if ($model->anonimizar($uid)) {
        $model->revogarToken($uid);
        iniciar_sessao();
        $_SESSION = [];
        session_destroy();
        json_response(['status' => 'ok', 'data' => null, 'message' => 'Conta anonimizada conforme a LGPD.']);
    }

    json_response(['status' => 'error', 'message' => 'Não foi possível processar a solicitação.', 'data' => null], 500);
}

json_response(['status' => 'error', 'message' => 'Endpoint não encontrado.', 'data' => null], 404);
