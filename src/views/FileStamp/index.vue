<template>
  <div class="file-stamp-page">
    <div class="page-header">
      <div>
        <h2>文件盖章</h2>
        <p>用于处理零散 PDF 文件盖章申请，审批通过后在最后节点完成盖章。</p>
      </div>
      <div class="header-actions">
        <el-button :icon="Refresh" @click="loadList">刷新</el-button>
        <el-button type="primary" :icon="Plus" @click="openCreateDialog">
          创建盖章申请
        </el-button>
      </div>
    </div>

    <el-card shadow="never">
      <el-table :data="records" v-loading="loading" border stripe>
        <el-table-column prop="title" label="文件名称" min-width="220" />
        <el-table-column label="审批用章" min-width="180">
          <template #default="{ row }">
            <span v-if="row.approval_instance?.stamp_selection_mode === 'stamp'">
              {{ row.approval_instance.stamp_company || '-' }} /
              {{ getStampTypeText(row.approval_instance.stamp_type) }}
            </span>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="110" align="center">
          <template #default="{ row }">
            <el-tag :type="getStatusTagType(row.status)">
              {{ getStatusText(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="当前审批人" width="130">
          <template #default="{ row }">
            {{ getCurrentApproverName(row) }}
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="170">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button
              v-if="row.status === 'rejected'"
              link
              type="warning"
              @click="handleResubmit(row)"
            >
              重新提交
            </el-button>
            <el-button
              v-if="row.attachments?.[0]"
              link
              type="primary"
              :icon="Download"
              @click="handleDownload(row)"
            >
              下载
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination">
        <el-pagination
          v-model:current-page="pagination.current"
          :page-size="pagination.pageSize"
          :total="pagination.total"
          layout="total, prev, pager, next, jumper"
          @current-change="loadList"
        />
      </div>
    </el-card>

    <el-dialog
      v-model="createDialogVisible"
      title="创建盖章申请"
      width="720px"
      :close-on-click-modal="false"
      @close="resetCreateForm"
    >
      <el-form label-width="110px">
        <el-form-item label="上传文件" required>
          <el-upload
            class="file-uploader"
            drag
            action="#"
            :auto-upload="false"
            :limit="1"
            :file-list="fileList"
            :on-change="handleFileChange"
            :on-remove="handleFileRemove"
            :on-exceed="handleFileExceed"
            :before-upload="beforeUpload"
            accept=".pdf,application/pdf"
          >
            <el-icon class="el-icon--upload"><UploadFilled /></el-icon>
            <div class="el-upload__text">拖拽文件到这里，或 <em>点击选择</em></div>
            <template #tip>
              <div class="el-upload__tip">只需要上传一个 PDF 文件，文件大小不超过 50MB。</div>
            </template>
          </el-upload>
        </el-form-item>

        <el-form-item label="盖章方式" required>
          <el-radio-group v-model="stampMethod">
            <el-radio value="online">线上盖章</el-radio>
            <el-radio value="offline">线下盖章</el-radio>
          </el-radio-group>
          <div class="stamp-method-tip">
            线上盖章：审批通过后由系统自动加盖印章；线下盖章：审批通过后由工作人员线下盖章。
          </div>
        </el-form-item>

        <ApprovalStampSelector
          v-if="stampMethod === 'online'"
          ref="stampSelectorRef"
          v-model="stampSelection"
          :allow-none="false"
          company-label="盖章公司"
          type-label="选择印章"
        />

        <el-alert
          type="info"
          :closable="false"
          show-icon
          title="提交后会进入审批管理。最后一个审批节点需要先点击“签名盖章”预览并确认，通过后自动完成盖章。"
        />
      </el-form>

      <template #footer>
        <el-button @click="createDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit">
          提交审批
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Download, Plus, Refresh, UploadFilled } from '@element-plus/icons-vue'
import ApprovalStampSelector from '@/components/ApprovalStampSelector.vue'
import {
  createProcess,
  deleteProcess,
  downloadAttachment,
  getProcessList,
  submitProcess,
  uploadAttachment
} from '@/api/processApproval'

const fileList = ref([])
const records = ref([])
const loading = ref(false)
const submitting = ref(false)
const createDialogVisible = ref(false)
const stampSelectorRef = ref(null)
const stampMethod = ref('online')
const stampSelection = ref({})
const pagination = reactive({
  current: 1,
  pageSize: 15,
  total: 0
})

const stampTypeTexts = {
  official: '公章',
  finance: '财务专用章',
  contract: '合同专用章',
  legal_person: '法人章',
  business: '业务专用章',
  hr: '人事部专用章',
  bank: '银行付讫章',
  cash: '现金付讫章'
}

const getDefaultStampSelection = () => ({
  stamp_selection_mode: 'stamp',
  stamp_company: '',
  stamp_type: '',
  stamp_id: null,
  stamp_name: ''
})

const getOfflineStampSelection = () => ({
  stamp_selection_mode: 'none',
  stamp_company: '',
  stamp_type: '',
  stamp_id: null,
  stamp_name: ''
})

const loadList = async () => {
  loading.value = true
  try {
    const res = await getProcessList({
      category: 'file_stamp',
      page: pagination.current,
      per_page: pagination.pageSize
    })
    records.value = res.data?.data || []
    pagination.total = res.data?.total || 0
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '加载列表失败')
  } finally {
    loading.value = false
  }
}

const openCreateDialog = () => {
  resetCreateForm()
  createDialogVisible.value = true
}

const resetCreateForm = () => {
  fileList.value = []
  stampMethod.value = 'online'
  stampSelection.value = getDefaultStampSelection()
}

const beforeUpload = (file) => {
  const fileName = file.name || ''
  const isPDF = file.type === 'application/pdf' || fileName.toLowerCase().endsWith('.pdf')
  if (!isPDF) {
    ElMessage.error('只能上传 PDF 文件')
    return false
  }

  const isLt50M = file.size / 1024 / 1024 < 50
  if (!isLt50M) {
    ElMessage.error('文件大小不能超过 50MB')
    return false
  }
  return true
}

const handleFileChange = (file) => {
  if (!beforeUpload(file.raw || file)) {
    fileList.value = []
    return
  }
  fileList.value = [file]
}

const handleFileRemove = () => {
  fileList.value = []
}

const handleFileExceed = () => {
  ElMessage.warning('一次只能上传一个文件')
}

const getStampTypeText = (type) => stampTypeTexts[type] || type || '-'

const getStatusText = (status) => {
  const texts = {
    draft: '草稿',
    pending: '审批中',
    approved: '已通过',
    rejected: '已驳回'
  }
  return texts[status] || status || '-'
}

const getStatusTagType = (status) => {
  const types = {
    draft: 'info',
    pending: 'warning',
    approved: 'success',
    rejected: 'danger'
  }
  return types[status] || 'info'
}

const getCurrentApproverName = (row) => {
  if (row.current_approver_name && row.current_approver_name !== '-') {
    return row.current_approver_name
  }

  const instance = row.approval_instance || {}
  const records = Array.isArray(instance.records) ? instance.records : []
  const pendingRecord = records.find(record => record.status === 'pending')
  if (pendingRecord?.approver_name) {
    return pendingRecord.approver_name
  }

  const currentStep = Number(instance.current_step || 0)
  const currentRecord = records.find(record => Number(record.step_order || 0) === currentStep)
  return currentRecord?.approver_name || '-'
}

const formatDateTime = (value) => {
  if (!value) return '-'
  return new Date(value).toLocaleString('zh-CN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false
  })
}

