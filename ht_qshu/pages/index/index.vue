<template>
	<view class="container">
		<!-- 欢迎横幅 -->
		<view class="welcome-banner">
			<text class="welcome-title">您好，{{ employeeName }}</text>
			<text class="welcome-subtitle">{{ welcomeText }}</text>
		</view>
		
		<!-- 待办事项 -->
		<view class="todo-section">
			<view class="section-header">
				<text class="section-title">📋 待办事项</text>
			</view>
			
			<view v-if="pendingContracts.length > 0" class="contract-list">
				<view 
					v-for="contract in pendingContracts" 
					:key="contract.id"
					class="contract-item"
					@click="goToDetail(contract.id)"
				>
					<view class="contract-icon">📄</view>
					<view class="contract-info">
						<text class="contract-type">{{ getContractTypeText(contract.contract_type) }}</text>
						<text class="contract-time">上传时间：{{ formatTime(contract.uploaded_at) }}</text>
					</view>
					<view class="contract-action">
						<text class="sign-btn">立即签署</text>
					</view>
				</view>
			</view>
			
			<view v-else class="empty-state">
				<text class="empty-icon">✅</text>
				<text class="empty-text">暂无待签署合同</text>
			</view>
		</view>
		
		<!-- 快捷入口 -->
		<view class="quick-menu">
			<view class="menu-item" @click="goToContracts">
				<text class="menu-icon">📑</text>
				<text class="menu-text">我的合同</text>
			</view>
			<view class="menu-item" @click="goToMy">
				<text class="menu-icon">👤</text>
				<text class="menu-text">个人中心</text>
			</view>
		</view>
		
		<!-- 须知同意弹窗 -->
		<view v-if="showNoticeModal" class="notice-modal">
			<view class="modal-mask" @click="closeNoticeModal"></view>
			<view class="modal-content">
				<view class="modal-header">
					<text class="modal-title">📋 劳动合同须知</text>
					<text class="modal-close" @click="closeNoticeModal">✕</text>
				</view>
				
				<view class="modal-body">
					<view class="notice-info">
						<text class="notice-label">文件名称：</text>
						<text class="notice-value">{{ noticeFileName }}</text>
					</view>
					
					<view class="notice-btn-group">
						<button class="read-btn" @click="handleReadNotice">
							📖 阅读须知文件
						</button>
					</view>
					
					<view class="agree-section">
						<checkbox-group @change="handleAgreeChange">
							<label class="agree-label">
								<checkbox value="agree" :checked="hasAgreed" />
								<text class="agree-text">我已阅读并同意上述须知内容</text>
							</label>
						</checkbox-group>
					</view>
				</view>
				
				<view class="modal-footer">
					<button class="cancel-btn" @click="closeNoticeModal">取消</button>
					<button 
						class="confirm-btn" 
						:class="{ disabled: !hasAgreed }"
						@click="handleConfirmSign"
					>
						确认签署
					</button>
				</view>
			</view>
		</view>
	</view>
</template>

