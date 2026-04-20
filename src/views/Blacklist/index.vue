<template>
  <div class="blacklist-page">
    <div class="page-header">
      <h1>人员黑名单</h1>
      <el-button type="danger" @click="showAddDialog = true">
        <el-icon><Plus /></el-icon>
        添加黑名单
      </el-button>
    </div>

    <!-- 搜索 -->
    <el-card shadow="never" style="margin-bottom: 20px;">
      <el-form :inline="true">
        <el-form-item label="搜索">
          <el-input
            v-model="searchForm.search"
            placeholder="姓名或身份证号"
            clearable
            style="width: 300px;"
            @keyup.enter="handleSearch"
          />
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

    <!-- 列表 -->
    <el-card shadow="never">
      <el-alert
        title="提示"
        type="warning"
        :closable="false"
        style="margin-bottom: 16px;"
      >
        黑名单人员将无法在任何账套中办理入职，已在职的员工将自动终止合同
      </el-alert>
      
      <el-table
        :data="tableData"
        v-loading="loading"
        border
        stripe
      >
        <el-table-column type="index" label="序号" width="60" align="center" />
        <el-table-column prop="name" label="姓名" width="120" />
        <el-table-column prop="id_number" label="身份证号" width="180" />
        <el-table-column prop="reason" label="加入原因" min-width="200" show-overflow-tooltip />
        <el-table-column label="操作人" width="120">
          <template #default="{ row }">
            {{ row.creator?.name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="加入时间" width="180">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="100" fixed="right">
          <template #default="{ row }">
            <el-button
              type="danger"
              size="small"
              @click="handleRemove(row)"
            >
              移除
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="pagination.current_page"
        v-model:page-size="pagination.per_page"
        :total="pagination.total"
        :page-sizes="[20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="loadData"
        @current-change="loadData"
        style="margin-top: 20px; justify-content: flex-end;"
      />
    </el-card>

    <!-- 添加黑名单对话框 -->
    <el-dialog
      v-model="showAddDialog"
      title="添加黑名单"
      width="500px"
    >
      <el-form :model="addForm" :rules="addRules" ref="addFormRef" label-width="120px">
        <el-form-item label="身份证号" prop="id_number">
          <el-input
            v-model="addForm.id_number"
            placeholder="请输入身份证号"
            maxlength="18"
          />
        </el-form-item>
        <el-form-item label="姓名" prop="name">
          <el-input
            v-model="addForm.name"
            placeholder="请输入姓名"
          />
        </el-form-item>
        <el-form-item label="加入原因" prop="reason">
          <el-input
            v-model="addForm.reason"
            type="textarea"
            :rows="4"
            placeholder="请输入加入黑名单的原因"
            maxlength="500"
            show-word-limit
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showAddDialog = false">取消</el-button>
        <el-button type="danger" @click="handleAdd" :loading="adding">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Search, Refresh } from '@element-plus/icons-vue'
import request from '@/api/request'

const loading = ref(false)
const adding = ref(false)
const showAddDialog = ref(false)
const tableData = ref([])
const addFormRef = ref()

const searchForm = reactive({
  search: ''
})

const pagination = reactive({
  current_page: 1,
  per_page: 20,
  total: 0
})

const addForm = reactive({
  id_number: '',
  name: '',
  reason: ''
})

const addRules = {
  id_number: [
    { required: true, message: '请输入身份证号', trigger: 'blur' },
    { pattern: /^[1-9]\d{5}(18|19|20)\d{2}((0[1-9])|(1[0-2]))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$/, message: '请输入正确的身份证号', trigger: 'blur' }
  ],
  name: [
    { required: true, message: '请输入姓名', trigger: 'blur' }
  ],
  reason: [
    { required: true, message: '请输入加入原因', trigger: 'blur' }
  ]
}

const loadData = async () => {
  loading.value = true
  try {
    const response = await request.get('/blacklist', {
      params: {
        page: pagination.current_page,
        per_page: pagination.per_page,
        ...searchForm
      }
    })
    
    if (response.success) {
      tableData.value = response.data.data
      pagination.total = response.data.total
      pagination.current_page = response.data.current_page
    }
  } catch (error) {
    console.error('加载失败:', error)
    ElMessage.error('加载失败')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  pagination.current_page = 1
  loadData()
}

const handleReset = () => {
  searchForm.search = ''
  handleSearch()
}

const handleAdd = async () => {
  if (!addFormRef.value) return
  
  await addFormRef.value.validate(async (valid) => {
    if (!valid) return
    
    adding.value = true
    try {
      const response = await request.post('/blacklist', addForm)
      
      if (response.success) {
        ElMessage.success(response.message)
        showAddDialog.value = false
        addFormRef.value.resetFields()
        loadData()
      }
    } catch (error) {
      console.error('添加失败:', error)
      ElMessage.error(error.response?.data?.message || '添加失败')
    } finally {
      adding.value = false
    }
  })
}

const handleRemove = (row) => {
  ElMessageBox.confirm(
    `确定要将 ${row.name}（${row.id_number}）从黑名单中移除吗？`,
    '确认移除',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    }
  ).then(async () => {
    try {
      const response = await request.delete(`/blacklist/${row.id}`)
      
      if (response.success) {
        ElMessage.success('移除成功')
        loadData()
      }
    } catch (error) {
      console.error('移除失败:', error)
      ElMessage.error('移除失败')
    }
  }).catch(() => {})
}

// 格式化日期时间
const formatDateTime = (dateTime) => {
  if (!dateTime) return '-'
  const date = new Date(dateTime)
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  const seconds = String(date.getSeconds()).padStart(2, '0')
  return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.blacklist-page {
  padding: 20px;
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
  font-weight: 500;
}
</style>
