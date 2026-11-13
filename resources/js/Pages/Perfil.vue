<script setup>
import { useForm } from '@inertiajs/vue3';
import { UserOutlined, LockOutlined, SaveOutlined } from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHead from '@/Components/PageHead.vue';

const props = defineProps({ profile: Object });

const form = useForm({
    name: props.profile.name, paternal_surname: props.profile.paternal_surname, maternal_surname: props.profile.maternal_surname,
    email: props.profile.email, phone: props.profile.phone,
});
const pwd = useForm({ current_password: '', password: '', password_confirmation: '' });

const err = (f, k) => ({ 'validate-status': f.errors[k] ? 'error' : undefined, help: f.errors[k] });
</script>

<template>
    <AppLayout title="Mi perfil">
        <PageHead title="Mi perfil" :subtitle="`Último acceso: ${profile.last_login_at ?? '—'}`" :icon="UserOutlined">
            <template #actions><a-tag v-for="r in profile.roles" :key="r" color="blue">{{ r }}</a-tag></template>
        </PageHead>

        <a-row :gutter="[18, 18]">
            <a-col :xs="24" :lg="14">
                <a-card title="Datos personales" :bordered="false">
                    <a-form layout="vertical" @submit.prevent="form.put(route('perfil.update'), { preserveScroll: true })">
                        <a-row :gutter="12">
                            <a-col :xs="24" :md="8"><a-form-item label="Nombre(s)" v-bind="err(form, 'name')"><a-input v-model:value="form.name" /></a-form-item></a-col>
                            <a-col :xs="24" :md="8"><a-form-item label="Apellido paterno" v-bind="err(form, 'paternal_surname')"><a-input v-model:value="form.paternal_surname" /></a-form-item></a-col>
                            <a-col :xs="24" :md="8"><a-form-item label="Apellido materno" v-bind="err(form, 'maternal_surname')"><a-input v-model:value="form.maternal_surname" /></a-form-item></a-col>
                            <a-col :xs="24" :md="14"><a-form-item label="Correo" v-bind="err(form, 'email')"><a-input v-model:value="form.email" type="email" /></a-form-item></a-col>
                            <a-col :xs="24" :md="10"><a-form-item label="Teléfono" v-bind="err(form, 'phone')"><a-input v-digits="10" v-model:value="form.phone" placeholder="10 dígitos" /></a-form-item></a-col>
                        </a-row>
                        <a-button type="primary" html-type="submit" :loading="form.processing"><template #icon><SaveOutlined /></template>Guardar</a-button>
                    </a-form>
                </a-card>
            </a-col>
            <a-col :xs="24" :lg="10">
                <a-card title="Cambiar contraseña" :bordered="false">
                    <a-form layout="vertical" @submit.prevent="pwd.put(route('perfil.password'), { preserveScroll: true, onSuccess: () => pwd.reset() })">
                        <a-form-item label="Contraseña actual" v-bind="err(pwd, 'current_password')"><a-input-password v-model:value="pwd.current_password" /></a-form-item>
                        <a-form-item label="Nueva contraseña" v-bind="err(pwd, 'password')" :help="pwd.errors.password || 'Mínimo 8 caracteres, con letras y números.'"><a-input-password v-model:value="pwd.password" /></a-form-item>
                        <a-form-item label="Confirmar contraseña"><a-input-password v-model:value="pwd.password_confirmation" /></a-form-item>
                        <a-button type="primary" html-type="submit" :loading="pwd.processing"><template #icon><LockOutlined /></template>Actualizar contraseña</a-button>
                    </a-form>
                </a-card>
            </a-col>
        </a-row>
    </AppLayout>
</template>
