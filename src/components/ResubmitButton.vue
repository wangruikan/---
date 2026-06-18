<template>
  <span>
    <el-button
      v-if="canResubmit"
      type="warning"
      size="small"
      @click="openResubmitDialog"
      :loading="loading"
    >
      重新发起
    </el-button>

    <el-dialog
      v-model="dialogVisible"
      title="重新发起审批"
      width="500px"
      :close-on-click-modal="false"
      append-to-body
    >
      <el-form :model="stampForm" label-width="100px">
        <el-form-item label="盖章方式" required>
          <el-radio-group v-model="stampForm.stamp_method">
            <el-radio value="online">线上盖章</el-radio>
            <el-radio value="offline">线下盖章</el-radio>
          </el-radio-group>
          <div style="margin-top: 8px; color: #909399; font-size: 12px;">
            线上盖章：系统自动在PDF上添加印章；线下盖章：需要手动在纸质文件上盖章
          </div>
        </el-form-item>
        <ApprovalStampSelector
          ref="stampSelectorRef"
          v-model="stampForm.stamp_selection"
        />
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="loading" @click="handleResubmit">
          确认重新发起
        </el-button>
      </template>
    </el-dialog>
  </span>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { resubmitApproval } from '@/api/approvals'
import ApprovalStampSelector from '@/components/ApprovalStampSelector.vue'

const props = defineProps({
  // 业务数据对象
  record: {
    type: Object,
    required: true
  },
  // 业务类型（如：'发票申请'、'工资表审批'等）
  businessType: {
    type: String,
    required: true
  },
  businessId: {
    type: [Number, String],
    default: null
  },
  canResubmit: {
    type: Boolean,
    default: null
  }
})

const emit = defineEmits(['success'])

const loading = ref(false)
const dialogVisible = ref(false)
const stampSelectorRef = ref(null)

const getDefaultStampSelection = () => ({
  stamp_selection_mode: 'none',
  stamp_company: '',
  stamp_type: '',
  stamp_id: null
})

const stampForm = reactive({
  stamp_method: 'online',
  stamp_selection: getDefaultStampSelection()
})

// 判断是否可以重新发起
const canResubmit = computed(() => {
  if (props.canResubmit !== null) {
    return props.canResubmit
  }

  // 优先使用后端返回的 can_resubmit 字段
  if (props.record.can_resubmit !== undefined) {
    return props.record.can_resubmit
  }
  
  // 兼容：检查 status 是否为 rejected
  return props.record.status === 'rejected'
})

const openResubmitDialog = () => {
  stampForm.stamp_method = 'online'
  stampForm.stamp_selection = getDefaultStampSelection()
  dialogVisible.value = true
}

// 处理重新发起
const handleResubmit = async () => {
  try {
    const stampResult = stampSelectorRef.value?.validate?.()
    if (stampResult && !stampResult.valid) {
      ElMessage.warning(stampResult.message)
      return
    }

    loading.value = true
    
    // 调用统一的重新发起 API
    await resubmitApproval({
      business_type: props.businessType,
      business_id: props.businessId || props.record.id,
      stamp_method: stampForm.stamp_method,
      ...(stampResult?.value || stampForm.stamp_selection)
    })
    
    ElMessage.success('重新发起成功')
    dialogVisible.value = false
    emit('success')
    
  } catch (error) {
    if (error !== 'cancel') {
      console.error('重新发起失败:', error)
      // 兼容不同的错误格式
      const errorMsg = error.message || error.response?.data?.message || error.msg || '重新发起失败'
      ElMessage.error(errorMsg)
    }
  } finally {
    loading.value = false
  }
}
</script>
