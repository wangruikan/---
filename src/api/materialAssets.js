import request from './request'

export const getMaterialAssets = (params) => {
  return request({
    url: '/material-assets',
    method: 'get',
    params
  })
}

export const getMaterialAssetDetail = (id) => {
  return request({
    url: `/material-assets/${id}`,
    method: 'get'
  })
}

export const createMaterialAsset = (formData) => {
  return request({
    url: '/material-assets',
    method: 'post',
    data: formData
  })
}

export const updateMaterialAsset = (id, formData) => {
  return request({
    url: `/material-assets/${id}`,
    method: 'put',
    data: formData
  })
}

export const uploadMaterialAssetFile = (id, formData) => {
  return request({
    url: `/material-assets/${id}/files`,
    method: 'post',
    data: formData
  })
}

export const deleteMaterialAssetFile = (id, fileId) => {
  return request({
    url: `/material-assets/${id}/files/${fileId}`,
    method: 'delete'
  })
}

export const deleteMaterialAsset = (id) => {
  return request({
    url: `/material-assets/${id}`,
    method: 'delete'
  })
}

