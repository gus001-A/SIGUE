<script setup>
/**
 * Rúbrica como en Google Classroom: cada criterio con su puntaje y sus niveles en tarjetas
 * (puntos, título y descripción). El nivel elegido se resalta.
 *  - editable: el docente elige un nivel por criterio (v-model:scores = [índice de nivel por criterio]).
 *  - Solo lectura: el alumno ve los criterios y, ya calificada, el nivel que obtuvo.
 */
import { computed, ref } from 'vue';
import { TableOutlined, DownOutlined, RightOutlined, UpOutlined } from '@ant-design/icons-vue';

const props = defineProps({
    rubric: { type: Array, default: () => [] },
    scores: { type: [Array, Object], default: () => [] },
    editable: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
    collapsible: { type: Boolean, default: false },
    bars: { type: Boolean, default: false },          // estilo herramienta de calificación: barra segmentada por criterio
    hideHead: { type: Boolean, default: false },
});
const emit = defineEmits(['update:scores']);
const openRows = ref(props.rubric.map(() => !props.collapsible && !props.bars));
const allOpen = ref(true);

const at = (i) => { const v = Array.isArray(props.scores) ? props.scores[i] : props.scores?.[i]; return v === undefined ? null : v; };
const maxOf = (c) => Math.max(0, ...c.levels.map((l) => Number(l.points) || 0));
const total = computed(() => props.rubric.reduce((t, c, i) => t + (at(i) !== null ? Number(c.levels[at(i)]?.points ?? 0) : 0), 0));
const max = computed(() => props.rubric.reduce((t, c) => t + maxOf(c), 0));
const graded = computed(() => props.rubric.some((_, i) => at(i) !== null));

function pick(i, j) {
    if (!props.editable) return;
    const arr = props.rubric.map((_, k) => at(k));
    arr[i] = arr[i] === j ? null : j;
    emit('update:scores', arr);
}
</script>

<template>
    <!-- Barras: cada criterio con su casilla "3/5" y una barra con un segmento por nivel -->
    <div v-if="rubric.length && bars" class="rb">
        <div v-if="!hideHead" class="rb__head">
            <span>Rúbrica</span>
            <span class="rb__tot"><template v-if="graded || editable">{{ total }}</template>/{{ max }}
                <button type="button" class="rb__tg" :title="allOpen ? 'Contraer' : 'Expandir'" @click="allOpen = !allOpen"><component :is="allOpen ? UpOutlined : DownOutlined" /></button></span>
        </div>
        <template v-if="allOpen">
            <section v-for="(c, i) in rubric" :key="i" class="rb__c">
                <div class="rb__h">
                    <button type="button" class="rb__n" @click="openRows[i] = !openRows[i]">{{ c.name }} <component :is="openRows[i] ? UpOutlined : DownOutlined" /></button>
                    <span class="rb__box" :class="{ on: at(i) !== null }"><b>{{ at(i) !== null ? c.levels[at(i)]?.points : '' }}</b>/{{ maxOf(c) }}</span>
                </div>
                <div class="rb__bar">
                    <a-tooltip v-for="(l, j) in c.levels" :key="j" :title="`${l.label || 'Nivel ' + (j + 1)} · ${l.points} pts`">
                        <button type="button" class="rb__seg" :class="{ on: at(i) === j, ro: !editable }" @click="pick(i, j)" />
                    </a-tooltip>
                </div>
                <div v-if="openRows[i]" class="rb__lvs">
                    <p v-if="c.description" class="rb__d">{{ c.description }}</p>
                    <button v-for="(l, j) in c.levels" :key="j" type="button" class="rb__lv" :class="{ on: at(i) === j, ro: !editable }" @click="pick(i, j)">
                        <b>{{ l.points }} pts · {{ l.label }}</b><small v-if="l.description">{{ l.description }}</small>
                    </button>
                </div>
            </section>
        </template>
    </div>
    <div v-else-if="rubric.length" class="rv" :class="{ 'is-compact': compact }">
        <div class="rv__head">
            <span><TableOutlined /> Rúbrica</span>
            <span class="rv__total"><template v-if="graded || editable">{{ total }}</template><template v-else>—</template> / {{ max }}</span>
        </div>
        <section v-for="(c, i) in rubric" :key="i" class="rv__c">
            <button type="button" class="rv__ch" @click="openRows[i] = !openRows[i]">
                <component :is="openRows[i] ? DownOutlined : RightOutlined" v-if="collapsible" class="rv__car" />
                <span class="rv__name">{{ c.name }}</span>
                <span class="rv__pts">{{ at(i) !== null ? c.levels[at(i)]?.points : '' }}<template v-if="at(i) === null">—</template> / {{ maxOf(c) }}</span>
            </button>
            <template v-if="openRows[i]">
                <p v-if="c.description" class="rv__desc">{{ c.description }}</p>
                <div class="rv__levels">
                    <button v-for="(l, j) in c.levels" :key="j" type="button" class="rv__lv" :class="{ on: at(i) === j, ro: !editable }" @click="pick(i, j)">
                        <b>{{ l.points }} {{ Number(l.points) === 1 ? 'punto' : 'puntos' }}</b>
                        <span class="rv__lt">{{ l.label }}</span>
                        <small v-if="l.description">{{ l.description }}</small>
                    </button>
                </div>
            </template>
        </section>
    </div>
