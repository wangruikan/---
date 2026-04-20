<template>
  <div class="no-account-set-container">
    <el-result
      icon="warning"
      title="未分配账套"
      sub-title="您还没有被分配到任何账套，无法查看和操作数据"
    >
      <template #extra>
        <el-space direction="vertical" :size="20">
          <el-alert
            title="温馨提示"
            type="info"
            :closable="false"
            show-icon
          >
            <p>账套是系统中的数据隔离单元，用于区分不同公司或业务单元的数据。</p>
            <p>您需要由系统管理员将您分配到相应的账套后，才能进行操作。</p>
          </el-alert>

          <el-descriptions title="您的账号信息" border :column="1">
            <el-descriptions-item label="用户名">{{ userInfo?.name }}</el-descriptions-item>
            <el-descriptions-item label="昵称">{{ userInfo?.nickname || userInfo?.name }}</el-descriptions-item>
            <el-descriptions-item label="邮箱">{{ userInfo?.email }}</el-descriptions-item>
            <el-descriptions-item label="角色">
              <el-tag>{{ getRoleText(userInfo?.role) }}</el-tag>
            </el-descriptions-item>
          </el-descriptions>

          <el-card shadow="never">
            <template #header>
              <span>如何获取账套权限？</span>
            </template>
            <ol style="padding-left: 20px; margin: 0;">
              <li>联系系统管理员</li>
              <li>告知您的用户名和邮箱</li>
              <li>管理员会将您分配到相应的账套</li>
              <li>分配完成后，重新登录即可使用系统</li>
            </ol>
          </el-card>

          <el-button type="primary" @click="handleRefresh">
            <el-icon><Refresh /></el-icon>
            刷新状态
          </el-button>
        </el-space>
      </template>
    </el-result>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useUserStore } from '@/stores/user'
import { useAccountSetStore } from '@/stores/accountSet'
import { ElMessage } from 'element-plus'

const userStore = useUserStore()
const accountSetStore = useAccountSetStore()

const userInfo = computed(() => userStore.userInfo)

const getRoleText = (role) => {
  const texts = {
    admin: '系统管理员',
    hr: '人力资源',
    manager: '经理',
    employee: '员工'
  }
  return texts[role] || role
}

const handleRefresh = async () => {
  try {
    // 重新加载账套列表
    await accountSetStore.loadMyAccountSets()
    
    if (accountSetStore.myAccountSets.length > 0) {
      ElMessage.success('已获取账套权限，正在刷新页面...')
      setTimeout(() => {
        window.location.reload()
      }, 1000)
    } else {
      ElMessage.warning('仍未获取账套权限，请联系管理员')
    }
  } catch (error) {
    ElMessage.error('刷新失败')
  }
}
</script>

<style scoped>
.no-account-set-container {
  min-height: 60vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
}

:deep(.el-result) {
  padding: 40px 20px;
}

:deep(.el-result__title) {
  font-size: 24px;
  margin-top: 20px;
}

:deep(.el-result__subtitle) {
  margin-top: 10px;
  color: #606266;
}

:deep(.el-alert) {
  text-align: left;
}

:deep(.el-alert p) {
  margin: 5px 0;
  line-height: 1.6;
}
</style>

