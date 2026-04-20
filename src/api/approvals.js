import request from './request'

// 获取审批列表
export function getApprovals(params) {
  return request({
    url: '/approvals',
    method: 'get',
    params
  })
}

// 创建审批
export function createApproval(data) {
  return request({
    url: '/approvals',
    method: 'post',
    data
  })
}

// 获取审批详情
export function getApproval(id) {
  return request({
    url: `/approvals/${id}`,
    method: 'get'
  })
}

// 更新审批
export function updateApproval(id, data) {
  return request({
    url: `/approvals/${id}`,
    method: 'put',
    data
  })
}

// 删除审批
export function deleteApproval(id) {
  return request({
    url: `/approvals/${id}`,
    method: 'delete'
  })
}

// 审批通过
export function approveApproval(id, data) {
  return request({
    url: `/approvals/${id}/approve`,
    method: 'post',
    data
  })
}

// 审批拒绝
export function rejectApproval(id, data) {
  return request({
    url: `/approvals/${id}/reject`,
    method: 'post',
    data
  })
}

// 退回申请
export function returnApproval(id, data) {
  return request({
    url: `/approvals/${id}/return`,
    method: 'post',
    data
  })
}

// 获取待审批列表
export function getPendingApprovals(params) {
  return request({
    url: '/approvals/pending',
    method: 'get',
    params
  })
}

// 重新发起审批（驳回后重新提交）
export function resubmitApproval(data) {
  return request({
    url: '/approvals/resubmit',
    method: 'post',
    data
  })
}
