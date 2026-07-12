@echo off
setlocal EnableDelayedExpansion

echo.
echo ============================================================
echo   DESFFRUT HARDWARE - Instalador do Host Nativo (Edge)
echo   Versao 2.0.0
echo ============================================================
echo.

net session >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERRO] Execute como Administrador.
    pause & exit /b 1
)

set "INSTALL_DIR=C:\desffrut\native-host"
set "MANIFEST_EDGE=%INSTALL_DIR%\desffrut_print_edge.json"
set "REG_KEY=HKCU\Software\Microsoft\Edge\NativeMessagingHosts\desffrut_print_host"

REM Verifica se o host ja esta instalado (pelo Chrome)
if not exist "%INSTALL_DIR%\desffrut_print.exe" (
    if not exist "%INSTALL_DIR%\run_host.bat" (
        echo [AVISO] Host nativo nao encontrado.
        echo         Execute install_chrome.bat primeiro para instalar o host Python.
        pause & exit /b 1
    )
)

echo [1/2] Configurando manifest para Edge...
echo.
echo   1. Abra edge://extensions no Edge
echo   2. Ative "Modo desenvolvedor"
echo   3. Carregue a pasta: extension\edge\
echo   4. Copie o ID da extensao
echo.
set /p EXT_ID="   Cole o ID da extensao Edge aqui: "
if "%EXT_ID%"=="" set "EXT_ID=SUBSTITUA_PELO_ID_REAL"

REM Determina caminho do executavel
if exist "%INSTALL_DIR%\desffrut_print.exe" (
    set "HOST_PATH=%INSTALL_DIR%\desffrut_print.exe"
) else (
    set "HOST_PATH=%INSTALL_DIR%\run_host.bat"
)
set "HOST_PATH_ESC=!HOST_PATH:\=\\!"

echo {> "%MANIFEST_EDGE%"
echo   "name": "desffrut_print_host",>> "%MANIFEST_EDGE%"
echo   "description": "Desffrut Hardware - Host nativo (Edge)",>> "%MANIFEST_EDGE%"
echo   "path": "!HOST_PATH_ESC!",>> "%MANIFEST_EDGE%"
echo   "type": "stdio",>> "%MANIFEST_EDGE%"
echo   "allowed_origins": [>> "%MANIFEST_EDGE%"
echo     "chrome-extension://%EXT_ID%/">> "%MANIFEST_EDGE%"
echo   ]>> "%MANIFEST_EDGE%"
echo }>> "%MANIFEST_EDGE%"

echo [2/2] Registrando no Windows (HKCU - Edge)...
reg add "%REG_KEY%" /ve /t REG_SZ /d "%MANIFEST_EDGE%" /f >nul
if %errorlevel% equ 0 (
    echo     Registro Edge: OK
) else (
    echo [ERRO] Falha ao registrar.
    pause & exit /b 1
)

echo.
echo ============================================================
echo   INSTALACAO EDGE CONCLUIDA!
echo ============================================================
echo   Recarregue a extensao em edge://extensions e teste.
echo.
pause
