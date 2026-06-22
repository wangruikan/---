<template>
  <div class="delivery-config-container">
    <!-- 头部操作区 -->
    <el-card class="header-card">
      <el-row :gutter="20">
        <el-col :span="24">
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
            <el-form-item label="交付周期">
              <el-select v-model="filterForm.delivery_cycle" placeholder="全部" clearable style="width: 150px;">
                <el-option label="按月交付" value="monthly" />
                <el-option label="按季度交付" value="quarterly" />
              </el-select>
            </el-form-item>
            <el-form-item label="状态">
              <el-select v-model="filterForm.is_active" placeholder="全部" clearable style="width: 120px;">
                <el-option label="启用" :value="true" />
                <el-option label="禁用" :value="false" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
              <el-button :icon="Refresh" @click="handleReset">重置</el-button>
            </el-form-item>
          </el-form>
        </el-col>
      </el-row>
    </el-card>

    <!-- 列表 -->
    <el-card class="table-card">
      <el-table :data="configList" v-loading="loading" border stripe>
        <el-table-column label="ID" width="70" align="center">
          <template #default="{ $index }">
            {{ (pagination.current - 1) * pagination.pageSize + $index + 1 }}
          </template>
        </el-table-column>
        <el-table-column label="项目名称" width="180">
          <template #default="{ row }">
            {{ row.project?.name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="交付周期" width="120">
          <template #default="{ row }">
            <span v-if="!row.config_exists" style="color: #999;">待配置</span>
            <el-tag v-else :type="row.delivery_cycle === 'monthly' ? 'primary' : 'success'">
              {{ row.delivery_cycle === 'monthly' ? '按月交付' : '按季度交付' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="交付方式" width="120">
          <template #default="{ row }">
            <span v-if="!row.config_exists" style="color: #999;">待配置</span>
            <el-tag v-else :type="row.delivery_method === 'express' ? 'warning' : 'info'">
              {{ row.delivery_method === 'express' ? '快递交付' : '电子推送' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="任务月份" width="120">
          <template #default="{ row }">
            <span v-if="!row.config_exists" style="color: #999;">待配置</span>
            <el-tag v-else :type="row.delivery_release_month === 'next' ? 'warning' : 'success'">
              {{ row.delivery_release_month === 'next' ? '次月' : '当月' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="资料清单" min-width="200">
          <template #default="{ row }">
            <div v-if="row.config_exists && row.required_documents && row.required_documents.length > 0">
              <el-tag 
                v-for="(doc, index) in row.required_documents.slice(0, 3)" 
                :key="index"
                size="small"
                style="margin-right: 5px; margin-bottom: 3px;"
              >
                {{ doc }}
              </el-tag>
              <span v-if="row.required_documents.length > 3">...</span>
            </div>
            <span v-else-if="!row.config_exists" style="color: #E6A23C;">待配置</span>
            <span v-else style="color: #999;">未设置</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="80" align="center">
          <template #default="{ row }">
            <el-tag v-if="!row.config_exists" type="warning">
              待配置
            </el-tag>
            <el-tag v-else :type="row.is_active ? 'success' : 'info'">
              {{ row.is_active ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="创建人" width="100">
          <template #default="{ row }">
            {{ row.creator?.name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="创建时间" width="160">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button
              v-if="!row.config_exists"
              link
              type="primary"
              :icon="Edit"
              @click="handleCreate(row)"
            >
              去配置
            </el-button>
            <template v-else>
              <el-button link type="primary" :icon="Edit" @click="handleEdit(row)">编辑</el-button>
              <el-button 
                link 
                :type="row.is_active ? 'warning' : 'success'" 
                @click="handleToggleStatus(row)"
              >
                {{ row.is_active ? '禁用' : '启用' }}
              </el-button>
              <el-button link type="danger" :icon="Delete" @click="handleDelete(row)">删除</el-button>
            </template>
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
            @current-change="handlePageChange"
            @size-change="handlePageChange"
          />
        </div>
      </el-card>

    <!-- 新增/编辑对话框 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="600px"
      :close-on-click-modal="false"
    >
      <el-form
        ref="formRef"
        :model="formData"
        :rules="formRules"
        label-width="110px"
      >
        <el-form-item label="项目" prop="project_id">
          <el-select 
            v-model="formData.project_id" 
            placeholder="请选择项目"
            :disabled="true"
            style="width: 100%;"
          >
            <el-option
              v-for="project in projectList"
              :key="project.id"
              :label="project.name"
              :value="project.id"
            />
          </el-select>
          <div style="color: #999; font-size: 12px; margin-top: 5px;">
            每个项目只能配置一次，编辑时无法更改项目
          </div>
        </el-form-item>

        <el-form-item label="交付周期" prop="delivery_cycle">
          <el-radio-group v-model="formData.delivery_cycle">
            <el-radio value="monthly">按月交付</el-radio>
            <el-radio value="quarterly">按季度交付</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="交付方式" prop="delivery_method">
          <el-radio-group v-model="formData.delivery_method">
            <el-radio value="express">快递交付</el-radio>
            <el-radio value="electronic">电子推送</el-radio>
          </el-radio-group>
          <div style="color: #999; font-size: 12px; margin-top: 5px;">
            快递：需上传快递单号；电子：需上传电子资料
          </div>
        </el-form-item>

        <el-form-item label="任务月份" prop="delivery_release_month">
          <el-radio-group v-model="formData.delivery_release_month">
            <el-radio value="current">当月</el-radio>
            <el-radio value="next">次月</el-radio>
          </el-radio-group>
          <div style="color: #999; font-size: 12px; margin-top: 5px;">
            次月：本月资料交付任务延后到下个月出现
          </div>
        </el-form-item>

        <el-form-item label="资料清单" prop="required_documents">
          <div style="width: 100%;">
            <el-tag
              v-for="(doc, index) in formData.required_documents"
              :key="index"
              closable
              @close="removeDocument(index)"
              style="margin-right: 10px; margin-bottom: 5px;"
            >
              {{ doc }}
            </el-tag>
            <el-input
              v-if="showDocInput"
              v-model="newDocument"
              size="small"
              style="width: 150px;"
              @keyup.enter="addDocument"
              @blur="addDocument"
              placeholder="输入后回车"
            />
            <el-button
              v-else
              size="small"
              @click="showDocInput = true"
            >
              + 添加资料
            </el-button>
          </div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Refresh, Edit, Delete } from '@element-plus/icons-vue'
import { useAccountSetStore } from '@/stores/accountSet'
import { getProjects } from '@/api/projects'
import {
  getDeliveryConfigs,
  createDeliveryConfig,
  updateDeliveryConfig,
  deleteDeliveryConfig,
  toggleConfigStatus
} from '@/api/documentDelivery'

const accountSetStore = useAccountSetStore()

// 项目列表
const projectList = ref([])
const rawConfigList = ref([])

// 筛选表单
const filterForm = reactive({
  project_id: null,
  delivery_cycle: '',
  is_active: null
})

// 列表数据
const loading = ref(false)
const configList = ref([])
const pagination = reactive({
  current: 1,
  pageSize: 15,
  total: 0
})

// 对话框
const dialogVisible = ref(false)
const dialogTitle = ref('')
const formRef = ref(null)
const submitting = ref(false)
const editingId = ref(null)

// 表单数据
const formData = reactive({
  project_id: null,
  delivery_cycle: 'monthly',
  delivery_method: 'electronic',
  delivery_release_month: 'current',
  required_documents: []
})

// 资料清单输入
const showDocInput = ref(false)
const newDocument = ref('')

// 表单验证规则
const formRules = {
  project_id: [
    { required: true, message: '请选择项目', trigger: 'change' }
  ],
  delivery_cycle: [
    { required: true, message: '请选择交付周期', trigger: 'change' }
  ],
  delivery_method: [
    { required: true, message: '请选择交付方式', trigger: 'change' }
  ],
  delivery_release_month: [
    { required: true, message: '请选择任务月份', trigger: 'change' }
  ]
}

// 添加资料
const addDocument = () => {
  if (newDocument.value && newDocument.value.trim()) {
    formData.required_documents.push(newDocument.value.trim())
    newDocument.value = ''
  }
  showDocInput.value = false
}

// 移除资料
const removeDocument = (index) => {
  formData.required_documents.splice(index, 1)
}

// 格式化时间
const formatDateTime = (datetime) => {
  if (!datetime) return '-'
  return new Date(datetime).toLocaleString('zh-CN')
}

const buildMergedConfigRows = () => {
  const configMap = new Map(rawConfigList.value.map(config => [config.project_id, config]))

  let rows = projectList.value.map(project => {
    const config = configMap.get(project.id)

    if (config && config.config_exists !== false) {
      return {
        ...config,
        config_exists: true
      }
    }

    return {
      id: `project-${project.id}`,
      project_id: project.id,
      project,
      delivery_cycle: '',
      delivery_method: '',
      required_documents: [],
      is_active: null,
      creator: null,
      created_at: null,
      config_exists: false
    }
  })

  if (filterForm.project_id) {
    rows = rows.filter(row => row.project_id === filterForm.project_id)
  }

  if (filterForm.delivery_cycle) {
    rows = rows.filter(row => row.delivery_cycle === filterForm.delivery_cycle)
  }

  if (filterForm.is_active !== null) {
    rows = rows.filter(row => row.config_exists && row.is_active === filterForm.is_active)
  }

  rows.sort((a, b) => {
    if (a.config_exists !== b.config_exists) {
      return a.config_exists ? 1 : -1
    }

    return (a.project?.name || '').localeCompare(b.project?.name || '', 'zh-CN')
  })

  pagination.total = rows.length

  const start = (pagination.current - 1) * pagination.pageSize
  configList.value = rows.slice(start, start + pagination.pageSize)
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
      buildMergedConfigRows()
    }
  } catch (error) {
    console.error('Load projects error:', error)
    projectList.value = []
    buildMergedConfigRows()
  }
}

// 加载配置列表
const loadConfigList = async () => {
  loading.value = true
  try {
    const res = await getDeliveryConfigs({
      current_account_set_id: accountSetStore.currentAccountSetId,
      per_page: 1000
    })
    
    if (res.success) {
      rawConfigList.value = Array.isArray(res.data?.data)
        ? res.data.data.filter(item => item?.config_exists !== false)
        : []
      buildMergedConfigRows()
    } else {
      ElMessage.error(res.message || '获取列表失败')
    }
  } catch (error) {
    console.error('Load config list error:', error)
    ElMessage.error('获取列表失败')
  } finally {
    loading.value = false
  }
}

// 查询
const handleSearch = () => {
  pagination.current = 1
  buildMergedConfigRows()
}

// 重置
const handleReset = () => {
  filterForm.project_id = null
  filterForm.delivery_cycle = ''
  filterForm.is_active = null
  pagination.current = 1
  buildMergedConfigRows()
}

// 新增
const handleCreate = (row) => {
  dialogTitle.value = '配置交付要求'
  editingId.value = null
  resetForm()
  formData.project_id = row.project_id
  dialogVisible.value = true
}

// 编辑
const handleEdit = (row) => {
  dialogTitle.value = '编辑交付配置'
  editingId.value = row.id
  resetForm()
  Object.assign(formData, {
    project_id: row.project_id,
    delivery_cycle: row.delivery_cycle,
    delivery_method: row.delivery_method,
    delivery_release_month: row.delivery_release_month || 'current',
    required_documents: row.required_documents || []
  })
  dialogVisible.value = true
}

// 提交
const handleSubmit = async () => {
  if (!formRef.value) return
  
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    
    submitting.value = true
    try {
      const data = {
        ...formData,
        current_account_set_id: accountSetStore.currentAccountSetId
      }
      
      let res
      if (editingId.value) {
        res = await updateDeliveryConfig(editingId.value, data)
      } else {
        res = await createDeliveryConfig(data)
      }
      
      if (res.success) {
        ElMessage.success(res.message || '操作成功')
        dialogVisible.value = false
        await loadConfigList()
      } else {
        ElMessage.error(res.message || '操作失败')
      }
    } catch (error) {
      console.error('Submit error:', error)
      ElMessage.error('操作失败')
    } finally {
      submitting.value = false
    }
  })
}

// 切换状态
const handleToggleStatus = async (row) => {
  try {
    const res = await toggleConfigStatus(row.id)
    if (res.success) {
      ElMessage.success(res.message)
      await loadConfigList()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (error) {
    console.error('Toggle status error:', error)
    ElMessage.error('操作失败')
  }
}

// 删除
const handleDelete = (row) => {
  ElMessageBox.confirm(
    `确定要删除项目"${row.project?.name}"的交付配置吗？`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    }
  ).then(async () => {
    try {
      const res = await deleteDeliveryConfig(row.id)
      if (res.success) {
        ElMessage.success('删除成功')
        await loadConfigList()
      } else {
        ElMessage.error(res.message || '删除失败')
      }
    } catch (error) {
      console.error('Delete error:', error)
      ElMessage.error('删除失败')
    }
  }).catch(() => {})
}

// 重置表单
const resetForm = () => {
  if (formRef.value) {
    formRef.value.resetFields()
  }
  Object.assign(formData, {
    project_id: null,
    delivery_cycle: 'monthly',
    delivery_method: 'electronic',
    delivery_release_month: 'current',
    required_documents: []
  })
  showDocInput.value = false
  newDocument.value = ''
}

const handlePageChange = () => {
  buildMergedConfigRows()
}

// 初始化
onMounted(() => {
  loadProjects()
  loadConfigList()
})
</script>

<style scoped>
.delivery-config-container {
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

