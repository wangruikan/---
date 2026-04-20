<template>
  <div class="recruitment-demand-page">
    <div class="page-header">
      <h1>招聘需求</h1>
      <el-button 
        type="primary" 
        @click="goToCreate"
        v-if="userStore.userInfo?.approval_level !== 1"
      >
        <el-icon><Plus /></el-icon>
        新增招聘需求
      </el-button>
    </div>

    <!-- 筛选条件 -->
    <el-card class="filter-card">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="职位名称">
          <el-input
            v-model="searchForm.position"
            placeholder="请输入职位名称"
            clearable
            style="width: 200px"
          />
        </el-form-item>
        
        <el-form-item label="项目名称">
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

    <!-- 招聘需求列表 -->
    <el-card class="table-card">
      <el-table
        :data="demands"
        v-loading="loading"
        stripe
        border
      >
        <el-table-column type="index" label="序号" width="60" />
        <el-table-column prop="position" label="职位名称" width="150" />
        <el-table-column prop="project_name" label="项目名称" width="150" />
        <el-table-column prop="department" label="所属部门" width="120" />
        <el-table-column prop="required_count" label="需求人数" width="100" align="center">
          <template #default="{ row }">
            <el-tag type="primary">{{ row.required_count }}人</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="salary_range" label="薪资范围" width="150" />
        <el-table-column prop="work_location" label="工作地点" width="120" />
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)">
              {{ getStatusText(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="start_date" label="开始日期" width="120" />
        <el-table-column label="创建时间" width="160">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" size="small" @click="handleView(row)">
              查看详情
            </el-button>
            <el-button 
              v-if="['pending', 'active'].includes(row.status) && userStore.userInfo?.approval_level !== 1"
              type="warning" 
              size="small" 
              @click="handleEdit(row)"
            >
              编辑
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

    <!-- 查看详情对话框 -->
    <el-dialog
      v-model="showDetailDialog"
      title="招聘需求详情"
      width="800px"
    >
      <el-descriptions :column="2" border v-if="currentDemand">
        <el-descriptions-item label="职位名称">{{ currentDemand.position }}</el-descriptions-item>
        <el-descriptions-item label="项目名称">{{ currentDemand.project_name }}</el-descriptions-item>
        <el-descriptions-item label="所属部门">{{ currentDemand.department }}</el-descriptions-item>
        <el-descriptions-item label="需求人数">{{ currentDemand.required_count }}人</el-descriptions-item>
        <el-descriptions-item label="薪资范围">{{ currentDemand.salary_range || '-' }}</el-descriptions-item>
        <el-descriptions-item label="工作地点">{{ currentDemand.work_location || '-' }}</el-descriptions-item>
        <el-descriptions-item label="学历要求">{{ currentDemand.education_text || '-' }}</el-descriptions-item>
        <el-descriptions-item label="工作经验">{{ currentDemand.experience || '-' }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="getStatusType(currentDemand.status)">
            {{ getStatusText(currentDemand.status) }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="开始日期">{{ currentDemand.start_date || '-' }}</el-descriptions-item>
        <el-descriptions-item label="期望完成日期">{{ currentDemand.end_date || '-' }}</el-descriptions-item>
        <el-descriptions-item label="创建时间">{{ formatDateTime(currentDemand.created_at) }}</el-descriptions-item>
        <el-descriptions-item label="职位描述" :span="2">
          {{ currentDemand.description || '-' }}
        </el-descriptions-item>
        <el-descriptions-item label="任职要求" :span="2">
          {{ currentDemand.requirements || '-' }}
        </el-descriptions-item>
        <el-descriptions-item label="备注" :span="2">
          {{ currentDemand.notes || '-' }}
        </el-descriptions-item>
      </el-descriptions>
      
      <template #footer>
        <el-button @click="showDetailDialog = false">关闭</el-button>
      </template>
    </el-dialog>

    <!-- 编辑对话框 -->
    <el-dialog
      v-model="showEditDialog"
      title="编辑招聘需求"
      width="900px"
      @close="handleEditDialogClose"
    >
      <el-form
        ref="editFormRef"
        :model="editForm"
        :rules="formRules"
        label-width="120px"
      >
        <el-divider content-position="left">基本信息</el-divider>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="职位名称" prop="position">
              <el-input v-model="editForm.position" placeholder="请输入职位名称" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="所属项目" prop="project_id">
              <el-select v-model="editForm.project_id" placeholder="请选择项目" style="width: 100%">
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
              <el-input v-model="editForm.department" placeholder="请输入部门" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="招聘人数" prop="required_count">
              <el-input-number
                v-model="editForm.required_count"
                :min="1"
                :max="999"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-divider content-position="left">薪资与福利</el-divider>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="薪资范围" prop="salary_range">
              <el-input v-model="editForm.salary_range" placeholder="如：8000-12000元/月" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="工作地点" prop="work_location">
              <el-input v-model="editForm.work_location" placeholder="请输入工作地点" />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-divider content-position="left">任职要求</el-divider>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="学历要求" prop="education">
              <el-select v-model="editForm.education" placeholder="请选择学历要求" style="width: 100%">
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
              <el-input v-model="editForm.experience" placeholder="如：3-5年" />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-form-item label="职位描述" prop="description">
          <el-input
            v-model="editForm.description"
            type="textarea"
            :rows="4"
            placeholder="请详细描述职位职责和工作内容"
          />
        </el-form-item>
        
        <el-form-item label="任职要求" prop="requirements">
          <el-input
            v-model="editForm.requirements"
            type="textarea"
            :rows="4"
            placeholder="请详细列出任职要求"
          />
        </el-form-item>
        
        <el-divider content-position="left">招聘时间</el-divider>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="开始日期" prop="start_date">
              <el-date-picker
                v-model="editForm.start_date"
                type="date"
                placeholder="请选择开始日期"
                style="width: 100%"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="期望完成日期" prop="end_date">
              <el-date-picker
                v-model="editForm.end_date"
                type="date"
                placeholder="请选择期望完成日期"
                style="width: 100%"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
              />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-form-item label="备注">
          <el-input
            v-model="editForm.notes"
            type="textarea"
            :rows="2"
            placeholder="其他补充说明（可选）"
          />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showEditDialog = false">取消</el-button>
        <el-button type="primary" @click="handleSaveEdit" :loading="submitting">
          保存修改
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAccountSetStore } from '@/stores/accountSet'
import { useUserStore } from '@/stores/user'
import { ElMessage } from 'element-plus'
import { Plus, Search, Refresh } from '@element-plus/icons-vue'
import request from '@/api/request'
import dayjs from 'dayjs'

const router = useRouter()
const accountSetStore = useAccountSetStore()
const userStore = useUserStore()

const loading = ref(false)
const demands = ref([])
const projects = ref([])
const showDetailDialog = ref(false)
const showEditDialog = ref(false)
const currentDemand = ref(null)
const editFormRef = ref(null)
const submitting = ref(false)

const editForm = reactive({
  id: null,
  position: '',
  project_id: null,
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

const formRules = {
  position: [
    { required: true, message: '请输入职位名称', trigger: 'blur' }
  ],
  project_id: [
    { required: true, message: '请选择项目', trigger: 'change' }
  ],
  department: [
    { required: true, message: '请输入部门', trigger: 'blur' }
  ],
  required_count: [
    { required: true, message: '请输入招聘人数', trigger: 'blur' }
  ],
  requirements: [
    { required: true, message: '请填写任职要求', trigger: 'blur' }
  ],
  start_date: [
    { required: true, message: '请选择开始日期', trigger: 'change' }
  ]
}

const searchForm = reactive({
  position: '',
  project_id: null,
  status: ''
})

const pagination = reactive({
  currentPage: 1,
  pageSize: 15,
  total: 0
})

const goToCreate = () => {
  router.push('/recruitment-demand/create')
}

const handleView = (row) => {
  currentDemand.value = row
  showDetailDialog.value = true
}

const handleEdit = (row) => {
  // 填充编辑表单
  Object.assign(editForm, {
    id: row.id,
    position: row.position,
    project_id: row.project_id,
    department: row.department,
    required_count: row.required_count,
    salary_range: row.salary_range,
    work_location: row.work_location,
    education: row.education,
    experience: row.experience,
    description: row.description,
    requirements: row.requirements,
    start_date: row.start_date,
    end_date: row.end_date,
    notes: row.notes
  })
  showEditDialog.value = true
}

const handleEditDialogClose = () => {
  editFormRef.value?.resetFields()
}

const handleSaveEdit = async () => {
  try {
    await editFormRef.value.validate()
    
    submitting.value = true
    
    const response = await request({
      url: `/recruitment/${editForm.id}`,
      method: 'put',
      data: {
        ...editForm,
        current_account_set_id: accountSetStore.currentAccountSetId
      }
    })
    
    if (response.success) {
      ElMessage.success('修改成功！')
      showEditDialog.value = false
      loadDemands()
    } else {
      ElMessage.error(response.message || '修改失败')
    }
  } catch (error) {
    if (error !== false) {
      console.error('Update recruitment error:', error)
      ElMessage.error('修改失败，请稍后重试')
    }
  } finally {
    submitting.value = false
  }
}

const handleSearch = () => {
  pagination.currentPage = 1
  loadDemands()
}

const handleReset = () => {
  searchForm.position = ''
  searchForm.project_id = null
  searchForm.status = ''
  pagination.currentPage = 1
  loadDemands()
}

const handleSizeChange = (size) => {
  pagination.pageSize = size
  loadDemands()
}

const handleCurrentChange = (page) => {
  pagination.currentPage = page
  loadDemands()
}

const loadDemands = async () => {
  try {
    loading.value = true
    
    // 构建请求参数，过滤空值
    const params = {
      current_account_set_id: accountSetStore.currentAccountSetId,
      page: pagination.currentPage,
      per_page: pagination.pageSize
    }
    
    // 只添加有值的搜索条件
    if (searchForm.position) {
      params.position = searchForm.position
    }
    if (searchForm.project_id) {
      params.project_id = searchForm.project_id
    }
    if (searchForm.status) {
      params.status = searchForm.status
    }
    
    const response = await request({
      url: '/recruitment',
      method: 'get',
      params
    })
    
    console.log('招聘需求-API响应:', response)
    
    if (response.success) {
      // 后端返回格式：{ success: true, data: [...], total: 4 }
      if (Array.isArray(response.data)) {
        // data 直接是数组
        demands.value = response.data
        pagination.total = response.total || response.data.length
      } else if (response.data.data) {
        // 嵌套的分页数据格式
        demands.value = response.data.data || []
        pagination.total = response.data.total || 0
      } else {
        demands.value = []
        pagination.total = 0
      }
      
      console.log('已加载数据条数:', demands.value.length)
    }
  } catch (error) {
    console.error('Load demands error:', error)
    ElMessage.error('加载数据失败')
  } finally {
    loading.value = false
  }
}

const loadProjects = async () => {
  try {
    const response = await request({
      url: '/projects',
      method: 'get',
      params: {
        current_account_set_id: accountSetStore.currentAccountSetId,
        per_page: 1000
      }
    })
    
    if (response.success) {
      projects.value = response.data.data || response.data || []
    }
  } catch (error) {
    console.error('Load projects error:', error)
  }
}

const getStatusType = (status) => {
  const typeMap = {
    pending: 'info',
    active: 'warning',
    completed: 'success',
    paused: '',
    cancelled: 'danger'
  }
  return typeMap[status] || 'info'
}

const getStatusText = (status) => {
  const textMap = {
    pending: '待分配',
    active: '进行中',
    completed: '已完成',
    paused: '已暂停',
    cancelled: '已取消'
  }
  return textMap[status] || status
}

const formatDateTime = (datetime) => {
  return datetime ? dayjs(datetime).format('YYYY-MM-DD HH:mm:ss') : '-'
}

onMounted(() => {
  loadDemands()
  loadProjects()
})
</script>

<style scoped>
.recruitment-demand-page {
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.page-header h1 {
  margin: 0;
  font-size: 24px;
  font-weight: 500;
}

.filter-card {
  margin-bottom: 20px;
}

.table-card {
  margin-bottom: 20px;
}

.pagination {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
</style>

