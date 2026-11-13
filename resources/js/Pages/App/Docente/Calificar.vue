<script setup>
/**
 * App del docente · Calificar una tarea: resumen (entregados / asignados / calificados), filtros,
 * lista de alumnos y hoja con su trabajo (archivos), calificación directa, rúbrica en barras y Devolver
 * con comentario privado opcional.
 */
import { computed, ref } from 'vue';
import axios from 'axios';
import {
    FilePdfFilled, FileImageFilled, FileWordFilled, FileExcelFilled, FileFilled, CloseOutlined, LeftOutlined, RightOutlined, InboxOutlined, WarningOutlined,
} from '@ant-design/icons-vue';
import MobileLayout from '@/Layouts/MobileLayout.vue';
import MDialog from '@/Components/App/MDialog.vue';
import RubricView from '@/Components/Classroom/RubricView.vue';
import CommentThread from '@/Components/Classroom/CommentThread.vue';
import { ripple as vRipple, initials } from '@/lib/ripple';
import { message } from '@/lib/notify';

const props = defineProps({
    course: Object, assignment: Object, works: { type: Array, default: () => [] }, students: Array,
    classComments: Array, privateComments: { type: [Object, Array], default: () => ({}) }, section: String, periods: Array, periodId: [Number, null],
});
const hasRubric = computed(() => (props.assignment.rubric ?? []).length > 0);
const list = ref(props.works.map((w) => ({ ...w, draft: w.score, rub: [...(w.rubric_scores ?? [])] })));
const priv = ref(Array.isArray(props.privateComments) ? {} : { ...props.privateComments });

const FILTERS = computed(() => [
    { k: 'entregados', l: 'Entregados', n: list.value.filter((w) => w.submitted && w.score == null).length },
    { k: 'calificados', l: 'Calificados', n: list.value.filter((w) => w.score != null).length },
    { k: 'asignados', l: 'Asignados', n: list.value.filter((w) => !w.submitted && w.score == null).length },
]);
const filter = ref(FILTERS.value[0].n ? 'entregados' : 'asignados');
const shown = computed(() => list.value.filter((w) => (filter.value === 'entregados' ? w.submitted && w.score == null
    : filter.value === 'calificados' ? w.score != null : !w.submitted && w.score == null)));
const st = (w) => (w.score != null ? { t: `${Number(w.score)}/${props.assignment.max_score}`, c: '#146c2e' }
    : w.submitted ? { t: w.late ? 'Entregado tarde' : 'Entregado', c: w.late ? '#b06000' : '#0b57d0' }
        : w.missing ? { t: 'Falta', c: '#b3261e' } : { t: 'Asignado', c: '#444746' });

const selId = ref(null);
const sel = computed(() => list.value.find((w) => w.id === selId.value) ?? null);
const idx = computed(() => shown.value.findIndex((w) => w.id === selId.value));
const go = (d) => { const n = shown.value[idx.value + d]; if (n) selId.value = n.id; };
function setRubric(w, scores) {
    w.rub = scores;
    const picked = scores.filter((x) => x != null);
    w.draft = picked.length ? props.assignment.rubric.reduce((t, c, i) => t + (scores[i] != null ? Number(c.levels[scores[i]]?.points ?? 0) : 0), 0) : w.draft;
}

