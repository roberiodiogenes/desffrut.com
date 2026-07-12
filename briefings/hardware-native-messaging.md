# 🖨️ BRIEFING: MÓDULO HARDWARE — NATIVE MESSAGING (Fase 5 — Revisão 2.0)

> **Substitui:** QZ Tray (agente Java) por extensão de navegador + host nativo Python.
> **Data:** 2026-06-27 | **Status:** Em implantação

---

## 1. CONTEXTO E MOTIVAÇÃO

A Fase 5 original utilizava **QZ Tray** para impressão local a partir do navegador. Os problemas identificados foram:

| Problema | Impacto |
|----------|---------|
| Dependência de agente Java (JRE) | Instalação pesada, quebra com updates do Java |
| Certificado RSA obrigatório em produção | Complexidade de configuração por estação |
| Mixed-content bloqueado (HTTPS → WS local) | Falhas silenciosas em produção |
| Zero controle sobre o código do agente | Impossível debugar ou customizar |
| Sem suporte nativo a etiquetas ZPL | Impressora Tomate MKV-006 sem suporte adequado |

A solução adotada é **Chrome/Edge Native Messaging**: uma extensão leve do navegador que se comunica com um script Python local via protocolo nativo do browser — sem HTTP, sem WebSocket, sem Java, sem certificado.

---

## 2. ARQUITETURA GERAL

```
┌─────────────────────────────────────────────────────┐
│  desffrut.com (HTTPS)                               │
│                                                     │
│  hardware.js  ──postMessage──►  content.js          │
│  (módulo JS)  ◄──postMessage──  (extensão injetada) │
└─────────────────────────────────────────────────────┘
                                      │
                          chrome.runtime.sendMessage
                                      │
                                      ▼
┌─────────────────────────────────────────────────────┐
│  background.js (Service Worker da extensão)         │
│                                                     │
│  Recebe comandos ──────────────────────────────────►│
│  Encaminha via sendNativeMessage                    │
│  Retorna resultado de volta ao content.js           │
└─────────────────────────────────────────────────────┘
                                      │
                        chrome.runtime.connectNative
                          (stdin/stdout, JSON)
                                      │
                                      ▼
┌─────────────────────────────────────────────────────┐
│  desffrut_print.py (Host Nativo — Python)           │
│                                                     │
│  Recebe JSON via stdin                              │
│  Envia comandos para impressora via USB/TCP/porta   │
│  Retorna { ok, erro } via stdout                    │
└─────────────────────────────────────────────────────┘
                                      │
                    ┌─────────────────┼──────────────┐
                    ▼                 ▼               ▼
             Impressora          Impressora       Impressora
             Térmica Cupom      Etiqueta         Jato de Tinta
             (ESC/POS)          (MKV-006/ZPL)    (win32print)
```

### 2.1 Protocolo de comunicação (página ↔ extensão)

```
Página → Extensão:
window.postMessage({
  type:  'DESFFRUT_HW_REQUEST',
  msgId: <string único>,
  cmd:   'print' | 'list_printers' | 'test' | 'status',
  payload: { ... }
}, '*')

Extensão → Página (resposta):
window.postMessage({
  type:  'DESFFRUT_HW_RESPONSE',
  msgId: <mesmo string>,
  ok:    true | false,
  data:  { ... },
  erro:  <string | null>
}, '*')
```

### 2.2 Protocolo nativo (extensão ↔ Python)

Mensagens JSON com prefixo de 4 bytes (little-endian uint32) indicando o tamanho — padrão Chrome Native Messaging.

```json
// Entrada (extensão → Python)
{
  "cmd": "print",
  "tipo": "cupom" | "etiqueta" | "inkjet",
  "impressora": "Nome da Impressora Windows",
  "dados": { ... }
}

// Saída (Python → extensão)
{ "ok": true, "msg": "Impresso com sucesso" }
{ "ok": false, "erro": "Impressora não encontrada" }
```

---

## 3. IMPRESSORAS SUPORTADAS

### 3.1 Tipos e protocolos

| Tipo | Protocolo | Uso | Modelo de referência |
|------|-----------|-----|----------------------|
| `cupom` | ESC/POS (80mm / 58mm) | Cupom de venda, sangria, fechamento | Bematech, Epson TM, Elgin, Daruma |
| `etiqueta` | ZPL / ESC/POS label | Etiquetas adesivas (produto, envio) | **Tomate MKV-006** |
| `inkjet` | win32print / PDF | Relatórios, pedidos de compra | Qualquer impressora Windows |

### 3.2 Tomate MKV-006 — especificações

- **Tipo:** Impressora térmica de etiquetas adesivas
- **Protocolo:** ZPL (Zebra Programming Language) — compatível com impressoras Zebra ZPL-II
- **Papel:** Etiquetas adesivas 100×150mm (padrão Mercado Livre), 100×50mm, configurável
- **Conexão:** USB (aparece como impressora Windows)
- **Uso primário:** Etiquetas de envio (Mercado Livre, Shopee, SHEIN), etiquetas de produto
- **Comando de teste ZPL:**
  ```zpl
  ^XA
  ^FO50,50^A0N,30,30^FDDesffrut - Teste^FS
  ^FO50,100^BY2^BCN,100,Y,N,N^FD123456789^FS
  ^XZ
  ```

