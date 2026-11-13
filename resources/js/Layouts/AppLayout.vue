<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    DownloadOutlined, UserOutlined, BellOutlined, LogoutOutlined, MenuOutlined, DownOutlined, LockOutlined,
    SafetyOutlined, BankOutlined, AppstoreOutlined, EnvironmentOutlined, SwapOutlined,
    TeamOutlined, ReadOutlined, BookOutlined, FolderOpenOutlined, DollarOutlined, CheckSquareOutlined, HeartOutlined, SettingOutlined,
} from '@ant-design/icons-vue';
import { antdTheme, antdLocale } from '@/theme';
import { showFlash, confirmAction } from '@/lib/notify';
import { buildNav, activeKey } from '@/lib/nav';
import NotifBell from '@/Components/NotifBell.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import FileViewer from '@/Components/FileViewer.vue';
import PerfilModal from '@/Components/PerfilModal.vue';
import InstalarApp from '@/Components/InstalarApp.vue';
import { pwa } from '@/lib/pwa';

defineProps({
    title: { type: String, default: null },
    scroll: { type: Boolean, default: false },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const perms = computed(() => page.props.auth?.permissions ?? []);
const institution = computed(() => page.props.institution);
const campus = computed(() => page.props.campus);
const campuses = computed(() => page.props.campuses ?? []);
const unreadMessages = computed(() => page.props.counters?.messages ?? 0);
const initials = computed(() => {
    const n = (user.value.name || 'U').split(' ').filter(Boolean);
    return ((n[0]?.[0] ?? 'U') + (n[1]?.[0] ?? '')).toUpperCase();
});

// Todos los módulos a los que el usuario tiene permiso (nada se oculta)
// Las secciones de la clase (Pase de lista, Classroom…) usan la clase de trabajo del docente.
const teachingClass = computed(() => page.props.teaching?.current ?? null);
const allSections = computed(() => buildNav(perms.value, !!institution.value || !user.value.is_superadmin)
    .map((s) => ({ ...s, items: s.items
        .filter((i) => !i.needsClass || teachingClass.value)
        .map((i) => (i.needsClass ? { ...i, params: { class: teachingClass.value.id } } : i)) }))
    // El personal (admin, director, superadmin) no ve las secciones del docente ni del portal.
    .filter((s) => !(perms.value.includes('dashboard.ver') && ['Docencia', 'Mi escuela'].includes(s.label)))
    .filter((s) => s.items.length));

/*
 * Menú superior por grupos:
 *  - "General" (Inicio, Reportes, Mensajes) va como enlaces directos.
 *  - Cada grupo (Alumnos y familias, Académico, Finanzas, Administración…) tiene su dropdown.
 *  - Si el usuario solo tiene un grupo (docente, alumno, tutor) sus opciones van directas
 *    y el excedente en "Más".
 */
const SECTION_ICONS = {
    'Alumnos y familias': TeamOutlined, 'Académico': ReadOutlined, 'Finanzas': DollarOutlined,
    'Docencia': CheckSquareOutlined, 'Mi escuela': HeartOutlined, 'Administración': SettingOutlined, 'SIGUE': BankOutlined,
};
const GROUP_ICONS = { 'Mi clase': BookOutlined, 'Escolar': ReadOutlined, 'Trámites': FolderOpenOutlined };
/** Mensajes siempre va al final del menú. */
const withMessagesLast = (entries) => {
    const i = entries.findIndex((e) => e.type === 'link' && e.item.key === 'mensajes');
    return i >= 0 ? [...entries.slice(0, i), ...entries.slice(i + 1), entries[i]] : entries;
};
const navEntries = computed(() => withMessagesLast(buildEntries()));
function buildEntries() {
    const sections = allSections.value;
    const general = sections.find((s) => s.label === 'General');
    const others = sections.filter((s) => s.label !== 'General');
    const entries = (general?.items ?? []).map((i) => ({ type: 'link', item: i }));

    if (others.length === 1) {
        // Docente, alumno o tutor: sus opciones van directas y las que comparten "group" en un dropdown
        // (igual que el menú del administrador).
        const groups = {};
        for (const i of others[0].items) {
            if (!i.group) { entries.push({ type: 'link', item: i }); continue; }
            if (!groups[i.group]) {
                groups[i.group] = { type: 'menu', key: i.group, label: i.group, icon: GROUP_ICONS[i.group] ?? AppstoreOutlined, items: [] };
                entries.push(groups[i.group]);
            }
            groups[i.group].items.push(i);
        }
        return entries;
    }
    for (const s of others) {
        if (s.items.length === 1) entries.push({ type: 'link', item: s.items[0] });
        else entries.push({ type: 'menu', key: s.label, label: s.label, icon: SECTION_ICONS[s.label] ?? AppstoreOutlined, items: s.items });
    }
    return entries;
}

const current = computed(() => activeKey(allSections.value));
const menuActive = (e) => e.items.some((i) => i.key === current.value);

// Lógica del scroll
const scrolled = ref(false);
function onScroll() {
    scrolled.value = window.scrollY > 10;
}

const drawer = ref(false);
const userMenu = ref(false);
const perfil = ref(false);

function go(item) {
    drawer.value = false;
    router.visit(route(item.route, item.params ?? {}));
}
function logout() {
    confirmAction({ title: '¿Cerrar sesión?', content: 'Tendrás que volver a ingresar tu correo y contraseña.', okText: 'Sí, salir', tone: 'logout', onOk: () => router.post(route('logout')) });
}
function changeCampus(id) {
    userMenu.value = false; // Cerramos el menú
    router.post(route('plantel.cambiar'), { campus_id: id });
}
const installer = ref(null);
function handleMenuClick({ key }) {
    userMenu.value = false;
    if (key === 'instalar') { installer.value?.open(); return; }
    if (key === 'perfil') {
        perfil.value = true;
    } else if (key === 'logout') {
        logout();
    } else if (key === 'instituciones') {
        router.visit(route('superadmin.instituciones.index'));
    } else if (key === 'leave-inst') {
        router.post(route('superadmin.instituciones.leave'));
    } else {
        router.visit(route(key));
    }
}

// Los avisos (flash) se muestran una sola vez por visita desde app.js (initFlash).
onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
});
onUnmounted(() => window.removeEventListener('scroll', onScroll));
</script>

