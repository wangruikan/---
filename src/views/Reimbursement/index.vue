<template>
  <div class="reimbursement-page">
    <NoAccountSetTip v-if="!isAdmin && !currentAccountSetId" />
    <div v-else>
      <div class="page-header">
        <h1>报销管理</h1>
        <div class="header-actions">
          <el-button type="primary" @click="handleCreate">
            <el-icon><Plus /></el-icon>
            发起报销
          </el-button>
        </div>
      </div>

      <!-- 筛选条件 -->
      <el-card shadow="never" style="margin-bottom: 20px;">
        <el-form :inline="true" :model="searchForm" class="search-form">
          <el-form-item label="报销人">
            <el-input v-model="searchForm.applicant" placeholder="请输入报销人" clearable />
          </el-form-item>

          <el-form-item label="申请日期">
            <el-date-picker
              v-model="searchForm.dateRange"
              type="daterange"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              format="YYYY-MM-DD"
              value-format="YYYY-MM-DD"
              clearable
            />
          </el-form-item>

          <el-form-item label="状态">
            <el-select v-model="searchForm.status" placeholder="请选择状态" clearable>
              <el-option label="全部状态" :value="null" />
              <el-option label="待审批" value="pending" />
              <el-option label="已通过" value="approved" />
              <el-option label="已拒绝" value="rejected" />
            </el-select>
          </el-form-item>

          <el-form-item>
            <el-button type="primary" @click="handleSearch">查询</el-button>
            <el-button @click="handleReset">重置</el-button>
          </el-form-item>
        </el-form>
      </el-card>

      <!-- 报销列表 -->
      <el-card shadow="never">
        <template #header>
          <div class="card-header">
            <span class="title">报销列表</span>
            <el-button type="text" @click="handleSearch">
              <el-icon><Refresh /></el-icon>
              刷新
            </el-button>
          </div>
        </template>

        <el-table
          :data="tableData"
          border
          stripe
          v-loading="loading"
          style="width: 100%"
        >
          <el-table-column type="index" label="序号" width="60" align="center" />
          <el-table-column prop="applicant" label="报销人" width="120" />
          <el-table-column prop="amount" label="报销金额" width="140" align="right">
            <template #default="scope">
              <span style="color: #E6A23C; font-weight: bold;">{{ formatMoney(scope.row.amount) }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="reason" label="报销事由" min-width="200" show-overflow-tooltip />
          <el-table-column prop="status" label="状态" width="100">
            <template #default="scope">
              <el-tag :type="getStatusType(scope.row.status)">
                {{ getStatusText(scope.row.status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="created_at" label="申请时间" width="160">
            <template #default="{ row }">
              {{ formatDateTime(row.created_at) }}
            </template>
          </el-table-column>
          <el-table-column label="操作" width="250" fixed="right">
            <template #default="scope">
              <el-button link type="primary" size="small" @click="handleView(scope.row)">
                查看
              </el-button>
              <el-button 
                v-if="scope.row.status === 'pending' && isApprover" 
                link 
                type="success" 
                size="small" 
                @click="handleApprove(scope.row)"
              >
                审批
              </el-button>
              <!-- 审批通过且未发起付款：显示发起付款按钮 -->
              <el-button 
                v-if="scope.row.status === 'approved' && !scope.row.payment_request_created" 
                link 
                type="primary" 
                size="small" 
                @click="handleCreatePayment(scope.row)"
              >
                发起付款
              </el-button>
              <!-- 已发起付款：显示付款状态 -->
              <el-tag 
                v-if="scope.row.payment_request_created && scope.row.payment_request_status === 'pending'" 
                type="warning" 
                size="small"
              >
                付款待审批
              </el-tag>
              <el-tag 
                v-if="scope.row.payment_request_created && scope.row.payment_request_status === 'approved'" 
                type="success" 
                size="small"
              >
                付款已通过
              </el-tag>
              <el-button 
                v-if="scope.row.status === 'pending'" 
                link 
                type="danger" 
                size="small" 
                @click="handleDelete(scope.row)"
              >
                删除
              </el-button>
            </template>
          </el-table-column>
        </el-table>

        <!-- 分页 -->
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.pageSize"
          :total="pagination.total"
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleSearch"
          @current-change="handleSearch"
          style="margin-top: 20px; justify-content: flex-end;"
        />
      </el-card>

      <!-- 创建报销对话框 -->
      <el-dialog
        v-model="createDialogVisible"
        width="800px"
      >
        <template #header>
          <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <span>发起报销申请</span>
            <el-button type="success" size="small" @click="fillTestData">
              <el-icon><MagicStick /></el-icon>
              填充实例
            </el-button>
          </div>
        </template>
        <el-form :model="createForm" :rules="rules" ref="createFormRef" label-width="120px">
          <!-- 项目 -->
          <el-form-item label="项目" prop="project">
            <el-select 
              v-model="createForm.project" 
              placeholder="请选择项目" 
              style="width: 100%"
              filterable
            >
              <el-option
                v-for="proj in projectList"
                :key="proj.id"
                :label="proj.name"
                :value="proj.name"
              />
            </el-select>
          </el-form-item>

          <!-- 申请日期 -->
          <el-form-item label="申请日期" prop="applyDate">
            <el-date-picker
              v-model="createForm.applyDate"
              type="date"
              placeholder="选择申请日期"
              format="YYYY-MM-DD"
              value-format="YYYY-MM-DD"
              style="width: 100%"
            />
          </el-form-item>

          <!-- 报销人 -->
          <el-form-item label="报销人" prop="reimburser">
            <el-input v-model="createForm.reimburser" placeholder="请输入报销人" style="width: 100%" />
          </el-form-item>

          <!-- 发票号码 -->
          <el-form-item label="发票号码" prop="invoiceNumber">
            <el-input v-model="createForm.invoiceNumber" placeholder="请输入发票号码" style="width: 100%" />
          </el-form-item>

          <!-- 报销金额 -->
          <el-form-item label="报销金额" prop="expenditureAmount">
            <el-input-number
              v-model="createForm.expenditureAmount"
              :precision="2"
              :min="0"
              placeholder="请输入报销金额"
              style="width: 100%"
            />
          </el-form-item>

          <!-- 类目 -->
          <el-form-item label="类目">
            <el-select v-model="createForm.category" placeholder="请选择类目" style="width: 100%">
              <el-option label="报销" value="报销" />
              <el-option label="差旅" value="差旅" />
              <el-option label="采购" value="采购" />
              <el-option label="项目" value="项目" />
              <el-option label="其他" value="其他" />
            </el-select>
          </el-form-item>

          <!-- 报销事由 -->
          <el-form-item label="报销事由" prop="summary">
            <el-input
              v-model="createForm.summary"
              type="textarea"
              :rows="3"
              placeholder="请输入报销事由"
            />
          </el-form-item>

          <!-- 附件上传组件（隐藏发票上传，保留表格生成PDF功能） -->
          <PaymentAttachmentUploader
            ref="attachmentUploaderRef"
            v-model:other-file-list="attachmentFileList"
            :show-invoice-upload="false"
            :other-limit="10"
            :show-form-generator="true"
            form-button-text="填写报销表格生成PDF"
            form-title="报销申请表"
          />
        </el-form>

        <template #footer>
          <el-button @click="createDialogVisible = false">取消</el-button>
          <el-button 
            type="primary" 
            @click="handleConfirmCreate"
            :loading="submitting"
          >
            提交申请
          </el-button>
        </template>
      </el-dialog>

      <!-- 发起付款对话框 -->
      <el-dialog
        v-model="paymentDialogVisible"
        title="发起报销付款申请"
        width="800px"
      >
        <el-form :model="paymentForm" label-width="100px">
          <el-form-item label="报销人">
            <el-input v-model="paymentForm.applicant" disabled />
          </el-form-item>
          
          <el-form-item label="报销金额">
            <el-input v-model="paymentForm.amount" disabled>
              <template #append>元</template>
            </el-input>
          </el-form-item>

          <el-form-item label="付款类型">
            <el-input v-model="paymentForm.payment_type" disabled />
          </el-form-item>

          <el-form-item label="报销事由">
            <el-input v-model="paymentForm.reason" disabled type="textarea" :rows="2" />
          </el-form-item>

          <el-form-item label="备注">
            <el-input
              v-model="paymentForm.remarks"
              type="textarea"
              :rows="3"
              placeholder="请输入备注信息"
            />
          </el-form-item>

          <el-divider content-position="left">报销表单信息</el-divider>

          <!-- 导入报销表单组件 -->
          <ReimbursementForm
            ref="reimbursementFormRef"
            v-model="paymentForm.reimbursementFormData"
            :project-list="projectList"
          />

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
            form-title="报销付款申请表"
          />
          <div style="margin-top: 6px; color: #E6A23C; font-size: 12px;">
            候补资料开启后，可在付款申请列表72小时内补充发票或单据
          </div>
        </el-form>

        <template #footer>
          <el-button @click="paymentDialogVisible = false">取消</el-button>
          <el-button 
            type="primary" 
            @click="confirmCreatePayment"
            :loading="creatingPayment"
          >
            提交付款申请
          </el-button>
        </template>
      </el-dialog>

      <!-- 查看详情对话框 -->
      <el-dialog
        v-model="detailDialogVisible"
        title="报销详情"
        width="700px"
      >
        <el-descriptions :column="2" border v-if="currentRow">
          <el-descriptions-item label="报销人">{{ currentRow.applicant }}</el-descriptions-item>
          <el-descriptions-item label="报销金额">
            <span style="color: #E6A23C; font-weight: bold;">{{ formatMoney(currentRow.amount) }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="getStatusType(currentRow.status)">
              {{ getStatusText(currentRow.status) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="申请时间">{{ currentRow.created_at }}</el-descriptions-item>
          <el-descriptions-item label="报销事由" :span="2">{{ currentRow.reason }}</el-descriptions-item>
          <el-descriptions-item label="备注" :span="2">{{ currentRow.remarks || '-' }}</el-descriptions-item>
        </el-descriptions>

        <!-- 附件列表 -->
        <el-divider content-position="left">
          <span style="font-size: 14px; color: #606266;">附件列表 ({{ currentRow?.attachments?.length || 0 }})</span>
        </el-divider>
        
        <div v-if="currentRow?.attachments && currentRow.attachments.length > 0">
          <el-table :data="currentRow.attachments" border stripe>
            <el-table-column type="index" label="序号" width="60" align="center" />
            <el-table-column prop="file_name" label="文件名" show-overflow-tooltip>
              <template #default="{ row }">
                <el-link :href="getFileUrl(row.file_path)" target="_blank" :underline="false">
                  <span style="color: #409EFF;">{{ row.file_name }}</span>
                </el-link>
              </template>
            </el-table-column>
            <el-table-column prop="file_type" label="文件类型" width="120" align="center">
              <template #default="{ row }">
                <el-tag v-if="row.file_type && row.file_type.includes('pdf')" type="danger" size="small">PDF</el-tag>
                <el-tag v-else-if="row.file_type && row.file_type.includes('image')" type="success" size="small">图片</el-tag>
                <el-tag v-else-if="row.file_type && (row.file_type.includes('word') || row.file_type.includes('document'))" type="primary" size="small">文档</el-tag>
                <el-tag v-else-if="row.file_type && (row.file_type.includes('excel') || row.file_type.includes('sheet'))" type="warning" size="small">表格</el-tag>
                <el-tag v-else type="info" size="small">其他</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="150" align="center">
              <template #default="{ row }">
                <el-button 
                  type="primary" 
                  size="small" 
                  text
                  @click="handlePreviewAttachment(row)"
                >
                  预览
                </el-button>
                <el-button 
                  type="primary" 
                  size="small" 
                  text
                  @click="handleDownloadAttachment(row)"
                >
                  下载
                </el-button>
              </template>
            </el-table-column>
          </el-table>
        </div>
        <el-empty v-else description="暂无附件" :image-size="100" />

        <template #footer>
          <el-button @click="detailDialogVisible = false">关闭</el-button>
        </template>
      </el-dialog>

      <!-- 报销申请盖章方式选择对话框 -->
      <el-dialog
        v-model="reimbursementStampDialogVisible"
        title="选择盖章方式"
        width="400px"
        :close-on-click-modal="false"
      >
        <el-form :model="reimbursementStampForm" label-width="100px">
          <el-form-item label="盖章方式" required>
            <el-radio-group v-model="reimbursementStampForm.stamp_method">
              <el-radio value="online">线上盖章</el-radio>
              <el-radio value="offline">线下盖章</el-radio>
            </el-radio-group>
          </el-form-item>
        </el-form>
        <template #footer>
          <el-button @click="reimbursementStampDialogVisible = false">取消</el-button>
          <el-button type="primary" @click="confirmReimbursementStampAndSubmit" :loading="submitting">
            确认提交
          </el-button>
        </template>
      </el-dialog>

      <!-- 付款申请盖章方式选择对话框 -->
      <el-dialog
        v-model="paymentStampDialogVisible"
        title="选择盖章方式"
        width="400px"
        :close-on-click-modal="false"
        @close="handleCancelPaymentStampSelection"
      >
        <el-form :model="paymentStampForm" label-width="100px">
          <el-form-item label="盖章方式" required>
            <el-radio-group v-model="paymentStampForm.stamp_method">
              <el-radio value="online">线上盖章</el-radio>
              <el-radio value="offline">线下盖章</el-radio>
            </el-radio-group>
          </el-form-item>
        </el-form>
        <template #footer>
          <el-button @click="handleCancelPaymentStampSelection">取消</el-button>
          <el-button type="primary" @click="confirmPaymentStampAndSubmit" :loading="creatingPayment">
            确认提交
          </el-button>
        </template>
      </el-dialog>

    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh, MagicStick, Paperclip, Document } from '@element-plus/icons-vue'
import { useUserStore } from '@/stores/user'
import { useAccountSetStore } from '@/stores/accountSet'
import NoAccountSetTip from '@/components/NoAccountSetTip.vue'
import PaymentAttachmentUploader from '@/components/PaymentAttachmentUploader.vue'
import ReimbursementForm from '@/components/ReimbursementForm.vue'
import {
  getReimbursements,
  createReimbursement,
  deleteReimbursement,
  uploadReimbursementAttachment
} from '@/api/reimbursement'
import { getProjects } from '@/api/projects'
import {
  submitReimbursementPaymentRequest,
  uploadReimbursementPaymentAttachment,
  completeReimbursementPaymentSubmission
} from '@/api/reimbursementPayment'

const userStore = useUserStore()
const accountSetStore = useAccountSetStore()

const isAdmin = computed(() => userStore.userInfo?.role === 'admin')
const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)
const isApprover = computed(() => ['admin', 'approver'].includes(userStore.userInfo?.role))

// 搜索表单
const searchForm = reactive({
  applicant: '',
  dateRange: null,
  status: null
})

// 分页
const pagination = reactive({
  page: 1,
  pageSize: 20,
  total: 0
})

// 表格数据
const tableData = ref([])
const loading = ref(false)

// 创建对话框
const createDialogVisible = ref(false)
const createFormRef = ref(null)
const createForm = reactive({
  project: '',
  applyDate: '',
  reimburser: '',
  invoiceNumber: '', // 发票号码
  expenditureAmount: null,
  category: '',
  summary: ''
})

// 附件列表
const attachmentUploaderRef = ref(null)
const attachmentFileList = ref([])
const submitting = ref(false)

// 盖章方式选择（报销申请）
const reimbursementStampDialogVisible = ref(false)
const reimbursementStampForm = reactive({
  stamp_method: 'online'
})
const pendingReimbursementData = ref(null)

// 项目列表
const projectList = ref([])

// 详情对话框
const detailDialogVisible = ref(false)
const currentRow = ref(null)

// 发起付款对话框
const paymentDialogVisible = ref(false)
const reimbursementFormRef = ref(null)
const paymentForm = reactive({
  reimbursement_id: null,
  applicant: '',
  amount: '',
  payment_type: '',
  reason: '',
  remarks: '',
  reimbursementFormData: {} // 报销表单数据
})

// 付款文件上传相关
const paymentAttachmentUploaderRef = ref(null)
const paymentFileList = ref([]) // 其他附件列表
const invoiceFileList = ref([]) // 发票文件列表
const creatingPayment = ref(false)
const originalAttachments = ref([]) // 原报销申请的附件（自动带入审批）

// 付款申请盖章方式选择
const paymentStampDialogVisible = ref(false)
const paymentStampForm = reactive({
  stamp_method: 'online'
})
const pendingPaymentSubmissionData = ref(null)

// 表单验证规则
const rules = {
  project: [
    { required: true, message: '请选择项目', trigger: 'change' }
  ],
  applyDate: [
    { required: true, message: '请选择申请日期', trigger: 'change' }
  ],
  reimburser: [
    { required: true, message: '请输入报销人', trigger: 'blur' }
  ],
  invoiceNumber: [
    { required: true, message: '请输入发票号码', trigger: 'blur' }
  ],
  expenditureAmount: [
    { required: true, message: '请输入报销金额', trigger: 'blur' },
    { type: 'number', min: 0.01, message: '报销金额必须大于0', trigger: 'blur' }
  ],
  summary: [
    { required: true, message: '请输入报销事由', trigger: 'blur' }
  ]
}


// 格式化金额
const formatMoney = (amount) => {
  if (amount === null || amount === undefined) {
    amount = 0
  }
  return '¥' + Number(amount).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// 状态类型映射
const getStatusType = (status) => {
  const map = {
    pending: 'warning',
    approved: 'success',
    rejected: 'danger'
  }
  return map[status] || 'info'
}

// 状态文本映射
const getStatusText = (status) => {
  const map = {
    pending: '待审批',
    approved: '已通过',
    rejected: '已拒绝'
  }
  return map[status] || '未知'
}

// 查询
const handleSearch = async () => {
  loading.value = true
  try {
    const params = {
      applicant: searchForm.applicant,
      start_date: searchForm.dateRange?.[0],
      end_date: searchForm.dateRange?.[1],
      status: searchForm.status,
      page: pagination.page,
      per_page: pagination.pageSize
    }
    const response = await getReimbursements(params)
    if (response && response.success) {
      tableData.value = response.data.data || []
      pagination.total = response.data.total || 0
    }
  } catch (error) {
    console.error('Load reimbursement list error:', error)
    ElMessage.error('加载报销列表失败')
    tableData.value = []
    pagination.total = 0
  } finally {
    loading.value = false
  }
}

// 重置
const handleReset = () => {
  searchForm.applicant = ''
  searchForm.dateRange = null
  searchForm.status = null
  pagination.page = 1
  handleSearch()
}

// 发起报销
const handleCreate = () => {
  createDialogVisible.value = true
  // 重置所有字段
  createForm.project = ''
  createForm.applyDate = new Date().toISOString().split('T')[0]
  createForm.reimburser = ''
  createForm.invoiceNumber = ''
  createForm.expenditureAmount = null
  createForm.category = ''
  createForm.summary = ''
  attachmentFileList.value = []
}

// 填充测试数据
const fillTestData = () => {
  const today = new Date().toISOString().split('T')[0]
  
  createForm.project = projectList.value.length > 0 ? projectList.value[0].name : ''
  createForm.applyDate = today
  createForm.reimburser = '张三'
  createForm.invoiceNumber = 'INV' + Date.now().toString().slice(-8) // 生成示例发票号码
  createForm.expenditureAmount = 1500.50
  createForm.category = '报销'
  createForm.summary = '差旅费报销，包含往返交通费、住宿费及餐饮补贴'
  
  ElMessage.success('已填充测试数据')
}

// 确认创建
const handleConfirmCreate = async () => {
  try {
    // 验证表单
    await createFormRef.value.validate()

    // 准备提交数据
    const submitData = {
      company_name: '鄂尔多斯市汇邦人力资源有限责任公司', // 默认公司名称
      applicant: createForm.reimburser || '未填写', // 报销人
      invoice_number: createForm.invoiceNumber, // 发票号码
      amount: createForm.expenditureAmount || 0, // 报销金额
      project: createForm.project, // 项目
      reason: createForm.summary || '无', // 报销事由
      category: createForm.category || '报销', // 类目
      current_account_set_id: accountSetStore.currentAccountSetId,
      // 其他可选字段
      apply_date: createForm.applyDate,
      payment_date: createForm.applyDate,
      received_invoice: false,
      invoice_type: '',
      invoice_amount: createForm.expenditureAmount || 0,
      tax_rate: '',
      tax_deduction: 0,
      amount_excluding_tax: createForm.expenditureAmount || 0,
      tax_amount: 0,
      invoice_date: createForm.applyDate,
      record_status: '',
      accounting_status: '',
      remarks: ''
    }

    // 保存数据，打开盖章方式选择对话框
    pendingReimbursementData.value = submitData
    reimbursementStampForm.stamp_method = 'online'
    reimbursementStampDialogVisible.value = true
  } catch (error) {
    if (error !== false) {
      console.error('Form validation error:', error)
    }
  }
}

// 确认盖章方式并创建报销申请
const confirmReimbursementStampAndSubmit = async () => {
  try {
    submitting.value = true
    
    // 添加盖章方式到提交数据
    const submitData = {
      ...pendingReimbursementData.value,
      stamp_method: reimbursementStampForm.stamp_method
    }

    console.log('提交报销数据:', submitData)

    // 调用创建报销申请的API
    const response = await createReimbursement(submitData)
    
    if (response && response.success) {
      const reimbursementId = response.data.id
      
      // 上传附件
      if (attachmentFileList.value && attachmentFileList.value.length > 0) {
        console.log('开始上传附件，数量:', attachmentFileList.value.length)
        for (const fileItem of attachmentFileList.value) {
          try {
            const formData = new FormData()
            formData.append('file', fileItem.raw || fileItem)
            formData.append('reimbursement_id', reimbursementId)
            
            const uploadRes = await uploadReimbursementAttachment(formData)
            if (uploadRes && uploadRes.success) {
              console.log('附件上传成功:', fileItem.name)
            } else {
              console.warn('附件上传失败:', fileItem.name)
            }
          } catch (uploadError) {
            console.error('上传附件出错:', uploadError)
          }
        }
      }
      
      ElMessage.success('报销申请创建成功！')
      reimbursementStampDialogVisible.value = false
      createDialogVisible.value = false
      
      // 重置表单
      createForm.project = ''
      createForm.applyDate = new Date().toISOString().split('T')[0]
      createForm.reimburser = ''
      createForm.invoiceNumber = ''
      createForm.expenditureAmount = null
      createForm.category = ''
      createForm.summary = ''
      attachmentFileList.value = []
      pendingReimbursementData.value = null
      
      // 刷新列表
      handleSearch()
    } else {
      throw new Error(response?.message || '创建报销申请失败')
    }
  } catch (error) {
    console.error('Create reimbursement error:', error)
    ElMessage.error(error.response?.data?.message || error.message || '提交报销申请失败')
  } finally {
    submitting.value = false
  }
}

// 查看详情
const handleView = (row) => {
  currentRow.value = row
  detailDialogVisible.value = true
}

// 获取文件完整URL
const getFileUrl = (filePath) => {
  if (!filePath) return ''
  // 如果已经是完整URL，直接返回
  if (filePath.startsWith('http://') || filePath.startsWith('https://')) {
    return filePath
  }
  // 否则拼接服务器地址
  const baseUrl = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'
  return `${baseUrl}/storage/${filePath}`
}

// 预览附件
const handlePreviewAttachment = (attachment) => {
  const fileUrl = getFileUrl(attachment.file_path)
  const fileName = attachment.file_name
  const fileExt = fileName.split('.').pop().toLowerCase()
  
  // 判断文件类型
  const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']
  const pdfExts = ['pdf']
  
  if (imageExts.includes(fileExt) || pdfExts.includes(fileExt)) {
    // 图片和PDF可以直接在浏览器中预览
    window.open(fileUrl, '_blank')
  } else {
    // 其他文件类型提示下载
    ElMessage.info('该文件类型不支持预览，请下载后查看')
    handleDownloadAttachment(attachment)
  }
}

// 下载附件
const handleDownloadAttachment = (attachment) => {
  const fileUrl = getFileUrl(attachment.file_path)
  
  // 创建一个隐藏的 a 标签来触发下载
  const link = document.createElement('a')
  link.href = fileUrl
  link.download = attachment.file_name
  link.target = '_blank'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  ElMessage.success('开始下载文件')
}

// 审批
const handleApprove = (row) => {
  ElMessageBox.prompt('请输入审批意见（可选）', '审批报销申请', {
    confirmButtonText: '通过',
    cancelButtonText: '拒绝',
    inputPattern: /.*/,
    distinguishCancelAndClose: true,
    beforeClose: (action, instance, done) => {
      if (action === 'confirm') {
        // 审批通过
        ElMessage.success('审批通过')
        handleSearch()
        done()
      } else if (action === 'cancel') {
        // 审批拒绝
        ElMessageBox.prompt('请输入拒绝原因', '拒绝报销申请', {
          confirmButtonText: '确定',
          cancelButtonText: '取消',
          inputPattern: /.+/,
          inputErrorMessage: '请输入拒绝原因'
        }).then(({ value }) => {
          ElMessage.success('已拒绝')
          handleSearch()
        }).catch(() => {})
        done()
      } else {
        done()
      }
    }
  }).catch(() => {})
}

// 删除
const handleDelete = (row) => {
  ElMessageBox.confirm(
    `确定删除 ${row.applicant} 的报销申请吗？`,
    '删除确认',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    }
  ).then(async () => {
    try {
      await deleteReimbursement(row.id)
      ElMessage.success('删除成功')
      handleSearch()
    } catch (error) {
      console.error('Delete error:', error)
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }).catch(() => {})
}

// 发起付款
const handleCreatePayment = (row) => {
  // 打开发起付款对话框
  paymentForm.reimbursement_id = row.id
  paymentForm.applicant = row.applicant
  paymentForm.amount = formatMoney(row.amount)
  paymentForm.payment_type = row.category || '报销'
  paymentForm.reason = row.reason || ''
  paymentForm.remarks = (row.category || '报销') + '付款申请 - ' + row.applicant + (row.reason ? ' - ' + row.reason : '')
  // 重置报销表单数据
  paymentForm.reimbursementFormData = {}
  // 将原报销附件转换为文件列表格式（标记为已存在）
  paymentFileList.value = (row.attachments || []).map(att => ({
    name: att.file_name,
    size: att.file_size,
    uid: `existing_${att.id}`,
    status: 'success',
    isExisting: true,  // 标记为已存在的附件
    filePath: att.file_path
  }))
  invoiceFileList.value = []
  paymentDialogVisible.value = true
}

// 确认发起付款
const confirmCreatePayment = async () => {
  try {
    creatingPayment.value = true

    const uploadLater = paymentAttachmentUploaderRef.value?.getUploadLater() || false

    if (!uploadLater && invoiceFileList.value.length === 0) {
      const hasSituationReport = paymentFileList.value.some(file =>
        file.name && file.name.includes('情况说明单')
      )

      if (!hasSituationReport) {
        ElMessage.error('未上传发票时，必须填写情况说明单并生成附件！')
        return
      }
    }

    pendingPaymentSubmissionData.value = {
      reimbursement_id: paymentForm.reimbursement_id,
      remarks: paymentForm.remarks,
      reimbursement_form_data: { ...(paymentForm.reimbursementFormData || {}) },
      current_account_set_id: accountSetStore.currentAccountSetId,
      upload_later: uploadLater
    }

    paymentStampForm.stamp_method = 'online'
    paymentStampDialogVisible.value = true
  } catch (error) {
    console.error('Prepare payment submission error:', error)
    ElMessage.error(error.response?.data?.message || error.message || '发起付款失败')
  } finally {
    creatingPayment.value = false
  }
}

const handleCancelPaymentStampSelection = () => {
  paymentStampDialogVisible.value = false
  pendingPaymentSubmissionData.value = null
}

const confirmPaymentStampAndSubmit = async () => {
  if (!pendingPaymentSubmissionData.value) {
    ElMessage.warning('请先填写付款申请信息后再提交')
    return
  }

  try {
    creatingPayment.value = true

    const response = await submitReimbursementPaymentRequest({
      ...pendingPaymentSubmissionData.value
    })

    if (!response.data || !response.data.id) {
      throw new Error('创建付款申请失败：未返回付款申请ID')
    }

    const paymentRequestId = response.data.id
    const allFiles = [...invoiceFileList.value, ...paymentFileList.value]

    let uploadCount = 0
    if (allFiles.length > 0) {
      ElMessage.info('正在上传附件...')
      for (const file of allFiles) {
        const success = await uploadPaymentFileToServer(file, paymentRequestId)
        if (success) {
          uploadCount++
        }
      }

      if (uploadCount === 0 && allFiles.length > 0) {
        ElMessage.error('所有文件上传失败，请重试')
        return
      }
    }

    const completeResponse = await completeReimbursementPaymentSubmission({
      payment_request_id: paymentRequestId,
      stamp_method: paymentStampForm.stamp_method
    })

    if (completeResponse.success) {
      ElMessage.success('付款申请已提交！审批流程已创建')
    } else {
      ElMessage.warning('创建审批流程失败: ' + (completeResponse.message || ''))
    }

    paymentStampDialogVisible.value = false
    paymentDialogVisible.value = false
    pendingPaymentSubmissionData.value = null
    handleSearch()
  } catch (error) {
    console.error('Complete payment submission error:', error)
    ElMessage.error(error.response?.data?.message || error.message || '提交失败')
  } finally {
    creatingPayment.value = false
  }
}
const uploadPaymentFileToServer = async (file, paymentRequestId) => {
  try {
    const formData = new FormData()
    formData.append('file', file.raw)
    formData.append('payment_request_id', paymentRequestId)
    formData.append('current_account_set_id', accountSetStore.currentAccountSetId)
    
    await uploadReimbursementPaymentAttachment(formData)
    return true
  } catch (error) {
    console.error(`上传文件 ${file.name} 失败:`, error)
    ElMessage.error(`上传文件 ${file.name} 失败: ${error.response?.data?.message || error.message}`)
    return false
  }
}

// 初始化
// 加载项目列表
const loadProjects = async () => {
  try {
    const response = await getProjects({
      current_account_set_id: accountSetStore.currentAccountSetId,
      all: true // 获取所有项目，不分页
    })
    if (response.success && response.data) {
      // 如果返回的是分页数据，取data属性；如果直接是数组，就用它
      projectList.value = Array.isArray(response.data) ? response.data : (response.data.data || [])
      console.log('项目列表加载成功:', projectList.value.length, '个项目')
    }
  } catch (error) {
    console.error('加载项目列表失败:', error)
    ElMessage.error('加载项目列表失败')
  }
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
  handleSearch()
  loadProjects()
})
</script>

<style scoped>
.reimbursement-page {
  padding: 20px;
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

.header-actions {
  display: flex;
  gap: 10px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.title {
  font-size: 16px;
  font-weight: bold;
}

.search-form {
  margin-bottom: 0;
}
</style>

