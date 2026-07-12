# 📊 STATUS DE DESENVOLVIMENTO — DESFFRUT

> Arquivo mantido automaticamente durante o desenvolvimento. Atualizado ao fim de cada fase ou entregável relevante.

---

## Legenda
- ✅ Concluído e homologado
- 🔄 Em andamento
- ⏳ Aguardando início
- ❓ Aguardando decisão do PO

---

## FASE 0 — Setup & Infraestrutura

| Item | Status | Observação |
|------|--------|------------|
| Leitura e alinhamento com briefing.md | ✅ | Briefing v2.0 absorvido integralmente |
| Estrutura de pastas desenhada | ✅ | Árvore de diretórios proposta e validada |
| Modelagem SQL completa (16 tabelas) | ✅ | Schema proposto cobre todas as 7 fases |
| Criação física das pastas e arquivos | ✅ | 37 arquivos criados cobrindo todas as fases |
| `.htaccess` (rewrite + proteção) | ✅ | Clean URLs, bloqueio de diretórios internos, HTTPS toggle |
| `.gitignore` | ✅ | Credenciais, uploads e logs ignorados |
| Camada `/api/` com roteador base | ✅ | `api/index.php` + `api/auth.php` + 6 endpoints stub em `api/v1/` |
| Pipeline de deploy (GitHub → HostGator) | ⏳ | Pendente: configurar FTP/SSH ou GitHub Actions (requer acesso ao cPanel) |

### Pendências / Decisões abertas

| ID | Ponto | Status |
|----|-------|--------|
| A | Tamanho máximo e redimensionamento de fotos de produtos | ✅ Upload até 6 MB (JPG/PNG/GIF/WEBP) → conversão automática para WebP ≤ 90 KB. Original descartado. |
| B | Regra de pontuação do programa de fidelidade (conversão R$ → pontos) | ✅ R$ 1,00 = 1 ponto. 100 pontos = R$ 1,00 de desconto/crédito. Estorno automático em cancelamentos. |

---

## FASE 1 — Fundação: DB, SSO & API ✅

| Item | Status | Observação |
|------|--------|------------|
| 1.1 Arquitetura de pastas | ✅ | Concluído na Fase 0 |
| 1.2 Modelagem Relacional MySQL | ✅ | `database/schema.sql` (16 tabelas) + `database/seed.sql` (dados de teste) |
| 1.3 Tela de Login Unificada (SSO) | ✅ | `login.php` com fetch JS + `cadastro.php` com LGPD |
| 1.4 Motor de Redirecionamento PHP | ✅ | `api/v1/auth.php` retorna `redirect` por role; JS redireciona |
| 1.5 Middlewares de Segurança | ✅ | `app/middleware/auth_check.php` aplicado em todas as views |
## FASE 2 — Portal Público & Área do Cliente ✅

| Item | Status | Observação |
|------|--------|------------|
| 2.1 Seletor de Filial Ativa | ✅ | `catalogo.js` — select na navbar com persistência em `localStorage` |
| 2.2 Catálogo Digital Aberto | ✅ | `index.php` Bootstrap 5, grid responsivo, filtro por categoria e busca live |
| 2.3 Sacola de Compras de Visitante | ✅ | `sacola.js` — localStorage, merge ao trocar loja, badge de contagem |
| 2.4 Trava de Finalização | ✅ | `sacola.finalizar()` redireciona para `/login` se não autenticado |
| 2.5 Painel Meu Perfil (Cliente) | ✅ | `views/cliente/perfil.php` — pontos, histórico, exclusão LGPD |
| APIs de suporte | ✅ | `api/v1/lojas.php`, `api/v1/produtos.php` (catalogo), `api/v1/clientes.php` (pontos, compras, excluir) |
| Models | ✅ | `app/models/Loja.php`, `app/models/Produto.php` |
| Seed de produtos | ✅ | `database/seed_produtos.sql` — 18 produtos com preços e estoque na Loja 1 |
| Layout compartilhado | ✅ | `app/views/layout/header.php` + `footer.php` com Bootstrap 5 e logout |
| Dashboard Admin/Gerência | ✅ | Stubs atualizados com header/footer e menu de navegação |

### Como testar a Fase 2:
1. Execute `database/seed_produtos.sql` no phpMyAdmin
2. Acesse `http://localhost/desffrut.com/`
3. Selecione a Loja 1 → produtos aparecem com preços e promoções
4. Use filtros de categoria e busca
5. Adicione produtos à sacola → clique em "Finalizar" sem estar logado → redireciona para login

## FASE 3 — Retaguarda Comercial ✅

| Item | Status | Observação |
|------|--------|------------|
| 3.1 Model Produto CRUD completo | ✅ | `app/models/Produto.php` — criar, atualizar, toggle ativo, preços por loja, estoque crítico |
| 3.1 API Produtos (GET/POST/PUT/PATCH) | ✅ | `api/v1/produtos.php` — catálogo público + CRUD autenticado + `/preco` por loja |
| 3.1 API Uploads de foto | ✅ | `api/v1/uploads.php` — aceita até 6 MB, converte para WebP ≤ 90 KB via GD |
| 3.2 Toggle ativo sem apagar histórico | ✅ | `PATCH /api/v1/produtos/{id}` — `UPDATE SET ativo = 1 - ativo` |
| 3.3 Agendamento de promoções | ✅ | `promo_inicio`/`promo_fim` via UI com `datetime-local`; SQL aplica automaticamente com `NOW() BETWEEN` |
| 3.4 API Estoque | ✅ | `api/v1/estoque.php` — inventário por loja, ajuste quantidade, endpoint `/critico` |
| 3.4 API Quebras | ✅ | `POST /api/v1/estoque/quebra` — registra e deduz do estoque em transação atômica |
| 3.5 View Gestão de Produtos | ✅ | `views/gerencia/produtos.php` — tabela com filtros, modal criar/editar, aba de preços e promoções |
| 3.5 View Gestão de Estoque | ✅ | `views/gerencia/estoque.php` — inventário por loja, ajuste inline, modal quebras |
| 3.5 Hub de Relatórios | ✅ | `views/gerencia/relatorios.php` — cards de acesso com status por fase |
| 3.5 Relatório Estoque Crítico | ✅ | `views/gerencia/relatorio-estoque-critico.php` — imprimível, quantidade sugerida editável |
| `.htaccess` rotas adicionadas (retaguarda) | ✅ | `gerencia/produtos`, `gerencia/relatorio-estoque-critico` |
| **3.6 Dashboard Unificado com Sidebar** | | |
| Especificação de módulos | ✅ | `briefings/modulos_dashboard.md` — 8 módulos, roles, abas e fases detalhados |
| Shell do dashboard | ✅ | `views/dashboard/index.php` — sidebar Bootstrap accordion, AJAX fragment loader, localStorage state, badge crítico polling 60s, responsivo mobile |
| Router de fragmentos seguro | ✅ | `views/dashboard/fragmento.php` — whitelist + validação de role + 401/403/404 |
| Fragmento Produtos | ✅ | `views/dashboard/fragmentos/produtos.php` — CRUD inline (window.produtosUI) |
| Fragmento Estoque | ✅ | `views/dashboard/fragmentos/estoque.php` — inventário + ajuste + quebras (window.estoqueUI) |
| Fragmento Quebras | ✅ | `views/dashboard/fragmentos/quebras.php` — histórico filtrado + registro (window.quebrasUI) |
| Fragmento Relatórios | ✅ | `views/dashboard/fragmentos/relatorios.php` — hub de cards, Estoque Crítico abre nova aba |
| Fragmento Placeholder | ✅ | `views/dashboard/fragmentos/placeholder.php` — card "Em desenvolvimento — Fase X" |
| API `GET /estoque/quebras` | ✅ | Filtros por loja e período, role gerente força própria loja |
| Redirects legados | ✅ | `gerencia/dashboard` e `admin/dashboard` → `/dashboard` imediato |
| Login unificado | ✅ | Todos os roles operacionais → `/dashboard` (auth.php + login.php) |
| `.htaccess` rotas dashboard | ✅ | `dashboard/` e `dashboard/fragmento/` adicionadas |

