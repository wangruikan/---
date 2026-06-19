<template>
  <div class="employee-special-deductions-page">
    <div class="page-header">
      <h1>人员专项管理</h1>
    </div>

    <el-card>
      <div class="toolbar">
        <el-form :model="employeeSearchForm" inline>
          <el-form-item label="月份">
            <el-date-picker
              v-model="employeeSearchForm.month"
              type="month"
              value-format="YYYY-MM"
              placeholder="请选择月份"
              style="width: 160px"
              @change="handleEmployeeMonthChange"
            />
          </el-form-item>

          <el-form-item label="项目">
            <el-select
              v-model="employeeSearchForm.project_id"
              placeholder="请选择项目"
              clearable
              style="width: 200px"
              @change="handleProjectChange"
            >
              <el-option
                v-for="project in safeProjects.filter(p => p)"
                :key="project.id"
                :label="project.name"
                :value="project.id"
              />
            </el-select>
          </el-form-item>

          <el-form-item label="员工">
            <el-input
              v-model="employeeSearchForm.search"
              placeholder="请输入员工姓名或身份证号"
              clearable
              style="width: 240px"
              @keyup.enter="loadEmployeeDeductions"
            />
          </el-form-item>

          <el-form-item>
            <el-button type="primary" @click="loadEmployeeDeductions">
              <el-icon><Search /></el-icon>
              搜索
            </el-button>
            <el-button type="success" @click="handleDownloadImportTemplate">
              <el-icon><Download /></el-icon>
              下载导入模板
            </el-button>
            <el-button type="success" :loading="importing" @click="triggerImportTemplate">
              <el-icon><Upload /></el-icon>
              导入模板
            </el-button>
          </el-form-item>
        </el-form>
        <input
          ref="importFileInputRef"
          class="hidden-file-input"
          type="file"
          accept=".xlsx,.xls"
          @change="handleImportFileChange"
        />
      </div>

      <el-table
        :data="safeEmployeeDeductions"
        v-loading="employeesLoading"
        stripe
        border
        style="margin-top: 20px"
      >
        <el-table-column label="序号" type="index" width="70" :index="getEmployeeRowIndex" />
        <el-table-column prop="employee_name" label="员工姓名" width="120" />
        <el-table-column prop="id_number" label="身份证号" width="180" />
        <el-table-column prop="project_name" label="所属项目" width="150" />
        <el-table-column label="专项扣除项目" min-width="240">
          <template #default="{ row }">
            <template v-if="row.special_deduction_items && row.special_deduction_items.length">
              <el-tag
                v-for="item in row.special_deduction_items"
                :key="item.id"
                type="info"
                effect="plain"
                style="margin: 2px 6px 2px 0"
              >
                {{ item.name }}：¥{{ formatAmount(item.amount) }}
              </el-tag>
            </template>
            <span v-else class="text-muted">未设置</span>
          </template>
        </el-table-column>
        <el-table-column label="其他扣除项目" min-width="240">
          <template #default="{ row }">
            <template v-if="row.other_deduction_items && row.other_deduction_items.length">
              <el-tag
                v-for="item in row.other_deduction_items"
                :key="item.id"
                type="warning"
                effect="plain"
                style="margin: 2px 6px 2px 0"
              >
                {{ item.name }}：¥{{ formatAmount(item.amount) }}
              </el-tag>
            </template>
            <span v-else class="text-muted">未设置</span>
          </template>
        </el-table-column>
        <el-table-column prop="total_amount" label="总扣除金额" width="120">
          <template #default="{ row }">
            ¥{{ row.total_amount || '0.00' }}
          </template>
        </el-table-column>
        <el-table-column prop="is_active" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.has_deduction ? 'success' : 'warning'">
              {{ row.has_deduction ? '已设置' : '未设置' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleSetEmployee(row)">
              设置
            </el-button>
            <el-button
              v-if="row.has_deduction"
              link
              type="danger"
              @click="handleDeleteEmployee(row)"
            >
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination">
        <el-pagination
          v-model:current-page="employeePagination.currentPage"
          v-model:page-size="employeePagination.pageSize"
          :page-sizes="[10, 20, 50, 100]"
          :total="employeePagination.total"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="loadEmployeeDeductions"
          @current-change="loadEmployeeDeductions"
        />
      </div>
    </el-card>

    <el-dialog
      v-model="showSetEmployeeDialog"
      title="设置员工扣除"
      width="700px"
      @close="handleEmployeeDialogClose"
    >
      <el-form
        ref="employeeFormRef"
        :model="employeeForm"
        :rules="employeeFormRules"
        label-width="120px"
      >
        <el-form-item label="员工姓名">
          <el-input v-model="currentEmployee.name" disabled />
        </el-form-item>

        <el-form-item label="所属项目">
          <el-input v-model="currentEmployee.project_name" disabled />
        </el-form-item>

        <el-form-item label="扣除项目" prop="deduction_items" required>
          <div style="width: 100%">
            <div
              v-for="(item, index) in employeeForm.deduction_items"
              :key="index"
              style="display: flex; align-items: center; margin-bottom: 10px"
            >
              <el-select
                v-model="item.id"
                placeholder="请选择扣除项目"
                style="flex: 1; margin-right: 10px"
                @change="handleDeductionItemChange(index)"
              >
                <el-option
                  v-for="deductionItem in safeAvailableDeductionItems.filter(d => d)"
                  :key="deductionItem.id"
                  :label="formatDeductionItemOption(deductionItem)"
                  :value="deductionItem.id"
                />
              </el-select>
              <el-input-number
                v-model="item.amount"
                :min="0"
                :precision="2"
                placeholder="金额"
                style="width: 150px; margin-right: 10px"
              />
              <el-button
                type="danger"
                :icon="Delete"
                circle
                @click="removeDeductionItem(index)"
              />
            </div>
            <el-button
              type="primary"
              :icon="Plus"
              @click="addDeductionItem"
              style="width: 100%"
            >
              添加扣除项目
            </el-button>
          </div>
        </el-form-item>

        <el-form-item label="总扣除金额">
          <el-input :value="totalDeductionAmount" disabled>
            <template #prepend>¥</template>
          </el-input>
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="showSetEmployeeDialog = false">取消</el-button>
        <el-button type="primary" @click="handleEmployeeSubmit" :loading="employeeSubmitting">
          保存
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Plus, Delete, Download, Upload } from '@element-plus/icons-vue'
import * as XLSX from 'xlsx'
import { useAccountSetStore } from '@/stores/accountSet'
import {
  getDeductionItems,
  getEmployeeDeductions,
  setEmployeeDeduction,
  importEmployeeDeductions,
  deleteEmployeeDeduction
} from '@/api/specialDeductions'
import { getProjects } from '@/api/projects'

const route = useRoute()
const accountSetStore = useAccountSetStore()
const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)
const initialized = ref(false)

