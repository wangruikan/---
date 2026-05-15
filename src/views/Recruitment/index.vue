<template>
  <div class="recruitment-page">
    <div class="page-header">
      <h1>招聘管理</h1>
    </div>
    
    <!-- 统计卡片 -->
    <el-row :gutter="20" class="statistics-row">
      <el-col :span="6">
        <el-card class="stat-card total">
          <div class="stat-content">
            <div class="stat-icon">
              <el-icon><Document /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ statistics.total || 0 }}</div>
              <div class="stat-label">总需求</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card active">
          <div class="stat-content">
            <div class="stat-icon">
              <el-icon><Clock /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ statistics.active || 0 }}</div>
              <div class="stat-label">进行中</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card completed">
          <div class="stat-content">
            <div class="stat-icon">
              <el-icon><Check /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ statistics.completed || 0 }}</div>
              <div class="stat-label">已完成</div>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card candidates">
          <div class="stat-content">
            <div class="stat-icon">
              <el-icon><User /></el-icon>
            </div>
            <div class="stat-info">
              <div class="stat-value">{{ statistics.total_candidates || 0 }}</div>
              <div class="stat-label">候选人总数</div>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>
    
    <!-- 搜索和筛选 -->
    <el-card class="search-card">
        <el-form :model="searchForm" inline>
          <el-form-item label="职位名称">
            <el-input
              v-model="searchForm.position"
              placeholder="请输入职位名称"
              clearable
            style="width: 200px"
            />
          </el-form-item>
          
          <el-form-item label="项目">
            <el-select
              v-model="searchForm.project_id"
              placeholder="请选择项目"
              clearable
            style="width: 200px"
            >
              <el-option
                v-for="project in projects"
                :key="project.id"
                :label="project.name"
                :value="project.id"
              />
            </el-select>
          </el-form-item>
          
          <el-form-item label="状态">
            <el-select
              v-model="searchForm.status"
              placeholder="请选择状态"
              clearable
            style="width: 150px"
            >
            <el-option label="待分配" value="pending" />
              <el-option label="进行中" value="active" />
              <el-option label="已完成" value="completed" />
              <el-option label="已暂停" value="paused" />
              <el-option label="已取消" value="cancelled" />
            </el-select>
          </el-form-item>
        
        <el-form-item label="业务员">
          <el-select
            v-model="searchForm.assigned_to"
            placeholder="请选择业务员"
            clearable
            style="width: 150px"
          >
            <el-option
              v-for="user in users"
              :key="user.id"
              :label="user.name"
              :value="user.id"
            />
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
    
    <!-- 招聘列表 -->
    <el-card class="table-card">
        <el-table
          :data="recruitments"
          v-loading="loading"
          stripe
          border
        >
          <el-table-column prop="position" label="职位名称" width="150" />
          <el-table-column prop="project_name" label="项目名称" width="150" />
          <el-table-column prop="department" label="部门" width="120" />
        <el-table-column label="招聘进度" width="250">
          <template #default="{ row }">
            <div class="progress-info">
              <div class="progress-item">
                <span class="label">需求:</span>
                <el-tag type="info" size="small">{{ row.required_count }}</el-tag>
              </div>
              <div class="progress-item">
                <span class="label">申请:</span>
                <el-tag type="primary" size="small">{{ row.applied_count || 0 }}</el-tag>
              </div>
              <div class="progress-item">
                <span class="label">面试:</span>
                <el-tag type="warning" size="small">{{ row.interviewed_count || 0 }}</el-tag>
              </div>
              <div class="progress-item">
                <span class="label">录用:</span>
                <el-tag type="success" size="small">{{ row.hired_count || 0 }}</el-tag>
              </div>
            </div>
          </template>
        </el-table-column>
          <el-table-column prop="salary_range" label="薪资范围" width="150" />
        <el-table-column prop="education_text" label="学历要求" width="100" />
          <el-table-column prop="status" label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="getStatusType(row.status)">
                {{ getStatusText(row.status) }}
              </el-tag>
            </template>
          </el-table-column>
        <el-table-column label="操作" width="500" fixed="right">
            <template #default="{ row }">
            <el-button type="info" size="small" @click="handleView(row)">
                查看
              </el-button>
              <el-button 
              v-if="row.status === 'pending'" 
              type="primary" 
              size="small" 
              @click="handleAssign(row)"
            >
              分配
            </el-button>
            <el-button
              v-if="row.status === 'active'"
              type="primary"
              size="small"
              @click="handleManageCandidates(row)"
            >
              管理候选人
            </el-button>
              <el-button 
                v-if="row.status === 'active'" 
              type="primary" 
                size="small" 
                @click="handleUpdateProgress(row)"
              >
                更新进度
              </el-button>
              <el-button 
                v-if="row.status === 'active'" 
                type="success" 
                size="small" 
                @click="handleComplete(row)"
              >
                完成
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
    
    <!-- 创建/编辑招聘需求对话框 -->
    <el-dialog
      v-model="showFormDialog"
      :title="dialogTitle"
      width="900px"
      @close="handleDialogClose"
    >
      <el-form
        ref="formRef"
        :model="form"
        :rules="formRules"
        label-width="120px"
      >
        <el-divider content-position="left">基本信息</el-divider>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="职位名称" prop="position">
              <el-input v-model="form.position" placeholder="请输入职位名称" :disabled="isViewMode" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="所属项目" prop="project_id">
              <el-select v-model="form.project_id" placeholder="请选择项目" style="width: 100%" :disabled="isViewMode">
                <el-option
                  v-for="project in projects"
                  :key="project.id"
                  :label="project.name"
                  :value="project.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="所属部门" prop="department">
              <el-input v-model="form.department" placeholder="请输入部门" :disabled="isViewMode" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="招聘人数" prop="required_count">
              <el-input-number
                v-model="form.required_count"
                :min="1"
                :max="999"
                style="width: 100%"
                :disabled="isViewMode"
              />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-divider content-position="left">薪资与福利</el-divider>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="薪资范围" prop="salary_range">
              <el-input v-model="form.salary_range" placeholder="如：8000-12000元/月" :disabled="isViewMode" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="工作地点" prop="work_location">
              <el-input v-model="form.work_location" placeholder="请输入工作地点" :disabled="isViewMode" />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-divider content-position="left">任职要求</el-divider>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="学历要求" prop="education">
              <el-select v-model="form.education" placeholder="请选择学历要求" style="width: 100%" :disabled="isViewMode">
                <el-option label="不限" value="none" />
                <el-option label="高中及以下" value="high_school" />
                <el-option label="中专/大专" value="college" />
                <el-option label="本科" value="bachelor" />
                <el-option label="硕士" value="master" />
                <el-option label="博士" value="doctor" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="工作经验" prop="experience">
              <el-input v-model="form.experience" placeholder="如：3-5年" :disabled="isViewMode" />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-form-item label="职位描述" prop="description">
          <el-input
            v-model="form.description"
            type="textarea"
            :rows="4"
            placeholder="请详细描述职位职责和工作内容"
            :disabled="isViewMode"
          />
        </el-form-item>
        
        <el-form-item label="任职要求" prop="requirements">
          <el-input
            v-model="form.requirements"
            type="textarea"
            :rows="4"
            placeholder="请详细列出任职要求（如：专业技能、工作经验、个人素质等）"
            :disabled="isViewMode"
          />
        </el-form-item>
        
        <el-divider content-position="left">招聘时间</el-divider>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="开始日期" prop="start_date">
              <el-date-picker
                v-model="form.start_date"
                type="date"
                placeholder="请选择开始日期"
                style="width: 100%"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
                :disabled="isViewMode"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="期望完成日期" prop="end_date">
              <el-date-picker
                v-model="form.end_date"
                type="date"
                placeholder="请选择期望完成日期"
                style="width: 100%"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
                :disabled="isViewMode"
              />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-form-item label="备注">
          <el-input
            v-model="form.notes"
            type="textarea"
            :rows="2"
            placeholder="其他补充说明（可选）"
            :disabled="isViewMode"
          />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showFormDialog = false">
          {{ isViewMode ? '关闭' : '取消' }}
        </el-button>
        <el-button 
          v-if="!isViewMode" 
          type="primary" 
          @click="handleSubmit" 
          :loading="submitting"
        >
          {{ isEdit ? '保存修改' : '创建需求' }}
        </el-button>
      </template>
    </el-dialog>

    <!-- 分配任务确认对话框 -->
    <el-dialog
      v-model="showAssignDialog"
      title="分配招聘任务"
      width="500px"
    >
      <el-form :model="assignForm" label-width="120px">
        <el-form-item label="招聘职位">
          <el-input :value="currentRecruitment?.position" disabled />
        </el-form-item>
        <el-form-item label="招聘人数">
          <el-input :value="currentRecruitment?.required_count" disabled />
        </el-form-item>
        <el-form-item label="分配给">
          <el-input value="账套经办人员" disabled />
          <div style="color: #909399; font-size: 12px; margin-top: 5px;">
            系统将自动分配给当前账套的经办人员
          </div>
        </el-form-item>
        <el-form-item label="分配说明">
          <el-input
            v-model="assignForm.notes"
            type="textarea"
            :rows="3"
            placeholder="请输入分配说明或特殊要求（可选）"
          />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showAssignDialog = false">取消</el-button>
        <el-button type="primary" @click="handleConfirmAssign" :loading="assigning">
          确认分配
        </el-button>
      </template>
    </el-dialog>

    <!-- 更新进度对话框 -->
    <el-dialog
      v-model="showProgressDialog"
      title="更新招聘进度"
      width="700px"
      :close-on-click-modal="false"
      class="progress-dialog"
    >
      <div class="dialog-content">
        <!-- 招聘信息卡片 -->
        <div class="recruitment-info-card">
          <div class="info-header">
            <el-icon class="info-icon"><User /></el-icon>
            <span class="info-title">招聘信息</span>
          </div>
          <div class="info-content">
            <div class="info-item">
              <span class="label">招聘职位：</span>
              <span class="value">{{ currentRecruitment?.position }}</span>
            </div>
            <div class="info-item">
              <span class="label">计划人数：</span>
              <span class="value">{{ currentRecruitment?.required_count }}人</span>
            </div>
          </div>
        </div>

        <!-- 进度统计卡片 -->
        <div class="progress-stats-card">
          <div class="stats-header">
            <el-icon class="stats-icon"><Document /></el-icon>
            <span class="stats-title">进度统计</span>
          </div>
          <div class="stats-content">
            <div class="stat-item">
              <div class="stat-label">
                <span class="label-text">申请人数</span>
                <span class="label-desc">已收到的简历数量</span>
              </div>
              <div class="stat-input">
                <el-input-number
                  v-model="progressForm.applied_count"
                  :min="0"
                  :max="999"
                  controls-position="right"
                  size="large"
                />
              </div>
            </div>
            
            <div class="stat-item">
              <div class="stat-label">
                <span class="label-text">面试人数</span>
                <span class="label-desc">已安排面试的候选人</span>
              </div>
              <div class="stat-input">
                <el-input-number
                  v-model="progressForm.interviewed_count"
                  :min="0"
                  :max="progressForm.applied_count || 999"
                  controls-position="right"
                  size="large"
                />
              </div>
            </div>
            
            <div class="stat-item">
              <div class="stat-label">
                <span class="label-text">录用人数</span>
                <span class="label-desc">已确认录用的候选人</span>
              </div>
              <div class="stat-input">
                <el-input-number
                  v-model="progressForm.hired_count"
                  :min="0"
                  :max="progressForm.interviewed_count || 999"
                  controls-position="right"
                  size="large"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- 进度说明 -->
        <div class="progress-notes-card">
          <div class="notes-header">
            <el-icon class="notes-icon"><Document /></el-icon>
            <span class="notes-title">进度说明</span>
          </div>
          <div class="notes-content">
            <el-input
              v-model="progressForm.progress_notes"
              type="textarea"
              :rows="4"
              placeholder="请详细说明本次进度更新的情况，包括面试情况、候选人表现、录用决策等信息..."
              maxlength="500"
              show-word-limit
              resize="none"
            />
          </div>
        </div>
      </div>
      
      <template #footer>
        <div class="dialog-footer">
          <el-button @click="showProgressDialog = false" size="large">
            取消
          </el-button>
          <el-button 
            type="primary" 
            @click="handleConfirmUpdateProgress"
            size="large"
            :loading="submitting"
          >
            确认更新
          </el-button>
        </div>
      </template>
    </el-dialog>

    <!-- 候选人管理对话框 -->
    <el-dialog
      v-model="showCandidatesDialog"
      title="候选人管理"
      width="1200px"
      :close-on-click-modal="false"
      class="candidates-dialog"
    >
      <div class="candidates-header">
        <h3>{{ currentRecruitment?.position }} - 候选人列表</h3>
        <el-button
          type="primary"
          size="small"
          @click="handleAddCandidate"
        >
          <el-icon><Plus /></el-icon>
          添加候选人
        </el-button>
      </div>
      
      <el-table :data="candidates" border stripe class="candidates-table">
        <el-table-column prop="name" label="姓名" width="100" />
        <el-table-column label="性别" width="60">
          <template #default="{ row }">
            {{ getGenderText(row.gender) }}
          </template>
        </el-table-column>
        <el-table-column prop="age" label="年龄" width="60" />
        <el-table-column prop="phone" label="联系电话" width="120" />
        <el-table-column prop="email" label="邮箱" width="180" />
        <el-table-column label="学历" width="100">
          <template #default="{ row }">
            {{ getEducationText(row.education) }}
          </template>
        </el-table-column>
        <el-table-column prop="experience" label="工作经验" width="100" />
        <el-table-column label="简历" width="80">
          <template #default="{ row }">
            <span v-if="row.resume_url" @click.prevent="() => downloadCandidateResume(row)" style="color: #409eff; text-decoration: underline; cursor: pointer;">
              下载
            </span>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <span :class="['candidate-status-tag', row.status]">
              {{ getCandidateStatusText(row.status) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" size="small" @click="handleViewCandidate(row)">
              查看
            </el-button>
            <el-button
              type="warning"
              size="small"
              @click="handleEditCandidate(row)"
            >
              编辑
            </el-button>
            <el-button
              type="danger"
              size="small"
              @click="handleDeleteCandidate(row)"
            >
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>
      
      <template #footer>
        <el-button @click="showCandidatesDialog = false">关闭</el-button>
      </template>
    </el-dialog>

    <!-- 添加/编辑候选人对话框 -->
    <el-dialog
      v-model="showCandidateFormDialog"
      :title="isCandidateViewMode ? '查看候选人' : (isCandidateEdit ? '编辑候选人' : '添加候选人')"
      width="800px"
      :close-on-click-modal="false"
      class="candidate-form-dialog"
      @close="handleCandidateDialogClose"
    >
      <el-form :model="candidateForm" :rules="candidateFormRules" ref="candidateFormRef" label-width="100px">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="姓名" prop="name">
              <el-input v-model="candidateForm.name" placeholder="请输入姓名" :disabled="isCandidateViewMode" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="性别" prop="gender">
              <el-select v-model="candidateForm.gender" placeholder="请选择性别" style="width: 100%" :disabled="isCandidateViewMode">
                <el-option label="男" value="male" />
                <el-option label="女" value="female" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="年龄" prop="age">
              <el-input-number v-model="candidateForm.age" :min="18" :max="65" style="width: 100%" :disabled="isCandidateViewMode" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="联系电话" prop="phone">
              <el-input v-model="candidateForm.phone" placeholder="请输入联系电话" :disabled="isCandidateViewMode" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="邮箱" prop="email">
          <el-input v-model="candidateForm.email" placeholder="请输入邮箱" :disabled="isCandidateViewMode" />
        </el-form-item>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="学历" prop="education">
              <el-select v-model="candidateForm.education" placeholder="请选择学历" style="width: 100%" :disabled="isCandidateViewMode">
                <el-option label="高中及以下" value="high_school" />
                <el-option label="中专/大专" value="college" />
                <el-option label="本科" value="bachelor" />
                <el-option label="硕士" value="master" />
                <el-option label="博士" value="doctor" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="工作经验" prop="experience">
              <el-input v-model="candidateForm.experience" placeholder="如：3年" :disabled="isCandidateViewMode" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="状态" prop="status">
          <el-select v-model="candidateForm.status" placeholder="请选择状态" style="width: 100%" :disabled="isCandidateViewMode">
            <el-option label="待面试" value="pending" />
            <el-option label="面试中" value="interviewing" />
            <el-option label="待录用" value="to_be_hired" />
            <el-option label="已录用" value="hired" />
            <el-option label="已拒绝" value="rejected" />
          </el-select>
        </el-form-item>

        <el-form-item label="简历附件">
          <div v-if="!isCandidateViewMode">
            <el-upload
              ref="resumeUploadRef"
              class="upload-demo"
              :http-request="handleResumeUpload"
              :before-upload="beforeResumeUpload"
              :show-file-list="false"
              :limit="1"
            >
              <el-button size="small" type="primary">点击上传简历</el-button>
              <template #tip>
                <div class="el-upload__tip">
                  支持任意格式，文件大小不超过10MB
                </div>
              </template>
            </el-upload>
          </div>
          <div v-if="candidateForm.resume_url" class="resume-file-info">
            <span class="resume-file-label">已上传：</span>
            <span class="resume-file-name" @click.prevent="downloadResume">
              {{ getResumeDisplayName() }}
            </span>
            <el-button
              v-if="!isCandidateViewMode"
              type="danger"
              size="small"
              @click="handleDeleteResume"
            >
              删除
            </el-button>
          </div>
          <div v-if="!candidateForm.resume_url && isCandidateViewMode" class="resume-file-info">
            <span class="resume-file-label">未上传简历</span>
          </div>
        </el-form-item>

        <el-form-item label="备注">
          <el-input
            v-model="candidateForm.notes"
            type="textarea"
            :rows="3"
            placeholder="其他补充信息"
            :disabled="isCandidateViewMode"
          />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showCandidateFormDialog = false">取消</el-button>
        <el-button type="primary" @click="handleSubmitCandidate" :disabled="isCandidateViewMode">
          {{ isCandidateEdit ? '保存' : '添加' }}
        </el-button>
      </template>
    </el-dialog>

    <!-- 完成招聘对话框 -->
    <el-dialog
      v-model="showCompleteDialog"
      title="完成招聘"
      width="600px"
    >
      <el-form :model="completeForm" label-width="120px">
        <el-form-item label="招聘职位">
          <el-input :value="currentRecruitment?.position" disabled />
        </el-form-item>
        <el-form-item label="计划人数">
          <el-input :value="currentRecruitment?.required_count" disabled />
        </el-form-item>
        <el-form-item label="实际录用人数" required>
          <el-input-number
            v-model="completeForm.hired_count"
            :min="0"
            :max="currentRecruitment?.required_count || 999"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="完成情况说明">
          <el-input
            v-model="completeForm.completion_notes"
            type="textarea"
            :rows="4"
            placeholder="请详细说明招聘完成情况（如：录用人员名单、未完成原因等）"
          />
        </el-form-item>
        <el-form-item label="候选人信息">
          <el-input
            v-model="completeForm.candidates_summary"
            type="textarea"
            :rows="3"
            placeholder="录用人员的基本信息汇总（可选）"
          />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showCompleteDialog = false">取消</el-button>
        <el-button type="primary" @click="handleConfirmComplete">
          确认完成
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, nextTick } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Search, Refresh, Document, Clock, Check, User } from '@element-plus/icons-vue'
import {
  getRecruitments,
  createRecruitment,
  updateRecruitment,
  deleteRecruitment,
  assignRecruitment,
  updateProgress,
  completeRecruitment,
  getCandidates,
  addCandidate,
  updateCandidate,
  deleteCandidate,
  deleteCandidateResume,
  getRecruitmentPermissions
} from '@/api/recruitment'
import { getProjects } from '@/api/projects'
import { useUserStore } from '@/stores/user'

