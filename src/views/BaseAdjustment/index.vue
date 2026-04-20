<template>
  <div class="base-adjustment-container">
    <!-- 页面标题 -->
    <div class="page-header">
      <h2>基数调差管理</h2>
    </div>

    <!-- 权限提示 -->
    <el-alert
      v-if="permissionInfo.message"
      :title="permissionInfo.allowed ? '✅ ' + permissionInfo.message : '❌ ' + permissionInfo.message"
      :type="permissionInfo.allowed ? 'success' : 'warning'"
      :closable="false"
      style="margin-bottom: 20px;"
    />

    <!-- 筛选条件 -->
    <el-card class="filter-card">
      <el-form :model="filterForm" :inline="true">
        <el-form-item label="员工姓名">
          <el-input
            v-model="filterForm.employee_name"
            placeholder="请输入员工姓名"
            clearable
            style="width: 200px"
          />
        </el-form-item>

        <el-form-item label="项目">
          <el-select v-model="filterForm.project_id" placeholder="请选择项目" clearable style="width: 200px">
            <el-option label="全部" value="" />
            <!-- 这里可以添加项目列表 -->
          </el-select>
        </el-form-item>

        <el-form-item>
          <el-button type="primary" @click="loadEmployees">
            <el-icon><Search /></el-icon>
            查询
          </el-button>
          <el-button @click="resetFilter">
            <el-icon><Refresh /></el-icon>
            重置
          </el-button>
          <el-button type="success" @click="handleRefresh">
            <el-icon><RefreshRight /></el-icon>
            刷新
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 在职员工列表 -->
    <el-card class="table-card" style="margin-top: 20px;">
      <template #header>
        <div class="card-header">
          <span>在职员工列表（共 {{ employees.length }} 人）</span>
          <el-tag type="info" size="small">仅显示在职员工</el-tag>
        </div>
      </template>

      <el-table
        v-loading="loading"
        :data="employees"
        border
        stripe
        style="width: 100%"
        :height="600"
      >
        <el-table-column type="index" label="序号" width="60" align="center" />
        
        <el-table-column prop="employee_name" label="员工姓名" width="120" fixed="left">
          <template #default="{ row }">
            <div style="display: flex; align-items: center; gap: 8px;">
              <span>{{ row.employee_name }}</span>
              <el-tag v-if="row.adjustment" type="warning" size="small">已调整</el-tag>
            </div>
          </template>
        </el-table-column>

        <el-table-column prop="id_number" label="身份证号" width="180" />
        
        <el-table-column prop="project_name" label="所属项目" width="150" />

        <!-- 社保基数 -->
        <el-table-column label="社保基数" width="250" align="center">
          <template #default="{ row }">
            <div v-if="row.adjustment" style="display: flex; justify-content: space-around; align-items: center;">
              <div style="text-align: center;">
                <div style="color: #909399; font-size: 12px;">当前</div>
                <div style="font-weight: bold;">¥{{ row.current_social_security_base }}</div>
              </div>
              <el-icon color="#E6A23C"><Right /></el-icon>
              <div style="text-align: center;">
                <div style="color: #E6A23C; font-size: 12px;">调整后</div>
                <div style="font-weight: bold; color: #E6A23C;">
                  ¥{{ row.adjustment.new_social_security_base }}
                  <el-icon v-if="row.adjustment.new_social_security_base > row.current_social_security_base" color="#67C23A"><Top /></el-icon>
                  <el-icon v-else-if="row.adjustment.new_social_security_base < row.current_social_security_base" color="#F56C6C"><Bottom /></el-icon>
                </div>
              </div>
            </div>
            <div v-else style="color: #C0C4CC;">-</div>
          </template>
        </el-table-column>

        <!-- 医保基数 -->
        <el-table-column label="医保基数" width="250" align="center">
          <template #default="{ row }">
            <div v-if="row.adjustment" style="display: flex; justify-content: space-around; align-items: center;">
              <div style="text-align: center;">
                <div style="color: #909399; font-size: 12px;">当前</div>
                <div style="font-weight: bold;">¥{{ row.current_medical_insurance_base }}</div>
              </div>
              <el-icon color="#E6A23C"><Right /></el-icon>
              <div style="text-align: center;">
                <div style="color: #E6A23C; font-size: 12px;">调整后</div>
                <div style="font-weight: bold; color: #E6A23C;">
                  ¥{{ row.adjustment.new_medical_insurance_base }}
                  <el-icon v-if="row.adjustment.new_medical_insurance_base > row.current_medical_insurance_base" color="#67C23A"><Top /></el-icon>
                  <el-icon v-else-if="row.adjustment.new_medical_insurance_base < row.current_medical_insurance_base" color="#F56C6C"><Bottom /></el-icon>
                </div>
              </div>
            </div>
            <div v-else style="color: #C0C4CC;">-</div>
          </template>
        </el-table-column>

        <!-- 公积金基数 -->
        <el-table-column label="公积金基数" width="250" align="center">
          <template #default="{ row }">
            <div v-if="row.adjustment" style="display: flex; justify-content: space-around; align-items: center;">
              <div style="text-align: center;">
                <div style="color: #909399; font-size: 12px;">当前</div>
                <div style="font-weight: bold;">¥{{ row.current_housing_fund_base }}</div>
              </div>
              <el-icon color="#E6A23C"><Right /></el-icon>
              <div style="text-align: center;">
                <div style="color: #E6A23C; font-size: 12px;">调整后</div>
                <div style="font-weight: bold; color: #E6A23C;">
                  ¥{{ row.adjustment.new_housing_fund_base }}
                  <el-icon v-if="row.adjustment.new_housing_fund_base > row.current_housing_fund_base" color="#67C23A"><Top /></el-icon>
                  <el-icon v-else-if="row.adjustment.new_housing_fund_base < row.current_housing_fund_base" color="#F56C6C"><Bottom /></el-icon>
                </div>
              </div>
            </div>
            <div v-else style="color: #C0C4CC;">-</div>
          </template>
        </el-table-column>

        <!-- 大额医疗基数（选填） -->
        <el-table-column label="大额医疗基数" width="280" align="center">
          <template #default="{ row }">
            <div v-if="row.adjustment" style="display: flex; justify-content: space-around; align-items: center;">
              <div style="text-align: center;">
                <div style="color: #909399; font-size: 12px;">
                  当前
                  <el-tooltip content="大额医疗基数为选填项" placement="top">
                    <el-icon color="#909399" style="font-size: 12px;"><QuestionFilled /></el-icon>
                  </el-tooltip>
                </div>
                <div style="font-weight: bold;">¥{{ row.current_large_medical_base }}</div>
              </div>
              <el-icon color="#E6A23C"><Right /></el-icon>
              <div style="text-align: center;">
                <div style="color: #E6A23C; font-size: 12px;">调整后</div>
                <div style="font-weight: bold; color: #E6A23C;">
                  ¥{{ row.adjustment.new_large_medical_base }}
                  <el-icon v-if="row.adjustment.new_large_medical_base > row.current_large_medical_base" color="#67C23A"><Top /></el-icon>
                  <el-icon v-else-if="row.adjustment.new_large_medical_base < row.current_large_medical_base" color="#F56C6C"><Bottom /></el-icon>
                </div>
              </div>
            </div>
            <div v-else style="color: #C0C4CC;">-</div>
          </template>
        </el-table-column>

        <el-table-column label="社保生效时间" width="120" align="center">
          <template #default="{ row }">
            <span v-if="row.adjustment && row.adjustment.social_security_effective_date">{{ formatDate(row.adjustment.social_security_effective_date) }}</span>
            <span v-else style="color: #C0C4CC;">-</span>
          </template>
        </el-table-column>
        <el-table-column label="医保生效时间" width="120" align="center">
          <template #default="{ row }">
            <span v-if="row.adjustment && row.adjustment.medical_insurance_effective_date">{{ formatDate(row.adjustment.medical_insurance_effective_date) }}</span>
            <span v-else style="color: #C0C4CC;">-</span>
          </template>
        </el-table-column>
        <el-table-column label="公积金生效时间" width="120" align="center">
          <template #default="{ row }">
            <span v-if="row.adjustment && row.adjustment.housing_fund_effective_date">{{ formatDate(row.adjustment.housing_fund_effective_date) }}</span>
            <span v-else style="color: #C0C4CC;">-</span>
          </template>
        </el-table-column>
        <el-table-column label="大额医疗生效时间" width="120" align="center">
          <template #default="{ row }">
            <span v-if="row.adjustment && row.adjustment.large_medical_effective_date">{{ formatDate(row.adjustment.large_medical_effective_date) }}</span>
            <span v-else style="color: #C0C4CC;">-</span>
          </template>
        </el-table-column>

        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.adjustment && row.adjustment.status === 'pending'" type="warning">待生效</el-tag>
            <el-tag v-else-if="row.adjustment && row.adjustment.status === 'applied'" type="success">已生效</el-tag>
            <span v-else style="color: #C0C4CC;">-</span>
          </template>
        </el-table-column>

        <el-table-column label="操作" width="250" fixed="right" align="center">
          <template #default="{ row }">
            <div style="display: flex; gap: 5px; justify-content: center; flex-wrap: wrap;">
              <el-button
                type="primary"
                size="small"
                @click="handleAdjust(row)"
                :disabled="!permissionInfo.allowed"
              >
                <el-icon><Edit /></el-icon>
                {{ row.adjustment && row.adjustment.status === 'pending' ? '修改调整' : '调整基数' }}
              </el-button>

              <el-button
                v-if="row.adjustment && row.adjustment.status === 'pending'"
                type="success"
                size="small"
                @click="handleApplyNow(row)"
              >
                <el-icon><Check /></el-icon>
                立即生效
              </el-button>

              <el-button
                v-if="row.adjustment && row.adjustment.status === 'pending'"
                type="danger"
                size="small"
                @click="handleDelete(row)"
              >
                <el-icon><Delete /></el-icon>
                删除
              </el-button>

              <el-button
                type="info"
                size="small"
                @click="handleViewHistory(row)"
              >
                <el-icon><Document /></el-icon>
                历史
              </el-button>
            </div>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 调整基数对话框 -->
    <el-dialog
      v-model="showAdjustDialog"
      :title="'调整基数 - ' + (currentEmployee?.employee_name || '')"
      width="700px"
      :close-on-click-modal="false"
    >
      <el-form
        ref="adjustFormRef"
        :model="adjustForm"
        :rules="adjustFormRules"
        label-width="130px"
      >
        <el-alert
          title="💡 提示"
          type="info"
          :closable="false"
          style="margin-bottom: 20px;"
        >
          <p>• 所有基数和生效时间都是选填项，根据需要调整</p>
          <p>• 调整后的基数将在生效时间到达后自动应用到员工档案</p>
          <p>• 生效时间必须大于等于今天</p>
        </el-alert>

        <!-- 社保基数 -->
        <el-form-item label="社保基数">
          <div style="width: 100%;">
            <div style="margin-bottom: 8px; color: #909399; font-size: 12px;">
              当前：¥{{ currentEmployee?.current_social_security_base || 0 }}
            </div>
            <el-input-number
              v-model="adjustForm.new_social_security_base"
              :min="0"
              :precision="2"
              placeholder="调整后的社保基数"
              style="width: 100%"
            />
          </div>
        </el-form-item>

        <!-- 医保基数 -->
        <el-form-item label="医保基数">
          <div style="width: 100%;">
            <div style="margin-bottom: 8px; color: #909399; font-size: 12px;">
              当前：¥{{ currentEmployee?.current_medical_insurance_base || 0 }}
            </div>
            <el-input-number
              v-model="adjustForm.new_medical_insurance_base"
              :min="0"
              :precision="2"
              placeholder="调整后的医保基数"
              style="width: 100%"
            />
          </div>
        </el-form-item>

        <!-- 公积金基数 -->
        <el-form-item label="公积金基数">
          <div style="width: 100%;">
            <div style="margin-bottom: 8px; color: #909399; font-size: 12px;">
              当前：¥{{ currentEmployee?.current_housing_fund_base || 0 }}
            </div>
            <el-input-number
              v-model="adjustForm.new_housing_fund_base"
              :min="0"
              :precision="2"
              placeholder="调整后的公积金基数"
              style="width: 100%"
            />
          </div>
        </el-form-item>

        <!-- 大额医疗基数 -->
        <el-form-item label="大额医疗基数">
          <div style="width: 100%;">
            <!-- 特殊地区（有独立个人基数）：只读提示 -->
            <template v-if="isSpecialLargeMedicalRegion">
              <div style="display: flex; gap: 20px; margin-bottom: 8px;">
                <span style="color: #909399; font-size: 12px;">
                  个人基数：¥{{ currentEmployee?.current_large_medical_base || 0 }}
                </span>
                <span style="color: #909399; font-size: 12px;">
                  公司基数：¥{{ currentEmployee?.current_large_medical_company_base || 0 }}
                </span>
              </div>
              <el-alert
                type="info"
                :closable="false"
                show-icon
              >
                <template #default>
                  该员工所属特殊地区，大额基数按地区配置，如需调整请前往【大额医疗保险管理】修改
                </template>
              </el-alert>
            </template>
            <!-- 普通地区：可以调整 -->
            <template v-else>
              <div style="margin-bottom: 8px; color: #909399; font-size: 12px;">
                当前：¥{{ currentEmployee?.current_large_medical_base || 0 }}
                <el-tag type="info" size="small" style="margin-left: 5px;">选填</el-tag>
              </div>
              <el-input-number
                v-model="adjustForm.new_large_medical_base"
                :min="0"
                :precision="2"
                placeholder="调整后的大额医疗基数（选填）"
                style="width: 100%"
              />
            </template>
          </div>
        </el-form-item>

        <!-- 大额医疗基数生效时间（普通地区） -->
        <el-form-item label="大额医疗基数生效时间" v-if="!isSpecialLargeMedicalRegion">
          <el-date-picker
            v-model="adjustForm.large_medical_effective_date"
            type="date"
            placeholder="选择大额医疗基数生效时间"
            format="YYYY-MM-DD"
            value-format="YYYY-MM-DD"
            :disabled-date="disabledDate"
            style="width: 100%"
          />
        </el-form-item>

        <!-- 社保基数生效时间 -->
        <el-form-item label="社保基数生效时间">
          <el-date-picker
            v-model="adjustForm.social_security_effective_date"
            type="date"
            placeholder="选择社保基数生效时间"
            format="YYYY-MM-DD"
            value-format="YYYY-MM-DD"
            :disabled-date="disabledDate"
            style="width: 100%"
          />
        </el-form-item>

        <!-- 医保基数生效时间 -->
        <el-form-item label="医保基数生效时间">
          <el-date-picker
            v-model="adjustForm.medical_insurance_effective_date"
            type="date"
            placeholder="选择医保基数生效时间"
            format="YYYY-MM-DD"
            value-format="YYYY-MM-DD"
            :disabled-date="disabledDate"
            style="width: 100%"
          />
        </el-form-item>

        <!-- 公积金基数生效时间 -->
        <el-form-item label="公积金基数生效时间">
          <el-date-picker
            v-model="adjustForm.housing_fund_effective_date"
            type="date"
            placeholder="选择公积金基数生效时间"
            format="YYYY-MM-DD"
            value-format="YYYY-MM-DD"
            :disabled-date="disabledDate"
            style="width: 100%"
          />
        </el-form-item>

        <!-- 调整原因 -->
        <el-form-item label="调整原因">
          <el-input
            v-model="adjustForm.adjustment_reason"
            type="textarea"
            :rows="3"
            placeholder="请输入调整原因（可选）"
            maxlength="500"
            show-word-limit
          />
        </el-form-item>
      </el-form>

      <template #footer>
        <div class="dialog-footer">
          <el-button @click="showAdjustDialog = false">取消</el-button>
          <el-button type="primary" @click="handleSubmit" :loading="submitting">
            <el-icon><Check /></el-icon>
            保存
          </el-button>
        </div>
      </template>
    </el-dialog>

    <!-- 调整历史对话框 -->
    <el-dialog
      v-model="showHistoryDialog"
      :title="'调整历史 - ' + (currentEmployee?.employee_name || '')"
      width="900px"
    >
      <el-table
        v-loading="historyLoading"
        :data="historyRecords"
        border
        stripe
      >
        <el-table-column type="index" label="序号" width="60" align="center" />
        
        <el-table-column label="社保基数" width="150" align="center">
          <template #default="{ row }">
            <div>
              <div style="color: #909399; font-size: 12px;">¥{{ row.old_social_security_base }}</div>
              <el-icon color="#E6A23C"><ArrowDown /></el-icon>
              <div style="color: #E6A23C; font-weight: bold;">¥{{ row.new_social_security_base }}</div>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="医保基数" width="150" align="center">
          <template #default="{ row }">
            <div>
              <div style="color: #909399; font-size: 12px;">¥{{ row.old_medical_insurance_base }}</div>
              <el-icon color="#E6A23C"><ArrowDown /></el-icon>
              <div style="color: #E6A23C; font-weight: bold;">¥{{ row.new_medical_insurance_base }}</div>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="公积金基数" width="150" align="center">
          <template #default="{ row }">
            <div>
              <div style="color: #909399; font-size: 12px;">¥{{ row.old_housing_fund_base }}</div>
              <el-icon color="#E6A23C"><ArrowDown /></el-icon>
              <div style="color: #E6A23C; font-weight: bold;">¥{{ row.new_housing_fund_base }}</div>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="大额医疗基数" width="150" align="center">
          <template #default="{ row }">
            <div>
              <div style="color: #909399; font-size: 12px;">¥{{ row.old_large_medical_base }}</div>
              <el-icon color="#E6A23C"><ArrowDown /></el-icon>
              <div style="color: #E6A23C; font-weight: bold;">¥{{ row.new_large_medical_base }}</div>
            </div>
          </template>
        </el-table-column>

        <el-table-column prop="social_security_effective_date" label="社保生效时间" width="120" align="center">
          <template #default="{ row }">
            {{ formatDate(row.social_security_effective_date) }}
          </template>
        </el-table-column>
        <el-table-column prop="medical_insurance_effective_date" label="医保生效时间" width="120" align="center">
          <template #default="{ row }">
            {{ formatDate(row.medical_insurance_effective_date) }}
          </template>
        </el-table-column>
        <el-table-column prop="housing_fund_effective_date" label="公积金生效时间" width="120" align="center">
          <template #default="{ row }">
            {{ formatDate(row.housing_fund_effective_date) }}
          </template>
        </el-table-column>
        <el-table-column prop="large_medical_effective_date" label="大额医疗生效时间" width="120" align="center">
          <template #default="{ row }">
            {{ formatDate(row.large_medical_effective_date) }}
          </template>
        </el-table-column>
        
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.status === 'pending'" type="warning">待生效</el-tag>
            <el-tag v-else-if="row.status === 'applied'" type="success">已生效</el-tag>
          </template>
        </el-table-column>

        <el-table-column prop="created_at" label="创建时间" width="160" align="center">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
        
        <el-table-column prop="applied_at" label="实际生效时间" width="160" align="center">
          <template #default="{ row }">
            {{ formatDateTime(row.applied_at) }}
          </template>
        </el-table-column>
      </el-table>

      <template #footer>
        <div class="dialog-footer">
          <el-button @click="showHistoryDialog = false">关闭</el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { 
  Search, 
  Refresh, 
  RefreshRight, 
  Edit, 
  Check, 
  Delete,
  Document,
  Top, 
  Bottom,
  Right,
  ArrowDown,
  QuestionFilled
} from '@element-plus/icons-vue'
import { useAccountSetStore } from '@/stores/accountSet'
import { useUserStore } from '@/stores/user'
import request from '@/api/request'

