<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { MailOutlined, ScheduleOutlined, ClockCircleOutlined, EnvironmentOutlined, UserOutlined, ReadOutlined, TeamOutlined } from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PortalHeader from '@/Components/PortalHeader.vue';

const props = defineProps({ student: Object, children: Array, isGuardian: Boolean, schedule: Array, days: Object, teachers: Array, break: { type: Object, default: null } });
const PALETTE = ['#1d7fc9', '#14a39a', '#2e9b43', '#f39200', '#e3262b', '#7c4ddb', '#e0457b', '#0f5a99'];
const colorOf = (subject) => PALETTE[Math.max(0, props.teachers.findIndex((t) => t.subject === subject)) % PALETTE.length];
const usedDays = computed(() => [1, 2, 3, 4, 5, 6, 7].filter((d) => d <= 5 || props.schedule.some((s) => s.day === d)));
/** Clases del día más el receso del turno, en orden de hora. */
const byDay = (d) => [...props.schedule.filter((s) => s.day === d), ...(props.break ? [{ isBreak: true, starts_at: props.break.start, ends_at: props.break.end }] : [])]
    .sort((a, b) => a.starts_at.localeCompare(b.starts_at));
const today = new Date().getDay() || 7;
const canMessage = usePage().props.auth.permissions.includes('mensajes.usar');
</script>

<template>
    <AppLayout title="Horario">
        <PortalHeader :student="student" :children="children" :is-guardian="isGuardian" title="Horario y docentes" />

        <div class="sg-row-fill">
            <a-card :bordered="false" class="sg-fill" style="flex: 1; min-width: 0">
                <template #title><span class="sg-card-title" style="--c: #14a39a"><ScheduleOutlined /> Horario semanal</span></template>
                <div class="sg-scroll">
                    <a-empty v-if="!schedule.length" description="Sin horario registrado" />
                    <div v-else class="sg-week" :style="{ gridTemplateColumns: `repeat(${usedDays.length}, minmax(118px, 1fr))` }">
                        <div v-for="d in usedDays" :key="d">
                            <div class="sg-week__name" :class="{ today: d === today }">{{ days[d] }}</div>
                            <template v-for="s in byDay(d)" :key="(s.subject ?? 'receso') + s.starts_at">
                            <div v-if="s.isBreak" class="brk"><b>☕ Receso</b><span>{{ s.starts_at }}–{{ s.ends_at }}</span></div>
                            <div v-else class="sg-week__slot" :style="{ '--c': colorOf(s.subject) }">
                                <span class="t"><ClockCircleOutlined /> {{ s.starts_at }}–{{ s.ends_at }}</span>
                                <b>{{ s.subject }}</b>
                                <span v-if="s.teacher"><UserOutlined /> {{ s.teacher }}</span>
                                <span v-if="s.room"><EnvironmentOutlined /> {{ s.room }}</span>
                            </div>
                            </template>
                        </div>
                    </div>
                </div>
            </a-card>

            <a-card :bordered="false" class="sg-fill teachers">
                <template #title><span class="sg-card-title" style="--c: #7c4ddb"><TeamOutlined /> Docentes</span></template>
                <div class="sg-scroll">
                    <div v-for="t in teachers" :key="t.subject" class="sg-li" :style="{ '--c': colorOf(t.subject) }">
                        <span class="sg-li__ic"><ReadOutlined /></span>
                        <span class="sg-li__txt"><b>{{ t.subject }}</b><small>{{ t.teacher || 'Sin docente asignado' }}</small></span>
                        <a-tooltip v-if="canMessage && t.user_id" title="Enviar mensaje"><Link :href="route('mensajes.index')"><a-button class="sg-ab green" size="small"><MailOutlined /></a-button></Link></a-tooltip>
                    </div>
                </div>
            </a-card>
        </div>
    </AppLayout>
</template>

<style scoped>
.brk { display: flex; justify-content: space-between; align-items: center; margin: 6px 0; padding: 6px 10px; border-radius: 10px; font-size: 12px; color: #b45309; border: 1px dashed #f5c26b;
    background: repeating-linear-gradient(135deg, #fff7e6, #fff7e6 8px, #fdecc8 8px, #fdecc8 16px); }
.brk b { letter-spacing: .04em; }
.teachers { width: 300px; flex: none; }
@media (max-width: 900px) { .teachers { width: 100%; } }
</style>
