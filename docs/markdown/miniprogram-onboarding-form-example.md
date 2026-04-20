# 小程序入职登记表表单示例

这是一个微信小程序入职登记表表单的示例代码。您需要根据您的微信小程序框架（如uni-app、Taro等）进行调整。

## 1. 页面结构（pages/onboarding-form/index.wxml）

```xml
<view class="container">
  <view class="form-section">
    <view class="section-title">基本信息</view>
    
    <!-- 登记日期 -->
    <view class="form-item">
      <text class="label">登记日期</text>
      <picker mode="date" value="{{formData.registration_date}}" bindchange="onDateChange">
        <view class="picker">{{formData.registration_date || '请选择日期'}}</view>
      </picker>
    </view>
    
    <!-- 姓名 -->
    <view class="form-item">
      <text class="label">姓名</text>
      <input type="text" placeholder="请输入姓名" value="{{formData.name}}" bindinput="onInput" data-field="name" />
    </view>
    
    <!-- 性别 -->
    <view class="form-item">
      <text class="label">性别</text>
      <radio-group bindchange="onGenderChange">
        <label><radio value="male" checked="{{formData.gender === 'male'}}" />男</label>
        <label><radio value="female" checked="{{formData.gender === 'female'}}" />女</label>
      </radio-group>
    </view>
    
    <!-- 民族 -->
    <view class="form-item">
      <text class="label">民族</text>
      <input type="text" placeholder="请输入民族" value="{{formData.ethnicity}}" bindinput="onInput" data-field="ethnicity" />
    </view>
    
    <!-- 政治面貌 -->
    <view class="form-item">
      <text class="label">政治面貌</text>
      <picker mode="selector" range="{{politicalStatusList}}" value="{{politicalStatusIndex}}" bindchange="onPoliticalStatusChange">
        <view class="picker">{{formData.political_status || '请选择'}}</view>
      </picker>
    </view>
    
    <!-- 籍贯 -->
    <view class="form-item">
      <text class="label">籍贯</text>
      <input type="text" placeholder="请输入籍贯" value="{{formData.place_of_origin}}" bindinput="onInput" data-field="place_of_origin" />
    </view>
    
    <!-- 出生年月 -->
    <view class="form-item">
      <text class="label">出生年月</text>
      <picker mode="date" fields="month" value="{{formData.birth_date}}" bindchange="onBirthDateChange">
        <view class="picker">{{formData.birth_date || '请选择'}}</view>
      </picker>
    </view>
    
    <!-- 身份证号码 -->
    <view class="form-item">
      <text class="label">身份证号码</text>
      <input type="idcard" placeholder="请输入身份证号码" value="{{formData.id_number}}" bindinput="onInput" data-field="id_number" maxlength="18" />
    </view>
    
    <!-- 现居住地 -->
    <view class="form-item">
      <text class="label">现居住地</text>
      <input type="text" placeholder="请输入现居住地" value="{{formData.current_residence}}" bindinput="onInput" data-field="current_residence" />
    </view>
    
    <!-- 户口所在地 -->
    <view class="form-item">
      <text class="label">户口所在地</text>
      <input type="text" placeholder="请输入户口所在地" value="{{formData.household_registration}}" bindinput="onInput" data-field="household_registration" />
    </view>
    
    <!-- 婚姻状况 -->
    <view class="form-item">
      <text class="label">婚姻状况</text>
      <picker mode="selector" range="{{maritalStatusList}}" value="{{maritalStatusIndex}}" bindchange="onMaritalStatusChange">
        <view class="picker">{{formData.marital_status || '请选择'}}</view>
      </picker>
    </view>
    
    <!-- 健康状况 -->
    <view class="form-item">
      <text class="label">健康状况</text>
      <input type="text" placeholder="请输入健康状况" value="{{formData.health_status}}" bindinput="onInput" data-field="health_status" />
    </view>
    
    <!-- 身高 -->
    <view class="form-item">
      <text class="label">身高 (cm)</text>
      <input type="digit" placeholder="请输入身高" value="{{formData.height}}" bindinput="onInput" data-field="height" />
    </view>
    
    <!-- 体重 -->
    <view class="form-item">
      <text class="label">体重 (kg)</text>
      <input type="digit" placeholder="请输入体重" value="{{formData.weight}}" bindinput="onInput" data-field="weight" />
    </view>
  </view>
  
  <view class="form-section">
    <view class="section-title">教育信息</view>
    
    <!-- 毕业学校 -->
    <view class="form-item">
      <text class="label">毕业学校</text>
      <input type="text" placeholder="请输入毕业学校" value="{{formData.graduated_school}}" bindinput="onInput" data-field="graduated_school" />
    </view>
    
    <!-- 毕业时间 -->
    <view class="form-item">
      <text class="label">毕业时间</text>
      <picker mode="date" fields="month" value="{{formData.graduation_date}}" bindchange="onGraduationDateChange">
        <view class="picker">{{formData.graduation_date || '请选择'}}</view>
      </picker>
    </view>
    
    <!-- 文化程度 -->
    <view class="form-item">
      <text class="label">文化程度</text>
      <picker mode="selector" range="{{educationLevelList}}" value="{{educationLevelIndex}}" bindchange="onEducationLevelChange">
        <view class="picker">{{formData.education_level || '请选择'}}</view>
      </picker>
    </view>
    
    <!-- 所学专业 -->
    <view class="form-item">
      <text class="label">所学专业</text>
      <input type="text" placeholder="请输入所学专业" value="{{formData.major}}" bindinput="onInput" data-field="major" />
    </view>
    
    <!-- 学位 -->
    <view class="form-item">
      <text class="label">学位</text>
      <input type="text" placeholder="请输入学位" value="{{formData.degree}}" bindinput="onInput" data-field="degree" />
    </view>
    
    <!-- 技术职称 -->
    <view class="form-item">
      <text class="label">技术职称</text>
      <input type="text" placeholder="请输入技术职称" value="{{formData.technical_title}}" bindinput="onInput" data-field="technical_title" />
    </view>
  </view>
  
  <!-- 学习简历 -->
  <view class="form-section">
    <view class="section-title">
      学习简历
      <text class="add-btn" bindtap="addEducationBackground">+ 添加</text>
    </view>
    <view class="list-item" wx:for="{{formData.education_background}}" wx:key="index">
      <view class="item-header">
        <text>第{{index + 1}}条</text>
        <text class="delete-btn" bindtap="deleteEducationBackground" data-index="{{index}}">删除</text>
      </view>
      <view class="form-item">
        <text class="label">起止时间</text>
        <input type="text" placeholder="例如：2010.09-2014.06" value="{{item.start_date}}-{{item.end_date}}" bindinput="onEducationDateChange" data-index="{{index}}" />
      </view>
      <view class="form-item">
        <text class="label">在何学校学习</text>
        <input type="text" placeholder="请输入学校名称" value="{{item.school}}" bindinput="onEducationInput" data-index="{{index}}" data-field="school" />
      </view>
      <view class="form-item">
        <text class="label">学习层次</text>
        <input type="text" placeholder="请输入学习层次" value="{{item.level}}" bindinput="onEducationInput" data-index="{{index}}" data-field="level" />
      </view>
      <view class="form-item">
        <text class="label">证明人</text>
        <input type="text" placeholder="请输入证明人" value="{{item.certifier}}" bindinput="onEducationInput" data-index="{{index}}" data-field="certifier" />
      </view>
    </view>
  </view>
  
  <!-- 工作经历 -->
  <view class="form-section">
    <view class="section-title">
      工作经历
      <text class="add-btn" bindtap="addWorkExperience">+ 添加</text>
    </view>
    <view class="list-item" wx:for="{{formData.work_experience}}" wx:key="index">
      <view class="item-header">
        <text>第{{index + 1}}条</text>
        <text class="delete-btn" bindtap="deleteWorkExperience" data-index="{{index}}">删除</text>
      </view>
      <view class="form-item">
        <text class="label">起止时间</text>
        <input type="text" placeholder="例如：2014.07-2018.06" value="{{item.start_date}}-{{item.end_date}}" bindinput="onWorkDateChange" data-index="{{index}}" />
      </view>
      <view class="form-item">
        <text class="label">在何工作单位</text>
        <input type="text" placeholder="请输入工作单位" value="{{item.employer}}" bindinput="onWorkInput" data-index="{{index}}" data-field="employer" />
      </view>
      <view class="form-item">
        <text class="label">主要工作内容</text>
        <textarea placeholder="请输入主要工作内容" value="{{item.job_content}}" bindinput="onWorkInput" data-index="{{index}}" data-field="job_content" />
      </view>
      <view class="form-item">
        <text class="label">证明人</text>
        <input type="text" placeholder="请输入证明人" value="{{item.certifier}}" bindinput="onWorkInput" data-index="{{index}}" data-field="certifier" />
      </view>
    </view>
  </view>
  
  <!-- 家庭情况 -->
  <view class="form-section">
    <view class="section-title">
      家庭情况
      <text class="add-btn" bindtap="addFamilyInfo">+ 添加</text>
    </view>
    <view class="list-item" wx:for="{{formData.family_info}}" wx:key="index">
      <view class="item-header">
        <text>第{{index + 1}}条</text>
        <text class="delete-btn" bindtap="deleteFamilyInfo" data-index="{{index}}">删除</text>
      </view>
      <view class="form-item">
        <text class="label">姓名</text>
        <input type="text" placeholder="请输入姓名" value="{{item.name}}" bindinput="onFamilyInput" data-index="{{index}}" data-field="name" />
      </view>
      <view class="form-item">
        <text class="label">关系</text>
        <input type="text" placeholder="例如：父亲、母亲、配偶" value="{{item.relationship}}" bindinput="onFamilyInput" data-index="{{index}}" data-field="relationship" />
      </view>
      <view class="form-item">
        <text class="label">所在单位</text>
        <input type="text" placeholder="请输入所在单位" value="{{item.employer}}" bindinput="onFamilyInput" data-index="{{index}}" data-field="employer" />
      </view>
      <view class="form-item">
        <text class="label">联系电话</text>
        <input type="number" placeholder="请输入联系电话" value="{{item.phone}}" bindinput="onFamilyInput" data-index="{{index}}" data-field="phone" />
      </view>
    </view>
  </view>
  
  <!-- 就业信息 -->
  <view class="form-section">
    <view class="section-title">就业信息</view>
    
    <!-- 岗位 -->
    <view class="form-item">
      <text class="label">岗位</text>
      <input type="text" placeholder="请输入岗位" value="{{formData.position}}" bindinput="onInput" data-field="position" />
    </view>
    
    <!-- 求职地区 -->
    <view class="form-item">
      <text class="label">求职地区</text>
      <input type="text" placeholder="请输入求职地区" value="{{formData.desired_location}}" bindinput="onInput" data-field="desired_location" />
    </view>
    
    <!-- 是否服从调配 -->
    <view class="form-item">
      <text class="label">是否服从调配</text>
      <switch checked="{{formData.accept_assignment}}" bindchange="onAcceptAssignmentChange" />
    </view>
    
    <!-- 联系地址 -->
    <view class="form-item">
      <text class="label">联系地址</text>
      <input type="text" placeholder="请输入联系地址" value="{{formData.contact_address}}" bindinput="onInput" data-field="contact_address" />
    </view>
    
    <!-- 联系电话 -->
    <view class="form-item">
      <text class="label">联系电话</text>
      <input type="number" placeholder="请输入联系电话" value="{{formData.contact_phone}}" bindinput="onInput" data-field="contact_phone" />
    </view>
  </view>
  
  <!-- 备注 -->
  <view class="form-section">
    <view class="section-title">备注</view>
    <textarea placeholder="请输入备注信息" value="{{formData.remarks}}" bindinput="onInput" data-field="remarks" />
  </view>
  
  <!-- 声明和签名 -->
  <view class="form-section">
    <view class="section-title">声明和签名</view>
    <view class="declaration">
      <text>本人保证以上所填写的信息真实可靠，如有虚假，本人愿承担一切法律责任。</text>
    </view>
    <view class="form-item">
      <checkbox-group bindchange="onDeclarationChange">
        <label>
          <checkbox value="agree" checked="{{formData.declaration_agreed}}" />我已阅读并同意上述声明
        </label>
      </checkbox-group>
    </view>
    <view class="form-item">
      <text class="label">本人签名</text>
      <input type="text" placeholder="请输入姓名" value="{{formData.signature}}" bindinput="onInput" data-field="signature" />
    </view>
  </view>
  
  <!-- 提交按钮 -->
  <view class="submit-section">
    <button type="primary" bindtap="submitForm" loading="{{submitting}}">提交</button>
  </view>
</view>
```

