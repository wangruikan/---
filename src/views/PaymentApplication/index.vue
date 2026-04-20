<template>
  <div class="payment-application-container">
    <!-- 筛选区域 -->
    <el-card class="filter-card">
      <el-form :model="filterForm" inline>
        <el-form-item label="付款类型">
          <el-select v-model="filterForm.payment_type" placeholder="全部类型" clearable style="width: 150px;">
            <el-option label="工资付款" value="salary" />
            <el-option label="保险付款" value="insurance" />
            <el-option label="报销付款" value="reimbursement" />
          </el-select>
        </el-form-item>
        <el-form-item label="月份">
          <el-date-picker
            v-model="filterForm.month"
            type="month"
            placeholder="选择月份"
            format="YYYY-MM"
            value-format="YYYY-MM"
            clearable
          />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filterForm.status" placeholder="全部状态" clearable style="width: 150px;">
            <el-option label="草稿" value="draft" />
            <el-option label="待审批" value="pending" />
            <el-option label="已通过" value="approved" />
            <el-option label="已驳回" value="rejected" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
          <el-button :icon="Refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 付款申请列表 -->
    <el-card class="table-card">
      <el-table :data="applicationList" v-loading="loading" border stripe>
        <el-table-column prop="id" label="申请ID" width="80" />
        <el-table-column label="付款类型" width="140">
          <template #default="{ row }">
            <el-tag 
              :type="row.payment_type === 'salary' ? 'primary' : (row.payment_type === 'insurance' ? 'success' : 'warning')" 
              size="small"
            >
              {{ row.type_name }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="申请标题" min-width="200" />
        <el-table-column prop="month" label="月份" width="100" />
        <el-table-column label="发起人" width="120">
          <template #default="{ row }">
            {{ row.initiator?.name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="当前审批人" width="120">
          <template #default="{ row }">
            {{ getCurrentApproverName(row) }}
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.status === 'draft'" type="info">草稿</el-tag>
            <el-tag v-else-if="row.status === 'pending'" type="warning">待审批</el-tag>
            <el-tag v-else-if="row.status === 'approved'" type="success">已通过</el-tag>
            <el-tag v-else-if="row.status === 'rejected'" type="danger">已驳回</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="发票状态" width="150" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.upload_later && row.attachments_count === 0" type="danger">发票或附件未上传</el-tag>
            <el-tag v-else-if="row.invoice_status === 'pending_invoice'" type="warning">待上传发票</el-tag>
            <el-tag v-else-if="row.invoice_status === 'invoice_uploaded'" type="info">发票已上传</el-tag>
            <el-tag v-else-if="row.invoice_status === 'invoice_in_approval'" type="primary">发票审批中</el-tag>
            <el-tag v-else-if="row.invoice_status === 'invoice_approved'" type="success">发票已审批</el-tag>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column label="申请日期" width="110">
          <template #default="{ row }">
            {{ row.apply_date ? formatDateTime(row.apply_date).substring(0, 10) : '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="unit_name" label="单位名称" width="120" />
        <el-table-column prop="invoice_number" label="发票号码" width="120" />
        <el-table-column label="打款日期" width="110">
          <template #default="{ row }">
            {{ row.payment_date ? formatDateTime(row.payment_date).substring(0, 10) : '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="summary" label="摘要" width="150" show-overflow-tooltip />
        <el-table-column label="开票金额" width="100" align="right">
          <template #default="{ row }">
            {{ row.invoice_amount ? parseFloat(row.invoice_amount).toFixed(2) : '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="invoice_type" label="发票类型" width="120" />
        <el-table-column prop="reimburser" label="报销人" width="100" />
        <el-table-column prop="tax_rate" label="税率" width="80" />
        <el-table-column label="税金" width="100" align="right">
          <template #default="{ row }">
            {{ row.tax_amount ? parseFloat(row.tax_amount).toFixed(2) : '-' }}
          </template>
        </el-table-column>
        <el-table-column label="扣除额" width="100" align="right">
          <template #default="{ row }">
            {{ row.deduction_amount ? parseFloat(row.deduction_amount).toFixed(2) : '-' }}
          </template>
        </el-table-column>
        <el-table-column label="不含税金额" width="110" align="right">
          <template #default="{ row }">
            {{ row.amount_excluding_tax ? parseFloat(row.amount_excluding_tax).toFixed(2) : '-' }}
          </template>
        </el-table-column>
        <el-table-column label="附件数量" width="100" align="center">
          <template #default="{ row }">
            {{ row.attachments_count !== undefined ? row.attachments_count : (row.attachments?.length || 0) }}
          </template>
        </el-table-column>
        <el-table-column label="发票数量" width="100" align="center">
          <template #default="{ row }">
            {{ row.invoice_attachments_count || 0 }}
          </template>
        </el-table-column>
        <el-table-column label="提交时间" width="160">
          <template #default="{ row }">
            {{ formatDateTime(row.submitted_at) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="250" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" :icon="View" @click="handleView(row)">查看</el-button>
            <el-button 
              v-if="row.status === 'rejected'"
              link 
              type="warning" 
              :icon="RefreshRight" 
              @click="handleResubmit(row)"
            >
              重新申请
            </el-button>
            <el-button 
              v-if="row.can_supplement_attachment"
              link 
              type="success" 
              :icon="UploadFilled" 
              @click="handleSupplementAttachment(row)"
            >
              上传附件
            </el-button>
            <el-button 
              v-if="['pending_invoice', 'invoice_uploaded'].includes(row.invoice_status) && row.can_upload_invoice"
              link 
              type="warning" 
              :icon="UploadFilled" 
              @click="openInvoiceUploadDialog(row)"
            >
              上传发票
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-container">
        <el-pagination
          v-model:current-page="pagination.current"
          v-model:page-size="pagination.pageSize"
          :total="pagination.total"
          :page-sizes="[10, 15, 20, 50]"
          layout="total, sizes, prev, pager, next, jumper"
          @current-change="loadApplicationList"
          @size-change="loadApplicationList"
        />
      </div>
    </el-card>

    <!-- 编辑/查看详情对话框 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogMode === 'view' ? '付款申请详情' : '重新申请'"
      width="800px"
    >
      <!-- 查看模式 -->
      <template v-if="dialogMode === 'view'">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="申请标题">{{ detailData.title }}</el-descriptions-item>
          <el-descriptions-item label="月份">{{ detailData.month }}</el-descriptions-item>
          <el-descriptions-item label="发起人">{{ detailData.initiator?.name }}</el-descriptions-item>
          <el-descriptions-item label="当前审批人">
            {{ getCurrentApproverName(detailData) }}
          </el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag v-if="detailData.status === 'draft'" type="info">草稿</el-tag>
            <el-tag v-else-if="detailData.status === 'pending'" type="warning">待审批</el-tag>
            <el-tag v-else-if="detailData.status === 'approved'" type="success">已通过</el-tag>
            <el-tag v-else-if="detailData.status === 'rejected'" type="danger">已驳回</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="创建时间">
            {{ formatDateTime(detailData.created_at) }}
          </el-descriptions-item>
          <el-descriptions-item label="关联项目" :span="2">
            <el-tag 
              v-for="projectId in detailData.project_ids" 
              :key="projectId"
              style="margin-right: 8px;"
            >
              {{ getProjectName(projectId) }}
            </el-tag>
            <span v-if="!detailData.project_ids || detailData.project_ids.length === 0">-</span>
          </el-descriptions-item>
          <el-descriptions-item label="申请说明" :span="2">
            {{ detailData.description || '-' }}
          </el-descriptions-item>
        </el-descriptions>
      </template>

      <!-- 编辑模式 -->
      <template v-else>
        <el-form :model="detailData" label-width="120px">
          <el-form-item label="申请标题" required>
            <el-input v-model="detailData.title" placeholder="请输入申请标题" />
          </el-form-item>
          <el-form-item label="月份">
            <el-date-picker
              v-model="detailData.month"
              type="month"
              placeholder="选择月份"
              format="YYYY-MM"
              value-format="YYYY-MM"
              style="width: 100%;"
            />
          </el-form-item>
          <el-form-item label="关联项目">
            <el-select 
              v-model="detailData.project_ids" 
              multiple 
              placeholder="请选择项目"
              style="width: 100%;"
            >
              <el-option
                v-for="project in projectList"
                :key="project.id"
                :label="project.name"
                :value="project.id"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="申请说明">
            <el-input 
              v-model="detailData.description" 
              type="textarea" 
              :rows="4"
              placeholder="请输入申请说明"
            />
          </el-form-item>
        </el-form>
      </template>

      <!-- 报销表单信息 -->
      <el-divider v-if="detailData.reimbursement_form" content-position="left">报销表单信息</el-divider>
      
      <!-- 查看模式：只读显示 -->
      <el-descriptions v-if="detailData.reimbursement_form && dialogMode === 'view'" :column="2" border>
        <el-descriptions-item label="项目">{{ detailData.reimbursement_form.project || '-' }}</el-descriptions-item>
        <el-descriptions-item label="申请日期">
          {{ detailData.reimbursement_form.apply_date ? formatDateTime(detailData.reimbursement_form.apply_date).substring(0, 10) : '-' }}
        </el-descriptions-item>
        <el-descriptions-item label="单位名称">{{ detailData.reimbursement_form.unit_name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="发票号码">{{ detailData.reimbursement_form.invoice_number || '-' }}</el-descriptions-item>
        <el-descriptions-item label="查验">
          <el-tag v-if="detailData.reimbursement_form.verified" type="success" size="small">已查验</el-tag>
          <span v-else>-</span>
        </el-descriptions-item>
        <el-descriptions-item label="打款日期">
          {{ detailData.reimbursement_form.payment_date ? formatDateTime(detailData.reimbursement_form.payment_date).substring(0, 10) : '-' }}
        </el-descriptions-item>
        <el-descriptions-item label="支出金额">
          <span v-if="detailData.reimbursement_form.expenditure_amount">
            ¥{{ Number(detailData.reimbursement_form.expenditure_amount).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
          </span>
          <span v-else>-</span>
        </el-descriptions-item>
        <!-- <el-descriptions-item label="项目名称">{{ detailData.reimbursement_form.project_name || '-' }}</el-descriptions-item> -->
        <el-descriptions-item label="摘要" :span="2">{{ detailData.reimbursement_form.summary || '-' }}</el-descriptions-item>
        <el-descriptions-item label="收到发票">
          <el-tag v-if="detailData.reimbursement_form.invoice_received" type="success" size="small">已收到</el-tag>
          <span v-else>-</span>
        </el-descriptions-item>
        <el-descriptions-item label="发票类型">{{ detailData.reimbursement_form.invoice_type || '-' }}</el-descriptions-item>
        <el-descriptions-item label="开票金额">
          <span v-if="detailData.reimbursement_form.invoice_amount">
            ¥{{ Number(detailData.reimbursement_form.invoice_amount).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
          </span>
          <span v-else>-</span>
        </el-descriptions-item>
        <el-descriptions-item label="税率">{{ detailData.reimbursement_form.tax_rate || '-' }}</el-descriptions-item>
        <el-descriptions-item label="扣除额">
          <span v-if="detailData.reimbursement_form.deduction_amount">
            ¥{{ Number(detailData.reimbursement_form.deduction_amount).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
          </span>
          <span v-else>-</span>
        </el-descriptions-item>
        <el-descriptions-item label="不含税金额">
          <span v-if="detailData.reimbursement_form.amount_excluding_tax">
            ¥{{ Number(detailData.reimbursement_form.amount_excluding_tax).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
          </span>
          <span v-else>-</span>
        </el-descriptions-item>
        <el-descriptions-item label="税金">
          <span v-if="detailData.reimbursement_form.tax_amount">
            ¥{{ Number(detailData.reimbursement_form.tax_amount).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
          </span>
          <span v-else>-</span>
        </el-descriptions-item>
        <el-descriptions-item label="是否一致">
          <el-tag v-if="detailData.reimbursement_form.is_consistent" type="success" size="small">一致</el-tag>
          <span v-else>-</span>
        </el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag v-if="detailData.reimbursement_form.status_checked" type="success" size="small">已确认</el-tag>
          <span v-else>-</span>
        </el-descriptions-item>
        <el-descriptions-item label="勾选月份">{{ detailData.reimbursement_form.selected_month || '-' }}</el-descriptions-item>
        <el-descriptions-item label="报销人">{{ detailData.reimbursement_form.reimburser || '-' }}</el-descriptions-item>
        <el-descriptions-item label="开票日期">
          {{ detailData.reimbursement_form.invoice_date ? formatDateTime(detailData.reimbursement_form.invoice_date).substring(0, 10) : '-' }}
        </el-descriptions-item>
        <el-descriptions-item label="入账">
          <el-tag v-if="detailData.reimbursement_form.accounted" type="success" size="small">已入账</el-tag>
          <span v-else>-</span>
        </el-descriptions-item>
        <el-descriptions-item label="公司">{{ detailData.reimbursement_form.company || '-' }}</el-descriptions-item>
      </el-descriptions>

      <!-- 编辑模式：可编辑表单 -->
      <el-form v-if="detailData.reimbursement_form && dialogMode === 'edit'" :model="detailData.reimbursement_form" label-width="120px">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="项目">
              <el-input v-model="detailData.reimbursement_form.project" placeholder="请输入项目" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="申请日期">
              <el-date-picker
                v-model="detailData.reimbursement_form.apply_date"
                type="date"
                placeholder="选择日期"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
                style="width: 100%;"
              />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="单位名称">
              <el-input v-model="detailData.reimbursement_form.unit_name" placeholder="请输入单位名称" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="发票号码">
              <el-input v-model="detailData.reimbursement_form.invoice_number" placeholder="请输入发票号码" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="查验">
              <el-switch v-model="detailData.reimbursement_form.verified" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="打款日期">
              <el-date-picker
                v-model="detailData.reimbursement_form.payment_date"
                type="date"
                placeholder="选择日期"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
                style="width: 100%;"
              />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="支出金额">
              <el-input v-model="detailData.reimbursement_form.expenditure_amount" placeholder="请输入支出金额" type="number" />
            </el-form-item>
          </el-col>
          <!-- <el-col :span="12">
            <el-form-item label="项目名称">
              <el-input v-model="detailData.reimbursement_form.project_name" placeholder="请输入项目名称" />
            </el-form-item>
          </el-col> -->
        </el-row>
        <el-form-item label="摘要">
          <el-input v-model="detailData.reimbursement_form.summary" type="textarea" :rows="2" placeholder="请输入摘要" />
        </el-form-item>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="收到发票">
              <el-switch v-model="detailData.reimbursement_form.invoice_received" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="发票类型">
              <el-input v-model="detailData.reimbursement_form.invoice_type" placeholder="请输入发票类型" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="开票金额">
              <el-input v-model="detailData.reimbursement_form.invoice_amount" placeholder="请输入开票金额" type="number" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="税率">
              <el-input v-model="detailData.reimbursement_form.tax_rate" placeholder="请输入税率" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="扣除额">
              <el-input v-model="detailData.reimbursement_form.deduction_amount" placeholder="请输入扣除额" type="number" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="不含税金额">
              <el-input v-model="detailData.reimbursement_form.amount_excluding_tax" placeholder="请输入不含税金额" type="number" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="税金">
              <el-input v-model="detailData.reimbursement_form.tax_amount" placeholder="请输入税金" type="number" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="是否一致">
              <el-switch v-model="detailData.reimbursement_form.is_consistent" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="状态">
              <el-switch v-model="detailData.reimbursement_form.status_checked" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="勾选月份">
              <el-date-picker
                v-model="detailData.reimbursement_form.selected_month"
                type="month"
                placeholder="选择月份"
                format="YYYY-MM"
                value-format="YYYY-MM"
                style="width: 100%;"
              />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="报销人">
              <el-input v-model="detailData.reimbursement_form.reimburser" placeholder="请输入报销人" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="开票日期">
              <el-date-picker
                v-model="detailData.reimbursement_form.invoice_date"
                type="date"
                placeholder="选择日期"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
                style="width: 100%;"
              />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="入账">
              <el-switch v-model="detailData.reimbursement_form.accounted" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="公司">
              <el-input v-model="detailData.reimbursement_form.company" placeholder="请输入公司" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>

      <!-- 附件上传区域（草稿状态或编辑模式可编辑） -->
      <div style="margin-top: 20px;">
        <h4>附件列表 <span style="color: #f56c6c; font-size: 12px;">* 必须至少上传1个附件</span></h4>
        <div v-if="detailData.status === 'draft' || dialogMode === 'edit'" style="margin-bottom: 10px;">
          <el-button 
            type="success" 
            @click="showFormToWordDialog = true"
            style="margin-right: 10px;"
          >
            <el-icon><DocumentAdd /></el-icon>
            填写表格生成Word
          </el-button>
          <el-upload
            ref="uploadRef"
            :action="getUploadUrl(detailData.id)"
            :headers="uploadHeaders"
            name="file"
            :on-success="handleUploadSuccess"
            :on-error="handleUploadError"
            :show-file-list="false"
            :before-upload="beforeUpload"
          >
            <el-button type="primary" :icon="UploadFilled">上传附件</el-button>
          </el-upload>
        </div>
        <el-table :data="detailData.attachments" border>
          <el-table-column prop="filename" label="文件名" />
          <el-table-column label="大小" width="100">
            <template #default="{ row }">
              {{ formatFileSize(row.size) }}
            </template>
          </el-table-column>
          <el-table-column label="上传时间" width="160">
            <template #default="{ row }">
              {{ formatDateTime(row.created_at) }}
            </template>
          </el-table-column>
          <el-table-column label="操作" width="150">
            <template #default="{ row }">
              <el-button link type="primary" :icon="View" @click="viewAttachment(row)">
                查看
              </el-button>
              <el-button 
                v-if="detailData.status === 'draft' || dialogMode === 'edit'" 
                link 
                type="danger" 
                :icon="Delete" 
                @click="handleDeleteAttachment(row)"
              >
                删除
              </el-button>
            </template>
          </el-table-column>
        </el-table>
      </div>

      <!-- 底部按钮 -->
      <template #footer v-if="dialogMode === 'edit'">
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleResubmitConfirm">提交重新申请</el-button>
      </template>

    </el-dialog>

    <!-- 表格填写生成Word组件 -->
    <FormToWordGenerator 
      v-model="showFormToWordDialog" 
      title="付款申请表"
      @word-generated="handleWordGenerated"
    />

    <!-- 发票上传对话框 -->
    <el-dialog
      v-model="invoiceDialogVisible"
      title="上传发票凭证"
      width="700px"
      :close-on-click-modal="false"
    >
      <div v-if="currentInvoiceRequest">
        <el-descriptions :column="2" border style="margin-bottom: 20px;">
          <el-descriptions-item label="付款申请">{{ currentInvoiceRequest.title }}</el-descriptions-item>
          <el-descriptions-item label="保险类型">
            <el-tag :type="currentInvoiceRequest.insurance_type === 'social_security' ? 'success' : 'primary'">
              {{ currentInvoiceRequest.insurance_type === 'social_security' ? '社保' : '公积金' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="月份">{{ currentInvoiceRequest.month }}</el-descriptions-item>
          <el-descriptions-item label="发票状态">
            <el-tag v-if="currentInvoiceRequest.invoice_status === 'pending_invoice'" type="warning">待上传发票</el-tag>
            <el-tag v-else-if="currentInvoiceRequest.invoice_status === 'invoice_uploaded'" type="info">发票已上传</el-tag>
          </el-descriptions-item>
        </el-descriptions>

        <el-divider content-position="left">发票附件</el-divider>
        
        <div style="display: flex; gap: 10px; margin-bottom: 15px;">
          <el-upload
            ref="invoiceUploadRef"
            :action="getInvoiceUploadUrl()"
            :headers="uploadHeaders"
            :data="{ payment_request_id: currentInvoiceRequest.id }"
            name="file"
            :on-success="handleInvoiceUploadSuccess"
            :on-error="handleInvoiceUploadError"
            :show-file-list="false"
            :before-upload="beforeUpload"
          >
            <el-button type="primary" :icon="UploadFilled">上传发票文件</el-button>
          </el-upload>
          
          <el-button type="success" :icon="DocumentAdd" @click="showPaymentFormDialog = true">
            填写表单生成文件
          </el-button>
        </div>

        <el-table :data="invoiceAttachments" border v-loading="invoiceLoading">
          <el-table-column prop="filename" label="文件名" />
          <el-table-column label="大小" width="100">
            <template #default="{ row }">
              {{ formatFileSize(row.file_size) }}
            </template>
          </el-table-column>
          <el-table-column label="上传人" width="100">
            <template #default="{ row }">
              {{ row.uploader?.name || '-' }}
            </template>
          </el-table-column>
          <el-table-column label="上传时间" width="160">
            <template #default="{ row }">
              {{ formatDateTime(row.created_at) }}
            </template>
          </el-table-column>
          <el-table-column label="操作" width="120">
            <template #default="{ row }">
              <el-button link type="primary" :icon="View" @click="viewInvoiceAttachment(row)">查看</el-button>
              <el-button link type="danger" :icon="Delete" @click="handleDeleteInvoiceAttachment(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </div>

      <template #footer>
        <el-button @click="invoiceDialogVisible = false">取消</el-button>
        <el-button 
          type="primary" 
          :disabled="invoiceAttachments.length === 0"
          :loading="submitInvoiceLoading"
          @click="handleSubmitInvoiceApproval"
        >
          提交发票审批
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
        <el-button 
          type="primary" 
          @click="dialogMode === 'edit' ? confirmResubmit() : handleSubmit()"
        >
          确认提交
        </el-button>
      </template>
    </el-dialog>

    <!-- 情况说明单表单生成组件 -->
    <FormToWordGenerator 
      v-model="showPaymentFormDialog" 
      title="情况说明单"
      :only-situation="true"
      @word-generated="handlePaymentFormGenerated"
    />

    <!-- 附件补传对话框 -->
    <el-dialog
      v-model="supplementDialogVisible"
      title="上传附件"
      width="700px"
      :close-on-click-modal="false"
    >
      <div v-if="currentSupplementRequest">
        <el-descriptions :column="2" border style="margin-bottom: 20px;">
          <el-descriptions-item label="付款申请">{{ currentSupplementRequest.title }}</el-descriptions-item>
          <el-descriptions-item label="付款类型">
            <el-tag 
              :type="currentSupplementRequest.payment_type === 'salary' ? 'primary' : (currentSupplementRequest.payment_type === 'insurance' ? 'success' : 'warning')" 
              size="small"
            >
              {{ currentSupplementRequest.type_name }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="月份">{{ currentSupplementRequest.month }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag v-if="currentSupplementRequest.status === 'pending'" type="warning">待审批</el-tag>
            <el-tag v-else-if="currentSupplementRequest.status === 'approved'" type="success">已通过</el-tag>
          </el-descriptions-item>
        </el-descriptions>

        <el-divider content-position="left">上传附件</el-divider>
        
        <div style="display: flex; gap: 10px; margin-bottom: 15px;">
          <el-upload
            ref="supplementUploadRef"
            :action="getSupplementUploadUrl()"
            :headers="uploadHeaders"
            :data="{ payment_request_id: currentSupplementRequest.id }"
            name="file"
            :on-success="handleSupplementUploadSuccess"
            :on-error="handleSupplementUploadError"
            :show-file-list="false"
            :before-upload="beforeUpload"
            multiple
          >
            <el-button type="primary" :icon="UploadFilled">选择发票文件</el-button>
          </el-upload>
          
          <el-button type="success" :icon="DocumentAdd" @click="showSupplementFormDialog = true">
            填写表单生成文件
          </el-button>
        </div>

        <el-table :data="supplementAttachments" border v-loading="supplementLoading">
          <el-table-column prop="filename" label="文件名" />
          <el-table-column label="大小" width="100">
            <template #default="{ row }">
              {{ formatFileSize(row.file_size) }}
            </template>
          </el-table-column>
          <el-table-column label="上传人" width="100">
            <template #default="{ row }">
              {{ row.uploader?.name || '-' }}
            </template>
          </el-table-column>
          <el-table-column label="上传时间" width="160">
            <template #default="{ row }">
              {{ formatDateTime(row.created_at) }}
            </template>
          </el-table-column>
          <el-table-column label="操作" width="120">
            <template #default="{ row }">
              <el-button link type="primary" :icon="View" @click="viewSupplementAttachment(row)">查看</el-button>
              <el-button link type="danger" :icon="Delete" @click="handleDeleteSupplementAttachment(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </div>

      <template #footer>
        <el-button @click="supplementDialogVisible = false">取消</el-button>
        <el-button 
          type="primary" 
          :disabled="supplementAttachments.length === 0"
          :loading="submitSupplementLoading"
          @click="handleConfirmSupplement"
        >
          确认上传
        </el-button>
      </template>
    </el-dialog>

    <!-- 补传表单生成组件 -->
    <FormToWordGenerator 
      v-model="showSupplementFormDialog" 
      title="情况说明单"
      :only-situation="true"
      @word-generated="handleSupplementFormGenerated"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useRoute } from 'vue-router'
import {
  Search, Refresh, View, Promotion, Delete, UploadFilled, Download, DocumentAdd, RefreshRight
} from '@element-plus/icons-vue'
import { useAccountSetStore } from '@/stores/accountSet'
import { useUserStore } from '@/stores/user'
import FormToWordGenerator from '@/components/FormToWordGenerator.vue'
import PaymentAttachmentUploader from '@/components/PaymentAttachmentUploader.vue'
import {
  getPaymentApplications, getPaymentApplicationDetail, uploadAttachment,
  deleteAttachment, submitPaymentApplication, resubmitPaymentApplication, supplementAttachment,
  getPaymentRequestAttachments, deletePaymentRequestAttachment
} from '@/api/paymentApplication'
import { getProjects } from '@/api/projects'
import { PDFDocument } from 'pdf-lib'

const accountSetStore = useAccountSetStore()
const userStore = useUserStore()
const route = useRoute()

// 项目列表
const projectList = ref([])

// 筛选表单
const filterForm = reactive({
  payment_type: '',
  month: '',
  status: ''
})

// 列表数据
const loading = ref(false)
const applicationList = ref([])
const pagination = reactive({
  current: 1,
  pageSize: 15,
  total: 0
})

// 对话框
const dialogVisible = ref(false)
const dialogMode = ref('view')
const detailData = ref({})

// 盖章方式选择对话框
const submitStampDialogVisible = ref(false)
const submitStampForm = reactive({
  applicationId: null,
  stamp_method: 'online' // 默认线上盖章
})
const showFormToWordDialog = ref(false)

// 发票上传相关
const invoiceDialogVisible = ref(false)
const currentInvoiceRequest = ref(null)
const invoiceAttachments = ref([])
const invoiceLoading = ref(false)
const submitInvoiceLoading = ref(false)
const showPaymentFormDialog = ref(false)

// 附件补传相关
const supplementDialogVisible = ref(false)
const currentSupplementRequest = ref(null)
const supplementAttachments = ref([])
const supplementLoading = ref(false)
const submitSupplementLoading = ref(false)
const showSupplementFormDialog = ref(false)
const supplementUploadRef = ref(null)

// 上传相关
const uploadRef = ref()
const uploadHeaders = computed(() => ({
  'Authorization': `Bearer ${userStore.token}`,
  'X-Account-Set-Id': accountSetStore.currentAccountSetId
}))

// 获取上传URL
const getUploadUrl = (id) => {
  const baseURL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'
  return `${baseURL}/api/payment-applications/${id}/upload-attachment`
}

// 获取发票上传URL
const getInvoiceUploadUrl = () => {
  const baseURL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'
  return `${baseURL}/api/insurance-payment-requests/invoice-attachments/upload`
}

// 获取项目名称
const getProjectName = (projectId) => {
  const project = projectList.value.find(p => p && p.id === projectId)
  return project ? project.name : `项目#${projectId}`
}

// 获取当前审批人名称
const getCurrentApproverName = (row) => {
  if (!row.approval_instance || !row.approval_instance.records) {
    return '-'
  }
  const pendingRecord = row.approval_instance.records.find(r => r.status === 'pending')
  return pendingRecord ? pendingRecord.approver_name : '-'
}

// 格式化时间
const formatDateTime = (dateTime) => {
  if (!dateTime) return '-'
  // 如果是对象，转换为字符串
  if (typeof dateTime === 'object' && dateTime.date) {
    return dateTime.date.substring(0, 19).replace('T', ' ')
  }
  // 如果已经是字符串，直接返回前19位（YYYY-MM-DD HH:mm:ss）
  if (typeof dateTime === 'string') {
    return dateTime.substring(0, 19).replace('T', ' ')
  }
  return dateTime
}

// 格式化文件大小
const formatFileSize = (bytes) => {
  if (!bytes) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return (bytes / Math.pow(k, i)).toFixed(2) + ' ' + sizes[i]
}

// 加载项目列表
const loadProjects = async () => {
  try {
    const res = await getProjects({
      current_account_set_id: accountSetStore.currentAccountSetId
    })
    if (res.data && res.data.data) {
      projectList.value = res.data.data.filter(p => p && p.id)
    }
  } catch (error) {
    console.error('加载项目列表失败:', error)
  }
}

// 加载付款申请列表
const loadApplicationList = async () => {
  // 检查账套ID
  if (!accountSetStore.currentAccountSetId) {
    console.warn('未选择账套，跳过加载付款申请列表')
    return
  }

  loading.value = true
  try {
    const res = await getPaymentApplications({
      current_account_set_id: accountSetStore.currentAccountSetId,
      ...filterForm,
      page: pagination.current,
      per_page: pagination.pageSize
    })

    if (res.success && res.data) {
      applicationList.value = res.data.data || []
      pagination.total = res.data.total || 0
    }
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '获取列表失败')
  } finally {
    loading.value = false
  }
}

// 查询
const handleSearch = () => {
  pagination.current = 1
  loadApplicationList()
}

// 重置
const handleReset = () => {
  filterForm.payment_type = ''
  filterForm.month = ''
  filterForm.status = ''
  pagination.current = 1
  loadApplicationList()
}

// 查看详情
const handleView = async (row) => {
  try {
    const res = await getPaymentApplicationDetail(row.id)
    if (res.success) {
      detailData.value = res.data
      dialogMode.value = 'view'
      dialogVisible.value = true
    }
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '获取详情失败')
  }
}

// 打开盖章方式选择对话框
const openSubmitStampDialog = async (row) => {
  try {
    // 重新获取最新详情以验证附件
    const detailRes = await getPaymentApplicationDetail(row.id)
    if (!detailRes.success) {
      ElMessage.error('获取申请详情失败')
      return
    }
    
    const latestData = detailRes.data
    
    // 验证必须至少有1个附件
    if (!latestData.attachments || latestData.attachments.length === 0) {
      ElMessage.warning('请至少上传1个附件才能提交')
      return
    }
    
    // 打开盖章方式选择对话框
    submitStampForm.applicationId = row.id
    submitStampDialogVisible.value = true
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '获取详情失败')
  }
}

// 提交审批
const handleSubmit = async () => {
  try {
    const res = await submitPaymentApplication(submitStampForm.applicationId, {
      current_account_set_id: accountSetStore.currentAccountSetId,
      stamp_method: submitStampForm.stamp_method
    })
    
    if (res.success) {
      ElMessage.success('提交成功')
      submitStampDialogVisible.value = false
      dialogVisible.value = false
      // 重置盖章方式为默认值
      submitStampForm.stamp_method = 'online'
      loadApplicationList()
    } else {
      ElMessage.error(res.message || '提交失败')
    }
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '提交失败')
  }
}

// 重新申请(用于被驳回的付款申请)
const handleResubmit = async (row) => {
  try {
    // 获取详情数据
    const res = await getPaymentApplicationDetail(row.id)
    if (res.success) {
      detailData.value = res.data
      dialogMode.value = 'edit' // 设置为编辑模式
      dialogVisible.value = true
    }
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '获取详情失败')
  }
}

// 提交重新申请
const handleResubmitConfirm = async () => {
  try {
    // 验证必须至少有1个附件
    if (!detailData.value.attachments || detailData.value.attachments.length === 0) {
      ElMessage.warning('请至少上传1个附件才能提交')
      return
    }
    
    // 打开盖章方式选择对话框
    submitStampForm.applicationId = detailData.value.id
    submitStampDialogVisible.value = true
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '操作失败')
  }
}

// 确认重新提交(带盖章方式)
const confirmResubmit = async () => {
  try {
    const submitData = {
      current_account_set_id: accountSetStore.currentAccountSetId,
      title: detailData.value.title,
      month: detailData.value.month,
      project_ids: detailData.value.project_ids,
      description: detailData.value.description,
      stamp_method: submitStampForm.stamp_method
    }

    // 如果有报销表单数据，也一起提交
    if (detailData.value.reimbursement_form) {
      submitData.reimbursement_form = detailData.value.reimbursement_form
    }

    const res = await resubmitPaymentApplication(submitStampForm.applicationId, submitData)
    
    if (res.success) {
      ElMessage.success('重新申请已提交审批')
      submitStampDialogVisible.value = false
      dialogVisible.value = false
      submitStampForm.stamp_method = 'online'
      loadApplicationList()
    } else {
      ElMessage.error(res.message || '重新申请失败')
    }
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '重新申请失败')
  }
}

// 处理Word文档生成
const handleWordGenerated = async ({ file, fileName }) => {
  if (!detailData.value.id) {
    ElMessage.error('请先保存申请')
    return
  }
  
  console.log('Word文档已生成:', fileName)
  
  try {
    // 创建FormData并上传
    const formData = new FormData()
    formData.append('file', file, fileName)
    
    // 上传文件
    const response = await uploadAttachment(detailData.value.id, formData)
    
    if (response && response.success) {
      ElMessage.success('Word文档已自动上传')
      // 刷新详情
      await handleView({ id: detailData.value.id })
    }
  } catch (error) {
    console.error('上传Word文档失败:', error)
    ElMessage.error('Word文档上传失败: ' + (error.response?.data?.message || error.message))
  }
}

// 文件上传前检查
const beforeUpload = (file) => {
  const maxSize = 10 * 1024 * 1024 // 10MB
  if (file.size > maxSize) {
    ElMessage.error('文件大小不能超过 10MB')
    return false
  }
  return true
}

// 上传成功
const handleUploadSuccess = (response) => {
  if (response.success) {
    ElMessage.success('附件上传成功')
    // 刷新详情
    handleView({ id: detailData.value.id })
  } else {
    ElMessage.error(response.message || '上传失败')
  }
}

// 上传失败
const handleUploadError = (error) => {
  ElMessage.error('上传失败，请重试')
  console.error('上传错误:', error)
}

// 删除附件
const handleDeleteAttachment = async (attachment) => {
  try {
    await ElMessageBox.confirm('确认删除此附件吗？', '提示', {
      confirmButtonText: '确认',
      cancelButtonText: '取消',
      type: 'warning'
    })

    const res = await deleteAttachment(detailData.value.id, attachment.id)
    if (res.success) {
      ElMessage.success('删除成功')
      // 刷新详情
      handleView({ id: detailData.value.id })
    }
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }
}

// 查看附件（在新标签页打开）
const viewAttachment = (attachment) => {
  const baseURL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'
  const url = `${baseURL}/${attachment.path}`
  window.open(url, '_blank')
}

// ========== 发票上传相关方法 ==========

// 打开发票上传对话框
const openInvoiceUploadDialog = async (row) => {
  currentInvoiceRequest.value = row
  invoiceDialogVisible.value = true
  await loadInvoiceAttachments(row.id)
}

// 加载发票附件列表
const loadInvoiceAttachments = async (paymentRequestId) => {
  invoiceLoading.value = true
  try {
    const baseURL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'
    const response = await fetch(`${baseURL}/api/insurance-payment-requests/invoice-attachments?payment_request_id=${paymentRequestId}`, {
      headers: {
        'Authorization': `Bearer ${userStore.token}`,
        'X-Account-Set-Id': accountSetStore.currentAccountSetId
      }
    })
    const res = await response.json()
    if (res.success) {
      invoiceAttachments.value = res.data || []
    }
  } catch (error) {
    console.error('加载发票附件失败:', error)
  } finally {
    invoiceLoading.value = false
  }
}

// 发票上传成功
const handleInvoiceUploadSuccess = (response) => {
  if (response.success) {
    ElMessage.success('发票上传成功')
    loadInvoiceAttachments(currentInvoiceRequest.value.id)
    // 更新当前请求的发票状态
    currentInvoiceRequest.value.invoice_status = 'invoice_uploaded'
  } else {
    ElMessage.error(response.message || '上传失败')
  }
}

// 发票上传失败
const handleInvoiceUploadError = (error) => {
  ElMessage.error('发票上传失败，请重试')
  console.error('发票上传错误:', error)
}

// 查看发票附件
const viewInvoiceAttachment = (attachment) => {
  const baseURL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'
  const url = `${baseURL}/${attachment.file_path}`
  window.open(url, '_blank')
}

// 删除发票附件
const handleDeleteInvoiceAttachment = async (attachment) => {
  try {
    await ElMessageBox.confirm('确认删除此发票附件吗？', '提示', {
      confirmButtonText: '确认',
      cancelButtonText: '取消',
      type: 'warning'
    })

    const baseURL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'
    const response = await fetch(`${baseURL}/api/insurance-payment-requests/invoice-attachments`, {
      method: 'DELETE',
      headers: {
        'Authorization': `Bearer ${userStore.token}`,
        'X-Account-Set-Id': accountSetStore.currentAccountSetId,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ id: attachment.id })
    })
    const res = await response.json()
    
    if (res.success) {
      ElMessage.success('删除成功')
      loadInvoiceAttachments(currentInvoiceRequest.value.id)
    } else {
      ElMessage.error(res.message || '删除失败')
    }
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error('删除失败')
    }
  }
}

// 提交发票审批
const handleSubmitInvoiceApproval = async () => {
  if (invoiceAttachments.value.length === 0) {
    ElMessage.warning('请先上传发票附件')
    return
  }

  try {
    await ElMessageBox.confirm(
      '确认提交发票审批吗？提交后将进入审批流程。',
      '提示',
      {
        confirmButtonText: '确认提交',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    submitInvoiceLoading.value = true
    
    // 1. 先合成PDF（更新发票和附件数量）
    ElMessage.info('正在更新付款申请单...')
    await mergePdfWithCounts()
    
    // 2. 提交发票审批
    const baseURL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'
    const response = await fetch(`${baseURL}/api/insurance-payment-requests/submit-invoice-approval`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${userStore.token}`,
        'X-Account-Set-Id': accountSetStore.currentAccountSetId,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ payment_request_id: currentInvoiceRequest.value.id })
    })
    const res = await response.json()

    if (res.success) {
      ElMessage.success('发票审批流程已创建')
      invoiceDialogVisible.value = false
      loadApplicationList()
    } else {
      ElMessage.error(res.message || '提交失败')
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('提交发票审批失败:', error)
      ElMessage.error('提交失败: ' + (error.message || '未知错误'))
    }
  } finally {
    submitInvoiceLoading.value = false
  }
}

// 处理付款申请单表单生成的文件
const handlePaymentFormGenerated = async ({ file, fileName }) => {
  if (!currentInvoiceRequest.value) {
    ElMessage.error('请先选择付款申请')
    return
  }
  
  console.log('付款申请单已生成:', fileName)
  
  try {
    // 上传生成的文件作为发票附件
    const formData = new FormData()
    formData.append('file', file, fileName)
    formData.append('payment_request_id', currentInvoiceRequest.value.id)
    formData.append('current_account_set_id', accountSetStore.currentAccountSetId)
    
    const baseURL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'
    const response = await fetch(`${baseURL}/api/insurance-payment-requests/invoice-attachments/upload`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${userStore.token}`,
        'X-Account-Set-Id': accountSetStore.currentAccountSetId
      },
      body: formData
    })
    
    const res = await response.json()
    if (res.success) {
      ElMessage.success('付款申请单已自动上传')
      // 重新加载发票附件列表
      loadInvoiceAttachments(currentInvoiceRequest.value.id)
      // 更新发票状态
      currentInvoiceRequest.value.invoice_status = 'invoice_uploaded'
    } else {
      ElMessage.error(res.message || '上传失败')
    }
  } catch (error) {
    console.error('上传付款申请单失败:', error)
    ElMessage.error('上传失败: ' + (error.message || '未知错误'))
  }
}

// 合成PDF - 在付款申请单上添加发票和附件数量
const mergePdfWithCounts = async () => {
  if (!currentInvoiceRequest.value) {
    return
  }

  try {
    // 1. 获取付款申请的附件列表
    const baseURL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'
    const detailResponse = await fetch(`${baseURL}/api/payment-applications/${currentInvoiceRequest.value.id}`, {
      headers: {
        'Authorization': `Bearer ${userStore.token}`,
        'X-Account-Set-Id': accountSetStore.currentAccountSetId
      }
    })
    const detailRes = await detailResponse.json()
    
    if (!detailRes.success) {
      console.warn('获取付款申请详情失败，跳过PDF合成')
      return
    }

    const attachments = detailRes.data.attachments || []
    
    // 2. 查找文件名包含"付款申请单"的PDF文件
    const pdfAttachment = attachments.find(att => 
      att.filename && 
      att.filename.includes('付款申请单') && 
      att.filename.toLowerCase().endsWith('.pdf')
    )

    if (!pdfAttachment) {
      console.warn('未找到付款申请单PDF，跳过合成')
      return
    }

    console.log('找到付款申请单PDF:', pdfAttachment.filename)

    // 3. 下载PDF文件
    let pdfPath = pdfAttachment.path || pdfAttachment.file_path
    if (pdfPath.includes('localhost:8000')) {
      pdfPath = pdfPath.replace('http://localhost:8000', '')
    }
    if (!pdfPath.startsWith('/')) {
      pdfPath = '/' + pdfPath
    }
    
    const pdfResponse = await fetch(pdfPath)
    if (!pdfResponse.ok) {
      throw new Error(`下载PDF失败: HTTP ${pdfResponse.status}`)
    }
    const pdfBytes = await pdfResponse.arrayBuffer()

    // 4. 使用pdf-lib加载PDF
    const pdfDoc = await PDFDocument.load(pdfBytes)
    const pages = pdfDoc.getPages()
    const firstPage = pages[0]
    const { width, height } = firstPage.getSize()

    // 5. 计算发票和附件数量
    // 原始发票数量 + 新上传的发票凭证数量
    const originalInvoiceCount = attachments.filter(a => a.attachment_type === 'invoice').length
    const newInvoiceCount = invoiceAttachments.value.length
    const totalInvoiceCount = originalInvoiceCount + newInvoiceCount
    
    // 附件数量（不包括发票）
    const attachmentCount = attachments.filter(a => a.attachment_type === 'attachment' || !a.attachment_type).length

    console.log(`发票数量: ${totalInvoiceCount}, 附件数量: ${attachmentCount}`)

    // 6. 在PDF右上角添加带文字的图片
    const rectX = width - 90
    const rectY = height - 200
    const rectWidth = 55
    const rectHeight = 80

    // 7. 创建带中文文字的图片并嵌入PDF
    // 因为pdf-lib不支持中文字体，我们用canvas绘制文字然后嵌入为图片
    const canvas = document.createElement('canvas')
    canvas.width = rectWidth * 2  // 2倍分辨率
    canvas.height = rectHeight * 2
    const ctx = canvas.getContext('2d')
    
    // 绘制白色背景
    ctx.fillStyle = 'white'
    ctx.fillRect(0, 0, canvas.width, canvas.height)
    
    // 绘制文字
    ctx.fillStyle = '#666666'
    ctx.font = '24px SimSun, serif'  // 使用宋体
    ctx.textAlign = 'center'
    
    // 发票n张
    ctx.fillText(`发票${totalInvoiceCount}张`, canvas.width / 2, 50)
    // 附件n张
    ctx.fillText(`附件${attachmentCount}张`, canvas.width / 2, 110)
    
    // 将canvas转为PNG图片
    const imageDataUrl = canvas.toDataURL('image/png')
    const imageBytes = Uint8Array.from(atob(imageDataUrl.split(',')[1]), c => c.charCodeAt(0))
    const image = await pdfDoc.embedPng(imageBytes)
    
    // 在PDF上绘制图片
    firstPage.drawImage(image, {
      x: rectX,
      y: rectY,
      width: rectWidth,
      height: rectHeight,
    })

    // 8. 保存修改后的PDF
    const modifiedPdfBytes = await pdfDoc.save()
    
    // 9. 上传替换原PDF文件
    const blob = new Blob([modifiedPdfBytes], { type: 'application/pdf' })
    const formData = new FormData()
    formData.append('file', blob, pdfAttachment.filename)
    formData.append('payment_request_id', currentInvoiceRequest.value.id)
    formData.append('attachment_id', pdfAttachment.id) // 用于替换原文件
    formData.append('current_account_set_id', accountSetStore.currentAccountSetId)
    
    const uploadResponse = await fetch(`${baseURL}/api/insurance-payment-requests/attachments/replace`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${userStore.token}`,
        'X-Account-Set-Id': accountSetStore.currentAccountSetId
      },
      body: formData
    })
    
    const uploadRes = await uploadResponse.json()
    if (uploadRes.success) {
      console.log('PDF合成并替换成功')
    } else {
      console.warn('PDF替换失败:', uploadRes.message)
    }

  } catch (error) {
    console.error('PDF合成失败:', error)
    // 不阻止提交，只是记录错误
  }
}

// ========== 附件补传相关方法 ==========

// 打开附件补传对话框
const handleSupplementAttachment = async (row) => {
  currentSupplementRequest.value = row
  supplementDialogVisible.value = true
  await loadSupplementAttachments(row.id)
}

// 加载补传附件列表
const loadSupplementAttachments = async (paymentRequestId) => {
  try {
    supplementLoading.value = true
    // 使用现有的附件查询接口
    const res = await getPaymentRequestAttachments(paymentRequestId)
    if (res.success) {
      supplementAttachments.value = res.data || []
    }
  } catch (error) {
    console.error('加载附件列表失败:', error)
  } finally {
    supplementLoading.value = false
  }
}

// 处理补传附件上传成功
const handleSupplementUploadSuccess = (response, file) => {
  if (response.success) {
    ElMessage.success('文件上传成功')
    loadSupplementAttachments(currentSupplementRequest.value.id)
  } else {
    ElMessage.error(response.message || '上传失败')
  }
}

// 处理补传附件上传失败
const handleSupplementUploadError = (error) => {
  console.error('上传失败:', error)
  ElMessage.error('文件上传失败')
}

// 查看补传附件
const viewSupplementAttachment = (attachment) => {
  const host = import.meta.env.VITE_API_BASE_URL || ''
  const url = `${host}/${attachment.file_path}`
  window.open(url, '_blank')
}

// 删除补传附件
const handleDeleteSupplementAttachment = async (attachment) => {
  try {
    await ElMessageBox.confirm('确定要删除这个附件吗？', '提示', {
      type: 'warning'
    })

    const res = await deletePaymentRequestAttachment(currentSupplementRequest.value.id, attachment.id)
    if (res.success) {
      ElMessage.success('删除成功')
      loadSupplementAttachments(currentSupplementRequest.value.id)
    } else {
      ElMessage.error(res.message || '删除失败')
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除附件失败:', error)
      ElMessage.error('删除失败')
    }
  }
}

// 处理补传表单生成
const handleSupplementFormGenerated = ({ file, fileName }) => {
  // 直接上传生成的文件
  const formData = new FormData()
  formData.append('file', file)
  formData.append('payment_request_id', currentSupplementRequest.value.id)

  // 使用上传接口
  const url = getSupplementUploadUrl()
  fetch(url, {
    method: 'POST',
    headers: uploadHeaders.value,
    body: formData
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      ElMessage.success('文件已生成并上传')
      loadSupplementAttachments(currentSupplementRequest.value.id)
    } else {
      ElMessage.error(res.message || '上传失败')
    }
  })
  .catch(error => {
    console.error('上传失败:', error)
    ElMessage.error('上传失败')
  })
}

// 确认补传（更新upload_later标识）
const handleConfirmSupplement = async () => {
  try {
    if (supplementAttachments.value.length === 0) {
      ElMessage.warning('请至少上传一个文件')
      return
    }

    submitSupplementLoading.value = true

    // 调用接口更新upload_later标识
    const formData = new FormData()
    formData.append('current_account_set_id', accountSetStore.currentAccountSetId)
    formData.append('_method', 'PUT')

    const res = await supplementAttachment(currentSupplementRequest.value.id, formData)

    if (res.success) {
      ElMessage.success('附件补传完成')
      supplementDialogVisible.value = false
      loadApplicationList()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (error) {
    console.error('确认补传失败:', error)
    ElMessage.error(error.response?.data?.message || '操作失败')
  } finally {
    submitSupplementLoading.value = false
  }
}

// 获取补传上传URL
const getSupplementUploadUrl = () => {
  return `${import.meta.env.VITE_API_BASE_URL || ''}/api/payment-request-attachments`
}

// 组件挂载时
onMounted(async () => {
  // 等待账套加载完成
  if (!accountSetStore.currentAccountSetId) {
    // 如果当前没有账套，等待一下
    await new Promise(resolve => setTimeout(resolve, 500))
  }
  
  await loadProjects()
  
  // 先加载列表
  await loadApplicationList()
  
  // 如果URL中有id参数，显示成功提示，但不自动打开详情
  if (route.query.id) {
    ElMessage.success('付款申请已创建成功，可以在列表中查看')
  }
})
</script>

<style scoped>
.payment-application-container {
  padding: 20px;
}

.filter-card {
  margin-bottom: 20px;
}

.table-card {
  margin-bottom: 20px;
}

.pagination-container {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.form-actions {
  margin-top: 20px;
  text-align: right;
}
</style>

