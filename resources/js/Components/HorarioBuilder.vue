<script setup>
/**
 * Armador visual del horario de un grupo.
 * 1) Se definen las franjas: hora de inicio, duración de cada clase y número de horas (el receso
 *    del turno, configurado por plantel, se inserta solo).
 * 2) Se elige una asignatura de la paleta y se hace clic en las celdas (día × hora) para asignarla;
 *    clic con la misma asignatura (o con "Borrador") deja la celda libre.
 * 3) Se guarda todo de una vez; el servidor valida empalmes, receso y choques del docente.
 */
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { ClockCircleOutlined, ClearOutlined, SaveOutlined, ThunderboltOutlined, UserOutlined, CoffeeOutlined, EnvironmentOutlined, CheckCircleFilled } from '@ant-design/icons-vue';
import SgModal from '@/Components/SgModal.vue';

const props = defineProps({
    open: Boolean,
    group: { type: Object, required: true },      // { id, label, shift, break: {start, end} | null }
    classes: { type: Array, required: true },     // [{ id, subject, teacher, hours_per_week, schedules: [{day_of_week, starts_at, ends_at, room}] }]
    colorOf: { type: Function, required: true },
});
const emit = defineEmits(['update:open']);

const DAYS = [{ v: 1, l: 'Lunes' }, { v: 2, l: 'Martes' }, { v: 3, l: 'Miércoles' }, { v: 4, l: 'Jueves' }, { v: 5, l: 'Viernes' }, { v: 6, l: 'Sábado' }];
const START = { matutino: '07:00', vespertino: '13:00', nocturno: '18:00', mixto: '08:00' };
const toMin = (t) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
const toTime = (m) => `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;

const cfg = ref({ start: START[props.group.shift] ?? '07:00', duration: 50, slots: 7, saturday: false });
const cells = ref({});              // `${day}|${start}` -> { classId, room }
const brush = ref(null);            // id de la clase seleccionada o 'erase'
const saving = ref(false);

/**
 * Franjas: si el grupo ya tiene horario se usan sus mismas horas (modo "actual");
 * al cambiar inicio, duración u horas se generan de nuevo con el receso intercalado.
 */
const mode = ref('generated');
const existingRows = ref([]);
let loading = false;
watch(cfg, () => { if (!loading) mode.value = 'generated'; }, { deep: true });
const rows = computed(() => (mode.value === 'existing' ? existingRows.value : generatedRows.value));
const generatedRows = computed(() => {
    const out = [];
    let t = toMin(cfg.value.start);
    const br = props.group.break ? [toMin(props.group.break.start), toMin(props.group.break.end)] : null;
    let made = 0;
    let guard = 0;
    while (made < cfg.value.slots && guard++ < 40) {
        if (br && t < br[1] && t + cfg.value.duration > br[0]) {
            out.push({ break: true, start: toTime(br[0]), end: toTime(br[1]) });
            t = Math.max(t, br[1]);
            continue;
        }
        out.push({ start: toTime(t), end: toTime(t + cfg.value.duration) });
        t += cfg.value.duration;
        made++;
    }
    if (br && !out.some((r) => r.break) && t >= br[0] && t <= br[1] + 1) out.push({ break: true, start: toTime(br[0]), end: toTime(br[1]) });
    return out;
});
const days = computed(() => DAYS.filter((d) => d.v <= 5 || cfg.value.saturday));

/** Carga el horario actual del grupo y ajusta las franjas a él. */
function load() {
    loading = true;
    const all = props.classes.flatMap((c) => c.schedules.map((s) => ({ ...s, classId: c.id })));
    cells.value = {};
    if (all.length) {
        const ranges = [...new Map(all.map((s) => [s.starts_at, { start: s.starts_at, end: s.ends_at }])).values()];
        if (props.group.break) ranges.push({ break: true, start: props.group.break.start, end: props.group.break.end });
        existingRows.value = ranges.sort((a, b) => a.start.localeCompare(b.start) || (a.break ? -1 : 1));
        mode.value = 'existing';
        const starts = [...new Set(all.map((s) => s.starts_at))].sort();
        const first = all.find((s) => s.starts_at === starts[0]);
        cfg.value.start = starts[0];
        cfg.value.duration = Math.max(20, toMin(first.ends_at) - toMin(first.starts_at));
        cfg.value.slots = Math.max(starts.length, 1);
        cfg.value.saturday = all.some((s) => s.day_of_week === 6);
        all.forEach((s) => { cells.value[`${s.day_of_week}|${s.starts_at}`] = { classId: s.classId, room: s.room }; });
    } else {
        cfg.value = { start: START[props.group.shift] ?? '07:00', duration: 50, slots: 7, saturday: false };
        mode.value = 'generated';
    }
    brush.value = props.classes[0]?.id ?? null;
    setTimeout(() => (loading = false));
}
watch(() => props.open, (v) => v && load(), { immediate: true });

const classById = (id) => props.classes.find((c) => c.id === id);
function paint(day, row) {
    if (row.break) return;
    const k = `${day}|${row.start}`;
    const cur = cells.value[k];
    if (brush.value === 'erase' || (cur && cur.classId === brush.value)) { delete cells.value[k]; return; }
    if (brush.value) cells.value[k] = { classId: brush.value, room: cur?.room ?? null };
}
/** Horas asignadas por asignatura (para comparar con las horas por semana del plan). */
const assigned = computed(() => {
    const m = {};
    Object.entries(cells.value).forEach(([k, v]) => {
        const start = k.split('|')[1];
        const r = rows.value.find((x) => x.start === start && !x.break);
        if (r) m[v.classId] = (m[v.classId] ?? 0) + (toMin(r.end) - toMin(r.start)) / 60;
    });
    return m;
});
const clearAll = () => (cells.value = {});
/** Relleno rápido: reparte las horas por semana de cada asignatura en las celdas libres. */
function autofill() {
    const free = [];
    rows.value.filter((r) => !r.break).forEach((r) => days.value.forEach((d) => { if (!cells.value[`${d.v}|${r.start}`]) free.push({ d: d.v, r }); }));
    free.sort((a, b) => a.d - b.d || a.r.start.localeCompare(b.r.start));
    const queue = [];
    props.classes.forEach((c) => {
        const need = Math.max(0, Math.round((c.hours_per_week ?? 0) - (assigned.value[c.id] ?? 0)));
        for (let i = 0; i < need; i++) queue.push(c.id);
    });
    // Intercala materias para no repetir la misma seguida
    let di = 0;
    queue.forEach((id) => {
        const spot = free.splice(di % Math.max(1, free.length), 1)[0];
        if (spot) cells.value[`${spot.d}|${spot.r.start}`] = { classId: id, room: null };
        di += 3;
    });
}

function save() {
    const entries = Object.entries(cells.value).map(([k, v]) => {
        const [day, start] = k.split('|');
        const r = rows.value.find((x) => x.start === start && !x.break);
        return r && days.value.some((d) => d.v === Number(day)) ? { class_id: v.classId, day: Number(day), starts_at: r.start, ends_at: r.end, room: v.room } : null;
    }).filter(Boolean);
    saving.value = true;
    router.put(route('grupos.horario', props.group.id), { entries }, {
        preserveScroll: true,
        onSuccess: (p) => { if (!p.props.flash?.error) emit('update:open', false); },
        onFinish: () => (saving.value = false),
    });
}
</script>

<template>
    <SgModal :open="open" title="Armar horario" :subtitle="`Grupo ${group.label} · turno ${group.shift}${group.break ? ` · receso ${group.break.start}–${group.break.end}` : ''}`"
        :icon="ClockCircleOutlined" color="linear-gradient(135deg, #14a39a, #1d7fc9)" width="min(1240px, 97vw)" ok-text="Guardar horario" :ok-icon="SaveOutlined"
        :loading="saving" @update:open="emit('update:open', $event)" @ok="save">
        <!-- Franjas -->
        <div class="hb-cfg">
            <label>Inicio <a-time-picker v-model:value="cfg.start" format="HH:mm" value-format="HH:mm" :minute-step="5" :allow-clear="false" style="width: 110px" /></label>
            <label>Duración por clase <a-input-number v-model:value="cfg.duration" :min="20" :max="120" :step="5" addon-after="min" style="width: 130px" /></label>
            <label>Horas al día <a-input-number v-model:value="cfg.slots" :min="1" :max="12" style="width: 90px" /></label>
            <a-checkbox v-model:checked="cfg.saturday">Incluir sábado</a-checkbox>
            <span v-if="mode === 'existing'" class="hb-mode">Usando las horas del horario actual · cambia inicio, duración u horas para generar franjas nuevas</span>
            <span v-if="group.break" class="hb-brk"><CoffeeOutlined /> Receso {{ group.break.start }}–{{ group.break.end }} (del plantel)</span>
            <span v-else class="hb-brk none"><CoffeeOutlined /> Sin receso configurado para este turno</span>
        </div>

        <div class="hb">
            <!-- Paleta -->
            <aside class="hb-pal">
                <b class="hb-pal__t">1. Elige la asignatura</b>
                <button v-for="c in classes" :key="c.id" type="button" class="pal" :class="{ on: brush === c.id }" :style="{ '--c': colorOf(c.id) }" @click="brush = c.id">
                    <span class="pal__dot" />
                    <span class="pal__txt"><b>{{ c.subject }}</b><small><UserOutlined /> {{ c.teacher || 'Sin docente' }}</small></span>
                    <span class="pal__h" :class="{ ok: c.hours_per_week && (assigned[c.id] ?? 0) >= c.hours_per_week }">
                        <CheckCircleFilled v-if="c.hours_per_week && (assigned[c.id] ?? 0) >= c.hours_per_week" />
                        {{ (assigned[c.id] ?? 0).toFixed(1).replace('.0', '') }}<template v-if="c.hours_per_week">/{{ c.hours_per_week }}</template> h
                    </span>
                </button>
                <button type="button" class="pal pal--erase" :class="{ on: brush === 'erase' }" @click="brush = 'erase'"><ClearOutlined /> Borrador</button>
                <div class="hb-pal__acts">
                    <a-button size="small" @click="autofill"><ThunderboltOutlined /> Rellenar con horas del plan</a-button>
                    <a-button size="small" danger @click="clearAll"><ClearOutlined /> Vaciar</a-button>
                </div>
                <small class="hb-pal__hint">2. Haz clic en las celdas para asignarla. Clic otra vez en la misma la quita.</small>
            </aside>

            <!-- Rejilla -->
            <div class="hb-grid-wrap">
                <table class="hb-grid">
                    <thead><tr><th class="t">Hora</th><th v-for="d in days" :key="d.v">{{ d.l }}</th></tr></thead>
                    <tbody>
                        <tr v-for="r in rows" :key="r.start" :class="{ brk: r.break }">
                            <td class="t" :class="{ clash: !r.break && group.break && r.start < group.break.end && r.end > group.break.start }"><b>{{ r.start }}</b><small>{{ r.end }}</small></td>
                            <td v-if="r.break" :colspan="days.length" class="brk-cell"><span>☕ RECESO</span></td>
                            <template v-else>
                                <td v-for="d in days" :key="d.v" class="cell" @click="paint(d.v, r)">
                                    <div v-if="cells[`${d.v}|${r.start}`]" class="blk" :style="{ '--c': colorOf(cells[`${d.v}|${r.start}`].classId) }">
                                        <b>{{ classById(cells[`${d.v}|${r.start}`].classId)?.subject }}</b>
                                        <small>{{ classById(cells[`${d.v}|${r.start}`].classId)?.teacher || 'Sin docente' }}</small>
                                        <a-input v-model:value="cells[`${d.v}|${r.start}`].room" size="small" placeholder="Aula" class="blk__room" @click.stop>
                                            <template #prefix><EnvironmentOutlined /></template>
                                        </a-input>
                                    </div>
                                    <div v-else class="empty">+</div>
                                </td>
                            </template>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <template #extra><span class="hb-total">{{ Object.keys(cells).length }} clases a la semana</span></template>
    </SgModal>
</template>

<style scoped>
.hb-cfg { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; padding: 10px 12px; margin-bottom: 12px; border-radius: 12px; background: #f8fafc; border: 1px solid #eef2f7; }
.hb-cfg label { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 700; color: #475569; }
.hb-brk { margin-left: auto; display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 700; color: #b45309; background: #fff7e6; padding: 4px 10px; border-radius: 999px; }
.hb-mode { font-size: 11.5px; color: #64748b; background: #eef2f7; padding: 3px 10px; border-radius: 999px; }
.hb-brk.none { color: #94a3b8; background: #f1f5f9; }
.hb { display: grid; grid-template-columns: 260px minmax(0, 1fr); gap: 12px; }
.hb-pal { display: flex; flex-direction: column; gap: 6px; max-height: 58vh; overflow-y: auto; padding-right: 2px; }
.hb-pal__t { font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
.pal { display: flex; align-items: center; gap: 8px; padding: 7px 10px; border-radius: 10px; border: 1px solid #e2e8f0; background: #fff; cursor: pointer; text-align: left; font-family: inherit; transition: all .12s; }
.pal:hover { border-color: var(--c, #94a3b8); }
.pal.on { border-color: var(--c, #e3262b); background: color-mix(in srgb, var(--c, #e3262b) 9%, #fff); box-shadow: 0 0 0 2px color-mix(in srgb, var(--c, #e3262b) 25%, #fff); }
.pal__dot { width: 12px; height: 12px; border-radius: 4px; background: var(--c); flex: none; }
.pal__txt { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.pal__txt b { font-size: 12.5px; color: #0b2f55; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pal__txt small { font-size: 11px; color: #94a3b8; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pal__h { font-size: 11.5px; font-weight: 800; color: #94a3b8; white-space: nowrap; }
.pal__h.ok { color: #2e9b43; }
.pal--erase { color: #e3262b; font-weight: 700; justify-content: center; }
.hb-pal__acts { display: flex; flex-direction: column; gap: 6px; margin-top: 4px; }
.hb-pal__hint { color: #94a3b8; font-size: 11.5px; }
.hb-grid-wrap { max-height: 58vh; overflow: auto; border: 1px solid #e2e8f0; border-radius: 12px; }
.hb-grid { width: 100%; border-collapse: separate; border-spacing: 4px; min-width: 640px; }
.hb-grid th { position: sticky; top: 0; z-index: 1; background: #f1f5f9; color: #475569; font-size: 12px; padding: 7px; border-radius: 8px; }
.hb-grid .t { width: 70px; text-align: center; }
.hb-grid td.t b { display: block; font-size: 12.5px; color: #0b2f55; }
.hb-grid td.t small { font-size: 10.5px; color: #94a3b8; }
.hb-grid td.t.clash { background: #fef2f2; border-radius: 8px; }
.hb-grid td.t.clash b { color: #e3262b; }
.cell { height: 64px; vertical-align: top; cursor: pointer; }
.empty { height: 100%; min-height: 60px; display: grid; place-items: center; border: 1.5px dashed #e2e8f0; border-radius: 10px; color: #cbd5e1; font-size: 18px; transition: all .12s; }
.cell:hover .empty { border-color: #1d7fc9; color: #1d7fc9; background: #f5f9fd; }
.blk { height: 100%; min-height: 60px; display: flex; flex-direction: column; gap: 2px; padding: 5px 7px; border-radius: 10px; background: color-mix(in srgb, var(--c) 12%, #fff); border-left: 4px solid var(--c); }
.blk b { font-size: 12px; color: color-mix(in srgb, var(--c) 75%, #0b2f55); line-height: 1.15; }
.blk small { font-size: 10.5px; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.blk__room { margin-top: auto; }
.blk__room :deep(input) { font-size: 11px; }
.brk-cell span { display: block; text-align: center; padding: 6px; border-radius: 10px; font-weight: 800; font-size: 11.5px; letter-spacing: .1em; color: #b45309;
    background: repeating-linear-gradient(135deg, #fff7e6, #fff7e6 8px, #fdecc8 8px, #fdecc8 16px); border: 1px dashed #f5c26b; }
.hb-total { font-size: 12.5px; font-weight: 700; color: #64748b; }
@media (max-width: 900px) { .hb { grid-template-columns: 1fr; } }
</style>
