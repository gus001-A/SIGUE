<script setup>
import { ref, computed } from 'vue';
import { 
    CheckCircleOutlined, CloseCircleOutlined, ClockCircleOutlined, SafetyCertificateOutlined, 
    ReadOutlined, CalendarOutlined, FlagOutlined, FileTextOutlined, BarChartOutlined, EyeOutlined,
    PieChartOutlined
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PortalHeader from '@/Components/PortalHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusTag from '@/Components/StatusTag.vue';
import SgTable from '@/Components/SgTable.vue';
import SgModal from '@/Components/SgModal.vue';

const props = defineProps({ student: Object, children: Array, isGuardian: Boolean, summary: Object, bySubject: Array, incidents: Array });
const rateColor = (r) => (r >= 90 ? '#2e9b43' : r >= 80 ? '#f39200' : '#e3262b');

// Modal de faltas/retardos
const modalOpen = ref(false);

// Totales para la gráfica
const totalSessions = computed(() => 
    props.summary.presente + props.summary.falta + props.summary.retardo + props.summary.justificada
);
const rate = computed(() => {
    if (totalSessions.value === 0) return 0;
    const valid = props.summary.presente + props.summary.justificada;
    return Math.round((valid / totalSessions.value) * 100);
});

const STATUSES = [{ value: 'falta', label: 'Falta' }, { value: 'retardo', label: 'Retardo' }, { value: 'justificada', label: 'Justificada' }];
const columns = [
    { title: 'Fecha', key: 'date', dataIndex: 'date', icon: CalendarOutlined, width: 120 },
    { title: 'Asignatura', key: 'subject', dataIndex: 'subject', icon: ReadOutlined, filter: 'select' },
    { title: 'Tipo', key: 'status', dataIndex: 'status', icon: FlagOutlined, filter: 'select', filterOptions: STATUSES, width: 140 },
    { title: 'Observación', key: 'notes', dataIndex: 'notes', icon: FileTextOutlined },
];
</script>

<template>
    <AppLayout title="Asistencias">
        <PortalHeader :student="student" :children="children" :is-guardian="isGuardian" title="Asistencias" />

        <!-- KPIs ARRIBA -->
        <div class="kpi-grid">
            <StatCard label="Asistencias" :value="summary.presente" :icon="CheckCircleOutlined" color="green" />
            <StatCard label="Faltas" :value="summary.falta" :icon="CloseCircleOutlined" color="red" />
            <StatCard label="Retardos" :value="summary.retardo" :icon="ClockCircleOutlined" color="amber" />
            <StatCard label="Justificadas" :value="summary.justificada" :icon="SafetyCertificateOutlined" color="indigo" />
        </div>

        <a-row :gutter="[20, 20]">
            
            <!-- GRÁFICA CENTRAL (IZQUIERDA) -->
            <a-col :xs="24" :lg="10">
                <a-card :bordered="false" class="main-card">
                    <template #title><span class="sg-card-title" style="--c: #1d7fc9"><PieChartOutlined /> Resumen general</span></template>
                    
                    <div class="chart-container">
                        <a-progress
                            type="circle"
                            :percent="rate"
                            :size="220"
                            :stroke-color="rateColor(rate)"
                            :stroke-width="16"
                        >
                            <template #format>
                                <div class="chart-center">
                                    <span class="chart-rate">{{ rate }}%</span>
                                    <span class="chart-label">Asistencia</span>
                                    <span class="chart-sub">{{ totalSessions }} sesiones</span>
                                </div>
                            </template>
                        </a-progress>
                    </div>

                    <div class="chart-legend">
                        <div class="legend-item" style="--c: #2e9b43">
                            <span class="legend-dot"></span>
                            <span class="legend-label">Asistencias</span>
                            <b>{{ summary.presente }}</b>
                        </div>
                        <div class="legend-item" style="--c: #e3262b">
                            <span class="legend-dot"></span>
                            <span class="legend-label">Faltas</span>
                            <b>{{ summary.falta }}</b>
                        </div>
                        <div class="legend-item" style="--c: #f39200">
                            <span class="legend-dot"></span>
                            <span class="legend-label">Retardos</span>
                            <b>{{ summary.retardo }}</b>
                        </div>
                        <div class="legend-item" style="--c: #1d7fc9">
                            <span class="legend-dot"></span>
                            <span class="legend-label">Justificadas</span>
                            <b>{{ summary.justificada }}</b>
                        </div>
                    </div>

                    <a-button type="primary" block size="large" class="btn-detail" @click="modalOpen = true">
                        <EyeOutlined /> Ver detalle de incidencias
                    </a-button>
                </a-card>
            </a-col>

            <!-- POR ASIGNATURA (DERECHA) -->
            <a-col :xs="24" :lg="14">
                <a-card :bordered="false" class="main-card">
                    <template #title><span class="sg-card-title" style="--c: #1d7fc9"><BarChartOutlined /> Asistencia por asignatura</span></template>
                    
                    <div class="sg-scroll">
                        <a-empty v-if="!bySubject.length" :image="false" description="Sin registros" />
                        
                        <div v-for="s in bySubject" :key="s.subject" class="subject-card" :style="{ '--c': rateColor(s.rate) }">
                            <div class="subject-header">
                                <b><ReadOutlined /> {{ s.subject }}</b>
                                <span class="subject-rate">{{ s.rate }}%</span>
                            </div>
                            <a-progress 
                                :percent="s.rate" 
                                :show-info="false" 
                                :stroke-color="rateColor(s.rate)" 
                                :stroke-width="10"
                                class="subject-progress"
                            />
                            <div class="subject-stats">
                                <span><CloseCircleOutlined style="color: #e3262b" /> {{ s.falta }} falta(s)</span>
                                <span><ClockCircleOutlined style="color: #f39200" /> {{ s.retardo }} retardo(s)</span>
                                <span><CheckCircleOutlined style="color: #2e9b43" /> {{ s.total }} sesiones</span>
                            </div>
                        </div>
                    </div>
                </a-card>
            </a-col>
        </a-row>

        <!-- MODAL DE DETALLE (MEJORADO) -->
        <SgModal 
            v-model:open="modalOpen" 
            title="Detalle de asistencias" 
            subtitle="Faltas, retardos y justificantes del ciclo" 
            :icon="FileTextOutlined" 
            color="linear-gradient(135deg, #e3262b, #f39200)"
            width="min(960px, 96vw)" 
            :footer="false"
        >
            <!-- Resumen rápido en el modal -->
            <div class="modal-summary">
                <div class="ms-item" style="--c: #2e9b43">
                    <CheckCircleOutlined />
                    <div><b>{{ summary.presente }}</b><small>Asistencias</small></div>
                </div>
                <div class="ms-item" style="--c: #e3262b">
                    <CloseCircleOutlined />
                    <div><b>{{ summary.falta }}</b><small>Faltas</small></div>
                </div>
                <div class="ms-item" style="--c: #f39200">
                    <ClockCircleOutlined />
                    <div><b>{{ summary.retardo }}</b><small>Retardos</small></div>
                </div>
                <div class="ms-item" style="--c: #1d7fc9">
                    <SafetyCertificateOutlined />
                    <div><b>{{ summary.justificada }}</b><small>Justificadas</small></div>
                </div>
            </div>

            <!-- Tabla de detalle -->
            <div class="modal-table">
                <SgTable :columns="columns" :data-source="incidents" :row-key="(r) => r.date + r.subject + r.status" unit="registros" size="middle" empty-text="¡Asistencia perfecta, sin incidencias!">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'status'"><StatusTag :status="record.status" /></template>
                        <template v-else-if="column.key === 'notes'">{{ record.notes || '—' }}</template>
                    </template>
                </SgTable>
            </div>
        </SgModal>
    </AppLayout>
</template>

<style scoped>
/* ============ KPIs ============ */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 20px;
}
@media (max-width: 1024px) {
    .kpi-grid { grid-template-columns: repeat(2, 1fr); }
}

