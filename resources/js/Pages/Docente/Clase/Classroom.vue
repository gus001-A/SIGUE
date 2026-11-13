<script setup>
/**
 * Classroom de la clase, como Google Classroom:
 *  - Novedades: avisos y material (diapositivas, PDF, videos, enlaces) con comentarios de la clase.
 *  - Trabajo de clase: tareas, proyectos y actividades (archivos, rúbrica, comentarios).
 */
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import {
    NotificationOutlined, FolderOpenOutlined, AppstoreOutlined, CalendarOutlined, PlusOutlined, DeleteOutlined, LinkOutlined,
    SendOutlined, YoutubeOutlined, TeamOutlined, TableOutlined,
} from '@ant-design/icons-vue';
import ClaseShell from '@/Components/Clase/ClaseShell.vue';
import ClassroomTab from '@/Components/Clase/ClassroomTab.vue';
import GradebookTab from '@/Components/Classroom/GradebookTab.vue';
import PostCard from '@/Components/Classroom/PostCard.vue';
import MultiFileInput from '@/Components/MultiFileInput.vue';
import SgModal from '@/Components/SgModal.vue';
import { confirmDelete } from '@/lib/notify';

const props = defineProps({
    course: Object, section: String, students: Array, periods: Array, periodId: [Number, null], assignments: Array, options: Object,
    posts: { type: Array, default: () => [] },
    gradebook: { type: Object, default: () => ({ columns: [], cells: {} }) },
    topics: { type: Array, default: () => [] },
});

const KEY = `sg-classroom-tab-${props.course.id}`;
const view = ref((() => { try { return sessionStorage.getItem(KEY) || 'novedades'; } catch (e) { return 'novedades'; } })());
const setView = (v) => { view.value = v; try { sessionStorage.setItem(KEY, v); } catch (e) { /* sin almacenamiento */ } };

// Próximas entregas (columna lateral, como en Google Classroom)
const upcoming = computed(() => props.assignments.filter((a) => a.is_published && a.due_at && !a.overdue)
    .sort((a, b) => a.due_at.localeCompare(b.due_at)).slice(0, 5));
const materials = computed(() => props.posts.filter((p) => p.type === 'material'));

// ---------- Publicar aviso o material ----------
const open = ref(false);
const editing = ref(null);
const blank = (type = 'aviso') => ({ type, title: '', body: '', links: [], files: [], remove_files: [] });
const form = useForm(blank());
const err = (k) => ({ 'validate-status': form.errors[k] ? 'error' : undefined, help: form.errors[k] });
const linkErr = computed(() => Object.entries(form.errors).find(([k]) => k.startsWith('links'))?.[1]);
const fileErr = computed(() => form.errors.files || Object.entries(form.errors).find(([k]) => k.startsWith('files.'))?.[1]);

function compose(type = 'aviso') { editing.value = null; Object.assign(form, blank(type)); form.clearErrors(); open.value = true; }
function editPost(p) {
    editing.value = p;
    Object.assign(form, { ...blank(p.type), title: p.title ?? '', body: p.body ?? '', links: p.links.map((l) => ({ url: l.url, title: l.title })) });
    form.clearErrors();
    open.value = true;
}
const addLink = () => form.links.push({ url: '', title: '' });
function submit() {
    form.transform((d) => ({ ...d, links: d.links.filter((l) => l.url.trim()) }));
    const o = { forceFormData: true, preserveScroll: true, onSuccess: () => (open.value = false) };
    editing.value ? form.post(route('docente.publicaciones.update', editing.value.id), o) : form.post(route('docente.publicaciones.store', props.course.id), o);
}
const pin = (p) => router.post(route('docente.publicaciones.fijar', p.id), {}, { preserveScroll: true });
const removePost = (p) => confirmDelete({ title: '¿Eliminar la publicación?', content: p.title || p.body?.slice(0, 80), onOk: () => router.delete(route('docente.publicaciones.destroy', p.id), { preserveScroll: true }) });
</script>

