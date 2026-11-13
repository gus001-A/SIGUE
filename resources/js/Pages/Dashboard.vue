<script setup>
import { computed, reactive, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    DashboardOutlined, TeamOutlined, UserOutlined, AppstoreOutlined, TrophyOutlined,
    WarningOutlined, DollarOutlined, RiseOutlined, SolutionOutlined, IdcardOutlined, ArrowRightOutlined,
    LineChartOutlined, BarChartOutlined, InfoCircleOutlined, CheckCircleOutlined,
} from '@ant-design/icons-vue';
import BarChart from '@/Components/BarChart.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusTag from '@/Components/StatusTag.vue';
import { money, num, clean } from '@/lib/format';

const props = defineProps({
    filters: Object, options: Object, kpis: Object, attendanceTrend: Array, incomeTrend: Array,
    recentIncidents: Array, followUps: Array,
    charts: { type: Object, default: () => ({ attendanceDaily: { labels: [], series: [] }, finance: { labels: [], charged: [], collected: [] }, groups: [], incidents: [], passing: 6 }) },
});
const ATT_COLORS = { presente: '#2e9b43', retardo: '#f39200', justificada: '#1d7fc9', falta: '#e3262b' };
const attendanceSeries = computed(() => props.charts.attendanceDaily.series.map((s) => ({ ...s, color: ATT_COLORS[s.key] })));
// Asistencia por día: una fila por día con barra 100 % segmentada por estado y % de asistencia
const ATT_ORDER = ['presente', 'retardo', 'justificada', 'falta'];
const attDays = computed(() => {
    const ser = Object.fromEntries(props.charts.attendanceDaily.series.map((s) => [s.key, s.data]));
    return props.charts.attendanceDaily.labels.map((label, i) => {
        const counts = Object.fromEntries(ATT_ORDER.map((k) => [k, ser[k]?.[i] ?? 0]));
        const total = ATT_ORDER.reduce((t, k) => t + counts[k], 0);
        const ok = counts.presente + counts.retardo + counts.justificada;
        return { label, counts, total, rate: total ? Math.round((ok / total) * 1000) / 10 : null, today: i === props.charts.attendanceDaily.labels.length - 1 };
    }).reverse();
});
const attTotals = computed(() => {
    const t = Object.fromEntries(ATT_ORDER.map((k) => [k, attDays.value.reduce((s, d) => s + d.counts[k], 0)]));
    const all = ATT_ORDER.reduce((s, k) => s + t[k], 0);
    return { ...t, all, rate: all ? Math.round(((all - t.falta) / all) * 1000) / 10 : null };
});
const ATT_LABEL = { presente: 'Asistencias', retardo: 'Retardos', justificada: 'Justificadas', falta: 'Faltas' };
const rateColor = (r) => (r === null ? '#94a3b8' : r >= 90 ? '#2e9b43' : r >= 80 ? '#f39200' : '#e3262b');
const incCats = computed(() => props.charts.incidents.filter((c) => c.value > 0));
const moneyShort = (v) => (v == null ? '—' : Math.abs(v) >= 1000 ? `$${(v / 1000).toLocaleString('es-MX', { maximumFractionDigits: 1 })}k` : `$${Math.round(v)}`);
const page = usePage();
const can = (p) => page.props.auth.permissions.includes(p);

// Inicializamos el form con los filtros de la URL
const f = reactive({ ...props.filters });

// Guardamos el estado anterior para saber si cambió el grado
const prevGrade = reactive({ id: props.filters.grade_id });

watch(() => [f.school_year_id, f.grade_id, f.group_id], (newVal) => {
    const newGrade = newVal[1];
    
    // Si el grado cambió (y no es una limpieza por selección de grupo), 
    // limpiamos el grupo para evitar incongruencias (ej. Grupo 1 de otro grado)
    if (newGrade !== prevGrade.id) {
        f.group_id = null;
        prevGrade.id = newGrade;
    }

    // Hacemos la petición al backend
    router.get(route('dashboard'), clean({ ...f }), { 
        preserveState: true, 
        preserveScroll: true, 
        replace: true 
    });
});