const userStore = useUserStore()

// 响应式数据
const loading = ref(false)
const submitting = ref(false)
const assigning = ref(false)
const showFormDialog = ref(false)
const showAssignDialog = ref(false)
const showProgressDialog = ref(false)
const showCompleteDialog = ref(false)
const showCandidatesDialog = ref(false)
const showCandidateFormDialog = ref(false)
const isEdit = ref(false)
const isViewMode = ref(false)
const isCandidateEdit = ref(false)
const isCandidateViewMode = ref(false)
const formRef = ref()
const candidateFormRef = ref()
const resumeUploadRef = ref()
const uploadedResumeOriginalName = ref('')

const recruitments = ref([])
const projects = ref([])
const users = ref([])
const candidates = ref([])
const currentRecruitment = ref(null)

// 权限控制
const permissions = ref({
  is_handler: false,      // 是否是经办人员
  is_approver: false,     // 是否是审批人员
  can_create: false,      // 可以创建招聘需求
  can_edit: false,        // 可以编辑招聘需求
  can_manage_candidates: false,  // 可以管理候选人
  can_update_progress: false,    // 可以更新进度
  can_complete: false,           // 可以完成招聘
  can_view_candidates: true,     // 所有人都可以查看候选人
})

// 统计数据
const statistics = ref({
  total: 0,
  active: 0,
  completed: 0,
  total_candidates: 0
})

