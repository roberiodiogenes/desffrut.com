# 🏁 PLANO DE DESENVOLVIMENTO POR FASES: DESFFRUT

Este checklist serve para o controle do Product Owner (Você) sobre o progresso de desenvolvimento do Claude Code. Recomenda-se homologar e marcar uma fase como concluída antes de autorizar o início da próxima.

---

## ⚙️ FASE 0: Setup & Infraestrutura (Pré-requisito)
*Foco: Preparar o ambiente antes de escrever qualquer linha de código de negócio.*

* [ ] **0.1. Estrutura de Pastas:** Criar a hierarquia de diretórios do projeto no VS Code seguindo o padrão PHP/JS definido no briefing (pastas `public/`, `api/`, `app/`, `assets/`, etc.).
* [ ] **0.2. Arquivo `.htaccess`:** Configurar as rewrite rules do Apache para roteamento limpo de URLs, bloqueio de acesso direto a diretórios internos e redirecionamento forçado para HTTPS em produção.
* [ ] **0.3. Repositório GitHub + `.gitignore`:** Inicializar o repositório Git local, criar o `.gitignore` (excluindo arquivos de config local, `vendor/`, dumps de banco e credenciais) e vincular ao repositório remoto no GitHub.
* [ ] **0.4. Camada de API REST (`/api/`):** Criar o roteador base da API com autenticação por token de sessão (token gerado no login PHP, enviado no header `Authorization` das requisições JS) e estrutura padrão de resposta JSON (`status`, `data`, `message`).
* [ ] **0.5. Pipeline de Deploy (GitHub → HostGator):** Configurar o fluxo de publicação via FTP/SSH da HostGator (ou GitHub Actions), garantindo que o push na branch `main` reflita em produção de forma controlada.

---

## 🛠️ FASE 1: Fundação do Sistema, Banco de Dados & SSO
*Foco: Criar a estrutura de arquivos e o sistema que valida quem está logando e para onde vai.*

* [ ] **1.1. Arquitetura de Pastas:** Criar a estrutura de diretórios no VS Code para o padrão PHP/JS.
* [ ] **1.2. Modelagem Relacional MySQL:** Executar o script de criação das tabelas essenciais (`lojas`, `usuarios`, `produtos`, `clientes`) no XAMPP.
* [ ] **1.3. Tela de Login Unificada (SSO):** Criar a interface única de login na raiz do projeto.
* [ ] **1.4. Motor de Redirecionamento PHP:** Desenvolver a lógica de sessão (`$_SESSION`) que direciona cada `role` para sua respectiva pasta.
* [ ] **1.5. Middlewares de Segurança:** Implementar a trava de segurança no topo das páginas internas para impedir acessos não autorizados por URL.

---

## 🌐 FASE 2: Portal Público & Área do Cliente (Sem Barreiras)
*Foco: Colocar o site no ar para os clientes navegarem, escolherem produtos e consultarem a pontuação.*

* [ ] **2.1. Seletor de Filial Ativa:** Criar o componente que filtra preços e estoques de acordo com a loja escolhida pelo usuário.
* [ ] **2.2. Catálogo Digital Aberto:** Interface pública e responsiva mostrando os produtos por categoria (Frutas, Verduras, Legumes) e preços por Kg/Un.
* [ ] **2.3. Sacola de Compras de Visitante:** Lógica em JavaScript (Localstorage) para salvar o carrinho antes do login.
* [ ] **2.4. Trava de Finalização:** Formulário de interceptação exigindo login/cadastro apenas ao clicar em "Finalizar Pedido".
* [ ] **2.5. Painel 'Meu Perfil' do Cliente:** Área interna para acompanhar pontos de fidelidade acumulados e histórico de cupons/compras vinculados ao CPF.

---

## 📦 FASE 3: Retaguarda Comercial (Produtos, Promoções e Estoque)
*Foco: Dar poder ao Dono e Gerente para gerenciar o estoque, alterar preços e programar ofertas.*

