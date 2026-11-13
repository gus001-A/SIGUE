<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import dayjs from 'dayjs';
import {
    WarningOutlined, AlertOutlined, SyncOutlined, LikeOutlined, ExclamationCircleOutlined, CalendarOutlined, UserOutlined,
    TagsOutlined, FileTextOutlined, FireOutlined, FlagOutlined, SettingOutlined, FormOutlined, CheckCircleOutlined, MessageOutlined,
    SafetyCertificateOutlined, FilterOutlined, ClearOutlined, HistoryOutlined, PaperClipOutlined
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHead from '@/Components/PageHead.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusTag from '@/Components/StatusTag.vue';
import EvidenceButton from '@/Components/EvidenceButton.vue';
import SgTable from '@/Components/SgTable.vue';
import SgModal from '@/Components/SgModal.vue';

const props = defineProps({ items: Array, filters: Object, options: Object, stats: Object });

// --- FILTRO POR FECHA (de tal a tal fecha, se filtra en el servidor) ---
const range = ref(props.filters?.from && props.filters?.to ? [dayjs(props.filters.from), dayjs(props.filters.to)] : null);
const PRESETS = [
    { key: 'hoy', label: 'Hoy', get: () => [dayjs(), dayjs()] },
    { key: 'semana', label: 'Esta semana', get: () => [dayjs().startOf('week').add(1, 'day'), dayjs()] },
    { key: 'mes', label: 'Este mes', get: () => [dayjs().startOf('month'), dayjs()] },
    { key: '30', label: 'Últimos 30 días', get: () => [dayjs().subtract(29, 'day'), dayjs()] },
];
const activePreset = computed(() => {
    if (!range.value) return null;
    const [a, b] = range.value.map((d) => d.format('YYYY-MM-DD'));
    return PRESETS.find((p) => { const [x, y] = p.get().map((d) => d.format('YYYY-MM-DD')); return x === a && y === b; })?.key ?? null;
});
function applyRange(v) {
    range.value = v && v[0] && v[1] ? v : null;
    const params = { ...props.filters };
    delete params.from; delete params.to;
    if (range.value) { params.from = range.value[0].format('YYYY-MM-DD'); params.to = range.value[1].format('YYYY-MM-DD'); }
    Object.keys(params).forEach((k) => (params[k] === null || params[k] === undefined || params[k] === '') && delete params[k]);
    router.get(route('incidencias.index'), params, { preserveState: true, preserveScroll: true, replace: true });
}
const rangeLabel = computed(() => (range.value ? `${range.value[0].format('DD/MM/YYYY')} – ${range.value[1].format('DD/MM/YYYY')}` : 'Todas las fechas'));

// --- SEGUIMIENTO ---
const open = ref(false); const row = ref(null);
const form = useForm({ status: 'seguimiento', follow_up: '' });
function edit(r) { row.value = r; form.status = r.status === 'abierta' ? 'seguimiento' : r.status; form.follow_up = r.follow_up ?? ''; open.value = true; }
const save = () => form.put(route('incidencias.update', row.value.id), { preserveScroll: true, onSuccess: () => (open.value = false) });

const STATUS_ICON = { abierta: AlertOutlined, seguimiento: SyncOutlined, cerrada: CheckCircleOutlined };
const STATUS_COLOR = { abierta: '#f39200', seguimiento: '#1d7fc9', cerrada: '#2e9b43' };

const labelOf = (opts, v) => opts.find((o) => o.value === v)?.label ?? v;
const PRIORITY_COLOR = { alta: '#e3262b', media: '#f39200', baja: '#94a3b8' };

// --- COLUMNAS ---
const columns = [
    { title: 'Alumno', key: 'student', icon: UserOutlined, filterValue: (r) => r.student },
    { title: 'Categoría', key: 'category', dataIndex: 'category', icon: TagsOutlined, filter: 'select', filterOptions: props.options.categories, width: 200 },
    { title: 'Tutor', key: 'teacher', icon: UserOutlined, width: 180 },
    { title: 'Descripción', key: 'desc', icon: FileTextOutlined, filterValue: (r) => `${r.description} ${r.follow_up ?? ''}` },
    { title: 'Estado y prioridad', key: 'status', icon: FlagOutlined, filter: 'select', width: 175,
        filterValue: (r) => `${labelOf(props.options.statuses, r.status)} · ${labelOf(props.options.priorities, r.priority)}` },
    { title: 'Evidencia', key: 'evidence', icon: PaperClipOutlined, filter: 'select', width: 135, filterValue: (r) => (r.evidence_url ? 'Con evidencia' : 'Sin evidencia') },
    { title: 'Registró', key: 'registered_by', icon: SafetyCertificateOutlined, width: 170 },
    { title: 'Seguimiento', key: 'actions', icon: SettingOutlined, width: 160, align: 'center' },
];
</script>

<template>
    <AppLayout title="Incidencias">
        <PageHead title="Incidencias" subtitle="Registro conductual, reconocimientos y seguimiento" :icon="WarningOutlined">
        </PageHead>

        <div class="sg-stats">
            <StatCard label="Abiertas" :value="stats.abiertas" :icon="AlertOutlined" color="amber" />
            <StatCard label="En seguimiento" :value="stats.seguimiento" :icon="SyncOutlined" color="indigo" />
            <StatCard label="Prioridad alta" :value="stats.alta" :icon="ExclamationCircleOutlined" color="red" />
            <StatCard label="Reconocimientos (30 días)" :value="stats.positivas" :icon="LikeOutlined" color="green" />
        </div>

        <!-- Filtro por rango de fechas -->
        <div class="sg-toolbar2 df">
            <span class="sg-toolbar2__label"><CalendarOutlined /> Periodo</span>
            <a-range-picker :value="range" format="DD/MM/YYYY" :placeholder="['Desde', 'Hasta']" allow-clear class="df__picker"
                :presets="PRESETS.map((p) => ({ label: p.label, value: p.get() }))" @change="applyRange" />
            <div class="df__presets">
                <button v-for="p in PRESETS" :key="p.key" type="button" class="df__chip" :class="{ on: activePreset === p.key }" @click="applyRange(p.get())">{{ p.label }}</button>
                <button v-if="range" type="button" class="df__chip df__chip--clear" @click="applyRange(null)"><ClearOutlined /> Quitar filtro</button>
            </div>
            <span class="df__count"><HistoryOutlined /> <b>{{ items.length }}</b> incidencia{{ items.length === 1 ? '' : 's' }} · {{ rangeLabel }}</span>
        </div>

        <a-card :bordered="false" class="sg-fill">
            <SgTable :columns="columns" :data-source="items" unit="incidencias">
                <template #bodyCell="{ column, record }">
                    
                    <!-- Alumno -->
                    <template v-if="column.key === 'student'">
                        <Link :href="route('alumnos.show', record.student_id)" class="sg-who">
                            <span class="sg-av"><UserOutlined /></span>
                            <span><b>{{ record.student }}</b></span>
                        </Link>
                    </template>

                    <!-- Categoría -->
                    <template v-else-if="column.key === 'category'">
                        <span class="sg-chip" :style="{ '--c': record.positive ? '#2e9b43' : '#f39200', whiteSpace: 'nowrap' }">
                            <LikeOutlined v-if="record.positive" /><WarningOutlined v-else /> {{ record.category_label }}
                        </span>
                    </template>

                    <!-- Tutor -->
                    <template v-else-if="column.key === 'teacher'">
                        <div class="tutor-cell">
                            <UserOutlined />
                            <span>{{ record.teacher || '—' }}</span>
                            <small v-if="record.subject">· {{ record.subject }}</small>
                        </div>
                    </template>

                    <!-- Descripción -->
                    <template v-else-if="column.key === 'desc'">
                        <a-tooltip :title="record.description">
                            <div class="clamp">{{ record.description }}</div>
                        </a-tooltip>
                        <div v-if="record.follow_up" class="fu"><MessageOutlined /> {{ record.follow_up }}</div>
                    </template>

                    <!-- Estado y prioridad -->
                    <template v-else-if="column.key === 'status'">
                        <div class="sp">
                            <span class="sg-chip" :style="{ '--c': STATUS_COLOR[record.status] }">
                                <component :is="STATUS_ICON[record.status]" /> {{ labelOf(options.statuses, record.status) }}
                            </span>
                            <span class="sp__prio" :style="{ '--c': PRIORITY_COLOR[record.priority] ?? '#94a3b8' }"><FireOutlined /> Prioridad {{ labelOf(options.priorities, record.priority)?.toLowerCase() }}</span>
                        </div>
                    </template>

                    <!-- Evidencia -->
                    <template v-else-if="column.key === 'evidence'">
                        <EvidenceButton v-if="record.evidence_url" :url="record.evidence_url" :name="record.evidence_name" :title="`Evidencia · ${record.student}`" />
                        <span v-else class="sg-faint">Sin evidencia</span>
                    </template>

                    <!-- Registró -->
                    <template v-else-if="column.key === 'registered_by'">
                        <div class="trace-cell">
                            <span class="trace-user"><SafetyCertificateOutlined /> {{ record.created_by || 'Sistema' }}</span>
                            <span class="trace-date"><CalendarOutlined /> {{ record.created_at || record.date }}</span>
                        </div>
                    </template>

                    <!-- Seguimiento -->
                    <template v-else-if="column.key === 'actions'">
                        <button class="fu-btn" :class="`is-${record.status}`" @click="edit(record)">
                            <FormOutlined /> {{ record.status === 'cerrada' ? 'Reabrir' : record.follow_up ? 'Actualizar' : 'Seguimiento' }}
                        </button>
                    </template>
                </template>
            </SgTable>
        </a-card>

        <!-- MODAL DE SEGUIMIENTO -->
        <SgModal v-model:open="open" title="Seguimiento de incidencia" :subtitle="`${row?.student} · ${row?.category_label} · ${row?.date}`" :icon="FormOutlined"
            color="linear-gradient(135deg, #f39200, #e3262b)" width="min(760px, 96vw)" ok-text="Guardar seguimiento" :loading="form.processing" @ok="save">
            <div class="desc"><FileTextOutlined /> {{ row?.description }}
                <div><EvidenceButton :url="row?.evidence_url" :name="row?.evidence_name" :title="`Evidencia · ${row?.student}`" /></div></div>
            <a-form layout="vertical">
                <a-form-item label="Estado">
                    <div class="states">
                        <button v-for="s in options.statuses" :key="s.value" type="button" class="state" :class="{ on: form.status === s.value }"
                            :style="{ '--c': STATUS_COLOR[s.value] }" @click="form.status = s.value">
                            <component :is="STATUS_ICON[s.value]" /> {{ s.label }}
                        </button>
                    </div>
                </a-form-item>
                <a-form-item label="Notas de seguimiento (acuerdos, llamadas, canalizaciones…)" :validate-status="form.errors.follow_up ? 'error' : undefined" :help="form.errors.follow_up">
                    <a-textarea v-model:value="form.follow_up" :rows="5" />
                </a-form-item>
            </a-form>
        </SgModal>
    </AppLayout>
</template>

<style scoped>
.sp { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; }
.sp__prio { display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px; font-weight: 700; color: var(--c); }
/* Filtro por rango de fechas */
.df { flex-wrap: wrap; }
.df__picker { width: 270px; }
.df__presets { display: flex; gap: 6px; flex-wrap: wrap; }
.df__chip { border: 1px solid #e2e8f0; background: #fff; color: #475569; border-radius: 999px; padding: 4px 12px; font-size: 12.5px; font-weight: 600; cursor: pointer; font-family: inherit; transition: all .15s; }
.df__chip:hover { border-color: #1d7fc9; color: #1d7fc9; }
.df__chip.on { background: #1d7fc9; border-color: #1d7fc9; color: #fff; }
.df__chip--clear { color: #e3262b; border-color: #fecaca; background: #fef2f2; display: inline-flex; align-items: center; gap: 4px; }
.df__count { margin-left: auto; display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; color: #64748b; }
.df__count b { color: #0b2f55; }

/* Estilo para el Tutor */
.tutor-cell {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #475569;
    white-space: nowrap;
}
.tutor-cell small {
    color: #94a3b8;
    font-size: 11px;
}

/* Estilo para la descripción */
.clamp {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    white-space: normal;
    max-width: 220px;
}
.fu {
    margin-top: 4px;
    max-width: 220px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 12px;
    color: #0f5a99;
    background: #eaf4fc;
    border-radius: 8px;
    padding: 3px 8px;
}

/* Estilo para la columna Registró */
.trace-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 12px;
}
.trace-user {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
    color: #1d7fc9;
}
.trace-date {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #64748b;
}

/* Botón de seguimiento */
.fu-btn {
    display: inline-flex; align-items: center; gap: 6px; border: 0; cursor: pointer; padding: 7px 12px; border-radius: 10px;
    font-weight: 650; font-size: 12.5px; color: #fff; background: linear-gradient(135deg, #f39200, #e3262b);
    box-shadow: 0 6px 14px -6px rgba(227, 38, 43, .55); transition: transform .15s, box-shadow .15s; white-space: nowrap;
}
.fu-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 18px -8px rgba(227, 38, 43, .6); }
.fu-btn.is-seguimiento { background: linear-gradient(135deg, #1d7fc9, #0f5a99); box-shadow: 0 6px 14px -6px rgba(29, 127, 201, .55); }
.fu-btn.is-cerrada { background: #eef6ee; color: #2e9b43; box-shadow: none; }

/* Modal */
.desc { background: #f7fafd; border: 1px solid var(--sg-line); border-radius: 12px; padding: 10px 12px; margin-bottom: 14px; color: #334155; }
.states { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
.state { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px; border-radius: 12px; border: 2px solid var(--sg-line); background: #fff; cursor: pointer; font-weight: 650; color: #475569; }
.state.on { border-color: var(--c); color: var(--c); background: color-mix(in srgb, var(--c) 8%, #fff); }
</style>