const handleDownload = async (row) => {
  const attachment = row.attachments?.[0]
  if (!attachment) return

  try {
    const blob = await downloadAttachment(row.id, attachment.id)
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = attachment.filename || row.title || '盖章文件.pdf'
    link.style.display = 'none'
    document.body.appendChild(link)
    link.click()
    setTimeout(() => {
      document.body.removeChild(link)
      window.URL.revokeObjectURL(url)
    }, 100)
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '下载失败')
  }
}

const handleSubmit = async () => {
  if (fileList.value.length === 0 || !fileList.value[0]?.raw) {
    ElMessage.warning('请先上传文件')
    return
  }

  let stampResult = null
  if (stampMethod.value === 'online') {
    stampResult = stampSelectorRef.value?.validate?.()
    if (stampResult && !stampResult.valid) {
      ElMessage.warning(stampResult.message)
      return
    }
  }

  const stampValue = stampMethod.value === 'online'
    ? (stampResult?.value || stampSelection.value)
    : getOfflineStampSelection()

  submitting.value = true
  let processId = null
  try {
    const file = fileList.value[0].raw
    const createResponse = await createProcess({
      category: 'file_stamp',
      title: file.name,
      description: '文件盖章',
      project_ids: []
    })
    processId = createResponse?.data?.id

    if (!processId) {
      throw new Error('创建盖章申请失败，未返回申请ID')
    }

    const formData = new FormData()
    formData.append('file', file)
    await uploadAttachment(processId, formData)

    await submitProcess(processId, {
      stamp_method: stampMethod.value,
      ...stampValue
    })

    ElMessage.success('文件盖章审批已提交')
    createDialogVisible.value = false
    resetCreateForm()
    await loadList()
  } catch (error) {
    if (processId) {
      try {
        await deleteProcess(processId)
      } catch (cleanupError) {
        console.warn('清理未提交的盖章申请失败:', cleanupError)
      }
    }
    ElMessage.error(error.response?.data?.message || error.message || '提交失败')
  } finally {
    submitting.value = false
  }
}

const handleResubmit = async (row) => {
  const instance = row.approval_instance || {}
  const submitData = {
    stamp_method: instance.stamp_method || 'online',
    stamp_selection_mode: instance.stamp_selection_mode || 'none',
    stamp_company: instance.stamp_company || '',
    stamp_type: instance.stamp_type || '',
    stamp_id: instance.stamp_id || null
  }

  try {
    await submitProcess(row.id, submitData)
    ElMessage.success('已重新提交审批')
    await loadList()
  } catch (error) {
    ElMessage.error(error.response?.data?.message || error.message || '重新提交失败')
  }
}

onMounted(() => {
  resetCreateForm()
  loadList()
})
</script>

<style scoped>
.file-stamp-page {
  min-height: 100%;
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 16px;
}

.page-header h2 {
  margin: 0;
  font-size: 22px;
  font-weight: 600;
  color: #303133;
}

.page-header p {
  margin: 8px 0 0;
  color: #909399;
  font-size: 14px;
}

.header-actions {
  display: flex;
  gap: 10px;
  flex-shrink: 0;
}

.file-uploader {
  width: 100%;
}

.file-uploader :deep(.el-upload) {
  width: 100%;
}

.file-uploader :deep(.el-upload-dragger) {
  width: 100%;
}

.stamp-method-tip {
  margin-top: 8px;
  color: #909399;
  font-size: 12px;
  line-height: 1.5;
}

.pagination {
  margin-top: 16px;
  display: flex;
  justify-content: flex-end;
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
  }

  .header-actions {
    width: 100%;
  }
}
</style>
