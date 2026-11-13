<script setup>
import { computed, ref } from 'vue';
import { TrophyOutlined, ReadOutlined, CheckCircleOutlined, CloseCircleOutlined, FileDoneOutlined, UserOutlined, InfoCircleOutlined, BarChartOutlined, RightOutlined } from '@ant-design/icons-vue';
import BarChart from '@/Components/BarChart.vue';
import SgModal from '@/Components/SgModal.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PortalHeader from '@/Components/PortalHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import { num, gradeColor } from '@/lib/format';

const props = defineProps({ student: Object, children: Array, isGuardian: Boolean, periods: Array, rows: Array, passing: Number });
const PALETTE = ['#1d7fc9', '#14a39a', '#2e9b43', '#f39200', '#e3262b', '#7c4ddb', '#e0457b', '#0f5a99'];
const finals = computed(() => props.rows.flatMap((r) => Object.values(r.periods).map((p) => p.final)).filter((v) => v !== null));
const general = computed(() => (finals.value.length ? finals.value.reduce((a, b) => a + b, 0) / finals.value.length : null));
const approved = computed(() => props.rows.filter((r) => r.average !== null && r.average >= props.passing).length);
const failed = computed(() => props.rows.filter((r) => r.average !== null && r.average < props.passing).length);

/** Calificación a mostrar en un periodo: la final publicada o, si no hay, la parcial. */
const cell = (r, p) => r.periods[p.id]?.final ?? r.periods[p.id]?.partial ?? null;
const best = (r) => { const v = props.periods.map((p) => cell(r, p)).filter((x) => x !== null); return v.length ? v[v.length - 1] : null; };
const SERIES_COLORS = ['#1d7fc9', '#7c4ddb', '#14a39a', '#f39200'];
const chartSeries = computed(() => props.periods.map((p, i) => ({ name: p.name, color: SERIES_COLORS[i % SERIES_COLORS.length], data: props.rows.map((r) => cell(r, p)) })));
const detailOpen = ref(false);
const detail = ref(null);
const openDetail = (r) => { detail.value = r; detailOpen.value = true; };
</script>

<template>
    <AppLayout title="Calificaciones" scroll>
        <PortalHeader :student="student" :children="children" :is-guardian="isGuardian" title="Calificaciones" />

        <div class="sg-stats">
            <StatCard label="Promedio general" :value="general !== null ? num(general, 2) : '—'" :icon="TrophyOutlined" color="green" />
            <StatCard label="Asignaturas" :value="rows.length" :icon="ReadOutlined" color="indigo" />
            <StatCard label="Aprobadas" :value="approved" :icon="CheckCircleOutlined" color="violet" />
            <StatCard label="En riesgo" :value="failed" :icon="CloseCircleOutlined" :color="failed ? 'red' : 'slate'" :hint="`mínimo aprobatorio ${passing}`" />
            <StatCard label="Calificaciones publicadas" :value="finals.length" :icon="FileDoneOutlined" color="amber" />
        </div>

        <!-- Comparativo por materia y periodo -->
        <section class="gch">
            <header class="gch__head">
                <span class="gch__ic"><BarChartOutlined /></span>
                <div><b>Comparativo por materia</b><small>Calificación de cada periodo (finales publicadas o parciales) · línea = aprobatoria</small></div>
            </header>
            <BarChart :labels="rows.map((r) => r.subject)" :series="chartSeries" :max="10" :height="280"
                :reference="{ value: passing, label: `Aprobatoria ${passing}`, color: '#e3262b' }" :format="(v) => (v == null ? '—' : Number(v).toFixed(1))"
                empty="Aún no hay calificaciones capturadas" />
        </section>

        <a-empty v-if="!rows.length" description="Sin asignaturas en el ciclo actual" />
        <div v-else class="subjects">
            <button v-for="(r, i) in rows" :key="r.id" type="button" class="sc" :style="{ '--c': PALETTE[i % PALETTE.length], '--g': gradeColor(r.average ?? best(r), passing) }" @click="openDetail(r)">
                <div class="sc__top">
                    <span class="sc__ic"><ReadOutlined /></span>
                    <span class="sc__name"><b :title="r.subject">{{ r.subject }}</b><small><UserOutlined /> {{ r.teacher || 'Sin docente' }}</small></span>
                    <span class="sc__avg"><b>{{ r.average !== null ? num(r.average, 1) : '—' }}</b><small>promedio</small></span>
                </div>
                <div class="sc__periods">
                    <span v-for="p in periods" :key="p.id" class="sp" :class="{ partial: r.periods[p.id].final === null && r.periods[p.id].partial !== null }"
                        :style="{ '--p': cell(r, p) !== null ? gradeColor(cell(r, p), passing) : '#cbd5e1' }">
                        <small>{{ p.name }}</small>
                        <b>{{ cell(r, p) !== null ? num(cell(r, p), 1) : '—' }}</b>
                        <i v-if="r.periods[p.id].final === null && r.periods[p.id].partial !== null">parcial</i>
                        <i v-else-if="r.periods[p.id].final !== null">final</i>
                    </span>
                </div>
                <span class="sc__go">Ver desglose <RightOutlined /></span>
            </button>
        </div>
        <p class="note"><InfoCircleOutlined /> "Parcial" es un cálculo preliminar con lo capturado por el docente; la calificación final es la que el docente publica al cerrar el periodo.</p>

        <!-- Desglose de una materia -->
        <SgModal v-model:open="detailOpen" title="Desglose de calificaciones" :subtitle="detail ? `${detail.subject} · ${detail.teacher || 'Sin docente'}` : ''" :icon="ReadOutlined"
            color="linear-gradient(135deg, #7c4ddb, #1d7fc9)" :footer="false" :width="periods.length > 2 ? 'min(1040px, 96vw)' : 'min(760px, 96vw)'">
            <div v-if="detail" class="dg" :style="{ '--n': Math.min(periods.length, 3) }">
                <section v-for="p in periods" :key="p.id" class="dg__card" :style="{ '--g': cell(detail, p) !== null ? gradeColor(cell(detail, p), passing) : '#94a3b8' }">
                    <header><b>{{ p.name }}</b><span>{{ cell(detail, p) !== null ? num(cell(detail, p), 1) : '—' }}</span></header>
                    <small class="dg__note">{{ detail.periods[p.id].final !== null ? 'Calificación final publicada' : detail.periods[p.id].partial !== null ? 'Parcial (sin publicar)' : 'Sin calificación' }}</small>
                    <div v-if="!detail.periods[p.id].criteria.length" class="dg__empty">Sin criterios definidos</div>
                    <div v-for="(c, ci) in detail.periods[p.id].criteria" :key="c.name" class="dg__crit" :style="{ '--k': PALETTE[ci % PALETTE.length] }">
                        <div class="dg__top"><span>{{ c.name }}</span><em>{{ c.weight }}%</em><b>{{ c.score !== null ? num(c.score, 1) : '—' }}</b></div>
                        <div class="dg__bar"><i :style="{ width: (c.score ?? 0) * 10 + '%' }" /></div>
                    </div>
                </section>
            </div>
        </SgModal>
    </AppLayout>
