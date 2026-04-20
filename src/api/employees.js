import request from './request'

// 获取员工列表
export const getEmployees = (params) => {
  return request({
    url: '/employees',
    method: 'get',
    params
  })
}

// 获取员工详情
export const getEmployee = (id) => {
  return request({
    url: `/employees/${id}`,
    method: 'get'
  })
}

// 创建员工
export const createEmployee = (data) => {
  return request({
    url: '/employees',
    method: 'post',
    data
  })
}

// 更新员工
export const updateEmployee = (id, data) => {
  return request({
    url: `/employees/${id}`,
    method: 'put',
    data
  })
}

// 删除员工
export const deleteEmployee = (id) => {
  return request({
    url: `/employees/${id}`,
    method: 'delete'
  })
}

// 获取提醒信息
export const getReminders = () => {
  return request({
    url: '/employees/reminders/list',
    method: 'get'
  })
}

// 生成证明
export const generateCertificate = (data) => {
  return request({
    url: '/employees/certificate/generate',
    method: 'post',
    data
  })
}

// 发送合同短信
export const sendContractSms = (data) => {
  return request({
    url: '/employees/contract/sms',
    method: 'post',
    data
  })
}

// 发起线下入职审批
export const submitOfflineOnboarding = (id, data) => {
  return request({
    url: `/employees/${id}/offline-onboarding`,
    method: 'post',
    data
  })
}

// 获取待上传合同的员工列表
export const getPendingContractUpload = () => {
  return request({
    url: '/employees/pending-contract-upload',
    method: 'get'
  })
}

// 标记合同已上传
export const markContractUploaded = (id) => {
  return request({
    url: `/employees/${id}/mark-contract-uploaded`,
    method: 'post'
  })
}
