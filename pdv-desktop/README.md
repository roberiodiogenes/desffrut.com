# Desffrut PDV Desktop

PDV empacotado como executável Windows (.exe), construído em Python com
[pywebview](https://pywebview.flowrl.com/). A janela carrega o mesmo PDV
web já existente (`views/pdv/index.php`) — não é uma reescrita da
interface — e expõe uma API Python (`app/bridge.py`) que `hardware.js`
chama diretamente para imprimir e ler a balança, sem depender de
extensão de navegador ou Native Messaging.

## Por que essa abordagem

O módulo de hardware atual (`extension/` + `native-host/`) depende de:
extensão Chrome/Edge instalada por estação, host Python registrado no
registro do Windows, e o protocolo Native Messaging (stdin/stdout) entre
os dois. Cada uma dessas peças é um ponto de falha independente —
update de navegador, permissão negada, manifest não encontrado, etc.

O PDV Desktop elimina essa cadeia: o Python roda no mesmo processo da
janela, e `hardware.js` chama `window.pywebview.api.hw_comando(...)`
diretamente. Sincronização offline não precisou ser recriada — o PDV já
usa IndexedDB + `sync.js` + `api/v1/sync.php`, e isso continua
funcionando igual dentro da janela pywebview.

## Estrutura

```
pdv-desktop/
├── app/
│   ├── main.py              # ponto de entrada — abre a janela pywebview
│   ├── bridge.py            # API Python exposta ao JS (window.pywebview.api)
│   ├── config.py            # URL do PDV, paths de log e pareamento (%APPDATA%\Desffrut)
│   └── hardware/
│       ├── impressora.py    # win32print — cupom ESC/POS, etiqueta TSPL/ZPL, inkjet
│       └── balanca.py       # pyserial — leitura nativa da balança (substitui Web Serial)
├── build/
│   ├── pdv_desktop.spec     # spec do PyInstaller
│   ├── build.bat            # cria venv, instala deps, gera o .exe
│   └── run_dev.bat          # roda direto do código-fonte (sem compilar)
└── requirements.txt
```

## Fluxo de desenvolvimento

1. **Máquina de desenvolvimento precisa ser Windows.** `pywin32` e
   `win32print` só existem no Windows, e o PyInstaller não faz
   cross-compile — um `.exe` só é gerado rodando em Windows.

2. **Primeira vez:**
   ```
   cd pdv-desktop
   python -m venv venv
   venv\Scripts\activate
   pip install -r requirements.txt
   ```

3. **Rodar sem compilar** (iteração rápida, com DevTools):
   ```
   build\run_dev.bat
   ```
   Por padrão carrega `https://desffrut.com/views/pdv/index.php`. Para
   testar contra o XAMPP local, crie
   `%APPDATA%\Desffrut\settings.json`:
   ```json
   { "pdv_url": "http://localhost/desffrut.com/views/pdv/index.php" }
   ```
   ou rode `python main.py --url http://localhost/desffrut.com/views/pdv/index.php`.

4. **Testar hardware:** com o PDV aberto, abra o painel Dashboard →
   Hardware normalmente. `hardware.js` detecta `window.pywebview` e
   passa a usar o bridge automaticamente — a UI de configuração de
   impressoras/balança não muda.

5. **Gerar o .exe:**
   ```
   build\build.bat
   ```
   Executável final em `build\dist\DesffrutPDV.exe`. Teste-o numa
   estação limpa antes de distribuir (sem Python instalado) — o
   `--onefile` empacota o interpretador junto.

6. **Distribuir por loja:** copiar `DesffrutPDV.exe` para o computador
   do caixa, criar atalho na área de trabalho. Não precisa instalar
   Python, extensão, nem rodar `install_chrome.bat`.

## Próximos passos (fora do escopo Python — mudanças no site)

Estes dois pontos completam o plano original mas mexem em código PHP
em produção, então não foram aplicados automaticamente — seguem
especificados para implementação numa próxima etapa:

### 1. Pareamento de dispositivo

`app/bridge.py` já tem `parear_dispositivo()` / `device_info()`
prontos para guardar `{device_id, token, loja_id}` em
`%APPDATA%\Desffrut\device.json`. Falta o lado servidor:
- Nova tabela `pdv_devices` (device_id, loja_id, token, criado_em, revogado_em).
- Endpoint `POST /api/v1/dispositivos/parear` — dono/gerente autenticado
  gera um código de pareamento de uso único; o .exe troca esse código
  pelo token permanente do dispositivo na primeira execução.
- `api/auth.php` já aceita `Authorization: Bearer` para "PDV offline,
  apps externos" (comentário na linha 16) — o token de dispositivo pode
  reaproveitar esse mesmo modo, adicionando um header extra
  `X-Device-Id` que o middleware valida contra `pdv_devices`.

### 2. Restringir login de "caixa" ao PDV Desktop

Objetivo: o operador de caixa só consegue abrir o PDV pelo `.exe`
pareado à loja; dono/gerente continuam podendo acessar pelo navegador
normalmente.
- Em `app/middleware/pdv_loja_check.php` (ou no login), quando o papel
  do usuário for `caixa`, exigir que a requisição traga
  `X-Device-Id` válido e pareado — rejeitar login/sessão de caixa sem
  esse header.
- Como o dashboard usa cookie de sessão (não Bearer — ver regra já
  registrada no projeto) e o PDV Desktop pode enviar o header
  customizado via `pywebview`, dá para diferenciar as duas origens sem
  reescrever o fluxo de auth existente.

Nenhuma dessas mudanças foi aplicada ainda — recomendo tratá-las como
uma fase própria, com teste de regressão no login de gerente/dono antes
de ir para produção.
