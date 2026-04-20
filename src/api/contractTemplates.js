import request from './request'

// 获取项目的合同模板列表
export function getContractTemplates(projectId) {
  return request({
    url: `/projects/${projectId}/contract-templates`,
    method: 'get'
  })
}

// 添加合同模板
export function addContractTemplate(projectId, data) {
  return request({
    url: `/projects/${projectId}/contract-templates`,
    method: 'post',
    data
  })
}

// 设置默认模板
export function setDefaultTemplate(templateId) {
  return request({
    url: `/projects/contract-templates/${templateId}/set-default`,
    method: 'post'
  })
}

// 删除合同模板
export function deleteContractTemplate(templateId) {
  return request({
    url: `/projects/contract-templates/${templateId}`,
    method: 'delete'
  })
}

// 获取项目的默认合同模板
export function getDefaultTemplates(projectId) {
  return request({
    url: `/projects/${projectId}/default-templates`,
    method: 'get'
  })
}
