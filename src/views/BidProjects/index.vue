<template>
  <div class="bid-projects-page">
    <div class="page-header">
      <h1>投标项目管理</h1>
      <div class="header-actions">
        <el-button type="primary" @click="handleCreate" v-if="canCreateBidProject && !isFirstApprovalNode">
          <el-icon><Plus /></el-icon>
          新建项目
        </el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="20" style="margin-bottom: 20px;">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-icon" style="background: #ecf5ff; color: #409eff;">
              <el-icon :size="30"><Document /></el-icon>
            </div>
            <div class="stat-content">
              <div class="stat-label">项目总数</div>
              <div class="stat-value">{{ statistics.total || 0 }}</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-icon" style="background: #f0f9ff; color: #67c23a;">
              <el-icon :size="30"><CircleCheck /></el-icon>
            </div>
            <div class="stat-content">
              <div class="stat-label">已中标</div>
              <div class="stat-value">{{ statistics.won || 0 }}</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-icon" style="background: #fef0f0; color: #f56c6c;">
              <el-icon :size="30"><Warning /></el-icon>
            </div>
            <div class="stat-content">
              <div class="stat-label">即将到期</div>
              <div class="stat-value">{{ statistics.deadline_approaching || 0 }}</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-icon" style="background: #fdf6ec; color: #e6a23c;">
              <el-icon :size="30"><Clock /></el-icon>
            </div>
            <div class="stat-content">
              <div class="stat-label">准备中</div>
              <div class="stat-value">{{ statistics.preparing || 0 }}</div>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 搜索筛选 -->
    <el-card shadow="never" style="margin-bottom: 20px;">
      <el-form :inline="true" :model="searchForm" class="search-form">
        <el-form-item label="关键词">
          <el-input
            v-model="searchForm.keyword"
            placeholder="项目名称/编号/招标单位"
            clearable
            style="width: 240px;"
          />
        </el-form-item>

        <el-form-item label="项目状态">
          <el-select v-model="searchForm.status" placeholder="请选择" clearable style="width: 150px;">
            <el-option label="准备中" value="preparing" />
            <el-option label="已提交" value="submitted" />
            <el-option label="已开标" value="opened" />
            <el-option label="评标中" value="evaluating" />
            <el-option label="已中标" value="won" />
            <el-option label="未中标" value="lost" />
            <el-option label="已放弃" value="abandoned" />
            <el-option label="已签约" value="contracted" />
            <el-option label="已完成" value="completed" />
            <el-option label="已取消" value="cancelled" />
          </el-select>
        </el-form-item>

        <el-form-item label="投标结果">
          <el-select v-model="searchForm.bid_result" placeholder="请选择" clearable style="width: 120px;">
            <el-option label="中标" value="won" />
            <el-option label="未中标" value="lost" />
            <el-option label="放弃" value="abandoned" />
          </el-select>
        </el-form-item>

        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 项目列表 -->
    <el-card shadow="never">
      <template #header>
        <div class="card-header">
          <span class="title">项目列表</span>
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
        <el-table-column prop="project_code" label="项目编号" width="160" fixed />
        <el-table-column prop="project_name" label="项目名称" min-width="200" show-overflow-tooltip />
        <el-table-column prop="client_name" label="招标单位" min-width="180" show-overflow-tooltip />
        <el-table-column prop="project_budget" label="项目预算" width="130" align="right">
          <template #default="scope">
            <span v-if="scope.row.project_budget">{{ formatMoney(scope.row.project_budget) }}</span>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="bid_deadline" label="投标截止时间" width="160">
          <template #default="scope">
            <div v-if="scope.row.bid_deadline">
              <div>{{ formatDateTime(scope.row.bid_deadline) }}</div>
              <el-tag v-if="scope.row.is_overdue" type="danger" size="small">已过期</el-tag>
              <el-tag v-else-if="scope.row.is_deadline_approaching" type="warning" size="small">即将到期</el-tag>
            </div>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="scope">
            <el-tag :type="getStatusType(scope.row.status)">
              {{ scope.row.status_text }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="bid_result" label="投标结果" width="100">
          <template #default="scope">
            <el-tag v-if="scope.row.bid_result" :type="getResultType(scope.row.bid_result)">
              {{ scope.row.result_text }}
            </el-tag>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="240" fixed="right">
          <template #default="scope">
            <el-button link type="primary" size="small" @click="handleView(scope.row)">
              查看详情
            </el-button>
            <el-button link type="primary" size="small" @click="handleEdit(scope.row)" v-if="canEditBidProject && !isFirstApprovalNode">
              编辑
            </el-button>
            <el-button link type="warning" size="small" @click="handleUpdateStatus(scope.row)" v-if="canEditBidProject">
              更新状态
            </el-button>
            <el-button link type="danger" size="small" @click="handleDelete(scope.row)" v-if="canDeleteBidProject">
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
        :page-sizes="[15, 30, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        style="margin-top: 20px; justify-content: flex-end;"
        @size-change="handleSearch"
        @current-change="handleSearch"
      />
    </el-card>

    <!-- 创建/编辑对话框 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="900px"
      :close-on-click-modal="false"
    >
      <el-form
        ref="formRef"
        :model="form"
        :rules="formRules"
        label-width="120px"
      >
        <el-tabs v-model="activeTab">
          <el-tab-pane label="基本信息" name="basic">
            <el-form-item label="项目名称" prop="project_name">
              <el-input v-model="form.project_name" placeholder="请输入项目名称" />
            </el-form-item>

            <el-row :gutter="20">
              <el-col :span="12">
                <el-form-item label="招标单位" prop="client_name">
                  <el-input v-model="form.client_name" placeholder="请输入招标单位名称" />
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="联系人" prop="client_contact">
                  <el-input v-model="form.client_contact" placeholder="请输入联系人" />
                </el-form-item>
              </el-col>
            </el-row>

            <el-row :gutter="20">
              <el-col :span="12">
                <el-form-item label="联系电话" prop="client_phone">
                  <el-input v-model="form.client_phone" placeholder="请输入联系电话" />
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="项目地点" prop="project_location">
                  <el-input v-model="form.project_location" placeholder="请输入项目地点" />
                </el-form-item>
              </el-col>
            </el-row>

            <el-row :gutter="20">
              <el-col :span="12">
                <el-form-item label="项目预算" prop="project_budget">
                  <el-input v-model="form.project_budget" placeholder="请输入项目预算金额">
                    <template #suffix>元</template>
                  </el-input>
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="服务期限" prop="service_period">
                  <el-input v-model="form.service_period" placeholder="如：1年、3年" />
                </el-form-item>
              </el-col>
            </el-row>

            <el-form-item label="项目规模" prop="project_scale">
              <el-input
                v-model="form.project_scale"
                type="textarea"
                :rows="3"
                placeholder="请输入项目规模描述"
              />
            </el-form-item>
          </el-tab-pane>

          <el-tab-pane label="投标信息" name="bid">
            <el-row :gutter="20">
              <el-col :span="12">
                <el-form-item label="投标截止时间" prop="bid_deadline">
                  <el-date-picker
                    v-model="form.bid_deadline"
                    type="datetime"
                    placeholder="选择截止时间"
                    style="width: 100%;"
                    format="YYYY-MM-DD HH:mm"
                    value-format="YYYY-MM-DD HH:mm:ss"
                  />
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="开标时间" prop="bid_opening_time">
                  <el-date-picker
                    v-model="form.bid_opening_time"
                    type="datetime"
                    placeholder="选择开标时间"
                    style="width: 100%;"
                    format="YYYY-MM-DD HH:mm"
                    value-format="YYYY-MM-DD HH:mm:ss"
                  />
                </el-form-item>
              </el-col>
            </el-row>

            <el-row :gutter="20">
              <el-col :span="12">
                <el-form-item label="招标方式" prop="bid_method">
                  <el-select v-model="form.bid_method" placeholder="请选择" style="width: 100%;">
                    <el-option label="公开招标" value="公开招标" />
                    <el-option label="邀请招标" value="邀请招标" />
                    <el-option label="竞争性谈判" value="竞争性谈判" />
                    <el-option label="竞争性磋商" value="竞争性磋商" />
                    <el-option label="单一来源" value="单一来源" />
                  </el-select>
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="信息来源" prop="information_source">
                  <el-input v-model="form.information_source" placeholder="如：招标网、客户推荐等" />
                </el-form-item>
              </el-col>
            </el-row>

            <el-row :gutter="20">
              <el-col :span="12">
                <el-form-item label="投标保证金" prop="bid_bond">
                  <el-input v-model="form.bid_bond" placeholder="请输入保证金金额">
                    <template #suffix>元</template>
                  </el-input>
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="保证金缴纳时间" prop="bond_paid_at">
                  <el-date-picker
                    v-model="form.bond_paid_at"
                    type="datetime"
                    placeholder="选择缴纳时间"
                    style="width: 100%;"
                    format="YYYY-MM-DD HH:mm"
                    value-format="YYYY-MM-DD HH:mm:ss"
                  />
                </el-form-item>
              </el-col>
            </el-row>

            <el-row :gutter="20">
              <el-col :span="12">
                <el-form-item label="负责人" prop="responsible_person">
                  <el-input v-model="form.responsible_person" placeholder="请输入负责人" />
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="负责部门" prop="responsible_department">
                  <el-input v-model="form.responsible_department" placeholder="请输入负责部门" />
                </el-form-item>
              </el-col>
            </el-row>

            <el-form-item label="备注" prop="remarks">
              <el-input
                v-model="form.remarks"
                type="textarea"
                :rows="3"
                placeholder="请输入备注信息"
              />
            </el-form-item>
          </el-tab-pane>
        </el-tabs>
      </el-form>

      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitLoading">
          确定
        </el-button>
      </template>
    </el-dialog>

    <!-- 更新状态对话框 -->
    <el-dialog
      v-model="statusDialogVisible"
      title="更新项目状态"
      width="500px"
    >
      <el-form :model="statusForm" label-width="100px">
        <el-form-item label="当前状态">
          <el-tag>{{ currentProject?.status_text }}</el-tag>
        </el-form-item>
        <el-form-item label="新状态" required>
          <el-select v-model="statusForm.status" placeholder="请选择新状态" style="width: 100%;">
            <el-option label="准备中" value="preparing" />
            <el-option label="已提交" value="submitted" />
            <el-option label="已开标" value="opened" />
            <el-option label="评标中" value="evaluating" />
            <el-option label="已中标" value="won" />
            <el-option label="未中标" value="lost" />
            <el-option label="已放弃" value="abandoned" />
            <el-option label="已签约" value="contracted" />
            <el-option label="已完成" value="completed" />
            <el-option label="已取消" value="cancelled" />
          </el-select>
        </el-form-item>
        <el-form-item label="备注">
          <el-input
            v-model="statusForm.remarks"
            type="textarea"
            :rows="3"
            placeholder="请输入变更原因或备注"
          />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="statusDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleConfirmUpdateStatus" :loading="submitLoading">
          确定
        </el-button>
      </template>
    </el-dialog>

    <!-- 详情对话框 -->
    <BidProjectDetail
      v-model="detailDialogVisible"
      :project-id="currentProjectId"
      @refresh="handleSearch"
    />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh, Document, CircleCheck, Warning, Clock } from '@element-plus/icons-vue'
