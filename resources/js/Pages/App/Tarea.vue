<script setup>
/**
 * App del alumno · Tarea (como la app de Google Classroom):
 *  - Instrucciones, adjuntos, rúbrica y comentarios de la clase.
 *  - Hoja inferior "Tu trabajo": solo archivos (agregar, quitar), Entregar / Anular la entrega / Volver a enviar
 *    y comentarios privados con el docente.
 */
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import {
    FileTextOutlined, ProjectOutlined, ExperimentOutlined, LinkOutlined, PlayCircleFilled, FilePdfFilled, FileImageFilled, FileWordFilled,
    FileExcelFilled, FileFilled, PlusOutlined, CloseOutlined, UpOutlined, DownOutlined, TeamOutlined, UserOutlined,
} from '@ant-design/icons-vue';
import MobileLayout from '@/Layouts/MobileLayout.vue';
import MDialog from '@/Components/App/MDialog.vue';
import RubricView from '@/Components/Classroom/RubricView.vue';
import CommentThread from '@/Components/Classroom/CommentThread.vue';
import { ripple as vRipple, dueLabel } from '@/lib/ripple';
import { message } from '@/lib/notify';
import { num } from '@/lib/format';

const props = defineProps({ student: Object, children: Array, isGuardian: Boolean, item: Object, classId: Number, canSubmit: Boolean });
const ICON = { tarea: FileTextOutlined, proyecto: ProjectOutlined, actividad: ExperimentOutlined };
const MAX = 5;
const sub = computed(() => props.item.submission);
const submitted = computed(() => !!sub.value?.submitted_at);
const graded = computed(() => sub.value?.score != null);
const status = computed(() => {
    if (graded.value) return { t: `Calificada · ${num(sub.value.score)}/${num(props.item.max_score)}`, c: '#146c2e' };
    if (submitted.value) return { t: sub.value?.status === 'tarde' ? 'Entregada con retraso' : 'Entregada', c: '#146c2e' };
    return props.item.overdue ? { t: 'Falta entregar', c: '#b3261e' } : { t: 'Asignada', c: '#146c2e' };
});
const editable = computed(() => props.canSubmit && (!submitted.value || graded.value));

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
    sheet.value = true;
}
const fileErr = computed(() => form.errors.files || form.errors.content || Object.entries(form.errors).find(([k]) => k.startsWith('files.'))?.[1]);

const sheet = ref(false);
const dlg = ref({ open: false, kind: '' });
const askTurnIn = () => (dlg.value = { open: true, kind: 'turnin' });
const askUnsubmit = () => (dlg.value = { open: true, kind: 'unsubmit' });
const dlgText = computed(() => {
    if (dlg.value.kind === 'unsubmit') return { t: '¿Anular la entrega?', x: 'Podrás agregar o quitar archivos. Recuerda volver a entregarla antes de la fecha límite.', ok: 'Anular' };
    const again = graded.value;
    if (!total.value) return { t: '¿Marcar como completada?', x: 'No adjuntaste archivos. Tu docente verá la tarea como completada.', ok: 'Marcar' };
    return { t: again ? '¿Volver a enviar tu trabajo?' : '¿Entregar tu trabajo?', x: `Se ${again ? 'volverá a enviar' : 'entregará'} con ${total.value} archivo(s).`, ok: again ? 'Volver a enviar' : 'Entregar' };
});
function confirmDlg() {
    if (dlg.value.kind === 'unsubmit') {
        router.post(route('portal.anular', props.item.id), {}, { preserveScroll: true, onFinish: () => (dlg.value.open = false) });
        return;
    }
    form.post(route('portal.entregar', props.item.id), {
        forceFormData: true, preserveScroll: true,
        onSuccess: () => { form.files = []; form.remove_files = []; sheet.value = false; },
        onFinish: () => (dlg.value.open = false),
    });
}

