<?php
/**
 * Desffrut — Termos de Uso
 * Versão 1.0 — regras do portal, pedidos, fidelidade e responsabilidades.
 */
require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/functions.php';
iniciar_sessao();

$titulo_pagina  = 'Termos de Uso';
$og_description = 'Termos e condições de uso do portal ' . NOME_SISTEMA . ': regras de pedido, entrega, cancelamento e programa de fidelidade.';
$canonical_url  = BASE_URL . BASE_PATH . '/termos';
$robots         = 'noindex,follow';
$mostrar_sacola = false;
require_once __DIR__ . '/app/views/layout/header.php';
?>

<style>
.legal-hero {
    background: linear-gradient(135deg,#1b5e20 0%,#2e7d32 60%,#43a047 100%);
    color:#fff; padding:48px 20px 36px; text-align:center;
}
.legal-hero h1 { font-size:1.8rem; font-weight:800; margin:0 0 8px; }
.legal-hero p  { opacity:.85; margin:0; font-size:.95rem; }
.legal-body    { max-width:780px; margin:0 auto; padding:40px 20px 60px; }
.legal-body h2 { font-size:1.1rem; font-weight:700; color:#1b5e20;
                 border-left:4px solid var(--cor-primaria,#2e7d32);
                 padding-left:10px; margin:32px 0 10px; }
.legal-body h3 { font-size:.95rem; font-weight:700; margin:20px 0 6px; color:#333; }
.legal-body p, .legal-body li { font-size:.92rem; color:#444; line-height:1.7; }
.legal-body ul { padding-left:1.4rem; }
.legal-nav     { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:32px; }
.legal-nav a   { font-size:.8rem; background:#e8f5e9; color:#1b5e20;
                 border-radius:20px; padding:4px 12px; text-decoration:none; }
.legal-nav a:hover { background:#c8e6c9; }
.destaque-box  { background:#fff8e1; border:1px solid #ffe082; border-radius:8px;
                 padding:12px 16px; margin:16px 0; font-size:.88rem; color:#555; }
</style>

<div class="legal-hero">
    <h1>📋 Termos de Uso</h1>
    <p><?= htmlspecialchars(NOME_SISTEMA) ?> &mdash; Versão 1.0 &middot; Vigente a partir de 24/06/2026</p>
</div>

<div class="legal-body">

    <nav class="legal-nav" aria-label="Seções dos termos">
        <a href="#aceitacao">Aceitação</a>
        <a href="#cadastro">Cadastro</a>
        <a href="#pedidos">Pedidos</a>
        <a href="#entrega">Entrega</a>
        <a href="#cancelamento">Cancelamento</a>
        <a href="#pereciveis">Perecíveis</a>
        <a href="#pagamento">Pagamento</a>
        <a href="#fidelidade">Fidelidade</a>
        <a href="#indicacao">Programa de Indicação</a>
        <a href="#proibicoes">Proibições</a>
        <a href="#responsabilidade">Responsabilidade</a>
        <a href="#alteracoes">Alterações</a>
        <a href="#foro">Foro</a>
    </nav>

    <h2 id="aceitacao">1. Aceitação dos Termos</h2>
    <p>Ao criar uma conta ou utilizar o portal <strong><?= htmlspecialchars(NOME_SISTEMA) ?></strong>, você declara ter lido, compreendido e concordado com estes Termos de Uso e com a nossa <a href="<?= BASE_PATH ?>/privacidade">Política de Privacidade</a>. Caso não concorde com qualquer disposição, não utilize o portal.</p>
    <p>O uso continuado do portal após alterações publicadas nesta página constitui aceite das novas condições.</p>

    <h2 id="cadastro">2. Cadastro e Conta</h2>
    <ul>
        <li>O cadastro é gratuito e destinado a pessoas físicas maiores de 18 anos ou menores devidamente representados por responsável legal.</li>
        <li>Você é responsável pela veracidade das informações fornecidas. CPF inválido ou pertencente a terceiro resultará em suspensão da conta.</li>
        <li>A senha é pessoal e intransferível. Não compartilhe sua senha. Comunicar imediatamente qualquer acesso não autorizado pelo e-mail de suporte.</li>
        <li>Uma mesma pessoa pode ter apenas um cadastro. Contas duplicadas poderão ser mescladas ou removidas sem aviso prévio.</li>
    </ul>

    <h2 id="pedidos">3. Realização de Pedidos</h2>
    <ul>
        <li>Pedidos online estão sujeitos à disponibilidade de estoque da filial selecionada no momento da confirmação.</li>
        <li>Os preços exibidos no catálogo são os vigentes no momento da consulta e podem mudar. O preço que prevalece é o confirmado no momento do fechamento do pedido.</li>
        <li>Itens vendidos por peso (kg) têm o valor calculado com base no peso real separado. A quantidade solicitada é uma estimativa — o valor final pode variar em até 10% para mais ou para menos.</li>
        <li>Pedidos realizados via WhatsApp ficam no status <em>Aguardando Validação</em> até que a loja confirme o recebimento pelo link enviado na mensagem.</li>
        <li>A loja se reserva o direito de recusar ou cancelar pedidos em casos de suspeita de fraude, endereço fora da área de entrega ou indisponibilidade de estoque após a confirmação.</li>
    </ul>

    <h2 id="entrega">4. Entrega</h2>
    <ul>
        <li>O prazo de entrega estimado é informado no momento do pedido e depende da localização, do volume de pedidos e do horário de funcionamento da filial.</li>
        <li>A entrega é realizada no endereço informado no pedido. O cliente deve garantir que alguém esteja disponível para receber. Após duas tentativas sem sucesso, o pedido poderá ser cancelado sem restituição de frete.</li>
        <li>A área de cobertura da entrega é definida por cada filial e pode variar. Endereços fora da área de cobertura não serão atendidos.</li>
        <li>O acompanhamento do status da entrega está disponível em <strong>Meu Perfil → Meus Pedidos</strong>, com atualização a cada 20 segundos.</li>
    </ul>

    <h2 id="cancelamento">5. Cancelamento e Devolução</h2>

    <div class="destaque-box">
        <strong>Regra principal:</strong> o cancelamento de pedido online é aceito somente enquanto o status for <em>Pendente</em> ou <em>Aguardando Validação</em>. Após o status mudar para <em>Em Preparo</em>, o cancelamento fica sujeito à análise da loja.
    </div>

    <ul>
        <li>Para cancelar, entre em contato com a filial diretamente pelo WhatsApp indicado na página <a href="<?= BASE_PATH ?>/lojas">Nossas Lojas</a>.</li>
        <li>Pedidos já separados ou em rota de entrega não são passíveis de cancelamento sem custo.</li>
        <li>Em caso de produto errado ou com defeito visível, o cliente deve recusar o item no momento da entrega e comunicar imediatamente a filial para substituição ou crédito de pontos equivalente.</li>
        <li>Não realizamos reembolso em dinheiro para pedidos pagos com pontos de fidelidade.</li>
    </ul>

    <h2 id="pereciveis">6. Natureza Perecível dos Produtos</h2>
    <p>Frutas, verduras e legumes são produtos de alta perecibilidade. O <?= htmlspecialchars(NOME_SISTEMA) ?> não se responsabiliza por:</p>
    <ul>
        <li>Deterioração ocorrida após a confirmação de entrega ao destinatário</li>
        <li>Perda de qualidade por armazenamento inadequado por parte do cliente</li>
        <li>Variações naturais de aparência, tamanho ou sabor inerentes a produtos in natura</li>
    </ul>
    <p>Reclamações sobre qualidade devem ser feitas em até <strong>24 horas</strong> após a entrega, com foto do produto, pelo WhatsApp da filial correspondente.</p>

    <h2 id="pagamento">7. Formas de Pagamento</h2>
    <ul>
        <li><strong>Entrega:</strong> Dinheiro, Cartão de Débito, Cartão de Crédito ou Pix (confirmado manualmente pelo operador no recebimento).</li>
        <li><strong>Pontos de Fidelidade:</strong> podem ser utilizados como desconto parcial ou total no pedido.</li>
        <li>Vale-Alimentação e TEF não estão disponíveis nesta versão do portal.</li>
        <li>O troco, quando solicitado, é preparado de acordo com o valor informado no pedido. O cliente deve informar o valor correto para troco.</li>
    </ul>

    <h2 id="fidelidade">8. Programa de Fidelidade</h2>
    <ul>
        <li><strong>Acúmulo:</strong> R$ 1,00 gasto em qualquer compra (online ou presencial com CPF informado) = 1 ponto creditado ao perfil do cliente.</li>
        <li><strong>Resgate:</strong> 100 pontos = R$ 1,00 de desconto aplicável no próximo pedido.</li>
        <li>Pontos são creditados após a confirmação de entrega do pedido. Cancelamentos estornam os pontos correspondentes.</li>
        <li>Pontos não têm valor monetário, não são transferíveis entre contas e não podem ser convertidos em dinheiro.</li>
        <li>O <?= htmlspecialchars(NOME_SISTEMA) ?> reserva-se o direito de alterar as regras de acúmulo e resgate com aviso prévio de 30 dias.</li>
        <li>Contas inativas por mais de 24 meses podem ter os pontos expirados sem aviso prévio.</li>
    </ul>

    <h2 id="indicacao">9. Programa de Indicação ("Indique e Ganhe")</h2>
    <ul>
        <li>Cada cliente recebe um código de indicação único disponível em seu perfil.</li>
        <li>Quando um novo cliente se cadastra e realiza o primeiro pedido usando o link de indicação, o indicador recebe bônus em pontos conforme tabela vigente.</li>
        <li>O bônus é creditado automaticamente apenas após a confirmação da <strong>entrega</strong> do primeiro pedido do indicado.</li>
        <li>Tentativas de auto-indicação ou indicação fraudulenta resultarão no cancelamento do bônus e poderão resultar em suspensão da conta.</li>
        <li>O <?= htmlspecialchars(NOME_SISTEMA) ?> pode encerrar o programa de indicação a qualquer momento, com aviso prévio.</li>
    </ul>

    <h2 id="proibicoes">10. Uso Proibido</h2>
    <p>É proibido utilizar o portal para:</p>
    <ul>
        <li>Fornecer dados falsos, fraudar o programa de fidelidade ou criar contas falsas</li>
        <li>Realizar pedidos com intenção de não efetuar o pagamento</li>
        <li>Tentar acessar áreas restritas ou APIs internas sem autorização</li>
        <li>Enviar conteúdo ofensivo, difamatório ou ilegal por qualquer canal do sistema</li>
        <li>Revender produtos adquiridos pelo portal sem autorização expressa</li>
    </ul>

    <h2 id="responsabilidade">11. Limitação de Responsabilidade</h2>
    <p>O <?= htmlspecialchars(NOME_SISTEMA) ?> não se responsabiliza por danos decorrentes de:</p>
    <ul>
        <li>Falhas de internet ou indisponibilidade temporária do portal</li>
        <li>Atrasos causados por condições climáticas, trânsito ou força maior</li>
        <li>Uso indevido da conta pelo próprio cliente ou por terceiros com acesso autorizado</li>
        <li>Variações de preço ocorridas entre a adição ao carrinho e a finalização do pedido</li>
    </ul>

    <h2 id="alteracoes">12. Alterações nestes Termos</h2>
    <p>Podemos atualizar estes Termos de Uso a qualquer momento. A versão vigente estará sempre disponível nesta página. Alterações relevantes serão comunicadas por e-mail com antecedência mínima de 10 dias.</p>

    <h2 id="foro">13. Foro e Lei Aplicável</h2>
    <p>Estes Termos são regidos pela legislação brasileira. Fica eleito o foro da comarca da sede do <?= htmlspecialchars(NOME_SISTEMA) ?> para dirimir quaisquer controvérsias decorrentes deste contrato, com renúncia a qualquer outro, por mais privilegiado que seja.</p>

    <p class="mt-4">
        <a href="<?= BASE_PATH ?>/" class="btn btn-outline-success btn-sm">← Voltar ao catálogo</a>
        &nbsp;
        <a href="<?= BASE_PATH ?>/privacidade" class="btn btn-outline-secondary btn-sm">Ver Política de Privacidade</a>
    </p>

</div>

<?php require_once __DIR__ . '/app/views/layout/footer.php'; ?>
