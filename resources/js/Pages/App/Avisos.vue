<script setup>
/** App del alumno · Avisos: notificaciones agrupadas por fecha; al tocar se marca como leída y abre lo relacionado. */
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { BellOutlined, CheckOutlined } from '@ant-design/icons-vue';
import MobileLayout from '@/Layouts/MobileLayout.vue';
import { ripple as vRipple } from '@/lib/ripple';

const props = defineProps({ student: Object, children: Array, isGuardian: Boolean, items: { type: Array, default: () => [] } });
// Sirve para la app del alumno y la del docente (la navegación la decide el layout).
const list = ref(props.items.map((n) => ({ ...n })));
const groups = computed(() => {
    const m = new Map();
    for (const n of list.value) { const k = n.group || 'Anteriores'; if (!m.has(k)) m.set(k, []); m.get(k).push(n); }
    return [...m.entries()];
});
const unread = computed(() => list.value.filter((n) => !n.read).length);

/** Las ligas del portal web se abren en su pantalla equivalente de la app. */
function appUrl(url) {
    if (!url) return null;
    try {
        const u = new URL(url, location.origin);
        if (u.origin !== location.origin) return url;
        let m;
        if ((m = u.pathname.match(/^\/portal\/tareas\/(\d+)/))) return route('app.tarea', m[1]);
        if (u.pathname.startsWith('/portal/tareas')) return route('app.pendientes');
        if (u.pathname.startsWith('/portal/clases')) return route('app.clases') + u.search;
        if (u.pathname.startsWith('/portal/horario')) return route('app.horario');
        if ((m = u.pathname.match(/^\/docente\/actividades\/(\d+)/))) return route('app.docente.actividad', m[1]);
        if ((m = u.pathname.match(/^\/docente\/clases\/(\d+)/))) return route('app.docente.clase', m[1]);
        if (u.pathname === '/docente' || u.pathname === '/docente/') return route('app.docente.inicio');
        return u.pathname + u.search;
    } catch { return url; }
}
async function open(n) {
    if (!n.read) { n.read = true; axios.post(route('notificaciones.leer', n.id), {}, { headers: { Accept: 'application/json' } }).catch(() => {}); }
    const to = appUrl(n.url);
    if (to) router.visit(to);
}
const readAll = () => router.post(route('notificaciones.leer-todas'), {}, { preserveScroll: true, onSuccess: () => list.value.forEach((n) => (n.read = true)) });
</script>

<template>
    <MobileLayout title="Avisos">
        <template #actions>
            <button v-if="unread" v-ripple type="button" class="ib" title="Marcar todo como leído" @click="readAll"><CheckOutlined /></button>
        </template>
        <div v-if="!list.length" class="empty"><BellOutlined /><b>No tienes avisos</b><span>Aquí verás tareas nuevas, calificaciones y comentarios.</span></div>
        <section v-for="[g, items] in groups" :key="g">
            <h3>{{ g }}</h3>
            <button v-for="n in items" :key="n.id" v-ripple type="button" class="nt" :class="{ unread: !n.read }" @click="open(n)">
                <span class="nt__ic" :style="{ '--c': n.color || '#0b57d0' }"><BellOutlined /></span>
                <span class="nt__t"><b>{{ n.title }}</b><small v-if="n.message">{{ n.message }}</small><em>{{ n.date }}</em></span>
                <i v-if="!n.read" class="nt__dot" />
            </button>
        </section>
    </MobileLayout>
</template>

<style scoped>
.ib { width: 48px; height: 48px; border-radius: 50%; border: 0; background: none; font-size: 19px; color: var(--m-on-var); cursor: pointer; }
.empty { display: flex; flex-direction: column; align-items: center; gap: 8px; text-align: center; padding: 70px 30px; color: var(--m-on-var); }
.empty .anticon { font-size: 40px; color: var(--m-outline-var); }
.empty b { font-size: 18px; font-weight: 400; color: var(--m-on); }
h3 { margin: 16px 16px 4px; font-size: 14px; font-weight: 500; color: var(--m-on-var); }
.nt { width: 100%; display: flex; align-items: flex-start; gap: 14px; padding: 12px 16px; border: 0; background: none; text-align: left; font-family: inherit; cursor: pointer; color: var(--m-on); }
.nt.unread { background: #e8f0fe; }
.nt__ic { width: 40px; height: 40px; border-radius: 50%; display: grid; place-items: center; flex: none; color: var(--c); background: color-mix(in srgb, var(--c) 14%, #fff); }
.nt__t { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.nt__t b { font-size: 14.5px; font-weight: 400; }
.nt.unread .nt__t b { font-weight: 500; }
.nt__t small { font-size: 13px; color: var(--m-on-var); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.nt__t em { font-style: normal; font-size: 12px; color: var(--m-on-var); margin-top: 2px; }
.nt__dot { width: 10px; height: 10px; border-radius: 50%; background: var(--m-primary); margin-top: 6px; flex: none; }
</style>
