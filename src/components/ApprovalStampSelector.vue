<template>
  <div class="approval-stamp-selector">
    <el-row :gutter="12">
      <el-col :span="12">
        <el-form-item :label="companyLabel" :required="required">
          <el-select
            v-model="localCompany"
            :disabled="disabled || loading"
            :loading="loading"
            filterable
            clearable
            placeholder="请选择公司"
            style="width: 100%;"
            @change="handleCompanyChange"
            @clear="handleCompanyClear"
          >
            <el-option
              v-for="company in companyOptions"
              :key="company.name"
              :label="`${company.name}（${company.count}枚）`"
              :value="company.name"
            />
          </el-select>
        </el-form-item>
      </el-col>

      <el-col :span="12">
        <el-form-item :label="typeLabel" :required="required">
          <el-select
            v-model="localType"
            :disabled="disabled || loading || !localCompany"
            filterable
            clearable
            placeholder="请选择印章类型"
            style="width: 100%;"
            @change="handleTypeChange"
            @clear="handleTypeClear"
          >
            <el-option
              v-for="type in typeOptions"
              :key="type.type"
              :label="type.title"
              :value="type.type"
            >
              <span>{{ type.title }}</span>
              <span class="stamp-option-name">{{ type.stamp.name || '-' }}</span>
            </el-option>
          </el-select>
        </el-form-item>
      </el-col>
    </el-row>

    <div v-if="showSummary && selectedStamp" class="stamp-summary">
      <img
        v-if="selectedStamp.image_url"
        :src="selectedStamp.image_url"
        :alt="selectedStamp.name"
        class="stamp-summary-image"
      />
      <div class="stamp-summary-info">
        <div class="stamp-summary-title">
          {{ selectedStamp.name || getStampTypeTitle(selectedStamp.type) }}
        </div>
        <div class="stamp-summary-meta">
          {{ selectedStamp.company }} / {{ getStampTypeTitle(selectedStamp.type) }}
        </div>
      </div>
    </div>

    <el-alert
      v-else-if="!loading && !hasAvailableStamps"
      type="warning"
      :closable="false"
      title="当前账套还没有可用于审批的公司印章，请先到签名印章管理中上传。"
      show-icon
    />

    <el-alert
      v-else-if="errorMessage"
      type="error"
      :closable="false"
      :title="errorMessage"
      show-icon
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { getBankStamps } from '@/api/signatures'

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({})
  },
  required: {
    type: Boolean,
    default: true
  },
  disabled: {
    type: Boolean,
    default: false
  },
  showSummary: {
    type: Boolean,
    default: true
  },
  companyLabel: {
    type: String,
    default: '用章公司'
  },
  typeLabel: {
    type: String,
    default: '印章类型'
  },
  allowedTypes: {
    type: Array,
    default: () => []
  },
  autoSelectFirstCompany: {
    type: Boolean,
    default: false
  },
  autoSelectFirstType: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'change', 'loaded'])

const fixedStampTypes = [
  { type: 'bank', title: '银行付讫章' },
  { type: 'cash', title: '现金付讫章' },
  { type: 'official', title: '公章' },
  { type: 'finance', title: '财务专用章' },
  { type: 'contract', title: '合同专用章' },
  { type: 'legal_person', title: '法人章' },
  { type: 'business', title: '业务专用章' },
  { type: 'hr', title: '人事部专用章' }
]

const localCompany = ref('')
const localType = ref('')
const stamps = ref([])
const loading = ref(false)
const errorMessage = ref('')

const allowedTypeSet = computed(() => {
  return props.allowedTypes.length > 0 ? new Set(props.allowedTypes) : null
})

const availableStamps = computed(() => {
  return stamps.value.filter(stamp => {
    const company = String(stamp.company || '').trim()
    if (!company) return false
    if (allowedTypeSet.value && !allowedTypeSet.value.has(stamp.type)) return false
    return true
  })
})

const companyOptions = computed(() => {
  const companyMap = new Map()

  availableStamps.value.forEach(stamp => {
    const company = String(stamp.company || '').trim()
    companyMap.set(company, (companyMap.get(company) || 0) + 1)
  })

  return Array.from(companyMap.entries())
    .map(([name, count]) => ({ name, count }))
    .sort((a, b) => a.name.localeCompare(b.name, 'zh-CN'))
})

