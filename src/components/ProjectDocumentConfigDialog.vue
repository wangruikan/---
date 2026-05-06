<template>
  <el-dialog
    v-model="visible"
    title="项目资料配置"
    width="800px"
    :close-on-click-modal="false"
    @close="handleClose"
  >
    <div class="document-config-container">
      <el-alert type="info" :closable="false" style="margin-bottom: 20px;">
        <template #title>
          配置员工需要在小程序中上传的资料类型，员工将在小程序中看到这些资料并进行上传
        </template>
      </el-alert>

      <div class="toolbar">
        <el-button type="primary" @click="handleAdd">
          <el-icon><Plus /></el-icon>
          添加资料项
        </el-button>
      </div>

      <el-table
        :data="configs"
        v-loading="loading"
        border
        row-key="id"
      >
        <el-table-column prop="sort_order" label="排序" width="80" align="center" />
        <el-table-column prop="document_name" label="资料名称" min-width="150" />
        <el-table-column label="是否必填" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.is_required ? 'danger' : 'info'" size="small">
              {{ row.is_required ? '必填' : '选填' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="160" align="center">
          <template #default="{ row, $index }">
            <el-button type="text" size="small" @click="handleEdit(row)">
              编辑
            </el-button>
            <el-button type="text" size="small" @click="handleMoveUp($index)" :disabled="$index === 0">
              上移
            </el-button>
            <el-button type="text" size="small" @click="handleMoveDown($index)" :disabled="$index === configs.length - 1">
              下移
            </el-button>
            <el-button type="text" size="small" @click="handleDelete(row)" style="color: #f56c6c;">
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <template #footer>
      <el-button @click="visible = false">关闭</el-button>
    </template>
  </el-dialog>

  <!-- 添加/编辑资料配置对话框 -->
  <el-dialog
    v-model="showFormDialog"
    :title="formMode === 'add' ? '添加资料项' : '编辑资料项'"
    width="500px"
    :close-on-click-modal="false"
  >
    <el-form
      ref="formRef"
      :model="form"
      :rules="formRules"
      label-width="100px"
    >
      <el-form-item label="资料名称" prop="document_name">
        <el-input
          v-model="form.document_name"
          placeholder="例如：身份证照片、驾驶证等"
          maxlength="100"
          show-word-limit
        />
      </el-form-item>


      <el-form-item label="是否必填" prop="is_required">
        <el-switch
          v-model="form.is_required"
          active-text="必填"
          inactive-text="选填"
        />
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="showFormDialog = false">取消</el-button>
      <el-button type="primary" @click="handleSubmitForm" :loading="submitting">
        确定
      </el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, reactive, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
  getProjectDocumentConfigs,
  createProjectDocumentConfig,
  updateProjectDocumentConfig,
  deleteProjectDocumentConfig,
  updateDocumentConfigsSort
} from '@/api/projectDocuments'

const props = defineProps({
  modelValue: Boolean,
  projectId: Number
})

const emit = defineEmits(['update:modelValue'])

const visible = ref(false)
const loading = ref(false)
const submitting = ref(false)
const configs = ref([])

const showFormDialog = ref(false)
const formMode = ref('add') // 'add' or 'edit'
const formRef = ref()

const form = reactive({
  id: null,
  document_name: '',
  is_required: true
})

const formRules = {
  document_name: [
    { required: true, message: '请输入资料名称', trigger: 'blur' }
  ]
}

watch(() => props.modelValue, (val) => {
  visible.value = val
  if (val && props.projectId) {
    loadConfigs()
  }
})

watch(visible, (val) => {
  emit('update:modelValue', val)
})

const loadConfigs = async () => {
  loading.value = true
  try {
    const response = await getProjectDocumentConfigs(props.projectId)
    if (response.success) {
      configs.value = response.data || []
    }
  } catch (error) {
    console.error('加载资料配置失败:', error)
    ElMessage.error('加载资料配置失败')
  } finally {
    loading.value = false
  }
}

const handleAdd = () => {
  formMode.value = 'add'
  form.id = null
  form.document_name = ''
  form.is_required = true
  showFormDialog.value = true
}

const handleEdit = (row) => {
  formMode.value = 'edit'
  form.id = row.id
  form.document_name = row.document_name
  form.is_required = row.is_required
  showFormDialog.value = true
}

const handleSubmitForm = async () => {
  if (!formRef.value) return

  await formRef.value.validate(async (valid) => {
    if (!valid) return

    submitting.value = true
    try {
      if (formMode.value === 'add') {
        await createProjectDocumentConfig(props.projectId, {
          document_name: form.document_name,
          is_required: form.is_required
        })
        ElMessage.success('添加成功')
      } else {
        await updateProjectDocumentConfig(props.projectId, form.id, {
          document_name: form.document_name,
          is_required: form.is_required
        })
        ElMessage.success('更新成功')
      }

      showFormDialog.value = false
      loadConfigs()
    } catch (error) {
      console.error('操作失败:', error)
      ElMessage.error(error.response?.data?.message || '操作失败')
    } finally {
      submitting.value = false
    }
  })
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm('确定要删除这个资料配置吗？', '确认删除', {
      type: 'warning'
    })

    await deleteProjectDocumentConfig(props.projectId, row.id)
    ElMessage.success('删除成功')
    loadConfigs()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }
}

const handleMoveUp = async (index) => {
  if (index === 0) return

  const temp = configs.value[index]
  configs.value[index] = configs.value[index - 1]
  configs.value[index - 1] = temp

  await updateSort()
}

const handleMoveDown = async (index) => {
  if (index === configs.value.length - 1) return

  const temp = configs.value[index]
  configs.value[index] = configs.value[index + 1]
  configs.value[index + 1] = temp

  await updateSort()
}

const updateSort = async () => {
  try {
    const updatedConfigs = configs.value.map((config, index) => ({
      id: config.id,
      sort_order: index + 1
    }))

    await updateDocumentConfigsSort(props.projectId, updatedConfigs)
  } catch (error) {
    console.error('更新排序失败:', error)
    ElMessage.error('更新排序失败')
    loadConfigs() // 重新加载
  }
}

const handleClose = () => {
  configs.value = []
}
</script>

<style scoped>
.document-config-container {
  min-height: 400px;
}

.toolbar {
  margin-bottom: 15px;
  display: flex;
  justify-content: flex-start;
}
</style>

