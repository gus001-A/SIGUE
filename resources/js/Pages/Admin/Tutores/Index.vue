<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    HeartOutlined, PlusOutlined, SafetyCertificateOutlined, KeyOutlined, MinusCircleOutlined, UserOutlined, TeamOutlined,
    PhoneOutlined, MailOutlined, SettingOutlined, EditOutlined, DeleteOutlined, IdcardOutlined, HomeOutlined, ToolOutlined,
    EnvironmentOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHead from '@/Components/PageHead.vue';
import StatCard from '@/Components/StatCard.vue';
import SgTable from '@/Components/SgTable.vue';
import SgModal from '@/Components/SgModal.vue';
import AddressFields from '@/Components/AddressFields.vue';
import { confirmDelete, confirmAction } from '@/lib/notify';

const props = defineProps({ items: Array, stats: Object, options: Object, canEdit: Boolean, filters: Object });

const open = ref(false);
const editing = ref(null);
const blank = () => ({ name: '', paternal_surname: '', maternal_surname: '', email: '', phone: '', curp: '', occupation: '', address: '', postal_code: '', colony: '', municipality: '', state: '',
    location_consent: false, students: [{ id: null, relationship: 'tutor', is_primary: true }], create_account: true });
const form = useForm(blank());
const err = (k) => ({ 'validate-status': form.errors[k] ? 'error' : undefined, help: form.errors[k] });

function create() { editing.value = null; Object.assign(form, blank()); form.clearErrors(); open.value = true; }
function edit(r) {
    editing.value = r;
    Object.assign(form, { ...blank(), ...r, create_account: false,
        students: r.students.length ? r.students.map((s) => ({ id: s.id, relationship: s.relationship_key, is_primary: s.is_primary })) : blank().students });
    form.clearErrors();
    open.value = true;
}
function submit() {
    const o = { preserveScroll: true, onSuccess: () => (open.value = false) };
    editing.value ? form.put(route('tutores.update', editing.value.id), o) : form.post(route('tutores.store'), o);
}
const remove = (r) => confirmDelete({ title: '¿Eliminar tutor?', content: r.full_name, onOk: () => router.delete(route('tutores.destroy', r.id), { preserveScroll: true }) });
const verify = (r) => confirmAction({ title: r.verified ? '¿Retirar validación de identidad?' : '¿Validar identidad?', content: r.full_name, tone: r.verified ? 'warn' : 'success',
    okText: r.verified ? 'Sí, retirar' : 'Sí, validar', onOk: () => router.post(route('tutores.verify', r.id), {}, { preserveScroll: true }) });
const filterOption = (input, opt) => opt.label.toLowerCase().includes(input.toLowerCase());

const columns = [
    { title: 'Padre o tutor', key: 'name', icon: HeartOutlined, filterValue: (r) => `${r.full_name} ${r.email ?? ''}` },
    { title: 'Teléfono', key: 'phone', dataIndex: 'phone', icon: PhoneOutlined, width: 150 },
    { title: 'Alumnos vinculados', key: 'students', icon: TeamOutlined, filterValue: (r) => r.students.map((s) => s.name).join(' ') },
    { title: 'Identidad', key: 'verified', icon: SafetyCertificateOutlined, width: 140, filter: 'select', filterValue: (r) => (r.verified ? 'Validada' : 'Pendiente') },
    { title: 'Portal', key: 'account', icon: KeyOutlined, width: 140, filter: 'select', filterValue: (r) => (r.has_account ? 'Con cuenta' : 'Sin cuenta') },
    { title: 'Acciones', key: 'actions', icon: SettingOutlined, width: 150, align: 'center' },
];
</script>

