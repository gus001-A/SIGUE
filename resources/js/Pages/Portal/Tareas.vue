<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { 
    PaperClipOutlined, UploadOutlined, MessageOutlined, FileTextOutlined, ProjectOutlined, 
    ExperimentOutlined, ClockCircleOutlined, CheckCircleOutlined, TrophyOutlined, 
    WarningOutlined, CalendarOutlined, FilterOutlined, CheckCircleFilled, CloseCircleFilled,
    ClockCircleFilled
} from '@ant-design/icons-vue';
import StatCard from '@/Components/StatCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PortalHeader from '@/Components/PortalHeader.vue';
import MultiFileInput from '@/Components/MultiFileInput.vue';
import FileChips from '@/Components/FileChips.vue';
import SgModal from '@/Components/SgModal.vue';
import { num } from '@/lib/format';
import { openFile } from '@/lib/viewer';

const props = defineProps({ student: Object, children: Array, isGuardian: Boolean, items: Array, canSubmit: Boolean });
const filter = ref('pendientes');
// Detalle de la tarea (instrucciones, rúbrica y comentarios), como en Google Classroom
const dateRange = ref(null);

const shown = computed(() => {
    let filtered = props.items.filter((a) => 
        filter.value === 'todas' || 
        (filter.value === 'pendientes' && !a.submission) || 
        (filter.value === 'entregadas' && a.submission)
    );

    if (dateRange.value && dateRange.value[0] && dateRange.value[1]) {
        const start = new Date(dateRange.value[0]).setHours(0,0,0,0);
        const end = new Date(dateRange.value[1]).setHours(23,59,59,999);
        filtered = filtered.filter(a => {
            if (!a.due_at) return false;
            const t = new Date(a.due_at).getTime();
            return t >= start && t <= end;
        });
    }

    return filtered.sort((a, b) => {
        const aVencida = a.overdue && !a.submission;
        const bVencida = b.overdue && !b.submission;
        if (aVencida && !bVencida) return -1;
        if (!aVencida && bVencida) return 1;
        return new Date(a.due_at || 0) - new Date(b.due_at || 0);
    });
});

const open = ref(false);
const current = ref(null);
const form = useForm({ content: '', files: [], remove_files: [] });
function deliver(a) { router.visit(route('portal.tarea', a.id)); }
const fileErr = computed(() => form.errors.files || Object.entries(form.errors).find(([k]) => k.startsWith('files.'))?.[1]);
const submit = () => form.post(route('portal.entregar', current.value.id), { forceFormData: true, preserveScroll: true, onSuccess: () => (open.value = false) });

const TYPE_COLORS = {
    tarea: { main: '#1d7fc9', bg: '#eff6ff' },
    proyecto: { main: '#7c4ddb', bg: '#f3effa' },
    actividad: { main: '#14a39a', bg: '#e0f2f1' }
};

const getStatusStyle = (a) => {
    if (a.submission) {
        const s = a.submission.status;
        if (s === 'calificada') return { label: 'Calificada', icon: CheckCircleFilled, color: '#2e9b43', bg: '#ecfdf3' };
        if (s === 'rechazada') return { label: 'Rechazada', icon: CloseCircleFilled, color: '#e3262b', bg: '#fef2f2' };
        return { label: 'Entregada', icon: CheckCircleOutlined, color: '#1d7fc9', bg: '#eff6ff' };
    }
    if (a.overdue) return { label: 'Vencida', icon: WarningOutlined, color: '#e3262b', bg: '#fef2f2' };
    return { label: 'Pendiente', icon: ClockCircleFilled, color: '#f39200', bg: '#fff8eb' };
};
</script>

