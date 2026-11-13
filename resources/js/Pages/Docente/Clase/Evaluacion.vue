<script setup>
import ClaseShell from '@/Components/Clase/ClaseShell.vue';
import EvaluationTab from '@/Components/Clase/EvaluationTab.vue';

defineProps({ course: Object, section: String, students: Array, periods: Array, periodId: [Number, null], evaluation: { type: [Object, null], default: null }, options: Object });
</script>

<template>
    <ClaseShell :course="course" :section="section" :count="students.length">
        <div class="panel"><EvaluationTab :class-id="course.id" :students="students" :periods="periods" :period-id="periodId" :evaluation="evaluation" :options="options" /></div>
    </ClaseShell>
</template>

<style scoped>
.panel { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 0 2px 8px; }
</style>