const projects = ref([])
const safeProjects = computed(() => Array.isArray(projects.value) ? projects.value : [])
const employeeDeductions = ref([])
const safeEmployeeDeductions = computed(() => Array.isArray(employeeDeductions.value) ? employeeDeductions.value : [])
const employeesLoading = ref(false)
const employeeSearchForm = reactive({
  month: '',
  project_id: '',
  search: ''
})
const employeePagination = reactive({
  currentPage: 1,
  pageSize: 20,
  total: 0
})
const importFileInputRef = ref(null)
const importing = ref(false)

const showSetEmployeeDialog = ref(false)
const employeeSubmitting = ref(false)
const employeeFormRef = ref(null)
const currentEmployee = ref({})
const employeeForm = reactive({
  employee_id: null,
  project_id: null,
  deduction_items: [],
  is_active: true
})
const employeeFormRules = {
  deduction_items: [{ required: true, message: '请添加扣除项目', trigger: 'change' }]
}

const availableDeductionItems = ref([])
const safeAvailableDeductionItems = computed(() => Array.isArray(availableDeductionItems.value) ? availableDeductionItems.value : [])

const totalDeductionAmount = computed(() => {
  return employeeForm.deduction_items.reduce((sum, item) => {
    return sum + (parseFloat(item.amount) || 0)
  }, 0).toFixed(2)
})

const getCurrentMonth = () => {
  const date = new Date()
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
}

const normalizeMonth = (value) => {
  return /^\d{4}-(0[1-9]|1[0-2])$/.test(String(value || '')) ? value : getCurrentMonth()
}

const formatAmount = (value) => {
  return Number(value || 0).toFixed(2)
}