import { useUserStore } from '@/stores/user'
import { useAccountSetStore } from '@/stores/accountSet'
import { usePermissionStore } from '@/stores/permission'
import request from '@/api/request'
import {
  getBidProjects,
  createBidProject,
  updateBidProject,
  deleteBidProject,
  updateBidProjectStatus,
  getBidStatistics,
  getBidCategories
} from '@/api/bidProject'
import BidProjectDetail from './components/BidProjectDetail.vue'

const userStore = useUserStore()
const accountSetStore = useAccountSetStore()
const permissionStore = usePermissionStore()

// 权限控制
const canCreateBidProject = computed(() => permissionStore.hasPermission('bid_projects.create'))
const canEditBidProject = computed(() => permissionStore.hasPermission('bid_projects.edit'))
const canDeleteBidProject = computed(() => permissionStore.hasPermission('bid_projects.delete'))

// 数据
const loading = ref(false)
const tableData = ref([])
const statistics = ref({})

// 用户审批级别
const userApprovalLevel = ref(null)

// 判断是否为第1个审批节点（发起人）
const isFirstApprovalNode = computed(() => {
  return userApprovalLevel.value === null || userApprovalLevel.value === undefined
})

// 搜索
const searchForm = reactive({
  keyword: '',
  status: '',
  bid_result: ''
})

