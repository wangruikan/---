<template>
  <div class="region-portal-container">
    <!-- 头部操作区 -->
    <el-card class="header-card">
      <el-row :gutter="20">
        <el-col :span="18">
          <el-form :model="filterForm" inline>
            <el-form-item label="地区名称">
              <el-input
                v-model="filterForm.region_name"
                placeholder="请输入地区名称"
                clearable
                style="width: 200px;"
              />
            </el-form-item>
            <el-form-item label="业务类型">
              <el-input
                v-model="filterForm.business_type"
                placeholder="请输入业务类型"
                clearable
                style="width: 200px;"
              />
            </el-form-item>
            <el-form-item label="状态">
              <el-select v-model="filterForm.is_active" placeholder="全部" clearable style="width: 120px;">
                <el-option label="启用" :value="true" />
                <el-option label="禁用" :value="false" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
              <el-button :icon="Refresh" @click="handleReset">重置</el-button>
            </el-form-item>
          </el-form>
        </el-col>
        <el-col :span="6" style="text-align: right;">
          <el-button type="primary" :icon="Plus" @click="handleCreate">添加网页入口</el-button>
        </el-col>
      </el-row>
    </el-card>

    <!-- 列表区 -->
    <el-card class="table-card">
      <el-table :data="portalList" v-loading="loading" border stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column prop="region_name" label="地区名称" width="120" />
        <el-table-column prop="business_type" label="业务类型" width="120" />
        <el-table-column prop="portal_name" label="网站名称" min-width="180" />
        <el-table-column label="网站地址" min-width="300">
          <template #default="{ row }">
            <el-link :href="row.portal_url" target="_blank" type="primary">
              {{ row.portal_url }}
            </el-link>
          </template>
        </el-table-column>
        <el-table-column prop="sort_order" label="排序" width="80" align="center" />
        <el-table-column label="状态" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.is_active ? 'success' : 'info'">
              {{ row.is_active ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="创建人" width="100">
          <template #default="{ row }">
            {{ row.creator?.name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="240" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" :icon="Edit" @click="handleEdit(row)">编辑</el-button>
            <el-button 
              link 
              :type="row.is_active ? 'warning' : 'success'" 
              @click="handleToggleStatus(row)"
            >
              {{ row.is_active ? '禁用' : '启用' }}
            </el-button>
            <el-button link type="danger" :icon="Delete" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-container">
        <el-pagination
          v-model:current-page="pagination.current"
          v-model:page-size="pagination.pageSize"
          :total="pagination.total"
          :page-sizes="[15, 30, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          @current-change="loadPortalList"
          @size-change="loadPortalList"
        />
      </div>
    </el-card>

    <!-- 新增/编辑对话框 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="600px"
      :close-on-click-modal="false"
    >
      <el-form
        ref="formRef"
        :model="formData"
        :rules="formRules"
        label-width="100px"
      >
        <el-form-item label="地区名称" prop="region_name">
          <el-input
            v-model="formData.region_name"
            placeholder="请输入地区名称，如：北京、上海"
            maxlength="100"
            show-word-limit
          />
        </el-form-item>
        <el-form-item label="业务类型" prop="business_type">
          <el-input
            v-model="formData.business_type"
            placeholder="请输入业务类型，如：社保、公积金、税务"
            maxlength="100"
            show-word-limit
          />
        </el-form-item>
        <el-form-item label="网站名称" prop="portal_name">
          <el-input
            v-model="formData.portal_name"
            placeholder="请输入网站名称"
            maxlength="200"
            show-word-limit
          />
        </el-form-item>
        <el-form-item label="网站地址" prop="portal_url">
          <el-input
            v-model="formData.portal_url"
            placeholder="请输入完整的网站地址，如：https://www.example.com"
            maxlength="500"
            show-word-limit
          />
        </el-form-item>
        <el-form-item label="排序序号" prop="sort_order">
          <el-input-number
            v-model="formData.sort_order"
            :min="0"
            :step="1"
            controls-position="right"
            placeholder="数字越小越靠前"
          />
        </el-form-item>
        <el-form-item label="备注说明" prop="remarks">
          <el-input
            v-model="formData.remarks"
            type="textarea"
            :rows="3"
            placeholder="请输入备注说明（可选）"
            maxlength="500"
            show-word-limit
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { 
  Search, Refresh, Plus, Edit, Delete 
} from '@element-plus/icons-vue'
import { useAccountSetStore } from '@/stores/accountSet'
import {
  getRegionPortals,
  createRegionPortal,
  updateRegionPortal,
  deleteRegionPortal,
  togglePortalStatus
} from '@/api/regionPortal'

const accountSetStore = useAccountSetStore()

// 筛选表单
const filterForm = reactive({
  region_name: '',
  business_type: '',
  is_active: null
})

// 列表数据
const loading = ref(false)
const portalList = ref([])
const pagination = reactive({
  current: 1,
  pageSize: 15,
  total: 0
})

// 对话框
const dialogVisible = ref(false)
const dialogTitle = ref('')
const formRef = ref(null)
const submitting = ref(false)
const editingId = ref(null)

// 表单数据
const formData = reactive({
  region_name: '',
  business_type: '',
  portal_name: '',
  portal_url: '',
  sort_order: 0,
  remarks: ''
})

// 表单验证规则
const formRules = {
  region_name: [
    { required: true, message: '请输入地区名称', trigger: 'blur' }
  ],
  business_type: [
    { required: true, message: '请输入业务类型', trigger: 'blur' }
  ],
  portal_name: [
    { required: true, message: '请输入网站名称', trigger: 'blur' }
  ],
  portal_url: [
    { required: true, message: '请输入网站地址', trigger: 'blur' },
    { type: 'url', message: '请输入正确的网址格式', trigger: 'blur' }
  ]
}

// 加载列表
const loadPortalList = async () => {
  loading.value = true
  try {
    const res = await getRegionPortals({
      current_account_set_id: accountSetStore.currentAccountSetId,
      ...filterForm,
      page: pagination.current,
      per_page: pagination.pageSize
    })
    
    if (res.success) {
      portalList.value = res.data.data
      pagination.total = res.data.total
    } else {
      ElMessage.error(res.message || '获取列表失败')
    }
  } catch (error) {
    console.error('Load portal list error:', error)
    ElMessage.error('获取列表失败')
  } finally {
    loading.value = false
  }
}

// 查询
const handleSearch = () => {
  pagination.current = 1
  loadPortalList()
}

// 重置
const handleReset = () => {
  filterForm.region_name = ''
  filterForm.business_type = ''
  filterForm.is_active = null
  pagination.current = 1
  loadPortalList()
}

// 新增
const handleCreate = () => {
  dialogTitle.value = '添加网页入口'
  editingId.value = null
  resetForm()
  dialogVisible.value = true
}

// 编辑
const handleEdit = (row) => {
  dialogTitle.value = '编辑网页入口'
  editingId.value = row.id
  Object.assign(formData, {
    region_name: row.region_name,
    business_type: row.business_type,
    portal_name: row.portal_name,
    portal_url: row.portal_url,
    sort_order: row.sort_order,
    remarks: row.remarks || ''
  })
  dialogVisible.value = true
}

// 提交
const handleSubmit = async () => {
  if (!formRef.value) return
  
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    
    submitting.value = true
    try {
      const data = {
        ...formData,
        current_account_set_id: accountSetStore.currentAccountSetId
      }
      
      let res
      if (editingId.value) {
        res = await updateRegionPortal(editingId.value, data)
      } else {
        res = await createRegionPortal(data)
      }
      
      if (res.success) {
        ElMessage.success(res.message || '操作成功')
        dialogVisible.value = false
        loadPortalList()
      } else {
        ElMessage.error(res.message || '操作失败')
      }
    } catch (error) {
      console.error('Submit error:', error)
      ElMessage.error('操作失败')
    } finally {
      submitting.value = false
    }
  })
}

// 切换状态
const handleToggleStatus = async (row) => {
  try {
    const res = await togglePortalStatus(row.id)
    if (res.success) {
      ElMessage.success(res.message)
      loadPortalList()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (error) {
    console.error('Toggle status error:', error)
    ElMessage.error('操作失败')
  }
}

// 删除
const handleDelete = (row) => {
  ElMessageBox.confirm(
    `确定要删除"${row.portal_name}"吗？`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    }
  ).then(async () => {
    try {
      const res = await deleteRegionPortal(row.id)
      if (res.success) {
        ElMessage.success('删除成功')
        loadPortalList()
      } else {
        ElMessage.error(res.message || '删除失败')
      }
    } catch (error) {
      console.error('Delete error:', error)
      ElMessage.error('删除失败')
    }
  }).catch(() => {
    // 取消删除
  })
}

// 重置表单
const resetForm = () => {
  if (formRef.value) {
    formRef.value.resetFields()
  }
  Object.assign(formData, {
    region_name: '',
    business_type: '',
    portal_name: '',
    portal_url: '',
    sort_order: 0,
    remarks: ''
  })
}

// 初始化
onMounted(() => {
  loadPortalList()
})
</script>

<style scoped>
.region-portal-container {
  padding: 20px;
}

.header-card {
  margin-bottom: 20px;
}

.table-card {
  margin-top: 20px;
}

.pagination-container {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
</style>