const getDeductionTypeLabel = (type) => {
  return type === 'other' ? '其他扣除' : '专项扣除'
}

const formatDeductionItemOption = (item) => {
  return `${getDeductionTypeLabel(item.item_type)}-${item.name}`
}

const sortDeductionItemsByType = (items) => {
  const typeOrder = { special: 0, other: 1 }
  return [...items].sort((a, b) => {
    const aType = a.item_type === 'other' ? 'other' : 'special'
    const bType = b.item_type === 'other' ? 'other' : 'special'
    if (typeOrder[aType] !== typeOrder[bType]) {
      return typeOrder[aType] - typeOrder[bType]
    }
    return Number(a.sort_order || 0) - Number(b.sort_order || 0) || Number(a.id || 0) - Number(b.id || 0)
  })
}

const getEmployeeRowIndex = (index) => {
  return (employeePagination.currentPage - 1) * employeePagination.pageSize + index + 1
}

const loadProjects = async () => {
  try {
    const res = await getProjects({
      per_page: 1000,
      current_account_set_id: currentAccountSetId.value
    })
    if (res.success) {
      projects.value = res.data?.data || res.data || []
    }
  } catch (error) {
    console.error('加载项目失败:', error)
    projects.value = []
  }
}

const loadAvailableDeductionItems = async () => {
  try {
    const res = await getDeductionItems({
      is_active: 1,
      per_page: 1000,
      item_type: 'all',
      current_account_set_id: currentAccountSetId.value
    })
    if (res.success) {
      availableDeductionItems.value = (res.data || []).filter(item => item !== null)
    }
  } catch (error) {
    console.error('加载可用扣除项目失败:', error)
    availableDeductionItems.value = []
  }
}

const getActiveDeductionItems = async () => {
  const res = await getDeductionItems({
    is_active: 1,
    per_page: 1000,
    item_type: 'all',
    current_account_set_id: currentAccountSetId.value
  })
  return res.success ? sortDeductionItemsByType((res.data || []).filter(item => item !== null)) : []
}

