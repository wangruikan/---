<template>
  <div class="account-sets-page">
    <div class="page-header">
      <h1>套账管理</h1>
      <div class="header-actions">
        <el-button type="primary" @click="showCreateDialog = true">
          <el-icon><Plus /></el-icon>
          新建套账
        </el-button>
      </div>
    </div>

    <!-- 搜索和筛选 -->
    <div class="search-section">
      <el-card>
        <el-form :model="searchForm" inline>
          <el-form-item label="搜索">
            <el-input
              v-model="searchForm.search"
              placeholder="套账名称/代码/公司名"
              clearable
              style="width: 250px"
              @keyup.enter="handleSearch"
            />
          </el-form-item>
          
          <el-form-item label="状态">
            <el-select
              v-model="searchForm.status"
              placeholder="请选择状态"
              clearable
              style="width: 150px"
            >
              <el-option label="启用" value="active" />
              <el-option label="停用" value="inactive" />
              <el-option label="已归档" value="archived" />
            </el-select>
          </el-form-item>
          
          <el-form-item>
            <el-button type="primary" @click="handleSearch">
              <el-icon><Search /></el-icon>
              搜索
            </el-button>
            <el-button @click="handleReset">
              <el-icon><Refresh /></el-icon>
              重置
            </el-button>
          </el-form-item>
        </el-form>
      </el-card>
    </div>

    <!-- 统计卡片 -->
    <div class="statistics-section">
      <el-row :gutter="20">
        <el-col :span="6">
          <el-card>
            <div class="stat-item">
              <div class="stat-label">总套账数</div>
              <div class="stat-value">{{ statistics.total }}</div>
            </div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card>
            <div class="stat-item">
              <div class="stat-label">启用中</div>
              <div class="stat-value active">{{ statistics.active }}</div>
            </div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card>
            <div class="stat-item">
              <div class="stat-label">已停用</div>
              <div class="stat-value inactive">{{ statistics.inactive }}</div>
            </div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card>
            <div class="stat-item">
              <div class="stat-label">已归档</div>
              <div class="stat-value archived">{{ statistics.archived }}</div>
            </div>
          </el-card>
        </el-col>
      </el-row>
    </div>

    <!-- 套账列表 -->
    <div class="table-section">
      <el-card>
        <el-table
          :data="accountSets"
          v-loading="loading"
          stripe
          border
        >
          <el-table-column prop="code" label="套账代码" width="150">
            <template #default="{ row }">
              <el-tag v-if="row.is_default" type="danger" size="small">默认</el-tag>
              {{ row.code }}
            </template>
          </el-table-column>
          <el-table-column prop="name" label="套账名称" min-width="150" />
          <el-table-column prop="company_name" label="公司名称" min-width="150" />
          <el-table-column prop="contact_person" label="联系人" width="100" />
          <el-table-column prop="contact_phone" label="联系电话" width="120" />
          <el-table-column prop="status" label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="getStatusType(row.status)">
                {{ getStatusText(row.status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="enabled_date" label="启用日期" width="120">
            <template #default="{ row }">
              {{ formatDate(row.enabled_date) }}
            </template>
          </el-table-column>
          <el-table-column prop="creator.name" label="创建人" width="100" />
          <el-table-column prop="created_at" label="创建时间" width="180">
            <template #default="{ row }">
              {{ formatDateTime(row.created_at) }}
            </template>
          </el-table-column>
          <el-table-column label="操作" width="350" fixed="right">
            <template #default="{ row }">
              <el-button size="small" @click="handleView(row)">查看</el-button>
              <el-button size="small" type="primary" @click="handleEdit(row)">编辑</el-button>
              <el-button
                v-if="!row.is_default"
                size="small"
                type="success"
                @click="handleSetDefault(row)"
              >设为默认</el-button>
              <el-button
                v-if="!row.is_default && row.status === 'active'"
                size="small"
                type="warning"
                @click="handleArchive(row)"
              >归档</el-button>
              <el-button
                v-if="!row.is_default"
                size="small"
                type="danger"
                @click="handleDelete(row)"
              >删除</el-button>
            </template>
          </el-table-column>
        </el-table>

        <div class="pagination">
          <el-pagination
            v-model:current-page="pagination.currentPage"
            v-model:page-size="pagination.pageSize"
            :page-sizes="[10, 20, 50, 100]"
            :total="pagination.total"
            layout="total, sizes, prev, pager, next, jumper"
            @size-change="handleSizeChange"
            @current-change="handleCurrentChange"
          />
        </div>
      </el-card>
    </div>

    <!-- 创建/编辑对话框 -->
    <el-dialog
      v-model="showCreateDialog"
      :title="isEdit ? (isViewMode ? '查看账套' : '编辑账套') : '新建账套'"
      width="600px"
      @close="resetForm"
    >
      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        label-width="100px"
        :disabled="isViewMode"
      >
        <el-form-item label="套账代码">
          <el-input v-model="form.code" :disabled="true" :placeholder="isEdit ? '' : '系统自动生成'" />
        </el-form-item>

        <el-form-item label="套账名称" prop="name">
          <el-input v-model="form.name" placeholder="请输入套账名称" />
        </el-form-item>

        <el-form-item label="公司名称" prop="company_name">
          <el-input v-model="form.company_name" placeholder="请输入公司名称" />
        </el-form-item>

        <el-form-item label="税号">
          <el-input v-model="form.tax_number" placeholder="请输入税号" />
        </el-form-item>

        <el-form-item label="联系人">
          <el-input v-model="form.contact_person" placeholder="请输入联系人" />
        </el-form-item>

        <el-form-item label="联系电话">
          <el-input v-model="form.contact_phone" placeholder="请输入联系电话" />
        </el-form-item>

        <el-form-item label="地址">
          <el-input
            v-model="form.address"
            type="textarea"
            :rows="2"
            placeholder="请输入地址"
          />
        </el-form-item>

        <el-form-item label="启用日期">
          <el-date-picker
            v-model="form.enabled_date"
            type="date"
            placeholder="请选择启用日期"
            value-format="YYYY-MM-DD"
            style="width: 100%"
          />
        </el-form-item>

        <el-form-item label="描述">
          <el-input
            v-model="form.description"
            type="textarea"
            :rows="3"
            placeholder="请输入描述"
          />
        </el-form-item>

        <el-form-item label="设为默认" v-if="!isViewMode">
          <el-switch v-model="form.is_default" />
          <span class="form-tip">（默认套账在系统中优先显示）</span>
        </el-form-item>

        <el-form-item label="状态" v-if="isEdit">
          <el-radio-group v-model="form.status">
            <el-radio label="active">启用</el-radio>
            <el-radio label="inactive">停用</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="基数调整月份">
          <el-checkbox-group v-model="form.base_adjustment_months">
            <el-checkbox :label="1">1月</el-checkbox>
            <el-checkbox :label="2">2月</el-checkbox>
            <el-checkbox :label="3">3月</el-checkbox>
            <el-checkbox :label="4">4月</el-checkbox>
            <el-checkbox :label="5">5月</el-checkbox>
            <el-checkbox :label="6">6月</el-checkbox>
            <el-checkbox :label="7">7月</el-checkbox>
            <el-checkbox :label="8">8月</el-checkbox>
            <el-checkbox :label="9">9月</el-checkbox>
            <el-checkbox :label="10">10月</el-checkbox>
            <el-checkbox :label="11">11月</el-checkbox>
            <el-checkbox :label="12">12月</el-checkbox>
          </el-checkbox-group>
          <div class="form-tip">选择允许调整基数的月份，未选择的月份将无法修改基数</div>
        </el-form-item>
      </el-form>

      <template #footer v-if="!isViewMode">
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitting">
          {{ isEdit ? '保存' : '创建' }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getAccountSets,
  createAccountSet,
  updateAccountSet,
  deleteAccountSet,
  setDefaultAccountSet,
  archiveAccountSet,
  getAccountSetStatistics
} from '@/api/accountSets'
import { useUserStore } from '@/stores/user'
import { useRouter, useRoute } from 'vue-router'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()

// 权限检查
const isAdmin = computed(() => userStore.userInfo?.role === 'admin')

// 如果不是管理员，跳转到首页
onMounted(() => {
  if (!isAdmin.value) {
    ElMessage.error('只有管理员可以访问套账管理')
    router.push('/')
    return
  }
  loadAccountSets()
  loadStatistics()

  handleCreateFromRouteQuery()
})

const loading = ref(false)
const submitting = ref(false)
const showCreateDialog = ref(false)
const isEdit = ref(false)
const isViewMode = ref(false)
const formRef = ref()

const accountSets = ref([])

const statistics = ref({
  total: 0,
  active: 0,
  inactive: 0,
  archived: 0
})

const searchForm = reactive({
  search: '',
  status: ''
})

const pagination = reactive({
  currentPage: 1,
  pageSize: 20,
  total: 0
})

const form = reactive({
  id: null,
  name: '',
  code: '',
  description: '',
  company_name: '',
  tax_number: '',
  contact_person: '',
  contact_phone: '',
  address: '',
  enabled_date: '',
  status: 'active',
  is_default: false,
  base_adjustment_months: []
})

const rules = {
  name: [
    { required: true, message: '请输入套账名称', trigger: 'blur' }
  ]
}

const normalizeAdjustmentMonths = (value) => {
  let months = []

  if (Array.isArray(value)) {
    months = value
  } else if (typeof value === 'string') {
    const trimmed = value.trim()
    if (!trimmed) return []

    try {
      const parsed = JSON.parse(trimmed)
      months = Array.isArray(parsed) ? parsed : []
    } catch (error) {
      months = trimmed.split(',')
    }
  } else if (typeof value === 'number') {
    months = [value]
  }

  return [...new Set(
    months
      .map((item) => Number(item))
      .filter((item) => Number.isInteger(item) && item >= 1 && item <= 12)
  )]
}

const loadAccountSets = async () => {
  loading.value = true
  try {
    const params = {
      page: pagination.currentPage,
      per_page: pagination.pageSize,
      ...searchForm
    }
    
    const response = await getAccountSets(params)
    
    if (response.success) {
      accountSets.value = response.data || []
      pagination.total = response.total || 0
    }
  } catch (error) {
    console.error('Load account sets error:', error)
    ElMessage.error('加载套账列表失败')
  } finally {
    loading.value = false
  }
}

const loadStatistics = async () => {
  try {
    const response = await getAccountSetStatistics()
    if (response.success) {
      statistics.value = response.data
    }
  } catch (error) {
    console.error('Load statistics error:', error)
  }
}

const handleSearch = () => {
  pagination.currentPage = 1
  loadAccountSets()
}

const handleReset = () => {
  Object.assign(searchForm, {
    search: '',
    status: ''
  })
  handleSearch()
}

const handleSizeChange = (size) => {
  pagination.pageSize = size
  loadAccountSets()
}

const handleCurrentChange = (page) => {
  pagination.currentPage = page
  loadAccountSets()
}

const handleView = (row) => {
  isViewMode.value = true
  isEdit.value = false
  Object.assign(form, { ...row })
  form.base_adjustment_months = normalizeAdjustmentMonths(row.base_adjustment_months)
  showCreateDialog.value = true
}

const handleEdit = (row) => {
  isViewMode.value = false
  isEdit.value = true
  Object.assign(form, { ...row })
  form.base_adjustment_months = normalizeAdjustmentMonths(row.base_adjustment_months)
  showCreateDialog.value = true
}

const handleSubmit = async () => {
  if (!formRef.value) return
  
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    
    submitting.value = true
    try {
      if (isEdit.value) {
        await updateAccountSet(form.id, form)
        ElMessage.success('套账更新成功')
      } else {
        const createPayload = { ...form }
        delete createPayload.code
        await createAccountSet(createPayload)
        ElMessage.success('套账创建成功')
      }
      
      showCreateDialog.value = false
      loadAccountSets()
      loadStatistics()
    } catch (error) {
      console.error('Submit error:', error)
      ElMessage.error(error.response?.data?.message || '操作失败')
    } finally {
      submitting.value = false
    }
  })
}

const handleSetDefault = async (row) => {
  try {
    await ElMessageBox.confirm(
      `确定将"${row.name}"设置为默认套账吗？`,
      '确认操作',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
    
    await setDefaultAccountSet(row.id)
    ElMessage.success('已设置为默认套账')
    loadAccountSets()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('Set default error:', error)
      ElMessage.error('操作失败')
    }
  }
}

const handleArchive = async (row) => {
  try {
    await ElMessageBox.confirm(
      `确定归档"${row.name}"吗？归档后将不再显示在常用列表中。`,
      '确认归档',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
    
    await archiveAccountSet(row.id)
    ElMessage.success('套账已归档')
    loadAccountSets()
    loadStatistics()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('Archive error:', error)
      ElMessage.error(error.response?.data?.message || '归档失败')
    }
  }
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(
      `确定删除套账"${row.name}"吗？此操作不可恢复！`,
      '确认删除',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'error'
      }
    )
    
    await deleteAccountSet(row.id)
    ElMessage.success('套账删除成功')
    loadAccountSets()
    loadStatistics()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('Delete error:', error)
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }
}