* [ ] **3.1. CRUD Multi-loja de Produtos:** Painel de cadastro com foto, código de barras (EAN), preço de custo e preço de venda.
* [ ] **3.2. Mecanismo de Visibilidade:** Recurso de "Ocultar/Inativar" produto sem apagar o histórico financeiro dele.
* [ ] **3.3. Agendador de Promoções:** Regra que aplica o preço promocional automaticamente baseado no intervalo de data/hora configurado.
* [ ] **3.4. Gestão de Inventário e Quebras:** Controle de quantidade por loja com módulo para dar baixa em itens que estragaram (perdas de hortifruti).
* [ ] **3.5. Gerador de Pedido de Compra (PDF):** Relatório automático formatado para impressão com os itens que estão abaixo do estoque mínimo.

---

## 🛒 FASE 4: Frente de Caixa (PDV) Híbrido & Modo Offline
*Foco: Criar o caixa da loja física. Ele precisa ser ultra rápido e funcionar mesmo se a internet cair.*

* [ ] **4.1. Carga e Estrutura IndexedDB:** Script JS que baixa as tabelas da HostGator para o banco local do navegador ao abrir o dia.
* [ ] **4.2. Operações de Fluxo:** Telas e rotinas para Abertura de Caixa, Suprimento e Sangria Justificada.
* [ ] **4.3. Tela de Venda Rápida (Teclado):** Interface do operador baseada em atalhos de teclado (F1, F2, ESC), busca por código ou nome.
* [ ] **4.4. Mecanismo de Contingência Offline:** Detecção automática do status da rede. Se desconectar, salva as vendas localmente no `IndexedDB` sem exibir erro.
* [ ] **4.5. Sincronizador de Retorno (Background Sync):** Assim que a internet volta, o sistema faz o upload dos cupons pendentes e atualiza o MySQL na nuvem de forma transparente.

---

## 🔌 FASE 5: Integrações de Hardware Local ✅
*Foco: Conectar a aplicação web com os periféricos físicos do balcão da loja.*

* [x] **5.1. Integração com Balança Comercial:** *Web Serial API* para ler peso da balança Toledo/Filizola (RS-232, 9600 baud). Botão ⚖️ no modal de quantidade captura o peso automaticamente. Porta mantida aberta entre leituras; suporte a Toledo ST e GS.
* [x] **5.2. Formatação de Cupom ESC/POS:** Layout de impressão térmica em 80mm (48 colunas) e 58mm (32 colunas) via QZ Tray. Cabeçalho, itens, totais, formas de pagamento, troco, pontos de fidelidade e rodapé.
* [x] **5.3. Disparador de Impressão de Venda:** Impressão automática após `desffrut:venda:concluida`. Botão 🖨️ Reimprimir no painel lateral. Integração via QZ Tray (Chrome/Edge obrigatório).
* [x] **5.4. Impressão de Sangria/Fechamento:** Comprovante de sangria/suprimento e relatório de fechamento de caixa impressos automaticamente via `imprimirSangria()` e `imprimirFechamento()`.
* [x] **5.5. Leitor de Código de Barras / QR Code:** Dois modos:
  * **USB HID** (leitores de mesa/pistola): plug & play — emulam teclado, funcionam automaticamente no campo de busca do PDV, zero configuração.
  * **Câmera** (BarcodeDetector API, Chrome/Edge 83+): botão 📷 no campo de busca ou atalho **F5**. Detecta EAN-13, EAN-8, UPC-A, QR Code, Code-128 e outros. Beep de confirmação via Web Audio API.
* [x] **5.6. Tela de Configuração Hardware (Dashboard):** Fragmento `hardware.php` com status de todos os periféricos (dots verde/vermelho), configuração de impressora e papel, seleção de porta serial, teste de balança, teste de câmera e diagnóstico completo.

---

## 🛵 FASE 6: Tele-Entrega & Logística Integrada ✅
*Foco: Gerenciar o fluxo do pedido desde a separação até a casa do cliente.*