const formatTemplateDate = () => {
  const date = new Date()
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}${month}${day}`
}

const handleDownloadImportTemplate = async () => {
  try {
    const items = await getActiveDeductionItems()
    if (items.length === 0) {
      ElMessage.warning('请先添加启用的扣除项目')
      return
    }

    const headers = ['员工姓名', '身份证号', ...items.map(item => formatDeductionItemOption(item))]
    const exampleRow = ['张三', '110101199001010011', ...items.map(() => '0')]
    const sheet = XLSX.utils.aoa_to_sheet([headers, exampleRow])
    sheet['!cols'] = headers.map((header, index) => ({
      wch: index < 2 ? 18 : Math.max(String(header).length + 6, 14)
    }))
    if (sheet.B2) {
      sheet.B2.t = 's'
      sheet.B2.z = '@'
    }
    if (sheet['!cols'][1]) {
      sheet['!cols'][1].z = '@'
    }

    const noteSheet = XLSX.utils.aoa_to_sheet([
      ['填写说明'],
      ['1. 身份证号必填，用于匹配员工。'],
      ['2. 身份证号列请按文本填写，避免 Excel 自动转成科学计数法。'],
      ['3. 后续扣除项目列直接填写金额，空白表示不设置该项目。'],
      ['4. 模板包含专项扣除和其他扣除项目，导入时会按项目类型自动保存并覆盖该员工当月扣除设置。'],
      ['5. 如果页面选择了项目，只会导入该项目下的员工。'],
      [],
      ['当前启用扣除项目'],
      ...items.map(item => [formatDeductionItemOption(item)])
    ])
    noteSheet['!cols'] = [{ wch: 28 }, { wch: 16 }]

    const workbook = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(workbook, sheet, '人员扣除导入')
    XLSX.utils.book_append_sheet(workbook, noteSheet, '填写说明')
    XLSX.writeFile(workbook, `人员扣除导入模板_${formatTemplateDate()}.xlsx`)
  } catch (error) {
    console.error('下载导入模板失败:', error)
    ElMessage.error(error.message || '下载导入模板失败')
  }
}

const triggerImportTemplate = () => {
  importFileInputRef.value?.click()
}

const expandScientificNotation = (value) => {
  const match = String(value).trim().match(/^([+-]?)(\d+)(?:\.(\d+))?e([+-]?\d+)$/i)
  if (!match) return String(value ?? '').trim()

  const sign = match[1] === '-' ? '-' : ''
  const integerPart = match[2]
  const decimalPart = match[3] || ''
  const exponent = Number(match[4])
  const digits = `${integerPart}${decimalPart}`
  const decimalIndex = integerPart.length + exponent

  if (decimalIndex <= 0) {
    return `${sign}0.${'0'.repeat(Math.abs(decimalIndex))}${digits}`.replace(/\.?0+$/, '')
  }

  if (decimalIndex >= digits.length) {
    return `${sign}${digits}${'0'.repeat(decimalIndex - digits.length)}`
  }

  return `${sign}${digits.slice(0, decimalIndex)}.${digits.slice(decimalIndex)}`.replace(/\.?0+$/, '')
}

const normalizeImportCell = (value) => {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value.toLocaleString('en-US', {
      useGrouping: false,
      maximumFractionDigits: 0
    }).trim()
  }

  const text = String(value ?? '').trim()
  if (/^[+-]?\d+(?:\.\d+)?e[+-]?\d+$/i.test(text)) {
    return expandScientificNotation(text)
  }

  return text
}

const handleImportFileChange = async (event) => {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return

  if (!/\.(xlsx|xls)$/i.test(file.name)) {
    ElMessage.warning('请选择 Excel 文件')
    return
  }

  importing.value = true
  try {
    const buffer = await file.arrayBuffer()
    const workbook = XLSX.read(buffer, { type: 'array' })
    const sheetName = workbook.SheetNames[0]
    if (!sheetName) {
      ElMessage.warning('导入文件没有可读取的工作表')
      return
    }

    const rawRows = XLSX.utils.sheet_to_json(workbook.Sheets[sheetName], { header: 1, defval: '', raw: true })
    const headers = (rawRows[0] || []).map(value => normalizeImportCell(value))
    const rows = rawRows.slice(1).map(row => {
      const record = {}
      headers.forEach((header, index) => {
        if (header) {
          record[header] = normalizeImportCell(row[index])
        }
      })
      return record
    })
    const importRows = rows.filter(row => {
      const idNumber = normalizeImportCell(row['身份证号'] || row['身份证号码'])
      return idNumber && idNumber !== '110101199001010011'
    })

    if (importRows.length === 0) {
      ElMessage.warning('没有可导入的数据，请填写模板后再导入')
      return
    }

    const res = await importEmployeeDeductions({
      rows: importRows,
      project_id: employeeSearchForm.project_id || null,
      month: employeeSearchForm.month,
      current_account_set_id: currentAccountSetId.value
    })

    if (res.success) {
      const result = res.data || {}
      const message = res.message || `导入完成，成功 ${result.success_count || 0} 条，失败 ${result.error_count || 0} 条`
      if (result.error_count > 0) {
        const errors = (result.errors || []).slice(0, 30).join('\n')
        await ElMessageBox.alert(errors || message, '导入结果', { type: result.success_count > 0 ? 'warning' : 'error' })
      } else {
        ElMessage.success(message)
      }
      loadEmployeeDeductions()
    } else {
      ElMessage.error(res.message || '导入失败')
    }
  } catch (error) {
    console.error('导入人员扣除失败:', error)
    ElMessage.error(error.message || '导入失败')
  } finally {
    importing.value = false
  }
}

const loadEmployeeDeductions = async () => {
  employeesLoading.value = true
  try {
    employeeSearchForm.month = normalizeMonth(employeeSearchForm.month)
    await loadAvailableDeductionItems()
    const params = {
      page: employeePagination.currentPage,
      per_page: employeePagination.pageSize,
      month: employeeSearchForm.month,
      deduction_type: 'all',
      current_account_set_id: currentAccountSetId.value || undefined
    }
    if (employeeSearchForm.project_id) {
      params.project_id = employeeSearchForm.project_id
    }
    if (employeeSearchForm.search) {
      params.search = employeeSearchForm.search
    }
    const res = await getEmployeeDeductions(params)
    if (res.success) {
      employeeDeductions.value = (res.data || []).filter(item => item !== null)
      employeePagination.total = res.total || 0
    }
  } catch (error) {
    ElMessage.error('加载员工专项扣除失败')
    console.error('加载员工专项扣除失败:', error)
    employeeDeductions.value = []
  } finally {
    employeesLoading.value = false
  }
}

const handleProjectChange = () => {
  employeePagination.currentPage = 1
  loadEmployeeDeductions()
}

const handleEmployeeMonthChange = () => {
  employeeSearchForm.month = normalizeMonth(employeeSearchForm.month)
  employeePagination.currentPage = 1
  loadEmployeeDeductions()
}

const handleSetEmployee = async (row) => {
  currentEmployee.value = {
    id: row.employee_id,
    name: row.employee_name || row.name,
    project_name: row.project_name,
    project_id: row.project_id
  }

  employeeForm.employee_id = row.employee_id
  employeeForm.project_id = row.project_id
  employeeForm.is_active = true

  await loadAvailableDeductionItems()

  if (row.deduction_items && row.deduction_items.length > 0) {
    employeeForm.deduction_items = row.deduction_items.map(item => ({
      id: item.id,
      amount: parseFloat(item.amount)
    }))
  } else {
    employeeForm.deduction_items = []
  }

  showSetEmployeeDialog.value = true
}

const handleDeleteEmployee = async (row) => {
  try {
    await ElMessageBox.confirm('确定要删除该员工的专项扣除设置吗？', '提示', {
      type: 'warning'
    })

    if (row.deduction_detail_ids && row.deduction_detail_ids.length > 0) {
      for (const detailId of row.deduction_detail_ids) {
        await deleteEmployeeDeduction(detailId)
      }
      ElMessage.success('删除成功')
      loadEmployeeDeductions()
    } else {
      ElMessage.warning('该员工没有专项扣除设置')
    }
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.message || '删除失败')
    }
  }
}

const addDeductionItem = () => {
  employeeForm.deduction_items.push({
    id: null,
    amount: null
  })
}

const removeDeductionItem = (index) => {
  employeeForm.deduction_items.splice(index, 1)
}

const handleDeductionItemChange = (index) => {
  const item = employeeForm.deduction_items[index]
  const deductionItem = availableDeductionItems.value.find(d => d.id === item.id)
  if (deductionItem) {
    item.amount = 0
  }
}

const handleEmployeeSubmit = async () => {
  try {
    await employeeFormRef.value.validate()

    if (employeeForm.deduction_items.length === 0) {
      ElMessage.warning('请至少添加一个扣除项目')
      return
    }

    const hasEmpty = employeeForm.deduction_items.some(item => !item.id || item.amount === null || item.amount === undefined)
    if (hasEmpty) {
      ElMessage.warning('请完整填写所有扣除项目')
      return
    }

    employeeSubmitting.value = true
    const res = await setEmployeeDeduction({
      employee_id: employeeForm.employee_id,
      project_id: employeeForm.project_id,
      month: employeeSearchForm.month,
      deduction_type: 'all',
      deduction_items: employeeForm.deduction_items,
      is_active: true,
      current_account_set_id: currentAccountSetId.value
    })

    if (res.success) {
      ElMessage.success('设置成功')
      showSetEmployeeDialog.value = false
      loadEmployeeDeductions()
    }
  } catch (error) {
    if (error !== false) {
      ElMessage.error(error.message || '设置失败')
    }
  } finally {
    employeeSubmitting.value = false
  }
}

const handleEmployeeDialogClose = () => {
  currentEmployee.value = {}
  Object.assign(employeeForm, {
    employee_id: null,
    project_id: null,
    deduction_items: [],
    is_active: true
  })
  employeeFormRef.value?.clearValidate()
}

watch(currentAccountSetId, (newId, oldId) => {
  if (!initialized.value) return
  if (newId && newId !== oldId) {
    loadProjects()
    loadEmployeeDeductions()
  }
})

onMounted(async () => {
  employeeSearchForm.month = normalizeMonth(route.query.month || employeeSearchForm.month)

  if (!currentAccountSetId.value) {
    await accountSetStore.loadMyAccountSets()
  }

  initialized.value = true
  await loadProjects()
  await loadEmployeeDeductions()
})
</script>

<style scoped>
.employee-special-deductions-page {
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.page-header h1 {
  margin: 0;
  font-size: 24px;
  font-weight: 500;
}

.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.hidden-file-input {
  display: none;
}

.pagination {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.text-muted {
  color: #909399;
}
</style>
