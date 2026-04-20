import request from './request'

// 获取财务软件链接列表
export function getFinancialSoftwareLinks(params) {
  return request({
    url: '/financial-software-links',
    method: 'get',
    params
  })
}

// 创建财务软件链接
export function createFinancialSoftwareLink(data) {
  return request({
    url: '/financial-software-links',
    method: 'post',
    data
  })
}

// 更新财务软件链接
export function updateFinancialSoftwareLink(id, data) {
  return request({
    url: `/financial-software-links/${id}`,
    method: 'put',
    data
  })
}

// 删除财务软件链接
export function deleteFinancialSoftwareLink(id) {
  return request({
    url: `/financial-software-links/${id}`,
    method: 'delete'
  })
}
