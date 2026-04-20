import request from './request'

// 获取发票列表
export function getInvoices(params) {
  return request({
    url: '/invoices',
    method: 'get',
    params
  })
}

// 创建发票
export function createInvoice(data) {
  return request({
    url: '/invoices',
    method: 'post',
    data
  })
}

// 更新发票
export function updateInvoice(id, data) {
  return request({
    url: `/invoices/${id}`,
    method: 'put',
    data
  })
}

// 删除发票
export function deleteInvoice(id) {
  return request({
    url: `/invoices/${id}`,
    method: 'delete'
  })
}

// 提交发票
export function submitInvoice(id) {
  return request({
    url: `/invoices/${id}/submit`,
    method: 'post'
  })
}

// 审批发票
export function approveInvoice(id) {
  return request({
    url: `/invoices/${id}/approve`,
    method: 'post'
  })
}

// 开票
export function issueInvoice(id) {
  return request({
    url: `/invoices/${id}/issue`,
    method: 'post'
  })
}

// 获取发票汇总
export function getInvoiceSummary(params) {
  return request({
    url: '/invoices/summary',
    method: 'get',
    params
  })
}
