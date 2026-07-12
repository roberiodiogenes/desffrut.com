/**
 * Desffrut — Interface da Frente de Caixa (PDV)
 * Fase 4: venda rápida por atalhos de teclado, múltiplas formas de pagamento.
 *
 * Atalhos:
 *   F1  → Foco no campo de busca de produto
 *   F2  → Finalizar venda (abre modal de pagamento)
 *   F3  → Cancelar último item (requer senha do gerente)
 *   F4  → Sangria (redireciona para /pdv/sangria)
 *   ESC → Limpa campo de busca / fecha modal
 *
 * Formas de pagamento: Dinheiro | Cartão Débito | Cartão Crédito | Pix (manual) | Pontos
 *
 * Depende de: indexeddb.js (DesffrDatabase), sync.js (DesffrSync), APP.api, APP.token
 */

'use strict';

const PDV = (function () {

    // ── Estado ────────────────────────────────────────────────────────────────
    const estado = {
        caixa_id:   null,
        loja_id:    null,
        cliente:    null,    // { id, nome, cpf, pontos_fidelidade } ou null
        itens:      [],      // [{ produto, quantidade, preco_snap, subtotal }]
        desconto:   0,
        pontos_usados: 0,
    };

    // ── Totais ────────────────────────────────────────────────────────────────
    function calcularTotais() {
        const bruto    = estado.itens.reduce((s, i) => s + i.subtotal, 0);
        const liq      = Math.max(0, bruto - estado.desconto);
        return { bruto, desconto: estado.desconto, liquido: liq };
    }

    // ── Adicionar item ────────────────────────────────────────────────────────
    async function adicionarItem(termo) {
        const resultados = await DesffrDatabase.buscarProduto(termo.trim());
        if (!resultados || resultados.length === 0) {
            mostrarAlerta('Produto não encontrado: ' + termo, 'warning');
            return;
        }

        // Se mais de um resultado: exibe lista de seleção
        if (resultados.length > 1) {
            exibirSelecaoProduto(resultados);
            return;
        }

        const produto = resultados[0];
        const preco   = parseFloat(produto.preco_vigente) || 0;
        if (preco <= 0) {
            mostrarAlerta('Produto sem preço cadastrado para esta loja.', 'danger');
            return;
        }

        // Produto por peso: abre modal de quantidade
        if (produto.unidade_medida === 'kg') {
            abrirModalQuantidade(produto, preco);
            return;
        }

        // Produto por unidade: adiciona 1
        _inserirItem(produto, 1, preco);
    }

    function _inserirItem(produto, quantidade, preco_snap) {
        // Verifica se já está no carrinho
        const existente = estado.itens.find(i => i.produto.id === produto.id);
        if (existente) {
            existente.quantidade += quantidade;
            existente.subtotal    = round2(existente.quantidade * existente.preco_snap);
        } else {
            estado.itens.push({
                produto,
                quantidade,
                preco_snap,
                subtotal: round2(quantidade * preco_snap),
            });
        }
        renderizarCarrinho();
        renderizarProdutoAtual(produto, quantidade, preco_snap);
        limparBusca();
    }

    // ── Card "produto atual" ─────────────────────────────────────────────────
    function renderizarProdutoAtual(produto, quantidade, preco_snap) {
        const el = document.getElementById('pdv-produto-atual');
        if (!el) return;
        const total = round2(quantidade * preco_snap);
        const foto = produto.foto_url
            ? `<img src="${htmlEsc(produto.foto_url)}" class="pdv-produto-atual-foto" alt="">`
            : `<div class="pdv-produto-atual-placeholder">🥦</div>`;
        el.innerHTML = `
            ${foto}
            <div class="pdv-produto-atual-info">
                <div class="pdv-produto-atual-nome">${htmlEsc(produto.nome)}</div>
                <div class="pdv-produto-atual-cod">Cód. ${htmlEsc(produto.codigo_interno || produto.codigo_barras || produto.id)}</div>
                <div class="pdv-produto-atual-linha">
                    <span>Qtd <strong>${quantidade}${produto.unidade_medida === 'kg' ? ' kg' : ''}</strong></span>
                    <span>Unit. <strong>${formatBRL(preco_snap)}</strong></span>
                    <span>Total <strong class="text-success">${formatBRL(total)}</strong></span>
                </div>
            </div>`;
    }

    // ── Remover item ──────────────────────────────────────────────────────────
    async function removerItem(index) {
        const senhaOk = await validarSenhaGerente('Cancelamento de item requer confirmação do gerente.');
        if (!senhaOk) return;
        estado.itens.splice(index, 1);
        renderizarCarrinho();
        registrarLog('item_removido');
    }

    // ── Alterar quantidade ────────────────────────────────────────────────────
    function alterarQuantidade(index, novaQtd) {
        const qtd = parseFloat(novaQtd);
        if (isNaN(qtd) || qtd <= 0) { removerItem(index); return; }
        estado.itens[index].quantidade = qtd;
        estado.itens[index].subtotal   = round2(qtd * estado.itens[index].preco_snap);
        renderizarCarrinho();
    }

    // ── Cliente ───────────────────────────────────────────────────────────────
    async function buscarCliente(cpf) {
        const c = await DesffrDatabase.buscarClientePorCpf(cpf);
        if (c) {
            estado.cliente = c;
            renderizarCliente();
        } else {
            mostrarAlerta('CPF não encontrado no cadastro.', 'info');
        }
    }

    function removerCliente() {
        estado.cliente       = null;
        estado.pontos_usados = 0;
        renderizarCliente();
    }

    // ── Cadastro de cliente no balcão ────────────────────────────────────────
    let _fotoCadastroFile = null;

    function abrirCadastroCliente() {
        const el = document.getElementById('pdv-modal-cadastro-cliente');
        if (!el) return;
        const cpfAtual = document.getElementById('pdv-cpf')?.value || '';
        ['cad-nome','cad-telefone','cad-whatsapp','cad-endereco','cad-numero','cad-complemento','cad-bairro','cad-cidade']
            .forEach(id => { const f = document.getElementById(id); if (f) f.value = ''; });
        const cpfField = document.getElementById('cad-cpf');
        if (cpfField) cpfField.value = cpfAtual;
        const fotoPrev = document.getElementById('cad-foto-preview');
        if (fotoPrev) fotoPrev.style.display = 'none';
        const fotoInput = document.getElementById('cad-foto');
        if (fotoInput) fotoInput.value = '';
        _fotoCadastroFile = null;
        el.style.display = 'flex';
        setTimeout(() => document.getElementById('cad-nome')?.focus(), 10);
    }

    function fecharCadastroCliente() {
        const el = document.getElementById('pdv-modal-cadastro-cliente');
        if (el) el.style.display = 'none';
    }

    function previewFotoCadastro(input) {
        const prev = document.getElementById('cad-foto-preview');
        if (!input.files || !input.files[0] || !prev) return;
        _fotoCadastroFile = input.files[0];
        const reader = new FileReader();
        reader.onload = e => { prev.src = e.target.result; prev.style.display = 'block'; };
        reader.readAsDataURL(_fotoCadastroFile);
    }

    async function salvarCadastroCliente() {
        const nome = document.getElementById('cad-nome')?.value.trim() || '';
        if (!nome) { mostrarAlerta('Informe o nome do cliente.', 'warning'); return; }
        if (!navigator.onLine) { mostrarAlerta('Cadastro de cliente requer conexão com a internet.', 'warning'); return; }

        const btn = document.getElementById('cad-btn-salvar');
        if (btn) btn.disabled = true;

        const fd = new FormData();
        fd.append('nome', nome);
        ['cpf','telefone','whatsapp','endereco','numero','complemento','bairro','cidade'].forEach(campo => {
            fd.append(campo, document.getElementById('cad-' + campo)?.value.trim() || '');
        });
        if (_fotoCadastroFile) fd.append('foto', _fotoCadastroFile);

        try {
            const token = APP.token || sessionStorage.getItem('desffrut_token') || '';
            const resp = await fetch(`${APP.api}/clientes/cadastro`, {
                method:  'POST',
                headers: { Authorization: 'Bearer ' + token },
                body:    fd,
            });
            const json = await resp.json();
            if (json.status !== 'ok') {
                mostrarAlerta(json.message || 'Não foi possível cadastrar o cliente.', 'danger');
                return;
            }
            const cliente = {
                id:                 json.data.id,
                nome:               json.data.nome,
                cpf:                json.data.cpf,
                pontos_fidelidade:  json.data.pontos_fidelidade || 0,
            };
            await DesffrDatabase.salvarClienteUnico(cliente);
            estado.cliente = cliente;
            renderizarCliente();
            const cpfEl = document.getElementById('pdv-cpf');
            if (cpfEl && cliente.cpf) cpfEl.value = formatCPF(cliente.cpf);
            fecharCadastroCliente();
            mostrarAlerta(`✅ Cliente "${cliente.nome}" cadastrado e selecionado.`, 'success');
        } catch (e) {
            mostrarAlerta('Erro de conexão ao cadastrar cliente.', 'danger');
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    // ── Finalizar venda ───────────────────────────────────────────────────────
    async function confirmarPagamento() {
        const forma   = document.getElementById('pdv-pag-forma').value;
        const totais  = calcularTotais();
        const btn     = document.getElementById('pdv-btn-confirmar-pag');
        if (btn) btn.disabled = true;

        // Salva info do cliente antes de limpar o estado
        const clienteNome = estado.cliente?.nome  || null;
        const clienteId   = estado.cliente?.id    || null;

        // Monta cupom
        const cupom = {
            cupom_uuid:      DesffrDatabase.gerarUUID(),
            caixa_id:        estado.caixa_id,
            loja_id:         estado.loja_id,
            cliente_id:      clienteId,
            forma_pagamento: forma,
            desconto:        totais.desconto,
            pontos_usados:   estado.pontos_usados,
            itens:           estado.itens.map(i => ({
                produto_id:              i.produto.id,
                quantidade:              i.quantidade,
                preco_unitario_snapshot: i.preco_snap,
            })),
            created_at: new Date().toISOString(),
        };

        // Salva localmente (sempre — online ou offline)
        await DesffrDatabase.salvarVenda(cupom);

        // Tenta enviar online
        let venda_id     = null;
        let pontos_ganhos = clienteId ? Math.floor(totais.liquido) : 0; // estimativa offline
        if (navigator.onLine) {
            try {
                const token = APP.token || sessionStorage.getItem('desffrut_token') || '';
                const resp  = await fetch(`${APP.api}/vendas`, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
                    body:    JSON.stringify(cupom),
                });
                const json = await resp.json();
                if (json.status === 'ok') {
                    venda_id     = json.data.id;
                    pontos_ganhos = json.data.pontos_ganhos ?? pontos_ganhos; // valor real do servidor
                    await DesffrDatabase.marcarSincronizado(cupom.cupom_uuid, venda_id);
                    if (json.data.alertas_estoque?.length) {
                        mostrarAlerta('⚠️ Produto(s) com estoque zerado — avise o gerente.', 'warning');
                    }
                }
            } catch {
                // Offline — será sincronizado depois pelo sync.js
            }
        }

        imprimirCupomUI(cupom, totais, pontos_ganhos);
        limparVenda();
        if (btn) btn.disabled = false;

        // Toast com feedback de pontos
        const msgPontos = (clienteNome && pontos_ganhos > 0)
            ? ` · ${clienteNome} ganhou +${pontos_ganhos} pts`
            : '';
        mostrarAlerta(`✅ Venda finalizada!${msgPontos}`, 'success');

        // Dispara evento para impressão automática via hardware.js (Fase 5)
        document.dispatchEvent(new CustomEvent('desffrut:venda:concluida', {
            detail: { cupom, totais, pontos_ganhos }
        }));
    }

    // ── Limpar venda ──────────────────────────────────────────────────────────
    function limparVenda() {
        estado.itens        = [];
        estado.desconto     = 0;
        estado.pontos_usados = 0;
        estado.cliente      = null;
        // Limpa campo CPF
        const cpfEl = document.getElementById('pdv-cpf');
        if (cpfEl) cpfEl.value = '';
        const pa = document.getElementById('pdv-produto-atual');
        if (pa) pa.innerHTML = '<div class="pdv-produto-atual-vazio">Nenhum produto adicionado ainda</div>';
        selecionarForma('dinheiro');
        renderizarCarrinho();
        renderizarCliente();
        focarBusca();
    }

    // ── Renderização ──────────────────────────────────────────────────────────
    function renderizarCarrinho() {
        const tbody  = document.getElementById('pdv-itens-tbody');
        const totais = calcularTotais();
        if (!tbody) return;

        tbody.innerHTML = estado.itens.map((item, idx) => `
            <tr>
                <td>${idx + 1}</td>
                <td class="pdv-col-nome">${htmlEsc(item.produto.nome)}</td>
                <td>
                    <input type="number" class="pdv-qty-input"
                        value="${item.quantidade}" min="0.001" step="${item.produto.unidade_medida === 'kg' ? '0.001' : '1'}"
                        onchange="PDV.alterarQuantidade(${idx}, this.value)"
                        style="width:70px; text-align:right;">
                    <small class="text-muted">${item.produto.unidade_medida}</small>
                </td>
                <td class="text-end">${formatBRL(item.preco_snap)}</td>
                <td class="text-end fw-semibold">${formatBRL(item.subtotal)}</td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-danger" onclick="PDV.removerItem(${idx})" title="Remover (F3 para último)">✕</button>
                </td>
            </tr>
        `).join('') || '<tr><td colspan="6" class="text-center text-muted py-3">Nenhum item adicionado</td></tr>';

        // Totais
        const elBruto  = document.getElementById('pdv-total-bruto');
        const elDesc   = document.getElementById('pdv-total-desconto');
        const elLiq    = document.getElementById('pdv-total-liquido');
        if (elBruto)  elBruto.textContent  = formatBRL(totais.bruto);
        if (elDesc)   elDesc.textContent   = formatBRL(totais.desconto);
        if (elLiq)    elLiq.textContent    = formatBRL(totais.liquido);

        // Atualiza "+X pts a ganhar" conforme carrinho muda
        if (estado.cliente) renderizarCliente();

        // Painel de pagamento (sempre visível): total, troco e valores rápidos
        atualizarPainelPagamento(totais);
    }

    // ── Painel de pagamento (sempre visível na coluna direita) ──────────────────
    function atualizarPainelPagamento(totais) {
        totais = totais || calcularTotais();

        const elTotal = document.getElementById('pdv-pag-total');
        if (elTotal) elTotal.textContent = formatBRL(totais.liquido);

        const forma = document.getElementById('pdv-pag-forma')?.value || 'dinheiro';
        const inputValor = document.getElementById('pdv-pag-valor');
        if (inputValor && forma !== 'dinheiro') {
            inputValor.value = totais.liquido > 0 ? totais.liquido.toFixed(2) : '';
        }

        const quick = document.getElementById('pdv-quick-vals');
        if (quick) {
            const total = totais.liquido;
            const notas = [5, 10, 20, 50, 100].filter(v => v >= total);
            if (total > 0 && !notas.includes(Math.ceil(total))) notas.unshift(Math.ceil(total));
            quick.innerHTML = notas.slice(0, 5).map(v =>
                `<button onclick="document.getElementById('pdv-pag-valor').value='${v}';PDV.calcularTroco()">R$ ${v}</button>`
            ).join('');
        }

        calcularTroco();
    }

    // ── Seleção da forma de pagamento (botões sempre visíveis) ──────────────────
    function selecionarForma(forma) {
        const el = document.getElementById('pdv-pag-forma');
        if (el) el.value = forma;

        document.querySelectorAll('.pdv-forma-btn').forEach(b => {
            b.classList.toggle('ativo', b.dataset.forma === forma);
        });

        const inputValor = document.getElementById('pdv-pag-valor');
        if (inputValor) {
            if (forma === 'dinheiro') {
                inputValor.readOnly = false;
            } else {
                const totais = calcularTotais();
                inputValor.value = totais.liquido > 0 ? totais.liquido.toFixed(2) : '';
                inputValor.readOnly = true;
            }
        }
        calcularTroco();
    }

    // ── Finalizar venda (antes: abria modal em 3 passos; agora painel fixo) ─────
    function finalizarVenda() {
        if (estado.itens.length === 0) {
            mostrarAlerta('Adicione ao menos um produto antes de finalizar.', 'warning');
            return;
        }
        confirmarPagamento();
    }

    function renderizarCliente() {
        const el = document.getElementById('pdv-cliente-info');
        if (!el) return;
        const elPts = document.getElementById('pdv-pag-pontos-disponiveis');
        if (elPts) elPts.textContent = estado.cliente ? estado.cliente.pontos_fidelidade : '0';
        if (estado.cliente) {
            const totais     = calcularTotais();
            const ptsGanhar  = Math.floor(totais.liquido); // PONTOS_POR_REAL = 1
            const ptsPreview = ptsGanhar > 0
                ? `<span class="badge bg-success ms-1" title="Pontos a ganhar nesta compra">+${ptsGanhar} pts</span>`
                : '';
            el.innerHTML = `
                <span class="text-success fw-semibold">👤 ${htmlEsc(estado.cliente.nome)}</span>
                <small class="text-muted ms-2">${formatCPF(estado.cliente.cpf || '')}</small>
                <span class="badge bg-warning text-dark ms-2" title="Saldo atual">🏆 ${estado.cliente.pontos_fidelidade} pts</span>
                ${ptsPreview}
                <button class="btn btn-sm btn-link text-danger p-0 ms-2" onclick="PDV.removerCliente()">✕</button>
            `;
        } else {
            el.innerHTML = '<span class="text-muted small">Sem cliente identificado</span>';
        }
    }

    function exibirSelecaoProduto(produtos) {
        const el = document.getElementById('pdv-lista-produtos');
        if (!el) return;
        el.innerHTML = produtos.map(p => `
            <button class="list-group-item list-group-item-action" onclick="PDV._selecionarProduto(${p.id})">
                <strong>${htmlEsc(p.nome)}</strong>
                <span class="float-end text-success fw-bold">${formatBRL(p.preco_vigente)}</span>
                <br><small class="text-muted">${p.ean || ''} · ${p.categoria} · ${p.unidade_medida}</small>
            </button>
        `).join('');
        el.style.display = 'block';
    }

    async function _selecionarProduto(id) {
        const p = await DesffrDatabase.getProduto(id);
        const el = document.getElementById('pdv-lista-produtos');
        if (el) el.style.display = 'none';
        if (!p) return;
        if (p.unidade_medida === 'kg') {
            abrirModalQuantidade(p, parseFloat(p.preco_vigente));
        } else {
            _inserirItem(p, 1, parseFloat(p.preco_vigente));
        }
    }

    function abrirModalQuantidade(produto, preco) {
        const el = document.getElementById('pdv-modal-quantidade');
        if (!el) return;
        document.getElementById('pdv-qty-nome').textContent  = produto.nome;
        document.getElementById('pdv-qty-preco').textContent = formatBRL(preco);
        document.getElementById('pdv-qty-valor').value = '';
        el.style.display = 'flex';
        el._produto = produto;
        el._preco   = preco;
        document.getElementById('pdv-qty-valor').focus();
    }

    function confirmarQuantidade() {
        const el    = document.getElementById('pdv-modal-quantidade');
        const qty   = parseFloat(document.getElementById('pdv-qty-valor').value);
        if (!el || isNaN(qty) || qty <= 0) return;
        _inserirItem(el._produto, qty, el._preco);
        el.style.display = 'none';
    }

    // ── Impressão simplificada (texto) ────────────────────────────────────────
    function imprimirCupomUI(cupom, totais, pontos_ganhos = 0) {
        const el = document.getElementById('pdv-cupom-preview');
        if (!el) return;
        const linhas = [
            '================================',
            '        DESFFRUT HORTIFRUTI',
            '================================',
            `Data: ${new Date().toLocaleString('pt-BR')}`,
            `UUID: ${cupom.cupom_uuid.slice(0, 8)}...`,
            '--------------------------------',
            ...cupom.itens.map(i =>
                `${i.produto_id} x${i.quantidade} @ ${formatBRL(i.preco_unitario_snapshot)} = ${formatBRL(i.quantidade * i.preco_unitario_snapshot)}`
            ),
            '--------------------------------',
            `TOTAL: ${formatBRL(totais.liquido)}`,
            `Pagamento: ${cupom.forma_pagamento.toUpperCase()}`,
        ];
        if (cupom.cliente_id && pontos_ganhos > 0) {
            linhas.push('--------------------------------');
            linhas.push(`🏆 Pontos ganhos: +${pontos_ganhos} pts`);
        }
        linhas.push('================================');
        el.textContent = linhas.join('\n');
        el.style.display = 'block';
    }

    // ── Senha do gerente (modal simples) ──────────────────────────────────────
    function validarSenhaGerente(mensagem = 'Confirmação necessária') {
        return new Promise(resolve => {
            const senha = prompt(`🔐 ${mensagem}\n\nDigite a senha do gerente:`);
            if (!senha) return resolve(false);
            const token = APP.token || sessionStorage.getItem('desffrut_token') || '';
            fetch(`${APP.api}/auth/validar-gerente`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
                body:    JSON.stringify({ senha, loja_id: estado.loja_id }),
            })
            .then(r => r.json())
            .then(j => resolve(j.status === 'ok'))
            .catch(() => {
                // Offline: aceita condicionalmente (registra para auditoria posterior)
                resolve(true);
            });
        });
    }

    // ── Atalhos de teclado ────────────────────────────────────────────────────
    function _registrarAtalhos() {
        document.addEventListener('keydown', function (e) {
            // Não interfere quando foco está em input (exceto F*)
            const emInput = ['INPUT','TEXTAREA','SELECT'].includes(e.target.tagName);

            switch (e.key) {
                case 'F1':
                    e.preventDefault();
                    focarBusca();
                    break;
                case 'F2':
                    e.preventDefault();
                    finalizarVenda();
                    break;
                case 'F3':
                    e.preventDefault();
                    if (estado.itens.length > 0) removerItem(estado.itens.length - 1);
                    break;
                case 'F4':
                    e.preventDefault();
                    window.location.href = APP.base + '/pdv/sangria';
                    break;
                case 'F5':
                    e.preventDefault();
                    if (typeof abrirScannerPDV === 'function') abrirScannerPDV();
                    break;
                case 'Escape':
                    limparBusca();
                    fecharCadastroCliente();
                    const mq = document.getElementById('pdv-modal-quantidade');
                    if (mq) mq.style.display = 'none';
                    const lp = document.getElementById('pdv-lista-produtos');
                    if (lp) lp.style.display = 'none';
                    break;
            }
        });
    }

    // ── Helpers de UI ─────────────────────────────────────────────────────────
    function focarBusca() {
        const el = document.getElementById('pdv-busca');
        if (el) { el.value = ''; el.focus(); }
    }

    function limparBusca() {
        const el = document.getElementById('pdv-busca');
        if (el) el.value = '';
        const lp = document.getElementById('pdv-lista-produtos');
        if (lp) lp.style.display = 'none';
    }

    function mostrarAlerta(msg, tipo = 'info') {
        const el = document.getElementById('pdv-alerta');
        if (!el) return;
        el.className = `alert alert-${tipo} pdv-alerta-toast`;
        el.textContent = msg;
        el.style.display = 'block';
        clearTimeout(el._timer);
        el._timer = setTimeout(() => { el.style.display = 'none'; }, 3500);
    }

    function registrarLog(acao) {
        // Log local — auditoria offline simples
        const logs = JSON.parse(localStorage.getItem('pdv_audit_log') || '[]');
        logs.push({ acao, ts: new Date().toISOString() });
        if (logs.length > 500) logs.shift();
        localStorage.setItem('pdv_audit_log', JSON.stringify(logs));
    }

    // ── Formatadores ──────────────────────────────────────────────────────────
    function formatBRL(v) {
        return 'R$ ' + parseFloat(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function formatCPF(cpf) {
        const d = cpf.replace(/\D/g, '');
        return d.length === 11 ? d.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4') : cpf;
    }
    function htmlEsc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function round2(v) { return Math.round(v * 100) / 100; }

    // ── Status de conexão ──────────────────────────────────────────────────────
    function _atualizarStatusOnline() {
        const el = document.getElementById('pdv-status-online');
        if (!el) return;
        if (navigator.onLine) {
            el.textContent  = '🟢 Online';
            el.className    = 'badge bg-success';
        } else {
            el.textContent  = '🔴 Offline';
            el.className    = 'badge bg-danger';
        }
    }

    // ── Troco ──────────────────────────────────────────────────────────────────
    function calcularTroco() {
        const valor  = parseFloat(document.getElementById('pdv-pag-valor')?.value || 0);
        const totais = calcularTotais();
        const troco  = Math.max(0, valor - totais.liquido);
        const elT    = document.getElementById('pdv-pag-troco');
        if (elT) elT.textContent = formatBRL(troco);
    }

    // ── Init ──────────────────────────────────────────────────────────────────
    async function init(config = {}) {
        estado.caixa_id = config.caixa_id || null;
        estado.loja_id  = config.loja_id  || null;

        _registrarAtalhos();
        _atualizarStatusOnline();
        window.addEventListener('online',  _atualizarStatusOnline);
        window.addEventListener('offline', _atualizarStatusOnline);

        // Eventos de sync
        document.addEventListener('desffrut:sync:alerta_estoque', e => {
            mostrarAlerta('⚠️ Estoque zerado em ' + e.detail.produtos.length + ' produto(s). Avise o gerente.', 'warning');
        });
        document.addEventListener('desffrut:sync:concluido', e => {
            if (e.detail.sincronizados > 0) {
                mostrarAlerta(`✅ ${e.detail.sincronizados} venda(s) sincronizada(s).`, 'success');
            }
        });

        // Campo de busca
        const busca = document.getElementById('pdv-busca');
        if (busca) {
            busca.addEventListener('keydown', async function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    await adicionarItem(this.value);
                }
            });
            busca.focus();
        }

        // CPF do cliente
        const cpfInput = document.getElementById('pdv-cpf');
        if (cpfInput) {
            cpfInput.addEventListener('keydown', async function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    await buscarCliente(this.value);
                }
            });
        }

        // Troco em tempo real
        const pagValor = document.getElementById('pdv-pag-valor');
        if (pagValor) pagValor.addEventListener('input', calcularTroco);

        // Painel de pagamento sempre visível: começa em "Dinheiro"
        selecionarForma('dinheiro');

        // Info de cupons pendentes
        const info = await DesffrDatabase.info();
        if (info.pendentes > 0) {
            mostrarAlerta(`📶 ${info.pendentes} venda(s) offline pendentes de sincronização.`, 'info');
        }

        renderizarCarrinho();
        renderizarCliente();
    }

    // ── API pública ───────────────────────────────────────────────────────────
    return {
        init,
        adicionarItem,
        removerItem,
        alterarQuantidade,
        buscarCliente,
        removerCliente,
        abrirCadastroCliente,
        fecharCadastroCliente,
        previewFotoCadastro,
        salvarCadastroCliente,
        selecionarForma,
        finalizarVenda,
        confirmarPagamento,
        confirmarQuantidade,
        limparVenda,
        calcularTroco,
        mostrarAlerta,
        _selecionarProduto, // usado pelo HTML gerado dinamicamente
        formatBRL,
    };

})();
