<script setup>
/**
 * Gráfica de barras en SVG (sin dependencias): vertical u horizontal, una o varias series
 * (agrupadas o apiladas), cuadrícula con escala "bonita", valores sobre las barras, línea de
 * referencia opcional (p. ej. calificación aprobatoria) y tooltip al pasar el mouse.
 *
 * <BarChart :labels="['Lun', 'Mar']" :series="[{ name: 'Asistencias', color: '#2e9b43', data: [30, 28] }]" :height="320" />
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    labels: { type: Array, default: () => [] },
    series: { type: Array, default: () => [] },     // [{ name, color, data: [num|null] }]
    height: { type: Number, default: 320 },
    horizontal: { type: Boolean, default: false },
    stacked: { type: Boolean, default: false },
    format: { type: Function, default: (v) => (v == null ? '—' : Number(v).toLocaleString('es-MX', { maximumFractionDigits: 1 })) },
    axisFormat: { type: Function, default: null },
    max: { type: Number, default: null },            // máximo fijo del eje (p. ej. 100 % o 10)
    reference: { type: Object, default: null },      // { value, label, color }
    showValues: { type: Boolean, default: true },
    empty: { type: String, default: 'Sin datos para mostrar' },
});

// ---------- Medidas reactivas al ancho ----------
const root = ref(null);
const width = ref(600);
let ro = null;
onMounted(() => {
    const measure = () => { if (root.value) width.value = Math.max(240, root.value.clientWidth); };
    measure();
    ro = new ResizeObserver(measure);
    ro.observe(root.value);
});
onBeforeUnmount(() => ro?.disconnect());

const hasData = computed(() => props.series.some((s) => s.data.some((v) => v != null && v !== 0)));
const fmtAxis = computed(() => props.axisFormat ?? ((v) => (Math.abs(v) >= 1000 ? `${Math.round(v / 100) / 10}k` : `${Math.round(v * 10) / 10}`)));

/** Escala "bonita": 4–6 divisiones con pasos 1, 2, 2.5, 5 × 10ⁿ. */
function niceScale(maxVal) {
    if (maxVal <= 0) return { top: 1, step: 0.25 };
    const rough = maxVal / 5;
    const pow = 10 ** Math.floor(Math.log10(rough));
    const step = [1, 2, 2.5, 5, 10].map((m) => m * pow).find((s) => s >= rough) ?? 10 * pow;
    return { top: Math.ceil(maxVal / step) * step, step };
}
const totals = computed(() => props.labels.map((_, i) => props.series.reduce((t, s) => t + (Number(s.data[i]) || 0), 0)));
const rawMax = computed(() => {
    const vals = props.stacked ? totals.value : props.series.flatMap((s) => s.data.map((v) => Number(v) || 0));
    return Math.max(props.reference?.value ?? 0, ...vals, 0);
});
const scale = computed(() => (props.max ? { top: props.max, step: niceScale(props.max).step } : niceScale(rawMax.value * 1.08)));
const ticks = computed(() => {
    const out = [];
    for (let v = 0; v <= scale.value.top + 1e-9; v += scale.value.step) out.push(Math.round(v * 1000) / 1000);
    return out;
});

// ---------- Geometría ----------
const pad = computed(() => (props.horizontal
    ? { l: Math.min(190, Math.max(70, Math.max(...props.labels.map((l) => String(l).length), 4) * 7.2)), r: 46, t: 10, b: 28 }
    : { l: 46, r: 14, t: 26, b: 38 }));
const plotW = computed(() => width.value - pad.value.l - pad.value.r);
const plotH = computed(() => props.height - pad.value.t - pad.value.b);
const n = computed(() => Math.max(1, props.labels.length));
const band = computed(() => (props.horizontal ? plotH.value : plotW.value) / n.value);
const groupW = computed(() => Math.min(band.value * 0.72, props.horizontal ? 46 : 96));
const barW = computed(() => (props.stacked || props.series.length === 1 ? groupW.value : groupW.value / props.series.length));
const pos = (v) => ((Number(v) || 0) / scale.value.top) * (props.horizontal ? plotW.value : plotH.value);

