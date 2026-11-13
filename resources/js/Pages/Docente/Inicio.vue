<script setup>
/**
 * Mi día del docente (limpio, al estilo Google):
 *  - Saludo con fecha y resumen en una línea.
 *  - Cuatro indicadores discretos.
 *  - Hoy: clases del día con pase de lista.  - Por revisar: entregas sin calificar agrupadas por tarea.
 *  - Mis clases: tarjetas como Google Classroom.  - Seguimientos de tutoría.
 */
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    CalendarOutlined, FormOutlined, IdcardOutlined, RightOutlined, CheckCircleFilled, ClockCircleOutlined, TeamOutlined,
    FileTextOutlined, ProjectOutlined, ExperimentOutlined, CheckSquareOutlined, EnvironmentOutlined, InboxOutlined, AlertOutlined, ReadOutlined, MobileOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    teacher: String, classes: Array, today: Array, toGrade: Array, pendingSubmissions: Number, followUps: Array, hasTeacher: Boolean,
    toReview: { type: Array, default: () => [] },
});
const page = usePage();
const name = computed(() => page.props.auth.user.first_name ?? '');
const h = new Date().getHours();
const hello = h < 12 ? 'Buenos días' : h < 19 ? 'Buenas tardes' : 'Buenas noches';
const dl = new Intl.DateTimeFormat('es-MX', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date());
const dateLabel = dl.charAt(0).toUpperCase() + dl.slice(1);
const noList = computed(() => props.today.filter((t) => !t.taken && t.state !== 'upcoming').length);
/** Listas que ya se pueden pasar (la clase ya empezó) y aún no se toman. */
const isMobile = typeof window !== 'undefined' && window.innerWidth < 900;
const pendingList = computed(() => props.today.filter((t) => !t.taken && t.state !== 'upcoming').length);

const THEMES = ['#1967d2', '#188038', '#e37400', '#7c4ddb', '#c5221f', '#129eaf', '#d01884', '#5f6368'];
const theme = (i) => THEMES[i % THEMES.length];
const TYPE_ICON = { tarea: FileTextOutlined, proyecto: ProjectOutlined, actividad: ExperimentOutlined };

const metrics = computed(() => [
    { label: 'Clases hoy', value: props.today.length, icon: CalendarOutlined, c: '#1967d2' },
    { label: 'Sin pase de lista', value: noList.value, icon: CheckSquareOutlined, c: noList.value ? '#e37400' : '#188038' },
    { label: 'Por calificar', value: props.pendingSubmissions, icon: FormOutlined, c: '#7c4ddb' },
    { label: 'Tutorías abiertas', value: props.followUps.length, icon: IdcardOutlined, c: '#129eaf' },
]);
</script>

