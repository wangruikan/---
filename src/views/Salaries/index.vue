<template>
  <div class="salaries-page">
    <NoAccountSetTip v-if="!isAdmin && !currentAccountSetId" />
    <div v-else>
    <div class="page-header">
        <h1>工资管理</h1>
      <div class="header-actions">
          <el-button type="primary" @click="handleCreate">
            <el-icon><Plus /></el-icon>
            生成工资表
        </el-button>
      </div>
    </div>
    
      <!-- 筛选条件 -->
      <el-card shadow="never" style="margin-bottom: 20px;">
        <el-form :inline="true" :model="searchForm" class="search-form">
          <el-form-item label="工资期间">
            <el-date-picker
              v-model="searchForm.month"
              type="month"
              placeholder="选择月份"
              format="YYYY-MM"
              value-format="YYYY-MM"
              clearable
            />
          </el-form-item>

            <el-form-item label="项目">
            <el-select v-model="searchForm.project_id" placeholder="请选择项目" clearable style="width: 200px;">
                <el-option
                  v-for="project in projects"
                  :key="project.id"
                  :label="project.name"
                  :value="project.id"
                />
              </el-select>
            </el-form-item>
            
            <el-form-item label="状态">
            <el-select v-model="searchForm.status" placeholder="请选择状态" clearable style="width: 180px;">
              <el-option label="全部状态" :value="null" />
                <el-option label="草稿" value="draft" />
                <el-option label="已提交" value="submitted" />
                <el-option label="已审批" value="approved" />
              <el-option label="已发放" value="paid" />
                <el-option label="已拒绝" value="rejected" />
              </el-select>
            </el-form-item>
            
            <el-form-item>
            <el-button type="primary" @click="handleSearch">查询</el-button>
            <el-button @click="handleReset">重置</el-button>
            </el-form-item>
          </el-form>
      </el-card>

      <!-- 工资表列表（按项目+期间） -->
      <el-card shadow="never">
        <template #header>
          <div class="card-header">
            <span class="title">工资表列表</span>
            <el-button type="text" @click="handleSearch">
              <el-icon><Refresh /></el-icon>
              刷新
            </el-button>
        </div>
        </template>
        
        <el-table
          :data="tableData"
          border
          stripe
          v-loading="loading"
          style="width: 100%"
        >
          <el-table-column prop="month" label="工资期间" width="120" />
          <el-table-column label="工资周期" width="150" align="center">
            <template #default="scope">
              <span v-if="scope.row.period_start && scope.row.period_end">
                {{ scope.row.period_start }} - {{ scope.row.period_end }}
              </span>
              <span v-else>-</span>
            </template>
          </el-table-column>
          <el-table-column prop="project_name" label="项目名称" min-width="200" show-overflow-tooltip>
            <template #default="scope">
              <el-tooltip :content="scope.row.project_name" placement="top" :disabled="scope.row.project_name.length <= 20">
                <span>{{ scope.row.project_name.length > 20 ? scope.row.project_name.substring(0, 20) + '...' : scope.row.project_name }}</span>
              </el-tooltip>
            </template>
          </el-table-column>
          <el-table-column prop="employee_count" label="员工人数" width="100" align="center" />
          <el-table-column prop="total_gross_salary" label="应发合计" width="140" align="right">
            <template #default="scope">
              <span style="color: #409EFF; font-weight: bold;">{{ formatMoney(scope.row.total_gross_salary) }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="total_net_salary" label="实发合计" width="140" align="right">
            <template #default="scope">
              <span style="color: #67C23A; font-weight: bold;">{{ formatMoney(scope.row.total_net_salary) }}</span>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="100">
            <template #default="scope">
              <el-tag :type="getStatusType(scope.row.has_approval ? scope.row.approval_status : 'draft')">
                {{ getStatusText(scope.row.has_approval ? scope.row.approval_status : 'draft') }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="工资依据" width="150" align="center">
            <template #default="scope">
              <el-button link type="primary" @click="handleViewSalaryBasis(scope.row)">
                查看依据
              </el-button>
            </template>
          </el-table-column>
          <el-table-column prop="created_at" label="创建时间" width="160">
            <template #default="scope">
              {{ formatDateTime(scope.row.created_at) }}
            </template>
          </el-table-column>
          <el-table-column label="操作" width="300" fixed="right">
            <template #default="scope">
              <el-button link type="primary" size="small" @click="handleView(scope.row)">
                查看明细
              </el-button>
              
              <!-- 未提交审批：显示提交审批按钮 -->
              <el-button 
                v-if="!scope.row.has_approval" 
                link 
                type="success" 
                size="small" 
                @click="handleSubmit(scope.row)"
              >
                提交审批
              </el-button>
              
              <!-- 已提交审批但待审批：显示审批状态 -->
              <el-tag v-if="scope.row.has_approval && scope.row.approval_status === 'pending'" type="warning" size="small">
                待审批
              </el-tag>
              
              <!-- 审批通过：显示已通过标签和发起付款按钮 -->
              <template v-if="scope.row.has_approval && scope.row.approval_status === 'approved'">
                <el-tag type="success" size="small" style="margin-right: 8px">
                  已审批
                </el-tag>
                
                <!-- 未发起付款：显示发起付款按钮 -->
              <el-button 
                  v-if="!scope.row.has_payment_request"
                  link 
                  type="primary" 
                size="small" 
                  @click="handleCreatePayment(scope.row)"
              >
                  发起付款
              </el-button>
                
                <!-- 已发起付款：显示付款状态 -->
                <el-tag 
                  v-if="scope.row.has_payment_request && scope.row.payment_request_status === 'pending'" 
                  type="warning" 
                  size="small"
                >
                  付款待审批
                </el-tag>
                <el-tag 
                  v-if="scope.row.has_payment_request && scope.row.payment_request_status === 'approved'" 
                type="success" 
                size="small" 
                >
                  付款已批准
                </el-tag>
                <el-tag 
                  v-if="scope.row.has_payment_request && scope.row.payment_request_status === 'rejected'" 
                  type="danger" 
                  size="small"
                >
                  付款已驳回
                </el-tag>
                <el-tag 
                  v-if="scope.row.has_payment_request && scope.row.payment_request_status === 'paid'" 
                type="info" 
                size="small" 
                >
                  已付款
                </el-tag>
              </template>
              
              <!-- 审批拒绝：显示已拒绝标签和重新发起按钮 -->
              <template v-if="scope.row.has_approval && scope.row.approval_status === 'rejected'">
                <el-tag type="danger" size="small" style="margin-right: 8px">
                  已拒绝
                </el-tag>
                <ResubmitButton 
                  :record="scope.row" 
                  business-type="工资表审批"
                  @success="fetchData"
                />
              </template>
              
              <!-- 删除按钮：只有未提交审批时才能删除 -->
              <el-button 
                v-if="!scope.row.has_approval" 
                link 
                type="danger" 
                size="small" 
                @click="handleDelete(scope.row)"
              >
                删除
              </el-button>
            </template>
          </el-table-column>
        </el-table>
        
        <!-- 分页 -->
          <el-pagination
          v-model:current-page="pagination.page"
            v-model:page-size="pagination.pageSize"
            :total="pagination.total"
          :page-sizes="[10, 20, 50, 100]"
            layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleSearch"
          @current-change="handleSearch"
          style="margin-top: 20px; justify-content: flex-end;"
          />
      </el-card>
    
      <!-- 提交审批对话框 -->
    <el-dialog
        v-model="submitApprovalDialogVisible"
        title="提交工资表审批"
        width="500px"
      >
        <el-form :model="submitApprovalForm" label-width="100px">
          <el-form-item label="项目名称">
            <el-input v-model="submitApprovalForm.project_name" disabled />
          </el-form-item>
          
          <el-form-item label="工资期间">
            <el-input v-model="submitApprovalForm.month" disabled />
          </el-form-item>

          <el-form-item label="盖章方式" required>
            <el-radio-group v-model="submitApprovalForm.approval_type">
              <el-radio label="online">线上盖章</el-radio>
              <el-radio label="offline">线下盖章</el-radio>
            </el-radio-group>
            <div style="color: #909399; font-size: 12px; margin-top: 5px;">
              线上盖章：系统自动在PDF上添加印章；线下盖章：需要手动在纸质文件上盖章
            </div>
          </el-form-item>

          <el-form-item label="备注">
            <el-input
              v-model="submitApprovalForm.remarks"
              type="textarea"
              :rows="3"
              placeholder="请输入备注信息"
            />
          </el-form-item>

          <el-form-item label="附件上传" required>
            <el-upload
              ref="uploadRef"
              :auto-upload="false"
              :on-change="handleFileChange"
              :on-remove="handleRemoveFile"
              :file-list="fileList"
              :limit="5"
              :before-upload="beforeUpload"
              multiple
            >
              <el-button type="primary" size="small">选择文件</el-button>
              <template #tip>
                <div class="el-upload__tip">
                  支持上传PDF、Word、Excel、图片等格式，单个文件不超过50MB，最多5个文件（必须至少上传1个文件）
                </div>
              </template>
            </el-upload>
          </el-form-item>
        </el-form>

        <template #footer>
          <el-button @click="submitApprovalDialogVisible = false">取消</el-button>
          <el-button type="primary" @click="handleConfirmSubmitApproval" :disabled="fileList.length === 0">提交审批</el-button>
        </template>
      </el-dialog>

      <!-- 发起付款对话框 -->
      <el-dialog
        v-model="paymentDialogVisible"
        title="发起工资付款申请"
        width="800px"
      >
        <el-form :model="paymentForm" label-width="100px">
          <el-form-item label="项目名称">
            <el-input v-model="paymentForm.project_name" disabled />
          </el-form-item>
          
          <el-form-item label="工资期间">
            <el-input v-model="paymentForm.month" disabled />
          </el-form-item>

          <el-form-item label="付款金额">
            <el-input v-model="paymentForm.amount" disabled>
              <template #append>元</template>
            </el-input>
          </el-form-item>

          <!-- 付款表单字段组件 -->
          <PaymentFormFields ref="paymentFormFieldsRef" v-model="paymentFormFields" />

          <el-form-item label="备注">
            <el-input
              v-model="paymentForm.remarks"
              type="textarea"
              :rows="2"
              placeholder="请输入备注信息"
            />
        </el-form-item>

        <!-- 付款附件上传组件（包含发票、其他附件、签名盖章） -->
        <PaymentAttachmentUploader
          ref="paymentAttachmentUploaderRef"
          v-model:invoice-file-list="invoiceFileList"
          v-model:other-file-list="paymentFileList"
          :invoice-limit="10"
          :other-limit="5"
          :show-form-generator="true"
          :show-upload-later="true"
          form-button-text="填写表格生成PDF"
          form-title="工资付款申请表"
        />
        <div style="margin-top: 6px; color: #E6A23C; font-size: 12px;">
          候补资料开启后，可在付款申请列表72小时内补充发票或单据
        </div>
        </el-form>

        <template #footer>
          <el-button @click="paymentDialogVisible = false">取消</el-button>
          <el-button type="primary" @click="confirmCreatePayment">
            提交付款申请
          </el-button>
        </template>
      </el-dialog>


      <!-- 生成工资表对话框 -->
      <el-dialog
        v-model="createDialogVisible"
        title="生成工资表"
        width="600px"
      >
        <el-form :model="createForm" label-width="100px">
          <el-form-item label="工资期间" required>
          <el-date-picker
              v-model="createForm.month"
            type="month"
              placeholder="选择月份"
            format="YYYY-MM"
            value-format="YYYY-MM"
              style="width: 100%"
          />
        </el-form-item>

          <el-form-item label="工资周期" required>
            <el-row :gutter="10">
              <el-col :span="11">
                <el-input-number
                  v-model="createForm.period_start"
                  :min="1"
                  :max="31"
                  :controls="false"
                  placeholder="开始日期"
                  style="width: 100%"
                />
              </el-col>
              <el-col :span="2" style="text-align: center; line-height: 32px;">-</el-col>
              <el-col :span="11">
                <el-input-number
                  v-model="createForm.period_end"
                  :min="1"
                  :max="31"
                  :controls="false"
                  placeholder="结束日期"
                  style="width: 100%"
                />
              </el-col>
            </el-row>
          </el-form-item>
        
          <el-form-item label="项目" required>
          <el-select 
              v-model="createForm.project_id" 
              placeholder="请先选择工资期间" 
            style="width: 100%"
              :loading="loadingProjects"
              :disabled="!createForm.month"
          >
            <el-option
                v-for="project in availableProjects"
                :key="project.id"
                :label="project.label"
                :value="project.id"
                :disabled="project.disabled"
              >
                <span>{{ project.label }}</span>
              </el-option>
          </el-select>
            <div v-if="createForm.month && availableProjects.length === 0" style="color: #999; font-size: 12px; margin-top: 5px;">
              该期间暂无可生成工资表的项目
          </div>
        </el-form-item>
        
          <el-alert
            v-if="!createForm.month"
            title="请先选择工资期间"
            type="warning"
            :closable="false"
            style="margin-bottom: 20px"
          />

          <el-alert
            v-else-if="availableProjects.length === 0"
            title="该期间暂无可生成工资表的项目"
            type="warning"
            :closable="false"
            style="margin-bottom: 20px"
          >
            • 需要考勤的项目：必须先审批考勤表才能生成工资表<br>
            • 无需考勤的项目：可直接生成工资表<br>
            请检查项目设置或到"考勤管理"模块审批考勤表。
          </el-alert>

          <el-alert
            v-else
            title="提示"
            type="info"
            :closable="false"
            style="margin-bottom: 20px"
          >
            系统将自动为所选项目下的所有员工生成工资记录，包括：<br>
            • 基本工资<br>
            • 考勤数据（从考勤表读取）<br>
            • 社保公积金个人部分<br>
            • 个人所得税<br>
            • 应发和实发工资
          </el-alert>
      </el-form>
      
      <template #footer>
          <el-button @click="createDialogVisible = false">取消</el-button>
          <el-button 
            type="primary" 
            @click="handleConfirmCreate"
            :disabled="!createForm.month || !createForm.period_start || !createForm.period_end || !createForm.project_id"
          >
            确定生成
        </el-button>
      </template>
    </el-dialog>
    
      <!-- 工资明细对话框 -->
    <el-dialog
        v-model="detailDialogVisible"
        width="95%"
        top="5vh"
      >
        <template #header>
          <div class="dialog-header-title">
            <span class="title-text">工资明细 - </span>
            <el-tooltip 
              :content="currentSheet?.project_name" 
              placement="top"
              :disabled="!currentSheet?.project_name || currentSheet.project_name.length <= 30"
            >
              <span class="project-name">
                {{ currentSheet?.project_name && currentSheet.project_name.length > 30 
                   ? currentSheet.project_name.substring(0, 30) + '...' 
                   : currentSheet?.project_name }}
              </span>
            </el-tooltip>
            <span class="month-text"> ({{ currentSheet?.month }})</span>
          </div>
        </template>
        <div v-if="currentSheet" class="detail-content">
          <!-- 统计信息 -->
          <el-descriptions :column="4" border style="margin-bottom: 20px;">
            <el-descriptions-item label="项目名称">
              <el-tooltip 
                :content="currentSheet.project_name" 
                placement="top"
                :disabled="currentSheet.project_name.length <= 40"
              >
                <span>{{ currentSheet.project_name.length > 40 ? currentSheet.project_name.substring(0, 40) + '...' : currentSheet.project_name }}</span>
              </el-tooltip>
            </el-descriptions-item>
            <el-descriptions-item label="工资期间">{{ currentSheet.month }}</el-descriptions-item>
            <el-descriptions-item label="工资周期">
              <span v-if="currentSheet.period_start && currentSheet.period_end">
                {{ currentSheet.period_start }} - {{ currentSheet.period_end }}
              </span>
              <span v-else>-</span>
            </el-descriptions-item>
            <el-descriptions-item label="员工人数">{{ currentSheet.employee_count }}</el-descriptions-item>
            <el-descriptions-item label="状态">
              <el-tag :type="getStatusType(currentSheet.status)">
                {{ getStatusText(currentSheet.status) }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="应发合计">
              <span style="color: #409EFF; font-weight: bold;">{{ formatMoney(currentSheet.total_gross_salary) }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="实发合计">
              <span style="color: #67C23A; font-weight: bold;">{{ formatMoney(currentSheet.total_net_salary) }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="创建时间">{{ formatDateTime(currentSheet.created_at) }}</el-descriptions-item>
            <el-descriptions-item label="审批时间">{{ formatDateTime(currentSheet.approved_at) }}</el-descriptions-item>
          </el-descriptions>

          <!-- 附件列表 -->
          <div v-if="currentSheet.attachments && currentSheet.attachments.length > 0" style="margin-bottom: 20px;">
            <el-divider content-position="left">
              <span style="font-size: 14px; color: #606266;">附件列表 ({{ currentSheet.attachments.length }})</span>
            </el-divider>
            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
              <el-tag 
                v-for="att in currentSheet.attachments" 
                :key="att.id"
                type="info"
                style="cursor: pointer;"
                @click="handleDownloadAttachment(att)"
              >
                <el-icon><Document /></el-icon>
                {{ att.filename || att.file_name }}
              </el-tag>
            </div>
          </div>

          <!-- 导入应发工资 -->
          <div v-if="!currentSheet?.has_approval" style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
            <div>
              <el-upload
                ref="grossSalaryUploadRef"
                :auto-upload="false"
                :show-file-list="false"
                accept=".xlsx,.xls"
                :on-change="handleGrossSalaryFileChange"
              >
                <el-button type="warning" size="small">
                  <el-icon><Upload /></el-icon>
                  导入应发工资Excel
                </el-button>
              </el-upload>
            </div>
            <el-text type="info" size="small">
              Excel第2行为标题（需包含"身份证号码"和"应发工资"列），第3行起为数据
            </el-text>
          </div>

          <!-- 员工工资明细 -->
          <div
            v-if="importExtraColumns.length > 0"
            class="import-extra-toolbar"
          >
            <el-tag type="info" effect="plain">
              导入动态列 {{ importExtraColumns.length }} 项
            </el-tag>
            <el-button
              link
              type="primary"
              @click="toggleImportExtraColumns"
            >
              {{ importExtraColumnsCollapsed ? '展开导入列' : '折叠导入列' }}
            </el-button>
          </div>
          <el-table
            :data="salaryDetails"
            border
            stripe
            v-loading="detailLoading"
            height="500"
            :row-class-name="() => 'salary-row'"
          >
            <el-table-column type="index" label="序号" width="60" align="center" :index="index => index + 1" />
            <el-table-column prop="employee_name" label="姓名" width="100" />
            <el-table-column prop="id_card" label="身份证号" width="160" />
            <el-table-column prop="department" label="部门" width="120" />
            <el-table-column prop="position" label="岗位" width="120" />
            <el-table-column prop="basic_salary" label="基本工资" width="110" align="right">
              <template #default="{ row }">{{ formatMoney(row.basic_salary) }}</template>
            </el-table-column>
            <el-table-column prop="gross_salary" label="应发工资" width="120" align="right">
              <template #default="{ row }">
                <span style="color: #409EFF; font-weight: bold;">{{ formatMoney(row.gross_salary) }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="cumulative_income" label="累计收入" width="130" align="right">
              <template #default="{ row }">
                <span style="color: #67C23A; font-weight: bold;">{{ formatMoney(row.cumulative_income) }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="cumulative_basic_deduction" label="累计减除费用" width="130" align="right">
              <template #default="{ row }">
                <span style="color: #E6A23C; font-weight: bold;">{{ formatMoney(row.cumulative_basic_deduction) }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="cumulative_special_deduction_insurance" label="累计专项扣除" width="130" align="right">
              <template #default="{ row }">
                <span style="color: #F56C6C; font-weight: bold;">{{ formatMoney(row.cumulative_special_deduction_insurance) }}</span>
              </template>
            </el-table-column>
            <template v-for="column in visibleImportExtraColumns" :key="'import-extra-' + column.key">
              <el-table-column :label="column.label" min-width="130" align="right">
                <template #default="{ row }">
                  {{ getImportExtraColumnValue(row, column.key) }}
                </template>
              </el-table-column>
            </template>
            <!-- (X月)单位部分 - 大标题 -->
            <el-table-column :label="getCurrentMonthLabel()" align="center">
              <!-- 动态生成社保险种列 - 只显示金额 -->
              <template v-for="(type, typeIndex) in getInsuranceTypes(salaryDetails)" :key="'insurance-' + typeIndex">
                <el-table-column :label="type.name" width="100" align="right">
                  <template #default="{ row }">
                    {{ getInsuranceValue(row, type.key, 'company_amount') }}
                  </template>
                </el-table-column>
              </template>
              
              <!-- 公积金 - 只显示金额 -->
              <el-table-column label="公积金" width="100" align="right">
                <template #default="{ row }">
                  {{ row.insurance_details?.housing_fund ? formatMoney(row.insurance_details.housing_fund.company_amount) : '-' }}
                </template>
              </el-table-column>
              
              <!-- 大额医疗 - 只显示金额 -->
              <el-table-column label="大额医疗" width="100" align="right">
              <template #default="{ row }">
                  {{ row.insurance_details?.large_medical ? formatMoney(row.insurance_details.large_medical.company_amount) : '-' }}
              </template>
            </el-table-column>
              
              <!-- 社保基数 -->
              <el-table-column label="社保基数" width="100" align="right">
              <template #default="{ row }">
                  {{ getSocialSecurityBase(row) }}
              </template>
            </el-table-column>
              
              <!-- 公积金基数 -->
              <el-table-column label="公积金基数" width="100" align="right">
              <template #default="{ row }">
                  {{ row.insurance_details?.housing_fund ? formatMoney(row.insurance_details.housing_fund.base) : '-' }}
              </template>
            </el-table-column>
              
              <!-- 医保基数 -->
              <el-table-column label="医保基数" width="100" align="right">
              <template #default="{ row }">
                  {{ getMedicalInsuranceBase(row) }}
              </template>
            </el-table-column>
              
              <!-- 大额基数 -->
              <el-table-column label="大额基数" width="100" align="right">
              <template #default="{ row }">
                  {{ row.insurance_details?.large_medical ? formatMoney(row.insurance_details.large_medical.base) : '-' }}
              </template>
            </el-table-column>
              
              <!-- 社保补差（单位） -->
              <el-table-column label="社保补差" width="110" align="right">
              <template #default="{ row }">
                  <span v-if="getCompensationAmount(row, 'social_security', 'company') !== 0" style="color: #F56C6C;">
                    {{ formatMoney(getCompensationAmount(row, 'social_security', 'company')) }}
                  </span>
                  <span v-else>-</span>
              </template>
            </el-table-column>
              
              <!-- 公积金补差（单位） -->
              <el-table-column label="公积金补差" width="110" align="right">
              <template #default="{ row }">
                  <span v-if="getCompensationAmount(row, 'housing_fund', 'company') !== 0" style="color: #F56C6C;">
                    {{ formatMoney(getCompensationAmount(row, 'housing_fund', 'company')) }}
                  </span>
                  <span v-else>-</span>
              </template>
            </el-table-column>
            </el-table-column>
            
            <!-- 单位合计 -->
            <el-table-column label="单位合计" width="120" align="right">
              <template #default="{ row }">
                <span style="color: #E6A23C; font-weight: bold;">{{ formatMoney(calculateCompanyTotal(row)) }}</span>
              </template>
            </el-table-column>
            
            <!-- (X月)个人部分 - 大标题 -->
            <el-table-column :label="getCurrentMonthLabel('个人')" align="center">
              <!-- 动态生成社保险种列 - 只显示个人金额 -->
              <template v-for="(type, typeIndex) in getInsuranceTypes(salaryDetails)" :key="'personal-insurance-' + typeIndex">
                <el-table-column :label="type.name" width="100" align="right">
              <template #default="{ row }">
                    {{ getInsuranceValue(row, type.key, 'personal_amount') }}
              </template>
            </el-table-column>
              </template>
              
              <!-- 公积金个人 - 只显示金额 -->
              <el-table-column label="公积金" width="100" align="right">
              <template #default="{ row }">
                  {{ row.insurance_details?.housing_fund ? formatMoney(row.insurance_details.housing_fund.personal_amount) : '-' }}
              </template>
            </el-table-column>
              
              <!-- 大额医疗个人 - 只显示金额 -->
              <el-table-column label="大额医疗" width="100" align="right">
              <template #default="{ row }">
                  {{ row.insurance_details?.large_medical ? formatMoney(row.insurance_details.large_medical.personal_amount) : '-' }}
              </template>
            </el-table-column>
              
              <!-- 社保补差（个人） -->
              <el-table-column label="社保补差" width="110" align="right">
                <template #default="{ row }">
                  <span v-if="getCompensationAmount(row, 'social_security', 'personal') !== 0" style="color: #F56C6C;">
                    {{ formatMoney(getCompensationAmount(row, 'social_security', 'personal')) }}
                  </span>
                  <span v-else>-</span>
                </template>
              </el-table-column>
              
              <!-- 公积金补差（个人） -->
              <el-table-column label="公积金补差" width="110" align="right">
                <template #default="{ row }">
                  <span v-if="getCompensationAmount(row, 'housing_fund', 'personal') !== 0" style="color: #F56C6C;">
                    {{ formatMoney(getCompensationAmount(row, 'housing_fund', 'personal')) }}
                  </span>
                  <span v-else>-</span>
                </template>
              </el-table-column>
            </el-table-column>
            
            <!-- 个人合计 -->
            <el-table-column label="个人合计" width="120" align="right">
              <template #default="{ row }">
                <span style="color: #67C23A; font-weight: bold;">{{ formatMoney(calculatePersonalTotal(row)) }}</span>
              </template>
            </el-table-column>
            
            <!-- 个人所得税专项附加扣除 -->
            <el-table-column label="个人所得税专项附加扣除" align="center">
              <!-- 动态生成专项扣除列 -->
              <template v-for="(item, index) in getSpecialDeductionItems(salaryDetails)" :key="'deduction-' + index">
                <el-table-column :label="item.name" width="110" align="right">
                  <template #default="{ row }">
                    {{ getSpecialDeductionAmount(row, item.id) }}
                  </template>
                </el-table-column>
              </template>
            </el-table-column>
            
            <!-- 累计专项附加扣除（6项扣除） - 当月的6项扣除合计 - 已隐藏 -->
            <!-- <el-table-column label="累计专项附加扣除（6项扣除）" width="180" align="right">
              <template #default="{ row }">
                <span style="color: #409EFF;">{{ formatMoney(row.special_deduction_monthly) }}</span>
              </template>
            </el-table-column> -->
            
            <!-- 累计专项附加扣除 - 1月到当前月的累计值 -->
            <el-table-column label="累计专项附加扣除" width="130" align="right">
              <template #default="{ row }">
                <span style="color: #67C23A; font-weight: bold;">{{ formatMoney(row.special_deduction) }}</span>
              </template>
            </el-table-column>
            
            <!-- 累计应纳税所得额 -->
            <el-table-column prop="taxable_income" label="累计应纳税所得额" width="140" align="right">
              <template #default="{ row }">
                <span style="color: #409EFF; font-weight: bold;">{{ formatMoney(row.taxable_income) }}</span>
              </template>
            </el-table-column>
            
            <el-table-column prop="tax_rate" label="税率(%)" width="90" align="right">
              <template #default="{ row }">
                <span style="color: #F56C6C;">{{ row.tax_rate || 0 }}%</span>
              </template>
            </el-table-column>
            
            <el-table-column prop="quick_deduction" label="速算扣除数" width="120" align="right">
              <template #default="{ row }">
                {{ formatMoney(row.quick_deduction) }}
              </template>
            </el-table-column>
            
            <el-table-column prop="cumulative_tax_payable" label="累计应扣缴税额" width="140" align="right">
              <template #default="{ row }">
                <span style="color: #E6A23C; font-weight: bold;">{{ formatMoney(row.cumulative_tax_payable) }}</span>
              </template>
            </el-table-column>
            
            <el-table-column prop="tax_already_withheld" label="已扣缴税额" width="120" align="right">
              <template #default="{ row }">
                <span style="color: #67C23A; font-weight: bold;">{{ formatMoney(row.tax_already_withheld) }}</span>
              </template>
            </el-table-column>
            
            <el-table-column prop="cumulative_other_taxable" label="累计其他应纳税项（合并扣税）" width="180" align="right">
              <template #default="{ row }">
                {{ formatMoney(row.cumulative_other_taxable) }}
              </template>
            </el-table-column>
            
            <el-table-column prop="tax_payable_or_refundable" label="应补（退）税额" width="130" align="right">
              <template #default="{ row }">
                <span :style="{ color: row.tax_payable_or_refundable > 0 ? '#F56C6C' : (row.tax_payable_or_refundable < 0 ? '#67C23A' : '') }">
                  {{ formatMoney(row.tax_payable_or_refundable) }}
                </span>
              </template>
            </el-table-column>
            
            <el-table-column prop="net_salary" label="实发工资" width="120" align="right">
              <template #default="{ row }">
                <span style="color: #67C23A; font-weight: bold;">{{ formatMoney(row.net_salary) }}</span>
              </template>
            </el-table-column>
            
            <el-table-column prop="employee_signature" label="本人签字" width="120" align="center" fixed="right">
              <template #default="{ row }">
                {{ row.employee_signature || '' }}
              </template>
            </el-table-column>
          </el-table>
      </div>
      
      <template #footer>
          <el-button @click="detailDialogVisible = false">关闭</el-button>
        <el-button 
          v-if="currentSheet && !currentSheet.has_approval" 
          type="primary" 
            @click="handleSubmit(currentSheet)"
        >
          提交审批
        </el-button>
        <el-button 
          v-if="currentSheet && currentSheet.status === 'submitted' && isApprover" 
          type="success" 
            @click="handleApprove(currentSheet)"
        >
          审批通过
        </el-button>
        <el-button 
          v-if="currentSheet && currentSheet.status === 'submitted' && isApprover" 
          type="danger" 
            @click="handleReject(currentSheet)"
        >
          审批拒绝
        </el-button>
        <el-button 
          v-if="currentSheet && currentSheet.status === 'approved' && !currentSheet.has_payment_request"
          type="success"
          @click="handleCreatePaymentFromDetail"
        >
          发起付款
        </el-button>
      </template>
    </el-dialog>

    <!-- 备注事项弹窗 -->
    <el-dialog
      v-model="remarksDialogVisible"
      title="备注事项"
      width="600px"
    >
      <div v-if="currentRemarks.length > 0">
        <el-table :data="currentRemarks" border>
          <el-table-column prop="project_name" label="项目名称" width="180" />
          <el-table-column prop="remark" label="备注内容" min-width="200">
            <template #default="{ row }">
              {{ row.remark || '-' }}
            </template>
          </el-table-column>
        </el-table>
      </div>
      <el-empty v-else description="暂无备注信息" />
      <template #footer>
        <el-button @click="remarksDialogVisible = false">关闭</el-button>
      </template>
    </el-dialog>

    <!-- 查看工资依据对话框 -->
    <el-dialog
      v-model="basisDialogVisible"
      title="工资依据信息"
      width="800px"
    >
      <el-descriptions :column="2" border v-if="currentBasis">
        <el-descriptions-item label="项目名称">{{ currentBasis.project?.name }}</el-descriptions-item>
        <el-descriptions-item label="月份">{{ currentBasis.month }}</el-descriptions-item>
        <el-descriptions-item label="创建人">{{ currentBasis.creator?.name }}</el-descriptions-item>
        <el-descriptions-item label="创建时间">{{ currentBasis.created_at }}</el-descriptions-item>
        <el-descriptions-item label="说明" :span="2">
          {{ currentBasis.description || '无' }}
        </el-descriptions-item>
      </el-descriptions>

      <el-divider content-position="left">附件列表</el-divider>

      <el-table :data="currentBasis?.attachments" border v-if="currentBasis?.attachments?.length > 0">
        <el-table-column prop="file_name" label="文件名" min-width="200" show-overflow-tooltip />
        <el-table-column prop="file_type" label="类型" width="100">
          <template #default="{ row }">
            <el-tag :type="getFileTypeTag(row.file_type)">
              {{ getFileTypeText(row.file_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="大小" width="100">
          <template #default="{ row }">
            {{ formatFileSize(row.file_size) }}
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="上传时间" width="180" />
        <el-table-column label="操作" width="100">
          <template #default="{ row }">
            <el-button type="primary" size="small" @click="handlePreviewBasisFile(row)" link>
              预览
            </el-button>
          </template>
        </el-table-column>
      </el-table>
      <el-empty v-else description="暂无附件" />

      <template #footer>
        <el-button @click="basisDialogVisible = false">关闭</el-button>
      </template>
    </el-dialog>
        </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh, Upload, Paperclip, Document } from '@element-plus/icons-vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { useAccountSetStore } from '@/stores/accountSet'
import request from '@/api/request'
import NoAccountSetTip from '@/components/NoAccountSetTip.vue'
import PaymentAttachmentUploader from '@/components/PaymentAttachmentUploader.vue'
import PaymentFormFields from '@/components/PaymentFormFields.vue'
import ResubmitButton from '@/components/ResubmitButton.vue'
import { 
  getProjectsWithApprovalStatus, 
  generateSalarySheet,
  getSalarySheets, 
  getSalaryDetails,
  submitSalary,
  approveSalary,
  rejectSalary,
  paySalary,
  deleteSalary,
  createSalaryPaymentRequest,
  importGrossSalary,
  validateBeforeSubmit
} from '@/api/salaries'
import { getProjects } from '@/api/projects'
import {
  submitSalaryApproval,
  approveSalaryApproval,
  rejectSalaryApproval,
  uploadSalaryApprovalAttachment,
  deleteSalaryApprovalAttachment,
  getSalaryApprovalAttachments,
  downloadSalaryApprovalAttachment,
  completeSalaryApprovalSubmission
} from '@/api/salaryApprovals'
import {
  submitSalaryPaymentRequest,
  completeSalaryPaymentSubmission,
  uploadSalaryPaymentAttachment
} from '@/api/salaryPaymentRequests'

import { usePermissionStore } from '@/stores/permission'

const router = useRouter()
const userStore = useUserStore()
const accountSetStore = useAccountSetStore()
const permissionStore = usePermissionStore()

const isAdmin = computed(() => userStore.userInfo?.role === 'admin')
const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)
const isApprover = computed(() => ['admin', 'approver'].includes(userStore.userInfo?.role))

// 权限控制
const canCreateSalary = computed(() => permissionStore.hasPermission('salaries.create'))
const canEditSalary = computed(() => permissionStore.hasPermission('salaries.edit'))
const canDeleteSalary = computed(() => permissionStore.hasPermission('salaries.delete'))
const canExportSalary = computed(() => permissionStore.hasPermission('salaries.export'))

// 搜索表单
const searchForm = reactive({
  month: null,
  project_id: null,
  status: null
})

// 分页
const pagination = reactive({
  page: 1,
  pageSize: 20,
  total: 0
})

// 表格数据
const tableData = ref([])
const loading = ref(false)

// 项目列表
const projects = ref([])
const availableProjects = ref([])
const loadingProjects = ref(false)

// 生成工资表对话框
const createDialogVisible = ref(false)
const createForm = reactive({
  month: null,
  period_start: 19,  // 默认开始日期
  period_end: 30,    // 默认结束日期
  project_id: null  // 单选项目
})

// 工资明细对话框
const detailDialogVisible = ref(false)
const currentSheet = ref(null)
const salaryDetails = ref([])
const detailLoading = ref(false)
const importExtraColumnsCollapsed = ref(false)

// 备注事项弹窗
const remarksDialogVisible = ref(false)
const currentRemarks = ref([])

// 工资依据对话框
const basisDialogVisible = ref(false)
const currentBasis = ref(null)
// 动态获取当前服务器地址，自动适配环境
const apiBaseUrl = window.location.origin

// 状态类型映射
const getStatusType = (status) => {
  const map = {
    draft: 'info',
    submitted: 'warning',
    approved: 'success',
    paid: '',
    rejected: 'danger'
  }
  return map[status] || 'info'
}

// 状态文本映射
const getStatusText = (status) => {
  const map = {
    draft: '草稿',
    submitted: '已提交',
    pending: '待审批',
    approved: '已审批',
    paid: '已发放',
    rejected: '已拒绝'
  }
  return map[status] || '未知'
}

// 格式化金额
const formatMoney = (amount) => {
  // 如果是 null 或 undefined，显示为 0.00
  if (amount === null || amount === undefined) {
    amount = 0
  }
  return '¥' + Number(amount).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// 格式化时间
const formatDateTime = (datetime) => {
  if (!datetime) return '-'
  const date = new Date(datetime)
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  return `${year}-${month}-${day} ${hours}:${minutes}`
}

// 计算单位合计（所有保险的单位部分相加）
const calculateCompanyTotal = (row) => {
  let total = 0
  
  // 社保单位部分（包括医保）
  if (row.insurance_details && row.insurance_details.social_security) {
    row.insurance_details.social_security.forEach(item => {
      total += parseFloat(item.company_amount || 0)
    })
  }
  
  // 公积金单位部分
  if (row.insurance_details && row.insurance_details.housing_fund) {
    total += parseFloat(row.insurance_details.housing_fund.company_amount || 0)
  }
  
  // 大额医疗单位部分
  if (row.insurance_details && row.insurance_details.large_medical) {
    total += parseFloat(row.insurance_details.large_medical.company_amount || 0)
  }
  
  return total
}

// 计算个人合计（所有保险的个人部分相加）
const calculatePersonalTotal = (row) => {
  let total = 0
  
  // 社保个人部分（包括医保）
  if (row.insurance_details && row.insurance_details.social_security) {
    row.insurance_details.social_security.forEach(item => {
      total += parseFloat(item.personal_amount || 0)
    })
  }
  
  // 公积金个人部分
  if (row.insurance_details && row.insurance_details.housing_fund) {
    total += parseFloat(row.insurance_details.housing_fund.personal_amount || 0)
  }
  
  // 大额医疗个人部分
  if (row.insurance_details && row.insurance_details.large_medical) {
    total += parseFloat(row.insurance_details.large_medical.personal_amount || 0)
  }
  
  return total
}

// 获取补差金额
const getCompensationAmount = (row, type, party) => {
  // type: 'social_security' | 'housing_fund'
  // party: 'company' | 'personal'
  
  if (!row.compensation_details) return 0
  
  const compensations = row.compensation_details[type]
  if (!compensations || compensations.length === 0) return 0
  
  let total = 0
  compensations.forEach(comp => {
    if (party === 'company') {
      total += parseFloat(comp.company_total || 0)
    } else {
      total += parseFloat(comp.personal_total || 0)
    }
  })
  
  return total
}

// 获取所有专项扣除项目（从第一个员工的扣除明细中提取，因为后端已返回所有启用的项目）
const getSpecialDeductionItems = (details) => {
  if (!details || details.length === 0) return []
  
  // 后端已经为每个员工返回了所有启用的专项扣除项目（包括金额为0的）
  // 所以我们只需要从第一个员工的数据中获取项目列表即可
  const firstEmployee = details[0]
  if (!firstEmployee || !firstEmployee.special_deduction_details || !firstEmployee.special_deduction_details.items) {
    return []
  }
  
  return firstEmployee.special_deduction_details.items.map(item => ({
    id: item.id,
    name: item.name
  }))
}

// 获取某个员工某个扣除项目的金额
const getSpecialDeductionAmount = (row, itemId) => {
  if (!row.special_deduction_details || !row.special_deduction_details.items) {
    return '0.00'
  }
  
  const item = row.special_deduction_details.items.find(i => i.id === itemId)
  if (!item) {
    return '0.00'
  }
  
  // 后端已经返回了所有项目（包括金额为0的），直接格式化显示
  return formatMoney(item.amount)
}

// 获取某个员工的专项附加扣除总额
const getSpecialDeductionTotal = (row) => {
  if (!row.special_deduction_details) {
    return '0.00'
  }
  
  return formatMoney(row.special_deduction_details.total || 0)
}

const getImportExtraColumns = (details) => {
  const columns = new Map()
  ;(details || []).forEach(row => {
    ;(row.import_extra_columns || []).forEach(column => {
      if (column && column.key && !columns.has(column.key)) {
        columns.set(column.key, {
          key: column.key,
          label: column.label || column.short_label || column.key
        })
      }
    })
  })

  return Array.from(columns.values())
}

const importExtraColumns = computed(() => getImportExtraColumns(salaryDetails.value))

const visibleImportExtraColumns = computed(() => {
  return importExtraColumnsCollapsed.value ? [] : importExtraColumns.value
})

const toggleImportExtraColumns = () => {
  importExtraColumnsCollapsed.value = !importExtraColumnsCollapsed.value
}

const getImportExtraColumnValue = (row, key) => {
  const column = (row.import_extra_columns || []).find(item => item.key === key)
  const value = column ? column.value : ''
  return value === null || value === undefined || value === '' ? '-' : value
}

// 获取所有险种类型（从所有员工的保险明细中提取）
const getInsuranceTypes = (details) => {
  if (!details || details.length === 0) return []
  
  const typesSet = new Set()
  details.forEach(row => {
    if (row.insurance_details && row.insurance_details.social_security) {
      row.insurance_details.social_security.forEach(item => {
        typesSet.add(JSON.stringify({ name: item.name, key: item.name }))
      })
    }
  })
  
  return Array.from(typesSet).map(str => JSON.parse(str))
}

// 获取指定险种的值
const getInsuranceValue = (row, typeKey, field) => {
  if (!row.insurance_details || !row.insurance_details.social_security) return '-'
  
  const item = row.insurance_details.social_security.find(i => i.name === typeKey)
  if (!item) return '-'
  
  const value = item[field]
  if (value === undefined || value === null) return '-'
  
  // 格式化不同类型的值
  if (field.includes('ratio')) {
    return (value * 100).toFixed(2) + '%'
  } else if (field.includes('amount') || field === 'base') {
    return formatMoney(value)
  }
  
  return value
}

// 获取社保基数（取第一个社保险种的基数，因为社保基数通常统一）
const getSocialSecurityBase = (row) => {
  if (!row.insurance_details || !row.insurance_details.social_security || row.insurance_details.social_security.length === 0) {
    return '-'
  }
  const firstItem = row.insurance_details.social_security[0]
  return firstItem.base ? formatMoney(firstItem.base) : '-'
}

// 获取医保基数（查找医疗保险的基数）
const getMedicalInsuranceBase = (row) => {
  if (!row.insurance_details || !row.insurance_details.social_security) return '-'
  
  const medicalItem = row.insurance_details.social_security.find(i => 
    i.name.includes('医疗') || i.name.includes('医保')
  )
  
  return medicalItem && medicalItem.base ? formatMoney(medicalItem.base) : '-'
}

// 获取当前月份标签（用于表头）
const getCurrentMonthLabel = (type = '单位') => {
  if (!currentSheet.value || !currentSheet.value.month) return `(本月)${type}部分`
  
  const month = currentSheet.value.month // 格式：2025-10
  const monthNumber = month.split('-')[1] // 提取月份：10
  
  return `(${parseInt(monthNumber)}月)${type}部分`
}

// 加载项目列表
const loadProjects = async () => {
  try {
    const response = await getProjects()
    if (response && response.success) {
      projects.value = response.data.data || []
    }
  } catch (error) {
    console.error('Load projects error:', error)
  }
}

// 加载可用项目列表（考勤已审批）
const loadAvailableProjects = async () => {
  if (!createForm.month) {
    availableProjects.value = []
    return
  }

  loadingProjects.value = true
  try {
    const response = await getProjectsWithApprovalStatus(createForm.month)
    if (response.success) {
      availableProjects.value = response.data || []

      // 提示用户
      const canCreateCount = response.can_create_count || 0
      const totalCount = response.total_count || 0

      if (canCreateCount === 0) {
        ElMessage.warning(`${createForm.month} 暂无可生成工资表的项目`)
      } else if (canCreateCount < totalCount) {
        ElMessage.info(`${createForm.month} 共有 ${totalCount} 个项目，其中 ${canCreateCount} 个可生成工资表`)
      }
    }
  } catch (error) {
    ElMessage.error('加载项目列表失败')
    availableProjects.value = []
  } finally {
    loadingProjects.value = false
  }
}

// 监听期间变化，自动加载项目列表
watch(() => createForm.month, () => {
  createForm.project_id = null // 重置项目选择
  loadAvailableProjects()
})

// 查询
const handleSearch = async () => {
  loading.value = true
  try {
    const params = {
      month: searchForm.month,
      project_id: searchForm.project_id,
      status: searchForm.status,
      page: pagination.page,
      per_page: pagination.pageSize
    }
    const response = await getSalarySheets(params)
    if (response && response.success) {
      tableData.value = response.data.data || []
      pagination.total = response.data.total || 0
    }
  } catch (error) {
    console.error('Load salary sheets error:', error)
    tableData.value = []
    pagination.total = 0
  } finally {
    loading.value = false
  }
}

// 重置
const handleReset = () => {
  searchForm.month = null
  searchForm.project_id = null
  searchForm.status = null
  pagination.page = 1
  handleSearch()
}

// 生成工资表
const handleCreate = () => {
  createDialogVisible.value = true
  createForm.month = null
  createForm.project_id = null
  availableProjects.value = []
}

// 确认生成
const handleConfirmCreate = async () => {
  if (!createForm.month) {
    ElMessage.warning('请选择工资期间')
    return
  }
  if (!createForm.period_start || !createForm.period_end) {
    ElMessage.warning('请输入工资周期')
    return
  }
  if (createForm.period_start > createForm.period_end) {
    ElMessage.warning('开始日期不能大于结束日期')
    return
  }
  if (!createForm.project_id) {
    ElMessage.warning('请选择项目')
    return
  }

  try {
    loading.value = true
    
    await generateSalarySheet({
      month: createForm.month,
      period_start: createForm.period_start,
      period_end: createForm.period_end,
      project_ids: [createForm.project_id]  // 传递单个项目ID作为数组
    })
    
    ElMessage.success('工资表生成成功')
    
    createDialogVisible.value = false
    handleSearch()
  } catch (error) {
    console.error('Generate salary sheet error:', error)
    ElMessage.error(error.response?.data?.message || '生成工资表失败')
  } finally {
    loading.value = false
  }
}

// 查看明细
const handleView = (row) => {
  currentSheet.value = row
  detailDialogVisible.value = true
  loadSalaryDetails(row)
}

// 查看备注事项
// 查看工资依据
const handleViewSalaryBasis = async (row) => {
  try {
    const response = await request({
      url: '/basis-records',
      method: 'get',
      params: {
        type: 'salary',
        project_id: row.project_id,
        month: row.month,
        current_account_set_id: accountSetStore.currentAccountSetId
      }
    })
    
    if (response.success && response.data && response.data.length > 0) {
      currentBasis.value = response.data[0]
      basisDialogVisible.value = true
    } else {
      ElMessageBox.confirm(
        `该项目该月份暂无工资依据，是否前往创建？`,
        '提示',
        {
          confirmButtonText: '前往创建',
          cancelButtonText: '取消',
          type: 'info'
        }
      ).then(() => {
        router.push('/salary-basis')
      }).catch(() => {})
    }
  } catch (error) {
    console.error('加载依据失败', error)
    ElMessage.error('加载依据失败')
  }
}

// 预览依据文件
const handlePreviewBasisFile = (file) => {
  const url = `${apiBaseUrl}/storage/${file.file_path}`
  window.open(url, '_blank')
}

// 文件类型标签
const getFileTypeTag = (type) => {
  const tags = { image: 'success', document: 'primary', other: 'info' }
  return tags[type] || 'info'
}

// 文件类型文本
const getFileTypeText = (type) => {
  const texts = { image: '图片', document: '文档', other: '其他' }
  return texts[type] || '其他'
}

// 格式化文件大小
const formatFileSize = (bytes) => {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(2) + ' MB'
}

// 导入应发工资Excel
const grossSalaryUploadRef = ref(null)
const handleGrossSalaryFileChange = async (file) => {
  if (!currentSheet.value) {
    ElMessage.error('未找到当前工资表信息')
    return
  }

  try {
    ElMessage.info('正在导入应发工资数据...')
    
    const formData = new FormData()
    formData.append('file', file.raw)
    formData.append('project_id', currentSheet.value.project_id)
    formData.append('month', currentSheet.value.month)
    formData.append('current_account_set_id', accountSetStore.currentAccountSetId)
    if (currentSheet.value.draft_batch_id) {
      formData.append('draft_batch_id', currentSheet.value.draft_batch_id)
    }

    const response = await importGrossSalary(formData)
    
    if (response.success) {
      ElMessage.success(response.message || '导入成功')
      
      // 显示详细结果
      if (response.data && response.data.errors && response.data.errors.length > 0) {
        ElMessageBox.alert(
          response.data.errors.join('\n'),
          '导入结果',
          {
            confirmButtonText: '确定',
            type: 'warning'
          }
        )
      }
      
      // 重新加载工资明细
      loadSalaryDetails(currentSheet.value)
      
      // 重新加载列表（更新合计）
      handleSearch()
    } else {
      ElMessage.error(response.message || '导入失败')
    }
    
    // 清除上传组件的文件列表
    if (grossSalaryUploadRef.value) {
      grossSalaryUploadRef.value.clearFiles()
    }
  } catch (error) {
    console.error('Import gross salary error:', error)
    ElMessage.error(error.response?.data?.message || '导入失败')
  }
}

// 加载工资明细
const loadSalaryDetails = async (row) => {
  detailLoading.value = true
  importExtraColumnsCollapsed.value = false
  try {
    const response = await getSalaryDetails({
      project_id: row.project_id,
      month: row.month,
      has_approval: !!row.has_approval,
      salary_approval_id: row.salary_approval_id,
      draft_batch_id: row.draft_batch_id
    })
    if (response && response.success) {
      salaryDetails.value = response.data.details || []
    }
  } catch (error) {
    console.error('Load salary details error:', error)
    ElMessage.error('加载工资明细失败')
  } finally {
    detailLoading.value = false
  }
}

// 提交审批
// 提交审批对话框
const submitApprovalDialogVisible = ref(false)
const submitApprovalForm = reactive({
  project_id: null,
  project_name: '',
  month: null,
  draft_batch_id: null,
  approval_type: 'online',
  remarks: '',
  approval_id: null  // 审批ID，用于上传附件
})

// 文件上传相关
const uploadRef = ref(null)
const fileList = ref([])

// 发起付款对话框
const paymentDialogVisible = ref(false)
const paymentForm = reactive({
  salary_approval_id: null,
  project_name: '',
  month: '',
  amount: '',
  remarks: ''
})
const paymentFormFields = ref({}) // 付款表单字段组件数据
const paymentFormFieldsRef = ref(null)

// 付款文件上传相关
const paymentAttachmentUploaderRef = ref(null)
const paymentFileList = ref([]) // 其他附件列表
const invoiceFileList = ref([]) // 发票文件列表
const creatingPayment = ref(false)
const originalAttachments = ref([]) // 原工资表审批的附件（自动带入审批）

const handleSubmit = async (row) => {
  // 先进行验证
  try {
    const validateResponse = await validateBeforeSubmit({
      project_id: row.project_id,
      month: row.month,
      draft_batch_id: row.draft_batch_id
    })
    
    if (validateResponse.success && validateResponse.data.has_warnings) {
      const warnings = validateResponse.data.warnings
      
      // 检查是否有阻止提交的错误（实发工资小于0）
      const blockingErrors = warnings.filter(w => w.type === 'negative_net_salary')
      const otherWarnings = warnings.filter(w => w.type !== 'negative_net_salary')
      
      // 如果有实发工资小于0的情况，直接阻止提交
      if (blockingErrors.length > 0) {
        let errorHtml = '<div style="max-height: 400px; overflow-y: auto;">'
        
        blockingErrors.forEach(error => {
          errorHtml += `<div style="margin-bottom: 15px;">`
          errorHtml += `<div style="font-weight: bold; color: #F56C6C; margin-bottom: 8px;">❌ ${error.title}</div>`
          errorHtml += `<div style="color: #606266; margin-bottom: 8px;">${error.message}：</div>`
          errorHtml += `<ul style="margin: 0; padding-left: 20px; color: #909399;">`
          
          error.employees.forEach(emp => {
            const netSalary = parseFloat(emp.net_salary) || 0
            errorHtml += `<li>${emp.name}（${emp.id_card}）- 实发工资: <span style="color: #F56C6C; font-weight: bold;">${netSalary.toFixed(2)}元</span></li>`
          })
          
          errorHtml += `</ul></div>`
        })
        
        errorHtml += '<div style="margin-top: 15px; padding: 10px; background: #FEF0F0; border-radius: 4px; color: #F56C6C;">请先调整工资数据，确保所有员工实发工资不为负数后再提交审批。</div>'
        errorHtml += '</div>'
        
        await ElMessageBox.alert(
          errorHtml,
          '无法提交审批',
          {
            type: 'error',
            dangerouslyUseHTMLString: true,
            confirmButtonText: '知道了',
            customClass: 'salary-validation-dialog'
          }
        )
        return
      }
      
      // 如果只有其他警告（在职人员未计算、个税异常），允许用户选择是否继续
      if (otherWarnings.length > 0) {
        let warningHtml = '<div style="max-height: 400px; overflow-y: auto;">'
        
        otherWarnings.forEach(warning => {
          const iconColor = warning.type === 'missing_employees' ? '#E6A23C' : '#909399'
          warningHtml += `<div style="margin-bottom: 15px;">`
          warningHtml += `<div style="font-weight: bold; color: ${iconColor}; margin-bottom: 8px;">⚠️ ${warning.title}</div>`
          warningHtml += `<div style="color: #606266; margin-bottom: 8px;">${warning.message}：</div>`
          warningHtml += `<ul style="margin: 0; padding-left: 20px; color: #909399;">`
          
          warning.employees.forEach(emp => {
            if (warning.type === 'missing_employees') {
              warningHtml += `<li>${emp.name}（${emp.id_card || '无身份证'}）</li>`
            } else if (warning.type === 'invalid_tax') {
              const grossSalary = parseFloat(emp.gross_salary) || 0
              const taxPayable = parseFloat(emp.tax_payable_or_refundable) || 0
              warningHtml += `<li>${emp.name}（${emp.id_card}）- 应发: ${grossSalary.toFixed(2)}元, 应缴税: ${taxPayable.toFixed(2)}元</li>`
            }
          })
          
          warningHtml += `</ul></div>`
        })
        
        warningHtml += '</div>'
        
        try {
          await ElMessageBox.confirm(
            warningHtml,
            '工资表存在以下问题',
            {
              confirmButtonText: '仍然提交',
              cancelButtonText: '取消',
              type: 'warning',
              dangerouslyUseHTMLString: true,
              customClass: 'salary-validation-dialog'
            }
          )
        } catch {
          // 用户取消
          return
        }
      }
    }
  } catch (error) {
    console.error('Validation error:', error)
    ElMessage.error('验证失败，请重试')
    return
  }
  
  // 验证通过或用户确认继续，打开提交对话框
  submitApprovalForm.project_id = row.project_id
  submitApprovalForm.project_name = row.project_name
  submitApprovalForm.month = row.month
  submitApprovalForm.draft_batch_id = row.draft_batch_id || null
  submitApprovalForm.approval_type = 'online'
  submitApprovalForm.remarks = ''
  submitApprovalForm.approval_id = null
  fileList.value = []
  submitApprovalDialogVisible.value = true
}

// 文件选择后
const handleFileChange = (file, uploadFiles) => {
  // 检查文件大小
  const isLt50M = file.size / 1024 / 1024 < 50
  if (!isLt50M) {
    ElMessage.error('文件大小不能超过 50MB!')
    // 移除超大文件
    const index = uploadFiles.indexOf(file)
    if (index > -1) {
      uploadFiles.splice(index, 1)
    }
    return
  }
  
  fileList.value = uploadFiles
}

// 移除文件
const handleRemoveFile = (file, uploadFiles) => {
  fileList.value = uploadFiles
}

// 文件上传前检查（实际不会调用，因为设置了auto-upload: false）
const beforeUpload = (file) => {
  const isLt50M = file.size / 1024 / 1024 < 50
  if (!isLt50M) {
    ElMessage.error('文件大小不能超过 50MB!')
    return false
        }
        return true
      }

// 上传单个文件到服务器
const uploadFileToServer = async (file, approvalId) => {
  const formData = new FormData()
  formData.append('file', file.raw)
  formData.append('salary_approval_id', approvalId)
  formData.append('current_account_set_id', accountSetStore.currentAccountSetId)

  try {
    const response = await uploadSalaryApprovalAttachment(formData)
    if (response.success) {
      return true
    } else {
      ElMessage.error(`文件 ${file.name} 上传失败: ${response.message}`)
      return false
    }
  } catch (error) {
    console.error('Upload file error:', error)
    ElMessage.error(`文件 ${file.name} 上传失败`)
    return false
  }
}

// 确认提交审批
const handleConfirmSubmitApproval = async () => {
  // 检查是否选择了文件
  if (fileList.value.length === 0) {
    ElMessage.warning('请先选择要上传的附件文件（至少1个）')
    return
  }

  try {
    // 1. 先创建审批记录
    const response = await submitSalaryApproval({
      project_id: submitApprovalForm.project_id,
      month: submitApprovalForm.month,
      draft_batch_id: submitApprovalForm.draft_batch_id,
      approval_type: submitApprovalForm.approval_type,
      remarks: submitApprovalForm.remarks
    })
    
    if (!response.data || !response.data.id) {
      ElMessage.error('创建审批失败')
      return
    }

    const approvalId = response.data.id
    
    // 2. 上传所有文件
    ElMessage.info('正在上传附件...')
    let uploadCount = 0
    for (const file of fileList.value) {
      const success = await uploadFileToServer(file, approvalId)
      if (success) {
        uploadCount++
      }
    }

    if (uploadCount === 0) {
      ElMessage.error('所有文件上传失败，请重试')
      return
    }

    // 3. 完成提交，创建审批流程实例
    ElMessage.info('正在创建审批流程...')
    const completeResponse = await completeSalaryApprovalSubmission({
      salary_approval_id: approvalId,
      current_account_set_id: accountSetStore.currentAccountSetId
    })
    
    if (completeResponse.success) {
      ElMessage.success(`提交成功！已上传 ${uploadCount}/${fileList.value.length} 个文件，审批流程已创建`)
    } else {
      ElMessage.warning(`附件已上传，但创建审批流程失败: ${completeResponse.message}`)
    }

    submitApprovalDialogVisible.value = false
    detailDialogVisible.value = false
    handleSearch()
      } catch (error) {
    console.error('Submit error:', error)
    ElMessage.error(error.response?.data?.message || '提交失败')
  }
}

// 审批通过
const handleApprove = (row) => {
  ElMessageBox.confirm(
    `确定审批通过 ${row.project_name} ${row.month} 的工资表吗？`,
    '审批通过',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    }
  ).then(async () => {
    try {
      await approveSalary({
        project_id: row.project_id,
        month: row.month
      })
      ElMessage.success('审批成功')
      detailDialogVisible.value = false
      handleSearch()
    } catch (error) {
      console.error('Approve error:', error)
      ElMessage.error(error.response?.data?.message || '审批失败')
    }
  }).catch(() => {})
}

// 审批拒绝
const handleReject = (row) => {
  ElMessageBox.prompt('请输入拒绝原因', '审批拒绝', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    inputPattern: /.+/,
    inputErrorMessage: '请输入拒绝原因'
  }).then(async ({ value }) => {
    try {
      await rejectSalary({
        project_id: row.project_id,
        month: row.month,
        reason: value
      })
      ElMessage.success('已拒绝')
      detailDialogVisible.value = false
      handleSearch()
  } catch (error) {
      console.error('Reject error:', error)
      ElMessage.error(error.response?.data?.message || '拒绝失败')
    }
  }).catch(() => {})
}

// 标记发放
const handlePay = (row) => {
  ElMessageBox.confirm(
    `确定标记 ${row.project_name} ${row.month} 的工资为已发放吗？`,
    '标记发放',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    }
  ).then(async () => {
    try {
      await paySalary({
        project_id: row.project_id,
        month: row.month
      })
      ElMessage.success('已标记为发放')
      handleSearch()
  } catch (error) {
      console.error('Pay error:', error)
      ElMessage.error(error.response?.data?.message || '标记发放失败')
    }
  }).catch(() => {})
}

// 发起付款
const handleCreatePayment = async (row) => {
  // 确保项目列表已加载
  if (projects.value.length === 0) {
    await loadProjects()
  }
  
  // 打开发起付款对话框
  paymentForm.salary_approval_id = row.salary_approval_id
  paymentForm.project_name = row.project_name
  paymentForm.month = row.month
  paymentForm.amount = formatMoney(row.total_net_salary)
  paymentForm.remarks = `工资付款申请 - ${row.project_name} ${row.month}`
  // 重置付款表单字段组件
  paymentFormFields.value = {}
  if (paymentFormFieldsRef.value) {
    paymentFormFieldsRef.value.resetForm()
  }
  // 将原工资表附件转换为文件列表格式（标记为已存在）
  paymentFileList.value = (row.attachments || []).map(att => ({
    name: att.filename || att.file_name,
    size: att.file_size,
    uid: `existing_${att.id}`,
    status: 'success',
    isExisting: true,  // 标记为已存在的附件
    filePath: att.file_path
  }))
  invoiceFileList.value = []
  paymentDialogVisible.value = true
}

// 从明细对话框发起付款
const handleCreatePaymentFromDetail = () => {
  if (currentSheet.value) {
    handleCreatePayment(currentSheet.value)
    detailDialogVisible.value = false
  }
}

// 下载附件
const handleDownloadAttachment = (att) => {
  // 获取文件路径
  const filePath = att.file_path || att.path
  if (!filePath) {
    ElMessage.warning('附件路径不存在')
    return
  }
  
  // 构建完整URL - 使用相对路径，让浏览器自动使用当前域名
  const baseURL = import.meta.env.VITE_API_BASE_URL || ''
  // salary_approvals 目录直接在 public 下，不需要 storage 前缀
  // 其他目录可能需要 storage 前缀
  let url
  if (filePath.startsWith('salary_approvals/') || filePath.startsWith('/salary_approvals/')) {
    url = `${baseURL}/${filePath.replace(/^\//, '')}`
  } else if (filePath.startsWith('storage/') || filePath.startsWith('/storage/')) {
    url = `${baseURL}/${filePath.replace(/^\//, '')}`
  } else {
    url = `${baseURL}/storage/${filePath}`
  }
  
  window.open(url, '_blank')
}

// 付款文件选择后

// 确认发起付款
const confirmCreatePayment = async () => {
  try {
    creatingPayment.value = true

    // 获取稍后上传状态
    const uploadLater = paymentAttachmentUploaderRef.value?.getUploadLater() || false

    // 1. 校验：如果没有勾选稍后上传，且没有发票，必须有情况说明单附件
    if (!uploadLater && invoiceFileList.value.length === 0) {
      // 检查其他附件中是否有情况说明单（文件名包含"情况说明单"）
      const hasSituationReport = paymentFileList.value.some(file => 
        file.name && file.name.includes('情况说明单')
      )
      
      if (!hasSituationReport) {
        ElMessage.error('未上传发票时，必须填写情况说明单并生成附件！')
        creatingPayment.value = false
        return
      }
    }

    // 2. 先创建付款申请记录
    const formFieldsData = paymentFormFieldsRef.value ? paymentFormFieldsRef.value.getFormData() : {}
    const response = await submitSalaryPaymentRequest({
      salary_approval_id: paymentForm.salary_approval_id,
      remarks: paymentForm.remarks,
      current_account_set_id: accountSetStore.currentAccountSetId,
      upload_later: uploadLater, // 传递稍后上传状态
      // 付款表单字段
      reimbursement_form_data: formFieldsData
    })
    
    if (!response.data || !response.data.id) {
      throw new Error('创建付款申请失败：未返回付款申请ID')
    }
    
    const paymentRequestId = response.data.id
    
    // 3. 分别上传发票和其他附件，传递不同的attachment_type
    const newInvoiceFiles = invoiceFileList.value.filter(file => !file.isExisting && file.raw)
    const newOtherFiles = paymentFileList.value.filter(file => !file.isExisting && file.raw)
    
    // 4. 如果有新文件，则上传
    let uploadCount = 0
    if (newInvoiceFiles.length > 0 || newOtherFiles.length > 0) {
      ElMessage.info('正在上传附件...')
      
      // 上传发票文件
      for (const file of newInvoiceFiles) {
        const success = await uploadPaymentFileToServer(file, paymentRequestId, 'invoice')
        if (success) {
          uploadCount++
        }
      }
      
      // 上传其他附件
      for (const file of newOtherFiles) {
        const success = await uploadPaymentFileToServer(file, paymentRequestId, 'attachment')
        if (success) {
          uploadCount++
        }
      }
      
      const totalNewFiles = newInvoiceFiles.length + newOtherFiles.length
      if (uploadCount === 0 && totalNewFiles > 0) {
        ElMessage.error('所有文件上传失败，请重试')
        creatingPayment.value = false
        return
      }
    }
    
    // 5. 完成提交，创建审批流程（无附件时系统将自动生成占位附件）
    const completeResponse = await completeSalaryPaymentSubmission({
      payment_request_id: paymentRequestId
    })
    
    if (completeResponse.success) {
      const allFiles = [...invoiceFileList.value, ...paymentFileList.value]
      const newFilesCount = newInvoiceFiles.length + newOtherFiles.length
      const existingCount = allFiles.length - newFilesCount
      let message = '付款申请已提交！'
      if (newFilesCount > 0) {
        message += `已上传 ${uploadCount} 个新文件`
      }
      if (existingCount > 0) {
        message += `${newFilesCount > 0 ? '，' : ''}已关联 ${existingCount} 个原有附件`
      }
      message += '，审批流程已创建'
      ElMessage.success(message)
    } else {
      ElMessage.warning(`附件已上传，但创建审批流程失败: ${completeResponse.message}`)
    }

    paymentDialogVisible.value = false
    handleSearch()
  } catch (error) {
    console.error('Create payment error:', error)
    ElMessage.error(error.response?.data?.message || error.message || '发起付款失败')
  } finally {
    creatingPayment.value = false
  }
}


// 上传付款文件到服务器
const uploadPaymentFileToServer = async (file, paymentRequestId, attachmentType = 'attachment') => {
  try {
    const formData = new FormData()
    formData.append('file', file.raw)
    formData.append('payment_request_id', paymentRequestId)
    formData.append('current_account_set_id', accountSetStore.currentAccountSetId)
    formData.append('attachment_type', attachmentType)
    
    await uploadSalaryPaymentAttachment(formData)
    return true
  } catch (error) {
    console.error(`上传文件 ${file.name} 失败:`, error)
    ElMessage.error(`上传文件 ${file.name} 失败: ${error.response?.data?.message || error.message}`)
    return false
  }
}

// 删除
const handleDelete = (row) => {
  ElMessageBox.confirm(
    `确定删除 ${row.project_name} ${row.month} 的工资表吗？`,
    '删除确认',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    }
  ).then(async () => {
    try {
      await deleteSalary({
        project_id: row.project_id,
        month: row.month,
        draft_batch_id: row.draft_batch_id
      })
      ElMessage.success('删除成功')
      handleSearch()
    } catch (error) {
      console.error('Delete error:', error)
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }).catch(() => {})
}

// 初始化
onMounted(() => {
  handleSearch()
  loadProjects()
})
</script>

<style scoped>
.salaries-page {
  padding: 20px;
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

.header-actions {
  display: flex;
  gap: 10px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.title {
  font-size: 16px;
  font-weight: bold;
}

.search-form {
  margin-bottom: 0;
}

.detail-content {
  max-height: 70vh;
  overflow-y: auto;
}

.import-extra-toolbar {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}

/* 对话框标题样式 */
.dialog-header-title {
  display: flex;
  align-items: center;
  font-size: 18px;
  font-weight: 600;
  color: #303133;
}

.dialog-header-title .title-text {
  white-space: nowrap;
}

.dialog-header-title .project-name {
  color: #409EFF;
  font-weight: 700;
  cursor: help;
  display: inline-block;
  max-width: 500px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dialog-header-title .month-text {
  white-space: nowrap;
  color: #606266;
  font-weight: 500;
}

:deep(.el-table) {
  font-size: 14px;
}

:deep(.el-table th) {
  background-color: #f5f7fa;
}
</style>
