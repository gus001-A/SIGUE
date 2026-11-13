<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import {
    PlusOutlined, BankOutlined, CalendarOutlined, ScheduleOutlined, RiseOutlined, ApartmentOutlined, BookOutlined, FileOutlined,
    EditOutlined, DeleteOutlined, SettingOutlined, FontSizeOutlined, NumberOutlined, CheckSquareOutlined, UnorderedListOutlined, AppstoreOutlined, FileTextOutlined, DatabaseOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHead from '@/Components/PageHead.vue';
import SgTable from '@/Components/SgTable.vue';
import SgModal from '@/Components/SgModal.vue';
import AddressFields from '@/Components/AddressFields.vue';
import { confirmDelete } from '@/lib/notify';
import { statusLabel } from '@/lib/format';

const props = defineProps({ catalog: Object, fields: Array, rows: Array, sources: Object, filters: Object, catalogs: Array });
const ICONS = { bank: BankOutlined, calendar: CalendarOutlined, schedule: ScheduleOutlined, rise: RiseOutlined, apartment: ApartmentOutlined, book: BookOutlined, file: FileOutlined };
const TYPE_ICON = { text: FontSizeOutlined, number: NumberOutlined, date: CalendarOutlined, switch: CheckSquareOutlined, select: UnorderedListOutlined, textarea: FontSizeOutlined };

// Catálogos generales agrupados en pestañas dentro de una sola página
const TABS = [
    { key: 'ciclos', label: 'Ciclos escolares', icon: CalendarOutlined, color: '#14a39a' },
    { key: 'periodos', label: 'Periodos de evaluación', icon: ScheduleOutlined, color: '#7c4ddb' },
    { key: 'grados', label: 'Grados', icon: RiseOutlined, color: '#f39200' },
    { key: 'tipos-documento', label: 'Tipos de documento', icon: FileTextOutlined, color: '#e0457b' },
];
const inTabs = computed(() => TABS.some((t) => t.key === props.catalog.key));
const goTab = (k) => router.visit(route('catalogos.index', k), { preserveScroll: true });

const fieldByName = Object.fromEntries(props.fields.map((f) => [f.name, f]));
const optionsFor = (f) => f.options ?? props.sources[f.source] ?? [];
const labelOf = (f, v) => optionsFor(f).find((o) => String(o.value) === String(v))?.label;

function display(f, v) {
    if (!f) return v;
    if (f.type === 'switch') return v ? 'Sí' : 'No';
    if (f.type === 'select') return v === null || v === undefined ? '—' : (labelOf(f, v) ?? statusLabel(v));
    if (f.type === 'date') return v ? v.split('-').reverse().join('/') : '—';
    return v ?? '—';
}

const columns = computed(() => [
    ...props.catalog.columns.map((c) => {
        const f = fieldByName[c];
        return {
            title: f?.label ?? c, key: c, icon: TYPE_ICON[f?.type] ?? FontSizeOutlined,
            filter: ['select', 'switch'].includes(f?.type) ? 'select' : 'text',
            filterValue: (r) => display(f, r[c]),
        };
    }),
    { title: 'Acciones', key: 'actions', icon: SettingOutlined, width: 110, align: 'center' },
]);

const open = ref(false);
const editing = ref(null);
const blank = () => Object.fromEntries(props.fields.map((f) => [f.name, f.default ?? (f.type === 'switch' ? false : null)]));
const form = useForm(blank());
function create() { editing.value = null; Object.assign(form, blank()); form.clearErrors(); open.value = true; }
function edit(r) { editing.value = r; Object.assign(form, blank(), Object.fromEntries(props.fields.map((f) => [f.name, r[f.name]]))); form.clearErrors(); open.value = true; }
function submit() {
    const o = { preserveScroll: true, onSuccess: () => (open.value = false) };
    editing.value ? form.put(route('catalogos.update', [props.catalog.key, editing.value.id]), o) : form.post(route('catalogos.store', props.catalog.key), o);
}
const remove = (r) => confirmDelete({ title: `¿Eliminar ${props.catalog.singular}?`, content: r.name, onOk: () => router.delete(route('catalogos.destroy', [props.catalog.key, r.id]), { preserveScroll: true }) });

// Los campos de domicilio (tipo 'address') se capturan juntos con autocompletado por CP
const formFields = computed(() => props.fields.filter((f) => f.type !== 'address'));
const hasAddress = computed(() => props.fields.some((f) => f.type === 'address'));

// Columnas del formulario: 2 o 3 según el número de campos (filas parejas)
const gridClass = computed(() => 'sg-grid-2'); // siempre dos columnas parejas
// En dos columnas con número impar de campos, el último ocupa todo el ancho
const fieldClass = (i) => (gridClass.value === 'sg-grid-2' && formFields.value.length % 2 === 1 && i === formFields.value.length - 1 ? 'full' : '');
const width = computed(() => (hasAddress.value ? 'min(860px, 96vw)' : 'min(680px, 96vw)'));
</script>

<template>
    <AppLayout :title="catalog.title">
        <PageHead :title="inTabs ? 'Catálogos' : catalog.title" :subtitle="inTabs ? 'Ciclos, periodos de evaluación, grados y tipos de documento de la institución' : catalog.subtitle"
            :icon="inTabs ? DatabaseOutlined : ICONS[catalog.icon]" back @back="router.visit(route('configuracion'))">
            <template #actions>
                <a-button type="primary" size="large" @click="create"><template #icon><PlusOutlined /></template>Agregar {{ catalog.singular }}</a-button>
            </template>
        </PageHead>

        <nav v-if="inTabs" class="cat-tabs">
            <button v-for="t in TABS" :key="t.key" type="button" class="cat-tab" :class="{ on: t.key === catalog.key }" :style="{ '--c': t.color }" @click="t.key !== catalog.key && goTab(t.key)">
                <span class="cat-tab__ic"><component :is="t.icon" /></span>
                <span class="cat-tab__txt"><b>{{ t.label }}</b><small v-if="t.key === catalog.key">{{ rows.length }} registro{{ rows.length === 1 ? '' : 's' }}</small></span>
            </button>
        </nav>

        <a-card :bordered="false" class="sg-fill">
            <SgTable :columns="columns" :data-source="rows" :unit="catalog.title.toLowerCase()">
                <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'actions'">
                        <div class="sg-actions">
                            <a-tooltip title="Editar"><a-button class="sg-ab navy" @click="edit(record)"><EditOutlined /></a-button></a-tooltip>
                            <a-tooltip title="Eliminar"><a-button class="sg-ab red" @click="remove(record)"><DeleteOutlined /></a-button></a-tooltip>
                        </div>
                    </template>
                    <template v-else-if="fieldByName[column.key]?.type === 'switch'">
                        <span class="sg-chip" :style="{ '--c': record[column.key] ? '#2e9b43' : '#94a3b8' }">{{ record[column.key] ? 'Sí' : 'No' }}</span>
                    </template>
                    <template v-else-if="column.key === 'name'"><b>{{ record.name }}</b></template>
                    <template v-else-if="fieldByName[column.key]?.type === 'select' && record[column.key] !== null">
                        <span class="sg-chip" style="--c: #1d7fc9">{{ display(fieldByName[column.key], record[column.key]) }}</span>
                    </template>
                    <template v-else>{{ display(fieldByName[column.key], record[column.key]) }}</template>
                </template>
            </SgTable>
        </a-card>

        <SgModal v-model:open="open" :title="(editing ? 'Editar ' : 'Nuevo(a) ') + catalog.singular" :subtitle="catalog.title" :icon="ICONS[catalog.icon]"
            :width="width" :loading="form.processing" @ok="submit">
            <a-form layout="vertical" :class="gridClass">
                <a-form-item v-for="(f, i) in formFields" :key="f.name" :class="fieldClass(i)" :label="f.label" :required="f.required"
                    :validate-status="form.errors[f.name] ? 'error' : undefined" :help="form.errors[f.name]">
                    <a-input v-if="f.type === 'text' && f.digits" v-digits="f.digits" v-model:value="form[f.name]" :placeholder="f.placeholder" />
                    <a-input v-else-if="f.type === 'text'" v-model:value="form[f.name]" :placeholder="f.placeholder" />
                    <a-input-number v-else-if="f.type === 'number'" v-model:value="form[f.name]" />
                    <a-date-picker v-else-if="f.type === 'date'" v-model:value="form[f.name]" value-format="YYYY-MM-DD" format="DD/MM/YYYY" />
                    <a-select v-else-if="f.type === 'select'" v-model:value="form[f.name]" :options="optionsFor(f)" allow-clear />
                    <a-textarea v-else-if="f.type === 'textarea'" v-model:value="form[f.name]" :rows="3" />
                    <a-switch v-else-if="f.type === 'switch'" v-model:checked="form[f.name]" checked-children="Sí" un-checked-children="No" />
                </a-form-item>
                <AddressFields v-if="hasAddress" :form="form" :errors="form.errors" class="full" />
            </a-form>
        </SgModal>
    </AppLayout>
</template>

<style scoped>
.cat-tabs { flex: none; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-bottom: 12px; }
.cat-tab { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 14px; border: 1px solid #e2e8f0; background: #fff; cursor: pointer; text-align: left;
    font-family: inherit; transition: border-color .15s, box-shadow .15s, transform .15s; min-width: 0; }
.cat-tab:hover { border-color: color-mix(in srgb, var(--c) 45%, #fff); transform: translateY(-1px); }
.cat-tab.on { border-color: var(--c); box-shadow: 0 0 0 3px color-mix(in srgb, var(--c) 14%, transparent), 0 10px 20px -16px var(--c); }
.cat-tab__ic { width: 36px; height: 36px; border-radius: 10px; flex: none; display: grid; place-items: center; font-size: 16px; color: var(--c); background: color-mix(in srgb, var(--c) 12%, #fff); }
.cat-tab.on .cat-tab__ic { color: #fff; background: var(--c); }
.cat-tab__txt { display: flex; flex-direction: column; min-width: 0; }
.cat-tab__txt b { font-size: 13px; color: #0b2f55; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cat-tab__txt small { font-size: 11px; color: var(--c); font-weight: 700; }
@media (max-width: 760px) { .cat-tabs { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
