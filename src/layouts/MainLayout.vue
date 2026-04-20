<template>
  <div class="main-layout">
    <el-container>
      <!-- 悬浮菜单 -->
      <HoverMenu />
      
      <!-- 主内容区 -->
      <el-container class="main-container">
        <!-- 顶部导航 -->
        <el-header class="header">
          <div class="header-left">
            <el-breadcrumb separator="/">
              <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
              <el-breadcrumb-item v-if="currentPageTitle">{{ currentPageTitle }}</el-breadcrumb-item>
            </el-breadcrumb>
            
            <!-- 账套选择器 -->
            <AccountSetSelector style="margin-left: 30px;" />
          </div>
          
          <div class="header-right">
            <!-- 通知 - 已隐藏 -->
            <!-- <el-badge :value="unreadCount" :hidden="unreadCount === 0" class="notification-badge">
              <el-button type="text" @click="showNotifications = true">
                <el-icon size="20"><Bell /></el-icon>
              </el-button>
            </el-badge> -->
            
            <!-- 用户菜单 -->
            <el-dropdown @command="handleUserCommand">
              <div class="user-info">
                <el-avatar :size="32" :src="userStore.userInfo?.avatar">
                  {{ userStore.userInfo?.name?.charAt(0) }}
                </el-avatar>
                <span class="user-name">{{ userStore.userInfo?.name }}</span>
                <el-icon><ArrowDown /></el-icon>
              </div>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="profile">个人资料</el-dropdown-item>
                  <el-dropdown-item command="password">修改密码</el-dropdown-item>
                  <el-dropdown-item divided command="logout">退出登录</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </div>
        </el-header>
        
        <!-- 主内容 -->
        <el-main class="main-content">
          <router-view />
        </el-main>
      </el-container>
    </el-container>
    
    <!-- 操作记录弹幕 - 已隐藏 -->
    <!-- <OperationBarrage /> -->
    
    <!-- 通知抽屉 -->
    <el-drawer
      v-model="showNotifications"
      title="通知"
      direction="rtl"
      size="400px"
    >
      <div class="notification-list">
        <div v-if="notifications.length === 0" class="empty-notifications">
          <el-empty description="暂无通知" />
        </div>
        <div v-else>
          <div
            v-for="notification in notifications"
            :key="notification.id"
            class="notification-item"
            :class="{ unread: !notification.is_read }"
            @click="markAsRead(notification.id)"
          >
            <div class="notification-content">
              <div class="notification-title">{{ notification.title }}</div>
              <div class="notification-text">{{ notification.content }}</div>
              <div class="notification-time">{{ formatTime(notification.created_at) }}</div>
            </div>
          </div>
        </div>
      </div>
    </el-drawer>

    <!-- 个人资料弹窗 -->
    <el-dialog v-model="showProfileDialog" title="个人资料" width="500px">
      <el-form :model="profileForm" :rules="profileRules" ref="profileFormRef" label-width="80px">
        <el-form-item label="用户名">
          <el-input v-model="profileForm.name" disabled />
        </el-form-item>
        <el-form-item label="昵称" prop="nickname">
          <el-input v-model="profileForm.nickname" placeholder="请输入昵称" />
        </el-form-item>
        <el-form-item label="邮箱" prop="email">
          <el-input v-model="profileForm.email" placeholder="请输入邮箱" />
        </el-form-item>
        <el-form-item label="电话" prop="phone">
          <el-input v-model="profileForm.phone" placeholder="请输入电话" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showProfileDialog = false">取消</el-button>
        <el-button type="primary" @click="handleSaveProfile" :loading="profileSaving">保存</el-button>
      </template>
    </el-dialog>

    <!-- 修改密码弹窗 -->
    <el-dialog v-model="showPasswordDialog" title="修改密码" width="500px">
      <el-form :model="passwordForm" :rules="passwordRules" ref="passwordFormRef" label-width="100px">
        <el-form-item label="原密码" prop="old_password">
          <el-input v-model="passwordForm.old_password" type="password" placeholder="请输入原密码" show-password />
        </el-form-item>
        <el-form-item label="新密码" prop="new_password">
          <el-input v-model="passwordForm.new_password" type="password" placeholder="请输入新密码（至少6位）" show-password />
        </el-form-item>
        <el-form-item label="确认密码" prop="confirm_password">
          <el-input v-model="passwordForm.confirm_password" type="password" placeholder="请再次输入新密码" show-password />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showPasswordDialog = false">取消</el-button>
        <el-button type="primary" @click="handleChangePassword" :loading="passwordSaving">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { useAccountSetStore } from '@/stores/accountSet'
import { usePermissionStore } from '@/stores/permission'
import request from '@/api/request'
import { ElMessage, ElMessageBox } from 'element-plus'
import dayjs from 'dayjs'
import AccountSetSelector from '@/components/AccountSetSelector.vue'
import HoverMenu from '@/components/HoverMenu.vue'
// import OperationBarrage from '@/components/OperationBarrage.vue' // 已隐藏弹幕功能
import { Bell, ArrowDown } from '@element-plus/icons-vue'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const accountSetStore = useAccountSetStore()
const permissionStore = usePermissionStore()

const showNotifications = ref(false)
const notifications = ref([])

// 个人资料相关
const showProfileDialog = ref(false)
const profileSaving = ref(false)
const profileFormRef = ref()
const profileForm = reactive({
  name: '',
  nickname: '',
  email: '',
  phone: ''
})
const profileRules = {
  email: [{ type: 'email', message: '请输入正确的邮箱格式', trigger: 'blur' }]
}

