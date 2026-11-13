<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    WalletOutlined, PlusOutlined, StopOutlined, DollarOutlined, WarningOutlined, RiseOutlined, FieldTimeOutlined, UserOutlined,
    FileTextOutlined, CalendarOutlined, FlagOutlined, SettingOutlined, TagsOutlined, GiftOutlined, NotificationOutlined,
    AppstoreOutlined, GlobalOutlined, NumberOutlined, CheckCircleOutlined, ClockCircleOutlined,
    CheckCircleFilled, CloseCircleOutlined, ExclamationCircleOutlined
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHead from '@/Components/PageHead.vue';
import StatCard from '@/Components/StatCard.vue';
import SgTable from '@/Components/SgTable.vue';
import SgModal from '@/Components/SgModal.vue';
import PaymentModal from './PaymentModal.vue';
import { money, optionMap } from '@/lib/format';
import { confirmAction } from '@/lib/notify';

const props = defineProps({ items: Array, filters: Object, stats: Object, options: Object, canManage: Boolean });
const typeLabel = computed(() => optionMap(props.options.types));
const onlyOverdue = ref(false);
const rows = computed(() => (onlyOverdue.value ? props.items.filter((c) => c.overdue) : props.items));

const open = ref(false);
const form = useForm({ target: 'student', student_id: null, group_id: null, type: 'colegiatura', concept: '', amount: null, due_on: null, apply_scholarship: true, notify: true });
const err = (k) => ({ 'validate-status': form.errors[k] ? 'error' : undefined, help: form.errors[k] });
const submit = () => form.post(route('finanzas.adeudos.store'), { preserveScroll: true, onSuccess: () => { open.value = false; form.reset(); } });
const cancel = (r) => confirmAction({ title: '¿Cancelar cargo?', content: `${r.concept} · ${r.student}`, tone: 'delete', okText: 'Sí, cancelar cargo', onOk: () => router.post(route('finanzas.adeudos.cancel', r.id), {}, { preserveScroll: true }) });
const payRef = ref(null);
const filterOption = (input, opt) => opt.label.toLowerCase().includes(input.toLowerCase());

// Mapeo de estado (Estilo Incidencias)
const STATUS_MAP = {
    pendiente: { icon: ClockCircleOutlined, color: '#f39200', bg: '#fff8eb', label: 'Pendiente' },
    parcial: { icon: ExclamationCircleOutlined, color: '#7c4ddb', bg: '#f3effa', label: 'Parcial' },
    pagado: { icon: CheckCircleOutlined, color: '#2e9b43', bg: '#ecfdf3', label: 'Pagado' },
    cancelado: { icon: CloseCircleOutlined, color: '#e3262b', bg: '#fef2f2', label: 'Cancelado' }
};

// --- NUEVO ORDEN Y ANCHOS ---
const columns = [
    { title: 'Matrícula', key: 'enrollment_number', icon: NumberOutlined, width: 130 },
    { title: 'Alumno', key: 'student', icon: UserOutlined, filterValue: (r) => `${r.student} ${r.enrollment_number}`, width: 260 },
    { title: 'Concepto', key: 'concept', dataIndex: 'concept', icon: FileTextOutlined, width: 180 },
    { title: 'Tipo', key: 'type', dataIndex: 'type', icon: TagsOutlined, filter: 'select', filterOptions: props.options.types, width: 140 },
    { title: 'Vence', key: 'due', dataIndex: 'due_on', icon: CalendarOutlined, width: 120 },
    { title: 'Total', key: 'total', icon: WalletOutlined, width: 140, align: 'right', filterValue: (r) => money(r.total) },
    // Se agrega un poco de espacio a la izquierda para separar del Total
    { title: 'Estado', key: 'status', dataIndex: 'status', icon: FlagOutlined, filter: 'select', filterOptions: props.options.statuses, width: 160, align: 'center' },
    { title: 'Acciones', key: 'actions', icon: SettingOutlined, width: 120, align: 'center' },
];

// Función para determinar el único estado a mostrar
const getDisplayStatus = (record) => {
    if (record.overdue) return { ...STATUS_MAP['pendiente'], label: 'Vencido', color: '#e3262b', bg: '#fef2f2', icon: WarningOutlined };
    return STATUS_MAP[record.status];
};
</script>

