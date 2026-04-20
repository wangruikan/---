<template>
  <div class="payment-summary-container">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>出款汇总</span>
        </div>
      </template>

      <!-- 筛选表单 -->
      <el-form :inline="true" :model="filterForm" class="filter-form">
        <el-form-item label="月份">
          <el-date-picker
            v-model="filterForm.month"
            type="month"
            placeholder="选择月份"
            format="YYYY-MM"
            value-format="YYYY-MM"
            clearable
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
          <el-button :icon="Refresh" @click="handleReset">重置</el-button>
          <el-button type="success" :icon="Download" @click="handleExport" :loading="exporting">
            导出Excel
          </el-button>
        </el-form-item>
      </el-form>

      <!-- 数据表格 -->
      <el-table :data="tableData" v-loading="loading" border stripe>
        <el-table-column type="index" label="序号" width="60" align="center" />
        <el-table-column prop="payment_type" label="付款类型" width="120">
          <template #default="{ row }">
            <el-tag :type="getPaymentTypeTagType(row.payment_type)" size="small">
              {{ getPaymentTypeName(row.payment_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="category" label="类目" width="100" align="center" />
        <el-table-column prop="month" label="月份" width="100" align="center" />
        <el-table-column prop="project" label="项目" width="150" show-overflow-tooltip />
        <el-table-column prop="apply_date" label="申请日期" width="110" align="center">
          <template #default="{ row }">
            {{ row.apply_date ? formatDate(row.apply_date) : '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="unit_name" label="单位名称" width="150" show-overflow-tooltip />
        <el-table-column prop="invoice_number" label="发票号码" width="120" />
        <el-table-column prop="payment_date" label="打款日期" width="110" align="center">
          <template #default="{ row }">
            {{ row.payment_date ? formatDate(row.payment_date) : '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="expenditure_amount" label="支出金额" width="120" align="right">
          <template #default="{ row }">
            <span v-if="row.expenditure_amount">
              ¥{{ Number(row.expenditure_amount).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
            </span>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="summary" label="摘要" min-width="200" show-overflow-tooltip />
        <el-table-column prop="invoice_type" label="发票类型" width="100" />
        <el-table-column prop="invoice_amount" label="开票金额" width="120" align="right">
          <template #default="{ row }">
            <span v-if="row.invoice_amount">
              ¥{{ Number(row.invoice_amount).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
            </span>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="tax_rate" label="税率" width="80" />
        <el-table-column prop="deduction_amount" label="扣除额" width="120" align="right">
          <template #default="{ row }">
            <span v-if="row.deduction_amount">
              ¥{{ Number(row.deduction_amount).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
            </span>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="amount_excluding_tax" label="不含税金额" width="120" align="right">
          <template #default="{ row }">
            <span v-if="row.amount_excluding_tax">
              ¥{{ Number(row.amount_excluding_tax).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
            </span>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="tax_amount" label="税金" width="120" align="right">
          <template #default="{ row }">
            <span v-if="row.tax_amount">
              ¥{{ Number(row.tax_amount).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
            </span>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="reimburser" label="报销人" width="100" />
        <el-table-column prop="amount" label="付款金额" width="120" align="right">
          <template #default="{ row }">
            <span style="color: #f56c6c; font-weight: bold">
              ¥{{ Number(row.amount).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="approved_at" label="审批通过时间" width="160" align="center">
          <template #default="{ row }">
            {{ row.approved_at ? formatDateTime(row.approved_at) : '-' }}
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-container">
        <el-pagination
          v-model:current-page="pagination.current"
          v-model:page-size="pagination.pageSize"
          :total="pagination.total"
          :page-sizes="[15, 30, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleSizeChange"
          @current-change="handlePageChange"
        />
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Search, Refresh, Download } from '@element-plus/icons-vue'
import { useAccountSetStore } from '@/stores/accountSet'
import { getPaymentSummaries } from '@/api/paymentSummary'
import * as XLSX from 'xlsx'

const accountSetStore = useAccountSetStore()

// 筛选表单
const filterForm = reactive({
  month: ''
})

// 表格数据
const tableData = ref([])
const loading = ref(false)
const exporting = ref(false)

// 分页
const pagination = reactive({
  current: 1,
  pageSize: 15,
  total: 0
})

// 获取列表
const loadData = async () => {
  if (!accountSetStore.currentAccountSetId) {
    ElMessage.warning('请先选择账套')
    return
  }

  try {
    loading.value = true
    const params = {
      current_account_set_id: accountSetStore.currentAccountSetId,
      month: filterForm.month || undefined
    }

    console.log('请求参数:', params)
    const response = await getPaymentSummaries(params)
    console.log('响应数据:', response)
    
    if (response && response.success) {
      tableData.value = response.data || []
      pagination.total = tableData.value.length
      if (tableData.value.length === 0) {
        ElMessage.info('暂无数据')
      }
    } else {
      ElMessage.error(response?.message || '获取数据失败')
    }
  } catch (error) {
    console.error('Load data error:', error)
    console.error('Error response:', error.response)
    ElMessage.error(error.response?.data?.message || error.message || '获取数据失败')
  } finally {
    loading.value = false
  }
}

// 查询
const handleSearch = () => {
  pagination.current = 1
  loadData()
}

// 重置
const handleReset = () => {
  filterForm.month = ''
  pagination.current = 1
  loadData()
}

// 导出Excel（纯前端实现，参考发票申请）
const handleExport = async () => {
  if (!accountSetStore.currentAccountSetId) {
    ElMessage.warning('请先选择账套')
    return
  }

  try {
    exporting.value = true
    
    // 获取当前显示的表格数据
    const exportData = tableData.value
    
    if (!exportData || exportData.length === 0) {
      ElMessage.warning('没有数据可导出')
      exporting.value = false
      return
    }
    
    // 准备Excel数据
    const accountSetName = accountSetStore.currentAccountSet?.name || '汇邦人力'
    const month = filterForm.month || new Date().toISOString().slice(0, 7)
    const title = `${accountSetName}${month}出款汇总表`
    
    // 表头（移除：付款类型、申请日期、付款金额、审批通过时间、项目名称）
    const headers = [
      '序号', '类目', '月份', '项目', '单位名称', '发票号码',
      '查验', '打款日期', '支出金额', '摘要', '收到发票',
      '发票类型', '开票金额', '税率', '扣除额', '不含税金额', '税金',
      '是否一致', '状态', '勾选月份', '报销人', '开票日期', '入账', '公司'
    ]
    
    // 数据行（移除：付款类型、申请日期、付款金额、审批通过时间、项目名称）
    const dataRows = exportData.map((item, index) => {
      return [
        index + 1, // 序号
        item.category || '', // 类目
        item.month || '', // 月份
        item.project || '', // 项目
        item.unit_name || '', // 单位名称
        item.invoice_number || '', // 发票号码
        item.verified ? '已查验' : '', // 查验
        item.payment_date ? formatDate(item.payment_date) : '', // 打款日期
        item.expenditure_amount || '', // 支出金额
        item.summary || '', // 摘要
        item.invoice_received ? '已收到' : '', // 收到发票
        item.invoice_type || '', // 发票类型
        item.invoice_amount || '', // 开票金额
        item.tax_rate || '', // 税率
        item.deduction_amount || '', // 扣除额
        item.amount_excluding_tax || '', // 不含税金额
        item.tax_amount || '', // 税金
        item.is_consistent ? '一致' : '', // 是否一致
        item.status_checked ? '已确认' : '', // 状态
        item.selected_month || '', // 勾选月份
        item.reimburser || '', // 报销人
        item.invoice_date ? formatDate(item.invoice_date) : '', // 开票日期
        item.accounted ? '已入账' : '', // 入账
        item.company || '' // 公司
      ]
    })
    
    // 创建工作表数据（直接使用表头和数据，不包含标题）
    const wsData = [
      headers, // 第1行：表头
      ...dataRows // 第2行起：数据
    ]
    
    // 创建工作表
    const ws = XLSX.utils.aoa_to_sheet(wsData)
    
    // 设置列宽（移除了项目名称列）
    ws['!cols'] = [
      { wch: 6 },   // A: 序号
      { wch: 10 },  // B: 类目
      { wch: 10 },  // C: 月份
      { wch: 15 },  // D: 项目
      { wch: 20 },  // E: 单位名称
      { wch: 15 },  // F: 发票号码
      { wch: 8 },   // G: 查验
      { wch: 12 },  // H: 打款日期
      { wch: 12 },  // I: 支出金额
      { wch: 20 },  // J: 摘要
      { wch: 10 },  // K: 收到发票
      { wch: 10 },  // L: 发票类型
      { wch: 12 },  // M: 开票金额
      { wch: 8 },   // N: 税率
      { wch: 12 },  // O: 扣除额
      { wch: 12 },  // P: 不含税金额
      { wch: 12 },  // Q: 税金
      { wch: 10 },  // R: 是否一致
      { wch: 10 },  // S: 状态
      { wch: 10 },  // T: 勾选月份
      { wch: 10 },  // U: 报销人
      { wch: 12 },  // V: 开票日期
      { wch: 8 },   // W: 入账
      { wch: 20 }   // X: 公司
    ]
    
    // 创建工作簿
    const wb = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(wb, ws, '出款汇总')
    
    // 导出文件
    const filename = `出款汇总_${month}_${Date.now()}.xlsx`
    XLSX.writeFile(wb, filename)
    
    ElMessage.success('导出成功')
  } catch (error) {
    console.error('导出失败', error)
    ElMessage.error('导出失败')
  } finally {
    exporting.value = false
  }
}

// 分页变化
const handleSizeChange = () => {
  loadData()
}

const handlePageChange = () => {
  loadData()
}

// 格式化日期
const formatDate = (date) => {
  if (!date) return '-'
  if (typeof date === 'string') {
    return date.split('T')[0]
  }
  return new Date(date).toISOString().split('T')[0]
}

// 格式化日期时间
const formatDateTime = (datetime) => {
  if (!datetime) return '-'
  const date = new Date(datetime)
  return date.toLocaleString('zh-CN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  })
}

// 获取付款类型名称
const getPaymentTypeName = (type) => {
  const map = {
    'salary': '工资付款',
    'insurance': '保险付款',
    'reimbursement': '报销付款',
    '报销': '报销付款',
    '差旅': '差旅报销付款',
    '采购': '采购报销付款',
    '项目': '项目报销付款',
    '其他': '其他报销付款',
  }
  return map[type] || type
}

// 获取付款类型标签类型
const getPaymentTypeTagType = (type) => {
  if (type === 'salary') return 'primary'
  if (type === 'insurance') return 'success'
  return 'warning'
}

// 初始化
onMounted(() => {
  loadData()
})
</script>

<style scoped>
.payment-summary-container {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.filter-form {
  margin-bottom: 20px;
}

.pagination-container {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
</style>

