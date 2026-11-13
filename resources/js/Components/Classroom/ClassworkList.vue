<script setup>
/**
 * "Trabajo de clase" como en Google Classroom: temas con su lista de tareas.
 * Cada tarea se despliega (publicado, entregadas/asignadas o tu estado, instrucciones, rúbrica y adjuntos)
 * y tiene "Ver instrucciones".
 *  - mode="teacher": menú ⋮ con Editar / Eliminar.   - mode="student": estado de la entrega.
 */
import { computed, reactive, ref } from 'vue';
import {
    FileTextOutlined, ProjectOutlined, ExperimentOutlined, MoreOutlined, UpOutlined, DownOutlined, ShrinkOutlined, ArrowsAltOutlined, MessageOutlined, TableOutlined,
    EditOutlined, DeleteOutlined, PaperClipOutlined, LinkOutlined, PlayCircleFilled, FilePdfOutlined, FileImageOutlined, FileOutlined, FolderOpenOutlined,
} from '@ant-design/icons-vue';
import { openFile } from '@/lib/viewer';
import { num } from '@/lib/format';

const props = defineProps({
    items: { type: Array, default: () => [] },
    topics: { type: Array, default: () => [] },        // [{value, label}] temas de la clase, en orden
    mode: { type: String, default: 'teacher' },
    materials: { type: Array, default: () => [] },
});
const emit = defineEmits(['open', 'edit', 'remove', 'topic']);

const ICON = { tarea: FileTextOutlined, proyecto: ProjectOutlined, actividad: ExperimentOutlined };
// Temas en el orden del docente; "Sin tema" primero (como Google). En modo docente se muestran también los temas vacíos.
const filter = ref('all');
const topicGroups = computed(() => {
    const groups = [];
    const none = props.items.filter((a) => !a.topic_id || !props.topics.some((t) => t.value === a.topic_id));
    if (none.length) groups.push({ key: 0, id: null, label: 'Sin tema', items: none });
    props.topics.forEach((t, i) => {
        const items = props.items.filter((a) => a.topic_id === t.value);
        if (items.length || props.mode === 'teacher') groups.push({ key: t.value, id: t.value, label: t.label, items, first: i === 0, last: i === props.topics.length - 1 });
    });
    return groups;
});
const topics = computed(() => (filter.value === 'all' ? topicGroups.value : topicGroups.value.filter((g) => g.key === filter.value)));
const filterOptions = computed(() => [{ value: 'all', label: 'Todos los temas' }, ...topicGroups.value.map((g) => ({ value: g.key, label: g.label }))]);
const closedTopics = reactive({});
const expanded = ref(null);
const allClosed = computed(() => topicGroups.value.length && topicGroups.value.every((t) => closedTopics[t.key]));
const toggleAll = () => { const v = !allClosed.value; topicGroups.value.forEach((t) => (closedTopics[t.key] = v)); if (v) expanded.value = null; };

const dueText = (a) => (a.due_full || a.due_at || a.due_label ? `Fecha límite: ${a.due_full ?? a.due_at ?? a.due_label}` : 'Sin fecha límite');
const studentState = (a) => {
    const s = a.submission;
    if (s?.score != null) return { t: `${num(s.score)}/${num(a.max_score)}`, c: '#188038' };
    if (s?.submitted_at) return { t: 'Entregada', c: '#188038' };
    return a.overdue ? { t: 'Falta entregar', c: '#d93025' } : { t: 'Asignada', c: '#5f6368' };
};
const fileIcon = (n = '') => { const x = n.split('.').pop().toLowerCase(); return x === 'pdf' ? FilePdfOutlined : ['png', 'jpg', 'jpeg', 'gif', 'webp'].includes(x) ? FileImageOutlined : FileOutlined; };
const rubricMax = (r) => r.reduce((t, c) => t + Math.max(0, ...c.levels.map((l) => Number(l.points) || 0)), 0);
</script>

