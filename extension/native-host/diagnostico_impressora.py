#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Desffrut - Diagnostico de Impressora de Etiqueta
Testa ZPL e TSPL direto na impressora para descobrir qual protocolo funciona.
Execute: python diagnostico_impressora.py
"""

import sys
import time

try:
    import win32print
    WIN32_OK = True
except ImportError:
    WIN32_OK = False
    print("[ERRO] pywin32 nao instalado.")
    print("       Execute: pip install pywin32")
    input("Pressione Enter...")
    sys.exit(1)


def listar_impressoras():
    flags = win32print.PRINTER_ENUM_LOCAL | win32print.PRINTER_ENUM_CONNECTIONS
    return [p[2] for p in win32print.EnumPrinters(flags)]


def enviar_raw(nome: str, dados: bytes, doc: str = "TESTE") -> bool:
    """Envia bytes crus (RAW) para a impressora."""
    try:
        h = win32print.OpenPrinter(nome)
        try:
            job = win32print.StartDocPrinter(h, 1, (doc, None, "RAW"))
            try:
                win32print.StartPagePrinter(h)
                win32print.WritePrinter(h, dados)
                win32print.EndPagePrinter(h)
            finally:
                win32print.EndDocPrinter(h)
        finally:
            win32print.ClosePrinter(h)
        return True
    except Exception as e:
        print(f"  ERRO ao enviar: {e}")
        return False


def testar_zpl(nome: str):
    """Envia etiqueta de teste em ZPL."""
    print("  Enviando ZPL (Zebra)...")
    zpl = (
        "^XA"
        "^PW640"      # largura 80mm @ 203dpi
        "^LL320"      # altura 40mm  @ 203dpi
        "^FO50,30^A0N,40,40^FDTESTE ZPL^FS"
        "^FO50,90^A0N,28,28^FDDesffrut - MDK-006^FS"
        "^FO50,130^A0N,24,24^FDZPL funcionou!^FS"
        "^FO50,180^BY2^BCN,60,Y,N,N^FD12345678^FS"
        "^XZ"
    )
    ok = enviar_raw(nome, zpl.encode("latin-1"), "TESTE-ZPL")
    if ok:
        print("  ZPL enviado. Aguardando 3s...")
        time.sleep(3)
        return True
    return False


def testar_tspl(nome: str):
    """Envia etiqueta de teste em TSPL (TSC)."""
    print("  Enviando TSPL (TSC/Argox)...")
    tspl = (
        "SIZE 80 mm, 40 mm\r\n"
        "GAP 3 mm, 0\r\n"
        "DIRECTION 0\r\n"
        "CLS\r\n"
        "TEXT 50,20,\"3\",0,1,1,\"TESTE TSPL\"\r\n"
        "TEXT 50,70,\"3\",0,1,1,\"Desffrut MDK-006\"\r\n"
        "TEXT 50,110,\"3\",0,1,1,\"TSPL funcionou!\"\r\n"
        "BARCODE 50,150,\"128\",60,1,0,2,2,\"12345678\"\r\n"
        "PRINT 1,1\r\n"
    )
    ok = enviar_raw(nome, tspl.encode("latin-1"), "TESTE-TSPL")
    if ok:
        print("  TSPL enviado. Aguardando 3s...")
        time.sleep(3)
        return True
    return False


def testar_texto_simples(nome: str):
    """Envia texto simples (fallback para verificar se RAW funciona)."""
    print("  Enviando texto simples (ASCII)...")
    texto = b"TESTE DESFFRUT\r\nMDK-006 RAW OK\r\n\r\n\r\n"
    ok = enviar_raw(nome, texto, "TESTE-TXT")
    if ok:
        print("  Texto enviado.")
        time.sleep(2)
        return True
    return False


def info_driver(nome: str):
    """Exibe informacoes do driver da impressora."""
    try:
        h = win32print.OpenPrinter(nome)
        info = win32print.GetPrinter(h, 2)
        win32print.ClosePrinter(h)
        print(f"  Driver    : {info.get('pDriverName', '?')}")
        print(f"  Porta     : {info.get('pPortName', '?')}")
        print(f"  Processador: {info.get('pPrintProcessor', '?')}")
        print(f"  Tipo dados : {info.get('pDatatype', '?')}")
    except Exception as e:
        print(f"  (nao foi possivel obter info do driver: {e})")


# ── Main ──────────────────────────────────────────────────────────────────────

print()
print("=" * 60)
print("  DESFFRUT - Diagnostico de Impressora de Etiqueta")
print("=" * 60)
print()

impressoras = listar_impressoras()
if not impressoras:
    print("[ERRO] Nenhuma impressora encontrada.")
    input("Pressione Enter...")
    sys.exit(1)

print("Impressoras instaladas:")
for i, p in enumerate(impressoras, 1):
    print(f"  {i}. {p}")
print()

escolha = input("Digite o numero ou o nome da impressora: ").strip()
if escolha.isdigit():
    idx = int(escolha) - 1
    if 0 <= idx < len(impressoras):
        PRINTER = impressoras[idx]
    else:
        print("[ERRO] Numero invalido.")
        input("Pressione Enter...")
        sys.exit(1)
else:
    PRINTER = escolha

print()
print(f"Impressora selecionada: {PRINTER}")
print()
print("Informacoes do driver:")
info_driver(PRINTER)
print()

print("=" * 60)
print("TESTE 1: ZPL (Zebra Programming Language)")
print("=" * 60)
ok_zpl = testar_zpl(PRINTER)
resp = input("A etiqueta foi impressa? [s/n]: ").strip().lower()
if resp == 's':
    print()
    print("RESULTADO: Impressora usa ZPL.")
    print("O hardware.js esta configurado corretamente.")
    print()
    input("Pressione Enter para sair.")
    sys.exit(0)

print()
print("=" * 60)
print("TESTE 2: TSPL (TSC Printer Language)")
print("=" * 60)
ok_tspl = testar_tspl(PRINTER)
resp = input("A etiqueta foi impressa? [s/n]: ").strip().lower()
if resp == 's':
    print()
    print("RESULTADO: Impressora usa TSPL.")
    print("O hardware.js precisa ser atualizado para gerar TSPL em vez de ZPL.")
    print()
    input("Pressione Enter para sair.")
    sys.exit(10)

print()
print("=" * 60)
print("TESTE 3: Texto puro (verifica se RAW passthrough funciona)")
print("=" * 60)
testar_texto_simples(PRINTER)
resp = input("Alguma saida apareceu na impressora? [s/n]: ").strip().lower()
if resp == 'n':
    print()
    print("RESULTADO: RAW passthrough nao funciona com este driver.")
    print("Solucao: reinstale a impressora com driver 'Generic / Text Only'")
    print("         ou 'Zebra Technologies ZPL'")
else:
    print()
    print("RESULTADO: RAW funciona mas os comandos ZPL/TSPL sao ignorados.")
    print("Verifique o manual da MDK-006 para descobrir o protocolo correto.")

print()
input("Pressione Enter para sair.")
