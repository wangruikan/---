<template>
  <div class="hover-menu-container">
    <!-- 左侧一级菜单 -->
    <div class="primary-menu">
      <div class="logo">
        <div class="logo-icon">HRM</div>
      </div>
      
      <div 
        v-for="menu in visibleMenus" 
        :key="menu.id"
        :ref="el => setMenuRef(menu.id, el)"
        class="menu-item"
        :class="{ active: isMenuActive(menu) }"
        @mouseenter="handleMenuHover(menu, $event)"
        @click="handleMenuClick(menu)"
      >
        <el-icon :size="18">
          <component :is="menu.icon" />
        </el-icon>
        <span class="menu-title">{{ menu.title }}</span>
      </div>
    </div>
    
    <!-- 右侧悬浮子菜单面板 -->
    <transition name="submenu-fade">
      <div 
        v-if="activeMenu && activeMenu.children"
        class="submenu-panel"
        :style="{ top: submenuTop + 'px', '--submenu-panel-height': submenuPanelHeight + 'px' }"
        @mouseenter="keepSubmenuOpen"
        @mouseleave="closeSubmenu"
      >
        <div class="submenu-header">
          <h3>{{ activeMenu.title }}</h3>
        </div>
        <div class="submenu-grid">
          <div
            v-for="item in visibleSubmenuItems"
            :key="item.path"
            class="submenu-item"
            :class="{ active: $route.path === item.path }"
            @click="navigateTo(item.path)"
          >
            <el-icon :size="20">
              <component :is="item.icon" />
            </el-icon>
            <span>{{ item.title }}</span>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { useAccountSetStore } from '@/stores/accountSet'
import { usePermissionStore } from '@/stores/permission'
import { menuConfig } from '@/config/menuConfig'
import {
  House, User, CircleClose, UserFilled, Document, Money, Wallet, 
  Calendar, Setting, Folder, Edit, Files, Checked, FirstAidKit,
  DocumentChecked, List, Tickets, DocumentCopy, FolderOpened, 
  Link, Box, Key, Suitcase
} from '@element-plus/icons-vue'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()
const accountSetStore = useAccountSetStore()
const permissionStore = usePermissionStore()

const activeMenu = ref(null)
const hoverTimer = ref(null)
const leaveTimer = ref(null)
const submenuTop = ref(0)
const menuRefs = ref({})
const submenuPanelHeight = 388
const submenuViewportMargin = 14

const resolveSubmenuTop = (preferredTop = submenuViewportMargin) => {
  const viewportHeight = window.innerHeight || 0
  const maxTop = Math.max(
    submenuViewportMargin,
    viewportHeight - submenuPanelHeight - submenuViewportMargin
  )
  return Math.min(Math.max(preferredTop, submenuViewportMargin), maxTop)
}

const handleWindowResize = () => {
  submenuTop.value = resolveSubmenuTop(submenuTop.value)
}

// 设置菜单项的ref
const setMenuRef = (menuId, el) => {
  if (el) {
    menuRefs.value[menuId] = el
  }
}

// 权限判断
const isAdmin = computed(() => userStore.userInfo?.role === 'admin')
const hasAccountSet = computed(() => {
  return accountSetStore.myAccountSets && accountSetStore.myAccountSets.length > 0
})

const hasBusinessModuleAccess = computed(() => {
  return permissionStore.hasModuleAccess('projects') ||
    permissionStore.hasModuleAccess('attendance') ||
    permissionStore.hasModuleAccess('salaries') ||
    permissionStore.hasModuleAccess('social_security') ||
    permissionStore.hasModuleAccess('housing_fund') ||
    permissionStore.hasModuleAccess('other_insurance') ||
    permissionStore.hasModuleAccess('large_medical') ||
    permissionStore.hasModuleAccess('base_adjustment') ||
    permissionStore.hasModuleAccess('insurance_change') ||
    permissionStore.hasModuleAccess('payment_applications') ||
    permissionStore.hasModuleAccess('payment_summaries') ||
    permissionStore.hasModuleAccess('approvals') ||
    permissionStore.hasModuleAccess('account_sets')
})

