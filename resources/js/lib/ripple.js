/**
 * Efecto "ripple" de Material (como en Flutter) para la app del alumno.
 * Uso en <script setup>:  import { ripple as vRipple } from '@/lib/ripple';  → <button v-ripple>
 */
export const ripple = {
    mounted(el) {
        el.classList.add('m-ripple');
        el.addEventListener('pointerdown', (e) => {
            if (el.disabled) return;
            const r = el.getBoundingClientRect();
            const d = Math.max(r.width, r.height) * 2.2;
            const w = document.createElement('span');
            w.className = 'm-ripple__wave';
            w.style.width = w.style.height = `${d}px`;
            w.style.left = `${e.clientX - r.left - d / 2}px`;
            w.style.top = `${e.clientY - r.top - d / 2}px`;
            el.appendChild(w);
            setTimeout(() => w.remove(), 600);
        });
    },
};

/** Paleta de las clases (como los temas de Google Classroom). */
export const CLASS_THEMES = ['#1967d2', '#188038', '#e37400', '#7c4ddb', '#c5221f', '#129eaf', '#d01884', '#3c4043'];
export const classColor = (classes, id) => CLASS_THEMES[Math.max(0, (classes ?? []).findIndex((c) => c.id === id)) % CLASS_THEMES.length];

/** "dd/mm/aaaa HH:mm" → Date (o null). */
export function parseDue(s) {
    const m = /^(\d{2})\/(\d{2})\/(\d{4})(?: (\d{2}):(\d{2}))?/.exec(s ?? '');
    return m ? new Date(+m[3], +m[2] - 1, +m[1], +(m[4] ?? 23), +(m[5] ?? 59)) : null;
}

/** "Hoy, 23:59" · "Mañana, 23:59" · "vie 15 nov" · "Venció el 30 oct". */
export function dueLabel(s) {
    const d = parseDue(s);
    if (!d) return 'Sin fecha de entrega';
    const now = new Date();
    const day = (x) => new Date(x.getFullYear(), x.getMonth(), x.getDate()).getTime();
    const diff = Math.round((day(d) - day(now)) / 86400000);
    const hm = d.toTimeString().slice(0, 5);
    const short = new Intl.DateTimeFormat('es-MX', { weekday: 'short', day: 'numeric', month: 'short' }).format(d);
    if (d < now) return `Venció: ${short}`;
    if (diff === 0) return `Hoy, ${hm}`;
    if (diff === 1) return `Mañana, ${hm}`;
    return `${short.charAt(0).toUpperCase()}${short.slice(1)}, ${hm}`;
}

/** Iniciales de un nombre. */
export const initials = (n = '') => n.replace(/,/g, ' ').split(/\s+/).filter(Boolean).slice(0, 2).map((x) => x[0]).join('').toUpperCase();
