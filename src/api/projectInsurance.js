import request from './request'

// 获取项目的医保地区
export function getProjectMedicalInsuranceRegions(projectId, params) {
  return request({
    url: `/projects/${projectId}/medical-insurance-regions`,
    method: 'get',
    params
  })
}

// 设置项目的医保地区
export function setProjectMedicalInsuranceRegions(projectId, data) {
  return request({
    url: `/projects/${projectId}/medical-insurance-regions`,
    method: 'post',
    data
  })
}

// 获取项目的其他保险保单
export function getProjectOtherInsurancePolicies(projectId, params) {
  return request({
    url: `/projects/${projectId}/other-insurance-policies`,
    method: 'get',
    params
  })
}

// 设置项目的其他保险保单
export function setProjectOtherInsurancePolicies(projectId, data) {
  return request({
    url: `/projects/${projectId}/other-insurance-policies`,
    method: 'post',
    data
  })
}

// 获取可用的医保地区列表
export function getAvailableMedicalInsuranceRegions(params) {
  return request({
    url: '/projects/available/medical-insurance-regions',
    method: 'get',
    params
  })
}

// 获取可用的其他保险保单列表
export function getAvailableOtherInsurancePolicies(params) {
  return request({
    url: '/projects/available/other-insurance-policies',
    method: 'get',
    params
  })
}

// 获取员工项目的医保地区
export function getEmployeeProjectMedicalInsuranceRegions(projectId, params) {
  return request({
    url: `/employees/projects/${projectId}/medical-insurance-regions`,
    method: 'get',
    params
  })
}

// 获取员工项目的其他保险保单
export function getEmployeeProjectOtherInsurancePolicies(projectId, params) {
  return request({
    url: `/employees/projects/${projectId}/other-insurance-policies`,
    method: 'get',
    params
  })
}
