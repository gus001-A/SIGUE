<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { 
    BookOutlined, TeamOutlined, FileTextOutlined, ScheduleOutlined, ClockCircleOutlined, 
    ArrowRightOutlined, EnvironmentOutlined, FieldTimeOutlined, RightOutlined
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHead from '@/Components/PageHead.vue';
import StatCard from '@/Components/StatCard.vue';

const props = defineProps({ classes: Array, schedule: Array, days: Object });
const PALETTE = ['#1d7fc9', '#14a39a', '#2e9b43', '#f39200', '#e3262b', '#7c4ddb', '#e0457b', '#0f5a99'];
const colorOf = (id) => PALETTE[Math.max(0, props.classes.findIndex((c) => c.id === id)) % PALETTE.length];
const usedDays = computed(() => [1, 2, 3, 4, 5, 6, 7].filter((d) => d <= 5 || props.schedule.some((s) => s.day === d)));
const byDay = (d) => props.schedule.filter((s) => s.day === d);
const today = new Date().getDay() || 7;
const toMin = (t) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
const hours = computed(() => props.schedule.reduce((t, s) => t + (toMin(s.ends_at) - toMin(s.starts_at)) / 60, 0));
</script>

<template>
    <AppLayout title="Mis clases">
        <!-- HERO -->
        <section class="sg-hero">
            <div class="sg-hero__grid">
                <div>
                    <span class="sg-hero__eyebrow"><BookOutlined /> Módulo docente</span>
                    <h1 class="sg-hero__title">Mis clases</h1>
                    <p class="sg-hero__sub">Grupos, asignaturas y horario del ciclo actual.</p>
                </div>
            </div>
        </section>

        <!-- KPIs -->
        <div class="kpi-grid">
            <StatCard label="Clases asignadas" :value="classes.length" :icon="BookOutlined" color="indigo" />
            <StatCard label="Alumnos atendidos" :value="classes.reduce((t, c) => t + c.students, 0)" :icon="TeamOutlined" color="green" />
            <StatCard label="Horas por semana" :value="hours.toFixed(1)" :icon="FieldTimeOutlined" color="violet" />
            <StatCard label="Actividades publicadas" :value="classes.reduce((t, c) => t + c.assignments, 0)" :icon="FileTextOutlined" color="amber" />
        </div>

        <!-- TARJETAS DE CLASE -->
        <div class="classes-grid">
            <Link v-for="c in classes" :key="c.id" :href="route('docente.clase', c.id)" class="class-card" :style="{ '--c': colorOf(c.id) }">
                
                <div class="cc-header">
                    <span class="cc-badge">{{ c.group }}</span>
                    <div class="cc-title">
                        <b>{{ c.subject }}</b>
                        <small>{{ c.code }} · {{ c.shift }}</small>
                    </div>
                </div>

                <div class="cc-stats">
                    <span><TeamOutlined /> {{ c.students }} alumnos</span>
                    <span><FileTextOutlined /> {{ c.assignments }} actividades</span>
                </div>

                <div class="cc-footer">
                    <span>Entrar a la clase</span>
                    <ArrowRightOutlined />
                </div>
            </Link>
        </div>

        <!-- HORARIO SEMANAL -->
        <a-card :bordered="false" class="schedule-card">
            <template #title><span class="sg-card-title" style="--c: #14a39a"><ScheduleOutlined /> Horario semanal</span></template>
            
            <a-empty v-if="!schedule.length" :image="false" description="Sin horario capturado" />
            
            <div v-else class="week-grid" :style="{ gridTemplateColumns: `repeat(${usedDays.length}, minmax(160px, 1fr))` }">
                <div v-for="d in usedDays" :key="d" class="day-col">
                    <div class="day-header" :class="{ 'is-today': d === today }">
                        {{ days[d] }}
                        <span v-if="d === today" class="today-dot"></span>
                    </div>
                    
                    <div class="day-slots">
                        <Link v-for="s in byDay(d)" :key="s.class_id + s.starts_at" :href="route('docente.clase', s.class_id)" class="slot-card" :style="{ '--c': colorOf(s.class_id) }">
                            <!-- Columna izquierda: Hora -->
                            <div class="slot-time-col">
                                <span class="slot-time">{{ s.starts_at }}</span>
                                <span class="slot-time-end">{{ s.ends_at }}</span>
                            </div>
                            
                            <!-- Columna derecha: Contenido -->
                            <div class="slot-content-col">
                                <b class="slot-subject">{{ s.subject }}</b>
                                <span class="slot-group">{{ s.group }}</span>
                                <span v-if="s.room" class="slot-room"><EnvironmentOutlined /> {{ s.room }}</span>
                            </div>
                        </Link>
                        
                        <div v-if="!byDay(d).length" class="slot-empty">Libre</div>
                    </div>
                </div>
            </div>
        </a-card>
    </AppLayout>
</template>

<style scoped>
/* ============ HERO ============ */
.sg-hero {
    background: linear-gradient(135deg, #0b3f6e, #1d7fc9 55%, #14a39a);
    border-radius: 16px;
    padding: 32px;
    color: #fff;
    margin-bottom: 24px;
}
.sg-hero__grid {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}
.sg-hero__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #b9e6e1;
    margin-bottom: 8px;
}
.sg-hero__title {
    font-size: 2.2rem;
    font-weight: 800;
    margin: 0;
    letter-spacing: -0.5px;
}
.sg-hero__sub {
    font-size: 1.05rem;
    color: rgba(255, 255, 255, 0.85);
    margin: 8px 0 0;
    max-width: 600px;
}

/* ============ KPIs ============ */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 24px;
}
@media (max-width: 1024px) {
    .kpi-grid { grid-template-columns: repeat(2, 1fr); }
}

