import request from './request'

/**
 * 获取发票项目列表
 */
export function getInvoiceProjects(params) {
  return request({
    url: '/invoice-projects',
    method: 'get',
    params
  })
}

/**
 * 获取所有发票项目（下拉选择）
 */
export function getAllInvoiceProjects() {
  return request({
    url: '/invoice-projects/all',
    method: 'get'
  })
}

/**
 * 创建发票项目
 */
export function createInvoiceProject(data) {
  return request({
    url: '/invoice-projects',
    method: 'post',
    data
  })
}

/**
 * 更新发票项目
 */
export function updateInvoiceProject(id, data) {
  return request({
    url: `/invoice-projects/${id}`,
    method: 'put',
    data
  })
}

/**
 * 删除发票项目
 */
export function deleteInvoiceProject(id) {
  return request({
    url: `/invoice-projects/${id}`,
    method: 'delete'
  })
}

