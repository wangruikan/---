import request from './request'

// 保险种类管理
export function getInsuranceTypes(params) {
  return request({
    url: '/other-insurance/types',
    method: 'get',
    params
  })
}

export function createInsuranceType(data) {
  return request({
    url: '/other-insurance/types',
    method: 'post',
    data
  })
}

export function updateInsuranceType(id, data) {
  return request({
    url: `/other-insurance/types/${id}`,
    method: 'put',
    data
  })
}

export function deleteInsuranceType(id, params) {
  return request({
    url: `/other-insurance/types/${id}`,
    method: 'delete',
    params
  })
}

// 保单管理
export function getPolicies(typeId, params) {
  return request({
    url: `/other-insurance/types/${typeId}/policies`,
    method: 'get',
    params
  })
}

export function createPolicy(typeId, data) {
  return request({
    url: `/other-insurance/types/${typeId}/policies`,
    method: 'post',
    data
  })
}

export function updatePolicy(id, data) {
  return request({
    url: `/other-insurance/policies/${id}`,
    method: 'put',
    data
  })
}

export function deletePolicy(id, params) {
  return request({
    url: `/other-insurance/policies/${id}`,
    method: 'delete',
    params
  })
}

