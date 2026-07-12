<?php
/**
 * Desffrut — Logout: encerra sessão e redireciona para o catálogo público.
 */
require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/helpers/functions.php';

iniciar_sessao();
$_SESSION = [];
session_destroy();

redirecionar('/');
