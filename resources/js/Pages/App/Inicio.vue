<script setup>
/** App del alumno · Yo: perfil, resumen (promedio, asistencia, pendientes, saldo), calificaciones y accesos. */
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    TrophyOutlined, CalendarOutlined, BookOutlined, WalletOutlined, RightOutlined, DesktopOutlined, LogoutOutlined,
    FileTextOutlined, BarChartOutlined,
} from '@ant-design/icons-vue';
import MobileLayout from '@/Layouts/MobileLayout.vue';
import MDialog from '@/Components/App/MDialog.vue';
import { ripple as vRipple, initials } from '@/lib/ripple';
import { money, num } from '@/lib/format';

const props = defineProps({
    student: Object, children: Array, isGuardian: Boolean, summary: Object, pending: Array, today: Array,
    classes: Array, grades: { type: Array, default: () => [] }, attendanceDays: Object, incidents: Array, missingDocs: Number,
    passing: { type: Number, default: 6 },
});
const gradeColor = (v) => (v == null ? '#c4c7c5' : v < props.passing ? '#b3261e' : v < 8 ? '#e37400' : '#146c2e');
/** En la app solo lo indispensable; lo demás se hace desde una computadora. */
const DESKTOP = [
    { l: 'Documentos del expediente', icon: FileTextOutlined, why: 'Para subir o revisar tus documentos (acta, CURP, certificado, etc.) entra a SIGUE desde una computadora.' },
    { l: 'Pagos y recibos', icon: WalletOutlined, why: 'Para consultar tu estado de cuenta y descargar recibos entra a SIGUE desde una computadora.' },
    { l: 'Asistencias e incidencias', icon: CalendarOutlined, why: 'El detalle de asistencias e incidencias se consulta desde una computadora.' },
];
const pc = ref({ open: false, t: '', x: '' });
const logout = () => router.post(route('logout'));
</script>

<template>
    <MobileLayout title="Yo" :back="route('app.clases')">
        <div class="me">
            <div class="me__card">
                <small v-if="isGuardian" class="me__tag">Estás viendo a tu hijo/a</small>
                <span class="me__av"><img v-if="student.photo_url" :src="student.photo_url" alt="" /><template v-else>{{ initials(student.full_name) }}</template></span>
                <b>{{ student.full_name }}</b>
                <small>{{ student.group }}<template v-if="student.level"> · {{ student.level }}</template></small>
                <small v-if="student.enrollment_number">Matrícula {{ student.enrollment_number }}</small>
            </div>

            <div class="me__grid">
                <div class="tile" style="--c: #146c2e"><TrophyOutlined /><b>{{ summary.average !== null ? num(summary.average) : '—' }}</b><small>Promedio</small></div>
                <div class="tile" style="--c: #0b57d0"><CalendarOutlined /><b>{{ summary.attendance !== null ? summary.attendance + '%' : '—' }}</b><small>Asistencia</small></div>
                <div class="tile" style="--c: #e37400"><BookOutlined /><b>{{ summary.pending }}</b><small>Pendientes</small></div>
                <div class="tile" :style="{ '--c': summary.overdue ? '#b3261e' : '#6750a4' }"><WalletOutlined /><b>{{ money(summary.debt) }}</b><small>Saldo</small></div>
            </div>

            <section v-if="grades.length" class="me__sec">
                <h3>Calificaciones<small v-if="grades[0]?.period"> · {{ grades[0].period }}</small></h3>
                <div v-for="g in grades" :key="g.subject" class="gb">
                    <span>{{ g.subject }}</span>
                    <i class="gb__bar"><i :style="{ width: (g.score ?? 0) * 10 + '%', background: gradeColor(g.score) }" /></i>
                    <b :style="{ color: gradeColor(g.score) }">{{ g.score == null ? '—' : Number(g.score).toFixed(1) }}</b>
                </div>
            </section>

            <section class="me__sec flat">
                <button v-for="l in DESKTOP" :key="l.l" v-ripple type="button" class="lt" @click="pc = { open: true, t: l.l, x: l.why }">
                    <component :is="l.icon" /><span>{{ l.l }}</span><small class="lt__pc"><DesktopOutlined /> En computadora</small>
                </button>
                <a v-ripple :href="route('portal.inicio')" class="lt"><DesktopOutlined /><span>Versión web completa</span><RightOutlined /></a>
                <button v-ripple type="button" class="lt danger" @click="logout"><LogoutOutlined /><span>Cerrar sesión</span></button>
            </section>
        </div>
        <MDialog v-model:open="pc.open" :title="pc.t" :text="`${pc.x} En la app tienes lo indispensable: tus clases, tareas, horario y avisos.`" ok-text="Entendido" cancel-text="Cerrar" @ok="pc.open = false" />
    </MobileLayout>
</template>

<style scoped>
.me { padding: 8px 16px 28px; display: flex; flex-direction: column; gap: 16px; }
.me__card { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 20px 16px; background: var(--m-surface); border-radius: 24px; text-align: center; }
.me__av { width: 84px; height: 84px; border-radius: 50%; overflow: hidden; display: grid; place-items: center; background: #7c4ddb; color: #fff; font-size: 28px; margin-bottom: 10px; }
.me__av img { width: 100%; height: 100%; object-fit: cover; }
.me__tag { margin-bottom: 8px; background: #fef7e0; color: #5c3c00 !important; padding: 2px 10px; border-radius: 8px; }
.me__card b { font-size: 20px; font-weight: 400; }
.me__card small { font-size: 13px; color: var(--m-on-var); }
.me__grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.tile { border-radius: 20px; padding: 14px; background: color-mix(in srgb, var(--c) 9%, #fff); display: flex; flex-direction: column; gap: 2px; color: var(--c); }
.tile .anticon { font-size: 20px; margin-bottom: 6px; }
.tile b { font-size: 22px; font-weight: 500; color: var(--m-on); }
.tile small { font-size: 12.5px; color: var(--m-on-var); }
.me__sec { background: var(--m-surface); border-radius: 24px; padding: 16px; }
.me__sec.flat { padding: 6px 0; overflow: hidden; }
.me__sec h3 { margin: 0 0 8px; font-size: 16px; font-weight: 500; }
.me__sec h3 small { font-weight: 400; color: var(--m-on-var); }
.gb { display: grid; grid-template-columns: 110px 1fr 34px; gap: 10px; align-items: center; padding: 6px 0; font-size: 13px; }
.gb > span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.gb__bar { display: block; height: 8px; border-radius: 4px; background: var(--m-surface-2); overflow: hidden; }
.gb__bar i { display: block; height: 100%; border-radius: 4px; }
.gb > b { text-align: right; font-weight: 500; }
.lt { width: 100%; display: flex; align-items: center; gap: 16px; padding: 14px 18px; border: 0; background: none; font-family: inherit; font-size: 15px; color: var(--m-on); cursor: pointer; text-align: left; }
.lt > .anticon:first-child { font-size: 18px; color: var(--m-on-var); }
.lt > span:not(.anticon) { flex: 1; }
.lt > .anticon:last-child { font-size: 11px; color: var(--m-on-var); }
.lt__pc { font-size: 11.5px; color: var(--m-on-var); background: var(--m-surface-2); padding: 2px 8px; border-radius: 8px; display: inline-flex; gap: 4px; align-items: center; }
.lt.danger, .lt.danger .anticon { color: var(--m-error) !important; }
</style>
