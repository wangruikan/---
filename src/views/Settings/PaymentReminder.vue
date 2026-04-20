<template>
  <div class="payment-reminder-page">
    <div class="page-header">
      <h1>缴费提醒配置</h1>
      <p>配置社保和公积金的缴费日期及提醒时间（按账套配置）</p>
    </div>

    <div v-if="accountSetId" class="reminder-content">
      <!-- 缴费日期配置 -->
      <el-card class="config-card">
        <template #header>
          <div class="card-header">
            <span>缴费日期配置（按月份）</span>
          </div>
        </template>

        <el-tabs v-model="activePaymentType" @tab-change="loadDueDateConfigs">
          <el-tab-pane label="社保" name="social_security">
            <div class="due-date-config">
              <div class="batch-config">
                <span>批量设置：</span>
                <el-input-number
                  v-model="batchDueDay"
                  :min="1"
                  :max="31"
                  placeholder="缴费日"
                  style="width: 120px; margin: 0 10px;"
                />
                <span>号</span>
                <el-button type="primary" @click="batchSetDueDay" :loading="saving">
                  批量应用到所有月份
                </el-button>
              </div>

              <el-table :data="socialSecurityConfigs" v-loading="dueDateLoading" stripe style="margin-top: 20px;">
                <el-table-column prop="month" label="月份" width="100">
                  <template #default="{ row }">
                    {{ row.month }}月
                  </template>
                </el-table-column>
                <el-table-column label="缴费日" width="200">
                  <template #default="{ row }">
                    <el-input-number
                      v-model="row.due_day"
                      :min="1"
                      :max="31"
                      size="small"
                      style="width: 120px;"
                    />
                    <span style="margin-left: 5px;">号</span>
                  </template>
                </el-table-column>
                <el-table-column label="说明">
                  <template #default="{ row }">
                    每年{{ row.month }}月的{{ row.due_day }}号缴费
                  </template>
                </el-table-column>
              </el-table>

              <div style="margin-top: 20px; text-align: right;">
                <el-button type="primary" @click="saveDueDateConfigs('social_security')" :loading="saving">
                  保存社保配置
                </el-button>
              </div>
            </div>
          </el-tab-pane>

          <el-tab-pane label="公积金" name="housing_fund">
            <div class="due-date-config">
              <div class="batch-config">
                <span>批量设置：</span>
                <el-input-number
                  v-model="batchDueDay"
                  :min="1"
                  :max="31"
                  placeholder="缴费日"
                  style="width: 120px; margin: 0 10px;"
                />
                <span>号</span>
                <el-button type="primary" @click="batchSetDueDay" :loading="saving">
                  批量应用到所有月份
                </el-button>
              </div>

              <el-table :data="housingFundConfigs" v-loading="dueDateLoading" stripe style="margin-top: 20px;">
                <el-table-column prop="month" label="月份" width="100">
                  <template #default="{ row }">
                    {{ row.month }}月
                  </template>
                </el-table-column>
                <el-table-column label="缴费日" width="200">
                  <template #default="{ row }">
                    <el-input-number
                      v-model="row.due_day"
                      :min="1"
                      :max="31"
                      size="small"
                      style="width: 120px;"
                    />
                    <span style="margin-left: 5px;">号</span>
                  </template>
                </el-table-column>
                <el-table-column label="说明">
                  <template #default="{ row }">
                    每年{{ row.month }}月的{{ row.due_day }}号缴费
                  </template>
                </el-table-column>
              </el-table>

              <div style="margin-top: 20px; text-align: right;">
                <el-button type="primary" @click="saveDueDateConfigs('housing_fund')" :loading="saving">
                  保存公积金配置
                </el-button>
              </div>
            </div>
          </el-tab-pane>
        </el-tabs>
      </el-card>

      <!-- 提醒时间配置 -->
      <el-card class="config-card">
        <template #header>
          <div class="card-header">
            <span>提醒时间配置（社保和公积金通用）</span>
            <el-button type="primary" size="small" @click="showReminderDialog = true">
              <el-icon><Plus /></el-icon>
              添加提醒
            </el-button>
          </div>
        </template>

        <el-alert
          title="说明"
          type="info"
          :closable="false"
          style="margin-bottom: 20px;"
        >
          提醒时间配置对社保和公积金通用，对所有月份通用。社保提醒财务人员，公积金提醒业务发起人。提醒会显示在首页提醒事项中。
        </el-alert>

        <el-table :data="reminderConfigs" v-loading="reminderLoading" stripe>
          <el-table-column prop="days_before" label="提前天数" width="150">
            <template #default="{ row }">
              {{ row.days_before === 0 ? '当天' : `提前${row.days_before}天` }}
            </template>
          </el-table-column>
          <el-table-column prop="reminder_time" label="提醒时间" width="150" />
          <el-table-column label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="row.is_active ? 'success' : 'info'">
                {{ row.is_active ? '启用' : '禁用' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="150">
            <template #default="{ row }">
              <el-button type="primary" size="small" @click="editReminderConfig(row)">
                编辑
              </el-button>
              <el-button type="danger" size="small" @click="deleteReminderConfig(row)">
                删除
              </el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-card>
    </div>

    <el-empty v-else description="请在顶部选择账套" />

    <!-- 提醒时间配置对话框 -->
    <el-dialog
      v-model="showReminderDialog"
      :title="reminderForm.id ? '编辑提醒时间' : '添加提醒时间'"
      width="500px"
    >
      <el-form :model="reminderForm" :rules="reminderRules" ref="reminderFormRef" label-width="120px">
        <el-form-item label="提前天数" prop="days_before">
          <el-input-number
            v-model="reminderForm.days_before"
            :min="0"
            :max="30"
            placeholder="请输入"
          />
          <span style="margin-left: 10px;">天（0表示当天）</span>
        </el-form-item>

        <el-form-item label="提醒时间" prop="reminder_time">
          <el-time-picker
            v-model="reminderForm.reminder_time"
            format="HH:mm:ss"
            value-format="HH:mm:ss"
            placeholder="选择时间"
          />
        </el-form-item>

        <el-form-item label="是否启用" prop="is_active">
          <el-switch v-model="reminderForm.is_active" />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="showReminderDialog = false">取消</el-button>
        <el-button type="primary" @click="saveReminderConfig" :loading="saving">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import request from '@/api/request'
import { useAccountSetStore } from '@/stores/accountSet'

const accountSetStore = useAccountSetStore()
const accountSetId = computed(() => accountSetStore.currentAccountSetId)

// 当前选中的缴费类型
const activePaymentType = ref('social_security')

// 缴费日期配置
const socialSecurityConfigs = ref([])
const housingFundConfigs = ref([])
const dueDateLoading = ref(false)
const batchDueDay = ref(15)

// 提醒时间配置
const reminderConfigs = ref([])
const reminderLoading = ref(false)
const showReminderDialog = ref(false)
const reminderFormRef = ref()

const reminderForm = reactive({
  id: null,
  days_before: 1,
  reminder_time: '10:00:00',
  is_active: true
})

const reminderRules = {
  days_before: [{ required: true, message: '请输入提前天数', trigger: 'blur' }],
  reminder_time: [{ required: true, message: '请选择提醒时间', trigger: 'change' }]
}

const saving = ref(false)

// 初始化12个月的配置
const initMonthConfigs = (paymentType) => {
  const configs = []
  for (let month = 1; month <= 12; month++) {
    configs.push({
      account_set_id: accountSetId.value,
      payment_type: paymentType,
      month: month,
      due_day: 15
    })
  }
  return configs
}

// 加载缴费日期配置
const loadDueDateConfigs = async () => {
  if (!accountSetId.value) return

  dueDateLoading.value = true
  try {
    const response = await request.get('/payment-reminders/due-date-configs', {
      params: { account_set_id: accountSetId.value }
    })
    if (response.success) {
      const configs = response.data
      
      // 分离社保和公积金配置
      const socialConfigs = configs.filter(c => c.payment_type === 'social_security')
      const housingConfigs = configs.filter(c => c.payment_type === 'housing_fund')
      
      // 如果没有配置，初始化12个月
      if (socialConfigs.length === 0) {
        socialSecurityConfigs.value = initMonthConfigs('social_security')
      } else {
        // 确保有12个月的配置
        socialSecurityConfigs.value = []
        for (let month = 1; month <= 12; month++) {
          const existing = socialConfigs.find(c => c.month === month)
          if (existing) {
            socialSecurityConfigs.value.push(existing)
          } else {
            socialSecurityConfigs.value.push({
              account_set_id: accountSetId.value,
              payment_type: 'social_security',
              month: month,
              due_day: 15
            })
          }
        }
      }
      
      if (housingConfigs.length === 0) {
        housingFundConfigs.value = initMonthConfigs('housing_fund')
      } else {
        housingFundConfigs.value = []
        for (let month = 1; month <= 12; month++) {
          const existing = housingConfigs.find(c => c.month === month)
          if (existing) {
            housingFundConfigs.value.push(existing)
          } else {
            housingFundConfigs.value.push({
              account_set_id: accountSetId.value,
              payment_type: 'housing_fund',
              month: month,
              due_day: 15
            })
          }
        }
      }
    }
  } catch (error) {
    console.error('加载缴费日期配置失败:', error)
    ElMessage.error('加载缴费日期配置失败')
  } finally {
    dueDateLoading.value = false
  }
}

// 批量设置缴费日
const batchSetDueDay = () => {
  if (!batchDueDay.value || batchDueDay.value < 1 || batchDueDay.value > 31) {
    ElMessage.warning('请输入有效的缴费日（1-31）')
    return
  }

  const configs = activePaymentType.value === 'social_security' 
    ? socialSecurityConfigs.value 
    : housingFundConfigs.value

  configs.forEach(config => {
    config.due_day = batchDueDay.value
  })

  ElMessage.success('已批量设置，请点击保存按钮保存配置')
}

// 保存缴费日期配置
const saveDueDateConfigs = async (paymentType) => {
  if (!accountSetId.value) {
    ElMessage.warning('请在顶部选择账套')
    return
  }

  const configs = paymentType === 'social_security' 
    ? socialSecurityConfigs.value 
    : housingFundConfigs.value

  // 验证配置
  for (const config of configs) {
    if (!config.due_day || config.due_day < 1 || config.due_day > 31) {
      ElMessage.warning(`${config.month}月的缴费日无效，请输入1-31之间的数字`)
      return
    }
  }

  saving.value = true
  try {
    await request.post('/payment-reminders/due-date-configs/batch', {
      account_set_id: accountSetId.value,
      payment_type: paymentType,
      configs: configs.map(c => ({
        month: c.month,
        due_day: c.due_day
      }))
    })
    ElMessage.success('保存成功')
    loadDueDateConfigs()
  } catch (error) {
    console.error('保存失败:', error)
    ElMessage.error('保存失败')
  } finally {
    saving.value = false
  }
}

// 加载提醒时间配置
const loadReminderConfigs = async () => {
  if (!accountSetId.value) return

  reminderLoading.value = true
  try {
    const response = await request.get('/payment-reminders/reminder-configs', {
      params: { account_set_id: accountSetId.value }
    })
    if (response.success) {
      reminderConfigs.value = response.data
    }
  } catch (error) {
    console.error('加载提醒时间配置失败:', error)
    ElMessage.error('加载提醒时间配置失败')
  } finally {
    reminderLoading.value = false
  }
}

// 编辑提醒时间配置
const editReminderConfig = (row) => {
  reminderForm.id = row.id
  reminderForm.days_before = row.days_before
  reminderForm.reminder_time = row.reminder_time
  reminderForm.is_active = row.is_active
  showReminderDialog.value = true
}

// 保存提醒时间配置
const saveReminderConfig = async () => {
  if (!reminderFormRef.value) return
  if (!accountSetId.value) {
    ElMessage.warning('请在顶部选择账套')
    return
  }

  try {
    await reminderFormRef.value.validate()
    saving.value = true

    const data = {
      account_set_id: accountSetId.value,
      days_before: reminderForm.days_before,
      reminder_time: reminderForm.reminder_time,
      is_active: reminderForm.is_active
    }

    if (reminderForm.id) {
      await request.put(`/payment-reminders/reminder-configs/${reminderForm.id}`, data)
    } else {
      await request.post('/payment-reminders/reminder-configs', data)
    }

    ElMessage.success('保存成功')
    showReminderDialog.value = false
    
    // 重置表单
    reminderForm.id = null
    reminderForm.days_before = 1
    reminderForm.reminder_time = '10:00:00'
    reminderForm.is_active = true
    
    loadReminderConfigs()
  } catch (error) {
    console.error('保存失败:', error)
    ElMessage.error('保存失败')
  } finally {
    saving.value = false
  }
}

// 删除提醒时间配置
const deleteReminderConfig = async (row) => {
  try {
    await ElMessageBox.confirm('确定要删除这条提醒配置吗？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })

    await request.delete(`/payment-reminders/reminder-configs/${row.id}`)
    ElMessage.success('删除成功')
    loadReminderConfigs()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      ElMessage.error('删除失败')
    }
  }
}

onMounted(() => {
  if (accountSetId.value) {
    loadDueDateConfigs()
    loadReminderConfigs()
  }
})

// 监听账套变化
watch(accountSetId, (newVal) => {
  if (newVal) {
    loadDueDateConfigs()
    loadReminderConfigs()
  }
})
</script>

<style scoped>
.payment-reminder-page {
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

.reminder-content {
  max-width: 1200px;
}

.config-card {
  margin-bottom: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.due-date-config {
  padding: 10px 0;
}

.batch-config {
  display: flex;
  align-items: center;
  padding: 15px;
  background-color: #f5f7fa;
  border-radius: 4px;
}
</style>