<template>
    <AppLayout title="Tareas">
        <PortalHeader :student="student" :children="children" :is-guardian="isGuardian" title="Tareas y proyectos" />

        <div class="kpi-grid">
            <StatCard label="Pendientes" :value="items.filter((a) => !a.submission && !a.overdue).length" :icon="ClockCircleOutlined" color="amber" />
            <StatCard label="Vencidas sin entregar" :value="items.filter((a) => !a.submission && a.overdue).length" :icon="WarningOutlined" color="red" />
            <StatCard label="Entregadas" :value="items.filter((a) => a.submission).length" :icon="CheckCircleOutlined" color="green" />
            <StatCard label="Calificadas" :value="items.filter((a) => a.submission && a.submission.score != null).length" :icon="TrophyOutlined" color="indigo" />
        </div>

        <div class="filters-bar">
            <div class="filter-group">
                <span class="filter-label"><FilterOutlined /> Mostrar</span>
                <a-segmented v-model:value="filter" :options="[{ value: 'pendientes', label: 'Pendientes' }, { value: 'entregadas', label: 'Entregadas' }, { value: 'todas', label: 'Todas' }]" />
            </div>
            <div class="filter-group">
                <span class="filter-label"><CalendarOutlined /> Fecha de entrega</span>
                <a-range-picker v-model:value="dateRange" format="DD/MM/YYYY" placeholder="['Desde', 'Hasta']" allow-clear style="width: 260px" />
            </div>
        </div>

        <a-card :bordered="false" class="sg-fill">
            <div class="sg-scroll">
                <a-empty v-if="!shown.length" description="No hay actividades que coincidan" />
                
                <div v-for="a in shown" :key="a.id" class="task-card is-click" @click="deliver(a)" :style="{ '--tc': TYPE_COLORS[a.type]?.main || '#1d7fc9', '--tb': TYPE_COLORS[a.type]?.bg || '#eff6ff' }">
                    
                    <div class="tc-icon">
                        <FileTextOutlined v-if="a.type === 'tarea'" />
                        <ProjectOutlined v-else-if="a.type === 'proyecto'" />
                        <ExperimentOutlined v-else />
                    </div>

                    <div class="tc-main">
                        <div class="tc-header">
                            <span class="tc-subject">{{ a.subject }}</span>
                            <b class="tc-title">{{ a.title }}</b>
                        </div>
                        
                        <div class="tc-meta">
                            <span :class="{ 'is-overdue': a.overdue && !a.submission }">
                                <CalendarOutlined /> {{ a.due_at || 'Sin fecha' }}
                            </span>
                            <span><TrophyOutlined /> {{ a.max_score }} pts</span>
                        </div>
                        <div v-if="a.files?.length" class="tc-files"><span class="tc-files__l"><PaperClipOutlined /> Material:</span><FileChips :files="a.files" :title="a.title" /></div>
                        
                        <div v-if="a.description" class="tc-desc">{{ a.description }}</div>
                        <div class="tc-extra">
                            <span v-if="a.rubric?.length"><TrophyOutlined /> Con rúbrica</span>
                            <span v-if="a.comments?.length"><MessageOutlined /> {{ a.comments.length }} comentario{{ a.comments.length === 1 ? '' : 's' }} de la clase</span>
                            <span v-if="a.private?.length" class="pv"><MessageOutlined /> {{ a.private.length }} privado{{ a.private.length === 1 ? '' : 's' }}</span>
                            <span class="see">Ver detalle</span>
                        </div>
                        <div v-if="a.submission?.files?.length" class="tc-files"><span class="tc-files__l"><UploadOutlined /> Tu entrega:</span><FileChips :files="a.submission.files" title="Mi entrega" subtitle="Archivo entregado" /></div>
                    </div>

                    <div class="tc-side">
                        <div class="status-card" :style="{ background: getStatusStyle(a).bg, color: getStatusStyle(a).color }">
                            <component :is="getStatusStyle(a).icon" />
                            <span>{{ getStatusStyle(a).label }}</span>
                        </div>

                        <!-- CORRECCIÓN AQUÍ: Evaluamos que a.submission exista y el score no sea nulo ni undefined -->
                        <div v-if="a.submission && a.submission.score != null" class="tc-score">
                            {{ num(a.submission.score) }}<small>/{{ a.max_score }}</small>
                        </div>
                        
                        <div v-if="a.submission" class="tc-date">
                            <ClockCircleOutlined /> {{ a.submission.submitted_at }}
                        </div>
                        
                        <a-button v-if="canSubmit && a.submission?.status !== 'calificada'" type="primary" size="middle" class="tc-btn" @click.stop="deliver(a)">
                            <UploadOutlined /> {{ a.submission ? 'Reemplazar' : 'Entregar' }}
                        </a-button>
                    </div>
                </div>
            </div>
        </a-card>


            </AppLayout>
</template>

