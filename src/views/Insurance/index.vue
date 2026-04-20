<template>
  <div class="insurance-page">
    <div class="page-header">
      <h1>保险管理</h1>
      <el-button type="primary" @click="showCreateDialog = true">
        <el-icon><Plus /></el-icon>
        新增保险记录
      </el-button>
    </div>
    
    <!-- 搜索和筛选 -->
    <div class="search-section">
      <el-card>
        <el-form :model="searchForm" inline>
          <el-form-item label="员工姓名">
            <el-input
              v-model="searchForm.employee_name"
              placeholder="请输入员工姓名"
              clearable
              @keyup.enter="handleSearch"
            />
          </el-form-item>
          
          <el-form-item label="项目">
            <el-select
              v-model="searchForm.project_id"
              placeholder="请选择项目"
              clearable
            >
              <el-option
                v-for="project in projects"
                :key="project.id"
                :label="project.name"
                :value="project.id"
              />
            </el-select>
          </el-form-item>
          
          <el-form-item label="保险类型">
            <el-select
              v-model="searchForm.insurance_type"
              placeholder="请选择保险类型"
              clearable
            >
              <el-option label="养老保险" value="养老保险" />
              <el-option label="医疗保险" value="医疗保险" />
              <el-option label="失业保险" value="失业保险" />
              <el-option label="工伤保险" value="工伤保险" />
              <el-option label="生育保险" value="生育保险" />
            </el-select>
          </el-form-item>
          
          <el-form-item label="状态">
            <el-select
              v-model="searchForm.status"
              placeholder="请选择状态"
              clearable
            >
              <el-option label="待缴费" value="pending" />
              <el-option label="已缴费" value="paid" />
              <el-option label="已过期" value="overdue" />
            </el-select>
          </el-form-item>
          
          <el-form-item>
            <el-button type="primary" @click="handleSearch">
              <el-icon><Search /></el-icon>
              搜索
            </el-button>
            <el-button @click="handleReset">
              <el-icon><Refresh /></el-icon>
              重置
            </el-button>
          </el-form-item>
        </el-form>
      </el-card>
    </div>
    
    <!-- 保险记录列表 -->
    <div class="table-section">
      <el-card>
        <el-table
          :data="insuranceRecords"
          v-loading="loading"
          stripe
          border
        >
          <el-table-column prop="employee_name" label="员工姓名" width="120" />
          <el-table-column prop="project_name" label="项目名称" width="150" />
          <el-table-column prop="insurance_type" label="保险类型" width="120" />
          <el-table-column prop="company_amount" label="公司缴费" width="120">
            <template #default="{ row }">
              {{ formatCurrency(row.company_amount) }}
            </template>
          </el-table-column>
          <el-table-column prop="personal_amount" label="个人缴费" width="120">
            <template #default="{ row }">
              {{ formatCurrency(row.personal_amount) }}
            </template>
          </el-table-column>
          <el-table-column prop="total_amount" label="总缴费" width="120">
            <template #default="{ row }">
              {{ formatCurrency(row.total_amount) }}
            </template>
          </el-table-column>
          <el-table-column prop="payment_date" label="缴费日期" width="120" />
          <el-table-column prop="due_date" label="到期日期" width="120" />
          <el-table-column prop="status" label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="getStatusType(row.status)">
                {{ getStatusText(row.status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="created_at" label="创建时间" width="160" />
          <el-table-column label="操作" width="200" fixed="right">
            <template #default="{ row }">
              <el-button type="primary" size="small" @click="handleView(row)">
                查看
              </el-button>
              <el-button 
                v-if="row.status === 'pending'" 
                type="warning" 
                size="small" 
                @click="handleEdit(row)"
              >
                编辑
              </el-button>
              <el-button 
                v-if="row.status === 'pending'" 
                type="success" 
                size="small" 
                @click="handleComplete(row)"
              >
                标记完成
              </el-button>
              <el-button type="danger" size="small" @click="handleDelete(row)">
                删除
              </el-button>
            </template>
          </el-table-column>
        </el-table>
        
        <!-- 分页 -->
        <div class="pagination">
          <el-pagination
            v-model:current-page="pagination.currentPage"
            v-model:page-size="pagination.pageSize"
            :page-sizes="[10, 20, 50, 100]"
            :total="pagination.total"
            layout="total, sizes, prev, pager, next, jumper"
            @size-change="handleSizeChange"
            @current-change="handleCurrentChange"
          />
        </div>
      </el-card>
    </div>
    
    <!-- 创建/编辑/查看对话框 -->
    <el-dialog
      v-model="showCreateDialog"
      :title="isView ? '查看保险记录' : (isEdit ? '编辑保险记录' : '新增保险记录')"
      width="800px"
      @close="handleDialogClose"
    >
      <el-form
        ref="formRef"
        :model="form"
        :rules="formRules"
        label-width="100px"
      >
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="员工" prop="employee_id">
              <el-select 
                v-model="form.employee_id" 
                placeholder="请选择员工" 
                style="width: 100%"
                :disabled="isView"
              >
                <el-option
                  v-for="employee in employees"
                  :key="employee.id"
                  :label="employee.name"
                  :value="employee.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="项目" prop="project_id">
              <el-select 
                v-model="form.project_id" 
                placeholder="请选择项目" 
                style="width: 100%"
                :disabled="isView"
              >
                <el-option
                  v-for="project in projects"
                  :key="project.id"
                  :label="project.name"
                  :value="project.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="保险类型" prop="insurance_type">
              <el-select 
                v-model="form.insurance_type" 
                placeholder="请选择保险类型" 
                style="width: 100%"
                :disabled="isView"
              >
                <el-option label="养老保险" value="养老保险" />
                <el-option label="医疗保险" value="医疗保险" />
                <el-option label="失业保险" value="失业保险" />
                <el-option label="工伤保险" value="工伤保险" />
                <el-option label="生育保险" value="生育保险" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="缴费基数" prop="base_amount">
              <el-input-number
                v-model="form.base_amount"
                :min="0"
                :precision="2"
                style="width: 100%"
                :disabled="isView"
              />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="公司缴费比例" prop="company_rate">
              <el-input-number
                v-model="form.company_rate"
                :min="0"
                :max="1"
                :precision="4"
                style="width: 100%"
                :disabled="isView"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="个人缴费比例" prop="personal_rate">
              <el-input-number
                v-model="form.personal_rate"
                :min="0"
                :max="1"
                :precision="4"
                style="width: 100%"
                :disabled="isView"
              />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="公司缴费" prop="company_amount">
              <el-input-number
                v-model="form.company_amount"
                :min="0"
                :precision="2"
                style="width: 100%"
                disabled
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="个人缴费" prop="personal_amount">
              <el-input-number
                v-model="form.personal_amount"
                :min="0"
                :precision="2"
                style="width: 100%"
                disabled
              />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="缴费日期" prop="payment_date">
              <el-date-picker
                v-model="form.payment_date"
                type="date"
                placeholder="请选择缴费日期"
                style="width: 100%"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
                :disabled="isView"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="到期日期" prop="due_date">
              <el-date-picker
                v-model="form.due_date"
                type="date"
                placeholder="请选择到期日期"
                style="width: 100%"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
                :disabled="isView"
              />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-form-item label="备注" prop="notes">
          <el-input
            v-model="form.notes"
            type="textarea"
            :rows="3"
            placeholder="请输入备注"
            :disabled="isView"
          />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showCreateDialog = false">{{ isView ? '关闭' : '取消' }}</el-button>
        <el-button 
          v-if="!isView"
          type="primary" 
          @click="handleSubmit" 
          :loading="submitting"
        >
          {{ isEdit ? '更新' : '创建' }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { 
  getInsuranceRecords, 
  createInsuranceRecord, 
  updateInsuranceRecord, 
  deleteInsuranceRecord,
  markAsCompleted
} from '@/api/insurance'
import { getProjects } from '@/api/projects'
import { getEmployees } from '@/api/employees'

const loading = ref(false)
const submitting = ref(false)
const showCreateDialog = ref(false)
const isEdit = ref(false)
const isView = ref(false)
const formRef = ref()

const insuranceRecords = ref([])
const projects = ref([])
const employees = ref([])

const searchForm = reactive({
  employee_name: '',
  project_id: '',
  insurance_type: '',
  status: ''
})

const pagination = reactive({
  currentPage: 1,
  pageSize: 20,
  total: 0
})

const form = reactive({
  employee_id: '',
  project_id: '',
  insurance_type: '',
  base_amount: 0,
  company_rate: 0,
  personal_rate: 0,
  company_amount: 0,
  personal_amount: 0,
  total_amount: 0,
  payment_date: '',
  due_date: '',
  notes: ''
})

const formRules = {
  employee_id: [
    { required: true, message: '请选择员工', trigger: 'change' }
  ],
  project_id: [
    { required: true, message: '请选择项目', trigger: 'change' }
  ],
  insurance_type: [
    { required: true, message: '请选择保险类型', trigger: 'change' }
  ],
  base_amount: [
    { required: true, message: '请输入缴费基数', trigger: 'blur' }
  ],
  company_rate: [
    { required: true, message: '请输入公司缴费比例', trigger: 'blur' }
  ],
  personal_rate: [
    { required: true, message: '请输入个人缴费比例', trigger: 'blur' }
  ]
}

// 监听缴费计算
watch([
  () => form.base_amount,
  () => form.company_rate,
  () => form.personal_rate
], () => {
  form.company_amount = form.base_amount * form.company_rate
  form.personal_amount = form.base_amount * form.personal_rate
  form.total_amount = form.company_amount + form.personal_amount
})

const loadInsuranceRecords = async () => {
  loading.value = true
  try {
    const params = {
      page: pagination.currentPage,
      per_page: pagination.pageSize,
      ...searchForm
    }
    
    const response = await getInsuranceRecords(params)
    insuranceRecords.value = response.data.data
    pagination.total = response.data.total
  } catch (error) {
    console.error('Load insurance records error:', error)
  } finally {
    loading.value = false
  }
}

const loadProjects = async () => {
  try {
    const response = await getProjects()
    projects.value = response.data.data
  } catch (error) {
    console.error('Load projects error:', error)
  }
}

const loadEmployees = async () => {
  try {
    const response = await getEmployees()
    employees.value = response.data
  } catch (error) {
    console.error('Load employees error:', error)
  }
}

const handleSearch = () => {
  pagination.currentPage = 1
  loadInsuranceRecords()
}

const handleReset = () => {
  Object.assign(searchForm, {
    employee_name: '',
    project_id: '',
    insurance_type: '',
    status: ''
  })
  handleSearch()
}

const handleSizeChange = (size) => {
  pagination.pageSize = size
  loadInsuranceRecords()
}

const handleCurrentChange = (page) => {
  pagination.currentPage = page
  loadInsuranceRecords()
}

const handleView = (row) => {
  isView.value = true
  isEdit.value = false
  // 正确提取字段
  form.id = row.id
  form.employee_id = row.employee_id
  form.project_id = row.project_id
  form.insurance_type = row.insurance_type
  form.base_amount = row.base_amount || 0
  form.company_rate = row.company_rate || 0
  form.personal_rate = row.personal_rate || 0
  form.company_amount = row.company_amount || 0
  form.personal_amount = row.personal_amount || 0
  form.total_amount = row.total_amount || 0
  form.payment_date = row.payment_date || ''
  form.due_date = row.due_date || ''
  form.notes = row.notes || ''
  showCreateDialog.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  isView.value = false
  // 正确提取字段
  form.id = row.id
  form.employee_id = row.employee_id
  form.project_id = row.project_id
  form.insurance_type = row.insurance_type
  form.base_amount = row.base_amount || 0
  form.company_rate = row.company_rate || 0
  form.personal_rate = row.personal_rate || 0
  form.company_amount = row.company_amount || 0
  form.personal_amount = row.personal_amount || 0
  form.total_amount = row.total_amount || 0
  form.payment_date = row.payment_date || ''
  form.due_date = row.due_date || ''
  form.notes = row.notes || ''
  showCreateDialog.value = true
}

const handleComplete = async (row) => {
  try {
    await markAsCompleted(row.id)
    ElMessage.success('标记完成成功')
    loadInsuranceRecords()
  } catch (error) {
    console.error('Complete error:', error)
  }
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm('确定要删除该保险记录吗？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    
    await deleteInsuranceRecord(row.id)
    ElMessage.success('删除成功')
    loadInsuranceRecords()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('Delete error:', error)
    }
  }
}

const handleSubmit = async () => {
  if (!formRef.value) return
  
  await formRef.value.validate(async (valid) => {
    if (valid) {
      submitting.value = true
      try {
        if (isEdit.value) {
          await updateInsuranceRecord(form.id, form)
          ElMessage.success('更新成功')
        } else {
          await createInsuranceRecord(form)
          ElMessage.success('创建成功')
        }
        
        showCreateDialog.value = false
        loadInsuranceRecords()
      } catch (error) {
        console.error('Submit error:', error)
      } finally {
        submitting.value = false
      }
    }
  })
}

const handleDialogClose = () => {
  isEdit.value = false
  isView.value = false
  Object.assign(form, {
    employee_id: '',
    project_id: '',
    insurance_type: '',
    base_amount: 0,
    company_rate: 0,
    personal_rate: 0,
    company_amount: 0,
    personal_amount: 0,
    total_amount: 0,
    payment_date: '',
    due_date: '',
    notes: ''
  })
  formRef.value?.resetFields()
}

const getStatusType = (status) => {
  const types = {
    pending: 'warning',
    paid: 'success',
    overdue: 'danger'
  }
  return types[status] || 'info'
}

const getStatusText = (status) => {
  const texts = {
    pending: '待缴费',
    paid: '已缴费',
    overdue: '已过期'
  }
  return texts[status] || '未知'
}

const formatCurrency = (amount) => {
  return `¥${Number(amount).toFixed(2)}`
}

onMounted(() => {
  loadInsuranceRecords()
  loadProjects()
  loadEmployees()
})
</script>

<style scoped>
.insurance-page {
  padding: 0;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.page-header h1 {
  font-size: 24px;
  color: #303133;
  margin: 0;
}

.search-section {
  margin-bottom: 20px;
}

.table-section {
  margin-bottom: 20px;
}

.pagination {
  margin-top: 20px;
  text-align: right;
}

:deep(.el-table) {
  font-size: 14px;
}

:deep(.el-form-item) {
  margin-bottom: 20px;
}
</style>