// 搜索表单
const searchForm = reactive({
  position: '',
  project_id: '',
  status: '',
  assigned_to: ''
})

// 分页
const pagination = reactive({
  currentPage: 1,
  pageSize: 20,
  total: 0
})

// 招聘需求表单
const form = reactive({
  position: '',
  project_id: '',
  department: '',
  required_count: 1,
  salary_range: '',
  work_location: '',
  education: '',
  experience: '',
  description: '',
  requirements: '',
  start_date: '',
  end_date: '',
  notes: ''
})

// 分配表单
const assignForm = reactive({
  notes: ''
})

// 进度更新表单
const progressForm = reactive({
  applied_count: 0,
  interviewed_count: 0,
  hired_count: 0,
  progress_notes: ''
})

// 完成表单
const completeForm = reactive({
  hired_count: 0,
  completion_notes: '',
  candidates_summary: ''
})

// 候选人表单
const candidateForm = reactive({
  name: '',
  gender: '',
  age: null,
  phone: '',
  email: '',
  education: '',
  experience: '',
  status: 'pending',
  resume_url: '',
  notes: ''
})

const clearResumeUploadState = () => {
  nextTick(() => {
    if (resumeUploadRef.value && typeof resumeUploadRef.value.clearFiles === 'function') {
      resumeUploadRef.value.clearFiles()
    }
  })
}

