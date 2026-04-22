<template>
	<view class="container">
		<!-- 筛选标签 -->
		<view class="filter-tabs">
			<view 
				v-for="tab in tabs" 
				:key="tab.value"
				:class="['tab-item', currentTab === tab.value ? 'tab-active' : '']"
				@click="switchTab(tab.value)"
			>
				<text>{{ tab.label }}</text>
			</view>
		</view>
		
		<!-- 合同列表 -->
		<view class="contract-list">
			<view v-if="contracts.length > 0">
				<view 
					v-for="contract in contracts" 
					:key="contract.id"
					class="contract-item"
					@click="goToDetail(contract.id)"
				>
					<view class="contract-header">
						<view class="contract-type">
							<text class="type-icon">📄</text>
							<text class="type-text">{{ getContractTypeText(contract.contract_type, contract) }}</text>
						</view>
						<view :class="['status-tag', getStatusClass(contract.status)]">
							<text>{{ getStatusText(contract.status) }}</text>
						</view>
					</view>
					
					<view class="contract-info">
						<text class="info-text">文件名：{{ contract.original_filename }}</text>
						<text class="info-text">上传时间：{{ formatTime(contract.uploaded_at) }}</text>
					</view>
					
					<view v-if="contract.status === 'pending_sign'" class="contract-action">
						<text class="action-btn">立即签署 →</text>
					</view>
				</view>
			</view>
			
			<view v-else class="empty-state">
				<text class="empty-icon">📭</text>
				<text class="empty-text">暂无合同记录</text>
			</view>
		</view>
		
		<!-- 须知同意弹窗 -->
		<view v-if="showNoticeModal" class="notice-modal" @touchmove.stop.prevent>
			<view class="modal-mask" @click="closeNoticeModal"></view>
			<view class="modal-content">
				<view class="modal-header">
					<text class="modal-title">签订须知（{{ currentNoticeIndex + 1 }}/{{ noticeFiles.length || 1 }}）</text>
					<text class="close-btn" @click="closeNoticeModal">×</text>
				</view>
				
				<view class="modal-body">
					<view class="notice-text">
						签订劳动合同前，请阅读以下须知：
					</view>
					
					<view class="notice-file">
						<text class="file-icon">📄</text>
						<text class="file-name">{{ noticeFileName }}</text>
					</view>
					
					<button class="read-btn" @click="handleReadNotice">
						📖 阅读文件
					</button>
					
					<view class="agree-section">
						<checkbox-group @change="handleAgreeChange">
							<label class="agree-label">
								<checkbox value="agree" :checked="hasAgreed" color="#667eea" />
								<text>我已知晓并同意上述须知</text>
							</label>
						</checkbox-group>
					</view>
				</view>
				
				<view class="modal-footer">
					<button class="cancel-btn" @click="closeNoticeModal">取消</button>
					<button class="confirm-btn" @click="handleConfirmSign" :disabled="!hasAgreed">
						{{ currentNoticeIndex < noticeFiles.length - 1 ? '确认下一份' : '确认签署' }}
					</button>
				</view>
			</view>
		</view>
	</view>
</template>

<script>
import { getMyContracts, getContractDetail } from '@/api/contract.js'