* [x] **6.1. Checkout de Pedido:** `views/checkout/index.php` — sacola resumida, seleção de filial, formulário de endereço (pré-preenchido do perfil), forma de pagamento na entrega (dinheiro/débito/crédito/pix), troco, observações, opção de salvar endereço.
* [x] **6.2. API Pedidos:** `api/v1/pedidos.php` — criação com validação de preço no servidor, listagem por status/role, detalhes, atualização de status com máquina de estado, crédito de pontos ao entregar.
* [x] **6.3. Rastreamento do Cliente (Polling):** `views/cliente/pedido_status.php` — timeline visual (Recebido → Preparando → Em Rota → Entregue), polling a cada 20s, sem WebSocket.
* [x] **6.4. Painel de Despacho no Dashboard:** `views/dashboard/fragmentos/pedidos.php` — tabs Pendentes / Em Preparo / Em Rota / Histórico, cards com todas as informações, modal de despacho com seleção de entregador, polling 20s automático.
* [x] **6.5. Painel do Entregador:** Mesma tela, role `entregador` vê apenas Em Rota. Botões 📍 Google Maps e 🗺 Waze com endereço pré-formatado. Botão "✅ Entreguei" confirma e credita pontos.
* [x] **6.6. Integração Sacola → Checkout:** `sacola.finalizar()` redireciona para `/checkout`; visitante vai para login com redirect pendente, ao logar como cliente vai direto para o checkout.

---

## 💼 FASE 7: ERP Administrativo (RH, Contas a Pagar e BI) ✅
*Foco: Controle financeiro, pagamento de funcionários, contas de consumo e lucratividade real.*

* [x] **7.1. Módulo RH Completo:** Cadastro de funcionários (cargo, tipo de contrato CLT/PJ/autônomo/estágio, carga horária, salário). Controle de ponto/jornada com registro manual de entrada, saída e intervalos. Banco de horas calculado por mês. Folha de pagamento mensal (horas extras, descontos, total líquido). Histórico de baixas (demissões).
* [x] **7.2. Lançamento de Contas a Pagar:** Registro de despesas por categoria (Aluguel, Água, Energia, Internet, Fornecedores, Folha, Outros). Suporte a lançamentos recorrentes. Marcar como pago, excluir pendentes. KPIs pendente/vencido/pago em tempo real. Auto-atualização de status vencido.
* [x] **7.3. Painel de BI e Lucro Líquido:** Visão geral (KPIs do mês). Gráficos Chart.js: barras empilhadas (PDV + Delivery por mês) e linha (Receita vs Despesas). DRE simplificado com Receita Bruta → CMV → Lucro Bruto → Despesas Operacionais → EBITDA → IR estimado → Lucro Líquido → Margem %. Top 10 produtos por receita com barra de progresso proporcional.
* [x] **7.4. Trilha de Auditoria:** Log completo paginado em `logs_auditoria`. Filtros por ação, tabela afetada e período. Destaque visual por criticidade (demissão, sangria, pagamento). Exclusivo para super_admin. Função `registrar_log()` integrada nas APIs Fase 7.

---

## 🎨 FASE 8: CMS & Customização do Portal
*Foco: Dar ao Super Admin controle visual e editorial do portal sem tocar em código.*

