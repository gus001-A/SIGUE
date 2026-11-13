<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { MailOutlined, LockOutlined, LoginOutlined, SafetyOutlined, CrownOutlined, BankOutlined, SettingOutlined, ReadOutlined, HeartOutlined, UserOutlined, ExperimentOutlined, DownloadOutlined } from '@ant-design/icons-vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import InstalarApp from '@/Components/InstalarApp.vue';
import { pwa } from '@/lib/pwa';
import { ref as vref } from 'vue';
const installer = vref(null);

defineProps({ demo: { type: Array, default: () => [] } });
const form = useForm({ email: '', password: '', remember: false });

/** Accesos rápidos de prueba (solo en desarrollo, igual que en RIC Condominios). */
const ROLES = {
    superadmin: { icon: CrownOutlined, c: '#0b3f6e' }, director: { icon: BankOutlined, c: '#7c4ddb' }, admin: { icon: SettingOutlined, c: '#1d7fc9' },
    docente: { icon: ReadOutlined, c: '#2e9b43' }, tutor: { icon: HeartOutlined, c: '#e0457b' }, alumno: { icon: UserOutlined, c: '#f39200' },
};
function demoLogin(u) {
    form.email = u.email;
    form.password = u.password;
    submit();
}

function submit() {
    form.post(route('login'), { onError: () => form.reset('password') });
}

function loginWithGoogle() {
    window.location.href = route('auth.google.redirect');
}
</script>

<template>
    <AuthLayout title="Iniciar sesión">
        <template #title>Bienvenido de vuelta</template>
        <template #subtitle>Ingresa con la cuenta que te asignó tu institución</template>

        <!-- Botón Google -->
        <button type="button" class="google-btn" @click="loginWithGoogle">
            <svg class="google-btn__logo" viewBox="0 0 48 48" width="20" height="20" aria-hidden="true">
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
            </svg>
            <span>Continuar con Google</span>
        </button>

        <!-- Divider -->
        <div class="divider">
            <span>o continúa con tu correo</span>
        </div>

        <a-form layout="vertical" @submit.prevent="submit" class="form">
            <!-- Error global -->
            <transition name="fade">
                <div v-if="form.errors.email || form.errors.password" class="form__error">
                    <SafetyOutlined />
                    <span>Revisa tus datos e inténtalo de nuevo.</span>
                </div>
            </transition>

            <a-form-item
                :validate-status="form.errors.email ? 'error' : ''"
                :help="form.errors.email"
                class="field"
            >
                <label class="field__label">Correo electrónico</label>
                <a-input
                    v-model:value="form.email"
                    type="email"
                    size="large"
                    placeholder="tu.correo@escuela.edu.mx"
                    autocomplete="username"
                    class="field__input"
                    @press-enter="submit"
                >
                    <template #prefix><MailOutlined /></template>
                </a-input>
            </a-form-item>

            <a-form-item
                :validate-status="form.errors.password ? 'error' : ''"
                :help="form.errors.password"
                class="field"
            >
                <label class="field__label">Contraseña</label>
                <a-input-password
                    v-model:value="form.password"
                    size="large"
                    placeholder="Tu contraseña"
                    autocomplete="current-password"
                    class="field__input"
                    @press-enter="submit"
                >
                    <template #prefix><LockOutlined /></template>
                </a-input-password>
            </a-form-item>

            <div class="row">
                <a-checkbox v-model:checked="form.remember">Recordarme</a-checkbox>
                <Link :href="route('password.request')" class="link">¿Olvidaste tu contraseña?</Link>
            </div>

            <a-button
                type="primary"
                size="large"
                block
                :loading="form.processing"
                html-type="submit"
                class="submit-btn"
            >
                <template #icon><LoginOutlined /></template>
                Iniciar sesión
            </a-button>
        </a-form>

        <!-- Accesos de prueba (desarrollo) -->
        <div v-if="demo.length" class="demo">
            <div class="divider"><span><ExperimentOutlined /> Accesos de prueba · desarrollo</span></div>
            <div class="demo__grid">
                <button v-for="u in demo" :key="u.email" type="button" class="demo__btn" :style="{ '--c': ROLES[u.role]?.c }" :disabled="form.processing" @click="demoLogin(u)">
                    <span class="demo__ic"><component :is="ROLES[u.role]?.icon ?? UserOutlined" /></span>
                    <span class="demo__txt"><b>{{ u.label }}</b><small>{{ u.email }}</small></span>
                </button>
            </div>
        </div>

        <button v-if="!pwa.installed" type="button" class="install-btn" @click="installer?.open()">
            <img src="/images/pwa/icon-192.png" alt="" />
            <span><b>Instala la app de SIGUE</b><small>En tu celular o computadora, con su propio ícono</small></span>
            <DownloadOutlined />
        </button>
        <InstalarApp ref="installer" />

        <template #footer>
            ¿No tienes cuenta?
            <a href="#" class="link">Solicítala a la administración de tu escuela</a>.
        </template>
    </AuthLayout>