export default {
	data() {
		return {
			currentTab: '',
			contracts: [],
			tabs: [
				{ label: '全部', value: '' },
				{ label: '待签署', value: 'pending_sign' },
				{ label: '已签署', value: 'employee_signed' },
				{ label: '已完成', value: 'completed' }
			],
			// 须知弹窗相关
			showNoticeModal: false,
			hasAgreed: false,
			noticeFileName: '',
			noticeFileUrl: '',
			noticeFiles: [],
			currentNoticeIndex: 0,
			currentContractId: null
		}
	},

	onShow() {
		this.checkLogin()
		this.loadContracts()
	},

	methods: {
		checkLogin() {
			const employeeInfo = uni.getStorageSync('employeeInfo')
			if (!employeeInfo) {
				uni.reLaunch({
					url: '/pages/login/login'
				})
			}
		},

		switchTab(value) {
			this.currentTab = value
			this.loadContracts()
		},

		async loadContracts() {
			try {
				uni.showLoading({ title: '加载中...' })
				const res = await getMyContracts(this.currentTab)
				if (res.success) {
					this.contracts = res.data
				}
			} catch (error) {
				console.error('加载失败:', error)
			} finally {
				uni.hideLoading()
			}
		},

		async goToDetail(id) {
			const contract = this.contracts.find(c => c.id === id)
			if (contract && contract.status === 'pending_sign') {
				await this.checkNoticeAndSign(id)
			} else {
				uni.navigateTo({
					url: `/pages/contract/detail?id=${id}`
				})
			}
		},

		// 检查须知并签署
		async checkNoticeAndSign(contractId) {
			this.currentContractId = contractId
			this.hasAgreed = false
			this.currentNoticeIndex = 0
			this.noticeFiles = []
			this.noticeFileName = ''
			this.noticeFileUrl = ''
			this.showNoticeModal = false

			try {
				const res = await getContractDetail(contractId)
				if (!res || !res.success || !res.data) {
					this.goToSignPage(contractId)
					return
				}

				const noticeFiles = Array.isArray(res.data.notice_files) && res.data.notice_files.length > 0
					? res.data.notice_files
					: (res.data.notice_file ? [res.data.notice_file] : [])

				if (!noticeFiles.length) {
					this.goToSignPage(contractId)
					return
				}

				this.noticeFiles = noticeFiles
				this.currentNoticeIndex = 0
				this.noticeFileName = noticeFiles[0].name || '劳动合同须知.pdf'
				this.noticeFileUrl = noticeFiles[0].view_url || ''
				this.showNoticeModal = true
			} catch (error) {
				console.error('加载须知文件失败:', error)
				this.goToSignPage(contractId)
			}
		},

		// 阅读须知文件
		handleReadNotice() {
			if (!this.noticeFileUrl) {
				uni.showToast({
					title: '暂无须知文件',
					icon: 'none'
				})
				return
			}

			uni.showLoading({ title: '加载中...' })
			uni.downloadFile({
				url: this.noticeFileUrl,
				success: (res) => {
					uni.hideLoading()
					if (res.statusCode === 200) {
						uni.openDocument({
							filePath: res.tempFilePath,
							fileType: 'pdf',
							showMenu: true,
							success: () => {},
							fail: (err) => {
								console.error('文件打开失败:', err)
								uni.showToast({
									title: '文件打开失败',
									icon: 'error'
								})
							}
						})
					}
				},
				fail: (err) => {
					uni.hideLoading()
					console.error('文件下载失败:', err)
					uni.showToast({
						title: '文件下载失败',
						icon: 'error'
					})
				}
			})
		},

		// 勾选同意
		handleAgreeChange(e) {
			this.hasAgreed = e.detail.value.length > 0
		},

		// 确认签署（需要逐个勾选）
		handleConfirmSign() {
			if (!this.hasAgreed) {
				uni.showToast({
					title: '请先勾选同意',
					icon: 'none'
				})
				return
			}

			if (this.currentNoticeIndex < this.noticeFiles.length - 1) {
				this.currentNoticeIndex += 1
				const current = this.noticeFiles[this.currentNoticeIndex] || {}
				this.noticeFileName = current.name || '劳动合同须知.pdf'
				this.noticeFileUrl = current.view_url || ''
				this.hasAgreed = false
				return
			}

			this.goToSignPage(this.currentContractId)
			this.closeNoticeModal()
		},

		// 关闭须知弹窗
		closeNoticeModal() {
			this.showNoticeModal = false
			this.hasAgreed = false
			this.noticeFileName = ''
			this.noticeFileUrl = ''
			this.noticeFiles = []
			this.currentNoticeIndex = 0
			this.currentContractId = null
		},

		// 进入签署页面
		goToSignPage(contractId) {
			uni.navigateTo({
				url: `/pages/contract/sign-h5?id=${contractId}`
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
			const classes = {
				draft: 'status-draft',
				pending_sign: 'status-pending',
				employee_signed: 'status-signed',
				completed: 'status-completed',
				rejected: 'status-rejected'
			}
			return classes[status] || ''
		},

		formatTime(time) {
			if (!time) return ''
			const date = new Date(time)
			const m = (date.getMonth() + 1).toString().padStart(2, '0')
			const d = date.getDate().toString().padStart(2, '0')
			const h = date.getHours().toString().padStart(2, '0')
			const min = date.getMinutes().toString().padStart(2, '0')
			return `${m}-${d} ${h}:${min}`
		}
	}
}
</script>

<style scoped>
.container {
	min-height: 100vh;
	background: #F5F7FA;
}

.filter-tabs {
	display: flex;
	background: #fff;
	padding: 20rpx;
}

.tab-item {
	flex: 1;
	text-align: center;
	padding: 20rpx 0;
	font-size: 28rpx;
	color: #666;
	border-bottom: 4rpx solid transparent;
}

.tab-active {
	color: #667eea;
	font-weight: bold;
	border-bottom-color: #667eea;
}

.contract-list {
	padding: 20rpx;
}

.contract-item {
	background: #fff;
	border-radius: 16rpx;
	padding: 30rpx;
	margin-bottom: 20rpx;
}

.contract-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 24rpx;
}

.contract-type {
	display: flex;
	align-items: center;
}

.type-icon {
	font-size: 40rpx;
	margin-right: 12rpx;
}

.type-text {
	font-size: 32rpx;
	font-weight: bold;
	color: #333;
}

.status-tag {
	padding: 8rpx 20rpx;
	border-radius: 40rpx;
	font-size: 24rpx;
}

.status-pending {
	background: #FFF3E0;
	color: #FF9800;
}

.status-signed {
	background: #E3F2FD;
	color: #2196F3;
}

.status-completed {
	background: #E8F5E9;
	color: #4CAF50;
}

.status-rejected {
	background: #FFEBEE;
	color: #F44336;
}

.contract-info {
	margin-bottom: 20rpx;
}

.info-text {
	display: block;
	font-size: 26rpx;
	color: #666;
	line-height: 40rpx;
}

.contract-action {
	text-align: right;
}

.action-btn {
	color: #667eea;
	font-size: 28rpx;
	font-weight: bold;
}

.empty-state {
	text-align: center;
	padding: 200rpx 0;
}

.empty-icon {
	display: block;
	font-size: 120rpx;
	margin-bottom: 30rpx;
}

.empty-text {
	font-size: 28rpx;
	color: #999;
}

/* 须知弹窗样式 */
.notice-modal {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	z-index: 9999;
}

.modal-mask {
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(0, 0, 0, 0.6);
}

.modal-content {
	position: absolute;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
	width: 85%;
	max-width: 600rpx;
	background: #fff;
	border-radius: 20rpx;
	overflow: hidden;
}

.modal-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 30rpx;
	border-bottom: 1px solid #eee;
}

