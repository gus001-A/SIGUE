<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    GiftOutlined, PlusOutlined, NumberOutlined, UserOutlined, PercentageOutlined, CalendarOutlined, FlagOutlined, SettingOutlined,
    EditOutlined, DeleteOutlined, TagsOutlined, CheckCircleOutlined, StopOutlined, FileTextOutlined,
    CheckCircleFilled, CloseCircleFilled, ClockCircleOutlined
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHead from '@/Components/PageHead.vue';
import StatCard from '@/Components/StatCard.vue';
import SgTable from '@/Components/SgTable.vue';
import SgModal from '@/Components/SgModal.vue';
import { money, optionMap } from '@/lib/format';
import { confirmDelete } from '@/lib/notify';

const props = defineProps({ items: Array, options: Object });
const typeLabel = computed(() => optionMap(props.options.scholarshipTypes));

const open = ref(false);
const editing = ref(null);
const blank = { code: '', name: '', type: 'academica', student_id: null, percentage: null, amount: null, starts_on: null, ends_on: null, conditions: '', status: 'activa' };
const form = useForm({ ...blank });
const mode = ref('percentage');
const err = (k) => ({ 'validate-status': form.errors[k] ? 'error' : undefined, help: form.errors[k] });

function create() { editing.value = null; Object.assign(form, blank); mode.value = 'percentage'; form.clearErrors(); open.value = true; }
function edit(r) { editing.value = r; Object.assign(form, { ...blank, ...r }); mode.value = r.percentage ? 'percentage' : 'amount'; form.clearErrors(); open.value = true; }
function submit() {
    if (mode.value === 'percentage') form.amount = null; else form.percentage = null;
    const o = { preserveScroll: true, onSuccess: () => (open.value = false) };
    editing.value ? form.put(route('finanzas.becas.update', editing.value.id), o) : form.post(route('finanzas.becas.store'), o);
}
const remove = (r) => confirmDelete({ title: '¿Eliminar beca?', content: r.name, onOk: () => router.delete(route('finanzas.becas.destroy', r.id), { preserveScroll: true }) });
const filterOption = (input, opt) => opt.label.toLowerCase().includes(input.toLowerCase());

// Mapeo de estado (Estilo Incidencias)
const STATUS_MAP = {
    activa: { icon: CheckCircleFilled, color: '#2e9b43', bg: '#ecfdf3', label: 'Activa' },
    suspendida: { icon: CloseCircleFilled, color: '#e3262b', bg: '#fef2f2', label: 'Suspendida' },
    vencida: { icon: ClockCircleOutlined, color: '#f39200', bg: '#fff8eb', label: 'Vencida' },
    cancelada: { icon: CloseCircleFilled, color: '#64748b', bg: '#f1f5f9', label: 'Cancelada' }
};

// --- COLORES DINÁMICOS PARA TIPOS DE BECA ---
const getTypeStyle = (typeName) => {
    const text = (typeName || '').toLowerCase();
    if (text.includes('académica') || text.includes('academica')) return { c: '#1d7fc9', bg: '#eff6ff', border: '#bfdbfe' };
    if (text.includes('deportiva')) return { c: '#2e9b43', bg: '#ecfdf3', border: '#bbf7d0' };
    if (text.includes('cultural')) return { c: '#7c4ddb', bg: '#f3effa', border: '#e2d6f5' };
    if (text.includes('socioeconómica') || text.includes('economica')) return { c: '#f39200', bg: '#fff8eb', border: '#fbd38d' };
    if (text.includes('convenio')) return { c: '#e0457b', bg: '#fdf2f8', border: '#fbcfe8' };
    return { c: '#64748b', bg: '#f1f5f9', border: '#e2e8f0' }; // Por defecto
};

// --- NUEVO ORDEN DE COLUMNAS ---
const columns = [
    { title: 'Código', key: 'code', dataIndex: 'code', icon: NumberOutlined, width: 100 },
    { title: 'Beca', key: 'name', icon: GiftOutlined, filterValue: (r) => r.name },
    { title: 'Condición', key: 'conditions', icon: FileTextOutlined }, // <-- Movida aquí
    { title: 'Tipo', key: 'type', dataIndex: 'type', icon: TagsOutlined, filter: 'select', filterOptions: props.options.scholarshipTypes },
    { title: 'Beneficiario', key: 'student', icon: UserOutlined, filterValue: (r) => r.student ?? 'Sin asignar', width: 280 },
    { title: 'Descuento', key: 'benefit', icon: PercentageOutlined, filterValue: (r) => (r.percentage ? r.percentage + '%' : money(r.amount)) },
    { title: 'Vigencia', key: 'valid', icon: CalendarOutlined, filterValue: (r) => `${r.starts_on ?? ''} ${r.ends_on ?? ''}` },
    { title: 'Estado', key: 'status', icon: FlagOutlined, filter: 'select', filterValue: (r) => (r.active ? 'activa' : r.status), filterOptions: props.options.scholarshipStatuses, width: 130, align: 'center' },
    { title: 'Acciones', key: 'actions', icon: SettingOutlined, align: 'center' },
];

