<template>
  <div class="invoice-summary-page">
    <div class="page-header">
      <h1>发票汇总</h1>
      <el-button type="success" :icon="Download" @click="handleExport" :loading="exporting">
        导出Excel
      </el-button>
    </div>

    <!-- 筛选条件 -->
    <el-card shadow="never" style="margin-bottom: 20px;">
      <el-form :inline="true" :model="searchForm" class="search-form">
        <el-form-item label="申请日期">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
            clearable
          />
        </el-form-item>

        <el-form-item label="所属期">
          <el-date-picker
            v-model="searchForm.period"
            type="month"
            placeholder="选择月份"
            format="YYYY-MM"
            value-format="YYYY-MM"
            clearable
          />
        </el-form-item>

        <el-form-item label="项目名称">
          <el-input
            v-model="searchForm.project_name"
            placeholder="请输入项目名称"
            clearable
            style="width: 200px;"
          />
        </el-form-item>

        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="请选择状态" clearable style="width: 150px;">
            <el-option label="全部" value="" />
            <el-option label="待开票" value="pending" />
            <el-option label="已完成" value="completed" />
          </el-select>
        </el-form-item>

        <el-form-item>
          <el-button type="primary" @click="handleSearch">
            <el-icon><Search /></el-icon> 查询
          </el-button>
          <el-button @click="handleReset">
            <el-icon><Refresh /></el-icon> 重置
          </el-button>
          <el-button type="success" @click="handleExport">
            <el-icon><Download /></el-icon> 导出Excel
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 数据表格 -->
    <el-card shadow="never">
      <el-table
        :data="tableData"
        v-loading="loading"
        border
        stripe
        style="width: 100%"
      >
        <el-table-column type="index" label="序号" width="60" align="center" />
        <el-table-column prop="period" label="所属期" width="100" align="center" />
        <el-table-column prop="unit_name" label="单位名称" min-width="150" show-overflow-tooltip />
        <el-table-column prop="apply_date" label="申请日期" width="110" align="center" />
        <el-table-column prop="invoice_method" label="开票方式" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="getInvoiceMethodType(row.invoice_method)" size="small">
              {{ getInvoiceMethodText(row.invoice_method) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="invoice_type" label="开票种类" width="120" show-overflow-tooltip />
        <el-table-column prop="status" label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 'completed' ? 'success' : 'warning'" size="small">
              {{ row.status === 'completed' ? '已完成' : '待开票' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="project_name" label="项目名称" min-width="120" show-overflow-tooltip />
        <el-table-column prop="invoice_amount" label="开票金额" width="120" align="right">
          <template #default="{ row }">
            {{ formatMoney(row.invoice_amount) }}
          </template>
        </el-table-column>
        <el-table-column prop="deduction_amount" label="扣除额" width="110" align="right">
          <template #default="{ row }">
            {{ formatMoney(row.deduction_amount) }}
          </template>
        </el-table-column>
        <el-table-column prop="tax_rate" label="税率" width="80" align="center">
          <template #default="{ row }">
            {{ (row.tax_rate * 100).toFixed(2) }}%
          </template>
        </el-table-column>
        <el-table-column prop="amount_without_tax" label="不含税金额" width="120" align="right">
          <template #default="{ row }">
            {{ formatMoney(row.amount_without_tax) }}
          </template>
        </el-table-column>
        <el-table-column prop="invoice_tax" label="开票税金" width="110" align="right">
          <template #default="{ row }">
            {{ formatMoney(row.invoice_tax) }}
          </template>
        </el-table-column>
        <el-table-column prop="tax_amount" label="税金" width="110" align="right">
          <template #default="{ row }">
            {{ formatMoney(row.tax_amount) }}
          </template>
        </el-table-column>
        <el-table-column prop="invoice_date" label="开票日期" width="110" align="center">
          <template #default="{ row }">
            {{ row.invoice_date || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="is_completed" label="是否完成" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="row.is_completed ? 'success' : 'info'" size="small">
              {{ row.is_completed ? '是' : '否' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="invoicer" label="开票人" width="100" show-overflow-tooltip />
        <el-table-column prop="invoice_number" label="发票号码" width="150" show-overflow-tooltip />
        <el-table-column prop="remarks" label="备注" min-width="150" show-overflow-tooltip />
        <el-table-column label="操作" width="150" fixed="right" align="center">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="handleEdit(row)">
              编辑
            </el-button>
            <el-button 
              v-if="!row.is_completed" 
              type="success" 
              link 
              size="small" 
              @click="handleMarkCompleted(row)"
            >
              标记完成
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-container">
        <el-pagination
          v-model:current-page="pagination.current_page"
          v-model:page-size="pagination.per_page"
          :total="pagination.total"
          :page-sizes="[20, 50, 100, 200]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleSearch"
          @current-change="handleSearch"
        />
      </div>
    </el-card>

    <!-- 编辑对话框 -->
    <el-dialog
      v-model="editDialogVisible"
      title="编辑发票汇总"
      width="600px"
    >
      <el-form :model="editForm" label-width="120px">
        <el-form-item label="开票日期">
          <el-date-picker
            v-model="editForm.invoice_date"
            type="date"
            placeholder="选择日期"
            value-format="YYYY-MM-DD"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="开票人">
          <el-input v-model="editForm.invoicer" placeholder="请输入开票人" />
        </el-form-item>
        <el-form-item label="发票号码">
          <el-input v-model="editForm.invoice_number" placeholder="请输入发票号码" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="editForm.status" style="width: 100%">
            <el-option label="待开票" value="pending" />
            <el-option label="已完成" value="completed" />
          </el-select>
        </el-form-item>
        <el-form-item label="是否完成">
          <el-switch v-model="editForm.is_completed" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input
            v-model="editForm.remarks"
            type="textarea"
            :rows="3"
            placeholder="请输入备注"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSaveEdit">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Refresh, Download } from '@element-plus/icons-vue'
import request from '@/api/request'
import * as XLSX from 'xlsx'
import { useAccountSetStore } from '@/stores/accountSet'

const accountSetStore = useAccountSetStore()
const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)

const exporting = ref(false)
const loading = ref(false)
const tableData = ref([])
const dateRange = ref([])

const searchForm = reactive({
  period: '',
  project_name: '',
  status: ''
})

const pagination = reactive({
  current_page: 1,
  per_page: 50,
  total: 0
})

const editDialogVisible = ref(false)
const editForm = reactive({
  id: null,
  invoice_date: '',
  invoicer: '',
  invoice_number: '',
  status: 'pending',
  is_completed: false,
  remarks: ''
})

// 格式化金额
const formatMoney = (amount) => {
  if (!amount) return '0.00'
  return Number(amount).toLocaleString('zh-CN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

// 获取开票方式类型
const getInvoiceMethodType = (method) => {
  const types = {
    'full': 'success',
    'diff': 'warning',
    'none': 'info'
  }
  return types[method] || 'info'
}

// 获取开票方式文本
const getInvoiceMethodText = (method) => {
  const texts = {
    'full': '全额',
    'diff': '差额',
    'none': '无'
  }
  return texts[method] || method
}

// 加载数据
const loadData = async () => {
  try {
    loading.value = true
    
    const params = {
      page: pagination.current_page,
      per_page: pagination.per_page,
      current_account_set_id: currentAccountSetId.value,
      ...searchForm
    }
    
    if (dateRange.value && dateRange.value.length === 2) {
      params.start_date = dateRange.value[0]
      params.end_date = dateRange.value[1]
    }
    
    const response = await request({
      url: '/invoice-summaries',
      method: 'get',
      params
    })
    
    tableData.value = response.data.data
    pagination.total = response.data.total
    pagination.current_page = response.data.current_page
  } catch (error) {
    console.error('加载失败:', error)
    ElMessage.error('加载失败')
  } finally {
    loading.value = false
  }
}

// 查询
const handleSearch = () => {
  pagination.current_page = 1
  loadData()
}

// 重置
const handleReset = () => {
  dateRange.value = []
  searchForm.period = ''
  searchForm.project_name = ''
  searchForm.status = ''
  handleSearch()
}

// 导出Excel（纯前端实现）
const handleExport = async () => {
  try {
    exporting.value = true
    
    // 获取当前筛选条件的所有数据
    const params = {
      period: searchForm.period,
      project_name: searchForm.project_name,
      status: searchForm.status,
      per_page: 9999 // 获取所有数据
    }
    
    if (dateRange.value && dateRange.value.length === 2) {
      params.start_date = dateRange.value[0]
      params.end_date = dateRange.value[1]
    }
    
    const response = await request({
      url: '/invoice-summaries',
      method: 'get',
      params
    })
    
    const exportData = response.data.data || []
    
    if (exportData.length === 0) {
      ElMessage.warning('没有数据可导出')
      return
    }
    
    // 表头
    const headers = [
      '序号', '所属期', '单位名称', '申请日期', '开票方式', '开票种类', '状态',
      '项目名称', '开票金额', '扣除额', '税率', '不含税金额', '开票税金', '税金',
      '开票日期', '是否完成', '开票人', '发票号码', '备注'
    ]
    
    // 数据行
    const dataRows = exportData.map((item, index) => {
      return [
        index + 1,
        item.period || '',
        item.unit_name || '',
        item.apply_date || '',
        getInvoiceMethodText(item.invoice_method),
        item.invoice_type || '',
        item.status === 'completed' ? '已完成' : '待开票',
        item.project_name || '',
        item.invoice_amount || '',
        item.deduction_amount || '',
        item.tax_rate ? (item.tax_rate * 100).toFixed(2) + '%' : '',
        item.amount_without_tax || '',
        item.invoice_tax || '',
        item.tax_amount || '',
        item.invoice_date || '',
        item.is_completed ? '是' : '否',
        item.invoicer || '',
        item.invoice_number || '',
        item.remarks || ''
      ]
    })
    
    // 创建工作表数据
    const wsData = [headers, ...dataRows]
    
    // 创建工作表
    const ws = XLSX.utils.aoa_to_sheet(wsData)
    
    // 设置列宽
    ws['!cols'] = [
      { wch: 6 },   // 序号
      { wch: 10 },  // 所属期
      { wch: 20 },  // 单位名称
      { wch: 12 },  // 申请日期
      { wch: 10 },  // 开票方式
      { wch: 15 },  // 开票种类
      { wch: 10 },  // 状态
      { wch: 15 },  // 项目名称
      { wch: 12 },  // 开票金额
      { wch: 12 },  // 扣除额
      { wch: 10 },  // 税率
      { wch: 12 },  // 不含税金额
      { wch: 12 },  // 开票税金
      { wch: 12 },  // 税金
      { wch: 12 },  // 开票日期
      { wch: 10 },  // 是否完成
      { wch: 10 },  // 开票人
      { wch: 18 },  // 发票号码
      { wch: 20 }   // 备注
    ]
    
    // 创建工作簿
    const wb = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(wb, ws, '发票汇总')
    
    // 导出文件
    const filename = `发票汇总_${new Date().getTime()}.xlsx`
    XLSX.writeFile(wb, filename)
    
    ElMessage.success('导出成功')
  } catch (error) {
    console.error('导出失败:', error)
    ElMessage.error('导出失败')
  } finally {
    exporting.value = false
  }
}

// 编辑
const handleEdit = (row) => {
  editForm.id = row.id
  editForm.invoice_date = row.invoice_date
  editForm.invoicer = row.invoicer
  editForm.invoice_number = row.invoice_number
  editForm.status = row.status
  editForm.is_completed = row.is_completed
  editForm.remarks = row.remarks
  editDialogVisible.value = true
}

// 保存编辑
const handleSaveEdit = async () => {
  try {
    await request({
      url: `/invoice-summaries/${editForm.id}`,
      method: 'put',
      data: editForm
    })
    
    ElMessage.success('保存成功')
    editDialogVisible.value = false
    loadData()
  } catch (error) {
    console.error('保存失败:', error)
    ElMessage.error('保存失败')
  }
}

// 标记为已完成
const handleMarkCompleted = async (row) => {
  try {
    await ElMessageBox.prompt('请输入发票号码', '标记为已完成', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      inputPattern: /.+/,
      inputErrorMessage: '请输入发票号码'
    }).then(async ({ value }) => {
      await request({
        url: `/invoice-summaries/${row.id}/mark-completed`,
        method: 'post',
        data: {
          invoice_number: value,
          invoice_date: new Date().toISOString().split('T')[0]
        }
      })
      
      ElMessage.success('已标记为完成')
      loadData()
    })
  } catch (error) {
    if (error !== 'cancel') {
      console.error('操作失败:', error)
      ElMessage.error('操作失败')
    }
  }
}

onMounted(() => {
  loadData()
})

// 监听账套切换，自动刷新数据
watch(() => accountSetStore.currentAccountSetId, (newAccountSetId, oldAccountSetId) => {
  console.log('发票汇总页-账套变化检测:', { new: newAccountSetId, old: oldAccountSetId })
  if (newAccountSetId && oldAccountSetId && newAccountSetId !== oldAccountSetId) {
    console.log('✅ 发票汇总页-账套切换，重新加载数据:', newAccountSetId)
    pagination.current_page = 1
    loadData()
  }
})

</script>

<style scoped>
.invoice-summary-page {
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
  font-weight: 500;
  margin: 0;
}

.search-form {
  margin-bottom: 0;
}

.pagination-container {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
</style>
