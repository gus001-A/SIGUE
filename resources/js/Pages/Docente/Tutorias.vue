<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { 
    IdcardOutlined, PlusOutlined, AlertOutlined, UserOutlined, CalendarOutlined, 
    FileTextOutlined, FlagOutlined, SettingOutlined, EditOutlined, CheckCircleOutlined, 
    ClockCircleOutlined, CheckCircleFilled, CloseCircleFilled, WarningOutlined, MessageOutlined
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHead from '@/Components/PageHead.vue';
import SgModal from '@/Components/SgModal.vue';
import SgTable from '@/Components/SgTable.vue';
import StatCard from '@/Components/StatCard.vue';
import dayjs from 'dayjs';

const props = defineProps({ sessions: Array, students: Array, hasTeacher: Boolean });

const open = ref(false);
const editing = ref(null);
const blank = () => ({ student_id: null, date: dayjs().format('YYYY-MM-DD'), topic: '', observations: '', agreements: '', results: '', follow_up_on: null, status: 'abierta' });
const form = useForm(blank());
const err = (k) => ({ 'validate-status': form.errors[k] ? 'error' : undefined, help: form.errors[k] });
function create() { editing.value = null; Object.assign(form, blank()); form.clearErrors(); open.value = true; }
function edit(t) { editing.value = t; Object.assign(form, { ...blank(), ...t }); form.clearErrors(); open.value = true; }
function submit() {
    const o = { preserveScroll: true, onSuccess: () => (open.value = false) };
    editing.value ? form.put(route('docente.tutorias.update', editing.value.id), o) : form.post(route('docente.tutorias.store'), o);
}

// Mapeo de estado
const STATUS_MAP = {
    abierta: { icon: ClockCircleOutlined, color: '#f39200', bg: '#fff8eb', label: 'Abierta' },
    cerrada: { icon: CheckCircleFilled, color: '#2e9b43', bg: '#ecfdf3', label: 'Cerrada' }
};

// Columnas separadas
const columns = [
    { title: 'Fecha', key: 'date', dataIndex: 'date_label', icon: CalendarOutlined, width: 120 },
    { title: 'Alumno', key: 'student', dataIndex: 'student', icon: UserOutlined, width: 280 }, // Más ancho para nombre completo
    { title: 'Tema', key: 'topic', dataIndex: 'topic', icon: FileTextOutlined, filterValue: (r) => r.topic },
    { title: 'Compromisos', key: 'agreements', dataIndex: 'agreements', icon: MessageOutlined, filterValue: (r) => r.agreements },
    { title: 'Seguimiento', key: 'follow', icon: ClockCircleOutlined, width: 170, filterValue: (r) => r.follow_label ?? '' },
    { title: 'Estado', key: 'status', dataIndex: 'status', icon: FlagOutlined, width: 130, filter: 'select', filterOptions: [{ value: 'abierta', label: 'Abierta' }, { value: 'cerrada', label: 'Cerrada' }] },
    { title: 'Acciones', key: 'actions', icon: SettingOutlined, width: 100, align: 'center' },
];
</script>

<template>
    <AppLayout title="Tutorías">
        <PageHead title="Tutorías" subtitle="Sesiones, compromisos, resultados y alertas de seguimiento con tus alumnos" :icon="IdcardOutlined">
            <template #actions>
                <a-button type="primary" size="large" :disabled="!hasTeacher" @click="create"><template #icon><PlusOutlined /></template>Nueva sesión</a-button>
            </template>
        </PageHead>

        <!-- KPIs -->
        <div class="sg-stats">
            <StatCard label="Sesiones totales" :value="sessions.length" :icon="IdcardOutlined" color="purple" />
            <StatCard label="Abiertas" :value="sessions.filter((t) => t.status === 'abierta').length" :icon="ClockCircleOutlined" color="amber" />
            <StatCard label="Seguimiento vencido" :value="sessions.filter((t) => t.alert).length" :icon="AlertOutlined" color="red" />
            <StatCard label="Cerradas" :value="sessions.filter((t) => t.status === 'cerrada').length" :icon="CheckCircleOutlined" color="green" />
        </div>

        <!-- TABLA -->
        <a-card :bordered="false" class="sg-fill">
            <SgTable :columns="columns" :data-source="sessions" unit="sesiones">
                <template #bodyCell="{ column, record }">
                    
                    <!-- Fecha -->
                    <template v-if="column.key === 'date'">
                        {{ record.date_label }}
                    </template>

                    <!-- Alumno (Nombre completo) -->
                    <template v-else-if="column.key === 'student'">
                        <div class="sg-who student-cell">
                            <span class="sg-av" style="background: linear-gradient(135deg, #7c4ddb, #1d7fc9)"><UserOutlined /></span>
                            <span class="student-text"><b>{{ record.student }}</b></span>
                        </div>
                    </template>

                    <!-- Tema -->
                    <template v-else-if="column.key === 'topic'">
                        <b class="topic-title">{{ record.topic }}</b>
                    </template>

                    <!-- Compromisos -->
                    <template v-else-if="column.key === 'agreements'">
                        <span v-if="record.agreements" class="agreement-text">{{ record.agreements }}</span>
                        <span v-else class="sg-faint">—</span>
                    </template>

                    <!-- Seguimiento -->
                    <template v-else-if="column.key === 'follow'">
                        <span v-if="record.follow_label" class="follow-pill" :class="{ 'is-alert': record.alert }">
                            <AlertOutlined v-if="record.alert" /><CalendarOutlined v-else /> {{ record.follow_label }}
                        </span>
                        <span v-else class="sg-faint">—</span>
                    </template>

                    <!-- Estado -->
                    <template v-else-if="column.key === 'status'">
                        <span class="sg-badge" :style="{ '--c': STATUS_MAP[record.status]?.color }">{{ STATUS_MAP[record.status]?.label }}</span>
                    </template>

                    <!-- Acciones -->
                    <template v-else-if="column.key === 'actions'">
                        <div class="sg-actions">
                            <a-tooltip title="Ver / actualizar">
                                <a-button class="sg-ab purple" @click="edit(record)"><EditOutlined /></a-button>
                            </a-tooltip>
                        </div>
                    </template>
                </template>
            </SgTable>
        </a-card>

        <!-- MODAL -->
        <SgModal v-model:open="open" :title="editing ? 'Sesión de tutoría' : 'Nueva sesión de tutoría'" :subtitle="editing?.student" :icon="IdcardOutlined"
            color="linear-gradient(135deg, #7c4ddb, #1d7fc9)" width="min(900px, 96vw)" :loading="form.processing" @ok="submit">
            <a-form layout="vertical" class="sg-grid-4">
                <a-form-item label="Alumno" required class="span-2" v-bind="err('student_id')">
                    <a-select v-model:value="form.student_id" show-search option-filter-prop="label" :options="students" size="large">
                        <template #suffixIcon><UserOutlined style="color: #64748b" /></template>
                    </a-select>
                </a-form-item>
                <a-form-item label="Fecha" v-bind="err('date')">
                    <a-date-picker v-model:value="form.date" value-format="YYYY-MM-DD" format="DD/MM/YYYY" size="large" style="width: 100%" />
                </a-form-item>
                <a-form-item label="Estado">
                    <a-select v-model:value="form.status" :options="[{ value: 'abierta', label: 'Abierta' }, { value: 'cerrada', label: 'Cerrada' }]" size="large" />
                </a-form-item>
                
                <a-form-item label="Tema" required class="span-2" v-bind="err('topic')">
                    <a-input v-model:value="form.topic" size="large">
                        <template #prefix><FileTextOutlined style="color: #7c4ddb" /></template>
                    </a-input>
                </a-form-item>
                <a-form-item label="Seguimiento (alerta)" class="span-2">
                    <a-date-picker v-model:value="form.follow_up_on" value-format="YYYY-MM-DD" format="DD/MM/YYYY" size="large" style="width: 100%">
                        <template #suffixIcon><CalendarOutlined style="color: #f39200" /></template>
                    </a-date-picker>
                </a-form-item>
                
                <a-form-item label="Observaciones" class="span-2">
                    <a-textarea v-model:value="form.observations" :rows="3" />
                </a-form-item>
                <a-form-item label="Compromisos" class="span-2">
                    <a-textarea v-model:value="form.agreements" :rows="3" />
                </a-form-item>
                <a-form-item label="Resultados" class="full">
                    <a-textarea v-model:value="form.results" :rows="2" />
                </a-form-item>
            </a-form>
        </SgModal>
    </AppLayout>
</template>
<style scoped>

.btn-new {
    height: 50px;
    border-radius: 10px;
    font-weight: 700;
    background: #fff;
    color: #0b3f6e;
    border: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.btn-new:hover {
    background: #f0f9ff !important;
    color: #0b3f6e !important;
}

/* Alumno (Nombre completo) */
.student-cell {
    display: flex;
    align-items: center;
    gap: 8px;
}
.student-text b {
    font-size: 14px;
    color: #0f172a;
    white-space: normal; /* Permite que el nombre se vea completo */
    word-break: break-word;
}

/* Tema */
.topic-title {
    font-size: 14px;
    color: #0f172a;
    display: block;
}

/* Compromisos */
.agreement-text {
    font-size: 13px;
    color: #475569;
    display: block;
    line-height: 1.4;
}

/* Seguimiento */
.follow-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    background: #fff;
    color: #7c4ddb;
    border: 1px solid #ddd2f7;
}
.follow-pill.is-alert {
    color: #e3262b;
    border-color: #f6c6c8;
}

</style>
