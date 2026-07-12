<?php
/**
 * Relatório: Estoque Crítico / Lista de Compras
 * Formatado para impressão A4 — jato de tinta
 */
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/Produto.php';
iniciar_sessao();

$roles_permitidos = ['gerente', 'super_admin'];
require_once __DIR__ . '/../../app/middleware/auth_check.php';

$u       = usuario_logado();
$criticos = (new Produto())->estoqueCritico();
$gerado  = date('d/m/Y \à\s H:i');

// Agrupa por loja
$por_loja = [];
foreach ($criticos as $item) {
    $por_loja[$item['loja_nome']][] = $item;
}
$total_itens = count($criticos);
$total_lojas = count($por_loja);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>/public/img/favicon.png">
    <title>Lista de Compras — Estoque Crítico | Desffrut</title>

    <style>
    /* ════════════════════════════════════════════════════════════
       ESTILOS GERAIS (tela + impressão)
    ════════════════════════════════════════════════════════════ */
    *, *::before, *::after { box-sizing: border-box; }

    body {
        font-family: 'Segoe UI', Arial, sans-serif;
        font-size: 13px;
        color: #1a1a1a;
        background: #f5f5f5;
        margin: 0;
        padding: 0;
    }

    .pagina {
        max-width: 210mm;
        margin: 20px auto;
        background: #fff;
        padding: 20mm 15mm 15mm;
        box-shadow: 0 2px 12px rgba(0,0,0,.12);
    }

    /* ── Cabeçalho ── */
    .rel-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 3px solid #2e7d32;
        padding-bottom: 10px;
        margin-bottom: 14px;
    }
    .rel-logo-text {
        font-size: 1.5rem;
        font-weight: 800;
        color: #2e7d32;
        letter-spacing: -0.5px;
    }
    .rel-logo-sub {
        font-size: .8rem;
        color: #555;
        margin-top: 2px;
    }
    .rel-meta {
        text-align: right;
        font-size: .78rem;
        color: #555;
        line-height: 1.6;
    }
    .rel-meta strong { color: #222; }

    /* ── Alertas ── */
    .rel-alerta {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #fff3e0;
        border-left: 5px solid #e65100;
        padding: 10px 14px;
        border-radius: 0 8px 8px 0;
        margin-bottom: 16px;
        font-size: .84rem;
    }
    .rel-alerta-icon { font-size: 1.4rem; }
    .rel-alerta strong { color: #bf360c; }

    .rel-ok {
        background: #e8f5e9;
        border-left: 5px solid #2e7d32;
    }
    .rel-ok strong { color: #1b5e20; }

    /* ── Nome da loja ── */
    .rel-loja-titulo {
        font-size: .9rem;
        font-weight: 700;
        color: #1b5e20;
        margin: 18px 0 6px;
        padding: 6px 10px;
        background: #e8f5e9;
        border-left: 4px solid #2e7d32;
        border-radius: 0 6px 6px 0;
    }

    /* ── Tabela principal ── */
    .rel-tabela {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
        font-size: .82rem;
    }
    .rel-tabela th {
        background: #2e7d32;
        color: #fff;
        padding: 7px 9px;
        text-align: left;
        font-weight: 600;
        font-size: .75rem;
        letter-spacing: .2px;
        border: 1px solid #1b5e20;
    }
    .rel-tabela th.text-right { text-align: right; }
    .rel-tabela td {
        padding: 7px 9px;
        border: 1px solid #d0d0d0;
        vertical-align: middle;
    }
    .rel-tabela tbody tr:nth-child(even) td { background: #fafafa; }
    .rel-tabela tbody tr:hover td { background: #f1f8f1; }

    /* Linhas de status */
    .row-sem-estoque td {
        background: #ffcdd2 !important;
        font-weight: 700;
    }
    .row-critico td { /* padrão — sem fundo especial */ }

    /* Colunas numéricas */
    .text-right { text-align: right; }
    .text-center { text-align: center; }

    /* Células coloridas */
    .cel-atual-zero   { color: #b71c1c; font-weight: 700; }
    .cel-atual-critico{ color: #e65100; font-weight: 700; }
    .cel-deficit      { color: #c62828; font-weight: 700; }
    .cel-sugestao     { color: #1b5e20; font-weight: 700; }

    /* Badge categoria */
    .badge-cat {
        display: inline-block;
        padding: 2px 7px;
        border-radius: 20px;
        font-size: .7rem;
        font-weight: 600;
        background: #c8e6c9;
        color: #1b5e20;
    }

    /* Input de sugestão (tela apenas) */
    .input-qtd {
        border: 1px solid #ccc;
        border-radius: 4px;
        padding: 3px 6px;
        font-size: .82rem;
        width: 120px;
        color: #1b5e20;
        font-weight: 700;
    }
    .input-obs {
        border: 1px solid #ccc;
        border-radius: 4px;
        padding: 3px 6px;
        font-size: .79rem;
        width: 180px;
    }

    /* ── Rodapé ── */
    .rel-rodape {
        margin-top: 24px;
        padding-top: 10px;
        border-top: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: .72rem;
        color: #777;
    }

    /* ── Botões (tela) ── */
    .rel-acoes {
        position: fixed;
        top: 16px;
        right: 24px;
        display: flex;
        gap: 8px;
        z-index: 100;
    }
    .btn-imprimir {
        background: #2e7d32;
        color: #fff;
        border: none;
        padding: 9px 20px;
        border-radius: 8px;
        font-size: .85rem;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(0,0,0,.2);
    }
    .btn-imprimir:hover { background: #1b5e20; }
    .btn-voltar {
        background: #fff;
        color: #444;
        border: 1px solid #ccc;
        padding: 9px 16px;
        border-radius: 8px;
        font-size: .85rem;
        cursor: pointer;
        text-decoration: none;
        box-shadow: 0 1px 4px rgba(0,0,0,.1);
    }

    /* ════════════════════════════════════════════════════════════
       IMPRESSÃO A4
    ════════════════════════════════════════════════════════════ */
    @media print {
        @page {
            size: A4 portrait;
            margin: 1.5cm 1.5cm 2cm 1.5cm;
        }
        @page :first { margin-top: 1cm; }

        body {
            background: #fff;
            font-size: 11px;
            color: #000;
        }

        .pagina {
            max-width: 100%;
            margin: 0;
            padding: 0;
            box-shadow: none;
        }

        /* Oculta elementos de tela */
        .rel-acoes,
        .no-print,
        .input-obs { display: none !important; }

        /* Mostra elemento só na impressão */
        .only-print { display: block !important; }

        /* Cabeçalho repete em cada página */
        .rel-header {
            border-bottom: 2.5px solid #2e7d32;
            margin-bottom: 10px;
        }

        /* Tabela: bordas visíveis e sem corte de linha */
        .rel-tabela {
            font-size: 10px;
            page-break-inside: auto;
        }
        .rel-tabela th {
            background: #2e7d32 !important;
            color: #fff !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            border: 1px solid #1b5e20;
        }
        .rel-tabela td {
            border: 1px solid #bbb;
        }
        .rel-tabela tr {
            page-break-inside: avoid;
        }
        .rel-tabela tbody tr:nth-child(even) td {
            background: #f4f4f4 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .row-sem-estoque td {
            background: #ffcdd2 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Badge: sem fundo impresso para economizar tinta */
        .badge-cat {
            border: 1px solid #2e7d32;
            background: none !important;
            padding: 1px 5px;
        }

        /* Título de loja: page-break-before no 2.º em diante */
        .rel-loja-bloco + .rel-loja-bloco {
            page-break-before: always;
        }

        /* Alerta */
        .rel-alerta {
            background: #fff3e0 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Campo sugestão: mostra versão de impressão */
        .input-qtd { display: none; }
        .qtd-print  { display: inline !important; font-weight: 700; color: #1b5e20; }

        /* Rodapé */
        .rel-rodape {
            position: fixed;
            bottom: 0;
            left: 0; right: 0;
            font-size: .68rem;
            color: #555;
            border-top: 1px solid #ccc;
            padding-top: 4px;
        }

        /* Número de página via CSS */
        .rel-rodape-pagina::after {
            content: counter(page) ' / ' counter(pages);
        }
        counter-reset: page;
        counter-increment: page;
    }
    /* Oculta span de impressão na tela */
    .qtd-print { display: none; }
    .only-print { display: none; }
    </style>
</head>
<body>

<!-- Botões fixos (tela apenas) -->
<div class="rel-acoes no-print">
    <button class="btn-imprimir" onclick="window.print()">🖨️ Imprimir / Salvar PDF</button>
    <a href="<?= BASE_PATH ?>/dashboard" class="btn-voltar">← Dashboard</a>
</div>

<div class="pagina">

    <!-- ── Cabeçalho ───────────────────────────────────────────────────────── -->
    <div class="rel-header">
        <div>
            <div class="rel-logo-text">🌿 Desffrut</div>
            <div class="rel-logo-sub">Lista de Compras — Estoque Crítico</div>
        </div>
        <div class="rel-meta">
            <strong>Gerado em:</strong> <?= $gerado ?><br>
            <strong>Usuário:</strong> <?= htmlspecialchars($u['nome'] ?? '—') ?><br>
            <strong>Referência:</strong> <?= date('d/m/Y') ?>
        </div>
    </div>

    <?php if (empty($criticos)): ?>

    <!-- ── Tudo OK ────────────────────────────────────────────────────────── -->
    <div class="rel-alerta rel-ok">
        <span class="rel-alerta-icon">✅</span>
        <div><strong>Estoque regularizado.</strong> Nenhum produto está abaixo do mínimo no momento.</div>
    </div>

    <?php else: ?>

    <!-- ── Alerta de resumo ───────────────────────────────────────────────── -->
    <div class="rel-alerta">
        <span class="rel-alerta-icon">⚠️</span>
        <div>
            <strong><?= $total_itens ?> produto(s)</strong> com estoque abaixo do mínimo em
            <strong><?= $total_lojas ?> loja(s)</strong>.
            Verifique disponibilidade com fornecedores antes de confirmar os pedidos.
        </div>
    </div>

    <!-- ── Tabelas por loja ───────────────────────────────────────────────── -->
    <?php foreach ($por_loja as $loja_nome => $itens):
        $sem_estoque = array_filter($itens, fn($i) => (float)$i['quantidade'] == 0);
        $criticos_n  = count($itens);
    ?>
    <div class="rel-loja-bloco">
        <div class="rel-loja-titulo">
            🏪 <?= htmlspecialchars($loja_nome) ?>
            <span style="font-size:.75rem;font-weight:400;color:#444;margin-left:8px;">
                <?= $criticos_n ?> item(ns) crítico(s)
                <?= count($sem_estoque) ? ' · ' . count($sem_estoque) . ' sem estoque' : '' ?>
            </span>
        </div>

        <table class="rel-tabela">
            <thead>
                <tr>
                    <th style="width:24px;">#</th>
                    <th>Produto</th>
                    <th>Categoria</th>
                    <th class="text-right">Atual</th>
                    <th class="text-right">Mínimo</th>
                    <th class="text-right">Déficit</th>
                    <th style="min-width:130px;">Qtd. Compra</th>
                    <th class="no-print">Obs. / Fornecedor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $i => $p):
                    $zero     = (float)$p['quantidade'] == 0;
                    $sugestao = round((float)$p['deficit'] * 2, 3);
                    $un       = $p['unidade_medida'];
                ?>
                <tr class="<?= $zero ? 'row-sem-estoque' : 'row-critico' ?>">
                    <td class="text-center" style="color:#888;"><?= $i + 1 ?></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($p['nome']) ?></td>
                    <td><span class="badge-cat"><?= htmlspecialchars($p['categoria']) ?></span></td>
                    <td class="text-right <?= $zero ? 'cel-atual-zero' : 'cel-atual-critico' ?>">
                        <?= number_format((float)$p['quantidade'], 3, ',', '.') ?> <?= $un ?>
                    </td>
                    <td class="text-right" style="color:#555;">
                        <?= number_format((float)$p['estoque_minimo'], 3, ',', '.') ?> <?= $un ?>
                    </td>
                    <td class="text-right cel-deficit">
                        <?= number_format((float)$p['deficit'], 3, ',', '.') ?> <?= $un ?>
                    </td>
                    <td class="cel-sugestao">
                        <input type="text" class="input-qtd no-print"
                               value="<?= $sugestao ?> <?= $un ?>">
                        <span class="qtd-print"><?= $sugestao ?> <?= $un ?></span>
                    </td>
                    <td class="no-print">
                        <input type="text" class="input-obs"
                               placeholder="Fornecedor / observação…">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" style="font-weight:700;font-size:.78rem;color:#555;border:1px solid #d0d0d0;padding:6px 9px;">
                        Total: <?= $criticos_n ?> produto(s)
                    </td>
                    <td colspan="6" style="border:1px solid #d0d0d0;"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>

    <!-- ── Legenda ─────────────────────────────────────────────────────────── -->
    <?php if ($total_itens): ?>
    <div style="display:flex;gap:16px;margin-top:8px;margin-bottom:8px;font-size:.78rem;">
        <div style="display:flex;align-items:center;gap:5px;">
            <span style="display:inline-block;width:14px;height:14px;background:#ffcdd2;border:1px solid #bbb;border-radius:2px;"></span>
            Sem estoque (quantidade = 0)
        </div>
        <div style="display:flex;align-items:center;gap:5px;">
            <span style="display:inline-block;width:14px;height:14px;background:#fff;border:1px solid #bbb;border-radius:2px;"></span>
            Estoque crítico (abaixo do mínimo)
        </div>
        <div style="margin-left:auto;font-size:.75rem;color:#777;">
            * Qtd. Compra sugerida = déficit × 2 (duas vezes o mínimo)
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Rodapé ──────────────────────────────────────────────────────────── -->
    <div class="rel-rodape">
        <span>Desffrut — Lista de compras interna. Gerado em <?= $gerado ?>.</span>
        <span class="rel-rodape-pagina only-print">Página </span>
        <span>Não distribuir externamente.</span>
    </div>

</div><!-- /pagina -->

</body>
</html>
