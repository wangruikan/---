import request from './request'

/**
 * 获取报销列表
 */
export function getReimbursements(params) {
  return request({
    url: '/reimbursements',
    method: 'get',
    params
  })
}

/**
 * 创建报销申请
 */
export function createReimbursement(data) {
  return request({
    url: '/reimbursements',
    method: 'post',
    data
  })
}

/**
 * 获取报销详情
 */
export function getReimbursementDetail(id) {
  return request({
    url: `/reimbursements/${id}`,
    method: 'get'
  })
}

/**
 * 上传报销附件
 */
export function uploadReimbursementAttachment(formData) {
  return request({
    url: '/reimbursements/upload-attachment',
    method: 'post',
    data: formData,
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

/**
 * 审批报销申请
 */
export function approveReimbursement(id, data) {
  return request({
    url: `/reimbursements/${id}/approve`,
    method: 'post',
    data
  })
}

/**
 * 拒绝报销申请
 */
export function rejectReimbursement(id, data) {
  return request({
    url: `/reimbursements/${id}/reject`,
    method: 'post',
    data
  })
}

/**
 * 删除报销申请
 */
export function deleteReimbursement(id) {
  return request({
    url: `/reimbursements/${id}`,
    method: 'delete'
  })
}

/**
 * 完成报销申请提交（创建审批流程）
 */
export function completeReimbursementSubmission(data) {
  return request({
    url: '/reimbursements/complete-submission',
    method: 'post',
    data
  })
}