const accountSetStore = useAccountSetStore()
const userStore = useUserStore()

// 响应式数据
const loading = ref(false)
const employees = ref([])
const permissionInfo = ref({
  allowed: false,
  message: ''
})

// 筛选表单
const filterForm = reactive({
  employee_name: '',
  project_id: ''
})

// 调整对话框
const showAdjustDialog = ref(false)
const currentEmployee = ref(null)
const adjustFormRef = ref()
const submitting = ref(false)

const adjustForm = reactive({
  employee_id: null,
  new_social_security_base: 0,
  new_medical_insurance_base: 0,
  new_housing_fund_base: 0,
  new_large_medical_base: 0,
  social_security_effective_date: '',
  medical_insurance_effective_date: '',
  housing_fund_effective_date: '',
  large_medical_effective_date: '',
  adjustment_reason: ''
})

const adjustFormRules = {
  // 所有字段都是选填的，不需要必填验证
}

// 历史记录对话框
const showHistoryDialog = ref(false)
const historyLoading = ref(false)
const historyRecords = ref([])

// 计算属性：当前账套 ID（store 优先，否则用 localStorage，与请求拦截器一致）
const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)
const effectiveAccountSetId = computed(() => {
  const id = currentAccountSetId.value
  if (id) return id
  const stored = localStorage.getItem('current_account_set_id')
  if (!stored) return null
  const parsed = parseInt(stored, 10)
  return Number.isNaN(parsed) ? null : parsed
})