// 修改密码相关
const showPasswordDialog = ref(false)
const passwordSaving = ref(false)
const passwordFormRef = ref()
const passwordForm = reactive({
  old_password: '',
  new_password: '',
  confirm_password: ''
})
const passwordRules = {
  old_password: [{ required: true, message: '请输入原密码', trigger: 'blur' }],
  new_password: [
    { required: true, message: '请输入新密码', trigger: 'blur' },
    { min: 6, message: '密码至少6位', trigger: 'blur' }
  ],
  confirm_password: [
    { required: true, message: '请再次输入新密码', trigger: 'blur' },
    {
      validator: (rule, value, callback) => {
        if (value !== passwordForm.new_password) {
          callback(new Error('两次输入的密码不一致'))
        } else {
          callback()
        }
      },
      trigger: 'blur'
    }
  ]
}

const currentPageTitle = computed(() => route.meta?.title)
const unreadCount = computed(() => notifications.value.filter(n => !n.is_read).length)

const handleUserCommand = async (command) => {
  switch (command) {
    case 'profile':
      // 打开个人资料弹窗
      profileForm.name = userStore.userInfo?.name || ''
      profileForm.nickname = userStore.userInfo?.nickname || ''
      profileForm.email = userStore.userInfo?.email || ''
      profileForm.phone = userStore.userInfo?.phone || ''
      showProfileDialog.value = true
      break
    case 'password':
      // 打开修改密码弹窗
      passwordForm.old_password = ''
      passwordForm.new_password = ''
      passwordForm.confirm_password = ''
      showPasswordDialog.value = true
      break
    case 'logout':
      try {
        await ElMessageBox.confirm('确定要退出登录吗？', '提示', {
          confirmButtonText: '确定',
          cancelButtonText: '取消',
          type: 'warning'
        })
        await userStore.logoutUser()
        router.push('/login')
      } catch (error) {
        // 用户取消
      }
      break
  }
}

// 保存个人资料
const handleSaveProfile = async () => {
  if (!profileFormRef.value) return
  
  await profileFormRef.value.validate(async (valid) => {
    if (!valid) return
    
    profileSaving.value = true
    try {
      await request({
        url: '/auth/profile',
        method: 'put',
        data: {
          nickname: profileForm.nickname,
          email: profileForm.email,
          phone: profileForm.phone
        }
      })
      
      // 更新本地用户信息
      userStore.userInfo.nickname = profileForm.nickname
      userStore.userInfo.email = profileForm.email
      userStore.userInfo.phone = profileForm.phone
      
      ElMessage.success('个人资料更新成功')
      showProfileDialog.value = false
    } catch (error) {
      console.error('更新个人资料失败:', error)
      ElMessage.error(error.response?.data?.message || '更新失败')
    } finally {
      profileSaving.value = false
    }
  })
}

// 修改密码
const handleChangePassword = async () => {
  if (!passwordFormRef.value) return
  
  await passwordFormRef.value.validate(async (valid) => {
    if (!valid) return
    
    passwordSaving.value = true
    try {
      await request({
        url: '/auth/change-password',
        method: 'post',
        data: {
          old_password: passwordForm.old_password,
          new_password: passwordForm.new_password
        }
      })
      
      ElMessage.success('密码修改成功')
      showPasswordDialog.value = false
    } catch (error) {
      console.error('修改密码失败:', error)
      ElMessage.error(error.response?.data?.message || '修改失败')
    } finally {
      passwordSaving.value = false
    }
  })
}

const markAsRead = async (notificationId) => {
  // 这里应该调用API标记为已读
  const notification = notifications.value.find(n => n.id === notificationId)
  if (notification) {
    notification.is_read = true
  }
}

const formatTime = (time) => {
  return dayjs(time).format('MM-DD HH:mm')
}

const loadNotifications = async () => {
  try {
    // 这里应该调用API获取通知
    // const response = await getNotifications()
    // notifications.value = response.data
  } catch (error) {
    console.error('Load notifications error:', error)
  }
}

onMounted(async () => {
  loadNotifications()
  
  // 确保账套数据已加载
  if (!accountSetStore.myAccountSets || accountSetStore.myAccountSets.length === 0) {
    try {
      await accountSetStore.loadMyAccountSets()
    } catch (error) {
      console.error('加载账套数据失败:', error)
    }
  }
})
</script>

<style scoped>
.main-layout {
  height: 100vh;
  overflow: hidden;
}

.main-container {
  margin-left: 80px;
  height: 100vh;
  overflow-y: auto;
}

.sidebar-menu .el-sub-menu .el-menu-item.is-active .el-icon {
  color: white;
}

.header {
  background: white;
  border-bottom: 1px solid #e4e7ed;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
}

.header-left {
  display: flex;
  align-items: center;
}

.collapse-btn {
  margin-right: 20px;
  color: #606266;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 20px;
}

.notification-badge {
  margin-right: 10px;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  padding: 8px 12px;
  border-radius: 4px;
  transition: background-color 0.3s;
}

.user-info:hover {
  background: #f5f7fa;
}

.user-name {
  font-size: 14px;
  color: #606266;
}

.main-content {
  background: #f0f2f5;
  padding: 20px;
}

.notification-list {
  padding: 20px 0;
}

.empty-notifications {
  text-align: center;
  padding: 40px 0;
}

.notification-item {
  padding: 15px 20px;
  border-bottom: 1px solid #f0f0f0;
  cursor: pointer;
  transition: background-color 0.3s;
}

.notification-item:hover {
  background: #f5f7fa;
}

.notification-item.unread {
  background: #f0f9ff;
  border-left: 3px solid #409eff;
}

.notification-title {
  font-weight: 500;
  color: #303133;
  margin-bottom: 5px;
}

.notification-text {
  color: #606266;
  font-size: 14px;
  margin-bottom: 5px;
}

.notification-time {
  color: #909399;
  font-size: 12px;
}
</style>
