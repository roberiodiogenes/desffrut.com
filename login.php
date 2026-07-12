<?php
/**
 * Desffrut — SSO: Tela de Login Unificada (Fase 1)
 * O JS intercepta o form, envia via fetch para /api/v1/auth/login
 * e redireciona para o destino correto conforme o role retornado.
 */
require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/helpers/functions.php';

iniciar_sessao();

// Já está logado? Redireciona direto
$usuario = usuario_logado();
if ($usuario) {
    $destinos = [
        'cliente'       => BASE_PATH . '/',
        'caixa'         => BASE_PATH . '/dashboard',
        'entregador'    => BASE_PATH . '/dashboard',
        'rh_financeiro' => BASE_PATH . '/dashboard',
        'gerente'       => BASE_PATH . '/dashboard',
        'super_admin'   => BASE_PATH . '/dashboard',
        'dev_admin'     => BASE_PATH . '/dev',
    ];
    redirecionar($destinos[$usuario['role']] ?? '/');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>/public/img/favicon.png">
    <title>Entrar — <?= NOME_SISTEMA ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
    <style>
        :root {
            --verde:        #2e7d32;
            --verde-escuro: #1b5e20;
            --verde-claro:  #e8f5e9;
        }

        body {
            background: linear-gradient(135deg, #f0f7f0 0%, #e8f5e9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        .login-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(46, 125, 50, .12), 0 2px 8px rgba(0,0,0,.06);
            padding: 44px 40px 36px;
            width: 100%;
            max-width: 400px;
        }

        .login-logo {
            text-align: center;
            margin-bottom: 28px;
        }
        .login-logo .marca {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--verde-claro);
            border-radius: 50px;
            padding: 10px 22px;
            margin-bottom: 14px;
        }
        .login-logo .marca-icone {
            font-size: 1.7rem;
            line-height: 1;
        }
        .login-logo .marca-nome {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--verde-escuro);
            letter-spacing: -.3px;
        }
        .login-logo .subtitulo {
            font-size: .88rem;
            color: #78909c;
        }

        .login-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #263238;
            margin-bottom: 20px;
            text-align: center;
        }

        .form-label {
            font-size: .82rem;
            font-weight: 600;
            color: #546e7a;
            margin-bottom: 5px;
        }

        .form-control {
            border-radius: 8px;
            border: 1.5px solid #cfd8dc;
            padding: 10px 14px;
            font-size: .92rem;
            transition: border-color .2s, box-shadow .2s;
        }
        .form-control:focus {
            border-color: var(--verde);
            box-shadow: 0 0 0 3px rgba(46,125,50,.12);
            outline: none;
        }

        .input-group-text {
            border-radius: 8px 0 0 8px;
            border: 1.5px solid #cfd8dc;
            border-right: none;
            background: #f5f7f5;
            color: #78909c;
        }
        .input-group .form-control {
            border-radius: 0 8px 8px 0;
        }
        .input-group:focus-within .input-group-text {
            border-color: var(--verde);
        }

        .btn-entrar {
            background: var(--verde);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            padding: 12px;
            width: 100%;
            margin-top: 8px;
            transition: background .2s, transform .1s;
            cursor: pointer;
        }
        .btn-entrar:hover:not(:disabled) {
            background: var(--verde-escuro);
            transform: translateY(-1px);
        }
        .btn-entrar:disabled {
            opacity: .7;
            cursor: not-allowed;
        }

        .login-erro {
            background: #fff5f5;
            border: 1px solid #ffcdd2;
            border-radius: 8px;
            color: #c62828;
            font-size: .84rem;
            padding: 10px 14px;
            margin-bottom: 16px;
            display: none;
        }
        .login-erro::before { content: '⚠️ '; }

        .login-links {
            text-align: center;
            margin-top: 20px;
            font-size: .84rem;
            color: #90a4ae;
        }
        .login-links a {
            color: var(--verde);
            text-decoration: none;
            font-weight: 600;
        }
        .login-links a:hover { text-decoration: underline; }
        .login-links .sep { margin: 0 8px; color: #cfd8dc; }

        .pdv-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 16px;
            padding: 9px 16px;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            color: #546e7a;
            font-size: .82rem;
            text-decoration: none;
            transition: border-color .2s, color .2s;
        }
        .pdv-link:hover {
            border-color: var(--verde);
            color: var(--verde);
        }

        @media (max-width: 440px) {
            .login-card { padding: 32px 22px 28px; }
        }
    </style>
