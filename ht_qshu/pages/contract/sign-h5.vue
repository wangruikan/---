<template>
	<web-view :src="h5Url"></web-view>
</template>

<script>
import { BASE_URL } from '@/utils/request.js'
import { getContractDetail } from '@/api/contract.js'

export default {
	data() {
		return {
			h5Url: ''
		}
	},
	
	async onLoad(options) {
		const contractId = options.id
		const token = uni.getStorageSync('token')
		
		if (!contractId || !token) {
			uni.showToast({
				title: '参数错误',
				icon: 'none'
			})
			setTimeout(() => {
				uni.navigateBack()
			}, 1500)
			return
		}
		
		// 获取合同详情并输出签名位置
		await this.loadAndLogSignaturePositions(contractId)
		
		// 构建H5页面URL - 从 BASE_URL 中提取服务器地址
		const serverUrl = BASE_URL.replace('/api/mini', '')
		this.h5Url = `${serverUrl}/h5-sign/index.html?contractId=${contractId}&token=${token}`
		console.log('H5签署页面URL:', this.h5Url)
	},
	
	methods: {
		async loadAndLogSignaturePositions(contractId) {
			try {
				const res = await getContractDetail(contractId)
				
				console.log('========== 签署页面 - 占位符位置 ==========')
				console.log('合同ID:', contractId)
				
				if (res && res.success) {
					const contract = res.data.contract
					const positions = contract?.signature_positions || []
					
					console.log('signature_positions:', positions)
					console.log('位置数量:', positions.length)
					
					if (positions.length > 0) {
						positions.forEach((pos, i) => {
							console.log(`位置${i+1}: 页=${pos.page}, x=${pos.x}, y=${pos.y}, 宽=${pos.width}, 高=${pos.height}`)
						})
					} else {
						console.warn('⚠️ 没有预设签名位置!')
					}
				} else {
					console.log('获取合同失败:', res)
				}
				console.log('==========================================')
			} catch (e) {
				console.error('获取合同出错:', e)
			}
		}
	}
}
</script>

<style>
/* web-view全屏显示 */
</style>