const ret = ref({ open: false, note: '', sending: false });
function askReturn() {
    const w = sel.value;
    if (w.draft != null && w.draft !== '' && (w.draft < 0 || w.draft > props.assignment.max_score)) { message.warning(`La calificación debe estar entre 0 y ${props.assignment.max_score}.`); return; }
    ret.value = { open: true, note: '', sending: false };
}
async function confirmReturn() {
    const w = sel.value;
    ret.value.sending = true;
    try {
        const note = ret.value.note.trim();
        if (note) {
            const { data } = await axios.post(route('classroom.comentarios.store'), { type: 'assignment', id: props.assignment.id, body: note, private: true, student_id: w.id }, { headers: { Accept: 'application/json' } });
            priv.value = { ...priv.value, [w.id]: [...(priv.value[w.id] ?? []), data] };
        }
        const score = w.draft === '' || w.draft == null ? null : Number(w.draft);
        await axios.post(route('docente.actividades.calificar', props.assignment.id),
            { student_id: w.id, score, ...(hasRubric.value ? { rubric_scores: props.assignment.rubric.map((_, i) => w.rub[i] ?? null) } : {}) },
            { headers: { Accept: 'application/json' } });
        w.score = score;
        w.rubric_scores = [...w.rub];
        message.success(`Trabajo devuelto a ${w.list_name.split(',')[1]?.trim() || w.list_name}.`);
        ret.value.open = false;
        const next = shown.value.find((x) => x.id !== w.id && x.submitted && x.score == null);
        selId.value = next?.id ?? null;
    } catch (e) {
        message.error(e.response?.data?.message || 'No se pudo guardar la calificación.');
    } finally { ret.value.sending = false; }
}

const extOf = (n = '') => n.split('.').pop().toLowerCase();
const isImg = (n) => ['png', 'jpg', 'jpeg', 'gif', 'webp'].includes(extOf(n));
const iconOf = (n = '') => {
    const x = extOf(n);
    if (x === 'pdf') return [FilePdfFilled, '#ea4335'];
    if (isImg(n)) return [FileImageFilled, '#ea4335'];
    if (['doc', 'docx'].includes(x)) return [FileWordFilled, '#4285f4'];
    if (['xls', 'xlsx', 'csv'].includes(x)) return [FileExcelFilled, '#188038'];
    return [FileFilled, '#5f6368'];
};
</script>

