<template>
  <div class="tax-declaration-tasks">
    <div class="page-header">
      <h1>税费申报任务</h1>
    </div>

    <el-card>
      <div class="search-header">
        <el-form :model="searchForm" inline>
          <el-form-item label="状态">
            <el-select v-model="searchForm.status" placeholder="全部" clearable style="width: 120px">
              <el-option label="待处理" value="pending" />
              <el-option label="已完成" value="completed" />
            </el-select>
          </el-form-item>
          <el-form-item label="年份">
            <el-date-picker
              v-model="searchForm.year"
              type="year"
              placeholder="选择年份"
              value-format="YYYY"
              clearable
              style="width: 150px"
            />
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="loadTasks">查询</el-button>
            <el-button @click="handleResetSearch">重置</el-button>
          </el-form-item>
        </el-form>
      </div>

      <el-table :data="tasks" v-loading="tasksLoading" border stripe>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="company_name" label="公司名称" min-width="200" />
        <el-table-column prop="handler_name" label="操作员" width="120" />
        <el-table-column label="税种" min-width="250">
          <template #default="{ row }">
            <el-tag
              v-for="category in row.tax_categories_list"
              :key="category.id"
              size="small"
              style="margin-right: 5px"
            >
              {{ category.name }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="申报日期" width="120">
          <template #default="{ row }">
            {{ formatDate(row.declaration_date) }}
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 'completed' ? 'success' : 'warning'">
              {{ row.status === 'completed' ? '已完成' : '待处理' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" size="small" link @click="handleViewTask(row)">
              查看
            </el-button>
            <el-button
              v-if="row.status === 'pending'"
              type="primary"
              size="small"
              link
              @click="handleUploadFile(row)"
            >
              上传
            </el-button>
            <el-button
              v-if="row.status === 'pending'"
              type="success"
              size="small"
              link
              @click="handleCompleteTask(row)"
            >
              完成
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
        @size-change="loadTasks"
        @current-change="loadTasks"
        style="margin-top: 20px; justify-content: flex-end"
      />
    </el-card>

    <!-- 任务详情对话框 -->
    <el-dialog v-model="taskDetailDialogVisible" title="任务详情" width="800px">
      <el-descriptions :column="2" border v-if="currentTask">
        <el-descriptions-item label="ID">{{ currentTask.id }}</el-descriptions-item>
        <el-descriptions-item label="公司名称">{{ currentTask.company_name }}</el-descriptions-item>
        <el-descriptions-item label="操作员">{{ currentTask.handler_name }}</el-descriptions-item>
        <el-descriptions-item label="申报日期">{{ formatDate(currentTask.declaration_date) }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="currentTask.status === 'completed' ? 'success' : 'warning'">
            {{ currentTask.status === 'completed' ? '已完成' : '待处理' }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="完成时间">
          {{ currentTask.completed_at ? formatDateTime(currentTask.completed_at) : '-' }}
        </el-descriptions-item>
        <el-descriptions-item label="税种列表" :span="2">
          <el-tag
            v-for="category in currentTask.tax_categories_list"
            :key="category.id"
            size="small"
            style="margin-right: 5px"
          >
            {{ category.name }}
          </el-tag>
        </el-descriptions-item>
      </el-descriptions>

      <el-divider content-position="left">附件管理</el-divider>

      <el-upload
        v-if="currentTask && currentTask.status === 'pending'"
        :http-request="customUpload"
        :show-file-list="false"
        style="margin-bottom: 20px"
      >
        <el-button type="primary" :icon="Upload">上传附件</el-button>
      </el-upload>

      <el-table :data="currentTask?.attachments" border>
        <el-table-column prop="file_name" label="文件名" min-width="200" show-overflow-tooltip />
        <el-table-column label="文件大小" width="100">
          <template #default="{ row }">
            {{ formatFileSize(row.file_size) }}
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="上传时间" width="180" />
        <el-table-column label="操作" width="150">
          <template #default="{ row }">
            <el-button type="primary" size="small" link @click="handleDownloadAttachment(row)">
              下载
            </el-button>
            <el-button
              v-if="currentTask.status === 'pending'"
              type="danger"
              size="small"
              link
              @click="handleDeleteAttachment(row)"
            >
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <template #footer>
        <el-button @click="taskDetailDialogVisible = false">关闭</el-button>
      </template>
    </el-dialog>

    <!-- 上传文件对话框 -->
    <el-dialog v-model="uploadDialogVisible" title="上传申报文件" width="600px">
      <el-upload
        :http-request="customUpload"
        :show-file-list="true"
        :auto-upload="true"
        drag
        multiple
      >
        <el-icon class="el-icon--upload"><upload-filled /></el-icon>
        <div class="el-upload__text">
          将文件拖到此处，或<em>点击上传</em>
        </div>
        <template #tip>
          <div class="el-upload__tip">
            支持上传多个文件，单个文件不超过50MB
          </div>
        </template>
      </el-upload>
      <template #footer>
        <el-button @click="uploadDialogVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Upload, UploadFilled } from '@element-plus/icons-vue'
import {
  getTasks,
  getTaskDetail,
  completeTask,
  uploadAttachment,
  deleteAttachment
} from '@/api/taxDeclaration'
import { useAccountSetStore } from '@/stores/accountSet'

const accountSetStore = useAccountSetStore()

// 申报任务相关
const tasks = ref([])
const tasksLoading = ref(false)
const searchForm = reactive({
  status: '',
  year: new Date().getFullYear().toString()
})

const pagination = reactive({
  currentPage: 1,
  pageSize: 20,
  total: 0
})

const taskDetailDialogVisible = ref(false)
const currentTask = ref(null)
const uploadDialogVisible = ref(false)
const uploadTaskId = ref(null)

// 加载申报任务
const loadTasks = async () => {
  tasksLoading.value = true
  try {
    const response = await getTasks({
      account_set_id: accountSetStore.currentAccountSetId,
      status: searchForm.status,
      year: searchForm.year,
      page: pagination.currentPage,
      per_page: pagination.pageSize
    })
    tasks.value = response.data
    pagination.total = response.total
  } catch (error) {
    console.error('加载任务失败:', error)
    ElMessage.error('加载失败')
  } finally {
    tasksLoading.value = false
  }
}

// 重置任务搜索
const handleResetSearch = () => {
  searchForm.status = ''
  searchForm.year = new Date().getFullYear().toString()
  pagination.currentPage = 1
  loadTasks()
}

// 查看任务详情
const handleViewTask = async (row) => {
  try {
    const response = await getTaskDetail(row.id)
    currentTask.value = response.data
    taskDetailDialogVisible.value = true
  } catch (error) {
    console.error('加载详情失败:', error)
    ElMessage.error('加载失败')
  }
}

// 完成任务
const handleCompleteTask = async (row) => {
  try {
    await ElMessageBox.confirm('确定要完成该任务吗？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    
    await completeTask(row.id)
    ElMessage.success('任务已完成')
    loadTasks()
    
    if (currentTask.value && currentTask.value.id === row.id) {
      const response = await getTaskDetail(row.id)
      currentTask.value = response.data
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('操作失败:', error)
      ElMessage.error(error.response?.data?.message || '操作失败')
    }
  }
}

// 自定义上传
const customUpload = async (options) => {
  const formData = new FormData()
  formData.append('file', options.file)
  formData.append('task_id', uploadTaskId.value)
  
  try {
    const result = await uploadAttachment(formData)
    
    if (result.success) {
      ElMessage.success('上传成功')
      options.onSuccess(result)
    } else {
      ElMessage.error(result.message || '上传失败')
      options.onError(new Error(result.message || '上传失败'))
    }
  } catch (error) {
    console.error('上传失败:', error)
    ElMessage.error('上传失败')
    options.onError(error)
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
const handleDownloadAttachment = async (attachment) => {
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
    console.error('Download tax declaration attachment error:', error)
    ElMessage.error('下载失败')
  }
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
    const response = await getTaskDetail(currentTask.value.id)
    currentTask.value = response.data
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      ElMessage.error('删除失败')
    }
  }
}

// 工具函数
const formatFileSize = (bytes) => {
  if (!bytes) return '-'
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(2) + ' MB'
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  // 如果是完整的日期时间格式，只取日期部分
  return dateStr.split(' ')[0]
}

const formatDateTime = (dateStr) => {
  if (!dateStr) return '-'
  // 返回完整的日期时间，但去掉多余的部分
  // 格式：YYYY-MM-DD HH:mm:ss
  if (dateStr.includes('T')) {
    // ISO 格式转换
    return dateStr.replace('T', ' ').split('.')[0]
  }
  return dateStr
}

// 打开上传对话框
const handleUploadFile = (row) => {
  uploadTaskId.value = row.id
  uploadDialogVisible.value = true
}

// 上传文件后刷新列表
const handleUploadSuccess = () => {
  uploadDialogVisible.value = false
  loadTasks()
}

onMounted(() => {
  loadTasks()
})
</script>

<style scoped>
.tax-declaration-tasks {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h1 {
  font-size: 24px;
  font-weight: bold;
  color: #303133;
}

.search-header {
  margin-bottom: 20px;
}
</style>