const extOf = (n = '') => n.split('.').pop().toLowerCase();
const isImg = (n) => ['png', 'jpg', 'jpeg', 'gif', 'webp'].includes(extOf(n));
const typeOf = (n = '') => ({ pdf: 'PDF', doc: 'Word', docx: 'Word', xls: 'Excel', xlsx: 'Excel', ppt: 'PowerPoint', pptx: 'PowerPoint', png: 'Imagen', jpg: 'Imagen', jpeg: 'Imagen', gif: 'Imagen', webp: 'Imagen' }[extOf(n)] ?? 'Archivo');
const iconOf = (n = '') => {
    const x = extOf(n);
    if (x === 'pdf') return [FilePdfFilled, '#ea4335'];
    if (isImg(n)) return [FileImageFilled, '#ea4335'];
    if (['doc', 'docx'].includes(x)) return [FileWordFilled, '#4285f4'];
    if (['xls', 'xlsx', 'csv'].includes(x)) return [FileExcelFilled, '#188038'];
    return [FileFilled, '#5f6368'];
};
const showClass = ref(false);
const priv = ref([...(props.item.private ?? [])]);
</script>

<template>
    <MobileLayout :title="item.subject" :subtitle="item.topic || ''" :back="route('app.clases', { clase: classId })">
        <div class="tk">
            <header class="tk__h">
                <span class="tk__ic" :class="`is-${item.type}`"><component :is="ICON[item.type] ?? FileTextOutlined" /></span>
                <h1>{{ item.title }}</h1>
                <p>{{ item.teacher || 'Docente' }}<template v-if="item.created_label"> • {{ item.created_label }}</template></p>
                <div class="tk__meta">
                    <span class="tk__pts" :class="{ ok: graded }">{{ graded ? `${num(sub.score)}/${num(item.max_score)}` : `${num(item.max_score)} puntos` }}</span>
                    <span :class="{ late: item.overdue && !submitted }">{{ item.due_at ? dueLabel(item.due_at) : 'Sin fecha de entrega' }}</span>
                </div>
            </header>

            <p v-if="item.description" class="tk__d">{{ item.description }}</p>

            <div v-if="item.files?.length || item.links?.length" class="tk__att">
                <a v-for="l in item.links ?? []" :key="l.url" v-ripple :href="l.url" target="_blank" rel="noopener" class="fc">
                    <span class="fc__th"><img v-if="l.thumb" :src="l.thumb" alt="" /><LinkOutlined v-else /><PlayCircleFilled v-if="l.youtube" class="fc__play" /></span>
                    <span class="fc__t"><b>{{ l.title }}</b><small>{{ l.kind === 'video' ? 'Video de YouTube' : l.host }}</small></span>
                </a>
                <a v-for="f in item.files ?? []" :key="f.url" v-ripple :href="f.url" target="_blank" rel="noopener" class="fc">
                    <span class="fc__th"><img v-if="isImg(f.name)" :src="f.url" alt="" /><component :is="iconOf(f.name)[0]" v-else :style="{ color: iconOf(f.name)[1] }" /></span>
                    <span class="fc__t"><b>{{ f.name }}</b><small>{{ typeOf(f.name) }}</small></span>
                </a>
            </div>

            <RubricView v-if="item.rubric?.length" :rubric="item.rubric" :scores="sub?.rubric_scores ?? []" bars class="tk__rub" />

            <button v-ripple type="button" class="tk__row" @click="showClass = !showClass">
                <TeamOutlined /><span class="tk__rl">{{ (item.comments ?? []).length ? `${item.comments.length} comentario(s) de la clase` : 'Agregar comentario de la clase' }}</span>
                <component :is="showClass ? UpOutlined : DownOutlined" />
            </button>
            <div v-if="showClass" class="tk__cm">
                <CommentThread :items="item.comments ?? []" type="assignment" :id="item.id" :can-write="canSubmit" title="Comentarios de la clase" />
            </div>
            <div class="tk__space" />
        </div>

        <!-- Hoja inferior: Tu trabajo -->
        <template #sheet>
            <Transition name="scrim"><div v-if="sheet" class="scrim" @click="sheet = false" /></Transition>
            <section class="sh" :class="{ open: sheet }">
                <button type="button" class="sh__grab" aria-label="Tu trabajo" @click="sheet = !sheet"><i /></button>
                <div class="sh__h" @click="sheet = !sheet">
                    <b>Tu trabajo</b><span :style="{ color: status.c }">{{ status.t }}</span>
                    <component :is="sheet ? DownOutlined : UpOutlined" class="sh__chev" />
                </div>

                <div v-if="sheet" class="sh__body">
                    <p v-if="sub?.content" class="sh__text">{{ sub.content }}</p>
                    <div v-for="f in (editable ? kept : sub?.files ?? [])" :key="f.url" class="sf">
                        <a :href="f.url" target="_blank" rel="noopener" class="fc sm">
                            <span class="fc__th"><img v-if="isImg(f.name)" :src="f.url" alt="" /><component :is="iconOf(f.name)[0]" v-else :style="{ color: iconOf(f.name)[1] }" /></span>
                            <span class="fc__t"><b>{{ f.name }}</b><small>{{ typeOf(f.name) }}</small></span>
                        </a>
                        <button v-if="editable" v-ripple type="button" class="sf__x" aria-label="Quitar" @click="form.remove_files.push(f.index)"><CloseOutlined /></button>
                    </div>
                    <div v-for="(f, i) in form.files" :key="f.name + i" class="sf">
                        <span class="fc sm new">
                            <span class="fc__th"><img v-if="previews.get(f)" :src="previews.get(f)" alt="" /><component :is="iconOf(f.name)[0]" v-else :style="{ color: iconOf(f.name)[1] }" /></span>
                            <span class="fc__t"><b>{{ f.name }}</b><small>{{ typeOf(f.name) }} · por entregar</small></span>
                        </span>
                        <button v-ripple type="button" class="sf__x" aria-label="Quitar" @click="form.files.splice(i, 1)"><CloseOutlined /></button>
                    </div>
                    <p v-if="fileErr" class="sh__err">{{ fileErr }}</p>
                </div>

                <div v-if="canSubmit" class="sh__act">
                    <input ref="picker" type="file" multiple hidden @change="pick" />
                    <template v-if="editable">
                        <button v-ripple type="button" class="btn out" :disabled="total >= MAX" @click="picker?.click()"><PlusOutlined /> Agregar archivo</button>
                        <button v-ripple type="button" class="btn fill" :disabled="form.processing" @click="askTurnIn">
                            {{ graded ? 'Volver a enviar' : total ? 'Entregar' : 'Marcar como completada' }}
                        </button>
                    </template>
                    <button v-else v-ripple type="button" class="btn out wide" @click="askUnsubmit">Anular la entrega</button>
                </div>
                <p v-if="!canSubmit" class="sh__ro">Solo consulta: {{ student?.first_name || 'el alumno' }} entrega sus tareas desde su propia cuenta.</p>
                <p v-if="canSubmit && item.overdue && !submitted" class="sh__note">La fecha límite ya pasó: se marcará como entregada con retraso.</p>

                <div v-if="sheet" class="sh__pv">
                    <div class="sh__pvh"><UserOutlined /> Comentarios privados</div>
                    <CommentThread :items="priv" type="assignment" :id="item.id" private :can-write="canSubmit" class="sh__thread"
                        :placeholder="`Comentario para ${item.teacher || 'tu docente'}…`" @change="(l) => (priv = [...l])" />
                </div>
            </section>
        </template>

        <!-- Sin navegación inferior: la hoja "Tu trabajo" ocupa ese lugar -->
        <template #bottom><span /></template>

        <MDialog v-model:open="dlg.open" :title="dlgText.t" :text="dlgText.x" :ok-text="dlgText.ok" :loading="form.processing" @ok="confirmDlg" />
    </MobileLayout>
