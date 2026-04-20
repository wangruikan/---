<template>
  <div class="invoices-page">
    <div class="page-header">
      <h1>发票管理</h1>
      <el-button type="primary" @click="showCreateDialog = true">
        <el-icon><Plus /></el-icon>
        新增发票
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
          
          <el-form-item label="发票类型">
            <el-select
              v-model="searchForm.type"
              placeholder="请选择发票类型"
              clearable
            >
              <el-option label="增值税专用发票" value="vat_special" />
              <el-option label="增值税普通发票" value="vat_ordinary" />
              <el-option label="普通发票" value="ordinary" />
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
              <el-option label="已开票" value="issued" />
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
    
    <!-- 发票列表 -->
    <div class="table-section">
      <el-card>
        <el-table
          :data="invoices"
          v-loading="loading"
          stripe
          border
        >
          <el-table-column prop="project_name" label="项目名称" width="150" />
          <el-table-column prop="invoice_number" label="发票号码" width="150" />
          <el-table-column prop="type" label="发票类型" width="120">
            <template #default="{ row }">
              {{ getTypeText(row.type) }}
            </template>
          </el-table-column>
          <el-table-column prop="amount" label="发票金额" width="120">
            <template #default="{ row }">
              {{ formatCurrency(row.amount) }}
            </template>
          </el-table-column>
          <el-table-column prop="tax_amount" label="税额" width="120">
            <template #default="{ row }">
              {{ formatCurrency(row.tax_amount) }}
            </template>
          </el-table-column>
          <el-table-column prop="total_amount" label="价税合计" width="120">
            <template #default="{ row }">
              {{ formatCurrency(row.total_amount) }}
            </template>
          </el-table-column>
          <el-table-column prop="issue_date" label="开票日期" width="120" />
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
                @click="handleIssue(row)"
              >
                开票
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
      :title="isViewMode ? '查看发票' : (isEdit ? '编辑发票' : '新增发票')"
      width="800px"
      @close="handleDialogClose"
    >
      <el-form
        ref="formRef"
        :model="form"
        :rules="formRules"
        label-width="120px"
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
            <el-form-item label="发票类型" prop="type">
              <el-select v-model="form.type" placeholder="请选择发票类型" style="width: 100%" :disabled="isViewMode">
                <el-option label="增值税专用发票" value="vat_special" />
                <el-option label="增值税普通发票" value="vat_ordinary" />
                <el-option label="普通发票" value="ordinary" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="发票号码" prop="invoice_number">
              <el-input v-model="form.invoice_number" placeholder="请输入发票号码" :disabled="isViewMode" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="开票日期" prop="issue_date">
              <el-date-picker
                v-model="form.issue_date"
                type="date"
                placeholder="请选择开票日期"
                style="width: 100%"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
                :disabled="isViewMode"
              />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="发票金额" prop="amount">
              <el-input-number
                v-model="form.amount"
                :min="0"
                :precision="2"
                style="width: 100%"
                :disabled="isViewMode"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="税率" prop="tax_rate">
              <el-input-number
                v-model="form.tax_rate"
                :min="0"
                :max="1"
                :precision="4"
                style="width: 100%"
                :disabled="isViewMode"
              />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="税额" prop="tax_amount">
              <el-input-number
                v-model="form.tax_amount"
                :min="0"
                :precision="2"
                style="width: 100%"
                disabled
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="价税合计" prop="total_amount">
              <el-input-number
                v-model="form.total_amount"
                :min="0"
                :precision="2"
                style="width: 100%"
                disabled
              />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-form-item label="开票内容" prop="content">
          <el-input
            v-model="form.content"
            type="textarea"
            :rows="3"
            placeholder="请输入开票内容"
            :disabled="isViewMode"
          />
        </el-form-item>
        
        <el-form-item label="备注" prop="notes">
          <el-input
            v-model="form.notes"
            type="textarea"
            :rows="2"
            placeholder="请输入备注"
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
import { ref, reactive, onMounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { 
  getInvoices, 
  createInvoice, 
  updateInvoice, 
  deleteInvoice,
  submitInvoice,
  approveInvoice,
  issueInvoice
} from '@/api/invoices'
import { getProjects } from '@/api/projects'

const loading = ref(false)
const submitting = ref(false)
const showCreateDialog = ref(false)
const isEdit = ref(false)
const isViewMode = ref(false) // 添加查看模式标志
const formRef = ref()

const invoices = ref([])
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
  invoice_number: '',
  issue_date: '',
  amount: 0,
  tax_rate: 0.13,
  tax_amount: 0,
  total_amount: 0,
  content: '',
  notes: ''
})

