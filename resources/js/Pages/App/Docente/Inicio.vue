<script setup>
/**
 * App del docente · Hoy: saludo, clases del día con pase de lista (solo desde la hora de inicio)
 * y trabajos por revisar.
 */
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import {
    ClockCircleOutlined, CheckCircleFilled, EnvironmentOutlined, FileTextOutlined, ProjectOutlined, ExperimentOutlined, InboxOutlined, CalendarOutlined, RightOutlined,
} from '@ant-design/icons-vue';
import MobileLayout from '@/Layouts/MobileLayout.vue';
import { ripple as vRipple } from '@/lib/ripple';

const props = defineProps({
    teacher: String, classes: Array, today: Array, toGrade: Array, pendingSubmissions: Number, followUps: Array, hasTeacher: Boolean,
    toReview: { type: Array, default: () => [] },
});
const page = usePage();
const h = new Date().getHours();
const hello = h < 12 ? 'Buenos días' : h < 19 ? 'Buenas tardes' : 'Buenas noches';
const dl = new Intl.DateTimeFormat('es-MX', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date());
const dateLabel = dl.charAt(0).toUpperCase() + dl.slice(1);
const pendingList = computed(() => props.today.filter((t) => !t.taken && t.state !== 'upcoming').length);
const ICON = { tarea: FileTextOutlined, proyecto: ProjectOutlined, actividad: ExperimentOutlined };
const lista = (t) => router.visit(route('app.docente.lista', { class: t.class_id, session: t.session }));
</script>

<template>
    <MobileLayout title="Hoy">
        <div class="pg">
            <section class="hero">
                <span class="hero__date"><CalendarOutlined /> {{ dateLabel }}</span>
                <h1>{{ hello }}, {{ page.props.auth.user.first_name }}</h1>
                <div class="hero__chips">
                    <span><b>{{ today.length }}</b> clase(s)</span>
                    <span :class="{ warn: pendingList }"><b>{{ pendingList }}</b> lista(s) por pasar</span>
                    <span><b>{{ pendingSubmissions }}</b> por revisar</span>
                </div>
            </section>

            <h2 class="sec">Clases de hoy</h2>
            <p v-if="!today.length" class="empty"><CalendarOutlined /> No tienes clases hoy.</p>
            <article v-for="t in today" :key="t.class_id + '-' + t.session" class="cl" :class="t.state">
                <div class="cl__time"><b>{{ t.starts_at }}</b><small>{{ t.ends_at }}</small></div>
                <div class="cl__body">
                    <b>{{ t.subject }}</b>
                    <small>{{ t.group }}<template v-if="t.room"> · <EnvironmentOutlined /> {{ t.room }}</template></small>
                    <div class="cl__act">
                        <span v-if="t.state === 'now'" class="tag now">En curso</span>
                        <button v-if="t.taken" v-ripple type="button" class="btn tonal ok" @click="lista(t)"><CheckCircleFilled /> Lista tomada</button>
                        <span v-else-if="t.state === 'upcoming'" class="btn wait"><ClockCircleOutlined /> Lista a las {{ t.starts_at }}</span>
                        <button v-else v-ripple type="button" class="btn fill" :class="{ late: t.state === 'past' }" @click="lista(t)">Pasar lista</button>
                    </div>
                </div>
            </article>

            <h2 class="sec">Por revisar <small v-if="pendingSubmissions">{{ pendingSubmissions }}</small></h2>
            <p v-if="!toReview.length" class="empty"><InboxOutlined /> Todo revisado. ¡Buen trabajo!</p>
            <div v-else class="card">
                <button v-for="r in toReview" :key="r.id" v-ripple type="button" class="li" @click="router.visit(route('app.docente.actividad', r.id))">
                    <span class="li__ic"><component :is="ICON[r.type] ?? FileTextOutlined" /></span>
                    <span class="li__t"><b>{{ r.title }}</b><small>{{ r.subject }} · {{ r.group }}</small></span>
                    <span class="li__n">{{ r.count }}</span><RightOutlined class="li__go" />
                </button>
            </div>
        </div>
    </MobileLayout>
