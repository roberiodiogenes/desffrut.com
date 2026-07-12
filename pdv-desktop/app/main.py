#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Desffrut PDV Desktop — ponto de entrada.

Abre uma janela nativa (pywebview) carregando o PDV web já existente
(mesmo HTML/JS/CSS de views/pdv/index.php) e expõe uma API Python
(bridge.Api) para o JS chamar diretamente — sem extensão de navegador,
sem Native Messaging, sem instalação por estação.

Uso:
    python main.py                  # janela normal, URL de config.py
    python main.py --url <url>      # sobrescreve a URL
    python main.py --kiosk          # tela cheia, sem moldura (uso em loja)
    python main.py --devtools       # habilita DevTools para debug
"""

import argparse

import webview

from bridge import Api
from config import PDV_URL, WINDOW_TITLE, log


def main():
    parser = argparse.ArgumentParser(description='Desffrut PDV Desktop')
    parser.add_argument('--url', default=PDV_URL, help='URL do PDV a carregar')
    parser.add_argument('--kiosk', action='store_true', help='Tela cheia, sem moldura')
    parser.add_argument('--devtools', action='store_true', help='Habilita DevTools (debug)')
    args = parser.parse_args()

    log.info('=== Desffrut PDV Desktop iniciando ===')
    log.info(f'URL: {args.url}')

    api = Api()

    window = webview.create_window(
        WINDOW_TITLE,
        url=args.url,
        js_api=api,
        width=1366,
        height=768,
        min_size=(1024, 640),
        fullscreen=args.kiosk,
        frameless=args.kiosk,
        confirm_close=True,
    )
    api.set_window(window)

    webview.start(debug=args.devtools, http_server=False)
    log.info('=== Desffrut PDV Desktop finalizado ===')


if __name__ == '__main__':
    try:
        main()
    except Exception:
        log.exception('Erro fatal ao iniciar o PDV Desktop')
        raise
