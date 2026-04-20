import request from './request'

/**
 * 获取Dashboard统计数据
 */
export function getDashboardStats(params) {
  return request({
    url: '/dashboard/stats',
    method: 'get',
    params
  })
}

/**
 * 获取提醒事项
 */
export function getReminders(params) {
  return request({
    url: '/dashboard/reminders',
    method: 'get',
    params
  })
}

/**
 * 获取项目列表
 */
export function getProjects(params) {
  return request({
    url: '/dashboard/projects',
    method: 'get',
    params
  })
}

/**
 * 标记提醒为已读
 */
export function markReminderAsRead(data) {
  return request({
    url: '/dashboard/reminders/mark-read',
    method: 'post',
    data
  })
}

/**
 * 检查新入职员工资料
 */
export function checkNewEmployeeDocuments(data) {
  return request({
    url: '/assessment-records/check-new-employee-documents',
    method: 'post',
    data
  })
}

/**
 * 获取员工分布数据
 */
export function getEmployeeDistribution(params) {
  return request({
    url: '/dashboard/employee-distribution',
    method: 'get',
    params
  })
}

/**
 * 获取合同状态统计数据
 */
export function getContractStatistics(params) {
  return request({
    url: '/dashboard/contract-statistics',
    method: 'get',
    params
  })
}

/**
 * 获取Dashboard所有数据（统一接口）
 */
export function getDashboardData(params) {
  return request({
    url: '/dashboard/data',
    method: 'get',
    params
  })
}
