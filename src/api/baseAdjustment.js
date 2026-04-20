import request from '@/api/request'

/**
 * 获取在职员工列表（带调差记录）
 * @param {Object} params 查询参数
 * @returns {Promise}
 */
export function getEmployeesWithAdjustments(params) {
  return request({
    url: '/base-adjustments',
    method: 'get',
    params
  })
}

/**
 * 检查调整权限
 * @param {Object} params 查询参数
 * @returns {Promise}
 */
export function checkAdjustmentPermission(params) {
  return request({
    url: '/base-adjustments/check-permission',
    method: 'get',
    params
  })
}

/**
 * 创建或更新基数调整
 * @param {Object} data 调整数据
 * @returns {Promise}
 */
export function saveBaseAdjustment(data) {
  return request({
    url: '/base-adjustments',
    method: 'post',
    data
  })
}

/**
 * 删除基数调整记录
 * @param {Number} id 记录ID
 * @returns {Promise}
 */
export function deleteBaseAdjustment(id) {
  return request({
    url: `/base-adjustments/${id}`,
    method: 'delete'
  })
}

/**
 * 立即生效基数调整
 * @param {Number} id 记录ID
 * @returns {Promise}
 */
export function applyNow(id) {
  return request({
    url: `/base-adjustments/${id}/apply-now`,
    method: 'post'
  })
}

/**
 * 获取员工调整历史
 * @param {Number} employeeId 员工ID
 * @param {Object} params 查询参数
 * @returns {Promise}
 */
export function getAdjustmentHistory(employeeId, params) {
  return request({
    url: `/base-adjustments/employee/${employeeId}/history`,
    method: 'get',
    params
  })
}

/**
 * 批量应用已到期的基数调整（定时任务）
 * @returns {Promise}
 */
export function applyDue() {
  return request({
    url: '/base-adjustments/apply-due',
    method: 'post'
  })
}

