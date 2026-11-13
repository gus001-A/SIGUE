<script setup>
/**
 * "Ver entrega" (como Google Classroom): el trabajo del alumno con vista previa de sus archivos a la izquierda;
 * a la derecha la calificación (directa o por rúbrica), la retroalimentación, los comentarios privados y "Devolver".
 */
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { PaperClipOutlined, ExpandOutlined, FileUnknownOutlined, InboxOutlined, SendOutlined, SaveOutlined, ClockCircleOutlined, LeftOutlined, RightOutlined } from '@ant-design/icons-vue';
import SgModal from '@/Components/SgModal.vue';
import RubricView from '@/Components/Classroom/RubricView.vue';
import CommentThread from '@/Components/Classroom/CommentThread.vue';
import { openFile, fileKind } from '@/lib/viewer';
import { message } from '@/lib/notify';
import { FileTextOutlined } from '@ant-design/icons-vue';

const props = defineProps({
    open: Boolean,
    assignmentId: { type: Number, default: null },
    studentId: { type: Number, default: null },
    studentIds: { type: Array, default: () => [] },      // para pasar al alumno anterior / siguiente
});
const emit = defineEmits(['update:open', 'saved', 'navigate']);

const data = ref(null);
const loading = ref(false);
const draft = ref(null);
const note = ref('');
const rub = ref([]);
const fileIdx = ref(0);
const saving = ref(false);

async function load() {
    if (!props.open || !props.assignmentId || !props.studentId) return;
    loading.value = true;
    try {
        const { data: d } = await axios.get(route('docente.actividades.entrega', [props.assignmentId, props.studentId]));
        data.value = d;
        draft.value = d.work.score;
        note.value = d.work.feedback ?? '';
        rub.value = [...(d.work.rubric_scores ?? [])];
        fileIdx.value = 0;
    } finally { loading.value = false; }
}
watch(() => [props.open, props.assignmentId, props.studentId], load, { immediate: true });

const a = computed(() => data.value?.assignment);
const w = computed(() => data.value?.work);
const hasRubric = computed(() => (a.value?.rubric ?? []).length > 0);
const cur = computed(() => w.value?.files?.[fileIdx.value] ?? null);
const kind = computed(() => (cur.value ? fileKind(cur.value.name, cur.value.url) : null));
const status = computed(() => {
    if (!w.value) return '';
    if (w.value.score != null && w.value.status === 'devuelta') return 'Devuelta';
    if (w.value.score != null) return 'Calificada';
    if (w.value.submitted) return w.value.late ? 'Entregada con retraso' : 'Entregada';
    return w.value.missing ? 'Sin entregar' : 'Asignada';
});
const pos = computed(() => props.studentIds.indexOf(props.studentId));

function setRubric(scores) {
    rub.value = scores;
    const picked = scores.filter((x) => x !== null && x !== undefined);
    draft.value = picked.length ? a.value.rubric.reduce((t, c, i) => t + (scores[i] != null ? Number(c.levels[scores[i]]?.points ?? 0) : 0), 0) : null;
}
async function save(returnIt) {
    if (draft.value != null && (draft.value < 0 || draft.value > a.value.max_score)) { message.warning(`La calificación va de 0 a ${a.value.max_score}.`); return; }
    saving.value = true;
    try {
        const body = { student_id: props.studentId, score: draft.value ?? null, ...(returnIt ? { status: 'devuelta' } : {}),
            ...(hasRubric.value ? { rubric_scores: a.value.rubric.map((_, i) => rub.value[i] ?? null) } : {}) };
        const { data: r } = await axios.post(route('docente.actividades.calificar', props.assignmentId), body, { headers: { Accept: 'application/json' } });
        w.value.score = r.score; w.value.status = r.status;
        emit('saved', { assignmentId: props.assignmentId, studentId: props.studentId, score: r.score, status: r.status });
        message.success(returnIt ? 'Trabajo devuelto al alumno.' : 'Calificación guardada.');
    } catch (e) {
        message.error(e.response?.data?.message || 'No se pudo guardar.');
    } finally { saving.value = false; }
}
</script>

