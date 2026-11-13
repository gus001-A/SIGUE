<script setup>
import { ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { 
    DollarOutlined, UserOutlined, WalletOutlined, NumberOutlined, CalendarOutlined, 
    CreditCardOutlined, FileTextOutlined, TagOutlined, CheckCircleOutlined, ClockCircleOutlined 
} from '@ant-design/icons-vue';
import SgModal from '@/Components/SgModal.vue';
import { money } from '@/lib/format';

/** Modal reutilizable para registrar un pago (genera recibo). */
defineProps({ options: { type: Object, required: true } });
const visible = ref(false);
const charges = ref([]);
const form = useForm({ student_id: null, charge_id: null, amount: null, method: 'efectivo', reference: '', paid_at: null, notes: '' });
const err = (k) => ({ 'validate-status': form.errors[k] ? 'error' : undefined, help: form.errors[k] });
const filterOption = (input, opt) => opt.label.toLowerCase().includes(input.toLowerCase());

async function loadCharges(id) {
    charges.value = [];
    if (!id) return;
    const { data } = await axios.get(route('finanzas.alumno.adeudos', id));
    charges.value = data;
}
watch(() => form.student_id, (id, old) => { if (old !== undefined && old !== null && id !== old) form.charge_id = null; loadCharges(id); });
watch(() => form.charge_id, (id) => { const c = charges.value.find((x) => x.value === id); if (c) form.amount = c.balance; });

function open(prefill = {}) {
    form.reset();
    form.clearErrors();
    Object.assign(form, prefill);
    visible.value = true;
    loadCharges(form.student_id);
}
defineExpose({ open });

function submit() {
    form.post(route('finanzas.pagos.store'), {
        preserveScroll: true,
        onSuccess: () => {
            visible.value = false;
            const url = usePage().props.flash?.receipt;
            if (url) window.location.href = url; // el recibo se descarga
        },
    });
}
</script>

<template>
    <SgModal v-model:open="visible" title="Registrar pago" subtitle="Al guardar se abre el recibo para imprimir" :icon="DollarOutlined" color="linear-gradient(135deg, #2e9b43, #14a39a)"
        width="min(900px, 96vw)" ok-text="Registrar y generar recibo" :ok-icon="CheckCircleOutlined" :loading="form.processing" @ok="submit">
        
        <a-form layout="vertical" class="sg-grid-2">
            
            <!-- SECCIÓN: ALUMNO -->
            <a-form-item label="Alumno" required v-bind="err('student_id')" class="full">
                <a-select v-model:value="form.student_id" show-search :filter-option="filterOption" :options="options.students" placeholder="Busca por nombre o matrícula" size="large">
                    <template #suffixIcon><UserOutlined style="color: #64748b" /></template>
                </a-select>
            </a-form-item>

            <!-- SECCIÓN: DATOS DEL PAGO -->
            <a-form-item label="Adeudo a liquidar" v-bind="err('charge_id')" :help="form.errors.charge_id || 'Vacío = pago a cuenta'">
                <a-select v-model:value="form.charge_id" allow-clear :options="charges" placeholder="Pago a cuenta" size="large">
                    <template #suffixIcon><WalletOutlined style="color: #1d7fc9" /></template>
                </a-select>
            </a-form-item>
            
            <a-form-item label="Importe" required v-bind="err('amount')">
                <a-input-number v-model:value="form.amount" :min="0.01" :precision="2" prefix="$" size="large" style="width: 100%">
                    <template #prefix><DollarOutlined style="color: #2e9b43" /></template>
                </a-input-number>
            </a-form-item>

            <a-form-item label="Forma de pago" v-bind="err('method')">
                <a-select v-model:value="form.method" :options="options.methods" size="large">
                    <template #suffixIcon><CreditCardOutlined style="color: #7c4ddb" /></template>
                </a-select>
            </a-form-item>
            
            <a-form-item label="Referencia" v-bind="err('reference')">
                <a-input v-model:value="form.reference" size="large">
                    <template #prefix><NumberOutlined style="color: #f39200" /></template>
                </a-input>
            </a-form-item>

            <a-form-item label="Fecha de pago" v-bind="err('paid_at')">
                <a-date-picker v-model:value="form.paid_at" show-time value-format="YYYY-MM-DD HH:mm" format="DD/MM/YYYY HH:mm" placeholder="Ahora" size="large" style="width: 100%">
                    <template #suffixIcon><CalendarOutlined style="color: #e0457b" /></template>
                </a-date-picker>
            </a-form-item>

            <a-form-item label="Notas" class="full" v-bind="err('notes')">
                <a-input v-model:value="form.notes" size="large" placeholder="Observaciones adicionales...">
                    <template #prefix><FileTextOutlined style="color: #64748b" /></template>
                </a-input>
            </a-form-item>
        </a-form>

        <!-- RESUMEN DEL PAGO -->
        <div v-if="form.amount" class="payment-summary">
            <div class="ps-icon"><DollarOutlined /></div>
            <div class="ps-text">
                <span>Se registrará un pago por</span>
                <b>{{ money(form.amount) }}</b>
            </div>
        </div>
    </SgModal>
</template>

<style scoped>
/* Estilos de los inputs (más grandes y coloridos) */
:deep(.ant-input-affix-wrapper), :deep(.ant-input), :deep(.ant-select-selector), :deep(.ant-picker) {
    border-radius: 10px !important;
    padding-top: 6px !important;
    padding-bottom: 6px !important;
    background: #f8fafc !important;
    border-color: #e2e8f0 !important;
    transition: all 0.2s !important;
}
:deep(.ant-input-affix-wrapper:hover), :deep(.ant-input:hover), :deep(.ant-select-selector:hover), :deep(.ant-picker:hover) {
    border-color: #cbd5e1 !important;
    background: #fff !important;
}
:deep(.ant-input-affix-wrapper-focused), :deep(.ant-input:focus), :deep(.ant-select-focused .ant-select-selector), :deep(.ant-picker-focused) {
    border-color: #2e9b43 !important;
    box-shadow: 0 0 0 3px rgba(46, 155, 67, 0.15) !important;
    background: #fff !important;
}

/* El prefijo del importe */
:deep(.ant-input-number-prefix) {
    margin-right: 6px;
}

/* Bloque de resumen */
.payment-summary {
    margin-top: 20px;
    padding: 16px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 16px;
}
.ps-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: #2e9b43;
    color: #fff;
    display: grid;
    place-items: center;
    font-size: 24px;
    box-shadow: 0 4px 10px rgba(46, 155, 67, 0.3);
}
.ps-text {
    display: flex;
    flex-direction: column;
}
.ps-text span {
    font-size: 13px;
    color: #166534;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.ps-text b {
    font-size: 24px;
    color: #166534;
    font-weight: 800;
    line-height: 1.2;
}
</style>