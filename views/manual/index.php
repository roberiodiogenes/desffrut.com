<?php
/**
 * Desffrut — Manual de Uso da Equipe (Cat. 16)
 * Acesso: /manual
 * Accordion Bootstrap por perfil. Sem autenticação.
 * Não indexado via robots.txt.
 */
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
iniciar_sessao();

// ─── Filtro do manual por nível de acesso (role) ──────────────────────────────
// Cada role vê apenas a(s) seção(ões) do seu cargo + FAQ. super_admin/dev_admin
// veem o manual inteiro. Sem login (ou role sem mapeamento, ex.: cliente) → vê
// tudo, mantendo o acesso público original da página.
$_usuario_logado = $_SESSION['usuario'] ?? null;
$_role_atual     = $_usuario_logado['role'] ?? null;

$_todas_secoes = ['super-admin', 'gerente', 'rh', 'caixa', 'entregador', 'faq'];

$_secoes_por_role = [
    'dev_admin'     => $_todas_secoes,
    'super_admin'   => $_todas_secoes,
    'gerente'       => ['gerente', 'faq'],
    'rh_financeiro' => ['rh', 'faq'],
    'caixa'         => ['caixa', 'faq'],
    'entregador'    => ['entregador', 'faq'],
];

// ?completo=1 permite a quem tem visão filtrada (ex.: gerente treinando a equipe)
// consultar o manual inteiro sem precisar de outro login.
$_ver_completo   = isset($_GET['completo']);
$_filtro_mapeado = $_role_atual !== null && isset($_secoes_por_role[$_role_atual]);
$_secoes_visiveis = ($_ver_completo || !$_filtro_mapeado) ? $_todas_secoes : $_secoes_por_role[$_role_atual];
$_filtro_ativo    = $_filtro_mapeado && !$_ver_completo;
$_primeira_secao  = $_secoes_visiveis[0] ?? 'super-admin';

/** Retorna os atributos (classe do botão / collapse) da seção conforme se é a primeira visível. */
function _manualAttrs(string $id, string $primeira): array {
    return $id === $primeira
        ? ['btn' => '', 'col' => 'show', 'exp' => 'true']
        : ['btn' => 'collapsed', 'col' => '', 'exp' => 'false'];
}

$titulo_pagina  = 'Manual de Uso — Equipe';
$canonical_url  = BASE_URL . BASE_PATH . '/manual';
$robots         = 'noindex,nofollow';
$mostrar_sacola = false;
require_once __DIR__ . '/../../app/views/layout/header.php';
?>

