import request from './request'

// 获取保险记录列表
export function getInsuranceRecords(params) {
  return request({
    url: '/insurance',
    method: 'get',
    params
  })
}

// 创建保险记录
export function createInsuranceRecord(data) {
  return request({
    url: '/insurance',
    method: 'post',
    data
  })
}

// 更新保险记录
export function updateInsuranceRecord(id, data) {
  return request({
    url: `/insurance/${id}`,
    method: 'put',
    data
  })
}

// 删除保险记录
export function deleteInsuranceRecord(id) {
  return request({
    url: `/insurance/${id}`,
    method: 'delete'
  })
}

// 标记为已完成
export function markAsCompleted(id) {
  return request({
    url: `/insurance/${id}/complete`,
    method: 'post'
  })
}

// 获取过期记录
export function getOverdueRecords(params) {
  return request({
    url: '/insurance/overdue',
    method: 'get',
    params
  })
}

// 获取保险汇总
export function getInsuranceSummary(params) {
  return request({
    url: '/insurance/summary',
    method: 'get',
    params
  })
}
