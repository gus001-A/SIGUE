<script setup>
/**
 * Inicio del alumno / tutor (limpio, al estilo Google):
 *  - Saludo con fecha.  - Cuatro indicadores discretos (promedio, asistencia, pendientes, saldo).
 *  - Hoy (clases del día) y Pendientes (abren la tarea).
 *  - Mis clases como tarjetas de Classroom con sus tareas por entregar.  - Calificaciones en barras simples.
 */
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    TrophyOutlined, CalendarOutlined, BookOutlined, WalletOutlined, RightOutlined, CheckCircleFilled,
    FileTextOutlined, ProjectOutlined, ExperimentOutlined, EnvironmentOutlined, InboxOutlined, UserOutlined, MobileOutlined, ClockCircleOutlined, ReadOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PortalHeader from '@/Components/PortalHeader.vue';
import { money, num } from '@/lib/format';

const props = defineProps({
    student: Object, children: Array, isGuardian: Boolean, summary: Object, pending: Array, today: Array,
    classes: { type: Array, default: () => [] },
    grades: { type: Array, default: () => [] },
    attendanceDays: { type: Object, default: () => ({ labels: [], series: [] }) },
    incidents: { type: Array, default: () => [] },
    missingDocs: { type: Number, default: 0 },
    passing: { type: Number, default: 6 },
});
const h = new Date().getHours();
const hello = h < 12 ? 'Buenos días' : h < 19 ? 'Buenas tardes' : 'Buenas noches';
const dl = new Intl.DateTimeFormat('es-MX', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date());
const dateLabel = dl.charAt(0).toUpperCase() + dl.slice(1);
const THEMES = ['#1967d2', '#188038', '#e37400', '#7c4ddb', '#c5221f', '#129eaf', '#d01884', '#5f6368'];
const theme = (i) => THEMES[i % THEMES.length];
const TYPE_ICON = { tarea: FileTextOutlined, proyecto: ProjectOutlined, actividad: ExperimentOutlined };

const metrics = computed(() => [
    { label: 'Promedio', value: props.summary.average !== null ? num(props.summary.average) : '—', icon: TrophyOutlined, c: '#188038', to: 'portal.calificaciones' },
    { label: 'Asistencia (30 días)', value: props.summary.attendance !== null ? props.summary.attendance + '%' : '—', icon: CalendarOutlined, c: '#1967d2', to: 'portal.asistencias' },
    { label: 'Tareas pendientes', value: props.summary.pending, icon: BookOutlined, c: '#e37400', to: 'portal.tareas' },
    { label: props.summary.overdue ? `Saldo · ${props.summary.overdue} vencido(s)` : 'Saldo', value: money(props.summary.debt), icon: WalletOutlined, c: props.summary.overdue ? '#c5221f' : '#7c4ddb', to: 'portal.pagos' },
]);
const gradeColor = (v) => (v == null ? '#c4c7c5' : v < props.passing ? '#c5221f' : v < 8 ? '#e37400' : '#188038');
const isMobile = typeof window !== 'undefined' && window.innerWidth < 900;
</script>

