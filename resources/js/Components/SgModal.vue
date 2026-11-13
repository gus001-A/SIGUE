<script setup>
/**
 * Modal estándar de SIGUE: encabezado de color (el mismo del ícono), cuerpo con campos
 * redondeados en dos columnas parejas (.sg-grid-2 / .sg-grid-4) y pie con Cancelar / Guardar
 * en el color del modal. Si el contenido no cabe, solo el cuerpo hace scroll.
 */
import { SaveOutlined, CloseOutlined } from '@ant-design/icons-vue';

defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    icon: { type: [Object, Function], default: null },
    color: { type: String, default: 'linear-gradient(135deg, #1d7fc9, #14a39a)' },
    width: { type: [String, Number], default: 'min(860px, 96vw)' },
    okText: { type: String, default: 'Guardar' },
    okIcon: { type: [Object, Function], default: () => SaveOutlined },
    okDanger: { type: Boolean, default: false },
    okDisabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    footer: { type: Boolean, default: true },
    cancelText: { type: String, default: 'Cancelar' },
});
const emit = defineEmits(['update:open', 'ok', 'cancel']);
function close() {
    emit('update:open', false);
    emit('cancel');
}
</script>

<template>
    <a-modal :open="open" :width="width" centered destroy-on-close wrap-class-name="sg-modal2 sg-modal3" :footer="null" :closable="false" @cancel="close">
        <div class="sgm" :style="{ '--mc': color }">
            <header class="sgm__head">
                <span v-if="icon" class="sgm__icon"><component :is="icon" /></span>
                <div class="sgm__title">
                    <b>{{ title }}</b>
                    <small v-if="subtitle">{{ subtitle }}</small>
                </div>
                <button type="button" class="sgm__close" aria-label="Cerrar" @click="close"><CloseOutlined /></button>
            </header>

            <div class="sgm__body"><slot /></div>

            <footer v-if="footer" class="sgm__foot">
                <div class="sgm__extra"><slot name="extra" /></div>
                <a-button size="large" class="sgm__cancel" @click="close">{{ cancelText }}</a-button>
                <a-button type="primary" size="large" class="sgm__ok" :class="{ 'is-danger': okDanger }" :danger="okDanger" :loading="loading" :disabled="okDisabled" @click="emit('ok')">
                    <template #icon><component :is="okIcon" /></template>{{ okText }}
                </a-button>
            </footer>
        </div>
    </a-modal>
</template>

<style>
/* Estilos globales: el modal se monta fuera del componente */
.sg-modal3 .ant-modal-content { padding: 0 !important; border-radius: 22px !important; overflow: hidden; box-shadow: 0 30px 60px -20px rgba(11, 47, 85, .45) !important; }
.sg-modal3 .ant-modal-body { padding: 0 !important; }
.sgm { display: flex; flex-direction: column; max-height: calc(100vh - 48px); }

