<script setup>
/**
 * Selector de archivo con el estilo SIGUE y previsualización.
 * Uso: <FileInput v-model="form.file" accept=".pdf" hint="PDF, máx. 10 MB" :current-url="doc.url" current-name="acta.pdf" />
 * - Imagen seleccionada: miniatura.
 * - PDF u otro: ícono con botón "Ver" que abre el visor.
 * - currentUrl: archivo ya guardado (p. ej. la foto actual) para mostrarlo mientras no se elija otro.
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { CloudUploadOutlined, EyeOutlined, DeleteOutlined, FilePdfOutlined, FileOutlined, SwapOutlined } from '@ant-design/icons-vue';
import { openFile, fileKind } from '@/lib/viewer';

const props = defineProps({
    modelValue: { type: [File, null], default: null },
    accept: { type: String, default: '' },
    hint: { type: String, default: 'Haz clic para seleccionar un archivo' },
    currentUrl: { type: String, default: null },
    currentName: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);
const input = ref(null);
const blobUrl = ref(null);

watch(() => props.modelValue, (f) => {
    if (blobUrl.value) URL.revokeObjectURL(blobUrl.value);
    blobUrl.value = f ? URL.createObjectURL(f) : null;
    if (!f && input.value) input.value.value = '';
}, { immediate: true });
onBeforeUnmount(() => blobUrl.value && URL.revokeObjectURL(blobUrl.value));

const file = computed(() => (props.modelValue
    ? { url: blobUrl.value, name: props.modelValue.name, kind: fileKind(props.modelValue.name, '', props.modelValue.type), size: props.modelValue.size, isNew: true }
    : props.currentUrl ? { url: props.currentUrl, name: props.currentName || 'Archivo actual', kind: fileKind(props.currentName, props.currentUrl, props.accept.startsWith('image') ? 'image/' : ''), isNew: false } : null));

const sizeLabel = computed(() => {
    const s = file.value?.size;
    if (!s) return '';
    return s > 1048576 ? `${(s / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(s / 1024))} KB`;
});

function pick(e) { emit('update:modelValue', e.target.files?.[0] ?? null); }
const preview = () => file.value && openFile({ url: file.value.url, name: file.value.name, title: file.value.isNew ? 'Archivo seleccionado' : 'Archivo actual' });
</script>

<template>
    <div class="fi">
        <!-- Sin archivo: zona para seleccionar -->
        <label v-if="!file" class="file-drop">
            <span class="file-drop__icon"><CloudUploadOutlined /></span>
            <span class="file-drop__title">Seleccionar archivo</span>
            <span class="file-drop__hint">{{ hint }}</span>
            <input ref="input" type="file" :accept="accept" @change="pick" />
        </label>

        <!-- Con archivo: previsualización -->
        <div v-else class="fi__card" :class="{ 'is-new': file.isNew }">
            <button type="button" class="fi__thumb" :class="`is-${file.kind}`" title="Ver archivo" @click="preview">
                <img v-if="file.kind === 'image'" :src="file.url" alt="" />
                <FilePdfOutlined v-else-if="file.kind === 'pdf'" />
                <FileOutlined v-else />
                <span class="fi__eye"><EyeOutlined /></span>
            </button>
            <div class="fi__info">
                <b :title="file.name">{{ file.name }}</b>
                <small>{{ file.isNew ? 'Listo para subir' : 'Archivo actual' }}<template v-if="sizeLabel"> · {{ sizeLabel }}</template></small>
                <div class="fi__acts">
                    <button type="button" class="fi__btn" style="--c: #1d7fc9" @click="preview"><EyeOutlined /> Ver</button>
                    <label class="fi__btn fi__change" style="--c: #14a39a">
                        <SwapOutlined /> Cambiar
                        <input ref="input" type="file" :accept="accept" @change="pick" />
                    </label>
                    <button v-if="file.isNew" type="button" class="fi__btn" style="--c: #e3262b" @click="emit('update:modelValue', null)"><DeleteOutlined /> Quitar</button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.fi__card { display: flex; align-items: center; gap: 12px; padding: 8px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fafbfc; }
.fi__card.is-new { border-color: #bbf7d0; background: #f3fbf5; }
.fi__thumb {
    position: relative; width: 64px; height: 64px; flex: none; border-radius: 10px; overflow: hidden; border: 1px solid #e2e8f0; cursor: pointer;
    display: grid; place-items: center; font-size: 26px; color: #fff; padding: 0;
}
.fi__thumb.is-image { background: #fff; }
.fi__thumb.is-pdf { background: linear-gradient(135deg, #e3262b, #f39200); }
.fi__thumb.is-other { background: linear-gradient(135deg, #64748b, #0b2f55); }
.fi__thumb img { width: 100%; height: 100%; object-fit: cover; }
.fi__eye { position: absolute; inset: 0; display: grid; place-items: center; font-size: 18px; color: #fff; background: rgba(11, 47, 85, .45); opacity: 0; transition: opacity .15s; }
.fi__thumb:hover .fi__eye { opacity: 1; }
.fi__info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.fi__info b { font-size: 13px; color: #0b2f55; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fi__info small { font-size: 11.5px; color: #64748b; }
.fi__acts { display: flex; gap: 6px; margin-top: 4px; flex-wrap: wrap; }
.fi__btn {
    display: inline-flex; align-items: center; gap: 5px; height: 26px; padding: 0 10px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer;
    font-family: inherit; color: var(--c); background: color-mix(in srgb, var(--c) 10%, #fff); border: 1px solid color-mix(in srgb, var(--c) 25%, #fff); transition: background .15s;
}
.fi__btn:hover { background: color-mix(in srgb, var(--c) 18%, #fff); }
.fi__change { position: relative; overflow: hidden; }
.fi__change input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
</style>