</template>

<style scoped>
.rv { border: 1px solid #dadce0; border-radius: 8px; background: #fff; overflow: hidden; }
.rv__head { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid #dadce0; }
.rv__head > span:first-child { display: inline-flex; gap: 8px; align-items: center; font-size: 15px; font-weight: 500; color: #202124; }
.rv__head .anticon { color: #1967d2; }
.rv__total { font-size: 15px; color: #3c4043; }
.rv__c { padding: 14px 16px; border-top: 1px solid #e8eaed; }
.rv__c:first-of-type { border-top: 0; }
.rv__ch { width: 100%; display: flex; align-items: center; gap: 8px; border: 0; background: none; padding: 0; font-family: inherit; cursor: default; text-align: left; }
.rv__car { color: #5f6368; font-size: 11px; cursor: pointer; }
.rv__name { flex: 1; font-size: 14px; font-weight: 500; color: #202124; }
.rv__pts { font-size: 13px; color: #5f6368; }
.rv__desc { margin: 4px 0 0; font-size: 13px; color: #5f6368; }
.rv__levels { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px; margin-top: 10px; }
.rv__lv { display: flex; flex-direction: column; gap: 3px; text-align: left; padding: 10px 12px; border: 1px solid #dadce0; border-radius: 8px; background: #fff; font-family: inherit;
    cursor: pointer; transition: background .12s, border-color .12s; }
.rv__lv.ro { cursor: default; }
.rv__lv:not(.ro):hover { background: #f8f9fa; }
.rv__lv b { font-size: 13px; font-weight: 600; color: #202124; }
.rv__lt { font-size: 13px; color: #3c4043; }
.rv__lv small { font-size: 12px; color: #5f6368; line-height: 1.35; }
.rv__lv.on { border: 2px solid #1967d2; background: #e8f0fe; padding: 9px 11px; }
.rv__lv.on b { color: #1967d2; }
.rv.is-compact .rv__levels { grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); }
.rv.is-compact .rv__lv small { display: none; }
.rb { display: flex; flex-direction: column; }
.rb__head { display: flex; align-items: center; justify-content: space-between; padding: 4px 0 10px; font-size: 16px; color: #202124; }
.rb__head > span:first-child { font-weight: 500; }
.rb__tot { display: inline-flex; align-items: center; gap: 10px; color: #3c4043; }
.rb__tg { border: 0; background: none; color: #1967d2; cursor: pointer; padding: 4px; border-radius: 50%; }
.rb__tg:hover { background: #e8f0fe; }
.rb__c { border: 1px solid #dadce0; border-bottom: 0; padding: 14px 16px; background: #fff; }
.rb__c:first-of-type { border-radius: 8px 8px 0 0; }
.rb__c:last-of-type { border-bottom: 1px solid #dadce0; border-radius: 0 0 8px 8px; }
.rb__c:only-of-type { border-radius: 8px; }
.rb__h { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.rb__n { border: 0; background: none; padding: 4px 0; font-family: inherit; font-size: 14px; font-weight: 500; color: #1967d2; text-transform: uppercase; cursor: pointer; display: inline-flex; gap: 8px; align-items: center; text-align: left; }
.rb__n .anticon { font-size: 10px; }
.rb__box { min-width: 64px; padding: 8px 10px; border: 1px solid #c4c7c5; border-radius: 6px; text-align: right; font-size: 14px; color: #5f6368; }
.rb__box b { color: #202124; font-weight: 500; }
.rb__box.on { border-color: #1967d2; background: #f5f9ff; }
.rb__bar { display: flex; gap: 3px; margin-top: 12px; }
.rb__seg { flex: 1; max-width: 90px; height: 22px; border: 0; border-radius: 2px; background: #f1f3f4; cursor: pointer; transition: background .12s; padding: 0; }
.rb__seg:not(.ro):hover { background: #d2e3fc; }
.rb__seg.ro { cursor: default; }
.rb__seg.on, .rb__seg.on:not(.ro):hover { background: linear-gradient(135deg, #1967d2, #4285f4); }
.rb__lvs { display: flex; flex-direction: column; gap: 6px; margin-top: 10px; }
.rb__d { margin: 0; font-size: 12.5px; color: #5f6368; }
.rb__lv { display: flex; flex-direction: column; gap: 2px; text-align: left; border: 1px solid #e0e3e7; border-radius: 8px; padding: 8px 10px; background: #fff; font-family: inherit; cursor: pointer; }
.rb__lv.ro { cursor: default; }
.rb__lv b { font-size: 12.5px; font-weight: 500; color: #202124; }
.rb__lv small { font-size: 12px; color: #5f6368; }
.rb__lv.on { border-color: #1967d2; background: #e8f0fe; }
.rb__lv.on b { color: #1967d2; }
</style>
