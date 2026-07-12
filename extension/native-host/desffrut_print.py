#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Desffrut Hardware — Host Nativo Python (v2.0.0)

Recebe comandos JSON do Chrome/Edge via stdin (protocolo Native Messaging)
e envia jobs de impressão para impressoras Windows via win32print.

Suporte:
  - Cupom térmico (ESC/POS raw) — impressoras 80mm/58mm
  - Etiqueta (ZPL raw)          — Tomate MKV-006 e compatíveis
  - Jato de tinta               — qualquer impressora Windows via HTML/texto

Log: %APPDATA%\\Desffrut\\print_host.log
"""

import sys
import json
import struct
import os
import logging
import traceback
from datetime import datetime

# ── Configuração de log ──────────────────────────────────────────────────
LOG_DIR  = os.path.join(os.environ.get('APPDATA', os.path.expanduser('~')), 'Desffrut')
LOG_FILE = os.path.join(LOG_DIR, 'print_host.log')

os.makedirs(LOG_DIR, exist_ok=True)

logging.basicConfig(
    filename=LOG_FILE,
    level=logging.DEBUG,
    format='%(asctime)s [%(levelname)s] %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S',
)
log = logging.getLogger('desffrut_host')

# ── Versão ───────────────────────────────────────────────────────────────
VERSAO = '2.0.0'

# ── Importa win32print (pywin32) ─────────────────────────────────────────
try:
    import win32print
    import win32api
    WIN32_OK = True
except ImportError:
    WIN32_OK = False
    log.warning('pywin32 não instalado. Impressão desabilitada.')

# ════════════════════════════════════════════════════════════════════════
# PROTOCOLO NATIVE MESSAGING (stdin/stdout)
# ════════════════════════════════════════════════════════════════════════

def _ler_mensagem():
    """Lê uma mensagem JSON do stdin (4 bytes length + JSON UTF-8)."""
    raw_length = sys.stdin.buffer.read(4)
    if not raw_length or len(raw_length) < 4:
        return None
    length = struct.unpack('<I', raw_length)[0]
    raw_msg = sys.stdin.buffer.read(length)
    return json.loads(raw_msg.decode('utf-8'))

def _enviar_resposta(data: dict):
    """Envia resposta JSON para o stdout (4 bytes length + JSON UTF-8)."""
    encoded = json.dumps(data, ensure_ascii=False).encode('utf-8')
    sys.stdout.buffer.write(struct.pack('<I', len(encoded)))
    sys.stdout.buffer.write(encoded)
    sys.stdout.buffer.flush()

# ════════════════════════════════════════════════════════════════════════
# IMPRESSÃO
# ════════════════════════════════════════════════════════════════════════

def listar_impressoras() -> list:
    """Retorna lista de impressoras instaladas no Windows."""
    if not WIN32_OK:
        return []
    try:
        flags  = win32print.PRINTER_ENUM_LOCAL | win32print.PRINTER_ENUM_CONNECTIONS
        printers = [p[2] for p in win32print.EnumPrinters(flags)]
        return sorted(printers)
    except Exception as e:
        log.error(f'listar_impressoras: {e}')
        return []

def _imprimir_raw(nome_impressora: str, dados: bytes, doc_name: str = 'Desffrut') -> bool:
    """
    Envia dados raw para a impressora via win32print.
    Funciona para ESC/POS e ZPL — a impressora interpreta diretamente.
    """
    if not WIN32_OK:
        raise RuntimeError('pywin32 não instalado. Execute: pip install pywin32')

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
    """Imprime cupom ESC/POS."""
    log.info(f'Imprimindo cupom em "{impressora}" ({len(raw)} chars)')
    try:
        dados = raw.encode(encoding, errors='replace')
        _imprimir_raw(impressora, dados, 'Desffrut-Cupom')
        log.info('Cupom impresso com sucesso.')
        return {'ok': True, 'msg': 'Cupom impresso com sucesso.'}
    except Exception as e:
        log.error(f'imprimir_cupom: {e}\n{traceback.format_exc()}')
        raise

def imprimir_etiqueta_raw(impressora: str, conteudo: str, protocolo: str = 'tspl') -> dict:
    """
    Imprime etiqueta via protocolo raw (TSPL ou ZPL).
    Ambos são ASCII/Latin-1 enviados como bytes crus — a impressora interpreta.
    Tomate MKV-006 usa TSPL (TSC Printer Language).
    """
    log.info(f'Imprimindo etiqueta {protocolo.upper()} em "{impressora}" ({len(conteudo)} chars)')
    try:
        dados = conteudo.encode('latin-1', errors='replace')
        _imprimir_raw(impressora, dados, f'Desffrut-Etiqueta-{protocolo.upper()}')
        log.info(f'Etiqueta {protocolo.upper()} impressa com sucesso.')
        return {'ok': True, 'msg': f'Etiqueta impressa com sucesso ({protocolo.upper()}).'}
    except Exception as e:
        log.error(f'imprimir_etiqueta_raw: {e}\n{traceback.format_exc()}')
        raise

def imprimir_inkjet_html(impressora: str, html: str, papel: str = 'A4') -> dict:
    """
    Impressão em jato de tinta/laser via arquivo HTML temporário.
    Usa ShellExecute para abrir e imprimir o HTML no navegador padrão.
    """
    import tempfile
    import win32api
    import win32con

    log.info(f'Imprimindo HTML em "{impressora}" papel={papel}')
    try:
        # Salva HTML em arquivo temporário
        with tempfile.NamedTemporaryFile(
            mode='w', suffix='.html', delete=False, encoding='utf-8'
        ) as f:
            f.write(html)
            tmp_path = f.name

        # Define impressora padrão temporariamente
        printer_orig = win32print.GetDefaultPrinter()
        try:
            win32print.SetDefaultPrinter(impressora)
            win32api.ShellExecute(0, 'print', tmp_path, None, '.', win32con.SW_HIDE)
        finally:
            win32print.SetDefaultPrinter(printer_orig)

        import time
        time.sleep(2)  # Aguarda o browser abrir e imprimir

        try:
            os.unlink(tmp_path)
        except Exception:
            pass

        log.info('HTML enviado para impressão.')
        return {'ok': True, 'msg': 'Documento enviado para impressão.'}

    except Exception as e:
        log.error(f'imprimir_inkjet_html: {e}\n{traceback.format_exc()}')
        raise

# ════════════════════════════════════════════════════════════════════════
# ROTEADOR DE COMANDOS
# ════════════════════════════════════════════════════════════════════════

def processar_comando(msg: dict) -> dict:
    cmd = msg.get('cmd', '')
    log.debug(f'Comando recebido: {cmd} | payload: {str(msg)[:200]}')

    try:
        if cmd == 'status':
            return {
                'ok': True,
                'versao': VERSAO,
                'python_version': sys.version.split()[0],
                'win32_ok': WIN32_OK,
                'impressoras': listar_impressoras()[:10],  # primeiras 10
                'log_file': LOG_FILE,
            }

        elif cmd == 'list_printers':
            lista = listar_impressoras()
            return {'ok': True, 'impressoras': lista, 'total': len(lista)}

        elif cmd == 'print':
            tipo       = msg.get('tipo', '')
            impressora = msg.get('impressora', '')
            if not impressora:
                return {'ok': False, 'erro': 'Nome da impressora não informado.'}

            if tipo == 'cupom':
                raw      = msg.get('raw', '')
                encoding = msg.get('encoding', 'cp850')
                if not raw:
                    return {'ok': False, 'erro': 'Dados ESC/POS (raw) não informados.'}
                return imprimir_cupom(impressora, raw, encoding)

            elif tipo == 'etiqueta':
                protocolo = msg.get('protocolo', 'tspl')
                # Aceita TSPL (campo 'tspl') ou ZPL legado (campo 'zpl')
                conteudo = msg.get('tspl', '') or msg.get('zpl', '')
                if not conteudo:
                    return {'ok': False, 'erro': f'Conteúdo da etiqueta ({protocolo.upper()}) não informado.'}
                if protocolo in ('tspl', 'zpl'):
                    return imprimir_etiqueta_raw(impressora, conteudo, protocolo)
                else:
                    return {'ok': False, 'erro': f'Protocolo de etiqueta não suportado: {protocolo}'}

            elif tipo == 'inkjet':
                html  = msg.get('html', '')
                papel = msg.get('papel', 'A4')
                if not html:
                    return {'ok': False, 'erro': 'Conteúdo HTML não informado.'}
                return imprimir_inkjet_html(impressora, html, papel)

            else:
                return {'ok': False, 'erro': f'Tipo de impressora desconhecido: {tipo}'}

        else:
            return {'ok': False, 'erro': f'Comando desconhecido: {cmd}'}

    except Exception as e:
        log.error(f'Erro ao processar "{cmd}": {e}\n{traceback.format_exc()}')
        return {'ok': False, 'erro': str(e)}

# ════════════════════════════════════════════════════════════════════════
# LOOP PRINCIPAL
# ════════════════════════════════════════════════════════════════════════

def main():
    log.info(f'=== Desffrut Print Host v{VERSAO} iniciado ===')
    log.info(f'Python: {sys.version}')
    log.info(f'pywin32: {"OK" if WIN32_OK else "NÃO INSTALADO"}')

    while True:
        try:
            msg = _ler_mensagem()
            if msg is None:
                log.info('stdin encerrado — finalizando host.')
                break

            resposta = processar_comando(msg)
            _enviar_resposta(resposta)

        except Exception as e:
            log.error(f'Erro fatal no loop: {e}\n{traceback.format_exc()}')
            try:
                _enviar_resposta({'ok': False, 'erro': f'Erro interno do host: {e}'})
            except Exception:
                pass
            break

    log.info('=== Host finalizado ===')

if __name__ == '__main__':
    main()