/* ============ TARJETAS DE CLASE ============ */
.classes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.class-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    overflow: hidden;
    text-decoration: none;
    color: inherit;
    transition: transform 0.2s, box-shadow 0.2s;
    display: flex;
    flex-direction: column;
}
.class-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px -12px rgba(0,0,0,0.15);
    border-color: var(--c);
}
.cc-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px 18px 12px;
}
.cc-badge {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    color: #fff;
    font-weight: 900;
    font-size: 16px;
    background: var(--c);
    flex: none;
    box-shadow: 0 4px 10px -2px color-mix(in srgb, var(--c) 50%, transparent);
}
.cc-title {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.cc-title b {
    color: #0f172a;
    font-size: 16px;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cc-title small {
    color: #64748b;
    font-size: 12.5px;
    text-transform: capitalize;
}
.cc-stats {
    display: flex;
    gap: 16px;
    padding: 0 18px 14px;
    font-size: 13px;
    color: #475569;
}
.cc-stats span {
    display: flex;
    align-items: center;
    gap: 5px;
}
.cc-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 18px;
    background: color-mix(in srgb, var(--c) 6%, #fff);
    border-top: 1px solid color-mix(in srgb, var(--c) 15%, #fff);
    color: var(--c);
    font-size: 13px;
    font-weight: 700;
    transition: background 0.2s;
}
.class-card:hover .cc-footer {
    background: var(--c);
    color: #fff;
}

/* ============ HORARIO SEMANAL ============ */
.schedule-card {
    border-radius: 16px;
    box-shadow: 0 4px 12px -4px rgba(0,0,0,0.03);
    border: 1px solid #e2e8f0;
}
.week-grid {
    display: grid;
    gap: 12px;
    overflow-x: auto;
    padding-bottom: 8px;
}
.day-col {
    min-width: 160px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.day-header {
    font-size: 13px;
    font-weight: 800;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 8px 12px;
    background: #f8fafc;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.day-header.is-today {
    background: #eff6ff;
    color: #1d7fc9;
}
.today-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #1d7fc9;
    box-shadow: 0 0 0 3px rgba(29, 127, 201, 0.2);
}
.day-slots {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

/* NUEVO DISEÑO DE LOS SLOTS: HORA A LA IZQUIERDA */
.slot-card {
    display: flex;
    align-items: stretch;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
    text-decoration: none;
    color: inherit;
    transition: all 0.2s;
    border-left: 4px solid var(--c); /* Borde de color en el lado izquierdo */
}
.slot-card:hover {
    box-shadow: 0 4px 10px -4px var(--c);
    transform: translateX(2px);
}

/* Columna Izquierda: Hora */
.slot-time-col {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    background: color-mix(in srgb, var(--c) 8%, #fff);
    padding: 8px 10px;
    width: 60px;
    flex: none;
    border-right: 1px solid color-mix(in srgb, var(--c) 15%, #fff);
}
.slot-time {
    font-size: 12px;
    color: var(--c);
    font-weight: 800;
}
.slot-time-end {
    font-size: 10.5px;
    color: #94a3b8;
    font-weight: 600;
}

/* Columna Derecha: Contenido */
.slot-content-col {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 8px 12px;
    flex: 1;
    min-width: 0;
}
.slot-subject {
    font-size: 13px;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.slot-group {
    font-size: 11.5px;
    color: #64748b;
    font-weight: 600;
}
.slot-room {
    font-size: 11px;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 4px;
}
.slot-empty {
    padding: 20px 10px;
    text-align: center;
    font-size: 12px;
    color: #cbd5e1;
    border: 1px dashed #e2e8f0;
    border-radius: 10px;
    background: #fafbfc;
}
</style>