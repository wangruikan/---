<template>
  <div class="special-deductions-page">
    <div class="page-header">
      <h1>专项扣除管理</h1>
    </div>

    <el-card>
      <el-tabs v-model="activeTab" @tab-change="handleTabChange">
        <!-- 扣除项目设置 Tab -->
        <el-tab-pane label="扣除项目设置" name="items">
          <div class="tab-content">
            <!-- 工具栏 -->
            <div class="toolbar">
              <el-form :model="itemSearchForm" inline>

                <el-form-item label="名称">
                  <el-input
                    v-model="itemSearchForm.search"
                    placeholder="请输入扣除项目名称"
                    clearable
                    style="width: 200px"
                    @keyup.enter="loadDeductionItems"
                  />
                </el-form-item>

                <el-form-item>
                  <el-button type="primary" @click="loadDeductionItems">
                    <el-icon><Search /></el-icon>
                    搜索
                  </el-button>
                  <el-button type="primary" @click="showCreateItemDialog = true">
                    <el-icon><Plus /></el-icon>
                    新增扣除项目
                  </el-button>
                  <el-button type="success" @click="handleDownloadImportTemplate">
                    <el-icon><Download /></el-icon>
                    下载导入模板
                  </el-button>
                </el-form-item>
              </el-form>
            </div>

            <!-- 项目列表 -->
            <el-table
              :data="safeDeductionItems"
              v-loading="itemsLoading"
              stripe
              border
              style="margin-top: 20px"
            >
              <el-table-column prop="name" label="扣除项目名称" width="200" />
              <el-table-column prop="description" label="说明描述" show-overflow-tooltip />
              <el-table-column prop="sort_order" label="排序" width="80" />
              <el-table-column prop="creator_name" label="创建人" width="100" />
              <el-table-column prop="created_at" label="创建时间" width="160">
                <template #default="{ row }">
                  {{ formatDateTime(row.created_at) }}
                </template>
              </el-table-column>
              <el-table-column label="操作" width="150" fixed="right">
                <template #default="{ row }">
                  <el-button link type="primary" @click="handleEditItem(row)">编辑</el-button>
                  <el-button link type="danger" @click="handleDeleteItem(row)">删除</el-button>
                </template>
              </el-table-column>
            </el-table>

            <!-- 分页 -->
            <div class="pagination">
              <el-pagination
                v-model:current-page="itemPagination.currentPage"
                v-model:page-size="itemPagination.pageSize"
                :page-sizes="[10, 20, 50, 100]"
                :total="itemPagination.total"
                layout="total, sizes, prev, pager, next, jumper"
                @size-change="loadDeductionItems"
                @current-change="loadDeductionItems"
              />
            </div>
          </div>
        </el-tab-pane>

        <!-- 人员管理 Tab -->
        <el-tab-pane label="人员专项管理" name="employees">
          <div class="tab-content">
            <!-- 工具栏 -->
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
                  <el-button type="primary" :loading="pageSaving" @click="saveCurrentPageDeductions">
                    <el-icon><Setting /></el-icon>
                    保存当前页
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

            <!-- 员工列表 -->
            <el-table
              :data="safeEmployeeDeductions"
              v-loading="employeesLoading"
              stripe
              border
              style="margin-top: 20px"
            >
              <el-table-column prop="employee_name" label="员工姓名" width="120" />
              <el-table-column prop="id_number" label="身份证号" width="180" />
              <el-table-column prop="project_name" label="所属项目" width="150" />
              <el-table-column
                v-for="item in safeAvailableDeductionItems"
                :key="item.id"
                :label="item.name"
                min-width="140"
              >
                <template #default="{ row }">
                  <el-input-number
                    v-model="row.deduction_amount_map[item.id]"
                    :min="0"
                    :precision="2"
                    :controls="false"
                    size="small"
                    style="width: 110px"
                    @change="updateRowTotal(row)"
                  />
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
                  <el-button link type="primary" :loading="row.saving" @click="saveEmployeeDeductionRow(row)">
                    保存
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

            <!-- 分页 -->
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
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 新增/编辑扣除项目对话框 -->
    <el-dialog
      v-model="showCreateItemDialog"
      :title="isEditItem ? '编辑扣除项目' : '新增扣除项目'"
      width="600px"
      @close="handleItemDialogClose"
    >
      <el-form
        ref="itemFormRef"
        :model="itemForm"
        :rules="itemFormRules"
        label-width="120px"
      >
        <el-form-item label="扣除项目名称" prop="name">
          <el-input v-model="itemForm.name" placeholder="例如：子女教育、住房贷款利息等" />
        </el-form-item>

        <el-form-item label="说明描述" prop="description">
          <el-input
            v-model="itemForm.description"
            type="textarea"
            :rows="3"
            placeholder="请输入说明描述"
          />
        </el-form-item>

        <el-form-item label="排序" prop="sort_order">
          <el-input-number
            v-model="itemForm.sort_order"
            :min="0"
            placeholder="数字越小越靠前"
            style="width: 100%"
          />
        </el-form-item>

      </el-form>

      <template #footer>
        <el-button @click="showCreateItemDialog = false">取消</el-button>
        <el-button type="primary" @click="handleItemSubmit" :loading="itemSubmitting">
          {{ isEditItem ? '更新' : '创建' }}
        </el-button>
      </template>
    </el-dialog>

    <!-- 设置员工专项扣除对话框 -->
    <el-dialog
      v-model="showSetEmployeeDialog"
      title="设置员工专项扣除"
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

        <el-form-item label="专项扣除项目" prop="deduction_items" required>
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
                  :label="deductionItem.name"
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

    <!-- 批量设置对话框 -->
    <el-dialog
      v-model="showBatchSetDialog"
      title="批量设置专项扣除"
      width="800px"
      @close="handleBatchDialogClose"
    >
      <el-form
        ref="batchFormRef"
        :model="batchForm"
        :rules="batchFormRules"
        label-width="120px"
      >
        <el-form-item label="选择项目">
          <el-input :value="getProjectName(employeeSearchForm.project_id)" disabled />
        </el-form-item>

        <el-form-item label="选择员工" prop="employee_ids" required>
          <el-select
            v-model="batchForm.employee_ids"
            multiple
            placeholder="请选择员工"
            style="width: 100%"
            collapse-tags
            collapse-tags-tooltip
          >
            <el-option
              v-for="employee in safeProjectEmployees.filter(e => e)"
              :key="employee.id"
              :label="`${employee.name} (${employee.id_number})`"
              :value="employee.id"
            >
              <span>{{ employee.name }}</span>
              <span style="margin-left: 10px; color: #8492a6; font-size: 13px">
                {{ employee.id_number }}
              </span>
              <el-tag 
                v-if="employee.has_deduction" 
                type="success" 
                size="small"
                style="margin-left: 10px"
              >
                已设置
              </el-tag>
            </el-option>
          </el-select>
          <div style="margin-top: 10px">
            <el-button size="small" @click="selectAllEmployees">全选</el-button>
            <el-button size="small" @click="selectUnsetEmployees">选择未设置</el-button>
            <el-button size="small" @click="batchForm.employee_ids = []">清空</el-button>
          </div>
        </el-form-item>

        <el-form-item label="专项扣除项目" prop="deduction_items" required>
          <div style="width: 100%">
            <div 
              v-for="(item, index) in batchForm.deduction_items" 
              :key="index"
              style="display: flex; align-items: center; margin-bottom: 10px"
            >
              <el-select
                v-model="item.id"
                placeholder="请选择扣除项目"
                style="flex: 1; margin-right: 10px"
                @change="handleBatchDeductionItemChange(index)"
              >
                <el-option
                  v-for="deductionItem in safeAvailableDeductionItems.filter(d => d)"
                  :key="deductionItem.id"
                  :label="deductionItem.name"
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
                @click="removeBatchDeductionItem(index)"
              />
            </div>
            <el-button
              type="primary"
              :icon="Plus"
              @click="addBatchDeductionItem"
              style="width: 100%"
            >
              添加扣除项目
            </el-button>
          </div>
        </el-form-item>

        <el-form-item label="总扣除金额">
          <el-input :value="batchTotalDeductionAmount" disabled>
            <template #prepend>¥</template>
          </el-input>
        </el-form-item>

      </el-form>

      <template #footer>
        <el-button @click="showBatchSetDialog = false">取消</el-button>
        <el-button type="primary" @click="handleBatchSubmit" :loading="batchSubmitting">
          批量设置
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Plus, Setting, Delete, Download, Upload } from '@element-plus/icons-vue'
import * as XLSX from 'xlsx'
import { useAccountSetStore } from '@/stores/accountSet'
import {
  getDeductionItems,
  createDeductionItem,
  updateDeductionItem,
  deleteDeductionItem,
  getEmployeeDeductions,
  getProjectEmployees,
  setEmployeeDeduction,
  batchSetEmployeeDeduction,
  importEmployeeDeductions,
  deleteEmployeeDeduction
} from '@/api/specialDeductions'
import { getProjects } from '@/api/projects'

