<script setup>
/**
 * Marco común de las vistas de una clase del docente (Pase de lista, Classroom, Evaluación,
 * Incidencias y Alumnos): encabezado con la clase de trabajo, selector para cambiar de clase
 * y accesos a las otras secciones. Cada sección es una vista independiente.
 */
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import {
    CheckSquareOutlined, BookOutlined, FileDoneOutlined, WarningOutlined, TeamOutlined, FilePdfOutlined,
    SwapOutlined, ClockCircleOutlined, CalendarOutlined, ReadOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    course: { type: Object, required: true },
    section: { type: String, required: true },
    count: { type: Number, default: 0 },
    scroll: { type: Boolean, default: false },
});
const page = usePage();
const classes = computed(() => page.props.teaching?.classes ?? []);

const SECTIONS = [
    { key: 'asistencia', label: 'Pase de lista', icon: CheckSquareOutlined, c: '#2e9b43' },
    { key: 'classroom', label: 'Classroom', icon: BookOutlined, c: '#f39200' },
    { key: 'evaluacion', label: 'Evaluación', icon: FileDoneOutlined, c: '#7c4ddb' },
    { key: 'incidencias', label: 'Incidencias', icon: WarningOutlined, c: '#e3262b' },
    { key: 'alumnos', label: 'Alumnos', icon: TeamOutlined, c: '#14a39a' },
];
const current = computed(() => SECTIONS.find((s) => s.key === props.section) ?? SECTIONS[0]);
const goSection = (k) => k !== props.section && router.visit(route('docente.clase.' + k, props.course.id));
const switchClass = (id) => router.visit(route('docente.clase.' + props.section, id));
</script>

<template>
    <AppLayout :title="`${current.label} · ${course.subject} ${course.group}`" :scroll="scroll">
        <section class="cs" :style="{ '--c': current.c }">
            <span class="cs__ic"><component :is="current.icon" /></span>
            <div class="cs__txt">
                <small>{{ current.label }}</small>
                <h1>{{ course.subject }} <span>· Grupo {{ course.group }}</span></h1>
                <div class="cs__meta">
                    <span><CalendarOutlined /> {{ course.school_year }}</span>
                    <span><TeamOutlined /> {{ count }} alumnos</span>
                    <span v-if="course.schedule?.length"><ClockCircleOutlined /> {{ course.schedule.join(' · ') }}</span>
                    <span v-else class="warn"><ClockCircleOutlined /> Sin horario capturado</span>
                </div>
            </div>
            <div class="cs__acts">
                <div v-if="classes.length > 1" class="cs__switch">
                    <SwapOutlined />
                    <a-select :value="course.id" :options="classes" :bordered="false" :dropdown-match-select-width="false" class="cs__sel" @change="switchClass" />
                </div>
                <a-button @click="router.visit(route('docente.clases'))"><template #icon><ReadOutlined /></template>Mis clases</a-button>
                <a :href="route('docente.asistencia.reporte', course.id)" download>
                    <a-button><template #icon><FilePdfOutlined style="color: #e3262b" /></template>Reporte</a-button>
                </a>
            </div>
        </section>

        <slot />
    </AppLayout>
</template>

<style scoped>
.cs {
    flex: none; display: flex; align-items: center; gap: 12px; padding: 7px 12px 7px 16px; margin-bottom: 10px; border-radius: 14px; position: relative; overflow: hidden;
    background: linear-gradient(115deg, #fff 0%, #fff 55%, color-mix(in srgb, var(--c) 9%, #fff) 100%); border: 1px solid #e2e8f0;
    box-shadow: 0 10px 24px -20px rgba(11, 47, 85, .5);
}
.cs::before { content: ''; position: absolute; inset: 0 auto 0 0; width: 5px; background: var(--c); }
.cs__ic { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; color: #fff; font-size: 16px; flex: none; background: linear-gradient(135deg, var(--c), color-mix(in srgb, var(--c) 55%, #0b2f55)); box-shadow: 0 10px 20px -10px var(--c); }
.cs__txt { flex: 1; min-width: 0; }
.cs__txt small { font-size: 10px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--c); }
.cs__txt h1 { margin: 0; font-size: 15.5px; font-weight: 800; color: #0b2f55; line-height: 1.2; }
.cs__txt h1 span { color: #64748b; font-weight: 700; }
.cs__meta { display: flex; flex-wrap: wrap; gap: 2px 12px; font-size: 11.5px; color: #64748b; margin-top: 0; }
.cs__meta span { display: inline-flex; align-items: center; gap: 5px; }
.cs__meta .warn { color: #b45309; }
.cs__acts { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.cs__acts :deep(.ant-btn), .cs__switch { height: 32px; }
.cs__acts :deep(.ant-btn) { border-radius: 10px; font-weight: 600; }
.cs__switch { display: flex; align-items: center; gap: 4px; padding: 2px 4px 2px 10px; border-radius: 10px; border: 1px solid #e2e8f0; background: #fff; color: #64748b; }
.cs__sel { min-width: 200px; }
.cs__sel :deep(.ant-select-selection-item) { font-weight: 700; color: #0b2f55; }
.cs-nav { flex: none; display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px; }
.cs-nav__b {
    display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 999px; border: 1px solid #e2e8f0; background: #fff;
    color: #475569; font-weight: 600; font-size: 13px; cursor: pointer; font-family: inherit; transition: all .15s;
}
.cs-nav__b:hover { border-color: var(--c, #1d7fc9); color: var(--c, #1d7fc9); }
.cs-nav__b.on { color: #fff; background: var(--c); border-color: var(--c); box-shadow: 0 6px 14px -8px var(--c); }
.cs-nav__all { margin-left: auto; --c: #1d7fc9; }
@media (max-width: 900px) { .cs { flex-wrap: wrap; } .cs-nav__all { margin-left: 0; } }
</style>
