import request from './request'

/**
 * 获取出款汇总列表
 */
export function getPaymentSummaries(params) {
  return request({
    url: '/payment-summaries',
    method: 'get',
    params
  })
}

/**
 * 导出出款汇总Excel
 */
export function exportPaymentSummaries(params) {
  return request({
    url: '/payment-summaries/export',
    method: 'get',
    params,
    responseType: 'blob'
  })
}

