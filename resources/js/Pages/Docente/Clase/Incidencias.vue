<script setup>
import ClaseShell from '@/Components/Clase/ClaseShell.vue';
import IncidentsTab from '@/Components/Clase/IncidentsTab.vue';

defineProps({ course: Object, section: String, students: Array, periods: Array, periodId: [Number, null], incidents: Array, options: Object });
</script>

<template>
    <ClaseShell :course="course" :section="section" :count="students.length">
        <div class="panel"><IncidentsTab :class-id="course.id" :students="students" :incidents="incidents" :options="options" /></div>
    </ClaseShell>
</template>

<style scoped>
.panel { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; }
</style>
