import request from './request'

/**
 * 获取发票申请列表
 */
export function getInvoiceApplications(params) {
  return request({
    url: '/invoice-applications',
    method: 'get',
    params
  })
}

/**
 * 获取发票申请详情
 */
export function getInvoiceApplicationDetail(id) {
  return request({
    url: `/invoice-applications/${id}`,
    method: 'get'
  })
}

/**
 * 创建发票申请
 */
export function createInvoiceApplication(data) {
  return request({
    url: '/invoice-applications',
    method: 'post',
    data
  })
}

/**
 * 删除发票申请
 */
export function deleteInvoiceApplication(id) {
  return request({
    url: `/invoice-applications/${id}`,
    method: 'delete'
  })
}

/**
 * 添加明细项
 */
export function addInvoiceItem(applicationId, data) {
  return request({
    url: `/invoice-applications/${applicationId}/items`,
    method: 'post',
    data
  })
}

/**
 * 更新明细项
 */
export function updateInvoiceItem(applicationId, itemId, data) {
  return request({
    url: `/invoice-applications/${applicationId}/items/${itemId}`,
    method: 'put',
    data
  })
}

/**
 * 删除明细项
 */
export function deleteInvoiceItem(applicationId, itemId) {
  return request({
    url: `/invoice-applications/${applicationId}/items/${itemId}`,
    method: 'delete'
  })
}

/**
 * 生成Excel扣除明细表
 */
export function generateExcel(applicationId) {
  return request({
    url: `/invoice-applications/${applicationId}/generate-excel`,
    method: 'post'
  })
}

/**
 * 上传附件
 */
export function uploadAttachment(applicationId, file) {
  const formData = new FormData()
  formData.append('file', file)
  
  return request({
    url: `/invoice-applications/${applicationId}/attachments`,
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
export function deleteAttachment(applicationId, path) {
  return request({
    url: `/invoice-applications/${applicationId}/attachments`,
    method: 'delete',
    data: { path }
  })
}

/**
 * 提交审批
 */
export function submitInvoiceApplication(applicationId, data = {}) {
  return request({
    url: `/invoice-applications/${applicationId}/submit`,
    method: 'post',
    data
  })
}

/**
 * 重新提交（驳回后）
 */
export function resubmitInvoiceApplication(applicationId) {
  return request({
    url: `/invoice-applications/${applicationId}/resubmit`,
    method: 'post'
  })
}