const typeOptions = computed(() => {
  if (!localCompany.value) return []

  return fixedStampTypes
    .filter(typeConfig => !allowedTypeSet.value || allowedTypeSet.value.has(typeConfig.type))
    .map(typeConfig => {
      const stamp = availableStamps.value.find(item => (
        String(item.company || '').trim() === localCompany.value
        && item.type === typeConfig.type
      ))

      return stamp ? { ...typeConfig, stamp } : null
    })
    .filter(Boolean)
})

const selectedStamp = computed(() => {
  if (!localCompany.value || !localType.value) return null

  return availableStamps.value.find(stamp => (
    String(stamp.company || '').trim() === localCompany.value
    && stamp.type === localType.value
  )) || null
})

const hasAvailableStamps = computed(() => availableStamps.value.length > 0)

const getStampTypeTitle = (type) => {
  return fixedStampTypes.find(item => item.type === type)?.title || '印章'
}

const buildValue = () => {
  const stamp = selectedStamp.value

  return {
    ...props.modelValue,
    stamp_company: localCompany.value || '',
    stamp_type: localType.value || '',
    stamp_id: stamp?.id || null,
    stamp_name: stamp?.name || ''
  }
}

const emitValue = () => {
  const value = buildValue()
  emit('update:modelValue', value)
  emit('change', value)
}

const ensureAvailableSelection = () => {
  const companies = companyOptions.value.map(item => item.name)

  if (localCompany.value && !companies.includes(localCompany.value)) {
    localCompany.value = ''
    localType.value = ''
    emitValue()
    return
  }

  if (!localCompany.value && props.autoSelectFirstCompany && companyOptions.value.length > 0) {
    localCompany.value = companyOptions.value[0].name
  }

  const types = typeOptions.value.map(item => item.type)

  if (localType.value && !types.includes(localType.value)) {
    localType.value = ''
  }

  if (localCompany.value && !localType.value && props.autoSelectFirstType && typeOptions.value.length > 0) {
    localType.value = typeOptions.value[0].type
  }

  emitValue()
}

const loadStamps = async () => {
  loading.value = true
  errorMessage.value = ''

  try {
    const response = await getBankStamps()
    stamps.value = response.success && Array.isArray(response.data) ? response.data : []
    emit('loaded', stamps.value)
    ensureAvailableSelection()
  } catch (error) {
    console.error('加载公司印章失败:', error)
    errorMessage.value = error.response?.data?.message || '加载公司印章失败'
  } finally {
    loading.value = false
  }
}

const handleCompanyChange = () => {
  const nextTypes = typeOptions.value.map(item => item.type)
  if (!nextTypes.includes(localType.value)) {
    localType.value = ''
  }

  if (!localType.value && props.autoSelectFirstType && typeOptions.value.length > 0) {
    localType.value = typeOptions.value[0].type
  }

  emitValue()
}

const handleCompanyClear = () => {
  localCompany.value = ''
  localType.value = ''
  emitValue()
}

const handleTypeChange = () => {
  emitValue()
}

const handleTypeClear = () => {
  localType.value = ''
  emitValue()
}

const validate = () => {
  if (!props.required) {
    return { valid: true, message: '', value: buildValue() }
  }

  if (!localCompany.value) {
    return { valid: false, message: '请选择用章公司', value: buildValue() }
  }

  if (!localType.value) {
    return { valid: false, message: '请选择印章类型', value: buildValue() }
  }

  if (!selectedStamp.value) {
    return { valid: false, message: '所选公司没有上传该类型印章', value: buildValue() }
  }

  return { valid: true, message: '', value: buildValue() }
}

const getValue = () => buildValue()

const reload = () => loadStamps()

watch(
  () => props.modelValue,
  (value) => {
    localCompany.value = value?.stamp_company || ''
    localType.value = value?.stamp_type || ''
  },
  { immediate: true, deep: true }
)

onMounted(() => {
  loadStamps()
})

defineExpose({
  validate,
  getValue,
  reload
})
</script>

<style scoped>
.approval-stamp-selector {
  width: 100%;
}

.stamp-option-name {
  float: right;
  color: #909399;
  font-size: 12px;
}

.stamp-summary {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border: 1px solid #ebeef5;
  border-radius: 6px;
  background: #fafafa;
}

.stamp-summary-image {
  width: 72px;
  height: 40px;
  object-fit: contain;
  border: 1px solid #ebeef5;
  border-radius: 4px;
  background: #fff;
}

.stamp-summary-info {
  min-width: 0;
}

.stamp-summary-title {
  color: #303133;
  font-weight: 500;
}

.stamp-summary-meta {
  margin-top: 4px;
  color: #909399;
  font-size: 12px;
}
</style>
