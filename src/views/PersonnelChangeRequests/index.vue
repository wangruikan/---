<template>
  <div class="personnel-change-requests-page">
    <NoAccountSetTip v-if="!isAdmin && !currentAccountSetId" />
    <div v-else>
      <div class="page-header">
        <h1>人员汇总申请</h1>
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

          <el-form-item label="变动类型">
            <el-select v-model="searchForm.change_type" placeholder="请选择类型" clearable style="width: 150px;">
              <el-option label="全部类型" :value="null" />
              <el-option label="人员新增" value="add" />
              <el-option label="人员减少" value="remove" />
            </el-select>
          </el-form-item>

          <el-form-item label="状态">
            <el-select v-model="searchForm.status" placeholder="请选择状态" clearable style="width: 150px;">
              <el-option label="全部状态" :value="null" />
              <el-option label="待审批" value="pending" />
              <el-option label="审批中" value="in_approval" />
              <el-option label="已通过" value="approved" />
              <el-option label="已拒绝" value="rejected" />
            </el-select>
          </el-form-item>

          <el-form-item>
            <el-button type="primary" @click="handleSearch">查询</el-button>
            <el-button @click="handleReset">重置</el-button>
          </el-form-item>
        </el-form>
      </el-card>

      <!-- 人员变动申请列表 -->
      <el-card shadow="never">
        <template #header>
          <div class="card-header">
            <span class="title">人员变动申请列表</span>
            <el-button type="text" @click="handleSearch">
              <el-icon><Refresh /></el-icon>
              刷新
            </el-button>
          </div>
        </template>

        <el-table
          :data="tableData"
          border
          stripe
          v-loading="loading"
          style="width: 100%"
        >
          <el-table-column type="index" label="序号" width="60" align="center" />
          <el-table-column prop="project.name" label="项目名称" min-width="200" show-overflow-tooltip />
          <el-table-column prop="month" label="工资期间" width="120" />
          <el-table-column prop="change_type" label="变动类型" width="120">
            <template #default="scope">
              <el-tag :type="scope.row.change_type === 'add' ? 'success' : 'warning'">
                {{ scope.row.change_type === 'add' ? '人员新增' : '人员减少' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="personnel_count" label="人员数量" width="100" align="center" />
          <el-table-column prop="status" label="状态" width="100">
            <template #default="scope">
              <el-tag :type="getStatusType(scope.row.status)">
                {{ getStatusText(scope.row.status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="created_at" label="创建时间" width="160">
            <template #default="scope">
              {{ formatDateTime(scope.row.created_at) }}
            </template>
          </el-table-column>
          <el-table-column label="操作" width="250" fixed="right">
            <template #default="scope">
              <el-button link type="primary" size="small" @click="handleView(scope.row)">
                查看详情
              </el-button>
              <el-button 
                v-if="scope.row.status === 'pending'" 
                link 
                type="success" 
                size="small" 
                @click="handleSubmit(scope.row)"
              >
                提交审批
              </el-button>
              <el-button 
                v-if="scope.row.status === 'pending'" 
                link 
                type="danger" 
                size="small" 
                @click="handleDelete(scope.row)"
              >
                删除
              </el-button>
            </template>
          </el-table-column>
        </el-table>

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

      <!-- 查看详情对话框 -->
      <el-dialog
        v-model="detailDialogVisible"
        width="900px"
      >
        <template #header>
          <span>人员变动详情</span>
        </template>
        
        <div v-if="currentRow">
          <el-descriptions :column="2" border>
            <el-descriptions-item label="项目名称">{{ currentRow.project?.name }}</el-descriptions-item>
            <el-descriptions-item label="工资期间">{{ currentRow.month }}</el-descriptions-item>
            <el-descriptions-item label="变动类型">
              <el-tag :type="currentRow.change_type === 'add' ? 'success' : 'warning'">
                {{ currentRow.change_type === 'add' ? '人员新增' : '人员减少' }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="人员数量">{{ currentRow.personnel_count }} 人</el-descriptions-item>
            <el-descriptions-item label="状态">
              <el-tag :type="getStatusType(currentRow.status)">
                {{ getStatusText(currentRow.status) }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="创建时间">{{ formatDateTime(currentRow.created_at) }}</el-descriptions-item>
          </el-descriptions>

          <!-- 人员列表 -->
          <el-divider content-position="left">
            <span style="font-size: 14px; color: #606266;">
              {{ currentRow.change_type === 'add' ? '需要新增的人员' : '需要减少的人员' }}
              ({{ currentRow.personnel_count }})
            </span>
          </el-divider>

          <!-- 导出按钮 -->
          <div style="margin-bottom: 10px; text-align: right;">
            <el-button type="success" @click="exportToExcel" :loading="exporting">
              <el-icon><Download /></el-icon>
              导出Excel
            </el-button>
          </div>

          <el-table :data="enrichedPersonnelList" border stripe>
            <el-table-column type="index" label="序号" width="60" align="center" />
            <el-table-column prop="employee_number" label="工号" width="100" />
            <el-table-column prop="name" label="*姓名" width="100" />
            <el-table-column prop="id_type" label="*证件类型" width="100" />
            <el-table-column prop="id_card" label="*证件号码" width="180" />
            <el-table-column prop="country_region" label="*国籍(地区)" width="120" />
            <el-table-column prop="gender" label="*性别" width="80" />
            <el-table-column prop="birth_date" label="*出生日期" width="120" />
            <el-table-column prop="personnel_status" label="人员状态" width="100" />
            <el-table-column prop="employment_type" label="*任职受雇从业类型" width="150" />
            <el-table-column prop="other_notes" label="其他情况说明" width="150" />
            <el-table-column prop="annual_employment_status" label="入职年度就业情形" width="150" />
            <el-table-column prop="phone" label="手机号码" width="120" />
            <el-table-column prop="employment_date" label="任职受雇从业日期" width="140" />
            <el-table-column prop="resignation_date" label="离职日期" width="120" />
            <el-table-column prop="is_disabled" label="是否残疾" width="80">
              <template #default="scope">
                {{ scope.row.is_disabled ? '是' : '否' }}
              </template>
            </el-table-column>
            <el-table-column prop="is_martyr_family" label="是否烈属" width="80">
              <template #default="scope">
                {{ scope.row.is_martyr_family ? '是' : '否' }}
              </template>
            </el-table-column>
            <el-table-column prop="is_elderly_alone" label="是否孤老" width="80">
              <template #default="scope">
                {{ scope.row.is_elderly_alone ? '是' : '否' }}
              </template>
            </el-table-column>
            <el-table-column prop="disability_cert_type" label="残疾证件类型" width="120" />
            <el-table-column prop="disability_cert_number" label="残疾证号" width="150" />
            <el-table-column prop="martyr_family_cert_number" label="烈属证号" width="150" />
            <el-table-column prop="deduct_expense" label="是否扣除减除费用" width="140">
              <template #default="scope">
                {{ scope.row.deduct_expense ? '是' : '否' }}
              </template>
            </el-table-column>
            <el-table-column prop="personal_investment_amount" label="个人投资额" width="120" />
            <el-table-column prop="personal_investment_ratio" label="个人投资比例(%)" width="140" />
            <el-table-column prop="remarks" label="备注" width="150" />
            <el-table-column prop="chinese_name" label="中文名" width="100" />
            <el-table-column prop="tax_matter" label="涉税事由" width="120" />
            <el-table-column prop="birth_country" label="出生国家(地区)" width="120" />
            <el-table-column prop="first_entry_date" label="首次入境时间" width="120" />
            <el-table-column prop="expected_departure_date" label="预计离境时间" width="120" />
            <el-table-column prop="other_id_type" label="其他证件类型" width="120" />
            <el-table-column prop="other_id_number" label="其他证件号码" width="150" />
            <el-table-column prop="household_province" label="户籍所在地（省）" width="140" />
            <el-table-column prop="household_city" label="户籍所在地（市）" width="140" />
            <el-table-column prop="household_district" label="户籍所在地（区县）" width="150" />
            <el-table-column prop="household_address" label="户籍所在地（详细地址）" width="180" />
            <el-table-column prop="residence_province" label="经常居住地（省）" width="140" />
            <el-table-column prop="residence_city" label="经常居住地（市）" width="140" />
            <el-table-column prop="residence_district" label="经常居住地（区县）" width="150" />
            <el-table-column prop="residence_address" label="经常居住地（详细地址）" width="180" />
            <el-table-column prop="contact_province" label="联系地址（省）" width="120" />
            <el-table-column prop="contact_city" label="联系地址（市）" width="120" />
            <el-table-column prop="contact_district" label="联系地址（区县）" width="140" />
            <el-table-column prop="contact_address" label="联系地址（详细地址）" width="180" />
            <el-table-column prop="email_address" label="电子邮箱" width="150" />
            <el-table-column prop="education" label="学历" width="100" />
            <el-table-column prop="bank_name" label="开户银行" width="150" />
            <el-table-column prop="bank_account" label="银行账号" width="180" />
            <el-table-column prop="bank_province" label="开户行省份" width="120" />
            <el-table-column prop="job_title" label="职务" width="100" />
          </el-table>

          <!-- 附件列表 -->
          <el-divider content-position="left">
            <span style="font-size: 14px; color: #606266;">附件列表 ({{ currentRow.attachment_count }})</span>
          </el-divider>
          
          <div v-if="currentRow.attachments && currentRow.attachments.length > 0">
            <el-table :data="currentRow.attachments" border stripe>
              <el-table-column type="index" label="序号" width="60" align="center" />
              <el-table-column prop="file_name" label="文件名" show-overflow-tooltip>
                <template #default="{ row }">
                  <el-link :href="getFileUrl(row.file_path)" target="_blank" :underline="false">
                    <span style="color: #409EFF;">{{ row.file_name }}</span>
                  </el-link>
                </template>
              </el-table-column>
              <el-table-column label="操作" width="150" align="center">
                <template #default="{ row }">
                  <el-button 
                    type="primary" 
                    size="small" 
                    text
                    @click="handleDownloadAttachment(row)"
                  >
                    下载
                  </el-button>
                </template>
              </el-table-column>
            </el-table>
          </div>
          <el-empty v-else description="暂无附件" :image-size="100" />
        </div>

        <template #footer>
          <el-button @click="detailDialogVisible = false">关闭</el-button>
          <el-button 
            v-if="currentRow && currentRow.status === 'pending'" 
            type="success" 
            @click="handleSubmit(currentRow)"
          >
            提交审批
          </el-button>
        </template>
      </el-dialog>

      <!-- 提交审批对话框 -->
      <el-dialog
        v-model="submitDialogVisible"
        title="提交人员变动审批"
        width="800px"
      >
        <el-alert
          title="提示"
          type="info"
          :closable="false"
          style="margin-bottom: 20px"
        >
          提交后将跳过第一节点（经办），直接由第二节点审批人进行审批
        </el-alert>

        <el-form :model="submitForm" label-width="100px" v-if="currentRow">
          <el-form-item label="项目名称">
            <el-input :value="currentRow.project?.name" disabled />
          </el-form-item>

          <el-form-item label="工资期间">
            <el-input :value="currentRow.month" disabled />
          </el-form-item>

          <el-form-item label="变动类型">
            <el-tag :type="currentRow.change_type === 'add' ? 'success' : 'warning'">
              {{ currentRow.change_type === 'add' ? '人员新增' : '人员减少' }}
            </el-tag>
          </el-form-item>

          <el-form-item label="人员数量">
            <span>{{ currentRow.personnel_count }} 人</span>
          </el-form-item>

          <el-form-item label="盖章方式" required>
            <el-radio-group v-model="submitForm.stamp_method">
              <el-radio value="online">线上盖章</el-radio>
              <el-radio value="offline">线下盖章</el-radio>
            </el-radio-group>
            <div style="margin-top: 8px; color: #909399; font-size: 12px;">
              线上盖章：系统自动在PDF上添加印章；线下盖章：需要手动在纸质文件上盖章
            </div>
          </el-form-item>

          <!-- 附件上传组件 -->
          <PaymentAttachmentUploader
            ref="attachmentUploaderRef"
            v-model:other-file-list="attachmentFileList"
            :show-invoice-upload="false"
            :other-limit="10"
            :show-form-generator="true"
            form-button-text="填写报销表格生成PDF"
            form-title="人员变动申请表"
          />
        </el-form>

        <template #footer>
          <el-button @click="submitDialogVisible = false">取消</el-button>
          <el-button 
            type="primary" 
            @click="handleConfirmSubmit"
            :loading="submitting"
          >
            提交审批
          </el-button>
        </template>
      </el-dialog>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh, Download } from '@element-plus/icons-vue'
import { useUserStore } from '@/stores/user'
import { useAccountSetStore } from '@/stores/accountSet'
import NoAccountSetTip from '@/components/NoAccountSetTip.vue'
import PaymentAttachmentUploader from '@/components/PaymentAttachmentUploader.vue'
import {
  getPersonnelChangeRequests,
  uploadPersonnelChangeAttachment,
  completePersonnelChangeSubmission,
  deletePersonnelChangeRequest
} from '@/api/personnelChangeRequest'
import { getProjects } from '@/api/projects'
import { getEmployees } from '@/api/employees'
import * as XLSX from 'xlsx'

const userStore = useUserStore()
const accountSetStore = useAccountSetStore()

const isAdmin = computed(() => userStore.userInfo?.role === 'admin')
const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)

// 搜索表单
const searchForm = reactive({
  month: null,
  project_id: null,
  change_type: null,
  status: null
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

// 提交审批对话框
const submitDialogVisible = ref(false)
const submitForm = reactive({
  stamp_method: 'online' // 默认线上盖章
})
const attachmentUploaderRef = ref(null)
const attachmentFileList = ref([])
const submitting = ref(false)

// 导出相关
const exporting = ref(false)
const enrichedPersonnelList = ref([])

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

// 状态类型映射
const getStatusType = (status) => {
  const map = {
    pending: 'warning',
    in_approval: 'warning',
    approved: 'success',
    rejected: 'danger'
  }
  return map[status] || 'info'
}

// 状态文本映射
const getStatusText = (status) => {
  const map = {
    pending: '待审批',
    in_approval: '审批中',
    approved: '已通过',
    rejected: '已拒绝'
  }
  return map[status] || '未知'
}

// 查询
const handleSearch = async () => {
  loading.value = true
  try {
    const params = {
      month: searchForm.month,
      project_id: searchForm.project_id,
      change_type: searchForm.change_type,
      status: searchForm.status,
      page: pagination.page,
      per_page: pagination.pageSize,
      current_account_set_id: accountSetStore.currentAccountSetId
    }
    const response = await getPersonnelChangeRequests(params)
    if (response && response.success) {
      tableData.value = response.data.data || []
      pagination.total = response.data.total || 0
    }
  } catch (error) {
    console.error('Load personnel change requests error:', error)
    ElMessage.error('加载人员变动申请列表失败')
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
  searchForm.change_type = null
  searchForm.status = null
  pagination.page = 1
  handleSearch()
}

// 查看详情
const handleView = async (row) => {
  currentRow.value = row
  detailDialogVisible.value = true
  
  // 根据身份证号查询员工档案信息
  await enrichPersonnelData(row.personnel_list)
}

// 根据身份证号查询员工档案信息并丰富数据
const enrichPersonnelData = async (personnelList) => {
  if (!personnelList || personnelList.length === 0) {
    enrichedPersonnelList.value = []
    return
  }

  try {
    // 获取所有员工数据 - 确保获取完整数据
    const response = await getEmployees({
      current_account_set_id: accountSetStore.currentAccountSetId,
      all: true,
      per_page: 1000 // 确保获取所有员工
    })
    
    if (!response.success || !response.data) {
      enrichedPersonnelList.value = personnelList.map(person => ({
        ...person,
        employee_number: '-',
        id_type: '身份证',
        country_region: '-',
        gender: '-',
        birth_date: '-',
        personnel_status: '-',
        employment_type: '-',
        other_notes: '-',
        annual_employment_status: '-',
        phone: '-',
        employment_date: '-',
        resignation_date: '-',
        is_disabled: false,
        is_martyr_family: false,
        is_elderly_alone: false,
        disability_cert_type: '-',
        disability_cert_number: '-',
        martyr_family_cert_number: '-',
        deduct_expense: false,
        personal_investment_amount: '-',
        personal_investment_ratio: '-',
        remarks: '-',
        chinese_name: '-',
        tax_matter: '-',
        birth_country: '-',
        first_entry_date: '-',
        expected_departure_date: '-',
        other_id_type: '-',
        other_id_number: '-',
        household_province: '-',
        household_city: '-',
        household_district: '-',
        household_address: '-',
        residence_province: '-',
        residence_city: '-',
        residence_district: '-',
        residence_address: '-',
        contact_province: '-',
        contact_city: '-',
        contact_district: '-',
        contact_address: '-',
        email_address: '-',
        education: '-',
        bank_name: '-',
        bank_account: '-',
        bank_province: '-',
        job_title: '-'
      }))
      return
    }

    const employees = Array.isArray(response.data) ? response.data : (response.data.data || [])
    
    // 为每个人员匹配员工档案信息
    enrichedPersonnelList.value = personnelList.map(person => {
      const employee = employees.find(emp => emp.id_number === person.id_card)
      
      if (employee) {
        return {
          ...person,
          employee_number: employee.employee_number || '-',
          id_type: '身份证',
          country_region: employee.country_region || '-',
          gender: employee.gender === 'male' ? '男' : (employee.gender === 'female' ? '女' : (employee.gender || '-')),
          birth_date: employee.birth_date ? employee.birth_date.split(' ')[0] : '-',
          personnel_status: employee.personnel_status || '-',
          employment_type: employee.employment_type || '-',
          other_notes: employee.other_notes || '-',
          annual_employment_status: employee.annual_employment_status || '-',
          phone: employee.phone || '-',
          employment_date: employee.employment_date ? employee.employment_date.split(' ')[0] : '-',
          resignation_date: employee.resignation_date ? employee.resignation_date.split(' ')[0] : '-',
          is_disabled: employee.is_disabled || false,
          is_martyr_family: employee.is_martyr_family || false,
          is_elderly_alone: employee.is_elderly_alone || false,
          disability_cert_type: employee.disability_cert_type || '-',
          disability_cert_number: employee.disability_cert_number || '-',
          martyr_family_cert_number: employee.martyr_family_cert_number || '-',
          deduct_expense: employee.deduct_expense || false,
          personal_investment_amount: employee.personal_investment_amount || '-',
          personal_investment_ratio: employee.personal_investment_ratio || '-',
          remarks: employee.remarks || '-',
          chinese_name: employee.chinese_name || '-',
          tax_matter: employee.tax_matter || '-',
          birth_country: employee.birth_country || '-',
          first_entry_date: employee.first_entry_date ? employee.first_entry_date.split(' ')[0] : '-',
          expected_departure_date: employee.expected_departure_date ? employee.expected_departure_date.split(' ')[0] : '-',
          other_id_type: employee.other_id_type || '-',
          other_id_number: employee.other_id_number || '-',
          household_province: employee.household_province || '-',
          household_city: employee.household_city || '-',
          household_district: employee.household_district || '-',
          household_address: employee.household_address || '-',
          residence_province: employee.residence_province || '-',
          residence_city: employee.residence_city || '-',
          residence_district: employee.residence_district || '-',
          residence_address: employee.residence_address || '-',
          contact_province: employee.contact_province || '-',
          contact_city: employee.contact_city || '-',
          contact_district: employee.contact_district || '-',
          contact_address: employee.contact_address || '-',
          email_address: employee.email_address || employee.email || '-',
          education: employee.education || '-',
          bank_name: employee.bank_name || '-',
          bank_account: employee.bank_account || '-',
          bank_province: employee.bank_province || '-',
          job_title: employee.job_title || '-'
        }
      } else {
        // 如果没有找到对应的员工档案，返回默认值
        return {
          ...person,
          employee_number: '-',
          id_type: '身份证',
          country_region: '-',
          gender: '-',
          birth_date: '-',
          personnel_status: '-',
          employment_type: '-',
          other_notes: '-',
          annual_employment_status: '-',
          phone: '-',
          employment_date: '-',
          resignation_date: '-',
          is_disabled: false,
          is_martyr_family: false,
          is_elderly_alone: false,
          disability_cert_type: '-',
          disability_cert_number: '-',
          martyr_family_cert_number: '-',
          deduct_expense: false,
          personal_investment_amount: '-',
          personal_investment_ratio: '-',
          remarks: '-',
          chinese_name: '-',
          tax_matter: '-',
          birth_country: '-',
          first_entry_date: '-',
          expected_departure_date: '-',
          other_id_type: '-',
          other_id_number: '-',
          household_province: '-',
          household_city: '-',
          household_district: '-',
          household_address: '-',
          residence_province: '-',
          residence_city: '-',
          residence_district: '-',
          residence_address: '-',
          contact_province: '-',
          contact_city: '-',
          contact_district: '-',
          contact_address: '-',
          email_address: '-',
          education: '-',
          bank_name: '-',
          bank_account: '-',
          bank_province: '-',
          job_title: '-'
        }
      }
    })
  } catch (error) {
    console.error('查询员工档案信息失败:', error)
    ElMessage.error('查询员工档案信息失败')
    enrichedPersonnelList.value = personnelList
  }
}

// 导出Excel
const exportToExcel = () => {
  if (!enrichedPersonnelList.value || enrichedPersonnelList.value.length === 0) {
    ElMessage.warning('没有可导出的数据')
    return
  }

  try {
    exporting.value = true

    // 定义Excel表头
    const headers = [
      '序号', '工号', '*姓名', '*证件类型', '*证件号码', '*国籍(地区)', '*性别', '*出生日期',
      '人员状态', '*任职受雇从业类型', '其他情况说明', '入职年度就业情形', '手机号码',
      '任职受雇从业日期', '离职日期', '是否残疾', '是否烈属', '是否孤老', '残疾证件类型',
      '残疾证号', '烈属证号', '是否扣除减除费用', '个人投资额', '个人投资比例(%)',
      '备注', '中文名', '涉税事由', '出生国家(地区)', '首次入境时间', '预计离境时间',
      '其他证件类型', '其他证件号码', '户籍所在地（省）', '户籍所在地（市）',
      '户籍所在地（区县）', '户籍所在地（详细地址）', '经常居住地（省）', '经常居住地（市）',
      '经常居住地（区县）', '经常居住地（详细地址）', '联系地址（省）', '联系地址（市）',
      '联系地址（区县）', '联系地址（详细地址）', '电子邮箱', '学历', '开户银行',
      '银行账号', '开户行省份', '职务'
    ]

    // 转换数据
    const excelData = enrichedPersonnelList.value.map((person, index) => [
      index + 1,
      person.employee_number || '-',
      person.name || '-',
      person.id_type || '身份证',
      person.id_card || '-',
      person.country_region || '-',
      person.gender || '-',
      person.birth_date || '-',
      person.personnel_status || '-',
      person.employment_type || '-',
      person.other_notes || '-',
      person.annual_employment_status || '-',
      person.phone || '-',
      person.employment_date || '-',
      person.resignation_date || '-',
      person.is_disabled ? '是' : '否',
      person.is_martyr_family ? '是' : '否',
      person.is_elderly_alone ? '是' : '否',
      person.disability_cert_type || '-',
      person.disability_cert_number || '-',
      person.martyr_family_cert_number || '-',
      person.deduct_expense ? '是' : '否',
      person.personal_investment_amount || '-',
      person.personal_investment_ratio || '-',
      person.remarks || '-',
      person.chinese_name || '-',
      person.tax_matter || '-',
      person.birth_country || '-',
      person.first_entry_date || '-',
      person.expected_departure_date || '-',
      person.other_id_type || '-',
      person.other_id_number || '-',
      person.household_province || '-',
      person.household_city || '-',
      person.household_district || '-',
      person.household_address || '-',
      person.residence_province || '-',
      person.residence_city || '-',
      person.residence_district || '-',
      person.residence_address || '-',
      person.contact_province || '-',
      person.contact_city || '-',
      person.contact_district || '-',
      person.contact_address || '-',
      person.email_address || '-',
      person.education || '-',
      person.bank_name || '-',
      person.bank_account || '-',
      person.bank_province || '-',
      person.job_title || '-'
    ])

    // 创建工作簿
    const wb = XLSX.utils.book_new()
    const ws = XLSX.utils.aoa_to_sheet([headers, ...excelData])

    // 设置列宽
    const colWidths = headers.map(() => ({ wch: 15 }))
    ws['!cols'] = colWidths

    // 添加工作表
    XLSX.utils.book_append_sheet(wb, ws, '人员信息')

    // 生成文件名
    const fileName = `人员汇总申请_${currentRow.value?.project?.name || '未知项目'}_${currentRow.value?.month || '未知月份'}_${new Date().toISOString().slice(0, 10)}.xlsx`

    // 导出文件
    XLSX.writeFile(wb, fileName)
    
    ElMessage.success('Excel文件导出成功')
  } catch (error) {
    console.error('导出Excel失败:', error)
    ElMessage.error('导出Excel失败')
  } finally {
    exporting.value = false
  }
}

// 获取文件完整URL
const getFileUrl = (filePath) => {
  if (!filePath) return ''
  if (filePath.startsWith('http://') || filePath.startsWith('https://')) {
    return filePath
  }
  const baseUrl = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'
  return `${baseUrl}/storage/${filePath}`
}

// 下载附件
const handleDownloadAttachment = (attachment) => {
  const fileUrl = getFileUrl(attachment.file_path)
  
  const link = document.createElement('a')
  link.href = fileUrl
  link.download = attachment.file_name
  link.target = '_blank'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  ElMessage.success('开始下载文件')
}

// 提交审批
const handleSubmit = (row) => {
  currentRow.value = row
  attachmentFileList.value = []
  submitDialogVisible.value = true
}

// 上传附件到服务器
const uploadFileToServer = async (file, requestId) => {
  try {
    const formData = new FormData()
    formData.append('file', file.raw)
    formData.append('personnel_change_request_id', requestId)
    
    await uploadPersonnelChangeAttachment(formData)
    return true
  } catch (error) {
    console.error(`上传文件 ${file.name} 失败:`, error)
    ElMessage.error(`上传文件 ${file.name} 失败`)
    return false
  }
}

// 确认提交
const handleConfirmSubmit = async () => {
  try {
    submitting.value = true

    const requestId = currentRow.value.id

    // 上传附件（如果有）
    if (attachmentFileList.value.length > 0) {
      ElMessage.info('正在上传附件...')
      let uploadCount = 0
      for (const file of attachmentFileList.value) {
        const success = await uploadFileToServer(file, requestId)
        if (success) {
          uploadCount++
        }
      }
      
      if (uploadCount > 0) {
        ElMessage.success(`已上传 ${uploadCount} 个附件`)
      }
    }

    // 完成提交，创建审批流程
    ElMessage.info('正在创建审批流程...')
    const completeResponse = await completePersonnelChangeSubmission({
      personnel_change_request_id: requestId,
      current_account_set_id: accountSetStore.currentAccountSetId,
      stamp_method: submitForm.stamp_method // 传递盖章方式
    })

    if (completeResponse.success) {
      ElMessage.success('人员变动申请已提交！审批流程已创建（跳过第一节点）')
    } else {
      ElMessage.warning(`创建审批流程失败: ${completeResponse.message}`)
    }

    submitDialogVisible.value = false
    detailDialogVisible.value = false
    // 重置盖章方式为默认值
    submitForm.stamp_method = 'online'
    handleSearch()
  } catch (error) {
    console.error('Submit error:', error)
    ElMessage.error(error.response?.data?.message || '提交失败')
  } finally {
    submitting.value = false
  }
}

// 删除
const handleDelete = (row) => {
  ElMessageBox.confirm(
    `确定删除该人员变动申请吗？`,
    '删除确认',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    }
  ).then(async () => {
    try {
      await deletePersonnelChangeRequest(row.id)
      ElMessage.success('删除成功')
      handleSearch()
    } catch (error) {
      console.error('Delete error:', error)
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }).catch(() => {})
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
.personnel-change-requests-page {
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
</style>

