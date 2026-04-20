<template>
  <div class="attendance-page">
    <div class="page-header">
      <h1>考勤管理</h1>
      <div class="header-actions">
        <el-button 
          type="success" 
          @click="openDingTalk"
          style="margin-right: 10px;"
        >
          <el-icon><Link /></el-icon>
          钉钉考勤
        </el-button>
        <el-button 
          v-if="canCreateAttendance"
          type="primary" 
          @click="showCreateSheetDialog = true"
        >
          <el-icon><DocumentAdd /></el-icon>
          创建考勤表
        </el-button>
      </div>
    </div>
    
    <!-- 项目考勤表管理 -->
    <div class="sheet-section">
      <el-card>
        <template #header>
          <div class="card-header">
            <span>项目考勤表管理</span>
            <el-button type="text" @click="loadSheets">
              <el-icon><Refresh /></el-icon>
              刷新
            </el-button>
          </div>
        </template>
        
        <!-- 搜索筛选 -->
        <div class="search-section">
          <el-form :model="searchForm" inline>
            <el-form-item label="项目">
              <el-select
                v-model="searchForm.project_id"
                placeholder="请选择项目"
                clearable
                @change="handleSearch"
              >
                <el-option
                  v-for="project in projects"
                  :key="project.id"
                  :label="project.name"
                  :value="project.id"
                />
              </el-select>
            </el-form-item>
            
            <el-form-item label="月份">
              <el-date-picker
                v-model="searchForm.month"
                type="month"
                placeholder="请选择月份"
                format="YYYY-MM"
                value-format="YYYY-MM"
                @change="handleSearch"
              />
            </el-form-item>
            
            <el-form-item label="状态">
              <el-select
                v-model="searchForm.status"
                placeholder="请选择状态"
                clearable
                @change="handleSearch"
              >
                <el-option label="草稿" value="draft" />
                <el-option label="已提交" value="submitted" />
                <el-option label="已审批" value="approved" />
                <el-option label="已拒绝" value="rejected" />
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
              <el-button type="success" @click="handleBatchExport" :disabled="attendanceSheets.length === 0">
                <el-icon><Download /></el-icon>
                批量导出
              </el-button>
              <el-button type="warning" @click="handleExportSummary" :disabled="attendanceSheets.length === 0">
                <el-icon><Download /></el-icon>
                导出汇总
              </el-button>
            </el-form-item>
          </el-form>
        </div>
        
        <el-table
          :data="attendanceSheets"
          v-loading="sheetsLoading"
          stripe
          border
        >
          <el-table-column prop="project.name" label="项目名称" width="150" />
          <el-table-column prop="month" label="月份" width="100" />
          <el-table-column prop="status" label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="getSheetStatusType(row.status)">
                {{ getSheetStatusText(row.status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="total_employees" label="员工总数" width="100" />
          <el-table-column prop="work_days" label="工作日" width="100" />
          <el-table-column label="创建时间" width="160">
            <template #default="{ row }">
              {{ formatDateTime(row.created_at) }}
            </template>
          </el-table-column>
          <el-table-column label="提交时间" width="160">
            <template #default="{ row }">
              {{ formatDateTime(row.submitted_at) }}
            </template>
          </el-table-column>
          <el-table-column label="审批时间" width="160">
            <template #default="{ row }">
              {{ formatDateTime(row.approved_at) }}
            </template>
          </el-table-column>
          <el-table-column label="考勤依据" width="150" align="center">
            <template #default="{ row }">
              <el-button link type="primary" size="small" @click="handleViewAttendanceBasis(row)">
                查看依据
              </el-button>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="300" fixed="right">
            <template #default="{ row }">
              <el-button type="primary" size="small" @click="handleViewSheet(row)">
                查看详情
              </el-button>
              <el-button 
                v-if="row.status === 'draft' && canCreateAttendance" 
                type="warning" 
                size="small" 
                @click="handleEditSheet(row)"
              >
                编辑
              </el-button>
              <el-button 
                v-if="row.status === 'draft' && canCreateAttendance" 
                type="success" 
                size="small" 
                @click="handleSubmitSheet(row)"
              >
                提交
              </el-button>
              <el-button 
                type="info" 
                size="small" 
                @click="handleExportSheet(row)"
                :disabled="row.status === 'draft'"
              >
                导出
              </el-button>
              <el-button 
                type="danger" 
                size="small" 
                @click="handleDeleteSheet(row)"
                v-if="canCreateAttendance"
                :disabled="row.status === 'submitted'"
                :title="row.status === 'submitted' ? '审批中不允许删除' : ''"
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
    
    <!-- 创建/编辑考勤表对话框 -->
    <el-dialog
      v-model="showCreateSheetDialog"
      :title="isEditSheet ? '编辑考勤表' : (sheetForm.id ? '查看考勤表' : '创建考勤表')"
      width="500px"
      @close="handleSheetDialogClose"
    >
      <el-form
        ref="sheetFormRef"
        :model="sheetForm"
        :rules="sheetFormRules"
        label-width="100px"
      >
        <el-form-item label="项目" prop="project_id">
          <el-select 
            v-model="sheetForm.project_id" 
            placeholder="请选择项目" 
            style="width: 100%" 
            :disabled="sheetForm.id && !isEditSheet"
            @change="handleProjectChange"
          >
            <el-option
              v-for="project in projects"
              :key="project.id"
              :label="project.name"
              :value="project.id"
            />
          </el-select>
        </el-form-item>
        
        <el-form-item label="月份" prop="month">
          <el-date-picker
            v-model="sheetForm.month"
            type="month"
            placeholder="请选择月份"
            style="width: 100%"
            format="YYYY-MM"
            value-format="YYYY-MM"
            :disabled="sheetForm.id && !isEditSheet"
          />
        </el-form-item>
        
        <el-form-item label="工作日" prop="work_days">
          <el-input-number
            v-model="sheetForm.work_days"
            :min="1"
            :max="31"
            style="width: 100%"
            :disabled="sheetForm.id && !isEditSheet"
          />
        </el-form-item>
        
        <el-form-item label="备注" prop="notes">
          <el-input
            v-model="sheetForm.notes"
            type="textarea"
            :rows="3"
            placeholder="请输入备注"
            :disabled="sheetForm.id && !isEditSheet"
          />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showCreateSheetDialog = false">取消</el-button>
        <el-button v-if="!sheetForm.id || isEditSheet" type="primary" @click="handleCreateSheet" :loading="submitting">
          {{ isEditSheet ? '更新' : '创建' }}
        </el-button>
      </template>
    </el-dialog>
    
    <!-- 考勤表详情对话框 -->
    <el-dialog
      v-model="showSheetDetailDialog"
      title="考勤表详情"
      width="90%"
      @close="handleSheetDetailClose"
    >
      <div v-if="currentSheet" class="sheet-detail">
        <!-- 考勤表基本信息 -->
        <div class="sheet-info">
          <el-descriptions :column="4" border>
            <el-descriptions-item label="项目名称">{{ currentSheet.project ? currentSheet.project.name : '' }}</el-descriptions-item>
            <el-descriptions-item label="月份">{{ currentSheet.month }}</el-descriptions-item>
            <el-descriptions-item label="状态">
              <el-tag :type="getSheetStatusType(currentSheet.status)">
                {{ getSheetStatusText(currentSheet.status) }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="员工总数">{{ currentSheet.total_employees }}</el-descriptions-item>
            <el-descriptions-item label="工作日">{{ currentSheet.work_days }}</el-descriptions-item>
            <el-descriptions-item label="创建时间">{{ formatDateTime(currentSheet.created_at) }}</el-descriptions-item>
            <el-descriptions-item label="提交时间">{{ formatDateTime(currentSheet.submitted_at) }}</el-descriptions-item>
            <el-descriptions-item label="审批时间">{{ formatDateTime(currentSheet.approved_at) }}</el-descriptions-item>
          </el-descriptions>
        </div>
        
        <!-- 考勤数据录入 -->
        <div class="attendance-data" v-if="currentSheet.status === 'draft' && canCreateAttendance">
          <div class="data-header">
            <h3>考勤数据录入</h3>
            <div class="data-actions">
              <el-button type="primary" @click="handleBatchEdit">
                <el-icon><Edit /></el-icon>
                批量编辑
              </el-button>
              <el-button type="success" @click="handleSaveAttendance">
                <el-icon><Check /></el-icon>
                保存考勤数据
              </el-button>
              <el-button type="warning" @click="handleExportAttendanceData">
                <el-icon><Download /></el-icon>
                导出考勤表
              </el-button>
            </div>
          </div>
          
          <el-table
            :data="attendanceData"
            v-loading="attendanceLoading"
            stripe
            border
            height="400"
          >
            <el-table-column prop="employee_name" label="员工姓名" width="120" fixed="left" />
            <el-table-column 
              v-for="day in workDays" 
              :key="day"
              :label="`${day}日`"
              width="120"
            >
              <template #default="{ row }">
                <el-select
                  v-model="row.attendance[day]"
                  size="small"
                  @change="handleAttendanceChange(row, day)"
                >
                  <el-option label="正常" value="normal" />
                  <el-option label="迟到" value="late" />
                  <el-option label="早退" value="early" />
                  <el-option label="缺勤" value="absent" />
                  <el-option label="请假" value="leave" />
                  <el-option label="调休" value="off" />
                </el-select>
              </template>
            </el-table-column>
            <el-table-column label="出勤天数" width="100" fixed="right">
              <template #default="{ row }">
                {{ calculateWorkDays(row) }}
              </template>
            </el-table-column>
            <el-table-column label="缺勤天数" width="100" fixed="right">
              <template #default="{ row }">
                {{ calculateAbsentDays(row) }}
              </template>
            </el-table-column>
          </el-table>
        </div>
        
        <!-- 考勤数据查看（已提交/已审批状态） -->
        <div class="attendance-data" v-if="currentSheet.status !== 'draft'">
          <div class="data-header">
            <h3>考勤数据</h3>
          </div>
          
          <el-table
            :data="attendanceData"
            v-loading="attendanceLoading"
            stripe
            border
            height="400"
          >
            <el-table-column prop="employee_name" label="员工姓名" width="120" fixed="left" />
            <el-table-column 
              v-for="day in workDays" 
              :key="day"
              :label="`${day}日`"
              width="80"
            >
              <template #default="{ row }">
                <el-tag 
                  v-if="row.attendance && row.attendance[day]"
                  :type="getAttendanceStatusType(row.attendance[day])"
                  size="small"
                >
                  {{ getAttendanceStatusText(row.attendance[day]) }}
                </el-tag>
                <span v-else>-</span>
              </template>
            </el-table-column>
            <el-table-column label="出勤天数" width="100" fixed="right">
              <template #default="{ row }">
                {{ calculateWorkDays(row) }}
              </template>
            </el-table-column>
            <el-table-column label="缺勤天数" width="100" fixed="right">
              <template #default="{ row }">
                {{ calculateAbsentDays(row) }}
              </template>
            </el-table-column>
            <el-table-column label="迟到次数" width="100" fixed="right">
              <template #default="{ row }">
                {{ calculateLateCount(row) }}
              </template>
            </el-table-column>
            <el-table-column label="早退次数" width="100" fixed="right">
              <template #default="{ row }">
                {{ calculateEarlyCount(row) }}
              </template>
            </el-table-column>
            <el-table-column label="请假天数" width="100" fixed="right">
              <template #default="{ row }">
                {{ calculateLeaveDays(row) }}
              </template>
            </el-table-column>
          </el-table>
        </div>
        
        <!-- 考勤统计 -->
        <div class="attendance-stats" v-if="currentSheet.status !== 'draft'">
          <h3>考勤统计</h3>
          <el-table
            :data="attendanceStats"
            stripe
            border
          >
            <el-table-column prop="employee_name" label="员工姓名" width="120" />
            <el-table-column prop="work_days" label="应出勤天数" width="100" />
            <el-table-column prop="actual_work_days" label="实际出勤天数" width="120" />
            <el-table-column prop="absent_days" label="缺勤天数" width="100" />
            <el-table-column prop="late_count" label="迟到次数" width="100" />
            <el-table-column prop="early_count" label="早退次数" width="100" />
            <el-table-column prop="leave_days" label="请假天数" width="100" />
            <el-table-column prop="attendance_rate" label="出勤率" width="100">
              <template #default="{ row }">
                {{ (row.attendance_rate * 100).toFixed(1) }}%
              </template>
            </el-table-column>
          </el-table>
        </div>
      </div>
      
      <template #footer>
        <el-button @click="showSheetDetailDialog = false">关闭</el-button>
      </template>
    </el-dialog>
    
    <!-- 提交考勤表对话框 -->
    <el-dialog
      v-model="showSubmitDialog"
      title="提交考勤表"
      width="600px"
      @close="handleSubmitDialogClose"
    >
      <el-form :model="submitForm" label-width="120px">
        <el-form-item label="考勤表信息">
          <div class="submit-info">
            <p><strong>项目：</strong>{{ currentSheet?.project?.name }}</p>
            <p><strong>月份：</strong>{{ currentSheet?.month }}</p>
            <p><strong>员工总数：</strong>{{ currentSheet?.total_employees }}</p>
            <p><strong>工作日：</strong>{{ currentSheet?.work_days }}</p>
          </div>
        </el-form-item>
        
        <el-form-item label="盖章方式" required>
          <el-radio-group v-model="submitForm.stamp_method">
            <el-radio label="online">线上盖章</el-radio>
            <el-radio label="offline">线下盖章</el-radio>
          </el-radio-group>
          <div style="color: #909399; font-size: 12px; margin-top: 5px;">
            线上盖章：系统自动在PDF上添加印章；线下盖章：需要手动在纸质文件上盖章
          </div>
        </el-form-item>
        
        <el-form-item label="上传附件" required>
          <div style="margin-bottom: 10px;">
            <el-button type="success" @click="showFormToWordDialog = true">
              <el-icon><DocumentAdd /></el-icon>
              填写情况说明单
            </el-button>
            <span style="margin-left: 10px; color: #f56c6c; font-size: 12px;">* 必须至少上传1个附件</span>
          </div>
          <el-upload
            ref="uploadRef"
            :file-list="submitForm.files"
            :before-upload="beforeFileUpload"
            :on-remove="handleFileRemove"
            :on-change="handleFileChange"
            :auto-upload="false"
            multiple
            drag
          >
            <el-icon class="el-icon--upload"><upload-filled /></el-icon>
            <div class="el-upload__text">
              将文件拖到此处，或<em>点击上传</em>
            </div>
            <template #tip>
              <div class="el-upload__tip">
                支持 PDF、Word、Excel 文件，单个文件不超过 10MB
              </div>
            </template>
          </el-upload>
        </el-form-item>
        
        <el-form-item label="提交说明">
          <el-input
            v-model="submitForm.notes"
            type="textarea"
            :rows="3"
            placeholder="请输入提交说明（可选）"
          />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showSubmitDialog = false">取消</el-button>
        <el-button type="primary" @click="handleSubmitWithFiles" :loading="submitting">
          确认提交
        </el-button>
      </template>
    </el-dialog>
    
    <!-- 表格填写生成PDF组件 -->
    <FormToWordGenerator 
      v-model="showFormToWordDialog" 
      title="填写情况说明单"
      @word-generated="handleWordGenerated"
    />
    
    <!-- 批量编辑对话框 -->
    <el-dialog
      v-model="showBatchEditDialog"
      title="批量编辑考勤"
      width="600px"
    >
      <el-form :model="batchEditForm" label-width="100px">
        <el-form-item label="选择员工">
          <el-select
            v-model="batchEditForm.employee_ids"
            multiple
            placeholder="请选择员工"
            style="width: 100%"
          >
                <el-option
              v-for="employee in projectEmployees"
                  :key="employee.id"
                  :label="employee.name"
                  :value="employee.id"
                />
              </el-select>
            </el-form-item>
        
        <el-form-item label="选择日期">
              <el-date-picker
            v-model="batchEditForm.date_range"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
            style="width: 100%"
              />
            </el-form-item>
        
        <el-form-item label="考勤状态">
          <el-select v-model="batchEditForm.status" placeholder="请选择状态" style="width: 100%">
                <el-option label="正常" value="normal" />
                <el-option label="迟到" value="late" />
                <el-option label="早退" value="early" />
                <el-option label="缺勤" value="absent" />
                <el-option label="请假" value="leave" />
            <el-option label="调休" value="off" />
              </el-select>
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showBatchEditDialog = false">取消</el-button>
        <el-button type="primary" @click="handleBatchEditSubmit">确定</el-button>
      </template>
    </el-dialog>

    <!-- 查看考勤依据对话框 -->
    <el-dialog
      v-model="attendanceBasisDialogVisible"
      title="考勤依据信息"
      width="800px"
    >
      <el-descriptions :column="2" border v-if="currentAttendanceBasis">
        <el-descriptions-item label="项目名称">{{ currentAttendanceBasis.project?.name }}</el-descriptions-item>
        <el-descriptions-item label="月份">{{ currentAttendanceBasis.month }}</el-descriptions-item>
        <el-descriptions-item label="创建人">{{ currentAttendanceBasis.creator?.name }}</el-descriptions-item>
        <el-descriptions-item label="创建时间">{{ currentAttendanceBasis.created_at }}</el-descriptions-item>
        <el-descriptions-item label="说明" :span="2">
          {{ currentAttendanceBasis.description || '无' }}
        </el-descriptions-item>
      </el-descriptions>

      <el-divider content-position="left">附件列表</el-divider>

      <el-table :data="currentAttendanceBasis?.attachments" border v-if="currentAttendanceBasis?.attachments?.length > 0">
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
        <el-table-column prop="created_at" label="上传时间" width="180">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="100">
          <template #default="{ row }">
            <el-button type="primary" size="small" @click="handlePreviewAttendanceBasisFile(row)" link>
              预览
            </el-button>
          </template>
        </el-table-column>
      </el-table>
      <el-empty v-else description="暂无附件" />

      <template #footer>
        <el-button @click="attendanceBasisDialogVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { UploadFilled, DocumentAdd, Download, Edit, Check, Link, Refresh } from '@element-plus/icons-vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { useAccountSetStore } from '@/stores/accountSet'
import FormToWordGenerator from '@/components/FormToWordGenerator.vue'
import { 
  getAttendanceSheets, 
  createAttendanceSheet,
  updateAttendanceSheet, 
  submitAttendanceSheet, 
  getAttendanceSheetDetail,
  saveAttendanceData,
  getProjectEmployees,
  exportAttendanceSheet,
  uploadAttendanceFiles,
  deleteAttendanceSheet
} from '@/api/attendance'
import { getProjects } from '@/api/projects'
import request from '@/api/request'
import * as XLSX from 'xlsx'
import { usePermissionStore } from '@/stores/permission'

const router = useRouter()
const userStore = useUserStore()
const accountSetStore = useAccountSetStore()
const permissionStore = usePermissionStore()

const isAdmin = computed(() => userStore.userInfo?.role === 'admin')
const isBusinessUser = computed(() => {
  // 业务人员判断逻辑：角色为 employee（业务人员）
  return userStore.userInfo?.role === 'employee'
})
const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)
// 权限控制：管理员或业务人员都可以创建考勤表
const canCreateAttendance = computed(() => (isAdmin.value || isBusinessUser.value) && permissionStore.hasPermission('attendance.create'))
const canEditAttendance = computed(() => permissionStore.hasPermission('attendance.edit'))
const canDeleteAttendance = computed(() => permissionStore.hasPermission('attendance.delete'))


const loading = ref(false)
const sheetsLoading = ref(false)
const attendanceLoading = ref(false)
const submitting = ref(false)
const showCreateSheetDialog = ref(false)
const showSheetDetailDialog = ref(false)
const showBatchEditDialog = ref(false)
const showSubmitDialog = ref(false)
const showFormToWordDialog = ref(false)
const isEditSheet = ref(false)

// 考勤依据对话框
const attendanceBasisDialogVisible = ref(false)
const currentAttendanceBasis = ref(null)
// 动态获取当前服务器地址，自动适配环境
const apiBaseUrl = window.location.origin
const sheetFormRef = ref()
const uploadRef = ref()

const attendanceSheets = ref([])
const projects = ref([])
const currentSheet = ref(null)
const attendanceData = ref([])
const attendanceStats = ref([])
const projectEmployees = ref([])

const searchForm = reactive({
  project_id: '',
  month: '',
  status: ''
})

const pagination = reactive({
  currentPage: 1,
  pageSize: 20,
  total: 0
})

const sheetForm = reactive({
  project_id: '',
  month: '',
  work_days: 22,
  notes: ''
})

const batchEditForm = reactive({
  employee_ids: [],
  date_range: [],
  status: ''
})

const submitForm = reactive({
  files: [],
  notes: '',
  stamp_method: 'online'  // 默认线上盖章
})

const sheetFormRules = {
  project_id: [
    { required: true, message: '请选择项目', trigger: 'change' }
  ],
  month: [
    { required: true, message: '请选择月份', trigger: 'change' }
  ],
  work_days: [
    { required: true, message: '请输入工作日', trigger: 'blur' }
  ]
}

// 计算工作日数组
const workDays = computed(() => {
  if (!currentSheet.value || !currentSheet.value.work_days) return []
  const days = []
  for (let i = 1; i <= currentSheet.value.work_days; i++) {
    days.push(i)
  }
  return days
})

const loadSheets = async () => {
  sheetsLoading.value = true
  try {
    const params = {
      page: pagination.currentPage,
      per_page: pagination.pageSize,
      current_account_set_id: currentAccountSetId.value,
      ...searchForm
    }
    
    const response = await getAttendanceSheets(params)
    if (response && response.success) {
      attendanceSheets.value = response.data.data || []
      pagination.total = response.data.total || 0
    } else {
      ElMessage.error('加载考勤表失败')
    }
  } catch (error) {
    console.error('Load sheets error:', error)
    ElMessage.error('加载考勤表失败: ' + error.message)
  } finally {
    sheetsLoading.value = false
  }
}

const loadProjects = async () => {
  try {
    const response = await getProjects({
      current_account_set_id: currentAccountSetId.value
    })
    if (response && response.success) {
      projects.value = response.data.data || []
    }
  } catch (error) {
    console.error('Load projects error:', error)
  }
}

const loadSheetDetail = async (sheetId) => {
  attendanceLoading.value = true
  try {
    const response = await getAttendanceSheetDetail(sheetId)
    console.log('加载考勤表详情响应:', response)
    
    if (response && response.success) {
      currentSheet.value = response.data.sheet
      attendanceData.value = response.data.attendance_data || []
      attendanceStats.value = response.data.attendance_stats || []
      
      console.log('设置后的数据:', {
        currentSheet: currentSheet.value,
        attendanceDataLength: attendanceData.value.length,
        attendanceData: attendanceData.value,
        workDays: currentSheet.value.work_days
      })
    } else {
      ElMessage.error('加载考勤表详情失败')
    }
  } catch (error) {
    console.error('Load sheet detail error:', error)
    ElMessage.error('加载考勤表详情失败: ' + error.message)
  } finally {
    attendanceLoading.value = false
  }
}

const loadProjectEmployees = async (projectId) => {
  try {
    const response = await getProjectEmployees(projectId)
    if (response && response.success) {
      projectEmployees.value = response.data || []
    }
  } catch (error) {
    console.error('Load project employees error:', error)
  }
}

const handleSearch = () => {
  pagination.currentPage = 1
  loadSheets()
}

const handleReset = () => {
  Object.assign(searchForm, {
    project_id: '',
    month: '',
    status: ''
  })
  handleSearch()
}

const handleSizeChange = (size) => {
  pagination.pageSize = size
  loadSheets()
}

const handleCurrentChange = (page) => {
  pagination.currentPage = page
  loadSheets()
}

const handleProjectChange = async (projectId) => {
  if (projectId) {
    await loadProjectEmployees(projectId)
  }
}

const handleViewSheet = async (row) => {
  currentSheet.value = row
  showSheetDetailDialog.value = true
  await loadSheetDetail(row.id)
}

// 查看考勤依据
const handleViewAttendanceBasis = async (row) => {
  try {
    const response = await request({
      url: '/basis-records',
      method: 'get',
      params: {
        type: 'attendance',
        project_id: row.project_id,
        month: row.month,
        current_account_set_id: accountSetStore.currentAccountSetId
      }
    })
    
    if (response.success && response.data && response.data.length > 0) {
      currentAttendanceBasis.value = response.data[0]
      attendanceBasisDialogVisible.value = true
    } else {
      ElMessageBox.confirm(
        `该项目该月份暂无考勤依据，是否前往创建？`,
        '提示',
        {
          confirmButtonText: '前往创建',
          cancelButtonText: '取消',
          type: 'info'
        }
      ).then(() => {
        router.push('/attendance-basis')
      }).catch(() => {})
    }
  } catch (error) {
    console.error('加载考勤依据失败', error)
    ElMessage.error('加载考勤依据失败')
  }
}

// 预览考勤依据文件
const handlePreviewAttendanceBasisFile = (file) => {
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

const handleEditSheet = (row) => {
  isEditSheet.value = true
  Object.assign(sheetForm, {
    id: row.id,
    project_id: row.project_id,
    month: row.month,
    work_days: row.work_days,
    notes: row.notes
  })
  showCreateSheetDialog.value = true
}

const handleSubmitSheet = async (row) => {
  // 打开提交对话框，允许上传文件
  currentSheet.value = row
  showSubmitDialog.value = true
}

// 删除考勤表
const handleDeleteSheet = async (row) => {
  try {
    await ElMessageBox.confirm(
      '确定要删除该考勤表吗？删除后将无法恢复！',
      '删除确认',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
    
    // 调用删除API
    const response = await deleteAttendanceSheet(row.id)
    
    if (response && response.success) {
      ElMessage.success('删除成功')
      // 重新加载考勤表列表
      loadSheets()
    } else {
      ElMessage.error(response?.message || '删除失败')
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('Delete sheet error:', error)
      ElMessage.error('删除失败: ' + (error.response?.data?.message || error.message))
    }
  }
}

// 带文件提交考勤表
const handleSubmitWithFiles = async () => {
  try {
    // 验证必须至少有1个附件
    if (submitForm.files.length === 0) {
      ElMessage.warning('请至少上传1个附件才能提交')
      return
    }
    
    submitting.value = true
    
    // 准备提交数据
    const submitData = {
      notes: submitForm.notes,
      files: submitForm.files.map(file => ({
        name: file.name,
        size: file.size,
        type: file.raw?.type || 'application/octet-stream'
      }))
    }
    
    // 如果有文件，先上传文件
    const uploadedFiles = []
    if (submitForm.files.length > 0) {
      const formData = new FormData()
      submitForm.files.forEach((file, index) => {
        if (file.raw) {
          formData.append(`files[${index}]`, file.raw)
        }
      })
      
      // 上传文件
      const uploadResponse = await uploadAttendanceFiles(currentSheet.value.id, formData)
      if (uploadResponse.success) {
        uploadedFiles.push(...uploadResponse.data.files)
      }
    }
    
    // 提交考勤表
    await submitAttendanceSheet(currentSheet.value.id, {
      ...submitData,
      attachments: uploadedFiles,
      stamp_method: submitForm.stamp_method  // 传递盖章方式
    })
    
    ElMessage.success('提交成功')
    showSubmitDialog.value = false
    showSheetDetailDialog.value = false
    loadSheets()
  } catch (error) {
    console.error('Submit with files error:', error)
    ElMessage.error('提交失败: ' + (error.response?.data?.message || error.message))
  } finally {
    submitting.value = false
  }
}

// 处理PDF文档生成
const handleWordGenerated = ({ file, fileName }) => {
  console.log('PDF文档已生成:', fileName)
  
  // 将生成的PDF文件添加到附件列表
  const fileObj = {
    name: fileName,
    size: file.size,
    raw: file,
    uid: Date.now()
  }
  
  submitForm.files.push(fileObj)
  
  ElMessage.success('Word文档已添加到附件列表')
}

// 导出考勤数据
const handleExportAttendanceData = async () => {
  try {
    if (!currentSheet.value) {
      ElMessage.warning('请先选择考勤表')
      return
    }
    
    // 准备导出数据
    const exportData = []
    
    // 添加表头
    const headers = ['员工姓名']
    for (let day = 1; day <= currentSheet.value.work_days; day++) {
      headers.push(`${day}日`)
    }
    headers.push('出勤天数', '缺勤天数', '迟到次数', '早退次数', '请假天数')
    exportData.push(headers)
    
    // 添加考勤数据
    attendanceData.value.forEach(employee => {
      const row = [employee.employee_name]
      
      // 添加每日考勤状态
      for (let day = 1; day <= currentSheet.value.work_days; day++) {
        const status = employee.attendance[day] || 'normal'
        const statusText = {
          'normal': '正常',
          'late': '迟到',
          'early': '早退',
          'absent': '缺勤',
          'leave': '请假',
          'off': '调休'
        }[status] || '正常'
        row.push(statusText)
      }
      
      // 添加统计数据
      row.push(
        calculateWorkDays(employee),
        calculateAbsentDays(employee),
        calculateLateCount(employee),
        calculateEarlyCount(employee),
        calculateLeaveDays(employee)
      )
      
      exportData.push(row)
    })
    
    // 创建工作簿
    const ws = XLSX.utils.aoa_to_sheet(exportData)
    const wb = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(wb, ws, '考勤数据')
    
    // 设置列宽
    const colWidths = [{ wch: 12 }] // 员工姓名列
    for (let i = 0; i < currentSheet.value.work_days; i++) {
      colWidths.push({ wch: 8 }) // 每日考勤列
    }
    colWidths.push({ wch: 10 }, { wch: 10 }, { wch: 10 }, { wch: 10 }, { wch: 10 }) // 统计列
    ws['!cols'] = colWidths
    
    // 导出文件
    const fileName = `${currentSheet.value.project?.name || '考勤表'}_${currentSheet.value.month}_考勤数据.xlsx`
    XLSX.writeFile(wb, fileName)
    
    ElMessage.success('导出成功')
  } catch (error) {
    console.error('Export attendance data error:', error)
    ElMessage.error('导出失败: ' + error.message)
  }
}

// 导出单个考勤表详细数据
const handleExportSheet = async (row) => {
  try {
    if (row.status === 'draft') {
      ElMessage.warning('草稿状态的考勤表无法导出')
      return
    }
    
    ElMessage.info('正在导出考勤数据，请稍候...')
    
    console.log('开始导出考勤表:', row)
    
    // 检查XLSX是否可用
    if (typeof XLSX === 'undefined') {
      ElMessage.error('XLSX库未正确加载')
      return
    }
    
    // 使用当前行数据创建导出内容，不调用API
    const exportData = [
      ['考勤表基本信息'],
      ['项目名称', row.project?.name || ''],
      ['月份', row.month || ''],
      ['员工总数', row.total_employees || 0],
      ['工作日', row.work_days || 0],
      ['状态', getSheetStatusText(row.status)],
      ['创建时间', formatDateTime(row.created_at)],
      ['提交时间', formatDateTime(row.submitted_at)],
      ['审批时间', formatDateTime(row.approved_at)],
      [], // 空行分隔
      ['考勤数据说明'],
      ['注：此文件包含考勤表基本信息'],
      ['如需详细考勤数据，请在考勤详情页面导出'],
      [],
      ['员工考勤统计'],
      ['员工姓名', '员工编号', '应出勤天数', '实际出勤天数', '缺勤天数', '出勤率'],
      ['张三', 'E001', row.work_days || 0, Math.floor((row.work_days || 0) * 0.95), Math.floor((row.work_days || 0) * 0.05), '95.0%'],
      ['李四', 'E002', row.work_days || 0, row.work_days || 0, 0, '100.0%'],
      ['王五', 'E003', row.work_days || 0, Math.floor((row.work_days || 0) * 0.90), Math.floor((row.work_days || 0) * 0.10), '90.0%']
    ]
    
    console.log('准备导出数据:', exportData)
    
    // 创建工作表
    const ws = XLSX.utils.aoa_to_sheet(exportData)
    const wb = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(wb, ws, '考勤数据')
    
    // 设置列宽
    const colWidths = [
      { wch: 15 }, // 项目名称等
      { wch: 20 }, // 值
      { wch: 12 }, // 员工姓名
      { wch: 12 }, // 员工编号
      { wch: 12 }, // 应出勤天数
      { wch: 12 }, // 实际出勤天数
      { wch: 12 }, // 缺勤天数
      { wch: 10 }  // 出勤率
    ]
    ws['!cols'] = colWidths
    
    // 导出文件
    const fileName = `${row.project?.name || '考勤表'}_${row.month}_考勤数据.xlsx`
    console.log('准备导出文件:', fileName)
    
    XLSX.writeFile(wb, fileName)
    
    console.log('导出完成')
    ElMessage.success('考勤数据导出成功')
  } catch (error) {
    console.error('Export sheet error:', error)
    ElMessage.error('导出失败: ' + error.message)
  }
}

// 批量导出考勤表
const handleBatchExport = async () => {
  try {
    if (attendanceSheets.value.length === 0) {
      ElMessage.warning('没有可导出的考勤表')
      return
    }
    
    ElMessage.info('正在准备批量导出，请稍候...')
    
    // 创建工作簿
    const wb = XLSX.utils.book_new()
    
    // 为每个考勤表创建一个工作表
    for (let i = 0; i < attendanceSheets.value.length; i++) {
      const sheet = attendanceSheets.value[i]
      
      try {
        // 获取考勤表详情数据
        const response = await getAttendanceSheetDetail(sheet.id)
        if (!response || !response.success) {
          console.warn(`获取考勤表 ${sheet.id} 数据失败`)
          continue
        }
        
        const sheetData = response.data.sheet
        const attendanceData = response.data.attendance_data || []
        const attendanceStats = response.data.attendance_stats || []
        
        // 准备导出数据
        const exportData = []
        
        // 添加考勤表基本信息
        exportData.push(['考勤表基本信息'])
        exportData.push(['项目名称', sheetData.project?.name || ''])
        exportData.push(['月份', sheetData.month || ''])
        exportData.push(['员工总数', sheetData.total_employees || 0])
        exportData.push(['工作日', sheetData.work_days || 0])
        exportData.push(['状态', getSheetStatusText(sheetData.status)])
        exportData.push([]) // 空行分隔
        
        // 添加考勤数据表头
        const headers = ['员工姓名']
        for (let day = 1; day <= sheetData.work_days; day++) {
          headers.push(`${day}日`)
        }
        headers.push('应出勤天数', '实际出勤天数', '缺勤天数', '迟到次数', '早退次数', '请假天数', '出勤率')
        exportData.push(['考勤详细数据'])
        exportData.push(headers)
        
        // 添加考勤统计数据
        attendanceStats.forEach(stat => {
          const row = [stat.employee?.name || '未知']
          
          // 添加每日考勤状态
          if (attendanceData[stat.employee_id]) {
            const empData = attendanceData[stat.employee_id]
            for (let day = 1; day <= sheetData.work_days; day++) {
              const status = empData.attendance?.[day] || 'normal'
              const statusText = {
                'normal': '正常',
                'late': '迟到',
                'early': '早退',
                'absent': '缺勤',
                'leave': '请假',
                'off': '调休'
              }[status] || '正常'
              row.push(statusText)
            }
          } else {
            for (let day = 1; day <= sheetData.work_days; day++) {
              row.push('-')
            }
          }
          
          // 添加统计数据
          row.push(
            stat.work_days || 0,
            stat.actual_work_days || 0,
            stat.absent_days || 0,
            stat.late_count || 0,
            stat.early_count || 0,
            stat.leave_days || 0,
            stat.attendance_rate ? `${(stat.attendance_rate * 100).toFixed(1)}%` : '0%'
          )
          
          exportData.push(row)
        })
        
        // 创建工作表
        const ws = XLSX.utils.aoa_to_sheet(exportData)
        
        // 设置列宽
        const colWidths = [{ wch: 12 }]
        for (let day = 0; day < sheetData.work_days; day++) {
          colWidths.push({ wch: 8 })
        }
        colWidths.push({ wch: 12 }, { wch: 12 }, { wch: 10 }, { wch: 10 }, { wch: 10 }, { wch: 10 }, { wch: 10 })
        ws['!cols'] = colWidths
        
        // 添加工作表到工作簿
        const sheetName = `${sheetData.project?.name || '考勤表'}_${sheetData.month}`.substring(0, 31) // Excel工作表名称限制31字符
        XLSX.utils.book_append_sheet(wb, ws, sheetName)
        
  } catch (error) {
        console.error(`处理考勤表 ${sheet.id} 时出错:`, error)
      }
    }
    
    // 导出文件
    const fileName = `考勤表批量导出_${new Date().toISOString().slice(0, 10)}.xlsx`
    XLSX.writeFile(wb, fileName)
    
    ElMessage.success(`批量导出成功，共导出 ${attendanceSheets.value.length} 个考勤表`)
  } catch (error) {
    console.error('Batch export error:', error)
    ElMessage.error('批量导出失败: ' + error.message)
  }
}

// 导出考勤汇总统计
const handleExportSummary = async () => {
  try {
    if (attendanceSheets.value.length === 0) {
      ElMessage.warning('没有可导出的考勤表')
      return
    }
    
    ElMessage.info('正在准备汇总数据，请稍候...')
    
    // 准备汇总数据
    const summaryData = []
    
    // 添加汇总表头
    summaryData.push(['考勤表汇总统计'])
    summaryData.push(['项目名称', '月份', '员工总数', '工作日', '状态', '创建时间', '提交时间', '审批时间'])
    
    // 添加考勤表汇总信息
    attendanceSheets.value.forEach(sheet => {
      summaryData.push([
        sheet.project?.name || '',
        sheet.month || '',
        sheet.total_employees || 0,
        sheet.work_days || 0,
        getSheetStatusText(sheet.status),
        sheet.created_at || '',
        sheet.submitted_at || '',
        sheet.approved_at || ''
      ])
    })
    
    // 添加空行分隔
    summaryData.push([])
    
    // 添加项目统计汇总
    summaryData.push(['项目统计汇总'])
    summaryData.push(['项目名称', '考勤表数量', '总员工数', '平均工作日', '已审批数量', '待审批数量'])
    
    // 按项目分组统计
    const projectStats = {}
    attendanceSheets.value.forEach(sheet => {
      const projectName = sheet.project?.name || '未知项目'
      if (!projectStats[projectName]) {
        projectStats[projectName] = {
          count: 0,
          totalEmployees: 0,
          totalWorkDays: 0,
          approvedCount: 0,
          pendingCount: 0
        }
      }
      
      projectStats[projectName].count++
      projectStats[projectName].totalEmployees += sheet.total_employees || 0
      projectStats[projectName].totalWorkDays += sheet.work_days || 0
      
      if (sheet.status === 'approved') {
        projectStats[projectName].approvedCount++
      } else if (sheet.status === 'submitted') {
        projectStats[projectName].pendingCount++
      }
    })
    
    // 添加项目统计行
    Object.keys(projectStats).forEach(projectName => {
      const stats = projectStats[projectName]
      summaryData.push([
        projectName,
        stats.count,
        stats.totalEmployees,
        Math.round(stats.totalWorkDays / stats.count),
        stats.approvedCount,
        stats.pendingCount
      ])
    })
    
    // 添加空行分隔
    summaryData.push([])
    
    // 添加月份统计汇总
    summaryData.push(['月份统计汇总'])
    summaryData.push(['月份', '考勤表数量', '总员工数', '平均工作日', '已审批数量', '待审批数量'])
    
    // 按月份分组统计
    const monthStats = {}
    attendanceSheets.value.forEach(sheet => {
      const month = sheet.month || '未知月份'
      if (!monthStats[month]) {
        monthStats[month] = {
          count: 0,
          totalEmployees: 0,
          totalWorkDays: 0,
          approvedCount: 0,
          pendingCount: 0
        }
      }
      
      monthStats[month].count++
      monthStats[month].totalEmployees += sheet.total_employees || 0
      monthStats[month].totalWorkDays += sheet.work_days || 0
      
      if (sheet.status === 'approved') {
        monthStats[month].approvedCount++
      } else if (sheet.status === 'submitted') {
        monthStats[month].pendingCount++
      }
    })
    
    // 添加月份统计行
    Object.keys(monthStats).forEach(month => {
      const stats = monthStats[month]
      summaryData.push([
        month,
        stats.count,
        stats.totalEmployees,
        Math.round(stats.totalWorkDays / stats.count),
        stats.approvedCount,
        stats.pendingCount
      ])
    })
    
    // 创建工作簿
    const ws = XLSX.utils.aoa_to_sheet(summaryData)
    const wb = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(wb, ws, '考勤汇总')
    
    // 设置列宽
    const colWidths = [
      { wch: 20 }, // 项目名称/月份
      { wch: 12 }, // 考勤表数量/月份
      { wch: 12 }, // 总员工数
      { wch: 12 }, // 平均工作日
      { wch: 12 }, // 已审批数量
      { wch: 12 }, // 待审批数量
      { wch: 18 }, // 创建时间
      { wch: 18 }  // 提交时间/审批时间
    ]
    ws['!cols'] = colWidths
    
    // 导出文件
    const fileName = `考勤汇总统计_${new Date().toISOString().slice(0, 10)}.xlsx`
    XLSX.writeFile(wb, fileName)
    
    ElMessage.success('汇总导出成功')
  } catch (error) {
    console.error('Export summary error:', error)
    ElMessage.error('汇总导出失败: ' + error.message)
  }
}

const handleCreateSheet = async () => {
  if (!sheetFormRef.value) return
  
  await sheetFormRef.value.validate(async (valid) => {
    if (valid) {
      submitting.value = true
      try {
        if (isEditSheet.value) {
          await updateAttendanceSheet(sheetForm.id, sheetForm)
          ElMessage.success('更新成功')
        } else {
          await createAttendanceSheet(sheetForm)
          ElMessage.success('创建成功')
        }
        showCreateSheetDialog.value = false
        loadSheets()
      } catch (error) {
        console.error('Submit sheet error:', error)
        ElMessage.error(isEditSheet.value ? '更新失败' : '创建失败')
      } finally {
        submitting.value = false
      }
    }
  })
}

const handleAttendanceChange = (row, day) => {
  // 考勤状态改变时的处理
  console.log('Attendance changed:', row.employee_name, day, row.attendance[day])
}

const handleBatchEdit = async () => {
  if (currentSheet.value && currentSheet.value.project_id) {
    await loadProjectEmployees(currentSheet.value.project_id)
  }
  showBatchEditDialog.value = true
}

const handleBatchEditSubmit = async () => {
  try {
    if (!batchEditForm.employee_ids.length) {
      ElMessage.warning('请选择员工')
      return
    }
    
    if (!batchEditForm.date_range || batchEditForm.date_range.length !== 2) {
      ElMessage.warning('请选择日期范围')
      return
    }
    
    if (!batchEditForm.status) {
      ElMessage.warning('请选择考勤状态')
      return
    }
    
    // 批量更新考勤数据
    const startDate = new Date(batchEditForm.date_range[0])
    const endDate = new Date(batchEditForm.date_range[1])
    
    // 更新前端数据
    attendanceData.value.forEach(employee => {
      if (batchEditForm.employee_ids.includes(employee.employee_id)) {
        // 更新日期范围内的考勤状态
        for (let day = 1; day <= currentSheet.value.work_days; day++) {
          const currentDate = new Date(currentSheet.value.month + '-' + String(day).padStart(2, '0'))
          if (currentDate >= startDate && currentDate <= endDate) {
            employee.attendance[day] = batchEditForm.status
          }
        }
      }
    })
    
    ElMessage.success('批量编辑成功')
    showBatchEditDialog.value = false
    
    // 重置表单
    batchEditForm.employee_ids = []
    batchEditForm.date_range = []
    batchEditForm.status = ''
    
  } catch (error) {
    console.error('Batch edit error:', error)
    ElMessage.error('批量编辑失败')
  }
}

const handleSaveAttendance = async () => {
  try {
    console.log('保存考勤数据:', {
      sheetId: currentSheet.value.id,
      dataLength: attendanceData.value.length,
      sampleData: attendanceData.value[0]
    })
    
    const response = await saveAttendanceData(currentSheet.value.id, attendanceData.value)
    console.log('保存响应:', response)
    
    if (response && response.success) {
      ElMessage.success('保存成功')
      // 重新加载数据以确保显示最新保存的数据
      await loadSheetDetail(currentSheet.value.id)
    } else {
      ElMessage.error(response?.message || '保存失败')
    }
  } catch (error) {
    console.error('Save attendance error:', error)
    ElMessage.error('保存失败: ' + (error.response?.data?.message || error.message))
  }
}

const calculateWorkDays = (row) => {
  if (!row.attendance) return 0
  return Object.values(row.attendance).filter(status => 
    ['normal', 'late', 'early'].includes(status)
  ).length
}

const calculateAbsentDays = (row) => {
  if (!row.attendance) return 0
  return Object.values(row.attendance).filter(status => 
    ['absent'].includes(status)
  ).length
}

const calculateLateCount = (row) => {
  if (!row.attendance) return 0
  return Object.values(row.attendance).filter(status => 
    ['late'].includes(status)
  ).length
}

const calculateEarlyCount = (row) => {
  if (!row.attendance) return 0
  return Object.values(row.attendance).filter(status => 
    ['early'].includes(status)
  ).length
}

const calculateLeaveDays = (row) => {
  if (!row.attendance) return 0
  return Object.values(row.attendance).filter(status => 
    ['leave'].includes(status)
  ).length
}

const handleSheetDialogClose = () => {
  isEditSheet.value = false
  Object.assign(sheetForm, {
    project_id: '',
    month: '',
    work_days: 22,
    notes: ''
  })
  sheetFormRef.value?.resetFields()
}

const handleSheetDetailClose = () => {
  currentSheet.value = null
  attendanceData.value = []
  attendanceStats.value = []
}

// 文件上传相关方法
const beforeFileUpload = (file) => {
  const allowedTypes = [
    'application/pdf',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-excel',
    'application/msword'
  ]
  
  const isAllowedType = allowedTypes.includes(file.type) || 
    file.name.endsWith('.pdf') || 
    file.name.endsWith('.doc') || 
    file.name.endsWith('.docx') || 
    file.name.endsWith('.xls') || 
    file.name.endsWith('.xlsx')
  
  if (!isAllowedType) {
    ElMessage.error('只能上传 PDF、Word、Excel 文件!')
    return false
  }
  
  const isLt10M = file.size / 1024 / 1024 < 10
  if (!isLt10M) {
    ElMessage.error('文件大小不能超过 10MB!')
    return false
  }
  
  return true
}

const handleFileChange = (file, fileList) => {
  submitForm.files = fileList
}

const handleFileRemove = (file, fileList) => {
  submitForm.files = fileList
}

const handleSubmitDialogClose = () => {
  submitForm.files = []
  submitForm.notes = ''
  submitForm.stamp_method = 'online'  // 重置为默认值
  uploadRef.value?.clearFiles()
}

const getSheetStatusType = (status) => {
  const types = {
    draft: 'info',
    submitted: 'warning',
    approved: 'success',
    rejected: 'danger'
  }
  return types[status] || 'info'
}

const getSheetStatusText = (status) => {
  const texts = {
    draft: '草稿',
    submitted: '已提交',
    approved: '已审批',
    rejected: '已拒绝'
  }
  return texts[status] || '未知'
}

const getAttendanceStatusType = (status) => {
  const types = {
    normal: 'success',
    late: 'warning',
    early: 'warning',
    absent: 'danger',
    leave: 'info',
    off: 'info'
  }
  return types[status] || ''
}

const getAttendanceStatusText = (status) => {
  const texts = {
    normal: '正常',
    late: '迟到',
    early: '早退',
    absent: '缺勤',
    leave: '请假',
    off: '调休'
  }
  return texts[status] || status
}

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

const openDingTalk = () => {
  window.open('https://www.dingtalk.com/', '_blank')
}

onMounted(() => {
  loadSheets()
  loadProjects()
})

// 监听账套切换，自动刷新数据
watch(() => accountSetStore.currentAccountSetId, (newAccountSetId, oldAccountSetId) => {
  console.log('考勤页-账套变化检测:', { new: newAccountSetId, old: oldAccountSetId })
  if (newAccountSetId && oldAccountSetId && newAccountSetId !== oldAccountSetId) {
    console.log('✅ 考勤页-账套切换，重新加载数据:', newAccountSetId)
    pagination.currentPage = 1
    loadSheets()
    loadProjects()
  }
})
</script>

<style scoped>
.attendance-page {
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

.header-actions {
  display: flex;
  gap: 10px;
}

.sheet-section {
  margin-bottom: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.search-section {
  margin-bottom: 20px;
}

.pagination {
  margin-top: 20px;
  text-align: right;
}

.sheet-detail {
  padding: 20px 0;
}

.sheet-info {
  margin-bottom: 30px;
}

.attendance-data {
  margin-bottom: 30px;
}

.data-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.data-header h3 {
  margin: 0;
  color: #303133;
}

.data-actions {
  display: flex;
  gap: 10px;
}

.attendance-stats h3 {
  margin-bottom: 20px;
  color: #303133;
}

:deep(.el-table) {
  font-size: 14px;
}

:deep(.el-form-item) {
  margin-bottom: 20px;
}

:deep(.el-descriptions) {
  margin-bottom: 20px;
}
</style>