<?php
/**
 * Desffrut — Cadastro de Cliente (Fase 1 + LGPD)
 */
require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/helpers/functions.php';

iniciar_sessao();
if (usuario_logado()) redirecionar(BASE_PATH . '/meu-perfil');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>/public/img/favicon.png">
    <title>Criar conta — <?= NOME_SISTEMA ?></title>
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
            padding: 32px 16px;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        .cadastro-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(46,125,50,.12), 0 2px 8px rgba(0,0,0,.06);
            padding: 40px 40px 32px;
            width: 100%;
            max-width: 520px;
        }

        .cadastro-logo {
            text-align: center;
            margin-bottom: 24px;
        }
        .cadastro-logo .marca {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--verde-claro);
            border-radius: 50px;
            padding: 9px 20px;
            margin-bottom: 12px;
        }
        .marca-icone { font-size: 1.6rem; line-height: 1; }
        .marca-nome  { font-size: 1.3rem; font-weight: 800; color: var(--verde-escuro); letter-spacing: -.3px; }
        .subtitulo   { font-size: .85rem; color: #78909c; }

        .cadastro-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #263238;
            margin-bottom: 20px;
            text-align: center;
        }

        .form-label {
            font-size: .8rem;
            font-weight: 600;
            color: #546e7a;
            margin-bottom: 4px;
        }
        .form-label .opc {
            font-weight: 400;
            color: #90a4ae;
            font-size: .78rem;
        }

        .form-control {
            border-radius: 8px;
            border: 1.5px solid #cfd8dc;
            padding: 9px 13px;
            font-size: .9rem;
            transition: border-color .2s, box-shadow .2s;
        }
        .form-control:focus {
            border-color: var(--verde);
            box-shadow: 0 0 0 3px rgba(46,125,50,.12);
            outline: none;
        }

        /* Checkbox LGPD */
        .lgpd-wrap {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: #f0f7f0;
            border: 1.5px solid #c8e6c9;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: .84rem;
            color: #37474f;
            cursor: pointer;
        }
        .lgpd-wrap input[type="checkbox"] {
            width: 17px; height: 17px;
            margin-top: 1px;
            accent-color: var(--verde);
            flex-shrink: 0;
            cursor: pointer;
        }
        .lgpd-wrap a {
            color: var(--verde);
            font-weight: 600;
            text-decoration: none;
        }
        .lgpd-wrap a:hover { text-decoration: underline; }

        .btn-cadastrar {
            background: var(--verde);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            padding: 12px;
            width: 100%;
            transition: background .2s, transform .1s;
            cursor: pointer;
        }
        .btn-cadastrar:hover:not(:disabled) {
            background: var(--verde-escuro);
            transform: translateY(-1px);
        }
        .btn-cadastrar:disabled { opacity: .7; cursor: not-allowed; }

        .msg-erro {
            background: #fff5f5;
            border: 1px solid #ffcdd2;
            border-radius: 8px;
            color: #c62828;
            font-size: .84rem;
            padding: 10px 14px;
            margin-bottom: 16px;
            display: none;
        }
        .msg-erro::before { content: '⚠️  '; }

        .msg-ok {
            background: #f0f7f0;
            border: 1px solid #c8e6c9;
            border-radius: 8px;
            color: #2e7d32;
            font-size: .84rem;
            padding: 10px 14px;
            margin-bottom: 16px;
            display: none;
        }
        .msg-ok::before { content: '✅  '; }

        .cadastro-links {
            text-align: center;
            margin-top: 18px;
            font-size: .84rem;
            color: #90a4ae;
        }
        .cadastro-links a {
            color: var(--verde);
            text-decoration: none;
            font-weight: 600;
        }
        .cadastro-links a:hover { text-decoration: underline; }

        @media (max-width: 560px) {
            .cadastro-card { padding: 28px 18px 24px; }
        }
    </style>