<template>
    <AppLayout title="Inicio" scroll>
        <div class="sd">
            <PortalHeader v-if="isGuardian" :student="student" :children="children" :is-guardian="isGuardian" :title="`Resumen de ${student.first_name}`" />
            <Link v-if="isGuardian && isMobile" :href="route('app.clases')" class="hh__app tutor"><MobileOutlined /> Abrir la app</Link>
            <header v-else class="hh">
                <div class="hh__txt">
                    <span class="hh__date"><CalendarOutlined /> {{ dateLabel }}</span>
                    <h1>{{ hello }}, {{ student.first_name }}</h1>
                    <p>{{ student.group }}<template v-if="student.school_year"> · Ciclo {{ student.school_year }}</template></p>
                    <div class="hh__chips">
                        <span class="hh__chip"><ClockCircleOutlined /> {{ today.length }} clase(s) hoy</span>
                        <span class="hh__chip" :class="summary.pending ? 'warn' : 'ok'"><BookOutlined /> {{ summary.pending ? `${summary.pending} tarea(s) por entregar` : 'Sin tareas pendientes' }}</span>
                    </div>
                    <Link v-if="isMobile" :href="route('app.clases')" class="hh__app"><MobileOutlined /> Abrir la app</Link>
                </div>
                <span class="hh__art"><img v-if="student.photo_url" :src="student.photo_url" alt="" class="hh__photo" /><ReadOutlined v-else /></span>
            </header>

            <div class="dm">
                <Link v-for="m in metrics" :key="m.label" :href="route(m.to)" class="dm__i" :style="{ '--c': m.c }">
                    <span class="dm__ic"><component :is="m.icon" /></span>
                    <span class="dm__t"><b>{{ m.value }}</b><small>{{ m.label }}</small></span>
                </Link>
            </div>

            <div class="dg">
                <section class="dc">
                    <header class="dc__h"><h2>Hoy</h2><Link :href="route('portal.horario')" class="dc__more">Horario <RightOutlined /></Link></header>
                    <div v-if="!today.length" class="dc__empty"><CalendarOutlined /> No hay clases hoy</div>
                    <ul v-else class="tl">
                        <li v-for="t in today" :key="t.subject + t.starts_at" class="tl__i" :class="{ now: t.now }">
                            <span class="tl__time"><b>{{ t.starts_at }}</b><small>{{ t.ends_at }}</small></span>
                            <span class="tl__txt"><b>{{ t.subject }}</b>
                                <small><template v-if="t.teacher"><UserOutlined /> {{ t.teacher }}</template><template v-if="t.room"> · <EnvironmentOutlined /> {{ t.room }}</template></small></span>
                            <span v-if="t.now" class="tl__now">Ahora</span>
                        </li>
                    </ul>
                </section>

                <section class="dc">
                    <header class="dc__h"><h2>Pendientes</h2><Link :href="route('portal.tareas')" class="dc__more">Ver todo <RightOutlined /></Link></header>
                    <div v-if="!pending.length" class="dc__empty"><InboxOutlined /> ¡Todo al día! No tienes tareas pendientes.</div>
                    <Link v-for="p in pending" :key="p.id" :href="route('portal.tarea', p.id)" class="rv">
                        <span class="rv__ic" :class="{ soon: p.soon }"><component :is="TYPE_ICON[p.type] ?? FileTextOutlined" /></span>
                        <span class="rv__t"><b>{{ p.title }}</b><small>{{ p.subject }}</small></span>
                        <span class="rv__due" :class="{ soon: p.soon }">{{ p.due_label || 'Sin fecha' }}</span>
                    </Link>
                </section>
            </div>

            <section class="dsec">
                <header class="dc__h"><h2>Mis clases</h2><Link :href="route('portal.clases')" class="dc__more">Classroom <RightOutlined /></Link></header>
                <div class="cg">
                    <Link v-for="(c, i) in classes" :key="c.id" :href="route('portal.clases', { clase: c.id })" class="cc" :style="{ '--c': theme(i) }">
                        <div class="cc__top"><b>{{ c.subject }}</b><small>{{ c.teacher }}</small></div>
                        <div class="cc__tasks">
                            <Link v-for="u in c.upcoming ?? []" :key="u.id" :href="route('portal.tarea', u.id)" class="cc__up" :class="{ late: u.overdue }" @click.stop>
                                <small>{{ u.due }}</small><span>{{ u.title }}</span>
                            </Link>
                            <small v-if="c.pending > (c.upcoming ?? []).length" class="cc__more">y {{ c.pending - c.upcoming.length }} más</small>
                            <span v-if="!c.pending" class="cc__ok"><CheckCircleFilled /> Sin trabajo pendiente</span>
                        </div>
                    </Link>
                </div>
            </section>

            <div class="dg one">
                <section class="dc">
                    <header class="dc__h"><h2>Calificaciones<small v-if="grades[0]?.period"> · {{ grades[0].period }}</small></h2><Link :href="route('portal.calificaciones')" class="dc__more">Detalle <RightOutlined /></Link></header>
                    <div v-if="!grades.length" class="dc__empty"><TrophyOutlined /> Aún no hay calificaciones</div>
                    <div v-for="g in grades" :key="g.subject" class="gb">
                        <span class="gb__s">{{ g.subject }}</span>
                        <span class="gb__bar"><i :style="{ width: ((g.score ?? 0) * 10) + '%', background: gradeColor(g.score) }" /></span>
                        <b class="gb__v" :style="{ color: gradeColor(g.score) }">{{ g.score == null ? '—' : Number(g.score).toFixed(1) }}</b>
                        <small v-if="g.score != null && !g.final" class="gb__p" title="Calificación parcial, aún sin publicar">parcial</small>
                    </div>
                </section>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.sd { max-width: 1280px; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 20px; padding-bottom: 24px; }

