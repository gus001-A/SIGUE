<script setup>
/**
 * Invitación a instalar SIGUE como app (franja inferior en el celular) y modal con los pasos.
 * También se abre desde el menú del usuario o el login:  <InstalarApp ref="installer" />  →  installer.value.open()
 *   - banner: muestra la franja en pantallas chicas (por defecto sí).
 */
import { computed, ref } from 'vue';
import { MobileOutlined, CloseOutlined, DownloadOutlined, ExportOutlined, PlusSquareOutlined, CheckCircleOutlined, DesktopOutlined } from '@ant-design/icons-vue';
import { pwa, installApp } from '@/lib/pwa';
import { message } from '@/lib/notify';

defineProps({ banner: { type: Boolean, default: true } });

const KEY = 'sigue_instalar_oculto';
const readHidden = () => { try { return Number(localStorage.getItem(KEY) || 0); } catch (e) { return 0; } };
// Si la cierra, no se vuelve a mostrar en 14 días.
const hidden = ref(Date.now() - readHidden() < 14 * 86400000);
const strip = computed(() => pwa.available && !hidden.value);

const modal = ref(false);
const open = () => { modal.value = true; };
defineExpose({ open });

function closeStrip() {
    hidden.value = true;
    try { localStorage.setItem(KEY, String(Date.now())); } catch (e) { /* sin almacenamiento */ }
}
async function installNow() {
    if (pwa.canInstall) {
        if (await installApp()) {
            message.success('Encontrarás SIGUE en tu pantalla de inicio.', '¡App instalada!');
            modal.value = false;
        }
    } else {
        modal.value = true;
    }
}
</script>

<template>
    <!-- Franja en el celular -->
    <transition name="ia">
        <div v-if="banner && strip" class="ia-strip">
            <img src="/images/pwa/icon-192.png" alt="" class="ia-ic" />
            <div class="ia-txt">
                <b>Instala la app de SIGUE</b>
                <small>Acceso directo desde tu pantalla de inicio</small>
            </div>
            <a-button type="primary" size="small" @click="installNow"><DownloadOutlined /> Instalar</a-button>
            <a-button type="text" size="small" class="ia-x" aria-label="Cerrar" @click="closeStrip"><CloseOutlined /></a-button>
        </div>
    </transition>

    <a-modal v-model:open="modal" width="460px" centered :footer="null">
        <template #title>
            <div class="ia-title"><img src="/images/pwa/icon-192.png" alt="" class="ia-logo" /><div>Instalar SIGUE<small>Como una app, con su propio ícono</small></div></div>
        </template>

        <a-alert v-if="pwa.installed" type="success" show-icon message="Ya estás usando la app instalada." />

        <template v-else-if="pwa.canInstall">
            <p class="ia-muted">Se agregará a tu pantalla de inicio (o al escritorio en computadora) y abrirá en su propia ventana, sin la barra del navegador.</p>
            <a-button type="primary" size="large" block @click="installNow"><DownloadOutlined /> Instalar ahora</a-button>
        </template>

        <template v-else-if="pwa.isIOS">
            <p class="ia-muted">En iPhone y iPad se instala desde <b>Safari</b>:</p>
            <ol class="ia-steps">
                <li><span class="ia-num">1</span> <span>Toca el botón <b>Compartir</b> <ExportOutlined class="ia-sym" /> en la barra de Safari.</span></li>
                <li><span class="ia-num">2</span> <span>Elige <b>Agregar a inicio</b> <PlusSquareOutlined class="ia-sym" />.</span></li>
                <li><span class="ia-num">3</span> <span>Toca <b>Agregar</b>. El ícono de SIGUE aparecerá en tu pantalla de inicio.</span></li>
            </ol>
        </template>

        <template v-else>
            <p class="ia-muted">Abre SIGUE en <b>Chrome</b> o <b>Edge</b> (computadora o Android), o en <b>Safari</b> (iPhone):</p>
            <ol class="ia-steps">
                <li><span class="ia-num">1</span> <span>Entra con tu usuario como siempre.</span></li>
                <li><span class="ia-num">2</span> <span>Abre el menú del navegador (⋮ en Chrome, <DesktopOutlined class="ia-sym" /> ícono de instalar en la barra de direcciones, o Compartir en Safari).</span></li>
                <li><span class="ia-num">3</span> <span>Elige <b>Instalar SIGUE</b> o <b>Agregar a inicio</b>.</span></li>
            </ol>
            <a-alert type="info" show-icon message="Para instalarla, el sistema debe abrirse con una dirección segura (https)." />
        </template>
    </a-modal>
</template>

<style>
.ia-strip {
    position: fixed; left: 10px; right: 10px; z-index: 1000; bottom: calc(12px + env(safe-area-inset-bottom));
    display: none; align-items: center; gap: 10px; padding: 10px 8px 10px 10px;
    background: #fff; border: 1px solid #c6dbf3; border-radius: 16px; box-shadow: 0 14px 34px rgba(10, 31, 68, .22);
}
@media (max-width: 900px) { .ia-strip { display: flex; } }
.ia-ic { width: 40px; height: 40px; border-radius: 11px; flex: none; box-shadow: 0 2px 8px rgba(10, 31, 68, .15); }
.ia-txt { flex: 1; min-width: 0; }
.ia-txt b { display: block; color: #0a1f44; font-size: 14px; }
.ia-txt small { display: block; color: #5d7085; font-size: 12px; }
.ia-x { color: #5d7085 !important; }
.ia-enter-active, .ia-leave-active { transition: all .3s ease; }
.ia-enter-from, .ia-leave-to { opacity: 0; transform: translateY(20px); }
.ia-title { display: flex; align-items: center; gap: 12px; }
.ia-title small { display: block; color: #5d7085; font-size: 12.5px; font-weight: 400; }
.ia-logo { width: 42px; height: 42px; border-radius: 11px; box-shadow: 0 2px 8px rgba(10, 31, 68, .15); flex: none; }
.ia-muted { color: #5d7085; }
.ia-steps { list-style: none; padding: 0; margin: 0 0 14px; display: flex; flex-direction: column; gap: 10px; }
.ia-steps li { display: flex; align-items: center; gap: 10px; background: #f6f9fd; border: 1px solid #e1e8f0; border-radius: 12px; padding: 10px 12px; color: #0a1f44; }
.ia-num { width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center; background: #1d7fc9; color: #fff; font-weight: 700; font-size: 13px; flex: none; }
.ia-sym { color: #1d7fc9; }
</style>