const resetForm = () => {
  if (formRef.value) {
    formRef.value.resetFields()
  }
  Object.assign(form, {
    id: null,
    name: '',
    code: '',
    description: '',
    company_name: '',
    tax_number: '',
    contact_person: '',
    contact_phone: '',
    address: '',
    enabled_date: '',
    status: 'active',
    is_default: false,
    base_adjustment_months: []
  })
  isEdit.value = false
  isViewMode.value = false
}

const handleCreateFromRouteQuery = async () => {
  if (route.query.action !== 'create') return

  isViewMode.value = false
  isEdit.value = false
  resetForm()
  showCreateDialog.value = true

  const nextQuery = { ...route.query }
  delete nextQuery.action
  delete nextQuery.t

  try {
    await router.replace({ path: '/account-sets', query: nextQuery })
  } catch (error) {
    // ignore duplicated navigation
  }
}

watch(
  () => route.fullPath,
  () => {
    if (route.query.action === 'create') {
      handleCreateFromRouteQuery()
    }
  }
)

const getStatusType = (status) => {
  const types = {
    active: 'success',
    inactive: 'warning',
    archived: 'info'
  }
  return types[status] || 'info'
}

const getStatusText = (status) => {
  const texts = {
    active: '启用',
    inactive: '停用',
    archived: '已归档'
  }
  return texts[status] || '未知'
}

