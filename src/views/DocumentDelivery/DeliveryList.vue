<template>
  <div class="delivery-list-container">
    <el-card class="header-card">
      <el-form :model="filterForm" inline>
        <el-form-item label="项目">
          <el-select v-model="filterForm.project_id" placeholder="全部项目" clearable style="width: 200px;">
            <el-option
              v-for="project in projectList"
              :key="project.id"
              :label="project.name"
              :value="project.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="任务月份">
          <el-date-picker
            v-model="filterForm.delivery_period"
            type="month"
            placeholder="选择年月"
            format="YYYY-MM"
            value-format="YYYY-MM"
            clearable
            style="width: 180px;"
          />
        </el-form-item>
        <el-form-item label="交付周期">
          <el-select v-model="filterForm.delivery_cycle" placeholder="全部" clearable style="width: 120px;">
            <el-option label="按月" value="monthly" />
            <el-option label="按季度" value="quarterly" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filterForm.status" placeholder="全部" clearable style="width: 120px;">
            <el-option label="待交付" value="pending" />
            <el-option label="已提交" value="submitted" />
            <el-option label="已完成" value="completed" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
          <el-button :icon="Refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card">
      <el-table :data="deliveryList" v-loading="loading" border stripe>
        <el-table-column label="ID" width="70" align="center">
          <template #default="{ $index }">
            {{ (pagination.current - 1) * pagination.pageSize + $index + 1 }}
          </template>
        </el-table-column>
        <el-table-column label="项目名称" min-width="160">
          <template #default="{ row }">
            {{ row.project?.name || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="display_month" label="任务月份" width="120" />
        <el-table-column prop="delivery_period" label="交付期间" width="120" />
        <el-table-column prop="document_period" label="所属期" width="120">
          <template #default="{ row }">
            {{ row.document_period || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="交付周期" width="100">
          <template #default="{ row }">
            <el-tag size="small" :type="row.delivery_cycle === 'monthly' ? 'primary' : 'success'">
              {{ row.delivery_cycle === 'monthly' ? '按月' : '按季度' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="生成方式" width="100">
          <template #default="{ row }">
            <el-tag size="small" :type="row.delivery_release_month === 'next' ? 'warning' : 'success'">
              {{ row.delivery_release_month === 'next' ? '次月' : '当月' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="交付方式" width="100">
          <template #default="{ row }">
            <el-tag size="small" :type="row.delivery_method === 'express' ? 'warning' : 'info'">
              {{ row.delivery_method === 'express' ? '快递' : '电子' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.status === 'pending'" type="warning">待交付</el-tag>
            <el-tag v-else-if="row.status === 'submitted'" type="primary">已提交</el-tag>
            <el-tag v-else-if="row.status === 'completed'" type="success">已完成</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="交付进度" width="100" align="center">
          <template #default="{ row }">
            {{ row.submitted_item_count || 0 }}/{{ row.total_item_count || 0 }}
          </template>
        </el-table-column>
        <el-table-column label="未交付资料" min-width="220">
          <template #default="{ row }">
            <span>{{ formatDocumentList(row.pending_documents) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="附件数量" width="90" align="center">
          <template #default="{ row }">
            {{ row.attachment_count || 0 }}
          </template>
        </el-table-column>
        <el-table-column label="提交人" width="100">
          <template #default="{ row }">
            {{ row.submitter?.name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="提交时间" width="160">
          <template #default="{ row }">
            {{ formatDateTime(row.submitted_at) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="220" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" :icon="View" @click="handleViewDetail(row)">详情</el-button>
            <el-button
              v-if="row.status === 'pending'"
              link
              type="success"
              :icon="Upload"
              @click="handleSubmit(row)"
            >
              提交交付
            </el-button>
            <el-button
              v-if="row.status === 'submitted'"
              link
              type="success"
              @click="handleMarkCompleted(row)"
            >
              标记完成
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-container">
        <el-pagination
          v-model:current-page="pagination.current"
          v-model:page-size="pagination.pageSize"
          :total="pagination.total"
          :page-sizes="[15, 30, 50]"
          layout="total, sizes, prev, pager, next, jumper"
          @current-change="loadDeliveryList"
          @size-change="loadDeliveryList"
        />
      </div>
    </el-card>

    <el-dialog
      v-model="detailDialogVisible"
      title="交付详情"
      width="980px"
    >
      <template v-if="currentDelivery">
        <el-descriptions :column="3" border>
          <el-descriptions-item label="任务月份">
            {{ currentDelivery.display_month || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="项目名称">
            {{ currentDelivery.project?.name || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag v-if="currentDelivery.status === 'pending'" type="warning">待交付</el-tag>
            <el-tag v-else-if="currentDelivery.status === 'submitted'" type="primary">已提交</el-tag>
            <el-tag v-else-if="currentDelivery.status === 'completed'" type="success">已完成</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="交付期间">
            {{ currentDelivery.delivery_period || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="所属期">
            {{ currentDelivery.document_period || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="交付进度">
            {{ currentDelivery.submitted_item_count || 0 }}/{{ currentDelivery.total_item_count || 0 }}
          </el-descriptions-item>
          <el-descriptions-item label="交付周期">
            {{ currentDelivery.delivery_cycle === 'monthly' ? '按月交付' : '按季度交付' }}
          </el-descriptions-item>
          <el-descriptions-item label="生成方式">
            {{ currentDelivery.delivery_release_month === 'next' ? '次月' : '当月' }}
          </el-descriptions-item>
          <el-descriptions-item label="交付方式">
            {{ currentDelivery.delivery_method === 'express' ? '快递交付' : '电子推送' }}
          </el-descriptions-item>
          <el-descriptions-item label="快递单号">
            {{ currentDelivery.express_number || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="提交人">
            {{ currentDelivery.submitter?.name || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="提交时间">
            {{ formatDateTime(currentDelivery.submitted_at) }}
          </el-descriptions-item>
          <el-descriptions-item label="寄出日期">
            {{ currentDelivery.express_date || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="未交付资料" :span="3">
            {{ formatDocumentList(currentDelivery.pending_documents) }}
          </el-descriptions-item>
          <el-descriptions-item label="已提交资料" :span="3">
            {{ currentDelivery.submitted_documents || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="备注" :span="3">
            {{ currentDelivery.remarks || '-' }}
          </el-descriptions-item>
        </el-descriptions>

        <div class="section-title">资料交付明细</div>
        <el-table :data="currentDelivery.items || []" border stripe>
          <el-table-column prop="document_name" label="资料名称" min-width="180" />
          <el-table-column label="状态" width="100" align="center">
            <template #default="{ row }">
              <el-tag v-if="row.status === 'pending'" type="warning">待交付</el-tag>
              <el-tag v-else-if="row.status === 'submitted'" type="primary">已提交</el-tag>
              <el-tag v-else-if="row.status === 'completed'" type="success">已完成</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="express_number" label="快递单号" width="140" />
          <el-table-column label="附件数量" width="90" align="center">
            <template #default="{ row }">
              {{ row.attachment_count || 0 }}
            </template>
          </el-table-column>
          <el-table-column label="提交人" width="100">
            <template #default="{ row }">
              {{ row.submitter?.name || '-' }}
            </template>
          </el-table-column>
          <el-table-column label="提交时间" width="160">
            <template #default="{ row }">
              {{ formatDateTime(row.submitted_at) }}
            </template>
          </el-table-column>
          <el-table-column prop="remarks" label="备注" min-width="180" />
        </el-table>

        <template v-if="hasItemAttachments(currentDelivery.items)">
          <div class="section-title">资料附件</div>
          <div
            v-for="item in currentDelivery.items"
            :key="item.id"
            class="attachment-group"
          >
            <div class="attachment-group-title">{{ item.document_name }}</div>
            <div v-if="item.attachments && item.attachments.length > 0">
              <div v-for="att in item.attachments" :key="att.id" class="attachment-row">
                <el-link type="primary" @click="handleDownloadAttachment(att)">
                  <el-icon><Document /></el-icon>
                  {{ att.filename }} ({{ formatFileSize(att.file_size) }})
                </el-link>
              </div>
            </div>
            <div v-else class="empty-text">暂无附件</div>
          </div>
        </template>

        <template v-if="currentDelivery.legacy_attachments && currentDelivery.legacy_attachments.length > 0">
          <div class="section-title">历史附件</div>
          <div v-for="att in currentDelivery.legacy_attachments" :key="att.id" class="attachment-row">
            <el-link type="primary" @click="handleDownloadAttachment(att)">
              <el-icon><Document /></el-icon>
              {{ att.filename }} ({{ formatFileSize(att.file_size) }})
            </el-link>
          </div>
        </template>
      </template>
    </el-dialog>

    <el-dialog
      v-model="submitDialogVisible"
      :title="submitDialogTitle"
      width="680px"
      :close-on-click-modal="false"
    >
      <template v-if="currentDelivery">
        <el-alert
          type="info"
          :closable="false"
          show-icon
          class="submit-alert"
          :title="`未交付资料：${formatDocumentList(pendingDeliveryItems.map(item => item.document_name))}`"
        />

        <el-form
          ref="submitFormRef"
          :model="submitForm"
          :rules="submitFormRules"
          label-width="110px"
        >
          <el-form-item label="所属期" prop="document_period">
            <el-date-picker
              v-model="submitForm.document_period"
              type="month"
              placeholder="选择所属期"
              format="YYYY-MM"
              value-format="YYYY-MM"
              style="width: 100%;"
            />
          </el-form-item>

          <el-form-item label="交付资料" prop="delivery_item_id">
            <el-select
              v-model="submitForm.delivery_item_id"
              placeholder="请选择本次交付资料"
              style="width: 100%;"
              @change="handleDeliveryItemChange"
            >
              <el-option
                v-for="item in pendingDeliveryItems"
                :key="item.id"
                :label="item.document_name"
                :value="item.id"
              />
            </el-select>
          </el-form-item>

          <template v-if="currentDelivery.delivery_method === 'express'">
            <el-form-item label="快递单号" prop="express_number">
              <el-input v-model="submitForm.express_number" placeholder="请输入快递单号" />
            </el-form-item>
            <el-form-item label="寄出日期" prop="express_date">
              <el-date-picker
                v-model="submitForm.express_date"
                type="date"
                placeholder="选择寄出日期"
                value-format="YYYY-MM-DD"
                style="width: 100%;"
              />
            </el-form-item>
            <el-form-item label="资料说明">
              <el-input
                v-model="submitForm.submitted_documents"
                type="textarea"
                :rows="3"
                placeholder="可填写本次交付说明"
              />
            </el-form-item>
          </template>

          <template v-else>
            <el-form-item label="上传附件">
              <el-upload
                ref="uploadRef"
                :auto-upload="false"
                :on-change="handleFileChange"
                :on-remove="handleFileRemove"
                :file-list="fileList"
                multiple
              >
                <el-button :icon="Upload">选择文件</el-button>
                <template #tip>
                  <div class="upload-tip">支持多个文件，单个文件不超过50MB</div>
                </template>
              </el-upload>
            </el-form-item>
            <el-form-item label="资料说明">
              <el-input
                v-model="submitForm.submitted_documents"
                type="textarea"
                :rows="3"
                placeholder="可填写本次交付说明"
              />
            </el-form-item>
          </template>

          <el-form-item label="备注">
            <el-input
              v-model="submitForm.remarks"
              type="textarea"
              :rows="2"
              placeholder="填写备注信息（可选）"
            />
          </el-form-item>
        </el-form>

        <div v-if="submittedDeliveryItems.length > 0" class="submitted-section">
          <div class="section-title">已交付资料</div>
          <div class="submitted-tags">
            <el-tag
              v-for="item in submittedDeliveryItems"
              :key="item.id"
              type="success"
              effect="plain"
            >
              {{ item.document_name }}
            </el-tag>
          </div>
        </div>
      </template>

      <template #footer>
        <el-button @click="submitDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="confirmSubmit">确定提交</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Document, Refresh, Search, Upload, View } from '@element-plus/icons-vue'
import { useAccountSetStore } from '@/stores/accountSet'
import { getProjects } from '@/api/projects'
import {
  downloadDeliveryAttachment,
  getDeliveryDetail,
  getDocumentDeliveries,
  markDeliveryAsCompleted,
  submitElectronicDelivery,
  submitExpressDelivery,
  uploadDeliveryAttachment
} from '@/api/documentDelivery'

const accountSetStore = useAccountSetStore()

const projectList = ref([])

const getCurrentYearMonth = () => {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  return `${year}-${month}`
}

const filterForm = reactive({
  project_id: null,
  delivery_period: getCurrentYearMonth(),
  delivery_cycle: '',
  status: ''
})

const loading = ref(false)
const deliveryList = ref([])
const pagination = reactive({
  current: 1,
  pageSize: 15,
  total: 0
})

const detailDialogVisible = ref(false)
const currentDelivery = ref(null)

const submitDialogVisible = ref(false)
const submitDialogTitle = ref('')
const submitFormRef = ref(null)
const submitting = ref(false)
const uploadRef = ref(null)
const fileList = ref([])

const submitForm = reactive({
  document_period: '',
  delivery_item_id: null,
  express_number: '',
  express_date: '',
  submitted_documents: '',
  remarks: ''
})

const submitFormRules = {
  document_period: [
    { required: true, message: '请选择所属期', trigger: 'change' }
  ],
  delivery_item_id: [
    { required: true, message: '请选择交付资料', trigger: 'change' }
  ],
  express_number: [
    { required: true, message: '请输入快递单号', trigger: 'blur' }
  ],
  express_date: [
    { required: true, message: '请选择寄出日期', trigger: 'change' }
  ]
}

const pendingDeliveryItems = computed(() => {
  return (currentDelivery.value?.items || []).filter(item => item.status === 'pending')
})

const submittedDeliveryItems = computed(() => {
  return (currentDelivery.value?.items || []).filter(item => item.status !== 'pending')
})

const formatDateTime = (datetime) => {
  if (!datetime) return '-'
  return new Date(datetime).toLocaleString('zh-CN')
}

const formatFileSize = (bytes) => {
  if (!bytes) return '-'
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(2)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(2)} MB`
}

const formatDocumentList = (documents) => {
  if (!documents || documents.length === 0) {
    return '-'
  }
  return documents.join('、')
}

const hasItemAttachments = (items = []) => {
  return items.some(item => item.attachments && item.attachments.length > 0)
}

const handleDownloadAttachment = async (attachment) => {
  try {
    if (!attachment?.id || !currentDelivery.value?.id) {
      ElMessage.error('附件信息不完整')
      return
    }

    ElMessage.info('正在下载...')

    const blob = await downloadDeliveryAttachment(currentDelivery.value.id, attachment.id)
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = attachment.filename || '附件'
    link.style.display = 'none'
    document.body.appendChild(link)
    link.click()
    setTimeout(() => {
      document.body.removeChild(link)
      window.URL.revokeObjectURL(url)
    }, 100)
    ElMessage.success('下载成功')
  } catch (error) {
    console.error('Download error:', error)
    ElMessage.error(`下载失败: ${error.message || '未知错误'}`)
  }
}

const loadProjects = async () => {
  try {
    const res = await getProjects({
      current_account_set_id: accountSetStore.currentAccountSetId,
      responsibility_role_type: 'delivery'
    })
    if (res.success) {
      if (Array.isArray(res.data)) {
        projectList.value = res.data
      } else if (res.data && Array.isArray(res.data.data)) {
        projectList.value = res.data.data
      } else {
        projectList.value = []
      }
    }
  } catch (error) {
    console.error('Load projects error:', error)
    projectList.value = []
  }
}

const loadDeliveryList = async () => {
  loading.value = true
  try {
    const res = await getDocumentDeliveries({
      current_account_set_id: accountSetStore.currentAccountSetId,
      ...filterForm,
      page: pagination.current,
      per_page: pagination.pageSize
    })

    if (res.success) {
      deliveryList.value = res.data.data
      pagination.total = res.data.total
    } else {
      ElMessage.error(res.message || '获取列表失败')
    }
  } catch (error) {
    console.error('Load delivery list error:', error)
    ElMessage.error('获取列表失败')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  pagination.current = 1
  loadDeliveryList()
}

const handleReset = () => {
  filterForm.project_id = null
  filterForm.delivery_period = getCurrentYearMonth()
  filterForm.delivery_cycle = ''
  filterForm.status = ''
  pagination.current = 1
  loadDeliveryList()
}

const fetchDeliveryDetail = async (id) => {
  const res = await getDeliveryDetail(id)
  if (!res.success) {
    throw new Error(res.message || '加载详情失败')
  }
  return res.data
}

const handleViewDetail = async (row) => {
  try {
    currentDelivery.value = await fetchDeliveryDetail(row.id)
    detailDialogVisible.value = true
  } catch (error) {
    console.error('Load detail error:', error)
    ElMessage.error(error.message || '加载详情失败')
  }
}

const handleSubmit = async (row) => {
  try {
    currentDelivery.value = await fetchDeliveryDetail(row.id)

    if (pendingDeliveryItems.value.length === 0) {
      ElMessage.warning('当前没有可提交的资料')
      return
    }

    submitDialogTitle.value = `提交交付 - ${row.project?.name || ''} ${row.display_month || row.delivery_period || ''}`
    resetSubmitForm()
    submitDialogVisible.value = true
  } catch (error) {
    console.error('Open submit dialog error:', error)
    ElMessage.error(error.message || '加载交付详情失败')
  }
}

const handleDeliveryItemChange = () => {
  if (!submitForm.submitted_documents) {
    const selected = pendingDeliveryItems.value.find(item => item.id === submitForm.delivery_item_id)
    if (selected) {
      submitForm.submitted_documents = selected.document_name
    }
  }
}

const handleFileChange = (_file, files) => {
  fileList.value = files.slice()
}

const handleFileRemove = (_file, files) => {
  fileList.value = files.slice()
}

const confirmSubmit = async () => {
  if (!submitFormRef.value || !currentDelivery.value) return

  try {
    await submitFormRef.value.validate()
  } catch {
    return
  }

  if (currentDelivery.value.delivery_method === 'electronic' && fileList.value.length === 0) {
    ElMessage.warning('请至少上传一个文件')
    return
  }

  submitting.value = true
  try {
    let res

    if (currentDelivery.value.delivery_method === 'express') {
      res = await submitExpressDelivery(currentDelivery.value.id, {
        document_period: submitForm.document_period,
        delivery_item_id: submitForm.delivery_item_id,
        express_number: submitForm.express_number,
        express_date: submitForm.express_date,
        submitted_documents: submitForm.submitted_documents,
        remarks: submitForm.remarks
      })
    } else {
      for (const fileItem of fileList.value) {
        await uploadDeliveryAttachment(
          currentDelivery.value.id,
          fileItem.raw,
          submitForm.delivery_item_id
        )
      }

      res = await submitElectronicDelivery(currentDelivery.value.id, {
        document_period: submitForm.document_period,
        delivery_item_id: submitForm.delivery_item_id,
        submitted_documents: submitForm.submitted_documents,
        remarks: submitForm.remarks
      })
    }

    if (!res.success) {
      ElMessage.error(res.message || '提交失败')
      return
    }

    ElMessage.success('交付提交成功')
    currentDelivery.value = res.data

    if (currentDelivery.value.status === 'pending') {
      resetSubmitForm()
    } else {
      submitDialogVisible.value = false
    }

    await loadDeliveryList()
  } catch (error) {
    console.error('Submit delivery error:', error)
    ElMessage.error(error.message || '提交失败')
  } finally {
    submitting.value = false
  }
}

const handleMarkCompleted = (row) => {
  ElMessageBox.confirm(
    `确定要将"${row.project?.name || ''} ${row.delivery_period || ''}"标记为已完成吗？`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'success'
    }
  ).then(async () => {
    try {
      const res = await markDeliveryAsCompleted(row.id)
      if (res.success) {
        ElMessage.success('已标记为完成')
        loadDeliveryList()
      } else {
        ElMessage.error(res.message || '操作失败')
      }
    } catch (error) {
      console.error('Mark completed error:', error)
      ElMessage.error('操作失败')
    }
  }).catch(() => {})
}

const resetSubmitForm = () => {
  if (submitFormRef.value) {
    submitFormRef.value.resetFields()
  }

  const defaultPeriod = currentDelivery.value?.document_period || currentDelivery.value?.delivery_period || ''

  Object.assign(submitForm, {
    document_period: defaultPeriod,
    delivery_item_id: pendingDeliveryItems.value[0]?.id || null,
    express_number: '',
    express_date: '',
    submitted_documents: pendingDeliveryItems.value[0]?.document_name || '',
    remarks: ''
  })

  fileList.value = []
}

onMounted(() => {
  loadProjects()
  loadDeliveryList()
})
</script>

<style scoped>
.delivery-list-container {
  padding: 20px;
}

.header-card {
  margin-bottom: 20px;
}

.table-card {
  margin-top: 20px;
}

.pagination-container {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.section-title {
  margin: 18px 0 12px;
  font-size: 14px;
  font-weight: 600;
  color: #303133;
}

.attachment-group {
  padding: 12px 0;
  border-bottom: 1px solid #ebeef5;
}

.attachment-group:last-child {
  border-bottom: none;
}

.attachment-group-title {
  margin-bottom: 8px;
  font-weight: 500;
  color: #303133;
}

.attachment-row {
  margin-bottom: 6px;
}

.empty-text {
  color: #909399;
  font-size: 13px;
}

.submit-alert {
  margin-bottom: 16px;
}

.upload-tip {
  color: #999;
  font-size: 12px;
}

.submitted-section {
  margin-top: 8px;
}

.submitted-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
</style>
