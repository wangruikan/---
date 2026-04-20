import request from '@/utils/request.js'

/**
 * 获取我的离职证明列表
 */
export function getMyResignationCertificates() {
	return request.get('/my-resignation-certificates')
}

/**
 * 上传离职证明
 * @param {String} filePath 文件路径
 */
export function uploadResignationCertificate(filePath) {
	return request.upload('/my-resignation-certificates/upload', filePath)
}

/**
 * 删除离职证明
 * @param {Number} id 离职证明ID
 */
export function deleteResignationCertificate(id) {
	return request.delete(`/resignation-certificates/${id}`)
}

/**
 * 下载离职证明
 * @param {Number} id 离职证明ID
 */
export function downloadResignationCertificate(id) {
	return `/resignation-certificates/${id}/download`
}
