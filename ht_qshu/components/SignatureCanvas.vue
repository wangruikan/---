<template>
	<view class="signature-wrapper">
		<text class="label">{{ label }}</text>
		
		<!-- 未签名：显示Canvas -->
		<view v-if="!signatureUrl" class="signature-canvas-box">
			<canvas 
				:canvas-id="canvasId" 
				class="signature-canvas"
				:style="{ width: canvasWidth + 'px', height: canvasHeight + 'px' }"
				@touchstart="handleTouchStart"
				@touchmove="handleTouchMove"
				@touchend="handleTouchEnd"
				disable-scroll="true"
			></canvas>
			<view class="canvas-tips">请在上方空白区域手写签名</view>
			<view class="signature-buttons">
				<button class="clear-signature-btn" @click="clearSignature">清除重写</button>
				<button class="save-signature-btn" @click="finishSignature" v-if="hasSignature">完成签名</button>
			</view>
		</view>
		
		<!-- 已签名：显示图片 -->
		<view v-else class="signature-preview-box">
			<image :src="signatureUrl" mode="aspectFit" class="signature-image"></image>
			<button class="clear-signature-btn" @click="clearSignature">重新签名</button>
		</view>
	</view>
</template>

<script>
import request from '@/utils/request.js'

export default {
	name: 'SignatureCanvas',
	props: {
		label: {
			type: String,
			default: '本人签名：'
		},
		canvasId: {
			type: String,
			default: 'signatureCanvas'
		},
		// 已有的签名URL（用于回显）
		value: {
			type: String,
			default: ''
		}
	},
	
	data() {
		return {
			signatureCtx: null,
			isDrawing: false,
			lastPoint: null,
			hasSignature: false,
			canvasWidth: 0,
			canvasHeight: 200,
			signatureUrl: '',
			signaturePath: ''
		}
	},
	
	watch: {
		value: {
			immediate: true,
			handler(val) {
				if (val) {
					this.signatureUrl = val
					this.hasSignature = true
					// 从URL中提取path（兼容新旧格式）
					if (val.includes('/uploads/signatures/')) {
						const match = val.match(/\/uploads\/signatures\/(.+)$/)
						if (match) {
							this.signaturePath = 'uploads/signatures/' + match[1]
						}
					} else if (val.includes('/storage/signatures/')) {
						const match = val.match(/\/storage\/signatures\/(.+)$/)
						if (match) {
							this.signaturePath = 'signatures/' + match[1]
						}
					} else if (val.includes('/storage/')) {
						const match = val.match(/\/storage\/(.+)$/)
						if (match) {
							this.signaturePath = match[1]
						}
					}
				}
			}
		}
	},
	
	mounted() {
		// 计算Canvas尺寸
		const systemInfo = uni.getSystemInfoSync()
		const screenWidth = systemInfo.windowWidth
		this.canvasWidth = screenWidth - 60
		
		this.$nextTick(() => {
			this.initSignatureCanvas()
		})
	},
	
	methods: {
		// 初始化签名canvas
		initSignatureCanvas() {
			if (!this.signatureCtx) {
				this.signatureCtx = uni.createCanvasContext(this.canvasId, this)
			}
			this.signatureCtx.setStrokeStyle('#000000')
			this.signatureCtx.setLineWidth(3)
			this.signatureCtx.setLineCap('round')
			this.signatureCtx.setLineJoin('round')
		},
		
		// 开始绘制
		handleTouchStart(e) {
			if (!this.signatureCtx) {
				this.initSignatureCanvas()
			}
			this.isDrawing = true
			this.hasSignature = true
			
			const touch = e.touches[0]
			const x = touch.x
			const y = touch.y
			
			this.signatureCtx.beginPath()
			this.signatureCtx.moveTo(x, y)
			this.signatureCtx.lineTo(x, y)
			this.signatureCtx.stroke()
			this.signatureCtx.draw(true)
			
			this.lastPoint = { x, y }
		},
		
		// 绘制中
		handleTouchMove(e) {
			if (!this.isDrawing || !this.lastPoint) return
			
			const touch = e.touches[0]
			const x = touch.x
			const y = touch.y
			
			this.signatureCtx.beginPath()
			this.signatureCtx.moveTo(this.lastPoint.x, this.lastPoint.y)
			this.signatureCtx.lineTo(x, y)
			this.signatureCtx.stroke()
			this.signatureCtx.draw(true)
			
			this.lastPoint = { x, y }
		},
		
		// 结束绘制
		handleTouchEnd() {
			if (!this.isDrawing) return
			this.isDrawing = false
			this.lastPoint = null
		},
		
		// 完成签名
		async finishSignature() {
			if (!this.hasSignature) {
				uni.showToast({ title: '请先签名', icon: 'none' })
				return
			}
			
			uni.showLoading({ title: '上传中...', mask: true })
			
			this.saveSignatureToBase64(async (success, base64Data) => {
				if (!success) {
					uni.hideLoading()
					uni.showToast({ title: '签名生成失败', icon: 'none' })
					return
				}
				
				try {
					const res = await this.uploadSignature(base64Data)
					uni.hideLoading()
					
					if (res.success) {
						this.signatureUrl = res.data.url
						this.signaturePath = res.data.path
						
						// 触发事件，传递签名数据
						this.$emit('input', this.signatureUrl)
						this.$emit('change', {
							url: this.signatureUrl,
							path: this.signaturePath
						})
						
						uni.showToast({ title: '签名已保存', icon: 'success' })
					} else {
						uni.showToast({ title: res.message || '上传失败', icon: 'none' })
					}
				} catch (error) {
					uni.hideLoading()
					console.error('上传签名失败:', error)
					uni.showToast({ title: '上传失败', icon: 'none' })
				}
			})
		},
		
		// 保存签名为base64
		saveSignatureToBase64(callback) {
			uni.canvasToTempFilePath({
				canvasId: this.canvasId,
				fileType: 'png',
				quality: 1,
				width: this.canvasWidth,
				height: this.canvasHeight,
				destWidth: this.canvasWidth * 2,
				destHeight: this.canvasHeight * 2,
				success: (res) => {
					uni.getFileSystemManager().readFile({
						filePath: res.tempFilePath,
						encoding: 'base64',
						success: (data) => {
							const base64Data = 'data:image/png;base64,' + data.data
							if (callback) callback(true, base64Data)
						},
						fail: (error) => {
							console.error('读取签名失败:', error)
							if (callback) callback(false, null)
						}
					})
				},
				fail: (error) => {
					console.error('导出签名失败:', error)
					if (callback) callback(false, null)
				}
			}, this)
		},
		
		// 上传签名到服务器
		uploadSignature(base64Data) {
			return request.post('/upload-signature', { signature: base64Data })
		},
		
		// 清除签名
		clearSignature() {
			this.signatureUrl = ''
			this.signaturePath = ''
			this.isDrawing = false
			this.lastPoint = null
			this.hasSignature = false
			
			this.$emit('input', '')
			this.$emit('change', { url: '', path: '' })
			
			this.$nextTick(() => {
				if (this.signatureCtx) {
					this.signatureCtx.clearRect(0, 0, this.canvasWidth, this.canvasHeight)
					this.signatureCtx.draw()
				}
				this.initSignatureCanvas()
			})
		},
		
		// 获取签名路径（供父组件调用）
		getSignaturePath() {
			return this.signaturePath
		}
	}
}
</script>

