<script setup>
/** Pantallas de acceso con el estilo de SAINS ISSFAM (panel institucional + onda) y la marca SIGUE. */
import { onMounted, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    SafetyCertificateFilled, ReadFilled, CloudOutlined, MobileOutlined, LockOutlined, ThunderboltOutlined,
    CheckSquareOutlined, TeamOutlined, HeartOutlined, DollarOutlined,
} from '@ant-design/icons-vue';
import { authTheme, antdLocale } from '@/theme';
import { showFlash } from '@/lib/notify';

defineProps({ title: { type: String, default: 'Iniciar sesión' } });

const page = usePage();

const pilares = [
    { icon: CloudOutlined, label: 'En la nube' },
    { icon: MobileOutlined, label: 'Multiplataforma' },
    { icon: LockOutlined, label: 'Seguro' },
    { icon: ThunderboltOutlined, label: 'Tiempo real' },
];
const perfiles = [
    { icon: CheckSquareOutlined, label: 'Docentes' },
    { icon: TeamOutlined, label: 'Alumnos' },
    { icon: HeartOutlined, label: 'Padres y tutores' },
    { icon: DollarOutlined, label: 'Administración' },
];
</script>

<template>
    <a-config-provider :theme="authTheme" :locale="antdLocale">
        <Head :title="title" />

        <div class="au">
            <!-- Panel institucional -->
            <aside class="au-brand">
                <span class="au-brand__glow" aria-hidden="true"></span>

                <div class="au-brand__inner">
                    <div class="au-logo">
                        <Link :href="route('home')" class="au-logo__card"><img src="/images/logo-sigue.png" alt="SIGUE · Sistema Integral de Gestión Universal Educativa" /></Link>
                        <span class="au-logo__bar" aria-hidden="true"></span>
                    </div>

                    <section class="au-pitch">
                        <span class="au-pitch__eyebrow"><SafetyCertificateFilled /> Plataforma educativa integral</span>
                        <p class="au-pitch__lead">
                            <mark>Una institución conectada.</mark> Una educación inteligente. <b>Un futuro integrado.</b>
                        </p>
                        <div class="au-pitch__card">
                            <p>
                                SIGUE centraliza los procesos <b>académicos, administrativos, financieros y de comunicación</b>
                                de tu institución, conectando a directivos, docentes, alumnos y familias en tiempo real.
                            </p>
                            <ul class="au-pitch__chips">
                                <li v-for="p in pilares" :key="p.label"><component :is="p.icon" /> {{ p.label }}</li>
                            </ul>
                        </div>
                    </section>

                    <div class="au-rule" aria-hidden="true"><ReadFilled /></div>

                    <h2 class="au-careers__title">Un espacio para cada perfil:</h2>
                    <ul class="au-careers">
                        <li v-for="c in perfiles" :key="c.label" class="au-career">
                            <span class="au-career__icon"><component :is="c.icon" /></span>
                            <span class="au-career__label">{{ c.label }}</span>
                        </li>
                    </ul>
                </div>

                <svg class="au-wave" viewBox="0 0 160 1000" preserveAspectRatio="none" aria-hidden="true">
                    <path class="au-wave__back" d="M160 0H70C20 160 0 300 40 430c40 130 115 230 100 380-5 70-25 130-40 190h60z" />
                    <path class="au-wave__front" d="M160 0H95C55 170 40 300 75 430c35 130 95 240 80 390-6 60-18 120-30 180h35z" />
                </svg>
            </aside>

            <!-- Formulario -->
            <main class="au-side">
                <span class="au-side__dots" aria-hidden="true"></span>
                <span class="au-side__blob" aria-hidden="true"></span>

                <div class="au-card">
                    <div class="au-card__logos"><img src="/images/logo-sigue.png" alt="SIGUE" /></div>
                    <h1 class="au-card__title"><slot name="title">Bienvenido</slot></h1>
                    <p class="au-card__sub"><slot name="subtitle" /></p>
                    <slot />
                    <p v-if="$slots.footer" class="au-card__foot"><slot name="footer" /></p>
                </div>
                <p class="au-copy">© {{ new Date().getFullYear() }} SIGUE · Sistema Integral de Gestión Universal Educativa</p>
            </main>
        </div>
    </a-config-provider>
</template>

<style scoped>
.au {
    --au-navy: #0b2f55;
    --au-blue: #1d7fc9;
    --au-gold: #f6d32d;
    --au-bg: #f3f6fb;
    position: relative; display: grid; grid-template-columns: 1fr 1fr; min-height: 100vh; background: var(--au-bg);
    font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif; overflow: hidden;
}

