<template>
  <div class="settings-page">
    <div class="page-header">
      <h1>系统设置</h1>
      <p>管理系统配置和参数</p>
    </div>
    
    <div class="settings-content">
      <!-- 基本设置 -->
      <el-card class="settings-card">
        <template #header>
          <span>基本设置</span>
        </template>
        
        <el-form :model="settings" label-width="120px">
          <el-form-item label="系统名称">
            <el-input v-model="settings.system_name" placeholder="请输入系统名称" />
          </el-form-item>
          
          <el-form-item label="系统版本">
            <el-input v-model="settings.system_version" placeholder="请输入系统版本" />
          </el-form-item>
          
          <el-form-item label="公司名称">
            <el-input v-model="settings.company_name" placeholder="请输入公司名称" />
          </el-form-item>
          
          <el-form-item label="公司地址">
            <el-input v-model="settings.company_address" placeholder="请输入公司地址" />
          </el-form-item>
          
          <el-form-item label="联系电话">
            <el-input v-model="settings.contact_phone" placeholder="请输入联系电话" />
          </el-form-item>
          
          <el-form-item label="邮箱地址">
            <el-input v-model="settings.contact_email" placeholder="请输入邮箱地址" />
          </el-form-item>
          
          <el-form-item>
            <el-button type="primary" @click="saveSettings">保存设置</el-button>
            <el-button @click="resetSettings">重置</el-button>
          </el-form-item>
        </el-form>
      </el-card>

      <!-- 账套管理 -->
      <el-card class="settings-card">
        <template #header>
          <span>账套管理</span>
        </template>
        
        <el-table :data="accountSets" v-loading="accountSetsLoading" stripe>
          <el-table-column prop="name" label="账套名称" width="200" />
          <el-table-column prop="code" label="账套代码" width="120" />
          <el-table-column prop="company_name" label="公司名称" width="200" />
          <el-table-column label="基数调整月份" width="200">
            <template #default="{ row }">
              <el-tag v-if="row.base_adjustment_months && row.base_adjustment_months.length > 0" type="success">
                {{ getAdjustmentMonthsText(row.base_adjustment_months) }}
              </el-tag>
              <el-tag v-else type="info">未设置</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="当前状态" width="120">
            <template #default="{ row }">
              <el-tag :type="row.status === 'active' ? 'success' : 'warning'">
                {{ row.status === 'active' ? '启用' : '禁用' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="150">
            <template #default="{ row }">
              <el-button type="primary" size="small" @click="editAccountSet(row)">
                编辑
              </el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-card>

      <!-- 编辑账套对话框 -->
      <el-dialog
        v-model="showEditDialog"
        title="编辑账套"
        width="600px"
      >
        <el-form :model="editForm" :rules="editRules" ref="editFormRef" label-width="120px">
          <el-form-item label="账套名称" prop="name">
            <el-input v-model="editForm.name" placeholder="请输入账套名称" />
          </el-form-item>
          
          <el-form-item label="账套代码" prop="code">
            <el-input v-model="editForm.code" placeholder="请输入账套代码" />
          </el-form-item>
          
          <el-form-item label="公司名称">
            <el-input v-model="editForm.company_name" placeholder="请输入公司名称" />
          </el-form-item>
          
          <el-form-item label="基数调整月份" prop="base_adjustment_months">
            <el-checkbox-group v-model="editForm.base_adjustment_months">
              <el-checkbox :label="1">1月</el-checkbox>
              <el-checkbox :label="2">2月</el-checkbox>
              <el-checkbox :label="3">3月</el-checkbox>
              <el-checkbox :label="4">4月</el-checkbox>
              <el-checkbox :label="5">5月</el-checkbox>
              <el-checkbox :label="6">6月</el-checkbox>
              <el-checkbox :label="7">7月</el-checkbox>
              <el-checkbox :label="8">8月</el-checkbox>
              <el-checkbox :label="9">9月</el-checkbox>
              <el-checkbox :label="10">10月</el-checkbox>
              <el-checkbox :label="11">11月</el-checkbox>
              <el-checkbox :label="12">12月</el-checkbox>
            </el-checkbox-group>
            <div class="form-tip">选择允许调整基数的月份，未选择的月份将无法修改基数</div>
          </el-form-item>
        </el-form>
        
        <template #footer>
          <div class="dialog-footer">
            <el-button @click="showEditDialog = false">取消</el-button>
            <el-button type="primary" @click="saveAccountSet" :loading="saving">保存</el-button>
          </div>
        </template>
      </el-dialog>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import request from '@/api/request'

const settings = reactive({
  system_name: '人力资源管理系统',
  system_version: '1.0.0',
  company_name: '',
  company_address: '',
  contact_phone: '',
  contact_email: ''
})

// 账套管理相关
const accountSets = ref([])
const accountSetsLoading = ref(false)
const showEditDialog = ref(false)
const saving = ref(false)
const editFormRef = ref()

const editForm = reactive({
  id: null,
  name: '',
  code: '',
  company_name: '',
  base_adjustment_months: []
})

const editRules = {
  name: [
    { required: true, message: '请输入账套名称', trigger: 'blur' }
  ],
  code: [
    { required: true, message: '请输入账套代码', trigger: 'blur' }
  ]
}

const loadSettings = () => {
  // 这里应该从API加载设置
  console.log('加载系统设置')
}

const saveSettings = () => {
  // 这里应该保存设置到API
  ElMessage.success('设置保存成功')
}

const resetSettings = () => {
  Object.assign(settings, {
    system_name: '人力资源管理系统',
    system_version: '1.0.0',
    company_name: '',
    company_address: '',
    contact_phone: '',
    contact_email: ''
  })
}

// 加载账套列表
const loadAccountSets = async () => {
  accountSetsLoading.value = true
  try {
    const response = await request.get('/account-sets')
    if (response.success) {
      accountSets.value = response.data
    }
  } catch (error) {
    console.error('加载账套列表失败:', error)
    ElMessage.error('加载账套列表失败')
  } finally {
    accountSetsLoading.value = false
  }
}

// 编辑账套
const editAccountSet = (row) => {
  editForm.id = row.id
  editForm.name = row.name
  editForm.code = row.code
  editForm.company_name = row.company_name || ''
  editForm.base_adjustment_months = row.base_adjustment_months || []
  showEditDialog.value = true
}

// 保存账套
const saveAccountSet = async () => {
  if (!editFormRef.value) return

  try {
    await editFormRef.value.validate()
    saving.value = true

    const data = {
      name: editForm.name,
      code: editForm.code,
      company_name: editForm.company_name,
      base_adjustment_months: editForm.base_adjustment_months
    }

    await request.put(`/account-sets/${editForm.id}`, data)
    ElMessage.success('账套更新成功')
    showEditDialog.value = false
    loadAccountSets()
  } catch (error) {
    console.error('保存账套失败:', error)
    ElMessage.error('保存账套失败')
  } finally {
    saving.value = false
  }
}

// 获取调整月份文本
const getAdjustmentMonthsText = (months) => {
  if (!months || months.length === 0) return '未设置'
  
  const monthNames = {
    1: '1月', 2: '2月', 3: '3月', 4: '4月',
    5: '5月', 6: '6月', 7: '7月', 8: '8月',
    9: '9月', 10: '10月', 11: '11月', 12: '12月'
  }
  
  const monthTexts = months.map(month => monthNames[month] || `${month}月`)
  return monthTexts.join('、')
}

onMounted(() => {
  loadSettings()
  loadAccountSets()
})
</script>

<style scoped>
.settings-page {
  padding: 20px;
}

.page-header {
  margin-bottom: 30px;
}

.page-header h1 {
  font-size: 24px;
  color: #303133;
  margin: 0 0 10px 0;
}

.page-header p {
  color: #606266;
  margin: 0;
}

.settings-content {
  max-width: 1200px;
}

.settings-card {
  margin-bottom: 20px;
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
}
</style>
