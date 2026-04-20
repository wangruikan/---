<template>
  <div class="users-page">
    <div class="page-header">
      <h1>用户管理</h1>
      <el-button type="primary" @click="showCreateDialog = true">
        <el-icon><Plus /></el-icon>
        新增用户
      </el-button>
    </div>

    <!-- 搜索筛选 -->
    <div class="search-section">
      <el-card>
        <el-form :model="searchForm" inline>
          <el-form-item label="搜索">
            <el-input
              v-model="searchForm.search"
              placeholder="姓名或邮箱"
              clearable
              style="width: 200px"
              @keyup.enter="handleSearch"
            />
          </el-form-item>
          
          <el-form-item label="角色">
            <el-select
              v-model="searchForm.role"
              placeholder="请选择角色"
              clearable
              style="width: 150px"
            >
              <el-option label="超级管理员" value="super_admin" />
              <el-option label="管理员" value="admin" />
              <el-option label="业务人员" value="employee" />
              <el-option label="薪资核算" value="payroll" />
              <el-option label="财务" value="finance" />
              <el-option label="结算员" value="settlement" />
              <el-option label="招聘" value="recruitment" />
            </el-select>
          </el-form-item>
          
          <el-form-item label="状态">
            <el-select
              v-model="searchForm.is_active"
              placeholder="请选择状态"
              clearable
              style="width: 120px"
            >
              <el-option label="启用" :value="1" />
              <el-option label="禁用" :value="0" />
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

    <!-- 用户列表 -->
    <div class="table-section">
      <el-card>
        <el-table
          :data="users"
          v-loading="loading"
          stripe
          border
        >
          <el-table-column prop="id" label="ID" width="80" />
          <el-table-column label="姓名" width="150">
            <template #default="{ row }">
              {{ row.nickname || row.name }}
              <el-tag v-if="row.nickname" size="small" type="info" style="margin-left: 5px;">
                {{ row.name }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="email" label="邮箱" width="200" />
          <el-table-column prop="phone" label="手机号" width="130" />
          <el-table-column prop="role" label="角色" width="120">
            <template #default="{ row }">
              <el-tag :type="getRoleType(row.role)">
                {{ getRoleText(row.role) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="is_active" label="状态" width="100">
            <template #default="{ row }">
              <el-switch
                v-model="row.is_active"
                @change="handleStatusChange(row)"
                :active-value="true"
                :inactive-value="false"
              />
            </template>
          </el-table-column>
          <el-table-column prop="created_at" label="创建时间" width="180">
            <template #default="{ row }">
              {{ formatDateTime(row.created_at) }}
            </template>
          </el-table-column>
          <el-table-column label="操作" width="280" fixed="right">
            <template #default="{ row }">
              <el-button size="small" @click="handleView(row)">查看</el-button>
              <el-button size="small" type="primary" @click="handleEdit(row)">编辑</el-button>
              <el-button size="small" type="warning" @click="handleResetPassword(row)">
                重置密码
              </el-button>
              <el-button
                v-if="row.id !== currentUserId"
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
      :title="isEdit ? (isViewMode ? '查看用户' : '编辑用户') : '新增用户'"
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
        <el-form-item label="用户名" prop="name">
          <el-input v-model="form.name" placeholder="请输入用户名（用于登录）" />
        </el-form-item>

        <el-form-item label="昵称" prop="nickname">
          <el-input v-model="form.nickname" placeholder="请输入昵称（用于显示）" />
        </el-form-item>

        <el-form-item label="邮箱" prop="email">
          <el-input v-model="form.email" placeholder="请输入邮箱" />
        </el-form-item>

        <el-form-item label="手机号" prop="phone">
          <el-input v-model="form.phone" placeholder="请输入手机号" />
        </el-form-item>

        <el-form-item label="密码" prop="password" v-if="!isEdit">
          <el-input
            v-model="form.password"
            type="password"
            placeholder="请输入密码（至少6位）"
            show-password
          />
        </el-form-item>

        <el-form-item label="角色" prop="role">
          <el-select v-model="form.role" placeholder="请选择角色" style="width: 100%">
            <el-option label="超级管理员" value="super_admin" />
            <el-option label="管理员" value="admin" />
            <el-option label="业务人员" value="employee" />
            <el-option label="薪资核算" value="payroll" />
            <el-option label="财务" value="finance" />
            <el-option label="结算员" value="settlement" />
            <el-option label="招聘" value="recruitment" />
          </el-select>
        </el-form-item>

        <el-form-item label="状态" v-if="isEdit">
          <el-switch
            v-model="form.is_active"
            active-text="启用"
            inactive-text="禁用"
            :active-value="1"
            :inactive-value="0"
          />
        </el-form-item>

        <!-- 弹幕权限 - 已隐藏 -->
        <!--
        <el-form-item label="弹幕权限" v-if="isEdit">
          <el-switch
            v-model="form.can_view_operation_barrage"
            active-text="可查看"
            inactive-text="不可查看"
            :active-value="1"
            :inactive-value="0"
          />
          <div style="color: #909399; font-size: 12px; margin-top: 5px;">
            开启后，该用户可以看到系统操作日志弹幕
          </div>
        </el-form-item>
        -->
      </el-form>

      <template #footer v-if="!isViewMode">
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitting">
          {{ isEdit ? '保存' : '创建' }}
        </el-button>
      </template>
    </el-dialog>

    <!-- 重置密码对话框 -->
    <el-dialog
      v-model="showResetPasswordDialog"
      title="重置密码"
      width="400px"
    >
      <el-form :model="passwordForm" :rules="passwordRules" ref="passwordFormRef" label-width="100px">
        <el-form-item label="新密码" prop="password">
          <el-input
            v-model="passwordForm.password"
            type="password"
            placeholder="请输入新密码（至少6位）"
            show-password
          />
        </el-form-item>
        <el-form-item label="确认密码" prop="confirmPassword">
          <el-input
            v-model="passwordForm.confirmPassword"
            type="password"
            placeholder="请再次输入密码"
            show-password
          />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="showResetPasswordDialog = false">取消</el-button>
        <el-button type="primary" @click="handleResetPasswordSubmit" :loading="submitting">
          确定
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useUserStore } from '@/stores/user'
import { useRouter } from 'vue-router'
import request from '@/api/request'

const router = useRouter()
const userStore = useUserStore()

// 权限检查
const isAdmin = computed(() => ['super_admin', 'admin'].includes(userStore.userInfo?.role))
const currentUserId = computed(() => userStore.userInfo?.id)

onMounted(() => {
  if (!isAdmin.value) {
    ElMessage.error('只有管理员可以访问用户管理')
    router.push('/')
    return
  }
  loadUsers()
})

const loading = ref(false)
const submitting = ref(false)
const showCreateDialog = ref(false)
const showResetPasswordDialog = ref(false)
const isEdit = ref(false)
const isViewMode = ref(false)
const formRef = ref()
const passwordFormRef = ref()

const users = ref([])
const currentResetUserId = ref(null)

const searchForm = reactive({
  search: '',
  role: '',
  is_active: ''
})

const pagination = reactive({
  currentPage: 1,
  pageSize: 20,
  total: 0
})

const form = reactive({
  name: '',
  nickname: '',
  email: '',
  password: '',
  role: 'employee',
  phone: '',
  avatar: '',
  is_active: 1,
  can_view_operation_barrage: 0
})

const passwordForm = reactive({
  password: '',
  confirmPassword: ''
})

const rules = {
  name: [{ required: true, message: '请输入用户名', trigger: 'blur' }],
  email: [
    { type: 'email', message: '请输入正确的邮箱格式', trigger: 'blur' }
  ],
  password: [
    { required: true, message: '请输入密码', trigger: 'blur' },
    { min: 6, message: '密码至少6位', trigger: 'blur' }
  ],
  role: [{ required: true, message: '请选择角色', trigger: 'change' }]
}

const passwordRules = {
  password: [
    { required: true, message: '请输入新密码', trigger: 'blur' },
    { min: 6, message: '密码至少6位', trigger: 'blur' }
  ],
  confirmPassword: [
    { required: true, message: '请再次输入密码', trigger: 'blur' },
    {
      validator: (rule, value, callback) => {
        if (value !== passwordForm.password) {
          callback(new Error('两次输入的密码不一致'))
        } else {
          callback()
        }
      },
      trigger: 'blur'
    }
  ]
}

const loadUsers = async () => {
  loading.value = true
  try {
    const params = {
      page: pagination.currentPage,
      per_page: pagination.pageSize,
      ...searchForm
    }
    
    const response = await request({
      url: '/users',
      method: 'get',
      params
    })
    
    if (response.success) {
      users.value = response.data.data || response.data || []
      pagination.total = response.data.total || users.value.length
    }
  } catch (error) {
    console.error('Load users error:', error)
    ElMessage.error('加载用户列表失败')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  pagination.currentPage = 1
  loadUsers()
}

const handleReset = () => {
  Object.assign(searchForm, {
    search: '',
    role: '',
    is_active: ''
  })
  handleSearch()
}

const handleSizeChange = (size) => {
  pagination.pageSize = size
  loadUsers()
}

const handleCurrentChange = (page) => {
  pagination.currentPage = page
  loadUsers()
}

const handleView = (row) => {
  isViewMode.value = true
  isEdit.value = false
  Object.assign(form, { 
    ...row,
    is_active: row.is_active ? 1 : 0
  })
  showCreateDialog.value = true
}

const handleEdit = (row) => {
  isViewMode.value = false
  isEdit.value = true
  Object.assign(form, { 
    ...row,
    // 确保 is_active 和 can_view_operation_barrage 是数字类型
    is_active: row.is_active ? 1 : 0,
    can_view_operation_barrage: row.can_view_operation_barrage ? 1 : 0
  })
  showCreateDialog.value = true
}

const handleSubmit = async () => {
  if (!formRef.value) return
  
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    
    submitting.value = true
    try {
      if (isEdit.value) {
        await request({
          url: `/users/${form.id}`,
          method: 'put',
          data: form
        })
        ElMessage.success('用户更新成功')
      } else {
        await request({
          url: '/users',
          method: 'post',
          data: form
        })
        ElMessage.success('用户创建成功')
      }
      
      showCreateDialog.value = false
      loadUsers()
    } catch (error) {
      console.error('Submit error:', error)
      ElMessage.error(error.response?.data?.message || '操作失败')
    } finally {
      submitting.value = false
    }
  })
}

const handleStatusChange = async (row) => {
  try {
    await request({
      url: `/users/${row.id}/status`,
      method: 'put',
      data: { is_active: row.is_active ? 1 : 0 }
    })
    ElMessage.success('用户状态更新成功')
  } catch (error) {
    console.error('Update status error:', error)
    // 恢复原状态
    row.is_active = !row.is_active
    ElMessage.error('状态更新失败')
  }
}

const handleResetPassword = (row) => {
  currentResetUserId.value = row.id
  passwordForm.password = ''
  passwordForm.confirmPassword = ''
  showResetPasswordDialog.value = true
}

const handleResetPasswordSubmit = async () => {
  if (!passwordFormRef.value) return
  
  await passwordFormRef.value.validate(async (valid) => {
    if (!valid) return
    
    submitting.value = true
    try {
      await request({
        url: `/users/${currentResetUserId.value}/reset-password`,
        method: 'post',
        data: { password: passwordForm.password }
      })
      
      ElMessage.success('密码重置成功')
      showResetPasswordDialog.value = false
    } catch (error) {
      console.error('Reset password error:', error)
      ElMessage.error('密码重置失败')
    } finally {
      submitting.value = false
    }
  })
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(
      `确定删除用户"${row.nickname || row.name}"吗？此操作不可恢复！`,
      '确认删除',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'error'
      }
    )
    
    await request({
      url: `/users/${row.id}`,
      method: 'delete'
    })
    
    ElMessage.success('用户删除成功')
    loadUsers()
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
    name: '',
    nickname: '',
    email: '',
    password: '',
    role: 'employee',
    phone: '',
    avatar: '',
    is_active: 1,
    can_view_operation_barrage: 0
  })
  isEdit.value = false
  isViewMode.value = false
}

const getRoleType = (role) => {
  const types = {
    super_admin: 'danger',
    admin: 'warning',
    manager: 'success',
    employee: 'info',
    payroll: 'primary',
    finance: 'success',
    settlement: 'info',
    recruitment: 'warning'
  }
  return types[role] || 'info'
}

const getRoleText = (role) => {
  const texts = {
    super_admin: '超级管理员',
    admin: '管理员',
    manager: '管理员',
    employee: '业务人员',
    payroll: '薪资核算',
    finance: '财务',
    settlement: '结算员',
    recruitment: '招聘'
  }
  return texts[role] || role
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
</script>

<style scoped>
.users-page {
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

.table-section {
  margin-bottom: 20px;
}

.pagination {
  margin-top: 20px;
  text-align: right;
}

:deep(.el-table) {
  font-size: 14px;
}
</style>

