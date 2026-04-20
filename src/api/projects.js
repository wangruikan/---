import request from './request'

// 获取项目列表
export function getProjects(params) {
  return request({
    url: '/projects',
    method: 'get',
    params
  })
}

// 创建项目
export function createProject(data) {
  return request({
    url: '/projects',
    method: 'post',
    data
  })
}

// 获取项目详情
export function getProject(id) {
  return request({
    url: `/projects/${id}`,
    method: 'get'
  })
}

// 更新项目
export function updateProject(id, data) {
  return request({
    url: `/projects/${id}`,
    method: 'put',
    data
  })
}

// 删除项目
export function deleteProject(id) {
  return request({
    url: `/projects/${id}`,
    method: 'delete'
  })
}

// 获取项目统计
export function getProjectStatistics(id) {
  return request({
    url: `/projects/${id}/statistics`,
    method: 'get'
  })
}