</template>

<style scoped>
.tk { padding: 8px 20px 0; display: flex; flex-direction: column; gap: 18px; }
.tk__h { display: flex; flex-direction: column; gap: 6px; padding-bottom: 16px; border-bottom: 1px solid #e1e3e1; }
.tk__ic { width: 44px; height: 44px; border-radius: 50%; display: grid; place-items: center; font-size: 20px; background: #d3e3fd; color: #0b57d0; margin-bottom: 6px; }
.tk__ic.is-proyecto { background: #eaddff; color: #6750a4; }
.tk__ic.is-actividad { background: #c4eed0; color: #146c2e; }
.tk__h h1 { margin: 0; font-size: 26px; font-weight: 400; line-height: 1.25; color: var(--m-on); }
.tk__h p { margin: 0; font-size: 14px; color: var(--m-on-var); }
.tk__meta { display: flex; flex-wrap: wrap; gap: 8px 14px; font-size: 14px; color: var(--m-on-var); margin-top: 4px; }
.tk__pts { font-weight: 500; color: var(--m-on); }
.tk__pts.ok { color: var(--m-success); }
.tk__meta .late { color: var(--m-error); font-weight: 500; }
.tk__d { margin: 0; font-size: 15px; line-height: 1.6; white-space: pre-wrap; color: var(--m-on); }
.tk__att { display: flex; flex-direction: column; gap: 10px; }
.fc { display: flex; align-items: stretch; height: 64px; border: 1px solid var(--m-outline-var); border-radius: 12px; overflow: hidden; background: var(--m-surface); color: var(--m-on); }
.fc.sm { height: 58px; flex: 1; min-width: 0; }
.fc.new { border-style: dashed; border-color: var(--m-primary); }
.fc__th { position: relative; width: 72px; flex: none; display: grid; place-items: center; background: var(--m-surface-2); font-size: 26px; overflow: hidden; border-right: 1px solid var(--m-outline-var); }
.fc__th img { width: 100%; height: 100%; object-fit: cover; }
.fc__play { position: absolute; color: #fff; font-size: 22px; filter: drop-shadow(0 1px 3px rgba(0, 0, 0, .5)); }
.fc__t { flex: 1; min-width: 0; padding: 0 14px; display: flex; flex-direction: column; justify-content: center; }
.fc__t b { font-size: 14px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fc__t small { font-size: 12px; color: var(--m-on-var); }
.tk__rub :deep(.rb__head) { font-size: 16px; }
.tk__row { display: flex; align-items: center; gap: 14px; padding: 14px 4px; border: 0; border-top: 1px solid #e1e3e1; background: none; font-family: inherit; font-size: 14px; font-weight: 500; color: var(--m-on); cursor: pointer; text-align: left; }
.tk__rl { flex: 1; }
.tk__cm { margin-top: -10px; }
.tk__space { height: 170px; }

.scrim { position: absolute; inset: 0; background: rgba(0, 0, 0, .32); z-index: 8; }
.scrim-enter-active, .scrim-leave-active { transition: opacity .2s; }
.scrim-enter-from, .scrim-leave-to { opacity: 0; }
.sh { position: absolute; left: 0; right: 0; bottom: 0; z-index: 9; background: var(--m-surface-1); border-radius: 28px 28px 0 0; box-shadow: 0 -2px 12px rgba(0, 0, 0, .14);
    padding: 0 20px calc(16px + env(safe-area-inset-bottom)); max-height: 82%; overflow-y: auto; }
.sh__grab { display: block; width: 100%; border: 0; background: none; padding: 12px 0 6px; cursor: pointer; }
.sh__grab i { display: block; margin: 0 auto; width: 32px; height: 4px; border-radius: 2px; background: var(--m-outline); opacity: .45; }
.sh__h { display: flex; align-items: center; gap: 10px; padding: 4px 0 12px; cursor: pointer; }
.sh__h b { font-size: 20px; font-weight: 400; flex: 1; }
.sh__h span { font-size: 13px; font-weight: 500; }
.sh__chev { color: var(--m-on-var); font-size: 12px; }
.sh__body { display: flex; flex-direction: column; gap: 10px; margin-bottom: 12px; }
.sh__text { margin: 0; font-size: 13.5px; background: var(--m-surface); border-radius: 12px; padding: 10px 12px; white-space: pre-wrap; }
.sf { display: flex; align-items: center; gap: 4px; }
.sf__x { width: 40px; height: 40px; border-radius: 50%; border: 0; background: none; color: var(--m-on-var); font-size: 16px; cursor: pointer; flex: none; }
.sh__ro { margin: 0 0 4px; padding: 10px 12px; border-radius: 12px; background: #fef7e0; color: #5c3c00; font-size: 13px; line-height: 1.4; }
.sh__err { margin: 0; font-size: 12.5px; color: var(--m-error); }
.sh__note { margin: 8px 0 0; font-size: 12px; color: var(--m-on-var); text-align: center; }
.sh__act { display: flex; gap: 10px; }
.btn { flex: 1; height: 44px; border-radius: 22px; font-family: inherit; font-size: 14px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
.btn.out { border: 1px solid var(--m-outline); background: transparent; color: var(--m-primary); }
.btn.fill { border: 0; background: var(--m-primary); color: #fff; }
.btn:disabled { opacity: .45; cursor: default; }
.sh__pv { margin-top: 18px; padding-top: 14px; border-top: 1px solid #e1e3e1; }
.sh__pvh { display: flex; gap: 10px; align-items: center; font-size: 14px; font-weight: 500; margin-bottom: 8px; }
.sh__thread :deep(.ct__head) { display: none; }
</style>
