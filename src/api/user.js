import request from './request'

/**
 * 获取用户列表
 */
export function getUsers(params) {
  return request({
    url: '/users',
    method: 'get',
    params
  })
}

