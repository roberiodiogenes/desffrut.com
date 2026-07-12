# 📑 MASTER BRIEFING: ECOSSISTEMA FULLSTACK DESFFRUT
> **Fonte única de verdade do projeto.** Este arquivo consolida todas as decisões técnicas, regras de negócio e escopo do ecossistema Desffrut.
> Versão do documento: 2.2 | Última atualização: 2026-06-24

---

## 1. ESCOPO GERAL E PROPÓSITO

O sistema **Desffrut** é uma plataforma comercial híbrida (ERP, PDV e Catálogo Digital) projetada para gerenciar uma rede de lojas de hortifruti (Loja 1, Loja 2, Loja 3...). O objetivo é unificar em um **único domínio web** a experiência pública do cliente (compra e fidelidade) e o ambiente privado de trabalho (frente de caixa, gerência, logística, RH e finanças).

---

## 🛠️ 2. AMBIENTE DE DESENVOLVIMENTO E INFRAESTRUTURA

Para a execução, testes e deploy do projeto, serão utilizadas estritamente as seguintes ferramentas:

* **Editor de Código / IDE:** Visual Studio Code (VS Code).
* **Ambiente de Testes Local:** XAMPP (Localhost), utilizando o Apache para rodar o PHP e o MySQL/MariaDB localmente.
* **Controle de Versão:** Git com repositório remoto hospedado no GitHub.
* **Hospedagem / Produção:** Servidor HostGator Brasil (Ambiente Linux rodando PHP e MySQL na nuvem).
* **Deploy:** Pipeline Git → HostGator via FTP/SSH. Detalhamento na Fase 0.

---

## 🏗️ 3. ARQUITETURA TECNOLÓGICA & REGRAS DE NEGÓCIO

* **Backend:** PHP Puro ou com arquitetura MVC simples (totalmente compatível com o ambiente estável da HostGator).
* **Camada de API REST:** Todos os endpoints de sincronização e comunicação assíncrona residem em `/api/`. Autenticação via token de sessão (token gerado no login, armazenado em `$_SESSION` e enviado no header das requisições JS). Detalhamento na Fase 1.
* **Database Principal (Produção/Nuvem):** MySQL hospedado na HostGator.
* **Database Local (Desenvolvimento):** MySQL via XAMPP (`localhost`).
* **Database Caixa Offline:** `IndexedDB` (Banco de dados interno do navegador para garantir o funcionamento do PDV caso a internet da loja caia).
* **Frontend:** HTML5, CSS3 (Responsivo com Bootstrap ou Tailwind) e JavaScript Vanilla (ou framework leve).
* **Conexão de Hardware:** *Web Serial API* + **QZ Tray** (agente local) para comunicação do navegador com balanças comerciais (Toledo/Filizola) e impressoras térmicas (80mm/58mm). Ver Seção 3.1.
* **Atualizações Assíncronas:** A HostGator compartilhada não suporta WebSockets. Toda atualização de status "em tempo real" é implementada via **polling leve** (requisições a cada 15–30 segundos).
* **Backup:** Rotina de export agendado em PHP executa um dump diário do banco de dados para arquivo compactado no servidor. Ver Seção 3.2.

### 3.1. Hardware Local — Periféricos do PDV

O PDV integra quatro classes de hardware físico, todas via navegador (Chrome/Edge obrigatório):

#### Impressoras Térmicas — QZ Tray
O **QZ Tray** é um agente local gratuito e open source instalado no computador da loja. Faz a ponte entre o browser e a impressora via USB, serial ou rede, usando protocolo ESC/POS.

* Suporta 80mm (48 colunas) e 58mm (32 colunas). Configurável por estação no dashboard.
* Imprime automaticamente cupom de venda, comprovante de sangria e relatório de fechamento.
* Compatível com Bematech, Epson TM, Elgin, Daruma e similares.
* Download: https://qz.io/download/ | Produção: requer certificado RSA (https://qz.io/wiki/2.0-signing-messages).

#### Balanças Comerciais — Web Serial API
A **Web Serial API** do Chrome lê peso diretamente de balanças com porta RS-232 (Toledo, Filizola e similares).

* Protocolo: 9600 baud, 8N1. Suporta formatos ST e GS da Toledo.
* Botão ⚖️ no PDV captura o peso e preenche o modal de quantidade automaticamente.
* Adaptador USB-Serial é suficiente para balanças com porta DB9.

#### Leitor de Código de Barras / QR Code
Dois modos disponíveis, sem necessidade de driver adicional:

* **USB HID** (pistolas/leitores de mesa): emulam teclado — plug & play, zero configuração. Ao escanear, o código é digitado automaticamente no campo de busca do PDV e a busca é acionada.
* **Câmera** (BarcodeDetector API, Chrome/Edge 83+): botão 📷 ou atalho **F5** no PDV. Detecta EAN-13, EAN-8, UPC-A, QR Code, Code-128, Code-39 e outros. Beep de confirmação ao detectar.

> **Requisito de navegador:** Google Chrome ou Microsoft Edge (obrigatório em todos os modos). Firefox e Safari não são suportados para hardware.

> **Tela de configuração:** Dashboard → módulo **Hardware** (visível para super_admin e gerente). Permite configurar impressora, testar balança e testar câmera com status visual de cada periférico.

### 3.2. Estratégia de Backup

* Rotina PHP agendada (via cron na HostGator ou script chamado diariamente) executa `mysqldump` e salva o arquivo `.sql.gz` em diretório protegido no servidor.
* Retenção mínima: 7 dias de backups diários.
* Além do backup automático da HostGator, este dump manual garante portabilidade e recuperação independente do painel do provedor.

### 3.3. Política de Conflito no Background Sync (PDV Offline)

Quando o caixa reconecta após um período offline, a sincronização das vendas acumuladas segue a seguinte política:

* **Preço:** O preço gravado no cupom no momento da venda prevalece (snapshot imutável). Alterações de preço ocorridas durante o período offline não retroagem.
* **Estoque:** O estoque em nuvem é deduzido com base na **quantidade** de itens vendida, não no preço. O sistema registra a venda como realizada e ajusta o inventário.
* **Conflito de estoque negativo:** Se a quantidade vendida offline superar o estoque disponível em nuvem (ex.: outro caixa vendeu o mesmo item), o sistema registra a venda normalmente e sinaliza o item com alerta de "estoque negativo" no painel do gerente para revisão manual.

---

## 🚪 4. ENGENHARIA DE ACESSO UNIFICADO (SSO & PORTAL)

O sistema opera sob o conceito de **Portal Único** (`desffrut.com.br`). O comportamento da plataforma é moldado pelo estado de autenticação e nível de permissão do usuário:

```
[ Usuário acessa o Domínio Principal ]
│
├─► NÃO LOGADO: Navega pelo Catálogo Público (Adiciona itens à sacola)
│                  └─► Tenta fechar pedido? ──► EXIGE LOGIN/CADASTRO
│
└─► CLICA EM ENTRAR (Formulário de Login Único)
    │
    ├─► Se Nível = 'cliente'       ──► Vai para /meu-perfil (Mantém a sacola)
    │
    └─► Se Nível = operacional     ──► Vai para /dashboard (Dashboard Unificado)
        │   (caixa, entregador, rh_financeiro, gerente, super_admin)
        │
        └─► Sidebar lateral filtra os MÓDULOS visíveis pelo role:
            ├─► caixa         → Pedidos & Despacho (Pendentes/Ativos/Despacho/Histórico)
            ├─► entregador    → Pedidos & Despacho (Ativos/Despacho)
            ├─► rh_financeiro → Relatórios + Financeiro + RH
            ├─► gerente       → Produtos&Estoque + Pedidos + CEASA + Caixa(view) + Relatórios
            └─► super_admin   → Todos os módulos + Administração
```

> **Nota de implementação:** `/gerencia/dashboard` e `/admin/dashboard` agora redirecionam imediatamente para `/dashboard`. O dashboard unificado em `views/dashboard/index.php` serve todos os roles operacionais via sidebar com módulos filtrados por role no servidor (PHP). O carregamento de conteúdo é feito via AJAX (fetch) em fragmentos PHP isolados (`views/dashboard/fragmentos/*.php`).

---

## ⚖️ 5. CONFORMIDADE — LGPD (Lei 13.709/2018)

O Desffrut coleta e processa dados pessoais de clientes (CPF, endereço, histórico de compras) e de funcionários (dados trabalhistas). O sistema deve atender aos seguintes requisitos mínimos da LGPD:

* **Política de Privacidade:** Página estática visível no portal público (`/privacidade`), descrevendo quais dados são coletados, como são usados e por quanto tempo são retidos.
* **Consentimento Explícito:** No formulário de cadastro do cliente, checkbox obrigatório de aceite aos Termos de Uso e Política de Privacidade (não pré-marcado). O aceite é registrado com timestamp no banco de dados.
* **Mecanismo de Exclusão de Conta:** O cliente autenticado pode solicitar a exclusão de sua conta em `/meu-perfil`. A solicitação anonimiza os dados pessoais (nome, CPF, endereço) preservando o histórico financeiro agregado necessário para relatórios. O prazo de execução é de até 15 dias.
* **Dados de Funcionários:** Acesso restrito ao perfil `rh_financeiro` e `super_admin`. Logs de acesso a fichas de funcionários são registrados na trilha de auditoria.

---

## 📋 6. CHECKLIST COMPLETO DE FUNCIONALIDADES (POR CATEGORIAS)

### Categoria 01: Portal Público & Área do Cliente
* [ ] **Catálogo Digital Aberto:** Navegação sem login por categorias de hortifruti (Frutas, Verduras, Legumes) com preços expostos por Kg ou Unidade.
* [ ] **Filtro de Filial:** Seletor em destaque para o cliente escolher de qual loja deseja ver o estoque e os preços locais reais.
* [ ] **Carrinho de Visitante:** Adicionar itens à sacola de forma anônima, persistindo e vinculando os dados automaticamente após o login.
* [ ] **Painel de Fidelidade:** Consulta de saldo de pontos acumulados, histórico de extrato e opção de resgate por cupons de desconto. Regras de pontuação:
  * **Acúmulo:** R$ 1,00 gasto em qualquer compra (presencial ou online) = **1 ponto** creditado ao CPF do cliente.
  * **Resgate:** 100 pontos = **R$ 1,00** de desconto ou crédito aplicável na próxima compra.
  * Pontos são creditados após confirmação de pagamento. Cancelamentos estornam os pontos correspondentes.
* [ ] **Histórico Unificado de Compras:** Listagem de todos os pedidos do cliente (tanto compras online via tele-entrega quanto compras presenciais no balcão físico vinculadas ao seu CPF).

### Categoria 02: Controle de Acesso & Segurança (ACL)
* [ ] **Banco de Dados Centralizado de Usuários:** Uma única tabela com o campo de nível (`role`) determinando os acessos e `loja_id` determinando a filial do colaborador.
* [ ] **Middlewares de Proteção:** Bloqueio rígido no PHP para impedir que níveis inferiores ou clientes acessem URLs administrativas via digitação direta no navegador.

### Categoria 03: Frente de Caixa / PDV Híbrido (Online/Offline)
* [ ] **Sincronização de Abertura:** Download em lote do catálogo de produtos, preços vigentes e base de clientes para o `IndexedDB` ao iniciar o dia do caixa.
* [ ] **Operações de Caixa:** Abertura com fundo de troco, suprimento e controle de sangria obrigatória com justificativa por escrito.
* [ ] **Interface de Venda Rápida:** Tela otimizada operada exclusivamente por atalhos de teclado (sem mouse), cancelamento de itens (com validação de senha do gerente) e múltiplas formas de pagamento: Dinheiro, Cartão de Débito/Crédito, **Pix (registro manual — operador confirma o recebimento sem geração de QR code)** e Pontos de Fidelidade.
  > ⚠️ **Vale-Alimentação/Refeição:** não integrado nesta versão. Previsto para V2.0.
* [ ] **Contingência Offline:** Em caso de queda de internet, o caixa continua vendendo normalmente e gravando os cupons localmente no `IndexedDB`. O preço registrado no cupom é snapshot imutável (ver Seção 3.3).
* [ ] **Background Sync:** Sincronização automática em segundo plano das vendas acumuladas assim que a internet do caixa retornar, seguindo a política de conflito definida na Seção 3.3.

### Categoria 04: Integração com Hardwares Locais ✅
> ⚠️ **Pré-requisito:** QZ Tray instalado e ativo no computador do caixa. Navegador obrigatório: Chrome ou Edge.

* [x] **Leitura de Balança:** Captura do peso líquido via *Web Serial API* (Toledo/Filizola RS-232). Botão ⚖️ no PDV.
* [x] **Impressão Térmica:** Cupons ESC/POS (80mm/58mm) para impressoras USB/rede via QZ Tray. Impressão automática de venda, sangria e fechamento.
* [x] **Leitor de Código de Barras / QR Code:** USB HID (plug & play, emula teclado) ou câmera (BarcodeDetector API, F5 no PDV). EAN-13, EAN-8, QR Code, Code-128 e outros.
* [x] **Configuração por Estação:** Dashboard → Hardware com status visual de todos os periféricos e tela de testes.

