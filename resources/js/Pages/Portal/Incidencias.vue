<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PortalHeader from '@/Components/PortalHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import { WarningOutlined, LikeOutlined, AlertOutlined, CheckCircleOutlined } from '@ant-design/icons-vue';
import StatusTag from '@/Components/StatusTag.vue';
import EvidenceButton from '@/Components/EvidenceButton.vue';

defineProps({ student: Object, children: Array, isGuardian: Boolean, items: Array });
</script>

<template>
    <AppLayout title="Incidencias">
        <PortalHeader :student="student" :children="children" :is-guardian="isGuardian" title="Incidencias y reconocimientos" />

        <div class="sg-stats">
            <StatCard label="Registros" :value="items.length" :icon="WarningOutlined" color="indigo" />
            <StatCard label="Reconocimientos" :value="items.filter((i) => i.positive).length" :icon="LikeOutlined" color="green" />
            <StatCard label="A atender" :value="items.filter((i) => !i.positive).length" :icon="AlertOutlined" color="amber" />
            <StatCard label="Cerradas" :value="items.filter((i) => i.status === 'Cerrada').length" :icon="CheckCircleOutlined" color="slate" />
        </div>

        <a-card :bordered="false">
            <a-empty v-if="!items.length" description="Sin incidencias registradas" />
            <a-timeline v-else>
                <a-timeline-item v-for="i in items" :key="i.id" :color="i.positive ? 'green' : (i.priority === 'alta' ? 'red' : 'orange')">
                    <div class="sg-strong">{{ i.category }} <a-tag :bordered="false">{{ i.status }}</a-tag><StatusTag v-if="!i.positive" :status="i.priority" priority /></div>
                    <div>{{ i.description }}</div>
                    <div class="sg-cell-sub">{{ i.date }} · {{ i.teacher || '—' }}{{ i.subject ? ' · ' + i.subject : '' }}</div>
                    <div v-if="i.follow_up" class="fu">Seguimiento: {{ i.follow_up }}</div>
                    <EvidenceButton :url="i.evidence_url" :name="i.evidence_name" :title="`Evidencia · ${i.category}`" />
                </a-timeline-item>
            </a-timeline>
        </a-card>
    </AppLayout>
</template>

<style scoped>
.fu { margin-top: 4px; background: #f5f9fd; border-radius: 8px; padding: 6px 10px; font-size: 13px; }
</style>
