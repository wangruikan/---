import request from './request'

/**
 * 获取人员变动申请列表
 */
export function getPersonnelChangeRequests(params) {
  return request({
    url: '/personnel-change-requests',
    method: 'get',
    params
  })
}

/**
 * 获取人员变动申请详情
 */
export function getPersonnelChangeRequest(id) {
  return request({
    url: `/personnel-change-requests/${id}`,
    method: 'get'
  })
}

/**
 * 上传附件
 */
export function uploadPersonnelChangeAttachment(formData) {
  return request({
    url: '/personnel-change-requests/upload-attachment',
    method: 'post',
    data: formData,
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

/**
 * 完成提交（创建审批流程）
 */
export function completePersonnelChangeSubmission(data) {
  return request({
    url: '/personnel-change-requests/complete-submission',
    method: 'post',
    data
  })
}

/**
 * 删除人员变动申请
 */
export function deletePersonnelChangeRequest(id) {
  return request({
    url: `/personnel-change-requests/${id}`,
    method: 'delete'
  })
}