### Categoria 05: Catálogo, Promoções & Estoque (Nuvem)
* [ ] **Cadastro Multi-loja de Itens:** Gerenciamento centralizado de produtos com opção de ocultar/inativar itens sem excluir históricos passados. Upload de foto do produto com as seguintes regras:
  * Formatos aceitos no upload: qualquer formato de imagem comum (JPG, PNG, GIF, WEBP) com tamanho máximo de **6 MB**.
  * O sistema converte automaticamente para **WebP** e redimensiona para no máximo **90 KB** antes de salvar em `uploads/produtos/`.
  * Apenas o arquivo WebP final é armazenado; o original não é mantido.
* [ ] **Promoções Programadas:** Definição de preço promocional com data e hora de início e expiração automáticas.
* [ ] **Estoque Isolado:** Controle de inventário individualizado por filial com alertas visuais para níveis baixos ou críticos.
* [ ] **Controle de Quebras:** Módulo específico para dar baixa em mercadorias que estragaram ou sofreram avarias (controle de perdas de perecíveis).
* [ ] **Gerador de Pedidos de Compra:** Relatório limpo estruturado em PDF listando itens abaixo do estoque mínimo para envio a fornecedores ou CEASA.

### Categoria 06: Tele-Entrega & Logística
* [ ] **Painel de Despacho:** Tela para o gerente agrupar pedidos (vindos do portal ou telefone) e delegar rotas aos entregadores disponíveis.
* [ ] **Painel Mobile do Entregador:** Relação de entregas otimizada para o smartphone com endereços integrados ao Google Maps/Waze e indicação da forma de acerto.
* [ ] **Rastreamento por Polling:** Atualização do status do pedido ("Preparando", "Saiu para Entrega", "Entregue") visível para o cliente via polling leve (requisição JS a cada 15–30 segundos). WebSocket não utilizado — incompatível com hospedagem compartilhada HostGator.

### Categoria 07: Gestão de RH & Departamento Pessoal
* [ ] **Admissão de Funcionários:** Ficha com dados trabalhistas, cargo, salário-base, data de entrada e vinculação à filial correspondente.
* [ ] **Histórico e Baixas:** Registro de alterações de cargos, reajustes e módulo de desligamento/demissão com revogação imediata de credenciais de acesso.
* [ ] **Integração de Folha:** Emissão automática do relatório de salários devidos do mês enviado diretamente para o módulo de contas a pagar.

### Categoria 08: Gestão Financeira & Contas a Pagar
* [ ] **Lançamento de Despesas Fixas/Variáveis:** Cadastro e controle de contas das lojas (Aluguel, Água, Energia, Internet, Fornecedores e Salários).
* [ ] **Fluxo de Baixa:** Registro de quitação de contas com histórico de pagamentos e arquivamento de logs.

### Categoria 09: Dashboard & Business Intelligence
* [ ] **Painel Dinâmico de BI:** Gráficos com faturamento bruto e filtros flexíveis para isolar métricas por loja ou ver o consolidado geral do grupo.
* [ ] **Lucro Líquido Real:** Cruzamento automatizado do faturamento bruto deduzindo as despesas operacionais (Categoria 08) e salários (Categoria 07).
* [ ] **Auditoria de Segurança:** Histórico detalhado de logs contendo as ações críticas realizadas no ecossistema (quem efetuou sangrias, cancelamentos de cupons ou alterações em tabelas de preços).

### Categoria 10: Painel de Prospecção & Expansão de Leads (Custo Zero)
* [ ] **Formulário de Parcerias Público:** Página institucional externa para captação passiva de novos parceiros comerciais (B2B) interessados em compras em volume. Dados salvos na tabela `leads`.
* [ ] **Pipeline Visual (CRM Kanban):** Interface administrativa com cartões arrastáveis (`Sortable.js` via CDN) para gerenciar as fases da negociação: Novo → Contato → Proposta → Negociação → Fechado → Perdido. Atualização de fase via AJAX.
* [ ] **Importador de Leads (CSV Genérico):** Ferramenta de upload de qualquer planilha `.csv` com colunas `nome`, `telefone` e `bairro`. O usuário alimenta o arquivo de onde quiser. Dados injetados no banco com filtro de duplicatas por telefone.
* [ ] **Disparador de WhatsApp Assistido:** Geração de links `wa.me/?text=` dinâmicos por fase do funil, com mensagens pré-configuradas. Envio por clique individual — custo zero, sem API paga.

### Categoria 11: Integração de Pedidos via WhatsApp Híbrido (Automação Gratuita)
* [ ] **Checkout Estruturado Mobile:** Layout de finalização de compra otimizado para smartphones, salvando o pedido no MySQL como `aguardando_validacao`. Ajuste de CSS no checkout existente + novo estado na máquina de estados.
* [ ] **Gerador de String de Pedidos:** Engine em PHP que converte o carrinho de compras em mensagem de texto tabulada e legível, aberta via link `wa.me/{tel_loja}` para o WhatsApp da filial correspondente.
* [ ] **URL de Validação em Um Clique:** Link exclusivo (token UUID v4) incluído no final da mensagem recebida pela loja. Ao clicar: altera status do pedido para `Em Preparo` e aciona impressão térmica via QZ Tray. Token expira em 24h e é de uso único.
* [ ] **Notificações de Status:** Botões nas telas de Gerência e Entregador que abrem `wa.me/{tel_cliente}?text=` com mensagem automática de atualização logística gerada pelo sistema — custo zero.

### Categoria 12: Inteligência de Marketing e Gestão de Tráfego (AdTech)
* [ ] **Camada de Dados (DataLayer) para Pixels:** Injeção de `fbq()` (Meta Pixel) e `gtag()` (Google Tag) no `header.php`, com disparo automático de eventos: Ver Produto, Adicionar Sacola, Iniciar Checkout e Compra Concluída.
* [ ] **Rastreamento de UTMs e Campanhas:** Captura dos parâmetros `utm_source`, `utm_medium` e `utm_campaign` no acesso. Dados persistidos na sessão PHP e gravados na tabela de pedidos (coluna `origem_utm`) para cálculo de ROI por campanha.
* [ ] **Gestor de Anúncios Simplificado:** Painel onde o operador preenche imagem, texto e orçamento do anúncio. O sistema gera o criativo localmente e abre o Gerenciador de Anúncios do Meta com os campos pré-preenchidos via URL de deep link. Sem integração direta com a Graph API — sem dependência de app aprovado, sem risco de quebra por mudanças de política.
* [ ] **Motor Orgânico "Indique e Ganhe":** Geração de link de indicação único por cliente (`/i/{token}`). Quando um novo cliente finaliza a primeira compra via link, o indicador recebe crédito de pontos automaticamente. Integrado ao sistema de fidelidade existente.

