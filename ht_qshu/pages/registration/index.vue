<template>
	<view class="container">
		<!-- 填表说明 -->
		<view class="form-section notice-section">
			<view class="section-title">填表说明</view>
			<view class="notice-text">
				此表内容由应聘者本人填写，公司人力资源部配合说明。填写要求字迹清楚、工整，内容较多时，可另纸接续。填写人须保证填写内容的真实性并同意作为公司进行背景调查与核实的依据。一经发现填写内容与事实不符，公司将根据情节轻重给予记过或开除的处分并解除劳动合同。
			</view>
		</view>
		
		<!-- 头部信息 -->
		<view class="form-section">
			<view class="section-title">
				<text>基本信息</text>
				<view class="fill-sample-btn" @click="fillSampleData">
					<text class="icon">📝</text>
					<text class="text">示例数据</text>
				</view>
			</view>
			
			<view class="form-item">
				<text class="label">填表日期</text>
				<picker mode="date" :value="formatDateForPicker(formData.fill_date)" @change="onFillDateChange">
					<view class="picker">{{ formatDateDisplay(formData.fill_date) || '请选择日期' }}</view>
				</picker>
			</view>
			
			<view class="form-item">
				<text class="label">入职职位</text>
				<input type="text" placeholder="请输入入职职位" v-model="formData.entry_position" />
			</view>
			
			<view class="form-item">
				<text class="label">入职日期</text>
				<picker mode="date" :value="formatDateForPicker(formData.entry_date)" @change="onEntryDateChange">
					<view class="picker">{{ formatDateDisplay(formData.entry_date) || '请选择日期' }}</view>
				</picker>
			</view>
			
			<view class="form-item">
				<text class="label">部门</text>
				<input type="text" placeholder="请输入部门" v-model="formData.department" />
			</view>
			
			<view class="form-item">
				<text class="label">职务</text>
				<input type="text" placeholder="请输入职务" v-model="formData.job_title" />
			</view>

			<view class="form-item">
				<text class="label">公积金账户</text>
				<input type="text" placeholder="请输入公积金账户" v-model="formData.housing_fund_account" />
			</view>
			
			<view class="form-item">
				<text class="label">银行账号</text>
				<input type="text" placeholder="请输入银行账号" v-model="formData.bank_account" />
			</view>
			
			<view class="form-item">
				<text class="label">开户支行名称</text>
				<input type="text" placeholder="请输入开户支行名称" v-model="formData.bank_name" />
			</view>
		</view>
		
		<!-- 一、个人资料 -->
		<view class="form-section">
			<view class="section-title">一、个人资料</view>
			
			<view class="form-item">
				<text class="label required">姓名</text>
				<input type="text" placeholder="请输入姓名" v-model="formData.name" />
			</view>
			
			<view class="form-item">
				<text class="label">英文名</text>
				<input type="text" placeholder="请输入英文名" v-model="formData.english_name" />
			</view>
			
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
			
			<view class="form-item">
				<text class="label">身高(cm)</text>
				<input type="digit" placeholder="请输入身高" v-model="formData.height" />
			</view>
			
			<view class="form-item">
				<text class="label required">身份证号码</text>
				<input type="idcard" placeholder="请输入身份证号码" v-model="formData.id_number" maxlength="18" @blur="onIdNumberChange" />
			</view>
			
			<view class="form-item">
				<text class="label">出生日期</text>
				<view class="picker disabled">{{ formatDateDisplay(formData.birth_date) || '输入身份证后自动获取' }}</view>
			</view>

			<view class="form-item">
				<text class="label">政治面貌</text>
				<picker :range="politicalOptions" :value="politicalIndex" @change="onPoliticalChange">
					<view class="picker">{{ formData.political_status || '请选择政治面貌' }}</view>
				</picker>
			</view>
			
			<view class="form-item">
				<text class="label">文化程度</text>
				<picker :range="educationOptions" :value="educationIndex" @change="onEducationChange">
					<view class="picker">{{ formData.education_level || '请选择文化程度' }}</view>
				</picker>
			</view>
			
			<view class="form-item">
				<text class="label">学历性质</text>
				<radio-group @change="onEducationTypeChange">
					<label class="radio-label">
						<radio value="统招" :checked="formData.education_type === '统招'" />统招
					</label>
					<label class="radio-label">
						<radio value="非统招" :checked="formData.education_type === '非统招'" />非统招
					</label>
				</radio-group>
			</view>
			
			<view class="form-item">
				<text class="label">籍贯</text>
				<picker mode="region" :value="nativePlaceArray" @change="onNativePlaceChange">
					<view class="picker">{{ formData.native_place || '请选择籍贯' }}</view>
				</picker>
			</view>
			
			<view class="form-item">
				<text class="label">婚姻状况</text>
				<picker :range="maritalOptions" :value="maritalIndex" @change="onMaritalChange">
					<view class="picker">{{ getMaritalText(formData.marital_status) || '请选择婚姻状况' }}</view>
				</picker>
			</view>
			
			<view class="form-item">
				<text class="label">是否有子女</text>
				<picker :range="childrenOptions" :value="childrenIndex" @change="onChildrenChange">
					<view class="picker">{{ formData.has_children || '请选择' }}</view>
				</picker>
			</view>
			
			<view class="form-item">
				<text class="label">户口状态</text>
				<picker :range="householdOptions" :value="householdIndex" @change="onHouseholdChange">
					<view class="picker">{{ getHouseholdText(formData.household_type) || '请选择户口状态' }}</view>
				</picker>
			</view>
			
			<view class="form-item">
				<text class="label">现居住地址</text>
				<input type="text" placeholder="请输入现居住地址" v-model="formData.current_address" />
			</view>
			
			<view class="form-item">
				<text class="label">邮编</text>
				<input type="number" placeholder="请输入邮编" v-model="formData.postal_code" maxlength="6" />
			</view>
			
			<view class="form-item">
				<text class="label">户口地址</text>
				<input type="text" placeholder="请输入户口地址" v-model="formData.household_address" />
			</view>
			
			<view class="form-item">
				<text class="label">联系电话</text>
				<input type="number" placeholder="请输入联系电话" v-model="formData.contact_phone" />
			</view>
			
			<view class="form-item">
				<text class="label">文书送达地址</text>
				<input type="text" placeholder="请输入文书送达地址" v-model="formData.document_address" />
			</view>
			
			<view class="form-item">
				<text class="label">残疾证等级</text>
				<input type="text" placeholder="如有请填写等级，无则留空" v-model="formData.disability_level" />
			</view>
		</view>

		<!-- 二、个人技能 -->
		<view class="form-section">
			<view class="section-title">二、个人技能</view>
			
			<view class="form-item">
				<text class="label">英语水平</text>
				<view class="checkbox-group">
					<label v-for="item in englishOptions" :key="item" class="checkbox-label">
						<checkbox :value="item" :checked="formData.language_skills && formData.language_skills.includes(item)" @tap="toggleLanguageSkill(item)" />
						<text>{{ item }}</text>
					</label>
				</view>
			</view>
			
			<view class="form-item">
				<text class="label">工程证书</text>
				<view class="checkbox-group">
					<label v-for="item in engineeringOptions" :key="item" class="checkbox-label">
						<checkbox :value="item" :checked="formData.engineering_skills && formData.engineering_skills.includes(item)" @tap="toggleEngineeringSkill(item)" />
						<text>{{ item }}</text>
					</label>
				</view>
				<input type="text" placeholder="其他工程证书" v-model="formData.engineering_other" style="margin-top: 10rpx;" />
			</view>
			
			<view class="form-item">
				<text class="label">职称</text>
				<picker :range="titleOptions" :value="titleIndex" @change="onTitleChange">
					<view class="picker">{{ formData.professional_title || '请选择职称' }}</view>
				</picker>
			</view>
			
			<view class="form-item">
				<text class="label">兴趣爱好</text>
				<view class="checkbox-group">
					<label v-for="item in hobbyOptions" :key="item" class="checkbox-label">
						<checkbox :value="item" :checked="formData.hobbies && formData.hobbies.includes(item)" @tap="toggleHobby(item)" />
						<text>{{ item }}</text>
					</label>
				</view>
				<input type="text" placeholder="其他兴趣爱好" v-model="formData.hobby_other" style="margin-top: 10rpx;" />
			</view>
			
			<view class="form-item">
				<text class="label">其他技能</text>
				<textarea placeholder="请输入其他技能" v-model="formData.other_skills" />
			</view>
		</view>
		
		<!-- 三、教育情况 -->
		<view class="form-section">
			<view class="section-title">
				三、教育情况
				<text class="add-btn" @click="addEducation">+ 添加</text>
			</view>
			<view class="list-item" v-for="(item, index) in formData.education_history" :key="index">
				<view class="item-header">
					<text>第{{ index + 1 }}条</text>
					<text class="delete-btn" @click="deleteEducation(index)">删除</text>
				</view>
				<view class="form-item">
					<text class="label">起止时间</text>
					<input type="text" placeholder="例如：2010.09-2014.06" v-model="item.date_range" />
				</view>
				<view class="form-item">
					<text class="label">学校及专业</text>
					<input type="text" placeholder="请输入学校及专业" v-model="item.school_major" />
				</view>
				<view class="form-item">
					<text class="label">所获证书</text>
					<input type="text" placeholder="请输入所获证书" v-model="item.certificate" />
				</view>
			</view>
		</view>

		<!-- 四、工作履历 -->
		<view class="form-section">
			<view class="section-title">
				四、工作履历
				<text class="add-btn" @click="addWorkHistory">+ 添加</text>
			</view>
			<view class="list-item" v-for="(item, index) in formData.work_history" :key="index">
				<view class="item-header">
					<text>第{{ index + 1 }}条</text>
					<text class="delete-btn" @click="deleteWorkHistory(index)">删除</text>
				</view>
				<view class="form-item">
					<text class="label">起止时间</text>
					<input type="text" placeholder="例如：2014.07-2018.06" v-model="item.date_range" />
				</view>
				<view class="form-item">
					<text class="label">公司</text>
					<input type="text" placeholder="请输入公司名称" v-model="item.company" />
				</view>
				<view class="form-item">
					<text class="label">职位</text>
					<input type="text" placeholder="请输入职位" v-model="item.position" />
				</view>
				<view class="form-item">
					<text class="label">薪酬</text>
					<input type="text" placeholder="请输入薪酬" v-model="item.salary" />
				</view>
				<view class="form-item">
					<text class="label">离职原因</text>
					<input type="text" placeholder="请输入离职原因" v-model="item.leave_reason" />
				</view>
			</view>
			
			<view class="form-item" style="margin-top: 20rpx;">
				<text class="label">前单位名称（背调用）</text>
				<input type="text" placeholder="请输入前单位名称" v-model="formData.reference_company" />
			</view>
			<view class="form-item">
				<text class="label">联系人职位/电话</text>
				<input type="text" placeholder="请输入联系人职位和电话" v-model="formData.reference_contact" />
			</view>
		</view>
		
		<!-- 五、奖罚情况 -->
		<view class="form-section">
			<view class="section-title">五、奖罚情况</view>
			<textarea placeholder="何时何地担任何职务获得何种内容的奖励；或受到何种处罚，有否证明，请简述" v-model="formData.rewards_punishments" class="textarea" />
		</view>
		
		<!-- 六、家庭情况 -->
		<view class="form-section">
			<view class="section-title">
				六、家庭情况
				<text class="add-btn" @click="addFamilyMember">+ 添加</text>
			</view>
			<view class="list-item" v-for="(item, index) in formData.family_members" :key="index">
				<view class="item-header">
					<text>第{{ index + 1 }}条</text>
					<text class="delete-btn" @click="deleteFamilyMember(index)">删除</text>
				</view>
				<view class="form-item">
					<text class="label">姓名</text>
					<input type="text" placeholder="请输入姓名" v-model="item.name" />
				</view>
				<view class="form-item">
					<text class="label">关系</text>
					<input type="text" placeholder="例如：父亲、母亲、配偶" v-model="item.relation" />
				</view>
				<view class="form-item">
					<text class="label">年龄</text>
					<input type="number" placeholder="请输入年龄" v-model="item.age" />
				</view>
				<view class="form-item">
					<text class="label">工作单位</text>
					<input type="text" placeholder="请输入工作单位" v-model="item.employer" />
				</view>
				<view class="form-item">
					<text class="label">电话</text>
					<input type="number" placeholder="请输入电话" v-model="item.phone" />
				</view>
			</view>
		</view>

		<!-- 七、紧急联系方式 -->
		<view class="form-section">
			<view class="section-title">七、紧急联系方式</view>
			
			<view class="sub-title">第一联系人</view>
			<view class="form-item">
				<text class="label">姓名</text>
				<input type="text" placeholder="请输入姓名" v-model="formData.emergency_contact1_name" />
			</view>
			<view class="form-item">
				<text class="label">与己关系</text>
				<input type="text" placeholder="请输入关系" v-model="formData.emergency_contact1_relation" />
			</view>
			<view class="form-item">
				<text class="label">联系电话</text>
				<input type="number" placeholder="请输入电话" v-model="formData.emergency_contact1_phone" />
			</view>
			
			<view class="sub-title">第二联系人</view>
			<view class="form-item">
				<text class="label">姓名</text>
				<input type="text" placeholder="请输入姓名" v-model="formData.emergency_contact2_name" />
			</view>
			<view class="form-item">
				<text class="label">与己关系</text>
				<input type="text" placeholder="请输入关系" v-model="formData.emergency_contact2_relation" />
			</view>
			<view class="form-item">
				<text class="label">联系电话</text>
				<input type="number" placeholder="请输入电话" v-model="formData.emergency_contact2_phone" />
			</view>
		</view>
		
		<!-- 八、其他情况 -->
		<view class="form-section">
			<view class="section-title">八、其他情况</view>
			
			<view class="form-item">
				<text class="label">精神病</text>
				<radio-group @change="e => formData.mental_illness = e.detail.value">
					<label class="radio-label"><radio value="无" :checked="formData.mental_illness === '无'" />无</label>
					<label class="radio-label"><radio value="有" :checked="formData.mental_illness === '有'" />有</label>
				</radio-group>
			</view>
			<view class="form-item" v-if="formData.mental_illness === '有'">
				<text class="label">详情</text>
				<input type="text" placeholder="请注明详情" v-model="formData.mental_illness_detail" />
			</view>
			
			<view class="form-item">
				<text class="label">其他疾病</text>
				<radio-group @change="e => formData.other_illness = e.detail.value">
					<label class="radio-label"><radio value="无" :checked="formData.other_illness === '无'" />无</label>
					<label class="radio-label"><radio value="有" :checked="formData.other_illness === '有'" />有</label>
				</radio-group>
			</view>
			<view class="form-item" v-if="formData.other_illness === '有'">
				<text class="label">详情</text>
				<input type="text" placeholder="请注明详情" v-model="formData.other_illness_detail" />
			</view>
			
			<view class="form-item">
				<text class="label">最近6个月住院记录</text>
				<radio-group @change="e => formData.hospitalized_recently = e.detail.value">
					<label class="radio-label"><radio value="无" :checked="formData.hospitalized_recently === '无'" />无</label>
					<label class="radio-label"><radio value="有" :checked="formData.hospitalized_recently === '有'" />有</label>
				</radio-group>
			</view>
			<view class="form-item" v-if="formData.hospitalized_recently === '有'">
				<text class="label">病因</text>
				<input type="text" placeholder="请输入病因" v-model="formData.hospitalized_reason" />
			</view>
			
			<view class="form-item">
				<text class="label">违法犯罪记录</text>
				<radio-group @change="e => formData.criminal_record = e.detail.value">
					<label class="radio-label"><radio value="无" :checked="formData.criminal_record === '无'" />无</label>
					<label class="radio-label"><radio value="有" :checked="formData.criminal_record === '有'" />有</label>
				</radio-group>
			</view>
			<view class="form-item" v-if="formData.criminal_record === '有'">
				<text class="label">时间</text>
				<input type="text" placeholder="请输入时间" v-model="formData.criminal_record_time" />
			</view>
			
			<view class="form-item">
				<text class="label">就业证件</text>
				<view class="checkbox-group">
					<label v-for="item in employmentDocOptions" :key="item" class="checkbox-label">
						<checkbox :value="item" :checked="formData.employment_documents && formData.employment_documents.includes(item)" @tap="toggleEmploymentDoc(item)" />
						<text>{{ item }}</text>
					</label>
				</view>
			</view>
		</view>

		<!-- 九、其他需要说明的情况 -->
		<view class="form-section">
			<view class="section-title">九、其他需要说明的情况</view>
			<textarea placeholder="个人对职业发展目标、需求、建议等阐述" v-model="formData.remarks" class="textarea" />
		</view>
		
		<!-- 十、其他需要核实的情况 -->
		<view class="form-section">
			<view class="section-title">十、其他需要核实的情况</view>
			
			<view class="form-item">
				<text class="label">您是否怀孕</text>
				<radio-group @change="e => formData.is_pregnant = e.detail.value">
					<label class="radio-label"><radio value="无" :checked="formData.is_pregnant === '无'" />无</label>
					<label class="radio-label"><radio value="有" :checked="formData.is_pregnant === '有'" />有</label>
				</radio-group>
			</view>
			<view class="form-item" v-if="formData.is_pregnant === '有'">
				<text class="label">详情</text>
				<input type="text" placeholder="请注明详情" v-model="formData.pregnant_detail" />
			</view>
			
			<view class="form-item">
				<text class="label">是否接受加班、出差</text>
				<radio-group @change="e => formData.accept_overtime = e.detail.value">
					<label class="radio-label"><radio value="接受" :checked="formData.accept_overtime === '接受'" />接受</label>
					<label class="radio-label"><radio value="不接受" :checked="formData.accept_overtime === '不接受'" />不接受</label>
				</radio-group>
			</view>
			
			<view class="form-item">
				<text class="label">是否需要提供住宿</text>
				<radio-group @change="e => formData.need_accommodation = e.detail.value">
					<label class="radio-label"><radio value="无" :checked="formData.need_accommodation === '无'" />无</label>
					<label class="radio-label"><radio value="有" :checked="formData.need_accommodation === '有'" />有</label>
				</radio-group>
			</view>
			<view class="form-item" v-if="formData.need_accommodation === '有'">
				<text class="label">详情</text>
				<input type="text" placeholder="请注明详情" v-model="formData.accommodation_detail" />
			</view>
			
			<view class="form-item">
				<text class="label">您是否有驾照</text>
				<radio-group @change="e => formData.has_driving_license = e.detail.value">
					<label class="radio-label"><radio value="无" :checked="formData.has_driving_license === '无'" />无</label>
					<label class="radio-label"><radio value="有" :checked="formData.has_driving_license === '有'" />有</label>
				</radio-group>
			</view>
			<view class="form-item" v-if="formData.has_driving_license === '有'">
				<text class="label">详情</text>
				<input type="text" placeholder="请注明驾照类型" v-model="formData.driving_license_detail" />
			</view>
		</view>
		
		<!-- 声明与签名 -->
		<view class="form-section">
			<view class="section-title">声明与签名</view>
			<view class="declaration-box">
				<text class="declaration-text">1. 本人保证以上填写资料与所提供的证明文件均属实，并且本人授权调查上述资料之真实性，如有虚报隐瞒愿接受立即解雇之处分，并承担所有责任。</text>
				<text class="declaration-text">2. 员工在职及离职两年内如果送达地址有变更，应当书面及时告知公司变更后的送达地址。如未做变更，公司如有书面文件或通知送达上述地址，即默认为送达，造成后果由本人自行承担。</text>
			</view>
			
			<SignatureCanvas 
				ref="signatureCanvas"
				label="申请人签名："
				canvas-id="registrationSignature"
				:value="formData.signature"
				@change="onSignatureChange"
			/>
			
			<view class="form-item">
				<text class="label">签名日期</text>
				<picker mode="date" :value="formatDateForPicker(formData.signature_date)" @change="onSignatureDateChange">
					<view class="picker">{{ formatDateDisplay(formData.signature_date) || '请选择日期' }}</view>
				</picker>
			</view>
		</view>
		
		<!-- 提交按钮 -->
		<view class="submit-section">
			<button type="primary" @click="submitForm" :loading="submitting" class="submit-btn">提交</button>
		</view>
	</view>
