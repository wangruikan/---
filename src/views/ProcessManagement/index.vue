<template>
  <div class="process-management-container">
    <!-- 筛选区域 -->
    <el-card class="filter-card">
      <el-form :model="filterForm" inline>
        <el-form-item label="汇总类型">
          <el-select v-model="filterForm.category" placeholder="全部类型" clearable style="width: 120px;">
            <el-option label="社保汇总" value="social_insurance" />
            <el-option label="公积金汇总" value="housing_fund" />
          </el-select>
        </el-form-item>
        <el-form-item label="月份">
          <el-date-picker
            v-model="filterForm.month"
            type="month"
            placeholder="选择月份"
            format="YYYY-MM"
            value-format="YYYY-MM"
            clearable
          />
        </el-form-item>
        <el-form-item label="流程状态">
          <el-select v-model="filterForm.status" placeholder="全部状态" clearable style="width: 150px;">
            <el-option label="草稿" value="draft" />
            <el-option label="待审批" value="pending" />
            <el-option label="已通过" value="approved" />
            <el-option label="已驳回" value="rejected" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
          <el-button :icon="Refresh" @click="handleReset">重置</el-button>
          <el-button type="success" :icon="Plus" @click="handleCreate">发起流程</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 流程列表 -->
    <el-card class="table-card">
      <el-table :data="processList" v-loading="loading" border stripe>
        <el-table-column prop="id" label="流程ID" width="80" />
        <el-table-column label="汇总类型" width="110" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.category === 'housing_fund'" type="warning">公积金汇总</el-tag>
            <el-tag v-else type="primary">社保汇总</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="流程标题" min-width="200" />
        <el-table-column prop="month" label="月份" width="100" />
        <el-table-column label="发起人" width="120">
          <template #default="{ row }">
            {{ row.initiator?.name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="当前审批人" width="120">
          <template #default="{ row }">
            {{ getCurrentApproverName(row) }}
          </template>
        </el-table-column>
        <el-table-column label="盖章方式" width="110" align="center">
          <template #default="{ row }">
            <el-tag
              v-if="row.approval_instance?.stamp_method === 'offline'"
              type="warning"
            >
              线下
            </el-tag>
            <el-tag
              v-else-if="row.approval_instance?.stamp_method === 'online'"
              type="success"
            >
              线上
            </el-tag>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.status === 'draft'" type="info">草稿</el-tag>
            <el-tag v-else-if="row.status === 'pending'" type="warning">待审批</el-tag>
            <el-tag v-else-if="row.status === 'approved'" type="success">已通过</el-tag>
            <el-tag v-else-if="row.status === 'rejected'" type="danger">已驳回</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="附件数量" width="100" align="center">
          <template #default="{ row }">
            {{ row.attachments?.length || 0 }}
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="160">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="300" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" :icon="View" @click="handleView(row)">查看</el-button>
            <el-button 
              v-if="row.status === 'draft'" 
              link 
              type="success" 
              :icon="Promotion" 
              @click="handleSubmit(row)"
            >
              提交
            </el-button>
            <el-button 
              v-if="row.status === 'approved' && !row.has_payment_request" 
              link 
              type="warning" 
              @click="openPaymentRequestDialog(row.id)"
            >
              发起付款
            </el-button>
            <el-button 
              v-if="['draft', 'rejected'].includes(row.status)" 
              link 
              type="danger" 
              :icon="Delete" 
              @click="handleDelete(row)"
            >
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-container">
        <el-pagination
          v-model:current-page="pagination.current"
          v-model:page-size="pagination.pageSize"
          :total="pagination.total"
          :page-sizes="[10, 15, 20, 50]"
          layout="total, sizes, prev, pager, next, jumper"
          @current-change="loadProcessList"
          @size-change="loadProcessList"
        />
      </div>
    </el-card>

    <!-- 创建/编辑对话框 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="600px"
      :close-on-click-modal="false"
    >
      <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
        <el-form-item label="汇总类型" prop="category">
          <el-radio-group v-model="form.category">
            <el-radio value="social_insurance">社保汇总</el-radio>
            <el-radio value="housing_fund">公积金汇总</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="流程标题" prop="title">
          <el-input v-model="form.title" placeholder="请输入流程标题" />
        </el-form-item>
        <el-form-item label="关联项目" prop="project_id">
          <el-select
            v-model="form.project_id"
            placeholder="请选择项目"
            style="width: 100%;"
            @change="handleProjectChange"
          >
            <el-option
              v-for="project in projectList.filter(p => p && p.id)"
              :key="project.id"
              :label="project.name"
              :value="project.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="流程描述" prop="description">
          <el-input
            v-model="form.description"
            type="textarea"
            :rows="3"
            placeholder="请输入流程描述（可选）"
          />
        </el-form-item>
        <el-form-item label="附件" required>
          <div style="margin-bottom: 10px;">
            <el-button 
              type="success" 
              @click="showFormToWordDialog = true"
              :disabled="!currentProcessId"
            >
              <el-icon><DocumentAdd /></el-icon>
              填写表格生成Word
            </el-button>
            <span style="margin-left: 10px; color: #f56c6c; font-size: 12px;">* 必须至少上传1个附件</span>
          </div>
          <el-upload
            action="#"
            :http-request="handleUploadRequest"
            :on-success="handleUploadSuccess"
            :on-error="handleUploadError"
            :before-upload="beforeUpload"
            :file-list="fileList"
            :disabled="!currentProcessId"
            multiple
          >
            <el-button type="primary" :icon="UploadFilled" :disabled="!currentProcessId">
              上传附件
            </el-button>
            <template #tip>
              <div class="el-upload__tip">
                请先保存流程后再上传附件，文件大小不超过50MB
              </div>
            </template>
          </el-upload>
          <el-table v-if="fileList.length > 0" :data="fileList" border style="margin-top: 10px;">
            <el-table-column prop="name" label="文件名" />
            <el-table-column label="大小" width="100">
              <template #default="{ row }">
                {{ formatFileSize(row.size || row.file_size) }}
              </template>
            </el-table-column>
            <el-table-column label="操作" width="150">
              <template #default="{ row }">
                <el-button link type="primary" :icon="Download" @click="downloadAttachment(row)">
                  下载
                </el-button>
                <el-button link type="danger" :icon="Delete" @click="deleteAttachmentFile(row)">
                  删除
                </el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSave">保存</el-button>
        <el-button 
          v-if="currentProcessId && fileList.length > 0" 
          type="success" 
          @click="handleSubmit({ id: currentProcessId })"
        >
          提交审批
        </el-button>
      </template>
    </el-dialog>

    <!-- 表格填写生成Word组件 -->
    <FormToWordGenerator 
      v-model="showFormToWordDialog" 
      title="汇总申请表"
      @word-generated="handleWordGenerated"
    />

    <!-- 查看详情对话框 -->
    <el-dialog
      v-model="detailVisible"
      title="流程详情"
      width="700px"
    >
      <el-descriptions :column="2" border>
        <el-descriptions-item label="汇总类型">
          <el-tag v-if="detailData.category === 'housing_fund'" type="warning">公积金汇总</el-tag>
          <el-tag v-else type="primary">社保汇总</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="月份">{{ detailData.month }}</el-descriptions-item>
        <el-descriptions-item label="流程标题" :span="2">{{ detailData.title }}</el-descriptions-item>
        <el-descriptions-item label="发起人">{{ detailData.initiator?.name }}</el-descriptions-item>
        <el-descriptions-item label="当前审批人">
          {{ getCurrentApproverName(detailData) }}
        </el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag v-if="detailData.status === 'draft'" type="info">草稿</el-tag>
          <el-tag v-else-if="detailData.status === 'pending'" type="warning">待审批</el-tag>
          <el-tag v-else-if="detailData.status === 'approved'" type="success">已通过</el-tag>
          <el-tag v-else-if="detailData.status === 'rejected'" type="danger">已驳回</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="创建时间">
          {{ formatDateTime(detailData.created_at) }}
        </el-descriptions-item>
        <el-descriptions-item label="关联项目" :span="2">
          <el-tag 
            v-for="projectId in detailData.project_ids" 
            :key="projectId"
            style="margin-right: 8px;"
          >
            {{ getProjectName(projectId) }}
          </el-tag>
          <span v-if="!detailData.project_ids || detailData.project_ids.length === 0">-</span>
        </el-descriptions-item>
        <el-descriptions-item label="流程描述" :span="2">
          {{ detailData.description || '-' }}
        </el-descriptions-item>
      </el-descriptions>

      <div style="margin-top: 20px;">
        <h4>附件列表</h4>
        <!-- 草稿状态下显示上传按钮和填写表格按钮 -->
        <div v-if="detailData.status === 'draft'" style="margin-bottom: 10px;">
          <el-upload
            action="#"
            :http-request="handleUploadRequest"
            :on-success="handleUploadSuccess"
            :on-error="handleUploadError"
            :show-file-list="false"
            :before-upload="beforeUpload"
            style="display: inline-block; margin-right: 10px;"
          >
            <el-button type="primary" :icon="UploadFilled">上传附件</el-button>
          </el-upload>
          <el-button 
            type="success" 
            :icon="DocumentAdd"
            @click="openFormToWordDialogForDetail"
          >
            填写表格生成Word
          </el-button>
        </div>
        <el-table :data="detailData.attachments" border>
          <el-table-column prop="filename" label="文件名" />
          <el-table-column label="大小" width="100">
            <template #default="{ row }">
              {{ formatFileSize(row.file_size) }}
            </template>
          </el-table-column>
          <el-table-column label="上传人" width="100">
            <template #default="{ row }">
              {{ row.uploader?.name }}
            </template>
          </el-table-column>
          <el-table-column label="操作" width="150">
            <template #default="{ row }">
              <el-button link type="primary" :icon="Download" @click="downloadAttachment(row)">
                下载
              </el-button>
              <el-button 
                v-if="detailData.status === 'draft'"
                link 
                type="danger" 
                :icon="Delete" 
                @click="handleDeleteAttachment(row)"
              >
                删除
              </el-button>
            </template>
          </el-table-column>
        </el-table>
      </div>

      <!-- 发起付款申请按钮 - 暂时隐藏 -->
      <!-- <div v-if="detailData.status === 'approved'" style="margin-top: 20px; text-align: center;">
        <el-button 
          type="success" 
          size="large"
          :loading="creatingPayment"
          @click="openPaymentRequestDialog(detailData.id)"
        >
          发起付款申请
        </el-button>
      </div> -->
    </el-dialog>

    <!-- 发起付款申请对话框 -->
    <el-dialog
      v-model="paymentRequestDialogVisible"
      title="发起付款申请"
      width="800px"
      :close-on-click-modal="false"
    >
      <el-form label-width="100px">
        <el-form-item label="汇总类型">
          <el-tag v-if="paymentRequestData.category === 'housing_fund'" type="warning" size="large">公积金汇总</el-tag>
          <el-tag v-else type="primary" size="large">社保汇总</el-tag>
        </el-form-item>
        
        <el-form-item label="流程信息">
          <el-input v-model="paymentRequestData.processTitle" disabled />
        </el-form-item>
        
        <el-form-item label="付款金额" required>
          <el-input 
            v-model="paymentRequestData.amount" 
            type="number"
            placeholder="请输入付款金额"
            :min="0"
            :step="0.01"
          >
            <template #append>元</template>
          </el-input>
        </el-form-item>

        <el-form-item label="盖章方式" required>
          <el-radio-group v-model="paymentRequestData.stamp_method">
            <el-radio value="online">线上盖章</el-radio>
            <el-radio value="offline">线下盖章</el-radio>
          </el-radio-group>
          <div style="margin-top: 8px; color: #909399; font-size: 12px;">
            线上盖章：系统自动在PDF上添加印章；线下盖章：需要手动在纸质文件上盖章
          </div>
        </el-form-item>

        <el-form-item label="备注">
          <el-input
            v-model="paymentRequestData.remarks"
            type="textarea"
            :rows="3"
            placeholder="请输入备注信息"
          />
        </el-form-item>

        <!-- 付款表单字段组件 -->
        <PaymentFormFields ref="paymentFormFieldsRef" v-model="paymentFormFields" />

        <!-- 付款附件上传组件（包含发票、其他附件、签名盖章） -->
        <PaymentAttachmentUploader
          ref="paymentAttachmentUploaderRef"
          v-model:invoice-file-list="invoiceFileList"
          v-model:other-file-list="paymentFileList"
          :invoice-limit="10"
          :other-limit="5"
          :show-form-generator="true"
          :show-upload-later="true"
          form-button-text="填写表格生成PDF"
          form-title="付款申请表"
        />
      </el-form>

      <template #footer>
        <el-button @click="paymentRequestDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="confirmCreatePaymentRequest" :loading="creatingPayment">
          提交付款申请
        </el-button>
      </template>
    </el-dialog>

    <!-- 盖章方式选择对话框 -->
    <el-dialog
      v-model="submitStampDialogVisible"
      title="提交审批"
      width="500px"
      :close-on-click-modal="false"
    >
      <el-form :model="submitStampForm" label-width="100px">
        <el-form-item label="盖章方式" required>
          <el-radio-group v-model="submitStampForm.stamp_method">
            <el-radio value="online">线上盖章</el-radio>
            <el-radio value="offline">线下盖章</el-radio>
          </el-radio-group>
          <div style="margin-top: 8px; color: #909399; font-size: 12px;">
            线上盖章：系统自动在PDF上添加印章；线下盖章：需要手动在纸质文件上盖章
          </div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="submitStampDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleConfirmSubmitWithStamp">
          确认提交
        </el-button>
      </template>
    </el-dialog>


  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  Search, Refresh, Plus, View, Promotion, CircleCheck, Delete, UploadFilled, Download, Paperclip, Document, DocumentAdd
} from '@element-plus/icons-vue'
import { useAccountSetStore } from '@/stores/accountSet'
import PaymentAttachmentUploader from '@/components/PaymentAttachmentUploader.vue'
import PaymentFormFields from '@/components/PaymentFormFields.vue'
import FormToWordGenerator from '@/components/FormToWordGenerator.vue'
import {
  getProcessList, getProcessDetail, createProcess, uploadAttachment,
  deleteAttachment as deleteProcessAttachment,
  downloadAttachment as downloadProcessAttachment,
  submitProcess as submitProcessApi,
  deleteProcess as deleteProcessApi
} from '@/api/processApproval'
import { getProjects } from '@/api/projects'
import { createFromProcessApproval } from '@/api/paymentApplication'
import { useRouter } from 'vue-router'

const accountSetStore = useAccountSetStore()
const router = useRouter()

// 项目列表
const projectList = ref([])

// 发起付款申请的加载状态
const creatingPayment = ref(false)

// 发起付款申请对话框
const paymentRequestDialogVisible = ref(false)
const paymentAttachmentUploaderRef = ref(null)
const paymentFileList = ref([])
const invoiceFileList = ref([])
const originalAttachments = ref([]) // 原保险汇总的附件（自动带入审批）
const paymentFormFields = ref({}) // 付款表单字段组件数据
const paymentFormFieldsRef = ref(null)
const paymentRequestData = reactive({
  processApprovalId: null,
  category: 'social_insurance',
  processTitle: '',
  amount: '',
  remarks: '',
  projectIds: [],
  projectName: '',
  stamp_method: 'online' // 默认线上盖章
})

// 盖章方式选择对话框
const submitStampDialogVisible = ref(false)
const submitStampForm = reactive({
  processId: null,
  stamp_method: 'online' // 默认线上盖章
})

// 筛选表单
const filterForm = reactive({
  category: '',
  month: '',
  status: ''
})

// 流程列表
const loading = ref(false)
const processList = ref([])
const pagination = reactive({
  current: 1,
  pageSize: 15,
  total: 0
})

// 对话框
const dialogVisible = ref(false)
const dialogTitle = ref('发起流程')
const showFormToWordDialog = ref(false)
const formRef = ref(null)
const form = reactive({
  category: 'social_insurance',
  title: '',
  project_id: null,
  description: ''
})
const rules = {
  category: [{ required: true, message: '请选择汇总类型', trigger: 'change' }],
  title: [{ required: true, message: '请输入流程标题', trigger: 'blur' }]
}

// 当前流程ID（用于上传附件）
const currentProcessId = ref(null)

// 文件列表
const fileList = ref([])

// 详情对话框
const detailVisible = ref(false)
const detailData = ref({})

// 统一附件上传：走 request 拦截器，确保鉴权头与账套参数和其他接口一致
const handleUploadRequest = (uploadOptions) => {
  const processId = detailVisible.value && detailData.value?.id
    ? detailData.value.id
    : currentProcessId.value

  if (!processId) {
    return Promise.reject(new Error('请先保存流程后再上传附件'))
  }

  const formData = new FormData()
  const uploadFile = uploadOptions.file?.raw || uploadOptions.file
  formData.append('file', uploadFile, uploadOptions.file?.name || uploadFile?.name || 'upload-file')

  // 返回 Promise 给 el-upload，由组件内部统一触发 onSuccess/onError，避免重复回调
  return uploadAttachment(processId, formData)
}

// 删除详情弹窗中的附件
const handleDeleteAttachment = async (attachment) => {
  try {
    await ElMessageBox.confirm('确定要删除该附件吗？', '提示', {
      type: 'warning'
    })
    
    await deleteProcessAttachment(detailData.value.id, attachment.id)
    ElMessage.success('附件删除成功')
    
    // 重新加载详情
    const res = await getProcessDetail(detailData.value.id)
    detailData.value = res.data
    
    // 刷新列表
    loadProcessList()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error('删除失败')
    }
  }
}


