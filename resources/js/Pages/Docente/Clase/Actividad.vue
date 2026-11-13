<script setup>
/**
 * Herramienta de calificación al estilo Google Classroom:
 *  - Barra superior: alumno (selector), anterior/siguiente, archivo abierto, estado y "Devolver".
 *  - Izquierda (opcional): lista de alumnos con su calificación rápida.
 *  - Centro: visor oscuro con el archivo entregado.
 *  - Derecha: "Calificación y comentarios": archivos, calificación, rúbrica en barras y comentarios privados.
 *  - Devolver abre un diálogo para confirmar y dejar un comentario privado.
 */
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    FileTextOutlined, ProjectOutlined, ExperimentOutlined, CalendarOutlined,
    UserOutlined, LeftOutlined, RightOutlined, ExpandOutlined,
    FileUnknownOutlined, WarningOutlined, InboxOutlined, TeamOutlined, TableOutlined,
    CloseOutlined, SortAscendingOutlined, ExportOutlined, MoreOutlined, CheckOutlined, DownloadOutlined, FilePdfFilled, FileImageFilled,
    FileWordFilled, FileExcelFilled, FileFilled, DoubleRightOutlined, DoubleLeftOutlined, ReadOutlined,
} from '@ant-design/icons-vue';
import CommentThread from '@/Components/Classroom/CommentThread.vue';
import RubricView from '@/Components/Classroom/RubricView.vue';
import FileChips from '@/Components/FileChips.vue';
import SgModal from '@/Components/SgModal.vue';
import ClaseShell from '@/Components/Clase/ClaseShell.vue';
import { openFile, fileKind } from '@/lib/viewer';
import { message } from '@/lib/notify';

const props = defineProps({
    course: Object, section: String, students: Array, periods: Array, periodId: [Number, null], assignment: Object, works: Array,
    classComments: { type: Array, default: () => [] },
    privateComments: { type: [Object, Array], default: () => ({}) },
});
const hasRubric = computed(() => (props.assignment.rubric ?? []).length > 0);
const commentsOpen = ref(false);
const classCount = ref(props.classComments.length);
const priv = ref(Array.isArray(props.privateComments) ? {} : { ...props.privateComments });
const privateOf = (sid) => priv.value[sid] ?? [];
const rosterOpen = ref(typeof window !== 'undefined' ? window.innerWidth > 1500 : true);
const sideOpen = ref(true);
const instrOpen = ref(false);
const rubricFull = ref(false);
const rubricMax = computed(() => (props.assignment.rubric ?? []).reduce((t, c) => t + Math.max(0, ...c.levels.map((l) => Number(l.points) || 0)), 0));

const TYPE_ICON = { tarea: FileTextOutlined, proyecto: ProjectOutlined, actividad: ExperimentOutlined };
const STATUS = {
    asignada: { label: 'Asignada', c: '#5f6368' }, entregada: { label: 'Entregada', c: '#1967d2' },
    calificada: { label: 'Calificada', c: '#188038' }, devuelta: { label: 'Devuelta', c: '#7c4ddb' },
};
const st = (w) => (w.missing && !w.submitted && w.score === null ? { label: 'Falta entregar', c: '#d93025' } : STATUS[w.status] ?? STATUS.asignada);

// Copia editable de los trabajos (calificación y comentario por alumno)
const toRow = (w) => ({ ...w, draft: w.score, note: w.feedback ?? '', rub: [...(w.rubric_scores ?? [])] });
const list = ref(props.works.map(toRow));
watch(() => props.works, (v) => (list.value = v.map(toRow)));
/** Al elegir niveles de la rúbrica la calificación se suma sola. */
function setRubric(w, scores) {
    w.rub = scores;
    const picked = scores.filter((x) => x !== null && x !== undefined);
    w.draft = picked.length ? props.assignment.rubric.reduce((t, c, i) => t + (scores[i] != null ? Number(c.levels[scores[i]]?.points ?? 0) : 0), 0) : null;
}