.modal-title {
	font-size: 36rpx;
	font-weight: bold;
	color: #333;
}

.close-btn {
	width: 50rpx;
	height: 50rpx;
	line-height: 45rpx;
	text-align: center;
	font-size: 50rpx;
	color: #999;
}

.modal-body {
	padding: 30rpx;
}

.notice-text {
	font-size: 28rpx;
	color: #666;
	margin-bottom: 20rpx;
	line-height: 1.6;
}

.notice-file {
	display: flex;
	align-items: center;
	padding: 20rpx;
	background: #f5f7fa;
	border-radius: 10rpx;
	margin-bottom: 20rpx;
}

.file-icon {
	font-size: 40rpx;
	margin-right: 15rpx;
}

.file-name {
	flex: 1;
	font-size: 28rpx;
	color: #333;
}

.read-btn {
	width: 100%;
	height: 80rpx;
	line-height: 80rpx;
	background: linear-gradient(to right, #667eea, #764ba2);
	color: #fff;
	border-radius: 10rpx;
	font-size: 28rpx;
	margin-bottom: 30rpx;
	border: none;
	padding: 0;
}

.read-btn::after {
	border: none;
}

.agree-section {
	padding: 20rpx 0;
}

.agree-label {
	display: flex;
	align-items: center;
	font-size: 28rpx;
	color: #333;
}

.agree-label checkbox {
	margin-right: 15rpx;
	transform: scale(1.2);
}

.modal-footer {
	display: flex;
	border-top: 1px solid #eee;
}

.cancel-btn,
.confirm-btn {
	flex: 1;
	height: 90rpx;
	line-height: 90rpx;
	text-align: center;
	font-size: 32rpx;
	border: none;
	border-radius: 0;
	margin: 0;
	padding: 0;
}

.cancel-btn {
	background: #fff;
	color: #666;
	border-right: 1px solid #eee;
}

.confirm-btn {
	background: linear-gradient(to right, #667eea, #764ba2);
	color: #fff;
}

.confirm-btn[disabled] {
	background: #ccc;
	color: #999;
}

.confirm-btn::after,
.cancel-btn::after {
	border: none;
}
</style>