## 2. 页面逻辑（pages/onboarding-form/index.js）

```javascript
const app = getApp()

Page({
  data: {
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
      declaration_agreed: false,
      signature: '',
      education_background: [],
      work_experience: [],
      family_info: []
    },
    politicalStatusList: ['中共党员', '中共预备党员', '共青团员', '群众', '其他'],
    politicalStatusIndex: -1,
    maritalStatusList: ['未婚', '已婚', '离异', '丧偶'],
    maritalStatusIndex: -1,
    educationLevelList: ['小学', '初中', '高中', '中专', '大专', '本科', '硕士', '博士'],
    educationLevelIndex: -1,
    submitting: false
  },
  
  onLoad() {
    // 设置默认登记日期为今天
    const today = new Date()
    const dateStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`
    this.setData({
      'formData.registration_date': dateStr
    })
    
    // 加载已保存的数据
    this.loadSavedData()
  },
  
  // 加载已保存的数据
  async loadSavedData() {
    try {
      const token = wx.getStorageSync('token')
      const res = await wx.request({
        url: app.globalData.apiBaseUrl + '/api/mini/onboarding-form',
        method: 'GET',
        header: {
          'Authorization': 'Bearer ' + token
        }
      })
      
      if (res.data.success && res.data.data) {
        this.setData({
          formData: res.data.data
        })
      }
    } catch (error) {
      console.error('加载数据失败:', error)
    }
  },
  
  // 通用输入处理
  onInput(e) {
    const field = e.currentTarget.dataset.field
    const value = e.detail.value
    this.setData({
      [`formData.${field}`]: value
    })
  },
  
  // 日期选择
  onDateChange(e) {
    this.setData({
      'formData.registration_date': e.detail.value
    })
  },
  
  // 性别选择
  onGenderChange(e) {
    this.setData({
      'formData.gender': e.detail.value
    })
  },
  
  // 政治面貌选择
  onPoliticalStatusChange(e) {
    const index = e.detail.value
    this.setData({
      politicalStatusIndex: index,
      'formData.political_status': this.data.politicalStatusList[index]
    })
  },
  
  // 出生日期选择
  onBirthDateChange(e) {
    this.setData({
      'formData.birth_date': e.detail.value
    })
  },
  
  // 婚姻状况选择
  onMaritalStatusChange(e) {
    const index = e.detail.value
    const statusMap = {
      0: 'single',
      1: 'married',
      2: 'divorced',
      3: 'widowed'
    }
    this.setData({
      maritalStatusIndex: index,
      'formData.marital_status': statusMap[index]
    })
  },
  
  // 毕业时间选择
  onGraduationDateChange(e) {
    this.setData({
      'formData.graduation_date': e.detail.value
    })
  },
  
  // 文化程度选择
  onEducationLevelChange(e) {
    const index = e.detail.value
    this.setData({
      educationLevelIndex: index,
      'formData.education_level': this.data.educationLevelList[index]
    })
  },
  
  // 是否服从调配
  onAcceptAssignmentChange(e) {
    this.setData({
      'formData.accept_assignment': e.detail.value
    })
  },
  
  // 声明同意
  onDeclarationChange(e) {
    this.setData({
      'formData.declaration_agreed': e.detail.value.includes('agree')
    })
  },
  
  // 添加学习简历
  addEducationBackground() {
    const educationBackground = this.data.formData.education_background
    educationBackground.push({
      start_date: '',
      end_date: '',
      school: '',
      level: '',
      certifier: ''
    })
    this.setData({
      'formData.education_background': educationBackground
    })
  },
  
  // 删除学习简历
  deleteEducationBackground(e) {
    const index = e.currentTarget.dataset.index
    const educationBackground = this.data.formData.education_background
    educationBackground.splice(index, 1)
    this.setData({
      'formData.education_background': educationBackground
    })
  },
  
  // 学习简历输入
  onEducationInput(e) {
    const index = e.currentTarget.dataset.index
    const field = e.currentTarget.dataset.field
    const value = e.detail.value
    this.setData({
      [`formData.education_background[${index}].${field}`]: value
    })
  },
  
  // 学习简历日期输入
  onEducationDateChange(e) {
    const index = e.currentTarget.dataset.index
    const value = e.detail.value
    const dates = value.split('-')
    this.setData({
      [`formData.education_background[${index}].start_date`]: dates[0] || '',
      [`formData.education_background[${index}].end_date`]: dates[1] || ''
    })
  },
  
  // 添加工作经历
  addWorkExperience() {
    const workExperience = this.data.formData.work_experience
    workExperience.push({
      start_date: '',
      end_date: '',
      employer: '',
      job_content: '',
      certifier: ''
    })
    this.setData({
      'formData.work_experience': workExperience
    })
  },
  
  // 删除工作经历
  deleteWorkExperience(e) {
    const index = e.currentTarget.dataset.index
    const workExperience = this.data.formData.work_experience
    workExperience.splice(index, 1)
    this.setData({
      'formData.work_experience': workExperience
    })
  },
  
  // 工作经历输入
  onWorkInput(e) {
    const index = e.currentTarget.dataset.index
    const field = e.currentTarget.dataset.field
    const value = e.detail.value
    this.setData({
      [`formData.work_experience[${index}].${field}`]: value
    })
  },
  
  // 工作经历日期输入
  onWorkDateChange(e) {
    const index = e.currentTarget.dataset.index
    const value = e.detail.value
    const dates = value.split('-')
    this.setData({
      [`formData.work_experience[${index}].start_date`]: dates[0] || '',
      [`formData.work_experience[${index}].end_date`]: dates[1] || ''
    })
  },
  
  // 添加家庭情况
  addFamilyInfo() {
    const familyInfo = this.data.formData.family_info
    familyInfo.push({
      name: '',
      relationship: '',
      employer: '',
      phone: ''
    })
    this.setData({
      'formData.family_info': familyInfo
    })
  },
  
  // 删除家庭情况
  deleteFamilyInfo(e) {
    const index = e.currentTarget.dataset.index
    const familyInfo = this.data.formData.family_info
    familyInfo.splice(index, 1)
    this.setData({
      'formData.family_info': familyInfo
    })
  },
  
  // 家庭情况输入
  onFamilyInput(e) {
    const index = e.currentTarget.dataset.index
    const field = e.currentTarget.dataset.field
    const value = e.detail.value
    this.setData({
      [`formData.family_info[${index}].${field}`]: value
    })
  },
  
  // 提交表单
  async submitForm() {
    // 验证必填字段
    if (!this.data.formData.registration_date) {
      wx.showToast({
        title: '请选择登记日期',
        icon: 'none'
      })
      return
    }
    
    if (!this.data.formData.name) {
      wx.showToast({
        title: '请输入姓名',
        icon: 'none'
      })
      return
    }
    
    if (!this.data.formData.id_number) {
      wx.showToast({
        title: '请输入身份证号码',
        icon: 'none'
      })
      return
    }
    
    if (!this.data.formData.declaration_agreed) {
      wx.showToast({
        title: '请同意声明',
        icon: 'none'
      })
      return
    }
    
    this.setData({
      submitting: true
    })
    
    try {
      const token = wx.getStorageSync('token')
      const res = await wx.request({
        url: app.globalData.apiBaseUrl + '/api/mini/onboarding-form',
        method: 'POST',
        header: {
          'Authorization': 'Bearer ' + token,
          'Content-Type': 'application/json'
        },
        data: this.data.formData
      })
      
      if (res.data.success) {
        wx.showToast({
          title: '提交成功',
          icon: 'success'
        })
        
        // 返回上一页
        setTimeout(() => {
          wx.navigateBack()
        }, 1500)
      } else {
        wx.showToast({
          title: res.data.message || '提交失败',
          icon: 'none'
        })
      }
    } catch (error) {
      console.error('提交失败:', error)
      wx.showToast({
        title: '提交失败，请重试',
        icon: 'none'
      })
    } finally {
      this.setData({
        submitting: false
      })
    }
  }
})
```

## 3. 页面样式（pages/onboarding-form/index.wxss）

```css
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
}

