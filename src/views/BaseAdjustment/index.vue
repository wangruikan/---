<template>
  <div class="base-adjustment-container">
    <div class="page-header">
      <h2>基数调差管理</h2>
    </div>

    <el-alert
      v-if="permissionInfo.message"
      :title="permissionInfo.message"
      :type="permissionInfo.allowed ? 'success' : 'warning'"
      :closable="false"
      class="permission-alert"
    />

    <el-card class="filter-card">
      <el-form :model="filterForm" :inline="true">
        <el-form-item label="员工姓名">
          <el-input
            v-model="filterForm.employee_name"
            placeholder="请输入员工姓名"
            clearable
            style="width: 220px"
          />
        </el-form-item>

        <el-form-item label="项目">
          <el-select v-model="filterForm.project_id" placeholder="请选择项目" clearable style="width: 220px">
            <el-option v-for="option in projectOptions" :key="option.id" :label="option.name" :value="option.id" />
          </el-select>
        </el-form-item>

        <el-form-item>
          <el-button type="primary" @click="loadEmployees">
            <el-icon><Search /></el-icon>
            查询
          </el-button>
          <el-button @click="resetFilter">
            <el-icon><Refresh /></el-icon>
            重置
          </el-button>
          <el-button type="success" @click="handleRefresh">
            <el-icon><RefreshRight /></el-icon>
            刷新
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card">
      <template #header>
        <div class="card-header">
          <span>在职员工列表（共 {{ employees.length }} 人）</span>
          <el-tag type="info" size="small">按险种独立调基</el-tag>
        </div>
      </template>

      <el-table
        v-loading="loading"
        :data="employees"
        border
        stripe
        style="width: 100%"
        :height="620"
      >
        <el-table-column type="index" label="序号" width="60" align="center" />
        <el-table-column prop="employee_name" label="员工姓名" width="120" fixed="left" />
        <el-table-column prop="id_number" label="身份证号" width="180" />
        <el-table-column prop="project_name" label="所属项目" width="160" />

        <el-table-column
          v-for="type in adjustmentTypes"
          :key="type.key"
          :label="type.label"
          :width="type.width"
          align="center"
        >
          <template #default="{ row }">
            <div class="adjustment-card">
              <div class="adjustment-row">
                <span class="adjustment-label">当前</span>
                <span class="adjustment-value">{{ getCurrentDisplay(row, type.key) }}</span>
              </div>

              <template v-if="getAdjustmentItem(row, type.key)">
                <div class="adjustment-row">
                  <span class="adjustment-label">调整后</span>
                  <span class="adjustment-value target">{{ getTargetDisplay(getAdjustmentItem(row, type.key), type.key) }}</span>
                </div>
                <div class="adjustment-row">
                  <span class="adjustment-label">生效时间</span>
                  <span>{{ formatDate(getAdjustmentItem(row, type.key).effective_date) }}</span>
                </div>
                <div class="adjustment-row status-row">
                  <el-tag size="small" :type="getStatusTagType(getAdjustmentItem(row, type.key).status)">
                    {{ getStatusText(getAdjustmentItem(row, type.key).status) }}
                  </el-tag>
                  <span v-if="getAdjustmentItem(row, type.key).is_legacy_mixed" class="legacy-mark">历史混合记录</span>
                </div>
              </template>

              <div v-else class="adjustment-empty">暂无调基记录</div>

              <div v-if="type.key === 'large_medical' && row.large_medical_base_source === 'config'" class="adjustment-tip">
                特殊地区请前往大额医疗保险管理调整
              </div>

              <div class="adjustment-actions">
                <el-button
                  type="primary"
                  size="small"
                  :disabled="!permissionInfo.allowed || isTypeReadonly(row, type.key)"
                  @click="openAdjustDialog(row, type.key)"
                >
                  <el-icon><Edit /></el-icon>
                  {{ getActionText(row, type.key) }}
                </el-button>
                <el-button
                  v-if="canDelete(row, type.key)"
                  type="danger"
                  size="small"
                  @click="handleDelete(row, type.key)"
                >
                  <el-icon><Delete /></el-icon>
                  删除
                </el-button>
              </div>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="操作" width="100" fixed="right" align="center">
          <template #default="{ row }">
            <el-button type="info" size="small" @click="handleViewHistory(row)">
              <el-icon><Document /></el-icon>
              历史
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog
      v-model="showAdjustDialog"
      :title="dialogTitle"
      width="560px"
      :close-on-click-modal="false"
    >
      <el-alert type="info" :closable="false" class="dialog-alert">
        <template #default>
          <p>当前基数：{{ currentBaseDisplay }}</p>
          <p>调整记录会按当前险种独立保存，不会再和其他险种共用一条状态。</p>
        </template>
      </el-alert>

      <el-form label-width="110px">
        <el-form-item :label="dialogBaseLabel">
          <el-input-number
            v-model="adjustForm.new_base"
            :min="0"
            :precision="2"
            controls-position="right"
            style="width: 100%"
            placeholder="请输入调整后的基数"
          />
        </el-form-item>

        <el-form-item v-if="currentAdjustmentType === 'large_medical'" label="公司基数">
          <el-input-number
            v-model="adjustForm.new_company_base"
            :min="0"
            :precision="2"
            controls-position="right"
            style="width: 100%"
            placeholder="请输入调整后的公司基数"
          />
        </el-form-item>

        <el-form-item label="生效时间">
          <el-date-picker
            v-model="adjustForm.effective_date"
            type="date"
            placeholder="请选择生效时间"
            format="YYYY-MM-DD"
            value-format="YYYY-MM-DD"
            :disabled-date="disabledDate"
            style="width: 100%"
          />
        </el-form-item>

        <el-form-item label="调整原因">
          <el-input
            v-model="adjustForm.adjustment_reason"
            type="textarea"
            :rows="3"
            maxlength="500"
            show-word-limit
            placeholder="请输入调整原因（可选）"
          />
        </el-form-item>
      </el-form>

      <template #footer>
        <div class="dialog-footer">
          <el-button @click="showAdjustDialog = false">取消</el-button>
          <el-button type="primary" :loading="submitting" @click="handleSubmit">保存</el-button>
        </div>
      </template>
    </el-dialog>

    <el-dialog
      v-model="showHistoryDialog"
      :title="`调基历史 - ${currentEmployee?.employee_name || ''}`"
      width="980px"
      :close-on-click-modal="false"
    >
      <el-table v-loading="historyLoading" :data="historyRecords" border stripe style="width: 100%" max-height="560">
        <el-table-column prop="adjustment_type_label" label="险种" width="120" align="center" />
        <el-table-column label="调整前" width="180" align="center">
          <template #default="{ row }">
            {{ getHistoryBaseDisplay(row, 'old') }}
          </template>
        </el-table-column>
        <el-table-column label="调整后" width="180" align="center">
          <template #default="{ row }">
            {{ getHistoryBaseDisplay(row, 'new') }}
          </template>
        </el-table-column>
        <el-table-column prop="effective_date" label="生效时间" width="120" align="center">
          <template #default="{ row }">
            {{ formatDate(row.effective_date) }}
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag size="small" :type="getStatusTagType(row.status)">
              {{ getStatusText(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="adjustment_reason" label="调整原因" min-width="180" show-overflow-tooltip />
        <el-table-column prop="creator_name" label="创建人" width="110" align="center" />
        <el-table-column prop="created_at" label="创建时间" width="160" align="center">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column prop="applied_at" label="实际生效时间" width="160" align="center">
          <template #default="{ row }">
            {{ formatDateTime(row.applied_at) }}
          </template>
        </el-table-column>
      </el-table>

      <template #footer>
        <div class="dialog-footer">
          <el-button @click="showHistoryDialog = false">关闭</el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Refresh, RefreshRight, Edit, Check, Delete, Document } from '@element-plus/icons-vue'
import { useAccountSetStore } from '@/stores/accountSet'
import request from '@/api/request'

const accountSetStore = useAccountSetStore()

const adjustmentTypes = [
  { key: 'social_security', label: '社保基数', width: 260 },
  { key: 'medical_insurance', label: '医保基数', width: 260 },
  { key: 'housing_fund', label: '公积金基数', width: 260 },
  { key: 'large_medical', label: '大额医疗基数', width: 280 }
]

const typeMetaMap = {
  social_security: { label: '社保', currentField: 'current_social_security_base' },
  medical_insurance: { label: '医保', currentField: 'current_medical_insurance_base' },
  housing_fund: { label: '公积金', currentField: 'current_housing_fund_base' },
  large_medical: { label: '大额医疗', currentField: 'current_large_medical_base' }
}

const loading = ref(false)
const employees = ref([])
const permissionInfo = ref({ allowed: false, message: '' })

const filterForm = reactive({
  employee_name: '',
  project_id: ''
})

const showAdjustDialog = ref(false)
const submitting = ref(false)
const currentEmployee = ref(null)
const currentAdjustmentType = ref('social_security')
const adjustForm = reactive({
  adjustment_id: null,
  employee_id: null,
  new_base: null,
  new_company_base: null,
  effective_date: '',
  adjustment_reason: ''
})

const showHistoryDialog = ref(false)
const historyLoading = ref(false)
const historyRecords = ref([])

const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)
const effectiveAccountSetId = computed(() => {
  if (currentAccountSetId.value) {
    return currentAccountSetId.value
  }
  const stored = localStorage.getItem('current_account_set_id')
  if (!stored) {
    return null
  }
  const parsed = parseInt(stored, 10)
  return Number.isNaN(parsed) ? null : parsed
})

const projectOptions = computed(() => {
  const map = new Map()
  employees.value.forEach((item) => {
    if (item.project_id && item.project_name && !map.has(item.project_id)) {
      map.set(item.project_id, { id: item.project_id, name: item.project_name })
    }
  })
  return Array.from(map.values())
})

const currentTypeMeta = computed(() => typeMetaMap[currentAdjustmentType.value])
const currentPendingItem = computed(() => {
  if (!currentEmployee.value) {
    return null
  }
  const item = getAdjustmentItem(currentEmployee.value, currentAdjustmentType.value)
  return item && item.status === 'pending' ? item : null
})
const dialogTitle = computed(() => {
  const employeeName = currentEmployee.value?.employee_name || ''
  return `调整${currentTypeMeta.value.label}基数 - ${employeeName}`
})
const dialogBaseLabel = computed(() => (
  currentAdjustmentType.value === 'large_medical'
    ? '个人基数'
    : `${currentTypeMeta.value.label}基数`
))
const currentBaseDisplay = computed(() => {
  if (!currentEmployee.value) {
    return '-'
  }
  return getCurrentDisplay(currentEmployee.value, currentAdjustmentType.value)
})

const hasValue = (value) => value !== null && value !== undefined && value !== ''

const getAdjustmentItem = (row, type) => row.adjustments?.[type] || null

const getCurrentDisplay = (row, type) => {
  if (type === 'large_medical') {
    return formatBaseDisplay(row.current_large_medical_base, row.current_large_medical_company_base)
  }
  const field = typeMetaMap[type]?.currentField
  return formatMoney(row[field] ?? 0)
}

const getTargetDisplay = (item, type) => {
  if (!item) {
    return '-'
  }
  if (type === 'large_medical') {
    return formatBaseDisplay(item.new_base, item.new_company_base)
  }
  return formatMoney(item.new_base)
}

const getHistoryBaseDisplay = (item, stage) => {
  const baseField = stage === 'old' ? item.old_base : item.new_base
  const companyField = stage === 'old' ? item.old_company_base : item.new_company_base
  return item.adjustment_type === 'large_medical'
    ? formatBaseDisplay(baseField, companyField)
    : formatMoney(baseField)
}

const formatBaseDisplay = (base, companyBase) => {
  const baseText = hasValue(base) ? `个人 ${formatMoney(base)}` : ''
  const companyText = hasValue(companyBase) ? `公司 ${formatMoney(companyBase)}` : ''
  if (baseText && companyText) {
    return `${baseText} / ${companyText}`
  }
  return baseText || companyText || '-'
}

const formatMoney = (value) => {
  if (!hasValue(value)) {
    return '¥0.00'
  }
  const number = Number(value)
  return `¥${Number.isNaN(number) ? 0 : number.toFixed(2)}`
}

const getStatusText = (status) => {
  if (status === 'pending') return '待生效'
  if (status === 'applied') return '已生效'
  return status || '-'
}

const getStatusTagType = (status) => {
  if (status === 'pending') return 'warning'
  if (status === 'applied') return 'success'
  return 'info'
}

const getActionText = (row, type) => {
  const item = getAdjustmentItem(row, type)
  return item && item.status === 'pending' ? '修改' : '调基'
}

const isTypeReadonly = (row, type) => type === 'large_medical' && row.large_medical_base_source === 'config'

const canApplyNow = (row, type) => {
  const item = getAdjustmentItem(row, type)
  return !!item && item.status === 'pending'
}

const canDelete = (row, type) => {
  const item = getAdjustmentItem(row, type)
  return !!item && item.status === 'pending'
}

const checkPermission = async () => {
  const accountSetId = effectiveAccountSetId.value
  if (!accountSetId) {
    permissionInfo.value = { allowed: false, message: '请先选择账套' }
    return
  }

  try {
    const response = await request.get('/base-adjustments/check-permission', {
      params: { account_set_id: accountSetId }
    })
    if (response.success) {
      permissionInfo.value = response.data
    }
  } catch (error) {
    console.error('检查权限失败:', error)
  }
}

const loadEmployees = async () => {
  const accountSetId = effectiveAccountSetId.value
  if (!accountSetId) {
    ElMessage.warning('请先选择账套')
    return
  }

  loading.value = true
  try {
    const response = await request.get('/base-adjustments', {
      params: {
        account_set_id: accountSetId,
        ...filterForm
      }
    })
    employees.value = Array.isArray(response.data) ? response.data : []
  } catch (error) {
    employees.value = []
    console.error('加载员工列表失败:', error)
    ElMessage.error(error.response?.data?.message || '加载失败')
  } finally {
    loading.value = false
  }
}

const resetFilter = () => {
  filterForm.employee_name = ''
  filterForm.project_id = ''
  loadEmployees()
}

const handleRefresh = () => {
  checkPermission()
  loadEmployees()
}

const openAdjustDialog = (row, type) => {
  if (!permissionInfo.value.allowed) {
    ElMessage.warning(permissionInfo.value.message)
    return
  }

  if (isTypeReadonly(row, type)) {
    ElMessage.warning('该员工所属特殊地区，大额医疗请前往大额医疗保险管理中调整')
    return
  }

  currentEmployee.value = row
  currentAdjustmentType.value = type

  const pendingItem = getAdjustmentItem(row, type)
  if (pendingItem && pendingItem.status === 'pending') {
    adjustForm.adjustment_id = pendingItem.id
    adjustForm.new_base = pendingItem.new_base
    adjustForm.new_company_base = type === 'large_medical' ? pendingItem.new_company_base : null
    adjustForm.effective_date = pendingItem.effective_date || ''
    adjustForm.adjustment_reason = pendingItem.adjustment_reason || ''
  } else {
    adjustForm.adjustment_id = null
    adjustForm.new_base = null
    adjustForm.new_company_base = null
    adjustForm.effective_date = ''
    adjustForm.adjustment_reason = ''
  }
  adjustForm.employee_id = row.employee_id

  showAdjustDialog.value = true
}

const handleSubmit = async () => {
  if (!currentEmployee.value) {
    return
  }

  if (currentAdjustmentType.value === 'large_medical') {
    if (!hasValue(adjustForm.new_base) && !hasValue(adjustForm.new_company_base)) {
      ElMessage.warning('请至少填写一个大额医疗调整值')
      return
    }
  } else if (!hasValue(adjustForm.new_base)) {
    ElMessage.warning('请输入调整后的基数')
    return
  }

  if (!adjustForm.effective_date) {
    ElMessage.warning('请选择生效时间')
    return
  }

  submitting.value = true
  try {
    const data = {
      adjustment_id: adjustForm.adjustment_id,
      employee_id: adjustForm.employee_id,
      account_set_id: effectiveAccountSetId.value,
      adjustment_type: currentAdjustmentType.value,
      effective_date: adjustForm.effective_date,
      adjustment_reason: adjustForm.adjustment_reason
    }

    if (hasValue(adjustForm.new_base)) {
      data.new_base = adjustForm.new_base
    }
    if (hasValue(adjustForm.new_company_base)) {
      data.new_company_base = adjustForm.new_company_base
    }

    const response = await request.post('/base-adjustments', data)
    ElMessage.success(response.message || '保存成功')
    showAdjustDialog.value = false
    await loadEmployees()
  } catch (error) {
    console.error('保存调基失败:', error)
    ElMessage.error(error.response?.data?.message || '保存失败')
  } finally {
    submitting.value = false
  }
}

const handleApplyNow = async (row, type) => {
  const item = getAdjustmentItem(row, type)
  if (!item) {
    return
  }

  try {
    await ElMessageBox.confirm(
      `确定要让 ${row.employee_name} 的${typeMetaMap[type].label}基数立即生效吗？`,
      '确认操作',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    const response = await request.post(`/base-adjustments/${item.id}/apply-now`, {
      adjustment_type: type
    })

    ElMessage.success(response.message || '已立即生效')
    await loadEmployees()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('立即生效失败:', error)
      ElMessage.error(error.response?.data?.message || '操作失败')
    }
  }
}

const handleDelete = async (row, type) => {
  const item = getAdjustmentItem(row, type)
  if (!item) {
    return
  }

  try {
    await ElMessageBox.confirm(
      `确定要删除 ${row.employee_name} 的${typeMetaMap[type].label}调基记录吗？`,
      '确认删除',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    const response = await request.delete(`/base-adjustments/${item.id}`, {
      data: {
        adjustment_type: type
      }
    })

    ElMessage.success(response.message || '删除成功')
    await loadEmployees()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除调基失败:', error)
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }
}

const handleViewHistory = async (row) => {
  currentEmployee.value = row
  showHistoryDialog.value = true
  historyLoading.value = true

  try {
    const response = await request.get(`/base-adjustments/employee/${row.employee_id}/history`, {
      params: {
        account_set_id: effectiveAccountSetId.value
      }
    })
    historyRecords.value = Array.isArray(response.data) ? response.data : []
  } catch (error) {
    console.error('加载调基历史失败:', error)
    ElMessage.error(error.response?.data?.message || '加载历史失败')
  } finally {
    historyLoading.value = false
  }
}

const disabledDate = (time) => time.getTime() < Date.now() - 8.64e7

const formatDate = (dateString) => {
  if (!dateString) return '-'
  if (/^\d{4}-\d{2}-\d{2}$/.test(dateString)) return dateString
  const date = new Date(dateString)
  if (Number.isNaN(date.getTime())) return dateString
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const formatDateTime = (dateTimeString) => {
  if (!dateTimeString) return '-'
  if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(dateTimeString)) return dateTimeString
  const isoMatch = dateTimeString.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})/)
  if (isoMatch) {
    return `${isoMatch[1]}-${isoMatch[2]}-${isoMatch[3]} ${isoMatch[4]}:${isoMatch[5]}:${isoMatch[6]}`
  }
  const date = new Date(dateTimeString)
  if (Number.isNaN(date.getTime())) return dateTimeString
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  const seconds = String(date.getSeconds()).padStart(2, '0')
  return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`
}

watch(effectiveAccountSetId, (id) => {
  if (id) {
    checkPermission()
    loadEmployees()
    return
  }
  employees.value = []
  permissionInfo.value = { allowed: false, message: '请先选择账套' }
}, { immediate: true })

onMounted(() => {
  if (!effectiveAccountSetId.value) {
    permissionInfo.value = { allowed: false, message: '请先选择账套' }
  }
})
</script>

<style scoped>
.base-adjustment-container {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  font-size: 24px;
  color: #303133;
}

.permission-alert {
  margin-bottom: 20px;
}

.filter-card {
  margin-bottom: 20px;
}

.table-card {
  margin-top: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.adjustment-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: stretch;
  text-align: left;
}

.adjustment-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  font-size: 13px;
}

.adjustment-label {
  color: #909399;
  white-space: nowrap;
}

.adjustment-value {
  font-weight: 600;
  color: #303133;
}

.adjustment-value.target {
  color: #e6a23c;
}

.adjustment-empty {
  color: #c0c4cc;
  font-size: 13px;
}

.status-row {
  gap: 8px;
}

.legacy-mark {
  color: #909399;
  font-size: 12px;
}

.adjustment-tip {
  color: #909399;
  font-size: 12px;
  line-height: 1.5;
}

.adjustment-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.dialog-alert {
  margin-bottom: 20px;
}

.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
</style>