// 加载流程列表
const loadProcessList = async () => {
  loading.value = true
  try {
    const params = {
      page: pagination.current,
      per_page: pagination.pageSize,
      ...filterForm
    }
    const res = await getProcessList(params)
    processList.value = res.data.data
    pagination.total = res.data.total
  } catch (error) {
    ElMessage.error('加载流程列表失败')
  } finally {
    loading.value = false
  }
}

// 查询
const handleSearch = () => {
  pagination.current = 1
  loadProcessList()
}

// 重置
const handleReset = () => {
  filterForm.category = ''
  filterForm.month = ''
  filterForm.status = ''
  pagination.current = 1
  loadProcessList()
}

// 加载项目列表
const loadProjects = async () => {
  try {
    const res = await getProjects({ 
      per_page: 1000
    })
    // 项目API返回的是分页数据，实际数据在 data.data 中
    projectList.value = res.data?.data || res.data || []
  } catch (error) {
    console.error('加载项目列表失败:', error)
    projectList.value = []
  }
}

// 项目选择改变
const handleProjectChange = (value) => {
  // 可以在这里添加额外逻辑
}

// 获取项目名称
const getProjectName = (projectId) => {
  if (!projectId) return '-'
  const project = projectList.value.find(p => p && p.id === projectId)
  return project ? project.name : `项目${projectId}`
}

