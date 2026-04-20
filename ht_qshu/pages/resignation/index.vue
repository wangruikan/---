<template>
	<view class="container">
		<!-- 页面说明 -->
		<view class="notice-card">
			<text class="notice-icon">ℹ️</text>
			<view class="notice-content">
				<text class="notice-title">温馨提示</text>
				<text class="notice-text">请上传您的离职证明文件，支持 JPG、PNG、PDF 格式，单个文件最大 10MB</text>
			</view>
		</view>
		
		<!-- 上传按钮 -->
		<view class="upload-section">
			<button class="upload-btn" @click="chooseFile" :loading="uploading">
				<text class="upload-icon">📤</text>
				<text>{{ uploading ? '上传中...' : '上传离职证明' }}</text>
			</button>
		</view>
		
		<!-- 文件列表 -->
		<view class="file-list" v-if="fileList.length > 0">
			<view class="list-header">
				<text class="list-title">已上传的文件</text>
				<text class="list-count">共 {{ fileList.length }} 个</text>
			</view>
			
			<view class="file-item" v-for="item in fileList" :key="item.id">
				<view class="file-info">
					<text class="file-icon">{{ getFileIcon(item.file_type) }}</text>
					<view class="file-details">
						<text class="file-name">{{ item.file_name }}</text>
						<view class="file-meta">
							<text class="file-size">{{ formatFileSize(item.file_size) }}</text>
							<text class="file-divider">•</text>
							<text class="file-time">{{ formatTime(item.created_at) }}</text>
						</view>
						<view class="file-source">
							<text class="source-tag" :class="item.upload_source === 'miniprogram' ? 'tag-primary' : 'tag-default'">
								{{ item.upload_source === 'miniprogram' ? '小程序' : 'PC端' }}
							</text>
						</view>
					</view>
				</view>
				<view class="file-actions">
					<button class="action-btn preview-btn" @click="previewFile(item)" size="mini">
						<text class="btn-icon">👁️</text>
						<text>预览</text>
					</button>
					<button class="action-btn delete-btn" @click="deleteFile(item)" size="mini">
						<text class="btn-icon">🗑️</text>
						<text>删除</text>
					</button>
				</view>
			</view>
		</view>
		
		<!-- 空状态 -->
		<view class="empty-state" v-if="fileList.length === 0 && !loading">
			<text class="empty-icon">📄</text>
			<text class="empty-text">暂无离职证明</text>
			<text class="empty-tip">请点击上方按钮上传</text>
		</view>
		
		<!-- 加载状态 -->
		<view class="loading-state" v-if="loading">
			<text class="loading-text">加载中...</text>
		</view>
	</view>
</template>

<script>
import { getMyResignationCertificates, uploadResignationCertificate, deleteResignationCertificate } from '@/api/resignation.js'
import { BASE_URL } from '@/utils/request.js'

