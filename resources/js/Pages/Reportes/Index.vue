<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    BarChartOutlined, TrophyOutlined, CalendarOutlined, UserOutlined, WarningOutlined, SolutionOutlined, DollarOutlined,
    BankOutlined, IdcardOutlined, LineChartOutlined, TeamOutlined, WalletOutlined, FileTextOutlined, ArrowRightOutlined,
    AppstoreOutlined, ScheduleOutlined, FilePdfOutlined, FileExcelOutlined, SearchOutlined, ThunderboltOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHead from '@/Components/PageHead.vue';

const props = defineProps({ reports: { type: Array, default: () => [] } });

/** Estilo por categoría: color principal, secundario e ícono. */
const CAT = {
    'Académicos': { icon: TrophyOutlined, c: '#2e9b43', c2: '#1f7a32' },
    'Asistencia': { icon: CalendarOutlined, c: '#1d7fc9', c2: '#0f5a99' },
    'Docentes': { icon: UserOutlined, c: '#7c4ddb', c2: '#5b34ad' },
    'Conductual': { icon: WarningOutlined, c: '#e3262b', c2: '#b91c1c' },
    'Administrativo': { icon: SolutionOutlined, c: '#0f5a99', c2: '#0b3f6e' },
    'Financiero': { icon: DollarOutlined, c: '#f39200', c2: '#c27200' },
    'Institucional': { icon: BankOutlined, c: '#14a39a', c2: '#0e7d76' },
};
const style = (cat) => CAT[cat] ?? { icon: BarChartOutlined, c: '#1d7fc9', c2: '#0f5a99' };

const ICON = {
    calificaciones: TrophyOutlined, kardex: IdcardOutlined, promedios: LineChartOutlined, asistencia: CalendarOutlined, docentes: TeamOutlined,
    incidencias: WarningOutlined, inscripciones: SolutionOutlined, pagos: DollarOutlined, adeudos: WalletOutlined, estadisticas: BarChartOutlined,
};

/** Parámetros que acepta cada reporte, mostrados como chips. */
const PARAM = {
    group_id: { label: 'Grupo', icon: AppstoreOutlined },
    period_id: { label: 'Periodo', icon: ScheduleOutlined },
    student_id: { label: 'Alumno', icon: UserOutlined },
    from: { label: 'Rango de fechas', icon: CalendarOutlined },
};
const chips = (r) => r.params.filter((p) => PARAM[p]).map((p) => PARAM[p]);

