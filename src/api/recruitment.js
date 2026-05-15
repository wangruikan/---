import request from './request'

// 获取招聘列表
export function getRecruitments(params) {
  return request({
    url: '/recruitment',
    method: 'get',
    params
  })
}

// 获取招聘权限
export function getRecruitmentPermissions(params) {
  return request({
    url: '/recruitment/permissions',
    method: 'get',
    params
  })
}

// 创建招聘需求
export function createRecruitment(data) {
  return request({
    url: '/recruitment',
    method: 'post',
    data
  })
}

// 更新招聘需求
export function updateRecruitment(id, data) {
  return request({
    url: `/recruitment/${id}`,
    method: 'put',
    data
  })
}

// 删除招聘需求
export function deleteRecruitment(id) {
  return request({
    url: `/recruitment/${id}`,
    method: 'delete'
  })
}

// 分配招聘任务
export function assignRecruitment(id, data) {
  return request({
    url: `/recruitment/${id}/assign`,
    method: 'post',
    data
  })
}

// 更新招聘进度
export function updateProgress(id, data) {
  return request({
    url: `/recruitment/${id}/progress`,
    method: 'post',
    data
  })
}

// 完成招聘
export function completeRecruitment(id, data) {
  return request({
    url: `/recruitment/${id}/complete`,
    method: 'post',
    data
  })
}

// 获取候选人列表
export function getCandidates(recruitmentId) {
  return request({
    url: `/recruitment/${recruitmentId}/candidates`,
    method: 'get'
  })
}

// 添加候选人
export function addCandidate(data) {
  return request({
    url: '/recruitment/candidates',
    method: 'post',
    data
  })
}

// 更新候选人
export function updateCandidate(id, data) {
  return request({
    url: `/recruitment/candidates/${id}`,
    method: 'put',
    data
  })
}

// 删除候选人
export function deleteCandidate(id) {
  return request({
    url: `/recruitment/candidates/${id}`,
    method: 'delete'
  })
}

// 删除候选人简历
export function deleteCandidateResume(id) {
  return request({
    url: `/recruitment/candidates/${id}/resume`,
    method: 'delete'
  })
}