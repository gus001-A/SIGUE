<script setup>
/**
 * Tabla estándar de SIGUE:
 *  - encabezados con icono (column.icon),
 *  - un filtro al pie de CADA columna (texto o lista) y botón para limpiar en la columna de acciones,
 *  - ocupa el alto disponible y hace scroll dentro de la tabla (no en la página),
 *  - paginación de Ant Design de 10 en 10.
 *
 * Columnas: { title, key, dataIndex?, icon?, filter?: 'text'|'select'|false, filterOptions?, filterValue?: (r) => any }
 * Las columnas con key 'actions' muestran el botón de limpiar filtros.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, useSlots, watch } from 'vue';
import { SearchOutlined, ClearOutlined } from '@ant-design/icons-vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    columns: { type: Array, required: true },
    dataSource: { type: Array, default: () => [] },
    rowKey: { type: [String, Function], default: 'id' },
    unit: { type: String, default: 'registros' },
    fill: { type: Boolean, default: true },      // ocupa el alto disponible del contenedor
    maxHeight: { type: Number, default: null },  // alto máximo del cuerpo cuando fill = false
    pageSize: { type: Number, default: 10 },
    filters: { type: Boolean, default: true },
    loading: { type: Boolean, default: false },
    size: { type: String, default: 'middle' },
    emptyText: { type: String, default: 'Sin registros con estos filtros' },
});
const slots = useSlots();

const norm = (v) => String(v ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
const valueOf = (col, r) => (col.filterValue ? col.filterValue(r) : col.dataIndex ? r[col.dataIndex] : r[col.key]);
const isActions = (col) => col.key === 'actions';
const filterType = (col) => {
    if (isActions(col) || col.filter === false) return false;
    return col.filter ?? 'text';
};

const f = reactive({});
const optionsFor = (col) => {
    if (col.filterOptions) return col.filterOptions;
    const seen = new Map();
    for (const r of props.dataSource) {
        const v = valueOf(col, r);
        if (v !== null && v !== undefined && v !== '' && !seen.has(v)) seen.set(v, { value: v, label: String(v) });
    }
    return [...seen.values()].sort((a, b) => a.label.localeCompare(b.label, 'es'));
};

const rows = computed(() => {
    const active = props.columns.filter((c) => filterType(c) && f[c.key] !== undefined && f[c.key] !== null && f[c.key] !== '');
    if (!active.length) return props.dataSource;
    return props.dataSource.filter((r) => active.every((c) => {
        const v = valueOf(c, r);
        if (filterType(c) === 'select') return Array.isArray(v) ? v.includes(f[c.key]) : v === f[c.key];
        return norm(Array.isArray(v) ? v.join(' ') : v).includes(norm(f[c.key]));
    }));
});
const hasFilters = computed(() => Object.values(f).some((v) => v !== undefined && v !== null && v !== ''));
const clear = () => Object.keys(f).forEach((k) => (f[k] = undefined));
defineExpose({ clear, rows });

// ---------- Alto disponible ----------
const root = ref(null);
const y = ref(undefined);
const desktop = ref(true);
let ro = null;
async function measure() {
    desktop.value = window.innerWidth >= 900;
    if (!props.fill || !desktop.value) {
        y.value = props.maxHeight ?? undefined;
        return;
    }
    await nextTick();
    const el = root.value;
    if (!el) return;
    const q = (s) => el.querySelector(s)?.offsetHeight ?? 0;
    const avail = el.clientHeight - q('.ant-table-header') - q('.ant-table-summary') - q('.ant-pagination') - (q('.ant-pagination') ? 14 : 2);
    y.value = Math.max(160, avail);
}
onMounted(() => {
    measure();
    window.addEventListener('resize', measure);
    if ('ResizeObserver' in window && root.value) {
        let h = 0;
        ro = new ResizeObserver(([e]) => {
            if (Math.abs(e.contentRect.height - h) > 2) { h = e.contentRect.height; measure(); }
        });
        ro.observe(root.value);
    }
});
onBeforeUnmount(() => { window.removeEventListener('resize', measure); ro?.disconnect(); });
watch(() => [rows.value.length > props.pageSize, props.columns.length], measure);

// Paginación de Ant Design (sin textos extra); se oculta si cabe todo en una página
const pagination = computed(() => rows.value.length > props.pageSize
    ? { pageSize: props.pageSize, showSizeChanger: false, position: ['bottomRight'] }
    : false);
const passSlots = computed(() => Object.keys(slots).filter((s) => !['headerCell', 'summary'].includes(s)));
</script>

<template>
    <div ref="root" class="sg-table" :class="{ 'is-fill': fill && desktop }" :style="y && fill && desktop ? { '--sg-y': y + 'px' } : null">
        <div class="sg-table__in">
            <a-table v-bind="$attrs" :columns="columns" :data-source="rows" :row-key="rowKey" :pagination="pagination" :loading="loading"
                :size="size" :scroll="desktop ? { y } : { x: 'max-content', y }" :table-layout="desktop ? 'fixed' : 'auto'" :locale="{ emptyText }">
                <template #headerCell="{ column }">
                    <span v-if="typeof column.title === 'string'" class="sg-th"><component :is="column.icon" v-if="column.icon" />{{ column.title }}</span>
                </template>
                <template v-for="name in passSlots" #[name]="scope" :key="name"><slot :name="name" v-bind="scope" /></template>
                <template v-if="filters" #summary>
                    <a-table-summary fixed="bottom">
                        <a-table-summary-row class="sg-fsum">
                            <a-table-summary-cell v-if="slots.expandedRowRender" :index="0" />
                            <a-table-summary-cell v-for="(c, i) in columns" :key="c.key ?? i" :index="slots.expandedRowRender ? i + 1 : i" :align="isActions(c) ? 'center' : undefined">
                                <a-select v-if="filterType(c) === 'select'" v-model:value="f[c.key]" :options="optionsFor(c)" allow-clear show-search
                                    option-filter-prop="label" placeholder="Todos" size="small" :dropdown-match-select-width="false" />
                                <a-input v-else-if="filterType(c) === 'text'" v-model:value="f[c.key]" allow-clear placeholder="Filtrar" size="small">
                                    <template #prefix><SearchOutlined /></template>
                                </a-input>
                                <a-tooltip v-else-if="isActions(c)" title="Limpiar filtros">
                                    <a-button size="small" class="sg-ab red" :disabled="!hasFilters" @click="clear"><ClearOutlined /></a-button>
                                </a-tooltip>
                            </a-table-summary-cell>
                        </a-table-summary-row>
                    </a-table-summary>
                </template>
            </a-table>
        </div>
    </div>
</template>
