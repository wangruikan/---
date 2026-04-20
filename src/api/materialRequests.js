import request from './request'

export const getMaterialRequests = (params) => {
  return request({
    url: '/material-requests',
    method: 'get',
    params
  })
}

export const getMaterialRequestDetail = (id) => {
  return request({
    url: `/material-requests/${id}`,
    method: 'get'
  })
}

export const createMaterialRequest = (data) => {
  return request({
    url: '/material-requests',
    method: 'post',
    data
  })
}

export const returnMaterialRequestMaterials = (id, data) => {
  return request({
    url: `/material-requests/${id}/return`,
    method: 'post',
    data
  })
}