const filter = ref('todos');
const FILTERS = computed(() => [
    { key: 'todos', label: 'Todos', n: list.value.length },
    { key: 'entregados', label: 'Entregados', n: list.value.filter((w) => w.submitted).length },
    { key: 'pendientes', label: 'Por entregar', n: list.value.filter((w) => !w.submitted).length },
    { key: 'calificados', label: 'Calificados', n: list.value.filter((w) => w.score !== null).length },
]);
const shown = computed(() => list.value.filter((w) => filter.value === 'todos'
    || (filter.value === 'entregados' && w.submitted) || (filter.value === 'pendientes' && !w.submitted) || (filter.value === 'calificados' && w.score !== null)));

const selectedId = ref((list.value.find((w) => w.submitted && w.score === null) ?? list.value[0])?.id ?? null);
const sel = computed(() => list.value.find((w) => w.id === selectedId.value) ?? null);
const idx = computed(() => shown.value.findIndex((w) => w.id === selectedId.value));
const go = (d) => { const n = shown.value[idx.value + d]; if (n) selectedId.value = n.id; };

// Archivos de la entrega (hasta 5): se previsualiza el que se elija en las pestañas
const fileIdx = ref(0);
watch(selectedId, () => (fileIdx.value = 0));
const curFile = computed(() => sel.value?.files?.[fileIdx.value] ?? sel.value?.files?.[0] ?? null);
const kind = computed(() => (curFile.value ? fileKind(curFile.value.name, curFile.value.url) : null));
const saving = ref(false);

/** Guarda la calificación y el comentario y "devuelve" el trabajo al alumno. */
async function devolver(w = sel.value, silent = false) {
    if (!w) return;
    if (w.draft !== null && w.draft !== '' && (w.draft < 0 || w.draft > props.assignment.max_score)) {
        message.warning(`La calificación debe estar entre 0 y ${props.assignment.max_score}.`); return;
    }
    saving.value = true;
    try {
        await axios.post(route('docente.actividades.calificar', props.assignment.id),
            { student_id: w.id, score: w.draft === '' ? null : w.draft, ...(hasRubric.value ? { rubric_scores: props.assignment.rubric.map((_, i) => w.rub[i] ?? null) } : {}) },
            { headers: { Accept: 'application/json' } });
        w.score = w.draft === '' ? null : w.draft;
        w.rubric_scores = [...w.rub];
        if (w.score !== null) w.status = 'calificada';
        if (!silent) message.success(`Trabajo devuelto a ${w.list_name.split(',')[1]?.trim() || w.list_name}.`);
        if (!silent) { const next = shown.value.slice(idx.value + 1).find((x) => x.submitted && x.score === null); if (next) selectedId.value = next.id; }
    } catch (e) {
        message.error(e.response?.data?.message || 'No se pudo guardar la calificación.');
    } finally { saving.value = false; }
}
/** Al salir de la casilla de calificación de la lista se guarda como borrador calificado. */
const quickSave = (w) => { if (String(w.draft ?? '') !== String(w.score ?? '')) devolver(w, true); };

const stats = computed(() => ({
    entregados: list.value.filter((w) => w.submitted).length,
    calificados: list.value.filter((w) => w.score !== null).length,
    tarde: list.value.filter((w) => w.late).length,
    faltan: list.value.filter((w) => w.missing && !w.submitted).length,
}));
/** Estado como en Google: Sin calificar / No devuelto (cambios sin guardar) / Devuelto. */
const rubKey = (arr) => JSON.stringify((props.assignment.rubric ?? []).map((_, i) => arr?.[i] ?? null));
const dirty = (w) => String(w.draft ?? '') !== String(w.score ?? '') || (hasRubric.value && rubKey(w.rub) !== rubKey(w.rubric_scores));
const state = computed(() => {
    const w = sel.value;
    if (!w) return null;
    if (dirty(w)) return { t: 'No devuelto', c: '#d93025' };
    if (w.score !== null) return { t: 'Devuelto', c: '#188038' };
    return { t: w.submitted ? 'Sin calificar' : (w.missing ? 'Falta entregar' : 'Sin entregar'), c: '#5f6368' };
});
const studentOptions = computed(() => shown.value.map((w) => ({ value: w.id, label: w.list_name })));
const FICON = { pdf: [FilePdfFilled, '#ea4335'], image: [FileImageFilled, '#ea4335'], doc: [FileWordFilled, '#4285f4'], xls: [FileExcelFilled, '#188038'] };
const fIcon = (f) => {
    const x = (f.name.split('.').pop() || '').toLowerCase();
    const k = x === 'pdf' ? 'pdf' : ['png', 'jpg', 'jpeg', 'gif', 'webp'].includes(x) ? 'image' : ['doc', 'docx'].includes(x) ? 'doc' : ['xls', 'xlsx', 'csv'].includes(x) ? 'xls' : null;
    return FICON[k] ?? [FileFilled, '#5f6368'];
};

