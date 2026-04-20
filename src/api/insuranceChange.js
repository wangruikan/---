import request from './request'

// 获取参保增减列表
export const getInsuranceChanges = (params) => {
  return request.get('/insurance-changes', { params })
}

// 获取参保明细
export const getInsuranceChangeDetails = (params) => {
  return request.get('/insurance-changes/details', { params })
}

// 获取汇总数据
export const getInsuranceChangeSummaries = (params) => {
  return request.get('/insurance-changes/summaries', { params })
}

// 自动导入员工保险信息
export const autoImportInsurance = (data) => {
  return request.post('/insurance-changes/auto-import', data)
}

// 上传附件
export const uploadAttachment = (id, file) => {
  const formData = new FormData()
  formData.append('attachment', file)
  // 不要手动设置Content-Type，让浏览器自动设置（会包含boundary）
  return request.post(`/insurance-changes/${id}/upload-attachment`, formData)
}

// 处理参保信息
export const processInsuranceChange = (id) => {
  return request.post(`/insurance-changes/${id}/process`)
}

// 生成汇总表
export const generateSummary = (data) => {
  return request.post('/insurance-changes/generate-summary', data)
}

// 导出汇总表
export const exportSummary = (data) => {
  return request.post('/insurance-changes/export-summary', data, {
    responseType: 'blob'
  })
}

// 使用名额
export const useQuota = (id, data) => {
  return request.post(`/insurance-changes/${id}/use-quota`, data)
}

// 更新批单号
export const updateEndorsementNumber = (id, data) => {
  return request.put(`/insurance-changes/${id}/update-endorsement-number`, data)
}

// 更新其他保险费用
export const updateOtherInsuranceCost = (id, data) => {
  return request.put(`/insurance-changes/${id}/update-other-insurance-cost`, data)
}

// 确认处理
export const confirmProcess = (id) => {
  return request.put(`/insurance-changes/${id}/confirm-process`)
}

// 其他保险确认处理
export const confirmOtherInsuranceOnly = (id) => {
  return request.put(`/insurance-changes/${id}/confirm-other-insurance-only`)
}

// 获取社保补交记录列表
export const getSocialSecurityCompensationList = (params) => {
  return request.get('/insurance-changes/social-security-compensation', { params })
}

// 获取公积金补交记录列表
export const getHousingFundCompensationList = (params) => {
  return request.get('/insurance-changes/housing-fund-compensation', { params })
}