<style scoped>
.signature-wrapper {
	padding: 20rpx;
}

.label {
	font-size: 28rpx;
	color: #333;
	margin-bottom: 20rpx;
	display: block;
}

.signature-canvas-box {
	border: 2rpx dashed #ccc;
	border-radius: 8rpx;
	background-color: #fafafa;
	padding: 20rpx;
}

.signature-canvas {
	background-color: #fff;
	border: 1rpx solid #e0e0e0;
	border-radius: 4rpx;
}

.canvas-tips {
	text-align: center;
	font-size: 24rpx;
	color: #999;
	margin-top: 10rpx;
}

.signature-buttons {
	display: flex;
	justify-content: center;
	gap: 20rpx;
	margin-top: 20rpx;
}

.clear-signature-btn {
	background-color: #f5f5f5;
	color: #666;
	font-size: 26rpx;
	padding: 10rpx 30rpx;
	border-radius: 8rpx;
	border: none;
}

.save-signature-btn {
	background-color: #409eff;
	color: #fff;
	font-size: 26rpx;
	padding: 10rpx 30rpx;
	border-radius: 8rpx;
	border: none;
}

.signature-preview-box {
	text-align: center;
}

.signature-image {
	width: 100%;
	height: 200rpx;
	border: 1rpx solid #e0e0e0;
	border-radius: 4rpx;
	background-color: #fff;
}
</style>
