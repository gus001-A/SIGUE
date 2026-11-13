<script setup>
/**
 * App del alumno · Pendientes (como "Tareas pendientes" de Google Classroom):
 * pestañas Asignadas / No entregadas / Completadas, filtro por clase y grupos por fecha.
 */
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { FileTextOutlined, ProjectOutlined, ExperimentOutlined, CheckCircleOutlined, DownOutlined } from '@ant-design/icons-vue';
import MobileLayout from '@/Layouts/MobileLayout.vue';
import { ripple as vRipple, parseDue, dueLabel } from '@/lib/ripple';

const props = defineProps({ student: Object, children: Array, isGuardian: Boolean, items: { type: Array, default: () => [] }, canSubmit: Boolean });
const ICON = { tarea: FileTextOutlined, proyecto: ProjectOutlined, actividad: ExperimentOutlined };
const TABS = [{ k: 'asignadas', l: 'Asignadas' }, { k: 'faltan', l: 'No entregadas' }, { k: 'hechas', l: 'Completadas' }];
const tab = ref('asignadas');
const cls = ref('');
const classes = computed(() => [...new Set(props.items.map((a) => a.subject).filter(Boolean))].sort());

const done = (a) => !!a.submission?.submitted_at || a.submission?.score != null;
const kind = (a) => (done(a) ? 'hechas' : a.overdue ? 'faltan' : 'asignadas');
const list = computed(() => props.items.filter((a) => kind(a) === tab.value && (!cls.value || a.subject === cls.value)));
const count = (k) => props.items.filter((a) => kind(a) === k && (!cls.value || a.subject === cls.value)).length;

/** Grupos por fecha, como en Google: Sin fecha / Esta semana / Próxima semana / Más adelante. */
const groups = computed(() => {
    if (tab.value !== 'asignadas') return [{ k: 'all', l: '', items: list.value }];
    const now = new Date();
    const endWeek = new Date(now); endWeek.setDate(now.getDate() + (7 - ((now.getDay() + 6) % 7)) - 1); endWeek.setHours(23, 59, 59);
    const endNext = new Date(endWeek); endNext.setDate(endWeek.getDate() + 7);
    const g = { none: [], week: [], next: [], later: [] };
    for (const a of [...list.value].sort((x, y) => (parseDue(x.due_at)?.getTime() ?? Infinity) - (parseDue(y.due_at)?.getTime() ?? Infinity))) {
        const d = parseDue(a.due_at);
        g[!d ? 'none' : d <= endWeek ? 'week' : d <= endNext ? 'next' : 'later'].push(a);
    }
    return [{ k: 'week', l: 'Esta semana', items: g.week }, { k: 'next', l: 'Próxima semana', items: g.next }, { k: 'later', l: 'Más adelante', items: g.later }, { k: 'none', l: 'Sin fecha de entrega', items: g.none }]
        .filter((x) => x.items.length);
});
const sub = (a) => {
    const s = a.submission;
    if (s?.score != null) return { t: `${Number(s.score)}/${a.max_score}`, c: '#146c2e' };
    if (s?.submitted_at) return { t: 'Entregada', c: '#146c2e' };
    return null;
};
</script>