<template>
    <a-config-provider :theme="antdTheme" :locale="antdLocale">
        <Head :title="title" />

        <div class="sg-app" :class="{ 'is-scroll': scroll }">
            
            <!-- Línea superior multicolor -->
            <div class="sg-navbar__topbar"></div>

            <!-- ============ NAVBAR SUPERIOR ============ -->
            <header class="sg-navbar" :class="{ 'is-scrolled': scrolled }">
                <div class="sg-navbar__inner">
                    <!-- Logo -->
                    <Link :href="route('home')" class="sg-navbar__brand">
                        <img src="/images/logo-sigue-sm.png" alt="SIGUE" class="sg-navbar__logo" />
                    </Link>

                    <!-- Menú horizontal: enlaces directos + un dropdown por grupo -->
                    <nav class="sg-navbar__links" :class="{ 'is-compact': navEntries.length > 7 }">
                        <template v-for="e in navEntries" :key="e.type === 'link' ? e.item.key : e.key">
                            <a v-if="e.type === 'link'"
                               :href="route(e.item.route, e.item.params ?? {})"
                               class="sg-nav-link"
                               :class="{ 'is-active': current === e.item.key }"
                               @click.prevent="go(e.item)">
                                <component :is="e.item.icon" class="sg-nav-ic" :style="{ color: e.item.color }" />
                                <span>{{ e.item.label }}</span>
                                <span v-if="e.item.badge === 'messages' && unreadMessages" class="sg-badge">{{ unreadMessages }}</span>
                            </a>

                            <a-dropdown v-else placement="bottomLeft" :trigger="['hover', 'click']" overlay-class-name="sg-navbar-dd">
                                <a class="sg-nav-link" :class="{ 'is-active': menuActive(e) }" @click.prevent>
                                    <component :is="e.icon" class="sg-nav-ic sg-nav-ic--grp" />
                                    <span>{{ e.label }}</span>
                                    <DownOutlined class="sg-nav-caret" />
                                </a>
                                <template #overlay>
                                    <a-menu :selected-keys="current ? [current] : []">
                                        <a-menu-item v-for="i in e.items" :key="i.key" @click="go(i)">
                                            <span class="sg-dd-ic" :style="{ background: i.color }"><component :is="i.icon" /></span>
                                            <span>{{ i.label }}</span>
                                            <span v-if="i.badge === 'messages' && unreadMessages" class="sg-dd-badge">{{ unreadMessages }}</span>
                                        </a-menu-item>
                                    </a-menu>
                                </template>
                            </a-dropdown>
                        </template>
                    </nav>

                    <!-- Acciones derecha (Notificaciones y Usuario) -->
                    <div class="sg-navbar__actions">
                        <NotifBell />

                        <a-dropdown v-model:open="userMenu" placement="bottomRight" :trigger="['click']" overlay-class-name="usr-drop">
                            <button class="sg-user">
                                <span class="sg-user__av"><img v-if="page.props.auth.user?.photo_url" :src="page.props.auth.user?.photo_url" alt="" /><template v-else>{{ initials }}</template></span>
                                <span class="sg-user__txt"><b>{{ user.name }}</b></span>
                                <DownOutlined class="sg-user__caret" />
                            </button>
                            
                            <template #overlay>
                                <div class="usr-menu-container">
                                    <!-- Cabecera de usuario -->
                                    <div class="usr-menu__head">
                                        <span class="usr-menu__avatar"><img v-if="page.props.auth.user?.photo_url" :src="page.props.auth.user?.photo_url" alt="" /><template v-else>{{ initials }}</template></span>
                                        <div>
                                            <div class="usr-menu__name">{{ user.name }}</div>
                                            <div class="usr-menu__mail">{{ user.email }}</div>
                                        </div>
                                    </div>
                                    
                                    <div class="usr-menu__roles">
                                        <a-tag v-for="r in user.roles" :key="r.slug" color="default"><SafetyOutlined /> {{ r.name }}</a-tag>
                                    </div>

                                    <!-- SELECTOR DE PLANTEL (Movido aquí) -->
                                    <div v-if="campus" class="usr-menu__campus">
                                        <div class="usr-menu__campus-lbl"><EnvironmentOutlined /> Plantel actual</div>
                                        <a-select v-if="!page.props.campusLocked && campuses.length > 1" :value="campus.id" :options="campuses"
                                            class="usr-menu__campus-sel" @change="changeCampus" />
                                        <div v-else class="usr-menu__campus-one">{{ campus.name }}</div>
                                    </div>

                                    <!-- OPCIONES DEL MENÚ -->
                                    <a-menu mode="vertical" class="usr-menu-list" @click="handleMenuClick">
                                        <a-menu-item key="perfil"><UserOutlined /> Mi perfil</a-menu-item>
                                        <a-menu-item v-if="!pwa.installed" key="instalar"><DownloadOutlined /> Instalar app</a-menu-item>
                                        <a-menu-item v-if="user.is_superadmin" key="instituciones"><BankOutlined /> Instituciones</a-menu-item>
                                        
                                        <template v-if="user.is_superadmin && institution">
                                            <a-menu-divider />
                                            <a-menu-item key="leave-inst" danger><SwapOutlined /> Salir de la institución</a-menu-item>
                                        </template>
                                        
                                        <a-menu-divider />
                                        <a-menu-item key="logout" danger><LogoutOutlined /> Cerrar sesión</a-menu-item>
                                    </a-menu>
                                </div>
                            </template>
                        </a-dropdown>
                        
                        <!-- Burger para móvil -->
                        <button class="sg-navbar__burger" aria-label="Menú" @click="drawer = true">
                            <MenuOutlined />
                        </button>
                    </div>
                </div>
            </header>

            <!-- Drawer para móvil -->
            <a-drawer v-model:open="drawer" placement="left" :width="280" :body-style="{ padding: 0 }" :closable="false">
                <div class="sg-mobile-drawer">
                    <div class="sg-mobile-drawer__brand">
                        <img src="/images/logo-sigue-sm.png" alt="SIGUE" class="sg-mobile-drawer__logo" />
                    </div>
                    <nav class="sg-mobile-nav">
                        <div v-for="s in allSections" :key="s.label" class="sg-mobile-sec">
                            <div class="sg-mobile-label">{{ s.label }}</div>
                            <a v-for="i in s.items" :key="i.key" href="#" class="sg-mobile-link" :class="{ 'is-active': current === i.key }" @click.prevent="go(i)">
                                <component :is="i.icon" class="sg-mobile-ic" :style="{ color: i.color }" />
                                <span>{{ i.label }}</span>
                            </a>
                        </div>
                    </nav>
                </div>
            </a-drawer>

            <!-- ============ CONTENIDO ============ -->
            <main class="sg-content">
                <a-alert v-if="user.must_change_password" type="warning" show-icon class="sg-pwd" message="Estás usando una contraseña temporal">
                    <template #description>Por seguridad, cámbiala ahora. <a @click="perfil = true"><LockOutlined /> Cambiar contraseña</a></template>
                </a-alert>
                <div class="sg-page">
                    <slot />
                </div>
            </main>

            <ConfirmDialog />
            <FileViewer />
            <PerfilModal v-model:open="perfil" />
            <InstalarApp ref="installer" />
        </div>
    </a-config-provider>
