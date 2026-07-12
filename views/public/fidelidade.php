<?php
/**
 * Desffrut — Programa de Fidelidade (landing page pública)
 */
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
iniciar_sessao();
require_once __DIR__ . '/../../app/middleware/maintenance_check.php';

$titulo_pagina  = 'Programa de Fidelidade';
$og_description = 'Acumule pontos a cada compra no ' . NOME_SISTEMA . ' e troque por descontos. R$1 = 1 ponto, 100 pontos = R$1 off.';
$canonical_url  = BASE_URL . BASE_PATH . '/fidelidade';
$nav_ativa      = 'fidelidade';
$mostrar_sacola = true;

require_once __DIR__ . '/../../app/views/layout/header.php';

// Configurações do programa vindas do CMS
$pontos_indicacao = PONTOS_POR_REAL; // fallback da constante
try {
    $pdo  = db();
    $stmt = $pdo->query("SELECT chave, valor FROM configuracoes
                         WHERE chave IN ('pontos_indicacao','pontos_por_real')");
    if ($stmt) {
        foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
            if ($k === 'pontos_indicacao') $pontos_indicacao = (int)$v;
        }
    }
} catch (Throwable $_) {}

$usuario = usuario_logado();
?>

<style>
.fidel-hero {
    background: linear-gradient(135deg,#1b5e20 0%,#2e7d32 60%,#43a047 100%);
    color:#fff; padding:60px 20px 50px; text-align:center;
}
.fidel-hero h1 { font-size:2.2rem; font-weight:800; margin:0 0 10px; }
.fidel-hero p  { opacity:.88; max-width:520px; margin:0 auto 24px; font-size:1.05rem; }
.fidel-hero .badge-pts {
    background:rgba(255,255,255,.2); border:2px solid rgba(255,255,255,.5);
    border-radius:40px; padding:8px 24px; font-size:1rem; font-weight:700;
    display:inline-block;
}

.fidel-body { max-width:860px; margin:0 auto; padding:50px 20px 70px; }

.como-funciona {
    display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px; margin-bottom:48px;
}
@media(max-width:600px){ .como-funciona { grid-template-columns:1fr; } }

.passo-card {
    background:#fff; border-radius:14px; border:1px solid #e8f5e9;
    box-shadow:0 2px 10px rgba(0,0,0,.06); padding:28px 20px; text-align:center;
    position:relative;
}
.passo-card .num {
    position:absolute; top:-14px; left:50%; transform:translateX(-50%);
    background:var(--cor-primaria,#2e7d32); color:#fff;
    border-radius:50%; width:28px; height:28px;
    display:inline-flex; align-items:center; justify-content:center;
    font-size:.85rem; font-weight:700;
}
.passo-card .ico { font-size:2.4rem; margin:10px 0 10px; }
.passo-card h3  { font-size:.95rem; font-weight:700; color:#1b5e20; margin:0 0 6px; }
.passo-card p   { font-size:.85rem; color:#666; margin:0; }

.regras-box {
    background:#f9fbe7; border:1px solid #dcedc8; border-radius:12px;
    padding:28px 28px; margin:0 0 36px;
}
.regras-box h2 { font-size:1rem; font-weight:700; color:#1b5e20; margin:0 0 16px; }
.regras-box table { width:100%; border-collapse:collapse; font-size:.9rem; }
.regras-box td, .regras-box th {
    padding:10px 14px; border-bottom:1px solid #dcedc8; text-align:left;
}
.regras-box th { font-weight:700; color:#1b5e20; background:#e8f5e9; }
.regras-box tr:last-child td { border-bottom:none; }

.indicacao-box {
    background:linear-gradient(135deg,#1b5e20,#388e3c);
    color:#fff; border-radius:14px; padding:32px 28px; text-align:center; margin-bottom:40px;
}
.indicacao-box h2 { font-size:1.2rem; font-weight:800; margin:0 0 8px; }
.indicacao-box p  { opacity:.88; font-size:.95rem; margin:0 0 20px; }

.fidel-cta { text-align:center; }
</style>

<div class="fidel-hero">
    <h1>🎁 Programa de Fidelidade</h1>
    <p>Compre, acumule pontos e troque por descontos reais. Quanto mais você compra, mais você economiza!</p>
    <div class="badge-pts">R$ 1,00 = 1 ponto &nbsp;|&nbsp; 100 pontos = R$ 1,00 de desconto</div>
</div>

<div class="fidel-body">

    <div class="como-funciona">
        <div class="passo-card">
            <div class="num">1</div>
            <div class="ico">👤</div>
            <h3>Cadastre-se</h3>
            <p>Crie sua conta gratuita no portal e informe seu CPF para acumular em compras presenciais também.</p>
        </div>
        <div class="passo-card">
            <div class="num">2</div>
            <div class="ico">🛒</div>
            <h3>Compre e acumule</h3>
            <p>A cada R$ 1,00 em compras — online ou no balcão com seu CPF — você ganha 1 ponto automaticamente.</p>
        </div>
        <div class="passo-card">
            <div class="num">3</div>
            <div class="ico">💸</div>
            <h3>Resgate descontos</h3>
            <p>100 pontos valem R$ 1,00 de desconto. Use parcialmente ou no total do seu próximo pedido.</p>
        </div>
    </div>

    <div class="regras-box">
        <h2>📋 Regras do programa</h2>
        <table>
            <thead>
                <tr><th>Regra</th><th>Detalhe</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Acúmulo</strong></td>
                    <td>R$ 1,00 gasto = 1 ponto. Válido para compras online e presenciais com CPF informado.</td>
                </tr>
                <tr>
                    <td><strong>Resgate</strong></td>
                    <td>100 pontos = R$ 1,00 de desconto. Pode ser usado parcialmente.</td>
                </tr>
                <tr>
                    <td><strong>Crédito</strong></td>
                    <td>Pontos são creditados após a confirmação de entrega do pedido.</td>
                </tr>
                <tr>
                    <td><strong>Cancelamento</strong></td>
                    <td>Se um pedido for cancelado, os pontos correspondentes são estornados automaticamente.</td>
                </tr>
                <tr>
                    <td><strong>Validade</strong></td>
                    <td>Pontos não expiram enquanto a conta estiver ativa (ao menos uma compra por ano).</td>
                </tr>
                <tr>
                    <td><strong>Transferência</strong></td>
                    <td>Pontos são pessoais e intransferíveis entre contas.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="indicacao-box">
        <h2>🤝 Indique e Ganhe <?= $pontos_indicacao ?> pontos!</h2>
        <p>Compartilhe seu link de indicação com amigos e família. Quando eles fizerem o primeiro pedido, você ganha <strong><?= $pontos_indicacao ?> pontos bônus</strong> automaticamente — sem limite de indicações!</p>
        <?php if ($usuario && $usuario['role'] === 'cliente'): ?>
        <a href="<?= BASE_PATH ?>/meu-perfil#indicacao" class="btn btn-light btn-sm fw-bold">
            🔗 Ver meu link de indicação
        </a>
        <?php else: ?>
        <a href="<?= BASE_PATH ?>/cadastro" class="btn btn-light btn-sm fw-bold">
            Criar conta e obter meu link →
        </a>
        <?php endif; ?>
    </div>

    <div class="fidel-cta">
        <?php if ($usuario && $usuario['role'] === 'cliente'): ?>
        <a href="<?= BASE_PATH ?>/meu-perfil" class="btn btn-success btn-lg me-2">Ver meus pontos</a>
        <a href="<?= BASE_PATH ?>/" class="btn btn-outline-success btn-lg">🛒 Comprar agora</a>
        <?php else: ?>
        <a href="<?= BASE_PATH ?>/cadastro" class="btn btn-success btn-lg me-2">Criar conta grátis</a>
        <a href="<?= BASE_PATH ?>/login" class="btn btn-outline-success btn-lg">Já tenho conta</a>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../../app/views/layout/footer.php'; ?>
