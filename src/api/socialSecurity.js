import request from './request'

// 获取社保地区列表
export function getSocialSecurityRegions(params) {
  return request({
    url: '/social-security',
    method: 'get',
    params
  })
}

// 获取单个社保地区详情
export function getSocialSecurityRegion(id) {
  return request({
    url: `/social-security/${id}`,
    method: 'get'
  })
}

// 创建社保地区
export function createSocialSecurityRegion(data) {
  return request({
    url: '/social-security',
    method: 'post',
    data
  })
}

// 更新社保地区
export function updateSocialSecurityRegion(id, data) {
  return request({
    url: `/social-security/${id}`,
    method: 'put',
    data
  })
}

// 删除社保地区
export function deleteSocialSecurityRegion(id) {
  return request({
    url: `/social-security/${id}`,
    method: 'delete'
  })
}

// 添加社保类型
export function addSocialSecurityType(regionId, data) {
  return request({
    url: `/social-security/${regionId}/types`,
    method: 'post',
    data
  })
}

// 更新社保类型
export function updateSocialSecurityType(typeId, data) {
  return request({
    url: `/social-security/types/${typeId}`,
    method: 'put',
    data
  })
}

// 删除社保类型
export function deleteSocialSecurityType(typeId) {
  return request({
    url: `/social-security/types/${typeId}`,
    method: 'delete'
  })
}
