import { reactive } from 'vue';

/**
 * Estado de la app web instalable (PWA), igual que en RIC Condominios.
 *  - Android / Chrome / Edge (también en computadora): el navegador avisa que se puede instalar
 *    (evento capturado en app.blade.php antes de cargar Vue) y aquí se lanza con instalar().
 *  - iPhone / iPad: Safari no tiene ese aviso; se muestran los pasos (Compartir → Agregar a inicio).
 */
const ua = navigator.userAgent || '';
const isAndroid = /Android/i.test(ua);
const isIOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
const MODES = ['standalone', 'minimal-ui', 'fullscreen'];
const inApp = () => MODES.some((m) => window.matchMedia?.(`(display-mode: ${m})`).matches) || window.navigator.standalone === true;

export const pwa = reactive({
    installed: inApp(),
    canInstall: !!window.__installEvent,
    isIOS,
    isAndroid,
    /** Se ofrece instalar cuando aún no está instalada y el navegador lo permite (o es iPhone/Android). */
    get available() { return !this.installed && (this.canInstall || this.isIOS || this.isAndroid); },
});

MODES.forEach((m) => window.matchMedia?.(`(display-mode: ${m})`).addEventListener?.('change', () => { pwa.installed = inApp(); }));
window.addEventListener('sigue:instalable', () => { pwa.canInstall = true; });
window.addEventListener('appinstalled', () => {
    pwa.installed = true;
    pwa.canInstall = false;
    window.__installEvent = null;
});

/** Lanza el aviso de instalación del navegador. Regresa true si la persona aceptó. */
export async function installApp() {
    const ev = window.__installEvent;
    if (!ev) return false;
    ev.prompt();
    const { outcome } = await ev.userChoice;
    window.__installEvent = null;
    pwa.canInstall = false;
    return outcome === 'accepted';
}