// 过滤可见菜单
const visibleMenus = computed(() => {
  return menuConfig.filter(menu => {
    // 基础权限检查
    if (menu.requireAdmin && !isAdmin.value) return false
    if (menu.requireBusiness && !isAdmin.value && !hasAccountSet.value && !hasBusinessModuleAccess.value) return false
    
    // 角色菜单显示权限检查
    const userVisibleMenus = userStore.userInfo?.visible_menus
    
    // 如果用户的 visible_menus 为 null 或 undefined，表示可以看到所有菜单（管理员）
    if (!userVisibleMenus) return true
    
    // 如果 visible_menus 是空数组，表示没有任何菜单权限
    if (Array.isArray(userVisibleMenus) && userVisibleMenus.length === 0) return false
    
    // 如果是有子菜单的一级菜单，检查是否有任意子菜单权限
    if (menu.children && menu.children.length > 0) {
      // 只要有任意一个子菜单在可见列表中，就显示这个一级菜单
      const hasAnyChildVisible = menu.children.some(child => {
        const submenuId = `${menu.id}-${child.path.replace(/\//g, '-')}`
        return userVisibleMenus.includes(submenuId)
      })
      if (hasAnyChildVisible) return true
    }
    
    // 检查当前菜单ID是否在用户的可见菜单列表中
    if (Array.isArray(userVisibleMenus) && !userVisibleMenus.includes(menu.id)) return false
    
    return true
  })
})

// 过滤可见子菜单项
const visibleSubmenuItems = computed(() => {
  if (!activeMenu.value || !activeMenu.value.children) return []
  
  return activeMenu.value.children.filter(item => {
    // 如果设置了跳过权限检查，直接显示
    if (item.skipPermissionCheck) return true
    
    // 检查账套权限
    if (item.requireAccountSet && !isAdmin.value && !hasAccountSet.value) return false
    
    // 检查审批级别（经办人不可见）
    if (item.notForLevel1 && userStore.userInfo?.approval_level === 1) return false
    
    // 检查特定权限
    if (item.permission && !permissionStore.hasPermission(item.permission)) return false
    
    // 角色菜单显示权限检查（二级菜单）
    const userVisibleMenus = userStore.userInfo?.visible_menus
    
    // 如果用户的 visible_menus 为 null 或 undefined，表示可以看到所有菜单（管理员）
    if (!userVisibleMenus) return true
    
    // 如果 visible_menus 是空数组，表示没有任何菜单权限
    if (Array.isArray(userVisibleMenus) && userVisibleMenus.length === 0) return false
    
    // 生成二级菜单的唯一ID：父菜单ID + 子菜单path
    const submenuId = `${activeMenu.value.id}-${item.path.replace(/\//g, '-')}`
    
    // 检查二级菜单ID是否在用户的可见菜单列表中
    if (Array.isArray(userVisibleMenus) && !userVisibleMenus.includes(submenuId)) return false
    
    // 这些需要动态检查，暂时都显示
    // if (item.requireInvoice) return hasInvoiceAccess.value
    // if (item.requireDelivery) return hasDeliveryConfigAccess.value
    // if (item.requireProcessRecord) return canViewProcessRecords.value
    
    return true
  })
})

// 判断菜单是否激活
const isMenuActive = (menu) => {
  if (menu.path) {
    return route.path === menu.path
  }
  if (menu.children) {
    return menu.children.some(item => route.path === item.path)
  }
  return false
}

// 处理菜单悬浮
const handleMenuHover = (menu, event) => {
  // 清除之前的定时器
  if (leaveTimer.value) {
    clearTimeout(leaveTimer.value)
    leaveTimer.value = null
  }
  
  // 延迟显示子菜单
  if (hoverTimer.value) {
    clearTimeout(hoverTimer.value)
  }
  
  hoverTimer.value = setTimeout(() => {
    if (menu.children && menu.children.length > 0) {
      activeMenu.value = menu
      
      // 计算弹窗位置
      const menuElement = menuRefs.value[menu.id]
      if (menuElement) {
        const rect = menuElement.getBoundingClientRect()
        submenuTop.value = resolveSubmenuTop(rect.top - 8)
      } else {
        submenuTop.value = resolveSubmenuTop()
      }
    } else {
      activeMenu.value = null
    }
  }, 100)
}

// 处理菜单点击
const handleMenuClick = (menu) => {
  if (menu.path) {
    router.push(menu.path)
    activeMenu.value = null
  }
}

// 保持子菜单打开
const keepSubmenuOpen = () => {
  if (leaveTimer.value) {
    clearTimeout(leaveTimer.value)
    leaveTimer.value = null
  }
}

// 关闭子菜单
const closeSubmenu = () => {
  leaveTimer.value = setTimeout(() => {
    activeMenu.value = null
  }, 200)
}