const bars = computed(() => {
    const out = [];
    props.labels.forEach((label, i) => {
        const c = band.value * i + band.value / 2;
        let acc = 0;
        props.series.forEach((s, si) => {
            const v = s.data[i];
            if (v == null) return;
            const len = Math.max(0, pos(v));
            const off = props.stacked ? -groupW.value / 2 : -groupW.value / 2 + barW.value * si;
            const b = props.horizontal
                ? { x: pad.value.l + pos(acc), y: pad.value.t + c + off, w: len, h: barW.value }
                : { x: pad.value.l + c + off, y: pad.value.t + plotH.value - pos(acc) - len, w: barW.value, h: len };
            out.push({ ...b, v, i, si, color: s.color, name: s.name, label, top: !props.stacked || si === props.series.length - 1 });
            if (props.stacked) acc += Number(v) || 0;
        });
    });
    return out;
});
const valueLabels = computed(() => {
    if (!props.showValues) return [];
    if (props.stacked) {
        return props.labels.map((_, i) => {
            const t = totals.value[i];
            if (!t) return null;
            const c = band.value * i + band.value / 2;
            return props.horizontal
                ? { x: pad.value.l + pos(t) + 6, y: pad.value.t + c + 4, text: props.format(t), anchor: 'start' }
                : { x: pad.value.l + c, y: pad.value.t + plotH.value - pos(t) - 7, text: props.format(t), anchor: 'middle' };
        }).filter(Boolean);
    }
    // La etiqueta solo se dibuja si cabe sobre la barra (≈6.5 px por carácter); el tooltip siempre muestra el valor.
    return bars.value.filter((b) => props.horizontal || barW.value >= Math.max(18, String(props.format(b.v)).length * 6.5)).map((b) => (props.horizontal
        ? { x: b.x + b.w + 6, y: b.y + b.h / 2 + 4, text: props.format(b.v), anchor: 'start' }
        : { x: b.x + b.w / 2, y: b.y - 7, text: props.format(b.v), anchor: 'middle' }));
});
const refPos = computed(() => (props.reference ? pos(props.reference.value) : null));

// ---------- Tooltip ----------
const hover = ref(null);
const tip = computed(() => {
    if (hover.value === null) return null;
    const i = hover.value;
    const c = band.value * i + band.value / 2;
    const rows = props.series.map((s) => ({ name: s.name, color: s.color, v: s.data[i] })).filter((r) => r.v != null);
    const x = props.horizontal ? pad.value.l + Math.min(plotW.value, pos(props.stacked ? totals.value[i] : Math.max(...rows.map((r) => r.v), 0))) : pad.value.l + c;
    const y = props.horizontal ? pad.value.t + c : pad.value.t + 8;
    return { label: props.labels[i], rows, x: Math.min(x, width.value - 10), y, total: props.stacked && rows.length > 1 ? totals.value[i] : null };
});
const shortLabel = (l) => {
    const s = String(l);
    const maxChars = props.horizontal ? 26 : Math.max(4, Math.floor(band.value / 7));
    return s.length > maxChars ? s.slice(0, maxChars - 1) + '…' : s;
};
const uid = Math.random().toString(36).slice(2, 8);
</script>

<template>
    <div ref="root" class="bc">
        <!-- Leyenda (arriba, fuera del área de la gráfica) -->
        <div v-if="series.length > 1 && hasData" class="bc__legend">
            <span v-for="s in series" :key="s.name"><i :style="{ background: s.color }" />{{ s.name }}</span>
        </div>
        <div v-if="!hasData" class="bc__empty" :style="{ height: height + 'px' }">{{ empty }}</div>
        <svg v-else :width="width" :height="height" role="img">
            <defs>
                <linearGradient v-for="(s, si) in series" :id="`g${uid}${si}`" :key="si" :x1="horizontal ? 0 : 0" :y1="horizontal ? 0 : 0" :x2="horizontal ? 1 : 0" :y2="horizontal ? 0 : 1">
                    <stop offset="0%" :stop-color="s.color" stop-opacity="1" />
                    <stop offset="100%" :stop-color="s.color" stop-opacity="0.72" />
                </linearGradient>
            </defs>

            <!-- Cuadrícula y eje de valores -->
            <g class="bc__grid">
                <template v-for="t in ticks" :key="t">
                    <template v-if="!horizontal">
                        <line :x1="pad.l" :x2="width - pad.r" :y1="pad.t + plotH - pos(t)" :y2="pad.t + plotH - pos(t)" :class="{ base: t === 0 }" />
                        <text :x="pad.l - 8" :y="pad.t + plotH - pos(t) + 4" text-anchor="end">{{ fmtAxis(t) }}</text>
                    </template>
                    <template v-else>
                        <line :y1="pad.t" :y2="pad.t + plotH" :x1="pad.l + pos(t)" :x2="pad.l + pos(t)" :class="{ base: t === 0 }" />
                        <text :y="height - 8" :x="pad.l + pos(t)" text-anchor="middle">{{ fmtAxis(t) }}</text>
                    </template>
                </template>
            </g>

            <!-- Zonas de hover -->
            <rect v-for="(l, i) in labels" :key="'h' + i" class="bc__hit" :class="{ on: hover === i }"
                :x="horizontal ? pad.l : pad.l + band * i" :y="horizontal ? pad.t + band * i : pad.t" :width="horizontal ? plotW : band" :height="horizontal ? band : plotH"
                @mouseenter="hover = i" @mouseleave="hover = null" />

            <!-- Barras -->
            <g class="bc__bars">
                <rect v-for="(b, k) in bars" :key="k" :x="b.x" :y="b.y" :width="Math.max(0, b.w)" :height="Math.max(0, b.h)"
                    :rx="Math.min(7, (horizontal ? b.h : b.w) / 3)" :fill="`url(#g${uid}${b.si})`" :class="{ dim: hover !== null && hover !== b.i }"
                    @mouseenter="hover = b.i" @mouseleave="hover = null" />
            </g>

            <!-- Valores -->
            <g class="bc__vals">
                <text v-for="(t, k) in valueLabels" :key="k" :x="t.x" :y="t.y" :text-anchor="t.anchor">{{ t.text }}</text>
            </g>

            <!-- Línea de referencia -->
            <g v-if="reference" class="bc__ref" :style="{ '--rc': reference.color || '#e3262b' }">
                <line v-if="!horizontal" :x1="pad.l" :x2="width - pad.r" :y1="pad.t + plotH - refPos" :y2="pad.t + plotH - refPos" />
                <line v-else :y1="pad.t" :y2="pad.t + plotH" :x1="pad.l + refPos" :x2="pad.l + refPos" />
                <text v-if="!horizontal" :x="width - pad.r" :y="pad.t + plotH - refPos - 6" text-anchor="end">{{ reference.label }}</text>
                <text v-else :x="pad.l + refPos + 4" :y="pad.t + 10">{{ reference.label }}</text>
            </g>

            <!-- Etiquetas de categoría -->
            <g class="bc__labels">
                <template v-for="(l, i) in labels" :key="'l' + i">
                    <text v-if="!horizontal" :x="pad.l + band * i + band / 2" :y="height - 14" text-anchor="middle" :class="{ on: hover === i }">{{ shortLabel(l) }}</text>
                    <text v-else :x="pad.l - 10" :y="pad.t + band * i + band / 2 + 4" text-anchor="end" :class="{ on: hover === i }">{{ shortLabel(l) }}</text>
                </template>
            </g>
        </svg>

        <!-- Tooltip -->
        <div v-if="tip" class="bc__tip" :style="{ left: tip.x + 'px', top: tip.y + (series.length > 1 ? 24 : 0) + 'px' }" :class="{ 'is-h': horizontal }">
            <b>{{ tip.label }}</b>
            <span v-for="r in tip.rows" :key="r.name"><i :style="{ background: r.color }" />{{ r.name }}: <strong>{{ format(r.v) }}</strong></span>
            <span v-if="tip.total !== null" class="bc__tip-total">Total: <strong>{{ format(tip.total) }}</strong></span>
        </div>

    </div>
