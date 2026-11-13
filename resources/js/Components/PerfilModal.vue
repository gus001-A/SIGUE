<script setup>
/** Mi perfil (datos y contraseña) en un modal. */
import { ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { UserOutlined, MailOutlined, PhoneOutlined, LockOutlined, KeyOutlined, IdcardOutlined, SaveOutlined } from '@ant-design/icons-vue';
import SgModal from '@/Components/SgModal.vue';

const props = defineProps({ open: Boolean });
const emit = defineEmits(['update:open']);
const page = usePage();
const tab = ref('datos');

const form = useForm({ name: '', paternal_surname: '', maternal_surname: '', email: '', phone: '' });
const pwd = useForm({ current_password: '', password: '', password_confirmation: '' });
watch(() => props.open, (v) => {
    if (!v) return;
    const u = page.props.auth.user;
    Object.assign(form, { name: u.given_name, paternal_surname: u.paternal_surname, maternal_surname: u.maternal_surname, email: u.email, phone: u.phone });
    form.clearErrors(); pwd.reset(); pwd.clearErrors();
    tab.value = page.props.auth.user.must_change_password ? 'pwd' : 'datos';
});
const err = (f, k) => ({ 'validate-status': f.errors[k] ? 'error' : undefined, help: f.errors[k] });
function save() {
    const o = { preserveScroll: true, onSuccess: () => emit('update:open', false) };
    tab.value === 'datos' ? form.put(route('perfil.update'), o) : pwd.put(route('perfil.password'), o);
}
</script>

<template>
    <SgModal :open="open" title="Mi perfil" :subtitle="`Último acceso: ${page.props.auth.user?.last_login ?? '—'}`" :icon="UserOutlined"
        width="min(720px, 96vw)" :ok-text="tab === 'datos' ? 'Guardar datos' : 'Cambiar contraseña'" :ok-icon="tab === 'datos' ? SaveOutlined : KeyOutlined"
        :loading="form.processing || pwd.processing" @update:open="emit('update:open', $event)" @ok="save">
        <a-tabs v-model:active-key="tab">
            <a-tab-pane key="datos"><template #tab><span class="sg-tab"><IdcardOutlined /> Datos personales</span></template>
                <a-form layout="vertical" class="sg-grid-2">
                    <a-form-item label="Nombre(s)" v-bind="err(form, 'name')"><a-input v-model:value="form.name"><template #prefix><UserOutlined style="color: #1d7fc9" /></template></a-input></a-form-item>
                    <a-form-item label="Apellido paterno" v-bind="err(form, 'paternal_surname')"><a-input v-model:value="form.paternal_surname" /></a-form-item>
                    <a-form-item label="Apellido materno" v-bind="err(form, 'maternal_surname')"><a-input v-model:value="form.maternal_surname" /></a-form-item>
                    <a-form-item label="Teléfono" v-bind="err(form, 'phone')"><a-input v-digits="10" v-model:value="form.phone" placeholder="10 dígitos"><template #prefix><PhoneOutlined style="color: #2e9b43" /></template></a-input></a-form-item>
                    <a-form-item label="Correo" class="full" v-bind="err(form, 'email')"><a-input v-model:value="form.email"><template #prefix><MailOutlined style="color: #e0457b" /></template></a-input></a-form-item>
                </a-form>
            </a-tab-pane>
            <a-tab-pane key="pwd"><template #tab><span class="sg-tab"><LockOutlined /> Contraseña</span></template>
                <a-form layout="vertical" class="sg-grid-2">
                    <a-form-item label="Contraseña actual" class="full" v-bind="err(pwd, 'current_password')"><a-input-password v-model:value="pwd.current_password" /></a-form-item>
                    <a-form-item label="Nueva contraseña" v-bind="err(pwd, 'password')"><a-input-password v-model:value="pwd.password" /></a-form-item>
                    <a-form-item label="Confirmar contraseña"><a-input-password v-model:value="pwd.password_confirmation" /></a-form-item>
                </a-form>
                <p class="sg-muted" style="margin: 0">Mínimo 8 caracteres, con letras y números.</p>
            </a-tab-pane>
        </a-tabs>
    </SgModal>
</template>
