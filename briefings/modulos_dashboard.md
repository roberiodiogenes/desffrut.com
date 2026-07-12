# 📐 ESPECIFICAÇÃO — MÓDULOS DO DASHBOARD DESFFRUT

> Documento de referência para implementação do painel lateral de módulos.
> Gerado em: 2026-06-23 | Status: ✅ Aprovado pelo PO

---

## 1. Roles do Sistema

| Role (banco) | Perfil real | Obs. |
|---|---|---|
| `super_admin` | Dono | Acesso irrestrito a tudo, pode editar qualquer registro |
| `gerente` | Gerente de loja + Motorista CEASA + Auxiliares de compra | Motorista/auxiliares se cadastram como gerente. Acesso operacional completo |
| `caixa` | Atendente/Caixa da loja | Recebe pedidos, monta e entrega ao entregador. Trabalha em sincronia com entregador |
| `entregador` | Entregador (cliente final) | Visão de campo: pedidos ativos + despacho |
| `rh_financeiro` | Financeiro + RH | Role único, módulos separados na tela |
| `cliente` | Cliente do app | Apenas área do cliente (catálogo, sacola, perfil) |

---

## 2. Mapa de Módulos

### Módulo 1 — 📦 Produtos & Estoque
**Roles:** `super_admin`, `gerente`
**Permissão:** edição completa

| Aba | Conteúdo | Fase |
|-----|----------|------|
| Produtos | CRUD, foto, ativar/inativar | ✅ Fase 3 |
| Estoque | Inventário por loja, ajustes de quantidade | ✅ Fase 3 |
| Quebras | Registro de perdas/avarias com motivo | ✅ Fase 3 |

---

### Módulo 2 — 🛒 Pedidos & Despacho
**Roles:** `super_admin`, `gerente`, `caixa`, `entregador`
**Permissão diferenciada por aba (ver tabela abaixo)**

| Aba | super_admin | gerente | caixa | entregador | Fase |
|-----|:-----------:|:-------:|:-----:|:----------:|------|
| Pendentes | ✅ | ✅ | ✅ | ❌ | Fase 6 |
| Ativos / Em preparo | ✅ | ✅ | ✅ | ✅ | Fase 6 |
| Despacho / Em rota | ✅ | ✅ | ✅ | ✅ | Fase 6 |
| Histórico & Cancelados | ✅ | ✅ | ✅ | ❌ | Fase 6 |

**Nota:** Caixa vê tudo para trabalhar em sincronia com o entregador. Entregador vê apenas o que já saiu para entrega ou está ativo para se organizar.

---

### Módulo 3 — 🚛 Compras CEASA
**Roles:** `super_admin`, `gerente`
**Permissão:** edição completa

| Aba | Conteúdo | Fase |
|-----|----------|------|
| Lista de Compra | Gerada automaticamente do estoque crítico, editável | Fase 4 |
| Recebimento & Conferência | Entrada das mercadorias, conferência de peso/quantidade | Fase 4 |
| Distribuição entre Lojas | Divisão das mercadorias por filial | Fase 4 |
| Rota Interna | Sequência de entrega nas lojas pelo motorista | Fase 4 |

**Nota:** O motorista e os auxiliares de compra se cadastram com o role `gerente`. Não há roles separados para esses perfis.

---

### Módulo 4 — 🧾 Caixa
**Roles:** `super_admin` (edita), `gerente` e `rh_financeiro` (somente leitura)

| Aba | Conteúdo | Fase |
|-----|----------|------|
| Fechamentos do Dia | Resumo de cada abertura/fechamento de caixa por turno | Fase 4 (PDV) |
| Sangrias & Suprimentos | Histórico de movimentos de caixa | Fase 4 (PDV) |
| Resumo por Período | Totais por data/loja com filtros | Fase 5 |

**Regra crítica:** Gerente e financeiro só visualizam — não podem alterar registros de caixa. Isso evita erros e desvios. Apenas `super_admin` pode editar/estornar.

---

### Módulo 5 — 📊 Relatórios & Gráficos
**Roles:** `super_admin`, `gerente`, `rh_financeiro`
**Permissão:** leitura e geração de relatórios

| Aba | Conteúdo | Fase |
|-----|----------|------|
| Estoque Crítico | Lista de compra por loja, imprimível | ✅ Fase 3 |
| Vendas por Período | Gráficos de faturamento, ticket médio | Fase 5 |
| Indicadores | Produtos mais vendidos, horários de pico | Fase 5 |
| DRE Simplificado | Receita, custo de mercadoria, margem | Fase 7 |

