<script setup>
/**
 * "Trabajo de clase" del docente (como Google Classroom): botón "Crear" (tarea, proyecto, actividad, material o tema),
 * temas propios en acordeón y el editor de tareas en un modal amplio (archivos, YouTube, vínculos y rúbrica).
 */
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { PlusOutlined, FileTextOutlined, ProjectOutlined, ExperimentOutlined, FolderOpenOutlined, UnorderedListOutlined, SaveOutlined } from '@ant-design/icons-vue';
import ClassworkList from '@/Components/Classroom/ClassworkList.vue';
import AssignmentEditor from '@/Components/Classroom/AssignmentEditor.vue';
import SgModal from '@/Components/SgModal.vue';
import { confirmDelete } from '@/lib/notify';

const props = defineProps({
    classId: Number, assignments: Array, materials: { type: Array, default: () => [] }, periods: Array, options: Object,
    studentsTotal: { type: Number, default: 0 }, topics: { type: Array, default: () => [] },
});
const emit = defineEmits(['material']);

const editorOpen = ref(false);
const editing = ref(null);
const newType = ref('tarea');
const ICON = { tarea: FileTextOutlined, proyecto: ProjectOutlined, actividad: ExperimentOutlined };

function create(type) { editing.value = null; newType.value = type; editorOpen.value = true; }
function edit(a) { editing.value = a; newType.value = a.type; editorOpen.value = true; }
const remove = (a) => confirmDelete({ title: '¿Borrar la tarea?', content: `${a.title}. Se borrarán también sus calificaciones y comentarios.`, okText: 'Borrar',
    onOk: () => router.delete(route('docente.actividades.destroy', a.id), { preserveScroll: true }) });
const openAct = (a) => router.visit(route('docente.actividad', a.id));

// ---- Temas ----
const topicDlg = ref({ open: false, id: null, name: '', saving: false, error: '' });
const newTopic = () => (topicDlg.value = { open: true, id: null, name: '', saving: false, error: '' });
function saveTopic() {
    const t = topicDlg.value;
    if (!t.name.trim()) { t.error = 'Escribe el nombre del tema.'; return; }
    t.saving = true;
    const o = { preserveScroll: true, onSuccess: () => (t.open = false), onError: (e) => (t.error = e.name), onFinish: () => (t.saving = false) };
    t.id ? router.put(route('docente.temas.update', t.id), { name: t.name }, o) : router.post(route('docente.temas.store', props.classId), { name: t.name }, o);
}
function onTopic({ action, id, label }) {
    if (action === 'rename') topicDlg.value = { open: true, id, name: label, saving: false, error: '' };
    else if (action === 'up' || action === 'down') router.put(route('docente.temas.update', id), { name: label, move: action }, { preserveScroll: true });
    else if (action === 'delete') confirmDelete({ title: '¿Borrar el tema?', content: `${label}. Sus tareas no se borran: pasan a "Sin tema".`, okText: 'Borrar tema',
        onOk: () => router.delete(route('docente.temas.destroy', id), { preserveScroll: true }) });
}
</script>

<template>
    <div class="ct">
        <a-dropdown :trigger="['click']" placement="bottomLeft">
            <a-button type="primary" size="large" class="ct__create"><PlusOutlined /> Crear</a-button>
            <template #overlay>
                <a-menu class="ct__menu">
                    <a-menu-item v-for="t in options.assignmentTypes" :key="t.value" @click="create(t.value)"><component :is="ICON[t.value]" /> {{ t.label }}</a-menu-item>
                    <a-menu-item key="material" @click="emit('material')"><FolderOpenOutlined /> Material</a-menu-item>
                    <a-menu-divider />
                    <a-menu-item key="tema" @click="newTopic"><UnorderedListOutlined /> Tema</a-menu-item>
                </a-menu>
            </template>
        </a-dropdown>

        <ClassworkList :items="assignments.map((a) => ({ ...a, students_total: studentsTotal }))" :materials="materials" :topics="topics" mode="teacher"
            @open="openAct" @edit="edit" @remove="remove" @topic="onTopic" />

        <AssignmentEditor v-model:open="editorOpen" :class-id="classId" :editing="editing" :type="newType" :periods="periods" :topics="topics" :options="options" :students-total="studentsTotal" />

        <SgModal v-model:open="topicDlg.open" :title="topicDlg.id ? 'Cambiar nombre del tema' : 'Agregar tema'" subtitle="Agrupa las tareas de la clase (por ejemplo: Verbos en participio)"
            :icon="UnorderedListOutlined" color="linear-gradient(135deg, #1967d2, #14a39a)" width="min(620px, 96vw)" :ok-text="topicDlg.id ? 'Guardar' : 'Agregar'" :ok-icon="SaveOutlined"
            :loading="topicDlg.saving" @ok="saveTopic">
            <div class="gf" :class="{ 'is-err': topicDlg.error }"><span>Tema</span><input v-model="topicDlg.name" maxlength="120" placeholder="Verbos en participio" @keyup.enter="saveTopic" /></div>
            <p v-if="topicDlg.error" class="gf-help">{{ topicDlg.error }}</p>
        </SgModal>
    </div>
</template>

<style scoped>
.ct { display: flex; flex-direction: column; gap: 18px; width: 100%; }
.ct__create { align-self: flex-start; border-radius: 99px !important; height: 44px !important; padding: 0 24px !important; font-weight: 500; background: #1967d2 !important; box-shadow: 0 1px 3px rgba(60, 64, 67, .3); }
</style>
<style>
.ct__menu .ant-dropdown-menu-item { padding: 10px 18px !important; font-size: 14px; }
.ct__menu .ant-dropdown-menu-title-content { display: flex; align-items: center; gap: 14px; }
</style>