// 导航到子菜单项
const navigateTo = (path) => {
  router.push(path)
  activeMenu.value = null
}

onMounted(() => {
  window.addEventListener('resize', handleWindowResize)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', handleWindowResize)
})

// 监听路由变化，关闭子菜单
watch(() => route.path, () => {
  activeMenu.value = null
})
</script>

<style scoped>
.hover-menu-container {
  position: fixed;
  left: 0;
  top: 0;
  bottom: 0;
  z-index: 5000;
  pointer-events: none;
}

.primary-menu {
  width: 140px;
  height: 100vh;
  background: linear-gradient(180deg, #45455e 0%, #41425a 100%);
  display: flex;
  flex-direction: column;
  align-items: stretch;
  padding-top: 8px;
  box-shadow: 2px 0 8px rgba(0, 0, 0, 0.1);
  pointer-events: auto;
}

.logo {
  height: 52px;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  margin: 0 12px 8px;
  padding: 0 10px;
  border-radius: 8px;
  color: #f2f4ff;
  background: rgba(255, 255, 255, 0.06);
}

.logo-icon {
  font-size: 16px;
  letter-spacing: 0.5px;
  font-weight: 600;
}

.menu-item {
  height: 52px;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 10px;
  padding: 0 14px;
  margin: 2px 8px;
  border-radius: 8px;
  cursor: pointer;
  color: #d7d8e8;
  transition: all 0.3s;
  position: relative;
  overflow: visible;
}

.menu-item:hover {
  background: rgba(255, 255, 255, 0.09);
  color: #ffffff;
}

.menu-item.active {
  background: rgba(34, 39, 59, 0.85);
  color: #ffffff;
}

.menu-item.active::after {
  content: "";
  position: absolute;
  right: -8px;
  top: 50%;
  transform: translateY(-50%);
  border-top: 8px solid transparent;
  border-bottom: 8px solid transparent;
  border-left: 8px solid #ffffff;
}

.menu-title {
  font-size: 16px;
  line-height: 1;
  white-space: nowrap;
}

.submenu-panel {
  position: fixed;
  left: 140px;
  width: clamp(520px, calc(100vw - 180px), 980px);
  height: var(--submenu-panel-height);
  background: white;
  box-shadow: 8px 8px 28px rgba(0, 0, 0, 0.2);
  overflow: hidden;
  z-index: 999;
  border-radius: 0 0 6px 0;
  pointer-events: auto;
  display: flex;
  flex-direction: column;
}

.submenu-header {
  padding: 18px 26px 8px;
  border-bottom: 1px solid #f0f0f2;
  background: #ffffff;
  flex: 0 0 auto;
}

.submenu-header h3 {
  margin: 0;
  font-size: 17px;
  font-weight: 500;
  color: #2f3245;
}

.submenu-grid {
  padding: 18px 26px 22px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  grid-auto-rows: minmax(28px, auto);
  column-gap: 26px;
  row-gap: 12px;
  align-content: start;
  flex: 1 1 auto;
  overflow-y: auto;
  overflow-x: hidden;
}

.submenu-item {
  min-height: 28px;
  cursor: pointer;
  transition: color 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  color: #494d63;
  padding: 0;
  border-radius: 0;
  background: transparent;
}

.submenu-item:hover {
  color: #2f5ddb;
}

.submenu-item.active {
  color: #1f4ece;
  font-weight: 600;
}

.submenu-item :deep(.el-icon) {
  display: none;
}

.submenu-item span {
  font-size: 23px;
  line-height: 1.25;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

@media (max-width: 1440px) {
  .submenu-panel {
    width: min(860px, calc(100vw - 180px));
  }

  .submenu-item span {
    font-size: 20px;
  }
}

@media (max-width: 1200px) {
  .submenu-panel {
    width: calc(100vw - 180px);
  }

  .submenu-item span {
    font-size: 18px;
  }
}

/* 动画 */
.submenu-fade-enter-active,
.submenu-fade-leave-active {
  transition: all 0.3s ease;
}

.submenu-fade-enter-from {
  opacity: 0;
  transform: translateX(-10px);
}

.submenu-fade-leave-to {
  opacity: 0;
  transform: translateX(-10px);
}

/* 滚动条样式 */
.submenu-panel::-webkit-scrollbar {
  width: 6px;
}

.submenu-panel::-webkit-scrollbar-thumb {
  background: #dcdfe6;
  border-radius: 3px;
}

.submenu-panel::-webkit-scrollbar-thumb:hover {
  background: #c0c4cc;
}
</style>
