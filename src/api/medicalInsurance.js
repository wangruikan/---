import request from './request'

// 获取医保地区列表
export function getMedicalInsuranceRegions(params) {
  return request({
    url: '/medical-insurance',
    method: 'get',
    params
  })
}

// 获取单个医保地区详情
export function getMedicalInsuranceRegion(id) {
  return request({
    url: `/medical-insurance/${id}`,
    method: 'get'
  })
}

// 创建医保地区
export function createMedicalInsuranceRegion(data) {
  return request({
    url: '/medical-insurance',
    method: 'post',
    data
  })
}

// 更新医保地区
export function updateMedicalInsuranceRegion(id, data) {
  return request({
    url: `/medical-insurance/${id}`,
    method: 'put',
    data
  })
}

// 删除医保地区
export function deleteMedicalInsuranceRegion(id, params) {
  return request({
    url: `/medical-insurance/${id}`,
    method: 'delete',
    params
  })
}

// 添加医保类型
export function addMedicalInsuranceType(regionId, data) {
  return request({
    url: `/medical-insurance/${regionId}/types`,
    method: 'post',
    data
  })
}

// 更新医保类型
export function updateMedicalInsuranceType(typeId, data) {
  return request({
    url: `/medical-insurance/types/${typeId}`,
    method: 'put',
    data
  })
}

// 删除医保类型
export function deleteMedicalInsuranceType(typeId, params) {
  return request({
    url: `/medical-insurance/types/${typeId}`,
    method: 'delete',
    params
  })
}

// 获取医保地区上下限历史
export function getMedicalInsuranceRegionLimitHistories(id, params) {
  return request({
    url: `/medical-insurance/${id}/limit-histories`,
    method: 'get',
    params
  })
}

