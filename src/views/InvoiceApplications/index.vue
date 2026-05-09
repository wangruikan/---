<template>
  <div class="invoice-applications-container">
    <el-card>
      <template #header>
        <div class="card-header">
          <span class="title">发票申请管理</span>
          <!-- 创建按钮 - 只有审批人（第2、3、4节点）可见 -->
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
        <el-table-column label="操作" width="220" fixed="right" align="center">
          <template #default="{ row }">
            <el-button type="primary" link @click="handleDetail(row)">查看</el-button>
            <el-button 
              v-if="canEditInvoice && (!row.approval_status || row.approval_status === 'rejected')" 
              type="primary" 
              link 
              @click="handleEdit(row)"
            >
              编辑
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
      width="600px"
      @close="handleCreateDialogClose"
    >
      <el-form
        ref="createFormRef"
        :model="createForm"
        :rules="createFormRules"
        label-width="100px"
      >
        <el-form-item label="任务名称" prop="task_name">
          <el-input
            v-model="createForm.task_name"
            placeholder="请输入任务名称（如：11月工资发票）"
            maxlength="100"
            show-word-limit
          />
        </el-form-item>
        <el-form-item label="年份" prop="year">
          <el-select v-model="createForm.year" placeholder="请选择年份" style="width: 100%">
            <el-option
              v-for="year in years"
              :key="year"
              :label="`${year}年`"
              :value="year"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="月份" prop="month">
          <el-select v-model="createForm.month" placeholder="请选择月份" style="width: 100%">
            <el-option
              v-for="month in 12"
              :key="month"
              :label="`${month}月`"
              :value="month"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="项目" prop="project_id">
          <el-select
            v-model="createForm.project_id"
            placeholder="请选择项目"
            filterable
            style="width: 100%"
          >
            <el-option
              v-for="project in validProjects"
              :key="project.id"
              :label="project.name"
              :value="project.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="备注">
          <el-input
            v-model="createForm.remark"
            type="textarea"
            :rows="3"
            placeholder="请输入备注（非必填）"
            maxlength="500"
            show-word-limit
          />
        </el-form-item>
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
              <el-table-column prop="project_name" label="项目" width="150" />
              <el-table-column prop="item_name" label="名称" width="150" />
              <el-table-column prop="amount" label="金额" width="120" align="right">
                <template #default="{ row }">
                  ¥{{ Number(row.amount).toFixed(2) }}
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

            <!-- 生成Excel按钮 -->
            <div class="excel-section" v-if="currentApplication.items && currentApplication.items.length > 0">
              <el-button 
                type="success" 
                @click="handleGenerateExcel" 
                :loading="generatingExcel"
              >
                <el-icon><Document /></el-icon>
                生成扣除明细表（Excel）
              </el-button>
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
                :action="uploadAction"
                :headers="uploadHeaders"
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
                      <el-option label="全额" value="full" />
                      <el-option label="差额" value="diff" />
                      <el-option label="无" value="none" />
                    </el-select>
                  </el-form-item>
                </el-col>

                <!-- 开票种类 -->
                <el-col :span="12">
                  <el-form-item label="开票种类" prop="invoice_type">
                    <el-input v-model="invoiceDetailsForm.invoice_type" placeholder="默认：普票" />
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
                      :disabled="!needsDeductionAmount"
                      style="width: 100%"
                      placeholder="仅全额和差额需要填写"
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

                <!-- 不含税金额 -->
                <el-col :span="12">
                  <el-form-item label="不含税金额" prop="amount_excluding_tax">
                    <el-input-number
                      v-model="invoiceDetailsForm.amount_excluding_tax"
                      :precision="2"
                      :min="0"
                      :controls="false"
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
                      style="width: 100%"
                    />
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

                <!-- 税金 -->
                <el-col :span="12">
                  <el-form-item label="税金" prop="tax_amount">
                    <el-input-number
                      v-model="invoiceDetailsForm.tax_amount"
                      :precision="2"
                      :min="0"
                      :controls="false"
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
                  <el-form-item label="开票人" prop="invoicer">
                    <el-input v-model="invoiceDetailsForm.invoicer" placeholder="请输入开票人" />
                  </el-form-item>
                </el-col>

                <!-- 发票号码 -->
                <el-col :span="12">
                  <el-form-item label="发票号码" prop="invoice_number">
                    <el-input v-model="invoiceDetailsForm.invoice_number" placeholder="请输入发票号码" />
                  </el-form-item>
                </el-col>

                <!-- 是否完成 -->
                <el-col :span="12">
                  <el-form-item label="是否完成">
                    <el-switch v-model="invoiceDetailsForm.is_completed" />
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
          v-if="canSubmit" 
          type="primary" 
          @click="openSubmitStampDialog" 
          :loading="submitting"
        >
          提交审批
        </el-button>
      </template>
    </el-dialog>

    <!-- 盖章方式选择对话框 -->
    <el-dialog
      v-model="submitStampDialogVisible"
      title="提交审批"
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
      </el-form>
      <template #footer>
        <el-button @click="submitStampDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitting">
          确认提交
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
        <el-form-item label="项目" prop="invoice_project_id">
          <el-select 
            v-model="itemForm.invoice_project_id" 
            placeholder="请选择项目" 
            style="width: 100%"
            @change="handleProjectChange"
          >
            <el-option
              v-for="project in invoiceProjects"
              :key="project.id"
              :label="project.project_name"
              :value="project.id"
            />
          </el-select>
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
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Document, Upload, Download } from '@element-plus/icons-vue'
import {
  getInvoiceApplications,
  getInvoiceApplicationDetail,
  createInvoiceApplication,
  deleteInvoiceApplication,
  addInvoiceItem,
  updateInvoiceItem,
  deleteInvoiceItem,
  generateExcel,
  deleteAttachment,
  submitInvoiceApplication,
  resubmitInvoiceApplication
} from '@/api/invoiceApplication'
import { getAllInvoiceProjects } from '@/api/invoiceProject'
import request from '@/api/request'
import { useAccountSetStore } from '@/stores/accountSet'
import { usePermissionStore } from '@/stores/permission'
import * as XLSX from 'xlsx'

