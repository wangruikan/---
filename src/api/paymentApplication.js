import request from './request'

/**
 * 获取付款申请列表
 */
export function getPaymentApplications(params) {
  return request({
    url: '/payment-applications',
    method: 'get',
    params
  })
}

/**
 * 从汇总申请创建付款申请
 */
export function createFromProcessApproval(processApprovalId, data) {
  return request({
    url: `/payment-applications/from-process/${processApprovalId}`,
    method: 'post',
    data
  })
}

/**
 * 获取付款申请详情
 */
export function getPaymentApplicationDetail(id) {
  return request({
    url: `/payment-applications/${id}`,
    method: 'get'
  })
}

/**
 * 上传附件
 */
export function uploadAttachment(id, formData) {
  return request({
    url: `/payment-applications/${id}/upload-attachment`,
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
export function deleteAttachment(id, attachmentId) {
  return request({
    url: `/payment-applications/${id}/attachments/${attachmentId}`,
    method: 'delete'
  })
}

/**
 * 提交付款申请
 */
export function submitPaymentApplication(id, data) {
  return request({
    url: `/payment-applications/${id}/submit`,
    method: 'post',
    data
  })
}

/**
 * 重新申请（用于被驳回的付款申请）
 */
export function resubmitPaymentApplication(id, data) {
  return request({
    url: `/payment-applications/${id}/resubmit`,
    method: 'post',
    data
  })
}

/**
 * 提交保险付款申请（带附件上传功能）
 */
export function submitInsurancePaymentRequest(data) {
  return request({
    url: '/insurance-payment-requests/submit',
    method: 'post',
    data
  })
}

/**
 * 完成保险付款申请提交（创建审批流程）
 */
export function completeInsurancePaymentSubmission(data) {
  return request({
    url: '/insurance-payment-requests/complete-submission',
    method: 'post',
    data
  })
}

/**
 * 上传保险付款申请附件
 */
export function uploadInsurancePaymentAttachment(formData) {
  return request({
    url: '/insurance-payment-requests/attachments/upload',
    method: 'post',
    data: formData,
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

/**
 * 删除保险付款申请附件
 */
export function deleteInsurancePaymentAttachment(id) {
  return request({
    url: '/insurance-payment-requests/attachments',
    method: 'delete',
    data: { id }
  })
}

/**
 * 检查发票上传权限
 */
export function checkInvoiceUploadPermission(paymentRequestId) {
  return request({
    url: '/insurance-payment-requests/check-invoice-permission',
    method: 'get',
    params: { payment_request_id: paymentRequestId }
  })
}

/**
 * 上传发票附件
 */
export function uploadInvoiceAttachment(formData) {
  return request({
    url: '/insurance-payment-requests/invoice-attachments/upload',
    method: 'post',
    data: formData,
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

/**
 * 删除发票附件
 */
export function deleteInvoiceAttachment(id) {
  return request({
    url: '/insurance-payment-requests/invoice-attachments',
    method: 'delete',
    data: { id }
  })
}

/**
 * 获取发票附件列表
 */
export function getInvoiceAttachments(paymentRequestId) {
  return request({
    url: '/insurance-payment-requests/invoice-attachments',
    method: 'get',
    params: { payment_request_id: paymentRequestId }
  })
}

/**
 * 提交发票审批
 */
export function submitInvoiceApproval(paymentRequestId) {
  return request({
    url: '/insurance-payment-requests/submit-invoice-approval',
    method: 'post',
    data: { payment_request_id: paymentRequestId }
  })
}


/**
 * 补传附件 - 确认完成（更新upload_later标识）
 */
export function supplementAttachment(id, formData) {
  return request({
    url: `/payment-applications/${id}/supplement-attachment`,
    method: 'put',
    data: formData
  })
}

/**
 * 获取付款申请附件列表（用于补传）
 */
export function getPaymentRequestAttachments(paymentRequestId) {
  return request({
    url: '/payment-request-attachments',
    method: 'get',
    params: { payment_request_id: paymentRequestId }
  })
}

/**
 * 删除付款申请附件（用于补传）
 */
export function deletePaymentRequestAttachment(paymentRequestId, attachmentId) {
  return request({
    url: '/payment-request-attachments',
    method: 'delete',
    data: { id: attachmentId }
  })
}
