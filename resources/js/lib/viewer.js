import { reactive } from 'vue';

/**
 * Visor global de archivos (imágenes y PDF) en un modal. Lo dibuja <FileViewer />
 * en el layout. Uso: openFile({ url, name: 'acta.pdf', title: 'Acta de nacimiento' })
 */
export const viewer = reactive({ open: false, url: '', name: '', title: '', subtitle: '', download: '' });

const IMG = /\.(png|jpe?g|gif|webp|bmp|svg)(\?|$)/i;
const PDF = /\.pdf(\?|$)/i;

/** Tipo del archivo a partir del nombre, la URL o el MIME de un File local. */
export function fileKind(name = '', url = '', mime = '') {
    if (mime.startsWith('image/') || IMG.test(name) || IMG.test(url)) return 'image';
    if (mime === 'application/pdf' || PDF.test(name) || PDF.test(url)) return 'pdf';
    return 'other';
}

export function openFile({ url, name = '', title = '', subtitle = '', download = null }) {
    if (!url) return;
    Object.assign(viewer, {
        open: true, url, name, title: title || name || 'Archivo', subtitle,
        // Los archivos del servidor aceptan ?download=1; los locales (blob:) se descargan directo.
        download: download ?? (url.startsWith('blob:') ? url : url + (url.includes('?') ? '&' : '?') + 'download=1'),
    });
}

export function closeFile() {
    viewer.open = false;
}
