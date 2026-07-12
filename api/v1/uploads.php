<?php
/**
 * Desffrut — API v1: Uploads de imagens
 *
 * POST /api/v1/uploads/foto      → produto (campo "foto"), salva em uploads/produtos/
 * POST /api/v1/uploads           → com campo "destino"=logo|banner, salva no diretório correto [Fase 8]
 *
 * Formatos aceitos: JPG, PNG, GIF, WebP (até MAX_UPLOAD_BYTES = 6 MB)
 */

api_auth_exigir();
api_auth_role(['gerente', 'super_admin', 'rh_financeiro']);

if ($method !== 'POST') {
    json_response(['status' => 'error', 'message' => 'Endpoint inválido.', 'data' => null], 404);
}

// ── Determina destino ─────────────────────────────────────────────────────────
// /uploads/foto  (legado) → produtos
// /uploads       → usa campo "destino" no form (logo, banner, produto)
$destino_tipo = 'produto';
if ($id === 'foto') {
    $destino_tipo = 'produto';
} elseif ($id === null) {
    $destino_tipo = sanitize($_POST['destino'] ?? 'produto');
    if (!in_array($destino_tipo, ['produto','logo','banner','funcionario'], true)) {
        $destino_tipo = 'produto';
    }
} else {
    json_response(['status' => 'error', 'message' => 'Destino inválido.', 'data' => null], 404);
}

$dir_map = [
    'produto'     => DIR_UPLOAD_PRODUTOS,
    'logo'        => dirname(DIR_UPLOAD_PRODUTOS) . '/logos/',
    'banner'      => dirname(DIR_UPLOAD_PRODUTOS) . '/banners/',
    'funcionario' => dirname(DIR_UPLOAD_PRODUTOS) . '/funcionarios/',
];
$path_map = [
    'produto'     => 'uploads/produtos/',
    'logo'        => 'uploads/logos/',
    'banner'      => 'uploads/banners/',
    'funcionario' => 'uploads/funcionarios/',
];
$max_kb_map = [
    'produto'     => MAX_FOTO_KB,   // 90 KB
    'logo'        => 200,           // 200 KB para logo (qualidade maior)
    'banner'      => 300,           // 300 KB para banners
    'funcionario' => 95,            // < 100 KB — foto de crachá/ficha
];
$dir_destino = $dir_map[$destino_tipo];
$path_prefix = $path_map[$destino_tipo];
$max_kb      = $max_kb_map[$destino_tipo];

// ── Valida arquivo ────────────────────────────────────────────────────────────
if (empty($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['foto']['error'] ?? -1;
    $msg = match ($err) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Arquivo excede o tamanho máximo permitido.',
        UPLOAD_ERR_NO_FILE  => 'Nenhum arquivo enviado.',
        default             => 'Erro no upload. Código: ' . $err,
    };
    json_response(['status' => 'error', 'message' => $msg, 'data' => null], 422);
}

$arquivo = $_FILES['foto'];

if ($arquivo['size'] > MAX_UPLOAD_BYTES) {
    json_response(['status' => 'error', 'message' => 'Arquivo excede o limite de 6 MB.', 'data' => null], 422);
}

$mime_permitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array(mime_content_type($arquivo['tmp_name']), $mime_permitidos, true)) {
    json_response(['status' => 'error', 'message' => 'Formato inválido. Use JPG, PNG, GIF ou WebP.', 'data' => null], 422);
}

// ── Cria diretório de destino ─────────────────────────────────────────────────
if (!is_dir($dir_destino)) {
    mkdir($dir_destino, 0755, true);
}

// ── Converte e salva ──────────────────────────────────────────────────────────
$nome_arquivo = gerar_token(16) . '.webp';
$caminho_abs  = rtrim($dir_destino, '/\\') . DIRECTORY_SEPARATOR . $nome_arquivo;

// Logo/banner: redimensionamento próprio (dimensões maiores, mira em qualidade).
// Produto/funcionário: usa converter_para_webp() (limita a 800px e já aceita max_kb customizado).
if ($destino_tipo === 'logo' || $destino_tipo === 'banner') {
    // Copia e converte manualmente respeitando o limite do tipo
    $src = imagecreatefromstring(file_get_contents($arquivo['tmp_name']));
    if (!$src) {
        json_response(['status'=>'error','message'=>'Erro ao processar imagem (GD).'],500);
    }
    $w = imagesx($src); $h = imagesy($src);
    // Redimensiona mantendo proporção se muito grande
    $max_dim = ($destino_tipo === 'banner') ? 1920 : 400;
    if ($w > $max_dim) {
        $nh = (int)($h * $max_dim / $w); $nw = $max_dim;
        $dst = imagecreatetruecolor($nw,$nh);
        imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$w,$h);
        imagedestroy($src); $src = $dst;
    }
    // Salva com qualidade ajustada até caber no limite
    $qualidade = 85;
    do {
        ob_start(); imagewebp($src, null, $qualidade); $data = ob_get_clean();
        $qualidade -= 5;
    } while (strlen($data) > $max_kb * 1024 && $qualidade > 30);
    imagedestroy($src);
    file_put_contents($caminho_abs, $data);
} else {
    // 'produto' e 'funcionario' — foto de rosto/produto, limite em $max_kb (90 e 95 KB respectivamente)
    if (!converter_para_webp($arquivo['tmp_name'], $caminho_abs, $max_kb)) {
        json_response(['status' => 'error', 'message' => 'Erro ao processar a imagem. Verifique se GD está habilitado.', 'data' => null], 500);
    }
}

$path_relativo = $path_prefix . $nome_arquivo;

json_response([
    'status'  => 'ok',
    'data'    => [
        'path' => $path_relativo,
        'url'  => BASE_PATH . '/' . $path_relativo,
    ],
    'message' => 'Imagem enviada e convertida para WebP.',
]);
