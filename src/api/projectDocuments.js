import request from './request'

/**
 * 获取项目资料配置列表
 */
export function getProjectDocumentConfigs(projectId) {
  return request({
    url: `/projects/${projectId}/document-configs`,
    method: 'get'
  })
}

/**
 * 创建项目资料配置
 */
export function createProjectDocumentConfig(projectId, data) {
  return request({
    url: `/projects/${projectId}/document-configs`,
    method: 'post',
    data
  })
}

/**
 * 更新项目资料配置
 */
export function updateProjectDocumentConfig(projectId, configId, data) {
  return request({
    url: `/projects/${projectId}/document-configs/${configId}`,
    method: 'put',
    data
  })
}

/**
 * 删除项目资料配置
 */
export function deleteProjectDocumentConfig(projectId, configId) {
  return request({
    url: `/projects/${projectId}/document-configs/${configId}`,
    method: 'delete'
  })
}

/**
 * 更新资料配置排序
 */
export function updateDocumentConfigsSort(projectId, configs) {
  return request({
    url: `/projects/${projectId}/document-configs/sort`,
    method: 'post',
    data: { configs }
  })
}

/**
 * 获取员工资料上传列表（含配置和上传状态）
 */
export function getEmployeeDocuments(employeeId) {
  return request({
    url: `/employees/${employeeId}/documents`,
    method: 'get'
  })
}

/**
 * 上传员工资料
 */
export function uploadEmployeeDocument(employeeId, data) {
  return request({
    url: `/employees/${employeeId}/documents/upload`,
    method: 'post',
    data,
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

/**
 * 删除员工资料
 */
export function deleteEmployeeDocument(employeeId, documentId) {
  return request({
    url: `/employees/${employeeId}/documents/${documentId}`,
    method: 'delete'
  })
}

/**
 * 下载员工资料
 */
export function downloadEmployeeDocument(employeeId, documentId) {
  return request({
    url: `/employees/${employeeId}/documents/${documentId}/download`,
    method: 'get',
    responseType: 'blob'
  })
}

