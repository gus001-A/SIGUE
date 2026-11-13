<script setup>
/**
 * Evaluación del periodo:
 *  - Barra superior con periodo, estado e indicadores del grupo.
 *  - Modelo de evaluación (criterios ponderados) en una barra de pesos; se edita en un modal.
 *  - Hoja de calificaciones con captura por criterio, promedio ponderado y estado del alumno.
 */
import { computed, reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import {
    PlusOutlined, DeleteOutlined, SaveOutlined, SendOutlined, CalculatorOutlined, EditOutlined, CheckCircleOutlined,
    CloseCircleOutlined, MinusCircleOutlined, LockOutlined, ScheduleOutlined, UserOutlined, SlidersOutlined, ThunderboltOutlined, TrophyOutlined,
} from '@ant-design/icons-vue';
import SgModal from '@/Components/SgModal.vue';
import { confirmAction } from '@/lib/notify';
import { num, gradeColor } from '@/lib/format';

const props = defineProps({ classId: Number, students: Array, periods: Array, periodId: [Number, null], evaluation: [Object, null], options: Object });
const passing = computed(() => usePage().props.institution?.passing_score ?? 6);
const PAL = ['#0461d8', '#1597a5', '#7c4ddb', '#f0a202', '#e0457b', '#1faf5c', '#d9363e', '#0f5a99'];

// --- Modelo de evaluación (criterios ponderados) ---
const criteria = ref([]);
const grades = reactive({});
function hydrate() {
    criteria.value = (props.evaluation?.criteria ?? []).map((c) => ({ ...c }));
    for (const s of props.students) grades[s.id] = { ...(props.evaluation?.matrix?.[s.id]?.criteria ?? {}) };
}
hydrate();
watch(() => props.evaluation, hydrate, { deep: true });

const total = computed(() => Math.round(criteria.value.reduce((t, c) => t + (Number(c.weight) || 0), 0) * 100) / 100);
const savedCriteria = computed(() => props.evaluation?.criteria ?? []);
const manual = computed(() => savedCriteria.value.filter((c) => c.source === 'manual').map((c) => c.id));
const closed = computed(() => props.evaluation?.is_closed);
const errors = computed(() => usePage().props.errors ?? {});
const colorOf = (i) => PAL[i % PAL.length];

const modelOpen = ref(false);
function openModel() { criteria.value = savedCriteria.value.map((c) => ({ ...c })); modelOpen.value = true; }
function addCriterion() { criteria.value.push({ id: null, name: '', type: 'otro', source: 'manual', weight: 0 }); }
function preset() {
    criteria.value = [
        { id: null, name: 'TAREAS', type: 'tareas', source: 'tareas', weight: 20 },
        { id: null, name: 'EXÁMENES', type: 'examenes', source: 'manual', weight: 35 },
        { id: null, name: 'PROYECTOS', type: 'proyectos', source: 'manual', weight: 25 },
        { id: null, name: 'PARTICIPACIÓN', type: 'participacion', source: 'manual', weight: 10 },
        { id: null, name: 'ASISTENCIA', type: 'asistencia', source: 'asistencia', weight: 10 },
    ];
}
const savingCriteria = ref(false);
function saveCriteria() {
    savingCriteria.value = true;
    router.put(route('docente.evaluacion.criterios', [props.classId, props.periodId]), { criteria: criteria.value }, {
        preserveScroll: true,
        onSuccess: () => { if (!usePage().props.errors?.criteria) modelOpen.value = false; },
        onFinish: () => (savingCriteria.value = false),
    });
}

// --- Captura y promedios ---
function avg(sid) {
    const cs = savedCriteria.value;
    if (!cs.length) return null;
    let sum = 0, w = 0, any = false;
    for (const c of cs) {
        const v = grades[sid]?.[c.id];
        if (v !== null && v !== undefined && v !== '') any = true;
        sum += (Number(v) || 0) * c.weight;
        w += c.weight;
    }
    return any && w ? Math.round((sum / w) * 100) / 100 : null;
}
const statusOf = (v) => (v === null ? 'pend' : v >= passing.value ? 'ok' : 'bad');
const stats = computed(() => {
    const vals = props.students.map((s) => avg(s.id));
    const graded = vals.filter((v) => v !== null);
    return {
        avg: graded.length ? graded.reduce((a, b) => a + b, 0) / graded.length : null,
        ok: graded.filter((v) => v >= passing.value).length,
        bad: graded.filter((v) => v < passing.value).length,
        pend: vals.length - graded.length,
    };
});
const savingGrades = ref(false);
function saveGrades() {
    const rows = [];
    for (const s of props.students) for (const cid of manual.value) rows.push({ student_id: s.id, criterion_id: cid, score: grades[s.id]?.[cid] ?? null });
    savingGrades.value = true;
    router.put(route('docente.evaluacion.calificaciones', [props.classId, props.periodId]), { grades: rows }, {
        preserveScroll: true, onFinish: () => (savingGrades.value = false),
    });
}
function publish() {
    confirmAction({
        title: '¿Publicar calificaciones del periodo?',
        content: 'Se guardará el promedio ponderado de cada alumno y se notificará a alumnos y tutores.',
        okText: 'Publicar',
        onOk: () => router.post(route('docente.evaluacion.publicar', [props.classId, props.periodId]), {}, { preserveScroll: true }),
    });
}
function changePeriod(id) {
    router.get(route('docente.clase.evaluacion', props.classId), { period_id: id }, { preserveState: true, preserveScroll: true, only: ['evaluation', 'periodId'] });
}
const SRC = { manual: { label: 'Captura manual', icon: EditOutlined }, tareas: { label: 'Promedio de tareas', icon: ThunderboltOutlined }, asistencia: { label: '% de asistencia', icon: ScheduleOutlined } };
const cellColor = (v) => (v === null || v === undefined || v === '' ? '#cbd5e1' : gradeColor(Number(v), passing.value));
</script>

<template>
    <div class="ev">
        <!-- Barra superior -->
        <div class="ev-bar">
            <span class="ev-bar__lbl"><ScheduleOutlined /> Periodo</span>
            <a-segmented v-if="periods.length <= 4" :value="periodId" :options="periods" @change="changePeriod" />
            <a-select v-else :value="periodId" :options="periods" style="width: 200px" @change="changePeriod" />
            <span v-if="closed" class="ev-lock"><LockOutlined /> Periodo cerrado</span>
            <span class="ev-bar__sp" />
            <a-button v-if="periodId" :disabled="closed" @click="openModel"><template #icon><SlidersOutlined /></template>Modelo de evaluación</a-button>
        </div>

        <a-empty v-if="!periodId" description="No hay periodos de evaluación configurados para el ciclo." />

        <template v-else>
            <!-- Indicadores -->
            <div class="ev-kpis">
                <div class="ev-kpi" :style="{ '--k': stats.avg !== null ? gradeColor(stats.avg, passing) : '#94a3b8' }">
                    <span class="ev-kpi__ic"><CalculatorOutlined /></span><span><b>{{ stats.avg !== null ? num(stats.avg, 2) : '—' }}</b><small>Promedio del grupo</small></span>
                </div>
                <div class="ev-kpi" style="--k: #1faf5c"><span class="ev-kpi__ic"><CheckCircleOutlined /></span><span><b>{{ stats.ok }}</b><small>Aprobados</small></span></div>
                <div class="ev-kpi" style="--k: #d9363e"><span class="ev-kpi__ic"><CloseCircleOutlined /></span><span><b>{{ stats.bad }}</b><small>Reprobados</small></span></div>
                <div class="ev-kpi" style="--k: #94a3b8"><span class="ev-kpi__ic"><MinusCircleOutlined /></span><span><b>{{ stats.pend }}</b><small>Sin calificar</small></span></div>
            </div>

            <!-- Modelo: barra de pesos -->
            <div v-if="savedCriteria.length" class="ev-model">
                <div class="ev-model__head">
                    <b><TrophyOutlined /> Modelo de evaluación</b>
                    <small>Mínima aprobatoria {{ num(passing, 1) }} · total {{ num(evaluation.total_weight, 0) }}%</small>
                </div>
                <div class="ev-weights">
                    <span v-for="(c, i) in savedCriteria" :key="c.id" :style="{ flex: c.weight, '--c': colorOf(i) }">
                        <b>{{ c.name }}</b><em>{{ c.weight }}%</em>
                    </span>
                </div>
                <div class="ev-legend">
                    <span v-for="(c, i) in savedCriteria" :key="c.id" :style="{ '--c': colorOf(i) }"><i /><component :is="SRC[c.source]?.icon" /> {{ c.name }}: {{ SRC[c.source]?.label }}</span>
                </div>
            </div>
            <div v-else class="ev-empty">
                <SlidersOutlined />
                <b>Define cómo vas a evaluar este periodo</b>
                <span>Agrega criterios (exámenes, tareas, participación…) con su porcentaje; deben sumar 100%.</span>
                <a-button type="primary" :disabled="closed" @click="openModel"><PlusOutlined /> Crear modelo de evaluación</a-button>
            </div>

            <!-- Hoja de calificaciones -->
            <div v-if="savedCriteria.length" class="ev-sheet">
                <table class="grid">
                    <thead>
                        <tr>
                            <th class="left"><UserOutlined /> Alumno</th>
                            <th v-for="(c, i) in savedCriteria" :key="c.id" :style="{ '--c': colorOf(i) }" class="crit-th">
                                <span>{{ c.name }}</span><small>{{ c.weight }}% · {{ c.source === 'manual' ? 'captura' : 'automático' }}</small>
                            </th>
                            <th><CalculatorOutlined /> Promedio</th>
                            <th>Publicada</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in students" :key="s.id" :class="'st-' + statusOf(avg(s.id))">
                            <td class="left">
                                <span class="who"><span class="sg-av"><img v-if="s.photo_url" :src="s.photo_url" alt="" /><UserOutlined v-else /></span>
                                    <span><b>{{ s.list_name }}</b><small>{{ s.enrollment_number }}</small></span></span>
                            </td>
                            <td v-for="c in savedCriteria" :key="c.id">
                                <a-input-number v-if="c.source === 'manual'" v-model:value="grades[s.id][c.id]" :min="0" :max="10" :step="0.1" :precision="1"
                                    :disabled="closed" class="cell-in" :style="{ '--g': cellColor(grades[s.id][c.id]) }" placeholder="—" />
                                <span v-else class="auto" :style="{ color: cellColor(grades[s.id]?.[c.id]) }">{{ num(grades[s.id]?.[c.id], 1) }}</span>
                            </td>
                            <td>
                                <span class="avg" :style="{ '--g': cellColor(avg(s.id)) }"><b>{{ num(avg(s.id), 2) }}</b><i><em :style="{ width: Math.min(100, (avg(s.id) ?? 0) * 10) + '%' }" /></i></span>
                            </td>
                            <td><span class="pub" :style="{ color: cellColor(evaluation.matrix[s.id]?.published) }">{{ num(evaluation.matrix[s.id]?.published, 2) }}</span></td>
                            <td>
                                <span v-if="statusOf(avg(s.id)) === 'ok'" class="sg-badge" style="--c: #1faf5c">Aprobado</span>
                                <span v-else-if="statusOf(avg(s.id)) === 'bad'" class="sg-badge" style="--c: #d9363e">Reprobado</span>
                                <span v-else class="sg-badge" style="--c: #94a3b8">Pendiente</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="savedCriteria.length" class="ev-foot">
                <span class="ev-foot__hint">Las calificaciones van de 0 a 10. Los criterios automáticos se calculan con las tareas y la asistencia.</span>
                <a-button v-if="manual.length" size="large" :loading="savingGrades" :disabled="closed" @click="saveGrades"><template #icon><SaveOutlined /></template>Guardar calificaciones</a-button>
                <a-button type="primary" size="large" :disabled="closed || evaluation.total_weight !== 100" @click="publish"><template #icon><SendOutlined /></template>Publicar promedios</a-button>
            </div>
        </template>

        <!-- Modal: modelo de evaluación -->
        <SgModal v-model:open="modelOpen" title="Modelo de evaluación" subtitle="Criterios del periodo y su porcentaje · deben sumar 100%" :icon="SlidersOutlined"
            color="linear-gradient(135deg, #7c4ddb, #0461d8)" width="min(900px, 96vw)" ok-text="Guardar modelo" :loading="savingCriteria" @ok="saveCriteria">
            <a-alert v-if="errors.criteria" type="error" :message="errors.criteria" show-icon style="margin-bottom: 10px" />
            <div class="crit-head"><span>Criterio</span><span>Tipo</span><span>Cómo se obtiene</span><span>Peso</span><span /></div>
            <div v-for="(c, i) in criteria" :key="i" class="crit" :style="{ '--c': colorOf(i) }">
                <a-input v-model:value="c.name" placeholder="EXÁMENES" />
                <a-select v-model:value="c.type" :options="options.criterionTypes" />
                <a-select v-model:value="c.source" :options="options.criterionSources" />
                <a-input-number v-model:value="c.weight" :min="0" :max="100" addon-after="%" />
                <a-button class="sg-ab red" @click="criteria.splice(i, 1)"><DeleteOutlined /></a-button>
            </div>
            <div class="crit-tools">
                <a-button type="dashed" @click="addCriterion"><PlusOutlined /> Agregar criterio</a-button>
                <a-button v-if="!criteria.length" @click="preset"><ThunderboltOutlined /> Usar modelo sugerido</a-button>
                <span class="crit-total" :class="{ ok: total === 100 }">Total: <b>{{ num(total, 2) }}%</b><template v-if="total !== 100"> · faltan {{ num(100 - total, 2) }}%</template></span>
            </div>
            <div class="ev-weights ev-weights--sm">
                <span v-for="(c, i) in criteria" v-show="c.weight > 0" :key="i" :style="{ flex: c.weight || 0, '--c': colorOf(i) }"><em>{{ c.weight }}%</em></span>
            </div>
        </SgModal>
    </div>
</template>

<style scoped>
.ev { display: flex; flex-direction: column; gap: 12px; }
.ev-bar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding: 10px 14px; background: #fff; border: 1px solid #e1e8f0; border-radius: 14px; }
.ev-bar__lbl { display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-size: 12.5px; letter-spacing: .04em; text-transform: uppercase; color: #0461d8; }
.ev-bar__sp { flex: 1; }
.ev-lock { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; color: #d9363e; border: 1px solid #f6c6c8; padding: 3px 10px; border-radius: 99px; }

.ev-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
.ev-kpi { display: flex; align-items: center; gap: 12px; padding: 10px 14px; background: #fff; border: 1px solid #e1e8f0; border-left: 4px solid var(--k); border-radius: 12px; }
.ev-kpi__ic { width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; color: #fff; background: var(--k); font-size: 17px; flex: none; }
.ev-kpi b { display: block; font-size: 20px; font-weight: 800; color: #0a1f44; line-height: 1.1; }
.ev-kpi small { font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #5d7085; }

.ev-model { padding: 12px 14px; background: #fff; border: 1px solid #e1e8f0; border-radius: 14px; }
.ev-model__head { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; margin-bottom: 8px; flex-wrap: wrap; }
.ev-model__head b { color: #0a1f44; display: inline-flex; gap: 6px; align-items: center; }
.ev-model__head b .anticon { color: #f0a202; }
.ev-model__head small { color: #5d7085; font-size: 12px; }
.ev-weights { display: flex; gap: 3px; border-radius: 10px; overflow: hidden; }
.ev-weights span { min-width: 0; display: flex; align-items: center; justify-content: space-between; gap: 6px; padding: 7px 10px; color: #fff; background: var(--c); font-size: 12px; }
.ev-weights b { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 700; }
.ev-weights em { font-style: normal; font-weight: 800; background: rgba(255, 255, 255, .22); padding: 0 6px; border-radius: 6px; flex: none; }
.ev-weights--sm { margin-top: 12px; }
.ev-weights--sm span { justify-content: center; padding: 4px; }
.ev-legend { display: flex; gap: 14px; flex-wrap: wrap; margin-top: 8px; font-size: 11.5px; color: #5d7085; }
.ev-legend span { display: inline-flex; align-items: center; gap: 5px; }
.ev-legend i { width: 9px; height: 9px; border-radius: 3px; background: var(--c); }

.ev-empty { display: flex; flex-direction: column; align-items: center; gap: 8px; text-align: center; padding: 34px 16px; background: #fff; border: 1.5px dashed #e1e8f0; border-radius: 14px; color: #5d7085; }
.ev-empty > .anticon { font-size: 34px; color: #7c4ddb; }
.ev-empty b { color: #0a1f44; font-size: 15px; }

/* Hoja */
.ev-sheet { background: #fff; border: 1px solid #e1e8f0; border-radius: 14px; overflow: auto; }
.grid { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 760px; }
.grid th { position: sticky; top: 0; z-index: 1; background: #f4f7fb; color: #44566c; font-size: 11.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
    padding: 12px 8px; text-align: center; border-bottom: 1px solid #e1e8f0; }
.grid th.crit-th { border-top: 3px solid var(--c); }
.grid th.crit-th span { display: block; color: color-mix(in srgb, var(--c) 75%, #0a1f44); }
.grid th small { display: block; color: #94a3b8; font-weight: 600; text-transform: none; letter-spacing: 0; }
.grid td { padding: 8px; border-bottom: 1px solid #eef2f7; text-align: center; }
.grid tbody tr:hover td { background: #f5f9fe; }
.grid tr.st-bad td:first-child { box-shadow: inset 3px 0 0 #d9363e; }
.grid tr.st-ok td:first-child { box-shadow: inset 3px 0 0 #1faf5c; }
.grid .left { text-align: left; min-width: 240px; }
.who { display: flex; align-items: center; gap: 10px; }
.who .sg-av { width: 32px; height: 32px; }
.who > span:last-child { display: flex; flex-direction: column; line-height: 1.25; }
.who b { color: #0a1f44; font-size: 13px; }
.who small { color: #94a3b8; font-size: 11px; }
.cell-in { width: 76px; }
.cell-in :deep(input) { text-align: center; font-weight: 800; color: var(--g); }
.cell-in:not(.ant-input-number-disabled) { border-color: color-mix(in srgb, var(--g) 45%, #e1e8f0); }
.auto { font-weight: 800; }
.avg { display: inline-flex; flex-direction: column; align-items: center; gap: 3px; min-width: 64px; }
.avg b { font-size: 15px; font-weight: 800; color: var(--g); }
.avg i { display: block; width: 100%; height: 5px; border-radius: 99px; background: #edf1f6; overflow: hidden; }
.avg em { display: block; height: 100%; border-radius: 99px; background: var(--g); }
.pub { font-weight: 700; }

.ev-foot { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 10px 14px; background: #fff; border: 1px solid #e1e8f0; border-radius: 14px; }
.ev-foot__hint { flex: 1; min-width: 220px; font-size: 12px; color: #5d7085; }

/* Modal */
.crit-head, .crit { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1.2fr) minmax(0, 1.6fr) 130px 36px; gap: 8px; align-items: center; }
.crit-head { font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #5d7085; margin-bottom: 6px; }
.crit { margin-bottom: 8px; padding-left: 10px; border-left: 4px solid var(--c); }
.crit :deep(.ant-select), .crit :deep(.ant-input-number-group-wrapper) { width: 100%; }
.crit-tools { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 6px; }
.crit-total { margin-left: auto; font-size: 13px; color: #d9363e; }
.crit-total.ok { color: #1faf5c; }
@media (max-width: 900px) { .ev-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .crit-head { display: none; } .crit { grid-template-columns: 1fr 1fr; } }
</style>