const parseProjectIds = (value) => {
  if (Array.isArray(value)) {
    return value.filter(item => item !== null && item !== undefined && String(item).trim() !== '')
  }
  if (typeof value === 'number') {
    return [value]
  }
  if (typeof value === 'string') {
    const trimmed = value.trim()
    if (!trimmed) return []
    try {
      const parsed = JSON.parse(trimmed)
      if (Array.isArray(parsed)) {
        return parsed.filter(item => item !== null && item !== undefined && String(item).trim() !== '')
      }
    } catch (error) {
      // ignore invalid JSON and fallback to comma-separated mode
    }
    return trimmed
      .split(',')
      .map(item => item.trim())
      .filter(item => item !== '')
  }
  return []
}

const validatePaymentRequestForm = (formData) => {
  const fieldMap = {
    applyDate: '申请日期',
    unitName: '单位名称',
    reimburser: '报销人',
    invoiceNumber: '发票号码',
    invoiceType: '发票类型',
    invoiceAmount: '开票金额',
    taxRate: '税率',
    taxAmount: '税金',
    deductionAmount: '扣除额',
    amountExcludingTax: '不含税金额',
    paymentDate: '打款日期',
    expenditureAmount: '支出金额',
    summary: '摘要',
    project: '项目',
    projectName: '项目名称'
  }

  const missingFields = Object.entries(fieldMap)
    .filter(([key]) => {
      const value = formData[key]
      return value === undefined || value === null || String(value).trim() === ''
    })
    .map(([, label]) => label)

  if (missingFields.length > 0) {
    ElMessage.error(`请完整填写付款信息：${missingFields.join('、')}`)
    return false
  }

  return true
}