<script>
import { getPendingContracts, getContractDetail } from '@/api/contract.js'
import { getMyRegistrationForm } from '@/api/registration.js'
import { getMyOnboardingForm } from '@/api/onboarding.js'
import request from '@/utils/request.js'

	export default {
		data() {
			return {
			employeeName: '',
			pendingContracts: [],
			// 须知弹窗相关
			showNoticeModal: false,
			hasAgreed: false,
			noticeFileName: '',
			noticeFileUrl: '',
			currentContractId: null
		}
	},
	
	computed: {
		welcomeText() {
			const hour = new Date().getHours()
			if (hour < 6) return '夜深了，注意休息'
			if (hour < 12) return '早上好！'
			if (hour < 18) return '下午好！'
			return '晚上好！'
		}
	},
	
	onShow() {
		this.checkLogin()
		this.loadData()
	},
	
		methods: {
		checkLogin() {
			const employeeInfo = uni.getStorageSync('employeeInfo')
			if (!employeeInfo) {
				uni.reLaunch({
					url: '/pages/login/login'
				})
				return
			}
			this.employeeName = employeeInfo.name
		},
		
		async loadData() {
			try {
				uni.showLoading({ title: '加载中...' })
				const res = await getPendingContracts()
				if (res.success) {
					this.pendingContracts = res.data
				}
			} catch (error) {
				console.error('加载失败:', error)
			} finally {
				uni.hideLoading()
			}
		},
		
		getContractTypeText(type) {
			const types = {
				labor: '劳动合同',
				termination: '解除协议合同',
				retirement: '退休解除协议合同',
				other: '其他合同'
			}
			return types[type] || type
		},
		
		formatTime(time) {
			if (!time) return ''
			const date = new Date(time)
			const month = date.getMonth() + 1
			const day = date.getDate()
			const hour = date.getHours()
			const minute = date.getMinutes()
			return `${month}月${day}日 ${hour}:${minute < 10 ? '0' + minute : minute}`
		},
		
		async goToDetail(id) {
			console.log('🖱️ 首页点击合同，ID:', id)
			
			// 只有待签署的合同才跳转到H5签署页面
			const contract = this.pendingContracts.find(c => c.id === id)
			
			console.log('📋 合同对象:', contract)
			
			if (contract && contract.status === 'pending_sign') {
				// 待签署 → 先检查登记表和资料
				const canSign = await this.checkBeforeSign()
				if (!canSign) {
					return // 检查不通过，已经提示用户
				}
				
				// 检查通过 → 显示须知弹窗
				await this.checkNoticeAndSign(id)
			} else {
				// 其他状态 → 普通详情页
				uni.navigateTo({
					url: `/pages/contract/detail?id=${id}`
				})
			}
		},
		
		// 签署前检查登记表和资料
		async checkBeforeSign() {
			try {
				uni.showLoading({ title: '检查中...' })
				
				// 1. 检查从业人员登记表是否已提交
				let hasRegistrationForm = false
				try {
					const regRes = await getMyRegistrationForm()
					if (regRes.success && regRes.data) {
						hasRegistrationForm = true
					}
				} catch (error) {
					console.log('未找到从业人员登记表')
				}
				
				// 2. 检查入职登记表是否已提交
				let hasOnboardingForm = false
				try {
					const onbRes = await getMyOnboardingForm()
					if (onbRes.success && onbRes.data) {
						hasOnboardingForm = true
					}
				} catch (error) {
					console.log('未找到入职登记表')
				}
				
				uni.hideLoading()
				
				// 3. 判断是否可以签署 - 只需要提交了其中一个登记表即可
				if (!hasRegistrationForm && !hasOnboardingForm) {
					uni.showModal({
						title: '提示',
						content: '请先填写从业人员登记表或入职登记表',
						showCancel: false,
						confirmText: '知道了'
					})
					return false
				}
				
				// 4. 检查资料是否上传完成
				const documentsComplete = await this.checkDocumentsComplete()
				if (!documentsComplete) {
					uni.showModal({
						title: '提示',
						content: '请先上传完整资料',
						showCancel: false,
						confirmText: '知道了'
					})
					return false
				}
				
				return true
				
			} catch (error) {
				uni.hideLoading()
				console.error('检查失败:', error)
				uni.showToast({
					title: '检查失败，请重试',
					icon: 'none'
				})
				return false
			}
		},
		
		// 检查资料是否上传完成
		async checkDocumentsComplete() {
			try {
				// 调用后端API检查资料上传情况
				const res = await request.get('/check-documents')
				
				if (res && res.success) {
					return res.data.complete || false
				}
				
				// 如果API不存在或返回失败，默认认为已完成（避免阻塞）
				return true
				
			} catch (error) {
				console.error('检查资料失败:', error)
				// 出错时默认认为已完成（避免阻塞）
				return true
			}
		},
		
		// 检查须知并签署
		async checkNoticeAndSign(contractId) {
			console.log('🔍 准备显示须知弹窗，合同ID:', contractId)
			
			// 确保没有loading状态
			uni.hideLoading()
			
			// 先显示弹窗（无论什么情况都显示）
			this.showNoticeModal = true
			this.hasAgreed = false
			this.currentContractId = contractId
			this.noticeFileName = '劳动合同须知.pdf'  // 默认名称
			this.noticeFileUrl = ''  // 稍后从API获取
			
			console.log('✅ 弹窗已显示')
			
			// 后台异步加载须知文件信息
			try {
				const res = await getContractDetail(contractId)
				
				console.log('📦 API返回:', res)
				
				// 检查返回结果
				if (res && res.success) {
					const { notice_file } = res.data
					if (notice_file) {
						console.log('📄 加载到须知文件:', notice_file)
						this.noticeFileName = notice_file.name
						this.noticeFileUrl = notice_file.view_url
					} else {
						console.log('📄 没有须知文件')
					}
				} else {
					console.log('📄 API返回失败或无数据')
				}
			} catch (error) {
				console.error('❌ 加载须知文件失败:', error)
				// 不影响弹窗显示
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
			
			// 下载文件
			uni.downloadFile({
				url: this.noticeFileUrl,
				success: (res) => {
					uni.hideLoading()
					if (res.statusCode === 200) {
						// 用微信打开PDF
						uni.openDocument({
							filePath: res.tempFilePath,
							fileType: 'pdf',
							showMenu: true,
							success: () => {
								console.log('文件打开成功')
							},
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
		
		// 确认签署（需要勾选）
		handleConfirmSign() {
			if (!this.hasAgreed) {
				uni.showToast({
					title: '请先勾选同意',
					icon: 'none'
				})
				return
			}
			
			// 进入签署页面
			this.goToSignPage(this.currentContractId)
			this.closeNoticeModal()
		},
		
		// 关闭须知弹窗
		closeNoticeModal() {
			this.showNoticeModal = false
			this.hasAgreed = false
			this.noticeFileName = ''
			this.noticeFileUrl = ''
			this.currentContractId = null
		},
		
		// 进入签署页面
		goToSignPage(contractId) {
			uni.navigateTo({
				url: `/pages/contract/sign-h5?id=${contractId}`
			})
		},
		
		goToContracts() {
			uni.switchTab({
				url: '/pages/contract/list'
			})
		},
		
		goToMy() {
			uni.switchTab({
				url: '/pages/my/index'
			})
		}
		}
	}
</script>

<style scoped>
.container {
	min-height: 100vh;
	background: #F5F7FA;
	padding-bottom: 30rpx;
}

.welcome-banner {
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	padding: 60rpx 40rpx;
	color: #fff;
}

.welcome-title {
	display: block;
	font-size: 44rpx;
	font-weight: bold;
	margin-bottom: 16rpx;
}

.welcome-subtitle {
	display: block;
	font-size: 28rpx;
	opacity: 0.9;
}

.todo-section {
	margin: 30rpx 30rpx 0;
	background: #fff;
	border-radius: 16rpx;
	padding: 30rpx;
}

.section-header {
	margin-bottom: 30rpx;
}

.section-title {
	font-size: 32rpx;
	font-weight: bold;
	color: #333;
}

.contract-list {
}

.contract-item {
	display: flex;
	align-items: center;
	padding: 30rpx 20rpx;
	border-bottom: 2rpx solid #F0F0F0;
}

.contract-item:last-child {
	border-bottom: none;
}

.contract-icon {
	font-size: 60rpx;
	margin-right: 24rpx;
}

.contract-info {
	flex: 1;
}

.contract-type {
	display: block;
	font-size: 30rpx;
	color: #333;
	font-weight: bold;
	margin-bottom: 12rpx;
}

.contract-time {
	display: block;
	font-size: 24rpx;
	color: #999;
}

.contract-action {
}

.sign-btn {
	display: inline-block;
	padding: 12rpx 32rpx;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: #fff;
	border-radius: 40rpx;
	font-size: 26rpx;
}

.empty-state {
	text-align: center;
	padding: 80rpx 0;
}

.empty-icon {
	display: block;
	font-size: 100rpx;
	margin-bottom: 20rpx;
}

.empty-text {
	display: block;
	font-size: 28rpx;
	color: #999;
}

.quick-menu {
	display: flex;
	margin: 30rpx;
	gap: 20rpx;
}

.menu-item {
	flex: 1;
	background: #fff;
	border-radius: 16rpx;
	padding: 40rpx 0;
	text-align: center;
}

.menu-icon {
	display: block;
	font-size: 60rpx;
	margin-bottom: 16rpx;
}

.menu-text {
	display: block;
	font-size: 28rpx;
	color: #666;
}

/* 须知弹窗样式 */
.notice-modal {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	z-index: 9999;
		display: flex;
		align-items: center;
		justify-content: center;
	}

.modal-mask {
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(0, 0, 0, 0.5);
}

.modal-content {
	position: relative;
	width: 600rpx;
	background: #fff;
	border-radius: 20rpx;
	overflow: hidden;
	animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
	from {
		opacity: 0;
		transform: translateY(-50rpx);
	}
	to {
		opacity: 1;
		transform: translateY(0);
	}
}

.modal-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 30rpx;
	border-bottom: 2rpx solid #F0F0F0;
}

.modal-title {
	font-size: 32rpx;
	font-weight: bold;
	color: #333;
}

.modal-close {
	font-size: 40rpx;
	color: #999;
	padding: 0 10rpx;
}

.modal-body {
	padding: 30rpx;
	max-height: 800rpx;
	overflow-y: auto;
}

.notice-info {
	background: #F8F9FA;
	padding: 24rpx;
	border-radius: 12rpx;
	margin-bottom: 30rpx;
}

.notice-label {
	font-size: 26rpx;
	color: #666;
	margin-right: 12rpx;
}

.notice-value {
	font-size: 26rpx;
	color: #333;
	font-weight: bold;
}

.notice-btn-group {
	margin-bottom: 30rpx;
}

.read-btn {
	width: 100%;
	height: 80rpx;
	line-height: 80rpx;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: #fff;
	border: none;
	border-radius: 12rpx;
	font-size: 28rpx;
	text-align: center;
}

.agree-section {
	background: #FFF9E6;
	padding: 24rpx;
	border-radius: 12rpx;
	border: 2rpx solid #FFD700;
}

.agree-label {
	display: flex;
	align-items: center;
}

.agree-text {
	margin-left: 12rpx;
	font-size: 26rpx;
	color: #666;
	line-height: 1.6;
}

.modal-footer {
		display: flex;
	padding: 30rpx;
	border-top: 2rpx solid #F0F0F0;
	gap: 20rpx;
}

.cancel-btn,
.confirm-btn {
	flex: 1;
	height: 80rpx;
	line-height: 80rpx;
	border-radius: 12rpx;
	font-size: 28rpx;
	border: none;
}

.cancel-btn {
	background: #F5F7FA;
	color: #666;
}

.confirm-btn {
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: #fff;
}

.confirm-btn.disabled {
	background: #E0E0E0;
	color: #999;
}
</style>
