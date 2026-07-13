<?php
/**
 * Desffrut — Utilitário de desenvolvimento: criar/resetar senhas
 * Apenas para uso local (XAMPP). NÃO subir para produção.
 *
 * Acesso: http://localhost/desffrut.com/database/reset_senhas.php
 */

require_once __DIR__ . '/../app/config/config.php';

// Trava de segurança: nunca executar fora do ambiente local (XAMPP).
if (AMBIENTE !== 'local') {
    http_response_code(403);
    die('Acesso negado. Este utilitário só funciona no ambiente local (XAMPP).');
}

require_once __DIR__ . '/../app/config/database.php';

$msg   = '';
$tipo  = '';

// Reset em massa de todos os usuários de seed (legado — database/01-seed.sql)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resetar_todos'])) {
    $seed_users = [
        ['admin@desffrut.com.br',        'admin123'],
        ['rh@desffrut.com.br',           'rh123'],
        ['gerente1@desffrut.com.br',     'gerente123'],
        ['gerente2@desffrut.com.br',     'gerente123'],
        ['gerente3@desffrut.com.br',     'gerente123'],
        ['caixa1@desffrut.com.br',       'caixa123'],
        ['caixa2@desffrut.com.br',       'caixa123'],
        ['caixa3@desffrut.com.br',       'caixa123'],
        ['entregador1@desffrut.com.br',  'entregador123'],
        ['entregador2@desffrut.com.br',  'entregador123'],
        ['entregador3@desffrut.com.br',  'entregador123'],
        ['cliente@teste.com',            'cliente123'],
    ];
    try {
        $pdo  = db();
        $stmt = $pdo->prepare("UPDATE usuarios SET senha_hash = :h WHERE email = :e");
        $ok = 0;
        foreach ($seed_users as [$email, $senha]) {
            $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt->execute([':h' => $hash, ':e' => $email]);
            $ok += $stmt->rowCount();
        }
        $msg  = "✅ {$ok} usuário(s) de seed tiveram as senhas resetadas com sucesso.";
        $tipo = 'ok';
    } catch (Throwable $ex) {
        $msg  = 'Erro: ' . htmlspecialchars($ex->getMessage());
        $tipo = 'erro';
    }

