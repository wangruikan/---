import request from './request'

export function getInvoiceContentConfigs(params) {
  return request({
    url: '/invoice-content-configs',
    method: 'get',
    params
  })
}

export function getAllInvoiceContentConfigs() {
  return request({
    url: '/invoice-content-configs/all',
    method: 'get'
  })
}

export function createInvoiceContentConfig(data) {
  return request({
    url: '/invoice-content-configs',
    method: 'post',
    data
  })
}

export function updateInvoiceContentConfig(id, data) {
  return request({
    url: `/invoice-content-configs/${id}`,
    method: 'put',
    data
  })
}

export function deleteInvoiceContentConfig(id) {
  return request({
    url: `/invoice-content-configs/${id}`,
    method: 'delete'
  })
}

export function updateInvoiceContentConfigSort(items) {
  return request({
    url: '/invoice-content-configs/sort',
    method: 'post',
    data: { items }
  })
}
