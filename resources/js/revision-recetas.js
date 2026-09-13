export function inicializarRevision() {
    const modulo = document.querySelector('[data-revision]');
    if (!modulo) return;
    const aprobacion = modulo.querySelector('[data-decision="aprobar"]');
    const dialogo = modulo.querySelector('[data-confirmacion]');
    const estado = modulo.querySelector('[data-estado-envio]');
    let enviando = false;
    let confirmada = false;
    const botones = [...modulo.querySelectorAll('button[type="submit"]')];
    const reiniciar = () => {
        enviando = false;
        confirmada = false;
        botones.forEach(boton => { boton.disabled = boton.dataset.bloqueado === 'true'; });
        if (estado) estado.textContent = '';
    };
    reiniciar();
    window.addEventListener('pageshow', reiniciar);
    modulo.querySelector('[data-cancelar]')?.addEventListener('click', () => dialogo.close());
    modulo.querySelector('[data-confirmar]')?.addEventListener('click', () => {
        if (enviando) return;
        confirmada = true;
        dialogo.close();
        aprobacion.requestSubmit();
    });
    modulo.querySelectorAll('form[data-decision]').forEach(formulario => {
        const motivo = formulario.querySelector('textarea');
        motivo?.addEventListener('input', () => motivo.setCustomValidity(''));
        formulario.addEventListener('submit', evento => {
            if (enviando) { evento.preventDefault(); return; }
            if (motivo && !motivo.value.replace(/[\s\p{Z}\p{Cf}]/gu, '')) {
                evento.preventDefault();
                motivo.setCustomValidity('Escribe un motivo de rechazo con contenido real.');
                motivo.reportValidity();
                return;
            }
            if (formulario === aprobacion && !confirmada) {
                evento.preventDefault();
                dialogo.showModal();
                return;
            }
            enviando = true;
            botones.forEach(boton => { boton.disabled = true; });
            estado.textContent = 'Guardando la decisión…';
        });
    });
}