### Como testar a Fase 3 (retaguarda + dashboard):

1. Acesse `/login` como **gerente** → deve ir direto para `/dashboard`
2. Sidebar verde à esquerda com módulo **Produtos & Estoque** aberto automaticamente
3. Clique aba **Produtos** → tabela carrega via AJAX, crie um produto com foto e promoção
4. Clique aba **Estoque** → ajuste quantidade, registre uma quebra de teste
5. Clique aba **Quebras** → histórico dos últimos 30 dias
6. Clique módulo **Relatórios → Estoque Crítico** → abre nova aba para impressão
7. Acesse `/login` como **caixa** → dashboard com módulo Pedidos (placeholder fase 6)
8. Acesse `/login` como **entregador** → dashboard com módulo Pedidos filtrado
9. No mobile: hamburguer aparece, sidebar desliza sobre a tela

### Dependências de banco (já no schema.sql):
- `quebras` com `usuario_id` — ✅ presente desde a Fase 0
- `precos` com `promo_inicio`, `promo_fim` — ✅ presente desde a Fase 0

## FASE 4 — PDV Híbrido Offline ✅

| Item | Status | Observação |
|------|--------|------------|
| **4.1 API Caixas** | ✅ | `api/v1/caixas.php` — GET caixa aberto, POST abrir/fechar, POST sangria/suprimento, GET fechamentos e sangrias |
| **4.2 API Vendas** | ✅ | `api/v1/vendas.php` — POST registrar (online + idempotência por UUID), GET histórico, PUT cancelamento com senha gerente + estorno pontos |
| **4.3 API Sync** | ✅ | `api/v1/sync.php` — GET /sync/carga (snapshot produtos+preços+clientes) + POST /sync/upload (cupons offline, política Seção 3.3) |
| **4.4 IndexedDB** | ✅ | `public/js/pdv/indexeddb.js` — stores: produtos, clientes, vendas; busca por EAN e nome; UUID v4 offline |
| **4.5 Background Sync** | ✅ | `public/js/pdv/sync.js` — carregarCarga, sincronizar, polling 30s, detecção de reconexão, eventos customizados |
| **4.6 Interface PDV (JS)** | ✅ | `public/js/pdv/pdv.js` — busca de produto, sacola, cliente por CPF, modal pagamento, troco, atalhos F1-F4/ESC |
| **4.7 View Abertura** | ✅ | `views/pdv/abertura.php` — fundo de troco, verifica caixa já aberto, redireciona para PDV |
| **4.8 View PDV (frente de caixa)** | ✅ | `views/pdv/index.php` — layout dark mode, carrinho, totais, modal pagamento, modal fechar caixa |
| **4.9 View Sangria** | ✅ | `views/pdv/sangria.php` — sangria/suprimento, justificativa obrigatória, histórico do turno |
| **4.10 CSS PDV** | ✅ | `public/css/pdv.css` — dark mode, otimizado para tela cheia, scrollbars customizadas |
| **4.11 Fragmento Caixa** | ✅ | `views/dashboard/fragmentos/caixa.php` — Fechamentos do Dia + Sangrias & Suprimentos; view-only para gerente/rh_financeiro |
| **4.12 Fragmento CEASA** | ✅ | `views/dashboard/fragmentos/ceasa.php` — Lista de Compra (estoque crítico), Recebimento, Distribuição, Rota (drag-to-reorder) |
| **4.13 Dashboard integrado** | ✅ | `fragmento.php` + `index.php` — abas CEASA e Caixa apontam para fragmentos reais (fase removida) |

### Como testar a Fase 4:

**PDV:**
1. Acesse `/pdv/abertura` como **caixa** → preencha fundo de troco → Caixa aberto
2. Acesse `/pdv` → busque produto por EAN ou nome (F1) → adicione itens
3. F2 → modal de pagamento → selecione forma → confirme → cupom aparece
4. F4 → sangria → preencha valor + justificativa → confirmar
5. Desconecte o Wi-Fi → continue vendendo → reconecte → sync automático

**Dashboard:**
1. Acesse `/dashboard` como **gerente** → sidebar mostra módulo **Compras CEASA**
2. Clique **Lista de Compra** → produtos abaixo do mínimo aparecem com qtd sugerida
3. Clique **Recebimento** → selecione produto + quantidade → confirmar (atualiza estoque)
4. Clique **Caixa** (gerente vê como somente leitura) → Fechamentos do Dia → filtrar período
5. Acesse como **super_admin** → módulo Caixa mostrará todas as lojas com opção de edição futura

### Dependências de banco (já no schema.sql):
- `caixas`, `movimentos_caixa`, `vendas`, `itens_venda` — ✅ presentes desde Fase 0

## FASE 5 — Hardware Local ✅

| Item | Status | Observação |
|------|--------|------------|
| **5.1 DesffrHardware (hardware.js)** | ✅ | Módulo IIFE: QZ Tray ESC/POS (80mm/58mm) + Web Serial API balança Toledo/Filizola RS-232 |
| **5.2 Impressão cupom de venda** | ✅ | Automática após evento `desffrut:venda:concluida`; botão Reimprimir no painel esquerdo |
| **5.3 Impressão comprovante sangria** | ✅ | `imprimirSangria()` chamado após confirmar sangria/suprimento |
| **5.4 Impressão fechamento de caixa** | ✅ | `imprimirFechamento()` disparado em `confirmarFechamento()` antes do redirect |
| **5.5 Botão balança no modal de peso** | ✅ | Botão ⚖️ no modal de quantidade (kg); captura peso via Web Serial |
| **5.6 Fragmento Hardware no dashboard** | ✅ | `views/dashboard/fragmentos/hardware.php` — config impressora, detecção via QZ Tray, diagnóstico, teste balança |
| **5.7 Sidebar + whitelist** | ✅ | Módulo Hardware visível para super_admin e gerente; fragmento.php atualizado |
| **5.8 Leitor de Código de Barras / QR Code** | ✅ | USB HID: plug & play (emula teclado → funciona automaticamente no PDV). Câmera: BarcodeDetector API (Chrome/Edge 83+), botão 📷 no campo de busca e atalho F5. Formatos: EAN-13, EAN-8, UPC-A, QR Code, Code-128 e outros. Seção de teste no Dashboard → Hardware. |

### Como testar a Fase 5:

**Impressão:**
1. Instale o QZ Tray: https://qz.io/download/ e inicie o serviço
2. Acesse `/dashboard` como **gerente** → módulo **Hardware** → aba **Impressora & Balança**
3. Conecte QZ Tray → detecte impressoras → selecione a sua → escolha 80mm ou 58mm → **Salvar**
4. Clique **Imprimir Teste** para validar
5. Vá ao PDV → finalize uma venda → cupom imprime automaticamente
6. Botão 🖨️ Reimprimir aparece no painel esquerdo após cada venda

