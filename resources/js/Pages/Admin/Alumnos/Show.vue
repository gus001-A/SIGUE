<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    UserOutlined, EditOutlined, FilePdfOutlined, KeyOutlined, PlusOutlined, DeleteOutlined, MedicineBoxOutlined,
    PhoneOutlined, MailOutlined, CalendarOutlined, IdcardOutlined, ReadOutlined, FolderOpenOutlined, WalletOutlined,
    WarningOutlined, HeartOutlined, TrophyOutlined, CheckCircleOutlined, CloseCircleOutlined, ClockCircleOutlined,
    SafetyCertificateOutlined, HomeOutlined, AlertOutlined, PrinterOutlined, GiftOutlined, TeamOutlined,
    SearchOutlined, DashboardOutlined, ArrowRightOutlined, StarOutlined, CarOutlined, GlobalOutlined, DollarOutlined,
    ExclamationCircleOutlined, AppstoreOutlined, LikeOutlined, SmileOutlined, BarChartOutlined, EyeOutlined, FileTextOutlined
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHead from '@/Components/PageHead.vue';
import StatusTag from '@/Components/StatusTag.vue';
import EvidenceButton from '@/Components/EvidenceButton.vue';
import DocumentsPanel from '@/Components/DocumentsPanel.vue';
import SgModal from '@/Components/SgModal.vue';
import SgTable from '@/Components/SgTable.vue';
import AlumnoModal from '@/Components/AlumnoModal.vue';
import { money, num, gradeColor } from '@/lib/format';
import { confirmDelete } from '@/lib/notify';
import { openFile } from '@/lib/viewer';

const props = defineProps({
    student: Object, enrollments: Array, guardians: Array, health: [Object, null], grades: Array, attendance: Object,
    documents: Array, missingDocuments: Array, finance: [Object, null], incidents: Array, options: Object, can: Object,
    form: Object, formOptions: Object, openEdit: Boolean,
    current: { type: [Object, null], default: null },
    gradeMatrix: { type: Object, default: () => ({ periods: [], rows: [], average: null }) },
});
const tab = ref('resumen');
const editOpen = ref(props.openEdit && props.can.edit);

// ---------- Cuenta de acceso ----------
const accOpen = ref(false);
const acc = useForm({ email: props.student.email ?? '' });
const createAccount = () => acc.post(route('alumnos.account', props.student.id), { preserveScroll: true, onSuccess: () => (accOpen.value = false) });

// ---------- Salud ----------
const hOpen = ref(false);
const h = useForm({ ...(props.health ?? {}), consent_by: props.health?.consent_by ?? '' });
const saveHealth = () => h.put(route('alumnos.health', props.student.id), { preserveScroll: true, onSuccess: () => (hOpen.value = false) });

// ---------- Tutores ----------
const newGuardianId = ref(null);
const g = useForm({ guardian_id: null, relationship: 'tutor', is_primary: false, can_pickup: true });
const attachDirect = () => {
    if (!newGuardianId.value) return;
    g.guardian_id = newGuardianId.value;
    g.post(route('alumnos.guardians.attach', props.student.id), {
        preserveScroll: true,
        onSuccess: () => { newGuardianId.value = null; g.reset(); },
    });
};
const detach = (gu) => confirmDelete({ title: '¿Desvincular tutor?', content: gu.full_name, okText: 'Sí, desvincular', onOk: () => router.delete(route('alumnos.guardians.detach', [props.student.id, gu.id]), { preserveScroll: true }) });
const filterOption = (input, opt) => opt.label.toLowerCase().includes(input.toLowerCase());

// ---------- Desglose de calificaciones de una materia ----------
const breakdownOpen = ref(false);
const bdRow = ref(null);      // fila de gradeMatrix (promedio y finales por periodo)
const bdDetail = ref(null);   // detalle por criterios (props.grades)
const openBreakdown = (row) => {
    bdRow.value = row;
    bdDetail.value = props.grades.find((g) => g.subject === row.subject) ?? { periods: [] };
    breakdownOpen.value = true;
};
/** Periodos del desglose con la aportación ponderada de cada criterio. */
const bdPeriods = computed(() => (bdDetail.value?.periods ?? []).map((p) => {
    const criteria = (p.criteria ?? []).map((c) => ({
        ...c,
        points: c.score != null && c.weight != null ? (Number(c.score) * Number(c.weight)) / 100 : null,
    }));
    const weightSum = criteria.reduce((t, c) => t + Number(c.weight || 0), 0);
    const computedScore = criteria.length && criteria.every((c) => c.points != null) ? criteria.reduce((t, c) => t + c.points, 0) : null;
    return { ...p, criteria, weightSum, computedScore, shown: p.final ?? computedScore };
}));
const bdBest = computed(() => {
    const valid = bdPeriods.value.filter((p) => p.shown != null);
    return valid.length ? valid.reduce((a, b) => (b.shown > a.shown ? b : a)) : null;
});
const bdWidth = computed(() => ['min(720px, 96vw)', 'min(720px, 96vw)', 'min(900px, 96vw)'][bdPeriods.value.length] ?? 'min(1080px, 96vw)');
const PALETTE = ['#1d7fc9', '#14a39a', '#7c4ddb', '#f39200', '#e0457b', '#2e9b43', '#0f5a99'];

// ---------- Visor de archivos ----------
const openReceipt = (p) => { window.location.href = route('finanzas.recibo', p.id); }; // el recibo se descarga

// Subtítulo del encabezado
const headSub = computed(() => [
    `Matrícula ${props.student.enrollment_number ?? '—'}`,
    props.current ? `${props.current.grade ?? ''} · Grupo ${props.current.group}`.trim() : 'Sin inscripción vigente',
    props.student.campus,
].filter(Boolean).join(' · '));

// ---------- Datos derivados ----------
const GENDER = { F: 'Mujer', M: 'Hombre', X: 'No binario' };
const initials = computed(() => [props.student.name, props.student.paternal_surname].filter(Boolean).map((s) => s[0]).join('').toUpperCase() || '?');
const attRate = computed(() => (props.attendance.total ? Math.round(((props.attendance.total - props.attendance.falta) / props.attendance.total) * 100) : null));
const attColor = computed(() => (attRate.value === null ? '#94a3b8' : attRate.value >= 90 ? '#2e9b43' : attRate.value >= 80 ? '#f39200' : '#e3262b'));
const avg = computed(() => props.gradeMatrix?.average ?? null);
const negIncidents = computed(() => props.incidents.filter((i) => !i.positive));
const posIncidents = computed(() => props.incidents.filter((i) => i.positive));
const ATT = {
    presente: { label: 'Asistencia', c: '#2e9b43' }, falta: { label: 'Falta', c: '#e3262b' },
    retardo: { label: 'Retardo', c: '#f39200' }, justificada: { label: 'Justificada', c: '#1d7fc9' },
};

const info = computed(() => [
    { icon: IdcardOutlined, c: '#1d7fc9', label: 'CURP', value: props.student.curp },
    { icon: CalendarOutlined, c: '#7c4ddb', label: 'Nacimiento', value: [props.student.birth_date, props.student.age != null && `${props.student.age} años`].filter(Boolean).join(' · ') },
    { icon: UserOutlined, c: '#e0457b', label: 'Sexo', value: GENDER[props.student.gender] },
    { icon: HomeOutlined, c: '#14a39a', label: 'Plantel', value: props.student.campus },
    { icon: MailOutlined, c: '#e0457b', label: 'Correo', value: props.student.email },
    { icon: PhoneOutlined, c: '#2e9b43', label: 'Teléfono', value: props.student.phone },
    { icon: HomeOutlined, c: '#f39200', label: 'Domicilio', value: [props.student.address, props.student.postal_code && 'CP ' + props.student.postal_code].filter(Boolean).join(' · ') },
    { 
        icon: AlertOutlined, c: '#e3262b', label: 'Contacto de emergencia', 
        value: props.student.emergency_contact_name ? `${props.student.emergency_contact_name}${props.student.emergency_contact_phone ? ' · ' + props.student.emergency_contact_phone : ''}` : '—',
        fullWidth: true
    },
    { icon: GlobalOutlined, c: '#0f5a99', label: 'Publicación de fotos', value: props.student.photo_publication_consent ? 'Autorizada' : 'No autorizada' },
]);