<style scoped>
.tc-extra { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 6px; font-size: 12px; color: #5d7085; }
.tc-extra span { display: inline-flex; gap: 4px; align-items: center; }
.tc-extra .pv { color: #7c4ddb; }
.tc-extra .see { color: #1d7fc9; font-weight: 700; margin-left: auto; }
.task-card.is-click { cursor: pointer; }
.task-card.is-click:hover { box-shadow: 0 10px 24px -18px rgba(10, 31, 68, .45); }
.tc-files { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-top: 6px; }
.tc-files__l { font-size: 12px; font-weight: 600; color: #5d7085; display: inline-flex; gap: 4px; align-items: center; }
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 20px;
}
@media (max-width: 1024px) {
    .kpi-grid { grid-template-columns: repeat(2, 1fr); }
}

.filters-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fff;
    padding: 12px 20px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
}
.filter-group {
    display: flex;
    align-items: center;
    gap: 12px;
}
.filter-label {
    font-size: 13px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.task-card {
    display: flex;
    gap: 16px;
    padding: 16px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    margin-bottom: 12px;
    border-left: 4px solid var(--tc);
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
    transition: transform 0.2s, box-shadow 0.2s;
}
.task-card:hover {
    box-shadow: 0 8px 20px -8px rgba(0,0,0,0.08);
    transform: translateY(-2px);
}

.tc-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: var(--tc);
    color: #fff;
    display: grid;
    place-items: center;
    font-size: 22px;
    flex: none;
    box-shadow: 0 4px 10px -2px color-mix(in srgb, var(--tc) 50%, transparent);
}

.tc-main {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}
.tc-header {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 6px;
}
.tc-subject {
    background: var(--tb);
    color: var(--tc);
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.tc-title {
    font-size: 15px;
    color: #0f172a;
    font-weight: 700;
}
.tc-meta {
    display: flex;
    align-items: center;
    gap: 14px;
    font-size: 12.5px;
    color: #64748b;
    flex-wrap: wrap;
    margin-bottom: 8px;
}
.tc-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}
.tc-meta .is-overdue {
    color: #e3262b;
    font-weight: 700;
    background: #fef2f2;
    padding: 2px 6px;
    border-radius: 4px;
}
.tc-attachment {
    display: flex;
    align-items: center;
    gap: 4px;
    color: #1d7fc9;
    font-weight: 600;
    cursor: pointer;
    text-decoration: underline;
}
.tc-attachment:hover {
    color: #0f5a99;
}
.tc-desc {
    font-size: 13.5px;
    color: #334155;
    line-height: 1.5;
    margin-bottom: 8px;
}
.tc-feedback {
    background: #ecfdf5;
    color: #166534;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 13px;
    display: flex;
    gap: 6px;
    align-items: flex-start;
    margin-top: 4px;
}

.tc-side {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 150px;
    flex: none;
    background: #f8fafc;
    border-radius: 12px;
    padding: 12px;
    border: 1px solid #e2e8f0;
}
.status-card {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
    width: 100%;
    justify-content: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
.tc-score {
    font-size: 1.8rem;
    font-weight: 900;
    color: #0f5a99;
    line-height: 1;
    margin-top: 10px;
}
.tc-score small {
    font-size: 14px;
    color: #94a3b8;
    font-weight: 500;
}
.tc-date {
    font-size: 11.5px;
    color: #94a3b8;
    margin-top: 6px;
    display: flex;
    align-items: center;
    gap: 4px;
}
.tc-btn {
    margin-top: 12px;
    border-radius: 8px;
    font-weight: 700;
    background: var(--tc);
    border-color: var(--tc);
    box-shadow: 0 4px 10px -4px var(--tc);
    width: 100%;
}
.tc-btn:hover {
    background: color-mix(in srgb, var(--tc) 80%, #000) !important;
    border-color: color-mix(in srgb, var(--tc) 80%, #000) !important;
}

/* Celular: la tarjeta se acomoda en columna (ícono pequeño, estado abajo) */
@media (max-width: 600px) {
    .task-card { flex-wrap: wrap; gap: 10px; padding: 12px; }
    .tc-icon { width: 36px; height: 36px; font-size: 16px; border-radius: 10px; }
    .tc-main { flex: 1 1 calc(100% - 50px); }
    .tc-side { flex: 1 1 100%; min-width: 0; flex-direction: row; flex-wrap: wrap; justify-content: space-between; gap: 8px; padding: 8px 10px; }
    .tc-side .tc-btn { width: auto; }
    .tc-extra .see { margin-left: 0; }
}
</style>
