import { notification } from 'ant-design-vue';
import { confirmar } from '@/lib/confirm';

/**
 * Avisos con el estilo por defecto de Ant Design (fondo blanco, icono de color)
 * y diálogos de confirmación estilo SweetAlert.
 */
notification.config({ placement: 'topRight', top: '76px', duration: 4.5, maxCount: 4 });

const TITLES = { success: '¡Listo!', error: 'Algo salió mal', warning: 'Atención', info: 'Aviso' };

function toast(kind, text, title) {
    const fn = notification[kind] ?? notification.info;
    fn({ message: title ?? TITLES[kind] ?? TITLES.info, description: text, duration: kind === 'error' ? 7 : 4.5 });
}

/** API compatible con `message` de Ant Design. */
export const message = {
    success: (t, title) => toast('success', t, title),
    error: (t, title) => toast('error', t, title),
    warning: (t, title) => toast('warning', t, title),
    info: (t, title) => toast('info', t, title),
};

/** Muestra los mensajes flash de Inertia (page.props.flash). */
export function showFlash(flash) {
    if (!flash) return;
    if (flash.success) message.success(flash.success);
    if (flash.error) message.error(flash.error);
    if (flash.warning) message.warning(flash.warning);
    if (flash.info) message.info(flash.info);
}

/**
 * Un solo punto para los avisos: cada visita de Inertia (incluida la carga inicial)
 * muestra su flash una vez. Antes el layout los mostraba al montarse y además
 * al cambiar la prop, por lo que se duplicaban en cada navegación.
 */
let lastFlashKey = null;
export function initFlash(router) {
    // 'navigate' cubre cargas y cambios de página; 'success' cubre guardados que regresan a la misma página.
    const handle = (event) => {
        const flash = event.detail?.page?.props?.flash;
        const key = flash ? JSON.stringify([flash.success, flash.error, flash.warning, flash.info]) : null;
        const hasAny = flash && (flash.success || flash.error || flash.warning || flash.info);
        // El mismo mensaje en la misma URL dentro de 1.5 s = evento repetido (p. ej. recargas parciales).
        const now = Date.now();
        if (!hasAny) { lastFlashKey = null; return; }
        if (lastFlashKey && lastFlashKey.key === key && now - lastFlashKey.at < 1500) return;
        lastFlashKey = { key, at: now };
        showFlash(flash);
    };
    router.on('navigate', handle);
    router.on('success', handle);
}

/** Confirmación de borrado (icono rojo). */
export async function confirmDelete({ title = '¿Eliminar registro?', content = '', okText = 'Sí, eliminar', onOk }) {
    if (await confirmar({ type: 'danger', title, text: content, okText })) onOk?.();
}

/**
 * Confirmación genérica.
 * tone: 'delete' | 'warn' | 'ask' | 'logout' | 'success'
 */
export async function confirmAction({ title, content = '', okText = 'Sí, continuar', danger = false, tone, onOk }) {
    const type = { delete: 'danger', warn: 'warning', ask: 'question', logout: 'question', success: 'success' }[tone] ?? (danger ? 'warning' : 'question');
    if (await confirmar({ type, title, text: content, okText })) onOk?.();
}

export { confirmar };
