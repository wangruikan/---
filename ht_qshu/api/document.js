import request from '@/utils/request.js'

/**
 * 获取我的资料上传列表（含配置和上传状态，支持多文件）
 */
export function getMyDocuments() {
	return request.get('/my-documents')
}

/**
 * 上传资料（支持多文件，每次上传一个）
 */
export function uploadDocument(configId, filePath) {
	return request.upload('/documents/upload', filePath, {
		document_config_id: configId
	})
}

/**
 * 删除资料文件
 */
export function deleteDocument(documentId) {
	return request.delete(`/documents/${documentId}`)
}