// ---- Devolver con diálogo y comentario privado ----
const ret = ref({ open: false, note: '', sending: false });
function askReturn() {
    const w = sel.value;
    if (!w) return;
    if (w.draft !== null && w.draft !== '' && (w.draft < 0 || w.draft > props.assignment.max_score)) {
        message.warning(`La calificación debe estar entre 0 y ${props.assignment.max_score}.`); return;
    }
    ret.value = { open: true, note: '', sending: false };
}
async function confirmReturn() {
    const w = sel.value;
    ret.value.sending = true;
    try {
        const note = ret.value.note.trim();
        if (note) {
            const { data } = await axios.post(route('classroom.comentarios.store'), { type: 'assignment', id: props.assignment.id, body: note, private: true, student_id: w.id },
                { headers: { Accept: 'application/json' } });
            priv.value = { ...priv.value, [w.id]: [...privateOf(w.id), data] };
        }
        await devolver(w);
        ret.value.open = false;
    } catch (e) {
        message.error(e.response?.data?.errors?.body?.[0] ?? 'No se pudo enviar el comentario.');
    } finally { ret.value.sending = false; }
}
const firstName = (w) => w.list_name.split(',')[1]?.trim() || w.list_name;
const initials = (n) => n.split(/[\s,]+/).filter(Boolean).slice(0, 2).map((x) => x[0]).join('');
</script>

