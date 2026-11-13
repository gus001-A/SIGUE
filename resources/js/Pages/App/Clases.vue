<script setup>
/**
 * App del alumno · Clases (como la app de Google Classroom):
 *  - Inicio: tarjetas de las clases con sus tareas por entregar.
 *  - Una clase: Novedades (tablón), Trabajo de clase (por temas) y Personas, con navegación inferior propia.
 */
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    MessageOutlined, SolutionOutlined, TeamOutlined, FileTextOutlined, ProjectOutlined, ExperimentOutlined, PaperClipOutlined,
    LinkOutlined, PlayCircleFilled, CheckCircleFilled, PushpinFilled, NotificationOutlined, ReadOutlined, DownOutlined, UpOutlined, CalendarOutlined,
} from '@ant-design/icons-vue';
import MobileLayout from '@/Layouts/MobileLayout.vue';
import { ripple as vRipple, classColor, dueLabel, initials } from '@/lib/ripple';

const props = defineProps({
    student: Object, children: Array, isGuardian: Boolean, canSubmit: Boolean,
    classes: { type: Array, default: () => [] }, dueSoon: { type: Array, default: () => [] },
    current: { type: Object, default: null }, posts: { type: Array, default: () => [] }, items: { type: Array, default: () => [] },
    people: { type: Object, default: null },
});
const color = (id) => classColor(props.classes, id);
const h = new Date().getHours();
const hello = h < 12 ? 'Buenos días' : h < 19 ? 'Buenas tardes' : 'Buenas noches';
const dl = new Intl.DateTimeFormat('es-MX', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date());
const dateLabel = dl.charAt(0).toUpperCase() + dl.slice(1);
const totalPending = computed(() => props.classes.reduce((t, c) => t + (c.pending ?? 0), 0));
const openClass = (id) => router.visit(route('app.clases', { clase: id }));
const openTask = (id) => router.visit(route('app.tarea', id));
const ICON = { tarea: FileTextOutlined, proyecto: ProjectOutlined, actividad: ExperimentOutlined };

// ---- Una clase ----
const tab = ref('novedades');
const TABS = [
    { k: 'novedades', l: 'Novedades', icon: MessageOutlined },
    { k: 'trabajo', l: 'Trabajo de clase', icon: SolutionOutlined },
    { k: 'personas', l: 'Personas', icon: TeamOutlined },
];
const st = (a) => {
    const s = a.submission;
    if (s?.score != null) return { t: `Calificada · ${Number(s.score)}/${a.max_score}`, c: '#146c2e' };
    if (s?.submitted_at) return { t: 'Entregada', c: '#146c2e' };
    if (a.overdue) return { t: 'Falta entregar', c: '#b3261e' };
    return { t: a.due_at ? dueLabel(a.due_at) : 'Sin fecha de entrega', c: '#444746' };
};
const upcoming = computed(() => props.items.filter((a) => !a.submission?.submitted_at && a.submission?.score == null && !a.overdue && a.due_at).slice(0, 3));
const groups = computed(() => {
    const map = new Map();
    for (const a of props.items) {
        const k = a.topic_id ?? 0;
        if (!map.has(k)) map.set(k, { id: k, name: a.topic ?? 'Sin tema', pos: a.topic_id ? (a.topic_pos ?? 0) : -1, items: [] });
        map.get(k).items.push(a);
    }
    return [...map.values()].sort((x, y) => x.pos - y.pos);
});
const closed = ref({});
const openPost = ref({});
</script>