<template>
    <AppLayout title="Mi día" scroll>
        <div class="dd">
            <header class="hh">
                <div class="hh__txt">
                    <span class="hh__date"><CalendarOutlined /> {{ dateLabel }}</span>
                    <h1>{{ hello }}, {{ name }}</h1>
                    <p>Este es el resumen de tu día.</p>
                    <div class="hh__chips">
                        <span class="hh__chip"><ClockCircleOutlined /> {{ today.length }} clase(s) hoy</span>
                        <span class="hh__chip" :class="pendingList ? 'warn' : 'ok'"><CheckSquareOutlined /> {{ pendingList ? `${pendingList} lista(s) por pasar` : 'Listas al día' }}</span>
                        <span class="hh__chip"><FormOutlined /> {{ pendingSubmissions }} por revisar</span>
                    </div>
                    <Link v-if="isMobile" :href="route('app.docente.inicio')" class="hh__app"><MobileOutlined /> Abrir la app</Link>
                </div>
                <span class="hh__art"><ReadOutlined /></span>
            </header>

            <a-alert v-if="!hasTeacher" type="warning" show-icon class="dd__alert" message="Tu usuario no está vinculado a un registro de docente. Pide a la administración que lo vincule." />

            <div class="dm">
                <div v-for="m in metrics" :key="m.label" class="dm__i" :style="{ '--c': m.c }">
                    <span class="dm__ic"><component :is="m.icon" /></span>
                    <span class="dm__t"><b>{{ m.value }}</b><small>{{ m.label }}</small></span>
                </div>
            </div>

            <div class="dg">
                <!-- Hoy -->
                <section class="dc">
                    <header class="dc__h"><h2>Hoy</h2><Link :href="route('docente.clases')" class="dc__more">Mis clases <RightOutlined /></Link></header>
                    <div v-if="!today.length" class="dc__empty"><CalendarOutlined /> Sin clases programadas hoy</div>
                    <ul v-else class="tl">
                        <li v-for="t in today" :key="t.class_id + t.starts_at" class="tl__i" :class="{ now: t.state === 'now', off: t.state === 'upcoming' }">
                            <span class="tl__time"><b>{{ t.starts_at }}</b><small>{{ t.ends_at }}</small></span>
                            <Link :href="route('docente.clase', { class: t.class_id })" class="tl__txt">
                                <b>{{ t.subject }}</b>
                                <small>{{ t.group }}<template v-if="t.room"> · <EnvironmentOutlined /> {{ t.room }}</template></small>
                            </Link>
                            <span v-if="t.state === 'now'" class="tl__now">Ahora</span>
                            <Link v-if="t.taken" :href="route('docente.clase.asistencia', { class: t.class_id, session: t.session })" class="tl__ok"><CheckCircleFilled /> Lista tomada</Link>
                            <!-- Solo se puede pasar lista desde la hora de inicio de la clase -->
                            <a-tooltip v-else-if="t.state === 'upcoming'" :title="`Podrás pasar lista a partir de las ${t.starts_at}`">
                                <span class="tl__wait"><ClockCircleOutlined /> {{ t.starts_at }}</span>
                            </a-tooltip>
                            <Link v-else :href="route('docente.clase.asistencia', { class: t.class_id, session: t.session })" class="tl__btn" :class="{ late: t.state === 'past' }">Pasar lista</Link>
                        </li>
                    </ul>
                </section>

                <!-- Por revisar -->
                <section class="dc">
                    <header class="dc__h"><h2>Por revisar</h2><span v-if="pendingSubmissions" class="dc__count">{{ pendingSubmissions }}</span></header>
                    <div v-if="!toReview.length" class="dc__empty"><InboxOutlined /> Todo revisado. ¡Buen trabajo!</div>
                    <Link v-for="r in toReview" :key="r.id" :href="route('docente.actividad', r.id)" class="rv">
                        <span class="rv__ic"><component :is="TYPE_ICON[r.type] ?? FileTextOutlined" /></span>
                        <span class="rv__t"><b>{{ r.title }}</b><small>{{ r.subject }} · {{ r.group }} · última entrega {{ r.last }}</small></span>
                        <span class="rv__n"><b>{{ r.count }}</b><small>entregada{{ r.count === 1 ? '' : 's' }}</small></span>
                    </Link>
                </section>
            </div>

            <!-- Mis clases -->
            <section class="dsec">
                <header class="dc__h"><h2>Mis clases</h2><Link :href="route('docente.clases')" class="dc__more">Ver todas <RightOutlined /></Link></header>
                <div class="cg">
                    <Link v-for="(c, i) in classes" :key="c.id" :href="route('docente.clase', { class: c.id, tab: 'classroom' })" class="cc" :style="{ '--c': theme(i) }">
                        <div class="cc__top">
                            <b>{{ c.subject }}</b>
                            <small>{{ c.group }}<template v-if="c.level"> · {{ c.level }}</template></small>
                        </div>
                        <div class="cc__body">
                            <span><TeamOutlined /> {{ c.students }} alumnos</span>
                            <span><ClockCircleOutlined /> {{ c.hours }} h/sem</span>
                        </div>
                        <div class="cc__foot">
                            <span v-if="c.to_grade" class="cc__badge">{{ c.to_grade }} por calificar</span>
                            <span v-else class="cc__ok"><CheckCircleFilled /> Al día</span>
                        </div>
                    </Link>
                </div>
            </section>

            <!-- Tutorías -->
            <section v-if="followUps.length" class="dc">
                <header class="dc__h"><h2>Seguimientos de tutoría</h2><Link :href="route('docente.tutorias')" class="dc__more">Ver todas <RightOutlined /></Link></header>
                <div v-for="f in followUps" :key="f.id" class="fu">
                    <span class="fu__av">{{ (f.student || '?').split(' ').slice(0, 2).map((x) => x[0]).join('') }}</span>
                    <span class="fu__t"><b>{{ f.student }}</b><small>{{ f.topic }}</small></span>
                    <span class="fu__d" :class="{ late: f.overdue }"><AlertOutlined v-if="f.overdue" /> {{ f.date || 'Sin fecha' }}</span>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.dd { max-width: 1280px; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 20px; padding-bottom: 24px; }
