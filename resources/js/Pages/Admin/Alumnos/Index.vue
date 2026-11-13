<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    TeamOutlined, PlusOutlined, UserOutlined, CheckCircleOutlined, ClockCircleOutlined, StopOutlined, TrophyOutlined,
    NumberOutlined, AppstoreOutlined, FlagOutlined, KeyOutlined, SettingOutlined, EyeOutlined, EditOutlined, IdcardOutlined,
    BankOutlined, ReadOutlined, FileExcelOutlined, DownloadOutlined, CloudUploadOutlined, CheckCircleFilled, CloseCircleFilled, ImportOutlined, MailOutlined, PhoneOutlined,
} from '@ant-design/icons-vue';
import SgModal from '@/Components/SgModal.vue';
import FileInput from '@/Components/FileInput.vue';
import { message } from '@/lib/notify';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHead from '@/Components/PageHead.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusTag from '@/Components/StatusTag.vue';
import SgTable from '@/Components/SgTable.vue';
import AlumnoModal from '@/Components/AlumnoModal.vue';
import { statusLabel } from '@/lib/format';

const props = defineProps({ students: Array, stats: Object, options: Object, openCreate: Boolean, filters: Object });
const can = (p) => usePage().props.auth.permissions.includes(p);
const modal = ref(props.openCreate);

// ---------- Importación masiva desde Excel (solo administrador o superadministrador) ----------
const roles = computed(() => (usePage().props.auth.user.roles ?? []).map((r) => r.slug));
const canImport = computed(() => roles.value.includes('admin') || roles.value.includes('superadmin'));
const imp = ref({ open: false, file: null, step: 1, loading: false, rows: [], valid: 0, invalid: 0, authorize: true, onlyErrors: false });
function openImport() { imp.value = { open: true, file: null, step: 1, loading: false, rows: [], valid: 0, invalid: 0, authorize: true, onlyErrors: false }; }
async function review() {
    if (!imp.value.file) { message.warning('Selecciona el archivo de Excel.'); return; }
    imp.value.loading = true;
    try {
        const fd = new FormData(); fd.append('file', imp.value.file);
        const { data } = await axios.post(route('alumnos.importar.revisar'), fd, { headers: { Accept: 'application/json' } });
        Object.assign(imp.value, { rows: data.rows, valid: data.valid, invalid: data.invalid, step: 2 });
    } catch (e) {
        message.error(e.response?.data?.errors?.file?.[0] || e.response?.data?.message || 'No se pudo leer el archivo.');
    } finally { imp.value.loading = false; }
}
function runImport() {
    if (imp.value.step === 1) return review();
    if (!imp.value.valid) { message.warning('No hay filas válidas para importar.'); return; }
    imp.value.loading = true;
    router.post(route('alumnos.importar'), { authorize: imp.value.authorize }, { onFinish: () => (imp.value.loading = false), onSuccess: () => (imp.value.open = false) });
}
const impRows = computed(() => imp.value.rows.filter((r) => !imp.value.onlyErrors || !r.ok));

// --- COLUMNAS REACOMODADAS ---
const columns = [
    { title: 'Matrícula', key: 'enrollment_number', dataIndex: 'enrollment_number', icon: NumberOutlined, width: 130 },
    { title: 'Nombre completo', key: 'name', icon: UserOutlined, filterValue: (r) => `${r.list_name} ${r.curp ?? ''}` },
    { title: 'Correo', key: 'email', dataIndex: 'email', icon: MailOutlined, width: 230 },
    { title: 'Teléfono', key: 'phone', dataIndex: 'phone', icon: PhoneOutlined, width: 135 },
    { title: 'Grado (Nivel)', key: 'grade', icon: IdcardOutlined, filter: 'select', width: 170, filterValue: (r) => [r.grade, r.level].filter(Boolean).join(' · ') },
    { title: 'Grupo', key: 'group', dataIndex: 'group', icon: AppstoreOutlined, filter: 'select', width: 120 },
    { title: 'Cuenta', key: 'account', icon: KeyOutlined, filter: 'select', width: 130, filterValue: (r) => (r.has_account ? 'Con cuenta' : 'Sin cuenta') },
    { title: 'Acciones', key: 'actions', icon: SettingOutlined, width: 110, align: 'center' },
];
</script>