<template>
    <!-- ================= Inicio: mis clases ================= -->
    <MobileLayout v-if="!current" title="Clases">
        <div class="hm">
            <section class="hero">
                <span class="hero__date"><CalendarOutlined /> {{ dateLabel }}</span>
                <h1>{{ hello }}, {{ isGuardian ? $page.props.auth.user.first_name : student.first_name }}</h1>
                <div class="hero__chips">
                    <span v-if="isGuardian">Clases de <b>{{ student.first_name }}</b></span>
                    <span><b>{{ classes.length }}</b> clases</span>
                    <span :class="{ warn: totalPending }"><b>{{ totalPending }}</b> por entregar</span>
                </div>
            </section>
            <p v-if="!classes.length" class="empty">Aún no estás inscrito en un grupo con materias.</p>
            <article v-for="c in classes" :key="c.id" class="cc">
                <div v-ripple class="cc__h" :style="{ '--c': color(c.id) }" @click="openClass(c.id)">
                    <b>{{ c.subject }}</b>
                    <span>{{ c.group }}<template v-if="c.level"> · {{ c.level }}</template></span>
                    <small>{{ c.teacher || 'Sin docente' }}</small>
                    <i class="cc__d1" /><i class="cc__d2" />
                </div>
                <div class="cc__tasks">
                    <button v-for="u in c.upcoming" :key="u.id" v-ripple type="button" class="cc__t" :class="{ late: u.overdue }" @click="openTask(u.id)">
                        <span class="cc__ti"><component :is="ICON[u.type] ?? FileTextOutlined" /></span>
                        <span class="cc__tt"><small>{{ u.due }}</small><b>{{ u.title }}</b></span>
                    </button>
                    <button v-if="c.pending > c.upcoming.length" v-ripple type="button" class="cc__more" @click="openClass(c.id)">y {{ c.pending - c.upcoming.length }} más por entregar</button>
                    <p v-if="!c.upcoming.length" class="cc__ok"><CheckCircleFilled /> Sin trabajo pendiente</p>
                </div>
            </article>
        </div>
    </MobileLayout>

    <!-- ================= Una clase ================= -->
    <MobileLayout v-else :title="current.subject" :subtitle="current.group" :back="route('app.clases')" :tint="color(current.id)">
        <!-- Novedades -->
        <div v-if="tab === 'novedades'" class="pad">
            <div class="bn" :style="{ '--c': color(current.id) }">
                <b>{{ current.subject }}</b>
                <span>{{ current.group }}<template v-if="current.level"> · {{ current.level }}</template></span>
                <small>{{ current.teacher }}</small>
                <i class="cc__d1" /><i class="cc__d2" />
            </div>

            <div v-if="upcoming.length" class="up">
                <b>Próximas entregas</b>
                <button v-for="a in upcoming" :key="a.id" v-ripple type="button" class="up__i" @click="openTask(a.id)">
                    <small>{{ dueLabel(a.due_at) }}</small><span>{{ a.title }}</span>
                </button>
            </div>

            <p v-if="!posts.length" class="empty"><NotificationOutlined /> Tu docente aún no ha publicado avisos ni material.</p>
            <article v-for="p in posts" :key="p.id" class="pc">
                <header class="pc__h">
                    <span class="av" :style="{ background: color(current.id) }">{{ p.initials }}</span>
                    <span class="pc__w"><b>{{ p.author }}</b><small>{{ p.at }}</small></span>
                    <PushpinFilled v-if="p.pinned" class="pc__pin" />
                </header>
                <h4 v-if="p.title" class="pc__t"><ReadOutlined v-if="p.type === 'material'" /> {{ p.title }}</h4>
                <p v-if="p.body" class="pc__b" :class="{ clamp: !openPost[p.id] && p.body.length > 220 }" @click="openPost[p.id] = true">{{ p.body }}</p>
                <div v-if="p.files?.length || p.links?.length" class="pc__att">
                    <a v-for="f in p.files" :key="f.url" v-ripple :href="f.url" target="_blank" rel="noopener" class="chip"><PaperClipOutlined /> {{ f.name }}</a>
                    <a v-for="l in p.links" :key="l.url" v-ripple :href="l.url" target="_blank" rel="noopener" class="chip">
                        <PlayCircleFilled v-if="l.youtube" style="color: #c5221f" /><LinkOutlined v-else /> {{ l.title }}
                    </a>
                </div>
                <footer v-if="p.comments?.length" class="pc__f"><TeamOutlined /> {{ p.comments.length }} comentario{{ p.comments.length === 1 ? '' : 's' }} de la clase</footer>
            </article>
        </div>

        <!-- Trabajo de clase -->
        <div v-else-if="tab === 'trabajo'" class="pad0">
            <p v-if="!items.length" class="empty"><SolutionOutlined /> Tu docente aún no ha asignado trabajo.</p>
            <section v-for="g in groups" :key="g.id" class="tp">
                <button v-ripple type="button" class="tp__h" :style="{ color: color(current.id) }" @click="closed[g.id] = !closed[g.id]">
                    <span>{{ g.name }}</span><component :is="closed[g.id] ? DownOutlined : UpOutlined" />
                </button>
                <template v-if="!closed[g.id]">
                    <button v-for="a in g.items" :key="a.id" v-ripple type="button" class="li" @click="openTask(a.id)">
                        <span class="li__ic" :style="{ background: color(current.id) }"><component :is="ICON[a.type] ?? FileTextOutlined" /></span>
                        <span class="li__t"><b>{{ a.title }}</b><small :style="{ color: st(a).c }">{{ st(a).t }}</small></span>
                    </button>
                </template>
            </section>
        </div>

        <!-- Personas -->
        <div v-else class="pad0">
            <section class="pp">
                <h3 :style="{ color: color(current.id), borderColor: color(current.id) }">Profesores</h3>
                <div class="pp__r"><span class="av lg" :style="{ background: color(current.id) }">{{ initials(people?.teacher || 'D') }}</span><b>{{ people?.teacher || 'Sin docente' }}</b></div>
            </section>
            <section class="pp">
                <h3 :style="{ color: color(current.id), borderColor: color(current.id) }">Compañeros de clase <small>{{ people?.classmates?.length ?? 0 }} alumnos</small></h3>
                <div v-for="s in people?.classmates ?? []" :key="s.id" class="pp__r">
                    <span class="av lg"><img v-if="s.photo_url" :src="s.photo_url" alt="" /><template v-else>{{ initials(s.name) }}</template></span>
                    <b>{{ s.name }}</b>
                </div>
            </section>
        </div>

        <template #bottom>
            <nav class="cnav">
                <button v-for="t in TABS" :key="t.k" type="button" class="cnav__i" :class="{ on: tab === t.k }" @click="tab = t.k">
                    <span v-ripple class="cnav__pill"><component :is="t.icon" /></span>
                    <span class="cnav__l">{{ t.l }}</span>
                </button>
            </nav>
        </template>
    </MobileLayout>
