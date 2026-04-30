<template>
	<view class="container">
		<view v-if="loading" class="loading">
			<text>加载中...</text>
		</view>
		
		<view v-else-if="contract" class="contract-detail">
			<!-- 合同信息 -->
			<view class="info-section">
				<view class="info-header">
					<text class="contract-type">{{ getContractTypeText(contract.contract_type, contract) }}</text>
					<view :class="['status-tag', getStatusClass(contract.status)]">
						<text>{{ getStatusText(contract.status) }}</text>
					</view>
				</view>
				
				<view class="info-item">
					<text class="label">文件名：</text>
					<text class="value">{{ contract.original_filename }}</text>
				</view>
				<view class="info-item">
					<text class="label">上传人：</text>
					<text class="value">{{ contract.creator?.name || '系统' }}</text>
				</view>
				<view class="info-item">
					<text class="label">上传时间：</text>
					<text class="value">{{ formatDateTime(contract.uploaded_at) }}</text>
				</view>
			</view>
			
			<!-- 合同预览图片 -->
			<view v-if="contract.status === 'pending_sign' && previewImages.length > 0" class="preview-section">
				<view class="section-title">
					<text>📄 合同内容</text>
				</view>
				
				<view class="preview-images">
					<view 
						v-for="(imgUrl, index) in previewImages" 
						:key="index" 
						class="page-wrapper"
					>
						<text class="page-number">第 {{ index + 1 }} 页</text>
						<image 
							:src="imgUrl" 
							mode="widthFix"
							class="preview-image"
						></image>
					</view>
				</view>
				
				<!-- 签名按钮区域 -->
				<view class="sign-action-section">
					<view class="sign-tip-new">
						<text>✍️ 请仔细阅读合同内容，确认无误后点击下方按钮签署</text>
					</view>
					<button class="sign-btn-primary" @click="handleDirectSign">
						立即签署合同
					</button>
				</view>
			</view>
			
			<!-- 降级方案：PDF预览 + 签名（当PDF转图片失败时） -->
			<view v-else-if="contract.status === 'pending_sign'" class="fallback-section">
				<view class="section-title">
					<text>📄 合同文件</text>
				</view>
				<button class="preview-btn" @click="handlePreviewPDF">
					点击预览PDF文档
				</button>
				
				<view class="sign-tip-fallback">
					<text>📝 预览合同后，点击下方按钮签署</text>
				</view>
				
				<button class="sign-btn-fallback" @click="handleDirectSign">
					立即签署合同
				</button>
			</view>
			
			<!-- 普通PDF预览（已签署状态） -->
			<view v-else class="file-section">
				<view class="section-title">
					<text>📄 合同文件</text>
				</view>
				<button class="preview-btn" @click="handlePreviewPDF">
					点击预览PDF文档
				</button>
			</view>
			
			<!-- 签名弹窗 -->
			<view v-if="showSignPopup" class="sign-mask" @click="closeSignPopup">
				<view class="sign-popup" @click.stop>
					<view class="popup-header">
						<text class="popup-title">请签署您的姓名</text>
						<button class="close-btn" @click="closeSignPopup">×</button>
					</view>
					
					<view class="canvas-wrapper">
						<canvas 
							canvas-id="signCanvas" 
							class="sign-canvas"
							@touchstart="touchStart"
							@touchmove="touchMove"
							@touchend="touchEnd"
						></canvas>
					</view>
					
					<view class="popup-actions">
						<button class="clear-btn" @click="clearSign">清空重签</button>
						<button class="confirm-btn" @click="confirmSign">确认签名</button>
					</view>
				</view>
			</view>
			
			<!-- 操作按钮 -->
			<view class="action-section">
				<view v-if="contract.status === 'employee_signed'" class="signed-info">
					<text class="signed-text">✅ 您已于 {{ formatDateTime(contract.employee_signed_at) }} 签署此合同</text>
					<text class="wait-text">等待HR确认完成</text>
				</view>
				
				<view v-else-if="contract.status === 'completed'" class="completed-info">
					<text class="completed-text">✅ 合同已完成</text>
					<text class="time-text">完成时间：{{ formatDateTime(contract.completed_at) }}</text>
				</view>
				
				<view v-else-if="contract.status === 'pending_sign'" class="pending-actions">
					<button class="reject-btn" @click="handleReject">拒绝合同</button>
				</view>
			</view>
		</view>
	</view>
</template>

<script>
import { getContractDetail, signContract, rejectContract } from '@/api/contract.js'