// 账套store
const accountSetStore = useAccountSetStore()
const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)
const route = useRoute()

// Tab控制
const activeTab = ref('items')

// 项目列表（确保初始化为数组）
const projects = ref([])
// 确保 projects 永远是数组
const safeProjects = computed(() => Array.isArray(projects.value) ? projects.value : [])

// ==================== 扣除项目设置 ====================
const deductionItems = ref([])
const safeDeductionItems = computed(() => Array.isArray(deductionItems.value) ? deductionItems.value : [])
const itemsLoading = ref(false)
const itemSearchForm = reactive({
  search: ''
})
const itemPagination = reactive({
  currentPage: 1,
  pageSize: 20,
  total: 0
})

// 新增/编辑项目对话框
const showCreateItemDialog = ref(false)
const isEditItem = ref(false)
const itemSubmitting = ref(false)
const itemFormRef = ref(null)
const currentItemId = ref(null)
const itemForm = reactive({
  name: '',
  description: '',
  sort_order: 0,
  is_active: true
})
const itemFormRules = {
  name: [{ required: true, message: '请输入扣除项目名称', trigger: 'blur' }]
}

// ==================== 人员专项管理 ====================
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
const pageSaving = ref(false)

// 设置员工专项扣除对话框
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
  deduction_items: [{ required: true, message: '请添加专项扣除项目', trigger: 'change' }]
}

