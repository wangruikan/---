import request from './request'

/**
 * 获取我的签名
 */
export function getMySignature() {
  return request({
    url: '/signatures/my',
    method: 'get'
  })
}

/**
 * 上传签名
 */
export function uploadSignature(data) {
  return request({
    url: '/signatures/upload',
    method: 'post',
    data,
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

/**
 * 删除签名
 */
export function deleteSignature() {
  return request({
    url: '/signatures',
    method: 'delete'
  })
}

/**
 * 获取我的印章列表
 */
export function getMySeals() {
  return request({
    url: '/seals/my',
    method: 'get'
  })
}

/**
 * 上传印章
 */
export function uploadSeal(data) {
  return request({
    url: '/seals/upload',
    method: 'post',
    data,
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

/**
 * 设置默认印章
 */
export function setDefaultSeal(id) {
  return request({
    url: `/seals/${id}/set-default`,
    method: 'post'
  })
}

/**
 * 删除印章
 */
export function deleteSeal(id) {
  return request({
    url: `/seals/${id}`,
    method: 'delete'
  })
}



/**
 * 获取我的银行付讫章
 */
export function getMyBankStamp() {
  return request({
    url: '/bank-stamps/my',
    method: 'get'
  })
}

/**
 * 上传银行付讫章
 */
export function uploadBankStamp(data) {
  return request({
    url: '/bank-stamps/upload',
    method: 'post',
    data,
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

/**
 * 更新银行付讫章位置
 */
export function updateBankStampPosition(data) {
  return request({
    url: '/bank-stamps/position',
    method: 'put',
    data
  })
}

/**
 * 删除银行付讫章
 */
export function deleteBankStamp() {
  return request({
    url: '/bank-stamps',
    method: 'delete'
  })
}