**Balança:**
1. Conecte a balança via cabo serial RS-232 (ou adaptador USB-Serial)
2. Dashboard → Hardware → **Selecionar Porta** → autorize no dialog do Chrome
3. Clique **Ler Peso** para testar
4. No PDV, busque produto vendido por kg → botão ⚖️ no modal captura peso automaticamente

**Leitor de código de barras:**

*Modo USB HID (recomendado — zero configuração):*
1. Conecte o leitor USB ao computador do caixa
2. Abra o PDV → clique no campo de busca (F1)
3. Aponte o leitor para o código do produto → automático: preenche e busca

*Modo câmera (BarcodeDetector API):*
1. Abra o PDV → clique 📷 ou pressione **F5**
2. Autorize o acesso à câmera no dialog do Chrome
3. Aponte a câmera para o código de barras ou QR Code → detecção automática + beep
4. Modal fecha sozinho e o produto é adicionado ao carrinho

*Testar no Dashboard:*
1. Dashboard → Hardware → seção **Leitor de Código de Barras**
2. Clique **Abrir câmera** → aponte para qualquer código para validar

### Requisitos:
- Navegador: **Google Chrome ou Edge** (Firefox não suporta Web Serial API)
- QZ Tray instalado e ativo no computador do caixa
- Produção: configurar certificado RSA — ver https://qz.io/wiki/2.0-signing-messages

## FASE 6 — Tele-Entrega & Logística ✅

| Item | Status | Observação |
|------|--------|------------|
| **6.1 Migration banco** | ✅ | `04-migration_fase6_pedidos.sql` — `forma_pagamento`, `troco_para`, campos de endereço em `pedidos`; campos de endereço em `usuarios` |
| **6.2 API Pedidos** | ✅ | `api/v1/pedidos.php` — GET (lista por status), POST (criar), GET/:id (detalhes + polling), PATCH/:id (status + entregador). Valida preços no servidor. Crédita pontos ao entregar. |
| **6.3 API Entregadores** | ✅ | `api/v1/entregadores.php` — lista entregadores ativos com contagem de pedidos em rota |
| **6.4 Checkout** | ✅ | `views/checkout/index.php` — sacola resumida, selecionar filial, endereço de entrega (pré-preenchido do perfil), forma de pagamento (dinheiro/débito/crédito/pix), troco, obs. Opção de salvar endereço no perfil. |
| **6.5 Rastreamento cliente** | ✅ | `views/cliente/pedido_status.php` — timeline visual de 4 etapas, polling a cada 20s (sem WebSocket), links Google Maps/Waze quando em rota |
| **6.6 Fragmento Pedidos & Despacho** | ✅ | `views/dashboard/fragmentos/pedidos.php` — 4 tabs (Pendentes / Em Preparo / Em Rota / Histórico), cards de pedido com ações por role, modal de despacho com seleção de entregador, polling 20s |
| **6.7 Integração sacola → checkout** | ✅ | `sacola.js` `finalizar()` redireciona para `/checkout`; login.php respeita redirect pendente pós-login |
| **6.8 Dashboard sidebar + whitelist** | ✅ | Módulo "Pedidos & Despacho" ativo para gerente/caixa/entregador; `fragmento.php` atualizado |

### Como testar a Fase 6:

**Fluxo cliente:**
1. Acesse `/` como visitante → adicione produtos à sacola → clique "Finalizar" → redireciona para login
2. Faça login como **cliente** → redirecionado para `/checkout`
3. Selecione a filial, preencha o endereço, escolha forma de pagamento → **Fazer Pedido**
4. Redirecionado para `/pedidos/{id}/status` — timeline mostra "Pendente"
5. Timeline atualiza automaticamente a cada 20 segundos

**Fluxo caixa/gerente:**
1. Acesse `/dashboard` como **gerente** ou **caixa** → módulo **Pedidos & Despacho**
2. Aba **Pendentes** → card do pedido → botão **✅ Aceitar** → muda para Em Preparo
3. Aba **Em Preparo** → botão **🛵 Despachar** → modal seleciona entregador → confirmar
4. Aba **Em Rota** → link 📍 abre rota no Google Maps; link 🗺 abre no Waze

**Fluxo entregador:**
1. Acesse `/dashboard` como **entregador** → vê apenas aba **Em Rota**
2. Card com endereço + botão 📍 Maps + botão ✅ Entreguei
3. Ao confirmar entrega: pontos creditados ao cliente automaticamente

### Dependências de banco:
- Executar `database/04-migration_fase6_pedidos.sql` antes de testar

## FASE 7 — ERP Administrativo ✅

| Item | Status | Observação |
|------|--------|------------|
| **7.1 Migration banco** | ✅ | `05-migration_fase7_erp.sql` — campos em `funcionarios` (tipo_contrato, carga_horaria); novas tabelas `registro_ponto`, `folha_pagamento`; campos extras em `contas_pagar` |
| **7.2 API Funcionários (RH)** | ✅ | `api/v1/funcionarios.php` — GET lista, POST admitir, GET/:id, PUT editar, DELETE desligar (soft delete). Registra log em todas as ações. |
| **7.3 API Ponto/Jornada** | ✅ | `api/v1/ponto.php` — GET registros do mês, POST batimento (entrada/saída/intervalo), GET /resumo (banco de horas calculado), GET/POST /folha, PATCH /folha/{id} (marcar pago) |
| **7.4 API Contas a Pagar** | ✅ | `api/v1/contas_pagar.php` — GET (com filtros), GET /resumo (BI), POST lançar, PUT editar, PATCH marcar pago, DELETE excluir. Auto-atualiza status vencido. |
| **7.5 API BI / DRE** | ✅ | `api/v1/bi.php` — GET /faturamento, /despesas, /dre (DRE completo com CMV+EBITDA+IR), /top_produtos, /overview (KPIs do mês) |
| **7.6 API Auditoria** | ✅ | `api/v1/auditoria.php` — GET paginado (filtros por ação, tabela, período), GET /acoes (lista de ações distintas) |
| **7.7 Fragmento RH** | ✅ | `views/dashboard/fragmentos/rh.php` — 4 tabs: Funcionários (CRUD+admissão+desligamento), Ponto/Jornada (banco de horas), Folha de Pagamento (gerar+marcar pago), Baixas |
| **7.8 Fragmento Financeiro** | ✅ | `views/dashboard/fragmentos/financeiro.php` — tabs: Contas a Pagar (KPIs+filtros+lançar+marcar pago+excluir), Fluxo de Caixa (tabela mensal receita vs despesas) |
| **7.9 Fragmento BI** | ✅ | `views/dashboard/fragmentos/bi.php` — 4 tabs: Overview (KPIs), Gráficos Chart.js (faturamento empilhado + linha receita vs despesas), DRE simplificado, Top 10 Produtos |
| **7.10 Fragmento Auditoria** | ✅ | `views/dashboard/fragmentos/auditoria.php` — log paginado com filtros por ação/tabela/período, destaques por criticidade, exclusivo super_admin |
| **7.11 Integração dashboard** | ✅ | `fragmento.php` — whitelist atualizada (rh, financeiro, bi, auditoria); `index.php` — módulos RH e Financeiro apontam para fragmentos reais; Relatórios: BI/Gráficos + DRE ativos |

