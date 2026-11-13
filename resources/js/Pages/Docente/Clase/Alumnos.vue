<script setup>
import { computed } from 'vue';
import { UserOutlined, NumberOutlined, CheckCircleOutlined, CloseCircleOutlined, TrophyOutlined, WarningOutlined } from '@ant-design/icons-vue';
import ClaseShell from '@/Components/Clase/ClaseShell.vue';
import SgTable from '@/Components/SgTable.vue';
import { num, gradeColor } from '@/lib/format';
import { openFile } from '@/lib/viewer';

const props = defineProps({ course: Object, section: String, students: Array, periods: Array, periodId: [Number, null] });

const columns = [
    { title: 'Matrícula', key: 'enrollment_number', dataIndex: 'enrollment_number', icon: NumberOutlined, width: 130 },
    { title: 'Alumno', key: 'name', dataIndex: 'list_name', icon: UserOutlined },
    { title: 'Asistencia', key: 'att', icon: CheckCircleOutlined, width: 170, filter: 'select', filterValue: (r) => (r.attendance_rate === null ? 'Sin registros' : r.attendance_rate >= 90 ? '90% o más' : r.attendance_rate >= 80 ? '80–89%' : 'Menos de 80%') },
    { title: 'Faltas', key: 'absences', dataIndex: 'absences', icon: CloseCircleOutlined, width: 100, align: 'center', filter: false },
    { title: 'Promedio', key: 'average', icon: TrophyOutlined, width: 120, align: 'center', filter: 'select', filterValue: (r) => (r.average === null ? 'Sin calificación' : r.average >= 6 ? 'Aprobando' : 'En riesgo') },
    { title: 'Incidencias', key: 'incidents', dataIndex: 'incidents', icon: WarningOutlined, width: 120, align: 'center', filter: false },
];
const attColor = (v) => (v === null ? '#94a3b8' : v >= 90 ? '#2e9b43' : v >= 80 ? '#f39200' : '#e3262b');
const risk = computed(() => props.students.filter((s) => (s.average !== null && s.average < 6) || (s.attendance_rate !== null && s.attendance_rate < 80)).length);
</script>

<template>
    <ClaseShell :course="course" :section="section" :count="students.length">
        <div class="sum">
            <span style="--c: #1d7fc9"><UserOutlined /> <b>{{ students.length }}</b> alumnos</span>
            <span style="--c: #e3262b"><WarningOutlined /> <b>{{ risk }}</b> en riesgo (promedio &lt; 6 o asistencia &lt; 80%)</span>
        </div>
        <a-card :bordered="false" class="sg-fill">
            <SgTable :columns="columns" :data-source="students" unit="alumnos">
                <template #bodyCell="{ column, record, index }">
                    <template v-if="column.key === 'n'"><span class="sg-faint">{{ index + 1 }}</span></template>
                    <template v-else-if="column.key === 'name'">
                        <div class="sg-who">
                            <span class="sg-av" :style="record.photo_url ? 'cursor: zoom-in' : ''" @click="record.photo_url && openFile({ url: record.photo_url, name: 'foto.jpg', title: record.list_name })">
                                <img v-if="record.photo_url" :src="record.photo_url" alt="" /><UserOutlined v-else />
                            </span>
                            <b>{{ record.list_name }}</b>
                        </div>
                    </template>
                    <template v-else-if="column.key === 'att'">
                        <div v-if="record.attendance_rate !== null" class="att" :style="{ '--c': attColor(record.attendance_rate) }">
                            <span class="att__bar"><i :style="{ width: record.attendance_rate + '%' }" /></span><b>{{ record.attendance_rate }}%</b>
                        </div>
                        <span v-else class="sg-faint">Sin registros</span>
                    </template>
                    <template v-else-if="column.key === 'absences'"><b :style="{ color: record.absences ? '#e3262b' : '#94a3b8' }">{{ record.absences }}</b></template>
                    <template v-else-if="column.key === 'average'">
                        <span v-if="record.average !== null" class="avg" :style="{ '--g': gradeColor(record.average) }">{{ num(record.average, 1) }}</span>
                        <span v-else class="sg-faint">—</span>
                    </template>
                    <template v-else-if="column.key === 'incidents'"><b :style="{ color: record.incidents ? '#f39200' : '#94a3b8' }">{{ record.incidents }}</b></template>
                </template>
            </SgTable>
        </a-card>
    </ClaseShell>
</template>

<style scoped>
.sum { flex: none; display: flex; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; }
.sum span { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 10px; font-size: 13px; color: var(--c); background: color-mix(in srgb, var(--c) 8%, #fff); border: 1px solid color-mix(in srgb, var(--c) 22%, #fff); }
.sum b { color: #0b2f55; }
.att { display: flex; align-items: center; gap: 8px; }
.att__bar { flex: 1; height: 7px; border-radius: 999px; background: #eef2f7; overflow: hidden; }
.att__bar i { display: block; height: 100%; border-radius: 999px; background: var(--c); }
.att b { color: var(--c); font-size: 12.5px; min-width: 36px; text-align: right; }
.avg { font-weight: 800; color: var(--g); background: color-mix(in srgb, var(--g) 10%, #fff); padding: 2px 10px; border-radius: 999px; }
</style>