</head>
<body>

    <div class="cadastro-card">

        <!-- Logo -->
        <div class="cadastro-logo">
            <div class="marca">
                <span class="marca-icone">🌿</span>
                <span class="marca-nome"><?= NOME_SISTEMA ?></span>
            </div>
            <div class="subtitulo">Hortifruti sempre fresco</div>
        </div>

        <div class="cadastro-title">Criar sua conta</div>

        <!-- Mensagens -->
        <div id="msg-erro" class="msg-erro"></div>
        <div id="msg-ok"   class="msg-ok"></div>

        <?php
        // Fase 12: exibe aviso visual se vier com código de indicação
        $ref_url = preg_replace('/[^A-Z0-9]/', '', strtoupper($_GET['ref'] ?? ''));
        if ($ref_url): ?>
        <div class="alert alert-success py-2 small mb-3">
            🎁 Você foi indicado! Crie sua conta e ganhe vantagens exclusivas no primeiro pedido.
        </div>
        <?php endif; ?>

        <form id="form-cadastro" novalidate>
            <!-- Campo oculto para código de indicação (Fase 12) -->
            <input type="hidden" id="ref-code" value="<?= htmlspecialchars($ref_url) ?>">

            <!-- Nome + CPF -->
            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-7">
                    <label for="nome" class="form-label">Nome completo</label>
                    <input type="text" id="nome" name="nome"
                           class="form-control"
                           required placeholder="Maria da Silva"
                           autocomplete="name">
                </div>
                <div class="col-12 col-sm-5">
                    <label for="cpf" class="form-label">
                        CPF <span class="opc">(opcional)</span>
                    </label>
                    <input type="text" id="cpf" name="cpf"
                           class="form-control"
                           maxlength="14" placeholder="000.000.000-00"
                           autocomplete="off">
                </div>
            </div>

            <!-- E-mail -->
            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" id="email" name="email"
                       class="form-control"
                       required placeholder="seu@email.com"
                       autocomplete="email">
            </div>

            <!-- Senha -->
            <div class="mb-3">
                <label for="senha" class="form-label">
                    Senha <span class="opc">(mínimo 8 caracteres)</span>
                </label>
                <input type="password" id="senha" name="senha"
                       class="form-control"
                       required minlength="8"
                       placeholder="••••••••"
                       autocomplete="new-password">
            </div>

            <!-- LGPD -->
            <div class="mb-4">
                <label class="lgpd-wrap">
                    <input type="checkbox" id="lgpd" name="lgpd_aceite" value="1" required>
                    <span>
                        Li e concordo com os
                        <a href="<?= BASE_PATH ?>/privacidade" target="_blank">Termos de Uso e Política de Privacidade</a>.
                    </span>
                </label>
            </div>

            <button type="submit" id="btn-cadastrar" class="btn-cadastrar">
                Criar conta
            </button>

        </form>

        <div class="cadastro-links">
            Já tem conta? <a href="<?= BASE_PATH ?>/login">Entrar</a>
        </div>

    </div>

    <script>
    const API_BASE = '<?= API_ROOT ?>';
    (() => {
        const form   = document.getElementById('form-cadastro');
        const btn    = document.getElementById('btn-cadastrar');
        const msgErr = document.getElementById('msg-erro');
        const msgOk  = document.getElementById('msg-ok');

        // Máscara de CPF
        document.getElementById('cpf').addEventListener('input', (e) => {
            let v = e.target.value.replace(/\D/g, '').slice(0, 11);
            v = v.replace(/(\d{3})(\d)/, '$1.$2')
                 .replace(/(\d{3})(\d)/, '$1.$2')
                 .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            e.target.value = v;
        });

        function mostrarErro(msg) {
            msgErr.textContent   = msg;
            msgErr.style.display = 'block';
            msgOk.style.display  = 'none';
        }
        function mostrarOk(msg) {
            msgOk.textContent    = msg;
            msgOk.style.display  = 'block';
            msgErr.style.display = 'none';
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            msgErr.style.display = 'none';
            msgOk.style.display  = 'none';

            const payload = {
                nome:        document.getElementById('nome').value.trim(),
                cpf:         document.getElementById('cpf').value.trim(),
                email:       document.getElementById('email').value.trim(),
                senha:       document.getElementById('senha').value,
                lgpd_aceite: document.getElementById('lgpd').checked ? 1 : 0,
                ref_code:    document.getElementById('ref-code').value || undefined,
            };

            if (!payload.nome || !payload.email || !payload.senha) {
                mostrarErro('Preencha todos os campos obrigatórios.');
                return;
            }
            if (!document.getElementById('lgpd').checked) {
                mostrarErro('Você precisa aceitar os Termos de Uso para criar uma conta.');
                return;
            }

            btn.disabled    = true;
            btn.textContent = 'Criando conta…';

            try {
                const resp = await fetch(API_BASE + '/auth/registrar', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify(payload),
                });
                const json = await resp.json();

                if (resp.ok && json.status === 'ok') {
                    mostrarOk(json.message + ' Redirecionando para o login…');
                    setTimeout(() => window.location.href = '<?= BASE_PATH ?>/login', 2000);
                } else {
                    mostrarErro(json.message || 'Erro ao criar conta.');
                    btn.disabled    = false;
                    btn.textContent = 'Criar conta';
                }
            } catch {
                mostrarErro('Erro de conexão. Tente novamente.');
                btn.disabled    = false;
                btn.textContent = 'Criar conta';
            }
        });

        document.getElementById('nome').focus();
    })();
    </script>
</body>
</html>