<template>
    <MobileLayout :title="assignment.title" :subtitle="`${course.subject} · ${course.group}`" :back="route('app.docente.clase', course.id)">
        <div class="sum">
            <div><b>{{ list.filter((w) => w.submitted).length }}</b><small>Entregados</small></div>
            <div><b>{{ list.filter((w) => !w.submitted).length }}</b><small>Asignados</small></div>
            <div><b>{{ list.filter((w) => w.score != null).length }}</b><small>Calificados</small></div>
        </div>
        <p class="meta">{{ assignment.max_score }} puntos · {{ assignment.due_label ? `Entrega: ${assignment.due_label}` : 'Sin fecha de entrega' }}</p>

        <div class="chips">
            <button v-for="f in FILTERS" :key="f.k" v-ripple type="button" :class="{ on: filter === f.k }" @click="filter = f.k">{{ f.l }} <i>{{ f.n }}</i></button>
        </div>

        <p v-if="!shown.length" class="empty"><InboxOutlined /> Nadie en esta lista.</p>
        <button v-for="w in shown" :key="w.id" v-ripple type="button" class="row" @click="selId = w.id">
            <span class="av"><img v-if="w.photo_url" :src="w.photo_url" alt="" /><template v-else>{{ initials(w.list_name) }}</template></span>
            <span class="row__t"><b>{{ w.list_name }}</b><small v-if="w.submitted_at">Entregó {{ w.submitted_at }}<template v-if="w.files?.length"> · {{ w.files.length }} archivo(s)</template></small></span>
            <span class="row__s" :style="{ color: st(w).c }">{{ st(w).t }}</span>
        </button>

        <template #bottom><span /></template>
        <template #sheet>
            <Transition name="fade"><div v-if="sel" class="scrim" @click="selId = null" /></Transition>
            <section v-if="sel" class="sh">
                <header class="sh__h">
                    <button v-ripple type="button" class="ib" :disabled="idx <= 0" @click="go(-1)"><LeftOutlined /></button>
                    <span class="av"><img v-if="sel.photo_url" :src="sel.photo_url" alt="" /><template v-else>{{ initials(sel.list_name) }}</template></span>
                    <span class="sh__who"><b>{{ sel.list_name }}</b><small :style="{ color: st(sel).c }">{{ st(sel).t }}</small></span>
                    <button v-ripple type="button" class="ib" :disabled="idx < 0 || idx >= shown.length - 1" @click="go(1)"><RightOutlined /></button>
                    <button v-ripple type="button" class="ib" @click="selId = null"><CloseOutlined /></button>
                </header>

                <div class="sh__body">
                    <p v-if="sel.late" class="warn"><WarningOutlined /> Entregado después de la fecha límite.</p>
                    <p v-if="!sel.submitted" class="none">{{ sel.missing ? 'No entregó a tiempo.' : 'Aún no entrega.' }} Puedes calificar directo (p. ej. trabajo en papel).</p>
                    <p v-if="sel.content" class="txt">{{ sel.content }}</p>
                    <a v-for="f in sel.files" :key="f.url" v-ripple :href="f.url" target="_blank" rel="noopener" class="fc">
                        <span class="fc__th"><img v-if="isImg(f.name)" :src="f.url" alt="" /><component :is="iconOf(f.name)[0]" v-else :style="{ color: iconOf(f.name)[1] }" /></span>
                        <span class="fc__t"><b>{{ f.name }}</b><small>Toca para abrir</small></span>
                    </a>

                    <label class="grade" :class="{ on: sel.draft != null && sel.draft !== '' }">
                        <span class="grade__l">Calificación</span>
                        <input v-model.number="sel.draft" type="number" inputmode="decimal" :min="0" :max="assignment.max_score" placeholder="—" />
                        <span class="grade__m">/{{ assignment.max_score }}</span>
                    </label>
                    <small v-if="hasRubric" class="hint">La rúbrica sugiere la calificación; puedes escribirla directo.</small>
                    <RubricView v-if="hasRubric" :rubric="assignment.rubric" :scores="sel.rub" editable bars @update:scores="(v) => setRubric(sel, v)" />

                    <div class="pv">
                        <CommentThread :key="sel.id" :items="priv[sel.id] ?? []" type="assignment" :id="assignment.id" private :student-id="sel.id" title="Comentarios privados"
                            placeholder="Comentario privado…" @change="(l) => (priv = { ...priv, [sel.id]: l })" />
                    </div>
                </div>
                <div class="sh__act"><button v-ripple type="button" class="btn" @click="askReturn">Devolver</button></div>
            </section>
        </template>

        <MDialog v-model:open="ret.open" :title="`¿Devolver el trabajo a ${sel?.list_name.split(',')[1]?.trim() || sel?.list_name}?`"
            :text="`Calificación: ${sel?.draft ?? '—'}/${assignment.max_score}. Recibirá una notificación.`" ok-text="Devolver" :loading="ret.sending" @ok="confirmReturn">
            <textarea v-model="ret.note" class="dlg__ta" rows="3" maxlength="2000" placeholder="Comentario privado (opcional)" />
        </MDialog>
    </MobileLayout>
</template>

