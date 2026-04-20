<template>
  <div class="dashboard">
    <div class="page-header">
      <div class="header-left">
        <h1>仪表盘</h1>
        <p>欢迎使用人力资源管理系统</p>
      </div>
    </div>
    
    <!-- 统计卡片 -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon employees">
          <el-icon size="24"><User /></el-icon>
        </div>
        <div class="stat-content">
          <div class="stat-number">{{ stats.totalEmployees }}</div>
          <div class="stat-label">总员工数</div>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-icon projects">
          <el-icon size="24"><Folder /></el-icon>
        </div>
        <div class="stat-content">
          <div class="stat-number">{{ stats.totalProjects }}</div>
          <div class="stat-label">项目数量</div>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-icon pending">
          <el-icon size="24"><Clock /></el-icon>
        </div>
        <div class="stat-content">
          <div class="stat-number">{{ stats.pendingApprovals }}</div>
          <div class="stat-label">待审批</div>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-icon contracts">
          <el-icon size="24"><Document /></el-icon>
        </div>
        <div class="stat-content">
          <div class="stat-number">{{ stats.totalContracts }}</div>
          <div class="stat-label">合同总数</div>
        </div>
      </div>
    </div>
    
    <!-- 图表区域 -->
    <div class="charts-grid">
      <div class="chart-card">
        <div class="chart-header">
          <h3>员工分布</h3>
        </div>
        <div class="chart-content">
          <div ref="employeeChartRef" class="chart"></div>
        </div>
      </div>
      
      <div class="chart-card">
        <div class="chart-header">
          <h3>合同状态统计</h3>
        </div>
        <div class="chart-content">
          <div ref="contractChartRef" class="chart"></div>
        </div>
      </div>
    </div>
    
    <!-- 项目状态看板 -->
    <div class="project-statistics">
      <div class="card">
        <div class="card-header">
          <h3>项目状态看板</h3>
        </div>
        <div class="card-content">
          <div v-if="projects.length === 0" class="empty-projects">
            <el-empty description="暂无项目数据" />
          </div>
          <div v-else class="project-chart-container">
            <div ref="projectChartRef" class="project-chart"></div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- 提醒事项 -->
    <div class="reminders">
      <div class="card">
        <div class="card-header">
          <h3>提醒事项</h3>
        </div>
        <div class="card-content">
          <div v-if="reminders.length === 0" class="empty-reminders">
            <el-empty description="暂无提醒事项" />
          </div>
          <div v-else class="reminder-list">
            <div
              v-for="reminder in reminders"
              :key="reminder.id"
              class="reminder-item"
              :class="[reminder.type, `priority-${reminder.priority}`]"
            >
              <div class="reminder-icon">
                <el-icon v-if="reminder.type === 'contract'"><Document /></el-icon>
                <el-icon v-else-if="reminder.type === 'retirement'"><UserFilled /></el-icon>
                <el-icon v-else-if="reminder.type === 'invoice_reminder'"><Money /></el-icon>
                <el-icon v-else><Warning /></el-icon>
              </div>
              <div class="reminder-content" @click="handleReminderClick(reminder)">
                <div class="reminder-title">
                  {{ reminder.title }}
                  <el-tag 
                    v-if="reminder.priority === 'high'" 
                    type="danger" 
                    size="small"
                    class="priority-tag"
                  >
                    紧急
                  </el-tag>
                  <el-tag 
                    v-else-if="reminder.priority === 'medium'" 
                    type="warning" 
                    size="small"
                    class="priority-tag"
                  >
                    重要
                  </el-tag>
                </div>
                <div class="reminder-desc">{{ reminder.content || reminder.description }}</div>
                <!-- 如果是已提交原因的通知，显示原因 -->
                <div v-if="reminder.type === 'invoice_reason_submitted' && reminder.data?.reason" class="reminder-reason">
                  <el-tag type="info" size="small">原因</el-tag>
                  {{ reminder.data.reason }}
                </div>
                <!-- 如果是未开票提醒但有人已提交原因，显示原因 -->
                <div v-if="reminder.type === 'invoice_reminder' && reminder.data?.has_reason_submitted" class="reminder-reason">
                  <el-tag type="success" size="small">{{ reminder.data.submitted_by }} 已提交原因</el-tag>
                  {{ reminder.data.submitted_reason }}
                </div>
                <div class="reminder-time">{{ formatTime(reminder.created_at) }}</div>
              </div>
              <!-- 未开票提醒的操作按钮（只对 invoice_reminder 类型且没有人提交原因时显示） -->
              <div v-if="reminder.type === 'invoice_reminder' && !reminder.data?.has_reason_submitted" class="reminder-actions">
                <el-button 
                  type="primary" 
                  size="small" 
                  @click.stop="handleSubmitInvoiceReason(reminder)"
                >
                  填写原因
                </el-button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- 提交未开票原因对话框 -->
  <el-dialog
    v-model="invoiceReasonDialogVisible"
    title="填写未开票原因"
    width="500px"
  >
    <el-form :model="invoiceReasonForm" label-width="100px">
      <el-form-item label="项目">
        <span>{{ currentInvoiceReminder?.data?.project_name || '-' }}</span>
      </el-form-item>
      <el-form-item label="期间">
        <span>{{ currentInvoiceReminder?.data?.year }}年{{ currentInvoiceReminder?.data?.month }}月</span>
      </el-form-item>
      <el-form-item label="未开票原因" required>
        <el-input
          v-model="invoiceReasonForm.reason"
          type="textarea"
          :rows="4"
          placeholder="请输入未开票原因（如：本月无业务、项目暂停等）"
          maxlength="500"
          show-word-limit
        />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="invoiceReasonDialogVisible = false">取消</el-button>
      <el-button type="primary" @click="handleConfirmSubmitReason" :loading="submittingReason">
        提交
      </el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue'