const q = ref('');
const norm = (s) => (s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const groups = computed(() => {
    const term = norm(q.value);
    const list = props.reports.filter((r) => !term || norm(`${r.title} ${r.description} ${r.category}`).includes(term));
    const order = Object.keys(CAT);
    const byCat = {};
    list.forEach((r) => (byCat[r.category] ??= []).push(r));
    return Object.entries(byCat)
        .sort(([a], [b]) => (order.indexOf(a) + 99) % 99 - (order.indexOf(b) + 99) % 99)
        .map(([category, items]) => ({ category, items, ...style(category) }));
});

const open = (r) => router.visit(route('reportes.ver', r.key));
</script>

<template>
    <AppLayout title="Reportes">
        <PageHead title="Reportes" subtitle="Genera reportes académicos, de asistencia, conducta y finanzas en PDF, Excel o CSV" :icon="BarChartOutlined" />

        <!-- Barra de herramientas (estilo Ventrack) -->
        <div class="vt-toolbar">
            <span class="vt-toolbar-label"><SearchOutlined /> Buscar</span>
            <a-input v-model:value="q" allow-clear placeholder="Nombre o tipo de reporte…" class="rp-search" />
            <span class="rp-count"><b>{{ reports.length }}</b> reportes disponibles</span>
            <span class="rp-fmts">
                <span style="--e: #d9363e"><FilePdfOutlined /> PDF</span>
                <span style="--e: #1d8a4e"><FileExcelOutlined /> Excel</span>
                <span style="--e: #0461d8"><FileTextOutlined /> CSV</span>
            </span>
        </div>

        <div class="rp-wrap">
            <section v-for="g in groups" :key="g.category" class="rp-sec" :style="{ '--acc': g.c }">
                <div class="rp-sec__title"><span class="rp-sec__ic"><component :is="g.icon" /></span>{{ g.category }}<em>{{ g.items.length }}</em></div>
                <div class="rp-tipos">
                    <button v-for="r in g.items" :key="r.key" type="button" class="rp-tipo" @click="open(r)">
                        <span class="rp-tipo-ic"><component :is="ICON[r.key] ?? FileTextOutlined" /></span>
                        <span class="rp-tipo-txt">
                            <b>{{ r.title }}</b>
                            <small>{{ r.description }}</small>
                            <span class="rp-tipo-par">
                                <span v-for="c in chips(r)" :key="c.label"><component :is="c.icon" /> {{ c.label }}</span>
                                <span v-if="!chips(r).length" class="soft">Sin parámetros</span>
                            </span>
                        </span>
                        <ArrowRightOutlined class="rp-tipo-go" />
                    </button>
                </div>
            </section>

            <div v-if="!groups.length" class="vt-empty">
                <BarChartOutlined /> {{ q ? 'Ningún reporte coincide con la búsqueda.' : 'No tienes reportes disponibles.' }}
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.vt-toolbar { flex: none; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; background: #fff; border: 1px solid #e1e8f0; border-radius: 14px; padding: 10px 14px; margin-bottom: 12px; }
.vt-toolbar-label { display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-size: 12.5px; letter-spacing: .04em; text-transform: uppercase; color: #0461d8; white-space: nowrap; }
.rp-search { width: 300px; }
.rp-count { font-size: 13px; color: #5d7085; }
.rp-count b { background: #e6f0fd; color: #0461d8; padding: 1px 8px; border-radius: 6px; margin-right: 2px; }
.rp-fmts { margin-left: auto; display: flex; gap: 6px; flex-wrap: wrap; }
.rp-fmts span { display: inline-flex; align-items: center; gap: 5px; padding: 4px 11px; border-radius: 99px; font-size: 12px; font-weight: 700; color: #fff; background: var(--e); }

.rp-wrap { display: flex; flex-direction: column; gap: 18px; padding-bottom: 8px; }
.rp-sec__title { display: flex; align-items: center; gap: 8px; font-weight: 700; font-size: 14px; color: #0a1f44; margin: 0 0 10px; padding-bottom: 6px; border-bottom: 1px dashed #e1e8f0; }
.rp-sec__ic { width: 26px; height: 26px; border-radius: 8px; display: grid; place-items: center; color: #fff; font-size: 13px; background: var(--acc); }
.rp-sec__title em { font-style: normal; font-size: 11.5px; font-weight: 800; color: var(--acc); background: color-mix(in srgb, var(--acc) 12%, #fff); padding: 0 8px; border-radius: 99px; }

/* Tarjetas de tipo de reporte (como Ventrack) */
.rp-tipos { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; }
.rp-tipo { position: relative; display: flex; gap: 12px; align-items: flex-start; text-align: left; padding: 14px 16px; border: 1.5px solid #e1e8f0; border-radius: 14px;
    background: #fff; cursor: pointer; font-family: inherit; transition: border-color .15s, box-shadow .15s, transform .15s; }
.rp-tipo:hover { transform: translateY(-2px); border-color: var(--acc); box-shadow: 0 0 0 3px color-mix(in srgb, var(--acc) 15%, transparent), 0 8px 18px rgba(10, 31, 68, .08); }
.rp-tipo-ic { flex: none; width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; font-size: 19px; color: #fff; background: var(--acc); }
.rp-tipo-txt { display: flex; flex-direction: column; gap: 3px; min-width: 0; flex: 1; }
.rp-tipo-txt b { color: #0a1f44; font-size: 14.5px; }
.rp-tipo-txt small { color: #6b7a90; font-size: 12px; line-height: 1.35; }
.rp-tipo-par { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
.rp-tipo-par span { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 600; color: #44566c; border: 1px solid #e1e8f0; border-radius: 99px; padding: 1px 8px; }
.rp-tipo-par .anticon { color: var(--acc); }
.rp-tipo-par .soft { color: #94a3b8; }
.rp-tipo-go { color: #94a3b8; margin-top: 4px; transition: transform .15s, color .15s; }
.rp-tipo:hover .rp-tipo-go { color: var(--acc); transform: translateX(3px); }
.vt-empty { text-align: center; color: #5d7085; padding: 22px 8px; border: 1.5px dashed #e1e8f0; border-radius: 12px; display: flex; gap: 8px; justify-content: center; align-items: center; }
@media (max-width: 760px) { .rp-search { width: 100%; } .rp-fmts { margin-left: 0; } }
</style>