// 获取当前审批人名称
const getCurrentApproverName = (row) => {
  if (!row.approval_instance || !row.approval_instance.records) {
    return '-'
  }
  const pendingRecord = row.approval_instance.records.find(r => r.status === 'pending')
  return pendingRecord ? pendingRecord.approver_name : '-'
}

// 创建流程
const handleCreate = () => {
  dialogTitle.value = '发起流程'
  currentProcessId.value = null
  fileList.value = []
  Object.assign(form, {
    category: 'social_insurance',
    title: '',
    project_id: null,
    description: ''
  })
  dialogVisible.value = true
}

// 保存流程
const handleSave = async () => {
  if (!formRef.value) return
  
  await formRef.value.validate(async (valid) => {
    if (!valid) return

    try {
      if (!currentProcessId.value) {
        // 创建新流程
        const res = await createProcess({
          ...form,
          project_ids: form.project_id ? [form.project_id] : []
        })
        currentProcessId.value = res.data.id
        ElMessage.success('流程创建成功，现在可以上传附件了')
        // 不关闭对话框，让用户上传附件
      } else {
        ElMessage.success('流程保存成功')
        dialogVisible.value = false
        loadProcessList()
      }
    } catch (error) {
      ElMessage.error(error.response?.data?.message || '保存失败')
    }
  })
}