<template>
    <ClaseShell :course="course" :section="section" :count="students.length">
        <div class="gc">
            <!-- Banner de la clase -->
            <div class="gc__banner">
                <div>
                    <h2>{{ course.subject }}</h2>
                    <p>{{ course.group }} · {{ students.length }} alumnos</p>
                </div>
                <div class="gc__tabs">
                    <button type="button" :class="{ on: view === 'novedades' }" @click="setView('novedades')"><NotificationOutlined /> Novedades</button>
                    <button type="button" :class="{ on: view === 'trabajo' }" @click="setView('trabajo')"><AppstoreOutlined /> Trabajo de clase</button>
                    <button type="button" :class="{ on: view === 'calificaciones' }" @click="setView('calificaciones')"><TableOutlined /> Calificaciones</button>
                </div>
            </div>

            <!-- ===== Novedades ===== -->
            <div v-if="view === 'novedades'" class="gc__stream">
                <aside class="gc__side">
                    <div class="gc__box">
                        <b><CalendarOutlined /> Próximas entregas</b>
                        <p v-if="!upcoming.length" class="gc__none">¡Bien! No hay entregas próximas.</p>
                        <a v-for="a in upcoming" :key="a.id" class="gc__due" @click="router.visit(route('docente.actividad', a.id))">
                            <small>{{ a.due_label }}</small><span>{{ a.title }}</span>
                        </a>
                        <a class="gc__all" @click="setView('trabajo')">Ver todo</a>
                    </div>
                    <div class="gc__box">
                        <b><TeamOutlined /> Resumen</b>
                        <div class="gc__kv"><span>Tareas publicadas</span><em>{{ assignments.filter((a) => a.is_published).length }}</em></div>
                        <div class="gc__kv"><span>Material</span><em>{{ materials.length }}</em></div>
                        <div class="gc__kv"><span>Avisos</span><em>{{ posts.length - materials.length }}</em></div>
                    </div>
                </aside>

                <div class="gc__feed">
                    <div class="gc__composer">
                        <span class="gc__me"><NotificationOutlined /></span>
                        <button type="button" class="gc__ask" @click="compose('aviso')">Anuncia algo a tu clase…</button>
                        <a-button @click="compose('material')"><FolderOpenOutlined style="color: #f39200" /> Material</a-button>
                    </div>

                    <a-empty v-if="!posts.length" description="Aquí aparecerán tus avisos y el material de la clase." class="gc__empty" />
                    <PostCard v-for="p in posts" :key="p.id" :post="p" @edit="editPost" @remove="removePost" @pin="pin" />
                </div>
            </div>

            <!-- ===== Calificaciones ===== -->
            <GradebookTab v-else-if="view === 'calificaciones'" :students="students" :gradebook="gradebook" />

            <!-- ===== Trabajo de clase ===== -->
            <div v-else class="gc__work">
                <ClassroomTab :class-id="course.id" :assignments="assignments" :materials="materials" :periods="periods" :options="options" :students-total="students.length" :topics="topics" @material="compose('material')" />
            </div>
        </div>

        <!-- Modal: aviso o material -->
        <SgModal v-model:open="open" :title="(editing ? 'Editar ' : 'Nuevo ') + (form.type === 'material' ? 'material' : 'aviso')"
            :subtitle="form.type === 'material' ? 'Diapositivas, PDF, videos o enlaces para la clase' : 'Lo verán todos los alumnos de la clase'"
            :icon="form.type === 'material' ? FolderOpenOutlined : NotificationOutlined"
            :color="form.type === 'material' ? 'linear-gradient(135deg, #f39200, #e0457b)' : 'linear-gradient(135deg, #1d7fc9, #14a39a)'"
            width="min(860px, 96vw)" :ok-text="editing ? 'Guardar' : 'Publicar'" :ok-icon="SendOutlined" :loading="form.processing" @ok="submit">
            <a-form layout="vertical">
                <a-segmented v-if="!editing" v-model:value="form.type" block class="gc__type"
                    :options="[{ value: 'aviso', label: 'Aviso' }, { value: 'material', label: 'Material' }]" />
                <a-form-item v-if="form.type === 'material'" label="Título" required v-bind="err('title')"><a-input v-model:value="form.title" placeholder="Diapositivas del tema 3" /></a-form-item>
                <a-form-item :label="form.type === 'material' ? 'Descripción (opcional)' : 'Aviso'" v-bind="err('body')">
                    <a-textarea v-model:value="form.body" :rows="4" :placeholder="form.type === 'material' ? 'Para qué es este material' : 'Escribe el aviso para tu clase'" />
                </a-form-item>
                <a-form-item label="Archivos (hasta 5) · diapositivas, PDF, imágenes, videos" :validate-status="fileErr ? 'error' : undefined" :help="fileErr">
                    <MultiFileInput v-model="form.files" v-model:remove="form.remove_files" :current="editing?.files ?? []" :max="5" :max-mb="50" />
                </a-form-item>
                <a-form-item label="Enlaces (YouTube, Google Drive, sitios web)" :validate-status="linkErr ? 'error' : undefined" :help="linkErr">
                    <div class="gc__links">
                        <div v-for="(l, i) in form.links" :key="i" class="gc__lrow">
                            <a-input v-model:value="l.url" placeholder="https://www.youtube.com/watch?v=…"><template #prefix><YoutubeOutlined v-if="/youtu/.test(l.url)" style="color: #e3262b" /><LinkOutlined v-else /></template></a-input>
                            <a-input v-model:value="l.title" placeholder="Título (opcional)" />
                            <a-button class="sg-ab red" @click="form.links.splice(i, 1)"><DeleteOutlined /></a-button>
                        </div>
                        <a-button v-if="form.links.length < 10" type="dashed" @click="addLink"><PlusOutlined /> Agregar enlace</a-button>
                    </div>
                </a-form-item>
            </a-form>
        </SgModal>
    </ClaseShell>
