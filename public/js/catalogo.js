/**
 * Desffrut — Catálogo Digital centralizado (Fase 2 rev.1)
 * Pedidos centralizados: o cliente não escolhe filial.
 * Produtos são agregados de todas as lojas; menor preço exibido.
 */
(async () => {

    // ── Estado ────────────────────────────────────────────────────────────────
    let todosProdutos  = [];
    let categoriaAtiva = '';
    let termoBusca     = '';

    // ── Elementos ─────────────────────────────────────────────────────────────
    const estadoLoad   = document.getElementById('estado-carregando');
    const estadoVazio  = document.getElementById('estado-vazio-produtos');
    const gridProdutos = document.getElementById('grid-produtos');
    const busca        = document.getElementById('busca-input');

    // ── Utilitários ───────────────────────────────────────────────────────────
    function fmtReais(v) {
        return 'R$ ' + parseFloat(v).toFixed(2).replace('.', ',');
    }

    function iconeCategoria(cat) {
        return { frutas: '🍎', verduras: '🥬', legumes: '🥕', outros: '📦' }[cat] || '📦';
    }

    function setState(estado) {
        estadoLoad.style.display  = estado === 'carregando' ? '' : 'none';
        estadoVazio.style.display = estado === 'vazio'      ? '' : 'none';
        gridProdutos.style.display= estado === 'produtos'   ? '' : 'none';
    }

    // ── Carga de produtos ─────────────────────────────────────────────────────
    async function carregarProdutos() {
        setState('carregando');
        try {
            const r = await fetch(APP.api + '/produtos/catalogo');
            const j = await r.json();
            todosProdutos = j.data || [];
            renderizarGrid();
        } catch (e) {
            console.error('Erro ao carregar produtos:', e);
            setState('vazio');
        }
    }

    // ── Grid ──────────────────────────────────────────────────────────────────
    function filtrar() {
        return todosProdutos.filter(p => {
            const cat   = !categoriaAtiva || p.categoria === categoriaAtiva;
            const termo = !termoBusca || p.nome.toLowerCase().includes(termoBusca.toLowerCase());
            return cat && termo;
        });
    }

    function cardHtml(p) {
        const passo    = p.unidade === 'kg' ? '0.1' : '1';
        const minimo   = p.unidade === 'kg' ? '0.1' : '1';
        const unidLbl  = p.unidade === 'kg' ? '/ kg' : '/ un';

        const promoBadge   = p.em_promocao ? `<span class="badge-promo ms-1">OFERTA</span>` : '';
        const precoRiscado = p.em_promocao
            ? `<small class="preco-riscado d-block">${fmtReais(p.preco_normal)}</small>` : '';

        const fotoHtml = p.foto
            ? `<img src="${p.foto}" alt="${p.nome}" class="produto-foto" loading="lazy">`
            : `<div class="produto-foto-placeholder">${iconeCategoria(p.categoria)}</div>`;

        const itemData = JSON.stringify({
            produto_id: p.id,
            nome:       p.nome,
            unidade:    p.unidade,
            preco:      p.preco_atual,
        }).replace(/'/g, "\\'");

        return `
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card h-100 produto-card" data-categoria="${p.categoria}">
                ${fotoHtml}
                <div class="card-body d-flex flex-column p-3">
                    <div class="d-flex align-items-center gap-1 mb-1">
                        <span class="badge bg-success" style="font-size:.7rem;">
                            ${iconeCategoria(p.categoria)} ${p.categoria}
                        </span>
                        ${promoBadge}
                    </div>
                    <h6 class="card-title mb-1 fw-bold" style="font-size:.9rem;">${p.nome}</h6>
                    <div class="mt-auto">
                        <div class="mb-2">
                            <span class="preco-atual">${fmtReais(p.preco_atual)}</span>
                            <small class="text-muted">${unidLbl}</small>
                            ${precoRiscado}
                        </div>
                        <div class="d-flex">
                            <input type="number" id="qtd-${p.id}"
                                   class="form-control form-control-sm qtd-input"
                                   value="${minimo}" min="${minimo}" step="${passo}">
                            <button id="btn-add-${p.id}" class="btn-add flex-grow-1"
                                    onclick="sacola.adicionar(JSON.parse(this.dataset.item))"
                                    data-item='${itemData}'>
                                + Sacola
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
    }

    function renderizarGrid() {
        const lista = filtrar();
        if (lista.length === 0) { setState('vazio'); return; }
        setState('produtos');
        gridProdutos.innerHTML = lista.map(cardHtml).join('');
    }

    // ── Abas de categoria ─────────────────────────────────────────────────────
    document.querySelectorAll('.btn-categoria').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.btn-categoria').forEach(b => b.classList.remove('ativa'));
            btn.classList.add('ativa');
            categoriaAtiva = btn.dataset.cat;
            if (todosProdutos.length) renderizarGrid();
        });
    });

    // ── Busca ─────────────────────────────────────────────────────────────────
    busca?.addEventListener('input', () => {
        termoBusca = busca.value.trim();
        if (todosProdutos.length) renderizarGrid();
    });

    // ── Init ──────────────────────────────────────────────────────────────────
    await carregarProdutos();

})();