import { User, Folder, Clock, Money, Document, UserFilled, Warning, Bell, Refresh, CircleCheck, CloseBold } from '@element-plus/icons-vue'
import * as echarts from 'echarts'
import dayjs from 'dayjs'
import { markReminderAsRead, getDashboardData } from '@/api/dashboard'
import { submitInvoiceReason } from '@/api/invoiceReminder'
import { useAccountSetStore } from '@/stores/accountSet'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'

const stats = ref({
  totalEmployees: 0,
  totalProjects: 0,
  pendingApprovals: 0,
  totalContracts: 0
})

const projects = ref([])
const projectStatusStats = ref([])  // 项目状态统计
const reminders = ref([])
const refreshing = ref(false)

const employeeChartRef = ref()
const contractChartRef = ref()
const projectChartRef = ref()

// 图表数据缓存
const employeeDistributionData = ref([])
const contractStatisticsData = ref([])

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('zh-CN', {
    style: 'currency',
    currency: 'CNY'
  }).format(amount)
}

const formatTime = (time) => {
  return dayjs(time).format('MM-DD HH:mm')
}

// 统一加载所有Dashboard数据
const loadDashboardData = async () => {
  try {
    if (!accountSetStore.currentAccountSet?.id) {
      console.warn('未选择账套，无法加载Dashboard数据')
      return
    }

    const response = await getDashboardData({
      account_set_id: accountSetStore.currentAccountSet.id
    })

    if (response.success) {
      // 更新统计数据
      stats.value = response.data.stats || {}
      
      // 更新项目数据（新格式：包含list和statusStats）
      const projectsData = response.data.projects || {}
      projects.value = projectsData.list || []
      projectStatusStats.value = projectsData.statusStats || []
      
      // 更新提醒数据
      reminders.value = response.data.reminders || []
      
      // 保存图表数据供initCharts使用
      employeeDistributionData.value = response.data.employeeDistribution || []
      contractStatisticsData.value = response.data.contractStatistics || []
      
      // 初始化图表
      await initCharts()
    } else {
      console.error('获取Dashboard数据失败:', response.message)
      ElMessage.error(response.message || '获取Dashboard数据失败')
    }
  } catch (error) {
    console.error('Load dashboard data error:', error)
    ElMessage.error('获取Dashboard数据失败')
  }
}


// 获取项目状态类型
const getProjectStatusType = (status) => {
  switch (status) {
    case 'completed': return 'success'
    case 'active': return 'primary'
    case 'pending': return 'warning'
    case 'paused': return 'info'
    default: return 'info'
  }
}

// 获取项目状态文本
const getProjectStatusText = (status) => {
  switch (status) {
    case 'completed': return '已完成'
    case 'active': return '进行中'
    case 'pending': return '待开始'
    case 'paused': return '已暂停'
    default: return '未知'
  }
}

// 获取指定状态的项目数量
const getProjectCountByStatus = (status) => {
  return projects.value.filter(p => p.status === status).length
}

// 获取进度条颜色
const getProgressColor = (progress) => {
  if (progress >= 80) return '#67c23a'
  if (progress >= 60) return '#e6a23c'
  if (progress >= 40) return '#409eff'
  return '#f56c6c'
}

const accountSetStore = useAccountSetStore()
const router = useRouter()

