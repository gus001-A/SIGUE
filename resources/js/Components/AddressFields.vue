<script setup>
/**
 * Domicilio con autocompletado por código postal (catálogo SEPOMEX).
 * Al capturar los 5 dígitos del CP se llenan estado y municipio, y la colonia
 * se elige de la lista de colonias de ese CP (también se puede escribir a mano).
 *
 * Uso: <AddressFields :form="form" :errors="form.errors" class="full" />
 */
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import {
    EnvironmentOutlined, HomeOutlined, LoadingOutlined, CheckCircleFilled, ExclamationCircleFilled, BankOutlined, CompassOutlined,
} from '@ant-design/icons-vue';

const props = defineProps({
    form: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    keys: {
        type: Object,
        default: () => ({ street: 'address', cp: 'postal_code', colony: 'colony', municipality: 'municipality', state: 'state' }),
    },
    streetLabel: { type: String, default: 'Calle y número' },
    disabled: { type: Boolean, default: false },
});

const k = computed(() => props.keys);
const status = ref('idle'); // idle | loading | found | notfound
const colonies = ref([]);
const message = ref('');
let lastCp = null;

const colonyOptions = computed(() => {
    const q = (props.form[k.value.colony] ?? '').toString().toLowerCase();
    return colonies.value.filter((c) => !q || c.toLowerCase().includes(q)).map((c) => ({ value: c }));
});

async function lookup(cp, { fill = true } = {}) {
    if (!/^\d{5}$/.test(cp ?? '') || cp === lastCp) return;
    lastCp = cp;
    status.value = 'loading';
    try {
        const { data } = await axios.get(route('codigos-postales.show', cp));
        if (cp !== props.form[k.value.cp]) return; // el usuario ya cambió el CP
        if (!data.found) {
            status.value = 'notfound';
            colonies.value = [];
            message.value = data.message;
            return;
        }
        status.value = 'found';
        colonies.value = data.colonies;
        message.value = `${data.colonies.length} colonia${data.colonies.length === 1 ? '' : 's'} · ${data.municipality}, ${data.state}`;
        if (fill) {
            props.form[k.value.state] = data.state;
            props.form[k.value.municipality] = data.municipality;
            const current = props.form[k.value.colony];
            if (data.colonies.length === 1) props.form[k.value.colony] = data.colonies[0];
            else if (current && !data.colonies.includes(current)) props.form[k.value.colony] = null;
        }
    } catch (e) {
        status.value = 'notfound';
        colonies.value = [];
        message.value = e.response?.data?.message ?? 'No se pudo consultar el código postal.';
    }
}

function onCpInput(v) {
    const clean = (v ?? '').toString().replace(/\D/g, '').slice(0, 5);
    props.form[k.value.cp] = clean;
    if (clean.length < 5) {
        lastCp = null;
        status.value = 'idle';
        message.value = '';
    }
}

watch(() => props.form[k.value.cp], (cp) => lookup(cp));
onMounted(() => lookup(props.form[k.value.cp], { fill: !props.form[k.value.state] }));

const err = (key) => (props.errors?.[key] ? { validateStatus: 'error', help: props.errors[key] } : {});
</script>

<template>
    <div class="addr">
        <div class="addr__head">
            <span class="addr__ic"><EnvironmentOutlined /></span>
            <b>Domicilio</b>
            <span v-if="status === 'found'" class="addr__msg addr__msg--ok"><CheckCircleFilled /> {{ message }}</span>
            <span v-else-if="status === 'notfound'" class="addr__msg addr__msg--warn"><ExclamationCircleFilled /> {{ message }}</span>
            <span v-else class="addr__msg">Escribe el código postal y se llenan estado, municipio y colonias.</span>
        </div>

        <div class="addr__grid">
            <a-form-item label="Código postal" v-bind="err(k.cp)" class="addr__cp">
                <a-input :value="form[k.cp]" :disabled="disabled" placeholder="00000" :maxlength="5" inputmode="numeric" @update:value="onCpInput">
                    <template #prefix><CompassOutlined style="color: #f39200" /></template>
                    <template #suffix>
                        <LoadingOutlined v-if="status === 'loading'" style="color: #1d7fc9" />
                        <CheckCircleFilled v-else-if="status === 'found'" style="color: #2e9b43" />
                        <ExclamationCircleFilled v-else-if="status === 'notfound'" style="color: #f39200" />
                    </template>
                </a-input>
            </a-form-item>

            <a-form-item label="Estado" v-bind="err(k.state)">
                <a-input v-model:value="form[k.state]" :disabled="disabled" placeholder="Se llena con el CP" :class="{ 'is-auto': status === 'found' }">
                    <template #prefix><BankOutlined style="color: #7c4ddb" /></template>
                </a-input>
            </a-form-item>

            <a-form-item label="Municipio o alcaldía" v-bind="err(k.municipality)">
                <a-input v-model:value="form[k.municipality]" :disabled="disabled" placeholder="Se llena con el CP" :class="{ 'is-auto': status === 'found' }">
                    <template #prefix><EnvironmentOutlined style="color: #14a39a" /></template>
                </a-input>
            </a-form-item>

            <a-form-item label="Colonia" v-bind="err(k.colony)">
                <a-auto-complete v-model:value="form[k.colony]" :options="colonyOptions" :disabled="disabled"
                    :placeholder="colonies.length ? 'Elige o escribe la colonia' : 'Colonia'" allow-clear :class="{ 'is-auto': status === 'found' }" />
            </a-form-item>

            <a-form-item :label="streetLabel" v-bind="err(k.street)" class="addr__street">
                <a-input v-model:value="form[k.street]" :disabled="disabled" placeholder="Ej. Av. Juárez 120, int. 4">
                    <template #prefix><HomeOutlined style="color: #e0457b" /></template>
                </a-input>
            </a-form-item>
        </div>
    </div>
</template>

<style scoped>
.addr { border: 1px solid #e2e8f0; border-radius: 14px; padding: 10px 14px 0; background: linear-gradient(180deg, #fbfdff, #fff); margin-bottom: 14px; }
.addr__head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
.addr__ic { width: 26px; height: 26px; border-radius: 8px; display: grid; place-items: center; color: #fff; font-size: 13px; background: linear-gradient(135deg, #f39200, #e3262b); flex: none; }
.addr__head b { color: #0b2f55; font-size: 13.5px; }
.addr__msg { font-size: 12px; color: #94a3b8; margin-left: auto; display: inline-flex; align-items: center; gap: 5px; }
.addr__msg--ok { color: #1f7a32; }
.addr__msg--warn { color: #b45309; }
.addr__grid { display: grid; grid-template-columns: 150px repeat(3, minmax(0, 1fr)); gap: 0 12px; }
.addr__street { grid-column: 1 / -1; }
.addr :deep(.ant-form-item) { margin-bottom: 12px; }
.addr :deep(.ant-select) { width: 100%; }
.addr :deep(.is-auto.ant-input-affix-wrapper), .addr :deep(.is-auto .ant-select-selector) { background: #f3fbf5 !important; }
@media (max-width: 760px) {
    .addr__grid { grid-template-columns: 1fr 1fr; }
    .addr__cp { grid-column: 1 / -1; }
    .addr__msg { margin-left: 0; }
}
</style>
