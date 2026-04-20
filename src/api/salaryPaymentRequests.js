import request from './request'

/**
 * 获取工资付款申请列表
 */
export function getSalaryPaymentRequests(params) {
  return request({
    url: '/salary-payment-requests',
    method: 'get',
    params
  })
}

/**
 * 提交工资付款申请
 */
export function submitSalaryPaymentRequest(data) {
  return request({
    url: '/salary-payment-requests/submit',
    method: 'post',
    data
  })
}

/**
 * 完成提交（创建审批流程）
 */
export function completeSalaryPaymentSubmission(data) {
  return request({
    url: '/salary-payment-requests/complete-submission',
    method: 'post',
    data
  })
}

/**
 * 上传附件
 */
export function uploadSalaryPaymentAttachment(data) {
  return request({
    url: '/salary-payment-requests/attachments/upload',
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
export function deleteSalaryPaymentAttachment(id) {
  return request({
    url: '/salary-payment-requests/attachments',
    method: 'delete',
    data: { id }
  })
}

