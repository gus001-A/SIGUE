<script setup>
/**
 * Layout de la app del alumno con aspecto de app nativa (Material 3, como Flutter / Google Classroom):
 *  - Barra superior (top app bar) que se tiñe al hacer scroll, con regresar o logo y avatar.
 *  - Contenido con transición de entrada.
 *  - Barra de navegación inferior (NavigationBar) con indicador en píldora y contador de avisos.
 *  - En pantallas grandes se muestra dentro de un marco tipo teléfono.
 * Slots: default, actions (iconos de la barra), bottom (reemplaza la navegación), fab, sheet.
 */
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeftOutlined, ReadOutlined, CheckSquareOutlined, CalendarOutlined, BellOutlined, DesktopOutlined, HomeOutlined, SwapOutlined, CheckOutlined, UserOutlined, RightOutlined } from '@ant-design/icons-vue';
import { ripple as vRipple, initials } from '@/lib/ripple';

const props = defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    back: { type: String, default: null },       // URL a la que regresa la flecha
    tint: { type: String, default: null },        // color de la barra (p. ej. el de la clase)
});
const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const unread = computed(() => page.props.counters?.notifications ?? 0);
/** App del docente (rutas app.docente.*) o del alumno. */
const teacher = computed(() => !!route().current('app.docente.*'));
const NAV = computed(() => (teacher.value
    ? [
        { route: 'app.docente.inicio', label: 'Hoy', icon: HomeOutlined },
        { route: 'app.docente.clases', label: 'Clases', icon: ReadOutlined, also: ['app.docente.clase', 'app.docente.lista', 'app.docente.actividad'] },
        { route: 'app.docente.avisos', label: 'Avisos', icon: BellOutlined, badge: true },
    ]
    : [
        { route: 'app.clases', label: 'Clases', icon: ReadOutlined, also: ['app.tarea'] },
        { route: 'app.pendientes', label: 'Pendientes', icon: CheckSquareOutlined },
        { route: 'app.horario', label: 'Horario', icon: CalendarOutlined },
        { route: 'app.avisos', label: 'Avisos', icon: BellOutlined, badge: true },
    ]));
const isOn = (n) => route().current(n.route) || (n.also ?? []).some((r) => route().current(r));

const scrolled = ref(false);
const onScroll = (e) => (scrolled.value = e.target.scrollTop > 4);
const goBack = () => router.visit(props.back);

// ---- Tutor: ve la app de su hijo/a y puede cambiar entre sus hijos ----
const guardian = computed(() => !teacher.value && !!page.props.isGuardian);
const kid = computed(() => page.props.student ?? null);
const kids = computed(() => page.props.children ?? []);
const kidSheet = ref(false);
function pickKid(id) {
    kidSheet.value = false;
    if (id === kid.value?.id) return;
    // En una clase o tarea concreta se regresa a "Clases" (la del otro hijo es distinta)
    const stay = route().current('app.pendientes') || route().current('app.horario') || route().current('app.avisos') || route().current('app.perfil');
    router.get(stay ? window.location.pathname : route('app.clases'), { alumno: id });
}
</script>

