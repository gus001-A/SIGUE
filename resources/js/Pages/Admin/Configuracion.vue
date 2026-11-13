<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import {
    SettingOutlined, SaveOutlined, BankOutlined, RiseOutlined, MailOutlined, PhoneOutlined, 
    IdcardOutlined, EnvironmentOutlined, BgColorsOutlined, CameraOutlined, CoffeeOutlined, ClockCircleOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHead from '@/Components/PageHead.vue';
import AddressFields from '@/Components/AddressFields.vue';

const props = defineProps({ institution: Object, settings: Object, notifyOptions: Array, campus: { type: Object, default: null } });

// Receso por turno del plantel activo: hora de inicio + duración (el fin se calcula)
const toMin = (t) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
const toHHMM = (n) => `${String(Math.floor(n / 60) % 24).padStart(2, '0')}:${String(n % 60).padStart(2, '0')}`;
const breaks = useForm({
    breaks: (props.campus?.breaks ?? []).map((b) => ({ ...b, duration: b.start && b.end ? toMin(b.end) - toMin(b.start) : null })),
});
const DURATIONS = [10, 15, 20, 25, 30, 40, 45, 60].map((m) => ({ value: m, label: m === 60 ? '1 hora' : `${m} minutos` }));
const SHIFT_COLOR = { matutino: '#f39200', vespertino: '#7c4ddb', nocturno: '#0b3f6e', mixto: '#14a39a' };
function syncEnd(b) {
    if (b.start && !b.duration) b.duration = 30;
    b.end = b.start && b.duration ? toHHMM(toMin(b.start) + b.duration) : null;
}
const clearBreak = (b) => { b.start = null; b.end = null; b.duration = null; };
const breaksPayload = () => ({ breaks: breaks.breaks.map(({ shift, start, end }) => ({ shift, start, end })) });

const form = useForm({
    name: props.institution.name, short_name: props.institution.short_name, cct: props.institution.cct, rfc: props.institution.rfc,
    email: props.institution.email, phone: props.institution.phone, address: props.institution.address,
    postal_code: props.institution.postal_code, colony: props.institution.colony, municipality: props.institution.municipality, state: props.institution.state,
    logo: null,
    notify: props.settings.notify, passing_score: props.settings.passing_score,
});
const logoPreview = ref(props.institution.logo_url);
const err = (k) => ({ 'validate-status': form.errors[k] ? 'error' : undefined, help: form.errors[k] });
function pickLogo(e) { const f = e.target.files?.[0]; form.logo = f ?? null; if (f) logoPreview.value = URL.createObjectURL(f); }
const save = () => form.post(route('configuracion.update'), {
    forceFormData: true, preserveScroll: true,
    onSuccess: () => { if (props.campus && breaks.isDirty) breaks.transform(breaksPayload).put(route('configuracion.receso'), { preserveScroll: true, onSuccess: () => breaks.defaults() }); },
});
</script>

<template>
    <AppLayout title="Configuración">
        <PageHead title="Gestión y configuración" :subtitle="`Plan ${institution.plan} · licencia vigente hasta ${institution.license_expires_at ?? 'sin vencimiento'}`" :icon="SettingOutlined" />

        <a-card :bordered="false" class="config-card" :body-style="{ padding: '32px 40px' }">
            <a-form layout="vertical">
                
                <div class="two-column-layout">
                    
                    <!-- ============ COLUMNA IZQUIERDA ============ -->
                    <div class="col">
                        <!-- SECCIÓN: IDENTIDAD VISUAL -->
                        <div class="section-title"><BgColorsOutlined /> Identidad visual</div>
                        <div class="identity-row">
                            <label class="logo-upload">
                                <img v-if="logoPreview" :src="logoPreview" alt="Logotipo" />
                                <div v-else class="logo-placeholder">
                                    <CameraOutlined />
                                    <span>Subir logo</span>
                                </div>
                                <input type="file" accept="image/*" @change="pickLogo" />
                            </label>
                            <div class="identity-fields">
                                <a-form-item label="Nombre de la institución" required v-bind="err('name')">
                                    <a-input v-model:value="form.name" size="large"><template #prefix><BankOutlined style="color: #1d7fc9" /></template></a-input>
                                </a-form-item>
                                <a-form-item label="Nombre corto" v-bind="err('short_name')">
                                    <a-input v-model:value="form.short_name" size="large" />
                                </a-form-item>
                            </div>
                        </div>
                        <div v-if="form.errors.logo" class="ant-form-item-explain-error" style="margin-top: 8px;">{{ form.errors.logo }}</div>

                        <!-- SECCIÓN: DATOS FISCALES Y CONTACTO -->
                        <div class="section-title" style="margin-top: 32px"><IdcardOutlined /> Datos fiscales y contacto</div>
                        <a-row :gutter="16">
                            <a-col :xs="24" :md="12">
                                <a-form-item label="CCT" v-bind="err('cct')">
                                    <a-input v-model:value="form.cct" size="large" />
                                </a-form-item>
                            </a-col>
                            <a-col :xs="24" :md="12">
                                <a-form-item label="RFC" v-bind="err('rfc')">
                                    <a-input v-upper v-model:value="form.rfc" size="large" />
                                </a-form-item>
                            </a-col>
                            <a-col :xs="24" :md="12">
                                <a-form-item label="Teléfono" v-bind="err('phone')">
                                    <a-input v-digits="10" v-model:value="form.phone" placeholder="10 dígitos" size="large"><template #prefix><PhoneOutlined style="color: #2e9b43" /></template></a-input>
                                </a-form-item>
                            </a-col>
                            <a-col :xs="24" :md="12">
                                <a-form-item label="Correo electrónico" v-bind="err('email')">
                                    <a-input v-model:value="form.email" size="large"><template #prefix><MailOutlined style="color: #e0457b" /></template></a-input>
                                </a-form-item>
                            </a-col>
                        </a-row>
                        <!-- SECCIÓN: RECESO POR TURNO -->
                        <template v-if="campus">
                            <div class="section-title" style="margin-top: 32px"><CoffeeOutlined /> Receso por turno · {{ campus.name }}</div>
                            <p class="sg-muted" style="margin: -8px 0 12px">Elige la hora de inicio y cuánto dura. Aparece en los horarios y no se pueden programar clases en esa franja.</p>
                            <div class="breaks">
                                <div v-for="(b, i) in breaks.breaks" :key="b.shift" class="brk" :style="{ '--c': SHIFT_COLOR[b.shift] }" :class="{ on: b.start && b.end }">
                                    <div class="brk__head">
                                        <span class="brk__ic"><ClockCircleOutlined /></span><b>{{ b.label }}</b>
                                        <a v-if="b.start" class="brk__clear" @click="clearBreak(b)">Quitar</a>
                                    </div>
                                    <div class="brk__times">
                                        <a-time-picker v-model:value="b.start" format="HH:mm" value-format="HH:mm" :minute-step="5" placeholder="Inicio" @change="syncEnd(b)" />
                                        <a-select v-model:value="b.duration" :options="DURATIONS" placeholder="Duración" :disabled="!b.start" @change="syncEnd(b)" />
                                    </div>
                                    <small class="brk__sum">
                                        <template v-if="b.start && b.end">De <b>{{ b.start }}</b> a <b>{{ b.end }}</b> · {{ b.duration }} min</template>
                                        <template v-else>Sin receso</template>
                                    </small>
                                    <div v-if="breaks.errors[`breaks.${i}.end`]" class="ant-form-item-explain-error">{{ breaks.errors[`breaks.${i}.end`] }}</div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- ============ COLUMNA DERECHA ============ -->
                    <div class="col">
                        <!-- SECCIÓN: DIRECCIÓN -->
                        <div class="section-title"><EnvironmentOutlined /> Dirección</div>
                        <AddressFields :form="form" :errors="form.errors" />

                        <!-- SECCIÓN: EVALUACIÓN -->
                        <div class="section-title" style="margin-top: 32px"><RiseOutlined /> Evaluación</div>
                        <a-form-item label="Calificación mínima aprobatoria (0–10)" v-bind="err('passing_score')">
                            <a-input-number v-model:value="form.passing_score" :min="0" :max="10" :step="0.5" size="large" style="width: 100%" />
                        </a-form-item>

                        <!-- SECCIÓN: NOTIFICACIONES (3 COLUMNAS CUADRADAS) -->
                        <div class="section-title" style="margin-top: 32px"><MailOutlined /> Notificaciones automáticas</div>
                        <p class="sg-muted" style="margin-top: -6px; margin-bottom: 12px;">Avisos que se envían a padres y alumnos.</p>
                        
                        <a-checkbox-group v-model:value="form.notify" class="notify-group">
                            <label v-for="opt in notifyOptions" :key="opt.value" class="notify-square">
                                <a-checkbox :value="opt.value" />
                                <span class="notify-label" :title="opt.label">{{ opt.label }}</span>
                            </label>
                        </a-checkbox-group>
                    </div>
                </div>

                <!-- BOTÓN GUARDAR -->
                <div style="margin-top: 40px; display: flex; justify-content: flex-end; border-top: 1px solid #e2e8f0; padding-top: 24px;">
                    <a-button type="primary" size="large" :loading="form.processing" @click="save" class="save-btn">
                        <template #icon><SaveOutlined /></template>Guardar configuración
                    </a-button>
                </div>
            </a-form>
        </a-card>

    </AppLayout>
</template>

<style scoped>
/* ============ TARJETA PRINCIPAL ============ */
.config-card {
    border-radius: 16px;
    box-shadow: 0 4px 12px -4px rgba(0,0,0,0.03);
    border: 1px solid #e2e8f0;
    width: 100%;
}

/* ============ LAYOUT DE 2 COLUMNAS ============ */
.two-column-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 48px;
}
@media (max-width: 1100px) {
    .two-column-layout {
        grid-template-columns: 1fr;
        gap: 20px;
    }
}