<template>
    <AppLayout title="Padres y tutores">
        <PageHead title="Padres y tutores" subtitle="Registro, validación de identidad y vinculación con alumnos" :icon="HeartOutlined">
            <template #actions><a-button v-if="canEdit" type="primary" size="large" @click="create"><template #icon><PlusOutlined /></template>Nuevo tutor</a-button></template>
        </PageHead>

        <div class="sg-stats">
            <StatCard label="Tutores registrados" :value="stats.total" :icon="HeartOutlined" color="pink" />
            <StatCard label="Con acceso al portal" :value="stats.con_cuenta" :icon="KeyOutlined" color="indigo" />
            <StatCard label="Identidad sin validar" :value="stats.sin_verificar" :icon="SafetyCertificateOutlined" color="amber" />
        </div>

        <a-card :bordered="false" class="sg-fill">
            <SgTable :columns="columns" :data-source="items" unit="tutores">
                <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'name'">
                        <div class="sg-who">
                            <span class="sg-av" style="background: linear-gradient(135deg, #e0457b, #f39200)">{{ record.name[0] }}{{ record.paternal_surname?.[0] }}</span>
                            <span><b>{{ record.full_name }}</b><small><MailOutlined /> {{ record.email || 'Sin correo' }}</small></span>
                        </div>
                    </template>
                    <template v-else-if="column.key === 'students'">
                        <Link v-for="s in record.students" :key="s.id" :href="route('alumnos.show', s.id)" class="sg-chip" style="--c: #1d7fc9; margin: 2px"><UserOutlined /> {{ s.name }} · {{ s.relationship }}</Link>
                        <span v-if="!record.students.length" class="sg-faint">Sin alumnos</span>
                    </template>
                    <template v-else-if="column.key === 'verified'">
                        <span class="sg-chip" :style="{ '--c': record.verified ? '#2e9b43' : '#f39200' }"><SafetyCertificateOutlined /> {{ record.verified ? 'Validada' : 'Pendiente' }}</span>
                    </template>
                    <template v-else-if="column.key === 'account'">
                        <span class="sg-chip" :style="{ '--c': record.has_account ? '#1d7fc9' : '#94a3b8' }"><KeyOutlined /> {{ record.has_account ? 'Con cuenta' : 'Sin cuenta' }}</span>
                    </template>
                    <template v-else-if="column.key === 'actions'">
                        <div v-if="canEdit" class="sg-actions">
                            <a-tooltip :title="record.verified ? 'Retirar validación' : 'Validar identidad'"><a-button class="sg-ab green" @click="verify(record)"><SafetyCertificateOutlined /></a-button></a-tooltip>
                            <a-tooltip title="Editar"><a-button class="sg-ab navy" @click="edit(record)"><EditOutlined /></a-button></a-tooltip>
                            <a-tooltip title="Eliminar"><a-button class="sg-ab red" @click="remove(record)"><DeleteOutlined /></a-button></a-tooltip>
                        </div>
                    </template>
                </template>
            </SgTable>
        </a-card>

        <SgModal v-model:open="open" :title="editing ? 'Editar tutor' : 'Nuevo padre o tutor'" :subtitle="editing ? editing.full_name : 'Datos de contacto y alumnos a su cargo'"
            :icon="HeartOutlined" color="linear-gradient(135deg, #e0457b, #f39200)" width="min(960px, 96vw)" :loading="form.processing" @ok="submit">
            <a-form layout="vertical" class="sg-grid-4">
                <a-form-item label="Nombre(s)" required v-bind="err('name')"><a-input v-model:value="form.name"><template #prefix><UserOutlined style="color: #e0457b" /></template></a-input></a-form-item>
                <a-form-item label="Apellido paterno" required v-bind="err('paternal_surname')"><a-input v-model:value="form.paternal_surname" /></a-form-item>
                <a-form-item label="Apellido materno" v-bind="err('maternal_surname')"><a-input v-model:value="form.maternal_surname" /></a-form-item>
                <a-form-item label="CURP" v-bind="err('curp')"><a-input v-upper v-model:value="form.curp" :maxlength="18"><template #prefix><IdcardOutlined style="color: #f39200" /></template></a-input></a-form-item>
                <a-form-item label="Correo" v-bind="err('email')"><a-input v-model:value="form.email"><template #prefix><MailOutlined style="color: #e0457b" /></template></a-input></a-form-item>
                <a-form-item label="Teléfono" v-bind="err('phone')"><a-input v-digits="10" v-model:value="form.phone" placeholder="10 dígitos"><template #prefix><PhoneOutlined style="color: #2e9b43" /></template></a-input></a-form-item>
                <a-form-item label="Ocupación" v-bind="err('occupation')"><a-input v-model:value="form.occupation"><template #prefix><ToolOutlined style="color: #7c4ddb" /></template></a-input></a-form-item>
                <a-form-item label="Ubicación">
                    <a-checkbox v-model:checked="form.location_consent"><EnvironmentOutlined style="color: #14a39a" /> Autoriza GPS</a-checkbox>
                </a-form-item>
                <AddressFields :form="form" :errors="form.errors" class="full" />
                <div class="sg-sec" style="--c: #1d7fc9"><TeamOutlined /> Alumnos vinculados</div>
                <div class="full links">
                    <div v-for="(s, i) in form.students" :key="i" class="link-row">
                        <a-select v-model:value="s.id" show-search :filter-option="filterOption" :options="options.students" placeholder="Alumno" />
                        <a-select v-model:value="s.relationship" :options="options.relationships" />
                        <a-checkbox v-model:checked="s.is_primary">Principal</a-checkbox>
                        <a-button class="sg-ab red" size="small" @click="form.students.splice(i, 1)"><MinusCircleOutlined /></a-button>
                    </div>
                    <a-button type="dashed" @click="form.students.push({ id: null, relationship: 'tutor', is_primary: false })"><PlusOutlined /> Agregar alumno</a-button>
                </div>
            </a-form>
            <template #extra>
                <a-checkbox v-if="!editing || !editing.has_account" v-model:checked="form.create_account"><KeyOutlined style="color: #f39200" /> Crear acceso al portal de padres</a-checkbox>
            </template>
        </SgModal>
    </AppLayout>
</template>

<style scoped>
.links { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 16px; align-items: start; }
.link-row { display: grid; grid-template-columns: minmax(0, 1fr) 130px auto auto; gap: 6px; align-items: center; }
@media (max-width: 760px) { .links { grid-template-columns: 1fr; } }
</style>
