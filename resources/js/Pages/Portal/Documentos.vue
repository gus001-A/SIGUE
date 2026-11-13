<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PortalHeader from '@/Components/PortalHeader.vue';
import DocumentsPanel from '@/Components/DocumentsPanel.vue';

defineProps({ student: Object, children: Array, isGuardian: Boolean, documents: Array, documentTypes: Array, missing: Array });
</script>

<template>
    <AppLayout title="Documentos">
        <PortalHeader :student="student" :children="children" :is-guardian="isGuardian" title="Documentos del expediente" />
        <a-card :bordered="false">
            <DocumentsPanel :documents="documents" :document-types="documentTypes" :missing="missing" :upload-url="route('alumnos.documentos.store', student.id)" />
        </a-card>
    </AppLayout>
</template>