/* fecha */ .dd__date { color: #5f6368; font-size: 13px; font-weight: 500; }
.dd__alert { border-radius: 12px; }

.dm { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.dm__i { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: 16px; background: color-mix(in srgb, var(--c) 7%, #fff); }
.dm__ic { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; font-size: 18px; color: var(--c); background: color-mix(in srgb, var(--c) 14%, #fff); flex: none; }
.dm__t { display: flex; flex-direction: column; line-height: 1.2; }
.dm__t b { font-size: 24px; font-weight: 600; color: #1f1f1f; }
.dm__t small { font-size: 12.5px; color: #5f6368; }

.dg { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 16px; }
.dc { background: #fff; border: 1px solid #e3e7ed; border-radius: 16px; padding: 16px 18px; display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.dc__h { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
.dc__h h2 { margin: 0; flex: 1; font-size: 17px; font-weight: 500; color: #1f1f1f; }
.dc__more { font-size: 13px; font-weight: 500; color: #0b57d0; display: inline-flex; gap: 4px; align-items: center; }
.dc__count { background: #7c4ddb; color: #fff; font-size: 12px; font-weight: 600; padding: 1px 9px; border-radius: 99px; }
.dc__empty { padding: 28px 10px; text-align: center; color: #5f6368; display: flex; flex-direction: column; align-items: center; gap: 8px; font-size: 13.5px; }
.dc__empty .anticon { font-size: 26px; color: #c4c7c5; }

.tl { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
.tl__i { display: flex; align-items: center; gap: 14px; padding: 10px 6px; border-top: 1px solid #f0f2f5; }
.tl__i:first-child { border-top: 0; }
.tl__i.now { background: #e8f0fe; border-radius: 12px; border-color: transparent; }
.tl__time { width: 50px; display: flex; flex-direction: column; line-height: 1.2; flex: none; }
.tl__time b { font-size: 14px; color: #1f1f1f; font-weight: 600; }
.tl__time small { font-size: 12px; color: #80868b; }
.tl__txt { flex: 1; min-width: 0; display: flex; flex-direction: column; color: inherit; }
.tl__txt b { font-size: 14px; font-weight: 500; color: #1f1f1f; }
.tl__txt small { font-size: 12.5px; color: #5f6368; }
.tl__now { font-size: 11px; font-weight: 600; color: #0b57d0; background: #d3e3fd; padding: 2px 8px; border-radius: 99px; }
.tl__ok { font-size: 12.5px; color: #188038; font-weight: 500; display: inline-flex; gap: 5px; align-items: center; }
.tl__btn { font-size: 13px; font-weight: 500; color: #0b57d0; border: 1px solid #c4c7c5; border-radius: 99px; padding: 5px 14px; white-space: nowrap; }
.tl__btn:hover { background: #f0f4f9; }
.tl__btn.late { color: #fff; background: #e37400; border-color: #e37400; }
.tl__btn.late:hover { background: #c26401; }
.tl__wait { font-size: 12.5px; color: #80868b; display: inline-flex; gap: 5px; align-items: center; padding: 5px 12px; border-radius: 99px; background: #f1f3f4; cursor: help; }
.tl__i.off .tl__txt b, .tl__i.off .tl__time b { color: #5f6368; }

.rv { display: flex; align-items: center; gap: 12px; padding: 10px 6px; border-top: 1px solid #f0f2f5; border-radius: 10px; color: inherit; }
.rv:first-of-type { border-top: 0; }
.rv:hover { background: #f8f9fa; }
.rv__ic { width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; background: #f3e8fd; color: #7c4ddb; flex: none; }
.rv__t { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.rv__t b { font-size: 14px; font-weight: 500; color: #1f1f1f; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rv__t small { font-size: 12.5px; color: #5f6368; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rv__n { display: flex; flex-direction: column; align-items: center; line-height: 1.1; flex: none; }
.rv__n b { font-size: 20px; font-weight: 500; color: #1f1f1f; }
.rv__n small { font-size: 11px; color: #5f6368; }

.dsec { display: flex; flex-direction: column; }
.cg { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; }
.cc { display: flex; flex-direction: column; border: 1px solid #dadce0; border-radius: 12px; overflow: hidden; background: #fff; color: inherit; transition: box-shadow .15s; }
.cc:hover { box-shadow: 0 1px 3px rgba(60, 64, 67, .3), 0 4px 8px 3px rgba(60, 64, 67, .15); }
.cc__top { background: var(--c); color: #fff; padding: 14px 16px 18px; display: flex; flex-direction: column; background-image: radial-gradient(circle at 100% 0, rgba(255, 255, 255, .18) 0 60px, transparent 61px); }
.cc__top b { font-size: 18px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cc__top small { font-size: 13px; opacity: .9; }
.cc__body { display: flex; gap: 14px; padding: 12px 16px; font-size: 12.5px; color: #5f6368; }
.cc__body span { display: inline-flex; gap: 5px; align-items: center; }
.cc__foot { border-top: 1px solid #e8eaed; padding: 8px 16px; min-height: 38px; display: flex; align-items: center; }
.cc__badge { font-size: 12px; font-weight: 600; color: #7c4ddb; background: #f3e8fd; padding: 2px 10px; border-radius: 99px; }
.cc__ok { font-size: 12px; color: #188038; display: inline-flex; gap: 5px; align-items: center; }

.fu { display: flex; align-items: center; gap: 12px; padding: 9px 4px; border-top: 1px solid #f0f2f5; }
.fu:first-of-type { border-top: 0; }
.fu__av { width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center; background: #e0f7fa; color: #129eaf; font-size: 12px; font-weight: 600; flex: none; }
.fu__t { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.fu__t b { font-size: 13.5px; font-weight: 500; color: #1f1f1f; }
.fu__t small { font-size: 12.5px; color: #5f6368; }
.fu__d { font-size: 12.5px; color: #5f6368; }
.fu__d.late { color: #c5221f; font-weight: 500; }

@media (max-width: 1000px) { .dm { grid-template-columns: repeat(2, minmax(0, 1fr)); } .dg { grid-template-columns: 1fr; } }
</style>