export default {
	data() {
		return {
			contractId: null,
			contract: null,
			loading: false,
			previewImages: [], // PDF转换后的图片数组
			
			// 签名相关
			showSignPopup: false, // 是否显示签名弹窗
			ctx: null,
			canvasWidth: 0,
			canvasHeight: 0,
			lastPoint: null,
			hasSigned: false
		}
	},
	
	onLoad(options) {
		if (options.id) {
			this.contractId = options.id
			this.loadContractDetail()
		}
	},
	
	methods: {
		// 加载合同详情
		async loadContractDetail() {
			this.loading = true
			try {
				const res = await getContractDetail(this.contractId)
				console.log('合同详情:', res.data.contract)
				
				if (res.success) {
					this.contract = res.data.contract
					
					// 如果有预览图片，加载它们
					if (res.data.preview_images && res.data.preview_images.length > 0) {
						this.previewImages = res.data.preview_images
						console.log('PDF预览图片:', this.previewImages)
					}
				}
			} catch (error) {
				console.error('加载合同详情失败:', error)
				uni.showToast({
					title: '加载失败',
					icon: 'none'
				})
			} finally {
				this.loading = false
			}
		},
		
		// 跳转到H5签署页（统一走可上传须知副本的签署链路）
			handleDirectSign() {
				uni.navigateTo({
					url: `/pages/contract/sign-h5?id=${this.contractId}`
				})
			},
			
			// 初始化Canvas
		initCanvas() {
			const query = uni.createSelectorQuery().in(this)
			query.select('.sign-canvas').boundingClientRect(data => {
				if (!data) {
					console.error('Canvas元素未找到')
					return
				}
				
				this.canvasWidth = data.width
				this.canvasHeight = data.height
				
				console.log('Canvas尺寸:', this.canvasWidth, 'x', this.canvasHeight)
				
				this.ctx = uni.createCanvasContext('signCanvas', this)
				this.ctx.setStrokeStyle('#000')
				this.ctx.setLineWidth(3)
				this.ctx.setLineCap('round')
				this.ctx.setLineJoin('round')
				
				// 绘制白色背景
				this.ctx.setFillStyle('#ffffff')
				this.ctx.fillRect(0, 0, this.canvasWidth, this.canvasHeight)
				this.ctx.draw()
				
				console.log('Canvas初始化成功')
			}).exec()
		},
		
		// 开始触摸
		touchStart(e) {
			if (!this.ctx) return
			
			const touch = e.touches[0]
			this.lastPoint = {
				x: touch.x,
				y: touch.y
			}
			this.hasSigned = true
		},
		
		// 移动触摸
		touchMove(e) {
			if (!this.ctx || !this.lastPoint) return
			
			const touch = e.touches[0]
			const currentPoint = {
				x: touch.x,
				y: touch.y
			}
			
			this.ctx.beginPath()
			this.ctx.moveTo(this.lastPoint.x, this.lastPoint.y)
			this.ctx.lineTo(currentPoint.x, currentPoint.y)
			this.ctx.stroke()
			this.ctx.draw(true)
			
			this.lastPoint = currentPoint
		},
		
		// 结束触摸
		touchEnd() {
			this.lastPoint = null
		},
		
		// 清空签名
		clearSign() {
			if (!this.ctx) return
			
			this.ctx.setFillStyle('#ffffff')
			this.ctx.fillRect(0, 0, this.canvasWidth, this.canvasHeight)
			this.ctx.draw()
			this.hasSigned = false
		},
		
		// 确认签名
		confirmSign() {
			if (!this.hasSigned) {
				uni.showToast({
					title: '请先签署您的姓名',
					icon: 'none'
				})
				return
			}
			
			// 弹出身份验证对话框
			uni.showModal({
				title: '身份验证',
				content: '请输入您的身份证后4位以确认签署',
				editable: true,
				placeholderText: '请输入身份证后4位',
				success: (res) => {
					if (res.confirm) {
						const normalized = String(res.content || '').replace(/\s|　/g, '')
						if (!/^\d{4}$/.test(normalized)) {
							uni.showToast({
								title: '请输入4位数字',
								icon: 'none'
							})
							return
						}
						this.getSignatureImage(normalized)
					}
				}
			})
		},
		
		// 获取签名图片
		async getSignatureImage(idLast4) {
			uni.showLoading({ title: '处理签名...' })
			
			uni.canvasToTempFilePath({
				canvasId: 'signCanvas',
				success: (res) => {
					console.log('签名图片路径:', res.tempFilePath)
					this.convertImageToBase64(res.tempFilePath, idLast4)
				},
				fail: (err) => {
					console.error('生成签名图片失败:', err)
					uni.hideLoading()
					uni.showToast({
						title: '签名处理失败',
						icon: 'none'
					})
				}
			}, this)
		},
		
		// 将图片转为base64
		convertImageToBase64(filePath, idLast4) {
			const fs = uni.getFileSystemManager()
			fs.readFile({
				filePath: filePath,
				encoding: 'base64',
				success: (res) => {
					const base64 = 'data:image/png;base64,' + res.data
					console.log('Base64签名生成成功')
					this.submitSign(idLast4, base64)
				},
				fail: (err) => {
					console.error('转换base64失败:', err)
					uni.hideLoading()
					uni.showToast({
						title: '签名处理失败',
						icon: 'none'
					})
				}
			})
		},
		
		// 提交签署（签名位置由后端根据预设位置处理）
		async submitSign(idLast4, signatureBase64) {
			uni.showLoading({ title: '提交签署中...' })
			
			try {
				console.log('提交签名数据（位置由后端预设决定）')
				
				// 不再传递位置参数，签名位置由后端从合同模板的预设位置获取
				const res = await signContract(this.contractId, {
					id_last_4: idLast4,
					signature_image: signatureBase64
					// 签名位置不再由前端传递，后端会从 signature_positions 字段获取预设位置
				})
				
				if (res.success) {
					uni.hideLoading()
					uni.showToast({
						title: '签署成功',
						icon: 'success',
						duration: 2000
					})
					
					// 关闭签名弹窗
					this.showSignPopup = false
					
					setTimeout(() => {
						uni.navigateBack()
					}, 2000)
				}
			} catch (error) {
				console.error('签署失败:', error)
				uni.hideLoading()
				uni.showToast({
					title: error.data?.message || '签署失败',
					icon: 'none'
				})
			}
		},
		
		
		// 关闭签名弹窗
		closeSignPopup() {
			this.showSignPopup = false
		},
		
		// 拒绝合同
		handleReject() {
			uni.showModal({
				title: '拒绝合同',
				content: '请输入拒绝原因',
				editable: true,
				placeholderText: '请输入拒绝原因',
				success: async (res) => {
					if (res.confirm && res.content) {
						await this.submitReject(res.content)
					}
				}
			})
		},
		
		// 提交拒绝
		async submitReject(reason) {
			uni.showLoading({ title: '提交中...' })
			
			try {
				const res = await rejectContract(this.contractId, {
					reason: reason
				})
				
				if (res.success) {
					uni.showToast({
						title: '已拒绝',
						icon: 'success'
					})
					
					setTimeout(() => {
						uni.navigateBack()
					}, 1500)
				}
			} catch (error) {
				console.error('拒绝失败:', error)
			} finally {
				uni.hideLoading()
			}
		},
		
		// 预览PDF（已签署状态）
		handlePreviewPDF() {
			if (!this.contract || !this.contract.file_url) return
			
			uni.showLoading({ title: '准备预览...' })
			
			uni.downloadFile({
				url: this.contract.file_url,
				success: (res) => {
					if (res.statusCode === 200) {
						uni.openDocument({
							filePath: res.tempFilePath,
							fileType: 'pdf',
							success: () => {
								uni.hideLoading()
							},
							fail: (err) => {
								console.error('打开文档失败:', err)
								uni.hideLoading()
								uni.showToast({
									title: 'PDF预览失败',
									icon: 'none'
								})
							}
						})
					}
				},
				fail: (err) => {
					console.error('下载失败:', err)
					uni.hideLoading()
				}
			})
		},
		
		getContractTypeText(type, contract = null) {
			if (type === 'other') {
				const notes = contract?.notes || ''
				if (notes.includes('须知签名副本') || notes.includes('小程序签署时上传的须知签名副本')) {
					return '须知文件'
				}
			}
			const types = {
				labor: '劳动合同',
				termination: '解除协议合同',
				confidentiality: '保密协议',
				retirement: '退休解除协议合同',
				other: '其他合同'
			}
			return types[type] || type
		},
		
		getStatusText(status) {
			const statuses = {
				draft: '草稿',
				pending_sign: '待签署',
				employee_signed: '已签署',
				completed: '已完成',
				rejected: '已拒绝'
			}
			return statuses[status] || status
		},
		
		getStatusClass(status) {
			return `status-${status}`
		},
		
		formatDateTime(dateTimeStr) {
			if (!dateTimeStr) return '-'
			const date = new Date(dateTimeStr)
			const y = date.getFullYear()
			const m = String(date.getMonth() + 1).padStart(2, '0')
			const d = String(date.getDate()).padStart(2, '0')
			const h = String(date.getHours()).padStart(2, '0')
			const min = String(date.getMinutes()).padStart(2, '0')
			return `${y}-${m}-${d} ${h}:${min}`
		}
	}
}
</script>