// ---------- Finanzas ----------
const overdue = computed(() => (props.finance?.charges ?? []).filter((c) => c.overdue && c.balance > 0));
const finKpis = computed(() => {
    if (!props.finance) return [];
    const charged = props.finance.charges.reduce((t, c) => t + Number(c.total || 0), 0);
    const paid = props.finance.payments.filter((p) => p.status !== 'cancelado').reduce((t, p) => t + Number(p.amount || 0), 0);
    return [
        { label: 'Total cargado', value: money(charged), icon: WalletOutlined, c: '#1d7fc9' },
        { label: 'Total pagado', value: money(paid), icon: DollarOutlined, c: '#2e9b43' },
        { label: 'Saldo pendiente', value: money(props.finance.balance), icon: ExclamationCircleOutlined, c: props.finance.balance > 0 ? '#f39200' : '#2e9b43' },
        { label: 'Vencido', value: money(overdue.value.reduce((t, c) => t + Number(c.balance || 0), 0)), icon: WarningOutlined, c: overdue.value.length ? '#e3262b' : '#94a3b8' },
    ];
});
/** Cargos y pagos en una sola lista, del más reciente al más antiguo. */
const unifiedFinance = computed(() => {
    if (!props.finance) return [];
    const toDate = (d) => { if (!d) return 0; const [dd, mm, rest] = d.split('/'); return new Date(`${(rest || '').slice(0, 4)}-${mm}-${dd}`).getTime() || 0; };
    const charges = props.finance.charges.map((c) => ({
        ...c, _k: 'c' + c.id, kind: 'cargo', kindLabel: 'Cargo', date: c.due_on, dateLabel: 'Vence',
        charge: Number(c.total || 0), paid: Number(c.total || 0) - Number(c.balance || 0), pending: Number(c.balance || 0),
        statusKey: c.overdue && c.balance > 0 ? 'vencido' : c.status,
    }));
    const payments = props.finance.payments.map((p) => ({
        ...p, _k: 'p' + p.id, kind: 'pago', kindLabel: 'Pago', date: (p.paid_at || '').slice(0, 10), dateLabel: 'Pagado',
        concept: p.concept || 'Pago a cuenta', charge: null, paid: Number(p.amount || 0), pending: null, statusKey: p.status,
    }));
    return [...charges, ...payments].sort((a, b) => toDate(b.date) - toDate(a.date));
});
const FIN_STATUS = {
    pendiente: ['Pendiente', '#f39200'], parcial: ['Parcial', '#1d7fc9'], pagado: ['Pagado', '#2e9b43'], vencido: ['Vencido', '#e3262b'],
    cancelado: ['Cancelado', '#94a3b8'], aplicado: ['Aplicado', '#2e9b43'],
};
const finStatus = (k) => FIN_STATUS[k] ?? [k, '#64748b'];
const recentPayments = computed(() => (props.finance?.payments ?? []).slice(0, 3));

// ---------- Alertas del resumen ----------
const alerts = computed(() => {
    const list = [];
    if (props.missingDocuments.length) list.push({ c: '#f39200', icon: FolderOpenOutlined, title: `${props.missingDocuments.length} documento${props.missingDocuments.length === 1 ? '' : 's'} pendiente${props.missingDocuments.length === 1 ? '' : 's'}`, text: props.missingDocuments.join(', '), go: 'documentos' });
    if (overdue.value.length) list.push({ c: '#e3262b', icon: WalletOutlined, title: `${overdue.value.length} adeudo${overdue.value.length === 1 ? '' : 's'} vencido${overdue.value.length === 1 ? '' : 's'}`, text: overdue.value.map((c) => c.concept).join(', '), go: 'finanzas' });
    if (props.health?.allergies) list.push({ c: '#e0457b', icon: MedicineBoxOutlined, title: 'Alergias registradas', text: props.health.allergies, go: 'expediente' });
    if (!props.guardians.length) list.push({ c: '#7c4ddb', icon: HeartOutlined, title: 'Sin padre o tutor vinculado', text: 'Vincula al menos un tutor para avisos y recogida.', go: 'expediente' });
    if (!props.student.user_id) list.push({ c: '#0f5a99', icon: KeyOutlined, title: 'Sin cuenta de acceso', text: 'El alumno aún no puede entrar al portal.' });
    if (!props.current) list.push({ c: '#64748b', icon: TeamOutlined, title: 'Sin inscripción vigente', text: 'No tiene grupo autorizado en el ciclo actual.' });
    return list;
});

// ---------- Columnas ----------
const financeCols = [
    { title: 'Tipo', key: 'kind', dataIndex: 'kindLabel', icon: SafetyCertificateOutlined, width: 96, filter: 'select' },
    { title: 'Fecha', key: 'date', dataIndex: 'date', icon: CalendarOutlined, width: 104 },
    { title: 'Concepto', key: 'concept', dataIndex: 'concept', icon: ReadOutlined },
    { title: 'Cargo', key: 'charge', align: 'right', filter: false, width: 98 },
    { title: 'Pagado', key: 'paid', align: 'right', filter: false, width: 98 },
    { title: 'Saldo', key: 'pending', align: 'right', filter: false, width: 98 },
    { title: 'Estado', key: 'status', dataIndex: 'statusKey', icon: CheckCircleOutlined, width: 106, filter: 'select', filterValue: (r) => finStatus(r.statusKey)[0] },
    { title: 'Acciones', key: 'actions', width: 76, align: 'center' },
];
</script>

