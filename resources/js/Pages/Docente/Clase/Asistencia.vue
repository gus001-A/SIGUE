<script setup>
/**
 * Pase de lista de la clase de trabajo.
 * - Solo se puede pasar lista en días de clase (según el horario) y nunca en días futuros.
 * - La lista se puede capturar o corregir hasta 7 días después; después queda solo de lectura.
 * - Hoy solo se puede pasar lista a partir de la hora de inicio de la clase.
 * - La observación solo se pide cuando el alumno no está presente.
 */
import { computed, reactive, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import dayjs from 'dayjs';
import {
    CheckOutlined, SaveOutlined, EditOutlined, CalendarOutlined, CheckCircleFilled, ClockCircleOutlined,
    HistoryOutlined, UserOutlined, InfoCircleOutlined, ExclamationCircleFilled, LockOutlined, CloseOutlined, FieldTimeOutlined, SafetyOutlined,
} from '@ant-design/icons-vue';
import ClaseShell from '@/Components/Clase/ClaseShell.vue';
import SgModal from '@/Components/SgModal.vue';
import { ref } from 'vue';

const props = defineProps({ course: Object, section: String, students: Array, periods: Array, periodId: [Number, null], attendance: Object, options: Object });

const ST = {
    presente: { c: '#1faf5c', short: 'A', icon: CheckOutlined, hint: '' },
    falta: { c: '#d9363e', short: 'F', icon: CloseOutlined, hint: 'Motivo de la falta (opcional)' },
    retardo: { c: '#f0a202', short: 'R', icon: FieldTimeOutlined, hint: '¿Cuántos minutos? ¿Motivo?' },
    justificada: { c: '#0461d8', short: 'J', icon: SafetyOutlined, hint: 'Justificante o motivo' },
};
const readOnly = computed(() => props.attendance.locked || props.attendance.not_started);
function setStatus(s, v) {
    if (readOnly.value) return;
    rows[s].status = v;
    if (v === 'presente') rows[s].notes = '';
}
const form = useForm({ date: props.attendance.date, session: props.attendance.session, rows: [] });
const rows = reactive({});
function hydrate() {
    form.date = props.attendance.date;
    form.session = props.attendance.session;
    Object.keys(rows).forEach((k) => delete rows[k]);
    for (const r of props.attendance.rows) rows[r.student_id] = { status: r.status, notes: r.notes ?? '' };
}
hydrate();
watch(() => props.attendance, hydrate, { deep: true });

const counts = computed(() => {
    const c = { presente: 0, falta: 0, retardo: 0, justificada: 0 };
    Object.values(rows).forEach((r) => c[r.status]++);
    return c;
});
const editing = computed(() => props.attendance.taken);

function open(date, session = 1) {
    router.get(route('docente.clase.asistencia', props.course.id), { date, session }, { preserveScroll: true, preserveState: false });
}
function all(status) { if (!readOnly.value) Object.values(rows).forEach((r) => { r.status = status; if (status === 'presente') r.notes = ''; }); }
const pct = computed(() => { const t = props.students.length; return t ? Math.round(((counts.value.presente + counts.value.retardo + counts.value.justificada) / t) * 100) : 0; });
function save() {
    form.rows = props.students.map((s) => ({ student_id: s.id, ...rows[s.id] }));
    form.post(route('docente.asistencia.store', props.course.id), { preserveScroll: true });
}

const histOpen = ref(false);
const histTotals = computed(() => {
    const t = { presentes: 0, faltas: 0, retardos: 0, justificadas: 0 };
    for (const h of props.attendance.history) for (const k in t) t[k] += h[k];
    const all = t.presentes + t.faltas + t.retardos + t.justificadas;
    return { ...t, pct: all ? Math.round(((all - t.faltas) / all) * 100) : 0 };
});
const pctColor = (p) => (p >= 90 ? '#1faf5c' : p >= 80 ? '#f0a202' : '#d9363e');
const pctOf = (h) => { const t = h.presentes + h.faltas + h.retardos + h.justificadas; return t ? Math.round(((t - h.faltas) / t) * 100) : 0; };

/** En el calendario solo se pueden elegir días de clase de hoy hacia atrás. */
const disabledDate = (d) => !d || d.isAfter(dayjs().endOf('day')) || !props.attendance.class_days.includes(d.isoWeekday?.() ?? ((d.day() + 6) % 7) + 1);
</script>

<template>
    <ClaseShell :course="course" :section="section" :count="students.length">
        <div class="att">
            <!-- ========== Días de clase ========== -->
            <aside class="days">
                <div class="days__head">
                    <b><CalendarOutlined /> Esta semana</b>
                    <small>{{ attendance.week_label }}</small>
                </div>
                <div class="days__list">
                    <button v-for="d in attendance.recent" :key="d.date" type="button" class="day" :disabled="d.future"
                        :class="{ on: d.date === attendance.date, done: d.taken >= d.sessions, part: d.taken && d.taken < d.sessions, locked: d.locked, future: d.future }"
                        @click="open(d.date)">
                        <span class="day__dot"><CheckCircleFilled v-if="d.taken >= d.sessions" /><ClockCircleOutlined v-else /></span>
                        <span class="day__txt"><b>{{ d.label }}<em v-if="d.today">Hoy</em></b>
                            <small>{{ d.future ? 'Próximamente' : d.taken >= d.sessions ? 'Lista capturada' : d.taken ? `${d.taken} de ${d.sessions} sesiones` : d.locked ? 'Sin capturar' : 'Pendiente' }}</small></span>
                    </button>
                    <p v-if="!attendance.recent.length" class="days__none">No hay clases esta semana.</p>
                </div>
                <div class="days__other">
                    <small>Otro día</small>
                    <a-date-picker :value="dayjs(attendance.date)" format="ddd DD/MM/YYYY" :disabled-date="disabledDate" :allow-clear="false" size="small" class="days__picker"
                        @change="(d) => d && open(d.format('YYYY-MM-DD'))" />
                    <a-button size="small" block :disabled="!attendance.history.length" @click="histOpen = true"><HistoryOutlined /> Historial de asistencia</a-button>
                </div>
            </aside>

            <!-- ========== Lista ========== -->
            <section class="list-card">
                <header class="lc__head" :class="{ 'is-edit': editing }">
                    <span class="lc__ic"><EditOutlined v-if="editing" /><CheckOutlined v-else /></span>
                    <div class="lc__txt">
                        <b>{{ editing ? 'Editando la lista del' : 'Pase de lista del' }} {{ attendance.date_label }}</b>
                        <small v-if="editing">Capturada por {{ attendance.recorded_by ?? '—' }} · última modificación {{ attendance.recorded_at }}.</small>
                        <small v-else-if="attendance.is_today">Clase de hoy<template v-if="attendance.starts_at"> · inicia a las {{ attendance.starts_at }}</template>. Marca solo a quien no esté presente y guarda.</small>
                        <small v-else>Este día aún no tiene lista capturada.</small>
                    </div>
                    <span v-if="!attendance.locked" class="lc__until"><ClockCircleOutlined /> Editable hasta el {{ attendance.editable_until }}</span>
                    <span v-else class="lc__until is-lock"><LockOutlined /> Solo lectura</span>
                    <a-segmented v-if="attendance.sessions > 1" :value="attendance.session"
                        :options="Array.from({ length: attendance.sessions }, (_, i) => ({ value: i + 1, label: `Sesión ${i + 1}` }))"
                        @change="(s) => open(attendance.date, s)" />
                </header>

                <a-alert v-if="attendance.locked" type="warning" show-icon class="lc__alert"
                    :message="`El plazo para modificar esta lista terminó (máximo ${attendance.edit_days} días después de la clase).`"
                    description="Puedes consultarla, pero ya no se puede cambiar. Si hay un error, pide a la administración que lo corrija." />
                <a-alert v-else-if="attendance.not_started" type="info" show-icon class="lc__alert"
                    :message="`La clase empieza a las ${attendance.starts_at}.`" description="Podrás pasar lista a partir de esa hora." />

                <div class="lc__tools">
                    <a-button :disabled="readOnly" @click="all('presente')"><CheckOutlined /> Todos presentes</a-button>
                    <div class="counts">
                        <span v-for="(v, k) in counts" :key="k" :style="{ '--c': ST[k].c }"><i><component :is="ST[k].icon" /></i> {{ options.attendanceStatuses.find((o) => o.value === k)?.label }} <b>{{ v }}</b></span>
                        <span class="counts__pct" :style="{ '--c': pct >= 90 ? '#1faf5c' : pct >= 80 ? '#f0a202' : '#d9363e' }">Asistencia <b>{{ pct }}%</b></span>
                    </div>
                </div>

                <div class="rows">
                    <div v-for="s in students" :key="s.id" class="row" :class="{ 'is-off': rows[s.id]?.status !== 'presente', ro: readOnly }" :style="{ '--c': ST[rows[s.id]?.status]?.c }">
                        <span class="sg-av row__av"><img v-if="s.photo_url" :src="s.photo_url" alt="" /><UserOutlined v-else /></span>
                        <span class="row__name"><b>{{ s.list_name }}</b><small>{{ s.enrollment_number }}</small></span>
                        <div v-if="rows[s.id]" class="row__st">
                            <a-tooltip v-for="o in options.attendanceStatuses" :key="o.value" :title="o.label">
                                <button type="button" class="stb" :class="{ on: rows[s.id].status === o.value }" :disabled="readOnly"
                                    :style="{ '--c': ST[o.value].c }" @click="setStatus(s.id, o.value)"><component :is="ST[o.value].icon" /><em class="stb__lbl">{{ o.label }}</em></button>
                            </a-tooltip>
                        </div>
                        <a-input v-if="rows[s.id] && rows[s.id].status !== 'presente'" v-model:value="rows[s.id].notes" :disabled="readOnly" :maxlength="255"
                            :placeholder="ST[rows[s.id].status].hint" class="row__notes" />
                    </div>
                    <a-empty v-if="!students.length" description="El grupo no tiene alumnos inscritos" />
                </div>

                <footer class="lc__foot">
                    <span v-if="form.errors.date || form.errors.rows" class="lc__err"><ExclamationCircleFilled /> {{ form.errors.date || form.errors.rows }}</span>
                    <span v-else class="lc__hint"><InfoCircleOutlined /> Días de clase, desde la hora de inicio y hasta {{ attendance.edit_days }} días después. La observación solo aplica si el alumno no está presente.</span>
                    <a-button type="primary" size="large" class="lc__save" :loading="form.processing" :disabled="!students.length || readOnly" @click="save">
                        <template #icon><SaveOutlined /></template>{{ editing ? 'Guardar cambios' : 'Guardar pase de lista' }}
                    </a-button>
                </footer>
            </section>
        </div>
        <SgModal v-model:open="histOpen" title="Historial de asistencia" :subtitle="`${course.subject} · ${course.group} · ${attendance.history.length} pases de lista`" :icon="HistoryOutlined"
            color="linear-gradient(135deg, #1d7fc9, #14a39a)" width="min(1000px, 96vw)" :footer="false">
            <div class="hs">
                <div class="hs__kpi" style="--c: #1faf5c"><b>{{ histTotals.pct }}%</b><span>Asistencia promedio</span></div>
                <div class="hs__kpi" style="--c: #0461d8"><b>{{ histTotals.presentes }}</b><span>Asistencias</span></div>
                <div class="hs__kpi" style="--c: #d9363e"><b>{{ histTotals.faltas }}</b><span>Faltas</span></div>
                <div class="hs__kpi" style="--c: #f0a202"><b>{{ histTotals.retardos }}</b><span>Retardos</span></div>
                <div class="hs__kpi" style="--c: #7c4ddb"><b>{{ histTotals.justificadas }}</b><span>Justificadas</span></div>
            </div>
            <div class="hl">
                <div v-for="h in attendance.history" :key="h.date + h.session" class="hl__row" :class="{ on: h.date === attendance.date && h.session === attendance.session }"
                    :style="{ '--p': pctColor(pctOf(h)) }">
                    <div class="hl__date"><b>{{ h.label }}</b><small v-if="h.session > 1">Sesión {{ h.session }}</small></div>
                    <div class="hl__bar">
                        <i style="--c: #1faf5c" :style="{ flex: h.presentes }" /><i style="--c: #f0a202" :style="{ flex: h.retardos }" />
                        <i style="--c: #7c4ddb" :style="{ flex: h.justificadas }" /><i style="--c: #d9363e" :style="{ flex: h.faltas }" />
                    </div>
                    <div class="hl__n">
                        <span style="--c: #1faf5c">{{ h.presentes }}</span><span style="--c: #d9363e">{{ h.faltas }}</span>
                        <span style="--c: #f0a202">{{ h.retardos }}</span><span style="--c: #7c4ddb">{{ h.justificadas }}</span>
                    </div>
                    <b class="hl__pct">{{ pctOf(h) }}%</b>
                </div>
            </div>
            <div class="hl__legend">
                <span style="--c: #1faf5c">Asistencia</span><span style="--c: #d9363e">Falta</span><span style="--c: #f0a202">Retardo</span><span style="--c: #7c4ddb">Justificada</span>
            </div>
        </SgModal>
    </ClaseShell>
</template>

<style scoped>
.att { flex: 1 1 auto; min-height: 0; display: grid; grid-template-columns: 220px minmax(0, 1fr); gap: 12px; }

/* Días */
.days { min-height: 0; display: flex; flex-direction: column; gap: 8px; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 10px; overflow-y: auto; }
.days__head { display: flex; flex-direction: column; }
.days__head b { color: #0b2f55; display: inline-flex; gap: 6px; align-items: center; }
.days__head small { color: #64748b; font-size: 12px; }
.days__picker { width: 100%; }
.days__list { display: flex; flex-direction: column; gap: 6px; }
.day { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; cursor: pointer; text-align: left; font-family: inherit; transition: all .15s; }
.day:hover { border-color: #1d7fc9; }
.day.on { border-color: #1d7fc9; background: #eff6ff; box-shadow: 0 0 0 2px #dbeafe; }
.day__dot { width: 28px; height: 28px; border-radius: 8px; display: grid; place-items: center; flex: none; color: #b45309; background: #fff7e6; }
.day.done .day__dot { color: #2e9b43; background: #e8f6ec; }
.day.part .day__dot { color: #1d7fc9; background: #eff6ff; }
.day__txt { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.day__txt b { font-size: 13px; color: #0b2f55; }
.day__txt small { font-size: 11.5px; color: #64748b; }
.day__edit { color: #94a3b8; }
.days__hist { display: flex; flex-direction: column; gap: 4px; border-top: 1px dashed #e2e8f0; padding-top: 8px; }
.days__hist > b { color: #0b2f55; font-size: 12.5px; display: inline-flex; gap: 6px; align-items: center; margin-bottom: 2px; }
.hist { display: flex; justify-content: space-between; gap: 8px; padding: 5px 8px; border: 0; border-radius: 8px; background: transparent; cursor: pointer; font-family: inherit; font-size: 12px; color: #475569; }
.hist:hover { background: #f5f9fd; }
.hist__n { display: inline-flex; gap: 4px; }
.hist__n i { font-style: normal; font-weight: 800; color: var(--c); min-width: 16px; text-align: right; }

/* Lista */
.list-card { min-height: 0; display: flex; flex-direction: column; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; }
.lc__head { display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: linear-gradient(120deg, #ecfdf3, #fff 70%); border-bottom: 1px solid #eef2f7; flex-wrap: wrap; }
.lc__head.is-edit { background: linear-gradient(120deg, #fff7e6, #fff 70%); }
.lc__ic { width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; color: #fff; background: #2e9b43; flex: none; }
.lc__head.is-edit .lc__ic { background: #f39200; }
.lc__txt { flex: 1; min-width: 220px; display: flex; flex-direction: column; }
.lc__txt b { color: #0b2f55; font-size: 15px; }
.lc__txt small { color: #64748b; font-size: 12px; }
.lc__tools { display: flex; align-items: center; gap: 10px; padding: 10px 16px; flex-wrap: wrap; }
.counts { margin-left: auto; display: flex; gap: 6px; flex-wrap: wrap; }
.counts span { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px 3px 4px; border-radius: 999px; font-size: 12px; color: #475569; background: color-mix(in srgb, var(--c) 8%, #fff); border: 1px solid color-mix(in srgb, var(--c) 22%, #fff); }
.counts i { width: 20px; height: 20px; border-radius: 50%; display: grid; place-items: center; font-style: normal; font-weight: 800; font-size: 11px; color: #fff; background: var(--c); }
.counts b { color: var(--c); }
.rows { flex: 1; min-height: 0; overflow-y: auto; padding: 0 16px 10px; display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 470px), 1fr)); gap: 8px; align-content: start; }
.row { display: flex; align-items: center; gap: 10px; padding: 8px 10px; background: #fff; border: 1px solid #e1e8f0; border-left: 4px solid var(--c, #1faf5c); border-radius: 12px; flex-wrap: wrap;
    transition: border-color .15s, background .15s, box-shadow .15s; }
.row:hover { box-shadow: 0 6px 16px -12px rgba(10, 31, 68, .35); }
.row.is-off { background: color-mix(in srgb, var(--c) 5%, #fff); border-color: color-mix(in srgb, var(--c) 30%, #e1e8f0); }
.row__av { width: 34px; height: 34px; }
.row__name { flex: 1; min-width: 150px; display: flex; flex-direction: column; line-height: 1.25; }
.row__name b { color: #0a1f44; font-size: 13px; }
.row__name small { color: #94a3b8; font-size: 11px; font-family: ui-monospace, Consolas, monospace; }
.row__st { display: flex; gap: 4px; }
.stb { display: inline-flex; align-items: center; gap: 5px; border: 1px solid #e1e8f0; background: #fff; color: #5d7085; height: 32px; min-width: 32px; justify-content: center; padding: 0 9px; border-radius: 9px;
    font-size: 12px; font-weight: 700; cursor: pointer; font-family: inherit; transition: all .12s; }
.stb__lbl { display: none; font-style: normal; }
.stb.on .stb__lbl { display: inline; }
.stb .anticon { font-size: 13px; }
.stb:hover:not(:disabled) { border-color: var(--c); color: var(--c); }
.stb.on { background: var(--c); border-color: var(--c); color: #fff; box-shadow: 0 4px 10px -6px var(--c); }
.stb:disabled { cursor: not-allowed; opacity: .75; }
.row__notes { flex: 1 1 100%; }
.row.ro { opacity: .92; }
.lc__alert { margin: 10px 16px 0; }
.lc__until { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: #0461d8; background: #eef5ff; border: 1px solid #d6e6fc; padding: 4px 10px; border-radius: 99px; }
.lc__until.is-lock { color: #b45309; background: #fff7e6; border-color: #fde3b0; }
.counts__pct { font-weight: 600; }
.day.locked .day__dot { color: #94a3b8; background: #f1f5f9; }
.lc__foot { display: flex; align-items: center; gap: 12px; padding: 10px 16px; border-top: 1px solid #eef2f7; background: #fafbfd; }
.lc__hint { font-size: 12px; color: #64748b; display: inline-flex; gap: 6px; align-items: center; }
.lc__err { font-size: 12.5px; color: #e3262b; font-weight: 600; display: inline-flex; gap: 6px; align-items: center; }
.lc__save { margin-left: auto; border-radius: 12px !important; font-weight: 700; background: linear-gradient(135deg, #2e9b43, #14a39a) !important; border: none !important; }
@media (max-width: 900px) { .att { grid-template-columns: 1fr; } }

.day { padding: 6px 8px !important; }
.day__txt b { text-transform: capitalize; display: flex; align-items: center; gap: 6px; }
.day__txt b em { font-style: normal; font-size: 10px; font-weight: 700; color: #fff; background: #1967d2; padding: 0 6px; border-radius: 6px; text-transform: uppercase; }
.day.future { opacity: .55; cursor: default; }
.days__none { margin: 0; font-size: 12.5px; color: #94a3b8; }
.days__other { margin-top: auto; display: flex; flex-direction: column; gap: 6px; padding-top: 8px; border-top: 1px solid #eef2f7; }
.days__other small { font-size: 11px; font-weight: 700; color: #5f6368; text-transform: uppercase; letter-spacing: .04em; }
.hs { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; margin-bottom: 14px; }
.hs__kpi { display: flex; flex-direction: column; gap: 2px; padding: 12px 14px; border-radius: 12px; background: color-mix(in srgb, var(--c) 8%, #fff); border: 1px solid color-mix(in srgb, var(--c) 25%, #fff); }
.hs__kpi b { font-size: 24px; font-weight: 800; color: var(--c); line-height: 1.1; }
.hs__kpi span { font-size: 11.5px; font-weight: 600; color: #5d7085; }
.hl { display: flex; flex-direction: column; gap: 6px; max-height: 52vh; overflow-y: auto; padding-right: 4px; }
.hl__row { display: grid; grid-template-columns: 150px minmax(0, 1fr) 150px 56px; align-items: center; gap: 14px; padding: 9px 12px; border-radius: 12px; border: 1px solid #e8edf3;
    border-left: 4px solid var(--p); background: #fff; transition: background .15s; }
.hl__row:hover { background: #f8fafd; }
.hl__row.on { background: #eef5ff; border-color: #c9dcfb; border-left-color: var(--p); }
.hl__date { display: flex; flex-direction: column; line-height: 1.25; }
.hl__date b { font-size: 13px; color: #0a1f44; }
.hl__date small { font-size: 11px; color: #94a3b8; }
.hl__bar { display: flex; height: 10px; border-radius: 99px; overflow: hidden; background: #eef2f7; gap: 2px; }
.hl__bar i { display: block; background: var(--c); min-width: 0; }
.hl__n { display: flex; gap: 6px; justify-content: flex-end; }
.hl__n span { min-width: 30px; text-align: center; font-size: 12px; font-weight: 700; color: var(--c); background: color-mix(in srgb, var(--c) 10%, #fff); border-radius: 8px; padding: 2px 6px; }
.hl__pct { text-align: right; font-size: 15px; color: var(--p); }
.hl__legend { display: flex; gap: 14px; justify-content: center; margin-top: 12px; font-size: 12px; color: #5d7085; }
.hl__legend span { display: inline-flex; align-items: center; gap: 6px; }
.hl__legend span::before { content: ''; width: 10px; height: 10px; border-radius: 3px; background: var(--c); }
@media (max-width: 760px) { .hs { grid-template-columns: repeat(2, 1fr); } .hl__row { grid-template-columns: 1fr 50px; } .hl__bar, .hl__n { grid-column: 1 / -1; } }
</style>
