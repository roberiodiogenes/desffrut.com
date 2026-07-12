<?php
/**
 * Desffrut — API v1: Impressão Local (PHP/Windows)
 *
 * GET  /api/v1/print?a=listar   → lista impressoras via WMIC
 * GET  /api/v1/print?a=info     → diagnóstico do ambiente
 * POST /api/v1/print             → imprime ESC/POS via fopen() direto na impressora
 *   body: { printer:'Nome', paper:'58'|'80', tipo:'teste', dados:{} }
 *
 * Usa fopen('\\\\.\\PrinterName', 'wb') — sem exec(), sem QZ Tray.
 * Funciona apenas em localhost (XAMPP dev). Produção → QZ Tray.
 */

// Suprime warnings/notices que corromperiam o JSON
error_reporting(0);
ini_set('display_errors', '0');

if (!function_exists('api_auth_exigir')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'erro' => 'Acesso negado']); exit;
}

// Só localhost
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'erro' => 'Disponível apenas em localhost.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$acao = $_GET['a'] ?? ($body['a'] ?? ($method === 'POST' ? 'imprimir' : ''));

// ─────────────────────────────────────────────────────────────────────────────
// GET ?a=info  →  diagnóstico
// ─────────────────────────────────────────────────────────────────────────────
if ($acao === 'info') {
    $printers_raw = [];
    if (function_exists('exec')) {
        exec('wmic printer get name 2>&1', $printers_raw);
    }
    $printers = array_values(array_filter(
        array_map('trim', $printers_raw),
        fn($n) => $n && strtolower($n) !== 'name'
    ));

    echo json_encode([
        'ok'            => true,
        'php_version'   => PHP_VERSION,
        'os'            => PHP_OS,
        'exec_existe'   => function_exists('exec'),
        'fopen_existe'  => function_exists('fopen'),
        'temp_dir'      => sys_get_temp_dir(),
        'temp_gravavel' => is_writable(sys_get_temp_dir()),
        'disable_funcs' => ini_get('disable_functions'),
        'impressoras'   => $printers,
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// GET ?a=listar  →  lista impressoras via WMIC (ou PowerShell como fallback)
// ─────────────────────────────────────────────────────────────────────────────
if ($acao === 'listar' || ($method === 'GET' && !$acao)) {
    $impressoras = _listar_impressoras();
    echo json_encode(['ok' => true, 'impressoras' => $impressoras, 'total' => count($impressoras)]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// POST  →  imprimir ESC/POS
// ─────────────────────────────────────────────────────────────────────────────
if ($acao === 'imprimir' || $method === 'POST') {
    $printer = trim($body['printer'] ?? '');
    $paper   = in_array($body['paper'] ?? '', ['80', '58']) ? $body['paper'] : '58';
    $tipo    = $body['tipo']  ?? 'teste';
    $dados   = $body['dados'] ?? [];

    if (!$printer) {
        echo json_encode(['ok' => false, 'erro' => 'Nome da impressora é obrigatório.']); exit;
    }

    $cols = ($paper === '80') ? 48 : 32;
    $buf  = _escpos($tipo, $dados, $printer, $paper, $cols);

    // ── Método 1: fopen direto na impressora Windows (sem exec) ───────────────
    $printerSafe = str_replace(['"', "\r", "\n", '/'], ['', '', '', '\\'], $printer);
    $devicePath  = '\\\\.\\' . $printerSafe;
    $handle      = @fopen($devicePath, 'wb');

    if ($handle !== false) {
        $written = fwrite($handle, $buf);
        fclose($handle);

        if ($written > 0) {
            echo json_encode([
                'ok'       => true,
                'metodo'   => 'fopen',
                'impressora' => $printer,
                'bytes'    => $written,
            ]);
            exit;
        }
    }

    // ── Método 2: copy /b pela PORTA USB (mais confiável que pelo nome) ─────────
    if (function_exists('exec')) {
        // Descobre a porta da impressora (ex: USB001)
        $portQuery = [];
        exec('wmic printer where "Name=\'' . addslashes($printerSafe) . '\'" get PortName /format:value 2>&1', $portQuery);
        $portName = '';
        foreach ($portQuery as $linha) {
            if (stripos($linha, 'PortName=') === 0) {
                $portName = trim(substr($linha, 9));
                break;
            }
        }

        $tmp    = tempnam(sys_get_temp_dir(), 'dsfr_') . '.bin';
        $tmpWin = str_replace('/', '\\', $tmp);
        file_put_contents($tmp, $buf);

        // Tenta pela porta (USB001) — mais direto para impressoras USB
        if ($portName && preg_match('/^USB\d+$/i', $portName)) {
            $cmd = 'copy /b "' . $tmpWin . '" ' . strtoupper($portName) . ' 2>&1';
            exec($cmd, $out, $ret);
            if ($ret === 0) {
                @unlink($tmp);
                echo json_encode(['ok' => true, 'metodo' => 'copy_porta', 'porta' => $portName]);
                exit;
            }
        }

        // Tenta pelo nome da impressora via UNC
        $cmd2 = 'copy /b "' . $tmpWin . '" "\\\\.\\' . $printerSafe . '" 2>&1';
        $out2 = []; $ret2 = -1;
        exec($cmd2, $out2, $ret2);
        @unlink($tmp);

        if ($ret2 === 0) {
            echo json_encode(['ok' => true, 'metodo' => 'copy_unc', 'impressora' => $printer]);
        } else {
            echo json_encode([
                'ok'             => false,
                'erro'           => 'Falha ao imprimir. Veja os detalhes abaixo.',
                'fopen_tentado'  => $devicePath,
                'porta_detectada'=> $portName ?: '(não detectada)',
                'cmd_porta'      => isset($cmd) ? $cmd : '(n/a)',
                'saida_porta'    => $out ?? [],
                'cmd_unc'        => $cmd2,
                'saida_unc'      => $out2,
                'codigo_unc'     => $ret2,
            ]);
        }
        exit;
    }

    // exec() indisponível e fopen falhou
    echo json_encode([
        'ok'   => false,
        'erro' => 'Não foi possível imprimir. fopen() e exec() falharam.',
        'device_tentado' => $devicePath,
    ]);
    exit;
}

echo json_encode(['ok' => false, 'erro' => 'Ação inválida. Use GET ?a=listar|info ou POST.']);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function _listar_impressoras(): array
{
    $impressoras = [];

    // Tentativa 1: wmic
    if (function_exists('exec')) {
        $raw = [];
        exec('wmic printer get name 2>&1', $raw);
        foreach ($raw as $linha) {
            $nome = trim($linha);
            if (!$nome || strtolower($nome) === 'name') continue;
            $impressoras[] = $nome;
        }
    }

    // Tentativa 2: PowerShell (fallback para Windows 11 onde wmic foi removido)
    if (empty($impressoras) && function_exists('exec')) {
        $raw = [];
        exec('powershell -NoProfile -Command "Get-Printer | Select-Object -ExpandProperty Name" 2>&1', $raw);
        foreach ($raw as $linha) {
            $nome = trim($linha);
            if ($nome) $impressoras[] = $nome;
        }
    }

    sort($impressoras);
    return $impressoras;
}

function _escpos(string $tipo, array $d, string $printer, string $paper, int $cols): string
{
    $E = "\x1B"; $G = "\x1D";

    $INIT    = $E . '@';
    $CENTER  = $E . "a\x01";
    $LEFT    = $E . "a\x00";
    $BON     = $E . "E\x01";
    $BOF     = $E . "E\x00";
    $GON     = $G . "!\x11";
    $GOF     = $G . "!\x00";
    $CUT     = $G . "V\x41\x00";

    $hr      = function(string $ch = '-') use ($cols) { return str_repeat($ch, $cols) . "\n"; };

    $buf = $INIT;

    switch ($tipo) {
        case 'teste':
        default:
            $buf .= $CENTER . $GON . $BON . "DESFFRUT\n" . $GOF . $BOF;
            $buf .= "Pagina de Teste\n";
            $buf .= $LEFT . $hr();
            $buf .= _pline("Impressora", mb_substr($printer, 0, $cols - 15), $cols);
            $buf .= _pline("Papel",      $paper . "mm (" . $cols . " cols)", $cols);
            $buf .= _pline("Data/Hora",  date('d/m/Y H:i:s'), $cols);
            $buf .= $hr();
            $buf .= $CENTER . $BON . "Impressora OK!\n" . $BOF;
            $buf .= $LEFT . "Desffrut - Gestao Hortifruti\n";
            $buf .= $hr('=');
            $buf .= "\n\n\n" . $CUT;
            break;

        case 'cupom':
            $loja  = $d['loja'] ?? 'Loja';
            $total = number_format((float)($d['total'] ?? 0), 2, ',', '.');
            $itens = $d['itens'] ?? [];
            $buf  .= $CENTER . $GON . $BON . mb_strtoupper($loja) . "\n" . $GOF . $BOF;
            $buf  .= $LEFT . $hr();
            foreach ($itens as $it) {
                $nome = mb_substr($it['nome'] ?? '?', 0, $cols - 12);
                $val  = number_format((float)($it['valor'] ?? 0), 2, ',', '.');
                $buf .= _pline($nome, 'R$ ' . $val, $cols);
            }
            $buf .= $hr();
            $buf .= _pline($BON . "TOTAL", "R$ " . $total . $BOF, $cols);
            $buf .= $CENTER . "Obrigado!\n";
            $buf .= "\n\n\n" . $CUT;
            break;
    }
    return $buf;
}

/** Linha: esquerda : direita */
function _pline(string $esq, string $dir, int $cols): string
{
    $esq   = mb_substr($esq, 0, 12);
    $sep   = ' : ';
    $space = $cols - mb_strlen($esq) - mb_strlen($sep) - mb_strlen($dir);
    return str_pad($esq, 12) . $sep . $dir . "\n";
}
