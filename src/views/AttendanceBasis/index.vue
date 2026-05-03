<template>
  <div class="attendance-basis">
    <div class="page-header">
      <h1>考勤依据管理</h1>
    </div>

    <!-- 搜索栏 -->
    <el-card class="search-card">
      <el-form :model="searchForm" inline>
        <el-form-item label="项目">
          <el-select v-model="searchForm.project_id" placeholder="请选择项目" clearable style="width: 200px">
            <el-option
              v-for="project in availableProjects"
              :key="project.id"
              :label="project.name"
              :value="project.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="月份">
          <el-date-picker
            v-model="searchForm.month"
            type="month"
            placeholder="请选择月份"
            value-format="YYYY-MM"
            clearable
            style="width: 200px"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="handleReset">重置</el-button>
          <el-button type="success" @click="handleCreate">创建依据</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 列表 -->
    <el-card class="table-card">
      <el-table
        :data="records"
        v-loading="loading"
        stripe
        border
      >
        <el-table-column prop="project.name" label="项目名称" width="200" />
        <el-table-column prop="month" label="月份" width="120" />
        <el-table-column label="附件数量" width="100">
          <template #default="{ row }">
            {{ row.attachments?.length || 0 }}
          </template>
        </el-table-column>
        <el-table-column prop="description" label="说明" min-width="200" show-overflow-tooltip />
        <el-table-column prop="creator.name" label="创建人" width="120" />
        <el-table-column prop="created_at" label="创建时间" width="180">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" size="small" @click="handleView(row)">
              查看
            </el-button>
            <el-button type="warning" size="small" @click="handleEdit(row)">
              编辑
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
    </el-card>

    <!-- 创建/编辑对话框 -->
    <el-dialog
      v-model="dialogVisible"
      :title="isEdit ? '编辑考勤依据' : '创建考勤依据'"
      width="600px"
    >
      <el-form :model="form" :rules="formRules" ref="formRef" label-width="100px">
        <el-form-item label="项目" prop="project_id" v-if="!isEdit">
          <el-select v-model="form.project_id" placeholder="请选择项目" style="width: 100%">
            <el-option
              v-for="project in availableProjects"
              :key="project.id"
              :label="project.name"
              :value="project.id"
            />
          </el-select>
          <div class="form-tip">只显示设置了需要上传考勤依据的项目</div>
        </el-form-item>
        <el-form-item label="月份" prop="month" v-if="!isEdit">
          <el-date-picker
            v-model="form.month"
            type="month"
            placeholder="请选择月份"
            value-format="YYYY-MM"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="创建方式" prop="create_mode" v-if="!isEdit">
          <el-select v-model="form.create_mode" style="width: 100%">
            <el-option label="上传附件" value="upload" />
            <el-option label="复制上月" value="copy_last_month" />
            <el-option label="无附件" value="none" />
          </el-select>
          <div class="form-tip">上传：创建后上传；复制上月：自动复制上月附件；无附件：仅创建记录</div>
        </el-form-item>
        <el-form-item label="说明" prop="description">
          <el-input
            v-model="form.description"
            type="textarea"
            :rows="4"
            placeholder="请输入说明（可选）"
          />
        </el-form-item>
        
        <!-- 附件上传（仅编辑时显示） -->
        <el-form-item label="附件" v-if="isEdit && currentRecord">
          <el-upload
            :http-request="customUpload"
            :data="{ basis_record_id: currentRecord?.id }"
            :on-success="handleUploadSuccess"
            :on-error="handleUploadError"
            :show-file-list="false"
            style="margin-bottom: 10px"
          >
            <el-button type="primary" size="small" :icon="Upload">上传附件</el-button>
          </el-upload>
          
          <!-- 附件列表 -->
          <el-table :data="currentRecord?.attachments" border size="small" style="margin-top: 10px" v-if="currentRecord?.attachments?.length > 0">
            <el-table-column prop="file_name" label="文件名" min-width="150" show-overflow-tooltip />
            <el-table-column label="大小" width="80">
              <template #default="{ row }">
                {{ formatFileSize(row.file_size) }}
              </template>
            </el-table-column>
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
          <div style="color: #909399; font-size: 12px; margin-top: 10px" v-else>
            暂无附件
          </div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitting">
          {{ isEdit ? '保存' : '创建' }}
        </el-button>
      </template>
    </el-dialog>

    <!-- 查看对话框 -->
    <el-dialog
      v-model="viewDialogVisible"
      title="查看考勤依据"
      width="800px"
    >
      <el-descriptions :column="2" border v-if="currentRecord">
        <el-descriptions-item label="项目名称">{{ currentRecord.project?.name }}</el-descriptions-item>
        <el-descriptions-item label="月份">{{ currentRecord.month }}</el-descriptions-item>
        <el-descriptions-item label="创建人">{{ currentRecord.creator?.name }}</el-descriptions-item>
        <el-descriptions-item label="创建时间">{{ currentRecord.created_at }}</el-descriptions-item>
        <el-descriptions-item label="说明" :span="2">
          {{ currentRecord.description || '无' }}
        </el-descriptions-item>
      </el-descriptions>

      <el-divider content-position="left">附件管理</el-divider>

      <el-upload
        :http-request="customUpload"
        :data="{ basis_record_id: currentRecord?.id }"
        :on-success="handleUploadSuccess"
        :on-error="handleUploadError"
        :show-file-list="false"
        style="margin-bottom: 20px"
      >
        <el-button type="primary" :icon="Upload">上传附件</el-button>
      </el-upload>

      <el-table :data="currentRecord?.attachments" border>
        <el-table-column prop="file_name" label="文件名" min-width="200" show-overflow-tooltip />
        <el-table-column prop="file_type" label="类型" width="100">
          <template #default="{ row }">
            <el-tag :type="getFileTypeTag(row.file_type)">
              {{ getFileTypeText(row.file_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="大小" width="100">
          <template #default="{ row }">
            {{ formatFileSize(row.file_size) }}
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="上传时间" width="180">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="150">
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
  getBasisRecords,
  getAvailableProjects,
  createBasisRecord,
  copyLastMonthBasisRecord,
  updateBasisRecord,
  deleteBasisRecord,
  getBasisRecordDetail,
  deleteBasisAttachment,
  uploadBasisAttachment
} from '@/api/basisRecord'
import { useAccountSetStore } from '@/stores/accountSet'
import { useUserStore } from '@/stores/user'

const accountSetStore = useAccountSetStore()
const userStore = useUserStore()
// 动态获取当前服务器地址，自动适配环境
const apiBaseUrl = window.location.origin

const loading = ref(false)
const submitting = ref(false)
const dialogVisible = ref(false)
const viewDialogVisible = ref(false)
const isEdit = ref(false)

const records = ref([])
const availableProjects = ref([])
const currentRecord = ref(null)

// 获取当前月份（格式：YYYY-MM）
const getCurrentMonth = () => {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  return `${year}-${month}`
}

const searchForm = reactive({
  project_id: null,
  month: getCurrentMonth() // 默认为本月
})

const pagination = reactive({
  currentPage: 1,
  pageSize: 20,
  total: 0
})

const form = reactive({
  project_id: null,
  month: null,
  create_mode: 'upload',
  description: ''
})

const formRef = ref()

const formRules = {
  project_id: [
    { required: true, message: '请选择项目', trigger: 'change' }
  ],
  month: [
    { required: true, message: '请选择月份', trigger: 'change' }
  ],
  create_mode: [
    { required: true, message: '请选择创建方式', trigger: 'change' }
  ]
}

// 上传请求头（需要包含认证token）
const uploadHeaders = computed(() => ({
  'Authorization': `Bearer ${userStore.token}`,
  'X-Account-Set-Id': accountSetStore.currentAccountSetId
}))

// 自定义上传方法，使用封装的 API 确保 token 正确传递
const customUpload = async (options) => {
  const formData = new FormData()
  formData.append('file', options.file)
  formData.append('basis_record_id', currentRecord.value?.id)
  
  try {
    const result = await uploadBasisAttachment(formData)
    
    if (result.success) {
      options.onSuccess(result)
    } else {
      options.onError(new Error(result.message || '上传失败'))
    }
  } catch (error) {
    options.onError(error)
  }
}

// 加载依据列表
const loadRecords = async () => {
  loading.value = true
  try {
    const params = {
      type: 'attendance',
      page: pagination.currentPage,
      per_page: pagination.pageSize,
      ...searchForm
    }
    
    const response = await getBasisRecords(params)
    records.value = response.data
    pagination.total = response.total
  } catch (error) {
    console.error('Load records error:', error)
    ElMessage.error('加载数据失败')
  } finally {
    loading.value = false
  }
}

// 加载可用项目
const loadAvailableProjects = async () => {
  try {
    const response = await getAvailableProjects({ type: 'attendance' })
    availableProjects.value = response.data
  } catch (error) {
    console.error('Load projects error:', error)
    ElMessage.error('加载项目列表失败')
  }
}

// 查询
const handleSearch = () => {
  pagination.currentPage = 1
  loadRecords()
}

// 重置
const handleReset = () => {
  searchForm.project_id = null
  searchForm.month = null
  handleSearch()
}

// 创建
const handleCreate = () => {
  isEdit.value = false
  Object.assign(form, {
    project_id: null,
    month: null,
    create_mode: 'upload',
    description: ''
  })
  dialogVisible.value = true
}

// 编辑
const handleEdit = async (row) => {
  isEdit.value = true
  try {
    const response = await getBasisRecordDetail(row.id)
    currentRecord.value = response.data
    Object.assign(form, {
      id: row.id,
      project_id: row.project_id,
      month: row.month,
      description: row.description || ''
    })
    dialogVisible.value = true
  } catch (error) {
    console.error('Load detail error:', error)
    ElMessage.error('加载详情失败')
  }
}

// 提交
const handleSubmit = async () => {
  await formRef.value?.validate()
  
  submitting.value = true
  try {
    if (isEdit.value) {
      await updateBasisRecord(form.id, {
        description: form.description
      })
      ElMessage.success('更新成功')
      dialogVisible.value = false
      loadRecords()
    } else {
      if (form.create_mode === 'copy_last_month') {
        const response = await copyLastMonthBasisRecord({
          project_id: form.project_id,
          month: form.month,
          type: 'attendance',
          description: form.description
        })
        ElMessage.success(response.message || '复制上月成功')
        dialogVisible.value = false
        loadRecords()
        return
      }

      const response = await createBasisRecord({
        project_id: form.project_id,
        month: form.month,
        type: 'attendance',
        description: form.description
      })
      ElMessage.success('创建成功')
      dialogVisible.value = false
      loadRecords()

      if (form.create_mode === 'upload') {
        ElMessageBox.confirm(
          '依据已创建成功，是否立即上传附件？',
          '提示',
          {
            confirmButtonText: '上传附件',
            cancelButtonText: '稍后上传',
            type: 'success'
          }
        ).then(() => {
          handleEdit({ id: response.data.id })
        }).catch(() => {})
      }
    }
  } catch (error) {
    console.error('Submit error:', error)
    ElMessage.error(error.response?.data?.message || '操作失败')
  } finally {
    submitting.value = false
  }
}

// 查看
const handleView = async (row) => {
  try {
    const response = await getBasisRecordDetail(row.id)
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
    await ElMessageBox.confirm('确定要删除该依据吗？删除后将无法恢复，相关附件也会被删除。', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    
    await deleteBasisRecord(row.id)
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
const handleUploadSuccess = (response) => {
  if (response.success) {
    ElMessage.success('上传成功')
    // 重新加载详情
    getBasisRecordDetail(currentRecord.value.id).then(res => {
      currentRecord.value = res.data
    })
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
    
    await deleteBasisAttachment(attachment.id)
    ElMessage.success('删除成功')
    // 重新加载详情
    const response = await getBasisRecordDetail(currentRecord.value.id)
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
  const url = `${apiBaseUrl}/storage/${attachment.file_path}`
  window.open(url, '_blank')
}

// 文件类型标签
const getFileTypeTag = (type) => {
  const tags = {
    image: 'success',
    document: 'primary',
    other: 'info'
  }
  return tags[type] || 'info'
}

// 文件类型文本
const getFileTypeText = (type) => {
  const texts = {
    image: '图片',
    document: '文档',
    other: '其他'
  }
  return texts[type] || '其他'
}

// 格式化文件大小
const formatFileSize = (bytes) => {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(2) + ' MB'
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
  loadRecords()
  loadAvailableProjects()
})
</script>

<style scoped>
.attendance-basis {
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

.search-card {
  margin-bottom: 20px;
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 5px;
}
</style>