<template>
    <ClaseShell :course="course" :section="section" :count="students.length">
        <div class="gt">
            <!-- ===== Barra superior ===== -->
            <header class="gt__bar">
                <a-tooltip title="Volver a Classroom"><button type="button" class="gt__ib" @click="router.visit(route('docente.clase.classroom', course.id))"><CloseOutlined /></button></a-tooltip>
                <span class="gt__sep" />
                <a-tooltip :title="rosterOpen ? 'Ocultar lista de alumnos' : 'Lista de alumnos'"><button type="button" class="gt__ib" :class="{ on: rosterOpen }" @click="rosterOpen = !rosterOpen"><SortAscendingOutlined /></button></a-tooltip>
                <div class="gt__who">
                    <span class="rw__av sm"><img v-if="sel?.photo_url" :src="sel.photo_url" alt="" /><template v-else-if="sel">{{ initials(sel.list_name) }}</template></span>
                    <a-select v-model:value="selectedId" :options="studentOptions" show-search option-filter-prop="label" :bordered="false" class="gt__sel" />
                </div>
                <button type="button" class="gt__ib" :disabled="idx <= 0" title="Anterior" @click="go(-1)"><LeftOutlined /></button>
                <button type="button" class="gt__ib" :disabled="idx >= shown.length - 1" title="Siguiente" @click="go(1)"><RightOutlined /></button>
                <div v-if="curFile && sel?.submitted" class="gt__file">
                    <component :is="fIcon(curFile)[0]" :style="{ color: fIcon(curFile)[1] }" />
                    <span>{{ curFile.name }}</span>
                    <a-tooltip title="Abrir en una ventana nueva"><a :href="curFile.url" target="_blank" rel="noopener" class="gt__ib"><ExportOutlined /></a></a-tooltip>
                </div>
                <div v-else class="gt__ttl"><b>{{ assignment.title }}</b><small>{{ assignment.type_label }} · {{ assignment.max_score }} puntos</small></div>
                <span class="gt__sp" />
                <span v-if="state" class="gt__state" :style="{ color: state.c }">{{ state.t }}</span>
                <a-button type="primary" shape="round" size="large" class="gt__ret" :disabled="!sel" @click="askReturn">Devolver</a-button>
                <a-dropdown :trigger="['click']" placement="bottomRight">
                    <button type="button" class="gt__ib"><MoreOutlined /></button>
                    <template #overlay>
                        <a-menu>
                            <a-menu-item key="i" @click="instrOpen = true"><ReadOutlined /> Ver instrucciones</a-menu-item>
                            <a-menu-item key="c" @click="commentsOpen = true"><TeamOutlined /> Comentarios de la clase ({{ classCount }})</a-menu-item>
                            <a-menu-item v-if="curFile && sel?.submitted" key="d"><a :href="curFile.url + '?download=1'"><DownloadOutlined /> Descargar archivo</a></a-menu-item>
                        </a-menu>
                    </template>
                </a-dropdown>
            </header>

            <div class="gt__main" :class="{ 'no-roster': !rosterOpen, 'no-side': !sideOpen }">
                <!-- ===== Alumnos ===== -->
                <aside v-if="rosterOpen" class="roster">
                    <div class="roster__tabs">
                        <button v-for="f in FILTERS" :key="f.key" type="button" :class="{ on: filter === f.key }" @click="filter = f.key">{{ f.label }} <i>{{ f.n }}</i></button>
                    </div>
                    <div class="roster__list">
                        <div v-if="!shown.length" class="roster__empty"><InboxOutlined /> Nadie en esta lista</div>
                        <div v-for="w in shown" :key="w.id" class="rw" :class="{ on: w.id === selectedId }" @click="selectedId = w.id">
                            <span class="rw__av"><img v-if="w.photo_url" :src="w.photo_url" alt="" /><template v-else>{{ initials(w.list_name) }}</template></span>
                            <span class="rw__txt">
                                <b>{{ w.list_name }}</b>
                                <small :style="{ color: st(w).c }">{{ st(w).label }}<template v-if="w.late"> · tarde</template></small>
                            </span>
                            <span class="rw__score" @click.stop>
                                <a-input-number v-model:value="w.draft" :min="0" :max="assignment.max_score" size="small" :controls="false" placeholder="__" @blur="quickSave(w)" @press-enter="quickSave(w)" />
                                <small>/{{ assignment.max_score }}</small>
                            </span>
                        </div>
                    </div>
                </aside>

                <!-- ===== Visor ===== -->
                <section class="vw">
                    <div v-if="!sel" class="vw__empty"><UserOutlined /> Selecciona un alumno</div>
                    <template v-else-if="sel.submitted && curFile">
                        <div class="vw__bar">
                            <span class="vw__name"><component :is="fIcon(curFile)[0]" :style="{ color: fIcon(curFile)[1] }" /> {{ curFile.name }}</span>
                            <span class="vw__tools">
                                <a-tooltip title="Ver en grande"><button type="button" @click="openFile({ url: curFile.url, name: curFile.name, title: `Entrega de ${sel.list_name}` })"><ExpandOutlined /></button></a-tooltip>
                                <a-tooltip title="Descargar"><a :href="curFile.url + '?download=1'"><DownloadOutlined /></a></a-tooltip>
                            </span>
                        </div>
                        <div class="vw__doc">
                            <p v-if="sel.content" class="vw__text"><b>Respuesta escrita</b>{{ sel.content }}</p>
                            <img v-if="kind === 'image'" :src="curFile.url" alt="" />
                            <iframe v-else-if="kind === 'pdf'" :src="curFile.url" title="Entrega" />
                            <div v-else class="vw__other"><FileUnknownOutlined /><span>Este archivo no se puede previsualizar.</span>
                                <a-button type="primary" shape="round" :href="curFile.url + '?download=1'"><DownloadOutlined /> Descargar</a-button></div>
                        </div>
                    </template>
                    <div v-else-if="sel.submitted" class="vw__doc pad">
                        <p v-if="sel.content" class="vw__text big"><b>Respuesta escrita</b>{{ sel.content }}</p>
                        <div v-else class="vw__none"><InboxOutlined /><b>Entregó sin texto ni archivo</b></div>
                    </div>
                    <div v-else class="vw__none">
                        <InboxOutlined />
                        <b>{{ sel.missing ? 'No entregó a tiempo' : 'Aún no entrega' }}</b>
                        <span>Puedes asignarle una calificación directa (p. ej. trabajo en clase o entregado en papel).</span>
                    </div>
                    <button v-if="!sideOpen" type="button" class="vw__open" title="Calificación y comentarios" @click="sideOpen = true"><DoubleLeftOutlined /></button>
                </section>

                <!-- ===== Calificación y comentarios ===== -->
                <aside v-if="sideOpen && sel" class="gp">
                    <div class="gp__top"><b>Calificación y comentarios</b><button type="button" class="gt__ib" title="Ocultar" @click="sideOpen = false"><DoubleRightOutlined /></button></div>
                    <div class="gp__who">
                        <span :style="{ color: st(sel).c }">{{ st(sel).label }}</span>
                        <template v-if="sel.submitted_at"> · Entregado {{ sel.submitted_at }}</template>
                        <span v-if="sel.late" class="gp__late"><WarningOutlined /> tarde</span>
                    </div>

                    <div v-if="sel.submitted && sel.files?.length" class="gp__files">
                        <button v-for="(f, i) in sel.files" :key="f.url" type="button" class="gp__f" :class="{ on: f === curFile }" @click="fileIdx = i">
                            <CheckOutlined v-if="f === curFile" class="gp__ck" />
                            <component :is="fIcon(f)[0]" v-else :style="{ color: fIcon(f)[1] }" />
                            <span>{{ f.name }}</span>
                        </button>
                    </div>

                    <section class="gp__sec">
                        <h4>Calificación</h4>
                        <label class="gp__grade" :class="{ on: sel.draft !== null && sel.draft !== undefined }">
                            <span class="gp__lbl">Ingresar calificación</span>
                            <a-input-number v-model:value="sel.draft" :min="0" :max="assignment.max_score" :controls="false" :bordered="false" />
                            <span class="gp__max">/{{ assignment.max_score }}</span>
                        </label>
                        <small v-if="hasRubric" class="gp__hint">La rúbrica sugiere la calificación al elegir niveles; puedes escribirla o cambiarla directamente.</small>
                    </section>

                    <section v-if="hasRubric" class="gp__sec">
                        <div class="gp__rh"><h4>Rúbrica</h4><span>{{ sel.rub.some((x) => x != null) ? sel.draft ?? 0 : '' }}/{{ rubricMax }}</span></div>
                        <a class="gp__link" @click="rubricFull = true">Ver rúbrica completa <ExportOutlined /></a>
                        <RubricView :rubric="assignment.rubric" :scores="sel.rub" editable bars hide-head @update:scores="(v) => setRubric(sel, v)" />
                    </section>

                    <section class="gp__sec gp__pv">
                        <CommentThread :key="sel.id" :items="privateOf(sel.id)" type="assignment" :id="assignment.id" private :student-id="sel.id"
                            title="Comentarios privados" placeholder="Agregar un comentario privado…" @change="(l) => (priv = { ...priv, [sel.id]: l })" />
                    </section>
                </aside>
            </div>
        </div>

        <!-- Devolver -->
        <a-modal v-model:open="ret.open" :footer="null" :width="500" centered :closable="false" wrap-class-name="gt-ret">
            <div v-if="sel" class="rt">
                <h3>¿Quieres devolver el trabajo a 1 estudiante?</h3>
                <p>{{ firstName(sel) }} recibirá una notificación y podrá consultar su calificación.</p>
                <div class="rt__row">
                    <span class="rw__av sm"><img v-if="sel.photo_url" :src="sel.photo_url" alt="" /><template v-else>{{ initials(sel.list_name) }}</template></span>
                    <span class="rt__n">{{ sel.list_name }}</span>
                    <span class="rt__s"><b>{{ sel.draft ?? '—' }}</b>/{{ assignment.max_score }}</span>
                </div>
                <a-textarea v-model:value="ret.note" :auto-size="{ minRows: 2, maxRows: 6 }" :maxlength="2000" placeholder="Agregar un comentario privado (opcional)" class="rt__ta" />
                <div class="rt__ft">
                    <a-button type="text" class="rt__b" @click="ret.open = false">Cancelar</a-button>
                    <a-button type="text" class="rt__b" :loading="ret.sending || saving" @click="confirmReturn">Devolver</a-button>
                </div>
            </div>
        </a-modal>

        <SgModal v-model:open="instrOpen" :title="assignment.title" :subtitle="`${assignment.type_label} · ${assignment.max_score} puntos`" :icon="TYPE_ICON[assignment.type]"
            color="linear-gradient(135deg, #1967d2, #14a39a)" width="min(760px, 96vw)" :footer="false">
            <p class="ins__meta2"><CalendarOutlined /> {{ assignment.due_label ? 'Fecha de entrega: ' + assignment.due_label : 'Sin fecha de entrega' }}<template v-if="assignment.criterion"> · {{ assignment.criterion }}</template></p>
            <p v-if="assignment.description" class="ins__desc2">{{ assignment.description }}</p>
            <FileChips v-if="assignment.files?.length" :files="assignment.files" :title="assignment.title" subtitle="Material de la actividad" />
        </SgModal>
        <SgModal v-model:open="rubricFull" title="Rúbrica" :subtitle="sel ? sel.list_name : ''" :icon="TableOutlined" color="linear-gradient(135deg, #1967d2, #7c4ddb)"
            width="min(1100px, 96vw)" :footer="false">
            <RubricView v-if="sel" :rubric="assignment.rubric" :scores="sel.rub" editable @update:scores="(v) => setRubric(sel, v)" />
        </SgModal>
        <SgModal v-model:open="commentsOpen" title="Comentarios de la clase" :subtitle="assignment.title" :icon="TeamOutlined" color="linear-gradient(135deg, #1d7fc9, #0b3f6e)"
            width="min(820px, 96vw)" :footer="false">
            <p class="sg-muted" style="margin-top: 0">Los ven todos los alumnos de la clase. Para hablar solo con un alumno usa los comentarios privados de su trabajo.</p>
            <CommentThread :items="classComments" type="assignment" :id="assignment.id" @change="(l) => (classCount = l.length)" />
        </SgModal>
    </ClaseShell>
