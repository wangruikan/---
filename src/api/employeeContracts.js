import request from './request'

/**
 * 获取员工的合同列表
 */
export function getEmployeeContracts(employeeId) {
  return request({
    url: `/employees/${employeeId}/contracts`,
    method: 'get'
  })
}

/**
 * 创建合同（支持文件上传和模板创建）
 */
export function uploadContract(data) {
  console.log('uploadContract 接收到的数据:', data)
  
  // 检查是文件上传还是模板创建
  if (data.contract_file) {
    // 文件上传模式
    const formData = new FormData()
    
    formData.append('employee_id', data.employee_id)
    formData.append('contract_type', data.contract_type)
    formData.append('contract_file', data.contract_file)
    if (data.notes) {
      formData.append('notes', data.notes)
    }
    
    // 打印 FormData 内容（调试用）
    console.log('FormData 内容:')
    for (let pair of formData.entries()) {
      console.log(pair[0] + ':', pair[1])
    }

    return request({
      url: '/employees/contracts',
      method: 'post',
      data: formData,
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
  } else {
    // 模板创建模式
    const requestData = {
      employee_id: data.employee_id,
      contract_type: data.contract_type,
      template_id: data.template_id,
      notes: data.notes || ''
    }
    
    console.log('JSON 请求数据:', requestData)

    return request({
      url: '/employees/contracts',
      method: 'post',
      data: requestData,
      headers: {
        'Content-Type': 'application/json'
      }
    })
  }
}

/**
 * 提交合同供员工签署
 */
export function submitContract(id) {
  return request({
    url: `/employees/contracts/${id}/submit`,
    method: 'post'
  })
}

/**
 * 员工签署合同（小程序端）
 */
export function employeeSignContract(id) {
  return request({
    url: `/employees/contracts/${id}/employee-sign`,
    method: 'post'
  })
}

/**
 * 完成合同
 */
export function completeContract(id) {
  return request({
    url: `/employees/contracts/${id}/complete`,
    method: 'post'
  })
}

/**
 * 删除合同
 */
export function deleteContract(id) {
  return request({
    url: `/employees/contracts/${id}`,
    method: 'delete'
  })
}

/**
 * 下载合同
 */
export function downloadContract(id) {
  return `${import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'}/api/employees/contracts/${id}/download`
}

/**
 * 上传已签署合同（线下入职专用）
 */
export function uploadSignedContract(data) {
  const formData = new FormData()
  formData.append('employee_id', data.employee_id)
  formData.append('contract_type', data.contract_type)
  formData.append('contract_file', data.contract_file)
  if (data.notes) {
    formData.append('notes', data.notes)
  }

  return request({
    url: '/employees/contracts/upload-signed',
    method: 'post',
    data: formData,
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