### Categoria 13: Sistema de Customização e Configuração do Portal (CMS)
* [ ] **Gestor de Identidade Visual:** Interface para o Super Admin alterar Nome do Sistema, Slogan, Logomarca e paleta de cores. Configurações armazenadas em tabela `configuracoes` (key-value). CSS custom properties injetadas pelo PHP no layout.
* [ ] **Central de Banners e Vitrines:** Upload, ativação e desativação de banners promocionais rotativos na página inicial. Versões separadas para Mobile e Desktop. Tabela `banners` com controle de ordem, link de destino e período de exibição.
* [ ] **Painel de Detalhes das Lojas:** Gerenciamento dos dados públicos de cada filial: Endereço, Horário de Funcionamento, Telefone e Link do WhatsApp Business local. Novas colunas `horario_funcionamento` e `whatsapp_link` na tabela `lojas` existente.
* [ ] **Módulo de Campanhas Sazonais:** Ativação de cupons de desconto globais ou temas visuais (classe CSS no `body`) para datas comemorativas específicas. Tabela `campanhas` com `data_inicio`, `data_fim` e tipo (`cupom_global` ou `tema_css`).

### Categoria 14: Módulo Exclusivo do Desenvolvedor (Privilégios Raiz)
* [ ] **Painel Dev Mode Isolado:** Nível de acesso `dev_admin`, superior ao `super_admin`, com rota oculta dedicada. Middleware de autenticação independente dos demais roles.
* [ ] **Modo Manutenção:** Interruptor global que desativa o portal público exibindo tela customizada e bloqueia novos pedidos enquanto o sistema é atualizado. Flag em tabela `configuracoes` + middleware que intercepta todas as requests.
* [ ] **Aviso de Inadimplência (Stand By Brando):** Quando ativado pelo dev_admin, exibe banner fixo e destacado no topo de todos os painéis administrativos (incluindo donos, gerentes e rh_financeiro) informando o débito pendente. A operação do sistema não é interrompida — apenas o aviso é exibido.
* [ ] **Reset Forçado de Senha:** Ferramenta para o dev_admin gerar uma senha temporária aleatória para qualquer usuário ou nível administrativo e enviá-la por e-mail ao titular da conta. Não armazena nem exibe senhas em texto limpo — compatível com LGPD e bcrypt.
* [ ] **Auditoria Forense Ampliada:** Extensão da trilha de auditoria existente com: log de IPs em cada login de conta administrativa, registro de alterações de privilégios de usuários e histórico de ativações de Modo Manutenção e Stand By. Tabela imutável por design (sem botão de exclusão de log neste painel).

---

## 🗺️ 7. PLANO DE DESENVOLVIMENTO POR FASES (RESUMO EXECUTIVO)

> O detalhamento completo com checklists de execução está em `briefings/checklist.md`.

| Fase | Foco | Entregável Principal | Status |
|------|------|----------------------|--------|
| **0** | Setup & Infraestrutura | Repositório, estrutura de pastas, `.htaccess`, pipeline deploy | ✅ |
| **1** | Fundação: DB, SSO & API | Banco relacional, login unificado, camada `/api/` com token | ✅ |
| **2** | Portal Público & Cliente | Catálogo responsivo, carrinho visitante, área do cliente | ✅ |
| **3** | Retaguarda Comercial | CRUD de produtos, promoções, inventário, estoque, dashboard unificado com sidebar | ✅ |
| **4** | PDV Híbrido Offline | Caixa com IndexedDB, venda rápida por teclado, sync de retorno | ⏳ |
| **5** | Hardware Local | QZ Tray + balança (serial/USB) + impressão térmica ESC/POS + leitor código de barras/QR Code | ✅ |
| **6** | Tele-Entrega & Logística | Checkout, API Pedidos, rastreamento polling, despacho, painel entregador | ✅ |
| **7** | ERP Administrativo | RH, Contas a Pagar, BI com lucro líquido, trilha de auditoria | ✅ |
| **8** | CMS & Customização do Portal | Identidade visual, banners, detalhes das lojas, campanhas sazonais | ⏳ |
| **9** | Dev Mode & Operações de Sistema | Painel dev_admin, modo manutenção, aviso inadimplência, reset forçado, auditoria forense | ⏳ |
| **10** | Prospecção & CRM Kanban | Formulário parceiros, pipeline Kanban, importador CSV, disparador WhatsApp | ⏳ |
| **11** | WhatsApp Híbrido | Checkout mobile, gerador string pedido, URL validação 1 clique, notificações status | ⏳ |
| **12** | Marketing & AdTech | DataLayer pixels, rastreamento UTMs, gestor anúncios deep link, programa Indique e Ganhe | ⏳ |

### Categoria 15: Frontend Público Complementar

> Páginas voltadas ao visitante/cliente que complementam o catálogo existente. Sem autenticação. Prioridade: alta para conformidade legal e experiência do usuário.