// 上传前检查
const beforeUpload = (file) => {
  // 只检查文件大小，不检查是否保存（详情弹窗中的流程已经存在）
  const isLt50M = file.size / 1024 / 1024 < 50
  if (!isLt50M) {
    ElMessage.error('文件大小不能超过 50MB!')
    return false
  }
  return true
}

// 上传成功
const handleUploadSuccess = (response, file) => {
  if (response && response.success) {
    ElMessage.success('附件上传成功')
    
    // 如果是在编辑表单中上传
    if (currentProcessId.value) {
      fileList.value.push({
        id: response.data.id,
        name: response.data.filename,
        file_path: response.data.file_path,
        file_size: response.data.file_size,
        uploader: response.data.uploader
      })
    }
    
    // 如果是在详情弹窗中上传
    if (detailVisible.value && detailData.value.id) {
      // 重新加载详情
      getProcessDetail(detailData.value.id).then(res => {
        detailData.value = res.data
      })
    }
    
    // 刷新流程列表，确保附件数据同步
    loadProcessList()
  } else {
    ElMessage.error(response?.message || '上传失败')
  }
}

// 上传失败
const handleUploadError = (error) => {
  ElMessage.error('附件上传失败')
}

// 删除附件
const deleteAttachmentFile = async (file) => {
  try {
    await ElMessageBox.confirm('确定要删除该附件吗？', '提示', {
      type: 'warning'
    })
    
    await deleteProcessAttachment(currentProcessId.value, file.id)
    ElMessage.success('附件删除成功')
    fileList.value = fileList.value.filter(f => f.id !== file.id)
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error('删除失败')
    }
  }
}

