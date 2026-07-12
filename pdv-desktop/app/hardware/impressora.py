#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Desffrut PDV Desktop — impressão local (cupom, etiqueta, inkjet).

Portado de extension/native-host/desffrut_print.py: mesma lógica de
impressão via win32print (RAW), mas chamada em processo pelo bridge.py
em vez de receber comandos via stdin/stdout (Native Messaging). Isso
elimina a dependência de extensão de navegador + registro no Windows —
o problema que causava a maior parte das falhas de hardware relatadas.

Suporte:
  - Cupom térmico (ESC/POS raw)     — impressoras 80mm/58mm
  - Etiqueta (TSPL/ZPL raw)         — Tomate MKV-006 e compatíveis
  - Jato de tinta / laser           — qualquer impressora Windows via HTML
"""

import logging
import os
import tempfile
import time
import traceback

log = logging.getLogger('desffrut_pdv_desktop')

try:
    import win32print
    import win32api
    import win32con
    WIN32_OK = True
except ImportError:
    WIN32_OK = False
    log.warning('pywin32 não instalado — impressão desabilitada nesta máquina.')


def listar_impressoras() -> list:
    """Retorna lista de impressoras instaladas no Windows."""
    if not WIN32_OK:
        return []
    try:
        flags = win32print.PRINTER_ENUM_LOCAL | win32print.PRINTER_ENUM_CONNECTIONS
        printers = [p[2] for p in win32print.EnumPrinters(flags)]
        return sorted(printers)
    except Exception as e:
        log.error(f'listar_impressoras: {e}')
        return []


def _imprimir_raw(nome_impressora: str, dados: bytes, doc_name: str = 'Desffrut') -> bool:
    """Envia dados raw para a impressora via win32print. Funciona para
    ESC/POS e TSPL/ZPL — a própria impressora interpreta o protocolo."""
    if not WIN32_OK:
        raise RuntimeError('pywin32 não instalado. Rode: pip install pywin32')

    handle = win32print.OpenPrinter(nome_impressora)
    try:
        job = win32print.StartDocPrinter(handle, 1, (doc_name, None, 'RAW'))
        try:
            win32print.StartPagePrinter(handle)
            win32print.WritePrinter(handle, dados)
            win32print.EndPagePrinter(handle)
        finally:
            win32print.EndDocPrinter(handle)
    finally:
        win32print.ClosePrinter(handle)

    return True


def imprimir_cupom(impressora: str, raw: str, encoding: str = 'cp850') -> dict:
    """Imprime cupom ESC/POS (venda, sangria, fechamento)."""
    log.info(f'Imprimindo cupom em "{impressora}" ({len(raw)} chars)')
    try:
        dados = raw.encode(encoding, errors='replace')
        _imprimir_raw(impressora, dados, 'Desffrut-Cupom')
        return {'ok': True, 'msg': 'Cupom impresso com sucesso.'}
    except Exception as e:
        log.error(f'imprimir_cupom: {e}\n{traceback.format_exc()}')
        raise


def imprimir_etiqueta_raw(impressora: str, conteudo: str, protocolo: str = 'tspl') -> dict:
    """Imprime etiqueta via protocolo raw (TSPL ou ZPL). Tomate MKV-006
    usa TSPL (TSC Printer Language)."""
    log.info(f'Imprimindo etiqueta {protocolo.upper()} em "{impressora}" ({len(conteudo)} chars)')
    try:
        dados = conteudo.encode('latin-1', errors='replace')
        _imprimir_raw(impressora, dados, f'Desffrut-Etiqueta-{protocolo.upper()}')
        return {'ok': True, 'msg': f'Etiqueta impressa com sucesso ({protocolo.upper()}).'}
    except Exception as e:
        log.error(f'imprimir_etiqueta_raw: {e}\n{traceback.format_exc()}')
        raise


def imprimir_inkjet_html(impressora: str, html: str, papel: str = 'A4') -> dict:
    """Impressão em jato de tinta/laser via arquivo HTML temporário e
    ShellExecute, definindo a impressora como padrão temporariamente."""
    if not WIN32_OK:
        raise RuntimeError('pywin32 não instalado. Rode: pip install pywin32')

    log.info(f'Imprimindo HTML em "{impressora}" papel={papel}')
    try:
        with tempfile.NamedTemporaryFile(
            mode='w', suffix='.html', delete=False, encoding='utf-8'
        ) as f:
            f.write(html)
            tmp_path = f.name

        printer_orig = win32print.GetDefaultPrinter()
        try:
            win32print.SetDefaultPrinter(impressora)
            win32api.ShellExecute(0, 'print', tmp_path, None, '.', win32con.SW_HIDE)
        finally:
            win32print.SetDefaultPrinter(printer_orig)

        time.sleep(2)  # aguarda o processo de impressão iniciar

        try:
            os.unlink(tmp_path)
        except Exception:
            pass

        return {'ok': True, 'msg': 'Documento enviado para impressão.'}

    except Exception as e:
        log.error(f'imprimir_inkjet_html: {e}\n{traceback.format_exc()}')
        raise
