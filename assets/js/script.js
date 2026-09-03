/* Sistema de Gestão de Horários — FAGRENM
   Micro-interações partilhadas. */

document.addEventListener('DOMContentLoaded', function () {

    // ============================================================
    // Sidebar (menu lateral) — abre/fecha em ecrãs estreitos.
    // ============================================================
    var sidebar = document.querySelector('#sidebar');
    var sidebarOverlay = document.querySelector('.sidebar-overlay');
    function fecharSidebar() {
        sidebar && sidebar.classList.remove('open');
        sidebarOverlay && sidebarOverlay.classList.remove('show');
    }
    var botaoAbrirSidebar = document.querySelector('[data-sidebar-open]');
    botaoAbrirSidebar && botaoAbrirSidebar.addEventListener('click', function () {
        sidebar && sidebar.classList.add('open');
        sidebarOverlay && sidebarOverlay.classList.add('show');
    });
    document.querySelectorAll('[data-sidebar-close]').forEach(function (el) {
        el.addEventListener('click', fecharSidebar);
    });

    // ============================================================
    // Toast simples (canto do ecrã) para ações só decorativas —
    // distinto do mostrarToast() de baixo, que é o que dá feedback
    // real das ações AJAX (eliminar/ativar).
    // ============================================================
    var toastSimples = document.querySelector('#toast');
    var toastSimplesTexto = document.querySelector('#toastText');
    var toastSimplesTimer;
    window.showToast = function (mensagem) {
        if (!toastSimples || !toastSimplesTexto) { return; }
        toastSimplesTexto.textContent = mensagem;
        toastSimples.classList.add('show');
        clearTimeout(toastSimplesTimer);
        toastSimplesTimer = setTimeout(function () { toastSimples.classList.remove('show'); }, 2800);
    };
    document.querySelectorAll('[data-toast]').forEach(function (el) {
        el.addEventListener('click', function (ev) {
            if (el.getAttribute('href') === '#') { ev.preventDefault(); }
            window.showToast(el.dataset.toast);
        });
    });

    // ============================================================
    // Modal de confirmação — substitui o confirm() nativo do browser
    // por uma caixa com o CSS da aplicação. Dois padrões suportados:
    //   <form data-confirmar="mensagem">              — formulário inteiro
    //   <button data-confirmar="mensagem" type="submit"> — um botão específico
    //     dentro de um formulário com vários submits (ex.: "Limpar tudo e
    //     gerar de novo", ao lado de "Gerar automaticamente").
    // Registado ANTES da barra de progresso, e com stopImmediatePropagation
    // no primeiro (interceptado) submit, para a barra só aparecer depois
    // de a ação estar mesmo confirmada — nunca antes.
    // ============================================================
    var modalFundo = document.createElement('div');
    modalFundo.className = 'modal-fundo';
    modalFundo.innerHTML =
        '<div class="modal-caixa" role="alertdialog" aria-modal="true" aria-labelledby="modal-confirmar-msg">' +
            '<p class="modal-eyebrow">Confirmar ação</p>' +
            '<p id="modal-confirmar-msg"></p>' +
            '<div class="modal-acoes">' +
                '<button type="button" class="btn btn--secundario" data-modal-cancelar>Cancelar</button>' +
                '<button type="button" class="btn" data-modal-confirmar>Confirmar</button>' +
            '</div>' +
        '</div>';
    document.body.appendChild(modalFundo);

    var modalMsg = modalFundo.querySelector('#modal-confirmar-msg');
    var modalBtnConfirmar = modalFundo.querySelector('[data-modal-confirmar]');
    var modalBtnCancelar = modalFundo.querySelector('[data-modal-cancelar]');
    var modalFocoAnterior = null;
    var modalAoConfirmar = null;

    function modalAbrir(mensagem, aoConfirmar) {
        modalMsg.textContent = mensagem;
        modalAoConfirmar = aoConfirmar;
        modalFocoAnterior = document.activeElement;
        modalFundo.classList.add('aberto');
        modalBtnCancelar.focus();
    }
    function modalFechar() {
        modalFundo.classList.remove('aberto');
        modalAoConfirmar = null;
        if (modalFocoAnterior && typeof modalFocoAnterior.focus === 'function') { modalFocoAnterior.focus(); }
    }
    modalBtnConfirmar.addEventListener('click', function () {
        var callback = modalAoConfirmar;
        modalFechar();
        if (callback) { callback(); }
    });
    modalBtnCancelar.addEventListener('click', modalFechar);
    modalFundo.addEventListener('click', function (ev) {
        if (ev.target === modalFundo) { modalFechar(); }
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && modalFundo.classList.contains('aberto')) { modalFechar(); }
    });

    // ============================================================
    // Toast flutuante — feedback de uma ação sem navegar para lado
    // nenhum (ver eliminarPorAjax abaixo). Usa as mesmas classes .msg
    // do resto da aplicação, só que fixo no canto e a desaparecer sozinho.
    // ============================================================
    function mostrarToast(tipo, mensagem) {
        var toast = document.createElement('div');
        toast.className = 'msg msg--' + tipo + ' toast-flutuante';
        toast.setAttribute('role', 'status');
        toast.textContent = mensagem;
        document.body.appendChild(toast);
        requestAnimationFrame(function () { toast.classList.add('toast-visivel'); });
        setTimeout(function () {
            toast.classList.remove('toast-visivel');
            setTimeout(function () { toast.remove(); }, 250);
        }, 3200);
    }

    // ============================================================
    // Eliminar sem recarregar a página: formulários com
    // data-ajax-remover="<seletor>" (ex.: "tr", ".bloco-grelha") enviam-se
    // por fetch(); o servidor responde com JSON (ver ehPedidoAjax() /
    // responderAjax() em includes/flash.php) em vez do Post-Redirect-Get
    // normal. Só remove o elemento da página se o servidor confirmar
    // sucesso — um erro (ex.: FK a bloquear) mantém a linha e mostra
    // porquê no toast.
    // ============================================================
    function eliminarPorAjax(form) {
        var alvo = form.closest(form.dataset.ajaxRemover);
        var botao = form.querySelector('button[type="submit"]');
        if (botao) { botao.disabled = true; }

        fetch(form.action || window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
        })
            .then(function (resp) { return resp.json(); })
            .then(function (dados) {
                if (dados.sucesso && alvo) {
                    alvo.classList.add('a-remover');
                    setTimeout(function () { alvo.remove(); }, 220);
                } else if (botao) {
                    botao.disabled = false;
                }
                mostrarToast(dados.sucesso ? 'ok' : 'erro', dados.mensagem);
            })
            .catch(function () {
                if (botao) { botao.disabled = false; }
                mostrarToast('erro', 'Não foi possível ligar ao servidor. Tenta novamente.');
            });
    }

    // ============================================================
    // Ativar/desativar sem recarregar a página: formulários com
    // data-ajax-alternar="1" (ex.: admin/utilizadores.php). Ao contrário de
    // eliminarPorAjax, aqui a linha fica — só o texto do estado e do botão
    // é que muda, a partir do novo valor de "ativo" que o servidor devolve
    // (o JS não tem como adivinhar isso sozinho).
    // ============================================================
    function alternarPorAjax(form) {
        var linha = form.closest('tr');
        var botao = form.querySelector('button[type="submit"]');
        if (botao) { botao.disabled = true; }

        fetch(form.action || window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
        })
            .then(function (resp) { return resp.json(); })
            .then(function (dados) {
                if (dados.sucesso && linha && dados.ativo !== null && dados.ativo !== undefined) {
                    var ativo = !!dados.ativo;
                    var celulaEstado = linha.querySelector('.celula-estado');
                    if (celulaEstado) {
                        celulaEstado.lastChild && (celulaEstado.lastChild.textContent = ativo ? 'Ativo' : 'Inativo');
                        celulaEstado.classList.toggle('badge-success', ativo);
                        celulaEstado.classList.toggle('badge-warning', !ativo);
                    }
                    if (botao) { botao.textContent = ativo ? 'Desativar' : 'Ativar'; }
                    form.dataset.confirmar = (ativo ? 'Desativar' : 'Ativar') + ' esta conta?';
                }
                if (botao) { botao.disabled = false; }
                mostrarToast(dados.sucesso ? 'ok' : 'erro', dados.mensagem);
            })
            .catch(function () {
                if (botao) { botao.disabled = false; }
                mostrarToast('erro', 'Não foi possível ligar ao servidor. Tenta novamente.');
            });
    }

    document.querySelectorAll('form[data-confirmar]').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            if (form.dataset.confirmado === '1') { return; } // já confirmado — deixa seguir
            ev.preventDefault();
            ev.stopImmediatePropagation(); // a barra de progresso só reage depois de confirmado
            modalAbrir(form.dataset.confirmar, function () {
                if (form.dataset.ajaxRemover) {
                    eliminarPorAjax(form);
                } else if (form.dataset.ajaxAlternar) {
                    alternarPorAjax(form);
                } else {
                    form.dataset.confirmado = '1';
                    form.requestSubmit();
                }
            });
        });
    });

    document.querySelectorAll('button[data-confirmar]').forEach(function (btn) {
        btn.addEventListener('click', function (ev) {
            var form = btn.closest('form');
            if (!form) { return; }
            ev.preventDefault();
            ev.stopImmediatePropagation();
            modalAbrir(btn.dataset.confirmar, function () {
                form.requestSubmit(btn);
            });
        });
    });

    // Barra de progresso fina no topo em qualquer submissão de formulário —
    // como todas as ações (guardar, publicar, gerar horário) são pedidos
    // clássicos ao servidor (sem AJAX), a navegação para a página seguinte
    // já interrompe a barra a meio; isso é suficiente para dar uma
    // sensação clara de "a processar", em vez de a página parecer parada.
    var barra = document.createElement('div');
    barra.id = 'barra-progresso';
    document.body.appendChild(barra);

    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            if (ev.defaultPrevented) { return; }
            document.body.classList.add('a-carregar');
            var label = form.dataset.loadingLabel;
            // setTimeout(..., 0): adia o disable para depois do browser já ter
            // serializado os dados do submit. Desativar o botão clicado
            // SINCRONAMENTE dentro do próprio evento "submit" faz alguns
            // browsers excluírem o name=value desse botão do pedido — e
            // páginas como coordenador/escolher_curso.php dependem do value
            // do botão clicado (qual curso foi escolhido) para funcionar.
            setTimeout(function () {
                form.querySelectorAll('button[type="submit"]').forEach(function (btn) {
                    btn.disabled = true;
                    if (label) { btn.textContent = label; }
                });
            }, 0);
        });
    });

    // Cartões "Em desenvolvimento" não navegam — mostram um aviso suave
    document.querySelectorAll('.cartao-modulo[href="#"]').forEach(function (cartao) {
        cartao.addEventListener('click', function (ev) {
            ev.preventDefault();
            cartao.style.transition = 'transform .12s';
            cartao.style.transform = 'translateY(0) scale(.985)';
            setTimeout(function () { cartao.style.transform = ''; }, 130);
        });
    });

    // Pesquisa/filtro de tabelas: qualquer <input data-filtro-tabela="#id">
    // filtra as linhas <tr> dessa tabela por texto, no browser, sem pedir
    // nada ao servidor. Usa-se em listas (docentes, disciplinas, turmas,
    // utilizadores…) que podem crescer bastante.
    document.querySelectorAll('[data-filtro-tabela]').forEach(function (campo) {
        var tabela = document.querySelector(campo.dataset.filtroTabela);
        if (!tabela) { return; }
        var linhas = Array.prototype.slice.call(tabela.querySelectorAll('tr')).slice(1); // salta o cabeçalho
        campo.addEventListener('input', function () {
            var termo = campo.value.trim().toLowerCase();
            linhas.forEach(function (linha) {
                linha.style.display = !termo || linha.textContent.toLowerCase().indexOf(termo) !== -1 ? '' : 'none';
            });
        });
    });

    // Editor de horário: clicar num slot vazio da grelha pré-preenche
    // o dia e o bloco horário no formulário de "Nova aula" e leva o
    // foco para lá, para não ser preciso procurar os selects manualmente.
    var selDia = document.getElementById('f-dia');
    var selBloco = document.getElementById('f-bloco');
    if (selDia && selBloco) {
        document.querySelectorAll('td.slot-vazio').forEach(function (slot) {
            function preencher() {
                selDia.value = slot.dataset.dia;
                selBloco.value = slot.dataset.bloco;
                document.getElementById('form-nova-aula').scrollIntoView({ behavior: 'smooth', block: 'center' });
                var foco = document.getElementById('f-disciplina');
                if (foco) { foco.focus(); }
                slot.classList.add('slot-selecionado');
                setTimeout(function () { slot.classList.remove('slot-selecionado'); }, 900);
            }
            slot.addEventListener('click', preencher);
            slot.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); preencher(); }
            });
        });
    }

});