### Como testar a Fase 7:

**Pré-requisito:** executar `database/05-migration_fase7_erp.sql` no phpMyAdmin.

**RH:**
1. Acesse `/dashboard` como **super_admin** ou **rh_financeiro** → módulo **RH**
2. Aba Funcionários → botão **+ Admitir** → informe ID de usuário existente (e.g. ID 2), cargo, salário → confirmar
3. Aba **Ponto/Jornada** → selecione funcionário + mês → clique **+ Registrar Ponto** → tipo Entrada → hora atual
4. Repita com Saída → resumo mostra total de horas e banco de horas
5. Aba **Folha de Pagamento** → botão **+ Gerar Folha** → selecione funcionário + mês + valores → Salvar
6. Na linha da folha → botão **Marcar Pago**
7. Aba **Baixas** → mostra funcionários com demitido_em preenchido

**Financeiro:**
1. Módulo **Financeiro** → aba **Contas a Pagar**
2. Botão **+ Lançar Conta** → preencha aluguel, energia, etc. → Lançar
3. KPIs mostram pendente/vencido/pago em tempo real
4. Botão **✓ Pago** → status muda para pago
5. Aba **Fluxo de Caixa** → tabela mensal receita vs despesas + total acumulado

**BI / Relatórios:**
1. Módulo **Relatórios** → aba **BI / Gráficos**
2. 4 tabs: Overview com KPIs do mês atual
3. Aba Gráficos → barras empilhadas PDV+Delivery + linha Receita vs Despesas (Chart.js)
4. Aba DRE → tabela DRE com CMV, EBITDA, IR estimado 6%, Margem Líquida
5. Aba Top Produtos → top 10 por receita do mês

**Auditoria:**
1. Módulo **Administração → Auditoria** (super_admin apenas)
2. Lista todas as ações críticas: demissões, pagamentos, alterações de preço, sangrias
3. Filtrar por ação, tabela ou período

### Dependências de banco:
- Executar `database/05-migration_fase7_erp.sql`
- Tabelas `funcionarios` e `contas_pagar` já existem no schema base (novas colunas adicionadas pela migration)

---

## FASE 8 — CMS & Customização do Portal ✅

| Item | Status | Observação |
|------|--------|------------|
| **8.1 Tabela `configuracoes` + CSS custom props** | ✅ | `database/06-migration_fase8_cms.sql` — tabela key-value + chaves iniciais; CSS custom props (`--cor-primaria`, `--cor-secundaria`) injetadas pelo header.php via query PDO |
| **8.2 Gestor de Identidade Visual** | ✅ | `views/dashboard/fragmentos/cms_identidade.php` — upload de logo, campos nome/slogan, seletor de cores com preview ao vivo da navbar |
| **8.3 Migration `banners` + carrossel home** | ✅ | Tabela `banners` na migration; carrossel Bootstrap 5 inserido no `index.php`, detecta desktop/mobile, exibe apenas banners ativos dentro do período configurado |
| **8.4 Central de Banners** | ✅ | `views/dashboard/fragmentos/cms_banners.php` — tabs Desktop/Mobile/Novo, upload via api/v1/uploads, toggle ativo/inativo, remoção |
| **8.5 Painel de Detalhes das Lojas** | ✅ | `views/dashboard/fragmentos/cms_lojas.php` — edição inline de horário e WhatsApp por filial; `api/v1/lojas.php` estendido com PUT /{id} |
| **8.6 Migration `campanhas` + Campanhas Sazonais** | ✅ | Tabela `campanhas` na migration com ENUM cupom_global/tema_css; fragmento `cms_campanhas.php` com criação, ativação/desativação e exclusão |
| **8.7 API `configuracoes.php`** | ✅ | `api/v1/configuracoes.php` — GET/POST configuracoes; CRUD completo campanhas; CRUD banners (GET público em /banners-publicos) |
| **8.8 Integração dashboard** | ✅ | Módulo "CMS & Portal" 🎨 adicionado no sidebar (super_admin only); whitelist `fragmento.php` atualizada (cms_identidade, cms_banners, cms_lojas, cms_campanhas) |

---

## FASE 9 — Dev Mode & Operações de Sistema ✅

| Item | Status | Observação |
|------|--------|------------|
| **9.1 Role `dev_admin` + rota `/dev`** | ✅ | `07-migration_fase9_dev.sql` — ENUM atualizado; `app/middleware/dev_auth.php` — bloqueia 403 genérico para qualquer role ≠ dev_admin; `.htaccess` → `RewriteRule ^dev/?$` |
| **9.2 Modo Manutenção** | ✅ | Flag `manutencao_ativa` em `configuracoes`; `app/middleware/maintenance_check.php` incluído em `index.php`; roles operacionais isentos; tela customizada 503 |
| **9.3 Aviso de Inadimplência (banner fixo)** | ✅ | Flag `inadimplencia_ativa` em `configuracoes`; banner vermelho fixo renderizado no topo de `views/dashboard/index.php` — operação não interrompida |
| **9.4 Reset Forçado de Senha** | ✅ | `POST /api/v1/dev/reset-senha` — senha aleatória 12 chars, bcrypt, `trocar_senha_prox_login=1`, `mail()` ao titular; sem armazenar texto limpo |
| **9.5 Auditoria Forense Ampliada** | ✅ | Coluna `ip` garantida em `logs_auditoria`; `GET /api/v1/dev/auditoria` com filtros ip/acao/período; tabela imutável (sem DELETE); painel visual com badge de ações |
| **9.6 Painel Dev Mode** | ✅ | `views/dev/index.php` — layout dark isolado; status do sistema, toggles manutenção/inadimplência, reset de senha com busca de usuário, auditoria forense paginada |

### Como ativar o dev_admin:
1. Execute `database/07-migration_fase9_dev.sql` no phpMyAdmin
2. Altere o role de um usuário existente: `UPDATE usuarios SET role = 'dev_admin' WHERE id = {ID};`
3. Acesse `/dev` com esse usuário logado
4. Qualquer outro role → 403 genérico (a rota não é revelada)

### Dependências de banco:
- Executar `database/07-migration_fase9_dev.sql`

---

## FASE 10 — Prospecção & CRM Kanban ✅

| Item | Status | Observação |
|------|--------|------------|
| **10.1 Migration `leads`** | ✅ | `database/08-migration_fase10_crm.sql` — tabela `leads` (nome, telefone UNIQUE, email, bairro, empresa, mensagem, origem ENUM, fase ENUM 6 estágios, atribuido_a) |
| **10.2 Formulário Parcerias Público (`/parcerias`)** | ✅ | `views/parcerias/index.php` — hero + benefits + form AJAX; rota pública `leads/novo` em `api/index.php` |
| **10.3 API `leads.php`** | ✅ | POST público /leads/novo; GET /leads (filtro fase/q); GET/:id; PATCH/:id (fase, temperatura, valor_estimado, loja_id); DELETE/:id (super_admin); POST /leads/importar (CSV drag-drop, INSERT IGNORE, auto-detect separator) |
| **10.4 Pipeline CRM Kanban** | ✅ | `views/dashboard/fragmentos/crm.php` — "Funil de Parcerias"; Sortable.js carregado dinamicamente (contorna limitação do fragment loader); cards compactos por padrão (toggle ›) |
| **10.5 Importador CSV Genérico** | ✅ | Drag-drop + click, auto-detecta separador `;` ou `,`, mapeamento flexível de colunas, relatório: N inseridos / Y ignorados |
| **10.6 Disparador WhatsApp** | ✅ | Botão WA em cada card; mensagem pré-configurada por fase do funil (6 templates: novo/contato/proposta/negociação/fechado/perdido) |
| **10.7 Integração dashboard** | ✅ | Módulo 🤝 "Prospecção & CRM" no sidebar (super_admin, gerente); whitelist fragmento.php atualizada |
| **CRM v2 — Melhorias** | ✅ | Migration `09-migration_crm_v2.sql` — colunas temperatura ENUM(frio/morno/quente), valor_estimado, loja_id; temperatura badges clicáveis (ciclo frio→morno→quente) com update otimista; sum financeiro por coluna; barra de resumo (ativos, pipeline, taxa conversão, leads quentes); indicador de aging (dias) |