* [ ] **`/lojas` — Nossas Lojas:** Página pública que exibe os dados de cada filial gerenciados pelo CMS (Fase 8): endereço, horário de funcionamento, telefone e link direto para o WhatsApp Business local. Cards responsivos, um por filial. Dados lidos via `api/v1/lojas.php` (GET público). Mapa incorporado opcional (link Google Maps por endereço).
* [ ] **`/sobre` — Quem Somos:** Página institucional com a história do hortifruti, missão/valores e diferenciais (origem dos produtos, rastreabilidade, entrega). Conteúdo estático gerenciado via CMS (campo `texto_sobre` na tabela `configuracoes`). Impacta SEO e confiança do cliente.
* [ ] **`/fidelidade` — Programa de Pontos:** Landing page explicando as regras do programa: acúmulo (R$1 gasto = 1 ponto), resgate (100 pontos = R$1 de desconto), indicação (+X pontos no 1º pedido do indicado). CTA para cadastro. Dados de configuração do programa lidos de `configuracoes` (chave `pontos_indicacao`, `pontos_por_real`).
* [ ] **`/termos` — Termos de Uso:** Documento legal separado da Política de Privacidade. Cobre: regras do pedido online, prazo de entrega estimado, política de cancelamento (somente antes do status `preparando`), responsabilidade sobre itens perecíveis em trânsito, regras de expiração/transferência de pontos de fidelidade. Versão e data no cabeçalho. Link obrigatório no rodapé e no formulário de cadastro.
* [ ] **`/privacidade` (revisão) — Política de Privacidade Específica para Hortifruti:** Atualizar o arquivo existente para cobrir especificidades do negócio: dados de peso capturados (itens vendidos por kg vinculados ao CPF), histórico de compras de alimentos (preferências alimentares são dados sensíveis sob LGPD), programa de fidelidade vinculado ao CPF, dados de funcionários tratados no sistema (acesso restrito por role). Adicionar seção de cookies e rastreamento (Meta Pixel, GA4 — Fase 12). Formato com âncoras por seção para fácil navegação.
* [ ] **`/404` — Página de Erro Personalizada:** View `views/errors/404.php` com identidade visual do sistema, mensagem amigável e link de retorno ao catálogo. Configurada no `.htaccess` via `ErrorDocument 404`.
* [ ] **`/manutencao` — Tela de Manutenção:** View `views/manutencao.php` exibida quando o `dev_admin` ativa o modo manutenção (Fase 9). Mensagem customizável via `configuracoes` (chave `mensagem_manutencao`). Design simples com logo e previsão de retorno.
* [ ] **`robots.txt` e `sitemap.xml`:** Arquivo `robots.txt` na raiz bloqueando `/dashboard`, `/pdv`, `/api`, `/dev`, `/admin`. `sitemap.xml` gerado dinamicamente por `sitemap.php` listando: `/`, `/lojas`, `/sobre`, `/fidelidade`, `/parcerias`, `/termos`, `/privacidade`. Necessário para indexação correta e SEO.
* [ ] **Meta Tags OG (Open Graph):** Adicionar ao `header.php` as tags `og:title`, `og:description`, `og:image` e `og:url`. Imagem padrão: logo do sistema. Na página de produto (modal → URL canônica futura), usar foto do produto. Impacto direto no preview de links compartilhados no WhatsApp e redes sociais.
* [ ] **PWA Manifest (`/manifest.json`):** Arquivo de manifesto para permitir que o cliente instale o site como app no celular (Android). Campos: `name`, `short_name`, `start_url`, `display: standalone`, `background_color`, `theme_color`, `icons` (192px e 512px — derivados do logo). Service worker básico para cache do shell (sem modo offline completo para o cliente).

---

### Categoria 16: Manual de Uso da Equipe Desffrut

> Página interna `/manual` com navegação por sanfona (accordion Bootstrap). Acessível sem login para facilitar onboarding de novos colaboradores. Conteúdo fixo (HTML estático), atualizado pelo desenvolvedor conforme o sistema evolui. URL pública mas não indexada (`robots.txt` pode excluir).

* [ ] **Estrutura da Página `/manual`:** View `views/manual/index.php`. Layout com logo, título e accordion Bootstrap por perfil de usuário. Âncoras nomeadas para link direto por seção (`/manual#caixa`, `/manual#entregador`). Versão do manual e data de atualização no rodapé.
* [ ] **Seção: Dono / Super Admin:** Como acessar o sistema, configurar identidade visual (CMS), cadastrar lojas e produtos, ver relatórios de BI e DRE, acessar auditoria, ativar/desativar campanhas sazonais e programa de indicação, gerenciar usuários e permissões.
* [ ] **Seção: Gerente:** Como cadastrar e editar produtos com foto, programar promoções, controlar estoque e registrar quebras, gerenciar pedidos do dashboard (pendentes → preparando → despacho), usar o Kanban de leads, configurar hardware da loja.
* [ ] **Seção: RH / Financeiro:** Como admitir funcionários, registrar ponto manualmente, gerar folha de pagamento, lançar contas a pagar, marcar contas como pagas, visualizar fluxo de caixa e DRE.
* [ ] **Seção: Caixa (Operador de PDV):** Passo a passo de abertura de caixa, como vender (busca por nome/código de barras, atalhos F1–F4), registrar sangria com justificativa, processar diferentes formas de pagamento (dinheiro, cartão, Pix manual, pontos), contingência offline (o que fazer se a internet cair), fechamento de caixa.
* [ ] **Seção: Entregador:** Como acessar o painel de entregas, visualizar rota no Google Maps / Waze, confirmar entrega (botão "✅ Entreguei"), enviar notificação de status ao cliente via WhatsApp.
* [ ] **Seção: Perguntas Frequentes (FAQ):** Dúvidas comuns de operação: "O que fazer se a impressora não imprimir?", "Como cancelar um item já adicionado?", "O estoque ficou negativo — o que significa?", "O cliente quer cancelar o pedido — como proceder?". Cada resposta referencia o módulo correto do dashboard.

---

### Categoria 17: Navegação, SEO e Roteamento de Erros

> Infraestrutura de navegação global do portal público e otimização para motores de busca. Implementada sobre o sistema de layout já existente (`header.php` / `footer.php`) — zero alteração nas views individuais.

---

#### 17.1 Navegação global (header.php)

* [ ] **Links de navegação pública no navbar:** Adicionar ao `header.php` uma barra de links controlada pela variável `$mostrar_nav_publica` (padrão `true`). Links: Catálogo (`/`), Nossas Lojas (`/lojas`), Quem Somos (`/sobre`), Fidelidade (`/fidelidade`), Parcerias (`/parcerias`). Páginas que não devem exibir o menu (manutenção, manutencao.php — que não usa header.php) já estão isoladas naturalmente.
* [ ] **Variável `$nav_ativa`:** Páginas definem `$nav_ativa = 'lojas'` (ou outro slug) antes de incluir header.php. O link correspondente recebe a classe `active` no navbar.

#### 17.2 Rodapé completo (footer.php)

Substituir o rodapé minimalista por um footer de 3 colunas Bootstrap:

| Coluna | Links |
|--------|-------|
| **Links Rápidos** | Catálogo, Nossas Lojas, Quem Somos, Programa de Fidelidade, Seja Nosso Parceiro |
| **Institucional** | Política de Privacidade, Termos de Uso |
| **Acesso** | Manual da Equipe, Área Interna (login) |

Rodapé inferior: copyright, nome do sistema e ano dinâmico.

#### 17.3 Correção de rota 404

**Problema:** `ErrorDocument 404 /views/errors/404.php` não funciona em XAMPP (subdiretório) porque o Apache resolve o caminho a partir da raiz do servidor, não da pasta do projeto.

**Solução adotada — dupla proteção:**
1. Criar `404.php` na raiz do projeto. `ErrorDocument 404 /404.php` em produção (HostGator) resolve corretamente.
2. Adicionar ao final do `.htaccess` uma regra **catch-all** que captura qualquer rota não mapeada e a redireciona para `views/errors/404.php` — funciona tanto em XAMPP quanto em produção:

```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^.*$ views/errors/404.php [L]
```

#### 17.4 SEO por página

Variáveis que cada página define antes de incluir `header.php`:

| Variável | Tipo | Descrição |
|----------|------|-----------|
| `$titulo_pagina` | string | Texto do `<title>` (ex: "Nossas Lojas") |
| `$og_description` | string | Meta description + og:description (máx. 160 chars) |
| `$og_image` | string | URL absoluta da imagem OG (fallback: logo) |
| `$canonical_url` | string | URL canônica absoluta da página |
| `$robots` | string | Diretiva robots (padrão: `"index,follow"`) |
| `$json_ld` | string | JSON-LD serializado (structured data) para o `<head>` |

`header.php` lê essas variáveis e injeta os respectivos `<meta>` e `<script type="application/ld+json">` no `<head>`.

**Tabela de SEO por página:**

| Página | `<title>` | `robots` | Structured Data (JSON-LD) |
|--------|-----------|----------|--------------------------|
| `/` (catálogo) | "Frutas, Verduras e Legumes Frescos — Desffrut" | index,follow | `Organization` + `FoodEstablishment` |
| `/lojas` | "Nossas Lojas — Desffrut" | index,follow | `LocalBusiness` por filial |
| `/sobre` | "Quem Somos — Desffrut" | index,follow | `Organization` |
| `/fidelidade` | "Programa de Fidelidade — Desffrut" | index,follow | nenhum |
| `/parcerias` | "Parcerias B2B — Desffrut" | index,follow | nenhum |
| `/privacidade` | "Política de Privacidade — Desffrut" | noindex,follow | nenhum |
| `/termos` | "Termos de Uso — Desffrut" | noindex,follow | nenhum |
| `/manual` | "Manual da Equipe — Desffrut" | noindex,nofollow | nenhum |
| `/404` | "Página não encontrada — Desffrut" | noindex,nofollow | nenhum |
| `/manutencao` | "Em manutenção — Desffrut" | noindex,nofollow | nenhum |

**Structured data — schemas utilizados:**
- `Organization` — nome, logo, URL, contactPoint (email) — em `/sobre` e `/`
- `FoodEstablishment` / `LocalBusiness` — nome, endereço, telefone, openingHours, geo — em `/lojas` (um bloco JSON-LD por filial)
- `BreadcrumbList` — implementação futura (quando houver páginas de categoria de produto)

---

---

### Expansão do Cadastro de Lojas (Gestão pelo Super Admin)

> Funcionalidade exclusiva do `super_admin` (dono). Complementa o item existente "Painel de Detalhes das Lojas" (Cat. 13) adicionando a capacidade de criar novas lojas à medida que o negócio expande.

* [ ] **Seed: Loja 3 adicionada** — `database/01-seed.sql` atualizado com a terceira filial do Desffrut (nome, endereço, telefone, whatsapp_link, horario_funcionamento, ativo=1).
* [ ] **CRUD completo de Lojas — exclusivo super_admin:** Interface em `views/admin/lojas.php` para criar, editar, ativar/desativar e excluir lojas. Campos: nome, endereço, telefone, WhatsApp Business, horário de funcionamento, observações internas. Acessível apenas para `role = super_admin`. Gerentes e demais roles não têm acesso a este CRUD — podem apenas visualizar dados públicos da própria loja.
* [ ] **Proteção de exclusão:** Lojas com pedidos ou funcionários vinculados não podem ser excluídas fisicamente — apenas desativadas (`ativo = 0`). A exclusão física é bloqueada com mensagem explicativa.
* [ ] **Reflexo automático:** Ao criar uma nova loja, ela aparece automaticamente na página pública `/lojas`, no seletor de loja do checkout, e na listagem de lojas para vínculo de funcionários.

---

### Categoria 18: Etiquetas Térmicas para Produtos e Expositores

> Impressão de etiquetas adesivas em impressora térmica (formato padrão de gôndola e etiqueta de produto). Integração com QZ Tray (já presente na Fase 5). Bibliotecas: `JsBarcode` (Code 128) e `qrcode.js` (QR Code).

---

#### 18.1 Código interno do produto

* [ ] **Geração automática de `codigo_interno`:** Ao cadastrar um produto, o sistema gera um código único `PRD-XXXXXX` (6 dígitos incrementais com prefixo). Armazenado na tabela `produtos` — nova coluna `codigo_interno VARCHAR(12) UNIQUE`. Migration: `12-migration_fase18_etiquetas.sql`.
* [ ] **Exibição do código interno:** Visível na tela de cadastro/edição do produto (Gerência → Produtos) e na listagem do PDV. Usado como payload do barcode (Code 128) e como identificador de busca rápida no PDV.

#### 18.2 Etiqueta de expositor (prateleira)

> Etiqueta maior (ex: 10cm × 5cm) para identificar o produto na prateleira/gôndola. Sem barcode — focada na leitura visual pelo cliente.

* [ ] **Campos da etiqueta de expositor:**
  - Nome do produto (fonte grande)
  - Preço de venda (destaque, fonte muito grande)
  - Unidade (kg / un / caixa)
  - Nome da loja (subtítulo)
  - Logo do sistema (opcional, via CMS)
  - QR Code (codifica: nome + preço + unidade)
* [ ] **Seleção múltipla para impressão:** Em Gerência → Produtos, checkboxes permitem selecionar um ou vários produtos. Botão "🏷️ Imprimir Etiqueta Expositor" abre preview de impressão com layout `@media print`.
* [ ] **Layout de impressão:** Grade responsiva de etiquetas por folha (A4 horizontal → 4 etiquetas; folha térmica → 1 etiqueta). CSS `@page` configurado para impressora térmica.

#### 18.3 Etiqueta adesiva de produto (para balança / embalagem)

> Etiqueta menor (ex: 5cm × 3cm ou 6cm × 4cm) para colar no produto/embalagem. Contém barcode para leitura pelo PDV.

* [ ] **Campos da etiqueta de produto:**
  - Nome do produto
  - Preço unitário ou por kg
  - **Validade:** campo de data digitado pelo operador no momento da impressão (não armazenado — cada impressão pode ter validade diferente)
  - **Código de barras Code 128** (payload: `codigo_interno` do produto) — gerado por JsBarcode
  - **QR Code** (payload: JSON `{nome, preco, validade, loja}`) — gerado por qrcode.js
  - Nome da loja
