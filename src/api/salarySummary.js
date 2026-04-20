import request from './request'

/**
 * 获取工资汇总列表
 */
export function getSalarySummaries(params) {
  return request({
    url: '/salary-summaries',
    method: 'get',
    params
  })
}

/**
 * 获取工资汇总详情
 */
export function getSalarySummary(id) {
  return request({
    url: `/salary-summaries/${id}`,
    method: 'get'
  })
}

