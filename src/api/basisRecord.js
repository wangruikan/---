import request from './request'

/**
 * 获取依据列表
 */
export function getBasisRecords(params) {
  return request({
    url: '/basis-records',
    method: 'get',
    params
  })
}

/**
 * 获取可选择的项目列表
 */
export function getAvailableProjects(params) {
  return request({
    url: '/basis-records/available-projects',
    method: 'get',
    params
  })
}

/**
 * 检查依据是否存在
 */
export function checkBasisExists(data) {
  return request({
    url: '/basis-records/check-exists',
    method: 'post',
    data
  })
}

/**
 * 创建依据
 */
export function createBasisRecord(data) {
  return request({
    url: '/basis-records',
    method: 'post',
    data
  })
}

/**
 * 获取依据详情
 */
export function getBasisRecordDetail(id) {
  return request({
    url: `/basis-records/${id}`,
    method: 'get'
  })
}

/**
 * 更新依据
 */
export function updateBasisRecord(id, data) {
  return request({
    url: `/basis-records/${id}`,
    method: 'put',
    data
  })
}

/**
 * 删除依据
 */
export function deleteBasisRecord(id) {
  return request({
    url: `/basis-records/${id}`,
    method: 'delete'
  })
}

/**
 * 上传附件
 */
export function uploadBasisAttachment(formData) {
  return request({
    url: '/basis-records/upload-attachment',
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
export function deleteBasisAttachment(id) {
  return request({
    url: `/basis-records/attachments/${id}`,
    method: 'delete'
  })
}