// 账套store
const accountSetStore = useAccountSetStore()
const permissionStore = usePermissionStore()

// 权限控制
const canCreateInvoice = computed(() => permissionStore.hasPermission('invoice_applications.create'))
const canEditInvoice = computed(() => permissionStore.hasPermission('invoice_applications.update'))
const canDeleteInvoice = computed(() => permissionStore.hasPermission('invoice_applications.delete'))
const canApproveInvoice = computed(() => permissionStore.hasPermission('invoice_applications.approve'))

// 权限控制：是否可以创建任务（只有审批人可以）
const canCreateTask = ref(false)

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
  month: new Date().getMonth() + 1,
  project_id: null,
  remark: ''
})

const createFormRules = {
  task_name: [
    { required: true, message: '请输入任务名称', trigger: 'blur' },
    { max: 100, message: '任务名称不能超过100个字符', trigger: 'blur' }
  ],
  year: [{ required: true, message: '请选择年份', trigger: 'change' }],
  month: [{ required: true, message: '请选择月份', trigger: 'change' }],
  project_id: [{ required: true, message: '请选择项目', trigger: 'change' }]
}

// 详情对话框
const detailDialogVisible = ref(false)
const detailDialogTitle = ref('')
const activeTab = ref('items')
const currentApplication = ref({})
const isEditMode = ref(false)
const submitting = ref(false)

// 盖章方式选择对话框
const submitStampDialogVisible = ref(false)
const submitStampForm = reactive({
  stamp_method: 'online' // 默认线上盖章
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
  amount: 0,
  remark: ''
})

const itemFormRules = {
  invoice_project_id: [{ required: true, message: '请选择项目', trigger: 'change' }],
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
  invoice_type: '普票',
  deduction_amount: 0,
  tax_rate: 0,
  amount_excluding_tax: 0,
  invoice_tax_amount: 0,
  invoice_amount: 0,
  tax_amount: 0,
  invoice_date: null,
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
  invoice_type: [{ required: true, message: '请输入开票种类', trigger: 'blur' }],
  tax_rate: [{ required: true, message: '请选择税率', trigger: 'change' }],
  amount_excluding_tax: [{ required: true, message: '请输入不含税金额', trigger: 'blur' }],
  invoice_tax_amount: [{ required: true, message: '请输入开票税额', trigger: 'blur' }],
  invoice_amount: [{ required: true, message: '请输入开票金额', trigger: 'blur' }],
  tax_amount: [{ required: true, message: '请输入税金', trigger: 'blur' }],
  invoice_date: [{ required: true, message: '请选择开票日期', trigger: 'change' }],
  invoicer: [{ required: true, message: '请输入开票人', trigger: 'blur' }],
  invoice_number: [{ required: true, message: '请输入发票号码', trigger: 'blur' }]
}