<template>
    <Head :title="title || 'SIGUE'" />
    <div class="mx">
        <div class="mx__side">
            <img src="/images/pwa/icon-192.png" alt="" />
            <b>SIGUE · App {{ teacher ? 'del docente' : guardian ? 'para tutores' : 'del alumno' }}</b>
            <span>Instálala en tu celular desde el menú del navegador: “Agregar a la pantalla de inicio”.</span>
            <Link :href="route(teacher ? 'docente.inicio' : 'portal.inicio')" class="mx__web"><DesktopOutlined /> Ir a la versión web</Link>
        </div>
        <div class="ma">
            <header class="mbar" :class="{ scrolled, tinted: !!tint }" :style="tint ? { '--tint': tint } : null">
                <button v-if="back" v-ripple type="button" class="mbar__ib" aria-label="Regresar" @click="goBack"><ArrowLeftOutlined /></button>
                <img v-else src="/images/pwa/icon-192.png" alt="" class="mbar__logo" />
                <div class="mbar__t">
                    <b>{{ title }}</b>
                    <small v-if="subtitle">{{ subtitle }}</small>
                </div>
                <slot name="actions" />
                <button v-if="!back && guardian" v-ripple type="button" class="mbar__av kid" :aria-label="`Viendo a ${kid?.first_name}`" @click="kidSheet = true">
                    <img v-if="kid?.photo_url" :src="kid.photo_url" alt="" /><template v-else>{{ initials(kid?.full_name) }}</template>
                </button>
                <Link v-else-if="!back" v-ripple :href="route(teacher ? 'app.docente.yo' : 'app.perfil')" class="mbar__av" aria-label="Mi perfil">
                    <img v-if="user.photo_url" :src="user.photo_url" alt="" /><template v-else>{{ initials(user.name) }}</template>
                </Link>
            </header>
            <!-- Tutor: a quién está viendo -->
            <button v-if="guardian && kid" v-ripple type="button" class="kidbar" @click="kidSheet = true">
                <UserOutlined /><span>Viendo a <b>{{ kid.first_name }}</b><template v-if="kid.group"> · {{ kid.group }}</template></span>
                <em v-if="kids.length > 1"><SwapOutlined /> Cambiar</em>
            </button>

            <main class="mbody" @scroll.passive="onScroll">
                <div class="mbody__in"><slot /></div>
            </main>

            <slot name="fab" />
            <slot name="sheet" />

            <!-- Hoja para elegir hijo/a (tutores) -->
            <template v-if="guardian">
                <div v-if="kidSheet" class="ks__scrim" @click="kidSheet = false" />
                <section v-if="kidSheet" class="ks">
                    <i class="ks__grab" />
                    <h3>¿A quién quieres ver?</h3>
                    <button v-for="c in kids" :key="c.id" v-ripple type="button" class="ks__i" :class="{ on: c.id === kid?.id }" @click="pickKid(c.id)">
                        <span class="ks__av"><img v-if="c.photo_url" :src="c.photo_url" alt="" /><template v-else>{{ initials(c.name) }}</template></span>
                        <span class="ks__t"><b>{{ c.name }}</b><small>{{ c.group || 'Sin grupo' }}</small></span>
                        <CheckOutlined v-if="c.id === kid?.id" class="ks__ck" />
                    </button>
                    <Link v-ripple :href="route('app.perfil')" class="ks__i link"><UserOutlined /><span class="ks__t"><b>Resumen, calificaciones y más</b></span><RightOutlined /></Link>
                    <p class="ks__note">Como tutor ves la información de tu hijo/a. Las tareas las entrega el alumno desde su propia cuenta.</p>
                </section>
            </template>

            <slot name="bottom">
                <nav class="mnav">
                    <Link v-for="n in NAV" :key="n.route" :href="route(n.route)" class="mnav__i" :class="{ on: isOn(n) }">
                        <span v-ripple class="mnav__pill"><component :is="n.icon" /><i v-if="n.badge && unread" class="mnav__badge">{{ unread > 99 ? '99+' : unread }}</i></span>
                        <span class="mnav__l">{{ n.label }}</span>
                    </Link>
                </nav>
            </slot>
        </div>
    </div>
</template>

