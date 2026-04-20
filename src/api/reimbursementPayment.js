import request from './request'

/**
 * 获取报销付款申请列表
 */
export function getReimbursementPaymentRequests(params) {
  return request({
    url: '/reimbursement-payment-requests',
    method: 'get',
    params
  })
}

/**
 * 提交报销付款申请
 */
export function submitReimbursementPaymentRequest(data) {
  return request({
    url: '/reimbursement-payment-requests/submit',
    method: 'post',
    data
  })
}

/**
 * 上传报销付款附件
 */
export function uploadReimbursementPaymentAttachment(formData) {
  return request({
    url: '/reimbursement-payment-requests/attachments/upload',
    method: 'post',
    data: formData,
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

/**
 * 完成报销付款申请提交（创建审批流程）
 */
export function completeReimbursementPaymentSubmission(data) {
  return request({
    url: '/reimbursement-payment-requests/complete-submission',
    method: 'post',
    data
  })
}

/**
 * 删除报销付款附件
 */
export function deleteReimbursementPaymentAttachment(id) {
  return request({
    url: '/reimbursement-payment-requests/attachments',
    method: 'delete',
    data: { id }
  })
}

