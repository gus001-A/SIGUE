<script setup>
/**
 * Página de una tarea para el alumno o tutor, como Google Classroom:
 *  - Izquierda: título, docente • fecha, puntos (o calificación) | fecha de entrega, instrucciones, adjuntos,
 *    rúbrica en barras y comentarios de la clase.
 *  - Derecha: "Tu trabajo" (solo archivos: tarjetas con miniatura, agregar, quitar, Entregar,
 *    Anular la entrega o Volver a enviar cuando ya fue calificada) y comentarios privados con el docente.
 */
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    FileTextOutlined, ProjectOutlined, ExperimentOutlined, LinkOutlined, PlayCircleFilled, FilePdfFilled, FileImageFilled, FileWordFilled,
    FileExcelFilled, FileFilled, PlusOutlined, UserOutlined, ArrowLeftOutlined, MessageOutlined, CloseOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import RubricView from '@/Components/Classroom/RubricView.vue';
import CommentThread from '@/Components/Classroom/CommentThread.vue';
import { openFile } from '@/lib/viewer';
import { confirmAction, message } from '@/lib/notify';
import { num } from '@/lib/format';

const props = defineProps({ student: Object, children: Array, isGuardian: Boolean, item: Object, classId: Number, canSubmit: Boolean });

const ICON = { tarea: FileTextOutlined, proyecto: ProjectOutlined, actividad: ExperimentOutlined };
const MAX = 5;
const sub = computed(() => props.item.submission);
const submitted = computed(() => !!sub.value?.submitted_at);
const graded = computed(() => sub.value?.score != null);
const status = computed(() => {
    if (graded.value) return { t: 'Calificada', c: '#3c4043' };
    if (submitted.value) return { t: sub.value?.status === 'tarde' ? 'Entregada con retraso' : 'Entregada', c: '#188038' };
    return props.item.overdue ? { t: 'Falta entregar', c: '#d93025' } : { t: 'Asignado', c: '#188038' };
});
/** Se puede modificar si aún no entrega, o si ya fue calificada (se vuelve a enviar). */
const editable = computed(() => props.canSubmit && (!submitted.value || graded.value));

// ---- Archivos: los guardados (con X para quitar) y los nuevos por subir ----
const form = useForm({ content: sub.value?.content ?? '', files: [], remove_files: [] });
const kept = computed(() => (sub.value?.files ?? []).filter((f) => !form.remove_files.includes(f.index)));
const total = computed(() => kept.value.length + form.files.length);
const picker = ref(null);
const previews = ref(new Map());
function pick(e) {
    const add = [...(e.target.files ?? [])];
    e.target.value = '';
    for (const f of add) {
        if (total.value >= MAX) { message.warning(`Puedes adjuntar máximo ${MAX} archivos.`); break; }
        if (f.size > 20 * 1024 * 1024) { message.warning(`${f.name} pesa más de 20 MB.`); continue; }
        form.files.push(f);
        if (f.type.startsWith('image/')) previews.value.set(f, URL.createObjectURL(f));
    }
}
const removeKept = (f) => form.remove_files.push(f.index);
const removeNew = (i) => form.files.splice(i, 1);
const fileErr = computed(() => form.errors.files || Object.entries(form.errors).find(([k]) => k.startsWith('files.'))?.[1]);

const hasWork = computed(() => total.value > 0);
function turnIn() {
    const again = graded.value;
    confirmAction({
        title: again ? '¿Volver a enviar tu trabajo?' : hasWork.value ? '¿Entregar tu trabajo?' : '¿Marcar como completado?',
        content: hasWork.value ? `Se ${again ? 'volverá a enviar' : 'entregará'} con ${total.value} archivo(s).`
            : 'No adjuntaste archivos. Tu docente verá la tarea como completada.',
        okText: again ? 'Volver a enviar' : hasWork.value ? 'Entregar' : 'Marcar',
        onOk: () => form.post(route('portal.entregar', props.item.id), {
            forceFormData: true, preserveScroll: true, onSuccess: () => { form.files = []; form.remove_files = []; },
        }),
    });
}
const unsubmit = () => confirmAction({
    title: '¿Anular la entrega?', content: 'Anula la entrega para agregar o quitar archivos. Recuerda volver a entregarla antes de la fecha límite.', okText: 'Anular',
    onOk: () => router.post(route('portal.anular', props.item.id), {}, { preserveScroll: true }),
});