</template>

<style scoped>
.pg { padding: 6px 16px 28px; display: flex; flex-direction: column; gap: 12px; }
.hero { position: relative; overflow: hidden; border-radius: 28px; padding: 18px 18px 16px; color: #fff; background: linear-gradient(135deg, #0b57d0, #1a73e8 55%, #12a4a0); }
.hero::after { content: ''; position: absolute; width: 180px; height: 180px; border-radius: 50%; right: -60px; top: -80px; background: rgba(255, 255, 255, .12); }
.hero__date { display: inline-flex; gap: 6px; align-items: center; font-size: 12.5px; padding: 3px 10px; border-radius: 99px; background: rgba(255, 255, 255, .18); }
.hero h1 { margin: 10px 0 12px; font-size: 26px; font-weight: 400; color: #fff; line-height: 1.2; }
.hero__chips { display: flex; flex-wrap: wrap; gap: 6px; position: relative; z-index: 1; }
.hero__chips span { background: #fff; color: #0b57d0; border-radius: 99px; padding: 4px 11px; font-size: 12.5px; }
.hero__chips span.warn { color: #b06000; }
.hero__chips b { font-weight: 700; }
.sec { margin: 10px 4px 0; font-size: 16px; font-weight: 500; color: var(--m-on); display: flex; align-items: center; gap: 8px; }
.sec small { font-size: 12px; background: #7c4ddb; color: #fff; padding: 1px 8px; border-radius: 99px; }
.empty { margin: 0; padding: 22px; text-align: center; color: var(--m-on-var); background: var(--m-surface); border-radius: 20px; font-size: 14px; display: flex; gap: 8px; justify-content: center; align-items: center; }

.cl { display: flex; gap: 14px; padding: 14px 16px; border-radius: 20px; background: var(--m-surface); border: 1px solid #e1e3e1; }
.cl.now { border-color: #a8c7fa; background: #f3f7fe; box-shadow: inset 4px 0 0 #0b57d0; }
.cl.upcoming { opacity: .9; }
.cl__time { width: 46px; flex: none; display: flex; flex-direction: column; line-height: 1.25; padding-top: 2px; }
.cl__time b { font-size: 15px; font-weight: 500; }
.cl__time small { font-size: 12px; color: var(--m-on-var); }
.cl__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.cl__body > b { font-size: 16px; font-weight: 500; }
.cl__body > small { font-size: 13px; color: var(--m-on-var); }
.cl__act { display: flex; align-items: center; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
.tag.now { font-size: 11.5px; font-weight: 500; color: #0b57d0; background: #d3e3fd; padding: 3px 9px; border-radius: 8px; }
.btn { height: 36px; padding: 0 16px; border-radius: 18px; border: 0; font-family: inherit; font-size: 13.5px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
.btn.fill { background: #0b57d0; color: #fff; }
.btn.fill.late { background: #e37400; }
.btn.tonal.ok { background: #c4eed0; color: #0d652d; }
.btn.wait { background: var(--m-surface-2); color: var(--m-on-var); cursor: default; }

.card { background: var(--m-surface); border-radius: 20px; overflow: hidden; border: 1px solid #e1e3e1; }
.li { width: 100%; display: flex; align-items: center; gap: 14px; padding: 12px 16px; border: 0; border-top: 1px solid #f0f2f5; background: none; text-align: left; font-family: inherit; cursor: pointer; color: var(--m-on); }
.li:first-child { border-top: 0; }
.li__ic { width: 40px; height: 40px; border-radius: 50%; display: grid; place-items: center; background: #eaddff; color: #6750a4; flex: none; }
.li__t { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.li__t b { font-size: 14.5px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.li__t small { font-size: 12.5px; color: var(--m-on-var); }
.li__n { min-width: 26px; height: 26px; padding: 0 8px; border-radius: 13px; background: #7c4ddb; color: #fff; font-size: 12.5px; font-weight: 500; display: grid; place-items: center; }
.li__go { color: var(--m-on-var); font-size: 11px; }
</style>
