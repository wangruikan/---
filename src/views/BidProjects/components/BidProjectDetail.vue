<template>
  <el-dialog
    v-model="visible"
    title="项目详情"
    width="1200px"
    :close-on-click-modal="false"
    @close="handleClose"
  >
    <div v-loading="loading">
      <el-tabs v-model="activeTab">
        <!-- 基本信息 -->
        <el-tab-pane label="基本信息" name="info">
          <el-descriptions :column="2" border>
            <el-descriptions-item label="项目编号">{{ project.project_code }}</el-descriptions-item>
            <el-descriptions-item label="项目名称">{{ project.project_name }}</el-descriptions-item>
            <el-descriptions-item label="招标单位">{{ project.client_name || '-' }}</el-descriptions-item>
            <el-descriptions-item label="联系人">{{ project.client_contact || '-' }}</el-descriptions-item>
            <el-descriptions-item label="联系电话">{{ project.client_phone || '-' }}</el-descriptions-item>
            <el-descriptions-item label="项目地点">{{ project.project_location || '-' }}</el-descriptions-item>
            <el-descriptions-item label="服务期限">{{ project.service_period || '-' }}</el-descriptions-item>
            <el-descriptions-item label="项目预算">
              <span v-if="project.project_budget" style="color: #409EFF; font-weight: bold;">
                {{ formatMoney(project.project_budget) }}
              </span>
              <span v-else>-</span>
            </el-descriptions-item>
            <el-descriptions-item label="投标保证金">
              <span v-if="project.bid_bond">{{ formatMoney(project.bid_bond) }}</span>
              <span v-else>-</span>
            </el-descriptions-item>
            <el-descriptions-item label="招标方式">{{ project.bid_method || '-' }}</el-descriptions-item>
            <el-descriptions-item label="信息来源">{{ project.information_source || '-' }}</el-descriptions-item>
            <el-descriptions-item label="投标截止时间">
              <span v-if="project.bid_deadline">{{ formatDateTime(project.bid_deadline) }}</span>
              <span v-else>-</span>
            </el-descriptions-item>
            <el-descriptions-item label="开标时间">
              <span v-if="project.bid_opening_time">{{ formatDateTime(project.bid_opening_time) }}</span>
              <span v-else>-</span>
            </el-descriptions-item>
            <el-descriptions-item label="项目状态">
              <el-tag :type="getStatusType(project.status)">{{ project.status_text }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="投标结果">
              <el-tag v-if="project.bid_result" :type="getResultType(project.bid_result)">
                {{ project.result_text }}
              </el-tag>
              <el-button v-else link type="primary" @click="handleSetResult">设置结果</el-button>
            </el-descriptions-item>
            <el-descriptions-item label="中标金额" v-if="project.bid_result === 'won'">
              <span style="color: #67C23A; font-weight: bold;">{{ formatMoney(project.win_amount) }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="中标日期" v-if="project.bid_result === 'won'">
              {{ project.win_date || '-' }}
            </el-descriptions-item>
            <el-descriptions-item label="负责人">{{ project.responsible_person || '-' }}</el-descriptions-item>
            <el-descriptions-item label="负责部门">{{ project.responsible_department || '-' }}</el-descriptions-item>
            <el-descriptions-item label="项目规模" :span="2">
              <div style="white-space: pre-wrap;">{{ project.project_scale || '-' }}</div>
            </el-descriptions-item>
            <el-descriptions-item label="备注" :span="2">
              <div style="white-space: pre-wrap;">{{ project.remarks || '-' }}</div>
            </el-descriptions-item>
          </el-descriptions>
        </el-tab-pane>

        <!-- 文档管理 -->
        <el-tab-pane label="文档管理" name="documents">
          <div style="margin-bottom: 16px;">
            <el-button type="primary" @click="handleUploadDocument">
              <el-icon><Upload /></el-icon>
              上传文档
            </el-button>
          </div>

          <el-table :data="project.documents" border>
            <el-table-column prop="document_name" label="文档名称" min-width="200" />
            <el-table-column prop="document_type" label="文档类型" width="120">
              <template #default="scope">
                {{ getDocumentTypeText(scope.row.document_type) }}
              </template>
            </el-table-column>
            <el-table-column prop="version" label="版本" width="80" />
            <el-table-column prop="file_type" label="格式" width="80" />
            <el-table-column prop="upload_at" label="上传时间" width="160">
              <template #default="scope">
                {{ formatDateTime(scope.row.upload_at) }}
              </template>
            </el-table-column>
            <el-table-column label="操作" width="150">
              <template #default="scope">
                <el-button link type="primary" size="small" @click="handleDownloadDocument(scope.row)">
                  下载
                </el-button>
                <el-button link type="danger" size="small" @click="handleDeleteDocument(scope.row)">
                  删除
                </el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- 进度记录 -->
        <el-tab-pane label="进度记录" name="progress">
          <div style="margin-bottom: 16px;">
            <el-button type="primary" @click="handleAddLog">
              <el-icon><Plus /></el-icon>
              添加记录
            </el-button>
          </div>

          <el-timeline>
            <el-timeline-item
              v-for="log in project.progress_logs"
              :key="log.id"
              :timestamp="formatDateTime(log.log_time)"
              placement="top"
            >
              <el-card>
                <template #header>
                  <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span><strong>{{ log.log_title }}</strong></span>
                    <el-tag size="small">{{ getLogTypeText(log.log_type) }}</el-tag>
                  </div>
                </template>
                <div style="white-space: pre-wrap;">{{ log.log_content }}</div>
                <div style="margin-top: 8px; font-size: 12px; color: #909399;">
                  操作人：{{ log.operator_name }}
                </div>
              </el-card>
            </el-timeline-item>
          </el-timeline>
        </el-tab-pane>
      </el-tabs>
    </div>

    <!-- 上传文档对话框 -->
    <el-dialog
      v-model="uploadDialogVisible"
      title="上传文档"
      width="600px"
      append-to-body
    >
      <el-form :model="uploadForm" label-width="100px">
        <el-form-item label="文档类型" required>
          <el-select v-model="uploadForm.document_type" placeholder="请选择" style="width: 100%;">
            <el-option label="招标文件" value="bid_invitation" />
            <el-option label="投标文件" value="bid_document" />
            <el-option label="技术方案" value="technical_proposal" />
            <el-option label="报价单" value="quotation" />
            <el-option label="资质证明" value="qualification" />
            <el-option label="保证金凭证" value="bond_receipt" />
            <el-option label="合同文件" value="contract" />
            <el-option label="其他" value="other" />
          </el-select>
        </el-form-item>
        <el-form-item label="文档名称" required>
          <el-input v-model="uploadForm.document_name" placeholder="请输入文档名称" />
        </el-form-item>
        <el-form-item label="版本号">
          <el-input v-model="uploadForm.version" placeholder="如：1.0" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="uploadForm.remarks" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item label="文件" required>
          <el-upload
            ref="uploadRef"
            :auto-upload="false"
            :limit="1"
            :on-change="handleFileChange"
          >
            <el-button type="primary">选择文件</el-button>
          </el-upload>
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="uploadDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleConfirmUpload" :loading="uploading">
          确定上传
        </el-button>
      </template>
    </el-dialog>

    <!-- 添加进度记录对话框 -->
    <el-dialog
      v-model="logDialogVisible"
      title="添加进度记录"
      width="600px"
      append-to-body
    >
      <el-form :model="logForm" label-width="100px">
        <el-form-item label="记录类型" required>
          <el-select v-model="logForm.log_type" placeholder="请选择" style="width: 100%;">
            <el-option label="状态变更" value="status_change" />
            <el-option label="文档上传" value="document_upload" />
            <el-option label="款项支付" value="payment" />
            <el-option label="会议记录" value="meeting" />
            <el-option label="其他" value="other" />
          </el-select>
        </el-form-item>
        <el-form-item label="记录标题" required>
          <el-input v-model="logForm.log_title" placeholder="请输入标题" />
        </el-form-item>
        <el-form-item label="记录时间" required>
          <el-date-picker
            v-model="logForm.log_time"
            type="datetime"
            placeholder="选择时间"
            style="width: 100%;"
            format="YYYY-MM-DD HH:mm"
            value-format="YYYY-MM-DD HH:mm:ss"
          />
        </el-form-item>
        <el-form-item label="记录内容">
          <el-input v-model="logForm.log_content" type="textarea" :rows="5" placeholder="请输入详细内容" />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="logDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleConfirmAddLog" :loading="submitLoading">
          确定
        </el-button>
      </template>
    </el-dialog>

    <!-- 设置投标结果对话框 -->
    <el-dialog
      v-model="resultDialogVisible"
      title="设置投标结果"
      width="500px"
      append-to-body
    >
      <el-form :model="resultForm" label-width="100px">
        <el-form-item label="投标结果" required>
          <el-select v-model="resultForm.bid_result" placeholder="请选择" style="width: 100%;">
            <el-option label="中标" value="won" />
            <el-option label="未中标" value="lost" />
            <el-option label="放弃" value="abandoned" />
          </el-select>
        </el-form-item>
        <el-form-item label="中标金额" v-if="resultForm.bid_result === 'won'" required>
          <el-input v-model="resultForm.win_amount" placeholder="请输入中标金额">
            <template #suffix>元</template>
          </el-input>
        </el-form-item>
        <el-form-item label="中标日期" v-if="resultForm.bid_result === 'won'">
          <el-date-picker
            v-model="resultForm.win_date"
            type="date"
            placeholder="选择日期"
            style="width: 100%;"
            format="YYYY-MM-DD"
            value-format="YYYY-MM-DD"
          />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="resultForm.remarks" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="resultDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleConfirmSetResult" :loading="submitLoading">
          确定
        </el-button>
      </template>
    </el-dialog>
  </el-dialog>
</template>

<script setup>
import { ref, reactive, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Upload } from '@element-plus/icons-vue'
import {
  getBidProjectDetail,
  uploadBidDocument,
  deleteBidDocument,
  addProgressLog,
  setBidResult
} from '@/api/bidProject'

const props = defineProps({
  modelValue: Boolean,
  projectId: [Number, String]
})

const emit = defineEmits(['update:modelValue', 'refresh'])

const visible = ref(false)
const loading = ref(false)
const project = ref({})
const activeTab = ref('info')

// 上传文档
const uploadDialogVisible = ref(false)
const uploading = ref(false)
const uploadRef = ref(null)
const uploadForm = reactive({
  document_type: '',
  document_name: '',
  version: '1.0',
  remarks: '',
  file: null
})

// 添加记录
const logDialogVisible = ref(false)
const submitLoading = ref(false)
const logForm = reactive({
  log_type: '',
  log_title: '',
  log_time: '',
  log_content: ''
})

// 设置结果
const resultDialogVisible = ref(false)
const resultForm = reactive({
  bid_result: '',
  win_amount: '',
  win_date: '',
  remarks: ''
})

watch(() => props.modelValue, (val) => {
  visible.value = val
  if (val && props.projectId) {
    loadDetail()
  }
})

watch(visible, (val) => {
  emit('update:modelValue', val)
})

// 加载详情
const loadDetail = async () => {
  loading.value = true
  try {
    const response = await getBidProjectDetail(props.projectId)
    if (response && response.success) {
      project.value = response.data
    }
  } catch (error) {
    console.error('Load detail error:', error)
    ElMessage.error('加载详情失败')
  } finally {
    loading.value = false
  }
}

// 上传文档
const handleUploadDocument = () => {
  Object.keys(uploadForm).forEach(key => {
    uploadForm[key] = key === 'version' ? '1.0' : ''
  })
  uploadForm.file = null
  uploadDialogVisible.value = true
}

const handleFileChange = (file) => {
  uploadForm.file = file.raw
  if (!uploadForm.document_name) {
    uploadForm.document_name = file.name
  }
}

const handleConfirmUpload = async () => {
  if (!uploadForm.document_type || !uploadForm.document_name || !uploadForm.file) {
    ElMessage.warning('请填写完整信息并选择文件')
    return
  }

  const formData = new FormData()
  formData.append('document_type', uploadForm.document_type)
  formData.append('document_name', uploadForm.document_name)
  formData.append('version', uploadForm.version)
  formData.append('remarks', uploadForm.remarks)
  formData.append('file', uploadForm.file)

  uploading.value = true
  try {
    const response = await uploadBidDocument(props.projectId, formData)
    if (response && response.success) {
      ElMessage.success('上传成功')
      uploadDialogVisible.value = false
      loadDetail()
      emit('refresh')
    }
  } catch (error) {
    console.error('Upload error:', error)
    ElMessage.error('上传失败')
  } finally {
    uploading.value = false
  }
}

// 下载文档
const handleDownloadDocument = (doc) => {
  // 这里需要实现文件下载逻辑
  const baseURL = import.meta.env.VITE_API_BASE_URL || ''
  window.open(`${baseURL}/storage/${doc.file_path}`, '_blank')
}

// 删除文档
const handleDeleteDocument = (doc) => {
  ElMessageBox.confirm('确定删除该文档吗？', '删除确认', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(async () => {
    try {
      const response = await deleteBidDocument(props.projectId, doc.id)
      if (response && response.success) {
        ElMessage.success('删除成功')
        loadDetail()
        emit('refresh')
      }
    } catch (error) {
      console.error('Delete document error:', error)
      ElMessage.error('删除失败')
    }
  }).catch(() => {})
}

// 添加记录
const handleAddLog = () => {
  Object.keys(logForm).forEach(key => {
    logForm[key] = ''
  })
  logForm.log_time = new Date().toISOString().slice(0, 19).replace('T', ' ')
  logDialogVisible.value = true
}

const handleConfirmAddLog = async () => {
  if (!logForm.log_type || !logForm.log_title || !logForm.log_time) {
    ElMessage.warning('请填写完整信息')
    return
  }

  submitLoading.value = true
  try {
    const response = await addProgressLog(props.projectId, logForm)
    if (response && response.success) {
      ElMessage.success('添加成功')
      logDialogVisible.value = false
      loadDetail()
      emit('refresh')
    }
  } catch (error) {
    console.error('Add log error:', error)
    ElMessage.error('添加失败')
  } finally {
    submitLoading.value = false
  }
}

// 设置结果
const handleSetResult = () => {
  Object.keys(resultForm).forEach(key => {
    resultForm[key] = ''
  })
  resultDialogVisible.value = true
}

const handleConfirmSetResult = async () => {
  if (!resultForm.bid_result) {
    ElMessage.warning('请选择投标结果')
    return
  }

  if (resultForm.bid_result === 'won' && !resultForm.win_amount) {
    ElMessage.warning('请输入中标金额')
    return
  }

  submitLoading.value = true
  try {
    const response = await setBidResult(props.projectId, resultForm)
    if (response && response.success) {
      ElMessage.success('设置成功')
      resultDialogVisible.value = false
      loadDetail()
      emit('refresh')
    }
  } catch (error) {
    console.error('Set result error:', error)
    ElMessage.error('设置失败')
  } finally {
    submitLoading.value = false
  }
}

const handleClose = () => {
  visible.value = false
}

// 辅助函数
const formatMoney = (value) => {
  if (!value) return '¥0.00'
  return '¥' + parseFloat(value).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const formatDateTime = (value) => {
  if (!value) return ''
  return value.replace('T', ' ').substring(0, 16)
}

const getStatusType = (status) => {
  const typeMap = {
    preparing: 'info',
    submitted: 'warning',
    opened: 'warning',
    evaluating: 'warning',
    won: 'success',
    lost: 'danger',
    abandoned: 'info',
    contracted: 'success',
    completed: 'success',
    cancelled: 'info'
  }
  return typeMap[status] || 'info'
}

const getResultType = (result) => {
  const typeMap = {
    won: 'success',
    lost: 'danger',
    abandoned: 'info'
  }
  return typeMap[result] || 'info'
}

const getDocumentTypeText = (type) => {
  const typeMap = {
    bid_invitation: '招标文件',
    bid_document: '投标文件',
    technical_proposal: '技术方案',
    quotation: '报价单',
    qualification: '资质证明',
    bond_receipt: '保证金凭证',
    contract: '合同文件',
    other: '其他'
  }
  return typeMap[type] || type
}

const getLogTypeText = (type) => {
  const typeMap = {
    status_change: '状态变更',
    document_upload: '文档上传',
    payment: '款项支付',
    meeting: '会议记录',
    other: '其他'
  }
  return typeMap[type] || type
}
</script>

