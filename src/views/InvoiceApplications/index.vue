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
        <el-table-column prop="application_no" label="申请单号" width="180" />
        <el-table-column label="期间" width="120" align="center">
          <template #default="{ row }">
            {{ row.year }}-{{ String(row.month).padStart(2, '0') }}
          </template>
        </el-table-column>
        <el-table-column prop="total_amount" label="总金额" width="120" align="right">
          <template #default="{ row }">
            ¥{{ Number(row.total_amount || 0).toFixed(2) }}
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
              v-if="row.can_fill_invoice && row.approval_status === 'approved' && !row.is_completed"
              type="success"
              link
              @click="handleOpenInvoiceFill(row)"
            >
              填写发票号
            </el-button>
            <el-button 
              v-if="canEditInvoice && (!row.approval_status || row.approval_status === 'rejected')" 
              type="primary" 
              link 
              @click="handleEdit(row)"
            >
              编辑
            </el-button>
            <el-button
              v-if="canEditInvoice && (!row.approval_status || row.approval_status === 'rejected')"
              type="success"
              link
              @click="handleSubmitFromList(row)"
            >
              提交
            </el-button>
            <el-button 
              v-if="canEditInvoice && row.can_resubmit" 
              type="warning" 
              link 
              @click="handleResubmit(row)"
            >
              重新发起
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
          <el-col :span="24">
            <el-form-item label="项目" prop="project_name">
              <el-autocomplete
                v-model="createForm.project_name"
                :fetch-suggestions="querySearchCreateProjects"
                placeholder="请输入或选择项目"
                clearable
                style="width: 100%"
                @input="handleCreateProjectInput"
                @select="handleCreateProjectSelect"
              />
            </el-form-item>
          </el-col>
          <el-col :span="24" v-if="createProjectInvoiceInfoOptions.length > 0">
            <el-form-item label="开票信息备注" prop="remark">
              <el-select
                v-model="createForm.remark"
                placeholder="请选择开票信息备注"
                style="width: 100%"
                @change="handleCreateInvoiceInfoRemarkChange"
              >
                <el-option
                  v-for="option in createProjectInvoiceInfoOptions"
                  :key="`project-invoice-remark-${option.value}`"
                  :label="option.label"
                  :value="option.value"
                />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

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
              <el-input v-model="createForm.company_name" placeholder="请输入单位名称" />
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
            <el-form-item label="开票金额" prop="invoice_amount">
              <el-input-number
                v-model="createForm.invoice_amount"
                :precision="2"
                :min="0"
                :controls="false"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="税率" prop="tax_rate">
              <el-select v-model="createForm.tax_rate" placeholder="请选择税率" style="width: 100%">
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
          <el-col :span="12">
            <el-form-item label="扣除额" prop="deduction_amount">
              <el-input-number
                v-model="createForm.deduction_amount"
                :precision="2"
                :min="0"
                :controls="false"
                disabled
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="开票税额" prop="invoice_tax_amount">
              <el-input-number
                v-model="createForm.invoice_tax_amount"
                :precision="2"
                :min="0"
                :controls="false"
                disabled
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="不含税金额" prop="amount_excluding_tax">
              <el-input-number
                v-model="createForm.amount_excluding_tax"
                :precision="2"
                :min="0"
                :controls="false"
                disabled
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="税金" prop="tax_amount">
              <el-input-number
                v-model="createForm.tax_amount"
                :precision="2"
                :min="0"
                :controls="false"
                disabled
                style="width: 100%"
              />
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
          <el-col :span="12">
            <el-form-item label="开票人">
              <el-input :model-value="'审批通过后自动带出当前账号'" disabled />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="发票号码">
              <el-input :model-value="'创建时不需要填写'" disabled />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="是否完成">
              <el-input :model-value="'审批通过后填写发票号后自动更新'" disabled />
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
            <el-table-column label="规格型号" min-width="160">
              <template #default="{ row }">
                <el-input v-model="row.spec_model" placeholder="请输入规格型号" />
              </template>
            </el-table-column>
            <el-table-column label="单位" width="100">
              <template #default="{ row }">
                <el-input v-model="row.unit" placeholder="单位" />
              </template>
            </el-table-column>
            <el-table-column label="数量" width="130">
              <template #default="{ row }">
                <el-input-number
                  v-model="row.quantity"
                  :precision="4"
                  :min="0"
                  :controls="false"
                  style="width: 100%"
                />
              </template>
            </el-table-column>
            <el-table-column label="单价(不含税)" width="150">
              <template #default="{ row }">
                <el-input-number
                  v-model="row.unit_price"
                  :precision="2"
                  :min="0"
                  :controls="false"
                  style="width: 100%"
                />
              </template>
            </el-table-column>
            <el-table-column label="金额(不含税)" width="150">
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
            <el-table-column label="税率/征收率" width="140">
              <template #default="{ row }">
                <el-select v-model="row.tax_rate" placeholder="税率" style="width: 100%">
                  <el-option
                    v-for="option in invoiceItemTaxRateOptions"
                    :key="option.value"
                    :label="option.label"
                    :value="option.value"
                  />
                </el-select>
              </template>
            </el-table-column>
            <el-table-column label="税额" width="140">
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

        <el-divider content-position="left">附件上传</el-divider>
        <el-upload
          v-model:file-list="createAttachmentFileList"
          action="#"
          multiple
          :auto-upload="false"
          :before-upload="beforeCreateAttachmentUpload"
        >
          <el-button type="primary">
            <el-icon><Upload /></el-icon>
            选择附件
          </el-button>
          <template #tip>
            <div class="el-upload__tip">可上传多个文件，单个文件不超过10MB</div>
          </template>
        </el-upload>
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
              <el-descriptions-item label="项目" :span="2">
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
              >
                <el-icon><Plus /></el-icon>
                添加明细
              </el-button>
            </div>

            <el-table :data="currentApplication.items" border style="margin-top: 10px">
              <el-table-column prop="sequence" label="序号" width="70" align="center" />
              <el-table-column prop="project_name" label="模板项目" width="150" />
              <el-table-column prop="item_name" label="项目名称" width="160" />
              <el-table-column prop="spec_model" label="规格型号" width="150" show-overflow-tooltip />
              <el-table-column prop="unit" label="单位" width="90" />
              <el-table-column prop="quantity" label="数量" width="100" align="right" />
              <el-table-column prop="unit_price" label="单价(不含税)" width="130" align="right">
                <template #default="{ row }">
                  ¥{{ Number(row.unit_price || 0).toFixed(2) }}
                </template>
              </el-table-column>
              <el-table-column prop="amount" label="金额(不含税)" width="130" align="right">
                <template #default="{ row }">
                  ¥{{ Number(row.amount).toFixed(2) }}
                </template>
              </el-table-column>
              <el-table-column prop="tax_rate" label="税率/征收率" width="120" align="center">
                <template #default="{ row }">
                  {{ formatInvoiceItemRate(row.tax_rate) }}
                </template>
              </el-table-column>
              <el-table-column prop="tax_amount" label="税额" width="120" align="right">
                <template #default="{ row }">
                  ¥{{ Number(row.tax_amount || 0).toFixed(2) }}
                </template>
              </el-table-column>
              <el-table-column prop="remark" label="备注" min-width="200" show-overflow-tooltip />
              <el-table-column v-if="canEdit" label="操作" width="150" align="center">
                <template #default="{ row }">
                  <el-button type="primary" link @click="handleEditItem(row)">编辑</el-button>
                  <el-button type="danger" link @click="handleDeleteItem(row)">删除</el-button>
                </template>
              </el-table-column>
            </el-table>

            <div class="total-amount">
              合计金额：<span>¥{{ totalAmount.toFixed(2) }}</span>
            </div>

          </div>
        </el-tab-pane>

        <!-- 附件 -->
        <el-tab-pane label="附件上传" name="attachments">
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

        <!-- 开票详情 -->
        <el-tab-pane label="开票详情" name="invoice_details">
          <div class="invoice-details-section">
            <el-form ref="invoiceDetailsFormRef" :model="invoiceDetailsForm" :rules="invoiceDetailsFormRules" label-width="120px" :disabled="!canEdit">
              <el-row :gutter="20">
                <!-- 所属期-年份 -->
                <el-col :span="12">
                  <el-form-item label="所属期-年份" prop="period_year">
                    <el-select v-model="invoiceDetailsForm.period_year" placeholder="请选择年份" style="width: 100%">
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
                    <el-select v-model="invoiceDetailsForm.period_month" placeholder="请选择月份" style="width: 100%">
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
                    <el-input v-model="invoiceDetailsForm.company_name" placeholder="请输入单位名称" />
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
                    <el-select v-model="invoiceDetailsForm.invoice_type" placeholder="请选择开票种类" style="width: 100%">
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
                      disabled
                      style="width: 100%"
                    />
                  </el-form-item>
                </el-col>

                <!-- 开票税额 -->
                <el-col :span="12">
                  <el-form-item label="开票税额" prop="invoice_tax_amount">
                    <el-input-number
                      v-model="invoiceDetailsForm.invoice_tax_amount"
                      :precision="2"
                      :min="0"
                      :controls="false"
                      disabled
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
                    />
                  </el-form-item>
                </el-col>

                <!-- 开票人 -->
                <el-col :span="12">
                  <el-form-item label="开票人">
                    <el-input v-model="invoiceDetailsForm.invoicer" placeholder="审批通过后自动带出" disabled />
                  </el-form-item>
                </el-col>

                <!-- 发票号码 -->
                <el-col :span="12">
                  <el-form-item label="发票号码">
                    <el-input v-model="invoiceDetailsForm.invoice_number" placeholder="审批通过后填写" disabled />
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
                    />
                  </el-form-item>
                </el-col>

                <!-- 是否完成 -->
                <el-col :span="12">
                  <el-form-item label="是否完成">
                    <el-input
                      :model-value="invoiceDetailsForm.is_completed ? '已完成' : '填写发票号后自动更新'"
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
                    />
                  </el-form-item>
                </el-col>
              </el-row>

              <el-row v-if="canEdit">
                <el-col :span="24" style="text-align: right">
                  <el-button type="warning" @click="fillInvoiceTestData">一键填写测试数据</el-button>
                  <el-button type="primary" @click="handleSaveInvoiceDetails">保存开票详情</el-button>
                </el-col>
              </el-row>
            </el-form>
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
          v-if="currentApplication.can_fill_invoice && currentApplication.approval_status === 'approved' && !currentApplication.is_completed"
          type="primary"
          @click="openInvoiceFillDialog"
        >
          填写发票号
        </el-button>
      </template>
    </el-dialog>

    <el-dialog
      v-model="invoiceFillDialogVisible"
      title="填写发票号码"
      width="620px"
      :close-on-click-modal="false"
    >
      <el-alert
        title="保存发票号码时会自动把当前登录账号写入开票人，并自动更新完成状态。"
        type="info"
        :closable="false"
        style="margin-bottom: 16px"
      />
      <el-form :model="invoiceFillForm" label-width="100px">
        <el-form-item label="开票人">
          <el-input :model-value="currentApplication.invoicer || '保存时自动带出当前账号'" disabled />
        </el-form-item>
        <el-form-item label="发票号码" required>
          <el-input v-model="invoiceFillForm.invoice_number" placeholder="请输入发票号码" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="invoiceFillDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSaveInvoiceNumber" :loading="invoiceFillSaving">
          保存发票号码
        </el-button>
      </template>
    </el-dialog>

    <!-- 盖章方式选择对话框 -->
    <el-dialog
      v-model="submitStampDialogVisible"
      :title="submitStampForm.mode === 'resubmit' ? '重新发起审批' : '提交审批'"
      width="500px"
      :close-on-click-modal="false"
    >
      <el-form :model="submitStampForm" label-width="100px">
        <el-form-item label="盖章方式" required>
          <el-radio-group v-model="submitStampForm.stamp_method">
            <el-radio value="online">线上盖章</el-radio>
            <el-radio value="offline">线下盖章</el-radio>
          </el-radio-group>
          <div style="margin-top: 8px; color: #909399; font-size: 12px;">
            线上盖章：系统自动在PDF上添加印章；线下盖章：需要手动在纸质文件上盖章
          </div>
        </el-form-item>
        <ApprovalStampSelector
          ref="submitStampSelectorRef"
          v-model="submitStampForm.stamp_selection"
        />
      </el-form>
      <template #footer>
        <el-button @click="submitStampDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleConfirmSubmitStamp" :loading="submitting">
          {{ submitStampForm.mode === 'resubmit' ? '确认重新发起' : '确认提交' }}
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
        <el-form-item label="规格型号" prop="spec_model">
          <el-input v-model="itemForm.spec_model" placeholder="请输入规格型号" />
        </el-form-item>
        <el-form-item label="单位" prop="unit">
          <el-input v-model="itemForm.unit" placeholder="请输入单位" />
        </el-form-item>
        <el-form-item label="数量" prop="quantity">
          <el-input-number
            v-model="itemForm.quantity"
            :precision="4"
            :min="0"
            :controls="false"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="单价(不含税)" prop="unit_price">
          <el-input-number
            v-model="itemForm.unit_price"
            :precision="2"
            :min="0"
            :controls="false"
            style="width: 100%"
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
        <el-form-item label="税率/征收率" prop="tax_rate">
          <el-select v-model="itemForm.tax_rate" placeholder="请选择税率/征收率" style="width: 100%">
            <el-option
              v-for="option in invoiceItemTaxRateOptions"
              :key="option.value"
              :label="option.label"
              :value="option.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="税额" prop="tax_amount">
          <el-input-number
            v-model="itemForm.tax_amount"
            :precision="2"
            :min="0"
            :controls="false"
            disabled
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
  deleteInvoiceApplication,
  addInvoiceItem,
  updateInvoiceItem,
  deleteInvoiceItem,
  generateExcel,
  uploadAttachment,
  deleteAttachment,
  submitInvoiceApplication,
  resubmitInvoiceApplication,
  fillInvoiceApplicationNumber
} from '@/api/invoiceApplication'
import { getAllInvoiceProjects } from '@/api/invoiceProject'
import request from '@/api/request'
import { useAccountSetStore } from '@/stores/accountSet'
import { usePermissionStore } from '@/stores/permission'
import ApprovalStampSelector from '@/components/ApprovalStampSelector.vue'
import * as XLSX from 'xlsx'

