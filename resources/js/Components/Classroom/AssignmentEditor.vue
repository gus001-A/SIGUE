<script setup>
/**
 * Crear / editar tarea en un modal amplio, como el editor de Google Classroom:
 *  - Izquierda: título, instrucciones y adjuntos (archivos, YouTube y vínculos) con la barra "Adjuntar".
 *  - Derecha: para quién, puntos, fecha límite, tema, periodo, criterio de evaluación y rúbrica.
 */
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import {
    FileTextOutlined, ProjectOutlined, ExperimentOutlined, TeamOutlined, YoutubeFilled, UploadOutlined, LinkOutlined, CloseOutlined, DownOutlined,
    TableOutlined, PlusOutlined, FilePdfOutlined, FileImageOutlined, FileOutlined, InfoCircleOutlined, PlayCircleFilled,
} from '@ant-design/icons-vue';
import axios from 'axios';
import RubricEditor from '@/Components/Classroom/RubricEditor.vue';
import SgModal from '@/Components/SgModal.vue';
import { message } from '@/lib/notify';
import dayjs from 'dayjs';

const props = defineProps({
    open: Boolean, classId: Number, editing: { type: Object, default: null }, type: { type: String, default: 'tarea' },
    periods: { type: Array, default: () => [] }, topics: { type: Array, default: () => [] }, options: Object, studentsTotal: { type: Number, default: 0 },
});
const emit = defineEmits(['update:open']);

const TYPE = { tarea: { label: 'Tarea', icon: FileTextOutlined }, proyecto: { label: 'Proyecto', icon: ProjectOutlined }, actividad: { label: 'Actividad', icon: ExperimentOutlined } };
const blank = () => ({ type: props.type, title: '', description: '', due_at: null, max_score: 10, period_id: props.periods[0]?.value ?? null, evaluation_criterion_id: null, topic_id: null,
    is_published: true, files: [], remove_files: [], links: [], rubric: [] });
const form = useForm(blank());
watch(() => props.open, (v) => {
    if (!v) return;
    const e = props.editing;
    Object.assign(form, e ? { ...blank(), ...e, type: e.type, files: [], remove_files: [], links: (e.links ?? []).map((l) => ({ url: l.url, title: l.title })),
        rubric: JSON.parse(JSON.stringify(e.rubric ?? [])) } : blank());
    form.clearErrors();
});

const criteria = computed(() => (props.options?.criteriaAll ?? []).filter((c) => !form.period_id || c.period_id === form.period_id));
const rubricMax = computed(() => form.rubric.reduce((t, c) => t + Math.max(0, ...c.levels.map((l) => Number(l.points) || 0)), 0));
const keptFiles = computed(() => (props.editing?.files ?? []).filter((f) => !form.remove_files.includes(f.index)));
const totalFiles = computed(() => keptFiles.value.length + form.files.length);
const err = (k) => form.errors[k] || Object.entries(form.errors).find(([x]) => x.startsWith(k + '.'))?.[1];

// ---- Adjuntar ----
const fileInput = ref(null);
function pickFiles(e) {
    const chosen = [...(e.target.files ?? [])];
    e.target.value = '';
    const room = 5 - totalFiles.value;
    const ok = chosen.filter((f) => f.size <= 15 * 1048576);
    if (ok.length < chosen.length) message.warning('Cada archivo puede pesar máximo 15 MB.');
    if (ok.length > room) message.warning(`Máximo 5 archivos por tarea; se agregaron ${Math.max(0, room)}.`);
    form.files.push(...ok.slice(0, Math.max(0, room)));
}
const linkDlg = ref({ open: false, kind: 'link', url: '', title: '' });
const openLink = (kind) => (linkDlg.value = { open: true, kind, url: '', title: '' });
function addLink() {
    const url = linkDlg.value.url.trim();
    if (!/^https?:\/\/\S+\.\S+/.test(url)) { message.warning('Escribe una dirección completa (https://…).'); return; }
    if (linkDlg.value.kind === 'youtube' && !/youtu\.?be/.test(url)) { message.warning('Pega un enlace de YouTube.'); return; }
    if (form.links.length >= 10) { message.warning('Máximo 10 enlaces.'); return; }
    form.links.push({ url, title: linkDlg.value.title.trim() });
    linkDlg.value.open = false;
}
const ytId = (u) => (u.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([\w-]{11})/) ?? [])[1];
const hostOf = (u) => { try { return new URL(u).hostname.replace(/^www\./, ''); } catch (e) { return u; } };
const kindOf = (n = '') => { const x = n.split('.').pop().toLowerCase(); return x === 'pdf' ? FilePdfOutlined : ['png', 'jpg', 'jpeg', 'gif', 'webp'].includes(x) ? FileImageOutlined : FileOutlined; };
const sizeLabel = (s) => (!s ? '' : s > 1048576 ? `${(s / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(s / 1024))} KB`);

