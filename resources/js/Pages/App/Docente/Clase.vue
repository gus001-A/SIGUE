<script setup>
/**
 * App del docente · Una clase (solo lo indispensable): Trabajo de clase para calificar, Alumnos
 * y botón para pasar lista. Crear tareas, rúbricas y avisos se hace en computadora.
 */
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { SolutionOutlined, TeamOutlined, FileTextOutlined, ProjectOutlined, ExperimentOutlined, CheckSquareOutlined, DesktopOutlined } from '@ant-design/icons-vue';
import MobileLayout from '@/Layouts/MobileLayout.vue';
import { ripple as vRipple, initials } from '@/lib/ripple';

const props = defineProps({
    course: Object, students: { type: Array, default: () => [] }, assignments: { type: Array, default: () => [] },
    topics: { type: Array, default: () => [] }, section: String, periods: Array, periodId: [Number, null],
    posts: Array, gradebook: Object, options: Object,
});
const tab = ref('trabajo');
const ICON = { tarea: FileTextOutlined, proyecto: ProjectOutlined, actividad: ExperimentOutlined };
const topicName = (id) => props.topics.find((t) => t.value === id)?.label ?? 'Sin tema';
const groups = computed(() => {
    const m = new Map();
    for (const a of props.assignments.filter((x) => x.is_published)) {
        const k = a.topic_id ?? 0;
        if (!m.has(k)) m.set(k, { id: k, name: topicName(a.topic_id), items: [] });
        m.get(k).items.push(a);
    }
    return [...m.values()].sort((x, y) => (x.id === 0 ? -1 : y.id === 0 ? 1 : 0));
});
const drafts = computed(() => props.assignments.filter((a) => !a.is_published).length);
/** Entregadas sin calificar de cada tarea (según la tabla de calificaciones). */
const toGrade = (a) => props.students.filter((s) => { const c = props.gradebook?.cells?.[s.id]?.[a.id]; return c?.submitted && c?.score == null; }).length;
const turnedIn = (a) => props.students.filter((s) => props.gradebook?.cells?.[s.id]?.[a.id]?.submitted).length;
</script>

<template>
    <MobileLayout :title="course.subject" :subtitle="course.group" :back="route('app.docente.clases')" tint="#0b57d0">
        <div v-if="tab === 'trabajo'" class="pg0">
            <p v-if="!groups.length" class="empty"><SolutionOutlined /> Aún no hay trabajo publicado en esta clase.</p>
            <section v-for="g in groups" :key="g.id">
                <h3 class="tp">{{ g.name }}</h3>
                <button v-for="a in g.items" :key="a.id" v-ripple type="button" class="li" @click="router.visit(route('app.docente.actividad', a.id))">
                    <span class="li__ic"><component :is="ICON[a.type] ?? FileTextOutlined" /></span>
                    <span class="li__t">
                        <b>{{ a.title }}</b>
                        <small>{{ a.due_full ? `Entrega: ${a.due_full}` : 'Sin fecha de entrega' }}</small>
                        <small class="li__c">{{ turnedIn(a) }} de {{ students.length }} entregaron</small>
                    </span>
                    <span v-if="toGrade(a)" class="li__n" title="Por calificar">{{ toGrade(a) }}</span>
                </button>
            </section>
            <p class="note"><DesktopOutlined /> Para crear tareas, rúbricas o avisos usa la versión de computadora.<template v-if="drafts"> Tienes {{ drafts }} borrador(es).</template></p>
        </div>

        <div v-else class="pg0">
            <h3 class="tp">Alumnos <small>{{ students.length }}</small></h3>
            <div v-for="s in students" :key="s.id" class="st">
                <span class="av"><img v-if="s.photo_url" :src="s.photo_url" alt="" /><template v-else>{{ initials(s.list_name) }}</template></span>
                <span class="st__t"><b>{{ s.list_name }}</b><small v-if="s.enrollment_number">{{ s.enrollment_number }}</small></span>
            </div>
        </div>

        <template #fab>
            <button v-ripple type="button" class="fab" @click="router.visit(route('app.docente.lista', course.id))"><CheckSquareOutlined /> Pasar lista</button>
        </template>
        <template #bottom>
            <nav class="cnav">
                <button v-for="t in [{ k: 'trabajo', l: 'Trabajo de clase', icon: SolutionOutlined }, { k: 'alumnos', l: 'Alumnos', icon: TeamOutlined }]" :key="t.k" type="button"
                    class="cnav__i" :class="{ on: tab === t.k }" @click="tab = t.k">
                    <span v-ripple class="cnav__pill"><component :is="t.icon" /></span><span class="cnav__l">{{ t.l }}</span>
                </button>
            </nav>
        </template>
    </MobileLayout>
