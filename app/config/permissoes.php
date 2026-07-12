<?php
/**
 * Desffrut — Definição de permissões granulares (Categoria 20)
 *
 * PERMISSOES_DISPONIVEIS: array de todas as permissões do sistema.
 * PERMISSOES_POR_ROLE: define quais permissões cada role tem por padrão.
 *
 * A tabela `permissoes_usuario` armazena EXCEÇÕES (linhas que sobrepõem este padrão).
 * Usar via helper: tem_permissao($usuario_id, 'ver_dre')
 */

define('PERMISSOES_DISPONIVEIS', [
    // ── Financeiro / BI ──────────────────────────────────────────────────────
    'ver_dre'                => 'Visualizar DRE e resultado financeiro',
    'ver_bi'                 => 'Acessar painel de BI e gráficos',
    'exportar_relatorio'     => 'Exportar dados em CSV/PDF',
    'ver_folha'              => 'Visualizar folha de pagamento',
    // ── Produtos ─────────────────────────────────────────────────────────────
    'editar_produto'         => 'Cadastrar e editar produtos',
    'aplicar_desconto'       => 'Aplicar desconto manual no PDV',
    'imprimir_etiqueta'      => 'Imprimir etiquetas de produto',
    'ver_relatorio_estoque'  => 'Ver relatórios de estoque crítico',
    // ── Clientes / Fidelidade ─────────────────────────────────────────────────
    'ver_historico_pontos'   => 'Ver histórico de pontos de clientes',
    // ── Configurações / Campanhas ─────────────────────────────────────────────
    'gerenciar_campanhas'    => 'Ativar/desativar campanhas sazonais',
    // ── RH ───────────────────────────────────────────────────────────────────
    'admitir_funcionario'    => 'Admitir e demitir funcionários',
    // ── Auditoria ────────────────────────────────────────────────────────────
    'ver_auditoria'          => 'Acessar trilha de auditoria',
]);

// Permissões padrão por role (sem entrada em permissoes_usuario → usa este array)
define('PERMISSOES_POR_ROLE', [
    'dev_admin' => array_keys(PERMISSOES_DISPONIVEIS), // tudo
    'super_admin' => array_keys(PERMISSOES_DISPONIVEIS), // tudo
    'gerente' => [
        'ver_bi', 'editar_produto', 'aplicar_desconto', 'imprimir_etiqueta',
        'ver_relatorio_estoque', 'ver_historico_pontos', 'gerenciar_campanhas',
    ],
    'rh_financeiro' => [
        'ver_dre', 'ver_bi', 'exportar_relatorio', 'ver_folha', 'admitir_funcionario',
    ],
    'caixa' => [
        'aplicar_desconto', 'imprimir_etiqueta',
    ],
    'entregador' => [],
    'colaborador' => [], // staff de RH sem acesso ao painel (motorista, auxiliar CEASA)
    'cliente'    => [],
]);
