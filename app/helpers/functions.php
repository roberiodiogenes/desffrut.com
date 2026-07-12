<?php
/**
 * Desffrut — Funções utilitárias globais
 */

/**
 * Envia resposta JSON padronizada e encerra a execução.
 *
 * @param array $data    Dados a retornar (será serializado em JSON).
 * @param int   $status  Código HTTP (padrão 200).
 */
function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Sanitiza string de entrada contra XSS.
 */
function sanitize(string $value): string {
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}

/**
 * Gera token criptograficamente seguro.
 */
function gerar_token(int $bytes = 32): string {
    return bin2hex(random_bytes($bytes));
}

/**
 * Inicia a sessão com configurações seguras (chama uma vez no topo da requisição).
 */
function iniciar_sessao(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_EXPIRE,
            'path'     => '/',
            'secure'   => EM_PRODUCAO, // true em 'teste' e 'definitivo' (ambos servidos via HTTPS)
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/**
 * Retorna o usuário logado ou null se não houver sessão válida.
 */
function usuario_logado(): ?array {
    iniciar_sessao();
    return $_SESSION['usuario'] ?? null;
}

/**
 * Redireciona para uma URL e encerra.
 */
function redirecionar(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Calcula pontos de fidelidade a partir de um valor em reais.
 * R$ 1,00 = 1 ponto (arredondado para baixo).
 */
function calcular_pontos(float $valor_reais): int {
    return (int) floor($valor_reais * PONTOS_POR_REAL);
}

/**
 * Converte pontos em desconto equivalente em reais.
 * 100 pontos = R$ 1,00.
 */
function pontos_para_reais(int $pontos): float {
    return round(($pontos / 100) * REAIS_POR_100_PONTOS, 2);
}

/**
 * Converte uma imagem (JPG/PNG/GIF/WebP) para WebP com qualidade reduzida
 * até atingir o tamanho máximo desejado.
 *
 * @param string $tmp_path  Caminho do arquivo temporário (upload)
 * @param string $destino   Caminho completo de destino (.webp)
 * @param int    $max_kb    Tamanho máximo em KB (padrão: 90)
 * @return bool  true em sucesso, false em falha
 */
function converter_para_webp(string $tmp_path, string $destino, int $max_kb = MAX_FOTO_KB): bool {
    if (!function_exists('imagewebp')) return false;

    $mime = mime_content_type($tmp_path);
    $img  = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($tmp_path),
        'image/png'  => imagecreatefrompng($tmp_path),
        'image/gif'  => imagecreatefromgif($tmp_path),
        'image/webp' => imagecreatefromwebp($tmp_path),
        default      => null,
    };
    if (!$img) return false;

    // Redimensiona se maior que 800 px em qualquer lado
    $w = imagesx($img);
    $h = imagesy($img);
    if ($w > 800 || $h > 800) {
        $ratio   = min(800 / $w, 800 / $h);
        $nw      = (int) ($w * $ratio);
        $nh      = (int) ($h * $ratio);
        $resized = imagecreatetruecolor($nw, $nh);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $img = $resized;
    }

    // Reduz qualidade até atingir max_kb
    $temp = $destino . '.tmp.webp';
    foreach ([85, 70, 55, 40, 25] as $q) {
        imagewebp($img, $temp, $q);
        if (file_exists($temp) && filesize($temp) <= $max_kb * 1024) {
            rename($temp, $destino);
            imagedestroy($img);
            return true;
        }
    }

    // Último recurso
    imagewebp($img, $destino, 20);
    @unlink($temp);
    imagedestroy($img);
    return true;
}

/**
 * Verifica se um usuário tem uma permissão específica.
 *
 * Lógica:
 *   1. Consulta tabela `permissoes_usuario` para exceções do usuário.
 *   2. Se houver exceção, retorna o valor de `concedida`.
 *   3. Se não houver, verifica o array padrão do role em PERMISSOES_POR_ROLE.
 *
 * @param int    $usuario_id  ID do usuário
 * @param string $permissao   Chave da permissão (ex: 'ver_dre')
 * @param string $role        Role do usuário (evita query adicional se já conhecido)
 */
function tem_permissao(int $usuario_id, string $permissao, string $role = ''): bool {
    if (!defined('PERMISSOES_POR_ROLE')) {
        require_once __DIR__ . '/../config/permissoes.php';
    }

    // super_admin e dev_admin têm tudo sempre
    if (in_array($role, ['super_admin', 'dev_admin'], true)) return true;

    // Cache por requisição para evitar queries repetidas
    static $cache = [];
    $cache_key = "{$usuario_id}:{$permissao}";
    if (isset($cache[$cache_key])) return $cache[$cache_key];

    // 1. Busca exceção na tabela
    try {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT concedida FROM permissoes_usuario WHERE usuario_id=:uid AND permissao=:perm LIMIT 1");
        $stmt->execute([':uid' => $usuario_id, ':perm' => $permissao]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            return $cache[$cache_key] = (bool) $row['concedida'];
        }
    } catch (Throwable $_) {}

    // 2. Fallback: padrão do role
    if (!$role) {
        try {
            $row = db()->prepare("SELECT role FROM usuarios WHERE id=:id LIMIT 1");
            $row->execute([':id' => $usuario_id]);
            $role = $row->fetchColumn() ?: '';
        } catch (Throwable $_) {}
    }
    $padrao = PERMISSOES_POR_ROLE[$role] ?? [];
    return $cache[$cache_key] = in_array($permissao, $padrao, true);
}

/**
 * Verifica se o Modo Restrito está ativo (Categoria 22).
 * Usa cache estático por requisição (evita query repetida).
 */
function modo_restrito_ativo(): bool {
    static $cache = null;
    if ($cache !== null) return $cache;

    try {
        $stmt = db()->prepare("SELECT valor FROM configuracoes WHERE chave='modo_restrito' LIMIT 1");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        $cache = ($val === '1' || $val === 1);
    } catch (Throwable $_) {
        $cache = false;
    }
    return $cache;
}

/**
 * Retorna mensagem do Modo Restrito definida pelo dev_admin (ou padrão).
 */
function motivo_restricao(): string {
    static $msg = null;
    if ($msg !== null) return $msg;
    try {
        $stmt = db()->prepare("SELECT valor FROM configuracoes WHERE chave='motivo_restricao' LIMIT 1");
        $stmt->execute();
        $msg = $stmt->fetchColumn() ?: 'Função temporariamente indisponível. Entre em contato com o suporte.';
    } catch (Throwable $_) {
        $msg = 'Função temporariamente indisponível.';
    }
    return $msg;
}

/**
 * Registra uma ação crítica na tabela logs_auditoria.
 * Falha silenciosa: não interrompe o fluxo principal se o log não puder ser gravado.
 *
 * @param int|null $usuario_id  ID do usuário que realizou a ação
 * @param string   $acao        Ex.: 'login', 'cancelamento_venda', 'alteracao_preco', 'sangria'
 * @param string   $tabela      Tabela afetada
 * @param int|null $registro_id ID do registro afetado
 * @param array    $detalhes    Dados adicionais (serializado como JSON)
 */
function registrar_log(
    ?int $usuario_id,
    string $acao,
    string $tabela = '',
    ?int $registro_id = null,
    array $detalhes = []
): void {
    try {
        $pdo  = db();
        $stmt = $pdo->prepare('
            INSERT INTO logs_auditoria
                (usuario_id, acao, tabela_afetada, registro_id, detalhes_json, ip)
            VALUES
                (:usuario_id, :acao, :tabela, :registro_id, :detalhes, :ip)
        ');
        $stmt->execute([
            'usuario_id'  => $usuario_id,
            'acao'        => $acao,
            'tabela'      => $tabela ?: null,
            'registro_id' => $registro_id,
            'detalhes'    => !empty($detalhes) ? json_encode($detalhes, JSON_UNESCAPED_UNICODE) : null,
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable) {
        // Log falhou — não interrompe a operação principal
    }
}
