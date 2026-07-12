# 🔍 AUDITORIA DE CÓDIGO — DESFFRUT
> Data: 2026-06-25 | Auditor: Claude (Cowork) | Versão do projeto: 1.0.0

---

## Legenda de Status
- 🔴 Crítico — Segurança ou funcionalidade totalmente quebrada
- 🟡 Médio — Funcionalidade incompleta ou degradada
- 🟢 Menor — Inconsistência ou dívida técnica
- ✅ Corrigido
- ⏳ Pendente

---

## P1 — SEGURANÇA: `database/` exposto na web ✅
**Arquivo:** `.htaccess`
**Gravidade:** 🔴 Crítico

### Problema
O `.htaccess` protege apenas `app/` e `briefings/`, mas **não** a pasta `database/`.
O arquivo `database/reset_senhas.php` é acessível publicamente sem nenhuma autenticação e
permite criar ou modificar usuários com qualquer role (incluindo `dev_admin`).

```
http://localhost/desffrut.com/database/reset_senhas.php  ← EXPOSTO
```

### Correção
Adicionar `database` ao bloco de proteção do `.htaccess`:
```apache
RewriteRule ^(app|briefings|database)(\/|$) - [F,L]
```

---

## P2 — Redirect quebrado em XAMPP no `auth_check.php` ✅
**Arquivo:** `app/middleware/auth_check.php` — linha 22
**Gravidade:** 🔴 Crítico

### Problema
```php
redirecionar('/login');  // ← ERRADO
```
Em XAMPP com subdiretório `/desffrut.com`, o Apache interpreta `/login` como raiz do servidor
(`http://localhost/login`), não como `http://localhost/desffrut.com/login`.
Qualquer tentativa de acesso a rota protegida sem sessão resulta em **404 na página de login**.

### Correção
```php
redirecionar(BASE_PATH . '/login');  // ← CORRETO
```

---

## P3 — `01-seed.sql` referencia colunas inexistentes no schema base ✅
**Arquivo:** `database/01-seed.sql` — linha 13
**Gravidade:** 🔴 Crítico

### Problema
O seed insere dados em `lojas` com as colunas `horario_funcionamento` e `whatsapp_link`:
```sql
INSERT IGNORE INTO lojas (nome, endereco, telefone, horario_funcionamento, whatsapp_link, ativo)
```
Essas colunas só são criadas em `06-migration_fase8_cms.sql` — que roda **depois** do seed.
O INSERT falha com erro de coluna desconhecida, e as lojas não são inseridas no banco.

### Correção
Remover as colunas extras do INSERT base. As colunas CMS são populadas separadamente
após rodar a migration 06, via interface de admin (CMS → Lojas) ou script de seed adicional.

```sql
-- Antes (ERRADO):
INSERT IGNORE INTO lojas (nome, endereco, telefone, horario_funcionamento, whatsapp_link, ativo)

-- Depois (CORRETO):
INSERT IGNORE INTO lojas (nome, endereco, telefone, ativo)
```

---

## P4 — Gerente recebe 403 ao acessar BI e DRE no dashboard ✅
**Arquivo:** `views/dashboard/fragmento.php` — linha 38
**Gravidade:** 🔴 Crítico

### Problema
O whitelist de fragmentos restringe `bi` apenas a `super_admin` e `rh_financeiro`:
```php
'bi' => ['super_admin', 'rh_financeiro'],
```
Mas no `views/dashboard/index.php`, o módulo **Relatórios** (com roles `['super_admin', 'gerente', 'rh_financeiro']`)
inclui as abas "BI / Gráficos" e "DRE" que carregam o fragmento `bi`.
O gerente vê as abas no sidebar mas recebe 403 silencioso ao clicar — o conteúdo nunca carrega.

### Correção
```php
'bi' => ['super_admin', 'gerente', 'rh_financeiro'],
```

---

## P5 — `dev_admin` ausente do array de destinos no `login.php` ✅
**Arquivo:** `login.php` — linhas 15–24
**Gravidade:** 🟡 Médio

