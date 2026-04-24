import { createRouter, createWebHistory } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { useAccountSetStore } from '@/stores/accountSet'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/Login.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/',
    component: () => import('@/layouts/MainLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'Dashboard',
        component: () => import('@/views/Dashboard.vue'),
        meta: { title: '工作台' }
      },
      {
        path: '/employees',
        name: 'Employees',
        component: () => import('@/views/Employees/index.vue'),
        meta: { title: '人员档案' }
      },
      {
        path: '/blacklist',
        name: 'Blacklist',
        component: () => import('@/views/Blacklist/index.vue'),
        meta: { title: '人员黑名单' }
      },
      {
        path: '/personnel-change-requests',
        name: 'PersonnelChangeRequests',
        component: () => import('@/views/PersonnelChangeRequests/index.vue'),
        meta: { title: '人员汇总申请' }
      },
      {
        path: '/attendance-basis',
        name: 'AttendanceBasis',
        component: () => import('@/views/AttendanceBasis/index.vue'),
        meta: { title: '考勤依据' }
      },
      {
        path: '/salary-basis',
        name: 'SalaryBasis',
        component: () => import('@/views/SalaryBasis/index.vue'),
        meta: { title: '工资依据' }
      },
      {
        path: '/projects',
        name: 'Projects',
        component: () => import('@/views/Projects/index.vue'),
        meta: { title: '项目管理' }
      },
      {
        path: '/attendance',
        name: 'Attendance',
        component: () => import('@/views/Attendance/index.vue'),
        meta: { title: '考勤管理' }
      },
      {
        path: '/salaries',
        name: 'Salaries',
        component: () => import('@/views/Salaries/index.vue'),
        meta: { title: '工资管理' }
      },
      {
        path: '/salary-summaries',
        name: 'SalarySummaries',
        component: () => import('@/views/SalarySummaries/index.vue'),
        meta: { title: '工资汇总' }
      },
      {
        path: '/salary-payment-records',
        name: 'SalaryPaymentRecords',
        component: () => import('@/views/SalaryPaymentRecords/index.vue'),
        meta: { title: '发工资表' }
      },
      {
        path: '/payroll-remarks',
        name: 'PayrollRemarks',
        component: () => import('@/views/PayrollRemarks/index.vue'),
        meta: { title: '备注事项' }
      },
      {
        path: '/special-deductions',
        name: 'SpecialDeductions',
        component: () => import('@/views/SpecialDeductions/index.vue'),
        meta: { title: '专项扣除管理' }
      },
      {
        path: '/insurance',
        name: 'Insurance',
        component: () => import('@/views/Insurance/index.vue'),
        meta: { title: '保险管理' }
      },
      {
        path: '/insurance-change',
        name: 'InsuranceChange',
        component: () => import('@/views/InsuranceChange/index.vue'),
        meta: { title: '参保增减' }
      },
      {
        path: '/process-management',
        name: 'ProcessManagement',
        component: () => import('@/views/ProcessManagement/index.vue'),
        meta: { title: '汇总申请' }
      },
      {
        path: '/bid-projects',
        name: 'BidProjects',
        component: () => import('@/views/BidProjects/index.vue'),
        meta: { title: '投标项目管理' }
      },
      {
        path: '/invoice-projects',
        name: 'InvoiceProjects',
        component: () => import('@/views/InvoiceProjects/index.vue'),
        meta: { title: '发票项目配置' }
      },
      {
        path: '/invoice-applications',
        name: 'InvoiceApplications',
        component: () => import('@/views/InvoiceApplications/index.vue'),
        meta: { title: '发票申请管理' }
      },
      {
        path: '/invoice-summary',
        name: 'InvoiceSummary',
        component: () => import('@/views/InvoiceSummary/index.vue'),
        meta: { title: '发票汇总' }
      },
      {
        path: '/payment-applications',
        name: 'PaymentApplications',
        component: () => import('@/views/PaymentApplication/index.vue'),
        meta: { title: '付款申请' }
      },
      {
        path: '/payment-summaries',
        name: 'PaymentSummaries',
        component: () => import('@/views/PaymentSummary/index.vue'),
        meta: { title: '出款汇总' }
      },
      {
        path: '/region-portals',
        name: 'RegionPortals',
        component: () => import('@/views/RegionPortal/index.vue'),
        meta: { title: '地区网页入口' }
      },
      {
        path: '/financial-software-links',
        name: 'FinancialSoftwareLinks',
        component: () => import('@/views/FinancialSoftwareLinks/index.vue'),
        meta: { title: '财务软件链接' }
      },
      {
        path: '/tax-declaration-configs',
        name: 'TaxDeclarationConfigs',
        component: () => import('@/views/TaxDeclarationConfigs/index.vue'),
        meta: { title: '税费申报配置' }
      },
      {
        path: '/insurance-compensation',
        name: 'InsuranceCompensation',
        component: () => import('@/views/InsuranceCompensation/index.vue'),
        meta: { title: '理赔管理' }
      },
      {
        path: '/tax-declaration-tasks',
        name: 'TaxDeclarationTasks',
        component: () => import('@/views/TaxDeclarationTasks/index.vue'),
        meta: { title: '税费申报任务' }
      },
      {
        path: '/delivery-configs',
        name: 'DeliveryConfigs',
        component: () => import('@/views/DocumentDelivery/ConfigList.vue'),
        meta: { title: '资料交付配置' }
      },
      {
        path: '/document-deliveries',
        name: 'DocumentDeliveries',
        component: () => import('@/views/DocumentDelivery/DeliveryList.vue'),
        meta: { title: '资料交付记录' }
      },
      {
        path: '/process-records',
        name: 'ProcessRecords',
        component: () => import('@/views/ProcessRecords.vue'),
        meta: { 
          title: '流程记录管理',
          requiresProcessRecordAccess: true // 需要登录并选择账套
        }
      },
      {
        path: '/social-security',
        name: 'SocialSecurity',
        component: () => import('@/views/SocialSecurity/index.vue'),
        meta: { title: '社保管理' }
      },
      {
        path: '/housing-fund',
        name: 'HousingFund',
        component: () => import('@/views/HousingFund/index.vue'),
        meta: { title: '公积金管理' }
      },
      {
        path: '/other-insurance',
        name: 'OtherInsurance',
        component: () => import('@/views/OtherInsurance/index.vue'),
        meta: { title: '其他保险管理' }
      },
      {
        path: '/large-medical-insurance',
        name: 'LargeMedicalInsurance',
        component: () => import('@/views/SocialSecurity/index.vue'),
        meta: { title: '医保与大额医疗' }
      },
      {
        path: '/base-adjustment',
        name: 'BaseAdjustment',
        component: () => import('@/views/BaseAdjustment/index.vue'),
        meta: { title: '基数调差管理' }
      },
      {
        path: '/insurance-surrenders',
        name: 'InsuranceSurrenders',
        component: () => import('@/views/InsuranceSurrender/index.vue'),
        meta: { title: '商业险管理' }
      },
      {
        path: '/assessment',
        name: 'Assessment',
        component: () => import('@/views/Assessment/index.vue'),
        meta: { title: '考核记录' }
      },
      {
        path: '/approvals',
        name: 'Approvals',
        component: () => import('@/views/Approvals/index.vue'),
        meta: { title: '审批管理' }
      },
      {
        path: '/pending-tasks',
        name: 'PendingTasks',
        component: () => import('@/views/PendingTasks/index.vue'),
        meta: { title: '待办任务' }
      },
      {
        path: '/payments',
        name: 'Payments',
        component: () => import('@/views/Payments/index.vue'),
        meta: { title: '付款管理' }
      },
      {
        path: '/invoices',
        name: 'Invoices',
        component: () => import('@/views/Invoices/index.vue'),
        meta: { title: '发票管理' }
      },
      {
        path: '/reimbursement',
        name: 'Reimbursement',
        component: () => import('@/views/Reimbursement/index.vue'),
        meta: { title: '报销管理' }
      },
      {
        path: '/travel-application',
        name: 'TravelApplication',
        component: () => import('@/views/TravelApplication/index.vue'),
        meta: { title: '差旅申请' }
      },
      {
        path: '/recruitment',
        name: 'Recruitment',
        component: () => import('@/views/Recruitment/index.vue'),
        meta: { title: '招聘管理' }
      },
      {
        path: '/recruitment-demand',
        name: 'RecruitmentDemand',
        component: () => import('@/views/RecruitmentDemand/index.vue'),
        meta: { title: '招聘需求' }
      },
      {
        path: '/recruitment-demand/create',
        name: 'RecruitmentDemandCreate',
        component: () => import('@/views/RecruitmentDemand/Create.vue'),
        meta: { title: '新增招聘需求' }
      },
      {
        path: '/shared-files',
        name: 'SharedFiles',
        component: () => import('@/views/SharedFiles/index.vue'),
        meta: { title: '共享中心' }
      },
      {
        path: '/material-assets',
        name: 'MaterialAssets',
        component: () => import('@/views/MaterialAssets/index.vue'),
        meta: { title: '资料管理' }
      },
      {
        path: '/material-requests',
        name: 'MaterialRequests',
        component: () => import('@/views/MaterialRequests/index.vue'),
        meta: { title: '资料申请' }
      },
      {
        path: '/users',
        name: 'Users',
        component: () => import('@/views/Users/index.vue'),
        meta: { title: '用户管理', requiresAdmin: true }
      },
      {
        path: '/role-permissions',
        name: 'RolePermissions',
        component: () => import('@/views/Permissions/RolePermissions.vue'),
        meta: { title: '角色权限管理', requiresAdmin: true }
      },
      {
        path: '/role-menus',
        name: 'RoleMenus',
        component: () => import('@/views/Permissions/RoleMenus.vue'),
        meta: { title: '角色菜单配置', requiresAdmin: true }
      },
      {
        path: '/account-sets',
        name: 'AccountSets',
        component: () => import('@/views/AccountSets/index.vue'),
        meta: { title: '账套管理', requiresAdmin: true }
      },
      {
        path: '/settings',
        name: 'Settings',
        component: () => import('@/views/Settings/index.vue'),
        meta: { title: '系统设置' }
      },
      {
        path: '/payment-reminder',
        name: 'PaymentReminder',
        component: () => import('@/views/Settings/PaymentReminder.vue'),
        meta: { title: '缴费提醒配置' }
      },
      {
        path: '/signature-management',
        name: 'SignatureManagement',
        component: () => import('@/views/SignatureManagement/index.vue'),
        meta: { title: '签名印章管理' }
      },
      {
        path: '/operation-logs',
        name: 'OperationLogs',
        component: () => import('@/views/OperationLogs/index.vue'),
        meta: { title: '操作记录' }
      },
      {
        path: '/test-integration',
        name: 'TestIntegration',
        component: () => import('@/views/TestIntegration.vue'),
        meta: { title: '系统测试' }
      }
    ]
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// 路由守卫
router.beforeEach((to, from, next) => {
  const userStore = useUserStore()
  
  // 检查是否需要登录
  if (to.meta.requiresAuth && !userStore.isLoggedIn) {
    next('/login')
    return
  }
  
  // 检查是否需要管理员权限
  if (to.meta.requiresAdmin && userStore.userInfo?.role !== 'admin') {
    console.warn('需要管理员权限，当前角色:', userStore.userInfo?.role)
    next('/')
    return
  }
  
  // 检查是否需要流程记录访问权限（已移除审批级别限制）
  if (to.meta.requiresProcessRecordAccess) {
    const user = userStore.userInfo
    const accountSetStore = useAccountSetStore()
    
    if (!user || !accountSetStore.currentAccountSet?.id) {
      console.warn('流程记录权限检查：用户未登录或未选择账套')
      next('/')
      return
    }
    
    // 已登录且选择了账套即可访问
    next()
    return
  }
  
  // 已登录用户访问登录页，重定向到首页
  if (to.path === '/login' && userStore.isLoggedIn) {
    next('/')
    return
  }
  
  next()
})

export default router