* [ ] **Interface de impressão:** Modal em Gerência → Produtos. O operador define: quantidade de etiquetas, validade (datepicker) e tamanho da etiqueta (dropdown: 5×3cm / 6×4cm / personalizado). Preview ao vivo antes de imprimir.
* [ ] **Impressão via QZ Tray:** Quando QZ Tray está conectado, envia diretamente para a impressora térmica de etiquetas configurada (sem diálogo de browser). Fallback: impressão via `window.print()`.

---

### Categoria 19: PDV Multi-Loja e Caixas Individuais por Filial

> Cada operador de caixa gerencia exclusivamente o caixa da loja à qual está vinculado. O super_admin e gerentes têm visão consolidada ou por loja. Sem alteração nos roles — a segmentação é por `loja_id` no vínculo do funcionário.

---

#### 19.1 Vínculo operador ↔ loja

* [ ] **`loja_id` em `funcionarios`:** Verificar se a coluna já existe (Fase 7). Garantir que todo funcionário com role `caixa` tenha `loja_id` preenchido. Migration adiciona `NOT NULL` constraint com foreign key se ausente.
* [ ] **Validação no login do PDV:** Ao acessar `/pdv`, o middleware verifica se o usuário tem `loja_id` definido. Se não tiver (ex: novo funcionário sem loja), exibe aviso e bloqueia o acesso até o super_admin vincular a loja.
* [ ] **Seleção de loja no PDV restrita:** O caixa vê o nome da sua loja fixo no topo do PDV — não há dropdown para trocar. Gerentes e super_admin podem selecionar qualquer loja via dropdown.

#### 19.2 Abertura e fechamento de caixa por loja

* [ ] **Abertura de caixa segmentada:** A tela `/pdv/abertura` registra o caixa vinculado à `loja_id` do operador. Duas aberturas simultâneas na mesma loja são bloqueadas (uma loja, um caixa aberto por vez). Múltiplas lojas podem ter caixas abertos simultaneamente.
* [ ] **Fechamento e relatório por loja:** O relatório de fechamento (`/pdv/sangria` e fechamento final) exibe apenas as transações da loja do operador. Total de vendas, formas de pagamento e sangrias são segmentados por `loja_id`.
* [ ] **Painel consolidado para super_admin:** Em `/admin/dashboard` ou `/dashboard` (role super_admin), novo card "Caixas Abertos" mostra status de cada loja em tempo real: loja, operador logado, hora de abertura, total acumulado no turno.

#### 19.3 Relatórios multi-loja

* [ ] **Filtro por loja nos relatórios de BI:** Os fragmentos `bi.php` e `financeiro.php` ganham um dropdown de loja (visível apenas para super_admin e gerente). Padrão: "Todas as lojas" (consolidado). Gerente vê apenas a própria loja.
* [ ] **KPIs por loja no dashboard:** Overview do BI passa a exibir faturamento do dia por loja (mini-cards comparativos), além do total consolidado.

---

### Categoria 20: Controle Granular de Permissões por Usuário

> O super_admin pode conceder ou revogar permissões específicas de qualquer funcionário, sem alterar o role base. Implementado como tabela de exceções — o que não está na tabela segue o padrão do role.

---

#### 20.1 Modelagem

* [ ] **Tabela `permissoes_usuario`:** Migration `13-migration_fase20_permissoes.sql`.
  ```sql
  CREATE TABLE permissoes_usuario (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT NOT NULL,
    permissao   VARCHAR(60) NOT NULL,
    concedida   TINYINT(1) NOT NULL DEFAULT 1,
    criado_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuario_perm (usuario_id, permissao),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
  );
  ```
* [ ] **Enum de permissões granulares:** Definido em `app/config/permissoes.php` como array associativo `permissao => descrição_legível`. Permissões iniciais:

| Chave | Descrição | Role padrão que tem |
|-------|-----------|---------------------|
| `ver_dre` | Visualizar DRE e resultado financeiro | super_admin, rh_financeiro |
| `ver_bi` | Acessar painel de BI e gráficos | super_admin, gerente, rh_financeiro |
| `exportar_relatorio` | Exportar dados em CSV/PDF | super_admin, rh_financeiro |
| `editar_produto` | Cadastrar e editar produtos | super_admin, gerente |
| `aplicar_desconto` | Aplicar desconto manual no PDV | super_admin, gerente, caixa |
| `ver_auditoria` | Acessar trilha de auditoria | super_admin |
| `ver_historico_pontos` | Ver histórico de pontos de clientes | super_admin, gerente |
| `gerenciar_campanhas` | Ativar/desativar campanhas sazonais | super_admin, gerente |
| `ver_folha` | Visualizar folha de pagamento | super_admin, rh_financeiro |
| `admitir_funcionario` | Admitir e demitir funcionários | super_admin, rh_financeiro |
| `imprimir_etiqueta` | Imprimir etiquetas de produto | super_admin, gerente, caixa |
| `ver_relatorio_estoque` | Ver relatórios de estoque crítico | super_admin, gerente |

#### 20.2 Middleware de permissões

* [ ] **Função helper `tem_permissao($usuario_id, $permissao)`:** Em `app/helpers/functions.php`. Lógica: (1) verifica se há entrada em `permissoes_usuario` para o par `usuario_id + permissao`; (2) se houver, usa o valor de `concedida`; (3) se não houver, verifica o array de permissões padrão do role do usuário. Retorna `bool`.
* [ ] **Aplicação nos endpoints de API:** Cada endpoint sensível chama `tem_permissao()` antes de processar. Retorna HTTP 403 com `{"erro": "sem_permissao", "permissao": "ver_dre"}` se negado.
* [ ] **Aplicação nas views:** Botões e módulos do dashboard verificam `tem_permissao()` antes de renderizar. Módulos sem permissão ficam ocultos (não apenas desabilitados) para não confundir o operador.

#### 20.3 Interface de gerenciamento (super_admin)

* [ ] **Painel de permissões por usuário:** Em `views/admin/usuarios.php`, ao clicar em um funcionário, abre modal "Permissões Customizadas". Lista todas as permissões do enum com o valor padrão do role (chip cinza "Padrão") e eventuais exceções (chip verde "Concedida" ou chip vermelho "Revogada"). Toggle switch por permissão.
* [ ] **API de permissões:** `api/v1/permissoes.php` — GET `/:usuario_id` (lista permissões efetivas), POST (adicionar exceção), DELETE (remover exceção — volta ao padrão do role). Registra log na auditoria a cada alteração.
* [ ] **Histórico de alterações:** A trilha de auditoria registra quem alterou, qual permissão, para qual usuário e quando.

---

### Categoria 21: Redesign do PDV

