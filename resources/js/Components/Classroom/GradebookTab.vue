<script setup>
/**
 * Calificaciones (como Google Classroom): alumnos en filas, tareas en columnas.
 * - Cada celda se califica escribiendo "__/máx" (se guarda al salir de la casilla).
 * - Menú ⋮ por celda: "Ver entrega" (modal con el trabajo, rúbrica y comentarios) y "Devolver".
 * - Fila "Promedio de la clase" y columna "Calificación general".
 */
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { MoreOutlined, TeamOutlined, EyeOutlined, SendOutlined, SortAscendingOutlined } from '@ant-design/icons-vue';
import SubmissionModal from '@/Components/Classroom/SubmissionModal.vue';
import { message } from '@/lib/notify';

const props = defineProps({ students: Array, gradebook: Object });

// Copia editable de las celdas
const cells = reactive(JSON.parse(JSON.stringify(props.gradebook.cells ?? {})));
const drafts = reactive({});
const key = (s, a) => `${s}-${a}`;
const cell = (s, a) => cells[s]?.[a] ?? {};
for (const s of props.students) for (const c of props.gradebook.columns) drafts[key(s.id, c.id)] = cell(s.id, c.id).score;

const sort = ref('apellido');
const rows = computed(() => [...props.students].sort((x, y) => (sort.value === 'nombre'
    ? (x.list_name.split(',')[1] ?? '').localeCompare(y.list_name.split(',')[1] ?? '', 'es')
    : x.list_name.localeCompare(y.list_name, 'es'))));

const fmt = (n) => (n == null ? '' : Number(n).toLocaleString('es-MX', { maximumFractionDigits: 1 }));
const avgCol = (c) => {
    const v = props.students.map((s) => cell(s.id, c.id).score).filter((x) => x != null);
    return v.length ? v.reduce((t, x) => t + Number(x), 0) / v.length : null;
};
/** Calificación general del alumno en escala de 10 (puntos obtenidos / puntos de lo calificado). */
const overall = (s) => {
    let got = 0, max = 0;
    for (const c of props.gradebook.columns) { const v = cell(s.id, c.id).score; if (v != null) { got += Number(v); max += Number(c.max_score); } }
    return max ? (got / max) * 10 : null;
};
const classOverall = computed(() => { const v = props.students.map(overall).filter((x) => x != null); return v.length ? v.reduce((a, b) => a + b, 0) / v.length : null; });

async function saveCell(s, c, status = null) {
    const k = key(s.id, c.id);
    const v = drafts[k] === '' ? null : drafts[k];
    if (status === null && String(v ?? '') === String(cell(s.id, c.id).score ?? '')) return;
    if (v != null && (v < 0 || v > c.max_score)) { message.warning(`La calificación va de 0 a ${c.max_score}.`); drafts[k] = cell(s.id, c.id).score; return; }
    try {
        const { data } = await axios.post(route('docente.actividades.calificar', c.id), { student_id: s.id, score: v, ...(status ? { status } : {}) }, { headers: { Accept: 'application/json' } });
        cells[s.id] ??= {};
        cells[s.id][c.id] = { ...cell(s.id, c.id), score: data.score, status: data.status };
        if (status === 'devuelta') message.success('Trabajo devuelto.');
    } catch (e) {
        message.error(e.response?.data?.message || 'No se pudo guardar la calificación.');
    }
}
const hint = (s, c) => {
    const x = cell(s.id, c.id);
    if (x.score != null) return x.status === 'devuelta' ? 'Devuelta' : 'Calificada';
    if (x.submitted) return x.late ? 'Entregada tarde' : 'Entregada';
    return x.missing ? 'Sin entregar' : '';
};

// Modal "Ver entrega"
const view = reactive({ open: false, a: null, s: null });
const openWork = (s, c) => Object.assign(view, { open: true, a: c.id, s: s.id });
function onSaved({ assignmentId, studentId, score, status }) {
    cells[studentId] ??= {};
    cells[studentId][assignmentId] = { ...cell(studentId, assignmentId), score, status };
    drafts[key(studentId, assignmentId)] = score;
}
const rowIds = computed(() => rows.value.map((s) => s.id));
const initials = (n) => n.split(/[\s,]+/).filter(Boolean).slice(0, 2).map((x) => x[0]).join('');
</script>

