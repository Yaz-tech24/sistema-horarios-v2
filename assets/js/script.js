/* Sistema de Gestão de Horários — FAGRENM
   Micro-interações partilhadas. */

document.addEventListener('DOMContentLoaded', function () {

    // Cartões "Em desenvolvimento" não navegam — mostram um aviso suave
    document.querySelectorAll('.cartao-modulo[href="#"]').forEach(function (cartao) {
        cartao.addEventListener('click', function (ev) {
            ev.preventDefault();
            cartao.style.transition = 'transform .12s';
            cartao.style.transform = 'translateY(0) scale(.985)';
            setTimeout(function () { cartao.style.transform = ''; }, 130);
        });
    });

});
