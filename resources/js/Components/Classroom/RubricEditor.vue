<script setup>
/**
 * "Crear / Editar rúbrica" a pantalla completa, como Google Classroom:
 * cada criterio con título, descripción y sus niveles (puntos, título y descripción), con ⊕ para insertar niveles.
 * Trabaja sobre una copia y solo la devuelve al pulsar "Guardar".
 */
import { computed, ref, watch } from 'vue';
import { PlusOutlined, PlusCircleOutlined, MoreOutlined, CopyOutlined, DeleteOutlined, TableOutlined } from '@ant-design/icons-vue';
import SgModal from '@/Components/SgModal.vue';
import { message } from '@/lib/notify';

const props = defineProps({ open: Boolean, modelValue: { type: Array, default: () => [] }, assignmentTitle: { type: String, default: '' } });
const emit = defineEmits(['update:open', 'update:modelValue']);

const level = (points = 0) => ({ label: '', points, description: '' });
const criterion = () => ({ name: '', description: '', levels: [level(5), level(3), level(0)] });
const rubric = ref([]);
const order = ref('desc');
watch(() => props.open, (v) => {
    if (!v) return;
    rubric.value = props.modelValue.length ? JSON.parse(JSON.stringify(props.modelValue)) : [criterion()];
    order.value = 'desc';
});

const maxOf = (c) => Math.max(0, ...c.levels.map((l) => Number(l.points) || 0));
const total = computed(() => rubric.value.reduce((t, c) => t + maxOf(c), 0));

function sortLevels() {
    for (const c of rubric.value) c.levels.sort((a, b) => (order.value === 'desc' ? 1 : -1) * ((Number(b.points) || 0) - (Number(a.points) || 0)));
}
const insertLevel = (c, at) => c.levels.splice(at, 0, level(0));
const removeLevel = (c, j) => { if (c.levels.length > 2) c.levels.splice(j, 1); else message.warning('Cada criterio necesita al menos 2 niveles.'); };
const duplicate = (i) => rubric.value.splice(i + 1, 0, JSON.parse(JSON.stringify(rubric.value[i])));
const removeCriterion = (i) => rubric.value.splice(i, 1);

const tried = ref(false);
const errName = (c) => tried.value && !c.name.trim();
const errPts = (l) => tried.value && (l.points === '' || l.points === null || l.points === undefined || Number.isNaN(Number(l.points)));
function save() {
    tried.value = true;
    const bad = rubric.value.some((c) => !c.name.trim() || c.levels.some((l) => errPts(l)));
    if (bad) { message.warning('Completa el título de cada criterio y los puntos de cada nivel.'); return; }
    sortLevels();
    emit('update:modelValue', rubric.value.map((c) => ({ ...c, name: c.name.trim(), levels: c.levels.map((l) => ({ ...l, points: Number(l.points) || 0, label: l.label.trim() || `${Number(l.points) || 0} pts` })) })));
    emit('update:open', false);
    tried.value = false;
}
</script>

