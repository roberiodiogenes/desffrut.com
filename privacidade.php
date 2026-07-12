<?php
/**
 * Desffrut — Política de Privacidade (LGPD — Lei 13.709/2018)
 * Versão 2.0 — específica para operação de hortifruti.
 */
require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/functions.php';
iniciar_sessao();

$titulo_pagina  = 'Política de Privacidade';
$og_description = 'Saiba como o ' . NOME_SISTEMA . ' coleta, usa e protege seus dados pessoais, em conformidade com a LGPD (Lei 13.709/2018).';
$canonical_url  = BASE_URL . BASE_PATH . '/privacidade';
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
.destaque-box strong { color:#e65100; }
</style>

<div class="legal-hero">
    <h1>🔒 Política de Privacidade</h1>
    <p><?= htmlspecialchars(NOME_SISTEMA) ?> &mdash; Versão 2.0 &middot; Vigente a partir de 24/06/2026 &middot; LGPD Lei 13.709/2018</p>
</div>

<div class="legal-body">

    <!-- Índice de navegação -->
    <nav class="legal-nav" aria-label="Seções desta política">
        <a href="#quem-somos">Quem somos</a>
        <a href="#dados-coletados">Dados coletados</a>
        <a href="#dados-hortifruti">Especificidades hortifruti</a>
        <a href="#finalidades">Finalidades</a>
        <a href="#rastreamento">Rastreamento e cookies</a>
        <a href="#funcionarios">Dados de funcionários</a>
        <a href="#compartilhamento">Compartilhamento</a>
        <a href="#retencao">Retenção</a>
        <a href="#seus-direitos">Seus direitos</a>
        <a href="#seguranca">Segurança</a>
        <a href="#menores">Menores de idade</a>
        <a href="#contato">Contato</a>
    </nav>

    <h2 id="quem-somos">1. Quem somos e o que fazemos</h2>
    <p>O <strong><?= htmlspecialchars(NOME_SISTEMA) ?></strong> é uma plataforma digital de comércio varejista de hortifruti (frutas, verduras e legumes) que permite a compra online com entrega, o acompanhamento de pedidos e a participação em programa de fidelidade. Operamos uma rede de filiais físicas e disponibilizamos este portal para clientes e colaboradores.</p>
    <p>Somos o <strong>controlador</strong> dos dados pessoais coletados nesta plataforma, conforme definido pela Lei Geral de Proteção de Dados (LGPD — Lei nº 13.709/2018).</p>

    <h2 id="dados-coletados">2. Dados pessoais que coletamos</h2>

    <h3>2.1 Dados fornecidos pelo cliente no cadastro</h3>
    <ul>
        <li>Nome completo</li>
        <li>CPF (Cadastro de Pessoa Física) — usado para vincular compras presenciais e online ao mesmo perfil de fidelidade</li>
        <li>Endereço de e-mail</li>
        <li>Número de telefone / WhatsApp</li>
        <li>Endereço de entrega (rua, número, complemento, bairro, cidade, CEP)</li>
        <li>Senha de acesso (armazenada com hash bcrypt — nunca em texto simples)</li>
    </ul>

    <h3>2.2 Dados gerados pelo uso da plataforma</h3>
    <ul>
        <li>Histórico de pedidos online e compras presenciais vinculadas ao CPF</li>
        <li>Saldo e extrato do programa de pontos de fidelidade</li>
        <li>Código de indicação e vínculos de indicação (programa "Indique e Ganhe")</li>
        <li>Preferências de filial e método de pagamento mais utilizado</li>
        <li>Timestamps de acesso, IPs de login e ações realizadas na plataforma (log de auditoria)</li>
    </ul>

    <h2 id="dados-hortifruti">3. Especificidades da operação de hortifruti</h2>

    <div class="destaque-box">
        <strong>Atenção:</strong> por se tratar de uma operação de venda de alimentos, o sistema registra informações que podem, de forma indireta, refletir hábitos alimentares. Explicamos como tratamos esses dados abaixo.
    </div>

    <h3>3.1 Dados de peso (itens vendidos por quilograma)</h3>
    <p>Produtos comercializados por peso (kg) têm a quantidade em gramas ou quilogramas registrada em cada item de pedido. Esses dados são utilizados exclusivamente para fins comerciais (cálculo de preço, controle de estoque, emissão de comprovante) e não são compartilhados com terceiros.</p>

    <h3>3.2 Histórico de compras de alimentos</h3>
    <p>O histórico de compras (quais produtos foram adquiridos, com que frequência e em que quantidade) é armazenado vinculado ao CPF do cliente. Embora o sistema não crie perfis de saúde ou hábitos alimentares intencionalmente, reconhecemos que esses dados podem refletir preferências alimentares. Tratamos esse histórico com a mesma cautela dispensada a dados potencialmente sensíveis: acesso interno restrito, sem compartilhamento comercial e sujeito ao direito de exclusão pelo titular.</p>

    <h3>3.3 Compras presenciais vinculadas ao CPF</h3>
    <p>Quando o cliente informa o CPF na compra presencial no balcão físico, a transação é vinculada ao perfil digital para acúmulo de pontos. O CPF é coletado com a ciência do cliente e utilizado exclusivamente para o programa de fidelidade.</p>

    <h2 id="finalidades">4. Para que usamos seus dados</h2>
    <ul>
        <li>Processar e entregar pedidos realizados pelo portal</li>
        <li>Calcular, creditar e resgatar pontos de fidelidade</li>
        <li>Comunicar o status do pedido (polling na tela e notificações via WhatsApp, se consentido)</li>
        <li>Preencher automaticamente endereços em novos pedidos</li>
        <li>Enviar comunicações promocionais (somente com consentimento explícito marcado no cadastro)</li>
        <li>Cumprir obrigações legais, fiscais e contábeis</li>
        <li>Prevenir fraudes e garantir a segurança da plataforma</li>
        <li>Medir a eficácia de campanhas de marketing (de forma agregada e anonimizada)</li>
    </ul>

    <p><strong>Base legal (LGPD):</strong> execução de contrato (art. 7º, V), cumprimento de obrigação legal (art. 7º, II), legítimo interesse (art. 7º, IX) e consentimento (art. 7º, I) para comunicações promocionais.</p>

    <h2 id="rastreamento">5. Rastreamento, cookies e ferramentas de análise</h2>

    <h3>5.1 Cookies de sessão</h3>
    <p>Utilizamos um cookie de sessão (<code>desffrut_sess</code>) para manter o login durante a navegação. Ele é excluído ao fechar o navegador ou após 8 horas de inatividade. Não é possível desativá-lo sem perder a autenticação.</p>

    <h3>5.2 localStorage (sacola de compras)</h3>
    <p>O conteúdo da sacola é salvo no <code>localStorage</code> do navegador para que não seja perdido ao navegar entre páginas. Nenhum dado pessoal é armazenado nesse espaço.</p>

    <h3>5.3 Meta Pixel (Facebook/Instagram)</h3>
    <p>Quando configurado pelo administrador, o portal inclui o Meta Pixel para mensuração de campanhas de anúncios. O Pixel registra eventos de navegação (Visualizar Produto, Adicionar à Sacola, Iniciar Checkout, Compra Concluída). Os dados são tratados conforme a <a href="https://www.facebook.com/privacy/policy/" target="_blank" rel="noopener">Política de Privacidade do Meta</a>.</p>

    <h3>5.4 Google Analytics 4 (GA4)</h3>
    <p>Quando configurado, o Google Analytics 4 coleta dados anonimizados de uso (páginas visitadas, tempo de sessão, origem do tráfego via parâmetros UTM). Os dados são tratados conforme a <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Política de Privacidade do Google</a>.</p>

    <h3>5.5 Parâmetros UTM</h3>
    <p>Parâmetros <code>utm_source</code>, <code>utm_medium</code> e <code>utm_campaign</code> presentes em links de anúncios são capturados e associados ao pedido para cálculo de retorno sobre investimento em marketing. Esses dados são usados apenas internamente.</p>

    <h2 id="funcionarios">6. Dados de funcionários e colaboradores</h2>
    <p>O sistema armazena dados trabalhistas dos colaboradores (nome, CPF, cargo, salário, registros de ponto, folha de pagamento) para gestão interna de RH e financeiro. Esses dados são:</p>
    <ul>
        <li>Acessíveis apenas pelos perfis <strong>rh_financeiro</strong> e <strong>super_admin</strong></li>
        <li>Registrados na trilha de auditoria do sistema (todo acesso e alteração é logado)</li>
        <li>Armazenados pelo prazo mínimo exigido pela CLT e legislação trabalhista brasileira</li>
        <li>Não compartilhados com terceiros, exceto por obrigação legal (eSocial, FGTS, INSS)</li>
    </ul>

    <h2 id="compartilhamento">7. Compartilhamento de dados</h2>
    <p>Não vendemos, alugamos nem compartilhamos dados pessoais com terceiros para fins comerciais. O compartilhamento ocorre apenas nos seguintes casos:</p>
    <ul>
        <li><strong>Obrigação legal:</strong> autoridades fiscais, trabalhistas ou judiciais mediante requisição formal</li>
        <li><strong>Infraestrutura:</strong> servidor de hospedagem (HostGator Brasil) que processa os dados em território nacional</li>
        <li><strong>Ferramentas de análise:</strong> Meta e Google, conforme descrito na Seção 5 (dados de navegação anonimizados)</li>
    </ul>

    <h2 id="retencao">8. Por quanto tempo mantemos seus dados</h2>
    <ul>
        <li><strong>Dados de cadastro e pedidos:</strong> enquanto a conta estiver ativa e por até 5 anos após o encerramento (obrigação fiscal)</li>
        <li><strong>Logs de auditoria:</strong> mínimo de 5 anos (imutáveis por design)</li>
        <li><strong>Dados de marketing (UTMs, pixels):</strong> 2 anos</li>
        <li><strong>Dados trabalhistas:</strong> prazo definido pela legislação vigente (mínimo 5 anos para documentos fiscais)</li>
        <li><strong>Após solicitação de exclusão:</strong> anonimização em até 15 dias; registros financeiros agregados são mantidos sem identificação pessoal</li>
    </ul>

    <h2 id="seus-direitos">9. Seus direitos como titular (LGPD)</h2>
    <p>Você tem direito a:</p>
    <ul>
        <li><strong>Acesso:</strong> solicitar uma cópia dos dados que temos sobre você</li>
        <li><strong>Correção:</strong> atualizar dados incompletos ou desatualizados diretamente em <a href="<?= BASE_PATH ?>/meu-perfil">Meu Perfil</a></li>
        <li><strong>Exclusão:</strong> solicitar a exclusão da conta em <strong>Meu Perfil → Excluir minha conta</strong>. Os dados pessoais são anonimizados em até 15 dias. Registros financeiros agregados são mantidos conforme obrigação legal.</li>
        <li><strong>Portabilidade:</strong> solicitar seus dados em formato estruturado pelo e-mail de contato</li>
        <li><strong>Revogação de consentimento:</strong> cancelar recebimento de promoções a qualquer momento em Meu Perfil</li>
        <li><strong>Reclamação à ANPD:</strong> caso julgue que seus direitos foram violados, você pode acionar a Autoridade Nacional de Proteção de Dados (anpd.gov.br)</li>
    </ul>

    <h2 id="seguranca">10. Segurança dos dados</h2>
    <ul>
        <li>Senhas armazenadas com hash <strong>bcrypt</strong> — nunca em texto simples</li>
        <li>Comunicação via <strong>HTTPS</strong> em produção</li>
        <li>Acesso a dados administrativos restrito por perfil (role-based access control)</li>
        <li>Trilha de auditoria imutável para todas as ações críticas</li>
        <li>Backups diários criptografados no servidor</li>
        <li>Execução de PHP em uploads bloqueada por <code>.htaccess</code></li>
    </ul>

    <h2 id="menores">11. Menores de idade</h2>
    <p>O portal é destinado a maiores de 18 anos ou a menores acompanhados de responsável legal. Não coletamos dados de menores de forma intencional. Caso identifique um cadastro de menor, entre em contato para exclusão imediata.</p>

    <h2 id="contato">12. Contato e Encarregado de Dados (DPO)</h2>
    <p>Para exercer seus direitos ou tirar dúvidas sobre esta política, entre em contato:</p>
    <ul>
        <li><strong>E-mail:</strong> <a href="mailto:privacidade@desffrut.com.br">privacidade@desffrut.com.br</a></li>
        <li><strong>Prazo de resposta:</strong> até 15 dias úteis</li>
    </ul>

    <h2>13. Alterações nesta política</h2>
    <p>Esta política pode ser atualizada para refletir mudanças operacionais ou legais. A versão vigente sempre estará disponível nesta página, com a data de atualização no cabeçalho. Mudanças relevantes serão comunicadas por e-mail aos titulares cadastrados.</p>

    <p class="mt-4">
        <a href="<?= BASE_PATH ?>/" class="btn btn-outline-success btn-sm">← Voltar ao catálogo</a>
        &nbsp;
        <a href="<?= BASE_PATH ?>/termos" class="btn btn-outline-secondary btn-sm">Ver Termos de Uso</a>
    </p>

</div>

<?php require_once __DIR__ . '/app/views/layout/footer.php'; ?>