// 账套store
const route = useRoute()
const router = useRouter()
const accountSetStore = useAccountSetStore()
const permissionStore = usePermissionStore()

const getDefaultStampSelection = () => ({
  stamp_selection_mode: 'none',
  stamp_company: '',
  stamp_type: '',
  stamp_id: null
})

// 权限控制
const canCreateInvoice = computed(() => permissionStore.hasPermission('invoice_applications.create'))
const canEditInvoice = computed(() => permissionStore.hasPermission('invoice_applications.update'))
const canDeleteInvoice = computed(() => permissionStore.hasPermission('invoice_applications.delete'))
const canApproveInvoice = computed(() => permissionStore.hasPermission('invoice_applications.approve'))

// 权限控制：是否可以创建任务（只有审批人可以）
const canCreateTask = ref(false)

const invoiceTypeOptions = [
  { label: '普通发票', value: '普通发票' },
  { label: '增值税专用发票', value: '增值税专用发票' }
]

const invoiceItemTaxRateOptions = [
  { label: '0%', value: 0 },
  { label: '1%', value: 0.01 },
  { label: '2%', value: 0.02 },
  { label: '3%', value: 0.03 },
  { label: '4%', value: 0.04 },
  { label: '5%', value: 0.05 },
  { label: '6%', value: 0.06 },
  { label: '9%', value: 0.09 },
  { label: '13%', value: 0.13 }
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
  project_id: null,
  project_name: '',
  remark: '',
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
  project_name: [{ required: true, message: '请输入项目', trigger: 'blur' }],
  period_year: [{ required: true, message: '请选择所属期年份', trigger: 'change' }],
  period_month: [{ required: true, message: '请选择所属期月份', trigger: 'change' }],
  company_name: [{ required: true, message: '请输入单位名称', trigger: 'blur' }],
  application_date: [{ required: true, message: '请选择申请日期', trigger: 'change' }],
  invoice_method: [{ required: true, message: '请选择开票方式', trigger: 'change' }],
  invoice_type: [{ required: true, message: '请选择开票种类', trigger: 'change' }],
  tax_rate: [{ required: true, message: '请选择税率', trigger: 'change' }],
  amount_excluding_tax: [{ required: true, message: '请输入不含税金额', trigger: 'blur' }],
  invoice_tax_amount: [{ required: true, message: '请输入开票税额', trigger: 'blur' }],
  invoice_amount: [{ required: true, message: '请输入开票金额', trigger: 'blur' }],
  tax_amount: [{ required: true, message: '请输入税金', trigger: 'blur' }],
  invoice_date: [{ required: true, message: '请选择开票日期', trigger: 'change' }]
}