* [ ] **8.1. Tabela `configuracoes` (key-value):** Migration com chaves iniciais: `nome_sistema`, `slogan`, `logo_path`, `cor_primaria`, `cor_secundaria`. Valores lidos pelo PHP em `header.php` e injetados como CSS custom properties (`--cor-primaria`, etc.).
* [ ] **8.2. Gestor de Identidade Visual (fragmento `cms_identidade.php`):** Upload de logomarca (WebP, max 200 KB), campos de nome e slogan, seletor de cor com preview em tempo real. Salva via `api/v1/configuracoes.php`.
* [ ] **8.3. Migration `banners`:** Colunas: `id`, `imagem_path`, `link_destino`, `tipo` ENUM('desktop','mobile'), `ordem`, `ativo` TINYINT, `exibe_de`, `exibe_ate`, `criado_por`. Carrossel Bootstrap na home lê banners ativos pelo tipo e período.
* [ ] **8.4. Central de Banners (fragmento `cms_banners.php`):** Upload de banner, ativação/desativação toggle, reordenação por drag-and-drop (`Sortable.js`), separação Desktop / Mobile. Pré-visualização da imagem antes de salvar.
* [ ] **8.5. Painel de Detalhes das Lojas:** Migration adiciona `horario_funcionamento` VARCHAR e `whatsapp_link` VARCHAR na tabela `lojas`. Fragmento `cms_lojas.php` lista todas as filiais com campos editáveis inline. Dados exibidos no portal público em página de "Nossas Lojas".
* [ ] **8.6. Migration `campanhas` + Módulo de Campanhas Sazonais:** Colunas: `id`, `nome`, `tipo` ENUM('cupom_global','tema_css'), `valor_desconto` (para cupom_global), `classe_css` (para tema_css), `data_inicio`, `data_fim`, `ativo`. Middleware PHP verifica se há campanha ativa e aplica desconto ou injeta classe no `body`. Fragmento `cms_campanhas.php` para criar/ativar/desativar.
* [ ] **8.7. API `api/v1/configuracoes.php`:** GET /configuracoes (retorna todas as chaves), POST /configuracoes (salva/atualiza key-value), GET /campanhas, POST/PUT/PATCH/DELETE /campanhas. Role: `super_admin` obrigatório.
* [ ] **8.8. Integração no Dashboard:** Novo módulo "CMS & Portal" no sidebar (visível apenas para `super_admin`). Whitelist em `fragmento.php` atualizada. Abas: Identidade Visual, Banners, Lojas, Campanhas.

---

## 🔐 FASE 9: Dev Mode & Operações de Sistema
*Foco: Nível raiz para o desenvolvedor controlar infraestrutura e estado global do sistema.*

* [ ] **9.1. Role `dev_admin` e Rota Oculta:** Novo valor em `usuarios.role`. Rota `/dev` com middleware próprio que exige `dev_admin` — sem listagem no sidebar padrão. Acesso direto por URL apenas.
* [ ] **9.2. Modo Manutenção:** Flag `manutencao_ativa` na tabela `configuracoes`. Middleware em `app/middleware/auth_check.php` intercepta todas as requests de visitantes e clientes exibindo tela customizada (`views/manutencao.php`) enquanto o flag estiver ativo. Usuários operacionais continuam acessando normalmente.
* [ ] **9.3. Aviso de Inadimplência (Stand By Brando):** Flag `inadimplencia_ativa` na tabela `configuracoes`. Quando ativo, injeta banner fixo e estilizado no topo do dashboard para todos os roles operacionais (incluindo gerente e super_admin). A operação não é bloqueada — apenas o aviso é exibido até o dev_admin desativar.
* [ ] **9.4. Reset Forçado de Senha:** Painel no Dev Mode que lista todos os usuários. Botão "Resetar Senha" gera senha temporária aleatória (12 chars, alfanumérica), faz hash bcrypt, atualiza no banco e envia por e-mail ao titular via `mail()` PHP. Nenhuma senha é exibida em texto limpo. Registra log na auditoria.
* [ ] **9.5. Auditoria Forense Ampliada:** Migration adiciona coluna `ip_address` VARCHAR em `logs_auditoria`. Login de contas administrativas registra IP automaticamente. Novo fragmento `dev_auditoria.php` exibe logs com coluna de IP, filtro por IP suspeito e destaque para alterações de role. Tabela sem botão de exclusão neste painel — append-only by design.
* [ ] **9.6. Painel Dev Mode (view `views/dev/index.php`):** Dashboard exclusivo com: status dos flags de sistema (manutenção, inadimplência), contadores (usuários, lojas, pedidos, logs), atalhos para reset de senha, auditoria forense e chaves de configuração críticas.

