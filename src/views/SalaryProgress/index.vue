<template>
  <div class="salary-progress-page">
    <NoAccountSetTip v-if="!isAdmin && !currentAccountSetId" />
    <div v-else>
      <div class="page-header">
        <h1>工资进度</h1>
      </div>

      <el-card shadow="never" style="margin-bottom: 20px;">
        <el-form :inline="true" :model="progressSearchForm" class="search-form">
          <el-form-item label="月份">
            <el-date-picker
              v-model="progressSearchForm.month"
              type="month"
              placeholder="选择月份"
              format="YYYY-MM"
              value-format="YYYY-MM"
              style="width: 140px;"
            />
          </el-form-item>

          <el-form-item label="进度状态">
            <el-select v-model="progressSearchForm.progress_status" style="width: 140px;">
              <el-option label="全部" value="all" />
              <el-option label="未完成" value="pending" />
              <el-option label="已完成" value="completed" />
            </el-select>
          </el-form-item>

          <el-form-item>
            <el-button type="primary" @click="loadPayrollProgress">查询</el-button>
            <el-button @click="handleResetProgress">重置</el-button>
          </el-form-item>
        </el-form>

        <div class="progress-summary">
          <el-tag type="info">项目总数 {{ payrollProgressSummary.total }}</el-tag>
          <el-tag type="warning">未完成 {{ payrollProgressSummary.pending }}</el-tag>
          <el-tag type="success">已完成 {{ payrollProgressSummary.completed }}</el-tag>
        </div>
      </el-card>

      <el-card shadow="never" class="progress-card">
        <template #header>
          <div class="card-header">
            <span class="title">工资流程进度</span>
            <el-button type="text" @click="loadPayrollProgress">
              <el-icon><Refresh /></el-icon>
              刷新
            </el-button>
          </div>
        </template>

        <el-table
          :data="payrollProgressRows"
          border
          stripe
          v-loading="payrollProgressLoading"
          style="width: 100%"
        >
          <el-table-column prop="project_name" label="项目名称" min-width="180" show-overflow-tooltip />
          <el-table-column prop="project_code" label="项目编号" min-width="140" />
          <el-table-column prop="month" label="工资期间" width="120" />
          <el-table-column label="当前进度" min-width="180">
            <template #default="{ row }">
              <el-tag :type="row.current_step?.is_completed ? 'success' : 'warning'">
                {{ row.current_step?.stage_label || '-' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="项目状态" width="100" align="center">
            <template #default="{ row }">
              <el-tag :type="row.project_status === 'active' ? 'success' : 'info'">
                {{ row.project_status === 'active' ? '进行中' : '已结束' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="start_date" label="开始日期" width="120" />
          <el-table-column prop="end_date" label="结束日期" width="120" />
          <el-table-column label="操作" width="120" fixed="right">
            <template #default="{ row }">
              <el-button type="primary" link @click="handleGoProgress(row)">
                去处理
              </el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-card>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Refresh } from '@element-plus/icons-vue'
import { useUserStore } from '@/stores/user'
import { useAccountSetStore } from '@/stores/accountSet'
import NoAccountSetTip from '@/components/NoAccountSetTip.vue'
import { getPayrollProgress } from '@/api/salaries'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()
const accountSetStore = useAccountSetStore()

const isAdmin = computed(() => userStore.userInfo?.role === 'admin')
const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)

const getCurrentMonth = () => {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  return `${year}-${month}`
}

const payrollProgressLoading = ref(false)
const payrollProgressRows = ref([])
const payrollProgressSummary = reactive({
  total: 0,
  pending: 0,
  completed: 0
})
const progressSearchForm = reactive({
  month: getCurrentMonth(),
  progress_status: 'all'
})
const applyingRouteQuery = ref(false)

const resetPayrollProgressData = () => {
  payrollProgressRows.value = []
  payrollProgressSummary.total = 0
  payrollProgressSummary.pending = 0
  payrollProgressSummary.completed = 0
}

const loadPayrollProgress = async () => {
  if (!currentAccountSetId.value && !isAdmin.value) {
    resetPayrollProgressData()
    return
  }

  payrollProgressLoading.value = true
  try {
    const response = await getPayrollProgress({
      month: progressSearchForm.month,
      progress_status: progressSearchForm.progress_status,
      current_account_set_id: currentAccountSetId.value
    })

    if (response && response.success) {
      payrollProgressRows.value = response.data || []
      payrollProgressSummary.total = response.summary?.total || 0
      payrollProgressSummary.pending = response.summary?.pending || 0
      payrollProgressSummary.completed = response.summary?.completed || 0
    } else {
      resetPayrollProgressData()
      ElMessage.error(response?.message || '加载工资进度失败')
    }
  } catch (error) {
    console.error('Load payroll progress error:', error)
    resetPayrollProgressData()
    ElMessage.error('加载工资进度失败')
  } finally {
    payrollProgressLoading.value = false
  }
}

const handleResetProgress = () => {
  applyingRouteQuery.value = true
  progressSearchForm.month = getCurrentMonth()
  progressSearchForm.progress_status = 'all'
  loadPayrollProgress()
  nextTick(() => {
    applyingRouteQuery.value = false
  })
}

const handleGoProgress = (row) => {
  const stageKey = row.current_step?.stage_key
  const query = {
    project_id: row.project_id,
    month: row.month
  }

  if (stageKey === 'attendance_basis_missing') {
    router.push({
      path: '/attendance-basis',
      query: {
        ...query,
        action: 'create'
      }
    })
    return
  }

  if (['attendance_pending_create', 'attendance_draft', 'attendance_submitted'].includes(stageKey)) {
    router.push({
      path: '/attendance',
      query: {
        ...query,
        tab: stageKey === 'attendance_pending_create' ? 'pending' : 'list'
      }
    })
    return
  }

  if (stageKey === 'salary_basis_missing') {
    router.push({
      path: '/salary-basis',
      query: {
        ...query,
        action: 'create'
      }
    })
    return
  }

  router.push({
    path: '/salaries',
    query: {
      ...query,
      tab: stageKey === 'salary_pending_create' ? 'pending' : 'list'
    }
  })
}

const applyRouteQuery = () => {
  const nextMonth = typeof route.query.month === 'string' ? route.query.month : null
  const nextStatus = typeof route.query.progress_status === 'string' ? route.query.progress_status : null

  applyingRouteQuery.value = true
  progressSearchForm.month = nextMonth || getCurrentMonth()
  progressSearchForm.progress_status = ['all', 'pending', 'completed'].includes(nextStatus) ? nextStatus : 'all'
  loadPayrollProgress()
  nextTick(() => {
    applyingRouteQuery.value = false
  })
}

onMounted(() => {
  applyRouteQuery()
})

watch(() => progressSearchForm.month, () => {
  if (applyingRouteQuery.value) {
    return
  }
  loadPayrollProgress()
})

watch(() => progressSearchForm.progress_status, () => {
  if (applyingRouteQuery.value) {
    return
  }
  loadPayrollProgress()
})

watch(
  () => route.query,
  () => {
    applyRouteQuery()
  }
)

watch(() => accountSetStore.currentAccountSetId, (newAccountSetId, oldAccountSetId) => {
  if (newAccountSetId && oldAccountSetId && newAccountSetId !== oldAccountSetId) {
    loadPayrollProgress()
  }
})
</script>

<style scoped>
.salary-progress-page {
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.page-header h1 {
  font-size: 24px;
  color: #303133;
  margin: 0;
}

.search-form {
  margin-bottom: 0;
}

.progress-summary {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 4px;
  flex-wrap: wrap;
}

.progress-card {
  margin-bottom: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.title {
  font-size: 16px;
  font-weight: bold;
}
</style>