/* ---------- Panel institucional ---------- */
.au-brand {
    position: relative; display: flex; align-items: center; padding: 44px 150px 44px 5%; color: #fff; z-index: 1;
    background:
        radial-gradient(ellipse at 20% 0%, #2490d6 0%, transparent 55%),
        radial-gradient(ellipse at 80% 100%, #14a39a 0%, transparent 55%),
        linear-gradient(165deg, #0f5a99 0%, #0b3f6e 55%, #082a4d 100%);
}
.au-brand__glow { position: absolute; top: -20%; left: -10%; width: 70%; height: 70%; background: radial-gradient(circle, rgba(255, 255, 255, .12), transparent 65%); pointer-events: none; }
.au-brand__inner { position: relative; z-index: 2; max-width: 600px; width: 100%; animation: au-slide-in .6s cubic-bezier(.16, 1, .3, 1); }
@keyframes au-slide-in { from { opacity: 0; transform: translateX(-22px); } to { opacity: 1; transform: none; } }

.au-logo { margin-bottom: 26px; }
.au-logo__card {
    display: flex; align-items: center; justify-content: center; padding: 22px 36px; border-radius: 28px;
    background: linear-gradient(180deg, #ffffff 0%, #f3f6fb 100%);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .9), 0 2px 6px rgba(4, 18, 52, .18), 0 30px 60px -22px rgba(4, 18, 52, .75);
}
.au-logo__card img { display: block; height: 130px; width: auto; max-width: 100%; object-fit: contain; }
.au-logo__bar { display: block; width: 84px; height: 4px; margin: 22px auto 0; border-radius: 4px; background: rgba(255, 255, 255, .7); }

.au-pitch__eyebrow { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 12px; font-size: 11.5px; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: rgba(255, 255, 255, .8); }
.au-pitch__eyebrow::after { content: ''; width: 36px; height: 1px; background: rgba(255, 255, 255, .45); }
.au-pitch__eyebrow .anticon { font-size: 14px; color: var(--au-gold); }
.au-pitch__lead { margin: 0 0 16px; font-size: clamp(1.1rem, 1.4vw, 1.36rem); line-height: 1.5; color: rgba(255, 255, 255, .92); }
.au-pitch__lead b, .au-pitch__lead mark { background: none; padding: 0; color: #fff; font-weight: 700; }
.au-pitch__lead mark { background-image: linear-gradient(var(--au-gold), var(--au-gold)); background-repeat: no-repeat; background-size: 100% 2px; background-position: 0 100%; }
.au-pitch__card {
    margin-right: -48px; padding: 16px 14px 16px 18px; border-radius: 12px; background: rgba(7, 24, 64, .4);
    border: 1px solid rgba(255, 255, 255, .12); border-left: 3px solid var(--au-gold); backdrop-filter: blur(6px);
}
.au-pitch__card p { margin: 0; font-size: .94rem; line-height: 1.6; color: rgba(255, 255, 255, .84); }
.au-pitch__card b { color: #fff; font-weight: 600; }
.au-pitch__chips { list-style: none; display: flex; flex-wrap: wrap; gap: 6px; margin: 12px 0 0; padding: 0; }
.au-pitch__chips li { display: inline-flex; align-items: center; gap: 5px; padding: 5px 9px; border-radius: 6px; font-size: 12px; font-weight: 500; color: rgba(255, 255, 255, .9); background: rgba(255, 255, 255, .07); border: 1px solid rgba(255, 255, 255, .16); }
.au-pitch__chips li .anticon { color: var(--au-gold); }

.au-rule { display: flex; align-items: center; justify-content: center; margin: 20px 0 14px; color: var(--au-gold); font-size: 20px; }
.au-rule::before, .au-rule::after { content: ''; flex: 1; height: 3px; border-radius: 3px; }
.au-rule::before { background: linear-gradient(90deg, var(--au-gold), rgba(246, 211, 45, .25)); margin-right: 12px; }
.au-rule::after { background: linear-gradient(90deg, rgba(20, 163, 154, .9), rgba(20, 163, 154, .1)); margin-left: 12px; }

.au-careers__title { display: inline-block; margin: 0 0 14px; padding-bottom: 6px; font-size: 1.02rem; font-weight: 800; letter-spacing: .02em; text-transform: uppercase; color: #fff; border-bottom: 3px solid var(--au-gold); }
.au-careers { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(4, 1fr); }
.au-career { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 0 10px; text-align: center; }
.au-career + .au-career { border-left: 1px solid rgba(255, 255, 255, .22); }
.au-career__icon { display: grid; place-items: center; width: 60px; height: 60px; border-radius: 50%; border: 2px solid rgba(255, 255, 255, .85); font-size: 25px; color: #fff; background: rgba(255, 255, 255, .06); transition: transform .2s, background .2s, border-color .2s; }
.au-career:hover .au-career__icon { transform: translateY(-3px); background: rgba(246, 211, 45, .18); border-color: var(--au-gold); }
.au-career__label { font-size: .86rem; font-weight: 600; line-height: 1.3; color: #fff; }

.au-wave { position: absolute; top: 0; right: -1px; width: 160px; height: 100%; z-index: 3; pointer-events: none; }
.au-wave__back { fill: rgba(255, 255, 255, .28); }
.au-wave__front { fill: var(--au-bg); }

/* ---------- Formulario ---------- */
.au-side { position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 28px; background: radial-gradient(circle at 70% 30%, #ffffff 0%, transparent 60%), var(--au-bg); }
.au-side__dots { position: absolute; top: 24px; right: 24px; width: 220px; height: 160px; background-image: radial-gradient(rgba(29, 127, 201, .18) 1.5px, transparent 1.6px); background-size: 16px 16px; mask-image: linear-gradient(225deg, #000 30%, transparent 85%); pointer-events: none; }
.au-side__blob {
    position: absolute; right: -120px; bottom: -140px; width: 360px; height: 300px; border-radius: 55% 45% 0 0 / 60% 50% 0 0;
    background: linear-gradient(135deg, #f6d32d, #f39200); transform: rotate(-12deg); box-shadow: -18px -10px 0 -2px rgba(243, 146, 0, .18);
    pointer-events: none; animation: au-float 10s ease-in-out infinite;
}
@keyframes au-float { 0%, 100% { transform: rotate(-12deg) translate(0, 0); } 50% { transform: rotate(-10deg) translate(-8px, -10px); } }
.au-card {
    position: relative; z-index: 2; width: 100%; max-width: 540px; background: #fff; border: 1px solid #e8edf5; border-radius: 26px;
    padding: 40px 52px 34px; box-shadow: 0 30px 70px -30px rgba(11, 63, 110, .38), 0 2px 6px rgba(15, 23, 42, .04); text-align: center;
    animation: au-card-in .5s cubic-bezier(.16, 1, .3, 1);
}
@keyframes au-card-in { from { opacity: 0; transform: translateY(16px) scale(.99); } to { opacity: 1; transform: none; } }
.au-card__logos { margin-bottom: 18px; }
.au-card__logos img { height: 92px; max-width: 80%; object-fit: contain; }
.au-card__title { font-size: 1.9rem; font-weight: 800; color: var(--au-navy); margin: 0 0 4px; letter-spacing: -.01em; }
.au-card__sub { font-size: 1rem; color: #64748b; margin: 0 0 24px; }
.au-card > :deep(form) { text-align: left; }
.au-card :deep(form) > * { opacity: 0; animation: au-field-in .45s cubic-bezier(.16, 1, .3, 1) forwards; }
.au-card :deep(form) > *:nth-child(1) { animation-delay: .12s; }
.au-card :deep(form) > *:nth-child(2) { animation-delay: .19s; }
.au-card :deep(form) > *:nth-child(3) { animation-delay: .26s; }
.au-card :deep(form) > *:nth-child(4) { animation-delay: .33s; }
.au-card :deep(form) > *:nth-child(5) { animation-delay: .40s; }
@keyframes au-field-in { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
.au-card__foot { margin: 18px 0 0; font-size: 14px; color: #64748b; }
.au-copy { position: relative; z-index: 2; margin-top: 18px; font-size: 12px; color: #94a3b8; text-align: center; }

@media (max-width: 1280px) { .au-brand { padding-right: 130px; } .au-card { padding: 36px 38px 30px; } }
@media (max-height: 860px) and (min-width: 1025px) {
    .au-brand { padding-top: 24px; padding-bottom: 24px; }
    .au-logo__card { padding: 16px 26px; } .au-logo__card img { height: 96px; }
    .au-logo { margin-bottom: 16px; } .au-logo__bar { margin-top: 14px; }
    .au-pitch__lead { font-size: 1.08rem; margin-bottom: 10px; }
    .au-rule { margin: 14px 0 10px; } .au-career__icon { width: 52px; height: 52px; font-size: 22px; }
    .au-card { padding: 28px 44px 24px; } .au-card__logos img { height: 74px; } .au-card__sub { margin-bottom: 16px; }
}
@media (max-width: 1024px) {
    .au { grid-template-columns: 1fr; }
    .au-brand { display: none; }
    .au-side { min-height: 100vh; padding: 36px 18px; }
}
@media (max-width: 520px) { .au-card { padding: 28px 20px 24px; } .au-side__dots { display: none; } }
</style>

<style>
.au .ant-input-affix-wrapper { border-radius: 14px; padding-top: 12px; padding-bottom: 12px; font-size: 15.5px; }
.au .ant-input-affix-wrapper .ant-input { font-size: 15.5px; }
.au .ant-form-item { margin-bottom: 20px; }
.au .ant-btn { border-radius: 14px; font-weight: 600; }
.au .ant-btn-lg { height: 54px; font-size: 16px; }
.au .ant-btn-primary { background: linear-gradient(135deg, #1d7fc9, #0f5a99); box-shadow: 0 10px 22px -12px rgba(15, 90, 153, .8); }
.au .ant-btn-primary:not(:disabled):hover { background: linear-gradient(135deg, #2490d6, #0b3f6e); }
.au .ant-input-affix-wrapper > .ant-input-prefix { color: #1d7fc9; margin-inline-end: 8px; }
</style>