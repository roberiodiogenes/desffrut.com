/**
 * Desffrut — Gestão do IndexedDB (PDV Offline)
 * Fase 4: abertura/criação do banco local e operações CRUD sobre as stores.
 *
 * Stores:
 *   - produtos  : catálogo snapshot baixado na abertura do dia
 *   - clientes  : base mínima para busca offline por CPF
 *   - vendas    : cupons gerados online e offline (campo synced: bool)
 */

'use strict';

const DesffrDatabase = (function () {

    const DB_NAME    = 'desffrut_pdv';
    const DB_VERSION = 1;
    let _db = null;

    // ── Abre (ou cria) o banco ────────────────────────────────────────────────
    function abrir() {
        return new Promise((resolve, reject) => {
            if (_db) return resolve(_db);

            const req = indexedDB.open(DB_NAME, DB_VERSION);

            req.onupgradeneeded = function (e) {
                const db = e.target.result;

                // Store de produtos
                if (!db.objectStoreNames.contains('produtos')) {
                    const sp = db.createObjectStore('produtos', { keyPath: 'id' });
                    sp.createIndex('ean',       'ean',       { unique: false });
                    sp.createIndex('categoria', 'categoria', { unique: false });
                    sp.createIndex('nome',      'nome',      { unique: false });
                }

                // Store de clientes
                if (!db.objectStoreNames.contains('clientes')) {
                    const sc = db.createObjectStore('clientes', { keyPath: 'id' });
                    sc.createIndex('cpf',  'cpf',  { unique: true  });
                    sc.createIndex('nome', 'nome', { unique: false });
                }

                // Store de vendas (cupons)
                if (!db.objectStoreNames.contains('vendas')) {
                    const sv = db.createObjectStore('vendas', { keyPath: 'cupom_uuid' });
                    sv.createIndex('synced',     'synced',     { unique: false });
                    sv.createIndex('created_at', 'created_at', { unique: false });
                }
            };

            req.onsuccess = e  => { _db = e.target.result; resolve(_db); };
            req.onerror   = e  => reject(e.target.error);
        });
    }

    // ── Helpers de transação ──────────────────────────────────────────────────
    function tx(store, mode = 'readonly') {
        return _db.transaction([store], mode).objectStore(store);
    }

    function promisify(req) {
        return new Promise((res, rej) => {
            req.onsuccess = e => res(e.target.result);
            req.onerror   = e => rej(e.target.error);
        });
    }

    // ── PRODUTOS ──────────────────────────────────────────────────────────────

    async function salvarProdutos(lista) {
        await abrir();
        const store = tx('produtos', 'readwrite');
        // Limpa e recarrega (snapshot fresco)
        await promisify(store.clear());
        for (const p of lista) store.put(p);
        return lista.length;
    }

    async function buscarProduto(termo) {
        await abrir();
        // Busca por EAN exato primeiro, depois por nome parcial
        const todos = await promisify(tx('produtos').getAll());
        const t = termo.toLowerCase().trim();
        // EAN exato
        const porEan = todos.filter(p => p.ean && p.ean === t);
        if (porEan.length) return porEan;
        // Nome parcial
        return todos.filter(p => p.nome.toLowerCase().includes(t));
    }

    async function getProduto(id) {
        await abrir();
        return promisify(tx('produtos').get(id));
    }

    async function listarProdutos() {
        await abrir();
        return promisify(tx('produtos').getAll());
    }

    // ── CLIENTES ──────────────────────────────────────────────────────────────

    async function salvarClientes(lista) {
        await abrir();
        const store = tx('clientes', 'readwrite');
        await promisify(store.clear());
        for (const c of lista) store.put(c);
        return lista.length;
    }

    // Grava/atualiza um único cliente sem apagar o restante do cache local
    // (usado após cadastro feito no próprio PDV, para busca offline imediata).
    async function salvarClienteUnico(c) {
        await abrir();
        await promisify(tx('clientes', 'readwrite').put(c));
        return c;
    }

    async function buscarClientePorCpf(cpf) {
        await abrir();
        const cpfLimpo = cpf.replace(/\D/g, '');
        const idx = tx('clientes').index('cpf');
        // Tenta com e sem formatação
        let c = await promisify(idx.get(cpfLimpo));
        if (!c) {
            // Formato com pontuação (000.000.000-00)
            const fmt = cpfLimpo.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            c = await promisify(idx.get(fmt));
        }
        return c || null;
    }

    // ── VENDAS (cupons) ───────────────────────────────────────────────────────

    function gerarUUID() {
        return ([1e7]+-1e3+-4e3+-8e3+-1e11)
            .replace(/[018]/g, c =>
                (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
            );
    }

    async function salvarVenda(dadosVenda) {
        await abrir();
        const cupom = {
            cupom_uuid:      dadosVenda.cupom_uuid      || gerarUUID(),
            caixa_id:        dadosVenda.caixa_id        || null,
            loja_id:         dadosVenda.loja_id,
            cliente_id:      dadosVenda.cliente_id      || null,
            forma_pagamento: dadosVenda.forma_pagamento,
            desconto:        dadosVenda.desconto        || 0,
            pontos_usados:   dadosVenda.pontos_usados   || 0,
            itens:           dadosVenda.itens           || [],
            synced:          false,
            created_at:      new Date().toISOString(),
        };
        await promisify(tx('vendas', 'readwrite').put(cupom));
        return cupom;
    }

    async function marcarSincronizado(cupom_uuid, venda_id) {
        await abrir();
        const store = tx('vendas', 'readwrite');
        const cupom = await promisify(store.get(cupom_uuid));
        if (cupom) {
            cupom.synced    = true;
            cupom.venda_id  = venda_id;
            cupom.synced_at = new Date().toISOString();
            await promisify(store.put(cupom));
        }
    }

    async function listarPendentes() {
        await abrir();
        const todos = await promisify(tx('vendas').getAll());
        return todos.filter(v => !v.synced);
    }

    async function listarVendasHoje() {
        await abrir();
        const hoje  = new Date().toISOString().slice(0, 10);
        const todos = await promisify(tx('vendas').getAll());
        return todos.filter(v => v.created_at && v.created_at.slice(0, 10) === hoje);
    }

    // ── Info de estado ────────────────────────────────────────────────────────

    async function info() {
        await abrir();
        const [prods, clientes, vendas] = await Promise.all([
            promisify(tx('produtos').count()),
            promisify(tx('clientes').count()),
            promisify(tx('vendas').count()),
        ]);
        const pendentes = (await listarPendentes()).length;
        return { prods, clientes, vendas, pendentes };
    }

    // ── API pública ───────────────────────────────────────────────────────────
    return {
        abrir,
        gerarUUID,
        // Produtos
        salvarProdutos,
        buscarProduto,
        getProduto,
        listarProdutos,
        // Clientes
        salvarClientes,
        salvarClienteUnico,
        buscarClientePorCpf,
        // Vendas
        salvarVenda,
        marcarSincronizado,
        listarPendentes,
        listarVendasHoje,
        // Info
        info,
    };

})();