export default {
	data() {
		return {
			fileList: [],
			loading: false,
			uploading: false
		}
	},
	
	onLoad() {
		this.loadFileList()
	},
	
	onShow() {
		// 每次显示页面时刷新列表
		this.loadFileList()
	},
	
	// 下拉刷新
	onPullDownRefresh() {
		this.loadFileList().then(() => {
			uni.stopPullDownRefresh()
		})
	},
	
	methods: {
		// 加载文件列表
		async loadFileList() {
			this.loading = true
			try {
				const res = await getMyResignationCertificates()
				if (res.success) {
					this.fileList = res.data || []
				} else {
					uni.showToast({
						title: res.message || '加载失败',
						icon: 'none'
					})
				}
			} catch (error) {
				console.error('加载离职证明列表失败:', error)
				uni.showToast({
					title: '加载失败，请重试',
					icon: 'none'
				})
			} finally {
				this.loading = false
			}
		},
		
		// 选择文件
		chooseFile() {
			uni.chooseImage({
				count: 5,
				sizeType: ['original', 'compressed'],
				sourceType: ['album', 'camera'],
				success: (res) => {
					const tempFilePaths = res.tempFilePaths
					this.uploadFiles(tempFilePaths)
				}
			})
		},
		
		// 上传文件
		async uploadFiles(filePaths) {
			if (filePaths.length === 0) return
			
			this.uploading = true
			uni.showLoading({
				title: '上传中...',
				mask: true
			})
			
			let successCount = 0
			let failCount = 0
			
			for (let i = 0; i < filePaths.length; i++) {
				try {
					const res = await uploadResignationCertificate(filePaths[i])
					if (res.success) {
						successCount++
					} else {
						failCount++
						console.error('上传失败:', res.message)
					}
				} catch (error) {
					failCount++
					console.error('上传失败:', error)
				}
			}
			
			uni.hideLoading()
			this.uploading = false
			
			// 显示上传结果
			if (successCount > 0) {
				uni.showToast({
					title: `成功上传 ${successCount} 个文件`,
					icon: 'success'
				})
				// 重新加载列表
				this.loadFileList()
			}
			
			if (failCount > 0) {
				uni.showToast({
					title: `${failCount} 个文件上传失败`,
					icon: 'none'
				})
			}
		},
		
		// 预览文件
		previewFile(item) {
			const fileType = item.file_type.toLowerCase()
			
			if (fileType.includes('image') || fileType.includes('jpg') || fileType.includes('png') || fileType.includes('jpeg')) {
				// 图片预览
				const imageUrl = this.getFileUrl(item.file_path)
				uni.previewImage({
					urls: [imageUrl],
					current: imageUrl
				})
			} else if (fileType.includes('pdf')) {
				// PDF 预览
				uni.showLoading({
					title: '加载中...',
					mask: true
				})
				
				const fileUrl = this.getFileUrl(item.file_path)
				uni.downloadFile({
					url: fileUrl,
					success: (res) => {
						if (res.statusCode === 200) {
							uni.openDocument({
								filePath: res.tempFilePath,
								fileType: 'pdf',
								success: () => {
									console.log('打开文档成功')
								},
								fail: (err) => {
									console.error('打开文档失败:', err)
									uni.showToast({
										title: '无法打开文件',
										icon: 'none'
									})
								}
							})
						}
					},
					fail: (err) => {
						console.error('下载失败:', err)
						uni.showToast({
							title: '下载失败',
							icon: 'none'
						})
					},
					complete: () => {
						uni.hideLoading()
					}
				})
			} else {
				uni.showToast({
					title: '不支持预览此文件类型',
					icon: 'none'
				})
			}
		},
		
		// 删除文件
		deleteFile(item) {
			uni.showModal({
				title: '提示',
				content: `确定要删除文件 "${item.file_name}" 吗？`,
				success: async (res) => {
					if (res.confirm) {
						uni.showLoading({
							title: '删除中...',
							mask: true
						})
						
						try {
							const result = await deleteResignationCertificate(item.id)
							if (result.success) {
								uni.showToast({
									title: '删除成功',
									icon: 'success'
								})
								// 重新加载列表
								this.loadFileList()
							} else {
								uni.showToast({
									title: result.message || '删除失败',
									icon: 'none'
								})
							}
						} catch (error) {
							console.error('删除失败:', error)
							uni.showToast({
								title: '删除失败，请重试',
								icon: 'none'
							})
						} finally {
							uni.hideLoading()
						}
					}
				}
			})
		},
		
		// 获取文件图标
		getFileIcon(fileType) {
			if (!fileType) return '📄'
			
			const type = fileType.toLowerCase()
			if (type.includes('image') || type.includes('jpg') || type.includes('png') || type.includes('jpeg')) {
				return '🖼️'
			} else if (type.includes('pdf')) {
				return '📕'
			} else {
				return '📄'
			}
		},
		
		// 格式化文件大小
		formatFileSize(bytes) {
			if (!bytes || bytes === 0) return '0 B'
			const k = 1024
			const sizes = ['B', 'KB', 'MB', 'GB']
			const i = Math.floor(Math.log(bytes) / Math.log(k))
			return (bytes / Math.pow(k, i)).toFixed(2) + ' ' + sizes[i]
		},
		
		// 格式化时间
		formatTime(dateString) {
			if (!dateString) return ''
			const date = new Date(dateString)
			const year = date.getFullYear()
			const month = String(date.getMonth() + 1).padStart(2, '0')
			const day = String(date.getDate()).padStart(2, '0')
			const hour = String(date.getHours()).padStart(2, '0')
			const minute = String(date.getMinutes()).padStart(2, '0')
			return `${year}-${month}-${day} ${hour}:${minute}`
		},
		
		// 获取文件URL
		getFileUrl(filePath) {
			// 从 BASE_URL 中提取服务器地址
			const serverUrl = BASE_URL.replace('/api/mini', '')
			return `${serverUrl}/storage/${filePath}`
		}
	}
}
</script>