const getStatus = (record) => {
    if (record.active) return STATUS_MAP['activa'];
    return STATUS_MAP[record.status] || STATUS_MAP['cancelada'];
};
</script>

<template>
    <AppLayout title="Becas">
        <PageHead title="Becas y descuentos" subtitle="Se aplican automáticamente a colegiaturas e inscripciones al generar cargos" :icon="GiftOutlined">
            <template #actions><a-button type="primary" size="large" @click="create"><template #icon><PlusOutlined /></template>Nueva beca</a-button></template>
        </PageHead>

        <div class="sg-stats">
            <StatCard label="Becas" :value="items.length" :icon="GiftOutlined" color="pink" />
            <StatCard label="Vigentes" :value="items.filter((b) => b.active).length" :icon="CheckCircleOutlined" color="green" />
            <StatCard label="Suspendidas o vencidas" :value="items.filter((b) => !b.active).length" :icon="StopOutlined" color="red" />
        </div>

        <a-card :bordered="false" class="sg-fill">
            <SgTable :columns="columns" :data-source="items" unit="becas">
                <template #bodyCell="{ column, record }">
                    
                    <!-- Código -->
                    <template v-if="column.key === 'code'">
                        <span class="code-chip">{{ record.code }}</span>
                    </template>

                    <!-- Beca -->
                    <template v-else-if="column.key === 'name'">
                        <b class="beca-name">{{ record.name }}</b>
                    </template>

                    <!-- Condición -->
                    <template v-else-if="column.key === 'conditions'">
                        <span class="condition-text">{{ record.conditions || '—' }}</span>
                    </template>

                    <!-- Tipo (Colores dinámicos) -->
                    <template v-else-if="column.key === 'type'">
                        <span class="type-chip" :style="{ 
                            background: getTypeStyle(typeLabel[record.type]).bg, 
                            color: getTypeStyle(typeLabel[record.type]).c,
                            borderColor: getTypeStyle(typeLabel[record.type]).border
                        }">
                            {{ typeLabel[record.type] }}
                        </span>
                    </template>

                    <!-- Beneficiario (Completo) -->
                    <template v-else-if="column.key === 'student'">
                        <Link v-if="record.student_id" :href="route('alumnos.show', record.student_id)" class="sg-who beneficiary-cell">
                            <span class="sg-av"><UserOutlined /></span>
                            <span class="beneficiary-text"><b>{{ record.student }}</b></span>
                        </Link>
                        <span v-else class="sg-faint">Sin asignar</span>
                    </template>

                    <!-- Descuento -->
                    <template v-else-if="column.key === 'benefit'">
                        <span class="discount-badge">
                            <PercentageOutlined v-if="record.percentage" />
                            {{ record.percentage ? record.percentage + '%' : money(record.amount) }}
                        </span>
                    </template>

                    <!-- Vigencia -->
                    <template v-else-if="column.key === 'valid'">
                        <div class="validity-cell">
                            <span><CalendarOutlined /> Inicio: {{ record.starts_on || '—' }}</span>
                            <span><CalendarOutlined /> Fin: {{ record.ends_on || '—' }}</span>
                        </div>
                    </template>

                    <!-- Estado -->
                    <template v-else-if="column.key === 'status'">
                        <span class="status-pill" :style="{ background: getStatus(record).bg, color: getStatus(record).color }">
                            <component :is="getStatus(record).icon" class="status-icon" />
                            <span class="status-text">{{ getStatus(record).label }}</span>
                        </span>
                    </template>

                    <!-- Acciones -->
                    <template v-else-if="column.key === 'actions'">
                        <div class="sg-actions">
                            <a-tooltip title="Editar"><a-button class="sg-ab navy" @click="edit(record)"><EditOutlined /></a-button></a-tooltip>
                            <a-tooltip title="Eliminar"><a-button class="sg-ab red" @click="remove(record)"><DeleteOutlined /></a-button></a-tooltip>
                        </div>
                    </template>
                </template>
            </SgTable>
        </a-card>

        <SgModal v-model:open="open" :title="editing ? 'Editar beca' : 'Nueva beca'" :subtitle="editing ? editing.code : 'Descuento por porcentaje o monto fijo'" :icon="GiftOutlined"
            color="linear-gradient(135deg, #6b4c8c, #e0457b)" :loading="form.processing" @ok="submit">
            <a-form layout="vertical" class="sg-grid-2">
                <a-form-item label="Código" required v-bind="err('code')">
                    <a-input v-model:value="form.code" size="large"><template #prefix><NumberOutlined style="color: #6b4c8c" /></template></a-input>
                </a-form-item>
                <a-form-item label="Nombre" required v-bind="err('name')">
                    <a-input v-model:value="form.name" size="large"><template #prefix><GiftOutlined style="color: #e0457b" /></template></a-input>
                </a-form-item>
                
                <a-form-item label="Tipo" v-bind="err('type')">
                    <a-select v-model:value="form.type" :options="options.scholarshipTypes" size="large" />
                </a-form-item>
                <a-form-item label="Beneficiario" v-bind="err('student_id')">
                    <a-select v-model:value="form.student_id" allow-clear show-search :filter-option="filterOption" :options="options.students" size="large" />
                </a-form-item>
                
                <a-form-item label="Tipo de beneficio" class="full">
                    <a-segmented v-model:value="mode" block :options="[{ value: 'percentage', label: 'Porcentaje (%)' }, { value: 'amount', label: 'Monto ($)' }]" size="large" />
                </a-form-item>
                
                <a-form-item v-if="mode === 'percentage'" label="Porcentaje" v-bind="err('percentage')">
                    <a-input-number v-model:value="form.percentage" :min="0.01" :max="100" addon-after="%" size="large" style="width: 100%" />
                </a-form-item>
                <a-form-item v-else label="Monto" v-bind="err('amount')">
                    <a-input-number v-model:value="form.amount" :min="0.01" :precision="2" prefix="$" size="large" style="width: 100%" />
                </a-form-item>
                
                <a-form-item label="Inicio de vigencia" v-bind="err('starts_on')">
                    <a-date-picker v-model:value="form.starts_on" value-format="YYYY-MM-DD" format="DD/MM/YYYY" size="large" style="width: 100%" />
                </a-form-item>
                <a-form-item label="Fin de vigencia" v-bind="err('ends_on')">
                    <a-date-picker v-model:value="form.ends_on" value-format="YYYY-MM-DD" format="DD/MM/YYYY" size="large" style="width: 100%" />
                </a-form-item>
                
                <a-form-item label="Estado" v-bind="err('status')">
                    <a-select v-model:value="form.status" :options="options.scholarshipStatuses" size="large" />
                </a-form-item>
                
                <a-form-item label="Condiciones" class="full" v-bind="err('conditions')">
                    <a-input v-model:value="form.conditions" placeholder="Promedio mínimo, ingreso socioeconómico, etc." size="large">
                        <template #prefix><FileTextOutlined style="color: #1d7fc9" /></template>
                    </a-input>
                </a-form-item>
            </a-form>
        </SgModal>
    </AppLayout>
