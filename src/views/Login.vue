<template>
  <div class="login-container">
    <div class="login-box">
      <div class="login-header">
        <h1>人力资源管理系统</h1>
        <p>Human Resource Management System</p>
      </div>
      
      <el-form
        ref="loginFormRef"
        :model="loginForm"
        :rules="loginRules"
        class="login-form"
        @submit.prevent="handleLogin"
      >
        <el-form-item prop="username">
          <el-autocomplete
            v-model="loginForm.username"
            placeholder="请输入用户名"
            size="large"
            :fetch-suggestions="querySavedAccounts"
            @select="handleAccountSelect"
            @focus="showAccountList = true"
            style="width: 100%"
            clearable
          >
            <template #prefix>
              <el-icon><User /></el-icon>
            </template>
            <template #default="{ item }">
              <div class="account-item">
                <span class="account-username">{{ item.username }}</span>
                <el-button
                  link
                  type="danger"
                  size="small"
                  @click.stop="removeAccount(item.username)"
                  class="remove-btn"
                >
                  删除
                </el-button>
              </div>
            </template>
          </el-autocomplete>
        </el-form-item>
        
        <el-form-item prop="password">
          <el-input
            v-model="loginForm.password"
            type="password"
            placeholder="请输入密码"
            size="large"
            prefix-icon="Lock"
            show-password
            @keyup.enter="handleLogin"
          />
        </el-form-item>
        
        <el-form-item>
          <el-button
            type="primary"
            size="large"
            class="login-button"
            :loading="loading"
            @click="handleLogin"
          >
            登录
          </el-button>
        </el-form-item>
      </el-form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { ElMessage } from 'element-plus'
import { User } from '@element-plus/icons-vue'

const router = useRouter()
const userStore = useUserStore()

const loginFormRef = ref()
const loading = ref(false)
const showAccountList = ref(false)

const loginForm = reactive({
  username: '',
  password: ''
})

const loginRules = {
  username: [
    { required: true, message: '请输入用户名', trigger: 'blur' },
    { min: 2, message: '用户名长度不能少于2位', trigger: 'blur' }
  ],
  password: [
    { required: true, message: '请输入密码', trigger: 'blur' },
    { min: 6, message: '密码长度不能少于6位', trigger: 'blur' }
  ]
}

// 获取保存的账号列表
const getSavedAccounts = () => {
  const saved = localStorage.getItem('saved_login_accounts')
  return saved ? JSON.parse(saved) : []
}

// 保存账号到本地存储
const saveAccount = (username, password) => {
  const accounts = getSavedAccounts()
  // 检查是否已存在
  const index = accounts.findIndex(acc => acc.username === username)
  if (index >= 0) {
    // 更新密码
    accounts[index].password = password
  } else {
    // 添加新账号（最多保存5个）
    accounts.push({ username, password })
    if (accounts.length > 5) {
      accounts.shift() // 删除最旧的账号
    }
  }
  localStorage.setItem('saved_login_accounts', JSON.stringify(accounts))
}

// 删除账号
const removeAccount = (username) => {
  const accounts = getSavedAccounts()
  const filtered = accounts.filter(acc => acc.username !== username)
  localStorage.setItem('saved_login_accounts', JSON.stringify(filtered))
  ElMessage.success('已删除账号记录')
}

// 查询已保存的账号（用于自动完成）
const querySavedAccounts = (queryString, cb) => {
  const accounts = getSavedAccounts()
  // 直接返回所有账号，不进行过滤
  const results = accounts.map(account => ({
    value: account.username,
    username: account.username,
    password: account.password
  }))
  cb(results)
}

// 选择账号
const handleAccountSelect = (item) => {
  loginForm.username = item.username
  loginForm.password = item.password
}

const handleLogin = async () => {
  if (!loginFormRef.value) return
  
  await loginFormRef.value.validate(async (valid) => {
    if (valid) {
      loading.value = true
      try {
        await userStore.loginUser(loginForm)
        // 登录成功，保存账号信息（密码也保存，但实际使用中建议只保存用户名）
        saveAccount(loginForm.username, loginForm.password)
        router.push('/')
      } catch (error) {
        console.error('Login error:', error)
      } finally {
        loading.value = false
      }
    }
  })
}

// 页面加载时，如果有保存的账号，自动填充最后一个
onMounted(() => {
  const accounts = getSavedAccounts()
  if (accounts.length > 0) {
    const lastAccount = accounts[accounts.length - 1]
    loginForm.username = lastAccount.username
    // 不自动填充密码，用户需要手动输入或选择
  }
})
</script>

<style scoped>
.login-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.login-box {
  width: 400px;
  padding: 40px;
  background: white;
  border-radius: 10px;
  box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
}

.login-header {
  text-align: center;
  margin-bottom: 30px;
}

.login-header h1 {
  color: #333;
  font-size: 28px;
  margin-bottom: 10px;
}

.login-header p {
  color: #666;
  font-size: 14px;
}

.login-form {
  margin-top: 20px;
}

.login-button {
  width: 100%;
  height: 45px;
  font-size: 16px;
}

:deep(.el-input__wrapper) {
  height: 45px;
}

:deep(.el-form-item) {
  margin-bottom: 20px;
}

.account-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}

.account-username {
  flex: 1;
}

.remove-btn {
  padding: 0;
  margin-left: 10px;
}
</style>