</template>

<style scoped>
.hero { position: relative; overflow: hidden; border-radius: 28px; padding: 18px 18px 16px; color: #fff; background: linear-gradient(135deg, #0b57d0, #1a73e8 55%, #12a4a0); }
.hero::after { content: ''; position: absolute; width: 180px; height: 180px; border-radius: 50%; right: -60px; top: -80px; background: rgba(255, 255, 255, .12); }
.hero__date { display: inline-flex; gap: 6px; align-items: center; font-size: 12.5px; padding: 3px 10px; border-radius: 99px; background: rgba(255, 255, 255, .18); }
.hero h1 { margin: 10px 0 12px; font-size: 26px; font-weight: 400; color: #fff; line-height: 1.2; }
.hero__chips { display: flex; flex-wrap: wrap; gap: 6px; position: relative; z-index: 1; }
.hero__chips span { background: #fff; color: #0b57d0; border-radius: 99px; padding: 4px 11px; font-size: 12.5px; }
.hero__chips span.warn { color: #b06000; }
.hero__chips b { font-weight: 700; }
.hm { padding: 8px 16px 24px; display: flex; flex-direction: column; gap: 16px; }
.pad { padding: 12px 16px 24px; display: flex; flex-direction: column; gap: 14px; }
.pad0 { padding: 4px 0 24px; }
.empty { margin: 30px 16px; text-align: center; color: var(--m-on-var); font-size: 14px; display: flex; flex-direction: column; align-items: center; gap: 8px; }
.empty .anticon { font-size: 28px; color: var(--m-outline-var); }

.cc { background: var(--m-surface); border-radius: 16px; overflow: hidden; border: 1px solid #e1e3e1; }
.cc__h, .bn { position: relative; overflow: hidden; background: var(--c); color: #fff; padding: 16px 16px 14px; display: flex; flex-direction: column; min-height: 112px; cursor: pointer; }
.cc__h b, .bn b { font-size: 22px; font-weight: 400; line-height: 1.25; margin-right: 30px; }
.cc__h span, .bn span { font-size: 14px; opacity: .95; }
.cc__h small, .bn small { margin-top: auto; padding-top: 10px; font-size: 13px; opacity: .9; }
.cc__d1, .cc__d2 { position: absolute; border-radius: 50%; background: rgba(255, 255, 255, .13); pointer-events: none; }
.cc__d1 { width: 160px; height: 160px; right: -50px; top: -70px; }
.cc__d2 { width: 90px; height: 90px; right: 60px; bottom: -55px; }
.cc__tasks { display: flex; flex-direction: column; padding: 4px 0; }
.cc__t { display: flex; align-items: center; gap: 14px; padding: 10px 16px; border: 0; background: none; text-align: left; font-family: inherit; cursor: pointer; color: var(--m-on); }
.cc__ti { width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; background: var(--m-surface-2); color: var(--m-on-var); flex: none; }
.cc__tt { min-width: 0; display: flex; flex-direction: column; }
.cc__tt small { font-size: 12px; color: var(--m-on-var); }
.cc__tt b { font-size: 14px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cc__t.late small { color: var(--m-error); font-weight: 500; }
.cc__t.late .cc__ti { background: #fce8e6; color: var(--m-error); }
.cc__more { border: 0; background: none; text-align: left; padding: 8px 16px 10px 66px; font-family: inherit; font-size: 13px; color: var(--m-primary); font-weight: 500; cursor: pointer; }
.cc__ok { margin: 0; padding: 12px 16px; font-size: 13px; color: var(--m-success); display: flex; gap: 8px; align-items: center; }

.bn { border-radius: 16px; cursor: default; min-height: 128px; }
.up { background: var(--m-surface); border: 1px solid #e1e3e1; border-radius: 16px; padding: 14px 16px 8px; display: flex; flex-direction: column; }
.up > b { font-size: 15px; font-weight: 500; margin-bottom: 4px; }
.up__i { display: flex; flex-direction: column; align-items: flex-start; border: 0; background: none; padding: 8px 6px; margin: 0 -6px; border-radius: 10px; font-family: inherit; cursor: pointer; text-align: left; }
.up__i small { font-size: 12px; color: var(--m-on-var); }
.up__i span { font-size: 14px; color: var(--m-on); }

.pc { background: var(--m-surface); border: 1px solid #e1e3e1; border-radius: 16px; padding: 14px 16px; display: flex; flex-direction: column; gap: 10px; }
.pc__h { display: flex; align-items: center; gap: 12px; }
.av { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; display: grid; place-items: center; color: #fff; font-size: 14px; font-weight: 500; flex: none; background: #7c4ddb; }
.av img { width: 100%; height: 100%; object-fit: cover; }
.av.lg { width: 40px; height: 40px; }
.pc__w { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.pc__w b { font-size: 14px; font-weight: 500; }
.pc__w small { font-size: 12px; color: var(--m-on-var); }
.pc__pin { color: var(--m-on-var); }
.pc__t { margin: 0; font-size: 16px; font-weight: 500; display: flex; gap: 8px; align-items: center; }
.pc__b { margin: 0; font-size: 14px; line-height: 1.55; color: var(--m-on); white-space: pre-wrap; }
.pc__b.clamp { display: -webkit-box; -webkit-line-clamp: 5; -webkit-box-orient: vertical; overflow: hidden; }
.pc__att { display: flex; flex-wrap: wrap; gap: 8px; }
.chip { display: inline-flex; gap: 8px; align-items: center; max-width: 100%; height: 32px; padding: 0 12px; border: 1px solid var(--m-outline-var); border-radius: 8px; font-size: 13px;
    font-weight: 500; color: var(--m-on-var); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pc__f { border-top: 1px solid #e1e3e1; margin: 0 -16px; padding: 10px 16px 0; font-size: 13px; color: var(--m-on-var); display: flex; gap: 8px; align-items: center; }

.tp { border-bottom: 1px solid #e1e3e1; }
.tp__h { width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 18px 16px 10px; border: 0; background: none; font-family: inherit; font-size: 20px; font-weight: 400; cursor: pointer; text-align: left; }
.tp__h .anticon { font-size: 14px; }
.li { width: 100%; display: flex; align-items: center; gap: 16px; padding: 10px 16px; border: 0; background: none; text-align: left; font-family: inherit; cursor: pointer; color: var(--m-on); }
.li__ic { width: 40px; height: 40px; border-radius: 50%; display: grid; place-items: center; color: #fff; font-size: 17px; flex: none; }
.li__t { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.li__t b { font-size: 15px; font-weight: 400; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.li__t small { font-size: 12.5px; }

.pp { padding: 6px 0 10px; }
.pp h3 { margin: 10px 16px 8px; padding-bottom: 10px; border-bottom: 1px solid; font-size: 22px; font-weight: 400; display: flex; align-items: baseline; justify-content: space-between; }
.pp h3 small { font-size: 13px; color: var(--m-on-var); }
.pp__r { display: flex; align-items: center; gap: 16px; padding: 8px 16px; }
.pp__r b { font-size: 15px; font-weight: 400; }

.cnav { flex: none; display: flex; height: 80px; padding-bottom: env(safe-area-inset-bottom); background: var(--m-surface-2); }
.cnav__i { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; border: 0; background: none; font-family: inherit; color: var(--m-on-var); cursor: pointer; -webkit-tap-highlight-color: transparent; }
.cnav__pill { width: 64px; height: 32px; border-radius: 16px; display: grid; place-items: center; font-size: 20px; transition: background .2s; }
.cnav__i.on .cnav__pill { background: var(--m-sec-c); color: #001d35; }
.cnav__l { font-size: 12px; font-weight: 500; }
.cnav__i.on .cnav__l { color: var(--m-on); font-weight: 700; }
</style>
