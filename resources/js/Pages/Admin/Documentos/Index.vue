<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import {
    FolderOpenOutlined, EyeOutlined, CheckOutlined, CloseOutlined, EditOutlined, ClockCircleOutlined, CheckCircleOutlined,
    CloseCircleOutlined, ExclamationCircleOutlined, UserOutlined, FileTextOutlined, CalendarOutlined, FlagOutlined, SettingOutlined,
    WarningOutlined, TagsOutlined, UploadOutlined, InboxOutlined, NumberOutlined
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHead from '@/Components/PageHead.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusTag from '@/Components/StatusTag.vue';
import SgTable from '@/Components/SgTable.vue';
import SgModal from '@/Components/SgModal.vue';
import { openFile } from '@/lib/viewer';

const props = defineProps({ documents: Array, incomplete: Array, tab: String, status: String, stats: Object });
const view = ref(props.tab === 'incompletos' ? 'incompletos' : 'revision');
const statusFilter = ref(props.status && props.status !== 'todos' ? props.status : null);
const docs = computed(() => (statusFilter.value ? props.documents.filter((d) => d.status === statusFilter.value) : props.documents));

// Revisión
const revOpen = ref(false); const revDoc = ref(null);
const rev = useForm({ status: 'validado', review_notes: '' });
function review(doc, status) {
    revDoc.value = doc; rev.status = status; rev.review_notes = ''; rev.clearErrors();
    status === 'validado' ? rev.post(route('documentos.review', doc.id), { preserveScroll: true }) : (revOpen.value = true);
}
const send = () => rev.post(route('documentos.review', revDoc.value.id), { preserveScroll: true, onSuccess: () => (revOpen.value = false) });

// Subida desde expedientes incompletos
const uploadOpen = ref(false);
const uploadStudent = ref(null);
const up = useForm({ document_type_id: null, file: null });
function openUpload(student) {
    uploadStudent.value = student;
    up.reset();
    up.clearErrors();
    uploadOpen.value = true;
}
const submitUpload = () => {
    up.post(route('alumnos.documentos.store', uploadStudent.value.id), {
        preserveScroll: true,
        onSuccess: () => { uploadOpen.value = false; }
    });
};
const handleFileChange = (info) => {
    up.file = info.file;
};

const STATUSES = [{ value: 'pendiente', label: 'En revisión' }, { value: 'validado', label: 'Validado' }, { value: 'correccion', label: 'Corrección' }, { value: 'rechazado', label: 'Rechazado' }];

// Columnas
const columns = [
    { title: 'Matrícula', key: 'enrollment_number', icon: NumberOutlined, width: 130 },
    { title: 'Alumno', key: 'student', icon: UserOutlined, filterValue: (r) => `${r.student} ${r.enrollment_number ?? ''}`, width: 320 },
    { title: 'Documento', key: 'doc', icon: FileTextOutlined, filterValue: (r) => `${r.title} ${r.original_name ?? ''}` },
    { title: 'Tipo', key: 'type', icon: TagsOutlined, filter: 'select', filterValue: (r) => r.type || 'Otro', width: 220 },
    { title: 'Cargado', key: 'created_at', dataIndex: 'created_at', icon: CalendarOutlined, width: 130 },
    { title: 'Estado', key: 'status', dataIndex: 'status', icon: FlagOutlined, filter: 'select', filterOptions: STATUSES, width: 160 },
    { title: 'Acciones', key: 'actions', icon: SettingOutlined, width: 180, align: 'center' },
];

const incColumns = [
    { title: 'Matrícula', key: 'enrollment_number', icon: NumberOutlined, width: 130 },
    { title: 'Alumno', key: 'name', icon: UserOutlined, filterValue: (r) => `${r.name} ${r.enrollment_number}`, width: 320 },
    // Columna centrada para los documentos faltantes
    { title: 'Documentos faltantes', key: 'missing', icon: WarningOutlined, filterValue: (r) => r.missing.join(' '), align: 'center' },
    { title: 'Acciones', key: 'actions', icon: SettingOutlined, width: 100, align: 'center' },
];

const pick = (s) => { view.value = 'revision'; statusFilter.value = statusFilter.value === s ? null : s; };

// Mapeo de iconos y colores para el estado
const statusMap = {
    pendiente: { icon: ClockCircleOutlined, color: '#f39200', bg: '#fff8eb', label: 'En revisión' },
    validado: { icon: CheckCircleOutlined, color: '#2e9b43', bg: '#ecfdf3', label: 'Validado' },
    correccion: { icon: ExclamationCircleOutlined, color: '#7c4ddb', bg: '#f3effa', label: 'Corrección' },
    rechazado: { icon: CloseCircleOutlined, color: '#e3262b', bg: '#fef2f2', label: 'Rechazado' }
};
</script>

<template>
    <AppLayout title="Documentos">
        <PageHead title="Gestión documental" subtitle="Validación de documentos y expedientes incompletos del plantel" :icon="FolderOpenOutlined" />

        <div class="sg-stats">
            <StatCard label="En revisión" :value="stats.pendiente" :icon="ClockCircleOutlined" color="amber" :active="statusFilter === 'pendiente'" style="cursor: pointer" @click="pick('pendiente')" />
            <StatCard label="Validados" :value="stats.validado" :icon="CheckCircleOutlined" color="green" :active="statusFilter === 'validado'" style="cursor: pointer" @click="pick('validado')" />
            <StatCard label="Corrección" :value="stats.correccion" :icon="ExclamationCircleOutlined" color="purple" :active="statusFilter === 'correccion'" style="cursor: pointer" @click="pick('correccion')" />
            <StatCard label="Rechazados" :value="stats.rechazado" :icon="CloseCircleOutlined" color="red" :active="statusFilter === 'rechazado'" style="cursor: pointer" @click="pick('rechazado')" />
            <StatCard label="Expedientes incompletos" :value="incomplete.length" :icon="WarningOutlined" color="pink" :active="view === 'incompletos'" style="cursor: pointer" @click="view = 'incompletos'" />
        </div>

        <div class="sg-toolbar2">
            <span class="sg-toolbar2__label"><FolderOpenOutlined /> Vista</span>
            <a-segmented v-model:value="view" :options="[{ value: 'revision', label: 'Documentos cargados' }, { value: 'incompletos', label: 'Expedientes incompletos' }]" />
        </div>

        <!-- VISTA: DOCUMENTOS CARGADOS -->
        <a-card v-if="view === 'revision'" :bordered="false" class="sg-fill">
            <SgTable :columns="columns" :data-source="docs" unit="documentos">
                <template #bodyCell="{ column, record }">
                    
                    <template v-if="column.key === 'enrollment_number'">
                        <span class="matricula">{{ record.enrollment_number || '—' }}</span>
                    </template>

                    <template v-else-if="column.key === 'student'">
                        <Link :href="route('alumnos.show', record.student_id)" class="sg-who student-cell">
                            <span class="sg-av"><UserOutlined /></span>
                            <span class="student-text"><b>{{ record.student }}</b></span>
                        </Link>
                    </template>
                    
                    <template v-else-if="column.key === 'doc'">
                        <div class="doc-cell">
                            <b>{{ record.title }}</b>
                            <div class="sg-cell-sub doc-name">{{ record.original_name }}</div>
                        </div>
                    </template>

                    <template v-else-if="column.key === 'type'">
                        <span v-if="record.type" class="sg-chip type-chip"><TagsOutlined /> {{ record.type }}</span>
                        <span v-else class="sg-chip type-chip-empty"><TagsOutlined /> Sin tipo</span>
                    </template>

                    <template v-else-if="column.key === 'status'">
                        <div class="status-pill" :style="{ background: statusMap[record.status]?.bg, color: statusMap[record.status]?.color }">
                            <component :is="statusMap[record.status]?.icon" class="status-icon" />
                            <span class="status-text">{{ statusMap[record.status]?.label }}</span>
                        </div>
                        <div v-if="record.review_notes" class="sg-cell-sub status-note">{{ record.review_notes }}</div>
                    </template>

                    <template v-else-if="column.key === 'actions'">
                        <div class="sg-actions">
                            <a-tooltip title="Ver"><a-button class="sg-ab blue" @click="openFile({ url: record.url, name: record.original_name, title: record.title, subtitle: `${record.student} · ${record.type || 'Otro'}` })"><EyeOutlined /></a-button></a-tooltip>
                            <a-tooltip title="Validar"><a-button class="sg-ab green" @click="review(record, 'validado')"><CheckOutlined /></a-button></a-tooltip>
                            <a-tooltip title="Solicitar corrección"><a-button class="sg-ab orange" @click="review(record, 'correccion')"><EditOutlined /></a-button></a-tooltip>
                            <a-tooltip title="Rechazar"><a-button class="sg-ab red" @click="review(record, 'rechazado')"><CloseOutlined /></a-button></a-tooltip>
                        </div>
                    </template>
                </template>
            </SgTable>
        </a-card>

        <!-- VISTA: EXPEDIENTES INCOMPLETOS -->
        <a-card v-else :bordered="false" class="sg-fill">
            <SgTable :columns="incColumns" :data-source="incomplete" unit="expedientes">
                <template #bodyCell="{ column, record }">
                    
                    <template v-if="column.key === 'enrollment_number'">
                        <span class="matricula">{{ record.enrollment_number || '—' }}</span>
                    </template>

                    <template v-else-if="column.key === 'name'">
                        <Link :href="route('alumnos.show', record.id)" class="sg-who student-cell">
                            <span class="sg-av"><UserOutlined /></span>
                            <span class="student-text"><b>{{ record.name }}</b></span>
                        </Link>
                    </template>

                    <!-- Chips de faltantes CENTRADOS -->
                    <template v-else-if="column.key === 'missing'">
                        <div class="missing-list">
                            <template v-if="record.missing.length === record.total_expected">
                                <span class="miss-chip is-red">
                                    <CloseCircleOutlined /> Faltan todos los documentos
                                </span>
                            </template>
                            <template v-else>
                                <span v-for="m in record.missing" :key="m" class="miss-chip is-orange">
                                    <WarningOutlined /> {{ m }}
                                </span>
                            </template>
                        </div>
                    </template>

                    <template v-else-if="column.key === 'actions'">
                        <a-tooltip title="Subir documento faltante">
                            <a-button class="sg-ab green" @click="openUpload(record)"><UploadOutlined /></a-button>
                        </a-tooltip>
                    </template>
                </template>
            </SgTable>
        </a-card>

        <!-- MODAL DE REVISIÓN -->
        <SgModal v-model:open="revOpen" :title="rev.status === 'rechazado' ? 'Rechazar documento' : 'Solicitar corrección'" :subtitle="`${revDoc?.title} · ${revDoc?.student}`"
            :icon="rev.status === 'rechazado' ? CloseOutlined : EditOutlined" :color="rev.status === 'rechazado' ? 'linear-gradient(135deg, #e3262b, #b71c21)' : 'linear-gradient(135deg, #f39200, #d07c00)'"
            width="min(620px, 96vw)" :loading="rev.processing" @ok="send">
            <a-form layout="vertical">
                <a-form-item label="Motivo (se notifica al alumno y tutores)" required :validate-status="rev.errors.review_notes ? 'error' : undefined" :help="rev.errors.review_notes">
                    <a-textarea v-model:value="rev.review_notes" :rows="4" />
                </a-form-item>
            </a-form>
        </SgModal>

        <!-- MODAL PARA SUBIR DOCUMENTO FALTANTE -->
        <SgModal v-model:open="uploadOpen" title="Subir documento" :subtitle="`Alumno: ${uploadStudent?.name}`" :icon="UploadOutlined" color="linear-gradient(135deg, #2e9b43, #14a39a)"
            width="min(520px, 96vw)" ok-text="Subir documento" :ok-icon="UploadOutlined" :loading="up.processing" @ok="submitUpload">
            <a-form layout="vertical">
                <a-form-item label="Selecciona el documento a subir" required :validate-status="up.errors.document_type_id ? 'error' : undefined" :help="up.errors.document_type_id">
                    <a-select v-model:value="up.document_type_id" placeholder="Selecciona el tipo de documento">
                        <a-select-option v-for="m in uploadStudent?.missing" :key="m" :value="m">{{ m }}</a-select-option>
                    </a-select>
                </a-form-item>
                
                <a-form-item label="Archivo" required :validate-status="up.errors.file ? 'error' : undefined" :help="up.errors.file">
                    <a-upload-dragger 
                        name="file" 
                        :multiple="false" 
                        :before-upload="() => false"
                        @change="handleFileChange"
                        :max-count="1"
                    >
                        <p class="ant-upload-drag-icon"><InboxOutlined /></p>
                        <p class="ant-upload-text">Haz clic o arrastra el archivo aquí</p>
                        <p class="ant-upload-hint">Soporta PDF o imágenes</p>
                    </a-upload-dragger>
                </a-form-item>
            </a-form>
        </SgModal>
    </AppLayout>
</template>

<style scoped>
/* Estilo para la matrícula */
.matricula {
    font-family: 'Courier New', Courier, monospace;
    font-weight: 700;
    color: #334155;
    background: #f1f5f9;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 13px;
}

/* Estilos para la columna de Tipo */
.type-chip {
    background: color-mix(in srgb, #7c4ddb 12%, #fff);
    color: #7c4ddb;
    border: 1px solid color-mix(in srgb, #7c4ddb 25%, #fff);
    padding: 4px 10px;
    border-radius: 8px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.type-chip-empty {
    background: #f1f5f9;
    color: #64748b;
    border: 1px solid #e2e8f0;
    padding: 4px 10px;
    border-radius: 8px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

/* Estilo de Estado */
.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    border: 1px solid color-mix(in srgb, currentColor 20%, transparent);
}
.status-icon {
    font-size: 14px;
}
.status-note {
    margin-top: 6px;
    color: #e3262b;
    font-size: 12px;
    font-style: italic;
}

/* Chips de documentos faltantes (Centrados) */
.missing-list {
    display: flex;
    flex-wrap: wrap;
    justify-content: center; /* Centra horizontalmente */
    width: 100%;             /* Toma todo el ancho de la celda para centrarse */
    gap: 6px;
    margin: 0 auto;
}

.miss-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 12px;
    cursor: default;
}
.miss-chip.is-orange {
    background: #fff8eb;
    color: #f39200;
    border: 1px solid #fbd38d;
}
.miss-chip.is-red {
    background: #fef2f2;
    color: #e3262b;
    border: 1px solid #fecaca;
}

/* Limitar el ancho y truncar texto largo */
.student-cell {
    width: 100%;
    overflow: hidden;
    display: flex;
    align-items: center;
    gap: 8px;
}
.student-text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: block;
}
.student-text b {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.doc-cell {
    width: 100%;
    overflow: hidden;
}
.doc-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>