---

## 📊 FASE 10: Prospecção & CRM Kanban (Custo Zero)
*Foco: Captação e gestão de leads B2B sem custo operacional.*

* [ ] **10.1. Migration `leads`:** Colunas: `id`, `nome`, `telefone` (UNIQUE), `email`, `bairro`, `interesse`, `fase` ENUM('novo','contato','proposta','negociacao','fechado','perdido') DEFAULT 'novo', `criado_em`, `atualizado_em`, `criado_por`. Índice em `telefone` para deduplicação no import.
* [ ] **10.2. Formulário de Parcerias Público (`/parcerias`):** Página institucional com formulário (nome, telefone, e-mail, bairro, interesse). Submit via AJAX → `api/v1/leads.php` POST. Sem autenticação — acesso público. Confirmação visual ao enviar.
* [ ] **10.3. API `api/v1/leads.php`:** GET /leads (lista com filtro por fase/bairro), POST /leads (captação pública e importação), PATCH /leads/{id} (mover de fase + observações), DELETE /leads/{id} (apenas admin). Role: `super_admin` para admin; POST público sem auth.
* [ ] **10.4. Pipeline CRM Kanban (fragmento `crm_kanban.php`):** 6 colunas com cartões arrastáveis via `Sortable.js` (CDN). Ao soltar um cartão em nova coluna, AJAX chama `PATCH /leads/{id}`. Cada card exibe nome, telefone, bairro e botão de WhatsApp. Filtro por bairro no topo.
* [ ] **10.5. Importador de Leads CSV Genérico:** Upload de arquivo `.csv` com qualquer origem (exportado de Google Sheets, Excel, etc.). Colunas mapeadas: `nome`, `telefone`, `bairro` (colunas extras ignoradas). PHP faz leitura linha a linha, ignora duplicatas por telefone (`INSERT IGNORE`) e exibe relatório pós-importação: X inseridos, Y ignorados (duplicatas).
* [ ] **10.6. Disparador de WhatsApp Assistido:** Botão em cada card do Kanban que gera link `https://wa.me/55{telefone}?text={mensagem}`. Mensagem pré-configurada por fase do funil (ex.: fase "proposta" usa template de proposta comercial). Clique abre o WhatsApp Web/App diretamente. Custo zero.
* [ ] **10.7. Integração no Dashboard:** Módulo "Prospecção & CRM" no sidebar (`super_admin`). Abas: Kanban, Importar CSV, Parcerias (visualizar formulários recebidos).

---

## 📱 FASE 11: WhatsApp Híbrido (Pedidos & Notificações)
*Foco: Integrar o fluxo de pedidos com WhatsApp usando apenas recursos nativos gratuitos.*

* [ ] **11.1. Migration status pedidos:** Adicionar `aguardando_validacao` ao ENUM `status` na tabela `pedidos`. Adicionar coluna `token_validacao` CHAR(36) UNIQUE NULL e `token_expira_em` DATETIME NULL.
* [ ] **11.2. Checkout Mobile Otimizado:** Refatorar `views/checkout/index.php` para layout mobile-first com steps visuais e botões grandes. Ao confirmar, pedido criado com status `aguardando_validacao` antes de aguardar a validação da loja.
* [ ] **11.3. Gerador de String de Pedido:** Função PHP `gerarStringPedido($pedido_id)` que formata o carrinho em texto tabulado (cabeçalho, itens com qty+produto+valor, total, endereço, forma de pagamento) e retorna URL `wa.me/55{tel_loja}?text={texto_codificado}`. Botão "Confirmar via WhatsApp" na tela de confirmação do checkout abre o link.
* [ ] **11.4. URL de Validação em Um Clique:** Ao gerar a string do pedido, o sistema cria um UUID v4 como `token_validacao`, define `token_expira_em = NOW() + 24h` e inclui o link `https://desffrut.com.br/validar/{token}` no final da mensagem. Rota pública `/validar/{token}` valida: token existe, não expirou, não foi usado → muda status para `Em Preparo`, marca token como consumido (set NULL), aciona evento de impressão via QZ Tray (se disponível). Token de uso único — segunda chamada retorna aviso de "pedido já confirmado".
* [ ] **11.5. Notificações de Status para Cliente:** Nos cards de pedido do dashboard (fragmento `pedidos.php`), botões por status: "📦 Em Preparo", "🛵 Saiu para Entrega", "✅ Entregue" — cada um abre `wa.me/55{tel_cliente}?text=` com mensagem gerada automaticamente incluindo número do pedido e ETA estimado. Custo zero, sem API paga.
* [ ] **11.6. Tela de Validação Pública (`views/validar.php`):** Rota limpa `/validar/{token}`. Exibe feedback visual ao atendente: pedido confirmado com sucesso (nome cliente, itens, endereço) ou erro (token expirado/já usado). Sem autenticação necessária.

