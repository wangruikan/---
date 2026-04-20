import request from './request'

// 税种类目管理
export function getCategories(params) {
  return request({
    url: '/tax-declarations/categories',
    method: 'get',
    params
  })
}

export function createCategory(data) {
  return request({
    url: '/tax-declarations/categories',
    method: 'post',
    data
  })
}

export function updateCategory(id, data) {
  return request({
    url: `/tax-declarations/categories/${id}`,
    method: 'put',
    data
  })
}

export function deleteCategory(id) {
  return request({
    url: `/tax-declarations/categories/${id}`,
    method: 'delete'
  })
}

// 申报配置管理
export function getConfigs(params) {
  return request({
    url: '/tax-declarations/configs',
    method: 'get',
    params
  })
}

export function createConfig(data) {
  return request({
    url: '/tax-declarations/configs',
    method: 'post',
    data
  })
}

export function updateConfig(id, data) {
  return request({
    url: `/tax-declarations/configs/${id}`,
    method: 'put',
    data
  })
}

export function deleteConfig(id) {
  return request({
    url: `/tax-declarations/configs/${id}`,
    method: 'delete'
  })
}

// 申报任务管理
export function getTasks(params) {
  return request({
    url: '/tax-declarations/tasks',
    method: 'get',
    params
  })
}

export function getTaskDetail(id) {
  return request({
    url: `/tax-declarations/tasks/${id}`,
    method: 'get'
  })
}

export function completeTask(id) {
  return request({
    url: `/tax-declarations/tasks/${id}/complete`,
    method: 'post'
  })
}

// 附件管理
export function uploadAttachment(data) {
  return request({
    url: '/tax-declarations/attachments/upload',
    method: 'post',
    data,
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

export function deleteAttachment(id) {
  return request({
    url: `/tax-declarations/attachments/${id}`,
    method: 'delete'
  })
}