// Reset em massa dos 14 usuários de teste nomeados (database/seed_teste.sql)
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resetar_teste'])) {
    $senha_padrao = '@senha01';
    $emails_teste = [
        'roberio@desffrut.com.br',
        'paulo@desffrut.com.br',
        'paula@desffrut.com.br',
        'adriana@desffrut.com.br',
        'maria@desffrut.com.br',
        'josi@desffrut.com.br',
        'livia@desffrut.com.br',
        'francisco@desffrut.com.br',
        'henrique@desffrut.com.br',
        'antonio@desffrut.com.br',
        'chagas@desffrut.com.br',
        'marcia@teste.com',
        'jessica@teste.com',
        'costa@teste.com',
    ];
    try {
        $pdo  = db();
        $hash = password_hash($senha_padrao, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("UPDATE usuarios SET senha_hash = :h WHERE email = :e");
        $ok = 0;
        foreach ($emails_teste as $email) {
            $stmt->execute([':h' => $hash, ':e' => $email]);
            $ok += $stmt->rowCount();
        }
        $msg  = "✅ {$ok} usuário(s) de teste (seed_teste.sql) tiveram a senha definida para \"@senha01\".";
        $tipo = 'ok';
    } catch (Throwable $ex) {
        $msg  = 'Erro: ' . htmlspecialchars($ex->getMessage());
        $tipo = 'erro';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    $nome  = trim($_POST['nome']  ?? '');
    $role  = trim($_POST['role']  ?? 'cliente');

    if (!$email || !$senha) {
        $msg = 'E-mail e senha são obrigatórios.';
        $tipo = 'erro';
    } else {
        try {
            $pdo  = db();
            $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);

            // Verifica se o usuário já existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :e LIMIT 1");
            $stmt->execute([':e' => $email]);
            $existente = $stmt->fetchColumn();

            if ($existente) {
                // Atualiza senha (e role, se informado)
                $pdo->prepare("UPDATE usuarios SET senha_hash = :h, role = :r WHERE email = :e")
                    ->execute([':h' => $hash, ':r' => $role, ':e' => $email]);
                $msg  = "✅ Senha atualizada para <strong>{$email}</strong> com role <strong>{$role}</strong>.";
                $tipo = 'ok';
            } else {
                // Cria novo usuário
                if (!$nome) {
                    $msg  = 'Para criar um novo usuário, informe também o Nome.';
                    $tipo = 'erro';
                } else {
                    $pdo->prepare("INSERT INTO usuarios (nome, email, senha_hash, role, ativo)
                                   VALUES (:n, :e, :h, :r, 1)")
                        ->execute([':n' => $nome, ':e' => $email, ':h' => $hash, ':r' => $role]);
                    $msg  = "✅ Usuário <strong>{$email}</strong> criado com role <strong>{$role}</strong>.";
                    $tipo = 'ok';
                }
            }
        } catch (Throwable $ex) {
            $msg  = 'Erro: ' . htmlspecialchars($ex->getMessage());
            $tipo = 'erro';
        }
    }
}

// Lista usuários existentes
$usuarios = [];
try {
    $pdo      = db();
    $usuarios = $pdo->query("SELECT id, nome, email, role, ativo FROM usuarios ORDER BY role, nome")->fetchAll();
} catch (Throwable $e) {}

$roles = ['dev_admin','super_admin','gerente','caixa','entregador','rh_financeiro','cliente'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Reset de Senhas — Dev</title>
    <style>
        body { font-family: monospace; background: #1a1a2e; color: #e0e0e0; padding: 24px; }
        h1   { color: #7c3aed; margin-bottom: 4px; }
        .aviso { background: #3b1010; border: 1px solid #7f0000; color: #ffcdd2;
                 padding: 8px 14px; border-radius: 6px; margin-bottom: 20px; font-size: .85rem; }
        .card { background: #13131a; border: 1px solid #2a2a3a; border-radius: 10px;
                padding: 20px; margin-bottom: 20px; max-width: 520px; }
        label { display: block; font-size: .78rem; color: #888; margin-bottom: 3px; margin-top: 10px; }
        input, select {
            width: 100%; padding: 8px 10px; border: 1px solid #2a2a3a;
            background: #0d0d14; color: #e0e0e0; border-radius: 5px;
            font-family: monospace; font-size: .87rem; box-sizing: border-box;
        }
        button { margin-top: 14px; padding: 9px 22px; background: #7c3aed; color: #fff;
                 border: none; border-radius: 6px; cursor: pointer; font-family: monospace;
                 font-weight: 700; font-size: .87rem; }
        button:hover { background: #6d28d9; }
        .msg-ok  { background: #0d2b0d; border: 1px solid #2e7d32; color: #a5d6a7;
                   padding: 8px 14px; border-radius: 6px; margin-top: 12px; }
        .msg-err { background: #2b0d0d; border: 1px solid #c62828; color: #ef9a9a;
                   padding: 8px 14px; border-radius: 6px; margin-top: 12px; }
        table  { width: 100%; border-collapse: collapse; font-size: .77rem; max-width: 900px; }
        th     { background: #0d0d14; padding: 7px 10px; text-align: left; color: #666;
                 border-bottom: 1px solid #2a2a3a; }
        td     { padding: 6px 10px; border-bottom: 1px solid #111; color: #bbb; }
        tr:hover td { background: #0d0d14; }
        .role-dev   { color: #a78bfa; }
        .role-super { color: #fbbf24; }
        .role-ger   { color: #34d399; }
        .role-other { color: #60a5fa; }
    </style>
</head>
<body>

<h1>🔧 Reset de Senhas — Dev</h1>
<div class="aviso">⚠️ Use apenas em ambiente local. Não expor em produção.</div>

<!-- Reset em massa dos usuários de seed -->
<div class="card" style="border-color:#2e7d32;">
    <strong>⚡ Resetar todos os usuários de seed</strong><br>
    <small style="color:#666;">Define as senhas padrão de todos os 12 usuários de teste de uma vez.</small>
    <form method="POST" style="margin-top:12px;">
        <input type="hidden" name="resetar_todos" value="1">
        <table style="font-size:.75rem;color:#888;margin:8px 0;">
            <tr><td>admin@desffrut.com.br</td><td style="color:#a5d6a7;">→ admin123</td></tr>
            <tr><td>gerente1-3@desffrut.com.br</td><td style="color:#a5d6a7;">→ gerente123</td></tr>
            <tr><td>caixa1-3@desffrut.com.br</td><td style="color:#a5d6a7;">→ caixa123</td></tr>
            <tr><td>entregador1-3@desffrut.com.br</td><td style="color:#a5d6a7;">→ entregador123</td></tr>
            <tr><td>rh@desffrut.com.br</td><td style="color:#a5d6a7;">→ rh123</td></tr>
            <tr><td>cliente@teste.com</td><td style="color:#a5d6a7;">→ cliente123</td></tr>
        </table>
        <button type="submit" style="background:#2e7d32;">🌿 Resetar Todos os Seeds</button>
    </form>
    <?php if ($msg && isset($_POST['resetar_todos'])): ?>
    <div class="<?= $tipo === 'ok' ? 'msg-ok' : 'msg-err' ?>"><?= $msg ?></div>
    <?php endif; ?>
</div>

<!-- Reset em massa dos 14 usuários de teste nomeados (database/seed_teste.sql) -->
<div class="card" style="border-color:#7c3aed;">
    <strong>🔑 Resetar Senhas de Teste (@senha01)</strong><br>
    <small style="color:#666;">Define a senha "@senha01" para os 14 usuários nomeados do seed de demonstração (database/seed_teste.sql). Rode isso ANTES de exportar o banco para o servidor de teste — este utilitário só funciona em ambiente local.</small>
    <form method="POST" style="margin-top:12px;">
        <input type="hidden" name="resetar_teste" value="1">
        <table style="font-size:.75rem;color:#888;margin:8px 0;">
            <tr><td>roberio@desffrut.com.br</td><td style="color:#c4b5fd;">Robério — dev_admin</td></tr>
            <tr><td>paulo@desffrut.com.br</td><td style="color:#c4b5fd;">Paulo — super_admin</td></tr>
            <tr><td>paula@desffrut.com.br</td><td style="color:#c4b5fd;">Paula — rh_financeiro</td></tr>
            <tr><td>adriana@desffrut.com.br</td><td style="color:#c4b5fd;">Adriana — gerente</td></tr>
            <tr><td>maria@desffrut.com.br</td><td style="color:#c4b5fd;">Maria — caixa (Loja 1)</td></tr>
            <tr><td>josi@desffrut.com.br</td><td style="color:#c4b5fd;">Josi — caixa (Loja 2)</td></tr>
            <tr><td>livia@desffrut.com.br</td><td style="color:#c4b5fd;">Lívia — caixa (Loja 3)</td></tr>
            <tr><td>francisco@desffrut.com.br</td><td style="color:#c4b5fd;">Francisco — entregador</td></tr>
            <tr><td>henrique@desffrut.com.br</td><td style="color:#c4b5fd;">Henrique — colaborador (motorista)</td></tr>
            <tr><td>antonio@desffrut.com.br</td><td style="color:#c4b5fd;">Antônio — colaborador (auxiliar Loja 1)</td></tr>
            <tr><td>chagas@desffrut.com.br</td><td style="color:#c4b5fd;">Chagas — colaborador (auxiliar Loja 2)</td></tr>
            <tr><td>marcia@teste.com</td><td style="color:#c4b5fd;">Márcia — cliente</td></tr>
            <tr><td>jessica@teste.com</td><td style="color:#c4b5fd;">Jessica — cliente</td></tr>
            <tr><td>costa@teste.com</td><td style="color:#c4b5fd;">Costa — cliente</td></tr>
        </table>
        <button type="submit" style="background:#7c3aed;">🔑 Resetar Senhas de Teste (@senha01)</button>
    </form>
    <?php if ($msg && isset($_POST['resetar_teste'])): ?>
    <div class="<?= $tipo === 'ok' ? 'msg-ok' : 'msg-err' ?>"><?= $msg ?></div>
    <?php endif; ?>
</div>

<div class="card">
    <strong>Criar usuário ou redefinir senha</strong><br>
    <small style="color:#666;">Se o e-mail já existir, apenas atualiza a senha e o role.</small>

    <form method="POST">
        <label>Nome (obrigatório apenas para criar novo usuário)</label>
        <input type="text" name="nome" placeholder="Ex.: Dev Admin" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>">

        <label>E-mail *</label>
        <input type="email" name="email" required placeholder="dev@desffrut.com.br" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

        <label>Senha *</label>
        <input type="text" name="senha" required placeholder="devadmin123">

        <label>Role</label>
        <select name="role">
            <?php foreach ($roles as $r): ?>
            <option value="<?= $r ?>" <?= ($_POST['role'] ?? '') === $r ? 'selected' : '' ?>><?= $r ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit">🔑 Salvar</button>
    </form>

    <?php if ($msg): ?>
    <div class="<?= $tipo === 'ok' ? 'msg-ok' : 'msg-err' ?>"><?= $msg ?></div>
    <?php endif; ?>
</div>

<?php if ($usuarios): ?>
<strong style="font-size:.85rem;color:#888;">Usuários cadastrados (<?= count($usuarios) ?>)</strong>
<table style="margin-top:8px;">
    <thead><tr><th>#</th><th>Nome</th><th>E-mail</th><th>Role</th><th>Ativo</th></tr></thead>
    <tbody>
    <?php foreach ($usuarios as $usr): ?>
    <?php $cls = match($usr['role']) {
        'dev_admin'   => 'role-dev',
        'super_admin' => 'role-super',
        'gerente'     => 'role-ger',
        default       => 'role-other'
    }; ?>
    <tr>
        <td style="color:#555;"><?= $usr['id'] ?></td>
        <td><?= htmlspecialchars($usr['nome']) ?></td>
        <td><?= htmlspecialchars($usr['email']) ?></td>
        <td class="<?= $cls ?>"><?= $usr['role'] ?></td>
        <td><?= $usr['ativo'] ? '✅' : '❌' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

</body>
</html>
