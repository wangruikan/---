<template>
  <div class="other-insurance-page">
    <!-- 未分配账套提示 -->
    <NoAccountSetTip v-if="!isAdmin && !currentAccountSetId" />
    
    <!-- 正常内容 -->
    <div v-else>
      <div class="page-header">
        <h1>其他保险管理</h1>
        <el-button type="primary" @click="handleCreateType">
          <el-icon><Plus /></el-icon>
          新增保险种类
        </el-button>
      </div>

      <!-- 保险种类列表 -->
      <div class="types-section">
        <el-card>
          <template #header>
            <div class="card-header">
              <span>保险种类</span>
            </div>
          </template>
          
          <el-table :data="insuranceTypes" v-loading="loadingTypes" stripe>
            <el-table-column prop="name" label="保险种类" width="200" />
            <el-table-column prop="description" label="描述" min-width="200" />
            <el-table-column label="活跃保单数" width="120" align="center">
              <template #default="{ row }">
                <el-tag type="success">{{ row.active_policies_count || 0 }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="总保险金额" width="150" align="right">
              <template #default="{ row }">
                <span v-if="row.total_coverage_amount">¥{{ formatMoney(row.total_coverage_amount) }}</span>
                <span v-else class="text-gray-400">-</span>
              </template>
            </el-table-column>
            <el-table-column label="总人均费用" width="150" align="right">
              <template #default="{ row }">
                <span v-if="row.total_employee_per_capita_cost">¥{{ formatMoney(row.total_employee_per_capita_cost) }}</span>
                <span v-else class="text-gray-400">-</span>
              </template>
            </el-table-column>
            <el-table-column prop="creator.name" label="创建人" width="120" />
            <el-table-column prop="created_at" label="创建时间" width="180">
              <template #default="{ row }">
                <span v-date-time="row.created_at"></span>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="200" fixed="right">
              <template #default="{ row }">
                <el-button type="primary" size="small" @click="handleViewPolicies(row)">
                  查看保单
                </el-button>
                <el-button type="success" size="small" @click="handleAddPolicy(row)">
                  添加保单
                </el-button>
                <el-button type="warning" size="small" @click="handleEditType(row)">
                  编辑
                </el-button>
                <el-button 
                  type="danger" 
                  size="small" 
                  @click="handleDeleteType(row)"
                  :disabled="row.active_policies_count > 0"
                >
                  删除
                </el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </div>

      <!-- 保单列表 -->
      <div class="policies-section" v-if="selectedType">
        <el-card>
          <template #header>
            <div class="card-header">
              <span>{{ selectedType.name }} - 保单列表</span>
              <el-button type="primary" size="small" @click="handleAddPolicy(selectedType)">
                <el-icon><Plus /></el-icon>
                添加保单
              </el-button>
            </div>
          </template>
          
          <el-table :data="policies" v-loading="loadingPolicies" stripe>
            <el-table-column prop="policy_number" label="保单号" width="150" />
            <el-table-column prop="policy_name" label="保单名称" min-width="200" />
            <el-table-column prop="insurance_company" label="保险公司" width="150" />
            <el-table-column label="保险金额" width="120" align="right">
              <template #default="{ row }">
                ¥{{ formatMoney(row.coverage_amount) }}
              </template>
            </el-table-column>
            <el-table-column label="员工人均参保费用" width="150" align="right">
              <template #default="{ row }">
                ¥{{ formatMoney(row.employee_per_capita_cost) }}
              </template>
            </el-table-column>
            <el-table-column label="名额" width="100" align="center">
              <template #default="{ row }">
                <el-tag :type="getQuotaTagType(row.quota)" size="small">
                  {{ row.quota || 0 }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="contact_name" label="联系人姓名" width="120" />
            <el-table-column prop="contact_phone" label="联系人电话" width="130" />
            <el-table-column prop="start_date" label="开始日期" width="120">
              <template #default="{ row }">
                <span v-date-time="row.start_date"></span>
              </template>
            </el-table-column>
            <el-table-column prop="end_date" label="结束日期" width="120">
              <template #default="{ row }">
                <span v-date-time="row.end_date"></span>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="100" align="center">
              <template #default="{ row }">
                <el-tag 
                  :type="getStatusType(row.status)"
                  :class="{ 'expiring-soon': row.is_expiring_soon, 'expired': row.is_expired }"
                >
                  {{ getStatusText(row.status) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="剩余天数" width="100" align="center">
              <template #default="{ row }">
                <span v-if="row.status === 'active'" :class="{ 'text-red-500': row.remaining_days <= 30 }">
                  {{ row.remaining_days }}天
                </span>
                <span v-else class="text-gray-400">-</span>
              </template>
            </el-table-column>
            <el-table-column prop="creator.name" label="创建人" width="120" />
            <el-table-column label="操作" width="150" fixed="right">
              <template #default="{ row }">
                <el-button type="warning" size="small" @click="handleEditPolicy(row)">
                  编辑
                </el-button>
                <el-button type="danger" size="small" @click="handleDeletePolicy(row)">
                  删除
                </el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </div>
    </div>

    <!-- 保险种类编辑对话框 -->
    <el-dialog 
      v-model="showTypeDialog" 
      :title="isEditType ? '编辑保险种类' : '新增保险种类'"
      width="500px"
    >
      <el-form :model="typeForm" :rules="typeRules" ref="typeFormRef" label-width="100px">
        <el-form-item label="保险种类" prop="name">
          <el-input v-model="typeForm.name" placeholder="请输入保险种类名称，如：安责险" />
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input 
            v-model="typeForm.description" 
            type="textarea" 
            :rows="3"
            placeholder="请输入保险种类描述"
          />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showTypeDialog = false">取消</el-button>
        <el-button type="primary" @click="handleSubmitType" :loading="submitting">
          {{ isEditType ? '更新' : '创建' }}
        </el-button>
      </template>
    </el-dialog>

    <!-- 保单编辑对话框 -->
    <el-dialog 
      v-model="showPolicyDialog" 
      :title="isEditPolicy ? '编辑保单' : '新增保单'"
      width="600px"
    >
      <el-form :model="policyForm" :rules="policyRules" ref="policyFormRef" label-width="120px">
        <el-form-item label="保单号" prop="policy_number">
          <el-input v-model="policyForm.policy_number" placeholder="请输入保单号" />
        </el-form-item>
        <el-form-item label="保单名称" prop="policy_name">
          <el-input v-model="policyForm.policy_name" placeholder="请输入保单名称" />
        </el-form-item>
        <el-form-item label="保险公司" prop="insurance_company">
          <el-input v-model="policyForm.insurance_company" placeholder="请输入保险公司名称" />
        </el-form-item>
        <el-form-item label="保险金额" prop="coverage_amount">
          <el-input-number 
            v-model="policyForm.coverage_amount" 
            :min="0" 
            :precision="2"
            placeholder="请输入保险金额"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="员工人均参保费用" prop="employee_per_capita_cost">
          <el-input-number 
            v-model="policyForm.employee_per_capita_cost" 
            :min="0" 
            :precision="2"
            placeholder="请输入员工人均参保费用"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="名额" prop="quota">
          <el-input-number 
            v-model="policyForm.quota" 
            :min="0" 
            placeholder="请输入名额数量"
            style="width: 100%"
          />
          <div class="form-tip">名额大于0时，员工入职会减少名额而不增加保险金额</div>
        </el-form-item>
        <el-form-item label="联系人姓名" prop="contact_name">
          <el-input 
            v-model="policyForm.contact_name" 
            placeholder="请输入联系人姓名"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="联系人电话" prop="contact_phone">
          <el-input 
            v-model="policyForm.contact_phone" 
            placeholder="请输入联系人电话"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="人员姓名列表" prop="personnel_name_list">
          <div class="personnel-list-container">
            <div class="personnel-list-header">
              <el-input 
                v-model="newPersonName" 
                placeholder="请输入人员姓名"
                @keyup.enter="addPersonName"
                style="width: 200px; margin-right: 10px;"
              />
              <el-button type="primary" size="small" @click="addPersonName">
                <el-icon><Plus /></el-icon>
                添加
              </el-button>
            </div>
            <div class="personnel-list-items" v-if="policyForm.personnel_name_list && policyForm.personnel_name_list.length > 0">
              <el-tag 
                v-for="(name, index) in policyForm.personnel_name_list" 
                :key="index"
                closable
                @close="removePersonName(index)"
                style="margin: 5px 5px 5px 0;"
              >
                {{ name }}
              </el-tag>
            </div>
            <div v-else class="text-gray-400">暂无人员姓名</div>
          </div>
        </el-form-item>
        <el-form-item label="保险期间" required>
          <el-row :gutter="10">
            <el-col :span="12">
              <el-form-item prop="start_date">
                <el-date-picker 
                  v-model="policyForm.start_date" 
                  type="date" 
                  placeholder="开始日期"
                  style="width: 100%"
                />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item prop="end_date">
                <el-date-picker 
                  v-model="policyForm.end_date" 
                  type="date" 
                  placeholder="结束日期"
                  style="width: 100%"
                />
              </el-form-item>
            </el-col>
          </el-row>
        </el-form-item>
        <el-form-item label="保单状态" prop="status" v-if="isEditPolicy">
          <el-select v-model="policyForm.status" placeholder="请选择保单状态" style="width: 100%">
            <el-option label="生效中" value="active" />
            <el-option label="已过期" value="expired" />
            <el-option label="已取消" value="cancelled" />
          </el-select>
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input 
            v-model="policyForm.description" 
            type="textarea" 
            :rows="3"
            placeholder="请输入保单描述"
          />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showPolicyDialog = false">取消</el-button>
        <el-button type="primary" @click="handleSubmitPolicy" :loading="submitting">
          {{ isEditPolicy ? '更新' : '创建' }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { 
  getInsuranceTypes, 
  createInsuranceType, 
  updateInsuranceType, 
  deleteInsuranceType,
  getPolicies,
  createPolicy,
  updatePolicy,
  deletePolicy
} from '@/api/otherInsurance'
import { useUserStore } from '@/stores/user'
import { useAccountSetStore } from '@/stores/accountSet'
import NoAccountSetTip from '@/components/NoAccountSetTip.vue'

const userStore = useUserStore()
const accountSetStore = useAccountSetStore()

const isAdmin = computed(() => userStore.userInfo?.role === 'admin')
const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)

// 数据状态
const loadingTypes = ref(false)
const loadingPolicies = ref(false)
const submitting = ref(false)
const insuranceTypes = ref([])
const policies = ref([])
const selectedType = ref(null)

// 对话框状态
const showTypeDialog = ref(false)
const showPolicyDialog = ref(false)
const isEditType = ref(false)
const isEditPolicy = ref(false)
const currentPolicyId = ref(null)

// 表单引用
const typeFormRef = ref()
const policyFormRef = ref()

// 保险种类表单
const typeForm = reactive({
  name: '',
  description: ''
})

// 保单表单
const policyForm = reactive({
  policy_number: '',
  policy_name: '',
  insurance_company: '',
  coverage_amount: null,
  employee_per_capita_cost: null,
  quota: 0,
  contact_name: '',
  contact_phone: '',
  personnel_name_list: [],
  start_date: null,
  end_date: null,
  status: 'active',
  description: ''
})

// 新增人员姓名
const newPersonName = ref('')

// 表单验证规则
const typeRules = {
  name: [
    { required: true, message: '请输入保险种类名称', trigger: 'blur' },
    { min: 1, max: 100, message: '长度在 1 到 100 个字符', trigger: 'blur' }
  ]
}

const policyRules = {
  policy_number: [
    { required: true, message: '请输入保单号', trigger: 'blur' },
    { min: 1, max: 100, message: '长度在 1 到 100 个字符', trigger: 'blur' }
  ],
  policy_name: [
    { required: true, message: '请输入保单名称', trigger: 'blur' },
    { min: 1, max: 200, message: '长度在 1 到 200 个字符', trigger: 'blur' }
  ],
  insurance_company: [
    { required: true, message: '请输入保险公司名称', trigger: 'blur' },
    { min: 1, max: 200, message: '长度在 1 到 200 个字符', trigger: 'blur' }
  ],
  coverage_amount: [
    { required: true, message: '请输入保险金额', trigger: 'blur' },
    { type: 'number', min: 0, message: '保险金额必须大于等于0', trigger: 'blur' }
  ],
  employee_per_capita_cost: [
    { required: true, message: '请输入员工人均参保费用', trigger: 'blur' },
    { type: 'number', min: 0, message: '员工人均参保费用必须大于等于0', trigger: 'blur' }
  ],
  quota: [
    { type: 'number', min: 0, message: '名额必须大于等于0', trigger: 'blur' }
  ],
  contact_name: [
    { max: 100, message: '联系人姓名不能超过100个字符', trigger: 'blur' }
  ],
  contact_phone: [
    { max: 20, message: '联系人电话不能超过20个字符', trigger: 'blur' },
    { pattern: /^[\d\-\+\(\)\s]*$/, message: '请输入有效的电话号码', trigger: 'blur' }
  ],
  start_date: [
    { required: true, message: '请选择开始日期', trigger: 'change' }
  ],
  end_date: [
    { required: true, message: '请选择结束日期', trigger: 'change' }
  ]
}

// 加载保险种类列表
const loadInsuranceTypes = async () => {
  if (!currentAccountSetId.value) return
  
  loadingTypes.value = true
  try {
    const response = await getInsuranceTypes({
      current_account_set_id: currentAccountSetId.value
    })
    if (response.success) {
      insuranceTypes.value = response.data
    }
  } catch (error) {
    console.error('加载保险种类失败:', error)
    ElMessage.error('加载保险种类失败')
  } finally {
    loadingTypes.value = false
  }
}

// 加载保单列表
const loadPolicies = async (typeId) => {
  if (!currentAccountSetId.value || !typeId) return
  
  loadingPolicies.value = true
  try {
    const response = await getPolicies(typeId, {
      current_account_set_id: currentAccountSetId.value
    })
    if (response.success) {
      policies.value = response.data
    }
  } catch (error) {
    console.error('加载保单失败:', error)
    ElMessage.error('加载保单失败')
  } finally {
    loadingPolicies.value = false
  }
}

// 创建保险种类
const handleCreateType = () => {
  isEditType.value = false
  Object.assign(typeForm, {
    name: '',
    description: ''
  })
  showTypeDialog.value = true
}

// 编辑保险种类
const handleEditType = (row) => {
  isEditType.value = true
  Object.assign(typeForm, {
    name: row.name,
    description: row.description
  })
  showTypeDialog.value = true
}

// 提交保险种类
const handleSubmitType = async () => {
  if (!typeFormRef.value) return
  
  await typeFormRef.value.validate()
  submitting.value = true
  
  try {
    const data = {
      ...typeForm,
      current_account_set_id: currentAccountSetId.value
    }
    
    let response
    if (isEditType.value) {
      response = await updateInsuranceType(selectedType.value?.id, data)
    } else {
      response = await createInsuranceType(data)
    }
    
    if (response.success) {
      ElMessage.success(response.message)
      showTypeDialog.value = false
      loadInsuranceTypes()
    }
  } catch (error) {
    console.error('提交保险种类失败:', error)
    ElMessage.error(error.response?.data?.message || '操作失败')
  } finally {
    submitting.value = false
  }
}

// 删除保险种类
const handleDeleteType = async (row) => {
  try {
    await ElMessageBox.confirm(
      `确定要删除保险种类"${row.name}"吗？删除后无法恢复。`,
      '确认删除',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
    
    const response = await deleteInsuranceType(row.id, {
      current_account_set_id: currentAccountSetId.value
    })
    
    if (response.success) {
      ElMessage.success(response.message)
      loadInsuranceTypes()
      if (selectedType.value?.id === row.id) {
        selectedType.value = null
        policies.value = []
      }
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除保险种类失败:', error)
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }
}

// 查看保单
const handleViewPolicies = (row) => {
  selectedType.value = row
  loadPolicies(row.id)
}

// 添加保单
const handleAddPolicy = (row) => {
  selectedType.value = row
  isEditPolicy.value = false
  currentPolicyId.value = null  // 清空当前保单ID
  Object.assign(policyForm, {
    policy_number: '',
    policy_name: '',
    insurance_company: '',
    coverage_amount: null,
    employee_per_capita_cost: null,
    quota: 0,
    contact_name: '',
    contact_phone: '',
    personnel_name_list: [],
    start_date: null,
    end_date: null,
    status: 'active',
    description: ''
  })
  showPolicyDialog.value = true
}

// 添加人员姓名
const addPersonName = () => {
  if (newPersonName.value.trim()) {
    if (!policyForm.personnel_name_list) {
      policyForm.personnel_name_list = []
    }
    policyForm.personnel_name_list.push(newPersonName.value.trim())
    newPersonName.value = ''
  } else {
    ElMessage.warning('请输入人员姓名')
  }
}

// 删除人员姓名
const removePersonName = (index) => {
  policyForm.personnel_name_list.splice(index, 1)
}

// 编辑保单
const handleEditPolicy = (row) => {
  isEditPolicy.value = true
  currentPolicyId.value = row.id  // 保存当前编辑的保单ID
  Object.assign(policyForm, {
    policy_number: row.policy_number,
    policy_name: row.policy_name,
    insurance_company: row.insurance_company,
    coverage_amount: row.coverage_amount,
    employee_per_capita_cost: row.employee_per_capita_cost,
    quota: row.quota || 0,
    contact_name: row.contact_name,
    contact_phone: row.contact_phone,
    personnel_name_list: row.personnel_name_list || [],
    start_date: row.start_date,
    end_date: row.end_date,
    status: row.status,
    description: row.description
  })
  showPolicyDialog.value = true
}

// 提交保单
const handleSubmitPolicy = async () => {
  if (!policyFormRef.value) return
  
  await policyFormRef.value.validate()
  submitting.value = true
  
  try {
    const data = {
      ...policyForm,
      current_account_set_id: currentAccountSetId.value
    }
    
    let response
    if (isEditPolicy.value) {
      response = await updatePolicy(currentPolicyId.value, data)
    } else {
      response = await createPolicy(selectedType.value?.id, data)
    }
    
    if (response.success) {
      ElMessage.success(response.message)
      showPolicyDialog.value = false
      loadPolicies(selectedType.value?.id)
      loadInsuranceTypes() // 刷新保险种类列表以更新统计数据
    }
  } catch (error) {
    console.error('提交保单失败:', error)
    ElMessage.error(error.response?.data?.message || '操作失败')
  } finally {
    submitting.value = false
  }
}

// 删除保单
const handleDeletePolicy = async (row) => {
  try {
    await ElMessageBox.confirm(
      `确定要删除保单"${row.policy_name}"吗？删除后无法恢复。`,
      '确认删除',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
    
    const response = await deletePolicy(row.id, {
      current_account_set_id: currentAccountSetId.value
    })
    
    if (response.success) {
      ElMessage.success(response.message)
      loadPolicies(selectedType.value?.id)
      loadInsuranceTypes() // 刷新保险种类列表以更新统计数据
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除保单失败:', error)
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }
}

// 格式化金额
const formatMoney = (amount) => {
  if (!amount) return '0.00'
  return Number(amount).toLocaleString('zh-CN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

// 获取状态类型
const getStatusType = (status) => {
  const types = {
    active: 'success',
    expired: 'danger',
    cancelled: 'info'
  }
  return types[status] || 'info'
}

// 获取状态文本
const getStatusText = (status) => {
  const texts = {
    active: '生效中',
    expired: '已过期',
    cancelled: '已取消'
  }
  return texts[status] || status
}

// 获取名额标签类型
const getQuotaTagType = (quota) => {
  if (quota > 10) return 'success'
  if (quota > 5) return 'warning'
  if (quota > 0) return 'danger'
  return 'info'
}

onMounted(async () => {
  // 先初始化账套信息
  await accountSetStore.loadMyAccountSets()
  // 然后加载保险种类
  loadInsuranceTypes()
})
</script>

<style scoped>
.other-insurance-page {
  padding: 0;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.types-section {
  margin-bottom: 30px;
}

.policies-section {
  margin-top: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.personnel-list-container {
  border: 1px solid #dcdfe6;
  border-radius: 4px;
  padding: 10px;
  background-color: #fafafa;
}

.personnel-list-header {
  display: flex;
  align-items: center;
  margin-bottom: 10px;
}

.personnel-list-items {
  min-height: 30px;
}

.text-gray-400 {
  color: #9ca3af;
}

.text-red-500 {
  color: #ef4444;
}

.expiring-soon {
  background-color: #fef3c7 !important;
  color: #d97706 !important;
}

.expired {
  background-color: #fee2e2 !important;
  color: #dc2626 !important;
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
  line-height: 1.4;
}
</style>

