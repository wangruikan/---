<template>
  <div class="large-medical-insurance-container">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>大额医疗保险管理</span>
          <el-button type="primary" @click="showCreateDialog">新建配置</el-button>
        </div>
      </template>

      <!-- 筛选条件 -->
      <el-form :inline="true" :model="filterForm" class="filter-form">
        <el-form-item label="地区">
          <el-input v-model="filterForm.region_name" placeholder="请输入地区名称" clearable style="width: 200px" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadConfigs">查询</el-button>
          <el-button @click="resetFilter">重置</el-button>
        </el-form-item>
      </el-form>

      <!-- 配置列表 -->
      <el-table :data="configs" border stripe v-loading="loading">
        <el-table-column prop="region_name" label="地区名称" width="120" />
        <el-table-column label="计算方式" width="120">
          <template #default="{ row }">
            <el-tag :type="row.calculation_type === 'base' ? 'primary' : 'success'">
              {{ row.calculation_type === 'base' ? '按基数' : '按固定金额' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="基数来源" width="120">
          <template #default="{ row }">
            <template v-if="row.calculation_type === 'base'">
              <el-tag :type="row.base_source === 'config' ? 'warning' : 'info'" size="small">
                {{ row.base_source === 'config' ? '统一基数' : '员工基数' }}
              </el-tag>
            </template>
            <template v-else>--</template>
          </template>
        </el-table-column>
        <el-table-column label="公司基数" width="100">
          <template #default="{ row }">
            <template v-if="row.calculation_type === 'base' && row.base_source === 'config'">
              ¥{{ row.base_amount || 0 }}
            </template>
            <template v-else>--</template>
          </template>
        </el-table-column>
        <el-table-column label="个人基数" width="100">
          <template #default="{ row }">
            <template v-if="row.calculation_type === 'base' && row.base_source === 'config'">
              ¥{{ row.employee_base_amount || 0 }}
            </template>
            <template v-else>--</template>
          </template>
        </el-table-column>
        <el-table-column label="公司承担" width="100">
          <template #default="{ row }">
            <template v-if="row.calculation_type === 'base'">
              {{ (row.company_ratio * 100).toFixed(2) }}%
            </template>
            <template v-else>
              ¥{{ row.company_amount }}
            </template>
          </template>
        </el-table-column>
        <el-table-column label="员工承担" width="100">
          <template #default="{ row }">
            <template v-if="row.calculation_type === 'base'">
              {{ (row.employee_ratio * 100).toFixed(2) }}%
            </template>
            <template v-else>
              ¥{{ row.employee_amount }}
            </template>
          </template>
        </el-table-column>
        <el-table-column label="付款周期" width="80">
          <template #default="{ row }">
            {{ row.payment_cycle === 'month' ? '按月' : '按年' }}
          </template>
        </el-table-column>
        <!-- 待生效配置列（仅特殊地区显示） -->
        
        <el-table-column label="操作" width="220" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" size="small" @click="handleEdit(row)">编辑</el-button>
            <!-- 仅特殊地区显示设置生效和历史按钮 -->
            <template v-if="row.base_source === 'config'">
              <el-button type="info" size="small" @click="showConfigHistory(row)">历史</el-button>
            </template>
            <el-button type="danger" size="small" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 创建/编辑对话框 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="600px"
      @close="handleDialogClose"
    >
      <el-form :model="formData" :rules="formRules" ref="formRef" label-width="120px">
        <el-form-item label="地区名称" prop="region_name">
          <el-input v-model="formData.region_name" placeholder="请输入地区名称" :disabled="isEdit" />
        </el-form-item>
        
        <el-form-item label="计算方式" prop="calculation_type">
          <el-radio-group v-model="formData.calculation_type">
            <el-radio label="base">按基数</el-radio>
            <el-radio label="fixed">按固定金额</el-radio>
          </el-radio-group>
        </el-form-item>

        <!-- 按基数计算 -->
        <template v-if="formData.calculation_type === 'base'">
          <el-form-item label="基数来源" prop="base_source">
            <el-radio-group v-model="formData.base_source">
              <el-radio label="employee">使用员工基数（普通地区）</el-radio>
              <el-radio label="config">使用统一基数（特殊地区）</el-radio>
            </el-radio-group>
          </el-form-item>
          
          <!-- 特殊地区：需要填写统一基数 -->
          <template v-if="formData.base_source === 'config'">
            <el-form-item label="公司基数" prop="base_amount">
              <el-input-number v-model="formData.base_amount" :min="0" :precision="2" :step="100" style="width: 100%" />
            </el-form-item>
            <el-form-item label="个人基数" prop="employee_base_amount">
              <el-input-number v-model="formData.employee_base_amount" :min="0" :precision="2" :step="100" style="width: 100%" />
            </el-form-item>
          </template>
          
          <el-form-item label="公司比例" prop="company_ratio">
            <el-input-number v-model="formData.company_ratio" :min="0" :max="100" :precision="2" :step="0.01" style="width: 100%" />
            <span style="margin-left: 10px; color: #909399;">例如：10 代表 10%</span>
          </el-form-item>
          <el-form-item label="员工比例" prop="employee_ratio">
            <el-input-number v-model="formData.employee_ratio" :min="0" :max="100" :precision="2" :step="0.01" style="width: 100%" />
          </el-form-item>
        </template>

        <!-- 按固定金额 -->
        <template v-else>
          <el-form-item label="公司金额" prop="company_amount">
            <el-input-number v-model="formData.company_amount" :min="0" :precision="2" :step="10" style="width: 100%" />
          </el-form-item>
          <el-form-item label="员工金额" prop="employee_amount">
            <el-input-number v-model="formData.employee_amount" :min="0" :precision="2" :step="10" style="width: 100%" />
          </el-form-item>
        </template>

        <el-form-item label="付款周期" prop="payment_cycle">
          <el-radio-group v-model="formData.payment_cycle" disabled>
            <el-radio label="month">按月</el-radio>
            <el-radio label="year">按年</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="备注">
          <el-input v-model="formData.remarks" type="textarea" :rows="2" placeholder="请输入备注（可选）" />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitting">确定</el-button>
      </template>
    </el-dialog>



    <!-- 历史记录对话框 -->
    <el-dialog v-model="historyDialogVisible" :title="historyTitle" width="900px">
      <el-table :data="histories" border stripe v-loading="historyLoading" max-height="500">
        <el-table-column prop="region_name" label="地区" width="100" />
        <el-table-column label="变更类型" width="100">
          <template #default="{ row }">
            <el-tag :type="getChangeTypeTag(row.change_type)">{{ getChangeTypeText(row.change_type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="变更内容" min-width="250">
          <template #default="{ row }">
            <div v-if="row.old_base_amount !== row.new_base_amount">
              公司基数: ¥{{ row.old_base_amount || 0 }} → ¥{{ row.new_base_amount || 0 }}
            </div>
            <div v-if="row.old_employee_base_amount !== row.new_employee_base_amount">
              个人基数: ¥{{ row.old_employee_base_amount || 0 }} → ¥{{ row.new_employee_base_amount || 0 }}
            </div>
            <div v-if="row.old_company_ratio !== row.new_company_ratio">
              公司比例: {{ ((row.old_company_ratio || 0) * 100).toFixed(2) }}% → {{ ((row.new_company_ratio || 0) * 100).toFixed(2) }}%
            </div>
            <div v-if="row.old_employee_ratio !== row.new_employee_ratio">
              员工比例: {{ ((row.old_employee_ratio || 0) * 100).toFixed(2) }}% → {{ ((row.new_employee_ratio || 0) * 100).toFixed(2) }}%
            </div>
            <div v-if="row.old_company_amount !== row.new_company_amount">
              公司金额: ¥{{ row.old_company_amount || 0 }} → ¥{{ row.new_company_amount || 0 }}
            </div>
            <div v-if="row.old_employee_amount !== row.new_employee_amount">
              员工金额: ¥{{ row.old_employee_amount || 0 }} → ¥{{ row.new_employee_amount || 0 }}
            </div>
          </template>
        </el-table-column>
        <el-table-column label="生效日期" width="110">
          <template #default="{ row }">{{ formatDate(row.effective_date, 'date') || '--' }}</template>
        </el-table-column>
        <el-table-column prop="operated_by_name" label="操作人" width="100" />
        <el-table-column label="操作时间" width="160">
          <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" width="150" show-overflow-tooltip />
      </el-table>
      <div class="pagination-container" v-if="historyPagination.total > 0">
        <el-pagination v-model:current-page="historyPagination.page" v-model:page-size="historyPagination.per_page"
          :total="historyPagination.total" layout="total, prev, pager, next" @current-change="loadHistories" />
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useAccountSetStore } from '@/stores/accountSet'
import request from '@/api/request'

const accountSetStore = useAccountSetStore()
const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)

const decimalToPercent = (value) => {
  if (value === null || value === undefined || value === '') return 0
  return Number((Number(value) * 100).toFixed(2))
}

const percentToDecimal = (value) => {
  if (value === null || value === undefined || value === '') return 0
  return Number((Number(value) / 100).toFixed(4))
}

// 数据
const configs = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const submitting = ref(false)
const isEdit = ref(false)
const editingId = ref(null)
const formRef = ref()

const historyDialogVisible = ref(false)
const historyLoading = ref(false)
const histories = ref([])
const historyTitle = ref('历史记录')
const historyConfigId = ref(null)
const historyPagination = ref({ page: 1, per_page: 10, total: 0 })

// 筛选条件
const filterForm = ref({ region_name: '' })

// 表单数据
const formData = ref({
  region_name: '', calculation_type: 'base', base_source: 'employee',
  base_amount: 0, employee_base_amount: 0, company_ratio: 0, employee_ratio: 0,
  company_amount: 0, employee_amount: 0, payment_cycle: 'month', status: 1, remarks: ''
})

const formRules = {
  region_name: [{ required: true, message: '请输入地区名称', trigger: 'blur' }],
  calculation_type: [{ required: true, message: '请选择计算方式', trigger: 'change' }],
  payment_cycle: [{ required: true, message: '请选择付款周期', trigger: 'change' }]
}

const dialogTitle = computed(() => isEdit.value ? '编辑大额医疗保险配置' : '新建大额医疗保险配置')

// 禁用今天之前的日期（允许选择今天）
const formatDate = (date, type = 'datetime') => {
  if (!date) return '--'
  const d = new Date(date)
  if (type === 'date') return d.toLocaleDateString('zh-CN')
  return d.toLocaleString('zh-CN')
}

// 变更类型标签
const getChangeTypeTag = (type) => {
  const map = { create: 'success', update: 'primary', effective: 'warning', pending: 'info' }
  return map[type] || 'info'
}
const getChangeTypeText = (type) => {
  const map = { create: '新建', update: '修改', effective: '生效', pending: '待生效' }
  return map[type] || type
}

// 加载配置列表
const loadConfigs = async () => {
  if (!currentAccountSetId.value) { ElMessage.warning('请先选择账套'); return }
  loading.value = true
  try {
    const response = await request.get('/large-medical-insurance', {
      params: { account_set_id: currentAccountSetId.value, ...filterForm.value }
    })
    if (response.success) configs.value = response.data
  } catch (error) {
    ElMessage.error('加载配置失败')
  } finally {
    loading.value = false
  }
}

const resetFilter = () => { filterForm.value = { region_name: '' }; loadConfigs() }

const showCreateDialog = () => {
  isEdit.value = false; editingId.value = null; resetForm(); dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true; editingId.value = row.id
  formData.value = {
    region_name: row.region_name,
    calculation_type: row.calculation_type,
    base_source: row.base_source || 'employee',
    base_amount: row.base_amount || 0,
    employee_base_amount: row.employee_base_amount || 0,
    company_ratio: decimalToPercent(row.company_ratio || 0),
    employee_ratio: decimalToPercent(row.employee_ratio || 0),
    company_amount: row.company_amount || 0,
    employee_amount: row.employee_amount || 0,
    payment_cycle: row.payment_cycle,
    status: row.status,
    remarks: row.remarks || ''
  }
  dialogVisible.value = true
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(`确定要删除地区"${row.region_name}"的配置吗？`, '确认删除', { type: 'warning' })
    const response = await request.delete(`/large-medical-insurance/${row.id}`)
    if (response.success) { ElMessage.success('删除成功'); loadConfigs() }
  } catch (error) {
    if (error !== 'cancel') ElMessage.error(error.response?.data?.message || '删除失败')
  }
}

const handleSubmit = async () => {
  if (!formRef.value) return
  try {
    await formRef.value.validate()
    submitting.value = true
    const data = {
      ...formData.value,
      account_set_id: currentAccountSetId.value,
      company_ratio: formData.value.calculation_type === 'base' ? percentToDecimal(formData.value.company_ratio) : formData.value.company_ratio,
      employee_ratio: formData.value.calculation_type === 'base' ? percentToDecimal(formData.value.employee_ratio) : formData.value.employee_ratio
    }
    const response = isEdit.value
      ? await request.put(`/large-medical-insurance/${editingId.value}`, data)
      : await request.post('/large-medical-insurance', data)
    if (response.success) {
      ElMessage.success(isEdit.value ? '更新成功' : '创建成功')
      dialogVisible.value = false; loadConfigs()
    }
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '操作失败')
  } finally {
    submitting.value = false
  }
}

const handleDialogClose = () => { resetForm(); formRef.value?.clearValidate() }

const resetForm = () => {
  formData.value = {
    region_name: '', calculation_type: 'base', base_source: 'employee',
    base_amount: 0, employee_base_amount: 0, company_ratio: 0, employee_ratio: 0,
    company_amount: 0, employee_amount: 0, payment_cycle: 'month', status: 1, remarks: ''
  }
}

// 监听计算方式变化，自动设置付款周期
watch(() => formData.value.calculation_type, (newVal) => {
  // 按基数 -> 按月，按固定金额 -> 按年
  formData.value.payment_cycle = newVal === 'base' ? 'month' : 'year'
})

const showConfigHistory = (row) => {
  historyTitle.value = `${row.region_name} - 历史记录`
  historyConfigId.value = row.id
  historyPagination.value.page = 1
  loadHistories()
  historyDialogVisible.value = true
}

// 加载历史记录
const loadHistories = async () => {
  if (!historyConfigId.value) return
  historyLoading.value = true
  try {
    const response = await request.get(`/large-medical-insurance/${historyConfigId.value}/histories`, {
      params: {
        page: historyPagination.value.page,
        per_page: historyPagination.value.per_page
      }
    })
    if (response.success) {
      histories.value = response.data.data || response.data
      historyPagination.value.total = response.data.total || 0
    }
  } catch (error) {
    ElMessage.error('加载历史记录失败')
  } finally {
    historyLoading.value = false
  }
}

onMounted(() => loadConfigs())
</script>

<style scoped>
.large-medical-insurance-container { padding: 20px; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.filter-form { margin-bottom: 20px; }
.pagination-container { margin-top: 15px; display: flex; justify-content: flex-end; }
</style>
