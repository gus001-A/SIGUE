<script setup>
/** Diálogo de confirmación con icono animado (estilo SweetAlert). */
import { computed } from 'vue';
import { dialog, responder } from '@/lib/confirm';

const COLOR = { warning: '#f39200', danger: '#e3262b', success: '#2e9b43', question: '#1d7fc9', info: '#14a39a' };
const color = computed(() => COLOR[dialog.type] ?? COLOR.warning);
</script>

<template>
    <a-modal :open="dialog.open" :footer="null" :closable="false" centered width="min(480px, 94vw)" wrap-class-name="sg-swal-wrap" @cancel="responder(false)">
        <div class="sg-swal" :style="{ '--c': color }">
            <div class="sg-swal__icon" :class="dialog.type">
                <svg viewBox="0 0 80 80" aria-hidden="true">
                    <circle class="ring" cx="40" cy="40" r="36" />
                    <path v-if="dialog.type === 'success'" class="mark" d="M24 41 L35 52 L57 29" />
                    <template v-else-if="dialog.type === 'danger'">
                        <path class="mark" d="M28 28 L52 52" />
                        <path class="mark m2" d="M52 28 L28 52" />
                    </template>
                    <template v-else-if="dialog.type === 'question'">
                        <path class="mark" d="M31 31 C31 22, 49 22, 49 31 C49 38, 40 38, 40 45" />
                        <circle class="dot" cx="40" cy="55" r="2.6" />
                    </template>
                    <template v-else-if="dialog.type === 'info'">
                        <circle class="dot" cx="40" cy="25" r="2.8" />
                        <path class="mark" d="M40 35 L40 56" />
                    </template>
                    <template v-else>
                        <path class="mark" d="M40 22 L40 46" />
                        <circle class="dot" cx="40" cy="56" r="2.8" />
                    </template>
                </svg>
            </div>
            <h2 class="sg-swal__title">{{ dialog.title }}</h2>
            <p v-if="dialog.text" class="sg-swal__text">{{ dialog.text }}</p>
            <div class="sg-swal__btns">
                <button v-if="dialog.cancelText" type="button" class="sg-swal__btn cancel" @click="responder(false)">{{ dialog.cancelText }}</button>
                <button type="button" class="sg-swal__btn ok" autofocus @click="responder(true)">{{ dialog.okText }}</button>
            </div>
        </div>
    </a-modal>
</template>

<style>
.sg-swal-wrap .ant-modal-content { border-radius: 22px !important; padding: 34px 28px 26px !important; }
.sg-swal-wrap .ant-modal { animation: sg-swal-pop .3s cubic-bezier(.2, 1.4, .4, 1) both; }
.sg-swal { text-align: center; }
.sg-swal__icon { width: 92px; height: 92px; margin: 0 auto 14px; }
.sg-swal__icon svg { width: 100%; height: 100%; fill: none; stroke: var(--c); stroke-width: 4; stroke-linecap: round; stroke-linejoin: round; }
.sg-swal__icon .ring { stroke-opacity: .3; stroke-dasharray: 227; stroke-dashoffset: 227; animation: sg-swal-draw .5s ease-out forwards; }
.sg-swal__icon .mark { stroke-dasharray: 60; stroke-dashoffset: 60; animation: sg-swal-draw .35s .35s ease-out forwards; }
.sg-swal__icon .m2 { animation-delay: .5s; }
.sg-swal__icon .dot { fill: var(--c); stroke: none; opacity: 0; animation: sg-swal-fade .2s .6s forwards; }
.sg-swal__icon.warning svg, .sg-swal__icon.question svg { animation: sg-swal-pulse .6s .7s ease-in-out; }
.sg-swal__title { font-size: 23px; font-weight: 800; color: #1e293b; margin: 0 0 8px; }
.sg-swal__text { color: #5d6b7c; font-size: 15px; line-height: 1.5; margin: 0 auto 4px; max-width: 380px; }
.sg-swal__btns { display: flex; justify-content: center; gap: 12px; margin-top: 24px; }
.sg-swal__btn { border: 0; border-radius: 12px; padding: 11px 26px; font: 600 15px Inter, sans-serif; cursor: pointer; transition: transform .15s, filter .15s; }
.sg-swal__btn:hover { transform: translateY(-2px); }
.sg-swal__btn:active { transform: scale(.95); }
.sg-swal__btn.ok { background: var(--c); color: #fff; box-shadow: 0 8px 20px color-mix(in srgb, var(--c) 35%, transparent); }
.sg-swal__btn.ok:hover { filter: brightness(1.07); }
.sg-swal__btn.cancel { background: #eef1f5; color: #44566c; }
.sg-swal__btn.cancel:hover { background: #e3e8ee; }
@keyframes sg-swal-draw { to { stroke-dashoffset: 0; } }
@keyframes sg-swal-fade { to { opacity: 1; } }
@keyframes sg-swal-pop { from { transform: scale(.7); opacity: 0; } to { transform: none; opacity: 1; } }
@keyframes sg-swal-pulse { 50% { transform: scale(1.08); } }
</style>