<template>
    <SgModal :open="open" :title="modelValue.length ? 'Editar rúbrica' : 'Crear rúbrica'" :subtitle="assignmentTitle || 'Criterios y niveles de desempeño'" :icon="TableOutlined"
        color="linear-gradient(135deg, #7c4ddb, #1967d2)" width="min(1320px, 97vw)" ok-text="Guardar rúbrica" @update:open="emit('update:open', $event)" @ok="save">

        <div class="re">
            <h2 class="re__t">{{ assignmentTitle || 'Rúbrica' }}</h2>
            <p class="re__p">Agrega los criterios con los que se evaluará el trabajo de los alumnos y los niveles de desempeño de cada uno. Los alumnos verán la rúbrica con la tarea y, al calificarla, el nivel que obtuvieron.</p>

            <div class="re__opts">
                <label class="re__order">Orden de la puntuación:
                    <a-select v-model:value="order" :options="[{ value: 'desc', label: 'Descendente' }, { value: 'asc', label: 'Ascendente' }]" style="width: 180px" @change="sortLevels" />
                </label>
                <span class="re__total">/{{ total }}</span>
            </div>

            <div class="re__list">
                <section v-for="(c, i) in rubric" :key="i" class="re__c">
                    <div class="re__ch">
                        <div class="gf re__name" :class="{ 'is-err': errName(c) }">
                            <span>Título del criterio (obligatorio)</span>
                            <input v-model="c.name" maxlength="120" />
                        </div>
                        <span class="re__cmax">/{{ maxOf(c) }}</span>
                        <a-dropdown :trigger="['click']" placement="bottomRight">
                            <button type="button" class="re__more"><MoreOutlined /></button>
                            <template #overlay>
                                <a-menu>
                                    <a-menu-item key="dup" @click="duplicate(i)"><CopyOutlined /> Duplicar criterio</a-menu-item>
                                    <a-menu-item key="del" danger :disabled="rubric.length === 1" @click="removeCriterion(i)"><DeleteOutlined /> Borrar criterio</a-menu-item>
                                </a-menu>
                            </template>
                        </a-dropdown>
                    </div>
                    <div class="gf">
                        <span>Descripción del criterio</span>
                        <input v-model="c.description" maxlength="500" />
                    </div>

                    <div class="re__levels">
                        <button type="button" class="re__ins" title="Agregar nivel" @click="insertLevel(c, 0)"><PlusCircleOutlined /></button>
                        <template v-for="(l, j) in c.levels" :key="j">
                            <div class="re__l">
                                <div class="gf" :class="{ 'is-err': errPts(l) }">
                                    <span>Puntos (obligatorio)</span>
                                    <input v-model.number="l.points" v-digits="3" inputmode="numeric" />
                                </div>
                                <div class="gf">
                                    <span>Título del nivel</span>
                                    <input v-model="l.label" maxlength="60" />
                                </div>
                                <div class="gf">
                                    <span>Descripción</span>
                                    <textarea v-model="l.description" rows="2" maxlength="300" />
                                </div>
                                <a-dropdown :trigger="['click']" placement="bottomRight">
                                    <button type="button" class="re__lmore"><MoreOutlined /></button>
                                    <template #overlay>
                                        <a-menu><a-menu-item key="del" danger @click="removeLevel(c, j)"><DeleteOutlined /> Borrar nivel</a-menu-item></a-menu>
                                    </template>
                                </a-dropdown>
                            </div>
                            <button v-if="c.levels.length < 6" type="button" class="re__ins" title="Agregar nivel" @click="insertLevel(c, j + 1)"><PlusCircleOutlined /></button>
                        </template>
                    </div>
                </section>
            </div>

            <a-button class="re__add" @click="rubric.push(criterion())"><PlusOutlined /> Agregar un criterio</a-button>
        </div>
    </SgModal>
</template>

<style scoped>
.re { padding: 0 4px 4px; }
.re__t { display: none; }
.re__p { margin: 0 0 22px; color: #3c4043; font-size: 14px; max-width: 980px; }
.re__opts { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.re__order { display: inline-flex; align-items: center; gap: 10px; font-size: 13px; color: #5f6368; }
.re__total { font-size: 18px; color: #5f6368; padding-right: 60px; }
.re__list { border: 1px solid #dadce0; border-radius: 8px; }
.re__c { display: flex; flex-direction: column; gap: 14px; padding: 24px; border-top: 1px solid #dadce0; }
.re__c:first-child { border-top: 0; }
.re__ch { display: flex; align-items: center; gap: 14px; }
.re__name { width: 320px; }
.re__cmax { margin-left: auto; font-size: 16px; color: #5f6368; }
.re__more, .re__lmore { width: 36px; height: 36px; border-radius: 50%; border: 0; background: none; cursor: pointer; color: #5f6368; font-size: 18px; }
.re__more:hover, .re__lmore:hover { background: #f1f3f4; }
.re__levels { display: flex; align-items: center; gap: 0; overflow-x: auto; padding: 4px 0 8px; }
.re__l { position: relative; flex: none; width: 260px; display: flex; flex-direction: column; gap: 14px; padding: 16px; border: 1px solid #dadce0; border-left-width: 0; background: #fff; }
.re__levels > .re__l:nth-child(2) { border-left-width: 1px; border-radius: 8px 0 0 8px; }
.re__levels > .re__l:last-of-type { border-radius: 0 8px 8px 0; }
.re__lmore { position: absolute; right: 4px; top: 4px; width: 28px; height: 28px; font-size: 15px; }
.re__l > .gf:first-child { margin-right: 26px; }
.re__ins { flex: none; width: 26px; height: 26px; margin: 0 -13px; position: relative; z-index: 1; border-radius: 50%; border: 0; background: #fff; color: #1967d2; font-size: 20px; cursor: pointer; display: grid; place-items: center; }
.re__ins:hover { color: #174ea6; }
.re__add { margin-top: 18px; border-radius: 99px; height: 40px; padding: 0 18px; color: #1967d2; border-color: #dadce0; font-weight: 500; }
@media (max-width: 760px) { .re { padding: 18px 14px 40px; } .re__name { width: auto; flex: 1; } .re__total { padding-right: 0; } }
</style>
