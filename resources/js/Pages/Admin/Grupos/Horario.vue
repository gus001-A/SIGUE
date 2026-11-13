<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    ScheduleOutlined, PlusOutlined, DeleteOutlined, ClockCircleOutlined, TeamOutlined, BookOutlined, SendOutlined, MessageOutlined,
    UserOutlined, EnvironmentOutlined, FieldTimeOutlined, ReadOutlined, WarningOutlined, CoffeeOutlined, CheckCircleFilled, SolutionOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHead from '@/Components/PageHead.vue';
import SgModal from '@/Components/SgModal.vue';
import HorarioBuilder from '@/Components/HorarioBuilder.vue';
import axios from 'axios';
import { confirmDelete, message } from '@/lib/notify';

const props = defineProps({ group: Object, classes: Array, options: Object, canMessage: { type: Boolean, default: false } });
const builderOpen = ref(false);

const dayName = Object.fromEntries(props.options.days.map((d) => [d.value, d.label]));
const PALETTE = ['#1d7fc9', '#14a39a', '#2e9b43', '#f39200', '#e3262b', '#7c4ddb', '#e0457b', '#0f5a99', '#b9a20f', '#6b4c8c'];
const colorOf = (id) => PALETTE[props.classes.findIndex((c) => c.id === id) % PALETTE.length];

// Asignaturas
const cls = useForm({ subject_id: null, teacher_id: null });
const addOpen = ref(false);
const addClass = () => cls.post(route('grupos.clases.store', props.group.id), { preserveScroll: true, onSuccess: () => { addOpen.value = false; cls.reset(); } });
const setTeacher = (c, teacher_id) => router.put(route('clases.update', c.id), { teacher_id }, { preserveScroll: true });
// Si el docente asignado ya no está activo, se conserva como opción para no mostrar solo su id
const teacherOpts = (c) => (c.teacher_id && !props.options.teachers.some((t) => t.value === c.teacher_id)
    ? [{ value: c.teacher_id, label: `${c.teacher ?? 'Docente'} (inactivo)` }, ...props.options.teachers] : props.options.teachers);
const removeClass = (c) => confirmDelete({ title: '¿Quitar asignatura del grupo?', content: c.subject, okText: 'Sí, quitar', onOk: () => router.delete(route('clases.destroy', c.id), { preserveScroll: true }) });

// Horas
const toMin = (t) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
const hoursOf = (c) => c.schedules.reduce((t, s) => t + (toMin(s.ends_at) - toMin(s.starts_at)) / 60, 0);
const totalHours = computed(() => props.classes.reduce((t, c) => t + hoursOf(c), 0));
const noTeacher = computed(() => props.classes.filter((c) => !c.teacher_id).length);
const teachersCount = computed(() => new Set(props.classes.filter((c) => c.teacher_id).map((c) => c.teacher_id)).size);
const scheduled = computed(() => props.classes.filter((c) => c.schedules.length).length);
const KPIS = computed(() => [
    { label: 'Asignaturas', value: props.classes.length, note: `${scheduled.value} con horario`, icon: BookOutlined, color: '#0461d8' },
    { label: 'Horas por semana', value: totalHours.value.toFixed(1), note: 'programadas', icon: FieldTimeOutlined, color: '#1597a5' },
    { label: 'Docentes', value: teachersCount.value, note: 'imparten en el grupo', icon: SolutionOutlined, color: '#7c4ddb' },
    { label: 'Sin docente', value: noTeacher.value, note: noTeacher.value ? 'por asignar' : 'todo asignado', icon: WarningOutlined, color: noTeacher.value ? '#d9363e' : '#1faf5c' },
]);