const extOf = (n = '') => n.split('.').pop().toLowerCase();
const isImg = (n) => ['png', 'jpg', 'jpeg', 'gif', 'webp'].includes(extOf(n));
const typeOf = (n = '') => ({ pdf: 'PDF', doc: 'Microsoft Word', docx: 'Microsoft Word', xls: 'Microsoft Excel', xlsx: 'Microsoft Excel', ppt: 'PowerPoint', pptx: 'PowerPoint',
    png: 'Imagen', jpg: 'Imagen', jpeg: 'Imagen', gif: 'Imagen', webp: 'Imagen', zip: 'Archivo comprimido', ipynb: 'Objeto binario' }[extOf(n)] ?? 'Archivo');
const iconOf = (n = '') => {
    const x = extOf(n);
    if (x === 'pdf') return [FilePdfFilled, '#ea4335'];
    if (isImg(n)) return [FileImageFilled, '#ea4335'];
    if (['doc', 'docx'].includes(x)) return [FileWordFilled, '#4285f4'];
    if (['xls', 'xlsx', 'csv'].includes(x)) return [FileExcelFilled, '#188038'];
    return [FileFilled, '#5f6368'];
};

const priv = ref([...(props.item.private ?? [])]);
const privateOpen = ref(priv.value.length > 0);
const privTitle = computed(() => (priv.value.length ? `${priv.value.length} comentario${priv.value.length === 1 ? '' : 's'} privado${priv.value.length === 1 ? '' : 's'}` : 'Comentarios privados'));
</script>

