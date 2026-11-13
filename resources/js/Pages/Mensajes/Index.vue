<script setup>
/**
 * Mensajería en tiempo real (estilo chat, como en RIC):
 * - Bandeja a la izquierda que se refresca cada 15 s (y al volver a la pestaña).
 * - Chat a la derecha con sondeo incremental: solo trae los mensajes nuevos (?desde=último id),
 *   cada 2 s si hay actividad reciente y se espacia hasta 8 s si está en calma.
 * - "Está escribiendo…", palomitas de leído y envío inmediato (el mensaje aparece al instante).
 */
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import { useForm } from '@inertiajs/vue3';
import {
    MessageOutlined, EditOutlined, SendOutlined, SearchOutlined, CalendarOutlined, UserOutlined, CheckOutlined,
    ArrowDownOutlined, ArrowLeftOutlined, NotificationOutlined, TeamOutlined, LoadingOutlined, ExclamationCircleOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHead from '@/Components/PageHead.vue';
import SgModal from '@/Components/SgModal.vue';
import { message } from '@/lib/notify';

const props = defineProps({ me: Number, open: { type: Number, default: null }, recipients: Array, students: Array, kinds: Array });

const KIND = {
    mensaje: { c: '#1d7fc9', icon: MessageOutlined },
    reunion: { c: '#7c4ddb', icon: CalendarOutlined },
    comunicado: { c: '#f39200', icon: NotificationOutlined },
};
const kindOf = (k) => KIND[k] ?? KIND.mensaje;

// ================= BANDEJA =================
const conversations = ref([]);
const loadingList = ref(true);
const search = ref('');
const onlyUnread = ref(false);
const shown = computed(() => conversations.value.filter((c) => !onlyUnread.value || c.unread));
const totalUnread = computed(() => conversations.value.reduce((t, c) => t + c.unread, 0));

async function loadList() {
    try {
        const { data } = await axios.get(route('mensajes.conversaciones'), { params: search.value ? { buscar: search.value } : {} });
        conversations.value = data.conversations;
    } catch (e) { /* se reintenta en el siguiente ciclo */ } finally {
        loadingList.value = false;
    }
}
let tSearch = null;
const searchLater = () => { clearTimeout(tSearch); tSearch = setTimeout(loadList, 300); };

// ================= CHAT =================
const current = ref(null);   // { thread, items }
const box = ref(null);
const text = ref('');
const seenUpTo = ref(0);
const typing = ref(null);
const unseenBelow = ref(0);
const opening = ref(false);
let lastActivity = Date.now();
let lastTypingPing = 0;
let openToken = 0;

const lastId = () => {
    const ok = (current.value?.items ?? []).filter((m) => typeof m.id === 'number');
    return ok.length ? ok[ok.length - 1].id : 0;
};
const atBottom = () => { const el = box.value; return !el || el.scrollHeight - el.scrollTop - el.clientHeight < 80; };
const toBottom = async (smooth = false) => {
    await nextTick();
    if (box.value) box.value.scrollTo({ top: box.value.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
    unseenBelow.value = 0;
};

async function openThread(id) {
    const token = ++openToken;
    opening.value = true;
    try {
        const { data } = await axios.get(route('mensajes.feed', id));
        if (token !== openToken) return;
        current.value = { thread: data.thread, items: data.items };
        seenUpTo.value = data.seen_up_to ?? 0;
        typing.value = data.typing;
        text.value = '';
        const c = conversations.value.find((x) => x.id === id);
        if (c) c.unread = 0;
        history.replaceState(history.state, '', route('mensajes.index', { conversacion: id }));
        lastActivity = Date.now();
        toBottom();
        schedule(0);
    } catch (e) {
        message.error('No se pudo abrir la conversación.');
    } finally {
        opening.value = false;
    }
}
function closeThread() {
    current.value = null;
    history.replaceState(history.state, '', route('mensajes.index'));
}

/** Sondeo del chat abierto: solo pide lo que llegó después del último mensaje. */
let polling = false;
async function poll() {
    if (!current.value || polling) return;
    polling = true;
    const id = current.value.thread.id;
    try {
        const { data } = await axios.get(route('mensajes.feed', id), { params: { desde: lastId(), leer: document.hidden ? 0 : 1 } });
        if (!current.value || current.value.thread.id !== id) return;
        seenUpTo.value = data.seen_up_to ?? seenUpTo.value;
        typing.value = data.typing;
        const known = new Set(current.value.items.map((m) => m.id));
        const fresh = data.items.filter((m) => !known.has(m.id));
        if (fresh.length) {
            const bottom = atBottom();
            let list = current.value.items;
            // Si llega mi propio mensaje antes de terminar el envío, reemplaza su versión "enviando".
            for (const n of fresh.filter((m) => m.mine)) {
                const pend = list.find((m) => m.state === 'sending' && m.body.trim() === n.body.trim());
                if (pend) list = list.filter((m) => m !== pend);
            }
            current.value.items = [...list, ...fresh];
            lastActivity = Date.now();
            const theirs = fresh.filter((m) => !m.mine).length;
            if (bottom) toBottom(true); else unseenBelow.value += theirs;
            if (theirs) loadList();
        }
    } catch (e) { /* reintento */ } finally {
        polling = false;
    }
}
let tChat = null;
function schedule(wait = null) {
    clearTimeout(tChat);
    if (!current.value) return;
    const idle = Date.now() - lastActivity;
    const ms = wait ?? (document.hidden ? 15000 : typing.value || idle < 30000 ? 2000 : idle < 120000 ? 4000 : 8000);
    tChat = setTimeout(async () => { await poll(); schedule(); }, ms);
}

async function send() {
    const body = text.value.trim();
    if (!body || !current.value) return;
    const temp = { id: `t${Date.now()}`, body, mine: true, at: new Date().toISOString(), state: 'sending' };
    current.value.items.push(temp);
    text.value = '';
    toBottom(true);
    lastActivity = Date.now();
    try {
        const { data } = await axios.post(route('mensajes.reply', current.value.thread.id), { body }, { headers: { Accept: 'application/json' } });
        const i = current.value.items.indexOf(temp);
        if (i >= 0) current.value.items.splice(i, 1, data.item);
        loadList();
    } catch (e) {
        temp.state = 'error';
        message.error(e.response?.data?.message || 'No se pudo enviar el mensaje.');
    }
    schedule(1500);
}
function retry(m) {
    current.value.items = current.value.items.filter((x) => x !== m);
    text.value = m.body;
    send();
}
function onKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); return; }
    const now = Date.now();
    if (current.value && now - lastTypingPing > 3000) {
        lastTypingPing = now;
        axios.post(route('mensajes.escribiendo', current.value.thread.id)).catch(() => {});
    }
}

