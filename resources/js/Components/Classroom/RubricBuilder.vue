<script setup>
/**
 * Editor de rúbrica: criterios con niveles de desempeño y sus puntos.
 * v-model = [{ name, description, levels: [{ label, points, description }] }]
 * El puntaje máximo de la actividad es la suma del mejor nivel de cada criterio.
 */
import { computed } from 'vue';
import { PlusOutlined, DeleteOutlined, ThunderboltOutlined, TableOutlined } from '@ant-design/icons-vue';

const props = defineProps({ modelValue: { type: Array, default: () => [] } });
const emit = defineEmits(['update:modelValue']);
const rubric = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) });

const LEVELS = () => [
    { label: 'Excelente', points: 4, description: '' },
    { label: 'Bueno', points: 3, description: '' },
    { label: 'Suficiente', points: 2, description: '' },
    { label: 'Insuficiente', points: 0, description: '' },
];
const max = computed(() => rubric.value.reduce((t, c) => t + Math.max(0, ...c.levels.map((l) => Number(l.points) || 0)), 0));

const update = (fn) => { const r = JSON.parse(JSON.stringify(rubric.value)); fn(r); rubric.value = r; };
const addCriterion = () => update((r) => r.push({ name: '', description: '', levels: LEVELS() }));
const removeCriterion = (i) => update((r) => r.splice(i, 1));
const addLevel = (i) => update((r) => r[i].levels.push({ label: '', points: 0, description: '' }));
const removeLevel = (i, j) => update((r) => r[i].levels.splice(j, 1));
const set = (i, j, k, v) => update((r) => { if (j === null) r[i][k] = v; else r[i].levels[j][k] = v; });
function example() {
    rubric.value = [
        { name: 'CONTENIDO', description: 'Información completa y correcta', levels: [
            { label: 'Excelente', points: 4, description: 'Completo y sin errores' }, { label: 'Bueno', points: 3, description: 'Pocos errores' },
            { label: 'Suficiente', points: 2, description: 'Incompleto' }, { label: 'Insuficiente', points: 0, description: 'No cumple' }] },
        { name: 'PRESENTACIÓN', description: 'Orden, limpieza y ortografía', levels: [
            { label: 'Excelente', points: 3, description: '' }, { label: 'Bueno', points: 2, description: '' }, { label: 'Insuficiente', points: 0, description: '' }] },
        { name: 'ENTREGA A TIEMPO', description: '', levels: [{ label: 'A tiempo', points: 3, description: '' }, { label: 'Tarde', points: 1, description: '' }] },
    ];
}
</script>

<template>
    <div class="rb">
        <div v-if="!rubric.length" class="rb__empty">
            <TableOutlined />
            <div>
                <b>Esta tarea no tiene rúbrica</b>
                <span>Sin rúbrica calificas con un puntaje directo. Con rúbrica eliges un nivel por criterio y la calificación se suma sola; el alumno ve cómo se le calificó.</span>
            </div>
            <div class="rb__acts">
                <a-button type="primary" ghost @click="addCriterion"><PlusOutlined /> Crear rúbrica</a-button>
                <a-button @click="example"><ThunderboltOutlined /> Usar un ejemplo</a-button>
            </div>
        </div>
        <template v-else>
            <div class="rb__table">
                <div class="rb__thead">
                    <span>Criterio</span><span>Niveles de desempeño</span>
                </div>
                <div v-for="(c, i) in rubric" :key="i" class="rb__row">
                    <div class="rb__crit">
                        <div class="rb__crit-top">
                            <span class="rb__n">{{ i + 1 }}</span>
                            <a-tooltip title="Quitar criterio"><button type="button" class="rb__del" @click="removeCriterion(i)"><DeleteOutlined /></button></a-tooltip>
                        </div>
                        <a-input :value="c.name" placeholder="Nombre del criterio" class="rb__name" @update:value="(v) => set(i, null, 'name', v)" />
                        <a-textarea :value="c.description" :auto-size="{ minRows: 2, maxRows: 4 }" placeholder="Descripción del criterio (opcional)" @update:value="(v) => set(i, null, 'description', v)" />
                    </div>
                    <div class="rb__levels">
                        <div v-for="(l, j) in c.levels" :key="j" class="rb__l">
                            <div class="rb__lh">
                                <a-input-number :value="l.points" :min="0" :max="100" :controls="false" addon-after="pts" class="rb__pts" @update:value="(v) => set(i, j, 'points', v ?? 0)" />
                                <button v-if="c.levels.length > 2" type="button" class="rb__x" title="Quitar nivel" @click="removeLevel(i, j)"><DeleteOutlined /></button>
                            </div>
                            <a-input :value="l.label" placeholder="Nombre del nivel" class="rb__lname" @update:value="(v) => set(i, j, 'label', v)" />
                            <a-textarea :value="l.description" :auto-size="{ minRows: 2, maxRows: 4 }" placeholder="Qué se espera en este nivel" @update:value="(v) => set(i, j, 'description', v)" />
                        </div>
                        <button v-if="c.levels.length < 6" type="button" class="rb__addl" @click="addLevel(i)"><PlusOutlined /><span>Nivel</span></button>
                    </div>
                </div>
            </div>
            <div class="rb__foot">
                <a-button @click="addCriterion"><PlusOutlined /> Agregar criterio</a-button>
                <span class="rb__max">Puntaje máximo de la actividad <b>{{ max }} pts</b></span>
            </div>
        </template>
    </div>
