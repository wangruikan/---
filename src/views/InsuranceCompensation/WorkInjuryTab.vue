<template>
  <div class="work-injury-tab">
    <!-- 搜索栏 -->
    <el-form :model="searchForm" inline style="margin-bottom: 20px">
      <el-form-item label="员工姓名">
        <el-input v-model="searchForm.employee_name" placeholder="请输入员工姓名" clearable style="width: 200px" />
      </el-form-item>
      <el-form-item label="状态">
        <el-select v-model="searchForm.status" placeholder="请选择状态" clearable style="width: 200px">
          <el-option label="已登记" value="registered" />
          <el-option label="认定成功" value="recognition_success" />
          <el-option label="认定失败" value="recognition_failed" />
          <el-option label="材料已提交" value="material_submitted" />
          <el-option label="已完成" value="completed" />
        </el-select>
      </el-form-item>
      <el-form-item>
        <el-button type="primary" @click="handleSearch">查询</el-button>
        <el-button @click="handleReset">重置</el-button>
        <el-button type="success" @click="handleCreate">登记工伤</el-button>
      </el-form-item>
    </el-form>

    <!-- 列表 -->
    <el-table :data="records" v-loading="loading" stripe border>
      <el-table-column prop="employee_name" label="员工姓名" width="120" />
      <el-table-column prop="project_name" label="项目" width="150" show-overflow-tooltip />
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
            提交认定
          </el-button>
          <el-button 
            v-if="row.current_step === 2 && row.recognition_result === 'success'" 
            type="success" 
            size="small" 
            @click="handleStep3(row)"
          >
            提交材料
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

    <!-- 登记工伤对话框 -->
    <el-dialog v-model="createDialogVisible" title="登记工伤" width="600px">
      <el-form :model="createForm" :rules="createRules" ref="createFormRef" label-width="120px">
        <el-form-item label="员工" prop="employee_id">
          <el-select 
            v-model="createForm.employee_id" 
            placeholder="请选择员工" 
            style="width: 100%"
            filterable
          >
            <el-option
              v-for="emp in workInjuryEmployees"
              :key="emp.id"
              :label="`${emp.name} - ${emp.project_name || '无项目'}`"
              :value="emp.id"
            />
          </el-select>
          <div class="form-tip">只显示参保了社保的员工</div>
        </el-form-item>
        <el-form-item label="事故发生日期" prop="incident_date">
          <el-date-picker
            v-model="createForm.incident_date"
            type="date"
            placeholder="请选择日期"
            value-format="YYYY-MM-DD"
            style="width: 100%"
          />
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

    <!-- 步骤2：提交认定结果对话框 -->
    <el-dialog v-model="step2DialogVisible" title="提交认定结果" width="600px">
      <el-form :model="step2Form" :rules="step2Rules" ref="step2FormRef" label-width="120px">
        <el-form-item label="认定结果" prop="recognition_result">
          <el-radio-group v-model="step2Form.recognition_result">
            <el-radio label="success">认定成功</el-radio>
            <el-radio label="failed">认定失败</el-radio>
          </el-radio-group>
        </el-form-item>
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
            请上传认定成功或失败的相关材料
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
                <el-button type="primary" size="small" @click="handlePreview(row)" link>
                  预览
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
          提交
        </el-button>
      </template>
    </el-dialog>

    <!-- 步骤3：提交材料对话框 -->
    <el-dialog v-model="step3DialogVisible" title="提交材料" width="600px">
      <el-form :model="step3Form" :rules="step3Rules" ref="step3FormRef" label-width="120px">
        <el-form-item label="申报内容" prop="claimed_items">
          <el-checkbox-group v-model="step3Form.claimed_items">
            <el-checkbox label="medical_expense">医药费报销</el-checkbox>
            <el-checkbox label="disability">伤残认定</el-checkbox>
          </el-checkbox-group>
          <div class="form-tip">至少选择一项</div>
        </el-form-item>
        <el-form-item label="上传附件">
          <el-upload
            :http-request="customUpload"
            :data="{ compensation_record_id: currentRecord?.id, step: 3 }"
            :on-success="handleUploadSuccess"
            :on-error="handleUploadError"
            :show-file-list="false"
            style="margin-bottom: 10px"
          >
            <el-button type="primary" size="small" :icon="Upload">上传附件</el-button>
          </el-upload>
          <el-table 
            :data="step3Attachments" 
            border 
            size="small" 
            v-if="step3Attachments.length > 0"
          >
            <el-table-column prop="file_name" label="文件名" min-width="150" show-overflow-tooltip />
            <el-table-column label="操作" width="120">
              <template #default="{ row }">
                <el-button type="primary" size="small" @click="handlePreview(row)" link>
                  预览
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
        <el-button @click="step3DialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmitStep3" :loading="submitting">
          提交
        </el-button>
      </template>
    </el-dialog>

    <!-- 查看详情对话框 -->
    <el-dialog v-model="viewDialogVisible" title="工伤申报详情" width="800px">
      <el-descriptions :column="2" border v-if="currentRecord">
        <el-descriptions-item label="员工姓名">{{ currentRecord.employee_name }}</el-descriptions-item>
        <el-descriptions-item label="项目">{{ currentRecord.project_name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="事故日期">{{ currentRecord.incident_date }}</el-descriptions-item>
        <el-descriptions-item label="登记时间">{{ formatDateTime(currentRecord.registration_date) }}</el-descriptions-item>
        <el-descriptions-item label="当前步骤">步骤{{ currentRecord.current_step }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="getStatusType(currentRecord.status)">
            {{ getStatusText(currentRecord.status) }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="认定结果" v-if="currentRecord.recognition_result">
          <el-tag :type="currentRecord.recognition_result === 'success' ? 'success' : 'danger'">
            {{ currentRecord.recognition_result === 'success' ? '认定成功' : '认定失败' }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="认定时间" v-if="currentRecord.recognition_date">
          {{ formatDateTime(currentRecord.recognition_date) }}
        </el-descriptions-item>
        <el-descriptions-item label="医药费申报" v-if="currentRecord.medical_expense_claimed !== null">
          {{ currentRecord.medical_expense_claimed ? '是' : '否' }}
        </el-descriptions-item>
        <el-descriptions-item label="伤残认定" v-if="currentRecord.disability_claimed !== null">
          {{ currentRecord.disability_claimed ? '是' : '否' }}
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
            <el-button type="primary" size="small" @click="handlePreview(row)" link>
              预览
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
  getWorkInjuryEmployees,
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
const apiBaseUrl = window.location.origin

const loading = ref(false)
const submitting = ref(false)
const createDialogVisible = ref(false)
const step2DialogVisible = ref(false)
const step3DialogVisible = ref(false)
const viewDialogVisible = ref(false)

const records = ref([])
const workInjuryEmployees = ref([])
const currentRecord = ref(null)

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
  incident_date: null,
  incident_description: ''
})

const createFormRef = ref()
const createRules = {
  employee_id: [{ required: true, message: '请选择员工', trigger: 'change' }],
  incident_date: [{ required: true, message: '请选择事故日期', trigger: 'change' }]
}

const step2Form = reactive({
  recognition_result: 'success'
})

const step2FormRef = ref()
const step2Rules = {
  recognition_result: [{ required: true, message: '请选择认定结果', trigger: 'change' }]
}

const step3Form = reactive({
  claimed_items: []
})

const step3FormRef = ref()
const step3Rules = {
  claimed_items: [
    { 
      type: 'array', 
      required: true, 
      message: '请至少选择一项申报内容', 
      trigger: 'change',
      min: 1
    }
  ]
}

// 步骤2和步骤3的附件
const step2Attachments = computed(() => {
  if (!currentRecord.value || !currentRecord.value.attachments) return []
  return currentRecord.value.attachments.filter(att => att.step === 2)
})

const step3Attachments = computed(() => {
  if (!currentRecord.value || !currentRecord.value.attachments) return []
  return currentRecord.value.attachments.filter(att => att.step === 3)
})

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
      type: 'work_injury',
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

// 加载可申报工伤的员工
const loadWorkInjuryEmployees = async () => {
  try {
    const response = await getWorkInjuryEmployees({
      account_set_id: accountSetStore.currentAccountSetId
    })
    workInjuryEmployees.value = response.data
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

// 登记工伤
const handleCreate = async () => {
  // 检查是否有参保社保的员工
  await loadWorkInjuryEmployees()
  
  if (workInjuryEmployees.value.length === 0) {
    ElMessage.warning('没有参保社保的员工，无法申报工伤')
    return
  }
  
  Object.assign(createForm, {
    employee_id: null,
    incident_date: null,
    incident_description: ''
  })
  createDialogVisible.value = true
}

// 提交登记
const handleSubmitCreate = async () => {
  await createFormRef.value?.validate()
  
  submitting.value = true
  try {
    await createCompensationRecord({
      account_set_id: accountSetStore.currentAccountSetId,
      type: 'work_injury',
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

// 步骤2：提交认定
const handleStep2 = async (row) => {
  try {
    const response = await getCompensationRecordDetail(row.id)
    currentRecord.value = response.data
    step2Form.recognition_result = 'success'
    step2DialogVisible.value = true
  } catch (error) {
    console.error('Load detail error:', error)
    ElMessage.error('加载详情失败')
  }
}

// 提交步骤2
const handleSubmitStep2 = async () => {
  await step2FormRef.value?.validate()
  
  submitting.value = true
  try {
    await updateStep2(currentRecord.value.id, step2Form)
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

// 步骤3：提交材料
const handleStep3 = async (row) => {
  try {
    const response = await getCompensationRecordDetail(row.id)
    currentRecord.value = response.data
    step3Form.claimed_items = []
    step3DialogVisible.value = true
  } catch (error) {
    console.error('Load detail error:', error)
    ElMessage.error('加载详情失败')
  }
}

// 提交步骤3
const handleSubmitStep3 = async () => {
  await step3FormRef.value?.validate()
  
  submitting.value = true
  try {
    await updateStep3(currentRecord.value.id, {
      medical_expense_claimed: step3Form.claimed_items.includes('medical_expense'),
      disability_claimed: step3Form.claimed_items.includes('disability')
    })
    ElMessage.success('提交成功')
    step3DialogVisible.value = false
    loadRecords()
  } catch (error) {
    console.error('Submit step3 error:', error)
    ElMessage.error(error.response?.data?.message || '提交失败')
  } finally {
    submitting.value = false
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

// 预览附件
const handlePreview = (attachment) => {
  const url = attachment.file_url || `${apiBaseUrl}/storage/${attachment.file_path}`
  window.open(url, '_blank')
}

// 状态类型
const getStatusType = (status) => {
  const types = {
    registered: 'info',
    recognition_success: 'success',
    recognition_failed: 'danger',
    material_submitted: 'warning',
    completed: 'success'
  }
  return types[status] || 'info'
}

// 状态文本
const getStatusText = (status) => {
  const texts = {
    registered: '已登记',
    recognition_success: '认定成功',
    recognition_failed: '认定失败',
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
.work-injury-tab {
  padding: 10px 0;
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 5px;
}
</style>
