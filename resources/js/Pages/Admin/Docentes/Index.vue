<script setup>
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import {
    UserOutlined, PlusOutlined, CheckCircleOutlined, WarningOutlined, IdcardOutlined, NumberOutlined, ReadOutlined,
    BookOutlined, FlagOutlined, SettingOutlined, EditOutlined, DeleteOutlined, MailOutlined, PhoneOutlined, CalendarOutlined,
    KeyOutlined, SolutionOutlined, CheckCircleFilled, ClockCircleOutlined, StopOutlined, SafetyCertificateOutlined, BankOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHead from '@/Components/PageHead.vue';
import StatCard from '@/Components/StatCard.vue';
import SgTable from '@/Components/SgTable.vue';
import SgModal from '@/Components/SgModal.vue';
import DateField from '@/Components/DateField.vue';
import { confirmDelete } from '@/lib/notify';

const props = defineProps({ items: Array, options: Object, stats: Object, filters: Object });
const canEdit = usePage().props.auth.permissions.includes('docentes.editar');
const STATUSES = [{ value: 'activo', label: 'Activo' }, { value: 'inactivo', label: 'Inactivo' }, { value: 'licencia', label: 'Licencia' }];

// Mapeo de estado (igual al de incidencias)
const STATUS_MAP = {
    activo: { icon: CheckCircleFilled, color: '#2e9b43', bg: '#ecfdf3', label: 'Activo' },
    inactivo: { icon: StopOutlined, color: '#e3262b', bg: '#fef2f2', label: 'Inactivo' },
    licencia: { icon: ClockCircleOutlined, color: '#f39200', bg: '#fff8eb', label: 'Licencia' }
};

const open = ref(false);
const editing = ref(null);
const blank = { employee_number: '', name: '', paternal_surname: '', maternal_surname: '', email: '', phone: '', curp: '', rfc: '', specialty: '', hired_on: null, status: 'activo',
    gender: null, degree: null, career: '', alma_mater: '', professional_license: '', create_account: true };
const GENDERS = [{ value: 'F', label: 'Mujer' }, { value: 'M', label: 'Hombre' }, { value: 'X', label: 'Prefiere no decir' }];
const DEGREE_COLOR = { doctorado: '#7c4ddb', maestria: '#1d7fc9', especialidad: '#14a39a', licenciatura: '#2e9b43', ingenieria: '#2e9b43', normal: '#f39200', tecnico: '#e0457b', bachillerato: '#64748b' };
const form = useForm({ ...blank });
const err = (k) => ({ 'validate-status': form.errors[k] ? 'error' : undefined, help: form.errors[k] });

/** Vista previa del nombre con título (Dr., Mtra., Lic.…) igual que en el servidor. */
const TITLES = { tecnico: ['T.S.U.', 'T.S.U.', 'T.S.U.'], normal: ['Profr.', 'Profra.', 'Prof.'], licenciatura: ['Lic.', 'Lic.', 'Lic.'], ingenieria: ['Ing.', 'Ing.', 'Ing.'],
    especialidad: ['Esp.', 'Esp.', 'Esp.'], maestria: ['Mtro.', 'Mtra.', 'Mtro(a).'], doctorado: ['Dr.', 'Dra.', 'Dr(a).'] };
const previewName = computed(() => {
    const t = TITLES[form.degree];
    const title = t ? (form.gender === 'M' ? t[0] : form.gender === 'F' ? t[1] : t[2]) : '';
    return [title, form.name, form.paternal_surname, form.maternal_surname].filter(Boolean).join(' ') || '—';
});
function create() { editing.value = null; Object.assign(form, blank); form.clearErrors(); open.value = true; }
function edit(r) { editing.value = r; Object.assign(form, { ...blank, ...r, create_account: false }); form.clearErrors(); open.value = true; }
function submit() {
    const o = { preserveScroll: true, onSuccess: () => (open.value = false) };
    editing.value ? form.put(route('docentes.update', editing.value.id), o) : form.post(route('docentes.store'), o);
}
const remove = (r) => confirmDelete({ title: '¿Eliminar docente?', content: `${r.full_name}. Si tiene clases asignadas solo se marcará como inactivo.`, onOk: () => router.delete(route('docentes.destroy', r.id), { preserveScroll: true }) });

// --- COLUMNAS ---
const columns = [
    { title: 'Docente', key: 'name', icon: UserOutlined, filterValue: (r) => `${r.display_name} ${r.employee_number ?? ''} ${r.email ?? ''} ${r.phone ?? ''}` },
    { title: 'Formación', key: 'degree', icon: SolutionOutlined, width: 250, filter: 'select', filterValue: (r) => r.degree_label ?? 'Sin registrar' },
    { title: 'Carga académica', key: 'subjects', icon: BookOutlined, width: 280, filterValue: (r) => (r.subjects.length ? r.subjects.map((s) => `${s.name} ${s.groups.join(' ')}`).join(' ') : 'Sin materias') },
    { title: 'Estado', key: 'status', icon: FlagOutlined, width: 130, filter: 'select', filterValue: (r) => STATUS_MAP[r.status]?.label ?? r.status },
    { title: 'Acceso', key: 'account', icon: KeyOutlined, width: 165, filter: 'select', filterValue: (r) => (r.has_account ? 'Con cuenta' : 'Sin cuenta') },
    { title: 'Acciones', key: 'actions', icon: SettingOutlined, width: 100, align: 'center' },
];
</script>

<template>
    <AppLayout title="Docentes">
        <PageHead title="Docentes" subtitle="Plantilla docente del plantel, cuentas de acceso y carga académica" :icon="UserOutlined">
            <template #actions><a-button v-if="canEdit" type="primary" size="large" @click="create"><template #icon><PlusOutlined /></template>Nuevo docente</a-button></template>
        </PageHead>

        <div class="sg-stats">
            <StatCard label="Docentes" :value="stats.total" :icon="UserOutlined" color="purple" />
            <StatCard label="Activos" :value="stats.activos" :icon="CheckCircleOutlined" color="green" />
            <StatCard label="Activos sin clases" :value="stats.sin_clases" :icon="WarningOutlined" color="amber" />
        </div>

        <a-card :bordered="false" class="sg-fill">
            <SgTable :columns="columns" :data-source="items" unit="docentes">
                <template #bodyCell="{ column, record }">
                    <!-- Docente -->
                    <template v-if="column.key === 'name'">
                        <div class="tch" :style="{ '--c': DEGREE_COLOR[record.degree] ?? '#7c4ddb' }">
                            <span class="tch__av">{{ record.name?.[0] }}{{ record.paternal_surname?.[0] }}<i v-if="record.title">{{ record.title }}</i></span>
                            <span class="tch__txt">
                                <b :title="record.display_name">{{ record.display_name }}</b>
                                <span class="tch__meta">
                                    <span class="emp"><NumberOutlined /> {{ record.employee_number || 'S/N' }}</span>
                                    <span v-if="record.email"><MailOutlined /> {{ record.email }}</span>
                                    <span v-if="record.phone"><PhoneOutlined /> {{ record.phone }}</span>
                                </span>
                            </span>
                        </div>
                    </template>

                    <!-- Formación -->
                    <template v-else-if="column.key === 'degree'">
                        <div v-if="record.degree" class="edu" :style="{ '--c': DEGREE_COLOR[record.degree] }">
                            <span class="edu__deg"><SolutionOutlined /> {{ record.degree_label }}</span>
                            <small v-if="record.career" :title="record.career">{{ record.career }}</small>
                            <small v-if="record.alma_mater" class="edu__uni" :title="record.alma_mater"><BankOutlined /> {{ record.alma_mater }}</small>
                        </div>
                        <span v-else class="sg-faint">Sin registrar</span>
                    </template>

                    <!-- Carga académica -->
                    <template v-else-if="column.key === 'subjects'">
                        <div v-if="record.subjects.length" class="load">
                            <span class="load__n"><b>{{ record.classes_count }}</b> clase{{ record.classes_count === 1 ? '' : 's' }}</span>
                            <a-tooltip v-for="s in record.subjects" :key="s.name" :title="`Grupos: ${s.groups.join(', ')}`">
                                <span class="subj"><BookOutlined /> {{ s.name }} <i>{{ s.groups.join(' · ') }}</i></span>
                            </a-tooltip>
                        </div>
                        <span v-else class="sg-badge" style="--c: #f39200">Sin materias asignadas</span>
                    </template>

                    <!-- Estado -->
                    <template v-else-if="column.key === 'status'">
                        <span class="sg-badge" :style="{ '--c': STATUS_MAP[record.status]?.color }">{{ STATUS_MAP[record.status]?.label ?? record.status }}</span>
                    </template>

                    <!-- Acceso -->
                    <template v-else-if="column.key === 'account'">
                        <div class="acc">
                            <span class="sg-badge" :style="{ '--c': record.has_account ? '#1d7fc9' : '#94a3b8' }">{{ record.has_account ? 'Con cuenta' : 'Sin cuenta' }}</span>
                            <small v-if="record.has_account">{{ record.last_login ? `Último acceso ${record.last_login}` : 'Aún no ingresa' }}</small>
                        </div>
                    </template>

                    <!-- Acciones -->
                    <template v-else-if="column.key === 'actions'">
                        <div v-if="canEdit" class="sg-actions">
                            <a-tooltip title="Editar"><a-button class="sg-ab navy" @click="edit(record)"><EditOutlined /></a-button></a-tooltip>
                            <a-tooltip title="Eliminar"><a-button class="sg-ab red" @click="remove(record)"><DeleteOutlined /></a-button></a-tooltip>
                        </div>
                    </template>
                </template>
            </SgTable>
        </a-card>

        <SgModal v-model:open="open" :title="editing ? 'Editar docente' : 'Nuevo docente'" :subtitle="editing ? editing.full_name : 'Se registra en el plantel activo'"
            :icon="UserOutlined" color="linear-gradient(135deg, #7c4ddb, #1d7fc9)" width="min(1080px, 96vw)" :loading="form.processing" @ok="submit">
            <a-form layout="vertical" class="sg-grid-4">
                <div class="sg-sec full" style="--c: #7c4ddb"><UserOutlined /> Datos personales y laborales</div>
                <a-form-item label="Nombre(s)" required v-bind="err('name')"><a-input v-model:value="form.name"><template #prefix><UserOutlined style="color: #7c4ddb" /></template></a-input></a-form-item>
                <a-form-item label="Apellido paterno" required v-bind="err('paternal_surname')"><a-input v-model:value="form.paternal_surname" /></a-form-item>
                <a-form-item label="Apellido materno" v-bind="err('maternal_surname')"><a-input v-model:value="form.maternal_surname" /></a-form-item>
                <a-form-item label="Sexo" v-bind="err('gender')"><a-select v-model:value="form.gender" allow-clear :options="GENDERS" placeholder="Para el título" /></a-form-item>
                <a-form-item label="CURP" v-bind="err('curp')"><a-input v-upper v-model:value="form.curp" :maxlength="18"><template #prefix><IdcardOutlined style="color: #f39200" /></template></a-input></a-form-item>
                <a-form-item label="RFC" v-bind="err('rfc')"><a-input v-upper v-model:value="form.rfc" :maxlength="13" /></a-form-item>
                <a-form-item label="No. empleado" v-bind="err('employee_number')"><a-input v-model:value="form.employee_number"><template #prefix><NumberOutlined style="color: #1d7fc9" /></template></a-input></a-form-item>
                <a-form-item label="Fecha de ingreso" v-bind="err('hired_on')"><DateField v-model:value="form.hired_on" /></a-form-item>
                <a-form-item label="Correo" v-bind="err('email')"><a-input v-model:value="form.email"><template #prefix><MailOutlined style="color: #e0457b" /></template></a-input></a-form-item>
                <a-form-item label="Teléfono" v-bind="err('phone')"><a-input v-digits="10" v-model:value="form.phone" placeholder="10 dígitos"><template #prefix><PhoneOutlined style="color: #2e9b43" /></template></a-input></a-form-item>
                <a-form-item label="Especialidad que imparte" v-bind="err('specialty')"><a-input v-model:value="form.specialty" placeholder="Matemáticas"><template #prefix><SolutionOutlined style="color: #14a39a" /></template></a-input></a-form-item>
                <a-form-item label="Estado" v-bind="err('status')"><a-select v-model:value="form.status" :options="STATUSES" /></a-form-item>

                <div class="sg-sec full" style="--c: #1d7fc9"><ReadOutlined /> Formación académica
                    <span v-if="form.degree || form.name" class="title-preview">Se mostrará como: <b>{{ previewName }}</b></span>
                </div>
                <a-form-item label="Grado de estudios" v-bind="err('degree')"><a-select v-model:value="form.degree" allow-clear :options="options.degrees" placeholder="Selecciona" /></a-form-item>
                <a-form-item label="Cédula profesional" v-bind="err('professional_license')"><a-input v-model:value="form.professional_license" :maxlength="20"><template #prefix><SafetyCertificateOutlined style="color: #2e9b43" /></template></a-input></a-form-item>
                <a-form-item label="Carrera" v-bind="err('career')"><a-input v-model:value="form.career" placeholder="Licenciatura en Matemáticas"><template #prefix><ReadOutlined style="color: #1d7fc9" /></template></a-input></a-form-item>
                <a-form-item label="Institución de egreso" v-bind="err('alma_mater')"><a-input v-model:value="form.alma_mater" placeholder="UNAM"><template #prefix><BankOutlined style="color: #0b3f6e" /></template></a-input></a-form-item>
            </a-form>
            <template #extra>
                <a-checkbox v-if="!editing || !editing.has_account" v-model:checked="form.create_account"><KeyOutlined style="color: #f39200" /> Crear cuenta de acceso (requiere correo)</a-checkbox>
            </template>
        </SgModal>
    </AppLayout>
</template>

<style scoped>
.title-preview { margin-left: auto; font-size: 12px; font-weight: 500; color: #64748b; text-transform: none; letter-spacing: 0; }
.title-preview b { color: #0b2f55; }

/* Docente */
.tch { display: flex; align-items: center; gap: 12px; min-width: 0; }
.tch__av { position: relative; width: 42px; height: 42px; border-radius: 12px; flex: none; display: grid; place-items: center; color: #fff; font-weight: 800; font-size: 14px;
    background: linear-gradient(135deg, var(--c), color-mix(in srgb, var(--c) 55%, #0b2f55)); box-shadow: 0 6px 14px -8px var(--c); }
.tch__av i { position: absolute; bottom: -6px; left: 50%; transform: translateX(-50%); font-style: normal; font-size: 9.5px; font-weight: 800; line-height: 14px;
    padding: 0 5px; border-radius: 6px; background: #fff; color: var(--c); border: 1px solid color-mix(in srgb, var(--c) 35%, #fff); white-space: nowrap; }
.tch__txt { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.tch__txt b { color: #0f172a; font-weight: 700; font-size: 13.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tch__meta { display: flex; gap: 4px 12px; flex-wrap: wrap; font-size: 11.5px; color: #64748b; }
.tch__meta span { display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }
.tch__meta .anticon { color: #94a3b8; }
.emp { font-family: ui-monospace, 'Cascadia Mono', Consolas, monospace; font-weight: 700; color: #334155 !important; }

/* Formación */
.edu { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.edu__deg { display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-size: 12.5px; color: var(--c); }
.edu small { font-size: 11.5px; color: #475569; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.edu__uni { color: #94a3b8 !important; }

/* Carga académica */
.load { display: flex; flex-direction: column; align-items: flex-start; gap: 3px; min-width: 0; }
.load__n { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .05em; }
.load__n b { color: #0b2f55; font-size: 12.5px; }
.subj { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 600; color: #0b3f6e; background: #fff; border: 1px solid #dbe7f3;
    border-radius: 8px; padding: 1px 8px; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.subj .anticon { color: #f39200; }
.subj i { font-style: normal; font-weight: 600; color: #94a3b8; }

/* Acceso */
.acc { display: flex; flex-direction: column; align-items: flex-start; gap: 3px; }
.acc small { font-size: 11px; color: #94a3b8; }
</style>
