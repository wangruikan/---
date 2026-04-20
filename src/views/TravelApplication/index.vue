<template>
  <div class="travel-application-page">
    <NoAccountSetTip v-if="!isAdmin && !currentAccountSetId" />
    <div v-else>
      <div class="page-header">
        <h1>差旅申请管理</h1>
        <div class="header-actions">
          <el-button type="primary" @click="handleCreate">
            <el-icon><Plus /></el-icon>
            发起差旅申请
          </el-button>
        </div>
      </div>

      <!-- 筛选条件 -->
      <el-card shadow="never" style="margin-bottom: 20px;">
        <el-form :inline="true" :model="searchForm" class="search-form">
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
            <el-select v-model="searchForm.status" placeholder="请选择状态" clearable style="width: 200px;">
              <el-option label="全部状态" :value="null" />
              <el-option label="待审批" value="pending" />
              <el-option label="审批中" value="in_approval" />
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

      <!-- 差旅申请列表 -->
      <el-card shadow="never">
        <template #header>
          <div class="card-header">
            <span class="title">差旅申请列表</span>
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
          <el-table-column prop="applicant" label="申请人" width="120" />
          <el-table-column prop="destination" label="出差地" width="140" />
          <el-table-column prop="advance_amount" label="预支金额" width="140" align="right">
            <template #default="scope">
              <span style="color: #E6A23C; font-weight: bold;">{{ formatMoney(scope.row.advance_amount) }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="reason" label="出差事由" min-width="200" show-overflow-tooltip />
          <el-table-column prop="travel_dates" label="出差日期" width="200">
            <template #default="scope">
              {{ formatTravelDates(scope.row.start_time, scope.row.end_time, scope.row.days) }}
            </template>
          </el-table-column>
          <el-table-column prop="status" label="状态" width="100">
            <template #default="scope">
              <el-tag :type="getStatusType(scope.row.status)">
                {{ getStatusText(scope.row.status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="created_at" label="申请时间" width="160">
            <template #default="scope">
              {{ formatDateTime(scope.row.created_at) }}
            </template>
          </el-table-column>
          <el-table-column label="操作" width="200" fixed="right">
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

      <!-- 创建差旅申请对话框 -->
      <el-dialog
        v-model="createDialogVisible"
        width="800px"
      >
        <template #header>
          <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <span>发起差旅申请</span>
            <el-button type="success" size="small" @click="fillTestData">
              <el-icon><MagicStick /></el-icon>
              填充实例
            </el-button>
          </div>
        </template>
        <el-form :model="createForm" :rules="rules" ref="createFormRef" label-width="120px">
          <el-row :gutter="20">
            <!-- 项目 -->
            <el-col :span="12">
              <el-form-item label="项目" prop="department">
                <el-select 
                  v-model="createForm.department" 
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
            </el-col>

            <!-- 申请日期 -->
            <el-col :span="12">
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
            </el-col>
          </el-row>

          <el-row :gutter="20">
            <!-- 申请人 -->
            <el-col :span="12">
              <el-form-item label="申请人" prop="applicant">
                <el-input v-model="createForm.applicant" placeholder="请输入申请人姓名" />
              </el-form-item>
            </el-col>

            <!-- 出差地 -->
            <el-col :span="12">
              <el-form-item label="出差地" prop="destination">
                <el-input v-model="createForm.destination" placeholder="请输入出差地点" />
              </el-form-item>
            </el-col>
          </el-row>

          <!-- 出差事由 -->
          <el-form-item label="出差事由" prop="reason">
            <el-input
              v-model="createForm.reason"
              type="textarea"
              :rows="3"
              placeholder="请输入出差事由"
            />
          </el-form-item>

          <el-row :gutter="20">
            <!-- 起始时间 -->
            <el-col :span="12">
              <el-form-item label="起始时间" prop="startTime">
                <el-date-picker
                  v-model="createForm.startTime"
                  type="datetime"
                  placeholder="选择起始时间"
                  format="YYYY-MM-DD HH:mm"
                  value-format="YYYY-MM-DD HH:mm:ss"
                  style="width: 100%"
                />
              </el-form-item>
            </el-col>

            <!-- 结束时间 -->
            <el-col :span="12">
              <el-form-item label="结束时间" prop="endTime">
                <el-date-picker
                  v-model="createForm.endTime"
                  type="datetime"
                  placeholder="选择结束时间"
                  format="YYYY-MM-DD HH:mm"
                  value-format="YYYY-MM-DD HH:mm:ss"
                  style="width: 100%"
                />
              </el-form-item>
            </el-col>
          </el-row>

          <el-row :gutter="20">
            <!-- 计划天数 -->
            <el-col :span="12">
              <el-form-item label="计划天数">
                <el-input-number
                  v-model="createForm.days"
                  :min="0"
                  :controls="false"
                  placeholder="自动计算"
                  style="width: 100%"
                  disabled
                />
              </el-form-item>
            </el-col>

            <!-- 预支金额 -->
            <el-col :span="12">
              <el-form-item label="预支金额" prop="advanceAmount">
                <el-input-number
                  v-model="createForm.advanceAmount"
                  :min="0"
                  :precision="2"
                  :controls="false"
                  placeholder="请输入预支金额"
                  style="width: 100%"
                />
              </el-form-item>
            </el-col>
          </el-row>

          <el-row :gutter="20">
            <!-- 付款日期 -->
            <el-col :span="12">
              <el-form-item label="付款日期">
                <el-date-picker
                  v-model="createForm.paymentDate"
                  type="date"
                  placeholder="选择付款日期"
                  format="YYYY-MM-DD"
                  value-format="YYYY-MM-DD"
                  style="width: 100%"
                />
              </el-form-item>
            </el-col>
          </el-row>

          <!-- 备注 -->
          <el-form-item label="备注">
            <el-input
              v-model="createForm.remarks"
              type="textarea"
              :rows="2"
              placeholder="请输入备注信息（可选）"
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
            form-title="差旅申请表"
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

      <!-- 查看详情对话框 -->
      <el-dialog
        v-model="detailDialogVisible"
        title="差旅申请详情"
        width="700px"
      >
        <el-descriptions :column="2" border v-if="currentRow">
          <el-descriptions-item label="申请人">{{ currentRow.applicant }}</el-descriptions-item>
          <el-descriptions-item label="项目">{{ currentRow.department }}</el-descriptions-item>
          <el-descriptions-item label="出差地">{{ currentRow.destination }}</el-descriptions-item>
          <el-descriptions-item label="计划天数">{{ currentRow.days }} 天</el-descriptions-item>
          <el-descriptions-item label="起始时间">{{ formatDateTime(currentRow.start_time) }}</el-descriptions-item>
          <el-descriptions-item label="结束时间">{{ formatDateTime(currentRow.end_time) }}</el-descriptions-item>
          <el-descriptions-item label="预支金额">
            <span style="color: #E6A23C; font-weight: bold;">{{ formatMoney(currentRow.advance_amount) }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="付款日期">{{ currentRow.payment_date || '-' }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="getStatusType(currentRow.status)">
              {{ getStatusText(currentRow.status) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="申请时间">{{ formatDateTime(currentRow.created_at) }}</el-descriptions-item>
          <el-descriptions-item label="出差事由" :span="2">{{ currentRow.reason }}</el-descriptions-item>
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

      <!-- 盖章方式选择对话框 -->
      <el-dialog
        v-model="stampMethodDialogVisible"
        title="选择盖章方式"
        width="400px"
        :close-on-click-modal="false"
      >
        <el-form :model="stampMethodForm" label-width="100px">
          <el-form-item label="盖章方式" required>
            <el-radio-group v-model="stampMethodForm.stamp_method">
              <el-radio value="online">线上盖章</el-radio>
              <el-radio value="offline">线下盖章</el-radio>
            </el-radio-group>
          </el-form-item>
        </el-form>
        <template #footer>
          <el-button @click="stampMethodDialogVisible = false">取消</el-button>
          <el-button type="primary" @click="confirmStampMethodAndSubmit" :loading="submitting">
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
import { Plus, Refresh, MagicStick } from '@element-plus/icons-vue'
import { useUserStore } from '@/stores/user'
import { useAccountSetStore } from '@/stores/accountSet'
import NoAccountSetTip from '@/components/NoAccountSetTip.vue'
import PaymentAttachmentUploader from '@/components/PaymentAttachmentUploader.vue'
import {
  getTravelApplications,
  createTravelApplication,
  uploadTravelApplicationAttachment,
  completeTravelApplicationSubmission,
  approveTravelApplication,
  rejectTravelApplication,
  deleteTravelApplication
} from '@/api/travelApplication'
import { getProjects } from '@/api/projects'

const userStore = useUserStore()
const accountSetStore = useAccountSetStore()

const isAdmin = computed(() => userStore.userInfo?.role === 'admin')
const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)
const isApprover = computed(() => ['admin', 'approver'].includes(userStore.userInfo?.role))

// 搜索表单
const searchForm = reactive({
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
  department: '',
  applyDate: '',
  applicant: '',
  destination: '',
  reason: '',
  startTime: '',
  endTime: '',
  days: 0,
  advanceAmount: null,
  paymentDate: '',
  remarks: ''
})

// 附件列表
const attachmentUploaderRef = ref(null)
const attachmentFileList = ref([])
const submitting = ref(false)

// 盖章方式选择
const stampMethodDialogVisible = ref(false)
const stampMethodForm = reactive({
  stamp_method: 'online'
})
const pendingTravelApplicationId = ref(null)

// 项目列表
const projectList = ref([])

// 详情对话框
const detailDialogVisible = ref(false)
const currentRow = ref(null)

// 表单验证规则
const rules = {
  department: [
    { required: true, message: '请选择项目', trigger: 'change' }
  ],
  applyDate: [
    { required: true, message: '请选择申请日期', trigger: 'change' }
  ],
  applicant: [
    { required: true, message: '请输入申请人姓名', trigger: 'blur' }
  ],
  destination: [
    { required: true, message: '请输入出差地点', trigger: 'blur' }
  ],
  reason: [
    { required: true, message: '请输入出差事由', trigger: 'blur' }
  ],
  startTime: [
    { required: true, message: '请选择起始时间', trigger: 'change' }
  ],
  endTime: [
    { required: true, message: '请选择结束时间', trigger: 'change' }
  ],
  advanceAmount: [
    { required: true, message: '请输入预支金额', trigger: 'blur' },
    { type: 'number', min: 0.01, message: '预支金额必须大于0', trigger: 'blur' }
  ]
}

// 自动计算天数
const calculateDays = () => {
  if (createForm.startTime && createForm.endTime) {
    const start = new Date(createForm.startTime)
    const end = new Date(createForm.endTime)
    const diffTime = Math.abs(end - start)
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1
    createForm.days = diffDays
  } else {
    createForm.days = 0
  }
}

// 监听起止时间变化
import { watch } from 'vue'
watch([() => createForm.startTime, () => createForm.endTime], () => {
  calculateDays()
})

// 格式化金额
const formatMoney = (amount) => {
  if (amount === null || amount === undefined) {
    amount = 0
  }
  return '¥' + Number(amount).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// 格式化时间
const formatDateTime = (datetime) => {
  if (!datetime) return '-'
  const date = new Date(datetime)
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  return `${year}-${month}-${day} ${hours}:${minutes}`
}

// 格式化出差日期
const formatTravelDates = (startTime, endTime, days) => {
  if (!startTime || !endTime) return '-'
  const start = new Date(startTime)
  const end = new Date(endTime)
  return `${start.getMonth() + 1}/${start.getDate()} - ${end.getMonth() + 1}/${end.getDate()} (${days}天)`
}

// 状态类型映射
const getStatusType = (status) => {
  const map = {
    pending: 'warning',
    in_approval: 'warning',
    approved: 'success',
    rejected: 'danger'
  }
  return map[status] || 'info'
}

// 状态文本映射
const getStatusText = (status) => {
  const map = {
    pending: '待审批',
    in_approval: '审批中',
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
      start_date: searchForm.dateRange?.[0],
      end_date: searchForm.dateRange?.[1],
      status: searchForm.status,
      page: pagination.page,
      per_page: pagination.pageSize
    }
    const response = await getTravelApplications(params)
    if (response && response.success) {
      tableData.value = response.data.data || []
      pagination.total = response.data.total || 0
    }
  } catch (error) {
    console.error('Load travel application list error:', error)
    ElMessage.error('加载差旅申请列表失败')
    tableData.value = []
    pagination.total = 0
  } finally {
    loading.value = false
  }
}

// 重置
const handleReset = () => {
  searchForm.dateRange = null
  searchForm.status = null
  pagination.page = 1
  handleSearch()
}

// 发起差旅申请
const handleCreate = () => {
  createDialogVisible.value = true
  createForm.department = ''
  createForm.applyDate = ''
  createForm.applicant = ''
  createForm.destination = ''
  createForm.reason = ''
  createForm.startTime = ''
  createForm.endTime = ''
  createForm.days = 0
  createForm.advanceAmount = null
  createForm.paymentDate = ''
  createForm.remarks = ''
  attachmentFileList.value = []
}

// 填充测试数据
const fillTestData = () => {
  createForm.department = projectList.value.length > 0 ? projectList.value[0].name : ''
  createForm.applyDate = new Date().toISOString().split('T')[0]
  createForm.applicant = '张三'
  createForm.destination = '北京市'
  createForm.reason = '前往北京参加业务培训会议，预计3天'
  createForm.startTime = new Date(Date.now() + 2 * 24 * 60 * 60 * 1000).toISOString().slice(0, 16) + ':00'
  createForm.endTime = new Date(Date.now() + 4 * 24 * 60 * 60 * 1000).toISOString().slice(0, 16) + ':00'
  createForm.advanceAmount = 2000.00
  createForm.paymentDate = new Date().toISOString().split('T')[0]
  createForm.remarks = '住宿标准300元/天，交通费实报实销'
  
  // 触发天数计算
  calculateDays()
  
  ElMessage.success('已填充测试数据')
}

// 上传附件到服务器
const uploadFileToServer = async (file, travelApplicationId) => {
  try {
    const formData = new FormData()
    formData.append('file', file.raw)
    formData.append('travel_application_id', travelApplicationId)
    formData.append('current_account_set_id', accountSetStore.currentAccountSetId)
    
    await uploadTravelApplicationAttachment(formData)
    return true
  } catch (error) {
    console.error(`上传文件 ${file.name} 失败:`, error)
    ElMessage.error(`上传文件 ${file.name} 失败`)
    return false
  }
}

// 确认创建
const handleConfirmCreate = async () => {
  try {
    // 验证表单
    await createFormRef.value.validate()

    // 验证附件
    if (attachmentFileList.value.length === 0) {
      ElMessage.error('请至少上传一个附件！')
      return
    }

    submitting.value = true

    // 1. 创建差旅申请记录
    const response = await createTravelApplication({
      department: createForm.department,
      apply_date: createForm.applyDate,
      applicant: createForm.applicant,
      destination: createForm.destination,
      reason: createForm.reason,
      start_time: createForm.startTime,
      end_time: createForm.endTime,
      days: createForm.days,
      advance_amount: createForm.advanceAmount,
      payment_date: createForm.paymentDate,
      remarks: createForm.remarks,
      current_account_set_id: accountSetStore.currentAccountSetId
    })

    if (!response.data || !response.data.id) {
      throw new Error('创建差旅申请失败：未返回申请ID')
    }

    const travelApplicationId = response.data.id

    // 2. 上传所有附件
    ElMessage.info('正在上传附件...')
    let uploadCount = 0
    for (const file of attachmentFileList.value) {
      const success = await uploadFileToServer(file, travelApplicationId)
      if (success) {
        uploadCount++
      }
    }

    if (uploadCount === 0) {
      ElMessage.error('所有文件上传失败，请重试')
      return
    }

    // 3. 完成提交，创建审批流程
    ElMessage.info('正在创建审批流程...')
    
    // 保存申请ID，打开盖章方式选择对话框
    pendingTravelApplicationId.value = travelApplicationId
    stampMethodForm.stamp_method = 'online'
    stampMethodDialogVisible.value = true
    submitting.value = false
  } catch (error) {
    if (error !== false) {
      console.error('Create travel application error:', error)
      ElMessage.error(error.response?.data?.message || error.message || '提交差旅申请失败')
    }
    submitting.value = false
  }
}

// 确认盖章方式并完成提交
const confirmStampMethodAndSubmit = async () => {
  try {
    submitting.value = true
    const completeResponse = await completeTravelApplicationSubmission({
      travel_application_id: pendingTravelApplicationId.value,
      current_account_set_id: accountSetStore.currentAccountSetId,
      stamp_method: stampMethodForm.stamp_method
    })

    if (completeResponse.success) {
      ElMessage.success(`差旅申请已提交！审批流程已创建`)
    } else {
      ElMessage.warning(`附件已上传，但创建审批流程失败: ${completeResponse.message}`)
    }

    stampMethodDialogVisible.value = false
    createDialogVisible.value = false
    handleSearch()
  } catch (error) {
    console.error('Complete submission error:', error)
    ElMessage.error(error.response?.data?.message || error.message || '提交失败')
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
  if (filePath.startsWith('http://') || filePath.startsWith('https://')) {
    return filePath
  }
  const baseUrl = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'
  return `${baseUrl}/storage/${filePath}`
}

// 预览附件
const handlePreviewAttachment = (attachment) => {
  const fileUrl = getFileUrl(attachment.file_path)
  const fileName = attachment.file_name
  const fileExt = fileName.split('.').pop().toLowerCase()
  
  const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']
  const pdfExts = ['pdf']
  
  if (imageExts.includes(fileExt) || pdfExts.includes(fileExt)) {
    window.open(fileUrl, '_blank')
  } else {
    ElMessage.info('该文件类型不支持预览，请下载后查看')
    handleDownloadAttachment(attachment)
  }
}

// 下载附件
const handleDownloadAttachment = (attachment) => {
  const fileUrl = getFileUrl(attachment.file_path)
  
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
  ElMessageBox.prompt('请输入审批意见（可选）', '审批差旅申请', {
    confirmButtonText: '通过',
    cancelButtonText: '拒绝',
    inputPattern: /.*/,
    distinguishCancelAndClose: true,
    beforeClose: (action, instance, done) => {
      if (action === 'confirm') {
        ElMessage.success('审批通过')
        handleSearch()
        done()
      } else if (action === 'cancel') {
        ElMessageBox.prompt('请输入拒绝原因', '拒绝差旅申请', {
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
    `确定删除 ${row.applicant} 的差旅申请吗？`,
    '删除确认',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    }
  ).then(async () => {
    try {
      await deleteTravelApplication(row.id)
      ElMessage.success('删除成功')
      handleSearch()
    } catch (error) {
      console.error('Delete error:', error)
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }).catch(() => {})
}

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

// 初始化
onMounted(() => {
  handleSearch()
  loadProjects()
})
</script>

<style scoped>
.travel-application-page {
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

.el-upload__tip {
  font-size: 12px;
  color: #606266;
  margin-top: 7px;
  line-height: 1.5;
}
</style>

