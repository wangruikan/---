import request from './request'

// 获取付款列表
export function getPayments(params) {
  return request({
    url: '/payments',
    method: 'get',
    params
  })
}

// 创建付款
export function createPayment(data) {
  return request({
    url: '/payments',
    method: 'post',
    data
  })
}

// 更新付款
export function updatePayment(id, data) {
  return request({
    url: `/payments/${id}`,
    method: 'put',
    data
  })
}

// 删除付款
export function deletePayment(id) {
  return request({
    url: `/payments/${id}`,
    method: 'delete'
  })
}

// 提交付款
export function submitPayment(id) {
  return request({
    url: `/payments/${id}/submit`,
    method: 'post'
  })
}

// 审批付款
export function approvePayment(id) {
  return request({
    url: `/payments/${id}/approve`,
    method: 'post'
  })
}

// 执行付款
export function payPayment(id) {
  return request({
    url: `/payments/${id}/pay`,
    method: 'post'
  })
}

// 记录付款
export function recordPayment(id, data) {
  return request({
    url: `/payments/${id}/record`,
    method: 'post',
    data
  })
}

// 获取付款汇总
export function getPaymentSummary(params) {
  return request({
    url: '/payments/summary',
    method: 'get',
    params
  })
}
