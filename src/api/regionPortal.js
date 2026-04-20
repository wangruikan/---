import request from './request'

// 获取地区网页入口列表
export function getRegionPortals(params) {
  return request({
    url: '/region-portals',
    method: 'get',
    params
  })
}

// 创建地区网页入口
export function createRegionPortal(data) {
  return request({
    url: '/region-portals',
    method: 'post',
    data
  })
}

// 更新地区网页入口
export function updateRegionPortal(id, data) {
  return request({
    url: `/region-portals/${id}`,
    method: 'put',
    data
  })
}

// 删除地区网页入口
export function deleteRegionPortal(id) {
  return request({
    url: `/region-portals/${id}`,
    method: 'delete'
  })
}

// 切换启用/禁用状态
export function togglePortalStatus(id) {
  return request({
    url: `/region-portals/${id}/toggle-status`,
    method: 'post'
  })
}

