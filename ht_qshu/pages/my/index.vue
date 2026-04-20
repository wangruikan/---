<template>
	<view class="container">
		<!-- 个人信息卡片 -->
		<view class="user-card">
			<view class="avatar">
				<text class="avatar-text">{{ employeeName.substring(0, 1) }}</text>
			</view>
			<view class="user-info">
				<text class="name">{{ employeeName }}</text>
				<text class="phone">{{ employeePhone }}</text>
			</view>
		</view>
		
		<!-- 功能菜单 -->
		<view class="menu-list">
			<!-- 根据项目设置显示对应的登记表入口 -->
			<view class="menu-item" @click="goToOnboardingForm" v-if="registrationFormType === 'onboarding'">
				<text class="menu-icon">📝</text>
				<text class="menu-text">入职登记表</text>
				<text class="menu-arrow">›</text>
			</view>
			<view class="menu-item" @click="goToRegistrationForm" v-if="registrationFormType === 'registration'">
				<text class="menu-icon">📋</text>
				<text class="menu-text">从业人员登记表</text>
				<text class="menu-arrow">›</text>
			</view>
			<view class="menu-item" @click="goToDocumentUpload">
				<text class="menu-icon">📤</text>
				<text class="menu-text">资料上传</text>
				<view class="menu-badge" v-if="pendingCount > 0">{{ pendingCount }}</view>
				<text class="menu-arrow">›</text>
			</view>
			<!-- 离职证明入口（仅离职/退休员工显示） -->
			<view class="menu-item" @click="goToResignationCertificate" v-if="showResignationCertificate">
				<text class="menu-icon">📄</text>
				<text class="menu-text">离职证明</text>
				<text class="menu-arrow">›</text>
			</view>
			<view class="menu-item" @click="handleLogout">
				<text class="menu-icon">🚪</text>
				<text class="menu-text">退出登录</text>
				<text class="menu-arrow">›</text>
			</view>
		</view>
	</view>
</template>

<script>
import { getMyDocuments } from '@/api/document.js'

export default {
	data() {
		return {
			employeeName: '',
			employeePhone: '',
			pendingCount: 0,
			registrationFormType: 'onboarding',  // 默认入职登记表
			showResignationCertificate: false    // 是否显示离职证明入口
		}
	},
	
	onShow() {
		this.loadUserInfo()
		this.loadPendingDocumentsCount()
	},
	
	methods: {
		loadUserInfo() {
			const employeeInfo = uni.getStorageSync('employeeInfo')
			if (employeeInfo) {
				this.employeeName = employeeInfo.name
				this.employeePhone = employeeInfo.phone
				// 获取项目的登记表类型设置
				this.registrationFormType = employeeInfo.registration_form_type || 'onboarding'
				// 检查是否显示离职证明入口（离职或退休员工）
				const contractStatus = employeeInfo.contract_status
				this.showResignationCertificate = ['terminated', 'retired'].includes(contractStatus)
			}
		},
		
		async loadPendingDocumentsCount() {
			try {
				const res = await getMyDocuments()
				if (res.success) {
					const documents = res.data || []
					this.pendingCount = documents.filter(d => !d.uploaded && d.is_required).length
				}
			} catch (error) {
				console.error('加载待上传资料数失败:', error)
			}
		},
		
		goToOnboardingForm() {
			uni.navigateTo({
				url: '/pages/onboarding/index'
			})
		},
		
		goToRegistrationForm() {
			uni.navigateTo({
				url: '/pages/registration/index'
			})
		},
		
		goToDocumentUpload() {
			uni.navigateTo({
				url: '/pages/document/upload'
			})
		},
		
		goToResignationCertificate() {
			uni.navigateTo({
				url: '/pages/resignation/index'
			})
		},
		
		handleLogout() {
			uni.showModal({
				title: '提示',
				content: '确定要退出登录吗？',
				success: (res) => {
					if (res.confirm) {
						uni.removeStorageSync('token')
						uni.removeStorageSync('employeeInfo')
						uni.reLaunch({
							url: '/pages/login/login'
						})
					}
				}
			})
		}
	}
}
</script>

<style scoped>
.container {
	min-height: 100vh;
	background: #F5F7FA;
}

.user-card {
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	padding: 60rpx 40rpx;
	display: flex;
	align-items: center;
}

.avatar {
	width: 120rpx;
	height: 120rpx;
	border-radius: 60rpx;
	background: rgba(255, 255, 255, 0.3);
	display: flex;
	align-items: center;
	justify-content: center;
	margin-right: 30rpx;
}

.avatar-text {
	font-size: 48rpx;
	color: #fff;
	font-weight: bold;
}

.user-info {
	flex: 1;
}

.name {
	display: block;
	font-size: 40rpx;
	color: #fff;
	font-weight: bold;
	margin-bottom: 12rpx;
}

.phone {
	display: block;
	font-size: 28rpx;
	color: rgba(255, 255, 255, 0.9);
}

.menu-list {
	margin: 30rpx 20rpx;
}

.menu-item {
	background: #fff;
	padding: 30rpx 40rpx;
	border-radius: 16rpx;
	display: flex;
	align-items: center;
	margin-bottom: 20rpx;
}

.menu-icon {
	font-size: 40rpx;
	margin-right: 20rpx;
}

.menu-text {
	flex: 1;
	font-size: 30rpx;
	color: #333;
}

.menu-arrow {
	font-size: 40rpx;
	color: #CCC;
}

.menu-badge {
	background-color: #f56c6c;
	color: white;
	font-size: 22rpx;
	padding: 4rpx 12rpx;
	border-radius: 20rpx;
	margin-right: 10rpx;
	min-width: 40rpx;
	text-align: center;
}
</style>

