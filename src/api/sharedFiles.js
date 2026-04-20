import request from './request'

// 获取共享文件列表
export function getSharedFiles(params) {
  return request({
    url: '/shared-files',
    method: 'get',
    params
  })
}

// 上传文件
export function uploadFile(formData) {
  return request({
    url: '/shared-files',
    method: 'post',
    data: formData,
    headers: {
      'Content-Type': undefined // 让浏览器自动设置正确的 boundary
    }
  })
}

// 获取文件详情
export function getSharedFile(id) {
  return request({
    url: `/shared-files/${id}`,
    method: 'get'
  })
}

// 更新文件信息
export function updateSharedFile(id, data) {
  return request({
    url: `/shared-files/${id}`,
    method: 'put',
    data
  })
}

// 删除文件
export function deleteSharedFile(id) {
  return request({
    url: `/shared-files/${id}`,
    method: 'delete'
  })
}

// 下载文件
export function downloadFile(id) {
  return request({
    url: `/shared-files/${id}/download`,
    method: 'get',
    responseType: 'blob'
  })
}

