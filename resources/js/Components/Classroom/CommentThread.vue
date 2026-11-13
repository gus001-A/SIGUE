<script setup>
/**
 * Hilo de comentarios del Classroom (como Google Classroom).
 *  - Comentarios de la clase: los ven todos.  - Privados: solo docente ↔ alumno.
 * <CommentThread :items="c" type="assignment" :id="5" private :student-id="12" can-write />
 */
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { SendOutlined, DeleteOutlined, LockOutlined, TeamOutlined, MessageOutlined } from '@ant-design/icons-vue';
import { message } from '@/lib/notify';

const props = defineProps({
    items: { type: Array, default: () => [] },
    type: { type: String, required: true },           // assignment | post
    id: { type: Number, required: true },
    private: { type: Boolean, default: false },
    studentId: { type: Number, default: null },        // docente escribiendo un privado al alumno
    canWrite: { type: Boolean, default: true },
    compact: { type: Boolean, default: false },        // muestra solo los 2 últimos hasta expandir
    title: { type: String, default: '' },
    placeholder: { type: String, default: '' },
});
const emit = defineEmits(['change']);

const list = ref([...props.items]);
watch(() => props.items, (v) => (list.value = [...v]));
const expanded = ref(!props.compact);
const shown = computed(() => (expanded.value || list.value.length <= 2 ? list.value : list.value.slice(-2)));

const body = ref('');
const sending = ref(false);
async function send() {
    const text = body.value.trim();
    if (!text) return;
    sending.value = true;
    try {
        const { data } = await axios.post(route('classroom.comentarios.store'), { type: props.type, id: props.id, body: text, private: props.private, student_id: props.studentId },
            { headers: { Accept: 'application/json' } });
        list.value.push(data);
        body.value = '';
        expanded.value = true;
        emit('change', list.value);
    } catch (e) {
        message.error(e.response?.data?.errors?.body?.[0] ?? e.response?.data?.message ?? 'No se pudo enviar el comentario.');
    } finally {
        sending.value = false;
    }
}
async function remove(c) {
    try {
        await axios.delete(route('classroom.comentarios.destroy', c.id), { headers: { Accept: 'application/json' } });
        list.value = list.value.filter((x) => x.id !== c.id);
        emit('change', list.value);
    } catch (e) {
        message.error('No se pudo eliminar el comentario.');
    }
}
const ROLE_COLOR = { Docente: '#7c4ddb', Alumno: '#1d7fc9', Tutor: '#e0457b', Escuela: '#14a39a' };
</script>

<template>
    <div class="ct" :class="{ 'is-private': private }">
        <div class="ct__head">
            <component :is="private ? LockOutlined : TeamOutlined" />
            <span>{{ title || (private ? 'Comentarios privados' : 'Comentarios de la clase') }}</span>
            <em v-if="list.length">{{ list.length }}</em>
        </div>
        <button v-if="!expanded && list.length > 2" type="button" class="ct__more" @click="expanded = true">
            <MessageOutlined /> Ver los {{ list.length }} comentarios
        </button>
        <div v-for="c in shown" :key="c.id" class="ct__c">
            <span class="ct__av" :style="{ background: ROLE_COLOR[c.role] ?? '#64748b' }"><img v-if="c.photo" :src="c.photo" alt="" /><template v-else>{{ c.initials }}</template></span>
            <div class="ct__b">
                <div class="ct__meta"><b>{{ c.author }}</b><span class="ct__role" :style="{ color: ROLE_COLOR[c.role] }">{{ c.role }}</span><small :title="c.at">{{ c.ago }}</small></div>
                <p>{{ c.body }}</p>
            </div>
            <a-tooltip v-if="c.can_delete" title="Eliminar"><button type="button" class="ct__del" @click="remove(c)"><DeleteOutlined /></button></a-tooltip>
        </div>
        <p v-if="!list.length && !canWrite" class="ct__empty">Sin comentarios.</p>
        <div v-if="canWrite" class="ct__new">
            <a-textarea v-model:value="body" :auto-size="{ minRows: 1, maxRows: 5 }" :maxlength="2000"
                :placeholder="placeholder || (private ? 'Escribe un comentario privado…' : 'Agrega un comentario de la clase…')" @press-enter.exact.prevent="send" />
            <a-button type="primary" shape="circle" :loading="sending" :disabled="!body.trim()" @click="send"><SendOutlined /></a-button>
        </div>
    </div>
</template>

<style scoped>
.ct { display: flex; flex-direction: column; gap: 8px; }
.ct__head { display: flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 700; color: #44566c; }
.ct__head .anticon { color: #1d7fc9; }
.ct.is-private .ct__head .anticon { color: #7c4ddb; }
.ct__head em { font-style: normal; font-size: 11px; font-weight: 800; color: #1d7fc9; background: #e6f0fd; padding: 0 7px; border-radius: 99px; }
.ct__more { align-self: flex-start; border: 0; background: none; color: #1d7fc9; font-family: inherit; font-size: 12.5px; font-weight: 600; cursor: pointer; padding: 0; display: inline-flex; gap: 5px; align-items: center; }
.ct__c { display: flex; gap: 10px; align-items: flex-start; }
.ct__av { width: 30px; height: 30px; border-radius: 50%; flex: none; display: grid; place-items: center; color: #fff; font-size: 11px; font-weight: 800; }
.ct__b { flex: 1; min-width: 0; }
.ct__meta { display: flex; align-items: baseline; gap: 6px; flex-wrap: wrap; }
.ct__meta b { font-size: 13px; color: #0a1f44; }
.ct__role { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
.ct__meta small { font-size: 11.5px; color: #94a3b8; }
.ct__b p { margin: 1px 0 0; font-size: 13px; color: #334155; white-space: pre-wrap; overflow-wrap: anywhere; }
.ct__del { border: 0; background: none; color: #cbd5e1; cursor: pointer; padding: 4px; border-radius: 6px; }
.ct__del:hover { color: #d9363e; background: #fdecec; }
.ct__empty { margin: 0; font-size: 12.5px; color: #94a3b8; }
.ct__new { display: flex; gap: 8px; align-items: flex-end; }
.ct__new :deep(textarea) { border-radius: 18px; padding: 7px 14px; resize: none; }
.ct__av { overflow: hidden; }
.ct__av img { width: 100%; height: 100%; object-fit: cover; }
</style>
