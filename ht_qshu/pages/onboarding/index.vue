<template>
	<view class="container">
		<view class="form-section">
			<view class="section-title">
				<text>基本信息</text>
				<view class="fill-sample-btn" @click="fillSampleData">
					<text class="icon">📝</text>
					<text class="text">示例数据</text>
				</view>
			</view>
			
			<!-- 登记日期 -->
			<view class="form-item">
				<text class="label">登记日期</text>
				<picker mode="date" :value="formatDateForPicker(formData.registration_date)" @change="onDateChange">
					<view class="picker">{{ formatDateDisplay(formData.registration_date) || '请选择日期' }}</view>
				</picker>
			</view>
			
			<!-- 姓名 -->
			<view class="form-item">
				<text class="label">姓名</text>
				<input type="text" placeholder="请输入姓名" v-model="formData.name" />
			</view>
			
			<!-- 性别 -->
			<view class="form-item">
				<text class="label">性别</text>
				<radio-group @change="onGenderChange">
					<label class="radio-label">
						<radio value="male" :checked="formData.gender === 'male'" />男
					</label>
					<label class="radio-label">
						<radio value="female" :checked="formData.gender === 'female'" />女
					</label>
				</radio-group>
			</view>
			
			<!-- 身份证号码 -->
			<view class="form-item">
				<text class="label required">身份证号码</text>
				<input type="idcard" placeholder="请输入身份证号码" v-model="formData.id_number" maxlength="18" @blur="onIdNumberChange" />
			</view>
			
			<!-- 出生年月（根据身份证自动计算） -->
			<view class="form-item">
				<text class="label">出生年月</text>
				<view class="picker disabled">{{ formatBirthDate(formData.birth_date) || '输入身份证后自动获取' }}</view>
			</view>
			
			<!-- 一寸照片 -->
			<view class="form-item photo-item">
				<text class="label">一寸照片</text>
				<view class="photo-upload" @click="choosePhoto">
					<image v-if="formData.photo" :src="formData.photo" mode="aspectFill" class="photo-preview" />
					<view v-else class="photo-placeholder">
						<text class="photo-icon">📷</text>
						<text class="photo-text">点击上传</text>
					</view>
				</view>
			</view>
			
			<!-- 民族 -->
			<view class="form-item">
				<text class="label">民族</text>
				<picker :range="ethnicityOptions" :value="ethnicityIndex" @change="onEthnicityChange">
					<view class="picker">{{ formData.ethnicity || '请选择民族' }}</view>
				</picker>
			</view>
			
			<!-- 籍贯 -->
			<view class="form-item">
				<text class="label">籍贯</text>
				<picker mode="region" :value="placeOfOriginArray" @change="onPlaceOfOriginChange">
					<view class="picker">{{ formData.place_of_origin || '请选择籍贯' }}</view>
				</picker>
			</view>
			
			<!-- 政治面貌 -->
			<view class="form-item">
				<text class="label">政治面貌</text>
				<picker :range="politicalOptions" :value="politicalIndex" @change="onPoliticalChange">
					<view class="picker">{{ formData.political_status || '请选择政治面貌' }}</view>
				</picker>
			</view>
			
			<!-- 现居住地 -->
			<view class="form-item">
				<text class="label">现居住地</text>
				<input type="text" placeholder="请输入现居住地" v-model="formData.current_residence" />
			</view>
			
			<!-- 户口所在地 -->
			<view class="form-item">
				<text class="label">户口所在地</text>
				<input type="text" placeholder="请输入户口所在地" v-model="formData.household_registration" />
			</view>
			
			<!-- 婚姻状况 -->
			<view class="form-item">
				<text class="label">婚姻状况</text>
				<picker :range="maritalOptions" :value="maritalIndex" @change="onMaritalChange">
					<view class="picker">{{ formData.marital_status || '请选择婚姻状况' }}</view>
				</picker>
			</view>
			
			<!-- 健康状况 -->
			<view class="form-item">
				<text class="label">健康状况</text>
				<picker :range="healthOptions" :value="healthIndex" @change="onHealthChange">
					<view class="picker">{{ formData.health_status || '请选择健康状况' }}</view>
				</picker>
			</view>
			
			<!-- 身高 -->
			<view class="form-item">
				<text class="label">身高 (cm)</text>
				<input type="digit" placeholder="请输入身高" v-model="formData.height" />
			</view>
			
			<!-- 体重 -->
			<view class="form-item">
				<text class="label">体重 (kg)</text>
				<input type="digit" placeholder="请输入体重" v-model="formData.weight" />
			</view>
		</view>
		
		<view class="form-section">
			<view class="section-title">教育信息</view>
			
			<!-- 毕业学校 -->
			<view class="form-item">
				<text class="label">毕业学校</text>
				<input type="text" placeholder="请输入毕业学校" v-model="formData.graduated_school" />
			</view>
			
			<!-- 毕业时间 -->
			<view class="form-item">
				<text class="label">毕业时间</text>
				<picker mode="date" fields="month" :value="formData.graduation_date" @change="onGraduationDateChange">
					<view class="picker">{{ formData.graduation_date || '请选择' }}</view>
				</picker>
			</view>
			
			<!-- 文化程度 -->
			<view class="form-item">
				<text class="label">文化程度</text>
				<input type="text" placeholder="请输入文化程度" v-model="formData.education_level" />
			</view>
			
			<!-- 所学专业 -->
			<view class="form-item">
				<text class="label">所学专业</text>
				<input type="text" placeholder="请输入所学专业" v-model="formData.major" />
			</view>
			
			<!-- 学位 -->
			<view class="form-item">
				<text class="label">学位</text>
				<input type="text" placeholder="请输入学位" v-model="formData.degree" />
			</view>
			
			<!-- 技术职称 -->
			<view class="form-item">
				<text class="label">技术职称</text>
				<input type="text" placeholder="请输入技术职称" v-model="formData.technical_title" />
			</view>
		</view>
		
		<!-- 学习简历 -->
		<view class="form-section">
			<view class="section-title">
				学习简历
				<text class="add-btn" @click="addEducationBackground">+ 添加</text>
			</view>
			<view class="list-item" v-for="(item, index) in formData.education_background" :key="index">
				<view class="item-header">
					<text>第{{ index + 1 }}条</text>
					<text class="delete-btn" @click="deleteEducationBackground(index)">删除</text>
				</view>
				<view class="form-item">
					<text class="label">起止时间</text>
					<input type="text" placeholder="例如：2010.09-2014.06" v-model="item.date_range" @input="onEducationDateChange(index, $event)" />
				</view>
				<view class="form-item">
					<text class="label">在何学校学习</text>
					<input type="text" placeholder="请输入学校名称" v-model="item.school" />
				</view>
				<view class="form-item">
					<text class="label">学习层次</text>
					<input type="text" placeholder="请输入学习层次" v-model="item.level" />
				</view>
				<view class="form-item">
					<text class="label">证明人</text>
					<input type="text" placeholder="请输入证明人" v-model="item.certifier" />
				</view>
			</view>
		</view>
		
		<!-- 工作经历 -->
		<view class="form-section">
			<view class="section-title">
				工作经历
				<text class="add-btn" @click="addWorkExperience">+ 添加</text>
			</view>
			<view class="list-item" v-for="(item, index) in formData.work_experience" :key="index">
				<view class="item-header">
					<text>第{{ index + 1 }}条</text>
					<text class="delete-btn" @click="deleteWorkExperience(index)">删除</text>
				</view>
				<view class="form-item">
					<text class="label">起止时间</text>
					<input type="text" placeholder="例如：2014.07-2018.06" v-model="item.date_range" @input="onWorkDateChange(index, $event)" />
				</view>
				<view class="form-item">
					<text class="label">在何工作单位</text>
					<input type="text" placeholder="请输入工作单位" v-model="item.employer" />
				</view>
				<view class="form-item">
					<text class="label">主要工作内容</text>
					<textarea placeholder="请输入主要工作内容" v-model="item.job_content" />
				</view>
				<view class="form-item">
					<text class="label">证明人</text>
					<input type="text" placeholder="请输入证明人" v-model="item.certifier" />
				</view>
			</view>
		</view>
		
		<!-- 家庭情况 -->
		<view class="form-section">
			<view class="section-title">
				家庭情况
				<text class="add-btn" @click="addFamilyInfo">+ 添加</text>
			</view>
			<view class="list-item" v-for="(item, index) in formData.family_info" :key="index">
				<view class="item-header">
					<text>第{{ index + 1 }}条</text>
					<text class="delete-btn" @click="deleteFamilyInfo(index)">删除</text>
				</view>
				<view class="form-item">
					<text class="label">姓名</text>
					<input type="text" placeholder="请输入姓名" v-model="item.name" />
				</view>
				<view class="form-item">
					<text class="label">关系</text>
					<input type="text" placeholder="例如：父亲、母亲、配偶" v-model="item.relationship" />
				</view>
				<view class="form-item">
					<text class="label">所在单位</text>
					<input type="text" placeholder="请输入所在单位" v-model="item.employer" />
				</view>
				<view class="form-item">
					<text class="label">联系电话</text>
					<input type="number" placeholder="请输入联系电话" v-model="item.phone" />
				</view>
			</view>
		</view>
		
		<!-- 就业信息 -->
		<view class="form-section">
			<view class="section-title">就业信息</view>
			
			<!-- 岗位 -->
			<view class="form-item">
				<text class="label">岗位</text>
				<input type="text" placeholder="请输入岗位" v-model="formData.position" />
			</view>
			
			<!-- 求职地区 -->
			<view class="form-item">
				<text class="label">求职地区</text>
				<input type="text" placeholder="请输入求职地区" v-model="formData.desired_location" />
			</view>
			
			<!-- 是否服从调配 -->
			<view class="form-item">
				<text class="label">是否服从调配</text>
				<switch :checked="formData.accept_assignment" @change="onAcceptAssignmentChange" />
			</view>
			
			<!-- 联系地址 -->
			<view class="form-item">
				<text class="label">联系地址</text>
				<input type="text" placeholder="请输入联系地址" v-model="formData.contact_address" />
			</view>
			
			<!-- 联系电话 -->
			<view class="form-item">
				<text class="label">联系电话</text>
				<input type="number" placeholder="请输入联系电话" v-model="formData.contact_phone" />
			</view>
		</view>
		
		<!-- 备注 -->
		<view class="form-section">
			<view class="section-title">备注</view>
			<textarea placeholder="请输入备注信息" v-model="formData.remarks" class="textarea" />
		</view>
		
		<!-- 签名 -->
		<view class="form-section">
			<view class="section-title">声明与签名</view>
			<view class="declaration-box">
				<text class="declaration-text">本人保证以上所填写的信息真实可靠，如有虚假，本人愿承担一切法律责任。</text>
			</view>
			
			<view class="signature-wrapper">
				<text class="label">本人签名：</text>
				
				<!-- 未签名：显示Canvas -->
				<view v-if="!formData.signature" class="signature-canvas-box">
					<canvas 
						canvas-id="signatureCanvas" 
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
					<image :src="formData.signature" mode="aspectFit" class="signature-image"></image>
					<button class="clear-signature-btn" @click="clearSignature">重新签名</button>
				</view>
			</view>
		</view>
		
		<!-- 提交按钮 -->
		<view class="submit-section">
			<button type="primary" @click="submitForm" :loading="submitting" class="submit-btn">提交</button>
		</view>
	</view>
