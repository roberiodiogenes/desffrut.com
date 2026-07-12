#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Desffrut PDV Desktop — ponte Python <-> JavaScript (pywebview js_api).

hardware.js (public/js/pdv/hardware.js) detecta `window.pywebview` e, se
presente, chama `window.pywebview.api.hw_comando(cmd, payload)` em vez de
usar a extensão/Native Messaging. Este roteador espelha o mesmo protocolo
de comandos de extension/native-host/desffrut_print.py (status,
list_printers, print), só que executado em processo — sem stdin/stdout,
sem extensão de navegador, sem registro no Windows.
"""

import logging

from hardware import impressora, balanca
from config import carregar_device, salvar_device

log = logging.getLogger('desffrut_pdv_desktop')

VERSAO = '1.0.0'


class Api:
    """Exposta ao JS como window.pywebview.api."""

    def __init__(self):
        self._window = None

    def set_window(self, window):
        self._window = window

    # ── Roteador principal de hardware ──────────────────────────────────────
    def hw_comando(self, cmd: str, payload: dict = None) -> dict:
        payload = payload or {}
        log.debug(f'hw_comando: {cmd} | {str(payload)[:200]}')
        try:
            if cmd == 'status':
                return {
                    'ok': True,
                    'data': {
                        'versao': VERSAO,
                        'modo': 'pywebview_bridge',
                        'impressoras': impressora.listar_impressoras()[:10],
                        'balanca_portas': balanca.listar_portas(),
                    },
                }

            if cmd == 'list_printers':
                lista = impressora.listar_impressoras()
                return {'ok': True, 'data': {'impressoras': lista, 'total': len(lista)}}

            if cmd == 'print':
                return self._imprimir(payload)

            if cmd == 'balanca_listar_portas':
                return {'ok': True, 'data': {'portas': balanca.listar_portas()}}

            if cmd == 'balanca_ler':
                peso = balanca.ler_peso(payload.get('porta'))
                return {'ok': True, 'data': {'peso': peso}}

            return {'ok': False, 'erro': f'Comando desconhecido: {cmd}'}

        except Exception as e:
            log.exception(f'Erro no comando {cmd}')
            return {'ok': False, 'erro': str(e)}

    def _imprimir(self, payload: dict) -> dict:
        tipo = payload.get('tipo', '')
        nome = payload.get('impressora', '')
        if not nome:
            return {'ok': False, 'erro': 'Nome da impressora não informado.'}

        if tipo == 'cupom':
            raw = payload.get('raw', '')
            encoding = payload.get('encoding', 'cp850')
            if not raw:
                return {'ok': False, 'erro': 'Dados ESC/POS (raw) não informados.'}
            return {'ok': True, 'data': impressora.imprimir_cupom(nome, raw, encoding)}

        if tipo == 'etiqueta':
            protocolo = payload.get('protocolo', 'tspl')
            conteudo = payload.get('tspl', '') or payload.get('zpl', '')
            if not conteudo:
                return {'ok': False, 'erro': f'Conteúdo da etiqueta ({protocolo.upper()}) não informado.'}
            return {'ok': True, 'data': impressora.imprimir_etiqueta_raw(nome, conteudo, protocolo)}

        if tipo == 'inkjet':
            html = payload.get('html', '')
            papel = payload.get('papel', 'A4')
            if not html:
                return {'ok': False, 'erro': 'Conteúdo HTML não informado.'}
            return {'ok': True, 'data': impressora.imprimir_inkjet_html(nome, html, papel)}

        return {'ok': False, 'erro': f'Tipo de impressora desconhecido: {tipo}'}

    # ── Pareamento de dispositivo (restrição de acesso — ver README.md) ────
    def device_info(self) -> dict:
        return carregar_device()

    def parear_dispositivo(self, device_id: str, token: str, loja_id: int) -> dict:
        salvar_device({'device_id': device_id, 'token': token, 'loja_id': loja_id})
        log.info(f'Dispositivo pareado à loja {loja_id} (device_id={device_id})')
        return {'ok': True}
