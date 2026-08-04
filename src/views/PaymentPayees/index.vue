<template>
  <div class="payment-payees-page">
    <el-card shadow="never" class="header-card">
      <div class="header-row">
        <div class="header-title">收付款信息维护</div>
        <el-button type="primary" :icon="Plus" @click="handleCreate">新增收款信息</el-button>
      </div>
    </el-card>

    <el-card shadow="never">
      <el-table :data="payeeList" v-loading="loading" border stripe>
        <el-table-column label="序号" width="80" align="center">
          <template #default="{ $index }">
            {{ $index + 1 }}
          </template>
        </el-table-column>
        <el-table-column prop="payee_name" label="支付对象" min-width="220" />
        <el-table-column prop="bank_name" label="开户行" min-width="260" />
        <el-table-column prop="bank_account" label="账号" min-width="220" />
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" :icon="Edit" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="danger" :icon="Delete" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog
      v-model="dialogVisible"
      :title="editingId ? '编辑收款信息' : '新增收款信息'"
      width="560px"
      :close-on-click-modal="false"
    >
      <el-form ref="formRef" :model="formData" :rules="formRules" label-width="90px">
        <el-form-item label="支付对象" prop="payee_name">
          <el-input
            v-model="formData.payee_name"
            placeholder="请输入支付对象"
            maxlength="100"
            show-word-limit
          />
        </el-form-item>
        <el-form-item label="开户行" prop="bank_name">
          <el-input
            v-model="formData.bank_name"
            placeholder="请输入开户行"
            maxlength="255"
            show-word-limit
          />
        </el-form-item>
        <el-form-item label="账号" prop="bank_account">
          <el-input
            v-model="formData.bank_account"
            placeholder="请输入账号"
            maxlength="100"
            show-word-limit
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Delete, Edit, Plus } from '@element-plus/icons-vue'
import { useAccountSetStore } from '@/stores/accountSet'
import {
  createPaymentPayee,
  deletePaymentPayee,
  getPaymentPayees,
  updatePaymentPayee
} from '@/api/paymentPayees'

const accountSetStore = useAccountSetStore()

const loading = ref(false)
const submitting = ref(false)
const dialogVisible = ref(false)
const editingId = ref(null)
const formRef = ref(null)
const payeeList = ref([])

const formData = reactive({
  payee_name: '',
  bank_name: '',
  bank_account: ''
})

const formRules = {
  payee_name: [{ required: true, message: '请输入支付对象', trigger: 'blur' }],
  bank_name: [{ required: true, message: '请输入开户行', trigger: 'blur' }],
  bank_account: [{ required: true, message: '请输入账号', trigger: 'blur' }]
}

const loadPayeeList = async () => {
  if (!accountSetStore.currentAccountSetId) {
    payeeList.value = []
    return
  }

  loading.value = true
  try {
    const res = await getPaymentPayees({
      account_set_id: accountSetStore.currentAccountSetId
    })
    payeeList.value = Array.isArray(res.data) ? res.data : []
  } catch (error) {
    console.error('加载收款信息失败:', error)
    ElMessage.error('加载收款信息失败')
  } finally {
    loading.value = false
  }
}

const resetForm = () => {
  editingId.value = null
  formData.payee_name = ''
  formData.bank_name = ''
  formData.bank_account = ''
  formRef.value?.clearValidate()
}

const handleCreate = () => {
  resetForm()
  dialogVisible.value = true
}

const handleEdit = (row) => {
  editingId.value = row.id
  formData.payee_name = row.payee_name || ''
  formData.bank_name = row.bank_name || ''
  formData.bank_account = row.bank_account || ''
  dialogVisible.value = true
  formRef.value?.clearValidate()
}

const handleSubmit = async () => {
  if (!formRef.value) return

  await formRef.value.validate(async (valid) => {
    if (!valid) return

    submitting.value = true
    try {
      const payload = {
        account_set_id: accountSetStore.currentAccountSetId,
        payee_name: formData.payee_name,
        bank_name: formData.bank_name,
        bank_account: formData.bank_account
      }

      if (editingId.value) {
        await updatePaymentPayee(editingId.value, payload)
      } else {
        await createPaymentPayee(payload)
      }

      ElMessage.success('保存成功')
      dialogVisible.value = false
      loadPayeeList()
    } catch (error) {
      console.error('保存收款信息失败:', error)
    } finally {
      submitting.value = false
    }
  })
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(`确认删除“${row.payee_name}”吗？`, '提示', {
      type: 'warning'
    })
    await deletePaymentPayee(row.id)
    ElMessage.success('删除成功')
    loadPayeeList()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除收款信息失败:', error)
    }
  }
}

watch(
  () => accountSetStore.currentAccountSetId,
  () => {
    loadPayeeList()
  }
)

onMounted(() => {
  loadPayeeList()
})
</script>

<style scoped>
.payment-payees-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.header-card {
  margin-bottom: 0;
}

.header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.header-title {
  font-size: 16px;
  font-weight: 600;
  color: #303133;
}
</style>
