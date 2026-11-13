<script setup>
/**
 * App del docente · Pase de lista: día de la semana, sesión, y cada alumno con Presente / Retardo / Falta / Justificada.
 * Solo se puede capturar desde la hora de inicio de la clase y hasta el plazo permitido (lo valida también el servidor).
 */
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ClockCircleOutlined, LockOutlined, CheckOutlined, CheckCircleFilled } from '@ant-design/icons-vue';
import MobileLayout from '@/Layouts/MobileLayout.vue';
import { ripple as vRipple, initials } from '@/lib/ripple';

const props = defineProps({ course: Object, students: { type: Array, default: () => [] }, attendance: Object, options: Object, section: String, periods: Array, periodId: [Number, null] });
const A = computed(() => props.attendance);
const ST = [
    { k: 'presente', l: 'P', name: 'Presente', c: '#146c2e', bg: '#c4eed0' },
    { k: 'retardo', l: 'R', name: 'Retardo', c: '#8a4a00', bg: '#ffdcbe' },
    { k: 'falta', l: 'F', name: 'Falta', c: '#8c1d18', bg: '#f9dedc' },
    { k: 'justificada', l: 'J', name: 'Justificada', c: '#0b57d0', bg: '#d3e3fd' },
];
const rows = ref(A.value.rows.map((r) => ({ ...r })));
const rowOf = (id) => rows.value.find((r) => r.student_id === id);
const blocked = computed(() => A.value.locked || A.value.not_started);
const set = (id, k) => { if (!blocked.value) rowOf(id).status = k; };
const allPresent = () => { if (!blocked.value) rows.value.forEach((r) => (r.status = 'presente')); };
const counts = computed(() => Object.fromEntries(ST.map((s) => [s.k, rows.value.filter((r) => r.status === s.k).length])));
const saving = ref(false);
function save() {
    if (blocked.value) return;
    saving.value = true;
    router.post(route('docente.asistencia.store', props.course.id), { date: A.value.date, session: A.value.session, rows: rows.value },
        { preserveScroll: true, onFinish: () => (saving.value = false) });
}
const go = (q) => router.visit(route('app.docente.lista', { class: props.course.id, ...q }));
</script>

<template>
    <MobileLayout title="Pase de lista" :subtitle="`${course.subject} · ${course.group}`" :back="route('app.docente.clase', course.id)">
        <div class="days">
            <button v-for="d in A.recent" :key="d.date" v-ripple type="button" class="day" :class="{ on: d.date === A.date, today: d.today }" :disabled="d.future"
                @click="go({ date: d.date })">
                <small>{{ d.label.split(' ')[0].slice(0, 3) }}</small><b>{{ d.label.split(' ')[1] }}</b>
                <i v-if="d.taken" class="day__ok" />
            </button>
        </div>
        <div v-if="A.sessions > 1" class="ses">
            <button v-for="n in A.sessions" :key="n" v-ripple type="button" :class="{ on: n === A.session }" @click="go({ date: A.date, session: n })">Sesión {{ n }}</button>
        </div>

        <div class="head">
            <b>{{ A.date_label }}</b>
            <small v-if="A.starts_at">Inicia a las {{ A.starts_at }}</small>
        </div>

        <div v-if="A.not_started" class="banner wait"><ClockCircleOutlined /> La clase empieza a las {{ A.starts_at }}. Podrás pasar lista a partir de esa hora.</div>
        <div v-else-if="A.locked" class="banner lock"><LockOutlined /> El plazo para modificar esta lista terminó ({{ A.edit_days }} días después de la clase).</div>
        <div v-else-if="A.taken" class="banner ok"><CheckCircleFilled /> Lista tomada<template v-if="A.recorded_at"> · {{ A.recorded_at }}</template>. Puedes corregirla.</div>

        <div class="sum">
            <span v-for="s in ST" :key="s.k" :style="{ color: s.c, background: s.bg }"><b>{{ counts[s.k] }}</b> {{ s.name }}</span>
            <button v-if="!blocked" v-ripple type="button" class="sum__all" @click="allPresent"><CheckOutlined /> Todos presentes</button>
        </div>

        <div class="list" :class="{ dis: blocked }">
            <div v-for="s in students" :key="s.id" class="row">
                <span class="av"><img v-if="s.photo_url" :src="s.photo_url" alt="" /><template v-else>{{ initials(s.list_name) }}</template></span>
                <span class="row__n">{{ s.list_name }}</span>
                <div class="seg">
                    <button v-for="o in ST" :key="o.k" type="button" :title="o.name" :disabled="blocked"
                        :style="rowOf(s.id)?.status === o.k ? { background: o.bg, color: o.c, borderColor: o.c } : null" @click="set(s.id, o.k)">{{ o.l }}</button>
                </div>
            </div>
        </div>

        <template #bottom>
            <div class="bar">
                <button v-ripple type="button" class="save" :disabled="blocked || saving" @click="save">
                    {{ saving ? 'Guardando…' : blocked ? (A.not_started ? `Disponible a las ${A.starts_at}` : 'Lista cerrada') : A.taken ? 'Guardar cambios' : 'Guardar lista' }}
                </button>
            </div>
        </template>
    </MobileLayout>
