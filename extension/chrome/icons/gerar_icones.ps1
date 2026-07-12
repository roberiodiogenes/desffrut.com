# Desffrut Hardware — Gerador de Ícones PNG
# Execute no PowerShell: .\gerar_icones.ps1
# Gera icon16.png, icon48.png e icon128.png na pasta atual

Add-Type -AssemblyName System.Drawing

function New-Icon {
    param([int]$size)

    $bmp = New-Object System.Drawing.Bitmap($size, $size)
    $g   = [System.Drawing.Graphics]::FromImage($bmp)
    $g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias

    # Fundo com gradiente (roxo → violeta)
    $brush = New-Object System.Drawing.Drawing2D.LinearGradientBrush(
        [System.Drawing.Point]::new(0, 0),
        [System.Drawing.Point]::new($size, $size),
        [System.Drawing.Color]::FromArgb(99, 102, 241),
        [System.Drawing.Color]::FromArgb(139, 92, 246)
    )
    $radius = [int]($size * 0.2)
    $rect   = New-Object System.Drawing.Rectangle(0, 0, $size, $size)
    $path   = New-Object System.Drawing.Drawing2D.GraphicsPath
    $path.AddArc($rect.X, $rect.Y, $radius*2, $radius*2, 180, 90)
    $path.AddArc($rect.Right - $radius*2, $rect.Y, $radius*2, $radius*2, 270, 90)
    $path.AddArc($rect.Right - $radius*2, $rect.Bottom - $radius*2, $radius*2, $radius*2, 0, 90)
    $path.AddArc($rect.X, $rect.Bottom - $radius*2, $radius*2, $radius*2, 90, 90)
    $path.CloseFigure()
    $g.FillPath($brush, $path)

    # Ícone de impressora (simplificado)
    $white = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::White)
    $p = [int]($size * 0.18)
    $w = [int]($size * 0.64)
    $h = [int]($size * 0.40)
    # Corpo da impressora
    $g.FillRectangle($white, $p, [int]($size*0.36), $w, $h)
    # Papel saindo
    $grayBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(200, 200, 220))
    $g.FillRectangle($grayBrush, [int]($size*0.3), [int]($size*0.22), [int]($size*0.4), [int]($size*0.22))
    # Saída do papel
    $g.FillRectangle($white, [int]($size*0.3), [int]($size*0.62), [int]($size*0.4), [int]($size*0.08))

    $g.Dispose()
    $brush.Dispose()
    $white.Dispose()

    $outPath = Join-Path $PSScriptRoot "icon${size}.png"
    $bmp.Save($outPath, [System.Drawing.Imaging.ImageFormat]::Png)
    $bmp.Dispose()
    Write-Host "Criado: $outPath"
}

New-Icon -size 16
New-Icon -size 48
New-Icon -size 128

Write-Host ""
Write-Host "Icones gerados! Recarregue a extensao em chrome://extensions"