const createItems = ref([
  {
    invoice_project_id: null,
    item_name: '',
    spec_model: '',
    unit: '',
    quantity: null,
    unit_price: null,
    amount: 0,
    tax_rate: 0,
    tax_amount: 0,
    remark: ''
  }
])
const createAttachmentFileList = ref([])
const createDeductionSectionRef = ref(null)
const lastCreateDuplicateWarnKey = ref('')

const addCreateItem = () => {
  createItems.value.push(buildInvoiceItemFromProject(null))
}

const removeCreateItem = (index) => {
  if (createItems.value.length <= 1) return
  createItems.value.splice(index, 1)
}

const beforeCreateAttachmentUpload = (file) => {
  const isLt10M = file.size / 1024 / 1024 < 10
  if (!isLt10M) {
    ElMessage.error('\u6587\u4ef6\u5927\u5c0f\u4e0d\u80fd\u8d85\u8fc7 10MB')
  }
  return isLt10M
}

const validateCreateExtraData = () => {
  if (createNeedsDeductionAmount.value) {
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

    if (Number(createForm.deduction_amount || 0) > Number(createForm.invoice_amount || 0)) {
      ElMessage.warning('扣除额不能大于开票金额')
      return false
    }
  }

  if (!createAttachmentFileList.value.length) {
    ElMessage.warning('\u8bf7\u81f3\u5c11\u4e0a\u4f201\u4e2a\u9644\u4ef6')
    return false
  }

  const oversizeFile = createAttachmentFileList.value.find(file => {
    const size = file.size || file.raw?.size || 0
    return size / 1024 / 1024 >= 10
  })

  if (oversizeFile) {
    ElMessage.error('\u6587\u4ef6\u5927\u5c0f\u4e0d\u80fd\u8d85\u8fc7 10MB')
    return false
  }

  return true
}

