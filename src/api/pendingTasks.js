import request from './request'

/**
 * 获取待办任务列表
 */
export function getPendingTasks(params) {
  return request({
    url: '/pending-tasks',
    method: 'get',
    params
  })
}

/**
 * 获取待办任务统计
 */
export function getPendingTasksStatistics(params) {
  return request({
    url: '/pending-tasks/statistics',
    method: 'get',
    params
  })
}

/**
 * 标记任务为已完成
 */
export function markTaskAsCompleted(id) {
  return request({
    url: `/pending-tasks/${id}/complete`,
    method: 'post'
  })
}
