<template>
  <div class="process-records">
    <div class="page-header">
      <h1>流程记录管理</h1>
      <p class="page-desc">查看所有审批流程记录（仅限管理员）</p>
    </div>

    <!-- 统计卡片 -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon total">
          <el-icon><Document /></el-icon>
        </div>
        <div class="stat-content">
          <div class="stat-number">{{ stats.total }}</div>
          <div class="stat-label">总记录数</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon pending">
          <el-icon><Clock /></el-icon>
        </div>
        <div class="stat-content">
          <div class="stat-number">{{ stats.pending }}</div>
          <div class="stat-label">待处理</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon approved">
          <el-icon><Check /></el-icon>
        </div>
        <div class="stat-content">
          <div class="stat-number">{{ stats.approved }}</div>
          <div class="stat-label">已通过</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon rejected">
          <el-icon><CircleClose /></el-icon>
        </div>
        <div class="stat-content">
          <div class="stat-number">{{ stats.rejected }}</div>
          <div class="stat-label">已拒绝</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon completed">
          <el-icon><Select /></el-icon>
        </div>
        <div class="stat-content">
          <div class="stat-number">{{ stats.completed }}</div>
          <div class="stat-label">已完成</div>
        </div>
      </div>
    </div>

    <!-- 筛选条件 -->
    <div class="filter-section">
      <el-card>
        <el-form :model="filterForm" inline>
          <el-form-item label="状态">
            <el-select v-model="filterForm.status" placeholder="请选择状态" clearable style="width: 180px">
              <el-option label="审批中" value="pending" />
              <el-option label="已通过" value="approved" />
              <el-option label="已拒绝" value="rejected" />
              <el-option label="已完成" value="completed" />
            </el-select>
          </el-form-item>
          <el-form-item label="业务类型">
            <el-select v-model="filterForm.business_type" placeholder="请选择业务类型" clearable style="width: 180px">
              <el-option label="员工合同" value="employee_contract" />
              <el-option label="发票申请" value="invoice_application" />
              <el-option label="工资表审批" value="salary_approval" />
              <el-option label="付款申请" value="payment_application" />
            </el-select>
          </el-form-item>
          <el-form-item label="当前步骤">
            <el-select v-model="filterForm.current_step" placeholder="请选择当前步骤" clearable style="width: 180px">
              <el-option label="第1步" value="1" />
              <el-option label="第2步" value="2" />
              <el-option label="第3步" value="3" />
              <el-option label="第4步" value="4" />
            </el-select>
          </el-form-item>
          <el-form-item label="创建时间">
            <el-date-picker
              v-model="filterForm.date_range"
              type="daterange"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              format="YYYY-MM-DD"
              value-format="YYYY-MM-DD"
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
    </div>

    <!-- 数据表格 -->
    <div class="table-section">
      <el-card>
        <el-table 
          :data="records" 
          v-loading="loading"
          stripe
          border
        >
          <el-table-column prop="id" label="流程ID" width="100" />
          <el-table-column prop="account_set_name" label="所属账套" width="120" />
          <el-table-column prop="business_type_text" label="业务类型" width="150" />
          <el-table-column label="流程进度" width="120">
            <template #default="{ row }">
              <div class="step-progress-info">
                <el-progress 
                  :percentage="Math.round((row.current_step / row.total_steps) * 100)" 
                  :status="row.status === 'approved' ? 'success' : row.status === 'rejected' ? 'exception' : null"
                />
                <span class="progress-text">{{ row.current_step }}/{{ row.total_steps }}步</span>
              </div>
            </template>
          </el-table-column>
          <el-table-column prop="creator_name" label="发起人" width="120" />
          <el-table-column prop="current_approver" label="当前审批人" width="120" />
          <el-table-column prop="status" label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="getStatusType(row.status)">
                {{ row.status_text }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="approved_at" label="审批时间" width="160" />
          <el-table-column prop="created_at" label="创建时间" width="160" />
          <el-table-column label="操作" width="120" fixed="right">
            <template #default="{ row }">
              <el-button 
                type="primary" 
                size="small" 
                @click="handleViewDetail(row)"
              >
                查看详情
              </el-button>
            </template>
          </el-table-column>
        </el-table>

        <!-- 分页 -->
        <div class="pagination-wrapper">
          <el-pagination
            v-model:current-page="pagination.current_page"
            v-model:page-size="pagination.per_page"
            :page-sizes="[10, 15, 20, 50]"
            :total="pagination.total"
            layout="total, sizes, prev, pager, next, jumper"
            @size-change="handleSizeChange"
            @current-change="handleCurrentChange"
          />
        </div>
      </el-card>
    </div>

    <!-- 详情对话框 -->
    <el-dialog 
      v-model="detailDialogVisible" 
      title="流程记录详情" 
      width="600px"
    >
      <div v-if="currentRecord" class="detail-content">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="流程ID">
            {{ currentRecord.id }}
          </el-descriptions-item>
          <el-descriptions-item label="业务类型">
            {{ currentRecord.business_type_text }}
          </el-descriptions-item>
          <el-descriptions-item label="当前进度">
            第{{ currentRecord.current_step }}/{{ currentRecord.total_steps }}步
          </el-descriptions-item>
          <el-descriptions-item label="流程状态">
            <el-tag :type="getStatusType(currentRecord.status)">
              {{ currentRecord.status_text }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="发起人">
            {{ currentRecord.creator_name }}
          </el-descriptions-item>
          <el-descriptions-item label="当前审批人">
            {{ currentRecord.current_approver }}
          </el-descriptions-item>
          <el-descriptions-item label="创建时间">
            {{ currentRecord.created_at }}
          </el-descriptions-item>
          <el-descriptions-item label="完成时间">
            {{ currentRecord.completed_at || '未完成' }}
          </el-descriptions-item>
          <el-descriptions-item label="审批记录" :span="2">
            <el-steps 
              :active="currentRecord.current_step - 1" 
              finish-status="success"
              align-center
              v-if="currentRecord.approval_summary && currentRecord.approval_summary.length"
            >
              <el-step 
                v-for="step in currentRecord.approval_summary" 
                :key="step.step"
                :title="`第${step.step}步 - ${step.name}`"
                :description="step.approver"
                :status="step.status === 'approved' ? 'finish' : step.status === 'pending' ? 'process' : step.status === 'rejected' ? 'error' : 'wait'"
              />
            </el-steps>
          </el-descriptions-item>
        </el-descriptions>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue'
import { 
  Document, 
  Clock, 
  Check, 
  Close, 
  Search, 
  Refresh 
} from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { getProcessRecords, getProcessRecordStats } from '@/api/processRecord'
import { useAccountSetStore } from '@/stores/accountSet'

const accountSetStore = useAccountSetStore()

// 统计数据
const stats = reactive({
  total: 0,
  pending: 0,
  approved: 0,
  rejected: 0,
  completed: 0
})

// 筛选表单
const filterForm = reactive({
  status: '',
  business_type: '',
  current_step: '',
  date_range: null
})

// 表格数据
const records = ref([])
const loading = ref(false)

// 分页
const pagination = ref({
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1
})

// 详情对话框
const detailDialogVisible = ref(false)
const currentRecord = ref(null)

// 获取统计数据
const loadStats = async () => {
  try {
    const params = {}
    
    // 如果有选择账套，则传递账套ID；否则统计所有账套
    if (accountSetStore.currentAccountSet?.id) {
      params.account_set_id = accountSetStore.currentAccountSet.id
    }

    const response = await getProcessRecordStats(params)

    if (response.success) {
      // stats是reactive对象，需要使用Object.assign更新
      Object.assign(stats, response.data)
      console.log('统计数据已更新:', stats)
    }
  } catch (error) {
    console.error('获取统计数据失败:', error)
  }
}

// 获取记录列表
const loadRecords = async () => {
  try {
    loading.value = true
    
    const params = {
      page: pagination.value.current_page,
      per_page: pagination.value.per_page
    }
    
    // 如果有选择账套，则传递账套ID；否则查询所有账套
    if (accountSetStore.currentAccountSet?.id) {
      params.account_set_id = accountSetStore.currentAccountSet.id
    }
    
    // 只传递有值的筛选条件
    if (filterForm.status) {
      params.status = filterForm.status
    }
    if (filterForm.business_type) {
      params.business_type = filterForm.business_type
    }
    if (filterForm.current_step) {
      params.current_step = filterForm.current_step
    }
    if (filterForm.date_range) {
      params.date_range = filterForm.date_range
    }

    const response = await getProcessRecords(params)

    if (response.success) {
      records.value = response.data.records
      pagination.value = response.data.pagination
    } else {
      ElMessage.error(response.message || '获取记录失败')
    }
  } catch (error) {
    console.error('获取记录失败:', error)
    ElMessage.error('获取记录失败')
  } finally {
    loading.value = false
  }
}

// 搜索
const handleSearch = () => {
  pagination.value.current_page = 1
  loadRecords()
}

// 重置
const handleReset = () => {
  Object.assign(filterForm, {
    status: '',
    business_name: '',
    date_range: null
  })
  pagination.value.current_page = 1
  loadRecords()
}

// 分页变化
const handleSizeChange = (size) => {
  pagination.value.per_page = size
  pagination.value.current_page = 1
  loadRecords()
}

const handleCurrentChange = (page) => {
  pagination.value.current_page = page
  loadRecords()
}

// 查看详情
const handleViewDetail = (record) => {
  currentRecord.value = record
  detailDialogVisible.value = true
}

// 获取状态类型
const getStatusType = (status) => {
  switch (status) {
    case 'pending': return 'warning'      // 审批中 - 橙色
    case 'approved': return 'success'     // 已通过 - 绿色
    case 'rejected': return 'danger'      // 已拒绝 - 红色
    case 'cancelled': return 'info'       // 已取消 - 蓝色
    case 'completed': return 'success'    // 已完成 - 绿色
    default: return 'info'
  }
}

onMounted(() => {
  loadStats()
  loadRecords()
})
</script>

<style scoped>
.process-records {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h1 {
  margin: 0 0 8px 0;
  color: #303133;
}

.page-desc {
  margin: 0;
  color: #606266;
  font-size: 14px;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 20px;
}

.stat-card {
  background: #fff;
  border-radius: 8px;
  padding: 20px;
  border: 1px solid #e4e7ed;
  display: flex;
  align-items: center;
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 16px;
  font-size: 24px;
}

.stat-icon.total {
  background: #e3f2fd;
  color: #1976d2;
}

.stat-icon.pending {
  background: #fff3e0;
  color: #f57c00;
}

.stat-icon.approved {
  background: #e8f5e8;
  color: #388e3c;
}

.stat-icon.rejected {
  background: #ffebee;
  color: #d32f2f;
}

.stat-number {
  font-size: 24px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 4px;
}

.stat-label {
  font-size: 14px;
  color: #606266;
}

.filter-section {
  margin-bottom: 20px;
}

.table-section {
  margin-bottom: 20px;
}

.pagination-wrapper {
  display: flex;
  justify-content: center;
  margin-top: 20px;
}

.detail-content {
  padding: 20px 0;
}

.text-danger {
  color: #f56c6c;
  font-weight: 600;
}

.step-progress-info {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 8px 0;
}

.progress-text {
  font-size: 12px;
  color: #606266;
  text-align: center;
  font-weight: 500;
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
}
</style>
