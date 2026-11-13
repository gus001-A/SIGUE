<script setup>
/** Diálogo básico de Material 3 (como AlertDialog de Flutter). */
import { ripple as vRipple } from '@/lib/ripple';

defineProps({
    open: Boolean, title: String, text: String,
    okText: { type: String, default: 'Aceptar' }, cancelText: { type: String, default: 'Cancelar' }, loading: Boolean,
});
const emit = defineEmits(['update:open', 'ok']);
</script>

<template>
    <Teleport to="body">
        <Transition name="md">
            <div v-if="open" class="md" @click.self="emit('update:open', false)">
                <div class="md__box" role="dialog" aria-modal="true">
                    <h3>{{ title }}</h3>
                    <p v-if="text">{{ text }}</p>
                    <slot />
                    <div class="md__ft">
                        <button v-ripple type="button" class="md__b" @click="emit('update:open', false)">{{ cancelText }}</button>
                        <button v-ripple type="button" class="md__b" :disabled="loading" @click="emit('ok')">{{ loading ? 'Un momento…' : okText }}</button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.md { position: fixed; inset: 0; z-index: 2000; background: rgba(0, 0, 0, .32); display: grid; place-items: center; padding: 24px; font-family: Roboto, system-ui, sans-serif; }
.md__box { width: min(340px, 100%); background: #eef2f9; border-radius: 28px; padding: 24px 24px 16px; box-shadow: 0 8px 24px rgba(0, 0, 0, .2); }
.md__box h3 { margin: 0 0 14px; font-size: 22px; font-weight: 400; color: #1f1f1f; line-height: 1.3; }
.md__box p { margin: 0 0 8px; font-size: 14px; color: #444746; line-height: 1.5; }
.md__ft { display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px; }
.md__b { border: 0; background: none; color: #0b57d0; font-family: inherit; font-size: 14px; font-weight: 500; padding: 10px 14px; border-radius: 20px; cursor: pointer; }
.md__b:disabled { opacity: .5; }
.md-enter-active, .md-leave-active { transition: opacity .18s; }
.md-enter-active .md__box { animation: md-in .22s cubic-bezier(.2, 0, 0, 1); }
.md-enter-from, .md-leave-to { opacity: 0; }
@keyframes md-in { from { transform: scale(.9); opacity: 0; } to { transform: none; opacity: 1; } }
</style>
