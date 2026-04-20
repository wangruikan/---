<template>
  <div class="delivery-list-container">
    <!-- 头部筛选 -->
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
        <el-form-item label="交付期间">
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

    <!-- 列表 -->
    <el-card class="table-card">
      <el-table :data="deliveryList" v-loading="loading" border stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column label="项目名称" width="160">
          <template #default="{ row }">
            {{ row.project?.name || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="delivery_period" label="交付期间" width="120" />
        <el-table-column label="交付周期" width="100">
          <template #default="{ row }">
            <el-tag size="small" :type="row.delivery_cycle === 'monthly' ? 'primary' : 'success'">
              {{ row.delivery_cycle === 'monthly' ? '按月' : '按季度' }}
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
        <el-table-column label="快递单号" width="140">
          <template #default="{ row }">
            {{ row.express_number || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="附件数量" width="90" align="center">
          <template #default="{ row }">
            {{ row.attachments?.length || 0 }}
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
        <el-table-column label="操作" width="200" fixed="right">
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

      <!-- 分页 -->
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

    <!-- 详情对话框 -->
    <el-dialog
      v-model="detailDialogVisible"
      title="交付详情"
      width="700px"
    >
      <el-descriptions v-if="currentDelivery" :column="2" border>
        <el-descriptions-item label="项目名称">
          {{ currentDelivery.project?.name }}
        </el-descriptions-item>
        <el-descriptions-item label="交付期间">
          {{ currentDelivery.delivery_period }}
        </el-descriptions-item>
        <el-descriptions-item label="交付周期">
          {{ currentDelivery.delivery_cycle === 'monthly' ? '按月交付' : '按季度交付' }}
        </el-descriptions-item>
        <el-descriptions-item label="交付方式">
          {{ currentDelivery.delivery_method === 'express' ? '快递交付' : '电子推送' }}
        </el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag v-if="currentDelivery.status === 'pending'" type="warning">待交付</el-tag>
          <el-tag v-else-if="currentDelivery.status === 'submitted'" type="primary">已提交</el-tag>
          <el-tag v-else-if="currentDelivery.status === 'completed'" type="success">已完成</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="快递单号" v-if="currentDelivery.express_number">
          {{ currentDelivery.express_number }}
        </el-descriptions-item>
        <el-descriptions-item label="寄出日期" v-if="currentDelivery.express_date">
          {{ currentDelivery.express_date }}
        </el-descriptions-item>
        <el-descriptions-item label="提交人" v-if="currentDelivery.submitter">
          {{ currentDelivery.submitter.name }}
        </el-descriptions-item>
        <el-descriptions-item label="提交时间" v-if="currentDelivery.submitted_at">
          {{ formatDateTime(currentDelivery.submitted_at) }}
        </el-descriptions-item>
        <el-descriptions-item label="资料说明" :span="2" v-if="currentDelivery.submitted_documents">
          {{ currentDelivery.submitted_documents }}
        </el-descriptions-item>
        <el-descriptions-item label="备注" :span="2" v-if="currentDelivery.remarks">
          {{ currentDelivery.remarks }}
        </el-descriptions-item>
        <el-descriptions-item label="附件列表" :span="2" v-if="currentDelivery.attachments && currentDelivery.attachments.length > 0">
          <div v-for="att in currentDelivery.attachments" :key="att.id" style="margin-bottom: 5px;">
            <el-link :href="`/${att.file_path}`" target="_blank" type="primary">
              <el-icon><Document /></el-icon>
              {{ att.filename }} ({{ formatFileSize(att.file_size) }})
            </el-link>
          </div>
        </el-descriptions-item>
      </el-descriptions>
    </el-dialog>

    <!-- 提交对话框 -->
    <el-dialog
      v-model="submitDialogVisible"
      :title="submitDialogTitle"
      width="600px"
      :close-on-click-modal="false"
    >
      <el-form
        ref="submitFormRef"
        :model="submitForm"
        :rules="submitFormRules"
        label-width="110px"
      >
        <!-- 快递方式 -->
        <template v-if="currentDelivery && currentDelivery.delivery_method === 'express'">
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
          <el-form-item label="资料说明" prop="submitted_documents">
            <el-input
              v-model="submitForm.submitted_documents"
              type="textarea"
              :rows="3"
              placeholder="请填写已寄出的资料内容"
            />
          </el-form-item>
        </template>

        <!-- 电子方式 -->
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
                <div style="color: #999; font-size: 12px;">
                  支持多个文件，单个文件不超过50MB
                </div>
              </template>
            </el-upload>
          </el-form-item>
          <el-form-item label="资料说明">
            <el-input
              v-model="submitForm.submitted_documents"
              type="textarea"
              :rows="3"
              placeholder="请填写资料说明（可选）"
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
      <template #footer>
        <el-button @click="submitDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="confirmSubmit">确定提交</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Refresh, View, Upload, Document } from '@element-plus/icons-vue'
import { useAccountSetStore } from '@/stores/accountSet'
import { getProjects } from '@/api/projects'
import {
  getDocumentDeliveries,
  getDeliveryDetail,
  submitExpressDelivery,
  submitElectronicDelivery,
  markDeliveryAsCompleted,
  uploadDeliveryAttachment
} from '@/api/documentDelivery'

const accountSetStore = useAccountSetStore()

// 项目列表
const projectList = ref([])

// 获取当前年月
const getCurrentYearMonth = () => {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  return `${year}-${month}`
}

// 筛选表单
const filterForm = reactive({
  project_id: null,
  delivery_period: getCurrentYearMonth(), // 默认当前月份
  delivery_cycle: '',
  status: ''
})

// 列表数据
const loading = ref(false)
const deliveryList = ref([])
const pagination = reactive({
  current: 1,
  pageSize: 15,
  total: 0
})

// 详情对话框
const detailDialogVisible = ref(false)
const currentDelivery = ref(null)

// 提交对话框
const submitDialogVisible = ref(false)
const submitDialogTitle = ref('')
const submitFormRef = ref(null)
const submitting = ref(false)
const uploadRef = ref(null)
const fileList = ref([])

// 提交表单
const submitForm = reactive({
  express_number: '',
  express_date: '',
  submitted_documents: '',
  remarks: ''
})

// 验证规则
const submitFormRules = {
  express_number: [
    { required: true, message: '请输入快递单号', trigger: 'blur' }
  ],
  express_date: [
    { required: true, message: '请选择寄出日期', trigger: 'change' }
  ],
  submitted_documents: [
    { required: true, message: '请填写资料说明', trigger: 'blur' }
  ]
}

// 格式化时间
const formatDateTime = (datetime) => {
  if (!datetime) return '-'
  return new Date(datetime).toLocaleString('zh-CN')
}

// 格式化文件大小
const formatFileSize = (bytes) => {
  if (!bytes) return '-'
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(2) + ' MB'
}

// 加载项目列表
const loadProjects = async () => {
  try {
    const res = await getProjects({
      current_account_set_id: accountSetStore.currentAccountSetId
    })
    if (res.success) {
      // 确保 res.data 是数组，如果是分页数据则取 data 属性
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

// 加载交付列表
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

// 查询
const handleSearch = () => {
  pagination.current = 1
  loadDeliveryList()
}

// 重置
const handleReset = () => {
  filterForm.project_id = null
  filterForm.delivery_period = getCurrentYearMonth() // 重置为当前月份
  filterForm.delivery_cycle = ''
  filterForm.status = ''
  pagination.current = 1
  loadDeliveryList()
}

// 查看详情
const handleViewDetail = async (row) => {
  try {
    const res = await getDeliveryDetail(row.id)
    if (res.success) {
      currentDelivery.value = res.data
      detailDialogVisible.value = true
    }
  } catch (error) {
    console.error('Load detail error:', error)
    ElMessage.error('加载详情失败')
  }
}

// 提交交付
const handleSubmit = (row) => {
  currentDelivery.value = row
  submitDialogTitle.value = `提交交付 - ${row.project?.name} ${row.delivery_period}`
  resetSubmitForm()
  submitDialogVisible.value = true
}

// 文件选择
const handleFileChange = (file) => {
  fileList.value.push(file)
}

// 文件移除
const handleFileRemove = (file) => {
  const index = fileList.value.findIndex(f => f.uid === file.uid)
  if (index > -1) {
    fileList.value.splice(index, 1)
  }
}

// 确认提交
const confirmSubmit = async () => {
  if (!submitFormRef.value) return
  
  const delivery = currentDelivery.value
  
  if (delivery.delivery_method === 'express') {
    // 快递方式
    await submitFormRef.value.validate(async (valid) => {
      if (!valid) return
      
      submitting.value = true
      try {
        const res = await submitExpressDelivery(delivery.id, submitForm)
        if (res.success) {
          ElMessage.success('交付提交成功')
          submitDialogVisible.value = false
          loadDeliveryList()
        } else {
          ElMessage.error(res.message || '提交失败')
        }
      } catch (error) {
        console.error('Submit error:', error)
        ElMessage.error('提交失败')
      } finally {
        submitting.value = false
      }
    })
  } else {
    // 电子方式
    if (fileList.value.length === 0) {
      ElMessage.warning('请至少上传一个文件')
      return
    }
    
    submitting.value = true
    try {
      // 先上传所有文件
      for (const fileItem of fileList.value) {
        await uploadDeliveryAttachment(delivery.id, fileItem.raw)
      }
      
      // 再提交交付
      const res = await submitElectronicDelivery(delivery.id, {
        submitted_documents: submitForm.submitted_documents,
        remarks: submitForm.remarks
      })
      
      if (res.success) {
        ElMessage.success('交付提交成功')
        submitDialogVisible.value = false
        loadDeliveryList()
      } else {
        ElMessage.error(res.message || '提交失败')
      }
    } catch (error) {
      console.error('Submit error:', error)
      ElMessage.error('提交失败')
    } finally {
      submitting.value = false
    }
  }
}

// 标记完成
const handleMarkCompleted = (row) => {
  ElMessageBox.confirm(
    `确定要将"${row.project?.name} ${row.delivery_period}"标记为已完成吗？`,
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

// 重置提交表单
const resetSubmitForm = () => {
  if (submitFormRef.value) {
    submitFormRef.value.resetFields()
  }
  Object.assign(submitForm, {
    express_number: '',
    express_date: '',
    submitted_documents: '',
    remarks: ''
  })
  fileList.value = []
}

// 初始化
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
</style>