// 判断是否为特殊地区（使用统一基数）
const isSpecialLargeMedicalRegion = computed(() => {
  if (!currentEmployee.value) return false
  // 如果 large_medical_base_source 为 config，则为特殊地区（使用统一基数）
  return currentEmployee.value.large_medical_base_source === 'config'
})

// 方法
const checkPermission = async () => {
  const accountSetId = effectiveAccountSetId.value
  if (!accountSetId) {
    permissionInfo.value = {
      allowed: false,
      message: '请先选择账套'
    }
    return
  }

  try {
    const response = await request.get('/base-adjustments/check-permission', {
      params: {
        account_set_id: accountSetId
      }
    })

    if (response.success) {
      permissionInfo.value = response.data
    }
  } catch (error) {
    console.error('检查权限失败:', error)
  }
}

const loadEmployees = async () => {
  const accountSetId = effectiveAccountSetId.value
  if (!accountSetId) {
    ElMessage.warning('请先选择账套')
    return
  }

  loading.value = true
  try {
    const params = {
      account_set_id: accountSetId,
      ...filterForm
    }

    const response = await request.get('/base-adjustments', { params })

    if (response.success) {
      employees.value = Array.isArray(response.data) ? response.data : []
    } else {
      employees.value = []
      ElMessage.error(response.message || '加载失败')
    }
  } catch (error) {
    employees.value = []
    console.error('加载员工数据失败:', error)
    ElMessage.error(error.response?.data?.message || '加载失败')
  } finally {
    loading.value = false
  }
}

