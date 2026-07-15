import request from './request'

// get projects
export function getProjects(params) {
  return request({
    url: '/projects',
    method: 'get',
    params
  })
}

// create project
export function createProject(data) {
  return request({
    url: '/projects',
    method: 'post',
    data
  })
}

// preview generated project code
export function getProjectCodePreview(params) {
  return request({
    url: '/projects/generate-code-preview',
    method: 'get',
    params
  })
}

// get project detail
export function getProject(id) {
  return request({
    url: `/projects/${id}`,
    method: 'get'
  })
}

export function getProjectSocialSecurityRegions(id) {
  return request({
    url: `/projects/${id}/social-security-regions`,
    method: 'get'
  })
}

export function getProjectHousingFundRegions(id) {
  return request({
    url: `/projects/${id}/housing-fund-regions`,
    method: 'get'
  })
}

// update project
export function updateProject(id, data) {
  return request({
    url: `/projects/${id}`,
    method: 'put',
    data
  })
}

// terminate project
export function terminateProject(id) {
  return request({
    url: `/projects/${id}/terminate`,
    method: 'put'
  })
}

// delete project
export function deleteProject(id) {
  return request({
    url: `/projects/${id}`,
    method: 'delete'
  })
}

// get project statistics
export function getProjectStatistics(id) {
  return request({
    url: `/projects/${id}/statistics`,
    method: 'get'
  })
}

export function getProjectRoleUsers(id) {
  return request({
    url: `/projects/${id}/role-users`,
    method: 'get'
  })
}

export function saveProjectRoleUsers(id, data) {
  return request({
    url: `/projects/${id}/role-users`,
    method: 'post',
    data
  })
}
