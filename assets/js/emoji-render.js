// Sustituye los emojis por imágenes locales (Twemoji, assets/emoji/) en vez de
// depender de que el sistema operativo tenga una fuente de emoji instalada:
// en Linux suele faltar y los emojis se ven como un cuadrado vacío ("tofu").
// Se re-aplica automáticamente a cualquier contenido añadido o cambiado
// después de la carga (modales, avisos), sin necesidad de llamarlo a mano.
(function () {
    if (typeof twemoji === 'undefined') return;

    var opciones = {
        className: 'emoji',
        callback: function (icon) {
            return 'assets/emoji/' + icon + '.svg';
        }
    };

    function render(nodo) {
        twemoji.parse(nodo, opciones);
    }

    render(document.body);

    var observer = new MutationObserver(function (mutaciones) {
        mutaciones.forEach(function (m) {
            m.addedNodes.forEach(function (n) {
                if (n.nodeType === 1) render(n);
                else if (n.nodeType === 3 && n.parentNode) render(n.parentNode);
            });
            if (m.type === 'characterData' && m.target.parentNode) {
                render(m.target.parentNode);
            }
        });
    });
    observer.observe(document.body, { childList: true, subtree: true, characterData: true });
})();
