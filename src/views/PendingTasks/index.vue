<template>
  <div class="pending-tasks-container">
    <el-row :gutter="20" style="margin-bottom: 20px">
      <el-col :span="12">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-icon pending">
              <el-icon :size="32"><Clock /></el-icon>
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ statistics.pending || 0 }}</div>
              <div class="stat-label">待处理任务</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-icon completed">
              <el-icon :size="32"><CircleCheck /></el-icon>
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ statistics.completed || 0 }}</div>
              <div class="stat-label">已完成任务</div>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-card>
      <template #header>
        <div class="card-header">
          <span>待办任务</span>
          <el-radio-group v-model="statusFilter" @change="handleStatusChange">
            <el-radio-button label="pending">待处理</el-radio-button>
            <el-radio-button label="completed">已完成</el-radio-button>
          </el-radio-group>
        </div>
      </template>

      <el-table :data="tasks" v-loading="loading" style="width: 100%">
        <el-table-column prop="title" label="任务标题" min-width="200" />
        <el-table-column prop="description" label="任务描述" min-width="300" />
        <el-table-column prop="task_type" label="任务类型" width="150">
          <template #default="{ row }">
            <el-tag :type="getTaskTypeTagType(row.task_type)">
              {{ getTaskTypeName(row.task_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 'pending' ? 'warning' : 'success'">
              {{ row.status === 'pending' ? '待处理' : '已完成' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="180">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column prop="completed_at" label="完成时间" width="180">
          <template #default="{ row }">
            {{ row.completed_at ? formatDateTime(row.completed_at) : '-' }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" size="small" @click="handleViewTask(row)">
              {{ row.status === 'pending' ? '去处理' : '查看详情' }}
            </el-button>
          </template>
        </el-table-column>
        <template #empty>
          <el-empty :description="statusFilter === 'pending' ? '暂无待处理任务' : '暂无已完成任务'" />
        </template>
      </el-table>

      <el-pagination
        v-if="total > 0"
        v-model:current-page="currentPage"
        v-model:page-size="pageSize"
        :total="total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="fetchTasks"
        @current-change="fetchTasks"
        style="margin-top: 20px; justify-content: flex-end"
      />
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Clock, CircleCheck } from '@element-plus/icons-vue'
import { getPendingTasks, getPendingTasksStatistics } from '@/api/pendingTasks'
import { useAccountSetStore } from '@/stores/accountSet'

const router = useRouter()
const accountSetStore = useAccountSetStore()

const tasks = ref([])
const loading = ref(false)
const statusFilter = ref('pending')
const currentPage = ref(1)
const pageSize = ref(20)
const total = ref(0)
const statistics = ref({
  pending: 0,
  completed: 0
})

const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)

const getTaskTypeName = (type) => {
  const typeMap = {
    'payment_receipt': '付款申请回执',
    'offline_contract': '线下合同上传',
    'document_delivery': '资料交付',
    'salary_basis': '工资依据上传',
    'attendance_basis': '考勤依据上传',
    'attendance_sheet': '考勤表提交',
    'salary_sheet': '工资表提交',
    'tax_declaration': '税费申报'
  }
  return typeMap[type] || type
}

const getTaskTypeTagType = (type) => {
  const typeMap = {
    'payment_receipt': 'primary',
    'offline_contract': 'success',
    'document_delivery': 'warning',
    'salary_basis': 'danger',
    'attendance_basis': 'info',
    'attendance_sheet': '',
    'salary_sheet': 'warning'
  }
  return typeMap[type] || 'info'
}

const fetchTasks = async () => {
  if (!currentAccountSetId.value) {
    ElMessage.warning('请先选择账套')
    return
  }

  loading.value = true
  try {
    const response = await getPendingTasks({
      account_set_id: currentAccountSetId.value,
      status: statusFilter.value,
      page: currentPage.value,
      per_page: pageSize.value
    })

    if (response.success) {
      tasks.value = response.data.data || []
      total.value = response.data.total || 0
    }
  } catch (error) {
    console.error('获取待办任务失败:', error)
    ElMessage.error('获取待办任务失败')
  } finally {
    loading.value = false
  }
}

const fetchStatistics = async () => {
  if (!currentAccountSetId.value) {
    return
  }

  try {
    const response = await getPendingTasksStatistics({
      account_set_id: currentAccountSetId.value
    })

    if (response.success) {
      statistics.value = {
        pending: response.data.pending || 0,
        completed: response.data.completed || 0
      }
    }
  } catch (error) {
    console.error('获取统计数据失败:', error)
  }
}

const handleStatusChange = () => {
  currentPage.value = 1
  fetchTasks()
}

const handleViewTask = (task) => {
  if (task.task_type === 'payment_receipt') {
    router.push('/payment-applications')
  } else if (task.task_type === 'offline_contract') {
    router.push('/employees')
  } else if (task.task_type === 'document_delivery') {
    router.push('/document-deliveries')
  } else if (task.task_type === 'salary_basis') {
    // 跳转到工资依据页面，并传递筛选参数
    const params = task.route_params ? JSON.parse(task.route_params) : {}
    router.push({
      path: '/salary-basis',
      query: params
    })
  } else if (task.task_type === 'attendance_basis') {
    // 跳转到考勤依据页面，并传递筛选参数
    const params = task.route_params ? JSON.parse(task.route_params) : {}
    router.push({
      path: '/attendance-basis',
      query: params
    })
  } else if (task.task_type === 'attendance_sheet') {
    // 跳转到考勤表页面，并传递筛选参数
    const params = task.route_params ? JSON.parse(task.route_params) : {}
    router.push({
      path: '/attendance-sheets',
      query: params
    })
  } else if (task.task_type === 'salary_sheet') {
    // 跳转到工资表页面，并传递筛选参数
    const params = task.route_params ? JSON.parse(task.route_params) : {}
    router.push({
      path: '/salary-sheets',
      query: params
    })
  } else if (task.task_type === 'tax_declaration') {
    // 跳转到税费申报任务页面
    router.push('/tax-declaration-tasks')
  }
}

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
  fetchStatistics()
  fetchTasks()
})
</script>

<style scoped>
.pending-tasks-container {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 16px;
}

.stat-icon {
  width: 60px;
  height: 60px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-icon.pending {
  background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%);
  color: #d63031;
}

.stat-icon.completed {
  background: linear-gradient(135deg, #55efc4 0%, #00b894 100%);
  color: #00b894;
}

.stat-content {
  flex: 1;
}

.stat-value {
  font-size: 28px;
  font-weight: bold;
  color: #2d3436;
  line-height: 1;
  margin-bottom: 8px;
}

.stat-label {
  font-size: 14px;
  color: #636e72;
}
</style>