<template>
    <MobileLayout title="Pendientes">
        <div class="tabs">
            <button v-for="t in TABS" :key="t.k" v-ripple type="button" class="tabs__i" :class="{ on: tab === t.k }" @click="tab = t.k">
                {{ t.l }}<i v-if="count(t.k)">{{ count(t.k) }}</i>
            </button>
        </div>
        <div class="flt">
            <label class="chip-sel" :class="{ on: cls }">
                <select v-model="cls"><option value="">Todas las clases</option><option v-for="c in classes" :key="c" :value="c">{{ c }}</option></select>
                <span>{{ cls || 'Todas las clases' }}</span><DownOutlined />
            </label>
        </div>

        <div v-if="!list.length" class="empty">
            <CheckCircleOutlined />
            <b>{{ tab === 'hechas' ? 'Aún no has completado trabajos' : '¡Nada por aquí!' }}</b>
            <span>{{ tab === 'faltan' ? 'No tienes trabajos vencidos sin entregar.' : tab === 'asignadas' ? 'No tienes trabajo asignado pendiente.' : '' }}</span>
        </div>

        <section v-for="g in groups" :key="g.k" class="gr">
            <h3 v-if="g.l">{{ g.l }} <small>{{ g.items.length }}</small></h3>
            <button v-for="a in g.items" :key="a.id" v-ripple type="button" class="li" @click="router.visit(route('app.tarea', a.id))">
                <span class="li__ic" :class="{ late: tab === 'faltan', ok: tab === 'hechas' }"><component :is="ICON[a.type] ?? FileTextOutlined" /></span>
                <span class="li__t">
                    <b>{{ a.title }}</b>
                    <small>{{ a.subject }}</small>
                    <small :class="{ late: tab === 'faltan' }">{{ a.due_at ? dueLabel(a.due_at) : 'Sin fecha de entrega' }}</small>
                </span>
                <span v-if="sub(a)" class="li__s" :style="{ color: sub(a).c }">{{ sub(a).t }}</span>
            </button>
        </section>
    </MobileLayout>
</template>

<style scoped>
.tabs { position: sticky; top: 0; z-index: 2; display: flex; background: var(--m-surface-1); border-bottom: 1px solid #e1e3e1; }
.tabs__i { flex: 1; height: 48px; border: 0; background: none; font-family: inherit; font-size: 14px; font-weight: 500; color: var(--m-on-var); cursor: pointer; position: relative; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
.tabs__i.on { color: var(--m-primary); }
.tabs__i.on::after { content: ''; position: absolute; left: 20%; right: 20%; bottom: 0; height: 3px; border-radius: 3px 3px 0 0; background: var(--m-primary); }
.tabs__i i { font-style: normal; font-size: 11px; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px; background: var(--m-surface-3); display: grid; place-items: center; }
.tabs__i.on i { background: var(--m-primary); color: #fff; }
.flt { padding: 12px 16px 4px; }
.chip-sel { position: relative; display: inline-flex; align-items: center; gap: 8px; height: 32px; padding: 0 12px 0 16px; border: 1px solid var(--m-outline-var); border-radius: 8px; font-size: 14px; font-weight: 500; color: var(--m-on-var); }
.chip-sel.on { background: var(--m-sec-c); border-color: transparent; color: #001d35; }
.chip-sel .anticon { font-size: 10px; }
.chip-sel select { position: absolute; inset: 0; opacity: 0; width: 100%; cursor: pointer; }
.empty { display: flex; flex-direction: column; align-items: center; gap: 8px; text-align: center; padding: 60px 30px; color: var(--m-on-var); }
.empty .anticon { font-size: 40px; color: var(--m-outline-var); }
.empty b { font-size: 18px; font-weight: 400; color: var(--m-on); }
.gr h3 { margin: 18px 16px 4px; font-size: 14px; font-weight: 500; color: var(--m-on); display: flex; justify-content: space-between; }
.gr h3 small { color: var(--m-on-var); font-weight: 400; }
.li { width: 100%; display: flex; align-items: center; gap: 16px; padding: 12px 16px; border: 0; background: none; text-align: left; font-family: inherit; cursor: pointer; color: var(--m-on); }
.li__ic { width: 40px; height: 40px; border-radius: 50%; display: grid; place-items: center; background: #d3e3fd; color: #0b57d0; font-size: 17px; flex: none; }
.li__ic.late { background: #f9dedc; color: var(--m-error); }
.li__ic.ok { background: #c4eed0; color: var(--m-success); }
.li__t { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.li__t b { font-size: 15px; font-weight: 400; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.li__t small { font-size: 12.5px; color: var(--m-on-var); }
.li__t small.late { color: var(--m-error); }
.li__s { font-size: 13px; font-weight: 500; white-space: nowrap; }
</style>
