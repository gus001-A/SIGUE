<script setup>
/**
 * Mis clases (alumno o tutor), como Google Classroom:
 *  - Panel: tarjetas de las clases con sus tareas por entregar (fecha de entrega en cada tarjeta).
 *  - Clase: Tablón (avisos y material), Trabajo de clase (por temas) y Personas.
 */
import { computed, ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import {
    SolutionOutlined, FolderOutlined, UserOutlined, RightOutlined, ArrowLeftOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PortalHeader from '@/Components/PortalHeader.vue';
import PostCard from '@/Components/Classroom/PostCard.vue';
import ClassworkList from '@/Components/Classroom/ClassworkList.vue';

const props = defineProps({
    student: Object, children: Array, isGuardian: Boolean,
    classes: Array, current: [Object, null], posts: Array, items: Array, canSubmit: Boolean,
    dueSoon: { type: Array, default: () => [] }, people: { type: [Object, null], default: null },
});

// Colores de las tarjetas (como los temas de Google Classroom)
const THEMES = [
    ['#1e8e3e', '#137333'], ['#1967d2', '#174ea6'], ['#e37400', '#b06000'], ['#9334e6', '#7627bb'],
    ['#d93025', '#a50e0e'], ['#12b5cb', '#098591'], ['#e52592', '#b80672'], ['#5f6368', '#3c4043'],
];
const themeOf = (id) => THEMES[props.classes.findIndex((c) => c.id === id) % THEMES.length] ?? THEMES[1];
const go = (id) => router.get(route('portal.clases'), { clase: id });
const openTask = (a) => router.visit(route('portal.tarea', a.id));

const tab = ref('tablon');
const upcoming = computed(() => props.items.filter((a) => !a.submission?.submitted_at && a.submission?.score == null && a.due_at && !a.overdue).slice(0, 3));
const topics = computed(() => [...new Map(props.items.filter((a) => a.topic_id).sort((x, y) => (x.topic_pos ?? 0) - (y.topic_pos ?? 0))
    .map((a) => [a.topic_id, { value: a.topic_id, label: a.topic }])).values()]);
</script>

<template>
    <AppLayout title="Mis clases">
        <!-- ================= Panel de clases ================= -->
        <div v-if="!current" class="hm">
            <PortalHeader :student="student" :children="children" :is-guardian="isGuardian" title="Mis clases" />

            <section class="hm__card">
                <div class="hm__ch"><h2>Clases</h2></div>
                <a-empty v-if="!classes.length" description="Aún no estás inscrito en un grupo con materias." />
                <div class="hm__grid">
                    <article v-for="c in classes" :key="c.id" class="cc" @click="go(c.id)">
                        <header class="cc__h" :style="{ background: `linear-gradient(135deg, ${themeOf(c.id)[0]}, ${themeOf(c.id)[1]})` }">
                            <b :title="c.subject">{{ c.subject }}</b>
                            <span>{{ c.group }}<template v-if="c.level"> · {{ c.level }}</template></span>
                            <small>{{ c.teacher || 'Sin docente' }}</small>
                            <i class="cc__deco" />
                        </header>
                        <span class="cc__av" :style="{ background: themeOf(c.id)[1] }">{{ c.teacher_initials || 'D' }}</span>
                        <div class="cc__body">
                            <template v-if="c.upcoming.length">
                                <a v-for="u in c.upcoming" :key="u.id" class="cc__up" :class="{ late: u.overdue }" @click.stop="openTask(u)">
                                    <small>{{ u.due }}</small><span>{{ u.title }}</span>
                                </a>
                                <small v-if="c.pending > c.upcoming.length" class="cc__more">y {{ c.pending - c.upcoming.length }} más por entregar</small>
                            </template>
                            <small v-else class="cc__none">Sin trabajo pendiente</small>
                        </div>
                        <footer class="cc__f">
                            <a-tooltip title="Ver tu trabajo"><button type="button" @click.stop="router.visit(route('portal.tareas'))"><SolutionOutlined /></button></a-tooltip>
                            <a-tooltip title="Abrir la clase"><button type="button" @click.stop="go(c.id)"><FolderOutlined /></button></a-tooltip>
                        </footer>
                    </article>
                </div>
            </section>
        </div>

        <!-- ================= Una clase ================= -->
        <div v-else class="cls">
            <nav class="cls__tabs">
                <Link :href="route('portal.clases')" class="cls__back" title="Mis clases"><ArrowLeftOutlined /></Link>
                <div class="cls__crumb"><b>{{ current.subject }}</b><small>{{ current.group }}<template v-if="current.level"> · {{ current.level }}</template></small></div>
                <button v-for="t in [{ k: 'tablon', l: 'Tablón' }, { k: 'trabajo', l: 'Trabajo de clase' }, { k: 'personas', l: 'Personas' }]" :key="t.k" type="button"
                    :class="{ on: tab === t.k }" @click="tab = t.k">{{ t.l }}</button>
            </nav>

            <div class="cls__body">
                <!-- Tablón -->
                <template v-if="tab === 'tablon'">
                    <div class="cls__banner" :style="{ background: `linear-gradient(120deg, ${themeOf(current.id)[0]}, ${themeOf(current.id)[1]})` }">
                        <h1>{{ current.subject }}</h1>
                        <p>{{ current.group }}<template v-if="current.level"> · {{ current.level }}</template></p>
                        <i class="cls__deco a" /><i class="cls__deco b" />
                    </div>
                    <div class="cls__stream">
                        <aside class="cls__due">
                            <b>Próximas entregas</b>
                            <p v-if="!upcoming.length">¡Yuju! No tienes que entregar nada pronto.</p>
                            <a v-for="a in upcoming" :key="a.id" @click="openTask(a)"><small>{{ a.due_at }}</small><span>{{ a.title }}</span></a>
                            <a class="cls__all" @click="tab = 'trabajo'">Ver todo</a>
                        </aside>
                        <div class="cls__feed">
                            <a-empty v-if="!posts.length" description="Tu docente aún no ha publicado avisos ni material." class="cls__empty" />
                            <PostCard v-for="p in posts" :key="p.id" :post="p" :can-comment="canSubmit" />
                        </div>
                    </div>
                </template>

                <!-- Trabajo de clase -->
                <div v-else-if="tab === 'trabajo'" class="cls__work">
                    <ClassworkList :items="items" :topics="topics" mode="student" @open="openTask">
                        <template #tools>
                            <Link :href="route('portal.tareas')"><a-button class="cls__yw"><SolutionOutlined /> Ver tu trabajo</a-button></Link>
                        </template>
                    </ClassworkList>
                </div>

                <!-- Personas -->
                <div v-else class="cls__people">
                    <h2>Docente</h2>
                    <div class="pp"><span class="pp__av" :style="{ background: themeOf(current.id)[0] }"><UserOutlined /></span><span>{{ people?.teacher || 'Sin docente asignado' }}</span></div>
                    <h2>Compañeros <small>{{ people?.classmates?.length ?? 0 }} alumnos</small></h2>
                    <div v-for="s in people?.classmates ?? []" :key="s.id" class="pp">
                        <span class="pp__av"><img v-if="s.photo_url" :src="s.photo_url" alt="" /><UserOutlined v-else /></span><span>{{ s.name }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* ---- Panel ---- */
.hm { display: flex; flex-direction: column; gap: 20px; overflow-y: auto; flex: 1 1 auto; min-height: 0; padding-bottom: 20px; }
.hm__card { background: #fff; border-radius: 24px; padding: 20px 24px 24px; border: 1px solid #edf0f3; }
.hm__ch { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
.hm__ch h2 { margin: 0; flex: 1; font-size: 22px; font-weight: 400; color: #202124; }
.hm__link { font-size: 14px; font-weight: 500; color: #1967d2; }
.hm__ib { width: 36px; height: 36px; border-radius: 50%; border: 0; background: none; cursor: pointer; color: #5f6368; }
.hm__ib:hover { background: #f1f3f4; }
.hm__none { margin: 6px 0 0; text-align: center; color: #3c4043; font-size: 15px; }
.hm__due { display: flex; flex-direction: column; }
.hm__di { display: flex; align-items: center; gap: 14px; padding: 10px 8px; border-radius: 10px; color: inherit; }
.hm__di:hover { background: #f8f9fa; }
.hm__dic { width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; background: #e8f0fe; color: #1967d2; flex: none; }
.hm__dt { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.hm__dt b { font-weight: 500; color: #202124; }
.hm__dt small { color: #5f6368; }
.hm__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 18px; margin-top: 12px; }
.cc { position: relative; display: flex; flex-direction: column; border: 1px solid #dadce0; border-radius: 10px; overflow: hidden; background: #fff; cursor: pointer; min-height: 290px;
    transition: box-shadow .15s; }
.cc:hover { box-shadow: 0 1px 3px rgba(60, 64, 67, .3), 0 4px 8px 3px rgba(60, 64, 67, .15); }
.cc__h { position: relative; overflow: hidden; height: 100px; padding: 16px 16px 12px; color: #fff; display: flex; flex-direction: column; }
.cc__h b { font-size: 22px; font-weight: 400; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-right: 40px; }
.cc__h b:hover { text-decoration: underline; }
.cc__h span { font-size: 13.5px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-right: 80px; }
.cc__h small { font-size: 12.5px; margin-top: auto; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-right: 80px; }
.cc__deco { position: absolute; right: -30px; top: -40px; width: 140px; height: 140px; border-radius: 50%; background: rgba(255, 255, 255, .14); }
.cc__av { position: absolute; right: 16px; top: 60px; width: 76px; height: 76px; border-radius: 50%; display: grid; place-items: center; color: #fff; font-size: 26px; font-weight: 500;
    border: 3px solid #fff; box-shadow: 0 2px 6px rgba(0, 0, 0, .2); }
.cc__body { flex: 1; padding: 46px 16px 12px; display: flex; flex-direction: column; gap: 4px; }
.cc__lbl { font-size: 12px; color: #5f6368; }
.cc__up { display: flex; flex-direction: column; padding: 4px 0; color: #3c4043; min-width: 0; }
.cc__up small { font-size: 12px; color: #5f6368; }
.cc__up span { font-size: 13.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cc__up.late small { color: #d93025; font-weight: 500; }
.cc__up:hover span { color: #1967d2; text-decoration: underline; }
.cc__more { font-size: 12px; color: #5f6368; margin-top: 2px; }
.cc__none { font-size: 12.5px; color: #80868b; }
.cc__pend { font-size: 13px; color: #d93025; font-weight: 500; }
.cc__f { display: flex; justify-content: flex-end; gap: 6px; padding: 8px 10px; border-top: 1px solid #dadce0; }
.cc__f button { width: 44px; height: 44px; border-radius: 50%; border: 0; background: none; cursor: pointer; color: #5f6368; font-size: 18px; }
.cc__f button:hover { background: #f1f3f4; }

/* ---- Clase ---- */
.cls { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; background: #fff; border-radius: 16px; border: 1px solid #edf0f3; overflow: hidden; }
.cls__tabs { flex: none; display: flex; align-items: center; gap: 6px; padding: 0 16px; border-bottom: 1px solid #dadce0; height: 60px; }
.cls__back { width: 38px; height: 38px; border-radius: 50%; display: grid; place-items: center; color: #5f6368; }
.cls__back:hover { background: #f1f3f4; }
.cls__crumb { display: flex; flex-direction: column; line-height: 1.2; margin-right: 18px; min-width: 0; }
.cls__crumb b { font-size: 15px; font-weight: 500; color: #202124; }
.cls__crumb small { font-size: 12px; color: #5f6368; }
.cls__tabs button { height: 60px; border: 0; background: none; padding: 0 22px; cursor: pointer; font-family: inherit; font-size: 14px; font-weight: 500; color: #3c4043; border-bottom: 4px solid transparent; }
.cls__tabs button:hover { background: #f8f9fa; }
.cls__tabs button.on { color: #1967d2; border-bottom-color: #1967d2; }
.cls__body { flex: 1; min-height: 0; overflow-y: auto; padding: 24px; }
.cls__banner { position: relative; overflow: hidden; height: 120px; border-radius: 10px; padding: 18px 24px; color: #fff; display: flex; flex-direction: column; justify-content: flex-start; }
.cls__banner h1 { margin: 0; font-size: 26px; font-weight: 400; color: #fff; }
.cls__banner p { margin: 4px 0 0; font-size: 15px; font-weight: 400; }
.cls__info { position: absolute; right: 22px; bottom: 22px; font-size: 22px; }
.cls__deco { position: absolute; border-radius: 50%; background: rgba(255, 255, 255, .12); }
.cls__deco.a { width: 320px; height: 320px; right: -60px; top: -120px; }
.cls__deco.b { width: 180px; height: 180px; right: 220px; bottom: -110px; }
.cls__stream { display: grid; grid-template-columns: 200px minmax(0, 1fr); gap: 24px; margin-top: 24px; align-items: start; }
.cls__due { border: 1px solid #dadce0; border-radius: 10px; padding: 16px; display: flex; flex-direction: column; gap: 8px; }
.cls__due > b { font-size: 16px; font-weight: 500; color: #202124; }
.cls__due p { margin: 0; font-size: 13px; color: #3c4043; }
.cls__due a { display: flex; flex-direction: column; color: inherit; }
.cls__due a small { font-size: 12px; color: #5f6368; }
.cls__due a span { font-size: 13px; color: #3c4043; }
.cls__due a:hover span { color: #1967d2; text-decoration: underline; }
.cls__all { align-self: flex-end; font-size: 14px; font-weight: 500; color: #1967d2 !important; }
.cls__feed { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
.cls__empty { border: 1px dashed #dadce0; border-radius: 10px; padding: 30px; }
.cls__work { max-width: 1100px; margin: 0 auto; }
.cls__yw { border-radius: 99px !important; color: #1967d2 !important; border-color: #747775 !important; font-weight: 500; height: 40px; }
.cls__people { max-width: 900px; margin: 0 auto; }
.cls__people h2 { margin: 18px 0 8px; padding-bottom: 12px; font-size: 28px; font-weight: 400; color: #1967d2; border-bottom: 1px solid #1967d2; display: flex; align-items: baseline; justify-content: space-between; }
.cls__people h2 small { font-size: 14px; color: #1967d2; }
.pp { display: flex; align-items: center; gap: 16px; padding: 12px 16px; border-bottom: 1px solid #e8eaed; font-size: 14px; color: #3c4043; }
.pp__av { width: 32px; height: 32px; border-radius: 50%; overflow: hidden; display: grid; place-items: center; color: #fff; background: #8ab4f8; flex: none; }
.pp__av img { width: 100%; height: 100%; object-fit: cover; }
@media (max-width: 860px) {
    .cls__stream { grid-template-columns: 1fr; }
    .cls__banner { height: 150px; } .cls__banner h1 { font-size: 26px; } .cls__banner p { font-size: 16px; }
    .cls__crumb { display: none; } .cls__tabs button { padding: 0 12px; }
    .cls__body { padding: 14px; }
}
</style>
