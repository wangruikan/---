import request from './request'

// 获取项目的社保地区列表
export function getProjectSocialSecurityRegions(projectId) {
  return request({
    url: `/projects/${projectId}/social-security-regions`,
    method: 'get'
  })
}

// 设置项目的社保地区
export function setProjectSocialSecurityRegions(projectId, data) {
  return request({
    url: `/projects/${projectId}/social-security-regions`,
    method: 'post',
    data
  })
}

// 获取项目的公积金地区列表
export function getProjectHousingFundRegions(projectId) {
  return request({
    url: `/projects/${projectId}/housing-fund-regions`,
    method: 'get'
  })
}

// 设置项目的公积金地区
export function setProjectHousingFundRegions(projectId, data) {
  return request({
    url: `/projects/${projectId}/housing-fund-regions`,
    method: 'post',
    data
  })
}

// 获取可用的社保地区列表
export function getAvailableSocialSecurityRegions(params) {
  return request({
    url: '/projects/available/social-security-regions',
    method: 'get',
    params
  })
}

// 获取可用的公积金地区列表
export function getAvailableHousingFundRegions(params) {
  return request({
    url: '/projects/available/housing-fund-regions',
    method: 'get',
    params
  })
}
