@echo off
REM Desffrut PDV Desktop — build do executavel Windows.
REM Rodar este script NO WINDOWS (PyInstaller nao faz cross-compile de Linux/Mac).

cd /d "%~dp0\.."

if not exist venv (
    echo [1/4] Criando ambiente virtual...
    python -m venv venv
)

echo [2/4] Instalando dependencias...
call venv\Scripts\activate.bat
pip install -r requirements.txt
if errorlevel 1 (
    echo.
    echo ERRO: falha ao instalar dependencias. Veja as mensagens acima.
    pause
    exit /b 1
)

echo [3/4] Gerando executavel com PyInstaller...
cd build
pyinstaller pdv_desktop.spec --clean --noconfirm
if errorlevel 1 (
    echo.
    echo ERRO: PyInstaller falhou. Veja as mensagens acima.
    pause
    exit /b 1
)

echo [4/4] Pronto.
echo Executavel gerado em: build\dist\DesffrutPDV.exe
pause
