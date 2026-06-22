<template>
  <el-dialog
    v-model="visible"
    title="项目资料配置"
    width="900px"
    :close-on-click-modal="false"
    @close="handleClose"
  >
    <div class="document-config-container">
      <el-alert type="info" :closable="false" style="margin-bottom: 20px;">
        <template #title>
          一个项目可以配置多套资料方案。创建人员时选择对应方案后，PC端和小程序都只会显示该方案下的资料项。
        </template>
      </el-alert>

      <div class="set-toolbar">
        <div class="set-toolbar-left">
          <el-select
            v-model="currentSetId"
            placeholder="请选择资料方案"
            style="width: 260px"
            :disabled="loading || documentSets.length === 0"
          >
            <el-option
              v-for="set in documentSets"
              :key="set.id"
              :label="set.is_default ? `${set.set_name}（默认）` : set.set_name"
              :value="set.id"
            />
          </el-select>
          <el-tag v-if="currentSet?.is_default" type="success">默认方案</el-tag>
        </div>

        <div class="set-toolbar-right">
          <el-button type="primary" @click="handleAddSet">新增方案</el-button>
          <el-button :disabled="!currentSet" @click="handleEditSet">编辑方案</el-button>
          <el-button :disabled="!currentSet" @click="handleDeleteSet">删除方案</el-button>
        </div>
      </div>

      <div v-if="currentSet" class="config-panel">
        <div class="toolbar">
          <div class="toolbar-title">
            当前方案：{{ currentSet.set_name }}
          </div>
          <el-button type="primary" @click="handleAdd">
            <el-icon><Plus /></el-icon>
            添加资料项
          </el-button>
        </div>

        <el-table
          :data="currentConfigs"
          v-loading="loading"
          border
          row-key="id"
        >
          <el-table-column prop="sort_order" label="排序" width="80" align="center" />
          <el-table-column prop="document_name" label="资料名称" min-width="180" />
          <el-table-column prop="document_type_text" label="文件类型" width="150" align="center" />
          <el-table-column label="是否必填" width="100" align="center">
            <template #default="{ row }">
              <el-tag :type="row.is_required ? 'danger' : 'info'" size="small">
                {{ row.is_required ? '必填' : '选填' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="180" align="center">
            <template #default="{ row, $index }">
              <el-button type="text" size="small" @click="handleEdit(row)">
                编辑
              </el-button>
              <el-button type="text" size="small" @click="handleMoveUp($index)" :disabled="$index === 0">
                上移
              </el-button>
              <el-button type="text" size="small" @click="handleMoveDown($index)" :disabled="$index === currentConfigs.length - 1">
                下移
              </el-button>
              <el-button type="text" size="small" @click="handleDelete(row)" style="color: #f56c6c;">
                删除
              </el-button>
            </template>
          </el-table-column>
        </el-table>
      </div>

      <el-empty v-else description="当前项目还没有资料方案">
        <el-button type="primary" @click="handleAddSet">新建资料方案</el-button>
      </el-empty>
    </div>

    <template #footer>
      <el-button @click="visible = false">关闭</el-button>
    </template>
  </el-dialog>

  <el-dialog
    v-model="showSetDialog"
    :title="setFormMode === 'add' ? '新增资料方案' : '编辑资料方案'"
    width="500px"
    :close-on-click-modal="false"
  >
    <el-form
      ref="setFormRef"
      :model="setForm"
      :rules="setFormRules"
      label-width="100px"
    >
      <el-form-item label="方案名称" prop="set_name">
        <el-input
          v-model="setForm.set_name"
          placeholder="例如：普通员工资料、司机资料"
          maxlength="100"
          show-word-limit
        />
      </el-form-item>

      <el-form-item label="默认方案" prop="is_default">
        <el-switch
          v-model="setForm.is_default"
          active-text="是"
          inactive-text="否"
        />
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="showSetDialog = false">取消</el-button>
      <el-button type="primary" :loading="submittingSet" @click="handleSubmitSet">
        确定
      </el-button>
    </template>
  </el-dialog>

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

      <el-form-item label="文件类型" prop="document_type">
        <el-radio-group v-model="form.document_type">
          <el-radio label="image">仅图片</el-radio>
          <el-radio label="pdf">仅PDF</el-radio>
          <el-radio label="document">文档</el-radio>
          <el-radio label="all">所有类型</el-radio>
        </el-radio-group>
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
import { computed, reactive, ref, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
  createProjectDocumentConfig,
  createProjectDocumentSet,
  deleteProjectDocumentConfig,
  deleteProjectDocumentSet,
  getProjectDocumentConfigs,
  updateDocumentConfigsSort,
  updateProjectDocumentConfig,
  updateProjectDocumentSet
} from '@/api/projectDocuments'

const props = defineProps({
  modelValue: Boolean,
  projectId: Number
})

const emit = defineEmits(['update:modelValue'])

const visible = ref(false)
const loading = ref(false)
const submitting = ref(false)
const submittingSet = ref(false)

const documentSets = ref([])
const currentSetId = ref(null)

const showSetDialog = ref(false)
const setFormMode = ref('add')
const setFormRef = ref()
const setForm = reactive({
  id: null,
  set_name: '',
  is_default: false
})

const showFormDialog = ref(false)
const formMode = ref('add')
const formRef = ref()
const form = reactive({
  id: null,
  document_name: '',
  document_type: 'all',
  is_required: true
})

const currentSet = computed(() => {
  return documentSets.value.find(item => item.id === currentSetId.value) || null
})

const currentConfigs = computed(() => {
  return currentSet.value?.configs || []
})

const setFormRules = {
  set_name: [
    { required: true, message: '请输入方案名称', trigger: 'blur' }
  ]
}

const formRules = {
  document_name: [
    { required: true, message: '请输入资料名称', trigger: 'blur' }
  ],
  document_type: [
    { required: true, message: '请选择文件类型', trigger: 'change' }
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
  if (!props.projectId) return

  loading.value = true
  try {
    const response = await getProjectDocumentConfigs(props.projectId)
    if (response.success) {
      const data = response.data || {}
      documentSets.value = data.sets || []

      const currentExists = documentSets.value.some(item => item.id === currentSetId.value)
      if (currentExists) {
        return
      }

      currentSetId.value = data.default_set_id || documentSets.value[0]?.id || null
    }
  } catch (error) {
    console.error('加载资料配置失败:', error)
    ElMessage.error('加载资料配置失败')
  } finally {
    loading.value = false
  }
}

const resetSetForm = () => {
  setForm.id = null
  setForm.set_name = ''
  setForm.is_default = documentSets.value.length === 0
}

const handleAddSet = () => {
  setFormMode.value = 'add'
  resetSetForm()
  showSetDialog.value = true
}

const handleEditSet = () => {
  if (!currentSet.value) return

  setFormMode.value = 'edit'
  setForm.id = currentSet.value.id
  setForm.set_name = currentSet.value.set_name
  setForm.is_default = !!currentSet.value.is_default
  showSetDialog.value = true
}

const handleSubmitSet = async () => {
  if (!setFormRef.value) return

  await setFormRef.value.validate(async (valid) => {
    if (!valid) return

    submittingSet.value = true
    try {
      let response
      if (setFormMode.value === 'add') {
        response = await createProjectDocumentSet(props.projectId, {
          set_name: setForm.set_name,
          is_default: setForm.is_default
        })
        ElMessage.success('资料方案创建成功')
      } else {
        response = await updateProjectDocumentSet(props.projectId, setForm.id, {
          set_name: setForm.set_name,
          is_default: setForm.is_default
        })
        ElMessage.success('资料方案更新成功')
      }

      showSetDialog.value = false
      await loadConfigs()
      if (response?.data?.id) {
        currentSetId.value = response.data.id
      }
    } catch (error) {
      console.error('资料方案操作失败:', error)
      ElMessage.error(error.response?.data?.message || '操作失败')
    } finally {
      submittingSet.value = false
    }
  })
}

const handleDeleteSet = async () => {
  if (!currentSet.value) return

  try {
    await ElMessageBox.confirm(`确定要删除方案“${currentSet.value.set_name}”吗？`, '确认删除', {
      type: 'warning'
    })

    await deleteProjectDocumentSet(props.projectId, currentSet.value.id)
    ElMessage.success('删除成功')
    await loadConfigs()
    currentSetId.value = documentSets.value[0]?.id || null
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除资料方案失败:', error)
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }
}

const resetConfigForm = () => {
  form.id = null
  form.document_name = ''
  form.document_type = 'all'
  form.is_required = true
}

const handleAdd = () => {
  if (!currentSet.value) {
    ElMessage.warning('请先新增资料方案')
    return
  }

  formMode.value = 'add'
  resetConfigForm()
  showFormDialog.value = true
}

const handleEdit = (row) => {
  formMode.value = 'edit'
  form.id = row.id
  form.document_name = row.document_name
  form.document_type = row.document_type || 'all'
  form.is_required = row.is_required
  showFormDialog.value = true
}

const handleSubmitForm = async () => {
  if (!formRef.value || !currentSet.value) return

  await formRef.value.validate(async (valid) => {
    if (!valid) return

    submitting.value = true
    try {
      if (formMode.value === 'add') {
        await createProjectDocumentConfig(props.projectId, {
          document_set_id: currentSet.value.id,
          document_name: form.document_name,
          document_type: form.document_type,
          is_required: form.is_required
        })
        ElMessage.success('添加成功')
      } else {
        await updateProjectDocumentConfig(props.projectId, form.id, {
          document_set_id: currentSet.value.id,
          document_name: form.document_name,
          document_type: form.document_type,
          is_required: form.is_required
        })
        ElMessage.success('更新成功')
      }

      showFormDialog.value = false
      await loadConfigs()
    } catch (error) {
      console.error('资料项操作失败:', error)
      ElMessage.error(error.response?.data?.message || '操作失败')
    } finally {
      submitting.value = false
    }
  })
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm('确定要删除这个资料项吗？', '确认删除', {
      type: 'warning'
    })

    await deleteProjectDocumentConfig(props.projectId, row.id)
    ElMessage.success('删除成功')
    await loadConfigs()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除资料项失败:', error)
      ElMessage.error(error.response?.data?.message || '删除失败')
    }
  }
}

const updateSort = async () => {
  if (!currentSet.value) return

  try {
    const updatedConfigs = currentConfigs.value.map((config, index) => ({
      id: config.id,
      sort_order: index + 1
    }))

    await updateDocumentConfigsSort(props.projectId, {
      document_set_id: currentSet.value.id,
      configs: updatedConfigs
    })
  } catch (error) {
    console.error('更新排序失败:', error)
    ElMessage.error('更新排序失败')
    await loadConfigs()
  }
}

const handleMoveUp = async (index) => {
  if (!currentSet.value || index === 0) return

  const configs = currentSet.value.configs
  const temp = configs[index]
  configs[index] = configs[index - 1]
  configs[index - 1] = temp
  await updateSort()
}

const handleMoveDown = async (index) => {
  if (!currentSet.value || index === currentSet.value.configs.length - 1) return

  const configs = currentSet.value.configs
  const temp = configs[index]
  configs[index] = configs[index + 1]
  configs[index + 1] = temp
  await updateSort()
}

const handleClose = () => {
  documentSets.value = []
  currentSetId.value = null
  resetSetForm()
  resetConfigForm()
}
</script>

<style scoped>
.document-config-container {
  min-height: 420px;
}

.set-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.set-toolbar-left,
.set-toolbar-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.config-panel {
  margin-top: 12px;
}

.toolbar {
  margin-bottom: 15px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.toolbar-title {
  font-size: 14px;
  color: #606266;
}
</style>
