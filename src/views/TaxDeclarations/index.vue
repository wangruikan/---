<template>
  <div class="tax-declarations">
    <div class="page-header">
      <h1>税费申报管理</h1>
    </div>

    <el-card>
      <el-tabs v-model="activeTab">
        <!-- Tab1: 税种类目管理 -->
        <el-tab-pane label="税种类目" name="categories">
          <div class="tab-header">
            <el-button type="primary" @click="handleCreateCategory">添加税种</el-button>
          </div>

          <el-table :data="categories" v-loading="categoriesLoading" border stripe>
            <el-table-column prop="id" label="ID" width="80" />
            <el-table-column prop="name" label="税种名称" min-width="200" />
            <el-table-column prop="period_type" label="申报周期" width="120">
              <template #default="{ row }">
                <el-tag :type="getPeriodTypeTag(row.period_type)">
                  {{ getPeriodTypeText(row.period_type) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="creator.name" label="创建人" width="120" />
            <el-table-column prop="created_at" label="创建时间" width="180" />
            <el-table-column label="操作" width="150" fixed="right">
              <template #default="{ row }">
                <el-button type="primary" size="small" link @click="handleEditCategory(row)">
                  编辑
                </el-button>
                <el-button type="danger" size="small" link @click="handleDeleteCategory(row)">
                  删除
                </el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- Tab2: 申报配置管理 -->
        <el-tab-pane label="申报配置" name="configs">
          <div class="tab-header">
            <el-button type="primary" @click="handleCreateConfig">创建配置</el-button>
          </div>

          <el-table :data="configs" v-loading="configsLoading" border stripe>
            <el-table-column prop="id" label="ID" width="80" />
            <el-table-column prop="company_name" label="公司名称" min-width="200" />
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
            <el-table-column prop="declaration_date" label="申报日期" width="120" />
            <el-table-column prop="creator.name" label="创建人" width="120" />
            <el-table-column prop="created_at" label="创建时间" width="180" />
            <el-table-column label="操作" width="150" fixed="right">
              <template #default="{ row }">
                <el-button type="primary" size="small" link @click="handleEditConfig(row)">
                  编辑
                </el-button>
                <el-button type="danger" size="small" link @click="handleDeleteConfig(row)">
                  删除
                </el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- Tab3: 申报任务 -->
        <el-tab-pane label="申报任务" name="tasks">
          <div class="tab-header">
            <el-form :model="taskSearchForm" inline>
              <el-form-item label="状态">
                <el-select v-model="taskSearchForm.status" placeholder="全部" clearable style="width: 120px">
                  <el-option label="待处理" value="pending" />
                  <el-option label="已完成" value="completed" />
                </el-select>
              </el-form-item>
              <el-form-item label="年份">
                <el-date-picker
                  v-model="taskSearchForm.year"
                  type="year"
                  placeholder="选择年份"
                  value-format="YYYY"
                  clearable
                  style="width: 150px"
                />
              </el-form-item>
              <el-form-item>
                <el-button type="primary" @click="loadTasks">查询</el-button>
                <el-button @click="handleResetTaskSearch">重置</el-button>
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
            <el-table-column prop="declaration_date" label="申报日期" width="120" />
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
            v-model:current-page="taskPagination.currentPage"
            v-model:page-size="taskPagination.pageSize"
            :total="taskPagination.total"
            :page-sizes="[10, 20, 50, 100]"
            layout="total, sizes, prev, pager, next, jumper"
            @size-change="loadTasks"
            @current-change="loadTasks"
            style="margin-top: 20px; justify-content: flex-end"
          />
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 税种类目对话框 -->
    <el-dialog
      v-model="categoryDialogVisible"
      :title="categoryDialogMode === 'create' ? '添加税种' : '编辑税种'"
      width="500px"
    >
      <el-form :model="categoryForm" :rules="categoryRules" ref="categoryFormRef" label-width="100px">
        <el-form-item label="税种名称" prop="name">
          <el-input v-model="categoryForm.name" placeholder="请输入税种名称" />
        </el-form-item>
        <el-form-item label="申报周期" prop="period_type">
          <el-select v-model="categoryForm.period_type" placeholder="请选择申报周期" style="width: 100%">
            <el-option label="月度" value="monthly" />
            <el-option label="季度" value="quarterly" />
            <el-option label="年度" value="yearly" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="categoryDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmitCategory" :loading="categorySubmitting">
          确定
        </el-button>
      </template>
    </el-dialog>

    <!-- 申报配置对话框 -->
    <el-dialog
      v-model="configDialogVisible"
      :title="configDialogMode === 'create' ? '创建配置' : '编辑配置'"
      width="600px"
    >
      <el-form :model="configForm" :rules="configRules" ref="configFormRef" label-width="100px">
        <el-form-item label="公司名称" prop="company_name">
          <el-input v-model="configForm.company_name" placeholder="请输入公司名称" />
        </el-form-item>
        <el-form-item label="选择税种" prop="tax_category_ids">
          <el-select
            v-model="configForm.tax_category_ids"
            multiple
            placeholder="请选择税种（可多选）"
            style="width: 100%"
          >
            <el-option
              v-for="category in categories"
              :key="category.id"
              :label="`${category.name} (${getPeriodTypeText(category.period_type)})`"
              :value="category.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="申报日期" prop="declaration_date">
          <el-input
            v-model="configForm.declaration_date"
            placeholder="格式：MM-DD，如：01-15"
            maxlength="5"
          />
          <div style="color: #909399; font-size: 12px; margin-top: 5px">
            格式：MM-DD，例如：01-15 表示每年1月15日
          </div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="configDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmitConfig" :loading="configSubmitting">
          确定
        </el-button>
      </template>
    </el-dialog>

    <!-- 任务详情对话框 -->
    <el-dialog v-model="taskDetailDialogVisible" title="任务详情" width="800px">
      <el-descriptions :column="2" border v-if="currentTask">
        <el-descriptions-item label="ID">{{ currentTask.id }}</el-descriptions-item>
        <el-descriptions-item label="公司名称">{{ currentTask.company_name }}</el-descriptions-item>
        <el-descriptions-item label="操作员">{{ currentTask.handler_name }}</el-descriptions-item>
        <el-descriptions-item label="申报日期">{{ currentTask.declaration_date }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="currentTask.status === 'completed' ? 'success' : 'warning'">
            {{ currentTask.status === 'completed' ? '已完成' : '待处理' }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="完成时间">
          {{ currentTask.completed_at || '-' }}
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
            <el-button type="primary" size="small" link @click="handlePreviewAttachment(row)">
              预览
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
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Upload } from '@element-plus/icons-vue'
import {
  getCategories,
  createCategory,
  updateCategory,
  deleteCategory,
  getConfigs,
  createConfig,
  updateConfig,
  deleteConfig,
  getTasks,
  getTaskDetail,
  completeTask,
  uploadAttachment,
  deleteAttachment
} from '@/api/taxDeclaration'
import { useAccountSetStore } from '@/stores/accountSet'

const accountSetStore = useAccountSetStore()
const apiBaseUrl = window.location.origin

const activeTab = ref('categories')

// 税种类目相关
const categories = ref([])
const categoriesLoading = ref(false)
const categoryDialogVisible = ref(false)
const categoryDialogMode = ref('create')
const categorySubmitting = ref(false)
const categoryFormRef = ref()

const categoryForm = reactive({
  name: '',
  period_type: ''
})

const categoryRules = {
  name: [{ required: true, message: '请输入税种名称', trigger: 'blur' }],
  period_type: [{ required: true, message: '请选择申报周期', trigger: 'change' }]
}

// 申报配置相关
const configs = ref([])
const configsLoading = ref(false)
const configDialogVisible = ref(false)
const configDialogMode = ref('create')
const configSubmitting = ref(false)
const configFormRef = ref()

const configForm = reactive({
  company_name: '',
  tax_category_ids: [],
  declaration_date: ''
})

const configRules = {
  company_name: [{ required: true, message: '请输入公司名称', trigger: 'blur' }],
  tax_category_ids: [{ required: true, message: '请选择税种', trigger: 'change' }],
  declaration_date: [
    { required: true, message: '请输入申报日期', trigger: 'blur' },
    { pattern: /^\d{2}-\d{2}$/, message: '格式错误，应为 MM-DD', trigger: 'blur' }
  ]
}

// 申报任务相关
const tasks = ref([])
const tasksLoading = ref(false)
const taskSearchForm = reactive({
  status: '',
  year: new Date().getFullYear().toString()
})

const taskPagination = reactive({
  currentPage: 1,
  pageSize: 20,
  total: 0
})

const taskDetailDialogVisible = ref(false)
const currentTask = ref(null)

// 加载税种类目
const loadCategories = async () => {
  categoriesLoading.value = true
  try {
    const response = await getCategories({
      account_set_id: accountSetStore.currentAccountSetId
    })
    categories.value = response.data
  } catch (error) {
    console.error('加载税种类目失败:', error)
    ElMessage.error('加载失败')
  } finally {
    categoriesLoading.value = false
  }
}

// 创建税种类目
const handleCreateCategory = () => {
  categoryDialogMode.value = 'create'
  Object.assign(categoryForm, {
    name: '',
    period_type: ''
  })
  categoryDialogVisible.value = true
}

// 编辑税种类目
const handleEditCategory = (row) => {
  categoryDialogMode.value = 'edit'
  Object.assign(categoryForm, {
    id: row.id,
    name: row.name,
    period_type: row.period_type
  })
  categoryDialogVisible.value = true
}

// 提交税种类目
const handleSubmitCategory = async () => {
  await categoryFormRef.value?.validate()
  
  categorySubmitting.value = true
  try {
    const data = {
      account_set_id: accountSetStore.currentAccountSetId,
      name: categoryForm.name,
      period_type: categoryForm.period_type
    }
    
    if (categoryDialogMode.value === 'create') {
      await createCategory(data)
      ElMessage.success('创建成功')
    } else {
      await updateCategory(categoryForm.id, data)
      ElMessage.success('更新成功')
    }
    
    categoryDialogVisible.value = false
    loadCategories()
  } catch (error) {
    console.error('提交失败:', error)
    ElMessage.error(error.response?.data?.message || '操作失败')
  } finally {
    categorySubmitting.value = false
  }
}

// 删除税种类目
const handleDeleteCategory = async (row) => {
  try {
    await ElMessageBox.confirm(`确定要删除税种"${row.name}"吗？`, '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    
    await deleteCategory(row.id)
    ElMessage.success('删除成功')
    loadCategories()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }
}

// 加载申报配置
const loadConfigs = async () => {
  configsLoading.value = true
  try {
    const response = await getConfigs({
      account_set_id: accountSetStore.currentAccountSetId
    })
    configs.value = response.data
  } catch (error) {
    console.error('加载配置失败:', error)
    ElMessage.error('加载失败')
  } finally {
    configsLoading.value = false
  }
}

// 创建配置
const handleCreateConfig = () => {
  configDialogMode.value = 'create'
  Object.assign(configForm, {
    company_name: '',
    tax_category_ids: [],
    declaration_date: ''
  })
  configDialogVisible.value = true
}

// 编辑配置
const handleEditConfig = (row) => {
  configDialogMode.value = 'edit'
  Object.assign(configForm, {
    id: row.id,
    company_name: row.company_name,
    tax_category_ids: row.tax_category_ids,
    declaration_date: row.declaration_date
  })
  configDialogVisible.value = true
}

// 提交配置
const handleSubmitConfig = async () => {
  await configFormRef.value?.validate()
  
  configSubmitting.value = true
  try {
    const data = {
      account_set_id: accountSetStore.currentAccountSetId,
      company_name: configForm.company_name,
      tax_category_ids: configForm.tax_category_ids,
      declaration_date: configForm.declaration_date
    }
    
    if (configDialogMode.value === 'create') {
      await createConfig(data)
      ElMessage.success('创建成功')
    } else {
      await updateConfig(configForm.id, data)
      ElMessage.success('更新成功')
    }
    
    configDialogVisible.value = false
    loadConfigs()
  } catch (error) {
    console.error('提交失败:', error)
    ElMessage.error(error.response?.data?.message || '操作失败')
  } finally {
    configSubmitting.value = false
  }
}

// 删除配置
const handleDeleteConfig = async (row) => {
  try {
    await ElMessageBox.confirm(`确定要删除配置"${row.company_name}"吗？`, '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    
    await deleteConfig(row.id)
    ElMessage.success('删除成功')
    loadConfigs()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }
}

// 加载申报任务
const loadTasks = async () => {
  tasksLoading.value = true
  try {
    const response = await getTasks({
      account_set_id: accountSetStore.currentAccountSetId,
      status: taskSearchForm.status,
      year: taskSearchForm.year,
      page: taskPagination.currentPage,
      per_page: taskPagination.pageSize
    })
    tasks.value = response.data
    taskPagination.total = response.total
  } catch (error) {
    console.error('加载任务失败:', error)
    ElMessage.error('加载失败')
  } finally {
    tasksLoading.value = false
  }
}

// 重置任务搜索
const handleResetTaskSearch = () => {
  taskSearchForm.status = ''
  taskSearchForm.year = new Date().getFullYear().toString()
  taskPagination.currentPage = 1
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
  formData.append('task_id', currentTask.value.id)
  
  try {
    const result = await uploadAttachment(formData)
    
    if (result.success) {
      ElMessage.success('上传成功')
      // 重新加载详情
      const response = await getTaskDetail(currentTask.value.id)
      currentTask.value = response.data
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

// 预览附件
const handlePreviewAttachment = (attachment) => {
  const url = `${apiBaseUrl}/${attachment.file_path}`
  window.open(url, '_blank')
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
const getPeriodTypeText = (type) => {
  const types = {
    monthly: '月度',
    quarterly: '季度',
    yearly: '年度'
  }
  return types[type] || type
}

const getPeriodTypeTag = (type) => {
  const tags = {
    monthly: 'primary',
    quarterly: 'success',
    yearly: 'warning'
  }
  return tags[type] || 'info'
}

const formatFileSize = (bytes) => {
  if (!bytes) return '-'
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(2) + ' MB'
}

onMounted(() => {
  loadCategories()
  loadConfigs()
  loadTasks()
})
</script>

<style scoped>
.tax-declarations {
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

.tab-header {
  margin-bottom: 20px;
}
</style>