</template>


<script>
import SignatureCanvas from '@/components/SignatureCanvas.vue'
import { getMyRegistrationForm, submitRegistrationForm } from '@/api/registration.js'

export default {
	components: { SignatureCanvas },
	
	data() {
		return {
			formData: {
				// 头部信息
				fill_date: '',
				entry_position: '',
				entry_date: '',
				department: '',
				job_title: '',
				housing_fund_account: '',
				bank_account: '',
				bank_name: '',
				// 一、个人资料
				name: '',
				english_name: '',
				gender: 'male',
				height: '',
				birth_date: '',
				political_status: '',
				education_level: '',
				education_type: '',
				native_place: '',
				marital_status: '',
				has_children: '',
				id_number: '',
				household_type: '',
				current_address: '',
				postal_code: '',
				household_address: '',
				contact_phone: '',
				document_address: '',
				disability_level: '',
				// 二、个人技能
				language_skills: [],
				engineering_skills: [],
				engineering_other: '',
				professional_title: '',
				hobbies: [],
				hobby_other: '',
				other_skills: '',
				// 三、教育情况
				education_history: [],
				// 四、工作履历
				work_history: [],
				reference_company: '',
				reference_contact: '',
				// 五、奖罚情况
				rewards_punishments: '',
				// 六、家庭情况
				family_members: [],
				// 七、紧急联系方式
				emergency_contact1_name: '',
				emergency_contact1_relation: '',
				emergency_contact1_phone: '',
				emergency_contact2_name: '',
				emergency_contact2_relation: '',
				emergency_contact2_phone: '',
				// 八、其他情况
				mental_illness: '无',
				mental_illness_detail: '',
				other_illness: '无',
				other_illness_detail: '',
				hospitalized_recently: '无',
				hospitalized_reason: '',
				criminal_record: '无',
				criminal_record_time: '',
				employment_documents: [],
				// 九、其他需要说明的情况
				remarks: '',
				// 十、其他需要核实的情况
				is_pregnant: '无',
				pregnant_detail: '',
				accept_overtime: '接受',
				need_accommodation: '无',
				accommodation_detail: '',
				has_driving_license: '无',
				driving_license_detail: '',
				// 签名
				signature: '',
				signaturePath: '',
				signature_date: ''
			},
			submitting: false,
			// 选项数组
			politicalOptions: ['群众', '中共党员', '中共预备党员', '共青团员', '民革党员', '民盟盟员', '民建会员', '民进会员', '农工党党员', '致公党党员', '九三学社社员', '台盟盟员', '无党派人士'],
			politicalIndex: 0,
			educationOptions: ['小学', '初中', '高中', '中专', '大专', '本科', '硕士', '博士'],
			educationIndex: 0,
			maritalOptions: ['未婚', '已婚', '离婚'],
			maritalIndex: 0,
			childrenOptions: ['无', '女孩', '男孩', '女孩和男孩'],
			childrenIndex: 0,
			householdOptions: ['城镇', '非城镇'],
			householdIndex: 0,
			englishOptions: ['四级', '六级', '托福', '雅思'],
			engineeringOptions: ['电工证', '高压证'],
			titleOptions: ['无', '初级', '中级', '高级'],
			titleIndex: 0,
			hobbyOptions: ['唱歌', '棋类', '球类'],
			employmentDocOptions: ['劳动手册', '离职证明', '应届毕业', '下岗/协保证明'],
			nativePlaceArray: []
		}
	},

	onLoad() {
		// 设置默认日期为今天
		const today = new Date()
		const dateStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`
		this.formData.fill_date = dateStr
		this.formData.signature_date = dateStr
		
		// 加载已保存的数据
		this.loadSavedData()
	},
	
	methods: {
		// 加载已保存的数据
		async loadSavedData() {
			try {
				const res = await getMyRegistrationForm()
				if (res.success && res.data) {
					const data = res.data
					// 处理签名（兼容新旧格式）
					if (data.signature) {
						this.formData.signature = data.signature
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
					}
					// 复制其他数据
					const { signature, ...otherData } = data
					this.formData = { ...this.formData, ...otherData }
				}
			} catch (error) {
				console.error('加载数据失败:', error)
			}
		},
		
		// 日期格式化
		formatDateForPicker(dateStr) {
			if (!dateStr) return ''
			if (dateStr.includes('T')) return dateStr.split('T')[0]
			return dateStr
		},
		
		formatDateDisplay(dateStr) {
			if (!dateStr) return ''
			if (dateStr.includes('T')) return dateStr.split('T')[0]
			return dateStr
		},
		
		// 各种选择器变化处理
		onFillDateChange(e) { this.formData.fill_date = e.detail.value },
		onEntryDateChange(e) { this.formData.entry_date = e.detail.value },
		onGenderChange(e) { this.formData.gender = e.detail.value },
		onPoliticalChange(e) {
			this.politicalIndex = e.detail.value
			this.formData.political_status = this.politicalOptions[e.detail.value]
		},
		onEducationChange(e) {
			this.educationIndex = e.detail.value
			this.formData.education_level = this.educationOptions[e.detail.value]
		},
		onEducationTypeChange(e) {
			this.formData.education_type = e.detail.value
		},
		onNativePlaceChange(e) {
			this.nativePlaceArray = e.detail.value
			this.formData.native_place = e.detail.value.join('')
		},
		onMaritalChange(e) {
			this.maritalIndex = e.detail.value
			this.formData.marital_status = ['single', 'married', 'divorced'][e.detail.value]
		},
		onChildrenChange(e) {
			this.childrenIndex = e.detail.value
			this.formData.has_children = this.childrenOptions[e.detail.value]
		},
		onHouseholdChange(e) {
			this.householdIndex = e.detail.value
			this.formData.household_type = ['urban', 'rural'][e.detail.value]
		},
		onTitleChange(e) {
			this.titleIndex = e.detail.value
			this.formData.professional_title = this.titleOptions[e.detail.value]
		},
		onSignatureDateChange(e) { this.formData.signature_date = e.detail.value },
		
		getMaritalText(val) {
			const map = { single: '未婚', married: '已婚', divorced: '离婚' }
			return map[val] || val
		},
		getHouseholdText(val) {
			const map = { urban: '城镇', rural: '非城镇' }
			return map[val] || val
		},

		// 身份证号码变化
		onIdNumberChange() {
			const idNumber = this.formData.id_number
			if (!idNumber || idNumber.length !== 18) return
			if (!this.validateIdNumber(idNumber)) {
				uni.showToast({ title: '请输入正确的身份证号码', icon: 'none' })
				return
			}
			// 提取出生日期
			const birthYear = idNumber.substring(6, 10)
			const birthMonth = idNumber.substring(10, 12)
			const birthDay = idNumber.substring(12, 14)
			this.formData.birth_date = `${birthYear}-${birthMonth}-${birthDay}`
			// 提取性别
			const genderCode = parseInt(idNumber.substring(16, 17))
			this.formData.gender = genderCode % 2 === 1 ? 'male' : 'female'
		},
		
		validateIdNumber(idNumber) {
			return !!idNumber && idNumber.length === 18
		},
		
		// 多选切换
		toggleLanguageSkill(item) {
			if (!this.formData.language_skills) this.formData.language_skills = []
			const idx = this.formData.language_skills.indexOf(item)
			if (idx > -1) this.formData.language_skills.splice(idx, 1)
			else this.formData.language_skills.push(item)
		},
		toggleEngineeringSkill(item) {
			if (!this.formData.engineering_skills) this.formData.engineering_skills = []
			const idx = this.formData.engineering_skills.indexOf(item)
			if (idx > -1) this.formData.engineering_skills.splice(idx, 1)
			else this.formData.engineering_skills.push(item)
		},
		toggleHobby(item) {
			if (!this.formData.hobbies) this.formData.hobbies = []
			const idx = this.formData.hobbies.indexOf(item)
			if (idx > -1) this.formData.hobbies.splice(idx, 1)
			else this.formData.hobbies.push(item)
		},
		toggleEmploymentDoc(item) {
			if (!this.formData.employment_documents) this.formData.employment_documents = []
			const idx = this.formData.employment_documents.indexOf(item)
			if (idx > -1) this.formData.employment_documents.splice(idx, 1)
			else this.formData.employment_documents.push(item)
		},
		
		// 添加/删除列表项
		addEducation() {
			this.formData.education_history.push({ date_range: '', school_major: '', certificate: '' })
		},
		deleteEducation(index) { this.formData.education_history.splice(index, 1) },
		
		addWorkHistory() {
			this.formData.work_history.push({ date_range: '', company: '', position: '', salary: '', leave_reason: '' })
		},
		deleteWorkHistory(index) { this.formData.work_history.splice(index, 1) },
		
		addFamilyMember() {
			this.formData.family_members.push({ name: '', relation: '', age: '', employer: '', phone: '' })
		},
		deleteFamilyMember(index) { this.formData.family_members.splice(index, 1) },
		
		// 签名变化
		onSignatureChange(data) {
			this.formData.signature = data.url
			this.formData.signaturePath = data.path
		},

		// 填充示例数据
		fillSampleData() {
			uni.showModal({
				title: '提示',
				content: '确定要填充示例数据吗？这将覆盖当前已填写的内容。',
				success: (res) => {
					if (res.confirm) {
						const today = new Date()
						const dateStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`
						
						this.formData = {
							...this.formData,
							fill_date: dateStr,
							entry_position: '软件工程师',
							entry_date: dateStr,
							department: '技术部',
							job_title: '工程师',
							housing_fund_account: '1234567890',
							bank_account: '6222021234567890123',
							bank_name: '中国工商银行北京分行',
							name: '张三',
							english_name: 'Zhang San',
							gender: 'male',
							height: '175',
							birth_date: '1990-05-15',
							id_number: '110101199005150015',
							political_status: '群众',
							education_level: '本科',
							education_type: '统招',
							native_place: '北京市',
							marital_status: 'married',
							has_children: '男孩',
							household_type: 'urban',
							current_address: '北京市朝阳区某某街道某某号',
							postal_code: '100000',
							household_address: '北京市朝阳区',
							contact_phone: '13800138000',
							document_address: '北京市朝阳区某某街道某某号',
							disability_level: '',
							language_skills: ['四级', '六级'],
							engineering_skills: [],
							professional_title: '中级',
							hobbies: ['球类'],
							other_skills: '熟悉Java、Python等编程语言',
							education_history: [
								{ date_range: '2008.09-2012.06', school_major: '北京大学 计算机科学', certificate: '学士学位' }
							],
							work_history: [
								{ date_range: '2012.07-2020.06', company: '某某科技公司', position: '软件工程师', salary: '15000', leave_reason: '个人发展' }
							],
							reference_company: '某某科技公司',
							reference_contact: '李经理 13900139000',
							rewards_punishments: '2018年获得优秀员工称号',
							family_members: [
								{ name: '张父', relation: '父亲', age: '55', employer: '某某公司', phone: '13900139001' }
							],
							emergency_contact1_name: '张父',
							emergency_contact1_relation: '父亲',
							emergency_contact1_phone: '13900139001',
							emergency_contact2_name: '李四',
							emergency_contact2_relation: '配偶',
							emergency_contact2_phone: '13900139002',
							mental_illness: '无',
							other_illness: '无',
							hospitalized_recently: '无',
							criminal_record: '无',
							employment_documents: ['离职证明'],
							remarks: '希望在公司长期发展',
							is_pregnant: '无',
							accept_overtime: '接受',
							need_accommodation: '无',
							has_driving_license: '有',
							driving_license_detail: 'C1',
							signature_date: dateStr
						}
						uni.showToast({ title: '示例数据已填充', icon: 'success' })
					}
				}
			})
		},

		// 提交表单
		async submitForm() {
			// 验证必填字段
			if (!this.formData.name) {
				uni.showToast({ title: '请输入姓名', icon: 'none' })
				return
			}
			if (!this.formData.id_number) {
				uni.showToast({ title: '请输入身份证号码', icon: 'none' })
				return
			}
			if (!this.validateIdNumber(this.formData.id_number)) {
				uni.showToast({ title: '请输入正确的身份证号码', icon: 'none' })
				return
			}
			if (!this.formData.signaturePath) {
				uni.showToast({ title: '请先完成手写签名', icon: 'none' })
				return
			}
			
			this.submitting = true
			
			try {
				const submitData = { ...this.formData }
				submitData.signature = this.formData.signaturePath
				delete submitData.signaturePath
				if (!submitData.entry_date || !String(submitData.entry_date).trim()) {
					delete submitData.entry_date
				}
				
				const res = await submitRegistrationForm(submitData)
				
				if (res.success) {
					uni.showToast({ title: '提交成功', icon: 'success' })
					setTimeout(() => { uni.navigateBack() }, 1500)
				} else {
					uni.showToast({ title: res.message || '提交失败', icon: 'none' })
				}
			} catch (error) {
				console.error('提交失败:', error)
				uni.showToast({ title: '提交失败，请重试', icon: 'none' })
			} finally {
				this.submitting = false
			}
		}
	}
}
</script>


