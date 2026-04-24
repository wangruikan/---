import request from './request'

export function getHousingFundRegions(params) {
  return request({
    url: '/housing-fund-regions',
    method: 'get',
    params
  })
}

export function createHousingFundRegion(data) {
  return request({
    url: '/housing-fund-regions',
    method: 'post',
    data
  })
}

export function getHousingFundRegion(id) {
  return request({
    url: `/housing-fund-regions/${id}`,
    method: 'get'
  })
}

export function updateHousingFundRegion(id, data) {
  return request({
    url: `/housing-fund-regions/${id}`,
    method: 'put',
    data
  })
}

export function deleteHousingFundRegion(id) {
  return request({
    url: `/housing-fund-regions/${id}`,
    method: 'delete'
  })
}

export function getHousingFundRegionConfigs(regionId) {
  return request({
    url: `/housing-fund-regions/${regionId}/configs`,
    method: 'get'
  })
}

export function getHousingFundRegionLimitHistories(id) {
  return request({
    url: `/housing-fund-regions/${id}/limit-histories`,
    method: 'get'
  })
}