### Dependências de banco:
- Executar `database/08-migration_fase10_crm.sql`
- Executar `database/09-migration_crm_v2.sql` (para temperatura/valor/loja)

---

## FASE 11 — WhatsApp Híbrido ✅

| Item | Status | Observação |
|------|--------|------------|
| **11.1 Migration status + token** | ✅ | `database/10-migration_fase11_whatsapp.sql` — status ENUM adiciona `aguardando_validacao`; colunas `canal_origem`, `wa_token` CHAR(36) UNIQUE, `wa_token_expira_em`, `wa_token_usado` em `pedidos` |
| **11.2 Checkout Mobile Otimizado** | ✅ | `views/checkout/index.php` — botão "Enviar via WhatsApp" verde (#25d366); mobile CSS: font-size:16px (anti-zoom iOS), min-height:44px touch targets, media query 576px |
| **11.3 Gerador de Mensagem** | ✅ | `api/v1/pedidos.php` helper `_gerar_msg_wa()` — POST aceita `canal_origem:'whatsapp'`; retorna `wa_url` = `wa.me/55{tel_loja}?text={msg_tabulada}` com itens, totais, endereço e link de validação |
| **11.4 URL de Validação em 1 Clique** | ✅ | UUID v4 gerado no POST; `views/pedidos/validar.php` — página pública mobile-friendly; valida token (existe/expirado/já_usado); muda status → `preparando`; `.htaccess`: `^pedidos/validar/([a-f0-9\-]+)` |
| **11.5 Notificações de Status para Cliente** | ✅ | `views/dashboard/fragmentos/pedidos.php` — botões `📲 Avisar` (preparando) e `📲 Em Rota` (saiu_para_entrega) abrem `wa.me/55{tel_cliente}?text=`; helper `_waClienteUrl()` |
| **11.6 Aba Via WA no Dashboard** | ✅ | Tab "📲 Via WA"; badge CSS `aguardando_validacao`; `_labelStatus()` atualizado; PATCH transitions incluem `aguardando_validacao→preparando|cancelado` |

### Fluxo WhatsApp:
1. Cliente clica "Enviar via WhatsApp" no checkout → pedido criado com `aguardando_validacao` + token 24h
2. WhatsApp abre com mensagem tabulada + link de validação no fim
3. Loja recebe a mensagem → clica no link → status → `preparando` (token invalidado)
4. Dashboard detecta via polling → impressão QZ Tray automática
5. Staff clica "📲 Avisar" → notifica cliente que está em preparo

### Dependências de banco:
- Executar `database/10-migration_fase11_whatsapp.sql`

---

## FASE 12 — Marketing & AdTech ✅

| Item | Status | Observação |
|------|--------|------------|
| **12.1 Meta Pixel** | ✅ | Injetado dinamicamente no `<head>` via `configuracoes.pixel_meta_id`; `fbq('track','PageView')` em todas as páginas; noscript fallback |
| **12.2 Google Analytics 4** | ✅ | `gtag.js` condicional via `configuracoes.gtag_id`; ambos desativados por padrão (campo vazio) |
| **12.3 Helper `trackEvent()`** | ✅ | `window.trackEvent(nome, dados)` disponível globalmente via APP object — dispara fbq + gtag simultaneamente se configurados |
| **12.4 Rastreamento de UTMs** | ✅ | `app/views/layout/header.php` captura `?utm_*` → `$_SESSION['utm']` (sobrescreve a cada novo clique); `api/v1/pedidos.php` salva `origem_utm JSON` no POST |
| **12.5 Relatório ROI por Campanha** | ✅ | `GET /api/v1/adtech/roi` — agrupa por utm_source/medium/campaign, retorna pedidos+receita+ticket; tabela visual com barras proporcionais no fragmento |
| **12.6 UTM Builder + Deep Link Meta** | ✅ | `views/dashboard/fragmentos/adtech.php` aba "Criar Anúncio" — gerador de URL com UTM params + botão → Meta Ads Manager |
| **12.7 Motor "Indique e Ganhe"** | ✅ | `migration`: `codigo_indicacao CHAR(10)`, `indicado_por_id`, `indicacao_bonus_pago` em usuarios; `GET /adtech/meu-codigo` gera código único 8 chars; `cadastro.php` captura `?ref=CODE`; `api/v1/auth.php` registrar salva `indicado_por_id`; bônus automático (configurável via `pontos_indicacao`) ao 1º pedido `entregue` do indicado |
| **12.8 Painel Meu Perfil — Indique e Ganhe** | ✅ | `views/cliente/perfil.php` — card com código visual 2rem, link copiável, botão WA share, stats (indicados/bônus pagos/pts por indicação) |
| **12.9 Integração dashboard** | ✅ | Módulo 📡 "Marketing" no sidebar (super_admin, gerente); fragmento `adtech.php` com 4 abas: Pixels & Tags (config+badge ativo/inativo), ROI por Campanha, Criar Anúncio, Indique e Ganhe (ranking top 20) |
| **12.10 API `adtech.php`** | ✅ | GET /roi, GET /indicacoes, GET /meu-codigo, POST /config — descoberto automaticamente pelo roteador |

### Como ativar:
1. Executar `database/11-migration_fase12_adtech.sql`
2. Dashboard → Marketing → Pixels & Tags → inserir Meta Pixel ID e/ou GA4 Measurement ID → Salvar
3. Para testar UTMs: acesse `/?utm_source=teste&utm_campaign=fase12` → faça um pedido → ROI por Campanha mostrará os dados

### Dependências de banco:
- Executar `database/11-migration_fase12_adtech.sql`
- MySQL 5.7+ obrigatório para coluna JSON

---

## ✅ CATEGORIAS 15 E 16 — Frontend Público Complementar + Manual de Equipe

> Implementadas em 2026-06-24.

### Categoria 15 — Frontend Público Complementar

| Página / Arquivo | Rota | Status |
|-----------------|------|--------|
| Termos de Uso | `/termos` → `termos.php` | ✅ Concluído |
| Política de Privacidade (revisão LGPD hortifruti) | `/privacidade` → `privacidade.php` | ✅ Concluído |
| Página de erro 404 personalizada | `ErrorDocument 404` → `views/errors/404.php` | ✅ Concluído |
| Tela de manutenção | `/manutencao` → `views/manutencao.php` | ✅ Concluído |
| `robots.txt` | `/robots.txt` | ✅ Concluído |
| `sitemap.xml` | `/sitemap.php` (dinâmico) | ✅ Concluído |
| Nossas Lojas | `/lojas` → `views/public/lojas.php` | ✅ Concluído |
| Meta Tags OG + manifest link | `app/views/layout/header.php` | ✅ Concluído |
| Link /termos no rodapé | `app/views/layout/footer.php` | ✅ Concluído |
| Quem Somos | `/sobre` → `views/public/sobre.php` | ✅ Concluído |
| Programa de Pontos | `/fidelidade` → `views/public/fidelidade.php` | ✅ Concluído |
| PWA Manifest | `/manifest.json` | ✅ Concluído |
| Rotas no `.htaccess` | lojas, sobre, fidelidade, termos, manutencao, manual, sitemap.xml, 404 | ✅ Concluído |

### Categoria 16 — Manual de Uso da Equipe

| Item | Arquivo | Status |
|------|---------|--------|
| Página `/manual` com accordion Bootstrap | `views/manual/index.php` | ✅ Concluído |
| Seção: Dono / Super Admin | inline | ✅ Concluído |
| Seção: Gerente | inline | ✅ Concluído |
| Seção: RH / Financeiro | inline | ✅ Concluído |
| Seção: Caixa (PDV) — passo a passo completo com atalhos | inline | ✅ Concluído |
| Seção: Entregador | inline | ✅ Concluído |
| Seção: FAQ de Operação (7 perguntas) | inline | ✅ Concluído |

**Nota PWA:** o `manifest.json` referencia ícones em `uploads/logos/icon-192.png` e `icon-512.png` que precisam ser gerados a partir do logo do sistema (tarefa do CMS → Identidade Visual).

---

## 🗂️ ORDEM DE EXECUÇÃO DAS MIGRATIONS

Execute na sequência abaixo antes de testar qualquer fase:

```
database/00-schema.sql                    ← schema base (16 tabelas)
database/01-seed.sql                      ← usuários e lojas de teste
database/02-seed_produtos.sql             ← 18 produtos Loja 1
database/03-migration_fase2_perfil.sql    ← campos de perfil em usuarios
database/04-migration_fase6_pedidos.sql   ← endereço e pagamento em pedidos
database/05-migration_fase7_erp.sql       ← funcionarios, ponto, contas_pagar
database/06-migration_fase8_cms.sql       ← configuracoes, banners, campanhas
database/07-migration_fase9_dev.sql       ← ENUM role + dev_admin
database/08-migration_fase10_crm.sql      ← tabela leads
database/09-migration_crm_v2.sql          ← temperatura, valor_estimado, loja_id em leads
database/10-migration_fase11_whatsapp.sql ← aguardando_validacao, wa_token em pedidos
database/11-migration_fase12_adtech.sql   ← origem_utm, codigo_indicacao, indicado_por_id
```

---

## ✅ CATEGORIA 17 — Navegação, SEO e Roteamento de Erros

> Implementada em 2026-06-24.

### 17.1 Navegação global (header.php)

| Item | Status | Detalhe |
|------|--------|---------|
| Links públicos no navbar | ✅ | Catálogo, Nossas Lojas, Quem Somos, Fidelidade, Parcerias |
| Variável `$nav_ativa` | ✅ | Link ativo highlighted com classe `active` |
| Variável `$mostrar_nav_publica` | ✅ | Padrão `true`; desativar em páginas internas se necessário |

### 17.2 Rodapé 4 colunas (footer.php)

| Coluna | Links | Status |
|--------|-------|--------|
| Navegação | Catálogo, Nossas Lojas, Quem Somos, Fidelidade, Parcerias | ✅ |
| Minha Conta | Criar conta, Entrar, Meu Perfil, Meus Pedidos | ✅ |
| Institucional | Política de Privacidade, Termos de Uso | ✅ |
| Área Interna | Acesso da Equipe, Manual de Uso | ✅ |

### 17.3 Roteamento de erro 404

| Item | Status | Detalhe |
|------|--------|---------|
| `404.php` na raiz | ✅ | Wrapper que inclui `views/errors/404.php` |
| `ErrorDocument 404 /404.php` | ✅ | Funciona em produção (HostGator) |
| Catch-all RewriteRule | ✅ | Captura rotas não mapeadas em XAMPP e produção |

### 17.4 SEO por página

| Página | `<title>` | `robots` | JSON-LD | `$canonical_url` | `$nav_ativa` |
|--------|-----------|----------|---------|-----------------|--------------|
| `/` | Frutas, Verduras e Legumes Frescos | index,follow | FoodEstablishment | ✅ | catalogo |
| `/lojas` | Nossas Lojas | index,follow | LocalBusiness por filial | ✅ | lojas |
| `/sobre` | Quem Somos | index,follow | Organization | ✅ | sobre |
| `/fidelidade` | Programa de Fidelidade | index,follow | — | ✅ | fidelidade |
| `/parcerias` | Seja Nosso Parceiro | index,follow | — | ✅ | parcerias |
| `/termos` | Termos de Uso | noindex,follow | — | ✅ | — |
| `/privacidade` | Política de Privacidade | noindex,follow | — | ✅ | — |
| `/manual` | Manual de Uso — Equipe | noindex,nofollow | — | ✅ | — |
| `/404` | Página não encontrada | noindex,nofollow | — | ✅ | — |

**Tags adicionadas ao `header.php`:**
- `<meta name="robots">` — via variável `$robots` (padrão: `index,follow`)
- `<link rel="canonical">` — via variável `$canonical_url`
- `<meta name="twitter:card">` — Twitter Card com large image
- `<script type="application/ld+json">` — via variável `$json_ld`

---

---

## ✅ EXPANSÃO DE LOJAS — Gestão pelo Super Admin

> Implementada em 2026-06-25.

| Item | Arquivo | Status |
|------|---------|--------|
| Loja 3 + 6 novos usuários seed | `database/01-seed.sql` | ✅ Concluído |
| CRUD de lojas exclusivo super_admin | `views/admin/lojas.php` | ✅ Concluído |
| Proteção contra exclusão de loja com vínculos | `api/v1/lojas.php` (DELETE com CHECK 409) | ✅ Concluído |
| Toggle ativo/inativo por loja | `api/v1/lojas.php` (PATCH ativo) | ✅ Concluído |
| Listagem admin com lojas inativas | `api/v1/lojas.php` (`?todas=1`, super_admin) | ✅ Concluído |

---

## ✅ CATEGORIA 18 — Etiquetas Térmicas para Produtos e Expositores

> Implementada em 2026-06-25. Libraries: `JsBarcode` (Code 128) + `qrcode.js` (QR Code).

| Item | Arquivo | Status |
|------|---------|--------|
| Coluna `codigo_interno` na tabela `produtos` | `12-migration_fase18_etiquetas.sql` | ✅ Concluído |
| Geração automática de código `PRD-XXXXXX` | `api/v1/produtos.php` (POST criar) | ✅ Concluído |
| Interface de etiquetas (expositor + produto) | `views/gerencia/etiquetas.php` | ✅ Concluído |
| Chips de categoria, seleção múltipla, qtd de cópias | `views/gerencia/etiquetas.php` | ✅ Concluído |
| Code 128 via JsBarcode + QR Code via qrcode.js | `views/gerencia/etiquetas.php` (inline JS) | ✅ Concluído |
| Campo validade para etiqueta adesiva | `views/gerencia/etiquetas.php` | ✅ Concluído |
| Impressão via `window.print()` + suporte QZ Tray | `views/gerencia/etiquetas.php` | ✅ Concluído |
| Rota `.htaccess` | `^gerencia/etiquetas` | ✅ Concluído |

---

## ✅ CATEGORIA 19 — PDV Multi-Loja e Caixas Individuais por Filial

> Implementada em 2026-06-25.

| Item | Arquivo | Status |
|------|---------|--------|
| Middleware `pdv_loja_check.php` | `app/middleware/pdv_loja_check.php` | ✅ Concluído |
| Tela de bloqueio quando operador sem loja | `pdv_loja_check.php` | ✅ Concluído |
| PDV mostra loja do operador (fixo) | `views/pdv/index.php` | ✅ Concluído |
| Super_admin: dropdown para selecionar loja no PDV | `views/pdv/index.php` | ✅ Concluído |
| Painel "Caixas Abertos" para super_admin | `views/dashboard/fragmentos/caixas_abertos.php` | ✅ Concluído |

---

## ✅ CATEGORIA 20 — Controle Granular de Permissões por Usuário

> Implementada em 2026-06-25.

| Item | Arquivo | Status |
|------|---------|--------|
| Tabela `permissoes_usuario` | `13-migration_fase20_permissoes.sql` | ✅ Concluído |
| Enum de permissões + defaults por role | `app/config/permissoes.php` | ✅ Concluído |
| Função `tem_permissao($usuario_id, $permissao, $role)` | `app/helpers/functions.php` | ✅ Concluído |
| API `permissoes.php` (GET lista, PUT substituir, POST toggle) | `api/v1/permissoes.php` | ✅ Concluído |
| Log de auditoria para alterações de permissão | `api/v1/permissoes.php` | ✅ Concluído |
| Gestão de usuários + modal de permissões | `views/admin/usuarios.php` | ✅ Concluído |

**Permissões granulares definidas:** `ver_dre`, `ver_bi`, `exportar_relatorio`, `editar_produto`, `aplicar_desconto`, `ver_auditoria`, `ver_historico_pontos`, `gerenciar_campanhas`, `ver_folha`, `admitir_funcionario`, `imprimir_etiqueta`, `ver_relatorio_estoque`.

**Novo arquivo de execução obrigatório:**
```
database/13-migration_fase20_permissoes.sql
```

---

## ✅ CATEGORIA 21 — Redesign do PDV

> Implementada em 2026-06-25. Lógica de negócio e APIs preservadas.

| Item | Status |
|------|--------|
| Layout split-screen 100vh (catálogo 60% / carrinho 40%) | ✅ Concluído |
| Grid de cards de produto com foto | ✅ Concluído |
| Chips de categoria com scroll horizontal | ✅ Concluído |
| Busca + dropdown de resultados | ✅ Concluído |
| CPF cliente inline na barra de busca | ✅ Concluído |
| Barra de atalhos no rodapé do carrinho | ✅ Concluído |
| Modal de pagamento em 3 etapas (forma → valor → confirmar) | ✅ Concluído |
| Botões rápidos de cédulas no passo 2 | ✅ Concluído |
| Indicador de modo offline (badge âmbar + pulso) | ✅ Concluído |
| Animação de flash ao adicionar produto pelo card | ✅ Concluído |

---

## ✅ CATEGORIA 22 — Modo Restrito (Inadimplência)

> Implementada em 2026-06-25. Flag `modo_restrito` na tabela `configuracoes`. Exclusivo `dev_admin`.

| Item | Arquivo | Status |
|------|---------|--------|
| Funções `modo_restrito_ativo()` e `motivo_restricao()` | `app/helpers/functions.php` | ✅ Concluído |
| Middleware `modo_restrito.php` (HTTP 402 / tela HTML) | `app/middleware/modo_restrito.php` | ✅ Concluído |
| Middleware aplicado nas 7 APIs bloqueadas | `bi.php`, `auditoria.php`, `ponto.php`, `estoque.php`, `contas_pagar.php`, `funcionarios.php`, `adtech.php` | ✅ Concluído |
| Toggle + campo de motivo no painel dev_admin | `views/dev/index.php` | ✅ Concluído |
| Endpoint `POST /dev/modo-restrito` | `api/v1/dev.php` | ✅ Concluído |
| Status "Modo Restrito" no painel dev | `views/dev/index.php` | ✅ Concluído |
| Log de auditoria na ativação/desativação | `api/v1/dev.php` | ✅ Concluído |

**Funcionalidades bloqueadas (HTTP 402):** BI/DRE, Auditoria, Ponto/Jornada, Estoque, Contas a Pagar, Funcionários, Campanhas/AdTech.
**Preservados:** PDV, catálogo público, checkout, pedidos, login.

---

## 🗂️ ORDEM DE EXECUÇÃO DAS MIGRATIONS (ATUALIZADA)

```
database/00-schema.sql
database/01-seed.sql
database/02-seed_produtos.sql
database/03-migration_fase2_perfil.sql
database/04-migration_fase6_pedidos.sql
database/05-migration_fase7_erp.sql
database/06-migration_fase8_cms.sql
database/07-migration_fase9_dev.sql
database/08-migration_fase10_crm.sql
database/09-migration_crm_v2.sql
database/10-migration_fase11_whatsapp.sql
database/11-migration_fase12_adtech.sql
database/12-migration_fase18_etiquetas.sql   ← Categoria 18
database/13-migration_fase20_permissoes.sql  ← Categoria 20
database/14-migration_lojas_unique.sql       ← UNIQUE(nome) em lojas
database/15-migration_usuarios_foto_perfil.sql ← ADD COLUMN foto_perfil VARCHAR(255)
database/16-migration_historico_pontos.sql   ← CREATE TABLE historico_pontos ⚠️ PENDENTE
database/17-migration_pedidos_motivo.sql     ← ADD COLUMN motivo_cancelamento ⚠️ PENDENTE
database/18-migration_ceasa.sql              ← frota + colaboradores + rotas + recebimentos ⚠️ PENDENTE
```

**Nota migration 18:** Se ocorrer erro #1005 (FK incorretamente formada), faça DROP das tabelas na ordem inversa e re-execute. A causa é incompatibilidade de tipo `INT` vs `INT UNSIGNED` entre tabelas antigas e novas. O arquivo atual já remove FKs para `lojas`, `usuarios` e `produtos` — mantém apenas FKs entre as novas tabelas.

---

## ✅ SESSÃO 2026-06-25b — Correções e melhorias pós-auditoria

| Item | Arquivo(s) | Status |
|------|-----------|--------|
| Fix p.unidade → unidade_medida em pedidos.php | `api/v1/pedidos.php` | ✅ |
| Fix preco_promo → promo_preco com date range | `api/v1/pedidos.php` | ✅ |
| Fix Entreguei: historico_pontos inexistente | `api/v1/pedidos.php`, migration 16 | ✅ |
| Centralizar delivery para Loja 3 | `views/checkout/index.php` | ✅ |
| Auto-preencher endereço salvo no checkout | `views/checkout/index.php` | ✅ |
| Histórico compras: incluir pedidos online | `api/v1/clientes.php` | ✅ |
| Redesign perfil cliente (hero + tabs) | `views/cliente/perfil.php` | ✅ |
| Página rastreamento /pedidos/{id}/status | `views/cliente/pedido_status.php` | ✅ |
| Botão Não Entregue (5 motivos) | `views/dashboard/fragmentos/pedidos.php` | ✅ |
| Sacola: step="any", largura input 72px | `public/js/sacola.js` | ✅ |
| Fix .htaccess rota pedido status | `.htaccess` | ✅ |
| motivo_cancelamento em pedidos | migration 17 | ✅ |

---

## ✅ SESSÃO 2026-06-26 — CEASA avançado + Pedidos interativos

### Módulo CEASA redesenhado

| Item | Arquivo | Status |
|------|---------|--------|
| Lista de Compra: planilha com TODOS os produtos | `ceasa.php` | ✅ |
| Colunas D1/D2/D3 por loja (pivot query) | `ceasa.php` | ✅ |
| Cores por status: crítico/baixo/ok/excesso | `ceasa.php` | ✅ |
| Filtros: busca + categoria + status estoque | `ceasa.php` | ✅ |
| Cabeçalho com data, dia, contadores | `ceasa.php` | ✅ |
| Card "Rota do Dia" (carro, motorista, aux1, aux2) | `ceasa.php` | ✅ |
| Modal configurar rota + gerenciar frota/equipe | `ceasa.php` | ✅ |
| Aba Recebimento: planilha com todos os produtos | `ceasa.php` | ✅ |
| Histórico de recebimentos (sub-aba) | `ceasa.php` | ✅ |
| APIs: frota, ceasa_colaboradores, ceasa_rotas | `api/v1/` | ✅ |
| API ceasa_recebimentos (POST salva+estoque, GET hist.) | `api/v1/ceasa_recebimentos.php` | ✅ |
| Migration 18: 5 novas tabelas | `database/18-migration_ceasa.sql` | ⚠️ PENDENTE runner |

### Módulo Pedidos & Despacho

| Item | Arquivo | Status |
|------|---------|--------|
| Botões 📱 WA + 📞 ligar para todos os cards | `pedidos.php` fragmento | ✅ |
| Checklist interativo para "Em Preparo" | `pedidos.php` fragmento | ✅ |
| Marcar item separado (checkbox + risco) | `pedidos.php` fragmento | ✅ |
| Editar quantidade por item | `pedidos.php` fragmento | ✅ |
| Remover item (DOM) | `pedidos.php` fragmento | ✅ |
| Adicionar produto à lista | `pedidos.php` fragmento | ✅ |
| Salvar alterações (PATCH /pedidos/{id}/itens) | `api/v1/pedidos.php` | ✅ |
| API retorna item_id e produto_id nos itens | `api/v1/pedidos.php` | ✅ |

---

---

## ✅ SESSÃO 2026-06-26b — CEASA melhorias (filtros, distribuição redesign, rota histórico)

### Módulo CEASA — melhorias de UX e auditoria

| Item | Arquivo | Status |
|------|---------|--------|
| Recebimento: filtro por categoria (Todas/Frutas/Verduras/Legumes/Outros) | `ceasa.php` | ✅ |
| Recebimento: campo de busca por nome | `ceasa.php` | ✅ |
| Recebimento: data-cat e data-nome nos TRs para filtragem JS | `ceasa.php` | ✅ |
| Distribuição: redesign completo com checklist melhorado | `ceasa.php` | ✅ |
| Distribuição: filtro por categoria com contadores | `ceasa.php` | ✅ |
| Distribuição: status dots (crítico/baixo) nas linhas | `ceasa.php` | ✅ |
| Distribuição: checkbox "distribuído" risca item visualmente | `ceasa.php` | ✅ |
| Distribuição: chip contador de lançamentos | `ceasa.php` | ✅ |
| Rota: painel de status atual (planejada/em andamento/concluída) | `ceasa.php` | ✅ |
| Rota: botão "Iniciar saída" (planejada → em_andamento) | `ceasa.php` | ✅ |
| Rota: modal de conclusão (resultado + motivo atraso + observações) | `ceasa.php` | ✅ |
| Rota: sub-abas "Rota de Entrega" e "Histórico de Rotas" | `ceasa.php` | ✅ |
| Rota: tabela de histórico com status, atraso, botão detalhes | `ceasa.php` | ✅ |
| Rota: modal de detalhes de rota (equipe, veículo, conclusão) | `ceasa.php` | ✅ |
| API ceasa_rotas PATCH: suporte a houve_atraso/motivo/obs/concluida_em | `api/v1/ceasa_rotas.php` | ✅ |
| Migration 19: ALTER TABLE ceasa_rotas + 4 colunas de conclusão | `database/19-migration_ceasa_rotas_conclusao.sql` | ⚠️ PENDENTE runner |

---

## ✅ SESSÃO 2026-06-26c — Módulo Caixa redesenhado

### Dashboard → Caixa

| Item | Arquivo | Status |
|------|---------|--------|
| Aba Fechamentos: KPI bar completa (Faturamento, Sangrias, Suprimentos, Líquido, Turnos, Vendas, Ticket Médio) | `caixa.php` | ✅ |
| Aba Fechamentos: cards por filial (super_admin, "Todas as lojas") com barra de proporção | `caixa.php` | ✅ |
| Aba Fechamentos: coluna Líquido na tabela (Fat − Sang) por turno + total no rodapé | `caixa.php` | ✅ |
| Aba Fechamentos: fundo troco e operador visíveis na tabela | `caixa.php` | ✅ |
| Aba Sangrias: KPIs (total sangrias, total suprimentos, saldo, qtd movimentos) | `caixa.php` | ✅ |
| Aba Resumo por Período: IMPLEMENTADA (agrupamento por dia, KPIs globais, tabela com barra de progresso por dia) | `caixa.php` | ✅ |
| Aba Resumo: atalhos rápidos de período (7d, 15d, 30d, Mês atual) | `caixa.php` | ✅ |
| Aba Resumo: destaque visual para fins de semana | `caixa.php` | ✅ |
| Cores semânticas consistentes: verde = faturamento/liquido+, vermelho = sangrias/liquido−, azul = suprimentos | `caixa.php` | ✅ |
| Botão de impressão em Fechamentos e Resumo | `caixa.php` | ✅ |
| Auto-load na aba ativa ao abrir o módulo | `caixa.php` | ✅ |

---

## ⚠️ ACTIONS PENDENTES (usuário deve executar)

| # | Ação | Arquivo | Observação |
|---|------|---------|------------|
| 1 | Rodar migration 16 | `database/16-migration_historico_pontos.sql` | Cria tabela `historico_pontos` |
| 2 | Rodar migration 17 | `database/17-migration_pedidos_motivo.sql` | ADD COLUMN `motivo_cancelamento` em `pedidos` |
| 3 | Rodar migration 18 | `database/18-migration_ceasa.sql` | 5 tabelas CEASA (frota/colaboradores/rotas/recebimentos/itens) |
| 4 | Rodar migration 19 | `database/19-migration_ceasa_rotas_conclusao.sql` | ADD COLUMN houve_atraso + motivo_atraso + observacoes_conclusao + concluida_em em `ceasa_rotas` |

---

*Última atualização: 2026-06-26 | Fases 0–12 ✅ | Categorias 13–22 ✅ | Expansão de Lojas ✅ | Sessões de melhorias 2026-06-25b, 2026-06-26, 2026-06-26b e 2026-06-26c ✅*