// 下载附件
const downloadAttachment = async (attachment) => {
  try {
    const processId = detailVisible.value && detailData.value?.id
      ? detailData.value.id
      : currentProcessId.value

    if (!processId || !attachment?.id) {
      ElMessage.error('下载失败：缺少流程或附件信息')
      return
    }

    const response = await downloadProcessAttachment(processId, attachment.id)
    const blob = response instanceof Blob
      ? response
      : new Blob([response], { type: attachment.mime_type || 'application/octet-stream' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = attachment.filename || attachment.name || '附件'
    link.style.display = 'none'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
  } catch (error) {
    ElMessage.error('下载失败')
  }
}

// 格式化文件大小
const formatFileSize = (bytes) => {
  if (!bytes) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
}

// 查看详情
const handleView = async (row) => {
  try {
    const res = await getProcessDetail(row.id)
    detailData.value = res.data
    detailVisible.value = true
  } catch (error) {
    ElMessage.error('加载详情失败')
  }
}

// 提交流程
const handleSubmit = async (row) => {
  try {
    // 如果是从列表直接提交，需要先获取详情检查附件
    let attachmentCount = 0
    if (row.attachments) {
      // 从列表行数据获取附件数量
      attachmentCount = row.attachments.length
    } else if (fileList.value && fileList.value.length > 0) {
      // 从对话框的文件列表获取
      attachmentCount = fileList.value.length
    } else {
      // 需要从后端获取详情
      const res = await getProcessDetail(row.id)
      if (res.data && res.data.attachments) {
        attachmentCount = res.data.attachments.length
      }
    }
    
    // 验证必须至少有1个附件
    if (attachmentCount === 0) {
      ElMessage.warning('请至少上传1个附件才能提交')
      return
    }
    
    // 显示盖章方式选择对话框
    submitStampDialogVisible.value = true
    submitStampForm.processId = row.id
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.response?.data?.message || '提交失败')
    }
  }
}

// 确认提交（带盖章方式）
const handleConfirmSubmitWithStamp = async () => {
  try {
    await submitProcessApi(submitStampForm.processId, {
      stamp_method: submitStampForm.stamp_method
    })
    ElMessage.success('流程提交成功')
    submitStampDialogVisible.value = false
    dialogVisible.value = false
    loadProcessList()
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '提交失败')
  }
}