<template>
    <div class="gb">
        <a-empty v-if="!gradebook.columns.length" description="Cuando publiques tareas aparecerán aquí para calificarlas." />
        <div v-else class="gb__scroll">
            <table class="gb__t">
                <thead>
                    <tr>
                        <th class="gb__name">
                            <a-select v-model:value="sort" size="small" :bordered="false" class="gb__sort"
                                :options="[{ value: 'apellido', label: 'Ordenar por apellido' }, { value: 'nombre', label: 'Ordenar por nombre' }]">
                                <template #suffixIcon><SortAscendingOutlined /></template>
                            </a-select>
                        </th>
                        <th class="gb__overall">Calificación<br />general</th>
                        <th v-for="c in gradebook.columns" :key="c.id" class="gb__col">
                            <small :class="{ late: c.overdue }">{{ c.due }}</small>
                            <a class="gb__title" :title="c.title" @click="router.visit(route('docente.actividad', c.id))">{{ c.title }}</a>
                            <small>de {{ fmt(c.max_score) }}</small>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="gb__avg">
                        <td class="gb__name"><div class="gb__who"><span class="gb__av gb__av--cls"><TeamOutlined /></span> Promedio de la clase</div></td>
                        <td class="gb__overall">{{ classOverall != null ? fmt(classOverall) : '' }}</td>
                        <td v-for="c in gradebook.columns" :key="c.id" class="gb__cellavg">{{ avgCol(c) != null ? fmt(avgCol(c)) : '' }}</td>
                    </tr>
                    <tr v-for="s in rows" :key="s.id">
                        <td class="gb__name">
                            <div class="gb__who">
                                <span class="gb__av"><img v-if="s.photo_url" :src="s.photo_url" alt="" /><template v-else>{{ initials(s.list_name) }}</template></span>
                                <span class="gb__sn" :title="s.list_name">{{ s.list_name }}</span>
                            </div>
                        </td>
                        <td class="gb__overall" :class="{ muted: overall(s) == null }">{{ overall(s) != null ? fmt(overall(s)) : 'Sin calificación' }}</td>
                        <td v-for="c in gradebook.columns" :key="c.id" class="gb__cell">
                            <div class="gb__in">
                                <span v-if="cell(s.id, c.id).missing && drafts[`${s.id}-${c.id}`] == null" class="gb__miss" @click="drafts[`${s.id}-${c.id}`] = ''">Sin entregar</span>
                                <template v-else>
                                    <a-input-number v-model:value="drafts[`${s.id}-${c.id}`]" :min="0" :max="c.max_score" :controls="false" :bordered="false" placeholder="__"
                                        :disabled="c.has_rubric" class="gb__num" @blur="saveCell(s, c)" @press-enter="saveCell(s, c)" />
                                    <span class="gb__max">/{{ fmt(c.max_score) }}</span>
                                </template>
                                <a-dropdown :trigger="['click']" placement="bottomRight">
                                    <button type="button" class="gb__more"><MoreOutlined /></button>
                                    <template #overlay>
                                        <a-menu>
                                            <a-menu-item key="ver" @click="openWork(s, c)"><EyeOutlined /> Ver entrega</a-menu-item>
                                            <a-menu-item key="dev" :disabled="cell(s.id, c.id).score == null && drafts[`${s.id}-${c.id}`] == null" @click="saveCell(s, c, 'devuelta')"><SendOutlined /> Devolver</a-menu-item>
                                        </a-menu>
                                    </template>
                                </a-dropdown>
                            </div>
                            <small class="gb__hint" :class="{ red: hint(s, c) === 'Sin entregar' }">{{ c.has_rubric && cell(s.id, c.id).score == null && hint(s, c) !== 'Sin entregar' ? 'Calificar con rúbrica' : hint(s, c) }}</small>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SubmissionModal v-model:open="view.open" :assignment-id="view.a" :student-id="view.s" :student-ids="rowIds" @saved="onSaved" @navigate="(id) => (view.s = id)" />
    </div>
</template>

<style scoped>
.gb { min-width: 0; }
.gb__scroll { overflow: auto; max-height: calc(100vh - 330px); border: 1px solid #e3e8ef; border-radius: 12px; background: #fff; }
.gb__t { border-collapse: separate; border-spacing: 0; min-width: 100%; font-size: 13.5px; color: #202124; }
.gb__t th, .gb__t td { border-bottom: 1px solid #e8eaed; border-right: 1px solid #e8eaed; padding: 0 14px; height: 56px; white-space: nowrap; background: #fff; }
.gb__t thead th { position: sticky; top: 0; z-index: 2; height: 96px; vertical-align: bottom; padding-bottom: 12px; font-weight: 500; text-align: left; }
.gb__name { position: sticky; left: 0; z-index: 1; min-width: 270px; max-width: 270px; }
.gb__t thead .gb__name { z-index: 3; }
.gb__who { display: flex; align-items: center; gap: 12px; min-width: 0; }
.gb__name { width: 270px; }
.gb__sort { margin-left: -8px; }
.gb__overall { min-width: 120px; color: #5f6368; }
.gb__overall.muted { color: #9aa0a6; }
.gb__col { min-width: 150px; max-width: 170px; }
.gb__col small { display: block; font-size: 12px; color: #5f6368; }
.gb__col small.late { color: #5f6368; }
.gb__title { display: block; padding: 4px 0 4px; margin-bottom: 4px; border-bottom: 1px solid #dadce0; color: #1967d2; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: normal;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; line-height: 1.3; }
.gb__title:hover { text-decoration: underline; }
.gb__avg td { background: #f8f9fa; color: #5f6368; }
.gb__av { width: 32px; height: 32px; border-radius: 50%; overflow: hidden; display: grid; place-items: center; flex: none; font-size: 11px; font-weight: 700; color: #fff; background: #8ab4f8; }
.gb__av img { width: 100%; height: 100%; object-fit: cover; }
.gb__av--cls { background: none; color: #1967d2; font-size: 18px; }
.gb__sn { overflow: hidden; text-overflow: ellipsis; min-width: 0; }
.gb__cell { position: relative; padding: 0 6px 0 10px !important; vertical-align: middle; }
.gb__cell:hover { background: #f8f9fa; }
.gb__cell:focus-within { box-shadow: inset 0 0 0 2px #1967d2; }
.gb__in { display: flex; align-items: center; }
.gb__num { width: 46px; }
.gb__num :deep(input) { padding: 0 2px !important; text-align: right; color: #188038; font-weight: 500; }
.gb__max { color: #188038; }
.gb__miss { color: #d93025; cursor: text; flex: 1; }
.gb__more { margin-left: auto; width: 28px; height: 28px; border-radius: 50%; border: 0; background: none; cursor: pointer; color: #5f6368; opacity: 0; transition: opacity .12s; }
.gb__cell:hover .gb__more, .gb__more:focus { opacity: 1; background: #e8eaed; }
.gb__hint { position: absolute; left: 12px; bottom: 4px; font-size: 10.5px; color: #9aa0a6; }
.gb__hint.red { display: none; }
.gb__cellavg { color: #5f6368; }
</style>
