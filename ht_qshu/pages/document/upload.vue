<template>
	<view class="document-upload-page">
		<view class="header">
			<view class="title">资料上传</view>
			<view class="subtitle">请按要求上传以下资料（每个资料可上传多个文件）</view>
		</view>

		<!-- 统计卡片 -->
		<view class="stats-card">
			<view class="stat-item">
				<text class="stat-value">{{ totalCount }}</text>
				<text class="stat-label">总资料数</text>
			</view>
			<view class="stat-divider"></view>
			<view class="stat-item">
				<text class="stat-value uploaded">{{ uploadedCount }}</text>
				<text class="stat-label">已上传</text>
			</view>
			<view class="stat-divider"></view>
			<view class="stat-item">
				<text class="stat-value pending">{{ pendingCount }}</text>
				<text class="stat-label">待上传</text>
			</view>
		</view>

		<!-- 资料列表 -->
		<view class="document-list">
			<view
				v-for="(item, index) in documents"
				:key="item.config_id"
				class="document-item"
				:class="{ 'uploaded': item.uploaded }"
			>
				<view class="item-header">
					<view class="item-title">
						<text class="title-text">{{ item.document_name }}</text>
						<view class="tags">
							<text v-if="item.is_required" class="tag required">必填</text>
							<text v-else class="tag optional">选填</text>
							<text class="tag type">{{ getDocumentTypeText(item.document_type) }}</text>
						</view>
					</view>
					<text class="status" :class="item.uploaded ? 'uploaded' : 'pending'">
						{{ item.uploaded ? `✓ 已上传(${item.file_count || 1})` : '○ 未上传' }}
					</text>
				</view>

				<!-- 已上传文件列表 -->
				<view v-if="item.files && item.files.length > 0" class="files-list">
					<view v-for="(file, fIndex) in item.files" :key="file.id" class="file-item">
						<view class="file-info">
							<text class="file-name">{{ file.original_filename }}</text>
							<text class="file-size">{{ file.file_size_formatted }}</text>
						</view>
						<view class="file-actions">
							<button class="action-btn delete" @click="handleDelete(file, item)">
								<text class="icon">🗑</text>
							</button>
						</view>
					</view>
				</view>

				<!-- 上传按钮（始终显示，支持继续添加文件） -->
				<view class="upload-action">
					<button class="upload-btn" @click="handleUpload(item)">
						<text class="icon">📤</text>
						{{ item.uploaded ? '继续上传' : getUploadButtonText(item.document_type) }}
					</button>
				</view>
			</view>
		</view>

		<!-- 空状态 -->
		<view v-if="!loading && documents.length === 0" class="empty-state">
			<text class="empty-icon">📋</text>
			<text class="empty-text">暂无需要上传的资料</text>
			<text class="empty-hint">请联系管理员在项目中配置资料类型</text>
		</view>

		<!-- 加载中 -->
		<view v-if="loading" class="loading">
			<text>加载中...</text>
		</view>
	</view>
</template>

<script>
import { getMyDocuments, uploadDocument, deleteDocument } from '@/api/document.js'

