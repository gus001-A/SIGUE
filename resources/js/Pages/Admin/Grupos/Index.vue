<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import {
    AppstoreOutlined, PlusOutlined, TeamOutlined, BookOutlined, EditOutlined, WarningOutlined, SearchOutlined,
    FieldTimeOutlined, CrownOutlined, NumberOutlined, CalendarOutlined, EyeOutlined, ClockCircleOutlined,
    FilePdfOutlined, ScheduleOutlined, UnorderedListOutlined, ArrowRightOutlined, BankOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHead from '@/Components/PageHead.vue';
import StatCard from '@/Components/StatCard.vue';
import SgModal from '@/Components/SgModal.vue';
import SgTable from '@/Components/SgTable.vue';

const props = defineProps({ groups: Array, schoolYearId: [Number, null], options: Object });

const open = ref(false);
const editing = ref(null);
const blank = () => ({ school_year_id: props.schoolYearId, grade_id: null, tutor_teacher_id: null, name: '', shift: 'matutino', capacity: 35, with_subjects: true });
const form = useForm(blank());
const err = (k) => ({ 'validate-status': form.errors[k] ? 'error' : undefined, help: form.errors[k] });
const SHIFTS = [{ value: 'matutino', label: 'Matutino' }, { value: 'vespertino', label: 'Vespertino' }, { value: 'nocturno', label: 'Nocturno' }, { value: 'mixto', label: 'Mixto' }];
const PALETTE = ['#1d7fc9', '#14a39a', '#2e9b43', '#f39200', '#e3262b', '#7c4ddb', '#e0457b', '#0f5a99'];
const colorOf = (g) => PALETTE[(g.grade_id ?? 0) % PALETTE.length];

function create() { editing.value = null; Object.assign(form, blank()); form.clearErrors(); open.value = true; }
function edit(g) { editing.value = g; Object.assign(form, { ...blank(), ...g, with_subjects: false }); form.clearErrors(); open.value = true; }
function submit() {
    const o = { preserveScroll: true, onSuccess: () => (open.value = false) };
    editing.value ? form.put(route('grupos.update', editing.value.id), o) : form.post(route('grupos.store'), o);
}
const pct = (g) => (g.capacity ? Math.min(100, Math.round((g.students_count / g.capacity) * 100)) : 0);

const LEVEL_COLOR = { Preescolar: '#e0457b', Primaria: '#2e9b43', Secundaria: '#1d7fc9', Preparatoria: '#7c4ddb', Bachillerato: '#7c4ddb', Licenciatura: '#f39200' };
const shiftLabel = Object.fromEntries(SHIFTS.map((s) => [s.value, s.label]));

const columns = [
    { title: 'Grupo', key: 'group', icon: AppstoreOutlined, width: 220, filterValue: (g) => `${g.label} ${g.grade ?? ''}` },
    { title: 'Nivel', key: 'level', icon: BankOutlined, width: 150, filter: 'select', filterValue: (g) => g.level || 'Sin nivel' },
    { title: 'Turno', key: 'shift', icon: FieldTimeOutlined, width: 130, filter: 'select', filterValue: (g) => shiftLabel[g.shift] ?? g.shift },
    { title: 'Docente tutor', key: 'tutor', icon: CrownOutlined, width: 250, filterValue: (g) => g.tutor ?? 'Sin tutor' },
    { title: 'Alumnos', key: 'students', icon: TeamOutlined, filter: false },
    { title: 'Asignaturas', key: 'subjects', icon: BookOutlined, width: 150, filter: false },
    { title: 'Acciones', key: 'actions', width: 150, align: 'center' },
];
</script>

<template>
    <AppLayout title="Grupos">
        <PageHead title="Grupos y horarios" subtitle="Grupos del ciclo, asignaturas, docentes y horarios del plantel" :icon="AppstoreOutlined">
            <template #actions>
                <a-select :value="schoolYearId" :options="options.schoolYears" style="width: 200px" size="large" @change="(v) => router.get(route('grupos.index'), { school_year_id: v })">
                    <template #suffixIcon><CalendarOutlined /></template>
                </a-select>
                <a-button type="primary" size="large" @click="create"><template #icon><PlusOutlined /></template>Nuevo grupo</a-button>
            </template>
        </PageHead>

        <div class="sg-stats">
            <StatCard label="Grupos del ciclo" :value="groups.length" :icon="AppstoreOutlined" color="violet" />
            <StatCard label="Alumnos inscritos" :value="groups.reduce((t, g) => t + g.students_count, 0)" :icon="TeamOutlined" color="indigo" />
            <StatCard label="Asignaturas asignadas" :value="groups.reduce((t, g) => t + g.classes_count, 0)" :icon="BookOutlined" color="amber" />
            <StatCard label="Grupos sin tutor" :value="groups.filter((g) => !g.tutor).length" :icon="WarningOutlined" color="red" />
        </div>

        <a-card :bordered="false" class="sg-fill">
            <SgTable :columns="columns" :data-source="groups" unit="grupos"
                :custom-row="(r) => ({ onDblclick: () => router.visit(route('grupos.show', r.id)) })">
                <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'group'">
                        <div class="cell-group">
                            <div class="badge" :style="{ background: `linear-gradient(135deg, ${colorOf(record)}, color-mix(in srgb, ${colorOf(record)} 55%, #0b2f55))` }">{{ record.name }}</div>
                            <div class="group-label"><b>{{ record.label }}</b><small>{{ record.grade }} grado</small></div>
                        </div>
                    </template>
                    <template v-else-if="column.key === 'level'">
                        <span class="sg-badge" :style="{ '--c': LEVEL_COLOR[record.level] ?? '#94a3b8' }">{{ record.level || 'Sin nivel' }}</span>
                    </template>
                    <template v-else-if="column.key === 'shift'">
                        <span class="cell-shift"><FieldTimeOutlined /> {{ shiftLabel[record.shift] ?? record.shift }}</span>
                    </template>
                    <template v-else-if="column.key === 'tutor'">
                        <span v-if="record.tutor" class="tutor-name"><CrownOutlined /> {{ record.tutor }}</span>
                        <span v-else class="sg-badge" style="--c: #e3262b">Sin tutor</span>
                    </template>
                    <template v-else-if="column.key === 'students'">
                        <div class="cell-progress" :style="{ '--c': colorOf(record) }">
                            <span class="progress-text"><b>{{ record.students_count }}</b> / {{ record.capacity ?? '∞' }} alumnos <em v-if="record.capacity">{{ pct(record) }}%</em></span>
                            <span class="bar"><i :style="{ width: pct(record) + '%' }" /></span>
                        </div>
                    </template>
                    <template v-else-if="column.key === 'subjects'">
                        <span class="subj-n" :class="{ zero: !record.classes_count }"><b>{{ record.classes_count }}</b> {{ record.classes_count === 1 ? 'asignatura' : 'asignaturas' }}</span>
                    </template>
                    <template v-else-if="column.key === 'actions'">
                        <div class="sg-actions">
                            <a-tooltip title="Alumnos del grupo"><a-button class="sg-ab blue" @click.stop="router.visit(route('grupos.show', record.id))"><TeamOutlined /></a-button></a-tooltip>
                            <a-tooltip title="Horario y asignaturas"><a-button class="sg-ab green" @click.stop="router.visit(route('grupos.horario.ver', record.id))"><ScheduleOutlined /></a-button></a-tooltip>
                            <a-tooltip title="Editar grupo"><a-button class="sg-ab navy" @click.stop="edit(record)"><EditOutlined /></a-button></a-tooltip>
                        </div>
                    </template>
                </template>
            </SgTable>
        </a-card>

        <SgModal v-model:open="open" :title="editing ? `Editar grupo ${editing.label}` : 'Nuevo grupo'" subtitle="Se crea en el plantel activo" :icon="AppstoreOutlined"
            color="linear-gradient(135deg, #14a39a, #1d7fc9)" :loading="form.processing" @ok="submit">
            <a-form layout="vertical" class="sg-grid-2">
                <a-form-item label="Ciclo escolar" required v-bind="err('school_year_id')"><a-select v-model:value="form.school_year_id" :options="options.schoolYears" /></a-form-item>
                <a-form-item label="Grado" required v-bind="err('grade_id')"><a-select v-model:value="form.grade_id" :options="options.grades" /></a-form-item>
                <a-form-item label="Grupo" required v-bind="err('name')"><a-input v-model:value="form.name" placeholder="A"><template #prefix><NumberOutlined style="color: #14a39a" /></template></a-input></a-form-item>
                <a-form-item label="Turno" v-bind="err('shift')"><a-select v-model:value="form.shift" :options="SHIFTS" /></a-form-item>
                <a-form-item label="Capacidad" v-bind="err('capacity')"><a-input-number v-model:value="form.capacity" :min="1" /></a-form-item>
                
                <a-form-item label="Docente tutor del grupo" v-bind="err('tutor_teacher_id')">
                    <a-select v-model:value="form.tutor_teacher_id" allow-clear show-search option-filter-prop="label" :options="options.teachers"><template #suffixIcon><CrownOutlined /></template></a-select>
                </a-form-item>
                
                <a-form-item label="Asignaturas" class="full">
                    <a-checkbox v-if="!editing" v-model:checked="form.with_subjects"><BookOutlined style="color: #f39200" /> Asignar las del grado</a-checkbox>
                    <span v-else class="sg-muted"><ClockCircleOutlined /> Se gestionan dentro del grupo</span>
                </a-form-item>
            </a-form>
        </SgModal>
    </AppLayout>
</template>

<style scoped>
.cell-group { display: flex; align-items: center; gap: 12px; }
.badge { width: 40px; height: 40px; border-radius: 12px; color: #fff; display: grid; place-items: center; font-weight: 800; font-size: 16px; flex: none; box-shadow: 0 6px 14px -8px rgba(11, 47, 85, .6); }
.group-label { display: flex; flex-direction: column; line-height: 1.25; min-width: 0; }
.group-label b { font-size: 14px; color: #0f172a; }
.group-label small { font-size: 11.5px; color: #94a3b8; }
.cell-shift { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #475569; }
.cell-shift .anticon { color: #94a3b8; }
.tutor-name { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: #1e293b; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tutor-name .anticon { color: #7c4ddb; }
.cell-progress { display: flex; flex-direction: column; gap: 5px; min-width: 160px; }
.progress-text { font-size: 12.5px; color: #64748b; }
.progress-text b { color: #0f172a; font-size: 14px; }
.progress-text em { font-style: normal; float: right; font-weight: 700; color: var(--c); }
.bar { height: 6px; border-radius: 99px; background: #eef2f7; overflow: hidden; }
.bar i { display: block; height: 100%; border-radius: 99px; background: var(--c); }
.subj-n { display: inline-flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 600; color: #5d7085; }
.subj-n b { min-width: 28px; height: 28px; padding: 0 6px; border-radius: 9px; display: grid; place-items: center; font-size: 14px; font-weight: 800; color: #0461d8; background: #e6f0fd; }
.subj-n.zero b { color: #d9363e; background: #fdecec; }
</style>
