/**
 * Desffrut — Sacola de Compras (localStorage) rev.2
 *
 * Estrutura: { items: [ { produto_id, nome, unidade, preco, quantidade, subtotal } ] }
 * Sem loja_id — pedidos centralizados, a filial de atendimento é definida internamente.
 */
const sacola = (() => {

    const KEY = 'desffrut_sacola';

    // ── Persistência ──────────────────────────────────────────────────────────
    const EXPIRY_MS = 2 * 60 * 60 * 1000; // 2 horas em milissegundos

    function carregar() {
        try {
            const dados = JSON.parse(localStorage.getItem(KEY)) || { items: [] };
            // Auto-limpar após 2h sem atividade
            if (dados.items.length > 0 && dados._ts && (Date.now() - dados._ts) > EXPIRY_MS) {
                localStorage.removeItem(KEY);
                return { items: [] };
            }
            return dados;
        } catch { return { items: [] }; }
    }

    function salvar(dados) {
        if (dados.items.length > 0) {
            dados._ts = Date.now(); // marca timestamp da última atividade
        } else {
            delete dados._ts;
        }
        localStorage.setItem(KEY, JSON.stringify(dados));
    }

    // ── Cálculos ──────────────────────────────────────────────────────────────
    function totalItens(dados) {
        return dados.items.reduce((s, i) => s + i.quantidade, 0);
    }

    function totalReais(dados) {
        return dados.items.reduce((s, i) => s + i.subtotal, 0);
    }

    function fmtReais(v) {
        return 'R$ ' + parseFloat(v).toFixed(2).replace('.', ',');
    }

    // ── Badge ─────────────────────────────────────────────────────────────────
    function atualizarBadge() {
        const badge = document.getElementById('sacola-badge');
        if (!badge) return;
        const total = totalItens(carregar());
        badge.textContent   = total > 99 ? '99+' : Math.ceil(total);
        badge.style.display = total > 0 ? 'inline-block' : 'none';
    }

    // ── Render do drawer ──────────────────────────────────────────────────────
    function renderizarDrawer() {
        const dados     = carregar();
        const divVazia  = document.getElementById('sacola-vazia');
        const divItens  = document.getElementById('sacola-itens');
        const divRodape = document.getElementById('sacola-rodape');
        const spanTotal = document.getElementById('sacola-total');
        if (!divVazia) return;

        if (dados.items.length === 0) {
            divVazia.style.display  = '';
            divItens.style.display  = 'none';
            divRodape.style.display = 'none';
            return;
        }

        divVazia.style.display  = 'none';
        divItens.style.display  = '';
        divRodape.style.display = '';
        spanTotal.textContent   = fmtReais(totalReais(dados));

        divItens.innerHTML = dados.items.map((item, idx) => {
            const passo = item.unidade === 'kg' ? '0.1' : '1';
            const min   = item.unidade === 'kg' ? '0.1' : '1';
            return `
            <div class="sacola-item">
                <div class="flex-grow-1">
                    <div class="sacola-item-nome">${item.nome}</div>
                    <div class="sacola-item-detalhe">
                        ${fmtReais(item.preco)} / ${item.unidade}
                        → <strong>${fmtReais(item.subtotal)}</strong>
                    </div>
                    <div class="sacola-item-qtd mt-1">
                        <div class="input-group input-group-sm" style="max-width:160px;">
                            <button class="btn btn-outline-secondary btn-qtd-menos"
                                    onclick="sacola.decrementar(${idx})">−</button>
                            <input type="number"
                                   class="form-control text-center qtd-sacola-input"
                                   value="${item.quantidade}"
                                   min="${min}" step="any"
                                   onchange="sacola.alterarQtd(${idx}, this.value)"
                                   style="width:72px;min-width:72px;">
                            <button class="btn btn-outline-secondary btn-qtd-mais"
                                    onclick="sacola.incrementar(${idx})">+</button>
                        </div>
                    </div>
                </div>
                <button class="btn-remover ms-2" onclick="sacola.remover(${idx})"
                        title="Remover item">✕</button>
            </div>`;
        }).join('');
    }

    // ── Adicionar ─────────────────────────────────────────────────────────────
    function adicionar(produto) {
        // produto: { produto_id, nome, unidade, preco }
        const qtdEl    = document.getElementById(`qtd-${produto.produto_id}`);
        const quantidade = parseFloat(qtdEl?.value || '1');

        if (!quantidade || quantidade <= 0) {
            alert('Informe uma quantidade válida.');
            return;
        }

        const dados = carregar();
        const idx   = dados.items.findIndex(i => i.produto_id === produto.produto_id);

        if (idx >= 0) {
            dados.items[idx].quantidade = parseFloat((dados.items[idx].quantidade + quantidade).toFixed(3));
            dados.items[idx].subtotal   = parseFloat((dados.items[idx].quantidade * produto.preco).toFixed(2));
        } else {
            dados.items.push({
                produto_id: produto.produto_id,
                nome:       produto.nome,
                unidade:    produto.unidade,
                preco:      produto.preco,
                quantidade: parseFloat(quantidade.toFixed(3)),
                subtotal:   parseFloat((quantidade * produto.preco).toFixed(2)),
            });
        }

        salvar(dados);
        atualizarBadge();
        renderizarDrawer();

        // Feedback visual no botão
        const btn = document.getElementById(`btn-add-${produto.produto_id}`);
        if (btn) {
            const orig = btn.textContent;
            btn.textContent      = '✓ Adicionado';
            btn.style.background = '#388e3c';
            setTimeout(() => { btn.textContent = orig; btn.style.background = ''; }, 1200);
        }
    }

    // ── Alterar quantidade (input direto) ─────────────────────────────────────
    function alterarQtd(idx, valorStr) {
        const novaQtd = parseFloat(valorStr);
        if (isNaN(novaQtd) || novaQtd <= 0) { remover(idx); return; }

        const dados = carregar();
        if (!dados.items[idx]) return;
        dados.items[idx].quantidade = parseFloat(novaQtd.toFixed(3));
        dados.items[idx].subtotal   = parseFloat((dados.items[idx].quantidade * dados.items[idx].preco).toFixed(2));
        salvar(dados);
        atualizarBadge();
        renderizarDrawer();
    }

    // ── Incrementar / Decrementar ─────────────────────────────────────────────
    function incrementar(idx) {
        const dados = carregar();
        if (!dados.items[idx]) return;
        const passo = dados.items[idx].unidade === 'kg' ? 0.1 : 1;
        alterarQtd(idx, dados.items[idx].quantidade + passo);
    }

    function decrementar(idx) {
        const dados = carregar();
        if (!dados.items[idx]) return;
        const passo = dados.items[idx].unidade === 'kg' ? 0.1 : 1;
        const nova  = parseFloat((dados.items[idx].quantidade - passo).toFixed(3));
        if (nova <= 0) { remover(idx); return; }
        alterarQtd(idx, nova);
    }

    // ── Remover item ──────────────────────────────────────────────────────────
    function remover(idx) {
        const dados = carregar();
        dados.items.splice(idx, 1);
        salvar(dados);
        atualizarBadge();
        renderizarDrawer();
    }

    // ── Esvaziar ──────────────────────────────────────────────────────────────
    function esvaziar() {
        if (!confirm('Esvaziar toda a sacola?')) return;
        salvar({ items: [] });
        atualizarBadge();
        renderizarDrawer();
    }

    // ── Finalizar → checkout (Fase 6) ────────────────────────────────────────
    function finalizar() {
        const dados = carregar();
        if (dados.items.length === 0) {
            alert('Sua sacola está vazia.');
            return;
        }
        if (!APP.usuario) {
            // Salva URL de retorno e manda para login
            sessionStorage.setItem('desffrut_redirect_pos_login', APP.base + '/checkout');
            window.location.href = APP.base + '/login';
            return;
        }
        // Cliente logado → checkout de tele-entrega
        window.location.href = APP.base + '/checkout';
    }

    // ── Init ──────────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        atualizarBadge();
        document.getElementById('sacolaDrawer')
            ?.addEventListener('show.bs.offcanvas', renderizarDrawer);
    });

    return { adicionar, alterarQtd, incrementar, decrementar, remover, esvaziar, finalizar, carregar };
})();
