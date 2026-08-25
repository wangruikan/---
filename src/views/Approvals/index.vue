<template>
  <div class="approvals-page">
    <div class="page-header">
      <h1>审批管理</h1>
    </div>
    
    <!-- Tab 切换 -->
    <el-tabs v-model="activeTab" @tab-click="handleTabClick" class="approval-tabs">
      <el-tab-pane label="我的待办" name="my-tasks">
        <template #label>
          <span>
            我的待办
            <el-badge v-if="taskCount > 0" :value="taskCount" class="tab-badge" />
          </span>
        </template>
      </el-tab-pane>
      <el-tab-pane label="我审批的" name="my-approved" />
      <el-tab-pane label="我发起的" name="my-initiated" />
      <el-tab-pane label="抄送给我" name="cc-to-me">
        <template #label>
          <span>
            抄送给我
            <el-badge v-if="ccCount > 0" :value="ccCount" class="tab-badge" />
          </span>
        </template>
      </el-tab-pane>
    </el-tabs>
    
    <!-- 搜索和筛选 -->
    <div class="search-section" v-if="false">
      <el-card>
        <el-form :model="searchForm" inline>
          <el-form-item label="审批类型">
            <el-select
              v-model="searchForm.type"
              placeholder="请选择审批类型"
              clearable
            >
              <el-option label="请假申请" value="leave" />
              <el-option label="加班申请" value="overtime" />
              <el-option label="出差申请" value="business_trip" />
              <el-option label="费用报销" value="expense" />
              <el-option label="其他" value="other" />
            </el-select>
          </el-form-item>
          
          <el-form-item label="申请人">
            <el-input
              v-model="searchForm.applicant_name"
              placeholder="请输入申请人姓名"
              clearable
              @keyup.enter="handleSearch"
            />
          </el-form-item>
          
          <el-form-item label="状态">
            <el-select
              v-model="searchForm.status"
              placeholder="请选择状态"
              clearable
            >
              <el-option label="待审批" value="pending" />
              <el-option label="已通过" value="approved" />
              <el-option label="已拒绝" value="rejected" />
              <el-option label="已退回" value="returned" />
            </el-select>
          </el-form-item>
          
          <el-form-item label="创建时间">
            <el-date-picker
              v-model="searchForm.date_range"
              type="daterange"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              format="YYYY-MM-DD"
              value-format="YYYY-MM-DD"
            />
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
    
    <!-- 审批列表 -->
    <div class="table-section">
      <el-card>
        <el-table
          :data="approvals"
          v-loading="loading"
          stripe
          border
        >
          <el-table-column prop="business_type" label="业务类型" width="140">
            <template #default="{ row }">
              {{ getBusinessTypeText(row) }}
            </template>
          </el-table-column>
          <el-table-column v-if="activeTab === 'my-tasks'" label="当前步骤" width="120">
            <template #default="{ row }">
              {{ row.step_name }}
            </template>
          </el-table-column>
          <el-table-column v-if="activeTab !== 'my-tasks'" label="发起人" width="120">
            <template #default="{ row }">
              {{ getInitiatorName(row) }}
            </template>
          </el-table-column>
          <el-table-column label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="getStatusType(row.status)">
                {{ getStatusText(row.status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="盖章方式" width="100">
            <template #default="{ row }">
              <el-tag v-if="row.instance?.stamp_method || row.stamp_method" :type="(row.instance?.stamp_method || row.stamp_method) === 'online' ? 'success' : 'warning'" size="small">
                {{ getStampMethodText(row.instance?.stamp_method || row.stamp_method) }}
              </el-tag>
              <span v-else>-</span>
            </template>
          </el-table-column>
          <el-table-column prop="created_at" label="申请时间" width="180">
            <template #default="{ row }">
              <span v-date-time="row.created_at"></span>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="200" fixed="right">
            <template #default="{ row }">
              <el-button type="primary" size="small" @click="handleViewDetail(row)">
                查看
              </el-button>
              <el-button 
                v-if="activeTab === 'my-tasks' && row.status === 'pending'" 
                type="success" 
                size="small" 
                @click="handleApprove(row)"
              >
                审批
              </el-button>
              <el-button 
                v-if="activeTab === 'my-initiated' && canWithdraw(row)" 
                type="warning" 
                size="small" 
                @click="handleWithdraw(row)"
              >
                撤回
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
    
    <!-- 审批详情对话框 -->
    <el-dialog
      v-model="showDetailDialog"
      title="审批详情"
      width="900px"
      @close="handleDetailDialogClose"
    >
      <div v-if="currentDetail" class="approval-detail">
        <!-- 业务信息 -->
        <div class="detail-section">
          <h3>业务信息</h3>
          <el-descriptions :column="2" border>
            <el-descriptions-item label="业务类型">
              {{ getBusinessTypeText(currentDetail) }}
            </el-descriptions-item>
            <el-descriptions-item label="发起人">
              {{ currentDetail.creator?.name }}
            </el-descriptions-item>
            <el-descriptions-item label="员工姓名" v-if="currentDetail.business_type === 'employee_contract' && currentDetail.business_data?.employee">
              {{ currentDetail.business_data.employee.name }}
            </el-descriptions-item>
            <el-descriptions-item label="项目名称" v-if="currentDetail.business_type === 'employee_contract' && getEmployeeContractProjectNames(currentDetail.business_data)">
              {{ getEmployeeContractProjectNames(currentDetail.business_data) }}
            </el-descriptions-item>
            <el-descriptions-item label="合同类型" v-if="currentDetail.business_type === 'employee_contract' && currentDetail.business_data?.contract_type">
              {{ getContractTypeText(currentDetail.business_data.contract_type) }}
            </el-descriptions-item>
            <el-descriptions-item label="项目名称" v-if="currentDetail.business_type === '工资表审批' && currentDetail.business_data?.project">
              {{ currentDetail.business_data.project.name }}
            </el-descriptions-item>
            <el-descriptions-item label="工资月份" v-if="currentDetail.business_type === '工资表审批' && currentDetail.business_data?.month">
              {{ currentDetail.business_data.month }}
            </el-descriptions-item>
            <el-descriptions-item label="盖章方式" v-if="currentDetail.business_type === '工资表审批' && currentDetail.business_data?.approval_type">
              <el-tag :type="currentDetail.business_data.approval_type === 'online' ? 'success' : 'warning'" size="small">
                {{ currentDetail.business_data.approval_type === 'online' ? '线上' : '线下' }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="创建时间">
              {{ formatDateTime(currentDetail.created_at) }}
            </el-descriptions-item>
            <el-descriptions-item label="当前状态">
              <el-tag :type="getStatusType(currentDetail.status)">
                {{ getStatusText(currentDetail.status) }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="盖章方式" v-if="currentDetail.instance?.stamp_method || currentDetail.stamp_method">
              <el-tag :type="(currentDetail.instance?.stamp_method || currentDetail.stamp_method) === 'online' ? 'success' : 'warning'" size="small">
                {{ getStampMethodText(currentDetail.instance?.stamp_method || currentDetail.stamp_method) }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="审批用章" :span="2" v-if="currentDetail.stamp_selection_mode">
              <span v-if="currentDetail.stamp_selection_mode === 'none'">无</span>
              <span v-else>
                {{ currentDetail.stamp_company || '-' }} / {{ getSelectedStampTypeText(currentDetail.stamp_type) }}
              </span>
            </el-descriptions-item>
            <el-descriptions-item label="附件" :span="2">
              <div class="attachments-section">
                <!-- 附件列表 -->
                <div class="attachments-list" v-if="currentDetail.attachments && currentDetail.attachments.length > 0">
                  <div v-for="att in currentDetail.attachments" :key="att.id" class="attachment-item">
                    <el-link type="primary" @click="handleViewAttachment(att)">
                      <el-icon><Document /></el-icon>
                      {{ att.file_name }}
                    </el-link>
                    <el-button type="primary" size="small" style="margin-left: 10px" @click="handleDownloadAttachment(att)">
                      下载
                    </el-button>
                    <!-- 删除按钮暂时隐藏
                    <el-button type="danger" size="small" style="margin-left: 5px" @click="handleDeleteInstanceAttachment(att)">
                      删除
                    </el-button>
                    -->
                  </div>
                </div>
                <div v-else style="color: #999; margin-bottom: 10px;">暂无附件</div>
                
                <!-- 上传附件按钮 -->
                <el-upload
                  ref="instanceAttachmentUpload"
                  :http-request="handleInstanceAttachmentUpload"
                  :show-file-list="false"
                  :before-upload="beforeInstanceAttachmentUpload"
                  style="margin-top: 10px;"
                >
                  <el-button type="success" size="small">
                    <el-icon><Upload /></el-icon>
                    上传附件
                  </el-button>
                </el-upload>
              </div>
            </el-descriptions-item>
            
            <!-- 保险汇总详情显示 -->
            <el-descriptions-item label="流程详情" :span="2" v-if="['保险汇总', '文件盖章'].includes(currentDetail.business_type) && currentDetail.business_data">
              <div class="process-detail">
                <div class="process-info">
                  <p><strong>{{ currentDetail.business_type === '文件盖章' ? '文件名称' : '流程标题' }}：</strong>{{ currentDetail.business_data.title || '-' }}</p>
                  <p v-if="currentDetail.business_type !== '文件盖章'"><strong>月份：</strong>{{ currentDetail.business_data.month || '-' }}</p>
                  <p><strong>发起人：</strong>{{ currentDetail.business_data.initiator?.name || '-' }}</p>
                  <template v-if="currentDetail.business_data.category === 'social_detail_edit'">
                    <p><strong>修改原因：</strong>{{ currentDetail.business_data.social_detail_edit_data?.reason || '-' }}</p>
                    <p>
                      <strong>社保基数：</strong>
                      {{ formatApprovalEditBase(currentDetail.business_data.social_detail_edit_data?.before?.social_security_base) }}
                      →
                      {{ formatApprovalEditBase(currentDetail.business_data.social_detail_edit_data?.after?.social_security_base) }}
                    </p>
                    <p>
                      <strong>医保基数：</strong>
                      {{ formatApprovalEditBase(currentDetail.business_data.social_detail_edit_data?.before?.medical_insurance_base) }}
                      →
                      {{ formatApprovalEditBase(currentDetail.business_data.social_detail_edit_data?.after?.medical_insurance_base) }}
                    </p>
                  </template>
                  <p v-else><strong>描述：</strong>{{ currentDetail.business_data.description || '-' }}</p>
                  <p v-if="currentDetail.business_data.attachments && currentDetail.business_data.attachments.length > 0">
                    <strong>附件数量：</strong>{{ currentDetail.business_data.attachments.length }} 个
                  </p>
                </div>
              </div>
            </el-descriptions-item>
            
            <!-- 付款申请详情显示 -->
            <el-descriptions-item label="付款申请详情" :span="2" v-if="currentDetail.business_type === '付款申请' && currentDetail.business_data">
              <div class="payment-detail">
                <div class="payment-info">
                  <p><strong>申请标题：</strong>{{ currentDetail.business_data.title || '-' }}</p>
                  <p><strong>月份：</strong>{{ currentDetail.business_data.month || '-' }}</p>
                  <p><strong>发起人：</strong>{{ currentDetail.business_data.initiator?.name || '-' }}</p>
                  <p><strong>关联汇总申请ID：</strong>{{ currentDetail.business_data.process_approval_id || '-' }}</p>
                  <p><strong>描述：</strong>{{ currentDetail.business_data.description || '-' }}</p>
                  <p v-if="currentDetail.business_data.attachments && currentDetail.business_data.attachments.length > 0">
                    <strong>附件数量：</strong>{{ currentDetail.business_data.attachments.length }} 个
                  </p>
                </div>
              </div>
            </el-descriptions-item>
            
            <!-- 考勤表详情显示 -->
            <el-descriptions-item label="考勤详情" :span="2" v-if="currentDetail.business_type === '考勤申请' && currentDetail.business_data">
              <div class="attendance-detail">
                <div class="attendance-info">
                  <p><strong>项目：</strong>{{ currentDetail.business_data.project?.name || '-' }}</p>
                  <p><strong>月份：</strong>{{ currentDetail.business_data.month || '-' }}</p>
                  <p><strong>员工总数：</strong>{{ currentDetail.business_data.total_employees || 0 }}</p>
                  <p><strong>工作日：</strong>{{ currentDetail.business_data.work_days || 0 }}</p>
                </div>
                
                <!-- 考勤数据表格 -->
                <div v-if="currentDetail.business_data.attendance_data && currentDetail.business_data.attendance_data.length > 0" class="attendance-table">
                  <h4>考勤数据</h4>
                  <el-table :data="currentDetail.business_data.attendance_data" size="small" border>
                    <el-table-column prop="employee_name" label="员工姓名" width="120" />
                    <el-table-column prop="work_days" label="应出勤" width="80" />
                    <el-table-column prop="actual_work_days" label="实际出勤" width="90" />
                    <el-table-column prop="absent_days" label="缺勤" width="80" />
                    <el-table-column prop="attendance_rate" label="出勤率" width="80">
                      <template #default="{ row }">
                        {{ (row.attendance_rate * 100).toFixed(1) }}%
                      </template>
                    </el-table-column>
                  </el-table>
                </div>
              </div>
            </el-descriptions-item>
          </el-descriptions>
        </div>
        
        <!-- 审批流程 -->
        <div class="detail-section">
          <h3>审批流程</h3>
          <div class="approval-timeline">
            <div 
              v-for="(record, index) in getApprovalRecords()"
              :key="record.id"
              class="timeline-item"
              :class="getTimelineItemClass(record)"
            >
              <div class="timeline-marker">
                <div class="marker-icon">
                  <el-icon v-if="record.status === 'approved'"><CircleCheck /></el-icon>
                  <el-icon v-else-if="record.status === 'rejected'"><CircleClose /></el-icon>
                  <el-icon v-else-if="record.status === 'pending'"><Clock /></el-icon>
                  <el-icon v-else><Minus /></el-icon>
              </div>
                <div v-if="index < getApprovalRecords().length - 1" class="marker-line"></div>
              </div>
              <div class="timeline-content">
                <div class="timeline-header">
                  <span class="step-name">{{ record.step_name }}</span>
                  <el-tag :type="getRecordTagType(record)" size="small">
                    {{ getRecordStatusText(record.status) }}
                  </el-tag>
                </div>
                <div class="timeline-body">
                  <div class="timeline-info">
                    <el-icon><User /></el-icon>
                    <span>审批人：{{ record.approver_name }}</span>
                  </div>
                  <div v-if="record.approved_at" class="timeline-info">
                    <el-icon><Clock /></el-icon>
                    <span>审批时间：{{ formatDateTime(record.approved_at) }}</span>
                  </div>
                  <div v-if="record.comment" class="timeline-comment">
                    <el-icon><ChatLineSquare /></el-icon>
                    <span>审批意见：{{ record.comment }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <template #footer>
        <el-button @click="showDetailDialog = false">关闭</el-button>
      </template>
    </el-dialog>
    
    <!-- 审批操作对话框 -->
    <el-dialog
      v-model="showActionDialog"
      title="审批操作"
      width="600px"
      @close="handleActionDialogClose"
    >
      <el-form
        ref="actionFormRef"
        :model="actionForm"
        :rules="actionFormRules"
        label-width="100px"
      >
        <!-- 我的签名和印章已隐藏 -->
        
        <el-form-item 
          :label="actionType === 'approve' ? '审批意见（可选）' : '退回/驳回原因'" 
          prop="comment"
          :required="actionType === 'return' || actionType === 'reject'"
        >
          <el-input
            v-model="actionForm.comment"
            type="textarea"
            :rows="3"
            :placeholder="actionType === 'approve' ? '请输入审批意见（可选）' : '请输入原因（必填）'"
          />
        </el-form-item>
        
        <el-form-item label="抄送人员">
          <el-select
            v-model="actionForm.cc_users"
            multiple
            placeholder="选择抄送人员（可选）"
            style="width: 100%"
            filterable
          >
            <el-option
              v-for="user in availableUsers"
              :key="user.id"
              :label="user.name"
              :value="user.id"
            />
          </el-select>
          <div class="form-tip">💡 抄送人员会收到通知并可以查看审批进度</div>
        </el-form-item>

        <el-alert
          v-if="requiresApprovalStampBeforeApprove"
          :title="approvalStampAlertTitle"
          :type="approvalStampPreviewReady ? 'success' : 'warning'"
          :closable="false"
          style="margin-bottom: 12px;"
        />
      </el-form>
      
      <template #footer>
        <el-button @click="showActionDialog = false">取消</el-button>
        <el-button 
          v-if="canShowApprovalStampButton"
          :type="approvalStampPreviewReady ? 'success' : 'warning'"
          @click="openPDFEditor"
        >
          <el-icon><Edit /></el-icon>
          {{ approvalStampPreviewReady ? '已预览' : '签名盖章' }}
        </el-button>
        <el-button 
          type="success" 
          @click="handleActionSubmit('approve')" 
          :loading="submitting && actionType === 'approve'"
        >
          通过
        </el-button>
        <el-button 
          v-if="!isFirstStep"
          type="warning" 
          @click="handleActionSubmit('return')" 
          :loading="submitting && actionType === 'return'"
        >
          退回
        </el-button>
        <el-button 
          type="danger" 
          @click="handleActionSubmit('reject')" 
          :loading="submitting && actionType === 'reject'"
        >
          驳回
        </el-button>
      </template>
    </el-dialog>
    
    <!-- 文件选择对话框 -->
    <el-dialog
      v-model="showPDFSelector"
      title="选择文件"
      width="600px"
      :close-on-click-modal="false"
    >
      <div style="margin-bottom: 15px; color: #606266;">
        发现 {{ pdfList.length }} 个文件，请选择要签名盖章的文件：
      </div>
      
      <el-radio-group v-model="selectedPDFIndex" style="width: 100%;">
        <el-radio
          v-for="(file, index) in pdfList"
          :key="index"
          :label="index"
          style="display: block; margin-bottom: 15px; padding: 12px; border: 1px solid #dcdfe6; border-radius: 4px; height: auto; line-height: normal;"
        >
          <div style="display: flex; align-items: center;">
            <el-icon style="font-size: 20px; margin-right: 10px;" :style="{ color: getFileIconColor(file.file_name || file.original_name) }">
              <component :is="getFileIcon(file.file_name || file.original_name)" />
            </el-icon>
            <div style="flex: 1;">
              <div style="word-break: break-all;">
                {{ index + 1 }}. {{ file.file_name || file.original_name }}
              </div>
            </div>
          </div>
        </el-radio>
      </el-radio-group>
      
      <template #footer>
        <el-button @click="showPDFSelector = false">取消</el-button>
        <el-button type="primary" @click="confirmPDFSelection">
          确定
        </el-button>
      </template>
    </el-dialog>
    
    <!-- PDF签名编辑器全屏层 -->
    <Teleport to="body">
      <div v-if="showPDFEditor && currentPDFUrl" ref="pdfStampFullscreenRef" class="native-stamp-fullscreen">
        <el-button class="native-stamp-close" text circle @click="closePDFEditor">
          <el-icon><Close /></el-icon>
        </el-button>
        <div class="native-stamp-body">
          <PDFSignatureEditor
            :pdf-url="currentPDFUrl"
            :allowed-seal="selectedApprovalStamp"
            :show-seal-section="!!selectedApprovalStamp"
            fullscreen
            @confirm="handlePDFEditorConfirm"
            @cancel="closePDFEditor"
          />
        </div>
      </div>
    </Teleport>
    
    <!-- 批量盖章 - 步骤1：选择文件 -->
    <el-dialog
      v-model="showBatchStampSelectDialog"
      title="批量盖章 - 选择文件"
      width="600px"
      :close-on-click-modal="false"
    >
      <el-alert
        title="请选择需要批量盖章的PDF文件"
        type="info"
        :closable="false"
        style="margin-bottom: 16px;"
      />
      
      <el-checkbox-group v-model="batchStampSelectedFiles">
        <div 
          v-for="(file, index) in batchStampPDFList" 
          :key="index"
          style="padding: 12px; border: 1px solid #dcdfe6; border-radius: 4px; margin-bottom: 10px;"
        >
          <el-checkbox :label="index">
            <div style="display: flex; align-items: center;">
              <el-icon style="font-size: 20px; margin-right: 10px; color: #F56C6C;">
                <Document />
              </el-icon>
              <span style="word-break: break-all;">{{ file.file_name || file.original_name }}</span>
            </div>
          </el-checkbox>
        </div>
      </el-checkbox-group>
      
      <template #footer>
        <el-button @click="showBatchStampSelectDialog = false">取消</el-button>
        <el-button type="primary" @click="goToBatchStampPosition" :disabled="batchStampSelectedFiles.length === 0">
          下一步：设置盖章位置 ({{ batchStampSelectedFiles.length }})
        </el-button>
      </template>
    </el-dialog>
    
    <!-- 批量盖章 - 步骤2：设置盖章位置 -->
    <Teleport to="body">
      <div v-if="showBatchStampPositionDialog && batchStampFirstPDFUrl" ref="batchStampFullscreenRef" class="native-stamp-fullscreen">
        <el-button class="native-stamp-close" text circle @click="closeBatchStampPosition">
          <el-icon><Close /></el-icon>
        </el-button>
        <div class="native-stamp-tip">
          <el-alert
            title="请在下方PDF上拖动印章到合适位置，此位置将应用到所有选中的文件"
            type="info"
            :closable="false"
          />
        </div>
        <div class="native-stamp-body">
          <PDFSignatureEditor
            ref="batchStampEditorRef"
            :pdf-url="batchStampFirstPDFUrl"
            :allowed-seal="selectedApprovalStamp"
            :show-seal-section="!!selectedApprovalStamp"
            :preview-mode="true"
            fullscreen
            @position-change="handleBatchStampPositionChange"
            @cancel="closeBatchStampPosition"
          />
        </div>
        <div class="native-stamp-footer">
          <el-button @click="closeBatchStampPosition">取消</el-button>
          <el-button @click="backToBatchStampSelect">上一步</el-button>
          <el-button type="primary" @click="goToBatchStampPreview">
            下一步：预览确认
          </el-button>
        </div>
      </div>
    </Teleport>
    
    <!-- 批量盖章 - 步骤3：预览确认 -->
    <el-dialog
      v-model="showBatchStampPreviewDialog"
      title="批量盖章 - 预览确认"
      width="900px"
      :close-on-click-modal="false"
    >
      <el-alert
        title="请确认以下文件的盖章位置，如需调整可点击对应文件的【调整】按钮"
        type="warning"
        :closable="false"
        style="margin-bottom: 16px;"
      />
      
      <div class="batch-stamp-preview-grid">
        <div 
          v-for="(file, index) in batchStampPreviewList" 
          :key="index"
          class="batch-stamp-preview-item"
        >
          <div class="preview-thumbnail">
            <div class="pdf-placeholder">
              <el-icon style="font-size: 40px; color: #F56C6C;"><Document /></el-icon>
              <div style="margin-top: 8px; font-size: 12px; color: #909399;">PDF</div>
            </div>
            <div 
              class="stamp-indicator" 
              :style="{ 
                left: file.stampPosition.x + '%', 
                top: file.stampPosition.y + '%' 
              }"
            >
              🔴
            </div>
          </div>
          <div class="preview-info">
            <div class="file-name" :title="file.fileName">{{ file.fileName }}</div>
            <el-button size="small" type="primary" link @click="adjustSingleStamp(index)">
              调整位置
            </el-button>
          </div>
        </div>
      </div>
      
      <template #footer>
        <el-button @click="showBatchStampPreviewDialog = false">取消</el-button>
        <el-button @click="backToBatchStampPosition">上一步</el-button>
        <el-button type="danger" @click="confirmBatchStamp" :loading="batchStampSubmitting">
          确认盖章 ({{ batchStampPreviewList.length }}个文件)
        </el-button>
      </template>
    </el-dialog>
    
    <!-- 批量盖章 - 单个文件位置调整 -->
    <Teleport to="body">
      <div v-if="showSingleStampAdjustDialog && singleAdjustPDFUrl" ref="singleStampFullscreenRef" class="native-stamp-fullscreen">
        <el-button class="native-stamp-close" text circle @click="closeSingleStampAdjust">
          <el-icon><Close /></el-icon>
        </el-button>
        <div class="native-stamp-body">
          <PDFSignatureEditor
            ref="singleAdjustEditorRef"
            :pdf-url="singleAdjustPDFUrl"
            :allowed-seal="selectedApprovalStamp"
            :show-seal-section="!!selectedApprovalStamp"
            :preview-mode="true"
            :initial-position="singleAdjustInitialPosition"
            fullscreen
            @position-change="handleSingleAdjustPositionChange"
            @cancel="closeSingleStampAdjust"
          />
        </div>
        <div class="native-stamp-footer">
          <el-button @click="closeSingleStampAdjust">取消</el-button>
          <el-button type="primary" @click="confirmSingleAdjust">
            确认调整
          </el-button>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, watch, nextTick } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Document, Picture, Files, Tickets, Upload, Close } from '@element-plus/icons-vue'
import { 
  getMyTasks,
  getMyApproved,
  getMyInitiated,
  getCCToMe,
  getApprovalDetail,
  approveRecord,
  returnRecord,
  rejectRecord,
  downloadApprovalAttachment,
  getApprovalAttachmentDownloadUrl,
  getApprovalAttachmentFileUrl
} from '@/api/approvalFlow'
import { getMySignature } from '@/api/signatures'
import { useAccountSetStore } from '@/stores/accountSet'
import { useRouter } from 'vue-router'
import request from '@/api/request'
import { PDFDocument } from 'pdf-lib'
import PDFSignatureEditor from '@/components/PDFSignatureEditor.vue'

const accountSetStore = useAccountSetStore()
const router = useRouter()
const SEAL_DRAW_SCALE = 1.3

const loading = ref(false)
const submitting = ref(false)
const showDetailDialog = ref(false)
const showActionDialog = ref(false)
const showPDFEditor = ref(false)
const showPDFSelector = ref(false)
const pdfList = ref([])
const selectedPDFIndex = ref(0)
const selectedAttachmentId = ref(null) // 记录用户选择的附件ID
const pendingSignedPDFBlob = ref(null)
const pendingSignedPDFMeta = ref(null)
const approvalStampPreviewOpened = ref(false)
const actionType = ref('')
const currentApproval = ref(null)
const currentDetail = ref(null)
const currentPDFUrl = ref('')
const actionFormRef = ref()

// 批量盖章相关
const showBatchStampSelectDialog = ref(false)
const showBatchStampPositionDialog = ref(false)
const showBatchStampPreviewDialog = ref(false)
const showSingleStampAdjustDialog = ref(false)
const batchStampPDFList = ref([]) // 所有PDF文件列表
const batchStampSelectedFiles = ref([]) // 选中的文件索引
const batchStampFirstPDFUrl = ref('') // 第一个PDF的URL（用于设置位置）
const batchStampPosition = ref({ x: 80, y: 85, page: 1, sealId: null, sealUrl: '' }) // 盖章位置
const batchStampPreviewList = ref([]) // 预览列表
const batchStampSubmitting = ref(false)
const batchStampEditorRef = ref(null)
const singleAdjustPDFUrl = ref('')
const singleAdjustIndex = ref(-1)
const singleAdjustInitialPosition = ref({ x: 80, y: 85 })
const singleAdjustEditorRef = ref(null)
const pdfStampFullscreenRef = ref(null)
const batchStampFullscreenRef = ref(null)
const singleStampFullscreenRef = ref(null)

const activeTab = ref('my-tasks')
const approvals = ref([])
const taskCount = ref(0)
const ccCount = ref(0)
const availableUsers = ref([])
const mySignature = ref(null)
const mySeals = ref([])

const selectedApprovalStamp = computed(() => {
  return currentApproval.value?.instance?.selected_stamp || null
})

const pagination = reactive({
  currentPage: 1,
  pageSize: 20,
  total: 0
})

const actionForm = reactive({
  comment: '',
  cc_users: [],
  use_signature: false,
  selected_seal_id: null
})

const resolveAttachmentInstanceId = (attachment) => {
  return (
    attachment?.instance_id ||
    currentDetail.value?.id ||
    currentApproval.value?.instance?.id ||
    currentApproval.value?.instance_id ||
    null
  )
}

// 判断是否是第一步（第一步不能退回）
const isFirstStep = computed(() => {
  return currentApproval.value?.step_order === 1
})

const isFinalApprovalStep = computed(() => {
  const currentStepOrder = Number(currentApproval.value?.step_order || 0)
  const records = currentApproval.value?.instance?.records

  if (Array.isArray(records) && records.length > 0) {
    const effectiveStepOrders = records
      .map(record => Number(record?.step_order || 0))
      .filter(stepOrder => stepOrder > 0)

    if (effectiveStepOrders.length > 0) {
      return currentStepOrder > 0 && currentStepOrder === Math.max(...effectiveStepOrders)
    }
  }

  const totalSteps = Number(currentApproval.value?.instance?.total_steps || 0)
  if (currentStepOrder > 0 && totalSteps > 0) {
    return currentStepOrder === totalSteps
  }

  return false
})

const canShowApprovalStampButton = computed(() => {
  return isFinalApprovalStep.value
    && hasContractAttachment.value
    && !!selectedApprovalStamp.value
})

const requiresApprovalStampBeforeApprove = computed(() => {
  return actionType.value === 'approve'
    && isFinalApprovalStep.value
    && hasContractAttachment.value
    && !!selectedApprovalStamp.value
    && currentApproval.value?.instance?.business_type === 'employee_contract'
})

const requiresStampPreviewBeforeApprove = requiresApprovalStampBeforeApprove

const approvalStampPreviewReady = computed(() => {
  return approvalStampPreviewOpened.value || (!!pendingSignedPDFBlob.value && !!pendingSignedPDFMeta.value?.attachmentId)
})

const approvalStampAlertTitle = computed(() => {
  if (approvalStampPreviewReady.value) {
    return '已进入签名盖章预览，点击“通过”后系统会按占位符自动盖章并继续审批。'
  }
  return '当前是最后一个审批节点且已选择公司章，请先点击一次“签名盖章”，再点击“通过”。'
})

const actionFormRules = computed(() => {
  // 退回和驳回必须填写原因
  if (actionType.value === 'reject' || actionType.value === 'return') {
    return {
  comment: [
        { required: true, message: actionType.value === 'reject' ? '请输入驳回原因' : '请输入退回原因', trigger: 'blur' }
      ]
    }
  }
  return {}
})

const stampTypeTexts = {
  bank: '银行付讫章',
  cash: '现金付讫章',
  official: '公章',
  finance: '财务专用章',
  contract: '合同专用章',
  legal_person: '法人章',
  business: '业务专用章',
  hr: '人事部专用章'
}

const getSelectedStampTypeText = (type) => {
  return stampTypeTexts[type] || type || '-'
}

// 监听账套切换，重新加载数据
watch(() => accountSetStore.currentAccountSetId, () => {
  loadApprovals()
})

const loadApprovals = async () => {
  loading.value = true
  try {
    const params = {
      page: pagination.currentPage,
      per_page: pagination.pageSize
    }
    
    let response
    if (activeTab.value === 'my-tasks') {
      response = await getMyTasks(params)
      taskCount.value = response.total || 0
    } else if (activeTab.value === 'my-approved') {
      response = await getMyApproved(params)
    } else if (activeTab.value === 'my-initiated') {
      response = await getMyInitiated(params)
    } else if (activeTab.value === 'cc-to-me') {
      response = await getCCToMe(params)
      ccCount.value = response.total || 0
    }
    
    approvals.value = response.data || []
    pagination.total = response.total || 0
  } catch (error) {
    console.error('Load approvals error:', error)
    ElMessage.error('加载数据失败')
  } finally {
    loading.value = false
  }
}

const handleTabClick = async () => {
  pagination.currentPage = 1
  // 等待 Vue 更新 activeTab 的值
  await nextTick()
  loadApprovals()
}

const handleSizeChange = (size) => {
  pagination.pageSize = size
  loadApprovals()
}

const handleCurrentChange = (page) => {
  pagination.currentPage = page
  loadApprovals()
}

// 查看详情
const handleViewDetail = async (row) => {
  try {
    const instanceId = row.instance_id || row.instance?.id || row.id
    const response = await getApprovalDetail(instanceId)
    currentDetail.value = response.data
    showDetailDialog.value = true
  } catch (error) {
    console.error('Load detail error:', error)
    ElMessage.error('加载详情失败')
  }
}

// 发起审批操作
const handleApprove = async (row) => {
  try {
  actionType.value = 'approve'
    pendingSignedPDFBlob.value = null
    pendingSignedPDFMeta.value = null
    approvalStampPreviewOpened.value = false
    selectedAttachmentId.value = null
    
    // 加载完整的审批实例数据（包括附件）
    const instanceId = row.instance_id || row.instance?.id
    if (instanceId) {
      const response = await getApprovalDetail(instanceId)
      // 将完整的instance数据合并到row中
      currentApproval.value = {
        ...row,
        instance: response.data
      }
    } else {
  currentApproval.value = row
}

    loadAvailableUsers()
    loadMySignatureAndSeals()
  showActionDialog.value = true
  } catch (error) {
    console.error('Load approval data error:', error)
    ElMessage.error('加载审批数据失败')
  }
}

// 提交审批操作
const handleActionSubmit = async (type) => {
  if (!actionFormRef.value) return
  
  // 设置当前操作类型（用于表单验证规则）
  actionType.value = type
  
  // 等待 Vue 更新验证规则
  await nextTick()
  
  await actionFormRef.value.validate(async (valid) => {
    if (!valid) {
      ElMessage.warning(type === 'return' ? '请填写退回原因' : type === 'reject' ? '请填写驳回原因' : '请检查表单')
      return
    }
    
      submitting.value = true
      try {
        const data = {
        comment: actionForm.comment,
        cc_users: actionForm.cc_users || []
        }
        
      // 如果是审批通过
        if (type === 'approve') {
        if (requiresStampPreviewBeforeApprove.value && !approvalStampPreviewReady.value) {
          ElMessage.warning('当前审批已选择公司章，请先点击一次“签名盖章”后，再点击“通过”')
          return
        }

        if (requiresStampPreviewBeforeApprove.value && approvalStampPreviewReady.value) {
          ElMessage.info('正在处理签名盖章文件，请稍候...')
          const hasAutoStamped = await stampApprovalContractByPlaceholder()
          if (!hasAutoStamped && pendingSignedPDFBlob.value && pendingSignedPDFMeta.value?.attachmentId) {
            await uploadPendingPreviewStampedPDF()
          }
        } else if ((actionForm.use_signature || actionForm.selected_seal_id) && currentApproval.value.instance) {
          const instance = currentApproval.value.instance
          if (instance.business_type === 'employee_contract' && instance.attachments && instance.attachments.length > 0) {
            ElMessage.info('正在合成PDF，请稍候...')
            
            // 合成并上传PDF
            const mergeSuccess = await mergePDFAndUpload(
              currentApproval.value.id,
              instance.attachments[0],
              actionForm.use_signature ? mySignature.value : null,
              actionForm.selected_seal_id ? selectedApprovalStamp.value : null,
              currentApproval.value.step_order
            )
            
            if (!mergeSuccess) {
              ElMessage.error('PDF合成失败，但仍可继续审批')
            }
          }
        }
        
        if (actionForm.use_signature && mySignature.value) {
          data.signature_id = mySignature.value.id
        }
        if (actionForm.selected_seal_id) {
          data.seal_id = actionForm.selected_seal_id
        }
        
        await approveRecord(currentApproval.value.id, data)
        ElMessage.success('审批通过，已流转到下一步')
      } else if (type === 'return') {
        // 退回上一级
        await returnRecord(currentApproval.value.id, data)
        ElMessage.success('已退回上一级')
      } else if (type === 'reject') {
        // 驳回整个流程
        await rejectRecord(currentApproval.value.id, data)
        ElMessage.success('审批已驳回')
        }
        
        showActionDialog.value = false
        loadApprovals()
      } catch (error) {
        console.error('Action error:', error)
      ElMessage.error(error.response?.data?.message || '操作失败')
      } finally {
        submitting.value = false
    }
  })
}

// 合成PDF并上传
const mergePDFAndUpload = async (recordId, attachment, signature, seal, stepOrder) => {
  try {
    const instanceId = resolveAttachmentInstanceId(attachment)
    if (!instanceId) {
      throw new Error('审批实例ID缺失，无法下载附件')
    }

    // 下载原PDF（通过API下载，不用storage路径）
    const pdfBlob = await downloadApprovalAttachment(instanceId, attachment.id)
    const originalPdfBytes = await pdfBlob.arrayBuffer()
    
    // 使用pdf-lib加载PDF
    const pdfDoc = await PDFDocument.load(originalPdfBytes)
    const pages = pdfDoc.getPages()
    const lastPage = pages[pages.length - 1]
    const { width, height } = lastPage.getSize()
    
    // 最多支持10个审批节点：每列5个，避免后续节点盖章位置超出页面
    const normalizedStep = Math.max(1, Number(stepOrder) || 1)
    const rowIndex = (normalizedStep - 1) % 5
    const columnIndex = Math.floor((normalizedStep - 1) / 5)
    const pos = {
      x: Math.max(20, width - 200 - columnIndex * 200),
      y: Math.min(height - 80, 100 + rowIndex * 50)
    }
    
    // 添加签名
    if (signature && actionForm.use_signature) {
      // 转换URL使用Vite代理
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
        width: 120,
        height: 60,
      })
    }
    
    // 添加印章（在签名右侧）
    if (seal && actionForm.selected_seal_id) {
      // 转换URL使用Vite代理
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
        width: 60 * SEAL_DRAW_SCALE,
        height: 60 * SEAL_DRAW_SCALE,
        opacity: 0.7,
      })
    }
    
    // 生成新PDF
    const signedPdfBytes = await pdfDoc.save()
    const newPdfBlob = new Blob([signedPdfBytes], { type: 'application/pdf' })
    
    // 上传到后端
    const formData = new FormData()
    formData.append('signed_pdf', newPdfBlob, 'signed.pdf')
    
    const uploadResponse = await request({
      url: `/approvals/records/${recordId}/upload-signed-pdf`,
      method: 'post',
      data: formData,
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    
    return uploadResponse.success
  } catch (error) {
    console.error('PDF合成失败:', error)
    return false
  }
}

const parsePlaceholderPositions = (positions) => {
  if (!positions) {
    return []
  }

  if (Array.isArray(positions)) {
    return positions
  }

  if (typeof positions === 'string') {
    try {
      const parsed = JSON.parse(positions)
      return Array.isArray(parsed) ? parsed : []
    } catch (error) {
      console.warn('解析合同占位符失败:', error)
    }
  }

  return []
}

const getCompanyStampPositions = () => {
  const contract = currentApproval.value?.instance?.business_data || currentApproval.value?.business_data
  const contractTemplate = contract?.contract_template || contract?.contractTemplate
  const positions = contractTemplate
    ? parsePlaceholderPositions(contractTemplate.placeholder_positions)
    : parsePlaceholderPositions(contract?.signature_positions)

  return positions
    .filter(position => position?.type === 'company_stamp')
}

const getDefaultApprovalPdfAttachment = () => {
  const attachments = getApprovalPdfAttachments(currentApproval.value?.instance)
  return selectedAttachmentId.value
    ? attachments.find(attachment => attachment.id === selectedAttachmentId.value) || attachments[0]
    : attachments[0]
}

const normalizeStampImageUrl = (stamp) => {
  const url = stamp?.image_url || stamp?.url || stamp?.image_path || ''
  if (!url) {
    return ''
  }

  if (url.includes('localhost:8000')) {
    return url.replace('http://localhost:8000', '')
  }

  return url
}

const hasFiniteNumber = (value) => {
  return value !== null
    && value !== undefined
    && value !== ''
    && Number.isFinite(Number(value))
}

const clampNumber = (value, min, max) => {
  return Math.min(max, Math.max(min, Number(value)))
}

const calculatePlaceholderStampRect = (position, pageWidth, pageHeight) => {
  const hasPercentageRect = [
    position?.x_percent,
    position?.y_percent,
    position?.width_percent,
    position?.height_percent
  ].every(hasFiniteNumber)
  const savedPageWidth = hasFiniteNumber(position?.page_width) ? Number(position.page_width) : null
  const savedPageHeight = hasFiniteNumber(position?.page_height) ? Number(position.page_height) : null
  const renderScale = Number(position?.render_scale) > 0 ? Number(position.render_scale) : 1
  const scaleX = savedPageWidth ? pageWidth / savedPageWidth : 1 / renderScale
  const scaleY = savedPageHeight ? pageHeight / savedPageHeight : 1 / renderScale
  const x = hasPercentageRect
    ? pageWidth * Number(position.x_percent) / 100
    : Number(position?.x || 0) * scaleX
  const topY = hasPercentageRect
    ? pageHeight * Number(position.y_percent) / 100
    : Number(position?.y || 0) * scaleY
  const width = hasPercentageRect
    ? pageWidth * Number(position.width_percent) / 100
    : Number(position?.width || 150) * scaleX
  const height = hasPercentageRect
    ? pageHeight * Number(position.height_percent) / 100
    : Number(position?.height || 150) * scaleY
  const safeWidth = clampNumber(width, 1, pageWidth)
  const safeHeight = clampNumber(height, 1, pageHeight)

  return {
    x: clampNumber(x, 0, pageWidth - safeWidth),
    y: clampNumber(pageHeight - topY - safeHeight, 0, pageHeight - safeHeight),
    width: safeWidth,
    height: safeHeight
  }
}

const embedStampImage = async (pdfDoc, stampUrl) => {
  const response = await fetch(stampUrl, {
    credentials: 'include',
    mode: 'cors'
  })

  if (!response.ok) {
    throw new Error(`下载印章失败: ${response.status}`)
  }

  const imageBytes = await response.arrayBuffer()
  const contentType = response.headers.get('content-type') || ''
  const isJpg = contentType.includes('jpeg')
    || stampUrl.toLowerCase().endsWith('.jpg')
    || stampUrl.toLowerCase().endsWith('.jpeg')

  return isJpg ? pdfDoc.embedJpg(imageBytes) : pdfDoc.embedPng(imageBytes)
}

const stampApprovalContractByPlaceholder = async () => {
  const stampPositions = getCompanyStampPositions()
  if (stampPositions.length === 0) {
    return false
  }

  const stampUrl = normalizeStampImageUrl(selectedApprovalStamp.value)
  if (!stampUrl) {
    throw new Error('未找到公司印章图片')
  }

  const attachment = getDefaultApprovalPdfAttachment()
  if (!pendingSignedPDFBlob.value && !attachment) {
    throw new Error('未找到可盖章的合同PDF')
  }

  const originalPdfBytes = pendingSignedPDFBlob.value
    ? await pendingSignedPDFBlob.value.arrayBuffer()
    : await (await downloadApprovalAttachment(resolveAttachmentInstanceId(attachment), attachment.id)).arrayBuffer()

  const pdfDoc = await PDFDocument.load(originalPdfBytes)
  const pages = pdfDoc.getPages()
  const stampImage = await embedStampImage(pdfDoc, stampUrl)

  stampPositions.forEach(position => {
    const pageIndex = clampNumber(Math.floor(Number(position?.page) || 0), 0, pages.length - 1)
    const page = pages[pageIndex]
    const { width: pageWidth, height: pageHeight } = page.getSize()
    const rect = calculatePlaceholderStampRect(position, pageWidth, pageHeight)

    page.drawImage(stampImage, {
      ...rect,
      opacity: 0.7
    })
  })

  const signedPdfBytes = await pdfDoc.save()
  const stampedPdfBlob = new Blob([signedPdfBytes], { type: 'application/pdf' })
  const formData = new FormData()
  formData.append('signed_pdf', stampedPdfBlob, 'signed.pdf')

  if (attachment?.id) {
    formData.append('attachment_id', attachment.id)
  } else if (pendingSignedPDFMeta.value?.attachmentId) {
    formData.append('attachment_id', pendingSignedPDFMeta.value.attachmentId)
  }

  const response = await request({
    url: `/approvals/records/${currentApproval.value.id}/upload-signed-pdf`,
    method: 'post',
    data: formData,
    headers: { 'Content-Type': 'multipart/form-data' }
  })

  if (!response?.success) {
    throw new Error(response?.message || '占位符盖章上传失败')
  }

  return true
}

const uploadPendingPreviewStampedPDF = async () => {
  if (!pendingSignedPDFBlob.value || !pendingSignedPDFMeta.value?.attachmentId) {
    return true
  }

  const formData = new FormData()
  formData.append('signed_pdf', pendingSignedPDFBlob.value, pendingSignedPDFMeta.value.fileName || 'signed.pdf')
  formData.append('attachment_id', pendingSignedPDFMeta.value.attachmentId)

  const response = await request({
    url: `/approvals/records/${currentApproval.value.id}/upload-signed-pdf`,
    method: 'post',
    data: formData,
    headers: { 'Content-Type': 'multipart/form-data' }
  })

  if (!response?.success) {
    throw new Error(response?.message || 'PDF上传失败')
  }

  return true
}

const getApprovalPdfAttachments = (instance) => {
  if (!instance?.attachments || !Array.isArray(instance.attachments)) {
    return []
  }

  return instance.attachments.filter(attachment => {
    const fileName = attachment.file_name || attachment.original_name || ''
    return fileName.toLowerCase().endsWith('.pdf')
  })
}

const enterNativeFullscreen = async (targetRef) => {
  await nextTick()
  const target = targetRef.value
  if (!target || document.fullscreenElement) {
    return
  }

  try {
    await target.requestFullscreen?.()
  } catch (error) {
    console.warn('进入浏览器全屏失败，已使用页面内全屏:', error)
  }
}

const exitNativeFullscreen = async () => {
  if (!document.fullscreenElement) {
    return
  }

  try {
    await document.exitFullscreen?.()
  } catch (error) {
    console.warn('退出浏览器全屏失败:', error)
  }
}

const handleDetailDialogClose = () => {
  currentDetail.value = null
}

const handleActionDialogClose = () => {
  actionForm.comment = ''
  actionForm.cc_users = []
  actionForm.use_signature = false
  actionForm.selected_seal_id = null
  pendingSignedPDFBlob.value = null
  pendingSignedPDFMeta.value = null
  approvalStampPreviewOpened.value = false
  selectedAttachmentId.value = null
  actionFormRef.value?.resetFields()
}

// 加载可用用户（用于抄送）
const loadAvailableUsers = async () => {
  try {
    const accountSetId = accountSetStore.currentAccountSetId
    if (!accountSetId) {
      availableUsers.value = []
      return
    }

    const response = await request({
      url: `/account-sets/${accountSetId}/users`,
      method: 'get'
    })

    const users = Array.isArray(response?.data) ? response.data : []
    availableUsers.value = users.map(user => ({
      id: user.id,
      name: user.name || user.nickname || `用户${user.id}`
    }))
  } catch (error) {
    console.error('Load users error:', error)
    availableUsers.value = []
  }
}

// 加载我的签名和印章
const loadMySignatureAndSeals = async () => {
  try {
    console.log('开始加载签名和印章...')
    
    const sigResponse = await getMySignature()
    console.log('签名响应:', sigResponse)
    if (sigResponse.success) {
      mySignature.value = sigResponse.data
      console.log('签名已加载:', mySignature.value)
    }
    
    mySeals.value = selectedApprovalStamp.value ? [selectedApprovalStamp.value] : []
  } catch (error) {
    console.error('加载签名印章失败:', error)
    ElMessage.warning('加载签名印章失败，请先前往"签名印章管理"上传')
  }
}

// 跳转到签名管理
const goToSignatureManagement = () => {
  router.push('/signature-management')
}

// 检查是否有PDF附件可以签名盖章（computed属性，自动响应数据变化）
const hasContractAttachment = computed(() => {
  console.log('🔍 检查PDF附件:', {
    hasApproval: !!currentApproval.value,
    hasInstance: !!currentApproval.value?.instance,
    businessType: currentApproval.value?.instance?.business_type,
    attachments: currentApproval.value?.instance?.attachments
  })
  
  if (!currentApproval.value || !currentApproval.value.instance) {
    console.log('❌ 无审批数据或实例')
    return false
  }
  
  const instance = currentApproval.value.instance
  
  // 检查是否有附件
  if (!instance.attachments || instance.attachments.length === 0) {
    console.log('❌ 无附件')
    return false
  }
  
  // 检查是否有PDF文件
  const hasPDF = instance.attachments.some(attachment => {
    const fileName = attachment.file_name || attachment.original_name || ''
    return fileName.toLowerCase().endsWith('.pdf')
  })
  
  if (!hasPDF) {
    console.log('❌ 无PDF附件')
    return false
  }
  
  console.log('✅ 有PDF附件，显示高级签名按钮')
  return true
})

// 检查是否有多个PDF附件（用于批量盖章按钮显示）
const hasPDFAttachments = computed(() => {
  if (!currentApproval.value || !currentApproval.value.instance) {
    return false
  }
  
  const instance = currentApproval.value.instance
  const instanceId = instance?.id
  if (!instanceId) {
    ElMessage.error('审批实例ID缺失')
    return
  }
  if (!instance.attachments || instance.attachments.length === 0) {
    return false
  }
  
  // 获取所有PDF文件
  const pdfFiles = instance.attachments.filter(attachment => {
    const fileName = attachment.file_name || attachment.original_name || ''
    return fileName.toLowerCase().endsWith('.pdf')
  })
  
  // 至少有1个PDF才显示批量盖章按钮
  return pdfFiles.length >= 1
})

// 打开PDF编辑器
const openPDFEditor = async () => {
  if (!currentApproval.value || !currentApproval.value.instance) {
    ElMessage.error('审批信息加载失败')
    return
  }

  if (!canShowApprovalStampButton.value) {
    ElMessage.warning('只有最后一个审批节点可以签名盖章')
    return
  }

  const instance = currentApproval.value.instance
  if (!instance.attachments || instance.attachments.length === 0) {
    ElMessage.error('未找到附件')
    return
  }
  
  const pdfAttachments = instance.attachments.filter(attachment => {
    const fileName = attachment.file_name || attachment.original_name || ''
    return fileName.toLowerCase().endsWith('.pdf')
  })

  if (pdfAttachments.length === 0) {
    ElMessage.warning('未找到可签名盖章的PDF文件')
    return
  }

  approvalStampPreviewOpened.value = true
  
  // 如果只有一个PDF附件，直接打开
  if (pdfAttachments.length === 1) {
    const attachment = pdfAttachments[0]
    const instanceId = resolveAttachmentInstanceId(attachment)

    if (!instanceId) {
      ElMessage.error('审批实例ID缺失，无法打开附件')
      return
    }
    currentPDFUrl.value = getApprovalAttachmentDownloadUrl(instanceId, attachment.id)
    selectedAttachmentId.value = attachment.id // 记录选择的附件ID
    showPDFEditor.value = true
    enterNativeFullscreen(pdfStampFullscreenRef)
    return
  }
  
  // 如果有多个PDF附件，显示选择对话框
  pdfList.value = pdfAttachments
  selectedPDFIndex.value = 0
  showPDFSelector.value = true
}

// 获取文件图标
const getFileIcon = (fileName) => {
  if (!fileName) return Files
  const ext = fileName.toLowerCase().split('.').pop()
  
  // PDF文件
  if (ext === 'pdf') return Document
  
  // 图片文件
  if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'].includes(ext)) return Picture
  
  // Word文档
  if (['doc', 'docx'].includes(ext)) return Tickets
  
  // Excel表格
  if (['xls', 'xlsx', 'csv'].includes(ext)) return Tickets
  
  // 其他文件
  return Files
}

// 获取文件图标颜色
const getFileIconColor = (fileName) => {
  if (!fileName) return '#909399'
  const ext = fileName.toLowerCase().split('.').pop()
  
  // PDF - 红色
  if (ext === 'pdf') return '#F56C6C'
  
  // 图片 - 绿色
  if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'].includes(ext)) return '#67C23A'
  
  // Word - 蓝色
  if (['doc', 'docx'].includes(ext)) return '#409EFF'
  
  // Excel - 橙色
  if (['xls', 'xlsx', 'csv'].includes(ext)) return '#E6A23C'
  
  // 其他 - 灰色
  return '#909399'
}

// 确认选择文件
const confirmPDFSelection = () => {
  const attachment = pdfList.value[selectedPDFIndex.value]
  const instanceId = resolveAttachmentInstanceId(attachment)
  if (!instanceId) {
    ElMessage.error('审批实例ID缺失，无法预览附件')
    return
  }
  currentPDFUrl.value = getApprovalAttachmentDownloadUrl(instanceId, attachment.id)
  selectedAttachmentId.value = attachment.id // 记录选择的附件ID
  showPDFSelector.value = false
  showPDFEditor.value = true
  enterNativeFullscreen(pdfStampFullscreenRef)
}

// PDF编辑器确认
const handlePDFEditorConfirm = async (data) => {
  try {
    loading.value = true

    if (requiresStampPreviewBeforeApprove.value) {
      pendingSignedPDFBlob.value = data.pdfBlob
      pendingSignedPDFMeta.value = {
        attachmentId: selectedAttachmentId.value,
        fileName: 'signed.pdf'
      }
    } else {
      const formData = new FormData()
      formData.append('signed_pdf', data.pdfBlob, 'signed.pdf')

      if (selectedAttachmentId.value) {
        formData.append('attachment_id', selectedAttachmentId.value)
      }

      await request({
        url: `/approvals/records/${currentApproval.value.id}/upload-signed-pdf`,
        method: 'post',
        data: formData,
        headers: { 'Content-Type': 'multipart/form-data' }
      })
    }

    // 更新表单数据（保留签名印章信息，但不自动提交）
    if (data.hasSignature) {
      actionForm.use_signature = true
    }
    if (data.hasSeal && data.sealId) {
      actionForm.selected_seal_id = data.sealId
    }

    // 关闭PDF编辑器，返回审批操作对话框
    showPDFEditor.value = false
    await exitNativeFullscreen()
    ElMessage.success(requiresStampPreviewBeforeApprove.value ? '已进入签名盖章预览，请点击“通过”继续审批' : 'PDF签名盖章已准备完成，请点击“通过”继续审批')
    
  } catch (error) {
    console.error('PDF处理失败:', error)
    ElMessage.error('PDF处理失败')
  } finally {
    loading.value = false
  }
}

const closePDFEditor = () => {
  showPDFEditor.value = false
  exitNativeFullscreen()
}

const closeBatchStampPosition = () => {
  showBatchStampPositionDialog.value = false
  exitNativeFullscreen()
}

const closeSingleStampAdjust = () => {
  showSingleStampAdjustDialog.value = false
  exitNativeFullscreen()
}

// 辅助函数
const getBusinessTypeText = (row) => {
  const instance = row.instance || row
  const businessData = row.business_data || instance.business_data
  if (businessData?.category === 'social_detail_edit') {
    return '社保明细修改'
  }
  const type = instance.business_type
  const texts = {
    'employee_contract': '员工合同审批',
    'offline_onboarding': '线下入职审批',
    'employee_deletion': '员工删除审批',
    'employee_salary_adjustment': '员工工资调整审批',
    'employee_registration_form_update': '登记表修改审批',
    'personnel_change': '人员汇总申请',
    'salary': '工资审核',
    'salary_approval': '工资表审批',
    'insurance': '社保审核',
    'insurance_summary': '保险汇总审批',
    'attendance_sheet': '考勤申请',
    'payment_application': '付款申请',
    '工资表审批': '工资表审批',
    '保险汇总': '保险汇总',
    'social_detail_edit': '社保明细修改',
    '文件盖章': '文件盖章',
    '付款申请': '付款申请',
    '考勤申请': '考勤审批',
    '工资付款申请': '工资付款',
    '保险付款申请': '保险付款',
    '报销申请': '报销申请',
    '报销付款申请': '报销付款',
    'invoice_application': '发票申请',
    'material_request': '资料申请'
  }
  return texts[type] || type
}

const formatApprovalEditBase = (value) => {
  if (value === null || value === undefined || value === '') return '-'
  const number = Number(value)
  return Number.isFinite(number) ? number.toFixed(2) : '-'
}

const getContractTypeText = (type) => {
  const texts = {
    'labor': '劳动合同',
    'intern': '实习合同',
    'dispatch': '劳务派遣合同',
    'part_time': '兼职合同',
    'confidentiality': '保密协议',
    'non_compete': '竞业限制协议'
  }
  return texts[type] || type
}

const getEmployeeContractProjectNames = (businessData) => {
  const projects = businessData?.employee?.projects || []
  if (!Array.isArray(projects) || projects.length === 0) {
    return ''
  }

  const activeProjects = projects.filter(project => project?.pivot?.status === 'active')
  const displayProjects = activeProjects.length > 0 ? activeProjects : projects

  return displayProjects
    .map(project => project?.name)
    .filter(Boolean)
    .join('、')
}

const getInitiatorName = (row) => {
  return row.instance?.creator?.name || row.creator?.name || '-'
}

const getStatusType = (status) => {
  const types = {
    'pending': 'warning',
    'waiting': 'info',
    'approved': 'success',
    'rejected': 'danger',
    'returned': 'warning',
    'withdrawn': 'info'
  }
  return types[status] || 'info'
}

const getStatusText = (status) => {
  const texts = {
    'pending': '待审批',
    'waiting': '等待中',
    'approved': '已通过',
    'rejected': '已驳回',
    'returned': '已退回',
    'withdrawn': '已撤回'
  }
  return texts[status] || status
}

const getStampMethodText = (method) => {
  const texts = {
    'online': '线上盖章',
    'offline': '线下盖章'
  }
  return texts[method] || method
}

const getCurrentStepIndex = () => {
  if (!currentDetail.value) return 0
  const instance = currentDetail.value.instance || currentDetail.value
  return instance.current_step - 1
}

const getApprovalRecords = () => {
  if (!currentDetail.value) return []
  const records = currentDetail.value.records || []
  const rejectedIndex = records.findIndex(record => record.status === 'rejected')
  return rejectedIndex === -1 ? records : records.slice(0, rejectedIndex + 1)
}

const getStepDescription = (record) => {
  let desc = `审批人：${record.approver_name}`
  if (record.status === 'approved' && record.approved_at) {
    desc += `\n审批时间：${formatDateTime(record.approved_at)}`
  }
  if (record.comment) {
    desc += `\n审批意见：${record.comment}`
  }
  return desc
}

const getStepStatus = (record) => {
  if (record.status === 'approved') return 'success'
  if (record.status === 'rejected') return 'error'
  if (record.status === 'pending') return 'process'
  return 'wait'
}

const getTimelineItemClass = (record) => {
  return {
    'timeline-approved': record.status === 'approved',
    'timeline-rejected': record.status === 'rejected',
    'timeline-pending': record.status === 'pending',
    'timeline-waiting': record.status === 'waiting'
  }
}

const getRecordTagType = (record) => {
  if (record.status === 'approved') return 'success'
  if (record.status === 'rejected') return 'danger'
  if (record.status === 'pending') return 'warning'
  return 'info'
}

const getRecordStatusText = (status) => {
  const texts = {
    'approved': '已通过',
    'rejected': '已驳回',
    'returned': '已退回',
    'pending': '待审批',
    'waiting': '等待中'
  }
  return texts[status] || status
}

// 预览附件（浏览器直接打开）
const handleViewAttachment = async (attachment) => {
  if (!attachment.file_path) {
    ElMessage.warning('无附件')
    return
  }

  try {
    const previewUrl = getApprovalAttachmentFileUrl(attachment.file_path)
    if (!previewUrl) {
      ElMessage.error('附件地址不存在，无法打开')
      return
    }

    window.open(previewUrl, '_blank', 'noopener,noreferrer')
  } catch (error) {
    console.error('Preview error:', error)
    ElMessage.error('预览失败: ' + (error.message || '未知错误'))
  }
}

const handleDownloadAttachment = async (attachment) => {
  if (!attachment.file_path) {
    ElMessage.warning('无附件')
    return
  }

  try {
    const instanceId = resolveAttachmentInstanceId(attachment)
    if (!instanceId) {
      ElMessage.error('审批实例ID缺失，无法下载附件')
      return
    }

    ElMessage.info('正在下载，请稍候...')

    const blob = await downloadApprovalAttachment(instanceId, attachment.id)
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = attachment.filename || attachment.file_name || '附件'
    link.style.display = 'none'
    document.body.appendChild(link)
    link.click()
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

// 上传前检查
const beforeInstanceAttachmentUpload = (file) => {
  const isLt50M = file.size / 1024 / 1024 < 50
  if (!isLt50M) {
    ElMessage.error('文件大小不能超过 50MB!')
    return false
  }
  return true
}

// 自定义上传方法
const handleInstanceAttachmentUpload = async (options) => {
  if (!currentDetail.value || !currentDetail.value.id) {
    ElMessage.error('审批实例ID不存在')
    return
  }
  
  try {
    const formData = new FormData()
    formData.append('file', options.file)
    
    const response = await request.post(
      `/approvals/${currentDetail.value.id}/upload-attachment`,
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data',
          'X-Account-Set-Id': accountSetStore.currentAccountSetId
        }
      }
    )
    
    // response 本身就是 {success: true, message: "...", data: {...}}
    if (response && response.success) {
      ElMessage.success('附件上传成功')
      // 刷新审批详情
      await refreshApprovalDetail()
    } else {
      ElMessage.error(response?.message || '上传失败')
    }
  } catch (error) {
    console.error('Upload error:', error)
    ElMessage.error('附件上传失败')
  }
}

// 删除审批实例附件
const handleDeleteInstanceAttachment = async (attachment) => {
  try {
    await ElMessageBox.confirm('确定要删除这个附件吗？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    
    const response = await request.delete(
      `/approvals/${currentDetail.value.id}/attachments/${attachment.id}`,
      {
        headers: {
          'X-Account-Set-Id': accountSetStore.currentAccountSetId
        }
      }
    )
    
    // response 本身就是 {success: true, message: "..."}
    if (response && response.success) {
      ElMessage.success('附件删除成功')
      // 刷新审批详情
      await refreshApprovalDetail()
    } else {
      ElMessage.error(response?.message || '删除失败')
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('Delete attachment error:', error)
      ElMessage.error('删除附件失败')
    }
  }
}

// 刷新审批详情
const refreshApprovalDetail = async () => {
  if (!currentDetail.value || !currentDetail.value.id) {
    return
  }
  
  try {
    const response = await getApprovalDetail(currentDetail.value.id)
    // response 本身就是 {success: true, data: {...}}
    if (response && response.success) {
      // 更新 currentDetail，包括 attachments
      currentDetail.value = response.data
      // 同时更新 currentApproval（如果存在）
      if (currentApproval.value) {
        currentApproval.value.instance = response.data
      }
    }
  } catch (error) {
    console.error('Refresh approval detail error:', error)
  }
}

// 格式化日期时间
const formatDateTime = (dateTime) => {
  if (!dateTime) return '-'
  let date;
  
  // 如果时间字符串是 YYYY-MM-DD HH:mm:ss 格式（没有时区信息），
  // 假设它是UTC时间并添加'Z'确保被解析为UTC
  if (typeof dateTime === 'string' && !dateTime.includes('Z') && !dateTime.includes('+') && !dateTime.includes('-') && dateTime.includes(' ')) {
    date = new Date(dateTime + 'Z');
  } else {
    date = new Date(dateTime);
  }
  
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

// 判断是否可以撤回
// 规则：审批状态为pending，且第二个审批人还未审批
const canWithdraw = (row) => {
  // 必须是待审批状态
  if (row.status !== 'pending') {
    return false
  }
  
  // 获取审批记录
  const records = row.records || []
  if (records.length < 2) {
    return false
  }
  
  // 检查第二个审批人的状态
  const secondRecord = records.find(r => r.step_order === 2)
  if (!secondRecord) {
    return false
  }
  
  // 第二个审批人还未审批才能撤回
  return secondRecord.status !== 'approved'
}

// 撤回审批
const handleWithdraw = async (row) => {
  try {
    await ElMessageBox.confirm(
      '确定要撤回此审批吗？撤回后审批流程将终止。',
      '撤回确认',
      {
        confirmButtonText: '确定撤回',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
    
    loading.value = true
    
    const instanceId = row.id
    await request({
      url: `/approvals/${instanceId}/withdraw`,
      method: 'post'
    })
    
    ElMessage.success('审批已撤回')
    loadApprovals()
    
  } catch (error) {
    if (error !== 'cancel') {
      console.error('Withdraw error:', error)
      ElMessage.error(error.response?.data?.message || '撤回失败')
    }
  } finally {
    loading.value = false
  }
}

// ==================== 批量盖章功能 ====================

// 打开批量盖章对话框
const openBatchStampDialog = () => {
  if (!currentApproval.value || !currentApproval.value.instance) {
    ElMessage.error('审批信息加载失败')
    return
  }
  
  const instance = currentApproval.value.instance
  if (!instance.attachments || instance.attachments.length === 0) {
    ElMessage.error('未找到附件')
    return
  }
  
  // 获取所有PDF文件
  batchStampPDFList.value = instance.attachments.filter(attachment => {
    const fileName = attachment.file_name || attachment.original_name || ''
    return fileName.toLowerCase().endsWith('.pdf')
  })
  
  if (batchStampPDFList.value.length === 0) {
    ElMessage.warning('没有可盖章的PDF文件')
    return
  }
  
  // 重置选择
  batchStampSelectedFiles.value = []
  showBatchStampSelectDialog.value = true
}

// 进入设置盖章位置步骤
const goToBatchStampPosition = () => {
  if (batchStampSelectedFiles.value.length === 0) {
    ElMessage.warning('请至少选择一个文件')
    return
  }
  
  // 获取第一个选中文件的URL
  const firstSelectedIndex = batchStampSelectedFiles.value[0]
  const firstFile = batchStampPDFList.value[firstSelectedIndex]
  const firstFileInstanceId = resolveAttachmentInstanceId(firstFile)
  if (!firstFileInstanceId) {
    ElMessage.error('审批实例ID缺失，无法加载附件')
    return
  }
  batchStampFirstPDFUrl.value = getApprovalAttachmentDownloadUrl(firstFileInstanceId, firstFile.id)
  
  // 重置盖章位置
  batchStampPosition.value = { x: 80, y: 85, page: 1, sealId: null, sealUrl: '' }
  
  showBatchStampSelectDialog.value = false
  showBatchStampPositionDialog.value = true
  enterNativeFullscreen(batchStampFullscreenRef)
}

// 返回文件选择步骤
const backToBatchStampSelect = () => {
  showBatchStampPositionDialog.value = false
  exitNativeFullscreen()
  showBatchStampSelectDialog.value = true
}

// 处理盖章位置变化
const handleBatchStampPositionChange = (position) => {
  console.log('盖章位置变化:', position)
  batchStampPosition.value = { ...batchStampPosition.value, ...position }
}

// 进入预览确认步骤
const goToBatchStampPreview = () => {
  // 从编辑器获取当前位置和印章信息
  if (batchStampEditorRef.value) {
    const editorPosition = batchStampEditorRef.value.getCurrentPosition?.()
    if (editorPosition) {
      batchStampPosition.value = { ...batchStampPosition.value, ...editorPosition }
    }
  }
  
  // 检查是否选择了印章
  if (!batchStampPosition.value.sealId && !batchStampPosition.value.sealUrl) {
    ElMessage.warning('请先选择印章并设置盖章位置')
    return
  }
  
  // 生成预览列表
  batchStampPreviewList.value = batchStampSelectedFiles.value.map(index => {
    const file = batchStampPDFList.value[index]
    const instanceId = resolveAttachmentInstanceId(file)
    return {
      index: index,
      fileId: file.id,
      instanceId,
      fileName: file.file_name || file.original_name,
      filePath: file.file_path,
      fileUrl: getApprovalAttachmentDownloadUrl(instanceId, file.id),
      stampPosition: { ...batchStampPosition.value }
    }
  })
  
  showBatchStampPositionDialog.value = false
  exitNativeFullscreen()
  showBatchStampPreviewDialog.value = true
}

// 返回设置位置步骤
const backToBatchStampPosition = () => {
  showBatchStampPreviewDialog.value = false
  showBatchStampPositionDialog.value = true
  enterNativeFullscreen(batchStampFullscreenRef)
}

// 调整单个文件的盖章位置
const adjustSingleStamp = (previewIndex) => {
  const file = batchStampPreviewList.value[previewIndex]
  singleAdjustIndex.value = previewIndex
  singleAdjustPDFUrl.value = file.fileUrl
  singleAdjustInitialPosition.value = { ...file.stampPosition }
  showSingleStampAdjustDialog.value = true
  enterNativeFullscreen(singleStampFullscreenRef)
}

// 处理单个文件位置调整变化
const handleSingleAdjustPositionChange = (position) => {
  singleAdjustInitialPosition.value = { ...singleAdjustInitialPosition.value, ...position }
}

// 确认单个文件位置调整
const confirmSingleAdjust = () => {
  if (singleAdjustIndex.value >= 0 && singleAdjustIndex.value < batchStampPreviewList.value.length) {
    // 从编辑器获取当前位置
    if (singleAdjustEditorRef.value) {
      const editorPosition = singleAdjustEditorRef.value.getCurrentPosition?.()
      if (editorPosition) {
        singleAdjustInitialPosition.value = { ...singleAdjustInitialPosition.value, ...editorPosition }
      }
    }
    
    batchStampPreviewList.value[singleAdjustIndex.value].stampPosition = { ...singleAdjustInitialPosition.value }
    ElMessage.success('位置已调整')
  }
  showSingleStampAdjustDialog.value = false
  exitNativeFullscreen()
}

// 确认批量盖章
const confirmBatchStamp = async () => {
  if (batchStampPreviewList.value.length === 0) {
    ElMessage.warning('没有要盖章的文件')
    return
  }
  
  try {
    await ElMessageBox.confirm(
      `确定要对 ${batchStampPreviewList.value.length} 个文件进行盖章吗？此操作将替换原文件，不可撤销。`,
      '确认盖章',
      {
        confirmButtonText: '确认盖章',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
    
    batchStampSubmitting.value = true
    
    let successCount = 0
    let failCount = 0
    const totalCount = batchStampPreviewList.value.length
    
    // 逐个处理每个PDF文件（前端合成）
    for (let i = 0; i < batchStampPreviewList.value.length; i++) {
      const file = batchStampPreviewList.value[i]
      
      try {
        ElMessage.info(`正在处理 ${i + 1}/${totalCount}: ${file.fileName}`)
        
        // 1. 下载原始PDF
        if (!file.instanceId) {
          throw new Error('审批实例ID缺失')
        }
        const sourcePdfBlob = await downloadApprovalAttachment(file.instanceId, file.fileId)
        const sourcePdfBytes = await sourcePdfBlob.arrayBuffer()
        
        // 2. 使用pdf-lib加载PDF
        const pdfDoc = await PDFDocument.load(sourcePdfBytes)
        const pageCount = pdfDoc.getPageCount()
        
        // 3. 获取要盖章的页面
        const stampPage = Math.min(file.stampPosition.page || 1, pageCount)
        const page = pdfDoc.getPage(stampPage - 1)
        const { width: pdfWidth, height: pdfHeight } = page.getSize()
        
        // 4. 下载印章图片
        let sealUrl = file.stampPosition.sealUrl
        if (sealUrl.includes('localhost:8000')) {
          sealUrl = sealUrl.replace('http://localhost:8000', '')
        }
        
        const imgResponse = await fetch(sealUrl, {
          credentials: 'include',
          mode: 'cors'
        })
        if (!imgResponse.ok) {
          throw new Error(`下载印章图片失败: HTTP ${imgResponse.status}`)
        }
        const imgBytes = await imgResponse.arrayBuffer()
        
        // 5. 嵌入印章图片
        let image
        if (sealUrl.toLowerCase().includes('.png')) {
          image = await pdfDoc.embedPng(imgBytes)
        } else {
          image = await pdfDoc.embedJpg(imgBytes)
        }
        
        // 6. 计算印章位置和大小
        const stampWidth = file.stampPosition.width || (80 * SEAL_DRAW_SCALE)
        const stampHeight = file.stampPosition.height || (80 * SEAL_DRAW_SCALE)
        const x = (file.stampPosition.x / 100) * pdfWidth
        const y = pdfHeight - ((file.stampPosition.y / 100) * pdfHeight) - stampHeight
        
        // 7. 在PDF上绘制印章
        page.drawImage(image, {
          x: x,
          y: y,
          width: stampWidth,
          height: stampHeight,
          opacity: 0.7,
        })
        
        // 8. 保存合成后的PDF
        const mergedPdfBytes = await pdfDoc.save()
        const pdfBlob = new Blob([mergedPdfBytes], { type: 'application/pdf' })
        
        // 9. 上传替换原文件
        const formData = new FormData()
        formData.append('file', pdfBlob, file.fileName)
        formData.append('attachment_id', file.fileId)
        formData.append('file_path', file.filePath)
        
        const uploadResponse = await request({
          url: `/approvals/records/${currentApproval.value.id}/replace-attachment`,
          method: 'post',
          data: formData,
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        })
        
        if (uploadResponse.success) {
          successCount++
          console.log(`✅ ${file.fileName} 盖章成功`)
        } else {
          failCount++
          console.error(`❌ ${file.fileName} 上传失败:`, uploadResponse.message)
        }
        
      } catch (error) {
        failCount++
        console.error(`❌ ${file.fileName} 处理失败:`, error)
      }
    }
    
    // 显示结果
    if (failCount === 0) {
      ElMessage.success(`全部 ${successCount} 个文件盖章成功！`)
    } else if (successCount === 0) {
      ElMessage.error(`全部 ${failCount} 个文件盖章失败`)
    } else {
      ElMessage.warning(`盖章完成：成功 ${successCount} 个，失败 ${failCount} 个`)
    }
    
    showBatchStampPreviewDialog.value = false
    
    // 刷新审批详情
    if (currentApproval.value) {
      const instanceId = currentApproval.value.instance_id || currentApproval.value.instance?.id
      if (instanceId) {
        const response = await getApprovalDetail(instanceId)
        currentApproval.value = {
          ...currentApproval.value,
          instance: response.data
        }
      }
    }
    
  } catch (error) {
    if (error !== 'cancel') {
      console.error('批量盖章失败:', error)
      ElMessage.error(error.response?.data?.message || '批量盖章失败')
    }
  } finally {
    batchStampSubmitting.value = false
  }
}

// ==================== 批量盖章功能结束 ====================

onMounted(() => {
  loadApprovals()
})
</script>

<style scoped>
.approvals-page {
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

.approval-tabs {
  margin-bottom: 20px;
}

.tab-badge {
  margin-left: 5px;
}

.approval-detail {
  padding: 10px 0;
}

.detail-section {
  margin-bottom: 30px;
}

.detail-section h3 {
  font-size: 16px;
  font-weight: bold;
  margin-bottom: 15px;
  color: #303133;
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 5px;
}

.native-stamp-fullscreen {
  position: fixed !important;
  inset: 0 !important;
  width: 100vw !important;
  height: 100vh !important;
  z-index: 2147483647 !important;
  background: #fff;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.native-stamp-close {
  position: fixed !important;
  top: 8px;
  right: 8px;
  z-index: 2147483647;
  width: 32px;
  height: 32px;
  background: rgba(255, 255, 255, 0.92) !important;
  border: 1px solid #dcdfe6 !important;
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.14);
}

.native-stamp-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
  padding: 0;
  overflow: hidden;
}

.native-stamp-footer {
  flex-shrink: 0;
  padding: 8px 16px;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  border-top: 1px solid #e4e7ed;
}

.native-stamp-tip {
  flex-shrink: 0;
  padding: 8px 10px 0;
}

/* 附件列表样式 */
.attachments-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.attachment-item {
  display: flex;
  align-items: center;
  padding: 8px 12px;
  background: #f5f7fa;
  border-radius: 4px;
  transition: all 0.3s;
}

.attachment-item:hover {
  background: #e8f4ff;
}

/* 审批时间轴样式 */
.approval-timeline {
  padding: 10px 0;
}

.timeline-item {
  display: flex;
  position: relative;
  padding-bottom: 20px;
}

.timeline-item:last-child {
  padding-bottom: 0;
}

.timeline-marker {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-right: 15px;
}

.marker-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  background: #fff;
  border: 2px solid #dcdfe6;
  transition: all 0.3s;
  z-index: 2;
}

.timeline-approved .marker-icon {
  background: #67c23a;
  border-color: #67c23a;
  color: #fff;
  box-shadow: 0 0 0 3px rgba(103, 194, 58, 0.15);
}

.timeline-rejected .marker-icon {
  background: #f56c6c;
  border-color: #f56c6c;
  color: #fff;
  box-shadow: 0 0 0 3px rgba(245, 108, 108, 0.15);
}

.timeline-pending .marker-icon {
  background: #e6a23c;
  border-color: #e6a23c;
  color: #fff;
  box-shadow: 0 0 0 3px rgba(230, 162, 60, 0.15);
  animation: pulse 2s infinite;
}

.timeline-waiting .marker-icon {
  background: #f5f7fa;
  border-color: #dcdfe6;
  color: #909399;
}

@keyframes pulse {
  0%, 100% {
    box-shadow: 0 0 0 3px rgba(230, 162, 60, 0.15);
  }
  50% {
    box-shadow: 0 0 0 5px rgba(230, 162, 60, 0.08);
  }
}

.marker-line {
  flex: 1;
  width: 2px;
  background: #e4e7ed;
  margin-top: 6px;
  min-height: 30px;
}

.timeline-approved .marker-line {
  background: linear-gradient(to bottom, #67c23a, #dcdfe6);
}

.timeline-rejected .marker-line {
  background: #f56c6c;
}

.timeline-content {
  flex: 1;
  background: #fff;
  border: 1px solid #e4e7ed;
  border-radius: 6px;
  padding: 12px 16px;
  transition: all 0.3s;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.timeline-content:hover {
  box-shadow: 0 3px 8px rgba(0, 0, 0, 0.12);
  transform: translateY(-1px);
}

.timeline-approved .timeline-content {
  border-color: #b3e19d;
  background: #f0f9ff;
}

.timeline-rejected .timeline-content {
  border-color: #fab6b6;
  background: #fef0f0;
}

.timeline-pending .timeline-content {
  border-color: #f0c78a;
  background: #fffbf0;
  border-width: 2px;
}

.timeline-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
  padding-bottom: 8px;
  border-bottom: 1px dashed #e4e7ed;
}

.step-name {
  font-size: 15px;
  font-weight: 600;
  color: #303133;
}

.timeline-pending .step-name {
  color: #e6a23c;
}

.timeline-body {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.timeline-info {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #606266;
}

.timeline-info .el-icon {
  color: #909399;
  font-size: 14px;
}

.timeline-comment {
  display: flex;
  align-items: flex-start;
  gap: 6px;
  margin-top: 6px;
  padding: 8px 10px;
  background: #f5f7fa;
  border-radius: 4px;
  border-left: 3px solid #409eff;
  font-size: 13px;
  color: #606266;
}

.timeline-comment .el-icon {
  color: #409eff;
  margin-top: 1px;
  font-size: 14px;
}

.timeline-comment span {
  flex: 1;
  line-height: 1.5;
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
  max-width: 200px;
  max-height: 80px;
  object-fit: contain;
  display: block;
  margin: 10px auto 0;
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

:deep(.el-radio) {
  display: flex;
  align-items: center;
  margin-bottom: 10px;
  padding: 8px 12px;
  border: 1px solid #e4e7ed;
  border-radius: 4px;
  transition: all 0.3s;
}

:deep(.el-radio:hover) {
  background: #f5f7fa;
  border-color: #409eff;
}

:deep(.el-radio.is-checked) {
  background: #ecf5ff;
  border-color: #409eff;
}

/* 考勤详情样式 */
.attendance-detail {
  margin-top: 10px;
}

.attendance-info {
  background: #f8f9fa;
  padding: 12px;
  border-radius: 4px;
  margin-bottom: 15px;
}

.attendance-info p {
  margin: 4px 0;
  font-size: 14px;
}

.attendance-table {
  margin-top: 15px;
}

.attendance-table h4 {
  margin: 0 0 10px 0;
  font-size: 14px;
  color: #303133;
}

:deep(.attendance-table .el-table) {
  font-size: 12px;
}

:deep(.attendance-table .el-table th) {
  background: #f5f7fa;
  font-weight: 600;
}

.seal-mini-preview {
  width: 24px;
  height: 24px;
  object-fit: contain;
  vertical-align: middle;
  margin-left: 6px;
}

:deep(.el-radio) {
  display: flex;
  align-items: center;
  margin-bottom: 10px;
  padding: 8px 12px;
  border: 1px solid #e4e7ed;
  border-radius: 4px;
  transition: all 0.3s;
}

:deep(.el-radio:hover) {
  background: #f5f7fa;
  border-color: #409eff;
}

:deep(.el-radio.is-checked) {
  background: #ecf5ff;
  border-color: #409eff;
}

/* 考勤详情样式 */
.attendance-detail {
  margin-top: 10px;
}

.attendance-info {
  background: #f8f9fa;
  padding: 12px;
  border-radius: 4px;
  margin-bottom: 15px;
}

.attendance-info p {
  margin: 4px 0;
  font-size: 14px;
}

.attendance-table {
  margin-top: 15px;
}

.attendance-table h4 {
  margin: 0 0 10px 0;
  font-size: 14px;
  color: #303133;
}

:deep(.attendance-table .el-table) {
  font-size: 12px;
}

:deep(.attendance-table .el-table th) {
  background: #f5f7fa;
  font-weight: 600;
}

/* 批量盖章预览网格 */
.batch-stamp-preview-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 16px;
  max-height: 500px;
  overflow-y: auto;
  padding: 8px;
}

.batch-stamp-preview-item {
  border: 1px solid #dcdfe6;
  border-radius: 8px;
  overflow: hidden;
  transition: all 0.3s;
}

.batch-stamp-preview-item:hover {
  border-color: #409eff;
  box-shadow: 0 2px 12px rgba(64, 158, 255, 0.2);
}

.preview-thumbnail {
  position: relative;
  height: 150px;
  background: #f5f7fa;
  display: flex;
  align-items: center;
  justify-content: center;
}

.pdf-placeholder {
  text-align: center;
}

.stamp-indicator {
  position: absolute;
  font-size: 20px;
  transform: translate(-50%, -50%);
  filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
}

.preview-info {
  padding: 12px;
  background: #fff;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.file-name {
  font-size: 13px;
  color: #303133;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 120px;
}
</style>
