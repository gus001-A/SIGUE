<script setup>
/** Alta / edición del expediente del alumno en un modal de dos pestañas. */
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import {
    UserAddOutlined, EditOutlined, IdcardOutlined, HomeOutlined, MailOutlined, PhoneOutlined, NumberOutlined,
    ExclamationCircleFilled, AlertOutlined, CameraOutlined, ReadOutlined,
} from '@ant-design/icons-vue';
import SgModal from '@/Components/SgModal.vue';
import DateField from '@/Components/DateField.vue';
import FileInput from '@/Components/FileInput.vue';
import AddressFields from '@/Components/AddressFields.vue';

const props = defineProps({
    open: Boolean,
    student: { type: Object, default: null }, // null = alta
    options: { type: Object, required: true }, // statuses, groups
});
const emit = defineEmits(['update:open']);
const tab = ref('personal');

const blank = () => ({
    enrollment_number: '', name: '', paternal_surname: '', maternal_surname: '', birth_date: null, curp: '', gender: null,
    email: '', phone: '', address: '', postal_code: '', colony: '', municipality: '', state: '', emergency_contact_name: '', emergency_contact_phone: '',
    status: 'activo', photo_publication_consent: false, photo: null, group_id: null,
});
const form = useForm(blank());
watch(() => props.open, (v) => {
    if (!v) return;
    tab.value = 'personal';
    form.clearErrors();
    Object.assign(form, blank(), props.student ? Object.fromEntries(Object.keys(blank()).map((k) => [k, props.student[k] ?? blank()[k]])) : {});
    form.photo = null;
});

const PERSONAL = ['name', 'paternal_surname', 'maternal_surname', 'birth_date', 'curp', 'gender', 'email', 'phone', 'photo'];
const hasErr = (t) => Object.keys(form.errors).some((k) => (t === 'personal') === PERSONAL.includes(k));
const err = (k) => ({ 'validate-status': form.errors[k] ? 'error' : undefined, help: form.errors[k] });

function save() {
    const opts = {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
        onError: () => (tab.value = hasErr('personal') ? 'personal' : 'escolar'),
    };
    props.student ? form.post(route('alumnos.update', props.student.id), opts) : form.post(route('alumnos.store'), opts);
}
</script>

<template>
    <SgModal :open="open" :title="student ? 'Editar expediente' : 'Registrar alumno'" :subtitle="student ? `${student.name} ${student.paternal_surname}` : 'La matrícula se asigna automáticamente si la dejas vacía'"
        :icon="student ? EditOutlined : UserAddOutlined" width="min(940px, 96vw)" :loading="form.processing" @update:open="emit('update:open', $event)" @ok="save">
        <a-form layout="vertical">
            <a-tabs v-model:active-key="tab">
                <a-tab-pane key="personal">
                    <template #tab><span class="sg-tab"><IdcardOutlined /> Datos personales <ExclamationCircleFilled v-if="hasErr('personal')" style="color: #e3262b" /></span></template>
                    <div class="sg-grid-2">
                        <a-form-item label="Nombre(s)" required v-bind="err('name')"><a-input v-model:value="form.name"><template #prefix><IdcardOutlined style="color: #1d7fc9" /></template></a-input></a-form-item>
                        <a-form-item label="Apellido paterno" required v-bind="err('paternal_surname')"><a-input v-model:value="form.paternal_surname" /></a-form-item>
                        <a-form-item label="Apellido materno" v-bind="err('maternal_surname')"><a-input v-model:value="form.maternal_surname" /></a-form-item>
                        <a-form-item label="Fecha de nacimiento" v-bind="err('birth_date')"><DateField v-model:value="form.birth_date" limite="nacimiento" /></a-form-item>
                        <a-form-item label="CURP" v-bind="err('curp')"><a-input v-upper v-model:value="form.curp" :maxlength="18" style="text-transform: uppercase" /></a-form-item>
                        <a-form-item label="Sexo" v-bind="err('gender')">
                            <a-select v-model:value="form.gender" allow-clear placeholder="Selecciona" :options="[{ value: 'F', label: 'Mujer' }, { value: 'M', label: 'Hombre' }, { value: 'X', label: 'No binario' }]" />
                        </a-form-item>
                        <a-form-item label="Correo" v-bind="err('email')"><a-input v-model:value="form.email"><template #prefix><MailOutlined style="color: #e0457b" /></template></a-input></a-form-item>
                        <a-form-item label="Teléfono" v-bind="err('phone')"><a-input v-digits="10" v-model:value="form.phone" placeholder="10 dígitos"><template #prefix><PhoneOutlined style="color: #2e9b43" /></template></a-input></a-form-item>
                        <a-form-item label="Fotografía" class="full" v-bind="err('photo')"><FileInput v-model="form.photo" accept="image/*" hint="JPG o PNG · máx. 3 MB" :current-url="student?.photo_url" current-name="Fotografía actual" /></a-form-item>
                    </div>
                </a-tab-pane>
                <a-tab-pane key="escolar">
                    <template #tab><span class="sg-tab"><ReadOutlined /> Escolar y contacto <ExclamationCircleFilled v-if="hasErr('escolar')" style="color: #e3262b" /></span></template>
                    <div class="sg-grid-2">
                        <a-form-item label="Matrícula" v-bind="err('enrollment_number')"><a-input v-model:value="form.enrollment_number" placeholder="Automática"><template #prefix><NumberOutlined style="color: #7c4ddb" /></template></a-input></a-form-item>
                        <a-form-item label="Estado" v-bind="err('status')"><a-select v-model:value="form.status" :options="options.statuses" /></a-form-item>
                        <AddressFields :form="form" :errors="form.errors" class="full" />
                        <a-form-item label="Contacto de emergencia" v-bind="err('emergency_contact_name')"><a-input v-model:value="form.emergency_contact_name"><template #prefix><AlertOutlined style="color: #e3262b" /></template></a-input></a-form-item>
                        <a-form-item label="Teléfono de emergencia" v-bind="err('emergency_contact_phone')"><a-input v-digits="10" v-model:value="form.emergency_contact_phone" placeholder="10 dígitos" /></a-form-item>
                        <a-form-item v-if="!student" label="Inscribir en grupo (queda pendiente de autorización)" v-bind="err('group_id')">
                            <a-select v-model:value="form.group_id" allow-clear show-search option-filter-prop="label" :options="options.groups" placeholder="Opcional" />
                        </a-form-item>
                        <a-form-item label="Anuario" :class="student ? 'full' : ''">
                            <a-checkbox v-model:checked="form.photo_publication_consent"><CameraOutlined /> Autoriza publicar su fotografía</a-checkbox>
                        </a-form-item>
                    </div>
                </a-tab-pane>
            </a-tabs>
        </a-form>
    </SgModal>
</template>
