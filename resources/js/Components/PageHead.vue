<script setup>
/** Encabezado de página: barra de acento, icono, título, subtítulo y acciones. */
import { ArrowLeftOutlined } from '@ant-design/icons-vue';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    icon: { type: [Object, Function], default: null },
    back: { type: [String, Boolean], default: false },
});
defineEmits(['back']);
</script>

<template>
    <div class="sg-pagehead">
        <div class="sg-pagehead__left">
            <a-tooltip v-if="back" title="Regresar">
                <button type="button" class="sg-pagehead__back" @click="$emit('back')"><ArrowLeftOutlined /></button>
            </a-tooltip>
            <div v-if="icon" class="sg-pagehead__icon"><component :is="icon" /></div>
            <div class="sg-pagehead__txt">
                <h1 class="sg-pagehead__title">{{ title }}</h1>
                <p v-if="subtitle" class="sg-pagehead__sub">{{ subtitle }}</p>
            </div>
        </div>
        <div v-if="$slots.actions" class="sg-pagehead__actions">
            <slot name="actions" />
        </div>
    </div>
</template>