// ================= Fechas y agrupación =================
const hour = (iso) => (iso ? new Date(iso).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }) : '');
const when = (iso) => {
    if (!iso) return '';
    const d = new Date(iso), today = new Date();
    if (d.toDateString() === today.toDateString()) return hour(iso);
    const y = new Date(); y.setDate(y.getDate() - 1);
    if (d.toDateString() === y.toDateString()) return 'Ayer';
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
};
const dayLabel = (iso) => {
    const d = new Date(iso), today = new Date();
    if (d.toDateString() === today.toDateString()) return 'Hoy';
    const y = new Date(); y.setDate(y.getDate() - 1);
    if (d.toDateString() === y.toDateString()) return 'Ayer';
    return d.toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' });
};
const rows = computed(() => {
    const out = [];
    let lastDay = null;
    (current.value?.items ?? []).forEach((m, i, arr) => {
        const day = new Date(m.at).toDateString();
        if (day !== lastDay) { out.push({ sep: true, key: 'd' + day, label: dayLabel(m.at) }); lastDay = day; }
        const prev = arr[i - 1];
        const grouped = prev && prev.mine === m.mine && new Date(m.at) - new Date(prev.at) < 5 * 60000 && new Date(prev.at).toDateString() === day;
        out.push({ ...m, key: m.id, grouped });
    });
    return out;
});