const getResumeFileNameFromUrl = (url) => {
  if (!url || typeof url !== 'string') return '简历附件'
  const sanitizedUrl = url.split('?')[0]
  const fileName = sanitizedUrl.substring(sanitizedUrl.lastIndexOf('/') + 1)
  try {
    return decodeURIComponent(fileName) || '简历附件'
  } catch (error) {
    return fileName || '简历附件'
  }
}

const getResumeDisplayName = () => {
  return uploadedResumeOriginalName.value || getResumeFileNameFromUrl(candidateForm.resume_url)
}

// 表单验证规则
const formRules = {
  position: [{ required: true, message: '请输入职位名称', trigger: 'blur' }],
  project_id: [{ required: true, message: '请选择项目', trigger: 'change' }],
  department: [{ required: true, message: '请输入部门', trigger: 'blur' }],
  required_count: [{ required: true, message: '请输入招聘人数', trigger: 'blur' }],
  salary_range: [{ required: true, message: '请输入薪资范围', trigger: 'blur' }],
  description: [{ required: true, message: '请输入职位描述', trigger: 'blur' }],
  requirements: [{ required: true, message: '请输入任职要求', trigger: 'blur' }]
}

const candidateFormRules = {
  name: [{ required: true, message: '请输入姓名', trigger: 'blur' }],
  phone: [{ required: true, message: '请输入联系电话', trigger: 'blur' }],
  gender: [{ required: true, message: '请选择性别', trigger: 'change' }]
}