</template>

<style scoped>
.days { display: flex; gap: 8px; padding: 6px 16px; overflow-x: auto; scrollbar-width: none; }
.day { position: relative; flex: 1; min-width: 52px; height: 58px; border-radius: 16px; border: 1px solid var(--m-outline-var); background: var(--m-surface); display: flex; flex-direction: column;
    align-items: center; justify-content: center; font-family: inherit; color: var(--m-on-var); cursor: pointer; }
.day small { font-size: 11.5px; text-transform: capitalize; }
.day b { font-size: 17px; font-weight: 500; color: var(--m-on); }
.day.today { border-color: #0b57d0; }
.day.on { background: #0b57d0; border-color: #0b57d0; color: #fff; }
.day.on b { color: #fff; }
.day:disabled { opacity: .4; cursor: default; }
.day__ok { position: absolute; top: 6px; right: 6px; width: 7px; height: 7px; border-radius: 50%; background: #1e8e3e; }
.ses { display: flex; gap: 8px; padding: 4px 16px; }
.ses button { height: 32px; padding: 0 14px; border-radius: 8px; border: 1px solid var(--m-outline-var); background: none; font-family: inherit; font-size: 13px; font-weight: 500; color: var(--m-on-var); cursor: pointer; }
.ses button.on { background: var(--m-sec-c); border-color: transparent; color: #001d35; }
.head { padding: 10px 18px 4px; display: flex; flex-direction: column; }
.head b { font-size: 18px; font-weight: 400; }
.head small { font-size: 12.5px; color: var(--m-on-var); }
.banner { margin: 8px 16px; padding: 12px 14px; border-radius: 16px; font-size: 13.5px; display: flex; gap: 10px; align-items: flex-start; line-height: 1.4; }
.banner.wait { background: #fef7e0; color: #7a4f00; }
.banner.lock { background: #f1f3f4; color: #444746; }
.banner.ok { background: #e6f4ea; color: #0d652d; }
.sum { display: flex; flex-wrap: wrap; gap: 6px; padding: 8px 16px; align-items: center; }
.sum span { font-size: 12px; padding: 4px 9px; border-radius: 8px; }
.sum b { font-weight: 700; }
.sum__all { margin-left: auto; height: 32px; padding: 0 12px; border-radius: 16px; border: 1px solid var(--m-outline); background: none; color: #0b57d0; font-family: inherit; font-size: 13px; font-weight: 500; display: inline-flex; gap: 6px; align-items: center; cursor: pointer; }
.list { padding: 4px 0 20px; }
.list.dis { opacity: .55; }
.row { display: flex; align-items: center; gap: 12px; padding: 8px 16px; }
.av { width: 38px; height: 38px; border-radius: 50%; overflow: hidden; display: grid; place-items: center; background: #7c4ddb; color: #fff; font-size: 12.5px; font-weight: 500; flex: none; }
.av img { width: 100%; height: 100%; object-fit: cover; }
.row__n { flex: 1; min-width: 0; font-size: 14px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.seg { display: flex; flex: none; }
.seg button { width: 36px; height: 36px; border: 1px solid var(--m-outline-var); background: var(--m-surface); font-family: inherit; font-size: 13px; font-weight: 700; color: var(--m-on-var); cursor: pointer; margin-left: -1px; }
.seg button:first-child { border-radius: 18px 0 0 18px; margin-left: 0; }
.seg button:last-child { border-radius: 0 18px 18px 0; }
.seg button:disabled { cursor: default; }
.bar { flex: none; padding: 12px 16px calc(12px + env(safe-area-inset-bottom)); background: var(--m-surface-2); }
.save { width: 100%; height: 52px; border-radius: 26px; border: 0; background: #0b57d0; color: #fff; font-family: inherit; font-size: 15px; font-weight: 500; cursor: pointer; }
.save:disabled { background: #e3e3e3; color: #8e918f; cursor: default; }
</style>