> Reformulação visual e de UX do PDV existente (`views/pdv/index.php`). Mantém toda a lógica de negócio (JavaScript + API) — apenas layout, CSS e fluxo de interação são alterados. Compatível com teclado, touch e mouse.

---

#### 21.1 Layout geral

* [ ] **Split-screen aprimorado:** Painel esquerdo (60%) — busca + grid de produtos; Painel direito (40%) — carrinho + totalizador + pagamento. Em mobile (< 768px): tabs "Produtos" e "Carrinho" alternam entre os painéis.
* [ ] **Header do PDV:** Barra superior com: nome da loja + operador logado (esquerda), status de conexão QZ Tray + modo offline (centro), relógio em tempo real + botão de Sangria (direita). Altura fixa, sem scroll.
* [ ] **Altura total da tela:** PDV ocupa 100vh sem scroll na página. Os painéis internos têm scroll independente (produtos e itens do carrinho).

#### 21.2 Painel de produtos (esquerda)

* [ ] **Campo de busca destacado:** Sempre em foco ao abrir o PDV. Busca por nome, código interno ou código de barras. Resultado aparece em tempo real (debounce 300ms).
* [ ] **Cards de produto visuais:** Grid 3 colunas (desktop) / 2 colunas (tablet). Cada card: foto (thumbnail 80×80px), nome, preço, unidade. Clique/Enter adiciona ao carrinho com animação de feedback (flash verde breve no card + item "voa" para o carrinho).
* [ ] **Filtro por categoria:** Chips horizontais com rolagem para filtrar por categoria de produto (frutas, verduras, legumes, temperos, etc.).
* [ ] **Atalhos de teclado visíveis:** Barra fixa na base do painel com os atalhos ativos: `F1` Busca · `F2` Sangria · `F3` Abrir Caixa · `F4` Fechar Caixa · `ESC` Limpar busca.

#### 21.3 Painel do carrinho (direita)

* [ ] **Lista de itens do carrinho:** Cada item: nome, quantidade (editável inline com +/−), preço unitário, subtotal. Botão ❌ para remover. Scroll interno quando a lista excede a altura disponível.
* [ ] **Totalizador fixo na base do painel direito:** Subtotal, desconto (se aplicado), **Total** em fonte grande e destaque verde. Não some ao scrollar a lista.
* [ ] **Fluxo de pagamento em modal:** Ao clicar "Finalizar Venda", abre modal em 3 etapas: (1) forma de pagamento (dinheiro / débito / crédito / Pix / pontos); (2) valor recebido + troco calculado em tempo real (apenas dinheiro); (3) confirmação + impressão do comprovante via QZ Tray. Navegação entre etapas por teclado (Tab / Enter).
* [ ] **Feedback visual de venda concluída:** Após confirmar, o carrinho é limpo com animação, exibe "✅ Venda realizada!" por 2 segundos e retorna ao estado inicial pronto para o próximo cliente.

#### 21.4 Estado offline

* [ ] **Indicador de modo offline:** Ícone e texto "⚡ Offline" no header quando sem conexão. Fundo do header muda para laranja âmbar para alerta visual imediato.
* [ ] **Operação offline preservada:** Vendas continuam sendo registradas no IndexedDB. Badge com contador de "X vendas pendentes de sincronização" aparece quando reconectar.

---

### Categoria 22: Modo Restrito (Inadimplência)

> Funcionalidade exclusiva do `dev_admin`. Distinto do "Stand By Brando" (Cat. 14) que exibe apenas um banner informativo. O Modo Restrito desativa silenciosamente endpoints e módulos específicos, mantendo a operação básica do negócio intacta.

---

#### 22.1 Ativação

* [ ] **Flag em `configuracoes`:** Chaves adicionadas: `modo_restrito` (0/1) e `motivo_restricao` (texto livre exibido ao usuário — ex: "Serviço suspenso temporariamente. Entre em contato: suporte@desffrut.com.br").
* [ ] **Interface dev_admin:** Em `views/dev/index.php`, novo toggle "🔒 Modo Restrito" com campo de texto para o motivo. Ao ativar, exige confirmação explícita ("Digite RESTRITO para confirmar"). Log registrado na auditoria com timestamp.

#### 22.2 Funcionalidades bloqueadas (HTTP 402 na API, módulo oculto na view)

| Módulo | Endpoint bloqueado | View afetada |
|--------|-------------------|--------------|
| BI / Gráficos | `GET /api/v1/bi/*` | `fragmentos/bi.php` |
| DRE | `GET /api/v1/bi/dre` | `fragmentos/bi.php` |
| Relatório de Estoque | `GET /api/v1/estoque/relatorio` | `fragmentos/estoque.php` |
| Histórico de Pontos | `GET /api/v1/pontos/historico` | `views/cliente/perfil.php` (seção pontos) |
| Exportação de dados | `GET /api/v1/*/export` | Botões de export em todos os fragmentos |
| Auditoria | `GET /api/v1/auditoria/*` | `fragmentos/auditoria.php` |
| Folha de Pagamento | `GET /api/v1/ponto/folha` | `fragmentos/rh.php` (aba Folha) |

#### 22.3 Funcionalidades que CONTINUAM operando

PDV completo, catálogo público, checkout de pedidos, rastreamento de entrega, login de todos os usuários, gestão de estoque básica (entrada/saída), abertura e fechamento de caixa.

#### 22.4 Experiência do usuário bloqueado

* [ ] **Card de módulo restrito:** Quando um módulo bloqueado é acessado, em vez de erro técnico, exibe card centralizado: `🔒 Função temporariamente indisponível` + texto do `motivo_restricao` do CMS + link de contato. Estilo neutro, sem referência a inadimplência.
* [ ] **Middleware de verificação:** Função `modo_restrito_ativo()` em `functions.php` — consulta `configuracoes` com cache de 60 segundos (evita query em toda requisição). Os endpoints bloqueados chamam essa função antes de processar.

---

## 🚫 8. FORA DO ESCOPO — V1.0 (PREVISTO PARA VERSÕES FUTURAS)

Os itens abaixo foram conscientemente excluídos do escopo desta versão para garantir a entrega do produto principal:

| Item | Motivo da Exclusão | Previsão |
|------|--------------------|----------|
| **NFC-e / SAT Fiscal** | Requer certificado digital, integração SEFAZ e homologação por UF | V2.0 (pós-conclusão do projeto) |
| **Vale-Alimentação / TEF** | Requer terminal de cartão dedicado e integração TEF (hardware específico) | V2.0 |
| **Pix com QR Code automático** | Requer integração com gateway de pagamento (custo por transação) | V2.0 |
| **App Nativo (iOS/Android)** | Escopo focado em PWA e web responsiva | V3.0 |