// 计算属性
const dialogTitle = computed(() => {
  if (isViewMode.value) return '查看招聘需求'
  if (isEdit.value) return '编辑招聘需求'
  return '新增招聘需求'
})

// 加载数据
const loadRecruitments = async () => {
  loading.value = true
  try {
    const params = {
      page: pagination.currentPage,
      per_page: pagination.pageSize,
      ...searchForm
    }
    
    const response = await getRecruitments(params)
    recruitments.value = response.data
    pagination.total = response.total
    
    // 更新统计数据
    updateStatistics()
  } catch (error) {
    console.error('加载招聘列表失败:', error)
    ElMessage.error('加载招聘列表失败')
  } finally {
    loading.value = false
  }
}

const updateStatistics = () => {
  statistics.value = {
    total: recruitments.value.length,
    active: recruitments.value.filter(r => r.status === 'active').length,
    completed: recruitments.value.filter(r => r.status === 'completed').length,
    total_candidates: recruitments.value.reduce((sum, r) => sum + (r.applied_count || 0), 0)
  }
}

const loadProjects = async () => {
  try {
    const response = await getProjects()
    projects.value = response.data.data
  } catch (error) {
    console.error('加载项目列表失败:', error)
  }
}

const loadUsers = async () => {
  try {
    // 这里需要添加获取用户列表的API
    // const response = await getUsers()
    // users.value = response.data
    
    // 临时模拟数据
    users.value = [
      { id: 1, name: '张三' },
      { id: 2, name: '李四' },
      { id: 3, name: '王五' }
    ]
  } catch (error) {
    console.error('加载用户列表失败:', error)
  }
}

const loadCandidates = async (recruitmentId) => {
  try {
    const response = await getCandidates(recruitmentId)
    candidates.value = response.data
  } catch (error) {
    console.error('加载候选人列表失败:', error)
    ElMessage.error('加载候选人列表失败')
  }
}

// 事件处理
const handleSearch = () => {
  pagination.currentPage = 1
  loadRecruitments()
}

const handleReset = () => {
  Object.assign(searchForm, {
    position: '',
    project_id: '',
    status: '',
    assigned_to: ''
  })
  handleSearch()
}

const handleSizeChange = (size) => {
  pagination.pageSize = size
  loadRecruitments()
}

const handleCurrentChange = (page) => {
  pagination.currentPage = page
  loadRecruitments()
}

const handleCreate = () => {
  isEdit.value = false
  isViewMode.value = false
  showFormDialog.value = true
}

const handleView = (row) => {
  isViewMode.value = true
  isEdit.value = false
  Object.assign(form, row)
  showFormDialog.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  isViewMode.value = false
  Object.assign(form, row)
  showFormDialog.value = true
}

const handleAssign = (row) => {
  currentRecruitment.value = row
  assignForm.notes = ''
  showAssignDialog.value = true
}

const handleConfirmAssign = async () => {
  assigning.value = true
  try {
    // 提交时不传assigned_to，后端会自动获取账套经办人
    await assignRecruitment(currentRecruitment.value.id, assignForm)
    ElMessage.success('已分配给账套经办人员')
    showAssignDialog.value = false
    loadRecruitments()
  } catch (error) {
    console.error('分配失败:', error)
    ElMessage.error(error.message || '分配失败')
  } finally {
    assigning.value = false
  }
}

const handleUpdateProgress = (row) => {
  currentRecruitment.value = row
  progressForm.applied_count = row.applied_count || 0
  progressForm.interviewed_count = row.interviewed_count || 0
  progressForm.hired_count = row.hired_count || 0
  progressForm.progress_notes = ''
  showProgressDialog.value = true
}

const handleConfirmUpdateProgress = async () => {
  submitting.value = true
  try {
    await updateProgress(currentRecruitment.value.id, progressForm)
    ElMessage.success('进度更新成功')
    showProgressDialog.value = false
    loadRecruitments()
  } catch (error) {
    console.error('更新进度失败:', error)
    ElMessage.error(error.message || '更新进度失败')
  } finally {
    submitting.value = false
  }
}

const handleManageCandidates = async (row) => {
  currentRecruitment.value = row
  await loadCandidates(row.id)
  showCandidatesDialog.value = true
}

const handleViewCandidates = async (row) => {
  currentRecruitment.value = row
  await loadCandidates(row.id)
  showCandidatesDialog.value = true
}

const handleAddCandidate = () => {
  isCandidateEdit.value = false
  isCandidateViewMode.value = false
  uploadedResumeOriginalName.value = ''
  Object.assign(candidateForm, {
    name: '',
    gender: '',
    age: null,
    phone: '',
    email: '',
    education: '',
    experience: '',
    status: 'pending',
    resume_url: '',
    notes: ''
  })
  clearResumeUploadState()
  showCandidateFormDialog.value = true
}

const handleViewCandidate = (row) => {
  // 查看候选人详情 - 使用编辑表单但禁用所有字段
  isCandidateViewMode.value = true
  isCandidateEdit.value = false
  uploadedResumeOriginalName.value = ''
  Object.assign(candidateForm, row)
  clearResumeUploadState()
  showCandidateFormDialog.value = true
}

const handleEditCandidate = (row) => {
  isCandidateEdit.value = true
  isCandidateViewMode.value = false
  uploadedResumeOriginalName.value = ''
  Object.assign(candidateForm, row)
  clearResumeUploadState()
  showCandidateFormDialog.value = true
}

const handleDeleteCandidate = async (row) => {
  try {
    await ElMessageBox.confirm('确定要删除该候选人吗？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    
    await deleteCandidate(row.id)
    ElMessage.success('删除成功')
    loadCandidates(currentRecruitment.value.id)
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      ElMessage.error('删除失败')
    }
  }
}

