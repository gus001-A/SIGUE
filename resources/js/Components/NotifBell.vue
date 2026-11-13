<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { BellOutlined, CheckOutlined, SoundOutlined, SoundFilled } from '@ant-design/icons-vue';
import { message } from '@/lib/notify';
import { reproducirDing, sonidoActivo, setSonido } from '@/lib/sound';

const page = usePage();
const open = ref(false);
const items = ref([]);
const unread = ref(page.props.counters?.notifications ?? 0);
const pulse = ref(false);
const withSound = ref(sonidoActivo());
let timer = null;
let first = true;
let lastSeen = 0;

watch(() => page.props.counters?.notifications, (v) => { if (typeof v === 'number') unread.value = v; });

function toggleSound() {
    withSound.value = !withSound.value;
    setSonido(withSound.value);
    if (withSound.value) reproducirDing({ force: true });
}

async function load() {
    try {
        const { data } = await axios.get(route('notificaciones.feed'));
        const list = data.items ?? [];
        const maxId = list.reduce((m, n) => Math.max(m, n.id), 0);
        if (!first && maxId > lastSeen && !open.value) {
            const n = list.find((x) => x.id === maxId);
            pulse.value = true;
            setTimeout(() => (pulse.value = false), 1600);
            message.info(n?.title || 'Tienes una notificación nueva');
            reproducirDing();
        }
        items.value = list;
        unread.value = data.unread ?? 0;
        lastSeen = Math.max(lastSeen, maxId);
        first = false;
    } catch (e) { /* silencioso */ }
}

async function openItem(n) {
    if (!n.read) {
        try { await axios.post(route('notificaciones.leer', n.id)); } catch (e) { /* noop */ }
        n.read = true;
        unread.value = Math.max(0, unread.value - 1);
    }
    open.value = false;
    if (n.url) router.visit(n.url);
}

async function readAll() {
    try { await axios.post(route('notificaciones.leer-todas')); } catch (e) { /* noop */ }
    items.value.forEach((n) => (n.read = true));
    unread.value = 0;
}

watch(open, (v) => v && load());
onMounted(() => {
    load();
    timer = setInterval(load, 60000);
});
onUnmounted(() => clearInterval(timer));
</script>

<template>
    <a-dropdown v-model:open="open" :trigger="['click']" placement="bottomRight">
        <button class="bell" :class="{ 'is-pulse': pulse }" aria-label="Notificaciones">
            <a-badge :count="unread" :overflow-count="99" size="small">
                <BellOutlined style="font-size: 18px" />
            </a-badge>
        </button>
        <template #overlay>
            <div class="bell-panel" @click.stop>
                <div class="bell-head">
                    <b>Notificaciones</b>
                    <span class="bell-head__tools">
                        <a-tooltip :title="withSound ? 'Silenciar' : 'Activar sonido'">
                            <button class="bell-tool" @click="toggleSound">
                                <SoundFilled v-if="withSound" /><SoundOutlined v-else />
                            </button>
                        </a-tooltip>
                        <a-tooltip title="Marcar todas como leídas">
                            <button class="bell-tool" :disabled="!unread" @click="readAll"><CheckOutlined /></button>
                        </a-tooltip>
                    </span>
                </div>
                <div class="bell-body">
                    <a-empty v-if="!items.length" :image="null" description="Sin notificaciones" />
                    <button v-for="n in items" :key="n.id" class="bell-item" :class="{ 'is-unread': !n.read }" @click="openItem(n)">
                        <span class="bell-dot" :class="`is-${n.color}`"></span>
                        <span class="bell-txt">
                            <span class="bell-title">{{ n.title }}</span>
                            <span class="bell-msg">{{ n.message }}</span>
                            <span class="bell-date">{{ n.type_label }} · {{ n.date }}</span>
                        </span>
                    </button>
                </div>
            </div>
        </template>
    </a-dropdown>
</template>

<style scoped>
.bell {
    width: 38px; height: 38px; border-radius: 11px; border: 1px solid transparent; background: transparent;
    color: #475569; cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: background .15s ease, color .15s ease;
}
.bell:hover { background: #eaf4fc; color: #1d7fc9; }
.bell.is-pulse { animation: bell-ring .9s ease 2; }
@keyframes bell-ring { 0%, 100% { transform: rotate(0); } 25% { transform: rotate(14deg); } 75% { transform: rotate(-14deg); } }

.bell-panel {
    width: 370px; max-width: 92vw; background: #fff; border-radius: 14px; overflow: hidden;
    box-shadow: 0 18px 44px -14px rgba(15, 23, 42, .25); border: 1px solid #eef1f6;
}
.bell-head { display: flex; align-items: center; justify-content: space-between; padding: 11px 14px; background: #fafbfd; border-bottom: 1px solid #eef1f6; }
.bell-head__tools { display: flex; gap: 4px; }
.bell-tool { width: 28px; height: 28px; border-radius: 8px; border: 0; background: transparent; cursor: pointer; color: #64748b; }
.bell-tool:hover:not(:disabled) { background: #eaf4fc; color: #1d7fc9; }
.bell-tool:disabled { opacity: .4; cursor: default; }
.bell-body { max-height: 360px; overflow-y: auto; }
.bell-item {
    display: flex; gap: 10px; width: 100%; text-align: left; padding: 11px 14px; border: 0; background: #fff;
    border-bottom: 1px solid #f4f6fa; cursor: pointer;
}
.bell-item:hover { background: #f6f9fd; }
.bell-item.is-unread { background: #f3f9fe; }
.bell-dot { width: 8px; height: 8px; border-radius: 50%; margin-top: 6px; flex: none; background: #94a3b8; }
.bell-dot.is-red { background: #dc2626; } .bell-dot.is-amber { background: #f59e0b; } .bell-dot.is-green { background: #16a34a; }
.bell-dot.is-indigo { background: #1d7fc9; } .bell-dot.is-violet { background: #7c3aed; } .bell-dot.is-pink { background: #db2777; }
.bell-txt { display: flex; flex-direction: column; min-width: 0; }
.bell-title { font-size: 12.5px; font-weight: 650; color: #0f172a; }
.bell-msg { font-size: 12px; color: #475569; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.bell-date { font-size: 11px; color: #94a3b8; margin-top: 2px; }
.bell-foot { display: block; text-align: center; padding: 10px; font-size: 12.5px; font-weight: 600; color: #1d7fc9; border-top: 1px solid #eef1f6; }
</style>