<style scoped>
.container {
	min-height: 100vh;
	background: #F5F7FA;
	padding: 30rpx;
}

/* 提示卡片 */
.notice-card {
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	border-radius: 16rpx;
	padding: 30rpx;
	display: flex;
	align-items: flex-start;
	margin-bottom: 30rpx;
}

.notice-icon {
	font-size: 40rpx;
	margin-right: 20rpx;
}

.notice-content {
	flex: 1;
}

.notice-title {
	display: block;
	font-size: 32rpx;
	font-weight: bold;
	color: #fff;
	margin-bottom: 10rpx;
}

.notice-text {
	display: block;
	font-size: 26rpx;
	color: rgba(255, 255, 255, 0.9);
	line-height: 1.6;
}

/* 上传区域 */
.upload-section {
	margin-bottom: 30rpx;
}

.upload-btn {
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: #fff;
	border: none;
	border-radius: 16rpx;
	padding: 30rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 32rpx;
	font-weight: bold;
}

.upload-icon {
	font-size: 40rpx;
	margin-right: 15rpx;
}

/* 文件列表 */
.file-list {
	background: #fff;
	border-radius: 16rpx;
	padding: 30rpx;
}

.list-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 30rpx;
	padding-bottom: 20rpx;
	border-bottom: 1px solid #F0F0F0;
}

.list-title {
	font-size: 32rpx;
	font-weight: bold;
	color: #333;
}

.list-count {
	font-size: 26rpx;
	color: #999;
}

.file-item {
	border-bottom: 1px solid #F0F0F0;
	padding: 30rpx 0;
}

.file-item:last-child {
	border-bottom: none;
}

.file-info {
	display: flex;
	align-items: flex-start;
	margin-bottom: 20rpx;
}

.file-icon {
	font-size: 60rpx;
	margin-right: 20rpx;
}

.file-details {
	flex: 1;
}

.file-name {
	display: block;
	font-size: 30rpx;
	color: #333;
	font-weight: 500;
	margin-bottom: 10rpx;
	word-break: break-all;
}

.file-meta {
	display: flex;
	align-items: center;
	font-size: 24rpx;
	color: #999;
	margin-bottom: 10rpx;
}

.file-size {
	margin-right: 10rpx;
}

.file-divider {
	margin: 0 10rpx;
}

.file-time {
	margin-left: 10rpx;
}

.file-source {
	margin-top: 10rpx;
}

.source-tag {
	display: inline-block;
	font-size: 22rpx;
	padding: 6rpx 16rpx;
	border-radius: 20rpx;
}

.tag-primary {
	background: #E6F7FF;
	color: #1890FF;
}

.tag-default {
	background: #F5F5F5;
	color: #999;
}

.file-actions {
	display: flex;
	justify-content: flex-end;
	gap: 20rpx;
}

.action-btn {
	display: flex;
	align-items: center;
	padding: 12rpx 24rpx;
	border-radius: 8rpx;
	font-size: 26rpx;
	border: none;
}

.preview-btn {
	background: #E6F7FF;
	color: #1890FF;
}

.delete-btn {
	background: #FFF1F0;
	color: #FF4D4F;
}

.btn-icon {
	font-size: 28rpx;
	margin-right: 8rpx;
}

/* 空状态 */
.empty-state {
	text-align: center;
	padding: 150rpx 0;
}

.empty-icon {
	display: block;
	font-size: 120rpx;
	margin-bottom: 30rpx;
	opacity: 0.5;
}

.empty-text {
	display: block;
	font-size: 32rpx;
	color: #999;
	margin-bottom: 15rpx;
}

.empty-tip {
	display: block;
	font-size: 26rpx;
	color: #CCC;
}

/* 加载状态 */
.loading-state {
	text-align: center;
	padding: 100rpx 0;
}

.loading-text {
	font-size: 28rpx;
	color: #999;
}
</style>
