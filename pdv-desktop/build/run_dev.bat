@echo off
REM Desffrut PDV Desktop — roda direto do codigo-fonte (sem compilar),
REM para desenvolvimento e teste rapido.

cd /d "%~dp0\..\app"

if not exist ..\venv (
    echo Ambiente virtual nao encontrado. Rode build.bat primeiro ou:
    echo   python -m venv ..\venv ^&^& ..\venv\Scripts\activate ^&^& pip install -r ..\requirements.txt
    pause
    exit /b 1
)

call ..\venv\Scripts\activate.bat
python main.py --devtools %*