/* ============ TÍTULOS DE SECCIÓN ============ */
.section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 800;
    color: #1d7fc9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 20px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e2e8f0;
}

/* ============ IDENTIDAD VISUAL ============ */
.identity-row {
    display: flex;
    gap: 24px;
    align-items: flex-start;
}
.identity-fields {
    flex: 1;
    min-width: 0;
}

.logo-upload {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 180px;
    height: 180px;
    border-radius: 20px;
    border: 2px dashed #cbd5e1;
    background: #f8fafc;
    cursor: pointer;
    overflow: hidden;
    transition: all 0.2s;
    flex: none;
}
.logo-upload:hover {
    border-color: #1d7fc9;
    background: #eff6ff;
}
.logo-upload img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
.logo-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    color: #64748b;
}
.logo-placeholder .anticon {
    font-size: 42px;
    color: #94a3b8;
}
.logo-placeholder span {
    font-size: 13px;
    font-weight: 600;
}
.logo-upload input {
    display: none;
}

/* ============ NOTIFICACIONES EN CUADRÍCULA 3x3 ============ */
.notify-group {
    display: grid;
    grid-template-columns: repeat(3, 1fr); /* 3 columnas exactas */
    gap: 10px;
}

.notify-square {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    height: 90px; /* Altura fija para que sean cuadrados */
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
    padding: 10px;
    text-align: center;
}

