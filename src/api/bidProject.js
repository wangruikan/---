import request from './request'

/**
 * 获取投标项目列表
 */
export function getBidProjects(params) {
  return request({
    url: '/bid-projects',
    method: 'get',
    params
  })
}

/**
 * 获取投标项目详情
 */
export function getBidProjectDetail(id) {
  return request({
    url: `/bid-projects/${id}`,
    method: 'get'
  })
}

/**
 * 创建投标项目
 */
export function createBidProject(data) {
  return request({
    url: '/bid-projects',
    method: 'post',
    data
  })
}

/**
 * 更新投标项目
 */
export function updateBidProject(id, data) {
  return request({
    url: `/bid-projects/${id}`,
    method: 'put',
    data
  })
}

/**
 * 删除投标项目
 */
export function deleteBidProject(id) {
  return request({
    url: `/bid-projects/${id}`,
    method: 'delete'
  })
}

/**
 * 更新项目状态
 */
export function updateBidProjectStatus(id, data) {
  return request({
    url: `/bid-projects/${id}/status`,
    method: 'post',
    data
  })
}

/**
 * 设置投标结果
 */
export function setBidResult(id, data) {
  return request({
    url: `/bid-projects/${id}/bid-result`,
    method: 'post',
    data
  })
}

/**
 * 上传文档
 */
export function uploadBidDocument(id, formData) {
  return request({
    url: `/bid-projects/${id}/documents`,
    method: 'post',
    data: formData,
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

/**
 * 删除文档
 */
export function deleteBidDocument(projectId, documentId) {
  return request({
    url: `/bid-projects/${projectId}/documents/${documentId}`,
    method: 'delete'
  })
}

/**
 * 添加进度记录
 */
export function addProgressLog(id, data) {
  return request({
    url: `/bid-projects/${id}/progress-logs`,
    method: 'post',
    data
  })
}

/**
 * 获取统计数据
 */
export function getBidStatistics() {
  return request({
    url: '/bid-projects/statistics',
    method: 'get'
  })
}

/**
 * 获取项目类别列表
 */
export function getBidCategories() {
  return request({
    url: '/bid-projects/categories',
    method: 'get'
  })
}