.add-btn {
  font-size: 28rpx;
  color: #409EFF;
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

.picker {
  border: 1rpx solid #ddd;
  border-radius: 5rpx;
  padding: 0 20rpx;
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

.declaration {
  background-color: #f0f9ff;
  padding: 20rpx;
  border-radius: 5rpx;
  margin-bottom: 20rpx;
  font-size: 26rpx;
  color: #666;
  line-height: 1.6;
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
}
```

## 4. 使用说明

1. 将上述代码复制到您的小程序项目中
2. 在 `app.json` 中注册页面路由
3. 确保 `app.js` 中配置了 `globalData.apiBaseUrl` 为后端API地址
4. 确保用户已登录并获取了token
5. 在小程序的"我的"页面添加"入职登记表"入口，跳转到此页面

## 5. API接口说明

### 获取入职登记表
- **URL**: `/api/mini/onboarding-form`
- **Method**: GET
- **Headers**: `Authorization: Bearer {token}`

### 提交入职登记表
- **URL**: `/api/mini/onboarding-form`
- **Method**: POST
- **Headers**: `Authorization: Bearer {token}`
- **Body**: 表单数据（JSON格式）

## 注意事项

1. 此示例代码适用于原生微信小程序，如使用uni-app、Taro等框架，需要相应调整
2. 日期格式需要根据后端要求进行调整
3. 表单验证可以根据实际需求加强
4. 多行数据（学习简历、工作经历、家庭情况）的日期输入可以优化为日期选择器

