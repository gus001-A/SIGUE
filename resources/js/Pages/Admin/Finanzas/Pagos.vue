<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import {
    DollarOutlined, PlusOutlined, PrinterOutlined, StopOutlined, NumberOutlined, CalendarOutlined, UserOutlined, FileTextOutlined,
    CreditCardOutlined, FlagOutlined, SettingOutlined, RiseOutlined, CheckCircleOutlined, CloseCircleOutlined,
    CheckCircleFilled, CloseCircleFilled, ClockCircleOutlined, SafetyCertificateOutlined, BankOutlined, WalletOutlined,
    MobileOutlined
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHead from '@/Components/PageHead.vue';
import StatCard from '@/Components/StatCard.vue';
import SgTable from '@/Components/SgTable.vue';
import SgModal from '@/Components/SgModal.vue';
import PaymentModal from './PaymentModal.vue';
import { money, optionMap } from '@/lib/format';

const props = defineProps({ items: Array, filters: Object, total: Number, options: Object, canManage: Boolean });
const methodLabel = computed(() => optionMap(props.options.methods));
const payRef = ref(null);
const applied = computed(() => props.items.filter((p) => p.status === 'aplicado'));

const cOpen = ref(false); const cRow = ref(null);
const cf = useForm({ reason: '' });
const doCancel = () => cf.post(route('finanzas.pagos.cancel', cRow.value.id), { preserveScroll: true, onSuccess: () => (cOpen.value = false) });

// Mapeo de estado (Estilo Incidencias)
const STATUS_MAP = {
    aplicado: { icon: CheckCircleFilled, color: '#2e9b43', bg: '#ecfdf3', label: 'Aplicado' },
    cancelado: { icon: CloseCircleFilled, color: '#e3262b', bg: '#fef2f2', label: 'Cancelado' }
};

// --- COLORES DINÁMICOS PARA FORMAS DE PAGO ---
const getMethodStyle = (methodLabelText) => {
    const text = (methodLabelText || '').toLowerCase();
    
    if (text.includes('efectivo')) return { c: '#2e9b43', bg: '#ecfdf3', border: '#bbf7d0', icon: WalletOutlined };
    if (text.includes('transferencia')) return { c: '#1d7fc9', bg: '#eff6ff', border: '#bfdbfe', icon: BankOutlined };
    if (text.includes('tarjeta')) return { c: '#7c4ddb', bg: '#f3effa', border: '#e2d6f5', icon: CreditCardOutlined };
    if (text.includes('depósito') || text.includes('deposito')) return { c: '#14a39a', bg: '#e0f2f1', border: '#b2dfdb', icon: DollarOutlined };
    if (text.includes('cheque')) return { c: '#f39200', bg: '#fff8eb', border: '#fbd38d', icon: FileTextOutlined };
    if (text.includes('digital') || text.includes('app') || text.includes('móvil')) return { c: '#e0457b', bg: '#fdeef4', border: '#fbcfe8', icon: MobileOutlined };
    
    // Color por defecto (Gris)
    return { c: '#475569', bg: '#f1f5f9', border: '#e2e8f0', icon: CreditCardOutlined };
};

// --- NUEVO ORDEN DE COLUMNAS ---
const columns = [
    { title: 'Folio', key: 'folio', dataIndex: 'folio', icon: NumberOutlined, width: 130 },
    { title: 'Alumno', key: 'student', icon: UserOutlined, filterValue: (r) => `${r.student} ${r.enrollment_number}` }, 
    { title: 'Concepto', key: 'concept', dataIndex: 'concept', icon: FileTextOutlined },
    { title: 'Forma de pago', key: 'method', dataIndex: 'method', icon: CreditCardOutlined, filter: 'select', filterOptions: props.options.methods, width: 170 },
    { title: 'Referencia', key: 'reference', icon: FileTextOutlined, width: 150 },
    { title: 'Importe', key: 'amount', icon: DollarOutlined, width: 130, align: 'right', filterValue: (r) => money(r.amount) },
    { title: 'Estado', key: 'status', dataIndex: 'status', icon: FlagOutlined, filter: 'select', width: 140, align: 'center',
        filterOptions: [{ value: 'aplicado', label: 'Aplicado' }, { value: 'cancelado', label: 'Cancelado' }] },
    { title: 'Recibió', key: 'received_by', icon: SafetyCertificateOutlined, width: 200 },
    { title: 'Acciones', key: 'actions', icon: SettingOutlined, width: 100, align: 'center' },
];
</script>

<template>
    <AppLayout title="Pagos">
        <PageHead title="Pagos y recibos" subtitle="Registro de pagos, movimientos y recibos del plantel" :icon="DollarOutlined">
            <template #actions><a-button v-if="canManage" type="primary" size="large" @click="payRef.open()"><template #icon><PlusOutlined /></template>Registrar pago</a-button></template>
        </PageHead>

        <div class="sg-stats">
            <StatCard label="Total aplicado" :value="money(total)" :icon="RiseOutlined" color="green" />
            <StatCard label="Pagos aplicados" :value="applied.length" :icon="CheckCircleOutlined" color="indigo" />
            <StatCard label="Cancelados" :value="items.length - applied.length" :icon="CloseCircleOutlined" color="red" />
            <StatCard label="Pago promedio" :value="money(applied.length ? total / applied.length : 0)" :icon="DollarOutlined" color="violet" />
        </div>

        <a-card :bordered="false" class="sg-fill">
            <SgTable :columns="columns" :data-source="items" unit="pagos">
                <template #bodyCell="{ column, record }">
                    
                    <!-- 1. Folio -->
                    <template v-if="column.key === 'folio'">
                        <span class="sg-chip" style="--c: #0f5a99"><NumberOutlined /> {{ record.folio }}</span>
                    </template>

                    <!-- 2. Alumno -->
                    <template v-else-if="column.key === 'student'">
                        <Link :href="route('alumnos.show', record.student_id)" class="sg-who student-cell">
                            <span class="sg-av"><UserOutlined /></span>
                            <span class="student-text"><b>{{ record.student }}</b></span>
                        </Link>
                    </template>

                    <!-- 3. Concepto -->
                    <template v-else-if="column.key === 'concept'">
                        {{ record.concept }}
                    </template>

                    <!-- 4. Forma de pago (COLORES DINÁMICOS) -->
                    <template v-else-if="column.key === 'method'">
                        <span class="method-badge" :style="{ 
                            background: getMethodStyle(methodLabel[record.method]).bg, 
                            color: getMethodStyle(methodLabel[record.method]).c,
                            borderColor: getMethodStyle(methodLabel[record.method]).border
                        }">
                            <component :is="getMethodStyle(methodLabel[record.method]).icon" /> 
                            {{ methodLabel[record.method] }}
                        </span>
                    </template>

                    <!-- 5. Referencia -->
                    <template v-else-if="column.key === 'reference'">
                        <span class="ref-cell">{{ record.reference || '—' }}</span>
                    </template>

                    <!-- 6. Importe -->
                    <template v-else-if="column.key === 'amount'">
                        <b :style="{ color: '#2e9b43', textDecoration: record.status === 'cancelado' ? 'line-through' : 'none' }">
                            {{ money(record.amount) }}
                        </b>
                    </template>

                    <!-- 7. Estado (Estilo Incidencias) -->
                    <template v-else-if="column.key === 'status'">
                        <span class="status-pill" :style="{ background: STATUS_MAP[record.status]?.bg, color: STATUS_MAP[record.status]?.color }">
                            <component :is="STATUS_MAP[record.status]?.icon" class="status-icon" />
                            <span class="status-text">{{ STATUS_MAP[record.status]?.label }}</span>
                        </span>
                    </template>

                    <!-- 8. Recibió (Usuario + Fecha + Hora) -->
                    <template v-else-if="column.key === 'received_by'">
                        <div class="trace-cell">
                            <span class="trace-user"><SafetyCertificateOutlined /> {{ record.received_by || 'Sistema' }}</span>
                            <span class="trace-date"><ClockCircleOutlined /> {{ record.paid_at }}</span>
                        </div>
                    </template>

                    <!-- 9. Acciones -->
                    <template v-else-if="column.key === 'actions'">
                        <div class="sg-actions">
                            <a-tooltip title="Imprimir recibo"><a-button class="sg-ab blue" :href="route('finanzas.recibo', record.id)" download><PrinterOutlined /></a-button></a-tooltip>
                            <a-tooltip v-if="canManage && record.status === 'aplicado'" title="Cancelar pago"><a-button class="sg-ab red" @click="cRow = record; cf.reset(); cOpen = true"><StopOutlined /></a-button></a-tooltip>
                        </div>
                    </template>
                </template>
            </SgTable>
        </a-card>

        <PaymentModal ref="payRef" :options="options" />

        <SgModal v-model:open="cOpen" :title="`Cancelar pago ${cRow?.folio ?? ''}`" :subtitle="`${cRow?.student} · ${money(cRow?.amount)}`" :icon="StopOutlined"
            color="linear-gradient(135deg, #e3262b, #b71c21)" width="min(600px, 96vw)" ok-text="Cancelar pago" :ok-icon="StopOutlined" ok-danger :loading="cf.processing" @ok="doCancel">
            <a-form layout="vertical">
                <a-form-item label="Motivo" required :validate-status="cf.errors.reason ? 'error' : undefined" :help="cf.errors.reason"><a-textarea v-model:value="cf.reason" :rows="3" /></a-form-item>
            </a-form>
        </SgModal>
    </AppLayout>
</template>

<style scoped>
/* Forma de Pago (Píldora) */
.method-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 12.5px;
    border: 1px solid;
    white-space: nowrap;
}

/* Estilo para Recibió */
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

/* Referencia */
.ref-cell {
    font-family: 'Courier New', Courier, monospace;
    font-size: 13px;
    color: #475569;
}

/* Estado */
.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
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