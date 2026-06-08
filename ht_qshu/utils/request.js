// API请求封装
// 本地开发地址
// const BASE_URL = 'http://127.0.0.1:8000/api/mini'   //本地开发
const BASE_URL = 'https://renli.cyygg.cn/api/mini'   //人事域名（生产环境）
// const BASE_URL = 'https://xjz.cyygkj.cn/api/mini' //包小筑域名

// 导出 BASE_URL 供其他模块使用
export { BASE_URL }

console.log('API地址:', BASE_URL)

// 请求拦截器
function request(options) {
	return new Promise((resolve, reject) => {
		// 获取token
		const token = uni.getStorageSync('token')
		
		// 构建请求头
		const header = {
			'Content-Type': 'application/json',
			'Accept': 'application/json'
		}
		
		// 添加token（同时添加Authorization和X-Auth-Token，确保云端服务器能正确接收）
		if (token) {
			header['Authorization'] = `Bearer ${token}`
			header['X-Auth-Token'] = token
		}
		
		// 构建完整URL（添加token参数作为备选方案）
		let fullUrl = BASE_URL + options.url
		if (token && options.method === 'GET') {
			const separator = fullUrl.includes('?') ? '&' : '?'
			fullUrl += separator + 'token=' + encodeURIComponent(token)
		}
		
		// 发起请求
		uni.request({
			url: fullUrl,
			method: options.method || 'GET',
			data: options.data || {},
			header: header,
			success: (res) => {
				// 响应成功
				if (res.statusCode === 200) {
					resolve(res.data)
				} else if (res.statusCode === 401) {
					// 未授权，清除token并跳转登录
					uni.removeStorageSync('token')
					uni.removeStorageSync('employeeInfo')
					uni.showToast({
						title: '登录已过期，请重新登录',
						icon: 'none'
					})
					setTimeout(() => {
						uni.reLaunch({
							url: '/pages/login/login'
						})
					}, 1500)
					reject(res.data)
				} else if (res.statusCode === 422) {
					// 验证失败
					const message = res.data.message || '数据验证失败'
					uni.showToast({
						title: message,
						icon: 'none'
					})
					reject(res.data)
				} else {
					// 其他错误
					const message = res.data.message || '请求失败'
					uni.showToast({
						title: message,
						icon: 'none'
					})
					reject(res.data)
				}
			},
			fail: (err) => {
				console.error('请求失败:', err)
				uni.showToast({
					title: '网络错误，请检查网络连接',
					icon: 'none'
				})
				reject(err)
			}
		})
	})
}

export default {
	get(url, data) {
		return request({
			url,
			method: 'GET',
			data
		})
	},
	
	post(url, data) {
		return request({
			url,
			method: 'POST',
			data
		})
	},
	
	put(url, data) {
		return request({
			url,
			method: 'PUT',
			data
		})
	},
	
	delete(url, data) {
		return request({
			url,
			method: 'DELETE',
			data
		})
	},
	
	// 文件上传方法
	upload(url, filePath, formData = {}) {
		return new Promise((resolve, reject) => {
			// 获取token
			const token = uni.getStorageSync('token')
			
			// 构建请求头（同时添加Authorization和X-Auth-Token，确保云端服务器能正确接收）
			const header = {}
			if (token) {
				header['Authorization'] = `Bearer ${token}`
				header['X-Auth-Token'] = token
			}
			
			// 发起上传请求
			uni.uploadFile({
				url: BASE_URL + url,
				filePath: filePath,
				name: 'file',
				formData: formData,
				header: header,
				success: (res) => {
					try {
						const data = JSON.parse(res.data)
						if (res.statusCode === 200) {
							resolve(data)
						} else if (res.statusCode === 401) {
							// 未授权
							uni.removeStorageSync('token')
							uni.removeStorageSync('employeeInfo')
							uni.showToast({
								title: '登录已过期，请重新登录',
								icon: 'none'
							})
							setTimeout(() => {
								uni.reLaunch({
									url: '/pages/login/login'
								})
							}, 1500)
							reject(data)
						} else {
							const message = data.message || '上传失败'
							uni.showToast({
								title: message,
								icon: 'none'
							})
							reject(data)
						}
					} catch (e) {
						console.error('解析上传响应失败:', e)
						reject({ message: '上传失败' })
					}
				},
				fail: (err) => {
					console.error('上传失败:', err)
					uni.showToast({
						title: '上传失败，请重试',
						icon: 'none'
					})
					reject(err)
				}
			})
		})
	}
}