</template>

<style scoped>
.install-btn { display: flex; align-items: center; gap: 10px; width: 100%; margin-top: 14px; padding: 10px 12px; border-radius: 14px; border: 1px dashed #b9d4ee; background: #f5f9fe; cursor: pointer; text-align: left; font-family: inherit; color: #1d7fc9; transition: all .15s; }
.install-btn:hover { border-style: solid; border-color: #1d7fc9; background: #eef5fd; }
.install-btn img { width: 34px; height: 34px; border-radius: 9px; flex: none; }
.install-btn span { flex: 1; display: flex; flex-direction: column; }
.install-btn b { color: #0b2f55; font-size: 13px; }
.install-btn small { color: #64748b; font-size: 11.5px; }
/* ---------- Google ---------- */
.google-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    width: 100%;
    height: 48px;
    padding: 0 20px;
    border: 1px solid #dadce0;
    border-radius: 12px;
    background: #fff;
    color: #3c4043;
    font-size: 14.5px;
    font-weight: 600;
    cursor: pointer;
    transition: background .18s ease, border-color .18s ease, box-shadow .2s ease, transform .15s ease;
    font-family: inherit;
}
.google-btn:hover {
    background: #f8f9fa;
    border-color: #c4c7c5;
    box-shadow: 0 1px 3px rgba(60, 64, 67, .15), 0 4px 8px rgba(60, 64, 67, .08);
    transform: translateY(-1px);
}
.google-btn:active { transform: translateY(0); }
.google-btn__logo { flex-shrink: 0; }
.google-btn span { line-height: 1; }

/* ---------- Divider ---------- */
.divider {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 20px 0;
    color: #80868b;
    font-size: 12.5px;
    font-weight: 500;
}
.divider::before,
.divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, transparent, #e0e0e0, transparent);
}

/* ---------- Form ---------- */
.form { margin-top: 4px; }
.form__error {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    margin-bottom: 16px;
    border-radius: 10px;
    background: #fce8e6;
    border: 1px solid #f6aea9;
    color: #c5221f;
    font-size: 13px;
    font-weight: 500;
}
.form__error .anticon { font-size: 16px; }

.fade-enter-active, .fade-leave-active { transition: opacity .2s ease, transform .2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(-4px); }

/* ---------- Fields ---------- */
.field { margin-bottom: 16px; }
.field__label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #3c4043;
    margin-bottom: 6px;
}
:deep(.field__input.ant-input-affix-wrapper),
:deep(.field__input.ant-input) {
    height: 48px !important;
    padding: 0 14px !important;
    border-radius: 12px !important;
    border: 1.5px solid #dadce0 !important;
    background: #fff !important;
    transition: border-color .18s ease, box-shadow .18s ease, background .18s ease !important;
}
:deep(.field__input.ant-input-affix-wrapper:hover),
:deep(.field__input.ant-input:hover) {
    border-color: #bdc1c6 !important;
    background: #fafafa !important;
}
:deep(.field__input.ant-input-affix-wrapper-focused),
:deep(.field__input.ant-input:focus) {
    border-color: #1a73e8 !important;
    box-shadow: 0 0 0 3px rgba(26, 115, 232, .15) !important;
    background: #fff !important;
}
:deep(.field__input .anticon) {
    color: #5f6368;
    font-size: 16px;
    transition: color .18s ease;
}
:deep(.field__input.ant-input-affix-wrapper-focused .anticon) {
    color: #1a73e8;
}
:deep(.field__input .ant-input::placeholder) {
    color: #9aa0a6;
    font-weight: 400;
}
:deep(.field .ant-form-item-explain-error) {
    font-size: 12px;
    margin-top: 6px;
    color: #d93025;
    font-weight: 500;
}

/* ---------- Row ---------- */
.row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 4px 0 20px;
    font-size: 13.5px;
    gap: 12px;
    flex-wrap: wrap;
}
:deep(.ant-checkbox-wrapper) {
    font-size: 13.5px;
    color: #3c4043;
}
:deep(.ant-checkbox-checked .ant-checkbox-inner) {
    background-color: #1a73e8 !important;
    border-color: #1a73e8 !important;
}
:deep(.ant-checkbox-wrapper:hover .ant-checkbox-inner) {
    border-color: #1a73e8 !important;
}

