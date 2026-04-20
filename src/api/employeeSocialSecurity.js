import request from './request'

// 获取项目的社保地区列表（用于员工参保选择）
export function getProjectSocialSecurityRegions(projectId, params) {
  return request({
    url: `/employees/projects/${projectId}/social-security-regions`,
    method: 'get',
    params
  })
}

// 获取项目的公积金地区列表（用于员工参保选择）
export function getProjectHousingFundRegions(projectId, params) {
  return request({
    url: `/employees/projects/${projectId}/housing-fund-regions`,
    method: 'get',
    params
  })
}