const resetFilter = () => {
  filterForm.employee_name = ''
  filterForm.project_id = ''
  loadEmployees()
}

const handleRefresh = () => {
  loadEmployees()
  checkPermission()
}

const handleAdjust = (row) => {
  if (!permissionInfo.value.allowed) {
    ElMessage.warning(permissionInfo.value.message)
    return
  }

  currentEmployee.value = row
  
  // 如果已有调整记录，填充表单
  if (row.adjustment) {
    adjustForm.employee_id = row.employee_id
    adjustForm.new_social_security_base = row.adjustment.new_social_security_base
    adjustForm.new_medical_insurance_base = row.adjustment.new_medical_insurance_base
    adjustForm.new_housing_fund_base = row.adjustment.new_housing_fund_base
    adjustForm.new_large_medical_base = row.adjustment.new_large_medical_base
    adjustForm.social_security_effective_date = row.adjustment.social_security_effective_date || ''
    adjustForm.medical_insurance_effective_date = row.adjustment.medical_insurance_effective_date || ''
    adjustForm.housing_fund_effective_date = row.adjustment.housing_fund_effective_date || ''
    adjustForm.large_medical_effective_date = row.adjustment.large_medical_effective_date || ''
    adjustForm.adjustment_reason = row.adjustment.adjustment_reason || ''
  } else {
    // 新建调整，使用当前基数作为默认值
    adjustForm.employee_id = row.employee_id
    adjustForm.new_social_security_base = row.current_social_security_base
    adjustForm.new_medical_insurance_base = row.current_medical_insurance_base
    adjustForm.new_housing_fund_base = row.current_housing_fund_base
    adjustForm.new_large_medical_base = row.current_large_medical_base
    adjustForm.social_security_effective_date = ''
    adjustForm.medical_insurance_effective_date = ''
    adjustForm.housing_fund_effective_date = ''
    adjustForm.large_medical_effective_date = ''
    adjustForm.adjustment_reason = ''
  }
  
  showAdjustDialog.value = true
}