// 分页
const pagination = reactive({
  page: 1,
  pageSize: 15,
  total: 0
})

// 对话框
const dialogVisible = ref(false)
const dialogTitle = computed(() => form.id ? '编辑项目' : '新建项目')
const submitLoading = ref(false)
const activeTab = ref('basic')

// 表单
const formRef = ref(null)
const form = reactive({
  id: null,
  project_name: '',
  client_name: '',
  client_contact: '',
  client_phone: '',
  project_budget: '',
  bid_bond: '',
  bond_paid_at: '',
  project_location: '',
  project_scale: '',
  service_period: '',
  bid_deadline: '',
  bid_opening_time: '',
  bid_method: '公开招标',
  information_source: '',
  responsible_person: '',
  responsible_department: '',
  remarks: ''
})

const formRules = {
  project_name: [{ required: true, message: '请输入项目名称', trigger: 'blur' }]
}

// 状态对话框
const statusDialogVisible = ref(false)
const currentProject = ref(null)
const statusForm = reactive({
  status: '',
  remarks: ''
})

// 详情对话框
const detailDialogVisible = ref(false)
const currentProjectId = ref(null)

// 获取用户的审批级别
const loadUserApprovalLevel = async () => {
  const user = userStore.userInfo
  const currentAccountSet = accountSetStore.currentAccountSet
  
  if (!user || !currentAccountSet?.id) {
    userApprovalLevel.value = null
    return
  }
  
  try {
    const response = await request({
      url: `/account-sets/${currentAccountSet.id}/users`,
      method: 'get'
    })
    
    if (response.success && response.data) {
      const currentUser = response.data.find(u => u.id === user.id)
      if (currentUser) {
        userApprovalLevel.value = currentUser.approval_level
      } else {
        userApprovalLevel.value = null
      }
    }
  } catch (error) {
    console.error('获取用户审批级别失败:', error)
    userApprovalLevel.value = null
  }
}

