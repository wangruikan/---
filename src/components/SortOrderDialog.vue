<template>
  <el-dialog
    :model-value="modelValue"
    :title="title"
    width="620px"
    :close-on-click-modal="false"
    @update:model-value="value => emit('update:modelValue', value)"
  >
    <el-alert
      type="info"
      :closable="false"
      title="调整后点击保存，列表和对应下拉选项都会按新顺序显示。"
      style="margin-bottom: 16px"
    />

    <el-empty v-if="workingItems.length === 0" description="暂无可排序数据" />
    <div v-else class="sort-list">
      <div v-for="(item, index) in workingItems" :key="item.id" class="sort-item">
        <span class="sort-index">{{ index + 1 }}</span>
        <div class="sort-item-content">
          <span class="sort-item-label">{{ getLabel(item) }}</span>
          <span v-if="getDescription(item)" class="sort-item-description">
            {{ getDescription(item) }}
          </span>
        </div>
        <div class="sort-item-actions">
          <el-button
            link
            circle
            :icon="ArrowUp"
            :disabled="index === 0"
            title="上移"
            aria-label="上移"
            @click="moveItem(index, -1)"
          />
          <el-button
            link
            circle
            :icon="ArrowDown"
            :disabled="index === workingItems.length - 1"
            title="下移"
            aria-label="下移"
            @click="moveItem(index, 1)"
          />
        </div>
      </div>
    </div>

    <template #footer>
      <el-button @click="emit('update:modelValue', false)">取消</el-button>
      <el-button type="primary" :loading="loading" :disabled="workingItems.length === 0" @click="handleSave">
        保存顺序
      </el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, watch } from 'vue'
import { ArrowDown, ArrowUp } from '@element-plus/icons-vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: '调整顺序' },
  items: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  getLabel: { type: Function, default: item => item.project_name || '-' },
  getDescription: { type: Function, default: () => '' },
})

const emit = defineEmits(['update:modelValue', 'save'])
const workingItems = ref([])

const syncItems = () => {
  workingItems.value = props.items.map(item => ({ ...item }))
}

watch(() => props.modelValue, value => {
  if (value) syncItems()
})

watch(() => props.items, () => {
  if (props.modelValue) syncItems()
}, { deep: true })

const moveItem = (index, offset) => {
  const targetIndex = index + offset
  if (targetIndex < 0 || targetIndex >= workingItems.value.length) return

  const nextItems = [...workingItems.value]
  const [item] = nextItems.splice(index, 1)
  nextItems.splice(targetIndex, 0, item)
  workingItems.value = nextItems
}

const handleSave = () => {
  emit('save', workingItems.value.map((item, index) => ({
    id: item.id,
    sort_order: index + 1,
  })))
}
</script>

<style scoped>
.sort-list {
  max-height: 460px;
  overflow-y: auto;
  border: 1px solid #dcdfe6;
  border-radius: 4px;
}

.sort-item {
  display: flex;
  align-items: center;
  min-height: 52px;
  padding: 8px 12px;
  border-bottom: 1px solid #ebeef5;
}

.sort-item:last-child {
  border-bottom: 0;
}

.sort-index {
  width: 32px;
  color: #909399;
  text-align: center;
}

.sort-item-content {
  display: flex;
  flex: 1;
  min-width: 0;
  flex-direction: column;
  gap: 3px;
  padding: 0 12px;
}

.sort-item-label {
  overflow: hidden;
  color: #303133;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.sort-item-description {
  color: #909399;
  font-size: 12px;
}

.sort-item-actions {
  display: flex;
  gap: 4px;
}
</style>
