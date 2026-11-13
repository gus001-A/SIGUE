<script setup>
import { computed } from 'vue';

/** Gráfica de barras ligera (sin dependencias). */
const props = defineProps({
    data: { type: Array, default: () => [] }, // [{ label, value }]
    color: { type: String, default: '#1d7fc9' },
    max: { type: Number, default: null },
    format: { type: Function, default: (v) => v },
    height: { type: Number, default: 150 },
});
const top = computed(() => props.max ?? Math.max(1, ...props.data.map((d) => Number(d.value) || 0)));
</script>

<template>
    <div class="mb" :style="{ height: height + 'px' }" role="img" :aria-label="data.map((d) => `${d.label}: ${format(d.value)}`).join(', ')">
        <div v-for="d in data" :key="d.label" class="mb__col">
            <a-tooltip :title="`${d.label}: ${d.value === null ? 'sin registro' : format(d.value)}`">
                <div class="mb__track">
                    <div class="mb__bar" :style="{ height: ((Number(d.value) || 0) / top) * 100 + '%', background: color, opacity: d.value === null ? 0.15 : 1 }"></div>
                </div>
            </a-tooltip>
            <span class="mb__label">{{ d.label }}</span>
        </div>
    </div>
</template>

<style scoped>
.mb { display: flex; align-items: stretch; gap: 8px; }
.mb__col { flex: 1; display: flex; flex-direction: column; align-items: center; min-width: 0; }
.mb__track { flex: 1; width: 100%; max-width: 38px; display: flex; align-items: flex-end; background: #f4f7fb; border-radius: 8px; overflow: hidden; height: 100%; }
.mb__bar { width: 100%; border-radius: 8px 8px 0 0; transition: height .5s cubic-bezier(.16, 1, .3, 1); min-height: 2px; }
.mb__label { font-size: 10.5px; color: #94a3b8; margin-top: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; text-transform: capitalize; }
</style>
