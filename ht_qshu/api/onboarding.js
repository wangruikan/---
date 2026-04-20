import request from '@/utils/request.js'

/**
 * 获取我的入职登记表
 */
export function getMyOnboardingForm() {
	return request.get('/onboarding-form')
}

/**
 * 提交入职登记表
 */
export function submitOnboardingForm(data) {
	return request.post('/onboarding-form', data)
}

/**
 * 上传签名图片
 */
export function uploadSignature(signature) {
	return request.post('/upload-signature', { signature })
}

