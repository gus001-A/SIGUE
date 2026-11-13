<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { UploadOutlined, EyeOutlined, DownloadOutlined, CheckOutlined, CloseOutlined, EditOutlined, DeleteOutlined, FileTextOutlined, FilePdfOutlined, FileImageOutlined, FolderOpenOutlined, ExclamationCircleOutlined } from '@ant-design/icons-vue';
import StatusTag from '@/Components/StatusTag.vue';
import FileInput from '@/Components/FileInput.vue';
import SgModal from '@/Components/SgModal.vue';
import { confirmDelete } from '@/lib/notify';
import { openFile, fileKind } from '@/lib/viewer';

/** Expediente documental: carga, consulta y (opcionalmente) validación. */
const props = defineProps({
    documents: { type: Array, default: () => [] },
    documentTypes: { type: Array, default: () => [] },
    missing: { type: Array, default: () => [] },
    uploadUrl: { type: String, required: true },
    canReview: { type: Boolean, default: false },
    canUpload: { type: Boolean, default: true },
});

const upOpen = ref(false);
const up = useForm({ document_type_id: null, title: '', file: null, validate: false });
function upload() {
    up.post(props.uploadUrl, { forceFormData: true, preserveScroll: true, onSuccess: () => { upOpen.value = false; up.reset(); } });
}

const revOpen = ref(false);
const revDoc = ref(null);
const rev = useForm({ status: 'validado', review_notes: '' });
function review(doc, status) {
    revDoc.value = doc;
    rev.status = status;
    rev.review_notes = '';
    rev.clearErrors();
    if (status === 'validado') {
        rev.post(route('documentos.review', doc.id), { preserveScroll: true });
    } else {
        revOpen.value = true;
    }
}
function sendReview() {
    rev.post(route('documentos.review', revDoc.value.id), { preserveScroll: true, onSuccess: () => (revOpen.value = false) });
}
const view = (d) => openFile({ url: d.url, name: d.original_name, title: d.title, subtitle: `${d.type || 'Otro'} · ${d.original_name ?? ''}` });
const isPdf = (d) => fileKind(d.original_name, d.url) === 'pdf';
function remove(doc) {
    confirmDelete({ title: '¿Eliminar documento?', content: doc.title, onOk: () => router.delete(route('documentos.destroy', doc.id), { preserveScroll: true }) });
}
</script>