<template>
    <div class="cl">
        <div v-if="topicGroups.length" class="cl__tools">
            <div class="cl__filter"><span>Filtro por tema</span><a-select v-model:value="filter" :options="filterOptions" :bordered="false" class="cl__fsel" /></div>
            <slot name="tools" />
            <button type="button" class="cl__collapse" @click="toggleAll"><component :is="allClosed ? ArrowsAltOutlined : ShrinkOutlined" /> {{ allClosed ? 'Mostrar todo' : 'Ocultar todo' }}</button>
        </div>

        <section v-for="t in topics" :key="t.key" class="cl__topic">
            <div class="cl__th">
                <span class="cl__tn" @click="closedTopics[t.key] = !closedTopics[t.key]">{{ t.label }}</span>
                <button type="button" class="cl__tb" @click="closedTopics[t.key] = !closedTopics[t.key]"><component :is="closedTopics[t.key] ? DownOutlined : UpOutlined" /></button>
                <a-dropdown v-if="mode === 'teacher' && t.id" :trigger="['click']" placement="bottomRight">
                    <button type="button" class="cl__tb"><MoreOutlined /></button>
                    <template #overlay>
                        <a-menu>
                            <a-menu-item key="ren" @click="emit('topic', { action: 'rename', id: t.id, label: t.label })"><EditOutlined /> Cambiar nombre</a-menu-item>
                            <a-menu-item key="up" :disabled="t.first" @click="emit('topic', { action: 'up', id: t.id, label: t.label })"><UpOutlined /> Subir</a-menu-item>
                            <a-menu-item key="down" :disabled="t.last" @click="emit('topic', { action: 'down', id: t.id, label: t.label })"><DownOutlined /> Bajar</a-menu-item>
                            <a-menu-item key="del" danger @click="emit('topic', { action: 'delete', id: t.id, label: t.label })"><DeleteOutlined /> Borrar tema</a-menu-item>
                        </a-menu>
                    </template>
                </a-dropdown>
            </div>
            <template v-if="!closedTopics[t.key]">
                <p v-if="!t.items.length" class="cl__empty">Aún no hay tareas en este tema. Elige este tema al crear una tarea.</p>
                <div v-for="a in t.items" :key="a.id" class="cl__it" :class="{ open: expanded === a.id }">
                    <div class="cl__row" @click="expanded = expanded === a.id ? null : a.id">
                        <span class="cl__ic" :class="{ draft: mode === 'teacher' && !a.is_published }"><component :is="ICON[a.type] ?? FileTextOutlined" /></span>
                        <span class="cl__title">{{ a.title }}<em v-if="mode === 'teacher' && !a.is_published"> · Borrador</em>
                            <span v-if="a.comments_count || a.comments?.length" class="cl__cm"><MessageOutlined /> {{ a.comments_count ?? a.comments.length }}</span></span>
                        <span v-if="mode === 'student'" class="cl__state" :style="{ color: studentState(a).c }">{{ studentState(a).t }}</span>
                        <span v-else class="cl__due" :class="{ late: a.overdue }">{{ dueText(a) }}</span>
                        <a-dropdown v-if="mode === 'teacher'" :trigger="['click']" placement="bottomRight">
                            <button type="button" class="cl__more" @click.stop><MoreOutlined /></button>
                            <template #overlay>
                                <a-menu>
                                    <a-menu-item key="edit" @click="emit('edit', a)"><EditOutlined /> Editar</a-menu-item>
                                    <a-menu-item key="del" danger @click="emit('remove', a)"><DeleteOutlined /> Borrar</a-menu-item>
                                </a-menu>
                            </template>
                        </a-dropdown>
                    </div>

                    <div v-if="expanded === a.id" class="cl__body">
                        <div class="cl__top">
                            <small class="cl__pub">Publicado: {{ a.created_label ?? a.created }}<template v-if="mode === 'student' && a.due_at"> · {{ dueText(a) }}</template></small>
                            <div v-if="mode === 'teacher'" class="cl__stats">
                                <div><b>{{ a.submissions_count }}</b><span>Entregadas</span></div>
                                <div><b>{{ Math.max(0, (a.students_total ?? 0) - a.submissions_count) }}</b><span>Asignadas</span></div>
                            </div>
                        </div>
                        <p v-if="a.description" class="cl__desc">{{ a.description }}</p>
                        <span v-if="a.rubric?.length" class="cl__rub"><TableOutlined /> Rúbrica: {{ a.rubric.length }} criterio{{ a.rubric.length === 1 ? '' : 's' }} • {{ rubricMax(a.rubric) }} ptos.</span>
                        <div v-if="a.files?.length || a.links?.length" class="cl__atts">
                            <a v-for="l in a.links ?? []" :key="l.url" :href="l.url" target="_blank" rel="noopener" class="cl__att" @click.stop>
                                <span class="cl__att-t"><b>{{ l.title }}</b><small>{{ l.kind === 'video' ? 'Video de YouTube' : l.host }}</small></span>
                                <span class="cl__att-th"><img v-if="l.thumb" :src="l.thumb" alt="" /><LinkOutlined v-else /><PlayCircleFilled v-if="l.youtube" class="cl__play" /></span>
                            </a>
                            <button v-for="f in a.files ?? []" :key="f.url" type="button" class="cl__att" @click.stop="openFile({ url: f.url, name: f.name, title: a.title })">
                                <span class="cl__att-t"><b>{{ f.name }}</b><small>Archivo</small></span>
                                <span class="cl__att-th"><component :is="fileIcon(f.name)" /></span>
                            </button>
                        </div>
                        <div class="cl__foot"><a @click="emit('open', a)">{{ mode === 'teacher' ? 'Ver instrucciones y entregas' : 'Ver instrucciones' }}</a></div>
                    </div>
                </div>
            </template>
        </section>

        <section v-if="materials.length" class="cl__topic">
            <div class="cl__th"><span class="cl__tn" @click="closedTopics.mat = !closedTopics.mat">Material</span>
                <button type="button" class="cl__tb" @click="closedTopics.mat = !closedTopics.mat"><component :is="closedTopics.mat ? DownOutlined : UpOutlined" /></button></div>
            <template v-if="!closedTopics.mat">
                <div v-for="m in materials" :key="m.id" class="cl__it" :class="{ open: expanded === 'm' + m.id }">
                    <div class="cl__row" @click="expanded = expanded === 'm' + m.id ? null : 'm' + m.id">
                        <span class="cl__ic"><FolderOpenOutlined /></span>
                        <span class="cl__title">{{ m.title || 'Material' }}</span>
                        <span class="cl__due">Publicado: {{ m.at }}</span>
                    </div>
                    <div v-if="expanded === 'm' + m.id" class="cl__body">
                        <p v-if="m.body" class="cl__desc">{{ m.body }}</p>
                        <div class="cl__atts">
                            <a v-for="l in m.links" :key="l.url" :href="l.url" target="_blank" rel="noopener" class="cl__att">
                                <span class="cl__att-t"><b>{{ l.title }}</b><small>{{ l.kind === 'video' ? 'Video de YouTube' : l.host }}</small></span>
                                <span class="cl__att-th"><img v-if="l.thumb" :src="l.thumb" alt="" /><LinkOutlined v-else /><PlayCircleFilled v-if="l.youtube" class="cl__play" /></span>
                            </a>
                            <button v-for="f in m.files" :key="f.url" type="button" class="cl__att" @click="openFile({ url: f.url, name: f.name, title: m.title })">
                                <span class="cl__att-t"><b>{{ f.name }}</b><small>Archivo</small></span>
                                <span class="cl__att-th"><component :is="fileIcon(f.name)" /></span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </section>

        <a-empty v-if="!items.length && !materials.length" description="Aún no hay trabajo de clase." />
    </div>
