import request from './request'

// 登录
export const login = (data) => {
  return request({
    url: '/auth/login',
    method: 'post',
    data
  })
}

// 登出
export const logout = () => {
  return request({
    url: '/auth/logout',
    method: 'post'
  })
}

// 获取用户信息
export const getUserInfo = (currentAccountSetId = null) => {
  const params = {}
  if (currentAccountSetId) {
    params.current_account_set_id = currentAccountSetId
  }
  return request({
    url: '/auth/user',
    method: 'get',
    params
  })
}

// 修改密码
export const changePassword = (data) => {
  return request({
    url: '/auth/change-password',
    method: 'post',
    data
  })
}

// 更新用户资料
export const updateProfile = (data) => {
  return request({
    url: '/auth/profile',
    method: 'put',
    data
  })
}
