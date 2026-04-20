<template>
  <div class="test-integration">
    <div class="page-header">
      <h1>系统集成测试</h1>
      <p>测试各个模块的API连接和功能完整性</p>
    </div>
    
    <div class="test-grid">
      <el-card class="test-card">
        <template #header>
          <div class="card-header">
            <span>员工管理</span>
            <el-button type="primary" size="small" @click="testEmployees">测试</el-button>
          </div>
        </template>
        <div class="test-result">
          <el-tag :type="testResults.employees.status ? 'success' : 'danger'">
            {{ testResults.employees.status ? '通过' : '失败' }}
          </el-tag>
          <p v-if="testResults.employees.message">{{ testResults.employees.message }}</p>
        </div>
      </el-card>
      
      <el-card class="test-card">
        <template #header>
          <div class="card-header">
            <span>项目管理</span>
            <el-button type="primary" size="small" @click="testProjects">测试</el-button>
          </div>
        </template>
        <div class="test-result">
          <el-tag :type="testResults.projects.status ? 'success' : 'danger'">
            {{ testResults.projects.status ? '通过' : '失败' }}
          </el-tag>
          <p v-if="testResults.projects.message">{{ testResults.projects.message }}</p>
        </div>
      </el-card>
      
      <el-card class="test-card">
        <template #header>
          <div class="card-header">
            <span>考勤管理</span>
            <el-button type="primary" size="small" @click="testAttendance">测试</el-button>
          </div>
        </template>
        <div class="test-result">
          <el-tag :type="testResults.attendance.status ? 'success' : 'danger'">
            {{ testResults.attendance.status ? '通过' : '失败' }}
          </el-tag>
          <p v-if="testResults.attendance.message">{{ testResults.attendance.message }}</p>
        </div>
      </el-card>
      
      <el-card class="test-card">
        <template #header>
          <div class="card-header">
            <span>薪金管理</span>
            <el-button type="primary" size="small" @click="testSalaries">测试</el-button>
          </div>
        </template>
        <div class="test-result">
          <el-tag :type="testResults.salaries.status ? 'success' : 'danger'">
            {{ testResults.salaries.status ? '通过' : '失败' }}
          </el-tag>
          <p v-if="testResults.salaries.message">{{ testResults.salaries.message }}</p>
        </div>
      </el-card>
      
      <el-card class="test-card">
        <template #header>
          <div class="card-header">
            <span>保险管理</span>
            <el-button type="primary" size="small" @click="testInsurance">测试</el-button>
          </div>
        </template>
        <div class="test-result">
          <el-tag :type="testResults.insurance.status ? 'success' : 'danger'">
            {{ testResults.insurance.status ? '通过' : '失败' }}
          </el-tag>
          <p v-if="testResults.insurance.message">{{ testResults.insurance.message }}</p>
        </div>
      </el-card>
      
      <el-card class="test-card">
        <template #header>
          <div class="card-header">
            <span>审批管理</span>
            <el-button type="primary" size="small" @click="testApprovals">测试</el-button>
          </div>
        </template>
        <div class="test-result">
          <el-tag :type="testResults.approvals.status ? 'success' : 'danger'">
            {{ testResults.approvals.status ? '通过' : '失败' }}
          </el-tag>
          <p v-if="testResults.approvals.message">{{ testResults.approvals.message }}</p>
        </div>
      </el-card>
      
      <el-card class="test-card">
        <template #header>
          <div class="card-header">
            <span>付款管理</span>
            <el-button type="primary" size="small" @click="testPayments">测试</el-button>
          </div>
        </template>
        <div class="test-result">
          <el-tag :type="testResults.payments.status ? 'success' : 'danger'">
            {{ testResults.payments.status ? '通过' : '失败' }}
          </el-tag>
          <p v-if="testResults.payments.message">{{ testResults.payments.message }}</p>
        </div>
      </el-card>
      
      <el-card class="test-card">
        <template #header>
          <div class="card-header">
            <span>发票管理</span>
            <el-button type="primary" size="small" @click="testInvoices">测试</el-button>
          </div>
        </template>
        <div class="test-result">
          <el-tag :type="testResults.invoices.status ? 'success' : 'danger'">
            {{ testResults.invoices.status ? '通过' : '失败' }}
          </el-tag>
          <p v-if="testResults.invoices.message">{{ testResults.invoices.message }}</p>
        </div>
      </el-card>
      
      <el-card class="test-card">
        <template #header>
          <div class="card-header">
            <span>招聘管理</span>
            <el-button type="primary" size="small" @click="testRecruitment">测试</el-button>
          </div>
        </template>
        <div class="test-result">
          <el-tag :type="testResults.recruitment.status ? 'success' : 'danger'">
            {{ testResults.recruitment.status ? '通过' : '失败' }}
          </el-tag>
          <p v-if="testResults.recruitment.message">{{ testResults.recruitment.message }}</p>
        </div>
      </el-card>
    </div>
    
    <div class="test-actions">
      <el-button type="primary" @click="runAllTests" :loading="runningAll">
        运行所有测试
      </el-button>
      <el-button @click="clearResults">
        清除结果
      </el-button>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import { getEmployees } from '@/api/employees'
