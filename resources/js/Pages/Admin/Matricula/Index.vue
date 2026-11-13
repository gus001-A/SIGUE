<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    SolutionOutlined, PlusOutlined, CheckOutlined, SwapOutlined, StopOutlined, RiseOutlined, TrophyOutlined, ClockCircleOutlined,
    CheckCircleOutlined, UserOutlined, AppstoreOutlined, ReadOutlined, CalendarOutlined, FlagOutlined, SettingOutlined, NumberOutlined,
    ArrowRightOutlined, FileTextOutlined, SafetyCertificateOutlined
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHead from '@/Components/PageHead.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusTag from '@/Components/StatusTag.vue';
import SgTable from '@/Components/SgTable.vue';
import SgModal from '@/Components/SgModal.vue';
import { confirmAction } from '@/lib/notify';

const props = defineProps({ items: Array, filters: Object, stats: Object, options: Object, can: Object });
const filterOption = (input, opt) => opt.label.toLowerCase().includes(input.toLowerCase());
const changeYear = (v) => router.get(route('matricula.index'), { school_year_id: v }, { preserveScroll: true });

// Inscripción
const enOpen = ref(false);
const en = useForm({ student_id: null, group_id: null, type: 'inscripcion', notes: '', authorize: props.can.authorize });
const enroll = () => en.post(route('matricula.store'), { preserveScroll: true, onSuccess: () => { enOpen.value = false; en.reset(); } });

// Baja
const dropOpen = ref(false); const dropRow = ref(null);
const dr = useForm({ drop_reason: '', student_status: true });
const doDrop = () => dr.post(route('matricula.drop', dropRow.value.id), { preserveScroll: true, onSuccess: () => (dropOpen.value = false) });

// Cambio de grupo: solo grupos del MISMO grado
const chOpen = ref(false); const chRow = ref(null);
const ch = useForm({ group_id: null, reason: '' });
const sameGrade = computed(() => props.options.groups.filter((g) => g.grade_id === chRow.value?.grade_id && g.value !== chRow.value?.group_id));
const doChange = () => ch.post(route('matricula.change', chRow.value.id), { preserveScroll: true, onSuccess: () => (chOpen.value = false) });

// Reinscripción masiva y egreso
const prOpen = ref(false);
const pr = useForm({ from_group_id: null, to_group_id: null });
const promote = () => pr.post(route('matricula.promote'), { preserveScroll: true, onSuccess: () => (prOpen.value = false) });
const grOpen = ref(false);
const grGroup = ref(null);
const graduate = () => confirmAction({ title: '¿Registrar egreso del grupo?', content: 'Los alumnos con inscripción autorizada pasarán a estado "Egresado".', tone: 'warn', okText: 'Sí, registrar egreso',
    onOk: () => router.post(route('matricula.graduate', grGroup.value), {}, { preserveScroll: true, onSuccess: () => (grOpen.value = false) }) });

const authorize = (r) => confirmAction({ title: '¿Autorizar inscripción?', content: `${r.student} · ${r.group}`, tone: 'success', okText: 'Sí, autorizar',
    onOk: () => router.post(route('matricula.authorize', r.id), {}, { preserveScroll: true }) });

const TYPES = [{ value: 'inscripcion', label: 'Inscripción' }, { value: 'reinscripcion', label: 'Reinscripción' }];

// --- COLUMNAS REACOMODADAS ---
const columns = [
    { title: 'Matrícula', key: 'enrollment_number', icon: NumberOutlined, width: 130 },
    { title: 'Alumno', key: 'student', icon: UserOutlined, filterValue: (r) => `${r.student} ${r.enrollment_number ?? ''}` },
    { title: 'Tipo', key: 'type', dataIndex: 'type', icon: FileTextOutlined, filter: 'select', filterOptions: TYPES, width: 140 },
    { title: 'Grado', key: 'level', icon: ReadOutlined, filter: 'select', width: 150 }, 
    { title: 'Grupo', key: 'group', dataIndex: 'group', icon: AppstoreOutlined, filter: 'select', width: 120 },
    { title: 'Estado', key: 'status', dataIndex: 'status', icon: FlagOutlined, filter: 'select', filterOptions: props.options.statuses, width: 140 },
    { title: 'Autorizó / Fecha', key: 'audit', icon: SafetyCertificateOutlined, width: 200 },
    { title: 'Acciones', key: 'actions', icon: SettingOutlined, width: 150, align: 'center' },
];
</script>

