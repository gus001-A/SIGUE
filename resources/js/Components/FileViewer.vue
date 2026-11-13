<script setup>
/** Modal global para previsualizar imágenes y PDF sin salir del sistema. Se abre con openFile() de '@/lib/viewer'. */
import { computed, ref, watch } from 'vue';
import {
    EyeOutlined, DownloadOutlined, ExportOutlined, FileImageOutlined, FilePdfOutlined, FileUnknownOutlined,
    ZoomInOutlined, ZoomOutOutlined, LoadingOutlined,
} from '@ant-design/icons-vue';
import { viewer, closeFile, fileKind } from '@/lib/viewer';

const kind = computed(() => fileKind(viewer.name, viewer.url));
const zoom = ref(1);
const loading = ref(true);
watch(() => [viewer.open, viewer.url], () => { zoom.value = 1; loading.value = true; });

const ICON = { image: FileImageOutlined, pdf: FilePdfOutlined, other: FileUnknownOutlined };
const COLOR = {
    image: 'linear-gradient(135deg, #1d7fc9, #14a39a)',
    pdf: 'linear-gradient(135deg, #e3262b, #f39200)',
    other: 'linear-gradient(135deg, #64748b, #0b2f55)',
};
</script>

<template>
    <a-modal :open="viewer.open" width="min(1040px, 96vw)" centered destroy-on-close wrap-class-name="sg-modal2 sg-viewer" :footer="null" @cancel="closeFile">
        <template #title>
            <div class="sg-mtitle">
                <span class="sg-mtitle__icon" :style="{ background: COLOR[kind] }"><component :is="ICON[kind]" /></span>
                <div>{{ viewer.title }}<small v-if="viewer.subtitle || viewer.name">{{ viewer.subtitle || viewer.name }}</small></div>
            </div>
        </template>

        <div class="fv">
            <div class="fv__bar">
                <span class="fv__tag"><EyeOutlined /> Vista previa</span>
                <template v-if="kind === 'image'">
                    <a-button size="small" :disabled="zoom <= 0.5" @click="zoom = Math.max(0.5, zoom - 0.25)"><ZoomOutOutlined /></a-button>
                    <span class="fv__zoom">{{ Math.round(zoom * 100) }}%</span>
                    <a-button size="small" :disabled="zoom >= 3" @click="zoom = Math.min(3, zoom + 0.25)"><ZoomInOutlined /></a-button>
                </template>
                <span class="fv__sp" />
                <a :href="viewer.url" target="_blank" rel="noopener"><a-button><template #icon><ExportOutlined /></template>Abrir en pestaña</a-button></a>
                <a :href="viewer.download" :download="viewer.name || true"><a-button type="primary"><template #icon><DownloadOutlined /></template>Descargar</a-button></a>
            </div>

            <div class="fv__stage" :class="`is-${kind}`">
                <LoadingOutlined v-if="loading && kind !== 'other'" class="fv__spin" />
                <div v-if="kind === 'image'" class="fv__imgwrap">
                    <img :src="viewer.url" :alt="viewer.title" :style="{ transform: `scale(${zoom})` }" @load="loading = false" @error="loading = false" />
                </div>
                <iframe v-else-if="kind === 'pdf'" :src="viewer.url" :title="viewer.title" @load="loading = false" />
                <div v-else class="fv__other">
                    <FileUnknownOutlined />
                    <b>Este tipo de archivo no se puede previsualizar</b>
                    <span>Descárgalo para abrirlo con la aplicación correspondiente.</span>
                </div>
            </div>
        </div>
    </a-modal>
</template>

<style scoped>
.fv { display: flex; flex-direction: column; gap: 10px; }
.fv__bar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.fv__tag { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; color: #1d7fc9; background: #eff6ff; }
.fv__zoom { font-size: 12px; font-weight: 700; color: #475569; min-width: 40px; text-align: center; }
.fv__sp { flex: 1; }
.fv__bar a :deep(.ant-btn) { border-radius: 10px; font-weight: 600; }
.fv__stage { position: relative; height: min(70vh, 640px); border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; background: #f1f5f9; }
.fv__stage.is-image { background: repeating-conic-gradient(#f1f5f9 0% 25%, #fff 0% 50%) 50% / 22px 22px; }
.fv__spin { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 28px; color: #1d7fc9; }
.fv__imgwrap { width: 100%; height: 100%; overflow: auto; display: grid; place-items: center; }
.fv__imgwrap img { max-width: 100%; max-height: 100%; object-fit: contain; transition: transform .2s; transform-origin: center; border-radius: 6px; box-shadow: 0 10px 30px -12px rgba(15, 23, 42, .35); }
.fv__stage iframe { width: 100%; height: 100%; border: 0; background: #fff; }
.fv__other { height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; color: #64748b; text-align: center; padding: 20px; }
.fv__other .anticon { font-size: 44px; color: #94a3b8; }
.fv__other b { color: #0b2f55; }
</style>