// 批量设置对话框
const showBatchSetDialog = ref(false)
const batchSubmitting = ref(false)
const batchFormRef = ref(null)
const projectEmployees = ref([])
const safeProjectEmployees = computed(() => Array.isArray(projectEmployees.value) ? projectEmployees.value : [])
const batchForm = reactive({
  employee_ids: [],
  project_id: null,
  deduction_items: [],
  is_active: true
})
const batchFormRules = {
  employee_ids: [{ required: true, message: '请选择员工', trigger: 'change' }],
  deduction_items: [{ required: true, message: '请添加专项扣除项目', trigger: 'change' }]
}

// 可用的扣除项目（通用项目 + 当前项目的项目）
const availableDeductionItems = ref([])
const safeAvailableDeductionItems = computed(() => Array.isArray(availableDeductionItems.value) ? availableDeductionItems.value : [])

// ==================== 计算属性 ====================
const totalDeductionAmount = computed(() => {
  return employeeForm.deduction_items.reduce((sum, item) => {
    return sum + (parseFloat(item.amount) || 0)
  }, 0).toFixed(2)
})

const batchTotalDeductionAmount = computed(() => {
  return batchForm.deduction_items.reduce((sum, item) => {
    return sum + (parseFloat(item.amount) || 0)
  }, 0).toFixed(2)
})

// ==================== 方法 ====================
const getCurrentMonth = () => {
  const date = new Date()
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
}

const normalizeMonth = (value) => {
  return /^\d{4}-(0[1-9]|1[0-2])$/.test(String(value || '')) ? value : getCurrentMonth()
}

