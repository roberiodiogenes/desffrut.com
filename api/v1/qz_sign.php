<?php
/**
 * DESCONTINUADO — Fase 5 Revisão 2.0
 *
 * Este endpoint era usado pelo QZ Tray para assinar requisições.
 * O QZ Tray foi substituído por Native Messaging (extensão Chrome/Edge).
 * Este arquivo é mantido apenas para evitar erros 404 em instalações legadas.
 *
 * @deprecated desde 2026-06-27
 * @see extension/chrome/ e extension/native-host/
 */
http_response_code(410);
header('Content-Type: application/json');
echo json_encode([
    'ok'   => false,
    'erro' => 'QZ Tray descontinuado. Use a extensão Desffrut Hardware (Native Messaging).',
]);