<template>
    <AppLayout title="Matriculación">
        <PageHead title="Matriculación" subtitle="Inscripciones, reinscripciones, bajas, cambios de grupo y egresos" :icon="SolutionOutlined">
            <template #actions>
                <!-- Selector más ancho (width: 240px) -->
                <a-select :value="filters.school_year_id" :options="options.schoolYears" style="width: 240px" size="large" @change="changeYear" />
                <a-button size="large" @click="prOpen = true"><template #icon><RiseOutlined style="color: #14a39a" /></template>Reinscripción masiva</a-button>
                <a-button v-if="can.authorize" size="large" @click="grOpen = true"><template #icon><TrophyOutlined style="color: #7c4ddb" /></template>Egreso</a-button>
                <a-button type="primary" size="large" @click="enOpen = true"><template #icon><PlusOutlined /></template>Inscribir</a-button>
            </template>
        </PageHead>

        <div class="sg-stats">
            <StatCard label="Autorizadas" :value="stats.autorizadas" :icon="CheckCircleOutlined" color="green" />
            <StatCard label="Pendientes" :value="stats.pendientes" :icon="ClockCircleOutlined" color="amber" />
            <StatCard label="Reinscripciones" :value="stats.reinscripciones" :icon="RiseOutlined" color="violet" />
            <StatCard label="Bajas" :value="stats.bajas" :icon="StopOutlined" color="red" />
        </div>

        <a-card :bordered="false" class="sg-fill">
            <SgTable :columns="columns" :data-source="items" unit="movimientos">
                <template #bodyCell="{ column, record }">
                    
                    <!-- 1. Matrícula -->
                    <template v-if="column.key === 'enrollment_number'">
                        <span class="matricula">{{ record.enrollment_number || '—' }}</span>
                    </template>

                    <!-- 2. Alumno -->
                    <template v-else-if="column.key === 'student'">
                        <Link :href="route('alumnos.show', record.student_id)" class="sg-who">
                            <span class="sg-av"><UserOutlined /></span>
                            <span><b>{{ record.student }}</b></span>
                        </Link>
                    </template>

                    <!-- 3. Tipo -->
                    <template v-else-if="column.key === 'type'">
                        <span class="sg-chip" :style="{ '--c': record.type === 'inscripcion' ? '#1d7fc9' : '#7c4ddb' }">
                            {{ record.type === 'inscripcion' ? 'Inscripción' : 'Reinscripción' }}
                        </span>
                    </template>

                    <!-- 4. Grado (Nivel Educativo) -->
                    <template v-else-if="column.key === 'level'">
                        <span class="sg-chip" style="--c: #f39200">
                            <ReadOutlined /> {{ record.level || record.grade_level || 'Secundaria' }}
                        </span>
                    </template>

                    <!-- 5. Grupo -->
                    <template v-else-if="column.key === 'group'">
                        <span class="sg-chip" style="--c: #14a39a"><AppstoreOutlined /> {{ record.group }}</span>
                    </template>

                    <!-- 6. Estado -->
                    <template v-else-if="column.key === 'status'">
                        <StatusTag :status="record.status" />
                        <div v-if="record.drop_reason" class="sg-cell-sub" style="color:#e3262b">{{ record.drop_reason }}</div>
                    </template>

                    <!-- 7. Autorizó / Fecha -->
                    <template v-else-if="column.key === 'audit'">
                        <div class="audit-cell">
                            <span v-if="record.authorized_by" class="audit-name">
                                <SafetyCertificateOutlined /> {{ record.authorized_by }}
                            </span>
                            <span v-else class="audit-name muted">
                                <ClockCircleOutlined /> Pendiente
                            </span>
                            <span class="audit-date">
                                <CalendarOutlined /> {{ record.enrolled_on }}
                            </span>
                        </div>
                    </template>

                    <!-- 8. Acciones -->
                    <template v-else-if="column.key === 'actions'">
                        <div class="sg-actions">
                            <a-tooltip v-if="record.status === 'pendiente' && can.authorize" title="Autorizar"><a-button class="sg-ab green" @click="authorize(record)"><CheckOutlined /></a-button></a-tooltip>
                            <a-tooltip v-if="record.status === 'autorizada'" title="Cambio de grupo (mismo grado)"><a-button class="sg-ab blue" @click="chRow = record; ch.reset(); ch.clearErrors(); chOpen = true"><SwapOutlined /></a-button></a-tooltip>
                            <a-tooltip v-if="['pendiente', 'autorizada'].includes(record.status) && can.authorize" title="Dar de baja"><a-button class="sg-ab red" @click="dropRow = record; dr.reset(); dropOpen = true"><StopOutlined /></a-button></a-tooltip>
                        </div>
                    </template>
                </template>
            </SgTable>
        </a-card>

        <!-- MODALES -->
        <SgModal v-model:open="enOpen" title="Inscripción / reinscripción" subtitle="El alumno debe estar registrado en el plantel activo" :icon="SolutionOutlined"
            width="min(760px, 96vw)" ok-text="Registrar" :loading="en.processing" @ok="enroll">
            <a-form layout="vertical" class="sg-grid-2">
                <a-form-item label="Alumno" required :validate-status="en.errors.student_id ? 'error' : undefined" :help="en.errors.student_id">
                    <a-select v-model:value="en.student_id" show-search :filter-option="filterOption" :options="options.students" placeholder="Busca por nombre o matrícula" />
                </a-form-item>
                <a-form-item label="Grupo" required :validate-status="en.errors.group_id ? 'error' : undefined" :help="en.errors.group_id">
                    <a-select v-model:value="en.group_id" show-search option-filter-prop="label" :options="options.groups" />
                </a-form-item>
                <a-form-item label="Tipo"><a-segmented v-model:value="en.type" :options="TYPES" block /></a-form-item>
                <a-form-item label="Autorización">
                    <a-checkbox v-if="can.authorize" v-model:checked="en.authorize"><CheckCircleOutlined style="color: #2e9b43" /> Autorizar de inmediato</a-checkbox>
                    <span v-else class="sg-muted">Quedará pendiente de autorización</span>
                </a-form-item>
                <a-form-item label="Notas" class="full"><a-textarea v-model:value="en.notes" :rows="2" /></a-form-item>
            </a-form>
        </SgModal>

        <SgModal v-model:open="dropOpen" title="Registrar baja" :subtitle="`${dropRow?.student} · ${dropRow?.group}`" :icon="StopOutlined" color="linear-gradient(135deg, #e3262b, #b71c21)"
            width="min(620px, 96vw)" ok-text="Dar de baja" :ok-icon="StopOutlined" ok-danger :loading="dr.processing" @ok="doDrop">
            <a-form layout="vertical">
                <a-form-item label="Motivo" required :validate-status="dr.errors.drop_reason ? 'error' : undefined" :help="dr.errors.drop_reason"><a-textarea v-model:value="dr.drop_reason" :rows="3" /></a-form-item>
                <a-checkbox v-model:checked="dr.student_status">Cambiar el estado del alumno a "Baja"</a-checkbox>
            </a-form>
        </SgModal>

        <SgModal v-model:open="chOpen" title="Cambio de grupo" :subtitle="`${chRow?.student} · actualmente en ${chRow?.group}`" :icon="SwapOutlined"
            width="min(640px, 96vw)" ok-text="Aplicar cambio" :ok-icon="SwapOutlined" :loading="ch.processing" @ok="doChange">
            <a-alert type="info" show-icon style="margin-bottom: 14px" :message="`Solo puedes cambiarlo a otro grupo de ${chRow?.grade ?? 'su mismo grado'}. Para cambiar de grado usa la reinscripción.`" />
            <a-form layout="vertical" class="sg-grid-2">
                <a-form-item label="Nuevo grupo" required :validate-status="ch.errors.group_id ? 'error' : undefined" :help="ch.errors.group_id">
                    <a-select v-model:value="ch.group_id" :options="sameGrade" :not-found-content="'No hay otro grupo de ' + (chRow?.grade ?? 'este grado')" />
                </a-form-item>
                <a-form-item label="Motivo"><a-input v-model:value="ch.reason" /></a-form-item>
            </a-form>
        </SgModal>

        <SgModal v-model:open="prOpen" title="Reinscripción masiva" subtitle="Pasa a todos los alumnos activos de un grupo al grupo del siguiente grado" :icon="RiseOutlined"
            color="linear-gradient(135deg, #14a39a, #2e9b43)" width="min(720px, 96vw)" ok-text="Generar reinscripciones" :ok-icon="RiseOutlined" :loading="pr.processing" @ok="promote">
            <a-form layout="vertical" class="promo">
                <a-form-item label="Grupo origen" :validate-status="pr.errors.from_group_id ? 'error' : undefined" :help="pr.errors.from_group_id"><a-select v-model:value="pr.from_group_id" :options="options.groups" /></a-form-item>
                <ArrowRightOutlined class="promo__arrow" />
                <a-form-item label="Grupo destino" :validate-status="pr.errors.to_group_id ? 'error' : undefined" :help="pr.errors.to_group_id"><a-select v-model:value="pr.to_group_id" :options="options.groups" /></a-form-item>
            </a-form>
            <p class="sg-muted" style="margin: 0">Para pasar a otro ciclo, cambia primero el ciclo escolar en el selector de la página. Las reinscripciones quedan pendientes de autorización.</p>
        </SgModal>

        <SgModal v-model:open="grOpen" title="Egreso de grupo" subtitle="Concluye las inscripciones y marca a los alumnos como egresados" :icon="TrophyOutlined"
            color="linear-gradient(135deg, #7c4ddb, #1d7fc9)" width="min(560px, 96vw)" ok-text="Registrar egreso" :ok-icon="TrophyOutlined" :ok-disabled="!grGroup" @ok="graduate">
            <a-form layout="vertical"><a-form-item label="Grupo que egresa"><a-select v-model:value="grGroup" :options="options.groups" /></a-form-item></a-form>
        </SgModal>
    </AppLayout>
</template>

<style scoped>
/* Estilo para la matrícula */
.matricula {
    font-family: 'Courier New', Courier, monospace;
    font-weight: 700;
    color: #334155;
    background: #f1f5f9;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 13px;
}

/* Estilo para la columna de auditoría (Autorizó / Fecha) */
.audit-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 12.5px;
}
.audit-name {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
    color: #1d7fc9;
}
.audit-name.muted {
    color: #94a3b8;
    font-weight: 500;
}
.audit-date {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #64748b;
}

/* Promo layout */
.promo { display: grid; grid-template-columns: 1fr auto 1fr; gap: 12px; align-items: center; }
.promo__arrow { font-size: 22px; color: #14a39a; margin-top: 8px; }
</style>