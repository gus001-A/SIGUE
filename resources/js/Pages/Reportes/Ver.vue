<script setup>
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import dayjs from 'dayjs';
import {
    ArrowLeftOutlined, BarChartOutlined, TrophyOutlined, CalendarOutlined, UserOutlined, WarningOutlined, SolutionOutlined,
    DollarOutlined, BankOutlined, IdcardOutlined, LineChartOutlined, TeamOutlined, WalletOutlined, FileTextOutlined,
    FilterOutlined, ReloadOutlined, FilePdfOutlined, FileExcelOutlined, AppstoreOutlined, ScheduleOutlined, ClockCircleOutlined,
    InfoCircleOutlined, DatabaseOutlined, CalculatorOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHead from '@/Components/PageHead.vue';
import SgTable from '@/Components/SgTable.vue';
import { money, gradeColor, STATUS } from '@/lib/format';

const props = defineProps({
    report: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    missing: { type: Array, default: () => [] },
    data: { type: Object, default: null },
    generatedAt: { type: String, default: null },
    generatedBy: { type: String, default: '' },
    options: { type: Object, default: () => ({}) },
});

const CAT = {
    'Académicos': ['#2e9b43', '#1f7a32'], 'Asistencia': ['#1d7fc9', '#0f5a99'], 'Docentes': ['#7c4ddb', '#5b34ad'],
    'Conductual': ['#e3262b', '#b91c1c'], 'Administrativo': ['#0f5a99', '#0b3f6e'], 'Financiero': ['#f39200', '#c27200'],
    'Institucional': ['#14a39a', '#0e7d76'],
};
const ICON = {
    calificaciones: TrophyOutlined, kardex: IdcardOutlined, promedios: LineChartOutlined, asistencia: CalendarOutlined, docentes: TeamOutlined,
    incidencias: WarningOutlined, inscripciones: SolutionOutlined, pagos: DollarOutlined, adeudos: WalletOutlined, estadisticas: BarChartOutlined,
};
const [acc, acc2] = CAT[props.report.category] ?? ['#1d7fc9', '#0f5a99'];
const has = (p) => props.report.params.includes(p);

// ---------- Filtros ----------
const form = reactive({
    group_id: props.filters.group_id, period_id: props.filters.period_id, student_id: props.filters.student_id,
    from: props.filters.from, to: props.filters.to,
});
const query = () => Object.fromEntries(Object.entries(form).filter(([k, v]) => has(k === 'to' ? 'from' : k) && v !== undefined && v !== null && v !== ''));
const generate = () => router.get(route('reportes.ver', props.report.key), query(), { preserveState: false });
const exportTo = (fmt) => {
    const url = route('reportes.export', { report: props.report.key, format: fmt, ...query() });
    window.location.href = url; // todos los formatos se descargan
};
const filterOption = (input, opt) => opt.label.toLowerCase().includes(input.toLowerCase());
const range = computed(() => (form.from && form.to ? [dayjs(form.from), dayjs(form.to)] : null));
const setRange = (v) => { form.from = v?.[0]?.format('YYYY-MM-DD'); form.to = v?.[1]?.format('YYYY-MM-DD'); };

// ---------- Tabla ----------
const PAL = ['#1d7fc9', '#2e9b43', '#7c4ddb', '#f39200', '#14a39a', '#e3262b', '#0f5a99', '#e0457b'];
const MONEY = ['importe', 'total', 'pagado', 'saldo'];
const headings = computed(() => props.data?.headings ?? []);
const rows = computed(() => (props.data?.rows ?? []).map((r, i) => ({ _k: i, ...r })));
const isMoneyCol = (i) => MONEY.includes(String(headings.value[i] ?? '').toLowerCase());
const isGradeCol = (i) => props.report.key === 'calificaciones' ? i >= 2 : ['calificación', 'promedio'].includes(String(headings.value[i] ?? '').toLowerCase());

const isPersonCol = (i) => /^(alumno|docente|tutor|nombre|padre|responsable)/i.test(String(headings.value[i] ?? ''));
const isDateCol = (i) => /fecha|d[ií]a/i.test(String(headings.value[i] ?? ''));
const columns = computed(() => headings.value.map((h, i) => {
    const distinct = new Set(rows.value.map((r) => r[i])).size;
    const numeric = rows.value.length && rows.value.every((r) => r[i] === null || r[i] === '—' || !isNaN(Number(r[i])));
    return {
        title: h, key: String(i), dataIndex: String(i), _c: PAL[i % PAL.length],
        align: (numeric && !isCodeCol(i)) || isGradeCol(i) ? 'center' : isMoneyCol(i) ? 'right' : undefined,
        filter: distinct > 1 && distinct <= 8 && rows.value.length > 8 ? 'select' : 'text',
        width: isPersonCol(i) ? 250 : h === 'Descripción' ? 260 : isDateCol(i) ? 140 : isCodeCol(i) ? 120 : undefined,
    };
}));
const initials = (n) => String(n ?? '').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
const avColor = (n) => PAL[[...String(n ?? '')].reduce((a, c) => a + c.charCodeAt(0), 0) % PAL.length];
const pctNum = (v) => Math.max(0, Math.min(100, parseFloat(String(v))));

const statusOf = (v) => {
    if (typeof v !== 'string') return null;
    const k = v.toLowerCase();
    if (k === 'vencido') return ['Vencido', 'red'];
    return STATUS[k] ?? null;
};
const isCodeCol = (i) => /matr[ií]cula|folio|empleado/i.test(String(headings.value[i] ?? ''));
const cellType = (v, i) => {
    if (v === null || v === undefined || v === '' || v === '—') return 'empty';
    if (isCodeCol(i)) return 'code';
    if (isPersonCol(i) && typeof v === 'string') return 'person';
    if (isDateCol(i) && typeof v === 'string' && /\d/.test(v)) return 'date';
    if (isMoneyCol(i) && !isNaN(Number(v))) return 'money';
    if (isGradeCol(i) && !isNaN(Number(v))) return 'grade';
    if (typeof v === 'number' || (typeof v === 'string' && /^-?\d+(\.\d+)?$/.test(v))) return 'num';
    if (typeof v === 'string' && /%$/.test(v.trim())) return 'pct';
    if (statusOf(v)) return 'tag';
    return 'text';
};

// ---------- Totales ----------
const kpis = computed(() => {
    if (!props.data) return [];
    const list = [];
    headings.value.forEach((h, i) => {
        if (isMoneyCol(i)) list.push({ label: `Suma de ${h.toLowerCase()}`, value: money(rows.value.reduce((s, r) => s + (Number(r[i]) || 0), 0)), icon: DollarOutlined, c: '#1faf5c', note: 'suma de la columna', base: !list.length });
    });
    const gi = headings.value.findIndex((h) => h === 'Promedio' || h === 'Calificación');
    if (gi >= 0) {
        const vals = rows.value.map((r) => Number(r[gi])).filter((v) => !isNaN(v) && v > 0);
        if (vals.length) list.push({ label: 'Promedio del reporte', value: (vals.reduce((a, b) => a + b, 0) / vals.length).toFixed(2), icon: CalculatorOutlined, c: '#7c4ddb', note: 'de los registros con calificación', base: !list.length });
    }
    return list.slice(0, 5);
});
const generatedAt = computed(() => props.generatedAt ? dayjs(props.generatedAt).format('DD/MM/YYYY HH:mm') : '');
</script>

<template>
    <AppLayout :title="report.title">
        <div class="rv" :style="{ '--acc': acc, '--acc2': acc2 }">
            <PageHead :title="report.title" :subtitle="`${report.category} · ${report.description ?? 'Consulta, filtra y exporta la información'}`"
                :icon="ICON[report.key] ?? FileTextOutlined" back @back="router.visit(route('reportes.index'))">
                <template v-if="data" #actions>
                    <a-button size="large" class="rp-btn pdf" @click="exportTo('pdf')"><template #icon><FilePdfOutlined /></template>Descargar PDF</a-button>
                    <a-button size="large" class="rp-btn xls" @click="exportTo('xlsx')"><template #icon><FileExcelOutlined /></template>Descargar Excel</a-button>
                    <a-button size="large" class="rp-btn csv" @click="exportTo('csv')"><template #icon><FileTextOutlined /></template>CSV</a-button>
                </template>
            </PageHead>

            <!-- Filtros en una sola línea (barra Ventrack) -->
            <div class="vt-toolbar">
                <span class="vt-toolbar-label"><FilterOutlined /> Filtros</span>
                <a-select v-if="has('group_id')" v-model:value="form.group_id" :options="options.groups" allow-clear show-search
                    option-filter-prop="label" placeholder="Grupo (todos)" class="rv-sel">
                    <template #suffixIcon><AppstoreOutlined /></template>
                </a-select>
                <a-select v-if="has('period_id')" v-model:value="form.period_id" :options="options.periods" placeholder="Periodo" class="rv-sel">
                    <template #suffixIcon><ScheduleOutlined /></template>
                </a-select>
                <a-select v-if="has('student_id')" v-model:value="form.student_id" :options="options.students" show-search
                    :filter-option="filterOption" placeholder="Busca un alumno" class="rv-sel rv-sel--wide">
                    <template #suffixIcon><UserOutlined /></template>
                </a-select>
                <template v-if="has('from')">
                    <span class="vt-toolbar-label"><CalendarOutlined /> Periodo</span>
                    <a-range-picker :value="range" format="DD/MM/YYYY" :allow-clear="false" @change="setRange" />
                </template>
                <span v-if="!report.params.length" class="vt-muted">Este reporte no tiene filtros.</span>
                <a-button v-if="report.params.length" type="primary" class="rv-gen" @click="generate"><template #icon><ReloadOutlined /></template>Generar</a-button>
            </div>

            <!-- Faltan parámetros -->
            <a-alert v-if="!data" type="info" show-icon message="Completa los filtros para generar el reporte">
                <template #description>Selecciona {{ missing.join(' y ') }} y presiona <b>Generar</b>.</template>
            </a-alert>

            <!-- Vista previa -->
            <div v-else class="rp-prev">
                <div class="rp-prev-h">
                    <div class="rp-prev-t">
                        <h2>{{ data.title ?? report.title }}</h2>
                        <p><template v-if="data.subtitle">{{ data.subtitle }} · </template><b>Generado por:</b> {{ generatedBy }} · <ClockCircleOutlined /> {{ generatedAt }} · <b>Registros:</b> {{ rows.length }}</p>
                    </div>
                    <span class="rp-prev-tag"><InfoCircleOutlined /> Vista previa</span>
                </div>
                <div v-if="kpis.length || data.footer" class="rp-kpis" :style="{ '--n': Math.max(4, kpis.length + (data.footer ? 2 : 0)) }">
                    <div v-for="(k, i) in kpis" :key="k.label" class="rp-kpi" :class="{ base: k.base }" :style="{ '--c': k.c, animationDelay: i * 0.05 + 's' }">
                        <div class="rp-kpi-top"><span class="rp-kpi-etq">{{ k.label }}</span><i class="rp-kpi-ic"><component :is="k.icon" /></i></div>
                        <strong class="rp-kpi-val">{{ k.value }}</strong>
                        <small class="rp-kpi-nota">{{ k.note }}</small>
                    </div>
                    <div v-if="data.footer" class="rp-kpi rp-kpi--foot" :style="{ '--c': acc2 }">
                        <div class="rp-kpi-top"><span class="rp-kpi-etq">Resumen</span><i class="rp-kpi-ic"><InfoCircleOutlined /></i></div>
                        <span class="rp-kpi-foot">{{ data.footer }}</span>
                    </div>
                </div>

                <div class="rp-table">
                    <SgTable :columns="columns" :data-source="rows" row-key="_k" size="small" unit="registros" :fill="false" :max-height="520" empty-text="No hay información para los filtros seleccionados.">
                        <template #bodyCell="{ column, text }">
                            <template v-if="column.dataIndex !== undefined">
                                <span v-if="cellType(text, +column.key) === 'empty'" class="c-empty">—</span>
                                <span v-else-if="cellType(text, +column.key) === 'code'" class="c-code">{{ text }}</span>
                                <span v-else-if="cellType(text, +column.key) === 'person'" class="c-person">
                                    <i :style="{ background: avColor(text) }">{{ initials(text) }}</i><b>{{ text }}</b>
                                </span>
                                <span v-else-if="cellType(text, +column.key) === 'date'" class="c-date"><CalendarOutlined /> {{ text }}</span>
                                <span v-else-if="cellType(text, +column.key) === 'money'" class="c-money">{{ money(text) }}</span>
                                <span v-else-if="cellType(text, +column.key) === 'grade'" class="c-grade" :style="{ '--g': gradeColor(Number(text)) }">
                                    <b>{{ Number(text).toFixed(1) }}</b><span class="c-grade__bar"><i :style="{ width: Math.min(100, Number(text) * 10) + '%' }" /></span>
                                </span>
                                <span v-else-if="cellType(text, +column.key) === 'num'" class="c-num">{{ Number(text).toLocaleString('es-MX') }}</span>
                                <span v-else-if="cellType(text, +column.key) === 'pct'" class="c-pct" :style="{ '--p': pctNum(text) >= 90 ? '#1faf5c' : pctNum(text) >= 80 ? '#f0a202' : '#d9363e' }">
                                    <b>{{ text }}</b><span class="c-pct__bar"><i :style="{ width: pctNum(text) + '%' }" /></span>
                                </span>
                                <a-tag v-else-if="cellType(text, +column.key) === 'tag'" :color="statusOf(text)[1]">{{ statusOf(text)[0] }}</a-tag>
                                <span v-else class="c-text">{{ text }}</span>
                            </template>
                        </template>
                    </SgTable>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.rv { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; overflow-y: auto; overflow-x: hidden; padding-right: 2px; }
.rv > * { flex: none; }

/* Botones de exportación (Ventrack) */
.rp-btn { color: #fff !important; border: 0 !important; font-weight: 600; }
.rp-btn.pdf { background: #d9363e !important; }
.rp-btn.xls { background: #1d8a4e !important; }
.rp-btn.csv { background: #0461d8 !important; }
.rp-btn:hover { filter: brightness(1.08); transform: translateY(-2px); }

/* Barra de filtros */
.vt-toolbar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; background: #fff; border: 1px solid #e1e8f0; border-radius: 14px; padding: 10px 14px; margin-bottom: 12px; }
.vt-toolbar-label { display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-size: 12.5px; letter-spacing: .04em; text-transform: uppercase; color: #0461d8; white-space: nowrap; }
.vt-muted { color: #5d7085; font-size: 13px; }
.rv-sel { width: 200px; }
.rv-sel--wide { width: 340px; }
.rv-gen { margin-left: auto; font-weight: 600; }

/* Vista previa */
.rp-prev { background: #fff; border: 1px solid #e1e8f0; border-radius: 14px; overflow: hidden; }
.rp-prev-h { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 14px 18px 0; }
.rp-prev-t { min-width: 0; }
.rp-prev-h h2 { margin: 0; font-size: 18px; font-weight: 800; color: #0a1f44; }
.rp-prev-h p { margin: 2px 0 0; font-size: 12.5px; color: #5d7085; }
.rp-prev-tag { flex: none; font-size: 12px; font-weight: 600; color: #0461d8; background: #eef5ff; padding: 4px 10px; border-radius: 99px; display: inline-flex; gap: 5px; align-items: center; }
.rp-kpis { display: grid; grid-template-columns: repeat(var(--n), minmax(0, 1fr)); gap: 12px; padding: 14px 18px 18px; }
.rp-kpi { min-width: 0; padding: 14px 16px 12px; border-radius: 14px; background: #fff; border: 1px solid #e1e8f0; box-shadow: 0 1px 2px rgba(10, 31, 68, .04);
    transition: transform .18s, box-shadow .18s, border-color .18s; }
.rp-kpi:hover { transform: translateY(-3px); border-color: color-mix(in srgb, var(--c) 35%, #e1e8f0); box-shadow: 0 12px 24px color-mix(in srgb, var(--c) 16%, transparent); }
.rp-kpi.base { background: linear-gradient(135deg, var(--c), color-mix(in srgb, var(--c) 70%, #0a1f44)); border-color: transparent; }
.rp-kpi.base .rp-kpi-etq, .rp-kpi.base .rp-kpi-nota { color: rgba(255, 255, 255, .85); }
.rp-kpi.base .rp-kpi-val { color: #fff; }
.rp-kpi.base .rp-kpi-ic { color: #fff; background: rgba(255, 255, 255, .2); }
.rp-kpi-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.rp-kpi-etq { font-size: 11.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #5d7085; }
.rp-kpi-ic { flex: none; width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; font-size: 17px; font-style: normal; color: var(--c); background: color-mix(in srgb, var(--c) 13%, #fff); }
.rp-kpi-val { display: block; margin: 4px 0 6px; font-size: 26px; font-weight: 800; line-height: 1; color: #0a1f44; }
.rp-kpi-nota { display: block; font-size: 11.5px; color: #6b7a90; }
.rp-kpi--foot { grid-column: span 2; }
.rp-kpi-foot { display: block; margin-top: 8px; font-size: 13px; font-weight: 600; color: #0a1f44; line-height: 1.4; }
.rp-table { padding: 0 18px 16px; }

/* Tabla del reporte: encabezado con el color de la categoría */
.rp-prev-h + .rp-table { padding-top: 14px; }
.rp-table :deep(.ant-table) { border-top: 3px solid var(--acc) !important; }
.rp-table :deep(.ant-table-thead > tr > th) { background: color-mix(in srgb, var(--acc) 6%, #f7fafc) !important; color: color-mix(in srgb, var(--acc) 60%, #0a1f44) !important; }
.rp-table :deep(.ant-table-thead .sg-th .anticon) { color: var(--acc); }
.rp-table :deep(.ant-table-tbody > tr.ant-table-row > td) { padding-top: 9px !important; padding-bottom: 9px !important; border-bottom: 1px solid #eef2f7 !important; }
.rp-table :deep(.ant-table-tbody > tr.ant-table-row:nth-child(odd) > td) { background: #fbfcfe; }
.rp-table :deep(.ant-table-tbody > tr.ant-table-row:hover > td) { background: color-mix(in srgb, var(--acc) 6%, #fff) !important; }

/* Celdas */
.c-empty { color: #cbd5e1; }
.c-code { display: inline-block; font-family: ui-monospace, 'Cascadia Mono', Consolas, monospace; font-size: 12px; font-weight: 700; color: #0461d8;
    background: #f1f6fe; border: 1px solid #dbe8fb; padding: 1px 7px; border-radius: 6px; }
.c-person { display: inline-flex; align-items: center; gap: 9px; }
.c-person i { width: 30px; height: 30px; border-radius: 50%; flex: none; display: grid; place-items: center; font-style: normal; font-size: 11px; font-weight: 800; color: #fff; }
.c-person b { font-weight: 650; color: #0a1f44; font-size: 13px; }
.c-date { display: inline-flex; align-items: center; gap: 6px; color: #44566c; font-variant-numeric: tabular-nums; font-size: 12.5px; }
.c-date .anticon { color: #94a3b8; }
.c-money { font-variant-numeric: tabular-nums; font-weight: 800; color: #16804b; }
.c-grade { display: inline-flex; flex-direction: column; align-items: center; gap: 3px; min-width: 52px; }
.c-grade b { font-variant-numeric: tabular-nums; font-weight: 800; color: var(--g); font-size: 13.5px; }
.c-grade__bar, .c-pct__bar { display: block; width: 100%; height: 4px; border-radius: 99px; background: #edf1f6; overflow: hidden; }
.c-grade__bar i { display: block; height: 100%; border-radius: 99px; background: var(--g); }
.c-pct { display: inline-flex; flex-direction: column; align-items: center; gap: 3px; min-width: 64px; }
.c-pct b { font-weight: 800; color: var(--p); }
.c-pct__bar i { display: block; height: 100%; border-radius: 99px; background: var(--p); }
.c-num { font-variant-numeric: tabular-nums; font-weight: 700; color: #0a1f44; }
.c-text { color: #334155; }

@media (max-width: 900px) {
    .rv-sel, .rv-sel--wide { width: 100%; }
    .rp-kpis { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
    .rp-kpi--foot { grid-column: auto; }
}
</style>
