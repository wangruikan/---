import request from './request'

// 考勤表管理
export const getAttendanceSheets = (params) => {
  return request({
    url: '/attendance',
    method: 'get',
    params
  })
}

export const createAttendanceSheet = (data) => {
  return request({
    url: '/attendance',
    method: 'post',
    data
  })
}

export const updateAttendanceSheet = (id, data) => {
  return request({
    url: `/attendance/${id}`,
    method: 'put',
    data
  })
}

export const deleteAttendanceSheet = (id) => {
  return request({
    url: `/attendance/${id}`,
    method: 'delete'
  })
}

export const getAttendanceSheetDetail = (id) => {
  return request({
    url: `/attendance/${id}`,
    method: 'get'
  })
}


export const submitAttendanceSheet = (id, data = null) => {
  return request({
    url: `/attendance/${id}/submit`,
    method: 'post',
    data
  })
}


export const rejectAttendanceSheet = (id) => {
  return request({
    url: `/attendance/${id}/reject`,
    method: 'post'
  })
}

export const saveAttendanceData = (id, data) => {
  return request({
    url: `/attendance/${id}/attendance-data`,
    method: 'post',
    data: {
      attendance_data: data
    }
  })
}

export const getProjectEmployees = (projectId) => {
  return request({
    url: `/attendance/project/${projectId}/employees`,
    method: 'get'
  })
}

// 导出考勤表 (已移动到文件末尾)

// 上传考勤表附件
export const uploadAttendanceFiles = (id, formData) => {
  return request({
    url: `/attendance/${id}/upload-files`,
    method: 'post',
    data: formData,
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

// 考勤记录管理
export const getAttendanceRecords = (params) => {
  return request({
    url: '/attendance/records',
    method: 'get',
    params
  })
}

export const createAttendanceRecord = (data) => {
  return request({
    url: '/attendance/records',
    method: 'post',
    data
  })
}

export const updateAttendanceRecord = (id, data) => {
  return request({
    url: `/attendance/records/${id}`,
    method: 'put',
    data
  })
}

export const deleteAttendanceRecord = (id) => {
  return request({
    url: `/attendance/records/${id}`,
    method: 'delete'
  })
}

// 考勤统计
export const getAttendanceStatistics = (params) => {
  return request({
    url: '/attendance/statistics',
    method: 'get',
    params
  })
}

// 导出功能
export const exportAttendanceSheet = (id, format = 'excel') => {
  return request({
    url: `/attendance/${id}/export`,
    method: 'get',
    params: { format },
    responseType: 'blob'
  })
}

export const batchUpdateAttendance = (data) => {
  return request({
    url: '/attendance/batch-update',
    method: 'post',
    data
  })
}