</template>

<style scoped>
/* ============ BASE ============ */
.sg-app {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    background: #f8fafc;
}

/* ============ TOPBAR (Línea de colores) ============ */
.sg-navbar__topbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    z-index: 60;
    background: linear-gradient(90deg, #ea4335, #fbbc04, #34a853, #1a73e8);
    background-size: 200% 100%;
    animation: bar-anim 8s linear infinite;
}
@keyframes bar-anim {
    0% { background-position: 0% 0; }
    100% { background-position: 200% 0; }
}

/* ============ NAVBAR PREMIUM ============ */
.sg-navbar {
    position: sticky;
    top: 3px;
    z-index: 50;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: saturate(180%) blur(16px);
    -webkit-backdrop-filter: saturate(180%) blur(16px);
    border-bottom: 1px solid transparent;
    transition: background 0.3s, border-color 0.3s, box-shadow 0.3s;
}

.sg-navbar.is-scrolled {
    background: rgba(255, 255, 255, 0.98);
    border-bottom-color: #e2e8f0;
    box-shadow: 0 4px 20px -10px rgba(0, 0, 0, 0.05);
}

.sg-navbar__inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    height: 70px;
    max-width: 1600px;
    margin: 0 auto;
}

/* Logo */
.sg-navbar__brand {
    display: flex;
    align-items: center;
    text-decoration: none;
    transition: transform 0.2s;
    margin-right: 24px;
    flex: none;
}
.sg-navbar__brand:hover {
    transform: scale(1.02);
}
.sg-navbar__logo {
    max-height: 52px;
    width: auto;
    object-fit: contain;
}

