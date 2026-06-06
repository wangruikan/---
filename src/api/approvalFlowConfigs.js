import request from './request'

export const getApprovalFlowConfigs = () => {
  return request({
    url: '/approval-flow-configs',
    method: 'get'
  })
}

export const saveApprovalFlowConfig = (data) => {
  return request({
    url: '/approval-flow-configs',
    method: 'post',
    data
  })
}