</template>

<style scoped>
.cl { width: 100%; }
.cl__tools { display: flex; align-items: center; justify-content: flex-end; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
.cl__filter { margin-right: auto; display: flex; flex-direction: column; border: 2px solid #1967d2; border-radius: 6px; padding: 4px 6px 2px 12px; min-width: 300px; position: relative; }
.cl__filter > span { position: absolute; top: -9px; left: 10px; background: #fff; padding: 0 4px; font-size: 11px; color: #1967d2; }
.cl__fsel { width: 100%; }
.cl__fsel :deep(.ant-select-selector) { padding-left: 0 !important; font-size: 15px; }
.cl__tn { flex: 1; font-size: 22px; font-weight: 400; color: #202124; cursor: pointer; }
.cl__tb { width: 40px; height: 40px; border-radius: 50%; border: 0; background: none; cursor: pointer; color: #5f6368; font-size: 16px; }
.cl__tb:hover { background: #f1f3f4; }
.cl__empty { margin: 0; padding: 18px 24px; font-size: 13px; color: #80868b; border-bottom: 1px solid #e8eaed; }
.cl__collapse { border: 0; background: none; cursor: pointer; color: #1967d2; font-family: inherit; font-size: 14px; font-weight: 500; display: inline-flex; gap: 6px; align-items: center; padding: 8px; border-radius: 4px; }
.cl__collapse:hover { background: #f1f6fe; }
.cl__topic { margin-bottom: 28px; }
.cl__th { width: 100%; display: flex; align-items: center; gap: 4px; padding: 8px 8px 8px 24px; border-bottom: 1px solid #dadce0; }
.cl__it { border-bottom: 1px solid #e8eaed; }
.cl__it.open { background: #f0f4f9; border-radius: 8px; border-bottom-color: transparent; margin: 8px 0; }
.cl__row { display: flex; align-items: center; gap: 16px; padding: 10px 16px 10px 24px; cursor: pointer; min-height: 60px; }
.cl__it:not(.open) .cl__row:hover { background: #f8f9fa; }
.cl__it.open .cl__row { border-bottom: 1px solid #dadce0; }
.cl__ic { width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; background: #e8eaed; color: #3c4043; font-size: 17px; flex: none; }
.cl__ic.draft { background: #f1f3f4; color: #9aa0a6; }
.cl__title { flex: 1; min-width: 0; font-size: 14px; font-weight: 500; color: #3c4043; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cl__title em { font-style: normal; color: #9aa0a6; font-weight: 400; }
.cl__cm { margin-left: 10px; font-weight: 400; color: #5f6368; font-size: 13px; }
.cl__due { font-size: 13px; color: #3c4043; white-space: nowrap; }
.cl__due.late { color: #3c4043; }
.cl__state { font-size: 13px; font-weight: 500; white-space: nowrap; }
.cl__more { width: 36px; height: 36px; border-radius: 50%; border: 0; background: none; cursor: pointer; color: #5f6368; font-size: 18px; flex: none; }
.cl__more:hover { background: #e8eaed; }
.cl__body { padding: 16px 24px 0; display: flex; flex-direction: column; gap: 14px; }
.cl__top { display: flex; justify-content: space-between; gap: 16px; }
.cl__pub { font-size: 12px; font-weight: 500; color: #3c4043; }
.cl__stats { display: flex; }
.cl__stats div { display: flex; flex-direction: column; padding: 0 16px; border-left: 1px solid #dadce0; min-width: 92px; }
.cl__stats b { font-size: 26px; font-weight: 400; color: #3c4043; line-height: 1.2; }
.cl__stats span { font-size: 13px; color: #5f6368; }
.cl__desc { margin: 0; font-size: 14px; color: #3c4043; white-space: pre-wrap; }
.cl__rub { align-self: flex-start; display: inline-flex; align-items: center; gap: 8px; border: 1px solid #5f6368; border-radius: 8px; padding: 5px 12px; font-size: 13.5px; color: #3c4043; }
.cl__rub .anticon { color: #1967d2; }
.cl__atts { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 12px; }
.cl__att { display: flex; align-items: stretch; border: 1px solid #dadce0; border-radius: 8px; overflow: hidden; background: #fff; text-align: left; font-family: inherit; cursor: pointer; padding: 0; color: inherit; height: 80px; }
.cl__att:hover { border-color: #1967d2; }
.cl__att-t { flex: 1; min-width: 0; padding: 14px 16px; display: flex; flex-direction: column; justify-content: center; }
.cl__att-t b { font-weight: 500; color: #202124; text-decoration: underline; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cl__att-t small { font-size: 13px; color: #5f6368; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cl__att-th { position: relative; width: 96px; flex: none; display: grid; place-items: center; background: #f1f3f4; color: #5f6368; font-size: 24px; border-left: 1px solid #dadce0; }
.cl__att-th img { width: 100%; height: 100%; object-fit: cover; }
.cl__play { position: absolute; color: #fff; font-size: 24px; filter: drop-shadow(0 1px 4px rgba(0, 0, 0, .5)); }
.cl__foot { margin: 4px -24px 0; padding: 14px 24px; border-top: 1px solid #dadce0; }
.cl__foot a { color: #1967d2; font-weight: 500; font-size: 14px; }
@media (max-width: 700px) {
    .cl__row { padding: 10px 10px; gap: 10px; }
    .cl__due { display: none; }
    .cl__th { padding: 10px 12px; }
    .cl__body { padding: 12px 12px 0; }
    .cl__foot { margin: 4px -12px 0; padding: 12px; }
    .cl__top { flex-direction: column; }
    .cl__stats div:first-child { border-left: 0; padding-left: 0; }
}
</style>