// ---- Rúbrica ----
const rubricOpen = ref(false);

// ---- Guardar ----
function submit(publish) {
    form.is_published = publish;
    if (!form.title.trim()) { form.setError('title', 'El título es obligatorio.'); return; }
    form.transform((d) => ({ ...d, max_score: d.rubric.length ? rubricMax.value : d.max_score }));
    const o = { forceFormData: true, preserveScroll: true, onSuccess: () => emit('update:open', false),
        onError: () => message.error('Revisa los datos marcados.') };
    props.editing ? form.post(route('docente.actividades.update', props.editing.id), o) : form.post(route('docente.actividades.store', props.classId), o);
}
const disabledDate = (d) => d && d.isBefore(dayjs().startOf('day'));

// ---- Temas: los de la clase y uno nuevo al momento ----
const localTopics = ref([]);
watch(() => props.topics, (v) => (localTopics.value = [...v]), { immediate: true });
const topicOptions = computed(() => [{ value: null, label: 'Sin tema' }, ...localTopics.value]);
const newTopic = ref({ open: false, name: '', saving: false });
async function createTopic() {
    const name = newTopic.value.name.trim();
    if (!name) return;
    newTopic.value.saving = true;
    try {
        const { data } = await axios.post(route('docente.temas.store', props.classId), { name }, { headers: { Accept: 'application/json' } });
        localTopics.value.push(data);
        form.topic_id = data.value;
        newTopic.value = { open: false, name: '', saving: false };
    } catch (e) {
        message.error(e.response?.data?.errors?.name?.[0] ?? 'No se pudo crear el tema.');
        newTopic.value.saving = false;
    }
}
</script>

