# -*- mode: python ; coding: utf-8 -*-
# Desffrut PDV Desktop — spec do PyInstaller.
# Gerar o .exe (rodar de dentro de build/, com o venv ativado):
#   pyinstaller pdv_desktop.spec --clean
# O executável final fica em build/dist/DesffrutPDV.exe

import os

block_cipher = None
APP_DIR = os.path.join(os.path.dirname(os.path.abspath(SPEC)), '..', 'app')

a = Analysis(
    [os.path.join(APP_DIR, 'main.py')],
    pathex=[APP_DIR],
    binaries=[],
    datas=[],
    hiddenimports=[
        'win32print',
        'win32api',
        'win32con',
        'serial',
        'serial.tools.list_ports',
    ],
    hookspath=[],
    runtime_hooks=[],
    excludes=[],
    cipher=block_cipher,
)

pyz = PYZ(a.pure, a.zipped_data, cipher=block_cipher)

exe = EXE(
    pyz,
    a.scripts,
    a.binaries,
    a.zipfiles,
    a.datas,
    [],
    name='DesffrutPDV',
    debug=False,
    bootloader_ignore_signals=False,
    strip=False,
    upx=True,
    upx_exclude=[],
    runtime_tmpdir=None,
    console=False,          # sem janela de terminal atrás do PDV
    icon=None,              # troque por 'assets/icon.ico' quando existir
    onefile=True,
)