const formRules = {
  project_id: [
    { required: true, message: '请选择项目', trigger: 'change' }
  ],
  type: [
    { required: true, message: '请选择发票类型', trigger: 'change' }
  ],
  invoice_number: [
    { required: true, message: '请输入发票号码', trigger: 'blur' }
  ],
  amount: [
    { required: true, message: '请输入发票金额', trigger: 'blur' }
  ],
  content: [
    { required: true, message: '请输入开票内容', trigger: 'blur' }
  ]
}

// 监听税额计算
watch([
  () => form.amount,
  () => form.tax_rate
], () => {
  form.tax_amount = form.amount * form.tax_rate
  form.total_amount = form.amount + form.tax_amount
})

const loadInvoices = async () => {
  loading.value = true
  try {
    const params = {
      page: pagination.currentPage,
      per_page: pagination.pageSize,
      ...searchForm
    }
    
    const response = await getInvoices(params)
    invoices.value = response.data
    pagination.total = response.total
  } catch (error) {
    console.error('Load invoices error:', error)
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
  loadInvoices()
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
  loadInvoices()
}

const handleCurrentChange = (page) => {
  pagination.currentPage = page
  loadInvoices()
}

const handleView = (row) => {
  // 查看发票详情 - 只读模式
  isEdit.value = false
  isViewMode.value = true
  Object.assign(form, {
    id: row.id,
    project_id: row.project_id,
    type: row.type,
    invoice_number: row.invoice_number,
    issue_date: row.issue_date,
    amount: row.amount,
    tax_rate: row.tax_rate,
    tax_amount: row.tax_amount,
    total_amount: row.total_amount,
    content: row.content,
    notes: row.notes
  })
  showCreateDialog.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  Object.assign(form, row)
  showCreateDialog.value = true
}

const handleSubmitForApproval = async (row) => {
  try {
    await submitInvoice(row.id)
    ElMessage.success('提交成功')
    loadInvoices()
  } catch (error) {
    console.error('Submit error:', error)
  }
}

const handleFormSubmit = async () => {
  await formRef.value.validate(async (valid) => {
    if (valid) {
      submitting.value = true
      try {
        if (isEdit.value) {
          await updateInvoice(form.id, form)
          ElMessage.success('更新成功')
        } else {
          await createInvoice(form)
          ElMessage.success('创建成功')
        }
        
        showCreateDialog.value = false
        loadInvoices()
      } catch (error) {
        console.error('Submit error:', error)
      } finally {
        submitting.value = false
      }
    }
  })
}

const handleIssue = async (row) => {
  try {
    await issueInvoice(row.id)
    ElMessage.success('开票成功')
    loadInvoices()
  } catch (error) {
    console.error('Issue error:', error)
  }
}

const handleDialogClose = () => {
  isEdit.value = false
  isViewMode.value = false // 重置查看模式
  Object.assign(form, {
    project_id: '',
    type: '',
    invoice_number: '',
    issue_date: '',
    amount: 0,
    tax_rate: 0.13,
    tax_amount: 0,
    total_amount: 0,
    content: '',
    notes: ''
  })
  formRef.value?.resetFields()
}

const getTypeText = (type) => {
  const texts = {
    vat_special: '增值税专用发票',
    vat_ordinary: '增值税普通发票',
    ordinary: '普通发票'
  }
  return texts[type] || '未知'
}

const getStatusType = (status) => {
  const types = {
    draft: 'info',
    pending: 'warning',
    approved: 'success',
    issued: 'success',
    rejected: 'danger'
  }
  return types[status] || 'info'
}

const getStatusText = (status) => {
  const texts = {
    draft: '待提交',
    pending: '待审批',
    approved: '已审批',
    issued: '已开票',
    rejected: '已拒绝'
  }
  return texts[status] || '未知'
}

const formatCurrency = (amount) => {
  return `¥${Number(amount).toFixed(2)}`
}

onMounted(() => {
  loadInvoices()
  loadProjects()
})
</script>

<style scoped>
.invoices-page {
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
