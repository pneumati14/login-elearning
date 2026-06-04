<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Chart from 'chart.js/auto'
import type { ChartConfiguration } from 'chart.js'

/**
 * Thin Chart.js wrapper: renders the given configuration on a canvas and
 * fully re-creates the chart whenever the configuration changes (the
 * report is small, so a rebuild is simpler and safe).
 */
const props = withDefaults(defineProps<{ config: ChartConfiguration; height?: string }>(), {
  height: '300px',
})

const canvas = ref<HTMLCanvasElement | null>(null)
let chart: Chart | null = null

function render(): void {
  if (null === canvas.value) return
  chart?.destroy()
  chart = new Chart(canvas.value, props.config)
}

onMounted(render)
watch(() => props.config, render, { deep: true })
onBeforeUnmount(() => chart?.destroy())
</script>

<template>
  <div class="chart-box" :style="{ height: props.height }">
    <canvas ref="canvas" />
  </div>
</template>

<style scoped>
.chart-box {
  position: relative;
  width: 100%;
}
</style>