// ================= Nuevo mensaje =================
const newOpen = ref(false);
const form = useForm({ recipient_id: null, student_id: null, kind: 'mensaje', subject: '', body: '', meeting_at: null });
const err = (k) => ({ 'validate-status': form.errors[k] ? 'error' : undefined, help: form.errors[k] });
const filterOption = (input, opt) => opt.label.toLowerCase().includes(input.toLowerCase());
async function create() {
    form.processing = true;
    form.clearErrors();
    try {
        const { data } = await axios.post(route('mensajes.store'), form.data(), { headers: { Accept: 'application/json' } });
        newOpen.value = false;
        form.reset();
        await loadList();
        openThread(data.id);
        message.success('Mensaje enviado.');
    } catch (e) {
        const errs = e.response?.data?.errors ?? {};
        Object.entries(errs).forEach(([k, v]) => form.setError(k, v[0]));
        if (!Object.keys(errs).length) message.error('No se pudo enviar el mensaje.');
    } finally {
        form.processing = false;
    }
}

// ================= Ciclo de vida =================
let tList = null;
const onBack = () => { if (!document.hidden) { loadList(); schedule(0); } };
onMounted(async () => {
    await loadList();
    if (props.open) openThread(props.open);
    tList = setInterval(() => { if (!document.hidden) loadList(); }, 15000);
    document.addEventListener('visibilitychange', onBack);
    window.addEventListener('focus', onBack);
});
onBeforeUnmount(() => {
    clearInterval(tList);
    clearTimeout(tChat);
    document.removeEventListener('visibilitychange', onBack);
    window.removeEventListener('focus', onBack);
});
</script>