<template>
    <AppLayout :title="item.title">
        <div class="tp">
            <div class="tp__crumb">
                <Link :href="route('portal.clases', { clase: classId })" class="tp__back"><ArrowLeftOutlined /></Link>
                <span><b>{{ item.subject }}</b><small v-if="item.topic"> · {{ item.topic }}</small></span>
            </div>

            <div class="tp__grid">
                <!-- Instrucciones -->
                <main class="tp__main">
                    <header class="tp__head">
                        <span class="tp__ic" :class="`is-${item.type}`"><component :is="ICON[item.type] ?? FileTextOutlined" /></span>
                        <div class="tp__ht">
                            <h1>{{ item.title }}</h1>
                            <p>{{ item.teacher || 'Docente' }}<template v-if="item.created_label"> &nbsp;•&nbsp; {{ item.created_label }}</template></p>
                            <p class="tp__pts">
                                <span v-if="graded" class="tp__score">{{ num(sub.score) }}/{{ num(item.max_score) }}</span>
                                <template v-else>{{ num(item.max_score) }} puntos</template>
                                &nbsp;|&nbsp; <span :class="{ late: item.overdue && !submitted }">{{ item.due_at ? `Fecha límite: ${item.due_at}` : 'Sin fecha límite' }}</span>
                            </p>
                        </div>
                    </header>

                    <p v-if="item.description" class="tp__desc">{{ item.description }}</p>

                    <div v-if="item.files?.length || item.links?.length" class="tp__atts">
                        <a v-for="l in item.links ?? []" :key="l.url" :href="l.url" target="_blank" rel="noopener" class="att">
                            <span class="att__t"><b>{{ l.title }}</b><small>{{ l.kind === 'video' ? 'Video de YouTube' : l.host }}</small></span>
                            <span class="att__th"><img v-if="l.thumb" :src="l.thumb" alt="" /><LinkOutlined v-else /><PlayCircleFilled v-if="l.youtube" class="att__play" /></span>
                        </a>
                        <button v-for="f in item.files ?? []" :key="f.url" type="button" class="att" @click="openFile({ url: f.url, name: f.name, title: item.title })">
                            <span class="att__t"><b>{{ f.name }}</b><small>{{ typeOf(f.name) }}</small></span>
                            <span class="att__th"><img v-if="isImg(f.name)" :src="f.url" alt="" /><component :is="iconOf(f.name)[0]" v-else :style="{ color: iconOf(f.name)[1] }" /></span>
                        </button>
                    </div>

                    <RubricView v-if="item.rubric?.length" :rubric="item.rubric" :scores="sub?.rubric_scores ?? []" bars />

                    <section class="tp__cm">
                        <CommentThread :items="item.comments ?? []" type="assignment" :id="item.id" :can-write="canSubmit" title="Comentarios de la clase" compact />
                    </section>
                </main>

                <!-- Tu trabajo y comentarios privados -->
                <aside class="tp__side">
                    <section class="tw">
                        <div class="tw__h"><b>Tu trabajo</b><span :style="{ color: status.c }">{{ status.t }}</span></div>

                        <p v-if="sub?.content" class="tw__text">{{ sub.content }}</p>

                        <div v-for="f in (editable ? kept : sub?.files ?? [])" :key="f.url" class="wf">
                            <button type="button" class="att small" @click="openFile({ url: f.url, name: f.name, title: 'Mi entrega' })">
                                <span class="att__t"><b>{{ f.name }}</b><small>{{ typeOf(f.name) }}</small></span>
                                <span class="att__th"><img v-if="isImg(f.name)" :src="f.url" alt="" /><component :is="iconOf(f.name)[0]" v-else :style="{ color: iconOf(f.name)[1] }" /></span>
                            </button>
                            <button v-if="editable" type="button" class="wf__x" title="Quitar" @click="removeKept(f)"><CloseOutlined /></button>
                        </div>
                        <div v-for="(f, i) in form.files" :key="f.name + i" class="wf">
                            <div class="att small is-new">
                                <span class="att__t"><b>{{ f.name }}</b><small>{{ typeOf(f.name) }} · por entregar</small></span>
                                <span class="att__th"><img v-if="previews.get(f)" :src="previews.get(f)" alt="" /><component :is="iconOf(f.name)[0]" v-else :style="{ color: iconOf(f.name)[1] }" /></span>
                            </div>
                            <button type="button" class="wf__x" title="Quitar" @click="removeNew(i)"><CloseOutlined /></button>
                        </div>
                        <p v-if="fileErr" class="tw__err">{{ fileErr }}</p>

                        <template v-if="canSubmit">
                            <template v-if="editable">
                                <input ref="picker" type="file" multiple hidden @change="pick" />
                                <a-button block size="large" class="tw__ghost" :disabled="total >= MAX" @click="picker?.click()"><PlusOutlined /> Agregar archivo</a-button>
                                <small class="tw__lim">Hasta {{ MAX }} archivos · 20 MB cada uno</small>
                                <a-button type="primary" block size="large" class="tw__btn" :loading="form.processing" @click="turnIn">
                                    {{ graded ? 'Volver a enviar' : hasWork ? 'Entregar' : 'Marcar como completado' }}
                                </a-button>
                            </template>
                            <a-button v-else block size="large" class="tw__ghost" @click="unsubmit">Anular la entrega</a-button>
                            <p v-if="item.overdue && !submitted" class="tw__note">La fecha límite ya pasó: tu trabajo se marcará como entregado con retraso.</p>
                        </template>
                        <p v-else class="tw__note">Vista de consulta para el tutor.</p>
                    </section>

                    <section class="tw tw--pv">
                        <div class="tw__pvh"><UserOutlined /> {{ privTitle }}</div>
                        <a v-if="!privateOpen && canSubmit" class="tw__add" @click="privateOpen = true"><MessageOutlined /> Agregar un comentario a {{ item.teacher || 'tu docente' }}</a>
                        <template v-if="privateOpen || !canSubmit">
                            <CommentThread :items="priv" type="assignment" :id="item.id" private :can-write="canSubmit" class="tw__thread"
                                placeholder="Agregar un comentario privado…" @change="(l) => (priv = [...l])" />
                            <p class="tw__foot">Los comentarios privados solo son visibles para ti y tus profesores.</p>
                        </template>
                    </section>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.tp { flex: 1 1 auto; min-height: 0; overflow-y: auto; background: #fff; border-radius: 16px; border: 1px solid #edf0f3; }
.tp__crumb { display: flex; align-items: center; gap: 10px; padding: 10px 18px; border-bottom: 1px solid #e8eaed; font-size: 14px; color: #3c4043; }
.tp__crumb b { font-weight: 500; }
.tp__crumb small { color: #5f6368; }
.tp__back { width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; color: #5f6368; }
.tp__back:hover { background: #f1f3f4; }
.tp__grid { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 24px; max-width: 1260px; margin: 0 auto; padding: 28px 28px 40px; align-items: start; }
.tp__main { display: flex; flex-direction: column; gap: 20px; min-width: 0; }
.tp__head { display: flex; gap: 16px; padding-bottom: 16px; border-bottom: 1px solid #dadce0; }
.tp__ic { width: 40px; height: 40px; border-radius: 50%; display: grid; place-items: center; background: #e8eaed; color: #3c4043; font-size: 18px; flex: none; margin-top: 4px; }
.tp__ic.is-tarea { background: #e8f0fe; color: #1967d2; }
.tp__ic.is-proyecto { background: #f3e8fd; color: #7c4ddb; }
.tp__ic.is-actividad { background: #e6f4ea; color: #188038; }
.tp__ht { min-width: 0; }
.tp__ht h1 { margin: 0; font-size: 32px; font-weight: 400; color: #1f1f1f; line-height: 1.25; }
.tp__ht p { margin: 8px 0 0; font-size: 14px; color: #3c4043; }
.tp__pts { font-weight: 500; }
.tp__score { color: #188038; font-weight: 600; }
.tp__pts .late { color: #d93025; }
.tp__desc { margin: 0; font-size: 14px; color: #3c4043; white-space: pre-wrap; line-height: 1.65; }
.tp__atts { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 12px; }
.att { display: flex; align-items: stretch; height: 80px; border: 1px solid #dadce0; border-radius: 8px; overflow: hidden; background: #fff; text-align: left; font-family: inherit;
    cursor: pointer; padding: 0; color: inherit; width: 100%; }
.att:hover { border-color: #1967d2; }
.att.is-new { cursor: default; border-style: dashed; border-color: #1967d2; }
.att__t { flex: 1; min-width: 0; padding: 12px 16px; display: flex; flex-direction: column; justify-content: center; }
.att__t b { font-weight: 500; font-size: 14px; color: #1f1f1f; text-decoration: underline; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.att__t small { font-size: 12.5px; color: #5f6368; }
.att__th { position: relative; width: 80px; flex: none; display: grid; place-items: center; background: #f8f9fa; color: #5f6368; font-size: 30px; border-left: 1px solid #dadce0; overflow: hidden; }
.att__th img { width: 100%; height: 100%; object-fit: cover; }
.att__play { position: absolute; color: #fff; font-size: 22px; filter: drop-shadow(0 1px 4px rgba(0, 0, 0, .5)); }
.tp__cm { padding-top: 16px; border-top: 1px solid #dadce0; }
.tp__side { display: flex; flex-direction: column; gap: 16px; position: sticky; top: 16px; }
.tw { background: #f0f4f9; border-radius: 16px; padding: 16px; display: flex; flex-direction: column; gap: 10px; }
.tw__h { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; margin-bottom: 4px; }
.tw__h b { font-size: 22px; font-weight: 400; color: #1f1f1f; }
.tw__h span { font-size: 13px; font-weight: 500; }
.tw__text { margin: 0; font-size: 13px; color: #3c4043; white-space: pre-wrap; background: #fff; border-radius: 8px; padding: 10px 12px; border: 1px solid #dadce0; }
.tw__lim { margin-top: -4px; text-align: center; font-size: 11.5px; color: #5f6368; }
.wf { display: flex; align-items: center; gap: 4px; }
.wf .att { flex: 1; min-width: 0; }
.wf__x { width: 34px; height: 34px; border-radius: 50%; border: 0; background: none; color: #444746; cursor: pointer; flex: none; font-size: 15px; }
.wf__x:hover { background: rgba(0, 0, 0, .06); }
.tw__ghost { border-radius: 99px !important; border-color: #747775 !important; color: #0b57d0 !important; font-weight: 500; background: transparent !important; }
.tw__ghost:hover { background: rgba(11, 87, 208, .06) !important; }
.tw__btn { border-radius: 99px !important; font-weight: 500; background: #0b57d0 !important; border: 0 !important; }
.tw__note { margin: 2px 0 0; font-size: 12px; font-style: italic; color: #444746; text-align: center; }
.tw__err { margin: -4px 0 0; font-size: 12px; color: #d93025; }
.tw__pvh { display: flex; align-items: center; gap: 12px; font-size: 14px; font-weight: 500; color: #1f1f1f; }
.tw__add { display: inline-flex; align-items: center; gap: 10px; color: #0b57d0; font-size: 14px; font-weight: 500; padding: 6px 16px; }
.tw__thread :deep(.ct__head) { display: none; }
.tw__foot { margin: 0; font-size: 12px; color: #444746; }
@media (max-width: 900px) { .tp__grid { grid-template-columns: 1fr; padding: 16px; } .tp__side { position: static; } .tp__ht h1 { font-size: 24px; } }
</style>