// 初始化
onMounted(() => {
  loadUserApprovalLevel()
  loadStatistics()
  handleSearch()
})

// 加载统计数据
const loadStatistics = async () => {
  try {
    const response = await getBidStatistics()
    if (response && response.success) {
      statistics.value = response.data
    }
  } catch (error) {
    console.error('Load statistics error:', error)
  }
}

// 查询
const handleSearch = async () => {
  loading.value = true
  try {
    const response = await getBidProjects({
      ...searchForm,
      page: pagination.page,
      page_size: pagination.pageSize
    })
    if (response && response.success) {
      tableData.value = response.data.data || []
      pagination.total = response.data.total || 0
    }
  } catch (error) {
    console.error('Load projects error:', error)
    ElMessage.error('加载项目列表失败')
  } finally {
    loading.value = false
  }
}

// 重置
const handleReset = () => {
  Object.keys(searchForm).forEach(key => {
    searchForm[key] = ''
  })
  pagination.page = 1
  handleSearch()
}

// 新建
const handleCreate = () => {
  resetForm()
  dialogVisible.value = true
}

// 编辑
const handleEdit = (row) => {
  Object.keys(form).forEach(key => {
    form[key] = row[key] || ''
  })
  form.id = row.id
  activeTab.value = 'basic'
  dialogVisible.value = true
}

