<script setup>
/** Botón "Ver evidencia" de una incidencia: abre el archivo (imagen o PDF) en el visor. */
import { PaperClipOutlined } from '@ant-design/icons-vue';
import { openFile } from '@/lib/viewer';

defineProps({ url: { type: String, default: null }, name: { type: String, default: '' }, title: { type: String, default: 'Evidencia de la incidencia' } });
</script>

<template>
    <button v-if="url" type="button" class="evb" @click.stop="openFile({ url, name, title, subtitle: 'Evidencia adjunta' })">
        <PaperClipOutlined /> Ver evidencia
    </button>
</template>

<style scoped>
.evb {
    display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 8px; white-space: nowrap; cursor: pointer; font-family: inherit;
    font-size: 12px; font-weight: 700; color: #1d7fc9; background: #eff6ff; border: 1px solid #bfdbfe; transition: background .15s;
}
.evb:hover { background: #dbeafe; }
</style>