const buildDeductionAmountMap = (items = []) => {
  const map = {}
  safeAvailableDeductionItems.value.forEach(item => {
    map[item.id] = 0
  })
  ;(items || []).forEach(item => {
    map[item.id] = Number(item.amount || 0)
  })
  return map
}

const buildDeductionItemsFromRow = (row) => {
  return safeAvailableDeductionItems.value.map(item => ({
    id: item.id,
    amount: Number(row.deduction_amount_map?.[item.id] || 0)
  }))
}

const updateRowTotal = (row) => {
  row.total_amount = buildDeductionItemsFromRow(row).reduce((sum, item) => sum + item.amount, 0).toFixed(2)
}

// 加载项目列表
const loadProjects = async () => {
  try {
    const res = await getProjects({ 
      per_page: 1000,
      current_account_set_id: currentAccountSetId.value
    })
    if (res.success) {
      // 项目API返回的是分页数据，实际数据在 data.data 中
      projects.value = res.data?.data || res.data || []
    }
  } catch (error) {
    console.error('加载项目失败:', error)
    projects.value = []
  }
}

// 加载扣除项目列表
const loadDeductionItems = async () => {
  itemsLoading.value = true
  try {
    const params = {
      ...itemSearchForm,
      page: itemPagination.currentPage,
      per_page: itemPagination.pageSize,
      current_account_set_id: currentAccountSetId.value
    }
    const res = await getDeductionItems(params)
    if (res.success) {
      deductionItems.value = (res.data || []).filter(item => item !== null)
      itemPagination.total = res.total || 0
    }
  } catch (error) {
    ElMessage.error('加载扣除项目失败')
    console.error(error)
    deductionItems.value = []
  } finally {
    itemsLoading.value = false
  }
}

// 加载可用扣除项目（用于设置员工专项扣除时选择）
const loadAvailableDeductionItems = async (projectId) => {
  try {
    const params = {
      is_active: 1,
      per_page: 1000,
      current_account_set_id: currentAccountSetId.value
    }
    const res = await getDeductionItems(params)
    if (res.success) {
      // 现在所有扣除项目都是通用的，直接使用所有激活的项目
      availableDeductionItems.value = (res.data || [])
        .filter(item => item !== null)
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
    current_account_set_id: currentAccountSetId.value
  })
  return res.success ? (res.data || []).filter(item => item !== null) : []
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

    const headers = ['员工姓名', '身份证号', ...items.map(item => item.name)]
    const exampleRow = [
      '张三',
      '110101199001010011',
      ...items.map(() => '0')
    ]
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
      ['3. 后续专项扣除列直接填写金额，空白表示 0。'],
      ['4. 导入会按当前页面选择的月份，覆盖该员工当月专项扣除设置。'],
      ['5. 如果页面选择了项目，只会导入该项目下的员工。'],
      [],
      ['当前启用扣除项目'],
      ...items.map(item => [item.name])
    ])
    noteSheet['!cols'] = [{ wch: 28 }, { wch: 16 }]

    const workbook = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(workbook, sheet, '人员专项扣除导入')
    XLSX.utils.book_append_sheet(workbook, noteSheet, '填写说明')
    XLSX.writeFile(workbook, `人员专项扣除导入模板_${formatTemplateDate()}.xlsx`)
  } catch (error) {
    console.error('下载导入模板失败:', error)
    ElMessage.error(error.message || '下载导入模板失败')
  }
}

const triggerImportTemplate = () => {
  importFileInputRef.value?.click()
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
    console.error('导入专项扣除失败:', error)
    ElMessage.error(error.message || '导入失败')
  } finally {
    importing.value = false
  }
}

// 编辑扣除项目
const handleEditItem = (row) => {
  isEditItem.value = true
  currentItemId.value = row.id
  Object.assign(itemForm, {
    name: row.name,
    description: row.description,
    sort_order: row.sort_order,
    is_active: true
  })
  showCreateItemDialog.value = true
}

