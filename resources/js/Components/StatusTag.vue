<script setup>
import { statusColor, statusLabel } from '@/lib/format';

const props = defineProps({
    status: { type: String, default: '' },
    label: { type: String, default: null },
    priority: { type: Boolean, default: false },
});
const key = () => (props.priority && props.status === 'baja' ? 'baja_p' : props.status);
</script>

<template>
    <a-tag :color="statusColor(key())" :bordered="false">{{ label ?? statusLabel(key()) }}</a-tag>
</template>