</template>

<style scoped>
.gc { flex: 1 1 auto; min-height: 0; overflow-y: auto; display: flex; flex-direction: column; gap: 14px; padding: 0 2px 10px; }
.gc__banner { flex: none; position: relative; overflow: hidden; display: flex; align-items: flex-end; justify-content: space-between; gap: 14px; flex-wrap: wrap;
    min-height: 120px; padding: 18px 22px; border-radius: 18px; color: #fff; background: linear-gradient(120deg, #0b3f6e 0%, #1d7fc9 55%, #14a39a 100%); }
.gc__banner::after { content: ''; position: absolute; right: -40px; top: -60px; width: 220px; height: 220px; border-radius: 50%; background: rgba(255, 255, 255, .1); }
.gc__banner h2 { margin: 0; font-size: 26px; font-weight: 800; color: #fff; }
.gc__banner p { margin: 2px 0 0; opacity: .9; }
.gc__tabs { position: relative; z-index: 1; display: flex; gap: 4px; background: rgba(255, 255, 255, .16); padding: 4px; border-radius: 12px; }
.gc__tabs button { border: 0; background: none; color: #fff; padding: 7px 14px; border-radius: 9px; font-family: inherit; font-weight: 600; cursor: pointer; display: inline-flex; gap: 6px; align-items: center; }
.gc__tabs button.on { background: #fff; color: #0b3f6e; box-shadow: 0 4px 12px -6px rgba(0, 0, 0, .4); }

.gc__stream { display: grid; grid-template-columns: 230px minmax(0, 1fr); gap: 16px; align-items: start; }
.gc__side { display: flex; flex-direction: column; gap: 12px; position: sticky; top: 0; }
.gc__box { background: #fff; border: 1px solid #e1e8f0; border-radius: 14px; padding: 12px 14px; display: flex; flex-direction: column; gap: 8px; }
.gc__box > b { font-size: 13px; color: #0a1f44; display: inline-flex; gap: 6px; align-items: center; }
.gc__box > b .anticon { color: #1d7fc9; }
.gc__none { margin: 0; font-size: 12.5px; color: #5d7085; }
.gc__due { display: flex; flex-direction: column; color: inherit; line-height: 1.3; }
.gc__due small { font-size: 11px; color: #94a3b8; }
.gc__due span { font-size: 13px; color: #0a1f44; font-weight: 600; }
.gc__due:hover span { color: #1d7fc9; text-decoration: underline; }
.gc__all { align-self: flex-end; font-size: 12.5px; font-weight: 700; }
.gc__kv { display: flex; justify-content: space-between; font-size: 12.5px; color: #5d7085; }
.gc__kv em { font-style: normal; font-weight: 800; color: #0a1f44; }
.gc__feed { display: flex; flex-direction: column; gap: 12px; min-width: 0; }
.gc__composer { display: flex; align-items: center; gap: 10px; background: #fff; border: 1px solid #e1e8f0; border-radius: 16px; padding: 12px 14px; box-shadow: 0 6px 16px -14px rgba(10, 31, 68, .4); }
.gc__me { width: 38px; height: 38px; border-radius: 50%; display: grid; place-items: center; color: #fff; background: linear-gradient(135deg, #1d7fc9, #14a39a); flex: none; }
.gc__ask { flex: 1; text-align: left; border: 0; background: #f4f7fb; border-radius: 99px; padding: 10px 16px; color: #5d7085; cursor: pointer; font-family: inherit; font-size: 14px; }
.gc__ask:hover { background: #eaf2fb; color: #1d7fc9; }
.gc__empty { background: #fff; border: 1.5px dashed #e1e8f0; border-radius: 16px; padding: 30px; }
.gc__work { background: #fff; border: 1px solid #e1e8f0; border-radius: 16px; padding: 24px 18px; }
.gc__type { margin-bottom: 14px; }
.gc__links { display: flex; flex-direction: column; gap: 6px; }
.gc__lrow { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr) 36px; gap: 6px; }
@media (max-width: 900px) { .gc__stream { grid-template-columns: 1fr; } .gc__side { position: static; } .gc__lrow { grid-template-columns: 1fr 36px; } .gc__lrow > :nth-child(2) { grid-column: 1; } }
</style>