### 3.3 Configuração por estação

Cada estação de trabalho (caixa) configura suas próprias impressoras via **Dashboard → Hardware**. As configurações são salvas no `localStorage` do navegador (por máquina, não no servidor).

```js
// Estrutura de configuração no localStorage
{
  "desffrut_hw_v2": {
    "impressoras": {
      "cupom":    { "nome": "BEMATECH MP-4200", "papel": "80" },
      "etiqueta": { "nome": "Tomate MKV-006",  "largura": 100, "altura": 150 },
      "inkjet":   { "nome": "HP LaserJet 1020", "papel": "A4" }
    },
    "balanca": { "porta": null },
    "extensao": { "instalada": false, "versao": null }
  }
}
```

---

## 4. ESTRUTURA DE ARQUIVOS

```
desffrut.com/
├── public/
│   └── js/
│       └── pdv/
│           └── hardware.js              ← Módulo JS reescrito (sem QZ)
├── views/
│   └── dashboard/
│       └── fragmentos/
│           └── hardware.php             ← UI reescrita (multi-impressora)
└── extension/
    ├── chrome/
    │   ├── manifest.json                ← Manifest V3 (Chrome)
    │   ├── background.js                ← Service Worker
    │   ├── content.js                   ← Bridge página ↔ extensão
    │   ├── popup.html                   ← UI da extensão (status)
    │   ├── popup.js
    │   └── icons/
    │       ├── icon16.png
    │       ├── icon48.png
    │       └── icon128.png
    ├── edge/
    │   └── manifest.json                ← Manifest adaptado para Edge
    └── native-host/
        ├── desffrut_print.py            ← Host nativo Python
        ├── desffrut_print.json          ← Manifest do host (Chrome)
        ├── desffrut_print_edge.json     ← Manifest do host (Edge)
        ├── requirements.txt             ← pywin32
        ├── install_chrome.bat           ← Instala host + registra no Windows
        ├── install_edge.bat
        └── uninstall.bat
```

---

## 5. FASES DE IMPLANTAÇÃO

---

### FASE 5A — Briefing e Arquitetura ✅
*Definir escopo, estrutura e protocolo antes de escrever código.*

- [x] Documentar problema com QZ Tray
- [x] Definir arquitetura Native Messaging
- [x] Especificar protocolo de comunicação (postMessage ↔ chrome.runtime ↔ stdin/stdout)
- [x] Mapear impressoras: cupom (ESC/POS), etiqueta (ZPL - Tomate MKV-006), inkjet
- [x] Definir estrutura de arquivos

---

### FASE 5B — Reescrita do hardware.js ✅
*Módulo JS central sem QZ Tray, comunicação via postMessage para a extensão.*

- [x] Remover toda referência a QZ Tray
- [x] Implementar canal `postMessage` com timeout e msgId único
- [x] Detector de extensão instalada (`extensaoInstalada()`)
- [x] Suporte a 3 tipos de impressora via `tipo` no payload
- [x] Manter Web Serial API (balança Toledo/Filizola)
- [x] Manter BarcodeDetector API (câmera)
- [x] Manter impressão local PHP (fallback localhost)
- [x] API pública compatível com código existente (mesmos nomes de função)

---

### FASE 5C — Reescrita do hardware.php (UI) ✅
*Painel de configuração multi-impressora, mais elegante e funcional.*

- [x] Cards por tipo de impressora (cupom, etiqueta, inkjet)
- [x] Status visual por periférico (ponto verde/amarelo/vermelho)
- [x] Detecção e listagem de impressoras instaladas no Windows
- [x] Botão de teste por tipo de impressora
- [x] Painel de status da extensão (instalada / não instalada / versão)
- [x] Link de download/instalação da extensão
- [x] Configuração de papel por tipo (80mm/58mm para cupom, 100×150mm para etiqueta)
- [x] Manter seção de balança e leitor de código de barras

---

### FASE 5D — Extensão Chrome ✅
*Manifest V3, content script como bridge, service worker com Native Messaging.*

- [x] `manifest.json` — Manifest V3, permissões: `nativeMessaging`, host permissions
- [x] `content.js` — Escuta `window.postMessage` e repassa para `chrome.runtime`
- [x] `background.js` — Service Worker que chama `chrome.runtime.sendNativeMessage`
- [x] `popup.html` / `popup.js` — Status visual (extensão OK / host conectado / impressoras)
- [x] Ícones SVG/PNG (16, 48, 128px)

---

### FASE 5E — Host Nativo Python ✅
*Script Python que recebe JSON, envia para impressora, retorna resultado.*

