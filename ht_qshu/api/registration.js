import request from '@/utils/request.js'

/**
 * 获取我的从业人员登记表
 */
export function getMyRegistrationForm() {
	return request.get('/registration-form')
}

/**
 * 提交从业人员登记表
 */
export function submitRegistrationForm(data) {
	return request.post('/registration-form', data)
}