// 未开票原因对话框相关
const invoiceReasonDialogVisible = ref(false)
const currentInvoiceReminder = ref(null)
const invoiceReasonForm = ref({
  reason: ''
})
const submittingReason = ref(false)

// 打开填写未开票原因对话框
const handleSubmitInvoiceReason = (reminder) => {
  currentInvoiceReminder.value = reminder
  invoiceReasonForm.value.reason = ''
  invoiceReasonDialogVisible.value = true
}

// 确认提交未开票原因
const handleConfirmSubmitReason = async () => {
  if (!invoiceReasonForm.value.reason || !invoiceReasonForm.value.reason.trim()) {
    ElMessage.warning('请填写未开票原因')
    return
  }

  submittingReason.value = true
  try {
    const response = await submitInvoiceReason({
      notification_id: currentInvoiceReminder.value.id,
      reason: invoiceReasonForm.value.reason.trim()
    })

    if (response.success) {
      ElMessage.success('提交成功')
      invoiceReasonDialogVisible.value = false
      
      // 立即更新当前提醒：改变类型并添加原因数据
      const reminderIndex = reminders.value.findIndex(r => r.id === currentInvoiceReminder.value.id)
      if (reminderIndex !== -1) {
        reminders.value[reminderIndex] = {
          ...reminders.value[reminderIndex],
          type: 'invoice_reason_submitted',
          content: `您已提交未开票原因：${invoiceReasonForm.value.reason.trim()}`,
          data: {
            ...reminders.value[reminderIndex].data,
            reason: invoiceReasonForm.value.reason.trim()
          }
        }
      }
      
      currentInvoiceReminder.value = null
      invoiceReasonForm.value.reason = ''
    } else {
      ElMessage.error(response.message || '提交失败')
    }
  } catch (error) {
    console.error('提交未开票原因失败:', error)
    ElMessage.error('提交失败，请重试')
  } finally {
    submittingReason.value = false
  }
}

// 处理提醒点击
const handleReminderClick = async (reminder) => {
  try {
    // 如果有标记已读的逻辑，先标记为已读
    if (reminder.source && reminder.source_id) {
      await markReminderAsRead({
        source: reminder.source,
        source_id: reminder.source_id
      })
    }

    // 根据提醒类型跳转到相应页面
    if (reminder.action_url) {
      router.push(reminder.action_url)
    } else {
      // 默认跳转逻辑
      switch (reminder.source) {
        case 'contract_reminder':
          router.push('/assessment') // 合同提醒跳转到考核页面
          break
        case 'assessment_record':
          router.push('/assessment')
          break
        case 'delivery_reminder':
          router.push('/document-delivery')
          break
        default:
          console.log('点击提醒:', reminder.title)
      }
    }
  } catch (error) {
    console.error('处理提醒点击失败:', error)
    ElMessage.error('操作失败')
  }
}