</head>
<body>

    <div class="login-card">

        <!-- Logo / Marca -->
        <div class="login-logo">
            <div class="marca">
                <span class="marca-icone">🌿</span>
                <span class="marca-nome"><?= NOME_SISTEMA ?></span>
            </div>
            <div class="subtitulo">Hortifruti sempre fresco</div>
        </div>

        <div class="login-title">Entrar na sua conta</div>

        <!-- Erro -->
        <div id="msg-erro" class="login-erro"></div>

        <form id="form-login" novalidate>

            <!-- E-mail -->
            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <div class="input-group">
                    <span class="input-group-text">✉️</span>
                    <input type="email" id="email" name="email"
                           class="form-control"
                           required autocomplete="email"
                           placeholder="seu@email.com">
                </div>
            </div>

            <!-- Senha -->
            <div class="mb-4">
                <label for="senha" class="form-label">Senha</label>
                <div class="input-group">
                    <span class="input-group-text">🔑</span>
                    <input type="password" id="senha" name="senha"
                           class="form-control"
                           required autocomplete="current-password"
                           placeholder="••••••••">
                </div>
            </div>

            <button type="submit" id="btn-entrar" class="btn-entrar">
                Entrar
            </button>

        </form>

        <!-- Links secundários -->
        <div class="login-links">
            <a href="<?= BASE_PATH ?>/cadastro">Criar conta</a>
            <span class="sep">·</span>
            <a href="<?= BASE_PATH ?>/">Ver catálogo</a>
        </div>

        <!-- Acesso PDV -->
        <a href="<?= BASE_PATH ?>/pdv/abertura" class="pdv-link">
            🖥️ Acesso direto ao PDV (caixa)
        </a>

    </div>

    <script>
    const API_BASE = '<?= API_ROOT ?>';
    (() => {
        const form    = document.getElementById('form-login');
        const btnSend = document.getElementById('btn-entrar');
        const msgErro = document.getElementById('msg-erro');

        function mostrarErro(msg) {
            msgErro.textContent = msg;
            msgErro.style.display = 'block';
        }
        function ocultarErro() {
            msgErro.style.display = 'none';
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            ocultarErro();

            const email = document.getElementById('email').value.trim();
            const senha = document.getElementById('senha').value;

            if (!email || !senha) {
                mostrarErro('Preencha e-mail e senha.');
                return;
            }

            btnSend.disabled    = true;
            btnSend.textContent = 'Entrando…';

            try {
                const resp = await fetch(API_BASE + '/auth/login', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ email, senha }),
                });

                const json = await resp.json();

                if (resp.ok && json.status === 'ok') {
                    sessionStorage.setItem('desffrut_token', json.data.token);
                    sessionStorage.setItem('desffrut_role',  json.data.role);
                    sessionStorage.setItem('desffrut_nome',  json.data.nome);
                    // Se havia um destino pendente (ex: checkout após login), usa ele
                    const pendente = sessionStorage.getItem('desffrut_redirect_pos_login');
                    if (pendente && json.data.role === 'cliente') {
                        sessionStorage.removeItem('desffrut_redirect_pos_login');
                        window.location.href = pendente;
                    } else {
                        window.location.href = json.data.redirect;
                    }
                } else {
                    mostrarErro(json.message || 'Erro ao fazer login.');
                    btnSend.disabled    = false;
                    btnSend.textContent = 'Entrar';
                }
            } catch (err) {
                mostrarErro('Erro de conexão. Tente novamente.');
                btnSend.disabled    = false;
                btnSend.textContent = 'Entrar';
            }
        });

        // Foco automático no campo de e-mail
        document.getElementById('email').focus();
    })();
    </script>
</body>
</html>
