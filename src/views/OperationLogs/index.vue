<template>
  <div class="operation-logs-container">
    <div class="page-header">
      <h2>操作记录</h2>
    </div>

    <!-- 筛选条件 -->
    <el-card class="filter-card">
      <el-form :model="filterForm" inline>
        <el-form-item label="操作人员">
          <el-select v-model="filterForm.user_id" placeholder="请选择人员" clearable filterable style="width: 200px">
            <el-option
              v-for="user in users"
              :key="user.id"
              :label="user.name"
              :value="user.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="操作类型">
          <el-select v-model="filterForm.action" placeholder="请选择类型" clearable style="width: 150px">
            <el-option label="全部" value="" />
            <el-option label="创建" value="created" />
            <el-option label="更新" value="updated" />
            <el-option label="删除" value="deleted" />
          </el-select>
        </el-form-item>
        <el-form-item label="时间范围">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            style="width: 240px"
            @change="handleDateChange"
          />
        </el-form-item>
        <el-form-item label="关键词">
          <el-input
            v-model="filterForm.keyword"
            placeholder="搜索操作描述或人员"
            clearable
            style="width: 200px"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadLogs">查询</el-button>
          <el-button @click="resetFilter">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 操作记录列表 -->
    <el-card class="table-card">
      <el-table :data="logs" v-loading="loading" border stripe>
        <el-table-column prop="user_name" label="操作人员" width="120" />
        <el-table-column prop="description" label="操作描述" min-width="400" show-overflow-tooltip />
        <el-table-column prop="created_at" label="操作时间" width="160">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-container">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.per_page"
          :page-sizes="[20, 50, 100, 200]"
          :total="pagination.total"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="loadLogs"
          @current-change="loadLogs"
        />
      </div>
    </el-card>

    <!-- 详情弹窗 -->
    <el-dialog v-model="showDetailDialog" title="操作详情" width="700px">
      <el-descriptions :column="2" border v-if="currentLog">
        <el-descriptions-item label="操作人员">{{ currentLog.user_name }}</el-descriptions-item>
        <el-descriptions-item label="操作类型">
          <el-tag :type="getActionType(currentLog.action)" size="small">
            {{ getActionText(currentLog.action) }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="操作时间" :span="2">
          {{ formatDateTime(currentLog.created_at) }}
        </el-descriptions-item>
        <el-descriptions-item label="IP地址">{{ currentLog.ip_address }}</el-descriptions-item>
        <el-descriptions-item label="用户代理" :span="2">
          {{ currentLog.user_agent || '-' }}
        </el-descriptions-item>
        <el-descriptions-item label="模块" :span="2">{{ currentLog.model_type }}</el-descriptions-item>
        <el-descriptions-item label="操作描述" :span="2">{{ currentLog.description }}</el-descriptions-item>
        <el-descriptions-item label="变更前数据" :span="2" v-if="currentLog.old_values">
          <pre class="json-display">{{ formatJson(currentLog.old_values) }}</pre>
        </el-descriptions-item>
        <el-descriptions-item label="变更后数据" :span="2" v-if="currentLog.new_values">
          <pre class="json-display">{{ formatJson(currentLog.new_values) }}</pre>
        </el-descriptions-item>
      </el-descriptions>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { useAccountSetStore } from '@/stores/accountSet'
import request from '@/api/request'

const accountSetStore = useAccountSetStore()

// 计算属性
const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)

// 响应式数据
const loading = ref(false)
const logs = ref([])
const users = ref([])

// 筛选条件
const filterForm = ref({
  user_id: '',
  action: '',
  keyword: '',
  start_date: '',
  end_date: ''
})

const dateRange = ref([])

// 分页
const pagination = ref({
  page: 1,
  per_page: 20,
  total: 0
})

// 详情弹窗
const showDetailDialog = ref(false)
const currentLog = ref(null)

// 加载操作记录
const loadLogs = async () => {
  if (!currentAccountSetId.value) {
    ElMessage.warning('请先选择账套')
    return
  }

  loading.value = true
  try {
    const response = await request({
      url: '/operation-logs',
      method: 'get',
      params: {
        account_set_id: currentAccountSetId.value,
        ...filterForm.value,
        page: pagination.value.page,
        per_page: pagination.value.per_page
      }
    })

    if (response.success) {
      logs.value = response.data.data
      pagination.value.total = response.data.total
    }
  } catch (error) {
    console.error('加载操作记录失败:', error)
    ElMessage.error('加载操作记录失败')
  } finally {
    loading.value = false
  }
}

// 加载用户列表
const loadUsers = async () => {
  if (!currentAccountSetId.value) {
    return
  }
  
  try {
    const response = await request({
      url: '/users',
      method: 'get',
      params: {
        account_set_id: currentAccountSetId.value,
        all: 'true'  // 获取所有用户，不分页
      }
    })
    if (response.success && response.data) {
      users.value = Array.isArray(response.data) ? response.data : []
    } else {
      users.value = []
    }
  } catch (error) {
    console.error('加载用户列表失败:', error)
    users.value = []
  }
}

// 处理日期范围变化
const handleDateChange = (val) => {
  if (val && val.length === 2) {
    filterForm.value.start_date = formatDate(val[0])
    filterForm.value.end_date = formatDate(val[1])
  } else {
    filterForm.value.start_date = ''
    filterForm.value.end_date = ''
  }
}

// 重置筛选
const resetFilter = () => {
  filterForm.value = {
    user_id: '',
    action: '',
    keyword: '',
    start_date: '',
    end_date: ''
  }
  dateRange.value = []
  pagination.value.page = 1
  loadLogs()
}

// 查看详情
const viewDetail = (log) => {
  currentLog.value = log
  showDetailDialog.value = true
}

// 获取操作类型标签类型
const getActionType = (action) => {
  const typeMap = {
    created: 'success',
    updated: 'warning',
    deleted: 'danger'
  }
  return typeMap[action] || 'info'
}

// 获取操作类型文本
const getActionText = (action) => {
  const textMap = {
    created: '创建',
    updated: '更新',
    deleted: '删除'
  }
  return textMap[action] || action
}

// 格式化日期
const formatDate = (date) => {
  if (!date) return '-'
  const d = new Date(date)
  return d.toLocaleDateString('zh-CN')
}

// 格式化日期时间
const formatDateTime = (date) => {
  if (!date) return '-'
  const d = new Date(date)
  return d.toLocaleString('zh-CN', { hour12: false })
}

// 格式化JSON
const formatJson = (data) => {
  if (!data) return '-'
  try {
    if (typeof data === 'string') {
      data = JSON.parse(data)
    }
    return JSON.stringify(data, null, 2)
  } catch (e) {
    return data
  }
}

// 组件挂载
onMounted(() => {
  if (currentAccountSetId.value) {
    loadUsers()
    loadLogs()
  }
})

// 监听账套切换
watch(currentAccountSetId, (newVal) => {
  if (newVal) {
    // 重置筛选条件
    filterForm.value.user_id = ''
    users.value = []
    // 重新加载用户列表和操作记录
    loadUsers()
    loadLogs()
  }
})
</script>

<style scoped>
.operation-logs-container {
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  font-size: 24px;
  color: #303133;
}

.filter-card {
  margin-bottom: 20px;
}

.table-card {
  margin-bottom: 20px;
}

.pagination-container {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.json-display {
  background: #f5f7fa;
  padding: 10px;
  border-radius: 4px;
  font-size: 12px;
  max-height: 300px;
  overflow-y: auto;
  margin: 0;
}
</style>
