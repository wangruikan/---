import request from './request'

// 获取考核记录列表
export function getAssessmentRecords(params) {
  return request({
    url: '/assessment-records',
    method: 'get',
    params
  })
}

// 获取考核统计
export function getAssessmentStatistics(params) {
  return request({
    url: '/assessment-records/statistics',
    method: 'get',
    params
  })
}

// 标记为已完成
export function completeAssessmentRecord(id) {
  return request({
    url: `/assessment-records/${id}/complete`,
    method: 'put'
  })
}

// 更新备注
export function updateAssessmentRemark(id, data) {
  return request({
    url: `/assessment-records/${id}/remark`,
    method: 'put',
    data
  })
}

// 删除记录
export function deleteAssessmentRecord(id) {
  return request({
    url: `/assessment-records/${id}`,
    method: 'delete'
  })
}

// 刷新状态
export function refreshAssessmentStatus(data) {
  return request({
    url: '/assessment-records/refresh-status',
    method: 'post',
    data
  })
}

// 手动触发检查
export function triggerCheck(data) {
  return request({
    url: '/assessment-records/trigger-check',
    method: 'post',
    data
  })
}

// 检查新入职员工资料
export function checkNewEmployeeDocuments(data) {
  return request({
    url: '/assessment-records/check-new-employee-documents',
    method: 'post',
    data
  })
}