<style scoped>
.sum { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; padding: 6px 16px 0; }
.sum div { background: var(--m-surface); border-radius: 16px; padding: 10px; display: flex; flex-direction: column; align-items: center; border: 1px solid #e1e3e1; }
.sum b { font-size: 24px; font-weight: 400; }
.sum small { font-size: 12px; color: var(--m-on-var); }
.meta { margin: 8px 18px 0; font-size: 12.5px; color: var(--m-on-var); }
.chips { display: flex; gap: 8px; padding: 10px 16px; overflow-x: auto; scrollbar-width: none; }
.chips button { flex: none; height: 32px; padding: 0 12px; border-radius: 8px; border: 1px solid var(--m-outline-var); background: none; font-family: inherit; font-size: 13px; font-weight: 500; color: var(--m-on-var); cursor: pointer; display: inline-flex; gap: 6px; align-items: center; }
.chips button.on { background: var(--m-sec-c); border-color: transparent; color: #001d35; }
.chips i { font-style: normal; opacity: .7; }
.empty { margin: 30px; text-align: center; color: var(--m-on-var); display: flex; gap: 8px; justify-content: center; }
.row { width: 100%; display: flex; align-items: center; gap: 14px; padding: 10px 16px; border: 0; background: none; text-align: left; font-family: inherit; cursor: pointer; color: var(--m-on); }
.av { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; display: grid; place-items: center; background: #7c4ddb; color: #fff; font-size: 13px; font-weight: 500; flex: none; }
.av img { width: 100%; height: 100%; object-fit: cover; }
.row__t { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.row__t b { font-size: 14.5px; font-weight: 400; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.row__t small { font-size: 12px; color: var(--m-on-var); }
.row__s { font-size: 13px; font-weight: 500; white-space: nowrap; }

.scrim { position: absolute; inset: 0; background: rgba(0, 0, 0, .32); z-index: 8; }
.fade-enter-active, .fade-leave-active { transition: opacity .2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.sh { position: absolute; left: 0; right: 0; bottom: 0; top: 48px; z-index: 9; background: var(--m-surface-1); border-radius: 28px 28px 0 0; display: flex; flex-direction: column; box-shadow: 0 -2px 12px rgba(0, 0, 0, .15); }
.sh__h { display: flex; align-items: center; gap: 8px; padding: 12px 8px 10px 4px; border-bottom: 1px solid #e1e3e1; }
.sh__who { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.sh__who b { font-size: 15px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sh__who small { font-size: 12.5px; font-weight: 500; }
.ib { width: 40px; height: 40px; border-radius: 50%; border: 0; background: none; color: var(--m-on-var); font-size: 15px; cursor: pointer; flex: none; }
.ib:disabled { opacity: .3; }
.sh__body { flex: 1; min-height: 0; overflow-y: auto; padding: 14px 16px; display: flex; flex-direction: column; gap: 10px; }
.warn { margin: 0; font-size: 13px; color: #8a4a00; background: #fef7e0; padding: 8px 12px; border-radius: 12px; display: flex; gap: 8px; align-items: center; }
.none { margin: 0; font-size: 13.5px; color: var(--m-on-var); background: var(--m-surface-2); padding: 12px; border-radius: 12px; }
.txt { margin: 0; font-size: 13.5px; background: var(--m-surface); padding: 10px 12px; border-radius: 12px; white-space: pre-wrap; }
.fc { display: flex; align-items: stretch; height: 62px; border: 1px solid var(--m-outline-var); border-radius: 12px; overflow: hidden; background: var(--m-surface); color: var(--m-on); }
.fc__th { width: 70px; flex: none; display: grid; place-items: center; background: var(--m-surface-2); font-size: 26px; overflow: hidden; border-right: 1px solid var(--m-outline-var); }
.fc__th img { width: 100%; height: 100%; object-fit: cover; }
.fc__t { flex: 1; min-width: 0; padding: 0 14px; display: flex; flex-direction: column; justify-content: center; }
.fc__t b { font-size: 14px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fc__t small { font-size: 12px; color: var(--m-on-var); }
.grade { position: relative; display: flex; align-items: center; height: 60px; border: 1px solid var(--m-outline); border-radius: 8px; padding: 0 16px; margin-top: 10px; background: var(--m-surface); }
.grade:focus-within { border: 2px solid #0b57d0; padding: 0 15px; }
.grade.on { border-color: #146c2e; }
.grade__l { position: absolute; top: -9px; left: 12px; padding: 0 4px; background: var(--m-surface-1); font-size: 12px; color: var(--m-on-var); }
.grade input { flex: 1; border: 0; outline: 0; background: none; font-family: inherit; font-size: 22px; font-weight: 500; color: #146c2e; text-align: right; min-width: 0; }
.grade__m { font-size: 20px; color: var(--m-on-var); margin-left: 4px; }
.hint { font-size: 12px; color: var(--m-on-var); }
.pv { margin-top: 6px; padding-top: 12px; border-top: 1px solid #e1e3e1; }
.sh__act { padding: 12px 16px calc(12px + env(safe-area-inset-bottom)); background: var(--m-surface-2); }
.btn { width: 100%; height: 52px; border-radius: 26px; border: 0; background: #0b57d0; color: #fff; font-family: inherit; font-size: 15px; font-weight: 500; cursor: pointer; }
.dlg__ta { width: 100%; border: 2px solid #0b57d0; border-radius: 16px; padding: 10px 12px; font-family: inherit; font-size: 14px; resize: none; outline: 0; background: #fff; margin-top: 6px; }
</style>