<template>
    <AppLayout :title="student.full_name">
        <PageHead :title="student.full_name" :subtitle="headSub" :icon="UserOutlined" back @back="router.visit(route('alumnos.index'))">
            <template #actions>
                <a v-if="current" :href="route('alumnos.constancia', student.id)" download><a-button size="large"><template #icon><FilePdfOutlined style="color: #e3262b" /></template>Constancia</a-button></a>
                <a-button v-if="can.edit" size="large" @click="accOpen = true"><template #icon><KeyOutlined style="color: #f39200" /></template>{{ student.user_id ? 'Restablecer acceso' : 'Crear cuenta' }}</a-button>
                <a-button v-if="can.edit" type="primary" size="large" @click="editOpen = true"><template #icon><EditOutlined /></template>Editar</a-button>
            </template>
        </PageHead>

        <!-- ================= FICHA (franja superior) ================= -->
        <section class="hero">
            <button type="button" class="hero__photo" :class="{ 'has-img': student.photo_url }" :title="student.photo_url ? 'Ver fotografía' : ''"
                @click="student.photo_url && openFile({ url: student.photo_url, name: 'foto.jpg', title: student.full_name, subtitle: 'Fotografía' })">
                <img v-if="student.photo_url" :src="student.photo_url" alt="" />
                <span v-else>{{ initials }}</span>
            </button>
            <div class="hero__main">
                <div class="hero__chips">
                    <StatusTag :status="student.status" />
                    <span class="chip" style="--c: #7c4ddb"><IdcardOutlined /> {{ student.enrollment_number ?? 'Sin matrícula' }}</span>
                    <template v-if="current">
                        <a class="chip chip--link" style="--c: #1d7fc9" @click="router.visit(route('grupos.show', current.group_id))"><TeamOutlined /> Grupo {{ current.group }}</a>
                        <span v-if="current.level" class="chip" style="--c: #14a39a"><ReadOutlined /> {{ current.level }}</span>
                        <span v-if="current.shift" class="chip" style="--c: #f39200"><ClockCircleOutlined /> {{ current.shift }}</span>
                        <span v-if="current.school_year" class="chip" style="--c: #0f5a99"><CalendarOutlined /> {{ current.school_year }}</span>
                    </template>
                    <span v-else class="chip" style="--c: #94a3b8"><TeamOutlined /> Sin grupo vigente</span>
                </div>
                <div class="hero__contact">
                    <span><PhoneOutlined style="color: #2e9b43" /> <a v-if="student.phone" :href="`tel:${student.phone}`">{{ student.phone }}</a><em v-else>Sin teléfono</em></span>
                    <span><MailOutlined style="color: #e0457b" /> <a v-if="student.email" :href="`mailto:${student.email}`">{{ student.email }}</a><em v-else>Sin correo</em></span>
                    <span><IdcardOutlined style="color: #1d7fc9" /> <b v-if="student.curp">{{ student.curp }}</b><em v-else>Sin CURP</em></span>
                    <span v-if="student.age != null"><CalendarOutlined style="color: #7c4ddb" /> {{ student.age }} años</span>
                </div>
            </div>
            <div class="hero__kpis">
                <div class="hk" :style="{ '--c': avg !== null ? gradeColor(avg) : '#94a3b8' }">
                    <span class="hk__ic"><TrophyOutlined /></span><span><b>{{ avg !== null ? num(avg, 2) : '—' }}</b><small>Promedio</small></span>
                </div>
                <div class="hk" :style="{ '--c': attColor }">
                    <span class="hk__ic"><CheckCircleOutlined /></span><span><b>{{ attRate !== null ? attRate + '%' : '—' }}</b><small>Asistencia</small></span>
                </div>
                <div v-if="finance" class="hk" :style="{ '--c': finance.balance > 0 ? '#e3262b' : '#2e9b43' }">
                    <span class="hk__ic"><WalletOutlined /></span><span><b>{{ money(finance.balance) }}</b><small>Saldo</small></span>
                </div>
                <div class="hk" :style="{ '--c': negIncidents.length ? '#e3262b' : '#2e9b43' }">
                    <span class="hk__ic"><WarningOutlined /></span><span><b>{{ incidents.length }}</b><small>Incidencias</small></span>
                </div>
            </div>
        </section>

        <div class="sg-row-fill">

            <!-- ================= CONTENIDO ================= -->
            <a-card :bordered="false" class="sg-fill main">
                <a-tabs v-model:active-key="tab" class="sg-tabs-fill st-tabs">
                    <!-- RESUMEN -->
                    <a-tab-pane key="resumen">
                        <template #tab><span class="tab-pill"><DashboardOutlined /> Resumen</span></template>

                        <div v-if="alerts.length" class="alerts">
                            <component :is="a.go ? 'button' : 'div'" v-for="a in alerts" :key="a.title" type="button" class="alert" :class="{ 'is-link': a.go }"
                                :style="{ '--c': a.c }" @click="a.go && (tab = a.go)">
                                <span class="alert__ic"><component :is="a.icon" /></span>
                                <span class="alert__txt"><b>{{ a.title }}</b><small :title="a.text">{{ a.text }}</small></span>
                                <ArrowRightOutlined v-if="a.go" class="alert__go" />
                            </component>
                        </div>
                        <div v-else class="all-good"><SmileOutlined /> Expediente al corriente: sin documentos, adeudos ni avisos pendientes.</div>

                        <div class="sum-grid">
                            <!-- Nueva Card de Asistencia Horizontal -->
                            <section class="box box--wide" style="--c: #2e9b43">
                                <header class="box__head"><span class="box__ic"><CheckCircleOutlined /></span><b>Resumen de asistencia</b>
                                    <span class="box__meta">{{ attendance.total }} registros totales</span>
                                </header>
                                <div class="att-horizontal">
                                    <div class="att-left">
                                        <div class="att-big-number" :style="{ color: attColor }">{{ attRate !== null ? attRate + '%' : '—' }}</div>
                                        <div class="att-label">Índice de asistencia</div>
                                        <div class="att-bar-bg">
                                            <div class="att-bar-fill" :style="{ width: (attRate ?? 0) + '%', background: attColor }"></div>
                                        </div>
                                    </div>
                                    <div class="att-right">
                                        <div v-for="(v, k) in ATT" :key="k" class="att-stat" :style="{ '--c': v.c }">
                                            <span class="att-stat-icon"><component :is="k === 'presente' ? CheckCircleOutlined : k === 'falta' ? CloseCircleOutlined : k === 'retardo' ? ClockCircleOutlined : SafetyCertificateOutlined" /></span>
                                            <div class="att-stat-info">
                                                <b>{{ attendance[k] }}</b>
                                                <span>{{ v.label }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <!-- Rendimiento por asignatura (Mini) -->
                            <section class="box box--wide" style="--c: #7c4ddb">
                                <header class="box__head"><span class="box__ic"><BarChartOutlined /></span><b>Rendimiento por asignatura</b>
                                    <span v-if="avg !== null" class="box__meta">Promedio general <b :style="{ color: gradeColor(avg) }">{{ num(avg, 2) }}</b></span>
                                </header>
                                <div v-if="!gradeMatrix.rows.length" class="empty-mini"><TrophyOutlined /> Sin calificaciones publicadas</div>
                                <div v-else class="bars">
                                    <div v-for="r in gradeMatrix.rows" :key="r.subject" class="bar">
                                        <span class="bar__name" :title="r.subject">{{ r.subject }}</span>
                                        <span class="bar__track"><i :style="{ width: ((r.average ?? 0) * 10) + '%', background: gradeColor(r.average) }" /></span>
                                        <b class="bar__val" :style="{ color: gradeColor(r.average) }">{{ r.average !== null ? num(r.average, 1) : '—' }}</b>
                                    </div>
                                </div>
                            </section>

                            <!-- Incidencias recientes -->
                            <section class="box" style="--c: #e3262b">
                                <header class="box__head"><span class="box__ic"><WarningOutlined /></span><b>Últimas incidencias</b>
                                    <a v-if="incidents.length" class="box__link" @click="tab = 'incidencias'">Ver todas <ArrowRightOutlined /></a>
                                </header>
                                <div v-if="!incidents.length" class="empty-mini"><LikeOutlined /> Sin incidencias registradas</div>
                                <div v-for="i in incidents.slice(0, 3)" :key="i.id" class="li-row">
                                    <span class="dot" :style="{ background: i.positive ? '#2e9b43' : (i.priority === 'alta' ? '#e3262b' : '#f39200') }" />
                                    <span class="li-row__txt"><b>{{ i.category }}</b><small>{{ i.date }} · {{ i.description }}</small></span>
                                    <StatusTag :status="i.status" />
                                </div>
                            </section>

                            <!-- Pagos recientes -->
                            <section v-if="finance" class="box" style="--c: #14a39a">
                                <header class="box__head"><span class="box__ic"><DollarOutlined /></span><b>Últimos pagos</b>
                                    <a class="box__link" @click="tab = 'finanzas'">Ver finanzas <ArrowRightOutlined /></a>
                                </header>
                                <div v-if="!recentPayments.length" class="empty-mini"><DollarOutlined /> Sin pagos registrados</div>
                                <div v-for="p in recentPayments" :key="p.id" class="li-row">
                                    <span class="dot" style="background: #2e9b43" />
                                    <span class="li-row__txt"><b>{{ p.concept || 'Pago a cuenta' }}</b><small>{{ p.paid_at }} · {{ p.method }}</small></span>
                                    <b class="money">{{ money(p.amount) }}</b>
                                </div>
                            </section>
                        </div>
                    </a-tab-pane>

                    <!-- EXPEDIENTE -->
                    <a-tab-pane key="expediente">
                        <template #tab><span class="tab-pill"><IdcardOutlined /> Expediente</span></template>

                        <div class="sec-title"><IdcardOutlined /> Datos personales</div>
                        <div class="info-grid">
                            <div v-for="i in info" :key="i.label" class="info" :style="{ '--c': i.c }" :class="{ 'info--span2': i.fullWidth }">
                                <span class="info__ic"><component :is="i.icon" /></span>
                                <span class="info__text"><small>{{ i.label }}</small><b>{{ i.value || '—' }}</b></span>
                            </div>
                        </div>

                        <!-- Padres o tutores -->
                        <div class="sec-title sec-gap" style="--c: #e0457b"><HeartOutlined /> Padres o tutores <span class="count">{{ guardians.length }}</span>
                            <div v-if="can.guardians" class="add-tutor">
                                <a-select v-model:value="newGuardianId" show-search :filter-option="filterOption" :options="options.guardians"
                                    placeholder="Buscar tutor para vincular…" class="add-tutor__sel">
                                    <template #suffixIcon><SearchOutlined /></template>
                                </a-select>
                                <a-button type="primary" class="add-tutor__btn" :disabled="!newGuardianId" :loading="g.processing" @click="attachDirect"><PlusOutlined /> Vincular</a-button>
                            </div>
                        </div>
                        <div v-if="!guardians.length" class="empty-mini"><HeartOutlined /> Sin padres o tutores vinculados</div>
                        <div v-else class="tutor-grid">
                            <div v-for="gu in guardians" :key="gu.id" class="tutor" :class="{ 'is-primary': gu.is_primary }">
                                <span class="sg-av tutor__av">{{ gu.full_name?.[0] }}</span>
                                <div class="tutor__txt">
                                    <b>{{ gu.full_name }}</b>
                                    <span class="tutor__tags">
                                        <span class="mini" style="--c: #e0457b">{{ gu.relationship }}</span>
                                        <span v-if="gu.is_primary" class="mini" style="--c: #f39200"><StarOutlined /> Principal</span>
                                        <span v-if="gu.can_pickup" class="mini" style="--c: #14a39a"><CarOutlined /> Recoge</span>
                                        <span class="mini" :style="{ '--c': gu.has_account ? '#1d7fc9' : '#94a3b8' }"><KeyOutlined /> {{ gu.has_account ? 'Con portal' : 'Sin cuenta' }}</span>
                                    </span>
                                    <small v-if="gu.phone"><PhoneOutlined /> <a :href="`tel:${gu.phone}`">{{ gu.phone }}</a></small>
                                    <small v-if="gu.email"><MailOutlined /> <a :href="`mailto:${gu.email}`">{{ gu.email }}</a></small>
                                </div>
                                <a-tooltip v-if="can.guardians" title="Desvincular"><a-button class="sg-ab red" size="small" @click="detach(gu)"><DeleteOutlined /></a-button></a-tooltip>
                            </div>
                        </div>

                        <template v-if="can.health">
                            <div class="sec-title sec-gap" style="--c: #e3262b"><MedicineBoxOutlined /> Expediente de salud
                                <a-button type="primary" size="small" class="btn-update" @click="hOpen = true"><EditOutlined /> Actualizar</a-button>
                            </div>
                            <div class="sensitive"><SafetyCertificateOutlined /> Información sensible: acceso restringido por permisos y con consentimiento registrado.</div>
                            <div class="info-grid">
                                <div class="info" style="--c: #e3262b"><span class="info__ic"><HeartOutlined /></span><span class="info__text"><small>Grupo sanguíneo</small><b>{{ health?.blood_type || '—' }} {{ health?.rh_factor || '' }}</b></span></div>
                                <div class="info" style="--c: #1d7fc9"><span class="info__ic"><UserOutlined /></span><span class="info__text"><small>Peso / talla</small><b>{{ health?.weight_kg ?? '—' }} kg · {{ health?.height_cm ?? '—' }} cm</b></span></div>
                                <div class="info" :class="{ 'is-warn': health?.allergies }" style="--c: #f39200"><span class="info__ic"><AlertOutlined /></span><span class="info__text"><small>Alergias</small><b>{{ health?.allergies || 'Ninguna registrada' }}</b></span></div>
                                <div class="info" style="--c: #7c4ddb"><span class="info__ic"><MedicineBoxOutlined /></span><span class="info__text"><small>Información médica</small><b>{{ health?.medical_notes || '—' }}</b></span></div>
                                <div class="info" style="--c: #2e9b43"><span class="info__ic"><SafetyCertificateOutlined /></span><span class="info__text"><small>Consentimiento</small><b>{{ health?.consent_by ? `${health.consent_by} · ${health.consent_at}` : 'Sin registrar' }}</b></span></div>
                            </div>
                        </template>

                        <div class="sec-title sec-gap" style="--c: #14a39a"><ReadOutlined /> Historial de inscripciones</div>
                        <div v-if="!enrollments.length" class="empty-mini"><ReadOutlined /> Sin inscripciones registradas</div>
                        <div class="timeline">
                            <div v-for="e in enrollments" :key="e.id" class="tl-item">
                                <span class="tl-marker" :style="{ background: e.status === 'autorizada' ? '#2e9b43' : e.status === 'baja' ? '#e3262b' : '#94a3b8' }" />
                                <div class="tl-content">
                                    <div class="tl-header"><b>{{ e.school_year }}</b><StatusTag :status="e.status" /></div>
                                    <div class="tl-body">
                                        <span><TeamOutlined /> Grupo <b>{{ e.group }}</b></span>
                                        <span><ReadOutlined /> {{ e.type === 'inscripcion' ? 'Inscripción' : 'Reinscripción' }}</span>
                                        <span><CalendarOutlined /> {{ e.enrolled_on }}</span>
                                    </div>
                                    <div v-if="e.drop_reason" class="tl-reason">{{ e.drop_reason }}</div>
                                </div>
                            </div>
                        </div>
                    </a-tab-pane>

                    <!-- ACADÉMICO -->
                    <a-tab-pane key="academico">
                        <template #tab><span class="tab-pill"><TrophyOutlined /> Académico</span></template>

                        <!-- Tabla de calificaciones rediseñada como lista -->
                        <div class="sec-title" style="--c: #2e9b43"><AppstoreOutlined /> Calificaciones por materia</div>
                        <div v-if="!gradeMatrix.rows.length" class="empty-mini"><TrophyOutlined /> Sin calificaciones capturadas</div>
                        <div v-else class="subject-list">
                            <button v-for="(r, ri) in gradeMatrix.rows" :key="r.subject" type="button" class="subject-item"
                                :style="{ '--c': PALETTE[ri % PALETTE.length], '--g': gradeColor(r.average) }" @click="openBreakdown(r)">
                                <div class="subject-info">
                                    <span class="subject-ic"><ReadOutlined /></span>
                                    <span class="subject-name" :title="r.subject">{{ r.subject }}</span>
                                    <span class="subject-score">{{ r.average !== null ? num(r.average, 1) : '—' }}</span>
                                </div>
                                <div class="subject-bar-bg"><div class="subject-bar-fill" :style="{ width: (r.average ?? 0) * 10 + '%' }" /></div>
                                <div class="subject-periods">
                                    <span v-for="(s, i) in r.scores" :key="i" class="sp" :style="{ '--p': s !== null ? gradeColor(s) : '#cbd5e1' }">
                                        <small>{{ gradeMatrix.periods[i] }}</small><b>{{ s !== null ? num(s, 1) : '—' }}</b>
                                    </span>
                                </div>
                                <span class="subject-btn">Ver desglose <ArrowRightOutlined /></span>
                            </button>
                        </div>
                    </a-tab-pane>

                    <!-- DOCUMENTOS -->
                    <a-tab-pane key="documentos">
                        <template #tab><span class="tab-pill"><FolderOpenOutlined /> Documentos <a-badge v-if="missingDocuments.length" :count="missingDocuments.length" :number-style="{ background: '#f39200' }" /></span></template>
                        <DocumentsPanel :documents="documents" :document-types="options.documentTypes" :missing="missingDocuments"
                            :upload-url="route('alumnos.documentos.store', student.id)" :can-review="can.documents" :can-upload="can.edit || can.documents" />
                    </a-tab-pane>

                    <!-- FINANZAS -->
                    <a-tab-pane v-if="finance" key="finanzas">
                        <template #tab><span class="tab-pill"><WalletOutlined /> Finanzas <a-badge v-if="overdue.length" :count="overdue.length" :number-style="{ background: '#e3262b' }" /></span></template>

                        <div class="fin-kpis">
                            <div v-for="k in finKpis" :key="k.label" class="fin-kpi" :style="{ '--c': k.c }">
                                <span class="fin-kpi__ic"><component :is="k.icon" /></span>
                                <span><b>{{ k.value }}</b><small>{{ k.label }}</small></span>
                            </div>
                        </div>

                        <div v-if="finance.scholarships.length" class="becas">
                            <div v-for="b in finance.scholarships" :key="b.code" class="beca"><GiftOutlined /><span><b>{{ b.name }}</b><small>{{ b.percentage ? b.percentage + '%' : money(b.amount) }} · hasta {{ b.ends_on || '—' }}</small></span><StatusTag :status="b.status" /></div>
                        </div>

                        <div class="fin-wrap">
                            <SgTable :columns="financeCols" :data-source="unifiedFinance" row-key="_k" size="small" unit="movimientos" >
                                <template #bodyCell="{ column, record }">
                                    <template v-if="column.key === 'kind'">
                                        <span class="mv" :class="`mv--${record.kind}`">
                                            <span class="mv__ic"><WalletOutlined v-if="record.kind === 'cargo'" /><DollarOutlined v-else /></span>{{ record.kindLabel }}
                                        </span>
                                    </template>
                                    <template v-else-if="column.key === 'date'">
                                        <span class="fd"><b>{{ record.date || '—' }}</b><small>{{ record.dateLabel }}</small></span>
                                    </template>
                                    <template v-else-if="column.key === 'concept'">
                                        <span class="fc">
                                            <b :title="record.concept">{{ record.concept }}</b>
                                            <small v-if="record.kind === 'pago'"><PrinterOutlined /> {{ record.folio }} · {{ record.method }}</small>
                                            <small v-else-if="Number(record.discount) > 0"><GiftOutlined /> Descuento {{ money(record.discount) }}</small>
                                        </span>
                                    </template>
                                    <template v-else-if="column.key === 'charge'">
                                        <span v-if="record.charge !== null" class="amt">{{ money(record.charge) }}</span><span v-else class="sg-faint">—</span>
                                    </template>
                                    <template v-else-if="column.key === 'paid'">
                                        <span v-if="record.paid" class="amt amt--ok">{{ money(record.paid) }}</span><span v-else class="sg-faint">—</span>
                                    </template>
                                    <template v-else-if="column.key === 'pending'">
                                        <span v-if="record.pending" class="amt amt--due">{{ money(record.pending) }}</span>
                                        <span v-else-if="record.kind === 'cargo'" class="amt amt--zero">$0.00</span><span v-else class="sg-faint">—</span>
                                    </template>
                                    <template v-else-if="column.key === 'status'">
                                        <span class="fst" :style="{ '--c': finStatus(record.statusKey)[1] }">{{ finStatus(record.statusKey)[0] }}</span>
                                    </template>
                                    <template v-else-if="column.key === 'actions'">
                                        <a-tooltip v-if="record.kind === 'pago' && record.folio" title="Ver recibo">
                                            <a-button class="sg-ab blue" size="small" @click="openReceipt(record)"><EyeOutlined /></a-button>
                                        </a-tooltip>
                                        <span v-else class="sg-faint">—</span>
                                    </template>
                                </template>
                            </SgTable>
                        </div>
                    </a-tab-pane>

                    <!-- INCIDENCIAS -->
                    <a-tab-pane key="incidencias">
                        <template #tab><span class="tab-pill"><WarningOutlined /> Incidencias <a-badge v-if="negIncidents.length" :count="negIncidents.length" :number-style="{ background: '#e3262b' }" /></span></template>

                        <div v-if="!incidents.length" class="all-good big"><LikeOutlined /><b>¡Todo en orden!</b><span>Este alumno no tiene incidencias ni reconocimientos registrados.</span></div>
                        <template v-else>
                            <div class="inc-sum">
                                <span style="--c: #e3262b"><WarningOutlined /> <b>{{ negIncidents.length }}</b> incidencias</span>
                                <span style="--c: #2e9b43"><StarOutlined /> <b>{{ posIncidents.length }}</b> reconocimientos</span>
                            </div>
                            <a-timeline class="inc-tl">
                                <a-timeline-item v-for="i in incidents" :key="i.id" :color="i.positive ? 'green' : (i.priority === 'alta' ? 'red' : 'orange')">
                                    <div class="inc">
                                        <div class="inc__head"><b>{{ i.category }}</b><StatusTag :status="i.status" /><StatusTag v-if="!i.positive && i.priority" :status="i.priority" priority /></div>
                                        <p>{{ i.description }}</p>
                                        <small><CalendarOutlined /> {{ i.date }} · <UserOutlined /> {{ i.teacher || '—' }}</small>
                                        <div><EvidenceButton :url="i.evidence_url" :name="i.evidence_name" :title="`Evidencia · ${i.category}`" /></div>
                                    </div>
                                </a-timeline-item>
                            </a-timeline>
                        </template>
                    </a-tab-pane>
                </a-tabs>
            </a-card>
        </div>

        <!-- ================= MODALES ================= -->
        <AlumnoModal v-if="can.edit" v-model:open="editOpen" :student="form" :options="formOptions" />

        <!-- Modal: desglose de calificaciones de la materia -->
        <SgModal v-model:open="breakdownOpen" title="Desglose de calificaciones" :subtitle="bdRow ? `${bdRow.subject} · ${student.full_name}` : ''" :icon="ReadOutlined"
            color="linear-gradient(135deg, #2e9b43, #14a39a)" :footer="false" :width="bdWidth">
            <template v-if="bdRow">
                <!-- Resumen de la materia -->
                <div class="bd-hero" :style="{ '--g': gradeColor(bdRow.average) }">
                    <div class="bd-ring">
                        <a-progress type="circle" :percent="(bdRow.average ?? 0) * 10" :size="84" :stroke-width="9" :stroke-color="gradeColor(bdRow.average)"
                            :format="() => (bdRow.average !== null ? num(bdRow.average, 1) : '—')" />
                    </div>
                    <div class="bd-hero__txt">
                        <small>Promedio de la materia</small>
                        <b>{{ bdRow.subject }}</b>
                        <span>{{ bdRow.average === null ? 'Aún sin calificaciones finales' : bdRow.average >= 6 ? 'Aprobando la materia' : 'En riesgo de reprobar' }}
                            <template v-if="bdBest"> · Mejor periodo: <b>{{ bdBest.period }}</b> ({{ num(bdBest.shown, 1) }})</template></span>
                    </div>
                    <div class="bd-steps">
                        <div v-for="(s, i) in bdRow.scores" :key="i" class="bd-step" :style="{ '--p': s !== null ? gradeColor(s) : '#cbd5e1' }">
                            <span class="bd-step__dot">{{ s !== null ? num(s, 1) : '—' }}</span>
                            <small>{{ gradeMatrix.periods[i] }}</small>
                        </div>
                    </div>
                </div>

                <!-- Un recuadro por periodo -->
                <div v-if="bdPeriods.length" class="bd-grid" :style="{ '--n': Math.min(bdPeriods.length, 3) }">
                    <section v-for="p in bdPeriods" :key="p.period" class="bd-card" :style="{ '--g': p.shown != null ? gradeColor(p.shown) : '#94a3b8' }">
                        <header class="bd-card__head">
                            <span class="bd-card__ic"><CalendarOutlined /></span>
                            <b>{{ p.period }}</b>
                            <span class="bd-card__final">{{ p.shown != null ? num(p.shown, 1) : '—' }}</span>
                        </header>
                        <small class="bd-card__note">
                            <template v-if="p.final != null">Calificación final publicada</template>
                            <template v-else-if="p.computedScore != null">Calculada con los criterios (sin publicar)</template>
                            <template v-else>Sin calificación final</template>
                        </small>
                        <div v-if="!p.criteria.length" class="empty-mini">Sin criterios capturados</div>
                        <div v-for="(c, ci) in p.criteria" :key="c.name" class="bd-crit" :style="{ '--c': PALETTE[ci % PALETTE.length] }">
                            <div class="bd-crit__top">
                                <span class="bd-crit__name">{{ c.name }}</span>
                                <span class="bd-crit__w">{{ c.weight }}%</span>
                                <b :style="{ color: c.score != null ? gradeColor(c.score) : '#94a3b8' }">{{ c.score != null ? num(c.score, 1) : '—' }}</b>
                            </div>
                            <div class="bd-crit__bar"><i :style="{ width: (c.score ?? 0) * 10 + '%' }" /></div>
                            <small v-if="c.points != null">Aporta <b>{{ num(c.points, 2) }}</b> pts a la calificación</small>
                        </div>
                        <footer v-if="p.criteria.length" class="bd-card__foot">
                            <span>Ponderación capturada <b :class="{ 'is-bad': p.weightSum < 100 }">{{ p.weightSum }}%</b></span>
                            <span v-if="p.weightSum < 100" class="is-warn">Faltan criterios por capturar</span>
                            <span v-else-if="p.computedScore != null">Suma <b>{{ num(p.computedScore, 2) }}</b></span>
                        </footer>
                    </section>
                </div>
                <div v-else class="empty-mini">Sin calificaciones capturadas para esta materia.</div>
            </template>
        </SgModal>

        <SgModal v-model:open="accOpen" :title="student.user_id ? 'Restablecer acceso' : 'Crear cuenta de acceso'" subtitle="Se generará una contraseña temporal que se mostrará una sola vez."
            :icon="KeyOutlined" color="linear-gradient(135deg, #f39200, #e3262b)" width="min(560px, 96vw)" ok-text="Generar acceso" :ok-icon="KeyOutlined" :loading="acc.processing" @ok="createAccount">
            <a-form layout="vertical">
                <a-form-item label="Correo de acceso" :validate-status="acc.errors.email ? 'error' : undefined" :help="acc.errors.email">
                    <a-input v-model:value="acc.email" size="large"><template #prefix><MailOutlined style="color: #e0457b" /></template></a-input>
                </a-form-item>
            </a-form>
        </SgModal>

        <SgModal v-model:open="hOpen" title="Expediente de salud" :subtitle="student.full_name" :icon="MedicineBoxOutlined" color="linear-gradient(135deg, #e3262b, #e0457b)"
            :loading="h.processing" @ok="saveHealth">
            <a-form layout="vertical" class="sg-grid-4">
                <a-form-item label="Grupo sanguíneo"><a-select v-model:value="h.blood_type" allow-clear :options="['O', 'A', 'B', 'AB'].map((v) => ({ value: v, label: v }))" /></a-form-item>
                <a-form-item label="Factor RH"><a-select v-model:value="h.rh_factor" allow-clear :options="['Positivo', 'Negativo'].map((v) => ({ value: v, label: v }))" /></a-form-item>
                <a-form-item label="Peso (kg)"><a-input-number v-model:value="h.weight_kg" :min="0" :max="300" /></a-form-item>
                <a-form-item label="Talla (cm)"><a-input-number v-model:value="h.height_cm" :min="0" :max="250" /></a-form-item>
                <a-form-item label="Alergias" class="span-2"><a-textarea v-model:value="h.allergies" :rows="3" /></a-form-item>
                <a-form-item label="Información médica relevante" class="span-2"><a-textarea v-model:value="h.medical_notes" :rows="3" /></a-form-item>
                <a-form-item label="Consentimiento otorgado por" required class="full" :validate-status="h.errors.consent_by ? 'error' : undefined" :help="h.errors.consent_by">
                    <a-input v-model:value="h.consent_by" placeholder="Nombre del padre, madre o tutor"><template #prefix><SafetyCertificateOutlined style="color: #2e9b43" /></template></a-input>
                </a-form-item>
            </a-form>
        </SgModal>
    </AppLayout>
</template>

<style scoped>
/* ============ FRANJA SUPERIOR ============ */
.hero {
    flex: none; display: flex; align-items: center; gap: 16px; margin-bottom: 14px; padding: 12px 16px; border-radius: 16px; position: relative; overflow: hidden;
    background: linear-gradient(120deg, #fff 0%, #fff 55%, #f0f7fd 100%); border: 1px solid #e2e8f0; box-shadow: 0 6px 18px -14px rgba(11, 47, 85, .35);
}
.hero::before { content: ''; position: absolute; inset: 0 auto 0 0; width: 5px; background: linear-gradient(180deg, #1d7fc9, #14a39a); }
.hero__photo {
    width: 68px; height: 68px; flex: none; border-radius: 18px; overflow: hidden; display: grid; place-items: center; padding: 0; border: 3px solid #fff;
    background: linear-gradient(135deg, #7c4ddb, #1d7fc9); color: #fff; font-size: 24px; font-weight: 800; box-shadow: 0 8px 18px -8px rgba(11, 47, 85, .55); cursor: default;
}
.hero__photo.has-img { cursor: zoom-in; }
.hero__photo img { width: 100%; height: 100%; object-fit: cover; }
.hero__main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 8px; }
.hero__chips { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
.hero__chips :deep(.ant-tag) { margin: 0; }
.chip {
    display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 700;
    color: var(--c); background: color-mix(in srgb, var(--c) 10%, #fff); border: 1px solid color-mix(in srgb, var(--c) 22%, #fff); text-transform: capitalize;
}
.chip--link { cursor: pointer; transition: background .15s; }
.chip--link:hover { background: color-mix(in srgb, var(--c) 18%, #fff); }
.hero__contact { display: flex; flex-wrap: wrap; gap: 6px 18px; font-size: 12.5px; color: #475569; }
.hero__contact span { display: inline-flex; align-items: center; gap: 6px; min-width: 0; }
.hero__contact a, .hero__contact b { color: #0b2f55; font-weight: 600; }
.hero__contact em { color: #94a3b8; font-style: normal; }
.hero__kpis { display: grid; grid-template-columns: repeat(4, minmax(118px, auto)); gap: 8px; flex: none; }
.hk { display: flex; align-items: center; gap: 9px; padding: 8px 12px; border-radius: 12px; background: color-mix(in srgb, var(--c) 6%, #fff); border: 1px solid color-mix(in srgb, var(--c) 22%, #fff); }
.hk__ic { width: 32px; height: 32px; border-radius: 9px; display: grid; place-items: center; color: #fff; background: var(--c); flex: none; font-size: 15px; }
.hk > span:last-child { display: flex; flex-direction: column; min-width: 0; }
.hk b { font-size: 17px; font-weight: 800; color: #0b2f55; line-height: 1.1; white-space: nowrap; }
.hk small { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #64748b; }

/* ============ LAYOUT ============ */
.main { flex: 1; min-width: 0; }

/* ============ TUTORES ============ */
.tutor-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 10px; flex: none; }
.count { margin-left: 2px; min-width: 22px; height: 22px; padding: 0 7px; border-radius: 999px; display: grid; place-items: center; font-size: 11.5px; font-weight: 800; color: #e0457b; background: #fdeef4; }
.tutor { display: flex; align-items: flex-start; gap: 10px; padding: 10px; border-radius: 12px; background: #f8fafc; border: 1px solid #e2e8f0; }
.tutor.is-primary { border-color: #fbd38d; background: #fffbf3; }
.tutor__av { background: linear-gradient(135deg, #e0457b, #f39200); }
.tutor__txt { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.tutor__txt b { font-size: 13px; color: #0b2f55; line-height: 1.25; }
.tutor__txt small { font-size: 11.5px; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tutor__tags { display: flex; flex-wrap: wrap; gap: 4px; }
.mini { display: inline-flex; align-items: center; gap: 3px; padding: 0 6px; border-radius: 6px; font-size: 10.5px; font-weight: 700; color: var(--c); background: color-mix(in srgb, var(--c) 11%, #fff); text-transform: capitalize; }
.add-tutor { margin-left: auto; display: flex; gap: 8px; font-weight: 400; }
.add-tutor__sel { width: 320px; }
.add-tutor__btn { border-radius: 8px !important; font-weight: 600; background: linear-gradient(135deg, #e0457b, #f39200) !important; border: none !important; }
.add-tutor__btn[disabled] { opacity: .5; color: #fff !important; }
.tutor__txt a { color: #0b2f55; }
.empty-mini { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 14px; color: #94a3b8; font-size: 12.5px; border: 1px dashed #e2e8f0; border-radius: 10px; background: #fafbfc; }

/* ============ PESTAÑAS ============ */
.st-tabs :deep(> .ant-tabs-nav) { margin-bottom: 14px; }
.st-tabs :deep(> .ant-tabs-nav .ant-tabs-nav-list) { gap: 6px; }
.st-tabs :deep(> .ant-tabs-nav .ant-tabs-tab) { background: #fff; border: 1px solid #e2e8f0; border-radius: 999px; padding: 5px 12px; margin: 0 !important; transition: all .2s; }
.st-tabs :deep(> .ant-tabs-nav .ant-tabs-tab:hover) { background: #f8fafc; }
.st-tabs :deep(> .ant-tabs-nav .ant-tabs-tab-active) { background: #eff6ff; border-color: #bfdbfe; box-shadow: 0 2px 6px rgba(29, 127, 201, .15); }
.st-tabs :deep(> .ant-tabs-nav .ant-tabs-ink-bar) { display: none; }
.st-tabs :deep(> .ant-tabs-nav::before) { display: none; }
.st-tabs :deep(.ant-tabs-tabpane-active) { padding-right: 4px; }
.tab-pill { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; font-size: 13px; color: #64748b; }
.tab-pill :deep(.ant-badge-count) { box-shadow: none; }
.st-tabs :deep(.ant-tabs-tab-active) .tab-pill { color: #1d7fc9; }

/* ============ SECCIONES ============ */
.sec-title { display: flex; align-items: center; gap: 8px; font-size: 14.5px; font-weight: 800; color: var(--c, #0b2f55); margin-bottom: 12px; flex: none; }
.sec-gap { margin-top: 24px; }
.btn-update { 
    margin-left: auto; border-radius: 8px !important; font-weight: 600;
    background: linear-gradient(135deg, #e3262b, #e0457b) !important; border: none !important;
    box-shadow: 0 2px 6px rgba(227, 38, 43, 0.3);
}

/* ============ NUEVA CARD DE ASISTENCIA ============ */
.att-horizontal {
    display: flex;
    align-items: center;
    gap: 24px;
    padding: 2px 0;
}
.att-left {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.att-big-number {
    font-size: 36px;
    font-weight: 900;
    line-height: 1;
    letter-spacing: -1px;
}
.att-label {
    font-size: 14px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.att-bar-bg {
    width: 100%;
    height: 14px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
    margin-top: 6px;
}
.att-bar-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 0.5s ease;
}
.att-right {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    flex: none;
    width: 560px;
}
.att-stat {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #f8fafc;
    padding: 8px 10px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}
.att-stat-icon {
    font-size: 20px;
    color: var(--c);
    background: color-mix(in srgb, var(--c) 12%, #fff);
    width: 34px; height: 34px; border-radius: 8px;
    display: grid; place-items: center;
}
.att-stat-info {
    display: flex;
    flex-direction: column;
}
.att-stat-info b {
    font-size: 18px;
    color: #0b2f55;
    line-height: 1;
}
.att-stat-info span {
    font-size: 11.5px;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
    margin-top: 4px;
}

/* ============ RENDIMIENTO (barras) ============ */
.bars { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 22px; }
.bar { display: grid; grid-template-columns: minmax(0, 150px) 1fr 40px; align-items: center; gap: 10px; }
.bar__name { font-size: 12.5px; font-weight: 600; color: #334155; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.bar__track { height: 9px; border-radius: 999px; background: #eef2f7; overflow: hidden; }
.bar__track i { display: block; height: 100%; border-radius: 999px; transition: width .4s; }
.bar__val { font-size: 14px; text-align: right; }
.li-row { display: flex; align-items: center; gap: 10px; padding: 7px 0; border-top: 1px dashed #eef2f7; }
.li-row:first-of-type { border-top: none; }
.li-row__txt { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.li-row__txt b { font-size: 13px; color: #0b2f55; }
.li-row__txt small { font-size: 11.5px; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dot { width: 9px; height: 9px; border-radius: 50%; flex: none; }
.money { color: #1f7a32; font-variant-numeric: tabular-nums; }

/* ============ RESUMEN ============ */
.alerts { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 10px; margin-bottom: 14px; flex: none; }
.alert { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 12px; text-align: left; font-family: inherit; min-width: 0; background: color-mix(in srgb, var(--c) 7%, #fff); border: 1px solid color-mix(in srgb, var(--c) 25%, #fff); border-left: 4px solid var(--c); }
.alert.is-link { cursor: pointer; transition: transform .15s, box-shadow .15s; }
.alert.is-link:hover { transform: translateY(-2px); box-shadow: 0 8px 18px -12px var(--c); }
.alert__ic { width: 32px; height: 32px; border-radius: 9px; display: grid; place-items: center; color: #fff; background: var(--c); flex: none; }
.alert__txt { display: flex; flex-direction: column; min-width: 0; flex: 1; }
.alert__txt b { font-size: 13px; color: #0b2f55; }
.alert__txt small { font-size: 11.5px; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.alert__go { color: var(--c); }
.all-good { display: flex; align-items: center; gap: 8px; padding: 10px 14px; margin-bottom: 14px; border-radius: 12px; background: #ecfdf3; border: 1px solid #bbf7d0; color: #1f7a32; font-weight: 600; font-size: 13px; flex: none; }
.all-good.big { flex-direction: column; justify-content: center; padding: 40px 16px; text-align: center; }
.all-good.big .anticon { font-size: 34px; }
.all-good.big b { font-size: 16px; color: #0b2f55; }
.all-good.big span { font-weight: 500; color: #64748b; }

.sum-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; flex: none; }
.box { border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px 14px; background: #fff; min-width: 0; border-top: 3px solid var(--c); }
.box--wide { grid-column: 1 / -1; }
.box__head { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.box__head > b { color: #0b2f55; font-size: 13.5px; }
.box__ic { width: 28px; height: 28px; border-radius: 8px; display: grid; place-items: center; color: #fff; background: var(--c); font-size: 13px; flex: none; }
.box__link { margin-left: auto; font-size: 12px; font-weight: 700; color: var(--c); cursor: pointer; display: inline-flex; align-items: center; gap: 4px; }
.box__meta { margin-left: auto; font-size: 12px; color: #64748b; }

/* ============ ACADÉMICO ============ */
.subject-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 12px; flex: none; }
.subject-item {
    display: flex; flex-direction: column; gap: 10px; padding: 14px; text-align: left; font-family: inherit; cursor: pointer;
    background: #fff; border: 1px solid #e2e8f0; border-top: 3px solid var(--c); border-radius: 14px; transition: transform .15s, box-shadow .15s, border-color .15s;
}
.subject-item:hover { transform: translateY(-2px); border-color: color-mix(in srgb, var(--c) 45%, #e2e8f0); box-shadow: 0 12px 24px -16px var(--c); }
.subject-info { display: flex; align-items: center; gap: 10px; }
.subject-ic { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; color: #fff; background: var(--c); flex: none; }
.subject-name { flex: 1; min-width: 0; font-size: 14px; font-weight: 800; color: #0b2f55; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.subject-score { font-size: 20px; font-weight: 900; color: var(--g); }
.subject-bar-bg { height: 7px; background: #eef2f7; border-radius: 999px; overflow: hidden; }
.subject-bar-fill { height: 100%; border-radius: 999px; background: var(--g); transition: width .4s; }
.subject-periods { display: flex; gap: 6px; flex-wrap: wrap; }
.sp { display: flex; flex-direction: column; align-items: center; min-width: 64px; padding: 4px 8px; border-radius: 10px; background: color-mix(in srgb, var(--p) 9%, #fff); border: 1px solid color-mix(in srgb, var(--p) 25%, #fff); }
.sp small { font-size: 10px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
.sp b { font-size: 14px; color: var(--p); }
.subject-btn { align-self: flex-end; display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; color: var(--c); }
.subject-item:hover .subject-btn { gap: 9px; }

/* ============ EXPEDIENTE ============ */
.info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 10px; flex: none; }
.info { display: flex; gap: 12px; align-items: center; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fafbfc; transition: all .2s; min-width: 0; }
.info:hover { background: #fff; border-color: #cbd5e1; }
.info.is-warn { background: #fff8eb; border-color: #fbd38d; }
.info__ic { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; color: #fff; background: var(--c); flex: none; }
.info__text { display: flex; flex-direction: column; min-width: 0; }
.info small { font-size: 10.5px; color: #64748b; text-transform: uppercase; letter-spacing: .04em; font-weight: 700; }
.info b { font-size: 13px; color: #0b2f55; word-break: break-word; }
.info--span2 { grid-column: span 2; }
.sensitive { display: flex; align-items: center; gap: 8px; padding: 8px 12px; margin-bottom: 10px; border-radius: 10px; background: #f0f7fd; color: #0f5a99; font-size: 12.5px; flex: none; }

.timeline { position: relative; padding-left: 20px; display: flex; flex-direction: column; gap: 12px; flex: none; }
.timeline::before { content: ''; position: absolute; left: 6px; top: 8px; bottom: 8px; width: 2px; background: #e2e8f0; }
.tl-item { position: relative; }
.tl-marker { position: absolute; left: -20px; top: 14px; width: 14px; height: 14px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 0 0 1px #cbd5e1; }
.tl-content { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 14px; }
.tl-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.tl-header b { font-size: 14px; color: #0b2f55; }
.tl-body { display: flex; flex-wrap: wrap; gap: 14px; font-size: 12.5px; color: #475569; }
.tl-body span { display: flex; align-items: center; gap: 5px; }
.tl-reason { margin-top: 8px; font-size: 12px; color: #e3262b; background: #fef2f2; padding: 5px 10px; border-radius: 6px; display: inline-block; }

/* ============ FINANZAS ============ */
.fin-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-bottom: 14px; flex: none; }
.fin-kpi { position: relative; overflow: hidden; display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 13px; background: #fff; border: 1px solid #e2e8f0; }
.fin-kpi::before { content: ''; position: absolute; inset: 0 auto 0 0; width: 4px; background: var(--c); }
.fin-kpi__ic { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; color: var(--c); background: color-mix(in srgb, var(--c) 12%, #fff); flex: none; }
.fin-kpi > span:last-child { display: flex; flex-direction: column; min-width: 0; }
.fin-kpi b { font-size: 16px; color: #0b2f55; }
.fin-kpi small { font-size: 10.5px; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; font-weight: 700; }
.becas { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; flex: none; }
.beca { display: flex; gap: 10px; align-items: center; padding: 8px 12px; border-radius: 12px; background: #f3effa; color: #6b4c8c; border: 1px solid #e2d6f5; }
.beca small { display: block; color: #64748b; font-size: 11.5px; }
.fin-wrap { flex: 1 1 auto; min-height: 320px; display: flex; flex-direction: column; border: 1px solid #e2e8f0; border-radius: 14px; padding: 8px; background: #fff; }
.mv { display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-size: 12.5px; }
.mv__ic { width: 24px; height: 24px; border-radius: 7px; display: grid; place-items: center; font-size: 12px; color: #fff; }
.mv--cargo { color: #b45309; } .mv--cargo .mv__ic { background: linear-gradient(135deg, #f39200, #e3262b); }
.mv--pago { color: #1f7a32; } .mv--pago .mv__ic { background: linear-gradient(135deg, #2e9b43, #14a39a); }
.fd { display: flex; flex-direction: column; line-height: 1.2; }
.fd b { color: #0b2f55; font-size: 12.5px; }
.fd small, .fc small { font-size: 11px; color: #94a3b8; }
.fc { display: flex; flex-direction: column; min-width: 0; line-height: 1.25; }
.fc b { color: #0b2f55; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.amt { font-variant-numeric: tabular-nums; font-weight: 700; color: #0b2f55; }
.amt--ok { color: #1f7a32; }
.amt--due { color: #e3262b; font-weight: 800; }
.amt--zero { color: #94a3b8; }
.fst { display: inline-flex; padding: 2px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 700; color: var(--c); background: color-mix(in srgb, var(--c) 11%, #fff); border: 1px solid color-mix(in srgb, var(--c) 25%, #fff); }

/* ============ INCIDENCIAS ============ */
.inc-sum { display: flex; gap: 10px; margin-bottom: 14px; flex: none; }
.inc-sum span { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 10px; font-size: 13px; color: var(--c); background: color-mix(in srgb, var(--c) 9%, #fff); border: 1px solid color-mix(in srgb, var(--c) 22%, #fff); }
.inc-sum b { color: #0b2f55; }
.inc-tl { padding-top: 6px; flex: none; }
.inc { background: #fafbfc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 12px; }
.inc__head { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.inc__head b { color: #0b2f55; margin-right: 4px; }
.inc p { margin: 6px 0; color: #334155; font-size: 13px; }
.inc small { color: #64748b; font-size: 12px; }

/* ============ MODAL DESGLOSE ============ */
.bd-hero { display: flex; align-items: center; gap: 16px; padding: 14px 16px; margin-bottom: 14px; border-radius: 14px; background: linear-gradient(120deg, color-mix(in srgb, var(--g) 8%, #fff), #fff 70%); border: 1px solid color-mix(in srgb, var(--g) 25%, #e2e8f0); }
.bd-ring :deep(.ant-progress-text) { font-weight: 800; color: var(--g) !important; font-size: 20px !important; }
.bd-hero__txt { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.bd-hero__txt small { font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #94a3b8; }
.bd-hero__txt > b { font-size: 18px; color: #0b2f55; }
.bd-hero__txt span { font-size: 12.5px; color: #64748b; }
.bd-steps { display: flex; align-items: flex-start; gap: 0; }
.bd-step { position: relative; display: flex; flex-direction: column; align-items: center; gap: 4px; min-width: 84px; }
.bd-step + .bd-step::before { content: ''; position: absolute; top: 21px; right: 50%; width: 100%; height: 3px; background: #e2e8f0; z-index: 0; }
.bd-step__dot { position: relative; z-index: 1; width: 44px; height: 44px; border-radius: 50%; display: grid; place-items: center; font-weight: 800; font-size: 14px; color: var(--p); background: #fff; border: 3px solid var(--p); }
.bd-step small { font-size: 10.5px; font-weight: 700; color: #64748b; text-transform: uppercase; text-align: center; }
.bd-grid { display: grid; grid-template-columns: repeat(var(--n), minmax(0, 1fr)); gap: 12px; }
.bd-card { display: flex; flex-direction: column; gap: 8px; padding: 12px; border-radius: 14px; background: #fff; border: 1px solid #e2e8f0; border-top: 4px solid var(--g); }
.bd-card__head { display: flex; align-items: center; gap: 8px; }
.bd-card__ic { width: 28px; height: 28px; border-radius: 8px; display: grid; place-items: center; color: #fff; background: var(--g); font-size: 13px; }
.bd-card__head > b { flex: 1; color: #0b2f55; font-size: 14px; }
.bd-card__final { font-size: 22px; font-weight: 900; color: var(--g); }
.bd-card__note { font-size: 11.5px; color: #94a3b8; margin-top: -4px; }
.bd-crit { padding: 8px 10px; border-radius: 10px; background: #f8fafc; border: 1px solid #eef2f7; display: flex; flex-direction: column; gap: 5px; }
.bd-crit__top { display: flex; align-items: center; gap: 8px; }
.bd-crit__name { flex: 1; min-width: 0; font-size: 13px; font-weight: 600; color: #334155; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.bd-crit__w { font-size: 11px; font-weight: 800; color: var(--c); background: color-mix(in srgb, var(--c) 12%, #fff); padding: 1px 7px; border-radius: 999px; }
.bd-crit__top b { font-size: 15px; min-width: 32px; text-align: right; }
.bd-crit__bar { height: 6px; border-radius: 999px; background: #e2e8f0; overflow: hidden; }
.bd-crit__bar i { display: block; height: 100%; border-radius: 999px; background: var(--c); }
.bd-crit small { font-size: 11px; color: #64748b; }
.bd-card__foot { display: flex; justify-content: space-between; gap: 8px; margin-top: auto; padding-top: 8px; border-top: 1px dashed #e2e8f0; font-size: 12px; color: #64748b; }
.bd-card__foot b { color: #0b2f55; }
.bd-card__foot b.is-bad { color: #e3262b; }
.bd-card__foot .is-warn { color: #b45309; font-weight: 600; }

/* ============ RESPONSIVE ============ */
@media (max-width: 1280px) {
    .hero__kpis { grid-template-columns: repeat(2, minmax(118px, auto)); }
}
@media (max-width: 1200px) {
    .bars { grid-template-columns: 1fr; }
    .bd-grid { grid-template-columns: 1fr; }
    .bd-hero { flex-wrap: wrap; }
    .att-horizontal { flex-direction: column; align-items: stretch; gap: 20px; }
    .att-right { width: 100%; grid-template-columns: 1fr 1fr; }
    .fin-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 900px) {
    .hero { flex-wrap: wrap; }
    .hero__kpis { width: 100%; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .add-tutor { width: 100%; margin-left: 0; } .add-tutor__sel { flex: 1; width: auto; }
    .sec-title { flex-wrap: wrap; }
    .sum-grid { grid-template-columns: 1fr; }
    .att-right { grid-template-columns: 1fr; }
    .info--span2 { grid-column: auto; }
}
</style>