const detailDialogVisible = ref(false)
const detailDialogTitle = ref('')
const activeTab = ref('items')
const currentApplication = ref({})
const isEditMode = ref(false)
const submitting = ref(false)

// 盖章方式选择对话框
const submitStampDialogVisible = ref(false)
const submitStampSelectorRef = ref(null)
const submitStampForm = reactive({
  mode: 'submit',
  application_id: null,
  stamp_method: 'online', // 默认线上盖章
  stamp_selection: getDefaultStampSelection()
})

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
  spec_model: '',
  unit: '',
  quantity: null,
  unit_price: null,
  amount: 0,
  tax_rate: 0,
  tax_amount: 0,
  remark: ''
})

const itemFormRules = {
  item_name: [{ required: true, message: '请输入项目名称', trigger: 'blur' }],
  amount: [{ required: true, message: '请输入金额', trigger: 'blur' }]
}

// 发票项目列表
const invoiceProjects = ref([])

// 项目列表
const projects = ref([])

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
  period_year: [{ required: true, message: '请选择年份', trigger: 'change' }],
  period_month: [{ required: true, message: '请选择月份', trigger: 'change' }],
  company_name: [{ required: true, message: '请输入单位名称', trigger: 'blur' }],
  application_date: [{ required: true, message: '请选择申请日期', trigger: 'change' }],
  invoice_method: [{ required: true, message: '请选择开票方式', trigger: 'change' }],
  invoice_type: [{ required: true, message: '请选择开票种类', trigger: 'change' }],
  tax_rate: [{ required: true, message: '请选择税率', trigger: 'change' }],
  amount_excluding_tax: [{ required: true, message: '请输入不含税金额', trigger: 'blur' }],
  invoice_tax_amount: [{ required: true, message: '请输入开票税额', trigger: 'blur' }],
  invoice_amount: [{ required: true, message: '请输入开票金额', trigger: 'blur' }],
  tax_amount: [{ required: true, message: '请输入税金', trigger: 'blur' }],
  invoice_date: [{ required: true, message: '请选择开票日期', trigger: 'change' }]
}

// 开票详情表单ref
const invoiceDetailsFormRef = ref(null)
const invoiceFillDialogVisible = ref(false)
const invoiceFillSaving = ref(false)
const invoiceFillForm = reactive({
  invoice_number: ''
})

