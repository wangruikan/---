<template>
	<view class="login-container">
		<view class="login-header">
			<text class="title">合同签署系统</text>
			<text class="subtitle">员工登录</text>
		</view>
		
		<view class="login-form">
			<view class="form-item">
				<text class="label">手机号</text>
				<input 
					class="input" 
					v-model="form.phone" 
					type="number" 
					maxlength="11"
					placeholder="请输入手机号" 
				/>
			</view>
			
			<view class="form-item">
				<text class="label">密码</text>
				<input 
					class="input" 
					v-model="form.password" 
					type="text"
					placeholder="请输入密码（初始密码为身份证后6位）" 
				/>
			</view>
			
			<button 
				class="login-btn" 
				:loading="loading"
				:disabled="!canSubmit"
				@click="handleLogin"
			>
				登录
			</button>
			
			<view class="tips">
				<text class="tip-item">• 初始密码为身份证后6位</text>
				<text class="tip-item">• 登录后可修改密码</text>
			</view>
		</view>
	</view>
</template>

<script>
import { login } from '@/api/contract.js'

export default {
	data() {
		return {
			form: {
				phone: '15304543664',
				password: '020345'
			},
			loading: false
		}
	},
	
	computed: {
		canSubmit() {
			return this.form.phone.length === 11 && this.form.password.length >= 6
		}
	},
	
	methods: {
		async handleLogin() {
			if (!this.canSubmit) return
			
			this.loading = true
			
			try {
				const res = await login(this.form)
				
				if (res.success) {
					// 保存token和员工信息
					uni.setStorageSync('token', res.data.token)
					uni.setStorageSync('employeeInfo', res.data.employee)
					
					uni.showToast({
						title: '登录成功',
						icon: 'success'
					})
					
					// 跳转到首页
					setTimeout(() => {
						uni.switchTab({
							url: '/pages/index/index'
						})
					}, 1000)
				}
			} catch (error) {
				console.error('登录失败:', error)
			} finally {
				this.loading = false
			}
		}
	}
}
</script>

<style scoped>
.login-container {
	min-height: 100vh;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	padding: 100rpx 60rpx;
}

.login-header {
	text-align: center;
	margin-bottom: 100rpx;
}

.title {
	display: block;
	font-size: 56rpx;
	font-weight: bold;
	color: #fff;
	margin-bottom: 20rpx;
}

.subtitle {
	display: block;
	font-size: 32rpx;
	color: rgba(255, 255, 255, 0.8);
}

.login-form {
	background: #fff;
	border-radius: 20rpx;
	padding: 60rpx 40rpx;
	box-shadow: 0 10rpx 40rpx rgba(0, 0, 0, 0.1);
}

.form-item {
	margin-bottom: 40rpx;
	position: relative;
}

.label {
	display: block;
	font-size: 28rpx;
	color: #666;
	margin-bottom: 16rpx;
}

.input {
	width: 100%;
	height: 88rpx;
	border: 2rpx solid #E5E5E5;
	border-radius: 12rpx;
	padding: 0 30rpx;
	font-size: 30rpx;
	box-sizing: border-box;
}

.input:focus {
	border-color: #667eea;
}

.login-btn {
	width: 100%;
	height: 88rpx;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: #fff;
	border-radius: 12rpx;
	font-size: 32rpx;
	font-weight: bold;
	border: none;
	margin-top: 40rpx;
}

.login-btn:disabled {
	opacity: 0.6;
}

.tips {
	margin-top: 40rpx;
	padding-top: 30rpx;
	border-top: 2rpx solid #F0F0F0;
}

.tip-item {
	display: block;
	font-size: 24rpx;
	color: #999;
	line-height: 40rpx;
}
</style>