const initCharts = async () => {
  // 等待 DOM 更新完成
  await nextTick()
  
  try {
    // 初始化员工分布图表
    if (employeeChartRef.value && employeeDistributionData.value.length > 0) {
      const employeeChart = echarts.init(employeeChartRef.value)
      const employeeOption = {
        tooltip: {
          trigger: 'item'
        },
        legend: {
          orient: 'vertical',
          left: 'left'
        },
        series: [
          {
            name: '员工分布',
            type: 'pie',
            radius: '50%',
            data: employeeDistributionData.value,
            emphasis: {
              itemStyle: {
                shadowBlur: 10,
                shadowOffsetX: 0,
                shadowColor: 'rgba(0, 0, 0, 0.5)'
              }
            }
          }
        ]
      }
      employeeChart.setOption(employeeOption)
    }
    
    // 初始化合同状态统计图表
    if (contractChartRef.value && contractStatisticsData.value.length > 0) {
      const contractChart = echarts.init(contractChartRef.value)
      const contractOption = {
        tooltip: {
          trigger: 'item',
          formatter: '{a} <br/>{b}: {c} ({d}%)'
        },
        legend: {
          orient: 'vertical',
          left: 'left'
        },
        series: [
          {
            name: '合同状态',
            type: 'pie',
            radius: ['40%', '70%'],
            avoidLabelOverlap: false,
            itemStyle: {
              borderRadius: 10,
              borderColor: '#fff',
              borderWidth: 2
            },
            label: {
              show: false,
              position: 'center'
            },
            emphasis: {
              label: {
                show: true,
                fontSize: '18',
                fontWeight: 'bold'
              }
            },
            labelLine: {
              show: false
            },
            data: contractStatisticsData.value
          }
        ]
      }
      contractChart.setOption(contractOption)
    }
    
    // 初始化项目状态图表 - 显示每个项目的状态（进行中/已结束/未开始）
    if (projectChartRef.value && projects.value.length > 0) {
      const projectChart = echarts.init(projectChartRef.value)
      
      // 准备数据：项目名称和状态
      const projectNames = projects.value.slice(0, 10).map(p => 
        p.name.length > 10 ? p.name.substring(0, 10) + '...' : p.name
      )
      
      // 根据状态设置颜色和标签
      const getStatusInfo = (status) => {
        switch (status) {
          case 'active': return { color: '#67C23A', label: '进行中' }
          case 'completed': return { color: '#909399', label: '已结束' }
          case 'pending': return { color: '#E6A23C', label: '未开始' }
          default: return { color: '#409EFF', label: '未知' }
        }
      }
      
      // 为每个项目生成数据（固定值100，用颜色区分状态）
      const barData = projects.value.slice(0, 10).map(p => {
        const info = getStatusInfo(p.status)
        return {
          value: 100,
          itemStyle: {
            color: info.color,
            borderRadius: [0, 4, 4, 0]
          },
          statusLabel: info.label,
          projectName: p.name,
          startDate: p.start_date || '未设置',
          endDate: p.end_date || '未设置',
          employeeCount: p.employee_count || 0
        }
      })
      
      const projectOption = {
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'shadow'
          },
          formatter: function(params) {
            const data = params[0].data
            return `<div style="font-weight:bold;margin-bottom:5px;">${data.projectName}</div>
                    状态: ${data.statusLabel}<br/>
                    开始时间: ${data.startDate}<br/>
                    结束时间: ${data.endDate}<br/>
                    员工数: ${data.employeeCount}人`
          }
        },
        legend: {
          data: ['进行中', '已结束', '未开始'],
          top: 5,
          textStyle: {
            color: '#fff'
          },
          itemWidth: 14,
          itemHeight: 14
        },
        grid: {
          left: '3%',
          right: '15%',
          bottom: '3%',
          top: '40px',
          containLabel: true
        },
        xAxis: {
          type: 'value',
          max: 100,
          show: false
        },
        yAxis: {
          type: 'category',
          data: projectNames,
          axisLabel: {
            color: '#303133',  // 改为深色，这样在白色背景上可见
            fontSize: 12
          },
          axisLine: {
            lineStyle: {
              color: '#DCDFE6'  // 改为浅灰色
            }
          }
        },
        series: [
          {
            name: '项目状态',
            type: 'bar',
            data: barData,
            label: {
              show: true,
              position: 'right',
              formatter: function(params) {
                return params.data.statusLabel
              },
              color: '#fff',
              fontSize: 12
            },
            barWidth: '50%'
          }
        ]
      }
      projectChart.setOption(projectOption)
      
      // 监听窗口大小变化
      window.addEventListener('resize', () => {
        projectChart.resize()
      })
    }
  } catch (error) {
    console.error('初始化图表失败:', error)
  }
}

// 刷新所有数据
const refreshAllData = async () => {
  if (refreshing.value) return // 防止重复刷新
  
  refreshing.value = true
  try {
    console.log('开始刷新Dashboard数据...')
    
    // 使用统一接口加载所有数据
    await loadDashboardData()
    
    ElMessage.success('数据刷新成功')
    console.log('Dashboard数据刷新完成')
  } catch (error) {
    console.error('刷新数据失败:', error)
    ElMessage.error('数据刷新失败')
  } finally {
    refreshing.value = false
  }
}

// 监听账套变化
watch(() => accountSetStore.currentAccountSet, (newAccountSet) => {
  if (newAccountSet?.id) {
    console.log('账套变化，刷新Dashboard数据:', newAccountSet.name)
    refreshAllData()
  }
}, { immediate: true }) // 改为 immediate: true，这样初始化时如果账套已加载就会立即执行

onMounted(() => {
  // 如果账套还没加载，等待加载完成后会通过 watch 触发刷新
  // 如果账套已经加载，watch 的 immediate: true 会立即触发刷新
  console.log('Dashboard mounted, 当前账套:', accountSetStore.currentAccountSet?.name)
})
</script>

