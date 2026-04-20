import request from './request'

/**
 * 获取可以生成工资表的项目列表（考勤已审批）
 * @param {string} period - 工资期间（格式：2025-10）
 */
export function getProjectsWithApprovalStatus(period) {
  return request({
    url: '/payroll/projects-with-approval',
    method: 'get',
    params: { period }
  })
}

/**
 * 获取工资表列表（统计视图）
 */
export function getSalarySheets(params) {
  return request({
    url: '/salaries',
    method: 'get',
    params
  })
}

/**
 * 生成工资表
 */
export function generateSalarySheet(data) {
  return request({
    url: '/salaries/generate',
    method: 'post',
    data
  })
}

/**
 * 获取工资明细
 */
export function getSalaryDetails(params) {
  return request({
    url: '/salaries/details',
    method: 'get',
    params
  })
}

/**
 * 提交审批
 */
export function submitSalary(data) {
  return request({
    url: '/salaries/submit',
    method: 'post',
    data
  })
}

/**
 * 审批通过
 */
export function approveSalary(data) {
  return request({
    url: '/salaries/approve',
    method: 'post',
    data
  })
}

/**
 * 审批拒绝
 */
export function rejectSalary(data) {
  return request({
    url: '/salaries/reject',
    method: 'post',
    data
  })
}

/**
 * 标记发放
 */
export function paySalary(data) {
  return request({
    url: '/salaries/pay',
    method: 'post',
    data
  })
}

/**
 * 删除工资表
 */
export function deleteSalary(params) {
  return request({
    url: '/salaries',
    method: 'delete',
    params
  })
}

/**
 * 发起工资付款申请
 */
export function createSalaryPaymentRequest(data) {
  return request({
    url: '/salaries/create-payment-request',
    method: 'post',
    data
  })
}

/**
 * 导入应发工资Excel
 */
export function importGrossSalary(formData) {
  return request({
    url: '/salaries/import-gross-salary',
    method: 'post',
    data: formData,
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

/**
 * 提交审批前验证工资表
 */
export function validateBeforeSubmit(data) {
  return request({
    url: '/salaries/validate-before-submit',
    method: 'post',
    data
  })
}
