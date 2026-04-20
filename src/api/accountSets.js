import request from './request'

// 获取套账列表
export function getAccountSets(params) {
  return request({
    url: '/account-sets',
    method: 'get',
    params
  })
}

// 创建套账
export function createAccountSet(data) {
  return request({
    url: '/account-sets',
    method: 'post',
    data
  })
}

// 获取套账详情
export function getAccountSet(id) {
  return request({
    url: `/account-sets/${id}`,
    method: 'get'
  })
}

// 更新套账
export function updateAccountSet(id, data) {
  return request({
    url: `/account-sets/${id}`,
    method: 'put',
    data
  })
}

// 删除套账
export function deleteAccountSet(id) {
  return request({
    url: `/account-sets/${id}`,
    method: 'delete'
  })
}

// 设置为默认套账
export function setDefaultAccountSet(id) {
  return request({
    url: `/account-sets/${id}/set-default`,
    method: 'post'
  })
}

// 归档套账
export function archiveAccountSet(id) {
  return request({
    url: `/account-sets/${id}/archive`,
    method: 'post'
  })
}

// 获取统计信息
export function getAccountSetStatistics() {
  return request({
    url: '/account-sets/statistics',
    method: 'get'
  })
}

// 获取我的账套列表
export function getMyAccountSets() {
  return request({
    url: '/account-sets/my',
    method: 'get'
  })
}

// 分配管理员到账套
export function assignUsers(id, data) {
  return request({
    url: `/account-sets/${id}/assign-users`,
    method: 'post',
    data
  })
}

// 获取账套的管理员列表
export function getAccountSetUsers(id) {
  return request({
    url: `/account-sets/${id}/users`,
    method: 'get'
  })
}

// 移除账套管理员
export function removeAccountSetUser(accountSetId, userId) {
  return request({
    url: `/account-sets/${accountSetId}/users/${userId}`,
    method: 'delete'
  })
}

// 设置用户的默认账套
export function setUserDefaultAccountSet(accountSetId) {
  return request({
    url: '/account-sets/set-user-default',
    method: 'post',
    data: { account_set_id: accountSetId }
  })
}