<style>
/* Tokens Material 3 (claro) */
.mx { --m-primary: #0b57d0; --m-on-primary: #fff; --m-primary-c: #d3e3fd; --m-on-primary-c: #041e49; --m-sec-c: #c2e7ff; --m-surface: #fff;
    --m-surface-1: #f8fafd; --m-surface-2: #f0f4f9; --m-surface-3: #e9eef6; --m-on: #1f1f1f; --m-on-var: #444746; --m-outline: #747775; --m-outline-var: #c4c7c5;
    --m-error: #b3261e; --m-success: #146c2e; font-family: Roboto, 'Inter', system-ui, sans-serif; }
.mx a, .mx a:hover { text-decoration: none; }
.m-ripple { position: relative; overflow: hidden; -webkit-tap-highlight-color: transparent; }
.m-ripple__wave { position: absolute; border-radius: 50%; background: currentColor; opacity: .14; transform: scale(0); animation: m-ripple .55s cubic-bezier(.2, 0, 0, 1) forwards; pointer-events: none; }
@keyframes m-ripple { to { transform: scale(1); opacity: 0; } }
@keyframes m-in { from { opacity: 0; transform: translateX(14px); } to { opacity: 1; transform: none; } }
@keyframes m-up { from { transform: translateY(100%); } to { transform: none; } }
</style>

<style scoped>
.mx { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; gap: 48px; background: #dfe5ee; color: var(--m-on); }
.mx__side { display: none; }
.ma { position: relative; width: 100%; height: 100%; display: flex; flex-direction: column; background: var(--m-surface-1); overflow: hidden; }

.mbar { flex: none; display: flex; align-items: center; gap: 4px; height: 64px; padding: 0 8px 0 4px; padding-top: env(safe-area-inset-top); background: var(--m-surface-1);
    transition: background .2s, box-shadow .2s; z-index: 5; }
.mbar.scrolled { background: var(--m-surface-2); }
.mbar.tinted { background: var(--tint); color: #fff; }
.mbar.tinted.scrolled { box-shadow: 0 2px 6px rgba(0, 0, 0, .18); }
.mbar__ib { width: 48px; height: 48px; border-radius: 50%; border: 0; background: none; display: grid; place-items: center; font-size: 20px; color: inherit; cursor: pointer; flex: none; }
.mbar__logo { width: 34px; height: 34px; margin: 0 10px 0 12px; border-radius: 10px; flex: none; }
.mbar__t { flex: 1; min-width: 0; display: flex; flex-direction: column; line-height: 1.2; padding-left: 4px; }
.mbar__t b { font-size: 21px; font-weight: 400; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mbar__t small { font-size: 12.5px; opacity: .78; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mbar__av { width: 36px; height: 36px; border-radius: 50%; overflow: hidden; display: grid; place-items: center; background: #7c4ddb; color: #fff; font-size: 13px; font-weight: 500; margin: 0 6px; flex: none; }
.mbar__av img { width: 100%; height: 100%; object-fit: cover; }
.mbar__av.kid { border: 0; padding: 0; cursor: pointer; box-shadow: 0 0 0 2px var(--m-surface-1), 0 0 0 4px #e37400; background: #e37400; font-family: inherit; }
.kidbar { flex: none; display: flex; align-items: center; gap: 10px; margin: 0 12px 6px; padding: 8px 14px; border: 0; border-radius: 14px; background: #fef7e0; color: #5c3c00;
    font-family: inherit; font-size: 13px; cursor: pointer; text-align: left; z-index: 4; }
.kidbar > span:not(.anticon) { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.kidbar b { font-weight: 600; }
.kidbar em { font-style: normal; font-weight: 500; color: #8a4a00; display: inline-flex; gap: 5px; align-items: center; }
.ks__scrim { position: absolute; inset: 0; z-index: 20; background: rgba(0, 0, 0, .32); }
.ks { position: absolute; left: 0; right: 0; bottom: 0; z-index: 21; background: var(--m-surface-1); border-radius: 28px 28px 0 0; padding: 10px 0 calc(16px + env(safe-area-inset-bottom));
    box-shadow: 0 -2px 12px rgba(0, 0, 0, .15); max-height: 80%; overflow-y: auto; }
.ks__grab { display: block; width: 32px; height: 4px; border-radius: 2px; background: var(--m-outline); opacity: .45; margin: 4px auto 12px; }
.ks h3 { margin: 0 20px 8px; font-size: 18px; font-weight: 400; }
.ks__i { width: 100%; display: flex; align-items: center; gap: 14px; padding: 10px 20px; border: 0; background: none; font-family: inherit; text-align: left; cursor: pointer; color: var(--m-on); }
.ks__i.on { background: var(--m-sec-c); }
.ks__i.link { border-top: 1px solid #e1e3e1; margin-top: 6px; padding-top: 14px; }
.ks__i.link > .anticon { font-size: 18px; color: var(--m-on-var); }
.ks__av { width: 44px; height: 44px; border-radius: 50%; overflow: hidden; display: grid; place-items: center; background: #e37400; color: #fff; font-weight: 500; flex: none; }
.ks__av img { width: 100%; height: 100%; object-fit: cover; }
.ks__t { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.ks__t b { font-size: 15px; font-weight: 500; }
.ks__t small { font-size: 12.5px; color: var(--m-on-var); }
.ks__ck { color: #0b57d0; }
.ks__note { margin: 10px 20px 0; font-size: 12.5px; color: var(--m-on-var); line-height: 1.45; }

.mbody { flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior: contain; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
.mbody::-webkit-scrollbar { display: none; }
.mbody__in { animation: m-in .26s cubic-bezier(.2, 0, 0, 1); min-height: 100%; }

.mnav { flex: none; display: flex; height: 80px; padding-bottom: env(safe-area-inset-bottom); background: var(--m-surface-2); z-index: 5; }
.mnav__i { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; color: var(--m-on-var); -webkit-tap-highlight-color: transparent; user-select: none; }
.mnav__pill { position: relative; width: 64px; height: 32px; border-radius: 16px; display: grid; place-items: center; font-size: 20px; transition: background .2s; overflow: visible !important; }
.mnav__i.on .mnav__pill { background: var(--m-sec-c); color: #001d35; }
.mnav__l { font-size: 12px; font-weight: 500; letter-spacing: .3px; }
.mnav__i.on .mnav__l { color: var(--m-on); font-weight: 700; }
.mnav__badge { position: absolute; top: 2px; left: 36px; min-width: 16px; height: 16px; padding: 0 4px; border-radius: 8px; background: var(--m-error); color: #fff; font-size: 10.5px;
    font-style: normal; font-weight: 500; display: grid; place-items: center; line-height: 1; }

/* Escritorio: la app en un marco tipo teléfono */
@media (min-width: 720px) {
    .ma { width: 412px; height: min(880px, calc(100dvh - 40px)); border-radius: 32px; box-shadow: 0 30px 80px -30px rgba(11, 31, 68, .45), 0 0 0 10px #1f1f1f; }
    .mx__side { display: flex; flex-direction: column; gap: 10px; max-width: 260px; color: #3c4043; }
    .mx__side img { width: 56px; height: 56px; border-radius: 16px; }
    .mx__side b { font-size: 22px; font-weight: 500; color: #1f1f1f; }
    .mx__side span { font-size: 14px; line-height: 1.5; }
    .mx__web { align-self: flex-start; margin-top: 6px; display: inline-flex; gap: 8px; align-items: center; padding: 10px 18px; border-radius: 99px; background: #0b57d0; color: #fff; font-weight: 500; }
}
@media (min-width: 720px) and (max-width: 900px) { .mx__side { display: none; } }
</style>
