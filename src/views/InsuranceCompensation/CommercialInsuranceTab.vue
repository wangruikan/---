<template>
  <div class="commercial-insurance-tab">
    <!-- 搜索栏 -->
    <el-form :model="searchForm" inline style="margin-bottom: 20px">
      <el-form-item label="员工姓名">
        <el-input v-model="searchForm.employee_name" placeholder="请输入员工姓名" clearable style="width: 200px" />
      </el-form-item>
      <el-form-item label="状态">
        <el-select v-model="searchForm.status" placeholder="请选择状态" clearable style="width: 200px">
          <el-option label="已登记" value="registered" />
          <el-option label="材料已提交" value="material_submitted" />
          <el-option label="已完成" value="completed" />
        </el-select>
      </el-form-item>
      <el-form-item>
        <el-button type="primary" @click="handleSearch">查询</el-button>
        <el-button @click="handleReset">重置</el-button>
        <el-button type="success" @click="handleCreate">登记申报</el-button>
      </el-form-item>
    </el-form>

    <!-- 列表 -->
    <el-table :data="records" v-loading="loading" stripe border>
      <el-table-column prop="employee_name" label="员工姓名" width="120" />
      <el-table-column prop="policy_name" label="保单名称" width="200" show-overflow-tooltip />
      <el-table-column prop="incident_date" label="事故日期" width="120">
        <template #default="{ row }">
          {{ formatDate(row.incident_date) }}
        </template>
      </el-table-column>
      <el-table-column prop="incident_description" label="事故描述" min-width="200" show-overflow-tooltip />
      <el-table-column label="当前步骤" width="100">
        <template #default="{ row }">
          步骤{{ row.current_step }}
        </template>
      </el-table-column>
      <el-table-column label="状态" width="120">
        <template #default="{ row }">
          <el-tag :type="getStatusType(row.status)">
            {{ getStatusText(row.status) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="registration_date" label="登记时间" width="180">
        <template #default="{ row }">
          {{ formatDateTime(row.registration_date) }}
        </template>
      </el-table-column>
      <el-table-column label="操作" width="200" fixed="right">
        <template #default="{ row }">
          <el-button type="primary" size="small" @click="handleView(row)">
            查看
          </el-button>
          <el-button 
            v-if="row.current_step === 1" 
            type="warning" 
            size="small" 
            @click="handleStep2(row)"
          >
            提供材料
          </el-button>
          <el-button 
            v-if="row.current_step === 2" 
            type="success" 
            size="small" 
            @click="handleStep3(row)"
          >
            理赔到账
          </el-button>
          <el-button type="danger" size="small" @click="handleDelete(row)">
            删除
          </el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-pagination
      v-model:current-page="pagination.currentPage"
      v-model:page-size="pagination.pageSize"
      :total="pagination.total"
      :page-sizes="[10, 20, 50, 100]"
      layout="total, sizes, prev, pager, next, jumper"
      @size-change="loadRecords"
      @current-change="loadRecords"
      style="margin-top: 20px; justify-content: flex-end"
    />

    <!-- 登记申报对话框 -->
    <el-dialog v-model="createDialogVisible" title="登记商业险申报" width="600px">
      <el-form :model="createForm" :rules="createRules" ref="createFormRef" label-width="120px">
        <el-form-item label="员工" prop="employee_id">
          <el-select 
            v-model="createForm.employee_id" 
            placeholder="请选择员工" 
            style="width: 100%"
            filterable
            @change="handleEmployeeChange"
          >
            <el-option
              v-for="emp in commercialEmployees"
              :key="emp.id"
              :label="`${emp.name} - ${emp.project_name || '无项目'}`"
              :value="emp.id"
            />
          </el-select>
          <div class="form-tip">只显示绑定了商业险保单的员工</div>
        </el-form-item>
        <el-form-item label="保单" prop="policy_id" v-if="availablePolicies.length > 0">
          <el-select v-model="createForm.policy_id" placeholder="请选择保单" style="width: 100%">
            <el-option
              v-for="policy in availablePolicies"
              :key="policy.id"
              :label="policy.name"
              :value="policy.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="事故发生日期" prop="incident_date">
          <el-date-picker
            v-model="createForm.incident_date"
            type="date"
            placeholder="请选择日期"
            value-format="YYYY-MM-DD"
            style="width: 100%"
          />
          <div class="form-tip">建议在24小时内登记</div>
        </el-form-item>
        <el-form-item label="事故描述" prop="incident_description">
          <el-input
            v-model="createForm.incident_description"
            type="textarea"
            :rows="4"
            placeholder="请输入事故描述"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmitCreate" :loading="submitting">
          确定
        </el-button>
      </template>
    </el-dialog>

    <!-- 步骤2：提供材料对话框 -->
    <el-dialog v-model="step2DialogVisible" title="提供材料" width="600px">
      <el-form label-width="120px">
        <el-form-item label="上传附件">
          <el-upload
            :http-request="customUpload"
            :data="{ compensation_record_id: currentRecord?.id, step: 2 }"
            :on-success="handleUploadSuccess"
            :on-error="handleUploadError"
            :show-file-list="false"
            style="margin-bottom: 10px"
          >
            <el-button type="primary" size="small" :icon="Upload">上传附件</el-button>
          </el-upload>
          <div style="color: #909399; font-size: 12px; margin-bottom: 10px">
            请上传理赔所需的相关材料
          </div>
          <el-table 
            :data="step2Attachments" 
            border 
            size="small" 
            v-if="step2Attachments.length > 0"
          >
            <el-table-column prop="file_name" label="文件名" min-width="150" show-overflow-tooltip />
            <el-table-column label="操作" width="120">
              <template #default="{ row }">
                <el-button type="primary" size="small" @click="handleDownload(row)" link>
                  下载
                </el-button>
                <el-button type="danger" size="small" @click="handleDeleteAttachment(row)" link>
                  删除
                </el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="step2DialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmitStep2" :loading="submitting">
          确认提交
        </el-button>
      </template>
    </el-dialog>

    <!-- 查看详情对话框 -->
    <el-dialog v-model="viewDialogVisible" title="商业险申报详情" width="800px">
      <el-descriptions :column="2" border v-if="currentRecord">
        <el-descriptions-item label="员工姓名">{{ currentRecord.employee_name }}</el-descriptions-item>
        <el-descriptions-item label="保单名称">{{ currentRecord.policy_name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="事故日期">{{ currentRecord.incident_date }}</el-descriptions-item>
        <el-descriptions-item label="登记时间">{{ formatDateTime(currentRecord.registration_date) }}</el-descriptions-item>
        <el-descriptions-item label="当前步骤">步骤{{ currentRecord.current_step }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="getStatusType(currentRecord.status)">
            {{ getStatusText(currentRecord.status) }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="材料提交时间" v-if="currentRecord.material_submitted_date">
          {{ formatDateTime(currentRecord.material_submitted_date) }}
        </el-descriptions-item>
        <el-descriptions-item label="理赔到账时间" v-if="currentRecord.claim_received_date">
          {{ formatDateTime(currentRecord.claim_received_date) }}
        </el-descriptions-item>
        <el-descriptions-item label="事故描述" :span="2">
          {{ currentRecord.incident_description || '-' }}
        </el-descriptions-item>
      </el-descriptions>

      <el-divider content-position="left">附件列表</el-divider>
      <el-table :data="currentRecord?.attachments" border v-if="currentRecord?.attachments?.length > 0">
        <el-table-column label="步骤" width="100">
          <template #default="{ row }">
            步骤{{ row.step }}
          </template>
        </el-table-column>
        <el-table-column prop="file_name" label="文件名" min-width="200" show-overflow-tooltip />
        <el-table-column label="操作" width="100">
          <template #default="{ row }">
            <el-button type="primary" size="small" @click="handleDownload(row)" link>
              下载
            </el-button>
          </template>
        </el-table-column>
      </el-table>
      <div v-else style="text-align: center; color: #909399; padding: 20px">
        暂无附件
      </div>

      <template #footer>
        <el-button @click="viewDialogVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Upload } from '@element-plus/icons-vue'
import {
  getCompensationRecords,
  getCommercialInsuranceEmployees,
  createCompensationRecord,
  getCompensationRecordDetail,
  updateStep2,
  updateStep3,
  uploadAttachment,
  deleteAttachment,
  deleteCompensationRecord
} from '@/api/insuranceCompensation'
import { useAccountSetStore } from '@/stores/accountSet'
import { useUserStore } from '@/stores/user'

const accountSetStore = useAccountSetStore()
const userStore = useUserStore()

const loading = ref(false)
const submitting = ref(false)
const createDialogVisible = ref(false)
const step2DialogVisible = ref(false)
const viewDialogVisible = ref(false)

const records = ref([])
const commercialEmployees = ref([])
const currentRecord = ref(null)
const availablePolicies = ref([])

const searchForm = reactive({
  employee_name: '',
  status: ''
})

const pagination = reactive({
  currentPage: 1,
  pageSize: 20,
  total: 0
})

const createForm = reactive({
  employee_id: null,
  policy_id: null,
  incident_date: null,
  incident_description: ''
})

const createFormRef = ref()
const createRules = {
  employee_id: [{ required: true, message: '请选择员工', trigger: 'change' }],
  policy_id: [{ required: true, message: '请选择保单', trigger: 'change' }],
  incident_date: [{ required: true, message: '请选择事故日期', trigger: 'change' }]
}

// 步骤2的附件
const step2Attachments = computed(() => {
  if (!currentRecord.value || !currentRecord.value.attachments) return []
  return currentRecord.value.attachments.filter(att => att.step === 2)
})

// 员工变更时，更新可用保单列表
const handleEmployeeChange = (employeeId) => {
  const employee = commercialEmployees.value.find(emp => emp.id === employeeId)
  if (employee) {
    availablePolicies.value = employee.policies || []
    createForm.policy_id = null
  }
}

// 自定义上传
const customUpload = async (options) => {
  const formData = new FormData()
  formData.append('file', options.file)
  formData.append('compensation_record_id', options.data.compensation_record_id)
  formData.append('step', options.data.step)
  
  try {
    const result = await uploadAttachment(formData)
    if (result.success) {
      options.onSuccess(result)
    } else {
      options.onError(new Error(result.message || '上传失败'))
    }
  } catch (error) {
    options.onError(error)
  }
}

// 加载记录列表
const loadRecords = async () => {
  loading.value = true
  try {
    const params = {
      account_set_id: accountSetStore.currentAccountSetId,
      type: 'commercial',
      page: pagination.currentPage,
      per_page: pagination.pageSize,
      ...searchForm
    }
    
    const response = await getCompensationRecords(params)
    records.value = response.data
    pagination.total = response.total
  } catch (error) {
    console.error('Load records error:', error)
    ElMessage.error('加载数据失败')
  } finally {
    loading.value = false
  }
}

// 加载可申报商业险的员工
const loadCommercialEmployees = async () => {
  try {
    const response = await getCommercialInsuranceEmployees({
      account_set_id: accountSetStore.currentAccountSetId
    })
    commercialEmployees.value = response.data
  } catch (error) {
    console.error('Load employees error:', error)
    ElMessage.error('加载员工列表失败')
  }
}

// 查询
const handleSearch = () => {
  pagination.currentPage = 1
  loadRecords()
}

// 重置
const handleReset = () => {
  searchForm.employee_name = ''
  searchForm.status = ''
  handleSearch()
}

// 登记申报
const handleCreate = async () => {
  // 检查是否有绑定商业险的员工
  await loadCommercialEmployees()
  
  if (commercialEmployees.value.length === 0) {
    ElMessage.warning('没有绑定商业险保单的员工，无法申报')
    return
  }
  
  Object.assign(createForm, {
    employee_id: null,
    policy_id: null,
    incident_date: null,
    incident_description: ''
  })
  availablePolicies.value = []
  createDialogVisible.value = true
}

// 提交登记
const handleSubmitCreate = async () => {
  await createFormRef.value?.validate()
  
  submitting.value = true
  try {
    await createCompensationRecord({
      account_set_id: accountSetStore.currentAccountSetId,
      type: 'commercial',
      ...createForm
    })
    ElMessage.success('登记成功')
    createDialogVisible.value = false
    loadRecords()
  } catch (error) {
    console.error('Create error:', error)
    ElMessage.error(error.response?.data?.message || '登记失败')
  } finally {
    submitting.value = false
  }
}

// 步骤2：提供材料
const handleStep2 = async (row) => {
  try {
    const response = await getCompensationRecordDetail(row.id)
    currentRecord.value = response.data
    step2DialogVisible.value = true
  } catch (error) {
    console.error('Load detail error:', error)
    ElMessage.error('加载详情失败')
  }
}

// 提交步骤2
const handleSubmitStep2 = async () => {
  submitting.value = true
  try {
    await updateStep2(currentRecord.value.id, {})
    ElMessage.success('提交成功')
    step2DialogVisible.value = false
    loadRecords()
  } catch (error) {
    console.error('Submit step2 error:', error)
    ElMessage.error(error.response?.data?.message || '提交失败')
  } finally {
    submitting.value = false
  }
}

// 步骤3：理赔到账
const handleStep3 = async (row) => {
  try {
    await ElMessageBox.confirm('确认理赔已到账？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'success'
    })
    
    await updateStep3(row.id, {})
    ElMessage.success('操作成功')
    loadRecords()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('Submit step3 error:', error)
      ElMessage.error(error.response?.data?.message || '操作失败')
    }
  }
}

// 查看详情
const handleView = async (row) => {
  try {
    const response = await getCompensationRecordDetail(row.id)
    currentRecord.value = response.data
    viewDialogVisible.value = true
  } catch (error) {
    console.error('Load detail error:', error)
    ElMessage.error('加载详情失败')
  }
}

// 删除
const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm('确定要删除该记录吗？删除后将无法恢复。', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    
    await deleteCompensationRecord(row.id)
    ElMessage.success('删除成功')
    loadRecords()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('Delete error:', error)
      ElMessage.error('删除失败')
    }
  }
}

// 上传成功
const handleUploadSuccess = async (response) => {
  if (response.success) {
    ElMessage.success('上传成功')
    // 重新加载详情
    const detailResponse = await getCompensationRecordDetail(currentRecord.value.id)
    currentRecord.value = detailResponse.data
  } else {
    ElMessage.error(response.message || '上传失败')
  }
}

// 上传失败
const handleUploadError = () => {
  ElMessage.error('上传失败')
}

// 删除附件
const handleDeleteAttachment = async (attachment) => {
  try {
    await ElMessageBox.confirm('确定要删除该附件吗？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    
    await deleteAttachment(attachment.id)
    ElMessage.success('删除成功')
    // 重新加载详情
    const response = await getCompensationRecordDetail(currentRecord.value.id)
    currentRecord.value = response.data
  } catch (error) {
    if (error !== 'cancel') {
      console.error('Delete attachment error:', error)
      ElMessage.error('删除失败')
    }
  }
}

const resolveAttachmentRequestUrl = (attachment) => {
  let filePath = String(
    attachment?.file_path ||
    attachment?.path ||
    attachment?.file_url ||
    attachment?.url ||
    ''
  ).trim()

  if (!filePath) return ''

  if (/^https?:\/\//i.test(filePath)) {
    try {
      const urlObj = new URL(filePath)
      filePath = `${urlObj.pathname}${urlObj.search}`
    } catch (error) {
      console.warn('Parse attachment url failed:', error)
    }
  }

  if (filePath.startsWith('/api/') || filePath.startsWith('api/')) {
    return filePath.startsWith('/') ? filePath : `/${filePath}`
  }

  if (filePath.startsWith('/storage/')) {
    filePath = filePath.slice('/storage/'.length)
  } else if (filePath.startsWith('storage/')) {
    filePath = filePath.slice('storage/'.length)
  } else {
    filePath = filePath.replace(/^\/+/, '')
  }

  const [pathOnly, queryString = ''] = filePath.split('?')
  const encodedPath = pathOnly
    .split('/')
    .filter(Boolean)
    .map(segment => encodeURIComponent(segment))
    .join('/')

  if (!encodedPath) return ''
  return queryString ? `/storage/${encodedPath}?${queryString}` : `/storage/${encodedPath}`
}

// 下载附件（仅下载，不预览）
const handleDownload = async (attachment) => {
  const requestUrl = resolveAttachmentRequestUrl(attachment)
  if (!requestUrl) {
    ElMessage.warning('附件路径不存在')
    return
  }

  try {
    const token = localStorage.getItem('token')
    const headers = token ? { Authorization: `Bearer ${token}` } : {}
    const response = await fetch(requestUrl, { method: 'GET', headers })
    if (!response.ok) {
      throw new Error(`Download failed: ${response.status}`)
    }

    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = attachment.file_name || attachment.filename || '附件'
    link.style.display = 'none'
    document.body.appendChild(link)
    link.click()

    setTimeout(() => {
      document.body.removeChild(link)
      window.URL.revokeObjectURL(url)
    }, 100)
  } catch (error) {
    console.error('Download commercial insurance attachment error:', error)
    ElMessage.error('下载失败')
  }
}

// 状态类型
const getStatusType = (status) => {
  const types = {
    registered: 'info',
    material_submitted: 'warning',
    completed: 'success'
  }
  return types[status] || 'info'
}

// 状态文本
const getStatusText = (status) => {
  const texts = {
    registered: '已登记',
    material_submitted: '材料已提交',
    completed: '已完成'
  }
  return texts[status] || status
}

// 格式化日期时间
const formatDateTime = (dateTime) => {
  if (!dateTime) return '-'
  const date = new Date(dateTime)
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  const seconds = String(date.getSeconds()).padStart(2, '0')
  return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`
}

// 格式化日期（只显示日期，不显示时间）
const formatDate = (date) => {
  if (!date) return '-'
  const d = new Date(date)
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

onMounted(() => {
  loadRecords()
})
</script>

<style scoped>
.commercial-insurance-tab {
  padding: 10px 0;
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 5px;
}
</style>
