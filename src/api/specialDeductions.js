import request from './request'

// 获取专项扣除项目列表
export function getDeductionItems(params) {
  return request({
    url: '/special-deductions/items',
    method: 'get',
    params
  })
}

// 创建专项扣除项目
export function createDeductionItem(data) {
  return request({
    url: '/special-deductions/items',
    method: 'post',
    data
  })
}

// 更新专项扣除项目
export function updateDeductionItem(id, data) {
  return request({
    url: `/special-deductions/items/${id}`,
    method: 'put',
    data
  })
}

// 删除专项扣除项目
export function deleteDeductionItem(id) {
  return request({
    url: `/special-deductions/items/${id}`,
    method: 'delete'
  })
}

// 获取员工专项扣除列表
export function getEmployeeDeductions(params) {
  return request({
    url: '/special-deductions/employees',
    method: 'get',
    params
  })
}

// 获取项目员工列表
export function getProjectEmployees(projectId, currentAccountSetId) {
  return request({
    url: '/special-deductions/employees/project',
    method: 'get',
    params: { 
      project_id: projectId,
      current_account_set_id: currentAccountSetId
    }
  })
}

// 获取员工专项扣除详情
export function getEmployeeDeductionDetail(employeeId, projectId, currentAccountSetId) {
  return request({
    url: `/special-deductions/employees/${employeeId}/detail`,
    method: 'get',
    params: { 
      project_id: projectId,
      current_account_set_id: currentAccountSetId
    }
  })
}

// 设置员工专项扣除
export function setEmployeeDeduction(data) {
  return request({
    url: '/special-deductions/employees/set',
    method: 'post',
    data
  })
}

// 批量设置员工专项扣除
export function batchSetEmployeeDeduction(data) {
  return request({
    url: '/special-deductions/employees/batch-set',
    method: 'post',
    data
  })
}

// 删除员工专项扣除
export function deleteEmployeeDeduction(id) {
  return request({
    url: `/special-deductions/employees/${id}`,
    method: 'delete'
  })
}