/* ============ TARJETAS PRINCIPALES ============ */
.main-card {
    border-radius: 16px;
    box-shadow: 0 4px 12px -4px rgba(0,0,0,0.03);
    border: 1px solid #e2e8f0;
    height: 100%;
    display: flex;
    flex-direction: column;
}

/* ============ GRÁFICA CENTRAL ============ */
.chart-container {
    display: flex;
    justify-content: center;
    padding: 20px 0;
}
.chart-center {
    display: flex;
    flex-direction: column;
    align-items: center;
}
.chart-rate {
    font-size: 42px;
    font-weight: 900;
    color: #0f172a;
    line-height: 1;
}
.chart-label {
    font-size: 13px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 4px;
}
.chart-sub {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 4px;
}

/* ============ LEYENDA ============ */
.chart-legend {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin: 16px 0 20px;
}
.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    background: color-mix(in srgb, var(--c) 8%, #fff);
    padding: 8px 12px;
    border-radius: 10px;
    border: 1px solid color-mix(in srgb, var(--c) 20%, #fff);
}
.legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--c);
}
.legend-label {
    font-size: 12.5px;
    font-weight: 600;
    color: #475569;
    flex: 1;
}
.legend-item b {
    font-size: 16px;
    color: #0f172a;
    font-weight: 800;
}

.btn-detail {
    margin-top: auto;
    height: 46px;
    border-radius: 10px;
    font-weight: 700;
    background: linear-gradient(135deg, #1d7fc9, #0f5a99);
    box-shadow: 0 4px 10px rgba(29, 127, 201, 0.3);
}

/* ============ TARJETAS DE ASIGNATURA ============ */
.subject-card {
    padding: 16px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    margin-bottom: 12px;
    background: linear-gradient(90deg, color-mix(in srgb, var(--c) 5%, #fff) 0%, #fff 100%);
    border-left: 4px solid var(--c);
    transition: all 0.2s;
}
.subject-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.04);
    transform: translateY(-2px);
}
.subject-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.subject-header b {
    color: #0f172a;
    font-size: 14.5px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.subject-rate {
    font-size: 20px;
    font-weight: 800;
    color: var(--c);
}
.subject-progress {
    margin-bottom: 8px;
}
.subject-stats {
    display: flex;
    gap: 16px;
    font-size: 12px;
    color: #64748b;
}
.subject-stats span {
    display: flex;
    align-items: center;
    gap: 4px;
}

/* ============ SCROLL ============ */
.sg-scroll {
    max-height: 500px;
    overflow-y: auto;
    padding-right: 4px;
}

/* ============ MODAL ============ */
.modal-summary {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 24px;
}
.ms-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: 10px;
    background: color-mix(in srgb, var(--c) 8%, #fff);
    border: 1px solid color-mix(in srgb, var(--c) 20%, #fff);
    color: var(--c);
    font-size: 22px;
}
.ms-item div {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
}
.ms-item b {
    color: #0f172a;
    font-size: 18px;
    font-weight: 800;
}
.ms-item small {
    font-size: 11px;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
}

.modal-table {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
}

@media (max-width: 768px) {
    .modal-summary { grid-template-columns: repeat(2, 1fr); }
}
</style>