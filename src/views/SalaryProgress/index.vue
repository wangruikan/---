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
          :row-key="getProgressRowKey"
          style="width: 100%"
        >
          <el-table-column type="expand" width="48">
            <template #default="{ row }">
              <div class="flow-panel">
                <div v-if="hasApprovalFlow(row)" class="approval-flow">
                  <div class="flow-title">
                    <span>{{ row.current_step.approval_flow.business_type }}流程图</span>
                    <el-tag :type="getFlowStatusType(row.current_step.approval_flow.status)" size="small">
                      {{ getFlowStatusText(row.current_step.approval_flow.status) }}
                    </el-tag>
                  </div>
                  <div class="flow-timeline">
                    <div
                      v-for="(node, index) in row.current_step.approval_flow.nodes"
                      :key="node.id || index"
                      class="flow-node"
                      :class="getFlowNodeClass(node)"
                    >
                      <div class="node-marker">
                        <div class="node-dot">{{ index + 1 }}</div>
                        <div v-if="index < row.current_step.approval_flow.nodes.length - 1" class="node-line"></div>
                      </div>
                      <div class="node-card">
                        <div class="node-header">
                          <span class="node-name">{{ node.step_name || `第${index + 1}级审批` }}</span>
                          <el-tag :type="getNodeStatusType(node.status)" size="small">
                            {{ getNodeStatusText(node.status) }}
                          </el-tag>
                        </div>
                        <div class="node-meta">审批人：{{ node.approver_name || '-' }}</div>
                        <div v-if="node.approved_at" class="node-meta">审批时间：{{ node.approved_at }}</div>
                        <div v-if="node.comment" class="node-comment">意见：{{ node.comment }}</div>
                      </div>
                    </div>
                  </div>
                </div>
                <el-empty v-else description="当前节点还没有审批流程图" :image-size="60" />
              </div>
            </template>
          </el-table-column>
          <el-table-column prop="project_name" label="项目名称" min-width="180" show-overflow-tooltip />
          <el-table-column prop="project_code" label="项目编号" min-width="140" />
          <el-table-column prop="month" label="工资期间" width="120" />
          <el-table-column label="当前进度" min-width="180">
            <template #default="{ row }">
              <el-tag :type="row.current_step?.is_completed ? 'success' : 'warning'">
                {{ row.current_step?.stage_label || '-' }}
              </el-tag>
              <el-tag v-if="hasApprovalFlow(row)" type="primary" effect="plain" class="flow-hint">
                可展开查看流程图
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

const hasApprovalFlow = (row) => {
  return Array.isArray(row?.current_step?.approval_flow?.nodes)
    && row.current_step.approval_flow.nodes.length > 0
}

const getProgressRowKey = (row) => {
  const flowId = row?.current_step?.approval_flow?.instance_id || 'no-flow'
  const stageKey = row?.current_step?.stage_key || 'unknown'
  return `${row.project_id}-${row.month}-${stageKey}-${flowId}`
}

const getNodeStatusType = (status) => {
  if (status === 'approved') return 'success'
  if (status === 'rejected') return 'danger'
  if (status === 'pending') return 'warning'
  return 'info'
}

const getNodeStatusText = (status) => {
  const map = {
    approved: '已通过',
    rejected: '已驳回',
    pending: '待审批',
    waiting: '待处理'
  }
  return map[status] || '未知'
}

const getFlowStatusType = (status) => {
  if (status === 'completed') return 'success'
  if (status === 'rejected') return 'danger'
  if (status === 'pending') return 'warning'
  return 'info'
}

const getFlowStatusText = (status) => {
  const map = {
    completed: '已完成',
    rejected: '已驳回',
    pending: '审批中'
  }
  return map[status] || '未知'
}

const getFlowNodeClass = (node) => {
  return {
    'node-approved': node.status === 'approved',
    'node-rejected': node.status === 'rejected',
    'node-pending': node.status === 'pending',
    'node-waiting': !['approved', 'rejected', 'pending'].includes(node.status)
  }
}

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

.flow-hint {
  margin-left: 8px;
}

.flow-panel {
  padding: 16px 24px 18px;
  background: linear-gradient(135deg, #f8fbff 0%, #f6f7fb 100%);
}

.approval-flow {
  border: 1px solid #e4e7ed;
  border-radius: 12px;
  background: #fff;
  padding: 16px 18px 18px;
}

.flow-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
  font-size: 15px;
  font-weight: 600;
  color: #303133;
}

.flow-timeline {
  display: flex;
  gap: 0;
  overflow-x: auto;
  padding-bottom: 4px;
}

.flow-node {
  display: flex;
  min-width: 230px;
}

.node-marker {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-right: 10px;
}

.node-dot {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  color: #909399;
  background: #f5f7fa;
  border: 2px solid #dcdfe6;
  z-index: 1;
}

.node-line {
  width: 2px;
  flex: 1;
  min-height: 72px;
  margin-top: 6px;
  background: #dcdfe6;
}

.node-card {
  flex: 1;
  min-height: 104px;
  margin-right: 18px;
  padding: 12px;
  border: 1px solid #e4e7ed;
  border-radius: 10px;
  background: #fff;
  box-shadow: 0 2px 8px rgba(31, 45, 61, 0.06);
}

.node-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 10px;
}

.node-name {
  font-weight: 600;
  color: #303133;
}

.node-meta {
  font-size: 13px;
  line-height: 1.7;
  color: #606266;
}

.node-comment {
  margin-top: 8px;
  padding: 8px 10px;
  border-radius: 8px;
  background: #f5f7fa;
  color: #606266;
  font-size: 13px;
  line-height: 1.5;
}

.node-approved .node-dot {
  color: #fff;
  background: #67c23a;
  border-color: #67c23a;
}

.node-approved .node-line {
  background: linear-gradient(to bottom, #67c23a, #dcdfe6);
}

.node-rejected .node-dot {
  color: #fff;
  background: #f56c6c;
  border-color: #f56c6c;
}

.node-rejected .node-card {
  border-color: #fab6b6;
  background: #fef0f0;
}

.node-pending .node-dot {
  color: #fff;
  background: #e6a23c;
  border-color: #e6a23c;
  box-shadow: 0 0 0 4px rgba(230, 162, 60, 0.14);
}

.node-pending .node-card {
  border-color: #f0c78a;
  background: #fffaf0;
}
</style>