</template>

<style scoped>
/* ============ CHIPS Y ETIQUETAS ============ */
.code-chip {
    font-family: 'Courier New', Courier, monospace;
    font-weight: 800;
    color: #6b4c8c;
    background: #f3effa;
    padding: 2px 6px;
    border-radius: 6px;
    font-size: 12px;
    border: 1px solid #e2d6f5;
    white-space: nowrap;
}

.beca-name {
    font-size: 14px;
    color: #0f172a;
}

.type-chip {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    border: 1px solid;
    white-space: nowrap;
}

.condition-text {
    font-size: 13px;
    color: #475569;
    line-height: 1.4;
    display: block;
}

.discount-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #fdf2f8;
    color: #e0457b;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 800;
    border: 1px solid #fbcfe8;
    white-space: nowrap;
}

.validity-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 12px;
    color: #475569;
    white-space: nowrap;
}
.validity-cell .anticon {
    color: #1d7fc9;
    margin-right: 4px;
}

/* Beneficiario completo */
.beneficiary-cell {
    width: 100%;
    overflow: hidden;
    display: flex;
    align-items: center;
    gap: 8px;
}
.beneficiary-text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: block;
}
.beneficiary-text b {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* ============ ESTADO ============ */
.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    border: 1px solid color-mix(in srgb, currentColor 20%, transparent);
    white-space: nowrap;
}
.status-icon {
    font-size: 13px;
}
</style>