<script setup>
/** App del docente · Yo: perfil, qué se hace en la app y qué en computadora, versión web y cerrar sesión. */
import { router, usePage } from '@inertiajs/vue3';
import { DesktopOutlined, LogoutOutlined, RightOutlined, CheckSquareOutlined, FormOutlined, ReadOutlined } from '@ant-design/icons-vue';
import MobileLayout from '@/Layouts/MobileLayout.vue';
import { ripple as vRipple, initials } from '@/lib/ripple';

defineProps({ profile: Object });
const page = usePage();
</script>

<template>
    <MobileLayout title="Yo" :back="route('app.docente.inicio')">
        <div class="me">
            <div class="me__card">
                <span class="me__av">{{ initials(profile.name) }}</span>
                <b>{{ profile.teacher || profile.name }}</b>
                <small>{{ profile.email }}</small>
                <small class="role">{{ page.props.auth.user.role_label }}</small>
            </div>
            <section class="box">
                <h3>En la app puedes</h3>
                <p><CheckSquareOutlined /> Pasar lista desde la hora de inicio de cada clase.</p>
                <p><FormOutlined /> Revisar entregas, calificar con o sin rúbrica y devolver con comentario.</p>
                <p><ReadOutlined /> Consultar tus clases, alumnos y avisos.</p>
            </section>
            <section class="box muted">
                <h3><DesktopOutlined /> Desde una computadora</h3>
                <p>Crear tareas, rúbricas y avisos, la evaluación del periodo, incidencias, tutorías, reportes y documentos.</p>
            </section>
            <section class="box flat">
                <a v-ripple :href="route('docente.inicio')" class="lt"><DesktopOutlined /><span>Versión de computadora</span><RightOutlined /></a>
                <button v-ripple type="button" class="lt danger" @click="router.post(route('logout'))"><LogoutOutlined /><span>Cerrar sesión</span></button>
            </section>
        </div>
    </MobileLayout>
</template>

<style scoped>
.me { padding: 8px 16px 28px; display: flex; flex-direction: column; gap: 14px; }
.me__card { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 22px 16px; background: var(--m-surface); border-radius: 24px; text-align: center; }
.me__av { width: 80px; height: 80px; border-radius: 50%; display: grid; place-items: center; background: linear-gradient(135deg, #0b57d0, #12a4a0); color: #fff; font-size: 28px; margin-bottom: 10px; }
.me__card b { font-size: 20px; font-weight: 400; }
.me__card small { font-size: 13px; color: var(--m-on-var); }
.role { margin-top: 6px; background: #d3e3fd; color: #041e49 !important; padding: 2px 10px; border-radius: 8px; }
.box { background: var(--m-surface); border-radius: 24px; padding: 16px 18px; }
.box h3 { margin: 0 0 8px; font-size: 15px; font-weight: 500; display: flex; gap: 8px; align-items: center; }
.box p { margin: 6px 0; font-size: 13.5px; color: var(--m-on-var); display: flex; gap: 10px; align-items: flex-start; line-height: 1.45; }
.box p .anticon { color: #0b57d0; margin-top: 3px; }
.box.muted { background: var(--m-surface-2); }
.box.flat { padding: 6px 0; overflow: hidden; }
.lt { width: 100%; display: flex; align-items: center; gap: 16px; padding: 14px 18px; border: 0; background: none; font-family: inherit; font-size: 15px; color: var(--m-on); cursor: pointer; text-align: left; }
.lt > span:not(.anticon) { flex: 1; }
.lt > .anticon:first-child { font-size: 18px; color: var(--m-on-var); }
.lt > .anticon:last-child { font-size: 11px; color: var(--m-on-var); }
.lt.danger, .lt.danger .anticon { color: #b3261e !important; }
</style>