<style scoped>
.container {
	min-height: 100vh;
	background-color: #f5f5f5;
	padding-bottom: 100px;
}

.loading {
	display: flex;
	justify-content: center;
	align-items: center;
	height: 100vh;
}

.contract-detail {
	padding: 20px;
}

.info-section {
	background-color: #fff;
	border-radius: 12px;
	padding: 20px;
	margin-bottom: 20px;
}

.info-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 20px;
}

.contract-type {
	font-size: 18px;
	font-weight: bold;
	color: #333;
}

.status-tag {
	padding: 6px 12px;
	border-radius: 20px;
	font-size: 14px;
}

.status-pending_sign {
	background-color: #fff3e0;
	color: #ff9800;
}

.status-employee_signed {
	background-color: #e3f2fd;
	color: #2196f3;
}

.status-completed {
	background-color: #e8f5e9;
	color: #4caf50;
}

.info-item {
	display: flex;
	margin-bottom: 12px;
	font-size: 14px;
}

.label {
	color: #999;
	width: 80px;
}

.value {
	color: #333;
	flex: 1;
}

.preview-section {
	background-color: #fff;
	border-radius: 12px;
	padding: 20px;
	margin-bottom: 20px;
}

.section-title {
	font-size: 16px;
	font-weight: bold;
	color: #333;
	margin-bottom: 15px;
}