---

## 📡 FASE 12: Marketing & AdTech (Analytics e Crescimento Orgânico)
*Foco: Instrumentar o portal para medir ROI de campanhas e ativar o crescimento por indicação.*

* [ ] **12.1. DataLayer — Meta Pixel:** Inserir script base do Pixel do Meta em `header.php` (ID configurável via tabela `configuracoes`). Disparar eventos: `PageView` (todas as páginas), `ViewContent` (página de produto), `AddToCart` (ao adicionar à sacola), `InitiateCheckout` (ao abrir o checkout), `Purchase` (ao confirmar pedido — com `value` e `currency`).
* [ ] **12.2. DataLayer — Google Tag (GA4):** Inserir `gtag.js` em `header.php` (ID configurável). Eventos equivalentes ao item 12.1 via `gtag('event', ...)`. Evento `purchase` inclui `transaction_id` (id do pedido), `value` e `items`.
* [ ] **12.3. Rastreamento de UTMs:** Middleware em `index.php` captura `$_GET['utm_source/medium/campaign/content/term']` e salva em `$_SESSION['utm']`. Ao criar pedido em `api/v1/pedidos.php`, os valores da sessão são gravados na coluna `origem_utm` JSON da tabela `pedidos`. Migration adiciona coluna `origem_utm` JSON NULL.
* [ ] **12.4. Relatório de ROI por Campanha (fragmento `adtech_roi.php`):** Tabela no BI que agrupa pedidos por `origem_utm->>'$.utm_source'` e `utm_campaign`, exibindo: total de pedidos, receita gerada e ticket médio por campanha. Filtro por período.
* [ ] **12.5. Gestor de Anúncios via Deep Link:** Painel onde o operador preenche: imagem do anúncio (upload), texto principal, texto de chamada, orçamento diário e duração. O sistema monta os parâmetros e abre o Gerenciador de Anúncios do Meta Business (`business.facebook.com/adsmanager/creation/...`) com os campos pré-preenchidos via query string. Sem integração direta com Graph API — zero dependência de app aprovado pelo Meta.
* [ ] **12.6. Motor "Indique e Ganhe":** Migration adiciona `codigo_indicacao` CHAR(10) UNIQUE em `clientes` e `indicado_por_cliente_id` INT NULL em `clientes`. Ao cadastrar novo cliente com `ref={codigo}` na URL, o vínculo é salvo. Ao primeiro pedido entregue do indicado: X pontos creditados automaticamente ao indicador via `registrarPontos()`. Painel em `/meu-perfil` exibe link de indicação personalizado e contagem de indicados ativos.
* [ ] **12.7. Integração no Dashboard:** Novo módulo "Marketing" no sidebar (`super_admin`). Abas: Pixels & Tags (configurar IDs), ROI por Campanha, Criar Anúncio (deep link), Programa de Indicação (ranking de indicadores).