<script setup>
/** Indicador compacto con icono de color (tarjeta KPI). */
import { computed } from 'vue';

const COLORS = {
    indigo: '#1d7fc9', violet: '#14a39a', green: '#2e9b43', pink: '#6b4c8c',
    amber: '#f39200', slate: '#64748b', red: '#e3262b', yellow: '#d4a90b', purple: '#7c4ddb',
};
const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], default: '—' },
    hint: { type: String, default: null },
    icon: { type: [Object, Function], default: null },
    color: { type: String, default: 'indigo' },
    active: { type: Boolean, default: false },
});
const c = computed(() => COLORS[props.color] ?? props.color);
const display = computed(() => (typeof props.value === 'number' ? props.value.toLocaleString('es-MX') : props.value));
</script>

<template>
    <div class="sg-kpi" :class="{ on: active }" :style="{ '--k': c }">
        <span class="sg-kpi__ic"><component :is="icon" v-if="icon" /></span>
        <span class="sg-kpi__txt">
            <b>{{ display }}</b>
            <small>{{ label }}</small>
            <em v-if="hint">{{ hint }}</em>
        </span>
    </div>
</template>