// Calcular el valor máximo para escalar las barras de ingresos
const maxIncome = computed(() => Math.max(...(props.incomeTrend?.map(i => i.value) || [1]), 1));

// Tomar solo las últimas 6 incidencias
const lastIncidents = computed(() => (props.recentIncidents || []).slice(0, 6));
</script>

<template>
    <AppLayout title="Dashboard" scroll>
        <!-- HERO -->
        <section class="sg-hero">
            <div class="sg-hero__grid">
                <div>
                    <span class="sg-hero__eyebrow"><DashboardOutlined /> Panel ejecutivo</span>
                    <h1 class="sg-hero__title">Hola, {{ page.props.auth.user.first_name }}</h1>
                    <p class="sg-hero__sub">{{ page.props.institution?.name }} · plantel {{ page.props.campus?.name }} · indicadores en tiempo real.</p>
                </div>
                <div class="sg-hero__aside">
                    <div class="sg-hero__ring" :style="{ '--v': kpis.attendance_today ?? 0 }">
                        <div><b>{{ kpis.attendance_today !== null ? num(kpis.attendance_today) + '%' : '—' }}</b><span>Asistencia hoy</span></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FILTROS -->
        <a-card :bordered="false" :body-style="{ padding: '16px 20px' }" style="margin-bottom: 18px" class="filter-card">
            <div class="filter-header">
                <span>Filtros de visualización</span>
            </div>
            <div class="filters">
                <a-select v-model:value="f.school_year_id" :options="options.schoolYears" placeholder="Ciclo escolar" size="large" />
                <a-select v-model:value="f.grade_id" :options="options.grades" allow-clear placeholder="Todos los grados" size="large" />
                <a-select v-model:value="f.group_id" :options="options.groups" allow-clear show-search option-filter-prop="label" placeholder="Todos los grupos" size="large" />
            </div>
            <div class="filter-hint">
                <InfoCircleOutlined /> Selecciona un grado para ver todos sus grupos, o filtra por un grupo específico.
            </div>
        </a-card>

        <!-- KPIs EN UNA SOLA LÍNEA -->
        <div class="sg-kpi-grid">
            <StatCard label="Alumnos inscritos" :value="kpis.enrolled" :icon="TeamOutlined" color="indigo" :hint="`${kpis.active} activos`" />
            <StatCard label="Docentes" :value="kpis.teachers" :icon="UserOutlined" color="violet" :hint="`${kpis.groups} grupos`" />
            <StatCard label="Promedio" :value="kpis.average !== null ? num(kpis.average, 2) : '—'" :icon="TrophyOutlined" color="green" />
            <StatCard label="Inscripciones" :value="kpis.pending_enrollments" :icon="SolutionOutlined" color="amber" hint="Pendientes" />
            <StatCard label="Incidencias" :value="kpis.incidents" :icon="WarningOutlined" color="pink" :hint="`${kpis.incidents_open} sin cerrar`" />
            <StatCard v-if="can('finanzas.ver')" label="Adeudos" :value="money(kpis.debt)" :icon="DollarOutlined" color="red" :hint="`${kpis.overdue} vencidos`" />
            <StatCard v-if="can('finanzas.ver')" label="Ingresos (mes)" :value="money(kpis.income_month)" :icon="RiseOutlined" color="green" />
            <StatCard label="Grupos" :value="kpis.groups" :icon="AppstoreOutlined" color="slate" />
        </div>

        <!-- ================= GRÁFICAS (eje central del panel) ================= -->
        <div class="charts">
            <!-- 1. Asistencia por día -->
            <section class="ch ch--wide" style="--c: #2e9b43">
                <header class="ch__head">
                    <span class="ch__ic"><CheckCircleOutlined /></span>
                    <div class="ch__title"><b>Asistencia por día</b><small>Últimos 10 días hábiles · % de asistencia y registros por estado</small></div>
                    <span class="ch__badge">Hoy: <b>{{ kpis.attendance_today !== null ? num(kpis.attendance_today) + '%' : '—' }}</b></span>
                </header>
                <div v-if="attTotals.all" class="att">
                    <div class="att__sum">
                        <div v-for="k in ATT_ORDER" :key="k" class="att__kpi" :style="{ '--k': ATT_COLORS[k] }">
                            <i /><span>{{ ATT_LABEL[k] }}</span><b>{{ attTotals[k] }}</b>
                        </div>
                        <div class="att__kpi att__kpi--rate" :style="{ '--k': rateColor(attTotals.rate) }"><span>Promedio del periodo</span><b>{{ attTotals.rate }}%</b></div>
                    </div>
                    <div class="att__rows">
                        <div v-for="d in attDays" :key="d.label" class="att__row" :class="{ today: d.today, empty: !d.total }">
                            <span class="att__day">{{ d.label }}<em v-if="d.today">Hoy</em></span>
                            <a-tooltip :title="d.total ? ATT_ORDER.map((k) => `${ATT_LABEL[k]}: ${d.counts[k]}`).join(' · ') : 'Sin pase de lista'">
                                <div class="att__bar">
                                    <template v-if="d.total">
                                        <i v-for="k in ATT_ORDER" :key="k" :style="{ width: (d.counts[k] / d.total * 100) + '%', background: ATT_COLORS[k] }" />
                                    </template>
                                    <span v-else class="att__none">Sin pase de lista</span>
                                </div>
                            </a-tooltip>
                            <span class="att__n">{{ d.total || '' }}<small v-if="d.total"> reg.</small></span>
                            <span class="att__rate" :style="{ color: rateColor(d.rate) }">{{ d.rate !== null ? d.rate + '%' : '—' }}</span>
                        </div>
                    </div>
                </div>
                <a-empty v-else :image="false" description="Aún no hay asistencias registradas" />
            </section>

            <!-- 2. Promedio por grupo -->
            <section class="ch" style="--c: #7c4ddb">
                <header class="ch__head">
                    <span class="ch__ic"><TrophyOutlined /></span>
                    <div class="ch__title"><b>Promedio por grupo</b><small>Calificaciones del ciclo · línea = mínima aprobatoria</small></div>
                    <span class="ch__badge">General: <b>{{ kpis.average !== null ? num(kpis.average, 2) : '—' }}</b></span>
                </header>
                <BarChart :labels="charts.groups.map((g) => g.label)" :series="[{ name: 'Promedio', color: '#7c4ddb', data: charts.groups.map((g) => g.avg) }]"
                    :max="10" :reference="{ value: charts.passing, label: `Aprobatoria ${charts.passing}`, color: '#e3262b' }"
                    :format="(v) => (v == null ? '—' : Number(v).toFixed(1))" :height="340" empty="Sin calificaciones capturadas en el ciclo" />
            </section>

            <!-- 3. Ocupación por grupo -->
            <section class="ch" style="--c: #1d7fc9">
                <header class="ch__head">
                    <span class="ch__ic"><TeamOutlined /></span>
                    <div class="ch__title"><b>Ocupación por grupo</b><small>Alumnos inscritos contra capacidad</small></div>
                    <span class="ch__badge">Inscritos: <b>{{ kpis.enrolled }}</b></span>
                </header>
                <BarChart :labels="charts.groups.map((g) => g.label)" :height="340"
                    :series="[{ name: 'Inscritos', color: '#1d7fc9', data: charts.groups.map((g) => g.enrolled) }, { name: 'Capacidad', color: '#cbd5e1', data: charts.groups.map((g) => g.capacity) }]" />
            </section>

            <!-- 4. Finanzas -->
            <section v-if="can('finanzas.ver')" class="ch" style="--c: #f39200">
                <header class="ch__head">
                    <span class="ch__ic"><DollarOutlined /></span>
                    <div class="ch__title"><b>Cobrado vs. cargado</b><small>Últimos 6 meses</small></div>
                    <span class="ch__badge">Mes: <b>{{ money(kpis.income_month) }}</b></span>
                </header>
                <BarChart :labels="charts.finance.labels" :height="340" :format="moneyShort" :axis-format="moneyShort"
                    :series="[{ name: 'Cargado', color: '#f39200', data: charts.finance.charged }, { name: 'Cobrado', color: '#2e9b43', data: charts.finance.collected }]"
                    empty="Sin movimientos financieros" />
            </section>

            <!-- 5. Incidencias por categoría -->
            <section class="ch" style="--c: #e3262b">
                <header class="ch__head">
                    <span class="ch__ic"><WarningOutlined /></span>
                    <div class="ch__title"><b>Incidencias por categoría</b><small>Últimos 30 días</small></div>
                    <span class="ch__badge">Abiertas: <b>{{ kpis.incidents_open }}</b></span>
                </header>
                <BarChart horizontal stacked :labels="incCats.map((c) => c.label)" :height="340" empty="Sin incidencias en los últimos 30 días" :format="(v) => String(v)"
                    :series="[{ name: 'Negativas', color: '#e3262b', data: incCats.map((c) => (c.positive ? null : c.value)) }, { name: 'Reconocimientos', color: '#2e9b43', data: incCats.map((c) => (c.positive ? c.value : null)) }]" />
            </section>
        </div>

        <!-- INCIDENCIAS EN CARDS -->
        <div style="margin-top: 24px;">
            <div class="section-header">
                <h2 class="section-title">Incidencias recientes</h2>
                <Link v-if="can('incidencias.ver')" :href="route('incidencias.index')" class="link-more">
                    Ver todas <ArrowRightOutlined />
                </Link>
            </div>

            <!-- :image="false" para evitar el error de consola -->
            <a-empty v-if="!lastIncidents.length" :image="false" description="Sin incidencias recientes" class="empty-state" />
            
            <div v-else class="incident-cards-grid">
                <div v-for="i in lastIncidents" :key="i.id" class="incident-card">
                    <div class="ic-header">
                        <span class="ic-dot" :class="i.positive ? 'is-pos' : 'is-neg'"></span>
                        <StatusTag :status="i.priority" priority />
                    </div>
                    <div class="ic-body">
                        <Link v-if="can('alumnos.ver')" :href="route('alumnos.show', i.student_id)" class="ic-name">
                            {{ i.student }}
                        </Link>
                        <span v-else class="ic-name">{{ i.student }}</span>
                        <p class="ic-reason">{{ i.category }}</p>
                    </div>
                    <div class="ic-footer">
                        <span class="ic-date"><IdcardOutlined /> {{ i.date }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* ================= GRÁFICAS ================= */
.charts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
.ch { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 16px 18px 12px; box-shadow: 0 10px 26px -20px rgba(11, 47, 85, .45); position: relative; overflow: hidden; min-width: 0; }
.ch::before { content: ''; position: absolute; inset: 0 0 auto 0; height: 4px; background: linear-gradient(90deg, var(--c), color-mix(in srgb, var(--c) 40%, #14a39a)); }
.ch--wide { grid-column: 1 / -1; }
.att { display: flex; flex-direction: column; gap: 12px; padding-top: 6px; }
.att__sum { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 8px; }
.att__kpi { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 12px; background: color-mix(in srgb, var(--k) 6%, #fff); border: 1px solid color-mix(in srgb, var(--k) 20%, #fff); min-width: 0; }
.att__kpi i { width: 10px; height: 10px; border-radius: 3px; background: var(--k); flex: none; }
.att__kpi span { flex: 1; font-size: 12px; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.att__kpi b { font-size: 16px; color: #0b2f55; }
.att__kpi--rate b { color: var(--k); }
.att__rows { display: flex; flex-direction: column; gap: 4px; }
.att__row { display: grid; grid-template-columns: 120px minmax(0, 1fr) 70px 64px; align-items: center; gap: 12px; padding: 5px 8px; border-radius: 10px; transition: background .15s; }
.att__row:hover { background: #f5f9fd; }
.att__row.today { background: #f0f7fd; }
.att__day { font-size: 12.5px; font-weight: 600; color: #334155; display: flex; align-items: center; gap: 6px; white-space: nowrap; }
.att__day em { font-style: normal; font-size: 10px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; color: #fff; background: #1d7fc9; padding: 0 6px; border-radius: 6px; }
.att__bar { display: flex; height: 14px; border-radius: 99px; overflow: hidden; background: #eef2f7; }
.att__bar i { display: block; height: 100%; transition: width .4s ease; }
.att__bar i + i { box-shadow: inset 2px 0 0 #fff; }
.att__none { font-size: 10.5px; color: #94a3b8; padding-left: 10px; line-height: 14px; }
.att__row.empty .att__bar { background: repeating-linear-gradient(135deg, #f1f5f9, #f1f5f9 6px, #e8eef5 6px, #e8eef5 12px); }
.att__n { font-size: 12.5px; font-weight: 700; color: #0b2f55; text-align: right; }
.att__n small { font-weight: 500; color: #94a3b8; }
.att__rate { font-size: 14px; font-weight: 800; text-align: right; }
@media (max-width: 760px) { .att__sum { grid-template-columns: repeat(2, minmax(0, 1fr)); } .att__row { grid-template-columns: 90px minmax(0, 1fr) 54px; } .att__n { display: none; } }
.ch__head { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
.ch__ic { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; color: #fff; font-size: 18px; flex: none; background: linear-gradient(135deg, var(--c), color-mix(in srgb, var(--c) 60%, #0b2f55)); box-shadow: 0 8px 16px -8px var(--c); }
.ch__title { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.ch__title b { font-size: 16px; color: #0b2f55; }
.ch__title small { font-size: 12px; color: #94a3b8; }
.ch__badge { font-size: 12.5px; color: #64748b; padding: 5px 12px; border-radius: 999px; background: color-mix(in srgb, var(--c) 8%, #fff); border: 1px solid color-mix(in srgb, var(--c) 22%, #fff); white-space: nowrap; }
.ch__badge b { color: var(--c); font-size: 14px; }
@media (max-width: 1100px) { .charts { grid-template-columns: 1fr; } }
/* ============ FILTROS ============ */
.filter-card {
    border-radius: 12px;
    box-shadow: 0 1px 2px rgba(0,0,0,.03);
}
.filter-header {
    font-size: 13px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
}
.filters { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.filters :deep(.ant-select) { width: 100%; }
.filter-hint {
    font-size: 12.5px;
    color: #64748b;
    margin-top: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* ============ KPIs ============ */
.sg-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 14px;
    margin-bottom: 18px;
}

/* ============ GRÁFICAS PREMIUM Y MÁS GRANDES ============ */
.chart-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 12px -4px rgba(0,0,0,0.03);
    height: 100%;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.chart-header {
    padding: 24px;
    border-bottom: 1px solid #f1f5f9;
    background: #fafbfc;
}

.chart-title {
    display: flex;
    align-items: center;
    gap: 16px;
}

.chart-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-size: 22px;
    flex: none;
}
.icon-teal { background: #e0f2f1; color: #14a39a; }
.icon-blue { background: #eff6ff; color: #1d7fc9; }

.chart-title h3 {
    margin: 0 0 4px;
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
}
.chart-title p {
    margin: 0;
    font-size: 13.5px;
    color: #64748b;
}

/* Área del gráfico (AUMENTADA A 420px) */
.chart-body {
    padding: 24px;
    flex: 1;
    display: flex;
    height: 420px; 
}

/* Eje Y */
.y-axis {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding-right: 16px;
    padding-bottom: 28px;
    font-size: 13px;
    font-weight: 500;
    color: #94a3b8;
    text-align: right;
    width: 80px;
    flex: none;
}

/* Área de las barras */
.chart-bars-wrap {
    flex: 1;
    position: relative;
    display: flex;
    flex-direction: column;
}

/* Líneas de la cuadrícula de fondo */
.grid-lines {
    position: absolute;
    inset: 0 0 28px 0; /* Ajustado por el nuevo tamaño del label X */
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    z-index: 0;
}
.g-line {
    width: 100%;
    height: 1px;
    background: #f1f5f9;
}

/* Contenedor de las barras */
.bars {
    flex: 1;
    display: flex;
    align-items: flex-end;
    gap: 16px;
    z-index: 1;
    padding: 0 12px;
}

.bar-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
    justify-content: flex-end;
    position: relative;
}

/* Efecto hover en toda la columna */
.bar-col:hover .bar-tooltip {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}
.bar-col:hover .bar {
    filter: brightness(1.05);
    box-shadow: 0 8px 16px -6px rgba(0,0,0,0.15);
}

.bar-container {
    width: 100%;
    height: calc(100% - 28px); /* Ajustado por el label X */
    display: flex;
    align-items: flex-end;
    justify-content: center;
    position: relative;
}

/* Barra animada con gradiente */
.bar {
    width: 100%;
    max-width: 56px; /* Barras un poco más anchas */
    border-radius: 8px 8px 0 0;
    position: relative;
    animation: growBar 1s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
    transform-origin: bottom;
    display: flex;
    justify-content: center;
}

@keyframes growBar {
    from { transform: scaleY(0); }
    to { transform: scaleY(1); }
}

.bar-teal { background: linear-gradient(180deg, #2dd4bf 0%, #14a39a 100%); }
.bar-blue { background: linear-gradient(180deg, #60a5fa 0%, #1d7fc9 100%); }

/* Tooltip flotante */
.bar-tooltip {
    position: absolute;
    top: -34px;
    left: 50%;
    transform: translateX(-50%) translateY(4px);
    background: #1e293b;
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    padding: 6px 10px;
    border-radius: 8px;
    white-space: nowrap;
    opacity: 0;
    transition: all 0.2s cubic-bezier(0.2, 0.8, 0.2, 1);
    pointer-events: none;
    z-index: 10;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
}

/* Flechita del tooltip */
.bar-tooltip::after {
    content: '';
    position: absolute;
    bottom: -4px;
    left: 50%;
    transform: translateX(-50%);
    border-width: 4px 4px 0;
    border-style: solid;
    border-color: #1e293b transparent transparent transparent;
}

/* Label del Eje X */
.x-label {
    height: 28px;
    line-height: 28px;
    font-size: 13px;
    font-weight: 500;
    color: #64748b;
    margin-top: 4px;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
}
.bar-col:hover .x-label {
    color: #0f172a;
    font-weight: 600;
}

/* ============ INCIDENCIAS EN CARDS ============ */
.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
}
.section-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}
.link-more {
    font-size: 13px;
    font-weight: 600;
    color: #1d7fc9;
    display: flex;
    align-items: center;
    gap: 4px;
    text-decoration: none;
}
.link-more:hover { color: #0b3f6e; }

.empty-state {
    background: #fff;
    border-radius: 12px;
    padding: 40px;
    border: 1px dashed #cbd5e1;
}

.incident-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}

.incident-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    transition: all 0.2s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
.incident-card:hover {
    box-shadow: 0 12px 24px -8px rgba(0,0,0,0.08);
    transform: translateY(-2px);
    border-color: #cbd5e1;
}

.ic-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.ic-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}
.ic-dot.is-pos { background: #10b981; box-shadow: 0 0 0 4px #d1fae5; }
.ic-dot.is-neg { background: #f59e0b; box-shadow: 0 0 0 4px #fef3c7; }

.ic-body {
    flex: 1;
}
.ic-name {
    display: block;
    font-size: 15px;
    font-weight: 600;
    color: #0f172a;
    text-decoration: none;
    margin-bottom: 6px;
}
.ic-name:hover { color: #1d4ed8; }
.ic-reason {
    font-size: 13px;
    color: #475569;
    margin: 0;
    line-height: 1.4;
}

.ic-footer {
    margin-top: 16px;
    padding-top: 12px;
    border-top: 1px dashed #e2e8f0;
    font-size: 12px;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* ============ RESPONSIVE ============ */
@media (max-width: 900px) {
    .filters { grid-template-columns: 1fr 1fr; }
    .y-axis { display: none; }
    .chart-body { height: 280px; padding: 16px; }
}
@media (max-width: 520px) {
    .filters { grid-template-columns: 1fr; }
}
</style>