@echo off
setlocal EnableDelayedExpansion

echo.
echo ============================================================
echo   DESFFRUT HARDWARE - Instalador do Host Nativo (Chrome)
echo   Versao 2.0.0
echo ============================================================
echo.

REM Verifica permissao de Administrador
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERRO] Execute como Administrador.
    echo        Clique com botao direito e escolha "Executar como administrador".
    pause
    exit /b 1
)

REM Define caminhos
set "INSTALL_DIR=C:\desffrut\native-host"
set "PY_HOST=%INSTALL_DIR%\desffrut_print.py"
set "EXE_HOST=%INSTALL_DIR%\desffrut_print\desffrut_print.exe"
set "MANIFEST=%INSTALL_DIR%\desffrut_print.json"
set "REG_KEY=HKCU\Software\Google\Chrome\NativeMessagingHosts\desffrut_print_host"
set "SCRIPT_DIR=%~dp0"

echo [1/5] Criando pasta de instalacao...
if not exist "%INSTALL_DIR%" mkdir "%INSTALL_DIR%"

REM Copia arquivos
echo [2/5] Copiando arquivos do host...
copy /Y "%SCRIPT_DIR%desffrut_print.py" "%PY_HOST%" >nul
if exist "%SCRIPT_DIR%requirements.txt" (
    copy /Y "%SCRIPT_DIR%requirements.txt" "%INSTALL_DIR%\requirements.txt" >nul
)
echo     Arquivos copiados para %INSTALL_DIR%

REM Verifica Python
echo [3/5] Verificando Python...
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo     Python nao encontrado. Baixando instalador...
    curl -L -o "%TEMP%\python_installer.exe" "https://www.python.org/ftp/python/3.12.0/python-3.12.0-amd64.exe"
    "%TEMP%\python_installer.exe" /quiet InstallAllUsers=0 PrependPath=1 Include_test=0
    del "%TEMP%\python_installer.exe" 2>nul
)
echo     Python: OK

REM Instala dependencias
echo [4/5] Instalando dependencias Python (pywin32, pyinstaller)...
python -m pip install --quiet --upgrade pywin32 pyinstaller
if %errorlevel% neq 0 (
    echo [AVISO] Falha ao instalar dependencias. Tentando pip direto...
    pip install --quiet pywin32 pyinstaller
)
echo     Dependencias: OK

REM Gera executavel com PyInstaller (--onedir e mais rapido que --onefile)
echo     Gerando executavel standalone...
cd /d "%INSTALL_DIR%"
python -m PyInstaller --onedir --distpath "%INSTALL_DIR%" --workpath "%TEMP%\desffrut_build" --name desffrut_print --noconsole "%PY_HOST%" >nul 2>&1
if exist "%EXE_HOST%" (
    set "HOST_PATH=%EXE_HOST%"
    echo     Executavel gerado: desffrut_print\desffrut_print.exe
) else (
    set "HOST_PATH=%INSTALL_DIR%\run_host.bat"
    echo @echo off > "%HOST_PATH%"
    echo python "%PY_HOST%" %%* >> "%HOST_PATH%"
    echo     Usando wrapper Python (sem .exe standalone)
)

REM ID da extensao
echo.
echo ============================================================
echo   IMPORTANTE: ID da Extensao Chrome
echo ============================================================
echo.
echo   1. Abra chrome://extensions no Chrome
echo   2. Ative "Modo desenvolvedor"
echo   3. Carregue a pasta: extension\chrome\
echo   4. Copie o ID da extensao (32 letras, ex: abcdefgh...)
echo.
set /p EXT_ID="   Cole o ID da extensao Chrome aqui: "
if "%EXT_ID%"=="" set "EXT_ID=SUBSTITUA_PELO_ID_REAL"

REM Gera manifest JSON
set "HOST_PATH_ESC=!HOST_PATH:\=\\!"

echo {> "%MANIFEST%"
echo   "name": "desffrut_print_host",>> "%MANIFEST%"
echo   "description": "Desffrut Hardware - Impressao local ESC/POS e ZPL",>> "%MANIFEST%"
echo   "path": "!HOST_PATH_ESC!",>> "%MANIFEST%"
echo   "type": "stdio",>> "%MANIFEST%"
echo   "allowed_origins": [>> "%MANIFEST%"
echo     "chrome-extension://%EXT_ID%/">> "%MANIFEST%"
echo   ]>> "%MANIFEST%"
echo }>> "%MANIFEST%"
echo     Manifest gerado: %MANIFEST%

REM Registra no Registry
echo [5/5] Registrando host no Windows (HKCU)...
reg add "%REG_KEY%" /ve /t REG_SZ /d "%MANIFEST%" /f >nul
if %errorlevel% equ 0 (
    echo     Registro: OK
) else (
    echo [ERRO] Falha ao registrar no Registry.
    pause
    exit /b 1
)

echo.
echo ============================================================
echo   INSTALACAO CONCLUIDA!
echo ============================================================
echo.
echo   Proximos passos:
echo   1. Va em chrome://extensions e clique "Atualizar" (icone circular)
echo   2. Acesse Dashboard - Hardware
echo   3. Clique em "Atualizar Status" - deve ficar verde
echo.
echo   Log disponivel em: %APPDATA%\Desffrut\print_host.log
echo.
pause