<template>
    <AppLayout title="Mensajes">
        <PageHead title="Mensajes" subtitle="Comunicación escuela–familia en tiempo real · solicitudes de reunión y comunicados" :icon="MessageOutlined">
            <template #actions>
                <a-button type="primary" size="large" @click="newOpen = true"><template #icon><EditOutlined /></template>Nuevo mensaje</a-button>
            </template>
        </PageHead>

        <div class="chat sg-fill" :class="{ 'has-thread': current }">
            <!-- ============ BANDEJA ============ -->
            <aside class="inbox">
                <div class="inbox__head">
                    <a-input v-model:value="search" allow-clear placeholder="Buscar persona o asunto" @input="searchLater" @change="searchLater">
                        <template #prefix><SearchOutlined /></template>
                    </a-input>
                    <div class="inbox__tabs">
                        <button type="button" :class="{ on: !onlyUnread }" @click="onlyUnread = false">Todas</button>
                        <button type="button" :class="{ on: onlyUnread }" @click="onlyUnread = true">Sin leer <span v-if="totalUnread" class="pill">{{ totalUnread }}</span></button>
                        <span class="live"><i /> En vivo</span>
                    </div>
                </div>
                <div class="inbox__list">
                    <div v-if="loadingList" class="inbox__empty"><LoadingOutlined /> Cargando…</div>
                    <div v-else-if="!shown.length" class="inbox__empty"><MessageOutlined /> {{ onlyUnread ? 'No tienes mensajes sin leer' : 'Aún no hay conversaciones' }}</div>
                    <button v-for="c in shown" :key="c.id" type="button" class="conv" :class="{ on: current?.thread.id === c.id, unread: c.unread }"
                        :style="{ '--k': kindOf(c.kind).c }" @click="openThread(c.id)">
                        <span class="conv__av">{{ c.other_initials }}<i class="conv__kind"><component :is="kindOf(c.kind).icon" /></i></span>
                        <span class="conv__body">
                            <span class="conv__top"><b>{{ c.other }}</b><small>{{ when(c.last_at) }}</small></span>
                            <span class="conv__subj">{{ c.subject }}</span>
                            <span class="conv__last"><span v-if="c.last_mine" class="me">Tú:</span> {{ c.last }}</span>
                        </span>
                        <span v-if="c.unread" class="conv__badge">{{ c.unread }}</span>
                    </button>
                </div>
            </aside>

            <!-- ============ CHAT ============ -->
            <section class="room">
                <div v-if="!current" class="room__empty">
                    <span class="room__empty-ic"><MessageOutlined /></span>
                    <b>Selecciona una conversación</b>
                    <span>Los mensajes llegan en tiempo real, sin recargar la página.</span>
                    <a-button type="primary" @click="newOpen = true"><EditOutlined /> Escribir un mensaje</a-button>
                </div>

                <template v-else>
                    <header class="room__head" :style="{ '--k': kindOf(current.thread.kind).c }">
                        <button type="button" class="room__back" @click="closeThread"><ArrowLeftOutlined /></button>
                        <span class="conv__av">{{ current.thread.other_initials }}</span>
                        <div class="room__who">
                            <b>{{ current.thread.other }}</b>
                            <small v-if="typing" class="typing">escribiendo<i>.</i><i>.</i><i>.</i></small>
                            <small v-else>{{ current.thread.subject }}</small>
                        </div>
                        <span class="room__kind"><component :is="kindOf(current.thread.kind).icon" /> {{ current.thread.kind_label }}</span>
                        <span v-if="current.thread.student" class="room__chip"><UserOutlined /> {{ current.thread.student }}</span>
                        <span v-if="current.thread.meeting_at" class="room__chip room__chip--meet"><CalendarOutlined /> {{ current.thread.meeting_at }}</span>
                    </header>

                    <div ref="box" class="room__msgs" @scroll="atBottom() && (unseenBelow = 0)">
                        <template v-for="r in rows" :key="r.key">
                            <div v-if="r.sep" class="day"><span>{{ r.label }}</span></div>
                            <div v-else class="msg" :class="{ mine: r.mine, grouped: r.grouped, err: r.state === 'error' }">
                                <div class="msg__bubble">
                                    <span class="msg__text">{{ r.body }}</span>
                                    <span class="msg__meta">
                                        {{ hour(r.at) }}
                                        <template v-if="r.mine">
                                            <LoadingOutlined v-if="r.state === 'sending'" />
                                            <a v-else-if="r.state === 'error'" class="msg__retry" @click="retry(r)"><ExclamationCircleOutlined /> Reintentar</a>
                                            <span v-else class="ticks" :class="{ seen: r.id <= seenUpTo }"><CheckOutlined /><CheckOutlined /></span>
                                        </template>
                                    </span>
                                </div>
                            </div>
                        </template>
                        <div v-if="typing" class="msg"><div class="msg__bubble msg__bubble--typing"><i /><i /><i /></div></div>
                    </div>
                    <button v-if="unseenBelow" type="button" class="room__new" @click="toBottom(true)"><ArrowDownOutlined /> {{ unseenBelow }} nuevo{{ unseenBelow === 1 ? '' : 's' }}</button>

                    <footer class="room__compose">
                        <a-textarea v-model:value="text" :auto-size="{ minRows: 1, maxRows: 5 }" placeholder="Escribe un mensaje…  (Enter envía · Shift+Enter salto de línea)"
                            :maxlength="5000" @keydown="onKey" />
                        <button type="button" class="send" :disabled="!text.trim()" @click="send"><SendOutlined /></button>
                    </footer>
                </template>
            </section>
        </div>

        <!-- Nuevo mensaje -->
        <SgModal v-model:open="newOpen" title="Nuevo mensaje" subtitle="Comunicación interna escuela–familia" :icon="EditOutlined" color="linear-gradient(135deg, #2e9b43, #14a39a)"
            width="min(820px, 96vw)" ok-text="Enviar" :ok-icon="SendOutlined" :loading="form.processing" @ok="create">
            <a-form layout="vertical" class="sg-grid-2">
                <a-form-item label="Para" required v-bind="err('recipient_id')">
                    <a-select v-model:value="form.recipient_id" show-search :options="recipients" :filter-option="filterOption" placeholder="Selecciona destinatario">
                        <template #suffixIcon><TeamOutlined /></template>
                    </a-select>
                </a-form-item>
                <a-form-item label="Tipo" v-bind="err('kind')"><a-select v-model:value="form.kind" :options="kinds" /></a-form-item>
                <a-form-item label="Asunto" required v-bind="err('subject')"><a-input v-model:value="form.subject" :maxlength="150" /></a-form-item>
                <a-form-item v-if="form.kind === 'reunion'" label="Fecha y hora propuesta" v-bind="err('meeting_at')">
                    <a-date-picker v-model:value="form.meeting_at" show-time format="DD/MM/YYYY HH:mm" value-format="YYYY-MM-DD HH:mm" style="width: 100%" />
                </a-form-item>
                <a-form-item v-else label="Sobre el alumno"><a-select v-model:value="form.student_id" allow-clear :options="students" :disabled="!students.length" :placeholder="students.length ? 'Opcional' : 'No aplica'" /></a-form-item>
                <a-form-item label="Mensaje" required class="full" v-bind="err('body')"><a-textarea v-model:value="form.body" :rows="5" /></a-form-item>
            </a-form>
        </SgModal>
    </AppLayout>