<template>
    <AppLayout title="Adeudos">
        <PageHead title="Adeudos y cooperaciones" subtitle="Colegiaturas, inscripciones, cooperaciones y servicios del plantel" :icon="WalletOutlined">
            <template #actions>
                <a-button v-if="canManage" size="large" @click="payRef.open()"><template #icon><DollarOutlined style="color: #2e9b43" /></template>Registrar pago</a-button>
                <a-button v-if="canManage" type="primary" size="large" @click="open = true"><template #icon><PlusOutlined /></template>Nuevo cargo</a-button>
            </template>
        </PageHead>

        <div class="sg-stats">
            <StatCard label="Por cobrar" :value="money(stats.por_cobrar)" :icon="DollarOutlined" color="indigo" />
            <StatCard label="Cargos vencidos" :value="stats.vencidos" :icon="WarningOutlined" color="red" :active="onlyOverdue" style="cursor: pointer" @click="onlyOverdue = !onlyOverdue" />
            <StatCard label="Monto vencido" :value="money(stats.monto_vencido)" :icon="FieldTimeOutlined" color="amber" />
            <StatCard label="Cobrado este mes" :value="money(stats.cobrado_mes)" :icon="RiseOutlined" color="green" />
        </div>

        <a-card :bordered="false" class="sg-fill">
            <SgTable :columns="columns" :data-source="rows" unit="cargos">
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

                    <template v-else-if="column.key === 'concept'">
                        {{ record.concept }}
                        <div v-if="record.scholarship" class="sg-cell-sub"><GiftOutlined style="color: #6b4c8c" /> beca {{ record.scholarship }}</div>
                    </template>

                    <template v-else-if="column.key === 'type'">
                        <span class="sg-chip" style="--c: #7c4ddb">{{ typeLabel[record.type] }}</span>
                    </template>

                    <template v-else-if="column.key === 'due'">
                        <span :style="{ color: record.overdue ? '#e3262b' : undefined, fontWeight: record.overdue ? 700 : 400 }">
                            <CalendarOutlined /> {{ record.due_on || '—' }}
                        </span>
                    </template>

                    <template v-else-if="column.key === 'total'">
                        <div class="total-cell">
                            {{ money(record.total) }}
                            <div v-if="record.discount" class="sg-cell-sub">-{{ money(record.discount) }}</div>
                        </div>
                    </template>

                    <!-- ESTADO ÚNICO -->
                    <template v-else-if="column.key === 'status'">
                        <div class="status-cell">
                            <span class="status-pill" :style="{ background: getDisplayStatus(record)?.bg, color: getDisplayStatus(record)?.color }">
                                <component :is="getDisplayStatus(record)?.icon" class="status-icon" />
                                <span class="status-text">{{ getDisplayStatus(record)?.label }}</span>
                            </span>
                        </div>
                    </template>

                    <template v-else-if="column.key === 'actions'">
                        <div v-if="canManage" class="sg-actions">
                            <a-tooltip v-if="['pendiente', 'parcial'].includes(record.status)" title="Registrar pago">
                                <a-button class="sg-ab green" @click="payRef.open({ student_id: record.student_id, charge_id: record.id, amount: record.balance })"><DollarOutlined /></a-button>
                            </a-tooltip>
                            <a-tooltip v-if="record.status === 'pendiente'" title="Cancelar cargo">
                                <a-button class="sg-ab red" @click="cancel(record)"><StopOutlined /></a-button>
                            </a-tooltip>
                        </div>
                    </template>
                </template>
            </SgTable>
        </a-card>

        <PaymentModal ref="payRef" :options="options" />

        <SgModal v-model:open="open" title="Nuevo cargo" subtitle="Colegiatura, inscripción, cooperación o servicio" :icon="WalletOutlined" color="linear-gradient(135deg, #e3262b, #f39200)"
            width="min(880px, 96vw)" ok-text="Registrar cargo" :loading="form.processing" @ok="submit">
            <a-form layout="vertical" class="sg-grid-2">
                <a-form-item label="Aplicar a" class="full">
                    <a-segmented v-model:value="form.target" block :options="[
                        { value: 'student', label: 'Un alumno' }, { value: 'group', label: 'Un grupo' }, { value: 'all', label: 'Todos los alumnos activos del plantel' }]" />
                </a-form-item>
                <a-form-item v-if="form.target === 'student'" label="Alumno" required class="full" v-bind="err('student_id')"><a-select v-model:value="form.student_id" show-search :filter-option="filterOption" :options="options.students"><template #suffixIcon><UserOutlined /></template></a-select></a-form-item>
                <a-form-item v-else-if="form.target === 'group'" label="Grupo" required class="full" v-bind="err('group_id')"><a-select v-model:value="form.group_id" :options="options.groups"><template #suffixIcon><AppstoreOutlined /></template></a-select></a-form-item>
                <a-form-item v-else label="Alcance" class="full"><a-alert type="warning" show-icon :message="'Se generará un cargo para cada alumno activo del plantel ' + ($page.props.campus?.name ?? '')"><template #icon><GlobalOutlined /></template></a-alert></a-form-item>
                
                <a-form-item label="Tipo" v-bind="err('type')"><a-select v-model:value="form.type" :options="options.types" /></a-form-item>
                <a-form-item label="Concepto" required v-bind="err('concept')"><a-input v-model:value="form.concept" placeholder="Colegiatura diciembre"><template #prefix><FileTextOutlined style="color: #1d7fc9" /></template></a-input></a-form-item>
                <a-form-item label="Vencimiento" v-bind="err('due_on')"><a-date-picker v-model:value="form.due_on" value-format="YYYY-MM-DD" format="DD/MM/YYYY" style="width: 100%" /></a-form-item>
                
                <a-form-item label="Importe" required class="full" v-bind="err('amount')">
                    <a-input-number v-model:value="form.amount" :min="0.01" :precision="2" prefix="$" disabled style="width: 100%;" size="large" />
                </a-form-item>
                
                <a-form-item label="Opciones" class="full">
                    <a-checkbox v-model:checked="form.apply_scholarship"><GiftOutlined style="color: #6b4c8c" /> Aplicar becas</a-checkbox>
                    <a-checkbox v-model:checked="form.notify"><NotificationOutlined style="color: #f39200" /> Notificar a tutores</a-checkbox>
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

/* Alumno */
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

/* Total */
.total-cell {
    text-align: right;
    padding-right: 8px; /* Empuja el total un poco a la izquierda, alejándolo del estado */
}

/* Estado (Centrado y con margen) */
.status-cell {
    display: flex;
    justify-content: center;
    width: 100%;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px; /* Un poco más de padding interno */
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    border: 1px solid color-mix(in srgb, currentColor 20%, transparent);
    white-space: nowrap;
}
.status-icon {
    font-size: 14px;
}
</style>