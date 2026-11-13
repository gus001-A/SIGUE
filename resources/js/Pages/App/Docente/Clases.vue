<script setup>
/** App del docente · Clases: tarjetas como Google Classroom con alumnos y trabajos por calificar. */
import { router } from '@inertiajs/vue3';
import { TeamOutlined, FormOutlined, CheckSquareOutlined, CheckCircleFilled } from '@ant-design/icons-vue';
import MobileLayout from '@/Layouts/MobileLayout.vue';
import { ripple as vRipple, CLASS_THEMES } from '@/lib/ripple';

defineProps({ classes: { type: Array, default: () => [] }, schedule: Array, days: Object });
const color = (i) => CLASS_THEMES[i % CLASS_THEMES.length];
</script>

<template>
    <MobileLayout title="Clases">
        <div class="pg">
            <p v-if="!classes.length" class="empty">No tienes clases asignadas en este ciclo.</p>
            <article v-for="(c, i) in classes" :key="c.id" class="cc">
                <div v-ripple class="cc__h" :style="{ '--c': color(i) }" @click="router.visit(route('app.docente.clase', c.id))">
                    <b>{{ c.subject }}</b>
                    <span>{{ c.group }}<template v-if="c.level"> · {{ c.level }}</template></span>
                    <small><TeamOutlined /> {{ c.students }} alumnos</small>
                    <i class="d1" /><i class="d2" />
                </div>
                <div class="cc__f">
                    <span v-if="c.to_grade" class="pill warn"><FormOutlined /> {{ c.to_grade }} por calificar</span>
                    <span v-else class="pill ok"><CheckCircleFilled /> Al día</span>
                    <button v-ripple type="button" class="ib" title="Pasar lista" @click="router.visit(route('app.docente.lista', c.id))"><CheckSquareOutlined /></button>
                </div>
            </article>
        </div>
    </MobileLayout>
</template>

<style scoped>
.pg { padding: 6px 16px 28px; display: flex; flex-direction: column; gap: 14px; }
.empty { text-align: center; color: var(--m-on-var); padding: 40px 10px; }
.cc { background: var(--m-surface); border-radius: 20px; overflow: hidden; border: 1px solid #e1e3e1; }
.cc__h { position: relative; overflow: hidden; background: var(--c); color: #fff; padding: 16px; display: flex; flex-direction: column; min-height: 108px; cursor: pointer; }
.cc__h b { font-size: 22px; font-weight: 400; margin-right: 30px; }
.cc__h span { font-size: 14px; opacity: .95; }
.cc__h small { margin-top: auto; padding-top: 8px; font-size: 13px; opacity: .9; display: inline-flex; gap: 6px; align-items: center; }
.d1, .d2 { position: absolute; border-radius: 50%; background: rgba(255, 255, 255, .13); pointer-events: none; }
.d1 { width: 160px; height: 160px; right: -50px; top: -70px; }
.d2 { width: 90px; height: 90px; right: 60px; bottom: -55px; }
.cc__f { display: flex; align-items: center; justify-content: space-between; padding: 8px 8px 8px 16px; }
.pill { font-size: 12.5px; font-weight: 500; padding: 4px 10px; border-radius: 8px; display: inline-flex; gap: 6px; align-items: center; }
.pill.warn { background: #eaddff; color: #4f378b; }
.pill.ok { background: #c4eed0; color: #0d652d; }
.ib { width: 44px; height: 44px; border-radius: 50%; border: 0; background: none; font-size: 19px; color: var(--m-on-var); cursor: pointer; }
</style>