// Mensaje directo al docente
const dm = ref({ open: false, cls: null, subject: '', body: '', sending: false, errors: {} });
function openDm(c) {
    dm.value = { open: true, cls: c, subject: `Grupo ${props.group.label} · ${c.subject}`, body: '', sending: false, errors: {} };
}
async function sendDm() {
    dm.value.sending = true;
    dm.value.errors = {};
    try {
        await axios.post(route('mensajes.store'), {
            recipient_id: dm.value.cls.teacher_user_id, kind: 'mensaje', subject: dm.value.subject, body: dm.value.body,
        }, { headers: { Accept: 'application/json' } });
        dm.value.open = false;
        message.success(`Mensaje enviado a ${dm.value.cls.teacher}.`);
    } catch (e) {
        const errs = e.response?.data?.errors ?? {};
        dm.value.errors = Object.fromEntries(Object.entries(errs).map(([k, v]) => [k, v[0]]));
        if (errs.recipient_id) message.error('No puedes enviarle mensajes a este docente desde tu cuenta.');
        else if (!Object.keys(errs).length) message.error('No se pudo enviar el mensaje.');
    } finally {
        dm.value.sending = false;
    }
}
const pctOf = (c) => (c.hours_per_week ? Math.min(100, Math.round((hoursOf(c) / c.hours_per_week) * 100)) : (c.schedules.length ? 100 : 0));

// Cuadrícula
const grid = computed(() => {
    const rows = {};
    for (const c of props.classes) for (const s of c.schedules) {
        const k = `${s.starts_at}-${s.ends_at}`;
        rows[k] ??= { time: k, cells: {} };
        rows[k].cells[s.day_of_week] = { subject: c.subject, teacher: c.teacher, room: s.room, color: colorOf(c.id) };
    }
    const list = Object.values(rows);
    if (props.group.break) list.push({ time: `${props.group.break.start}-${props.group.break.end}`, isBreak: true, cells: {} });
    return list.sort((a, b) => a.time.localeCompare(b.time));
});
const todayDow = ((new Date().getDay() + 6) % 7) + 1;
const days = computed(() => {
    const used = new Set(props.classes.flatMap((c) => c.schedules.map((s) => s.day_of_week)));
    return [1, 2, 3, 4, 5, 6, 7].filter((d) => d <= 5 || used.has(d));
});
</script>

