<script setup>
/**
 * Adjuntar varios archivos (máximo `max`, por defecto 5).
 *   <MultiFileInput v-model="form.files" v-model:remove="form.remove_files" :current="actividad.files" :max="5" :max-mb="15" />
 * - modelValue: archivos nuevos (File[]).
 * - current: archivos ya guardados [{ index, name, url }]; al quitarlos se agregan a `remove` (índices).
 */
import { computed } from 'vue';
import { CloudUploadOutlined, DeleteOutlined, EyeOutlined, FilePdfOutlined, FileImageOutlined, FileOutlined, FileWordOutlined, FileExcelOutlined, PaperClipOutlined } from '@ant-design/icons-vue';
import { openFile } from '@/lib/viewer';
import { message } from '@/lib/notify';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    remove: { type: Array, default: () => [] },
    current: { type: Array, default: () => [] },
    max: { type: Number, default: 5 },
    maxMb: { type: Number, default: 15 },
    accept: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue', 'update:remove']);

const kept = computed(() => props.current.filter((f) => !props.remove.includes(f.index)));
const total = computed(() => kept.value.length + props.modelValue.length);
const left = computed(() => Math.max(0, props.max - total.value));

const ICON = { pdf: FilePdfOutlined, img: FileImageOutlined, doc: FileWordOutlined, xls: FileExcelOutlined };
const kindOf = (name = '') => {
    const ext = name.split('.').pop().toLowerCase();
    if (ext === 'pdf') return 'pdf';
    if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'heic'].includes(ext)) return 'img';
    if (['doc', 'docx', 'odt', 'rtf'].includes(ext)) return 'doc';
    if (['xls', 'xlsx', 'csv', 'ods'].includes(ext)) return 'xls';
    return 'file';
};
const sizeLabel = (s) => (!s ? '' : s > 1048576 ? `${(s / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(s / 1024))} KB`);

function pick(e) {
    const chosen = [...(e.target.files ?? [])];
    e.target.value = '';
    const tooBig = chosen.filter((f) => f.size > props.maxMb * 1048576);
    if (tooBig.length) message.warning(`${tooBig.map((f) => f.name).join(', ')}: cada archivo puede pesar máximo ${props.maxMb} MB.`);
    const ok = chosen.filter((f) => f.size <= props.maxMb * 1048576);
    if (ok.length > left.value) message.warning(`Solo puedes adjuntar ${props.max} archivos en total; se agregaron los primeros ${left.value}.`);
    emit('update:modelValue', [...props.modelValue, ...ok.slice(0, left.value)]);
}
const dropNew = (i) => emit('update:modelValue', props.modelValue.filter((_, k) => k !== i));
const dropCurrent = (f) => emit('update:remove', [...props.remove, f.index]);
const viewNew = (f) => openFile({ url: URL.createObjectURL(f), name: f.name, title: f.name, subtitle: 'Archivo seleccionado' });
const viewCurrent = (f) => openFile({ url: f.url, name: f.name, title: f.name, subtitle: 'Archivo adjunto' });
</script>

<template>
    <div class="mfi">
        <div v-if="kept.length || modelValue.length" class="mfi__list">
            <div v-for="f in kept" :key="'c' + f.index" class="mfi__item">
                <span class="mfi__ic" :class="kindOf(f.name)"><component :is="ICON[kindOf(f.name)] ?? FileOutlined" /></span>
                <span class="mfi__txt"><b :title="f.name">{{ f.name }}</b><small>Guardado<template v-if="f.size"> · {{ sizeLabel(f.size) }}</template></small></span>
                <a-tooltip title="Ver"><button type="button" class="mfi__btn" @click="viewCurrent(f)"><EyeOutlined /></button></a-tooltip>
                <a-tooltip v-if="!disabled" title="Quitar"><button type="button" class="mfi__btn red" @click="dropCurrent(f)"><DeleteOutlined /></button></a-tooltip>
            </div>
            <div v-for="(f, i) in modelValue" :key="'n' + i" class="mfi__item is-new">
                <span class="mfi__ic" :class="kindOf(f.name)"><component :is="ICON[kindOf(f.name)] ?? FileOutlined" /></span>
                <span class="mfi__txt"><b :title="f.name">{{ f.name }}</b><small>Nuevo · {{ sizeLabel(f.size) }}</small></span>
                <a-tooltip title="Ver"><button type="button" class="mfi__btn" @click="viewNew(f)"><EyeOutlined /></button></a-tooltip>
                <a-tooltip title="Quitar"><button type="button" class="mfi__btn red" @click="dropNew(i)"><DeleteOutlined /></button></a-tooltip>
            </div>
        </div>

        <label v-if="left > 0 && !disabled" class="mfi__drop">
            <CloudUploadOutlined />
            <span><b>{{ total ? 'Agregar más archivos' : 'Seleccionar archivos' }}</b><small>Hasta {{ max }} archivos · máx. {{ maxMb }} MB c/u · quedan {{ left }}</small></span>
            <input type="file" multiple :accept="accept" @change="pick" />
        </label>
        <div v-else-if="!disabled" class="mfi__full"><PaperClipOutlined /> Llegaste al máximo de {{ max }} archivos. Quita uno para agregar otro.</div>
    </div>
</template>

<style scoped>
.mfi { display: flex; flex-direction: column; gap: 8px; }
.mfi__list { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 6px; }
.mfi__item { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border: 1px solid #e1e8f0; border-radius: 10px; background: #fff; min-width: 0; }
.mfi__item.is-new { border-color: #b9e3c6; background: #f6fcf8; }
.mfi__ic { width: 30px; height: 30px; border-radius: 8px; flex: none; display: grid; place-items: center; font-size: 15px; color: #5d7085; background: #f1f5f9; }
.mfi__ic.pdf { color: #d9363e; background: #fdecec; }
.mfi__ic.img { color: #7c4ddb; background: #f1ecfc; }
.mfi__ic.doc { color: #0461d8; background: #e6f0fd; }
.mfi__ic.xls { color: #1d8a4e; background: #e3f8ec; }
.mfi__txt { flex: 1; min-width: 0; display: flex; flex-direction: column; line-height: 1.25; }
.mfi__txt b { font-size: 12.5px; color: #0a1f44; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mfi__txt small { font-size: 11px; color: #94a3b8; }
.mfi__btn { width: 26px; height: 26px; border-radius: 7px; border: 0; cursor: pointer; display: grid; place-items: center; color: #0461d8; background: #eef5ff; flex: none; }
.mfi__btn.red { color: #d9363e; background: #fdecec; }
.mfi__drop { position: relative; display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1.5px dashed #b9d4ee; border-radius: 12px; background: #f7fafd; cursor: pointer; color: #1d7fc9; font-size: 18px; transition: border-color .15s, background .15s; }
.mfi__drop:hover { border-color: #1d7fc9; background: #eef5fd; }
.mfi__drop span { display: flex; flex-direction: column; }
.mfi__drop b { font-size: 13px; color: #0a1f44; }
.mfi__drop small { font-size: 11.5px; color: #5d7085; }
.mfi__drop input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.mfi__full { font-size: 12px; color: #b45309; background: #fff7e6; border: 1px solid #fde3b0; border-radius: 10px; padding: 7px 10px; display: flex; gap: 6px; align-items: center; }
</style>