const handleSubmit = async () => {
  if (!adjustFormRef.value) return

  try {
    await adjustFormRef.value.validate()

    submitting.value = true

    // 检查账套ID
    if (!effectiveAccountSetId.value) {
      ElMessage.error('请先选择账套')
      return
    }

    // 检查用户是否已登录
    if (!userStore.token) {
      ElMessage.error('用户未登录，请重新登录')
      return
    }

    // 只发送有值的字段，避免后端验证空字符串
    const data = {
      employee_id: adjustForm.employee_id,
      account_set_id: effectiveAccountSetId.value
    }

    // 只添加填写了的基数和对应的生效时间
    if (adjustForm.new_social_security_base) {
      data.new_social_security_base = adjustForm.new_social_security_base
      if (adjustForm.social_security_effective_date) {
        data.social_security_effective_date = adjustForm.social_security_effective_date
      }
    }
    
    if (adjustForm.new_medical_insurance_base) {
      data.new_medical_insurance_base = adjustForm.new_medical_insurance_base
      if (adjustForm.medical_insurance_effective_date) {
        data.medical_insurance_effective_date = adjustForm.medical_insurance_effective_date
      }
    }
    
    if (adjustForm.new_housing_fund_base) {
      data.new_housing_fund_base = adjustForm.new_housing_fund_base
      if (adjustForm.housing_fund_effective_date) {
        data.housing_fund_effective_date = adjustForm.housing_fund_effective_date
      }
    }
    
    // 只有普通地区才能调整大额基数
    if (adjustForm.new_large_medical_base && !isSpecialLargeMedicalRegion.value) {
      data.new_large_medical_base = adjustForm.new_large_medical_base
      if (adjustForm.large_medical_effective_date) {
        data.large_medical_effective_date = adjustForm.large_medical_effective_date
      }
    }

    // 添加调整原因（可以为空）
    if (adjustForm.adjustment_reason) {
      data.adjustment_reason = adjustForm.adjustment_reason
    }

    const response = await request.post('/base-adjustments', data)

    if (response.success) {
      ElMessage.success(response.message || '保存成功')
      showAdjustDialog.value = false
      loadEmployees()
    } else {
      ElMessage.error(response.message || '保存失败')
    }
  } catch (error) {
    console.error('保存失败:', error)
    if (error.response?.data?.message) {
      ElMessage.error(error.response.data.message)
    }
  } finally {
    submitting.value = false
  }
}

