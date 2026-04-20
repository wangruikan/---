<template>
  <div class="payments-page">
    <div class="page-header">
      <h1>付款管理</h1>
      <el-button type="primary" @click="showCreateDialog = true">
        <el-icon><Plus /></el-icon>
        新增付款
      </el-button>
    </div>
    
    <!-- 搜索和筛选 -->
    <div class="search-section">
      <el-card>
        <el-form :model="searchForm" inline>
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
          
          <el-form-item label="付款类型">
            <el-select
              v-model="searchForm.type"
              placeholder="请选择付款类型"
              clearable
            >
              <el-option label="工资" value="salary" />
              <el-option label="社保" value="social_security" />
              <el-option label="商业保险" value="commercial_insurance" />
              <el-option label="报销" value="reimbursement" />
            </el-select>
          </el-form-item>
          
          <el-form-item label="状态">
            <el-select
              v-model="searchForm.status"
              placeholder="请选择状态"
              clearable
            >
              <el-option label="待提交" value="draft" />
              <el-option label="待审批" value="pending" />
              <el-option label="已审批" value="approved" />
              <el-option label="已付款" value="paid" />
              <el-option label="已拒绝" value="rejected" />
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
    
    <!-- 付款列表 -->
    <div class="table-section">
      <el-card>
        <el-table
          :data="payments"
          v-loading="loading"
          stripe
          border
        >
          <el-table-column prop="project_name" label="项目名称" width="150" />
          <el-table-column prop="type" label="付款类型" width="120">
            <template #default="{ row }">
              {{ getTypeText(row.type) }}
            </template>
          </el-table-column>
          <el-table-column prop="amount" label="付款金额" width="120">
            <template #default="{ row }">
              {{ formatCurrency(row.amount) }}
            </template>
          </el-table-column>
          <el-table-column prop="description" label="付款说明" min-width="200" />
          <el-table-column prop="payment_date" label="付款日期" width="120" />
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
                v-if="row.status === 'draft'" 
                type="warning" 
                size="small" 
                @click="handleEdit(row)"
              >
                编辑
              </el-button>
              <el-button 
                v-if="row.status === 'draft'" 
                type="success" 
                size="small" 
                @click="handleSubmitForApproval(row)"
              >
                提交
              </el-button>
              <el-button 
                v-if="row.status === 'approved'" 
                type="success" 
                size="small" 
                @click="handlePay(row)"
              >
                付款
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
      :title="isViewMode ? '查看付款' : (isEdit ? '编辑付款' : '新增付款')"
      width="600px"
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
            <el-form-item label="项目" prop="project_id">
              <el-select v-model="form.project_id" placeholder="请选择项目" style="width: 100%" :disabled="isViewMode">
                <el-option
                  v-for="project in projects"
                  :key="project.id"
                  :label="project.name"
                  :value="project.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="付款类型" prop="type">
              <el-select v-model="form.type" placeholder="请选择付款类型" style="width: 100%" :disabled="isViewMode">
                <el-option label="工资" value="salary" />
                <el-option label="社保" value="social_security" />
                <el-option label="商业保险" value="commercial_insurance" />
                <el-option label="报销" value="reimbursement" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-form-item label="付款金额" prop="amount">
          <el-input-number
            v-model="form.amount"
            :min="0"
            :precision="2"
            style="width: 100%"
            :disabled="isViewMode"
          />
        </el-form-item>
        
        <el-form-item label="付款说明" prop="description">
          <el-input
            v-model="form.description"
            type="textarea"
            :rows="3"
            placeholder="请输入付款说明"
            :disabled="isViewMode"
          />
        </el-form-item>
        
        <el-form-item label="付款日期" prop="payment_date">
          <el-date-picker
            v-model="form.payment_date"
            type="date"
            placeholder="请选择付款日期"
            style="width: 100%"
            format="YYYY-MM-DD"
            value-format="YYYY-MM-DD"
            :disabled="isViewMode"
          />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showCreateDialog = false">{{ isViewMode ? '关闭' : '取消' }}</el-button>
        <el-button v-if="!isViewMode" type="primary" @click="handleFormSubmit" :loading="submitting">
          {{ isEdit ? '更新' : '创建' }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { 
  getPayments, 
  createPayment, 
  updatePayment, 
  deletePayment,
  submitPayment,
  approvePayment,
  payPayment
} from '@/api/payments'
import { getProjects } from '@/api/projects'

const loading = ref(false)
const submitting = ref(false)
const showCreateDialog = ref(false)
const isEdit = ref(false)
const isViewMode = ref(false) // 添加查看模式标志
const formRef = ref()

const payments = ref([])
const projects = ref([])

const searchForm = reactive({
  project_id: '',
  type: '',
  status: ''
})

const pagination = reactive({
  currentPage: 1,
  pageSize: 20,
  total: 0
})

const form = reactive({
  project_id: '',
  type: '',
  amount: 0,
  description: '',
  payment_date: ''
})

const formRules = {
  project_id: [
    { required: true, message: '请选择项目', trigger: 'change' }
  ],
  type: [
    { required: true, message: '请选择付款类型', trigger: 'change' }
  ],
  amount: [
    { required: true, message: '请输入付款金额', trigger: 'blur' }
  ],
  description: [
    { required: true, message: '请输入付款说明', trigger: 'blur' }
  ]
}

const loadPayments = async () => {
  loading.value = true
  try {
    const params = {
      page: pagination.currentPage,
      per_page: pagination.pageSize,
      ...searchForm
    }
    
    const response = await getPayments(params)
    payments.value = response.data
    pagination.total = response.total
  } catch (error) {
    console.error('Load payments error:', error)
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

const handleSearch = () => {
  pagination.currentPage = 1
  loadPayments()
}

const handleReset = () => {
  Object.assign(searchForm, {
    project_id: '',
    type: '',
    status: ''
  })
  handleSearch()
}

const handleSizeChange = (size) => {
  pagination.pageSize = size
  loadPayments()
}

const handleCurrentChange = (page) => {
  pagination.currentPage = page
  loadPayments()
}

const handleView = (row) => {
  // 查看付款详情 - 只读模式
  isEdit.value = false
  isViewMode.value = true
  Object.assign(form, {
    id: row.id,
    project_id: row.project_id,
    type: row.type,
    amount: row.amount,
    description: row.description,
    payment_date: row.payment_date
  })
  showCreateDialog.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  Object.assign(form, {
    id: row.id,
    project_id: row.project_id,
    type: row.type,
    amount: row.amount,
    description: row.description,
    payment_date: row.payment_date
  })
  showCreateDialog.value = true
}

const handleSubmitForApproval = async (row) => {
  try {
    await submitPayment(row.id)
    ElMessage.success('提交成功')
    loadPayments()
  } catch (error) {
    console.error('Submit error:', error)
  }
}

const handleFormSubmit = async () => {
  if (!formRef.value) return
  
  await formRef.value.validate(async (valid) => {
    if (valid) {
      submitting.value = true
      try {
        if (isEdit.value) {
          await updatePayment(form.id, form)
          ElMessage.success('更新成功')
        } else {
          await createPayment(form)
          ElMessage.success('创建成功')
        }
        
        showCreateDialog.value = false
        loadPayments()
      } catch (error) {
        console.error('Submit error:', error)
      } finally {
        submitting.value = false
      }
    }
  })
}

const handlePay = async (row) => {
  try {
    await payPayment(row.id)
    ElMessage.success('付款成功')
    loadPayments()
  } catch (error) {
    console.error('Pay error:', error)
  }
}

const handleDialogClose = () => {
  isEdit.value = false
  isViewMode.value = false // 重置查看模式
  Object.assign(form, {
    project_id: '',
    type: '',
    amount: 0,
    description: '',
    payment_date: ''
  })
  formRef.value?.resetFields()
}

const getTypeText = (type) => {
  const texts = {
    salary: '工资',
    social_security: '社保',
    commercial_insurance: '商业保险',
    reimbursement: '报销'
  }
  return texts[type] || '未知'
}

const getStatusType = (status) => {
  const types = {
    draft: 'info',
    pending: 'warning',
    approved: 'success',
    paid: 'success',
    rejected: 'danger'
  }
  return types[status] || 'info'
}

const getStatusText = (status) => {
  const texts = {
    draft: '待提交',
    pending: '待审批',
    approved: '已审批',
    paid: '已付款',
    rejected: '已拒绝'
  }
  return texts[status] || '未知'
}

const formatCurrency = (amount) => {
  return `¥${Number(amount).toFixed(2)}`
}

onMounted(() => {
  loadPayments()
  loadProjects()
})
</script>

<style scoped>
.payments-page {
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
