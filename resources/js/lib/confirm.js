import { reactive } from 'vue';

/**
 * Diálogo de confirmación global estilo SweetAlert (icono animado, título,
 * texto y botones grandes). Lo dibuja <ConfirmDialog /> en los layouts.
 */
export const dialog = reactive({
    open: false,
    type: 'warning', // warning | danger | success | question | info
    title: '',
    text: '',
    okText: 'Sí, continuar',
    cancelText: 'Cancelar',
    resolve: null,
});

export function confirmar({ type = 'warning', title, text = '', okText = 'Sí, continuar', cancelText = 'Cancelar' }) {
    dialog.resolve?.(false);
    return new Promise((resolve) => {
        Object.assign(dialog, { type, title, text, okText, cancelText, open: true, resolve });
    });
}

export function responder(value) {
    dialog.open = false;
    dialog.resolve?.(value);
    dialog.resolve = null;
}
