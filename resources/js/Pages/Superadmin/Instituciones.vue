<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import {
    BankOutlined, PlusOutlined, LoginOutlined, TeamOutlined, CheckCircleOutlined, UserOutlined, CrownOutlined, EnvironmentOutlined,
    CalendarOutlined, EditOutlined, MailOutlined, PhoneOutlined, HomeOutlined, NumberOutlined, KeyOutlined, ArrowRightOutlined,
    SafetyCertificateOutlined, DeleteOutlined, SearchOutlined, AppstoreOutlined, ClockCircleOutlined, WarningOutlined, ReadOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHead from '@/Components/PageHead.vue';
import StatCard from '@/Components/StatCard.vue';
import SgTable from '@/Components/SgTable.vue';
import SgModal from '@/Components/SgModal.vue';
import AddressFields from '@/Components/AddressFields.vue';
import { optionMap } from '@/lib/format';
import { confirmDelete } from '@/lib/notify';

const props = defineProps({ items: Array, filters: Object, plans: Array, modules: Array, stats: Object, current: [Number, null] });
const planLabel = computed(() => optionMap(props.plans));
const PLAN_COLOR = { basico: '#1d7fc9', profesional: '#7c4ddb', enterprise: '#f39200', empresarial: '#f39200' };

const STATUS_COLOR = { activa: '#2e9b43', prueba: '#1d7fc9', suspendida: '#e3262b' };
const statusLabel = { activa: 'Activa', prueba: 'Prueba', suspendida: 'Suspendida' };
const columns = computed(() => [
    { title: 'Institución', key: 'name', icon: BankOutlined, filterValue: (i) => `${i.name} ${i.short_name ?? ''} ${i.cct ?? ''} ${i.campus_list.map((c) => c.name).join(' ')}` },
    { title: 'Plan', key: 'plan', icon: CrownOutlined, width: 150, filter: 'select', filterValue: (i) => planLabel.value[i.plan] || i.plan },
    { title: 'Licencia', key: 'license', icon: ClockCircleOutlined, width: 210, filter: 'select', filterValue: (i) => (i.license_days === null ? 'Sin vigencia' : i.license_days < 0 ? 'Vencida' : i.license_days <= 30 ? 'Por vencer' : 'Vigente') },
    { title: 'Alumnos', key: 'students', icon: TeamOutlined, width: 140, filter: false },
    { title: 'Planteles', key: 'campuses', icon: EnvironmentOutlined, width: 115, filter: false },
    { title: 'Usuarios', key: 'users', icon: UserOutlined, width: 105, filter: false },
    { title: 'Estado', key: 'status', icon: CheckCircleOutlined, width: 135, filter: 'select', filterValue: (i) => (i.active ? statusLabel[i.status] : 'Inactiva') },
    { title: 'Acciones', key: 'actions', width: 140, align: 'center' },
]);

const pct = (n, max) => (max ? Math.min(100, Math.round((n / max) * 100)) : 0);
const initials = (n) => (n || '?').split(/\s+/).filter((w) => w.length > 2).slice(0, 2).map((w) => w[0]).join('').toUpperCase() || '?';
const licenseState = (i) => {
    if (i.license_days === null) return { c: '#94a3b8', t: 'Sin vigencia' };
    if (i.license_days < 0) return { c: '#e3262b', t: `Venció hace ${-i.license_days} día(s)` };
    if (i.license_days <= 30) return { c: '#f39200', t: `Vence en ${i.license_days} día(s)` };
    return { c: '#2e9b43', t: `Vigente · ${i.license_expires_at.split('-').reverse().join('/')}` };
};

// ---------- Institución ----------
const open = ref(false);
const editing = ref(null);
const tab = ref('general');
const blank = { name: '', short_name: '', cct: '', email: '', phone: '', address: '', postal_code: '', colony: '', municipality: '', state: '', plan: 'basico', max_students: null, max_campuses: 1,
    modules: props.modules.map((m) => m.value), license_expires_at: null, status: 'activa', admin_name: '', admin_email: '', admin_password: '' };
const form = useForm({ ...blank });
const err = (k) => ({ 'validate-status': form.errors[k] ? 'error' : undefined, help: form.errors[k] });
function create() { editing.value = null; tab.value = 'general'; Object.assign(form, blank); form.clearErrors(); open.value = true; }
function edit(r) { editing.value = r; tab.value = 'general'; Object.assign(form, { ...blank, ...r, modules: r.modules ?? [] }); form.clearErrors(); open.value = true; }
function submit() {
    const o = { preserveScroll: true, onSuccess: () => (open.value = false) };
    editing.value ? form.put(route('superadmin.instituciones.update', editing.value.id), o) : form.post(route('superadmin.instituciones.store'), o);
}
const removeInst = (i) => confirmDelete({
    title: '¿Eliminar institución?', content: `${i.name}. La eliminación es lógica: su información se conserva y puede recuperarse.`,
    onOk: () => router.delete(route('superadmin.instituciones.destroy', i.id), { preserveScroll: true }),
});

// ---------- Planteles ----------
const cOpen = ref(false);
const cInst = ref(null);
const cEditing = ref(null);
const cBlank = { name: '', code: '', phone: '', address: '', postal_code: '', colony: '', municipality: '', state: '', is_active: true };
const cForm = useForm({ ...cBlank });
const cErr = (k) => ({ 'validate-status': cForm.errors[k] ? 'error' : undefined, help: cForm.errors[k] });
function newCampus(i) { cInst.value = i; cEditing.value = null; Object.assign(cForm, cBlank); cForm.clearErrors(); cOpen.value = true; }
function editCampus(i, c) { cInst.value = i; cEditing.value = c; Object.assign(cForm, { ...cBlank, ...c }); cForm.clearErrors(); cOpen.value = true; }
function saveCampus() {
    const o = { preserveScroll: true, onSuccess: () => (cOpen.value = false) };
    cEditing.value ? cForm.put(route('superadmin.planteles.update', cEditing.value.id), o) : cForm.post(route('superadmin.planteles.store', cInst.value.id), o);
}
const removeCampus = (c) => confirmDelete({
    title: '¿Eliminar plantel?', content: `${c.name}. Solo se permite si no tiene alumnos activos; la eliminación es lógica.`,
    onOk: () => router.delete(route('superadmin.planteles.destroy', c.id), { preserveScroll: true }),
});

// ---------- Entrar ----------
const enterInst = ref(null);
function enter(i, campusId = null) {
    if (campusId) router.post(route('superadmin.instituciones.enter', i.id), { campus_id: campusId });
    else if (i.campus_list.length === 1) router.post(route('superadmin.instituciones.enter', i.id), { campus_id: i.campus_list[0].id });
    else enterInst.value = i;
}

const STATUSES = [{ value: 'activa', label: 'Activa' }, { value: 'prueba', label: 'Prueba' }, { value: 'suspendida', label: 'Suspendida' }];
</script>

<template>
    <AppLayout title="Instituciones">
        <PageHead title="Instituciones" subtitle="Administración general SIGUE: licencias, suscripciones y soporte" :icon="BankOutlined">
            <template #actions><a-button type="primary" size="large" @click="create"><template #icon><PlusOutlined /></template>Nueva institución</a-button></template>
        </PageHead>

        <div class="sg-stats">
            <StatCard label="Instituciones" :value="stats.institutions" :icon="BankOutlined" color="indigo" />
            <StatCard label="Activas" :value="stats.active" :icon="CheckCircleOutlined" color="green" />
            <StatCard label="Alumnos activos" :value="stats.students" :icon="TeamOutlined" color="violet" />
            <StatCard label="Usuarios" :value="stats.users" :icon="UserOutlined" color="slate" />
        </div>

        <a-card :bordered="false" class="sg-fill">
            <SgTable :columns="columns" :data-source="items" unit="instituciones" class="inst-table">
                <template #bodyCell="{ column, record: i }">
                    <template v-if="column.key === 'name'">
                        <div class="sg-who" :style="{ '--p': PLAN_COLOR[i.plan] ?? '#1d7fc9' }">
                            <span class="sg-av inst-av">{{ initials(i.name) }}</span>
                            <span><b>{{ i.name }}</b><small><NumberOutlined /> {{ i.cct || 'Sin CCT' }}<template v-if="current === i.id"> · <em class="here">Operando aquí</em></template></small></span>
                        </div>
                    </template>
                    <template v-else-if="column.key === 'plan'">
                        <span class="sg-chip" :style="{ '--c': PLAN_COLOR[i.plan] ?? '#1d7fc9' }"><CrownOutlined /> {{ planLabel[i.plan] || i.plan }}</span>
                    </template>
                    <template v-else-if="column.key === 'license'">
                        <span class="sg-badge" :style="{ '--c': licenseState(i).c }">{{ licenseState(i).t }}</span>
                    </template>
                    <template v-else-if="column.key === 'students'">
                        <div class="mini-use" style="--c: #1d7fc9">
                            <span><b>{{ i.students }}</b> / {{ i.max_students ?? '∞' }}</span>
                            <span class="use__bar"><i :style="{ width: (i.max_students ? pct(i.students, i.max_students) : 6) + '%' }" /></span>
                        </div>
                    </template>
                    <template v-else-if="column.key === 'campuses'">
                        <span class="cnt"><EnvironmentOutlined /> <b>{{ i.campuses }}</b> / {{ i.max_campuses ?? '∞' }}</span>
                    </template>
                    <template v-else-if="column.key === 'users'">
                        <span class="cnt"><UserOutlined /> <b>{{ i.users }}</b></span>
                    </template>
                    <template v-else-if="column.key === 'status'">
                        <span class="sg-badge" :style="{ '--c': STATUS_COLOR[i.active ? i.status : 'suspendida'] }">{{ i.active ? statusLabel[i.status] : 'Inactiva' }}</span>
                    </template>
                    <template v-else-if="column.key === 'actions'">
                        <div class="sg-actions">
                            <a-tooltip title="Entrar"><a-button class="sg-ab green" @click.stop="enter(i)"><LoginOutlined /></a-button></a-tooltip>
                            <a-tooltip title="Editar institución y licencia"><a-button class="sg-ab blue" @click.stop="edit(i)"><EditOutlined /></a-button></a-tooltip>
                            <a-tooltip title="Eliminar (lógico)"><a-button class="sg-ab red" @click.stop="removeInst(i)"><DeleteOutlined /></a-button></a-tooltip>
                        </div>
                    </template>
                </template>

                <template #expandedRowRender="{ record: i }">
                    <div class="xp">
                        <div class="xp__head">
                            <span class="xp__title"><EnvironmentOutlined /> Planteles de {{ i.short_name || i.name }} <span class="count">{{ i.campus_list.length }}</span></span>
                            <span class="xp__meta">
                                <span v-if="i.email"><MailOutlined /> {{ i.email }}</span>
                                <span v-if="i.phone"><PhoneOutlined /> {{ i.phone }}</span>
                                <span><AppstoreOutlined /> {{ (i.modules ?? []).length }} de {{ modules.length }} módulos</span>
                            </span>
                        </div>
                        <div class="campus-grid">
                            <div v-for="c in i.campus_list" :key="c.id" class="cp" :class="{ 'is-off': !c.is_active }">
                                <div class="cp__top">
                                    <span class="cp__ic"><HomeOutlined /></span>
                                    <div class="cp__txt">
                                        <b :title="c.name">{{ c.name }}</b>
                                        <small>{{ c.code || 'Sin clave' }}<template v-if="!c.is_active"> · Inactivo</template></small>
                                    </div>
                                </div>
                                <div class="cp__stats">
                                    <span><TeamOutlined /> <b>{{ c.students }}</b> alumnos</span>
                                    <span><ReadOutlined /> <b>{{ c.teachers }}</b> docentes</span>
                                    <span><AppstoreOutlined /> <b>{{ c.groups }}</b> grupos</span>
                                </div>
                                <small v-if="c.address || c.municipality" class="cp__addr"><EnvironmentOutlined /> {{ [c.address, c.colony, c.municipality].filter(Boolean).join(', ') }}</small>
                                <div class="cp__acts">
                                    <a-button size="small" class="cp__enter" @click="enter(i, c.id)"><LoginOutlined /> Entrar</a-button>
                                    <a-tooltip title="Editar plantel"><a-button size="small" class="sg-ab blue" @click="editCampus(i, c)"><EditOutlined /></a-button></a-tooltip>
                                    <a-tooltip title="Eliminar plantel"><a-button size="small" class="sg-ab red" @click="removeCampus(c)"><DeleteOutlined /></a-button></a-tooltip>
                                </div>
                            </div>
                            <button type="button" class="cp cp--add" :disabled="i.max_campuses && i.campuses >= i.max_campuses" @click="newCampus(i)">
                                <PlusOutlined />
                                <b>Agregar plantel</b>
                                <small v-if="i.max_campuses && i.campuses >= i.max_campuses"><WarningOutlined /> Límite del plan alcanzado</small>
                                <small v-else>Nueva sede de {{ i.short_name || i.name }}</small>
                            </button>
                        </div>
                    </div>
                </template>
            </SgTable>
        </a-card>

        <!-- Plantel -->
        <SgModal v-model:open="cOpen" :title="cEditing ? 'Editar plantel' : 'Nuevo plantel'" :subtitle="cInst?.name" :icon="EnvironmentOutlined"
            color="linear-gradient(135deg, #14a39a, #2e9b43)" width="min(860px, 96vw)" :loading="cForm.processing" @ok="saveCampus">
            <a-form layout="vertical" class="sg-grid-2">
                <a-form-item label="Nombre del plantel" required v-bind="cErr('name')"><a-input v-model:value="cForm.name" placeholder="PLANTEL NORTE"><template #prefix><HomeOutlined style="color: #14a39a" /></template></a-input></a-form-item>
                <a-form-item label="Clave" v-bind="cErr('code')"><a-input v-model:value="cForm.code" placeholder="NOR"><template #prefix><NumberOutlined style="color: #7c4ddb" /></template></a-input></a-form-item>
                <a-form-item label="Teléfono" v-bind="cErr('phone')"><a-input v-digits="10" v-model:value="cForm.phone" placeholder="10 dígitos"><template #prefix><PhoneOutlined style="color: #2e9b43" /></template></a-input></a-form-item>
                <a-form-item label="Estado del plantel"><a-switch v-model:checked="cForm.is_active" checked-children="Activo" un-checked-children="Inactivo" /></a-form-item>
                <AddressFields :form="cForm" :errors="cForm.errors" class="full" />
            </a-form>
        </SgModal>

        <!-- Entrar: elegir plantel -->
        <SgModal :open="!!enterInst" title="Entrar a la institución" :subtitle="`${enterInst?.name} · elige el plantel con el que vas a trabajar`" :icon="LoginOutlined"
            color="linear-gradient(135deg, #2e9b43, #14a39a)" width="min(780px, 96vw)" :footer="false" @update:open="enterInst = null">
            <a-empty v-if="!enterInst?.campus_list?.length" description="La institución no tiene planteles activos" />
            <div class="campuses">
                <button v-for="c in enterInst?.campus_list" :key="c.id" class="campus" @click="enter(enterInst, c.id)">
                    <span class="campus__ic"><EnvironmentOutlined /></span>
                    <span class="campus__txt"><b>{{ c.name }}</b><small>{{ c.code ? c.code + ' · ' : '' }}{{ c.students }} alumnos activos</small><small v-if="c.address"><HomeOutlined /> {{ c.address }}</small></span>
                    <ArrowRightOutlined class="campus__go" />
                </button>
            </div>
            <p class="sg-muted" style="margin: 12px 0 16px"><SafetyCertificateOutlined /> Dentro de un plantel solo verás y modificarás la información de ese plantel. Puedes cambiarlo después desde la barra superior.</p>
        </SgModal>

        <SgModal v-model:open="open" :title="editing ? 'Editar institución' : 'Nueva institución'" :subtitle="editing ? editing.name : 'Alta de un nuevo cliente SIGUE'" :icon="BankOutlined"
            color="linear-gradient(135deg, #0b3f6e, #1d7fc9)" width="min(940px, 96vw)" :loading="form.processing" @ok="submit">
            <a-tabs v-model:active-key="tab">
                <a-tab-pane key="general"><template #tab><span class="sg-tab"><BankOutlined /> Datos generales</span></template>
                    <a-form layout="vertical" class="sg-grid-2">
                        <a-form-item label="Nombre" required class="full" v-bind="err('name')"><a-input v-model:value="form.name"><template #prefix><BankOutlined style="color: #0b3f6e" /></template></a-input></a-form-item>
                        <a-form-item label="Nombre corto" v-bind="err('short_name')"><a-input v-model:value="form.short_name" /></a-form-item>
                        <a-form-item label="CCT" v-bind="err('cct')"><a-input v-model:value="form.cct"><template #prefix><NumberOutlined style="color: #7c4ddb" /></template></a-input></a-form-item>
                        <a-form-item label="Correo" v-bind="err('email')"><a-input v-model:value="form.email"><template #prefix><MailOutlined style="color: #e0457b" /></template></a-input></a-form-item>
                        <a-form-item label="Teléfono" v-bind="err('phone')"><a-input v-digits="10" v-model:value="form.phone" placeholder="10 dígitos"><template #prefix><PhoneOutlined style="color: #2e9b43" /></template></a-input></a-form-item>
                        <AddressFields :form="form" :errors="form.errors" class="full" />
                    </a-form>
                </a-tab-pane>
                <a-tab-pane key="licencia"><template #tab><span class="sg-tab"><CrownOutlined /> Suscripción y licencia</span></template>
                    <a-form layout="vertical" class="sg-grid-2">
                        <a-form-item label="Plan" v-bind="err('plan')"><a-select v-model:value="form.plan" :options="plans" /></a-form-item>
                        <a-form-item label="Vigencia de licencia" v-bind="err('license_expires_at')"><a-date-picker v-model:value="form.license_expires_at" value-format="YYYY-MM-DD" format="DD/MM/YYYY" /></a-form-item>
                        <a-form-item label="Estado" v-bind="err('status')"><a-select v-model:value="form.status" :options="STATUSES" /></a-form-item>
                        <a-form-item label="Límites (alumnos activos · planteles)" :validate-status="form.errors.max_students || form.errors.max_campuses ? 'error' : undefined"
                            :help="form.errors.max_students || form.errors.max_campuses">
                            <div class="lims">
                                <a-input-number v-model:value="form.max_students" :min="1" placeholder="Alumnos" addon-before="Alumnos" />
                                <a-input-number v-model:value="form.max_campuses" :min="1" placeholder="Planteles" addon-before="Planteles" />
                            </div>
                        </a-form-item>
                        <a-form-item label="Módulos contratados" class="full"><a-checkbox-group v-model:value="form.modules" :options="modules" class="mods" /></a-form-item>
                    </a-form>
                </a-tab-pane>
                <a-tab-pane v-if="!editing" key="admin"><template #tab><span class="sg-tab"><KeyOutlined /> Administrador inicial</span></template>
                    <a-form layout="vertical" class="sg-grid-2">
                        <a-form-item label="Nombre" class="full" v-bind="err('admin_name')"><a-input v-model:value="form.admin_name"><template #prefix><UserOutlined style="color: #1d7fc9" /></template></a-input></a-form-item>
                        <a-form-item label="Correo" v-bind="err('admin_email')"><a-input v-model:value="form.admin_email"><template #prefix><MailOutlined style="color: #e0457b" /></template></a-input></a-form-item>
                        <a-form-item label="Contraseña temporal" v-bind="err('admin_password')"><a-input-password v-model:value="form.admin_password" /></a-form-item>
                    </a-form>
                    <p class="sg-muted">Opcional. Se le pedirá cambiar la contraseña al ingresar.</p>
                </a-tab-pane>
            </a-tabs>
        </SgModal>
    </AppLayout>
</template>

<style scoped>
/* Estilos del modal (Mantenidos de tu código original) */
.lims { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.lims :deep(.ant-input-number-group-wrapper) { width: 100%; }
.campuses { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.campus {
    display: flex; align-items: center; gap: 12px; text-align: left; padding: 14px; border-radius: 14px; border: 2px solid var(--sg-line);
    background: #fff; cursor: pointer; transition: border-color .15s, transform .15s, box-shadow .15s;
}
.campus:hover { border-color: #2e9b43; transform: translateY(-2px); box-shadow: 0 12px 24px -16px rgba(46, 155, 67, .6); }
.campus__ic { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; color: #fff; font-size: 19px; background: linear-gradient(135deg, #f39200, #e3262b); flex: none; }
.campus__txt { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.campus__txt b { color: #0b2f55; }
.campus__txt small { color: #64748b; font-size: 12px; }
.campus__go { color: #2e9b43; font-size: 18px; }
.mods { display: grid !important; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
@media (max-width: 760px) { .campuses, .mods { grid-template-columns: 1fr; } }

/* ---------- Tabla expandible ---------- */
.inst-av { width: 40px; height: 40px; border-radius: 12px; background: linear-gradient(135deg, #0b3f6e, var(--p)); font-size: 13px; }
.here { font-style: normal; color: #2e9b43; font-weight: 700; }
.mini-use { display: flex; flex-direction: column; gap: 4px; font-size: 12px; color: #64748b; }
.mini-use b { color: #0b2f55; font-size: 14px; }
.cnt { display: inline-flex; align-items: center; gap: 6px; color: #64748b; font-size: 12.5px; }
.cnt b { color: #0b2f55; font-size: 14px; }
.cnt .anticon { color: #14a39a; }
.inst-table :deep(.ant-table-expanded-row > td) { background: #f6f9fc !important; padding: 14px 18px 18px 52px !important; white-space: normal; }
.inst-table :deep(.ant-table-row-expand-icon) { border-radius: 6px; width: 20px; height: 20px; border-color: #1d7fc9; color: #1d7fc9; }
.inst-table :deep(.ant-table-row-expand-icon::before) { top: 8.5px; inset-inline: 4px; }
.inst-table :deep(.ant-table-row-expand-icon::after) { inset-block: 4px; inset-inline-start: 8.5px; }
.xp { display: flex; flex-direction: column; gap: 10px; }
.xp__head { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
.xp__title { display: inline-flex; align-items: center; gap: 6px; font-weight: 800; color: #0b2f55; font-size: 13.5px; }
.xp__meta { display: flex; gap: 14px; flex-wrap: wrap; font-size: 12px; color: #64748b; }
.xp__meta span { display: inline-flex; gap: 5px; align-items: center; }
.xp .cp { background: #fff; }
/* ---------- Tarjetas de institución ---------- */
.insts { flex: 1 1 auto; min-height: 0; overflow-y: auto; display: flex; flex-direction: column; gap: 14px; padding: 2px 4px 10px 2px; }
.inst { flex: none; background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 16px 18px; position: relative; overflow: hidden; box-shadow: 0 8px 22px -18px rgba(11, 47, 85, .45); }
.inst::before { content: ''; position: absolute; inset: 0 0 auto 0; height: 4px; background: linear-gradient(90deg, var(--p), #14a39a); }
.inst.is-current { border-color: #86d39b; box-shadow: 0 0 0 3px #e3f6e8; }
.inst.is-off { opacity: .8; }
.inst__head { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.inst__logo { width: 56px; height: 56px; border-radius: 16px; display: grid; place-items: center; color: #fff; font-weight: 900; font-size: 20px; flex: none; background: linear-gradient(135deg, #0b3f6e, var(--p)); box-shadow: 0 10px 20px -10px var(--p); }
.inst__title { flex: 1; min-width: 260px; display: flex; flex-direction: column; gap: 4px; }
.inst__name { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.inst__name b { font-size: 17px; color: #0b2f55; }
.inst__name :deep(.ant-tag) { margin: 0; }
.inst__here { font-size: 11.5px; font-weight: 700; color: #2e9b43; background: #e3f6e8; padding: 1px 8px; border-radius: 999px; display: inline-flex; gap: 4px; align-items: center; }
.inst__meta { display: flex; flex-wrap: wrap; gap: 4px 14px; font-size: 12.5px; color: #64748b; }
.inst__meta span { display: inline-flex; align-items: center; gap: 5px; }
.inst__plan { color: var(--p); font-weight: 700; }
.inst__acts { display: flex; gap: 8px; align-items: center; }
.btn-enter { border-radius: 10px !important; font-weight: 700; background: linear-gradient(135deg, #2e9b43, #14a39a) !important; border: none !important; }

.inst__usage { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin: 14px 0; }
.use { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 12px; background: color-mix(in srgb, var(--c) 5%, #fff); border: 1px solid color-mix(in srgb, var(--c) 18%, #fff); }
.use__ic { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; color: #fff; background: var(--c); flex: none; }
.use__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 5px; }
.use__top b { font-size: 17px; color: #0b2f55; }
.use__top small { font-size: 12px; color: #64748b; }
.use__bar { height: 7px; border-radius: 999px; background: #e8eef5; overflow: hidden; }
.use__bar i { display: block; height: 100%; border-radius: 999px; background: var(--c); }
.use__mods { font-size: 12px; color: #64748b; display: inline-flex; gap: 5px; align-items: center; }

.inst__campus-title { display: flex; align-items: center; gap: 6px; font-weight: 800; color: #0b2f55; font-size: 13.5px; margin-bottom: 8px; }
.count { min-width: 22px; height: 20px; padding: 0 7px; border-radius: 999px; display: grid; place-items: center; font-size: 11px; font-weight: 800; color: #14a39a; background: #e0f5f3; }
.campus-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 10px; }
.cp { display: flex; flex-direction: column; gap: 8px; padding: 12px; border-radius: 14px; border: 1px solid #e2e8f0; background: #fafbfd; transition: border-color .15s, box-shadow .15s, transform .15s; }
.cp:hover { border-color: #9ed9d4; box-shadow: 0 10px 20px -16px #14a39a; transform: translateY(-2px); }
.cp.is-off { opacity: .65; }
.cp__top { display: flex; align-items: center; gap: 10px; }
.cp__ic { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; color: #fff; background: linear-gradient(135deg, #f39200, #e3262b); flex: none; }
.cp__txt { min-width: 0; display: flex; flex-direction: column; }
.cp__txt b { color: #0b2f55; font-size: 13.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cp__txt small { color: #94a3b8; font-size: 11.5px; }
.cp__stats { display: flex; flex-wrap: wrap; gap: 4px 12px; font-size: 12px; color: #64748b; }
.cp__stats b { color: #0b2f55; }
.cp__addr { font-size: 11.5px; color: #94a3b8; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cp__acts { display: flex; gap: 6px; margin-top: auto; }
.cp__enter { flex: 1; border-radius: 8px !important; font-weight: 700; color: #2e9b43 !important; background: #e8f6ec !important; border-color: #c7ebd1 !important; }
.cp--add { align-items: center; justify-content: center; text-align: center; min-height: 150px; border: 2px dashed #b9dcd8; background: #f4fbfa; color: #14a39a; cursor: pointer; font-family: inherit; font-size: 20px; }
.cp--add b { font-size: 13.5px; }
.cp--add small { font-size: 11.5px; color: #64748b; }
.cp--add:disabled { cursor: not-allowed; border-color: #e2e8f0; background: #f8fafc; color: #94a3b8; transform: none; box-shadow: none; }
.insts__empty { padding: 40px; background: #fff; border-radius: 16px; }
@media (max-width: 900px) { .inst__usage { grid-template-columns: 1fr; } }
</style>