<template>
    <SgModal :open="open" :title="(editing ? 'Editar ' : 'Nueva ') + TYPE[form.type]?.label.toLowerCase()" :subtitle="form.title || 'Instrucciones, adjuntos, puntos y rúbrica'"
        :icon="TYPE[form.type]?.icon" color="linear-gradient(135deg, #1967d2, #14a39a)" width="min(1320px, 97vw)"
        :ok-text="editing && editing.is_published ? 'Guardar' : 'Asignar'" :loading="form.processing" @update:open="emit('update:open', $event)" @ok="submit(true)">
        <template #extra><a-button :disabled="form.processing" @click="submit(false)">Guardar borrador</a-button></template>
        <div class="aeg">
        <div class="ae">
            <section class="ae__card">
                <div class="gf ae__title" :class="{ 'is-err': form.errors.title }">
                    <span>Título*</span>
                    <input v-model="form.title" maxlength="200" />
                </div>
                <p v-if="form.errors.title" class="gf-help">{{ form.errors.title }}</p>
                <div class="gf ae__desc">
                    <span>Instrucciones (opcional)</span>
                    <textarea v-model="form.description" rows="5" maxlength="5000" />
                </div>

                <!-- Adjuntos -->
                <div v-if="keptFiles.length || form.files.length || form.links.length" class="ae__atts">
                    <div v-for="f in keptFiles" :key="'k' + f.index" class="att">
                        <div class="att__txt"><a :href="f.url" target="_blank" rel="noopener">{{ f.name }}</a><small>Archivo</small></div>
                        <span class="att__thumb"><component :is="kindOf(f.name)" /></span>
                        <button type="button" class="att__x" title="Quitar" @click="form.remove_files.push(f.index)"><CloseOutlined /></button>
                    </div>
                    <div v-for="(f, i) in form.files" :key="'n' + i" class="att">
                        <div class="att__txt"><b>{{ f.name }}</b><small>Archivo nuevo · {{ sizeLabel(f.size) }}</small></div>
                        <span class="att__thumb"><component :is="kindOf(f.name)" /></span>
                        <button type="button" class="att__x" title="Quitar" @click="form.files.splice(i, 1)"><CloseOutlined /></button>
                    </div>
                    <div v-for="(l, i) in form.links" :key="'l' + i" class="att">
                        <div class="att__txt"><a :href="l.url" target="_blank" rel="noopener">{{ l.title || (ytId(l.url) ? 'Video de YouTube' : hostOf(l.url)) }}</a><small>{{ ytId(l.url) ? 'Video de YouTube' : hostOf(l.url) }}</small></div>
                        <span class="att__thumb" :class="{ yt: ytId(l.url) }">
                            <img v-if="ytId(l.url)" :src="`https://img.youtube.com/vi/${ytId(l.url)}/mqdefault.jpg`" alt="" /><LinkOutlined v-else />
                            <PlayCircleFilled v-if="ytId(l.url)" class="att__play" />
                        </span>
                        <button type="button" class="att__x" title="Quitar" @click="form.links.splice(i, 1)"><CloseOutlined /></button>
                    </div>
                </div>
                <p v-if="err('files') || err('links')" class="gf-help">{{ err('files') || err('links') }}</p>
            </section>

            <section class="ae__card ae__attach">
                <b>Adjuntar</b>
                <div class="ae__btns">
                    <button type="button" @click="openLink('youtube')"><span class="yt"><YoutubeFilled /></span>YouTube</button>
                    <button type="button" :disabled="totalFiles >= 5" @click="fileInput.click()"><span><UploadOutlined /></span>Subir</button>
                    <button type="button" @click="openLink('link')"><span><LinkOutlined /></span>Vínculo</button>
                </div>
                <small>Hasta 5 archivos (15 MB c/u) y 10 enlaces.</small>
                <input ref="fileInput" type="file" multiple hidden @change="pickFiles" />
            </section>
        </div>

        <aside class="aeg__side">
            <div class="as">
                <label>Para</label>
                <div class="as__pill"><TeamOutlined /> Todos los alumnos <small v-if="studentsTotal">({{ studentsTotal }})</small></div>
            </div>
            <div class="as">
                <label>Puntos</label>
                <a-input-number v-if="!form.rubric.length" v-model:value="form.max_score" :min="1" :max="100" size="large" class="as__pts" />
                <div v-else class="as__fixed">{{ rubricMax }} <small>según la rúbrica</small></div>
            </div>
            <div class="as">
                <label>Fecha límite</label>
                <a-date-picker v-model:value="form.due_at" show-time value-format="YYYY-MM-DD HH:mm" format="ddd D [de] MMM, HH:mm" placeholder="Sin fecha límite"
                    :disabled-date="disabledDate" size="large" style="width: 100%" />
                <p v-if="form.errors.due_at" class="gf-help">{{ form.errors.due_at }}</p>
            </div>
            <div class="as">
                <label>Tema</label>
                <a-select v-model:value="form.topic_id" :options="topicOptions" size="large" />
                <div v-if="newTopic.open" class="as__nt">
                    <a-input v-model:value="newTopic.name" placeholder="Nuevo tema" :maxlength="120" @press-enter="createTopic" />
                    <a-button type="primary" :loading="newTopic.saving" @click="createTopic">Crear</a-button>
                </div>
                <a v-else class="as__link" @click="newTopic.open = true"><PlusOutlined /> Crear tema</a>
            </div>
            <div class="as">
                <label>Periodo de evaluación</label>
                <a-select v-model:value="form.period_id" :options="periods" allow-clear placeholder="Sin periodo" size="large" />
            </div>
            <div class="as">
                <label>Cuenta para la evaluación <a-tooltip title="Si eliges un criterio (por ejemplo TAREAS), la calificación se promedia en la Evaluación del periodo."><InfoCircleOutlined /></a-tooltip></label>
                <a-select v-model:value="form.evaluation_criterion_id" :options="criteria" allow-clear placeholder="No cuenta" size="large" />
            </div>
            <div class="as">
                <label>Rúbrica</label>
                <div v-if="form.rubric.length" class="as__chip" @click="rubricOpen = true">
                    <TableOutlined /> Rúbrica: {{ form.rubric.length }} criterio{{ form.rubric.length === 1 ? '' : 's' }} • {{ rubricMax }} ptos.
                    <button type="button" title="Quitar rúbrica" @click.stop="form.rubric = []"><CloseOutlined /></button>
                </div>
                <a-button v-else class="as__rub" @click="rubricOpen = true"><PlusOutlined /> Rúbrica</a-button>
                <p v-if="err('rubric')" class="gf-help">{{ err('rubric') }}</p>
            </div>
        </aside>
        </div>
    </SgModal>

    <RubricEditor v-model:open="rubricOpen" v-model="form.rubric" :assignment-title="form.title" />

    <SgModal v-model:open="linkDlg.open" :title="linkDlg.kind === 'youtube' ? 'Agregar video de YouTube' : 'Agregar vínculo'" :icon="linkDlg.kind === 'youtube' ? YoutubeFilled : LinkOutlined"
        color="linear-gradient(135deg, #1d7fc9, #0b3f6e)" width="min(620px, 96vw)" ok-text="Agregar" :ok-icon="PlusOutlined" @ok="addLink">
        <div class="gf"><span>{{ linkDlg.kind === 'youtube' ? 'URL del video' : 'Vínculo' }}</span><input v-model="linkDlg.url" placeholder="https://" /></div>
        <div class="gf" style="margin-top: 12px"><span>Título (opcional)</span><input v-model="linkDlg.title" maxlength="150" /></div>
    </SgModal>