import { getProjects } from '@/api/projects'
import { getAttendanceSheets } from '@/api/attendance'
import { getSalarySheets } from '@/api/salaries'
import { getInsuranceRecords } from '@/api/insurance'
import { getApprovals } from '@/api/approvals'
import { getPayments } from '@/api/payments'
import { getInvoices } from '@/api/invoices'
import { getRecruitments } from '@/api/recruitment'

const runningAll = ref(false)

const testResults = reactive({
  employees: { status: false, message: '' },
  projects: { status: false, message: '' },
  attendance: { status: false, message: '' },
  salaries: { status: false, message: '' },
  insurance: { status: false, message: '' },
  approvals: { status: false, message: '' },
  payments: { status: false, message: '' },
  invoices: { status: false, message: '' },
  recruitment: { status: false, message: '' }
})

const testEmployees = async () => {
  try {
    await getEmployees({ page: 1, per_page: 1 })
    testResults.employees.status = true
    testResults.employees.message = 'API连接正常'
  } catch (error) {
    testResults.employees.status = false
    testResults.employees.message = `API连接失败: ${error.message}`
  }
}

const testProjects = async () => {
  try {
    await getProjects({ page: 1, per_page: 1 })
    testResults.projects.status = true
    testResults.projects.message = 'API连接正常'
  } catch (error) {
    testResults.projects.status = false
    testResults.projects.message = `API连接失败: ${error.message}`
  }
}

const testAttendance = async () => {
  try {
    await getAttendanceSheets({ page: 1, per_page: 1 })
    testResults.attendance.status = true
    testResults.attendance.message = 'API连接正常'
  } catch (error) {
    testResults.attendance.status = false
    testResults.attendance.message = `API连接失败: ${error.message}`
  }
}

const testSalaries = async () => {
  try {
    await getSalarySheets({ page: 1, per_page: 1 })
    testResults.salaries.status = true
    testResults.salaries.message = 'API连接正常'
  } catch (error) {
    testResults.salaries.status = false
    testResults.salaries.message = `API连接失败: ${error.message}`
  }
}

const testInsurance = async () => {
  try {
    await getInsuranceRecords({ page: 1, per_page: 1 })
    testResults.insurance.status = true
    testResults.insurance.message = 'API连接正常'
  } catch (error) {
    testResults.insurance.status = false
    testResults.insurance.message = `API连接失败: ${error.message}`
  }
}

const testApprovals = async () => {
  try {
    await getApprovals({ page: 1, per_page: 1 })
    testResults.approvals.status = true
    testResults.approvals.message = 'API连接正常'
  } catch (error) {
    testResults.approvals.status = false
    testResults.approvals.message = `API连接失败: ${error.message}`
  }
}

const testPayments = async () => {
  try {
    await getPayments({ page: 1, per_page: 1 })
    testResults.payments.status = true
    testResults.payments.message = 'API连接正常'
  } catch (error) {
    testResults.payments.status = false
    testResults.payments.message = `API连接失败: ${error.message}`
  }
}

const testInvoices = async () => {
  try {
    await getInvoices({ page: 1, per_page: 1 })
    testResults.invoices.status = true
    testResults.invoices.message = 'API连接正常'
  } catch (error) {
    testResults.invoices.status = false
    testResults.invoices.message = `API连接失败: ${error.message}`
  }
}

const testRecruitment = async () => {
  try {
    await getRecruitments({ page: 1, per_page: 1 })
    testResults.recruitment.status = true
    testResults.recruitment.message = 'API连接正常'
  } catch (error) {
    testResults.recruitment.status = false
    testResults.recruitment.message = `API连接失败: ${error.message}`
  }
}

const runAllTests = async () => {
  runningAll.value = true
  try {
    await Promise.all([
      testEmployees(),
      testProjects(),
      testAttendance(),
      testSalaries(),
      testInsurance(),
      testApprovals(),
      testPayments(),
      testInvoices(),
      testRecruitment()
    ])
    ElMessage.success('所有测试完成')
  } catch (error) {
    ElMessage.error('测试过程中出现错误')
  } finally {
    runningAll.value = false
  }
}

const clearResults = () => {
  Object.keys(testResults).forEach(key => {
    testResults[key].status = false
    testResults[key].message = ''
  })
}
</script>

<style scoped>
.test-integration {
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

.test-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.test-card {
  min-height: 150px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.test-result {
  text-align: center;
  padding: 20px 0;
}

.test-result p {
  margin-top: 10px;
  color: #606266;
  font-size: 14px;
}

.test-actions {
  text-align: center;
  padding: 20px 0;
}

.test-actions .el-button {
  margin: 0 10px;
}
</style>
