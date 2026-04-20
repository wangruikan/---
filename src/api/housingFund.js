import request from './request'

// 获取公积金列表
export function getHousingFunds(params) {
  return request({
    url: '/housing-fund',
    method: 'get',
    params
  })
}

// 创建公积金
export function createHousingFund(data) {
  return request({
    url: '/housing-fund',
    method: 'post',
    data
  })
}

// 更新公积金
export function updateHousingFund(id, data) {
  return request({
    url: `/housing-fund/${id}`,
    method: 'put',
    data
  })
}

// 删除公积金
export function deleteHousingFund(id) {
  return request({
    url: `/housing-fund/${id}`,
    method: 'delete'
  })
}
