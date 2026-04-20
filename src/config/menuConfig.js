// 菜单配置
export const menuConfig = [
  {
    id: 'home',
    title: '首页',
    icon: 'House',
    path: '/',
    requireAuth: false
  },
  {
    id: 'personnel',
    title: '人员管理',
    icon: 'User',
    requireAuth: false,
    children: [
      { title: '人员档案', path: '/employees', icon: 'User' },
      { title: '人员黑名单', path: '/blacklist', icon: 'CircleClose' },
      { title: '人员汇总申请', path: '/personnel-change-requests', icon: 'UserFilled', requireAccountSet: true },
      { title: '招聘需求', path: '/recruitment-demand', icon: 'Document', requireAccountSet: true },
      { title: '招聘管理', path: '/recruitment', icon: 'UserFilled', requireAccountSet: true }
    ]
  },
  {
    id: 'salary',
    title: '薪酬管理',
    icon: 'Money',
    requireBusiness: true,
    children: [
      { title: '考勤管理', path: '/attendance', icon: 'Calendar' },
      { title: '工资管理', path: '/salaries', icon: 'Money' },
      { title: '工资汇总', path: '/salary-summaries', icon: 'Document' },
      { title: '发工资表', path: '/salary-payment-records', icon: 'Document' }
    ]
  },
  {
    id: 'insurance',
    title: '保险管理',
    icon: 'FirstAidKit',
    requireBusiness: true,
    children: [
      { title: '参保增减', path: '/insurance-change', icon: 'Document' },
      { title: '商业险管理', path: '/insurance-surrenders', icon: 'Document', permission: 'insurance_surrender.view' },
      { title: '汇总申请', path: '/process-management', icon: 'Checked' }
    ]
  },
  {
    id: 'finance',
    title: '财务管理',
    icon: 'Wallet',
    requireBusiness: true,
    children: [
      { title: '付款申请', path: '/payment-applications', icon: 'DocumentChecked' },
      { title: '出款汇总', path: '/payment-summaries', icon: 'List' },
      { title: '发票申请', path: '/invoice-applications', icon: 'Document' },
      { title: '发票汇总', path: '/invoice-summary', icon: 'List' },
      { title: '报销管理', path: '/reimbursement', icon: 'Wallet' },
      { title: '差旅申请', path: '/travel-application', icon: 'Suitcase' },
      { title: '税费申报任务', path: '/tax-declaration-tasks', icon: 'Document', skipPermissionCheck: true },
      { title: '理赔管理', path: '/insurance-compensation', icon: 'FirstAidKit', skipPermissionCheck: true }
    ]
  },
  {
    id: 'approval',
    title: '流程中心',
    icon: 'Checked',
    requireBusiness: true,
    children: [
      { title: '审批管理', path: '/approvals', icon: 'Checked', skipPermissionCheck: true },
      { title: '待办任务', path: '/pending-tasks', icon: 'List', skipPermissionCheck: true },
      { title: '资料交付', path: '/document-deliveries', icon: 'DocumentCopy', skipPermissionCheck: true },
      { title: '资料申请', path: '/material-requests', icon: 'Document', skipPermissionCheck: true },
      { title: '地区网页入口', path: '/region-portals', icon: 'Link', skipPermissionCheck: true }
    ]
  },
  {
    id: 'settings',
    title: '系统设置',
    icon: 'Setting',
    children: [
      { title: '操作记录', path: '/operation-logs', icon: 'Document' },
      { title: '用户管理', path: '/users', icon: 'UserFilled' },
      { title: '角色权限', path: '/role-permissions', icon: 'Key' },
      { title: '角色菜单', path: '/role-menus', icon: 'Menu' },
      { title: '账套管理', path: '/account-sets', icon: 'Box' },
      { title: '签名印章管理', path: '/signature-management', icon: 'Edit' },
      { title: '流程记录管理', path: '/process-records', icon: 'Document', requireProcessRecord: true },
      { title: '考核记录', path: '/assessment', icon: 'DocumentChecked' },
      { title: '项目管理', path: '/projects', icon: 'Folder' },
      { title: '投标项目管理', path: '/bid-projects', icon: 'Document' },
      { title: '社保管理', path: '/social-security', icon: 'UserFilled' },
      { title: '公积金管理', path: '/housing-fund', icon: 'House' },
      { title: '其他保险管理', path: '/other-insurance', icon: 'Document' },
      { title: '大额医疗保险', path: '/large-medical-insurance', icon: 'Document' },
      { title: '基数调差管理', path: '/base-adjustment', icon: 'Edit' },
      { title: '专项扣除管理', path: '/special-deductions', icon: 'DocumentChecked' },
      { title: '发票项目配置', path: '/invoice-projects', icon: 'Setting', requireInvoice: true },
      { title: '考勤依据', path: '/attendance-basis', icon: 'Tickets', notForLevel1: true },
      { title: '工资依据', path: '/salary-basis', icon: 'Money', notForLevel1: true },
      { title: '交付配置', path: '/delivery-configs', icon: 'Setting', requireDelivery: true },
      { title: '共享中心', path: '/shared-files', icon: 'FolderOpened' },
      { title: '资料管理', path: '/material-assets', icon: 'Files' },
      { title: '缴费提醒配置', path: '/payment-reminder', icon: 'Bell' },
      { title: '财务软件链接', path: '/financial-software-links', icon: 'Link', skipPermissionCheck: true },
      { title: '税费申报配置', path: '/tax-declaration-configs', icon: 'Setting', skipPermissionCheck: true },
      { title: '系统设置', path: '/settings', icon: 'Setting' }
    ]
  }
]
