<template>
  <div class="invoice-applications-container">
    <el-card>
      <template #header>
        <div class="card-header">
          <span class="title">发票申请管理</span>
          <!-- 创建按钮 - 只有后续审批节点人员可见 -->
          <el-button v-if="canCreateTask" type="primary" @click="handleCreate">
            <el-icon><Plus /></el-icon>
            创建开票任务
          </el-button>
        </div>
      </template>

      <!-- 搜索栏 -->
      <el-form :inline="true" class="search-form">
        <el-form-item label="年份">
          <el-select v-model="searchForm.year" placeholder="请选择年份" clearable style="width: 120px">
            <el-option
              v-for="year in years"
              :key="year"
              :label="`${year}年`"
              :value="year"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="月份">
          <el-select v-model="searchForm.month" placeholder="请选择月份" clearable style="width: 120px">
            <el-option
              v-for="month in 12"
              :key="month"
              :label="`${month}月`"
              :value="month"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="业务状态">
          <el-select v-model="searchForm.status" placeholder="请选择状态" clearable style="width: 140px">
            <el-option label="正常" value="normal" />
            <el-option label="红冲" value="red_flushed" />
          </el-select>
        </el-form-item>
        <el-form-item label="审批状态">
          <el-select v-model="searchForm.approval_status" placeholder="请选择审批状态" clearable style="width: 150px">
            <el-option label="审批中" value="pending" />
            <el-option label="已通过" value="approved" />
            <el-option label="已驳回" value="rejected" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
          <el-button type="success" @click="handleExport" :loading="exporting">
            <el-icon><Download /></el-icon>
            导出Excel
          </el-button>
        </el-form-item>
      </el-form>

      <!-- 表格 -->
      <el-table
        :data="tableData"
        v-loading="loading"
        border
        style="width: 100%"
      >
        <el-table-column type="index" label="序号" width="60" align="center" />
        <el-table-column prop="project_name" label="项目" min-width="180">
          <template #default="{ row }">
            {{ row.project_name || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="company_name" label="开票单位" min-width="220">
          <template #default="{ row }">
            {{ row.company_name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="开票项目名称" min-width="220">
          <template #default="{ row }">
            {{ formatContentProjectNames(row.content_items || row.contentItems) }}
          </template>
        </el-table-column>
        <el-table-column prop="invoice_amount" label="开票金额" width="120" align="right">
          <template #default="{ row }">
            ¥{{ Number(row.invoice_amount || 0).toFixed(2) }}
          </template>
        </el-table-column>
        <el-table-column prop="amount_excluding_tax" label="不含税金额" width="130" align="right">
          <template #default="{ row }">
            ¥{{ Number(row.amount_excluding_tax || 0).toFixed(2) }}
          </template>
        </el-table-column>
        <el-table-column prop="tax_amount" label="税金" width="120" align="right">
          <template #default="{ row }">
            ¥{{ Number(row.tax_amount || 0).toFixed(2) }}
          </template>
        </el-table-column>
        <el-table-column prop="status_text" label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)">
              {{ row.status_text }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="审批状态" width="120" align="center">
          <template #default="{ row }">
            <el-tag 
              v-if="row.approval_status" 
              :type="getApprovalStatusType(row.approval_status)"
            >
              {{ getApprovalStatusText(row.approval_status) }}
            </el-tag>
            <span v-else class="text-muted">-</span>
          </template>
        </el-table-column>
        <el-table-column prop="submitter.name" label="提交人" width="120" />
        <el-table-column prop="submitted_at" label="提交时间" width="180">
          <template #default="{ row }">
            {{ formatDateTime(row.submitted_at) }}
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="180">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="280" fixed="right" align="center">
          <template #default="{ row }">
            <el-button type="primary" link @click="handleDetail(row)">查看</el-button>
            <el-button
              v-if="row.can_fill_invoice && (!row.approval_status || row.approval_status === 'rejected')"
              type="success"
              link
              @click="handleOpenInvoiceFill(row)"
            >
              填写发票信息
            </el-button>
            <el-button 
              v-if="row.can_fill_invoice && (!row.approval_status || row.approval_status === 'rejected')" 
              type="primary" 
              link 
              @click="handleEdit(row)"
            >
              编辑
            </el-button>
            <el-button
              v-if="row.can_fill_invoice && (!row.approval_status || row.approval_status === 'rejected')"
              type="success"
              link
              @click="handleSubmitFromList(row)"
            >
              提交
            </el-button>
            <el-button 
              v-if="canDeleteInvoice && !row.approval_status && row.status === 'normal'" 
              type="danger" 
              link 
              @click="handleDelete(row)"
            >
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <el-pagination
        v-model:current-page="pagination.current"
        v-model:page-size="pagination.pageSize"
        :total="pagination.total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="handleSearch"
        @current-change="handleSearch"
        style="margin-top: 20px; justify-content: flex-end"
      />
    </el-card>

    <!-- 创建开票任务对话框 -->
    <el-dialog
      v-model="createDialogVisible"
      title="创建开票任务"
      width="960px"
      top="12vh"
      @close="handleCreateDialogClose"
    >
      <el-form
        ref="createFormRef"
        :model="createForm"
        :rules="createFormRules"
        label-width="120px"
        style="max-height: 58vh; overflow-y: auto; padding-right: 6px"
      >
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="业务类型" prop="status">
              <el-select
                v-model="createForm.status"
                placeholder="请选择业务类型"
                style="width: 100%"
                @change="handleCreateStatusChange"
              >
                <el-option label="正常" value="normal" />
                <el-option label="红冲" value="red_flushed" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col v-if="isCreateRedFlushed" :span="12">
            <el-form-item label="红冲发票" prop="red_flush_source_id">
              <el-select
                v-model="createForm.red_flush_source_id"
                placeholder="请选择需要红冲的历史发票"
                clearable
                filterable
                :loading="redFlushCandidateLoading"
                style="width: 100%"
                @change="handleCreateRedFlushSourceChange"
              >
                <el-option
                  v-for="item in redFlushCandidates"
                  :key="item.id"
                  :label="formatCreateRedFlushCandidateLabel(item)"
                  :value="item.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="24">
            <el-form-item label="项目" prop="project_id">
              <el-select
                v-model="createForm.project_id"
                placeholder="请选择项目"
                clearable
                filterable
                style="width: 100%"
                @change="handleCreateProjectChange"
              >
                <el-option
                  v-for="item in projectOptions"
                  :key="item.id"
                  :label="item.name"
                  :value="item.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

        <el-row v-if="createForm.project_id" :gutter="20">
          <el-col v-if="selectedProjectInvoiceInfos.length > 1" :span="24">
            <el-form-item label="开票信息">
              <el-select
                v-model="createForm.invoice_info_index"
                placeholder="请选择要导入的开票信息"
                style="width: 100%"
                @change="handleCreateInvoiceInfoChange"
              >
                <el-option
                  v-for="(item, index) in selectedProjectInvoiceInfos"
                  :key="`${createForm.project_id}-invoice-info-${index}`"
                  :label="formatInvoiceInfoLabel(item, index)"
                  :value="index"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="开票单位">
              <el-input :model-value="selectedProjectInvoiceInfo.company_name" disabled />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="企业税号">
              <el-input :model-value="selectedProjectInvoiceInfo.tax_number" disabled />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="企业地址">
              <el-input :model-value="selectedProjectInvoiceInfo.company_address" disabled />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="企业电话">
              <el-input :model-value="selectedProjectInvoiceInfo.company_phone" disabled />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="开户银行">
              <el-input :model-value="selectedProjectInvoiceInfo.bank_name" disabled />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="银行账户">
              <el-input :model-value="selectedProjectInvoiceInfo.bank_account" disabled />
            </el-form-item>
          </el-col>
          <el-col :span="24">
            <el-form-item label="行号">
              <el-input :model-value="selectedProjectInvoiceInfo.bank_code" disabled />
            </el-form-item>
          </el-col>
        </el-row>

        <el-divider content-position="left">开票内容明细</el-divider>
        <div class="section-header">
          <span>开票内容明细项</span>
          <el-button
            type="primary"
            link
            :disabled="createForm.invoice_method !== 'none'"
            @click="addCreateContentItem"
          >
            增行
          </el-button>
        </div>
        <el-table
          :data="createContentItems"
          border
          size="small"
          class="invoice-content-items-table"
          style="margin-top: 10px"
        >
          <el-table-column type="index" label="序号" width="70" align="center" />
          <el-table-column label="项目名称" min-width="220">
            <template #default="{ row }">
              <el-select
                v-model="row.invoice_content_config_id"
                placeholder="请选择配置项目"
                clearable
                filterable
                style="width: 100%"
                @change="value => handleInvoiceContentConfigSelect(row, value)"
              >
                <el-option
                  v-for="item in invoiceContentConfigs"
                  :key="item.id"
                  :label="item.project_name"
                  :value="item.id"
                />
              </el-select>
            </template>
          </el-table-column>
          <el-table-column label="开票金额" width="150">
            <template #default="{ row }">
              <el-input-number
                v-model="row.invoice_amount"
                :precision="2"
                :min="0"
                :controls="false"
                style="width: 100%"
              />
            </template>
          </el-table-column>
          <el-table-column label="税率" width="140">
            <template #default="{ row }">
              <el-input :model-value="formatInvoiceItemRate(row.tax_rate)" disabled />
            </template>
          </el-table-column>
          <el-table-column label="扣除额" width="150">
            <template #default="{ row }">
              <el-input-number
                v-model="row.deduction_amount"
                :precision="2"
                :min="0"
                :controls="false"
                :disabled="createForm.invoice_method !== 'none'"
                style="width: 100%"
              />
            </template>
          </el-table-column>
          <el-table-column label="不含税金额" width="150">
            <template #default="{ row }">
              <el-input-number
                v-model="row.amount_excluding_tax"
                :precision="2"
                :min="0"
                :controls="false"
                disabled
                style="width: 100%"
              />
            </template>
          </el-table-column>
          <el-table-column label="税金" width="150">
            <template #default="{ row }">
              <el-input-number
                v-model="row.tax_amount"
                :precision="2"
                :min="0"
                :controls="false"
                disabled
                style="width: 100%"
              />
            </template>
          </el-table-column>
          <el-table-column label="操作" width="100" align="center" fixed="right">
            <template #default="{ $index }">
              <el-button
                type="danger"
                link
                @click="removeCreateContentItem($index)"
                :disabled="createContentItems.length <= 1 || createForm.invoice_method !== 'none'"
              >
                删除
              </el-button>
            </template>
          </el-table-column>
        </el-table>

        <el-divider content-position="left">开票详情</el-divider>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="所属期-年份" prop="period_year">
              <el-select v-model="createForm.period_year" placeholder="请选择年份" style="width: 100%">
                <el-option
                  v-for="year in years"
                  :key="'period-year-' + year"
                  :label="`${year}年`"
                  :value="year"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="所属期-月份" prop="period_month">
              <el-select v-model="createForm.period_month" placeholder="请选择月份" style="width: 100%">
                <el-option
                  v-for="month in 12"
                  :key="'period-month-' + month"
                  :label="`${month}月`"
                  :value="month"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="单位名称" prop="company_name">
              <el-input v-model="createForm.company_name" placeholder="选择项目后自动带出" disabled />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="申请日期" prop="application_date">
              <el-date-picker
                v-model="createForm.application_date"
                type="date"
                placeholder="选择日期"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="开票方式" prop="invoice_method">
              <el-select v-model="createForm.invoice_method" placeholder="请选择开票方式" style="width: 100%">
                <el-option label="差额征税-全额开票" value="full" />
                <el-option label="差额征税-差额开票" value="diff" />
                <el-option label="无" value="none" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="开票种类" prop="invoice_type">
              <el-select v-model="createForm.invoice_type" placeholder="请选择开票种类" style="width: 100%">
                <el-option
                  v-for="option in invoiceTypeOptions"
                  :key="'create-invoice-type-' + option.value"
                  :label="option.label"
                  :value="option.value"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="开票日期" prop="invoice_date">
              <el-date-picker
                v-model="createForm.invoice_date"
                type="date"
                placeholder="选择日期"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="最早开票日期">
              <el-date-picker
                v-model="createForm.earliest_invoice_date"
                type="date"
                placeholder="选填，限制最早开票时间"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
          <el-col :span="24">
            <el-form-item label="开票备注">
              <el-input
                v-model="createForm.invoice_remark"
                type="textarea"
                :rows="2"
                placeholder="请输入开票备注（非必填）"
              />
            </el-form-item>
          </el-col>
        </el-row>

        <div v-if="createNeedsDeductionAmount" ref="createDeductionSectionRef">
          <el-divider content-position="left">扣除明细</el-divider>
          <div class="section-header">
            <span>扣除明细项</span>
            <el-button type="primary" link @click="addCreateItem">添加明细</el-button>
          </div>
          <el-table :data="createItems" border size="small" style="margin-top: 10px">
            <el-table-column type="index" label="序号" width="70" align="center" />
            <el-table-column label="项目" min-width="220">
              <template #default="{ row }">
                <el-autocomplete
                  v-model="row.item_name"
                  :fetch-suggestions="querySearchInvoiceProjects"
                  placeholder="请输入项目"
                  clearable
                  style="width: 100%"
                  @input="value => handleInvoiceProjectInput(row, value)"
                  @select="item => handleInvoiceProjectSelect(row, item)"
                />
              </template>
            </el-table-column>
            <el-table-column label="金额" width="150">
              <template #default="{ row }">
                <el-input-number
                  v-model="row.amount"
                  :precision="2"
                  :min="0"
                  :controls="false"
                  style="width: 100%"
                />
              </template>
            </el-table-column>
            <el-table-column label="备注" min-width="240">
              <template #default="{ row }">
                <el-input v-model="row.remark" placeholder="请输入备注（可选）" />
              </template>
            </el-table-column>
            <el-table-column label="操作" width="120" align="center">
              <template #default="{ $index }">
                <el-button
                  type="danger"
                  link
                  @click="removeCreateItem($index)"
                  :disabled="createItems.length <= 1"
                >
                  删除
                </el-button>
              </template>
            </el-table-column>
          </el-table>
        </div>

      </el-form>

      <template #footer>
        <el-button @click="createDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleConfirmCreate" :loading="creating">
          确定
        </el-button>
      </template>
    </el-dialog>

    <!-- 编辑/查看详情对话框 -->
    <el-dialog
      v-model="detailDialogVisible"
      :title="detailDialogTitle"
      width="1200px"
      @close="handleDetailDialogClose"
    >
      <el-tabs v-model="activeTab">
        <!-- 开票详情 -->
        <el-tab-pane label="开票详情" name="invoice_details">
          <div class="invoice-details-section">
            <el-form ref="invoiceDetailsFormRef" :model="invoiceDetailsForm" :rules="invoiceDetailsFormRules" label-width="120px" :disabled="!canEdit">
              <el-row :gutter="20">
                <!-- 所属期-年份 -->
                <el-col :span="12">
                  <el-form-item label="所属期-年份" prop="period_year">
                    <el-select v-model="invoiceDetailsForm.period_year" placeholder="请选择年份" style="width: 100%" disabled>
                      <el-option
                        v-for="year in years"
                        :key="year"
                        :label="year + '年'"
                        :value="year"
                      />
                    </el-select>
                  </el-form-item>
                </el-col>

                <!-- 所属期-月份 -->
                <el-col :span="12">
                  <el-form-item label="所属期-月份" prop="period_month">
                    <el-select v-model="invoiceDetailsForm.period_month" placeholder="请选择月份" style="width: 100%" disabled>
                      <el-option
                        v-for="month in 12"
                        :key="month"
                        :label="month + '月'"
                        :value="month"
                      />
                    </el-select>
                  </el-form-item>
                </el-col>

                <!-- 单位名称 -->
                <el-col :span="12">
                  <el-form-item label="单位名称" prop="company_name">
                    <el-input v-model="invoiceDetailsForm.company_name" placeholder="请输入单位名称" disabled />
                  </el-form-item>
                </el-col>

                <!-- 申请日期 -->
                <el-col :span="12">
                  <el-form-item label="申请日期" prop="application_date">
                    <el-date-picker
                      v-model="invoiceDetailsForm.application_date"
                      type="date"
                      placeholder="选择日期"
                      format="YYYY-MM-DD"
                      value-format="YYYY-MM-DD"
                      style="width: 100%"
                      disabled
                    />
                  </el-form-item>
                </el-col>

                <!-- 开票方式 -->
                <el-col :span="12">
                  <el-form-item label="开票方式" prop="invoice_method">
                    <el-select
                      v-model="invoiceDetailsForm.invoice_method"
                      placeholder="请选择"
                      style="width: 100%"
                      disabled
                    >
                      <el-option label="差额征税-全额开票" value="full" />
                      <el-option label="差额征税-差额开票" value="diff" />
                      <el-option label="无" value="none" />
                    </el-select>
                  </el-form-item>
                </el-col>

                <!-- 开票种类 -->
                <el-col :span="12">
                  <el-form-item label="开票种类" prop="invoice_type">
                    <el-select v-model="invoiceDetailsForm.invoice_type" placeholder="请选择开票种类" style="width: 100%" disabled>
                      <el-option
                        v-for="option in invoiceTypeOptions"
                        :key="'detail-invoice-type-' + option.value"
                        :label="option.label"
                        :value="option.value"
                      />
                    </el-select>
                  </el-form-item>
                </el-col>

                <!-- 开票金额 -->
                <el-col :span="12">
                  <el-form-item label="开票金额" prop="invoice_amount">
                    <el-input-number
                      v-model="invoiceDetailsForm.invoice_amount"
                      :precision="2"
                      :min="0"
                      :controls="false"
                      style="width: 100%"
                      disabled
                    />
                  </el-form-item>
                </el-col>

                <!-- 税率 -->
                <el-col :span="12">
                  <el-form-item label="税率" prop="tax_rate">
                    <el-select
                      v-model="invoiceDetailsForm.tax_rate"
                      placeholder="请选择税率"
                      style="width: 100%"
                      disabled
                    >
                      <el-option label="1%" :value="0.01" />
                      <el-option label="2%" :value="0.02" />
                      <el-option label="3%" :value="0.03" />
                      <el-option label="4%" :value="0.04" />
                      <el-option label="5%" :value="0.05" />
                      <el-option label="6%" :value="0.06" />
                      <el-option label="7%" :value="0.07" />
                      <el-option label="8%" :value="0.08" />
                      <el-option label="9%" :value="0.09" />
                      <el-option label="10%" :value="0.10" />
                      <el-option label="11%" :value="0.11" />
                      <el-option label="12%" :value="0.12" />
                      <el-option label="13%" :value="0.13" />
                    </el-select>
                  </el-form-item>
                </el-col>

                <!-- 扣除额 -->
                <el-col :span="12">
                  <el-form-item label="扣除额" prop="deduction_amount">
                  <el-input-number
                    v-model="invoiceDetailsForm.deduction_amount"
                    :precision="2"
                    :min="0"
                    :controls="false"
                    :disabled="invoiceDetailsForm.invoice_method !== 'none'"
                    style="width: 100%"
                  />
                </el-form-item>
              </el-col>

                <!-- 不含税金额 -->
                <el-col :span="12">
                  <el-form-item label="不含税金额" prop="amount_excluding_tax">
                    <el-input-number
                      v-model="invoiceDetailsForm.amount_excluding_tax"
                      :precision="2"
                      :min="0"
                      :controls="false"
                      disabled
                      style="width: 100%"
                    />
                  </el-form-item>
                </el-col>

                <!-- 税金 -->
                <el-col :span="12">
                  <el-form-item label="税金" prop="tax_amount">
                    <el-input-number
                      v-model="invoiceDetailsForm.tax_amount"
                      :precision="2"
                      :min="0"
                      :controls="false"
                      disabled
                      style="width: 100%"
                    />
                  </el-form-item>
                </el-col>

                <!-- 开票日期 -->
                <el-col :span="12">
                  <el-form-item label="开票日期" prop="invoice_date">
                    <el-date-picker
                      v-model="invoiceDetailsForm.invoice_date"
                      type="date"
                      placeholder="选择日期"
                      format="YYYY-MM-DD"
                      value-format="YYYY-MM-DD"
                      style="width: 100%"
                      disabled
                    />
                  </el-form-item>
                </el-col>

                <!-- 开票人 -->
              <el-col :span="12">
                <el-form-item label="开票人">
                    <el-input v-model="invoiceDetailsForm.invoicer" placeholder="保存发票号码后自动带出当前账号" disabled />
                  </el-form-item>
                </el-col>

                <!-- 发票号码 -->
                <el-col :span="12">
                  <el-form-item label="发票号码" prop="invoice_number">
                    <el-input v-model="invoiceDetailsForm.invoice_number" placeholder="请输入发票号码" />
                  </el-form-item>
                </el-col>

                <!-- 最早开票日期 -->
                <el-col :span="12">
                  <el-form-item label="最早开票日期" prop="earliest_invoice_date">
                    <el-date-picker
                      v-model="invoiceDetailsForm.earliest_invoice_date"
                      type="date"
                      placeholder="选填，限制最早开票时间"
                      value-format="YYYY-MM-DD"
                      style="width: 100%"
                      disabled
                    />
                  </el-form-item>
                </el-col>

                <!-- 是否完成 -->
                <el-col :span="12">
                  <el-form-item label="是否完成">
                    <el-input
                      :model-value="invoiceDetailsForm.is_completed ? '已完成' : '提交审批后自动更新'"
                      disabled
                    />
                  </el-form-item>
                </el-col>

                <!-- 备注 -->
                <el-col :span="24">
                  <el-form-item label="备注">
                    <el-input
                      v-model="invoiceDetailsForm.invoice_remark"
                      type="textarea"
                      :rows="3"
                      placeholder="请输入备注信息"
                      disabled
                    />
                  </el-form-item>
                </el-col>
              </el-row>

              <el-row v-if="canEdit">
                <el-col :span="24" style="text-align: right">
                  <el-button type="primary" @click="handleSaveInvoiceDetails">保存发票号码</el-button>
                  <el-button type="success" @click="handleSubmit">提交审批</el-button>
                </el-col>
              </el-row>
            </el-form>
          </div>
        </el-tab-pane>

        <!-- 开票内容明细 -->
        <el-tab-pane label="开票内容明细" name="content_items">
          <div class="invoice-content-items-section">
            <div class="section-header">
              <span>开票内容明细项</span>
            </div>
            <el-table
              :data="currentApplication.content_items || []"
              border
              class="invoice-content-items-table"
              style="margin-top: 10px"
            >
              <el-table-column prop="sequence" label="序号" width="70" align="center" />
              <el-table-column prop="project_name" label="项目名称" width="180" show-overflow-tooltip />
              <el-table-column prop="invoice_amount" label="开票金额" width="130" align="right">
                <template #default="{ row }">
                  ¥{{ Number(row.invoice_amount || 0).toFixed(2) }}
                </template>
              </el-table-column>
              <el-table-column prop="tax_rate" label="税率" width="100" align="center">
                <template #default="{ row }">
                  {{ formatInvoiceItemRate(row.tax_rate) }}
                </template>
              </el-table-column>
              <el-table-column prop="deduction_amount" label="扣除额" width="130" align="right">
                <template #default="{ row }">
                  ¥{{ Number(row.deduction_amount || 0).toFixed(2) }}
                </template>
              </el-table-column>
              <el-table-column prop="amount_excluding_tax" label="不含税金额" width="130" align="right">
                <template #default="{ row }">
                  ¥{{ Number(row.amount_excluding_tax || 0).toFixed(2) }}
                </template>
              </el-table-column>
              <el-table-column prop="tax_amount" label="税金" width="130" align="right">
                <template #default="{ row }">
                  ¥{{ Number(row.tax_amount || 0).toFixed(2) }}
                </template>
              </el-table-column>
            </el-table>
          </div>
        </el-tab-pane>

        <!-- 明细项 -->
        <el-tab-pane label="扣除明细" name="items">
          <div class="items-section">
            <!-- 基本信息 -->
            <el-descriptions :column="2" border style="margin-bottom: 20px">
              <el-descriptions-item label="申请单号">
                {{ currentApplication.application_no }}
              </el-descriptions-item>
              <el-descriptions-item label="期间">
                {{ currentApplication.year }}-{{ String(currentApplication.month).padStart(2, '0') }}
              </el-descriptions-item>
          <el-descriptions-item label="商品名称/项目" :span="2">
                <span>{{ currentApplication.project_name || '-' }}</span>
              </el-descriptions-item>
              <el-descriptions-item label="状态">
                <el-tag :type="getStatusType(currentApplication.status)">
                  {{ currentApplication.status_text }}
                </el-tag>
              </el-descriptions-item>
              <el-descriptions-item label="总金额">
                ¥{{ Number(currentApplication.total_amount || 0).toFixed(2) }}
              </el-descriptions-item>
            </el-descriptions>

            <div class="section-header">
              <span>扣除明细项</span>
              <el-button
                v-if="canEdit"
                type="primary"
                size="small"
                @click="handleAddItem"
                :disabled="currentApplication.invoice_method !== 'full' && currentApplication.invoice_method !== 'diff'"
              >
                <el-icon><Plus /></el-icon>
                添加明细
              </el-button>
            </div>

            <el-table :data="currentApplication.items" border style="margin-top: 10px">
              <el-table-column prop="sequence" label="序号" width="70" align="center" />
              <el-table-column prop="project_name" label="模板项目" width="150" />
              <el-table-column prop="item_name" label="项目名称" width="160" />
              <el-table-column prop="amount" label="金额" width="130" align="right">
                <template #default="{ row }">
                  ¥{{ Number(row.amount).toFixed(2) }}
                </template>
              </el-table-column>
              <el-table-column prop="remark" label="备注" min-width="200" show-overflow-tooltip />
              <el-table-column v-if="canEdit" label="操作" width="150" align="center">
                <template #default="{ row }">
                  <el-button type="primary" link @click="handleEditItem(row)">编辑</el-button>
                  <el-button
                    type="danger"
                    link
                    @click="handleDeleteItem(row)"
                    :disabled="currentApplication.invoice_method !== 'none' && currentApplication.items.length <= 1"
                  >
                    删除
                  </el-button>
                </template>
              </el-table-column>
            </el-table>

            <div class="total-amount">
              合计金额：<span>¥{{ totalAmount.toFixed(2) }}</span>
            </div>

          </div>
        </el-tab-pane>

        <!-- 附件 -->
        <el-tab-pane label="文件上传" name="attachments">
          <div class="attachments-section">
            <div class="section-header">
              <span>附件列表（必须上传）</span>
              <el-upload
                v-if="canEdit"
                :http-request="handleUploadRequest"
                :on-success="handleUploadSuccess"
                :on-error="handleUploadError"
                :show-file-list="false"
                :before-upload="beforeUpload"
              >
                <el-button type="primary" size="small">
                  <el-icon><Upload /></el-icon>
                  上传附件
                </el-button>
              </el-upload>
            </div>

            <el-table :data="currentApplication.attachments" border style="margin-top: 10px">
              <el-table-column type="index" label="序号" width="70" align="center" />
              <el-table-column prop="filename" label="文件名" min-width="250" />
              <el-table-column prop="size" label="大小" width="120">
                <template #default="{ row }">
                  {{ formatFileSize(row.size) }}
                </template>
              </el-table-column>
              <el-table-column prop="uploaded_at" label="上传时间" width="180">
                <template #default="{ row }">
                  {{ formatDateTime(row.uploaded_at) }}
                </template>
              </el-table-column>
              <el-table-column label="操作" width="150" align="center">
                <template #default="{ row }">
                  <el-button type="primary" link @click="handleDownload(row)">下载</el-button>
                  <el-button 
                    v-if="canEdit" 
                    type="danger" 
                    link 
                    @click="handleDeleteAttachment(row)"
                  >
                    删除
                  </el-button>
                </template>
              </el-table-column>
            </el-table>
          </div>
        </el-tab-pane>

        <!-- 审批信息 - 已隐藏 -->
        <!-- <el-tab-pane label="审批信息" name="approval" v-if="currentApplication.approval_instance">
          <div class="approval-section">
            <el-steps :active="getApprovalStep()" align-center>
              <el-step 
                v-for="(approval, index) in currentApplication.approval_instance.approvals" 
                :key="index"
                :title="approval.node.name"
                :description="getApprovalDescription(approval)"
                :status="getApprovalStatus(approval)"
              />
            </el-steps>
          </div>
        </el-tab-pane> -->
      </el-tabs>

      <template #footer>
        <el-button @click="detailDialogVisible = false">关闭</el-button>
        <el-button
          v-if="currentApplication.can_fill_invoice && (!currentApplication.approval_status || currentApplication.approval_status === 'rejected')"
          type="success"
          @click="handleSubmit"
        >
          提交审批
        </el-button>
      </template>
    </el-dialog>

    <!-- 填写发票号码对话框 -->
    <el-dialog
      v-model="invoiceNumberDialogVisible"
      title="填写发票号码"
      width="520px"
      @close="handleInvoiceNumberDialogClose"
    >
      <el-form
        ref="invoiceNumberFormRef"
        :model="invoiceNumberForm"
        :rules="invoiceNumberFormRules"
        label-width="90px"
      >
        <el-form-item label="发票号码" prop="invoice_number">
          <el-input
            v-model="invoiceNumberForm.invoice_number"
            placeholder="请输入发票号码"
            clearable
          />
        </el-form-item>
        <el-form-item label="发票附件">
          <el-upload
            v-model:file-list="invoiceNumberFileList"
            :auto-upload="false"
            :before-upload="beforeUpload"
            multiple
          >
            <el-button type="primary">
              <el-icon><Upload /></el-icon>
              上传发票
            </el-button>
            <template #tip>
              <div class="el-upload__tip">支持上传发票附件，单个文件不超过 10MB</div>
            </template>
          </el-upload>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="invoiceNumberDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleConfirmInvoiceNumber" :loading="invoiceNumberSubmitting">
          确定并提交
        </el-button>
      </template>
    </el-dialog>

    <!-- 添加/编辑明细项对话框 -->
    <el-dialog
      v-model="itemDialogVisible"
      :title="itemDialogTitle"
      width="600px"
      @close="handleItemDialogClose"
      >
        <el-form
          ref="itemFormRef"
          :model="itemForm"
          :rules="itemFormRules"
        label-width="100px"
      >
        <el-form-item label="项目">
          <el-autocomplete
            v-model="itemForm.item_name"
            :fetch-suggestions="querySearchInvoiceProjects"
            placeholder="请输入项目"
            style="width: 100%"
            clearable
            @input="handleProjectInput"
            @select="handleProjectSelect"
          />
        </el-form-item>
        <el-form-item label="金额" prop="amount">
          <el-input-number
            v-model="itemForm.amount"
            :precision="2"
            :step="100"
            :min="0"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="备注">
          <el-input
            v-model="itemForm.remark"
            type="textarea"
            :rows="3"
            placeholder="请输入备注"
            maxlength="500"
          />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="itemDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleItemSubmit" :loading="itemSubmitting">
          确定
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Upload, Download } from '@element-plus/icons-vue'
import {
  getInvoiceApplications,
  getInvoiceApplicationDetail,
  createInvoiceApplication,
  getRedFlushCandidates,
  deleteInvoiceApplication,
  addInvoiceItem,
  updateInvoiceItem,
  deleteInvoiceItem,
  generateExcel,
  uploadAttachment,
  deleteAttachment,
  submitInvoiceApplication
} from '@/api/invoiceApplication'
import { getAllInvoiceProjects } from '@/api/invoiceProject'
import { getAllInvoiceContentConfigs } from '@/api/invoiceContentConfig'
import { getProjects } from '@/api/projects'
import request from '@/api/request'
import { useAccountSetStore } from '@/stores/accountSet'
import { usePermissionStore } from '@/stores/permission'
import * as XLSX from 'xlsx'

// 账套store
const route = useRoute()
const router = useRouter()
const accountSetStore = useAccountSetStore()
const permissionStore = usePermissionStore()

// 权限控制
const canCreateInvoice = computed(() => permissionStore.hasPermission('invoice_applications.create'))
const canEditInvoice = computed(() => permissionStore.hasPermission('invoice_applications.update'))
const canDeleteInvoice = computed(() => permissionStore.hasPermission('invoice_applications.delete'))
const canApproveInvoice = computed(() => permissionStore.hasPermission('invoice_applications.approve'))

// 权限控制：是否可以创建任务
const canCreateTask = computed(() => {
  return !!accountSetStore.currentAccountSetId && canCreateInvoice.value
})

const invoiceTypeOptions = [
  { label: '普通发票', value: '普通发票' },
  { label: '增值税专用发票', value: '增值税专用发票' }
]

// 年份列表
const currentYear = new Date().getFullYear()
const currentMonth = new Date().getMonth() + 1
const years = ref([])
for (let i = currentYear - 5; i <= currentYear + 1; i++) {
  years.value.push(i)
}

// 搜索表单（默认选择当前年月）
const searchForm = reactive({
  year: currentYear,
  month: currentMonth,
  status: null,
  approval_status: null
})

// 表格数据
const tableData = ref([])
const loading = ref(false)
const exporting = ref(false)

// 分页
const pagination = reactive({
  current: 1,
  pageSize: 15,
  total: 0
})

// 创建对话框
const createDialogVisible = ref(false)
const createFormRef = ref(null)
const creating = ref(false)
const createForm = reactive({
  task_name: '',
  year: currentYear,
  month: currentMonth,
  status: 'normal',
  red_flush_source_id: null,
  project_id: null,
  invoice_info_index: 0,
  project_name: '',
  period_year: currentYear,
  period_month: currentMonth,
  company_name: '',
  application_date: '',
  invoice_method: null,
  invoice_type: '普通发票',
  deduction_amount: 0,
  tax_rate: null,
  amount_excluding_tax: 0,
  invoice_tax_amount: 0,
  invoice_amount: 0,
  tax_amount: 0,
  invoice_date: '',
  earliest_invoice_date: '',
  is_completed: false,
  invoicer: '',
  invoice_number: '',
  invoice_remark: ''
})

const createFormRules = {
  status: [{ required: true, message: '请选择业务类型', trigger: 'change' }],
  red_flush_source_id: [{
    validator: (_rule, value, callback) => {
      if (createForm.status === 'red_flushed' && !value) {
        callback(new Error('请选择需要红冲的历史发票'))
        return
      }
      callback()
    },
    trigger: 'change'
  }],
  project_id: [{
    validator: (_rule, value, callback) => {
      if (!value && !String(createForm.project_name || '').trim()) {
        callback(new Error('请选择项目'))
        return
      }
      callback()
    },
    trigger: 'change'
  }],
  period_year: [{ required: true, message: '请选择年份', trigger: 'change' }],
  period_month: [{ required: true, message: '请选择月份', trigger: 'change' }],
  application_date: [{ required: true, message: '请选择申请日期', trigger: 'change' }],
  invoice_method: [{ required: true, message: '请选择开票方式', trigger: 'change' }],
  invoice_type: [{ required: true, message: '请选择开票种类', trigger: 'change' }],
  invoice_date: [{ required: true, message: '请选择开票日期', trigger: 'change' }]
}

const createItems = ref([
  {
    invoice_project_id: null,
    item_name: '',
    amount: 0,
    remark: ''
  }
])
const invoiceContentConfigs = ref([])
const projectOptions = ref([])
const createContentItems = ref([])
const createDeductionSectionRef = ref(null)
const lastCreateDuplicateWarnKey = ref('')
const redFlushCandidates = ref([])
const redFlushCandidateLoading = ref(false)
const createProjectInvoiceInfoDefaults = () => ({
  company_name: '',
  tax_number: '',
  company_address: '',
  company_phone: '',
  bank_name: '',
  bank_account: '',
  bank_code: ''
})
const selectedProjectInvoiceInfo = reactive(createProjectInvoiceInfoDefaults())
const isCreateRedFlushed = computed(() => createForm.status === 'red_flushed')

const hasProjectInvoiceInfoValue = (invoiceInfo = {}) => {
  return [
    invoiceInfo.remark,
    invoiceInfo.company_name,
    invoiceInfo.tax_number,
    invoiceInfo.company_address,
    invoiceInfo.company_phone,
    invoiceInfo.bank_name,
    invoiceInfo.bank_account,
    invoiceInfo.bank_code
  ].some(value => String(value || '').trim())
}

const normalizeProjectInvoiceInfo = (invoiceInfo = {}) => ({
  remark: String(invoiceInfo.remark || '').trim(),
  company_name: String(invoiceInfo.company_name || '').trim(),
  tax_number: String(invoiceInfo.tax_number || '').trim(),
  company_address: String(invoiceInfo.company_address || '').trim(),
  company_phone: String(invoiceInfo.company_phone || '').trim(),
  bank_name: String(invoiceInfo.bank_name || '').trim(),
  bank_account: String(invoiceInfo.bank_account || '').trim(),
  bank_code: String(invoiceInfo.bank_code || '').trim()
})

const getProjectInvoiceInfos = (project) => {
  if (!project) return []

  const invoiceInfos = Array.isArray(project.invoice_infos)
    ? project.invoice_infos.map(item => normalizeProjectInvoiceInfo(item)).filter(item => hasProjectInvoiceInfoValue(item))
    : []

  return invoiceInfos.length ? invoiceInfos : [normalizeProjectInvoiceInfo()]
}

const selectedProjectInvoiceInfos = computed(() => {
  const project = projectOptions.value.find(item => Number(item.id) === Number(createForm.project_id)) || null
  return getProjectInvoiceInfos(project)
})

const formatInvoiceInfoLabel = (invoiceInfo, index) => {
  const remark = String(invoiceInfo?.remark || '').trim() || `开票信息${index + 1}`
  const companyName = String(invoiceInfo?.company_name || '').trim() || '未填写企业名称'
  return `${remark} | ${companyName}`
}

const buildInvoiceContentItemFromConfig = (config = null) => {
  return {
    invoice_content_config_id: config?.id || null,
    project_name: config?.project_name || '',
    invoice_amount: 0,
    tax_rate: Number(config?.tax_rate || 0),
    deduction_amount: 0,
    invoice_tax_amount: 0,
    amount_excluding_tax: 0,
    tax_amount: 0
  }
}

const buildCreateContentItemFromSource = (item = {}) => {
  return {
    invoice_content_config_id: item.invoice_content_config_id || item.config?.id || null,
    project_name: item.project_name || '',
    invoice_amount: Number(item.invoice_amount || 0),
    tax_rate: Number(item.tax_rate || 0),
    deduction_amount: Number(item.deduction_amount || 0),
    invoice_tax_amount: Number(item.invoice_tax_amount || 0),
    amount_excluding_tax: Number(item.amount_excluding_tax || 0),
    tax_amount: Number(item.tax_amount || 0)
  }
}

const resetCreateContentItems = () => {
  createContentItems.value = [buildInvoiceContentItemFromConfig()]
}

const addCreateContentItem = () => {
  createContentItems.value.push(buildInvoiceContentItemFromConfig())
}

const removeCreateContentItem = (index) => {
  if (createContentItems.value.length <= 1) return
  createContentItems.value.splice(index, 1)
}

const handleInvoiceContentConfigSelect = (targetItem, configId) => {
  const config = invoiceContentConfigs.value.find(item => item.id === configId)
  Object.assign(targetItem, buildInvoiceContentItemFromConfig(config || null))
}

const hasCreateContentItemValue = (item) => {
  if (!item) return false
  return Boolean(
    item.invoice_content_config_id ||
    String(item.project_name || '').trim() ||
    Number(item.invoice_amount || 0) > 0 ||
    Number(item.tax_rate || 0) > 0 ||
    Number(item.deduction_amount || 0) > 0 ||
    Number(item.invoice_tax_amount || 0) > 0 ||
    Number(item.amount_excluding_tax || 0) > 0 ||
    Number(item.tax_amount || 0) > 0
  )
}

const normalizeCreateContentItems = () => {
  return createContentItems.value
    .filter(item => hasCreateContentItemValue(item))
    .map(item => ({
      invoice_content_config_id: item.invoice_content_config_id || null,
      project_name: String(item.project_name || '').trim(),
      invoice_amount: Number(item.invoice_amount || 0),
      tax_rate: Number(item.tax_rate || 0),
      deduction_amount: Number(item.deduction_amount || 0),
      invoice_tax_amount: Number(item.invoice_tax_amount || 0),
      amount_excluding_tax: Number(item.amount_excluding_tax || 0),
      tax_amount: Number(item.tax_amount || 0)
    }))
}

const sumCreateContentItems = (items = []) => {
  const total = items.reduce((result, item) => {
    result.invoice_amount += Number(item.invoice_amount || 0)
    result.deduction_amount += Number(item.deduction_amount || 0)
    result.invoice_tax_amount += Number(item.invoice_tax_amount || 0)
    result.amount_excluding_tax += Number(item.amount_excluding_tax || 0)
    result.tax_amount += Number(item.tax_amount || 0)
    return result
  }, {
    invoice_amount: 0,
    tax_rate: 0,
    deduction_amount: 0,
    invoice_tax_amount: 0,
    amount_excluding_tax: 0,
    tax_amount: 0
  })

  total.tax_rate = items.length ? Number(items[0].tax_rate || 0) : 0

  return {
    invoice_amount: roundAmount(total.invoice_amount),
    tax_rate: total.tax_rate,
    deduction_amount: roundAmount(total.deduction_amount),
    invoice_tax_amount: roundAmount(total.invoice_tax_amount),
    amount_excluding_tax: roundAmount(total.amount_excluding_tax),
    tax_amount: roundAmount(total.tax_amount)
  }
}

const addCreateItem = () => {
  createItems.value.push(buildInvoiceItemFromProject(null))
}

const removeCreateItem = (index) => {
  if (createItems.value.length <= 1) return
  createItems.value.splice(index, 1)
}

const validateCreateExtraData = () => {
  if (createNeedsDeductionAmount.value && normalizeCreateContentItems().length > 1) {
    ElMessage.warning('差额或全额开票时，开票内容明细只支持 1 行')
    return false
  }

  const invalidContentIndex = createContentItems.value.findIndex(item => {
    return hasCreateContentItemValue(item) && !String(item.project_name || '').trim()
  })

  if (invalidContentIndex !== -1) {
    ElMessage.warning('请先选择开票内容明细第 ' + (invalidContentIndex + 1) + ' 行的项目名称')
    return false
  }

  if (createNeedsDeductionAmount.value) {
    syncCreateItemAmountsFromRemark()

    if (!createItems.value.length) {
      ElMessage.warning('\u8bf7\u81f3\u5c11\u6dfb\u52a01\u6761\u6263\u9664\u660e\u7ec6')
      return false
    }

    const invalidIndex = createItems.value.findIndex(item => {
      return !String(item.item_name || '').trim() || Number(item.amount) <= 0
    })

    if (invalidIndex !== -1) {
      ElMessage.warning('\u8bf7\u5b8c\u6210\u6263\u9664\u660e\u7ec6\u7b2c ' + (invalidIndex + 1) + ' \u884c\u7684\u9879\u76ee\u540d\u79f0\u548c\u91d1\u989d')
      return false
    }

    if (sumDeductionItems(createItems.value) > Number(createForm.invoice_amount || 0)) {
      ElMessage.warning('扣除额不能大于开票金额')
      return false
    }
  }

  return true
}

const detailDialogVisible = ref(false)
const detailDialogTitle = ref('')
const activeTab = ref('invoice_details')
const currentApplication = ref({})
const isEditMode = ref(false)
const submitting = ref(false)

// 填写发票号码对话框
const invoiceNumberDialogVisible = ref(false)
const invoiceNumberFormRef = ref(null)
const invoiceNumberSubmitting = ref(false)
const invoiceNumberForm = reactive({
  id: null,
  application_no: '',
  invoice_number: ''
})
const invoiceNumberFormRules = {
  invoice_number: [{ required: true, message: '请输入发票号码', trigger: 'blur' }]
}
const invoiceNumberFileList = ref([])

// 明细项对话框
const itemDialogVisible = ref(false)
const itemDialogTitle = ref('')
const itemFormRef = ref(null)
const itemSubmitting = ref(false)
const isEditItem = ref(false)
const itemForm = reactive({
  id: null,
  invoice_project_id: null,
  item_name: '',
  amount: 0,
  remark: ''
})

const itemFormRules = {
  item_name: [{ required: true, message: '请输入项目名称', trigger: 'blur' }],
  amount: [{ required: true, message: '请输入金额', trigger: 'blur' }]
}

// 发票项目列表
const invoiceProjects = ref([])

// 开票详情表单
const invoiceDetailsForm = reactive({
  period_year: null,
  period_month: null,
  company_name: '',
  application_date: null,
  invoice_method: null,
  invoice_type: '普通发票',
  deduction_amount: 0,
  tax_rate: 0,
  amount_excluding_tax: 0,
  invoice_tax_amount: 0,
  invoice_amount: 0,
  tax_amount: 0,
  invoice_date: null,
  earliest_invoice_date: null,
  is_completed: false,
  invoicer: '',
  invoice_number: '',
  invoice_remark: ''
})

// 开票详情表单验证规则
const invoiceDetailsFormRules = {
  invoice_number: [{ required: true, message: '请输入发票号码', trigger: 'blur' }]
}

// 开票详情表单ref
const invoiceDetailsFormRef = ref(null)

// 计算属性
const canEdit = computed(() => {
  return isEditMode.value && 
         currentApplication.value.can_fill_invoice &&
         ['draft', 'normal', 'red_flushed'].includes(currentApplication.value.status)
})

// 判断是否需要填写扣除额（只有全额和差额需要）
const needsDeductionAmount = computed(() => {
  const method = invoiceDetailsForm.invoice_method
  return method === 'full' || method === 'diff'
})

const createNeedsDeductionAmount = computed(() => {
  const method = createForm.invoice_method
  return method === 'full' || method === 'diff'
})

const totalAmount = computed(() => {
  if (!currentApplication.value.items) return 0
  return currentApplication.value.items.reduce((sum, item) => sum + Number(item.amount), 0)
})

const resetCreateItems = () => {
  createItems.value = [buildInvoiceItemFromProject(null)]
}

const roundAmount = (value) => {
  return Math.round((Number(value || 0) + Number.EPSILON) * 100) / 100
}

const parseAmountFromText = (value) => {
  const text = String(value ?? '').trim()
  if (!text) return null

  const normalized = text.replace(/[,\s￥¥]/g, '')
  if (!/^\d+(\.\d+)?$/.test(normalized)) return null

  const amount = Number(normalized)
  return Number.isFinite(amount) && amount > 0 ? roundAmount(amount) : null
}

const shouldUseRemarkAsAmount = (item = {}) => {
  return Number(item.amount || 0) <= 0 && parseAmountFromText(item.remark) !== null
}

const getInvoiceItemAmount = (item = {}) => {
  const amount = Number(item.amount ?? 0)
  if (Number.isFinite(amount) && amount > 0) {
    return roundAmount(amount)
  }

  return parseAmountFromText(item.remark) ?? 0
}

const getInvoiceItemRemark = (item = {}) => {
  return shouldUseRemarkAsAmount(item) ? '' : (item.remark || '')
}

const syncCreateItemAmountsFromRemark = () => {
  let changed = false
  const nextItems = createItems.value.map(item => {
    if (!shouldUseRemarkAsAmount(item)) {
      return item
    }

    changed = true
    return {
      ...item,
      amount: getInvoiceItemAmount(item),
      remark: ''
    }
  })

  if (changed) {
    createItems.value = nextItems
  }
}

const formatInvoiceItemRate = (value) => {
  return `${roundAmount(Number(value || 0) * 100)}%`
}

const resetSelectedProjectInvoiceInfo = () => {
  Object.assign(selectedProjectInvoiceInfo, createProjectInvoiceInfoDefaults())
}

const getProjectInvoiceInfo = (project, index = 0) => {
  const invoiceInfos = getProjectInvoiceInfos(project)
  if (!invoiceInfos.length) {
    return createProjectInvoiceInfoDefaults()
  }

  const safeIndex = Math.min(Math.max(Number(index) || 0, 0), invoiceInfos.length - 1)
  return invoiceInfos[safeIndex] || createProjectInvoiceInfoDefaults()
}

const findProjectInvoiceInfoIndexByCompanyName = (invoiceInfos, companyName) => {
  const normalizedCompanyName = String(companyName || '').trim()
  if (!normalizedCompanyName) return -1

  return invoiceInfos.findIndex(item => String(item.company_name || '').trim() === normalizedCompanyName)
}

const applySelectedProjectInvoiceInfo = (project, preferredIndex = 0, preferredCompanyName = '') => {
  const invoiceInfos = getProjectInvoiceInfos(project)

  if (!invoiceInfos.length) {
    createForm.invoice_info_index = 0
    resetSelectedProjectInvoiceInfo()
    createForm.company_name = preferredCompanyName || ''
    return
  }

  let nextIndex = Math.max(0, Number(preferredIndex) || 0)
  const matchedIndex = findProjectInvoiceInfoIndexByCompanyName(invoiceInfos, preferredCompanyName)
  if (matchedIndex !== -1) {
    nextIndex = matchedIndex
  }
  nextIndex = Math.min(nextIndex, invoiceInfos.length - 1)

  createForm.invoice_info_index = nextIndex
  Object.assign(selectedProjectInvoiceInfo, getProjectInvoiceInfo(project, nextIndex))
  createForm.company_name = selectedProjectInvoiceInfo.company_name || preferredCompanyName || ''
}

const syncCreateProjectByName = (projectName, fallbackCompanyName = '') => {
  const normalizedProjectName = String(projectName || '').trim()
  const project = projectOptions.value.find(item => String(item.name || '').trim() === normalizedProjectName) || null

  if (!project) {
    createForm.project_id = null
    createForm.invoice_info_index = 0
    createForm.project_name = normalizedProjectName
    resetSelectedProjectInvoiceInfo()
    createForm.company_name = fallbackCompanyName || ''
    return
  }

  createForm.project_id = project.id
  createForm.project_name = project.name || normalizedProjectName
  applySelectedProjectInvoiceInfo(project, 0, fallbackCompanyName)
}

const handleCreateProjectChange = (projectId) => {
  const project = projectOptions.value.find(item => Number(item.id) === Number(projectId)) || null

  if (!project) {
    createForm.invoice_info_index = 0
    createForm.project_name = ''
    createForm.company_name = ''
    resetSelectedProjectInvoiceInfo()
    return
  }

  createForm.project_name = project.name || ''
  applySelectedProjectInvoiceInfo(project)

  if (!selectedProjectInvoiceInfo.company_name) {
    ElMessage.warning('所选项目未配置开票信息，请先到项目管理中维护')
  }
}

const handleCreateInvoiceInfoChange = (index) => {
  const project = projectOptions.value.find(item => Number(item.id) === Number(createForm.project_id)) || null
  if (!project) {
    return
  }

  applySelectedProjectInvoiceInfo(project, index)
}

const buildCreateItemFromSource = (item = {}) => {
  return {
    invoice_project_id: item.invoice_project_id || item.invoice_project?.id || null,
    item_name: item.item_name || '',
    amount: getInvoiceItemAmount(item),
    remark: getInvoiceItemRemark(item)
  }
}

const formatCreateRedFlushCandidateLabel = (item) => {
  const year = item?.year || '--'
  const month = item?.month ? String(item.month).padStart(2, '0') : '--'
  const companyName = item?.company_name || '未填写单位名称'
  const invoiceNumber = item?.invoice_number || '无发票号码'
  const amount = Number(item?.invoice_amount || 0).toFixed(2)
  return `${year}-${month} | ${companyName} | ${invoiceNumber} | ¥${amount}`
}

const loadRedFlushCandidates = async () => {
  if (!accountSetStore.currentAccountSetId) {
    redFlushCandidates.value = []
    return
  }

  redFlushCandidateLoading.value = true
  try {
    const response = await getRedFlushCandidates({
      current_account_set_id: accountSetStore.currentAccountSetId,
      before_year: createForm.year,
      before_month: createForm.month
    })

    if (response?.success) {
      redFlushCandidates.value = Array.isArray(response.data) ? response.data : []
      return
    }

    redFlushCandidates.value = []
  } catch (error) {
    console.error('加载红冲历史发票失败', error)
    ElMessage.error('加载红冲历史发票失败')
    redFlushCandidates.value = []
  } finally {
    redFlushCandidateLoading.value = false
  }
}

const applyCreateRedFlushSource = (source) => {
  if (!source) return

  syncCreateProjectByName(source.project_name, source.company_name || '')
  createForm.period_year = source.period_year || source.year || currentYear
  createForm.period_month = source.period_month || source.month || currentMonth
  createForm.application_date = source.application_date || getTodayDate()
  createForm.invoice_method = source.invoice_method || null
  createForm.invoice_type = source.invoice_type || '普通发票'
  createForm.deduction_amount = Number(source.deduction_amount || 0)
  createForm.tax_rate = Number(source.tax_rate || 0)
  createForm.amount_excluding_tax = Number(source.amount_excluding_tax || 0)
  createForm.invoice_tax_amount = Number(source.invoice_tax_amount || 0)
  createForm.invoice_amount = Number(source.invoice_amount || 0)
  createForm.tax_amount = Number(source.tax_amount || 0)
  createForm.invoice_date = source.invoice_date || getTodayDate()
  createForm.earliest_invoice_date = source.earliest_invoice_date || ''
  createForm.is_completed = false
  createForm.invoicer = ''
  createForm.invoice_number = ''
  createForm.invoice_remark = source.invoice_remark || ''

  const sourceContentItems = Array.isArray(source.content_items) ? source.content_items : []
  if (sourceContentItems.length) {
    createContentItems.value = sourceContentItems.map(item => buildCreateContentItemFromSource(item))
  } else {
    createContentItems.value = [{
      invoice_content_config_id: null,
      project_name: source.project_name || '',
      invoice_amount: Number(source.invoice_amount || 0),
      tax_rate: Number(source.tax_rate || 0),
      deduction_amount: Number(source.deduction_amount || 0),
      invoice_tax_amount: Number(source.invoice_tax_amount || 0),
      amount_excluding_tax: Number(source.amount_excluding_tax || 0),
      tax_amount: Number(source.tax_amount || 0)
    }]
  }

  const sourceItems = Array.isArray(source.items) ? source.items : []
  createItems.value = sourceItems.length
    ? sourceItems.map(item => buildCreateItemFromSource(item))
    : [buildInvoiceItemFromProject(null)]

  syncCreateCalculatedAmounts()
}

const handleCreateRedFlushSourceChange = (sourceId) => {
  const source = redFlushCandidates.value.find(item => Number(item.id) === Number(sourceId)) || null
  if (!source) {
    return
  }

  applyCreateRedFlushSource(source)
}

const handleCreateStatusChange = async (status) => {
  createForm.red_flush_source_id = null
  redFlushCandidates.value = []

  if (status === 'red_flushed') {
    await loadRedFlushCandidates()
  }
}

const buildInvoiceItemFromProject = (project) => {
  if (!project) {
    return {
      invoice_project_id: null,
      item_name: '',
      amount: 0,
      remark: ''
    }
  }

  return {
    invoice_project_id: project.id,
    item_name: project.project_name || '',
    amount: getInvoiceItemAmount(project),
    remark: getInvoiceItemRemark(project)
  }
}

const findInvoiceProjectByKeyword = (keyword) => {
  const normalizedKeyword = String(keyword || '').trim()
  if (!normalizedKeyword) return null
  return invoiceProjects.value.find(item => String(item.project_name || '').trim() === normalizedKeyword) || null
}

const querySearchInvoiceProjects = (queryString, cb) => {
  const keyword = String(queryString || '').trim().toLowerCase()
  const result = invoiceProjects.value
    .filter(item => {
      const name = String(item.project_name || '').trim().toLowerCase()
      return !keyword || name.includes(keyword)
    })
    .map(item => ({
      value: item.project_name || '',
      project: item
    }))
  cb(result)
}

const applyInvoiceProjectToItem = (targetItem, keyword) => {
  const project = findInvoiceProjectByKeyword(keyword)
  if (!targetItem) return

  if (!project) {
    targetItem.invoice_project_id = null
    targetItem.item_name = String(keyword || '').trim()
    return
  }

  const mapped = buildInvoiceItemFromProject(project)
  Object.assign(targetItem, mapped)
}

const handleInvoiceProjectInput = (targetItem, keyword) => {
  if (!targetItem) return
  targetItem.invoice_project_id = null
  targetItem.item_name = String(keyword || '')
}

const handleInvoiceProjectSelect = (targetItem, selected) => {
  const keyword = selected?.value || ''
  applyInvoiceProjectToItem(targetItem, keyword)
}

const calculateInvoiceDerivedAmounts = (invoiceAmount, deductionAmount, taxRate) => {
  const safeInvoiceAmount = roundAmount(Math.max(0, Number(invoiceAmount || 0)))
  const safeDeductionAmount = roundAmount(Math.max(0, Number(deductionAmount || 0)))
  const safeTaxRate = Math.max(0, Number(taxRate || 0))
  const taxableAmount = Math.max(0, safeInvoiceAmount - safeDeductionAmount)
  const invoiceTaxAmount = safeTaxRate > 0
    ? roundAmount(taxableAmount / (1 + safeTaxRate) * safeTaxRate)
    : 0
  const amountExcludingTax = roundAmount(Math.max(0, safeInvoiceAmount - invoiceTaxAmount))

  return {
    amountExcludingTax,
    invoiceTaxAmount,
    taxAmount: invoiceTaxAmount
  }
}

const syncCreateContentItemCalculatedAmounts = () => {
  createContentItems.value.forEach((item) => {
    const { amountExcludingTax, invoiceTaxAmount, taxAmount } = calculateInvoiceDerivedAmounts(
      item.invoice_amount,
      item.deduction_amount,
      item.tax_rate
    )

    if (Number(item.amount_excluding_tax || 0) !== amountExcludingTax) {
      item.amount_excluding_tax = amountExcludingTax
    }

    if (Number(item.invoice_tax_amount || 0) !== invoiceTaxAmount) {
      item.invoice_tax_amount = invoiceTaxAmount
    }

    if (Number(item.tax_amount || 0) !== taxAmount) {
      item.tax_amount = taxAmount
    }
  })
}

const sumDeductionItems = (items = []) => {
  return roundAmount(items.reduce((sum, item) => sum + Number(item?.amount || 0), 0))
}

const syncCreateCalculatedAmounts = () => {
  if (!createContentItems.value.length) {
    resetCreateContentItems()
  }

  if (createNeedsDeductionAmount.value && createContentItems.value.length > 1) {
    createContentItems.value = [createContentItems.value[0]]
  }

  if (createNeedsDeductionAmount.value && createContentItems.value.length) {
    createContentItems.value[0].deduction_amount = sumDeductionItems(createItems.value)
  }

  syncCreateContentItemCalculatedAmounts()

  const normalizedContentItems = normalizeCreateContentItems()
  const contentTotals = sumCreateContentItems(normalizedContentItems)

  createForm.deduction_amount = contentTotals.deduction_amount
  createForm.tax_rate = contentTotals.tax_rate
  createForm.invoice_amount = contentTotals.invoice_amount

  if (
    contentTotals.amount_excluding_tax > 0 ||
    contentTotals.invoice_tax_amount > 0 ||
    contentTotals.tax_amount > 0
  ) {
    createForm.amount_excluding_tax = contentTotals.amount_excluding_tax
    createForm.invoice_tax_amount = contentTotals.invoice_tax_amount
    createForm.tax_amount = contentTotals.tax_amount
    return
  }

  const { amountExcludingTax, invoiceTaxAmount, taxAmount } = calculateInvoiceDerivedAmounts(
    createForm.invoice_amount,
    createForm.deduction_amount,
    createForm.tax_rate
  )

  createForm.amount_excluding_tax = amountExcludingTax
  createForm.invoice_tax_amount = invoiceTaxAmount
  createForm.tax_amount = taxAmount
}

const syncInvoiceDetailsCalculatedAmounts = () => {
  invoiceDetailsForm.deduction_amount = needsDeductionAmount.value
    ? sumDeductionItems(currentApplication.value.items || [])
    : 0

  const { amountExcludingTax, invoiceTaxAmount, taxAmount } = calculateInvoiceDerivedAmounts(
    invoiceDetailsForm.invoice_amount,
    invoiceDetailsForm.deduction_amount,
    invoiceDetailsForm.tax_rate
  )

  invoiceDetailsForm.amount_excluding_tax = amountExcludingTax
  invoiceDetailsForm.invoice_tax_amount = invoiceTaxAmount
  invoiceDetailsForm.tax_amount = taxAmount
}

const scrollToCreateDeductionSection = async () => {
  await nextTick()
  createDeductionSectionRef.value?.scrollIntoView({
    behavior: 'smooth',
    block: 'start'
  })
}

const checkExistingInvoicePeriod = async ({
  projectName,
  periodYear,
  periodMonth,
  excludeId = null
}) => {
  if (!projectName || !periodYear || !periodMonth) {
    return { exists: false, total: 0 }
  }

  const response = await getInvoiceApplications({
    project_name: projectName,
    period_year: periodYear,
    period_month: periodMonth,
    exclude_id: excludeId,
    per_page: 5
  })

  if (!response?.success) {
    return { exists: false, total: 0 }
  }

  const total = Number(response.data?.total || 0)
  return {
    exists: total > 0,
    total
  }
}

const warnDuplicateCreatePeriodIfNeeded = async () => {
  const projectName = String(createForm.project_name || '').trim()
  const periodYear = createForm.period_year
  const periodMonth = createForm.period_month

  if (!projectName || !periodYear || !periodMonth) {
    return
  }

  const warnKey = `${projectName}_${periodYear}_${periodMonth}`
  if (lastCreateDuplicateWarnKey.value === warnKey) {
    return
  }

  try {
    const { exists, total } = await checkExistingInvoicePeriod({
      projectName,
      periodYear,
      periodMonth
    })

    if (exists) {
      lastCreateDuplicateWarnKey.value = warnKey
      ElMessage.warning(`${projectName}${periodYear}年${periodMonth}月已存在 ${total} 条开票任务，本次仍可继续选择`)
    }
  } catch (error) {
    console.error('检查开票月份重复失败', error)
  }
}

// 状态类型
const getStatusType = (status) => {
  const typeMap = {
    draft: '',
    normal: 'primary',      // 正常 - 蓝色
    pending: 'warning',     // 审批中 - 黄色
    approved: 'success',    // 已通过 - 绿色
    rejected: 'danger',     // 已驳回 - 红色
    red_flushed: 'danger'   // 红冲 - 红色
  }
  return typeMap[status] || ''
}

// 获取审批状态类型
const getApprovalStatusType = (status) => {
  const typeMap = {
    pending: 'warning',    // 审批中
    approved: 'success',   // 已通过
    rejected: 'danger'     // 已驳回
  }
  return typeMap[status] || 'info'
}

// 获取审批状态文本
const getApprovalStatusText = (status) => {
  const textMap = {
    pending: '审批中',
    approved: '已通过',
    rejected: '已驳回'
  }
  return textMap[status] || status
}

// 加载数据
const loadData = async () => {
  loading.value = true
  try {
    const response = await getInvoiceApplications({
      year: searchForm.year,
      month: searchForm.month,
      status: searchForm.status,
      page: pagination.current,
      per_page: pagination.pageSize
    })

    if (response.success) {
      tableData.value = response.data.data
      pagination.total = response.data.total
      pagination.current = response.data.current_page
    }
  } catch (error) {
    console.error('加载数据失败', error)
    ElMessage.error('加载数据失败')
  } finally {
    loading.value = false
  }
}

// 加载发票项目
const loadInvoiceProjects = async () => {
  try {
    const response = await getAllInvoiceProjects()
    if (response.success) {
      invoiceProjects.value = response.data
    }
  } catch (error) {
    console.error('加载项目失败', error)
  }
}

const loadProjectOptions = async () => {
  try {
    const response = await getProjects({ all: true })
    if (response.success) {
      projectOptions.value = Array.isArray(response.data)
        ? response.data
        : (response.data?.data || [])
    }
  } catch (error) {
    console.error('加载项目管理列表失败', error)
  }
}

// 加载开票内容配置项目
const loadInvoiceContentConfigs = async () => {
  try {
    const response = await getAllInvoiceContentConfigs()
    if (response.success) {
      invoiceContentConfigs.value = response.data || []
    }
  } catch (error) {
    console.error('加载开票内容配置失败', error)
  }
}

// 搜索
const handleSearch = () => {
  pagination.current = 1
  loadData()
}

// 重置
const handleReset = () => {
  searchForm.year = currentYear
  searchForm.month = currentMonth
  searchForm.status = null
  searchForm.approval_status = null
  handleSearch()
}

// 导出Excel（纯前端实现）
const handleExport = async () => {
  if (!searchForm.year || !searchForm.month) {
    ElMessage.warning('请选择年份和月份')
    return
  }

  try {
    exporting.value = true
    
    // 获取当前显示的表格数据
    const exportData = tableData.value
    
    if (!exportData || exportData.length === 0) {
      ElMessage.warning('没有数据可导出')
      return
    }
    
    // 准备Excel数据
    const accountSetName = accountSetStore.currentAccountSet?.name || '汇邦人力'
    const title = `${accountSetName}${searchForm.year}年${searchForm.month}月开票登记表`
    
    // 表头
    const headers = [
      '序号', '所属期', '单位名称', '申请日期', '开票方式', '开票种类', 
      '状态', '项目名称', '开票金额', '扣除额', '税率', '不含税金额', 
      '开票税额', '税金', '开票日期', '是否完成', '开票人', '发票号码', '备注'
    ]
    
    // 数据行
    const dataRows = exportData.map((item, index) => {
      // 开票方式映射
      const invoiceMethodMap = {
        'full': '差额征税-全额开票',
        'diff': '差额征税-差额开票',
        'partial': '缺额', // 兼容旧数据
        'none': '无' // 兼容旧数据
      }
      
      return [
        index + 1, // 序号
        `${item.period_year || item.year}-${String(item.period_month || item.month).padStart(2, '0')}`, // 所属期
        item.company_name || '', // 单位名称
        item.application_date || '', // 申请日期
        invoiceMethodMap[item.invoice_method] || '', // 开票方式
        item.invoice_type || '普通发票', // 开票种类
        item.status_text || '', // 状态
        item.project_name || '', // 项目名称
        item.amount_excluding_tax || 0, // 开票金额
        item.deduction_amount || 0, // 扣除额
        item.tax_rate || 0, // 税率
        item.amount_excluding_tax || 0, // 不含税金额
        item.invoice_tax_amount || 0, // 开票税额
        item.tax_amount || 0, // 税金
        item.invoice_date || '', // 开票日期
        item.is_completed ? '是' : '否', // 是否完成
        item.invoicer || '', // 开票人
        item.invoice_number || '', // 发票号码
        item.invoice_remark || '' // 备注
      ]
    })
    
    // 创建工作表数据（标题 + 表头 + 数据）
    const wsData = [
      [title], // 第1行：标题
      headers, // 第2行：表头
      ...dataRows // 第3行起：数据
    ]
    
    // 创建工作表
    const ws = XLSX.utils.aoa_to_sheet(wsData)
    
    // 合并标题单元格 A1:S1
    ws['!merges'] = [{ s: { r: 0, c: 0 }, e: { r: 0, c: 18 } }]
    
    // 设置标题单元格样式（居中、加粗、字体大小）
    if (!ws['A1'].s) ws['A1'].s = {}
    ws['A1'].s = {
      alignment: {
        horizontal: 'center',
        vertical: 'center'
      },
      font: {
        bold: true,
        sz: 14
      }
    }
    
    // 设置列宽
    ws['!cols'] = [
      { wch: 6 },   // A: 序号
      { wch: 10 },  // B: 所属期
      { wch: 20 },  // C: 单位名称
      { wch: 12 },  // D: 申请日期
      { wch: 10 },  // E: 开票方式
      { wch: 10 },  // F: 开票种类
      { wch: 8 },   // G: 状态
      { wch: 18 },  // H: 项目名称
      { wch: 12 },  // I: 开票金额
      { wch: 12 },  // J: 扣除额
      { wch: 10 },  // K: 税率
      { wch: 12 },  // L: 不含税金额
      { wch: 12 },  // M: 开票税额
      { wch: 12 },  // N: 税金
      { wch: 12 },  // O: 开票日期
      { wch: 10 },  // P: 是否完成
      { wch: 12 },  // Q: 开票人
      { wch: 16 },  // R: 发票号码
      { wch: 20 }   // S: 备注
    ]
    
    // 创建工作簿
    const wb = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(wb, ws, '开票登记表')
    
    // 导出文件
    const filename = `${accountSetName}${searchForm.year}年${searchForm.month}月开票登记表.xlsx`
    XLSX.writeFile(wb, filename)
    
    ElMessage.success('导出成功')
  } catch (error) {
    console.error('导出失败', error)
    ElMessage.error('导出失败')
  } finally {
    exporting.value = false
  }
}

// 创建开票任务
const getTodayDate = () => {
  return new Date().toISOString().split('T')[0]
}

const resetCreateForm = () => {
  const today = getTodayDate()
  createForm.task_name = ''
  createForm.year = currentYear
  createForm.month = currentMonth
  createForm.status = 'normal'
  createForm.red_flush_source_id = null
  createForm.project_id = null
  createForm.invoice_info_index = 0
  createForm.project_name = ''
  createForm.period_year = currentYear
  createForm.period_month = currentMonth
  createForm.company_name = ''
  createForm.application_date = today
  createForm.invoice_method = null
  createForm.invoice_type = '普通发票'
  createForm.deduction_amount = 0
  createForm.tax_rate = null
  createForm.amount_excluding_tax = 0
  createForm.invoice_tax_amount = 0
  createForm.invoice_amount = 0
  createForm.tax_amount = 0
  createForm.invoice_date = today
  createForm.earliest_invoice_date = ''
  createForm.is_completed = false
  createForm.invoicer = ''
  createForm.invoice_number = ''
  createForm.invoice_remark = ''
  resetCreateItems()
  resetCreateContentItems()
  resetSelectedProjectInvoiceInfo()
  redFlushCandidates.value = []
  lastCreateDuplicateWarnKey.value = ''
  createFormRef.value?.clearValidate()
}

const handleCreate = () => {
  resetCreateForm()
  createDialogVisible.value = true
}

// 确认创建
const handleConfirmCreate = async () => {
  try {
    const projectName = String(createForm.project_name || '').trim()
    const taskName = (projectName || '开票') + `${createForm.year}年${createForm.month}月`

    await createFormRef.value.validate()

    if (!String(createForm.company_name || '').trim()) {
      ElMessage.warning('所选项目未配置开票信息，请先到项目管理中维护')
      return
    }

    syncCreateItemAmountsFromRemark()
    syncCreateCalculatedAmounts()

    const normalizedContentItems = normalizeCreateContentItems()
    const contentTotals = sumCreateContentItems(normalizedContentItems)
    createForm.invoice_amount = contentTotals.invoice_amount
    createForm.tax_rate = contentTotals.tax_rate
    createForm.deduction_amount = contentTotals.deduction_amount
    createForm.amount_excluding_tax = contentTotals.amount_excluding_tax
    createForm.invoice_tax_amount = contentTotals.invoice_tax_amount
    createForm.tax_amount = contentTotals.tax_amount
    if (!validateCreateExtraData()) {
      return
    }

    creating.value = true
    const createPayload = new FormData()
    const createFields = {
      task_name: taskName,
      year: createForm.year,
      month: createForm.month,
      status: createForm.status,
      red_flush_source_id: createForm.red_flush_source_id,
      project_id: createForm.project_id,
      invoice_info_index: createForm.invoice_info_index,
      project_name: projectName,
      period_year: createForm.period_year,
      period_month: createForm.period_month,
      company_name: createForm.company_name,
      application_date: createForm.application_date,
      invoice_method: createForm.invoice_method,
      invoice_type: createForm.invoice_type,
      deduction_amount: createForm.deduction_amount,
      tax_rate: createForm.tax_rate,
      amount_excluding_tax: createForm.amount_excluding_tax,
      invoice_tax_amount: createForm.invoice_tax_amount,
      invoice_amount: createForm.invoice_amount,
      tax_amount: createForm.tax_amount,
      invoice_date: createForm.invoice_date,
      earliest_invoice_date: createForm.earliest_invoice_date,
      invoice_remark: createForm.invoice_remark,
      current_account_set_id: accountSetStore.currentAccountSetId
    }
    Object.entries(createFields).forEach(([key, value]) => {
      if (value !== null && value !== undefined) {
        createPayload.append(key, value)
      }
    })
    createPayload.append('items', JSON.stringify(createNeedsDeductionAmount.value ? createItems.value : []))
    createPayload.append('content_items', JSON.stringify(normalizedContentItems))

    const response = await createInvoiceApplication(createPayload)

    if (!response?.success) {
      ElMessage.error(response?.message || '创建失败')
      return
    }

    ElMessage.success(response.message || '创建成功')
    createDialogVisible.value = false
    loadData()
  } catch (error) {
    if (error !== false) {
      console.error('创建失败', error)
      ElMessage.error(error.response?.data?.message || error.message || '创建失败')
    }
  } finally {
    creating.value = false
  }
}

const handleCreateDialogClose = () => {
  resetCreateForm()
}

// 查看详情
const handleDetail = async (row) => {
  isEditMode.value = false
  detailDialogTitle.value = `发票申请详情 - ${row.application_no}`
  await loadApplicationDetail(row.id)
  detailDialogVisible.value = true
}

// 编辑
const handleEdit = async (row) => {
  isEditMode.value = true
  detailDialogTitle.value = `编辑发票申请 - ${row.application_no}`
  await loadApplicationDetail(row.id)
  detailDialogVisible.value = true
}

// 更新项目名称
const handleUpdateProjectName = async () => {
  try {
    if (!currentApplication.value.project_name || !currentApplication.value.project_name.trim()) {
      ElMessage.warning('项目名称不能为空')
      await loadApplicationDetail(currentApplication.value.id)
      return
    }

    const response = await request({
      url: `/invoice-applications/${currentApplication.value.id}/update-project`,
      method: 'put',
      data: {
        project_name: currentApplication.value.project_name
      }
    })

    if (response.success) {
      ElMessage.success('项目名称更新成功')
    }
  } catch (error) {
    console.error('更新项目名称失败', error)
    ElMessage.error(error.response?.data?.message || '更新失败')
    await loadApplicationDetail(currentApplication.value.id)
  }
}

// 加载申请详情
const loadApplicationDetail = async (id) => {
  try {
    const response = await getInvoiceApplicationDetail(id)
    if (response.success) {
      currentApplication.value = response.data
      // 填充开票详情表单
      loadInvoiceDetailsForm(response.data)
      return true
    }
  } catch (error) {
    console.error('加载详情失败', error)
    ElMessage.error('加载详情失败')
  }
  return false
}

// 填充开票详情表单
const loadInvoiceDetailsForm = (data) => {
  invoiceDetailsForm.period_year = data.period_year
  invoiceDetailsForm.period_month = data.period_month
  invoiceDetailsForm.company_name = data.company_name || ''
  invoiceDetailsForm.application_date = data.application_date
  invoiceDetailsForm.invoice_method = data.invoice_method
  invoiceDetailsForm.invoice_type = data.invoice_type || '普通发票'
  invoiceDetailsForm.deduction_amount = data.deduction_amount || 0
  invoiceDetailsForm.tax_rate = data.tax_rate || 0
  invoiceDetailsForm.amount_excluding_tax = data.amount_excluding_tax || 0
  invoiceDetailsForm.invoice_tax_amount = data.invoice_tax_amount || 0
  invoiceDetailsForm.invoice_amount = data.invoice_amount || 0
  invoiceDetailsForm.tax_amount = data.tax_amount || 0
  invoiceDetailsForm.invoice_date = data.invoice_date
  invoiceDetailsForm.earliest_invoice_date = data.earliest_invoice_date || null
  invoiceDetailsForm.is_completed = data.is_completed || false
  invoiceDetailsForm.invoicer = data.invoicer || ''
  invoiceDetailsForm.invoice_number = data.invoice_number || ''
  invoiceDetailsForm.invoice_remark = data.invoice_remark || ''
  syncInvoiceDetailsCalculatedAmounts()
}

// 保存开票详情
const handleSaveInvoiceDetails = async () => {
  try {
    // 校验表单
    if (invoiceDetailsFormRef.value) {
      await invoiceDetailsFormRef.value.validate()
    }

    const response = await request({
      url: `/invoice-applications/${currentApplication.value.id}/update-invoice-details`,
      method: 'put',
      data: {
        invoice_number: invoiceDetailsForm.invoice_number
      }
    })

    if (response.success) {
      ElMessage.success('保存成功')
      await loadApplicationDetail(currentApplication.value.id)
      return true
    }
  } catch (error) {
    if (error === 'cancel') return
    console.error('保存失败', error)
    ElMessage.error(error.response?.data?.message || '保存失败')
  }
  return false
}

// 详情对话框关闭
const handleDetailDialogClose = () => {
  currentApplication.value = {}
  activeTab.value = 'invoice_details'
}

const openInvoiceNumberDialog = (application) => {
  invoiceNumberForm.id = application.id
  invoiceNumberForm.application_no = application.application_no || ''
  invoiceNumberForm.invoice_number = application.invoice_number || ''
  invoiceNumberFileList.value = []
  invoiceNumberDialogVisible.value = true
  nextTick(() => {
    invoiceNumberFormRef.value?.clearValidate()
  })
}

const handleInvoiceNumberDialogClose = () => {
  invoiceNumberForm.id = null
  invoiceNumberForm.application_no = ''
  invoiceNumberForm.invoice_number = ''
  invoiceNumberFileList.value = []
  invoiceNumberFormRef.value?.clearValidate()
}

const handleOpenInvoiceFill = async (row) => {
  const loaded = await loadApplicationDetail(row.id)
  if (!loaded) return
  openInvoiceNumberDialog(currentApplication.value)
}

const handleConfirmInvoiceNumber = async () => {
  try {
    await invoiceNumberFormRef.value?.validate()
    const invoiceNumber = String(invoiceNumberForm.invoice_number || '').trim()
    const applicationId = invoiceNumberForm.id

    invoiceNumberSubmitting.value = true
    const response = await request({
      url: `/invoice-applications/${applicationId}/update-invoice-details`,
      method: 'put',
      data: {
        invoice_number: invoiceNumber
      }
    })

    if (response.success) {
      invoiceDetailsForm.invoice_number = invoiceNumber
      if (currentApplication.value.id === applicationId) {
        currentApplication.value.invoice_number = invoiceNumber
      }

      for (const fileItem of invoiceNumberFileList.value) {
        const rawFile = fileItem.raw || fileItem
        if (rawFile) {
          if (!beforeUpload(rawFile)) {
            return false
          }
          const uploadResponse = await uploadAttachment(applicationId, rawFile, 'invoice')
          if (!uploadResponse?.success) {
            throw new Error(uploadResponse?.message || '发票附件上传失败')
          }
        }
      }

      await loadApplicationDetail(applicationId)
      const submitted = await handleSubmit()
      if (submitted) {
        invoiceNumberDialogVisible.value = false
        invoiceNumberFileList.value = []
      }
      return submitted
    }
    ElMessage.error(response?.message || '保存失败')
  } catch (error) {
    if (!error?.response && !error?.message) return false
    console.error('保存发票号码失败', error)
    ElMessage.error(error.response?.data?.message || error.message || '保存失败')
  } finally {
    invoiceNumberSubmitting.value = false
  }
  return false
}

// 删除申请
const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(
      `确定要删除申请"${row.application_no}"吗？`,
      '提示',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    const response = await deleteInvoiceApplication(row.id)
    if (response.success) {
      ElMessage.success(response.message || '删除成功')
      loadData()
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败', error)
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }
}

// 添加明细
const handleAddItem = () => {
  isEditItem.value = false
  itemDialogTitle.value = '添加明细项'
  resetItemForm()
  itemDialogVisible.value = true
}

// 编辑明细
const handleEditItem = (row) => {
  isEditItem.value = true
  itemDialogTitle.value = '编辑明细项'
  itemForm.id = row.id
  itemForm.invoice_project_id = row.invoice_project_id
  itemForm.item_name = row.item_name || ''
  itemForm.amount = Number(row.amount || 0)
  itemForm.remark = row.remark
  itemDialogVisible.value = true
}

// 删除明细
const handleDeleteItem = async (row) => {
  try {
    await ElMessageBox.confirm('确定要删除这条明细吗？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })

    const response = await deleteInvoiceItem(currentApplication.value.id, row.id)
    if (response.success) {
      ElMessage.success(response.message || '删除成功')
      await loadApplicationDetail(currentApplication.value.id)
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败', error)
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }
}

// 提交明细
const handleItemSubmit = async () => {
  try {
    await itemFormRef.value.validate()
    
    itemSubmitting.value = true
    let response

    if (isEditItem.value) {
      response = await updateInvoiceItem(currentApplication.value.id, itemForm.id, itemForm)
    } else {
      response = await addInvoiceItem(currentApplication.value.id, itemForm)
    }

    if (response.success) {
      ElMessage.success(response.message || (isEditItem.value ? '更新成功' : '添加成功'))
      itemDialogVisible.value = false
      await loadApplicationDetail(currentApplication.value.id)
    }
  } catch (error) {
    if (error !== false) {
      console.error('提交失败', error)
      ElMessage.error(error.response?.data?.message || '操作失败')
    }
  } finally {
    itemSubmitting.value = false
  }
}

// 重置明细表单
const resetItemForm = () => {
  itemForm.id = null
  itemForm.invoice_project_id = null
  itemForm.item_name = ''
  itemForm.amount = 0
  itemForm.remark = ''
  itemFormRef.value?.clearValidate()
}

// 明细对话框关闭
const handleItemDialogClose = () => {
  resetItemForm()
}

const handleProjectInput = (keyword) => {
  handleInvoiceProjectInput(itemForm, keyword)
}

const handleProjectSelect = (selected) => {
  handleInvoiceProjectSelect(itemForm, selected)
}

const hasDeductionExcelAttachment = () => {
  return currentApplication.value.attachments?.some(att => {
    return att.filename && att.filename.includes('扣除明细')
  })
}

const autoGenerateDeductionExcelIfNeeded = async () => {
  const invoiceMethod = invoiceDetailsForm.invoice_method
  const needsDeductionExcel = invoiceMethod === 'full' || invoiceMethod === 'diff'
  if (!needsDeductionExcel) return
  if (hasDeductionExcelAttachment()) return

  const response = await generateExcel(currentApplication.value.id)
  if (!response?.success) {
    throw new Error(response?.message || '自动生成扣除明细表失败')
  }
  await loadApplicationDetail(currentApplication.value.id)
}

// 上传前验证
const beforeUpload = (file) => {
  const isLt10M = file.size / 1024 / 1024 < 10
  if (!isLt10M) {
    ElMessage.error('文件大小不能超过 10MB')
  }
  return isLt10M
}

// 自定义上传请求
const handleUploadRequest = async (options) => {
  try {
    const response = await uploadAttachment(currentApplication.value.id, options.file)
    if (response.success) {
      ElMessage.success('上传成功')
      await loadApplicationDetail(currentApplication.value.id)
      options.onSuccess(response)
    } else {
      ElMessage.error(response.message || '上传失败')
      options.onError(response)
    }
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '上传失败')
    options.onError(error)
  }
}

// 上传成功
const handleUploadSuccess = async (response) => {
  if (response.success) {
    ElMessage.success('上传成功')
    await loadApplicationDetail(currentApplication.value.id)
  } else {
    ElMessage.error(response.message || '上传失败')
  }
}

// 上传失败
const handleUploadError = (error) => {
  console.error('上传失败', error)
  ElMessage.error('上传失败')
}

// 下载附件
const handleDownload = async (attachment) => {
  try {
    ElMessage.info('正在下载，请稍候...')

    const attachmentPath = String(attachment?.path || '').trim()
    if (!attachmentPath) {
      ElMessage.error('附件路径无效')
      return
    }

    const encodedPath = attachmentPath
      .split('/')
      .map(segment => encodeURIComponent(segment))
      .join('/')

    // 直接读取静态文件，避免后端下载链路对二进制流造成影响
    const response = await fetch(`/storage/${encodedPath}`, { method: 'GET' })
    if (!response.ok) {
      throw new Error(`下载失败: ${response.status}`)
    }

    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)

    const link = document.createElement('a')
    link.href = url
    link.download = attachment.filename || '附件'
    link.style.display = 'none'

    document.body.appendChild(link)
    link.click()

    setTimeout(() => {
      document.body.removeChild(link)
      window.URL.revokeObjectURL(url)
    }, 100)

    ElMessage.success('下载成功')
  } catch (error) {
    ElMessage.error('下载失败')
  }
}

// 删除附件
const handleDeleteAttachment = async (attachment) => {
  try {
    await ElMessageBox.confirm('确定要删除这个附件吗？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })

    const response = await deleteAttachment(currentApplication.value.id, attachment.path)
    if (response.success) {
      ElMessage.success(response.message || '删除成功')
      await loadApplicationDetail(currentApplication.value.id)
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败', error)
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }
}

// 提交审批
const handleSubmit = async () => {
  try {
    if (!String(invoiceDetailsForm.invoice_number || '').trim()) {
      ElMessage.warning('请先填写发票号码')
      if (currentApplication.value?.id) {
        openInvoiceNumberDialog(currentApplication.value)
      }
      return false
    }

    await autoGenerateDeductionExcelIfNeeded()

    submitting.value = true
    const response = await submitInvoiceApplication(currentApplication.value.id, {
      stamp_method: 'none',
      stamp_selection_mode: 'none'
    })

    if (response.success) {
      ElMessage.success(response.message || '提交成功')
      detailDialogVisible.value = false
      await loadData()
      return true
    }
    ElMessage.error(response?.message || '提交失败')
  } catch (error) {
    if (error !== 'cancel') {
      console.error('提交失败', error)
      ElMessage.error(error.response?.data?.message || '提交失败')
    }
  } finally {
    submitting.value = false
  }
  return false
}

const handleSubmitFromList = async (row) => {
  isEditMode.value = true
  const loaded = await loadApplicationDetail(row.id)
  if (!loaded) return

  if (!String(invoiceDetailsForm.invoice_number || '').trim()) {
    ElMessage.warning('请先填写发票号码')
    openInvoiceNumberDialog(currentApplication.value)
    return
  }

  await handleSubmit()
}

// 格式化文件大小
const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return (bytes / Math.pow(k, i)).toFixed(2) + ' ' + sizes[i]
}

// 获取审批步骤
const getApprovalStep = () => {
  if (!currentApplication.value.approval_instance) return 0
  const approvals = currentApplication.value.approval_instance.approvals || []
  const pendingIndex = approvals.findIndex(a => a.status === 'pending')
  return pendingIndex === -1 ? approvals.length : pendingIndex
}

// 获取审批状态
const getApprovalStatus = (approval) => {
  if (approval.status === 'approved') return 'success'
  if (approval.status === 'rejected') return 'error'
  if (approval.status === 'pending') return 'process'
  return 'wait'
}

// 获取审批描述
const getApprovalDescription = (approval) => {
  if (approval.status === 'approved') {
    return `${approval.approver?.name} 已审批\n${approval.approved_at}`
  }
  if (approval.status === 'rejected') {
    return `${approval.approver?.name} 已驳回\n${approval.comment}`
  }
  if (approval.status === 'pending') {
    return '待审批'
  }
  return ''
}

const handleRouteInvoiceFill = async () => {
  const action = route.query.action
  const invoiceId = route.query.id
  if (!['fill_invoice_info', 'fill_invoice_number'].includes(action) || !invoiceId) {
    return
  }

  try {
    isEditMode.value = true
    const loaded = await loadApplicationDetail(invoiceId)
    if (!loaded) return
    openInvoiceNumberDialog(currentApplication.value)
  } finally {
    const nextQuery = { ...route.query }
    delete nextQuery.id
    delete nextQuery.action
    delete nextQuery.task_id
    router.replace({ path: route.path, query: nextQuery })
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

const formatContentProjectNames = (contentItems = []) => {
  if (!Array.isArray(contentItems) || contentItems.length === 0) {
    return '-'
  }

  const projectNames = contentItems
    .map(item => String(item?.project_name || '').trim())
    .filter(Boolean)

  if (projectNames.length === 0) {
    return '-'
  }

  return [...new Set(projectNames)].join('、')
}

// 监听开票方式变化，自动清空扣除额（如果不是全额或差额）
watch(
  () => createForm.invoice_method,
  async (newMethod, oldMethod) => {
    if ((newMethod === 'full' || newMethod === 'diff') && oldMethod !== 'full' && oldMethod !== 'diff') {
      await scrollToCreateDeductionSection()
    }

    syncCreateCalculatedAmounts()
  }
)

watch(
  () => [createForm.project_name, createForm.period_year, createForm.period_month],
  () => {
    warnDuplicateCreatePeriodIfNeeded()
  }
)

watch(
  () => invoiceDetailsForm.invoice_method,
  (newMethod) => {
    if (newMethod !== 'full' && newMethod !== 'diff') {
      invoiceDetailsForm.deduction_amount = 0
    }
    syncInvoiceDetailsCalculatedAmounts()
  }
)

watch(
  () => [createForm.invoice_amount, createForm.tax_rate, createForm.invoice_method],
  () => {
    syncCreateCalculatedAmounts()
  }
)

watch(
  createItems,
  () => {
    syncCreateCalculatedAmounts()
  },
  { deep: true }
)

watch(
  createContentItems,
  () => {
    syncCreateCalculatedAmounts()
  },
  { deep: true }
)

watch(
  () => [invoiceDetailsForm.invoice_amount, invoiceDetailsForm.tax_rate, invoiceDetailsForm.invoice_method],
  () => {
    syncInvoiceDetailsCalculatedAmounts()
  }
)

watch(
  () => currentApplication.value.items,
  () => {
    syncInvoiceDetailsCalculatedAmounts()
  },
  { deep: true }
)

watch(
  () => [route.query.id, route.query.action],
  () => {
    handleRouteInvoiceFill()
  },
  { immediate: true }
)

// 初始化
onMounted(() => {
  loadData()
  loadProjectOptions()
  loadInvoiceProjects()
  loadInvoiceContentConfigs()
  resetCreateContentItems()
  syncCreateCalculatedAmounts()
})
</script>

<style scoped>
.invoice-applications-container {
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header .title {
  font-size: 18px;
  font-weight: bold;
}

.search-form {
  margin-bottom: 20px;
}

.items-section,
.invoice-content-items-section,
.attachments-section,
.approval-section {
  padding: 20px;
}

.invoice-content-items-table {
  width: 100%;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  font-weight: bold;
}

.total-amount {
  margin-top: 15px;
  text-align: right;
  font-size: 16px;
  font-weight: bold;
}

.total-amount span {
  color: #f56c6c;
  font-size: 18px;
}

.excel-section {
  margin-top: 20px;
  text-align: center;
}

.text-muted {
  color: #909399;
  font-size: 14px;
}

</style>
