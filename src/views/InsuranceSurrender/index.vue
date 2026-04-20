<template>
  <div class="insurance-surrender-container">
    <div class="page-header">
      <h2>商业险管理</h2>
    </div>

    <el-tabs v-model="activeTab" @tab-change="handleTabChange">
      <!-- 退保列表标签页 -->
      <el-tab-pane label="退保列表" name="list">
        <el-card class="filter-card">
          <el-form :inline="true" :model="filterForm">
            <el-form-item label="状态">
              <el-select v-model="filterForm.status" clearable placeholder="全部" style="width: 180px">
                <el-option label="待业务上传" value="pending_business" />
                <el-option label="待财务上传" value="business_done" />
                <el-option label="已完成" value="finance_done" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="loadList">查询</el-button>
              <el-button @click="reset">重置</el-button>
              <!-- 只有管理员和超级管理员可以创建退保 -->
              <el-button v-if="canCreate" type="success" @click="openCreateDialog">创建退保</el-button>
            </el-form-item>
          </el-form>
        </el-card>

        <el-card class="table-card" style="margin-top: 16px;">
          <el-table :data="list" v-loading="loading" border stripe style="width: 100%">
            <el-table-column type="index" label="序号" width="60" />
            <el-table-column label="保单" min-width="260">
              <template #default="{ row }">
                <div style="font-weight: 600;">{{ row.policy?.policy_name || row.policy?.name || '-' }}</div>
                <div style="color:#909399;font-size:12px;">
                  保单号：{{ row.policy?.policy_number || '-' }} / 种类：{{ row.policy?.type?.name || '-' }}
                </div>
              </template>
            </el-table-column>
            <el-table-column label="项目" width="180">
              <template #default="{ row }">
                {{ row.project?.name || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="退保金额" width="120" align="right">
              <template #default="{ row }">
                <span v-if="row.surrender_amount !== null && row.surrender_amount !== undefined">¥{{ row.surrender_amount }}</span>
                <span v-else style="color:#C0C4CC;">-</span>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="120">
              <template #default="{ row }">
                <el-tag v-if="row.status === 'pending_business'" type="warning">待业务</el-tag>
                <el-tag v-else-if="row.status === 'business_done'" type="info">待财务</el-tag>
                <el-tag v-else-if="row.status === 'finance_done'" type="success">已完成</el-tag>
                <el-tag v-else type="danger">未知</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="创建时间" width="170">
              <template #default="{ row }">
                {{ row.created_at || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="操作" width="180" fixed="right">
              <template #default="{ row }">
                <el-button size="small" @click="openDetail(row)">详情/处理</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-tab-pane>

      <!-- 保单统计标签页 -->
      <el-tab-pane label="保单统计" name="statistics">
        <el-card class="filter-card">
          <el-form :inline="true" :model="statisticsFilter">
            <el-form-item label="保单">
              <el-select v-model="statisticsFilter.policy_id" clearable placeholder="全部保单" style="width: 220px" filterable>
                <el-option 
                  v-for="policy in availablePolicies" 
                  :key="policy.id" 
                  :label="policy.policy_name" 
                  :value="policy.id" 
                />
              </el-select>
            </el-form-item>
            <el-form-item label="月份范围">
              <el-date-picker
                v-model="statisticsFilter.monthRange"
                type="monthrange"
                range-separator="至"
                start-placeholder="开始月份"
                end-placeholder="结束月份"
                format="YYYY-MM"
                value-format="YYYY-MM"
                style="width: 300px"
              />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="loadStatistics">查询</el-button>
              <el-button @click="resetStatistics">重置</el-button>
            </el-form-item>
          </el-form>
        </el-card>

        <el-card class="table-card" style="margin-top: 16px;">
          <div v-loading="statisticsLoading">
            <div v-if="statistics.length === 0" style="text-align: center; padding: 40px; color: #909399;">
              <el-empty description="暂无统计数据" />
            </div>
            <div v-else>
              <el-collapse v-model="activeCollapse" accordion>
                <el-collapse-item 
                  v-for="policy in statistics" 
                  :key="policy.policy_id" 
                  :name="policy.policy_id"
                >
                  <template #title>
                    <div style="display: flex; align-items: center; width: 100%;">
                      <el-icon style="margin-right: 8px;"><Document /></el-icon>
                      <span style="font-weight: 600; font-size: 15px;">{{ policy.policy_name }}</span>
                      <el-tag size="small" style="margin-left: 12px;">{{ policy.insurance_type }}</el-tag>
                      <span style="margin-left: auto; color: #909399; font-size: 13px;">
                        共 {{ policy.months.length }} 个月份
                      </span>
                    </div>
                  </template>
                  
                  <el-table :data="policy.months" border stripe>
                    <el-table-column label="月份" width="120" prop="month" />
                    <el-table-column label="参保人数" width="120" align="right">
                      <template #default="{ row }">
                        <span style="font-weight: 600; color: #409EFF;">{{ row.count }}</span> 人
                      </template>
                    </el-table-column>
                    <el-table-column label="总金额" width="150" align="right">
                      <template #default="{ row }">
                        <span style="font-weight: 600; color: #67C23A;">¥{{ row.total_amount.toFixed(2) }}</span>
                      </template>
                    </el-table-column>
                    <el-table-column label="人均金额" width="150" align="right">
                      <template #default="{ row }">
                        ¥{{ (row.total_amount / row.count).toFixed(2) }}
                      </template>
                    </el-table-column>
                    <!-- 操作列已隐藏 -->
                    <!-- <el-table-column label="操作" width="120">
                      <template #default="{ row }">
                        <el-button size="small" type="primary" link @click="showEmployeeDetail(row)">
                          查看明细
                        </el-button>
                      </template>
                    </el-table-column> -->
                  </el-table>
                </el-collapse-item>
              </el-collapse>
            </div>
          </div>
        </el-card>
      </el-tab-pane>
    </el-tabs>

    <el-dialog v-model="detailVisible" title="退保详情/处理" width="900px">
      <div v-if="current">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="保单">
            {{ current.policy?.policy_name || current.policy?.name || '-' }}
            <div style="color:#909399;font-size:12px;">{{ current.policy?.policy_number || '-' }}</div>
          </el-descriptions-item>
          <el-descriptions-item label="项目">{{ current.project?.name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="状态">{{ statusText(current.status) }}</el-descriptions-item>
          <el-descriptions-item label="退保金额">
            <span v-if="current.surrender_amount !== null && current.surrender_amount !== undefined">¥{{ current.surrender_amount }}</span>
            <span v-else style="color:#C0C4CC;">-</span>
          </el-descriptions-item>
        </el-descriptions>

        <div style="margin-top: 16px;">
          <el-alert
            :title="current.status === 'pending_business' ? '业务：上传退保保单页并填写退保金额后提交' : (current.status === 'business_done' ? '财务：上传收款回单后提交完成' : '已完成')"
            type="info"
            :closable="false"
            show-icon
          />
        </div>

        <div style="margin-top: 16px;">
          <h4 style="margin: 0 0 8px;">附件</h4>
          <div v-if="current.attachments && current.attachments.length > 0" class="files-list">
            <div v-for="file in current.attachments" :key="file.id" class="file-item">
              <div class="file-info">
                <el-icon><Document /></el-icon>
                <span class="filename" :title="file.filename">{{ file.filename }}</span>
                <span class="file-meta">{{ file.type === 'policy_page' ? '保单页' : '回单' }}</span>
                <el-tag size="small" type="primary">{{ formatFileSize(file.file_size) }}</el-tag>
              </div>
              <div class="file-actions">
                <el-button type="primary" link size="small" @click="handlePreviewFile(file)">
                  <el-icon><View /></el-icon>
                </el-button>
                <el-button type="success" link size="small" @click="handleDownloadFile(file)">
                  <el-icon><Download /></el-icon>
                </el-button>
              </div>
            </div>
          </div>
          <div v-else class="no-upload">
            <el-text type="info">暂无附件</el-text>
          </div>
        </div>

        <div style="margin-top: 16px;">
          <el-form :model="actionForm" label-width="90px">
            <el-form-item v-if="current.status === 'pending_business'" label="退保金额">
              <el-input-number 
                v-model="actionForm.surrender_amount" 
                :min="0" 
                :precision="2" 
                :disabled="!canOperate"
                style="width: 240px" 
              />
            </el-form-item>

            <!-- 只有有权限的人才能看到上传区域 -->
            <el-form-item v-if="canOperate" :label="current.status === 'pending_business' ? '保单页' : '回单'">
              <PaymentAttachmentUploader
                ref="attachmentUploaderRef"
                v-model:other-file-list="uploadFileList"
                :show-invoice-upload="false"
                :other-limit="10"
                :show-form-generator="false"
                :show-signature-stamp="false"
              />
              <div style="color:#999; font-size:12px; margin-top:8px;">
                {{ current.status === 'pending_business' ? '请上传退保保单页' : '请上传收款回单' }}
              </div>
            </el-form-item>
            
            <!-- 权限提示 - 没有权限时显示 -->
            <el-form-item v-if="!canOperate && current.status !== 'finance_done'" label="">
              <el-alert 
                type="warning" 
                :closable="false"
                show-icon
              >
                <template v-if="current.status === 'business_done'">
                  当前状态需要财务角色才能上传回单和提交，您只能查看和下载已上传的文件
                </template>
                <template v-else-if="current.status === 'pending_business'">
                  您没有业务提交权限，只能查看和下载已上传的文件
                </template>
              </el-alert>
            </el-form-item>
          </el-form>

          <div style="display:flex;justify-content:flex-end;gap:10px;margin-top: 12px;">
            <el-button @click="detailVisible=false">关闭</el-button>
            <el-button
              v-if="current.status === 'pending_business'"
              type="success"
              :loading="submitting"
              :disabled="!canOperate"
              @click="submitBusiness"
            >
              业务提交
            </el-button>
            <el-button
              v-if="current.status === 'business_done'"
              type="success"
              :loading="submitting"
              :disabled="!canOperate"
              @click="submitFinance"
            >
              财务提交完成
            </el-button>
          </div>
        </div>
      </div>
    </el-dialog>

    <!-- 员工明细对话框 -->
    <el-dialog v-model="employeeDetailVisible" title="参保员工明细" width="800px">
      <div v-if="currentMonthDetail">
        <el-descriptions :column="2" border style="margin-bottom: 16px;">
          <el-descriptions-item label="月份">{{ currentMonthDetail.month }}</el-descriptions-item>
          <el-descriptions-item label="参保人数">{{ currentMonthDetail.count }} 人</el-descriptions-item>
          <el-descriptions-item label="总金额">¥{{ currentMonthDetail.total_amount.toFixed(2) }}</el-descriptions-item>
          <el-descriptions-item label="人均金额">¥{{ (currentMonthDetail.total_amount / currentMonthDetail.count).toFixed(2) }}</el-descriptions-item>
        </el-descriptions>

        <el-table :data="currentMonthDetail.employees" border stripe max-height="400">
          <el-table-column type="index" label="序号" width="60" />
          <el-table-column label="员工姓名" prop="employee_name" min-width="120" />
          <el-table-column label="员工ID" prop="employee_id" width="100" />
          <el-table-column label="金额" width="120" align="right">
            <template #default="{ row }">
              ¥{{ row.amount.toFixed(2) }}
            </template>
          </el-table-column>
        </el-table>
      </div>
    </el-dialog>

    <!-- 创建退保对话框 -->
    <el-dialog v-model="createDialogVisible" title="创建退保记录" width="600px">
      <el-form :model="createForm" label-width="100px" ref="createFormRef">
        <el-form-item label="选择保单" required>
          <el-select 
            v-model="createForm.policy_id" 
            placeholder="请选择商业险保单" 
            style="width: 100%"
            filterable
          >
            <el-option 
              v-for="policy in commercialPolicies" 
              :key="policy.id" 
              :label="policy.policy_name" 
              :value="policy.id"
            >
              <div style="display: flex; justify-content: space-between;">
                <span>{{ policy.policy_name }}</span>
                <span style="color: #909399; font-size: 12px;">{{ policy.policy_number }}</span>
              </div>
            </el-option>
          </el-select>
        </el-form-item>
        
        <el-form-item label="选择项目" required>
          <el-select 
            v-model="createForm.project_id" 
            placeholder="请选择项目" 
            style="width: 100%"
            filterable
          >
            <el-option 
              v-for="project in projects" 
              :key="project.id" 
              :label="project.name" 
              :value="project.id"
            />
          </el-select>
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="createDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleCreate" :loading="createLoading">确定创建</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { Document, View, Download } from '@element-plus/icons-vue'
import PaymentAttachmentUploader from '@/components/PaymentAttachmentUploader.vue'
import request from '@/api/request'
import { useAccountSetStore } from '@/stores/accountSet'
import { useUserStore } from '@/stores/user'
import { usePermissionStore } from '@/stores/permission'

const accountSetStore = useAccountSetStore()
const userStore = useUserStore()
const permissionStore = usePermissionStore()

const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)

// 判断是否为财务角色
const isFinanceRole = computed(() => {
  return userStore.userInfo?.role === 'finance'
})

// 判断当前操作是否可用
const canOperate = computed(() => {
  if (!current.value) return false
  
  // 待业务状态：任何人都可以操作
  if (current.value.status === 'pending_business') {
    return true
  }
  
  // 待财务状态：只有财务角色可以操作
  if (current.value.status === 'business_done') {
    return isFinanceRole.value
  }
  
  // 已完成状态：不允许操作
  return false
})

// 判断是否可以创建退保（只有管理员和超级管理员）
const canCreate = computed(() => {
  const role = userStore.userInfo?.role
  return role === 'admin' || role === 'super_admin'
})

// ===== 创建退保相关 =====
const createDialogVisible = ref(false)
const createLoading = ref(false)
const commercialPolicies = ref([]) // 商业险保单列表
const projects = ref([]) // 项目列表
const createFormRef = ref(null)

const createForm = reactive({
  policy_id: null,
  project_id: null
})

// 打开创建对话框
const openCreateDialog = async () => {
  // 重置表单
  createForm.policy_id = null
  createForm.project_id = null
  
  // 加载商业险保单列表
  await loadCommercialPolicies()
  
  // 加载项目列表
  await loadProjects()
  
  createDialogVisible.value = true
}

// 加载商业险保单列表
const loadCommercialPolicies = async () => {
  try {
    const res = await request.get('/projects/available/other-insurance-policies', {
      params: {
        account_set_id: currentAccountSetId.value
      }
    })
    // 过滤出商业险类型的保单
    const allPolicies = Array.isArray(res.data) ? res.data : []
    commercialPolicies.value = allPolicies.filter(policy => {
      return policy.type?.name === '商业险' || policy.insurance_type === '商业险'
    })
  } catch (e) {
    console.error('加载保单列表失败:', e)
    ElMessage.error('加载保单列表失败')
    commercialPolicies.value = []
  }
}


// 加载项目列表
const loadProjects = async () => {
  try {
    const res = await request.get('/projects', {
      params: {
        current_account_set_id: currentAccountSetId.value,
        per_page: 1000  // 获取所有项目
      }
    })
    // 分页数据在 res.data.data 中
    projects.value = Array.isArray(res.data?.data) ? res.data.data : []
  } catch (e) {
    console.error('加载项目列表失败:', e)
    ElMessage.error('加载项目列表失败')
    projects.value = []
  }
}

// 创建退保记录
const handleCreate = async () => {
  if (!createForm.policy_id) {
    ElMessage.warning('请选择保单')
    return
  }
  if (!createForm.project_id) {
    ElMessage.warning('请选择项目')
    return
  }
  
  createLoading.value = true
  try {
    const res = await request.post('/insurance-surrenders', {
      account_set_id: currentAccountSetId.value,
      policy_id: createForm.policy_id,
      project_id: createForm.project_id
    })
    
    if (res.success) {
      ElMessage.success('退保记录创建成功')
      createDialogVisible.value = false
      loadList() // 刷新列表
    } else {
      ElMessage.error(res.message || '创建失败')
    }
  } catch (e) {
    console.error('创建退保记录失败:', e)
    ElMessage.error(e.response?.data?.message || '创建失败')
  } finally {
    createLoading.value = false
  }
}

const loading = ref(false)
const list = ref([])

const filterForm = reactive({
  status: ''
})

// ===== 保单统计相关 =====
const activeTab = ref('list') // 当前激活的标签页
const statistics = ref([]) // 统计数据
const statisticsLoading = ref(false) // 统计加载状态
const activeCollapse = ref(null) // 折叠面板激活项
const availablePolicies = ref([]) // 可用的保单列表
const employeeDetailVisible = ref(false) // 员工明细对话框
const currentMonthDetail = ref(null) // 当前查看的月份明细

const statisticsFilter = reactive({
  policy_id: null,
  monthRange: null
})

// 标签页切换
const handleTabChange = (tabName) => {
  if (tabName === 'statistics') {
    loadAvailablePolicies()
    loadStatistics()
  } else if (tabName === 'list') {
    loadList()
  }
}

// 加载可用的保单列表（只加载商业险）
const loadAvailablePolicies = async () => {
  try {
    const res = await request.get('/other-insurance-policies', {
      params: { 
        account_set_id: currentAccountSetId.value,
        insurance_type: '商业险'
      }
    })
    availablePolicies.value = Array.isArray(res.data) ? res.data : []
  } catch (e) {
    console.error('加载保单列表失败:', e)
    availablePolicies.value = []
  }
}

// 加载统计数据
const loadStatistics = async () => {
  if (!currentAccountSetId.value) {
    ElMessage.warning('请先选择账套')
    return
  }
  
  statisticsLoading.value = true
  try {
    const params = {
      account_set_id: currentAccountSetId.value
    }
    
    if (statisticsFilter.policy_id) {
      params.policy_id = statisticsFilter.policy_id
    }
    
    if (statisticsFilter.monthRange && statisticsFilter.monthRange.length === 2) {
      params.start_month = statisticsFilter.monthRange[0]
      params.end_month = statisticsFilter.monthRange[1]
    }
    
    const res = await request.get('/insurance-surrenders/policy-statistics', { params })
    statistics.value = Array.isArray(res.data) ? res.data : []
  } catch (e) {
    console.error('加载统计数据失败:', e)
    ElMessage.error('加载统计数据失败')
    statistics.value = []
  } finally {
    statisticsLoading.value = false
  }
}

// 重置统计筛选
const resetStatistics = () => {
  statisticsFilter.policy_id = null
  statisticsFilter.monthRange = null
  loadStatistics()
}

// 查看员工明细
const showEmployeeDetail = (monthData) => {
  currentMonthDetail.value = monthData
  employeeDetailVisible.value = true
}

const loadList = async () => {
  if (!currentAccountSetId.value) {
    ElMessage.warning('请先选择账套')
    return
  }
  loading.value = true
  try {
    const res = await request.get('/insurance-surrenders', {
      params: { account_set_id: currentAccountSetId.value, ...filterForm }
    })
    list.value = Array.isArray(res.data) ? res.data : []
  } catch (e) {
    list.value = []
    ElMessage.error(e.response?.data?.message || e.message || '加载失败')
  } finally {
    loading.value = false
  }
}

const reset = () => {
  filterForm.status = ''
  loadList()
}

const detailVisible = ref(false)
const current = ref(null)
const uploadFileList = ref([])
const attachmentUploaderRef = ref(null)
const submitting = ref(false)

const actionForm = reactive({
  surrender_amount: 0
})

const statusText = (s) => {
  if (s === 'pending_business') return '待业务上传'
  if (s === 'business_done') return '待财务上传'
  if (s === 'finance_done') return '已完成'
  return s || '-'
}

const openDetail = async (row) => {
  detailVisible.value = true
  uploadFileList.value = []
  actionForm.surrender_amount = row.surrender_amount || 0
  try {
    const res = await request.get(`/insurance-surrenders/${row.id}`)
    current.value = res.data
    actionForm.surrender_amount = current.value.surrender_amount || 0
    
    // 调试：输出角色检查结果
    console.log('=== 退保详情权限检查 ===')
    console.log('当前状态:', current.value.status)
    console.log('用户角色:', userStore.userInfo?.role)
    console.log('是否为财务角色:', isFinanceRole.value)
    console.log('可操作:', canOperate.value)
    console.log('========================')
  } catch (e) {
    ElMessage.error(e.response?.data?.message || e.message || '加载详情失败')
  }
}

// 批量上传附件
const uploadAttachments = async () => {
  if (!current.value || !uploadFileList.value || uploadFileList.value.length === 0) {
    return
  }

  const attachmentType = current.value.status === 'pending_business' ? 'policy_page' : 'payment_receipt'
  
  for (const fileItem of uploadFileList.value) {
    try {
      const fd = new FormData()
      fd.append('file', fileItem.raw || fileItem)
      fd.append('type', attachmentType)
      await request.post(`/insurance-surrenders/${current.value.id}/attachments`, fd)
    } catch (e) {
      ElMessage.error(`上传 ${fileItem.name} 失败: ${e.response?.data?.message || e.message}`)
      throw e
    }
  }
}

const submitBusiness = async () => {
  if (!current.value) return
  
  // 先上传附件
  if (uploadFileList.value && uploadFileList.value.length > 0) {
    try {
      await uploadAttachments()
      ElMessage.success('附件上传成功')
      // 刷新详情以显示新上传的附件
      const detail = await request.get(`/insurance-surrenders/${current.value.id}`)
      current.value = detail.data
      uploadFileList.value = []
    } catch (e) {
      ElMessage.error('附件上传失败，请重试')
      return
    }
  }
  
  submitting.value = true
  try {
    const res = await request.post(`/insurance-surrenders/${current.value.id}/submit-business`, {
      surrender_amount: actionForm.surrender_amount
    })
    ElMessage.success(res.message || '提交成功')
    await loadList()
    const detail = await request.get(`/insurance-surrenders/${current.value.id}`)
    current.value = detail.data
  } catch (e) {
    ElMessage.error(e.response?.data?.message || e.message || '提交失败')
  } finally {
    submitting.value = false
  }
}

const submitFinance = async () => {
  if (!current.value) return
  
  // 先上传附件
  if (uploadFileList.value && uploadFileList.value.length > 0) {
    try {
      await uploadAttachments()
      ElMessage.success('附件上传成功')
      // 刷新详情以显示新上传的附件
      const detail = await request.get(`/insurance-surrenders/${current.value.id}`)
      current.value = detail.data
      uploadFileList.value = []
    } catch (e) {
      ElMessage.error('附件上传失败，请重试')
      return
    }
  }
  
  submitting.value = true
  try {
    const res = await request.post(`/insurance-surrenders/${current.value.id}/submit-finance`)
    ElMessage.success(res.message || '已完成')
    await loadList()
    const detail = await request.get(`/insurance-surrenders/${current.value.id}`)
    current.value = detail.data
  } catch (e) {
    ElMessage.error(e.response?.data?.message || e.message || '提交失败')
  } finally {
    submitting.value = false
  }
}

// 文件预览
const handlePreviewFile = (file) => {
  if (!file || !file.file_url) {
    ElMessage.warning('文件不存在')
    return
  }

  const filename = file.filename.toLowerCase()
  
  // 图片、PDF、Word、Excel直接在新窗口打开
  if (filename.endsWith('.jpg') || filename.endsWith('.jpeg') || filename.endsWith('.png') || 
      filename.endsWith('.gif') || filename.endsWith('.webp') || filename.endsWith('.pdf') || 
      filename.endsWith('.doc') || filename.endsWith('.docx') || filename.endsWith('.xls') || 
      filename.endsWith('.xlsx')) {
    window.open(file.file_url, '_blank')
  } else {
    ElMessage.warning('不支持预览该文件类型，请使用下载功能')
  }
}

// 文件下载
const handleDownloadFile = (file) => {
  if (!file || !file.file_url) {
    ElMessage.warning('文件不存在')
    return
  }
  
  const link = document.createElement('a')
  link.href = file.file_url
  link.download = file.filename
  link.target = '_blank'
  link.click()
  ElMessage.success('正在下载...')
}

// 格式化文件大小
const formatFileSize = (bytes) => {
  if (!bytes || bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
}

onMounted(() => {
  loadList()
})
</script>

<style scoped>
.insurance-surrender-container { padding: 20px; }
.page-header { margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 24px; color: #303133; }

/* 文件列表样式 - 复用员工档案中的样式 */
.files-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.file-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 10px;
  background-color: #f5f7fa;
  border-radius: 4px;
  border: 1px solid #e4e7ed;
}

.file-info {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  min-width: 0;
}

.file-info .el-icon {
  font-size: 16px;
  color: #409eff;
  flex-shrink: 0;
}

.filename {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 13px;
  color: #303133;
}

.file-meta {
  font-size: 12px;
  color: #909399;
  flex-shrink: 0;
  margin-right: 8px;
}

.file-actions {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
  margin-left: 10px;
}

.no-upload {
  text-align: center;
  padding: 16px 0;
  color: #909399;
  background-color: #f5f7fa;
  border-radius: 4px;
  border: 1px dashed #e4e7ed;
}
</style>