.sgm__head {
    position: relative; overflow: hidden; display: flex; align-items: center; gap: 14px; padding: 18px 22px; color: #fff; flex: none;
    background: var(--mc);
}
.sgm__head::before, .sgm__head::after { content: ''; position: absolute; border-radius: 50%; background: rgba(255, 255, 255, .12); pointer-events: none; }
.sgm__head::before { width: 180px; height: 180px; right: -50px; top: -90px; }
.sgm__head::after { width: 110px; height: 110px; right: 110px; bottom: -70px; background: rgba(255, 255, 255, .08); }
.sgm__icon {
    position: relative; width: 46px; height: 46px; border-radius: 14px; display: grid; place-items: center; font-size: 21px; flex: none;
    background: rgba(255, 255, 255, .2); border: 1px solid rgba(255, 255, 255, .35); box-shadow: inset 0 1px 0 rgba(255, 255, 255, .3);
}
.sgm__title { position: relative; flex: 1; min-width: 0; display: flex; flex-direction: column; }
.sgm__title b { font-size: 18px; font-weight: 800; letter-spacing: -.01em; line-height: 1.25; }
.sgm__title small { font-size: 12.5px; opacity: .88; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sgm__close {
    position: relative; width: 34px; height: 34px; border-radius: 10px; border: 0; cursor: pointer; flex: none; display: grid; place-items: center;
    color: #fff; background: rgba(255, 255, 255, .16); transition: background .15s, transform .15s;
}
.sgm__close:hover { background: rgba(255, 255, 255, .3); transform: rotate(90deg); }

.sgm__body { padding: 20px 24px 8px; overflow-y: auto; min-height: 0; background: #fff; }
.sgm__body .ant-form-item { margin-bottom: 14px; }
.sgm__body .ant-form-item-label { padding-bottom: 4px !important; }
.sgm__body .ant-form-item-label > label { font-weight: 700 !important; color: #334155 !important; font-size: 12.5px !important; }
.sgm__body .ant-input-affix-wrapper, .sgm__body .ant-input, .sgm__body .ant-input-number,
.sgm__body .ant-picker, .sgm__body .ant-select:not(.ant-select-customize-input) .ant-select-selector, .sgm__body textarea.ant-input {
    border-radius: 11px !important; background: #f8fafc !important; border-color: #e2e8f0 !important; transition: border-color .15s, box-shadow .15s, background .15s !important;
}
.sgm__body .ant-input-affix-wrapper, .sgm__body .ant-picker, .sgm__body .ant-input-number, .sgm__body .ant-select-single:not(.ant-select-customize-input) .ant-select-selector { min-height: 40px; }
.sgm__body .ant-select-single .ant-select-selector .ant-select-selection-item, .sgm__body .ant-select-single .ant-select-selector .ant-select-selection-placeholder { line-height: 38px !important; }
.sgm__body .ant-input-number-input { height: 38px; }
.sgm__body .ant-input-affix-wrapper:hover, .sgm__body .ant-picker:hover, .sgm__body .ant-input-number:hover, .sgm__body .ant-select:hover .ant-select-selector, .sgm__body textarea.ant-input:hover {
    background: #fff !important; border-color: #cbd5e1 !important;
}
.sgm__body .ant-input-affix-wrapper-focused, .sgm__body .ant-picker-focused, .sgm__body .ant-input-number-focused, .sgm__body .ant-select-focused .ant-select-selector, .sgm__body textarea.ant-input:focus {
    background: #fff !important; border-color: #1d7fc9 !important; box-shadow: 0 0 0 3px rgba(29, 127, 201, .14) !important;
}
.sgm__body .ant-input-affix-wrapper .ant-input, .sgm__body .ant-input-number .ant-input-number-input { background: transparent !important; border: 0 !important; box-shadow: none !important; border-radius: 0 !important; }
.sgm__body .ant-input:hover:not(.ant-input-affix-wrapper .ant-input) { background: #fff !important; border-color: #cbd5e1 !important; }
.sgm__body .ant-input:focus:not(.ant-input-affix-wrapper .ant-input) { background: #fff !important; border-color: #1d7fc9 !important; box-shadow: 0 0 0 3px rgba(29, 127, 201, .14) !important; }
.sgm__body .ant-form-item-has-error .ant-input-affix-wrapper, .sgm__body .ant-form-item-has-error .ant-select-selector, .sgm__body .ant-form-item-has-error .ant-picker { border-color: #e3262b !important; background: #fff8f8 !important; }
.sgm__body .sg-sec { margin-top: 2px; }

.sgm__foot { display: flex; align-items: center; gap: 10px; padding: 14px 24px; background: #f8fafc; border-top: 1px solid #eef2f7; flex: none; }
.sgm__extra { margin-right: auto; text-align: left; min-width: 0; }
.sgm__cancel { border-radius: 12px !important; font-weight: 600; border-color: #e2e8f0 !important; color: #475569 !important; }
.sgm__cancel:hover { color: #0b2f55 !important; border-color: #cbd5e1 !important; background: #fff !important; }
.sgm__ok { border-radius: 12px !important; font-weight: 700; border: none !important; background: var(--mc) !important; box-shadow: 0 10px 20px -10px rgba(11, 47, 85, .55) !important; transition: transform .15s, filter .15s !important; }
.sgm__ok:hover:not(:disabled) { filter: brightness(1.07); transform: translateY(-1px); }
.sgm__ok.is-danger { background: linear-gradient(135deg, #e3262b, #b91c1c) !important; }
.sgm__ok:disabled { opacity: .55; color: #fff !important; }
@media (max-width: 640px) { .sgm__head { padding: 14px 16px; } .sgm__body { padding: 16px; } .sgm__foot { padding: 12px 16px; flex-wrap: wrap; } }
</style>
