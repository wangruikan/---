<template>
  <div class="insurance-change-container" :class="{ 'preview-only-host': props.previewOnly }">
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
                  <el-option label="成功" value="completed" />
                  <el-option label="终结" value="terminated" />
                  <el-option label="失败" value="failed" />
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
              <el-form-item label="项目">
                <el-select v-model="selectedProjectName" placeholder="请选择项目" clearable style="width: 200px">
                  <el-option label="全部" value="" />
                  <el-option
                    v-for="project in projectOptions"
                    :key="project"
                    :label="project"
                    :value="project"
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
                  <span class="total-count">共 {{ filteredChanges.length }} / {{ changes.length }} 条记录</span>
                  <span class="summary-count pending-count">待处理 {{ changeStats.pending }} 人</span>
                  <span class="summary-count success-count">成功 {{ changeStats.success }} 人</span>
                  <span class="summary-count terminated-count">终结 {{ changeStats.terminated }} 人</span>
                  <span class="summary-count failed-count">失败 {{ changeStats.failed }} 人</span>
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

            <el-tabs v-model="changeStatusTab" type="card" class="detail-tabs" style="margin-bottom: 12px;">
              <el-tab-pane label="增加" name="increase" />
              <el-tab-pane label="减少" name="decrease" />
            </el-tabs>

            <el-table 
              ref="changeTableRef"
              :data="filteredChanges" 
              :max-height="changeTableMaxHeight"
              v-loading="loading" 
              stripe
              @selection-change="handleTaskSelectionChange"
            >
              <el-table-column type="selection" width="55" :selectable="isTaskSelectable" />
              <el-table-column label="员工姓名" width="120">
                <template #default="{ row }">
                  {{ row.employee?.name || row.employee_name || '-' }}
                </template>
              </el-table-column>
              <el-table-column label="增减类型" width="100">
                <template #default="{ row }">
                  <el-tag v-if="row.change_type === 'decrease'" type="danger">
                    减少
                  </el-tag>
                  <el-tag v-else type="success">
                    增加
                  </el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="project.name" label="项目名称" width="150" />
              <el-table-column label="地区名称" width="140" show-overflow-tooltip>
                <template #default="{ row }">
                  {{ getPrimaryRegionName(row) || '-' }}
                </template>
              </el-table-column>
              <el-table-column
                v-if="changeStatusTab === 'decrease'"
                label="离职原因"
                min-width="220"
                show-overflow-tooltip
              >
                <template #default="{ row }">
                  {{ getChangeLeaveReason(row) || '-' }}
                </template>
              </el-table-column>
              <el-table-column
                v-for="category in insuranceCategoryColumns"
                :key="category.key"
                :label="category.label"
                width="110"
                align="center"
              >
                <template #default="{ row }">
                  <template v-if="getCategoryDisplayStatus(row, category.key)">
                    <span
                      v-if="isSuccessStatus(getCategoryDisplayStatus(row, category.key))"
                      class="category-status-icon success"
                    >
                      √
                    </span>
                    <span
                      v-else-if="isFailedStatus(getCategoryDisplayStatus(row, category.key))"
                      class="category-status-icon failed"
                    >
                      ×
                    </span>
                    <span
                      v-else-if="isTerminatedStatus(getCategoryDisplayStatus(row, category.key))"
                      class="category-status-icon terminated"
                    >
                      ▲
                    </span>
                    <el-tag
                      v-else
                      :type="getStatusTagType(getCategoryDisplayStatus(row, category.key))"
                      size="small"
                    >
                      {{ getStatusText(getCategoryDisplayStatus(row, category.key)) }}
                    </el-tag>
                  </template>
                  <span v-else>-</span>
                </template>
              </el-table-column>
              <el-table-column label="商业保险细分" min-width="240">
                <template #default="{ row }">
                  <div v-if="getOtherInsuranceDetailRows(row).length > 0" class="other-insurance-detail-list">
                    <div
                      v-for="detail in getOtherInsuranceDetailRows(row)"
                      :key="detail.key"
                      class="other-insurance-detail-row"
                    >
                      <span class="other-insurance-detail-name">{{ detail.label }}</span>
                      <span
                        v-if="isSuccessStatus(detail.status)"
                        class="category-status-icon success"
                      >
                        √
                      </span>
                      <span
                        v-else-if="isFailedStatus(detail.status)"
                        class="category-status-icon failed"
                      >
                        ×
                      </span>
                      <span
                        v-else-if="isTerminatedStatus(detail.status)"
                        class="category-status-icon terminated"
                      >
                        ▲
                      </span>
                      <el-tag
                        v-else-if="detail.status"
                        :type="getStatusTagType(detail.status)"
                        size="small"
                      >
                        {{ getStatusText(detail.status) }}
                      </el-tag>
                      <span v-else>-</span>
                    </div>
                  </div>
                  <span v-else>-</span>
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
                  <!-- 按需求：仅隐藏“商业保险确认处理”按钮 -->
                  <el-button 
                    v-if="hasProcessableItems(row)"
                    type="primary" 
                    size="small" 
                    :loading="processing && processingChangeId === row.id"
                    :disabled="processing && processingChangeId === row.id"
                    @click="showProcessDialog(row)"
                  >
                    处理业务
                  </el-button>
                  <!-- 按需求临时隐藏：商业保险确认处理 -->
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
              <el-form-item v-if="detailTabNeedsRegionFilter()" label="地区">
                <el-select v-model="detailFilterForm.region_name" placeholder="请选择地区" style="width: 200px">
                  <el-option 
                    v-for="region in regions" 
                    :key="region" 
                    :label="region" 
                    :value="region" 
                  />
                </el-select>
              </el-form-item>
              <el-form-item>
                <el-button type="primary" @click="loadActiveDetailData">查询</el-button>
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
                  
                  <div class="table-title detail-table-title">{{ getSocialSecurityTitle() }}</div>

                  <el-table 
                    :data="socialSecurityDetails" 
                    size="small" 
                    border 
                    class="detail-table"
                    :span-method="socialSecuritySpanMethod"
                    :row-class-name="detailSummaryRowClassName"
                  >
                    <el-table-column prop="serial_number" label="序号" width="60" align="center">
                      <template #default="{ row, $index }">
                        <template v-if="row.isTitleRow">
                          <div class="table-title">{{ row.title }}</div>
                        </template>
                        <template v-else-if="row.isSummaryRow || row.isTotalRow">
                          {{ '' }}
                        </template>
                        <template v-else>
                          {{ row.serial_number || $index }}
                        </template>
                      </template>
                    </el-table-column>
                    <el-table-column prop="employee_name" label="姓名" width="100">
                      <template #default="{ row }">
                        <template v-if="row.isSummaryRow || row.isTotalRow">
                          {{ row.employee_name }}
                        </template>
                        <template v-else>
                          {{ row.employee_name === 'NaN' || !row.employee_name ? '-' : row.employee_name }}
                        </template>
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
                          header-align="center"
                        >
                          <template #header>
                            <div class="insurance-column-header">
                              <span>{{ column.name }}</span>
                              <span
                                v-if="getInsuranceColumnRatioLabel(column, 'company')"
                                class="insurance-column-ratio"
                              >
                                {{ getInsuranceColumnRatioLabel(column, 'company') }}
                              </span>
                            </div>
                          </template>
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
                          header-align="center"
                        >
                          <template #header>
                            <div class="insurance-column-header">
                              <span>{{ column.name }}</span>
                              <span
                                v-if="getInsuranceColumnRatioLabel(column, 'employee')"
                                class="insurance-column-ratio"
                              >
                                {{ getInsuranceColumnRatioLabel(column, 'employee') }}
                              </span>
                            </div>
                          </template>
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
                    <el-table-column label="操作" width="90" fixed="right" align="center">
                      <template #default="{ row }">
                        <el-button
                          v-if="row.can_edit_social_detail && !row.isSummaryRow && !row.isTotalRow"
                          type="primary"
                          link
                          size="small"
                          @click="openSocialDetailEdit(row)"
                        >
                          <el-icon><Edit /></el-icon>
                          修改
                        </el-button>
                      </template>
                    </el-table-column>
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
                  
                  <div class="table-title detail-table-title">{{ getHousingFundTitle() }}</div>

                  <el-table 
                    :data="housingFundDetails" 
                    size="small" 
                    border 
                    class="detail-table"
                    :span-method="housingFundSpanMethod"
                    :row-class-name="detailSummaryRowClassName"
                  >
                    <el-table-column prop="serial_number" label="序号" width="60" align="center">
                      <template #default="{ row, $index }">
                        <template v-if="row.isTitleRow">
                          <div class="table-title">{{ row.title }}</div>
                        </template>
                        <template v-else-if="row.isSummaryRow || row.isTotalRow">
                          {{ '' }}
                        </template>
                        <template v-else>
                          {{ row.serial_number || $index }}
                        </template>
                      </template>
                    </el-table-column>
                    <el-table-column prop="employee_name" label="姓名" width="100">
                      <template #default="{ row }">
                        <template v-if="row.isSummaryRow || row.isTotalRow">
                          {{ row.employee_name }}
                        </template>
                        <template v-else>
                          {{ row.employee_name === 'NaN' || !row.employee_name ? '-' : row.employee_name }}
                        </template>
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
                    <el-table-column prop="company_portion" label="单位部分" width="120" align="right" header-align="center">
                      <template #header>
                        <div class="insurance-column-header">
                          <span>单位部分</span>
                          <span
                            v-if="getHousingFundRatioLabel('company')"
                            class="insurance-column-ratio"
                          >
                            {{ getHousingFundRatioLabel('company') }}
                          </span>
                        </div>
                      </template>
                    </el-table-column>
                    <el-table-column prop="employee_portion" label="个人部分" width="120" align="right" header-align="center">
                      <template #header>
                        <div class="insurance-column-header">
                          <span>个人部分</span>
                          <span
                            v-if="getHousingFundRatioLabel('employee')"
                            class="insurance-column-ratio"
                          >
                            {{ getHousingFundRatioLabel('employee') }}
                          </span>
                        </div>
                      </template>
                    </el-table-column>
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

    <!-- 业务处理对话框 -->
    <el-dialog
      v-model="showUploadDialogFlag"
      title="业务处理"
      width="800px"
    >
      <el-form :model="processForm" ref="uploadFormRef" label-width="100px">
        <el-form-item label="员工姓名">
          <el-input :value="currentChange.employee ? currentChange.employee.name : ''" disabled />
        </el-form-item>
        <el-form-item label="项目名称">
          <el-input :value="currentChange.project ? currentChange.project.name : ''" disabled />
        </el-form-item>
        <el-form-item label="处理业务">
          <el-select
            v-model="processForm.item_ids"
            multiple
            collapse-tags
            collapse-tags-tooltip
            placeholder="请选择处理业务"
            style="width: 100%"
            @change="handleProcessItemChange"
          >
            <el-option
              v-for="item in processableItemOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item v-if="isOtherInsuranceProcessSelected" :label="processOtherInsuranceAmountLabel">
          <el-table
            :data="processOtherInsuranceAmountRows"
            size="small"
            border
            style="width: 100%"
          >
            <el-table-column prop="name" label="保险名称" min-width="180" />
            <el-table-column prop="type" label="保险类型" min-width="120" />
            <el-table-column :label="processOtherInsuranceAmountLabel" width="180">
              <template #default="{ row }">
                <el-input-number
                  v-model="row.amount"
                  :precision="2"
                  :step="1"
                  :min="0"
                  size="small"
                  style="width: 140px;"
                />
              </template>
            </el-table-column>
          </el-table>
        </el-form-item>
        <el-form-item v-if="!isOtherInsuranceProcessSelected" label="处理结果">
          <el-radio-group v-model="processForm.result">
            <el-radio-button label="success">成功</el-radio-button>
            <el-radio-button label="failed">失败</el-radio-button>
            <el-radio-button label="terminated">终结</el-radio-button>
          </el-radio-group>
        </el-form-item>

        <el-alert
          v-if="isOtherInsuranceProcessSelected"
          title="商业保险默认按成功处理，确认后会先保存参保费用并完成处理。"
          type="success"
          :closable="false"
          style="margin-bottom: 16px;"
        />
        <el-alert
          v-else-if="processForm.result === 'success'"
          title="成功后会立即将当前所选的待处理业务一次性完成，本次不需要上传附件。"
          type="success"
          :closable="false"
          style="margin-bottom: 16px;"
        />
        <el-alert
          v-else-if="processForm.result === 'failed'"
          title="失败时必须上传附件，当前所选业务会保留失败结果，并立即生成下月续办任务。"
          type="warning"
          :closable="false"
          style="margin-bottom: 16px;"
        />
        <el-alert
          v-else
          title="终结时必须上传附件，当前所选业务会直接终结，不会生成下月续办任务。"
          type="warning"
          :closable="false"
          style="margin-bottom: 16px;"
        />

        <el-form-item label="已上传附件" v-if="currentProcessAttachments.length > 0">
          <el-table :data="currentProcessAttachments" size="small" border style="width: 100%">
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

        <el-form-item label="上传附件" v-if="!isOtherInsuranceProcessSelected && ['failed', 'terminated'].includes(processForm.result)">
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
            @click="submitProcess"
            :loading="processing && processingChangeId === currentChange?.id"
          >
            确认提交
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
          <el-descriptions-item
            v-if="currentChange.change_type === 'decrease' && getChangeLeaveReason(currentChange)"
            label="离职原因"
          >
            {{ getChangeLeaveReason(currentChange) }}
          </el-descriptions-item>
        </el-descriptions>

        <div v-if="hasRegionInfo()" class="insurance-region-info">
          <h4>参保地区信息</h4>
          <el-descriptions :column="2" size="small" border>
            <!-- 社保地区 -->
            <el-descriptions-item label="社保地区" v-if="shouldShowDetailCategory('social_security') && currentChange.employee && currentChange.employee.social_security_region">
              <div style="display: flex; flex-direction: column; gap: 4px;">
                <span>{{ currentChange.employee.social_security_region.name }}</span>
                <span v-if="currentChange.employee.social_security_region.code" style="color: #909399; font-size: 12px;">
                  编号：{{ currentChange.employee.social_security_region.code }}
                </span>
              </div>
            </el-descriptions-item>
            <el-descriptions-item label="社保地区" v-else-if="shouldShowDetailCategory('social_security')">
              <el-tag type="info" size="small">未设置</el-tag>
            </el-descriptions-item>

            <!-- 医保地区 -->
            <el-descriptions-item label="医保地区" v-if="shouldShowDetailCategory('medical_insurance') && currentChange.employee && currentChange.employee.medical_insurance_region">
              <div style="display: flex; flex-direction: column; gap: 4px;">
                <span>{{ currentChange.employee.medical_insurance_region.name }}</span>
                <span v-if="currentChange.employee.medical_insurance_region.code" style="color: #909399; font-size: 12px;">
                  编号：{{ currentChange.employee.medical_insurance_region.code }}
                </span>
              </div>
            </el-descriptions-item>
            <el-descriptions-item label="医保地区" v-else-if="shouldShowDetailCategory('medical_insurance')">
              <el-tag type="info" size="small">未设置</el-tag>
            </el-descriptions-item>

            <!-- 公积金地区 -->
            <el-descriptions-item label="公积金地区" v-if="shouldShowDetailCategory('housing_fund') && currentChange.employee && currentChange.employee.housing_fund_region">
              <div style="display: flex; flex-direction: column; gap: 4px;">
                <span>{{ currentChange.employee.housing_fund_region.region_name }}</span>
                <span v-if="currentChange.employee.housing_fund_region.account_number" style="color: #909399; font-size: 12px;">
                  账号：{{ currentChange.employee.housing_fund_region.account_number }}
                </span>
              </div>
            </el-descriptions-item>
            <el-descriptions-item label="公积金地区" v-else-if="shouldShowDetailCategory('housing_fund')">
              <el-tag type="info" size="small">未设置</el-tag>
            </el-descriptions-item>

            <!-- 大额医疗保险地区 -->
            <el-descriptions-item label="大额医疗地区" v-if="shouldShowDetailCategory('large_medical_insurance') && currentChange.employee && currentChange.employee.large_medical_insurance_config_relation">
              {{ currentChange.employee.large_medical_insurance_config_relation.region_name }}
            </el-descriptions-item>
            <el-descriptions-item label="大额医疗地区" v-else-if="shouldShowDetailCategory('large_medical_insurance')">
              <el-tag type="info" size="small">未设置</el-tag>
            </el-descriptions-item>
          </el-descriptions>
          <div class="form-tip">以上信息为该员工在员工档案中设置的参保地区信息。</div>
        </div>

        <!-- 员工保险基数信息 -->
        <div v-if="hasEmployeeBaseInfo()" class="insurance-details">
          <h4>员工保险基数</h4>
          <el-descriptions :column="2" size="small" border>
            <el-descriptions-item label="社保基数" v-if="shouldShowDetailCategory('social_security')">
              <span class="base-amount">¥{{ currentChange.employee_social_security_base || '0.00' }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="医保基数" v-if="shouldShowDetailCategory('medical_insurance')">
              <span class="base-amount">¥{{ currentChange.employee_medical_insurance_base || '0.00' }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="公积金基数" v-if="shouldShowDetailCategory('housing_fund')">
              <span class="base-amount">¥{{ currentChange.employee_housing_fund_base || '0.00' }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="大额医疗个人基数" v-if="shouldShowDetailCategory('large_medical_insurance')">
              <span class="base-amount">¥{{ currentChange.employee_large_medical_base || '0.00' }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="大额医疗单位基数" v-if="shouldShowDetailCategory('large_medical_insurance') && currentChange.employee_large_medical_company_base && currentChange.employee_large_medical_company_base != currentChange.employee_large_medical_base">
              <span class="base-amount">¥{{ currentChange.employee_large_medical_company_base || '0.00' }}</span>
              <el-tag type="warning" size="small" style="margin-left: 8px;">特殊地区</el-tag>
            </el-descriptions-item>
          </el-descriptions>
          <div class="form-tip">这些基数是员工档案中设置的保险缴费基数，用于计算各项保险费用。特殊地区的大额医疗保险支持个人和单位使用不同基数。</div>
        </div>

        <!-- 社保配置详情 -->
        <div v-if="shouldShowDetailCategory('social_security') && getSocialSecurityDetails().length > 0" class="insurance-details" :class="{ 'has-change': hasCategoryChange('social_security') || (currentChange.change_summary && currentChange.change_summary.includes('社保')) }">
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
        <div v-if="shouldShowDetailCategory('housing_fund') && getHousingFundDetails()" class="insurance-details" :class="{ 'has-change': hasCategoryChange('housing_fund') || (currentChange.change_summary && currentChange.change_summary.includes('公积金')) }">
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
        <div v-if="shouldShowDetailCategory('medical_insurance') && getMedicalInsuranceDetails().length > 0" class="insurance-details" :class="{ 'has-change': hasCategoryChange('medical_insurance') || (currentChange.change_summary && currentChange.change_summary.includes('医保')) }">
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

        <!-- 商业保险信息 -->
        <div v-if="shouldShowDetailCategory('other_insurance') && getOtherInsuranceDetails().length > 0" class="insurance-details" :class="{ 'has-change': hasCategoryChange('other_insurance') || (currentChange.change_summary && (currentChange.change_summary.includes('其他保险') || currentChange.change_summary.includes('商业保险'))) }">
          <h4>
            商业保险信息
            <el-tag v-if="hasCategoryChange('other_insurance') || (currentChange.change_summary && (currentChange.change_summary.includes('其他保险') || currentChange.change_summary.includes('商业保险')))" type="danger" size="small" effect="dark" style="margin-left: 10px;">
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
                {{ row.type || '商业保险' }}
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
            <el-table-column prop="employee_per_capita_cost" label="参保费用" width="280">
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
            <el-table-column
              v-if="currentChange && currentChange.change_type === 'decrease'"
              prop="surrender_amount"
              label="退保金额"
              width="180"
            >
              <template #default="{ row }">
                <el-input-number
                  v-if="canEditOtherInsuranceSurrenderAmount()"
                  v-model="row.surrender_amount"
                  :precision="2"
                  :step="1"
                  :min="0"
                  size="small"
                  style="width: 130px;"
                  @change="saveSurrenderAmount(row)"
                />
                <span v-else>¥{{ row.surrender_amount || '0.00' }}</span>
              </template>
            </el-table-column>
          </el-table>
          <div class="form-tip">该项目绑定的商业保险，无需选择地区。数据导入后不可修改。</div>
        </div>

        <!-- 大额医疗保险 -->
        <div v-if="shouldShowDetailCategory('large_medical_insurance') && currentChange && getLargeMedicalInsuranceDetails()" class="insurance-details" :class="{ 'has-change': hasCategoryChange('large_medical_insurance') || (currentChange.change_summary && currentChange.change_summary.includes('大额')) }">
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
          </el-table>
        </div>
      </div>
    </el-dialog>

    <!-- 社保汇总表预览 -->
    <el-dialog
      v-model="showSummaryPreviewDialog"
      title="社保汇总表预览"
      width="90%"
      top="5vh"
      :close-on-click-modal="false"
    >
      <div class="summary-preview-wrapper">
        <table class="summary-preview-table">
          <thead>
            <tr>
              <th :colspan="summaryTableColumnCount" class="main-title">
                {{ getCompanyNameWithRegion() }}{{ summaryPreviewMeta.monthText }}社保汇总表
              </th>
            </tr>
            <tr>
              <th :colspan="summaryTableColumnCount" class="sub-title">
                社保编号:{{ summaryPreviewMeta.socialSecurityCode || '未设置' }}
              </th>
            </tr>
            <tr>
              <th :colspan="summaryTableColumnCount" class="sub-title">
                医保编号:{{ summaryPreviewMeta.medicalInsuranceCode || '未设置' }}
              </th>
            </tr>
            <tr>
              <th rowspan="2">项目名称</th>
              <th rowspan="2">社保缴费人数</th>
              <th rowspan="2">医保缴费人数</th>
              <th rowspan="2">类别</th>
              <th rowspan="2">所属期</th>
              <th :colspan="dynamicCompanyColumns.length">单位部分</th>
              <th :colspan="dynamicEmployeeColumns.length">个人部分</th>
              <th colspan="2">实缴金额</th>
              <th rowspan="2">合计</th>
            </tr>
            <tr>
              <th v-for="column in dynamicCompanyColumns" :key="'preview-company-head-' + column.name">
                {{ column.name }}
              </th>
              <th v-for="column in dynamicEmployeeColumns" :key="'preview-employee-head-' + column.name">
                {{ column.name }}
              </th>
              <th>单位本金</th>
              <th>个人本金</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(item, index) in summaryPreviewRows"
              :key="'summary-preview-' + index"
              :class="getSummaryPreviewRowClass(item)"
            >
              <td>{{ item.project_name }}</td>
              <td>{{ item.counts?.social ?? '-' }}</td>
              <td>{{ item.counts?.medical ?? '-' }}</td>
              <td>{{ item.category }}</td>
              <td>{{ item.period || '-' }}</td>
              <td v-for="column in dynamicCompanyColumns" :key="'preview-company-' + index + '-' + column.name">
                {{ getSummaryCompanyCell(item, column) }}
              </td>
              <td v-for="column in dynamicEmployeeColumns" :key="'preview-employee-' + index + '-' + column.name">
                {{ getSummaryEmployeeCell(item, column) }}
              </td>
              <td>{{ getSummaryCompanyTotalCell(item) }}</td>
              <td>{{ getSummaryEmployeeTotalCell(item) }}</td>
              <td>{{ getSummaryGrandTotalCell(item) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <template #footer>
        <el-button @click="showSummaryPreviewDialog = false">关闭</el-button>
        <el-button type="primary" @click="handleSummaryPreviewExport" :loading="exportLoading">
          <el-icon><Download /></el-icon>
          导出Excel
        </el-button>
      </template>
    </el-dialog>

    <!-- 公积金汇总表预览 -->
    <el-dialog
      v-model="showHousingFundSummaryPreviewDialog"
      title="公积金汇总表预览"
      width="90%"
      top="5vh"
      :close-on-click-modal="false"
    >
      <iframe
        class="housing-summary-preview-frame"
        :srcdoc="housingFundSummaryPreviewHtml"
        title="公积金汇总表预览"
      />
      <template #footer>
        <el-button @click="showHousingFundSummaryPreviewDialog = false">关闭</el-button>
        <el-button type="primary" @click="exportHousingFundSummaryAction" :loading="exportLoading">
          <el-icon><Download /></el-icon>
          导出Excel
        </el-button>
      </template>
    </el-dialog>

    <!-- 滞留金填写 -->
    <el-dialog
      v-model="showSummaryRetentionDialog"
      title="填写滞留金"
      width="90%"
      top="8vh"
      :close-on-click-modal="false"
    >
      <el-alert
        title="不需要填写的字段可以留空，导出时会显示为 -。"
        type="info"
        :closable="false"
        style="margin-bottom: 12px;"
      />

      <div class="summary-preview-wrapper">
        <table class="summary-preview-table summary-retention-table">
          <thead>
            <tr>
              <th rowspan="2">项目名称</th>
              <th rowspan="2">社保缴费人数</th>
              <th rowspan="2">医保缴费人数</th>
              <th rowspan="2">类别</th>
              <th rowspan="2">所属期</th>
              <th :colspan="dynamicCompanyColumns.length">单位部分</th>
              <th :colspan="dynamicEmployeeColumns.length">个人部分</th>
              <th colspan="2">实缴金额</th>
              <th rowspan="2">合计</th>
            </tr>
            <tr>
              <th v-for="column in dynamicCompanyColumns" :key="'retention-company-head-' + column.name">
                {{ column.name }}
              </th>
              <th v-for="column in dynamicEmployeeColumns" :key="'retention-employee-head-' + column.name">
                {{ column.name }}
              </th>
              <th>单位本金</th>
              <th>个人本金</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><el-input v-model="summaryRetentionForm.project_name" placeholder="不填为-" size="small" /></td>
              <td><el-input v-model="summaryRetentionForm.social_count" placeholder="不填为-" size="small" /></td>
              <td><el-input v-model="summaryRetentionForm.medical_count" placeholder="不填为-" size="small" /></td>
              <td><el-input v-model="summaryRetentionForm.category" placeholder="不填为-" size="small" /></td>
              <td><el-input v-model="summaryRetentionForm.period" placeholder="不填为-" size="small" /></td>
              <td v-for="column in dynamicCompanyColumns" :key="'retention-company-' + column.name">
                <el-input
                  v-model="summaryRetentionForm.company[getSummaryCompanyField(column)]"
                  placeholder="不填为-"
                  size="small"
                />
              </td>
              <td v-for="column in dynamicEmployeeColumns" :key="'retention-employee-' + column.name">
                <el-input
                  v-model="summaryRetentionForm.employee[getSummaryEmployeeField(column)]"
                  placeholder="不填为-"
                  size="small"
                />
              </td>
              <td><el-input v-model="summaryRetentionForm.company_principal" placeholder="不填为-" size="small" /></td>
              <td><el-input v-model="summaryRetentionForm.employee_principal" placeholder="不填为-" size="small" /></td>
              <td><el-input v-model="summaryRetentionForm.total_amount" placeholder="不填为-" size="small" /></td>
            </tr>
          </tbody>
        </table>
      </div>

      <template #footer>
        <el-button @click="showSummaryRetentionDialog = false">取消</el-button>
        <el-button type="primary" @click="confirmSummaryRetentionExport" :loading="exportLoading">
          确认导出
        </el-button>
      </template>
    </el-dialog>
    
    <!-- 社保明细编辑：先修改明细，提交审批时只填写原因 -->
    <el-dialog
      v-model="showSocialDetailEditDialog"
      title="修改社保明细"
      width="520px"
      :close-on-click-modal="false"
    >
      <el-descriptions :column="2" border size="small" style="margin-bottom: 18px;">
        <el-descriptions-item label="姓名">
          {{ socialDetailEditForm.employee_name || '-' }}
        </el-descriptions-item>
        <el-descriptions-item label="所属期">
          {{ socialDetailEditForm.month || '-' }}
        </el-descriptions-item>
      </el-descriptions>

      <el-form
        ref="socialDetailEditFormRef"
        :model="socialDetailEditForm"
        :rules="socialDetailEditRules"
        label-width="100px"
      >
        <el-form-item label="社保基数" prop="social_security_base">
          <el-input-number
            v-model="socialDetailEditForm.social_security_base"
            :min="0"
            :precision="2"
            :step="100"
            controls-position="right"
            style="width: 100%;"
          />
        </el-form-item>
        <el-form-item label="医保基数" prop="medical_insurance_base">
          <el-input-number
            v-model="socialDetailEditForm.medical_insurance_base"
            :min="0"
            :precision="2"
            :step="100"
            controls-position="right"
            style="width: 100%;"
          />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="cancelSocialDetailEdit">取消</el-button>
        <el-button
          type="primary"
          @click="continueSocialDetailEdit"
        >
          填写原因
        </el-button>
      </template>
    </el-dialog>

    <el-dialog
      v-model="showSocialDetailEditReasonDialog"
      title="提交社保明细修改审批"
      width="520px"
      :close-on-click-modal="false"
    >
      <el-descriptions :column="1" border size="small" style="margin-bottom: 18px;">
        <el-descriptions-item label="修改对象">
          {{ socialDetailEditForm.employee_name || '-' }}（{{ socialDetailEditForm.month || '-' }}）
        </el-descriptions-item>
        <el-descriptions-item label="修改内容">
          社保基数 {{ formatEditBase(socialDetailEditForm.original_social_security_base) }} →
          {{ formatEditBase(socialDetailEditForm.social_security_base) }}；
          医保基数 {{ formatEditBase(socialDetailEditForm.original_medical_insurance_base) }} →
          {{ formatEditBase(socialDetailEditForm.medical_insurance_base) }}
        </el-descriptions-item>
      </el-descriptions>

      <el-form
        ref="socialDetailEditReasonFormRef"
        :model="socialDetailEditForm"
        :rules="socialDetailEditReasonRules"
        label-width="80px"
      >
        <el-form-item label="修改原因" prop="reason">
          <el-input
            v-model="socialDetailEditForm.reason"
            type="textarea"
            :rows="4"
            maxlength="1000"
            show-word-limit
            placeholder="请输入修改原因"
          />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="backToSocialDetailEdit">返回修改</el-button>
        <el-button
          type="primary"
          :loading="socialDetailEditLoading"
          @click="submitSocialDetailEditForm"
        >
          确认提交
        </el-button>
      </template>
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
import { ref, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { DocumentAdd, Download, Warning, Plus, Minus, Edit, InfoFilled, ArrowDown, ArrowRight, Check, Document } from '@element-plus/icons-vue'
import { useAccountSetStore } from '@/stores/accountSet'
import request from '@/api/request'
import {
  getInsuranceChanges,
  getInsuranceChangeDetails,
  submitSocialDetailEdit as submitSocialDetailEditRequest,
  getInsuranceChangeSummaries,
  generateSummary,
  exportSummary,
  updateEndorsementNumber,
  getSocialSecurityCompensationList,
  getHousingFundCompensationList
} from '@/api/insuranceChange'
import { getSocialSecurityRegions } from '@/api/socialSecurity'
import { getMedicalInsuranceRegions } from '@/api/medicalInsurance'
import {
  exportSocialSecurityToExcelHTML,
  exportToExcelHTML,
  exportHousingFundSummaryToExcel,
  buildHousingFundSummaryHTML
} from '@/utils/excelExportHTML'

const props = defineProps({
  previewOnly: {
    type: Boolean,
    default: false
  }
})
const accountSetStore = useAccountSetStore()

// 计算属性
const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)
const currentAccountSetCompanyName = computed(() => accountSetStore.currentAccountSet?.company_name || '')
const getCompanyNameWithRegion = (regionName = detailFilterForm.value.region_name || '全部地区') => {
  return `${currentAccountSetCompanyName.value}（${regionName}）`
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
  
  return `${getCompanyNameWithRegion(regionName)}${formattedMonth}社保明细`
}

// 生成公积金明细标题
const getHousingFundTitle = () => {
  const regionName = detailFilterForm.value.region_name || '全部地区'
  const month = detailFilterForm.value.month || getCurrentMonth()
  
  // 格式化月份显示：202507 格式
  let formattedMonth = month
  if (month && month.includes('-')) {
    const [year, monthNum] = month.split('-')
    formattedMonth = `${year}${monthNum.padStart(2, '0')}`
  }
  
  return `${getCompanyNameWithRegion(regionName)}${formattedMonth}公积金明细`
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

const parseJsonArray = (value) => {
  if (!hasSnapshotValue(value)) {
    return []
  }

  if (Array.isArray(value)) {
    return value
  }

  if (typeof value === 'string') {
    try {
      const parsed = JSON.parse(value)
      return Array.isArray(parsed) ? parsed : []
    } catch (e) {
      console.error('解析数组配置失败:', e)
      return []
    }
  }

  return []
}

const getCurrentSocialSecurityRegion = () => {
  return socialSecurityRegions.value.find(region => region.name === detailFilterForm.value.region_name) || null
}

const getCurrentMedicalInsuranceRegion = () => {
  return medicalInsuranceRegions.value.find(region => region.name === detailFilterForm.value.region_name) || null
}

const getCurrentLargeMedicalConfig = () => {
  return largeMedicalConfigs.value.find(config => config.region_name === detailFilterForm.value.region_name) || null
}

const formatRatioLabel = (value) => {
  if (value === null || value === undefined || value === '') {
    return ''
  }

  const numericValue = Number(value)
  if (Number.isNaN(numericValue)) {
    return ''
  }

  const percentage = Math.abs(numericValue) <= 1 ? numericValue * 100 : numericValue
  const fixedValue = percentage.toFixed(4).replace(/\.?0+$/, '')
  return `${fixedValue}%`
}

const getInsuranceColumnRatioLabel = (column, side) => {
  const ratio = side === 'company' ? column.companyRatio : column.employeeRatio
  return formatRatioLabel(ratio)
}

const parseInsuranceNumber = (value) => {
  if (value === null || value === undefined || value === '') {
    return 0
  }

  const numericValue = Number(value)
  return Number.isNaN(numericValue) ? 0 : numericValue
}

const isTruthyConfigValue = (value) => value === true || value === 1 || value === '1'

const shouldShowEmployeeInsuranceColumn = (column) => {
  if (!column) {
    return false
  }

  if (column.onlyCompanyPay) {
    return false
  }

  if (column.calculationType === 'fixed') {
    return parseInsuranceNumber(column.employeeAmount) > 0
  }

  return parseInsuranceNumber(column.employeeRatio) > 0
}

// 动态列配置
const dynamicCompanyColumns = computed(() => {
  const columns = []
  const socialRegion = getCurrentSocialSecurityRegion()
  const medicalRegion = getCurrentMedicalInsuranceRegion()
  const largeMedicalConfig = getCurrentLargeMedicalConfig()

  const socialSecurityTypes = parseJsonArray(socialRegion?.socialSecurityTypes || socialRegion?.social_security_types)
  if (socialSecurityTypes.length > 0) {
    socialSecurityTypes.forEach(type => {
      columns.push({
        name: type.name,
        type: 'social_security',
        fieldPrefix: '社保_',
        companyRatio: type.company_ratio,
        employeeRatio: type.employee_ratio,
        onlyCompanyPay: isTruthyConfigValue(type.only_company_pay || type.onlyCompanyPay) || parseInsuranceNumber(type.employee_ratio) === 0
      })
    })
  }

  const medicalInsuranceTypes = parseJsonArray(medicalRegion?.medicalInsuranceTypes || medicalRegion?.medical_insurance_types)
  if (medicalInsuranceTypes.length > 0) {
    medicalInsuranceTypes.forEach(type => {
      columns.push({
        name: type.name,
        type: 'medical_insurance',
        fieldPrefix: '医保_',
        companyRatio: type.company_ratio,
        employeeRatio: type.employee_ratio,
        onlyCompanyPay: isTruthyConfigValue(type.only_company_pay || type.onlyCompanyPay) || parseInsuranceNumber(type.employee_ratio) === 0
      })
    })
  }

  if (largeMedicalConfig && Number(largeMedicalConfig.status) === 1) {
    columns.push({
      name: '大额医疗',
      type: 'large_medical',
      companyRatio: largeMedicalConfig.company_ratio,
      employeeRatio: largeMedicalConfig.employee_ratio,
      calculationType: largeMedicalConfig.calculation_type,
      companyAmount: largeMedicalConfig.company_amount,
      employeeAmount: largeMedicalConfig.employee_amount
    })
  }

  return columns
})

const dynamicEmployeeColumns = computed(() => {
  return dynamicCompanyColumns.value.filter(shouldShowEmployeeInsuranceColumn)
})

const getHousingFundRatioFromDetails = (side) => {
  const detail = details.value.find(item => item?.insurance_personnel?.housing_fund_params)
  const params = detail?.insurance_personnel?.housing_fund_params
  if (!params) {
    return ''
  }

  try {
    const parsedParams = typeof params === 'string' ? JSON.parse(params) : params
    return side === 'company' ? parsedParams?.company_ratio : parsedParams?.employee_ratio
  } catch (error) {
    console.error('解析公积金比例失败:', error)
    return ''
  }
}

const getHousingFundRatioLabel = (side) => {
  return formatRatioLabel(getHousingFundRatioFromDetails(side))
}

const detailSummaryRowClassName = ({ row }) => {
  if (row?.isTotalRow) {
    return 'detail-total-row'
  }

  if (row?.isSummaryRow) {
    return 'detail-summary-row'
  }

  return ''
}

const formatDisplayBase = (...values) => {
  for (const value of values) {
    if (value === null || value === undefined || value === '') {
      continue
    }

    const amount = Number(value)
    if (Number.isFinite(amount)) {
      return amount.toFixed(2)
    }
  }

  return '0.00'
}

const buildSocialSecurityDetailRow = (detail, serialNumber, typeLabel) => {
  const employee = detail.employee || {}
  const project = detail.project || {}
  const socialRegion = getCurrentSocialSecurityRegion()
  const medicalRegion = getCurrentMedicalInsuranceRegion()
  const largeMedicalConfig = getCurrentLargeMedicalConfig()
  const socialSecurityTypes = parseJsonArray(socialRegion?.socialSecurityTypes || socialRegion?.social_security_types)
  const medicalInsuranceTypes = parseJsonArray(medicalRegion?.medicalInsuranceTypes || medicalRegion?.medical_insurance_types)

  const medicalCompanyAmount = parseFloat(detail.medical_insurance_company_amount || 0)
  const medicalEmployeeAmount = parseFloat(detail.medical_insurance_employee_amount || 0)
  const socialCompanyAmount = parseFloat(detail.social_security_company_amount || 0)
  const socialEmployeeAmount = parseFloat(detail.social_security_employee_amount || 0)
  const largeMedicalCompanyAmount = parseFloat(detail.large_medical_company_amount || 0)
  const largeMedicalEmployeeAmount = parseFloat(detail.large_medical_employee_amount || 0)

  const companyTotal = medicalCompanyAmount + socialCompanyAmount + largeMedicalCompanyAmount
  const employeeTotal = medicalEmployeeAmount + socialEmployeeAmount + largeMedicalEmployeeAmount
  const socialSecurityTotal = companyTotal + employeeTotal
  const socialBaseAmount = parseFloat(detail.employee_social_security_base || 0)
  const medicalBaseAmount = parseFloat(detail.employee_medical_insurance_base || 0)

  const rowData = {
    serial_number: serialNumber,
    detail_id: detail.id,
    detail_source: detail.source || 'current',
    detail_month: detail.payment_period || (
      detail.record_year && detail.record_month
        ? String(detail.record_year) + '-' + String(detail.record_month).padStart(2, '0')
        : detailFilterForm.value.month
    ),
    detail_project_id: detail.project_id || project.id || null,
    can_edit_social_detail: typeLabel === '正常' && detail.can_edit_social_detail !== false,
    employee_name: detail.employee_name || employee.name || '小计',
    id_number: detail.employee_id_number || employee.id_number || '',
    project_name: detail.project_name || (project ? project.name : ''),
    enrollment_date: formatEnrollmentDate(detail.social_insurance_enrollment_date || employee.social_insurance_enrollment_date || detail.created_at),
    type: typeLabel,
    period: formatPeriodString(detail.payment_period) || formatPeriod(detail.created_at),
    medical_base: formatDisplayBase(
      detail.employee_medical_insurance_base,
      detail.display_employee_medical_insurance_base,
      employee.medical_insurance_base
    ),
    social_security_base: formatDisplayBase(
      detail.employee_social_security_base,
      detail.display_employee_social_security_base,
      employee.social_security_base
    ),
    social_security_total: socialSecurityTotal.toFixed(2),
    remarks: ''
  }

  socialSecurityTypes.forEach(type => {
    rowData['company_社保_' + type.name] = (socialBaseAmount * parseFloat(type.company_ratio || 0)).toFixed(2)
    rowData['employee_社保_' + type.name] = (socialBaseAmount * parseFloat(type.employee_ratio || 0)).toFixed(2)
  })

  medicalInsuranceTypes.forEach(type => {
    rowData['company_医保_' + type.name] = (medicalBaseAmount * parseFloat(type.company_ratio || 0)).toFixed(2)
    rowData['employee_医保_' + type.name] = (medicalBaseAmount * parseFloat(type.employee_ratio || 0)).toFixed(2)
  })

  if (largeMedicalConfig && Number(largeMedicalConfig.status) === 1) {
    rowData['company_大额医疗'] = largeMedicalCompanyAmount.toFixed(2)
    rowData['employee_大额医疗'] = largeMedicalEmployeeAmount.toFixed(2)
  }

  rowData.company_total = companyTotal.toFixed(2)
  rowData.employee_total = employeeTotal.toFixed(2)
  rowData.social_security_total = socialSecurityTotal.toFixed(2)

  return rowData
}

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
  const normalData = normalEmployees.map((detail, index) => buildSocialSecurityDetailRow(detail, index + 1, '正常'))
  
  // 处理补交员工数据
  const supplementaryData = supplementaryEmployees.map((detail, index) => buildSocialSecurityDetailRow(detail, normalData.length + index + 1, '补交'))
  
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
      housing_fund_base: formatDisplayBase(
        detail.display_employee_housing_fund_base,
        employee.housing_fund_base,
        detail.employee_housing_fund_base
      ),
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
      housing_fund_base: formatDisplayBase(
        detail.display_employee_housing_fund_base,
        employee.housing_fund_base,
        detail.employee_housing_fund_base
      ),
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
const changeStatusTab = ref('increase')
const changeTableRef = ref(null)
const changeTableMaxHeight = ref(360)
const selectedProjectName = ref('')
const detailScopedCategory = ref('')
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
const processing = ref(false)
const processingOtherInsurance = ref(false)
const showSummaryPreviewDialog = ref(false)
const showHousingFundSummaryPreviewDialog = ref(false)
const housingFundSummaryPreviewHtml = ref('')
const showSummaryRetentionDialog = ref(false)
const summaryPreviewRows = ref([])
const summaryPreviewMeta = ref({
  monthText: '',
  socialSecurityCode: '',
  medicalInsuranceCode: ''
})
const createEmptySummaryRetentionForm = () => ({
  project_name: '滞留金',
  social_count: '',
  medical_count: '',
  category: '滞留金',
  period: '',
  company: {},
  employee: {},
  company_principal: '',
  employee_principal: '',
  total_amount: ''
})
const summaryRetentionForm = ref(createEmptySummaryRetentionForm())
const summaryTableColumnCount = computed(() => 5 + dynamicCompanyColumns.value.length + dynamicEmployeeColumns.value.length + 3)

// 数据
const changes = ref([])
const details = ref([])
const summaries = ref([])
const regions = ref([])
const rawCompensationData = ref([])
const socialSecurityRegions = ref([])
const medicalInsuranceRegions = ref([])
const largeMedicalConfigs = ref([])


const hasSnapshotValue = (value) => {
  if (value === null || value === undefined) return false
  if (typeof value === 'string') {
    const text = value.trim()
    return text !== '' && text !== '[]' && text !== '{}'
  }
  if (Array.isArray(value)) return value.length > 0
  if (typeof value === 'object') return Object.keys(value).length > 0
  return Boolean(value)
}

const parseChangeDetails = (change) => {
  if (!change) return []

  if (Array.isArray(change.parsed_change_details)) {
    return change.parsed_change_details
  }

  if (!change.change_details) {
    return []
  }

  try {
    const parsed = typeof change.change_details === 'string'
      ? JSON.parse(change.change_details)
      : change.change_details

    if (Array.isArray(parsed)) return parsed
    if (parsed && Array.isArray(parsed.changes)) return parsed.changes
  } catch (error) {
    console.warn('parse change_details failed:', error)
  }

  return []
}

const getChangeCategories = (change) => {
  const categories = new Set()
  if (!change) return categories

  if (Array.isArray(change.change_items)) {
    change.change_items.forEach((item) => {
      if (item?.category) categories.add(item.category)
    })
  }

  parseChangeDetails(change).forEach((detail) => {
    if (detail?.category) categories.add(detail.category)
  })

  if (hasSnapshotValue(change.social_security_types)) categories.add('social_security')
  if (hasSnapshotValue(change.medical_insurance_types)) categories.add('medical_insurance')
  if (hasSnapshotValue(change.housing_fund_params)) categories.add('housing_fund')
  if (hasSnapshotValue(change.large_medical_insurance_config) || Number(change.large_medical_insurance_enabled) === 1) {
    categories.add('large_medical_insurance')
  }
  if (hasSnapshotValue(change.other_insurance_policies)) categories.add('other_insurance')

  return categories
}

const changeHasCategory = (change, category) => {
  if (!category) return true
  return getChangeCategories(change).has(category)
}

const getChangeProjectName = (change) => {
  return change?.project?.name || change?.project_name || '未分配项目'
}

const insuranceCategoryColumns = [
  { key: 'social_security', label: '社保' },
  { key: 'medical_insurance', label: '医保' },
  { key: 'housing_fund', label: '公积金' },
  { key: 'large_medical_insurance', label: '大额医疗' }
]

const getCategoryItem = (change, category) => {
  if (!Array.isArray(change?.change_items)) {
    return null
  }

  return change.change_items.find((item) => item?.category === category) || null
}

const getCategoryDisplayStatus = (change, category) => {
  const item = getCategoryItem(change, category)
  if (item?.status) {
    return item.status
  }

  if (Array.isArray(change?.change_items) && change.change_items.length > 0) {
    return ''
  }

  if (changeHasCategory(change, category)) {
    return change?.status || 'pending'
  }

  return ''
}

const getSocialSecurityRegionName = (change) => {
  return change?.employee?.social_security_region?.name ||
    change?.employee?.socialSecurityRegion?.name ||
    change?.social_security_region_name ||
    ''
}

const getHousingFundRegionName = (change) => {
  return change?.employee?.housing_fund_region?.region_name ||
    change?.employee?.housingFundRegion?.region_name ||
    change?.housing_fund_region_name ||
    ''
}

const getPrimaryRegionName = (change) => {
  return getSocialSecurityRegionName(change) ||
    getHousingFundRegionName(change) ||
    change?.employee?.medical_insurance_region?.name ||
    change?.employee?.medicalInsuranceRegion?.name ||
    ''
}

const getChangeLeaveReason = (change) => {
  if (!change || change.change_type !== 'decrease') {
    return ''
  }

  const note = typeof change.notes === 'string' ? change.notes.trim() : ''
  if (!note || ['员工离职，停止参保', '员工退休，停止参保'].includes(note)) {
    return ''
  }

  return note
}

const parseOtherInsurancePolicies = (change) => {
  const policySources = [
    change?.other_insurance_policies,
    getCategoryItem(change, 'other_insurance')?.category_snapshot,
    ...(Array.isArray(change?.change_items)
      ? change.change_items
        .filter((item) => isOtherInsurancePolicyCategory(item?.category))
        .map((item) => item.category_snapshot)
      : [])
  ]

  for (const source of policySources) {
    if (!source) {
      continue
    }

    let parsed = source

    if (typeof parsed === 'string') {
      try {
        parsed = JSON.parse(parsed)
      } catch (error) {
        continue
      }
    }

    if (Array.isArray(parsed)) {
      return parsed
    }

    if (parsed && Array.isArray(parsed.other_insurance_policies)) {
      return parsed.other_insurance_policies
    }
  }

  return []
}

const OTHER_INSURANCE_POLICY_CATEGORY_PREFIX = 'other_policy:'

const isOtherInsurancePolicyCategory = (category) => {
  return typeof category === 'string' && category.startsWith(OTHER_INSURANCE_POLICY_CATEGORY_PREFIX)
}

const getOtherInsurancePolicyId = (policy = {}) => {
  return policy?.id ?? policy?.policy_id ?? ''
}

const getOtherInsurancePolicyCategory = (policy = {}, index = 0) => {
  const policyId = getOtherInsurancePolicyId(policy)
  return `${OTHER_INSURANCE_POLICY_CATEGORY_PREFIX}${policyId || `idx${index}`}`
}

const parseOtherInsurancePolicySnapshot = (source) => {
  if (!source) return null

  let parsed = source
  if (typeof parsed === 'string') {
    try {
      parsed = JSON.parse(parsed)
    } catch (error) {
      return null
    }
  }

  if (Array.isArray(parsed)) {
    return parsed[0] && typeof parsed[0] === 'object' ? parsed[0] : null
  }

  if (parsed && Array.isArray(parsed.other_insurance_policies)) {
    return parsed.other_insurance_policies[0] || null
  }

  return parsed && typeof parsed === 'object' ? parsed : null
}

const getOtherInsurancePolicyLabel = (policy = {}) => {
  const candidates = [
    policy.policy_name,
    policy.name,
    policy.type_name,
    policy.insurance_type_name,
    policy.insurance_type_text,
    policy.policy_type_name,
    policy.type
  ]

  for (const candidate of candidates) {
    const name = normalizeOtherInsuranceTypeValue(candidate)
    if (name && name !== '[object Object]') {
      return name
    }
  }

  return '商业保险'
}

const getOtherInsuranceDetailRows = (change) => {
  const items = Array.isArray(change?.change_items) ? change.change_items : []
  const policyItems = items.filter((item) => isOtherInsurancePolicyCategory(item?.category))
  const policies = parseOtherInsurancePolicies(change)

  if (policyItems.length > 0) {
    return policyItems.map((item, index) => {
      const snapshotPolicy = parseOtherInsurancePolicySnapshot(item.category_snapshot)
      const policy = snapshotPolicy || policies.find((candidate, policyIndex) => {
        return getOtherInsurancePolicyCategory(candidate, policyIndex) === item.category
      }) || {}

      return {
        key: item.id || item.category || index,
        label: getOtherInsurancePolicyLabel(policy),
        status: item.status || ''
      }
    })
  }

  const fallbackItem = getCategoryItem(change, 'other_insurance')
  return policies.map((policy, index) => {
    const category = getOtherInsurancePolicyCategory(policy, index)
    const item = getCategoryItem(change, category) || fallbackItem
    return {
      key: item?.id || category,
      label: getOtherInsurancePolicyLabel(policy),
      status: item?.status || (Array.isArray(change?.change_items) && change.change_items.length > 0 ? '' : (change?.status || 'pending'))
    }
  })
}

const getOtherInsuranceTypeNames = (change) => {
  const names = parseOtherInsurancePolicies(change)
    .map((policy) => {
      if (!policy || typeof policy !== 'object') {
        return ''
      }

      return resolveOtherInsuranceTypeName(policy)
    })
    .map((name) => (typeof name === 'string' ? name.trim() : ''))
    .filter(Boolean)

  return Array.from(new Set(names))
}

const projectOptions = computed(() => {
  return Array.from(new Set(
    changes.value
      .map(change => getChangeProjectName(change))
      .filter(Boolean)
  )).sort((a, b) => a.localeCompare(b, 'zh-CN'))
})

const filteredChanges = computed(() => {
  return changes.value.filter((change) => {
    if (selectedProjectName.value && getChangeProjectName(change) !== selectedProjectName.value) {
      return false
    }

    if (changeStatusTab.value === 'decrease') {
      return change.change_type === 'decrease'
    }

    return change.change_type !== 'decrease'
  })
})

const normalizeRowStatus = (status) => {
  if (status === 'submitted') {
    return 'pending'
  }

  return status || 'pending'
}

const changeStats = computed(() => {
  const stats = {
    pending: new Set(),
    success: new Set(),
    terminated: new Set(),
    failed: new Set()
  }

  filteredChanges.value.forEach((change) => {
    const employeeKey = change.employee?.id || change.employee_id || `change-${change.id}`
    const status = normalizeRowStatus(change.status)

    if (status === 'completed') {
      stats.success.add(employeeKey)
      return
    }

    if (status === 'failed') {
      stats.failed.add(employeeKey)
      return
    }

    if (status === 'terminated') {
      stats.terminated.add(employeeKey)
      return
    }

    stats.pending.add(employeeKey)
  })

  return {
    pending: stats.pending.size,
    success: stats.success.size,
    terminated: stats.terminated.size,
    failed: stats.failed.size
  }
})

const resolveDetailCategory = (change) => {
  return ''
}

const shouldShowDetailCategory = (category) => {
  return !detailScopedCategory.value || detailScopedCategory.value === category
}

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
const isInsuranceDynamicAmountColumn = (key) => {
  return key.startsWith('company_社保_') ||
    key.startsWith('employee_社保_') ||
    key.startsWith('company_医保_') ||
    key.startsWith('employee_医保_') ||
    key === 'company_大额医疗' ||
    key === 'employee_大额医疗'
}

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
      if (isInsuranceDynamicAmountColumn(key)) {
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
      if (isInsuranceDynamicAmountColumn(key)) {
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
      if (isInsuranceDynamicAmountColumn(key)) {
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
const syncAvailableRegions = () => {
  regions.value = Array.from(new Set([
    ...socialSecurityRegions.value.map(region => region.name),
    ...medicalInsuranceRegions.value.map(region => region.name),
    ...largeMedicalConfigs.value.map(config => config.region_name)
  ].filter(Boolean)))

  if (!regions.value.includes(detailFilterForm.value.region_name)) {
    detailFilterForm.value.region_name = getDefaultDetailRegion()
  }
}

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
      syncAvailableRegions()
    } else {
      console.warn('加载社保地区失败:', response.message)
      socialSecurityRegions.value = []
      syncAvailableRegions()
    }
  } catch (error) {
    console.error('加载社保地区失败:', error)
    socialSecurityRegions.value = []
    syncAvailableRegions()
  }
}

const loadMedicalInsuranceRegions = async () => {
  if (!currentAccountSetId.value) {
    return
  }

  try {
    const response = await getMedicalInsuranceRegions({
      account_set_id: currentAccountSetId.value
    })

    if (response.success) {
      medicalInsuranceRegions.value = response.data || []
      syncAvailableRegions()
    } else {
      console.warn('加载医保地区失败:', response.message)
      medicalInsuranceRegions.value = []
      syncAvailableRegions()
    }
  } catch (error) {
    console.error('加载医保地区失败:', error)
    medicalInsuranceRegions.value = []
    syncAvailableRegions()
  }
}

const loadLargeMedicalConfigs = async () => {
  if (!currentAccountSetId.value) {
    return
  }

  try {
    const response = await request.get('/large-medical-insurance', {
      params: {
        account_set_id: currentAccountSetId.value
      }
    })

    if (response.success) {
      largeMedicalConfigs.value = response.data || []
      syncAvailableRegions()
    } else {
      console.warn('加载大额医疗配置失败:', response.message)
      largeMedicalConfigs.value = []
      syncAvailableRegions()
    }
  } catch (error) {
    console.error('加载大额医疗配置失败:', error)
    largeMedicalConfigs.value = []
    syncAvailableRegions()
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
    
    summaryPreviewRows.value = summaryData
    summaryPreviewMeta.value = {
      monthText,
      socialSecurityCode,
      medicalInsuranceCode
    }
    showSummaryPreviewDialog.value = true

    ElMessage.success('汇总表已生成，可预览后导出')
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

const getSummaryCompanyField = (column) => {
  return 'company_' + (column.fieldPrefix || '') + column.name
}

const getSummaryEmployeeField = (column) => {
  return 'employee_' + (column.fieldPrefix || '') + column.name
}

const normalizeSummaryInput = (value) => {
  if (value === null || value === undefined) {
    return '-'
  }

  const text = String(value).trim()
  return text === '' ? '-' : text
}

const formatSummaryAmountCell = (value) => {
  if (value === null || value === undefined || value === '') {
    return '-'
  }

  if (typeof value === 'number') {
    return Number.isFinite(value) ? value.toFixed(2) : '-'
  }

  const text = String(value).trim()
  if (text === '' || text === '-') {
    return '-'
  }

  const numericValue = Number(text)
  return Number.isNaN(numericValue) ? text : numericValue.toFixed(2)
}

const getSummaryCompanyCell = (item, column) => {
  return formatSummaryAmountCell(item.company?.[getSummaryCompanyField(column)])
}

const getSummaryEmployeeCell = (item, column) => {
  return formatSummaryAmountCell(item.employee?.[getSummaryEmployeeField(column)])
}

const getSummaryCompanyTotalCell = (item) => {
  if (item.isRetentionRow) {
    return formatSummaryAmountCell(item.company_principal)
  }

  const total = Object.values(item.company || {}).reduce((sum, value) => sum + (Number(value) || 0), 0)
  return total.toFixed(2)
}

const getSummaryEmployeeTotalCell = (item) => {
  if (item.isRetentionRow) {
    return formatSummaryAmountCell(item.employee_principal)
  }

  const total = Object.values(item.employee || {}).reduce((sum, value) => sum + (Number(value) || 0), 0)
  return total.toFixed(2)
}

const getSummaryGrandTotalCell = (item) => {
  if (item.isRetentionRow) {
    return formatSummaryAmountCell(item.total_amount)
  }

  const companyTotal = Object.values(item.company || {}).reduce((sum, value) => sum + (Number(value) || 0), 0)
  const employeeTotal = Object.values(item.employee || {}).reduce((sum, value) => sum + (Number(value) || 0), 0)
  return (companyTotal + employeeTotal).toFixed(2)
}

const getSummaryPreviewRowClass = (item) => {
  if (item?.project_name === '合计') {
    return 'total-row'
  }

  if (item?.project_name === '小计') {
    return 'subtotal-row'
  }

  if (item?.isRetentionRow) {
    return 'retention-row'
  }

  return 'data-row'
}

const insertBeforeGrandTotal = (rows, insertedRow) => {
  const result = [...rows]
  const totalIndex = result.findIndex(item => item.project_name === '合计')
  if (totalIndex >= 0) {
    result.splice(totalIndex, 0, insertedRow)
  } else {
    result.push(insertedRow)
  }

  return result
}

const buildRetentionSummaryRow = () => {
  const form = summaryRetentionForm.value

  return {
    isRetentionRow: true,
    project_name: normalizeSummaryInput(form.project_name),
    category: normalizeSummaryInput(form.category),
    period: normalizeSummaryInput(form.period),
    counts: {
      social: normalizeSummaryInput(form.social_count),
      medical: normalizeSummaryInput(form.medical_count)
    },
    company: { ...form.company },
    employee: { ...form.employee },
    company_principal: normalizeSummaryInput(form.company_principal),
    employee_principal: normalizeSummaryInput(form.employee_principal),
    total_amount: normalizeSummaryInput(form.total_amount)
  }
}

const handleSummaryPreviewExport = async () => {
  if (!summaryPreviewRows.value.length) {
    ElMessage.warning('没有可导出的汇总表数据')
    return
  }

  try {
    await ElMessageBox.confirm(
      '是否需要填写滞留金？',
      '导出确认',
      {
        confirmButtonText: '需要填写',
        cancelButtonText: '不需要，直接导出',
        distinguishCancelAndClose: true,
        type: 'warning'
      }
    )

    summaryRetentionForm.value = createEmptySummaryRetentionForm()
    showSummaryRetentionDialog.value = true
  } catch (action) {
    if (action === 'cancel') {
      await exportSummaryTableToExcel(
        summaryPreviewRows.value,
        summaryPreviewMeta.value.monthText,
        summaryPreviewMeta.value.socialSecurityCode,
        summaryPreviewMeta.value.medicalInsuranceCode
      )
      ElMessage.success('导出成功')
    }
  }
}

const confirmSummaryRetentionExport = async () => {
  const rows = insertBeforeGrandTotal(summaryPreviewRows.value, buildRetentionSummaryRow())

  exportLoading.value = true
  try {
    await exportSummaryTableToExcel(
      rows,
      summaryPreviewMeta.value.monthText,
      summaryPreviewMeta.value.socialSecurityCode,
      summaryPreviewMeta.value.medicalInsuranceCode
    )
    showSummaryRetentionDialog.value = false
    showSummaryPreviewDialog.value = false
    ElMessage.success('导出成功')
  } catch (error) {
    console.error('导出汇总表失败:', error)
    ElMessage.error('导出失败，请重试')
  } finally {
    exportLoading.value = false
  }
}

const escapeHtml = (value) => {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

// 导出汇总表到Excel（使用HTML格式，与明细表相同）
const exportSummaryTableToExcel = async (summaryData, monthText, socialSecurityCode = '', medicalInsuranceCode = '') => {
  // 构建HTML表格
  let html = `
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>${getCompanyNameWithRegion()}${monthText}社保汇总表</title>
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
    .retention-row {
      background-color: #E3F2FD;
      font-weight: bold;
      font-size: 14px;
      color: #1E3A8A;
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
        <th colspan="${summaryTableColumnCount.value}" class="main-title">${getCompanyNameWithRegion()}${monthText}社保汇总表</th>
      </tr>
      <tr>
        <th colspan="${summaryTableColumnCount.value}" class="sub-title">社保编号:${socialSecurityCode || '未设置'}</th>
      </tr>
      <tr>
        <th colspan="${summaryTableColumnCount.value}" class="sub-title">医保编号:${medicalInsuranceCode || '未设置'}</th>
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
    const rowClass = getSummaryPreviewRowClass(item)
    
    html += `<tr class="${rowClass}">`
    html += `<td>${escapeHtml(item.project_name)}</td>`
    html += `<td>${escapeHtml(item.counts?.social ?? '-')}</td>`
    html += `<td>${escapeHtml(item.counts?.medical ?? '-')}</td>`
    html += `<td>${escapeHtml(item.category)}</td>`
    html += `<td>${escapeHtml(item.period || '-')}</td>`
    
    // 添加动态列数据
    dynamicCompanyColumns.value.forEach(column => {
      html += `<td>${escapeHtml(getSummaryCompanyCell(item, column))}</td>`
    })
    
    dynamicEmployeeColumns.value.forEach(column => {
      html += `<td>${escapeHtml(getSummaryEmployeeCell(item, column))}</td>`
    })
    
    html += `<td>${escapeHtml(getSummaryCompanyTotalCell(item))}</td>`
    html += `<td>${escapeHtml(getSummaryEmployeeTotalCell(item))}</td>`
    html += `<td>${escapeHtml(getSummaryGrandTotalCell(item))}</td>`
    html += `</tr>`
  })
  
  html += `
    </tbody>
  </table>
  
  <div class="footer-info">
    <div class="footer-row">制表人：&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;审核人：</div>
    <div class="footer-row" style="text-align: right;">${getCompanyNameWithRegion()}</div>
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
  link.download = `${getCompanyNameWithRegion()}${monthText}社保汇总表.xlsx`
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
    const filename = `${getCompanyNameWithRegion(regionName)}${formattedMonth}社保明细.xlsx`
    
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
      {
        companyColumns: dynamicCompanyColumns.value,
        employeeColumns: dynamicEmployeeColumns.value
      }
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
    const filename = `${getCompanyNameWithRegion()}${formattedMonth}公积金明细.xlsx`

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

const getHousingFundSummaryTitle = () => {
  const month = detailFilterForm.value.month || getCurrentMonth()
  let formattedMonth = month
  if (month && month.includes('-')) {
    const [year, monthNum] = month.split('-')
    formattedMonth = `${year}年${monthNum.padStart(2, '0')}月`
  }
  return `${getCompanyNameWithRegion()}${formattedMonth}公积金汇总表`
}

const generateHousingFundSummaryPreview = () => {
  if (!housingFundDetails.value || housingFundDetails.value.length === 0) {
    ElMessage.warning('没有数据可生成汇总表')
    return
  }

  housingFundSummaryPreviewHtml.value = buildHousingFundSummaryHTML(
    housingFundDetails.value,
    getHousingFundSummaryTitle()
  )
  showHousingFundSummaryPreviewDialog.value = true
  ElMessage.success('汇总表已生成，可预览后导出')
}

// 导出公积金汇总表
const exportHousingFundSummaryAction = async () => {
  if (!housingFundDetails.value || housingFundDetails.value.length === 0) {
    ElMessage.warning('没有数据可导出')
    return
  }

  exportLoading.value = true

  try {
    const title = getHousingFundSummaryTitle()
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
const detailProjectId = ref(null)

const summaryFilterForm = ref({
  region_name: ''
})

const detailTabNeedsRegionFilter = (tab = detailActiveTab.value) => {
  return ['social', 'compensation', 'housingFundCompensation', 'housing'].includes(tab)
}

const getDefaultDetailRegion = () => {
  return regions.value[0] || ''
}

const ensureDetailRegionSelected = (tab = detailActiveTab.value) => {
  if (!detailTabNeedsRegionFilter(tab)) {
    return true
  }

  if (detailFilterForm.value.region_name) {
    return true
  }

  const defaultRegion = getDefaultDetailRegion()
  if (!defaultRegion) {
    return false
  }

  detailFilterForm.value.region_name = defaultRegion
  return true
}

const loadActiveDetailData = () => {
  if (detailActiveTab.value === 'compensation') {
    loadCompensationDetails()
  } else if (detailActiveTab.value === 'housingFundCompensation') {
    loadHousingFundCompensationDetails()
  } else {
    loadDetails()
  }
}

// 对话框
const showUploadDialogFlag = ref(false)
const showViewFilesDialogFlag = ref(false)
const showDetailDialogFlag = ref(false)
const showSocialDetailEditDialog = ref(false)
const showSocialDetailEditReasonDialog = ref(false)
const socialDetailEditLoading = ref(false)
const socialDetailEditFormRef = ref(null)
const socialDetailEditReasonFormRef = ref(null)
const socialDetailEditForm = ref({
  detail_id: null,
  source: 'current',
  month: '',
  project_id: null,
  employee_name: '',
  original_social_security_base: 0,
  original_medical_insurance_base: 0,
  social_security_base: 0,
  medical_insurance_base: 0,
  reason: ''
})
const socialDetailEditRules = {
  social_security_base: [{ required: true, message: '请输入社保基数', trigger: 'change' }],
  medical_insurance_base: [{ required: true, message: '请输入医保基数', trigger: 'change' }]
}
const socialDetailEditReasonRules = {
  reason: [{ required: true, message: '请输入修改原因', trigger: 'blur' }]
}
const currentChange = ref(null)
const processingChangeId = ref(null)

const processForm = ref({
  item_ids: [],
  result: 'success'
})
const previousProcessItemIds = ref([])
const processOtherInsuranceAmountRows = ref([])

const uploadFormRef = ref()
const viewFilesFormRef = ref()
const uploadRef = ref()
const fileList = ref([])

const processableItems = computed(() => {
  if (!currentChange.value || !Array.isArray(currentChange.value.change_items)) {
    return []
  }

  const pendingItems = currentChange.value.change_items.filter((item) => ['pending', 'submitted'].includes(item.status))
  const hasOtherInsurancePolicyItems = pendingItems.some((item) => isOtherInsurancePolicyCategory(item?.category))

  return pendingItems.filter((item) => {
    return !(hasOtherInsurancePolicyItems && item?.category === 'other_insurance')
  })
})

const getProcessItemLabel = (item) => {
  if (isOtherInsurancePolicyCategory(item?.category)) {
    const policy = parseOtherInsurancePolicySnapshot(item?.category_snapshot)
    return policy ? getOtherInsuranceProcessItemLabel(policy) : '商业保险'
  }

  return getCategoryText(item?.category)
}

const getOtherInsuranceProcessItemLabel = (policy = {}) => {
  const typeName = resolveOtherInsuranceTypeName(policy)
  const policyName = getOtherInsurancePolicyLabel(policy)

  if (typeName && !['其他保险', '商业保险'].includes(typeName)) {
    return policyName && policyName !== typeName && !['其他保险', '商业保险'].includes(policyName)
      ? `${typeName}（${policyName}）`
      : typeName
  }

  return policyName || '商业保险'
}

const processableItemOptions = computed(() => {
  return processableItems.value
    .filter((item) => item?.id)
    .map((item) => ({
      value: Number(item.id),
      label: getProcessItemLabel(item)
  }))
})

const isOtherInsuranceProcessItem = (item) => {
  return item?.category === 'other_insurance' || isOtherInsurancePolicyCategory(item?.category)
}

const getProcessItemById = (id) => {
  const numericId = Number(id)
  return processableItems.value.find((item) => Number(item?.id) === numericId) || null
}

const selectedProcessItems = computed(() => {
  return (processForm.value.item_ids || [])
    .map((id) => getProcessItemById(id))
    .filter(Boolean)
})

const isOtherInsuranceProcessSelected = computed(() => {
  return selectedProcessItems.value.some((item) => isOtherInsuranceProcessItem(item))
})

const processOtherInsuranceAmountLabel = computed(() => {
  return currentChange.value?.change_type === 'decrease' ? '退保金额' : '参保费用'
})

const getPolicyByProcessItem = (item) => {
  const snapshotPolicy = parseOtherInsurancePolicySnapshot(item?.category_snapshot)
  if (snapshotPolicy) {
    return snapshotPolicy
  }

  const policies = parseOtherInsurancePolicies(currentChange.value)
  return policies.find((policy, index) => getOtherInsurancePolicyCategory(policy, index) === item?.category) || {}
}

const buildProcessOtherInsuranceAmountRows = () => {
  const selectedOtherItems = selectedProcessItems.value.filter((item) => isOtherInsuranceProcessItem(item))
  const rows = []

  selectedOtherItems.forEach((item) => {
    if (item.category === 'other_insurance') {
      parseOtherInsurancePolicies(currentChange.value).forEach((policy, index) => {
        rows.push(buildProcessOtherInsuranceAmountRow(policy, item, index))
      })
      return
    }

    rows.push(buildProcessOtherInsuranceAmountRow(getPolicyByProcessItem(item), item, rows.length))
  })

  const uniqueRows = []
  const usedKeys = new Set()
  rows.forEach((row) => {
    const key = row.policy_id || row.item_id || `${row.name}:${row.index}`
    if (usedKeys.has(key)) {
      return
    }
    usedKeys.add(key)
    uniqueRows.push(row)
  })

  processOtherInsuranceAmountRows.value = uniqueRows
}

const buildProcessOtherInsuranceAmountRow = (policy = {}, item = {}, index = 0) => {
  const amountField = currentChange.value?.change_type === 'decrease'
    ? 'surrender_amount'
    : 'employee_per_capita_cost'

  return {
    item_id: item?.id ? Number(item.id) : null,
    policy_id: getOtherInsurancePolicyId(policy),
    name: getOtherInsurancePolicyLabel(policy),
    type: resolveOtherInsuranceTypeName(policy),
    amount: Number(policy?.[amountField] ?? 0),
    index
  }
}

const handleProcessItemChange = (ids = []) => {
  const selectedIds = (ids || []).map((id) => Number(id)).filter(Boolean)
  const selectedItems = selectedIds.map((id) => getProcessItemById(id)).filter(Boolean)
  const hasOtherInsurance = selectedItems.some((item) => isOtherInsuranceProcessItem(item))
  const hasNormalInsurance = selectedItems.some((item) => !isOtherInsuranceProcessItem(item))
  let nextIds = selectedIds

  if (hasOtherInsurance && hasNormalInsurance) {
    const previousItems = previousProcessItemIds.value.map((id) => getProcessItemById(id)).filter(Boolean)
    const previousHadOtherInsurance = previousItems.some((item) => isOtherInsuranceProcessItem(item))
    nextIds = selectedItems
      .filter((item) => previousHadOtherInsurance ? !isOtherInsuranceProcessItem(item) : isOtherInsuranceProcessItem(item))
      .map((item) => Number(item.id))
  }

  processForm.value.item_ids = nextIds

  if (nextIds.some((id) => isOtherInsuranceProcessItem(getProcessItemById(id)))) {
    processForm.value.result = 'success'
    fileList.value = []
  }

  previousProcessItemIds.value = [...nextIds]
  buildProcessOtherInsuranceAmountRows()
}

const getDefaultProcessItemIdsFromChange = (change) => {
  const items = Array.isArray(change?.change_items)
    ? change.change_items.filter((item) => item?.id && ['pending', 'submitted'].includes(item.status))
    : []
  const normalItems = items.filter((item) => !isOtherInsuranceProcessItem(item))
  const defaultItems = normalItems.length > 0 ? normalItems : []

  return defaultItems.map((item) => Number(item.id))
}

const currentProcessAttachments = computed(() => {
  if (!currentChange.value) {
    return []
  }

  return (currentChange.value.attachments || []).filter((attachment) => !attachment.insurance_change_item_id)
})

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

  if (!ensureDetailRegionSelected()) {
    details.value = []
    return
  }

  const params = {
    account_set_id: currentAccountSetId.value,
    ...detailFilterForm.value,
    insurance_category: detailActiveTab.value === 'housing' ? 'housing_fund' : 'social_insurance'
  }

  if (detailProjectId.value) {
    params.project_id = detailProjectId.value
  }

  if (!detailTabNeedsRegionFilter()) {
    delete params.region_name
  }

  detailLoading.value = true
  try {
    const response = await getInsuranceChangeDetails(params)
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

const resetSocialDetailEditForm = () => {
  socialDetailEditForm.value = {
    detail_id: null,
    source: 'current',
    month: '',
    project_id: null,
    employee_name: '',
    original_social_security_base: 0,
    original_medical_insurance_base: 0,
    social_security_base: 0,
    medical_insurance_base: 0,
    reason: ''
  }
  nextTick(() => {
    socialDetailEditFormRef.value?.clearValidate()
    socialDetailEditReasonFormRef.value?.clearValidate()
  })
}

const formatEditBase = (value) => {
  if (value === null || value === undefined || value === '') return '-'
  const number = Number(value)
  return Number.isFinite(number) ? number.toFixed(2) : '-'
}

const openSocialDetailEdit = (row) => {
  if (!row?.can_edit_social_detail) {
    ElMessage.warning('该月份社保汇总已审批完成，不能再修改')
    return
  }

  const socialBase = Number(row.social_security_base || 0)
  const medicalBase = Number(row.medical_base || 0)
  socialDetailEditForm.value = {
    detail_id: row.detail_id,
    source: row.detail_source || 'current',
    month: row.detail_month || detailFilterForm.value.month || getCurrentMonth(),
    project_id: row.detail_project_id || detailProjectId.value || null,
    employee_name: row.employee_name || '',
    original_social_security_base: socialBase,
    original_medical_insurance_base: medicalBase,
    social_security_base: socialBase,
    medical_insurance_base: medicalBase,
    reason: ''
  }
  showSocialDetailEditDialog.value = true
  nextTick(() => socialDetailEditFormRef.value?.clearValidate())
}

const cancelSocialDetailEdit = () => {
  showSocialDetailEditDialog.value = false
  showSocialDetailEditReasonDialog.value = false
  resetSocialDetailEditForm()
}

const continueSocialDetailEdit = async () => {
  if (!socialDetailEditFormRef.value) return

  try {
    await socialDetailEditFormRef.value.validate()
  } catch {
    return
  }

  showSocialDetailEditDialog.value = false
  showSocialDetailEditReasonDialog.value = true
  nextTick(() => socialDetailEditReasonFormRef.value?.clearValidate())
}

const backToSocialDetailEdit = () => {
  showSocialDetailEditReasonDialog.value = false
  showSocialDetailEditDialog.value = true
}

const submitSocialDetailEditForm = async () => {
  if (!socialDetailEditReasonFormRef.value || socialDetailEditLoading.value) {
    return
  }

  try {
    await socialDetailEditReasonFormRef.value.validate()
  } catch {
    return
  }

  if (!currentAccountSetId.value) {
    ElMessage.warning('请先选择账套')
    return
  }

  socialDetailEditLoading.value = true
  try {
    const response = await submitSocialDetailEditRequest({
      account_set_id: currentAccountSetId.value,
      detail_id: socialDetailEditForm.value.detail_id,
      source: socialDetailEditForm.value.source,
      month: socialDetailEditForm.value.month,
      project_id: socialDetailEditForm.value.project_id,
      social_security_base: socialDetailEditForm.value.social_security_base,
      medical_insurance_base: socialDetailEditForm.value.medical_insurance_base,
      reason: socialDetailEditForm.value.reason.trim()
    })

    if (!response.success) {
      throw new Error(response.message || '提交审批失败')
    }

    ElMessage.success(response.message || '已提交审批，审批通过后生效')
    showSocialDetailEditReasonDialog.value = false
    resetSocialDetailEditForm()
    await loadDetails()
  } catch (error) {
    ElMessage.error(error.response?.data?.message || error.message || '提交审批失败')
  } finally {
    socialDetailEditLoading.value = false
  }
}

// 加载社保补差明细
const loadCompensationDetails = async () => {
  if (!currentAccountSetId.value) {
    ElMessage.warning('请先选择账套')
    return
  }

  if (!ensureDetailRegionSelected()) {
    rawCompensationData.value = []
    return
  }
  
  detailLoading.value = true
  try {
    const response = await getSocialSecurityCompensationList({
      account_set_id: currentAccountSetId.value,
      month: detailFilterForm.value.month,  // ✅ 添加月份筛选
      region_name: detailFilterForm.value.region_name
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

  if (!ensureDetailRegionSelected()) {
    rawHousingFundCompensationData.value = []
    return
  }
  
  detailLoading.value = true
  try {
    const response = await getHousingFundCompensationList({
      account_set_id: currentAccountSetId.value,
      month: detailFilterForm.value.month,  // ✅ 添加月份筛选
      region_name: detailFilterForm.value.region_name
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

const hasProcessableItems = (change) => {
  return Array.isArray(change?.change_items) && change.change_items.some((item) => ['pending', 'submitted'].includes(item.status))
}

const refreshCurrentChangeData = async (changeId) => {
  const detailResponse = await request.get(`/insurance-changes/${changeId}?t=${Date.now()}`)
  if (detailResponse.success) {
    currentChange.value = detailResponse.data
    return detailResponse.data
  }
  return null
}

const getProcessableItemIdsFromChange = (change) => {
  if (!Array.isArray(change?.change_items)) {
    return []
  }

  return change.change_items
    .filter((item) => item?.id && ['pending', 'submitted'].includes(item.status))
    .map((item) => Number(item.id))
}

const showProcessDialog = async (change) => {
  fileList.value = []
  processOtherInsuranceAmountRows.value = []

  try {
    const latestChange = await refreshCurrentChangeData(change.id)
    currentChange.value = latestChange || change
  } catch (error) {
    currentChange.value = change
  }

  const defaultItemIds = getDefaultProcessItemIdsFromChange(currentChange.value)
  processForm.value = {
    item_ids: defaultItemIds,
    result: 'success'
  }
  previousProcessItemIds.value = [...defaultItemIds]
  buildProcessOtherInsuranceAmountRows()

  showUploadDialogFlag.value = true
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

const saveProcessOtherInsuranceAmounts = async () => {
  if (!currentChange.value?.id || !isOtherInsuranceProcessSelected.value) {
    return
  }

  if (processOtherInsuranceAmountRows.value.length === 0) {
    ElMessage.warning('请填写商业保险金额')
    throw new Error('请填写商业保险金额')
  }

  const url = currentChange.value.change_type === 'decrease'
    ? `/insurance-changes/${currentChange.value.id}/update-surrender-amount`
    : `/insurance-changes/${currentChange.value.id}/update-per-capita-cost`
  const amountField = currentChange.value.change_type === 'decrease'
    ? 'surrender_amount'
    : 'employee_per_capita_cost'

  for (const row of processOtherInsuranceAmountRows.value) {
    if (!row.policy_id) {
      throw new Error(`无法获取"${row.name || '商业保险'}"的保单ID`)
    }

    const amount = Number(row.amount)
    if (Number.isNaN(amount) || amount < 0) {
      throw new Error(`请正确填写"${row.name || '商业保险'}"的${processOtherInsuranceAmountLabel.value}`)
    }

    const response = await request({
      url,
      method: 'post',
      data: {
        insurance_id: row.policy_id,
        [amountField]: amount
      }
    })

    if (!response.success) {
      throw new Error(response.message || '商业保险金额保存失败')
    }

    if (response.data) {
      currentChange.value = response.data
    }
  }
}

const submitProcess = async () => {
  if (!currentChange.value?.id) {
    return
  }

  if (!processForm.value.item_ids || processForm.value.item_ids.length === 0) {
    ElMessage.warning('请选择处理业务')
    return
  }

  if (processing.value) {
    return
  }

  processing.value = true
  processingChangeId.value = currentChange.value.id

  try {
    const isOtherInsuranceProcess = isOtherInsuranceProcessSelected.value
    if (isOtherInsuranceProcess) {
      processForm.value.result = 'success'
      await saveProcessOtherInsuranceAmounts()
    }

    if (!isOtherInsuranceProcess && ['failed', 'terminated'].includes(processForm.value.result) && fileList.value.length > 0) {
      const formData = new FormData()
      fileList.value.forEach((file) => {
        formData.append('attachments[]', file.raw)
      })

      const uploadResponse = await request.post(
        `/insurance-changes/${currentChange.value.id}/upload-attachment`,
        formData
      )

      if (!uploadResponse.success) {
        throw new Error(uploadResponse.message || '上传附件失败')
      }

      currentChange.value = uploadResponse.data?.change || currentChange.value
      fileList.value = []
    }

    if (!isOtherInsuranceProcess && ['failed', 'terminated'].includes(processForm.value.result) && currentProcessAttachments.value.length === 0) {
      ElMessage.warning(processForm.value.result === 'terminated' ? '终结时必须上传处理附件' : '失败时必须上传处理附件')
      return
    }

    const response = await request.put(`/insurance-changes/${currentChange.value.id}/confirm-process`, {
      item_ids: processForm.value.item_ids,
      result: isOtherInsuranceProcess ? 'success' : processForm.value.result
    })

    if (!response.success) {
      throw new Error(response.message || '处理失败')
    }

    ElMessage.success(response.message || '处理成功')

    await loadChanges()
    if (activeTab.value === 'details') {
      await loadDetails()
    }
    if (activeTab.value === 'summaries') {
      await loadSummaries()
    }

    const refreshed = await refreshCurrentChangeData(currentChange.value.id).catch(() => null)
    if (refreshed) {
      const listChange = changes.value.find((item) => item.id === refreshed.id)
      if (listChange) {
        Object.assign(listChange, refreshed)
      }
    }

    showUploadDialogFlag.value = false
  } catch (error) {
    console.error('业务处理失败:', error)
    ElMessage.error(error.response?.data?.message || error.message || '业务处理失败')
  } finally {
    processing.value = false
    processingChangeId.value = null
  }
}

// 商业保险确认处理（只处理商业保险）
const confirmOtherInsuranceOnly = async (change) => {
  // 防止重复点击
  if (processingOtherInsurance.value) {
    return
  }
  
  try {
    await ElMessageBox.confirm(
      `确定要处理"${change.employee.name}"的商业保险吗？\n\n处理后将：\n1. 只更新商业保险明细\n2. 不影响社保、医保、公积金等其他数据\n3. 确认处理按钮和上传文件功能不受影响`,
      '商业保险确认处理',
      {
        confirmButtonText: '确定处理',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    processingOtherInsurance.value = true
    
    // 调用后端API只处理商业保险
    const response = await request.put(`/insurance-changes/${change.id}/confirm-other-insurance-only`)
    
    if (response.success) {
      ElMessage.success('商业保险已处理完成')
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
      console.error('商业保险确认处理失败:', error)
      ElMessage.error(error.response?.data?.message || error.message || '处理失败')
    }
  } finally {
    processingOtherInsurance.value = false
  }
}

// 查看详情
const viewDetails = async (change) => {
  detailScopedCategory.value = resolveDetailCategory(change)
  console.log('=== 查看详情 ===')
  console.log('change对象:', change)
  
  try {
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
      console.log('=== 商业保险数据调试 ===')
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
  } catch (error) {
    console.error('获取详情失败:', error)
    // 如果API调用失败，使用列表中的数据作为备选
    currentChange.value = change
    console.warn('API调用失败，使用列表数据')
    console.warn('列表数据中的大额医疗保险状态:', change.large_medical_insurance_enabled)
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
  selectedProjectName.value = ''
  loadChanges()
}

const resetDetailFilter = () => {
  detailProjectId.value = null
  detailFilterForm.value = {
    month: getCurrentMonth(), // 重置为当前月份
    region_name: getDefaultDetailRegion()
  }
  loadActiveDetailData()
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
    'completed': '成功',
    'failed': '失败',
    'terminated': '终结',
    'processing': '成功', // 兼容旧状态
    'approved': '成功',   // 兼容旧状态
    'finished': '成功'    // 兼容旧状态
  }
  return statusMap[status] || '待处理' // 默认显示为待处理而不是未知
}

// 获取状态标签类型
const getStatusTagType = (status) => {
  const typeMap = {
    'pending': 'warning',
    'submitted': 'primary',  // 已上传附件，待确认处理
    'completed': 'success',
    'failed': 'danger',
    'terminated': 'info',
    'processing': 'success', // 兼容旧状态
    'approved': 'success',   // 兼容旧状态
    'finished': 'success'    // 兼容旧状态
  }
  return typeMap[status] || 'warning' // 默认显示为待处理样式
}

const isSuccessStatus = (status) => {
  return ['completed', 'processing', 'approved', 'finished'].includes(status)
}

const isFailedStatus = (status) => {
  return status === 'failed'
}

const isTerminatedStatus = (status) => {
  return status === 'terminated'
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

const normalizeOtherInsuranceTypeValue = (value) => {
  if (value === null || value === undefined) return ''

  if (typeof value === 'string') {
    return value.trim()
  }

  if (typeof value === 'number') {
    return String(value)
  }

  if (typeof value === 'object') {
    return (value.name || value.type_name || value.insurance_type_name || value.label || value.title || value.value || '').toString().trim()
  }

  return ''
}

const resolveOtherInsuranceTypeName = (policy = {}) => {
  const candidates = [
    policy.type_name,
    policy.insurance_type_name,
    policy.insurance_type_text,
    policy.policy_type_name,
    policy.type,
    policy.insurance_type,
    policy.policy_type
  ]

  for (const candidate of candidates) {
    const name = normalizeOtherInsuranceTypeValue(candidate)
    if (name && name !== '[object Object]') {
      return name
    }
  }

  return '商业保险'
}

// 获取商业保险详情
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
      type: resolveOtherInsuranceTypeName({
        insurance_type_name: currentChange.value.insurance_type_name,
        type_name: currentChange.value.type_name,
        type: currentChange.value.insurance_type
      }),
      coverage: currentChange.value.coverage,
      employee_per_capita_cost: currentChange.value.employee_per_capita_cost
    }]
  }
  
  // 使用快照数据中的商业保险配置
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
      console.log('=== 商业保险详情数据 ===')
      console.log('数组长度:', policies.length)
      
      // 映射字段名，确保前端模板能正确显示
      const mappedPolicies = policies.map((policy, index) => {
        console.log(`保单${index + 1}:`, policy)
        return {
          ...policy,
          // 映射字段名
          name: policy.name || policy.policy_name || policy.type_name || '未知保险',
          type: resolveOtherInsuranceTypeName(policy),
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
  
  console.log('没有找到商业保险数据')
  console.log('currentChange对象:', currentChange.value)
  console.log('other_insurance_policies字段:', currentChange.value.other_insurance_policies)
  return []
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
    'large_medical_insurance': '大额医疗',
    'other_insurance': '商业保险'
  }
  return categoryMap[category] || category
}

// 检查是否有员工基数信息
const hasEmployeeBaseInfo = () => {
  if (!currentChange.value) return false

  if (detailScopedCategory.value === 'social_security') {
    return !!currentChange.value.employee_social_security_base
  }
  if (detailScopedCategory.value === 'medical_insurance') {
    return !!currentChange.value.employee_medical_insurance_base
  }
  if (detailScopedCategory.value === 'housing_fund') {
    return !!currentChange.value.employee_housing_fund_base
  }
  if (detailScopedCategory.value === 'large_medical_insurance') {
    return !!(currentChange.value.employee_large_medical_base || currentChange.value.employee_large_medical_company_base)
  }

  return currentChange.value.employee_social_security_base ||
         currentChange.value.employee_medical_insurance_base ||
         currentChange.value.employee_housing_fund_base ||
         currentChange.value.employee_large_medical_base
}

// 检查是否有参保地区信息
const hasRegionInfo = () => {
  if (!currentChange.value || !currentChange.value.employee) return false

  if (detailScopedCategory.value === 'social_security') {
    return !!currentChange.value.employee.social_security_region
  }
  if (detailScopedCategory.value === 'medical_insurance') {
    return !!currentChange.value.employee.medical_insurance_region
  }
  if (detailScopedCategory.value === 'housing_fund') {
    return !!currentChange.value.employee.housing_fund_region
  }
  if (detailScopedCategory.value === 'large_medical_insurance') {
    return !!currentChange.value.employee.large_medical_insurance_config_relation
  }

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


// 处理商业保险费用变更
const handleOtherInsuranceCostChange = (row) => {
  // 标记为正在编辑
  row._editing = true
}

// 保存商业保险费用 - 已禁用
const saveOtherInsuranceCost = async (row) => {
  // 功能已禁用，不再执行任何操作
  console.log('保存商业保险费用功能已禁用')
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
        insurance_id: row.id || row.policy_id,
        employee_per_capita_cost: row.employee_per_capita_cost
      }
    })

    if (response.success) {
      if (response.data) {
        currentChange.value = response.data
      }
      ElMessage.success('费用保存成功')
    } else {
      ElMessage.error(response.message || '费用保存失败')
    }
  } catch (error) {
    console.error('保存费用失败:', error)
    ElMessage.error('费用保存失败')
  }
}

const canEditOtherInsuranceSurrenderAmount = () => {
  return currentChange.value
    && currentChange.value.change_type === 'decrease'
    && ['pending', 'submitted'].includes(currentChange.value.status)
}

// 保存减少参保时的商业保险退保金额
const saveSurrenderAmount = async (row) => {
  if (!currentChange.value) {
    return
  }

  try {
    const response = await request({
      url: `/insurance-changes/${currentChange.value.id}/update-surrender-amount`,
      method: 'post',
      data: {
        insurance_id: row.id || row.policy_id,
        surrender_amount: row.surrender_amount
      }
    })

    if (response.success) {
      if (response.data) {
        currentChange.value = response.data
      }
      ElMessage.success('退保金额保存成功')
    } else {
      ElMessage.error(response.message || '退保金额保存失败')
    }
  } catch (error) {
    console.error('保存退保金额失败:', error)
    ElMessage.error(error.response?.data?.message || '退保金额保存失败')
  }
}

// 使用名额
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

const updateChangeTableMaxHeight = () => {
  nextTick(() => {
    if (activeTab.value !== 'changes') return

    const tableElement = changeTableRef.value?.$el || changeTableRef.value
    if (!tableElement?.getBoundingClientRect) return

    const tableTop = tableElement.getBoundingClientRect().top
    const availableHeight = document.documentElement.clientHeight - tableTop - 80
    changeTableMaxHeight.value = Math.max(180, Math.floor(availableHeight))
  })
}

// 监听标签页切换
const handleTabChange = (tab) => {
  if (tab === 'changes') {
    updateChangeTableMaxHeight()
  } else if (tab === 'details') {
    loadActiveDetailData()
  } else if (tab === 'summaries') {
    loadSummaries()
  }
}

// 监听选项卡切换
watch(detailActiveTab, (newTab) => {
  if (activeTab.value === 'details') {
    loadActiveDetailData()
  }
})

// 监听月份筛选变化，自动触发查询
watch(() => detailFilterForm.value.month, (newMonth, oldMonth) => {
  // 确保月份发生了变化，且不是初始化时的变化
  if (newMonth !== oldMonth && oldMonth !== undefined) {
    // 根据当前激活的标签页触发相应的查询
    if (activeTab.value === 'details') {
      loadActiveDetailData()
    } else if (activeTab.value === 'summaries') {
      loadSummaries()
    }
  }
})

watch(showDetailDialogFlag, (visible) => {
  if (!visible) {
    detailScopedCategory.value = ''
  }
})

watch(projectOptions, (options) => {
  if (selectedProjectName.value && !options.includes(selectedProjectName.value)) {
    selectedProjectName.value = ''
  }
})

watch(changeStatusTab, () => {
  selectedTasks.value = []
  updateChangeTableMaxHeight()
})

watch(() => filteredChanges.value.length, updateChangeTableMaxHeight)

const loadSummaryPreviewConfigurations = async () => {
  await loadSocialSecurityRegions()
  await loadMedicalInsuranceRegions()
  await loadLargeMedicalConfigs()
}

const openSummaryPreview = async ({ category, month, regionName, projectId }) => {
  if (!['social_insurance', 'housing_fund'].includes(category) || !month || !regionName || !projectId) {
    ElMessage.warning('汇总预览参数不完整')
    return
  }

  showSummaryPreviewDialog.value = false
  showHousingFundSummaryPreviewDialog.value = false
  details.value = []
  await loadSummaryPreviewConfigurations()

  detailActiveTab.value = category === 'housing_fund' ? 'housing' : 'social'
  detailFilterForm.value = {
    month,
    region_name: regionName
  }
  detailProjectId.value = projectId
  activeTab.value = 'details'

  await loadDetails()
  await nextTick()

  if (category === 'housing_fund') {
    generateHousingFundSummaryPreview()
  } else {
    await generateSummaryTable()
  }
}

defineExpose({ openSummaryPreview })

onMounted(async () => {
  if (props.previewOnly) {
    return
  }

  window.addEventListener('resize', updateChangeTableMaxHeight)
  // 先加载各类地区配置
  await loadSummaryPreviewConfigurations()
  // 再加载参保人员列表
  await loadChanges()
  updateChangeTableMaxHeight()
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', updateChangeTableMaxHeight)
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

.insurance-change-container.preview-only-host {
  padding: 0;
}

.insurance-change-container.preview-only-host > .page-header,
.insurance-change-container.preview-only-host > .tabs-container {
  display: none;
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
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.summary-count {
  font-size: 14px;
  font-weight: 500;
}

.pending-count {
  color: #e6a23c;
}

.success-count {
  color: #67c23a;
}

.terminated-count {
  color: #909399;
}

.failed-count {
  color: #f56c6c;
}

.category-status-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  font-size: 18px;
  font-weight: 700;
  line-height: 1;
}

.category-status-icon.success {
  color: #67c23a;
}

.category-status-icon.failed {
  color: #f56c6c;
}

.category-status-icon.terminated {
  color: #e6a23c;
}

.other-insurance-detail-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.other-insurance-detail-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  min-height: 24px;
}

.other-insurance-detail-name {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
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

.detail-table-title {
  margin-bottom: 12px;
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
  vertical-align: middle;
}

.detail-table .el-table__header .cell {
  text-align: center !important;
  white-space: normal;
  line-height: 1.35;
}

.insurance-column-header {
  display: flex;
  min-height: 34px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  line-height: 1.25;
  text-align: center;
}

.insurance-column-ratio {
  color: #909399;
  font-size: 11px;
  font-weight: 400;
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

:deep(.detail-table .detail-summary-row > td) {
  background-color: #fff7e6 !important;
  color: #7a4f01;
  font-weight: 600;
}

:deep(.detail-table .detail-total-row > td) {
  background-color: #e8f3ff !important;
  color: #1d4ed8;
  font-weight: 700;
}

.housing-summary-preview-frame {
  width: 100%;
  height: 70vh;
  border: 0;
  background: #fff;
}

.summary-preview-wrapper {
  width: 100%;
  overflow: auto;
  max-height: 70vh;
}

.summary-preview-table {
  width: 100%;
  min-width: 1100px;
  border-collapse: collapse;
  font-size: 13px;
  color: #303133;
}

.summary-preview-table th,
.summary-preview-table td {
  border: 1px solid #dcdfe6;
  padding: 8px 10px;
  text-align: center;
  vertical-align: middle;
  white-space: nowrap;
}

.summary-preview-table th {
  background-color: #f5f7fa;
  font-weight: 600;
}

.summary-preview-table .main-title {
  background-color: #e8f5e9;
  font-size: 18px;
  font-weight: 700;
}

.summary-preview-table .sub-title {
  background-color: #f0f9eb;
  text-align: left;
  font-weight: 600;
}

.summary-preview-table .subtotal-row td {
  background-color: #fff7e6;
  color: #7a4f01;
  font-weight: 600;
}

.summary-preview-table .total-row td {
  background-color: #ffe082;
  color: #7a4f01;
  font-weight: 700;
}

.summary-preview-table .retention-row td {
  background-color: #e8f3ff;
  color: #1d4ed8;
  font-weight: 600;
}

.summary-retention-table :deep(.el-input__wrapper) {
  box-shadow: none;
  padding: 0 4px;
}

.summary-retention-table :deep(.el-input__inner) {
  text-align: center;
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