<style>
.manual-hero {
    background:linear-gradient(135deg,#1b5e20 0%,#2e7d32 60%,#43a047 100%);
    color:#fff; padding:52px 20px 40px; text-align:center;
}
.manual-hero h1 { font-size:2rem; font-weight:800; margin:0 0 8px; }
.manual-hero p  { opacity:.85; max-width:560px; margin:0 auto; font-size:.97rem; }

.manual-body { max-width:840px; margin:0 auto; padding:40px 20px 70px; }

/* Navegação por perfil */
.manual-nav {
    display:flex; flex-wrap:wrap; gap:8px; margin-bottom:28px;
}
.manual-nav a {
    font-size:.82rem; font-weight:600; padding:6px 16px; border-radius:20px;
    text-decoration:none; border:2px solid;
    transition:background .15s, color .15s;
}
.manual-nav a:hover { opacity:.85; }
.nav-admin    { background:#1b5e20; color:#fff; border-color:#1b5e20; }
.nav-gerente  { background:#2e7d32; color:#fff; border-color:#2e7d32; }
.nav-rh       { background:#0277bd; color:#fff; border-color:#0277bd; }
.nav-caixa    { background:#e65100; color:#fff; border-color:#e65100; }
.nav-entregad { background:#4a148c; color:#fff; border-color:#4a148c; }
.nav-faq      { background:#424242; color:#fff; border-color:#424242; }

/* Badge de perfil */
.perfil-badge {
    display:inline-flex; align-items:center; gap:6px;
    font-size:.78rem; font-weight:700; padding:3px 10px; border-radius:12px;
    margin-bottom:4px;
}

/* Accordion customizado */
.accordion-button:not(.collapsed) { background:#e8f5e9; color:#1b5e20; box-shadow:none; }
.accordion-button:focus { box-shadow:none; }
.accordion-item { border:1px solid #dcedc8; border-radius:10px !important;
                  margin-bottom:10px; overflow:hidden; }
.accordion-button { font-weight:700; font-size:.97rem; }

/* Conteúdo do manual */
.manual-section h4 { font-size:.95rem; font-weight:700; color:#1b5e20;
                     margin:20px 0 8px; padding-left:12px;
                     border-left:3px solid var(--cor-primaria,#2e7d32); }
.manual-section p, .manual-section li { font-size:.9rem; color:#444; line-height:1.7; }
.manual-section ul { padding-left:1.4rem; margin-bottom:12px; }
.manual-section ol { padding-left:1.4rem; margin-bottom:12px; }
.manual-section .atalho {
    display:inline-block; background:#f5f5f5; border:1px solid #ddd;
    border-radius:4px; padding:1px 8px; font-family:monospace; font-size:.85rem;
    color:#333; margin:0 2px;
}
.manual-section .alerta {
    background:#fff3e0; border-left:4px solid #ff9800; padding:10px 14px;
    border-radius:0 8px 8px 0; font-size:.88rem; color:#555; margin:12px 0;
}
.manual-section .dica {
    background:#e8f5e9; border-left:4px solid #4caf50; padding:10px 14px;
    border-radius:0 8px 8px 0; font-size:.88rem; color:#555; margin:12px 0;
}

/* ── Fluxogramas ilustrados ─────────────────────────────────────────────── */
.fluxo {
    display:flex; flex-wrap:wrap; align-items:center;
    gap:6px; margin:18px 0 24px; justify-content:center;
}
.fluxo-passo {
    display:flex; flex-direction:column; align-items:center;
    background:#fff; border:2px solid var(--cor-primaria,#2e7d32);
    border-radius:12px; padding:12px 14px; min-width:100px; max-width:130px;
    text-align:center;
}
.fluxo-passo .fp-ico  { font-size:1.8rem; line-height:1; margin-bottom:4px; }
.fluxo-passo .fp-txt  { font-size:.75rem; font-weight:700; color:#1b5e20; line-height:1.3; }
.fluxo-seta { font-size:1.4rem; color:#a5d6a7; flex-shrink:0; }
@media(max-width:500px){
    .fluxo-passo { min-width:80px; padding:10px; }
    .fluxo-passo .fp-ico { font-size:1.4rem; }
    .fluxo-seta { font-size:1rem; }
}

/* ── Card de atalhos de teclado ─────────────────────────────────────────── */
.atalhos-grid {
    display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr));
    gap:10px; margin:16px 0 24px;
}
.atalho-card {
    background:#fff; border:2px solid #ddd; border-radius:10px;
    padding:14px 10px; text-align:center;
}
.atalho-card .ak-tecla {
    display:inline-block; background:#37474f; color:#fff;
    border-radius:6px; padding:4px 12px; font-size:1rem; font-weight:800;
    font-family:monospace; margin-bottom:8px; letter-spacing:.05em;
    box-shadow:0 3px 0 #1a2526;
}
.atalho-card .ak-desc { font-size:.78rem; color:#555; line-height:1.4; }

/* ── Mapa do dashboard ──────────────────────────────────────────────────── */
.dash-mapa {
    display:grid; grid-template-columns:1fr 1fr;
    gap:10px; margin:16px 0 24px;
}
@media(max-width:480px){ .dash-mapa { grid-template-columns:1fr; } }
.dash-mapa-item {
    background:#f9fbe7; border:1px solid #dcedc8; border-radius:10px;
    padding:12px 14px; font-size:.85rem;
}
.dash-mapa-item strong { color:#1b5e20; display:block; margin-bottom:4px; }

.manual-versao { text-align:center; margin-top:48px; font-size:.8rem; color:#aaa; }
</style>

<div class="manual-hero">
    <h1>📖 Manual de Uso — Equipe <?= htmlspecialchars(NOME_SISTEMA) ?></h1>
    <p>Guia completo para todos os cargos da equipe. Clique na seção correspondente ao seu cargo.</p>
</div>

<div class="manual-body">

    <!-- Navegação rápida por perfil -->
    <nav class="manual-nav">
        <?php if (in_array('super-admin', $_secoes_visiveis, true)): ?><a href="#super-admin"  class="nav-admin">👑 Dono / Responsável</a><?php endif; ?>
        <?php if (in_array('gerente', $_secoes_visiveis, true)): ?><a href="#gerente"      class="nav-gerente">🏪 Gerente</a><?php endif; ?>
        <?php if (in_array('rh', $_secoes_visiveis, true)): ?><a href="#rh"           class="nav-rh">📋 RH / Financeiro</a><?php endif; ?>
        <?php if (in_array('caixa', $_secoes_visiveis, true)): ?><a href="#caixa"        class="nav-caixa">🛒 Caixa</a><?php endif; ?>
        <?php if (in_array('entregador', $_secoes_visiveis, true)): ?><a href="#entregador"   class="nav-entregad">🛵 Entregador</a><?php endif; ?>
        <?php if (in_array('faq', $_secoes_visiveis, true)): ?><a href="#faq"          class="nav-faq">❓ FAQ</a><?php endif; ?>
    </nav>

    <?php if ($_filtro_ativo): ?>
    <div class="alert alert-success d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 px-3 mb-3" style="font-size:.85rem;">
        <span>📌 Mostrando apenas a seção do seu perfil.</span>
        <a href="?completo=1" class="fw-semibold">Ver manual completo →</a>
    </div>
    <?php elseif ($_ver_completo && $_filtro_mapeado): ?>
    <div class="alert alert-secondary d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 px-3 mb-3" style="font-size:.85rem;">
        <span>📖 Exibindo o manual completo.</span>
        <a href="?" class="fw-semibold">Ver só a minha seção →</a>
    </div>
    <?php endif; ?>

    <div class="accordion" id="manualAccordion">

        <!-- ═══════════════════════════════════════════════════════════ SUPER ADMIN -->
        <?php if (in_array('super-admin', $_secoes_visiveis, true)): $_a = _manualAttrs('super-admin', $_primeira_secao); ?>
        <div class="accordion-item" id="super-admin">
            <h2 class="accordion-header">
                <button class="accordion-button <?= $_a['btn'] ?>" type="button" data-bs-toggle="collapse" data-bs-target="#colSuperAdmin" aria-expanded="<?= $_a['exp'] ?>">
                    👑 &nbsp;Dono / Responsável (Super Admin)
                    <span class="perfil-badge ms-2" style="background:#e8f5e9;color:#1b5e20;">Acesso total ao sistema</span>
                </button>
            </h2>
            <div id="colSuperAdmin" class="accordion-collapse collapse <?= $_a['col'] ?>" data-bs-parent="#manualAccordion">
                <div class="accordion-body manual-section">

                    <h4>O que o Dono pode fazer no sistema?</h4>
                    <!-- Mapa visual do dashboard -->
                    <div class="dash-mapa">
                        <div class="dash-mapa-item">
                            <strong>🎨 Aparência do portal</strong>
                            Logo, cores, banners e nome da loja
                        </div>
                        <div class="dash-mapa-item">
                            <strong>📊 Relatórios e lucro</strong>
                            Faturamento, despesas, resultado do mês
                        </div>
                        <div class="dash-mapa-item">
                            <strong>🏪 Dados das filiais</strong>
                            Endereço, horário e WhatsApp de cada loja
                        </div>
                        <div class="dash-mapa-item">
                            <strong>📡 Anúncios e campanhas</strong>
                            Medir retorno de anúncios, criar promoções sazonais
                        </div>
                        <div class="dash-mapa-item">
                            <strong>🔐 Segurança</strong>
                            Histórico de tudo que aconteceu no sistema
                        </div>
                        <div class="dash-mapa-item">
                            <strong>🤝 Parceiros B2B</strong>
                            Leads e funil de prospecção de clientes corporativos
                        </div>
                    </div>

                    <h4>Primeiro acesso</h4>
                    <ol>
                        <li>Acesse <strong><?= BASE_PATH ?>/login</strong> com o e-mail e senha cadastrados para o perfil de dono.</li>
                        <li>Você irá direto para o <strong>Painel de Controle (Dashboard)</strong> automaticamente.</li>
                        <li>Na primeira vez, configure a aparência em <strong>CMS &amp; Portal → Identidade Visual</strong>: coloque o logo, nome da loja, slogan e cores.</li>
                    </ol>

                    <h4>Configurar a aparência das lojas e filiais</h4>
                    <p>No Painel → <strong>CMS &amp; Portal → Lojas</strong>: edite o endereço, horário de funcionamento e o número de WhatsApp de cada filial. Essas informações aparecem automaticamente na página pública "Nossas Lojas".</p>

                    <h4>Colocar banners na página inicial</h4>
                    <p>Painel → <strong>CMS &amp; Portal → Banners</strong>: envie imagens de promoções para aparecer no carrossel da página principal. Você pode ativar ou desativar cada banner e definir por quanto tempo ele aparece.</p>

                    <h4>Criar promoções sazonais (Dia das Mães, Natal etc.)</h4>
                    <p>Painel → <strong>CMS &amp; Portal → Campanhas</strong>: crie cupons de desconto globais para datas especiais. Defina a data de início e o fim da promoção — o sistema ativa e desativa automaticamente.</p>

                    <h4>Gerenciar quem acessa o sistema</h4>
                    <p>Para criar ou alterar o acesso de um funcionário, o desenvolvedor técnico (responsável pela manutenção do sistema) realiza essa operação. Os tipos de acesso disponíveis são:</p>
                    <ul>
                        <li><strong>Cliente</strong> — acesso apenas ao catálogo e ao próprio perfil</li>
                        <li><strong>Caixa</strong> — acesso ao sistema de vendas (PDV)</li>
                        <li><strong>Entregador</strong> — acessa apenas a lista de entregas do dia</li>
                        <li><strong>RH / Financeiro</strong> — acessa folha de pagamento, contas e relatórios</li>
                        <li><strong>Gerente</strong> — acessa produtos, estoque, pedidos e relatórios</li>
                        <li><strong>Dono / Super Admin</strong> — acesso completo a tudo</li>
                    </ul>
                    <div class="alerta">O tipo de acesso "dev_admin" (desenvolvedor do sistema) é exclusivo do técnico responsável e não deve ser atribuído a funcionários da loja.</div>

                    <h4>Ver os resultados financeiros do mês</h4>
                    <p>Painel → <strong>Relatórios → BI / Gráficos</strong>: visualize quanto a loja faturou, quanto gastou e qual foi o lucro. O relatório financeiro completo (chamado de DRE — Demonstração do Resultado do Exercício, que é basicamente um resumo de receitas e gastos) está na aba "DRE".</p>

                    <h4>Medir o retorno dos anúncios</h4>
                    <p>Painel → <strong>Marketing</strong>: veja quais anúncios geraram mais vendas. O sistema se integra com as ferramentas de anúncio do Facebook/Instagram (chamado de Meta Pixel — um código invisível que conta quando alguém que viu o anúncio fez um pedido) e com o Google Analytics (ferramenta gratuita do Google que conta quantas pessoas visitaram o site e compraram).</p>
                    <div class="dica">Para ativar essas medições, basta pedir ao desenvolvedor os números de identificação das contas de anúncio e inserir no painel Marketing → Pixels &amp; Tags.</div>

                    <h4>Consultar o histórico de segurança</h4>
                    <p>Painel → <strong>Administração → Auditoria</strong>: o sistema guarda um registro de tudo que aconteceu — quem fez sangria no caixa, quem cancelou uma venda, quem mudou o preço de um produto. Esse histórico é somente para consulta; ninguém consegue apagar os registros.</p>

                </div>
            </div>
        </div>
        <?php endif; // fim seção super-admin ?>

        <!-- ═══════════════════════════════════════════════════════════════ GERENTE -->
        <?php if (in_array('gerente', $_secoes_visiveis, true)): $_a = _manualAttrs('gerente', $_primeira_secao); ?>
        <div class="accordion-item" id="gerente">
            <h2 class="accordion-header">
                <button class="accordion-button <?= $_a['btn'] ?>" type="button" data-bs-toggle="collapse" data-bs-target="#colGerente" aria-expanded="<?= $_a['exp'] ?>">
                    🏪 &nbsp;Gerente
                    <span class="perfil-badge ms-2" style="background:#c8e6c9;color:#1b5e20;">Operação completa da loja</span>
                </button>
            </h2>
            <div id="colGerente" class="accordion-collapse collapse <?= $_a['col'] ?>" data-bs-parent="#manualAccordion">
                <div class="accordion-body manual-section">

                    <h4>Cadastrar e editar produtos</h4>
                    <ol>
                        <li>Painel → <strong>Produtos &amp; Estoque → Produtos</strong></li>
                        <li>Clique em <strong>+ Novo Produto</strong>.</li>
                        <li>Preencha nome, categoria, unidade de venda (quilograma ou unidade), código de barras (o número abaixo das listras do pacote — chamado de EAN), preço de custo e preço de venda.</li>
                        <li>Envie a foto do produto (formatos JPG, PNG ou WEBP, até 6 MB — o sistema converte automaticamente para um formato compacto).</li>
                        <li>Salve. O produto aparece no catálogo público imediatamente.</li>
                    </ol>
                    <div class="dica">Para esconder temporariamente um produto sem apagar o histórico de vendas dele, use o botão <strong>Ativo/Inativo</strong> na lista de produtos.</div>

                    <h4>Programar promoções</h4>
                    <p>Na tela de edição do produto, defina um <strong>Preço Promocional</strong> com data e hora de início e fim. O sistema aplica e remove o desconto automaticamente — sem precisar lembrar de alterar.</p>

                    <h4>Controlar o estoque</h4>
                    <p>Painel → <strong>Produtos &amp; Estoque → Estoque</strong>: ajuste as quantidades disponíveis em cada loja, defina o estoque mínimo (abaixo disso o sistema emite alerta) e veja os produtos que precisam ser repostos.</p>

                    <h4>Registrar perdas e produtos estragados (quebras)</h4>
                    <p>Painel → <strong>Produtos &amp; Estoque → Quebras</strong>: informe quais produtos estragaram ou foram avariados, com a quantidade e o motivo. O estoque é deduzido automaticamente e a perda fica registrada para o relatório financeiro.</p>

                    <h4>Gerenciar pedidos de entrega</h4>

                    <!-- Fluxo visual do pedido -->
                    <div class="fluxo">
                        <div class="fluxo-passo">
                            <div class="fp-ico">📥</div>
                            <div class="fp-txt">Pedido recebido</div>
                        </div>
                        <div class="fluxo-seta">→</div>
                        <div class="fluxo-passo">
                            <div class="fp-ico">✅</div>
                            <div class="fp-txt">Aceitar pedido</div>
                        </div>
                        <div class="fluxo-seta">→</div>
                        <div class="fluxo-passo">
                            <div class="fp-ico">📦</div>
                            <div class="fp-txt">Separar e despachar</div>
                        </div>
                        <div class="fluxo-seta">→</div>
                        <div class="fluxo-passo">
                            <div class="fp-ico">🛵</div>
                            <div class="fp-txt">Entregador a caminho</div>
                        </div>
                        <div class="fluxo-seta">→</div>
                        <div class="fluxo-passo">
                            <div class="fp-ico">🏠</div>
                            <div class="fp-txt">Entregue ao cliente</div>
                        </div>
                    </div>

                    <p>Painel → <strong>Pedidos &amp; Despacho</strong>:</p>
                    <ul>
                        <li><strong>Pendentes:</strong> pedidos recém chegados, esperando confirmação → clique <em>✅ Aceitar</em>.</li>
                        <li><strong>Em Preparo:</strong> pedidos que você aceitou, sendo separados → clique <em>🛵 Despachar</em> para escolher o entregador.</li>
                        <li><strong>Em Rota:</strong> pedidos saíram para entrega.</li>
                        <li><strong>Via WhatsApp:</strong> pedidos enviados pelo WhatsApp do cliente, aguardando você confirmar pelo link recebido na mensagem.</li>
                    </ul>

                    <h4>Compras no CEASA</h4>
                    <p>Painel → <strong>Compras CEASA</strong>: veja a lista dos produtos que estão abaixo do estoque mínimo (o que precisa comprar), registre a chegada da mercadoria e distribua entre as filiais.</p>

                    <h4>Prospectar clientes corporativos (B2B)</h4>
                    <p>Painel → <strong>Prospecção &amp; CRM</strong>: gerencie os contatos de empresas interessadas em compras em volume. As oportunidades ficam organizadas em colunas por etapa da negociação (como um quadro de gestão de tarefas). Você também pode importar listas de contatos a partir de planilhas.</p>

                    <h4>Relatório de produtos que precisam de reposição</h4>
                    <p>Painel → <strong>Relatórios → Estoque Crítico</strong>: gera uma lista formatada para impressão de tudo que está abaixo do estoque mínimo — útil para levar ao CEASA ou enviar ao fornecedor.</p>

                </div>
            </div>
        </div>
        <?php endif; // fim seção gerente ?>

        <!-- ══════════════════════════════════════════════════════════ RH / FINANCEIRO -->
        <?php if (in_array('rh', $_secoes_visiveis, true)): $_a = _manualAttrs('rh', $_primeira_secao); ?>
        <div class="accordion-item" id="rh">
            <h2 class="accordion-header">
                <button class="accordion-button <?= $_a['btn'] ?>" type="button" data-bs-toggle="collapse" data-bs-target="#colRH" aria-expanded="<?= $_a['exp'] ?>">
                    📋 &nbsp;RH / Financeiro
                    <span class="perfil-badge ms-2" style="background:#e3f2fd;color:#0277bd;">Departamento pessoal e contas</span>
                </button>
            </h2>
            <div id="colRH" class="accordion-collapse collapse <?= $_a['col'] ?>" data-bs-parent="#manualAccordion">
                <div class="accordion-body manual-section">

                    <h4>Ciclo do funcionário no sistema</h4>
                    <div class="fluxo">
                        <div class="fluxo-passo">
                            <div class="fp-ico">👤</div>
                            <div class="fp-txt">Admissão</div>
                        </div>
                        <div class="fluxo-seta">→</div>
                        <div class="fluxo-passo">
                            <div class="fp-ico">🕐</div>
                            <div class="fp-txt">Registro de ponto</div>
                        </div>
                        <div class="fluxo-seta">→</div>
                        <div class="fluxo-passo">
                            <div class="fp-ico">💵</div>
                            <div class="fp-txt">Folha de pagamento</div>
                        </div>
                        <div class="fluxo-seta">→</div>
                        <div class="fluxo-passo">
                            <div class="fp-ico">🚪</div>
                            <div class="fp-txt">Desligamento</div>
                        </div>
                    </div>

                    <h4>Admitir um novo funcionário</h4>
                    <ol>
                        <li>Painel → <strong>RH → Funcionários → + Admitir</strong>.</li>
                        <li>O funcionário precisa já ter um cadastro de login no sistema. Informe o número de identificação dele no sistema (o número que aparece quando você abre o perfil dele), cargo, tipo de contrato (CLT, Pessoa Jurídica, Autônomo ou Estágio), carga horária e salário.</li>
                        <li>Salve. O funcionário entra na lista de ativos vinculado à filial correta.</li>
                    </ol>
                    <div class="alerta">O funcionário precisa ter sido cadastrado no sistema como usuário antes de ser registrado como funcionário. Se ainda não tem cadastro, peça ao dono ou desenvolvedor para criar.</div>

                    <h4>Registrar o ponto do dia</h4>
                    <p>Painel → <strong>RH → Ponto/Jornada</strong>: escolha o funcionário e o mês. Registre manualmente os horários de entrada, saída e intervalo. O sistema calcula automaticamente as horas trabalhadas e se há horas extras ou banco de horas.</p>

                    <h4>Gerar a folha de pagamento</h4>
                    <p>Painel → <strong>RH → Folha de Pagamento → + Gerar Folha</strong>: escolha o funcionário e o mês de referência. Informe horas extras, descontos e adicione observações se necessário. Salve — o valor vai automaticamente para a lista de contas a pagar.</p>

                    <h4>Lançar uma conta a pagar</h4>
                    <p>Painel → <strong>Financeiro → Contas a Pagar → + Lançar Conta</strong>: informe a descrição (ex.: "Aluguel — Junho/2026"), a categoria (Aluguel, Energia, Fornecedor, Folha de pagamento etc.), o valor, a data de vencimento e se repete todo mês. Contas vencidas ficam destacadas automaticamente.</p>

                    <h4>Marcar uma conta como paga</h4>
                    <p>Na lista de contas a pagar, clique em <strong>✓ Pago</strong> na linha da conta. Os totais de pendente e pago são atualizados na hora.</p>

                    <h4>Ver o fluxo de caixa</h4>
                    <p>Painel → <strong>Financeiro → Fluxo de Caixa</strong>: tabela mês a mês mostrando quanto entrou de dinheiro (vendas), quanto saiu (despesas) e qual o saldo. Use para apresentar ao dono ou planejar os próximos meses.</p>

                    <h4>Ver o resultado financeiro completo (DRE)</h4>
                    <p>Painel → <strong>Relatórios → BI → DRE</strong>: o DRE (Demonstração do Resultado do Exercício — resumo de quanto a loja ganhou, quanto gastou e qual foi o lucro de verdade) mostra o cálculo completo: receita bruta, custo dos produtos vendidos (CMV — Custo da Mercadoria Vendida), despesas operacionais, lucro operacional (EBITDA — resultado antes de impostos) e imposto estimado. Filtre por mês e por loja.</p>

                    <h4>Desligar um funcionário</h4>
                    <p>Painel → <strong>RH → Funcionários → [nome do funcionário] → Desligar</strong>: registra a data do desligamento e bloqueia o acesso ao sistema imediatamente. O histórico de ponto e pagamentos fica guardado para consulta futura.</p>

                </div>
            </div>
        </div>
        <?php endif; // fim seção rh ?>

        <!-- ══════════════════════════════════════════════════════════════════ CAIXA -->
        <?php if (in_array('caixa', $_secoes_visiveis, true)): $_a = _manualAttrs('caixa', $_primeira_secao); ?>
        <div class="accordion-item" id="caixa">
            <h2 class="accordion-header">
                <button class="accordion-button <?= $_a['btn'] ?>" type="button" data-bs-toggle="collapse" data-bs-target="#colCaixa" aria-expanded="<?= $_a['exp'] ?>">
                    🛒 &nbsp;Caixa (Operador de PDV)
                    <span class="perfil-badge ms-2" style="background:#fff3e0;color:#e65100;">Frente de loja</span>
                </button>
            </h2>
            <div id="colCaixa" class="accordion-collapse collapse <?= $_a['col'] ?>" data-bs-parent="#manualAccordion">
                <div class="accordion-body manual-section">

                    <h4>Como funciona o dia do caixa</h4>

                    <!-- Fluxo visual do dia do caixa -->
                    <div class="fluxo">
                        <div class="fluxo-passo">
                            <div class="fp-ico">🌅</div>
                            <div class="fp-txt">Abrir o caixa</div>
                        </div>
                        <div class="fluxo-seta">→</div>
                        <div class="fluxo-passo">
                            <div class="fp-ico">💰</div>
                            <div class="fp-txt">Vender durante o dia</div>
                        </div>
                        <div class="fluxo-seta">→</div>
                        <div class="fluxo-passo">
                            <div class="fp-ico">🏦</div>
                            <div class="fp-txt">Fazer sangria (se precisar)</div>
                        </div>
                        <div class="fluxo-seta">→</div>
                        <div class="fluxo-passo">
                            <div class="fp-ico">🌙</div>
                            <div class="fp-txt">Fechar o caixa</div>
                        </div>
                    </div>

                    <h4>Começando o dia — Abertura de caixa</h4>
                    <ol>
                        <li>Acesse <strong><?= BASE_PATH ?>/pdv/abertura</strong> (ou clique no link que o gerente informou).</li>
                        <li>Informe o <strong>Fundo de Troco</strong> — é o dinheiro que você já tem na gaveta para dar troco aos clientes.</li>
                        <li>Clique em <strong>Abrir Caixa</strong>. O sistema baixa automaticamente todos os produtos e preços do dia.</li>
                    </ol>
                    <div class="alerta">Não feche o navegador sem fechar o caixa no final do turno. Se a internet cair, continue vendendo normalmente — o sistema salva tudo no computador e envia quando a conexão voltar.</div>

                    <h4>Teclas de atalho — Use o teclado para vender mais rápido</h4>

                    <!-- Card visual dos atalhos -->
                    <div class="atalhos-grid">
                        <div class="atalho-card">
                            <div class="ak-tecla">F1</div>
                            <div class="ak-desc">Ir para a busca de produto</div>
                        </div>
                        <div class="atalho-card">
                            <div class="ak-tecla">F2</div>
                            <div class="ak-desc">Abrir tela de pagamento (fechar venda)</div>
                        </div>
                        <div class="atalho-card">
                            <div class="ak-tecla">F3</div>
                            <div class="ak-desc">Buscar cliente pelo CPF</div>
                        </div>
                        <div class="atalho-card">
                            <div class="ak-tecla">F4</div>
                            <div class="ak-desc">Abrir tela de sangria ou suprimento</div>
                        </div>
                        <div class="atalho-card">
                            <div class="ak-tecla">F5</div>
                            <div class="ak-desc">Ativar câmera para ler código de barras</div>
                        </div>
                        <div class="atalho-card">
                            <div class="ak-tecla">ESC</div>
                            <div class="ak-desc">Fechar qualquer janela aberta</div>
                        </div>
                    </div>

                    <h4>Buscar e vender um produto</h4>
                    <ol>
                        <li>Pressione <span class="atalho">F1</span> para ir ao campo de busca.</li>
                        <li>Digite parte do nome do produto ou passe o leitor de código de barras (o aparelho em cima do balcão). Os resultados aparecem enquanto você digita.</li>
                        <li>Clique no produto desejado para adicionar ao carrinho.</li>
                        <li>Para produto vendido por <strong>peso</strong>: clique no botão ⚖️ e o sistema lê o peso direto da balança.</li>
                    </ol>

                    <h4>Fechar a venda e receber o pagamento</h4>
                    <ol>
                        <li>Com os itens no carrinho, pressione <span class="atalho">F2</span>.</li>
                        <li>Escolha a forma de pagamento:
                            <ul>
                                <li>💵 <strong>Dinheiro</strong> — informe o valor recebido e o troco é calculado</li>
                                <li>💳 <strong>Cartão</strong> — passe o cartão na máquina e confirme</li>
                                <li>📱 <strong>Pix</strong> — o cliente paga e você confirma manualmente quando o dinheiro cair</li>
                                <li>⭐ <strong>Pontos de Fidelidade</strong> — usa os pontos acumulados do cliente</li>
                            </ul>
                        </li>
                        <li>Clique em <strong>Confirmar Venda</strong>. O cupom é impresso automaticamente pela impressora.</li>
                    </ol>

                    <h4>Remover um item do carrinho</h4>
                    <p>Clique no 🗑️ ao lado do item antes de fechar a venda. Se a venda já foi <strong>finalizada</strong> e precisa cancelar um item, é necessário que o gerente autorize com a senha dele.</p>

                    <h4>Sangria (retirada de dinheiro) e Suprimento (colocar dinheiro)</h4>
                    <ol>
                        <li>Pressione <span class="atalho">F4</span> ou acesse a tela de sangria.</li>
                        <li>Escolha: <strong>Sangria</strong> (tirar dinheiro da gaveta, ex.: para depositar no banco) ou <strong>Suprimento</strong> (colocar mais dinheiro para troco).</li>
                        <li>Informe o valor e o <strong>motivo obrigatório</strong> (ex.: "Depósito bancário às 14h — autorizado pelo gerente João").</li>
                        <li>O sistema imprime o comprovante automaticamente.</li>
                    </ol>
                    <div class="alerta">Toda sangria fica registrada e aparece no histórico de segurança. Nunca faça sangria sem registrar no sistema.</div>

                    <h4>E se a internet cair no meio do turno?</h4>
                    <p>O sistema detecta a queda e avisa na tela — mas <strong>você continua vendendo normalmente</strong>. Todas as vendas ficam salvas no próprio computador. Quando a internet voltar, o sistema envia tudo automaticamente para o servidor em até 30 segundos, sem precisar fazer nada.</p>
                    <div class="dica">O preço registrado no cupom durante a queda de internet não muda — mesmo que o gerente tenha alterado o preço enquanto a internet estava fora.</div>

                    <h4>Fechar o caixa no fim do turno</h4>
                    <ol>
                        <li>No sistema de vendas, clique em <strong>Fechar Caixa</strong>.</li>
                        <li>Confira o resumo: total de vendas, sangrias realizadas e saldo final estimado na gaveta.</li>
                        <li>Confirme o fechamento. O relatório do turno é impresso automaticamente.</li>
                    </ol>

                    <h4>Sobre a impressora e equipamentos</h4>
                    <p>A impressora de cupom funciona através de um programa auxiliar chamado <strong>QZ Tray</strong> (um pequeno programa instalado no computador da loja que faz a ponte entre o site e a impressora). Ele precisa estar aberto e ativo para imprimir — você verá o ícone dele na barra de tarefas do Windows.</p>
                    <p>Use sempre <strong>Google Chrome ou Microsoft Edge</strong> para operar o caixa — outros navegadores como Firefox ou Safari não são compatíveis com a balança e o leitor de câmera.</p>

                </div>
            </div>
        </div>
        <?php endif; // fim seção caixa ?>

        <!-- ═══════════════════════════════════════════════════════════ ENTREGADOR -->
        <?php if (in_array('entregador', $_secoes_visiveis, true)): $_a = _manualAttrs('entregador', $_primeira_secao); ?>
        <div class="accordion-item" id="entregador">
            <h2 class="accordion-header">
                <button class="accordion-button <?= $_a['btn'] ?>" type="button" data-bs-toggle="collapse" data-bs-target="#colEntregador" aria-expanded="<?= $_a['exp'] ?>">
                    🛵 &nbsp;Entregador
                    <span class="perfil-badge ms-2" style="background:#f3e5f5;color:#4a148c;">Logística de entrega</span>
                </button>
            </h2>
            <div id="colEntregador" class="accordion-collapse collapse <?= $_a['col'] ?>" data-bs-parent="#manualAccordion">
                <div class="accordion-body manual-section">

                    <h4>Como é o fluxo da sua entrega</h4>

                    <!-- Fluxo visual da entrega -->
                    <div class="fluxo">
                        <div class="fluxo-passo">
                            <div class="fp-ico">📋</div>
                            <div class="fp-txt">Ver pedidos no painel</div>
                        </div>
                        <div class="fluxo-seta">→</div>
                        <div class="fluxo-passo">
                            <div class="fp-ico">🗺️</div>
                            <div class="fp-txt">Abrir rota no Maps</div>
                        </div>
                        <div class="fluxo-seta">→</div>
                        <div class="fluxo-passo">
                            <div class="fp-ico">🛵</div>
                            <div class="fp-txt">Entregar ao cliente</div>
                        </div>
                        <div class="fluxo-seta">→</div>
                        <div class="fluxo-passo">
                            <div class="fp-ico">✅</div>
                            <div class="fp-txt">Confirmar no sistema</div>
                        </div>
                    </div>

                    <h4>Acessar o painel de entregas</h4>
                    <ol>
                        <li>Faça login em <strong><?= BASE_PATH ?>/login</strong> com seu e-mail e senha.</li>
                        <li>O sistema abre direto na lista de pedidos <strong>Em Rota</strong> — somente os pedidos designados para você.</li>
                    </ol>

                    <h4>Ver o endereço e abrir a rota</h4>
                    <p>Em cada cartão de pedido você encontra o endereço completo do cliente e dois botões de navegação:</p>
                    <ul>
                        <li>📍 <strong>Google Maps</strong> — abre o endereço no Google Maps já pronto para a navegação GPS.</li>
                        <li>🗺 <strong>Waze</strong> — abre o endereço no Waze, se preferir.</li>
                    </ul>
                    <div class="dica">O endereço já está formatado para busca — clique e o aplicativo de navegação abre direto na rota.</div>

                    <h4>Verificar a forma de pagamento antes de sair</h4>
                    <p>O cartão do pedido mostra como o cliente vai pagar: dinheiro (com o troco necessário indicado), cartão de débito/crédito ou Pix. Verifique antes de sair para não ter surpresa na entrega.</p>

                    <h4>Confirmar a entrega</h4>
                    <ol>
                        <li>Após entregar e receber o pagamento, clique em <strong>✅ Entreguei</strong> no cartão do pedido.</li>
                        <li>O pedido sai da sua lista e os pontos de fidelidade são creditados automaticamente para o cliente.</li>
                    </ol>
                    <div class="alerta">Clique em "Entreguei" somente após a entrega física e recebimento do pagamento. Não confirme antes disso.</div>

                    <h4>Avisar o cliente que o pedido saiu</h4>
                    <p>No cartão do pedido, o botão <strong>📲 Saiu para Entrega</strong> abre o WhatsApp do cliente com uma mensagem automática já escrita — basta clicar em Enviar. Sem custo, sem precisar digitar nada.</p>

                    <h4>O que fazer se houver problema na entrega</h4>
                    <p>Se o cliente não estiver em casa ou houver qualquer imprevisto, ligue ou mande mensagem para o gerente antes de voltar para a loja. <strong>Não marque como entregue</strong> se a entrega não foi concluída.</p>

                </div>
            </div>
        </div>
        <?php endif; // fim seção entregador ?>

        <!-- ═══════════════════════════════════════════════════════════════════ FAQ -->
        <?php if (in_array('faq', $_secoes_visiveis, true)): $_a = _manualAttrs('faq', $_primeira_secao); ?>
        <div class="accordion-item" id="faq">
            <h2 class="accordion-header">
                <button class="accordion-button <?= $_a['btn'] ?>" type="button" data-bs-toggle="collapse" data-bs-target="#colFAQ" aria-expanded="<?= $_a['exp'] ?>">
                    ❓ &nbsp;Dúvidas frequentes (FAQ)
                    <span class="perfil-badge ms-2" style="background:#f5f5f5;color:#424242;">Todos os cargos</span>
                </button>
            </h2>
            <div id="colFAQ" class="accordion-collapse collapse <?= $_a['col'] ?>" data-bs-parent="#manualAccordion">
                <div class="accordion-body manual-section">

                    <h4>A impressora não está imprimindo. O que faço?</h4>
                    <ol>
                        <li>Verifique se o programa auxiliar <strong>QZ Tray</strong> (ícone na barra de tarefas do Windows, perto do relógio) está aberto e ativo. Se não estiver, abra ele.</li>
                        <li>Acesse Painel → <strong>Hardware → Impressora</strong> e clique em <strong>Testar Impressão</strong>.</li>
                        <li>Confira se a impressora está ligada, com papel e sem nenhum aviso de erro piscando nela.</li>
                        <li>Se nada funcionar, reinicie o programa: clique com o botão direito no ícone do QZ Tray → Reiniciar.</li>
                        <li>Use sempre Google Chrome ou Microsoft Edge — a impressora não funciona com outros navegadores.</li>
                    </ol>

                    <h4>Como cancelo um produto já colocado no carrinho?</h4>
                    <p>No sistema de vendas, clique no ícone de lixeira 🗑️ ao lado do produto enquanto a venda ainda está aberta. Se a venda já foi <strong>finalizada e impressa</strong>, é preciso que o gerente autorize o cancelamento com a senha dele.</p>

                    <h4>O estoque ficou com número negativo. O que isso quer dizer?</h4>
                    <p>Acontece quando o caixa vendeu sem internet e, ao sincronizar, o produto já havia sido vendido por outro caixa também. O sistema registra a venda normalmente e marca o estoque como negativo para que o gerente possa investigar e ajustar. O gerente corrige em <strong>Estoque → Ajuste de Quantidade</strong>.</p>

                    <h4>O cliente quer cancelar o pedido de entrega. O que fazer?</h4>
                    <ul>
                        <li>Se o pedido ainda está como <strong>"Pendente"</strong> ou <strong>"Aguardando Validação pelo WhatsApp"</strong>: o gerente consegue cancelar diretamente no painel, na tela de pedidos.</li>
                        <li>Se o pedido já está <strong>"Em Preparo"</strong> ou em etapas posteriores: o gerente avalia o caso e entra em contato com o cliente para combinar.</li>
                        <li>Os pontos de fidelidade usados no pedido cancelado são devolvidos automaticamente.</li>
                    </ul>

                    <h4>Esqueci a senha. Como faço para recuperar?</h4>
                    <p>Para <strong>clientes</strong>: na tela de login, clique em "Esqueci minha senha". Para <strong>funcionários</strong>: peça ao dono ou ao responsável técnico (desenvolvedor) para gerar uma senha temporária — ele faz isso pelo painel de sistema sem ver a senha atual.</p>

                    <h4>A internet caiu no meio da venda. Perco os dados?</h4>
                    <p>Não. O sistema continua funcionando normalmente com os dados que já foram baixados na abertura do caixa. As vendas ficam salvas no próprio computador. Quando a internet voltar, tudo é enviado automaticamente para o servidor em até 30 segundos, sem precisar fazer nada.</p>

                    <h4>O cliente informou o CPF errado e os pontos não foram para a conta certa. O que faço?</h4>
                    <p>Não é possível transferir pontos entre contas pela interface normal. Para corrigir, entre em contato com o dono ou responsável técnico, informando o nome dos dois clientes e a quantidade de pontos a transferir. A correção é feita manualmente no banco de dados.</p>

                    <h4>A balança não está lendo o peso. O que faço?</h4>
                    <ol>
                        <li>Acesse Painel → <strong>Hardware → Balança</strong> e clique em <strong>Testar Leitura</strong>.</li>
                        <li>Verifique se o cabo que liga a balança ao computador (via adaptador USB) está bem conectado nos dois lados.</li>
                        <li>Se aparecer uma lista de portas para escolher, tente as opções uma a uma até o peso aparecer.</li>
                        <li>Use sempre Chrome ou Edge — a leitura da balança não funciona em outros navegadores.</li>
                    </ol>

                    <h4>Como instalar o site no celular como um aplicativo?</h4>
                    <p>Abra o site no Google Chrome do celular → toque nos 3 pontinhos (⋮) no canto superior direito → <strong>Adicionar à tela inicial</strong>. O site vira um atalho que abre como um app, sem barra de endereço.</p>

                </div>
            </div>
        </div>
        <?php endif; // fim seção faq ?>

    </div><!-- /#manualAccordion -->

    <div class="manual-versao">
        Manual v1.1 &middot; <?= htmlspecialchars(NOME_SISTEMA) ?> &middot; Atualizado em 24/06/2026<br>
        Para atualizar este manual, edite <code>views/manual/index.php</code>
    </div>

</div>

<?php require_once __DIR__ . '/../../app/views/layout/footer.php'; ?>
