<template>
  <div class="insurance-change-container">
    <div class="page-header">
      <h2>参保增减管理</h2>
      <!-- 按钮已隐藏 -->
      <!-- <div class="header-actions">
        <el-button type="primary" @click="generateSummaryAction">
          <el-icon><DocumentAdd /></el-icon>
          生成汇总表
        </el-button>
        <el-button type="success" @click="exportSummaryAction">
          <el-icon><Download /></el-icon>
          导出汇总表
        </el-button>
      </div> -->
    </div>

    <!-- 标签页 -->
    <el-tabs v-model="activeTab" type="card" class="tabs-container" @tab-change="handleTabChange">
      <!-- 参保人员管理 -->
      <el-tab-pane label="参保人员管理" name="changes">
        <div class="tab-content">
          <!-- 筛选条件 -->
          <el-card class="filter-card">
            <el-form :model="filterForm" inline>
              <el-form-item label="月份">
                <el-date-picker
                  v-model="filterForm.month"
                  type="month"
                  placeholder="选择月份"
                  format="YYYY年MM月"
                  value-format="YYYY-MM"
                  style="width: 200px"
                />
              </el-form-item>
              <el-form-item label="状态">
                <el-select v-model="filterForm.status" placeholder="请选择状态" clearable style="width: 200px">
                  <el-option label="全部" value="" />
                  <el-option label="待处理" value="pending" />
                  <el-option label="已处理" value="completed" />
                </el-select>
              </el-form-item>
              <el-form-item label="地区">
                <el-select v-model="filterForm.region_name" placeholder="请选择地区" clearable style="width: 200px">
                  <el-option label="全部" value="" />
                  <el-option 
                    v-for="region in regions" 
                    :key="region" 
                    :label="region" 
                    :value="region" 
                  />
                </el-select>
              </el-form-item>
              <el-form-item>
                <el-button type="primary" @click="loadChanges">查询</el-button>
                <el-button @click="resetFilter">重置</el-button>
              </el-form-item>
            </el-form>
          </el-card>

          <!-- 参保人员列表 -->
          <el-card class="table-card">
            <template #header>
              <div class="card-header">
                <span>参保人员列表</span>
                <div class="header-actions">
                  <span class="total-count">共 {{ changes.length }} 条记录</span>
                  <!-- 生成参保登记表按钮 - 已隐藏 -->
                  <!--
                  <el-button 
                    type="primary" 
                    @click="generateRegistrationReports"
                    :disabled="selectedTasks.length === 0 || isGeneratingReports"
                    :loading="isGeneratingReports"
                  >
                    <el-icon><Document /></el-icon>
                    生成参保登记表
                  </el-button>
                  -->
                  <!-- <el-button type="success" @click="showExportDialog = true">
                    <el-icon><Download /></el-icon>
                    导出数据
                  </el-button> -->
                </div>
              </div>
            </template>

            <el-table 
              :data="changes" 
              v-loading="loading" 
              stripe
              @selection-change="handleTaskSelectionChange"
            >
              <el-table-column type="selection" width="55" :selectable="isTaskSelectable" />
              <el-table-column prop="employee.name" label="员工姓名" width="120" />
              <el-table-column label="增减类型" width="100">
                <template #default="{ row }">
                  <!-- 如果有变更摘要，说明是配置变更，不是真正的新增或减少 -->
                  <el-tag v-if="row.change_summary" type="warning">
                    配置变更
                  </el-tag>
                  <el-tag v-else-if="row.change_type === 'decrease'" type="danger">
                    减少参保
                  </el-tag>
                  <el-tag v-else type="success">
                    新增参保
                  </el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="project.name" label="项目名称" width="150" />
              <!-- 参保地区列已隐藏 -->
              <el-table-column label="状态" width="220">
                <template #default="{ row }">
                  <div style="display: flex; flex-direction: column; gap: 5px;">
                    <el-tag :type="getStatusTagType(row.status)">
                      {{ getStatusText(row.status) }}
                    </el-tag>
                    <!-- 显示变更标记（所有状态都显示，只要有变更摘要） -->
                    <div v-if="row.change_summary" style="display: flex; flex-wrap: wrap; gap: 4px;">
                      <el-tag v-if="row.change_summary.includes('社保')" type="success" size="small" effect="dark">
                        🟢 社保变更
                      </el-tag>
                      <el-tag v-if="row.change_summary.includes('医保')" type="primary" size="small" effect="dark">
                        🔵 医保变更
                      </el-tag>
                      <el-tag v-if="row.change_summary.includes('公积金')" type="warning" size="small" effect="dark">
                        🟡 公积金变更
                      </el-tag>
                      <el-tag v-if="row.change_summary.includes('大额')" type="info" size="small" effect="dark">
                        🟣 大额医疗变更
                      </el-tag>
                      <el-tag v-if="row.change_summary.includes('其他保险')" type="danger" size="small" effect="dark">
                        🔴 其他保险变更
                      </el-tag>
                      <!-- 如果没有匹配到具体类型，显示通用变更标记 -->
                      <el-tag 
                        v-if="!row.change_summary.includes('社保') && !row.change_summary.includes('医保') && !row.change_summary.includes('公积金') && !row.change_summary.includes('大额') && !row.change_summary.includes('其他保险')" 
                        type="danger" 
                        size="small" 
                        effect="dark"
                      >
                        <el-icon style="margin-right: 2px;"><Warning /></el-icon>
                        有变更
                      </el-tag>
                    </div>
                  </div>
                </template>
              </el-table-column>
              <el-table-column label="附件" width="120">
                <template #default="{ row }">
                  <div style="display: flex; flex-direction: column; gap: 4px;">
                    <el-tag v-if="row.attachments && row.attachments.length > 0" type="success" size="small">
                      {{ row.attachments.length }}个文件
                    </el-tag>
                    <el-tag v-else type="info" size="small">无附件</el-tag>
                    <span v-if="row.attachment_uploaded_at" style="font-size: 12px; color: #909399;">
                      {{ formatDate(row.attachment_uploaded_at) }}
                    </span>
                  </div>
                </template>
              </el-table-column>
              <el-table-column prop="processed_at" label="处理时间" width="180">
                <template #default="{ row }">
                  {{ row.processed_at ? formatDate(row.processed_at) : '-' }}
                </template>
              </el-table-column>
              <el-table-column label="操作" width="280" fixed="right">
                <template #default="{ row }">
                  <!-- 待处理状态或旧状态但有附件时，显示上传和确认处理按钮 -->
                  <el-button 
                    v-if="row.status === 'pending'"
                    type="primary" 
                    size="small" 
                    @click="showUploadDialog(row)"
                  >
                    上传附件
                  </el-button>
                  <el-button 
                    v-if="(row.status === 'pending' || row.status === 'submitted') && row.attachments && row.attachments.length > 0"
                    type="success" 
                    size="small" 
                    :loading="processing"
                    :disabled="processing"
                    @click="confirmProcess(row)"
                  >
                    确认处理
                  </el-button>
                  <el-button 
                    v-if="!row.fully_confirmed && !row.other_insurance_processed && (row.status === 'pending' || row.status === 'submitted' || row.status === 'completed')"
                    type="warning" 
                    size="small" 
                    :loading="processingOtherInsurance"
                    :disabled="processingOtherInsurance"
                    @click="confirmOtherInsuranceOnly(row)"
                  >
                    其他保险确认处理
                  </el-button>
                  <el-button 
                    v-if="row.attachments && row.attachments.length > 0"
                    type="info" 
                    size="small" 
                    @click="showViewFilesDialog(row)"
                  >
                    查看文件
                  </el-button>
                  <el-button 
                    type="info" 
                    size="small" 
                    @click="viewDetails(row)"
                  >
                    查看详情
                  </el-button>
                </template>
              </el-table-column>
            </el-table>
          </el-card>
        </div>
      </el-tab-pane>

      <!-- 参保明细 -->
      <el-tab-pane label="参保明细" name="details">
        <div class="tab-content">
          <!-- 筛选条件 -->
          <el-card class="filter-card">
            <el-form :model="detailFilterForm" inline>
              <el-form-item label="月份">
                <el-date-picker
                  v-model="detailFilterForm.month"
                  type="month"
                  placeholder="选择月份"
                  format="YYYY年MM月"
                  value-format="YYYY-MM"
                  style="width: 200px"
                />
              </el-form-item>
              <el-form-item label="地区">
                <el-select v-model="detailFilterForm.region_name" placeholder="请选择地区" clearable style="width: 200px">
                  <el-option label="全部" value="" />
                  <el-option 
                    v-for="region in regions" 
                    :key="region" 
                    :label="region" 
                    :value="region" 
                  />
                </el-select>
              </el-form-item>
              <el-form-item>
                <el-button type="primary" @click="loadDetails">查询</el-button>
                <el-button @click="resetDetailFilter">重置</el-button>
              </el-form-item>
            </el-form>
          </el-card>

          <!-- 明细分类标签页 -->
          <el-tabs v-model="detailActiveTab" type="card" class="detail-tabs">
            <!-- 社保明细 -->
            <el-tab-pane label="社保明细" name="social">
              <div class="detail-tab-content">
                <el-card class="table-card">
                  <template #header>
                    <div class="card-header">
                      <span>社保明细（包括医保、社保、大额医疗保险）</span>
                      <div class="card-header-actions">
                        <span class="total-count">共 {{ socialSecurityDetails.length }} 条记录</span>
                        <div style="display: flex; gap: 8px;">
                          <el-button 
                            type="primary" 
                            size="small" 
                            @click="generateSummaryTable"
                            :loading="summaryLoading"
                          >
                            <el-icon><Document /></el-icon>
                            生成汇总表
                          </el-button>
                          <el-button 
                            type="success" 
                            size="small" 
                            @click="exportSocialSecurityExcel"
                            :loading="exportLoading"
                          >
                            <el-icon><Download /></el-icon>
                            导出Excel
                          </el-button>
                        </div>
                      </div>
                    </div>
                  </template>
                  
                  <el-table 
                    :data="socialSecurityDetailsWithTitle" 
                    size="small" 
                    border 
                    class="detail-table"
                    :span-method="socialSecuritySpanMethod"
                  >
                    <el-table-column prop="serial_number" label="序号" width="60" align="center">
                      <template #default="{ row, $index }">
                        <template v-if="row.isTitleRow">
                          <div class="table-title">{{ row.title }}</div>
                        </template>
                        <template v-else>
                          {{ $index }}
                        </template>
                      </template>
                    </el-table-column>
                    <el-table-column prop="employee_name" label="姓名" width="100">
                      <template #default="{ row }">
                        {{ row.employee_name === 'NaN' || !row.employee_name ? '小计' : row.employee_name }}
                      </template>
                    </el-table-column>
                    <el-table-column prop="id_number" label="身份证号" width="180" />
                    <el-table-column prop="project_name" label="项目" width="120" />
                    <el-table-column prop="enrollment_date" label="参保日期" width="100" align="center" />
                    <el-table-column prop="type" label="类型" width="80" align="center">
                      <template #default="{ row }">
                        <el-tag :type="row.type === '正常' ? 'success' : 'warning'" size="small">
                          {{ row.type }}
                        </el-tag>
                      </template>
                    </el-table-column>
                    <el-table-column prop="period" label="费款所属期" width="100" align="center" />
                    <el-table-column prop="medical_base" label="医保基数" width="100" align="right">
                      <template #default="{ row }">
                        <span class="base-amount">{{ row.medical_base }}</span>
                      </template>
                    </el-table-column>
                    <el-table-column prop="social_security_base" label="社保基数" width="100" align="right">
                      <template #default="{ row }">
                        <span class="base-amount">{{ row.social_security_base }}</span>
                      </template>
                    </el-table-column>
                    
                    <!-- 动态单位部分列 -->
                    <el-table-column label="单位部分" align="center">
                      <template v-for="column in dynamicCompanyColumns" :key="'company_' + column.name">
                        <el-table-column 
                          :prop="'company_' + (column.fieldPrefix || '') + column.name" 
                          :label="column.name" 
                          width="120" 
                          align="right"
                        >
                          <template #default="{ row }">
                            {{ row['company_' + (column.fieldPrefix || '') + column.name] || '0.00' }}
                          </template>
                        </el-table-column>
                      </template>
                      <el-table-column prop="company_total" label="单位缴纳保险合计" width="150" align="right">
                        <template #default="{ row }">
                          <span class="total-amount">{{ row.company_total }}</span>
                        </template>
                      </el-table-column>
                    </el-table-column>
                    
                    <!-- 动态个人部分列 -->
                    <el-table-column label="个人部分" align="center">
                      <template v-for="column in dynamicEmployeeColumns" :key="'employee_' + column.name">
                        <el-table-column 
                          :prop="'employee_' + (column.fieldPrefix || '') + column.name" 
                          :label="column.name" 
                          width="120" 
                          align="right"
                        >
                          <template #default="{ row }">
                            {{ row['employee_' + (column.fieldPrefix || '') + column.name] || '0.00' }}
                          </template>
                        </el-table-column>
                      </template>
                      <el-table-column prop="employee_total" label="个人缴纳保险合计" width="150" align="right">
                        <template #default="{ row }">
                          <span class="total-amount">{{ row.employee_total }}</span>
                        </template>
                      </el-table-column>
                    </el-table-column>
                    
                    <el-table-column prop="social_security_total" label="社保合计" width="120" align="right">
                      <template #default="{ row }">
                        <span class="grand-total">{{ row.social_security_total }}</span>
                      </template>
                    </el-table-column>
                    <el-table-column prop="remarks" label="备注" width="100" />
                  </el-table>
                </el-card>
              </div>
            </el-tab-pane>

            <!-- 社保补交明细 -->
            <el-tab-pane label="社保补交明细" name="compensation">
              <div class="detail-tab-content">
                <el-card class="table-card">
                  <template #header>
                    <div class="card-header">
                      <span>社保补交明细（仅显示社保基数补差数据）</span>
                      <div class="card-header-actions">
                        <span class="total-count">共 {{ compensationDetails.length }} 条记录</span>
                        <div style="display: flex; gap: 8px;">
                          <el-button 
                            type="success" 
                            size="small" 
                            @click="exportCompensationExcel"
                            :loading="exportLoading"
                          >
                            <el-icon><Download /></el-icon>
                            导出Excel
                          </el-button>
                        </div>
                      </div>
                    </div>
                  </template>
                  
                  <el-table 
                    :data="compensationDetails" 
                    size="small" 
                    border 
                    class="detail-table"
                  >
                    <el-table-column prop="serial_number" label="序号" width="60" align="center" />
                    <el-table-column prop="employee_name" label="姓名" width="100" />
                    <el-table-column prop="id_number" label="身份证号" width="180" />
                    <el-table-column prop="project_name" label="项目" width="120" />
                    <el-table-column prop="compensation_period" label="补差时段" width="180" align="center" />
                    <el-table-column prop="compensation_months" label="补差月数" width="90" align="center" />
                    <el-table-column prop="old_base" label="旧基数" width="100" align="right">
                      <template #default="{ row }">
                        <span class="base-amount">{{ row.old_base }}</span>
                      </template>
                    </el-table-column>
                    <el-table-column prop="new_base" label="新基数" width="100" align="right">
                      <template #default="{ row }">
                        <span class="base-amount">{{ row.new_base }}</span>
                      </template>
                    </el-table-column>
                    <el-table-column prop="type" label="类型" width="80" align="center">
                      <template #default>
                        <el-tag type="warning" size="small">补差</el-tag>
                      </template>
                    </el-table-column>
                    
                    <!-- 动态单位部分列 -->
                    <el-table-column label="单位部分" align="center">
                      <template v-for="column in dynamicCompensationColumns" :key="'company_' + column.name">
                        <el-table-column 
                          :prop="'company_' + column.name" 
                          :label="column.name" 
                          width="120" 
                          align="right"
                        >
                          <template #default="{ row }">
                            {{ row['company_' + column.name] || '0.00' }}
                          </template>
                        </el-table-column>
                      </template>
                      <el-table-column prop="company_total" label="单位补差合计" width="150" align="right">
                        <template #default="{ row }">
                          <span class="total-amount">{{ row.company_total }}</span>
                        </template>
                      </el-table-column>
                    </el-table-column>
                    
                    <!-- 动态个人部分列 -->
                    <el-table-column label="个人部分" align="center">
                      <template v-for="column in dynamicCompensationColumns" :key="'employee_' + column.name">
                        <el-table-column 
                          :prop="'employee_' + column.name" 
                          :label="column.name" 
                          width="120" 
                          align="right"
                        >
                          <template #default="{ row }">
                            {{ row['employee_' + column.name] || '0.00' }}
                          </template>
                        </el-table-column>
                      </template>
                      <el-table-column prop="employee_total" label="个人补差合计" width="150" align="right">
                        <template #default="{ row }">
                          <span class="total-amount">{{ row.employee_total }}</span>
                        </template>
                      </el-table-column>
                    </el-table-column>
                    
                    <el-table-column prop="total" label="补差总计" width="120" align="right">
                      <template #default="{ row }">
                        <span class="grand-total">{{ row.total }}</span>
                      </template>
                    </el-table-column>
                  </el-table>
                  
                  <!-- 无数据提示 -->
                  <el-empty v-if="!compensationDetails || compensationDetails.length === 0" description="暂无补差数据" style="margin-top: 40px;" />
                </el-card>
              </div>
            </el-tab-pane>

            <!-- 公积金补交明细 -->
            <el-tab-pane label="公积金补交明细" name="housingFundCompensation">
              <div class="detail-tab-content">
                <el-card class="table-card">
                  <template #header>
                    <div class="card-header">
                      <span>公积金补交明细（仅显示公积金基数补差数据）</span>
                      <div class="card-header-actions">
                        <span class="total-count">共 {{ housingFundCompensationDetails.length }} 条记录</span>
                      </div>
                    </div>
                  </template>
                  
                  <el-table 
                    :data="housingFundCompensationDetails" 
                    size="small" 
                    border 
                    class="detail-table"
                  >
                    <el-table-column prop="serial_number" label="序号" width="60" align="center" />
                    <el-table-column prop="employee_name" label="姓名" width="100" />
                    <el-table-column prop="id_number" label="身份证号" width="180" />
                    <el-table-column prop="project_name" label="项目" width="120" />
                    <el-table-column prop="compensation_period" label="补差时段" width="180" align="center" />
                    <el-table-column prop="compensation_months" label="补差月数" width="90" align="center" />
                    <el-table-column prop="old_base" label="旧基数" width="100" align="right" />
                    <el-table-column prop="new_base" label="新基数" width="100" align="right" />
                    <el-table-column prop="company_amount" label="单位补差" width="120" align="right" />
                    <el-table-column prop="employee_amount" label="个人补差" width="120" align="right" />
                    <el-table-column prop="company_total" label="单位补差合计" width="150" align="right" />
                    <el-table-column prop="employee_total" label="个人补差合计" width="150" align="right" />
                    <el-table-column prop="total" label="补差总计" width="120" align="right" />
                  </el-table>
                  
                  <!-- 无数据提示 -->
                  <el-empty v-if="!housingFundCompensationDetails || housingFundCompensationDetails.length === 0" description="暂无公积金补差数据" style="margin-top: 40px;" />
                </el-card>
              </div>
            </el-tab-pane>

            <!-- 公积金明细 -->
            <el-tab-pane label="公积金明细" name="housing">
              <div class="detail-tab-content">
                <el-card class="table-card">
                  <template #header>
                    <div class="card-header">
                      <div class="header-left">
                        <span>公积金明细</span>
                        <span class="total-count">共 {{ housingFundDetails.length }} 条记录</span>
                      </div>
                      <div class="header-right">
                        <el-button
                          type="primary"
                          size="small"
                          @click="exportHousingFundSummaryAction"
                          :loading="exportLoading"
                        >
                          <el-icon><Download /></el-icon>
                          导出汇总表
                        </el-button>
                        <el-button
                          type="success"
                          size="small"
                          @click="exportHousingFundExcel"
                          :loading="exportLoading"
                          style="margin-left: 10px"
                        >
                          <el-icon><Download /></el-icon>
                          导出明细Excel
                        </el-button>
                      </div>
                    </div>
                  </template>
                  
                  <el-table 
                    :data="housingFundDetailsWithTitle" 
                    size="small" 
                    border 
                    class="detail-table"
                    :span-method="housingFundSpanMethod"
                  >
                    <el-table-column prop="serial_number" label="序号" width="60" align="center">
                      <template #default="{ row, $index }">
                        <template v-if="row.isTitleRow">
                          <div class="table-title">{{ row.title }}</div>
                        </template>
                        <template v-else>
                          {{ $index }}
                        </template>
                      </template>
                    </el-table-column>
                    <el-table-column prop="employee_name" label="姓名" width="100">
                      <template #default="{ row }">
                        {{ row.employee_name === 'NaN' || !row.employee_name ? '小计' : row.employee_name }}
                      </template>
                    </el-table-column>
                    <el-table-column prop="id_number" label="身份证号" width="180" />
                    <el-table-column prop="project_name" label="项目" width="120" />
                    <el-table-column prop="enrollment_date" label="参保日期" width="100" align="center" />
                    <el-table-column prop="type" label="类型" width="80" align="center">
                      <template #default="{ row }">
                        <el-tag :type="row.type === '正常' ? 'success' : 'warning'" size="small">
                          {{ row.type }}
                        </el-tag>
                      </template>
                    </el-table-column>
                    <el-table-column prop="period" label="费款所属期" width="100" align="center" />
                    <el-table-column prop="housing_fund_base" label="公积金基数" width="120" align="right">
                      <template #default="{ row }">
                        <span class="base-amount">{{ row.housing_fund_base }}</span>
                      </template>
                    </el-table-column>
                    <el-table-column prop="ratio" label="比例" width="80" align="right">
                      <template #default="{ row }">
                        <span class="ratio-amount">{{ row.ratio }}</span>
                      </template>
                    </el-table-column>
                    <el-table-column prop="company_portion" label="单位部分" width="120" align="right" />
                    <el-table-column prop="employee_portion" label="个人部分" width="120" align="right" />
                    <el-table-column prop="housing_fund_total" label="公积金合计" width="120" align="right">
                      <template #default="{ row }">
                        <span class="grand-total">{{ row.housing_fund_total }}</span>
                      </template>
                    </el-table-column>
                    <el-table-column prop="remarks" label="备注" width="100" />
                  </el-table>
                </el-card>
              </div>
            </el-tab-pane>

            <!-- 其他保险明细 -->
            <el-tab-pane label="其他保险明细" name="other">
              <div class="detail-tab-content">
                <!-- 按保险种类分组显示 -->
                <div v-if="Object.keys(otherInsuranceDetails).length > 0">
                  <el-card 
                    v-for="(group, insuranceType) in otherInsuranceDetails" 
                    :key="insuranceType"
                    class="table-card insurance-group-card"
                    style="margin-bottom: 20px;"
                  >
                    <template #header>
                      <div class="card-header">
                        <div class="group-header">
                          <span class="insurance-type-title">{{ insuranceType }}</span>
                          <span class="group-count">共 {{ group.policies.length }} 人</span>
                        </div>
                        <div class="group-actions">
                          <el-button 
                            type="primary" 
                            size="small" 
                            @click="exportInsuranceGroup(insuranceType, group.policies)"
                          >
                            导出Excel
                          </el-button>
                        </div>
                      </div>
                    </template>
                    
                    <el-table 
                      :data="group.policies" 
                      size="small" 
                      border 
                      class="detail-table"
                    >
                      <el-table-column prop="serial_number" label="序号" width="60" align="center" />
                      <el-table-column prop="employee_name" label="姓名" width="100" />
                      <el-table-column prop="id_number" label="身份证号码" width="180" />
                      <el-table-column prop="gender" label="性别" width="80" align="center" />
                      <el-table-column prop="age" label="年龄" width="80" align="center" />
                      <el-table-column prop="contact_phone" label="联系电话" width="120" />
                      <el-table-column prop="project_name" label="项目" width="120" />
                      <el-table-column prop="addition_date" label="增加日期" width="100" align="center">
                        <template #default="{ row }">
                          {{ row.addition_date ? formatDate(row.addition_date) : '-' }}
                        </template>
                      </el-table-column>
                      <el-table-column prop="expiration_date" label="到期" width="100" align="center">
                        <template #default="{ row }">
                          {{ row.expiration_date ? formatDate(row.expiration_date) : '-' }}
                        </template>
                      </el-table-column>
                      <el-table-column prop="type" label="类型" width="80" align="center">
                        <template #default="{ row }">
                          <el-tag :type="row.type === '新增' ? 'success' : 'warning'" size="small">
                            {{ row.type }}
                          </el-tag>
                        </template>
                      </el-table-column>
                      <el-table-column prop="insurance_status" label="状态" width="80" align="center">
                        <template #default="{ row }">
                          <el-tag :type="row.insurance_status === '在保' ? 'success' : 'danger'" size="small">
                            {{ row.insurance_status }}
                          </el-tag>
                        </template>
                      </el-table-column>
                      <el-table-column prop="employment_status" label="状态" width="80" align="center">
                        <template #default="{ row }">
                          <el-tag :type="row.employment_status === '在职' ? 'success' : 'warning'" size="small">
                            {{ row.employment_status }}
                          </el-tag>
                        </template>
                      </el-table-column>
                      <el-table-column prop="amount" label="金额" width="120" align="right">
                        <template #default="{ row }">
                          <span class="amount-value">¥{{ row.amount }}</span>
                        </template>
                      </el-table-column>
                      <el-table-column prop="replaced_person_name" label="替换人员" width="120" align="center">
                        <template #default="{ row }">
                          <el-tag v-if="row.replaced_person_name" type="info" size="small">
                            {{ row.replaced_person_name }}
                          </el-tag>
                          <span v-else class="text-gray-400">-</span>
                        </template>
                      </el-table-column>
                      <el-table-column prop="endorsement_number" label="批单号" width="120" align="center">
                        <template #default="{ row }">
                          <span v-if="row.endorsement_number">{{ row.endorsement_number }}</span>
                          <span v-else class="text-gray-400">-</span>
                        </template>
                      </el-table-column>
                      <el-table-column prop="remarks" label="备注" width="100" />
                    </el-table>
                  </el-card>
                </div>
                
                <!-- 无数据提示 -->
                <el-empty v-else description="暂无其他保险明细数据" />
              </div>
            </el-tab-pane>
          </el-tabs>
        </div>
      </el-tab-pane>

      <!-- 汇总统计（已隐藏） -->
      <el-tab-pane v-if="false" label="汇总统计" name="summaries">
        <div class="tab-content">
          <!-- 筛选条件 -->
          <el-card class="filter-card">
            <el-form :model="summaryFilterForm" inline>
              <el-form-item label="地区">
                <el-select v-model="summaryFilterForm.region_name" placeholder="请选择地区" clearable style="width: 200px">
                  <el-option label="全部" value="" />
                  <el-option 
                    v-for="region in regions" 
                    :key="region" 
                    :label="region" 
                    :value="region" 
                  />
                </el-select>
              </el-form-item>
              <el-form-item>
                <el-button type="primary" @click="loadSummaries">查询</el-button>
                <el-button @click="resetSummaryFilter">重置</el-button>
              </el-form-item>
            </el-form>
          </el-card>

          <!-- 汇总列表 -->
          <el-card class="table-card">
            <template #header>
              <div class="card-header">
                <span>汇总统计列表</span>
                <span class="total-count">共 {{ summaries.length }} 条记录</span>
              </div>
            </template>

            <el-table :data="summaries" v-loading="summaryLoading" stripe>
              <el-table-column prop="region_name" label="地区" width="120" />
              <el-table-column prop="insurance_type_text" label="保险类型" width="100" />
              <el-table-column prop="insurance_name" label="保险名称" width="150" />
              <el-table-column prop="employee_count" label="参保人数" width="100" />
              <el-table-column prop="total_base_amount" label="总基数" width="120">
                <template #default="{ row }">
                  ¥{{ row.total_base_amount }}
                </template>
              </el-table-column>
              <el-table-column prop="total_employee_amount" label="员工总缴纳" width="120">
                <template #default="{ row }">
                  ¥{{ row.total_employee_amount }}
                </template>
              </el-table-column>
              <el-table-column prop="total_company_amount" label="公司总缴纳" width="120">
                <template #default="{ row }">
                  ¥{{ row.total_company_amount }}
                </template>
              </el-table-column>
              <el-table-column prop="total_amount" label="总缴纳金额" width="120">
                <template #default="{ row }">
                  ¥{{ row.total_amount }}
                </template>
              </el-table-column>
              <el-table-column prop="summary_date" label="汇总日期" width="120">
                <template #default="{ row }">
                  {{ formatDate(row.summary_date) }}
                </template>
              </el-table-column>
            </el-table>
          </el-card>
        </div>
      </el-tab-pane>
    </el-tabs>

    <!-- 查看文件对话框 -->
    <el-dialog
      v-model="showViewFilesDialogFlag"
      title="查看文件"
      width="800px"
    >
      <el-form :model="viewFilesForm" ref="viewFilesFormRef" label-width="100px">
        <el-form-item label="员工姓名">
          <el-input :value="currentChange.employee ? currentChange.employee.name : ''" disabled />
        </el-form-item>
        <el-form-item label="项目名称">
          <el-input :value="currentChange.project ? currentChange.project.name : ''" disabled />
        </el-form-item>
        <!-- 文件列表 -->
        <el-form-item label="已上传文件" v-if="currentChange.attachments && currentChange.attachments.length > 0">
          <el-table :data="currentChange.attachments" size="small" border style="width: 100%">
            <el-table-column prop="original_name" label="文件名" min-width="200" />
            <el-table-column prop="file_size_formatted" label="大小" width="100" />
            <el-table-column prop="created_at" label="上传时间" width="160">
              <template #default="{ row }">
                <span v-date-time="row.created_at"></span>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="200">
              <template #default="{ row }">
                <el-button type="success" size="small" @click="handleDownloadAttachment(row)">
                  下载
                </el-button>
                <el-button type="primary" size="small" @click="handlePreviewAttachment(row)">
                  预览
                </el-button>
                <el-button 
                  v-if="currentChange.status === 'pending'"
                  type="danger" 
                  size="small" 
                  @click="handleDeleteAttachment(row)"
                >
                  删除
                </el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-form-item>
        <el-form-item v-else>
          <el-empty description="暂无文件" />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <div class="dialog-footer">
          <el-button @click="showViewFilesDialogFlag = false">关闭</el-button>
        </div>
      </template>
    </el-dialog>

    <!-- 上传附件对话框 -->
    <el-dialog
      v-model="showUploadDialogFlag"
      title="附件管理"
      width="800px"
    >
      <el-form :model="uploadForm" ref="uploadFormRef" label-width="100px">
        <el-form-item label="员工姓名">
          <el-input :value="currentChange.employee ? currentChange.employee.name : ''" disabled />
        </el-form-item>
        <el-form-item label="项目名称">
          <el-input :value="currentChange.project ? currentChange.project.name : ''" disabled />
        </el-form-item>
        <!-- 已上传的附件列表 -->
        <el-form-item label="已上传附件" v-if="currentChange.attachments && currentChange.attachments.length > 0">
          <el-table :data="currentChange.attachments" size="small" border style="width: 100%">
            <el-table-column prop="original_name" label="文件名" min-width="200" />
            <el-table-column prop="file_size_formatted" label="大小" width="100" />
            <el-table-column prop="created_at" label="上传时间" width="160">
              <template #default="{ row }">
                <span v-date-time="row.created_at"></span>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="200">
              <template #default="{ row }">
                <el-button type="success" size="small" @click="handleDownloadAttachment(row)">
                  下载
                </el-button>
                <el-button type="primary" size="small" @click="handlePreviewAttachment(row)">
                  预览
                </el-button>
                <el-button type="danger" size="small" @click="handleDeleteAttachment(row)">
                  删除
                </el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-form-item>
        
        <!-- 上传新附件 -->
        <el-form-item label="上传新附件">
          <el-upload
            ref="uploadRef"
            :file-list="fileList"
            :on-change="handleFileChange"
            :on-remove="handleFileRemove"
            :on-exceed="handleFileExceed"
            :auto-upload="false"
            :limit="10"
            :multiple="true"
            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
          >
            <el-button type="primary">选择文件</el-button>
            <template #tip>
              <div class="el-upload__tip">
                支持多选，最多上传10个文件，每个文件不超过10MB
              </div>
            </template>
          </el-upload>
        </el-form-item>
      </el-form>
      
      <template #footer>
        <div class="dialog-footer">
          <el-button @click="showUploadDialogFlag = false">关闭</el-button>
          <el-button 
            type="primary" 
            @click="submitUpload" 
            :loading="uploading"
            :disabled="!fileList || fileList.length === 0"
          >
            上传所选文件
          </el-button>
        </div>
      </template>
    </el-dialog>

    <!-- 详情对话框 -->
    <el-dialog
      v-model="showDetailDialogFlag"
      title="参保详情"
      width="1000px"
    >
      <div v-if="currentChange" class="detail-content">
        <!-- 综合变更提示（所有状态都显示，只要有变更摘要） -->
        <el-alert
          v-if="currentChange.change_summary"
          :title="getAlertTitle()"
          :type="getAlertType()"
          style="margin-bottom: 20px;"
          show-icon
          :closable="false"
        >
          <template #default>
            <div style="margin-top: 10px;">
              <!-- 删除项目提示 -->
              <div v-if="hasDeletedItems()" style="margin-bottom: 12px;">
                <p style="margin: 0; color: #F56C6C; font-size: 14px;">
                  <strong>删除内容：</strong>以下保险项目已被删除
                </p>
                
                <!-- 删除项目列表 -->
                <div style="margin-top: 8px; padding: 8px; background: #fef0f0; border-radius: 4px; border: 1px solid #fbc4c4;">
                  <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                    <el-tag
                      v-for="(detail, index) in getDeletedItems()"
                      :key="'deleted-' + index"
                      type="danger"
                      effect="dark"
                      size="small"
                    >
                      <el-icon style="margin-right: 2px;"><Delete /></el-icon>
                      删除 {{ getCategoryText(detail.category) }}: {{ detail.item }}
                    </el-tag>
                  </div>
                </div>
              </div>
              
              <!-- 新增项目提示 -->
              <div v-if="hasAddedItems()" style="margin-bottom: 12px;">
                <p style="margin: 0; color: #67C23A; font-size: 14px;">
                  <strong>新增内容：</strong>以下保险项目已新增
                </p>
                
                <!-- 新增项目列表 -->
                <div style="margin-top: 8px; padding: 8px; background: #f0f9ff; border-radius: 4px; border: 1px solid #b3d8ff;">
                  <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                    <el-tag
                      v-for="(detail, index) in getAddedItems()"
                      :key="'added-' + index"
                      type="success"
                      effect="dark"
                      size="small"
                    >
                      <el-icon style="margin-right: 2px;"><Plus /></el-icon>
                      新增 {{ getCategoryText(detail.category) }}: {{ detail.item }}
                    </el-tag>
                  </div>
                </div>
              </div>
              
              <!-- 修改项目提示 -->
              <div v-if="hasModifiedItems()" style="margin-bottom: 12px;">
                <p style="margin: 0; color: #E6A23C; font-size: 14px;">
                  <strong>修改内容：</strong>以下保险项目已修改
                </p>
                
                <!-- 修改项目列表 -->
                <div style="margin-top: 8px; padding: 8px; background: #fdf6ec; border-radius: 4px; border: 1px solid #f5dab1;">
                  <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                    <el-tag
                      v-for="(detail, index) in getModifiedItems()"
                      :key="'modified-' + index"
                      type="warning"
                      effect="dark"
                      size="small"
                    >
                      <el-icon style="margin-right: 2px;"><Edit /></el-icon>
                      修改 {{ getCategoryText(detail.category) }}: {{ detail.item }}
                    </el-tag>
                  </div>
                </div>
              </div>
              
              <!-- 通用提示 -->
              <p style="margin: 12px 0 0 0; color: #909399; font-size: 12px;">
                <el-icon><Warning /></el-icon>
                检测到保险配置发生变化，状态已自动更新为"待处理"，请重新上传附件。
              </p>
            </div>
          </template>
        </el-alert>

        <!-- 基本信息 -->
        <el-descriptions :column="2" border style="margin-bottom: 20px;">
          <el-descriptions-item label="员工姓名">{{ currentChange.employee && currentChange.employee.name }}</el-descriptions-item>
          <el-descriptions-item label="项目名称">{{ currentChange.project && currentChange.project.name }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="getStatusTagType(currentChange.status)">
              {{ getStatusText(currentChange.status) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="创建时间">{{ formatDate(currentChange.created_at) }}</el-descriptions-item>
        </el-descriptions>

        <!-- 参保地区信息 -->
        <div v-if="hasRegionInfo()" class="insurance-region-info">
          <h4>参保地区信息</h4>
          <el-descriptions :column="2" size="small" border>
            <!-- 社保地区 -->
            <el-descriptions-item label="社保地区" v-if="currentChange.employee && currentChange.employee.social_security_region">
              <div style="display: flex; flex-direction: column; gap: 4px;">
                <span>{{ currentChange.employee.social_security_region.name }}</span>
                <span v-if="currentChange.employee.social_security_region.code" style="color: #909399; font-size: 12px;">
                  编号：{{ currentChange.employee.social_security_region.code }}
                </span>
              </div>
            </el-descriptions-item>
            <el-descriptions-item label="社保地区" v-else>
              <el-tag type="info" size="small">未设置</el-tag>
            </el-descriptions-item>

            <!-- 医保地区 -->
            <el-descriptions-item label="医保地区" v-if="currentChange.employee && currentChange.employee.medical_insurance_region">
              <div style="display: flex; flex-direction: column; gap: 4px;">
                <span>{{ currentChange.employee.medical_insurance_region.name }}</span>
                <span v-if="currentChange.employee.medical_insurance_region.code" style="color: #909399; font-size: 12px;">
                  编号：{{ currentChange.employee.medical_insurance_region.code }}
                </span>
              </div>
            </el-descriptions-item>
            <el-descriptions-item label="医保地区" v-else>
              <el-tag type="info" size="small">未设置</el-tag>
            </el-descriptions-item>

            <!-- 公积金地区 -->
            <el-descriptions-item label="公积金地区" v-if="currentChange.employee && currentChange.employee.housing_fund_region">
              <div style="display: flex; flex-direction: column; gap: 4px;">
                <span>{{ currentChange.employee.housing_fund_region.region_name }}</span>
                <span v-if="currentChange.employee.housing_fund_region.account_number" style="color: #909399; font-size: 12px;">
                  账号：{{ currentChange.employee.housing_fund_region.account_number }}
                </span>
              </div>
            </el-descriptions-item>
            <el-descriptions-item label="公积金地区" v-else>
              <el-tag type="info" size="small">未设置</el-tag>
            </el-descriptions-item>

            <!-- 大额医疗保险地区 -->
            <el-descriptions-item label="大额医疗地区" v-if="currentChange.employee && currentChange.employee.large_medical_insurance_config_relation">
              {{ currentChange.employee.large_medical_insurance_config_relation.region_name }}
            </el-descriptions-item>
            <el-descriptions-item label="大额医疗地区" v-else>
              <el-tag type="info" size="small">未设置</el-tag>
            </el-descriptions-item>
          </el-descriptions>
          <div class="form-tip">以上信息为该员工在员工档案中设置的参保地区信息。</div>
        </div>

        <!-- 员工保险基数信息 -->
        <div v-if="hasEmployeeBaseInfo()" class="insurance-details">
          <h4>员工保险基数</h4>
          <el-descriptions :column="2" size="small" border>
            <el-descriptions-item label="社保基数">
              <span class="base-amount">¥{{ currentChange.employee_social_security_base || '0.00' }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="医保基数">
              <span class="base-amount">¥{{ currentChange.employee_medical_insurance_base || '0.00' }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="公积金基数">
              <span class="base-amount">¥{{ currentChange.employee_housing_fund_base || '0.00' }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="大额医疗个人基数">
              <span class="base-amount">¥{{ currentChange.employee_large_medical_base || '0.00' }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="大额医疗单位基数" v-if="currentChange.employee_large_medical_company_base && currentChange.employee_large_medical_company_base != currentChange.employee_large_medical_base">
              <span class="base-amount">¥{{ currentChange.employee_large_medical_company_base || '0.00' }}</span>
              <el-tag type="warning" size="small" style="margin-left: 8px;">特殊地区</el-tag>
            </el-descriptions-item>
          </el-descriptions>
          <div class="form-tip">这些基数是员工档案中设置的保险缴费基数，用于计算各项保险费用。特殊地区的大额医疗保险支持个人和单位使用不同基数。</div>
        </div>

        <!-- 社保配置详情 -->
        <div v-if="getSocialSecurityDetails().length > 0" class="insurance-details" :class="{ 'has-change': hasCategoryChange('social_security') || (currentChange.change_summary && currentChange.change_summary.includes('社保')) }">
          <h4>
            社保配置详情
            <el-tag v-if="hasCategoryChange('social_security') || (currentChange.change_summary && currentChange.change_summary.includes('社保'))" type="danger" size="small" effect="dark" style="margin-left: 10px;">
              <el-icon style="margin-right: 2px;"><Warning /></el-icon>
              有变更
            </el-tag>
          </h4>
          <el-table :data="getSocialSecurityDetails()" size="small" border>
            <el-table-column prop="name" label="保险类型" width="200">
              <template #default="{ row }">
                <div style="display: flex; align-items: center; gap: 8px;">
                  <el-tag
                    v-if="isNewItem('social_security', row.name)"
                    type="success"
                    effect="dark"
                    size="small"
                  >
                    <el-icon style="margin-right: 2px;"><Plus /></el-icon>
                    新增
                  </el-tag>
                  <el-tag
                    v-else-if="isDeletedItem('social_security', row.name)"
                    type="danger"
                    effect="dark"
                    size="small"
                  >
                    <el-icon style="margin-right: 2px;"><Delete /></el-icon>
                    删除
                  </el-tag>
                  <el-tag
                    v-else-if="isModifiedItem('social_security', row.name)"
                    type="warning"
                    effect="dark"
                    size="small"
                  >
                    <el-icon style="margin-right: 2px;"><Edit /></el-icon>
                    修改
                  </el-tag>
                  <span>{{ row.name }}</span>
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="employee_ratio" label="个人比例" width="100">
              <template #default="{ row }">
                {{ row.employee_ratio ? (row.employee_ratio * 100).toFixed(2) + '%' : '-' }}
              </template>
            </el-table-column>
            <el-table-column prop="company_ratio" label="公司比例" width="100">
              <template #default="{ row }">
                {{ row.company_ratio ? (row.company_ratio * 100).toFixed(2) + '%' : '-' }}
              </template>
            </el-table-column>
            <el-table-column label="个人缴纳金额" width="120" align="right">
              <template #default="{ row }">
                <span class="amount-value">¥{{ calculateEmployeeAmount(row) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="公司缴纳金额" width="120" align="right">
              <template #default="{ row }">
                <span class="amount-value">¥{{ calculateCompanyAmount(row) }}</span>
              </template>
            </el-table-column>
          </el-table>
        </div>

        <!-- 公积金配置详情 -->
        <div v-if="getHousingFundDetails()" class="insurance-details" :class="{ 'has-change': hasCategoryChange('housing_fund') || (currentChange.change_summary && currentChange.change_summary.includes('公积金')) }">
          <h4>
            公积金配置详情
            <el-tag v-if="hasCategoryChange('housing_fund') || (currentChange.change_summary && currentChange.change_summary.includes('公积金'))" type="danger" size="small" effect="dark" style="margin-left: 10px;">
              <el-icon style="margin-right: 2px;"><Warning /></el-icon>
              有变更
            </el-tag>
          </h4>
          <el-descriptions :column="2" size="small" border>
            <el-descriptions-item label="配置名称">{{ getHousingFundDetails().config_name || '-' }}</el-descriptions-item>
            <el-descriptions-item label="个人比例">{{ getHousingFundDetails().employee_ratio ? (parseFloat(getHousingFundDetails().employee_ratio || 0) * 100).toFixed(2) + '%' : '-' }}</el-descriptions-item>
            <el-descriptions-item label="公司比例">{{ getHousingFundDetails().company_ratio ? (parseFloat(getHousingFundDetails().company_ratio || 0) * 100).toFixed(2) + '%' : '-' }}</el-descriptions-item>
            <el-descriptions-item label="总比例">{{ getHousingFundDetails().employee_ratio && getHousingFundDetails().company_ratio ? ((parseFloat(getHousingFundDetails().employee_ratio || 0) + parseFloat(getHousingFundDetails().company_ratio || 0)) * 100).toFixed(2) + '%' : '-' }}</el-descriptions-item>
            <el-descriptions-item label="个人缴纳金额">
              <span class="amount-value">¥{{ calculateHousingFundEmployeeAmount() }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="公司缴纳金额">
              <span class="amount-value">¥{{ calculateHousingFundCompanyAmount() }}</span>
            </el-descriptions-item>
          </el-descriptions>
        </div>

        <!-- 医保配置详情 -->
        <div v-if="getMedicalInsuranceDetails().length > 0" class="insurance-details" :class="{ 'has-change': hasCategoryChange('medical_insurance') || (currentChange.change_summary && currentChange.change_summary.includes('医保')) }">
          <h4>
            医保配置详情
            <el-tag v-if="hasCategoryChange('medical_insurance') || (currentChange.change_summary && currentChange.change_summary.includes('医保'))" type="danger" size="small" effect="dark" style="margin-left: 10px;">
              <el-icon style="margin-right: 2px;"><Warning /></el-icon>
              有变更
            </el-tag>
          </h4>
          <el-table :data="getMedicalInsuranceDetails()" size="small" border>
            <el-table-column prop="name" label="保险类型" width="200">
              <template #default="{ row }">
                <div style="display: flex; align-items: center; gap: 8px;">
                  <el-tag
                    v-if="isNewItem('medical_insurance', row.name)"
                    type="success"
                    effect="dark"
                    size="small"
                  >
                    <el-icon style="margin-right: 2px;"><Plus /></el-icon>
                    新增
                  </el-tag>
                  <el-tag
                    v-else-if="isDeletedItem('medical_insurance', row.name)"
                    type="danger"
                    effect="dark"
                    size="small"
                  >
                    <el-icon style="margin-right: 2px;"><Delete /></el-icon>
                    删除
                  </el-tag>
                  <el-tag
                    v-else-if="isModifiedItem('medical_insurance', row.name)"
                    type="warning"
                    effect="dark"
                    size="small"
                  >
                    <el-icon style="margin-right: 2px;"><Edit /></el-icon>
                    修改
                  </el-tag>
                  <span>{{ row.name || '-' }}</span>
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="employee_ratio" label="个人比例" width="100">
              <template #default="{ row }">
                {{ row.employee_ratio ? (row.employee_ratio * 100).toFixed(2) + '%' : '-' }}
              </template>
            </el-table-column>
            <el-table-column prop="company_ratio" label="公司比例" width="100">
              <template #default="{ row }">
                {{ row.company_ratio ? (row.company_ratio * 100).toFixed(2) + '%' : '-' }}
              </template>
            </el-table-column>
            <el-table-column label="个人缴纳金额" width="120" align="right">
              <template #default="{ row }">
                <span class="amount-value">¥{{ calculateMedicalInsuranceEmployeeAmount(row) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="公司缴纳金额" width="120" align="right">
              <template #default="{ row }">
                <span class="amount-value">¥{{ calculateMedicalInsuranceCompanyAmount(row) }}</span>
              </template>
            </el-table-column>
          </el-table>
        </div>

        <!-- 其他保险信息 -->
        <div v-if="getOtherInsuranceDetails().length > 0" class="insurance-details" :class="{ 'has-change': hasCategoryChange('other_insurance') || (currentChange.change_summary && currentChange.change_summary.includes('其他保险')) }">
          <h4>
            其他保险信息
            <el-tag v-if="hasCategoryChange('other_insurance') || (currentChange.change_summary && currentChange.change_summary.includes('其他保险'))" type="danger" size="small" effect="dark" style="margin-left: 10px;">
              <el-icon style="margin-right: 2px;"><Warning /></el-icon>
              有变更
            </el-tag>
          </h4>
          <el-table :data="getOtherInsuranceDetails()" size="small" border>
            <el-table-column prop="name" label="保险名称" width="260">
              <template #default="{ row }">
                <div style="display: flex; align-items: center; gap: 8px;">
                  <el-tag
                    v-if="isNewItem('other_insurance', row.name)"
                    type="success"
                    effect="dark"
                    size="small"
                  >
                    <el-icon style="margin-right: 2px;"><Plus /></el-icon>
                    新增
                  </el-tag>
                  <el-tag
                    v-else-if="isDeletedItem('other_insurance', row.name)"
                    type="danger"
                    effect="dark"
                    size="small"
                  >
                    <el-icon style="margin-right: 2px;"><Delete /></el-icon>
                    删除
                  </el-tag>
                  <el-tag
                    v-else-if="isModifiedItem('other_insurance', row.name)"
                    type="warning"
                    effect="dark"
                    size="small"
                  >
                    <el-icon style="margin-right: 2px;"><Edit /></el-icon>
                    修改
                  </el-tag>
                  <span>{{ row.name }}</span>
                </div>
              </template>
            </el-table-column>
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
            <el-table-column prop="endorsement_number" label="批单号" width="150">
              <template #default="{ row }">
                <el-input
                  v-if="currentChange && currentChange.status === 'pending'"
                  v-model="row.endorsement_number"
                  placeholder="请输入批单号"
                  size="small"
                  clearable
                  @blur="saveEndorsementNumber(row)"
                />
                <span v-else>{{ row.endorsement_number || '-' }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="policy_end_date" label="保单结束时间" width="150">
              <template #default="{ row }">
                <span>{{ formatDate(row.policy_end_date) || '-' }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="employee_per_capita_cost" label="员工人均参保费用" width="280">
              <template #default="{ row }">
                <div style="display: flex; align-items: center; gap: 8px;">
                  <!-- 不使用名额时可以编辑费用 -->
                  <template v-if="currentChange && currentChange.status === 'pending' && !row.quota_used">
                    <el-input-number
                      v-model="row.employee_per_capita_cost"
                      :precision="2"
                      :step="1"
                      :min="0"
                      size="small"
                      style="width: 120px;"
                      @change="saveEmployeePerCapitaCost(row)"
                    />
                  </template>
                  <span v-else>
                    ¥{{ row.employee_per_capita_cost || '0.00' }}
                  </span>
                  <!-- 使用名额标记 -->
                  <el-tag v-if="row.quota_used" type="success" size="small" effect="dark">
                    已用名额
                  </el-tag>
                  <!-- 显示被替换的人员姓名 -->
                  <el-tag 
                    v-if="row.quota_used && getReplacedPersonName(row)" 
                    type="info" 
                    size="small" 
                    effect="plain"
                    style="margin-left: 8px;"
                  >
                    替换: {{ getReplacedPersonName(row) }}
                  </el-tag>
                  <!-- 保存按钮已禁用 -->
                  <!-- 使用名额按钮 -->
                  <el-button 
                    type="primary" 
                    size="small"
                    @click="useQuota(row)"
                    v-if="currentChange && currentChange.status === 'pending' && !row.quota_used && row.available_quota > 0"
                  >
                    使用名额 ({{ row.available_quota }})
                  </el-button>
                </div>
              </template>
            </el-table-column>
          </el-table>
          <div class="form-tip">该项目绑定的其他保险，无需选择地区。数据导入后不可修改。</div>
        </div>

        <!-- 大额医疗保险 -->
        <div v-if="currentChange && getLargeMedicalInsuranceDetails()" class="insurance-details" :class="{ 'has-change': hasCategoryChange('large_medical_insurance') || (currentChange.change_summary && currentChange.change_summary.includes('大额')) }">
          <h4>
            大额医疗保险
            <!-- 显示具体的变更类型 -->
            <el-tag v-if="currentChange.change_summary && currentChange.change_summary.includes('开启大额')" type="success" size="small" effect="dark" style="margin-left: 10px;">
              <el-icon style="margin-right: 2px;"><Plus /></el-icon>
              开启参保
            </el-tag>
            <el-tag v-else-if="currentChange.change_summary && currentChange.change_summary.includes('关闭大额')" type="danger" size="small" effect="dark" style="margin-left: 10px;">
              <el-icon style="margin-right: 2px;"><Close /></el-icon>
              关闭参保
            </el-tag>
            <el-tag v-else-if="currentChange.change_summary && currentChange.change_summary.includes('大额医疗保险配置变更')" type="warning" size="small" effect="dark" style="margin-left: 10px;">
              <el-icon style="margin-right: 2px;"><Edit /></el-icon>
              配置变更
            </el-tag>
            <el-tag v-else-if="hasCategoryChange('large_medical_insurance') || (currentChange.change_summary && currentChange.change_summary.includes('大额'))" type="danger" size="small" effect="dark" style="margin-left: 10px;">
              <el-icon style="margin-right: 2px;"><Warning /></el-icon>
              有变更
            </el-tag>
          </h4>
          <el-table :data="[getLargeMedicalInsuranceDetails()]" size="small" border>
            <!-- 参保地区列已隐藏 -->
            <el-table-column label="计算方式" width="100">
              <template #default="{ row }">
                <div style="display: flex; align-items: center; gap: 8px;">
                  <el-tag
                    v-if="isModifiedItem('large_medical_insurance', '计算方式')"
                    type="warning"
                    effect="dark"
                    size="small"
                  >
                    <el-icon style="margin-right: 2px;"><Edit /></el-icon>
                    修改
                  </el-tag>
                  {{ row.calculation_type_text }}
                </div>
              </template>
            </el-table-column>
            <el-table-column label="个人基数" width="100" v-if="getLargeMedicalInsuranceDetails()?.calculation_type === 'base'">
              <template #default>
                <span class="amount-value">¥{{ currentChange.employee_large_medical_base || '0.00' }}</span>
              </template>
            </el-table-column>
            <el-table-column label="单位基数" width="100" v-if="getLargeMedicalInsuranceDetails()?.calculation_type === 'base' && currentChange.employee_large_medical_company_base && currentChange.employee_large_medical_company_base != currentChange.employee_large_medical_base">
              <template #default>
                <span class="amount-value">¥{{ currentChange.employee_large_medical_company_base || '0.00' }}</span>
                <el-tag type="warning" size="small" style="margin-left: 4px;">特殊</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="公司比例" width="100">
              <template #default="{ row }">
                <div style="display: flex; align-items: center; gap: 8px;">
                  <el-tag
                    v-if="isModifiedItem('large_medical_insurance', '公司比例')"
                    type="warning"
                    effect="dark"
                    size="small"
                  >
                    <el-icon style="margin-right: 2px;"><Edit /></el-icon>
                    修改
                  </el-tag>
                  <template v-if="row.calculation_type === 'base'">
                    {{ (row.company_ratio * 100).toFixed(2) }}%
                  </template>
                  <template v-else>
                    --
                  </template>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="员工比例" width="100">
              <template #default="{ row }">
                <div style="display: flex; align-items: center; gap: 8px;">
                  <el-tag
                    v-if="isModifiedItem('large_medical_insurance', '员工比例')"
                    type="warning"
                    effect="dark"
                    size="small"
                  >
                    <el-icon style="margin-right: 2px;"><Edit /></el-icon>
                    修改
                  </el-tag>
                  <template v-if="row.calculation_type === 'base'">
                    {{ (row.employee_ratio * 100).toFixed(2) }}%
                  </template>
                  <template v-else>
                    --
                  </template>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="公司缴纳金额" width="120" align="right">
              <template #default="{ row }">
                <span class="amount-value">¥{{ calculateLargeMedicalCompanyAmount(row) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="员工缴纳金额" width="120" align="right">
              <template #default="{ row }">
                <span class="amount-value">¥{{ calculateLargeMedicalEmployeeAmount(row) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="付款周期" width="100">
              <template #default="{ row }">
                <div style="display: flex; align-items: center; gap: 8px;">
                  <el-tag
                    v-if="isModifiedItem('large_medical_insurance', '付款周期')"
                    type="warning"
                    effect="dark"
                    size="small"
                  >
                    <el-icon style="margin-right: 2px;"><Edit /></el-icon>
                    修改
                  </el-tag>
                  {{ row.payment_cycle_text }}
                </div>
              </template>
            </el-table-column>
            <el-table-column label="是否启用" width="100">
              <template #default>
                <el-switch
                  v-model="currentChange.large_medical_insurance_enabled"
                  :disabled="isLargeMedicalSwitchDisabled"
                  @change="handleLargeMedicalSwitch"
                />
              </template>
            </el-table-column>
          </el-table>
          <div class="form-tip">
            大额医疗保险开关：开启后员工将参保大额医疗保险。
          </div>
        </div>
      </div>
    </el-dialog>
    
    <!-- 导出数据对话框 -->
    <el-dialog 
      v-model="showExportDialog" 
      title="导出参保数据" 
      width="600px"
      :close-on-click-modal="false"
    >
      <el-form label-width="120px">
        <el-form-item label="选择报表模板" required>
          <el-select 
            v-model="selectedTemplateId" 
            placeholder="请选择报表模板"
            style="width: 100%"
            @change="handleTemplateChange"
          >
            <el-option 
              v-for="tpl in exportTemplates" 
              :key="tpl.id" 
              :label="tpl.name" 
              :value="tpl.id"
            >
              <div style="display: flex; justify-content: space-between;">
                <span>{{ tpl.name }}</span>
                <span style="color: #8492a6; font-size: 12px;">{{ tpl.fields?.length || 0 }} 个字段</span>
              </div>
            </el-option>
          </el-select>
        </el-form-item>
        
        <el-form-item label="数据范围">
          <el-radio-group v-model="exportRange">
            <el-radio label="current">当前页数据 ({{ changes.length }} 条)</el-radio>
            <el-radio label="all">全部数据</el-radio>
          </el-radio-group>
        </el-form-item>
        
        <el-form-item label="模板说明" v-if="selectedTemplate">
          <el-text type="info">{{ selectedTemplate.description || '暂无说明' }}</el-text>
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showExportDialog = false">取消</el-button>
        <el-button type="primary" @click="exportData" :loading="exportLoading">
          <el-icon><Download /></el-icon>
          导出 Excel
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { DocumentAdd, Download, Warning, Plus, Minus, Edit, InfoFilled, ArrowDown, ArrowRight, Check, Document } from '@element-plus/icons-vue'
import { useAccountSetStore } from '@/stores/accountSet'
import request from '@/api/request'
import {
  getInsuranceChanges,
  getInsuranceChangeDetails,
  getInsuranceChangeSummaries,
  processInsuranceChange,
  generateSummary,
  exportSummary,
  updateEndorsementNumber,
  getSocialSecurityCompensationList,
  getHousingFundCompensationList
} from '@/api/insuranceChange'
import { getSocialSecurityRegions } from '@/api/socialSecurity'
import { exportSocialSecurityToExcelHTML, exportToExcelHTML, exportHousingFundSummaryToExcel } from '@/utils/excelExportHTML'

const accountSetStore = useAccountSetStore()

// 计算属性
const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)

// 判断是否是"开启大额医疗保险"任务
const isLargeMedicalEnableTask = computed(() => {
  if (!currentChange.value) return false
  // 通过 change_summary 判断是否是开启大额的任务
  return currentChange.value.change_summary === '开启大额医疗保险'
})

// 判断大额医疗保险开关是否应该禁用
const isLargeMedicalSwitchDisabled = computed(() => {
  if (!currentChange.value) return true
  
  // 待处理和待确认状态都可以操作开关，已处理状态禁用
  if (currentChange.value.status === 'pending' || currentChange.value.status === 'submitted') {
    return false
  }
  
  // 其他状态（已处理）禁用
  return true
})

// 格式化日期 - 只显示年月日
const formatDate = (date) => {
  if (!date) return ''
  try {
    // 解析日期字符串，移除多余的字符
    const dateStr = String(date).split('T')[0] // 只取日期部分
    return dateStr
  } catch (e) {
    console.error('日期格式化失败:', date, e)
    return ''
  }
}

// 生成社保明细标题
const getSocialSecurityTitle = () => {
  const regionName = detailFilterForm.value.region_name || '全部地区'
  const month = detailFilterForm.value.month || getCurrentMonth()
  
  // 格式化月份显示
  let formattedMonth = month
  if (month && month.includes('-')) {
    const [year, monthNum] = month.split('-')
    formattedMonth = `${year}年${monthNum.padStart(2, '0')}月`
  }
  
  return `${regionName}汇邦人力资源有限公司${formattedMonth}社保明细`
}

// 生成公积金明细标题
const getHousingFundTitle = () => {
  const month = detailFilterForm.value.month || getCurrentMonth()
  
  // 格式化月份显示：202507 格式
  let formattedMonth = month
  if (month && month.includes('-')) {
    const [year, monthNum] = month.split('-')
    formattedMonth = `${year}${monthNum.padStart(2, '0')}`
  }
  
  return `汇邦人力${formattedMonth}公积金明细`
}


// 按员工分组的明细数据（保留原有功能）
const groupedDetails = computed(() => {
  if (!details.value || details.value.length === 0) {
    return []
  }
  
  // 按员工ID分组
  const grouped = {}
  details.value.forEach(detail => {
    const employeeId = detail.employee_id
    if (!grouped[employeeId]) {
      grouped[employeeId] = {
        employee_id: employeeId,
        employee_name: detail.employee?.name || '未知员工',
        project_name: detail.project?.name || '未知项目',
        insurances: [],
        total_amount: 0,
        total_employee_amount: 0,
        total_company_amount: 0
      }
    }
    
    // 添加保险明细
    grouped[employeeId].insurances.push(detail)
    
    // 累加各种金额
    grouped[employeeId].total_amount += parseFloat(detail.total_amount || 0)
    grouped[employeeId].total_employee_amount += parseFloat(detail.employee_amount || 0)
    grouped[employeeId].total_company_amount += parseFloat(detail.company_amount || 0)
  })
  
  // 转换为数组并格式化金额
  return Object.values(grouped).map(group => ({
    ...group,
    total_amount: group.total_amount.toFixed(2),
    total_employee_amount: group.total_employee_amount.toFixed(2),
    total_company_amount: group.total_company_amount.toFixed(2)
  }))
})

// 动态列配置
const dynamicCompanyColumns = computed(() => {
  if (!details.value || details.value.length === 0) {
    return []
  }
  
  const columns = []
  const firstDetail = details.value[0]
  const insurancePersonnel = firstDetail.insurance_personnel || {}
  
  // 从社保类型快照数据中生成列
  if (insurancePersonnel.social_security_types) {
    try {
      const socialSecurityTypes = JSON.parse(insurancePersonnel.social_security_types)
      if (Array.isArray(socialSecurityTypes) && socialSecurityTypes.length > 0) {
        socialSecurityTypes.forEach(type => {
          columns.push({
            name: type.name,
            type: 'social_security',
            fieldPrefix: '社保_'
          })
        })
      } else {
        // 如果没有社保类型数据，添加默认的社保列
        columns.push({
          name: '社保',
          type: 'social_security',
          fieldPrefix: '社保_'
        })
      }
    } catch (e) {
      console.error('解析social_security_types失败:', e)
      // 解析失败时也添加默认社保列
      columns.push({
        name: '社保',
        type: 'social_security',
        fieldPrefix: '社保_'
      })
    }
  } else {
    // 如果没有社保类型数据，添加默认的社保列
    columns.push({
      name: '社保',
      type: 'social_security',
      fieldPrefix: '社保_'
    })
  }
  
  // 从医保类型快照数据中生成列
  if (insurancePersonnel.medical_insurance_types) {
    try {
      const medicalInsuranceTypes = JSON.parse(insurancePersonnel.medical_insurance_types)
      if (Array.isArray(medicalInsuranceTypes)) {
        medicalInsuranceTypes.forEach(type => {
          columns.push({
            name: type.name,
            type: 'medical_insurance',
            fieldPrefix: '医保_'
          })
        })
      }
    } catch (e) {
      console.error('解析medical_insurance_types失败:', e)
    }
  }
  
  // 添加大额医疗列（始终显示，如果未启用则显示0）
  // 检查是否启用了大额医疗保险
  const isLargeMedicalEnabled = insurancePersonnel.large_medical_insurance_config ? 
    (() => {
      try {
        const config = JSON.parse(insurancePersonnel.large_medical_insurance_config)
        return config.is_enabled || false
      } catch (e) {
        return false
      }
    })() : false
  
  if (isLargeMedicalEnabled) {
    columns.push({
      name: '大额医疗',
      type: 'large_medical'
    })
  }
  
  return columns
})

const dynamicEmployeeColumns = computed(() => {
  return dynamicCompanyColumns.value // 员工列和公司列使用相同的配置
})

// 社保明细数据（包括医保、社保、大额医疗保险）
const socialSecurityDetails = computed(() => {
  if (!details.value || details.value.length === 0) {
    return []
  }
  
  // 按员工类型分组：正常和补交
  const normalEmployees = []
  const supplementaryEmployees = []
  
  details.value.forEach((detail, index) => {
    const employeeType = detail.employee_type || '正常'
    
    if (employeeType === '补交') {
      supplementaryEmployees.push({ ...detail, originalIndex: index })
    } else {
      normalEmployees.push({ ...detail, originalIndex: index })
    }
  })
  
  // 处理正常员工数据
  const normalData = normalEmployees.map((detail, index) => {
    const employee = detail.employee || {}
    const project = detail.project || {}
    const insurancePersonnel = detail.insurance_personnel || {}
    
    // 从快照数据中解析保险类型
    let socialSecurityTypes = []
    let medicalInsuranceTypes = []
    
    if (insurancePersonnel.social_security_types) {
      try {
        socialSecurityTypes = JSON.parse(insurancePersonnel.social_security_types)
      } catch (e) {
        console.error('解析social_security_types失败:', e)
      }
    }
    
    if (insurancePersonnel.medical_insurance_types) {
      try {
        medicalInsuranceTypes = JSON.parse(insurancePersonnel.medical_insurance_types)
      } catch (e) {
        console.error('解析medical_insurance_types失败:', e)
      }
    }
    
    // 计算各项金额
    const medicalCompanyAmount = parseFloat(detail.medical_insurance_company_amount || 0)
    const medicalEmployeeAmount = parseFloat(detail.medical_insurance_employee_amount || 0)
    const socialCompanyAmount = parseFloat(detail.social_security_company_amount || 0)
    const socialEmployeeAmount = parseFloat(detail.social_security_employee_amount || 0)
    const largeMedicalCompanyAmount = parseFloat(detail.large_medical_company_amount || 0)
    const largeMedicalEmployeeAmount = parseFloat(detail.large_medical_employee_amount || 0)
    
    // 计算合计
    const companyTotal = medicalCompanyAmount + socialCompanyAmount + largeMedicalCompanyAmount
    const employeeTotal = medicalEmployeeAmount + socialEmployeeAmount + largeMedicalEmployeeAmount
    const socialSecurityTotal = companyTotal + employeeTotal
    
    const rowData = {
      serial_number: index + 1,
      employee_name: detail.employee_name || employee.name || '小计',
      id_number: detail.employee_id_number || employee.id_number || '',
      project_name: detail.project_name || (project ? project.name : ''),
      // 社保明细使用社保参保日期（优先使用后端返回的，其次从employee对象获取）
      enrollment_date: formatEnrollmentDate(detail.social_insurance_enrollment_date || employee.social_insurance_enrollment_date || detail.created_at),
      type: '正常',
      period: formatPeriodString(detail.payment_period) || formatPeriod(detail.created_at),
      medical_base: detail.employee_medical_insurance_base || '0.00',
      social_security_base: detail.employee_social_security_base || '0.00',
      
      // 社保合计
      social_security_total: socialSecurityTotal.toFixed(2),
      remarks: ''
    }
    
    // 动态添加社保类型列
    if (Array.isArray(socialSecurityTypes)) {
      socialSecurityTypes.forEach(type => {
        const baseAmount = parseFloat(detail.employee_social_security_base || 0)
        const companyAmount = baseAmount * (parseFloat(type.company_ratio || 0))
        const employeeAmount = baseAmount * (parseFloat(type.employee_ratio || 0))
        
        // 使用类型前缀避免字段名冲突
        rowData['company_社保_' + type.name] = companyAmount.toFixed(2)
        rowData['employee_社保_' + type.name] = employeeAmount.toFixed(2)
      })
    }
    
    // 动态添加医保类型列
    if (Array.isArray(medicalInsuranceTypes)) {
      medicalInsuranceTypes.forEach(type => {
        const baseAmount = parseFloat(detail.employee_medical_insurance_base || 0)
        const companyAmount = baseAmount * (parseFloat(type.company_ratio || 0))
        const employeeAmount = baseAmount * (parseFloat(type.employee_ratio || 0))
        
        // 使用类型前缀避免字段名冲突
        rowData['company_医保_' + type.name] = companyAmount.toFixed(2)
        rowData['employee_医保_' + type.name] = employeeAmount.toFixed(2)
      })
    }
    
    // 添加大额医疗列（如果启用）
    if (detail.large_medical_company_amount > 0 || detail.large_medical_employee_amount > 0) {
      rowData['company_大额医疗'] = largeMedicalCompanyAmount.toFixed(2)
      rowData['employee_大额医疗'] = largeMedicalEmployeeAmount.toFixed(2)
    }
    
    // 直接使用后端计算的合计金额，避免前端重复计算
    rowData.company_total = companyTotal.toFixed(2)
    rowData.employee_total = employeeTotal.toFixed(2)
    rowData.social_security_total = socialSecurityTotal.toFixed(2)
    
    return rowData
  })
  
  // 处理补交员工数据
  const supplementaryData = supplementaryEmployees.map((detail, index) => {
    const employee = detail.employee || {}
    const project = detail.project || {}
    const insurancePersonnel = detail.insurance_personnel || {}
    
    // 从快照数据中解析保险类型
    let socialSecurityTypes = []
    let medicalInsuranceTypes = []
    
    if (insurancePersonnel.social_security_types) {
      try {
        socialSecurityTypes = JSON.parse(insurancePersonnel.social_security_types)
      } catch (e) {
        console.error('解析social_security_types失败:', e)
      }
    }
    
    if (insurancePersonnel.medical_insurance_types) {
      try {
        medicalInsuranceTypes = JSON.parse(insurancePersonnel.medical_insurance_types)
      } catch (e) {
        console.error('解析medical_insurance_types失败:', e)
      }
    }
    
    // 计算各项金额
    const medicalCompanyAmount = parseFloat(detail.medical_insurance_company_amount || 0)
    const medicalEmployeeAmount = parseFloat(detail.medical_insurance_employee_amount || 0)
    const socialCompanyAmount = parseFloat(detail.social_security_company_amount || 0)
    const socialEmployeeAmount = parseFloat(detail.social_security_employee_amount || 0)
    const largeMedicalCompanyAmount = parseFloat(detail.large_medical_company_amount || 0)
    const largeMedicalEmployeeAmount = parseFloat(detail.large_medical_employee_amount || 0)
    
    // 计算合计
    const companyTotal = medicalCompanyAmount + socialCompanyAmount + largeMedicalCompanyAmount
    const employeeTotal = medicalEmployeeAmount + socialEmployeeAmount + largeMedicalEmployeeAmount
    const socialSecurityTotal = companyTotal + employeeTotal
    
    const rowData = {
      serial_number: normalData.length + index + 1, // 序号从正常员工后面开始
      employee_name: detail.employee_name || employee.name || '小计',
      id_number: detail.employee_id_number || employee.id_number || '',
      project_name: detail.project_name || (project ? project.name : ''),
      // 社保明细补交也使用社保参保日期
      enrollment_date: formatEnrollmentDate(detail.social_insurance_enrollment_date || employee.social_insurance_enrollment_date || detail.created_at),
      type: '补交', // 设置为补交类型
      period: formatPeriodString(detail.payment_period) || formatPeriod(detail.created_at),
      medical_base: detail.employee_medical_insurance_base || '0.00',
      social_security_base: detail.employee_social_security_base || '0.00',
      
      // 社保合计
      social_security_total: socialSecurityTotal.toFixed(2),
      remarks: ''
    }
    
    // 动态添加社保类型列
    if (Array.isArray(socialSecurityTypes)) {
      socialSecurityTypes.forEach(type => {
        const baseAmount = parseFloat(detail.employee_social_security_base || 0)
        const companyAmount = baseAmount * (parseFloat(type.company_ratio || 0))
        const employeeAmount = baseAmount * (parseFloat(type.employee_ratio || 0))
        
        // 使用类型前缀避免字段名冲突
        rowData['company_社保_' + type.name] = companyAmount.toFixed(2)
        rowData['employee_社保_' + type.name] = employeeAmount.toFixed(2)
      })
    }
    
    // 动态添加医保类型列
    if (Array.isArray(medicalInsuranceTypes)) {
      medicalInsuranceTypes.forEach(type => {
        const baseAmount = parseFloat(detail.employee_medical_insurance_base || 0)
        const companyAmount = baseAmount * (parseFloat(type.company_ratio || 0))
        const employeeAmount = baseAmount * (parseFloat(type.employee_ratio || 0))
        
        // 使用类型前缀避免字段名冲突
        rowData['company_医保_' + type.name] = companyAmount.toFixed(2)
        rowData['employee_医保_' + type.name] = employeeAmount.toFixed(2)
      })
    }
    
    // 添加大额医疗列（如果启用）
    if (detail.large_medical_company_amount > 0 || detail.large_medical_employee_amount > 0) {
      rowData['company_大额医疗'] = largeMedicalCompanyAmount.toFixed(2)
      rowData['employee_大额医疗'] = largeMedicalEmployeeAmount.toFixed(2)
    }
    
    // 直接使用后端计算的合计金额，避免前端重复计算
    rowData.company_total = companyTotal.toFixed(2)
    rowData.employee_total = employeeTotal.toFixed(2)
    rowData.social_security_total = socialSecurityTotal.toFixed(2)
    
    return rowData
  })
  
  // 构建结果数组：正常员工 + 正常小计 + 补交员工 + 补交小计 + 合计
  const result = []
  const summaryRows = [] // 用于存储小计行
  
  // 1. 正常员工数据 + 正常小计
  if (normalData.length > 0) {
    result.push(...normalData)
    const normalSummary = calculateSummaryRow(normalData, '小计')
    result.push(normalSummary)
    summaryRows.push(normalSummary)
  }
  
  // 2. 补交员工数据 + 补交小计
  if (supplementaryData.length > 0) {
    result.push(...supplementaryData)
    const supplementarySummary = calculateSummaryRow(supplementaryData, '小计')
    result.push(supplementarySummary)
    summaryRows.push(supplementarySummary)
  }
  
  // 3. 合计行（只计算小计行的值，但命名为"合计"）
  if (summaryRows.length > 0) {
    const totalRow = calculateTotalFromSummaries(summaryRows)
    totalRow.employee_name = '合计' // 确保显示为"合计"
    result.push(totalRow)
  }
  
  return result
})

// 公积金明细数据（参照社保明细逻辑）
const housingFundDetails = computed(() => {
  if (!details.value || details.value.length === 0) {
    return []
  }
  
  // 按员工类型分组：正常和补交
  const normalEmployees = []
  const supplementaryEmployees = []
  
  details.value.forEach((detail, index) => {
    const employeeType = detail.employee_type || '正常'
    
    if (employeeType === '补交') {
      supplementaryEmployees.push({ ...detail, originalIndex: index })
    } else {
      normalEmployees.push({ ...detail, originalIndex: index })
    }
  })
  
  // 处理正常员工数据
  const normalData = normalEmployees.map((detail, index) => {
    const employee = detail.employee || {}
    const project = detail.project || {}
    const insurancePersonnel = detail.insurance_personnel || {}
    
    // 从insurance_personnel对象的快照数据中解析公积金配置
    let housingFundParams = null
    if (insurancePersonnel.housing_fund_params) {
      if (typeof insurancePersonnel.housing_fund_params === 'string') {
        try {
          housingFundParams = JSON.parse(insurancePersonnel.housing_fund_params)
        } catch (e) {
          console.error('解析housing_fund_params失败:', e)
        }
      } else {
        housingFundParams = insurancePersonnel.housing_fund_params
      }
    }
    
    const employeeRatio = housingFundParams ? (parseFloat(housingFundParams.employee_ratio || 0) * 100).toFixed(2) : '0.00'
    const companyRatio = housingFundParams ? (parseFloat(housingFundParams.company_ratio || 0) * 100).toFixed(2) : '0.00'
    const totalRatio = (parseFloat(employeeRatio) + parseFloat(companyRatio)).toFixed(2)
    
    const companyPortion = parseFloat(detail.housing_fund_company_amount || 0)
    const employeePortion = parseFloat(detail.housing_fund_employee_amount || 0)
    const housingFundTotal = companyPortion + employeePortion
    
    return {
      serial_number: index + 1,
      employee_name: detail.employee_name || employee.name || '',
      id_number: detail.employee_id_number || employee.id_number || '',
      project_name: detail.project_name || (project ? project.name : ''),
      // 公积金明细使用公积金参保日期
      enrollment_date: formatEnrollmentDate(detail.provident_fund_enrollment_date || employee.provident_fund_enrollment_date || detail.created_at),
      type: '正常',
      period: formatPeriodString(detail.payment_period) || formatPeriod(detail.created_at),
      housing_fund_base: detail.employee_housing_fund_base || '0.00',
      employee_housing_fund_base: detail.employee_housing_fund_base,
      ratio: totalRatio + '%',
      company_portion: companyPortion.toFixed(2),
      employee_portion: employeePortion.toFixed(2),
      housing_fund_total: housingFundTotal.toFixed(2),
      remarks: ''
    }
  })
  
  // 处理补交员工数据
  const supplementaryData = supplementaryEmployees.map((detail, index) => {
    const employee = detail.employee || {}
    const project = detail.project || {}
    const insurancePersonnel = detail.insurance_personnel || {}
    
    // 从insurance_personnel对象的快照数据中解析公积金配置
    let housingFundParams = null
    if (insurancePersonnel.housing_fund_params) {
      if (typeof insurancePersonnel.housing_fund_params === 'string') {
        try {
          housingFundParams = JSON.parse(insurancePersonnel.housing_fund_params)
        } catch (e) {
          console.error('解析housing_fund_params失败:', e)
        }
      } else {
        housingFundParams = insurancePersonnel.housing_fund_params
      }
    }
    
    const employeeRatio = housingFundParams ? (parseFloat(housingFundParams.employee_ratio || 0) * 100).toFixed(2) : '0.00'
    const companyRatio = housingFundParams ? (parseFloat(housingFundParams.company_ratio || 0) * 100).toFixed(2) : '0.00'
    const totalRatio = (parseFloat(employeeRatio) + parseFloat(companyRatio)).toFixed(2)
    
    const companyPortion = parseFloat(detail.housing_fund_company_amount || 0)
    const employeePortion = parseFloat(detail.housing_fund_employee_amount || 0)
    const housingFundTotal = companyPortion + employeePortion
    
    return {
      serial_number: normalData.length + index + 1, // 序号从正常员工后面开始
      employee_name: detail.employee_name || employee.name || '',
      id_number: detail.employee_id_number || employee.id_number || '',
      project_name: detail.project_name || (project ? project.name : ''),
      // 公积金明细补交也使用公积金参保日期
      enrollment_date: formatEnrollmentDate(detail.provident_fund_enrollment_date || employee.provident_fund_enrollment_date || detail.created_at),
      type: '补交',
      period: formatPeriodString(detail.payment_period) || formatPeriod(detail.created_at),
      housing_fund_base: detail.employee_housing_fund_base || '0.00',
      employee_housing_fund_base: detail.employee_housing_fund_base,
      ratio: totalRatio + '%',
      company_portion: companyPortion.toFixed(2),
      employee_portion: employeePortion.toFixed(2),
      housing_fund_total: housingFundTotal.toFixed(2),
      remarks: ''
    }
  })
  
  // 构建结果数组：正常员工 + 正常小计 + 补交员工 + 补交小计 + 合计
  const result = []
  const summaryRows = [] // 用于存储小计行
  
  // 1. 正常员工数据 + 正常小计
  if (normalData.length > 0) {
    result.push(...normalData)
    const normalSummary = calculateHousingFundSummaryRow(normalData, '小计')
    result.push(normalSummary)
    summaryRows.push(normalSummary)
  }
  
  // 2. 补交员工数据 + 补交小计
  if (supplementaryData.length > 0) {
    result.push(...supplementaryData)
    const supplementarySummary = calculateHousingFundSummaryRow(supplementaryData, '小计')
    result.push(supplementarySummary)
    summaryRows.push(supplementarySummary)
  }
  
  // 3. 合计行（计算两个小计的值）
  if (summaryRows.length > 0) {
    const totalRow = calculateHousingFundTotalFromSummaries(summaryRows)
    totalRow.employee_name = '合计' // 确保显示为"合计"
    result.push(totalRow)
  }
  
  return result
})

// 计算年龄的辅助函数（从身份证号码）
const calculateAgeFromId = (idNumber, birthDate = null) => {
  let birth = null
  
  // 优先使用出生日期，但要验证其合理性
  if (birthDate) {
    birth = new Date(birthDate)
    // 如果出生日期是未来日期或明显不合理，则忽略出生日期
    if (birth.getTime() > new Date().getTime() || birth.getFullYear() > new Date().getFullYear()) {
      birth = null
    }
  } 
  
  // 如果没有合理的出生日期，尝试从身份证号码计算
  if (!birth && idNumber && idNumber.length >= 8) {
    try {
      // 身份证前8位是出生日期 (YYYYMMDD)
      const birthStr = idNumber.substring(0, 8)
      const year = parseInt(birthStr.substring(0, 4))
      const month = parseInt(birthStr.substring(4, 6))
      const day = parseInt(birthStr.substring(6, 8))
      
      // 验证日期有效性
      if (year >= 1900 && year <= new Date().getFullYear() && 
          month >= 1 && month <= 12 && 
          day >= 1 && day <= 31) {
        birth = new Date(year, month - 1, day)
      }
    } catch (e) {
      console.warn('身份证号码解析失败:', idNumber, e)
    }
  }
  
  if (!birth || isNaN(birth.getTime())) {
    return '-'
  }
  
  const today = new Date()
  let age = today.getFullYear() - birth.getFullYear()
  const monthDiff = today.getMonth() - birth.getMonth()
  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
    age--
  }
  return age
}

// 格式化性别显示
const formatGender = (gender) => {
  if (gender === 1 || gender === '1' || gender === 'male' || gender === '男') return '男'
  if (gender === 2 || gender === '2' || gender === 'female' || gender === '女') return '女'
  return '-'
}

// 格式化员工状态
const formatEmployeeStatus = (status) => {
  if (status === 1 || status === '1') return '在职'
  if (status === 2 || status === '2') return '离职'
  return '在职' // 默认为在职
}

// 其他保险明细数据 - 按保险种类分组显示
const otherInsuranceDetails = computed(() => {
  // 新逻辑：从 detail.other_insurance_policies 读取（而不是 insurancePersonnel）
  // 这样才能正确反映该月份是否生成了其他保险明细
  if (details.value && details.value.length > 0) {
    const groupedData = {}
    
    details.value.forEach((detail, detailIndex) => {
      const employee = detail.employee || {}
      const project = detail.project || {}
      
      // ✅ 关键修改：从 detail.other_insurance_policies 读取，而不是 insurancePersonnel
      // 这样才能正确显示该月份是否生成了其他保险明细
      if (detail.other_insurance_policies) {
        let otherInsurancePolicies = detail.other_insurance_policies
        
        if (typeof otherInsurancePolicies === 'string') {
          try {
            otherInsurancePolicies = JSON.parse(otherInsurancePolicies)
          } catch (e) {
            console.error('解析other_insurance_policies失败:', e)
            return // 跳过此detail，继续处理下一个
          }
        }
        
        if (Array.isArray(otherInsurancePolicies) && otherInsurancePolicies.length > 0) {
          otherInsurancePolicies.forEach((policy, policyIndex) => {
            const insuranceType = policy.type || '其他保险'
            
            // 初始化保险种类分组
            if (!groupedData[insuranceType]) {
              groupedData[insuranceType] = {
                type: insuranceType,
                policies: []
              }
            }
            
            const policyData = {
              serial_number: groupedData[insuranceType].policies.length + 1,
              employee_name: detail.employee_name || employee.name || '-',
              id_number: detail.employee_id_number || employee.id_number || '-',
              gender: formatGender(detail.employee_gender || employee.gender),
              age: calculateAgeFromId(detail.employee_id_number || employee.id_number, detail.employee_birth_date || employee.birth_date),
              contact_phone: detail.employee_phone || employee.phone || '-',
              project_name: detail.project_name || (project ? project.name : '-'),
              addition_date: new Date().toLocaleDateString('zh-CN').replace(/\//g, ''),
              expiration_date: policy.policy_end_date || '-',
              type: policy.removed_person_name ? '替换' : '新增',
              insurance_status: '在保',
              employment_status: formatEmployeeStatus(detail.employee_status || employee.status),
              amount: policy.employee_per_capita_cost || 0,
              replaced_person_name: policy.removed_person_name || '-',
              endorsement_number: policy.endorsement_number || '-',
              remarks: policy.coverage || '-',
              policy_id: policy.id,
              policy_name: policy.name
            }
            
            groupedData[insuranceType].policies.push(policyData)
          })
        }
      }
    })
    
    return groupedData
  }
  
  // 如果没有已处理的明细数据，则从当前变更记录生成（用于详情对话框）
  if (currentChange.value && currentChange.value.other_insurance_policies) {
    let otherInsurancePolicies = currentChange.value.other_insurance_policies
    
    // 确保 otherInsurancePolicies 是数组
    if (typeof otherInsurancePolicies === 'string') {
      try {
        otherInsurancePolicies = JSON.parse(otherInsurancePolicies)
      } catch (e) {
        console.error('解析other_insurance_policies失败:', e)
        return {}
      }
    }
    
    if (!Array.isArray(otherInsurancePolicies)) {
      return {}
    }
    
    const employee = currentChange.value.employee || {}
    const project = currentChange.value.project || {}
    
    // 优先使用InsuranceChange表中保存的员工信息，如果没有则使用employee关系
    const employeeInfo = {
      name: currentChange.value.employee_name || employee.name,
      id_number: currentChange.value.employee_id_number || employee.id_number,
      gender: currentChange.value.employee_gender || employee.gender,
      birth_date: currentChange.value.employee_birth_date || employee.birth_date,
      phone: currentChange.value.employee_phone || employee.phone,
      status: currentChange.value.employee_status || employee.status
    }
  
  // 计算年龄
  const calculateAge = (birthDate, idNumber = null) => {
    let birth = null
    
    // 优先使用出生日期
    if (birthDate) {
      birth = new Date(birthDate)
    } 
    // 如果没有出生日期，尝试从身份证号码计算
    else if (idNumber && idNumber.length >= 8) {
      try {
        // 身份证前8位是出生日期 (YYYYMMDD)
        const birthStr = idNumber.substring(0, 8)
        const year = parseInt(birthStr.substring(0, 4))
        const month = parseInt(birthStr.substring(4, 6))
        const day = parseInt(birthStr.substring(6, 8))
        
        // 验证日期有效性
        if (year >= 1900 && year <= new Date().getFullYear() && 
            month >= 1 && month <= 12 && 
            day >= 1 && day <= 31) {
          birth = new Date(year, month - 1, day)
        }
      } catch (e) {
        console.warn('身份证号码解析失败:', idNumber, e)
      }
    }
    
    if (!birth || isNaN(birth.getTime())) {
      return ''
    }
    
    const today = new Date()
    let age = today.getFullYear() - birth.getFullYear()
    const monthDiff = today.getMonth() - birth.getMonth()
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
      age--
    }
    return age
  }
  
  
  // 格式化日期 - 只显示年月日
  const formatDate = (date) => {
    if (!date) return ''
    try {
      // 解析日期字符串，移除多余的字符
      const dateStr = String(date).split('T')[0] // 只取日期部分
      return dateStr
    } catch (e) {
      console.error('日期格式化失败:', date, e)
      return ''
    }
  }
  
  // 获取被替换的人员姓名
  const getReplacedPersonName = (policyId) => {
    if (!currentChange.value.used_quotas) return null
    
    let usedQuotas = currentChange.value.used_quotas
    if (typeof usedQuotas === 'string') {
      try {
        usedQuotas = JSON.parse(usedQuotas)
      } catch (e) {
        return null
      }
    }
    
    if (!Array.isArray(usedQuotas)) return null
    
    for (const usedQuota of usedQuotas) {
      if (typeof usedQuota === 'object' && usedQuota.policy_id == policyId && usedQuota.removed_person_name) {
        return usedQuota.removed_person_name
      }
    }
    return null
  }
  
  // 为每个保险保单生成一行数据（按保险种类分组）
  const groupedData = {}
  otherInsurancePolicies.forEach((policy, index) => {
    const insuranceType = policy.type || '其他保险'
    
    // 初始化保险种类分组
    if (!groupedData[insuranceType]) {
      groupedData[insuranceType] = {
        type: insuranceType,
        policies: []
      }
    }
    // 获取被替换的人员姓名
    const replacedPersonName = getReplacedPersonName(policy.id)
    
    // 获取当前年月作为增加日期
    const getCurrentYearMonth = () => {
      const now = new Date()
      const year = now.getFullYear()
      const month = String(now.getMonth() + 1).padStart(2, '0')
      return `${year}${month}`
    }
    
    // 格式化性别显示
    const formatGender = (gender) => {
      if (gender === 1 || gender === '1' || gender === '男') return '男'
      if (gender === 2 || gender === '2' || gender === '女') return '女'
      return '-'
    }
    
    // 格式化员工状态
    const formatEmployeeStatus = (status) => {
      if (status === 1 || status === '1') return '在职'
      if (status === 2 || status === '2') return '离职'
      return '在职' // 默认为在职
    }
    
    const policyData = {
      serial_number: groupedData[insuranceType].policies.length + 1,
      employee_name: employeeInfo.name || '-',
      id_number: employeeInfo.id_number || '-',
      gender: formatGender(employeeInfo.gender),
      age: calculateAge(employeeInfo.birth_date, employeeInfo.id_number) || '-',
      contact_phone: employeeInfo.phone || '-',
      project_name: project.name || '-',
      addition_date: getCurrentYearMonth(),
      expiration_date: formatDate(policy.policy_end_date),
      type: replacedPersonName ? '替换' : '新增',
      insurance_status: '在保',
      employment_status: formatEmployeeStatus(employeeInfo.status),
      amount: policy.employee_per_capita_cost || 0,
      replaced_person_name: replacedPersonName || '-',
      endorsement_number: policy.endorsement_number || '-',
      remarks: policy.description || '-',
      policy_id: policy.id,
      policy_name: policy.name
    }
    
    groupedData[insuranceType].policies.push(policyData)
  })
  
  return groupedData
  }
  
  return {}
})

// 社保补交明细数据
const compensationDetails = computed(() => {
  if (!rawCompensationData.value || rawCompensationData.value.length === 0) {
    return []
  }
  
  const result = []
  let serialNumber = 1
  
  rawCompensationData.value.forEach(compensation => {
    // ✅ 修改：从 social_security_types 字段解析补差明细
    let compensationTypes = []
    try {
      compensationTypes = typeof compensation.social_security_types === 'string' 
        ? JSON.parse(compensation.social_security_types) 
        : compensation.social_security_types || []
    } catch (e) {
      console.error('解析social_security_types失败:', e)
    }
    
    if (compensationTypes && compensationTypes.length > 0) {
      const rowData = {
        serial_number: serialNumber++,
        employee_name: compensation.employee_name || compensation.employee?.name || '-',
        id_number: compensation.employee_id_number || compensation.employee?.id_number || '-',
        project_name: compensation.project?.name || '-',
        compensation_period: `${compensation.compensation_start_month || ''} 至 ${compensation.compensation_end_month || ''}`,
        compensation_months: compensation.compensation_months || 0,
        old_base: parseFloat(compensation.old_base || 0).toFixed(2),
        new_base: parseFloat(compensation.new_base || 0).toFixed(2),
      }
      
      // 动态添加各险种的金额
      let companyTotal = 0
      let employeeTotal = 0
      
      compensationTypes.forEach(type => {
        const typeName = type.name || '未知险种'
        const companyAmount = parseFloat(type.company_amount || 0)
        const personalAmount = parseFloat(type.personal_amount || 0)
        
        rowData[`company_${typeName}`] = companyAmount.toFixed(2)
        rowData[`employee_${typeName}`] = personalAmount.toFixed(2)
        
        companyTotal += companyAmount
        employeeTotal += personalAmount
      })
      
      // 计算合计金额
      rowData.company_total = companyTotal.toFixed(2)
      rowData.employee_total = employeeTotal.toFixed(2)
      rowData.total = (companyTotal + employeeTotal).toFixed(2)
      
      result.push(rowData)
    }
  })
  
  return result
})

// 动态补差列配置
const dynamicCompensationColumns = computed(() => {
  if (!rawCompensationData.value || rawCompensationData.value.length === 0) {
    return []
  }
  
  const columnSet = new Set()
  
  rawCompensationData.value.forEach(compensation => {
    // ✅ 修改：从 social_security_types 字段解析险种列表
    let compensationTypes = []
    try {
      compensationTypes = typeof compensation.social_security_types === 'string' 
        ? JSON.parse(compensation.social_security_types) 
        : compensation.social_security_types || []
    } catch (e) {
      console.error('解析social_security_types失败:', e)
    }
    
    if (compensationTypes && compensationTypes.length > 0) {
      compensationTypes.forEach(type => {
        if (type.name) {
          columnSet.add(type.name)
        }
      })
    }
  })
  
  return Array.from(columnSet).map(name => ({ name }))
})

// 公积金补交明细数据
const rawHousingFundCompensationData = ref([])
const housingFundCompensationDetails = computed(() => {
  if (!rawHousingFundCompensationData.value || rawHousingFundCompensationData.value.length === 0) {
    return []
  }
  
  const result = []
  let serialNumber = 1
  
  rawHousingFundCompensationData.value.forEach(compensation => {
    // ✅ 修改：从 housing_fund_params 字段解析补差明细
    let compensationTypes = []
    try {
      compensationTypes = typeof compensation.housing_fund_params === 'string' 
        ? JSON.parse(compensation.housing_fund_params) 
        : compensation.housing_fund_params || []
    } catch (e) {
      console.error('解析housing_fund_params失败:', e)
    }
    
    if (compensationTypes && compensationTypes.length > 0) {
      const firstType = compensationTypes[0] || {}
      
      // 计算合计金额
      let companyTotal = 0
      let employeeTotal = 0
      
      compensationTypes.forEach(type => {
        companyTotal += parseFloat(type.company_amount || 0)
        employeeTotal += parseFloat(type.personal_amount || 0)
      })
      
      const rowData = {
        serial_number: serialNumber++,
        employee_name: compensation.employee_name || compensation.employee?.name || '-',
        id_number: compensation.employee_id_number || compensation.employee?.id_number || '-',
        project_name: compensation.project?.name || '-',
        compensation_period: `${compensation.compensation_start_month || ''} 至 ${compensation.compensation_end_month || ''}`,
        compensation_months: compensation.compensation_months || 0,
        old_base: parseFloat(compensation.old_base || 0).toFixed(2),
        new_base: parseFloat(compensation.new_base || 0).toFixed(2),
        company_amount: parseFloat(firstType.company_amount || 0).toFixed(2),
        employee_amount: parseFloat(firstType.personal_amount || 0).toFixed(2),
        company_total: companyTotal.toFixed(2),
        employee_total: employeeTotal.toFixed(2),
        total: (companyTotal + employeeTotal).toFixed(2)
      }
      
      result.push(rowData)
    }
  })
  
  return result
})

// 响应式数据
const activeTab = ref('changes')
const detailActiveTab = ref('social') // 明细分类标签页
const loading = ref(false)
const detailLoading = ref(false)
const exportLoading = ref(false)

// 生成参保登记表相关
const selectedTasks = ref([])
const isGeneratingReports = ref(false)

// 导出相关
const showExportDialog = ref(false)
const selectedTemplateId = ref(null)
const exportRange = ref('current')
const exportTemplates = ref([])
const selectedTemplate = computed(() => {
  return exportTemplates.value.find(t => t.id === selectedTemplateId.value)
})

// 折叠状态管理
const collapsedStates = ref({})

// 切换员工分组的折叠状态
const toggleCollapse = (employeeId) => {
  collapsedStates.value[employeeId] = !collapsedStates.value[employeeId]
}
const summaryLoading = ref(false)
const uploading = ref(false)
const processing = ref(false)
const processingOtherInsurance = ref(false)

// 数据
const changes = ref([])
const details = ref([])
const summaries = ref([])
const regions = ref([])
const rawCompensationData = ref([])
const socialSecurityRegions = ref([])

// 获取当前月份
const getCurrentMonth = () => {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  return `${year}-${month}`
}

// 带标题的社保明细数据
const socialSecurityDetailsWithTitle = computed(() => {
  const titleRow = {
    isTitleRow: true,
    title: getSocialSecurityTitle(),
    serial_number: '',
    employee_name: '',
    id_number: '',
    project_name: '',
    enrollment_date: '',
    type: '',
    period: '',
    medical_base: '',
    social_security_base: '',
    // 添加其他必要的空字段
    medical_insurance: '',
    pension_insurance: '',
    unemployment_insurance: '',
    work_injury_insurance: '',
    maternity_insurance: '',
    large_medical_insurance: '',
    housing_fund_base: '',
    housing_fund_personal: '',
    housing_fund_company: '',
    housing_fund_total: ''
  }
  
  // 不再添加合计行，因为socialSecurityDetails.value已经包含了所有数据
  return [titleRow, ...socialSecurityDetails.value]
})

// 带标题的公积金明细数据
const housingFundDetailsWithTitle = computed(() => {
  const titleRow = {
    isTitleRow: true,
    title: getHousingFundTitle(),
    serial_number: '',
    employee_name: '',
    id_number: '',
    project_name: '',
    enrollment_date: '',
    type: '',
    period: '',
    housing_fund_base: '',
    ratio: '',
    company_portion: '',
    employee_portion: '',
    housing_fund_total: '',
    remarks: ''
  }
  
  return [titleRow, ...housingFundDetails.value]
})

// 计算小计行
const calculateSummaryRow = (data, summaryType = '小计') => {
  if (!data || data.length === 0) {
    return {
      isSummaryRow: true,
      serial_number: '',
      employee_name: summaryType,
      id_number: '',
      project_name: '',
      enrollment_date: '',
      type: '',
      period: '',
      medical_base: '0.00',
      social_security_base: '0.00',
      company_total: '0.00',
      employee_total: '0.00',
      social_security_total: '0.00',
      remarks: ''
    }
  }
  
  // 计算各项小计
  let totalMedicalBase = 0
  let totalSocialBase = 0
  let totalCompanyAmount = 0
  let totalEmployeeAmount = 0
  let totalSocialSecurityAmount = 0
  
  data.forEach(row => {
    totalMedicalBase += parseFloat(row.medical_base || 0)
    totalSocialBase += parseFloat(row.social_security_base || 0)
    totalCompanyAmount += parseFloat(row.company_total || 0)
    totalEmployeeAmount += parseFloat(row.employee_total || 0)
    totalSocialSecurityAmount += parseFloat(row.social_security_total || 0)
  })
  
  // 计算动态列的小计
  const dynamicTotals = {}
  data.forEach(row => {
    Object.keys(row).forEach(key => {
      if (key.startsWith('company_') || key.startsWith('employee_')) {
        if (!dynamicTotals[key]) {
          dynamicTotals[key] = 0
        }
        dynamicTotals[key] += parseFloat(row[key] || 0)
      }
    })
  })
  
  // 将动态列的小计保留两位小数
  Object.keys(dynamicTotals).forEach(key => {
    dynamicTotals[key] = dynamicTotals[key].toFixed(2)
  })
  
  return {
    isSummaryRow: true,
    serial_number: '',
    employee_name: summaryType,
    id_number: '',
    project_name: '',
    enrollment_date: '',
    type: '',
    period: '',
    medical_base: totalMedicalBase.toFixed(2),
    social_security_base: totalSocialBase.toFixed(2),
    company_total: totalCompanyAmount.toFixed(2),
    employee_total: totalEmployeeAmount.toFixed(2),
    social_security_total: totalSocialSecurityAmount.toFixed(2),
    remarks: '',
    ...dynamicTotals
  }
}

// 计算公积金小计行
const calculateHousingFundSummaryRow = (data, summaryType = '小计') => {
  if (!data || data.length === 0) {
    return {
      isSummaryRow: true,
      serial_number: '',
      employee_name: summaryType,
      id_number: '',
      project_name: '',
      enrollment_date: '',
      type: '',
      period: '',
      housing_fund_base: '0.00',
      ratio: '0',
      company_portion: '0.00',
      employee_portion: '0.00',
      housing_fund_total: '0.00',
      remarks: ''
    }
  }
  
  let totalBase = 0
  let totalCompanyPortion = 0
  let totalEmployeePortion = 0
  let totalHousingFund = 0
  
  data.forEach(row => {
    totalBase += parseFloat(row.housing_fund_base || 0)
    totalCompanyPortion += parseFloat(row.company_portion || 0)
    totalEmployeePortion += parseFloat(row.employee_portion || 0)
    totalHousingFund += parseFloat(row.housing_fund_total || 0)
  })
  
  return {
    isSummaryRow: true,
    serial_number: '',
    employee_name: summaryType,
    id_number: '',
    project_name: '',
    enrollment_date: '',
    type: '',
    period: '',
    housing_fund_base: totalBase.toFixed(2),
    ratio: '0',
    company_portion: totalCompanyPortion.toFixed(2),
    employee_portion: totalEmployeePortion.toFixed(2),
    housing_fund_total: totalHousingFund.toFixed(2),
    remarks: ''
  }
}

// 计算公积金合计行（从两个小计计算）
const calculateHousingFundTotalFromSummaries = (summaryRows) => {
  if (summaryRows.length === 0) {
    return {
      isTotalRow: true,
      serial_number: '',
      employee_name: '合计',
      id_number: '',
      project_name: '',
      enrollment_date: '',
      type: '',
      period: '',
      housing_fund_base: '0.00',
      ratio: '0',
      company_portion: '0.00',
      employee_portion: '0.00',
      housing_fund_total: '0.00',
      remarks: ''
    }
  }
  
  let totalBase = 0
  let totalCompanyPortion = 0
  let totalEmployeePortion = 0
  let totalHousingFund = 0
  
  summaryRows.forEach(row => {
    totalBase += parseFloat(row.housing_fund_base || 0)
    totalCompanyPortion += parseFloat(row.company_portion || 0)
    totalEmployeePortion += parseFloat(row.employee_portion || 0)
    totalHousingFund += parseFloat(row.housing_fund_total || 0)
  })
  
  return {
    isTotalRow: true,
    serial_number: '',
    employee_name: '合计',
    id_number: '',
    project_name: '',
    enrollment_date: '',
    type: '',
    period: '',
    housing_fund_base: totalBase.toFixed(2),
    ratio: '0',
    company_portion: totalCompanyPortion.toFixed(2),
    employee_portion: totalEmployeePortion.toFixed(2),
    housing_fund_total: totalHousingFund.toFixed(2),
    remarks: ''
  }
}

// 计算总计行（只计算两个小计的值）
const calculateTotalFromSummaries = (summaryRows) => {
  // 直接接收小计行数组
  
  if (summaryRows.length === 0) {
    return {
      isTotalRow: true,
      serial_number: '',
      employee_name: '合计',
      id_number: '',
      project_name: '',
      enrollment_date: '',
      type: '',
      period: '',
      medical_base: '0.00',
      social_security_base: '0.00',
      company_total: '0.00',
      employee_total: '0.00',
      social_security_total: '0.00',
      remarks: ''
    }
  }
  
  // 计算所有小计行的合计
  let totalMedicalBase = 0
  let totalSocialBase = 0
  let totalCompanyAmount = 0
  let totalEmployeeAmount = 0
  let totalSocialSecurityAmount = 0
  
  summaryRows.forEach(row => {
    totalMedicalBase += parseFloat(row.medical_base || 0)
    totalSocialBase += parseFloat(row.social_security_base || 0)
    totalCompanyAmount += parseFloat(row.company_total || 0)
    totalEmployeeAmount += parseFloat(row.employee_total || 0)
    totalSocialSecurityAmount += parseFloat(row.social_security_total || 0)
  })
  
  // 计算动态列的合计
  const dynamicTotals = {}
  summaryRows.forEach(row => {
    Object.keys(row).forEach(key => {
      if (key.startsWith('company_') || key.startsWith('employee_')) {
        if (!dynamicTotals[key]) {
          dynamicTotals[key] = 0
        }
        dynamicTotals[key] += parseFloat(row[key] || 0)
      }
    })
  })
  
  // 将动态列的合计保留两位小数
  Object.keys(dynamicTotals).forEach(key => {
    dynamicTotals[key] = dynamicTotals[key].toFixed(2)
  })
  
  return {
    isTotalRow: true,
    serial_number: '',
    employee_name: '合计',
    id_number: '',
    project_name: '',
    enrollment_date: '',
    type: '',
    period: '',
    medical_base: totalMedicalBase.toFixed(2),
    social_security_base: totalSocialBase.toFixed(2),
    company_total: totalCompanyAmount.toFixed(2),
    employee_total: totalEmployeeAmount.toFixed(2),
    social_security_total: totalSocialSecurityAmount.toFixed(2),
    remarks: '',
    ...dynamicTotals
  }
}

// 计算合计行
const calculateTotalRow = (data) => {
  if (!data || data.length === 0) {
    return {
      isTotalRow: true,
      serial_number: '',
      employee_name: '合计',
      id_number: '',
      project_name: '',
      enrollment_date: '',
      type: '',
      period: '',
      medical_base: '0.00',
      social_security_base: '0.00',
      company_total: '0.00',
      employee_total: '0.00',
      social_security_total: '0.00',
      remarks: ''
    }
  }
  
  // 计算各项合计
  let totalMedicalBase = 0
  let totalSocialBase = 0
  let totalCompanyAmount = 0
  let totalEmployeeAmount = 0
  let totalSocialSecurityAmount = 0
  
  data.forEach(row => {
    totalMedicalBase += parseFloat(row.medical_base || 0)
    totalSocialBase += parseFloat(row.social_security_base || 0)
    totalCompanyAmount += parseFloat(row.company_total || 0)
    totalEmployeeAmount += parseFloat(row.employee_total || 0)
    totalSocialSecurityAmount += parseFloat(row.social_security_total || 0)
  })
  
  // 计算动态列的合计
  const dynamicTotals = {}
  data.forEach(row => {
    Object.keys(row).forEach(key => {
      if (key.startsWith('company_') || key.startsWith('employee_')) {
        if (!dynamicTotals[key]) {
          dynamicTotals[key] = 0
        }
        dynamicTotals[key] += parseFloat(row[key] || 0)
      }
    })
  })
  
  return {
    isTotalRow: true,
    serial_number: '',
    employee_name: '',
    id_number: '',
    project_name: '',
    enrollment_date: '',
    type: '',
    period: '',
    medical_base: totalMedicalBase.toFixed(2),
    social_security_base: totalSocialBase.toFixed(2),
    company_total: totalCompanyAmount.toFixed(2),
    employee_total: totalEmployeeAmount.toFixed(2),
    social_security_total: totalSocialSecurityAmount.toFixed(2),
    remarks: '',
    ...dynamicTotals
  }
}

// 社保明细表格单元格合并方法
const socialSecuritySpanMethod = ({ row, column, rowIndex, columnIndex }) => {
  // 如果是标题行（第一行），合并所有列
  if (row.isTitleRow) {
    if (columnIndex === 0) {
      // 第一列显示标题，合并所有列
      // 计算总列数：基础列(9) + 动态单位列 + 动态个人列 + 合计列(3) + 备注(1)
      const totalColumns = 9 + (dynamicCompanyColumns.value.length + 1) + (dynamicEmployeeColumns.value.length + 1) + 3 + 1
      return {
        rowspan: 1,
        colspan: totalColumns
      }
    } else {
      // 其他列隐藏
      return {
        rowspan: 0,
        colspan: 0
      }
    }
  }
  
  // 数据行正常显示
  return {
    rowspan: 1,
    colspan: 1
  }
}

// 公积金明细表格合并方法
const housingFundSpanMethod = ({ row, column, rowIndex, columnIndex }) => {
  // 如果是标题行（第一行），合并所有列
  if (row.isTitleRow) {
    if (columnIndex === 0) {
      // 第一列显示标题，合并所有列
      // 计算总列数：13列（序号、姓名、身份证号、项目、参保日期、类型、费款所属期、公积金基数、比例、单位部分、个人部分、公积金合计、备注）
      const totalColumns = 13
      return {
        rowspan: 1,
        colspan: totalColumns
      }
    } else {
      // 其他列隐藏
      return {
        rowspan: 0,
        colspan: 0
      }
    }
  }
  
  // 数据行正常显示
  return {
    rowspan: 1,
    colspan: 1
  }
}

// 加载社保地区列表
const loadSocialSecurityRegions = async () => {
  if (!currentAccountSetId.value) {
    return
  }
  
  try {
    const response = await getSocialSecurityRegions({
      account_set_id: currentAccountSetId.value
    })
    
    if (response.success) {
      socialSecurityRegions.value = response.data || []
      // 将社保地区名称提取到regions数组中
      regions.value = socialSecurityRegions.value.map(region => region.name)
    } else {
      console.warn('加载社保地区失败:', response.message)
      regions.value = []
    }
  } catch (error) {
    console.error('加载社保地区失败:', error)
    regions.value = []
  }
}

// 生成汇总表
const generateSummaryTable = async () => {
  if (!socialSecurityDetails.value || socialSecurityDetails.value.length === 0) {
    ElMessage.warning('没有数据可生成汇总表')
    return
  }

  try {
    summaryLoading.value = true
    
    // 获取当前月份
    const currentMonth = detailFilterForm.value.month || getCurrentMonth()
    const [year, month] = currentMonth.split('-')
    const monthText = `${year}年${month}月`
    
    // 按项目分组数据
    const projectGroups = {}
    
    // 过滤掉小计和合计行，只处理实际数据
    const actualData = socialSecurityDetails.value.filter(item => 
      item.employee_name && 
      item.employee_name !== '小计' && 
      item.employee_name !== '合计'
    )
    
    actualData.forEach(item => {
      const projectName = item.project_name || '未分配项目'
      if (!projectGroups[projectName]) {
        projectGroups[projectName] = {
          normal: [],
          supplementary: []
        }
      }
      
      if (item.type === '正常') {
        projectGroups[projectName].normal.push(item)
      } else if (item.type === '补交') {
        projectGroups[projectName].supplementary.push(item)
      }
    })
    
    // 生成汇总表数据
    const summaryData = []
    let totalNormal = { company: {}, employee: {}, counts: { social: 0, medical: 0 } }
    let totalSupplementary = { company: {}, employee: {}, counts: { social: 0, medical: 0 } }
    
    // 处理每个项目的数据
    Object.keys(projectGroups).forEach(projectName => {
      const group = projectGroups[projectName]
      
      // 正常数据汇总
      if (group.normal.length > 0) {
        const normalSummary = calculateProjectSummary(group.normal, projectName, '正常', currentMonth)
        summaryData.push(normalSummary)
        
        // 累加到总计
        addToTotal(totalNormal, normalSummary)
      }
    })
    
    // 添加小计行 - 正常数据小计
    if (Object.keys(totalNormal).length > 0 && (totalNormal.company && Object.keys(totalNormal.company).length > 0)) {
      summaryData.push({
        project_name: '小计',
        category: '正常',
        period: `${year}.${month}-${year}.${month}`,
        ...totalNormal
      })
    }
    
    // 添加小计行 - 补交数据小计
    if (Object.keys(totalSupplementary).length > 0 && (totalSupplementary.company && Object.keys(totalSupplementary.company).length > 0)) {
      summaryData.push({
        project_name: '小计',
        category: '补交',
        period: `${year}.${month}-${year}.${month}`,
        ...totalSupplementary
      })
    }
    
    // 补交数据汇总（放在第二个小计后面）
    Object.keys(projectGroups).forEach(projectName => {
      const group = projectGroups[projectName]
      
      // 补交数据汇总
      if (group.supplementary.length > 0) {
        const supplementarySummary = calculateProjectSummary(group.supplementary, projectName, '补交', currentMonth)
        summaryData.push(supplementarySummary)
        
        // 累加到总计
        addToTotal(totalSupplementary, supplementarySummary)
      }
    })
    
    // 添加补交项目小计（在补交数据后面）
    if (Object.keys(totalSupplementary).length > 0 && (totalSupplementary.company && Object.keys(totalSupplementary.company).length > 0)) {
      summaryData.push({
        project_name: '小计',
        category: '补交项目',
        period: `${year}.${month}-${year}.${month}`,
        ...totalSupplementary
      })
    }
    
    // 添加合计行
    const grandTotal = calculateGrandTotal(totalNormal, totalSupplementary)
    if (grandTotal) {
      summaryData.push(grandTotal)
    }
    
    // 从details中获取社保编号和医保编号（从第一条记录中提取，同一地区的编号应该相同）
    let socialSecurityCode = ''
    let medicalInsuranceCode = ''
    if (details.value && details.value.length > 0) {
      // 尝试从第一条记录获取编号
      socialSecurityCode = details.value[0].social_security_code || ''
      medicalInsuranceCode = details.value[0].medical_insurance_code || ''
    }
    
    // 导出汇总表
    await exportSummaryTableToExcel(summaryData, monthText, socialSecurityCode, medicalInsuranceCode)
    
    ElMessage.success('汇总表生成成功')
  } catch (error) {
    console.error('生成汇总表失败:', error)
    ElMessage.error('生成汇总表失败，请重试')
  } finally {
    summaryLoading.value = false
  }
}

// 计算项目汇总数据
const calculateProjectSummary = (items, projectName, category, period) => {
  // 修正所属期格式
  let formattedPeriod = period
  if (category === '正常') {
    // 正常数据：使用当前月份格式，如 2025.10-2025.10
    const [year, month] = period.split('-')
    formattedPeriod = `${year}.${month}-${year}.${month}`
  } else if (category === '补交') {
    // 补交数据：根据实际数据计算所属期，统一格式为 YYYY.MM-YYYY.MM
    if (items.length > 0) {
      const periods = items.map(item => item.period).filter(p => p)
      if (periods.length > 0) {
        // 转换格式并排序
        const formattedPeriods = periods.map(p => {
          // 如果是 YYYYMM 格式，转换为 YYYY.MM
          if (p && p.length === 6 && /^\d{6}$/.test(p)) {
            return `${p.substring(0, 4)}.${p.substring(4, 6)}`
          }
          return p
        }).filter(p => p)
        
        const uniquePeriods = [...new Set(formattedPeriods)].sort()
        if (uniquePeriods.length === 1) {
          formattedPeriod = `${uniquePeriods[0]}-${uniquePeriods[0]}`
        } else {
          formattedPeriod = `${uniquePeriods[0]}-${uniquePeriods[uniquePeriods.length - 1]}`
        }
      }
    }
  }
  
  const summary = {
    project_name: projectName,
    category: category,
    period: formattedPeriod,
    company: {},
    employee: {},
    counts: { social: 0, medical: 0 }
  }
  
  // 统计人数 - 直接计数记录数（参考公积金汇总表的逻辑）
  let socialCount = 0
  let medicalCount = 0
  
  items.forEach(item => {
    // 跳过标题行、小计行、合计行
    if (item.isTitleRow || item.isSummaryRow || item.isTotalRow) {
      return
    }
    
    // 检查是否有社保相关金额
    let hasSocialAmount = false
    let hasMedicalAmount = false
    
    // 检查所有动态列的金额
    dynamicCompanyColumns.value.forEach(column => {
      const fieldName = 'company_' + (column.fieldPrefix || '') + column.name
      if (parseFloat(item[fieldName] || 0) > 0) {
        // 根据列类型判断是社保还是医保
        if (column.type === 'social_security') {
          hasSocialAmount = true
        } else if (column.type === 'medical_insurance' || column.type === 'large_medical') {
          hasMedicalAmount = true
        } else {
          // 如果没有类型标识，默认都算
          hasSocialAmount = true
          hasMedicalAmount = true
        }
      }
    })
    
    dynamicEmployeeColumns.value.forEach(column => {
      const fieldName = 'employee_' + (column.fieldPrefix || '') + column.name
      if (parseFloat(item[fieldName] || 0) > 0) {
        // 根据列类型判断是社保还是医保
        if (column.type === 'social_security') {
          hasSocialAmount = true
        } else if (column.type === 'medical_insurance' || column.type === 'large_medical') {
          hasMedicalAmount = true
        } else {
          // 如果没有类型标识，默认都算
          hasSocialAmount = true
          hasMedicalAmount = true
        }
      }
    })
    
    // 检查基数
    if (parseFloat(item.social_security_base || 0) > 0) {
      hasSocialAmount = true
    }
    if (parseFloat(item.medical_base || 0) > 0) {
      hasMedicalAmount = true
    }
    
    // 直接计数（每条记录算一个人）
    if (hasSocialAmount || hasMedicalAmount) {
      // 简化处理：有任何保险就都算上
      socialCount++
      medicalCount++
    }
    
    // 累加动态列数据
    dynamicCompanyColumns.value.forEach(column => {
      const fieldName = 'company_' + (column.fieldPrefix || '') + column.name
      const amount = parseFloat(item[fieldName] || 0)
      summary.company[fieldName] = (summary.company[fieldName] || 0) + amount
    })
    
    dynamicEmployeeColumns.value.forEach(column => {
      const fieldName = 'employee_' + (column.fieldPrefix || '') + column.name
      const amount = parseFloat(item[fieldName] || 0)
      summary.employee[fieldName] = (summary.employee[fieldName] || 0) + amount
    })
  })
  
  // 设置统计的人数
  summary.counts.social = socialCount
  summary.counts.medical = medicalCount
  
  return summary
}

// 累加到总计
const addToTotal = (total, summary) => {
  total.counts.social += summary.counts.social
  total.counts.medical += summary.counts.medical
  
  Object.keys(summary.company).forEach(key => {
    total.company[key] = (total.company[key] || 0) + summary.company[key]
  })
  
  Object.keys(summary.employee).forEach(key => {
    total.employee[key] = (total.employee[key] || 0) + summary.employee[key]
  })
}

// 计算合计
const calculateGrandTotal = (totalNormal, totalSupplementary) => {
  if (!totalNormal.company || !totalSupplementary.company) return null
  
  const grandTotal = {
    project_name: '合计',
    category: '总计',
    period: '',
    company: {},
    employee: {},
    counts: { 
      social: totalNormal.counts.social + totalSupplementary.counts.social,
      medical: totalNormal.counts.medical + totalSupplementary.counts.medical
    }
  }
  
  // 合并公司部分
  Object.keys(totalNormal.company).forEach(key => {
    grandTotal.company[key] = (totalNormal.company[key] || 0) + (totalSupplementary.company[key] || 0)
  })
  
  // 合并个人部分
  Object.keys(totalNormal.employee).forEach(key => {
    grandTotal.employee[key] = (totalNormal.employee[key] || 0) + (totalSupplementary.employee[key] || 0)
  })
  
  return grandTotal
}

// 导出汇总表到Excel（使用HTML格式，与明细表相同）
const exportSummaryTableToExcel = async (summaryData, monthText, socialSecurityCode = '', medicalInsuranceCode = '') => {
  // 构建HTML表格
  let html = `
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>汇邦人力${monthText}社保汇总表</title>
  <style>
    body {
      font-family: "Microsoft YaHei", "微软雅黑", Arial, sans-serif;
      margin: 20px;
    }
    .main-title {
      text-align: center;
      font-size: 20px;
      font-weight: bold;
      color: #333;
      background-color: #E8F5E9;
      padding: 18px;
      border: 2px solid #000;
      margin-bottom: 10px;
    }
    .sub-title {
      text-align: left;
      font-size: 14px;
      font-weight: bold;
      color: #333;
      background-color: #C8E6C9;
      padding: 12px;
      border: 1px solid #000;
      margin-bottom: 5px;
    }
    table {
      border-collapse: collapse;
      width: 100%;
      font-size: 14px;
      margin-top: 10px;
    }
    th, td {
      border: 1px solid #000;
      padding: 12px;
      text-align: center;
      vertical-align: middle;
      min-width: 80px;
      height: 40px;
    }
    th {
      background-color: #E8F5E9;
      font-weight: bold;
      font-size: 14px;
      color: #333;
      height: 40px;
    }
    .data-row {
      background-color: #FFFFFF;
      height: 35px;
    }
    .subtotal-row {
      background-color: #FFF9C4;
      font-weight: bold;
      font-size: 14px;
      color: #333;
      height: 40px;
    }
    .total-row {
      background-color: #FFE082;
      font-weight: bold;
      font-size: 14px;
      color: #333;
      height: 40px;
    }
    .footer-info {
      margin-top: 30px;
      font-size: 14px;
    }
    .footer-row {
      margin-bottom: 10px;
    }
  </style>
</head>
<body>
  <table>
    <thead>
      <tr>
        <th colspan="${5 + dynamicCompanyColumns.value.length + dynamicEmployeeColumns.value.length + 3}" class="main-title">汇邦人力${monthText}社保汇总表</th>
      </tr>
      <tr>
        <th colspan="${5 + dynamicCompanyColumns.value.length + dynamicEmployeeColumns.value.length + 3}" class="sub-title">社保编号:${socialSecurityCode || '未设置'}</th>
      </tr>
      <tr>
        <th colspan="${5 + dynamicCompanyColumns.value.length + dynamicEmployeeColumns.value.length + 3}" class="sub-title">医保编号:${medicalInsuranceCode || '未设置'}</th>
      </tr>
      <tr>
        <th rowspan="2">项目名称</th>
        <th rowspan="2">社保缴费人数</th>
        <th rowspan="2">医保缴费人数</th>
        <th rowspan="2">类别</th>
        <th rowspan="2">所属期</th>
        <th colspan="${dynamicCompanyColumns.value.length}">单位部分</th>
        <th colspan="${dynamicEmployeeColumns.value.length}">个人部分</th>
        <th colspan="2">实缴金额</th>
        <th rowspan="2">合计</th>
      </tr>
      <tr>
  `
  
  // 添加动态列头
  dynamicCompanyColumns.value.forEach(column => {
    html += `<th>${column.name}</th>`
  })
  
  dynamicEmployeeColumns.value.forEach(column => {
    html += `<th>${column.name}</th>`
  })
  
  html += `
        <th>单位本金</th>
        <th>个人本金</th>
      </tr>
    </thead>
    <tbody>
  `
  
  // 添加数据行
  summaryData.forEach(item => {
    // 判断行类型
    let rowClass = 'data-row'
    if (item.project_name === '小计') {
      rowClass = 'subtotal-row'
    } else if (item.project_name === '合计') {
      rowClass = 'total-row'
    }
    
    html += `<tr class="${rowClass}">`
    html += `<td>${item.project_name}</td>`
    html += `<td>${item.counts.social}</td>`
    html += `<td>${item.counts.medical}</td>`
    html += `<td>${item.category}</td>`
    html += `<td>${item.period}</td>`
    
    // 添加动态列数据
    dynamicCompanyColumns.value.forEach(column => {
      const fieldName = 'company_' + (column.fieldPrefix || '') + column.name
      const amount = (item.company[fieldName] || 0).toFixed(2)
      html += `<td>${amount}</td>`
    })
    
    dynamicEmployeeColumns.value.forEach(column => {
      const fieldName = 'employee_' + (column.fieldPrefix || '') + column.name
      const amount = (item.employee[fieldName] || 0).toFixed(2)
      html += `<td>${amount}</td>`
    })
    
    // 计算单位本金和个人本金
    const companyTotal = Object.values(item.company).reduce((sum, val) => sum + (val || 0), 0)
    const employeeTotal = Object.values(item.employee).reduce((sum, val) => sum + (val || 0), 0)
    
    html += `<td>${companyTotal.toFixed(2)}</td>`
    html += `<td>${employeeTotal.toFixed(2)}</td>`
    html += `<td>${(companyTotal + employeeTotal).toFixed(2)}</td>`
    html += `</tr>`
  })
  
  html += `
    </tbody>
  </table>
  
  <div class="footer-info">
    <div class="footer-row">制表人：&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;审核人：</div>
    <div class="footer-row" style="text-align: right;">鄂尔多斯市汇邦人力资源有限责任公司</div>
    <div class="footer-row" style="text-align: right;">日期：${new Date().toLocaleDateString()}</div>
  </div>
</body>
</html>
  `
  
  // 创建并下载文件
  const blob = new Blob([html], { 
    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' 
  })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `汇邦人力${monthText}社保汇总表.xlsx`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}

// 导出社保明细Excel
const exportSocialSecurityExcel = async () => {
  if (!socialSecurityDetails.value || socialSecurityDetails.value.length === 0) {
    ElMessage.warning('没有数据可导出')
    return
  }
  
  exportLoading.value = true
  
  try {
    // 生成文件名
    const regionName = detailFilterForm.value.region_name || '全部地区'
    const month = detailFilterForm.value.month || getCurrentMonth()
    const formattedMonth = month.replace('-', '年') + '月'
    const filename = `${regionName}汇邦人力资源有限公司${formattedMonth}社保明细.xlsx`
    
    // 准备列配置
    const columns = [
      { label: '序号', prop: 'serial_number', width: 60 },
      { label: '姓名', prop: 'employee_name', width: 100 },
      { label: '身份证号', prop: 'id_number', width: 180 },
      { label: '项目', prop: 'project_name', width: 120 },
      { label: '参保日期', prop: 'enrollment_date', width: 100 },
      { label: '类型', prop: 'type', width: 80 },
      { label: '费款所属期', prop: 'period', width: 100 },
      { label: '医保基数', prop: 'medical_base', width: 100 },
      { label: '社保基数', prop: 'social_security_base', width: 100 }
    ]
    
    // 添加动态单位部分列
    dynamicCompanyColumns.value.forEach(column => {
      columns.push({
        label: column.name,
        prop: 'company_' + (column.fieldPrefix || '') + column.name,
        width: 120
      })
    })
    columns.push({ label: '单位缴纳保险合计', prop: 'company_total', width: 150 })
    
    // 添加动态个人部分列
    dynamicEmployeeColumns.value.forEach(column => {
      columns.push({
        label: column.name,
        prop: 'employee_' + (column.fieldPrefix || '') + column.name,
        width: 120
      })
    })
    columns.push({ label: '个人缴纳保险合计', prop: 'employee_total', width: 150 })
    
    // 添加其他列
    columns.push(
      { label: '社保合计', prop: 'social_security_total', width: 120 },
      { label: '备注', prop: 'remarks', width: 100 }
    )
    
    // 导出Excel
    exportSocialSecurityToExcelHTML(
      socialSecurityDetailsWithTitle.value,
      getSocialSecurityTitle(),
      columns,
      filename,
      dynamicCompanyColumns.value
    )
    
    ElMessage.success('导出成功')
  } catch (error) {
    console.error('导出Excel失败:', error)
    ElMessage.error('导出失败，请重试')
  } finally {
    exportLoading.value = false
  }
}

// 导出公积金明细Excel
const exportHousingFundExcel = async () => {
  if (!housingFundDetails.value || housingFundDetails.value.length === 0) {
    ElMessage.warning('没有数据可导出')
    return
  }

  exportLoading.value = true

  try {
    // 生成文件名
    const month = detailFilterForm.value.month || getCurrentMonth()
    const formattedMonth = month.replace('-', '')
    const filename = `汇邦人力${formattedMonth}公积金明细.xlsx`

    // 准备列配置
    const columns = [
      { label: '序号', prop: 'serial_number' },
      { label: '姓名', prop: 'employee_name' },
      { label: '身份证号', prop: 'id_number' },
      { label: '项目', prop: 'project_name' },
      { label: '参保日期', prop: 'enrollment_date' },
      { label: '类型', prop: 'type' },
      { label: '费款所属期', prop: 'period' },
      { label: '公积金基数', prop: 'housing_fund_base' },
      { label: '比例', prop: 'ratio' },
      { label: '单位部分', prop: 'company_portion' },
      { label: '个人部分', prop: 'employee_portion' },
      { label: '公积金合计', prop: 'housing_fund_total' },
      { label: '备注', prop: 'remarks' }
    ]

    // 导出Excel（使用通用导出工具）
    exportToExcelHTML(
      housingFundDetailsWithTitle.value,
      getHousingFundTitle(),
      columns,
      filename
    )

    ElMessage.success('导出成功')
  } catch (error) {
    console.error('导出Excel失败:', error)
    ElMessage.error('导出失败，请重试')
  } finally {
    exportLoading.value = false
  }
}

// 导出公积金汇总表
const exportHousingFundSummaryAction = async () => {
  if (!housingFundDetails.value || housingFundDetails.value.length === 0) {
    ElMessage.warning('没有数据可导出')
    return
  }

  exportLoading.value = true

  try {
    // 生成文件名和标题
    const month = detailFilterForm.value.month || getCurrentMonth()
    let formattedMonth = month
    if (month && month.includes('-')) {
      const [year, monthNum] = month.split('-')
      formattedMonth = `${year}年${monthNum.padStart(2, '0')}月`
    }
    const title = `汇邦人力${formattedMonth}公积金汇总表`
    const filename = `${title}.xlsx`

    // 导出汇总表
    exportHousingFundSummaryToExcel(housingFundDetails.value, title, filename)

    ElMessage.success('导出成功')
  } catch (error) {
    console.error('导出汇总表失败:', error)
    ElMessage.error('导出失败，请重试')
  } finally {
    exportLoading.value = false
  }
}

// 导出社保补差明细Excel
const exportCompensationExcel = async () => {
  if (!compensationDetails.value || compensationDetails.value.length === 0) {
    ElMessage.warning('没有数据可导出')
    return
  }

  exportLoading.value = true

  try {
    // 动态导入 XLSX
    const XLSX = await import('xlsx')
    
    // 生成文件名
    const month = detailFilterForm.value.month || getCurrentMonth()
    const filename = `社保补交明细_${month}.xlsx`
    
    // 准备表头
    const headers = [
      '序号', '姓名', '身份证号', '项目', '补差时段', '补差月数', '旧基数', '新基数', '类型'
    ]
    
    // 添加动态列头
    dynamicCompensationColumns.value.forEach(col => {
      headers.push(`单位_${col.name}`)
    })
    headers.push('单位补差合计')
    
    dynamicCompensationColumns.value.forEach(col => {
      headers.push(`个人_${col.name}`)
    })
    headers.push('个人补差合计')
    headers.push('补差总计')
    
    // 准备数据行
    const rows = compensationDetails.value.map(row => {
      const dataRow = [
        row.serial_number,
        row.employee_name,
        row.id_number,
        row.project_name,
        row.compensation_period,
        row.compensation_months,
        row.old_base,
        row.new_base,
        '补差'
      ]
      
      // 添加单位部分数据
      dynamicCompensationColumns.value.forEach(col => {
        dataRow.push(row[`company_${col.name}`] || '0.00')
      })
      dataRow.push(row.company_total)
      
      // 添加个人部分数据
      dynamicCompensationColumns.value.forEach(col => {
        dataRow.push(row[`employee_${col.name}`] || '0.00')
      })
      dataRow.push(row.employee_total)
      dataRow.push(row.total)
      
      return dataRow
    })
    
    // 创建工作表
    const worksheet = XLSX.utils.aoa_to_sheet([headers, ...rows])
    
    // 设置列宽
    const colWidths = headers.map((_, index) => {
      if (index === 2) return { wch: 20 } // 身份证号
      if (index === 4) return { wch: 20 } // 补差时段
      return { wch: 12 }
    })
    worksheet['!cols'] = colWidths
    
    // 创建工作簿
    const workbook = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(workbook, worksheet, '社保补交明细')
    
    // 导出文件
    XLSX.writeFile(workbook, filename)
    
    ElMessage.success('导出成功')
  } catch (error) {
    console.error('导出失败:', error)
    ElMessage.error('导出失败，请重试')
  } finally {
    exportLoading.value = false
  }
}

// 筛选表单
const filterForm = ref({
  month: getCurrentMonth(), // 默认当前月份
  status: '',
  region_name: ''
})

const detailFilterForm = ref({
  month: getCurrentMonth(), // 默认当前月份
  region_name: ''
})

const summaryFilterForm = ref({
  region_name: ''
})

// 对话框
const showUploadDialogFlag = ref(false)
const showViewFilesDialogFlag = ref(false)
const showDetailDialogFlag = ref(false)
const currentChange = ref(null)

// 上传表单
const uploadForm = ref({
  attachment: null
})

const uploadFormRef = ref()
const viewFilesFormRef = ref()
const uploadRef = ref()
const fileList = ref([])

// 查看文件表单
const viewFilesForm = ref({})

// 加载参保人员列表
const loadChanges = async () => {
  if (!currentAccountSetId.value) {
    ElMessage.warning('请先选择账套')
    return
  }

  loading.value = true
  try {
    const response = await getInsuranceChanges({
      account_set_id: currentAccountSetId.value,
      ...filterForm.value
    })
      if (response.success) {
        // 设置初始化标志位，防止数据加载时触发开关事件
        isInitializing.value = true
        
        changes.value = response.data
        
        // 确保所有记录的大额医疗保险状态都是布尔值
        changes.value.forEach(change => {
          if (typeof change.large_medical_insurance_enabled === 'number') {
            change.large_medical_insurance_enabled = Boolean(change.large_medical_insurance_enabled)
          }
        })
      
      // 调试：检查附件数据
      console.log('=== 加载的保险变更数据 ===')
      changes.value.forEach((change, index) => {
        console.log(`记录${index + 1}:`, {
          id: change.id,
          employee_name: change.employee?.name,
          status: change.status,
          large_medical_insurance_enabled: change.large_medical_insurance_enabled,
          attachments: change.attachments,
          attachments_count: change.attachments ? change.attachments.length : 0
        })
      })
      
      // 地区列表现在直接从社保管理模块获取，不需要从员工数据中提取
    }
  } catch (error) {
    console.error('加载参保人员列表失败:', error)
    ElMessage.error('加载参保人员列表失败')
  } finally {
    loading.value = false
    // 使用nextTick确保数据加载完成后再重置标志位
    await nextTick(() => {
      isInitializing.value = false
    })
  }
}

// 处理任务选择变化
const handleTaskSelectionChange = (selection) => {
  selectedTasks.value = selection
}

// 判断任务是否可选（只有已完成的任务可选）
const isTaskSelectable = (row) => {
  return row.status === 'completed'
}

// 生成参保登记表
const generateRegistrationReports = async () => {
  if (selectedTasks.value.length === 0) {
    ElMessage.warning('请先选择任务')
    return
  }

  // 只允许选择已完成的任务
  const uncompletedTasks = selectedTasks.value.filter(t => t.status !== 'completed')
  if (uncompletedTasks.length > 0) {
    ElMessage.warning('只能选择已完成的任务')
    return
  }

  const loading = ElMessage({
    message: '正在生成参保登记表...',
    type: 'info',
    duration: 0
  })

  try {
    isGeneratingReports.value = true
    const taskIds = selectedTasks.value.map(t => t.id)

    // 1. 获取报表文件数据
    const response = await request.post('/insurance-changes/generate-registration-reports', {
      task_ids: taskIds,
      account_set_id: currentAccountSetId.value,
      month: filterForm.value.month
    })

    if (!response.success) {
      // 针对特定错误给出更友好的提示
      if (response.message && response.message.includes('未找到可用的报表模板')) {
        loading.close()
        ElMessage.warning('暂无可用的报表模板，请先在报表模板管理中创建模板')
        return
      }
      throw new Error(response.message || '生成报表失败')
    }

    const files = response.data

    if (!files || files.length === 0) {
      throw new Error('没有可生成的报表')
    }

    // 2. 使用 JSZip 打包文件
    loading.message = '正在打包文件...'

    const JSZip = (await import('jszip')).default
    const zip = new JSZip()

    for (const file of files) {
      // 解码 base64 内容
      const binaryContent = atob(file.content)
      const bytes = new Uint8Array(binaryContent.length)
      for (let i = 0; i < binaryContent.length; i++) {
        bytes[i] = binaryContent.charCodeAt(i)
      }

      zip.file(file.name, bytes)
    }

    // 3. 生成并下载 ZIP
    loading.message = '正在生成 ZIP 文件...'

    const zipBlob = await zip.generateAsync({ type: 'blob' })
    const url = URL.createObjectURL(zipBlob)
    const link = document.createElement('a')
    link.href = url
    const month = filterForm.value.month || getCurrentMonth()
    link.download = `参保登记表-${month}.zip`
    link.click()
    URL.revokeObjectURL(url)

    loading.close()
    ElMessage.success(`成功生成 ${files.length} 个报表文件`)
  } catch (error) {
    loading.close()
    console.error('生成报表失败:', error)
    ElMessage.error(error.message || '生成报表失败')
  } finally {
    isGeneratingReports.value = false
  }
}

// 加载明细列表
const loadDetails = async () => {
  if (!currentAccountSetId.value) {
    ElMessage.warning('请先选择账套')
    return
  }

  detailLoading.value = true
  try {
    const response = await getInsuranceChangeDetails({
      account_set_id: currentAccountSetId.value,
      ...detailFilterForm.value
    })
    if (response.success) {
      details.value = response.data
      
      // 初始化每个员工分组的折叠状态为展开
      nextTick(() => {
        groupedDetails.value.forEach(group => {
          if (collapsedStates.value[group.employee_id] === undefined) {
            collapsedStates.value[group.employee_id] = false // 默认展开
          }
        })
      })
    }
  } catch (error) {
    console.error('加载明细列表失败:', error)
    ElMessage.error('加载明细列表失败')
  } finally {
    detailLoading.value = false
  }
}

// 加载社保补差明细
const loadCompensationDetails = async () => {
  if (!currentAccountSetId.value) {
    ElMessage.warning('请先选择账套')
    return
  }
  
  detailLoading.value = true
  try {
    const response = await getSocialSecurityCompensationList({
      account_set_id: currentAccountSetId.value,
      month: detailFilterForm.value.month  // ✅ 添加月份筛选
    })
    if (response.success) {
      rawCompensationData.value = response.data || []
      console.log('社保补差数据加载成功:', rawCompensationData.value.length, '条', '月份:', detailFilterForm.value.month)
    } else {
      console.error('加载社保补差数据失败:', response.message)
    }
  } catch (error) {
    console.error('加载社保补差数据失败:', error)
    ElMessage.error('加载社保补差数据失败')
  } finally {
    detailLoading.value = false
  }
}

// 加载公积金补差明细
const loadHousingFundCompensationDetails = async () => {
  if (!currentAccountSetId.value) {
    ElMessage.warning('请先选择账套')
    return
  }
  
  detailLoading.value = true
  try {
    const response = await getHousingFundCompensationList({
      account_set_id: currentAccountSetId.value,
      month: detailFilterForm.value.month  // ✅ 添加月份筛选
    })
    if (response.success) {
      rawHousingFundCompensationData.value = response.data || []
      console.log('公积金补差数据加载成功:', rawHousingFundCompensationData.value.length, '条', '月份:', detailFilterForm.value.month)
    } else {
      console.error('加载公积金补差数据失败:', response.message)
    }
  } catch (error) {
    console.error('加载公积金补差数据失败:', error)
    ElMessage.error('加载公积金补差数据失败')
  } finally {
    detailLoading.value = false
  }
}

// 加载汇总列表
const loadSummaries = async () => {
  if (!currentAccountSetId.value) {
    ElMessage.warning('请先选择账套')
    return
  }

  summaryLoading.value = true
  try {
    const response = await getInsuranceChangeSummaries({
      account_set_id: currentAccountSetId.value,
      ...summaryFilterForm.value
    })
    if (response.success) {
      summaries.value = response.data
    }
  } catch (error) {
    console.error('加载汇总列表失败:', error)
    ElMessage.error('加载汇总列表失败')
  } finally {
    summaryLoading.value = false
  }
}

// 显示查看文件对话框
const showViewFilesDialog = (change) => {
  console.log('=== 打开查看文件对话框 ===')
  console.log('change对象:', change)
  console.log('attachments:', change.attachments)
  
  currentChange.value = change
  showViewFilesDialogFlag.value = true
}

// 显示上传对话框
const showUploadDialog = (change) => {
  console.log('=== 打开上传对话框 ===')
  console.log('change对象:', change)
  console.log('attachments:', change.attachments)
  console.log('attachments类型:', typeof change.attachments)
  console.log('attachments长度:', change.attachments ? change.attachments.length : 'undefined')
  
  if (change.attachments && change.attachments.length > 0) {
    console.log('附件详情:')
    change.attachments.forEach((att, index) => {
      console.log(`附件${index + 1}:`, att)
    })
  } else {
    console.log('没有附件数据')
  }
  
  currentChange.value = change
  showUploadDialogFlag.value = true
  fileList.value = []
}

// 文件选择（参考 Employees 的实现）
const handleFileChange = (file, fileListParam) => {
  console.log('=== 文件选择事件 ===')
  console.log('1. file 对象:', file)
  console.log('2. file.raw:', file.raw)
  console.log('3. fileList:', fileListParam)
  
  fileList.value = fileListParam
  
  console.log('4. 更新后的 fileList:', fileList.value)
}

// 文件移除（参考 Employees 的实现）
const handleFileRemove = (file, uploadFileList) => {
  fileList.value = uploadFileList
  console.log('文件已移除，剩余:', fileList.value)
}

// 文件超过限制
const handleFileExceed = () => {
  ElMessage.warning('最多只能上传10个文件')
}

// 下载附件
const handleDownloadAttachment = async (attachment) => {
  try {
    ElMessage.info('正在下载，请稍候...')
    
    const response = await fetch(`/storage/${attachment.file_path}`)
    if (!response.ok) {
      throw new Error('下载失败')
    }
    
    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = attachment.original_name
    link.style.display = 'none'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
    
    ElMessage.success('下载成功')
  } catch (error) {
    console.error('下载失败:', error)
    ElMessage.error('下载失败')
  }
}

// 预览附件
const handlePreviewAttachment = (attachment) => {
  const url = `/storage/${attachment.file_path}`
  window.open(url, '_blank')
}

// 删除附件
const handleDeleteAttachment = async (attachment) => {
  try {
    await ElMessageBox.confirm(
      `确定要删除附件"${attachment.original_name}"吗？此操作不可恢复！`,
      '确认删除',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
    
    const response = await request.delete(`/insurance-changes/attachments/${attachment.id}`)
    
    if (response.success) {
      ElMessage.success('附件删除成功')
      
      // 更新 currentChange 中的附件列表
      if (currentChange.value && currentChange.value.attachments) {
        currentChange.value.attachments = currentChange.value.attachments.filter(a => a.id !== attachment.id)
      }
      
      // 刷新列表数据
      loadChanges()
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除附件失败:', error)
      ElMessage.error(error.response?.data?.message || '删除附件失败')
    }
  }
}

// 提交上传（支持多文件）
const submitUpload = async () => {
  if (!fileList.value || fileList.value.length === 0) {
    ElMessage.warning('请选择要上传的文件')
    return
  }
  
  uploading.value = true
  try {
    console.log('=== 开始上传附件（多文件）===')
    console.log('fileList:', fileList.value)
    console.log('文件数量:', fileList.value.length)
    
    // 创建FormData，添加所有文件
    const formData = new FormData()
    fileList.value.forEach((file, index) => {
      console.log(`添加文件 ${index + 1}:`, file.raw.name)
      formData.append('attachments[]', file.raw)  // 使用 attachments[] 支持多文件
    })
    
    console.log('FormData已创建，开始请求后端...')
    
    // 直接使用 request 发送
    const response = await request.post(
      `/insurance-changes/${currentChange.value.id}/upload-attachment`,
      formData
    )
    
    console.log('上传响应:', response)
    
    if (response.success) {
      ElMessage.success(`成功上传 ${fileList.value.length} 个文件，请点击"确认处理"按钮完成处理`)
      
      // 更新当前记录的附件列表
      if (response.data && response.data.change) {
        currentChange.value = response.data.change
      }
      
      // 清空待上传列表
      fileList.value = []
      
      // 不关闭对话框，让用户可以继续查看和管理附件
      // showUploadDialogFlag.value = false
      
      // 刷新列表数据
      loadChanges()
    } else {
      throw new Error(response.message || '上传失败')
    }
  } catch (error) {
    console.error('上传附件失败:', error)
    ElMessage.error(error.response?.data?.message || error.message || '上传附件失败')
  } finally {
    uploading.value = false
  }
}

// 其他保险确认处理（只处理其他保险）
const confirmOtherInsuranceOnly = async (change) => {
  // 防止重复点击
  if (processingOtherInsurance.value) {
    return
  }
  
  try {
    await ElMessageBox.confirm(
      `确定要处理"${change.employee.name}"的其他保险吗？\n\n处理后将：\n1. 只更新其他保险明细\n2. 不影响社保、医保、公积金等其他数据\n3. 确认处理按钮和上传文件功能不受影响`,
      '其他保险确认处理',
      {
        confirmButtonText: '确定处理',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    processingOtherInsurance.value = true
    
    // 调用后端API只处理其他保险
    const response = await request.put(`/insurance-changes/${change.id}/confirm-other-insurance-only`)
    
    if (response.success) {
      ElMessage.success('其他保险已处理完成')
      loadChanges()
      // 如果当前在明细页面，也刷新明细数据
      if (activeTab.value === 'details') {
        loadDetails()
      }
      // 如果当前在汇总页面，也刷新汇总数据
      if (activeTab.value === 'summaries') {
        loadSummaries()
      }
    } else {
      ElMessage.error(response.message || '处理失败')
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('其他保险确认处理失败:', error)
      ElMessage.error(error.response?.data?.message || error.message || '处理失败')
    }
  } finally {
    processingOtherInsurance.value = false
  }
}

// 确认处理（从待处理状态更新为已处理）
const confirmProcess = async (change) => {
  // 防止重复点击
  if (processing.value) {
    return
  }
  
  try {
    await ElMessageBox.confirm(
      `确定要将"${change.employee.name}"的参保信息标记为已处理吗？\n\n处理后将：\n1. 状态更新为"已处理"\n2. 自动导入到参保明细\n3. 更新汇总统计\n4. "其他保险确认处理"按钮将不再显示`,
      '确认处理',
      {
        confirmButtonText: '确定处理',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    processing.value = true
    
    // 调用后端API更新状态为已处理
    const response = await request.put(`/insurance-changes/${change.id}/confirm-process`)
    
    if (response.success) {
      ElMessage.success('处理完成，数据已导入参保明细')
      loadChanges()
      // 如果当前在明细页面，也刷新明细数据
      if (activeTab.value === 'details') {
        loadDetails()
      }
      // 如果当前在汇总页面，也刷新汇总数据
      if (activeTab.value === 'summaries') {
        loadSummaries()
      }
      
      // 如果当前有打开的详情对话框，刷新对话框数据
      if (showDetailDialogFlag.value && currentChange.value && currentChange.value.id === change.id) {
        console.log('刷新详情对话框数据，ID:', change.id)
        try {
          const detailResponse = await request.get(`/insurance-changes/${change.id}?t=${Date.now()}`)
          if (detailResponse.success) {
            currentChange.value = detailResponse.data
            // 确保大额医疗保险状态是布尔值
            if (typeof detailResponse.data.large_medical_insurance_enabled === 'number') {
              currentChange.value.large_medical_insurance_enabled = Boolean(detailResponse.data.large_medical_insurance_enabled)
            }
            console.log('详情对话框数据已刷新，新状态:', currentChange.value.status)
          }
        } catch (error) {
          console.error('刷新详情对话框数据失败:', error)
        }
      }
    } else {
      throw new Error(response.message || '处理失败')
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('确认处理失败:', error)
      
      // 处理不同类型的错误
      if (error.response) {
        const status = error.response.status
        const message = error.response.data?.message || error.message
        
        if (status === 401) {
          ElMessage.error('登录已过期，请重新登录')
          // 不自动重定向，让用户手动操作
        } else if (status === 403) {
          ElMessage.error('没有权限执行此操作')
        } else if (status === 400) {
          ElMessage.warning(message || '请求参数错误')
        } else if (status >= 500) {
          ElMessage.error('服务器错误，请稍后重试')
        } else {
          ElMessage.error(message || '操作失败')
        }
      } else {
        ElMessage.error(error.message || '网络错误，请检查网络连接')
      }
    }
  } finally {
    processing.value = false
  }
}

// 处理参保信息（原有的处理完成功能）
const processChange = async (change) => {
  try {
    await ElMessageBox.confirm('确定要处理完成该参保信息吗？', '确认处理', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })

    await processInsuranceChange(change.id)
    ElMessage.success('处理完成')
    loadChanges()
    loadDetails()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('处理参保信息失败:', error)
      ElMessage.error('处理参保信息失败')
    }
  }
}

// 查看详情
const viewDetails = async (change) => {
  console.log('=== 查看详情 ===')
  console.log('change对象:', change)
  
  try {
    // 设置初始化标志位，防止开关自动触发
    isInitializing.value = true
    
    // 重新从API获取最新数据，添加时间戳防止缓存
    console.log('准备调用show API，ID:', change.id)
    const response = await request.get(`/insurance-changes/${change.id}?t=${Date.now()}`)
    console.log('show API响应:', response)
    
    if (response.success) {
      currentChange.value = response.data
      // 确保大额医疗保险状态是布尔值
      if (typeof response.data.large_medical_insurance_enabled === 'number') {
        currentChange.value.large_medical_insurance_enabled = Boolean(response.data.large_medical_insurance_enabled)
      }
      console.log('获取到的最新数据:', response.data)
      console.log('大额医疗保险状态:', response.data.large_medical_insurance_enabled)
      console.log('大额医疗保险状态类型:', typeof response.data.large_medical_insurance_enabled)
      console.log('转换后的状态:', currentChange.value.large_medical_insurance_enabled)
      console.log('转换后的状态类型:', typeof currentChange.value.large_medical_insurance_enabled)
      console.log('变化摘要:', response.data.change_summary)
      console.log('解析的变化详情:', response.data.parsed_change_details)
      console.log('=== 其他保险数据调试 ===')
      console.log('other_insurance_policies:', response.data.other_insurance_policies)
      console.log('other_insurance_policies 类型:', typeof response.data.other_insurance_policies)
      
      // 立即测试解析
      if (response.data.other_insurance_policies) {
        let testPolicies = response.data.other_insurance_policies
        if (typeof testPolicies === 'string') {
          try {
            testPolicies = JSON.parse(testPolicies)
            console.log('解析后的 other_insurance_policies:', testPolicies)
            console.log('解析后的数组长度:', testPolicies.length)
          } catch (e) {
            console.error('解析失败:', e)
          }
        }
      }
    } else {
      // 如果API调用失败，使用列表中的数据作为备选
      currentChange.value = change
      console.warn('API调用失败，使用列表数据')
      console.warn('列表数据中的大额医疗保险状态:', change.large_medical_insurance_enabled)
    }
    
    // 使用nextTick确保DOM更新完成后再重置标志位
    await nextTick(() => {
      isInitializing.value = false
    })
  } catch (error) {
    console.error('获取详情失败:', error)
    // 如果API调用失败，使用列表中的数据作为备选
    currentChange.value = change
    console.warn('API调用失败，使用列表数据')
    console.warn('列表数据中的大额医疗保险状态:', change.large_medical_insurance_enabled)
    
    // 确保在错误情况下也重置标志位
    await nextTick(() => {
      isInitializing.value = false
    })
  }
  
  showDetailDialogFlag.value = true
}

// 生成汇总表
const generateSummaryAction = async () => {
  if (!currentAccountSetId.value) {
    ElMessage.warning('请先选择账套')
    return
  }

  try {
    await generateSummary({
      account_set_id: currentAccountSetId.value,
      region_name: summaryFilterForm.value.region_name
    })
    ElMessage.success('汇总表生成成功')
    loadSummaries()
  } catch (error) {
    console.error('生成汇总表失败:', error)
    ElMessage.error('生成汇总表失败')
  }
}

// 导出汇总表
const exportSummaryAction = async () => {
  if (!currentAccountSetId.value) {
    ElMessage.warning('请先选择账套')
    return
  }

  try {
    const response = await exportSummary({
      account_set_id: currentAccountSetId.value,
      region_name: summaryFilterForm.value.region_name
    })
    
    // 创建下载链接
    const blob = new Blob([response], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `参保汇总表_${summaryFilterForm.value.region_name || '全部'}_${new Date().toISOString().split('T')[0]}.xlsx`
    link.click()
    window.URL.revokeObjectURL(url)
    
    ElMessage.success('导出成功')
  } catch (error) {
    console.error('导出汇总表失败:', error)
    ElMessage.error('导出汇总表失败')
  }
}

// 重置筛选
const resetFilter = () => {
  filterForm.value = {
    month: getCurrentMonth(), // 重置为当前月份
    status: '',
    region_name: ''
  }
  loadChanges()
}

const resetDetailFilter = () => {
  detailFilterForm.value = {
    month: getCurrentMonth(), // 重置为当前月份
    region_name: ''
  }
  loadDetails()
}

const resetSummaryFilter = () => {
  summaryFilterForm.value = {
    region_name: ''
  }
  loadSummaries()
}

// 获取状态文本
const getStatusText = (status) => {
  const statusMap = {
    'pending': '待处理',
    'submitted': '待确认',  // 已上传附件，待确认处理
    'completed': '已处理',
    'processing': '已处理', // 兼容旧状态
    'approved': '已处理',   // 兼容旧状态
    'finished': '已处理'    // 兼容旧状态
  }
  return statusMap[status] || '待处理' // 默认显示为待处理而不是未知
}

// 获取状态标签类型
const getStatusTagType = (status) => {
  const typeMap = {
    'pending': 'warning',
    'submitted': 'primary',  // 已上传附件，待确认处理
    'completed': 'success',
    'processing': 'success', // 兼容旧状态
    'approved': 'success',   // 兼容旧状态
    'finished': 'success'    // 兼容旧状态
  }
  return typeMap[status] || 'warning' // 默认显示为待处理样式
}

// 获取详情表格数据
// 获取社保详情
const getSocialSecurityDetails = () => {
  console.log('=== getSocialSecurityDetails 函数被调用 ===')
  if (!currentChange.value) {
    console.log('currentChange 为空')
    return []
  }
  
  console.log('currentChange.social_security_types:', currentChange.value.social_security_types)
  console.log('类型:', typeof currentChange.value.social_security_types)
  
  // 如果是InsuranceChangeDetail数据
  if (currentChange.value.insurance_type === 'social_security') {
    return [{
      name: currentChange.value.insurance_name,
      base_amount: currentChange.value.base_amount,
      employee_ratio: currentChange.value.employee_ratio,
      company_ratio: currentChange.value.company_ratio
    }]
  }
  
  // 使用快照数据
  let socialSecurityTypes = currentChange.value.social_security_types
  
  if (!socialSecurityTypes) {
    console.log('social_security_types 为空')
    return []
  }
  
  // 如果是字符串，尝试解析为JSON
  if (typeof socialSecurityTypes === 'string') {
    try {
      socialSecurityTypes = JSON.parse(socialSecurityTypes)
      console.log('JSON 解析成功')
    } catch (e) {
      console.error('解析social_security_types失败:', e)
      return []
    }
  }
  
  if (!Array.isArray(socialSecurityTypes)) {
    console.log('social_security_types 不是数组')
    return []
  }
  
  console.log('社保数据数组长度:', socialSecurityTypes.length)
  
  // 直接返回数据，不过滤
  const result = socialSecurityTypes.map(type => ({
    name: type.name,
    base_amount: type.base_amount,
    employee_ratio: type.employee_ratio,
    company_ratio: type.company_ratio
  }))
  
  console.log('返回结果:', result)
  return result
}

// 获取公积金详情
const getHousingFundDetails = () => {
  if (!currentChange.value) return null
  
  // 如果是InsuranceChangeDetail数据
  if (currentChange.value.insurance_type === 'housing_fund') {
    return {
      config_name: currentChange.value.insurance_name || '住房公积金',
      region_name: currentChange.value.region_name,
      base_amount: currentChange.value.base_amount,
      employee_ratio: currentChange.value.employee_ratio,
      company_ratio: currentChange.value.company_ratio
    }
  }
  
  // 使用快照数据而不是实时数据
  if (currentChange.value.housing_fund_params) {
    let housingFundParams = currentChange.value.housing_fund_params
    
    // 如果是字符串，尝试解析为JSON
    if (typeof housingFundParams === 'string') {
      try {
        housingFundParams = JSON.parse(housingFundParams)
      } catch (e) {
        console.error('解析housing_fund_params失败:', e)
        return null
      }
    }
    
    if (typeof housingFundParams === 'object' && housingFundParams !== null) {
      return {
        config_name: housingFundParams.config_name || '住房公积金',
        region_name: housingFundParams.region_name,
        base_amount: housingFundParams.base_amount,
        employee_ratio: housingFundParams.employee_ratio,
        company_ratio: housingFundParams.company_ratio
      }
    }
  }
  
  return null
}

// 获取医保详情
const getMedicalInsuranceDetails = () => {
  if (!currentChange.value) return []
  
  // 如果是InsuranceChangeDetail数据
  if (currentChange.value.insurance_type === 'medical_insurance') {
    return [{
      name: currentChange.value.insurance_name,
      base_amount: currentChange.value.base_amount,
      employee_ratio: currentChange.value.employee_ratio,
      company_ratio: currentChange.value.company_ratio
    }]
  }
  
  // 优先使用实时数据，如果没有则使用快照数据
  let medicalInsuranceTypes = null
  
  // 尝试从员工关联的医保地区获取实时数据
  if (currentChange.value.employee && currentChange.value.employee.medical_insurance_region) {
    const region = currentChange.value.employee.medical_insurance_region
    if (region.medical_insurance_types && Array.isArray(region.medical_insurance_types)) {
      medicalInsuranceTypes = region.medical_insurance_types
    }
  }
  
  // 如果没有实时数据，使用快照数据
  if (!medicalInsuranceTypes && currentChange.value.medical_insurance_types) {
    medicalInsuranceTypes = currentChange.value.medical_insurance_types
    
    // 如果是字符串，尝试解析为JSON
    if (typeof medicalInsuranceTypes === 'string') {
      try {
        medicalInsuranceTypes = JSON.parse(medicalInsuranceTypes)
      } catch (e) {
        console.error('解析medical_insurance_types失败:', e)
        return []
      }
    }
  }
  
  if (Array.isArray(medicalInsuranceTypes)) {
    // 获取已删除的项目列表
    const deletedItems = getDeletedItems('medical_insurance')
    
    // 过滤掉已删除的项目
    return medicalInsuranceTypes
      .filter(type => !deletedItems.includes(type.name))
      .map(type => ({
        name: type.name,
        base_amount: type.base_amount,
        employee_ratio: type.employee_ratio,
        company_ratio: type.company_ratio
      }))
  }
  
  return []
}

// 获取大额医疗保险详情
const getLargeMedicalInsuranceDetails = () => {
  if (!currentChange.value) return null
  
  // 如果有大额医疗保险配置ID，从配置中获取真实信息
  if (currentChange.value.large_medical_insurance_config_id) {
    // 从 currentChange 中获取大额医疗保险配置信息
    const config = currentChange.value.large_medical_insurance_config || {}
    
    return {
      region_name: config.region_name || '北京市',
      calculation_type: config.calculation_type || 'base',
      calculation_type_text: config.calculation_type === 'fixed' ? '固定金额' : '按基数',
      company_ratio: config.company_ratio || 0,
      employee_ratio: config.employee_ratio || 0,
      company_cost: config.company_amount || config.company_cost || 0, // 修复字段名
      employee_cost: config.employee_amount || config.employee_cost || 0, // 修复字段名
      payment_cycle: config.payment_cycle || 'monthly',
      payment_cycle_text: config.payment_cycle === 'year' ? '按年' : '按月',
      is_enabled: currentChange.value.large_medical_insurance_enabled || false
    }
  }
  
  // 如果 change_summary 包含"大额"，说明有大额医疗变更，尝试从 large_medical_insurance_config 获取数据
  if (currentChange.value.change_summary && currentChange.value.change_summary.includes('大额')) {
    const config = currentChange.value.large_medical_insurance_config || {}
    
    // 如果是字符串，尝试解析
    let parsedConfig = config
    if (typeof config === 'string') {
      try {
        parsedConfig = JSON.parse(config)
      } catch (e) {
        console.error('解析 large_medical_insurance_config 失败:', e)
        parsedConfig = {}
      }
    }
    
    // 只要有配置数据，就显示
    if (parsedConfig && Object.keys(parsedConfig).length > 0) {
      return {
        region_name: parsedConfig.region_name || '北京市',
        calculation_type: parsedConfig.calculation_type || 'base',
        calculation_type_text: parsedConfig.calculation_type === 'fixed' ? '固定金额' : '按基数',
        company_ratio: parsedConfig.company_ratio || 0,
        employee_ratio: parsedConfig.employee_ratio || 0,
        company_cost: parsedConfig.company_amount || parsedConfig.company_cost || 0,
        employee_cost: parsedConfig.employee_amount || parsedConfig.employee_cost || 0,
        payment_cycle: parsedConfig.payment_cycle || 'monthly',
        payment_cycle_text: parsedConfig.payment_cycle === 'year' ? '按年' : '按月',
        is_enabled: currentChange.value.large_medical_insurance_enabled || false
      }
    }
  }
  
  return null
}

// 获取其他保险详情
const getOtherInsuranceDetails = () => {
  console.log('=== getOtherInsuranceDetails 函数被调用 ===')
  
  if (!currentChange.value) {
    console.log('getOtherInsuranceDetails: currentChange为空')
    return []
  }
  
  console.log('currentChange存在，检查数据...')
  
  // 如果是InsuranceChangeDetail数据
  if (currentChange.value.insurance_type && !['social_security', 'medical_insurance', 'housing_fund'].includes(currentChange.value.insurance_type)) {
    console.log('是InsuranceChangeDetail数据，返回单个保险')
    return [{
      name: currentChange.value.insurance_name,
      type: currentChange.value.insurance_type,
      coverage: currentChange.value.coverage,
      employee_per_capita_cost: currentChange.value.employee_per_capita_cost
    }]
  }
  
  // 使用快照数据中的其他保险配置
  if (currentChange.value.other_insurance_policies) {
    console.log('找到other_insurance_policies字段')
    let policies = currentChange.value.other_insurance_policies
    
    // 如果是字符串，尝试解析为JSON
    if (typeof policies === 'string') {
      console.log('字段是字符串，尝试解析JSON')
      try {
        policies = JSON.parse(policies)
        console.log('JSON解析成功')
      } catch (e) {
        console.error('解析other_insurance_policies失败:', e)
        return []
      }
    }
    
    if (Array.isArray(policies)) {
      console.log('=== 其他保险详情数据 ===')
      console.log('数组长度:', policies.length)
      
      // 映射字段名，确保前端模板能正确显示
      const mappedPolicies = policies.map((policy, index) => {
        console.log(`保单${index + 1}:`, policy)
        return {
          ...policy,
          // 映射字段名
          name: policy.name || policy.policy_name || policy.type_name || '未知保险',
          type: policy.type || policy.type_name || '-',
          coverage: policy.coverage || policy.description || '-',
          policy_end_date: policy.policy_end_date || policy.end_date,
        }
      })
      
      console.log('返回映射后的保单数组，长度:', mappedPolicies.length)
      return mappedPolicies
    } else {
      console.log('解析后的数据不是数组:', typeof policies)
    }
  } else {
    console.log('没有找到other_insurance_policies字段')
  }
  
  console.log('没有找到其他保险数据')
  console.log('currentChange对象:', currentChange.value)
  console.log('other_insurance_policies字段:', currentChange.value.other_insurance_policies)
  return []
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

// 格式化日期时间 - 显示完整时间
const formatDateTime = (date) => {
  if (!date) return '-'
  try {
    // 移除 ISO 8601 格式中的 T 和 Z，并只保留日期时间部分
    const dateStr = String(date).replace('T', ' ').split('.')[0]
    return dateStr
  } catch (e) {
    console.error('日期时间格式化失败:', date, e)
    return '-'
  }
}

// 获取变更详情列表
const getChangeDetailsList = () => {
  if (!currentChange.value) {
    return []
  }
  
  // 优先使用parsed_change_details（旧格式）
  if (currentChange.value.parsed_change_details) {
    return currentChange.value.parsed_change_details
  }
  
  // 解析新的change_details格式
  if (currentChange.value.change_details) {
    try {
      const details = typeof currentChange.value.change_details === 'string' 
        ? JSON.parse(currentChange.value.change_details) 
        : currentChange.value.change_details
      
      // 如果包含changes数组，返回它
      if (details.changes && Array.isArray(details.changes)) {
        return details.changes
      }
      
      // 如果是旧格式，直接返回
      if (Array.isArray(details)) {
        return details
      }
    } catch (e) {
      console.error('解析change_details失败:', e)
    }
  }
  
  return []
}

// 判断是否为新增项（所有状态都显示）
const isNewItem = (category, itemName) => {
  // 移除状态限制，所有状态都显示新增标记
  if (!currentChange.value) {
    return false
  }
  
  const details = getChangeDetailsList()
  
  // 调试信息
  if (category === 'social_security') {
    console.log('=== 调试新增标记 ===')
    console.log('检查项目:', itemName)
    console.log('变更详情:', details)
    console.log('查找条件: category=' + category + ', action=added, item=' + itemName)
  }
  
  return details.some(detail => {
    // 检查类别和动作
    if (detail.category !== category || detail.action !== 'added') {
      return false
    }
    
    // 精确匹配或包含匹配
    const isMatch = detail.item === itemName || detail.item.includes(itemName)
    
    // 调试信息
    if (category === 'social_security') {
      console.log('检查详情:', detail)
      console.log('匹配结果:', isMatch)
    }
    
    return isMatch
  })
}

// 检查是否有删除项目
const hasDeletedItems = () => {
  if (!currentChange.value || currentChange.value.status !== 'pending') {
    return false
  }
  
  const details = getChangeDetailsList()
  return details.some(detail => detail.action === 'deleted')
}

// 获取所有删除项目
const getDeletedItems = (category = null) => {
  if (!currentChange.value || currentChange.value.status !== 'pending') {
    return []
  }
  
  const details = getChangeDetailsList()
  const deletedDetails = details.filter(detail => detail.action === 'deleted')
  
  // 如果指定了类别，返回该类别的删除项目名称列表
  if (category) {
    return deletedDetails
      .filter(detail => detail.category === category)
      .map(detail => detail.item)
  }
  
  // 否则返回完整的删除详情列表
  return deletedDetails
}

// 检查是否有新增项目
const hasAddedItems = () => {
  if (!currentChange.value || currentChange.value.status !== 'pending') {
    return false
  }
  
  const details = getChangeDetailsList()
  return details.some(detail => detail.action === 'added')
}

// 获取所有新增项目
const getAddedItems = () => {
  if (!currentChange.value || currentChange.value.status !== 'pending') {
    return []
  }
  
  const details = getChangeDetailsList()
  return details.filter(detail => detail.action === 'added')
}

// 检查是否有修改项目
const hasModifiedItems = () => {
  if (!currentChange.value || currentChange.value.status !== 'pending') {
    return false
  }
  
  const details = getChangeDetailsList()
  return details.some(detail => detail.action === 'modified')
}

// 获取所有修改项目
const getModifiedItems = () => {
  if (!currentChange.value || currentChange.value.status !== 'pending') {
    return []
  }
  
  const details = getChangeDetailsList()
  return details.filter(detail => detail.action === 'modified')
}

// 获取提示标题
const getAlertTitle = () => {
  if (hasDeletedItems() && hasAddedItems()) {
    return '⚠️ 保险项目已删除和新增'
  } else if (hasDeletedItems()) {
    return '⚠️ 保险项目已删除'
  } else if (hasAddedItems()) {
    return '✅ 保险项目已新增'
  } else if (hasModifiedItems()) {
    return '📝 保险信息已修改'
  } else {
    return '保险信息已变更'
  }
}

// 获取提示类型
const getAlertType = () => {
  if (hasDeletedItems()) {
    return 'error'  // 红色，因为有删除操作
  } else if (hasAddedItems()) {
    return 'success'  // 绿色，因为只有新增操作
  } else {
    return 'warning'  // 黄色，修改操作
  }
}

// 判断是否为删除项（所有状态都显示）
const isDeletedItem = (category, itemName) => {
  // 移除状态限制，所有状态都显示删除标记
  if (!currentChange.value) {
    return false
  }
  
  const details = getChangeDetailsList()
  return details.some(detail => {
    // 检查类别是否匹配
    if (detail.category !== category) {
      return false
    }
    
    // 检查动作是否为删除
    if (detail.action !== 'deleted') {
      return false
    }
    
    // 检查项目名称是否匹配
    return detail.item === itemName || detail.item.includes(itemName)
  })
}

// 判断是否为修改项（所有状态都显示）
const isModifiedItem = (category, itemName) => {
  // 移除状态限制，所有状态都显示修改标记
  if (!currentChange.value) {
    return false
  }
  
  const details = getChangeDetailsList()
  
  // 调试信息
  if (category === 'social_security') {
    console.log('=== 调试修改标记 ===')
    console.log('检查项目:', itemName)
    console.log('变更详情:', details)
  }
  
  return details.some(detail => {
    // 检查类别是否匹配
    if (detail.category !== category) {
      return false
    }
    
    // 检查动作是否为修改
    if (detail.action !== 'modified') {
      return false
    }
    
    // 对于社保和医保，检查是否包含具体的保险类型名称
    if (category === 'social_security' || category === 'medical_insurance') {
      // 如果item包含保险类型名称，则显示标记（但不包括"配置"这种通用词）
      const isMatch = detail.item.includes(itemName) && !detail.item.includes('配置')
      
      // 调试信息
      if (category === 'social_security') {
        console.log('检查修改详情:', detail)
        console.log('匹配结果:', isMatch)
      }
      
      return isMatch
    }
    
    // 对于大额医疗保险，检查是否包含具体的字段名称
    if (category === 'large_medical_insurance') {
      // 检查是否包含字段名称，如"大额医疗保险公司比例"包含"公司比例"
      return detail.item.includes(itemName)
    }
    
    // 对于其他类型，使用原来的逻辑
    return detail.item.startsWith(itemName)
  })
}

// 获取变更动作类型（用于标签颜色）
const getChangeActionType = (action) => {
  const typeMap = {
    'added': 'success',
    'removed': 'danger',
    'modified': 'warning'
  }
  return typeMap[action] || 'info'
}

// 判断某个类别是否有变更（用于区块标题标记）
const hasCategoryChange = (category) => {
  // 所有状态都显示变更标记（移除状态限制）
  if (!currentChange.value) {
    return false
  }
  
  const details = getChangeDetailsList()
  
  // 检查是否有该类别的任何变更
  return details.some(detail => detail.category === category)
}

// 获取变更动作文本
const getChangeActionText = (action) => {
  const textMap = {
    'added': '新增',
    'removed': '删除',
    'modified': '修改'
  }
  return textMap[action] || '变更'
}

// 获取类别文本
const getCategoryText = (category) => {
  const categoryMap = {
    'social_security': '社保',
    'medical_insurance': '医保',
    'housing_fund': '公积金',
    'other_insurance': '其他保险'
  }
  return categoryMap[category] || category
}

// 检查是否有员工基数信息
const hasEmployeeBaseInfo = () => {
  if (!currentChange.value) return false
  
  return currentChange.value.employee_social_security_base ||
         currentChange.value.employee_medical_insurance_base ||
         currentChange.value.employee_housing_fund_base ||
         currentChange.value.employee_large_medical_base
}

// 检查是否有参保地区信息
const hasRegionInfo = () => {
  if (!currentChange.value || !currentChange.value.employee) return false
  return currentChange.value.employee.social_security_region ||
         currentChange.value.employee.medical_insurance_region ||
         currentChange.value.employee.housing_fund_region ||
         currentChange.value.employee.large_medical_insurance_config_relation
}

// 计算社保员工缴纳金额
const calculateEmployeeAmount = (row) => {
  if (!currentChange.value || !row.employee_ratio) return '0.00'
  
  // 根据保险类型选择对应的基数
  let base = 0
  if (row.name && row.name.includes('养老')) {
    // 养老保险使用社保基数
    base = currentChange.value.employee_social_security_base || 0
  } else if (row.name && row.name.includes('医疗')) {
    // 医疗保险使用医保基数
    base = currentChange.value.employee_medical_insurance_base || 0
  } else {
    // 其他社保类型使用社保基数
    base = currentChange.value.employee_social_security_base || 0
  }
  
  const amount = base * row.employee_ratio
  return amount.toFixed(2)
}

// 计算社保公司缴纳金额
const calculateCompanyAmount = (row) => {
  if (!currentChange.value || !row.company_ratio) return '0.00'
  
  // 根据保险类型选择对应的基数
  let base = 0
  if (row.name && row.name.includes('养老')) {
    // 养老保险使用社保基数
    base = currentChange.value.employee_social_security_base || 0
  } else if (row.name && row.name.includes('医疗')) {
    // 医疗保险使用医保基数
    base = currentChange.value.employee_medical_insurance_base || 0
  } else {
    // 其他社保类型使用社保基数
    base = currentChange.value.employee_social_security_base || 0
  }
  
  const amount = base * row.company_ratio
  return amount.toFixed(2)
}

// 计算公积金员工缴纳金额
const calculateHousingFundEmployeeAmount = () => {
  if (!currentChange.value) return '0.00'
  
  const housingFund = getHousingFundDetails()
  if (!housingFund || !housingFund.employee_ratio) return '0.00'
  
  const base = currentChange.value.employee_housing_fund_base || 0
  const amount = base * parseFloat(housingFund.employee_ratio)
  return amount.toFixed(2)
}

// 计算公积金公司缴纳金额
const calculateHousingFundCompanyAmount = () => {
  if (!currentChange.value) return '0.00'
  
  const housingFund = getHousingFundDetails()
  if (!housingFund || !housingFund.company_ratio) return '0.00'
  
  const base = currentChange.value.employee_housing_fund_base || 0
  const amount = base * parseFloat(housingFund.company_ratio)
  return amount.toFixed(2)
}

// 计算医保员工缴纳金额
const calculateMedicalInsuranceEmployeeAmount = (row) => {
  if (!currentChange.value || !row.employee_ratio) return '0.00'
  
  const base = currentChange.value.employee_medical_insurance_base || 0
  const amount = base * row.employee_ratio
  return amount.toFixed(2)
}

// 计算医保公司缴纳金额
const calculateMedicalInsuranceCompanyAmount = (row) => {
  if (!currentChange.value || !row.company_ratio) return '0.00'
  
  const base = currentChange.value.employee_medical_insurance_base || 0
  const amount = base * row.company_ratio
  return amount.toFixed(2)
}

// 计算大额医疗保险公司缴纳金额
const calculateLargeMedicalCompanyAmount = (row) => {
  if (!currentChange.value || !currentChange.value.large_medical_insurance_enabled) return '0.00'
  
  if (row.calculation_type === 'fixed') {
    // 固定金额方式
    const amount = parseFloat(row.company_cost || row.company_amount || 0)
    return amount.toFixed(2)
  } else if (row.calculation_type === 'base') {
    // 基数计算方式
    // 特殊地区：使用单位基数计算公司缴纳金额
    // 普通地区：使用员工基数计算公司缴纳金额
    const companyBase = currentChange.value.employee_large_medical_company_base || currentChange.value.employee_large_medical_base || 0
    const amount = companyBase * (row.company_ratio || 0)
    return amount.toFixed(2)
  }
  
  return '0.00'
}

// 计算大额医疗保险员工缴纳金额
const calculateLargeMedicalEmployeeAmount = (row) => {
  if (!currentChange.value || !currentChange.value.large_medical_insurance_enabled) return '0.00'
  
  if (row.calculation_type === 'fixed') {
    // 固定金额方式
    const amount = parseFloat(row.employee_cost || row.employee_amount || 0)
    return amount.toFixed(2)
  } else if (row.calculation_type === 'base') {
    // 基数计算方式
    const base = currentChange.value.employee_large_medical_base || 0
    const amount = base * (row.employee_ratio || 0)
    return amount.toFixed(2)
  }
  
  return '0.00'
}

// 获取员工医保基数（用于参保明细显示）
const getEmployeeMedicalBase = (row) => {
  // 优先从明细数据中获取员工医保基数
  if (row.employee_medical_insurance_base) {
    return parseFloat(row.employee_medical_insurance_base).toFixed(2)
  }
  
  // 如果employee_medical_insurance_base为空，使用medical_base作为备选
  if (row.medical_base) {
    return parseFloat(row.medical_base).toFixed(2)
  }
  
  // 如果没有，尝试从当前变更记录获取（用于详情对话框）
  if (currentChange.value && currentChange.value.employee_medical_insurance_base) {
    return parseFloat(currentChange.value.employee_medical_insurance_base).toFixed(2)
  }
  
  return '0.00'
}

// 获取员工社保基数（用于参保明细显示）
const getEmployeeSocialSecurityBase = (row) => {
  // 优先从明细数据中获取员工社保基数
  if (row.employee_social_security_base) {
    return parseFloat(row.employee_social_security_base).toFixed(2)
  }
  
  // 如果employee_social_security_base为空，使用pension_base作为备选
  if (row.pension_base) {
    return parseFloat(row.pension_base).toFixed(2)
  }
  
  // 如果没有，尝试从当前变更记录获取（用于详情对话框）
  if (currentChange.value && currentChange.value.employee_social_security_base) {
    return parseFloat(currentChange.value.employee_social_security_base).toFixed(2)
  }
  
  return '0.00'
}

// 获取员工公积金基数（用于参保明细显示）
const getEmployeeHousingFundBase = (row) => {
  // 优先从明细数据中获取员工公积金基数
  if (row.employee_housing_fund_base) {
    return parseFloat(row.employee_housing_fund_base).toFixed(2)
  }
  
  // 如果employee_housing_fund_base为空，使用base_amount作为备选
  if (row.housing_fund_base) {
    return parseFloat(row.housing_fund_base).toFixed(2)
  }
  
  // 如果没有，尝试从当前变更记录获取（用于详情对话框）
  if (currentChange.value && currentChange.value.employee_housing_fund_base) {
    return parseFloat(currentChange.value.employee_housing_fund_base).toFixed(2)
  }
  
  return '0.00'
}

// 格式化参保日期
const formatEnrollmentDate = (date) => {
  if (!date) return ''
  const d = new Date(date)
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  return `${year}-${month}` // 改为 YYYY-MM 格式
}

// 格式化费款所属期
const formatPeriod = (date) => {
  if (!date) return ''
  const d = new Date(date)
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  return `${year}-${month}` // 改为 YYYY-MM 格式
}

// 格式化费款所属期字符串（后端返回的 YYYYMM 格式）
const formatPeriodString = (periodStr) => {
  if (!periodStr) return ''
  // 如果已经是 YYYY-MM 格式，直接返回
  if (periodStr.includes('-')) return periodStr
  // 转换 YYYYMM 或 YYYYM 格式为 YYYY-MM
  const year = periodStr.substring(0, 4)
  const month = periodStr.substring(4).padStart(2, '0') // 确保月份补零
  return `${year}-${month}`
}

// 社保明细汇总方法
const getSocialSecuritySummary = (param) => {
  const { columns, data } = param
  const sums = []
  
  columns.forEach((column, index) => {
    if (index === 0) {
      sums[index] = '合计'
      return
    }
    
    // 跳过非数值列
    if (['employee_name', 'id_number', 'project_name', 'enrollment_date', 'type', 'period', 'remarks'].includes(column.property)) {
      sums[index] = ''
      return
    }
    
    // 计算数值列的合计
    const values = data.map(item => {
      const value = item[column.property]
      return value ? parseFloat(value) : 0
    })
    
    if (values.length > 0) {
      const total = values.reduce((prev, curr) => prev + curr, 0)
      sums[index] = total.toFixed(2)
    } else {
      sums[index] = '0.00'
    }
  })
  return sums
}

// 公积金明细汇总方法
const getHousingFundSummary = (param) => {
  const { columns, data } = param
  const sums = []
  columns.forEach((column, index) => {
    if (index === 0) {
      sums[index] = '小计'
      return
    }
    if (index === 1 || index === 2 || index === 3 || index === 4 || index === 5 || index === 6) {
      sums[index] = ''
      return
    }
    const values = data.map(item => Number(item[column.property]))
    if (!values.every(value => Number.isNaN(value))) {
      sums[index] = values.reduce((prev, curr) => {
        const value = Number(curr)
        if (!Number.isNaN(value)) {
          return prev + curr
        } else {
          return prev
        }
      }, 0).toFixed(2)
    } else {
      sums[index] = ''
    }
  })
  return sums
}


// 导出单个保险组的Excel
const exportInsuranceGroup = async (insuranceType, policies) => {
  try {
    // 使用 xlsx 库导出Excel
    const XLSX = await import('xlsx')
    
    // 准备表头和数据行
    const headers = ['序号', '姓名', '身份证号码', '性别', '年龄', '联系电话', '项目', '增加日期', '到期', '类型', '参保状态', '在职状态', '金额', '替换人员', '批单号', '备注']
    
    const dataRows = policies.map(policy => [
      policy.serial_number,
      policy.employee_name,
      policy.id_number,
      policy.gender,
      policy.age,
      policy.contact_phone,
      policy.project_name,
      policy.addition_date,
      policy.expiration_date,
      policy.type,
      policy.insurance_status,
      policy.employment_status,
      policy.amount,
      policy.replaced_person_name,
      policy.endorsement_number,
      policy.remarks
    ])
    
    // 第一行：险种名称
    // 第二行：表头
    // 后续行：数据
    const aoa = [
      [insuranceType],  // 第一行显示险种名称
      headers,          // 第二行是表头
      ...dataRows       // 后续是数据
    ]
    
    // 使用 aoa_to_sheet 创建工作表
    const worksheet = XLSX.utils.aoa_to_sheet(aoa)
    
    // 设置列宽
    const colWidths = [
      { wch: 8 },   // 序号
      { wch: 10 },  // 姓名
      { wch: 20 },  // 身份证号码
      { wch: 6 },   // 性别
      { wch: 6 },   // 年龄
      { wch: 15 },  // 联系电话
      { wch: 15 },  // 项目
      { wch: 12 },  // 增加日期
      { wch: 12 },  // 到期
      { wch: 8 },   // 类型
      { wch: 10 },  // 参保状态
      { wch: 10 },  // 在职状态
      { wch: 12 },  // 金额
      { wch: 12 },  // 替换人员
      { wch: 15 },  // 批单号
      { wch: 12 }   // 备注
    ]
    worksheet['!cols'] = colWidths
    
    // 设置所有单元格居中对齐
    const range = XLSX.utils.decode_range(worksheet['!ref'])
    for (let R = range.s.r; R <= range.e.r; ++R) {
      for (let C = range.s.c; C <= range.e.c; ++C) {
        const cellAddress = XLSX.utils.encode_cell({ r: R, c: C })
        if (!worksheet[cellAddress]) continue
        
        // 设置单元格样式
        worksheet[cellAddress].s = {
          alignment: {
            horizontal: 'center',
            vertical: 'center'
          }
        }
      }
    }
    
    const workbook = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(workbook, worksheet, insuranceType)
    
    // 生成文件名
    const fileName = `${insuranceType}_${new Date().toLocaleDateString('zh-CN').replace(/\//g, '')}.xlsx`
    
    // 导出文件（使用bookType: 'xlsx'以支持样式）
    XLSX.writeFile(workbook, fileName, { bookType: 'xlsx', type: 'binary' })
    
    ElMessage.success(`导出成功：${fileName}`)
  } catch (error) {
    console.error('导出失败:', error)
    ElMessage.error('导出失败，请重试')
  }
}

// 处理其他保险费用变更
const handleOtherInsuranceCostChange = (row) => {
  // 标记为正在编辑
  row._editing = true
}

// 保存其他保险费用 - 已禁用
const saveOtherInsuranceCost = async (row) => {
  // 功能已禁用，不再执行任何操作
  console.log('保存其他保险费用功能已禁用')
  return
}

// 保存批单号
const saveEndorsementNumber = async (row) => {
  if (!currentChange.value) {
    return
  }

  try {
    console.log('保存批单号', {
      change_id: currentChange.value.id,
      insurance_id: row.id,
      endorsement_number: row.endorsement_number
    })

    const response = await updateEndorsementNumber(
      currentChange.value.id,
      {
        insurance_id: row.id,
        endorsement_number: row.endorsement_number
      }
    )

    if (response.success) {
      ElMessage.success('批单号保存成功')
      // 刷新数据
      loadChanges()
    } else {
      ElMessage.error(response.message || '批单号保存失败')
    }
  } catch (error) {
    console.error('保存批单号失败:', error)
    ElMessage.error('批单号保存失败')
  }
}

// 保存员工人均参保费用
const saveEmployeePerCapitaCost = async (row) => {
  if (!currentChange.value) {
    return
  }

  try {
    console.log('保存员工人均参保费用', {
      change_id: currentChange.value.id,
      insurance_id: row.id,
      employee_per_capita_cost: row.employee_per_capita_cost
    })

    const response = await request({
      url: `/insurance-changes/${currentChange.value.id}/update-per-capita-cost`,
      method: 'post',
      data: {
        insurance_id: row.id,
        employee_per_capita_cost: row.employee_per_capita_cost
      }
    })

    if (response.success) {
      ElMessage.success('费用保存成功')
    } else {
      ElMessage.error(response.message || '费用保存失败')
    }
  } catch (error) {
    console.error('保存费用失败:', error)
    ElMessage.error('费用保存失败')
  }
}

// 使用名额
// 防止递归调用的标志位
const isHandlingLargeMedicalSwitch = ref(false)
// 防止自动触发的标志位
const isInitializing = ref(false)
// 用户点击标志位
const userClickedSwitch = ref(false)

// 用户点击开关
const onSwitchClick = () => {
  console.log('用户点击了开关')
  console.log('当前开关状态:', currentChange.value?.large_medical_insurance_enabled)
  console.log('当前状态:', currentChange.value?.status)
  console.log('是否正在初始化:', isInitializing.value)
  
  // 只有在非初始化状态下才允许点击
  if (!isInitializing.value) {
    userClickedSwitch.value = true
    console.log('设置用户点击标志为true')
    // 不在这里重置标志位，让handleLargeMedicalSwitch的finally块来处理
  } else {
    console.log('正在初始化中，忽略点击事件')
  }
}

// 阻止自动触发开关事件
const beforeLargeMedicalSwitch = () => {
  console.log('beforeLargeMedicalSwitch 被调用')
  console.log('isInitializing.value:', isInitializing.value)
  console.log('isHandlingLargeMedicalSwitch.value:', isHandlingLargeMedicalSwitch.value)
  console.log('userClickedSwitch.value:', userClickedSwitch.value)
  
  // 如果正在初始化数据，阻止触发
  if (isInitializing.value) {
    console.log('阻止开关事件：正在初始化数据')
    return false
  }
  
  // 如果正在处理开关事件，阻止触发
  if (isHandlingLargeMedicalSwitch.value) {
    console.log('阻止开关事件：正在处理开关事件')
    return false
  }
  
  // 如果用户没有点击开关，阻止触发
  if (!userClickedSwitch.value) {
    console.log('阻止开关事件：用户没有点击开关')
    return false
  }
  
  console.log('允许开关事件：用户主动操作')
  return true
}

// 处理大额医疗保险开关
const handleLargeMedicalSwitch = async () => {
  console.log('handleLargeMedicalSwitch 被调用')
  console.log('currentChange.value:', currentChange.value)
  
  // 防止递归调用
  if (isHandlingLargeMedicalSwitch.value) {
    console.log('正在处理中，跳过')
    return
  }
  
  // 如果正在初始化数据，跳过
  if (isInitializing.value) {
    console.log('正在初始化数据，跳过')
    return
  }
  
  isHandlingLargeMedicalSwitch.value = true
  
  try {
    if (!currentChange.value || !currentChange.value.id) {
      console.log('currentChange 数据不完整:', currentChange.value)
      ElMessage.error('无法获取参保记录信息')
      return
    }

    // 如果是启用大额医疗，需要校验员工是否填写了大额参保日期
    if (currentChange.value.large_medical_insurance_enabled) {
      const employee = currentChange.value.employee
      if (!employee || !employee.large_medical_enrollment_date) {
        ElMessage.warning('该员工未填写大额医疗参保日期，请先在人员档案中填写后再启用')
        // 恢复开关状态
        currentChange.value.large_medical_insurance_enabled = false
        return
      }
    }

    const action = currentChange.value.large_medical_insurance_enabled ? '启用' : '停用'
    // 直接执行操作，不显示确认对话框

    // 调用后端API更新开关状态
    console.log('准备调用API，参数:', {
      id: currentChange.value.id,
      is_enabled: currentChange.value.large_medical_insurance_enabled
    })
    
    const response = await request.put(
      `/insurance-changes/${currentChange.value.id}/toggle-large-medical`,
      {
        is_enabled: currentChange.value.large_medical_insurance_enabled
      }
    )
    
    console.log('API响应:', response)
    console.log('API响应中的large_medical_insurance_enabled:', response.data?.change?.large_medical_insurance_enabled)

    if (response.success) {
      ElMessage.success(`${action}成功`)
      // 直接更新当前变更记录的状态
      if (response.data && response.data.change) {
        console.log('更新前状态:', currentChange.value.large_medical_insurance_enabled)
        // 确保将数字转换为布尔值
        const newStatus = Boolean(response.data.change.large_medical_insurance_enabled)
        currentChange.value.large_medical_insurance_enabled = newStatus
        console.log('更新后状态:', currentChange.value.large_medical_insurance_enabled)
        
        // 同时更新列表中的对应记录
        const changeIndex = changes.value.findIndex(c => c.id === currentChange.value.id)
        if (changeIndex !== -1) {
          changes.value[changeIndex].large_medical_insurance_enabled = newStatus
          console.log('列表记录也已更新')
        }
      }
      // 强制刷新列表数据，确保数据同步
      console.log('准备刷新列表数据...')
      await loadChanges()
      console.log('列表数据刷新完成')
    } else {
      throw new Error(response.message || `${action}失败`)
    }
  } catch (error) {
    console.error('切换大额医疗保险失败:', error)
    console.error('错误详情:', {
      message: error.message,
      response: error.response,
      status: error.response?.status,
      data: error.response?.data
    })
    
    ElMessage.error(error.response?.data?.message || error.message || '操作失败')
    // 恢复开关状态
    currentChange.value.large_medical_insurance_enabled = !currentChange.value.large_medical_insurance_enabled
  } finally {
    // 重置标志位
    isHandlingLargeMedicalSwitch.value = false
  }
}

const useQuota = async (row) => {
  try {
    await ElMessageBox.confirm(
      `确定要使用"${row.name}"的名额吗？\n\n使用后将：\n1. 自动填充员工人均参保费用（¥${row.employee_per_capita_cost || 0}）\n2. 保单剩余名额减1\n3. 此记录不可再修改`,
      '使用名额确认',
      {
        confirmButtonText: '确定使用',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    if (!currentChange.value || !currentChange.value.id) {
      ElMessage.error('无法获取参保记录信息')
      return
    }

    if (!row.id) {
      ElMessage.error('无法获取保险ID')
      return
    }

    console.log('=== 使用名额 ===')
    console.log('参保记录ID:', currentChange.value.id)
    console.log('保险ID:', row.id)

    // 调用后端API使用名额
    const response = await request.post(
      `/insurance-changes/${currentChange.value.id}/use-quota`,
      {
        insurance_id: row.id
      }
    )

    console.log('使用名额响应:', response)

    if (response.success) {
      ElMessage.success('名额使用成功')
      
      // 更新当前行的状态
      row.quota_used = true
      row.available_quota = (row.available_quota || 0) - 1
      
      // 刷新数据
      loadChanges()
    } else {
      throw new Error(response.message || '使用名额失败')
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('使用名额失败:', error)
      ElMessage.error(error.response?.data?.message || error.message || '使用名额失败')
    }
  }
}

// 获取被替换的人员姓名
const getReplacedPersonName = (row) => {
  if (!currentChange.value || !currentChange.value.used_quotas) {
    return null
  }
  
  let usedQuotas = currentChange.value.used_quotas
  
  // 如果used_quotas是字符串，手动解析为数组
  if (typeof usedQuotas === 'string') {
    try {
      usedQuotas = JSON.parse(usedQuotas)
    } catch (e) {
      console.error('解析used_quotas失败:', e)
      return null
    }
  }
  
  if (!Array.isArray(usedQuotas)) {
    return null
  }
  
  // 查找对应保单的被替换人员姓名
  for (const usedQuota of usedQuotas) {
    if (typeof usedQuota === 'object' && usedQuota.policy_id == row.id && usedQuota.removed_person_name) {
      return usedQuota.removed_person_name
    }
  }
  
  return null
}

// 监听标签页切换
const handleTabChange = (tab) => {
  if (tab === 'details') {
    loadDetails()
  } else if (tab === 'summaries') {
    loadSummaries()
  }
}

// 监听选项卡切换
watch(detailActiveTab, (newTab) => {
  if (newTab === 'compensation') {
    loadCompensationDetails()
  } else if (newTab === 'housingFundCompensation') {
    loadHousingFundCompensationDetails()
  }
})

// 监听月份筛选变化，自动触发查询
watch(() => detailFilterForm.value.month, (newMonth, oldMonth) => {
  // 确保月份发生了变化，且不是初始化时的变化
  if (newMonth !== oldMonth && oldMonth !== undefined) {
    // 根据当前激活的标签页触发相应的查询
    if (activeTab.value === 'details') {
      if (detailActiveTab.value === 'social') {
        loadDetails()
      } else if (detailActiveTab.value === 'compensation') {
        loadCompensationDetails()
      } else if (detailActiveTab.value === 'housingFundCompensation') {
        loadHousingFundCompensationDetails()
      }
    } else if (activeTab.value === 'summaries') {
      loadSummaries()
    }
  }
})

onMounted(async () => {
  // 设置初始化标志位，防止页面加载时触发开关事件
  isInitializing.value = true
  
  try {
    // 先加载社保地区列表
    await loadSocialSecurityRegions()
    // 再加载参保人员列表
    await loadChanges()
  } finally {
    // 使用nextTick确保所有数据加载完成后再重置标志位
    await nextTick(() => {
      isInitializing.value = false
    })
  }
})

// ==================== 导出功能 ====================

// 加载报表模板列表
const loadExportTemplates = async () => {
  try {
    const response = await request.get('/report-templates', {
      params: {
        account_set_id: currentAccountSetId.value,
        region_type: 'social_security'
      }
    })
    
    if (response.success) {
      exportTemplates.value = response.data
    }
  } catch (error) {
    console.error('加载模板列表失败:', error)
  }
}

// 模板选择变化
const handleTemplateChange = () => {
  console.log('选中的模板:', selectedTemplate.value)
}

// 导出数据
const exportData = async () => {
  if (!selectedTemplateId.value) {
    ElMessage.warning('请选择报表模板')
    return
  }
  
  try {
    exportLoading.value = true
    
    const template = selectedTemplate.value
    if (!template) {
      ElMessage.error('模板不存在')
      return
    }
    
    // 获取要导出的数据
    let dataToExport = []
    if (exportRange.value === 'current') {
      dataToExport = changes.value
    } else {
      // 获取全部数据
      const response = await request.get('/insurance-changes', {
        params: {
          account_set_id: currentAccountSetId.value,
          month: filterForm.value.month,
          status: filterForm.value.status,
          region_name: filterForm.value.region_name,
          all: true
        }
      })
      dataToExport = response.data || []
    }
    
    if (dataToExport.length === 0) {
      ElMessage.warning('没有数据可导出')
      return
    }
    
    // 根据模板配置映射数据
    const exportRows = dataToExport.map(row => {
      const mappedRow = {}
      template.fields.forEach(field => {
        // 根据字段 key 获取对应的值
        let value = ''
        switch (field.key) {
          case 'name':
            value = row.employee?.name || row.employee_name || ''
            break
          case 'id_number':
            value = row.employee?.id_number || row.employee_id_number || ''
            break
          case 'phone':
            value = row.employee?.phone || ''
            break
          case 'department':
            value = row.employee?.department || ''
            break
          case 'position':
            value = row.employee?.position || ''
            break
          case 'entry_date':
            value = row.employee?.entry_date || ''
            break
          case 'social_security_base':
            value = row.employee_social_security_base || ''
            break
          // 可以继续添加更多字段映射
          default:
            value = row[field.key] || ''
        }
        mappedRow[field.label] = value
      })
      return mappedRow
    })
    
    // 使用简单的方式导出（调用后端生成 Excel）
    const response = await request.post('/insurance-changes/export', {
      template_id: selectedTemplateId.value,
      data: exportRows,
      filename: template.name
    }, {
      responseType: 'blob'
    })
    
    // 下载文件
    const url = window.URL.createObjectURL(new Blob([response]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `${template.name}.xlsx`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
    
    ElMessage.success('导出成功')
    showExportDialog.value = false
  } catch (error) {
    console.error('导出失败:', error)
    ElMessage.error('导出失败')
  } finally {
    exportLoading.value = false
  }
}

// 监听对话框打开，加载模板列表
watch(showExportDialog, (newVal) => {
  if (newVal) {
    loadExportTemplates()
  }
})
</script>

<style scoped>
.insurance-change-container {
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  color: #303133;
}

.header-actions {
  display: flex;
  gap: 10px;
}

/* 表格内标题样式 */
.table-title {
  text-align: center;
  padding: 15px 0;
  background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
  border-radius: 4px;
  color: #2c3e50;
  font-size: 18px;
  font-weight: 600;
  letter-spacing: 1px;
  margin: 0;
  width: 100%;
  display: block;
}

.tabs-container {
  margin-top: 20px;
}

.tab-content {
  margin-top: 20px;
}

.filter-card {
  margin-bottom: 20px;
}

.table-card {
  margin-bottom: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header-actions {
  display: flex;
  align-items: center;
  gap: 15px;
}

.total-count {
  color: #909399;
  font-size: 14px;
}

.region-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.region-item {
  display: flex;
  align-items: center;
}

.detail-content {
  padding: 20px 0;
}

.insurance-region-info {
  margin-bottom: 20px;
}

.insurance-region-info h4 {
  margin: 0 0 12px 0;
  color: #303133;
  font-size: 16px;
  font-weight: 600;
}

.insurance-details {
  margin-bottom: 20px;
}

.insurance-details h4 {
  margin: 0 0 12px 0;
  color: #303133;
  font-size: 16px;
  font-weight: 600;
}

.base-amount {
  color: #F56C6C;
  font-weight: 600;
}

.detail-table {
  margin-top: 20px;
}

.detail-table h4 {
  margin-bottom: 15px;
  color: #303133;
}

.dialog-footer {
  text-align: right;
}

.el-upload__tip {
  color: #909399;
  font-size: 12px;
  margin-top: 4px;
}

/* 保险配置详情样式 */
.insurance-details {
  margin: 25px 0;
  padding: 20px;
  background-color: #f8f9fa;
  border-radius: 8px;
  border: 1px solid #e9ecef;
  transition: all 0.3s ease;
}

/* 有变更的区块样式 */
.insurance-details.has-change {
  border: 2px solid #F56C6C;
  background-color: #fef0f0;
  box-shadow: 0 0 8px rgba(245, 108, 108, 0.3);
}

.insurance-details.has-change h4 {
  color: #F56C6C;
}

.insurance-details h4 {
  margin: 0 0 15px 0;
  font-size: 16px;
  color: #495057;
  font-weight: 500;
  display: flex;
  align-items: center;
}

/* 明细分类标签页样式 */
.detail-tabs {
  margin-top: 20px;
}

.detail-tabs .el-tabs__header {
  margin-bottom: 20px;
}

.detail-tab-content {
  padding: 0;
}

/* 明细表格样式 */
.detail-table {
  font-size: 12px;
}

.detail-table .el-table__header {
  background-color: #f5f7fa;
}

.detail-table .el-table__header th {
  background-color: #f5f7fa !important;
  color: #606266;
  font-weight: 600;
  text-align: center;
}

.detail-table .el-table__body td {
  padding: 8px 0;
}

/* 金额样式 */
.base-amount {
  color: #409eff;
  font-weight: 500;
}

.ratio-amount {
  color: #409eff;
  font-weight: 500;
}

.amount-value {
  color: #e6a23c;
  font-weight: 600;
  font-size: 13px;
}

.total-amount {
  background-color: #f0f9ff;
  color: #1d4ed8;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 4px;
}

.grand-total {
  background-color: #dbeafe;
  color: #1e40af;
  font-weight: 700;
  padding: 4px 8px;
  border-radius: 4px;
}

/* 表格汇总行样式 */
.detail-table .el-table__footer-wrapper {
  background-color: #f8fafc;
}

.detail-table .el-table__footer-wrapper td {
  background-color: #f8fafc !important;
  font-weight: 600;
  color: #374151;
}

/* 标签页头部样式 */
.detail-tabs .el-tabs__item {
  font-weight: 500;
  padding: 0 20px;
}

.detail-tabs .el-tabs__item.is-active {
  color: #409eff;
  font-weight: 600;
}

/* 卡片头部样式 */
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.total-count {
  color: #909399;
  font-size: 14px;
  font-weight: normal;
}

/* 响应式调整 */
@media (max-width: 1200px) {
  .detail-table {
    font-size: 11px;
  }
  
  .detail-table .el-table__body td {
    padding: 6px 0;
  }
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 6px;
  line-height: 1.4;
}

/* 保险分组样式 */
.insurance-group-card {
  border-left: 4px solid #409eff;
}

.insurance-group-card .card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0;
}

.insurance-group-card .group-header {
  display: flex;
  align-items: center;
  gap: 20px;
}

.insurance-type-title {
  font-size: 18px;
  font-weight: 600;
  color: #409eff;
}

.group-count {
  font-size: 14px;
  color: #909399;
  padding: 4px 12px;
  background: #f5f7fa;
  border-radius: 12px;
}

.group-total {
  font-size: 16px;
  font-weight: 600;
  color: #f56c6c;
}

.group-actions {
  display: flex;
  gap: 10px;
}

/* 员工分组样式 */
.employee-groups {
  margin-top: 10px;
}

.employee-group {
  margin-bottom: 20px;
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  overflow: hidden;
}

.employee-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 15px 20px;
  display: flex;
  align-items: center;
  gap: 20px;
  cursor: pointer;
  user-select: none;
  transition: background-color 0.2s ease;
}

.employee-header:hover {
  background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
}

.collapse-icon {
  margin-right: 10px;
  transition: transform 0.3s ease;
  font-size: 16px;
}

.employee-header h4 {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
}

.project-name {
  font-size: 14px;
  opacity: 0.9;
}

.amount-summary {
  margin-left: auto;
  display: flex;
  gap: 12px;
}

.amount-item {
  font-size: 14px;
  font-weight: 600;
  background: rgba(255, 255, 255, 0.2);
  padding: 4px 12px;
  border-radius: 16px;
  white-space: nowrap;
}

.employee-amount {
  background: rgba(76, 175, 80, 0.3);
}

.company-amount {
  background: rgba(33, 150, 243, 0.3);
}

.total-amount {
  background: rgba(255, 255, 255, 0.2);
}

.employee-insurance-table {
  margin: 0;
}

.no-data {
  text-align: center;
  padding: 40px 0;
}
</style>