<script setup>
import { Link } from '@inertiajs/vue3';
import { EyeOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons-vue';

defineProps({
    viewHref: { type: String, default: null },
    editHref: { type: String, default: null },
    editable: { type: Boolean, default: false },   // usa el evento @edit (modal) en vez de un enlace
    deletable: { type: Boolean, default: true },
    deleteDisabled: { type: Boolean, default: false },
    viewLabel: { type: String, default: 'Ver' },
    editLabel: { type: String, default: 'Editar' },
    deleteLabel: { type: String, default: 'Eliminar' },
});

defineEmits(['edit', 'delete']);
</script>

<template>
    <div class="row-actions" @click.stop>
        <a-tooltip v-if="viewHref" :title="viewLabel">
            <Link :href="viewHref" class="row-actions__btn is-view">
                <EyeOutlined />
            </Link>
        </a-tooltip>

        <a-tooltip v-if="editHref" :title="editLabel">
            <Link :href="editHref" class="row-actions__btn is-edit">
                <EditOutlined />
            </Link>
        </a-tooltip>
        <a-tooltip v-else-if="editable" :title="editLabel">
            <button type="button" class="row-actions__btn is-edit" @click="$emit('edit')">
                <EditOutlined />
            </button>
        </a-tooltip>

        <span v-if="$slots.extra" class="row-actions__extra"><slot name="extra" /></span>

        <a-tooltip v-if="deletable" :title="deleteDisabled ? 'No se puede eliminar' : deleteLabel">
            <button
                type="button"
                class="row-actions__btn is-delete"
                :class="{ 'is-disabled': deleteDisabled }"
                :disabled="deleteDisabled"
                @click="!deleteDisabled && $emit('delete')"
            >
                <DeleteOutlined />
            </button>
        </a-tooltip>
    </div>
</template>

