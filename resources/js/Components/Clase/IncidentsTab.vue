<script setup>
/** Incidencias de la clase: misma tabla que en Administración (filtros al pie, estado y prioridad, evidencia). */
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import {
    PlusOutlined, WarningOutlined, UserOutlined, TagsOutlined, FileTextOutlined, FlagOutlined, PaperClipOutlined, CalendarOutlined,
    LikeOutlined, AlertOutlined, SyncOutlined, CheckCircleOutlined, FireOutlined, MessageOutlined, ExclamationCircleOutlined, InfoCircleOutlined,
} from '@ant-design/icons-vue';
import EvidenceButton from '@/Components/EvidenceButton.vue';
import FileInput from '@/Components/FileInput.vue';
import SgModal from '@/Components/SgModal.vue';
import SgTable from '@/Components/SgTable.vue';
import StatCard from '@/Components/StatCard.vue';
import dayjs from 'dayjs';

const props = defineProps({ classId: Number, students: Array, incidents: Array, options: Object });

const open = ref(false);
const form = useForm({ student_id: null, date: dayjs().format('YYYY-MM-DD'), category: 'observacion', description: '', priority: 'media', notify_guardian: true, evidence: null });
const err = (k) => ({ 'validate-status': form.errors[k] ? 'error' : undefined, help: form.errors[k] });
const submit = () => form.post(route('docente.incidencias.store', props.classId), { forceFormData: true, preserveScroll: true, onSuccess: () => { open.value = false; form.reset(); } });

const STATUS_ICON = { abierta: AlertOutlined, seguimiento: SyncOutlined, cerrada: CheckCircleOutlined };
const STATUS_COLOR = { abierta: '#f39200', seguimiento: '#1d7fc9', cerrada: '#2e9b43' };
const PRIORITY_COLOR = { alta: '#e3262b', media: '#f39200', baja: '#94a3b8' };
const labelOf = (opts, v) => (opts ?? []).find((o) => o.value === v)?.label ?? v;

const stats = computed(() => ({
    abiertas: props.incidents.filter((i) => i.status === 'abierta').length,
    seguimiento: props.incidents.filter((i) => i.status === 'seguimiento').length,
    alta: props.incidents.filter((i) => i.priority === 'alta' && i.status !== 'cerrada').length,
    positivas: props.incidents.filter((i) => i.positive).length,
}));

const columns = [
    { title: 'Fecha', key: 'date', dataIndex: 'date', icon: CalendarOutlined, width: 120 },
    { title: 'Alumno', key: 'student', icon: UserOutlined, width: 260, filterValue: (r) => r.student },
    { title: 'Categoría', key: 'category', dataIndex: 'category', icon: TagsOutlined, filter: 'select', width: 210 },
    { title: 'Descripción', key: 'desc', icon: FileTextOutlined, filterValue: (r) => `${r.description} ${r.follow_up ?? ''}` },
    { title: 'Estado y prioridad', key: 'status', icon: FlagOutlined, filter: 'select', width: 180,
        filterValue: (r) => `${labelOf(props.options.statuses, r.status)} · ${labelOf(props.options.priorities, r.priority)}` },
    { title: 'Evidencia', key: 'evidence', icon: PaperClipOutlined, filter: 'select', width: 140, filterValue: (r) => (r.evidence_url ? 'Con evidencia' : 'Sin evidencia') },
];
</script>