const formatDate = (dateValue) => {
  if (!dateValue) return '-'
  const date = new Date(dateValue)
  return date.toLocaleDateString('zh-CN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  })
}

const formatDateTime = (dateTime) => {
  if (!dateTime) return '-'
  const date = new Date(dateTime)
  return date.toLocaleString('zh-CN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const getRoleText = (role) => {
  const texts = {
    super_admin: '超级管理员',
    admin: '管理员',
    manager: '经理',
    employee: '员工',
    finance: '财务'
  }
  return texts[role] || role
}
</script>

<style scoped>
.account-sets-page {
  padding: 0;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.page-header h1 {
  margin: 0;
  font-size: 24px;
  font-weight: 600;
}

.search-section {
  margin-bottom: 20px;
}

.statistics-section {
  margin-bottom: 20px;
}

.stat-item {
  text-align: center;
  padding: 10px 0;
}

.stat-label {
  font-size: 14px;
  color: #909399;
  margin-bottom: 10px;
}

.stat-value {
  font-size: 28px;
  font-weight: bold;
  color: #303133;
}

.stat-value.active {
  color: #67C23A;
}

.stat-value.inactive {
  color: #E6A23C;
}

.stat-value.archived {
  color: #909399;
}

.table-section {
  margin-bottom: 20px;
}

.pagination {
  margin-top: 20px;
  text-align: right;
}

.form-tip {
  margin-left: 10px;
  font-size: 12px;
  color: #909399;
}

.users-section {
  padding: 10px 0;
}

.current-users {
  margin-bottom: 30px;
}

.current-users h3,
.add-users h3 {
  margin: 0 0 15px 0;
  font-size: 16px;
  color: #303133;
}

.add-users {
  padding-top: 20px;
  border-top: 1px solid #dcdfe6;
}

:deep(.el-table) {
  font-size: 14px;
}
</style>