const handleApplyNow = async (row) => {
  if (!row.adjustment) return

  try {
    await ElMessageBox.confirm(
      `确定要立即生效 ${row.employee_name} 的基数调整吗？`,
      '确认操作',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    const response = await request.post(`/base-adjustments/${row.adjustment.id}/apply-now`)

    if (response.success) {
      ElMessage.success('基数调整已生效')
      loadEmployees()
    } else {
      ElMessage.error(response.message || '操作失败')
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('立即生效失败:', error)
      ElMessage.error('操作失败')
    }
  }
}

const handleDelete = async (row) => {
  if (!row.adjustment) return

  try {
    await ElMessageBox.confirm(
      `确定要删除 ${row.employee_name} 的基数调整记录吗？`,
      '确认删除',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    const response = await request.delete(`/base-adjustments/${row.adjustment.id}`)

    if (response.success) {
      ElMessage.success('删除成功')
      loadEmployees()
    } else {
      ElMessage.error(response.message || '删除失败')
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      ElMessage.error('删除失败')
    }
  }
}

const handleViewHistory = async (row) => {
  currentEmployee.value = row
  showHistoryDialog.value = true
  historyLoading.value = true

  try {
    const response = await request.get(`/base-adjustments/employee/${row.employee_id}/history`, {
      params: {
        account_set_id: effectiveAccountSetId.value
      }
    })

    if (response.success) {
      historyRecords.value = response.data
    } else {
      ElMessage.error(response.message || '加载历史记录失败')
    }
  } catch (error) {
    console.error('加载历史记录失败:', error)
    ElMessage.error('加载历史记录失败')
  } finally {
    historyLoading.value = false
  }
}

const disabledDate = (time) => {
  // 不能选择今天之前的日期
  return time.getTime() < Date.now() - 8.64e7
}

// 格式化日期
const formatDate = (dateString) => {
  if (!dateString) return '-'
  
  // 如果已经是 YYYY-MM-DD 格式，直接返回
  if (/^\d{4}-\d{2}-\d{2}$/.test(dateString)) {
    return dateString
  }
  
  // 处理其他格式（如时间戳、ISO格式等）
  const date = new Date(dateString)
  if (isNaN(date.getTime())) return dateString // 无效日期，返回原值
  
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  
  return `${year}-${month}-${day}`
}

// 格式化日期时间
const formatDateTime = (dateTimeString) => {
  if (!dateTimeString) return '-'
  
  // 如果已经是 YYYY-MM-DD HH:mm:ss 格式，直接返回
  if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(dateTimeString)) {
    return dateTimeString
  }
  
  // 处理 ISO 格式（2025-11-20T00:00:00.000Z）
  // 直接提取日期时间部分，避免时区转换
  const isoMatch = dateTimeString.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})/)
  if (isoMatch) {
    return `${isoMatch[1]}-${isoMatch[2]}-${isoMatch[3]} ${isoMatch[4]}:${isoMatch[5]}:${isoMatch[6]}`
  }
  
  // 处理其他格式（如时间戳）
  const date = new Date(dateTimeString)
  if (isNaN(date.getTime())) return dateTimeString // 无效日期，返回原值
  
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  const seconds = String(date.getSeconds()).padStart(2, '0')
  
  return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`
}

// 监听账套变化，重新加载数据和权限状态
watch(effectiveAccountSetId, (id) => {
  if (id) {
    checkPermission()
    loadEmployees()
  } else {
    employees.value = []
    permissionInfo.value = { allowed: false, message: '请先选择账套' }
  }
}, { immediate: true })

// 生命周期：若 effectiveAccountSetId 已有值，watch 的 immediate 会已触发加载；否则只做一次权限提示
onMounted(() => {
  if (!effectiveAccountSetId.value) {
    permissionInfo.value = { allowed: false, message: '请先选择账套' }
  }
})
</script>

<style scoped>
.base-adjustment-container {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  font-size: 24px;
  color: #303133;
}

.filter-card {
  margin-bottom: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
</style>

