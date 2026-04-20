import request from './request'

// ============ 项目交付配置 ============

// 获取配置列表
export function getDeliveryConfigs(params) {
  return request({
    url: '/delivery-configs',
    method: 'get',
    params
  })
}

// 获取配置详情
export function getDeliveryConfigDetail(id) {
  return request({
    url: `/delivery-configs/${id}`,
    method: 'get'
  })
}

// 创建配置
export function createDeliveryConfig(data) {
  return request({
    url: '/delivery-configs',
    method: 'post',
    data
  })
}

// 更新配置
export function updateDeliveryConfig(id, data) {
  return request({
    url: `/delivery-configs/${id}`,
    method: 'put',
    data
  })
}

// 删除配置
export function deleteDeliveryConfig(id) {
  return request({
    url: `/delivery-configs/${id}`,
    method: 'delete'
  })
}

// 切换配置状态
export function toggleConfigStatus(id) {
  return request({
    url: `/delivery-configs/${id}/toggle-status`,
    method: 'post'
  })
}

// ============ 资料交付记录 ============

// 获取交付记录列表
export function getDocumentDeliveries(params) {
  return request({
    url: '/document-deliveries',
    method: 'get',
    params
  })
}

// 获取我的待办交付
export function getMyPendingDeliveries(params) {
  return request({
    url: '/document-deliveries/my-pending',
    method: 'get',
    params
  })
}

// 获取交付记录详情
export function getDeliveryDetail(id) {
  return request({
    url: `/document-deliveries/${id}`,
    method: 'get'
  })
}

// 提交快递交付
export function submitExpressDelivery(id, data) {
  return request({
    url: `/document-deliveries/${id}/submit-express`,
    method: 'post',
    data
  })
}

// 提交电子交付
export function submitElectronicDelivery(id, data) {
  return request({
    url: `/document-deliveries/${id}/submit-electronic`,
    method: 'post',
    data
  })
}

// 标记为完成
export function markDeliveryAsCompleted(id) {
  return request({
    url: `/document-deliveries/${id}/mark-completed`,
    method: 'post'
  })
}

// 上传附件
export function uploadDeliveryAttachment(id, file) {
  const formData = new FormData()
  formData.append('file', file)
  
  return request({
    url: `/document-deliveries/${id}/attachments`,
    method: 'post',
    data: formData,
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

// 删除附件
export function deleteDeliveryAttachment(deliveryId, attachmentId) {
  return request({
    url: `/document-deliveries/${deliveryId}/attachments/${attachmentId}`,
    method: 'delete'
  })
}