const handleSubmitCandidate = async () => {
  if (!candidateFormRef.value) return
  
  // 验证是否有当前招聘需求
  if (!currentRecruitment.value || !currentRecruitment.value.id) {
    ElMessage.error('招聘需求信息丢失，请重新打开')
    showCandidateFormDialog.value = false
    return
  }
  
  await candidateFormRef.value.validate(async (valid) => {
    if (valid) {
      try {
        const data = {
          ...candidateForm,
          recruitment_id: currentRecruitment.value.id
        }
        
        console.log('提交候选人数据:', data)
        
        if (isCandidateEdit.value) {
          await updateCandidate(candidateForm.id, data)
          ElMessage.success('更新成功')
        } else {
          await addCandidate(data)
          ElMessage.success('添加成功')
        }
        
        showCandidateFormDialog.value = false
        loadCandidates(currentRecruitment.value.id)
      } catch (error) {
        console.error('提交失败:', error)
        ElMessage.error('提交失败')
      }
    }
  })
}

const handleComplete = (row) => {
  currentRecruitment.value = row
  completeForm.hired_count = row.hired_count || 0
  completeForm.completion_notes = ''
  completeForm.candidates_summary = ''
  showCompleteDialog.value = true
}

const handleConfirmComplete = async () => {
  if (!completeForm.completion_notes) {
    ElMessage.warning('请填写完成情况说明')
    return
  }
  
  try {
    await completeRecruitment(currentRecruitment.value.id, completeForm)
    ElMessage.success('招聘任务已完成')
    showCompleteDialog.value = false
    loadRecruitments()
  } catch (error) {
    console.error('完成失败:', error)
    ElMessage.error('完成失败')
  }
}

const handleSubmit = async () => {
  if (!formRef.value) return
  
  await formRef.value.validate(async (valid) => {
    if (valid) {
      submitting.value = true
      try {
        if (isEdit.value) {
          await updateRecruitment(form.id, form)
          ElMessage.success('更新成功')
        } else {
          await createRecruitment(form)
          ElMessage.success('创建成功')
        }
        
        showFormDialog.value = false
        loadRecruitments()
      } catch (error) {
        console.error('提交失败:', error)
        ElMessage.error('提交失败')
      } finally {
        submitting.value = false
      }
    }
  })
}

const handleDialogClose = () => {
  isEdit.value = false
  isViewMode.value = false
  Object.assign(form, {
    position: '',
    project_id: '',
    department: '',
    required_count: 1,
    salary_range: '',
    work_location: '',
    education: '',
    experience: '',
    description: '',
    requirements: '',
    start_date: '',
    end_date: '',
    notes: ''
  })
  formRef.value?.resetFields()
}

const handleCandidateDialogClose = () => {
  isCandidateEdit.value = false
  isCandidateViewMode.value = false
  uploadedResumeOriginalName.value = ''
  clearResumeUploadState()
}

// 文件上传
import axios from 'axios'

const handleResumeUpload = async (options) => {
  const { file, onSuccess, onError } = options
  const userStore = useUserStore()
  const currentAccountSetId = localStorage.getItem('current_account_set_id')

  const formData = new FormData()
  formData.append('file', file)

  if (currentAccountSetId) {
    formData.append('current_account_set_id', parseInt(currentAccountSetId, 10))
  }

  try {
    const response = await axios.post('/api/recruitment/candidates/upload-resume', formData, {
      headers: {
        'Authorization': `Bearer ${userStore.token}`,
        'X-Auth-Token': userStore.token,
        'X-Account-Set-Id': currentAccountSetId ? parseInt(currentAccountSetId, 10) : undefined,
        'Content-Type': 'multipart/form-data'
      }
    })

    if (response.data.success) {
      candidateForm.resume_url = response.data.data.url
      uploadedResumeOriginalName.value = response.data.data.original_name || getResumeFileNameFromUrl(response.data.data.url)
      ElMessage.success('简历上传成功')
      onSuccess(response.data, file)
    } else {
      ElMessage.error(response.data.message || '上传失败')
      onError(new Error(response.data.message))
    }
  } catch (error) {
    console.error('上传失败:', error)
    ElMessage.error('上传失败')
    onError(error)
  }
}