// 开票详情表单ref
const invoiceDetailsFormRef = ref(null)

// Excel生成
const generatingExcel = ref(false)

// 上传配置
const uploadAction = computed(() => {
  const baseURL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'
  return `${baseURL}/api/invoice-applications/${currentApplication.value.id}/attachments`
})

const uploadHeaders = computed(() => {
  const token = localStorage.getItem('token')
  return {
    'Authorization': `Bearer ${token}`
  }
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

const canSubmit = computed(() => {
  // 与后端逻辑保持一致：无审批状态或已驳回状态，且有明细项和附件
  const hasNoApprovalOrRejected = !currentApplication.value.approval_status || 
                                   currentApplication.value.approval_status === 'rejected'
  return hasNoApprovalOrRejected &&
         currentApplication.value.items?.length > 0 &&
         currentApplication.value.attachments?.length > 0
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
        account_set_id: accountSetStore.currentAccountSet.id
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
        'full': '全额',
        'diff': '差额',
        'partial': '缺额', // 兼容旧数据
        'none': '无' // 兼容旧数据
      }
      
      return [
        index + 1, // 序号
        `${item.period_year || item.year}-${String(item.period_month || item.month).padStart(2, '0')}`, // 所属期
        item.company_name || '', // 单位名称
        item.application_date || '', // 申请日期
        invoiceMethodMap[item.invoice_method] || '', // 开票方式
        item.invoice_type || '普票', // 开票种类
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
const handleCreate = () => {
  createForm.year = currentYear
  createForm.month = new Date().getMonth() + 1
  createDialogVisible.value = true
}

// 确认创建
const handleConfirmCreate = async () => {
  try {
    await createFormRef.value.validate()
    
    creating.value = true
    const response = await createInvoiceApplication(createForm)

    if (response.success) {
      ElMessage.success(response.message || '创建成功')
      createDialogVisible.value = false
      loadData()
      // 直接打开编辑
      handleEdit(response.data)
    }
  } catch (error) {
    if (error !== false) {
      console.error('创建失败', error)
      ElMessage.error(error.response?.data?.message || '创建失败')
    }
  } finally {
    creating.value = false
  }
}

// 创建对话框关闭
const handleCreateDialogClose = () => {
  createFormRef.value?.resetFields()
  createForm.task_name = ''
  createForm.year = currentYear
  createForm.month = new Date().getMonth() + 1
  createForm.project_id = null
  createForm.remark = ''
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
  invoiceDetailsForm.invoice_type = data.invoice_type || '普票'
  invoiceDetailsForm.deduction_amount = data.deduction_amount || 0
  invoiceDetailsForm.tax_rate = data.tax_rate || 0
  invoiceDetailsForm.amount_excluding_tax = data.amount_excluding_tax || 0
  invoiceDetailsForm.invoice_tax_amount = data.invoice_tax_amount || 0
  invoiceDetailsForm.invoice_amount = data.invoice_amount || 0
  invoiceDetailsForm.tax_amount = data.tax_amount || 0
  invoiceDetailsForm.invoice_date = data.invoice_date
  invoiceDetailsForm.is_completed = data.is_completed || false
  invoiceDetailsForm.invoicer = data.invoicer || ''
  invoiceDetailsForm.invoice_number = data.invoice_number || ''
  invoiceDetailsForm.invoice_remark = data.invoice_remark || ''
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
      data: invoiceDetailsForm
    })

    if (response.success) {
      ElMessage.success('保存成功')
      await loadApplicationDetail(currentApplication.value.id)
    }
  } catch (error) {
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
  invoiceDetailsForm.invoice_method = '电子发票'
  invoiceDetailsForm.invoice_type = '普票'
  invoiceDetailsForm.deduction_amount = 5000.00
  invoiceDetailsForm.tax_rate = 0.06
  invoiceDetailsForm.amount_excluding_tax = 4716.98
  invoiceDetailsForm.invoice_tax_amount = 5000.00
  invoiceDetailsForm.invoice_amount = 5000.00
  invoiceDetailsForm.tax_amount = 283.02
  invoiceDetailsForm.invoice_date = today
  invoiceDetailsForm.is_completed = false
  invoiceDetailsForm.invoicer = '张三'
  invoiceDetailsForm.invoice_number = 'FP' + Date.now()
  invoiceDetailsForm.invoice_remark = '人力资源服务费'
  
  ElMessage.success('开票详情测试数据已填充')
}

// 详情对话框关闭
const handleDetailDialogClose = () => {
  currentApplication.value = {}
  activeTab.value = 'items'
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
  itemForm.amount = row.amount
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
  itemForm.amount = 0
  itemForm.remark = ''
  itemFormRef.value?.clearValidate()
}

// 明细对话框关闭
const handleItemDialogClose = () => {
  resetItemForm()
}

// 项目变更
const handleProjectChange = (projectId) => {
  const project = invoiceProjects.value.find(p => p.id === projectId)
  if (project && !itemForm.item_name) {
    // 可以设置默认名称
  }
}

// 生成Excel
const handleGenerateExcel = async () => {
  try {
    generatingExcel.value = true
    const response = await generateExcel(currentApplication.value.id)
    
    if (response.success) {
      ElMessage.success(response.message || '生成成功，已自动添加到附件列表')
      // 刷新数据以获取新生成的文件
      await loadApplicationDetail(currentApplication.value.id)
      // 自动切换到附件标签页
      activeTab.value = 'attachments'
    }
  } catch (error) {
    console.error('生成Excel失败', error)
    ElMessage.error(error.response?.data?.message || '生成失败')
  } finally {
    generatingExcel.value = false
  }
}

// 上传前验证
const beforeUpload = (file) => {
  const isLt10M = file.size / 1024 / 1024 < 10
  if (!isLt10M) {
    ElMessage.error('文件大小不能超过 10MB')
  }
  return isLt10M
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
const handleDownload = (attachment) => {
  const baseURL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'
  window.open(`${baseURL}/storage/${attachment.path}`, '_blank')
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
  
  // 3. 如果选择了全额或差额，必须生成扣除明细
  if (invoiceMethod === 'full' || invoiceMethod === 'diff') {
    const hasDeductionAttachment = currentApplication.value.attachments?.some(att => 
      att.filename && att.filename.includes('扣除明细')
    )
    
    if (!hasDeductionAttachment) {
      const methodText = invoiceMethod === 'full' ? '全额' : '差额'
      ElMessage.warning(`您选择了"${methodText}"开票方式，请先生成扣除明细表`)
      return
    }
  }
  
  // 验证通过，打开盖章方式选择对话框
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
    
    // 3. 如果选择了全额或差额，必须生成扣除明细
    if (invoiceMethod === 'full' || invoiceMethod === 'diff') {
      // 检查是否有扣除明细附件
      const hasDeductionAttachment = currentApplication.value.attachments?.some(att => 
        att.filename && att.filename.includes('扣除明细')
      )
      
      if (!hasDeductionAttachment) {
        const methodText = invoiceMethod === 'full' ? '全额' : '差额'
        ElMessage.warning(`您选择了"${methodText}"开票方式，请先生成扣除明细表`)
        return
      }
    }

    submitting.value = true
    const response = await submitInvoiceApplication(currentApplication.value.id, {
      stamp_method: submitStampForm.stamp_method
    })

    if (response.success) {
      ElMessage.success(response.message || '提交成功')
      submitStampDialogVisible.value = false
      detailDialogVisible.value = false
      // 重置盖章方式为默认值
      submitStampForm.stamp_method = 'online'
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

// 重新发起
const handleResubmit = async (row) => {
  try {
    await ElMessageBox.confirm(
      '将基于此红冲申请创建新的申请并发起审批流程，确定要继续吗？',
      '重新发起申请',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    const response = await resubmitInvoiceApplication(row.id)

    if (response.success) {
      ElMessage.success(response.message || '重新发起成功')
      loadData()
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('重新发起失败', error)
      ElMessage.error(error.response?.data?.message || '重新发起失败')
    }
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
      // 只有审批级别2、3、4可以创建任务
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
  () => invoiceDetailsForm.invoice_method,
  (newMethod) => {
    if (newMethod !== 'full' && newMethod !== 'diff') {
      invoiceDetailsForm.deduction_amount = 0
    }
  }
)

// 初始化
onMounted(() => {
  loadData()
  loadInvoiceProjects()
  loadProjects()
  checkCreatePermission()
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
