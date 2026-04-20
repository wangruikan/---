<template>
  <el-button
    v-if="canResubmit"
    type="warning"
    size="small"
    @click="handleResubmit"
    :loading="loading"
  >
    重新发起
  </el-button>
</template>

<script setup>
import { ref, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { resubmitApproval } from '@/api/approvals'

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
  }
})

const emit = defineEmits(['success'])

const loading = ref(false)

// 判断是否可以重新发起
const canResubmit = computed(() => {
  // 优先使用后端返回的 can_resubmit 字段
  if (props.record.can_resubmit !== undefined) {
    return props.record.can_resubmit
  }
  
  // 兼容：检查 status 是否为 rejected
  return props.record.status === 'rejected'
})

// 处理重新发起
const handleResubmit = async () => {
  try {
    await ElMessageBox.confirm(
      '确认要重新发起审批吗？将删除原审批流程并创建新的审批。',
      '提示',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    loading.value = true
    
    // 调用统一的重新发起 API
    await resubmitApproval({
      business_type: props.businessType,
      business_id: props.record.id
    })
    
    ElMessage.success('重新发起成功')
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