</template>

<style scoped>
.bc { position: relative; width: 100%; }
.bc svg { display: block; overflow: visible; font-family: inherit; }
.bc__empty { height: 100%; display: grid; place-items: center; color: #94a3b8; font-size: 13px; border: 1px dashed #e2e8f0; border-radius: 12px; background: #fafbfc; }
.bc__grid line { stroke: #eef2f7; stroke-width: 1; }
.bc__grid line.base { stroke: #cbd5e1; stroke-width: 1.5; }
.bc__grid text { font-size: 11px; fill: #94a3b8; font-variant-numeric: tabular-nums; }
.bc__hit { fill: transparent; }
.bc__hit.on { fill: rgba(29, 127, 201, .06); }
.bc__bars rect { transition: opacity .15s, filter .15s; cursor: pointer; }
.bc__bars rect.dim { opacity: .45; }
.bc__bars rect:hover { filter: brightness(1.08) drop-shadow(0 4px 8px rgba(15, 23, 42, .18)); }
.bc__vals text { font-size: 11.5px; font-weight: 800; fill: #0b2f55; font-variant-numeric: tabular-nums; pointer-events: none; }
.bc__labels text { font-size: 12px; font-weight: 600; fill: #475569; }
.bc__labels text.on { fill: #0b2f55; font-weight: 800; }
.bc__ref line { stroke: var(--rc); stroke-width: 2; stroke-dasharray: 6 5; }
.bc__ref text { font-size: 11px; font-weight: 800; fill: var(--rc); }
.bc__tip {
    position: absolute; margin-top: var(--lg, 0px); transform: translate(-50%, 0); pointer-events: none; z-index: 5; min-width: 130px;
    background: #0b2f55; color: #fff; border-radius: 10px; padding: 8px 10px; font-size: 12px; box-shadow: 0 12px 24px -10px rgba(11, 47, 85, .6);
    display: flex; flex-direction: column; gap: 3px;
}
.bc__tip.is-h { transform: translate(12px, -50%); }
.bc__tip b { font-size: 12.5px; margin-bottom: 2px; }
.bc__tip span { display: flex; align-items: center; gap: 6px; opacity: .95; }
.bc__tip i { width: 9px; height: 9px; border-radius: 3px; flex: none; }
.bc__tip-total { border-top: 1px solid rgba(255, 255, 255, .2); padding-top: 3px; margin-top: 2px; }
.bc__legend { display: flex; justify-content: flex-end; margin-bottom: 4px; gap: 12px; flex-wrap: wrap; font-size: 12px; color: #475569; font-weight: 600; }
.bc__legend span { display: inline-flex; align-items: center; gap: 5px; }
.bc__legend i { width: 10px; height: 10px; border-radius: 3px; }
</style>
