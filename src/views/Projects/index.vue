<template>
  <div class="projects-page">
    <!-- 未分配账套提示 -->
    <NoAccountSetTip v-if="!isAdmin && !currentAccountSetId" />
    
    <!-- 正常内容 -->
    <div v-else>
    <div class="page-header">
      <h1>项目管理</h1>
      <el-button v-if="canCreateProject" type="primary" @click="handleCreate">
        <el-icon><Plus /></el-icon>
        新增项目
      </el-button>
    </div>
    
    <!-- 搜索和筛选 -->
    <div class="search-section">
      <el-card>
        <el-form :model="searchForm" inline>
          <el-form-item label="项目名称">
            <el-input
              v-model="searchForm.search"
              placeholder="请输入项目名称或代码"
              clearable
              @keyup.enter="handleSearch"
            />
          </el-form-item>
          
          <el-form-item label="状态">
            <el-select
              v-model="searchForm.status"
              placeholder="请选择状态"
              clearable
              style="width: 200px"
            >
              <el-option label="进行中" value="active" />
              <el-option label="已完成" value="completed" />
              <el-option label="已停用" value="inactive" />
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
          </el-form-item>
        </el-form>
      </el-card>
    </div>
    
    <!-- 项目列表 -->
    <div class="table-section">
      <el-card>
        <el-table
          :data="projects"
          v-loading="loading"
          stripe
          border
        >
          <el-table-column prop="code" label="项目编号" width="100" />
          <el-table-column prop="name" label="项目名称" width="200" />
          <el-table-column prop="status" label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="getStatusType(row.status)">
                {{ getStatusText(row.status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="salary_payment_date" label="工资发放日期" width="120">
            <template #default="{ row }">
              {{ row.salary_payment_date ? `每月${row.salary_payment_date}号` : '-' }}
            </template>
          </el-table-column>
          <el-table-column prop="delivery_frequency" label="交付频率" width="100">
            <template #default="{ row }">
              {{ getDeliveryFrequencyText(row.delivery_frequency) }}
            </template>
          </el-table-column>
          <el-table-column prop="delivery_method" label="交付方式" width="100">
            <template #default="{ row }">
              {{ getDeliveryMethodText(row.delivery_method) }}
            </template>
          </el-table-column>
          <el-table-column prop="requires_salary_basis" label="上传工资依据" width="120">
            <template #default="{ row }">
              <el-tag :type="row.requires_salary_basis ? 'success' : 'info'">
                {{ row.requires_salary_basis ? '是' : '否' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="requires_attendance_basis" label="上传考勤依据" width="120">
            <template #default="{ row }">
              <el-tag :type="row.requires_attendance_basis ? 'success' : 'info'">
                {{ row.requires_attendance_basis ? '是' : '否' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="employees_count" label="员工数量" width="100" />
          <el-table-column prop="created_at" label="创建时间" width="160">
            <template #default="{ row }">
              {{ formatDateTime(row.created_at) }}
            </template>
          </el-table-column>
          <el-table-column label="操作" width="350" fixed="right">
            <template #default="{ row }">
              <el-button type="primary" size="small" @click="handleView(row)">
                查看
              </el-button>
              <el-button type="success" size="small" @click="handleNoticeSettings(row)">
                须知设置
              </el-button>
              <el-button type="info" size="small" @click="handleDocumentSettings(row)">
                资料配置
              </el-button>
              <el-button type="primary" size="small" @click="handlePlaceholderFieldsSettings(row)">
                占位符字段
              </el-button>
              <el-button type="warning" size="small" @click="handleEdit(row)">
                编辑
              </el-button>
              <el-button type="danger" size="small" @click="handleDelete(row)">
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
      :title="isEdit ? '编辑项目' : (form.id ? '查看项目' : '新增项目')"
      width="800px"
      @close="handleDialogClose"
    >
      <el-form
        ref="formRef"
        :model="form"
        :rules="formRules"
        label-width="120px"
      >
        <el-row :gutter="20">
                    <el-col :span="12">
            <el-form-item label="&#39033;&#30446;&#21517;&#31216;" prop="name">
              <el-input v-model="form.name" placeholder="&#35831;&#36755;&#20837;&#39033;&#30446;&#21517;&#31216;" :readonly="form.id && !isEdit" @blur="handleProjectNameBlur" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="&#39033;&#30446;&#32534;&#21495;" prop="code">
              <el-input v-model="form.code" placeholder="&#30041;&#31354;&#21017;&#33258;&#21160;&#29983;&#25104;" :readonly="form.id && !isEdit" @input="handleProjectCodeInput" @blur="handleProjectCodeBlur">
                <template #prepend>
                  <el-icon><Tickets /></el-icon>
                </template>
              </el-input>
              <div style="font-size: 12px; color: #909399; margin-top: 4px;">
                &#40664;&#35748;&#25353;&#39033;&#30446;&#21517;&#31216;&#25340;&#38899;&#39318;&#23383;&#27597;&#29983;&#25104;&#65292;&#21487;&#25163;&#21160;&#20462;&#25913;&#65307;&#21516;&#36134;&#22871;&#19979;&#32534;&#21495;&#19981;&#33021;&#37325;&#22797;
              </div>
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-form-item label="项目描述" prop="description">
          <el-input
            v-model="form.description"
            type="textarea"
            :rows="3"
            placeholder="请输入项目描述"
            :readonly="form.id && !isEdit"
          />
        </el-form-item>
        
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="项目状态" prop="status">
              <el-select
                v-model="form.status"
                placeholder="请选择项目状态"
                style="width: 100%"
                :disabled="form.id && !isEdit"
                @change="handleStatusChange"
              >
                <el-option label="进行中" value="active" />
                <el-option label="已完成" value="completed" />
                <el-option label="已停用" value="inactive" />
              </el-select>
              <div class="form-tip">选择"已停用"会自动关闭工资依据、考勤依据和考勤表开关</div>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="开始时间" prop="start_date">
              <el-date-picker
                v-model="form.start_date"
                type="date"
                placeholder="请选择开始时间"
                style="width: 100%"
                :disabled="form.id && !isEdit"
                value-format="YYYY-MM-DD"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="结束时间" prop="end_date">
              <el-date-picker
                v-model="form.end_date"
                type="date"
                placeholder="请选择结束时间"
                style="width: 100%"
                :disabled="form.id && !isEdit"
                value-format="YYYY-MM-DD"
              />
            </el-form-item>
          </el-col>
        </el-row>
        
        <!-- 社保地区选择 -->
        <el-form-item label="社保地区" prop="social_security_regions">
          <el-select
            v-model="form.social_security_regions"
            multiple
            placeholder="请选择社保地区"
            style="width: 100%"
            :disabled="form.id && !isEdit"
            @change="handleSocialSecurityRegionsChange"
          >
            <el-option
              v-for="region in availableSocialSecurityRegions"
              :key="region.id"
              :label="region.name"
              :value="region.id"
            />
          </el-select>
          <div class="form-tip">选择项目支持的社保地区，员工只能从这些地区中选择参保</div>
        </el-form-item>

        <!-- 公积金地区选择 -->
        <el-form-item label="公积金地区" prop="housing_fund_regions">
          <el-select
            v-model="form.housing_fund_regions"
            multiple
            placeholder="请选择公积金地区"
            style="width: 100%"
            :disabled="form.id && !isEdit"
            @change="handleHousingFundRegionsChange"
          >
            <el-option
              v-for="region in availableHousingFundRegions"
              :key="region.id"
              :label="region.region_name"
              :value="region.id"
            />
          </el-select>
          <div class="form-tip">选择项目支持的公积金地区，员工只能从这些地区中选择参保</div>
        </el-form-item>

        <!-- 医保参保地区选择 -->
        <el-form-item label="医保参保地区" prop="medical_insurance_regions">
          <el-select
            v-model="form.medical_insurance_regions"
            multiple
            placeholder="请选择医保参保地区"
            style="width: 100%"
            :disabled="form.id && !isEdit"
            @change="handleMedicalInsuranceRegionsChange"
          >
            <el-option
              v-for="region in availableMedicalInsuranceRegions"
              :key="region.id"
              :label="region.name"
              :value="region.id"
            />
          </el-select>
          <div class="form-tip">选择项目支持的医保参保地区，员工只能从这些地区中选择参保</div>
        </el-form-item>

        <!-- 绑定其他保险保单 -->
        <el-form-item label="绑定其他保险保单" prop="other_insurance_policies">
          <div style="display: flex; align-items: center; gap: 10px;">
            <el-button 
              @click="openPolicySelectionDialog" 
              :disabled="form.id && !isEdit"
            >
              选择保单 (已选 {{ form.other_insurance_policies.length }} 个)
            </el-button>
            <div v-if="selectedPoliciesSummary.length > 0" style="color: #606266; font-size: 14px;">
              {{ selectedPoliciesSummary.join('、') }}
            </div>
          </div>
          <div class="form-tip">点击按钮选择项目绑定的其他保险保单，每种保险类型只能绑定一个保单，员工将自动享受这些保险</div>
        </el-form-item>

        <!-- 绑定大额医疗保险 -->
        <el-form-item label="大额医疗保险" prop="large_medical_insurance_configs">
          <el-select
            v-model="form.large_medical_insurance_configs"
            multiple
            collapse-tags
            collapse-tags-tooltip
            placeholder="请选择大额医疗保险配置"
            clearable
            style="width: 100%"
            :disabled="form.id && !isEdit"
          >
            <el-option
              v-for="config in availableLargeMedicalInsuranceConfigs"
              :key="config.id"
              :label="`${config.region_name} (${config.calculation_type === 'base' ? '按基数' : '按固定金额'})`"
              :value="config.id"
            />
          </el-select>
          <div class="form-tip">选择项目绑定的大额医疗保险配置，员工可在增减模块中选择是否启用</div>
        </el-form-item>
        
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="工资发放日期" prop="salary_payment_date">
              <el-select
                v-model="form.salary_payment_date"
                placeholder="请选择每月发放日期"
                style="width: 100%"
                :disabled="form.id && !isEdit"
                clearable
              >
                <el-option
                  v-for="day in 31"
                  :key="day"
                  :label="`每月${day}号`"
                  :value="day"
                />
              </el-select>
              <div class="form-tip">选择每月的发放日期（1-31号），设置后每月沿用</div>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="需要考勤表" prop="requires_attendance">
              <el-switch v-model="form.requires_attendance" :disabled="form.id && !isEdit" />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="工资发放" prop="salary_payment_month">
              <el-select v-model="form.salary_payment_month" placeholder="请选择工资发放月份" :disabled="form.id && !isEdit">
                <el-option label="本月" value="current" />
                <el-option label="次月" value="next" />
              </el-select>
              <div class="form-tip">选择工资在本月发放还是次月发放</div>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="保险导入" prop="insurance_import_month">
              <el-select v-model="form.insurance_import_month" placeholder="请选择保险导入设置" :disabled="form.id && !isEdit">
                <el-option label="当月" value="current" />
                <el-option label="次月" value="next" />
                <el-option label="不导入" value="none" />
              </el-select>
              <div class="form-tip">选择保险数据的导入方式</div>
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="交付频率" prop="delivery_frequency">
              <el-select v-model="form.delivery_frequency" placeholder="请选择交付频率" :disabled="form.id && !isEdit">
                <el-option label="月度" value="monthly" />
                <el-option label="季度" value="quarterly" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="交付方式" prop="delivery_method">
              <el-select v-model="form.delivery_method" placeholder="请选择交付方式" :disabled="form.id && !isEdit">
                <el-option label="快递" value="express" />
                <el-option label="电子" value="electronic" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="是否需要上传工资依据" prop="requires_salary_basis">
              <el-switch v-model="form.requires_salary_basis" :disabled="form.id && !isEdit" />
        </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="是否需要上传考勤依据" prop="requires_attendance_basis">
              <el-switch v-model="form.requires_attendance_basis" :disabled="form.id && !isEdit" />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="员工登记表类型" prop="registration_form_type">
              <el-select v-model="form.registration_form_type" placeholder="请选择登记表类型" :disabled="form.id && !isEdit">
                <el-option label="入职登记表" value="onboarding" />
                <el-option label="从业人员登记表" value="registration" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        
        <!-- 合同模板设置 -->
        <el-divider content-position="left">合同模板设置</el-divider>
        
        <!-- 劳动合同模板 -->
        <el-form-item label="劳动合同模板">
          <div class="template-manager">
            <div class="template-header">
              <span class="template-title">劳动合同模板</span>
              <el-button 
                type="primary" 
                size="small" 
                @click="openTemplateUploadDialog('labor')"
                :disabled="!form.id || (form.id && !isEdit)"
              >
                上传模板
              </el-button>
            </div>
            <div class="template-list">
              <div v-if="contractTemplates.labor && contractTemplates.labor.length > 0" class="template-items">
                <div 
                  v-for="template in contractTemplates.labor" 
                  :key="template.id"
                  class="template-item"
                  :class="{ 'is-default': template.is_default }"
                >
                  <div class="template-info">
                    <span class="template-name">{{ template.shared_file?.name || '文件已删除' }}</span>
                    <el-tag v-if="template.is_default" type="success" size="small">默认</el-tag>
                  </div>
                  <div class="template-actions">
                    <el-button 
                      type="text" 
                      size="small"
                      @click="openPlaceholderSetup(template, 'labor')"
                      :disabled="form.id && !isEdit"
                    >
                      设置占位符
                    </el-button>
                    <el-button 
                      v-if="!template.is_default"
                      type="text" 
                      size="small"
                      @click="setDefaultTemplateAction(template.id)"
                      :disabled="form.id && !isEdit"
                    >
                      设为默认
                    </el-button>
                    <el-button 
                      type="text" 
                      size="small"
                      @click="deleteTemplate(template.id)"
                      :disabled="form.id && !isEdit"
                      style="color: #f56c6c"
                    >
                      删除
                    </el-button>
                  </div>
                </div>
              </div>
              <div v-else class="template-empty">
                <el-empty description="暂无劳动合同模板" :image-size="60">
                  <el-button 
                    type="primary" 
                    size="small"
                    @click="openTemplateUploadDialog('labor')"
                    :disabled="!form.id || (form.id && !isEdit)"
                  >
                    上传模板
                  </el-button>
                </el-empty>
              </div>
            </div>
          </div>
        </el-form-item>
        
        <!-- 解除协议模板 -->
        <el-form-item label="解除协议模板">
          <div class="template-manager">
            <div class="template-header">
              <span class="template-title">解除协议模板</span>
              <el-button 
                type="primary" 
                size="small" 
                @click="openTemplateUploadDialog('termination')"
                :disabled="!form.id || (form.id && !isEdit)"
              >
                上传模板
              </el-button>
            </div>
            <div class="template-list">
              <div v-if="contractTemplates.termination && contractTemplates.termination.length > 0" class="template-items">
                <div 
                  v-for="template in contractTemplates.termination" 
                  :key="template.id"
                  class="template-item"
                  :class="{ 'is-default': template.is_default }"
                >
                  <div class="template-info">
                    <span class="template-name">{{ template.shared_file?.name || '文件已删除' }}</span>
                    <el-tag v-if="template.is_default" type="success" size="small">默认</el-tag>
                  </div>
                  <div class="template-actions">
                    <el-button 
                      type="text" 
                      size="small"
                      @click="openPlaceholderSetup(template, 'termination')"
                      :disabled="form.id && !isEdit"
                    >
                      设置占位符
                    </el-button>
                    <el-button 
                      v-if="!template.is_default"
                      type="text" 
                      size="small"
                      @click="setDefaultTemplateAction(template.id)"
                      :disabled="form.id && !isEdit"
                    >
                      设为默认
                    </el-button>
                    <el-button 
                      type="text" 
                      size="small"
                      @click="deleteTemplate(template.id)"
                      :disabled="form.id && !isEdit"
                      style="color: #f56c6c"
                    >
                      删除
                    </el-button>
                  </div>
                </div>
              </div>
              <div v-else class="template-empty">
                <el-empty description="暂无解除协议模板" :image-size="60">
                  <el-button 
                    type="primary" 
                    size="small"
                    @click="openTemplateUploadDialog('termination')"
                    :disabled="!form.id || (form.id && !isEdit)"
                  >
                    上传模板
                  </el-button>
                </el-empty>
              </div>
            </div>
          </div>
        </el-form-item>
        
        <!-- 退休解除协议模板 -->
        <el-form-item label="退休解除协议模板">
          <div class="template-manager">
            <div class="template-header">
              <span class="template-title">退休解除协议模板</span>
              <el-button 
                type="primary" 
                size="small" 
                @click="openTemplateUploadDialog('retirement')"
                :disabled="!form.id || (form.id && !isEdit)"
              >
                上传模板
              </el-button>
            </div>
            <div class="template-list">
              <div v-if="contractTemplates.retirement && contractTemplates.retirement.length > 0" class="template-items">
                <div 
                  v-for="template in contractTemplates.retirement" 
                  :key="template.id"
                  class="template-item"
                  :class="{ 'is-default': template.is_default }"
                >
                  <div class="template-info">
                    <span class="template-name">{{ template.shared_file?.name || '文件已删除' }}</span>
                    <el-tag v-if="template.is_default" type="success" size="small">默认</el-tag>
                  </div>
                  <div class="template-actions">
                    <el-button 
                      type="text" 
                      size="small"
                      @click="openPlaceholderSetup(template, 'retirement')"
                      :disabled="form.id && !isEdit"
                    >
                      设置占位符
                    </el-button>
                    <el-button 
                      v-if="!template.is_default"
                      type="text" 
                      size="small"
                      @click="setDefaultTemplateAction(template.id)"
                      :disabled="form.id && !isEdit"
                    >
                      设为默认
                    </el-button>
                    <el-button 
                      type="text" 
                      size="small"
                      @click="deleteTemplate(template.id)"
                      :disabled="form.id && !isEdit"
                      style="color: #f56c6c"
                    >
                      删除
                    </el-button>
                  </div>
                </div>
              </div>
              <div v-else class="template-empty">
                <el-empty description="暂无退休解除协议模板" :image-size="60">
                  <el-button 
                    type="primary" 
                    size="small"
                    @click="openTemplateUploadDialog('retirement')"
                    :disabled="!form.id || (form.id && !isEdit)"
                  >
                    上传模板
                  </el-button>
                </el-empty>
              </div>
            </div>
          </div>
        </el-form-item>
        
        <!-- 保密协议模板 -->
        <el-form-item label="保密协议模板">
          <div class="template-manager">
            <div class="template-header">
              <span class="template-title">保密协议模板</span>
              <el-button 
                type="primary" 
                size="small" 
                @click="openTemplateUploadDialog('confidentiality')"
                :disabled="!form.id || (form.id && !isEdit)"
              >
                上传模板
              </el-button>
            </div>
            <div class="template-list">
              <div v-if="contractTemplates.confidentiality && contractTemplates.confidentiality.length > 0" class="template-items">
                <div 
                  v-for="template in contractTemplates.confidentiality" 
                  :key="template.id"
                  class="template-item"
                  :class="{ 'is-default': template.is_default }"
                >
                  <div class="template-info">
                    <span class="template-name">{{ template.shared_file?.name || '文件已删除' }}</span>
                    <el-tag v-if="template.is_default" type="success" size="small">默认</el-tag>
                  </div>
                  <div class="template-actions">
                    <el-button 
                      type="text" 
                      size="small"
                      @click="openPlaceholderSetup(template, 'confidentiality')"
                      :disabled="form.id && !isEdit"
                    >
                      设置占位符
                    </el-button>
                    <el-button 
                      v-if="!template.is_default"
                      type="text" 
                      size="small"
                      @click="setDefaultTemplateAction(template.id)"
                      :disabled="form.id && !isEdit"
                    >
                      设为默认
                    </el-button>
                    <el-button 
                      type="text" 
                      size="small"
                      @click="deleteTemplate(template.id)"
                      :disabled="form.id && !isEdit"
                      style="color: #f56c6c"
                    >
                      删除
                    </el-button>
                  </div>
                </div>
              </div>
              <div v-else class="template-empty">
                <el-empty description="暂无保密协议模板" :image-size="60">
                  <el-button 
                    type="primary" 
                    size="small"
                    @click="openTemplateUploadDialog('confidentiality')"
                    :disabled="!form.id || (form.id && !isEdit)"
                  >
                    上传模板
                  </el-button>
                </el-empty>
              </div>
            </div>
          </div>
        </el-form-item>
        
        <!-- 其他合同模板 -->
        <el-form-item label="其他合同模板">
          <div class="template-manager">
            <div class="template-header">
              <span class="template-title">其他合同模板</span>
              <el-button 
                type="primary" 
                size="small" 
                @click="openTemplateUploadDialog('other')"
                :disabled="!form.id || (form.id && !isEdit)"
              >
                上传模板
              </el-button>
            </div>
            <div class="template-list">
              <div v-if="contractTemplates.other && contractTemplates.other.length > 0" class="template-items">
                <div 
                  v-for="template in contractTemplates.other" 
                  :key="template.id"
                  class="template-item"
                  :class="{ 'is-default': template.is_default }"
                >
                  <div class="template-info">
                    <span class="template-name">{{ template.shared_file?.name || '文件已删除' }}</span>
                    <el-tag v-if="template.is_default" type="success" size="small">默认</el-tag>
                  </div>
                  <div class="template-actions">
                    <el-button 
                      type="text" 
                      size="small"
                      @click="openPlaceholderSetup(template, 'other')"
                      :disabled="form.id && !isEdit"
                    >
                      设置占位符
                    </el-button>
                    <el-button 
                      v-if="!template.is_default"
                      type="text" 
                      size="small"
                      @click="setDefaultTemplateAction(template.id)"
                      :disabled="form.id && !isEdit"
                    >
                      设为默认
                    </el-button>
                    <el-button 
                      type="text" 
                      size="small"
                      @click="deleteTemplate(template.id)"
                      :disabled="form.id && !isEdit"
                      style="color: #f56c6c"
                    >
                      删除
                    </el-button>
                  </div>
                </div>
              </div>
              <div v-else class="template-empty">
                <el-empty description="暂无其他合同模板" :image-size="60">
                  <el-button 
                    type="primary" 
                    size="small"
                    @click="openTemplateUploadDialog('other')"
                    :disabled="!form.id || (form.id && !isEdit)"
                  >
                    上传模板
                  </el-button>
                </el-empty>
              </div>
            </div>
          </div>
        </el-form-item>
        
        <!-- 资料配置管理（仅编辑模式） -->
        <div v-if="form.id" class="section-divider">
          <el-divider content-position="left">员工资料配置</el-divider>
        </div>
        
        <el-form-item v-if="form.id" label="需要上传的资料">
          <div class="document-config-inline">
            <el-alert type="info" :closable="false" show-icon style="margin-bottom: 15px;">
              <template #title>
                配置员工需要在小程序中上传的资料类型，员工将在小程序中看到这些资料并进行上传
              </template>
            </el-alert>
            
            <div class="document-toolbar">
              <el-button 
                type="primary" 
                size="small" 
                @click="handleAddDocument"
                :disabled="!isEdit"
              >
                <el-icon><Plus /></el-icon>
                添加资料项
              </el-button>
            </div>
            
            <el-table
              :data="documentConfigs"
              v-loading="loadingDocumentConfigs"
              border
              size="small"
              max-height="400"
            >
              <el-table-column prop="sort_order" label="排序" width="60" align="center" />
              <el-table-column prop="document_name" label="资料名称" min-width="150" />
              <el-table-column label="文件类型" width="100" align="center">
                <template #default="{ row }">
                  <el-tag :type="getDocumentTypeTagType(row.document_type)" size="small">
                    {{ getDocumentTypeText(row.document_type) }}
                  </el-tag>
                </template>
              </el-table-column>
              <el-table-column label="必填" width="70" align="center">
                <template #default="{ row }">
                  <el-tag :type="row.is_required ? 'danger' : 'info'" size="small">
                    {{ row.is_required ? '是' : '否' }}
                  </el-tag>
                </template>
              </el-table-column>
              <el-table-column label="操作" width="180" align="center">
                <template #default="{ row, $index }">
                  <el-button type="text" size="small" @click="handleEditDocument(row)" :disabled="!isEdit">
                    编辑
                  </el-button>
                  <el-button type="text" size="small" @click="handleMoveDocUp($index)" :disabled="!isEdit || $index === 0">
                    上移
                  </el-button>
                  <el-button type="text" size="small" @click="handleMoveDocDown($index)" :disabled="!isEdit || $index === documentConfigs.length - 1">
                    下移
                  </el-button>
                  <el-button type="text" size="small" @click="handleDeleteDocument(row)" :disabled="!isEdit" style="color: #f56c6c;">
                    删除
                  </el-button>
                </template>
              </el-table-column>
            </el-table>
            
            <el-empty v-if="!loadingDocumentConfigs && documentConfigs.length === 0" description="暂无资料配置" :image-size="60">
              <el-button 
                v-if="isEdit"
                type="primary" 
                size="small"
                @click="handleAddDocument"
              >
                添加资料项
              </el-button>
            </el-empty>
          </div>
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button v-if="!form.id || isEdit" type="primary" @click="handleSubmit" :loading="submitting">
          {{ isEdit ? '更新' : '创建' }}
        </el-button>
      </template>
    </el-dialog>
    
    <!-- 资料配置添加/编辑对话框 -->
    <el-dialog
      v-model="showDocumentFormDialog"
      :title="documentFormMode === 'add' ? '添加资料项' : '编辑资料项'"
      width="500px"
      :close-on-click-modal="false"
    >
      <el-form
        ref="documentFormRef"
        :model="documentForm"
        :rules="documentFormRules"
        label-width="100px"
      >
        <el-form-item label="资料名称" prop="document_name">
          <el-input
            v-model="documentForm.document_name"
            placeholder="例如：身份证照片、驾驶证等"
            maxlength="100"
            show-word-limit
          />
        </el-form-item>

        <el-form-item label="文件类型" prop="document_type">
          <el-radio-group v-model="documentForm.document_type">
            <el-radio label="image">仅图片</el-radio>
            <el-radio label="pdf">仅PDF</el-radio>
            <el-radio label="document">文档(Word/Excel/PDF)</el-radio>
            <el-radio label="all">所有类型</el-radio>
          </el-radio-group>
          <div style="font-size: 12px; color: #909399; margin-top: 8px;">
            建议：身份证、驾驶证等选"仅图片"；合同、证书等选"文档"或"所有类型"
          </div>
        </el-form-item>

        <el-form-item label="是否必填" prop="is_required">
          <el-switch
            v-model="documentForm.is_required"
            active-text="必填"
            inactive-text="选填"
          />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="showDocumentFormDialog = false">取消</el-button>
        <el-button type="primary" @click="handleSubmitDocumentForm" :loading="submittingDocument">
          确定
        </el-button>
      </template>
    </el-dialog>
    
    <!-- 须知文件设置对话框 -->
    <el-dialog
      v-model="showNoticeDialog"
      title="劳动合同须知文件设置"
      width="900px"
      @close="handleNoticeDialogClose"
    >
      <div style="margin-bottom: 15px;">
        <el-alert type="info" :closable="false" show-icon>
          <template #title>
            为该项目设置劳动合同签订前需要阅读的须知文件（可选择多个，签署时将按顺序逐个确认）
          </template>
        </el-alert>
      </div>
      
      <el-table
        ref="noticeTableRef"
        :data="availableNoticeFiles"
        v-loading="loadingNotices"
        border
        max-height="400"
        row-key="id"
        @selection-change="handleNoticeSelectionChange"
      >
        <el-table-column type="selection" width="55" />
        <el-table-column prop="name" label="文件名" min-width="250" />
        <el-table-column prop="size" label="大小" width="120">
          <template #default="{ row }">
            {{ formatFileSize(row.size) }}
          </template>
        </el-table-column>
        <el-table-column prop="uploader_name" label="上传者" width="120" />
        <el-table-column prop="created_at" label="上传时间" width="160">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="签名占位符" width="170">
          <template #default="{ row }">
            <el-button link type="primary" @click="openNoticePlaceholderSetup(row)">
              设置
            </el-button>
            <el-tag v-if="getNoticePlaceholderCount(row.id) > 0" size="small" type="success">
              {{ getNoticePlaceholderCount(row.id) }}个
            </el-tag>
          </template>
        </el-table-column>
      </el-table>
      
      <div style="margin-top: 15px; text-align: right;">
        <el-button type="info" size="small" @click="handleClearNotice">
          清除设置
        </el-button>
      </div>
      
      <template #footer>
        <el-button @click="showNoticeDialog = false">取消</el-button>
        <el-button type="primary" @click="handleSaveNoticeSettings" :loading="savingNotices">
          保存设置
        </el-button>
      </template>
    </el-dialog>

    <!-- 须知签名占位符设置对话框 -->
    <el-dialog
      v-model="showNoticePlaceholderSetupDialog"
      :title="`设置须知签名占位符 - ${currentNoticePlaceholderFile?.name || ''}`"
      width="90%"
      :close-on-click-modal="false"
      @close="cleanupNoticePlaceholderPdfUrl"
    >
      <PdfPlaceholderSetup
        v-if="currentNoticePlaceholderFile && showNoticePlaceholderSetupDialog"
        :pdf-url="currentNoticePlaceholderPdfUrl"
        :template-id="currentNoticePlaceholderTemplateId"
        :saved-positions="currentNoticePlaceholderPositions"
        :placeholder-fields="[{ key: 'employee_signature', label: '员工签字' }]"
        @save="handleSaveNoticePlaceholderPositions"
        @cancel="() => { showNoticePlaceholderSetupDialog = false; cleanupNoticePlaceholderPdfUrl(); }"
      />
    </el-dialog>

    <!-- 合同模板上传对话框 -->
    <el-dialog
      v-model="showTemplateUploadDialog"
      :title="`上传${getTemplateTypeName(currentTemplateType)}模板`"
      width="800px"
      @close="handleTemplateUploadDialogClose"
    >
      <div style="margin-bottom: 15px;">
        <el-alert type="info" :closable="false" show-icon>
          <template #title>
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
              <span>从共享文件中选择一个文件作为{{ getTemplateTypeName(currentTemplateType) }}模板</span>
              <el-tag size="small" type="warning">仅限 PDF 文件</el-tag>
            </div>
          </template>
        </el-alert>
      </div>
      
      <el-table
        :data="availableSharedFiles"
        v-loading="loadingSharedFiles"
        border
        max-height="400"
        highlight-current-row
        @current-change="handleSharedFileRowChange"
      >
        <el-table-column label="选择" width="80">
          <template #default="{ row }">
            <el-radio v-model="selectedSharedFileId" :label="row.id">
              <span></span>
            </el-radio>
          </template>
        </el-table-column>
        <el-table-column prop="name" label="文件名" min-width="250" />
        <el-table-column prop="file_category" label="类型" width="100">
          <template #default="{ row }">
            <el-tag :type="row.file_category === 'notice' ? 'warning' : 'primary'" size="small">
              {{ row.file_category === 'notice' ? '须知文件' : '共享文件' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="size" label="大小" width="120">
          <template #default="{ row }">
            {{ formatFileSize(row.size) }}
          </template>
        </el-table-column>
        <el-table-column prop="uploader_name" label="上传者" width="120" />
        <el-table-column prop="created_at" label="上传时间" width="160">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
      </el-table>
      
      <div style="margin-top: 15px; text-align: right;">
        <el-button @click="showTemplateUploadDialog = false">取消</el-button>
        <el-button 
          type="primary" 
          @click="handleUploadTemplate"
          :loading="uploadingTemplate"
          :disabled="!selectedSharedFileId"
        >
          确认上传
        </el-button>
      </div>
    </el-dialog>

    <!-- 保单选择对话框 -->
    <el-dialog
      v-model="showPolicySelectionDialog"
      title="选择其他保险保单"
      width="800px"
      :close-on-click-modal="false"
    >
      <div class="policy-selection-container">
        <el-alert 
          type="info" 
          :closable="false" 
          style="margin-bottom: 20px;"
        >
          每种保险类型只能选择一个保单，请按保险类型选择
        </el-alert>

        <div v-if="!groupedPoliciesByType || groupedPoliciesByType.length === 0" style="text-align: center; padding: 40px; color: #909399;">
          暂无可用的保险保单
        </div>

        <div v-else class="policy-type-list">
          <div 
            v-for="typeGroup in groupedPoliciesByType" 
            :key="typeGroup.typeId"
            class="policy-type-item"
          >
            <!-- 保险类型标题（可点击展开/收起） -->
            <div 
              class="policy-type-header"
              @click="toggleTypeExpansion(typeGroup.typeId)"
            >
              <el-icon class="expand-icon" :class="{ 'expanded': expandedTypes[typeGroup.typeId] }">
                <ArrowRight />
              </el-icon>
              <el-icon><DocumentChecked /></el-icon>
              <span class="type-name">{{ typeGroup.typeName }}</span>
              <span class="policy-count">({{ typeGroup.policies.length }}个保单)</span>
              <el-tag v-if="typeGroup.selectedPolicy" type="success" size="small">
                已选: {{ typeGroup.selectedPolicy.policy_name }}
              </el-tag>
            </div>
            
            <!-- 保单列表（可折叠） -->
            <el-collapse-transition>
              <div v-show="expandedTypes[typeGroup.typeId]" class="policy-list-container">
                <el-radio-group 
                  v-model="selectedPoliciesByType[typeGroup.typeId]"
                  class="policy-list"
                >
                  <el-radio 
                    v-for="policy in typeGroup.policies" 
                    :key="policy.id" 
                    :label="policy.id"
                    class="policy-radio-item"
                  >
                    <div class="policy-info">
                      <div class="policy-name">{{ policy.policy_name }}</div>
                      <div class="policy-details">
                        <span>保单号: {{ policy.policy_number }}</span>
                        <span>保险公司: {{ policy.insurance_company }}</span>
                        <span>保额: ¥{{ policy.coverage_amount }}</span>
                        <span>人均费用: ¥{{ policy.employee_per_capita_cost }}</span>
                        <el-tag 
                          :type="policy.status === 'active' ? 'success' : policy.status === 'expired' ? 'danger' : 'warning'" 
                          size="small"
                        >
                          {{ policy.status === 'active' ? '生效中' : policy.status === 'expired' ? '已过期' : '未生效' }}
                        </el-tag>
                      </div>
                    </div>
                  </el-radio>
                </el-radio-group>
              </div>
            </el-collapse-transition>
          </div>
        </div>
      </div>

      <template #footer>
        <el-button @click="showPolicySelectionDialog = false">取消</el-button>
        <el-button type="primary" @click="confirmPolicySelection">
          确定 (已选 {{ selectedPoliciesCount }} 个)
        </el-button>
      </template>
    </el-dialog>
    
    <!-- 占位符设置对话框 -->
    <el-dialog
      v-model="showPlaceholderSetupDialog"
      title="设置PDF占位符位置"
      width="90%"
      :close-on-click-modal="false"
      @close="cleanupPdfUrl"
    >
      <PdfPlaceholderSetup
        v-if="currentTemplate"
        :pdf-url="currentPdfUrl"
        :template-id="currentTemplate.id"
        :saved-positions="currentTemplate.placeholder_positions || []"
        :placeholder-fields="currentProjectPlaceholderFields"
        @save="handleSavePlaceholderPositions"
        @cancel="() => { showPlaceholderSetupDialog = false; cleanupPdfUrl(); }"
      />
    </el-dialog>
    
    <!-- 资料配置对话框 -->
    <ProjectDocumentConfigDialog
      v-model="showDocumentConfigDialog"
      :project-id="currentProjectForDocConfig"
    />
    
    <!-- 占位符字段配置对话框 -->
    <el-dialog
      v-model="showPlaceholderFieldsDialog"
      title="配置占位符字段"
      width="700px"
      :close-on-click-modal="false"
    >
      <div class="placeholder-fields-config">
        <el-alert 
          type="info" 
          :closable="false"
          style="margin-bottom: 16px"
        >
          <template #title>
            选择该项目合同模板中可使用的占位符字段。设置后，在设置占位符时只会显示这里选择的字段。
          </template>
        </el-alert>
        
        <div class="field-selection">
          <div class="select-all-row" style="margin-bottom: 12px;">
            <el-checkbox 
              :model-value="isAllFieldsSelected"
              :indeterminate="isFieldsIndeterminate"
              @change="handleSelectAllFields"
            >
              全选
            </el-checkbox>
          </div>
          <el-checkbox-group v-model="selectedPlaceholderFieldKeys">
            <div class="field-grid">
              <el-checkbox 
                v-for="(label, key) in availablePlaceholderFields" 
                :key="key"
                :label="key"
                border
              >
                {{ label }}
              </el-checkbox>
            </div>
          </el-checkbox-group>
        </div>
        
        <div class="selected-preview" v-if="selectedPlaceholderFieldKeys.length > 0">
          <el-divider content-position="left">已选择 {{ selectedPlaceholderFieldKeys.length }} 个字段</el-divider>
          <div class="selected-tags">
            <el-tag 
              v-for="key in selectedPlaceholderFieldKeys" 
              :key="key"
              closable
              @close="removeSelectedField(key)"
              style="margin: 4px"
            >
              {{ availablePlaceholderFields[key] }}
            </el-tag>
          </div>
        </div>
      </div>
      
      <template #footer>
        <el-button @click="showPlaceholderFieldsDialog = false">取消</el-button>
        <el-button type="primary" @click="savePlaceholderFieldsConfig" :loading="savingPlaceholderFields">
          保存配置
        </el-button>
      </template>
    </el-dialog>
    
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import PdfPlaceholderSetup from '@/components/PdfPlaceholderSetup.vue'
import ProjectDocumentConfigDialog from '@/components/ProjectDocumentConfigDialog.vue'
import { getProjects, createProject, updateProject, deleteProject, getProjectCodePreview } from '@/api/projects'
import { getSharedFiles } from '@/api/sharedFiles'
import { addContractTemplate, getDefaultTemplates, getContractTemplates, setDefaultTemplate, deleteContractTemplate } from '@/api/contractTemplates'
import { getAvailableSocialSecurityRegions, getAvailableHousingFundRegions, setProjectSocialSecurityRegions, setProjectHousingFundRegions } from '@/api/projectSocialSecurity'
import { 
  getAvailableMedicalInsuranceRegions, 
  getAvailableOtherInsurancePolicies,
  setProjectMedicalInsuranceRegions,
  setProjectOtherInsurancePolicies
} from '@/api/projectInsurance'
import {
  getProjectDocumentConfigs,
  createProjectDocumentConfig,
  updateProjectDocumentConfig,
  deleteProjectDocumentConfig,
  updateDocumentConfigsSort
} from '@/api/projectDocuments'
import request from '@/api/request'
import { useUserStore } from '@/stores/user'
import { useAccountSetStore } from '@/stores/accountSet'
import { usePermissionStore } from '@/stores/permission'
import NoAccountSetTip from '@/components/NoAccountSetTip.vue'

const userStore = useUserStore()
const accountSetStore = useAccountSetStore()
const permissionStore = usePermissionStore()

const isAdmin = computed(() => userStore.userInfo?.role === 'admin')
const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)

// 权限控制
const canCreateProject = computed(() => permissionStore.hasPermission('projects.create'))
const canEditProject = computed(() => permissionStore.hasPermission('projects.edit'))
const canDeleteProject = computed(() => permissionStore.hasPermission('projects.delete'))

// 计算属性：按保险类型分组的保单
const groupedPoliciesByType = computed(() => {
  const grouped = {}
  
  availableOtherInsurancePolicies.value.forEach(policy => {
    // 检查 policy.type 是否存在
    if (!policy.type) {
      console.warn('保单缺少类型信息:', policy)
      return
    }
    
    const typeId = policy.type.id
    const typeName = policy.type.name
    
    if (!grouped[typeId]) {
      grouped[typeId] = {
        typeId,
        typeName,
        policies: []
      }
    }
    
    grouped[typeId].policies.push(policy)
  })
  
  // 转换为数组并添加已选保单信息
  return Object.values(grouped).map(group => {
    const selectedPolicyId = selectedPoliciesByType.value[group.typeId]
    const selectedPolicy = selectedPolicyId 
      ? group.policies.find(p => p.id === selectedPolicyId)
      : null
    
    return {
      ...group,
      selectedPolicy
    }
  })
})

// 计算属性：已选保单数量
const selectedPoliciesCount = computed(() => {
  return Object.values(selectedPoliciesByType.value).filter(id => id).length
})

// 计算属性：已选保单摘要
const selectedPoliciesSummary = computed(() => {
  const summary = []
  
  groupedPoliciesByType.value.forEach(group => {
    if (group.selectedPolicy) {
      summary.push(`${group.typeName}: ${group.selectedPolicy.policy_name}`)
    }
  })
  
  return summary
})

const loading = ref(false)
const submitting = ref(false)
const showCreateDialog = ref(false)
const isEdit = ref(false)
const formRef = ref()

// 须知文件设置相关
const showNoticeDialog = ref(false)
const loadingNotices = ref(false)
const savingNotices = ref(false)
const currentProject = ref(null)
const availableNoticeFiles = ref([])
const selectedNoticeFileIds = ref([])
const noticeTableRef = ref()
const noticePlaceholderPositions = ref({})
const showNoticePlaceholderSetupDialog = ref(false)
const currentNoticePlaceholderFile = ref(null)
const currentNoticePlaceholderPdfUrl = ref(null)
const currentNoticePlaceholderTemplateId = ref(0)
const currentNoticePlaceholderPositions = ref([])

// 合同模板管理相关
const availableSharedFiles = ref([])
const contractTemplates = ref({
  labor: [],
  termination: [],
  retirement: [],
  confidentiality: [],
  other: []
})
const showTemplateUploadDialog = ref(false)
const currentTemplateType = ref('labor')
const selectedSharedFileId = ref(null)
const loadingSharedFiles = ref(false)
const uploadingTemplate = ref(false)

// 社保和公积金地区相关
const availableSocialSecurityRegions = ref([])
const availableHousingFundRegions = ref([])
const loadingSocialSecurityRegions = ref(false)
const loadingHousingFundRegions = ref(false)

// 医保和其他保险相关
const availableMedicalInsuranceRegions = ref([])
const availableOtherInsurancePolicies = ref([])
const availableLargeMedicalInsuranceConfigs = ref([])
const loadingMedicalInsuranceRegions = ref(false)
const loadingOtherInsurancePolicies = ref(false)
const loadingLargeMedicalInsuranceConfigs = ref(false)

// 保单选择对话框
const showPolicySelectionDialog = ref(false)
const selectedPoliciesByType = ref({})
const expandedTypes = ref({})  // 记录哪些保险类型已展开

// 占位符设置相关
const showPlaceholderSetupDialog = ref(false)
const currentTemplate = ref(null)
const currentPdfUrl = ref(null)
const currentProjectPlaceholderFields = ref([])

// 占位符字段配置相关
const showPlaceholderFieldsDialog = ref(false)
const currentPlaceholderFieldsProjectId = ref(null)
const selectedPlaceholderFieldKeys = ref([])
const savingPlaceholderFields = ref(false)
const availablePlaceholderFields = ref({
  name: '姓名',
  id_number: '身份证号',
  phone: '手机号',
  address: '地址',
  gender: '性别',
  birth_date: '出生日期',
  nationality: '民族',
  education: '学历',
  position: '岗位',
  employee_number: '工号',
  email: '邮箱',
  bank_name: '开户银行',
  bank_account: '银行卡号',
  bank_account_holder: '开户名',
  basic_salary: '基础薪资',
  comprehensive_salary: '综合薪资',
  probation_salary: '试用期薪资',
  performance_salary: '绩效薪资',
  signing_location: '签署地',
  household_type: '户口类型',
  gender_male_check: '性别-男（打勾）',
  gender_female_check: '性别-女（打勾）',
  household_agricultural_check: '户籍-农业（打勾）',
  household_non_agricultural_check: '户籍-非农业（打勾）',
  hire_date: '入职日期',
  contract_sign_date: '签订日期',
  contract_start_date: '合同开始日期',
  contract_end_date: '合同结束日期',
  contract_start_year: '合同开始年',
  contract_start_month: '合同开始月',
  contract_start_day: '合同开始日',
  contract_end_year: '合同结束年',
  contract_end_month: '合同结束月',
  contract_end_day: '合同结束日',
  emergency_contact: '紧急联系人',
  emergency_phone: '紧急联系电话',
  household_address: '户籍地址',
  residence_address: '居住地址',
  contact_address: '通讯地址',
  employee_signature: '员工签字'
})

// 资料配置相关
const showDocumentConfigDialog = ref(false)
const currentProjectForDocConfig = ref(null)
const documentConfigs = ref([])
const loadingDocumentConfigs = ref(false)
const showDocumentFormDialog = ref(false)
const documentFormMode = ref('add') // 'add' or 'edit'
const documentFormRef = ref()
const submittingDocument = ref(false)

const documentForm = reactive({
  id: null,
  document_name: '',
  document_type: 'all',  // 默认所有类型
  is_required: true
})

const documentFormRules = {
  document_name: [
    { required: true, message: '请输入项目名称', trigger: 'blur' }
  ],
  code: [
    { min: 1, max: 255, message: '项目编号长度需在 1 到 255 个字符之间', trigger: 'blur' }
  ],
  document_type: [
    { required: true, message: '请选择文件类型', trigger: 'change' }
  ]
}

// 清理PDF URL
const cleanupPdfUrl = () => {
  currentPdfUrl.value = null
}

const cleanupNoticePlaceholderPdfUrl = () => {
  currentNoticePlaceholderPdfUrl.value = null
}

// 打开资料配置对话框
const handleDocumentSettings = (row) => {
  currentProjectForDocConfig.value = row.id
  showDocumentConfigDialog.value = true
}

// 打开占位符字段配置对话框
const handlePlaceholderFieldsSettings = async (row) => {
  currentPlaceholderFieldsProjectId.value = row.id
  selectedPlaceholderFieldKeys.value = []
  
  // 加载已配置的字段
  try {
    const res = await request.get(`/projects/${row.id}/placeholder-fields`)
    if (res.success && res.data && res.data.length > 0) {
      selectedPlaceholderFieldKeys.value = res.data.map(f => f.key)
    }
  } catch (e) {
    console.warn('加载占位符字段配置失败:', e)
  }
  
  showPlaceholderFieldsDialog.value = true
}

// 计算是否全选
const isAllFieldsSelected = computed(() => {
  const allKeys = Object.keys(availablePlaceholderFields.value)
  return allKeys.length > 0 && selectedPlaceholderFieldKeys.value.length === allKeys.length
})

// 计算是否部分选中
const isFieldsIndeterminate = computed(() => {
  const allKeys = Object.keys(availablePlaceholderFields.value)
  return selectedPlaceholderFieldKeys.value.length > 0 && selectedPlaceholderFieldKeys.value.length < allKeys.length
})

// 全选/取消全选
const handleSelectAllFields = (val) => {
  if (val) {
    selectedPlaceholderFieldKeys.value = Object.keys(availablePlaceholderFields.value)
  } else {
    selectedPlaceholderFieldKeys.value = []
  }
}

// 移除选中的字段
const removeSelectedField = (key) => {
  const index = selectedPlaceholderFieldKeys.value.indexOf(key)
  if (index > -1) {
    selectedPlaceholderFieldKeys.value.splice(index, 1)
  }
}

// 保存占位符字段配置
const savePlaceholderFieldsConfig = async () => {
  if (!currentPlaceholderFieldsProjectId.value) return
  
  savingPlaceholderFields.value = true
  try {
    // 构建要保存的字段数据
    const fieldsToSave = selectedPlaceholderFieldKeys.value.map(key => ({
      key: key,
      label: availablePlaceholderFields.value[key]
    }))
    
    const res = await request.post(`/projects/${currentPlaceholderFieldsProjectId.value}/placeholder-fields`, {
      placeholder_fields: fieldsToSave
    })
    
    if (res.success) {
      ElMessage.success('占位符字段配置保存成功')
      showPlaceholderFieldsDialog.value = false
    } else {
      ElMessage.error(res.message || '保存失败')
    }
  } catch (error) {
    console.error('保存占位符字段配置失败:', error)
    ElMessage.error('保存失败: ' + (error.response?.data?.message || error.message))
  } finally {
    savingPlaceholderFields.value = false
  }
}

// 加载项目资料配置
const loadDocumentConfigs = async () => {
  if (!currentProject.value || !currentProject.value.id) return
  
  loadingDocumentConfigs.value = true
  try {
    const response = await getProjectDocumentConfigs(currentProject.value.id)
    if (response.success) {
      documentConfigs.value = response.data || []
    }
  } catch (error) {
    console.error('加载资料配置失败:', error)
    ElMessage.error('加载资料配置失败')
  } finally {
    loadingDocumentConfigs.value = false
  }
}

// 添加资料配置
const handleAddDocument = () => {
  documentFormMode.value = 'add'
  documentForm.id = null
  documentForm.document_name = ''
  documentForm.document_type = 'all'  // 默认所有类型
  documentForm.is_required = true
  showDocumentFormDialog.value = true
}

// 编辑资料配置
const handleEditDocument = (row) => {
  documentFormMode.value = 'edit'
  documentForm.id = row.id
  documentForm.document_name = row.document_name
  documentForm.document_type = row.document_type
  documentForm.is_required = row.is_required
  showDocumentFormDialog.value = true
}

// 提交资料配置表单
const handleSubmitDocumentForm = async () => {
  if (!documentFormRef.value) return

  await documentFormRef.value.validate(async (valid) => {
    if (!valid) return

    submittingDocument.value = true
    try {
      if (documentFormMode.value === 'add') {
        await createProjectDocumentConfig(currentProject.value.id, {
          document_name: documentForm.document_name,
          document_type: documentForm.document_type,
          is_required: documentForm.is_required
        })
        ElMessage.success('添加成功')
      } else {
        await updateProjectDocumentConfig(currentProject.value.id, documentForm.id, {
          document_name: documentForm.document_name,
          document_type: documentForm.document_type,
          is_required: documentForm.is_required
        })
        ElMessage.success('更新成功')
      }

      showDocumentFormDialog.value = false
      loadDocumentConfigs()
    } catch (error) {
      console.error('操作失败:', error)
      ElMessage.error(error.response?.data?.message || '操作失败')
    } finally {
      submittingDocument.value = false
    }
  })
}

// 删除资料配置
const handleDeleteDocument = async (row) => {
  try {
    await ElMessageBox.confirm('确定要删除这个资料配置吗？', '确认删除', {
      type: 'warning'
    })

    await deleteProjectDocumentConfig(currentProject.value.id, row.id)
    ElMessage.success('删除成功')
    loadDocumentConfigs()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }
}

// 上移资料配置
const handleMoveDocUp = async (index) => {
  if (index === 0) return

  const temp = documentConfigs.value[index]
  documentConfigs.value[index] = documentConfigs.value[index - 1]
  documentConfigs.value[index - 1] = temp

  await updateDocumentSort()
}

// 下移资料配置
const handleMoveDocDown = async (index) => {
  if (index === documentConfigs.value.length - 1) return

  const temp = documentConfigs.value[index]
  documentConfigs.value[index] = documentConfigs.value[index + 1]
  documentConfigs.value[index + 1] = temp

  await updateDocumentSort()
}

// 更新资料配置排序
const updateDocumentSort = async () => {
  try {
    const updatedConfigs = documentConfigs.value.map((config, index) => ({
      id: config.id,
      sort_order: index + 1
    }))

    await updateDocumentConfigsSort(currentProject.value.id, updatedConfigs)
  } catch (error) {
    console.error('更新排序失败:', error)
    ElMessage.error('更新排序失败')
    loadDocumentConfigs() // 重新加载
  }
}

// 获取文件类型文本
const getDocumentTypeText = (type) => {
  const texts = {
    image: '仅图片',
    pdf: '仅PDF',
    document: '文档',
    all: '所有类型'
  }
  return texts[type] || type
}

// 获取文件类型标签颜色
const getDocumentTypeTagType = (type) => {
  const types = {
    image: 'success',
    pdf: 'warning',
    document: 'primary',
    all: 'info'
  }
  return types[type] || 'info'
}

const projects = ref([])

const searchForm = reactive({
  search: '',
  status: ''
})

const pagination = reactive({
  currentPage: 1,
  pageSize: 20,
  total: 0
})

const form = reactive({
  name: '',
  code: '',
  description: '',
  status: 'active',
  start_date: '',
  end_date: '',
  social_security_location: '',
  insurance_types: [],
  salary_payment_date: null,  // 工资发放日期：每月几号（1-31）
  salary_payment_month: 'current',  // 工资发放月份：本月/次月
  insurance_import_month: 'current',  // 保险导入设置：当月/次月/不导入
  requires_attendance: true,
  requires_salary_basis: false,  // 是否需要上传工资依据
  requires_attendance_basis: false,  // 是否需要上传考勤依据
  registration_form_type: 'onboarding',  // 登记表类型：onboarding-入职登记表，registration-从业人员登记表
  delivery_frequency: 'monthly',
  delivery_method: 'electronic',
  delivery_requirements: [],
  social_security_regions: [],  // 社保地区ID列表
  housing_fund_regions: [],  // 公积金地区ID列表
  medical_insurance_regions: [],  // 医保地区ID列表
  other_insurance_policies: [],  // 其他保险保单ID列表
  large_medical_insurance_configs: []  // 大额医疗保险配置ID列表
})

const formRules = {
  name: [
    { required: true, message: '请输入项目名称', trigger: 'blur' }
  ],
  code: [
    { min: 1, max: 255, message: '项目编号长度需在 1 到 255 个字符之间', trigger: 'blur' }
  ],
  status: [
    { required: true, message: '请选择状态', trigger: 'change' }
  ],
  delivery_frequency: [
    { required: true, message: '请选择交付频率', trigger: 'change' }
  ],
  delivery_method: [
    { required: true, message: '请选择交付方式', trigger: 'change' }
  ]
}

const isProjectCodeManuallyEdited = ref(false)
const normalizeProjectCode = (value) => {
  if (typeof value !== 'string') {
    return ''
  }
  return value.trim().toUpperCase()
}
const updateProjectCodePreview = async () => {
  if (form.id || isProjectCodeManuallyEdited.value) {
    return
  }
  const projectName = typeof form.name === 'string' ? form.name.trim() : ''
  if (!projectName) {
    form.code = ''
    return
  }
  try {
    const response = await getProjectCodePreview({
      name: projectName,
      current_account_set_id: currentAccountSetId.value
    })
    if (response?.success) {
      form.code = normalizeProjectCode(response.data?.code || '')
    }
  } catch (error) {
    console.error('get project code preview failed:', error)
  }
}
const handleProjectNameBlur = async () => {
  await updateProjectCodePreview()
}
const handleProjectCodeInput = (value) => {
  const normalized = normalizeProjectCode(value)
  if (form.code !== normalized) {
    form.code = normalized
  }
  isProjectCodeManuallyEdited.value = normalized !== ''
}
const handleProjectCodeBlur = () => {
  form.code = normalizeProjectCode(form.code)
}
const loadProjects = async () => {
  loading.value = true
  try {
    const params = {
      page: pagination.currentPage,
      per_page: pagination.pageSize,
      current_account_set_id: currentAccountSetId.value,
      ...searchForm
    }
    
    console.log('Loading projects with params:', params)
    console.log('Current account set ID:', currentAccountSetId.value)
    console.log('Is admin:', isAdmin.value)
    console.log('API URL:', 'http://localhost:8000/api/projects')
    
    const response = await getProjects(params)
    console.log('API response:', response)
    console.log('Response type:', typeof response)
    console.log('Response keys:', Object.keys(response))
    
    if (response && response.success) {
      projects.value = response.data.data || []
      pagination.total = response.data.total || 0
      console.log('Loaded projects:', projects.value.length)
      console.log('Projects data:', projects.value)
    } else {
      console.error('API returned success: false or no response')
      console.error('Response:', response)
      ElMessage.error('加载项目数据失败')
    }
  } catch (error) {
    console.error('Load projects error:', error)
    console.error('Error details:', {
      message: error.message,
      response: error.response,
      request: error.request
    })
    ElMessage.error('加载项目数据失败: ' + error.message)
  } finally {
    loading.value = false
  }
}

// 加载可用的社保地区
const loadAvailableSocialSecurityRegions = async () => {
  if (!currentAccountSetId.value) {
    console.warn('No current account set ID available')
    return
  }
  
  console.log('Loading available social security regions for account set:', currentAccountSetId.value)
  loadingSocialSecurityRegions.value = true
  try {
    const response = await getAvailableSocialSecurityRegions({
      account_set_id: currentAccountSetId.value
    })
    if (response.success) {
      availableSocialSecurityRegions.value = response.data
    }
  } catch (error) {
    console.error('加载社保地区失败:', error)
    ElMessage.error('加载社保地区失败')
  } finally {
    loadingSocialSecurityRegions.value = false
  }
}

// 加载可用的公积金地区
const loadAvailableHousingFundRegions = async () => {
  if (!currentAccountSetId.value) {
    console.warn('No current account set ID available')
    return
  }
  
  console.log('Loading available housing fund regions for account set:', currentAccountSetId.value)
  loadingHousingFundRegions.value = true
  try {
    const response = await getAvailableHousingFundRegions({
      account_set_id: currentAccountSetId.value
    })
    if (response.success) {
      availableHousingFundRegions.value = response.data
    }
  } catch (error) {
    console.error('加载公积金地区失败:', error)
    ElMessage.error('加载公积金地区失败')
  } finally {
    loadingHousingFundRegions.value = false
  }
}

// 处理社保地区变化
const handleSocialSecurityRegionsChange = (value) => {
  console.log('社保地区变化:', value)
}

// 处理公积金地区变化
const handleHousingFundRegionsChange = (value) => {
  console.log('公积金地区变化:', value)
}

// 加载可用的医保地区
const loadAvailableMedicalInsuranceRegions = async () => {
  if (!currentAccountSetId.value) {
    console.warn('No current account set ID available')
    return
  }

  loadingMedicalInsuranceRegions.value = true
  try {
    const response = await getAvailableMedicalInsuranceRegions({
      account_set_id: currentAccountSetId.value
    })
    if (response.success) {
      availableMedicalInsuranceRegions.value = response.data
    }
  } catch (error) {
    console.error('加载医保地区失败:', error)
    ElMessage.error('加载医保地区失败')
  } finally {
    loadingMedicalInsuranceRegions.value = false
  }
}

// 加载可用的其他保险保单
const loadAvailableOtherInsurancePolicies = async () => {
  if (!currentAccountSetId.value) {
    console.warn('No current account set ID available')
    return
  }

  loadingOtherInsurancePolicies.value = true
  try {
    const response = await getAvailableOtherInsurancePolicies({
      account_set_id: currentAccountSetId.value
    })
    if (response.success) {
      availableOtherInsurancePolicies.value = response.data
    }
  } catch (error) {
    console.error('加载其他保险保单失败:', error)
    ElMessage.error('加载其他保险保单失败')
  } finally {
    loadingOtherInsurancePolicies.value = false
  }
}

// 加载可用的大额医疗保险配置
const loadAvailableLargeMedicalInsuranceConfigs = async () => {
  if (!currentAccountSetId.value) {
    console.warn('No current account set ID available')
    return
  }

  loadingLargeMedicalInsuranceConfigs.value = true
  try {
    const response = await request.get('/projects/available/large-medical-insurance-regions', {
      params: {
        account_set_id: currentAccountSetId.value
      }
    })
    if (response.success) {
      availableLargeMedicalInsuranceConfigs.value = response.data
    }
  } catch (error) {
    console.error('加载大额医疗保险配置失败:', error)
    ElMessage.error('加载大额医疗保险配置失败')
  } finally {
    loadingLargeMedicalInsuranceConfigs.value = false
  }
}

// 处理医保地区变化
const handleMedicalInsuranceRegionsChange = (value) => {
  console.log('医保地区变化:', value)
}

// 确认保单选择
const confirmPolicySelection = () => {
  // 将selectedPoliciesByType转换为保单ID数组
  const selectedIds = Object.values(selectedPoliciesByType.value).filter(id => id)
  form.other_insurance_policies = selectedIds
  showPolicySelectionDialog.value = false
  ElMessage.success(`已选择 ${selectedIds.length} 个保单`)
}

// 切换保险类型展开/收起状态
const toggleTypeExpansion = (typeId) => {
  expandedTypes.value[typeId] = !expandedTypes.value[typeId]
}

// 打开保单选择对话框时，初始化选择状态
const openPolicySelectionDialog = () => {
  // 从form.other_insurance_policies恢复选择状态
  const tempSelection = {}
  
  form.other_insurance_policies.forEach(policyId => {
    const policy = availableOtherInsurancePolicies.value.find(p => p.id === policyId)
    if (policy) {
      tempSelection[policy.type.id] = policyId
    }
  })
  
  selectedPoliciesByType.value = tempSelection
  
  // 初始化展开状态（默认都收起）
  expandedTypes.value = {}
  
  showPolicySelectionDialog.value = true
}

// 重置表单
const resetForm = () => {
  isProjectCodeManuallyEdited.value = false
  Object.assign(form, {
    id: undefined,  // 重置ID，确保新建时不会误用旧ID
    name: '',
    code: '',
    description: '',
    status: 'active',
    start_date: '',
    end_date: '',
    social_security_location: '',
    insurance_types: [],
    salary_payment_date: null,
    requires_attendance: true,
    requires_salary_basis: false,
    requires_attendance_basis: false,
    registration_form_type: 'onboarding',
    delivery_frequency: 'monthly',
    delivery_method: 'electronic',
    delivery_requirements: [],
    social_security_regions: [],
    housing_fund_regions: [],
    medical_insurance_regions: [],
    other_insurance_policies: [],
    large_medical_insurance_configs: []
  })
}

// 占位符设置相关方法
const openPlaceholderSetup = async (template, contractType) => {
  console.log('打开占位符设置:', template, contractType)
  
  currentTemplate.value = template
  
  try {
    if (!template.shared_file) {
      ElMessage.error('模板文件已被删除，无法设置占位符')
      return
    }
    
    console.log('开始获取模板文件:', template.shared_file.id, template.shared_file.name)
    console.log('文件路径:', template.shared_file.path)
    
    // 使用相对路径，通过Vite代理访问，避免CORS问题
    const fileUrl = `/storage/${template.shared_file.path}`
    
    console.log('构建的文件URL:', fileUrl)
    console.log('原始路径:', template.shared_file.path)
    
    // 获取项目的占位符字段配置
    const projectId = template.project_id || currentProject.value?.id
    if (projectId) {
      try {
        const fieldsRes = await request.get(`/projects/${projectId}/placeholder-fields`)
        if (fieldsRes.success && fieldsRes.data) {
          currentProjectPlaceholderFields.value = fieldsRes.data
        } else {
          currentProjectPlaceholderFields.value = []
        }
      } catch (e) {
        console.warn('获取项目占位符字段配置失败，使用默认字段:', e)
        currentProjectPlaceholderFields.value = []
      }
    } else {
      currentProjectPlaceholderFields.value = []
    }
    
    // 直接设置PDF URL，让PDF.js组件处理
    currentPdfUrl.value = fileUrl
    showPlaceholderSetupDialog.value = true
    
    ElMessage.success('PDF文件加载成功')
    
  } catch (error) {
    console.error('获取模板文件失败:', error)
    ElMessage.error('获取模板文件失败: ' + (error.response?.data?.message || error.message))
  }
}

const handleSavePlaceholderPositions = async (data) => {
  console.log('保存占位符位置:', data)
  
  try {
    // 使用项目的request工具保存占位符位置
    // 注意：request拦截器已经返回了 data，所以 response 就是后端返回的数据本身
    const result = await request.post('/projects/contract-templates/placeholder-positions', {
      template_id: data.templateId,
      positions: data.positions
    })
    
    console.log('保存响应:', result)
    
    if (result && result.success) {
      ElMessage.success('占位符位置保存成功')
      // 更新本地模板数据
      if (currentTemplate.value) {
        currentTemplate.value.placeholder_positions = data.positions
      }
      showPlaceholderSetupDialog.value = false
    } else {
      ElMessage.error(result?.message || '保存失败')
    }
    
  } catch (error) {
    console.error('保存占位符位置失败:', error)
    console.error('错误详情:', error.response)
    ElMessage.error('保存失败: ' + (error.response?.data?.message || error.message))
  }
}

// 处理项目状态变化
const handleStatusChange = (newStatus) => {
  // 如果选择"已停用"，自动关闭相关开关
  if (newStatus === 'inactive') {
    form.requires_salary_basis = false
    form.requires_attendance_basis = false
    form.requires_attendance = false
    ElMessage.info('已自动关闭工资依据、考勤依据和考勤表开关')
  }
}

// 处理新建项目
const handleCreate = async () => {
  isEdit.value = false  // 新建时设置为false
  currentProject.value = null
  resetForm()
  isProjectCodeManuallyEdited.value = false
  
  // 重置合同模板数据
  contractTemplates.value = {
    labor: [],
    termination: [],
    retirement: [],
    confidentiality: [],
    other: []
  }
  
  // 加载所有地区和保险数据
  await loadAvailableRegions()
  
  showCreateDialog.value = true
}

const handleSearch = () => {
  pagination.currentPage = 1
  loadProjects()
}

const handleReset = () => {
  Object.assign(searchForm, {
    search: '',
    status: ''
  })
  handleSearch()
}

const handleSizeChange = (size) => {
  pagination.pageSize = size
  loadProjects()
}

const handleCurrentChange = (page) => {
  pagination.currentPage = page
  loadProjects()
}

const handleView = async (row) => {
  // 查看项目详情
  isEdit.value = false
  currentProject.value = row
  isProjectCodeManuallyEdited.value = true
  
  // 先加载所有可用的地区和保险数据（确保下拉列表有数据）
  await loadAvailableRegions()
  
  Object.assign(form, {
    ...row,
    insurance_types: row.insurance_types || [],
    delivery_requirements: row.delivery_requirements || []
  })
  
  console.log('查看项目 - 地区数据加载完成:', {
    socialSecurity: availableSocialSecurityRegions.value.length,
    medicalInsurance: availableMedicalInsuranceRegions.value.length,
    housingFund: availableHousingFundRegions.value.length,
    otherInsurance: availableOtherInsurancePolicies.value.length,
    largeMedical: availableLargeMedicalInsuranceConfigs.value.length
  })
  
  // 加载资料配置
  await loadDocumentConfigs()
  
  // 加载合同模板
  await loadContractTemplates()
  
  showCreateDialog.value = true
}

const handleEdit = async (row) => {
  isEdit.value = true
  currentProject.value = row
  isProjectCodeManuallyEdited.value = true
  
  // 先加载所有可用的地区和保险数据（确保下拉列表有数据）
  await loadAvailableRegions()
  
  Object.assign(form, {
    ...row,
    insurance_types: row.insurance_types || [],
    delivery_requirements: row.delivery_requirements || [],
    // 注意：从数据库返回的是数组ID，但显示时需要从_data中获取详细信息
    social_security_regions: row.social_security_regions || [],
    housing_fund_regions: row.housing_fund_regions || [],
    // 医保和其他保险使用关系，需要提取ID
    medical_insurance_regions: row.medical_insurance_regions ? row.medical_insurance_regions.map(r => r.id) : [],
    other_insurance_policies: row.other_insurance_policies ? row.other_insurance_policies.map(p => p.id) : [],
    large_medical_insurance_configs: row.large_medical_insurance_configs ? row.large_medical_insurance_configs.map(c => c.id) : []
  })
  
  console.log('编辑项目 - 地区数据加载完成:', {
    socialSecurity: availableSocialSecurityRegions.value.length,
    medicalInsurance: availableMedicalInsuranceRegions.value.length,
    housingFund: availableHousingFundRegions.value.length,
    otherInsurance: availableOtherInsurancePolicies.value.length,
    largeMedical: availableLargeMedicalInsuranceConfigs.value.length
  })
  
  // 【优化】只加载资料配置，其他数据改为懒加载（用户切换tab时再加载）
  // 这样可以大幅提升编辑时的加载速度
  await loadDocumentConfigs()
  
  // 加载合同模板
  await loadContractTemplates()
  
  showCreateDialog.value = true
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm('确定要删除该项目吗？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    
    await deleteProject(row.id)
    ElMessage.success('删除成功')
    loadProjects()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('Delete project error:', error)
    }
  }
}

// 须知文件设置
const handleNoticeSettings = async (row) => {
  currentProject.value = row
  showNoticeDialog.value = true
  await loadAvailableNoticeFiles()
  await loadProjectNoticeFiles(row.id)
}

const loadAvailableNoticeFiles = async () => {
  loadingNotices.value = true
  try {
    // 获取所有须知文件
    const response = await getSharedFiles({
      file_category: 'notice',
      per_page: 1000 // 获取全部
    })
    
    if (response.success) {
      // 过滤掉 .folder 占位文件和文件夹
      const files = (response.data || []).filter(file => {
        // 排除 .folder 占位文件
        if (file.name && file.name.endsWith('.folder')) return false
        if (file.original_name && file.original_name === '.folder') return false
        // 排除文件夹类型
        if (file.type === 'folder') return false
        return true
      })
      availableNoticeFiles.value = files
    }
  } catch (error) {
    console.error('Load notice files error:', error)
    ElMessage.error('加载须知文件列表失败')
  } finally {
    loadingNotices.value = false
  }
}

const loadProjectNoticeFiles = async (projectId) => {
  try {
    const response = await request({
      url: `/projects/${projectId}/contract-notices`,
      method: 'get'
    })

    console.log('📋 加载项目须知文件:', response)

    let ids = []
    let loadedNoticePlaceholderPositions = {}
    if (response.success && response.data) {
      if (Array.isArray(response.data.notice_file_ids)) {
        ids = response.data.notice_file_ids.map(id => Number(id)).filter(id => Number.isInteger(id) && id > 0)
      } else if (response.data.notice_file && response.data.notice_file.id) {
        ids = [Number(response.data.notice_file.id)]
      }
      loadedNoticePlaceholderPositions = response.data.notice_placeholder_positions || {}
    }

    selectedNoticeFileIds.value = ids

    const selectedIdSet = new Set(ids)
    if (noticeTableRef.value) {
      noticeTableRef.value.clearSelection()
      availableNoticeFiles.value.forEach(file => {
        if (selectedIdSet.has(Number(file.id))) {
          noticeTableRef.value.toggleRowSelection(file, true)
        }
      })
    }

    noticePlaceholderPositions.value = loadedNoticePlaceholderPositions
  } catch (error) {
    console.error('❌ 加载项目须知文件失败:', error)
    selectedNoticeFileIds.value = []
    noticePlaceholderPositions.value = {}
    if (noticeTableRef.value) {
      noticeTableRef.value.clearSelection()
    }
  }
}

const handleNoticeSelectionChange = (rows) => {
  const selectedIds = (rows || []).map(row => Number(row.id))
  selectedNoticeFileIds.value = selectedIds

  const selectedIdSet = new Set(selectedIds)
  const nextPositions = {}
  Object.entries(noticePlaceholderPositions.value || {}).forEach(([fileId, positions]) => {
    const id = Number(fileId)
    if (selectedIdSet.has(id)) {
      nextPositions[id] = positions
    }
  })
  noticePlaceholderPositions.value = nextPositions
}

const handleClearNotice = () => {
  selectedNoticeFileIds.value = []
  noticePlaceholderPositions.value = {}
  showNoticePlaceholderSetupDialog.value = false
  currentNoticePlaceholderFile.value = null
  currentNoticePlaceholderPositions.value = []
  currentNoticePlaceholderTemplateId.value = 0
  cleanupNoticePlaceholderPdfUrl()
  if (noticeTableRef.value) {
    noticeTableRef.value.clearSelection()
  }
}

const handleSaveNoticeSettings = async () => {
  if (!currentProject.value) return

  console.log('💾 保存须知文件设置:', selectedNoticeFileIds.value)

  savingNotices.value = true
  try {
    await request({
      url: `/projects/${currentProject.value.id}/contract-notices`,
      method: 'post',
      data: {
        notice_file_ids: selectedNoticeFileIds.value,
        notice_placeholder_positions: noticePlaceholderPositions.value
      }
    })

    if (selectedNoticeFileIds.value.length > 0) {
      ElMessage.success('须知文件设置成功')
    } else {
      ElMessage.success('须知文件已清除')
    }
    showNoticeDialog.value = false
    loadProjects()
  } catch (error) {
    console.error('❌ 保存失败:', error)
    ElMessage.error(error.response?.data?.message || '保存失败')
  } finally {
    savingNotices.value = false
  }
}

const handleNoticeDialogClose = () => {
  selectedNoticeFileIds.value = []
  noticePlaceholderPositions.value = {}
  currentNoticePlaceholderFile.value = null
  currentNoticePlaceholderPositions.value = []
  currentNoticePlaceholderTemplateId.value = 0
  cleanupNoticePlaceholderPdfUrl()
  if (noticeTableRef.value) {
    noticeTableRef.value.clearSelection()
  }
  currentProject.value = null
}

const getNoticePlaceholderCount = (fileId) => {
  const positions = noticePlaceholderPositions.value?.[fileId] || []
  return (positions || []).filter(p => p && p.type === 'employee_signature').length
}

const openNoticePlaceholderSetup = (row) => {
  if (!row || !row.path) {
    ElMessage.warning('该须知文件无可用PDF路径')
    return
  }

  if (!selectedNoticeFileIds.value.includes(Number(row.id))) {
    ElMessage.warning('请先勾选该须知文件后再设置占位符')
    return
  }

  currentNoticePlaceholderFile.value = row
  currentNoticePlaceholderTemplateId.value = Number(`9${currentProject.value?.id || 0}${row.id}`)
  currentNoticePlaceholderPositions.value = Array.isArray(noticePlaceholderPositions.value?.[row.id])
    ? noticePlaceholderPositions.value[row.id]
    : []
  currentNoticePlaceholderPdfUrl.value = `/storage/${row.path}`
  showNoticePlaceholderSetupDialog.value = true
}

const handleSaveNoticePlaceholderPositions = (data) => {
  const fileId = currentNoticePlaceholderFile.value?.id
  if (!fileId) {
    return
  }
  noticePlaceholderPositions.value = {
    ...noticePlaceholderPositions.value,
    [fileId]: (data.positions || []).filter(p => p && p.type === 'employee_signature')
  }
  showNoticePlaceholderSetupDialog.value = false
  cleanupNoticePlaceholderPdfUrl()
  ElMessage.success('须知签名占位符已保存到当前配置')
}

// 合同模板管理方法
const getTemplateTypeName = (type) => {
  const names = {
    labor: '劳动合同',
    termination: '解除协议',
    confidentiality: '保密协议',
    retirement: '退休解除协议',
    other: '其他合同'
  }
  return names[type] || type
}

const loadAvailableSharedFiles = async () => {
  loadingSharedFiles.value = true
  try {
    const response = await getSharedFiles({
      current_account_set_id: currentAccountSetId.value,
      per_page: 100  // 获取更多文件，避免分页问题
    })
    console.log('共享文件API响应:', response)
    if (response.success) {
      // 根据文件名后缀过滤，只显示PDF文件，并排除文件夹
      const allFiles = response.data || []
      availableSharedFiles.value = allFiles.filter(file => {
        // 排除文件夹（type为folder或path为空）
        if (file.type === 'folder' || !file.path) {
          return false
        }
        const fileName = (file.original_name || file.name || '').toLowerCase()
        return fileName.endsWith('.pdf')
      })
      console.log('加载到的共享文件数量:', allFiles.length, '，PDF文件数量:', availableSharedFiles.value.length)
    } else {
      availableSharedFiles.value = []
      console.log('API返回失败:', response)
    }
  } catch (error) {
    console.error('加载共享文件失败:', error)
    ElMessage.error('加载共享文件失败')
    availableSharedFiles.value = []
  } finally {
    loadingSharedFiles.value = false
  }
}

const openTemplateUploadDialog = (templateType) => {
  currentTemplateType.value = templateType
  selectedSharedFileId.value = null
  showTemplateUploadDialog.value = true
  loadAvailableSharedFiles()
}

const handleSharedFileRowChange = (row) => {
  if (row) {
    selectedSharedFileId.value = row.id
  }
}

const handleUploadTemplate = async () => {
  if (!selectedSharedFileId.value || !currentProject.value) return
  
  uploadingTemplate.value = true
  try {
    // 检查是否已有该类型的模板
    const existingTemplates = contractTemplates.value[currentTemplateType.value] || []
    const isFirstTemplate = existingTemplates.length === 0
    
    await addContractTemplate(currentProject.value.id, {
      contract_type: currentTemplateType.value,
      shared_file_id: selectedSharedFileId.value,
      is_default: isFirstTemplate  // 只有第一个模板才自动设为默认
    })
    
    ElMessage.success('模板上传成功' + (isFirstTemplate ? '（已自动设为默认）' : ''))
    showTemplateUploadDialog.value = false
    loadContractTemplates()
  } catch (error) {
    console.error('上传模板失败:', error)
    ElMessage.error(error.response?.data?.message || '上传模板失败')
  } finally {
    uploadingTemplate.value = false
  }
}

const handleTemplateUploadDialogClose = () => {
  selectedSharedFileId.value = null
  currentTemplateType.value = 'labor'
}

const loadContractTemplates = async () => {
  if (!currentProject.value) return
  
  try {
    const response = await getContractTemplates(currentProject.value.id)
    const templates = response.data || {}
    
    // 按合同类型分组
    contractTemplates.value = {
      labor: templates.labor || [],
      termination: templates.termination || [],
      retirement: templates.retirement || [],
      confidentiality: templates.confidentiality || [],
      other: templates.other || []
    }
  } catch (error) {
    console.error('加载合同模板失败:', error)
  }
}

const setDefaultTemplateAction = async (templateId) => {
  try {
    await setDefaultTemplate(templateId)
    ElMessage.success('默认模板设置成功')
    loadContractTemplates()
  } catch (error) {
    console.error('设置默认模板失败:', error)
    ElMessage.error(error.response?.data?.message || '设置默认模板失败')
  }
}

const deleteTemplate = async (templateId) => {
  try {
    await ElMessageBox.confirm('确定要删除这个模板吗？', '确认删除', {
      type: 'warning'
    })
    
    await deleteContractTemplate(templateId)
    ElMessage.success('模板删除成功')
    loadContractTemplates()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除模板失败:', error)
      ElMessage.error(error.response?.data?.message || '删除模板失败')
    }
  }
}





const handleSubmit = async () => {
  if (!formRef.value) return
  
  await formRef.value.validate(async (valid) => {
    if (valid) {
      submitting.value = true
      try {
        let projectId
        // 使用 form.id 来判断是编辑还是新建，更可靠
        if (isEdit.value && form.id) {
          await updateProject(form.id, form)
          projectId = form.id
          ElMessage.success('更新成功')
        } else {
          const response = await createProject(form)
          projectId = response.data.id
          ElMessage.success('创建成功')
        }
        
        // 保存社保地区（过滤空值和无效值）
        if (form.social_security_regions && form.social_security_regions.length > 0) {
          const validRegions = form.social_security_regions.filter(id => id !== null && id !== undefined && id !== '')
          if (validRegions.length > 0) {
            try {
              await setProjectSocialSecurityRegions(projectId, {
                region_ids: validRegions
              })
            } catch (error) {
              console.warn('保存社保地区失败:', error)
            }
          }
        }
        
        // 保存公积金地区（过滤空值和无效值）
        if (form.housing_fund_regions && form.housing_fund_regions.length > 0) {
          const validRegions = form.housing_fund_regions.filter(id => id !== null && id !== undefined && id !== '')
          if (validRegions.length > 0) {
            try {
              await setProjectHousingFundRegions(projectId, {
                region_ids: validRegions
              })
            } catch (error) {
              console.warn('保存公积金地区失败:', error)
            }
          }
        }
        
        // 保存医保地区（过滤空值和无效值）
        if (form.medical_insurance_regions && form.medical_insurance_regions.length > 0) {
          const validRegions = form.medical_insurance_regions.filter(id => id !== null && id !== undefined && id !== '')
          if (validRegions.length > 0) {
            try {
              await setProjectMedicalInsuranceRegions(projectId, {
                region_ids: validRegions
              })
            } catch (error) {
              console.warn('保存医保地区失败:', error)
            }
          }
        }
        
        // 保存其他保险保单
        if (form.other_insurance_policies && form.other_insurance_policies.length > 0) {
          await setProjectOtherInsurancePolicies(projectId, {
            policy_ids: form.other_insurance_policies
          })
        }
        
        // 保存大额医疗保险配置（即使为空也要同步，以便清除之前的绑定）
        await request.post(`/projects/${projectId}/large-medical-insurance-configs`, {
          config_ids: form.large_medical_insurance_configs || []
        })
        
        showCreateDialog.value = false
        loadProjects() // 刷新列表
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
    }
  })
}

const handleDialogClose = () => {
  isEdit.value = false
  isProjectCodeManuallyEdited.value = false
  Object.assign(form, {
    name: '',
    code: '',
    description: '',
    status: 'active',
    social_security_location: '',
    insurance_types: [],
    salary_payment_date: null,
    requires_attendance: true,
    requires_salary_basis: false,
    requires_attendance_basis: false,
    registration_form_type: 'onboarding',
    delivery_frequency: 'monthly',
    delivery_method: 'electronic',
    delivery_requirements: []
  })
  formRef.value?.resetFields()
}

const getStatusType = (status) => {
  const types = {
    active: 'success',
    completed: 'info',
    inactive: 'danger'
  }
  return types[status] || 'info'
}

const getStatusText = (status) => {
  const texts = {
    active: '进行中',
    completed: '已完成',
    inactive: '已停用'
  }
  return texts[status] || '未知'
}

const getDeliveryFrequencyText = (frequency) => {
  const texts = {
    monthly: '月度',
    quarterly: '季度'
  }
  return texts[frequency] || '未知'
}

const getDeliveryMethodText = (method) => {
  const texts = {
    express: '快递',
    electronic: '电子'
  }
  return texts[method] || '未知'
}

const formatFileSize = (size) => {
  if (!size) return '-'
  if (size < 1024) return size + ' B'
  if (size < 1024 * 1024) return (size / 1024).toFixed(1) + ' KB'
  if (size < 1024 * 1024 * 1024) return (size / (1024 * 1024)).toFixed(1) + ' MB'
  return (size / (1024 * 1024 * 1024)).toFixed(1) + ' GB'
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


// 加载所有可用的地区和保险数据
const loadAvailableRegions = async () => {
  try {
    // 并行加载所有数据
    const [
      socialSecurityResponse, 
      housingFundResponse, 
      medicalInsuranceResponse,
      otherInsuranceResponse
    ] = await Promise.all([
      getAvailableSocialSecurityRegions(),
      getAvailableHousingFundRegions(),
      getAvailableMedicalInsuranceRegions(),
      getAvailableOtherInsurancePolicies()
    ])
    
    if (socialSecurityResponse.success) {
      availableSocialSecurityRegions.value = socialSecurityResponse.data || []
    }
    
    if (housingFundResponse.success) {
      availableHousingFundRegions.value = housingFundResponse.data || []
    }
    
    if (medicalInsuranceResponse.success) {
      availableMedicalInsuranceRegions.value = medicalInsuranceResponse.data || []
    }
    
    if (otherInsuranceResponse.success) {
      availableOtherInsurancePolicies.value = otherInsuranceResponse.data || []
    }
    
    // 加载大额医疗保险配置
    await loadAvailableLargeMedicalInsuranceConfigs()
    
    console.log('地区和保险数据加载完成:', {
      socialSecurity: availableSocialSecurityRegions.value.length,
      housingFund: availableHousingFundRegions.value.length,
      medicalInsurance: availableMedicalInsuranceRegions.value.length,
      otherInsurance: availableOtherInsurancePolicies.value.length,
      largeMedical: availableLargeMedicalInsuranceConfigs.value.length
    })
  } catch (error) {
    console.error('加载地区数据失败:', error)
    ElMessage.error('加载地区数据失败')
  }
}

onMounted(async () => {
  // 先初始化账套信息
  await accountSetStore.loadMyAccountSets()
  // 然后加载项目
  loadProjects()
})

// 监听账套切换，自动刷新数据
watch(() => accountSetStore.currentAccountSetId, (newAccountSetId, oldAccountSetId) => {
  console.log('项目页-账套变化检测:', { new: newAccountSetId, old: oldAccountSetId })
  if (newAccountSetId && oldAccountSetId && newAccountSetId !== oldAccountSetId) {
    console.log('✅ 项目页-账套切换，重新加载数据:', newAccountSetId)
    loadProjects()
  }
})
</script>

<style scoped>
.projects-page {
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

:deep(.el-table) {
  font-size: 14px;
}

:deep(.el-form-item) {
  margin-bottom: 20px;
}

/* 合同模板管理样式 */
.template-management {
  padding: 20px 0;
}

.template-header {
  margin-bottom: 20px;
}

.template-header h3 {
  margin: 0 0 10px 0;
  color: #303133;
}

.template-desc {
  margin: 0;
  color: #606266;
  font-size: 14px;
}

.template-type-content {
  padding: 20px 0;
}

.template-actions {
  margin-bottom: 20px;
}

.form-tip {
  margin-left: 10px;
  color: #909399;
  font-size: 12px;
}

.template-selector {
  width: 100%;
}

.template-selector .form-tip {
  margin-left: 0;
  margin-top: 5px;
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
  line-height: 1.4;
}

/* 新的合同模板管理样式 */
.template-manager {
  border: 1px solid #e4e7ed;
  border-radius: 4px;
  padding: 15px;
  background-color: #fafafa;
}

.template-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  padding-bottom: 10px;
  border-bottom: 1px solid #e4e7ed;
}

.template-title {
  font-weight: 500;
  color: #303133;
}

.template-list {
  min-height: 60px;
}

.template-items {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.template-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 15px;
  background-color: #fff;
  border: 1px solid #e4e7ed;
  border-radius: 4px;
  transition: all 0.3s;
}

.template-item:hover {
  border-color: #409eff;
  box-shadow: 0 2px 4px rgba(64, 158, 255, 0.1);
}

.template-item.is-default {
  border-color: #67c23a;
  background-color: #f0f9ff;
}

.template-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.template-name {
  font-weight: 500;
  color: #303133;
}

.template-actions {
  display: flex;
  gap: 5px;
}

.template-empty {
  text-align: center;
  padding: 20px;
}

.template-empty .el-empty {
  padding: 0;
}

/* 保单选择对话框样式 */
.policy-selection-container {
  max-height: 600px;
  overflow-y: auto;
}

.policy-type-list {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.policy-type-item {
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  padding: 16px;
  background-color: #fafbfc;
}

.policy-type-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
  padding: 12px;
  border-bottom: 2px solid #409eff;
  cursor: pointer;
  border-radius: 6px;
  transition: all 0.3s;
  background-color: #f8f9fa;
}

.policy-type-header:hover {
  background-color: #e3f2fd;
}

.policy-type-header .el-icon {
  font-size: 20px;
  color: #409eff;
}

.expand-icon {
  transition: transform 0.3s;
  font-size: 16px !important;
}

.expand-icon.expanded {
  transform: rotate(90deg);
}

.policy-type-header .type-name {
  font-size: 16px;
  font-weight: 600;
  color: #303133;
  flex: 1;
}

.policy-count {
  font-size: 14px;
  color: #909399;
  margin-left: 8px;
}

.policy-list-container {
  padding: 0 12px 12px 12px;
}

.policy-list {
  width: 100%;
}

.policy-radio-item {
  width: 100%;
  margin: 8px 0;
  padding: 30px 16px;
  border: 1px solid #e4e7ed;
  border-radius: 6px;
  background-color: #fff;
  transition: all 0.3s;
}

.policy-radio-item:hover {
  border-color: #409eff;
  background-color: #f0f9ff;
}

.policy-radio-item.is-checked {
  border-color: #409eff;
  background-color: #ecf5ff;
}

.policy-info {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: 100%;
}

.policy-name {
  font-size: 15px;
  font-weight: 500;
  color: #303133;
}

.policy-details {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  font-size: 13px;
  color: #606266;
  line-height: 1.6;
}

.policy-details span {
  padding: 2px 8px;
  background-color: #f5f7fa;
  border-radius: 4px;
}

/* 资料配置内联样式 */
.document-config-inline {
  width: 100%;
}

.document-toolbar {
  margin-bottom: 15px;
}

.section-divider {
  margin: 30px 0 20px 0;
}

/* 占位符字段配置样式 */
.placeholder-fields-config {
  padding: 10px 0;
}

.field-selection {
  padding: 10px;
  background: #fafafa;
  border-radius: 8px;
}

.field-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}

.field-grid .el-checkbox {
  margin: 0;
  width: 100%;
}

.field-grid .el-checkbox :deep(.el-checkbox__label) {
  font-size: 13px;
}

.selected-preview {
  margin-top: 20px;
}

.selected-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}
</style>
