<template>
  <div v-if="enabled && hasPermission" class="operation-barrage">
    <!-- 弹幕开关按钮 -->
    <div class="barrage-toggle" @click="toggleBarrage">
      <el-icon :size="20">
        <View v-if="!isVisible" />
        <Hide v-else />
      </el-icon>
      <span>{{ isVisible ? '关闭' : '开启' }}弹幕</span>
    </div>
    
    <!-- 弹幕容器 -->
    <div v-if="isVisible" class="barrage-screen">
      <div
        v-for="barrage in activeBarrages"
        :key="barrage.id"
        class="barrage-item"
        :style="{
          top: barrage.top + 'px',
          animationDuration: barrage.duration + 's',
          color: barrage.color
        }"
      >
        <span class="barrage-user">{{ barrage.user_name }}：</span>
        <span class="barrage-text">{{ barrage.description }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { View, Hide } from '@element-plus/icons-vue'
import request from '@/api/request'
import { useUserStore } from '@/stores/user'
import { useAccountSetStore } from '@/stores/accountSet'

const userStore = useUserStore()
const accountSetStore = useAccountSetStore()

const enabled = ref(true) // 是否启用弹幕功能
const isVisible = ref(false) // 是否显示弹幕
const activeBarrages = ref([]) // 当前屏幕上的弹幕
const lastFetchTime = ref(null)
const displayedLogIds = ref(new Set()) // 记录已显示的日志ID
let pollingTimer = null
let barrageIdCounter = 0

// 弹幕颜色池
const colors = [
  '#409EFF', // 蓝色
  '#67C23A', // 绿色
  '#E6A23C', // 橙色
  '#F56C6C', // 红色
  '#909399', // 灰色
  '#00D7FF', // 青色
  '#FF69B4', // 粉色
  '#9370DB', // 紫色
]

// 检查用户是否有权限查看操作日志
const hasPermission = computed(() => {
  const user = userStore.userInfo
  if (!user) return false
  
  // 超级管理员和管理员可以查看
  if (user.role === 'admin' || user.role === 'super_admin') return true
  
  // 检查用户是否有弹幕查看权限
  if (user.can_view_operation_barrage) return true
  
  return false
})

// 切换弹幕显示
const toggleBarrage = () => {
  isVisible.value = !isVisible.value
  
  if (isVisible.value) {
    // 清空当前屏幕上的弹幕
    activeBarrages.value = []
    // 清空已显示日志ID集合
    displayedLogIds.value.clear()
    // 打开弹幕时，设置当前时间为起点，忽略历史记录
    // 使用本地时间格式，避免时区问题
    const now = new Date()
    const localTime = now.getFullYear() + '-' + 
                      String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                      String(now.getDate()).padStart(2, '0') + ' ' + 
                      String(now.getHours()).padStart(2, '0') + ':' + 
                      String(now.getMinutes()).padStart(2, '0') + ':' + 
                      String(now.getSeconds()).padStart(2, '0')
    lastFetchTime.value = localTime
    console.log('[弹幕] 开启弹幕，设置起始时间:', localTime)
    console.log('[弹幕] 只会显示此时间之后的操作记录')
    // 开始轮询（不立即获取，等待新的操作产生）
    startPolling()
  } else {
    console.log('[弹幕] 关闭弹幕')
    stopPolling()
    activeBarrages.value = []
  }
}

// 计算弹幕轨道（避免重叠）
const getBarrageTrack = () => {
  const trackHeight = 40 // 每条弹幕的高度
  const maxTracks = Math.floor(window.innerHeight / trackHeight) - 4 // 减去顶部空间
  const randomTrack = Math.floor(Math.random() * maxTracks)
  return 80 + randomTrack * trackHeight // 从顶部80px开始
}

// 添加弹幕到屏幕
const addBarrage = (log) => {
  const barrage = {
    id: `barrage-${barrageIdCounter++}-${log.id}`,
    user_name: log.user_name,
    description: log.description,
    top: getBarrageTrack(),
    duration: 20 + Math.random() * 10, // 20-30秒随机速度（更慢）
    color: colors[Math.floor(Math.random() * colors.length)]
  }
  
  activeBarrages.value.push(barrage)
  
  // 弹幕飘完后移除
  setTimeout(() => {
    const index = activeBarrages.value.findIndex(b => b.id === barrage.id)
    if (index > -1) {
      activeBarrages.value.splice(index, 1)
    }
  }, barrage.duration * 1000)
}

// 获取最新日志
const fetchLogs = async () => {
  try {
    const params = {
      limit: 20
    }
    
    // 如果有上次获取时间,只获取之后的日志
    if (lastFetchTime.value) {
      params.after = lastFetchTime.value
      console.log('[弹幕] 获取日志，after参数:', lastFetchTime.value)
    } else {
      console.log('[弹幕] 获取日志，无after参数（会获取所有历史记录）')
    }
    
    // 如果有当前账套,只显示该账套的日志
    if (accountSetStore.currentAccountSetId) {
      params.account_set_id = accountSetStore.currentAccountSetId
    }
    
    const response = await request({
      url: '/operation-logs/latest',
      method: 'get',
      params
    })
    
    console.log('[弹幕] API返回日志数量:', response.data?.length || 0)
    
    if (response.success && response.data.length > 0) {
      // 过滤掉已显示的日志
      const newLogs = response.data.filter(log => !displayedLogIds.value.has(log.id))
      
      console.log('[弹幕] 过滤后新日志数量:', newLogs.length)
      
      if (newLogs.length > 0) {
        // 记录已显示的日志ID
        newLogs.forEach(log => {
          displayedLogIds.value.add(log.id)
          console.log('[弹幕] 添加弹幕:', log.description)
          // 添加弹幕，每条延迟一点时间，避免同时出现
          setTimeout(() => {
            if (isVisible.value) {
              addBarrage(log)
            }
          }, Math.random() * 2000)
        })
        
        // 更新最后获取时间为当前本地时间
        const now = new Date()
        const localTime = now.getFullYear() + '-' + 
                          String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                          String(now.getDate()).padStart(2, '0') + ' ' + 
                          String(now.getHours()).padStart(2, '0') + ':' + 
                          String(now.getMinutes()).padStart(2, '0') + ':' + 
                          String(now.getSeconds()).padStart(2, '0')
        lastFetchTime.value = localTime
      }
    }
  } catch (error) {
    console.error('[弹幕] 获取操作日志失败:', error)
  }
}

// 开始轮询
const startPolling = () => {
  if (pollingTimer) return
  
  // 每1秒轮询一次
  pollingTimer = setInterval(() => {
    fetchLogs()
  }, 1000)
}

// 停止轮询
const stopPolling = () => {
  if (pollingTimer) {
    clearInterval(pollingTimer)
    pollingTimer = null
  }
}

onMounted(() => {
  // 默认不显示,用户可以手动打开
})

onUnmounted(() => {
  stopPolling()
})
</script>

<style scoped>
.operation-barrage {
  position: fixed;
  right: 20px;
  top: 150px;
  z-index: 999;
}

.barrage-toggle {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: rgba(255, 255, 255, 0.95);
  border-radius: 20px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
  cursor: pointer;
  transition: all 0.3s;
  backdrop-filter: blur(10px);
}

.barrage-toggle:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
  transform: translateY(-2px);
  background: rgba(255, 255, 255, 1);
}

/* 弹幕屏幕容器 */
.barrage-screen {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  z-index: 998;
  overflow: hidden;
}

/* 弹幕项 */
.barrage-item {
  position: absolute;
  right: -100%;
  white-space: nowrap;
  font-size: 16px;
  font-weight: 500;
  padding: 8px 16px;
  background: rgba(0, 0, 0, 0.7);
  border-radius: 20px;
  backdrop-filter: blur(10px);
  animation: barrage-move linear forwards;
  text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.barrage-user {
  font-weight: 700;
  margin-right: 4px;
}

.barrage-text {
  opacity: 0.95;
}

/* 弹幕移动动画 */
@keyframes barrage-move {
  from {
    right: -100%;
    opacity: 0;
  }
  5% {
    opacity: 1;
  }
  95% {
    opacity: 1;
  }
  to {
    right: 100%;
    opacity: 0;
  }
}

/* 响应式调整 */
@media (max-width: 768px) {
  .barrage-item {
    font-size: 14px;
    padding: 6px 12px;
  }
}
</style>