// 处理Word文档生成
const handleWordGenerated = async ({ file, fileName }) => {
  if (!currentProcessId.value) {
    ElMessage.error('请先保存流程')
    return
  }
  
  console.log('Word文档已生成:', fileName)
  
  try {
    // 创建FormData并上传
    const formData = new FormData()
    formData.append('file', file, fileName)
    
    // 上传文件
    const response = await uploadAttachment(currentProcessId.value, formData)
    
    if (response && response.success) {
      ElMessage.success('Word文档已自动上传')
      // 重新加载流程详情以更新附件列表
      await loadProcessList()
      
      // 更新当前流程的文件列表
      const process = processList.value.find(p => p.id === currentProcessId.value)
      if (process && process.attachments) {
        fileList.value = process.attachments.map(att => ({
          name: att.filename,
          file_name: att.filename,
          size: att.size,
          file_size: att.size,
          url: att.path,
          id: att.id
        }))
      }
      
      // 如果是从详情弹窗打开的，也需要更新详情数据
      if (detailVisible.value && detailData.value.id === currentProcessId.value) {
        detailData.value = process
      }
    }
  } catch (error) {
    console.error('上传Word文档失败:', error)
    ElMessage.error('Word文档上传失败: ' + (error.response?.data?.message || error.message))
  }
}

// 从详情弹窗打开填写表格对话框
const openFormToWordDialogForDetail = () => {
  if (!detailData.value || !detailData.value.id) {
    ElMessage.error('流程信息不完整')
    return
  }
  
  // 设置当前流程ID
  currentProcessId.value = detailData.value.id
  
  // 打开对话框
  showFormToWordDialog.value = true
}



// 删除流程
const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm('确定要删除该流程吗？', '提示', {
      type: 'warning'
    })

    await deleteProcessApi(row.id)
    ElMessage.success('流程删除成功')
    loadProcessList()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }
}

// 打开发起付款申请对话框
const openPaymentRequestDialog = async (processApprovalId) => {
  try {
    // 先加载流程详情
    const response = await getProcessDetail(processApprovalId)
    const detail = response.data || response // 兼容不同的返回格式

    const projectIds = parseProjectIds(detail.project_ids)
    if (projectIds.length === 0 && detail.project_id) {
      projectIds.push(detail.project_id)
    }
    const resolvedProjectName = detail.project_name || detail.projectName || (projectIds.length > 0 ? getProjectName(projectIds[0]) : '')
    
    paymentRequestData.processApprovalId = processApprovalId
    paymentRequestData.category = detail.category || 'social_insurance'
    paymentRequestData.processTitle = detail.title || ''
    paymentRequestData.amount = '' // 清空金额，让用户手动输入
    const categoryName = paymentRequestData.category === 'housing_fund' ? '公积金' : '社保'
    paymentRequestData.remarks = `${categoryName}汇总付款申请 - ${detail.title}`
    paymentRequestData.projectIds = projectIds
    paymentRequestData.projectName = resolvedProjectName
    
    // 重置付款表单字段组件
    paymentFormFields.value = {}
    if (paymentFormFieldsRef.value) {
      paymentFormFieldsRef.value.resetForm()
    }
    
    // 将原保险汇总附件转换为文件列表格式（标记为已存在）
    paymentFileList.value = (detail.attachments || []).map(att => ({
      name: att.filename || att.file_name,
      size: att.file_size,
      uid: `existing_${att.id}`,
      status: 'success',
      isExisting: true,  // 标记为已存在的附件
      filePath: att.file_path
    }))
    invoiceFileList.value = []
    paymentRequestDialogVisible.value = true
  } catch (error) {
    console.error('加载流程详情失败:', error)
    ElMessage.error('加载流程详情失败')
  }
}

// 付款文件选择后

