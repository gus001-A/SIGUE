<script setup>
/** Página de error de SIGUE (403, 404, 500, 503): reemplaza la pantalla negra de Laravel. */
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeftOutlined, HomeOutlined, LockOutlined, SearchOutlined, ToolOutlined, WarningOutlined, InfoCircleOutlined } from '@ant-design/icons-vue';

const props = defineProps({ status: Number, message: String, home: String });

const INFO = {
    403: { title: 'Acceso restringido', hint: 'Si crees que deberías poder entrar, pide a la administración de tu escuela que revise los permisos de tu cuenta.', icon: LockOutlined, c: '#f39200', c2: '#e3262b' },
    404: { title: 'Página no encontrada', hint: 'Revisa la dirección o regresa al inicio para seguir trabajando.', icon: SearchOutlined, c: '#1d7fc9', c2: '#14a39a' },
    500: { title: 'Algo salió mal', hint: 'Intenta de nuevo en unos minutos. Si el problema sigue, avisa a soporte.', icon: WarningOutlined, c: '#e3262b', c2: '#7c4ddb' },
    503: { title: 'En mantenimiento', hint: 'Estamos mejorando SIGUE. Vuelve en unos minutos.', icon: ToolOutlined, c: '#7c4ddb', c2: '#1d7fc9' },
};
const info = computed(() => INFO[props.status] ?? INFO[500]);
const back = () => (window.history.length > 1 ? window.history.back() : (window.location.href = props.home));
</script>

<template>
    <Head :title="info.title" />
    <div class="err" :style="{ '--c': info.c, '--c2': info.c2 }">
        <span class="err__blob err__blob--a" />
        <span class="err__blob err__blob--b" />

        <main class="err__card">
            <img src="/images/logo-sigue-sm.png" alt="SIGUE" class="err__logo" />

            <div class="err__badge">
                <span class="err__ring"><component :is="info.icon" /></span>
            </div>

            <div class="err__code">{{ status }}</div>
            <h1 class="err__title">{{ info.title }}</h1>
            <p class="err__msg">{{ message }}</p>

            <div class="err__acts">
                <a-button size="large" @click="back"><template #icon><ArrowLeftOutlined /></template>Regresar</a-button>
                <Link :href="home"><a-button type="primary" size="large" class="err__home"><template #icon><HomeOutlined /></template>Ir al inicio</a-button></Link>
            </div>

            <p class="err__hint"><InfoCircleOutlined /> {{ info.hint }}</p>
        </main>
    </div>
</template>

<style scoped>
.err { position: relative; min-height: 100vh; display: grid; place-items: center; padding: 24px; overflow: hidden;
    background: linear-gradient(160deg, #f3f7fa 0%, #eaf2fb 100%); font-family: 'Inter', system-ui, sans-serif; }
.err__blob { position: absolute; border-radius: 50%; filter: blur(60px); opacity: .35; pointer-events: none; }
.err__blob--a { width: 420px; height: 420px; background: var(--c); top: -140px; left: -120px; }
.err__blob--b { width: 360px; height: 360px; background: var(--c2); bottom: -140px; right: -100px; }

.err__card { position: relative; width: min(520px, 100%); text-align: center; background: rgba(255, 255, 255, .92); backdrop-filter: blur(8px);
    border: 1px solid #e1e8f0; border-radius: 24px; padding: 28px 32px 24px; box-shadow: 0 30px 60px -36px rgba(10, 31, 68, .5); animation: err-in .45s cubic-bezier(.16, 1, .3, 1); }
.err__card::before { content: ''; position: absolute; inset: 0 0 auto 0; height: 5px; border-radius: 24px 24px 0 0; background: linear-gradient(90deg, var(--c), var(--c2)); }
.err__logo { height: 40px; }

.err__badge { display: grid; place-items: center; margin: 18px 0 4px; }
.err__ring { width: 84px; height: 84px; border-radius: 26px; display: grid; place-items: center; font-size: 36px; color: #fff;
    background: linear-gradient(135deg, var(--c), var(--c2)); box-shadow: 0 18px 34px -16px var(--c), 0 0 0 10px color-mix(in srgb, var(--c) 10%, transparent);
    animation: err-float 3s ease-in-out infinite; }

.err__code { font-size: 64px; font-weight: 900; line-height: 1; letter-spacing: -.04em; margin-top: 14px;
    background: linear-gradient(135deg, var(--c), var(--c2)); -webkit-background-clip: text; background-clip: text; color: transparent; }
.err__title { margin: 6px 0 4px; font-size: 22px; font-weight: 800; color: #0a1f44; }
.err__msg { margin: 0 auto; max-width: 380px; color: #5d7085; font-size: 14.5px; line-height: 1.5; }

.err__acts { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin: 22px 0 18px; }
.err__acts :deep(.ant-btn) { border-radius: 12px; font-weight: 600; min-width: 150px; }
.err__home { background: linear-gradient(135deg, var(--c), var(--c2)) !important; border: 0 !important; }

.err__hint { margin: 0; padding: 10px 14px; border-radius: 12px; background: #f7fafc; border: 1px solid #e9eef5; color: #5d7085; font-size: 12.5px;
    display: flex; gap: 8px; align-items: flex-start; text-align: left; }
.err__hint .anticon { color: var(--c); margin-top: 3px; }

@keyframes err-in { from { opacity: 0; transform: translateY(14px) scale(.98); } to { opacity: 1; transform: none; } }
@keyframes err-float { 50% { transform: translateY(-6px); } }
@media (max-width: 480px) { .err__card { padding: 22px 18px 18px; } .err__code { font-size: 52px; } .err__acts :deep(.ant-btn) { min-width: 0; flex: 1; } }
</style>
