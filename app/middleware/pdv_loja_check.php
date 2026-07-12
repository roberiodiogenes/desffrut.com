<?php
/**
 * Desffrut — Middleware: garante que operadores de caixa têm loja_id definido.
 * Inclua APÓS auth_check.php nas views do PDV.
 *
 * super_admin e gerente: podem selecionar loja via ?loja_id=X (parâmetro GET/POST)
 *   ou têm acesso a todas as lojas.
 * caixa: obrigatório ter loja_id na sessão. Sem loja → bloqueia com mensagem.
 */
$u = usuario_logado();
if (!$u) redirecionar(BASE_PATH . '/login');

$role        = $u['role'] ?? '';
$loja_id_usr = (int) ($u['loja_id'] ?? 0);

// super_admin e gerente podem sobrepor via GET param (ex: ?loja_id=2)
if (in_array($role, ['super_admin', 'dev_admin', 'gerente'], true)) {
    $loja_id_pdv = isset($_GET['loja_id']) ? (int)$_GET['loja_id'] : $loja_id_usr;
    // Busca nome da loja para exibir no PDV
    if ($loja_id_pdv) {
        try {
            $stmt = db()->prepare("SELECT id, nome FROM lojas WHERE id=:id AND ativo=1 LIMIT 1");
            $stmt->execute([':id' => $loja_id_pdv]);
            $loja_pdv = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $_) { $loja_pdv = null; }
    } else {
        $loja_pdv = null;
    }
} else {
    // Caixa e entregador: obrigatório ter loja_id na sessão
    if (!$loja_id_usr) {
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head><meta charset="UTF-8"><title>PDV — Sem Loja</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
        </head>
        <body class="bg-light">
        <div class="d-flex align-items-center justify-content-center min-vh-100">
            <div class="text-center p-5 bg-white rounded-4 shadow" style="max-width:420px;">
                <div style="font-size:3rem;">🏪</div>
                <h4 class="mt-3 fw-bold">Loja não configurada</h4>
                <p class="text-muted">Sua conta ainda não foi vinculada a uma filial. Peça ao <strong>dono</strong> ou <strong>gerente</strong> para vincular seu usuário à loja correta.</p>
                <a href="<?= BASE_PATH ?>/dashboard" class="btn btn-outline-secondary mt-2">← Voltar ao painel</a>
            </div>
        </div>
        </body></html>
        <?php
        exit;
    }
    $loja_id_pdv = $loja_id_usr;
    try {
        $stmt = db()->prepare("SELECT id, nome FROM lojas WHERE id=:id AND ativo=1 LIMIT 1");
        $stmt->execute([':id' => $loja_id_pdv]);
        $loja_pdv = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $_) { $loja_pdv = null; }

    // Loja desativada
    if (!$loja_pdv) {
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head><meta charset="UTF-8"><title>PDV — Loja Inativa</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
        </head>
        <body class="bg-light">
        <div class="d-flex align-items-center justify-content-center min-vh-100">
            <div class="text-center p-5 bg-white rounded-4 shadow" style="max-width:420px;">
                <div style="font-size:3rem;">⚠️</div>
                <h4 class="mt-3 fw-bold">Loja desativada</h4>
                <p class="text-muted">A loja vinculada à sua conta está desativada no momento. Contate o administrador.</p>
                <a href="<?= BASE_PATH ?>/dashboard" class="btn btn-outline-secondary mt-2">← Voltar ao painel</a>
            </div>
        </div>
        </body></html>
        <?php
        exit;
    }
}
