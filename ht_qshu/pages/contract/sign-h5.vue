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
				title: '\u53c2\u6570\u9519\u8bef',
				icon: 'none'
			})
			setTimeout(() => {
				uni.navigateBack()
			}, 1500)
			return
		}
		
		// Load contract detail and log placeholder positions
		await this.loadAndLogSignaturePositions(contractId)
		
		// Build H5 URL and add cache-bust version
		const serverUrl = BASE_URL.replace('/api/mini', '')
		this.h5Url = `${serverUrl}/h5-sign/index.html?contractId=${encodeURIComponent(contractId)}&token=${encodeURIComponent(token)}&h5_v=20260522_prev_company_font_final7`
		console.log('H5 sign page URL:', this.h5Url)
	},
	
	methods: {
		async loadAndLogSignaturePositions(contractId) {
			try {
				const res = await getContractDetail(contractId)
				
				console.log('========== H5 sign page signature positions ==========', contractId)
				
				if (res && res.success) {
					const contract = res.data.contract
					const positions = contract?.signature_positions || []
					
					console.log('signature_positions:', positions)
					console.log('positions_count:', positions.length)
					
					if (positions.length > 0) {
						positions.forEach((pos, i) => {
							console.log(`position_${i + 1}: page=${pos.page}, x=${pos.x}, y=${pos.y}, width=${pos.width}, height=${pos.height}`)
						})
					} else {
						console.warn('No preset signature position')
					}
				} else {
					console.log('Load contract failed:', res)
				}
				console.log('==========================================')
			} catch (e) {
				console.error('Load contract error:', e)
			}
		}
	}
}
</script>

<style>
/* web-view fullscreen */
</style>