// 删除扣除项目
const handleDeleteItem = async (row) => {
  try {
    await ElMessageBox.confirm('确定要删除该扣除项目吗？', '提示', {
      type: 'warning'
    })
    const res = await deleteDeductionItem(row.id)
    if (res.success) {
      ElMessage.success('删除成功')
      loadDeductionItems()
    }
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.message || '删除失败')
    }
  }
}

// 提交扣除项目表单
const handleItemSubmit = async () => {
  try {
    await itemFormRef.value.validate()
    itemSubmitting.value = true

    const data = { 
      ...itemForm,
      is_active: true,
      current_account_set_id: currentAccountSetId.value
    }
    let res
    if (isEditItem.value) {
      res = await updateDeductionItem(currentItemId.value, data)
    } else {
      res = await createDeductionItem(data)
    }

    if (res.success) {
      ElMessage.success(isEditItem.value ? '更新成功' : '创建成功')
      showCreateItemDialog.value = false
      loadDeductionItems()
    }
  } catch (error) {
    if (error !== false) {
      ElMessage.error(error.message || '操作失败')
    }
  } finally {
    itemSubmitting.value = false
  }
}

// 关闭扣除项目对话框
const handleItemDialogClose = () => {
  isEditItem.value = false
  currentItemId.value = null
  Object.assign(itemForm, {
    name: '',
    description: '',
    sort_order: 0,
    is_active: true
  })
  itemFormRef.value?.clearValidate()
}

// 加载员工专项扣除列表
const loadEmployeeDeductions = async () => {
  console.log('开始加载员工专项扣除数据...')
  console.log('当前账套ID:', currentAccountSetId.value)
  
  employeesLoading.value = true
  try {
    employeeSearchForm.month = normalizeMonth(employeeSearchForm.month)
    await loadAvailableDeductionItems()
    const params = {
      page: employeePagination.currentPage,
      per_page: employeePagination.pageSize,
      month: employeeSearchForm.month,
      current_account_set_id: currentAccountSetId.value || undefined
    }
    if (employeeSearchForm.project_id) {
      params.project_id = employeeSearchForm.project_id
    }
    if (employeeSearchForm.search) {
      params.search = employeeSearchForm.search
    }
    console.log('请求参数:', params)
    const res = await getEmployeeDeductions(params)
    console.log('API响应:', res)
    if (res.success) {
      employeeDeductions.value = (res.data || []).filter(item => item !== null).map(row => {
        const mappedRow = {
          ...row,
          deduction_amount_map: buildDeductionAmountMap(row.deduction_items || row.deduction_items_array || []),
          saving: false
        }
        updateRowTotal(mappedRow)
        return mappedRow
      })
      employeePagination.total = res.total || 0
      console.log('员工数据:', employeeDeductions.value)
    }
  } catch (error) {
    ElMessage.error('加载员工专项扣除失败')
    console.error('加载员工专项扣除失败:', error)
    employeeDeductions.value = []
  } finally {
    employeesLoading.value = false
  }
}

// 项目切换
const handleProjectChange = () => {
  employeePagination.currentPage = 1
  loadEmployeeDeductions()
  if (employeeSearchForm.project_id) {
    loadAvailableDeductionItems(employeeSearchForm.project_id)
  }
}

const handleEmployeeMonthChange = () => {
  employeeSearchForm.month = normalizeMonth(employeeSearchForm.month)
  employeePagination.currentPage = 1
  loadEmployeeDeductions()
}

const saveEmployeeDeductionRow = async (row) => {
  row.saving = true
  try {
    const res = await setEmployeeDeduction({
      employee_id: row.employee_id,
      project_id: null,
      month: employeeSearchForm.month,
      deduction_items: buildDeductionItemsFromRow(row),
      is_active: true,
      current_account_set_id: currentAccountSetId.value
    })
    if (res.success) {
      row.has_deduction = true
      updateRowTotal(row)
      ElMessage.success('保存成功')
    }
  } catch (error) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    row.saving = false
  }
}