<style scoped>
.container {
	padding: 20rpx;
	background-color: #f5f5f5;
}

.form-section {
	background-color: #fff;
	border-radius: 16rpx;
	padding: 30rpx;
	margin-bottom: 20rpx;
}

.notice-section {
	background-color: #fff9e6;
	border: 1rpx solid #ffd666;
}

.notice-text {
	font-size: 24rpx;
	color: #666;
	line-height: 1.6;
}

.section-title {
	font-size: 32rpx;
	font-weight: bold;
	color: #333;
	margin-bottom: 30rpx;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.sub-title {
	font-size: 28rpx;
	font-weight: bold;
	color: #666;
	margin: 20rpx 0 10rpx;
	padding-left: 10rpx;
	border-left: 4rpx solid #409eff;
}

.fill-sample-btn {
	display: flex;
	align-items: center;
	background-color: #e6f7ff;
	padding: 8rpx 16rpx;
	border-radius: 8rpx;
}

.fill-sample-btn .icon {
	font-size: 24rpx;
	margin-right: 8rpx;
}

.fill-sample-btn .text {
	font-size: 24rpx;
	color: #1890ff;
	font-weight: normal;
}

.form-item {
	display: flex;
	align-items: center;
	padding: 20rpx 0;
	border-bottom: 1rpx solid #f0f0f0;
}

.form-item:last-child {
	border-bottom: none;
}

.label {
	width: 200rpx;
	font-size: 28rpx;
	color: #333;
	flex-shrink: 0;
}

.label.required::before {
	content: '*';
	color: #f56c6c;
	margin-right: 4rpx;
}

.form-item input, .form-item textarea {
	flex: 1;
	font-size: 28rpx;
	color: #333;
}

.picker {
	flex: 1;
	font-size: 28rpx;
	color: #333;
}

.picker.disabled {
	color: #999;
}

.radio-label, .checkbox-label {
	margin-right: 30rpx;
	font-size: 28rpx;
}

.checkbox-group {
	display: flex;
	flex-wrap: wrap;
	flex: 1;
}

.textarea {
	width: 100%;
	min-height: 150rpx;
	font-size: 28rpx;
	padding: 20rpx;
	background-color: #f9f9f9;
	border-radius: 8rpx;
}

.add-btn {
	font-size: 26rpx;
	color: #409eff;
	font-weight: normal;
}

.list-item {
	background-color: #f9f9f9;
	border-radius: 8rpx;
	padding: 20rpx;
	margin-bottom: 20rpx;
}

.item-header {
	display: flex;
	justify-content: space-between;
	margin-bottom: 10rpx;
	font-size: 26rpx;
	color: #666;
}

.delete-btn {
	color: #f56c6c;
}

.declaration-box {
	background-color: #f9f9f9;
	padding: 20rpx;
	border-radius: 8rpx;
	margin-bottom: 20rpx;
}

.declaration-text {
	font-size: 24rpx;
	color: #666;
	line-height: 1.8;
	display: block;
	margin-bottom: 10rpx;
}

.submit-section {
	padding: 30rpx 0;
}

.submit-btn {
	background-color: #409eff;
	color: #fff;
	border-radius: 8rpx;
}
</style>
