<script setup>
import { ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import {
    UsergroupAddOutlined, PlusOutlined, KeyOutlined, PoweroffOutlined, UserOutlined, SafetyOutlined, ClockCircleOutlined,
    FlagOutlined, SettingOutlined, EditOutlined, MailOutlined, PhoneOutlined, LockOutlined, CheckCircleOutlined, StopOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHead from '@/Components/PageHead.vue';
import StatCard from '@/Components/StatCard.vue';
import SgTable from '@/Components/SgTable.vue';
import SgModal from '@/Components/SgModal.vue';
import { confirmAction } from '@/lib/notify';

const props = defineProps({ items: Array, options: Object, filters: Object });
const page = usePage();
const me = page.props.auth.user.id;

const open = ref(false);
const editing = ref(null);
const blank = { name: '', paternal_surname: '', maternal_surname: '', email: '', phone: '', campus_id: null, is_active: true, roles: [], password: '', assign_campus: false };
const form = useForm({ ...blank });
const err = (k) => ({ 'validate-status': form.errors[k] ? 'error' : undefined, help: form.errors[k] });

function create() { editing.value = null; Object.assign(form, blank); form.clearErrors(); open.value = true; }
function edit(r) { editing.value = r; Object.assign(form, { ...blank, ...r, roles: [...r.roles], password: '', assign_campus: !!r.campus_id }); form.clearErrors(); open.value = true; }
function submit() {
    form.campus_id = form.assign_campus ? page.props.campus?.id : null;
    const o = { preserveScroll: true, onSuccess: () => (open.value = false) };
    editing.value ? form.put(route('usuarios.update', editing.value.id), o) : form.post(route('usuarios.store'), o);
}
const toggle = (r) => confirmAction({ title: r.is_active ? '¿Desactivar usuario?' : '¿Activar usuario?', tone: r.is_active ? 'delete' : 'success',
    content: r.is_active ? `${r.full_name} ya no podrá iniciar sesión.` : `${r.full_name} podrá volver a iniciar sesión.`, okText: r.is_active ? 'Sí, desactivar' : 'Sí, activar',
    onOk: () => router.post(route('usuarios.toggle', r.id), {}, { preserveScroll: true }) });
const resetPwd = (r) => confirmAction({ title: '¿Restablecer contraseña?', content: 'Se generará una contraseña temporal para ' + r.email, tone: 'warn', okText: 'Sí, restablecer',
    onOk: () => router.post(route('usuarios.password', r.id), {}, { preserveScroll: true }) });

const roleOptions = props.options.roles.map((r) => ({ value: r.label, label: r.label }));
const columns = [
    { title: 'Usuario', key: 'name', icon: UserOutlined, filterValue: (r) => `${r.full_name} ${r.email}` },
    { title: 'Roles', key: 'roles', icon: SafetyOutlined, filter: 'select', filterOptions: roleOptions, filterValue: (r) => r.role_names },
    { title: 'Plantel', key: 'campus', icon: FlagOutlined, filter: 'select', filterValue: (r) => r.campus ?? 'Todos los planteles' },
    { title: 'Último acceso', key: 'last_login', dataIndex: 'last_login', icon: ClockCircleOutlined, width: 160 },
    { title: 'Estado', key: 'active', icon: CheckCircleOutlined, width: 120, filter: 'select', filterValue: (r) => (r.is_active ? 'Activo' : 'Inactivo') },
    { title: 'Acciones', key: 'actions', icon: SettingOutlined, width: 150, align: 'center' },
];
</script>

<template>
    <AppLayout title="Usuarios">
        <PageHead title="Usuarios" subtitle="Cuentas de acceso y roles autorizados (un usuario puede tener varios roles)" :icon="UsergroupAddOutlined">
            <template #actions><a-button type="primary" size="large" @click="create"><template #icon><PlusOutlined /></template>Nuevo usuario</a-button></template>
        </PageHead>

        <div class="sg-stats">
            <StatCard label="Usuarios" :value="items.length" :icon="UserOutlined" color="indigo" />
            <StatCard label="Activos" :value="items.filter((u) => u.is_active).length" :icon="CheckCircleOutlined" color="green" />
            <StatCard label="Inactivos" :value="items.filter((u) => !u.is_active).length" :icon="StopOutlined" color="red" />
            <StatCard label="Nunca han ingresado" :value="items.filter((u) => !u.last_login).length" :icon="ClockCircleOutlined" color="amber" />
        </div>

        <a-card :bordered="false" class="sg-fill">
            <SgTable :columns="columns" :data-source="items" unit="usuarios">
                <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'name'">
                        <div class="sg-who"><span class="sg-av">{{ record.name[0] }}{{ record.paternal_surname?.[0] }}</span><span><b>{{ record.full_name }}</b><small><MailOutlined /> {{ record.email }}</small></span></div>
                    </template>
                    <template v-else-if="column.key === 'roles'"><span v-for="r in record.role_names" :key="r" class="sg-chip" style="--c: #1d7fc9; margin: 2px"><SafetyOutlined /> {{ r }}</span></template>
                    <template v-else-if="column.key === 'campus'"><span class="sg-chip" style="--c: #f39200"><FlagOutlined /> {{ record.campus || 'Todos' }}</span></template>
                    <template v-else-if="column.key === 'last_login'">{{ record.last_login || 'Nunca' }}</template>
                    <template v-else-if="column.key === 'active'"><a-badge :status="record.is_active ? 'success' : 'default'" :text="record.is_active ? 'Activo' : 'Inactivo'" /></template>
                    <template v-else-if="column.key === 'actions'">
                        <div class="sg-actions">
                            <a-tooltip title="Editar"><a-button class="sg-ab navy" @click="edit(record)"><EditOutlined /></a-button></a-tooltip>
                            <a-tooltip title="Restablecer contraseña"><a-button class="sg-ab orange" @click="resetPwd(record)"><KeyOutlined /></a-button></a-tooltip>
                            <a-tooltip v-if="record.id !== me" :title="record.is_active ? 'Desactivar' : 'Activar'"><a-button class="sg-ab" :class="record.is_active ? 'red' : 'green'" @click="toggle(record)"><PoweroffOutlined /></a-button></a-tooltip>
                        </div>
                    </template>
                </template>
            </SgTable>
        </a-card>

        <SgModal v-model:open="open" :title="editing ? 'Editar usuario' : 'Nuevo usuario'" :subtitle="editing ? editing.email : 'Cuenta de acceso a SIGUE'" :icon="UsergroupAddOutlined" :loading="form.processing" @ok="submit">
            <a-form layout="vertical" class="sg-grid-2">
                <a-form-item label="Nombre(s)" required v-bind="err('name')"><a-input v-model:value="form.name"><template #prefix><UserOutlined style="color: #1d7fc9" /></template></a-input></a-form-item>
                <a-form-item label="Apellido paterno" v-bind="err('paternal_surname')"><a-input v-model:value="form.paternal_surname" /></a-form-item>
                <a-form-item label="Apellido materno" v-bind="err('maternal_surname')"><a-input v-model:value="form.maternal_surname" /></a-form-item>
                <a-form-item label="Teléfono" v-bind="err('phone')"><a-input v-digits="10" v-model:value="form.phone" placeholder="10 dígitos"><template #prefix><PhoneOutlined style="color: #2e9b43" /></template></a-input></a-form-item>
                <a-form-item label="Correo" required v-bind="err('email')"><a-input v-model:value="form.email"><template #prefix><MailOutlined style="color: #e0457b" /></template></a-input></a-form-item>
                <a-form-item :label="editing ? 'Nueva contraseña (opcional)' : 'Contraseña (vacía = temporal)'" v-bind="err('password')"><a-input-password v-model:value="form.password"><template #prefix><LockOutlined style="color: #f39200" /></template></a-input-password></a-form-item>
                <div class="sg-sec" style="--c: #2e9b43"><SafetyOutlined /> Roles autorizados</div>
                <a-form-item class="full" v-bind="err('roles')">
                    <a-checkbox-group v-model:value="form.roles" :disabled="editing?.id === me" class="roles">
                        <label v-for="r in options.roles" :key="r.value" class="role" :class="{ on: form.roles.includes(r.value) }">
                            <a-checkbox :value="r.value" /><span><b>{{ r.label }}</b><small>{{ r.description }}</small></span>
                        </label>
                    </a-checkbox-group>
                </a-form-item>
            </a-form>
            <template #extra>
                <a-checkbox v-model:checked="form.assign_campus"><FlagOutlined style="color: #f39200" /> Limitar al plantel {{ page.props.campus?.name }}</a-checkbox>
            </template>
        </SgModal>
    </AppLayout>
</template>

<style scoped>
.roles { display: grid !important; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; width: 100%; }
.role { display: flex; gap: 8px; align-items: flex-start; padding: 8px 10px; border: 1px solid var(--sg-line); border-radius: 12px; cursor: pointer; }
.role.on { border-color: #9cc8ea; background: #f3f9fe; }
.role small { display: block; font-size: 11px; color: #94a3b8; line-height: 1.3; }
@media (max-width: 760px) { .roles { grid-template-columns: 1fr; } }
</style>