<template>
    <SgModal :open="open" :title="data?.student?.name ?? 'Entrega'" :subtitle="a ? `${a.title} · ${a.max_score} pts` : ''" :icon="FileTextOutlined"
        color="linear-gradient(135deg, #1d7fc9, #0b3f6e)" width="min(1280px, 96vw)" :footer="false" @update:open="emit('update:open', $event)">
        <a-spin :spinning="loading">
            <div v-if="data" class="sm">
                <!-- Trabajo -->
                <section class="sm__work">
                    <div class="sm__bar">
                        <span class="sm__st" :class="{ red: status === 'Sin entregar' }">{{ status }}</span>
                        <span v-if="w.submitted_at" class="sm__when"><ClockCircleOutlined /> {{ w.submitted_at }}</span>
                        <span class="sm__nav">
                            <a-button size="small" :disabled="pos <= 0" @click="emit('navigate', studentIds[pos - 1])"><LeftOutlined /></a-button>
                            <a-button size="small" :disabled="pos < 0 || pos >= studentIds.length - 1" @click="emit('navigate', studentIds[pos + 1])"><RightOutlined /></a-button>
                        </span>
                    </div>
                    <p v-if="w.content" class="sm__text">{{ w.content }}</p>
                    <template v-if="w.files.length">
                        <div class="sm__tabs">
                            <button v-for="(f, i) in w.files" :key="f.url" type="button" :class="{ on: i === fileIdx }" :title="f.name" @click="fileIdx = i"><PaperClipOutlined /> <span>{{ f.name }}</span></button>
                            <a-button size="small" class="sm__big" @click="openFile({ url: cur.url, name: cur.name, title: data.student.name })"><ExpandOutlined /> Abrir</a-button>
                        </div>
                        <div class="sm__preview">
                            <img v-if="kind === 'image'" :src="cur.url" alt="" />
                            <iframe v-else-if="kind === 'pdf'" :src="cur.url" title="Entrega" />
                            <div v-else class="sm__other"><FileUnknownOutlined /><span>Este archivo no se puede previsualizar.</span><a :href="cur.url + '?download=1'">Descargar</a></div>
                        </div>
                    </template>
                    <div v-if="!w.content && !w.files.length" class="sm__none">
                        <InboxOutlined />
                        <b>{{ w.submitted ? 'Entregó sin texto ni archivos' : 'Aún no hay entrega' }}</b>
                        <span v-if="!w.submitted">Puedes calificar de forma directa (por ejemplo, un trabajo entregado en papel).</span>
                    </div>
                </section>

                <!-- Calificación -->
                <aside class="sm__side">
                    <div class="sm__grade">
                        <label>Calificación</label>
                        <div class="sm__gbox">
                            <a-input-number v-model:value="draft" :min="0" :max="a.max_score" :controls="false" :disabled="hasRubric" placeholder="__" />
                            <span>/ {{ a.max_score }}</span>
                        </div>
                        <small v-if="hasRubric">Se calcula con la rúbrica.</small>
                    </div>
                    <RubricView v-if="hasRubric" :rubric="a.rubric" :scores="rub" editable compact @update:scores="setRubric" />
                    <div class="sm__actions">
                        <a-button :loading="saving" @click="save(false)"><SaveOutlined /> Guardar</a-button>
                        <a-button type="primary" :loading="saving" @click="save(true)"><SendOutlined /> Devolver</a-button>
                    </div>
                    <div class="sm__private">
                        <CommentThread :key="studentId" :items="data.private" type="assignment" :id="a.id" private :student-id="studentId" title="Comentarios privados" />
                    </div>
                </aside>
            </div>
        </a-spin>
    </SgModal>
</template>

<style scoped>
.sm { display: grid; grid-template-columns: minmax(0, 1fr) 380px; gap: 20px; min-height: 480px; }
.sm__work { display: flex; flex-direction: column; gap: 10px; min-width: 0; }
.sm__bar { display: flex; align-items: center; gap: 12px; padding-bottom: 10px; border-bottom: 1px solid #e8edf3; }
.sm__st { font-weight: 700; color: #0a1f44; }
.sm__st.red { color: #d9363e; }
.sm__when { font-size: 12.5px; color: #5d7085; display: inline-flex; gap: 5px; align-items: center; }
.sm__nav { margin-left: auto; display: flex; gap: 6px; }
.sm__text { margin: 0; padding: 10px 12px; background: #f7f9fb; border-radius: 10px; color: #334155; white-space: pre-wrap; }
.sm__tabs { display: flex; align-items: center; gap: 6px; overflow-x: auto; }
.sm__tabs button { display: inline-flex; align-items: center; gap: 5px; max-width: 220px; padding: 4px 10px; border-radius: 8px; border: 1px solid #e3e8ef; background: #fff; cursor: pointer;
    font-family: inherit; font-size: 12.5px; color: #44566c; flex: none; }
.sm__tabs button span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sm__tabs button.on { border-color: #1d7fc9; color: #1d7fc9; }
.sm__big { margin-left: auto; flex: none; }
.sm__preview { flex: 1; min-height: 420px; border: 1px solid #e3e8ef; border-radius: 10px; overflow: hidden; display: grid; place-items: center; background: #f1f4f8; }
.sm__preview img { max-width: 100%; max-height: 560px; object-fit: contain; }
.sm__preview iframe { width: 100%; height: 100%; min-height: 520px; border: 0; background: #fff; }
.sm__other, .sm__none { display: flex; flex-direction: column; align-items: center; gap: 6px; color: #5d7085; text-align: center; padding: 24px; }
.sm__other .anticon, .sm__none .anticon { font-size: 34px; color: #9aa7b6; }
.sm__none { flex: 1; justify-content: center; border: 1px dashed #d6dee8; border-radius: 10px; }
.sm__none b { color: #0a1f44; }
.sm__side { display: flex; flex-direction: column; gap: 14px; padding-left: 20px; border-left: 1px solid #e8edf3; min-width: 0; }
.sm__side label { display: block; font-size: 12px; font-weight: 700; color: #5d7085; margin-bottom: 4px; }
.sm__gbox { display: flex; align-items: baseline; gap: 8px; }
.sm__gbox :deep(.ant-input-number) { width: 100px; }
.sm__gbox :deep(input) { font-size: 22px; font-weight: 800; text-align: center; height: 44px; }
.sm__gbox span { font-size: 16px; color: #5d7085; }
.sm__grade small { font-size: 12px; color: #94a3b8; }
.sm__actions { display: flex; gap: 8px; justify-content: flex-end; }
.sm__private { padding-top: 12px; border-top: 1px solid #e8edf3; }
@media (max-width: 960px) { .sm { grid-template-columns: 1fr; } .sm__side { padding-left: 0; border-left: 0; } }
</style>