// 下载简历（列表页）
const downloadCandidateResume = async (row) => {
  if (!row.resume_url) return

  try {
    const userStore = useUserStore()
    const currentAccountSetId = localStorage.getItem('current_account_set_id')

    let url = row.resume_url
    if (!url.startsWith('http')) {
      url = window.location.origin + url
    }

    const response = await fetch(url, {
      headers: {
        'Authorization': `Bearer ${userStore.token}`,
        'X-Auth-Token': userStore.token,
        'X-Account-Set-Id': currentAccountSetId || undefined
      },
      credentials: 'include'
    })

    if (!response.ok) {
      throw new Error('下载失败')
    }

    const contentDisposition = response.headers.get('Content-Disposition')
    let filename = 'resume'
    if (contentDisposition) {
      const match = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/)
      if (match) {
        filename = match[1].replace(/['"]/g, '')
      }
    }

    const urlParts = row.resume_url.split('/')
    const urlFilename = urlParts[urlParts.length - 1]
    if (urlFilename.includes('.')) {
      filename = decodeURIComponent(urlFilename)
    }

    const blob = await response.blob()
    const downloadUrl = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = downloadUrl
    link.download = filename
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(downloadUrl)
  } catch (error) {
    console.error('下载失败:', error)
    ElMessage.error('下载失败')
  }
}

// 下载简历
const downloadResume = async () => {
  if (!candidateForm.resume_url) return

  try {
    const userStore = useUserStore()
    const currentAccountSetId = localStorage.getItem('current_account_set_id')

    // 构建完整的下载URL
    let url = candidateForm.resume_url
    if (!url.startsWith('http')) {
      url = window.location.origin + url
    }

    // 使用fetch下载文件
    const response = await fetch(url, {
      headers: {
        'Authorization': `Bearer ${userStore.token}`,
        'X-Auth-Token': userStore.token,
        'X-Account-Set-Id': currentAccountSetId || undefined
      },
      credentials: 'include'
    })

    if (!response.ok) {
      throw new Error('下载失败')
    }

    // 获取文件名
    const contentDisposition = response.headers.get('Content-Disposition')
    let filename = 'resume'
    if (contentDisposition) {
      const match = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/)
      if (match) {
        filename = match[1].replace(/['"]/g, '')
      }
    }

    // 从URL中提取文件名
    const urlParts = candidateForm.resume_url.split('/')
    const urlFilename = urlParts[urlParts.length - 1]
    if (urlFilename.includes('.')) {
      filename = decodeURIComponent(urlFilename)
    }

    // 创建blob并下载
    const blob = await response.blob()
    const downloadUrl = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = downloadUrl
    link.download = filename
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(downloadUrl)
  } catch (error) {
    console.error('下载失败:', error)
    ElMessage.error('下载失败')
  }
}

// 删除简历
const handleDeleteResume = async () => {
  if (!candidateForm.resume_url) return

  if (!candidateForm.id) {
    candidateForm.resume_url = ''
    uploadedResumeOriginalName.value = ''
    clearResumeUploadState()
    return
  }

  try {
    await ElMessageBox.confirm('确定要删除该简历吗？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })

    await deleteCandidateResume(candidateForm.id)
    candidateForm.resume_url = ''
    uploadedResumeOriginalName.value = ''
    clearResumeUploadState()
    ElMessage.success('简历删除成功')

    const localCandidate = candidates.value.find(c => c.id === candidateForm.id)
    if (localCandidate) {
      localCandidate.resume_url = null
    }

    // 刷新候选人列表
    const candidatesData = await getCandidates(currentRecruitment.value.id)
    candidates.value = candidatesData.data

    // 更新当前表单中的数据
    const updatedCandidate = candidates.value.find(c => c.id === candidateForm.id)
    if (updatedCandidate) {
      Object.assign(candidateForm, updatedCandidate)
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      ElMessage.error('删除失败')
    }
  }
}

const beforeResumeUpload = (file) => {
  const isLt10M = file.size / 1024 / 1024 < 10

  if (!isLt10M) {
    ElMessage.error('文件大小不能超过10MB!')
    return false
  }
  return true
}

// 格式化
const formatDateTime = (dateTime) => {
  if (!dateTime) return '-'
  return new Date(dateTime).toLocaleString('zh-CN')
}

const getStatusType = (status) => {
  const types = {
    pending: 'info',
    active: 'success',
    completed: '',
    paused: 'warning',
    cancelled: 'danger'
  }
  return types[status] || 'info'
}

const getStatusText = (status) => {
  const texts = {
    pending: '待分配',
    active: '进行中',
    completed: '已完成',
    paused: '已暂停',
    cancelled: '已取消'
  }
  return texts[status] || '未知'
}

const getCandidateStatusType = (status) => {
  const types = {
    pending: 'info',
    interviewing: 'warning',
    to_be_hired: 'primary',
    hired: 'success',
    rejected: 'danger'
  }
  return types[status] || 'info'
}

const getCandidateStatusText = (status) => {
  const texts = {
    pending: '待面试',
    interviewing: '面试中',
    to_be_hired: '待录用',
    hired: '已录用',
    rejected: '已拒绝'
  }
  return texts[status] || '未知'
}

const getGenderText = (gender) => {
  const texts = {
    male: '男',
    female: '女'
  }
  return texts[gender] || '-'
}

const getEducationText = (education) => {
  const texts = {
    none: '不限',
    high_school: '高中及以下',
    college: '中专/大专',
    bachelor: '本科',
    master: '硕士',
    doctor: '博士'
  }
  return texts[education] || education || '-'
}

// 获取权限信息
const loadPermissions = async () => {
  try {
    const response = await getRecruitmentPermissions({
      current_account_set_id: userStore.currentAccountSetId
    })
    
    if (response.success) {
      permissions.value = response.data
      console.log('招聘权限:', permissions.value)
    }
  } catch (error) {
    console.error('获取权限失败:', error)
    ElMessage.error('获取权限信息失败')
  }
}

onMounted(() => {
  loadPermissions()
  loadRecruitments()
  loadProjects()
  loadUsers()
})
</script>

<style scoped>
.recruitment-page {
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

/* 统计卡片 */
.statistics-row {
  margin-bottom: 20px;
}

.stat-card {
  cursor: pointer;
  transition: all 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.stat-content {
  display: flex;
  align-items: center;
  gap: 15px;
}

.stat-icon {
  width: 50px;
  height: 50px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: white;
}

.stat-card.total .stat-icon {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-card.active .stat-icon {
  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.stat-card.completed .stat-icon {
  background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stat-card.candidates .stat-icon {
  background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

.stat-info {
  flex: 1;
}

.stat-value {
  font-size: 28px;
  font-weight: bold;
  color: #303133;
  line-height: 1;
  margin-bottom: 5px;
}

.stat-label {
  font-size: 14px;
  color: #909399;
}

/* 搜索卡片 */
.search-card {
  margin-bottom: 20px;
}

/* 表格卡片 */
.table-card {
  margin-bottom: 20px;
}

/* 进度更新对话框样式 */
.progress-dialog .el-dialog__body {
  padding: 20px;
}

.dialog-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* 招聘信息卡片 */
.recruitment-info-card {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 12px;
  padding: 20px;
  color: white;
  box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
}

.info-header {
  display: flex;
  align-items: center;
  margin-bottom: 15px;
}

.info-icon {
  font-size: 20px;
  margin-right: 10px;
  background: rgba(255, 255, 255, 0.2);
  padding: 8px;
  border-radius: 8px;
}

.info-title {
  font-size: 18px;
  font-weight: 600;
}

.info-content {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.info-item {
  display: flex;
  align-items: center;
}

.info-item .label {
  font-size: 14px;
  opacity: 0.9;
  margin-right: 10px;
}

.info-item .value {
  font-size: 16px;
  font-weight: 600;
}

/* 进度统计卡片 */
.progress-stats-card {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
  border: 1px solid #f0f0f0;
}

.stats-header {
  display: flex;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid #f5f5f5;
}

.stats-icon {
  font-size: 20px;
  margin-right: 10px;
  color: #409eff;
  background: #f0f9ff;
  padding: 8px;
  border-radius: 8px;
}

.stats-title {
  font-size: 18px;
  font-weight: 600;
  color: #303133;
}

.stats-content {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.stat-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px;
  background: #fafafa;
  border-radius: 8px;
  transition: all 0.3s ease;
}

.stat-item:hover {
  background: #f5f5f5;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.stat-label {
  display: flex;
  flex-direction: column;
  flex: 1;
}

.label-text {
  font-size: 16px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 4px;
}

.label-desc {
  font-size: 13px;
  color: #909399;
}

.stat-input {
  margin-left: 20px;
}

.stat-input .el-input-number {
  width: 140px;
}

.stat-input .el-input-number .el-input__inner {
  text-align: center;
  font-size: 16px;
  font-weight: 600;
}

/* 进度说明卡片 */
.progress-notes-card {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
  border: 1px solid #f0f0f0;
}

.notes-header {
  display: flex;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid #f5f5f5;
}

.notes-icon {
  font-size: 20px;
  margin-right: 10px;
  color: #67c23a;
  background: #f0f9ff;
  padding: 8px;
  border-radius: 8px;
}

.notes-title {
  font-size: 18px;
  font-weight: 600;
  color: #303133;
}

.notes-content .el-textarea__inner {
  border-radius: 8px;
  border: 2px solid #e4e7ed;
  transition: all 0.3s ease;
}

.notes-content .el-textarea__inner:focus {
  border-color: #409eff;
  box-shadow: 0 0 0 2px rgba(64, 158, 255, 0.2);
}

/* 对话框底部 */
.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 20px 0;
}

.dialog-footer .el-button {
  min-width: 100px;
  border-radius: 8px;
}

/* 候选人管理对话框样式 */
.candidates-dialog .el-dialog__body {
  padding: 20px;
}

.candidates-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid #f5f5f5;
}

.candidates-header h3 {
  margin: 0;
  color: #303133;
  font-size: 18px;
  font-weight: 600;
}

/* 候选人表格样式优化 */
.candidates-table .el-table__header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.candidates-table .el-table__header th {
  background: transparent !important;
  color: white !important;
  font-weight: 600;
  border: none;
}

.candidates-table .el-table__row:hover {
  background: #f8f9ff;
}

.candidates-table .el-table__row td {
  border-bottom: 1px solid #f0f0f0;
}

/* 候选人状态标签 */
.candidate-status-tag {
  border-radius: 12px;
  padding: 4px 12px;
  font-size: 12px;
  font-weight: 500;
}

.candidate-status-tag.pending {
  background: #fff7e6;
  color: #d46b08;
  border: 1px solid #ffd591;
}

.candidate-status-tag.interviewing {
  background: #e6f7ff;
  color: #1890ff;
  border: 1px solid #91d5ff;
}

.candidate-status-tag.to_be_hired {
  background: #f6ffed;
  color: #52c41a;
  border: 1px solid #b7eb8f;
}

.candidate-status-tag.hired {
  background: #f6ffed;
  color: #389e0d;
  border: 1px solid #95de64;
}

.candidate-status-tag.rejected {
  background: #fff2f0;
  color: #cf1322;
  border: 1px solid #ffccc7;
}

/* 候选人表单对话框样式 */
.candidate-form-dialog .el-dialog__body {
  padding: 30px;
}

.candidate-form-dialog .el-form-item {
  margin-bottom: 20px;
}

.candidate-form-dialog .el-input,
.candidate-form-dialog .el-select,
.candidate-form-dialog .el-input-number {
  border-radius: 8px;
}

.candidate-form-dialog .el-input__inner,
.candidate-form-dialog .el-select .el-input__inner {
  border: 2px solid #e4e7ed;
  transition: all 0.3s ease;
}

.candidate-form-dialog .el-input__inner:focus,
.candidate-form-dialog .el-select .el-input__inner:focus {
  border-color: #409eff;
  box-shadow: 0 0 0 2px rgba(64, 158, 255, 0.2);
}

.candidate-form-dialog .el-textarea__inner {
  border-radius: 8px;
  border: 2px solid #e4e7ed;
  transition: all 0.3s ease;
}

.candidate-form-dialog .el-textarea__inner:focus {
  border-color: #409eff;
  box-shadow: 0 0 0 2px rgba(64, 158, 255, 0.2);
}

/* 上传组件样式 */
.uploaded-file {
  margin-top: 10px;
  padding: 8px 12px;
  background: #f0f9ff;
  border: 1px solid #b3d8ff;
  border-radius: 6px;
  color: #409eff;
  font-size: 13px;
}

/* 简历文件信息样式 */
.resume-file-info {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 10px;
  padding: 8px 12px;
  background: #f0f9ff;
  border: 1px solid #b3d8ff;
  border-radius: 6px;
}

.resume-file-label {
  color: #606266;
  font-size: 14px;
}

.resume-file-name {
  color: #409eff;
  font-size: 14px;
  text-decoration: underline;
  cursor: pointer;
}

.resume-file-name:hover {
  color: #66b1ff;
}

/* 表单按钮样式 */
.candidate-form-dialog .dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 20px 0;
}

.candidate-form-dialog .dialog-footer .el-button {
  min-width: 100px;
  border-radius: 8px;
  font-weight: 500;
}

/* 进度信息 */
.progress-info {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.progress-item {
  display: flex;
  align-items: center;
  gap: 5px;
}

.progress-item .label {
  font-size: 12px;
  color: #909399;
}

/* 展开内容 */
.expand-content {
  padding: 20px;
  background-color: #f5f7fa;
}

/* 分页 */
.pagination {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

/* 候选人管理 */
.candidates-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.candidates-header h3 {
  margin: 0;
  font-size: 18px;
  color: #303133;
}

.uploaded-file {
  margin-top: 10px;
  color: #67c23a;
  font-size: 14px;
}

:deep(.el-table) {
  font-size: 14px;
}

:deep(.el-form-item) {
  margin-bottom: 20px;
}

:deep(.el-divider__text) {
  font-weight: bold;
  color: #409eff;
}
</style>
