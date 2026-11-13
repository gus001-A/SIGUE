<script setup>
import { useForm } from '@inertiajs/vue3';
import { LockOutlined, CheckOutlined } from '@ant-design/icons-vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const props = defineProps({ token: String, email: String });
const form = useForm({ token: props.token, email: props.email ?? '', password: '', password_confirmation: '' });

function submit() {
    form.post(route('password.update'), { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <AuthLayout title="Nueva contraseña">
        <template #title>Crea tu nueva contraseña</template>
        <template #subtitle>{{ form.email }}</template>

        <a-form layout="vertical" @submit.prevent="submit">
            <a-alert v-if="form.errors.email" type="error" :message="form.errors.email" show-icon style="margin-bottom: 14px" />
            <a-form-item label="Nueva contraseña" :validate-status="form.errors.password ? 'error' : ''" :help="form.errors.password || 'Mínimo 8 caracteres, con letras y números.'">
                <a-input-password v-model:value="form.password" size="large"><template #prefix><LockOutlined /></template></a-input-password>
            </a-form-item>
            <a-form-item label="Confirmar contraseña">
                <a-input-password v-model:value="form.password_confirmation" size="large" @press-enter="submit"><template #prefix><LockOutlined /></template></a-input-password>
            </a-form-item>
            <a-button type="primary" size="large" block :loading="form.processing" html-type="submit">
                <template #icon><CheckOutlined /></template>
                Guardar contraseña
            </a-button>
        </a-form>
    </AuthLayout>
</template>
