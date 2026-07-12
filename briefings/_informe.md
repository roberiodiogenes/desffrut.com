# 🔑 ACESSOS DE DESENVOLVIMENTO — DESFFRUT

> Usar apenas em ambiente local (XAMPP). Não subir para produção.
> Se os logins não funcionarem, acesse: http://localhost/desffrut.com/database/reset_senhas.php

---

## Login

**URL:** http://localhost/desffrut.com/login

| Role          | E-mail                      | Senha           | Destino após login |
|---------------|-----------------------------|-----------------|--------------------|
| **dev_admin** | **dev@desffrut.com.br**     | **devadmin123** | **/dev (console isolado)** |
| super_admin   | admin@desffrut.com.br       | admin123        | /dashboard (acesso total) |
| gerente       | gerente1@desffrut.com.br    | gerente123      | /dashboard (Loja 1) |
| caixa         | caixa1@desffrut.com.br      | caixa123        | /dashboard → PDV |
| entregador    | entregador1@desffrut.com.br | entregador123   | /dashboard (só Em Rota) |
| rh_financeiro | rh@desffrut.com.br          | rh123           | /dashboard (Financeiro → Contas a Pagar) |
| cliente       | cliente@teste.com           | cliente123      | /meu-perfil |

> **⚠️ Para criar o usuário dev_admin** — após rodar `07-migration_fase9_dev.sql`:
>
> **Passo 1** — Acesse http://localhost/desffrut.com/database/reset_senhas.php e crie/atualize
> o usuário `dev@desffrut.com.br` com a senha `devadmin123`.
>
> **Passo 2** — No phpMyAdmin, execute:
> ```sql
> -- Se o usuário ainda não existir, insira primeiro via reset_senhas.php,
> -- depois promova para dev_admin:
> UPDATE usuarios SET role = 'dev_admin' WHERE email = 'dev@desffrut.com.br';
> ```
>
> **Verificar:** `SELECT id, nome, email, role FROM usuarios WHERE role = 'dev_admin';`

---

## URLs úteis

| Tela | URL |
|------|-----|
| Catálogo público | http://localhost/desffrut.com/ |
| Login | http://localhost/desffrut.com/login |
| Cadastro | http://localhost/desffrut.com/cadastro |
| Meu Perfil (cliente) | http://localhost/desffrut.com/meu-perfil |
| Checkout | http://localhost/desffrut.com/checkout |
| Dashboard | http://localhost/desffrut.com/dashboard |
| **Dev Mode** | **http://localhost/desffrut.com/dev** |
| **Parcerias (pública)** | **http://localhost/desffrut.com/parcerias** |
| **Validar pedido WA** | **http://localhost/desffrut.com/pedidos/validar/{token}** |
| PDV (abertura) | http://localhost/desffrut.com/pdv/abertura |
| PDV (frente de caixa) | http://localhost/desffrut.com/pdv |
| PDV (sangria) | http://localhost/desffrut.com/pdv/sangria |
| Reset de senhas | http://localhost/desffrut.com/database/reset_senhas.php |

---

## Fases concluídas

| Fase | Descrição |
|------|-----------|
| ✅ Fase 0 | Setup, estrutura, schema SQL |
| ✅ Fase 1 | Banco, SSO, login unificado |
| ✅ Fase 2 | Catálogo público, sacola, área do cliente |
| ✅ Fase 3 | Retaguarda comercial, dashboard unificado |
| ✅ Fase 4 | PDV híbrido offline (IndexedDB + sync) |
| ✅ Fase 5 | Hardware: impressora térmica, balança, leitor de código de barras/QR Code |
| ✅ Fase 6 | Tele-entrega: checkout, pedidos, rastreamento, despacho, entregador |
| ✅ Fase 7 | ERP Administrativo: RH, Ponto/Jornada, Folha, Contas a Pagar, Fluxo, BI+DRE, Auditoria |
| ✅ Fase 8 | CMS & Portal: identidade visual, banners, lojas, campanhas sazonais |
| ✅ Fase 9 | Dev Mode: painel dev_admin, modo manutenção, inadimplência, reset forçado de senha, auditoria forense |
| ✅ Fase 10 | Prospecção & CRM: formulário parceiros (/parcerias), pipeline Kanban (Sortable.js), importador CSV, disparador WhatsApp por fase |
| ✅ Fase 11 | WhatsApp Híbrido: checkout mobile otimizado, botão "Enviar via WA", gerador de mensagem tabulada, token de validação em 1 clique (24h), notificações de status para cliente |
| ✅ Fase 12 | Marketing & AdTech: Meta Pixel + GA4 (injeção automática), helper `trackEvent()`, UTM capture em sessão, ROI por campanha no Dashboard, UTM builder + deep link Meta Ads, programa "Indique e Ganhe" com código único + bônus automático no 1º pedido entregue |


# LINKS
## Categoria 18 — Etiquetas Térmicas

views/gerencia/etiquetas.php — interface completa com grid de produtos, seleção, chips de tipo (expositor/adesiva), campo de validade, cópias, preview com JsBarcode (Code 128) + qrcode.js, impressão via window.print()

## Categoria 19 — PDV Multi-Loja

app/middleware/pdv_loja_check.php — bloqueia caixa sem loja_id, exibe tela clara de orientação
views/pdv/index.php — seletor de loja para super_admin/gerente, nome da loja exibido para caixas
views/dashboard/fragmentos/caixas_abertos.php — painel exclusivo super_admin com resumo financeiro consolidado e status por filial

## Categoria 20 — Permissões Granulares

database/13-migration_fase20_permissoes.sql — tabela permissoes_usuario (exceções-only)
app/config/permissoes.php — 12 permissões definidas com defaults por role
tem_permissao() e modo_restrito_ativo() em functions.php
api/v1/permissoes.php — GET, PUT (substituir todas), POST (toggle individual)
views/admin/usuarios.php — CRUD completo de usuários com modal de permissões por toggle

## Categoria 21 — Redesign do PDV

Layout 60/40 split com grid de cards de produto com foto
Chips de categoria com scroll horizontal
Indicador offline âmbar com animação de pulso
Animação flash ao adicionar produto pelo card
Modal de pagamento em 3 passos (forma → valor + cédulas rápidas → confirmar com resumo)

## Categoria 22 — Modo Restrito

app/middleware/modo_restrito.php — HTTP 402 em APIs / tela HTML em views
Middleware aplicado nas 7 APIs: bi, auditoria, ponto, estoque, contas_pagar, funcionarios, adtech
Painel dev: card vermelho com toggle + campo de motivo, stat no header
POST /dev/modo-restrito com log de auditoria
