<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { MailOutlined, SendOutlined, ArrowLeftOutlined } from '@ant-design/icons-vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const form = useForm({ email: '' });

function submit() {
    form.post(route('password.email'), { preserveScroll: true, onSuccess: () => form.reset() });
}
</script>

<template>
    <AuthLayout title="Recuperar contraseña">
        <template #title>¿Olvidaste tu contraseña?</template>
        <template #subtitle>Te enviaremos un enlace para crear una nueva</template>

        <a-form layout="vertical" @submit.prevent="submit">
            <a-form-item :validate-status="form.errors.email ? 'error' : ''" :help="form.errors.email">
                <a-input v-model:value="form.email" type="email" size="large" placeholder="Correo electrónico" @press-enter="submit">
                    <template #prefix><MailOutlined /></template>
                </a-input>
            </a-form-item>
            <a-button type="primary" size="large" block :loading="form.processing" html-type="submit">
                <template #icon><SendOutlined /></template>
                Enviar enlace
            </a-button>
        </a-form>

        <template #footer>
            <Link :href="route('login')" style="font-weight: 600"><ArrowLeftOutlined /> Volver a iniciar sesión</Link>
        </template>
    </AuthLayout>
</template>
