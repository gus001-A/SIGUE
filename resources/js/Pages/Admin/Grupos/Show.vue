<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    AppstoreOutlined, FilePdfOutlined, EyeOutlined, TeamOutlined, BookOutlined, CrownOutlined, UserOutlined, NumberOutlined, FlagOutlined,
    ScheduleOutlined, UserAddOutlined, CheckOutlined, HourglassOutlined, InfoCircleOutlined, MailOutlined, PhoneOutlined, HeartOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHead from '@/Components/PageHead.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusTag from '@/Components/StatusTag.vue';
import SgTable from '@/Components/SgTable.vue';
import SgModal from '@/Components/SgModal.vue';

const props = defineProps({
    group: Object, students: Array, options: Object,
    pending: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

// Inscribir alumno al grupo
const enrOpen = ref(false);
const enr = useForm({ student_id: null, group_id: props.group.id, type: 'inscripcion', authorize: true, notes: '' });
const candidate = computed(() => props.options.candidates?.find((c) => c.value === enr.student_id));
const seatsLeft = computed(() => (props.group.capacity ? props.group.capacity - props.students.length : null));
function openEnroll() {
    enr.reset();
    enr.clearErrors();
    enr.authorize = !!props.can.authorize;
    enrOpen.value = true;
}
watch(() => enr.student_id, () => { enr.type = candidate.value?.status === 'activo' ? 'reinscripcion' : 'inscripcion'; });
const enroll = () => enr.post(route('matricula.store'), { preserveScroll: true, onSuccess: () => { enrOpen.value = false; } });
const authorizeEnr = (e) => router.post(route('matricula.authorize', e.id), {}, { preserveScroll: true });

const studentCols = [
    { title: 'Matrícula', key: 'enrollment_number', dataIndex: 'enrollment_number', icon: NumberOutlined, width: 130 },
    { title: 'Alumno', key: 'name', icon: UserOutlined, filterValue: (r) => `${r.list_name} ${r.curp ?? ''}` },
    { title: 'Correo', key: 'email', dataIndex: 'email', icon: MailOutlined, width: 220 },
    { title: 'Teléfono', key: 'phone', dataIndex: 'phone', icon: PhoneOutlined, width: 130 },
    { title: 'Tutor', key: 'guardian', icon: HeartOutlined, width: 220, filterValue: (r) => `${r.guardian ?? ''} ${r.guardian_phone ?? ''}` },
    { title: 'Estado', key: 'status', dataIndex: 'status', icon: FlagOutlined, filter: 'select', width: 120 },
    { title: 'Acciones', key: 'actions', width: 90, align: 'center' },
];
</script>

<template>
    <AppLayout :title="`Grupo ${group.label}`">
        <PageHead :title="`Grupo ${group.label}`" :subtitle="`${group.grade ?? ''} ${group.level ?? ''} · ${group.school_year} · turno ${group.shift} · ${group.campus ?? ''}`"
            :icon="AppstoreOutlined" back @back="router.visit(route('grupos.index'))">
            <template #actions>
                <a :href="route('grupos.lista', group.id)" download>
                    <a-button size="large"><template #icon><FilePdfOutlined style="color: #e3262b" /></template>Lista del grupo</a-button>
                </a>
                <Link :href="route('grupos.horario.ver', group.id)">
                    <a-button size="large" class="btn-sched"><template #icon><ScheduleOutlined /></template>Horario y asignaturas</a-button>
                </Link>
                <a-button v-if="can.enroll" type="primary" size="large" class="btn-enroll" @click="openEnroll">
                    <template #icon><UserAddOutlined /></template>Inscribir alumno
                </a-button>
            </template>
        </PageHead>

        <div class="sg-stats">
            <StatCard label="Alumnos inscritos" :value="students.length" :icon="TeamOutlined" color="indigo" :hint="group.capacity ? `Capacidad: ${group.capacity}` : null" />
            <StatCard label="Lugares disponibles" :value="seatsLeft === null ? 'Sin límite' : Math.max(0, seatsLeft)" :icon="UserAddOutlined" :color="seatsLeft !== null && seatsLeft <= 0 ? 'red' : 'green'" />
            <StatCard label="Pendientes de autorizar" :value="pending.length" :icon="HourglassOutlined" color="amber" />
            <StatCard label="Docente tutor" :value="group.tutor ?? 'Sin asignar'" :icon="CrownOutlined" color="purple" />
        </div>

        <!-- Pendientes de autorizar -->
        <div v-if="pending.length" class="enr-pend">
            <div class="enr-pend__head"><HourglassOutlined /> Inscripciones pendientes de autorizar ({{ pending.length }})</div>
            <div v-for="e in pending" :key="e.id" class="enr-pend__row">
                <span class="sg-av"><UserOutlined /></span>
                <div class="enr-pend__who"><b>{{ e.list_name }}</b><small>{{ e.enrollment_number }} · {{ e.type === 'reinscripcion' ? 'Reinscripción' : 'Inscripción' }} · {{ e.enrolled_on }}</small></div>
                <a-button v-if="can.authorize" size="small" class="sg-ab green" @click="authorizeEnr(e)"><CheckOutlined /> Autorizar</a-button>
                <span v-else class="sg-badge" style="--c: #f39200">Pendiente</span>
            </div>
        </div>

        <a-card :bordered="false" class="sg-fill">
            <SgTable :columns="studentCols" :data-source="students" unit="alumnos" empty-text="Aún no hay alumnos inscritos en este grupo"
                :custom-row="(r) => ({ onDblclick: () => router.visit(route('alumnos.show', r.id)) })">
                <template #bodyCell="{ column, record, index }">
                    <template v-if="column.key === 'n'"><span class="sg-faint">{{ index + 1 }}</span></template>
                    <template v-else-if="column.key === 'enrollment_number'"><span class="mat">{{ record.enrollment_number || '—' }}</span></template>
                    <template v-else-if="column.key === 'name'">
                        <Link :href="route('alumnos.show', record.id)" class="sg-who">
                            <span class="sg-av"><img v-if="record.photo_url" :src="record.photo_url" alt="" /><UserOutlined v-else /></span>
                            <span><b>{{ record.list_name }}</b><small>{{ record.curp || 'Sin CURP' }}</small></span>
                        </Link>
                    </template>
                    <template v-else-if="column.key === 'email'">
                        <a v-if="record.email" :href="`mailto:${record.email}`" class="ct"><MailOutlined /> {{ record.email }}</a>
                        <span v-else class="sg-faint">Sin correo</span>
                    </template>
                    <template v-else-if="column.key === 'phone'">
                        <a v-if="record.phone" :href="`tel:${record.phone}`" class="ct"><PhoneOutlined /> {{ record.phone }}</a>
                        <span v-else class="sg-faint">Sin teléfono</span>
                    </template>
                    <template v-else-if="column.key === 'guardian'">
                        <div v-if="record.guardian" class="gd"><b>{{ record.guardian }}</b><small v-if="record.guardian_phone"><PhoneOutlined /> {{ record.guardian_phone }}</small></div>
                        <span v-else class="sg-faint">Sin tutor</span>
                    </template>
                    <template v-else-if="column.key === 'status'"><StatusTag :status="record.status" /></template>
                    <template v-else-if="column.key === 'actions'">
                        <a-tooltip title="Ver expediente"><Link :href="route('alumnos.show', record.id)"><a-button class="sg-ab blue"><EyeOutlined /></a-button></Link></a-tooltip>
                    </template>
                </template>
            </SgTable>
        </a-card>

        <!-- Modal: Inscribir alumno -->
        <SgModal v-model:open="enrOpen" title="Inscribir alumno" :subtitle="`Grupo ${group.label} · ${group.school_year}`" :icon="UserAddOutlined"
            color="linear-gradient(135deg, #1d7fc9, #14a39a)" width="min(760px, 96vw)" ok-text="Inscribir" :ok-icon="UserAddOutlined"
            :loading="enr.processing" @ok="enroll">
            <a-form layout="vertical">
                <a-form-item label="Alumno" required :validate-status="enr.errors.student_id ? 'error' : undefined" :help="enr.errors.student_id">
                    <a-select v-model:value="enr.student_id" :options="options.candidates" show-search option-filter-prop="label"
                        placeholder="Busca por nombre o matrícula" :not-found-content="'No hay alumnos disponibles para inscribir'">
                        <template #suffixIcon><UserOutlined /></template>
                    </a-select>
                </a-form-item>
                <div class="sg-grid-2">
                    <a-form-item label="Tipo de movimiento">
                        <a-segmented v-model:value="enr.type" block :options="[{ label: 'Inscripción', value: 'inscripcion' }, { label: 'Reinscripción', value: 'reinscripcion' }]" />
                    </a-form-item>
                    <a-form-item label="Autorización">
                        <a-checkbox v-model:checked="enr.authorize" :disabled="!can.authorize">Autorizar de inmediato</a-checkbox>
                    </a-form-item>
                </div>
                <a-form-item label="Observaciones" :validate-status="enr.errors.notes ? 'error' : undefined" :help="enr.errors.notes">
                    <a-input v-model:value="enr.notes" placeholder="Opcional" :maxlength="1000" />
                </a-form-item>
            </a-form>
            <div class="enr-note">
                <InfoCircleOutlined />
                <span>
                    Solo aparecen alumnos del plantel sin inscripción vigente en el ciclo {{ group.school_year }}.
                    <template v-if="seatsLeft !== null && seatsLeft <= 0"> <b>El grupo está lleno:</b> la inscripción quedará pendiente.</template>
                    <template v-else-if="!can.authorize"> La inscripción quedará pendiente de autorización.</template>
                </span>
            </div>
        </SgModal>

    </AppLayout>
</template>

<style scoped>
.btn-sched { border-radius: 10px; font-weight: 700; color: #0e7d76 !important; border-color: #9ed9d4 !important; background: #f0fbfa !important; }
.btn-enroll { background: linear-gradient(135deg, #1d7fc9, #14a39a) !important; border: none !important; color: #fff !important; font-weight: 700; }
.btn-enroll:hover { filter: brightness(1.06); }
.mat { font-family: ui-monospace, 'Cascadia Mono', Consolas, monospace; font-weight: 700; color: #334155; font-size: 12.5px; }
.ct { display: inline-flex; align-items: center; gap: 6px; color: #334155; font-size: 12.5px; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ct .anticon { color: #1d7fc9; }
.ct:hover { color: #1d7fc9; }
.gd { display: flex; flex-direction: column; min-width: 0; line-height: 1.3; }
.gd b { font-size: 12.5px; font-weight: 600; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.gd small { font-size: 11.5px; color: #94a3b8; display: inline-flex; gap: 4px; align-items: center; }
.enr-pend { flex: none; margin-bottom: 10px; border: 1px solid #fde68a; background: #fffbeb; border-radius: 12px; padding: 8px 12px; max-height: 150px; overflow-y: auto; }
.enr-pend__head { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: #b45309; margin-bottom: 6px; }
.enr-pend__row { display: flex; align-items: center; gap: 10px; padding: 6px 0; border-top: 1px dashed #fde68a; }
.enr-pend__row:first-of-type { border-top: none; }
.enr-pend__who { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.enr-pend__who b { color: #0b2f55; font-size: 13px; }
.enr-pend__who small { color: #94a3b8; font-size: 11.5px; }
.enr-note { display: flex; gap: 8px; align-items: flex-start; padding: 10px 12px; border-radius: 10px; background: #f0f7fd; color: #0f5a99; font-size: 12.5px; }
</style>