// 确认创建付款申请
const confirmCreatePaymentRequest = async () => {
  try {
    creatingPayment.value = true

    // 1. 先创建付款申请记录
    const { submitInsurancePaymentRequest } = await import('@/api/paymentApplication')
    const formFieldsData = paymentFormFieldsRef.value ? paymentFormFieldsRef.value.getFormData() : {}
    
    // 验证付款金额
    if (!paymentRequestData.amount || paymentRequestData.amount <= 0) {
      ElMessage.error('请输入有效的付款金额')
      creatingPayment.value = false
      return
    }

    const resolvedProjectName = paymentRequestData.projectName || ''
    if (resolvedProjectName) {
      formFieldsData.project = formFieldsData.project || resolvedProjectName
      formFieldsData.projectName = formFieldsData.projectName || resolvedProjectName
    }

    if (!validatePaymentRequestForm(formFieldsData)) {
      creatingPayment.value = false
      return
    }
    
    const response = await submitInsurancePaymentRequest({
      process_approval_id: paymentRequestData.processApprovalId,
      amount: paymentRequestData.amount, // 添加付款金额
      remarks: paymentRequestData.remarks,
      project_ids: paymentRequestData.projectIds,
      current_account_set_id: accountSetStore.currentAccountSetId,
      upload_later: paymentAttachmentUploaderRef.value ? paymentAttachmentUploaderRef.value.getUploadLater() : false, // 传递稍后上传状态
      // 付款表单字段
      reimbursement_form_data: formFieldsData
    })
    
    if (!response.data || !response.data.id) {
      throw new Error('创建付款申请失败：未返回付款申请ID')
    }
    
    const paymentRequestId = response.data.id
    
    // 2. 合并发票和其他附件，并标记类型
    const allFiles = [
      ...invoiceFileList.value.map(f => ({ ...f, attachmentType: 'invoice' })),
      ...paymentFileList.value.map(f => ({ ...f, attachmentType: 'attachment' }))
    ]
    
    // 3. 如果有文件，则上传所有文件
    let uploadCount = 0
    if (allFiles.length > 0) {
      ElMessage.info('正在上传附件...')
      const { uploadInsurancePaymentAttachment } = await import('@/api/paymentApplication')
      
      for (const file of allFiles) {
        // 跳过已存在的附件（从汇总申请继承的）
        if (file.isExisting) {
          uploadCount++
          continue
        }
        
        // 跳过没有raw属性的文件
        if (!file.raw) {
          console.warn(`文件 ${file.name} 没有raw属性，跳过上传`)
          continue
        }
        
        try {
          const formData = new FormData()
          formData.append('file', file.raw)
          formData.append('payment_request_id', paymentRequestId)
          formData.append('current_account_set_id', accountSetStore.currentAccountSetId)
          formData.append('attachment_type', file.attachmentType) // 添加附件类型
          
          await uploadInsurancePaymentAttachment(formData)
          uploadCount++
        } catch (error) {
          console.error(`上传文件 ${file.name} 失败:`, error)
          ElMessage.error(`上传文件 ${file.name} 失败`)
        }
      }
      
      if (uploadCount === 0 && allFiles.length > 0) {
        ElMessage.error('所有文件上传失败，请重试')
        creatingPayment.value = false
        return
      }
    }
    
    // 4. 完成提交，创建审批流程
    const { completeInsurancePaymentSubmission } = await import('@/api/paymentApplication')
    const completeResponse = await completeInsurancePaymentSubmission({
      payment_request_id: paymentRequestId,
      stamp_method: paymentRequestData.stamp_method // 传递盖章方式
    })
    
    if (completeResponse.success) {
      const message = allFiles.length > 0 
        ? `付款申请已提交！已上传 ${uploadCount}/${allFiles.length} 个文件（发票 ${invoiceFileList.value.length} 个，其他附件 ${paymentFileList.value.length} 个），审批流程已创建` 
        : '付款申请已提交！系统已自动生成占位附件，审批流程已创建'
      ElMessage.success(message)
      
      paymentRequestDialogVisible.value = false
      detailVisible.value = false
      
      // 跳转到付款申请列表页
      setTimeout(() => {
        router.push('/payment-applications')
      }, 500)
    } else {
      ElMessage.warning(`附件已上传，但创建审批流程失败: ${completeResponse.message}`)
    }
  } catch (error) {
    console.error('Create payment error:', error)
    ElMessage.error(error.response?.data?.message || error.message || '发起付款失败')
  } finally {
    creatingPayment.value = false
  }
}

// 获取当前月份
const getCurrentMonth = () => {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  return `${year}-${month}`
}

// 格式化日期时间
const formatDateTime = (dateTime) => {
  if (!dateTime) return '-'
  const date = new Date(dateTime)
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

onMounted(() => {
  filterForm.month = getCurrentMonth()
  loadProjects()
  loadProcessList()
})
</script>

<style scoped>
.process-management-container {
  padding: 20px;
}

.filter-card {
  margin-bottom: 20px;
}

.table-card {
  margin-bottom: 20px;
}

.pagination-container {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
</style>
