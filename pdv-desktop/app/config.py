#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Desffrut PDV Desktop — configuração e caminhos.

Guarda: URL do PDV, token de pareamento do dispositivo e configuração de
log. Tudo persistido em %APPDATA%\\Desffrut\\ — a mesma pasta já usada
pelo host de impressão antigo (extension/native-host), para manter log e
dados do dispositivo num único lugar por máquina.
"""

import json
import logging
import os

APPDATA_DIR = os.path.join(os.environ.get('APPDATA', os.path.expanduser('~')), 'Desffrut')
os.makedirs(APPDATA_DIR, exist_ok=True)

LOG_FILE      = os.path.join(APPDATA_DIR, 'pdv_desktop.log')
DEVICE_FILE   = os.path.join(APPDATA_DIR, 'device.json')
SETTINGS_FILE = os.path.join(APPDATA_DIR, 'settings.json')

WINDOW_TITLE = 'Desffrut PDV'

# ── URL padrão do PDV ────────────────────────────────────────────────────
# Padrão aponta para o XAMPP local (ambiente de desenvolvimento atual).
# Sobrescreva criando %APPDATA%\Desffrut\settings.json com {"pdv_url": "..."}
# ou passando --url na linha de comando. Exemplos:
#   XAMPP local: http://localhost/desffrut.com/views/pdv/index.php  (padrão)
#   Produção:    https://SEU-DOMINIO-REAL/views/pdv/index.php
# Troque "SEU-DOMINIO-REAL" pelo domínio de produção quando for gerar o
# .exe para as lojas — desffrut.com sozinho não resolve (não é o domínio
# registrado).
_DEFAULT_URL = 'http://localhost/desffrut.com/views/pdv/index.php'


def _carregar_settings() -> dict:
    if os.path.exists(SETTINGS_FILE):
        try:
            with open(SETTINGS_FILE, 'r', encoding='utf-8') as f:
                return json.load(f)
        except Exception:
            pass
    return {}


_settings = _carregar_settings()
PDV_URL = _settings.get('pdv_url', _DEFAULT_URL)

# ── Logging ──────────────────────────────────────────────────────────────
logging.basicConfig(
    filename=LOG_FILE,
    level=logging.DEBUG,
    format='%(asctime)s [%(levelname)s] %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S',
)
log = logging.getLogger('desffrut_pdv_desktop')


# ── Pareamento de dispositivo (ver README.md, seção "Restrição de acesso") ─
def carregar_device() -> dict:
    """Retorna {'device_id', 'token', 'loja_id'} ou {} se o PDV ainda não
    foi pareado a nenhuma loja nesta máquina."""
    if os.path.exists(DEVICE_FILE):
        try:
            with open(DEVICE_FILE, 'r', encoding='utf-8') as f:
                return json.load(f)
        except Exception:
            pass
    return {}


def salvar_device(dados: dict) -> None:
    with open(DEVICE_FILE, 'w', encoding='utf-8') as f:
        json.dump(dados, f, ensure_ascii=False, indent=2)