<template>
    <div class="it">
        <div class="it-head">
            <div class="sg-stats it-stats">
                <StatCard label="Abiertas" :value="stats.abiertas" :icon="AlertOutlined" color="amber" />
                <StatCard label="En seguimiento" :value="stats.seguimiento" :icon="SyncOutlined" color="indigo" />
                <StatCard label="Prioridad alta" :value="stats.alta" :icon="ExclamationCircleOutlined" color="red" />
                <StatCard label="Reconocimientos" :value="stats.positivas" :icon="LikeOutlined" color="green" />
            </div>
            <a-button type="primary" size="large" @click="open = true"><template #icon><PlusOutlined /></template>Registrar incidencia</a-button>
        </div>

        <a-card :bordered="false" class="sg-fill">
            <SgTable :columns="columns" :data-source="incidents" unit="incidencias" empty-text="Sin incidencias registradas en esta clase">
                <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'date'"><span class="it-date"><CalendarOutlined /> {{ record.date }}</span></template>
                    <template v-else-if="column.key === 'student'">
                        <span class="sg-who"><span class="sg-av"><UserOutlined /></span><span><b>{{ record.student }}</b></span></span>
                    </template>
                    <template v-else-if="column.key === 'category'">
                        <span class="sg-chip" :style="{ '--c': record.positive ? '#2e9b43' : '#f39200' }">
                            <LikeOutlined v-if="record.positive" /><WarningOutlined v-else /> {{ record.category }}
                        </span>
                    </template>
                    <template v-else-if="column.key === 'desc'">
                        <div class="it-desc">{{ record.description }}</div>
                        <div v-if="record.follow_up" class="it-fu"><MessageOutlined /> {{ record.follow_up }}</div>
                    </template>
                    <template v-else-if="column.key === 'status'">
                        <div class="sp">
                            <span class="sg-chip" :style="{ '--c': STATUS_COLOR[record.status] }"><component :is="STATUS_ICON[record.status]" /> {{ labelOf(options.statuses, record.status) }}</span>
                            <span class="sp__prio" :style="{ '--c': PRIORITY_COLOR[record.priority] ?? '#94a3b8' }"><FireOutlined /> Prioridad {{ String(labelOf(options.priorities, record.priority)).toLowerCase() }}</span>
                        </div>
                    </template>
                    <template v-else-if="column.key === 'evidence'">
                        <EvidenceButton v-if="record.evidence_url" :url="record.evidence_url" :name="record.evidence_name" :title="`Evidencia · ${record.student}`" />
                        <span v-else class="sg-faint">Sin evidencia</span>
                    </template>
                </template>
            </SgTable>
        </a-card>

        <SgModal v-model:open="open" title="Registrar incidencia" subtitle="No modifica calificaciones; informa y permite dar seguimiento" :icon="WarningOutlined"
            color="linear-gradient(135deg, #f39200, #e3262b)" width="min(900px, 96vw)" ok-text="Registrar" :loading="form.processing" @ok="submit">
            <a-form layout="vertical" class="sg-grid-2">
                <a-form-item label="Alumno" required v-bind="err('student_id')">
                    <a-select v-model:value="form.student_id" show-search option-filter-prop="label" :options="students.map((s) => ({ value: s.id, label: s.list_name }))" />
                </a-form-item>
                <a-form-item label="Fecha" v-bind="err('date')"><a-date-picker v-model:value="form.date" value-format="YYYY-MM-DD" format="DD/MM/YYYY" style="width: 100%" /></a-form-item>
                <a-form-item label="Categoría" v-bind="err('category')"><a-select v-model:value="form.category" :options="options.categories" /></a-form-item>
                <a-form-item label="Prioridad" v-bind="err('priority')"><a-select v-model:value="form.priority" :options="options.priorities" /></a-form-item>
                <a-form-item label="Descripción" required v-bind="err('description')"><a-textarea v-model:value="form.description" :rows="5" /></a-form-item>
                <a-form-item label="Evidencia (opcional)" v-bind="err('evidence')"><FileInput v-model="form.evidence" accept=".pdf,.jpg,.jpeg,.png" hint="PDF, JPG o PNG" /></a-form-item>
            </a-form>
            <p class="sg-muted" style="margin: 0"><InfoCircleOutlined /> Las incidencias no modifican calificaciones; solo informan y permiten dar seguimiento.</p>
            <template #extra><a-checkbox v-model:checked="form.notify_guardian">Notificar al padre o tutor</a-checkbox></template>
        </SgModal>
    </div>
</template>

<style scoped>
.it { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; gap: 12px; }
.it > * { flex: none; }
.it > .sg-fill { flex: 1 1 auto; }
.it-head { display: flex; align-items: center; gap: 14px; }
.it-stats { flex: 1; margin: 0 !important; }
.it-date { display: inline-flex; align-items: center; gap: 6px; color: #44566c; font-size: 12.5px; }
.it-date .anticon { color: #94a3b8; }
.it-desc { color: #334155; font-size: 13px; }
.it-fu { margin-top: 4px; font-size: 12px; color: #0461d8; display: flex; gap: 6px; }
.sp { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; }
.sp__prio { display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px; font-weight: 700; color: var(--c); }
@media (max-width: 900px) { .it-head { flex-direction: column; align-items: stretch; } }
</style>