.notify-square:hover {
    border-color: #1d7fc9;
    background: #f8fafc;
    box-shadow: 0 2px 6px rgba(29, 127, 201, 0.08);
}

.notify-square :deep(.ant-checkbox-wrapper) {
    margin: 0;
}

.notify-label {
    font-size: 11.5px;
    font-weight: 600;
    color: #475569;
    line-height: 1.2;
    display: -webkit-box;
    -webkit-line-clamp: 2; /* Máximo 2 líneas para mantener cuadrados */
    -webkit-box-orient: vertical;
    overflow: hidden;
    white-space: normal;
    word-break: break-word;
}

/* ============ RECESO ============ */
.breaks { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.brk { padding: 10px 12px; border-radius: 12px; border: 1px solid #e2e8f0; background: #fafbfd; border-left: 4px solid #cbd5e1; display: flex; flex-direction: column; gap: 8px; }
.brk.on { border-left-color: var(--c); background: color-mix(in srgb, var(--c) 4%, #fff); }
.brk__head { display: flex; align-items: center; gap: 8px; }
.brk__head b { flex: 1; color: #0b2f55; font-size: 13px; }
.brk__ic { width: 24px; height: 24px; border-radius: 7px; display: grid; place-items: center; color: #fff; background: var(--c); font-size: 12px; }
.brk__times { display: grid; grid-template-columns: 1fr 1.2fr; gap: 6px; }
.brk__times :deep(.ant-picker), .brk__times :deep(.ant-select) { width: 100%; }
.brk__sum { font-size: 11.5px; color: #94a3b8; }
.brk.on .brk__sum { color: #475569; }
.brk__sum b { color: var(--c); }
.brk__clear { font-size: 11.5px; color: #e3262b; }
@media (max-width: 760px) { .breaks { grid-template-columns: 1fr; } }

/* ============ BOTÓN GUARDAR ============ */
.save-btn {
    border-radius: 10px;
    font-weight: 700;
    height: 50px;
    padding: 0 36px;
    background: linear-gradient(135deg, #1d7fc9, #0f5a99);
    box-shadow: 0 4px 12px rgba(29, 127, 201, 0.3);
}
.save-btn:hover {
    background: linear-gradient(135deg, #0f5a99, #0b3f6e);
}

/* ============ INPUTS ============ */
:deep(.ant-input), :deep(.ant-input-affix-wrapper), :deep(.ant-select-selector), :deep(.ant-input-number) {
    border-radius: 8px !important;
    background: #f8fafc !important;
    border-color: #e2e8f0 !important;
}
:deep(.ant-input:focus), :deep(.ant-input-affix-wrapper-focused), :deep(.ant-select-focused .ant-select-selector), :deep(.ant-input-number-focused) {
    background: #fff !important;
    border-color: #1d7fc9 !important;
    box-shadow: 0 0 0 3px rgba(29, 127, 201, 0.15) !important;
}
</style>