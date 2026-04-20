import request from './request'

/**
 * 获取流程记录列表
 */
export function getProcessRecords(params) {
  return request({
    url: '/process-records',
    method: 'get',
    params
  })
}

/**
 * 获取流程记录统计
 */
export function getProcessRecordStats(params) {
  return request({
    url: '/process-records/stats',
    method: 'get',
    params
  })
}

/**
 * 检查用户是否有权限访问流程记录管理
 */
export function checkProcessRecordAccess(params) {
  return request({
    url: '/process-records/check-access',
    method: 'get',
    params
  })
}