</template>

<style scoped>
.gt { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; background: #fff; border-radius: 16px; border: 1px solid #e1e5ea; overflow: hidden;
    box-shadow: 0 10px 30px -24px rgba(10, 31, 68, .5); }
.gt__bar { flex: none; display: flex; align-items: center; gap: 6px; padding: 10px 12px 8px; border-bottom: 1px solid #e0e3e7; position: relative; }
.gt__bar::before { content: ''; position: absolute; inset: 0 0 auto; height: 3px; background: linear-gradient(90deg, #1967d2, #14a39a 50%, #7c4ddb); }
.gt__ib { width: 38px; height: 38px; border-radius: 50%; border: 0; background: none; display: grid; place-items: center; color: #444746; font-size: 17px; cursor: pointer; flex: none; }
.gt__ib:hover:not(:disabled) { background: #f1f3f4; }
.gt__ib:disabled { opacity: .35; cursor: default; }
.gt__ib.on { background: #e8f0fe; color: #1967d2; }
.gt__sep { width: 1px; height: 26px; background: #dadce0; margin: 0 4px; }
.gt__who { display: flex; align-items: center; gap: 4px; border: 1px solid #c4c7c5; border-radius: 8px; padding: 2px 4px 2px 8px; width: 250px; }
.gt__sel { flex: 1; min-width: 0; }
.gt__sel :deep(.ant-select-selection-item) { font-weight: 500; color: #1f1f1f; }
.gt__file, .gt__ttl { display: flex; align-items: center; gap: 8px; min-width: 0; margin-left: 8px; font-size: 16px; color: #1f1f1f; }
.gt__file > .anticon { font-size: 20px; }
.gt__file > span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 360px; }
.gt__ttl { flex-direction: column; align-items: flex-start; gap: 0; }
.gt__ttl b { font-weight: 500; font-size: 15px; }
.gt__ttl small { font-size: 12px; color: #5f6368; }
.gt__sp { flex: 1; }
.gt__state { font-size: 14px; font-weight: 500; margin-right: 6px; white-space: nowrap; }
.gt__ret { min-width: 110px; font-weight: 500; background: #0b57d0 !important; border: 0 !important; }
.gt__ret:disabled { background: #e3e3e3 !important; color: #8e918f !important; }

.gt__main { flex: 1; min-height: 0; display: grid; grid-template-columns: 290px minmax(0, 1fr) 340px; }
.gt__main.no-roster { grid-template-columns: minmax(0, 1fr) 340px; }
.gt__main.no-side { grid-template-columns: 290px minmax(0, 1fr); }
.gt__main.no-roster.no-side { grid-template-columns: minmax(0, 1fr); }

.roster { min-height: 0; display: flex; flex-direction: column; border-right: 1px solid #e0e3e7; background: #fbfcfe; }
.roster__tabs { display: flex; gap: 4px; padding: 10px; border-bottom: 1px solid #eef0f3; flex-wrap: wrap; }
.roster__tabs button { border: 0; background: #eef1f5; color: #475569; border-radius: 999px; padding: 4px 10px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; }
.roster__tabs button i { font-style: normal; opacity: .7; }
.roster__tabs button.on { background: #1967d2; color: #fff; }
.roster__list { flex: 1; min-height: 0; overflow-y: auto; padding: 6px; }
.roster__empty { padding: 30px; text-align: center; color: #94a3b8; }
.rw { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 12px; cursor: pointer; transition: background .12s; }
.rw:hover { background: #f1f5fb; }
.rw.on { background: #e8f0fe; box-shadow: inset 4px 0 0 #1967d2; }
.rw__av { width: 34px; height: 34px; border-radius: 50%; overflow: hidden; display: grid; place-items: center; color: #fff; font-size: 12px; font-weight: 700; background: linear-gradient(135deg, #7c4ddb, #1967d2); flex: none; }
.rw__av.sm { width: 28px; height: 28px; font-size: 11px; }
.rw__av img { width: 100%; height: 100%; object-fit: cover; }
.rw__txt { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.rw__txt b { font-size: 12.5px; font-weight: 600; color: #1f1f1f; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rw__txt small { font-size: 11.5px; font-weight: 600; }
.rw__score { display: flex; align-items: center; gap: 2px; }
.rw__score :deep(.ant-input-number) { width: 48px; border-radius: 8px; }
.rw__score :deep(input) { text-align: center; font-weight: 700; color: #188038; }
.rw__score small { font-size: 11px; color: #94a3b8; }

.vw { position: relative; min-height: 0; display: flex; flex-direction: column; background: #202124; color: #e8eaed; }
.vw__bar { flex: none; display: flex; align-items: center; justify-content: space-between; padding: 10px 18px; }
.vw__name { display: inline-flex; gap: 10px; align-items: center; font-size: 15px; font-weight: 500; color: #fff; min-width: 0; }
.vw__tools { display: flex; gap: 4px; }
.vw__tools button, .vw__tools a { width: 38px; height: 38px; border-radius: 50%; border: 0; background: none; color: #e8eaed; display: grid; place-items: center; font-size: 17px; cursor: pointer; }
.vw__tools button:hover, .vw__tools a:hover { background: rgba(255, 255, 255, .1); }
.vw__doc { flex: 1; min-height: 0; overflow: auto; display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 0 24px 24px; }
.vw__doc.pad { padding-top: 24px; }
.vw__doc img { max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 4px; box-shadow: 0 8px 30px rgba(0, 0, 0, .45); background: #fff; }
.vw__doc iframe { width: min(100%, 980px); flex: 1; min-height: 420px; border: 0; background: #fff; box-shadow: 0 8px 30px rgba(0, 0, 0, .45); }
.vw__text { width: min(100%, 980px); margin: 0; padding: 14px 18px; border-radius: 8px; background: #fff; color: #3c4043; font-size: 14px; white-space: pre-wrap; flex: none; }
.vw__text b { display: block; font-size: 12px; color: #1967d2; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 4px; }
.vw__text.big { min-height: 200px; }
.vw__other { margin: auto; display: flex; flex-direction: column; align-items: center; gap: 12px; color: #bdc1c6; }
.vw__other .anticon { font-size: 44px; }
.vw__none, .vw__empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; text-align: center; color: #bdc1c6; padding: 30px; }
.vw__none .anticon, .vw__empty .anticon { font-size: 40px; color: #5f6368; }
.vw__none b { color: #fff; font-size: 16px; font-weight: 500; }
.vw__none span { max-width: 360px; font-size: 13px; }
.vw__open { position: absolute; top: 10px; right: 10px; width: 36px; height: 36px; border-radius: 50%; border: 0; background: rgba(255, 255, 255, .12); color: #fff; cursor: pointer; }

.gp { min-height: 0; overflow-y: auto; border-left: 1px solid #e0e3e7; background: #fff; display: flex; flex-direction: column; }
.gp__top { display: flex; align-items: center; justify-content: space-between; padding: 14px 12px 4px 20px; }
.gp__top b { font-size: 16px; font-weight: 500; color: #1f1f1f; }
.gp__who { padding: 0 20px 10px; font-size: 12.5px; color: #5f6368; }
.gp__who > span:first-child { font-weight: 600; }
.gp__late { color: #e37400; font-weight: 600; margin-left: 6px; }
.gp__files { display: flex; flex-direction: column; gap: 2px; padding: 4px 12px 14px; border-bottom: 1px solid #e0e3e7; }
.gp__f { display: flex; align-items: center; gap: 14px; padding: 12px 14px; border: 0; border-radius: 99px; background: none; text-align: left; font-family: inherit; font-size: 15px; color: #1f1f1f; cursor: pointer; }
.gp__f span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.gp__f .anticon { font-size: 18px; flex: none; }
.gp__f:hover { background: #f1f3f4; }
.gp__f.on { background: #d3e3fd; color: #041e49; }
.gp__ck { color: #041e49; }
.gp__sec { padding: 16px 20px; border-bottom: 1px solid #e0e3e7; display: flex; flex-direction: column; gap: 10px; }
.gp__sec h4 { margin: 0; font-size: 16px; font-weight: 500; color: #1f1f1f; }
.gp__grade { position: relative; display: flex; align-items: center; height: 56px; border: 1px solid #747775; border-radius: 6px; padding: 0 14px; cursor: text; transition: border-color .12s; margin-top: 6px; }
.gp__grade:focus-within { border: 2px solid #0b57d0; padding: 0 13px; }
.gp__grade.on { border-color: #188038; background: linear-gradient(135deg, #f3fbf6, #fff); }
.gp__grade.dis { cursor: default; }
.gp__lbl { position: absolute; top: -9px; left: 10px; padding: 0 4px; background: #fff; font-size: 12px; color: #444746; }
.gp__grade :deep(.ant-input-number) { flex: 1; background: transparent; }
.gp__grade :deep(input) { text-align: right; font-size: 18px !important; color: #188038 !important; font-weight: 600; }
.gp__grade :deep(.ant-input-number-disabled) { background: transparent; }
.gp__max { font-size: 18px; color: #444746; }
.gp__rh { display: flex; align-items: center; justify-content: space-between; font-size: 16px; color: #1f1f1f; }
.gp__link { align-self: flex-start; display: inline-flex; gap: 8px; align-items: center; color: #0b57d0; font-size: 14px; font-weight: 500; }
.gp__pv { border-bottom: 0; }
.gp__hint { font-size: 12px; color: #5f6368; line-height: 1.4; }

.rt { padding: 6px 4px 0; }
.rt h3 { margin: 0 0 10px; font-size: 22px; font-weight: 400; color: #1f1f1f; line-height: 1.3; }
.rt p { margin: 0 0 18px; font-size: 14px; color: #444746; line-height: 1.5; }
.rt__row { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
.rt__n { flex: 1; font-size: 14px; color: #1f1f1f; }
.rt__s { font-size: 14px; color: #444746; }
.rt__s b { color: #1f1f1f; font-weight: 600; }
.rt__ta { border: 2px solid #0b57d0 !important; border-radius: 16px !important; padding: 12px 14px !important; font-size: 14px; }
.rt__ft { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; }
.rt__b { color: #0b57d0 !important; font-weight: 500; border-radius: 99px !important; }
.ins__meta2 { display: flex; align-items: center; gap: 6px; margin: 0 0 10px; color: #5f6368; font-size: 13px; }
.ins__desc2 { white-space: pre-wrap; color: #3c4043; font-size: 14px; }

@media (max-width: 1280px) { .gt__main { grid-template-columns: 250px minmax(0, 1fr) 310px; } .gt__file > span { max-width: 200px; } }
@media (max-width: 1000px) {
    .gt__main, .gt__main.no-roster, .gt__main.no-side { grid-template-columns: 1fr; overflow-y: auto; }
    .vw { min-height: 420px; }
    .gt__bar { flex-wrap: wrap; }
    .gt__who { width: auto; flex: 1; }
}
</style>
<style>
.gt-ret .ant-modal-content { border-radius: 28px; background: #eef2f9; padding: 24px; }
</style>
