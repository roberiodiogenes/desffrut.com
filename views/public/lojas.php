<?php
/**
 * Desffrut — Nossas Lojas (página pública)
 * Exibe endereço, horário e WhatsApp de cada filial.
 * Dados gerenciados pelo CMS (Fase 8 — fragmento cms_lojas.php).
 */
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
iniciar_sessao();
require_once __DIR__ . '/../../app/middleware/maintenance_check.php';

// Busca lojas ativas ANTES de incluir header (para gerar JSON-LD)
$lojas = [];
try {
    $pdo   = db();
    $stmt  = $pdo->query("SELECT id, nome, endereco, telefone, horario_funcionamento, whatsapp_link
                           FROM lojas WHERE ativo = 1 ORDER BY id ASC");
    $lojas = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $_) {}

$titulo_pagina  = 'Nossas Lojas';
$og_description = 'Encontre a filial ' . NOME_SISTEMA . ' mais próxima de você. Endereços, horários e WhatsApp de cada loja.';
$canonical_url  = BASE_URL . BASE_PATH . '/lojas';
$nav_ativa      = 'lojas';
$mostrar_sacola = true;

// JSON-LD: LocalBusiness por filial
$_jld_stores = array_map(fn($l) => [
    '@context' => 'https://schema.org',
    '@type'    => 'LocalBusiness',
    'name'     => NOME_SISTEMA . ($l['nome'] ? ' — ' . $l['nome'] : ''),
    'url'      => BASE_URL . BASE_PATH . '/lojas',
    'address'  => ['@type' => 'PostalAddress', 'streetAddress' => $l['endereco'] ?? ''],
    'telephone' => $l['telefone'] ?? '',
    'openingHours' => $l['horario_funcionamento'] ?? '',
], $lojas);
$json_ld = $lojas ? json_encode(count($_jld_stores) === 1 ? $_jld_stores[0] : $_jld_stores,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';

require_once __DIR__ . '/../../app/views/layout/header.php';
?>

<style>
.lojas-hero {
    background: linear-gradient(135deg,#1b5e20 0%,#2e7d32 60%,#43a047 100%);
    color:#fff; padding:52px 20px 40px; text-align:center;
}
.lojas-hero h1 { font-size:2rem; font-weight:800; margin:0 0 8px; }
.lojas-hero p  { opacity:.85; max-width:500px; margin:0 auto; font-size:1rem; }

.lojas-grid {
    display:grid;
    grid-template-columns: repeat(auto-fill, minmax(300px,1fr));
    gap:24px;
    max-width:1000px;
    margin:40px auto;
    padding:0 20px 60px;
}

.loja-card {
    background:#fff;
    border-radius:14px;
    border:1px solid #e8f5e9;
    box-shadow:0 2px 12px rgba(0,0,0,.06);
    padding:28px;
    transition:box-shadow .2s, transform .2s;
}
.loja-card:hover { box-shadow:0 6px 24px rgba(0,0,0,.12); transform:translateY(-2px); }

.loja-nome {
    font-size:1.1rem; font-weight:700; color:#1b5e20;
    margin:0 0 16px; display:flex; align-items:center; gap:8px;
}
.loja-nome span.num {
    background:var(--cor-primaria,#2e7d32); color:#fff;
    border-radius:50%; width:28px; height:28px; display:inline-flex;
    align-items:center; justify-content:center; font-size:.8rem; flex-shrink:0;
}
.loja-info { list-style:none; padding:0; margin:0 0 20px; }
.loja-info li { display:flex; gap:10px; margin-bottom:10px;
                font-size:.9rem; color:#555; align-items:flex-start; }
.loja-info li .ico { flex-shrink:0; font-size:1rem; margin-top:1px; }
.loja-info li strong { color:#333; }

.btn-wa {
    display:inline-flex; align-items:center; gap:8px;
    background:#25d366; color:#fff; border-radius:8px;
    padding:10px 18px; font-weight:600; text-decoration:none;
    font-size:.9rem; width:100%; justify-content:center;
    transition:background .2s;
}
.btn-wa:hover { background:#128c7e; color:#fff; }

.btn-maps {
    display:inline-flex; align-items:center; gap:6px;
    color:#1b5e20; text-decoration:none; font-size:.82rem;
    margin-top:10px; justify-content:center; width:100%;
}
.btn-maps:hover { text-decoration:underline; }

.sem-lojas { text-align:center; padding:60px 20px; color:#888; }
</style>

<div class="lojas-hero">
    <h1>📍 Nossas Lojas</h1>
    <p>Produtos frescos direto para você. Escolha a filial mais próxima e venha nos visitar — ou peça a entrega!</p>
</div>

<div class="lojas-grid">
    <?php if (empty($lojas)): ?>
    <div class="sem-lojas" style="grid-column:1/-1">
        <p style="font-size:2rem">🏪</p>
        <p>Nenhuma loja cadastrada ainda.<br>Volte em breve!</p>
    </div>
    <?php else: ?>
    <?php foreach ($lojas as $i => $loja): ?>
    <div class="loja-card">
        <h2 class="loja-nome">
            <span class="num"><?= $i + 1 ?></span>
            <?= htmlspecialchars($loja['nome']) ?>
        </h2>
        <ul class="loja-info">
            <?php if ($loja['endereco']): ?>
            <li>
                <span class="ico">📍</span>
                <span><?= htmlspecialchars($loja['endereco']) ?></span>
            </li>
            <?php endif; ?>
            <?php if ($loja['horario_funcionamento']): ?>
            <li>
                <span class="ico">🕐</span>
                <span><?= nl2br(htmlspecialchars($loja['horario_funcionamento'])) ?></span>
            </li>
            <?php endif; ?>
            <?php if ($loja['telefone']): ?>
            <li>
                <span class="ico">📞</span>
                <a href="tel:<?= preg_replace('/\D/','',$loja['telefone']) ?>" style="color:inherit">
                    <?= htmlspecialchars($loja['telefone']) ?>
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <?php
        $wa_link = $loja['whatsapp_link'] ?? '';
        if (!$wa_link && $loja['telefone']) {
            $tel = preg_replace('/\D/', '', $loja['telefone']);
            if (!str_starts_with($tel, '55')) $tel = '55' . $tel;
            $wa_link = 'https://wa.me/' . $tel;
        }
        ?>
        <?php if ($wa_link): ?>
        <a href="<?= htmlspecialchars($wa_link) ?>" target="_blank" rel="noopener" class="btn-wa">
            <svg width="18" height="18" viewBox="0 0 32 32" fill="currentColor">
                <path d="M16 .5C7.44.5.5 7.44.5 16c0 2.83.74 5.49 2.04 7.8L.5 31.5l7.93-2.07A15.44 15.44 0 0016 31.5C24.56 31.5 31.5 24.56 31.5 16S24.56.5 16 .5zm8.2 21.7c-.35.98-2.02 1.87-2.77 1.9-.72.03-1.4.34-4.72-1.05-3.97-1.67-6.53-5.7-6.73-5.96-.2-.26-1.6-2.13-1.6-4.06s1.01-2.88 1.37-3.28c.35-.4.77-.5 1.02-.5l.74.01c.24.01.56-.09.87.67.35.83 1.18 2.87 1.28 3.08.1.2.17.44.03.7-.13.26-.2.42-.38.65-.19.23-.4.52-.57.7-.19.2-.38.41-.17.8.22.4.97 1.6 2.09 2.6 1.44 1.28 2.65 1.68 3.05 1.87.4.19.63.16.86-.1.24-.26 1.01-1.18 1.28-1.58.27-.4.54-.33.9-.2.37.13 2.35 1.11 2.75 1.31.4.2.67.3.77.47.1.17.1 1.01-.25 1.99z"/>
            </svg>
            Chamar no WhatsApp
        </a>
        <?php endif; ?>

        <?php if ($loja['endereco']): ?>
        <a href="https://maps.google.com/?q=<?= urlencode($loja['endereco']) ?>"
           target="_blank" rel="noopener" class="btn-maps">
            🗺 Ver no Google Maps
        </a>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../app/views/layout/footer.php'; ?>