.preview-images {
	margin-bottom: 20px;
}

.page-wrapper {
	margin-bottom: 20px;
}

.page-number {
	display: block;
	font-size: 14px;
	color: #666;
	margin-bottom: 10px;
	text-align: center;
}

.preview-image {
	width: 100%;
	border-radius: 8px;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.sign-tip {
	background-color: #fff9e6;
	padding: 12px;
	border-radius: 8px;
	text-align: center;
}

.sign-tip text {
	color: #ff9800;
	font-size: 14px;
}

/* 新增：签名按钮区域样式 */
.sign-action-section {
	margin-top: 20px;
	padding-top: 20px;
	border-top: 1px solid #eee;
}

.sign-tip-new {
	background-color: #e8f5e9;
	padding: 15px;
	border-radius: 8px;
	text-align: center;
	margin-bottom: 15px;
}

.sign-tip-new text {
	color: #4caf50;
	font-size: 14px;
}

.sign-btn-primary {
	background: linear-gradient(135deg, #4caf50, #66bb6a);
	color: #fff;
	border: none;
	border-radius: 10px;
	padding: 16px;
	width: 100%;
	font-size: 18px;
	font-weight: bold;
	box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
}

.file-section {
	background-color: #fff;
	border-radius: 12px;
	padding: 20px;
	margin-bottom: 20px;
}

.preview-btn {
	background-color: #2196f3;
	color: #fff;
	border: none;
	border-radius: 8px;
	padding: 15px;
	width: 100%;
	font-size: 16px;
}

.fallback-section {
	background-color: #fff;
	border-radius: 12px;
	padding: 20px;
	margin-bottom: 20px;
}

.sign-tip-fallback {
	background-color: #fff9e6;
	padding: 12px;
	border-radius: 8px;
	text-align: center;
	margin: 15px 0;
}

.sign-tip-fallback text {
	color: #ff9800;
	font-size: 14px;
}

.sign-btn-fallback {
	background-color: #4caf50;
	color: #fff;
	border: none;
	border-radius: 8px;
	padding: 15px;
	width: 100%;
	font-size: 16px;
	font-weight: bold;
}

.sign-mask {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background-color: rgba(0, 0, 0, 0.5);
	display: flex;
	align-items: flex-end;
	z-index: 1000;
}

.sign-popup {
	background-color: #fff;
	border-top-left-radius: 20px;
	border-top-right-radius: 20px;
	padding: 20px;
}

.popup-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 20px;
}

.popup-title {
	font-size: 18px;
	font-weight: bold;
	color: #333;
}

.close-btn {
	background: none;
	border: none;
	font-size: 30px;
	color: #999;
	padding: 0;
	width: 40px;
	height: 40px;
	line-height: 40px;
}

.canvas-wrapper {
	width: 100%;
	height: 200px;
	border: 2px dashed #ddd;
	border-radius: 8px;
	overflow: hidden;
	margin-bottom: 20px;
}

.sign-canvas {
	width: 100%;
	height: 100%;
}

.popup-actions {
	display: flex;
	gap: 15px;
}

.clear-btn, .confirm-btn {
	flex: 1;
	padding: 15px;
	border-radius: 8px;
	border: none;
	font-size: 16px;
}

.clear-btn {
	background-color: #f5f5f5;
	color: #666;
}

.confirm-btn {
	background-color: #4caf50;
	color: #fff;
}

.action-section {
	background-color: #fff;
	border-radius: 12px;
	padding: 20px;
}

.signed-info, .completed-info {
	text-align: center;
}

.signed-text, .completed-text {
	display: block;
	font-size: 16px;
	color: #4caf50;
	margin-bottom: 10px;
}

.wait-text, .time-text {
	display: block;
	font-size: 14px;
	color: #999;
}

.pending-actions {
	display: flex;
	justify-content: center;
}

.reject-btn {
	background-color: #f44336;
	color: #fff;
	border: none;
	border-radius: 8px;
	padding: 15px 40px;
	font-size: 16px;
}
</style>
