<template>
  <div class="employees-page">
    <!-- 未分配账套提示 -->
    <NoAccountSetTip v-if="!isAdmin && !currentAccountSetId" />
    
    <!-- 正常内容 -->
    <div v-else>
    <div class="page-header">
      <h1>人员档案管理</h1>
      <div style="display: flex; gap: 10px;">
        <el-button 
          v-if="expiredIdCardsCount > 0" 
          type="danger" 
          @click="showExpiredIdCardsDialog"
        >
          <el-icon><Warning /></el-icon>
          身份证已过期 ({{ expiredIdCardsCount }}人)
        </el-button>
        <el-button 
          v-if="pendingContractUploadList.length > 0" 
          type="warning" 
          @click="showPendingContractDialog = true"
        >
          <el-icon><Warning /></el-icon>
          待上传合同 ({{ pendingContractUploadList.length }}人)
        </el-button>
        <el-button type="success" @click="handleDownloadTemplate">
          <el-icon><Download /></el-icon>
          下载导入模板
        </el-button>
        <el-button type="warning" @click="handleImportEmployees">
          <el-icon><Upload /></el-icon>
          批量导入
        </el-button>
        <el-button type="primary" @click="handleAddEmployee">
          <el-icon><Plus /></el-icon>
          新增员工
        </el-button>
      </div>
    </div>
    
    <!-- 人员统计 -->
    <div class="stats-section" style="margin-bottom: 16px;">
      <el-row :gutter="16">
        <el-col :span="6">
          <el-card shadow="hover" class="stat-card">
            <div class="stat-item">
              <div class="stat-value" style="color: #67c23a;">{{ employeeStats.active }}</div>
              <div class="stat-label">在职人数</div>
            </div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="stat-card">
            <div class="stat-item">
              <div class="stat-value" style="color: #909399;">{{ employeeStats.resigned }}</div>
              <div class="stat-label">离职人数</div>
            </div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="stat-card">
            <div class="stat-item">
              <div class="stat-value" style="color: #e6a23c;">{{ employeeStats.probation }}</div>
              <div class="stat-label">试用期人数</div>
            </div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="stat-card">
            <div class="stat-item">
              <div class="stat-value" style="color: #f56c6c;">{{ employeeStats.contractExpired }}</div>
              <div class="stat-label">合同已到期</div>
            </div>
          </el-card>
        </el-col>
      </el-row>
    </div>
    
    <!-- 搜索和筛选 -->
    <div class="search-section">
      <el-card>
        <el-form :model="searchForm" inline>
          <el-form-item label="姓名">
            <el-input
              v-model="searchForm.search"
              placeholder="请输入姓名或身份证号"
              clearable
              @keyup.enter="handleSearch"
            />
          </el-form-item>
          
          <el-form-item label="项目">
            <el-select
              v-model="searchForm.project_id"
              placeholder="请选择项目"
              clearable
              style="width: 200px"
            >
              <el-option
                v-for="project in projects"
                :key="project.id"
                :label="project.name"
                :value="project.id"
              />
            </el-select>
          </el-form-item>
          
          <el-form-item label="合同状态">
            <el-select
              v-model="searchForm.contract_status"
              placeholder="请选择合同状态"
              clearable
              style="width: 200px"
            >
              <el-option label="在职" value="active" />
              <el-option label="已过期" value="expired" />
              <el-option label="已终止" value="terminated" />
              <el-option label="退休" value="retired" />
            </el-select>
          </el-form-item>
          
          <el-form-item>
            <el-button type="primary" @click="handleSearch">
              <el-icon><Search /></el-icon>
              搜索
            </el-button>
            <el-button @click="handleReset">
              <el-icon><Refresh /></el-icon>
              重置
            </el-button>
            <el-button 
              type="success" 
              @click="handleBatchExportPdf"
              :disabled="selectedEmployees.length === 0"
            >
              <el-icon><Download /></el-icon>
              批量导出PDF ({{ selectedEmployees.length }})
            </el-button>
            <el-button 
              type="warning" 
              @click="handleBatchDownloadDocuments"
              :disabled="selectedEmployees.length === 0"
            >
              <el-icon><FolderOpened /></el-icon>
              批量下载资料 ({{ selectedEmployees.length }})
            </el-button>
          </el-form-item>
        </el-form>
      </el-card>
    </div>
    
    <!-- 员工列表 -->
    <div class="table-section">
      <el-card>
        <!-- 顶部模块选择器 -->
        <div class="module-selector" style="margin-bottom: 16px; padding: 16px; background: #f5f7fa; border-radius: 4px;">
          <div style="margin-bottom: 8px;">
            <el-text type="primary" size="large" style="font-weight: bold;">选择显示模块：</el-text>
          </div>
          <el-checkbox-group v-model="selectedModules" @change="handleModuleChange">
            <el-checkbox label="employee" border>员工信息</el-checkbox>
            <el-checkbox label="insurance" border>保险信息</el-checkbox>
            <el-checkbox label="documents" border>资料上传</el-checkbox>
            <el-checkbox label="personal" border>个人信息</el-checkbox>
            <el-checkbox label="salary-card" border>工资卡</el-checkbox>
            <el-checkbox label="transfer-logs" border>调动记录</el-checkbox>
          </el-checkbox-group>
        </div>
        
        <el-table
          :data="employees"
          v-loading="loading"
          stripe
          border
          @selection-change="handleSelectionChange"
        >
          <el-table-column type="selection" width="55" fixed="left" />
          <!-- 始终显示的列 -->
          <el-table-column prop="employee_number" label="工号" width="120" fixed="left" />
          <el-table-column prop="name" label="姓名" width="100" fixed="left" />
          <el-table-column prop="projects" label="所属项目" min-width="200">
            <template #default="{ row }">
              <el-tag
                v-for="project in row.projects.filter(p => p.pivot?.status === 'active')"
                :key="project.id"
                class="project-tag"
                size="small"
              >
                {{ project.name }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="contract_status" label="合同状态" width="100">
            <template #default="{ row }">
              <el-tag :type="getEmployeeContractStatusType(row.contract_status)">
                {{ getEmployeeContractStatusText(row.contract_status) }}
              </el-tag>
            </template>
          </el-table-column>
          
          <!-- 线下合同上传状态 -->
          <el-table-column label="线下合同" width="110">
            <template #default="{ row }">
              <el-tag 
                v-if="row.is_offline_onboarding" 
                :type="row.contract_uploaded ? 'success' : 'warning'"
              >
                {{ row.contract_uploaded ? '已上传' : '待上传' }}
              </el-tag>
              <el-text v-else type="info">-</el-text>
            </template>
          </el-table-column>
          
          <!-- 员工信息视图的列 -->
          <template v-if="selectedModules.includes('employee')">
            <el-table-column prop="position" label="岗位" width="120" />
            <el-table-column prop="id_number" label="身份证号" width="180" />
            <el-table-column prop="phone" label="手机号" width="120" />
            <el-table-column prop="gender" label="性别" width="80">
              <template #default="{ row }">
                <el-tag :type="row.gender === 'male' ? 'primary' : 'success'">
                  {{ row.gender === 'male' ? '男' : '女' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="birth_date" label="出生日期" width="120">
              <template #default="{ row }">
                {{ formatDate(row.birth_date) }}
              </template>
            </el-table-column>
            <el-table-column prop="hire_date" label="入职日期" width="120">
              <template #default="{ row }">
                {{ formatDate(row.hire_date) }}
              </template>
            </el-table-column>
            <el-table-column prop="contract_start_date" label="合同开始日期" width="130">
              <template #default="{ row }">
                {{ formatDate(row.contract_start_date) }}
              </template>
            </el-table-column>
            <el-table-column prop="contract_end_date" label="合同结束日期" width="130">
              <template #default="{ row }">
                {{ formatDate(row.contract_end_date) }}
              </template>
            </el-table-column>
            <el-table-column prop="country_region" label="国籍(地区)" width="120" />
            <el-table-column prop="chinese_name" label="中文名" width="100" />
            <el-table-column prop="birth_country" label="出生国家(地区)" width="130" />
            <el-table-column prop="birth_place" label="出生地" width="120" />
            <el-table-column prop="native_place" label="籍贯" width="120" />
            <el-table-column prop="ethnicity" label="民族" width="80" />
            <el-table-column prop="political_status" label="政治面貌" width="100" />
            <el-table-column prop="marital_status" label="婚姻状况" width="100">
              <template #default="{ row }">
                {{ row.marital_status === 'married' ? '已婚' : row.marital_status === 'single' ? '未婚' : '-' }}
              </template>
            </el-table-column>
            <el-table-column prop="health_status" label="健康状况" width="100" />
            <el-table-column prop="blood_type" label="血型" width="80" />
            <el-table-column prop="height" label="身高(cm)" width="100" />
            <el-table-column prop="weight" label="体重(kg)" width="100" />
            <el-table-column prop="education" label="学历" width="100" />
            <el-table-column prop="degree" label="学位" width="100" />
            <el-table-column prop="graduation_school" label="毕业院校" width="150" />
            <el-table-column prop="major" label="专业" width="120" />
            <el-table-column prop="graduation_date" label="毕业时间" width="120">
              <template #default="{ row }">
                {{ formatDate(row.graduation_date) }}
              </template>
            </el-table-column>
            <el-table-column prop="email" label="邮箱" width="180" />
            <el-table-column prop="address" label="现住址" width="200" />
            <el-table-column prop="household_registration" label="户口所在地" width="200" />
          </template>
          
          <!-- 保险信息视图的列 -->
          <template v-if="selectedModules.includes('insurance')">
            <el-table-column label="社保地区" width="150">
              <template #default="{ row }">
                {{ row.social_security_region?.name || row.social_security_region?.region_name || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="医保地区" width="150">
              <template #default="{ row }">
                {{ row.medical_insurance_region?.name || row.medical_insurance_region?.region_name || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="公积金地区" width="150">
              <template #default="{ row }">
                {{ row.housing_fund_region?.name || row.housing_fund_region?.region_name || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="公积金配置" width="180">
              <template #default="{ row }">
                {{ row.housing_fund_config?.name || row.housing_fund_config?.config_name || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="大额医疗配置" width="180">
              <template #default="{ row }">
                {{ row.large_medical_insurance_config?.name || row.large_medical_insurance_config?.region_name || '-' }}
              </template>
            </el-table-column>
            <el-table-column prop="social_security_base" label="社保基数" width="120">
              <template #default="{ row }">
                {{ row.social_security_base || '-' }}
              </template>
            </el-table-column>
            <el-table-column prop="medical_insurance_base" label="医保基数" width="120">
              <template #default="{ row }">
                {{ row.medical_insurance_base || '-' }}
              </template>
            </el-table-column>
            <el-table-column prop="housing_fund_base" label="公积金基数" width="120">
              <template #default="{ row }">
                {{ row.housing_fund_base || '-' }}
              </template>
            </el-table-column>
            <el-table-column prop="large_medical_base" label="大额医疗基数" width="130">
              <template #default="{ row }">
                {{ row.large_medical_base || '-' }}
              </template>
            </el-table-column>
          </template>
          
          <!-- 资料上传视图的列 -->
          <template v-if="selectedModules.includes('documents')">
            <el-table-column label="资料总数" width="100">
              <template #default="{ row }">
                <el-tag type="primary">{{ row.documents?.length || 0 }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="资料列表" min-width="400">
              <template #default="{ row }">
                <div v-if="row.documents && row.documents.length > 0" style="display: flex; flex-wrap: wrap; gap: 8px;">
                  <el-tag 
                    v-for="doc in row.documents" 
                    :key="doc.id"
                    type="success"
                    size="small"
                  >
                    {{ doc.document_name || doc.original_filename }}
                  </el-tag>
                </div>
                <el-text v-else type="info">暂无资料</el-text>
              </template>
            </el-table-column>
          </template>
          
          <!-- 个人信息视图的列 -->
          <template v-if="selectedModules.includes('personal')">
            <!-- 基本信息 -->
            <el-table-column label="登记日期" width="120">
              <template #default="{ row }">
                {{ row.onboarding_form?.registration_date ? formatDate(row.onboarding_form.registration_date) : '-' }}
              </template>
            </el-table-column>
            <el-table-column label="姓名" width="100">
              <template #default="{ row }">
                {{ row.onboarding_form?.name || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="性别" width="80">
              <template #default="{ row }">
                {{ row.onboarding_form?.gender === 'male' ? '男' : (row.onboarding_form?.gender === 'female' ? '女' : '-') }}
              </template>
            </el-table-column>
            <el-table-column label="民族" width="80">
              <template #default="{ row }">
                {{ row.onboarding_form?.ethnicity || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="政治面貌" width="100">
              <template #default="{ row }">
                {{ row.onboarding_form?.political_status || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="籍贯" width="120">
              <template #default="{ row }">
                {{ row.onboarding_form?.place_of_origin || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="出生年月" width="120">
              <template #default="{ row }">
                {{ row.onboarding_form?.birth_date ? formatDate(row.onboarding_form.birth_date) : '-' }}
              </template>
            </el-table-column>
            <el-table-column label="身份证号码" width="180">
              <template #default="{ row }">
                {{ row.onboarding_form?.id_number || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="现居住地" width="200">
              <template #default="{ row }">
                {{ row.onboarding_form?.current_residence || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="户口所在地" width="200">
              <template #default="{ row }">
                {{ row.onboarding_form?.household_registration || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="婚姻状况" width="100">
              <template #default="{ row }">
                {{ row.onboarding_form?.marital_status === 'married' ? '已婚' : (row.onboarding_form?.marital_status === 'single' ? '未婚' : '-') }}
              </template>
            </el-table-column>
            <el-table-column label="健康状况" width="100">
              <template #default="{ row }">
                {{ row.onboarding_form?.health_status || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="身高(cm)" width="100">
              <template #default="{ row }">
                {{ row.onboarding_form?.height || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="体重(kg)" width="100">
              <template #default="{ row }">
                {{ row.onboarding_form?.weight || '-' }}
              </template>
            </el-table-column>
            
            <!-- 教育信息 -->
            <el-table-column label="毕业学校" width="150">
              <template #default="{ row }">
                {{ row.onboarding_form?.graduated_school || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="毕业时间" width="120">
              <template #default="{ row }">
                {{ row.onboarding_form?.graduation_date ? formatDate(row.onboarding_form.graduation_date) : '-' }}
              </template>
            </el-table-column>
            <el-table-column label="文化程度" width="100">
              <template #default="{ row }">
                {{ row.onboarding_form?.education_level || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="所学专业" width="120">
              <template #default="{ row }">
                {{ row.onboarding_form?.major || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="学位" width="100">
              <template #default="{ row }">
                {{ row.onboarding_form?.degree || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="技术职称" width="120">
              <template #default="{ row }">
                {{ row.onboarding_form?.technical_title || '-' }}
              </template>
            </el-table-column>
            
            <!-- 学习简历 -->
            <el-table-column label="学习简历数" width="100">
              <template #default="{ row }">
                {{ row.onboarding_form?.education_background?.length || 0 }}
              </template>
            </el-table-column>
            
            <!-- 工作经历 -->
            <el-table-column label="工作经历数" width="100">
              <template #default="{ row }">
                {{ row.onboarding_form?.work_experience?.length || 0 }}
              </template>
            </el-table-column>
            
            <!-- 家庭情况 -->
            <el-table-column label="家庭成员数" width="100">
              <template #default="{ row }">
                {{ row.onboarding_form?.family_info?.length || 0 }}
              </template>
            </el-table-column>
            
            <!-- 紧急联系人 -->
            <el-table-column label="紧急联系人" width="120">
              <template #default="{ row }">
                {{ row.onboarding_form?.emergency_contact_name || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="紧急联系电话" width="130">
              <template #default="{ row }">
                {{ row.onboarding_form?.emergency_contact_phone || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="与本人关系" width="120">
              <template #default="{ row }">
                {{ row.onboarding_form?.emergency_contact_relationship || '-' }}
              </template>
            </el-table-column>
            
            <!-- 就业信息 -->
            <el-table-column label="岗位" width="120">
              <template #default="{ row }">
                {{ row.onboarding_form?.position || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="求职地区" width="120">
              <template #default="{ row }">
                {{ row.onboarding_form?.desired_location || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="是否服从调配" width="120">
              <template #default="{ row }">
                <el-tag :type="row.onboarding_form?.accept_assignment ? 'success' : 'info'">
                  {{ row.onboarding_form?.accept_assignment ? '是' : '否' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="联系电话" width="130">
              <template #default="{ row }">
                {{ row.onboarding_form?.contact_phone || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="联系地址" width="200">
              <template #default="{ row }">
                {{ row.onboarding_form?.contact_address || '-' }}
              </template>
            </el-table-column>
            
            <!-- 备注和签名 -->
            <el-table-column label="备注" width="200">
              <template #default="{ row }">
                {{ row.onboarding_form?.remarks || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="是否签名" width="100">
              <template #default="{ row }">
                <el-tag :type="row.onboarding_form?.signature ? 'success' : 'info'">
                  {{ row.onboarding_form?.signature ? '已签' : '未签' }}
                </el-tag>
              </template>
            </el-table-column>
          </template>
          
          <!-- 工资卡视图的列 -->
          <template v-if="selectedModules.includes('salary-card')">
            <el-table-column prop="bank_account" label="银行账号" width="200">
              <template #default="{ row }">
                {{ row.bank_account || '-' }}
              </template>
            </el-table-column>
            <el-table-column prop="bank_account_holder" label="户名" width="120">
              <template #default="{ row }">
                {{ row.bank_account_holder || '-' }}
              </template>
            </el-table-column>
            <el-table-column prop="bank_name" label="开户行" width="150">
              <template #default="{ row }">
                {{ row.bank_name || '-' }}
              </template>
            </el-table-column>
            <el-table-column prop="bank_branch" label="开户地" width="150">
              <template #default="{ row }">
                {{ row.bank_branch || '-' }}
              </template>
            </el-table-column>
            <el-table-column prop="remittance_remark" label="汇款备注" width="200">
              <template #default="{ row }">
                {{ row.remittance_remark || '-' }}
              </template>
            </el-table-column>
          </template>
          
          <!-- 调动记录视图的列 -->
          <template v-if="selectedModules.includes('transfer-logs')">
            <el-table-column label="调动次数" width="100">
              <template #default="{ row }">
                <el-tag type="primary">{{ getTransferCount(row) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="最近调动时间" width="170">
              <template #default="{ row }">
                {{ getLatestTransferTime(row) }}
              </template>
            </el-table-column>
            <el-table-column label="最近从项目" width="150">
              <template #default="{ row }">
                {{ getLatestTransferFrom(row) }}
              </template>
            </el-table-column>
            <el-table-column label="最近至项目" width="150">
              <template #default="{ row }">
                {{ getLatestTransferTo(row) }}
              </template>
            </el-table-column>
            <el-table-column label="最近操作人" width="120">
              <template #default="{ row }">
                {{ getLatestTransferOperator(row) }}
              </template>
            </el-table-column>
          </template>
          
          <el-table-column label="操作" width="680" fixed="right">
            <template #default="{ row }">
              <el-button type="primary" size="small" @click="handleView(row)">
                查看
              </el-button>
              <el-button type="warning" size="small" @click="handleEdit(row)">
                编辑
              </el-button>
              <el-button type="info" size="small" @click="openTransferDialog(row)">
                调动
              </el-button>
              <el-button type="success" size="small" @click="handleContractManage(row)">
                合同管理
              </el-button>
              <el-button size="small" @click="handleViewChangeHistory(row)">
                变更历史
              </el-button>
              <!-- 线下入职按钮（仅未入职状态显示） -->
              <el-button 
                v-if="row.contract_status !== 'active' && row.contract_status !== 'terminated' && row.contract_status !== 'retired'"
                :type="row.pending_offline_onboarding ? 'info' : 'success'" 
                size="small" 
                :disabled="row.pending_offline_onboarding"
                @click="handleOfflineOnboarding(row)"
              >
                {{ row.pending_offline_onboarding ? '审批中' : '线下入职' }}
              </el-button>
              <!-- 大额医疗保险按钮（仅在职状态显示） -->
              <el-button 
                v-if="row.contract_status === 'active' && row.largeMedicalStatus && row.largeMedicalStatus.has_config"
                :type="getLargeMedicalButtonType(row.largeMedicalStatus)"
                size="small" 
                :disabled="!row.largeMedicalStatus.can_enable"
                @click="handleEnableLargeMedical(row)"
              >
                {{ row.largeMedicalStatus.status_text }}
              </el-button>
              <el-button type="primary" size="small" @click="handlePrintEmployee(row)">
                打印
              </el-button>
              <el-button 
                v-if="canShowDeleteButton(row)"
                type="danger" 
                size="small" 
                @click="handleDelete(row)"
              >
                删除
              </el-button>
            </template>
          </el-table-column>
        </el-table>
        
        <!-- 分页 -->
        <div class="pagination">
          <el-pagination
            v-model:current-page="pagination.currentPage"
            v-model:page-size="pagination.pageSize"
            :page-sizes="[10, 20, 50, 100]"
            :total="pagination.total"
            layout="total, sizes, prev, pager, next, jumper"
            @size-change="handleSizeChange"
            @current-change="handleCurrentChange"
          />
        </div>
      </el-card>
    </div>
    
    <!-- 创建/编辑对话框 -->
    <el-dialog
      v-model="showCreateDialog"
      :title="isViewMode ? '查看员工' : (isEdit ? '编辑员工' : '新增员工')"
      width="1000px"
      @close="handleDialogClose"
    >
      <el-tabs v-model="activeTab" v-if="isEdit || isViewMode">
        <el-tab-pane label="员工信息" name="employee">
          <el-form
            ref="formRef"
            :model="form"
            :rules="formRules"
            label-width="120px"
          >
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="工号" prop="employee_number">
              <el-input 
                v-model="form.employee_number" 
                placeholder="自动生成" 
                readonly
              >
                <template #prepend>
                  <el-icon><Postcard /></el-icon>
                </template>
              </el-input>
              <div class="form-tip" style="color: #67C23A;">根据所选项目自动生成（如：AA001, AB001）</div>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="姓名" prop="name">
              <el-input v-model="form.name" placeholder="请输入姓名" :disabled="isViewMode" />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="岗位" prop="position">
              <el-input v-model="form.position" placeholder="请输入岗位" :disabled="isViewMode" />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="身份证号" prop="id_number">
              <el-input v-model="form.id_number" placeholder="请输入身份证号" :disabled="isViewMode" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="手机号" prop="phone">
              <el-input v-model="form.phone" placeholder="请输入手机号" :disabled="isViewMode" />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="身份证有效期开始" prop="id_card_valid_from">
              <el-date-picker
                v-model="form.id_card_valid_from"
                type="date"
                placeholder="请选择身份证有效期开始日期"
                style="width: 100%"
                :disabled="isViewMode"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="身份证有效期至" prop="id_card_valid_until">
              <el-date-picker
                v-model="form.id_card_valid_until"
                type="date"
                placeholder="请选择身份证有效期至（长期有效可不填）"
                style="width: 100%"
                :disabled="isViewMode"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
              />
              <div class="form-tip">长期有效可不填写</div>
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="性别" prop="gender">
              <el-radio-group v-model="form.gender" :disabled="isViewMode">
                <el-radio label="male">男</el-radio>
                <el-radio label="female">女</el-radio>
              </el-radio-group>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="出生日期" prop="birth_date">
              <el-date-picker
                v-model="form.birth_date"
                type="date"
                placeholder="请选择出生日期"
                style="width: 100%"
                :disabled="isViewMode"
              />
            </el-form-item>
          </el-col>
        </el-row>

        <!-- 退休类别（仅女性显示） -->
        <el-row :gutter="30" v-if="form.gender === 'female'">
          <el-col :span="12">
            <el-form-item label="退休类别" prop="retirement_category">
              <el-radio-group v-model="form.retirement_category" :disabled="isViewMode">
                <el-radio label="worker">普通岗（原50岁退休）</el-radio>
                <el-radio label="cadre">管理岗（原55岁退休）</el-radio>
              </el-radio-group>
              <div class="form-tip" style="color: #909399; font-size: 12px;">
                根据2025年延迟退休政策，普通岗延至55岁，管理岗延至58岁
              </div>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="预计退休日期">
              <el-input 
                :value="calculatedRetirementDate" 
                readonly 
                placeholder="根据出生日期自动计算"
              >
                <template #prefix>
                  <el-icon><Calendar /></el-icon>
                </template>
              </el-input>
            </el-form-item>
          </el-col>
        </el-row>

        <!-- 男性显示预计退休日期 -->
        <el-row :gutter="30" v-if="form.gender === 'male'">
          <el-col :span="12">
            <el-form-item label="预计退休日期">
              <el-input 
                :value="calculatedRetirementDate" 
                readonly 
                placeholder="根据出生日期自动计算"
              >
                <template #prefix>
                  <el-icon><Calendar /></el-icon>
                </template>
              </el-input>
              <div class="form-tip" style="color: #909399; font-size: 12px;">
                根据2025年延迟退休政策，男职工延至63岁
              </div>
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="合同开始日期" prop="contract_start_date">
              <el-date-picker
                v-model="form.contract_start_date"
                type="date"
                placeholder="请选择合同开始日期"
                style="width: 100%"
                :disabled="isViewMode"
                @change="handleContractStartDateChange"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="入职日期" prop="hire_date">
              <el-date-picker
                v-model="form.hire_date"
                type="date"
                placeholder="请选择入职日期"
                style="width: 100%"
                :disabled="isViewMode"
              />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="合同结束日期" prop="contract_end_date">
              <el-date-picker
                v-model="form.contract_end_date"
                type="date"
                placeholder="请选择合同结束日期"
                style="width: 100%"
                :disabled="isViewMode"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="试用期结束日期" prop="probation_end_date">
              <el-date-picker
                v-model="form.probation_end_date"
                type="date"
                placeholder="请选择试用期结束日期"
                style="width: 100%"
                :disabled="isViewMode"
              />
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="离职日期">
              <el-date-picker
                v-model="form.resignation_date"
                type="date"
                placeholder="离职日期（可不填）"
                style="width: 100%"
                :disabled="isViewMode"
                clearable
              />
              <div class="form-tip" style="color: #909399; font-size: 12px; margin-top: 4px;">
                💡 填写后系统将在月底检查离职合同审批情况
              </div>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="签署地">
              <el-input
                v-model="form.signing_location"
                placeholder="请输入签署地"
                clearable
                :disabled="isViewMode"
              />
              <div class="form-tip" style="color: #909399; font-size: 12px; margin-top: 4px;">
                合同签署的地点
              </div>
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="户口类型">
              <el-select
                v-model="form.household_type"
                placeholder="请选择户口类型"
                clearable
                :disabled="isViewMode"
                style="width: 100%"
              >
                <el-option label="农业" value="agricultural" />
                <el-option label="非农业" value="non_agricultural" />
              </el-select>
              <div class="form-tip" style="color: #909399; font-size: 12px; margin-top: 4px;">
                员工的户口类型
              </div>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="所属项目" prop="project_ids">
              <el-select
                v-model="form.project_ids[0]"
                placeholder="请选择所属项目"
                style="width: 100%"
                :disabled="isViewMode"
                @change="handleSingleProjectChange"
              >
                <el-option
                  v-for="project in projects"
                  :key="project.id"
                  :label="project.name"
                  :value="project.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        
        <!-- 示例填充按钮 -->
        <el-row style="margin-bottom: 20px;">
          <el-col :span="24" style="text-align: right;">
            <el-button type="primary" plain @click="fillSampleData" :disabled="isViewMode">
              <el-icon><Star /></el-icon>
              示例填充
            </el-button>
          </el-col>
        </el-row>

        <!-- 一、基础身份信息 -->
        <el-divider content-position="left">基础身份信息</el-divider>
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="国籍(地区)" prop="country_region">
              <el-select v-model="form.country_region" placeholder="请选择国籍(地区)" :disabled="isViewMode" clearable filterable>
                <el-option label="中国" value="中国" />
                <el-option label="中国澳门" value="中国澳门" />
                <el-option label="中国台湾" value="中国台湾" />
                <el-option label="中国香港" value="中国香港" />
                <el-option label="阿尔巴尼亚" value="阿尔巴尼亚" />
                <el-option label="阿尔及利亚" value="阿尔及利亚" />
                <el-option label="阿富汗" value="阿富汗" />
                <el-option label="阿根廷" value="阿根廷" />
                <el-option label="阿联酋" value="阿联酋" />
                <el-option label="阿曼" value="阿曼" />
                <el-option label="埃及" value="埃及" />
                <el-option label="埃塞俄比亚" value="埃塞俄比亚" />
                <el-option label="爱尔兰" value="爱尔兰" />
                <el-option label="爱沙尼亚" value="爱沙尼亚" />
                <el-option label="安道尔" value="安道尔" />
                <el-option label="安哥拉" value="安哥拉" />
                <el-option label="安提瓜和巴布达" value="安提瓜和巴布达" />
                <el-option label="奥地利" value="奥地利" />
                <el-option label="澳大利亚" value="澳大利亚" />
                <el-option label="巴巴多斯" value="巴巴多斯" />
                <el-option label="巴布亚新几内亚" value="巴布亚新几内亚" />
                <el-option label="巴哈马" value="巴哈马" />
                <el-option label="巴基斯坦" value="巴基斯坦" />
                <el-option label="巴拉圭" value="巴拉圭" />
                <el-option label="巴林" value="巴林" />
                <el-option label="巴拿马" value="巴拿马" />
                <el-option label="巴西" value="巴西" />
                <el-option label="白俄罗斯" value="白俄罗斯" />
                <el-option label="保加利亚" value="保加利亚" />
                <el-option label="贝宁" value="贝宁" />
                <el-option label="比利时" value="比利时" />
                <el-option label="冰岛" value="冰岛" />
                <el-option label="波兰" value="波兰" />
                <el-option label="波斯尼亚和黑塞哥维那" value="波斯尼亚和黑塞哥维那" />
                <el-option label="玻利维亚" value="玻利维亚" />
                <el-option label="博茨瓦纳" value="博茨瓦纳" />
                <el-option label="不丹" value="不丹" />
                <el-option label="布基纳法索" value="布基纳法索" />
                <el-option label="布隆迪" value="布隆迪" />
                <el-option label="朝鲜" value="朝鲜" />
                <el-option label="赤道几内亚" value="赤道几内亚" />
                <el-option label="丹麦" value="丹麦" />
                <el-option label="德国" value="德国" />
                <el-option label="东帝汶" value="东帝汶" />
                <el-option label="多哥" value="多哥" />
                <el-option label="多米尼加" value="多米尼加" />
                <el-option label="多米尼克" value="多米尼克" />
                <el-option label="俄罗斯" value="俄罗斯" />
                <el-option label="厄瓜多尔" value="厄瓜多尔" />
                <el-option label="厄立特里亚" value="厄立特里亚" />
                <el-option label="法国" value="法国" />
                <el-option label="梵蒂冈" value="梵蒂冈" />
                <el-option label="菲律宾" value="菲律宾" />
                <el-option label="斐济" value="斐济" />
                <el-option label="芬兰" value="芬兰" />
                <el-option label="佛得角" value="佛得角" />
                <el-option label="冈比亚" value="冈比亚" />
                <el-option label="刚果(布)" value="刚果(布)" />
                <el-option label="刚果(金)" value="刚果(金)" />
                <el-option label="哥伦比亚" value="哥伦比亚" />
                <el-option label="哥斯达黎加" value="哥斯达黎加" />
                <el-option label="格林纳达" value="格林纳达" />
                <el-option label="格鲁吉亚" value="格鲁吉亚" />
                <el-option label="古巴" value="古巴" />
                <el-option label="圭亚那" value="圭亚那" />
                <el-option label="哈萨克斯坦" value="哈萨克斯坦" />
                <el-option label="海地" value="海地" />
                <el-option label="韩国" value="韩国" />
                <el-option label="荷兰" value="荷兰" />
                <el-option label="黑山" value="黑山" />
                <el-option label="洪都拉斯" value="洪都拉斯" />
                <el-option label="基里巴斯" value="基里巴斯" />
                <el-option label="吉布提" value="吉布提" />
                <el-option label="吉尔吉斯斯坦" value="吉尔吉斯斯坦" />
                <el-option label="几内亚" value="几内亚" />
                <el-option label="几内亚比绍" value="几内亚比绍" />
                <el-option label="加拿大" value="加拿大" />
                <el-option label="加纳" value="加纳" />
                <el-option label="加蓬" value="加蓬" />
                <el-option label="柬埔寨" value="柬埔寨" />
                <el-option label="捷克" value="捷克" />
                <el-option label="津巴布韦" value="津巴布韦" />
                <el-option label="喀麦隆" value="喀麦隆" />
                <el-option label="卡塔尔" value="卡塔尔" />
                <el-option label="科摩罗" value="科摩罗" />
                <el-option label="科特迪瓦" value="科特迪瓦" />
                <el-option label="科威特" value="科威特" />
                <el-option label="克罗地亚" value="克罗地亚" />
                <el-option label="肯尼亚" value="肯尼亚" />
                <el-option label="库克群岛" value="库克群岛" />
                <el-option label="拉脱维亚" value="拉脱维亚" />
                <el-option label="莱索托" value="莱索托" />
                <el-option label="老挝" value="老挝" />
                <el-option label="黎巴嫩" value="黎巴嫩" />
                <el-option label="立陶宛" value="立陶宛" />
                <el-option label="利比里亚" value="利比里亚" />
                <el-option label="利比亚" value="利比亚" />
                <el-option label="列支敦士登" value="列支敦士登" />
                <el-option label="卢森堡" value="卢森堡" />
                <el-option label="卢旺达" value="卢旺达" />
                <el-option label="罗马尼亚" value="罗马尼亚" />
                <el-option label="马达加斯加" value="马达加斯加" />
                <el-option label="马尔代夫" value="马尔代夫" />
                <el-option label="马耳他" value="马耳他" />
                <el-option label="马拉维" value="马拉维" />
                <el-option label="马来西亚" value="马来西亚" />
                <el-option label="马里" value="马里" />
                <el-option label="马绍尔群岛" value="马绍尔群岛" />
                <el-option label="毛里求斯" value="毛里求斯" />
                <el-option label="毛里塔尼亚" value="毛里塔尼亚" />
                <el-option label="美国" value="美国" />
                <el-option label="蒙古" value="蒙古" />
                <el-option label="孟加拉国" value="孟加拉国" />
                <el-option label="秘鲁" value="秘鲁" />
                <el-option label="密克罗尼西亚" value="密克罗尼西亚" />
                <el-option label="缅甸" value="缅甸" />
                <el-option label="摩尔多瓦" value="摩尔多瓦" />
                <el-option label="摩洛哥" value="摩洛哥" />
                <el-option label="摩纳哥" value="摩纳哥" />
                <el-option label="莫桑比克" value="莫桑比克" />
                <el-option label="墨西哥" value="墨西哥" />
                <el-option label="纳米比亚" value="纳米比亚" />
                <el-option label="南非" value="南非" />
                <el-option label="南苏丹" value="南苏丹" />
                <el-option label="瑙鲁" value="瑙鲁" />
                <el-option label="尼泊尔" value="尼泊尔" />
                <el-option label="尼加拉瓜" value="尼加拉瓜" />
                <el-option label="尼日尔" value="尼日尔" />
                <el-option label="尼日利亚" value="尼日利亚" />
                <el-option label="挪威" value="挪威" />
                <el-option label="帕劳" value="帕劳" />
                <el-option label="葡萄牙" value="葡萄牙" />
                <el-option label="日本" value="日本" />
                <el-option label="瑞典" value="瑞典" />
                <el-option label="瑞士" value="瑞士" />
                <el-option label="萨尔瓦多" value="萨尔瓦多" />
                <el-option label="萨摩亚" value="萨摩亚" />
                <el-option label="塞尔维亚" value="塞尔维亚" />
                <el-option label="塞拉利昂" value="塞拉利昂" />
                <el-option label="塞内加尔" value="塞内加尔" />
                <el-option label="塞浦路斯" value="塞浦路斯" />
                <el-option label="塞舌尔" value="塞舌尔" />
                <el-option label="沙特阿拉伯" value="沙特阿拉伯" />
                <el-option label="圣多美和普林西比" value="圣多美和普林西比" />
                <el-option label="圣基茨和尼维斯" value="圣基茨和尼维斯" />
                <el-option label="圣卢西亚" value="圣卢西亚" />
                <el-option label="圣马力诺" value="圣马力诺" />
                <el-option label="圣文森特和格林纳丁斯" value="圣文森特和格林纳丁斯" />
                <el-option label="斯里兰卡" value="斯里兰卡" />
                <el-option label="斯洛伐克" value="斯洛伐克" />
                <el-option label="斯洛文尼亚" value="斯洛文尼亚" />
                <el-option label="斯威士兰" value="斯威士兰" />
                <el-option label="苏丹" value="苏丹" />
                <el-option label="苏里南" value="苏里南" />
                <el-option label="所罗门群岛" value="所罗门群岛" />
                <el-option label="索马里" value="索马里" />
                <el-option label="塔吉克斯坦" value="塔吉克斯坦" />
                <el-option label="泰国" value="泰国" />
                <el-option label="坦桑尼亚" value="坦桑尼亚" />
                <el-option label="汤加" value="汤加" />
                <el-option label="特立尼达和多巴哥" value="特立尼达和多巴哥" />
                <el-option label="突尼斯" value="突尼斯" />
                <el-option label="图瓦卢" value="图瓦卢" />
                <el-option label="土耳其" value="土耳其" />
                <el-option label="土库曼斯坦" value="土库曼斯坦" />
                <el-option label="瓦努阿图" value="瓦努阿图" />
                <el-option label="危地马拉" value="危地马拉" />
                <el-option label="委内瑞拉" value="委内瑞拉" />
                <el-option label="文莱" value="文莱" />
                <el-option label="乌干达" value="乌干达" />
                <el-option label="乌克兰" value="乌克兰" />
                <el-option label="乌拉圭" value="乌拉圭" />
                <el-option label="乌兹别克斯坦" value="乌兹别克斯坦" />
                <el-option label="西班牙" value="西班牙" />
                <el-option label="希腊" value="希腊" />
                <el-option label="新加坡" value="新加坡" />
                <el-option label="新西兰" value="新西兰" />
                <el-option label="匈牙利" value="匈牙利" />
                <el-option label="叙利亚" value="叙利亚" />
                <el-option label="牙买加" value="牙买加" />
                <el-option label="亚美尼亚" value="亚美尼亚" />
                <el-option label="也门" value="也门" />
                <el-option label="伊拉克" value="伊拉克" />
                <el-option label="伊朗" value="伊朗" />
                <el-option label="以色列" value="以色列" />
                <el-option label="意大利" value="意大利" />
                <el-option label="印度" value="印度" />
                <el-option label="印度尼西亚" value="印度尼西亚" />
                <el-option label="英国" value="英国" />
                <el-option label="约旦" value="约旦" />
                <el-option label="越南" value="越南" />
                <el-option label="赞比亚" value="赞比亚" />
                <el-option label="乍得" value="乍得" />
                <el-option label="智利" value="智利" />
                <el-option label="中非" value="中非" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="中文名" prop="chinese_name">
              <el-input v-model="form.chinese_name" placeholder="请输入中文名" :disabled="isViewMode" />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="出生国家(地区)" prop="birth_country">
              <el-select v-model="form.birth_country" placeholder="请选择出生国家(地区)" :disabled="isViewMode" clearable filterable>
                <el-option label="中国" value="中国" />
                <el-option label="中国澳门" value="中国澳门" />
                <el-option label="中国台湾" value="中国台湾" />
                <el-option label="中国香港" value="中国香港" />
                <el-option label="阿尔巴尼亚" value="阿尔巴尼亚" />
                <el-option label="阿尔及利亚" value="阿尔及利亚" />
                <el-option label="阿富汗" value="阿富汗" />
                <el-option label="阿根廷" value="阿根廷" />
                <el-option label="阿联酋" value="阿联酋" />
                <el-option label="阿曼" value="阿曼" />
                <el-option label="埃及" value="埃及" />
                <el-option label="埃塞俄比亚" value="埃塞俄比亚" />
                <el-option label="爱尔兰" value="爱尔兰" />
                <el-option label="爱沙尼亚" value="爱沙尼亚" />
                <el-option label="安道尔" value="安道尔" />
                <el-option label="安哥拉" value="安哥拉" />
                <el-option label="安提瓜和巴布达" value="安提瓜和巴布达" />
                <el-option label="奥地利" value="奥地利" />
                <el-option label="澳大利亚" value="澳大利亚" />
                <el-option label="巴巴多斯" value="巴巴多斯" />
                <el-option label="巴布亚新几内亚" value="巴布亚新几内亚" />
                <el-option label="巴哈马" value="巴哈马" />
                <el-option label="巴基斯坦" value="巴基斯坦" />
                <el-option label="巴拉圭" value="巴拉圭" />
                <el-option label="巴林" value="巴林" />
                <el-option label="巴拿马" value="巴拿马" />
                <el-option label="巴西" value="巴西" />
                <el-option label="白俄罗斯" value="白俄罗斯" />
                <el-option label="保加利亚" value="保加利亚" />
                <el-option label="贝宁" value="贝宁" />
                <el-option label="比利时" value="比利时" />
                <el-option label="冰岛" value="冰岛" />
                <el-option label="波兰" value="波兰" />
                <el-option label="波斯尼亚和黑塞哥维那" value="波斯尼亚和黑塞哥维那" />
                <el-option label="玻利维亚" value="玻利维亚" />
                <el-option label="博茨瓦纳" value="博茨瓦纳" />
                <el-option label="不丹" value="不丹" />
                <el-option label="布基纳法索" value="布基纳法索" />
                <el-option label="布隆迪" value="布隆迪" />
                <el-option label="朝鲜" value="朝鲜" />
                <el-option label="赤道几内亚" value="赤道几内亚" />
                <el-option label="丹麦" value="丹麦" />
                <el-option label="德国" value="德国" />
                <el-option label="东帝汶" value="东帝汶" />
                <el-option label="多哥" value="多哥" />
                <el-option label="多米尼加" value="多米尼加" />
                <el-option label="多米尼克" value="多米尼克" />
                <el-option label="俄罗斯" value="俄罗斯" />
                <el-option label="厄瓜多尔" value="厄瓜多尔" />
                <el-option label="厄立特里亚" value="厄立特里亚" />
                <el-option label="法国" value="法国" />
                <el-option label="梵蒂冈" value="梵蒂冈" />
                <el-option label="菲律宾" value="菲律宾" />
                <el-option label="斐济" value="斐济" />
                <el-option label="芬兰" value="芬兰" />
                <el-option label="佛得角" value="佛得角" />
                <el-option label="冈比亚" value="冈比亚" />
                <el-option label="刚果(布)" value="刚果(布)" />
                <el-option label="刚果(金)" value="刚果(金)" />
                <el-option label="哥伦比亚" value="哥伦比亚" />
                <el-option label="哥斯达黎加" value="哥斯达黎加" />
                <el-option label="格林纳达" value="格林纳达" />
                <el-option label="格鲁吉亚" value="格鲁吉亚" />
                <el-option label="古巴" value="古巴" />
                <el-option label="圭亚那" value="圭亚那" />
                <el-option label="哈萨克斯坦" value="哈萨克斯坦" />
                <el-option label="海地" value="海地" />
                <el-option label="韩国" value="韩国" />
                <el-option label="荷兰" value="荷兰" />
                <el-option label="黑山" value="黑山" />
                <el-option label="洪都拉斯" value="洪都拉斯" />
                <el-option label="基里巴斯" value="基里巴斯" />
                <el-option label="吉布提" value="吉布提" />
                <el-option label="吉尔吉斯斯坦" value="吉尔吉斯斯坦" />
                <el-option label="几内亚" value="几内亚" />
                <el-option label="几内亚比绍" value="几内亚比绍" />
                <el-option label="加拿大" value="加拿大" />
                <el-option label="加纳" value="加纳" />
                <el-option label="加蓬" value="加蓬" />
                <el-option label="柬埔寨" value="柬埔寨" />
                <el-option label="捷克" value="捷克" />
                <el-option label="津巴布韦" value="津巴布韦" />
                <el-option label="喀麦隆" value="喀麦隆" />
                <el-option label="卡塔尔" value="卡塔尔" />
                <el-option label="科摩罗" value="科摩罗" />
                <el-option label="科特迪瓦" value="科特迪瓦" />
                <el-option label="科威特" value="科威特" />
                <el-option label="克罗地亚" value="克罗地亚" />
                <el-option label="肯尼亚" value="肯尼亚" />
                <el-option label="库克群岛" value="库克群岛" />
                <el-option label="拉脱维亚" value="拉脱维亚" />
                <el-option label="莱索托" value="莱索托" />
                <el-option label="老挝" value="老挝" />
                <el-option label="黎巴嫩" value="黎巴嫩" />
                <el-option label="立陶宛" value="立陶宛" />
                <el-option label="利比里亚" value="利比里亚" />
                <el-option label="利比亚" value="利比亚" />
                <el-option label="列支敦士登" value="列支敦士登" />
                <el-option label="卢森堡" value="卢森堡" />
                <el-option label="卢旺达" value="卢旺达" />
                <el-option label="罗马尼亚" value="罗马尼亚" />
                <el-option label="马达加斯加" value="马达加斯加" />
                <el-option label="马尔代夫" value="马尔代夫" />
                <el-option label="马耳他" value="马耳他" />
                <el-option label="马拉维" value="马拉维" />
                <el-option label="马来西亚" value="马来西亚" />
                <el-option label="马里" value="马里" />
                <el-option label="马绍尔群岛" value="马绍尔群岛" />
                <el-option label="毛里求斯" value="毛里求斯" />
                <el-option label="毛里塔尼亚" value="毛里塔尼亚" />
                <el-option label="美国" value="美国" />
                <el-option label="蒙古" value="蒙古" />
                <el-option label="孟加拉国" value="孟加拉国" />
                <el-option label="秘鲁" value="秘鲁" />
                <el-option label="密克罗尼西亚" value="密克罗尼西亚" />
                <el-option label="缅甸" value="缅甸" />
                <el-option label="摩尔多瓦" value="摩尔多瓦" />
                <el-option label="摩洛哥" value="摩洛哥" />
                <el-option label="摩纳哥" value="摩纳哥" />
                <el-option label="莫桑比克" value="莫桑比克" />
                <el-option label="墨西哥" value="墨西哥" />
                <el-option label="纳米比亚" value="纳米比亚" />
                <el-option label="南非" value="南非" />
                <el-option label="南苏丹" value="南苏丹" />
                <el-option label="瑙鲁" value="瑙鲁" />
                <el-option label="尼泊尔" value="尼泊尔" />
                <el-option label="尼加拉瓜" value="尼加拉瓜" />
                <el-option label="尼日尔" value="尼日尔" />
                <el-option label="尼日利亚" value="尼日利亚" />
                <el-option label="挪威" value="挪威" />
                <el-option label="帕劳" value="帕劳" />
                <el-option label="葡萄牙" value="葡萄牙" />
                <el-option label="日本" value="日本" />
                <el-option label="瑞典" value="瑞典" />
                <el-option label="瑞士" value="瑞士" />
                <el-option label="萨尔瓦多" value="萨尔瓦多" />
                <el-option label="萨摩亚" value="萨摩亚" />
                <el-option label="塞尔维亚" value="塞尔维亚" />
                <el-option label="塞拉利昂" value="塞拉利昂" />
                <el-option label="塞内加尔" value="塞内加尔" />
                <el-option label="塞浦路斯" value="塞浦路斯" />
                <el-option label="塞舌尔" value="塞舌尔" />
                <el-option label="沙特阿拉伯" value="沙特阿拉伯" />
                <el-option label="圣多美和普林西比" value="圣多美和普林西比" />
                <el-option label="圣基茨和尼维斯" value="圣基茨和尼维斯" />
                <el-option label="圣卢西亚" value="圣卢西亚" />
                <el-option label="圣马力诺" value="圣马力诺" />
                <el-option label="圣文森特和格林纳丁斯" value="圣文森特和格林纳丁斯" />
                <el-option label="斯里兰卡" value="斯里兰卡" />
                <el-option label="斯洛伐克" value="斯洛伐克" />
                <el-option label="斯洛文尼亚" value="斯洛文尼亚" />
                <el-option label="斯威士兰" value="斯威士兰" />
                <el-option label="苏丹" value="苏丹" />
                <el-option label="苏里南" value="苏里南" />
                <el-option label="所罗门群岛" value="所罗门群岛" />
                <el-option label="索马里" value="索马里" />
                <el-option label="塔吉克斯坦" value="塔吉克斯坦" />
                <el-option label="泰国" value="泰国" />
                <el-option label="坦桑尼亚" value="坦桑尼亚" />
                <el-option label="汤加" value="汤加" />
                <el-option label="特立尼达和多巴哥" value="特立尼达和多巴哥" />
                <el-option label="突尼斯" value="突尼斯" />
                <el-option label="图瓦卢" value="图瓦卢" />
                <el-option label="土耳其" value="土耳其" />
                <el-option label="土库曼斯坦" value="土库曼斯坦" />
                <el-option label="瓦努阿图" value="瓦努阿图" />
                <el-option label="危地马拉" value="危地马拉" />
                <el-option label="委内瑞拉" value="委内瑞拉" />
                <el-option label="文莱" value="文莱" />
                <el-option label="乌干达" value="乌干达" />
                <el-option label="乌克兰" value="乌克兰" />
                <el-option label="乌拉圭" value="乌拉圭" />
                <el-option label="乌兹别克斯坦" value="乌兹别克斯坦" />
                <el-option label="西班牙" value="西班牙" />
                <el-option label="希腊" value="希腊" />
                <el-option label="新加坡" value="新加坡" />
                <el-option label="新西兰" value="新西兰" />
                <el-option label="匈牙利" value="匈牙利" />
                <el-option label="叙利亚" value="叙利亚" />
                <el-option label="牙买加" value="牙买加" />
                <el-option label="亚美尼亚" value="亚美尼亚" />
                <el-option label="也门" value="也门" />
                <el-option label="伊拉克" value="伊拉克" />
                <el-option label="伊朗" value="伊朗" />
                <el-option label="以色列" value="以色列" />
                <el-option label="意大利" value="意大利" />
                <el-option label="印度" value="印度" />
                <el-option label="印度尼西亚" value="印度尼西亚" />
                <el-option label="英国" value="英国" />
                <el-option label="约旦" value="约旦" />
                <el-option label="越南" value="越南" />
                <el-option label="赞比亚" value="赞比亚" />
                <el-option label="乍得" value="乍得" />
                <el-option label="智利" value="智利" />
                <el-option label="中非" value="中非" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="其他证件类型" prop="other_id_type">
              <el-select v-model="form.other_id_type" placeholder="请选择其他证件类型" :disabled="isViewMode" clearable>
                <el-option label="护照" value="passport" />
                <el-option label="港澳通行证" value="hk_macau_pass" />
                <el-option label="台湾通行证" value="taiwan_pass" />
                <el-option label="军官证" value="military_id" />
                <el-option label="其他" value="other" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="其他证件号码" prop="other_id_number">
              <el-input v-model="form.other_id_number" placeholder="请输入其他证件号码" :disabled="isViewMode" />
            </el-form-item>
          </el-col>
        </el-row>
        
        <!-- 二、从业任职信息 -->
        <el-divider content-position="left">从业任职信息</el-divider>
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="人员状态" prop="personnel_status">
              <el-select v-model="form.personnel_status" placeholder="请选择人员状态" :disabled="isViewMode" clearable>
                <el-option label="在职" value="active" />
                <el-option label="离职" value="resigned" />
                <el-option label="退休" value="retired" />
                <el-option label="停薪留职" value="unpaid_leave" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="任职受雇从业类型" prop="employment_type">
              <el-select v-model="form.employment_type" placeholder="请选择任职受雇从业类型" :disabled="isViewMode" clearable>
                <el-option label="雇员" value="雇员" />
                <el-option label="保险营销员" value="保险营销员" />
                <el-option label="证券经纪人" value="证券经纪人" />
                <el-option label="实习学生(全日制学历教育)" value="实习学生(全日制学历教育)" />
                <el-option label="平台内从业人员" value="平台内从业人员" />
                <el-option label="其他" value="其他" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="任职受雇从业日期" prop="employment_date">
              <el-date-picker
                v-model="form.employment_date"
                type="date"
                placeholder="请选择任职受雇从业日期"
                style="width: 100%"
                :disabled="isViewMode"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="离职日期" prop="resignation_date">
              <el-date-picker
                v-model="form.resignation_date"
                type="date"
                placeholder="请选择离职日期"
                style="width: 100%"
                :disabled="isViewMode"
              />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="入职年度就业情形" prop="annual_employment_status">
              <el-select v-model="form.annual_employment_status" placeholder="请选择入职年度就业情形" :disabled="isViewMode" clearable>
                <el-option label="当年首次入职学生" value="当年首次入职学生" />
                <el-option label="当年首次入职退役士兵" value="当年首次入职退役士兵" />
                <el-option label="雇佣出狱期大于1年且于2020年7月前入职" value="雇佣出狱期大于1年且于2020年7月前入职" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="职务" prop="job_title">
              <el-select v-model="form.job_title" placeholder="请选择职务" :disabled="isViewMode" clearable>
                <el-option label="普通" value="普通" />
                <el-option label="高管" value="高管" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        
        <!-- 三、特殊身份信息 -->
        <el-divider content-position="left">特殊身份信息</el-divider>
        <el-row :gutter="30">
          <el-col :span="8">
            <el-form-item label="是否残疾" prop="is_disabled">
              <el-switch v-model="form.is_disabled" :disabled="isViewMode" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="残疾证件类型" prop="disability_cert_type">
              <el-select v-model="form.disability_cert_type" placeholder="请选择残疾证件类型" :disabled="isViewMode || !form.is_disabled" clearable>
                <el-option label="残疾证" value="残疾证" />
                <el-option label="残疾军人证" value="残疾军人证" />
                <el-option label="伤残人民警察证" value="伤残人民警察证" />
                <el-option label="残疾消防救援人员证" value="残疾消防救援人员证" />
                <el-option label="伤残预备役人员" value="伤残预备役人员" />
                <el-option label="伤残民兵民工" value="伤残民兵民工" />
                <el-option label="因公伤残人员证" value="因公伤残人员证" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="残疾证号" prop="disability_cert_number">
              <el-input v-model="form.disability_cert_number" placeholder="请输入残疾证号" :disabled="isViewMode || !form.is_disabled" />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="8">
            <el-form-item label="是否烈属" prop="is_martyr_family">
              <el-switch v-model="form.is_martyr_family" :disabled="isViewMode" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="烈属证号" prop="martyr_family_cert_number">
              <el-input v-model="form.martyr_family_cert_number" placeholder="请输入烈属证号" :disabled="isViewMode || !form.is_martyr_family" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="是否孤老" prop="is_elderly_alone">
              <el-switch v-model="form.is_elderly_alone" :disabled="isViewMode" />
            </el-form-item>
          </el-col>
        </el-row>
        
        <!-- 四、涉税与投资信息 -->
        <el-divider content-position="left">涉税与投资信息</el-divider>
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="涉税事由" prop="tax_matter">
              <el-select v-model="form.tax_matter" placeholder="请选择涉税事由" :disabled="isViewMode" clearable>
                <el-option label="任职受雇" value="任职受雇" />
                <el-option label="提供临时劳务" value="提供临时劳务" />
                <el-option label="转让财产" value="转让财产" />
                <el-option label="从事投资和经营活动" value="从事投资和经营活动" />
                <el-option label="其他" value="其他" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="是否扣除减除费用" prop="deduct_expense">
              <el-switch v-model="form.deduct_expense" :disabled="isViewMode" />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="个人投资额" prop="personal_investment_amount">
              <el-input-number
                v-model="form.personal_investment_amount"
                :min="0"
                :precision="2"
                placeholder="请输入个人投资额"
                style="width: 100%"
                :disabled="isViewMode"
                :controls="false"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="个人投资比例(%)" prop="personal_investment_ratio">
              <el-input-number
                v-model="form.personal_investment_ratio"
                :min="0"
                :max="100"
                :precision="2"
                placeholder="请输入个人投资比例"
                style="width: 100%"
                :disabled="isViewMode"
                :controls="false"
              />
            </el-form-item>
          </el-col>
        </el-row>
        
        <!-- 五、出入境信息 -->
        <el-divider content-position="left">出入境信息</el-divider>
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="首次入境时间" prop="first_entry_date">
              <el-date-picker
                v-model="form.first_entry_date"
                type="date"
                placeholder="请选择首次入境时间"
                style="width: 100%"
                :disabled="isViewMode"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="预计离境时间" prop="expected_departure_date">
              <el-date-picker
                v-model="form.expected_departure_date"
                type="date"
                placeholder="请选择预计离境时间"
                style="width: 100%"
                :disabled="isViewMode"
              />
            </el-form-item>
          </el-col>
        </el-row>
        
        <!-- 六、联系方式与银行信息 -->
        <el-divider content-position="left">联系方式与银行信息</el-divider>
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="电子邮箱" prop="email_address">
              <el-input v-model="form.email_address" placeholder="请输入电子邮箱" :disabled="isViewMode" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="开户行省份" prop="bank_province">
              <el-input v-model="form.bank_province" placeholder="请输入开户行省份" :disabled="isViewMode" />
            </el-form-item>
          </el-col>
        </el-row>
        
        <!-- 七、地址信息 -->
        <el-divider content-position="left">地址信息</el-divider>
        
        <!-- 户籍所在地 -->
        <h4 style="margin: 20px 0 10px 0; color: #606266; font-size: 14px;">户籍所在地</h4>
        <el-row :gutter="30">
          <el-col :span="8">
            <el-form-item label="省份" prop="household_province">
              <el-input
                v-model="form.household_province"
                placeholder="请输入户籍所在省份"
                :disabled="isViewMode"
                clearable
              />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="城市" prop="household_city">
              <el-input
                v-model="form.household_city"
                placeholder="请输入户籍所在城市"
                :disabled="isViewMode"
                clearable
              />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="区县" prop="household_district">
              <el-input
                v-model="form.household_district"
                placeholder="请输入户籍所在区县"
                :disabled="isViewMode"
                clearable
              />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="30">
          <el-col :span="24">
            <el-form-item label="详细地址" prop="household_address">
              <el-input
                v-model="form.household_address"
                type="textarea"
                :rows="2"
                placeholder="请输入户籍详细地址"
                :disabled="isViewMode"
                clearable
              />
            </el-form-item>
          </el-col>
        </el-row>
        
        <!-- 经常居住地 -->
        <h4 style="margin: 20px 0 10px 0; color: #606266; font-size: 14px;">经常居住地</h4>
        <el-row :gutter="30">
          <el-col :span="8">
            <el-form-item label="省份" prop="residence_province">
              <el-input
                v-model="form.residence_province"
                placeholder="请输入经常居住省份"
                :disabled="isViewMode"
                clearable
              />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="城市" prop="residence_city">
              <el-input
                v-model="form.residence_city"
                placeholder="请输入经常居住城市"
                :disabled="isViewMode"
                clearable
              />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="区县" prop="residence_district">
              <el-input
                v-model="form.residence_district"
                placeholder="请输入经常居住区县"
                :disabled="isViewMode"
                clearable
              />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="30">
          <el-col :span="24">
            <el-form-item label="详细地址" prop="residence_address">
              <el-input
                v-model="form.residence_address"
                type="textarea"
                :rows="2"
                placeholder="请输入经常居住详细地址"
                :disabled="isViewMode"
                clearable
              />
            </el-form-item>
          </el-col>
        </el-row>
        
        <!-- 联系地址 -->
        <h4 style="margin: 20px 0 10px 0; color: #606266; font-size: 14px;">联系地址</h4>
        <el-row :gutter="30">
          <el-col :span="8">
            <el-form-item label="省份" prop="contact_province">
              <el-input
                v-model="form.contact_province"
                placeholder="请输入联系地址省份"
                :disabled="isViewMode"
                clearable
              />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="城市" prop="contact_city">
              <el-input
                v-model="form.contact_city"
                placeholder="请输入联系地址城市"
                :disabled="isViewMode"
                clearable
              />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="区县" prop="contact_district">
              <el-input
                v-model="form.contact_district"
                placeholder="请输入联系地址区县"
                :disabled="isViewMode"
                clearable
              />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="30">
          <el-col :span="24">
            <el-form-item label="详细地址" prop="contact_address">
              <el-input
                v-model="form.contact_address"
                type="textarea"
                :rows="2"
                placeholder="请输入联系详细地址"
                :disabled="isViewMode"
                clearable
              />
            </el-form-item>
          </el-col>
        </el-row>
        
        <!-- 其他信息 -->
        <h4 style="margin: 20px 0 10px 0; color: #606266; font-size: 14px;">其他信息</h4>
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="学历" prop="education">
              <el-select v-model="form.education" placeholder="请选择学历" :disabled="isViewMode" clearable>
                <el-option label="小学" value="小学" />
                <el-option label="初中" value="初中" />
                <el-option label="高中" value="高中" />
                <el-option label="中专" value="中专" />
                <el-option label="大专" value="大专" />
                <el-option label="本科" value="本科" />
                <el-option label="硕士" value="硕士" />
                <el-option label="博士" value="博士" />
                <el-option label="其他" value="其他" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="备注" prop="remarks">
              <el-input
                v-model="form.remarks"
                type="textarea"
                :rows="2"
                placeholder="请输入备注信息"
                :disabled="isViewMode"
                clearable
              />
            </el-form-item>
          </el-col>
        </el-row>

        <!-- 八、备注说明信息 -->
        <el-divider content-position="left">备注说明信息</el-divider>
        <el-row :gutter="30">
          <el-col :span="24">
            <el-form-item label="其他情况说明" prop="other_notes">
              <el-select v-model="form.other_notes" placeholder="请选择其他情况说明" :disabled="isViewMode" clearable>
                <el-option label="扣缴申报扣缴税款纳税人所得" value="扣缴申报扣缴税款纳税人所得" />
                <el-option label="申报缴纳所得" value="申报缴纳所得" />
                <el-option label="申报缴他所得" value="申报缴他所得" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        
        <!-- 基础工资字段已隐藏，暂时不需要 -->
        <!-- <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="基础工资" prop="basic_salary">
              <el-input-number
                v-model="form.basic_salary"
                :min="0"
                :max="9999999"
                :precision="2"
                placeholder="请输入基础工资"
                style="width: 100%"
                :disabled="isViewMode"
                :controls="false"
              />
              <div class="form-tip">员工的基础月薪</div>
            </el-form-item>
          </el-col>
        </el-row> -->
          </el-form>
        </el-tab-pane>
        
        <el-tab-pane label="保险信息" name="insurance">
          <el-form
            ref="formRef"
            :model="form"
            :rules="formRules"
            label-width="120px"
          >
        <!-- 保险基数信息 -->
        <el-divider content-position="left">保险基数</el-divider>
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="社保基数" prop="social_security_base">
              <el-input-number
                v-model="form.social_security_base"
                :min="0"
                :max="9999999"
                :precision="2"
                placeholder="请输入社保基数"
                style="width: 100%"
                :disabled="isViewMode"
                :controls="false"
              />
              <div class="form-tip">用于社保缴费计算的基数</div>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="医保基数" prop="medical_insurance_base">
              <el-input-number
                v-model="form.medical_insurance_base"
                :min="0"
                :max="9999999"
                :precision="2"
                placeholder="请输入医保基数"
                style="width: 100%"
                :disabled="isViewMode"
                :controls="false"
              />
              <div class="form-tip">用于医保缴费计算的基数</div>
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="6">
            <el-form-item label="公积金基数" prop="housing_fund_base">
              <el-input-number
                v-model="form.housing_fund_base"
                :min="0"
                :max="9999999"
                :precision="2"
                placeholder="请输入公积金基数"
                style="width: 100%"
                :disabled="isViewMode"
                :controls="false"
              />
              <div class="form-tip">用于公积金缴费计算的基数</div>
            </el-form-item>
          </el-col>
          <!-- 特殊地区：显示两个基数（禁用） -->
          <template v-if="isSpecialLargeMedicalRegion">
            <el-col :span="6">
              <el-form-item label="大额个人基数" prop="large_medical_base">
                <el-input-number
                  v-model="form.large_medical_base"
                  :min="0"
                  :max="9999999"
                  :precision="2"
                  placeholder="大额个人基数"
                  style="width: 100%"
                  disabled
                  :controls="false"
                />
                <div class="form-tip">特殊地区，从配置同步</div>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="大额公司基数" prop="large_medical_company_base">
                <el-input-number
                  v-model="form.large_medical_company_base"
                  :min="0"
                  :max="9999999"
                  :precision="2"
                  placeholder="大额公司基数"
                  style="width: 100%"
                  disabled
                  :controls="false"
                />
                <div class="form-tip">特殊地区，从配置同步</div>
              </el-form-item>
            </el-col>
          </template>
          <!-- 普通地区按基数：显示一个基数（可输入） -->
          <el-col :span="12" v-else-if="isLargeMedicalByBase">
            <el-form-item label="大额医疗基数" prop="large_medical_base">
              <el-input-number
                v-model="form.large_medical_base"
                :min="0"
                :max="9999999"
                :precision="2"
                placeholder="请输入大额医疗基数"
                style="width: 100%"
                :disabled="isViewMode"
                :controls="false"
              />
              <div class="form-tip">用于大额医疗缴费计算的基数</div>
            </el-form-item>
          </el-col>
          <!-- 固定金额类型：不显示基数 -->
        </el-row>
        
        <!-- 参保地区选择 -->
        <el-divider content-position="left">保险信息</el-divider>
        <el-row :gutter="30">
          <el-col :span="6">
            <el-form-item label="社保参保日期" prop="social_insurance_enrollment_date">
              <el-date-picker
                v-model="form.social_insurance_enrollment_date"
                type="month"
                placeholder="请选择社保参保日期"
                style="width: 100%"
                :disabled="isViewMode"
                format="YYYY-MM"
                value-format="YYYY-MM-DD"
              />
              <div class="form-tip">社保开始参保的月份</div>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="公积金参保日期" prop="provident_fund_enrollment_date">
              <el-date-picker
                v-model="form.provident_fund_enrollment_date"
                type="month"
                placeholder="请选择公积金参保日期"
                style="width: 100%"
                :disabled="isViewMode"
                format="YYYY-MM"
                value-format="YYYY-MM-DD"
              />
              <div class="form-tip">公积金开始参保的月份</div>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="医保参保日期" prop="medical_insurance_enrollment_date">
              <el-date-picker
                v-model="form.medical_insurance_enrollment_date"
                type="month"
                placeholder="请选择医保参保日期"
                style="width: 100%"
                :disabled="isViewMode"
                format="YYYY-MM"
                value-format="YYYY-MM-DD"
              />
              <div class="form-tip">医保开始参保的月份</div>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="大额参保日期" prop="large_medical_enrollment_date">
              <el-date-picker
                v-model="form.large_medical_enrollment_date"
                type="month"
                placeholder="请选择大额参保日期"
                style="width: 100%"
                :disabled="isViewMode"
                format="YYYY-MM"
                value-format="YYYY-MM-DD"
              />
              <div class="form-tip">大额医疗开始参保的月份（可选）</div>
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="8">
            <el-form-item label="社保参保地区" prop="social_security_region_id">
              <el-select
                v-model="form.social_security_region_id"
                placeholder="请选择社保参保地区"
                style="width: 100%"
                :disabled="isViewMode"
                @change="handleSocialSecurityRegionChange"
                clearable
              >
                <el-option
                  v-for="region in availableSocialSecurityRegions"
                  :key="region.id"
                  :label="region.region_name"
                  :value="region.id"
                />
              </el-select>
              <div class="form-tip">只能从员工所属项目设置的社保地区中选择</div>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="医保参保地区" prop="medical_insurance_region_id">
              <el-select
                v-model="form.medical_insurance_region_id"
                placeholder="请选择医保参保地区"
                style="width: 100%"
                :disabled="isViewMode"
                @change="handleMedicalInsuranceRegionChange"
                clearable
              >
                <el-option
                  v-for="region in availableMedicalInsuranceRegions"
                  :key="region.id"
                  :label="region.region_name"
                  :value="region.id"
                />
              </el-select>
              <div class="form-tip">只能从员工所属项目设置的医保地区中选择</div>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="公积金参保地区" prop="housing_fund_region_id">
              <el-select
                v-model="form.housing_fund_region_id"
                placeholder="请选择公积金参保地区"
                style="width: 100%"
                :disabled="isViewMode"
                @change="handleHousingFundRegionChange"
                clearable
              >
                <el-option
                  v-for="region in availableHousingFundRegions"
                  :key="region.id"
                  :label="region.region_name"
                  :value="region.id"
                />
              </el-select>
              <div class="form-tip">只能从员工所属项目设置的公积金地区中选择</div>
            </el-form-item>
          </el-col>
        </el-row>
        
        <!-- 公积金配置选择 -->
        <el-row :gutter="30" v-if="selectedHousingFundRegion">
          <el-col :span="8">
            <el-form-item label="公积金配置" prop="housing_fund_config_id">
              <el-select
                v-model="form.housing_fund_config_id"
                placeholder="请选择公积金配置"
                style="width: 100%"
                :disabled="isViewMode"
                @change="handleHousingFundConfigChange"
                clearable
              >
                <el-option
                  v-for="config in availableHousingFundConfigs"
                  :key="config.id"
                  :label="config.config_name"
                  :value="config.id"
                >
                  <span>{{ config.config_name }}</span>
                  <span style="float: right; color: #8492a6; font-size: 12px">
                    基数: ¥{{ config.base_amount }} | 员工: {{ (config.employee_ratio * 100).toFixed(2) }}% | 公司: {{ (config.company_ratio * 100).toFixed(2) }}%
                  </span>
                </el-option>
              </el-select>
              <div class="form-tip">选择该地区下的具体公积金配置</div>
            </el-form-item>
          </el-col>
        </el-row>
        
        <!-- 大额医疗保险配置选择 -->
        <el-row :gutter="30">
          <el-col :span="8">
            <el-form-item label="大额医疗保险" prop="large_medical_insurance_config_id">
              <el-select
                v-model="form.large_medical_insurance_config_id"
                placeholder="请选择大额医疗保险配置"
                style="width: 100%"
                :disabled="isViewMode"
                @change="handleLargeMedicalInsuranceConfigChange"
                clearable
              >
                <el-option
                  v-for="config in availableLargeMedicalInsuranceConfigs"
                  :key="config.id"
                  :label="`${config.region_name} (${config.calculation_type === 'base' ? '按基数' : '固定金额'})`"
                  :value="config.id"
                >
                  <span>{{ config.region_name }}</span>
                  <span style="float: right; color: #8492a6; font-size: 12px">
                    {{ config.calculation_type === 'base' ? '按基数' : '固定金额' }}
                  </span>
                </el-option>
              </el-select>
              <div class="form-tip">只能从员工所属项目设置的大额医疗保险配置中选择</div>
            </el-form-item>
          </el-col>
          <!-- 大额医疗保险开启按钮 -->
          <el-col :span="8" v-if="isEdit && currentLargeMedicalStatus">
            <el-form-item label="大额参保状态">
              <div style="display: flex; align-items: center; gap: 10px;">
                <el-tag :type="getLargeMedicalButtonType(currentLargeMedicalStatus)">
                  {{ currentLargeMedicalStatus.status_text }}
                </el-tag>
                <el-button 
                  v-if="currentLargeMedicalStatus.can_enable"
                  type="primary" 
                  size="small"
                  @click="handleEnableLargeMedicalInDialog"
                >
                  开启大额参保
                </el-button>
              </div>
              <div class="form-tip">
                <span v-if="currentLargeMedicalStatus.status === 'enrolled'">大额医疗保险已参保</span>
                <span v-else-if="currentLargeMedicalStatus.status === 'pending'">已创建开启任务，请在增减管理中确认处理</span>
                <span v-else-if="currentLargeMedicalStatus.status === 'can_enable'">点击按钮开启大额医疗保险参保</span>
                <span v-else-if="currentLargeMedicalStatus.status === 'not_onboarded'">员工尚未完成入职流程</span>
              </div>
            </el-form-item>
          </el-col>
        </el-row>
        
        <!-- 参保配置详情显示 -->
        <div v-if="selectedSocialSecurityRegion" class="insurance-details">
          <h4>社保配置详情</h4>
          <el-table :data="selectedSocialSecurityRegion.social_security_types" size="small" border>
            <el-table-column prop="name" label="保险类型" />
            <el-table-column prop="employee_ratio" label="个人比例" />
            <el-table-column prop="company_ratio" label="公司比例" />
          </el-table>
        </div>
        
        <div v-if="selectedHousingFundConfig" class="insurance-details">
          <h4>公积金配置详情</h4>
          <el-descriptions :column="2" size="small" border>
            <el-descriptions-item label="配置名称">{{ selectedHousingFundConfig.config_name }}</el-descriptions-item>
            <el-descriptions-item label="地区">{{ selectedHousingFundRegion?.region_name }}</el-descriptions-item>
            <el-descriptions-item label="个人比例">{{ (parseFloat(selectedHousingFundConfig.employee_ratio || 0) * 100).toFixed(2) }}%</el-descriptions-item>
            <el-descriptions-item label="公司比例">{{ (parseFloat(selectedHousingFundConfig.company_ratio || 0) * 100).toFixed(2) }}%</el-descriptions-item>
            <el-descriptions-item label="总比例" :span="2">{{ ((parseFloat(selectedHousingFundConfig.employee_ratio || 0) + parseFloat(selectedHousingFundConfig.company_ratio || 0)) * 100).toFixed(2) }}%</el-descriptions-item>
          </el-descriptions>
        </div>
        
        <!-- 医保配置详情显示 -->
        <div v-if="selectedMedicalInsuranceRegion" class="insurance-details">
          <h4>医保配置详情</h4>
          <el-table :data="selectedMedicalInsuranceRegion.medical_insurance_types || []" size="small" border>
            <el-table-column prop="name" label="保险类型">
              <template #default="{ row }">
                {{ row.name || '-' }}
              </template>
            </el-table-column>
            <el-table-column prop="employee_ratio" label="个人比例">
              <template #default="{ row }">
                {{ row.employee_ratio ? (row.employee_ratio * 100).toFixed(2) + '%' : '-' }}
              </template>
            </el-table-column>
            <el-table-column prop="company_ratio" label="公司比例">
              <template #default="{ row }">
                {{ row.company_ratio ? (row.company_ratio * 100).toFixed(2) + '%' : '-' }}
              </template>
            </el-table-column>
          </el-table>
        </div>
        
        <!-- 其他保险信息显示 -->
        <div v-if="projectOtherInsurancePolicies && projectOtherInsurancePolicies.length > 0" class="insurance-details">
          <h4>其他保险信息</h4>
          <el-table :data="projectOtherInsurancePolicies" size="small" border>
            <el-table-column prop="name" label="保险名称" />
            <el-table-column prop="type" label="保险类型">
              <template #default="{ row }">
                {{ typeof row.type === 'object' ? JSON.stringify(row.type) : row.type }}
              </template>
            </el-table-column>
            <el-table-column prop="coverage" label="保障内容">
              <template #default="{ row }">
                {{ formatCoverageContent(row.coverage) }}
              </template>
            </el-table-column>
          </el-table>
          <div class="form-tip">该项目绑定的其他保险，无需选择地区</div>
        </div>
          </el-form>
        </el-tab-pane>
        
        <el-tab-pane label="资料上传" name="documents">
          <EmployeeDocumentManager 
            v-if="form.id" 
            :employee-id="form.id" 
          />
        </el-tab-pane>
        
        <el-tab-pane label="个人信息" name="personal" v-if="registrationFormType === 'onboarding'">
          <div v-loading="onboardingFormLoading" style="min-height: 400px;">
            <el-empty v-if="!onboardingFormLoading && (!onboardingForm || Object.keys(onboardingForm).length === 0)" description="该员工尚未填写入职登记表" />
            <el-form v-else-if="onboardingForm" :model="onboardingForm" label-width="120px" disabled>
              <!-- 基本信息 -->
              <el-divider content-position="left">基本信息</el-divider>
              
              <!-- 寸照显示 -->
              <el-row :gutter="20" style="margin-bottom: 20px;">
                <el-col :span="24">
                  <el-form-item label="一寸照片">
                    <div v-if="onboardingForm?.photo" class="photo-display">
                      <img :src="onboardingForm.photo" alt="员工寸照" style="width: 100px; height: 130px; object-fit: cover; border: 1px solid #dcdfe6; border-radius: 4px;" />
                    </div>
                    <el-text v-else type="info">该员工尚未上传寸照</el-text>
                  </el-form-item>
                </el-col>
              </el-row>
              
              <el-row :gutter="20">
                <el-col :span="24">
                  <el-form-item label="登记日期">
                    <el-input :value="formatDate(onboardingForm?.registration_date)" placeholder="-" />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row :gutter="20">
                <el-col :span="12">
                  <el-form-item label="性别">
                    <el-input :value="onboardingForm?.gender === 'male' ? '男' : (onboardingForm?.gender === 'female' ? '女' : '-')" placeholder="-" />
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="民族">
                    <el-input :value="onboardingForm?.ethnicity || '-'" placeholder="-" />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row :gutter="20">
                <el-col :span="12">
                  <el-form-item label="政治面貌">
                    <el-input :value="onboardingForm?.political_status || '-'" placeholder="-" />
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="籍贯">
                    <el-input :value="onboardingForm?.place_of_origin || '-'" placeholder="-" />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row :gutter="20">
                <el-col :span="12">
                  <el-form-item label="出生年月">
                    <el-input :value="formatDate(onboardingForm?.birth_date)" placeholder="-" />
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="身份证号码">
                    <el-input :value="onboardingForm?.id_number || '-'" placeholder="-" />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row :gutter="20">
                <el-col :span="12">
                  <el-form-item label="现居住地">
                    <el-input :value="onboardingForm?.current_residence || '-'" placeholder="-" />
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="户口所在地">
                    <el-input :value="onboardingForm?.household_registration || '-'" placeholder="-" />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row :gutter="20">
                <el-col :span="12">
                  <el-form-item label="婚姻状况">
                    <el-input :value="getMaritalStatusText(onboardingForm?.marital_status)" placeholder="-" />
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="健康状况">
                    <el-input :value="onboardingForm?.health_status || '-'" placeholder="-" />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row :gutter="20">
                <el-col :span="12">
                  <el-form-item label="身高(cm)">
                    <el-input :value="onboardingForm?.height ? onboardingForm.height : '-'" placeholder="-" />
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="体重(kg)">
                    <el-input :value="onboardingForm?.weight ? onboardingForm.weight : '-'" placeholder="-" />
                  </el-form-item>
                </el-col>
              </el-row>

              <!-- 教育信息 -->
              <el-divider content-position="left">教育信息</el-divider>
              <el-row :gutter="20">
                <el-col :span="12">
                  <el-form-item label="毕业学校">
                    <el-input :value="onboardingForm?.graduated_school || '-'" placeholder="-" />
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="毕业时间">
                    <el-input :value="formatDate(onboardingForm?.graduation_date)" placeholder="-" />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row :gutter="20">
                <el-col :span="12">
                  <el-form-item label="文化程度">
                    <el-input :value="onboardingForm?.education_level || '-'" placeholder="-" />
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="学历性质">
                    <el-input :value="onboardingForm?.education_type || '-'" placeholder="-" />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row :gutter="20">
                <el-col :span="12">
                  <el-form-item label="所学专业">
                    <el-input :value="onboardingForm?.major || '-'" placeholder="-" />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row :gutter="20">
                <el-col :span="12">
                  <el-form-item label="学位">
                    <el-input :value="onboardingForm?.degree || '-'" placeholder="-" />
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="技术职称">
                    <el-input :value="onboardingForm?.technical_title || '-'" placeholder="-" />
                  </el-form-item>
                </el-col>
              </el-row>

              <!-- 学习简历 -->
              <el-divider content-position="left">学习简历</el-divider>
              <template v-if="onboardingForm?.education_background && onboardingForm.education_background.length > 0">
                <div v-for="(item, index) in onboardingForm.education_background" :key="index" class="experience-card">
                  <el-card shadow="hover" :body-style="{ padding: '20px' }">
                    <template #header>
                      <div class="card-header">
                        <el-tag type="info" size="small" effect="plain">学习经历 {{ index + 1 }}</el-tag>
                        <span class="time-range">{{ item.start_date || '-' }} 至 {{ item.end_date || '-' }}</span>
                      </div>
                    </template>
                    <el-row :gutter="20">
                      <el-col :span="24">
                        <div class="info-item">
                          <span class="info-label">学校：</span>
                          <span class="info-value">{{ item.school || '-' }}</span>
                        </div>
                      </el-col>
                    </el-row>
                    <el-row :gutter="20" style="margin-top: 12px;">
                      <el-col :span="12">
                        <div class="info-item">
                          <span class="info-label">学习层次：</span>
                          <span class="info-value">{{ item.level || '-' }}</span>
                        </div>
                      </el-col>
                      <el-col :span="12">
                        <div class="info-item">
                          <span class="info-label">证明人：</span>
                          <span class="info-value">{{ item.certifier || '-' }}</span>
                        </div>
                      </el-col>
                    </el-row>
                  </el-card>
                </div>
              </template>
              <el-form-item v-else label="">
                <el-empty description="暂无学习简历" :image-size="80" />
              </el-form-item>

              <!-- 工作经历 -->
              <el-divider content-position="left">工作经历</el-divider>
              <template v-if="onboardingForm?.work_experience && onboardingForm.work_experience.length > 0">
                <div v-for="(item, index) in onboardingForm.work_experience" :key="index" class="experience-card">
                  <el-card shadow="hover" :body-style="{ padding: '20px' }">
                    <template #header>
                      <div class="card-header">
                        <el-tag type="primary" size="small" effect="plain">工作经历 {{ index + 1 }}</el-tag>
                        <span class="time-range">{{ item.start_date || '-' }} 至 {{ item.end_date || '-' }}</span>
                      </div>
                    </template>
                    <el-row :gutter="20">
                      <el-col :span="24">
                        <div class="info-item">
                          <span class="info-label">工作单位：</span>
                          <span class="info-value">{{ item.employer || '-' }}</span>
                        </div>
                      </el-col>
                    </el-row>
                    <el-row :gutter="20" style="margin-top: 12px;">
                      <el-col :span="12">
                        <div class="info-item">
                          <span class="info-label">证明人：</span>
                          <span class="info-value">{{ item.certifier || '-' }}</span>
                        </div>
                      </el-col>
                    </el-row>
                    <el-row :gutter="20" style="margin-top: 12px;" v-if="item.job_content">
                      <el-col :span="24">
                        <div class="info-item">
                          <span class="info-label">主要工作内容：</span>
                          <div class="info-content">{{ item.job_content || '-' }}</div>
                        </div>
                      </el-col>
                    </el-row>
                  </el-card>
                </div>
              </template>
              <el-form-item v-else label="">
                <el-empty description="暂无工作经历" :image-size="80" />
              </el-form-item>

              <!-- 家庭情况 -->
              <el-divider content-position="left">家庭情况</el-divider>
              <template v-if="onboardingForm?.family_info && onboardingForm.family_info.length > 0">
                <el-row :gutter="20">
                  <el-col :span="12" v-for="(item, index) in onboardingForm.family_info" :key="index" style="margin-bottom: 20px;">
                    <el-card shadow="hover" :body-style="{ padding: '20px' }" class="family-card">
                      <template #header>
                        <div class="card-header">
                          <el-tag type="success" size="small" effect="plain">家庭成员 {{ index + 1 }}</el-tag>
                        </div>
                      </template>
                      <div class="family-info">
                        <div class="info-item">
                          <span class="info-label">姓名：</span>
                          <span class="info-value">{{ item.name || '-' }}</span>
                        </div>
                        <div class="info-item">
                          <span class="info-label">关系：</span>
                          <span class="info-value">{{ item.relationship || '-' }}</span>
                        </div>
                        <div class="info-item">
                          <span class="info-label">所在单位：</span>
                          <span class="info-value">{{ item.employer || '-' }}</span>
                        </div>
                        <div class="info-item">
                          <span class="info-label">联系电话：</span>
                          <span class="info-value">{{ item.phone || '-' }}</span>
                        </div>
                      </div>
                    </el-card>
                  </el-col>
                </el-row>
              </template>
              <el-form-item v-else label="">
                <el-empty description="暂无家庭情况" :image-size="80" />
              </el-form-item>

              <!-- 就业信息 -->
              <el-divider content-position="left">就业信息</el-divider>
              <el-row :gutter="20">
                <el-col :span="12">
                  <el-form-item label="岗位">
                    <el-input :value="onboardingForm?.position || '-'" placeholder="-" />
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="求职地区">
                    <el-input :value="onboardingForm?.desired_location || '-'" placeholder="-" />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row :gutter="20">
                <el-col :span="12">
                  <el-form-item label="是否服从调配">
                    <el-input :value="onboardingForm?.accept_assignment ? '是' : '否'" placeholder="-" />
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="联系电话">
                    <el-input :value="onboardingForm?.contact_phone || '-'" placeholder="-" />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-form-item label="联系地址">
                <el-input :value="onboardingForm?.contact_address || '-'" placeholder="-" />
              </el-form-item>

              <!-- 备注 -->
              <el-divider content-position="left">备注</el-divider>
              <el-form-item label="备注">
                <el-input 
                  type="textarea" 
                  :rows="4"
                  :value="onboardingForm?.remarks || '-'" 
                  placeholder="-" 
                />
              </el-form-item>
              
              <!-- 签名 -->
              <el-divider content-position="left">本人签名</el-divider>
              <el-form-item label="签名">
                <div v-if="onboardingForm?.signature" class="signature-display">
                  <img :src="onboardingForm.signature" alt="员工签名" style="max-width: 400px; max-height: 200px; border: 1px solid #dcdfe6; padding: 10px; border-radius: 4px; background: #fff;" />
                </div>
                <el-text v-else type="info">该员工尚未签名</el-text>
              </el-form-item>
            </el-form>
          </div>
        </el-tab-pane>
        
        <el-tab-pane label="从业人员登记表" name="registration" v-if="registrationFormType === 'registration'">
          <div v-loading="registrationFormLoading" style="min-height: 400px;">
            <el-empty v-if="!registrationFormLoading && (!registrationForm || Object.keys(registrationForm).length === 0)" description="该员工尚未填写从业人员登记表" />
            <template v-else-if="registrationForm">
              <!-- 导出PDF按钮 -->
              <div style="margin-bottom: 20px; text-align: right;">
                <el-button type="primary" @click="exportRegistrationFormPdf(form.id)">
                  <el-icon><Download /></el-icon>
                  导出PDF
                </el-button>
              </div>
              
              <el-form :model="registrationForm" label-width="140px" disabled>
                <!-- 头部信息 -->
                <el-divider content-position="left">基本信息</el-divider>
                <el-row :gutter="20">
                  <el-col :span="8">
                    <el-form-item label="填表日期">
                      <el-input :value="formatDate(registrationForm?.fill_date)" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="8">
                    <el-form-item label="部门">
                      <el-input :value="registrationForm?.department || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="8">
                    <el-form-item label="职务">
                      <el-input :value="registrationForm?.position || '-'" />
                    </el-form-item>
                  </el-col>
                </el-row>
                <el-row :gutter="20">
                  <el-col :span="8">
                    <el-form-item label="入职职位">
                      <el-input :value="registrationForm?.entry_position || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="8">
                    <el-form-item label="入职日期">
                      <el-input :value="formatDate(registrationForm?.entry_date)" />
                    </el-form-item>
                  </el-col>
                </el-row>
                <el-row :gutter="20">
                  <el-col :span="8">
                    <el-form-item label="公积金账户">
                      <el-input :value="registrationForm?.housing_fund_account || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="8">
                    <el-form-item label="银行账号">
                      <el-input :value="registrationForm?.bank_account || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="8">
                    <el-form-item label="开户支行">
                      <el-input :value="registrationForm?.bank_branch || '-'" />
                    </el-form-item>
                  </el-col>
                </el-row>
                
                <!-- 一、个人资料 -->
                <el-divider content-position="left">一、个人资料</el-divider>
                <el-row :gutter="20">
                  <el-col :span="8">
                    <el-form-item label="姓名">
                      <el-input :value="registrationForm?.name || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="8">
                    <el-form-item label="英文名">
                      <el-input :value="registrationForm?.english_name || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="8">
                    <el-form-item label="性别">
                      <el-input :value="registrationForm?.gender === 'male' ? '男' : (registrationForm?.gender === 'female' ? '女' : '-')" />
                    </el-form-item>
                  </el-col>
                </el-row>
                <el-row :gutter="20">
                  <el-col :span="8">
                    <el-form-item label="身高(cm)">
                      <el-input :value="registrationForm?.height || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="8">
                    <el-form-item label="出生日期">
                      <el-input :value="formatDate(registrationForm?.birth_date)" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="8">
                    <el-form-item label="政治面貌">
                      <el-input :value="registrationForm?.political_status || '-'" />
                    </el-form-item>
                  </el-col>
                </el-row>
                <el-row :gutter="20">
                  <el-col :span="8">
                    <el-form-item label="文化程度">
                      <el-input :value="registrationForm?.education_level || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="8">
                    <el-form-item label="学历性质">
                      <el-input :value="registrationForm?.education_type || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="8">
                    <el-form-item label="籍贯">
                      <el-input :value="registrationForm?.native_place || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="8">
                    <el-form-item label="婚姻状况">
                      <el-input :value="registrationForm?.marital_status || '-'" />
                    </el-form-item>
                  </el-col>
                </el-row>
                <el-row :gutter="20">
                  <el-col :span="8">
                    <el-form-item label="是否有子女">
                      <el-input :value="registrationForm?.has_children || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="8">
                    <el-form-item label="身份证号">
                      <el-input :value="registrationForm?.id_number || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="8">
                    <el-form-item label="户口状态">
                      <el-input :value="registrationForm?.household_type || '-'" />
                    </el-form-item>
                  </el-col>
                </el-row>
                <el-row :gutter="20">
                  <el-col :span="12">
                    <el-form-item label="现居住地址">
                      <el-input :value="registrationForm?.current_address || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="12">
                    <el-form-item label="邮编">
                      <el-input :value="registrationForm?.postal_code || '-'" />
                    </el-form-item>
                  </el-col>
                </el-row>
                <el-row :gutter="20">
                  <el-col :span="12">
                    <el-form-item label="户口地址">
                      <el-input :value="registrationForm?.household_address || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="12">
                    <el-form-item label="联系电话">
                      <el-input :value="registrationForm?.contact_phone || '-'" />
                    </el-form-item>
                  </el-col>
                </el-row>
                <el-row :gutter="20">
                  <el-col :span="12">
                    <el-form-item label="文书送达地址">
                      <el-input :value="registrationForm?.document_delivery_address || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="12">
                    <el-form-item label="残疾证">
                      <el-input :value="registrationForm?.disability_certificate || '-'" />
                    </el-form-item>
                  </el-col>
                </el-row>
                
                <!-- 二、个人技能 -->
                <el-divider content-position="left">二、个人技能</el-divider>
                <el-row :gutter="20">
                  <el-col :span="12">
                    <el-form-item label="语言能力">
                      <el-input :value="registrationForm?.language_skills || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="12">
                    <el-form-item label="工程证书">
                      <el-input :value="registrationForm?.engineering_certificates || '-'" />
                    </el-form-item>
                  </el-col>
                </el-row>
                <el-row :gutter="20">
                  <el-col :span="12">
                    <el-form-item label="职称">
                      <el-input :value="registrationForm?.professional_title || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="12">
                    <el-form-item label="兴趣爱好">
                      <el-input :value="registrationForm?.hobbies || '-'" />
                    </el-form-item>
                  </el-col>
                </el-row>
                <el-form-item label="其他技能">
                  <el-input type="textarea" :rows="2" :value="registrationForm?.other_skills || '-'" />
                </el-form-item>
                
                <!-- 三、教育情况 -->
                <el-divider content-position="left">三、教育情况</el-divider>
                <template v-if="registrationForm?.education_history && registrationForm.education_history.length > 0">
                  <el-table :data="registrationForm.education_history" border style="width: 100%; margin-bottom: 20px;">
                    <el-table-column prop="period" label="起止时间" width="200" />
                    <el-table-column prop="school" label="学校及专业" />
                    <el-table-column prop="certificate" label="所获证书" width="200" />
                  </el-table>
                </template>
                <el-empty v-else description="暂无教育经历" :image-size="60" />
                
                <!-- 四、工作履历 -->
                <el-divider content-position="left">四、工作履历</el-divider>
                <template v-if="registrationForm?.work_history && registrationForm.work_history.length > 0">
                  <el-table :data="registrationForm.work_history" border style="width: 100%; margin-bottom: 20px;">
                    <el-table-column prop="period" label="起止时间" width="150" />
                    <el-table-column prop="company" label="公司" />
                    <el-table-column prop="position" label="职位" width="120" />
                    <el-table-column prop="salary" label="薪酬" width="100" />
                    <el-table-column prop="leave_reason" label="离职原因" width="150" />
                  </el-table>
                </template>
                <el-empty v-else description="暂无工作经历" :image-size="60" />
                <el-row :gutter="20">
                  <el-col :span="12">
                    <el-form-item label="前单位名称">
                      <el-input :value="registrationForm?.previous_company || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="12">
                    <el-form-item label="背调联系人/电话">
                      <el-input :value="registrationForm?.reference_contact || '-'" />
                    </el-form-item>
                  </el-col>
                </el-row>
                
                <!-- 五、奖罚情况 -->
                <el-divider content-position="left">五、奖罚情况</el-divider>
                <el-form-item label="奖罚描述">
                  <el-input type="textarea" :rows="3" :value="registrationForm?.rewards_punishments || '-'" />
                </el-form-item>
                
                <!-- 六、家庭情况 -->
                <el-divider content-position="left">六、家庭情况</el-divider>
                <template v-if="registrationForm?.family_members && registrationForm.family_members.length > 0">
                  <el-table :data="registrationForm.family_members" border style="width: 100%; margin-bottom: 20px;">
                    <el-table-column prop="name" label="姓名" width="120" />
                    <el-table-column prop="relationship" label="关系" width="100" />
                    <el-table-column prop="age" label="年龄" width="80" />
                    <el-table-column prop="workplace" label="工作单位" />
                    <el-table-column prop="phone" label="电话" width="150" />
                  </el-table>
                </template>
                <el-empty v-else description="暂无家庭成员信息" :image-size="60" />
                
                <!-- 七、紧急联系方式 -->
                <el-divider content-position="left">七、紧急联系方式</el-divider>
                <el-row :gutter="20">
                  <el-col :span="8">
                    <el-form-item label="第一联系人">
                      <el-input :value="registrationForm?.emergency_contact1_name || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="8">
                    <el-form-item label="与己关系">
                      <el-input :value="registrationForm?.emergency_contact1_relationship || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="8">
                    <el-form-item label="联系电话">
                      <el-input :value="registrationForm?.emergency_contact1_phone || '-'" />
                    </el-form-item>
                  </el-col>
                </el-row>
                <el-row :gutter="20">
                  <el-col :span="8">
                    <el-form-item label="第二联系人">
                      <el-input :value="registrationForm?.emergency_contact2_name || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="8">
                    <el-form-item label="与己关系">
                      <el-input :value="registrationForm?.emergency_contact2_relationship || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="8">
                    <el-form-item label="联系电话">
                      <el-input :value="registrationForm?.emergency_contact2_phone || '-'" />
                    </el-form-item>
                  </el-col>
                </el-row>
                
                <!-- 八、其他情况 -->
                <el-divider content-position="left">八、其他情况</el-divider>
                <el-row :gutter="20">
                  <el-col :span="12">
                    <el-form-item label="精神病史">
                      <el-input :value="registrationForm?.mental_illness || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="12">
                    <el-form-item label="其他疾病">
                      <el-input :value="registrationForm?.other_diseases || '-'" />
                    </el-form-item>
                  </el-col>
                </el-row>
                <el-row :gutter="20">
                  <el-col :span="12">
                    <el-form-item label="近6月住院记录">
                      <el-input :value="registrationForm?.hospitalization_record || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="12">
                    <el-form-item label="违法犯罪记录">
                      <el-input :value="registrationForm?.criminal_record || '-'" />
                    </el-form-item>
                  </el-col>
                </el-row>
                <el-form-item label="就业证件">
                  <el-input :value="registrationForm?.employment_documents || '-'" />
                </el-form-item>
                
                <!-- 九、其他需要说明的情况 -->
                <el-divider content-position="left">九、其他需要说明的情况</el-divider>
                <el-form-item label="备注说明">
                  <el-input type="textarea" :rows="3" :value="registrationForm?.additional_notes || '-'" />
                </el-form-item>
                
                <!-- 十、其他需要核实的情况 -->
                <el-divider content-position="left">十、其他需要核实的情况</el-divider>
                <el-row :gutter="20">
                  <el-col :span="12">
                    <el-form-item label="是否怀孕">
                      <el-input :value="registrationForm?.pregnancy_status || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="12">
                    <el-form-item label="接受加班出差">
                      <el-input :value="registrationForm?.accept_overtime || '-'" />
                    </el-form-item>
                  </el-col>
                </el-row>
                <el-row :gutter="20">
                  <el-col :span="12">
                    <el-form-item label="需要住宿">
                      <el-input :value="registrationForm?.need_accommodation || '-'" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="12">
                    <el-form-item label="是否有驾照">
                      <el-input :value="registrationForm?.has_driving_license || '-'" />
                    </el-form-item>
                  </el-col>
                </el-row>
                
                <!-- 签名 -->
                <el-divider content-position="left">申请人签名</el-divider>
                <el-form-item label="签名">
                  <div v-if="registrationForm?.signature" class="signature-display">
                    <img :src="registrationForm.signature" alt="申请人签名" style="max-width: 400px; max-height: 200px; border: 1px solid #dcdfe6; padding: 10px; border-radius: 4px; background: #fff;" />
                  </div>
                  <el-text v-else type="info">该员工尚未签名</el-text>
                </el-form-item>
                <el-form-item label="签名日期">
                  <el-input :value="formatDate(registrationForm?.signature_date)" />
                </el-form-item>
              </el-form>
            </template>
          </div>
        </el-tab-pane>
        
        <el-tab-pane label="工资" name="salary">
          <el-form
            :model="form"
            label-width="120px"
            :disabled="isViewMode"
          >
            <el-divider content-position="left">工资信息</el-divider>

            <el-alert
              v-if="pendingSalaryAdjustment"
              type="warning"
              :closable="false"
              style="margin-bottom: 16px;"
            >
              <template #title>
                当前有审核中的工资调整，审批通过后才会生效
              </template>
              <template #default>
                <div>审核中基础工资：¥{{ Number(pendingSalaryAdjustment.basic_salary || 0).toFixed(2) }}</div>
                <div style="margin-top: 6px;">审核中工资项：{{ formatSalaryItems(pendingSalaryAdjustment.salary_items) }}</div>
                <div v-if="pendingSalaryAdjustment.reason" style="margin-top: 6px;">申请原因：{{ pendingSalaryAdjustment.reason }}</div>
              </template>
            </el-alert>

            <el-row :gutter="20">
              <el-col :span="12">
                <el-form-item label="基础工资" prop="basic_salary">
                  <el-input-number
                    v-model="form.basic_salary"
                    :min="0"
                    :precision="2"
                    :step="100"
                    placeholder="请输入基础工资"
                    style="width: 100%"
                    controls-position="right"
                  />
                </el-form-item>
              </el-col>
            </el-row>

            <el-divider content-position="left">自定义工资项</el-divider>

            <div
              v-for="(item, index) in form.salary_items"
              :key="index"
              style="margin-bottom: 12px"
            >
              <el-row :gutter="12">
                <el-col :span="10">
                  <el-input
                    v-model="item.name"
                    placeholder="请输入工资项名称（如岗位工资）"
                    :disabled="isViewMode"
                    clearable
                  />
                </el-col>
                <el-col :span="10">
                  <el-input-number
                    v-model="item.amount"
                    :min="0"
                    :precision="2"
                    :step="100"
                    placeholder="请输入金额"
                    style="width: 100%"
                    controls-position="right"
                    :disabled="isViewMode"
                  />
                </el-col>
                <el-col :span="4" style="text-align: right">
                  <el-button
                    type="danger"
                    plain
                    @click="removeSalaryItem(index)"
                    :disabled="isViewMode"
                  >删除</el-button>
                </el-col>
              </el-row>
            </div>

            <el-button
              type="primary"
              plain
              @click="addSalaryItem"
              :disabled="isViewMode"
            >
              新增工资项
            </el-button>

            <div v-if="isEdit && !isViewMode" style="margin-top: 16px;">
              <el-button
                type="warning"
                @click="handleSubmitSalaryApproval"
                :loading="submittingSalaryApproval"
                :disabled="!!pendingSalaryAdjustment"
              >
                {{ pendingSalaryAdjustment ? '工资调整审批中' : '提交工资调整审批' }}
              </el-button>
            </div>
          </el-form>
        </el-tab-pane>

        <el-tab-pane label="工资卡" name="salary-card">
          <el-form
            ref="salaryCardFormRef"
            :model="form"
            :rules="salaryCardRules"
            label-width="120px"
            :disabled="isViewMode"
          >
            <el-divider content-position="left">工资卡信息</el-divider>

            <el-row :gutter="20">
              <el-col :span="12">
                <el-form-item label="银行账号" prop="bank_account">
                  <el-input
                    v-model="form.bank_account"
                    placeholder="请输入银行账号"
                    clearable
                  />
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="户名" prop="bank_account_holder">
                  <el-input
                    v-model="form.bank_account_holder"
                    placeholder="请输入户名"
                    clearable
                  />
                </el-form-item>
              </el-col>
            </el-row>

            <el-row :gutter="20">
              <el-col :span="12">
                <el-form-item label="开户行" prop="bank_name">
                  <el-input
                    v-model="form.bank_name"
                    placeholder="请输入开户行"
                    clearable
                  />
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="开户地" prop="bank_branch">
                  <el-input
                    v-model="form.bank_branch"
                    placeholder="请输入开户地/支行"
                    clearable
                  />
                </el-form-item>
              </el-col>
            </el-row>

            <el-row :gutter="20">
              <el-col :span="12">
                <el-form-item label="汇款备注" prop="remittance_remark">
                  <el-input
                    v-model="form.remittance_remark"
                    placeholder="请输入汇款备注"
                    clearable
                  />
                </el-form-item>
              </el-col>
            </el-row>

            <div class="form-tip">
              <el-text type="info" size="small">
                工资卡信息用于工资发放，请确保信息准确无误
              </el-text>
            </div>
          </el-form>
        </el-tab-pane>
        
        <el-tab-pane label="调动记录" name="transfer-logs">
          <el-button size="small" type="primary" @click="() => loadTransferLogs(form.id)" :loading="transferLogsLoading" style="margin-bottom:10px">刷新</el-button>
          <el-table v-if="transferLogs && transferLogs.length" :data="transferLogs" size="small" border>
            <el-table-column prop="changed_at" label="时间" width="170">
              <template #default="{ row }">{{ row.changed_at ? row.changed_at.substring(0,19).replace('T',' ') : '-' }}</template>
            </el-table-column>
            <el-table-column prop="from_project_name" label="从项目" min-width="160" />
            <el-table-column prop="to_project_name" label="至项目" min-width="160" />
            <el-table-column prop="operator_name" label="操作人" width="120" />
            <el-table-column prop="reason" label="备注" min-width="200" />
          </el-table>
          <el-empty v-else description="暂无调动记录" />
        </el-tab-pane>
        
        <el-tab-pane label="离职证明" name="resignation-certificate" v-if="form.id && ['terminated', 'retired'].includes(form.contract_status)">
          <div style="margin-bottom: 15px;">
            <el-upload
              :action="`/api/employees/${form.id}/resignation-certificates/upload`"
              :headers="{ Authorization: `Bearer ${getToken()}` }"
              :on-success="handleResignationCertificateUploadSuccess"
              :on-error="handleResignationCertificateUploadError"
              :before-upload="beforeResignationCertificateUpload"
              :show-file-list="false"
              multiple
            >
              <el-button size="small" type="primary" :loading="resignationCertificateUploading">
                <el-icon><Upload /></el-icon> 上传离职证明
              </el-button>
            </el-upload>
            <el-button size="small" @click="loadResignationCertificates" :loading="resignationCertificatesLoading" style="margin-left: 10px">
              <el-icon><Refresh /></el-icon> 刷新
            </el-button>
          </div>
          
          <el-table 
            v-if="resignationCertificates && resignationCertificates.length" 
            :data="resignationCertificates" 
            size="small" 
            border
            v-loading="resignationCertificatesLoading"
          >
            <el-table-column prop="file_name" label="文件名" min-width="200" />
            <el-table-column prop="file_size" label="文件大小" width="120">
              <template #default="{ row }">{{ formatFileSize(row.file_size) }}</template>
            </el-table-column>
            <el-table-column prop="upload_source" label="上传来源" width="100">
              <template #default="{ row }">
                <el-tag size="small" :type="row.upload_source === 'miniprogram' ? 'success' : 'info'">
                  {{ row.upload_source === 'miniprogram' ? '小程序' : 'PC端' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="uploaded_by_name" label="上传人" width="120" />
            <el-table-column prop="created_at" label="上传时间" width="170">
              <template #default="{ row }">{{ row.created_at ? row.created_at.substring(0,19).replace('T',' ') : '-' }}</template>
            </el-table-column>
            <el-table-column label="操作" width="200" fixed="right">
              <template #default="{ row }">
                <el-button size="small" type="success" link @click="previewResignationCertificate(row)">
                  <el-icon><View /></el-icon> 预览
                </el-button>
                <el-button size="small" type="primary" link @click="downloadResignationCertificate(row)">
                  <el-icon><Download /></el-icon> 下载
                </el-button>
                <el-button size="small" type="danger" link @click="deleteResignationCertificate(row)">
                  <el-icon><Delete /></el-icon> 删除
                </el-button>
              </template>
            </el-table-column>
          </el-table>
          <el-empty v-else description="暂无离职证明" />
        </el-tab-pane>
      </el-tabs>
      
      <!-- 新增员工时没有tab，直接显示表单 -->
      <el-form
        v-if="!isEdit && !isViewMode"
        ref="formRef"
        :model="form"
        :rules="formRules"
        label-width="120px"
      >
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="工号" prop="employee_number">
              <el-input 
                v-model="form.employee_number" 
                placeholder="自动生成" 
                readonly
              >
                <template #prepend>
                  <el-icon><Postcard /></el-icon>
                </template>
              </el-input>
              <div class="form-tip" style="color: #67C23A;">根据所选项目自动生成（如：AA001, AB001）</div>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="姓名" prop="name">
              <el-input v-model="form.name" placeholder="请输入姓名" />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="岗位" prop="position">
              <el-input v-model="form.position" placeholder="请输入岗位" />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="身份证号" prop="id_number">
              <el-input v-model="form.id_number" placeholder="请输入身份证号" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="手机号" prop="phone">
              <el-input v-model="form.phone" placeholder="请输入手机号" />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="身份证有效期开始" prop="id_card_valid_from">
              <el-date-picker
                v-model="form.id_card_valid_from"
                type="date"
                placeholder="请选择身份证有效期开始日期"
                style="width: 100%"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="身份证有效期至" prop="id_card_valid_until">
              <el-date-picker
                v-model="form.id_card_valid_until"
                type="date"
                placeholder="请选择身份证有效期至（长期有效可不填）"
                style="width: 100%"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
              />
              <div class="form-tip">长期有效可不填写</div>
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="性别" prop="gender">
              <el-radio-group v-model="form.gender">
                <el-radio label="male">男</el-radio>
                <el-radio label="female">女</el-radio>
              </el-radio-group>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="出生日期" prop="birth_date">
              <el-date-picker
                v-model="form.birth_date"
                type="date"
                placeholder="请选择出生日期"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="合同开始日期" prop="contract_start_date">
              <el-date-picker
                v-model="form.contract_start_date"
                type="date"
                placeholder="请选择合同开始日期"
                style="width: 100%"
                @change="handleContractStartDateChange"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="入职日期" prop="hire_date">
              <el-date-picker
                v-model="form.hire_date"
                type="date"
                placeholder="请选择入职日期"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="合同结束日期" prop="contract_end_date">
              <el-date-picker
                v-model="form.contract_end_date"
                type="date"
                placeholder="请选择合同结束日期"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="试用期结束日期" prop="probation_end_date">
              <el-date-picker
                v-model="form.probation_end_date"
                type="date"
                placeholder="请选择试用期结束日期"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="离职日期">
              <el-date-picker
                v-model="form.resignation_date"
                type="date"
                placeholder="离职日期（可不填）"
                style="width: 100%"
                clearable
              />
              <div class="form-tip" style="color: #909399; font-size: 12px; margin-top: 4px;">
                💡 填写后系统将在月底检查离职合同审批情况
              </div>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="签署地">
              <el-input
                v-model="form.signing_location"
                placeholder="请输入签署地"
                clearable
              />
              <div class="form-tip">合同签署地点信息</div>
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="所属项目" prop="project_ids">
              <el-select
                v-model="form.project_ids[0]"
                placeholder="请选择所属项目"
                style="width: 100%"
                @change="handleSingleProjectChange"
              >
                <el-option
                  v-for="project in projects"
                  :key="project.id"
                  :label="project.name"
                  :value="project.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        
        <!-- 示例填充按钮 -->
        <el-row style="margin-bottom: 20px;">
          <el-col :span="24" style="text-align: right;">
            <el-button type="primary" plain @click="fillSampleData">
              <el-icon><Star /></el-icon>
              示例填充
            </el-button>
          </el-col>
        </el-row>
        
        <!-- 基础工资字段已隐藏，暂时不需要 -->
        <!-- <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="基础工资" prop="basic_salary">
              <el-input-number
                v-model="form.basic_salary"
                :min="0"
                :max="9999999"
                :precision="2"
                placeholder="请输入基础工资"
                style="width: 100%"
                :controls="false"
              />
              <div class="form-tip">员工的基础月薪</div>
            </el-form-item>
          </el-col>
        </el-row> -->
        
        <!-- 工资卡信息 -->
        <el-divider content-position="left">工资卡信息</el-divider>
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="银行账号" prop="bank_account">
              <el-input
                v-model="form.bank_account"
                placeholder="请输入银行账号"
                clearable
              />
              <div class="form-tip">用于工资发放的银行账号</div>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="户名" prop="bank_account_holder">
              <el-input
                v-model="form.bank_account_holder"
                placeholder="请输入户名"
                clearable
              />
              <div class="form-tip">银行账户的户名</div>
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="开户行" prop="bank_name">
              <el-input
                v-model="form.bank_name"
                placeholder="请输入开户行"
                clearable
              />
              <div class="form-tip">银行名称</div>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="开户地" prop="bank_branch">
              <el-input
                v-model="form.bank_branch"
                placeholder="请输入开户地/支行"
                clearable
              />
              <div class="form-tip">具体的开户地或支行</div>
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="基础薪资" prop="basic_salary">
              <el-input-number
                v-model="form.basic_salary"
                :min="0"
                :max="9999999"
                :precision="2"
                :step="100"
                placeholder="请输入基础薪资"
                style="width: 100%"
                :disabled="isViewMode"
              />
              <div class="form-tip">员工的基础工资金额</div>
            </el-form-item>
          </el-col>
        </el-row>
        
        <!-- 保险基数信息 -->
        <el-divider content-position="left">保险基数</el-divider>
        <el-row :gutter="30">
          <el-col :span="12">
            <el-form-item label="社保基数" prop="social_security_base">
              <el-input-number
                v-model="form.social_security_base"
                :min="0"
                :max="9999999"
                :precision="2"
                placeholder="请输入社保基数"
                style="width: 100%"
                :controls="false"
              />
              <div class="form-tip">用于社保缴费计算的基数</div>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="医保基数" prop="medical_insurance_base">
              <el-input-number
                v-model="form.medical_insurance_base"
                :min="0"
                :max="9999999"
                :precision="2"
                placeholder="请输入医保基数"
                style="width: 100%"
                :controls="false"
              />
              <div class="form-tip">用于医保缴费计算的基数</div>
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="6">
            <el-form-item label="公积金基数" prop="housing_fund_base">
              <el-input-number
                v-model="form.housing_fund_base"
                :min="0"
                :max="9999999"
                :precision="2"
                placeholder="请输入公积金基数"
                style="width: 100%"
                :controls="false"
              />
              <div class="form-tip">用于公积金缴费计算的基数</div>
            </el-form-item>
          </el-col>
          <!-- 特殊地区：显示两个基数（禁用） -->
          <template v-if="isSpecialLargeMedicalRegion">
            <el-col :span="6">
              <el-form-item label="大额个人基数" prop="large_medical_base">
                <el-input-number
                  v-model="form.large_medical_base"
                  :min="0"
                  :max="9999999"
                  :precision="2"
                  placeholder="大额个人基数"
                  style="width: 100%"
                  disabled
                  :controls="false"
                />
                <div class="form-tip">特殊地区，从配置同步</div>
              </el-form-item>
            </el-col>
            <el-col :span="6">
              <el-form-item label="大额公司基数" prop="large_medical_company_base">
                <el-input-number
                  v-model="form.large_medical_company_base"
                  :min="0"
                  :max="9999999"
                  :precision="2"
                  placeholder="大额公司基数"
                  style="width: 100%"
                  disabled
                  :controls="false"
                />
                <div class="form-tip">特殊地区，从配置同步</div>
              </el-form-item>
            </el-col>
          </template>
          <!-- 普通地区按基数：显示一个基数（可输入） -->
          <el-col :span="12" v-else-if="isLargeMedicalByBase">
            <el-form-item label="大额医疗基数" prop="large_medical_base">
              <el-input-number
                v-model="form.large_medical_base"
                :min="0"
                :max="9999999"
                :precision="2"
                placeholder="请输入大额医疗基数"
                style="width: 100%"
                :controls="false"
              />
              <div class="form-tip">用于大额医疗缴费计算的基数</div>
            </el-form-item>
          </el-col>
          <!-- 固定金额类型：不显示基数 -->
        </el-row>
        
        <!-- 参保地区选择 -->
        <el-divider content-position="left">保险信息</el-divider>
        <el-row :gutter="30">
          <el-col :span="6">
            <el-form-item label="社保参保日期" prop="social_insurance_enrollment_date">
              <el-date-picker
                v-model="form.social_insurance_enrollment_date"
                type="month"
                placeholder="请选择社保参保日期"
                style="width: 100%"
                format="YYYY-MM"
                value-format="YYYY-MM-DD"
              />
              <div class="form-tip">社保开始参保的月份</div>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="公积金参保日期" prop="provident_fund_enrollment_date">
              <el-date-picker
                v-model="form.provident_fund_enrollment_date"
                type="month"
                placeholder="请选择公积金参保日期"
                style="width: 100%"
                format="YYYY-MM"
                value-format="YYYY-MM-DD"
              />
              <div class="form-tip">公积金开始参保的月份</div>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="医保参保日期" prop="medical_insurance_enrollment_date">
              <el-date-picker
                v-model="form.medical_insurance_enrollment_date"
                type="month"
                placeholder="请选择医保参保日期"
                style="width: 100%"
                format="YYYY-MM"
                value-format="YYYY-MM-DD"
              />
              <div class="form-tip">医保开始参保的月份</div>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="大额参保日期" prop="large_medical_enrollment_date">
              <el-date-picker
                v-model="form.large_medical_enrollment_date"
                type="month"
                placeholder="请选择大额参保日期"
                style="width: 100%"
                format="YYYY-MM"
                value-format="YYYY-MM-DD"
              />
              <div class="form-tip">大额医疗开始参保的月份（可选）</div>
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="30">
          <el-col :span="8">
            <el-form-item label="社保参保地区" prop="social_security_region_id">
              <el-select
                v-model="form.social_security_region_id"
                placeholder="请选择社保参保地区"
                style="width: 100%"
                @change="handleSocialSecurityRegionChange"
                clearable
              >
                <el-option
                  v-for="region in availableSocialSecurityRegions"
                  :key="region.id"
                  :label="region.region_name"
                  :value="region.id"
                />
              </el-select>
              <div class="form-tip">只能从员工所属项目设置的社保地区中选择</div>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="医保参保地区" prop="medical_insurance_region_id">
              <el-select
                v-model="form.medical_insurance_region_id"
                placeholder="请选择医保参保地区"
                style="width: 100%"
                @change="handleMedicalInsuranceRegionChange"
                clearable
              >
                <el-option
                  v-for="region in availableMedicalInsuranceRegions"
                  :key="region.id"
                  :label="region.region_name"
                  :value="region.id"
                />
              </el-select>
              <div class="form-tip">只能从员工所属项目设置的医保地区中选择</div>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="公积金参保地区" prop="housing_fund_region_id">
              <el-select
                v-model="form.housing_fund_region_id"
                placeholder="请选择公积金参保地区"
                style="width: 100%"
                @change="handleHousingFundRegionChange"
                clearable
              >
                <el-option
                  v-for="region in availableHousingFundRegions"
                  :key="region.id"
                  :label="region.region_name"
                  :value="region.id"
                />
              </el-select>
              <div class="form-tip">只能从员工所属项目设置的公积金地区中选择</div>
            </el-form-item>
          </el-col>
        </el-row>
        
        <!-- 公积金配置选择 -->
        <el-row :gutter="30" v-if="selectedHousingFundRegion">
          <el-col :span="8">
            <el-form-item label="公积金配置" prop="housing_fund_config_id">
              <el-select
                v-model="form.housing_fund_config_id"
                placeholder="请选择公积金配置"
                style="width: 100%"
                @change="handleHousingFundConfigChange"
                clearable
              >
                <el-option
                  v-for="config in availableHousingFundConfigs"
                  :key="config.id"
                  :label="config.config_name"
                  :value="config.id"
                >
                  <span>{{ config.config_name }}</span>
                  <span style="float: right; color: #8492a6; font-size: 12px">
                    基数: ¥{{ config.base_amount }} | 员工: {{ (config.employee_ratio * 100).toFixed(2) }}% | 公司: {{ (config.company_ratio * 100).toFixed(2) }}%
                  </span>
                </el-option>
              </el-select>
              <div class="form-tip">选择该地区下的具体公积金配置</div>
            </el-form-item>
          </el-col>
        </el-row>
        
        <!-- 大额医疗保险配置选择 -->
        <el-row :gutter="30">
          <el-col :span="8">
            <el-form-item label="大额医疗保险" prop="large_medical_insurance_config_id">
              <el-select
                v-model="form.large_medical_insurance_config_id"
                placeholder="请选择大额医疗保险配置"
                style="width: 100%"
                @change="handleLargeMedicalInsuranceConfigChange"
                clearable
              >
                <el-option
                  v-for="config in availableLargeMedicalInsuranceConfigs"
                  :key="config.id"
                  :label="`${config.region_name} (${config.calculation_type === 'base' ? '按基数' : '固定金额'})`"
                  :value="config.id"
                >
                  <span>{{ config.region_name }}</span>
                  <span style="float: right; color: #8492a6; font-size: 12px">
                    {{ config.calculation_type === 'base' ? '按基数' : '固定金额' }}
                  </span>
                </el-option>
              </el-select>
              <div class="form-tip">只能从员工所属项目设置的大额医疗保险配置中选择</div>
            </el-form-item>
          </el-col>
        </el-row>
        
        <!-- 参保配置详情显示 -->
        <div v-if="selectedSocialSecurityRegion" class="insurance-details">
          <h4>社保配置详情</h4>
          <el-table :data="selectedSocialSecurityRegion.social_security_types" size="small" border>
            <el-table-column prop="name" label="保险类型" />
            <el-table-column prop="employee_ratio" label="个人比例" />
            <el-table-column prop="company_ratio" label="公司比例" />
          </el-table>
        </div>
        
        <div v-if="selectedHousingFundConfig" class="insurance-details">
          <h4>公积金配置详情</h4>
          <el-descriptions :column="2" size="small" border>
            <el-descriptions-item label="配置名称">{{ selectedHousingFundConfig.config_name }}</el-descriptions-item>
            <el-descriptions-item label="地区">{{ selectedHousingFundRegion?.region_name }}</el-descriptions-item>
            <el-descriptions-item label="个人比例">{{ (parseFloat(selectedHousingFundConfig.employee_ratio || 0) * 100).toFixed(2) }}%</el-descriptions-item>
            <el-descriptions-item label="公司比例">{{ (parseFloat(selectedHousingFundConfig.company_ratio || 0) * 100).toFixed(2) }}%</el-descriptions-item>
            <el-descriptions-item label="总比例" :span="2">{{ ((parseFloat(selectedHousingFundConfig.employee_ratio || 0) + parseFloat(selectedHousingFundConfig.company_ratio || 0)) * 100).toFixed(2) }}%</el-descriptions-item>
          </el-descriptions>
        </div>
        
        <!-- 医保配置详情显示 -->
        <div v-if="selectedMedicalInsuranceRegion" class="insurance-details">
          <h4>医保配置详情</h4>
          <el-table :data="selectedMedicalInsuranceRegion.medical_insurance_types || []" size="small" border>
            <el-table-column prop="name" label="保险类型">
              <template #default="{ row }">
                {{ row.name || '-' }}
              </template>
            </el-table-column>
            <el-table-column prop="employee_ratio" label="个人比例">
              <template #default="{ row }">
                {{ row.employee_ratio ? (row.employee_ratio * 100).toFixed(2) + '%' : '-' }}
              </template>
            </el-table-column>
            <el-table-column prop="company_ratio" label="公司比例">
              <template #default="{ row }">
                {{ row.company_ratio ? (row.company_ratio * 100).toFixed(2) + '%' : '-' }}
              </template>
            </el-table-column>
          </el-table>
        </div>
        
        <!-- 其他保险信息显示 -->
        <div v-if="projectOtherInsurancePolicies && projectOtherInsurancePolicies.length > 0" class="insurance-details">
          <h4>其他保险信息</h4>
          <el-table :data="projectOtherInsurancePolicies" size="small" border>
            <el-table-column prop="name" label="保险名称" />
            <el-table-column prop="type" label="保险类型">
              <template #default="{ row }">
                {{ typeof row.type === 'object' ? JSON.stringify(row.type) : row.type }}
              </template>
            </el-table-column>
            <el-table-column prop="coverage" label="保障内容">
              <template #default="{ row }">
                {{ formatCoverageContent(row.coverage) }}
              </template>
            </el-table-column>
          </el-table>
          <div class="form-tip">该项目绑定的其他保险，无需选择地区</div>
        </div>
        
      </el-form>
      
      <template #footer>
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
          <!-- 左侧：批量创建模式下显示待创建员工数量 -->
          <div v-if="!isEdit && !isViewMode && batchEmployees.length > 0" style="color: #409EFF;">
            <el-icon><User /></el-icon>
            已添加 {{ batchEmployees.length }} 名员工待创建
            <el-button 
              type="text" 
              size="small" 
              @click="showBatchList = true"
              style="margin-left: 10px;"
            >
              查看列表
            </el-button>
          </div>
          <div v-else></div>
          
          <!-- 右侧：操作按钮 -->
          <div style="display: flex; gap: 10px;">
            <el-button @click="showCreateDialog = false">
              {{ isViewMode ? '关闭' : '取消' }}
            </el-button>
            
            <!-- 新增模式：添加到列表 + 批量创建 -->
            <template v-if="!isEdit && !isViewMode">
              <el-button 
                type="success" 
                @click="handleAddToBatch"
                :loading="submitting"
              >
                <el-icon><Plus /></el-icon>
                添加到列表并继续
              </el-button>
              <el-button 
                v-if="batchEmployees.length > 0"
                type="primary" 
                @click="handleBatchCreate"
                :loading="batchCreating"
              >
                <el-icon><Check /></el-icon>
                批量创建 ({{ batchEmployees.length }})
              </el-button>
              <el-button 
                v-else
                type="primary" 
                @click="handleSubmit"
                :loading="submitting"
              >
                创建
              </el-button>
            </template>
            
            <!-- 编辑模式：更新按钮 -->
            <el-button 
              v-if="isEdit && !isViewMode" 
              type="primary" 
              @click="handleSubmit" 
              :loading="submitting"
            >
              更新
            </el-button>
          </div>
        </div>
      </template>
    </el-dialog>

    <!-- 删除员工审批对话框 -->
    <el-dialog
      v-model="showDeleteApprovalDialog"
      title="提交删除员工审批"
      width="600px"
    >
      <el-alert
        title="该员工为在职状态"
        type="warning"
        :closable="false"
        style="margin-bottom: 20px;"
      >
        <template #default>
          <p>根据规定，在职员工不能直接删除。</p>
          <p>如需删除，请提交审批申请，审批通过后方可删除。</p>
        </template>
      </el-alert>

      <el-descriptions v-if="employeeToDelete" :column="2" border>
        <el-descriptions-item label="员工姓名">
          {{ employeeToDelete.name }}
        </el-descriptions-item>
        <el-descriptions-item label="员工编号">
          {{ employeeToDelete.employee_number || '-' }}
        </el-descriptions-item>
        <el-descriptions-item label="身份证号">
          {{ employeeToDelete.id_number || '-' }}
        </el-descriptions-item>
        <el-descriptions-item label="手机号">
          {{ employeeToDelete.phone || '-' }}
        </el-descriptions-item>
        <el-descriptions-item label="在职状态">
          <el-tag type="success">在职</el-tag>
        </el-descriptions-item>
      </el-descriptions>

      <el-form style="margin-top: 20px;">
        <el-form-item label="删除原因" required>
          <el-input
            v-model="deleteApprovalForm.reason"
            type="textarea"
            :rows="4"
            placeholder="请详细说明删除该员工的原因（必填）"
            maxlength="500"
            show-word-limit
          />
        </el-form-item>
        <el-form-item label="盖章方式" required>
          <el-radio-group v-model="deleteApprovalForm.stamp_method">
            <el-radio value="online">线上盖章</el-radio>
            <el-radio value="offline">线下盖章</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="showDeleteApprovalDialog = false">取消</el-button>
        <el-button 
          type="primary" 
          @click="handleSubmitDeleteApproval"
          :loading="submittingDeleteApproval"
        >
          提交审批
        </el-button>
      </template>
    </el-dialog>

    <!-- 工资调整审批对话框 -->
    <el-dialog
      v-model="showSalaryApprovalDialog"
      title="提交工资调整审批"
      width="600px"
    >
      <el-alert
        title="工资调整需审批后生效"
        type="warning"
        :closable="false"
        style="margin-bottom: 20px;"
      >
        <template #default>
          <p>当前修改不会直接落库。</p>
          <p>审批通过后，工资信息才会正式更新。</p>
        </template>
      </el-alert>

      <el-descriptions :column="2" border>
        <el-descriptions-item label="员工姓名">{{ form.name }}</el-descriptions-item>
        <el-descriptions-item label="当前基础工资">¥{{ Number(currentSalarySnapshot.basic_salary || 0).toFixed(2) }}</el-descriptions-item>
        <el-descriptions-item label="调整后基础工资">¥{{ Number(form.basic_salary || 0).toFixed(2) }}</el-descriptions-item>
        <el-descriptions-item label="调整后工资项">{{ formatSalaryItems(form.salary_items) }}</el-descriptions-item>
      </el-descriptions>

      <el-form style="margin-top: 20px;">
        <el-form-item label="调整原因" required>
          <el-input
            v-model="salaryApprovalForm.reason"
            type="textarea"
            :rows="4"
            placeholder="请填写工资调整原因（必填）"
            maxlength="500"
            show-word-limit
          />
        </el-form-item>
        <el-form-item label="盖章方式" required>
          <el-radio-group v-model="salaryApprovalForm.stamp_method">
            <el-radio value="online">线上盖章</el-radio>
            <el-radio value="offline">线下盖章</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="showSalaryApprovalDialog = false">取消</el-button>
        <el-button
          type="primary"
          @click="confirmSubmitSalaryApproval"
          :loading="submittingSalaryApproval"
        >
          提交审批
        </el-button>
      </template>
    </el-dialog>

    <!-- 批量创建员工列表对话框 -->
    <el-dialog
      v-model="showBatchList"
      title="待创建员工列表"
      width="800px"
    >
      <el-table :data="batchEmployees" border stripe>
        <el-table-column type="index" label="序号" width="60" />
        <el-table-column prop="name" label="姓名" width="100" />
        <el-table-column prop="id_number" label="身份证号" width="180" />
        <el-table-column prop="phone" label="手机号" width="120" />
        <el-table-column label="项目" min-width="150">
          <template #default="{ row }">
            <el-tag 
              v-for="projectId in row.project_ids" 
              :key="projectId"
              size="small"
              style="margin-right: 5px;"
            >
              {{ projects.find(p => p.id === projectId)?.name || projectId }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="100" fixed="right">
          <template #default="{ $index }">
            <el-button 
              type="danger" 
              size="small" 
              @click="handleRemoveFromBatch($index)"
            >
              移除
            </el-button>
          </template>
        </el-table-column>
      </el-table>
      
      <template #footer>
        <el-button @click="showBatchList = false">关闭</el-button>
        <el-button type="danger" @click="handleClearBatch">清空列表</el-button>
        <el-button 
          type="primary" 
          @click="handleBatchCreate"
          :loading="batchCreating"
        >
          批量创建 ({{ batchEmployees.length }})
        </el-button>
      </template>
    </el-dialog>
    
    <!-- 人员调动对话框 -->
    <el-dialog
      v-model="showTransferDialog"
      title="人员调动"
      width="500px"
    >
      <el-form label-width="100px">
        <el-form-item label="当前项目">
          <div>
            <el-tag 
              v-for="p in (transferEmployee ? (transferEmployee.projects || []).filter(proj => proj.pivot?.status === 'active') : [])" 
              :key="p.id" 
              size="small" 
              style="margin-right:6px"
            >{{ p.name }}</el-tag>
            <span v-if="!(transferEmployee && (transferEmployee.projects || []).filter(proj => proj.pivot?.status === 'active').length)">-</span>
          </div>
        </el-form-item>
        <el-form-item label="目标项目">
          <el-select v-model="transferForm.to_project_id" placeholder="请选择目标项目" style="width: 100%">
            <el-option v-for="p in projects" :key="p.id" :label="p.name" :value="p.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="transferForm.reason" placeholder="可填写调动原因" clearable />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showTransferDialog = false">取消</el-button>
        <el-button type="primary" @click="confirmTransfer">确定调动</el-button>
      </template>
    </el-dialog>
    
    <!-- 合同管理对话框 -->
    <el-dialog
      v-model="showContractDialog"
      title="合同管理"
      width="900px"
      @close="handleContractDialogClose"
    >
      <div class="contract-management">
        <div class="contract-header">
          <h3>员工：{{ currentEmployee?.name }}</h3>
          <div class="header-buttons">
            <el-dropdown @command="handleContractTypeSelect" split-button type="primary">
              上传合同
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="labor">签署劳动合同</el-dropdown-item>
                  <el-dropdown-item command="termination">解除协议合同</el-dropdown-item>
                  <el-dropdown-item command="retirement">退休解除协议合同</el-dropdown-item>
                  <el-dropdown-item command="other">其他合同</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
            
            <!-- 线下入职专用：上传已签署合同 -->
            <el-button 
              v-if="currentEmployee?.is_offline_onboarding && !currentEmployee?.contract_uploaded"
              type="success" 
              @click="showUploadSignedDialog = true"
            >
              <el-icon><Upload /></el-icon>
              上传已签署合同
            </el-button>
          </div>
        </div>
        
        <!-- 合同列表 -->
        <el-table
          :data="contracts"
          v-loading="contractsLoading"
          stripe
          border
          style="margin-top: 20px"
        >
          <el-table-column prop="contract_type" label="合同类型" width="150">
            <template #default="{ row }">
              <el-tag :type="getContractTypeColor(row.contract_type)">
                {{ getContractTypeText(row.contract_type, row) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="original_filename" label="文件名" min-width="200" />
          <el-table-column prop="status" label="状态" width="120">
            <template #default="{ row }">
              <el-tag :type="getContractStatusColor(row.status)">
                {{ getContractStatusText(row.status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="source_text" label="签署方式" width="100">
            <template #default="{ row }">
              <el-tag :type="getContractSourceColor(row)">
                {{ getContractSourceText(row) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="created_at" label="上传时间" width="160">
            <template #default="{ row }">
              {{ formatDateTime(row.created_at) }}
            </template>
          </el-table-column>
          <el-table-column label="操作" width="250" fixed="right">
            <template #default="{ row }">
              <el-button 
                v-if="row.status === 'draft'" 
                type="primary" 
                size="small" 
                @click="handleSubmitContract(row)"
              >
                提交签署
              </el-button>
              <el-button 
                v-if="row.status === 'employee_signed'" 
                type="success" 
                size="small" 
                @click="handleSubmitApproval(row)"
              >
                提交审批
              </el-button>
              <el-button 
                type="info" 
                size="small" 
                @click="handleDownloadContract(row)"
              >
                下载
              </el-button>
              <el-button 
                v-if="row.status === 'draft'" 
                type="danger" 
                size="small" 
                @click="handleDeleteContract(row)"
              >
                删除
              </el-button>
            </template>
          </el-table-column>
        </el-table>
      </div>
    </el-dialog>
    
    <!-- 创建合同对话框 -->
    <el-dialog
      v-model="showUploadDialog"
      title="创建合同"
      width="600px"
    >
      <el-form :model="uploadForm" label-width="100px">
        <el-form-item label="合同类型" required>
          <el-select
            v-model="uploadForm.contract_type"
            placeholder="请选择合同类型"
            style="width: 100%"
            @change="handleContractTypeChange"
          >
            <el-option label="劳动合同" value="labor" />
            <el-option label="解除协议合同" value="termination" />
            <el-option label="退休解除协议合同" value="retirement" />
            <el-option label="其他合同" value="other" />
          </el-select>
        </el-form-item>
        
        <el-form-item label="选择模板" required v-if="uploadForm.contract_type">
          <el-select
            v-model="uploadForm.template_id"
            placeholder="请选择合同模板"
            style="width: 100%"
            filterable
          >
            <el-option
              v-for="template in availableTemplates"
              :key="template.id"
              :label="template.shared_file?.name || '文件已删除'"
              :value="template.id"
            >
              <span>{{ template.shared_file?.name || '文件已删除' }}</span>
              <el-tag v-if="template.is_default" type="success" size="small" style="margin-left: 10px">默认</el-tag>
            </el-option>
          </el-select>
          <div class="form-tip">系统将使用选中的模板创建合同</div>
        </el-form-item>
        
        <el-form-item label="备注">
          <el-input
            v-model="uploadForm.notes"
            type="textarea"
            :rows="3"
            placeholder="请输入备注（选填）"
          />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showUploadDialog = false">取消</el-button>
        <el-button type="primary" @click="handleCreateContract" :loading="uploading">
          创建合同
        </el-button>
      </template>
    </el-dialog>

    <!-- 上传已签署合同对话框（线下入职专用） -->
    <el-dialog
      v-model="showUploadSignedDialog"
      title="上传已签署合同"
      width="600px"
    >
      <el-alert
        title="提示"
        type="info"
        :closable="false"
        style="margin-bottom: 20px;"
      >
        此功能用于上传已经签署完成的纸质合同扫描件（PDF格式），上传后将直接标记为已完成状态，不需要走电子签署流程。
      </el-alert>
      
      <el-form :model="uploadSignedForm" label-width="100px">
        <el-form-item label="合同类型" required>
          <el-select
            v-model="uploadSignedForm.contract_type"
            placeholder="请选择合同类型"
            style="width: 100%"
          >
            <el-option label="劳动合同" value="labor" />
            <el-option label="解除协议合同" value="termination" />
            <el-option label="退休解除协议合同" value="retirement" />
            <el-option label="其他合同" value="other" />
          </el-select>
        </el-form-item>
        
        <el-form-item label="合同文件" required>
          <el-upload
            ref="uploadSignedRef"
            :auto-upload="false"
            :limit="1"
            accept=".pdf"
            :on-change="handleSignedFileChange"
            :on-remove="handleSignedFileRemove"
            :on-exceed="handleSignedFileExceed"
          >
            <el-button type="primary">
              <el-icon><Upload /></el-icon>
              选择PDF文件
            </el-button>
            <template #tip>
              <div class="el-upload__tip">
                只能上传PDF格式的文件，且不超过10MB
              </div>
            </template>
          </el-upload>
        </el-form-item>
        
        <el-form-item label="备注">
          <el-input
            v-model="uploadSignedForm.notes"
            type="textarea"
            :rows="3"
            placeholder="请输入备注（选填）"
          />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showUploadSignedDialog = false">取消</el-button>
        <el-button 
          type="primary" 
          @click="handleUploadSignedContract" 
          :loading="uploadingSignedContract"
          :disabled="!uploadSignedForm.contract_type || !signedContractFile"
        >
          上传合同
        </el-button>
      </template>
    </el-dialog>

    <!-- 盖章签字对话框 -->
    <el-dialog
      v-model="showSignatureDialog"
      title="盖章签字"
      width="600px"
      :close-on-click-modal="false"
    >
      <el-form
        ref="signatureFormRef"
        :model="signatureForm"
        :rules="signatureRules"
        label-width="100px"
      >
        <el-form-item label="盖章方式" prop="stamp_method">
          <el-radio-group v-model="signatureForm.stamp_method">
            <el-radio label="online">线上盖章</el-radio>
            <el-radio label="offline">线下盖章</el-radio>
          </el-radio-group>
          <div class="form-item-tip">
            <el-text type="info" size="small">
              线上盖章：系统自动在PDF上添加印章；线下盖章：需要手动在纸质合同上盖章
            </el-text>
          </div>
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="handleSignatureDialogClose">取消</el-button>
        <el-button type="warning" @click="openPDFEditor">
          <el-icon><Edit /></el-icon>
          高级签名盖章
        </el-button>
        <el-button type="primary" @click="handleSignatureSubmit" :loading="submitting">
          确认盖章签字
        </el-button>
      </template>
    </el-dialog>

    <!-- PDF签名编辑器对话框 -->
    <el-dialog
      v-model="showPDFEditor"
      title="PDF签名盖章编辑器"
      width="90%"
      top="5vh"
      :close-on-click-modal="false"
    >
      <PDFSignatureEditor
        v-if="showPDFEditor && currentPDFUrl"
        :pdf-url="currentPDFUrl"
        @confirm="handlePDFEditorConfirm"
        @cancel="showPDFEditor = false"
      />
    </el-dialog>

    <!-- 身份证过期员工列表对话框 -->
    <el-dialog
      v-model="showExpiredIdCardsDialogVisible"
      title="身份证已过期员工列表"
      width="800px"
    >
      <el-alert
        title="提醒"
        type="warning"
        :closable="false"
        style="margin-bottom: 16px;"
      >
        以下在职员工的身份证已过期，请及时更新身份证信息
      </el-alert>
      
      <el-table 
        :data="expiredIdCardsList" 
        v-loading="loadingExpiredIdCards"
        border 
        stripe
      >
        <el-table-column type="index" label="序号" width="60" />
        <el-table-column prop="name" label="姓名" width="100" />
        <el-table-column prop="employee_number" label="工号" width="120" />
        <el-table-column prop="id_number" label="身份证号" width="180" />
        <el-table-column label="有效期至" width="120">
          <template #default="{ row }">
            {{ row.id_card_valid_until ? new Date(row.id_card_valid_until).toLocaleDateString('zh-CN') : '-' }}
          </template>
        </el-table-column>
        <el-table-column label="已过期天数" width="120">
          <template #default="{ row }">
            <el-tag type="danger">{{ row.expired_days }} 天</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="100" fixed="right">
          <template #default="{ row }">
            <el-button 
              type="primary" 
              size="small" 
              @click="handleEditExpiredEmployee(row.id)"
            >
              编辑
            </el-button>
          </template>
        </el-table-column>
      </el-table>
      
      <template #footer>
        <el-button @click="showExpiredIdCardsDialogVisible = false">关闭</el-button>
      </template>
    </el-dialog>

    <!-- 线下入职对话框 -->
    <el-dialog
      v-model="showOfflineOnboardingDialog"
      title="线下入职"
      width="600px"
    >
      <el-alert
        title="说明"
        type="info"
        :closable="false"
        style="margin-bottom: 16px;"
      >
        线下入职适用于先办理社保，合同在入职后30天内上传的情况
      </el-alert>
      
      <el-form
        :model="offlineOnboardingForm"
        label-width="120px"
      >
        <el-form-item label="员工姓名">
          <el-input :value="currentOfflineEmployee?.name" disabled />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="cancelOfflineOnboarding">取消</el-button>
        <el-button 
          type="primary" 
          @click="submitOfflineOnboardingForm"
          :loading="submittingOfflineOnboarding"
        >
          提交审批
        </el-button>
      </template>
    </el-dialog>

    <!-- 待上传合同列表对话框 -->
    <el-dialog
      v-model="showPendingContractDialog"
      title="待上传合同员工列表"
      width="900px"
    >
      <el-alert
        title="提醒"
        type="warning"
        :closable="false"
        style="margin-bottom: 16px;"
      >
        以下员工线下入职已超过30天，请尽快上传劳动合同
      </el-alert>
      
      <el-table 
        :data="pendingContractUploadList" 
        border 
        stripe
      >
        <el-table-column type="index" label="序号" width="60" />
        <el-table-column prop="name" label="姓名" width="100" />
        <el-table-column prop="id_number" label="身份证号" width="180" />
        <el-table-column prop="phone" label="手机号" width="120" />
        <el-table-column label="所属项目" width="150">
          <template #default="{ row }">
            <el-tag 
              v-for="(project, index) in row.projects" 
              :key="index"
              size="small"
              style="margin-right: 4px;"
            >
              {{ project }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="入职日期" width="120">
          <template #default="{ row }">
            {{ row.offline_onboarding_date ? new Date(row.offline_onboarding_date).toLocaleDateString('zh-CN') : '-' }}
          </template>
        </el-table-column>
        <el-table-column label="截止日期" width="120">
          <template #default="{ row }">
            {{ row.contract_upload_deadline ? new Date(row.contract_upload_deadline).toLocaleDateString('zh-CN') : '-' }}
          </template>
        </el-table-column>
        <el-table-column label="超期天数" width="100">
          <template #default="{ row }">
            <el-tag type="danger">{{ row.overdue_days }} 天</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="120" fixed="right">
          <template #default="{ row }">
            <el-button 
              type="success" 
              size="small" 
              @click="handleMarkContractUploaded(row.id)"
            >
              标记已上传
            </el-button>
          </template>
        </el-table-column>
      </el-table>
      
      <template #footer>
        <el-button @click="showPendingContractDialog = false">关闭</el-button>
      </template>
    </el-dialog>

    <!-- 员工变更历史对话框 -->
    <el-dialog
      v-model="changeHistoryDialogVisible"
      title="员工信息变更历史"
      width="900px"
      :close-on-click-modal="false"
    >
      <div v-if="currentChangeHistoryEmployee" style="margin-bottom: 15px; padding: 10px; background: #f5f7fa; border-radius: 4px;">
        <el-text><strong>员工：</strong>{{ currentChangeHistoryEmployee.name }}</el-text>
        <el-text style="margin-left: 20px;"><strong>工号：</strong>{{ currentChangeHistoryEmployee.employee_number }}</el-text>
      </div>

      <el-table
        :data="changeHistoryList"
        v-loading="changeHistoryLoading"
        border
        style="width: 100%"
      >
        <el-table-column prop="created_at" label="变更时间" width="160" />
        <el-table-column prop="user_name" label="操作人" width="100" />
        <el-table-column prop="description" label="变更内容" min-width="200" />
        <!-- 暂时隐藏查看详情功能
        <el-table-column label="详情" width="100">
          <template #default="{ row }">
            <el-button 
              size="small" 
              type="primary" 
              link
              @click="showChangeDetail(row)"
            >
              查看详情
            </el-button>
          </template>
        </el-table-column>
        -->
      </el-table>

      <el-pagination
        v-if="changeHistoryTotal > 0"
        style="margin-top: 15px; justify-content: center;"
        :current-page="changeHistoryPage"
        :page-size="changeHistoryPageSize"
        :total="changeHistoryTotal"
        layout="total, prev, pager, next"
        @current-change="handleChangeHistoryPageChange"
      />

      <el-empty v-if="!changeHistoryLoading && changeHistoryList.length === 0" description="暂无变更记录" />

      <template #footer>
        <el-button @click="changeHistoryDialogVisible = false">关闭</el-button>
      </template>
    </el-dialog>

    <!-- 变更详情对话框 -->
    <el-dialog
      v-model="changeDetailDialogVisible"
      title="变更详情"
      width="700px"
    >
      <div v-if="currentChangeDetail">
        <el-descriptions :column="1" border>
          <el-descriptions-item label="操作人">{{ currentChangeDetail.user_name }}</el-descriptions-item>
          <el-descriptions-item label="操作时间">{{ currentChangeDetail.created_at }}</el-descriptions-item>
          <el-descriptions-item label="IP地址">{{ currentChangeDetail.ip_address || '-' }}</el-descriptions-item>
          <el-descriptions-item label="变更说明">{{ currentChangeDetail.description }}</el-descriptions-item>
        </el-descriptions>

        <div v-if="currentChangeDetail.old_values && currentChangeDetail.new_values" style="margin-top: 20px;">
          <h4 style="margin-bottom: 10px;">字段变更对比：</h4>
          <el-table :data="getChangeComparison(currentChangeDetail)" border size="small">
            <el-table-column prop="field" label="字段" width="150" />
            <el-table-column prop="oldValue" label="修改前" min-width="200">
              <template #default="{ row }">
                <el-text type="danger">{{ row.oldValue }}</el-text>
              </template>
            </el-table-column>
            <el-table-column prop="newValue" label="修改后" min-width="200">
              <template #default="{ row }">
                <el-text type="success">{{ row.newValue }}</el-text>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </div>

      <template #footer>
        <el-button @click="changeDetailDialogVisible = false">关闭</el-button>
      </template>
    </el-dialog>

    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, nextTick, watch } from 'vue'
import { ElMessage, ElMessageBox, ElLoading } from 'element-plus'
import { UploadFilled, Download, Search, Refresh, Document, Plus, Edit, Star, Postcard, FolderOpened, Calendar, User, Check, Upload, Delete, View, Warning } from '@element-plus/icons-vue'
import { getEmployees, createEmployee, updateEmployee, deleteEmployee, submitOfflineOnboarding, getPendingContractUpload, markContractUploaded, submitSalaryAdjustmentApproval } from '@/api/employees'
import { getProjects } from '@/api/projects'
import { getEmployeeContracts, uploadContract, submitContract, completeContract, deleteContract, downloadContract, uploadSignedContract } from '@/api/employeeContracts'
import { getDefaultTemplates, getContractTemplates } from '@/api/contractTemplates'
import { getProjectSocialSecurityRegions, getProjectHousingFundRegions } from '@/api/employeeSocialSecurity'
import { useUserStore } from '@/stores/user'
import { useAccountSetStore } from '@/stores/accountSet'
import NoAccountSetTip from '@/components/NoAccountSetTip.vue'
import request from '@/api/request'
import { PdfFillService } from '@/utils/pdfFillService'
import { getMySignature, getMySeals } from '@/api/signatures'
import { useRouter } from 'vue-router'
import { PDFDocument } from 'pdf-lib'
import PDFSignatureEditor from '@/components/PDFSignatureEditor.vue'
import EmployeeDocumentManager from '@/components/EmployeeDocumentManager.vue'

const userStore = useUserStore()
const accountSetStore = useAccountSetStore()
const router = useRouter()

const isAdmin = computed(() => userStore.userInfo?.role === 'admin')
const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)

// 计算预计退休日期（前端预览，实际保存时由后端计算）
const calculatedRetirementDate = computed(() => {
  if (!form.birth_date || !form.gender) {
    return ''
  }

  const birth = new Date(form.birth_date)
  const policyStartDate = new Date('2025-01-01')

  let originalAge, targetAge, delayPer

  if (form.gender === 'male') {
    // 男职工：60岁→63岁，每4个月延1个月
    originalAge = 60
    targetAge = 63
    delayPer = 4
  } else if (form.retirement_category === 'cadre') {
    // 女职工管理岗：55岁→58岁，每4个月延1个月
    originalAge = 55
    targetAge = 58
    delayPer = 4
  } else {
    // 女职工普通岗：50岁→55岁，每2个月延1个月
    originalAge = 50
    targetAge = 55
    delayPer = 2
  }

  // 按原政策的退休日期
  const originalRetireDate = new Date(birth)
  originalRetireDate.setFullYear(originalRetireDate.getFullYear() + originalAge)

  // 如果原退休日期在政策实施前，按原政策退休
  if (originalRetireDate < policyStartDate) {
    return formatDateForDisplay(originalRetireDate)
  }

  // 计算从政策开始到原退休日期经过的月数
  const monthsFromStart = (originalRetireDate.getFullYear() - policyStartDate.getFullYear()) * 12 
    + (originalRetireDate.getMonth() - policyStartDate.getMonth())

  // 计算延迟月数
  let delayMonths = Math.floor(monthsFromStart / delayPer)
  const maxDelay = (targetAge - originalAge) * 12
  delayMonths = Math.min(delayMonths, maxDelay)

  // 最终退休日期
  const finalRetireDate = new Date(originalRetireDate)
  finalRetireDate.setMonth(finalRetireDate.getMonth() + delayMonths)

  return formatDateForDisplay(finalRetireDate)
})

// 格式化日期显示
const formatDateForDisplay = (date) => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const loading = ref(false)
const submitting = ref(false)
const showCreateDialog = ref(false)
const isEdit = ref(false)

// 删除审批相关
const showDeleteApprovalDialog = ref(false)
const employeeToDelete = ref(null)
const deleteApprovalForm = reactive({
  reason: '',
  stamp_method: 'online' // 盖章方式
})
const submittingDeleteApproval = ref(false)

// 工资调整审批相关
const showSalaryApprovalDialog = ref(false)
const salaryApprovalForm = reactive({
  reason: '',
  stamp_method: 'online'
})
const submittingSalaryApproval = ref(false)
const pendingSalaryAdjustment = ref(null)
const currentSalarySnapshot = ref({
  basic_salary: null,
  salary_items: []
})

const normalizeSalaryItems = (items) => {
  if (!Array.isArray(items)) {
    return []
  }

  return items
    .map((item) => ({
      name: String(item?.name || '').trim(),
      amount: Number(item?.amount || 0)
    }))
    .filter((item) => item.name !== '' || item.amount > 0)
}

const formatSalaryItems = (items) => {
  const normalized = normalizeSalaryItems(items)
  if (normalized.length === 0) {
    return '无'
  }

  return normalized
    .map((item) => `${item.name || '工资项'}: ¥${Number(item.amount || 0).toFixed(2)}`)
    .join('；')
}

// 批量创建相关
const batchEmployees = ref([]) // 待创建的员工列表
const showBatchList = ref(false) // 显示批量列表对话框
const batchCreating = ref(false) // 批量创建中
const selectedModules = ref(['employee']) // 选中的模块，默认显示员工信息
const isViewMode = ref(false) // 添加查看模式标识
const formRef = ref()

// 身份证过期相关
const expiredIdCardsCount = ref(0) // 过期身份证数量
const expiredIdCardsList = ref([]) // 过期身份证列表
const showExpiredIdCardsDialogVisible = ref(false) // 显示过期身份证对话框
const loadingExpiredIdCards = ref(false) // 加载过期身份证列表

const employees = ref([])
const projects = ref([])
const selectedEmployees = ref([]) // 选中的员工列表

// 人员统计数据
const employeeStats = ref({
  active: 0,      // 在职人数
  resigned: 0,    // 离职人数
  probation: 0,   // 试用期人数
  contractExpired: 0  // 合同已到期人数
})

// 社保、医保和公积金地区相关
const availableSocialSecurityRegions = ref([])
const availableMedicalInsuranceRegions = ref([])
const availableHousingFundRegions = ref([])
const availableHousingFundConfigs = ref([])
const availableLargeMedicalInsuranceConfigs = ref([])
const selectedSocialSecurityRegion = ref(null)
const selectedMedicalInsuranceRegion = ref(null)
const selectedHousingFundRegion = ref(null)
const selectedHousingFundConfig = ref(null)
const selectedLargeMedicalInsuranceConfig = ref(null)
const currentLargeMedicalStatus = ref(null) // 当前编辑员工的大额医疗保险状态

// 线下入职相关
const showOfflineOnboardingDialog = ref(false) // 显示线下入职对话框
const offlineOnboardingForm = reactive({
  hire_date: '',
  contract_start_date: '',
  contract_end_date: '',
  probation_end_date: ''
})
const currentOfflineEmployee = ref(null) // 当前要线下入职的员工
const submittingOfflineOnboarding = ref(false) // 提交线下入职中
const pendingContractUploadList = ref([]) // 待上传合同的员工列表
const showPendingContractDialog = ref(false) // 显示待上传合同对话框
const currentEditingEmployeeId = ref(null) // 当前编辑的员工ID

// 员工变更历史相关
const changeHistoryDialogVisible = ref(false)
const changeHistoryLoading = ref(false)
const changeHistoryList = ref([])
const changeHistoryTotal = ref(0)
const changeHistoryPage = ref(1)
const changeHistoryPageSize = ref(20)
const currentChangeHistoryEmployee = ref(null) // 当前查看变更历史的员工
const changeDetailDialogVisible = ref(false)
const currentChangeDetail = ref(null)

// 判断当前选择的大额医疗保险配置是否为按基数类型
const isLargeMedicalByBase = computed(() => {
  return selectedLargeMedicalInsuranceConfig.value?.calculation_type === 'base'
})

// 判断当前选择的大额医疗保险配置是否为固定金额类型
const isLargeMedicalByFixed = computed(() => {
  return selectedLargeMedicalInsuranceConfig.value?.calculation_type === 'fixed'
})

// 判断是否为特殊地区（使用统一基数）
const isSpecialLargeMedicalRegion = computed(() => {
  if (!selectedLargeMedicalInsuranceConfig.value) return false
  const config = selectedLargeMedicalInsuranceConfig.value
  // 如果 base_source 为 config，则为特殊地区（使用统一基数）
  return config.base_source === 'config'
})

// 是否需要显示大额基数输入框（按基数时显示且自动跟随医保基数，固定金额时隐藏）
const showLargeMedicalBase = computed(() => {
  // 如果选择了固定金额类型，隐藏大额基数
  if (isLargeMedicalByFixed.value) {
    return false
  }
  // 如果选择了按基数类型，显示大额基数（但值等于医保基数）
  if (isLargeMedicalByBase.value) {
    return true
  }
  // 未选择时显示
  return true
})

const loadingSocialSecurityRegions = ref(false)
const loadingMedicalInsuranceRegions = ref(false)
const loadingHousingFundRegions = ref(false)
const loadingHousingFundConfigs = ref(false)
const projectOtherInsurancePolicies = ref([])

const searchForm = reactive({
  search: '',
  project_id: '',
  contract_status: ''
})

const pagination = reactive({
  currentPage: 1,
  pageSize: 20,
  total: 0
})

const form = reactive({
  name: '',
  employee_number: '', // 新增工号字段
  position: '', // 岗位
  id_number: '',
  phone: '',
  email: '',
  gender: 'male',
  birth_date: '',
  retirement_category: 'worker', // 退休类别：worker-普通岗, cadre-管理岗
  hire_date: '',
  contract_start_date: '',
  contract_end_date: '',
  probation_end_date: '', // 试用期结束日期
  contract_months: null, // 签订月份
  address: '',
  project_ids: [],
  // 工资信息
  basic_salary: null,
  salary_items: [],
  // 工资卡信息
  bank_account: '',
  bank_account_holder: '',
  bank_name: '',
  bank_branch: '',
  remittance_remark: '',
  // 保险基数信息
  social_security_base: null,
  medical_insurance_base: null,
  housing_fund_base: null,
  large_medical_base: null,
  large_medical_company_base: null,
  // 保险信息
  social_security_region_id: null,
  medical_insurance_region_id: null,
  housing_fund_region_id: null,
  housing_fund_config_id: null,
  large_medical_insurance_config_id: null,
  social_insurance_enrollment_date: null,
  provident_fund_enrollment_date: null,
  medical_insurance_enrollment_date: null,
  large_medical_enrollment_date: null,
  // 身份证有效期
  id_card_valid_from: null,
  id_card_valid_until: null,
  
  // 详细员工信息
  // 一、基础身份信息
  country_region: '',
  chinese_name: '',
  birth_country: '',
  other_id_type: '',
  other_id_number: '',
  
  // 二、从业任职信息
  personnel_status: '',
  employment_type: '',
  employment_date: '',
  resignation_date: '',
  signing_location: '', // 签署地
  household_type: '', // 户口类型
  annual_employment_status: '',
  job_title: '',
  
  // 三、特殊身份信息
  is_disabled: false,
  disability_cert_type: '',
  disability_cert_number: '',
  is_martyr_family: false,
  martyr_family_cert_number: '',
  is_elderly_alone: false,
  
  // 四、涉税与投资信息
  tax_matter: '',
  deduct_expense: true,
  personal_investment_amount: null,
  personal_investment_ratio: null,
  
  // 五、出入境信息
  first_entry_date: '',
  expected_departure_date: '',
  
  // 六、联系方式与银行信息
  email_address: '',
  bank_province: '',
  
  // 七、地址信息
  household_province: '',
  household_city: '',
  household_district: '',
  household_address: '',
  residence_province: '',
  residence_city: '',
  residence_district: '',
  residence_address: '',
  contact_province: '',
  contact_city: '',
  contact_district: '',
  contact_address: '',
  
  // 其他信息
  education: '',
  remarks: '',
  
  // 八、备注说明信息
  other_notes: ''
})

const formRules = {
  name: [
    { required: true, message: '请输入姓名', trigger: 'blur' }
  ],
  employee_number: [
    // 工号自动生成，不需要必填验证
  ],
  id_number: [
    { required: true, message: '请输入身份证号', trigger: 'blur' },
    { pattern: /^[1-9]\d{5}(18|19|20)\d{2}((0[1-9])|(1[0-2]))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$/, message: '请输入正确的身份证号', trigger: 'blur' }
  ],
  phone: [
    { pattern: /^1[3-9]\d{9}$/, message: '请输入正确的手机号', trigger: 'blur' }
  ],
  gender: [
    { required: true, message: '请选择性别', trigger: 'change' }
  ],
  birth_date: [
    { required: true, message: '请选择出生日期', trigger: 'change' }
  ],
  hire_date: [
    { required: true, message: '请选择入职日期', trigger: 'change' }
  ],
  contract_start_date: [
    { required: true, message: '请选择合同开始日期', trigger: 'change' }
  ],
  contract_end_date: [
    { required: true, message: '请选择合同结束日期', trigger: 'change' }
  ],
  project_ids: [
    { required: true, message: '请选择所属项目', trigger: 'change' }
  ],
  // 保险信息改为非必填
  // large_medical_enrollment_date 保持非必填
  // basic_salary: [
  //   { required: true, message: '请输入基础工资', trigger: 'blur' }
  // ]
}

// 从身份证号码中解析性别和出生日期
const parseIdNumber = (idNumber) => {
  if (!idNumber || idNumber.length !== 18) {
    return null
  }
  
  // 验证身份证号格式
  const idPattern = /^[1-9]\d{5}(18|19|20)\d{2}((0[1-9])|(1[0-2]))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$/
  if (!idPattern.test(idNumber)) {
    return null
  }
  
  // 提取出生日期：第7-14位
  const birthYear = idNumber.substring(6, 10)
  const birthMonth = idNumber.substring(10, 12)
  const birthDay = idNumber.substring(12, 14)
  const birthDate = `${birthYear}-${birthMonth}-${birthDay}`
  
  // 提取性别：第17位，奇数为男，偶数为女
  const genderCode = parseInt(idNumber.substring(16, 17))
  const gender = genderCode % 2 === 1 ? 'male' : 'female'
  
  return {
    birthDate,
    gender
  }
}

// 监听身份证号变化，自动填充性别和出生日期
watch(() => form.id_number, (newIdNumber) => {
  if (newIdNumber && newIdNumber.length === 18) {
    const parsed = parseIdNumber(newIdNumber)
    if (parsed) {
      // 自动填充性别
      form.gender = parsed.gender
      // 自动填充出生日期
      form.birth_date = parsed.birthDate
    }
  }
})

// 大额基数现在从配置自动同步，不再跟随医保基数变化

// 工资卡验证规则
const salaryCardRules = {
  bank_account: [
    { pattern: /^\d{10,25}$/, message: '请输入正确的银行账号（10-25位数字）', trigger: 'blur' }
  ],
  bank_account_holder: [
    { max: 50, message: '户名长度不能超过50个字符', trigger: 'blur' }
  ],
  bank_name: [
    { max: 100, message: '开户行名称长度不能超过100个字符', trigger: 'blur' }
  ],
  bank_branch: [
    { max: 100, message: '开户地长度不能超过100个字符', trigger: 'blur' }
  ],
  remittance_remark: [
    { max: 100, message: '汇款备注长度不能超过100个字符', trigger: 'blur' }
  ]
}

const addSalaryItem = () => {
  form.salary_items.push({ name: '', amount: null })
}

const removeSalaryItem = (index) => {
  form.salary_items.splice(index, 1)
}

const loadEmployees = async () => {
  loading.value = true
  try {
    const params = {
      page: pagination.currentPage,
      per_page: pagination.pageSize,
      ...searchForm
    }
    
    console.log('Loading employees with params:', params)
    console.log('API URL:', 'http://localhost:8000/api/employees')
    
    const response = await getEmployees(params)
    console.log('API response:', response)
    console.log('Response type:', typeof response)
    console.log('Response keys:', Object.keys(response))
    
    if (response && response.success) {
      employees.value = response.data.data || response.data || []
      pagination.total = response.data.total || response.pagination?.total || 0
      
      // 确保工号字段存在
      employees.value.forEach(emp => {
        if (!emp.employee_number || emp.employee_number === null || emp.employee_number === '') {
          emp.employee_number = '未设置'
          console.log(`员工 ${emp.name || emp.id} 没有工号，设置为"未设置"`)
        } else {
          console.log(`员工 ${emp.name || emp.id} 的工号: ${emp.employee_number}`)
        }
      })
      
      // 更新统计数据
      if (response.data.stats) {
        employeeStats.value = response.data.stats
      }
      
    } else {
      employees.value = []
      ElMessage.error('加载员工数据失败')
    }
  } catch (error) {
    console.error('Load employees error:', error)
    ElMessage.error('加载员工数据失败: ' + error.message)
    employees.value = []
  } finally {
    loading.value = false
  }
}

// 检查身份证过期的员工
const checkExpiredIdCards = async () => {
  try {
    const response = await request.get('/employees/expired-id-cards', {
      params: {
        current_account_set_id: currentAccountSetId.value
      }
    })
    if (response.success) {
      expiredIdCardsCount.value = response.count || 0
      expiredIdCardsList.value = response.data || []
      
      // 如果有过期的身份证，弹出提示
      if (expiredIdCardsCount.value > 0) {
        ElMessageBox.alert(
          `有 ${expiredIdCardsCount.value} 名在职员工身份证已过期，请及时更新身份证信息`,
          '提醒',
          {
            confirmButtonText: '查看详情',
            type: 'warning',
            callback: (action) => {
              if (action === 'confirm') {
                showExpiredIdCardsDialog()
              }
            }
          }
        )
      }
    }
  } catch (error) {
    console.error('检查身份证过期失败:', error)
  }
}

// 显示过期身份证对话框
const showExpiredIdCardsDialog = async () => {
  showExpiredIdCardsDialogVisible.value = true
  loadingExpiredIdCards.value = true
  
  try {
    const response = await request.get('/employees/expired-id-cards', {
      params: {
        current_account_set_id: currentAccountSetId.value
      }
    })
    if (response.success) {
      expiredIdCardsList.value = response.data || []
      expiredIdCardsCount.value = response.count || 0
    }
  } catch (error) {
    console.error('加载过期身份证列表失败:', error)
    ElMessage.error('加载过期身份证列表失败')
  } finally {
    loadingExpiredIdCards.value = false
  }
}

// 编辑过期身份证的员工
const handleEditExpiredEmployee = (employeeId) => {
  showExpiredIdCardsDialogVisible.value = false
  const employee = employees.value.find(e => e.id === employeeId)
  if (employee) {
    handleEdit(employee)
  } else {
    // 如果当前列表中没有，重新加载后再编辑
    loadEmployees().then(() => {
      const emp = employees.value.find(e => e.id === employeeId)
      if (emp) {
        handleEdit(emp)
      }
    })
  }
}

// 加载当前编辑员工的大额医疗保险状态
const loadCurrentLargeMedicalStatus = async (employeeId) => {
  if (!employeeId) {
    currentLargeMedicalStatus.value = null
    return
  }
  
  try {
    const response = await request.get(`/employees/${employeeId}/large-medical-status`)
    if (response.success) {
      currentLargeMedicalStatus.value = response.data
    } else {
      currentLargeMedicalStatus.value = null
    }
  } catch (error) {
    console.error('获取大额医疗保险状态失败:', error)
    currentLargeMedicalStatus.value = null
  }
}

// 获取大额医疗保险按钮类型
const getLargeMedicalButtonType = (status) => {
  if (!status) return 'info'
  switch (status.status) {
    case 'enrolled':
      return 'success'
    case 'pending':
      return 'warning'
    case 'can_enable':
      return 'primary'
    default:
      return 'info'
  }
}

// 在列表中开启大额医疗保险（表格按钮）
const handleEnableLargeMedical = async (row) => {
  if (!row.largeMedicalStatus || !row.largeMedicalStatus.can_enable) {
    return
  }
  
  // 校验是否填写了大额参保日期
  if (!row.large_medical_enrollment_date) {
    ElMessage.warning('该员工未填写大额医疗参保日期，请先在人员档案中编辑填写后再启用')
    return
  }
  
  try {
    await ElMessageBox.confirm(
      `确定要为 ${row.name} 开启大额医疗保险吗？\n\n开启后将在增减管理中生成一条待处理任务，需要确认处理后才能正式参保。`,
      '开启大额医疗保险',
      {
        confirmButtonText: '确定开启',
        cancelButtonText: '取消',
        type: 'info'
      }
    )
    
    const response = await request.post(`/employees/${row.id}/enable-large-medical`)
    
    if (response.success) {
      ElMessage.success(response.message || '已创建大额医疗保险开启任务')
      // 刷新员工列表
      await loadEmployees()
    } else {
      ElMessage.error(response.message || '开启失败')
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('开启大额医疗保险失败:', error)
      ElMessage.error(error.response?.data?.message || error.message || '开启失败')
    }
  }
}

// 在编辑对话框中开启大额医疗保险
const handleEnableLargeMedicalInDialog = async () => {
  if (!currentLargeMedicalStatus.value || !currentLargeMedicalStatus.value.can_enable) {
    return
  }
  
  if (!currentEditingEmployeeId.value) {
    ElMessage.error('无法获取员工信息')
    return
  }
  
  // 校验是否填写了大额参保日期
  if (!form.large_medical_enrollment_date) {
    ElMessage.warning('请先填写大额医疗参保日期后再启用')
    return
  }
  
  try {
    await ElMessageBox.confirm(
      `确定要为该员工开启大额医疗保险吗？\n\n开启后将在增减管理中生成一条待处理任务，需要确认处理后才能正式参保。`,
      '开启大额医疗保险',
      {
        confirmButtonText: '确定开启',
        cancelButtonText: '取消',
        type: 'info'
      }
    )
    
    const response = await request.post(`/employees/${currentEditingEmployeeId.value}/enable-large-medical`)
    
    if (response.success) {
      ElMessage.success(response.message || '已创建大额医疗保险开启任务')
      // 刷新大额医疗保险状态
      await loadCurrentLargeMedicalStatus(currentEditingEmployeeId.value)
    } else {
      ElMessage.error(response.message || '开启失败')
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('开启大额医疗保险失败:', error)
      ElMessage.error(error.response?.data?.message || error.message || '开启失败')
    }
  }
}

// 加载项目的社保地区
const loadProjectSocialSecurityRegions = async (projectIds) => {
  if (!projectIds || projectIds.length === 0) {
    availableSocialSecurityRegions.value = []
    return
  }
  
  console.log('Loading social security regions for projects:', projectIds)
  console.log('Current account set ID:', currentAccountSetId.value)
  
  loadingSocialSecurityRegions.value = true
  try {
    // 获取所有项目的社保地区并合并
    const allRegions = []
    for (const projectId of projectIds) {
      console.log('Loading social security regions for project:', projectId)
      try {
        const response = await getProjectSocialSecurityRegions(projectId, {
          current_account_set_id: currentAccountSetId.value
        })
        console.log('Social security regions response:', response)
        if (response.success) {
          allRegions.push(...response.data)
          if (response.message) {
            console.info('Social security regions info for project:', projectId, response.message)
          }
        } else {
          console.warn('Failed to load social security regions for project:', projectId, response.message)
        }
      } catch (error) {
        console.error('Error loading social security regions for project:', projectId, error)
      }
    }
    
    // 去重
    const uniqueRegions = allRegions.filter((region, index, self) => 
      index === self.findIndex(r => r.id === region.id)
    )
    
    availableSocialSecurityRegions.value = uniqueRegions
  } catch (error) {
    console.error('加载社保地区失败:', error)
    ElMessage.error('加载社保地区失败')
  } finally {
    loadingSocialSecurityRegions.value = false
  }
}

// 加载项目的医保地区
const loadProjectMedicalInsuranceRegions = async (projectIds) => {
  if (!projectIds || projectIds.length === 0) {
    availableMedicalInsuranceRegions.value = []
    return
  }
  
  loadingMedicalInsuranceRegions.value = true
  try {
    const response = await request.get(`/employees/projects/${projectIds[0]}/medical-insurance-regions`, {
      params: { current_account_set_id: currentAccountSetId.value }
    })
    
    if (response.success) {
      availableMedicalInsuranceRegions.value = response.data || []
    } else {
      availableMedicalInsuranceRegions.value = []
    }
  } catch (error) {
    console.error('加载医保地区失败:', error)
    ElMessage.error('加载医保地区失败')
    availableMedicalInsuranceRegions.value = []
  } finally {
    loadingMedicalInsuranceRegions.value = false
  }
}

// 加载项目的公积金地区
const loadProjectHousingFundRegions = async (projectIds) => {
  if (!projectIds || projectIds.length === 0) {
    availableHousingFundRegions.value = []
    return
  }
  
  console.log('Loading housing fund regions for projects:', projectIds)
  console.log('Current account set ID:', currentAccountSetId.value)
  
  loadingHousingFundRegions.value = true
  try {
    // 获取所有项目的公积金地区并合并
    const allRegions = []
    for (const projectId of projectIds) {
      console.log('Loading housing fund regions for project:', projectId)
      try {
        const response = await getProjectHousingFundRegions(projectId, {
          current_account_set_id: currentAccountSetId.value
        })
        console.log('Housing fund regions response:', response)
        if (response.success) {
          allRegions.push(...response.data)
          if (response.message) {
            console.info('Housing fund regions info for project:', projectId, response.message)
          }
        } else {
          console.warn('Failed to load housing fund regions for project:', projectId, response.message)
        }
      } catch (error) {
        console.error('Error loading housing fund regions for project:', projectId, error)
      }
    }
    
    // 去重
    const uniqueRegions = allRegions.filter((region, index, self) => 
      index === self.findIndex(r => r.id === region.id)
    )
    
    availableHousingFundRegions.value = uniqueRegions
  } catch (error) {
    console.error('加载公积金地区失败:', error)
    ElMessage.error('加载公积金地区失败')
  } finally {
    loadingHousingFundRegions.value = false
  }
}

const loadProjects = async () => {
  try {
    // 使用封装的 API 方法，会自动添加 Authorization token
    const response = await getProjects({
      current_account_set_id: currentAccountSetId.value
    })
    if (response.success) {
      projects.value = response.data.data || []
      console.log('项目列表加载成功:', projects.value.length, '个项目')
    } else {
      ElMessage.warning('加载项目列表失败，请刷新重试')
      projects.value = []
    }
  } catch (error) {
    console.error('Load projects error:', error)
    ElMessage.error('加载项目列表失败: ' + (error.message || '未知错误'))
    projects.value = []
  }
}

const handleSearch = () => {
  pagination.currentPage = 1
  loadEmployees()
}

// 处理合同开始日期变化，单向联动到入职日期
const handleContractStartDateChange = (value) => {
  if (value) {
    // 每次修改合同开始日期都联动更新入职日期
    form.hire_date = value
  }
}

// 处理列表视图模式切换
const handleModuleChange = (modules) => {
  console.log('选中的模块:', modules)
  // 可以在这里加载不同的数据
}

// 调动记录相关辅助函数
const transferLogsCache = ref(new Map()) // 缓存调动记录

const getTransferCount = (employee) => {
  const cached = transferLogsCache.value.get(employee.id)
  if (cached) return cached.length
  // 如果没有缓存，异步加载
  loadEmployeeTransferLogs(employee.id)
  return '-'
}

const getLatestTransferTime = (employee) => {
  const cached = transferLogsCache.value.get(employee.id)
  if (cached && cached.length > 0) {
    const time = cached[0].changed_at
    return time ? time.substring(0, 19).replace('T', ' ') : '-'
  }
  return '-'
}

const getLatestTransferFrom = (employee) => {
  const cached = transferLogsCache.value.get(employee.id)
  if (cached && cached.length > 0) {
    return cached[0].from_project_name || '-'
  }
  return '-'
}

const getLatestTransferTo = (employee) => {
  const cached = transferLogsCache.value.get(employee.id)
  if (cached && cached.length > 0) {
    return cached[0].to_project_name || '-'
  }
  return '-'
}

const getLatestTransferOperator = (employee) => {
  const cached = transferLogsCache.value.get(employee.id)
  if (cached && cached.length > 0) {
    return cached[0].operator_name || '-'
  }
  return '-'
}

const loadEmployeeTransferLogs = async (employeeId) => {
  if (transferLogsCache.value.has(employeeId)) return
  
  try {
    const resp = await request({
      url: `/employees/${employeeId}/project-change-logs`,
      method: 'get'
    })
    if (resp && resp.success) {
      transferLogsCache.value.set(employeeId, resp.data || [])
    }
  } catch (e) {
    console.error('加载调动记录失败:', e)
  }
}

const handleReset = () => {
  Object.assign(searchForm, {
    search: '',
    project_id: '',
    contract_status: ''
  })
  handleSearch()
}

// 下载导入模板
const handleDownloadTemplate = async () => {
  if (!currentAccountSetId.value) {
    ElMessage.warning('请先选择账套')
    return
  }
  
  try {
    const response = await request({
      url: '/employees/download-import-template',
      method: 'get',
      params: {
        account_set_id: currentAccountSetId.value
      },
      responseType: 'blob'
    })
    
    // 创建下载链接
    const blob = new Blob([response], { 
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' 
    })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `员工导入模板_${new Date().getTime()}.xlsx`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
    
    ElMessage.success('模板下载成功')
  } catch (error) {
    console.error('下载模板失败:', error)
    ElMessage.error('下载模板失败: ' + (error.message || '未知错误'))
  }
}

// 批量导入员工
const handleImportEmployees = () => {
  if (!currentAccountSetId.value) {
    ElMessage.warning('请先选择账套')
    return
  }
  
  // 创建文件输入元素
  const input = document.createElement('input')
  input.type = 'file'
  input.accept = '.xlsx,.xls'
  
  input.onchange = async (e) => {
    const file = e.target.files[0]
    if (!file) return
    
    // 验证文件类型
    const fileName = file.name
    const fileExt = fileName.substring(fileName.lastIndexOf('.')).toLowerCase()
    if (!['.xlsx', '.xls'].includes(fileExt)) {
      ElMessage.error('只支持Excel文件（.xlsx或.xls）')
      return
    }
    
    // 显示加载提示
    const loading = ElLoading.service({
      lock: true,
      text: '正在导入员工数据，请稍候...',
      background: 'rgba(0, 0, 0, 0.7)'
    })
    
    try {
      // 创建FormData
      const formData = new FormData()
      formData.append('file', file)
      formData.append('account_set_id', currentAccountSetId.value)
      
      // 发送请求
      const response = await request({
        url: '/employees/import',
        method: 'post',
        data: formData,
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      })
      
      loading.close()
      
      if (response.success) {
        const { success_count, fail_count, errors } = response.data
        
        // 显示导入结果
        if (fail_count === 0) {
          ElMessage.success(`导入成功！共导入 ${success_count} 条数据`)
        } else {
          // 有失败的记录，显示详细信息
          ElMessageBox.alert(
            `<div style="max-height: 400px; overflow-y: auto;">
              <p style="margin-bottom: 10px;">导入完成：成功 ${success_count} 条，失败 ${fail_count} 条</p>
              ${errors.length > 0 ? `
                <p style="margin-bottom: 5px; font-weight: bold;">失败详情：</p>
                <ul style="margin: 0; padding-left: 20px; text-align: left;">
                  ${errors.map(err => `<li style="margin-bottom: 5px;">${err}</li>`).join('')}
                </ul>
              ` : ''}
            </div>`,
            '导入结果',
            {
              dangerouslyUseHTMLString: true,
              confirmButtonText: '确定',
              type: fail_count > 0 ? 'warning' : 'success'
            }
          )
        }
        
        // 刷新列表
        handleSearch()
      } else {
        ElMessage.error(response.message || '导入失败')
      }
    } catch (error) {
      loading.close()
      console.error('导入失败:', error)
      ElMessage.error('导入失败: ' + (error.response?.data?.message || error.message || '未知错误'))
    }
  }
  
  // 触发文件选择
  input.click()
}

// 处理表格选择变化
const handleSelectionChange = (selection) => {
  selectedEmployees.value = selection
}

// 批量导出登记表PDF（智能选择入职登记表或从业人员登记表，带签名合成）
const handleBatchExportPdf = async () => {
  if (selectedEmployees.value.length === 0) {
    ElMessage.warning('请先选择要导出的员工')
    return
  }
  
  const loading = ElLoading.service({
    lock: true,
    text: `正在生成PDF（0/${selectedEmployees.value.length}）...`,
    background: 'rgba(0, 0, 0, 0.7)'
  })
  
  try {
    // 1. 获取所有员工的PDF数据（智能选择登记表类型）
    const response = await fetch('/api/get-batch-pdfs-for-merge', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        employee_ids: selectedEmployees.value.map(e => e.id)
      })
    })
    
    const result = await response.json()
    
    if (!result.success) {
      throw new Error(result.message)
    }
    
    // 2. 使用pdf-lib合成每个员工的签名
    const processedPdfs = []
    
    for (let i = 0; i < result.data.length; i++) {
      const item = result.data[i]
      
      loading.setText(`正在处理PDF（${i + 1}/${result.data.length}）...`)
      
      if (item.error) {
        console.error(`员工 ${item.employee_id} 处理失败:`, item.error)
        continue
      }
      
      try {
        // 解码PDF
        const pdfBytes = Uint8Array.from(
          atob(item.pdf_base64),
          c => c.charCodeAt(0)
        )
        const pdfDoc = await PDFDocument.load(pdfBytes)
        
        // 如果有签名，嵌入签名
        if (item.signature_base64) {
          const signatureBytes = Uint8Array.from(
            atob(item.signature_base64),
            c => c.charCodeAt(0)
          )
          const signatureImage = await pdfDoc.embedPng(signatureBytes)
          
          // 获取最后一页
          const pages = pdfDoc.getPages()
          const lastPage = pages[pages.length - 1]
          const { width, height } = lastPage.getSize()
          
          // 使用后端返回的签名位置（动态计算）
          const position = item.signature_position || {
            x: 115,
            y: 142,
            width: 50,
            height: 20,
            from_bottom: true
          }
          
          // 计算实际Y坐标（pdf-lib坐标原点在左下角）
          const sigX = position.x
          const sigY = position.from_bottom ? position.y : (height - position.y)
          const sigWidth = position.width
          const sigHeight = position.height
          
          lastPage.drawImage(signatureImage, {
            x: sigX,
            y: sigY,
            width: sigWidth,
            height: sigHeight,
            opacity: 1
          })
        }
        
        // 保存PDF
        const modifiedPdfBytes = await pdfDoc.save()
        
        // 根据登记表类型设置文件名
        const formTypeName = item.form_type === 'registration' ? '从业人员登记表' : '入职登记表'
        
        processedPdfs.push({
          name: item.employee_name,
          formType: formTypeName,
          content: modifiedPdfBytes
        })
      } catch (error) {
        console.error(`处理员工 ${item.employee_name} 的PDF失败:`, error)
      }
    }
    
    if (processedPdfs.length === 0) {
      throw new Error('没有成功生成任何PDF')
    }
    
    // 3. 下载PDF（单个直接下载，多个打包成ZIP）
    if (processedPdfs.length === 1) {
      // 单个PDF直接下载
      const blob = new Blob([processedPdfs[0].content], { type: 'application/pdf' })
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `${processedPdfs[0].name}_${processedPdfs[0].formType}.pdf`
      link.click()
      URL.revokeObjectURL(url)
    } else {
      // 多个PDF打包成ZIP
      try {
        const JSZip = (await import('jszip')).default
        const zip = new JSZip()
        
        processedPdfs.forEach(pdf => {
          zip.file(`${pdf.name}_${pdf.formType}.pdf`, pdf.content)
        })
        
        const zipBlob = await zip.generateAsync({ type: 'blob' })
        const url = URL.createObjectURL(zipBlob)
        const link = document.createElement('a')
        link.href = url
        link.download = `登记表_${new Date().getTime()}.zip`
        link.click()
        URL.revokeObjectURL(url)
      } catch (error) {
        console.error('打包ZIP失败:', error)
        ElMessage.error('需要安装jszip库来打包多个PDF文件')
        throw error
      }
    }
    
    loading.close()
    ElMessage.success(`成功导出 ${processedPdfs.length} 个PDF文件`)
    
  } catch (error) {
    loading.close()
    console.error('批量导出PDF失败:', error)
    ElMessage.error('导出失败: ' + error.message)
  }
}

// 批量下载员工资料
const handleBatchDownloadDocuments = async () => {
  if (selectedEmployees.value.length === 0) {
    ElMessage.warning('请先选择要下载资料的员工')
    return
  }
  
  const loading = ElMessage({
    message: `正在打包下载资料（0/${selectedEmployees.value.length}）...`,
    type: 'info',
    duration: 0
  })
  
  try {
    const employeeIds = selectedEmployees.value.map(e => e.id)
    
    // 1. 获取所有员工的资料数据
    const response = await request({
      url: '/employees/batch-download-documents',
      method: 'post',
      data: { employee_ids: employeeIds }
    })
    
    if (!response.success) {
      throw new Error(response.message || '获取资料失败')
    }
    
    const employeesData = response.data
    
    if (!employeesData || employeesData.length === 0) {
      throw new Error('没有可下载的资料')
    }
    
    // 2. 使用JSZip打包文件
    loading.message = `正在打包资料...`
    
    const JSZip = (await import('jszip')).default
    const zip = new JSZip()
    
    let totalFiles = 0
    
    for (const employee of employeesData) {
      // 为每个员工创建文件夹
      const folderName = `${employee.employee_name}_${employee.employee_number}`
      const folder = zip.folder(folderName)
      
      for (const file of employee.files) {
        // 解码base64内容
        const binaryContent = atob(file.content)
        const bytes = new Uint8Array(binaryContent.length)
        for (let i = 0; i < binaryContent.length; i++) {
          bytes[i] = binaryContent.charCodeAt(i)
        }
        
        folder.file(file.name, bytes)
        totalFiles++
      }
    }
    
    // 3. 生成并下载ZIP
    loading.message = `正在生成ZIP文件...`
    
    const zipBlob = await zip.generateAsync({ type: 'blob' })
    const url = URL.createObjectURL(zipBlob)
    const link = document.createElement('a')
    link.href = url
    link.download = `员工资料_${new Date().getTime()}.zip`
    link.click()
    URL.revokeObjectURL(url)
    
    loading.close()
    ElMessage.success(`成功下载 ${employeesData.length} 个员工的 ${totalFiles} 份资料`)
    
  } catch (error) {
    loading.close()
    console.error('批量下载资料失败:', error)
    ElMessage.error('下载失败: ' + (error.message || '未知错误'))
  }
}

// 计算合同结束日期
const calculateContractEndDate = () => {
  if (form.contract_start_date && form.contract_months) {
    const startDate = new Date(form.contract_start_date)
    // 添加月份
    startDate.setMonth(startDate.getMonth() + form.contract_months)
    // 减去一天（因为合同是从开始日期到结束日期，包含开始日期）
    startDate.setDate(startDate.getDate() - 1)
    // 格式化为 YYYY-MM-DD
    const year = startDate.getFullYear()
    const month = String(startDate.getMonth() + 1).padStart(2, '0')
    const day = String(startDate.getDate()).padStart(2, '0')
    form.contract_end_date = `${year}-${month}-${day}`
  }
}

// 处理单选项目变化
const handleSingleProjectChange = async (projectId) => {
  // 将单选值转换为数组格式，保持与后端兼容
  form.project_ids = projectId ? [projectId] : []
  // 调用原有的项目变化处理逻辑
  await handleProjectIdsChange(form.project_ids)
}

// 处理项目选择变化
const handleProjectIdsChange = async (projectIds) => {
  // 加载社保、医保和公积金地区
  await loadProjectSocialSecurityRegions(projectIds)
  await loadProjectMedicalInsuranceRegions(projectIds)
  await loadProjectHousingFundRegions(projectIds)
  
  // 加载其他保险信息
  await loadProjectOtherInsurancePolicies(projectIds)
  await loadProjectLargeMedicalInsuranceConfigs(projectIds)
  
  // 清空已选择的参保地区（因为项目变了）
  form.social_security_region_id = null
  form.medical_insurance_region_id = null
  form.housing_fund_region_id = null
  form.housing_fund_config_id = null
  form.large_medical_insurance_config_id = null
  selectedSocialSecurityRegion.value = null
  selectedMedicalInsuranceRegion.value = null
  selectedHousingFundRegion.value = null
  selectedHousingFundConfig.value = null
  selectedLargeMedicalInsuranceConfig.value = null
  availableHousingFundConfigs.value = []
}

// 加载项目的其他保险信息
const loadProjectOtherInsurancePolicies = async (projectIds) => {
  if (!projectIds || projectIds.length === 0) {
    projectOtherInsurancePolicies.value = []
    return
  }
  
  try {
    const response = await request.get(`/employees/projects/${projectIds[0]}/other-insurance-policies`, {
      params: { current_account_set_id: currentAccountSetId.value }
    })
    
    if (response.success) {
      projectOtherInsurancePolicies.value = response.data || []
    } else {
      projectOtherInsurancePolicies.value = []
    }
  } catch (error) {
    console.error('加载其他保险信息失败:', error)
    projectOtherInsurancePolicies.value = []
  }
}

// 加载项目的大额医疗保险配置
const loadProjectLargeMedicalInsuranceConfigs = async (projectIds) => {
  if (!projectIds || projectIds.length === 0) {
    availableLargeMedicalInsuranceConfigs.value = []
    return
  }
  
  try {
    const response = await request.get(`/employees/projects/${projectIds[0]}/large-medical-insurance-configs`, {
      params: { current_account_set_id: currentAccountSetId.value }
    })
    
    if (response.success) {
      availableLargeMedicalInsuranceConfigs.value = response.data || []
    } else {
      availableLargeMedicalInsuranceConfigs.value = []
    }
  } catch (error) {
    console.error('加载大额医疗保险配置失败:', error)
    availableLargeMedicalInsuranceConfigs.value = []
  }
}

// 处理社保地区变化
const handleSocialSecurityRegionChange = (regionId) => {
  if (regionId) {
    selectedSocialSecurityRegion.value = availableSocialSecurityRegions.value.find(r => r.id === regionId)
  } else {
    selectedSocialSecurityRegion.value = null
  }
}

// 处理医保地区变化
const handleMedicalInsuranceRegionChange = (regionId) => {
  if (regionId) {
    selectedMedicalInsuranceRegion.value = availableMedicalInsuranceRegions.value.find(r => r.id === regionId)
  } else {
    selectedMedicalInsuranceRegion.value = null
  }
}

// 处理公积金地区变化
const handleHousingFundRegionChange = async (regionId) => {
  if (regionId) {
    selectedHousingFundRegion.value = availableHousingFundRegions.value.find(r => r.id === regionId)
    // 加载该地区下的配置
    await loadHousingFundConfigs(regionId)
    // 清空已选择的配置
    form.housing_fund_config_id = null
    selectedHousingFundConfig.value = null
  } else {
    selectedHousingFundRegion.value = null
    availableHousingFundConfigs.value = []
    form.housing_fund_config_id = null
    selectedHousingFundConfig.value = null
  }
}

// 加载公积金配置
const loadHousingFundConfigs = async (regionId) => {
  if (!regionId) {
    availableHousingFundConfigs.value = []
    return
  }
  
  loadingHousingFundConfigs.value = true
  try {
    const response = await request.get(`/housing-fund-regions/${regionId}/configs`)
    if (response.success) {
      availableHousingFundConfigs.value = response.data || []
      
      // 默认选择第一个配置
      if (availableHousingFundConfigs.value.length > 0) {
        const firstConfig = availableHousingFundConfigs.value[0]
        form.housing_fund_config_id = firstConfig.id
        selectedHousingFundConfig.value = firstConfig
      }
    } else {
      availableHousingFundConfigs.value = []
    }
  } catch (error) {
    console.error('加载公积金配置失败:', error)
    ElMessage.error('加载公积金配置失败')
    availableHousingFundConfigs.value = []
  } finally {
    loadingHousingFundConfigs.value = false
  }
}

// 处理公积金配置变化
const handleHousingFundConfigChange = (configId) => {
  if (configId) {
    selectedHousingFundConfig.value = availableHousingFundConfigs.value.find(c => c.id === configId)
  } else {
    selectedHousingFundConfig.value = null
  }
}

// 处理大额医疗保险配置变化
const handleLargeMedicalInsuranceConfigChange = (configId) => {
  if (configId) {
    selectedLargeMedicalInsuranceConfig.value = availableLargeMedicalInsuranceConfigs.value.find(c => c.id === configId)
    const config = selectedLargeMedicalInsuranceConfig.value
    
    if (config?.calculation_type === 'base') {
      // 根据 base_source 判断是否为特殊地区
      if (config.base_source === 'config') {
        // 特殊地区：从配置同步个人基数和公司基数
        form.large_medical_base = config.employee_base_amount || config.base_amount
        form.large_medical_company_base = config.base_amount
      } else {
        // 普通地区：用户可以手动输入，清空公司基数
        form.large_medical_company_base = null
      }
    } else {
      // 固定金额类型不需要基数
      form.large_medical_base = null
      form.large_medical_company_base = null
    }
  } else {
    selectedLargeMedicalInsuranceConfig.value = null
    form.large_medical_base = null
    form.large_medical_company_base = null
  }
}


const handleSizeChange = (size) => {
  pagination.pageSize = size
  loadEmployees()
}

const handleCurrentChange = (page) => {
  pagination.currentPage = page
  loadEmployees()
}

// 辅助函数：将字符串类型的数值字段转换为数字（后端返回的 decimal 字段是字符串类型）
const convertNumericFields = (data) => {
  const numericFields = [
    'basic_salary', 'social_security_base', 'medical_insurance_base', 
    'housing_fund_base', 'large_medical_base', 'large_medical_company_base',
    'personal_investment_amount', 'personal_investment_ratio'
  ]
  const result = { ...data }
  numericFields.forEach(field => {
    if (result[field] !== null && result[field] !== undefined && result[field] !== '') {
      result[field] = parseFloat(result[field])
    }
  })
  return result
}

const handleView = async (row) => {
  // 查看员工详情 - 只调用一个接口获取所有数据
  isViewMode.value = true
  isEdit.value = false
  activeTab.value = 'employee'
  registrationFormType.value = 'onboarding'  // 重置登记表类型
  registrationForm.value = null  // 重置从业人员登记表数据
  loadTransferLogs(row.id)
  
  // 如果是离职或退休状态，加载离职证明
  if (['terminated', 'retired'].includes(row.contract_status)) {
    loadResignationCertificates()
  }
  
  try {
    // 调用合并接口获取所有数据
    const response = await request({
      url: `/employees/${row.id}/view-details`,
      method: 'get'
    })
    
    if (response.success && response.data) {
      const data = response.data
      
      // 1. 设置员工基本信息（转换数值字段类型）
      const employeeData = convertNumericFields(data.employee)
      Object.assign(form, {
        ...employeeData,
        project_ids: data.employee.project_ids || data.employee.projects?.map(p => p.id) || [],
        salary_items: Array.isArray(data.employee.salary_items) ? data.employee.salary_items : []
      })
      
      // 2. 设置项目相关的地区信息
      if (data.project_regions) {
        // 社保地区
        if (data.project_regions.social_security) {
          availableSocialSecurityRegions.value = data.project_regions.social_security
          if (form.social_security_region_id) {
            selectedSocialSecurityRegion.value = availableSocialSecurityRegions.value.find(r => r.id === form.social_security_region_id)
          }
        }
        
        // 医保地区
        if (data.project_regions.medical_insurance) {
          availableMedicalInsuranceRegions.value = data.project_regions.medical_insurance
          if (form.medical_insurance_region_id) {
            selectedMedicalInsuranceRegion.value = availableMedicalInsuranceRegions.value.find(r => r.id === form.medical_insurance_region_id)
          }
        }
        
        // 公积金地区
        if (data.project_regions.housing_fund) {
          availableHousingFundRegions.value = data.project_regions.housing_fund
          if (form.housing_fund_region_id) {
            selectedHousingFundRegion.value = availableHousingFundRegions.value.find(r => r.id === form.housing_fund_region_id)
          }
        }
      }
      
      // 3. 设置公积金配置
      if (data.housing_fund_configs && data.housing_fund_configs.length > 0) {
        availableHousingFundConfigs.value = data.housing_fund_configs
        if (form.housing_fund_config_id) {
          selectedHousingFundConfig.value = availableHousingFundConfigs.value.find(c => c.id === form.housing_fund_config_id)
        }
      }
      
      // 4. 设置其他保险政策
      if (data.other_insurance_policies) {
        projectOtherInsurancePolicies.value = data.other_insurance_policies
      }
      
      // 5. 设置大额医疗保险配置
      if (data.large_medical_insurance_configs) {
        availableLargeMedicalInsuranceConfigs.value = data.large_medical_insurance_configs
        if (form.large_medical_insurance_config_id) {
          selectedLargeMedicalInsuranceConfig.value = availableLargeMedicalInsuranceConfigs.value.find(c => c.id === form.large_medical_insurance_config_id)
        }
      }
      
      // 6. 设置入职登记表
      if (data.onboarding_form) {
        onboardingForm.value = data.onboarding_form
        onboardingFormLoading.value = false
      } else {
        onboardingForm.value = null
        onboardingFormLoading.value = false
      }
      
      // 7. 设置登记表类型（从后端返回的数据中获取）
      registrationFormType.value = data.registration_form_type || 'onboarding'
      console.log('handleView - 登记表类型:', registrationFormType.value)
      
      // 8. 加载从业人员登记表（仅当类型为registration时）
      if (registrationFormType.value === 'registration') {
        loadRegistrationForm(row.id)
      }

      // 9. 工资审批相关展示数据
      pendingSalaryAdjustment.value = data.pending_salary_adjustment || null
      currentSalarySnapshot.value = {
        basic_salary: employeeData.basic_salary,
        salary_items: normalizeSalaryItems(employeeData.salary_items)
      }
    } else {
      // 如果接口失败，回退到原有逻辑
      const rowData = convertNumericFields(row)
      Object.assign(form, {
        ...rowData,
        project_ids: row.project_ids || row.projects?.map(p => p.id) || [],
        salary_items: Array.isArray(row.salary_items) ? row.salary_items : []
      })
      onboardingForm.value = null
      onboardingFormLoading.value = false
      registrationForm.value = null
      registrationFormType.value = 'onboarding'
      pendingSalaryAdjustment.value = null
      currentSalarySnapshot.value = {
        basic_salary: rowData.basic_salary,
        salary_items: normalizeSalaryItems(rowData.salary_items)
      }
    }
  } catch (error) {
    console.error('获取员工详情失败:', error)
    ElMessage.error('获取员工详情失败: ' + (error.response?.data?.message || error.message))
    
    // 如果接口失败，回退到原有逻辑
    const rowData = convertNumericFields(row)
    Object.assign(form, {
      ...rowData,
      project_ids: row.project_ids || row.projects?.map(p => p.id) || []
    })
    onboardingForm.value = null
    onboardingFormLoading.value = false
    registrationForm.value = null
    registrationFormType.value = 'onboarding'
    pendingSalaryAdjustment.value = null
    currentSalarySnapshot.value = {
      basic_salary: rowData.basic_salary,
      salary_items: normalizeSalaryItems(rowData.salary_items)
    }
  }

  showCreateDialog.value = true
}

const handleEdit = async (row) => {
  // 编辑员工详情 - 使用统一的接口获取所有数据
  isEdit.value = true
  isViewMode.value = false
  activeTab.value = 'employee'
  loadTransferLogs(row.id)
  
  // 如果是离职或退休状态，加载离职证明
  if (['terminated', 'retired'].includes(row.contract_status)) {
    loadResignationCertificates()
  }
  
  try {
    // 调用合并接口获取所有数据
    const response = await request({
      url: `/employees/${row.id}/view-details`,
      method: 'get'
    })
    
    if (response.success && response.data) {
      const data = response.data
      
      // 1. 设置员工基本信息（转换数值字段类型）
      const employeeData = convertNumericFields(data.employee)
      Object.assign(form, {
        ...employeeData,
        project_ids: data.employee.project_ids || data.employee.projects?.map(p => p.id) || [],
        salary_items: Array.isArray(data.employee.salary_items) ? data.employee.salary_items : []
      })
      
      // 2. 设置项目相关的地区信息
      if (data.project_regions) {
        // 社保地区
        if (data.project_regions.social_security) {
          availableSocialSecurityRegions.value = data.project_regions.social_security
          if (form.social_security_region_id) {
            selectedSocialSecurityRegion.value = availableSocialSecurityRegions.value.find(r => r.id === form.social_security_region_id)
          }
        }
        
        // 医保地区
        if (data.project_regions.medical_insurance) {
          availableMedicalInsuranceRegions.value = data.project_regions.medical_insurance
          if (form.medical_insurance_region_id) {
            selectedMedicalInsuranceRegion.value = availableMedicalInsuranceRegions.value.find(r => r.id === form.medical_insurance_region_id)
          }
        }
        
        // 公积金地区
        if (data.project_regions.housing_fund) {
          availableHousingFundRegions.value = data.project_regions.housing_fund
          if (form.housing_fund_region_id) {
            selectedHousingFundRegion.value = availableHousingFundRegions.value.find(r => r.id === form.housing_fund_region_id)
          }
        }
      }
      
      // 3. 设置公积金配置
      if (data.housing_fund_configs && data.housing_fund_configs.length > 0) {
        availableHousingFundConfigs.value = data.housing_fund_configs
        if (form.housing_fund_config_id) {
          selectedHousingFundConfig.value = availableHousingFundConfigs.value.find(c => c.id === form.housing_fund_config_id)
        }
      }
      
      // 4. 设置其他保险政策
      if (data.other_insurance_policies) {
        projectOtherInsurancePolicies.value = data.other_insurance_policies
      }
      
      // 5. 设置大额医疗保险配置
      if (data.large_medical_insurance_configs) {
        availableLargeMedicalInsuranceConfigs.value = data.large_medical_insurance_configs
        if (form.large_medical_insurance_config_id) {
          selectedLargeMedicalInsuranceConfig.value = availableLargeMedicalInsuranceConfigs.value.find(c => c.id === form.large_medical_insurance_config_id)
        }
      }
      
      // 6. 设置入职登记表
      if (data.onboarding_form) {
        onboardingForm.value = data.onboarding_form
        onboardingFormLoading.value = false
      } else {
        onboardingForm.value = null
        onboardingFormLoading.value = false
      }
      
      // 7. 设置登记表类型（从后端返回的数据中获取）
      registrationFormType.value = data.registration_form_type || 'onboarding'
      
      // 8. 加载从业人员登记表（仅当类型为registration时）
      if (registrationFormType.value === 'registration') {
        loadRegistrationForm(row.id)
      }
      
      // 9. 加载大额医疗保险状态
      currentEditingEmployeeId.value = row.id
      await loadCurrentLargeMedicalStatus(row.id)

      // 10. 工资审批相关展示数据
      pendingSalaryAdjustment.value = data.pending_salary_adjustment || null
      currentSalarySnapshot.value = {
        basic_salary: employeeData.basic_salary,
        salary_items: normalizeSalaryItems(employeeData.salary_items)
      }
    } else {
      // 如果接口失败，回退到原有逻辑
      const rowData = convertNumericFields(row)
      Object.assign(form, {
        ...rowData,
        project_ids: row.project_ids || row.projects?.map(p => p.id) || [],
        salary_items: Array.isArray(row.salary_items) ? row.salary_items : []
      })
      onboardingForm.value = null
      onboardingFormLoading.value = false
      registrationForm.value = null
      registrationFormType.value = 'onboarding'
      currentLargeMedicalStatus.value = null
      pendingSalaryAdjustment.value = null
      currentSalarySnapshot.value = {
        basic_salary: rowData.basic_salary,
        salary_items: normalizeSalaryItems(rowData.salary_items)
      }
    }
  } catch (error) {
    console.error('获取员工详情失败:', error)
    ElMessage.error('获取员工详情失败: ' + (error.response?.data?.message || error.message))
    
    // 如果接口失败，回退到原有逻辑
    const rowData = convertNumericFields(row)
    Object.assign(form, {
      ...rowData,
      project_ids: row.project_ids || row.projects?.map(p => p.id) || []
    })
    onboardingForm.value = null
    onboardingFormLoading.value = false
    registrationForm.value = null
    registrationFormType.value = 'onboarding'
    currentLargeMedicalStatus.value = null
    pendingSalaryAdjustment.value = null
    currentSalarySnapshot.value = {
      basic_salary: rowData.basic_salary,
      salary_items: normalizeSalaryItems(rowData.salary_items)
    }
  }

  showCreateDialog.value = true
}

// 线下入职相关方法
const handleOfflineOnboarding = (row) => {
  // 检查是否有待审批的申请
  if (row.pending_offline_onboarding) {
    ElMessage.warning('已有待审批的线下入职申请，请勿重复提交')
    return
  }
  
  currentOfflineEmployee.value = row
  // 重置表单
  offlineOnboardingForm.hire_date = ''
  offlineOnboardingForm.contract_start_date = ''
  offlineOnboardingForm.contract_end_date = ''
  offlineOnboardingForm.probation_end_date = ''
  showOfflineOnboardingDialog.value = true
}

const submitOfflineOnboardingForm = async () => {
  // 不需要验证，直接提交
  try {
    submittingOfflineOnboarding.value = true
    
    await submitOfflineOnboarding(currentOfflineEmployee.value.id, {
      hire_date: offlineOnboardingForm.hire_date,
      contract_start_date: offlineOnboardingForm.contract_start_date,
      contract_end_date: offlineOnboardingForm.contract_end_date,
      probation_end_date: offlineOnboardingForm.probation_end_date
    })
    
    ElMessage.success('线下入职审批已提交')
    showOfflineOnboardingDialog.value = false
    currentOfflineEmployee.value = null
    loadEmployees()
    
  } catch (error) {
    console.error('Submit offline onboarding error:', error)
    ElMessage.error(error.response?.data?.message || '提交失败')
  } finally {
    submittingOfflineOnboarding.value = false
  }
}

const cancelOfflineOnboarding = () => {
  showOfflineOnboardingDialog.value = false
  currentOfflineEmployee.value = null
}

// 加载待上传合同的员工列表
const loadPendingContractUpload = async () => {
  try {
    const response = await getPendingContractUpload()
    pendingContractUploadList.value = response.data || []
  } catch (error) {
    console.error('Load pending contract upload error:', error)
  }
}

// 标记合同已上传
const handleMarkContractUploaded = async (employeeId) => {
  try {
    await ElMessageBox.confirm(
      '确认该员工的合同已上传？',
      '确认',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
    
    await markContractUploaded(employeeId)
    ElMessage.success('已标记合同已上传')
    loadPendingContractUpload()
    
  } catch (error) {
    if (error !== 'cancel' && error !== 'close') {
      console.error('Mark contract uploaded error:', error)
      ElMessage.error(error.response?.data?.message || '操作失败')
    }
  }
}

const handleSubmitSalaryApproval = () => {
  if (pendingSalaryAdjustment.value) {
    ElMessage.warning('当前已有工资调整审批在进行中')
    return
  }

  salaryApprovalForm.reason = ''
  salaryApprovalForm.stamp_method = 'online'
  showSalaryApprovalDialog.value = true
}

const confirmSubmitSalaryApproval = async () => {
  if (pendingSalaryAdjustment.value) {
    ElMessage.warning('当前已有工资调整审批在进行中')
    return
  }

  if (!form.id) {
    ElMessage.warning('缺少员工信息，无法提交审批')
    return
  }

  if (!salaryApprovalForm.reason || salaryApprovalForm.reason.trim() === '') {
    ElMessage.warning('请填写调整原因')
    return
  }

  const payload = {
    employee_id: form.id,
    basic_salary: Number(form.basic_salary || 0),
    salary_items: normalizeSalaryItems(form.salary_items),
    reason: salaryApprovalForm.reason.trim(),
    stamp_method: salaryApprovalForm.stamp_method
  }

  try {
    submittingSalaryApproval.value = true
    await submitSalaryAdjustmentApproval(payload)

    ElMessage.success('工资调整审批已提交，请等待审批')
    showSalaryApprovalDialog.value = false
    pendingSalaryAdjustment.value = {
      basic_salary: payload.basic_salary,
      salary_items: payload.salary_items,
      reason: payload.reason
    }
  } catch (error) {
    console.error('Submit salary adjustment approval error:', error)
    ElMessage.error(error.response?.data?.message || '提交失败')
  } finally {
    submittingSalaryApproval.value = false
  }
}

const handleDelete = async (row) => {
  try {
    // 1. 检查员工是否是在职状态
    const isActive = row.contract_status === 'active'
    
    // 2. 检查当前用户是否是管理员
    const currentUser = JSON.parse(localStorage.getItem('user') || '{}')
    const isAdmin = currentUser.role === 'admin' || currentUser.role === 'super_admin'
    
    if (isActive && !isAdmin) {
      // 在职员工且非管理员，需要审批
      await ElMessageBox.confirm(
        '该员工为在职状态，删除需要提交审批。是否提交删除审批？',
        '需要审批',
        {
          confirmButtonText: '提交审批',
          cancelButtonText: '取消',
          type: 'warning',
          distinguishCancelAndClose: true
        }
      )
      
      // 打开删除审批对话框
      showDeleteApprovalDialog.value = true
      employeeToDelete.value = row
      deleteApprovalForm.reason = ''
      deleteApprovalForm.stamp_method = 'online' // 重置盖章方式
      
    } else {
      // 非在职员工或管理员，可以直接删除
      const confirmMessage = isActive 
        ? '您是管理员，可以直接删除在职员工。确定要删除吗？'
        : '确定要删除该员工吗？'
      
      await ElMessageBox.confirm(
        confirmMessage,
        '确认删除',
        {
          confirmButtonText: '确定删除',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )
      
      await deleteEmployee(row.id)
      ElMessage.success('删除成功')
      loadEmployees()
    }
  } catch (error) {
    if (error !== 'cancel' && error !== 'close') {
      console.error('Delete employee error:', error)
      ElMessage.error(error.response?.data?.message || '操作失败')
    }
  }
}

// 判断是否显示删除按钮
// 规则：如果有待审批的删除申请，则不显示删除按钮
const canShowDeleteButton = (row) => {
  // 检查是否有待审批的删除申请
  const hasPendingDeletionApproval = row.pending_deletion_approval === true
  
  // 如果有待审批的删除申请，不显示删除按钮
  if (hasPendingDeletionApproval) {
    return false
  }
  
  return true
}

// 提交删除审批
const handleSubmitDeleteApproval = async () => {
  if (!deleteApprovalForm.reason || deleteApprovalForm.reason.trim() === '') {
    ElMessage.warning('请填写删除原因')
    return
  }
  
  try {
    submittingDeleteApproval.value = true
    
    // 调用API提交删除审批
    await request({
      url: '/employees/delete-approval',
      method: 'post',
      data: {
        employee_id: employeeToDelete.value.id,
        reason: deleteApprovalForm.reason,
        stamp_method: deleteApprovalForm.stamp_method // 盖章方式
      }
    })
    
    ElMessage.success('删除审批已提交，请等待审批')
    showDeleteApprovalDialog.value = false
    employeeToDelete.value = null
    deleteApprovalForm.reason = ''
    deleteApprovalForm.stamp_method = 'online' // 重置盖章方式
    
  } catch (error) {
    console.error('Submit delete approval error:', error)
    ElMessage.error(error.response?.data?.message || '提交失败')
  } finally {
    submittingDeleteApproval.value = false
  }
}

// 添加到批量创建列表
const handleAddToBatch = async () => {
  if (!formRef.value) return
  
  await formRef.value.validate(async (valid) => {
    if (valid) {
      // 深拷贝当前表单数据
      const employeeData = JSON.parse(JSON.stringify(form))
      
      // 添加到批量列表
      batchEmployees.value.push(employeeData)
      
      ElMessage.success(`已添加 ${employeeData.name}，可以继续添加下一个员工`)
      
      // 重置表单，准备添加下一个员工
      await handleNewEmployee()
    }
  })
}

// 从批量列表中移除
const handleRemoveFromBatch = (index) => {
  const employee = batchEmployees.value[index]
  ElMessageBox.confirm(
    `确定要从列表中移除 ${employee.name} 吗？`,
    '确认移除',
    {
      type: 'warning'
    }
  ).then(() => {
    batchEmployees.value.splice(index, 1)
    ElMessage.success('已移除')
  }).catch(() => {})
}

// 清空批量列表
const handleClearBatch = () => {
  ElMessageBox.confirm(
    `确定要清空所有待创建员工吗？共 ${batchEmployees.value.length} 名员工`,
    '确认清空',
    {
      type: 'warning'
    }
  ).then(() => {
    batchEmployees.value = []
    ElMessage.success('已清空')
    showBatchList.value = false
  }).catch(() => {})
}

// 批量创建员工
const handleBatchCreate = async () => {
  if (batchEmployees.value.length === 0) {
    ElMessage.warning('请先添加员工到列表')
    return
  }
  
  try {
    await ElMessageBox.confirm(
      `确定要批量创建 ${batchEmployees.value.length} 名员工吗？`,
      '确认批量创建',
      {
        type: 'info'
      }
    )
    
    batchCreating.value = true
    const successCount = []
    const failedList = []
    
    // 使用 loading 提示
    const loadingInstance = ElLoading.service({
      lock: true,
      text: `正在创建员工 0/${batchEmployees.value.length}`,
      background: 'rgba(0, 0, 0, 0.7)'
    })
    
    // 逐个创建员工
    for (let i = 0; i < batchEmployees.value.length; i++) {
      const employee = batchEmployees.value[i]
      loadingInstance.setText(`正在创建员工 ${i + 1}/${batchEmployees.value.length}: ${employee.name}`)
      
      try {
        await request({
          url: '/employees',
          method: 'post',
          data: employee
        })
        successCount.push(employee.name)
      } catch (error) {
        console.error(`创建员工 ${employee.name} 失败:`, error)
        failedList.push({
          name: employee.name,
          error: error.response?.data?.message || error.message || '未知错误'
        })
      }
    }
    
    loadingInstance.close()
    
    // 显示结果
    if (failedList.length === 0) {
      ElMessage.success(`批量创建成功！共创建 ${successCount.length} 名员工`)
      batchEmployees.value = []
      showBatchList.value = false
      showCreateDialog.value = false
      loadEmployees()
    } else {
      let message = `创建完成：成功 ${successCount.length} 名，失败 ${failedList.length} 名\n\n失败列表：\n`
      failedList.forEach(item => {
        message += `• ${item.name}: ${item.error}\n`
      })
      
      ElMessageBox.alert(message, '批量创建结果', {
        type: failedList.length < batchEmployees.value.length ? 'warning' : 'error',
        confirmButtonText: '确定'
      })
      
      // 只保留失败的员工在列表中
      batchEmployees.value = batchEmployees.value.filter(emp => 
        failedList.some(failed => failed.name === emp.name)
      )
      
      if (successCount.length > 0) {
        loadEmployees()
      }
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('批量创建失败:', error)
      ElMessage.error('批量创建失败')
    }
  } finally {
    batchCreating.value = false
  }
}

// 人员调动（单活跃项目）
const showTransferDialog = ref(false)
const transferEmployee = ref(null)
const transferForm = reactive({
  to_project_id: null,
  reason: ''
})

const openTransferDialog = (row) => {
  transferEmployee.value = row
  const currentIds = (row.project_ids && row.project_ids.length > 0)
    ? row.project_ids
    : (row.projects?.map(p => p.id) || [])
  const firstOther = projects.value.find(p => !currentIds.includes(p.id))
  transferForm.to_project_id = firstOther ? firstOther.id : (currentIds[0] || null)
  transferForm.reason = ''
  showTransferDialog.value = true
}

const confirmTransfer = async () => {
  if (!transferEmployee.value || !transferForm.to_project_id) {
    ElMessage.warning('请选择目标项目')
    return
  }
  try {
    await updateEmployee(transferEmployee.value.id, {
      project_ids: [transferForm.to_project_id],
      transfer_reason: transferForm.reason
    })
    ElMessage.success('调动成功')
    showTransferDialog.value = false
    await loadEmployees()
  } catch (error) {
    console.error('Transfer error:', error)
    ElMessage.error(error.response?.data?.message || '调动失败')
  }
}

const handleSubmit = async () => {
  if (!formRef.value) return
  
  await formRef.value.validate(async (valid, invalidFields) => {
    if (valid) {
      console.log('=== 提交员工表单 ===')
      console.log('表单数据:', form)
      console.log('当前账套ID:', accountSetStore.currentAccountSetId)
      console.log('localStorage中的账套ID:', localStorage.getItem('current_account_set_id'))
      
      submitting.value = true
      try {
        if (isEdit.value) {
          await updateEmployee(form.id, form)
          ElMessage.success('更新成功')
        } else {
          console.log('开始创建员工...')
          
          // 工号由后端自动生成，不需要前端检查
          console.log('发送到后端的员工数据:', JSON.stringify(form))
          
          const response = await request({
            url: '/employees',
            method: 'post',
            data: form
          })
          console.log('创建员工响应:', JSON.stringify(response))
          ElMessage.success('创建成功')
        }
        
        showCreateDialog.value = false
        loadEmployees() // 刷新列表
      } catch (error) {
        console.error('Submit error:', error)
        
        // 处理验证错误
        if (error.response && error.response.data && error.response.data.errors) {
          const errors = error.response.data.errors
          let errorMessage = '提交失败：\n'
          
          Object.keys(errors).forEach(key => {
            errorMessage += `• ${errors[key].join(', ')}\n`
          })
          
          ElMessage.error({
            message: errorMessage,
            duration: 5000,
            showClose: true
          })
        } else if (error.response && error.response.data && error.response.data.message) {
          ElMessage.error(error.response.data.message)
        } else {
          ElMessage.error('操作失败，请重试')
        }
      } finally {
        submitting.value = false
      }
    } else {
      // 表单验证失败，显示错误提示
      console.log('表单验证失败:', invalidFields)
      if (invalidFields) {
        const firstField = Object.keys(invalidFields)[0]
        const firstError = invalidFields[firstField]?.[0]?.message || '请检查表单填写是否完整'
        ElMessage.error(firstError)
      } else {
        ElMessage.error('请检查表单填写是否完整')
      }
    }
  })
}

// 生成随机身份证号（用于示例填充）
const generateRandomIdNumber = () => {
  // 地区码（北京市）
  const areaCode = '110101'
  // 随机出生年份 1970-2000
  const year = 1970 + Math.floor(Math.random() * 30)
  // 随机月份 01-12
  const month = String(Math.floor(Math.random() * 12) + 1).padStart(2, '0')
  // 随机日期 01-28
  const day = String(Math.floor(Math.random() * 28) + 1).padStart(2, '0')
  // 随机顺序码 001-999
  const seq = String(Math.floor(Math.random() * 999) + 1).padStart(3, '0')
  // 随机校验码
  const checkCode = String(Math.floor(Math.random() * 10))
  
  return `${areaCode}${year}${month}${day}${seq}${checkCode}`
}

// 示例填充方法
const fillSampleData = () => {
  ElMessageBox.confirm(
    '确定要填充示例数据吗？这将覆盖当前表单中的数据。',
    '示例填充确认',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    }
  ).then(() => {
    // 自动选择第一个有保险配置的项目（如果有项目的话）
    let selectedProjectId = null
    if (projects.value.length > 0) {
      // 优先选择有社保地区配置的项目
      const projectWithInsurance = projects.value.find(p => 
        (p.social_security_regions && p.social_security_regions.length > 0) ||
        (p.housing_fund_regions && p.housing_fund_regions.length > 0)
      )
      selectedProjectId = projectWithInsurance ? projectWithInsurance.id : projects.value[0].id
    }
    const projectIds = selectedProjectId ? [selectedProjectId] : []
    
    // 生成唯一身份证号
    const uniqueIdNumber = generateRandomIdNumber()
    
    // 基础信息
    Object.assign(form, {
      name: '张三',
      // employee_number 由后端自动生成，不需要填充
      position: '软件工程师',
      id_number: uniqueIdNumber,
      phone: '13800138000',
      email: 'zhangsan@example.com',
      gender: 'male',
      birth_date: '1990-01-01',
      hire_date: '2024-01-01',
      contract_start_date: '2024-01-01',
      contract_end_date: '2026-12-31',
      address: '北京市朝阳区建国路1号',
      project_ids: projectIds,
      basic_salary: 12000,
      salary_items: [
        { name: '岗位工资', amount: 8000 },
        { name: '绩效工资', amount: 4000 }
      ],

      // 工资卡信息
      bank_account: '6222021234567890123',
      bank_account_holder: '张三',
      bank_name: '中国工商银行',
      bank_branch: '北京建国路支行',
      
      // 保险基数信息 ⭐ 关键字段
      social_security_base: 5000,
      medical_insurance_base: 5000,
      housing_fund_base: 5000,
      large_medical_base: 5000,
      large_medical_company_base: 5000,
      
      // 一、基础身份信息
      country_region: '中国',
      chinese_name: '张三',
      birth_country: '中国',
      other_id_type: '护照',
      other_id_number: 'G12345678',
      
      // 二、从业任职信息
      personnel_status: '在职',
      employment_type: '雇员',
      employment_date: '2024-01-01',
      resignation_date: '',
      signing_location: '北京', // 签署地示例
      household_type: 'non_agricultural', // 户口类型示例
      annual_employment_status: '首次入职',
      job_title: '普通',
      
      // 三、特殊身份信息
      is_disabled: false,
      disability_cert_type: '',
      disability_cert_number: '',
      is_martyr_family: false,
      martyr_family_cert_number: '',
      is_elderly_alone: false,
      
      // 四、涉税与投资信息
      tax_matter: '任职受雇',
      deduct_expense: true,
      personal_investment_amount: 50000,
      personal_investment_ratio: 10.5,
      
      // 五、出入境信息
      first_entry_date: '2024-01-01',
      expected_departure_date: '2026-12-31',
      
      // 六、联系方式与银行信息
      email_address: 'zhangsan@example.com',
      bank_province: '北京市',
      
      // 七、备注说明信息
      other_notes: '扣缴申报扣缴税款纳税人所得',
      
      // 地址信息
      household_province: '北京市',
      household_city: '北京市',
      household_district: '朝阳区',
      household_address: '建国路1号国贸大厦A座1001室',
      
      residence_province: '北京市',
      residence_city: '北京市',
      residence_district: '朝阳区',
      residence_address: '建国路1号国贸大厦A座1001室',
      
      contact_province: '北京市',
      contact_city: '北京市',
      contact_district: '朝阳区',
      contact_address: '建国路1号国贸大厦A座1001室',
      
      // 其他信息
      education: '本科',
      remarks: '优秀员工，工作认真负责'
    })
    
    // 如果选择了项目，触发项目变更以加载保险配置
    if (projectIds.length > 0) {
      handleProjectIdsChange(projectIds)
    }
    
    ElMessage.success('示例数据填充完成')
  }).catch(() => {
    // 用户取消
  })
}

const handleDialogClose = () => {
  // 如果有待创建的员工，提醒用户
  if (batchEmployees.value.length > 0 && !isEdit.value) {
    ElMessageBox.confirm(
      `当前有 ${batchEmployees.value.length} 名员工待创建，关闭后数据将保留。确定要关闭吗？`,
      '提示',
      {
        type: 'warning',
        confirmButtonText: '确定关闭',
        cancelButtonText: '取消'
      }
    ).then(() => {
      // 用户确认关闭
      resetDialogState()
    }).catch(() => {
      // 用户取消，不关闭对话框
    })
  } else {
    resetDialogState()
  }
}

// 重置对话框状态
const resetDialogState = () => {
  isEdit.value = false
  isViewMode.value = false // 重置查看模式
  activeTab.value = 'employee' // 重置tab
  onboardingForm.value = null // 清空入职登记表数据
  pendingSalaryAdjustment.value = null
  currentSalarySnapshot.value = {
    basic_salary: null,
    salary_items: []
  }
  salaryApprovalForm.reason = ''
  salaryApprovalForm.stamp_method = 'online'
  showSalaryApprovalDialog.value = false
  Object.assign(form, {
    name: '',
    employee_number: '',
    position: '',
    id_number: '',
    phone: '',
    email: '',
    gender: 'male',
    birth_date: '',
    retirement_category: 'worker', // 退休类别重置
    hire_date: '',
    contract_start_date: '',
    contract_end_date: '',
    probation_end_date: '', // 试用期结束日期重置
    contract_months: null, // 签订月份重置
    address: '',
    project_ids: [],
    basic_salary: null,
    salary_items: [],
    // 工资卡信息
    bank_account: '',
    bank_account_holder: '',
    bank_name: '',
    bank_branch: '',
    remittance_remark: '',
    social_security_base: null,
    medical_insurance_base: null,
    housing_fund_base: null,
    large_medical_base: null,
    large_medical_company_base: null,
    social_security_region_id: null,
    medical_insurance_region_id: null,
    housing_fund_region_id: null,
    housing_fund_config_id: null,
    large_medical_insurance_config_id: null,
    social_insurance_enrollment_date: null,
    provident_fund_enrollment_date: null,
    id_card_valid_from: null,
    id_card_valid_until: null,
    
    // 详细员工信息重置
    // 一、基础身份信息
    country_region: '',
    chinese_name: '',
    birth_country: '',
    other_id_type: '',
    other_id_number: '',
    
    // 二、从业任职信息
    personnel_status: '',
    employment_type: '',
    employment_date: '',
    resignation_date: '',
    signing_location: '', // 签署地
    household_type: '', // 户口类型
    annual_employment_status: '',
    job_title: '',
    
    // 三、特殊身份信息
    is_disabled: false,
    disability_cert_type: '',
    disability_cert_number: '',
    is_martyr_family: false,
    martyr_family_cert_number: '',
    is_elderly_alone: false,
    
    // 四、涉税与投资信息
    tax_matter: '',
    deduct_expense: true,
    personal_investment_amount: null,
    personal_investment_ratio: null,
    
    // 五、出入境信息
    first_entry_date: '',
    expected_departure_date: '',
    
    // 六、联系方式与银行信息
    email_address: '',
    bank_province: '',
    
    // 七、地址信息
    household_province: '',
    household_city: '',
    household_district: '',
    household_address: '',
    residence_province: '',
    residence_city: '',
    residence_district: '',
    residence_address: '',
    contact_province: '',
    contact_city: '',
    contact_district: '',
    contact_address: '',
    
    // 其他信息
    education: '',
    remarks: '',
    
    // 八、备注说明信息
    other_notes: ''
  })
  formRef.value?.resetFields()
  showCreateDialog.value = false // 关闭对话框
}

const getEmployeeContractStatusType = (status) => {
  const types = {
    active: 'success',
    expired: 'warning',
    terminated: 'danger',
    retired: 'info',
    unsigned: 'info'
  }
  return types[status] || 'info'
}

const getEmployeeContractStatusText = (status) => {
  const texts = {
    active: '在职',
    expired: '已过期',
    terminated: '已终止',
    retired: '退休',
    unsigned: '未签署',
    null: '未签署'
  }
  return texts[status] || '未签署'
}

// ========== 合同管理相关 ==========
const showContractDialog = ref(false)
const showUploadDialog = ref(false)
const showUploadSignedDialog = ref(false)
const currentEmployee = ref(null)
const contracts = ref([])
const contractsLoading = ref(false)
const transferLogs = ref([])
const transferLogsLoading = ref(false)
const uploading = ref(false)
const uploadingSignedContract = ref(false)

// 上传已签署合同表单
const uploadSignedForm = reactive({
  employee_id: null,
  contract_type: '',
  notes: ''
})
const signedContractFile = ref(null)
const uploadSignedRef = ref()

// 入职登记表相关
const onboardingForm = ref(null)
const onboardingFormLoading = ref(false)
const activeTab = ref('employee')

// 从业人员登记表相关
const registrationForm = ref(null)
const registrationFormLoading = ref(false)
const registrationFormType = ref('onboarding')  // 登记表类型：onboarding-入职登记表，registration-从业人员登记表

// ========== 离职证明相关 ==========
const resignationCertificates = ref([])
const resignationCertificatesLoading = ref(false)
const resignationCertificateUploading = ref(false)

// ========== 盖章签字相关 ==========
const showSignatureDialog = ref(false)
const showPDFEditor = ref(false)
const currentContract = ref(null)
const currentPDFUrl = ref('')
const mySignature = ref(null)
const mySeals = ref([])
const signatureForm = reactive({
  use_signature: false,
  selected_seal_id: null,
  stamp_method: 'online'  // 默认选择线上盖章
})
const signatureFormRef = ref()
const signatureRules = {}
const uploadRef = ref()
const fileList = ref([])

// 上传表单（不在 reactive 对象中存储 File 对象，参考 SharedFiles 的实现）
const uploadForm = reactive({
  employee_id: null,
  contract_type: '',
  template_id: null,
  notes: ''
})

// 可用模板列表
const availableTemplates = ref([])

// 打开合同管理对话框
const handleContractManage = async (row) => {
  console.log('=== 打开合同管理 ===')
  console.log('员工信息:', row)
  console.log('当前账套ID:', accountSetStore.currentAccountSetId)
  
  currentEmployee.value = row
  showContractDialog.value = true
  await loadEmployeeContracts(row.id)
}

// 加载员工合同列表
const loadEmployeeContracts = async (employeeId) => {
  console.log('=== 加载员工合同 ===')
  console.log('员工ID:', employeeId)
  console.log('当前账套ID:', accountSetStore.currentAccountSetId)
  
  contractsLoading.value = true
  try {
    const response = await getEmployeeContracts(employeeId)
    console.log('合同列表响应:', response)
    if (response.success) {
      contracts.value = response.data || []
      console.log('加载到的合同数量:', contracts.value.length)
    }
  } catch (error) {
    console.error('Load contracts error:', error)
    ElMessage.error('加载合同列表失败')
  } finally {
    contractsLoading.value = false
  }
}

// 加载员工调动记录（详情页面使用）
const loadTransferLogs = async (employeeId) => {
  if (!employeeId) {
    transferLogs.value = []
    return
  }
  transferLogsLoading.value = true
  try {
    const resp = await request({
      url: `/employees/${employeeId}/project-change-logs`,
      method: 'get'
    })
    if (resp && resp.success) {
      transferLogs.value = resp.data || []
    } else if (Array.isArray(resp)) {
      transferLogs.value = resp
    } else {
      transferLogs.value = []
    }
  } catch (e) {
    console.error('加载调动记录失败:', e)
    ElMessage.error('加载调动记录失败')
    transferLogs.value = []
  } finally {
    transferLogsLoading.value = false
  }
}

// ========== 离职证明相关方法 ==========
// 加载离职证明列表
const loadResignationCertificates = async () => {
  if (!form.id) {
    resignationCertificates.value = []
    return
  }
  resignationCertificatesLoading.value = true
  try {
    const resp = await request({
      url: `/employees/${form.id}/resignation-certificates`,
      method: 'get'
    })
    if (resp && resp.success) {
      resignationCertificates.value = resp.data || []
    } else if (Array.isArray(resp)) {
      resignationCertificates.value = resp
    } else {
      resignationCertificates.value = []
    }
  } catch (e) {
    console.error('加载离职证明失败:', e)
    ElMessage.error('加载离职证明失败')
    resignationCertificates.value = []
  } finally {
    resignationCertificatesLoading.value = false
  }
}

// 上传前验证
const beforeResignationCertificateUpload = (file) => {
  const isValidType = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'].includes(file.type)
  const isLt10M = file.size / 1024 / 1024 < 10

  if (!isValidType) {
    ElMessage.error('只能上传 JPG/PNG/PDF 格式的文件!')
    return false
  }
  if (!isLt10M) {
    ElMessage.error('文件大小不能超过 10MB!')
    return false
  }
  resignationCertificateUploading.value = true
  return true
}

// 上传成功
const handleResignationCertificateUploadSuccess = (response) => {
  resignationCertificateUploading.value = false
  if (response.success) {
    ElMessage.success('上传成功')
    loadResignationCertificates()
  } else {
    ElMessage.error(response.message || '上传失败')
  }
}

// 上传失败
const handleResignationCertificateUploadError = (error) => {
  resignationCertificateUploading.value = false
  console.error('上传失败:', error)
  ElMessage.error('上传失败，请重试')
}

// 预览离职证明（参考 EmployeeDocumentManager 的实现）
const previewResignationCertificate = (certificate) => {
  try {
    if (!certificate.file_path) {
      ElMessage.warning('无附件')
      return
    }
    
    // 生成文件 URL
    const baseURL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'
    const fileUrl = `${baseURL}/storage/${certificate.file_path}`
    
    const filename = certificate.file_name.toLowerCase()
    
    // 图片、PDF 等直接在新窗口打开
    if (filename.endsWith('.jpg') || filename.endsWith('.jpeg') || filename.endsWith('.png') || 
        filename.endsWith('.gif') || filename.endsWith('.webp') || filename.endsWith('.pdf')) {
      window.open(fileUrl, '_blank')
      ElMessage.success('正在新窗口打开...')
    } else {
      ElMessage.warning('不支持预览该文件类型，请使用下载功能')
    }
  } catch (error) {
    console.error('预览失败:', error)
    ElMessage.error('预览失败')
  }
}

// 下载离职证明（参考 EmployeeDocumentManager 的实现）
const downloadResignationCertificate = async (certificate) => {
  try {
    if (!certificate.file_path) {
      ElMessage.warning('无附件')
      return
    }
    
    ElMessage.info('正在下载，请稍候...')
    
    // 使用 request 下载，设置 responseType 为 blob
    const response = await request({
      url: `/employees/resignation-certificates/${certificate.id}/download`,
      method: 'get',
      responseType: 'blob'
    })
    
    // 创建 Blob - 使用 application/octet-stream 强制下载
    const blob = new Blob([response], { type: 'application/octet-stream' })
    const url = window.URL.createObjectURL(blob)
    
    // 创建下载链接
    const link = document.createElement('a')
    link.href = url
    link.download = certificate.file_name || '离职证明.jpg'
    link.style.display = 'none'
    
    document.body.appendChild(link)
    link.click()
    
    // 清理
    setTimeout(() => {
      document.body.removeChild(link)
      window.URL.revokeObjectURL(url)
    }, 100)
    
    ElMessage.success('下载成功')
  } catch (error) {
    console.error('下载失败:', error)
    ElMessage.error('下载失败')
  }
}

// 删除离职证明
const deleteResignationCertificate = async (certificate) => {
  try {
    await ElMessageBox.confirm(
      `确定要删除文件 "${certificate.file_name}" 吗？`,
      '提示',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
    
    const resp = await request({
      url: `/employees/resignation-certificates/${certificate.id}`,
      method: 'delete'
    })
    
    if (resp && resp.success) {
      ElMessage.success('删除成功')
      loadResignationCertificates()
    } else {
      ElMessage.error(resp.message || '删除失败')
    }
  } catch (e) {
    if (e !== 'cancel') {
      console.error('删除失败:', e)
      ElMessage.error('删除失败')
    }
  }
}

// 格式化文件大小
const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
}

// 获取 token
const getToken = () => {
  return localStorage.getItem('token') || sessionStorage.getItem('token') || ''
}
// ========== 离职证明相关方法结束 ==========

// 选择合同类型
const handleContractTypeSelect = async (contractType) => {
  console.log('=== 开始选择合同类型 ===')
  console.log('1. 合同类型:', contractType)
  console.log('2. 当前员工:', currentEmployee.value)
  console.log('3. 员工ID:', currentEmployee.value?.id)
  
  // 重置表单（参考 SharedFiles 的实现，不在 reactive 对象中存储 File）
  uploadForm.contract_type = contractType
  uploadForm.employee_id = currentEmployee.value?.id || null
  uploadForm.template_id = null
  uploadForm.notes = ''
  fileList.value = []
  
  console.log('4. 设置后的 uploadForm:', {
    contract_type: uploadForm.contract_type,
    employee_id: uploadForm.employee_id,
    notes: uploadForm.notes
  })
  
  if (!uploadForm.employee_id) {
    ElMessage.error('员工信息获取失败，请重试')
    return
  }
  
  showUploadDialog.value = true
  
  // 打开对话框后，如果有合同类型，自动加载模板
  if (uploadForm.contract_type) {
    // 使用 nextTick 确保对话框已经渲染
    await nextTick()
    handleContractTypeChange(uploadForm.contract_type)
  }
}

// 文件选择（参考 SharedFiles 的实现）
const handleFileChange = (file, fileListParam) => {
  console.log('=== 文件选择事件 ===')
  console.log('1. file 对象:', file)
  console.log('2. file.raw:', file.raw)
  console.log('3. fileList:', fileListParam)
  
  fileList.value = fileListParam
  
  console.log('4. 更新后的 fileList:', fileList.value)
}

// 文件移除（参考 SharedFiles 的实现）
const handleFileRemove = (file, uploadFileList) => {
  fileList.value = uploadFileList
  console.log('文件已移除，剩余:', fileList.value)
}

// 文件超过限制
const handleFileExceed = () => {
  ElMessage.warning('只能上传一个文件')
}

// 合同类型变更处理
const handleContractTypeChange = async (contractType) => {
  console.log('🔄 合同类型变更:', contractType)
  console.log('👤 当前员工:', currentEmployee.value)
  
  if (!contractType || !currentEmployee.value) return
  
  // 获取员工的项目
  const employeeProjects = currentEmployee.value.project_ids || []
  console.log('📋 员工项目列表:', employeeProjects)
  
  if (employeeProjects.length === 0) {
    ElMessage.warning('该员工未分配项目，无法获取合同模板')
    return
  }
  
  // 获取第一个项目的所有模板
  const projectId = employeeProjects[0]
  console.log('🎯 使用项目ID:', projectId)
  
  try {
    const response = await getContractTemplates(projectId)
    console.log('📦 模板API响应:', response)
    
    if (response.success && response.data[contractType]) {
      availableTemplates.value = response.data[contractType]
      console.log('✅ 可用模板:', availableTemplates.value)
      
      // 自动选择默认模板
      const defaultTemplate = response.data[contractType].find(template => template.is_default)
      if (defaultTemplate) {
        uploadForm.template_id = defaultTemplate.id
        console.log('🎯 自动选择默认模板:', defaultTemplate.id)
      } else {
        uploadForm.template_id = null
        ElMessage.warning(`项目未设置${getContractTypeText(contractType)}的默认模板`)
      }
    } else {
      availableTemplates.value = []
      uploadForm.template_id = null
      console.log('❌ 没有找到模板数据')
      ElMessage.warning(`项目未设置${getContractTypeText(contractType)}模板`)
    }
  } catch (error) {
    console.error('❌ 获取合同模板失败:', error)
    ElMessage.error('获取合同模板失败')
    availableTemplates.value = []
    uploadForm.template_id = null
  }
}

// 创建合同（带数据填充）
const handleCreateContract = async () => {
  if (!uploadForm.employee_id) {
    ElMessage.warning('员工信息丢失，请重新打开')
    return
  }

  if (!uploadForm.contract_type) {
    ElMessage.warning('请选择合同类型')
    return
  }

  if (!uploadForm.template_id) {
    ElMessage.warning('请选择合同模板')
    return
  }

  uploading.value = true
  try {
    console.log('📤 开始创建带数据填充的合同...')
    
    // 1. 调用新的API获取模板和员工数据
    const prepareData = {
      employee_id: uploadForm.employee_id,
      contract_type: uploadForm.contract_type,
      template_id: uploadForm.template_id,
      notes: uploadForm.notes || ''
    }
    
    console.log('📋 准备数据:', prepareData)
    
    const prepareResponse = await request.post('/employees/contracts/with-placeholder-fill', prepareData)
    
    if (!prepareResponse.success) {
      throw new Error(prepareResponse.message || '准备数据失败')
    }
    
    const { template, employee } = prepareResponse.data
    console.log('✅ 获取到模板和员工数据:', { template, employee })
    
    // 2. 使用PDF填充服务填充数据
    console.log('📝 开始填充PDF数据...')
    const filledPdfBlob = await PdfFillService.fillPdfTemplate(
      template.file_url,
      employee,
      template.placeholder_positions
    )
    
    console.log('✅ PDF数据填充完成')
    
    // 3. 上传填充后的PDF
    const formData = new FormData()
    formData.append('employee_id', uploadForm.employee_id)
    formData.append('template_id', uploadForm.template_id)
    formData.append('contract_type', uploadForm.contract_type)
    formData.append('notes', uploadForm.notes || '')
    formData.append('filled_pdf', filledPdfBlob, 'filled_contract.pdf')
    
    console.log('📤 上传填充后的PDF...')
    const saveResponse = await request.post('/employees/contracts/save-filled', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    
    if (saveResponse.success) {
      ElMessage.success('合同创建成功，数据已自动填充')
      showUploadDialog.value = false
      // 刷新合同列表
      await loadEmployeeContracts(currentEmployee.value.id)
    } else {
      throw new Error(saveResponse.message || '保存合同失败')
    }
    
  } catch (error) {
    console.error('❌ 创建合同失败:', error)
    
    if (error.message.includes('尚未设置占位符位置')) {
      ElMessage.error('该模板尚未设置占位符位置，请先在项目管理中设置占位符位置')
    } else if (error.message.includes('PDF数据填充失败')) {
      ElMessage.error('PDF数据填充失败，请检查模板和员工数据')
    } else {
      ElMessage.error(error.message || '创建合同失败')
    }
  } finally {
    uploading.value = false
  }
}

// 提交合同供员工签署
const handleSubmitContract = async (contract) => {
  try {
    await ElMessageBox.confirm(
      '确认提交该合同供员工签署吗？提交后合同状态将变更为"签署中"',
      '确认提交',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
    
    const response = await submitContract(contract.id)
    if (response.success) {
      ElMessage.success('合同已提交，等待员工签署')
      await loadEmployeeContracts(currentEmployee.value.id)
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('Submit contract error:', error)
      ElMessage.error(error.response?.data?.message || '提交失败')
    }
  }
}

// 提交审批
const handleSubmitApproval = async (contract) => {
  try {
    console.log('=== 提交审批 - 合同信息 ===')
    console.log('合同对象:', contract)
    console.log('合同字段:', {
      id: contract.id,
      contract_file: contract.contract_file,
      original_filename: contract.original_filename,
      status: contract.status
    })
    
    await loadMySignatureAndSeals()
    currentContract.value = contract
    showSignatureDialog.value = true
  } catch (error) {
    console.error('Open signature dialog error:', error)
    ElMessage.error('打开盖章签字对话框失败')
  }
}

// 确认完成合同（保留旧函数，以防其他地方还在用）
const handleCompleteContract = async (contract) => {
  try {
    const response = await completeContract(contract.id)
    if (response.success) {
      ElMessage.success('合同已完成')
      await loadEmployeeContracts(currentEmployee.value.id)
    }
  } catch (error) {
    console.error('Complete contract error:', error)
    ElMessage.error(error.response?.data?.message || '操作失败')
  }
}

// 下载合同（使用 axios 携带 token）
const handleDownloadContract = async (contract) => {
  try {
    console.log('下载合同:', {
      contract_id: contract.id,
      filename: contract.original_filename
    })
    
    ElMessage.info('正在下载，请稍候...')
    
    // 使用 axios 下载，会自动携带 Authorization token
    const response = await fetch(`/api/employees/contracts/${contract.id}/download`, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`
      }
    })
    
    if (!response.ok) {
      throw new Error(`下载失败: ${response.status}`)
    }
    
    // 获取文件 blob
    const blob = await response.blob()
    
    // 创建 blob URL
    const url = window.URL.createObjectURL(blob)
    
    // 创建隐藏的 a 标签进行下载
    const link = document.createElement('a')
    link.href = url
    link.download = contract.original_filename || '合同.pdf'
    link.style.display = 'none'
    
    // 添加到页面并触发点击
    document.body.appendChild(link)
    link.click()
    
    // 清理
    setTimeout(() => {
      document.body.removeChild(link)
      window.URL.revokeObjectURL(url)
    }, 100)
    
    ElMessage.success('下载成功')
  } catch (error) {
    console.error('Download error:', error)
    ElMessage.error('下载失败: ' + (error.message || '未知错误'))
  }
}

// 删除合同
const handleDeleteContract = async (contract) => {
  try {
    await ElMessageBox.confirm(
      '确定要删除该合同吗？此操作不可恢复！',
      '确认删除',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'error'
      }
    )
    
    const response = await deleteContract(contract.id)
    if (response.success) {
      ElMessage.success('合同删除成功')
      await loadEmployeeContracts(currentEmployee.value.id)
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('Delete contract error:', error)
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }
}

// 关闭合同管理对话框
const handleContractDialogClose = () => {
  currentEmployee.value = null
  contracts.value = []
}

// ========== 上传已签署合同相关方法 ==========
// 已签署合同文件选择
const handleSignedFileChange = (file, fileList) => {
  console.log('选择已签署合同文件:', file)
  signedContractFile.value = file.raw
}

// 已签署合同文件移除
const handleSignedFileRemove = () => {
  signedContractFile.value = null
}

// 已签署合同文件超过限制
const handleSignedFileExceed = () => {
  ElMessage.warning('只能上传一个文件')
}

// 上传已签署合同
const handleUploadSignedContract = async () => {
  if (!uploadSignedForm.contract_type) {
    ElMessage.warning('请选择合同类型')
    return
  }

  if (!signedContractFile.value) {
    ElMessage.warning('请选择合同文件')
    return
  }

  // 验证文件类型
  if (signedContractFile.value.type !== 'application/pdf') {
    ElMessage.error('只能上传PDF格式的文件')
    return
  }

  // 验证文件大小（10MB）
  if (signedContractFile.value.size > 10 * 1024 * 1024) {
    ElMessage.error('文件大小不能超过10MB')
    return
  }

  uploadingSignedContract.value = true
  try {
    console.log('开始上传已签署合同...')
    
    const uploadData = {
      employee_id: currentEmployee.value.id,
      contract_type: uploadSignedForm.contract_type,
      contract_file: signedContractFile.value,
      notes: uploadSignedForm.notes || ''
    }
    
    const response = await uploadSignedContract(uploadData)
    
    if (response.success) {
      ElMessage.success('合同上传成功')
      
      // 重置表单
      uploadSignedForm.contract_type = ''
      uploadSignedForm.notes = ''
      signedContractFile.value = null
      if (uploadSignedRef.value) {
        uploadSignedRef.value.clearFiles()
      }
      
      // 关闭对话框
      showUploadSignedDialog.value = false
      
      // 刷新合同列表
      await loadEmployeeContracts(currentEmployee.value.id)
      
      // 刷新员工列表（更新 contract_uploaded 状态）
      await loadEmployees()
    } else {
      ElMessage.error(response.message || '上传失败')
    }
  } catch (error) {
    console.error('上传已签署合同失败:', error)
    ElMessage.error(error.response?.data?.message || '上传失败')
  } finally {
    uploadingSignedContract.value = false
  }
}
// ========== 上传已签署合同相关方法结束 ==========

// 加载入职登记表
const loadOnboardingForm = async (employeeId) => {
  try {
    onboardingFormLoading.value = true
    onboardingForm.value = null

    console.log('=== 开始加载入职登记表 ===')
    console.log('员工ID:', employeeId)

    const response = await request({
      url: `/employees/${employeeId}/onboarding-form`,
      method: 'get'
    })

    console.log('入职登记表API响应:', response)
    console.log('响应数据:', response.data)

    if (response.success && response.data) {
      onboardingForm.value = response.data
      console.log('入职登记表数据已加载:', onboardingForm.value)
    } else {
      console.log('未找到入职登记表数据')
      onboardingForm.value = null
    }
  } catch (error) {
    console.error('获取入职登记表失败:', error)
    console.error('错误详情:', error.response?.data)
    onboardingForm.value = null
    ElMessage.warning('加载入职登记表失败: ' + (error.response?.data?.message || error.message))
  } finally {
    onboardingFormLoading.value = false
  }
}

// 加载从业人员登记表
const loadRegistrationForm = async (employeeId) => {
  try {
    registrationFormLoading.value = true
    registrationForm.value = null

    const response = await request({
      url: `/employees/${employeeId}/registration-form`,
      method: 'get'
    })

    if (response.success && response.data) {
      registrationForm.value = response.data
    } else {
      registrationForm.value = null
    }
  } catch (error) {
    console.error('获取从业人员登记表失败:', error)
    registrationForm.value = null
  } finally {
    registrationFormLoading.value = false
  }
}

// 导出从业人员登记表PDF
const exportRegistrationFormPdf = async (employeeId) => {
  try {
    const response = await request({
      url: `/employees/export-registration-pdfs`,
      method: 'post',
      data: { employee_ids: [employeeId] },
      responseType: 'blob'
    })
    
    const blob = new Blob([response], { type: 'application/pdf' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `从业人员登记表_${form.name || employeeId}.pdf`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
    
    ElMessage.success('导出成功')
  } catch (error) {
    console.error('导出PDF失败:', error)
    ElMessage.error('导出PDF失败')
  }
}

// 测试从业人员登记表PDF（使用示例数据）
const testRegistrationPdf = async () => {
  try {
    const response = await request({
      url: `/employees/test-registration-pdf`,
      method: 'get',
      responseType: 'blob'
    })
    
    const blob = new Blob([response], { type: 'application/pdf' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `从业人员登记表_测试.pdf`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
    
    ElMessage.success('测试PDF生成成功')
  } catch (error) {
    console.error('测试PDF生成失败:', error)
    ElMessage.error('测试PDF生成失败: ' + (error.message || '未知错误'))
  }
}

// 获取婚姻状况文本
const getMaritalStatusText = (status) => {
  const map = {
    'single': '未婚',
    'married': '已婚',
    'divorced': '离异',
    'widowed': '丧偶'
  }
  return map[status] || status || '-'
}

// 格式化日期
const formatDate = (date) => {
  if (!date) return '-'
  const d = new Date(date)
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

// 合同类型文本
const getContractTypeText = (type, row = null) => {
  if (type === 'other') {
    const notes = row?.notes || ''
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
}

// 合同类型颜色
const getContractTypeColor = (type) => {
  const colors = {
    labor: 'success',
    termination: 'warning',
    retirement: 'info',
    other: ''
  }
  return colors[type] || ''
}

// 合同状态文本
const getContractStatusText = (status) => {
  const statuses = {
    draft: '草稿',
    pending_sign: '签署中',
    employee_signed: '乙方已签署',
    in_approval: '审批中',
    completed: '已完成',
    rejected: '已驳回',
    withdrawn: '已撤回'
  }
  return statuses[status] || status
}

// 合同状态颜色
const getContractStatusColor = (status) => {
  const colors = {
    draft: 'info',
    pending_sign: 'warning',
    employee_signed: 'primary',
    in_approval: '',
    completed: 'success',
    rejected: 'danger',
    withdrawn: 'info'
  }
  return colors[status] || ''
}

const getContractSourceType = (row) => {
  if (row?.source_type === 'offline' || row?.source_type === 'online') {
    return row.source_type
  }
  return row?.status === 'completed' && !row?.employee_signed_at ? 'offline' : 'online'
}

const getContractSourceText = (row) => {
  return getContractSourceType(row) === 'offline' ? '线下' : '线上'
}

const getContractSourceColor = (row) => {
  return getContractSourceType(row) === 'offline' ? 'success' : 'primary'
}

// ========== 盖章签字相关方法 ==========

// 加载我的签名和印章
const loadMySignatureAndSeals = async () => {
  try {
    console.log('=== 加载签名和印章 ===')
    
    const sigResponse = await getMySignature()
    console.log('签名响应:', sigResponse)
    if (sigResponse.success) {
      mySignature.value = sigResponse.data
      console.log('签名已加载:', mySignature.value)
    }
    
    const sealsResponse = await getMySeals()
    console.log('印章响应:', sealsResponse)
    if (sealsResponse.success) {
      mySeals.value = sealsResponse.data
      console.log('印章已加载:', mySeals.value)
    }
  } catch (error) {
    console.error('加载签名印章失败:', error)
    ElMessage.warning('加载签名印章失败，请先前往"签名印章管理"上传')
  }
}

// PDF合成和上传
const mergePDFAndUpload = async (contractId, filePath, signature, seal, stepOrder) => {
  try {
    console.log('PDF合成参数:', {
      contract_id: contractId,
      file_path: filePath,
      signature: signature,
      seal: seal,
      step_order: stepOrder
    })
    
    // 下载原PDF
    const baseURL = import.meta.env.VITE_API_BASE_URL || ''
    const pdfUrl = `${baseURL}/storage/${filePath}`
    const pdfResponse = await fetch(pdfUrl)
    
    if (!pdfResponse.ok) {
      throw new Error(`下载PDF失败: ${pdfResponse.status}`)
    }
    
    const pdfBytes = await pdfResponse.arrayBuffer()
    const pdfDoc = await PDFDocument.load(pdfBytes)
    const pages = pdfDoc.getPages()
    const lastPage = pages[pages.length - 1]
    
    // 获取页面尺寸
    const { width, height } = lastPage.getSize()
    console.log('页面尺寸:', { width, height })
    
    // 签名位置（固定在第一页右下角）
    const pos = { x: width - 200, y: 100 }
    
    // 添加签名
    if (signature && signatureForm.use_signature) {
      let sigUrl = signature.image_url
      if (sigUrl.includes('localhost:8000')) {
        sigUrl = sigUrl.replace('http://localhost:8000', '')
      }
      console.log('📥 下载签名:', sigUrl)
      
      const sigResponse = await fetch(sigUrl, {
        credentials: 'include',
        mode: 'cors'
      })
      if (!sigResponse.ok) {
        throw new Error(`下载签名失败: ${sigResponse.status}`)
      }
      const sigBytes = await sigResponse.arrayBuffer()
      const sigImage = await pdfDoc.embedPng(sigBytes)
      lastPage.drawImage(sigImage, {
        x: pos.x,
        y: pos.y,
        width: 60,
        height: 60,
      })
    }
    
    // 添加印章（在签名右侧）
    if (seal && signatureForm.selected_seal_id) {
      let sealUrl = seal.image_url
      if (sealUrl.includes('localhost:8000')) {
        sealUrl = sealUrl.replace('http://localhost:8000', '')
      }
      console.log('📥 下载印章:', sealUrl)
      
      const sealResponse = await fetch(sealUrl, {
        credentials: 'include',
        mode: 'cors'
      })
      if (!sealResponse.ok) {
        throw new Error(`下载印章失败: ${sealResponse.status}`)
      }
      const sealBytes = await sealResponse.arrayBuffer()
      const sealImage = await pdfDoc.embedPng(sealBytes)
      lastPage.drawImage(sealImage, {
        x: pos.x + 130,
        y: pos.y,
        width: 60,
        height: 60,
      })
    }
    
    // 生成新的PDF
    const pdfBytesNew = await pdfDoc.save()
    const blob = new Blob([pdfBytesNew], { type: 'application/pdf' })
    
    // 上传已签名的PDF
    const formData = new FormData()
    formData.append('contract_id', contractId) // 添加合同ID
    formData.append('signed_pdf', blob, 'signed_contract.pdf')
    
    const response = await request.post('/employees/contracts/merge-signature', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    
    console.log('PDF合成响应:', response)
    return response.success
  } catch (error) {
    console.error('Merge PDF error:', error)
    console.error('Error response:', error.response?.data)
    return false
  }
}

// 盖章签字完成处理
const handleSignatureComplete = async (signatureData) => {
  try {
    // 1. 先合成PDF（盖章签字）
    if (signatureData.use_signature || signatureData.selected_seal_id) {
      ElMessage.info('正在合成PDF，请稍候...')
      
      const contractFile = currentContract.value.contract_file
      if (!contractFile) {
        ElMessage.error('合同文件路径不存在，无法进行签名合成')
        return
      }
      
      const mergeSuccess = await mergePDFAndUpload(
        currentContract.value.id,
        contractFile,
        signatureData.use_signature ? mySignature.value : null,
        signatureData.selected_seal_id ? mySeals.value.find(s => s.id === signatureData.selected_seal_id) : null,
        1 // 发起人签字
      )
      
      if (!mergeSuccess) {
        ElMessage.error('PDF合成失败')
        return
      }
    }
    
    // 2. 创建审批流程（跳过发起人审批）
    const response = await request({
      url: '/approvals',
      method: 'post',
      data: {
        business_type: 'employee_contract',  // 业务类型
        business_id: currentContract.value.id,  // 合同ID
        employee_id: currentEmployee.value.id,
        skip_initiator: true,  // 跳过发起人审批
        stamp_method: signatureData.stamp_method || 'online'  // 盖章方式
      }
    })
    
    if (response.success) {
      ElMessage.success('盖章签字完成，已直接提交二级审批')
      await loadEmployeeContracts(currentEmployee.value.id)
    }
  } catch (error) {
    console.error('Signature complete error:', error)
    ElMessage.error(error.response?.data?.message || '提交失败')
  } finally {
    showSignatureDialog.value = false
    currentContract.value = null
  }
}

// 关闭盖章签字对话框
const handleSignatureDialogClose = () => {
  signatureForm.use_signature = false
  signatureForm.selected_seal_id = null
  signatureForm.stamp_method = 'online'  // 重置为默认值
  signatureFormRef.value?.resetFields()
  showSignatureDialog.value = false
  currentContract.value = null
}

// 提交盖章签字
const handleSignatureSubmit = async () => {
  try {
    await handleSignatureComplete(signatureForm)
  } catch (error) {
    console.error('Signature submit error:', error)
    ElMessage.error('盖章签字失败')
  }
}

// 跳转到签名管理
const goToSignatureManagement = () => {
  router.push('/signature-management')
}

// 打开PDF编辑器
const openPDFEditor = async () => {
  if (!currentContract.value) {
    ElMessage.error('合同信息加载失败')
    return
  }
  
  if (!currentContract.value.contract_file) {
    ElMessage.error('未找到合同文件')
    return
  }
  
  // 生成PDF URL - 使用相对路径让Vite代理处理(和审批模块一样)
  const baseURL = import.meta.env.VITE_API_BASE_URL || ''
  currentPDFUrl.value = `${baseURL}/storage/${currentContract.value.contract_file}`
  
  console.log('=== 打开PDF编辑器 ===')
  console.log('合同文件路径:', currentContract.value.contract_file)
  console.log('baseURL:', baseURL)
  console.log('最终PDF URL:', currentPDFUrl.value)
  console.log('完整合同对象:', currentContract.value)
  
  showPDFEditor.value = true
}

// PDF编辑器确认
const handlePDFEditorConfirm = async (data) => {
  try {
    submitting.value = true
    
    // 1. 上传合成后的PDF
    const formData = new FormData()
    formData.append('contract_id', currentContract.value.id) // 添加合同ID
    formData.append('signed_pdf', data.pdfBlob, 'signed.pdf')
    
    const response = await request({
      url: `/employees/contracts/merge-signature`,
      method: 'post',
      data: formData,
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    
    if (!response.success) {
      throw new Error(response.message || 'PDF上传失败')
    }
    
    // 2. 更新合同文件路径
    if (response.data?.file_path) {
      // 删除原PDF文件
      if (currentContract.value.contract_file) {
        // 这里可以调用后端API删除原文件，或者让后端自动处理
      }
      
      // 更新合同文件路径
      currentContract.value.contract_file = response.data.file_path
    }
    
    // 3. 更新表单数据
    if (data.hasSignature) {
      signatureForm.use_signature = true
    }
    if (data.hasSeal && data.sealId) {
      signatureForm.selected_seal_id = data.sealId
    }
    
    // 4. 关闭编辑器
    showPDFEditor.value = false
    ElMessage.success('PDF签名盖章成功')
    
    // 5. 刷新合同列表数据
    await loadEmployeeContracts(currentEmployee.value.id)
    
    // 6. 自动提交审批（跳过发起人）
    await handleSignatureComplete(signatureForm)
    
  } catch (error) {
    console.error('PDF处理失败:', error)
    ElMessage.error('PDF处理失败: ' + (error.response?.data?.message || error.message))
  } finally {
    submitting.value = false
  }
}

// 格式化日期时间
const formatDateTime = (dateTime) => {
  if (!dateTime) return '-'
  const date = new Date(dateTime)
  return date.toLocaleString('zh-CN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false
  })
}

// 格式化保障内容
const formatCoverageContent = (coverage) => {
  if (!coverage) return '-'
  
  // 如果是布尔值
  if (typeof coverage === 'boolean') {
    return coverage ? '已保障' : '未保障'
  }
  
  // 如果是对象，尝试提取有意义的信息
  if (typeof coverage === 'object') {
    if (coverage.description) return coverage.description
    if (coverage.content) return coverage.content
    if (coverage.details) return coverage.details
    return '详见保单条款'
  }
  
  // 如果是字符串，直接返回
  if (typeof coverage === 'string') {
    return coverage
  }
  
  return String(coverage)
}

onMounted(() => {
  loadEmployees()
  loadProjects()
  checkExpiredIdCards()
  loadPendingContractUpload() // 加载待上传合同列表
})

// 监听账套切换，自动刷新数据
watch(() => accountSetStore.currentAccountSetId, (newAccountSetId, oldAccountSetId) => {
  console.log('账套变化检测:', { 
    new: newAccountSetId, 
    old: oldAccountSetId,
    type: typeof newAccountSetId 
  })
  if (newAccountSetId && oldAccountSetId && newAccountSetId !== oldAccountSetId) {
    console.log('✅ 账套切换，重新加载数据:', newAccountSetId)
    loadEmployees()
    loadProjects()
    checkExpiredIdCards()
    loadPendingContractUpload() // 加载待上传合同列表
  }
})

// 添加生成工号的方法
const generateEmployeeNumber = async () => {
  if (!currentAccountSetId.value) {
    ElMessage.warning('请先选择账套')
    // 即使没有账套ID，也尝试生成一个默认工号
    const defaultId = '1'
    const timestamp = new Date().getTime().toString().slice(-4)
    form.employee_number = `E${defaultId}-${timestamp}`
    return form.employee_number
  }
  
  try {
    // 前端自行生成工号，不依赖后端API
    console.log('开始生成工号，当前账套ID:', currentAccountSetId.value)
    console.log('当前员工列表:', employees.value.length, '名员工')
    
    // 1. 获取当前员工列表中的最大序号
    let maxNumber = 0
    
    // 遍历现有员工，查找最大工号
    if (employees.value && employees.value.length > 0) {
      employees.value.forEach(emp => {
        // 检查员工是否有工号
        console.log('检查员工工号:', emp.name, emp.employee_number)
        
        if (emp.employee_number && emp.employee_number !== '未设置') {
          // 尝试提取数字部分
          if (emp.employee_number.includes('-')) {
            const numStr = emp.employee_number.split('-')[1]
            if (numStr) {
              const num = parseInt(numStr, 10)
              if (!isNaN(num) && num > maxNumber) {
                maxNumber = num
                console.log('找到更大的工号序号:', num)
              }
            }
          }
        }
      })
    } else {
      console.log('没有现有员工，从001开始')
    }
    
    // 生成新的序号（当前最大序号+1）
    const nextNumber = maxNumber > 0 ? maxNumber + 1 : 1
    const employeeNumber = `E${currentAccountSetId.value}-${nextNumber.toString().padStart(3, '0')}`
    form.employee_number = employeeNumber
    console.log('最终生成的工号:', employeeNumber)
    return employeeNumber
  } catch (error) {
    console.error('生成工号错误:', error)
    // 如果出错，生成一个基于时间戳的工号
    const timestamp = new Date().getTime().toString().slice(-4)
    const employeeNumber = `E${currentAccountSetId.value}-${timestamp}`
    form.employee_number = employeeNumber
    console.log('出错后生成的备用工号:', employeeNumber)
    return employeeNumber
  }
}

// 在新增员工时自动生成工号
const handleNewEmployee = async () => {
  isEdit.value = false
  isViewMode.value = false
  // 重置表单
  Object.keys(form).forEach(key => {
    if (key === 'project_ids') {
      form[key] = []
    } else if (['birth_date', 'hire_date', 'contract_start_date', 'contract_end_date', 'social_insurance_enrollment_date', 'provident_fund_enrollment_date', 'medical_insurance_enrollment_date', 'large_medical_enrollment_date', 'id_card_valid_from', 'id_card_valid_until'].includes(key)) {
      form[key] = null
    } else if (['basic_salary', 'social_security_base', 'medical_insurance_base', 'housing_fund_base', 'large_medical_base', 'large_medical_company_base'].includes(key)) {
      form[key] = null
    } else if (key === 'salary_items') {
      form[key] = []
    } else {
      form[key] = ''
    }
  })
  
  // 工号字段清空，由后端根据项目自动生成（如：AA001, AB001）
  form.employee_number = ''
  
  // 重置选择的数据
  selectedSocialSecurityRegion.value = null
  selectedMedicalInsuranceRegion.value = null
  selectedHousingFundRegion.value = null
  selectedHousingFundConfig.value = null
  availableHousingFundConfigs.value = []
  projectOtherInsurancePolicies.value = []
  selectedLargeMedicalInsuranceConfig.value = null
  availableLargeMedicalInsuranceConfigs.value = []
  
  formRef.value?.resetFields()
  pendingSalaryAdjustment.value = null
  currentSalarySnapshot.value = {
    basic_salary: null,
    salary_items: []
  }
  showCreateDialog.value = true
}

// 修改新增员工按钮点击事件
const handleAddEmployee = async () => {
  await handleNewEmployee()
}

// 打印单个员工登记表（智能选择入职登记表或从业人员登记表，带签名合成）
const handlePrintEmployee = async (row) => {
  const loading = ElLoading.service({
    lock: true,
    text: '正在生成打印文件...',
    background: 'rgba(0, 0, 0, 0.7)'
  })
  
  try {
    // 获取员工的PDF数据（智能选择登记表类型）
    const response = await fetch('/api/get-batch-pdfs-for-merge', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        employee_ids: [row.id]
      })
    })
    
    const result = await response.json()
    
    if (!result.success || !result.data || result.data.length === 0) {
      throw new Error(result.message || '生成PDF失败')
    }
    
    const item = result.data[0]
    
    if (item.error) {
      throw new Error(item.error)
    }
    
    // 解码PDF
    const pdfBytes = Uint8Array.from(
      atob(item.pdf_base64),
      c => c.charCodeAt(0)
    )
    const pdfDoc = await PDFDocument.load(pdfBytes)
    
    // 获取第一页（用于贴寸照）和最后一页（用于贴签名）
    const pages = pdfDoc.getPages()
    const firstPage = pages[0]
    const lastPage = pages[pages.length - 1]
    
    // 如果有寸照，嵌入到第一页右上角
    if (item.photo_base64) {
      try {
        const photoBytes = Uint8Array.from(
          atob(item.photo_base64),
          c => c.charCodeAt(0)
        )
        // 尝试作为 JPEG 嵌入，如果失败则尝试 PNG
        let photoImage
        try {
          photoImage = await pdfDoc.embedJpg(photoBytes)
        } catch {
          photoImage = await pdfDoc.embedPng(photoBytes)
        }
        
        const photoPosition = item.photo_position || {
          x: 500,
          y: 680,
          width: 75,
          height: 100
        }
        
        firstPage.drawImage(photoImage, {
          x: photoPosition.x,
          y: photoPosition.y,
          width: photoPosition.width,
          height: photoPosition.height,
          opacity: 1
        })
      } catch (photoError) {
        console.warn('嵌入寸照失败:', photoError)
      }
    }
    
    // 如果有签名，嵌入签名
    if (item.signature_base64) {
      const signatureBytes = Uint8Array.from(
        atob(item.signature_base64),
        c => c.charCodeAt(0)
      )
      const signatureImage = await pdfDoc.embedPng(signatureBytes)
      
      // 使用后端返回的签名位置
      const position = item.signature_position || {
        x: 115,
        y: 142,
        width: 50,
        height: 20,
        from_bottom: true
      }
      
      // 计算实际Y坐标
      const sigX = position.x
      const sigY = position.from_bottom ? position.y : (lastPage.getSize().height - position.y)
      const sigWidth = position.width
      const sigHeight = position.height
      
      lastPage.drawImage(signatureImage, {
        x: sigX,
        y: sigY,
        width: sigWidth,
        height: sigHeight,
        opacity: 1
      })
    }
    
    // 保存PDF
    const modifiedPdfBytes = await pdfDoc.save()
    const blob = new Blob([modifiedPdfBytes], { type: 'application/pdf' })
    const url = URL.createObjectURL(blob)
    
    // 打开打印对话框
    const printWindow = window.open(url)
    if (printWindow) {
      printWindow.onload = () => {
        printWindow.print()
      }
    } else {
      ElMessage.error('无法打开打印窗口，请检查浏览器设置')
    }
    
    // 清理URL
    setTimeout(() => {
      URL.revokeObjectURL(url)
    }, 1000)
    
  } catch (error) {
    console.error('打印员工登记表失败:', error)
    ElMessage.error('打印失败: ' + error.message)
  } finally {
    loading.close()
  }
}

// 查看员工变更历史
const handleViewChangeHistory = async (row) => {
  currentChangeHistoryEmployee.value = row
  changeHistoryPage.value = 1
  changeHistoryDialogVisible.value = true
  await loadChangeHistory()
}

// 加载变更历史
const loadChangeHistory = async () => {
  if (!currentChangeHistoryEmployee.value) return
  
  changeHistoryLoading.value = true
  try {
    const response = await request.get(`/employees/${currentChangeHistoryEmployee.value.id}/change-history`, {
      params: {
        current_account_set_id: currentAccountSetId.value,
        page: changeHistoryPage.value,
        per_page: changeHistoryPageSize.value
      }
    })
    
    if (response && response.success) {
      changeHistoryList.value = response.data || []
      changeHistoryTotal.value = response.total || 0
    } else {
      ElMessage.error(response?.message || '获取变更历史失败')
    }
  } catch (error) {
    console.error('获取变更历史失败:', error)
    ElMessage.error('获取变更历史失败: ' + (error.response?.data?.message || error.message))
  } finally {
    changeHistoryLoading.value = false
  }
}

// 变更历史分页
const handleChangeHistoryPageChange = (page) => {
  changeHistoryPage.value = page
  loadChangeHistory()
}

// 查看变更详情
const showChangeDetail = (row) => {
  currentChangeDetail.value = row
  changeDetailDialogVisible.value = true
}

// 获取变更对比数据
const getChangeComparison = (detail) => {
  if (!detail.old_values || !detail.new_values) return []
  
  const oldValues = detail.old_values
  const newValues = detail.new_values
  const comparison = []
  
  // 遍历新值，与旧值对比
  for (const [field, newValue] of Object.entries(newValues)) {
    const oldValue = oldValues[field]
    comparison.push({
      field: field,
      oldValue: oldValue !== undefined && oldValue !== null ? String(oldValue) : '-',
      newValue: newValue !== undefined && newValue !== null ? String(newValue) : '-'
    })
  }
  
  return comparison
}
</script>

<style scoped>
.employees-page {
  padding: 0;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.page-header h1 {
  font-size: 24px;
  color: #303133;
  margin: 0;
}

/* 统计卡片样式 */
.stats-section .stat-card {
  cursor: pointer;
  transition: all 0.3s;
}

.stats-section .stat-card:hover {
  transform: translateY(-2px);
}

.stats-section .stat-item {
  text-align: center;
  padding: 10px 0;
}

.stats-section .stat-value {
  font-size: 28px;
  font-weight: bold;
  margin-bottom: 8px;
}

.stats-section .stat-label {
  font-size: 14px;
  color: #909399;
}

.search-section {
  margin-bottom: 20px;
}

.table-section {
  margin-bottom: 20px;
}

.pagination {
  margin-top: 20px;
  text-align: right;
}

.project-tag {
  margin-right: 5px;
  margin-bottom: 5px;
}

:deep(.el-table) {
  font-size: 14px;
}

:deep(.el-form-item) {
  margin-bottom: 20px;
}

.contract-management {
  min-height: 400px;
}

.contract-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.contract-header h3 {
  margin: 0;
  font-size: 18px;
  color: #303133;
}

/* 参保配置详情样式 */
.insurance-details {
  margin: 25px 0;
  padding: 20px;
  background-color: #f8f9fa;
  border-radius: 8px;
  border: 1px solid #e9ecef;
}

.insurance-details h4 {
  margin: 0 0 15px 0;
  font-size: 16px;
  color: #495057;
  font-weight: 500;
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 6px;
  line-height: 1.4;
}

/* 表单间距优化 */
.el-form-item {
  margin-bottom: 20px;
}

.el-form-item__label {
  font-weight: 500;
}

/* 签名印章预览样式 */
.signature-preview {
  cursor: pointer;
  padding: 12px;
  border: 2px dashed #dcdfe6;
  border-radius: 6px;
  transition: all 0.3s;
}

.signature-preview:hover {
  border-color: #409eff;
  background: #ecf5ff;
}

.preview-image {
  width: 100px;
  height: 50px;
  object-fit: contain;
  margin-top: 8px;
  border: 1px solid #dcdfe6;
  border-radius: 4px;
  padding: 8px;
  background: #fff;
  border-radius: 4px;
}

.seal-mini-preview {
  width: 24px;
  height: 24px;
  object-fit: contain;
  vertical-align: middle;
  margin-left: 6px;
}

/* 工作经历和学习简历卡片样式 */
.experience-card {
  margin-bottom: 20px;
}

.experience-card:last-child {
  margin-bottom: 0;
}

.experience-card .el-card {
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  transition: all 0.3s;
}

.experience-card .el-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  border-color: #409eff;
}

.experience-card .card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.experience-card .time-range {
  color: #909399;
  font-size: 13px;
  font-weight: normal;
}

.experience-card .info-item {
  display: flex;
  align-items: flex-start;
  margin-bottom: 8px;
  line-height: 1.6;
}

.experience-card .info-item:last-child {
  margin-bottom: 0;
}

.experience-card .info-label {
  color: #606266;
  font-weight: 500;
  min-width: 100px;
  flex-shrink: 0;
}

.experience-card .info-value {
  color: #303133;
  flex: 1;
}

.experience-card .info-content {
  color: #303133;
  line-height: 1.8;
  white-space: pre-wrap;
  word-break: break-word;
  margin-top: 4px;
}

/* 家庭成员卡片样式 */
.family-card {
  height: 100%;
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  transition: all 0.3s;
}

.family-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  border-color: #67c23a;
  transform: translateY(-2px);
}

.family-card .card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.family-info {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.family-info .info-item {
  display: flex;
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid #f5f7fa;
}

.family-info .info-item:last-child {
  border-bottom: none;
}

.family-info .info-label {
  color: #606266;
  font-weight: 500;
  min-width: 80px;
  flex-shrink: 0;
  font-size: 14px;
}

.family-info .info-value {
  color: #303133;
  flex: 1;
  font-size: 14px;
}

/* 工资卡标签页样式 */
.salary-card-form {
  padding: 20px 0;
}

.salary-card-form .el-divider {
  margin: 20px 0;
}

.salary-card-form .form-tip {
  margin-top: 5px;
  font-size: 12px;
  color: #909399;
  line-height: 1.4;
}

.salary-card-form .el-input {
  width: 100%;
}

.salary-card-form .el-form-item {
  margin-bottom: 20px;
}
</style>
