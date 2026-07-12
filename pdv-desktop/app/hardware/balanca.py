#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Desffrut PDV Desktop — leitura de balança via porta serial nativa.

Substitui a Web Serial API (usada em hardware.js) por pyserial direto.
Isso resolve boa parte das reclamações de balança: Web Serial exige
HTTPS, diálogo de permissão a cada sessão em alguns navegadores, e tem
suporte irregular a drivers USB-serial (Toledo/Filizola). pyserial
acessa a porta COM diretamente, sem essas limitações do navegador.
"""

import logging
import re

log = logging.getLogger('desffrut_pdv_desktop')

try:
    import serial
    import serial.tools.list_ports
    SERIAL_OK = True
except ImportError:
    SERIAL_OK = False
    log.warning('pyserial não instalado — leitura de balança desabilitada.')

# Mesmo padrão usado no lado JS (hardware.js): número + unidade "k"/"kg".
_PESO_RE = re.compile(r'[+\-]?\s*(\d{1,3}[.,]\d{1,3})\s*[kK]')


def listar_portas() -> list:
    """Lista portas seriais disponíveis (ex.: ['COM3', 'COM4'])."""
    if not SERIAL_OK:
        return []
    try:
        return [p.device for p in serial.tools.list_ports.comports()]
    except Exception as e:
        log.error(f'listar_portas: {e}')
        return []


def ler_peso(porta: str = None, baudrate: int = 9600, timeout: float = 4.0) -> float:
    """
    Lê o peso atual da balança conectada na porta serial informada.
    Se `porta` for None, usa a primeira porta disponível.

    Levanta exceção se a porta não existir, não responder a tempo, ou
    se pyserial não estiver instalado.
    """
    if not SERIAL_OK:
        raise RuntimeError('pyserial não instalado. Rode: pip install pyserial')

    if not porta:
        portas = listar_portas()
        if not portas:
            raise RuntimeError('Nenhuma porta serial encontrada. Verifique se a balança está conectada.')
        porta = portas[0]

    log.info(f'Lendo balança em {porta} (baudrate={baudrate}, timeout={timeout}s)')

    with serial.Serial(porta, baudrate=baudrate, bytesize=8, parity='N',
                        stopbits=1, timeout=timeout) as ser:
        buffer = ''
        import time
        deadline = time.time() + timeout
        while time.time() < deadline:
            chunk = ser.read(64)
            if not chunk:
                continue
            buffer += chunk.decode('ascii', errors='replace')
            match = _PESO_RE.search(buffer)
            if match:
                peso = float(match.group(1).replace(',', '.'))
                log.info(f'Peso lido: {peso} kg')
                return peso if peso > 0 else 0.0
            if len(buffer) > 256:
                buffer = buffer[-128:]

    raise TimeoutError('Tempo esgotado. Verifique se a balança está ligada e estável.')