<style scoped>
.dashboard {
  padding: 0;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding: 20px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.header-left h1 {
  margin: 0 0 4px 0;
  color: #303133;
  font-size: 24px;
}

.header-left p {
  margin: 0;
  color: #606266;
  font-size: 14px;
}

.header-right {
  display: flex;
  align-items: center;
}


.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.stat-card {
  background: white;
  border-radius: 8px;
  padding: 20px;
  display: flex;
  align-items: center;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  transition: transform 0.3s;
}

.stat-card:hover {
  transform: translateY(-2px);
}

.stat-icon {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 20px;
}

.stat-icon.employees {
  background: #e3f2fd;
  color: #1976d2;
}

.stat-icon.projects {
  background: #f3e5f5;
  color: #7b1fa2;
}

.stat-icon.pending {
  background: #fff3e0;
  color: #f57c00;
}

.stat-icon.contracts {
  background: #e3f2fd;
  color: #1976d2;
}

.stat-content {
  flex: 1;
}

.stat-number {
  font-size: 28px;
  font-weight: bold;
  color: #303133;
  margin-bottom: 4px;
}

.stat-label {
  color: #606266;
  font-size: 14px;
}

.charts-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 30px;
}

.chart-card {
  background: white;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.chart-header {
  margin-bottom: 20px;
}

.chart-header h3 {
  font-size: 16px;
  color: #303133;
  margin: 0;
}

.chart {
  height: 300px;
}

.recent-activities,
.reminders {
  margin-bottom: 30px;
}

.card {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 20px 0;
  border-bottom: 1px solid #f0f0f0;
  margin-bottom: 20px;
}

.card-header h3 {
  font-size: 16px;
  color: #303133;
  margin: 0;
}

.card-content {
  padding: 0 20px 20px;
}

.empty-activities,
.empty-reminders {
  text-align: center;
  padding: 40px 0;
}

.activity-list,
.reminder-list {
  max-height: 400px;
  overflow-y: auto;
}

.activity-item,
.reminder-item {
  display: flex;
  align-items: center;
  padding: 15px 0;
  border-bottom: 1px solid #f0f0f0;
}

.activity-item:last-child,
.reminder-item:last-child {
  border-bottom: none;
}

.activity-icon,
.reminder-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #f5f7fa;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 15px;
  color: #606266;
}

.reminder-item.contract .reminder-icon {
  background: #e3f2fd;
  color: #1976d2;
}

.reminder-item.retirement .reminder-icon {
  background: #fff3e0;
  color: #f57c00;
}

.reminder-item.warning .reminder-icon {
  background: #ffebee;
  color: #d32f2f;
}

.activity-content,
.reminder-content {
  flex: 1;
}

.activity-title,
.reminder-title {
  font-weight: 500;
  color: #303133;
  margin-bottom: 4px;
}

.activity-time,
.reminder-desc {
  color: #606266;
  font-size: 14px;
}

.reminder-time {
  color: #909399;
  font-size: 12px;
  margin-top: 4px;
}

.reminder-reason {
  margin-top: 8px;
  padding: 8px;
  background-color: #f5f7fa;
  border-radius: 4px;
  font-size: 13px;
  color: #606266;
  line-height: 1.5;
}

.reminder-reason .el-tag {
  margin-right: 8px;
}

.reminder-item {
  cursor: pointer;
  transition: background-color 0.3s;
}

.reminder-item:hover {
  background-color: #f8f9fa;
}

.priority-tag {
  margin-left: 8px;
}

.reminder-item.priority-high {
  border-left: 3px solid #f56c6c;
}

.reminder-item.priority-medium {
  border-left: 3px solid #e6a23c;
}

.reminder-item.priority-low {
  border-left: 3px solid #67c23a;
}

.reminder-actions {
  margin-left: 12px;
  display: flex;
  gap: 8px;
}

.reminder-item.invoice_reminder .reminder-icon {
  background: #fff3e0;
  color: #f57c00;
}

/* 项目统计样式 */
.project-statistics {
  grid-column: 1 / -1;
}

.project-stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
  margin-top: 16px;
}

.stat-card {
  display: flex;
  align-items: center;
  padding: 20px;
  background: #fff;
  border-radius: 8px;
  border: 1px solid #e4e7ed;
  transition: all 0.3s;
}

.stat-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
}

.stat-icon {
  width: 60px;
  height: 60px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  margin-right: 16px;
}

.stat-info {
  flex: 1;
}

.stat-value {
  font-size: 28px;
  font-weight: bold;
  color: #303133;
  line-height: 1;
  margin-bottom: 8px;
}

.stat-label {
  font-size: 14px;
  color: #909399;
}

.empty-projects {
  text-align: center;
  padding: 40px 0;
}

.project-chart-container {
  width: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
}

.project-chart {
  width: 100%;
  height: 280px;
}

@media (max-width: 768px) {
  .charts-grid {
    grid-template-columns: 1fr;
  }
  
  .stats-grid {
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  }
  
  .project-grid {
    grid-template-columns: 1fr;
  }
}
</style>
