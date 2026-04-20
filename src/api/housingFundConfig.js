import request from './request'

export function getHousingFundConfigs(params) {
  return request({
    url: '/housing-fund-configs',
    method: 'get',
    params
  })
}

export function createHousingFundConfig(data) {
  return request({
    url: '/housing-fund-configs',
    method: 'post',
    data
  })
}

export function getHousingFundConfig(id) {
  return request({
    url: `/housing-fund-configs/${id}`,
    method: 'get'
  })
}

export function updateHousingFundConfig(id, data) {
  return request({
    url: `/housing-fund-configs/${id}`,
    method: 'put',
    data
  })
}

export function deleteHousingFundConfig(id) {
  return request({
    url: `/housing-fund-configs/${id}`,
    method: 'delete'
  })
}

export function setDefaultHousingFundConfig(id) {
  return request({
    url: `/housing-fund-configs/${id}/set-default`,
    method: 'post'
  })
}
