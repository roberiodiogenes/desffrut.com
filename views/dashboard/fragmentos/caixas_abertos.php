<?php
/**
 * Desffrut — Fragmento: Caixas Abertos (exclusivo super_admin)
 * Exibe status em tempo real dos caixas de cada filial.
 * Carregado via AJAX no dashboard.
 */
require_once __DIR__ . '/../../../app/config/config.php';
require_once __DIR__ . '/../../../app/config/database.php';
require_once __DIR__ . '/../../../app/helpers/functions.php';
iniciar_sessao();

$u = usuario_logado();
if (!$u || !in_array($u['role'], ['super_admin', 'dev_admin'], true)) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Acesso restrito ao dono do sistema.</div>';
    exit;
}

// Busca caixas abertos por loja
$caixas_abertos = [];
$lojas_sem_caixa = [];
try {
    $pdo = db();

    // Lojas ativas
    $lojas = $pdo->query("SELECT id, nome FROM lojas WHERE ativo=1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

    // Caixas abertos com totalizadores
    $stmt = $pdo->query("
        SELECT
            c.id AS caixa_id,
            c.loja_id,
            c.fundo_troco,
            c.aberto_em,
            u.nome AS operador,
            COALESCE(SUM(v.total_final), 0) AS total_vendas,
            COUNT(v.id) AS qtd_vendas
        FROM caixas c
        LEFT JOIN usuarios u ON u.id = c.usuario_id
        LEFT JOIN vendas v ON v.caixa_id = c.id AND v.status = 'finalizada'
        WHERE c.status = 'aberto'
        GROUP BY c.id, c.loja_id, c.fundo_troco, c.aberto_em, u.nome
        ORDER BY c.loja_id
    ");
    $caixas_raw = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    $caixas_por_loja = [];
    foreach ($caixas_raw as $cx) {
        $caixas_por_loja[$cx['loja_id']] = $cx;
    }

    foreach ($lojas as $loja) {
        if (isset($caixas_por_loja[$loja['id']])) {
            $cx = $caixas_por_loja[$loja['id']];
            $caixas_abertos[] = array_merge($loja, $cx);
        } else {
            $lojas_sem_caixa[] = $loja;
        }
    }

    // Totais consolidados
    $total_geral  = array_sum(array_column($caixas_abertos, 'total_vendas'));
    $qtd_geral    = array_sum(array_column($caixas_abertos, 'qtd_vendas'));

} catch (Throwable $_) {
    echo '<div class="alert alert-warning">Tabela de caixas ainda não disponível. Execute as migrations.</div>';
    exit;
}
?>

<style>
.cx-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; }
.cx-card { background:#fff; border-radius:12px; border:1px solid #e0e0e0; padding:18px 20px; }
.cx-card.aberto  { border-left:4px solid #2e7d32; }
.cx-card.fechado { border-left:4px solid #bdbdbd; background:#fafafa; opacity:.7; }
.cx-loja { font-size:.75rem; color:#999; text-transform:uppercase; letter-spacing:.5px; }
.cx-nome { font-weight:700; font-size:1rem; margin:2px 0 8px; }
.cx-stat { display:flex; justify-content:space-between; font-size:.88rem; margin-bottom:4px; }
.cx-stat span:last-child { font-weight:600; }
.cx-total { display:flex; justify-content:space-between; font-size:1.05rem;
            font-weight:800; color:#1b5e20; margin-top:10px; padding-top:8px;
            border-top:1px solid #e0e0e0; }
.cx-abertos-count { font-size:.82rem; color:#2e7d32; }
.cx-sem-caixa { color:#bdbdbd; font-size:.85rem; }
.resumo-bar { background:#e8f5e9; border-radius:10px; padding:16px 20px;
              display:flex; gap:24px; flex-wrap:wrap; margin-bottom:20px; }
.resumo-item strong { display:block; font-size:1.3rem; font-weight:800; color:#1b5e20; }
.resumo-item span   { font-size:.78rem; color:#666; }
</style>

<div class="resumo-bar">
    <div class="resumo-item">
        <strong><?= count($caixas_abertos) ?></strong>
        <span>caixa<?= count($caixas_abertos) != 1 ? 's' : '' ?> aberto<?= count($caixas_abertos) != 1 ? 's' : '' ?></span>
    </div>
    <div class="resumo-item">
        <strong>R$ <?= number_format($total_geral, 2, ',', '.') ?></strong>
        <span>faturamento total hoje</span>
    </div>
    <div class="resumo-item">
        <strong><?= $qtd_geral ?></strong>
        <span>venda<?= $qtd_geral != 1 ? 's' : ''?> no turno</span>
    </div>
    <div class="ms-auto d-flex align-items-center">
        <button class="btn btn-sm btn-outline-success" onclick="document.location.reload()">
            🔄 Atualizar
        </button>
    </div>
</div>

<div class="cx-grid">
    <?php foreach ($caixas_abertos as $cx): ?>
    <div class="cx-card aberto">
        <div class="cx-loja">🟢 Caixa Aberto</div>
        <div class="cx-nome"><?= htmlspecialchars($cx['nome']) ?></div>
        <div class="cx-stat"><span>Operador</span><span><?= htmlspecialchars($cx['operador'] ?? '—') ?></span></div>
        <div class="cx-stat"><span>Abertura</span><span><?= date('H:i', strtotime($cx['aberto_em'])) ?></span></div>
        <div class="cx-stat"><span>Fundo de troco</span><span>R$ <?= number_format($cx['fundo_troco'], 2, ',', '.') ?></span></div>
        <div class="cx-stat"><span>Vendas no turno</span><span><?= $cx['qtd_vendas'] ?></span></div>
        <div class="cx-total">
            <span>Total do turno</span>
            <span>R$ <?= number_format($cx['total_vendas'], 2, ',', '.') ?></span>
        </div>
        <div class="mt-2">
            <a href="<?= BASE_PATH ?>/pdv?loja_id=<?= $cx['loja_id'] ?>"
               class="btn btn-sm btn-outline-success">🖥 Abrir PDV</a>
        </div>
    </div>
    <?php endforeach; ?>

    <?php foreach ($lojas_sem_caixa as $loja): ?>
    <div class="cx-card fechado">
        <div class="cx-loja">⭕ Caixa Fechado</div>
        <div class="cx-nome cx-sem-caixa"><?= htmlspecialchars($loja['nome']) ?></div>
        <div class="cx-stat"><span>Status</span><span style="color:#bdbdbd;">Sem turno ativo</span></div>
        <div class="mt-2">
            <a href="<?= BASE_PATH ?>/pdv/abertura?loja_id=<?= $loja['id'] ?>"
               class="btn btn-sm btn-outline-secondary">🧾 Abrir Caixa</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (!$lojas && !$lojas_sem_caixa): ?>
<div class="text-center py-5 text-muted">
    <div style="font-size:2.5rem;">🏪</div>
    <p class="mt-2">Nenhuma loja ativa cadastrada.</p>
    <a href="<?= BASE_PATH ?>/admin/lojas" class="btn btn-outline-success btn-sm">Cadastrar Loja</a>
</div>
<?php endif; ?>
