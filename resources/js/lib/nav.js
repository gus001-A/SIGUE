import {
    DatabaseOutlined, HomeOutlined, TeamOutlined, UserOutlined, HeartOutlined, AppstoreOutlined, WarningOutlined, BookOutlined,
    SolutionOutlined, FolderOpenOutlined, WalletOutlined, DollarOutlined, GiftOutlined, BarChartOutlined,
    SettingOutlined, SafetyOutlined, AuditOutlined, BankOutlined, CheckSquareOutlined, ReadOutlined, IdcardOutlined,
    FileDoneOutlined, ScheduleOutlined, CalendarOutlined, MailOutlined, UsergroupAddOutlined, RiseOutlined, FileTextOutlined,
} from '@ant-design/icons-vue';

const P = ['portal.alumno', 'portal.tutor'];

/**
 * Menú lateral de SIGUE por secciones. Cada opción declara los permisos que la
 * habilitan (basta con uno) y su color de icono.
 */
const SECTIONS = [
    {
        label: 'General',
        items: [
            { key: 'dashboard', icon: HomeOutlined, color: '#1d7fc9', label: 'Inicio', route: 'dashboard', perms: ['dashboard.ver'] },
            { key: 'reportes', icon: BarChartOutlined, color: '#e3262b', label: 'Reportes', route: 'reportes.index', perms: ['reportes.ver', 'reportes.financieros'] },
            { key: 'mensajes', icon: MailOutlined, color: '#2e9b43', label: 'Mensajes', route: 'mensajes.index', perms: ['mensajes.usar'], badge: 'messages' },
        ],
    },
    {
        // Todo lo que gira alrededor del alumno y su familia
        label: 'Alumnos y familias',
        items: [
            { key: 'alumnos', icon: TeamOutlined, color: '#1d7fc9', label: 'Alumnos', route: 'alumnos.index', perms: ['alumnos.ver'] },
            { key: 'tutores', icon: HeartOutlined, color: '#e0457b', label: 'Padres y tutores', route: 'tutores.index', perms: ['tutores.ver'] },
            { key: 'matricula', icon: SolutionOutlined, color: '#0f5a99', label: 'Inscripciones y bajas', route: 'matricula.index', perms: ['matricula.gestionar'] },
            { key: 'documentos', icon: FolderOpenOutlined, color: '#f39200', label: 'Documentos', route: 'documentos.index', perms: ['documentos.validar'] },
            { key: 'incidencias', icon: WarningOutlined, color: '#e3262b', label: 'Incidencias', route: 'incidencias.index', perms: ['incidencias.ver'] },
        ],
    },
    {
        // Gestión de la planta docente y la organización escolar
        label: 'Académico',
        items: [
            { key: 'docentes', icon: UserOutlined, color: '#7c4ddb', label: 'Docentes', route: 'docentes.index', perms: ['docentes.ver'] },
            { key: 'grupos', icon: AppstoreOutlined, color: '#14a39a', label: 'Grupos y horarios', route: 'grupos.index', perms: ['academico.gestionar'] },
            { key: 'catalogos.asignaturas', icon: BookOutlined, color: '#f39200', label: 'Asignaturas', route: 'catalogos.index', params: { catalog: 'asignaturas' }, perms: ['configuracion.gestionar'] },
        ],
    },
    {
        label: 'Finanzas',
        items: [
            { key: 'finanzas.adeudos', icon: WalletOutlined, color: '#e3262b', label: 'Adeudos', route: 'finanzas.adeudos', perms: ['finanzas.ver'] },
            { key: 'finanzas.pagos', icon: DollarOutlined, color: '#2e9b43', label: 'Pagos y recibos', route: 'finanzas.pagos', perms: ['finanzas.ver'] },
            { key: 'finanzas.becas', icon: GiftOutlined, color: '#6b4c8c', label: 'Becas', route: 'finanzas.becas', perms: ['becas.gestionar'] },
        ],
    },
    {
        label: 'Docencia',
        items: [
            { key: 'docente.inicio', icon: CheckSquareOutlined, color: '#2e9b43', label: 'Mi día', route: 'docente.inicio', perms: ['docencia.usar'] },
            { key: 'docente.clases', icon: ReadOutlined, color: '#1d7fc9', label: 'Mis clases', route: 'docente.clases', perms: ['docencia.usar'], also: ['docente.clase'] },
            // Secciones de la clase de trabajo (solo aparecen cuando el docente eligió una clase)
            { key: 'docente.clase.asistencia', icon: CheckSquareOutlined, color: '#2e9b43', label: 'Pase de lista', route: 'docente.clase.asistencia', perms: ['docencia.usar'], needsClass: true, group: 'Mi clase' },
            { key: 'docente.clase.classroom', icon: BookOutlined, color: '#f39200', label: 'Classroom', route: 'docente.clase.classroom', perms: ['docencia.usar'], needsClass: true, group: 'Mi clase' },
            { key: 'docente.clase.evaluacion', icon: FileDoneOutlined, color: '#7c4ddb', label: 'Evaluación', route: 'docente.clase.evaluacion', perms: ['docencia.usar'], needsClass: true, group: 'Mi clase' },
            { key: 'docente.clase.incidencias', icon: WarningOutlined, color: '#e3262b', label: 'Incidencias', route: 'docente.clase.incidencias', perms: ['docencia.usar'], needsClass: true, group: 'Mi clase' },
            { key: 'docente.clase.alumnos', icon: TeamOutlined, color: '#14a39a', label: 'Alumnos', route: 'docente.clase.alumnos', perms: ['docencia.usar'], needsClass: true, group: 'Mi clase' },
            { key: 'docente.tutorias', icon: IdcardOutlined, color: '#7c4ddb', label: 'Tutorías', route: 'docente.tutorias', perms: ['docencia.usar'] },
        ],
    },
    {
        label: 'Mi escuela',
        items: [
            { key: 'portal.inicio', icon: HomeOutlined, color: '#1d7fc9', label: 'Inicio', route: 'portal.inicio', perms: P },
            { group: 'Escolar', key: 'portal.calificaciones', icon: FileDoneOutlined, color: '#2e9b43', label: 'Calificaciones', route: 'portal.calificaciones', perms: P },
            { group: 'Escolar', key: 'portal.clases', icon: ReadOutlined, color: '#1d7fc9', label: 'Mis clases', route: 'portal.clases', perms: P },
            { group: 'Escolar', key: 'portal.tareas', icon: BookOutlined, color: '#f39200', label: 'Tareas', route: 'portal.tareas', perms: P },
            { group: 'Escolar', key: 'portal.horario', icon: ScheduleOutlined, color: '#14a39a', label: 'Horario', route: 'portal.horario', perms: P },
            { group: 'Escolar', key: 'portal.asistencias', icon: CalendarOutlined, color: '#0f5a99', label: 'Asistencias', route: 'portal.asistencias', perms: P },
            { group: 'Escolar', key: 'portal.incidencias', icon: WarningOutlined, color: '#e3262b', label: 'Incidencias', route: 'portal.incidencias', perms: P },
            { group: 'Trámites', key: 'portal.documentos', icon: FolderOpenOutlined, color: '#6b4c8c', label: 'Documentos', route: 'portal.documentos', perms: P },
            { group: 'Trámites', key: 'portal.pagos', icon: WalletOutlined, color: '#e0457b', label: 'Pagos', route: 'portal.pagos', perms: P },
        ],
    },
    {
        label: 'Administración',
        items: [
            { key: 'configuracion', icon: SettingOutlined, color: '#0b3f6e', label: 'Institución', route: 'configuracion', perms: ['configuracion.gestionar'] },
            { key: 'catalogos', icon: DatabaseOutlined, color: '#14a39a', label: 'Catálogos', route: 'catalogos.index', params: { catalog: 'ciclos' }, catalogs: ['ciclos', 'periodos', 'grados', 'tipos-documento'], perms: ['configuracion.gestionar'] },
            { key: 'usuarios', icon: UsergroupAddOutlined, color: '#1d7fc9', label: 'Usuarios', route: 'usuarios.index', perms: ['usuarios.gestionar'] },
        ],
    },
];

/** Secciones visibles según permisos (y si hay institución activa para el superadmin). */
export function buildNav(perms, hasTenant = true) {
    return SECTIONS
        .filter((s) => hasTenant || s.global)
        .map((s) => ({ ...s, items: s.items.filter((i) => i.perms.some((p) => perms.includes(p))) }))
        .filter((s) => s.items.length);
}

/** Opción activa según la ruta actual. */
export function activeKey(sections) {
    let current;
    try { current = route().current(); } catch (e) { return null; }
    if (!current) return null;
    const params = route().params ?? {};
    // Primero la coincidencia exacta (p. ej. docente.clase.asistencia antes que "Mis clases").
    for (const s of sections) {
        for (const i of s.items) {
            if (i.params?.catalog && !(i.catalogs ?? [i.params.catalog]).includes(params.catalog)) continue;
            if (i.route === current) return i.key;
        }
    }
    for (const s of sections) {
        for (const i of s.items) {
            if (i.params?.catalog && !(i.catalogs ?? [i.params.catalog]).includes(params.catalog)) continue;
            const names = [i.route, ...(i.also ?? [])];
            if (names.some((r) => current === r || current.startsWith(r.replace(/\.index$/, '') + '.'))) return i.key;
        }
    }
    return null;
}