</template>

<script>
import { getMyOnboardingForm, submitOnboardingForm, uploadSignature } from '@/api/onboarding.js'
import { BASE_URL } from '@/utils/request.js'

export default {
	data() {
		return {
			formData: {
				registration_date: '',
				name: '',
				gender: 'male',
				ethnicity: '',
				political_status: '',
				place_of_origin: '',
				birth_date: '',
				id_number: '',
				current_residence: '',
				household_registration: '',
				marital_status: '',
				health_status: '',
				height: '',
				weight: '',
				graduated_school: '',
				graduation_date: '',
				education_level: '',
				major: '',
				degree: '',
				technical_title: '',
				position: '',
				desired_location: '',
				accept_assignment: false,
				contact_address: '',
				contact_phone: '',
				remarks: '',
				signature: '',
				signaturePath: '',
				photo: '', // 一寸照片URL
				photoPath: '', // 一寸照片路径
				education_background: [],
				work_experience: [],
				family_info: []
			},
			submitting: false,
			// 签名相关
			signatureCtx: null,
			isDrawing: false,
			lastPoint: null,
			hasSignature: false,
			canvasWidth: 0,
			canvasHeight: 0,
			// 选项数组
			ethnicityOptions: ['汉族', '蒙古族', '回族', '藏族', '维吾尔族', '苗族', '彝族', '壮族', '布依族', '朝鲜族', '满族', '侗族', '瑶族', '白族', '土家族', '哈尼族', '哈萨克族', '傣族', '黎族', '傈僳族', '佤族', '畲族', '高山族', '拉祜族', '水族', '东乡族', '纳西族', '景颇族', '柯尔克孜族', '土族', '达斡尔族', '仫佬族', '羌族', '布朗族', '撒拉族', '毛南族', '仡佬族', '锡伯族', '阿昌族', '普米族', '塔吉克族', '怒族', '乌孜别克族', '俄罗斯族', '鄂温克族', '德昂族', '保安族', '裕固族', '京族', '塔塔尔族', '独龙族', '鄂伦春族', '赫哲族', '门巴族', '珞巴族', '基诺族'],
			ethnicityIndex: 0,
			politicalOptions: ['群众', '中共党员', '中共预备党员', '共青团员', '民革党员', '民盟盟员', '民建会员', '民进会员', '农工党党员', '致公党党员', '九三学社社员', '台盟盟员', '无党派人士'],
			politicalIndex: 0,
			maritalOptions: ['未婚', '已婚', '离异', '丧偶'],
			maritalIndex: 0,
			healthOptions: ['健康', '良好', '一般', '较差'],
			healthIndex: 0,
			placeOfOriginArray: []
		}
	},
	
	onLoad() {
		// 设置默认登记日期为今天
		const today = new Date()
		const dateStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`
		this.formData.registration_date = dateStr
		
		// 计算Canvas尺寸
		const systemInfo = uni.getSystemInfoSync()
		const screenWidth = systemInfo.windowWidth
		// 减去padding (20rpx * 2 = 20px * 2) 和 border (20rpx = 10px * 2)
		this.canvasWidth = screenWidth - 60
		this.canvasHeight = 200  // 固定高度200px
		
		// 初始化签名canvas
		this.$nextTick(() => {
			this.initSignatureCanvas()
		})
		
		// 加载已保存的数据
		this.loadSavedData()
	},
	
	methods: {
		// 加载已保存的数据
		async loadSavedData() {
			try {
				const res = await getMyOnboardingForm()
				if (res.success && res.data) {
					// 处理已保存的数据
					const data = res.data
					
					// 处理学习简历和工作经历的日期范围
					if (data.education_background) {
						data.education_background = data.education_background.map(item => ({
							...item,
							date_range: item.start_date && item.end_date ? `${item.start_date}-${item.end_date}` : ''
						}))
					}
					
					if (data.work_experience) {
						data.work_experience = data.work_experience.map(item => ({
							...item,
							date_range: item.start_date && item.end_date ? `${item.start_date}-${item.end_date}` : ''
						}))
					}
					
					// 处理签名：后端返回的signature是完整URL
				// 需要提取路径部分保存到signaturePath（兼容新旧格式）
				if (data.signature) {
					this.formData.signature = data.signature  // 完整URL用于显示
					// 新格式：/uploads/signatures/xxx.png
					if (data.signature.includes('/uploads/signatures/')) {
						const match = data.signature.match(/\/uploads\/signatures\/(.+)$/)
						if (match) {
							this.formData.signaturePath = 'uploads/signatures/' + match[1]
						}
					}
					// 旧格式：/storage/signatures/xxx.png
					else if (data.signature.includes('/storage/')) {
						const match = data.signature.match(/\/storage\/(.+)$/)
						if (match) {
							this.formData.signaturePath = match[1]
						}
					}
					this.hasSignature = true
				}
				
				// 处理寸照：后端返回的photo是完整URL
				if (data.photo) {
					this.formData.photo = data.photo  // 完整URL用于显示
					// 新格式：/uploads/photos/xxx.png
					if (data.photo.includes('/uploads/')) {
						const match = data.photo.match(/\/uploads\/(.+)$/)
						if (match) {
							this.formData.photoPath = 'uploads/' + match[1]
						}
					}
					// 旧格式：/storage/photos/xxx.png
					else if (data.photo.includes('/storage/')) {
						const match = data.photo.match(/\/storage\/(.+)$/)
						if (match) {
							this.formData.photoPath = match[1]
						}
					}
				}
				
				// 复制其他数据（排除signature和photo，因为已经处理过了）
				const { signature, photo, ...otherData } = data
				this.formData = { ...this.formData, ...otherData }
				}
			} catch (error) {
				console.error('加载数据失败:', error)
			}
		},
		
		// 日期选择
		onDateChange(e) {
			this.formData.registration_date = e.detail.value
		},
		
		// 格式化日期用于picker的value（需要YYYY-MM-DD格式）
		formatDateForPicker(dateStr) {
			if (!dateStr) return ''
			// 如果是ISO格式，转换为YYYY-MM-DD
			if (dateStr.includes('T')) {
				return dateStr.split('T')[0]
			}
			return dateStr
		},
		
		// 格式化日期用于显示
		formatDateDisplay(dateStr) {
			if (!dateStr) return ''
			// 如果是ISO格式，转换为YYYY-MM-DD
			if (dateStr.includes('T')) {
				return dateStr.split('T')[0]
			}
			return dateStr
		},
		
		// 格式化出生年月显示
		formatBirthDate(dateStr) {
			if (!dateStr) return ''
			// 如果是ISO格式，提取年月
			if (dateStr.includes('T')) {
				const date = new Date(dateStr)
				const year = date.getFullYear()
				const month = String(date.getMonth() + 1).padStart(2, '0')
				return `${year}-${month}`
			}
			// 如果已经是YYYY-MM格式
			if (dateStr.length === 7) {
				return dateStr
			}
			// 如果是YYYY-MM-DD格式，提取年月
			if (dateStr.length === 10) {
				return dateStr.substring(0, 7)
			}
			return dateStr
		},
		
		// 性别选择
		onGenderChange(e) {
			this.formData.gender = e.detail.value
		},
		
		// 身份证号码变化时校验并提取出生日期
		onIdNumberChange(e) {
			const idNumber = this.formData.id_number
			if (!idNumber) return
			
			// 校验身份证号码
			if (!this.validateIdNumber(idNumber)) {
				uni.showToast({
					title: '请输入正确的身份证号码',
					icon: 'none'
				})
				return
			}
			
			// 从身份证提取出生日期
			const birthYear = idNumber.substring(6, 10)
			const birthMonth = idNumber.substring(10, 12)
			this.formData.birth_date = `${birthYear}-${birthMonth}`
			
			// 从身份证提取性别（倒数第二位奇数为男，偶数为女）
			const genderCode = parseInt(idNumber.substring(16, 17))
			this.formData.gender = genderCode % 2 === 1 ? 'male' : 'female'
		},
		
		// 校验身份证号码
		validateIdNumber(idNumber) {
			if (!idNumber || idNumber.length !== 18) return false
			
			// 校验格式
			const reg = /^[1-9]\d{5}(19|20)\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\d{3}[\dXx]$/
			if (!reg.test(idNumber)) return false
			
			// 校验校验码
			const weights = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2]
			const checkCodes = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2']
			let sum = 0
			for (let i = 0; i < 17; i++) {
				sum += parseInt(idNumber[i]) * weights[i]
			}
			const checkCode = checkCodes[sum % 11]
			return idNumber[17].toUpperCase() === checkCode
		},
		
		// 民族选择
		onEthnicityChange(e) {
			this.ethnicityIndex = e.detail.value
			this.formData.ethnicity = this.ethnicityOptions[e.detail.value]
		},
		
		// 籍贯选择
		onPlaceOfOriginChange(e) {
			this.placeOfOriginArray = e.detail.value
			this.formData.place_of_origin = e.detail.value.join('')
		},
		
		// 政治面貌选择
		onPoliticalChange(e) {
			this.politicalIndex = e.detail.value
			this.formData.political_status = this.politicalOptions[e.detail.value]
		},
		
		// 婚姻状况选择
		onMaritalChange(e) {
			this.maritalIndex = e.detail.value
			this.formData.marital_status = this.maritalOptions[e.detail.value]
		},
		
		// 健康状况选择
		onHealthChange(e) {
			this.healthIndex = e.detail.value
			this.formData.health_status = this.healthOptions[e.detail.value]
		},
		
		// 选择一寸照片
		async choosePhoto() {
			uni.chooseImage({
				count: 1,
				sizeType: ['compressed'],
				sourceType: ['album', 'camera'],
				success: async (res) => {
					const tempFilePath = res.tempFilePaths[0]
					
					uni.showLoading({ title: '上传中...', mask: true })
					
					try {
						// 上传照片
						const uploadRes = await this.uploadPhoto(tempFilePath)
						uni.hideLoading()
						
						if (uploadRes.success) {
							this.formData.photo = uploadRes.data.url
							this.formData.photoPath = uploadRes.data.path
							uni.showToast({ title: '上传成功', icon: 'success' })
						} else {
							uni.showToast({ title: uploadRes.message || '上传失败', icon: 'none' })
						}
					} catch (error) {
						uni.hideLoading()
						console.error('上传照片失败:', error)
						uni.showToast({ title: '上传失败', icon: 'none' })
					}
				}
			})
		},
		
		// 上传照片到服务器
		uploadPhoto(filePath) {
			return new Promise((resolve, reject) => {
				const token = uni.getStorageSync('token')
				
				uni.uploadFile({
					url: BASE_URL + '/upload-photo',
					filePath: filePath,
					name: 'photo',
					header: {
						'Authorization': 'Bearer ' + token,
						'X-Auth-Token': token
					},
					success: (res) => {
						try {
							const data = JSON.parse(res.data)
							resolve(data)
						} catch (e) {
							reject(e)
						}
					},
					fail: (err) => {
						reject(err)
					}
				})
			})
		},
		
		// 毕业时间选择
		onGraduationDateChange(e) {
			this.formData.graduation_date = e.detail.value
		},
		
		// 是否服从调配
		onAcceptAssignmentChange(e) {
			this.formData.accept_assignment = e.detail.value
		},
		
		// 添加学习简历
		addEducationBackground() {
			this.formData.education_background.push({
				date_range: '',
				school: '',
				level: '',
				certifier: ''
			})
		},
		
		// 删除学习简历
		deleteEducationBackground(index) {
			this.formData.education_background.splice(index, 1)
		},
		
		// 学习简历日期输入
		onEducationDateChange(index, e) {
			const value = e.detail.value
			const dates = value.split('-')
			if (dates.length === 2) {
				this.formData.education_background[index].start_date = dates[0].trim()
				this.formData.education_background[index].end_date = dates[1].trim()
			}
		},
		
		// 添加工作经历
		addWorkExperience() {
			this.formData.work_experience.push({
				date_range: '',
				employer: '',
				job_content: '',
				certifier: ''
			})
		},
		
		// 删除工作经历
		deleteWorkExperience(index) {
			this.formData.work_experience.splice(index, 1)
		},
		
		// 工作经历日期输入
		onWorkDateChange(index, e) {
			const value = e.detail.value
			const dates = value.split('-')
			if (dates.length === 2) {
				this.formData.work_experience[index].start_date = dates[0].trim()
				this.formData.work_experience[index].end_date = dates[1].trim()
			}
		},
		
		// 添加家庭情况
		addFamilyInfo() {
			this.formData.family_info.push({
				name: '',
				relationship: '',
				employer: '',
				phone: ''
			})
		},
		
		// 删除家庭情况
		deleteFamilyInfo(index) {
			this.formData.family_info.splice(index, 1)
		},
		
		// 一键填充示例数据
		fillSampleData() {
			uni.showModal({
				title: '提示',
				content: '确定要填充示例数据吗？这将覆盖当前已填写的内容。',
				success: (res) => {
					if (res.confirm) {
						const today = new Date()
						const dateStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`
						const birthYear = 1990
						const birthMonth = 5
						const gradYear = 2012
						const gradMonth = 6
						
						this.formData = {
							registration_date: dateStr,
							name: '张三',
							gender: 'male',
							ethnicity: '汉',
							political_status: '群众',
							place_of_origin: '北京市',
							birth_date: `${birthYear}-${String(birthMonth).padStart(2, '0')}`,
							id_number: '110101199005011234',
							current_residence: '北京市朝阳区某某街道某某号',
							household_registration: '北京市朝阳区',
							marital_status: '已婚',
							health_status: '健康',
							height: '175',
							weight: '70.5',
							graduated_school: '北京大学',
							graduation_date: `${gradYear}-${String(gradMonth).padStart(2, '0')}`,
							education_level: '本科',
							major: '计算机科学与技术',
							degree: '学士',
							technical_title: '工程师',
							position: '软件工程师',
							desired_location: '北京市',
							accept_assignment: true,
							contact_address: '北京市朝阳区某某街道某某号',
							contact_phone: '13800138000',
							remarks: '本人具有良好的沟通能力和团队合作精神，熟悉Java、Python等编程语言。',
							education_background: [
								{
									date_range: '2006.09-2009.06',
									school: '北京市第一中学',
									level: '高中',
									certifier: '李老师',
									start_date: '2006.09',
									end_date: '2009.06'
								},
								{
									date_range: '2009.09-2012.06',
									school: '北京大学',
									level: '本科',
									certifier: '王老师',
									start_date: '2009.09',
									end_date: '2012.06'
								}
							],
							work_experience: [
								{
									date_range: '2012.07-2018.06',
									employer: '某某科技有限公司',
									job_content: '负责公司核心产品的开发和维护，参与系统架构设计，指导 junior 开发人员。',
									certifier: '赵经理',
									start_date: '2012.07',
									end_date: '2018.06'
								},
								{
									date_range: '2018.07-2024.12',
									employer: '某某互联网公司',
									job_content: '担任技术负责人，负责团队管理和项目推进，参与多个重要项目的开发。',
									certifier: '钱总监',
									start_date: '2018.07',
									end_date: '2024.12'
								}
							],
							family_info: [
								{
									name: '张父',
									relationship: '父亲',
									employer: '某某公司',
									phone: '13900139000'
								},
								{
									name: '张母',
									relationship: '母亲',
									employer: '某某单位',
									phone: '13900139001'
								},
								{
									name: '李四',
									relationship: '配偶',
									employer: '某某企业',
									phone: '13900139002'
								}
							]
						}
						
						uni.showToast({
							title: '示例数据已填充',
							icon: 'success'
						})
					}
				}
			})
		},
		
		// 提交表单
		async submitForm() {
			// 验证必填字段
			if (!this.formData.registration_date) {
				uni.showToast({
					title: '请选择登记日期',
					icon: 'none'
				})
				return
			}
			
			if (!this.formData.name) {
				uni.showToast({
					title: '请输入姓名',
					icon: 'none'
				})
				return
			}
			
			if (!this.formData.id_number) {
				uni.showToast({
					title: '请输入身份证号码',
					icon: 'none'
				})
				return
			}
			
			// 校验身份证号码格式
			if (!this.validateIdNumber(this.formData.id_number)) {
				uni.showToast({
					title: '请输入正确的身份证号码',
					icon: 'none'
				})
				return
			}
			
			// 验证签名（检查signaturePath，因为提交时用的是这个）
			if (!this.formData.signaturePath) {
				uni.showToast({
					title: '请先完成手写签名',
					icon: 'none'
				})
				return
			}
			
			this.submitting = true
			
			try {
				// 处理提交数据，将日期范围转换为start_date和end_date
				const submitData = { ...this.formData }
				
				// 提交时使用signaturePath（相对路径），不用signature（URL）
				submitData.signature = this.formData.signaturePath
				delete submitData.signaturePath
				
				// 提交时使用photoPath（相对路径），不用photo（URL）
				if (this.formData.photoPath) {
					submitData.photo = this.formData.photoPath
				}
				delete submitData.photoPath
				
				// 调试日志
				console.log('提交签名路径:', submitData.signature)
				console.log('提交寸照路径:', submitData.photo)
				
				// 处理学习简历
				if (submitData.education_background) {
					submitData.education_background = submitData.education_background.map(item => {
						const { date_range, ...rest } = item
						return rest
					})
				}
				
				// 处理工作经历
				if (submitData.work_experience) {
					submitData.work_experience = submitData.work_experience.map(item => {
						const { date_range, ...rest } = item
						return rest
					})
				}
				
				const res = await submitOnboardingForm(submitData)
				
				if (res.success) {
					uni.showToast({
						title: '提交成功',
						icon: 'success'
					})
					
					// 返回上一页
					setTimeout(() => {
						uni.navigateBack()
					}, 1500)
				} else {
					uni.showToast({
						title: res.message || '提交失败',
						icon: 'none'
					})
				}
			} catch (error) {
				console.error('提交失败:', error)
				uni.showToast({
					title: '提交失败，请重试',
					icon: 'none'
				})
			} finally {
				this.submitting = false
			}
		},
		
		// 初始化签名canvas
		initSignatureCanvas() {
			if (!this.signatureCtx) {
				this.signatureCtx = uni.createCanvasContext('signatureCanvas', this)
			}
			// 设置画笔样式
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
			this.hasSignature = true  // 标记已经开始签名
			
			const touch = e.touches[0]
			const x = touch.x
			const y = touch.y
			
			// 开始新的路径
			this.signatureCtx.beginPath()
			this.signatureCtx.moveTo(x, y)
			// 画一个点作为起点，避免起点无内容
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
			
			// 从上一点到当前点绘制直线
			this.signatureCtx.beginPath()
			this.signatureCtx.moveTo(this.lastPoint.x, this.lastPoint.y)
			this.signatureCtx.lineTo(x, y)
			this.signatureCtx.stroke()
			this.signatureCtx.draw(true)  // 保留之前的内容
			
			this.lastPoint = { x, y }
		},
		
		// 结束绘制（结束当前笔画，不保存，允许继续绘制下一笔）
		handleTouchEnd() {
			if (!this.isDrawing) return
			this.isDrawing = false
			this.lastPoint = null
		},
		
		// 完成签名（用户主动点击完成按钮）
		async finishSignature() {
			if (!this.hasSignature) {
				uni.showToast({
					title: '请先签名',
					icon: 'none'
				})
				return
			}
			
			// 显示上传中
			uni.showLoading({
				title: '上传中...'
			})
			
			// 保存签名为base64
			this.saveSignature(async (success, base64Data) => {
				if (!success) {
					uni.hideLoading()
					uni.showToast({
						title: '签名生成失败',
						icon: 'none'
					})
					return
				}
				
				try {
					// 立即上传到后端
					const res = await uploadSignature(base64Data)
					uni.hideLoading()
					
					console.log('上传签名响应:', res)
					
					if (res.success) {
						// 保存返回的URL（用于显示）和路径（用于提交）
						this.formData.signature = res.data.url  // 完整URL用于显示图片
						this.formData.signaturePath = res.data.path  // 相对路径用于提交表单
						
						console.log('保存到formData:')
						console.log('  signature:', this.formData.signature)
						console.log('  signaturePath:', this.formData.signaturePath)
						
						uni.showToast({
							title: '签名已保存',
							icon: 'success'
						})
					} else {
						uni.showToast({
							title: res.message || '上传失败',
							icon: 'none'
						})
					}
				} catch (error) {
					uni.hideLoading()
					console.error('上传签名失败:', error)
					uni.showToast({
						title: '上传失败',
						icon: 'none'
					})
				}
			})
		},
		
		// 保存签名为base64
		saveSignature(callback) {
			uni.canvasToTempFilePath({
				canvasId: 'signatureCanvas',
				fileType: 'png',
				quality: 1,
				width: this.canvasWidth,
				height: this.canvasHeight,
				destWidth: this.canvasWidth * 2,  // 提高导出质量
				destHeight: this.canvasHeight * 2,
				success: (res) => {
					// 读取临时文件并转换为base64
					uni.getFileSystemManager().readFile({
						filePath: res.tempFilePath,
						encoding: 'base64',
						success: (data) => {
							const base64Data = 'data:image/png;base64,' + data.data
							console.log('签名生成成功', base64Data.substring(0, 50))
							if (callback) callback(true, base64Data)  // 传递base64数据
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
		
		// 清除签名
		clearSignature() {
			// 清除签名数据
			this.formData.signature = ''
			this.formData.signaturePath = ''
			this.isDrawing = false
			this.lastPoint = null
			this.hasSignature = false
			
			// v-if会切换到Canvas，需要等DOM更新后再清空Canvas
			this.$nextTick(() => {
				if (this.signatureCtx) {
					this.signatureCtx.clearRect(0, 0, this.canvasWidth, this.canvasHeight)
					this.signatureCtx.draw(false)
					
					// 重新设置绘制样式
					this.signatureCtx.setStrokeStyle('#000000')
					this.signatureCtx.setLineWidth(3)
					this.signatureCtx.setLineCap('round')
					this.signatureCtx.setLineJoin('round')
				} else {
					// 如果Canvas上下文不存在，重新初始化
					this.initSignatureCanvas()
				}
			})
			
			uni.showToast({
				title: '已清除，请重新签名',
				icon: 'none'
			})
		}
	}
}
</script>

<style scoped>
.container {
	padding: 20rpx;
	background-color: #f5f5f5;
	min-height: 100vh;
	padding-bottom: 120rpx;
}

.form-section {
	background-color: #fff;
	margin-bottom: 20rpx;
	padding: 30rpx;
	border-radius: 10rpx;
}

.section-title {
	font-size: 32rpx;
	font-weight: bold;
	margin-bottom: 30rpx;
	color: #333;
	display: flex;
	justify-content: space-between;
	align-items: center;
	position: relative;
}

.fill-sample-btn {
	display: flex;
	align-items: center;
	padding: 8rpx 16rpx;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	border-radius: 20rpx;
	color: #fff;
	font-size: 22rpx;
	box-shadow: 0 2rpx 8rpx rgba(102, 126, 234, 0.3);
	transition: all 0.3s;
}

.fill-sample-btn:active {
	transform: scale(0.95);
	box-shadow: 0 1rpx 4rpx rgba(102, 126, 234, 0.3);
}

.fill-sample-btn .icon {
	font-size: 24rpx;
	margin-right: 6rpx;
}

.fill-sample-btn .text {
	font-weight: 500;
}

.add-btn {
	font-size: 28rpx;
	color: #007AFF;
	font-weight: normal;
}

.form-item {
	margin-bottom: 30rpx;
	display: flex;
	align-items: center;
}

.label {
	width: 200rpx;
	font-size: 28rpx;
	color: #666;
	flex-shrink: 0;
}

.form-item input,
.form-item textarea,
.picker {
	flex: 1;
	font-size: 28rpx;
	color: #333;
	min-height: 60rpx;
	line-height: 60rpx;
}

.form-item textarea {
	min-height: 120rpx;
	line-height: 1.5;
	padding: 10rpx;
	border: 1rpx solid #ddd;
	border-radius: 5rpx;
}

.textarea {
	width: 100%;
	min-height: 120rpx;
	line-height: 1.5;
	padding: 10rpx;
	border: 1rpx solid #ddd;
	border-radius: 5rpx;
	font-size: 28rpx;
}

.picker {
	border: 1rpx solid #ddd;
	border-radius: 5rpx;
	padding: 0 20rpx;
}

.radio-label {
	margin-right: 30rpx;
	font-size: 28rpx;
}

.list-item {
	background-color: #f9f9f9;
	padding: 20rpx;
	margin-bottom: 20rpx;
	border-radius: 10rpx;
}

.item-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 20rpx;
	font-size: 28rpx;
	color: #666;
}

.delete-btn {
	color: #f56c6c;
	font-size: 26rpx;
}


.submit-section {
	position: fixed;
	bottom: 0;
	left: 0;
	right: 0;
	padding: 20rpx;
	background-color: #fff;
	box-shadow: 0 -2rpx 10rpx rgba(0, 0, 0, 0.1);
}

.submit-section button {
	width: 100%;
	height: 88rpx;
	line-height: 88rpx;
	font-size: 32rpx;
	border-radius: 8rpx;
}

/* 签名相关样式 */
.declaration-box {
	padding: 30rpx;
	background: #fff9e6;
	border-left: 6rpx solid #ff9800;
	border-radius: 8rpx;
	margin-bottom: 30rpx;
}

.declaration-text {
	font-size: 28rpx;
	line-height: 1.8;
	color: #333;
}

.signature-wrapper {
	margin-top: 30rpx;
}

.signature-wrapper .label {
	display: block;
	font-size: 28rpx;
	font-weight: bold;
	margin-bottom: 20rpx;
	color: #333;
}

.signature-canvas-box {
	background: #fff;
	border: 2rpx dashed #ccc;
	border-radius: 10rpx;
	padding: 20rpx;
}

.signature-canvas {
	background: #fafafa;
	border-radius: 8rpx;
	display: block;
}

.canvas-tips {
	text-align: center;
	font-size: 24rpx;
	color: #999;
	margin-top: 10rpx;
	padding: 10rpx;
}

/* 按钮组 */
.signature-buttons {
	display: flex;
	gap: 20rpx;
	margin-top: 20rpx;
}

.clear-signature-btn {
	flex: 1;
	height: 70rpx;
	line-height: 70rpx;
	background: #ff5722;
	color: #fff;
	border: none;
	border-radius: 8rpx;
	font-size: 28rpx;
}

.save-signature-btn {
	flex: 1;
	height: 70rpx;
	line-height: 70rpx;
	background: #4CAF50;
	color: #fff;
	border: none;
	border-radius: 8rpx;
	font-size: 28rpx;
}

/* 签名预览 */
.signature-preview-box {
	background: #fff;
	border: 2rpx solid #4CAF50;
	border-radius: 10rpx;
	padding: 20rpx;
}

.signature-image {
	width: 100%;
	min-height: 200rpx;
	max-height: 400rpx;
	border-radius: 8rpx;
	display: block;
}

/* 必填标记 */
.label.required::after {
	content: '*';
	color: #f56c6c;
	margin-left: 4rpx;
}

/* 禁用的picker */
.picker.disabled {
	color: #999;
	background-color: #f5f5f5;
}

/* 一寸照片上传 */
.photo-item {
	align-items: flex-start;
}

.photo-upload {
	width: 200rpx;
	height: 260rpx;
	border: 2rpx dashed #ccc;
	border-radius: 8rpx;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	background-color: #fafafa;
	overflow: hidden;
}

.photo-preview {
	width: 100%;
	height: 100%;
}

.photo-placeholder {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
}

.photo-icon {
	font-size: 60rpx;
	margin-bottom: 10rpx;
}

.photo-text {
	font-size: 24rpx;
	color: #999;
}
</style>

