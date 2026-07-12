-- ============================================================
-- DESFFRUT — Migration 21: Ficha completa do Funcionário
-- Adiciona data de nascimento (para idade automática na ficha) e o
-- role 'colaborador' (staff sem acesso ao painel — ex.: motorista e
-- auxiliar de CEASA admitidos via RH, mas que não operam o sistema).
-- Endereço, CPF, telefone, whatsapp e foto_perfil já existem em
-- `usuarios` (migrations 00, 04, 15) — reaproveitados pela ficha.
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS data_nascimento DATE NULL
        COMMENT 'Usado para calcular idade automaticamente na ficha do funcionário'
        AFTER cpf;

ALTER TABLE usuarios
    MODIFY COLUMN role
        ENUM('cliente','caixa','entregador','gerente','rh_financeiro','super_admin','dev_admin','colaborador')
        NOT NULL DEFAULT 'cliente'
        COMMENT "'colaborador' = staff com ficha de RH mas sem acesso ao painel (motorista, auxiliar CEASA)";
