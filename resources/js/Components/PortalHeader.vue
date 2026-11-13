<script setup>
/**
 * Encabezado del portal (alumnos y padres): solo el título de la sección.
 * Para tutores con varios hijos agrega un selector compacto; si consulta a un solo hijo muestra su nombre.
 */
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({ student: Object, children: Array, isGuardian: Boolean, title: String, subtitle: String, icon: { type: [Object, Function], default: null } });
const initials = (n) => (n || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
const others = computed(() => props.children.length > 1);
function pick(id) {
    if (id !== props.student.id) router.get(window.location.pathname, { alumno: id }, { preserveScroll: true });
}
</script>

<template>
    <header class="ph">
        <div class="ph__title">
            <span v-if="icon" class="ph__ic"><component :is="icon" /></span>
            <div>
                <h1>{{ title }}</h1>
                <small v-if="subtitle">{{ subtitle }}</small>
            </div>
        </div>
        <div v-if="others" class="ph__kids">
            <button v-for="c in children" :key="c.id" type="button" class="kid" :class="{ on: c.id === student.id }" @click="pick(c.id)">
                <span class="kid__av"><img v-if="c.photo_url" :src="c.photo_url" alt="" /><template v-else>{{ initials(c.name) }}</template></span>
                {{ c.first_name || c.name.split(' ')[0] }}
            </button>
        </div>
        <span v-else-if="isGuardian" class="ph__who">Consultando a <b>{{ student.first_name || student.full_name }}</b></span>
    </header>
</template>

<style scoped>
.ph { flex: none; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
.ph__title { display: flex; align-items: center; gap: 10px; min-width: 0; }
.ph__ic { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; color: #fff; background: #1d7fc9; font-size: 17px; flex: none; }
.ph__title h1 { margin: 0; font-size: 22px; font-weight: 800; color: #0a1f44; line-height: 1.2; }
.ph__title small { font-size: 12.5px; color: #5d7085; }
.ph__kids { display: flex; gap: 6px; flex-wrap: wrap; }
.kid { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px 4px 4px; border-radius: 99px; border: 1px solid #e1e8f0; background: #fff; cursor: pointer;
    font-family: inherit; font-size: 13px; font-weight: 600; color: #44566c; }
.kid.on { border-color: #1d7fc9; color: #1d7fc9; background: #f2f7fe; }
.kid__av { width: 26px; height: 26px; border-radius: 50%; overflow: hidden; display: grid; place-items: center; background: #e6f0fd; color: #1d7fc9; font-size: 10.5px; font-weight: 800; }
.kid__av img { width: 100%; height: 100%; object-fit: cover; }
.ph__who { font-size: 13px; color: #5d7085; }
.ph__who b { color: #0a1f44; }
</style>
