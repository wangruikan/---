import request from './request'

/**
 * 获取工资表审批列表
 */
export function getSalaryApprovals(params) {
  return request({
    url: '/salary-approvals',
    method: 'get',
    params
  })
}

/**
 * 提交工资表审批
 */
export function submitSalaryApproval(data) {
  return request({
    url: '/salary-approvals/submit',
    method: 'post',
    data
  })
}

/**
 * 审批通过
 */
export function approveSalaryApproval(data) {
  return request({
    url: '/salary-approvals/approve',
    method: 'post',
    data
  })
}

/**
 * 审批拒绝
 */
export function rejectSalaryApproval(data) {
  return request({
    url: '/salary-approvals/reject',
    method: 'post',
    data
  })
}

/**
 * 撤回审批
 */
export function withdrawSalaryApproval(params) {
  return request({
    url: '/salary-approvals',
    method: 'delete',
    params
  })
}

/**
 * 上传附件
 */
export function uploadSalaryApprovalAttachment(formData) {
  return request({
    url: '/salary-approvals/attachments/upload',
    method: 'post',
    data: formData,
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

/**
 * 删除附件
 */
export function deleteSalaryApprovalAttachment(params) {
  return request({
    url: '/salary-approvals/attachments',
    method: 'delete',
    params
  })
}

/**
 * 获取附件列表
 */
export function getSalaryApprovalAttachments(params) {
  return request({
    url: '/salary-approvals/attachments',
    method: 'get',
    params
  })
}

/**
 * 下载附件
 */
export function downloadSalaryApprovalAttachment(id) {
  return `/api/salary-approvals/attachments/${id}/download`
}

/**
 * 完成提交（创建审批流程实例）
 */
export function completeSalaryApprovalSubmission(data) {
  return request({
    url: '/salary-approvals/complete-submission',
    method: 'post',
    data
  })
}