<template>
    <AppLayout :title="`Horario ${group.label}`">
        <PageHead :title="`Horario · Grupo ${group.label}`" :subtitle="`${group.grade ?? ''} ${group.level ?? ''} · ${group.school_year} · turno ${group.shift}`"
            :icon="ScheduleOutlined" back @back="router.visit(route('grupos.index'))">
            <template #actions>
                <Link :href="route('grupos.show', group.id)"><a-button size="large"><template #icon><TeamOutlined style="color: #1d7fc9" /></template>Alumnos del grupo</a-button></Link>
                <a-button size="large" @click="addOpen = true"><template #icon><PlusOutlined style="color: #f39200" /></template>Agregar asignatura</a-button>
                <a-button type="primary" size="large" class="btn-build" :disabled="!classes.length" @click="builderOpen = true">
                    <template #icon><ScheduleOutlined /></template>{{ grid.length ? 'Editar horario' : 'Armar horario' }}
                </a-button>
            </template>
        </PageHead>

        <div class="vt-kpis">
            <div v-for="k in KPIS" :key="k.label" class="vt-kpi" :style="{ '--k': k.color }">
                <span class="vt-kpi__ic"><component :is="k.icon" /></span>
                <span class="vt-kpi__txt"><b>{{ k.value }}</b><small>{{ k.label }}</small><em>{{ k.note }}</em></span>
            </div>
        </div>

        <div class="hz">
            <!-- Asignaturas y docentes -->
            <section class="subj-panel">
                <header class="panel-head">
                    <span><BookOutlined /> Asignaturas y docentes</span>
                    <span class="panel-count">{{ classes.length }}</span>
                </header>
                <div class="subj-list">
                    <a-empty v-if="!classes.length" :image="false" description="Agrega las asignaturas del grupo para armar su horario." />
                    <article v-for="c in classes" :key="c.id" class="sj" :style="{ '--c': colorOf(c.id) }">
                        <div class="sj__top">
                            <span class="sj__ic"><ReadOutlined /></span>
                            <div class="sj__txt">
                                <b :title="c.subject">{{ c.subject }}</b>
                                <small>{{ c.code || 'Sin clave' }} · {{ hoursOf(c).toFixed(1) }}<template v-if="c.hours_per_week"> / {{ c.hours_per_week }}</template> h</small>
                            </div>
                            <a-tooltip title="Quitar asignatura"><a-button size="small" class="sg-ab red" @click="removeClass(c)"><DeleteOutlined /></a-button></a-tooltip>
                        </div>
                        <div class="sj__bar"><i :style="{ width: pctOf(c) + '%' }" /><CheckCircleFilled v-if="pctOf(c) >= 100" class="sj__ok" /></div>
                        <div class="sj__tch">
                            <a-select :value="c.teacher_id" :options="teacherOpts(c)" allow-clear show-search option-filter-prop="label" size="small"
                                placeholder="Asignar docente…" class="sj__sel" :class="{ empty: !c.teacher_id || !options.teachers.some((t) => t.value === c.teacher_id) }" @change="(v) => setTeacher(c, v ?? null)">
                                <template #suffixIcon><UserOutlined /></template>
                            </a-select>
                            <a-tooltip v-if="canMessage" :title="c.teacher_user_id ? `Enviar mensaje a ${c.teacher}` : 'El docente no tiene cuenta para recibir mensajes'">
                                <a-button size="small" class="sg-ab green" :disabled="!c.teacher_user_id" @click="openDm(c)"><MessageOutlined /></a-button>
                            </a-tooltip>
                        </div>
                    </article>
                </div>
            </section>

            <!-- Horario semanal -->
            <section class="grid-panel">
                <header class="panel-head">
                    <span><ScheduleOutlined /> Horario semanal</span>
                    <span v-if="group.break" class="brk"><CoffeeOutlined /> Receso {{ group.break.start }}–{{ group.break.end }}</span>
                </header>
                <div v-if="!grid.length || grid.every((r) => r.isBreak)" class="grid-empty">
                    <ScheduleOutlined />
                    <b>Este grupo aún no tiene horario</b>
                    <span>Elige una asignatura como “pincel” y marca las horas de la semana en una sola vista.</span>
                    <a-button type="primary" class="btn-build" :disabled="!classes.length" @click="builderOpen = true"><ScheduleOutlined /> Armar horario</a-button>
                </div>
                <div v-else class="grid-scroll">
                    <table class="wk">
                        <thead>
                            <tr>
                                <th class="wk__t"><ClockCircleOutlined /></th>
                                <th v-for="d in days" :key="d" :class="{ today: d === todayDow }">{{ dayName[d] }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in grid" :key="r.time">
                                <td class="wk__t"><b>{{ r.time.split('-')[0] }}</b><small>{{ r.time.split('-')[1] }}</small></td>
                                <td v-if="r.isBreak" :colspan="days.length" class="wk__brk"><span><CoffeeOutlined /> RECESO</span></td>
                                <td v-for="d in (r.isBreak ? [] : days)" :key="d" :class="{ today: d === todayDow }">
                                    <div v-if="r.cells[d]" class="blk" :style="{ '--c': r.cells[d].color }">
                                        <b>{{ r.cells[d].subject }}</b>
                                        <small><UserOutlined /> {{ r.cells[d].teacher || 'Sin docente' }}</small>
                                        <small v-if="r.cells[d].room"><EnvironmentOutlined /> {{ r.cells[d].room }}</small>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <HorarioBuilder v-model:open="builderOpen" :group="group" :classes="classes" :color-of="colorOf" />

        <SgModal v-model:open="addOpen" title="Agregar asignatura" :subtitle="`Grupo ${group.label}`" :icon="BookOutlined" color="linear-gradient(135deg, #f39200, #e3262b)"
            width="min(700px, 96vw)" ok-text="Agregar" :ok-icon="PlusOutlined" :loading="cls.processing" @ok="addClass">
            <a-form layout="vertical" class="sg-grid-2">
                <a-form-item label="Asignatura" required :validate-status="cls.errors.subject_id ? 'error' : undefined" :help="cls.errors.subject_id">
                    <a-select v-model:value="cls.subject_id" show-search option-filter-prop="label" :options="options.subjects" />
                </a-form-item>
                <a-form-item label="Docente"><a-select v-model:value="cls.teacher_id" allow-clear show-search option-filter-prop="label" :options="options.teachers" /></a-form-item>
            </a-form>
        </SgModal>
        <SgModal v-model:open="dm.open" title="Mensaje directo" :subtitle="dm.cls ? `Para ${dm.cls.teacher} · ${dm.cls.subject}` : ''" :icon="MessageOutlined"
            color="linear-gradient(135deg, #1faf5c, #1597a5)" width="min(640px, 96vw)" ok-text="Enviar" :ok-icon="SendOutlined" :loading="dm.sending" @ok="sendDm">
            <a-form layout="vertical">
                <a-form-item label="Asunto" required :validate-status="dm.errors.subject ? 'error' : undefined" :help="dm.errors.subject">
                    <a-input v-model:value="dm.subject" :maxlength="150" />
                </a-form-item>
                <a-form-item label="Mensaje" required :validate-status="dm.errors.body ? 'error' : undefined" :help="dm.errors.body">
                    <a-textarea v-model:value="dm.body" :rows="5" :maxlength="5000" placeholder="Escribe tu mensaje para el docente…" />
                </a-form-item>
            </a-form>
            <p class="sg-muted" style="margin: 0">El docente lo recibe en <b>Mensajes</b> y como notificación.</p>
        </SgModal>
    </AppLayout>
</template>

<style scoped>
.btn-build { border-radius: 10px !important; font-weight: 700; background: linear-gradient(135deg, #14a39a, #1d7fc9) !important; border: none !important; color: #fff !important; }
.vt-kpis { flex: none; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-bottom: 12px; }
.vt-kpi { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #e1e8f0; border-left: 4px solid var(--k); border-radius: 12px; padding: 10px 14px;
    transition: transform .18s, box-shadow .18s; min-width: 0; }
.vt-kpi:hover { transform: translateY(-3px); box-shadow: 0 10px 22px color-mix(in srgb, var(--k) 18%, transparent); }
.vt-kpi__ic { width: 40px; height: 40px; border-radius: 11px; flex: none; display: grid; place-items: center; font-size: 18px; color: #fff; background: var(--k);
    box-shadow: 0 6px 14px color-mix(in srgb, var(--k) 35%, transparent); }
.vt-kpi__txt { display: flex; flex-direction: column; min-width: 0; line-height: 1.15; }
.vt-kpi__txt b { font-size: 20px; font-weight: 800; color: #0a1f44; }
.vt-kpi__txt small { font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #5d7085; }
.vt-kpi__txt em { font-style: normal; font-size: 11.5px; color: #94a3b8; }
.panel-count { min-width: 26px; height: 22px; padding: 0 8px; border-radius: 99px; display: grid; place-items: center; font-size: 12px; font-weight: 800; color: #0461d8; background: #e6f0fd; }
.sj__tch { display: flex; gap: 6px; align-items: center; }
.sj__tch .sj__sel { flex: 1; min-width: 0; }
@media (max-width: 900px) { .vt-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
.hz { flex: 1 1 auto; min-height: 0; display: grid; grid-template-columns: 340px minmax(0, 1fr); gap: 14px; }
.subj-panel, .grid-panel { min-height: 0; display: flex; flex-direction: column; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: 0 10px 24px -22px rgba(11, 47, 85, .45); overflow: hidden; }
.panel-head { flex: none; display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; padding: 12px 14px; border-bottom: 1px solid #eef2f7;
    background: linear-gradient(180deg, #f6faff, #fff); font-weight: 800; color: #0b2f55; font-size: 13.5px; }
.panel-head > span:first-child { display: inline-flex; align-items: center; gap: 7px; }
.panel-kpis { font-size: 12px; font-weight: 500; color: #64748b; display: inline-flex; gap: 4px; align-items: center; flex-wrap: wrap; }
.panel-kpis b { color: #0b2f55; }
.warn { font-style: normal; color: #e3262b; font-weight: 700; margin-left: 4px; display: inline-flex; gap: 4px; align-items: center; }
.brk { font-size: 12px; font-weight: 700; color: #b45309; background: #fff7e6; border: 1px solid #fde3b0; padding: 2px 10px; border-radius: 8px; display: inline-flex; gap: 5px; align-items: center; }

/* Lista de asignaturas */
.subj-list { flex: 1; min-height: 0; overflow-y: auto; padding: 10px; display: flex; flex-direction: column; gap: 8px; }
.sj { border: 1px solid #e8eef5; border-left: 4px solid var(--c); border-radius: 12px; padding: 9px 10px; display: flex; flex-direction: column; gap: 7px; background: #fff; transition: box-shadow .15s; }
.sj:hover { box-shadow: 0 8px 18px -14px var(--c); }
.sj__top { display: flex; align-items: center; gap: 8px; }
.sj__ic { width: 30px; height: 30px; border-radius: 9px; flex: none; display: grid; place-items: center; color: #fff; background: var(--c); font-size: 14px; }
.sj__txt { flex: 1; min-width: 0; display: flex; flex-direction: column; line-height: 1.25; }
.sj__txt b { font-size: 13px; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sj__txt small { font-size: 11px; color: #94a3b8; }
.sj .sg-ab { width: 28px; height: 28px; font-size: 13px; }
.sj__bar { position: relative; height: 5px; border-radius: 99px; background: #eef2f7; }
.sj__bar i { display: block; height: 100%; border-radius: 99px; background: var(--c); transition: width .3s; }
.sj__ok { position: absolute; right: -2px; top: -5px; font-size: 13px; color: #2e9b43; background: #fff; border-radius: 50%; }
.sj__sel { width: 100%; }
.sj__sel.empty :deep(.ant-select-selector) { border-color: #f5b1b3 !important; background: #fff7f7 !important; }

/* Cuadrícula */
.grid-scroll { flex: 1; min-height: 0; overflow: auto; padding: 8px 10px 10px; }
.wk { width: 100%; border-collapse: separate; border-spacing: 5px; min-width: 640px; table-layout: fixed; }
.wk th { position: sticky; top: 0; z-index: 1; background: #f1f5f9; color: #475569; font-size: 12px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; padding: 8px; border-radius: 8px; }
.wk th.today { background: linear-gradient(135deg, #1d7fc9, #14a39a); color: #fff; }
.wk .wk__t { width: 70px; text-align: center; }
td.wk__t b { display: block; font-size: 12.5px; color: #0b2f55; }
td.wk__t small { font-size: 10.5px; color: #94a3b8; }
.wk td { vertical-align: top; height: 54px; }
.wk td.today { background: #f5faff; border-radius: 10px; }
.blk { height: 100%; display: flex; flex-direction: column; gap: 1px; padding: 6px 8px; border-radius: 9px; background: color-mix(in srgb, var(--c) 9%, #fff); border-left: 3px solid var(--c); }
.blk b { font-size: 12px; color: color-mix(in srgb, var(--c) 80%, #0f172a); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.blk small { font-size: 10.5px; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: flex; gap: 3px; align-items: center; }
.wk__brk span { display: flex; align-items: center; justify-content: center; gap: 6px; height: 100%; min-height: 30px; border-radius: 9px; font-weight: 800; letter-spacing: .1em; font-size: 11.5px; color: #b45309;
    background: repeating-linear-gradient(135deg, #fff7e6, #fff7e6 10px, #fdecc8 10px, #fdecc8 20px); border: 1px dashed #f5c26b; }
.wk td.wk__brk { height: 34px; }
.grid-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; text-align: center; padding: 30px; color: #64748b; }
.grid-empty > .anticon { font-size: 40px; color: #9ed9d4; }
.grid-empty b { color: #0b2f55; font-size: 15px; }
@media (max-width: 960px) { .hz { grid-template-columns: 1fr; overflow-y: auto; } .subj-panel { max-height: 360px; } }
</style>