.dm { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.dm__i { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: 16px; background: color-mix(in srgb, var(--c) 7%, #fff); color: inherit; transition: background .15s; }
.dm__i:hover { background: color-mix(in srgb, var(--c) 12%, #fff); }
.dm__ic { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; font-size: 18px; color: var(--c); background: color-mix(in srgb, var(--c) 14%, #fff); flex: none; }
.dm__t { display: flex; flex-direction: column; line-height: 1.2; min-width: 0; }
.dm__t b { font-size: 22px; font-weight: 600; color: #1f1f1f; }
.dm__t small { font-size: 12.5px; color: #5f6368; }

.dg { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 16px; }
.dg.one { grid-template-columns: 1fr; }
.dc { background: #fff; border: 1px solid #e3e7ed; border-radius: 16px; padding: 16px 18px; display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.dc__h { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
.dc__h h2 { margin: 0; flex: 1; font-size: 17px; font-weight: 500; color: #1f1f1f; }
.dc__h h2 small { font-size: 13px; color: #5f6368; font-weight: 400; }
.dc__more { font-size: 13px; font-weight: 500; color: #0b57d0; display: inline-flex; gap: 4px; align-items: center; }
.dc__empty { padding: 26px 10px; text-align: center; color: #5f6368; display: flex; flex-direction: column; align-items: center; gap: 8px; font-size: 13.5px; }
.dc__empty .anticon { font-size: 26px; color: #c4c7c5; }

.tl { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
.tl__i { display: flex; align-items: center; gap: 14px; padding: 10px 6px; border-top: 1px solid #f0f2f5; }
.tl__i:first-child { border-top: 0; }
.tl__i.now { background: #e8f0fe; border-radius: 12px; border-color: transparent; }
.tl__time { width: 50px; display: flex; flex-direction: column; line-height: 1.2; flex: none; }
.tl__time b { font-size: 14px; color: #1f1f1f; font-weight: 600; }
.tl__time small { font-size: 12px; color: #80868b; }
.tl__txt { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.tl__txt b { font-size: 14px; font-weight: 500; color: #1f1f1f; }
.tl__txt small { font-size: 12.5px; color: #5f6368; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tl__now { font-size: 11px; font-weight: 600; color: #0b57d0; background: #d3e3fd; padding: 2px 8px; border-radius: 99px; }

.rv { display: flex; align-items: center; gap: 12px; padding: 10px 6px; border-top: 1px solid #f0f2f5; border-radius: 10px; color: inherit; }
.rv:first-of-type { border-top: 0; }
.rv:hover { background: #f8f9fa; }
.rv__ic { width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; background: #e8f0fe; color: #1967d2; flex: none; }
.rv__ic.soon { background: #fce8e6; color: #c5221f; }
.rv__t { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.rv__t b { font-size: 14px; font-weight: 500; color: #1f1f1f; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rv__t small { font-size: 12.5px; color: #5f6368; }
.rv__due { font-size: 12.5px; color: #5f6368; white-space: nowrap; }
.rv__due.soon { color: #c5221f; font-weight: 500; }

.dsec { display: flex; flex-direction: column; }
.cg { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; }
.cc { display: flex; flex-direction: column; border: 1px solid #dadce0; border-radius: 12px; overflow: hidden; background: #fff; color: inherit; transition: box-shadow .15s; }
.cc:hover { box-shadow: 0 1px 3px rgba(60, 64, 67, .3), 0 4px 8px 3px rgba(60, 64, 67, .15); }
.cc__top { background: var(--c); color: #fff; padding: 14px 16px 22px; display: flex; flex-direction: column; background-image: radial-gradient(circle at 100% 0, rgba(255, 255, 255, .18) 0 60px, transparent 61px); }
.cc__top b { font-size: 18px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cc__top small { font-size: 13px; opacity: .92; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cc__tasks { padding: 10px 16px 12px; min-height: 64px; display: flex; flex-direction: column; gap: 6px; }
.cc__up { display: flex; flex-direction: column; color: #3c4043; min-width: 0; }
.cc__up small { font-size: 11.5px; color: #5f6368; }
.cc__up span { font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cc__up.late small { color: #c5221f; font-weight: 500; }
.cc__up:hover span { color: #0b57d0; text-decoration: underline; }
.cc__more { font-size: 12px; color: #5f6368; }
.cc__badge { font-size: 12px; font-weight: 600; color: #b06000; background: #fef7e0; padding: 2px 10px; border-radius: 99px; }
.cc__ok { font-size: 12px; color: #188038; display: inline-flex; gap: 5px; align-items: center; }

.gb { display: grid; grid-template-columns: 130px minmax(0, 1fr) 40px auto; align-items: center; gap: 12px; padding: 7px 4px; }
.gb__s { font-size: 13px; color: #1f1f1f; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.gb__bar { height: 8px; border-radius: 99px; background: #f1f3f4; overflow: hidden; }
.gb__bar i { display: block; height: 100%; border-radius: 99px; transition: width .4s; }
.gb__v { font-size: 14px; font-weight: 600; text-align: right; }
.gb__p { font-size: 11px; color: #80868b; }


@media (max-width: 1000px) { .dm { grid-template-columns: repeat(2, minmax(0, 1fr)); } .dg { grid-template-columns: 1fr; } }
@media (max-width: 560px) { .gb { grid-template-columns: 96px minmax(0, 1fr) 36px; } .gb__p { display: none; } }
.hh__app.tutor { align-self: flex-start; margin-top: 0; background: #0b57d0; color: #fff !important; }
.hh__photo { width: 100%; height: 100%; object-fit: cover; border-radius: 24px; }
</style>
