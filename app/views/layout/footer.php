
<footer class="bg-dark text-secondary pt-5 pb-3 mt-5">
    <div class="container">
        <div class="row g-4 mb-4">

            <!-- Coluna 1: Links rápidos -->
            <div class="col-6 col-md-3">
                <h6 class="text-white fw-bold mb-3">🛒 Navegação</h6>
                <ul class="list-unstyled mb-0 small">
                    <li class="mb-2">
                        <a href="<?= BASE_PATH ?>/" class="text-secondary text-decoration-none">
                            Catálogo de Produtos
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="<?= BASE_PATH ?>/lojas" class="text-secondary text-decoration-none">
                            Nossas Lojas
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="<?= BASE_PATH ?>/sobre" class="text-secondary text-decoration-none">
                            Quem Somos
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="<?= BASE_PATH ?>/fidelidade" class="text-secondary text-decoration-none">
                            Programa de Fidelidade
                        </a>
                    </li>
                    <li class="mb-0">
                        <a href="<?= BASE_PATH ?>/parcerias" class="text-secondary text-decoration-none">
                            Seja Nosso Parceiro
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Coluna 2: Minha conta -->
            <div class="col-6 col-md-3">
                <h6 class="text-white fw-bold mb-3">👤 Minha Conta</h6>
                <ul class="list-unstyled mb-0 small">
                    <li class="mb-2">
                        <a href="<?= BASE_PATH ?>/cadastro" class="text-secondary text-decoration-none">
                            Criar conta gratuita
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="<?= BASE_PATH ?>/login" class="text-secondary text-decoration-none">
                            Entrar
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="<?= BASE_PATH ?>/meu-perfil" class="text-secondary text-decoration-none">
                            Meu Perfil &amp; Pontos
                        </a>
                    </li>
                    <li class="mb-0">
                        <a href="<?= BASE_PATH ?>/meu-perfil#pedidos" class="text-secondary text-decoration-none">
                            Meus Pedidos
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Coluna 3: Institucional -->
            <div class="col-6 col-md-3">
                <h6 class="text-white fw-bold mb-3">📋 Institucional</h6>
                <ul class="list-unstyled mb-0 small">
                    <li class="mb-2">
                        <a href="<?= BASE_PATH ?>/privacidade" class="text-secondary text-decoration-none">
                            Política de Privacidade
                        </a>
                    </li>
                    <li class="mb-0">
                        <a href="<?= BASE_PATH ?>/termos" class="text-secondary text-decoration-none">
                            Termos de Uso
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Coluna 4: Acesso interno -->
            <div class="col-6 col-md-3">
                <h6 class="text-white fw-bold mb-3">🔐 Área Interna</h6>
                <ul class="list-unstyled mb-0 small">
                    <li class="mb-2">
                        <a href="<?= BASE_PATH ?>/login" class="text-secondary text-decoration-none">
                            Acesso da Equipe
                        </a>
                    </li>
                    <li class="mb-0">
                        <a href="<?= BASE_PATH ?>/manual" class="text-secondary text-decoration-none">
                            Manual de Uso
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <hr class="border-secondary opacity-25">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <p class="mb-0 small">
                &copy; <?= date('Y') ?> <strong class="text-white"><?= NOME_SISTEMA ?></strong>
                &mdash; Hortifruti sempre fresco.
            </p>
            <p class="mb-0 small text-muted" style="font-size:.75rem;">
                Feito com ❤️ para o hortifruti brasileiro
            </p>
        </div>
    </div>
</footer>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
