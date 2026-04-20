import request from './request'

/**
 * 获取差旅申请列表
 */
export function getTravelApplications(params) {
  return request({
    url: '/travel-applications',
    method: 'get',
    params
  })
}

/**
 * 创建差旅申请
 */
export function createTravelApplication(data) {
  return request({
    url: '/travel-applications',
    method: 'post',
    data
  })
}

/**
 * 获取差旅申请详情
 */
export function getTravelApplicationDetail(id) {
  return request({
    url: `/travel-applications/${id}`,
    method: 'get'
  })
}

/**
 * 上传差旅申请附件
 */
export function uploadTravelApplicationAttachment(formData) {
  return request({
    url: '/travel-applications/upload-attachment',
    method: 'post',
    data: formData,
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

/**
 * 审批差旅申请
 */
export function approveTravelApplication(id, data) {
  return request({
    url: `/travel-applications/${id}/approve`,
    method: 'post',
    data
  })
}

/**
 * 拒绝差旅申请
 */
export function rejectTravelApplication(id, data) {
  return request({
    url: `/travel-applications/${id}/reject`,
    method: 'post',
    data
  })
}

/**
 * 删除差旅申请
 */
export function deleteTravelApplication(id) {
  return request({
    url: `/travel-applications/${id}`,
    method: 'delete'
  })
}

/**
 * 完成差旅申请提交（创建审批流程）
 */
export function completeTravelApplicationSubmission(data) {
  return request({
    url: '/travel-applications/complete-submission',
    method: 'post',
    data
  })
}