// 查看详情
const handleView = (row) => {
  currentProjectId.value = row.id
  detailDialogVisible.value = true
}

// 更新状态
const handleUpdateStatus = (row) => {
  currentProject.value = row
  statusForm.status = row.status
  statusForm.remarks = ''
  statusDialogVisible.value = true
}

// 确认更新状态
const handleConfirmUpdateStatus = async () => {
  if (!statusForm.status) {
    ElMessage.warning('请选择新状态')
    return
  }

  submitLoading.value = true
  try {
    const response = await updateBidProjectStatus(currentProject.value.id, statusForm)
    if (response && response.success) {
      ElMessage.success('状态更新成功')
      statusDialogVisible.value = false
      handleSearch()
      loadStatistics()
    }
  } catch (error) {
    console.error('Update status error:', error)
    ElMessage.error('状态更新失败')
  } finally {
    submitLoading.value = false
  }
}

// 提交表单
const handleSubmit = async () => {
  const valid = await formRef.value?.validate()
  if (!valid) return

  submitLoading.value = true
  try {
    const api = form.id ? updateBidProject : createBidProject
    const params = form.id ? [form.id, form] : [form]
    
    const response = await api(...params)
    if (response && response.success) {
      ElMessage.success(form.id ? '更新成功' : '创建成功')
      dialogVisible.value = false
      handleSearch()
      loadStatistics()
    }
  } catch (error) {
    console.error('Submit error:', error)
    ElMessage.error(form.id ? '更新失败' : '创建失败')
  } finally {
    submitLoading.value = false
  }
}

// 删除
const handleDelete = (row) => {
  ElMessageBox.confirm(
    `确定删除项目「${row.project_name}」吗？此操作将删除所有相关数据且不可恢复！`,
    '删除确认',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    }
  ).then(async () => {
    try {
      const response = await deleteBidProject(row.id)
      if (response && response.success) {
        ElMessage.success('删除成功')
        handleSearch()
        loadStatistics()
      }
    } catch (error) {
      console.error('Delete error:', error)
      ElMessage.error('删除失败')
    }
  }).catch(() => {})
}

// 重置表单
const resetForm = () => {
  Object.keys(form).forEach(key => {
    form[key] = key === 'bid_method' ? '公开招标' : ''
  })
  form.id = null
  formRef.value?.clearValidate()
}

// 格式化金额
const formatMoney = (value) => {
  if (!value) return '¥0.00'
  return '¥' + parseFloat(value).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// 格式化日期时间
const formatDateTime = (value) => {
  if (!value) return ''
  return value.replace('T', ' ').substring(0, 16)
}

// 状态类型
const getStatusType = (status) => {
  const typeMap = {
    preparing: 'info',
    submitted: 'warning',
    opened: 'warning',
    evaluating: 'warning',
    won: 'success',
    lost: 'danger',
    abandoned: 'info',
    contracted: 'success',
    completed: 'success',
    cancelled: 'info'
  }
  return typeMap[status] || 'info'
}

// 结果类型
const getResultType = (result) => {
  const typeMap = {
    won: 'success',
    lost: 'danger',
    abandoned: 'info'
  }
  return typeMap[result] || 'info'
}
</script>

<style scoped>
.bid-projects-page {
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.page-header h1 {
  margin: 0;
  font-size: 24px;
  color: #303133;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 16px;
}

.stat-icon {
  width: 60px;
  height: 60px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stat-content {
  flex: 1;
}

.stat-label {
  font-size: 14px;
  color: #909399;
  margin-bottom: 8px;
}

.stat-value {
  font-size: 24px;
  font-weight: bold;
  color: #303133;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header .title {
  font-size: 16px;
  font-weight: 600;
  color: #303133;
}

.search-form {
  margin-bottom: 0;
}
</style>

