import request from './request'

/**
 * 获取发工资表列表
 */
export const getSalaryPaymentRecords = (params) => {
  return request.get('/salary-payment-records', { params })
}

/**
 * 生成发工资表（工资表审核通过时调用）
 */
export const generateSalaryPaymentRecords = (salaryId) => {
  return request.post('/salary-payment-records/generate', {
    salary_id: salaryId
  })
}

/**
 * 导出发工资表为 Excel
 */
export const exportSalaryPaymentRecords = (params) => {
  return request.post('/salary-payment-records/export', params)
}

/**
 * 更新发工资记录
 */
export const updateSalaryPaymentRecord = (id, data) => {
  return request.put(`/salary-payment-records/${id}`, data)
}

/**
 * 删除发工资记录
 */
export const deleteSalaryPaymentRecord = (id) => {
  return request.delete(`/salary-payment-records/${id}`)
}
