<template>
  <div class="housing-fund-container">
    <div class="page-header">
      <h2>公积金管理</h2>
      <div class="header-buttons">
        <el-button type="primary" @click="showCreateRegionDialog = true">
          <el-icon><Plus /></el-icon>
          新建地区
        </el-button>
        <el-button 
          v-if="selectedRegions.length > 0" 
          type="success" 
          @click="batchCreateTemplate"
        >
          <el-icon><Plus /></el-icon>
          批量创建模板 ({{ selectedRegions.length }})
        </el-button>
      </div>
    </div>

    <!-- 地区列表 -->
    <el-card class="region-list-card">
      <template #header>
        <div class="card-header">
          <span>地区列表</span>
        </div>
      </template>

      <el-table :data="regions" v-loading="loading" stripe @selection-change="handleSelectionChange">
        <el-table-column type="selection" width="55" />
        <el-table-column prop="region_name" label="地区名称" width="200" />
        <el-table-column prop="account_number" label="公积金账号" width="180" />
        <el-table-column label="配置数量" width="120">
          <template #default="{ row }">
            <el-tag type="info">{{ row.config_count }} 个配置</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="creator.name" label="创建人" width="100" />
        <el-table-column prop="created_at" label="创建时间" width="160">
          <template #default="{ row }">
            <span v-date-time="row.created_at"></span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="450">
          <template #default="{ row }">
            <el-button type="primary" size="small" @click="viewConfigs(row)">
              查看配置
            </el-button>
            <el-button type="info" size="small" @click="showRegionHistory(row)">
              历史
            </el-button>
            <el-button
              v-if="!row.has_template"
              type="success"
              size="small"
              @click="createTemplate(row)"
            >
              创建模板
            </el-button>
            <!-- <el-button
              v-else
              type="warning"
              size="small"
              @click="editTemplate(row)"
            >
              编辑模板
            </el-button>
            <el-button
              v-if="row.has_template"
              type="info"
              size="small"
              @click="openCopyTemplateDialog(row)"
            >
              复制模板
            </el-button> -->
            <el-button type="warning" size="small" @click="editRegion(row)">
              编辑
            </el-button>
            <el-button type="danger" size="small" @click="deleteRegion(row)">
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 配置列表 -->
    <el-card v-if="selectedRegion" class="config-list-card">
      <template #header>
        <div class="card-header">
          <span>{{ selectedRegion.region_name }} - 配置列表</span>
          <el-button type="primary" @click="showCreateConfigDialog = true">
            <el-icon><Plus /></el-icon>
            新建配置
          </el-button>
        </div>
      </template>

      <el-table :data="configs" v-loading="configLoading" stripe>
        <el-table-column prop="config_name" label="配置名称" width="200" />
        <el-table-column prop="min_base_amount" label="下限基数" width="120">
          <template #default="{ row }">
            ¥{{ row.min_base_amount || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="max_base_amount" label="上限基数" width="120">
          <template #default="{ row }">
            ¥{{ row.max_base_amount || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="employee_ratio" label="员工缴纳比例" width="120">
          <template #default="{ row }">
            {{ (row.employee_ratio * 100).toFixed(2) }}%
          </template>
        </el-table-column>
        <el-table-column prop="company_ratio" label="公司缴纳比例" width="120">
          <template #default="{ row }">
            {{ (row.company_ratio * 100).toFixed(2) }}%
          </template>
        </el-table-column>
        <el-table-column prop="creator.name" label="创建人" width="100" />
        <el-table-column prop="created_at" label="创建时间" width="160">
          <template #default="{ row }">
            <span v-date-time="row.created_at"></span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="150">
          <template #default="{ row }">
            <el-button type="warning" size="small" @click="editConfig(row)">
              编辑
            </el-button>
            <el-button type="danger" size="small" @click="deleteConfig(row)">
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 创建/编辑地区对话框 -->
    <el-dialog
      v-model="showCreateRegionDialog"
      :title="editingRegion ? '编辑地区' : '新建地区'"
      width="400px"
    >
      <el-form :model="regionForm" :rules="regionRules" ref="regionFormRef" label-width="120px">
        <el-form-item label="地区名称" prop="region_name">
          <el-input v-model="regionForm.region_name" placeholder="请输入地区名称，如：北京市" />
        </el-form-item>
        <el-form-item label="公积金账号" prop="account_number">
          <el-input v-model="regionForm.account_number" placeholder="请输入公积金账号（可选）" />
        </el-form-item>
        <el-form-item label="单位（公司）" prop="company_name">
          <el-input v-model="regionForm.company_name" placeholder="请输入单位名称" />
          <div class="form-tip">缴纳公积金的单位（公司）名称</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateRegionDialog = false">取消</el-button>
        <el-button type="primary" @click="saveRegion" :loading="saving">
          {{ editingRegion ? '更新' : '创建' }}
        </el-button>
      </template>
    </el-dialog>

    <!-- 创建/编辑配置对话框 -->
    <el-dialog
      v-model="showCreateConfigDialog"
      :title="editingConfig ? '编辑配置' : '新建配置'"
      width="500px"
    >
      <el-form :model="configForm" :rules="configRules" ref="configFormRef" label-width="120px">
        <el-form-item label="配置名称" prop="config_name">
          <el-input v-model="configForm.config_name" placeholder="请输入配置名称，如：标准配置" />
        </el-form-item>
        <el-form-item label="下限基数" prop="min_base_amount">
          <el-input-number
            v-model="configForm.min_base_amount"
            :min="0"
            :precision="2"
            placeholder="请输入下限基数"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="上限基数" prop="max_base_amount">
          <el-input-number
            v-model="configForm.max_base_amount"
            :min="0"
            :precision="2"
            placeholder="请输入上限基数"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="员工缴纳比例" prop="employee_ratio">
          <el-input-number
            v-model="configForm.employee_ratio"
            :min="0"
            :max="1"
            :precision="4"
            :step="0.0001"
            placeholder="请输入员工缴纳比例"
            style="width: 100%"
          />
          <div class="form-tip">例如：0.12 表示 12%</div>
        </el-form-item>
        <el-form-item label="公司缴纳比例" prop="company_ratio">
          <el-input-number
            v-model="configForm.company_ratio"
            :min="0"
            :max="1"
            :precision="4"
            :step="0.0001"
            placeholder="请输入公司缴纳比例"
            style="width: 100%"
          />
          <div class="form-tip">例如：0.12 表示 12%</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateConfigDialog = false">取消</el-button>
        <el-button type="primary" @click="saveConfig" :loading="saving">
          {{ editingConfig ? '更新' : '创建' }}
        </el-button>
      </template>
    </el-dialog>

    <el-dialog
      v-model="showHistoryDialog"
      :title="historyTitle"
      width="600px"
    >
      <el-table :data="regionHistories" v-loading="historyLoading" stripe>
        <el-table-column prop="changed_at" label="修改时间" width="180" />
        <el-table-column prop="min_base_amount" label="下限基数" width="180">
          <template #default="{ row }">
            {{ row.min_base_amount === null || row.min_base_amount === undefined ? '-' : `¥${Number(row.min_base_amount).toFixed(2)}` }}
          </template>
        </el-table-column>
        <el-table-column prop="max_base_amount" label="上限基数" width="180">
          <template #default="{ row }">
            {{ row.max_base_amount === null || row.max_base_amount === undefined ? '-' : `¥${Number(row.max_base_amount).toFixed(2)}` }}
          </template>
        </el-table-column>
      </el-table>
    </el-dialog>

    <!-- 添加表头字段对话框 -->
    <el-dialog v-model="showAddHeaderFieldDialog" title="添加表头字段" width="500px">
      <el-form :model="newHeaderField" label-width="100px">
        <el-form-item label="字段标签">
          <el-input v-model="newHeaderField.label" placeholder="例如：公司名称" />
        </el-form-item>
        <el-form-item label="字段类型">
          <el-radio-group v-model="newHeaderField.type">
            <el-radio label="system">系统字段</el-radio>
            <el-radio label="text">固定文本</el-radio>
            <el-radio label="date">日期</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="系统字段" v-if="newHeaderField.type === 'system'">
          <el-select v-model="newHeaderField.systemField" style="width: 100%">
            <el-option 
              v-for="opt in systemFieldOptions" 
              :key="opt.value" 
              :label="opt.label" 
              :value="opt.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="字段值" v-if="newHeaderField.type === 'text'">
          <el-input v-model="newHeaderField.value" placeholder="输入固定文本" />
        </el-form-item>
        <el-form-item label="日期格式" v-if="newHeaderField.type === 'date'">
          <el-select v-model="newHeaderField.dateFormat" style="width: 100%">
            <el-option label="2025-01-02" value="YYYY-MM-DD" />
            <el-option label="2025年01月02日" value="YYYY年MM月DD日" />
            <el-option label="2025/01/02" value="YYYY/MM/DD" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showAddHeaderFieldDialog = false">取消</el-button>
        <el-button type="primary" @click="confirmAddHeaderField">确定</el-button>
      </template>
    </el-dialog>

    <!-- 添加表尾字段对话框 -->
    <el-dialog v-model="showAddFooterFieldDialog" title="添加表尾字段" width="500px">
      <el-form :model="newFooterField" label-width="100px">
        <el-form-item label="字段标签">
          <el-input v-model="newFooterField.label" placeholder="例如：制表人" />
        </el-form-item>
        <el-form-item label="字段类型">
          <el-radio-group v-model="newFooterField.type">
            <el-radio label="system">系统字段</el-radio>
            <el-radio label="text">固定文本</el-radio>
            <el-radio label="date">日期</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="系统字段" v-if="newFooterField.type === 'system'">
          <el-select v-model="newFooterField.systemField" style="width: 100%">
            <el-option label="制表人" value="creator_name" />
            <el-option label="审核人" value="auditor_name" />
            <el-option label="当前用户" value="current_user" />
          </el-select>
        </el-form-item>
        <el-form-item label="字段值" v-if="newFooterField.type === 'text'">
          <el-input v-model="newFooterField.value" placeholder="输入固定文本" />
        </el-form-item>
        <el-form-item label="日期格式" v-if="newFooterField.type === 'date'">
          <el-select v-model="newFooterField.dateFormat" style="width: 100%">
            <el-option label="2025-01-02" value="YYYY-MM-DD" />
            <el-option label="2025年01月02日" value="YYYY年MM月DD日" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showAddFooterFieldDialog = false">取消</el-button>
        <el-button type="primary" @click="confirmAddFooterField">确定</el-button>
      </template>
    </el-dialog>

    <!-- 复制模板对话框 -->
    <el-dialog
      v-model="showCopyTemplateDialog"
      title="复制模板到其他地区"
      width="500px"
    >
      <el-form label-width="100px">
        <el-form-item label="源地区">
          <el-input :value="copyTemplateSource?.region_name" disabled />
        </el-form-item>
        <el-form-item label="目标地区" required>
          <el-select
            v-model="copyTargetRegionIds"
            multiple
            filterable
            placeholder="请选择目标地区（可多选）"
            style="width: 100%"
          >
            <el-option
              v-for="region in copyTargetRegionOptions"
              :key="region.id"
              :label="region.region_name"
              :value="region.id"
            />
          </el-select>
        </el-form-item>
        <div class="form-tip" style="color: #909399; font-size: 12px; margin-top: -10px;">
          如果目标地区已有模板，将会被覆盖
        </div>
      </el-form>
      <template #footer>
        <el-button @click="showCopyTemplateDialog = false">取消</el-button>
        <el-button type="primary" @click="handleCopyTemplate" :loading="copyingTemplate">
          确认复制
        </el-button>
      </template>
    </el-dialog>

    <!-- 模板设计器组件 -->
    <ReportTemplateDesigner
      v-model:visible="showTemplateDesigner"
      :available-fields="availableFields"
      :template-type="'housing_fund'"
      :region="currentTemplateRegion"
      :region-name-field="'region_name'"
      :is-batch-mode="isBatchCreateMode"
      :batch-regions="batchCreateRegions"
      :edit-template="editingTemplate"
      :account-set-id="currentAccountSetId"
      @save="handleTemplateSaved"
      @close="handleTemplateDesignerClose"
    />

    <!-- 编辑报表标题对话框 -->
    <el-dialog v-model="showEditTitleDialog" title="设置报表标题" width="400px">
      <el-input v-model="reportTitle" placeholder="请输入报表标题" />
      <template #footer>
        <el-button @click="showEditTitleDialog = false">取消</el-button>
        <el-button type="primary" @click="showEditTitleDialog = false">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Delete, Edit, Download, Search, Refresh, Setting, Document, Printer, View, CopyDocument, ArrowDown, ArrowUp, Rank, Grid, List } from '@element-plus/icons-vue'
import { useAccountSetStore } from '@/stores/accountSet'
import request from '@/api/request'
import ReportTemplateDesigner from '@/components/ReportTemplateDesigner.vue'
import {
  getHousingFundRegions,
  createHousingFundRegion,
  updateHousingFundRegion,
  deleteHousingFundRegion,
  getHousingFundRegionConfigs,
  getHousingFundRegionLimitHistories
} from '@/api/housingFundRegion'
import { 
  createHousingFundConfig, 
  updateHousingFundConfig, 
  deleteHousingFundConfig
} from '@/api/housingFundConfig'

const accountSetStore = useAccountSetStore()

// 计算属性
const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)


// 模板设计器相关
const showTemplateDesigner = ref(false)
const currentTemplateRegion = ref(null)
const currentTemplateType = ref('housing_fund')
const isBatchCreateMode = ref(false)
const batchCreateRegions = ref([])
const isEditMode = ref(false)
const editingTemplateId = ref(null)
const editingTemplate = ref(null)
const templateForm = ref({
  name: '',
  description: ''
})

// 复制模板相关
const showCopyTemplateDialog = ref(false)
const copyTemplateSource = ref(null)
const copyTargetRegionIds = ref([])
const copyingTemplate = ref(false)

// 复制模板目标地区选项（排除源地区）
const copyTargetRegionOptions = computed(() => {
  if (!copyTemplateSource.value) return []
  return regions.value.filter(r => r.id !== copyTemplateSource.value.id)
})

// 侧边栏标签页
const sidebarTab = ref('header')

// 表格设计器相关
const templateColumns = ref([])
const selectedColumnIndex = ref(null)
const previewTableRef = ref()
const draggedField = ref(null)

// 表头字段相关
const reportTitle = ref('')
const showEditTitleDialog = ref(false)
const reportHeaderFields = ref([])
const selectedHeaderFieldIndex = ref(null)
const showAddHeaderFieldDialog = ref(false)
const newHeaderField = ref({
  label: '',
  type: 'system',
  systemField: '',
  value: '',
  dateFormat: 'YYYY-MM-DD'
})

// 表尾字段相关
const reportFooterFields = ref([])
const selectedFooterFieldIndex = ref(null)
const showAddFooterFieldDialog = ref(false)
const newFooterField = ref({
  label: '',
  type: 'text',
  systemField: '',
  value: '',
  dateFormat: 'YYYY-MM-DD'
})

// 选中的表头字段
const selectedHeaderField = computed(() => {
  if (selectedHeaderFieldIndex.value !== null && reportHeaderFields.value[selectedHeaderFieldIndex.value]) {
    return reportHeaderFields.value[selectedHeaderFieldIndex.value]
  }
  return null
})

// 选中的列
const selectedColumn = computed(() => {
  if (selectedColumnIndex.value !== null && templateColumns.value[selectedColumnIndex.value]) {
    return templateColumns.value[selectedColumnIndex.value]
  }
  return null
})

// 是否可以左移
const canMoveLeft = computed(() => {
  return selectedColumnIndex.value !== null && selectedColumnIndex.value > 0
})

// 是否可以右移
const canMoveRight = computed(() => {
  return selectedColumnIndex.value !== null && selectedColumnIndex.value < templateColumns.value.length - 1
})

// 预览数据（示例数据）
const previewData = ref([
  {
    serial_number: 1,
    employee_name: '张三',
    id_type: '居民身份证',
    id_number: '110101199001011234',
    project_name: '项目A',
    enrollment_date: '2023-01-15',
    type: '正常',
    period: '2026-01',
    housing_fund_base: 10000,
    housing_fund_company: 1200,
    housing_fund_employee: 1200,
    housing_fund_ratio: 12,
    company_total: 1200,
    employee_total: 1200,
    grand_total: 2400,
    remarks: '',
    current_user_name: '管理员',
    current_user_phone: '13800138000'
  },
  {
    serial_number: 2,
    employee_name: '李四',
    id_type: '居民身份证',
    id_number: '110101199102021234',
    project_name: '项目B',
    enrollment_date: '2022-06-20',
    type: '正常',
    period: '2026-01',
    housing_fund_base: 15000,
    housing_fund_company: 1800,
    housing_fund_employee: 1800,
    housing_fund_ratio: 12,
    company_total: 1800,
    employee_total: 1800,
    grand_total: 3600,
    remarks: '',
    current_user_name: '管理员',
    current_user_phone: '13800138000'
  }
])

// 可用的字段列表
const availableFields = ref([
  // 基础信息
  { key: 'serial_number', label: '序号', isSerial: true },
  { key: 'employee_name', label: '员工姓名' },
  { key: 'employee_number', label: '员工工号' },
  { key: 'id_type', label: '身份证件类型' },
  { key: 'id_number', label: '身份证号' },
  { key: 'project_name', label: '项目名称' },
  { key: 'enrollment_date', label: '参保日期' },
  { key: 'type', label: '类型' },
  { key: 'change_type', label: '变更类型' },
  { key: 'period', label: '费款所属期' },
  
  // 工资
  { key: 'basic_salary', label: '月工资额' },
  
  // 基数
  { key: 'housing_fund_base', label: '公积金基数' },
  
  // 公积金
  { key: 'housing_fund_company', label: '公积金-单位' },
  { key: 'housing_fund_employee', label: '公积金-个人' },
  { key: 'housing_fund_ratio', label: '公积金-比例' },
  
  // 合计
  { key: 'company_total', label: '单位缴纳合计' },
  { key: 'employee_total', label: '个人缴纳合计' },
  { key: 'grand_total', label: '总计' },
  
  // 其他
  { key: 'remarks', label: '备注' },
  
  // 当前用户信息
  { key: 'current_user_name', label: '当前用户昵称' },
  { key: 'current_user_phone', label: '当前用户手机号' }
])

// 系统字段选项
const systemFieldOptions = [
  { label: '公司名称', value: 'company_name' },
  { label: '当前地区', value: 'region_name' },
  { label: '账套名称', value: 'account_set_name' },
  { label: '当前年份', value: 'current_year' },
  { label: '当前月份', value: 'current_month' }
]

// 响应式数据
const loading = ref(false)
const configLoading = ref(false)
const saving = ref(false)
const submitting = ref(false)
const regions = ref([])
const selectedRegions = ref([])
const configs = ref([])
const selectedRegion = ref(null)
const showCreateRegionDialog = ref(false)
const showCreateConfigDialog = ref(false)
const editingRegion = ref(null)
const editingConfig = ref(null)
const regionFormRef = ref()
const configFormRef = ref()
const showHistoryDialog = ref(false)
const historyLoading = ref(false)
const regionHistories = ref([])
const historyTitle = ref('')

// 地区表单
const regionForm = reactive({
  region_name: '',
  account_number: '',
  company_name: ''
})

// 配置表单
const configForm = reactive({
  config_name: '',
  min_base_amount: 0,
  max_base_amount: 0,
  employee_ratio: 0.12,
  company_ratio: 0.12
})

// 表单验证规则
const regionRules = {
  region_name: [
    { required: true, message: '请输入地区名称', trigger: 'blur' }
  ]
}

const configRules = {
  config_name: [
    { required: true, message: '请输入配置名称', trigger: 'blur' }
  ],
  min_base_amount: [
    { required: true, message: '请输入下限基数', trigger: 'blur' }
  ],
  max_base_amount: [
    { required: true, message: '请输入上限基数', trigger: 'blur' }
  ],
  employee_ratio: [
    { required: true, message: '请输入员工缴纳比例', trigger: 'blur' }
  ],
  company_ratio: [
    { required: true, message: '请输入公司缴纳比例', trigger: 'blur' }
  ]
}

// 表格计算方法
// 加载地区列表
const loadRegions = async () => {
  loading.value = true
  try {
    const response = await getHousingFundRegions({
      account_set_id: currentAccountSetId.value
    })
    if (response.success) {
      regions.value = response.data
    } else {
      ElMessage.error('加载地区列表失败')
    }
  } catch (error) {
    console.error('加载地区列表失败:', error)
    ElMessage.error('加载地区列表失败')
  } finally {
    loading.value = false
  }
}

// 查看配置
const viewConfigs = async (region) => {
  selectedRegion.value = region
  configLoading.value = true
  try {
    const response = await getHousingFundRegionConfigs(region.id)
    if (response.success) {
      configs.value = response.data
    } else {
      ElMessage.error('加载配置列表失败')
    }
  } catch (error) {
    console.error('加载配置列表失败:', error)
    ElMessage.error('加载配置列表失败')
  } finally {
    configLoading.value = false
  }
}

const showRegionHistory = async (region) => {
  historyTitle.value = `${region.region_name} - 上下限历史`
  showHistoryDialog.value = true
  historyLoading.value = true
  try {
    const response = await getHousingFundRegionLimitHistories(region.id)
    regionHistories.value = response.data || []
  } catch (error) {
    console.error('加载公积金上下限历史失败:', error)
    ElMessage.error('加载历史失败')
    regionHistories.value = []
  } finally {
    historyLoading.value = false
  }
}

// 编辑地区
const editRegion = (region) => {
  editingRegion.value = region
  regionForm.region_name = region.region_name
  regionForm.account_number = region.account_number || ''
  regionForm.company_name = region.company_name || ''
  showCreateRegionDialog.value = true
}

// 保存地区
const saveRegion = async () => {
  if (!regionFormRef.value) return
  
  await regionFormRef.value.validate(async (valid) => {
    if (valid) {
      saving.value = true
      try {
        let response
        const dataToSave = { ...regionForm, account_set_id: currentAccountSetId.value }
        if (editingRegion.value) {
          response = await updateHousingFundRegion(editingRegion.value.id, dataToSave)
        } else {
          response = await createHousingFundRegion(dataToSave)
        }
        
        if (response.success) {
          ElMessage.success(editingRegion.value ? '地区更新成功' : '地区创建成功')
          showCreateRegionDialog.value = false
          resetRegionForm()
          loadRegions()
        } else {
          ElMessage.error(response.message || '保存失败')
        }
      } catch (error) {
        console.error('保存地区失败:', error)
        ElMessage.error('保存地区失败')
      } finally {
        saving.value = false
      }
    }
  })
}

// 删除地区
const deleteRegion = async (region) => {
  try {
    await ElMessageBox.confirm(
      `确定要删除地区"${region.region_name}"吗？`,
      '确认删除',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
    
    const response = await deleteHousingFundRegion(region.id)
    if (response.success) {
      ElMessage.success('地区删除成功')
      loadRegions()
      if (selectedRegion.value && selectedRegion.value.id === region.id) {
        selectedRegion.value = null
        configs.value = []
      }
    } else {
      ElMessage.error(response.message || '删除失败')
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除地区失败:', error)
      ElMessage.error('删除地区失败')
    }
  }
}

// 编辑配置
const editConfig = (config) => {
  editingConfig.value = config
  Object.assign(configForm, {
    config_name: config.config_name,
    min_base_amount: config.min_base_amount,
    max_base_amount: config.max_base_amount,
    employee_ratio: config.employee_ratio,
    company_ratio: config.company_ratio
  })
  showCreateConfigDialog.value = true
}

// 保存配置
const saveConfig = async () => {
  if (!configFormRef.value) return
  
  await configFormRef.value.validate(async (valid) => {
    if (valid) {
      saving.value = true
      try {
        let response
        const dataToSave = { 
          ...configForm, 
          region_id: selectedRegion.value.id,
          account_set_id: currentAccountSetId.value 
        }
        if (editingConfig.value) {
          response = await updateHousingFundConfig(editingConfig.value.id, dataToSave)
        } else {
          response = await createHousingFundConfig(dataToSave)
        }
        
        if (response.success) {
          ElMessage.success(editingConfig.value ? '配置更新成功' : '配置创建成功')
          showCreateConfigDialog.value = false
          resetConfigForm()
          viewConfigs(selectedRegion.value) // 重新加载配置列表
          loadRegions() // 重新加载地区列表以更新统计信息
        } else {
          ElMessage.error(response.message || '保存失败')
        }
      } catch (error) {
        console.error('保存配置失败:', error)
        ElMessage.error('保存配置失败')
      } finally {
        saving.value = false
      }
    }
  })
}

// 删除配置
const deleteConfig = async (config) => {
  try {
    await ElMessageBox.confirm(
      `确定要删除配置"${config.config_name}"吗？`,
      '确认删除',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
    
    const response = await deleteHousingFundConfig(config.id)
    if (response.success) {
      ElMessage.success('配置删除成功')
      viewConfigs(selectedRegion.value) // 重新加载配置列表
      loadRegions() // 重新加载地区列表以更新统计信息
    } else {
      ElMessage.error(response.message || '删除失败')
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除配置失败:', error)
      ElMessage.error('删除配置失败')
    }
  }
}

// 重置表单
const resetRegionForm = () => {
  editingRegion.value = null
  regionForm.region_name = ''
  regionForm.account_number = ''
  regionForm.company_name = ''
  regionFormRef.value?.resetFields()
}

const resetConfigForm = () => {
  editingConfig.value = null
  Object.assign(configForm, {
    config_name: '',
    min_base_amount: 0,
    max_base_amount: 0,
    employee_ratio: 0.12,
    company_ratio: 0.12
  })
  configFormRef.value?.resetFields()
}

// ==================== 模板管理功能 ====================

// 处理表格多选
const handleSelectionChange = (selection) => {
  console.log('选中的地区:', selection)
  selectedRegions.value = selection
}

// 批量创建模板
const batchCreateTemplate = () => {
  if (selectedRegions.value.length === 0) {
    ElMessage.warning('请先选择要创建模板的地区')
    return
  }
  
  const regionsWithoutTemplate = selectedRegions.value.filter(r => !r.has_template)
  
  if (regionsWithoutTemplate.length === 0) {
    ElMessage.warning('所选地区都已有模板，请使用编辑模板功能')
    return
  }
  
  currentTemplateRegion.value = regionsWithoutTemplate[0]
  templateForm.value = {
    name: `批量创建-公积金缴纳明细`,
    description: `为 ${regionsWithoutTemplate.map(r => r.region_name).join('、')} 创建模板`
  }
  
  reportTitle.value = ''
  templateColumns.value = []
  selectedColumnIndex.value = null
  reportHeaderFields.value = []
  selectedHeaderFieldIndex.value = null
  reportFooterFields.value = []
  selectedFooterFieldIndex.value = null
  sidebarTab.value = 'header'
  showTemplateDesigner.value = true
  
  isBatchCreateMode.value = true
  batchCreateRegions.value = regionsWithoutTemplate
  isEditMode.value = false
  editingTemplateId.value = null
  currentTemplateType.value = 'housing_fund'
}

// 创建模板
const createTemplate = (region) => {
  currentTemplateRegion.value = region
  templateForm.value = {
    name: `${region.region_name}-公积金缴纳明细`,
    description: ''
  }
  
  reportTitle.value = ''
  templateColumns.value = []
  selectedColumnIndex.value = null
  reportHeaderFields.value = []
  selectedHeaderFieldIndex.value = null
  reportFooterFields.value = []
  selectedFooterFieldIndex.value = null
  sidebarTab.value = 'header'
  showTemplateDesigner.value = true
  
  isBatchCreateMode.value = false
  batchCreateRegions.value = []
  isEditMode.value = false
  editingTemplateId.value = null
  currentTemplateType.value = 'housing_fund'
}

// 编辑模板
const editTemplate = async (region) => {
  try {
    const response = await request.get('/report-templates', {
      params: {
        region_id: region.id,
        region_type: 'housing_fund',
        account_set_id: currentAccountSetId.value
      }
    })
    
    if (response.success && response.data && response.data.length > 0) {
      const template = response.data[0]
      
      // 只设置传给组件的必要数据
      currentTemplateRegion.value = region
      editingTemplate.value = template
      editingTemplateId.value = template.id
      isEditMode.value = true
      isBatchCreateMode.value = false
      batchCreateRegions.value = []
      currentTemplateType.value = 'housing_fund'
      
      showTemplateDesigner.value = true
    } else {
      ElMessage.warning('未找到该地区的模板')
    }
  } catch (error) {
    console.error('加载模板失败:', error)
    ElMessage.error('加载模板失败')
  }
}

// 打开复制模板对话框
const openCopyTemplateDialog = async (region) => {
  try {
    // 先获取该地区的模板ID
    const response = await request.get('/report-templates', {
      params: {
        region_id: region.id,
        region_type: 'housing_fund',
        account_set_id: currentAccountSetId.value
      }
    })
    
    if (response.success && response.data && response.data.length > 0) {
      copyTemplateSource.value = { ...region, templateId: response.data[0].id }
      copyTargetRegionIds.value = []
      showCopyTemplateDialog.value = true
    } else {
      ElMessage.warning('未找到该地区的模板')
    }
  } catch (error) {
    console.error('获取模板失败:', error)
    ElMessage.error('获取模板失败')
  }
}

// 执行复制模板
const handleCopyTemplate = async () => {
  if (copyTargetRegionIds.value.length === 0) {
    ElMessage.warning('请选择目标地区')
    return
  }
  
  copyingTemplate.value = true
  try {
    const response = await request.post(`/report-templates/${copyTemplateSource.value.templateId}/copy-to-regions`, {
      target_region_ids: copyTargetRegionIds.value
    })
    
    if (response.success) {
      ElMessage.success(response.message || '模板复制成功')
      showCopyTemplateDialog.value = false
      
      // 刷新地区列表以更新模板状态
      await loadRegions()
    } else {
      ElMessage.error(response.message || '复制失败')
    }
  } catch (error) {
    console.error('复制模板失败:', error)
    ElMessage.error('复制模板失败')
  } finally {
    copyingTemplate.value = false
  }
}

// 编辑报表标题
const editReportTitle = () => {
  showEditTitleDialog.value = true
}

// 确认添加表头字段
const confirmAddHeaderField = () => {
  if (!newHeaderField.value.label) {
    ElMessage.warning('请输入字段标签')
    return
  }

  if (newHeaderField.value.type === 'system' && !newHeaderField.value.systemField) {
    ElMessage.warning('请选择系统字段')
    return
  }

  if (newHeaderField.value.type === 'text' && !newHeaderField.value.value) {
    ElMessage.warning('请输入字段值')
    return
  }

  const field = {
    label: newHeaderField.value.label,
    type: newHeaderField.value.type,
    systemField: newHeaderField.value.systemField,
    value: newHeaderField.value.value,
    dateFormat: newHeaderField.value.dateFormat
  }

  reportHeaderFields.value.push(field)
  selectedHeaderFieldIndex.value = reportHeaderFields.value.length - 1
  selectedColumnIndex.value = null
  
  newHeaderField.value = {
    label: '',
    type: 'system',
    systemField: '',
    value: '',
    dateFormat: 'YYYY-MM-DD'
  }
  
  showAddHeaderFieldDialog.value = false
  ElMessage.success('添加成功')
}

// 获取字段值预览
const getFieldValuePreview = (field) => {
  if (field.type === 'system') {
    const systemFieldLabels = {
      company_name: '{公司名称}',
      region_name: '{当前地区}',
      account_set_name: '{账套名称}',
      current_year: '{当前年份}',
      current_month: '{当前月份}'
    }
    return systemFieldLabels[field.systemField] || '{系统字段}'
  } else if (field.type === 'date') {
    return `{${field.dateFormat}}`
  } else {
    return field.value || '{文本}'
  }
}

// 选择表头字段
const selectHeaderField = (index) => {
  selectedHeaderFieldIndex.value = index
  selectedColumnIndex.value = null
}

// 删除表头字段
const removeHeaderField = (index) => {
  reportHeaderFields.value.splice(index, 1)
  selectedHeaderFieldIndex.value = null
}

// 确认添加表尾字段
const confirmAddFooterField = () => {
  if (!newFooterField.value.label) {
    ElMessage.warning('请输入字段标签')
    return
  }

  if (newFooterField.value.type === 'system' && !newFooterField.value.systemField) {
    ElMessage.warning('请选择系统字段')
    return
  }

  const field = {
    label: newFooterField.value.label,
    type: newFooterField.value.type,
    systemField: newFooterField.value.systemField,
    value: newFooterField.value.value,
    dateFormat: newFooterField.value.dateFormat
  }

  reportFooterFields.value.push(field)
  selectedFooterFieldIndex.value = reportFooterFields.value.length - 1
  selectedColumnIndex.value = null
  selectedHeaderFieldIndex.value = null
  
  newFooterField.value = {
    label: '',
    type: 'text',
    systemField: '',
    value: '',
    dateFormat: 'YYYY-MM-DD'
  }
  
  showAddFooterFieldDialog.value = false
  ElMessage.success('添加成功')
}

// 选择表尾字段
const selectFooterField = (index) => {
  selectedFooterFieldIndex.value = index
  selectedColumnIndex.value = null
  selectedHeaderFieldIndex.value = null
}

// 删除表尾字段
const removeFooterField = (index) => {
  reportFooterFields.value.splice(index, 1)
  selectedFooterFieldIndex.value = null
}

// 拖拽开始
const handleDragStart = (field) => {
  draggedField.value = field
}

// 拖拽放置到表格区域
const handleDrop = (e) => {
  e.preventDefault()
  if (draggedField.value) {
    addColumnWithField(draggedField.value)
    draggedField.value = null
  }
}

// 拖拽放置到父列
const handleDropToParent = (e, parentIndex) => {
  e.preventDefault()
  e.stopPropagation()
  
  if (!draggedField.value) return
  
  const parentColumn = templateColumns.value[parentIndex]
  if (!parentColumn || !parentColumn.isParent) return
  
  if (!parentColumn.children) {
    parentColumn.children = []
  }
  
  const newChild = {
    field: draggedField.value.key,
    title: draggedField.value.label,
    width: 120,
    align: draggedField.value.key.includes('amount') || draggedField.value.key.includes('base') || draggedField.value.key.includes('company') || draggedField.value.key.includes('employee') || draggedField.value.key.includes('total') ? 'right' : 'left',
    format: draggedField.value.key.includes('amount') || draggedField.value.key.includes('base') || draggedField.value.key.includes('company') || draggedField.value.key.includes('employee') || draggedField.value.key.includes('total') ? 'currency' : 'text'
  }
  
  parentColumn.children.push(newChild)
  draggedField.value = null
  ElMessage.success('子列添加成功')
}

// 添加父列
const addParentColumn = () => {
  const newColumn = {
    title: '父列标题',
    isParent: true,
    children: [],
    width: undefined,
    align: 'center'
  }
  templateColumns.value.push(newColumn)
  selectedColumnIndex.value = templateColumns.value.length - 1
  ElMessage.success('父列添加成功，可以拖拽字段到父列中')
}

// 添加普通列
const addColumn = () => {
  const newColumn = {
    field: 'name',
    title: '新列',
    width: 120,
    align: 'left',
    format: 'text'
  }
  templateColumns.value.push(newColumn)
  selectedColumnIndex.value = templateColumns.value.length - 1
}

// 使用字段添加列
const addColumnWithField = (field) => {
  let align = 'left'
  if (field.key.includes('amount') || field.key.includes('base') || field.key.includes('company') || field.key.includes('employee') || field.key.includes('total') || field.key.includes('ratio') || field.key === 'serial_number') {
    align = 'center'
  }
  if (field.key.includes('amount') || field.key.includes('base') || field.key.includes('company') || field.key.includes('employee') || field.key.includes('total')) {
    align = 'right'
  }
  
  let format = 'text'
  if (field.key.includes('amount') || field.key.includes('base') || field.key.includes('company') || field.key.includes('employee') || field.key.includes('total') || field.key.includes('fund')) {
    format = 'currency'
  } else if (field.key.includes('ratio')) {
    format = 'percent'
  } else if (field.key.includes('date')) {
    format = 'date'
  } else if (field.key === 'serial_number') {
    format = 'number'
  }
  
  const newColumn = {
    field: field.key,
    title: field.label,
    width: field.key === 'serial_number' ? 80 : 120,
    align: align,
    format: format,
    isSerial: field.isSerial || false
  }
  templateColumns.value.push(newColumn)
  selectedColumnIndex.value = templateColumns.value.length - 1
}

// 删除列
const removeColumn = () => {
  if (selectedColumnIndex.value !== null) {
    templateColumns.value.splice(selectedColumnIndex.value, 1)
    selectedColumnIndex.value = null
  }
}

// 获取总列数
const getTotalColumnCount = () => {
  let count = 0
  templateColumns.value.forEach(col => {
    if (col.children && col.children.length > 0) {
      count += col.children.length
    } else if (!col.isParent) {
      count += 1
    }
  })
  return count
}

// 左移列
const moveColumnLeft = () => {
  if (canMoveLeft.value) {
    const index = selectedColumnIndex.value
    const temp = templateColumns.value[index]
    templateColumns.value[index] = templateColumns.value[index - 1]
    templateColumns.value[index - 1] = temp
    selectedColumnIndex.value = index - 1
  }
}

// 右移列
const moveColumnRight = () => {
  if (canMoveRight.value) {
    const index = selectedColumnIndex.value
    const temp = templateColumns.value[index]
    templateColumns.value[index] = templateColumns.value[index + 1]
    templateColumns.value[index + 1] = temp
    selectedColumnIndex.value = index + 1
  }
}

// 选择列
const selectColumn = (index) => {
  selectedColumnIndex.value = index
  selectedHeaderFieldIndex.value = null
  selectedFooterFieldIndex.value = null
}

// 选择子列
const selectChildColumn = (parentIndex, childIndex) => {
  selectedColumnIndex.value = parentIndex
  selectedHeaderFieldIndex.value = null
  selectedFooterFieldIndex.value = null
}

// 格式化单元格值
const formatCellValue = (value, format) => {
  if (value === null || value === undefined) return ''
  
  switch (format) {
    case 'currency':
      return `¥${Number(value).toFixed(2)}`
    case 'number':
      return Number(value).toLocaleString()
    case 'percent':
      return `${(Number(value) * 100).toFixed(2)}%`
    case 'date':
      return value ? new Date(value).toLocaleDateString('zh-CN') : ''
    default:
      return value
  }
}

// 检查是否有任何列需要显示合计
const hasAnyTotal = () => {
  return templateColumns.value.some(col => {
    if (col.children && col.children.length > 0) {
      return col.children.some(child => child.showTotal)
    }
    return col.showTotal
  })
}

// 计算合计行数据
const getTotalRow = () => {
  const totalRow = {}
  
  templateColumns.value.forEach(col => {
    if (col.children && col.children.length > 0) {
      col.children.forEach(child => {
        const key = `${col.title}_${child.title}`
        if (child.showTotal) {
          const sum = previewData.value.reduce((acc, row) => {
            return acc + (Number(row[child.field]) || 0)
          }, 0)
          totalRow[key] = formatCellValue(sum, child.format)
        } else {
          totalRow[key] = ''
        }
      })
    } else if (!col.isParent) {
      if (col.showTotal) {
        if (col.field === 'serial_number' || col.isSerial) {
          totalRow[col.field] = '合计'
        } else {
          const sum = previewData.value.reduce((acc, row) => {
            return acc + (Number(row[col.field]) || 0)
          }, 0)
          totalRow[col.field] = formatCellValue(sum, col.format)
        }
      } else {
        const isFirstColumn = templateColumns.value.indexOf(col) === 0
        const hasOtherTotals = templateColumns.value.some(c => c.showTotal || (c.children && c.children.some(ch => ch.showTotal)))
        totalRow[col.field] = (isFirstColumn && hasOtherTotals) ? '合计' : ''
      }
    } else {
      totalRow[col.field] = ''
    }
  })
  
  return totalRow
}

// 保存模板
const saveTemplate = async () => {
  if (!templateForm.value.name) {
    ElMessage.warning('请输入模板名称')
    return
  }

  if (!reportTitle.value) {
    ElMessage.warning('请设置报表标题')
    return
  }
  
  if (templateColumns.value.length === 0) {
    ElMessage.warning('请至少添加一列')
    return
  }
  
  try {
    submitting.value = true
    
    if (isBatchCreateMode.value && batchCreateRegions.value.length > 0) {
      let successCount = 0
      let failCount = 0
      
      for (const region of batchCreateRegions.value) {
        try {
          const templateData = {
            name: `${region.region_name}-${templateForm.value.name}`,
            description: templateForm.value.description,
            report_title: reportTitle.value,
            region_id: region.id,
            region_type: currentTemplateType.value,
            fields: templateColumns.value.map((col, index) => ({
              key: col.field,
              label: col.title,
              order: index,
              width: col.width,
              align: col.align,
              format: col.format
            })),
            account_set_id: currentAccountSetId.value,
            header_fields: reportHeaderFields.value.map((field, index) => ({
              label: field.label,
              type: field.type,
              system_field: field.systemField,
              value: field.value,
              date_format: field.dateFormat,
              order: index
            })),
            footer_fields: reportFooterFields.value.map((field, index) => ({
              label: field.label,
              type: field.type,
              system_field: field.systemField,
              value: field.value,
              date_format: field.dateFormat,
              order: index
            }))
          }
          
          const response = await request.post('/report-templates', templateData)
          
          if (response.success) {
            successCount++
          } else {
            failCount++
          }
        } catch (error) {
          console.error(`为地区 ${region.region_name} 创建模板失败:`, error)
          failCount++
        }
      }
      
      if (successCount > 0) {
        ElMessage.success(`成功为 ${successCount} 个地区创建模板${failCount > 0 ? `，${failCount} 个失败` : ''}`)
        showTemplateDesigner.value = false
        reportTitle.value = ''
        templateColumns.value = []
        selectedColumnIndex.value = null
        reportHeaderFields.value = []
        selectedHeaderFieldIndex.value = null
        reportFooterFields.value = []
        selectedFooterFieldIndex.value = null
        isBatchCreateMode.value = false
        batchCreateRegions.value = []
        selectedRegions.value = []
        loadRegions()
      } else {
        ElMessage.error('批量创建模板失败')
      }
    } else {
      const templateData = {
        name: templateForm.value.name,
        description: templateForm.value.description,
        report_title: reportTitle.value,
        region_id: currentTemplateRegion.value.id,
        region_type: currentTemplateType.value,
        fields: templateColumns.value.map((col, index) => ({
          key: col.field,
          label: col.title,
          order: index,
          width: col.width,
          align: col.align,
          format: col.format
        })),
        account_set_id: currentAccountSetId.value,
        header_fields: reportHeaderFields.value.map((field, index) => ({
          label: field.label,
          type: field.type,
          system_field: field.systemField,
          value: field.value,
          date_format: field.dateFormat,
          order: index
        })),
        footer_fields: reportFooterFields.value.map((field, index) => ({
          label: field.label,
          type: field.type,
          system_field: field.systemField,
          value: field.value,
          date_format: field.dateFormat,
          order: index
        }))
      }
      
      let response
      if (isEditMode.value && editingTemplateId.value) {
        response = await request.put(`/report-templates/${editingTemplateId.value}`, templateData)
      } else {
        response = await request.post('/report-templates', templateData)
      }
      
      if (response.success) {
        ElMessage.success(isEditMode.value ? '模板更新成功' : '模板创建成功')
        showTemplateDesigner.value = false
        reportTitle.value = ''
        templateColumns.value = []
        selectedColumnIndex.value = null
        reportHeaderFields.value = []
        selectedHeaderFieldIndex.value = null
        reportFooterFields.value = []
        selectedFooterFieldIndex.value = null
        isEditMode.value = false
        editingTemplateId.value = null
        loadRegions()
      } else {
        throw new Error(response.message || '保存失败')
      }
    }
  } catch (error) {
    console.error('保存模板失败:', error)
    ElMessage.error('保存模板失败')
  } finally {
    submitting.value = false
  }
}

// 模板设计器保存回调
const handleTemplateSaved = (result) => {
  if (result.success) {
    loadRegions()
    // 重置状态
    isBatchCreateMode.value = false
    batchCreateRegions.value = []
    selectedRegions.value = []
    editingTemplate.value = null
    isEditMode.value = false
    editingTemplateId.value = null
  }
}

// 模板设计器关闭回调
const handleTemplateDesignerClose = () => {
  isBatchCreateMode.value = false
  batchCreateRegions.value = []
  editingTemplate.value = null
  isEditMode.value = false
  editingTemplateId.value = null
}

// 监听账套变化
const unwatchAccountSet = accountSetStore.$subscribe((mutation, state) => {
  if (state.currentAccountSetId) {
    loadRegions()
  }
})

// 组件挂载时加载数据
onMounted(() => {
  if (currentAccountSetId.value) {
    loadRegions()
  }
})
</script>

<style scoped>
.housing-fund-container {
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
  color: #303133;
}

.region-list-card {
  margin-bottom: 20px;
}

.config-list-card {
  margin-bottom: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
}

.calculation-result {
  background: #f5f7fa;
  padding: 15px;
  border-radius: 4px;
  border: 1px solid #e4e7ed;
}

.calc-item {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
}

.calc-item:last-child {
  margin-bottom: 0;
}

.calc-item.total {
  border-top: 1px solid #e4e7ed;
  padding-top: 8px;
  font-weight: bold;
  color: #409eff;
}

.calc-label {
  color: #606266;
}

.calc-value {
  color: #303133;
  font-weight: 500;
}

/* 模板设计器样式 */
.template-designer {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 200px);
}

.designer-header {
  padding: 16px;
  background: #f5f7fa;
  border-bottom: 1px solid #e4e7ed;
}

.designer-body {
  display: flex;
  flex: 1;
  overflow: hidden;
}

.designer-sidebar {
  width: 220px;
  border-right: 1px solid #e4e7ed;
  background: #fafafa;
  display: flex;
  flex-direction: column;
}

.sidebar-header {
  padding: 16px;
  border-bottom: 1px solid #e4e7ed;
}

.sidebar-header h4 {
  margin: 0 0 8px 0;
  font-size: 14px;
  color: #303133;
}

.field-item {
  padding: 10px 16px;
  margin: 4px 8px;
  background: white;
  border: 1px solid #dcdfe6;
  border-radius: 4px;
  cursor: move;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s;
}

.field-item:hover {
  background: #ecf5ff;
  border-color: #409eff;
  transform: translateX(4px);
}

.field-item .el-icon {
  color: #909399;
}

/* 表头字段列表样式 */
.header-fields-list {
  padding: 8px;
}

.header-field-preview {
  padding: 10px 12px;
  margin-bottom: 8px;
  background: white;
  border: 1px solid #dcdfe6;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
}

.header-field-preview:hover {
  border-color: #409eff;
  background: #ecf5ff;
}

.header-field-preview.selected {
  border-color: #409eff;
  background: #ecf5ff;
}

.field-preview-label {
  font-weight: 500;
  color: #303133;
  font-size: 13px;
  margin-bottom: 4px;
}

.field-preview-value {
  color: #909399;
  font-size: 12px;
}

.designer-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  padding: 16px;
  overflow: hidden;
}

.designer-toolbar {
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.table-preview {
  flex: 1;
  overflow: auto;
  border: 2px dashed #dcdfe6;
  border-radius: 4px;
  padding: 16px;
  background: white;
  position: relative;
}

.table-total-row {
  margin-top: -1px;
}

.table-total-row .el-table {
  border-top: 2px solid #409eff;
}

.table-total-row .total-cell {
  font-weight: bold;
  color: #303133;
}

.empty-table {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
}

/* 报表预览样式 */
.report-preview {
  flex: 1;
  overflow: auto;
  border: 2px solid #e4e7ed;
  border-radius: 4px;
  background: white;
  padding: 20px;
}

.report-header {
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 2px solid #303133;
}

.header-title {
  text-align: center;
  margin-bottom: 16px;
  cursor: pointer;
  padding: 8px;
  border-radius: 4px;
  transition: all 0.2s;
  position: relative;
  display: inline-block;
  width: 100%;
}

.header-title:hover {
  background: #f5f7fa;
}

.header-title h3 {
  margin: 0;
  font-size: 20px;
  font-weight: bold;
  color: #303133;
  display: inline-block;
}

.header-title .edit-icon {
  margin-left: 8px;
  color: #909399;
  font-size: 16px;
  vertical-align: middle;
}

.header-fields {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 12px;
  padding: 12px;
  background: #f5f7fa;
  border-radius: 4px;
  min-height: 60px;
}

.header-field-item {
  display: inline-flex;
  align-items: center;
  padding: 6px 12px;
  background: white;
  border: 1px solid #dcdfe6;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
}

.header-field-item:hover {
  border-color: #409eff;
  background: #ecf5ff;
}

.header-field-item.selected {
  border-color: #409eff;
  background: #ecf5ff;
}

.header-field-item .field-label {
  font-weight: 500;
  color: #606266;
  margin-right: 4px;
}

.header-field-item .field-value {
  color: #909399;
}

.header-field-item .remove-icon {
  margin-left: 8px;
  color: #f56c6c;
  cursor: pointer;
}

.header-field-item .remove-icon:hover {
  color: #f56c6c;
  transform: scale(1.2);
}

.report-footer {
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid #e4e7ed;
}

.footer-fields {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 12px;
  padding: 12px;
  background: #f5f7fa;
  border-radius: 4px;
  min-height: 50px;
}

.footer-field-item {
  display: inline-flex;
  align-items: center;
  padding: 6px 12px;
  background: white;
  border: 1px solid #dcdfe6;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
  font-size: 14px;
  color: #606266;
}

.footer-field-item:hover {
  border-color: #409eff;
  background: #ecf5ff;
}

.footer-field-item.selected {
  border-color: #409eff;
  background: #ecf5ff;
}

.footer-field-item .field-label {
  font-weight: 500;
  color: #606266;
  margin-right: 4px;
}

.footer-field-item .field-value {
  color: #909399;
}

.footer-field-item .remove-icon {
  margin-left: 8px;
  color: #f56c6c;
  cursor: pointer;
}

.footer-field-item .remove-icon:hover {
  color: #f56c6c;
  transform: scale(1.2);
}

.column-header {
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  transition: all 0.2s;
}

.column-header:hover {
  background: #ecf5ff;
}

.column-header.selected {
  background: #409eff;
  color: white;
}

.column-header.parent-column {
  background: #f0f9ff;
  border: 2px dashed #409eff;
  padding: 8px;
  min-height: 50px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.column-header.parent-column.drop-zone {
  background: #e6f7ff;
}

.column-header.parent-column:hover {
  background: #d9f0ff;
}

.column-header.child-column {
  background: #fafafa;
}

.designer-properties {
  width: 280px;
  border-left: 1px solid #e4e7ed;
  background: #fafafa;
  display: flex;
  flex-direction: column;
}

.properties-header {
  padding: 16px;
  border-bottom: 1px solid #e4e7ed;
}

.properties-header h4 {
  margin: 0;
  font-size: 14px;
  color: #303133;
}

.designer-properties .el-form {
  padding: 16px;
}

.designer-properties .el-form-item {
  margin-bottom: 18px;
}

:deep(.selected-column) {
  background: #ecf5ff !important;
}

</style>