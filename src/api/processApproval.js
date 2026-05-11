import request from './request'

/**
 * 获取流程列表
 */
export function getProcessList(params) {
  return request({
    url: '/process-approvals',
    method: 'get',
    params
  })
}

/**
 * 获取流程详情
 */
export function getProcessDetail(id) {
  return request({
    url: `/process-approvals/${id}`,
    method: 'get'
  })
}

/**
 * 创建流程
 */
export function createProcess(data) {
  return request({
    url: '/process-approvals',
    method: 'post',
    data
  })
}

/**
 * 上传附件
 */
export function uploadAttachment(id, formData) {
  return request({
    url: `/process-approvals/${id}/upload-attachment`,
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
    url: `/process-approvals/${id}/attachments/${attachmentId}`,
    method: 'delete'
  })
}

/**
 * 下载附件
 */
export function downloadAttachment(id, attachmentId) {
  return request({
    url: `/process-approvals/${id}/attachments/${attachmentId}/download`,
    method: 'get',
    responseType: 'blob'
  })
}

/**
 * Get attachment download URL (for PDF preview component).
 */
export function getDownloadAttachmentUrl(id, attachmentId) {
  return `/api/process-approvals/${id}/attachments/${attachmentId}/download`
}

/**
 * 提交流程
 */
export function submitProcess(id, data) {
  return request({
    url: `/process-approvals/${id}/submit`,
    method: 'post',
    data
  })
}


/**
 * 删除流程
 */
export function deleteProcess(id) {
  return request({
    url: `/process-approvals/${id}`,
    method: 'delete'
  })
}