</template>

<style scoped>
.rb { display: flex; flex-direction: column; gap: 12px; }
.rb__empty { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; padding: 18px 20px; border: 1px dashed #d6dee8; border-radius: 12px; background: #fafbfc; }
.rb__empty > .anticon { font-size: 26px; color: #5d7085; }
.rb__empty > div:nth-child(2) { flex: 1; min-width: 260px; display: flex; flex-direction: column; gap: 2px; }
.rb__empty b { color: #0a1f44; }
.rb__empty span { font-size: 13px; color: #5d7085; }
.rb__acts { display: flex; gap: 8px; }

.rb__table { border: 1px solid #e3e8ef; border-radius: 12px; overflow: hidden; }
.rb__thead { display: grid; grid-template-columns: 280px minmax(0, 1fr); background: #f6f8fa; border-bottom: 1px solid #e3e8ef; }
.rb__thead span { padding: 10px 14px; font-size: 11.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #5d7085; }
.rb__row { display: grid; grid-template-columns: 280px minmax(0, 1fr); border-top: 1px solid #eef1f5; }
.rb__row:first-of-type { border-top: 0; }
.rb__crit { display: flex; flex-direction: column; gap: 8px; padding: 14px; border-right: 1px solid #eef1f5; background: #fcfcfd; }
.rb__crit-top { display: flex; align-items: center; justify-content: space-between; }
.rb__n { font-size: 12px; font-weight: 700; color: #5d7085; }
.rb__n::before { content: 'Criterio '; }
.rb__del, .rb__x { border: 0; background: none; color: #9aa7b6; cursor: pointer; padding: 4px 6px; border-radius: 6px; }
.rb__del:hover, .rb__x:hover { color: #d9363e; background: #fdecec; }
.rb__name :deep(input) { font-weight: 600; }
.rb__levels { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 10px; padding: 14px; align-content: start; }
.rb__l { display: flex; flex-direction: column; gap: 8px; padding: 10px; border: 1px solid #e3e8ef; border-radius: 10px; background: #fff; }
.rb__lh { display: flex; align-items: center; justify-content: space-between; gap: 6px; }
.rb__pts { width: 110px; }
.rb__pts :deep(input) { font-weight: 700; text-align: center; }
.rb__lname :deep(input) { font-weight: 600; }
.rb__addl { grid-column: 1 / -1; display: flex; align-items: center; justify-content: center; gap: 6px; min-height: 34px; border: 1px dashed #cfd8e3; border-radius: 10px; background: #fafbfc;
    color: #5d7085; cursor: pointer; font-family: inherit; font-size: 13px; font-weight: 600; }
.rb__addl:hover { border-color: #1d7fc9; color: #1d7fc9; }
.rb__foot { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.rb__max { margin-left: auto; font-size: 13px; color: #5d7085; }
.rb__max b { color: #0a1f44; font-size: 15px; margin-left: 6px; }
@media (max-width: 860px) { .rb__thead { display: none; } .rb__row { grid-template-columns: 1fr; } .rb__crit { border-right: 0; border-bottom: 1px solid #eef1f5; } }
</style>
