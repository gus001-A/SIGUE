<script setup>
/** App del alumno · Horario: chips por día (Lun–Vie) y las clases del día con su hora, docente y aula. */
import { computed, ref } from 'vue';
import { UserOutlined, EnvironmentOutlined, CoffeeOutlined, CalendarOutlined } from '@ant-design/icons-vue';
import MobileLayout from '@/Layouts/MobileLayout.vue';
import { ripple as vRipple, CLASS_THEMES } from '@/lib/ripple';

const props = defineProps({
    student: Object, children: Array, isGuardian: Boolean, schedule: { type: Array, default: () => [] },
    days: { type: Object, default: () => ({}) }, break: { type: Object, default: null }, teachers: Array,
});
const SHORT = { 1: 'Lun', 2: 'Mar', 3: 'Mié', 4: 'Jue', 5: 'Vie', 6: 'Sáb', 7: 'Dom' };
const dayList = computed(() => {
    const used = new Set(props.schedule.map((s) => Number(s.day)));
    return [1, 2, 3, 4, 5, 6, 7].filter((d) => d <= 5 || used.has(d));
});
const todayIso = ((new Date().getDay() + 6) % 7) + 1;
const day = ref(dayList.value.includes(todayIso) ? todayIso : dayList.value[0]);
const subjects = computed(() => [...new Set(props.schedule.map((s) => s.subject))].sort());
const colorOf = (s) => CLASS_THEMES[subjects.value.indexOf(s) % CLASS_THEMES.length];
const nowHM = new Date().toTimeString().slice(0, 5);

const rows = computed(() => {
    const list = props.schedule.filter((s) => Number(s.day) === day.value).map((s) => ({ ...s, kind: 'class' }));
    if (props.break?.start && list.length) list.push({ kind: 'break', starts_at: props.break.start.slice(0, 5), ends_at: props.break.end.slice(0, 5) });
    return list.sort((a, b) => a.starts_at.localeCompare(b.starts_at));
});
const isNow = (r) => day.value === todayIso && nowHM >= r.starts_at && nowHM < r.ends_at;
</script>

<template>
    <MobileLayout title="Horario">
        <div class="days">
            <button v-for="d in dayList" :key="d" v-ripple type="button" class="days__c" :class="{ on: day === d, today: d === todayIso }" @click="day = d">
                <small>{{ SHORT[d] }}</small><b>{{ schedule.filter((s) => Number(s.day) === d).length }}</b>
            </button>
        </div>
        <h2 class="hd">{{ days[day] ?? SHORT[day] }}<small v-if="day === todayIso"> · Hoy</small></h2>

        <div v-if="!rows.length" class="empty"><CalendarOutlined /><b>Sin clases este día</b></div>
        <div class="tl">
            <div v-for="(r, i) in rows" :key="i" class="tl__r">
                <span class="tl__t"><b>{{ r.starts_at }}</b><small>{{ r.ends_at }}</small></span>
                <div v-if="r.kind === 'break'" class="tl__brk"><CoffeeOutlined /> Receso</div>
                <div v-else class="tl__c" :class="{ now: isNow(r) }" :style="{ '--c': colorOf(r.subject) }">
                    <b>{{ r.subject }}</b>
                    <small v-if="r.teacher"><UserOutlined /> {{ r.teacher }}</small>
                    <small v-if="r.room"><EnvironmentOutlined /> {{ r.room }}</small>
                    <span v-if="isNow(r)" class="tl__now">Ahora</span>
                </div>
            </div>
        </div>
    </MobileLayout>
</template>

<style scoped>
.days { display: flex; gap: 8px; padding: 8px 16px; overflow-x: auto; scrollbar-width: none; }
.days__c { flex: 1; min-width: 54px; height: 64px; border-radius: 16px; border: 1px solid var(--m-outline-var); background: var(--m-surface); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; font-family: inherit; cursor: pointer; color: var(--m-on-var); }
.days__c small { font-size: 12px; font-weight: 500; }
.days__c b { font-size: 18px; font-weight: 500; color: var(--m-on); }
.days__c.today { border-color: var(--m-primary); }
.days__c.on { background: var(--m-primary); border-color: var(--m-primary); color: #fff; }
.days__c.on b { color: #fff; }
.hd { margin: 12px 16px 6px; font-size: 22px; font-weight: 400; color: var(--m-on); }
.hd small { font-size: 14px; color: var(--m-primary); }
.empty { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 50px 20px; color: var(--m-on-var); }
.empty .anticon { font-size: 36px; color: var(--m-outline-var); }
.empty b { font-weight: 400; font-size: 16px; }
.tl { padding: 4px 16px 24px; display: flex; flex-direction: column; gap: 10px; }
.tl__r { display: flex; gap: 12px; align-items: stretch; }
.tl__t { width: 46px; flex: none; display: flex; flex-direction: column; padding-top: 10px; line-height: 1.2; }
.tl__t b { font-size: 14px; font-weight: 500; }
.tl__t small { font-size: 12px; color: var(--m-on-var); }
.tl__c { position: relative; flex: 1; min-width: 0; border-radius: 16px; padding: 12px 14px 12px 18px; background: color-mix(in srgb, var(--c) 10%, #fff); display: flex; flex-direction: column; gap: 2px; overflow: hidden; }
.tl__c::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 5px; background: var(--c); }
.tl__c b { font-size: 15px; font-weight: 500; color: var(--m-on); }
.tl__c small { font-size: 12.5px; color: var(--m-on-var); display: flex; gap: 6px; align-items: center; }
.tl__c.now { box-shadow: 0 0 0 2px var(--c); }
.tl__now { position: absolute; top: 10px; right: 12px; font-size: 11px; font-weight: 500; color: #fff; background: var(--c); padding: 2px 8px; border-radius: 99px; }
.tl__brk { flex: 1; border-radius: 16px; border: 1px dashed var(--m-outline-var); padding: 10px 14px; font-size: 13px; color: var(--m-on-var); display: flex; gap: 8px; align-items: center; }
</style>
