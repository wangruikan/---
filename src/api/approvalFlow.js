import request from './request'

const getApprovalStorageBaseUrl = () => {
  const baseURL = request.defaults.baseURL || ''

  if (/^https?:\/\//i.test(baseURL)) {
    return baseURL.replace(/\/api\/?$/i, '')
  }

  if (import.meta.env.DEV) {
    return 'http://localhost:8000'
  }

  return window.location.origin
}

const normalizeApprovalStoragePath = (filePath) => {
  const rawPath = String(filePath || '').trim()
  if (!rawPath) {
    return ''
  }

  if (/^https?:\/\//i.test(rawPath)) {
    return rawPath
  }

  let normalizedPath = rawPath.replace(/\\/g, '/').replace(/^\/+/, '')

  if (normalizedPath.startsWith('storage/')) {
    normalizedPath = normalizedPath.slice('storage/'.length)
  }

  const [pathname, query = ''] = normalizedPath.split('?')
  const encodedPath = pathname
    .split('/')
    .filter(Boolean)
    .map(segment => encodeURIComponent(segment))
    .join('/')

  if (!encodedPath) {
    return ''
  }

  return `${getApprovalStorageBaseUrl()}/storage/${encodedPath}${query ? `?${query}` : ''}`
}

/**
 * 创建审批流程
 */
export const createApprovalFlow = (data) => {
  return request({
    url: '/approvals',
    method: 'post',
    data
  })
}

/**
 * 我的待办
 */
export const getMyTasks = (params) => {
  return request({
    url: '/approvals/my-tasks',
    method: 'get',
    params
  })
}

/**
 * 我审批的
 */
export const getMyApproved = (params) => {
  return request({
    url: '/approvals/my-approved',
    method: 'get',
    params
  })
}

/**
 * 我发起的
 */
export const getMyInitiated = (params) => {
  return request({
    url: '/approvals/my-initiated',
    method: 'get',
    params
  })
}

/**
 * 抄送给我
 */
export const getCCToMe = (params) => {
  return request({
    url: '/approvals/cc-to-me',
    method: 'get',
    params
  })
}

/**
 * 审批详情
 */
export const getApprovalDetail = (id) => {
  return request({
    url: `/approvals/${id}`,
    method: 'get'
  })
}

/**
 * 审批通过
 */
export const approveRecord = (recordId, data) => {
  return request({
    url: `/approvals/records/${recordId}/approve`,
    method: 'post',
    data
  })
}

/**
 * 退回上一级
 */
export const returnRecord = (recordId, data) => {
  return request({
    url: `/approvals/records/${recordId}/return`,
    method: 'post',
    data
  })
}

/**
 * 驳回（拒绝整个流程）
 */
export const rejectRecord = (recordId, data) => {
  return request({
    url: `/approvals/records/${recordId}/reject`,
    method: 'post',
    data
  })
}

/**
 * 下载审批实例附件
 */
export const downloadApprovalAttachment = (instanceId, attachmentId) => {
  return request({
    url: `/approvals/${instanceId}/attachments/${attachmentId}/download`,
    method: 'get',
    responseType: 'blob'
  })
}

/**
 * 获取审批实例附件下载地址（用于PDF预览）
 */
export const getApprovalAttachmentDownloadUrl = (instanceId, attachmentId) => {
  return `/api/approvals/${instanceId}/attachments/${attachmentId}/download`
}

/**
 * 获取审批实例附件真实文件地址（浏览器直接打开）
 */
export const getApprovalAttachmentFileUrl = (filePath) => {
  return normalizeApprovalStoragePath(filePath)
}

/**
 * 上传合成后的PDF
 */
export const uploadSignedPDF = (recordId, formData) => {
  return request({
    url: `/approvals/records/${recordId}/upload-signed-pdf`,
    method: 'post',
    data: formData,
    headers: { 'Content-Type': 'multipart/form-data' }
  })
}
