<template>
  <div class="assessment-container">
    <div class="page-header">
      <h2>考核记录</h2>
    </div>


    <!-- 筛选条件 -->
    <el-card class="filter-card">
      <el-form :model="filterForm" inline>
        <el-form-item label="业务类型">
          <el-select v-model="filterForm.business_type" placeholder="请选择业务类型" clearable style="width: 150px">
            <el-option label="全部" value="" />
            <el-option label="参保入职" value="insurance_enrollment" />
            <el-option label="合同签署" value="contract_signing" />
            <el-option label="工资发放" value="salary_payment" />
            <el-option label="发票处理" value="invoice_processing" />
            <el-option label="资料收集" value="document_upload" />
            <el-option label="合同管理" value="contract_management" />
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
        <el-form-item>
          <el-button type="primary" @click="loadRecords">查询</el-button>
          <el-button @click="resetFilter">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 考核记录列表 -->
    <el-card class="table-card">
      <el-table :data="records" v-loading="loading" border stripe>
        <el-table-column prop="business_type_text" label="业务类型" width="120" />
        <el-table-column prop="business_name" label="业务描述" min-width="200" />
        <el-table-column prop="remark" label="备注" min-width="300" show-overflow-tooltip />
        <el-table-column prop="created_at" label="创建时间" width="160">
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
          :page-sizes="[10, 20, 50, 100]"
          :total="pagination.total"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="loadRecords"
          @current-change="loadRecords"
        />
      </div>
    </el-card>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { useAccountSetStore } from '@/stores/accountSet'
import {
  getAssessmentRecords
} from '@/api/assessment'

const accountSetStore = useAccountSetStore()

// 计算属性
const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)

// 响应式数据
const loading = ref(false)
const records = ref([])

// 筛选条件
const filterForm = ref({
  business_type: '',
  status: '',
  handler_id: '',
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


// 加载考核记录
const loadRecords = async () => {
  if (!currentAccountSetId.value) {
    ElMessage.warning('请先选择账套')
    return
  }

  loading.value = true
  try {
    const response = await getAssessmentRecords({
      account_set_id: currentAccountSetId.value,
      ...filterForm.value,
      page: pagination.value.page,
      per_page: pagination.value.per_page
    })

    if (response.success) {
      records.value = response.data
      pagination.value.total = response.total
    }
  } catch (error) {
    console.error('加载考核记录失败:', error)
    ElMessage.error('加载考核记录失败')
  } finally {
    loading.value = false
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
    business_type: '',
    status: '',
    handler_id: '',
    start_date: '',
    end_date: ''
  }
  dateRange.value = []
  pagination.value.page = 1
  loadRecords()
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
  return d.toLocaleString('zh-CN')
}


// 组件挂载
onMounted(() => {
  if (currentAccountSetId.value) {
    loadRecords()
  }
})
</script>

<style scoped>
.assessment-container {
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

.header-actions {
  display: flex;
  gap: 10px;
}

/* 统计卡片 */
.statistics-row {
  margin-bottom: 20px;
}

.stat-card {
  cursor: pointer;
  transition: all 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.stat-content {
  display: flex;
  align-items: center;
  gap: 15px;
}

.stat-icon {
  width: 50px;
  height: 50px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: white;
}

.stat-icon.total {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-icon.pending {
  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.stat-icon.overdue {
  background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
}

.stat-icon.completed {
  background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
}

.stat-info {
  flex: 1;
}

.stat-value {
  font-size: 28px;
  font-weight: bold;
  color: #303133;
  line-height: 1;
  margin-bottom: 5px;
}

.stat-label {
  font-size: 14px;
  color: #909399;
}

/* 筛选卡片 */
.filter-card {
  margin-bottom: 20px;
}

/* 表格卡片 */
.table-card {
  margin-bottom: 20px;
}

.overdue-text {
  color: #f56c6c;
  font-weight: bold;
}

/* 分页 */
.pagination-container {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
</style>