### Problema
O array `$destinos` de redirecionamento pós-login não inclui o role `dev_admin`:
```php
$destinos = [
    'cliente'       => BASE_PATH . '/meu-perfil',
    'caixa'         => BASE_PATH . '/dashboard',
    // ... (dev_admin ausente)
];
redirecionar($destinos[$usuario['role']] ?? '/');  // dev_admin cai no fallback '/'
```
Se um `dev_admin` já estiver logado e acessar `/login`, é redirecionado para `/` (catálogo).

### Correção
Adicionar ao array:
```php
'dev_admin' => BASE_PATH . '/dev',
```

---

## P6 — Fragmento `caixas_abertos` implementado mas desconectado da UI ✅
**Arquivos:** `views/dashboard/fragmento.php`, `views/dashboard/index.php`
**Gravidade:** 🟡 Médio

### Problema
O arquivo `views/dashboard/fragmentos/caixas_abertos.php` foi implementado na Categoria 19,
mas está **ausente** do whitelist do `fragmento.php` e **não aparece como aba** no sidebar do dashboard.
O super_admin não tem como acessar o painel consolidado de caixas abertos por filial.

### Correção
1. Adicionar ao whitelist em `fragmento.php`:
   ```php
   'caixas_abertos' => ['super_admin', 'dev_admin'],
   ```
2. Adicionar aba no módulo "Administração" do `dashboard/index.php`:
   ```php
   ['id' => 'caixas', 'label' => 'Caixas Abertos', 'frag' => 'caixas_abertos', 'fase' => null],
   ```

---

## P7 — Aba "Usuários" no dashboard ainda mostra placeholder ✅
**Arquivo:** `views/dashboard/index.php` — linha 143
**Gravidade:** 🟡 Médio

### Problema
O módulo "Administração" tem a aba "Usuários" apontando para `placeholder` com `fase: 9`:
```php
['id' => 'usuarios', 'label' => 'Usuários', 'frag' => 'placeholder', 'fase' => 9, ...]
```
Porém a página `views/admin/usuarios.php` com CRUD completo + modal de permissões
foi implementada na **Categoria 20** e está totalmente funcional.
O super_admin vê "Em desenvolvimento — Fase 9" ao clicar em "Usuários".

### Correção
Substituir o placeholder por um card que redireciona para `/admin/usuarios`
ou criar um fragmento dedicado. Solução adotada: card de link direto inline.

---

## P8 — Páginas públicas sem verificação de modo manutenção ✅
**Arquivos:** `views/public/lojas.php`, `views/public/sobre.php`, `views/public/fidelidade.php`, `views/parcerias/index.php`
**Gravidade:** 🟢 Menor

### Problema
O `index.php` (catálogo) inclui `maintenance_check.php` corretamente, mas as demais
páginas públicas não incluem. Durante modo manutenção, essas páginas continuam acessíveis.

### Correção
Adicionar em cada uma, após `iniciar_sessao()`:
```php
require_once __DIR__ . '/../../app/middleware/maintenance_check.php';
```

---

## Itens fora do escopo imediato (backlog)

| # | Item | Gravidade | Observação |
|---|------|-----------|------------|
| B1 | `00-schema.sql` sem categoria `temperos` no ENUM | 🟢 | Adicionar via migration futura se necessário |

---

## Histórico de correções

| Data | Item | Status |
|------|------|--------|
| 2026-06-25 | P1 — `.htaccess` bloqueia `database/` | ✅ |
| 2026-06-25 | P2 — `auth_check.php` redirect com `BASE_PATH` | ✅ |
| 2026-06-25 | P3 — `01-seed.sql` colunas corrigidas | ✅ |
| 2026-06-25 | P4 — `fragmento.php` gerente acessa `bi` | ✅ |
| 2026-06-25 | P5 — `login.php` destino `dev_admin` | ✅ |
| 2026-06-25 | P6 — `caixas_abertos` conectado ao dashboard | ✅ |
| 2026-06-25 | P7 — Aba Usuários substituída por link | ✅ |
| 2026-06-25 | P8 — `maintenance_check` nas páginas públicas | ✅ |
