<template>
  <div class="recruitment-create-page">
    <div class="page-header">
      <el-page-header @back="goBack" title="返回招聘需求">
        <template #content>
          <h1>新增招聘需求</h1>
        </template>
      </el-page-header>
    </div>

    <el-card class="form-card">
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
              <el-input v-model="form.position" placeholder="请输入职位名称" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="所属项目" prop="project_id">
              <el-select v-model="form.project_id" placeholder="请选择项目" style="width: 100%">
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
              <el-input v-model="form.department" placeholder="请输入部门" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="招聘人数" prop="required_count">
              <el-input-number
                v-model="form.required_count"
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
              <el-input v-model="form.salary_range" placeholder="如：8000-12000元/月" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="工作地点" prop="work_location">
              <el-input v-model="form.work_location" placeholder="请输入工作地点" />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-divider content-position="left">任职要求</el-divider>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="学历要求" prop="education">
              <el-select v-model="form.education" placeholder="请选择学历要求" style="width: 100%">
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
              <el-input v-model="form.experience" placeholder="如：3-5年" />
            </el-form-item>
          </el-col>
        </el-row>
        
        <el-form-item label="职位描述" prop="description">
          <el-input
            v-model="form.description"
            type="textarea"
            :rows="4"
            placeholder="请详细描述职位职责和工作内容"
          />
        </el-form-item>
        
        <el-form-item label="任职要求" prop="requirements">
          <el-input
            v-model="form.requirements"
            type="textarea"
            :rows="4"
            placeholder="请详细列出任职要求（如：专业技能、工作经验、个人素质等）"
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
          />
        </el-form-item>
        
        <el-form-item>
          <el-button type="primary" @click="handleSubmit" :loading="submitting" size="large">
            <el-icon><Check /></el-icon>
            创建招聘需求
          </el-button>
          <el-button @click="goBack" size="large">
            <el-icon><Close /></el-icon>
            取消
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAccountSetStore } from '@/stores/accountSet'
import { ElMessage } from 'element-plus'
import { Check, Close } from '@element-plus/icons-vue'
import request from '@/api/request'

const router = useRouter()
const accountSetStore = useAccountSetStore()

const formRef = ref(null)
const submitting = ref(false)
const projects = ref([])

const form = reactive({
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

const goBack = () => {
  router.push('/recruitment-demand')
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

const handleSubmit = async () => {
  try {
    await formRef.value.validate()
    
    submitting.value = true
    
    const response = await request({
      url: '/recruitment',
      method: 'post',
      data: {
        ...form,
        current_account_set_id: accountSetStore.currentAccountSetId
      }
    })
    
    if (response.success) {
      ElMessage.success('招聘需求创建成功！')
      router.push('/recruitment-demand')
    } else {
      ElMessage.error(response.message || '创建失败')
    }
  } catch (error) {
    if (error !== false) {
      console.error('Create recruitment error:', error)
      ElMessage.error('创建失败，请稍后重试')
    }
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadProjects()
})
</script>

<style scoped>
.recruitment-create-page {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h1 {
  margin: 0;
  font-size: 20px;
  font-weight: 500;
}

.form-card {
  max-width: 1200px;
  margin: 0 auto;
}

:deep(.el-divider__text) {
  font-size: 14px;
  font-weight: 500;
  color: #303133;
}

:deep(.el-form-item) {
  margin-bottom: 22px;
}

:deep(.el-form-item__label) {
  font-weight: 500;
}

:deep(.el-textarea__inner) {
  font-family: inherit;
}

.el-button + .el-button {
  margin-left: 12px;
}
</style>

