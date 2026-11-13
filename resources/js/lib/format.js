/** Utilidades de formato y catálogo de estados para etiquetas. */

export const money = (v) =>
    (Number(v) || 0).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });

export const num = (v, d = 1) =>
    v === null || v === undefined || v === '' ? '—' : Number(v).toLocaleString('es-MX', { maximumFractionDigits: d, minimumFractionDigits: 0 });

/** Color de calificación según aprobatoria. */
export const gradeColor = (v, passing = 6) =>
    v === null || v === undefined ? '#94a3b8' : (v >= 9 ? '#16a34a' : v >= passing ? '#0f5a99' : '#dc2626');

/** [etiqueta, color de a-tag] por estado. */
export const STATUS = {
    // alumnos
    aspirante: ['Aspirante', 'gold'], activo: ['Activo', 'green'], baja: ['Baja', 'red'], egresado: ['Egresado', 'blue'], titulado: ['Titulado', 'purple'],
    inactivo: ['Inactivo', 'default'], licencia: ['Licencia', 'orange'],
    // inscripciones
    pendiente: ['Pendiente', 'gold'], autorizada: ['Autorizada', 'green'], concluida: ['Concluida', 'default'],
    // documentos
    validado: ['Validado', 'green'], rechazado: ['Rechazado', 'red'], correccion: ['Corrección', 'orange'],
    // finanzas
    parcial: ['Pago parcial', 'blue'], pagado: ['Pagado', 'green'], cancelado: ['Cancelado', 'default'], aplicado: ['Aplicado', 'green'],
    activa: ['Activa', 'green'], suspendida: ['Suspendida', 'red'], vencida: ['Vencida', 'default'], prueba: ['Prueba', 'gold'],
    // incidencias / tutorías
    abierta: ['Abierta', 'gold'], seguimiento: ['En seguimiento', 'blue'], cerrada: ['Cerrada', 'default'],
    alta: ['Alta', 'red'], media: ['Media', 'orange'], baja_p: ['Baja', 'default'],
    // asistencia
    presente: ['Asistencia', 'green'], falta: ['Falta', 'red'], retardo: ['Retardo', 'orange'], justificada: ['Justificada', 'blue'],
    // entregas
    entregada: ['Entregada', 'blue'], tarde: ['Entregada tarde', 'orange'], calificada: ['Calificada', 'green'], devuelta: ['Devuelta', 'purple'], sin_entrega: ['Sin entrega', 'default'],
    // ciclos
    planeacion: ['Planeación', 'gold'], abierto: ['Abierto', 'green'], cerrado: ['Cerrado', 'default'],
};

export const statusLabel = (s) => STATUS[s]?.[0] ?? s;
export const statusColor = (s) => STATUS[s]?.[1] ?? 'default';

/** Construye un objeto { valor: etiqueta } a partir de opciones [{value,label}]. */
export const optionMap = (opts = []) => Object.fromEntries(opts.map((o) => [o.value, o.label]));

/** Elimina claves vacías de un objeto de filtros. */
export const clean = (obj) => Object.fromEntries(Object.entries(obj).filter(([, v]) => v !== '' && v !== null && v !== undefined));
