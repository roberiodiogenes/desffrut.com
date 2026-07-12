@echo off
echo.
echo Removendo Desffrut Hardware Host Nativo...
echo.
reg delete "HKCU\Software\Google\Chrome\NativeMessagingHosts\desffrut_print_host" /f >nul 2>&1
reg delete "HKCU\Software\Microsoft\Edge\NativeMessagingHosts\desffrut_print_host" /f >nul 2>&1
if exist "C:\desffrut\native-host" (
    rmdir /s /q "C:\desffrut\native-host"
)
echo Host nativo removido.
echo Desinstale a extensao manualmente em chrome://extensions ou edge://extensions
pause