<template>
    <AppLayout title="Alumnos">
        <PageHead title="Alumnos" subtitle="Expedientes personales, académicos, de salud y financieros" :icon="TeamOutlined">
            <template #actions>
                <a-button v-if="canImport" size="large" class="btn-import" @click="openImport"><template #icon><FileExcelOutlined /></template>Importar desde Excel</a-button>
                <a-button v-if="can('alumnos.editar')" type="primary" size="large" @click="modal = true"><template #icon><PlusOutlined /></template>Nuevo alumno</a-button>
            </template>
        </PageHead>

        <div class="sg-stats">
            <StatCard label="Expedientes" :value="stats.total" :icon="TeamOutlined" color="indigo" />
            <StatCard label="Activos" :value="stats.activos" :icon="CheckCircleOutlined" color="green" />
            <StatCard label="Aspirantes" :value="stats.aspirantes" :icon="ClockCircleOutlined" color="amber" />
            <StatCard label="Bajas" :value="stats.bajas" :icon="StopOutlined" color="red" />
            <StatCard label="Egresados" :value="stats.egresados" :icon="TrophyOutlined" color="purple" />
        </div>

        <a-card :bordered="false" class="sg-fill">
            <SgTable :columns="columns" :data-source="students" unit="alumnos"
                :custom-row="(r) => ({ onDblclick: () => router.visit(route('alumnos.show', r.id)) })">
                <template #bodyCell="{ column, record }">
                    
                    <!-- 1. Matrícula -->
                    <template v-if="column.key === 'enrollment_number'">
                        <span class="matricula">{{ record.enrollment_number || '—' }}</span>
                    </template>

                    <template v-else-if="column.key === 'email'">
                        <a v-if="record.email" :href="`mailto:${record.email}`" class="ct"><MailOutlined /> {{ record.email }}</a>
                        <span v-else class="sg-faint">Sin correo</span>
                    </template>
                    <template v-else-if="column.key === 'phone'">
                        <a v-if="record.phone" :href="`tel:${record.phone}`" class="ct"><PhoneOutlined /> {{ record.phone }}</a>
                        <span v-else class="sg-faint">Sin teléfono</span>
                    </template>

                    <!-- 3. Nombre Completo -->
                    <template v-else-if="column.key === 'name'">
                        <Link :href="route('alumnos.show', record.id)" class="sg-who">
                            <span class="sg-av"><img v-if="record.photo_url" :src="record.photo_url" alt="" /><UserOutlined v-else /></span>
                            <span>
                                <b>{{ record.list_name }}</b>
                                <small>{{ record.curp || 'Sin CURP' }}</small>
                            </span>
                        </Link>
                    </template>

                    <!-- 4. Grado (Nivel Educativo) -->
                    <template v-else-if="column.key === 'grade'">
                        <span class="sg-chip" style="--c: #1d7fc9">
                            <BankOutlined /> {{ [record.grade, record.level].filter(Boolean).join(' · ') || 'Sin grado' }}
                        </span>
                    </template>

                    <!-- 5. Grupo -->
                    <template v-else-if="column.key === 'group'">
                        <span v-if="record.group" class="sg-chip" style="--c: #14a39a">
                            <AppstoreOutlined /> {{ record.group }}
                        </span>
                        <span v-else class="sg-faint">Sin grupo</span>
                    </template>

                    <!-- 6. Cuenta -->
                    <template v-else-if="column.key === 'account'">
                        <span class="sg-chip" :style="{ '--c': record.has_account ? '#2e9b43' : '#94a3b8' }">
                            <KeyOutlined /> {{ record.has_account ? 'Con cuenta' : 'Sin cuenta' }}
                        </span>
                    </template>

                    <!-- 7. Acciones -->
                    <template v-else-if="column.key === 'actions'">
                        <div class="cell-actions">
                            <a-tooltip title="Ver expediente">
                                <a-button type="text" size="small" class="btn-action btn-view" @click.stop="router.visit(route('alumnos.show', record.id))">
                                    <EyeOutlined />
                                </a-button>
                            </a-tooltip>
                            <a-tooltip v-if="can('alumnos.editar')" title="Editar">
                                <a-button type="text" size="small" class="btn-action btn-edit" @click.stop="router.visit(route('alumnos.show', { student: record.id, editar: 1 }))">
                                    <EditOutlined />
                                </a-button>
                            </a-tooltip>
                        </div>
                    </template>
                </template>
            </SgTable>
        </a-card>

        <AlumnoModal v-model:open="modal" :options="options" />

        <!-- Importación masiva -->
        <SgModal v-model:open="imp.open" title="Importar alumnos desde Excel" :subtitle="imp.step === 1 ? 'Paso 1 de 2 · sube el archivo con la plantilla de SIGUE' : 'Paso 2 de 2 · revisa y confirma'"
            :icon="FileExcelOutlined" color="linear-gradient(135deg, #2e9b43, #14a39a)" :width="imp.step === 1 ? 'min(760px, 96vw)' : 'min(1080px, 96vw)'"
            :ok-text="imp.step === 1 ? 'Revisar archivo' : `Importar ${imp.valid} alumno(s)`" :ok-icon="imp.step === 1 ? CloudUploadOutlined : ImportOutlined"
            :ok-disabled="imp.step === 2 && !imp.valid" :loading="imp.loading" @ok="runImport">
            <template v-if="imp.step === 1">
                <div class="imp-steps">
                    <div class="imp-step"><span>1</span><div><b>Descarga la plantilla</b><small>Tiene las columnas y una fila de ejemplo. Los campos con * son obligatorios.</small>
                        <a :href="route('alumnos.importar.plantilla')"><a-button><template #icon><DownloadOutlined /></template>Descargar plantilla .xlsx</a-button></a></div></div>
                    <div class="imp-step"><span>2</span><div><b>Llénala con tus alumnos</b><small>Una fila por alumno. El grupo se escribe como aparece en SIGUE (p. ej. «1° A»). Si incluyes al tutor, se crea y se vincula.</small></div></div>
                    <div class="imp-step"><span>3</span><div><b>Súbela aquí</b><small>Se revisa fila por fila antes de guardar nada.</small></div></div>
                </div>
                <FileInput v-model="imp.file" accept=".xlsx,.xls,.csv" hint="Excel (.xlsx) o CSV · máx. 10 MB" />
            </template>
            <template v-else>
                <div class="imp-sum">
                    <span style="--c: #2e9b43"><CheckCircleFilled /> <b>{{ imp.valid }}</b> listas para importar</span>
                    <span v-if="imp.invalid" style="--c: #e3262b"><CloseCircleFilled /> <b>{{ imp.invalid }}</b> con errores (no se importarán)</span>
                    <a-checkbox v-if="imp.invalid" v-model:checked="imp.onlyErrors">Ver solo las de error</a-checkbox>
                    <a class="imp-back" @click="imp.step = 1">Cambiar archivo</a>
                </div>
                <div class="imp-table">
                    <table>
                        <thead><tr><th>Fila</th><th>Alumno</th><th>CURP</th><th>Grupo</th><th>Tutor</th><th>Estado</th></tr></thead>
                        <tbody>
                            <tr v-for="r in impRows" :key="r.row" :class="{ bad: !r.ok }">
                                <td>{{ r.row }}</td><td><b>{{ r.name }}</b></td><td>{{ r.curp || '—' }}</td><td>{{ r.group || '—' }}</td><td>{{ r.guardian || '—' }}</td>
                                <td><span v-if="r.ok" class="ok"><CheckCircleFilled /> Lista</span><span v-else class="err"><CloseCircleFilled /> {{ r.errors.join(' · ') }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
            <template #extra><a-checkbox v-if="imp.step === 2" v-model:checked="imp.authorize">Autorizar las inscripciones a grupo de inmediato</a-checkbox></template>
        </SgModal>
    </AppLayout>
</template>

<style scoped>
.ct { display: inline-flex; align-items: center; gap: 6px; color: #334155; font-size: 12.5px; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ct .anticon { color: #1d7fc9; }
.ct:hover { color: #1d7fc9; }
.btn-import { border-radius: 10px; font-weight: 600; color: #1f7a32 !important; border-color: #b7e4c4 !important; background: #f3fbf5 !important; }
.imp-steps { display: flex; flex-direction: column; gap: 10px; margin-bottom: 14px; }
.imp-step { display: flex; gap: 12px; align-items: flex-start; padding: 10px 12px; border-radius: 12px; background: #f8fafc; border: 1px solid #eef2f7; }
.imp-step > span { width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; color: #fff; font-weight: 800; background: linear-gradient(135deg, #2e9b43, #14a39a); flex: none; }
.imp-step > div { display: flex; flex-direction: column; gap: 4px; align-items: flex-start; }
.imp-step b { color: #0b2f55; }
.imp-step small { color: #64748b; font-size: 12.5px; }
.imp-sum { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 10px; }
.imp-sum > span { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 999px; font-size: 13px; color: var(--c); background: color-mix(in srgb, var(--c) 9%, #fff); }
.imp-back { margin-left: auto; font-size: 12.5px; }
.imp-table { max-height: 52vh; overflow: auto; border: 1px solid #e2e8f0; border-radius: 12px; }
.imp-table table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.imp-table th { position: sticky; top: 0; background: #f3f8fc; color: #0f5a99; text-align: left; padding: 8px 10px; font-size: 11.5px; text-transform: uppercase; }
.imp-table td { padding: 7px 10px; border-top: 1px solid #f1f4f8; }
.imp-table tr.bad td { background: #fff8f8; }
.imp-table .ok { color: #1f7a32; font-weight: 700; }
.imp-table .err { color: #b91c1c; font-weight: 600; }

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

/* Estilo para el CURP */
.curp-text {
    font-family: 'Courier New', Courier, monospace;
    font-size: 13px;
    color: #475569;
    letter-spacing: 0.5px;
}

/* Estilos de los botones de acción */
.cell-actions {
    display: flex;
    justify-content: center;
    gap: 8px;
}
.btn-action {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    width: 32px !important;
    height: 32px !important;
    border-radius: 8px !important;
    font-size: 15px;
    transition: all 0.2s ease;
    border: none !important;
}
.btn-view {
    color: #1d7fc9 !important;
    background: #eff6ff !important;
}
.btn-view:hover {
    color: #1d4ed8 !important;
    background: #dbeafe !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(29, 127, 201, 0.2);
}
.btn-edit {
    color: #0b3f6e !important;
    background: #f0f4f8 !important;
}
.btn-edit:hover {
    color: #082a4d !important;
    background: #e2e8f0 !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(11, 63, 110, 0.15);
}
</style>