const saveCurrentPageDeductions = async () => {
  if (safeEmployeeDeductions.value.length === 0) {
    ElMessage.warning('当前没有需要保存的人员')
    return
  }

  pageSaving.value = true
  try {
    for (const row of safeEmployeeDeductions.value) {
      await setEmployeeDeduction({
        employee_id: row.employee_id,
        project_id: null,
        month: employeeSearchForm.month,
        deduction_items: buildDeductionItemsFromRow(row),
        is_active: true,
        current_account_set_id: currentAccountSetId.value
      })
      row.has_deduction = true
      updateRowTotal(row)
    }
    ElMessage.success('当前页保存成功')
    loadEmployeeDeductions()
  } catch (error) {
    ElMessage.error(error.message || '保存当前页失败')
  } finally {
    pageSaving.value = false
  }
}

// 设置员工专项扣除
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

  // 加载可用扣除项目
  await loadAvailableDeductionItems(row.project_id)

  // 从row中直接获取现有的专项扣除设置
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

// 删除员工专项扣除
const handleDeleteEmployee = async (row) => {
  try {
    await ElMessageBox.confirm('确定要删除该员工的专项扣除设置吗？', '提示', {
      type: 'warning'
    })
    
    // 删除所有相关的扣除详情记录
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

// 添加扣除项目
const addDeductionItem = () => {
  employeeForm.deduction_items.push({
    id: null,
    amount: null
  })
}

// 移除扣除项目
const removeDeductionItem = (index) => {
  employeeForm.deduction_items.splice(index, 1)
}

// 扣除项目变更
const handleDeductionItemChange = (index) => {
  const item = employeeForm.deduction_items[index]
  const deductionItem = availableDeductionItems.value.find(d => d.id === item.id)
  if (deductionItem) {
    item.amount = 0
  }
}

// 提交员工专项扣除设置
const handleEmployeeSubmit = async () => {
  try {
    await employeeFormRef.value.validate()

    if (employeeForm.deduction_items.length === 0) {
      ElMessage.warning('请至少添加一个专项扣除项目')
      return
    }

    // 验证每个项目都已选择
    const hasEmpty = employeeForm.deduction_items.some(item => !item.id || item.amount === null || item.amount === undefined)
    if (hasEmpty) {
      ElMessage.warning('请完整填写所有专项扣除项目')
      return
    }

    employeeSubmitting.value = true

    const data = {
      employee_id: employeeForm.employee_id,
      project_id: employeeForm.project_id,
      month: employeeSearchForm.month,
      deduction_items: employeeForm.deduction_items,
      is_active: true,
      current_account_set_id: currentAccountSetId.value
    }

    const res = await setEmployeeDeduction(data)
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

// 关闭员工设置对话框
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

// 加载项目员工
const loadProjectEmployees = async () => {
  console.log('开始加载项目员工，项目ID:', employeeSearchForm.project_id)
  
  try {
    const res = await getProjectEmployees(employeeSearchForm.project_id, currentAccountSetId.value, employeeSearchForm.month)
    console.log('项目员工API响应:', res)
    if (res.success) {
      projectEmployees.value = (res.data || []).filter(item => item !== null)
      console.log('项目员工数据:', projectEmployees.value)
    }
  } catch (error) {
    ElMessage.error('加载项目员工失败')
    console.error('加载项目员工失败:', error)
    projectEmployees.value = []
  }
}

// 批量设置相关方法
const addBatchDeductionItem = () => {
  batchForm.deduction_items.push({
    id: null,
    amount: null
  })
}

const removeBatchDeductionItem = (index) => {
  batchForm.deduction_items.splice(index, 1)
}

const handleBatchDeductionItemChange = (index) => {
  const item = batchForm.deduction_items[index]
  const deductionItem = availableDeductionItems.value.find(d => d.id === item.id)
  if (deductionItem) {
    item.amount = 0
  }
}

const selectAllEmployees = () => {
  batchForm.employee_ids = safeProjectEmployees.value.map(e => e.id)
}

const selectUnsetEmployees = () => {
  batchForm.employee_ids = safeProjectEmployees.value.filter(e => !e.has_deduction).map(e => e.id)
}

const handleBatchSubmit = async () => {
  try {
    await batchFormRef.value.validate()

    if (batchForm.employee_ids.length === 0) {
      ElMessage.warning('请选择员工')
      return
    }

    if (batchForm.deduction_items.length === 0) {
      ElMessage.warning('请至少添加一个专项扣除项目')
      return
    }

    // 验证每个项目都已选择
    const hasEmpty = batchForm.deduction_items.some(item => !item.id || item.amount === null || item.amount === undefined)
    if (hasEmpty) {
      ElMessage.warning('请完整填写所有专项扣除项目')
      return
    }

    batchSubmitting.value = true

    const data = {
      employee_ids: batchForm.employee_ids,
      project_id: employeeSearchForm.project_id,
      month: employeeSearchForm.month,
      deduction_items: batchForm.deduction_items,
      is_active: true,
      current_account_set_id: currentAccountSetId.value
    }

    const res = await batchSetEmployeeDeduction(data)
    if (res.success) {
      ElMessage.success(res.message || '批量设置成功')
      showBatchSetDialog.value = false
      loadEmployeeDeductions()
      loadProjectEmployees() // 重新加载以更新has_deduction状态
    }
  } catch (error) {
    if (error !== false) {
      ElMessage.error(error.message || '批量设置失败')
    }
  } finally {
    batchSubmitting.value = false
  }
}

const handleBatchDialogClose = () => {
  Object.assign(batchForm, {
    employee_ids: [],
    project_id: null,
    deduction_items: [],
    is_active: true
  })
  batchFormRef.value?.clearValidate()
}


// 获取项目名称
const getProjectName = (projectId) => {
  if (!projectId || projectId === 0) {
    return '所有项目'  // project_id 为 null、undefined 或 0 时显示为所有项目
  }
  const project = safeProjects.value.find(p => p && p.id === projectId)
  return project ? project.name : ''
}

// 格式化日期时间
const formatDateTime = (datetime) => {
  if (!datetime) return ''
  const date = new Date(datetime)
  return date.toLocaleString('zh-CN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false
  })
}

// Tab切换
const handleTabChange = (tabName) => {
  if (tabName === 'items') {
    loadDeductionItems()
  } else if (tabName === 'employees') {
    // 切换到人员专项管理时，直接加载所有员工数据（不需要先选择项目）
    loadEmployeeDeductions()
  }
}

// 监听批量设置对话框打开
watch(showBatchSetDialog, (newValue) => {
  if (newValue) {
    console.log('批量设置对话框打开，开始加载员工数据')
    loadProjectEmployees()
    batchForm.project_id = employeeSearchForm.project_id
  }
})

watch(activeTab, (newTab) => {
  if (newTab === 'employees') {
    loadEmployeeDeductions()
  }
})

watch(currentAccountSetId, (newId) => {
  if (newId) {
    loadEmployeeDeductions()
  }
}, { immediate: true })

// 初始化
onMounted(async () => {
  console.log('页面初始化，开始加载数据...')
  console.log('当前账套ID:', currentAccountSetId.value)
  employeeSearchForm.month = normalizeMonth(route.query.month || employeeSearchForm.month)
  if (route.query.tab === 'employees') {
    activeTab.value = 'employees'
  }
  
  // 先确保账套初始化
  if (!currentAccountSetId.value) {
    console.log('账套ID不存在，先加载账套列表...')
    await accountSetStore.loadMyAccountSets()
    console.log('账套加载后，当前账套ID:', currentAccountSetId.value)
  }
  
  // 加载项目和扣除项目
  await loadProjects()
  await loadDeductionItems()
  
  // 加载员工数据
  console.log('开始加载员工数据，当前账套ID:', currentAccountSetId.value)
  loadEmployeeDeductions()
})
</script>

<style scoped>
.special-deductions-page {
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

.tab-content {
  padding: 20px 0;
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

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 5px;
}

.text-muted {
  color: #909399;
}
</style>