export default {
	data() {
		return {
			loading: false,
			documents: [],
			employeeInfo: null
		}
	},

	computed: {
		totalCount() {
			return this.documents.length
		},
		uploadedCount() {
			return this.documents.filter(d => d.uploaded).length
		},
		pendingCount() {
			return this.documents.filter(d => !d.uploaded).length
		}
	},

	onLoad() {
		// 获取员工信息
		this.employeeInfo = uni.getStorageSync('employeeInfo')
		if (!this.employeeInfo) {
			uni.showToast({
				title: '请先登录',
				icon: 'none'
			})
			setTimeout(() => {
				uni.reLaunch({
					url: '/pages/login/login'
				})
			}, 1500)
			return
		}

		this.loadDocuments()
	},

	onShow() {
		// 每次显示页面时刷新数据
		if (this.employeeInfo) {
			this.loadDocuments()
		}
	},

	methods: {
		async loadDocuments() {
			this.loading = true
			try {
				const res = await getMyDocuments()
				if (res.success) {
					this.documents = res.data || []
				} else {
					uni.showToast({
						title: res.message || '加载失败',
						icon: 'none'
					})
				}
			} catch (error) {
				console.error('加载资料列表失败:', error)
				uni.showToast({
					title: '加载失败，请重试',
					icon: 'none'
				})
			} finally {
				this.loading = false
			}
		},

		async handleUpload(item) {
			try {
				// 根据文件类型选择不同的上传方式
				let sourceType = ['album', 'camera']
				let extension = []

				if (item.document_type === 'image') {
					sourceType = ['album', 'camera']
					extension = ['jpg', 'jpeg', 'png', 'gif', 'webp']
				} else if (item.document_type === 'pdf') {
					sourceType = ['album']
					extension = ['pdf']
				} else if (item.document_type === 'document') {
					sourceType = ['album']
					extension = ['pdf', 'doc', 'docx', 'xls', 'xlsx']
				} else {
					sourceType = ['album', 'camera']
					extension = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx']
				}

				// 选择文件（支持多选）
				const chooseResult = await this.chooseFiles(sourceType, extension)
				
				if (!chooseResult || chooseResult.length === 0) {
					return
				}

				// 显示上传进度
				uni.showLoading({
					title: `正在上传(0/${chooseResult.length})...`,
					mask: true
				})

				let successCount = 0
				let failCount = 0

				// 逐个上传文件
				for (let i = 0; i < chooseResult.length; i++) {
					uni.showLoading({
						title: `正在上传(${i + 1}/${chooseResult.length})...`,
						mask: true
					})

					try {
						const uploadRes = await uploadDocument(item.config_id, chooseResult[i].tempFilePath)
						if (uploadRes.success) {
							successCount++
						} else {
							failCount++
						}
					} catch (err) {
						failCount++
						console.error('上传失败:', err)
					}
				}

				uni.hideLoading()

				if (successCount > 0) {
					uni.showToast({
						title: `成功上传${successCount}个文件${failCount > 0 ? `，${failCount}个失败` : ''}`,
						icon: successCount === chooseResult.length ? 'success' : 'none'
					})
					// 刷新列表
					this.loadDocuments()
				} else {
					uni.showToast({
						title: '上传失败',
						icon: 'none'
					})
				}
			} catch (error) {
				uni.hideLoading()
				console.error('上传失败:', error)
				uni.showToast({
					title: error.message || '上传失败',
					icon: 'none'
				})
			}
		},

		// 选择多个文件
		chooseFiles(sourceType, extension) {
			return new Promise((resolve, reject) => {
				const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp']
				const isOnlyImage = extension.every(ext => imageExtensions.includes(ext))
				const hasDocument = extension.some(ext => ['pdf', 'doc', 'docx', 'xls', 'xlsx'].includes(ext))
				
				// 如果只允许图片，使用chooseImage（支持多选）
				if (isOnlyImage && !hasDocument) {
					uni.chooseImage({
						count: 9, // 最多选择9张图片
						sourceType: sourceType,
						success: (res) => {
							console.log('选择图片成功:', res)
							resolve(res.tempFilePaths.map(path => ({ tempFilePath: path })))
						},
						fail: (err) => {
							console.error('选择图片失败:', err)
							reject(err)
						}
					})
				} else {
					// 使用chooseMessageFile（支持多选）
					uni.chooseMessageFile({
						count: 9, // 最多选择9个文件
						type: 'file',
						extension: extension,
						success: (res) => {
							console.log('选择文件成功:', res)
							resolve(res.tempFiles.map(file => ({ tempFilePath: file.path })))
						},
						fail: (err) => {
							console.error('选择文件失败:', err)
							// 如果chooseMessageFile失败且包含图片格式，尝试使用chooseImage
							if (extension.some(ext => imageExtensions.includes(ext))) {
								console.log('尝试使用chooseImage...')
								uni.chooseImage({
									count: 9,
									sourceType: sourceType,
									success: (imgRes) => {
										console.log('使用chooseImage成功:', imgRes)
										resolve(imgRes.tempFilePaths.map(path => ({ tempFilePath: path })))
									},
									fail: (imgErr) => {
										console.error('chooseImage也失败:', imgErr)
										uni.showToast({
											title: '选择文件失败，请重试',
											icon: 'none'
										})
										reject(imgErr)
									}
								})
							} else {
								uni.showToast({
									title: '选择文件失败，请重试',
									icon: 'none'
								})
								reject(err)
							}
						}
					})
				}
			})
		},

		async handleDelete(file, item) {
			uni.showModal({
				title: '确认删除',
				content: `确定要删除文件"${file.original_filename}"吗？`,
				success: async (res) => {
					if (res.confirm) {
						uni.showLoading({ title: '删除中...', mask: true })
						try {
							const result = await deleteDocument(file.id)
							uni.hideLoading()
							if (result.success) {
								uni.showToast({ title: '删除成功', icon: 'success' })
								this.loadDocuments()
							} else {
								uni.showToast({ title: result.message || '删除失败', icon: 'none' })
							}
						} catch (error) {
							uni.hideLoading()
							console.error('删除失败:', error)
							uni.showToast({ title: '删除失败', icon: 'none' })
						}
					}
				}
			})
		},

		getDocumentTypeText(type) {
			const texts = { image: '仅图片', pdf: '仅PDF', document: '文档', all: '所有类型' }
			return texts[type] || type
		},

		getUploadButtonText(type) {
			if (type === 'image') return '拍照/选择图片'
			else if (type === 'pdf') return '选择PDF文件'
			else if (type === 'document') return '选择文档'
			else return '拍照/选择文件'
		},

		formatDateTime(dateTime) {
			if (!dateTime) return '-'
			const date = new Date(dateTime)
			const year = date.getFullYear()
			const month = String(date.getMonth() + 1).padStart(2, '0')
			const day = String(date.getDate()).padStart(2, '0')
			const hour = String(date.getHours()).padStart(2, '0')
			const minute = String(date.getMinutes()).padStart(2, '0')
			return `${year}-${month}-${day} ${hour}:${minute}`
		}
	}
}
</script>

