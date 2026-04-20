import request from './request'

/**
 * 获取理赔记录列表
 */
export function getCompensationRecords(params) {
  return request({
    url: '/insurance-compensation',
    method: 'get',
    params
  })
}

/**
 * 获取可申报工伤的员工列表
 */
export function getWorkInjuryEmployees(params) {
  return request({
    url: '/insurance-compensation/work-injury-employees',
    method: 'get',
    params
  })
}

/**
 * 获取可申报商业险的员工列表
 */
export function getCommercialInsuranceEmployees(params) {
  return request({
    url: '/insurance-compensation/commercial-insurance-employees',
    method: 'get',
    params
  })
}

/**
 * 创建理赔记录（步骤1：登记）
 */
export function createCompensationRecord(data) {
  return request({
    url: '/insurance-compensation',
    method: 'post',
    data
  })
}

/**
 * 获取理赔记录详情
 */
export function getCompensationRecordDetail(id) {
  return request({
    url: `/insurance-compensation/${id}`,
    method: 'get'
  })
}

/**
 * 更新步骤2（工伤认定结果 或 商业险提供材料）
 */
export function updateStep2(id, data) {
  return request({
    url: `/insurance-compensation/${id}/step2`,
    method: 'post',
    data
  })
}

/**
 * 更新步骤3（工伤提交材料 或 商业险理赔到账）
 */
export function updateStep3(id, data) {
  return request({
    url: `/insurance-compensation/${id}/step3`,
    method: 'post',
    data
  })
}

/**
 * 上传附件
 */
export function uploadAttachment(data) {
  return request({
    url: '/insurance-compensation/attachments/upload',
    method: 'post',
    data,
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

/**
 * 删除附件
 */
export function deleteAttachment(id) {
  return request({
    url: `/insurance-compensation/attachments/${id}`,
    method: 'delete'
  })
}

/**
 * 删除理赔记录
 */
export function deleteCompensationRecord(id) {
  return request({
    url: `/insurance-compensation/${id}`,
    method: 'delete'
  })
}