</template>

<style scoped>
.aeg { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 0; margin: -4px -6px; }
.aeg__side { display: flex; flex-direction: column; gap: 20px; padding: 4px 4px 4px 24px; border-left: 1px solid #dadce0; }
.ae { padding: 4px 24px 4px 4px; display: flex; flex-direction: column; gap: 20px; }
.as__nt { display: flex; gap: 6px; }
.as__link { align-self: flex-start; font-size: 13px; font-weight: 500; color: #1967d2; display: inline-flex; gap: 5px; align-items: center; }
@media (max-width: 900px) { .aeg { grid-template-columns: 1fr; } .aeg__side { border-left: 0; border-top: 1px solid #dadce0; padding: 18px 0 0; } .ae { padding: 0 0 18px; } }
.ae__card { border: 1px solid #dadce0; border-radius: 8px; padding: 24px; display: flex; flex-direction: column; gap: 18px; background: #fff; }
.ae__title input { font-size: 16px; }
.ae__desc textarea { min-height: 110px; }
.ae__atts { display: flex; flex-direction: column; gap: 10px; }
.att { display: flex; align-items: center; border: 1px solid #dadce0; border-radius: 8px; overflow: hidden; max-width: 860px; }
.att__txt { flex: 1; min-width: 0; padding: 12px 16px; display: flex; flex-direction: column; }
.att__txt a, .att__txt b { color: #202124; font-weight: 500; text-decoration: underline; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.att__txt b { text-decoration: none; }
.att__txt small { color: #5f6368; font-size: 13px; }
.att__thumb { position: relative; width: 82px; height: 64px; flex: none; display: grid; place-items: center; background: #f1f3f4; color: #5f6368; font-size: 22px; border-left: 1px solid #dadce0; }
.att__thumb img { width: 100%; height: 100%; object-fit: cover; }
.att__play { position: absolute; font-size: 22px; color: #fff; filter: drop-shadow(0 1px 4px rgba(0, 0, 0, .5)); }
.att__x { width: 48px; align-self: stretch; border: 0; background: none; cursor: pointer; color: #5f6368; font-size: 16px; }
.att__x:hover { background: #f1f3f4; }
.ae__attach { align-items: stretch; }
.ae__attach > b { font-weight: 500; color: #202124; }
.ae__attach > small { text-align: center; color: #80868b; font-size: 12px; }
.ae__btns { display: flex; justify-content: center; gap: 40px; }
.ae__btns button { display: flex; flex-direction: column; align-items: center; gap: 8px; border: 0; background: none; cursor: pointer; font-family: inherit; font-size: 13px; color: #3c4043; }
.ae__btns button:disabled { opacity: .45; cursor: not-allowed; }
.ae__btns span { width: 50px; height: 50px; border-radius: 50%; border: 1px solid #dadce0; display: grid; place-items: center; font-size: 20px; color: #3c4043; transition: background .15s; }
.ae__btns span.yt { color: #ff0000; }
.ae__btns button:not(:disabled):hover span { background: #f1f3f4; }
.as { display: flex; flex-direction: column; gap: 8px; }
.as > label { font-size: 14px; font-weight: 500; color: #3c4043; display: inline-flex; gap: 6px; align-items: center; }
.as > label .anticon { color: #5f6368; }
.as__pill { display: flex; align-items: center; justify-content: center; gap: 8px; height: 46px; border: 1px solid #dadce0; border-radius: 99px; color: #1967d2; font-weight: 500; }
.as__pill small { color: #5f6368; font-weight: 400; }
.as__pts { width: 150px; }
.as__fixed { font-size: 20px; color: #202124; }
.as__fixed small { font-size: 12px; color: #5f6368; }
.as__chip { display: inline-flex; align-items: center; gap: 8px; align-self: flex-start; border: 1px solid #5f6368; border-radius: 8px; padding: 6px 6px 6px 12px; cursor: pointer; font-size: 13.5px; color: #3c4043; }
.as__chip:hover { background: #f1f3f4; }
.as__chip button { border: 0; background: none; cursor: pointer; color: #5f6368; padding: 2px 6px; border-radius: 50%; }
.as__rub { align-self: flex-start; border-radius: 4px; color: #1967d2; font-weight: 500; }
</style>
