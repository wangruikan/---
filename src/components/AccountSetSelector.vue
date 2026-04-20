<template>
  <div class="account-set-selector">
    <el-select
      v-model="selectedAccountSetId"
      placeholder="选择账套"
      style="width: 200px"
      @change="handleAccountSetChange"
      filterable
    >
      <el-option
        v-for="accountSet in accountSets"
        :key="accountSet.id"
        :label="accountSet.name"
        :value="accountSet.id"
      >
        <span>{{ accountSet.name }}</span>
        <el-tag v-if="accountSet.is_default" size="small" type="danger" style="float: right; margin-left: 10px;">
          默认
        </el-tag>
      </el-option>
      <template #prefix>
        <el-icon><Box /></el-icon>
      </template>
    </el-select>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useAccountSetStore } from '@/stores/accountSet'
import { ElMessage } from 'element-plus'

const accountSetStore = useAccountSetStore()

const selectedAccountSetId = ref(null)

const accountSets = computed(() => accountSetStore.myAccountSets || [])

// 初始化选中的账套
onMounted(() => {
  if (accountSetStore.currentAccountSetId) {
    selectedAccountSetId.value = Number(accountSetStore.currentAccountSetId)
  } else if (accountSets.value.length > 0) {
    // 默认选择第一个或默认账套
    const defaultSet = accountSets.value.find(s => s.is_default)
    selectedAccountSetId.value = defaultSet ? defaultSet.id : accountSets.value[0].id
    accountSetStore.setCurrentAccountSet(selectedAccountSetId.value)
  }
})

// 监听账套列表变化
watch(() => accountSets.value, (newSets) => {
  if (newSets.length > 0 && !selectedAccountSetId.value) {
    const defaultSet = newSets.find(s => s.is_default)
    selectedAccountSetId.value = defaultSet ? defaultSet.id : newSets[0].id
    accountSetStore.setCurrentAccountSet(selectedAccountSetId.value)
  }
}, { immediate: true })

const handleAccountSetChange = (accountSetId) => {
  if (!accountSetId) return
  
  accountSetStore.setCurrentAccountSet(accountSetId)
  ElMessage.success(`已切换到 ${accountSets.value.find(s => s.id === accountSetId)?.name}`)
  
  // 刷新当前页面数据
  setTimeout(() => {
    window.location.reload()
  }, 500)
}
</script>

<style scoped>
.account-set-selector {
  display: flex;
  align-items: center;
}

:deep(.el-select__prefix) {
  display: flex;
  align-items: center;
}
</style>

