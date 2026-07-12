@echo off
setlocal EnableDelayedExpansion

echo.
echo ============================================================
echo   DESFFRUT - Recriar Manifest do Host Nativo
echo   (use quando aparecer "Access is forbidden")
echo ============================================================
echo.

net session >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERRO] Execute como Administrador.
    pause & exit /b 1
)

set "INSTALL_DIR=C:\desffrut\native-host"

REM Localiza o executavel (aceita onedir, onefile ou wrapper bat)
set "EXE1=!INSTALL_DIR!\desffrut_print\desffrut_print.exe"
set "EXE2=!INSTALL_DIR!\desffrut_print.exe"
set "BAT=!INSTALL_DIR!\run_host.bat"
set "HOST_PATH="

if exist "!EXE1!" (
    set "HOST_PATH=!EXE1!"
    goto :found
)
if exist "!EXE2!" (
    set "HOST_PATH=!EXE2!"
    goto :found
)
if exist "!BAT!" (
    set "HOST_PATH=!BAT!"
    goto :found
)

echo [ERRO] Host nativo nao encontrado em !INSTALL_DIR!
echo        Execute install_chrome.bat primeiro para instalar o host Python.
pause & exit /b 1

:found
echo Host encontrado: !HOST_PATH!
echo.

REM Solicita ID da extensao
echo Abra chrome://extensions no Chrome e copie o ID da extensao.
echo O ID tem 32 letras minusculas (exemplo: abcdefghijklmnopabcdefghijklmnop)
echo.
set /p EXT_ID="Cole o ID aqui: "

REM Remove espacos e tabs do ID (limpeza)
set "EXT_ID=!EXT_ID: =!"
set "EXT_ID=!EXT_ID:	=!"

if "!EXT_ID!"=="" (
    echo [ERRO] ID nao pode ser vazio.
    pause & exit /b 1
)

echo.
echo ID recebido : [!EXT_ID!]
echo.

REM Gera JSON via PowerShell (evita problemas de encoding do echo)
set "MANIFEST=!INSTALL_DIR!\desffrut_print.json"
set "HP_PS=!HOST_PATH:\=\\!"

powershell -NoProfile -ExecutionPolicy Bypass -Command "$h = '!HP_PS!'; $id = '!EXT_ID!'; $j = '{\"name\":\"desffrut_print_host\",\"description\":\"Desffrut Hardware - Host nativo\",\"path\":\"' + $h + '\",\"type\":\"stdio\",\"allowed_origins\":[\"chrome-extension://' + $id + '/\"]}'; [System.IO.File]::WriteAllText('!MANIFEST!', $j, [System.Text.Encoding]::UTF8)"

if not exist "!MANIFEST!" (
    echo [ERRO] Falha ao criar manifest JSON.
    pause & exit /b 1
)

echo Manifest gerado: !MANIFEST!
echo.
echo Conteudo:
type "!MANIFEST!"
echo.
echo.

REM Registra Chrome
echo Registrando no Chrome...
reg add "HKCU\Software\Google\Chrome\NativeMessagingHosts\desffrut_print_host" /ve /t REG_SZ /d "!MANIFEST!" /f >nul
echo Chrome: OK

REM Registra Edge se instalado
reg query "HKCU\Software\Microsoft\Edge" >nul 2>&1
if %errorlevel% equ 0 (
    echo Registrando no Edge...
    reg add "HKCU\Software\Microsoft\Edge\NativeMessagingHosts\desffrut_print_host" /ve /t REG_SZ /d "!MANIFEST!" /f >nul
    echo Edge: OK
)

echo.
echo ============================================================
echo   MANIFEST RECRIADO COM SUCESSO!
echo ============================================================
echo.
echo   Agora:
echo   1. chrome://extensions -- clique no relogio circular na extensao Desffrut
echo   2. Recarregue o Dashboard
echo   3. Clique "Atualizar Status" -- deve ficar verde
echo.
pause
