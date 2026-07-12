<?php
class Loja {

    private PDO $db;

    public function __construct() { $this->db = db(); }

    public function listarAtivas(): array {
        return $this->db
            ->query('SELECT id, nome, endereco, telefone FROM lojas WHERE ativo = 1 ORDER BY nome')
            ->fetchAll();
    }

    public function buscarPorId(int $id): ?array {
        $stmt = $this->db->prepare('SELECT * FROM lojas WHERE id = ? AND ativo = 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
