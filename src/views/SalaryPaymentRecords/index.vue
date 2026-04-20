<template>
  <div class="salary-payment-records-container">
    <!-- 页面标题 -->
    <div class="page-header">
      <h1>发工资表</h1>
    </div>

    <!-- 搜索和操作栏 -->
    <el-card class="search-card">
      <template #header>
        <div class="card-header">
          <span>筛选条件</span>
        </div>
      </template>

      <el-row :gutter="20" class="search-form">
        <el-col :span="6">
          <el-form-item label="项目">
            <el-select
              v-model="searchForm.project_id"
              placeholder="请选择项目"
              clearable
              @change="handleSearch"
            >
              <el-option
                v-for="project in projects"
                :key="project.id"
                :label="project.name"
                :value="project.id"
              />
            </el-select>
          </el-form-item>
        </el-col>

        <el-col :span="6">
          <el-form-item label="月份">
            <el-date-picker
              v-model="searchForm.month"
              type="month"
              placeholder="请选择月份"
              format="YYYY-MM"
              value-format="YYYY-MM"
              @change="handleSearch"
            />
          </el-form-item>
        </el-col>

        <el-col :span="12">
          <el-button type="primary" @click="handleSearch" :loading="loading">
            <el-icon><Search /></el-icon>
            查询
          </el-button>
          <el-button @click="handleReset">
            <el-icon><Refresh /></el-icon>
            重置
          </el-button>
          <el-button type="success" @click="handleExport" :loading="exportLoading">
            <el-icon><Download /></el-icon>
            导出 Excel
          </el-button>
        </el-col>
      </el-row>
    </el-card>

    <!-- 数据表格 -->
    <el-card class="table-card">
      <template #header>
        <div class="card-header">
          <span>发工资表列表（共 {{ total }} 条）</span>
        </div>
      </template>

      <el-table
        :data="tableData"
        stripe
        border
        size="small"
        :loading="loading"
        style="width: 100%"
      >
        <el-table-column prop="bank_account" label="账号" min-width="150" />
        <el-table-column prop="bank_account_holder" label="户名" width="120" />
        <el-table-column prop="amount" label="金额" width="120">
          <template #default="{ row }">
            ¥{{ formatNumber(row.amount) }}
          </template>
        </el-table-column>
        <el-table-column prop="bank_name" label="开户行" min-width="150" />
        <el-table-column prop="bank_province" label="开户地" width="120" />
        <el-table-column prop="remittance_remark" label="汇款备注" min-width="150" />
      </el-table>

      <!-- 分页 -->
      <el-pagination
        v-model:current-page="currentPage"
        v-model:page-size="pageSize"
        :page-sizes="[10, 20, 50, 100]"
        :total="total"
        layout="total, sizes, prev, pager, next, jumper"
        style="margin-top: 20px; text-align: right"
        @change="handlePageChange"
      />
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Refresh, Download } from '@element-plus/icons-vue'
import { useAccountSetStore } from '@/stores/accountSet'
import { getProjects } from '@/api/projects'
import {
  getSalaryPaymentRecords,
  exportSalaryPaymentRecords
} from '@/api/salaryPaymentRecords'

const accountSetStore = useAccountSetStore()

// 状态
const loading = ref(false)
const exportLoading = ref(false)
const currentPage = ref(1)
const pageSize = ref(20)
const total = ref(0)
const tableData = ref([])
const projects = ref([])

// 搜索表单
const searchForm = reactive({
  project_id: null,
  month: null
})

// 格式化数字（千位分隔符）
const formatNumber = (num) => {
  if (!num) return '0.00'
  return parseFloat(num).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
}

// 加载项目列表
const loadProjects = async () => {
  try {
    const params = {}
    if (accountSetStore.currentAccountSetId) {
      params.current_account_set_id = accountSetStore.currentAccountSetId
    }
    const res = await getProjects(params)
    // 处理分页响应
    projects.value = res.data?.data || res.data || []
  } catch (error) {
    console.error('加载项目失败:', error)
  }
}

// 加载发工资表数据
const loadSalaryPaymentRecords = async () => {
  loading.value = true
  try {
    const res = await getSalaryPaymentRecords({
      current_account_set_id: accountSetStore.currentAccountSetId,
      project_id: searchForm.project_id,
      month: searchForm.month,
      page: currentPage.value,
      per_page: pageSize.value
    })

    if (res.success) {
      tableData.value = res.data.data || []
      total.value = res.data.total || 0
    }
  } catch (error) {
    console.error('加载发工资表失败:', error)
    ElMessage.error('加载数据失败')
  } finally {
    loading.value = false
  }
}

// 查询
const handleSearch = () => {
  currentPage.value = 1
  loadSalaryPaymentRecords()
}

// 重置
const handleReset = () => {
  searchForm.project_id = null
  searchForm.month = null
  currentPage.value = 1
  loadSalaryPaymentRecords()
}

// 分页变化
const handlePageChange = () => {
  loadSalaryPaymentRecords()
}

// 导出 Excel
const handleExport = async () => {
  if (tableData.value.length === 0) {
    ElMessage.warning('没有数据可导出')
    return
  }

  exportLoading.value = true
  try {
    const res = await exportSalaryPaymentRecords({
      current_account_set_id: accountSetStore.currentAccountSetId,
      project_id: searchForm.project_id,
      month: searchForm.month
    })

    if (res.success) {
      // 使用 XLSX 库生成 Excel 文件
      const XLSX = await import('xlsx')

      // 准备表头和数据
      const headers = res.headers
      const data = res.data

      // 创建工作表
      const ws = XLSX.utils.aoa_to_sheet([headers, ...data])

      // 设置列宽
      const colWidths = headers.map(() => ({ wch: 18 }))
      ws['!cols'] = colWidths

      // 创建工作簿
      const wb = XLSX.utils.book_new()
      XLSX.utils.book_append_sheet(wb, ws, '发工资表')

      // 导出文件
      XLSX.writeFile(wb, res.filename)

      ElMessage.success('导出成功')
    }
  } catch (error) {
    console.error('导出失败:', error)
    ElMessage.error(error.response?.data?.message || '导出失败')
  } finally {
    exportLoading.value = false
  }
}

// 初始化
onMounted(() => {
  loadProjects()
  loadSalaryPaymentRecords()
})
</script>

<style scoped>
.salary-payment-records-container {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h1 {
  font-size: 24px;
  font-weight: bold;
  color: #333;
}

.search-card {
  margin-bottom: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.search-form {
  display: flex;
  align-items: flex-end;
  gap: 10px;
}

.table-card {
  margin-bottom: 20px;
}

:deep(.el-form-item) {
  margin-bottom: 0;
}

:deep(.el-table) {
  font-size: 12px;
}
</style>