</template>

<style scoped>
.gch { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 16px 18px 12px; margin-bottom: 16px; position: relative; overflow: hidden; box-shadow: 0 10px 26px -20px rgba(11, 47, 85, .45); }
.gch::before { content: ''; position: absolute; inset: 0 0 auto 0; height: 4px; background: linear-gradient(90deg, #7c4ddb, #1d7fc9); }
.gch__head { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
.gch__head > div { display: flex; flex-direction: column; }
.gch__head b { font-size: 15.5px; color: #0b2f55; }
.gch__head small { font-size: 12px; color: #94a3b8; }
.gch__ic { width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; color: #fff; background: linear-gradient(135deg, #7c4ddb, #1d7fc9); font-size: 17px; }
.subjects { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 12px; }
.sc { display: flex; flex-direction: column; gap: 10px; padding: 14px; text-align: left; font-family: inherit; cursor: pointer; background: #fff; border: 1px solid #e2e8f0; border-top: 4px solid var(--c); border-radius: 16px; transition: transform .15s, box-shadow .15s; }
.sc:hover { transform: translateY(-3px); box-shadow: 0 16px 30px -20px var(--c); }
.sc__top { display: flex; align-items: center; gap: 10px; }
.sc__ic { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; color: #fff; background: var(--c); flex: none; font-size: 17px; }
.sc__name { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.sc__name b { color: #0b2f55; font-size: 14px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sc__name small { color: #94a3b8; font-size: 11.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sc__avg { display: flex; flex-direction: column; align-items: center; padding: 4px 10px; border-radius: 12px; background: color-mix(in srgb, var(--g) 10%, #fff); }
.sc__avg b { font-size: 20px; font-weight: 900; color: var(--g); line-height: 1.1; }
.sc__avg small { font-size: 10px; color: #64748b; text-transform: uppercase; font-weight: 700; }
.sc__periods { display: flex; gap: 6px; flex-wrap: wrap; }
.sp { display: flex; flex-direction: column; align-items: center; min-width: 74px; padding: 5px 8px; border-radius: 10px; background: color-mix(in srgb, var(--p) 9%, #fff); border: 1px solid color-mix(in srgb, var(--p) 28%, #fff); }
.sp.partial { border-style: dashed; }
.sp small { font-size: 10px; color: #64748b; font-weight: 700; text-transform: uppercase; }
.sp b { font-size: 16px; color: var(--p); }
.sp i { font-size: 9.5px; font-style: normal; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .04em; }
.sc__go { align-self: flex-end; display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; color: var(--c); }
.note { margin-top: 12px; font-size: 12.5px; color: #64748b; display: flex; gap: 6px; align-items: center; }
.dg { display: grid; grid-template-columns: repeat(var(--n), minmax(0, 1fr)); gap: 12px; padding-bottom: 12px; }
.dg__card { display: flex; flex-direction: column; gap: 8px; padding: 12px; border-radius: 14px; border: 1px solid #e2e8f0; border-top: 4px solid var(--g); }
.dg__card header { display: flex; align-items: center; justify-content: space-between; }
.dg__card header b { color: #0b2f55; }
.dg__card header span { font-size: 22px; font-weight: 900; color: var(--g); }
.dg__note { font-size: 11.5px; color: #94a3b8; margin-top: -6px; }
.dg__empty { font-size: 12.5px; color: #94a3b8; padding: 10px; text-align: center; border: 1px dashed #e2e8f0; border-radius: 10px; }
.dg__crit { padding: 8px 10px; border-radius: 10px; background: #f8fafc; display: flex; flex-direction: column; gap: 5px; }
.dg__top { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #334155; }
.dg__top span { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dg__top em { font-style: normal; font-size: 11px; font-weight: 800; color: var(--k); background: color-mix(in srgb, var(--k) 12%, #fff); padding: 1px 7px; border-radius: 999px; }
.dg__top b { color: #0b2f55; }
.dg__bar { height: 6px; border-radius: 999px; background: #e2e8f0; overflow: hidden; }
.dg__bar i { display: block; height: 100%; background: var(--k); border-radius: 999px; }
@media (max-width: 900px) { .dg { grid-template-columns: 1fr; } }
</style>
