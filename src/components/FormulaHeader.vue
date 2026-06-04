<template>
  <span class="formula-header">
    <span class="formula-header__label">{{ label }}</span>
    <el-popover
      v-model:visible="visible"
      placement="top"
      :width="width"
      trigger="hover"
      popper-class="formula-header-popper"
    >
      <div
        class="formula-header__panel"
        @mouseenter="handlePanelEnter"
        @mouseleave="handlePanelLeave"
      >
        <div class="formula-header__panel-title">{{ label }}</div>
        <div class="formula-header__panel-text">{{ formulaText }}</div>
      </div>
      <template #reference>
        <span
          class="formula-header__icon"
          tabindex="0"
          @mouseenter="handleIconEnter"
          @mouseleave="handleIconLeave"
          @click.stop="handleIconClick"
        >
          ?
        </span>
      </template>
    </el-popover>
  </span>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  label: {
    type: String,
    required: true
  },
  formula: {
    type: String,
    default: ''
  },
  width: {
    type: Number,
    default: 360
  }
})

const visible = ref(false)
const pinned = ref(false)
const hoveringPanel = ref(false)

const formulaText = computed(() => {
  const text = String(props.formula || '').trim()
  return text || '直接取当前字段值，系统不额外计算。'
})

const closeIfNeeded = () => {
  if (!pinned.value && !hoveringPanel.value) {
    visible.value = false
  }
}

const handleIconEnter = () => {
  visible.value = true
}

const handleIconLeave = () => {
  window.setTimeout(closeIfNeeded, 80)
}

const handlePanelEnter = () => {
  hoveringPanel.value = true
  visible.value = true
}

const handlePanelLeave = () => {
  hoveringPanel.value = false
  closeIfNeeded()
}

const handleIconClick = () => {
  pinned.value = !pinned.value
  visible.value = pinned.value || visible.value
  if (!pinned.value) {
    closeIfNeeded()
  }
}
</script>

<style scoped>
.formula-header {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  line-height: 1.2;
}

.formula-header__label {
  white-space: nowrap;
}

.formula-header__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #ecf5ff;
  color: #409eff;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  user-select: none;
}

.formula-header__panel-title {
  margin-bottom: 6px;
  color: #303133;
  font-weight: 600;
}

.formula-header__panel-text {
  color: #606266;
  line-height: 1.6;
  white-space: pre-wrap;
  word-break: break-word;
}
</style>