<template>
    <div>
        <a-alert v-if="missing.length" type="warning" show-icon style="margin-bottom: 14px" message="Expediente incompleto"
            :description="'Falta: ' + missing.join(', ')" />

        <div class="sg-toolbar" style="justify-content: space-between">
            <span class="sg-card-title" style="--c: #f39200"><FolderOpenOutlined /> {{ documents.length }} documento(s)</span>
            <a-button v-if="canUpload" type="primary" @click="upOpen = true"><template #icon><UploadOutlined /></template>Cargar documento</a-button>
        </div>

        <a-empty v-if="!documents.length" description="Sin documentos cargados" />
        <div v-for="d in documents" :key="d.id" class="doc">
            <span class="doc__icon" :class="(d.original_name || '').toLowerCase().endsWith('.pdf') ? 'is-pdf' : 'is-img'">
                <FilePdfOutlined v-if="(d.original_name || '').toLowerCase().endsWith('.pdf')" /><FileImageOutlined v-else />
            </span>
            <div class="doc__body">
                <div class="sg-strong">{{ d.title }} <StatusTag :status="d.status" /></div>
                <div class="sg-cell-sub">{{ d.type || 'Otro' }} · {{ d.original_name }} · cargado {{ d.created_at }}</div>
                <div v-if="d.review_notes" class="doc__notes">{{ d.review_notes }}</div>
            </div>
            <div class="sg-actions">
                <a-tooltip title="Ver"><a-button class="sg-ab blue" @click="view(d)"><EyeOutlined /></a-button></a-tooltip>
                <a-tooltip title="Descargar"><a-button class="sg-ab teal" :href="d.url + '?download=1'"><DownloadOutlined /></a-button></a-tooltip>
                <template v-if="canReview">
                    <a-tooltip title="Validar"><a-button class="sg-ab green" @click="review(d, 'validado')"><CheckOutlined /></a-button></a-tooltip>
                    <a-tooltip title="Solicitar corrección"><a-button class="sg-ab orange" @click="review(d, 'correccion')"><EditOutlined /></a-button></a-tooltip>
                    <a-tooltip title="Rechazar"><a-button class="sg-ab red" @click="review(d, 'rechazado')"><CloseOutlined /></a-button></a-tooltip>
                </template>
                <a-tooltip v-if="canReview || d.status !== 'validado'" title="Eliminar"><a-button class="sg-ab red" @click="remove(d)"><DeleteOutlined /></a-button></a-tooltip>
            </div>
        </div>

        <SgModal v-model:open="upOpen" title="Cargar documento" subtitle="Se agrega al expediente del alumno" :icon="UploadOutlined" color="linear-gradient(135deg, #f39200, #e3262b)"
            width="min(760px, 96vw)" ok-text="Cargar" :ok-icon="UploadOutlined" :loading="up.processing" @ok="upload">
            <a-form layout="vertical" class="sg-grid-2">
                <a-form-item label="Tipo de documento" :validate-status="up.errors.document_type_id ? 'error' : undefined" :help="up.errors.document_type_id">
                    <a-select v-model:value="up.document_type_id" :options="documentTypes" allow-clear placeholder="Otro" />
                </a-form-item>
                <a-form-item label="Título (opcional)"><a-input v-model:value="up.title"><template #prefix><FileTextOutlined style="color: #1d7fc9" /></template></a-input></a-form-item>
                <a-form-item label="Archivo" required class="full" :validate-status="up.errors.file ? 'error' : undefined" :help="up.errors.file">
                    <FileInput v-model="up.file" accept=".pdf,.jpg,.jpeg,.png" hint="PDF, JPG o PNG · máx. 10 MB" />
                </a-form-item>
            </a-form>
            <template #extra><a-checkbox v-if="canReview" v-model:checked="up.validate"><CheckOutlined style="color: #2e9b43" /> Marcar como validado</a-checkbox></template>
        </SgModal>

        <SgModal v-model:open="revOpen" :title="rev.status === 'rechazado' ? 'Rechazar documento' : 'Solicitar corrección'" :subtitle="revDoc?.title"
            :icon="rev.status === 'rechazado' ? CloseOutlined : EditOutlined" :color="rev.status === 'rechazado' ? 'linear-gradient(135deg, #e3262b, #b71c21)' : 'linear-gradient(135deg, #f39200, #d07c00)'"
            width="min(620px, 96vw)" ok-text="Guardar" :loading="rev.processing" @ok="sendReview">
            <a-form layout="vertical">
                <a-form-item label="Motivo (se notifica al alumno y tutores)" required :validate-status="rev.errors.review_notes ? 'error' : undefined" :help="rev.errors.review_notes">
                    <a-textarea v-model:value="rev.review_notes" :rows="4" />
                </a-form-item>
            </a-form>
        </SgModal>
    </div>
</template>

<style scoped>
.doc { display: flex; align-items: center; gap: 12px; padding: 12px; border: 1px solid var(--sg-line); border-radius: 14px; margin-bottom: 8px; flex-wrap: wrap; }
.doc__icon { width: 40px; height: 40px; border-radius: 10px; display: grid; place-items: center; color: #fff; font-size: 18px; flex: none; }
.doc__icon.is-pdf { background: linear-gradient(135deg, #e3262b, #f39200); }
.doc__icon.is-img { background: linear-gradient(135deg, #1d7fc9, #14a39a); }
.doc__body { flex: 1; min-width: 200px; }
.doc__notes { font-size: 12px; color: #b45309; background: #fffbeb; border-radius: 8px; padding: 4px 8px; margin-top: 4px; }
</style>