.link {
    color: #1a73e8;
    font-weight: 600;
    text-decoration: none;
    transition: color .15s ease;
}
.link:hover {
    color: #185abc;
    text-decoration: underline;
}

/* ---------- Submit ---------- */
.submit-btn {
    height: 48px !important;
    border-radius: 12px !important;
    font-weight: 600 !important;
    font-size: 15px !important;
    background: linear-gradient(135deg, #1a73e8, #1967d2) !important;
    border: none !important;
    box-shadow: 0 1px 2px rgba(26, 115, 232, .3), 0 4px 12px rgba(26, 115, 232, .25) !important;
    position: relative;
    overflow: hidden;
    isolation: isolate;
    transition: transform .15s ease, box-shadow .25s ease, background .25s ease !important;
}
.submit-btn:hover {
    background: linear-gradient(135deg, #1967d2, #185abc) !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(26, 115, 232, .45), 0 2px 6px rgba(26, 115, 232, .3) !important;
}
.submit-btn::after {
    content: '';
    position: absolute;
    top: 0; left: -120%;
    width: 60%; height: 100%;
    background: linear-gradient(120deg, transparent 0%, rgba(255, 255, 255, .4) 50%, transparent 100%);
    transform: skewX(-20deg);
    transition: left .7s cubic-bezier(.2, .8, .2, 1);
    pointer-events: none;
    z-index: 1;
}
.submit-btn:hover::after { left: 130%; }

/* ---------- Accesos de prueba ---------- */
.demo { margin-top: 18px; }
.demo__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.demo__btn { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; cursor: pointer; text-align: left; font-family: inherit; transition: all .15s; }
.demo__btn:hover:not(:disabled) { border-color: var(--c); background: color-mix(in srgb, var(--c) 6%, #fff); transform: translateY(-1px); }
.demo__btn:disabled { opacity: .6; cursor: wait; }
.demo__ic { width: 32px; height: 32px; border-radius: 10px; display: grid; place-items: center; color: #fff; background: var(--c); flex: none; }
.demo__txt { display: flex; flex-direction: column; min-width: 0; }
.demo__txt b { font-size: 13px; color: #0b2f55; }
.demo__txt small { font-size: 11px; color: #94a3b8; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* ---------- Footer link ---------- */
:deep(.auth-footer .link) { font-weight: 600; }
</style>