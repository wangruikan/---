import request from '@/utils/request.js'

// 登录
export function login(data) {
	return request.post('/login', data)
}

// 修改密码
export function changePassword(data) {
	return request.post('/change-password', data)
}

// 获取我的信息
export function getMyInfo() {
	return request.get('/my-info')
}

// 获取待签署合同列表
export function getPendingContracts() {
	return request.get('/pending-contracts')
}

// 获取我的所有合同
export function getMyContracts(status) {
	return request.get('/my-contracts', { status })
}

// 获取合同详情
export function getContractDetail(id) {
	return request.get(`/contracts/${id}`)
}

// 签署合同
export function signContract(id, data) {
	return request.post(`/contracts/${id}/sign`, data)
}

// 拒绝合同
export function rejectContract(id, data) {
	return request.post(`/contracts/${id}/reject`, data)
}