---

### Módulo 6 — 💵 Financeiro
**Roles:** `super_admin`, `rh_financeiro`
**Permissão:** edição completa

| Aba | Conteúdo | Fase |
|-----|----------|------|
| Contas a Pagar | Lançamentos, vencimentos, fornecedores | Fase 7 |
| Fluxo de Caixa | Entradas e saídas consolidadas | Fase 7 |
| Lançamentos | Registro manual de despesas/receitas diversas | Fase 7 |

---

### Módulo 7 — 👥 RH
**Roles:** `super_admin`, `rh_financeiro`
**Permissão:** edição completa

| Aba | Conteúdo | Fase |
|-----|----------|------|
| Funcionários | Cadastro, dados pessoais, contratos | Fase 7 |
| Salários & Pagamentos | Folha mensal, holerites | Fase 7 |
| Férias & Afastamentos | Controle de período, histórico | Fase 7 |
| Histórico/Baixas | Demissões, rescisões | Fase 7 |

**Nota:** RH e Financeiro compartilham o role `rh_financeiro` mas são módulos separados na interface. Um assistente de RH com este role terá acesso a ambos os módulos.

---

### Módulo 8 — ⚙️ Administração
**Roles:** `super_admin` exclusivamente
**Permissão:** total

| Aba | Conteúdo | Fase |
|-----|----------|------|
| Usuários & Permissões | Criar, editar, desativar usuários, trocar roles | ✅ Fase 1 (stub) |
| Lojas | Cadastro e configuração de filiais | ✅ Fase 1 (stub) |
| Auditoria | Log de todas as ações críticas do sistema | ✅ Fase 1 (stub) |

---

## 3. Matriz de Acesso Resumida

| Módulo | super_admin | gerente | caixa | entregador | rh_financeiro |
|--------|:-----------:|:-------:|:-----:|:----------:|:-------------:|
| Produtos & Estoque | ✅ edit | ✅ edit | ❌ | ❌ | ❌ |
| Pedidos & Despacho | ✅ | ✅ | ✅ (todas abas) | ✅ (ativos+desp.) | ❌ |
| Compras CEASA | ✅ edit | ✅ edit | ❌ | ❌ | ❌ |
| Caixa | ✅ edit | 👁️ view | ❌ | ❌ | 👁️ view |
| Relatórios | ✅ | ✅ | ❌ | ❌ | ✅ |
| Financeiro | ✅ edit | ❌ | ❌ | ❌ | ✅ edit |
| RH | ✅ edit | ❌ | ❌ | ❌ | ✅ edit |
| Administração | ✅ edit | ❌ | ❌ | ❌ | ❌ |

---

## 4. Comportamento da Interface

### Sidebar lateral
- Sempre visível em telas ≥ md (768px), colapsável em mobile
- Cada módulo é um item no menu; clicável expande as abas dentro do painel principal
- Módulos sem acesso para o role logado **não aparecem** (não ficam cinzas — simplesmente somem)
- Abas sem acesso dentro de um módulo também não aparecem
- Badge de alerta (🔴 número) aparece no módulo quando há alertas ativos (ex.: estoque crítico)

### Estados das abas
- **✅ Ativo:** conteúdo funcional, dados reais
- **⏳ Em desenvolvimento:** card com fase prevista, sem link clicável
- O super_admin vê uma tag discreta "[Fase X]" em abas ainda não implementadas

### Módulo ativo
- Persiste em `localStorage` o último módulo e aba visitados
- Ao fazer login, abre automaticamente o módulo mais relevante para o role:
  - `caixa` → Pedidos & Despacho / Pendentes
  - `entregador` → Pedidos & Despacho / Ativos
  - `gerente` → Produtos & Estoque / Estoque
  - `rh_financeiro` → Relatórios / Estoque Crítico
  - `super_admin` → Produtos & Estoque / Produtos

---

## 5. Fases de implementação dos módulos

| Módulo | Implementação |
|--------|--------------|
| Produtos & Estoque | ✅ Fase 3 (completo) |
| Relatórios (parcial) | ✅ Fase 3 (estoque crítico) |
| Compras CEASA | Fase 4 |
| Caixa | Fase 4 (PDV) |
| Pedidos & Despacho | Fase 6 |
| Financeiro | Fase 7 |
| RH | Fase 7 |
| Administração | Fase 7 (stubs existentes desde Fase 1) |

---

*Aprovado em: 2026-06-23 | Próximo passo: implementar sidebar com módulos no dashboard unificado*