<style scoped>
.document-upload-page {
	min-height: 100vh;
	background-color: #f5f5f5;
	padding-bottom: 40rpx;
}

.header {
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	padding: 60rpx 30rpx 40rpx;
	color: white;
}

.title {
	font-size: 48rpx;
	font-weight: bold;
	margin-bottom: 10rpx;
}

.subtitle {
	font-size: 26rpx;
	opacity: 0.9;
}

/* 统计卡片 */
.stats-card {
	margin: -40rpx 30rpx 30rpx;
	background-color: white;
	border-radius: 16rpx;
	padding: 40rpx 20rpx;
	display: flex;
	justify-content: space-around;
	align-items: center;
	box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.08);
}

.stat-item {
	display: flex;
	flex-direction: column;
	align-items: center;
	flex: 1;
}

.stat-value {
	font-size: 52rpx;
	font-weight: bold;
	color: #667eea;
	margin-bottom: 10rpx;
}

.stat-value.uploaded { color: #67c23a; }
.stat-value.pending { color: #e6a23c; }

.stat-label {
	font-size: 24rpx;
	color: #909399;
}

.stat-divider {
	width: 2rpx;
	height: 60rpx;
	background-color: #e4e7ed;
}

/* 资料列表 */
.document-list {
	padding: 0 30rpx;
}

.document-item {
	background-color: white;
	border-radius: 16rpx;
	padding: 30rpx;
	margin-bottom: 20rpx;
	box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.05);
}

.document-item.uploaded {
	border-left: 6rpx solid #67c23a;
}

.item-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	margin-bottom: 20rpx;
}

.item-title { flex: 1; }

.title-text {
	font-size: 32rpx;
	font-weight: 500;
	color: #303133;
	display: block;
	margin-bottom: 10rpx;
}

.tags {
	display: flex;
	gap: 10rpx;
	flex-wrap: wrap;
}

.tag {
	display: inline-block;
	padding: 4rpx 12rpx;
	border-radius: 6rpx;
	font-size: 22rpx;
	color: white;
}

.tag.required { background-color: #f56c6c; }
.tag.optional { background-color: #909399; }
.tag.type { background-color: #409eff; }

.status {
	font-size: 26rpx;
	font-weight: 500;
	white-space: nowrap;
}

.status.uploaded { color: #67c23a; }
.status.pending { color: #e6a23c; }

/* 文件列表 */
.files-list {
	margin-bottom: 20rpx;
}

.file-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 16rpx 20rpx;
	background-color: #f0f9ff;
	border-radius: 12rpx;
	margin-bottom: 12rpx;
}

.file-info {
	flex: 1;
	min-width: 0;
}

.file-name {
	font-size: 26rpx;
	color: #303133;
	display: block;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.file-size {
	font-size: 22rpx;
	color: #909399;
}

.file-actions {
	display: flex;
	gap: 10rpx;
}

.action-btn {
	width: 60rpx;
	height: 60rpx;
	padding: 0;
	margin: 0;
	border: none;
	border-radius: 8rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	background-color: transparent;
}

.action-btn.delete { background-color: #fff1f0; }
.action-btn .icon { font-size: 28rpx; }

/* 上传按钮 */
.upload-action {
	padding: 10rpx 0;
}

.upload-btn {
	width: 100%;
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 10rpx;
	padding: 24rpx;
	border-radius: 12rpx;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: white;
	font-size: 28rpx;
	font-weight: 500;
	border: none;
	box-shadow: 0 4rpx 12rpx rgba(102, 126, 234, 0.4);
}

.upload-btn .icon {
	font-size: 32rpx;
}

/* 空状态 */
.empty-state {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 120rpx 30rpx;
}

.empty-icon {
	font-size: 120rpx;
	margin-bottom: 30rpx;
}

.empty-text {
	font-size: 32rpx;
	color: #909399;
	margin-bottom: 10rpx;
}

.empty-hint {
	font-size: 26rpx;
	color: #c0c4cc;
}

/* 加载中 */
.loading {
	text-align: center;
	padding: 100rpx 0;
	color: #909399;
}
</style>