/* Links del Navbar (Con scroll horizontal si no caben) */
.sg-navbar__links {
    display: flex;
    align-items: center;
    gap: 4px;
    flex: 1;
    margin: 0 16px;
    overflow-x: auto;
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE */
}
.sg-navbar__links::-webkit-scrollbar {
    display: none; /* Chrome/Safari */
}

.sg-nav-link {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 16px;
    border-radius: 10px;
    color: #475569;
    font-weight: 500;
    font-size: 14.5px;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.2, 0.8, 0.2, 1);
    cursor: pointer;
    position: relative;
    white-space: nowrap;
    flex: none;
}

.sg-nav-link:hover {
    background: #f1f5f9;
    color: #0f172a;
}

.sg-nav-link.is-active {
    background: #eff6ff;
    color: #1d4ed8;
    font-weight: 600;
}
.sg-nav-link.is-active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 20%;
    width: 60%;
    height: 2px;
    background: #1d4ed8;
    border-radius: 2px 2px 0 0;
}

.sg-nav-ic { font-size: 15px; }
.sg-nav-ic--grp { color: #64748b; }
.sg-nav-link.is-active .sg-nav-ic { color: #1d4ed8 !important; }
.sg-nav-caret { font-size: 9px; opacity: .6; transition: transform .2s; }
.sg-nav-link:hover .sg-nav-caret { transform: rotate(180deg); }

/* Badge de mensajes */
.sg-badge {
    background: #ef4444;
    color: #fff;
    font-size: 10px;
    font-weight: bold;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: 2px;
    line-height: 1;
}

/* Acciones derecha */
.sg-navbar__actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: none;
}
/* Opciones centradas: logo y acciones ocupan lo mismo a cada lado */
@media (min-width: 1101px) {
    .sg-navbar__brand { flex: 1 1 0; min-width: max-content; }
    .sg-navbar__actions { flex: 1 1 0; min-width: max-content; justify-content: flex-end; }
    .sg-navbar__links { flex: 0 1 auto; justify-content: safe center; }
}

.sg-navbar__burger {
    display: none;
    background: none;
    border: none;
    font-size: 22px;
    color: #475569;
    cursor: pointer;
    padding: 8px;
    border-radius: 10px;
    transition: background 0.2s;
}
.sg-navbar__burger:hover { background: #f1f5f9; }

/* Botón de usuario */
.sg-user {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 4px 14px 4px 4px;
    border-radius: 30px;
    cursor: pointer;
    transition: all 0.2s;
}
.sg-user:hover { 
    border-color: #cbd5e1; 
    background: #f1f5f9;
}
.sg-user__av {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1d7fc9, #0b3f6e);
    color: #fff;
    display: grid;
    place-items: center;
    font-weight: 700;
    font-size: 13px;
}
.sg-user__txt { 
    font-size: 14px; color: #0f172a; font-weight: 600; 
    max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.sg-user__caret { font-size: 10px; color: #94a3b8; }

/* ============ MENÚ DE USUARIO ============ */
.usr-menu-container {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 12px 32px -8px rgba(0,0,0,0.15), 0 4px 12px -4px rgba(0,0,0,0.05);
    min-width: 280px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    border: 1px solid #f1f5f9;
}
.usr-menu__head {
    display: flex; align-items: center; gap: 12px; padding: 18px 16px; border-bottom: 1px solid #f1f5f9;
}
.usr-menu__avatar {
    width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(135deg, #1d7fc9, #0b3f6e); color: #fff;
    display: grid; place-items: center; font-weight: 700; font-size: 16px; flex: none;
}
.usr-menu__name { font-weight: 700; color: #0f172a; font-size: 15px; }
.usr-menu__mail { color: #64748b; font-size: 12.5px; }
.usr-menu__roles {
    padding: 12px 16px; display: flex; flex-wrap: wrap; gap: 6px; border-bottom: 1px solid #f1f5f9;
}

/* Selector de plantel dentro del menú */
.usr-menu__campus {
    padding: 12px 16px;
    background: #f8fafc;
    border-bottom: 1px solid #f1f5f9;
}
.usr-menu__campus-lbl {
    font-size: 11px; text-transform: uppercase; font-weight: 700; color: #94a3b8; 
    margin-bottom: 8px; display: flex; align-items: center; gap: 6px;
}
.usr-menu__campus-sel {
    width: 100%;
}
.usr-menu__campus-sel :deep(.ant-select-selector) {
    background: #fff !important;
    border-color: #e2e8f0 !important;
    border-radius: 8px !important;
}
.usr-menu__campus-one {
    font-size: 14px; font-weight: 600; color: #0f172a;
    padding: 4px 0;
}

.usr-menu-list { border: none !important; box-shadow: none !important; padding: 6px !important; }
.usr-menu-list :deep(.ant-menu-item) { border-radius: 8px; margin: 2px 0; font-weight: 500; }

/* ============ DRAWER MÓVIL ============ */
.sg-mobile-drawer { display: flex; flex-direction: column; height: 100%; background: #fff; }
.sg-mobile-drawer__brand { padding: 24px 16px; display: flex; justify-content: center; border-bottom: 1px solid #f1f5f9; }
.sg-mobile-drawer__logo { max-height: 50px; }
.sg-mobile-nav { padding: 16px; overflow-y: auto; }
.sg-mobile-sec { margin-bottom: 24px; }
.sg-mobile-label { font-size: 11px; text-transform: uppercase; font-weight: 700; color: #94a3b8; margin-bottom: 8px; letter-spacing: 0.5px; }
.sg-mobile-link {
    display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 8px;
    color: #475569; text-decoration: none; font-weight: 500; transition: background 0.2s;
}
.sg-mobile-link:hover, .sg-mobile-link.is-active { background: #f1f5f9; color: #0f172a; }
.sg-mobile-ic { font-size: 18px; color: #64748b; }

/* ============ CONTENIDO ============ */
.sg-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.sg-page {
    flex: 1;
    padding: 24px;
    overflow-y: auto;
}

/* ============ RESPONSIVE ============ */
/* Muchas opciones (p. ej. docente con clase seleccionada): sin íconos y más juntas */
.sg-navbar__links.is-compact { gap: 0; }
.sg-navbar__links.is-compact .sg-nav-link { padding: 7px 8px; font-size: 13px; gap: 5px; }
.sg-navbar__links.is-compact .sg-nav-ic { font-size: 14px; }
@media (max-width: 1500px) {
    .sg-user__txt { display: none; }
    .sg-user { padding: 4px 10px 4px 4px; }
    .sg-navbar__brand { margin-right: 8px; }
}
@media (max-width: 1340px) {
    .sg-nav-link { padding: 8px 10px; font-size: 13.5px; gap: 6px; }
    .sg-navbar__links { margin: 0 8px; gap: 2px; }
}
@media (max-width: 1160px) {
    .sg-nav-link { padding: 7px 7px !important; font-size: 12.5px !important; }
}

@media (max-width: 1024px) {
    .sg-navbar__links { display: none; }
    .sg-navbar__burger { display: block; }
}
.sg-user__av, .usr-menu__avatar { overflow: hidden; }
.sg-user__av img, .usr-menu__avatar img { width: 100%; height: 100%; object-fit: cover; }
</style>

<style>
/* Dropdown de cada grupo del navbar (se monta fuera del componente) */
.sg-navbar-dd .ant-dropdown-menu {
    min-width: 240px; border-radius: 14px !important; padding: 6px !important;
    box-shadow: 0 16px 36px -12px rgba(15, 23, 42, .25) !important; border: 1px solid #eef1f6;
}
.sg-navbar-dd .ant-dropdown-menu-item { border-radius: 10px !important; padding: 8px 10px !important; font-weight: 500; color: #334155 !important; }
.sg-navbar-dd .ant-dropdown-menu-item:hover { background: #f1f5f9 !important; }
.sg-navbar-dd .ant-dropdown-menu-item-selected { background: #eff6ff !important; color: #1d4ed8 !important; font-weight: 650; }
.sg-navbar-dd .ant-dropdown-menu-title-content { display: flex; align-items: center; gap: 10px; }
.sg-navbar-dd .sg-dd-ic { width: 28px; height: 28px; border-radius: 8px; display: grid; place-items: center; color: #fff; font-size: 13px; flex: none; }
.sg-navbar-dd .sg-dd-badge { margin-left: auto; background: #ef4444; color: #fff; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 10px; }
</style>