- [x] Protocolo de entrada/saída (4 bytes length + JSON UTF-8)
- [x] Comando `list_printers` — lista impressoras Windows via `win32print`
- [x] Comando `print` tipo `cupom` — ESC/POS via win32print (raw)
- [x] Comando `print` tipo `etiqueta` — ZPL via win32print (raw) para Tomate MKV-006
- [x] Comando `print` tipo `inkjet` — via win32print (raw PDF ou texto)
- [x] Comando `status` — retorna versão, Python version, impressoras disponíveis
- [x] Log em `%APPDATA%\Desffrut\print_host.log`
- [x] `requirements.txt` com `pywin32`
- [x] `install_chrome.bat` — instala Python deps + registra host no HKCU do Windows
- [x] `install_edge.bat` — mesmo para Edge

---

### FASE 5F — Extensão Edge ✅
*Adaptação mínima do Chrome para Edge (Chromium-based — 95% igual).*

- [x] `manifest.json` adaptado (sem diferenças funcionais significativas para MV3)
- [x] `desffrut_print_edge.json` com path correto para o host
- [x] `install_edge.bat` registra na chave de registro do Edge
- [x] Documentação de instalação para Edge

---

### FASE 5G — Atualização do Briefing Principal ⏳
*Atualizar brienfing.md e fases_desenvolvimento.md para refletir a mudança.*

- [ ] Atualizar Seção 3.1 do brienfing.md (Hardware Local) — substituir QZ Tray por Native Messaging
- [ ] Atualizar Categoria 4 do checklist (Hardware) — marcar como revisado
- [ ] Atualizar Categoria 18 (Etiquetas) — novo protocolo ZPL para MKV-006
- [ ] Remover arquivos legados: `api/v1/qz_sign.php`, `app/certs/qz-cert.php`, `app/certs/qz-private-key.pem`
- [ ] Atualizar `fases_desenvolvimento.md` — Fase 5 marcada com novo status

---

## 6. GUIA DE INSTALAÇÃO POR ESTAÇÃO

### Pré-requisitos
- Windows 10 ou 11
- Google Chrome 88+ ou Microsoft Edge 88+
- Python 3.8+ (instalado pelo script)
- Impressora instalada no Windows (aparecer em Configurações → Impressoras)

### Passo a passo

1. **Conectar impressoras** ao computador e garantir que aparecem em Configurações → Impressoras e Scanners do Windows.

2. **Instalar a extensão:**
   - Chrome: Abrir `chrome://extensions` → "Modo desenvolvedor" → "Carregar sem compactação" → selecionar pasta `extension/chrome/`
   - Edge: Abrir `edge://extensions` → "Modo desenvolvedor" → "Carregar sem compactação" → selecionar pasta `extension/edge/`

3. **Instalar o host nativo:**
   - Executar `extension/native-host/install_chrome.bat` (como Administrador)
   - Ou `install_edge.bat` para Edge

4. **Configurar no Dashboard:**
   - Acessar Dashboard → Hardware
   - Clicar em "Detectar Impressoras" em cada tipo
   - Selecionar a impressora correta
   - Clicar em "Testar" para confirmar

### Verificação
- O ícone da extensão no browser deve mostrar status verde ✅
- O painel Hardware deve mostrar "Extensão: Conectada" e "Host: Ativo"

---

## 7. COMPARATIVO: QZ TRAY vs NATIVE MESSAGING

| Critério | QZ Tray | Native Messaging |
|----------|---------|-----------------|
| Instalação | Agente Java (200MB+) | Python + script bat (5MB) |
| Certificado SSL | Obrigatório em produção | Não necessário |
| Mixed content | Problema (HTTPS → WS) | Não existe (extensão ↔ host) |
| Controle do código | Zero (código fechado) | 100% (nosso código) |
| Debug | Logs ocultos | Log em %APPDATA%\Desffrut |
| Suporte ZPL (etiquetas) | Não oficial | Nativo |
| Atualizações | Dependente do QZ | Independente |
| Custo | Gratuito (mas certificado $) | 100% gratuito |
| Tempo de instalação/estação | ~15 min | ~5 min |

---

## 8. TRATAMENTO DE ERROS

| Erro | Causa | Solução apresentada ao usuário |
|------|-------|-------------------------------|
| `extensao_nao_instalada` | Extensão não carregada no browser | Link para instrução de instalação |
| `host_nao_encontrado` | `install.bat` não executado | Instrução para executar o instalador |
| `impressora_nao_encontrada` | Nome incorreto ou impressora offline | Botão "Detectar Impressoras" |
| `timeout` | Host demorou > 10s | Verificar se impressora está ligada |
| `zpl_nao_suportado` | Impressora não aceita ZPL | Tentar modo ESC/POS como fallback |

---

## 9. ROLLBACK

Se necessário reverter para QZ Tray:
1. Restaurar `hardware.js` da versão anterior (Git: `git checkout HEAD~1 -- public/js/pdv/hardware.js`)
2. Restaurar `hardware.php` idem
3. Restaurar arquivos `api/v1/qz_sign.php` e `app/certs/`
4. A extensão pode permanecer instalada sem causar conflito

---

*Documento gerado em 2026-06-27. Próxima revisão após homologação da Fase 5G.*