</template>

<style scoped>
.chat { display: grid !important; grid-template-columns: 340px minmax(0, 1fr); gap: 14px; min-height: 0; }

/* ---------- Bandeja ---------- */
.inbox { display: flex; flex-direction: column; min-height: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; }
.inbox__head { padding: 12px; border-bottom: 1px solid #eef2f7; display: flex; flex-direction: column; gap: 10px; }
.inbox__head :deep(.ant-input-affix-wrapper) { border-radius: 10px; }
.inbox__tabs { display: flex; align-items: center; gap: 6px; }
.inbox__tabs button { border: 1px solid #e2e8f0; background: #fff; border-radius: 999px; padding: 3px 12px; font-size: 12.5px; font-weight: 600; color: #475569; cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 6px; }
.inbox__tabs button.on { background: #1d7fc9; border-color: #1d7fc9; color: #fff; }
.pill { background: #e3262b; color: #fff; border-radius: 999px; padding: 0 6px; font-size: 10.5px; }
.live { margin-left: auto; display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 700; color: #2e9b43; }
.live i { width: 7px; height: 7px; border-radius: 50%; background: #2e9b43; box-shadow: 0 0 0 0 rgba(46, 155, 67, .6); animation: live 1.8s infinite; }
@keyframes live { 70% { box-shadow: 0 0 0 7px rgba(46, 155, 67, 0); } 100% { box-shadow: 0 0 0 0 rgba(46, 155, 67, 0); } }
.inbox__list { flex: 1; min-height: 0; overflow-y: auto; padding: 6px; }
.inbox__empty { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 30px 10px; color: #94a3b8; font-size: 13px; }
.conv { width: 100%; display: flex; align-items: center; gap: 10px; padding: 10px; border: 0; background: transparent; border-radius: 12px; cursor: pointer; text-align: left; font-family: inherit; transition: background .15s; }
.conv:hover { background: #f5f9fd; }
.conv.on { background: #eaf4fc; }
.conv__av { position: relative; width: 42px; height: 42px; flex: none; border-radius: 50%; display: grid; place-items: center; color: #fff; font-weight: 800; font-size: 14px; background: linear-gradient(135deg, var(--k, #1d7fc9), #14a39a); }
.conv__kind { position: absolute; right: -3px; bottom: -3px; width: 18px; height: 18px; border-radius: 50%; display: grid; place-items: center; font-size: 10px; font-style: normal; color: var(--k); background: #fff; box-shadow: 0 1px 3px rgba(0, 0, 0, .15); }
.conv__body { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.conv__top { display: flex; align-items: baseline; gap: 6px; }
.conv__top b { flex: 1; min-width: 0; font-size: 13.5px; color: #0b2f55; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.conv__top small { font-size: 11px; color: #94a3b8; flex: none; }
.conv__subj { font-size: 12px; color: #475569; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.conv__last { font-size: 12px; color: #94a3b8; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.conv__last .me { color: #64748b; font-weight: 600; }
.conv.unread .conv__top b, .conv.unread .conv__last { color: #0b2f55; font-weight: 700; }
.conv__badge { min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; display: grid; place-items: center; font-size: 11px; font-weight: 800; color: #fff; background: #2e9b43; flex: none; }

/* ---------- Chat ---------- */
.room { position: relative; display: flex; flex-direction: column; min-height: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; }
.room__empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; color: #64748b; text-align: center; padding: 20px; background: radial-gradient(circle at 50% 40%, #f0f7fd, #fff 70%); }
.room__empty-ic { width: 76px; height: 76px; border-radius: 50%; display: grid; place-items: center; font-size: 34px; color: #fff; background: linear-gradient(135deg, #1d7fc9, #14a39a); box-shadow: 0 14px 30px -14px #1d7fc9; margin-bottom: 6px; }
.room__empty b { color: #0b2f55; font-size: 16px; }
.room__head { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid #eef2f7; flex-wrap: wrap; }
.room__back { display: none; border: 0; background: #f1f5f9; border-radius: 10px; width: 34px; height: 34px; cursor: pointer; }
.room__who { flex: 1; min-width: 160px; display: flex; flex-direction: column; }
.room__who b { color: #0b2f55; font-size: 14.5px; }
.room__who small { color: #64748b; font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.typing { color: #2e9b43 !important; font-weight: 700; }
.typing i { font-style: normal; animation: blink 1.2s infinite; }
.typing i:nth-child(2) { animation-delay: .2s; } .typing i:nth-child(3) { animation-delay: .4s; }
@keyframes blink { 0%, 100% { opacity: .2; } 50% { opacity: 1; } }
.room__kind, .room__chip { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; color: var(--k); background: color-mix(in srgb, var(--k) 10%, #fff); }
.room__chip { --k: #14a39a; }
.room__chip--meet { --k: #7c4ddb; }

.room__msgs { flex: 1; min-height: 0; overflow-y: auto; padding: 14px 18px; display: flex; flex-direction: column; gap: 6px; background: #f6f9fc; }
.day { display: flex; justify-content: center; margin: 8px 0; }
.day span { font-size: 11.5px; font-weight: 700; color: #64748b; background: #e8eef5; padding: 3px 12px; border-radius: 999px; text-transform: capitalize; }
.msg { display: flex; }
.msg.mine { justify-content: flex-end; }
.msg.grouped { margin-top: -3px; }
.msg__bubble { max-width: min(560px, 75%); padding: 8px 12px 6px; border-radius: 16px 16px 16px 4px; background: #fff; border: 1px solid #e2e8f0; box-shadow: 0 1px 2px rgba(15, 23, 42, .05); display: flex; flex-direction: column; }
.msg.mine .msg__bubble { border-radius: 16px 16px 4px 16px; background: linear-gradient(135deg, #1d7fc9, #1667a8); border-color: transparent; color: #fff; }
.msg.err .msg__bubble { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
.msg__text { white-space: pre-wrap; word-break: break-word; font-size: 13.5px; line-height: 1.45; }
.msg__meta { align-self: flex-end; display: inline-flex; align-items: center; gap: 4px; font-size: 10.5px; opacity: .75; margin-top: 2px; }
.ticks { display: inline-flex; }
.ticks .anticon + .anticon { margin-left: -6px; }
.ticks.seen { color: #a7f3d0; opacity: 1; }
.msg__retry { color: #b91c1c; font-weight: 700; cursor: pointer; }
.msg__bubble--typing { flex-direction: row; gap: 4px; padding: 12px 14px; }
.msg__bubble--typing i { width: 7px; height: 7px; border-radius: 50%; background: #94a3b8; animation: blink 1.2s infinite; }
.msg__bubble--typing i:nth-child(2) { animation-delay: .2s; } .msg__bubble--typing i:nth-child(3) { animation-delay: .4s; }
.room__new { position: absolute; left: 50%; bottom: 76px; transform: translateX(-50%); border: 0; border-radius: 999px; padding: 6px 14px; font-weight: 700; font-size: 12.5px; color: #fff; background: #1d7fc9; box-shadow: 0 8px 18px -8px #1d7fc9; cursor: pointer; display: inline-flex; gap: 6px; align-items: center; }
.room__compose { display: flex; align-items: flex-end; gap: 10px; padding: 10px 14px; border-top: 1px solid #eef2f7; background: #fff; }
.room__compose :deep(textarea) { border-radius: 14px; padding: 9px 14px; resize: none; }
.send { width: 42px; height: 42px; flex: none; border: 0; border-radius: 50%; display: grid; place-items: center; font-size: 17px; color: #fff; cursor: pointer; background: linear-gradient(135deg, #2e9b43, #14a39a); box-shadow: 0 8px 16px -8px #2e9b43; transition: transform .15s, opacity .15s; }
.send:hover:not(:disabled) { transform: scale(1.06); }
.send:disabled { opacity: .4; cursor: default; }

@media (max-width: 900px) {
    .chat { grid-template-columns: 1fr; }
    .chat.has-thread .inbox { display: none; }
    .chat:not(.has-thread) .room { display: none; }
    .room__back { display: grid; place-items: center; }
    .room { min-height: 70vh; }
}
</style>
