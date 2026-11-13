<script setup>
/** Lista compacta de archivos adjuntos [{ name, url }]; cada uno se abre en el visor. */
import { FilePdfOutlined, FileImageOutlined, FileOutlined, FileWordOutlined, FileExcelOutlined } from '@ant-design/icons-vue';
import { openFile } from '@/lib/viewer';

defineProps({ files: { type: Array, default: () => [] }, title: { type: String, default: '' }, subtitle: { type: String, default: 'Archivo adjunto' } });

const ICON = { pdf: FilePdfOutlined, img: FileImageOutlined, doc: FileWordOutlined, xls: FileExcelOutlined };
const kindOf = (name = '') => {
    const ext = name.split('.').pop().toLowerCase();
    if (ext === 'pdf') return 'pdf';
    if (['png', 'jpg', 'jpeg', 'gif', 'webp'].includes(ext)) return 'img';
    if (['doc', 'docx', 'odt', 'rtf'].includes(ext)) return 'doc';
    if (['xls', 'xlsx', 'csv', 'ods'].includes(ext)) return 'xls';
    return 'file';
};
</script>

<template>
    <span v-if="files.length" class="fch">
        <button v-for="f in files" :key="f.url" type="button" class="fch__f" :class="kindOf(f.name)" :title="f.name"
            @click.stop="openFile({ url: f.url, name: f.name, title: title || f.name, subtitle })">
            <component :is="ICON[kindOf(f.name)] ?? FileOutlined" /><span>{{ f.name }}</span>
        </button>
    </span>
</template>

<style scoped>
.fch { display: inline-flex; flex-wrap: wrap; gap: 4px; vertical-align: middle; }
.fch__f { display: inline-flex; align-items: center; gap: 5px; max-width: 220px; padding: 2px 9px; border-radius: 8px; border: 1px solid #e1e8f0; background: #fff;
    font-family: inherit; font-size: 12px; font-weight: 600; color: #334155; cursor: pointer; transition: border-color .15s; }
.fch__f span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fch__f:hover { border-color: #1d7fc9; color: #1d7fc9; }
.fch__f.pdf .anticon { color: #d9363e; }
.fch__f.img .anticon { color: #7c4ddb; }
.fch__f.doc .anticon { color: #0461d8; }
.fch__f.xls .anticon { color: #1d8a4e; }
</style>
