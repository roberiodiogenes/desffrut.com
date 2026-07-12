<?php
/**
 * Desffrut — Model: Usuario
 * Operações sobre a tabela `usuarios` (todos os roles).
 */
class Usuario {

    private PDO $db;

    public function __construct() {
        $this->db = db();
    }

    /** Busca usuário ativo por e-mail. Retorna array ou null. */
    public function buscarPorEmail(string $email): ?array {
        $stmt = $this->db->prepare(
            'SELECT * FROM usuarios WHERE email = :email AND ativo = 1 LIMIT 1'
        );
        $stmt->execute(['email' => strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    /** Busca usuário ativo por ID. */
    public function buscarPorId(int $id): ?array {
        $stmt = $this->db->prepare(
            'SELECT * FROM usuarios WHERE id = :id AND ativo = 1 LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Cria novo usuário. Retorna o ID inserido.
     *
     * @param array $dados Chaves: nome, email, cpf, senha_hash, role, loja_id, lgpd_aceito_em
     */
    public function criar(array $dados): int {
        $stmt = $this->db->prepare('
            INSERT INTO usuarios (nome, email, cpf, senha_hash, role, loja_id, lgpd_aceito_em)
            VALUES (:nome, :email, :cpf, :senha_hash, :role, :loja_id, :lgpd_aceito_em)
        ');
        $stmt->execute([
            'nome'           => $dados['nome'],
            'email'          => strtolower(trim($dados['email'])),
            'cpf'            => $dados['cpf'] ?? null,
            'senha_hash'     => $dados['senha_hash'],
            'role'           => $dados['role'] ?? 'cliente',
            'loja_id'        => $dados['loja_id'] ?? null,
            'lgpd_aceito_em' => $dados['lgpd_aceito_em'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** Atualiza o token de sessão ativo do usuário. */
    public function atualizarToken(int $id, ?string $token): void {
        $stmt = $this->db->prepare(
            'UPDATE usuarios SET token_sessao = :token WHERE id = :id'
        );
        $stmt->execute(['token' => $token, 'id' => $id]);
    }

    /** Revoga token (logout). */
    public function revogarToken(int $id): void {
        $this->atualizarToken($id, null);
    }

    /**
     * Anonimiza dados pessoais do cliente (exclusão LGPD).
     * Preserva o registro para integridade do histórico financeiro.
     */
    /** Retorna true se anonimizou com sucesso. */
    public function anonimizar(int $id): bool {
        $stamp = 'anonimizado_' . $id . '_' . time();
        $stmt  = $this->db->prepare('
            UPDATE usuarios
            SET nome           = :nome,
                email          = :email,
                cpf            = NULL,
                senha_hash     = :hash,
                ativo          = 0,
                token_sessao   = NULL
            WHERE id = :id
        ');
        $stmt->execute([
            'nome'  => '[Usuário removido]',
            'email' => $stamp . '@removido.local',
            'hash'  => password_hash(gerar_token(), PASSWORD_DEFAULT),
            'id'    => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    /** Verifica se e-mail já está cadastrado. */
    public function emailExiste(string $email): bool {
        $stmt = $this->db->prepare(
            'SELECT id FROM usuarios WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => strtolower(trim($email))]);
        return (bool) $stmt->fetch();
    }
}