// 计算属性
const canEdit = computed(() => {
  return isEditMode.value && 
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

// 有效的项目列表（过滤null和无效项）
const validProjects = computed(() => {
  if (!Array.isArray(projects.value)) return []
  return projects.value.filter(p => p && p.id)
})

const normalizeProjectInvoiceInfos = (project) => {
  if (!project || typeof project !== 'object') {
    return []
  }

  const normalized = Array.isArray(project.invoice_infos)
    ? project.invoice_infos.map(item => ({
      remark: String(item?.remark || '').trim(),
      company_name: String(item?.company_name || item?.invoice_company_name || '').trim(),
      tax_number: String(item?.tax_number || item?.invoice_tax_number || '').trim(),
      company_address: String(item?.company_address || item?.invoice_company_address || '').trim(),
      company_phone: String(item?.company_phone || item?.invoice_company_phone || '').trim(),
      bank_name: String(item?.bank_name || item?.invoice_bank_name || '').trim(),
      bank_account: String(item?.bank_account || item?.invoice_bank_account || '').trim(),
      bank_code: String(item?.bank_code || item?.invoice_bank_code || '').trim()
    })).filter(item => {
      return item.remark ||
        item.company_name ||
        item.tax_number ||
        item.company_address ||
        item.company_phone ||
        item.bank_name ||
        item.bank_account ||
        item.bank_code
    })
    : []

  if (normalized.length > 0) {
    return normalized
  }

  const legacyInvoiceInfo = {
    remark: '默认开票信息',
    company_name: String(project.invoice_company_name || project.invoice_company || project.company_name || '').trim(),
    tax_number: String(project.invoice_tax_number || '').trim(),
    company_address: String(project.invoice_company_address || '').trim(),
    company_phone: String(project.invoice_company_phone || '').trim(),
    bank_name: String(project.invoice_bank_name || '').trim(),
    bank_account: String(project.invoice_bank_account || '').trim(),
    bank_code: String(project.invoice_bank_code || '').trim()
  }

  const hasLegacyValue = [
    legacyInvoiceInfo.company_name,
    legacyInvoiceInfo.tax_number,
    legacyInvoiceInfo.company_address,
    legacyInvoiceInfo.company_phone,
    legacyInvoiceInfo.bank_name,
    legacyInvoiceInfo.bank_account,
    legacyInvoiceInfo.bank_code
  ].some(Boolean)

  return hasLegacyValue ? [legacyInvoiceInfo] : []
}

const currentCreateProject = computed(() => {
  return findProjectById(createForm.project_id) || findProjectByName(createForm.project_name)
})

const createProjectInvoiceInfos = computed(() => {
  return normalizeProjectInvoiceInfos(currentCreateProject.value)
})

const createProjectInvoiceInfoOptions = computed(() => {
  return createProjectInvoiceInfos.value.map((item, index) => ({
    label: item.remark || `开票信息${index + 1}`,
    value: item.remark || `开票信息${index + 1}`
  }))
})

const findProjectById = (projectId) => {
  if (projectId === null || projectId === undefined || projectId === '') return null
  const targetId = String(projectId)
  return validProjects.value.find(project => String(project.id) === targetId) || null
}

const findProjectByName = (projectName) => {
  const targetName = String(projectName || '').trim()
  if (!targetName) return null
  return validProjects.value.find(project => String(project.name || '').trim() === targetName) || null
}

const applyCreateProjectInvoiceInfoByRemark = (project, remark) => {
  const selectedProject = typeof project === 'object' && project !== null
    ? project
    : findProjectById(project)

  if (!selectedProject) {
    if (!createForm.project_name?.trim()) {
      createForm.company_name = ''
      createForm.remark = ''
    }
    return
  }

  const invoiceInfos = normalizeProjectInvoiceInfos(selectedProject)
  if (!invoiceInfos.length) {
    createForm.company_name =
      selectedProject.invoice_company_name ||
      selectedProject.invoice_company ||
      selectedProject.company_name ||
      ''
    createForm.remark = ''
    return
  }

  const selectedRemark = String(remark || '').trim()
  const matchedInvoiceInfo = invoiceInfos.find(item => item.remark === selectedRemark) || invoiceInfos[0]

  createForm.remark = matchedInvoiceInfo.remark || selectedRemark
  createForm.company_name = matchedInvoiceInfo.company_name || ''
}

const fillCreateInvoiceInfoByProject = (project) => {
  const selectedProject = typeof project === 'object' && project !== null
    ? project
    : findProjectById(project)

  if (!selectedProject) {
    if (!createForm.project_name?.trim()) {
      createForm.company_name = ''
    }
    return
  }

  createForm.project_id = selectedProject.id
  createForm.project_name = selectedProject.name || ''
  applyCreateProjectInvoiceInfoByRemark(selectedProject, createForm.remark)
}

const querySearchCreateProjects = (queryString, cb) => {
  const keyword = String(queryString || '').trim().toLowerCase()
  const result = validProjects.value
    .filter(project => {
      const name = String(project.name || '').trim().toLowerCase()
      return !keyword || name.includes(keyword)
    })
    .map(project => ({
      value: project.name || '',
      project
    }))
  cb(result)
}

const handleCreateProjectInput = (value) => {
  createForm.project_name = String(value || '')
  const matchedProject = findProjectByName(createForm.project_name)
  createForm.project_id = matchedProject?.id || null

  if (matchedProject) {
    fillCreateInvoiceInfoByProject(matchedProject)
  } else {
    createForm.remark = ''
    createForm.company_name = ''
  }
}

const handleCreateProjectSelect = (selected) => {
  const selectedProject = selected?.project || findProjectByName(selected?.value)
  if (!selectedProject) {
    createForm.project_name = selected?.value || ''
    createForm.project_id = null
    createForm.remark = ''
    createForm.company_name = ''
    return
  }

  fillCreateInvoiceInfoByProject(selectedProject)
}

const handleCreateInvoiceInfoRemarkChange = (value) => {
  applyCreateProjectInvoiceInfoByRemark(currentCreateProject.value, value)
}

const resetCreateItems = () => {
  createItems.value = [buildInvoiceItemFromProject(null)]
}

const roundAmount = (value) => {
  return Math.round((Number(value || 0) + Number.EPSILON) * 100) / 100
}

const formatInvoiceItemRate = (value) => {
  return `${roundAmount(Number(value || 0) * 100)}%`
}

const syncInvoiceItemAmounts = (item) => {
  if (!item) return

  item.amount = roundAmount(item.amount)
  item.tax_amount = roundAmount(Number(item.amount || 0) * Number(item.tax_rate || 0))
}

const buildInvoiceItemFromProject = (project) => {
  if (!project) {
    return {
      invoice_project_id: null,
      item_name: '',
      spec_model: '',
      unit: '',
      quantity: null,
      unit_price: null,
      amount: 0,
      tax_rate: 0,
      tax_amount: 0,
      remark: ''
    }
  }

  return {
    invoice_project_id: project.id,
    item_name: project.project_name || '',
    spec_model: project.spec_model || '',
    unit: project.unit || '',
    quantity: project.quantity === null || project.quantity === undefined ? null : Number(project.quantity),
    unit_price: project.unit_price === null || project.unit_price === undefined ? null : Number(project.unit_price),
    amount: Number(project.amount || 0),
    tax_rate: Number(project.tax_rate || 0),
    tax_amount: Number(project.tax_amount || 0),
    remark: ''
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
  syncInvoiceItemAmounts(targetItem)
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
  const amountExcludingTax = safeTaxRate > 0
    ? roundAmount(taxableAmount / (1 + safeTaxRate))
    : roundAmount(taxableAmount)
  const invoiceTaxAmount = roundAmount(taxableAmount - amountExcludingTax)

  return {
    amountExcludingTax,
    invoiceTaxAmount,
    taxAmount: invoiceTaxAmount
  }
}

const sumDeductionItems = (items = []) => {
  return roundAmount(items.reduce((sum, item) => sum + Number(item?.amount || 0), 0))
}

const syncCreateCalculatedAmounts = () => {
  createForm.deduction_amount = createNeedsDeductionAmount.value ? sumDeductionItems(createItems.value) : 0

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

// 加载项目列表
const loadProjects = async () => {
  try {
    // 确保账套已选择
    if (!accountSetStore.currentAccountSet?.id) {
      console.warn('未选择账套，无法加载项目列表')
      projects.value = []
      return
    }
    
    const response = await request({
      url: '/projects',
      method: 'get',
      params: {
        current_account_set_id: accountSetStore.currentAccountSet.id,
        all: true
      }
    })
    
    console.log('项目列表响应:', response)
    
    if (response.success) {
      // 处理可能的数据结构：response.data 或 response.data.data
      const projectData = response.data?.data || response.data || []
      console.log('解析后的项目数据:', projectData)
      projects.value = Array.isArray(projectData) ? projectData : []
    } else {
      projects.value = []
    }
  } catch (error) {
    console.error('加载项目列表失败', error)
    projects.value = []
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
  createForm.project_id = null
  createForm.project_name = ''
  createForm.remark = ''
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
  createAttachmentFileList.value = []
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
    syncCreateCalculatedAmounts()
    const projectName = String(createForm.project_name || '').trim()
    createForm.project_name = projectName
    createForm.task_name = (projectName || '开票') + `${createForm.year}年${createForm.month}月`

    await createFormRef.value.validate()

    if (!validateCreateExtraData()) {
      return
    }

    creating.value = true
    const response = await createInvoiceApplication(createForm)

    if (!response?.success) {
      ElMessage.error(response?.message || '创建失败')
      return
    }

    const createdId = response.data?.id

    if (!createdId) {
      throw new Error('创建成功但未返回任务ID')
    }

    try {
      if (createNeedsDeductionAmount.value) {
        for (const item of createItems.value) {
          const itemRes = await addInvoiceItem(createdId, {
            invoice_project_id: item.invoice_project_id,
            item_name: item.item_name,
            spec_model: item.spec_model,
            unit: item.unit,
            quantity: item.quantity,
            unit_price: item.unit_price,
            amount: Number(item.amount || 0),
            tax_rate: Number(item.tax_rate || 0),
            tax_amount: Number(item.tax_amount || 0),
            remark: item.remark || ''
          })

          if (!itemRes?.success) {
            throw new Error(itemRes?.message || '扣除明细保存失败')
          }
        }
      }

      const attachmentFiles = createAttachmentFileList.value
        .map(file => file.raw || file)
        .filter(Boolean)

      for (const file of attachmentFiles) {
        const uploadRes = await uploadAttachment(createdId, file)
        if (!uploadRes?.success) {
          throw new Error(uploadRes?.message || '附件上传失败')
        }
      }
    } catch (extraError) {
      ElMessage.error('开票任务已创建，但扣除明细或附件保存失败，请进入详情补充')
      createDialogVisible.value = false
      loadData()
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
    }
  } catch (error) {
    console.error('加载详情失败', error)
    ElMessage.error('加载详情失败')
  }
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
    syncInvoiceDetailsCalculatedAmounts()
    // 校验表单
    if (invoiceDetailsFormRef.value) {
      await invoiceDetailsFormRef.value.validate()
    }

    const response = await request({
      url: `/invoice-applications/${currentApplication.value.id}/update-invoice-details`,
      method: 'put',
      data: invoiceDetailsForm
    })

    if (response.success) {
      ElMessage.success('保存成功')
      await loadApplicationDetail(currentApplication.value.id)
    }
  } catch (error) {
    if (error === 'cancel') return
    console.error('保存失败', error)
    ElMessage.error(error.response?.data?.message || '保存失败')
  }
}

// 一键填写开票详情测试数据
const fillInvoiceTestData = () => {
  const today = new Date().toISOString().split('T')[0]
  const currentYear = new Date().getFullYear()
  const currentMonth = new Date().getMonth() + 1
  
  invoiceDetailsForm.period_year = currentYear
  invoiceDetailsForm.period_month = currentMonth
  invoiceDetailsForm.company_name = '鄂尔多斯市汇邦人力资源有限责任公司'
  invoiceDetailsForm.application_date = today
  invoiceDetailsForm.invoice_method = 'full'
  invoiceDetailsForm.invoice_type = '普通发票'
  invoiceDetailsForm.tax_rate = 0.06
  invoiceDetailsForm.invoice_amount = 5000.00
  invoiceDetailsForm.invoice_date = today
  invoiceDetailsForm.invoice_remark = '人力资源服务费'
  syncInvoiceDetailsCalculatedAmounts()
  
  ElMessage.success('开票详情测试数据已填充')
}

// 详情对话框关闭
const handleDetailDialogClose = () => {
  currentApplication.value = {}
  activeTab.value = 'items'
}

const openInvoiceFillDialog = () => {
  if (!currentApplication.value?.id) return
  invoiceFillForm.invoice_number = currentApplication.value.invoice_number || ''
  invoiceFillDialogVisible.value = true
}

const handleOpenInvoiceFill = async (row) => {
  isEditMode.value = false
  await loadApplicationDetail(row.id)
  detailDialogTitle.value = `发票申请详情 - ${currentApplication.value.application_no}`
  activeTab.value = 'invoice_details'
  detailDialogVisible.value = true
  openInvoiceFillDialog()
}

const handleSaveInvoiceNumber = async () => {
  const invoiceNumber = invoiceFillForm.invoice_number?.trim()
  if (!invoiceNumber) {
    ElMessage.warning('请输入发票号码')
    return
  }

  invoiceFillSaving.value = true
  try {
    const response = await fillInvoiceApplicationNumber(currentApplication.value.id, {
      invoice_number: invoiceNumber
    })

    if (response.success) {
      await loadApplicationDetail(currentApplication.value.id)
      await loadData()

      if (currentApplication.value.is_completed) {
        ElMessage.success('发票信息已填写完成')
        invoiceFillDialogVisible.value = false
      } else {
        ElMessage.success('发票号码已保存')
      }
    }
  } catch (error) {
    console.error('保存发票号码失败', error)
    ElMessage.error(error.response?.data?.message || '保存发票号码失败')
  } finally {
    invoiceFillSaving.value = false
  }
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
  itemForm.spec_model = row.spec_model || ''
  itemForm.unit = row.unit || ''
  itemForm.quantity = row.quantity === null || row.quantity === undefined ? null : Number(row.quantity)
  itemForm.unit_price = row.unit_price === null || row.unit_price === undefined ? null : Number(row.unit_price)
  itemForm.amount = row.amount
  itemForm.tax_rate = Number(row.tax_rate || 0)
  itemForm.tax_amount = Number(row.tax_amount || 0)
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
  itemForm.spec_model = ''
  itemForm.unit = ''
  itemForm.quantity = null
  itemForm.unit_price = null
  itemForm.amount = 0
  itemForm.tax_rate = 0
  itemForm.tax_amount = 0
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

// 打开盖章方式选择对话框
const openSubmitStampDialog = () => {
  // 先进行验证
  // 1. 验证开票详情是否填写
  const hasInvoiceDetails = invoiceDetailsForm.period_year && 
                             invoiceDetailsForm.period_month && 
                             invoiceDetailsForm.company_name
  
  if (!hasInvoiceDetails) {
    ElMessage.warning('请先填写开票详情（所属期-年份、所属期-月份、单位名称）')
    return
  }
  
  // 2. 检查开票方式是否选择
  const invoiceMethod = invoiceDetailsForm.invoice_method
  
  if (!invoiceMethod) {
    ElMessage.warning('请选择开票方式')
    return
  }
  
  submitStampForm.mode = 'submit'
  submitStampForm.application_id = currentApplication.value.id
  submitStampForm.stamp_method = 'online'
  submitStampForm.stamp_selection = getDefaultStampSelection()
  submitStampDialogVisible.value = true
}

// 提交审批
const handleSubmit = async () => {
  try {
    // 1. 验证开票详情是否填写
    const hasInvoiceDetails = invoiceDetailsForm.period_year && 
                               invoiceDetailsForm.period_month && 
                               invoiceDetailsForm.company_name
    
    if (!hasInvoiceDetails) {
      ElMessage.warning('请先填写开票详情（所属期-年份、所属期-月份、单位名称）')
      return
    }
    
    // 2. 检查开票方式是否选择
    const invoiceMethod = invoiceDetailsForm.invoice_method
    
    if (!invoiceMethod) {
      ElMessage.warning('请选择开票方式')
      return
    }
    
    await autoGenerateDeductionExcelIfNeeded()

    const stampResult = submitStampSelectorRef.value?.validate?.()
    if (stampResult && !stampResult.valid) {
      ElMessage.warning(stampResult.message)
      return
    }

    submitting.value = true
    const response = await submitInvoiceApplication(currentApplication.value.id, {
      stamp_method: submitStampForm.stamp_method,
      ...(stampResult?.value || submitStampForm.stamp_selection)
    })

    if (response.success) {
      ElMessage.success(response.message || '提交成功')
      submitStampDialogVisible.value = false
      detailDialogVisible.value = false
      submitStampForm.stamp_method = 'online'
      submitStampForm.stamp_selection = getDefaultStampSelection()
      loadData()
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('提交失败', error)
      ElMessage.error(error.response?.data?.message || '提交失败')
    }
  } finally {
    submitting.value = false
  }
}

const handleConfirmSubmitStamp = async () => {
  if (submitStampForm.mode === 'resubmit') {
    await handleResubmitConfirm()
    return
  }

  await handleSubmit()
}

const handleSubmitFromList = async (row) => {
  await loadApplicationDetail(row.id)
  openSubmitStampDialog()
}

// 重新发起
const handleResubmit = async (row) => {
  submitStampForm.mode = 'resubmit'
  submitStampForm.application_id = row.id
  submitStampForm.stamp_method = 'online'
  submitStampForm.stamp_selection = getDefaultStampSelection()
  submitStampDialogVisible.value = true
}

const handleResubmitConfirm = async () => {
  try {
    const stampResult = submitStampSelectorRef.value?.validate?.()
    if (stampResult && !stampResult.valid) {
      ElMessage.warning(stampResult.message)
      return
    }

    submitting.value = true
    const response = await resubmitInvoiceApplication(submitStampForm.application_id, {
      stamp_method: submitStampForm.stamp_method,
      ...(stampResult?.value || submitStampForm.stamp_selection)
    })

    if (response.success) {
      ElMessage.success(response.message || '重新发起成功')
      submitStampDialogVisible.value = false
      submitStampForm.stamp_method = 'online'
      submitStampForm.stamp_selection = getDefaultStampSelection()
      loadData()
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('重新发起失败', error)
      ElMessage.error(error.response?.data?.message || '重新发起失败')
    }
  } finally {
    submitting.value = false
  }
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

// 检查创建权限（只有审批人可以创建任务）
const checkCreatePermission = async () => {
  try {
    const accountSetId = accountSetStore.currentAccountSetId
    if (!accountSetId) {
      canCreateTask.value = false
      return
    }
    
    const response = await request({
      url: '/invoice-applications/check-permission/create',
      method: 'get',
      params: { account_set_id: accountSetId }
    })
    
    if (response.success) {
      // 只有后续审批节点人员可以创建任务
      canCreateTask.value = response.has_access
      console.log('创建权限检查结果:', response)
    } else {
      canCreateTask.value = false
    }
  } catch (error) {
    console.error('检查创建权限失败:', error)
    canCreateTask.value = false
  }
}

const handleRouteInvoiceFill = async () => {
  const action = route.query.action
  const invoiceId = route.query.id
  if (action !== 'fill_invoice_number' || !invoiceId) {
    return
  }

  try {
    isEditMode.value = false
    await loadApplicationDetail(invoiceId)
    detailDialogTitle.value = `发票申请详情 - ${currentApplication.value.application_no}`
    activeTab.value = 'invoice_details'
    detailDialogVisible.value = true
    openInvoiceFillDialog()
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

// 监听账套变化
watch(
  () => accountSetStore.currentAccountSet,
  (newAccountSet) => {
    if (newAccountSet?.id) {
      loadProjects()
    }
  }
)

// 监听开票方式变化，自动清空扣除额（如果不是全额或差额）
watch(
  () => createForm.invoice_method,
  async (newMethod, oldMethod) => {
    if (newMethod !== 'full' && newMethod !== 'diff') {
      createForm.deduction_amount = 0
      resetCreateItems()
      syncCreateCalculatedAmounts()
      return
    }

    if (oldMethod !== 'full' && oldMethod !== 'diff') {
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
    createItems.value.forEach(item => syncInvoiceItemAmounts(item))
    syncCreateCalculatedAmounts()
  },
  { deep: true }
)

watch(
  () => [itemForm.quantity, itemForm.unit_price, itemForm.amount, itemForm.tax_rate],
  () => {
    syncInvoiceItemAmounts(itemForm)
  }
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
  loadInvoiceProjects()
  loadProjects()
  checkCreatePermission()
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
.attachments-section,
.approval-section {
  padding: 20px;
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