</template>

<style scoped>
.pg0 { padding: 4px 0 96px; }
.empty { margin: 30px 16px; text-align: center; color: var(--m-on-var); display: flex; flex-direction: column; gap: 8px; align-items: center; }
.empty .anticon { font-size: 28px; color: var(--m-outline-var); }
.tp { margin: 16px 16px 4px; padding-bottom: 8px; border-bottom: 1px solid #0b57d0; font-size: 20px; font-weight: 400; color: #0b57d0; display: flex; justify-content: space-between; align-items: baseline; }
.tp small { font-size: 13px; color: var(--m-on-var); }
.li { width: 100%; display: flex; align-items: center; gap: 16px; padding: 12px 16px; border: 0; background: none; text-align: left; font-family: inherit; cursor: pointer; color: var(--m-on); }
.li__ic { width: 40px; height: 40px; border-radius: 50%; display: grid; place-items: center; background: #0b57d0; color: #fff; flex: none; }
.li__t { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.li__t b { font-size: 15px; font-weight: 400; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.li__t small { font-size: 12.5px; color: var(--m-on-var); }
.li__c { color: #146c2e !important; }
.li__n { min-width: 26px; height: 26px; padding: 0 8px; border-radius: 13px; background: #7c4ddb; color: #fff; font-size: 12.5px; font-weight: 500; display: grid; place-items: center; }
.note { margin: 20px 16px; padding: 12px 14px; border-radius: 16px; background: var(--m-surface-2); color: var(--m-on-var); font-size: 13px; display: flex; gap: 10px; align-items: flex-start; line-height: 1.45; }
.st { display: flex; align-items: center; gap: 14px; padding: 10px 16px; }
.av { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; display: grid; place-items: center; background: #7c4ddb; color: #fff; font-size: 13px; font-weight: 500; flex: none; }
.av img { width: 100%; height: 100%; object-fit: cover; }
.st__t { display: flex; flex-direction: column; min-width: 0; }
.st__t b { font-size: 14.5px; font-weight: 400; }
.st__t small { font-size: 12px; color: var(--m-on-var); }
.fab { position: absolute; right: 16px; bottom: 96px; z-index: 6; height: 56px; padding: 0 20px; border-radius: 16px; border: 0; background: #d3e3fd; color: #041e49; font-family: inherit;
    font-size: 15px; font-weight: 500; display: inline-flex; gap: 10px; align-items: center; box-shadow: 0 3px 8px rgba(0, 0, 0, .18); cursor: pointer; }
.cnav { flex: none; display: flex; height: 80px; padding-bottom: env(safe-area-inset-bottom); background: var(--m-surface-2); }
.cnav__i { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; border: 0; background: none; font-family: inherit; color: var(--m-on-var); cursor: pointer; }
.cnav__pill { width: 64px; height: 32px; border-radius: 16px; display: grid; place-items: center; font-size: 20px; }
.cnav__i.on .cnav__pill { background: var(--m-sec-c); color: #001d35; }
.cnav__l { font-size: 12px; font-weight: 500; }
.cnav__i.on .cnav__l { color: var(--m-on); font-weight: 700; }
</style>
