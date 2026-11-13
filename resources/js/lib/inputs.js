/**
 * Directivas para campos de captura:
 *  v-digits="10"  → solo números (teléfono, CP…), con máximo de dígitos y teclado numérico en el celular.
 *  v-upper        → convierte a MAYÚSCULAS y quita espacios (CURP, RFC).
 * Funcionan sobre <input> o sobre componentes que lo contienen (a-input con prefijo, etc.).
 */
const inputOf = (el) => (el.tagName === 'INPUT' ? el : el.querySelector('input'));

function bind(el, clean) {
    const input = inputOf(el);
    if (!input || input.__sgClean) return;
    input.__sgClean = true;
    input.addEventListener('input', () => {
        const v = clean(input.value);
        if (v !== input.value) {
            input.value = v;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });
}

export const digits = {
    mounted(el, binding) {
        const max = Number(binding.value) || null;
        const input = inputOf(el);
        if (!input) return;
        input.setAttribute('inputmode', 'numeric');
        input.setAttribute('autocomplete', 'tel');
        bind(el, (v) => { const d = v.replace(/\D+/g, ''); return max ? d.slice(0, max) : d; });
    },
};

export const upper = {
    mounted(el) {
        const input = inputOf(el);
        if (!input) return;
        input.style.textTransform = 'uppercase';
        bind(el, (v) => v.toUpperCase().replace(/\s+/g, ''));
    },
};
