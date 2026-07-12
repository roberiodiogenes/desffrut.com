# Desffrut Hardware Extension — Guia de Instalação

## Estrutura

```
extension/
├── chrome/          ← Extensão para Google Chrome
├── edge/            ← Extensão para Microsoft Edge (mesmos arquivos do Chrome)
└── native-host/     ← Host nativo Python + instaladores
```

A pasta `edge/` usa os mesmos `background.js`, `content.js`, `popup.html` e `popup.js`
do Chrome. Apenas o `manifest.json` é próprio do Edge (mas é idêntico ao do Chrome).

## Instalação — Chrome

1. Copie os arquivos de `chrome/` para uma pasta permanente no computador (ex: `C:\desffrut\extension\`)
2. Abra **chrome://extensions**
3. Ative **Modo desenvolvedor** (canto superior direito)
4. Clique em **Carregar sem compactação** e selecione a pasta `chrome/`
5. Anote o **ID da extensão** (ex: `abcdefghijklmnopqrstuvwxyzabcdef`)
6. Execute `native-host/install_chrome.bat` como **Administrador** e cole o ID quando solicitado

## Instalação — Edge

1. Repita os passos 2–5 acima em **edge://extensions**
2. Execute `native-host/install_edge.bat` como **Administrador** e cole o ID do Edge

## Verificação

- Ícone da extensão no browser deve mostrar badge verde ✓
- Dashboard → Hardware deve mostrar **"Extensão: Conectada"** e **"Host: Ativo"**
- Clique em "Detectar impressoras" — deve listar todas as impressoras do Windows

## Atualização

Para atualizar o host nativo Python:
1. Substitua `native-host/desffrut_print.py`
2. Execute novamente `install_chrome.bat` (não precisa recolocar o ID)
3. Recarregue a extensão em `chrome://extensions`

## Log de depuração

Arquivo de log disponível em:
`%APPDATA%\Desffrut\print_host.log`

## Tomate MKV-006 — Configuração

A impressora de etiquetas Tomate MKV-006 usa protocolo **ZPL**.
Para instalar no Windows:
1. Conecte via USB
2. Vá em Configurações → Impressoras e Scanners → Adicionar dispositivo
3. Se não aparecer automaticamente: "Adicionar manualmente" → "Zebra Technologies" → modelo genérico ZPL
4. Nomeie exatamente como **"Tomate MKV-006"**
5. No Dashboard → Hardware → Impressora de Etiqueta, selecione este nome
