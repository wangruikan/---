<template>
  <div class="salary-summaries-page">
    <NoAccountSetTip v-if="!isAdmin && !currentAccountSetId" />
    <div v-else>
      <div class="page-header">
        <h1>工资汇总</h1>
      </div>

      <!-- 筛选条件 -->
      <el-card shadow="never" style="margin-bottom: 20px;">
        <el-form :inline="true" :model="searchForm" class="search-form">
          <el-form-item label="工资期间">
            <el-date-picker
              v-model="searchForm.month"
              type="month"
              placeholder="选择月份"
              format="YYYY-MM"
              value-format="YYYY-MM"
              clearable
            />
          </el-form-item>

          <el-form-item label="项目">
            <el-select v-model="searchForm.project_id" placeholder="请选择项目" clearable filterable>
              <el-option
                v-for="proj in projectList"
                :key="proj.id"
                :label="proj.name"
                :value="proj.id"
              />
            </el-select>
          </el-form-item>

          <el-form-item>
            <el-button type="primary" @click="handleSearch">查询</el-button>
            <el-button @click="handleReset">重置</el-button>
          </el-form-item>
        </el-form>
      </el-card>

      <!-- 工资汇总列表 - 完全按照工资明细表格式 -->
      <el-card shadow="never">
        <template #header>
          <div class="card-header">
            <span class="title">工资汇总列表</span>
            <div>
              <el-button type="text" @click="handleSearch">
                <el-icon><Refresh /></el-icon>
                刷新
              </el-button>
              <el-button type="primary" @click="handlePrint">
                <el-icon><Printer /></el-icon>
                打印
              </el-button>
            </div>
          </div>
        </template>

        <div style="overflow-x: auto;">
          <el-table
            :data="tableData"
            border
            stripe
            v-loading="loading"
            style="width: 100%; font-size: 12px;"
          >
            <!-- 基础信息 -->
            <el-table-column type="index" label="序号" width="60" align="center" fixed="left" />
            <el-table-column prop="project_name" label="单位" width="150" show-overflow-tooltip fixed="left" />
            <el-table-column label="社保" width="100" align="center">
              <template #default="scope">
                <span v-if="scope.row.insurance_import_setting === 'current'">当月</span>
                <span v-else-if="scope.row.insurance_import_setting === 'next'">次月</span>
                <span v-else-if="scope.row.insurance_import_setting === 'none'">不导入</span>
                <span v-else>-</span>
              </template>
            </el-table-column>
            <el-table-column prop="social_security_location" label="参保地" width="100" align="center">
              <template #default="scope">{{ scope.row.social_security_location || '-' }}</template>
            </el-table-column>
            <el-table-column label="状态" width="100" align="center">
              <template #default="scope">
                <span v-if="scope.row.requires_salary_basis">
                  <el-tag :type="scope.row.salary_basis_uploaded ? 'success' : 'danger'" size="small">
                    {{ scope.row.salary_basis_uploaded ? '已上传' : '未上传' }}
                  </el-tag>
                </span>
                <span v-else>-</span>
              </template>
            </el-table-column>
            <el-table-column label="所属期" width="80" align="center">
              <template #default="scope">
                {{ getMonthDisplay(scope.row.month) }}
              </template>
            </el-table-column>
            <el-table-column label="人数" width="60" align="center">
              <template #default="scope">{{ scope.row.employee_count }}</template>
            </el-table-column>
            <el-table-column label="发薪日" width="100" align="center">
              <template #default="scope">
                {{ getSalaryPaymentDate(scope.row.month, scope.row.salary_payment_day) }}
              </template>
            </el-table-column>
            <el-table-column label="类别" width="80" align="center">
              <template #default>工资</template>
            </el-table-column>
            <el-table-column label="形式" width="80" align="center">
              <template #default>现金</template>
            </el-table-column>
            
            <!-- 个人社保 -->
            <el-table-column label="个人社保" align="center">
              <el-table-column label="社保" width="100" align="right">
                <template #default="scope">
                  {{ formatMoney((scope.row.total_pension_personal || 0) + (scope.row.total_unemployment_personal || 0)) }}
                </template>
              </el-table-column>
              <el-table-column label="医保" width="100" align="right">
                <template #default="scope">
                  {{ formatMoney(scope.row.total_medical_personal || 0) }}
                </template>
              </el-table-column>
              <el-table-column label="大额" width="100" align="right">
                <template #default="scope">
                  {{ formatMoney(scope.row.total_large_medical_personal || 0) }}
                </template>
              </el-table-column>
            </el-table-column>
            
            <!-- 公积金 -->
            <el-table-column label="公积金" align="center">
              <el-table-column label="个人" width="100" align="right">
                <template #default="scope">
                  {{ formatMoney(scope.row.total_housing_fund_personal || 0) }}
                </template>
              </el-table-column>
            </el-table-column>
            
            <!-- 其他扣除 -->
            <el-table-column label="其他扣除" width="100" align="right">
              <template #default="scope">
                {{ formatMoney((scope.row.total_work_injury_company || 0) + (scope.row.total_maternity_company || 0)) }}
              </template>
            </el-table-column>
            
            <!-- 完结 -->
            <el-table-column label="完结" width="80" align="center">
              <template #default>
                <span style="color: #67C23A; font-size: 18px;">✓</span>
              </template>
            </el-table-column>
            
            <!-- 审核 -->
            <el-table-column label="审核" width="80" align="center">
              <template #default>
                <span style="color: #67C23A; font-size: 18px;">✓</span>
              </template>
            </el-table-column>
            <el-table-column label="应发工资" width="120" align="right">
              <template #default="scope">
                <span style="color: #409EFF; font-weight: bold;">{{ formatMoney(scope.row.total_gross_salary) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="实发工资" width="120" align="right">
              <template #default="scope">
                <span style="color: #67C23A; font-weight: bold;">{{ formatMoney(scope.row.total_net_salary) }}</span>
              </template>
            </el-table-column>
            
            <!-- 扣除和调整项 -->
            <el-table-column label="扣除个税" width="100" align="right">
              <template #default="scope">
                {{ formatMoney(scope.row.total_tax_payable_or_refundable || 0) }}
              </template>
            </el-table-column>
            <el-table-column label="实际个税" width="100" align="right">
              <template #default="scope">
                {{ formatMoney(scope.row.total_tax_payable_or_refundable || 0) }}
              </template>
            </el-table-column>
            <el-table-column label="往期调整" width="100" align="right">
              <template #default>{{ formatMoney(0) }}</template>
            </el-table-column>
            
            <!-- 实际发放 -->
            <el-table-column label="实际发放" width="120" align="right">
              <template #default="scope">
                <span style="color: #67C23A; font-weight: bold;">{{ formatMoney(scope.row.total_net_salary || 0) }}</span>
              </template>
            </el-table-column>
            
            <!-- 差额 -->
            <el-table-column label="差额" width="100" align="right">
              <template #default>{{ formatMoney(0) }}</template>
            </el-table-column>
            
            <!-- 发放日期 -->
            <el-table-column prop="approved_at" label="发放日期" width="160" align="center">
              <template #default="scope">
                {{ formatDate(scope.row.approved_at) }}
              </template>
            </el-table-column>
            
            <!-- 备注 -->
            <el-table-column label="备注" width="120" align="center">
              <template #default>-</template>
            </el-table-column>
            
            <!-- 未调整统计/补发 -->
            <el-table-column label="未调整统计/补发" width="120" align="center">
              <template #default>-</template>
            </el-table-column>
            
            <!-- 标注 -->
            <el-table-column label="标注" width="120" align="center">
              <template #default>-</template>
            </el-table-column>
            
            <!-- 操作 -->
            <el-table-column label="操作" width="100" fixed="right" align="center">
              <template #default="scope">
                <el-button link type="primary" size="small" @click="handleView(scope.row)">
                  查看详情
                </el-button>
              </template>
            </el-table-column>
          </el-table>
        </div>

        <!-- 分页 -->
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.pageSize"
          :total="pagination.total"
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleSearch"
          @current-change="handleSearch"
          style="margin-top: 20px; justify-content: flex-end;"
        />
      </el-card>

      <!-- 查看详情对话框 - 显示列表行的汇总数据 -->
      <el-dialog
        v-model="detailDialogVisible"
        width="95%"
        top="5vh"
      >
        <template #header>
          <span class="dialog-title">工资汇总详情 - {{ currentRow?.project_name }} ({{ currentRow?.month }})</span>
        </template>
        
        <div v-if="currentRow">
          <!-- 使用描述列表展示详情 -->
          <el-descriptions :column="2" border>
            <el-descriptions-item label="项目名称">{{ currentRow.project_name }}</el-descriptions-item>
            <el-descriptions-item label="工资期间">{{ currentRow.month }}</el-descriptions-item>
            <el-descriptions-item label="工资周期">
              <span v-if="currentRow.period_start && currentRow.period_end">
                {{ currentRow.period_start }} - {{ currentRow.period_end }}
              </span>
              <span v-else>-</span>
            </el-descriptions-item>
            <el-descriptions-item label="员工人数">{{ currentRow.employee_count }} 人</el-descriptions-item>
            <el-descriptions-item label="应发工资合计">
              <span style="color: #409EFF; font-weight: bold;">¥{{ formatMoney(currentRow.total_gross_salary) }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="实发工资合计">
              <span style="color: #67C23A; font-weight: bold;">¥{{ formatMoney(currentRow.total_net_salary) }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="单位保险合计">
              ¥{{ formatMoney(currentRow.total_company_insurance_total) }}
            </el-descriptions-item>
            <el-descriptions-item label="个人保险合计">
              <span style="color: #F56C6C;">¥{{ formatMoney(currentRow.total_personal_insurance_total) }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="个税合计">
              ¥{{ formatMoney(currentRow.total_tax_payable_or_refundable) }}
            </el-descriptions-item>
            <el-descriptions-item label="审批时间">
              {{ formatDate(currentRow.approved_at) }}
            </el-descriptions-item>
          </el-descriptions>
        </div>

        <template #footer>
          <el-button @click="detailDialogVisible = false">关闭</el-button>
        </template>
      </el-dialog>

      <!-- 打印设置对话框 -->
      <el-dialog
        v-model="printDialogVisible"
        title="打印设置"
        width="600px"
      >
        <div>
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <p style="margin: 0;">请选择要打印的列：</p>
            <div>
              <el-button link type="primary" size="small" @click="selectAllColumns">全选</el-button>
              <el-button link type="primary" size="small" @click="clearAllColumns">取消全选</el-button>
            </div>
          </div>
          <el-checkbox-group 
            v-model="selectedPrintColumns" 
            @change="savePrintColumns"
            style="display: flex; flex-direction: column; gap: 10px; max-height: 400px; overflow-y: auto;"
          >
            <el-checkbox 
              v-for="column in allColumns" 
              :key="column.key" 
              :label="column.key"
            >
              {{ column.label }}
            </el-checkbox>
          </el-checkbox-group>
          <div style="margin-top: 10px; color: #909399; font-size: 12px;">
            已选择 {{ selectedPrintColumns.length }} / {{ allColumns.length }} 列
          </div>
        </div>
        <template #footer>
          <el-button @click="printDialogVisible = false">取消</el-button>
          <el-button type="primary" @click="confirmPrint">确认打印</el-button>
        </template>
      </el-dialog>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { Refresh, Printer } from '@element-plus/icons-vue'
import { useUserStore } from '@/stores/user'
import { useAccountSetStore } from '@/stores/accountSet'
import NoAccountSetTip from '@/components/NoAccountSetTip.vue'
import { getSalarySummaries } from '@/api/salarySummary'
import { getProjects } from '@/api/projects'

const userStore = useUserStore()
const accountSetStore = useAccountSetStore()

const isAdmin = computed(() => userStore.userInfo?.role === 'admin')
const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)

// 搜索表单
const searchForm = reactive({
  month: null,
  project_id: null
})

// 分页
const pagination = reactive({
  page: 1,
  pageSize: 20,
  total: 0
})

// 表格数据
const tableData = ref([])
const loading = ref(false)

// 项目列表
const projectList = ref([])

// 详情对话框
const detailDialogVisible = ref(false)
const currentRow = ref(null)

// 打印相关
const printDialogVisible = ref(false)
const selectedPrintColumns = ref([])

// localStorage 键名
const PRINT_COLUMNS_STORAGE_KEY = 'salary_summaries_print_columns'

// 所有可打印的列
const allColumns = [
  { key: 'index', label: '序号' },
  { key: 'project_name', label: '单位' },
  { key: 'insurance_import_setting', label: '社保' },
  { key: 'social_security_location', label: '参保地' },
  { key: 'status', label: '状态' },
  { key: 'month', label: '所属期' },
  { key: 'employee_count', label: '人数' },
  { key: 'salary_payment_day', label: '发薪日' },
  { key: 'category', label: '类别' },
  { key: 'form', label: '形式' },
  { key: 'pension_personal', label: '个人社保-社保' },
  { key: 'medical_personal', label: '个人社保-医保' },
  { key: 'large_medical_personal', label: '个人社保-大额' },
  { key: 'housing_fund_personal', label: '公积金-个人' },
  { key: 'other_deduction', label: '其他扣除' },
  { key: 'finished', label: '完结' },
  { key: 'approved', label: '审核' },
  { key: 'total_gross_salary', label: '应发工资' },
  { key: 'total_net_salary', label: '实发工资' },
  { key: 'tax_deduction', label: '扣除个税' },
  { key: 'actual_tax', label: '实际个税' },
  { key: 'previous_adjustment', label: '往期调整' },
  { key: 'actual_payment', label: '实际发放' },
  { key: 'difference', label: '差额' },
  { key: 'payment_date', label: '发放日期' },
  { key: 'remarks', label: '备注' },
  { key: 'unadjusted_statistics', label: '未调整统计/补发' },
  { key: 'annotation', label: '标注' }
]

// 格式化金额
const formatMoney = (amount) => {
  if (amount === null || amount === undefined) {
    amount = 0
  }
  return '¥' + Number(amount).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// 格式化时间
const formatDateTime = (datetime) => {
  if (!datetime) return '-'
  const date = new Date(datetime)
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  return `${year}-${month}-${day} ${hours}:${minutes}`
}

// 格式化日期
const formatDate = (datetime) => {
  if (!datetime) return '-'
  const date = new Date(datetime)
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}${month}${day}`
}

// 获取月份显示（如：09月）
const getMonthDisplay = (month) => {
  if (!month) return '-'
  const parts = month.split('-')
  if (parts.length === 2) {
    return parts[1] + '月'
  }
  return month
}

// 获取发薪日期（格式：15日）
const getSalaryPaymentDate = (month, paymentDay) => {
  if (!month || !paymentDay) return '-'
  const day = String(paymentDay).padStart(2, '0')
  return `${day}日`
}

// 查询
const handleSearch = async () => {
  loading.value = true
  try {
    const params = {
      month: searchForm.month,
      project_id: searchForm.project_id,
      page: pagination.page,
      per_page: pagination.pageSize,
      current_account_set_id: accountSetStore.currentAccountSetId
    }
    const response = await getSalarySummaries(params)
    if (response && response.success) {
      tableData.value = response.data.data || []
      pagination.total = response.data.total || 0
      
      // 🔍 调试日志：检查前端接收到的数据
      if (tableData.value.length > 0) {
        const firstRow = tableData.value[0]
        console.log('========== 前端接收到的数据 ==========')
        console.log('ID:', firstRow.id)
        console.log('项目名称:', firstRow.project_name)
        console.log('养老保险个人:', firstRow.total_pension_personal, '类型:', typeof firstRow.total_pension_personal)
        console.log('医疗保险个人:', firstRow.total_medical_personal, '类型:', typeof firstRow.total_medical_personal)
        console.log('失业保险个人:', firstRow.total_unemployment_personal, '类型:', typeof firstRow.total_unemployment_personal)
        console.log('大额医疗个人:', firstRow.total_large_medical_personal, '类型:', typeof firstRow.total_large_medical_personal)
        console.log('计算测试:')
        const sum = (firstRow.total_pension_personal || 0) + (firstRow.total_medical_personal || 0) + (firstRow.total_unemployment_personal || 0)
        console.log('三项相加结果:', sum, '类型:', typeof sum)
        console.log('Number(sum):', Number(sum))
        console.log('formatMoney结果:', formatMoney(sum))
        console.log('完整第一条记录:', firstRow)
        console.log('=====================================')
      }
    }
  } catch (error) {
    console.error('Load salary summaries error:', error)
    ElMessage.error('加载工资汇总列表失败')
    tableData.value = []
    pagination.total = 0
  } finally {
    loading.value = false
  }
}

// 重置
const handleReset = () => {
  searchForm.month = null
  searchForm.project_id = null
  pagination.page = 1
  handleSearch()
}

// 查看详情
const handleView = (row) => {
  currentRow.value = row
  detailDialogVisible.value = true
}

// 打开打印设置对话框
const handlePrint = () => {
  // 从 localStorage 读取上次的选择
  const savedColumns = loadPrintColumns()
  if (savedColumns && savedColumns.length > 0) {
    // 验证保存的列是否仍然有效（防止列定义变更导致的问题）
    const validColumns = savedColumns.filter(key => 
      allColumns.some(col => col.key === key)
    )
    if (validColumns.length > 0) {
      selectedPrintColumns.value = validColumns
    } else {
      // 如果保存的列都无效，则使用默认全选
      selectedPrintColumns.value = allColumns.map(col => col.key)
    }
  } else {
    // 如果没有保存的选择，默认选中所有列
    selectedPrintColumns.value = allColumns.map(col => col.key)
  }
  printDialogVisible.value = true
}

// 从 localStorage 加载打印列选择
const loadPrintColumns = () => {
  try {
    const saved = localStorage.getItem(PRINT_COLUMNS_STORAGE_KEY)
    if (saved) {
      return JSON.parse(saved)
    }
  } catch (error) {
    console.error('加载打印列选择失败:', error)
  }
  return null
}

// 保存打印列选择到 localStorage
const savePrintColumns = () => {
  try {
    localStorage.setItem(PRINT_COLUMNS_STORAGE_KEY, JSON.stringify(selectedPrintColumns.value))
  } catch (error) {
    console.error('保存打印列选择失败:', error)
  }
}

// 全选所有列
const selectAllColumns = () => {
  selectedPrintColumns.value = allColumns.map(col => col.key)
  savePrintColumns() // 保存选择
}

// 取消全选
const clearAllColumns = () => {
  selectedPrintColumns.value = []
  savePrintColumns() // 保存选择
}

// 确认打印
const confirmPrint = () => {
  if (selectedPrintColumns.value.length === 0) {
    ElMessage.warning('请至少选择一列进行打印')
    return
  }

  printDialogVisible.value = false
  
  // 创建打印窗口
  const printWindow = window.open('', '_blank')
  if (!printWindow) {
    ElMessage.error('无法打开打印窗口，请检查浏览器弹窗设置')
    return
  }

  // 构建打印内容（优化为类似Excel打印效果）
  let printContent = `
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="UTF-8">
      <title>工资汇总打印</title>
      <style>
        * {
          margin: 0;
          padding: 0;
          box-sizing: border-box;
        }
        body {
          font-family: "Microsoft YaHei", Arial, sans-serif;
          font-size: 11px;
          color: #000;
        }
        .print-container {
          width: 100%;
        }
        h2 {
          text-align: center;
          margin: 10px 0 15px 0;
          font-size: 16px;
          font-weight: bold;
        }
        table {
          width: 100%;
          border-collapse: collapse;
          margin: 0 auto;
          table-layout: fixed;
        }
        th, td {
          border: 1px solid #000;
          padding: 4px 6px;
          text-align: center;
          vertical-align: middle;
          word-wrap: break-word;
          font-size: 11px;
        }
        th {
          background-color: #D9D9D9;
          font-weight: bold;
          height: 25px;
        }
        td {
          background-color: #fff;
          height: 22px;
        }
        tr:nth-child(even) td {
          background-color: #F2F2F2;
        }
        /* 打印样式优化 */
        @media print {
          @page {
            size: A4 landscape;
            margin: 1.5cm 1cm;
          }
          body {
            margin: 0;
            padding: 0;
          }
          .print-container {
            width: 100%;
          }
          table {
            page-break-inside: auto;
          }
          tr {
            page-break-inside: avoid;
            page-break-after: auto;
          }
          thead {
            display: table-header-group;
          }
          tfoot {
            display: table-footer-group;
          }
          /* 每页都显示表头 */
          thead tr {
            page-break-after: avoid;
            page-break-inside: avoid;
          }
        }
      </style>
    </head>
    <body>
      <div class="print-container">
        <h2>工资汇总表</h2>
        <table>
        <thead>
          <tr>
  `

  // 添加表头
  selectedPrintColumns.value.forEach(key => {
    const column = allColumns.find(col => col.key === key)
    if (column) {
      printContent += `<th>${column.label}</th>`
    }
  })

  printContent += `
          </tr>
        </thead>
        <tbody>
  `

  // 添加数据行
  tableData.value.forEach((row, index) => {
    printContent += '<tr>'
    selectedPrintColumns.value.forEach(key => {
      let value = ''
      switch (key) {
        case 'index':
          value = index + 1
          break
        case 'project_name':
          value = row.project_name || '-'
          break
        case 'insurance_import_setting':
          if (row.insurance_import_setting === 'current') value = '当月'
          else if (row.insurance_import_setting === 'next') value = '次月'
          else if (row.insurance_import_setting === 'none') value = '不导入'
          else value = '-'
          break
        case 'social_security_location':
          value = row.social_security_location || '-'
          break
        case 'status':
          value = row.salary_basis_uploaded ? '已上传' : (row.requires_salary_basis ? '未上传' : '-')
          break
        case 'month':
          value = getMonthDisplay(row.month)
          break
        case 'employee_count':
          value = row.employee_count || 0
          break
        case 'salary_payment_day':
          value = getSalaryPaymentDate(row.month, row.salary_payment_day)
          break
        case 'category':
          value = '工资'
          break
        case 'form':
          value = '现金'
          break
        case 'pension_personal':
          value = formatMoney((row.total_pension_personal || 0) + (row.total_unemployment_personal || 0))
          break
        case 'medical_personal':
          value = formatMoney(row.total_medical_personal || 0)
          break
        case 'large_medical_personal':
          value = formatMoney(row.total_large_medical_personal || 0)
          break
        case 'housing_fund_personal':
          value = formatMoney(row.total_housing_fund_personal || 0)
          break
        case 'other_deduction':
          value = formatMoney((row.total_work_injury_company || 0) + (row.total_maternity_company || 0))
          break
        case 'finished':
          value = '✓'
          break
        case 'approved':
          value = '✓'
          break
        case 'total_gross_salary':
          value = formatMoney(row.total_gross_salary)
          break
        case 'total_net_salary':
          value = formatMoney(row.total_net_salary)
          break
        case 'tax_deduction':
          value = formatMoney(row.total_tax_payable_or_refundable || 0)
          break
        case 'actual_tax':
          value = formatMoney(row.total_tax_payable_or_refundable || 0)
          break
        case 'previous_adjustment':
          value = formatMoney(0)
          break
        case 'actual_payment':
          value = formatMoney(row.total_net_salary || 0)
          break
        case 'difference':
          value = formatMoney(0)
          break
        case 'payment_date':
          value = formatDate(row.approved_at)
          break
        case 'remarks':
          value = '-'
          break
        case 'unadjusted_statistics':
          value = '-'
          break
        case 'annotation':
          value = '-'
          break
        default:
          value = '-'
      }
      printContent += `<td>${value}</td>`
    })
    printContent += '</tr>'
  })

  printContent += `
        </tbody>
      </table>
      </div>
    </body>
    </html>
  `

  // 写入内容并打印
  printWindow.document.write(printContent)
  printWindow.document.close()
  
  // 等待内容加载完成后打印
  setTimeout(() => {
    printWindow.print()
    // 打印后可以选择关闭窗口
    // printWindow.close()
  }, 500)
}

// 加载项目列表
const loadProjects = async () => {
  try {
    const response = await getProjects({
      current_account_set_id: accountSetStore.currentAccountSetId,
      all: true
    })
    if (response.success && response.data) {
      projectList.value = Array.isArray(response.data) 
        ? response.data 
        : (response.data.data || [])
    }
  } catch (error) {
    console.error('加载项目列表失败:', error)
  }
}

// 初始化
onMounted(() => {
  handleSearch()
  loadProjects()
})
</script>

<style scoped>
.salary-summaries-page {
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

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.title {
  font-size: 16px;
  font-weight: bold;
}

.search-form {
  margin-bottom: 0;
}

/* 高亮红色列 */
:deep(.highlight-column) {
  background-color: #fee !important;
}
</style>
