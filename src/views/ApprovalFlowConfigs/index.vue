<template>
  <div class="approval-flow-configs-page">
    <div class="page-header">
      <div>
        <h1>审批流程配置</h1>
        <p>按账套配置每类审批需要经过哪些审批节点，关闭的节点会在发起审批时自动跳过。</p>
      </div>
      <el-button type="primary" :loading="loading" @click="loadConfigs">刷新</el-button>
    </div>

    <el-empty v-if="!accountSetId" description="请先在顶部选择账套" />

    <template v-else>
      <el-alert
        v-if="approvalLevels.length === 0"
        title="当前账套还没有配置审批节点，请先到账套管理中设置审批级别。"
        type="warning"
        :closable="false"
        show-icon
        class="page-alert"
      />

      <el-card>
        <template #header>
          <div class="card-header">
            <span>当前账套审批路线</span>
            <div class="card-actions">
              <span class="account-name">{{ currentAccountSetName }}</span>
              <el-button
                type="primary"
                size="small"
                :loading="savingAll"
                :disabled="approvalLevels.length === 0 || configs.length === 0"
                @click="saveAll"
              >
                一键保存
              </el-button>
            </div>
          </div>
        </template>

        <el-table :data="configs" v-loading="loading" border stripe>
          <el-table-column prop="business_label" label="审批业务" min-width="180" fixed="left" />
          <el-table-column label="业务标识" min-width="180">
            <template #default="{ row }">
              {{ getBusinessTypeDisplay(row) }}
            </template>
          </el-table-column>

          <el-table-column
            v-for="level in approvalLevels"
            :key="level.level"
            :label="level.level_name"
            width="180"
            align="center"
          >
            <template #header>
              <div class="level-header">
                <span>{{ level.level_name }}</span>
                <small>{{ getApproverNames(level) }}</small>
              </div>
            </template>
            <template #default="{ row }">
              <el-switch
                v-model="row.level_map[level.level]"
                :disabled="savingAll"
                active-text="启用"
                inactive-text="跳过"
              />
            </template>
          </el-table-column>

          <el-table-column label="配置状态" width="120" align="center">
            <template #default="{ row }">
              <el-tag :type="row.is_default ? 'info' : 'success'">
                {{ row.is_default ? '默认全部启用' : '已配置' }}
              </el-tag>
            </template>
          </el-table-column>
        </el-table>
      </el-card>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { getApprovalFlowConfigs, saveApprovalFlowConfig } from '@/api/approvalFlowConfigs'
import { useAccountSetStore } from '@/stores/accountSet'

const accountSetStore = useAccountSetStore()
const accountSetId = computed(() => accountSetStore.currentAccountSetId)
const currentAccountSetName = computed(() => accountSetStore.currentAccountSet?.name || '当前账套')

const loading = ref(false)
const approvalLevels = ref([])
const configs = ref([])
const savingAll = ref(false)

const buildLevelMap = (enabledLevels) => {
  const enabledSet = new Set((enabledLevels || []).map((level) => Number(level)))
  return approvalLevels.value.reduce((map, level) => {
    map[level.level] = enabledSet.has(Number(level.level))
    return map
  }, {})
}

const normalizeRows = (rows) => {
  return (rows || []).map((row) => ({
    ...row,
    level_map: buildLevelMap(row.enabled_levels)
  }))
}

const loadConfigs = async () => {
  if (!accountSetId.value) return

  loading.value = true
  try {
    const res = await getApprovalFlowConfigs()
    if (res.success) {
      approvalLevels.value = res.data?.approval_levels || []
      configs.value = normalizeRows(res.data?.configs || [])
    }
  } catch (error) {
    ElMessage.error(error?.response?.data?.message || '加载审批流程配置失败')
  } finally {
    loading.value = false
  }
}

const getApproverNames = (level) => {
  const names = level.approver_names || []
  return names.length ? names.join('、') : '未设置人员'
}

const getBusinessTypeDisplay = (row) => {
  return row.business_label || row.business_type || '-'
}

const getEnabledLevels = (row) => {
  return Object.entries(row.level_map || {})
    .filter(([, enabled]) => enabled)
    .map(([level]) => Number(level))
    .sort((a, b) => a - b)
}

const saveAll = async () => {
  const payloads = configs.value.map((row) => ({
    row,
    enabledLevels: getEnabledLevels(row)
  }))
  const invalidPayload = payloads.find((item) => item.enabledLevels.length === 0)

  if (invalidPayload) {
    ElMessage.warning(`${getBusinessTypeDisplay(invalidPayload.row)} 请至少启用一个审批节点`)
    return
  }

  savingAll.value = true
  try {
    const results = await Promise.all(payloads.map((item) => {
      return saveApprovalFlowConfig({
        business_type: item.row.business_type,
        enabled_levels: item.enabledLevels
      })
    }))

    const failedResult = results.find((res) => !res?.success)
    if (failedResult) {
      ElMessage.error(failedResult.message || '保存失败')
      return
    }

    payloads.forEach(({ row, enabledLevels }) => {
      row.enabled_levels = enabledLevels
      row.is_default = false
    })
    ElMessage.success('保存成功')
  } catch (error) {
    ElMessage.error(error?.response?.data?.message || '保存失败')
  } finally {
    savingAll.value = false
  }
}

onMounted(() => {
  loadConfigs()
})

watch(accountSetId, () => {
  approvalLevels.value = []
  configs.value = []
  loadConfigs()
})
</script>

<style scoped>
.approval-flow-configs-page {
  padding: 20px;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 20px;
}

.page-header h1 {
  margin: 0 0 8px;
  font-size: 22px;
  color: #303133;
}

.page-header p {
  margin: 0;
  color: #606266;
}

.page-alert {
  margin-bottom: 16px;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.card-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.account-name {
  color: #909399;
  font-size: 13px;
}

.level-header {
  display: flex;
  flex-direction: column;
  gap: 4px;
  line-height: 1.2;
}

.level-header small {
  color: #909399;
  font-weight: 400;
}
</style>
