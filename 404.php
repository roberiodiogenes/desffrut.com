<?php
/**
 * 404.php — Handler de erro na raiz do projeto.
 * Referenciado pelo .htaccess via: ErrorDocument 404 /404.php (HostGator)
 * e capturado pelo catch-all RewriteRule tanto em XAMPP quanto em produção.
 */
require_once __DIR__ . '/views/errors/404.php';
