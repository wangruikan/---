const express = require('express');
const cors = require('cors');
const app = express();
const PORT = 8000;

// 中间件
app.use(cors());
app.use(express.json());

// 模拟数据
const mockData = {
  users: [
    {
      id: 1,
      name: '系统管理员',
      email: 'admin@hrm.com',
      role: 'admin',
      created_at: '2024-01-01 10:00:00'
    }
  ],
  projects: [
    { id: 1, name: '项目A', code: 'PROJ001', status: 'active' },
    { id: 2, name: '项目B', code: 'PROJ002', status: 'active' },
    { id: 3, name: '项目C', code: 'PROJ003', status: 'completed' }
  ],
  employees: [
    {
      id: 1,
      name: '张三',
      id_number: '110101199001011234',
      phone: '13800138001',
      email: 'zhangsan@example.com',
      gender: 'male',
      birth_date: '1990-01-01',
      hire_date: '2024-01-01',
      contract_start_date: '2024-01-01',
      contract_end_date: '2024-12-31',
      contract_status: 'active',
      address: '北京市朝阳区',
      projects: [{ id: 1, name: '项目A' }],
      created_at: '2024-01-01 10:00:00'
    },
    {
      id: 2,
      name: '李四',
      id_number: '110101199002021234',
      phone: '13800138002',
      email: 'lisi@example.com',
      gender: 'female',
      birth_date: '1990-02-02',
      hire_date: '2024-01-15',
      contract_start_date: '2024-01-15',
      contract_end_date: '2024-12-31',
      contract_status: 'active',
      address: '北京市海淀区',
      projects: [{ id: 2, name: '项目B' }],
      created_at: '2024-01-15 10:00:00'
    }
  ],
  attendanceSheets: [
    {
      id: 1,
      project_name: '项目A',
      month: '2024-01',
      status: 'approved',
      total_employees: 5,
      submitted_at: '2024-01-31 18:00:00',
      approved_at: '2024-02-01 09:00:00'
    }
  ],
  attendanceRecords: [
    {
      id: 1,
      employee_name: '张三',
      project_name: '项目A',
      date: '2024-01-15',
      check_in_time: '09:00',
      check_out_time: '18:00',
      work_hours: 8,
      overtime_hours: 0,
      status: 'normal',
      notes: ''
    }
  ],
  salaries: [
    {
      id: 1,
      employee_name: '张三',
      project_name: '项目A',
      month: '2024-01',
      basic_salary: 8000,
      overtime_pay: 500,
      bonus: 1000,
      deductions: 200,
      net_salary: 9300,
      status: 'approved',
      created_at: '2024-01-31 10:00:00'
    }
  ],
  insuranceRecords: [
    {
      id: 1,
      employee_name: '张三',
      project_name: '项目A',
      insurance_type: '养老保险',
      company_amount: 800,
      personal_amount: 400,
      total_amount: 1200,
      payment_date: '2024-01-15',
      due_date: '2024-02-15',
      status: 'paid'
    }
  ],
  approvals: [
    {
      id: 1,
      type: 'leave',
      title: '请假申请',
      applicant_name: '张三',
      project_name: '项目A',
      amount: 0,
      start_date: '2024-01-20',
      end_date: '2024-01-22',
      status: 'approved',
      created_at: '2024-01-19 10:00:00'
    }
  ],
  payments: [
    {
      id: 1,
      project_name: '项目A',
      type: 'salary',
      amount: 50000,
      description: '1月份工资发放',
      payment_date: '2024-02-01',
      status: 'paid',
      created_at: '2024-01-31 10:00:00'
    }
  ],
  invoices: [
    {
      id: 1,
      project_name: '项目A',
      invoice_number: 'INV202401001',
      type: 'vat_ordinary',
      amount: 100000,
      tax_amount: 13000,
      total_amount: 113000,
      issue_date: '2024-01-31',
      status: 'issued',
      created_at: '2024-01-31 10:00:00'
    }
  ],
  recruitments: [
    {
      id: 1,
      position: '前端开发工程师',
      project_name: '项目A',
      department: '技术部',
      recruitment_count: 2,
      applied_count: 5,
      interviewed_count: 3,
      hired_count: 1,
      salary_range: '8000-12000',
      work_location: '北京',
      education: 'bachelor',
      experience: '2年以上',
      status: 'active',
      created_at: '2024-01-01 10:00:00'
    }
  ]
};

// 通用分页响应
const paginate = (data, page = 1, per_page = 20) => {
  const start = (page - 1) * per_page;
  const end = start + per_page;
  const items = data.slice(start, end);
  
  return {
    success: true,
    data: {
      data: items,
      current_page: parseInt(page),
      per_page: parseInt(per_page),
      total: data.length,
      last_page: Math.ceil(data.length / per_page)
    }
  };
};

// 路由定义

// 用户相关
app.get('/api/user', (req, res) => {
  res.json({ success: true, data: mockData.users[0] });
});

// 员工管理
app.get('/api/employees', (req, res) => {
  const { page = 1, per_page = 20, search, project_id, contract_status } = req.query;
  let employees = [...mockData.employees];
  
  // 搜索过滤
  if (search) {
    employees = employees.filter(emp => 
      emp.name.includes(search) || emp.id_number.includes(search)
    );
  }
  
  // 项目过滤
  if (project_id) {
    employees = employees.filter(emp => 
      emp.projects.some(p => p.id == project_id)
    );
  }
  
  // 合同状态过滤
  if (contract_status) {
    employees = employees.filter(emp => emp.contract_status === contract_status);
  }
  
  res.json(paginate(employees, page, per_page));
});

app.post('/api/employees', (req, res) => {
  const newEmployee = {
    id: mockData.employees.length + 1,
    ...req.body,
    created_at: new Date().toISOString()
  };
  mockData.employees.push(newEmployee);
  res.json({ success: true, message: '员工创建成功', data: newEmployee });
});

app.put('/api/employees/:id', (req, res) => {
  const id = parseInt(req.params.id);
  const index = mockData.employees.findIndex(emp => emp.id === id);
  if (index !== -1) {
    mockData.employees[index] = { ...mockData.employees[index], ...req.body };
    res.json({ success: true, message: '员工更新成功', data: mockData.employees[index] });
  } else {
    res.status(404).json({ success: false, message: '员工不存在' });
  }
});

app.delete('/api/employees/:id', (req, res) => {
  const id = parseInt(req.params.id);
  const index = mockData.employees.findIndex(emp => emp.id === id);
  if (index !== -1) {
    mockData.employees.splice(index, 1);
    res.json({ success: true, message: '员工删除成功' });
  } else {
    res.status(404).json({ success: false, message: '员工不存在' });
  }
});

// 项目管理
app.get('/api/projects', (req, res) => {
  const { page = 1, per_page = 20, search, status } = req.query;
  let projects = [...mockData.projects];
  
  if (search) {
    projects = projects.filter(proj => 
      proj.name.includes(search) || proj.code.includes(search)
    );
  }
  
  if (status) {
    projects = projects.filter(proj => proj.status === status);
  }
  
  res.json(paginate(projects, page, per_page));
});

app.post('/api/projects', (req, res) => {
  const newProject = {
    id: mockData.projects.length + 1,
    ...req.body,
    created_at: new Date().toISOString()
  };
  mockData.projects.push(newProject);
  res.json({ success: true, message: '项目创建成功', data: newProject });
});

app.put('/api/projects/:id', (req, res) => {
  const id = parseInt(req.params.id);
  const index = mockData.projects.findIndex(proj => proj.id === id);
  if (index !== -1) {
    mockData.projects[index] = { ...mockData.projects[index], ...req.body };
    res.json({ success: true, message: '项目更新成功', data: mockData.projects[index] });
  } else {
    res.status(404).json({ success: false, message: '项目不存在' });
  }
});

app.delete('/api/projects/:id', (req, res) => {
  const id = parseInt(req.params.id);
  const index = mockData.projects.findIndex(proj => proj.id === id);
  if (index !== -1) {
    mockData.projects.splice(index, 1);
    res.json({ success: true, message: '项目删除成功' });
  } else {
    res.status(404).json({ success: false, message: '项目不存在' });
  }
});

// 考勤管理
app.get('/api/attendance/sheets', (req, res) => {
  res.json({ success: true, data: { data: mockData.attendanceSheets } });
});

app.get('/api/attendance', (req, res) => {
  const { page = 1, per_page = 20 } = req.query;
  res.json(paginate(mockData.attendanceRecords, page, per_page));
});

app.post('/api/attendance/sheets', (req, res) => {
  const newSheet = {
    id: mockData.attendanceSheets.length + 1,
    ...req.body,
    status: 'draft',
    created_at: new Date().toISOString()
  };
  mockData.attendanceSheets.push(newSheet);
  res.json({ success: true, message: '考勤表创建成功', data: newSheet });
});

app.post('/api/attendance/sheets/:id/submit', (req, res) => {
  res.json({ success: true, message: '考勤表提交成功' });
});

app.post('/api/attendance/sheets/:id/approve', (req, res) => {
  res.json({ success: true, message: '考勤表审批成功' });
});

app.post('/api/attendance', (req, res) => {
  const newRecord = {
    id: mockData.attendanceRecords.length + 1,
    ...req.body,
    created_at: new Date().toISOString()
  };
  mockData.attendanceRecords.push(newRecord);
  res.json({ success: true, message: '考勤记录创建成功', data: newRecord });
});

app.put('/api/attendance/:id', (req, res) => {
  res.json({ success: true, message: '考勤记录更新成功' });
});

app.delete('/api/attendance/:id', (req, res) => {
  res.json({ success: true, message: '考勤记录删除成功' });
});

// 薪金管理
app.get('/api/salaries', (req, res) => {
  const { page = 1, per_page = 20 } = req.query;
  res.json(paginate(mockData.salaries, page, per_page));
});

app.post('/api/salaries', (req, res) => {
  const newSalary = {
    id: mockData.salaries.length + 1,
    ...req.body,
    status: 'draft',
    created_at: new Date().toISOString()
  };
  mockData.salaries.push(newSalary);
  res.json({ success: true, message: '薪金记录创建成功', data: newSalary });
});

app.put('/api/salaries/:id', (req, res) => {
  res.json({ success: true, message: '薪金记录更新成功' });
});

app.delete('/api/salaries/:id', (req, res) => {
  res.json({ success: true, message: '薪金记录删除成功' });
});

app.post('/api/salaries/batch', (req, res) => {
  res.json({ success: true, message: '批量创建薪金记录成功' });
});

app.post('/api/salaries/:id/submit', (req, res) => {
  res.json({ success: true, message: '薪金记录提交成功' });
});

app.post('/api/salaries/:id/approve', (req, res) => {
  res.json({ success: true, message: '薪金记录审批成功' });
});

app.post('/api/salaries/:id/reject', (req, res) => {
  res.json({ success: true, message: '薪金记录拒绝成功' });
});

// 保险管理
app.get('/api/insurance', (req, res) => {
  const { page = 1, per_page = 20 } = req.query;
  res.json(paginate(mockData.insuranceRecords, page, per_page));
});

app.post('/api/insurance', (req, res) => {
  const newRecord = {
    id: mockData.insuranceRecords.length + 1,
    ...req.body,
    status: 'pending',
    created_at: new Date().toISOString()
  };
  mockData.insuranceRecords.push(newRecord);
  res.json({ success: true, message: '保险记录创建成功', data: newRecord });
});

app.put('/api/insurance/:id', (req, res) => {
  res.json({ success: true, message: '保险记录更新成功' });
});

app.delete('/api/insurance/:id', (req, res) => {
  res.json({ success: true, message: '保险记录删除成功' });
});

app.post('/api/insurance/:id/complete', (req, res) => {
  res.json({ success: true, message: '保险记录标记完成成功' });
});

// 审批管理
app.get('/api/approvals', (req, res) => {
  const { page = 1, per_page = 20 } = req.query;
  res.json(paginate(mockData.approvals, page, per_page));
});

app.post('/api/approvals', (req, res) => {
  const newApproval = {
    id: mockData.approvals.length + 1,
    ...req.body,
    status: 'pending',
    created_at: new Date().toISOString()
  };
  mockData.approvals.push(newApproval);
  res.json({ success: true, message: '审批申请创建成功', data: newApproval });
});

app.put('/api/approvals/:id', (req, res) => {
  res.json({ success: true, message: '审批申请更新成功' });
});

app.delete('/api/approvals/:id', (req, res) => {
  res.json({ success: true, message: '审批申请删除成功' });
});

app.post('/api/approvals/:id/approve', (req, res) => {
  res.json({ success: true, message: '审批通过成功' });
});

app.post('/api/approvals/:id/reject', (req, res) => {
  res.json({ success: true, message: '审批拒绝成功' });
});

app.post('/api/approvals/:id/return', (req, res) => {
  res.json({ success: true, message: '审批退回成功' });
});

// 付款管理
app.get('/api/payments', (req, res) => {
  const { page = 1, per_page = 20 } = req.query;
  res.json(paginate(mockData.payments, page, per_page));
});

app.post('/api/payments', (req, res) => {
  const newPayment = {
    id: mockData.payments.length + 1,
    ...req.body,
    status: 'draft',
    created_at: new Date().toISOString()
  };
  mockData.payments.push(newPayment);
  res.json({ success: true, message: '付款记录创建成功', data: newPayment });
});

app.put('/api/payments/:id', (req, res) => {
  res.json({ success: true, message: '付款记录更新成功' });
});

app.delete('/api/payments/:id', (req, res) => {
  res.json({ success: true, message: '付款记录删除成功' });
});

app.post('/api/payments/:id/submit', (req, res) => {
  res.json({ success: true, message: '付款记录提交成功' });
});

app.post('/api/payments/:id/approve', (req, res) => {
  res.json({ success: true, message: '付款记录审批成功' });
});

app.post('/api/payments/:id/pay', (req, res) => {
  res.json({ success: true, message: '付款成功' });
});

// 发票管理
app.get('/api/invoices', (req, res) => {
  const { page = 1, per_page = 20 } = req.query;
  res.json(paginate(mockData.invoices, page, per_page));
});

app.post('/api/invoices', (req, res) => {
  const newInvoice = {
    id: mockData.invoices.length + 1,
    ...req.body,
    status: 'draft',
    created_at: new Date().toISOString()
  };
  mockData.invoices.push(newInvoice);
  res.json({ success: true, message: '发票记录创建成功', data: newInvoice });
});

app.put('/api/invoices/:id', (req, res) => {
  res.json({ success: true, message: '发票记录更新成功' });
});

app.delete('/api/invoices/:id', (req, res) => {
  res.json({ success: true, message: '发票记录删除成功' });
});

app.post('/api/invoices/:id/submit', (req, res) => {
  res.json({ success: true, message: '发票记录提交成功' });
});

app.post('/api/invoices/:id/approve', (req, res) => {
  res.json({ success: true, message: '发票记录审批成功' });
});

app.post('/api/invoices/:id/issue', (req, res) => {
  res.json({ success: true, message: '发票开票成功' });
});

// 招聘管理
app.get('/api/recruitment', (req, res) => {
  const { page = 1, per_page = 20 } = req.query;
  res.json(paginate(mockData.recruitments, page, per_page));
});

app.post('/api/recruitment', (req, res) => {
  const newRecruitment = {
    id: mockData.recruitments.length + 1,
    ...req.body,
    status: 'active',
    created_at: new Date().toISOString()
  };
  mockData.recruitments.push(newRecruitment);
  res.json({ success: true, message: '招聘需求创建成功', data: newRecruitment });
});

app.put('/api/recruitment/:id', (req, res) => {
  res.json({ success: true, message: '招聘需求更新成功' });
});

app.delete('/api/recruitment/:id', (req, res) => {
  res.json({ success: true, message: '招聘需求删除成功' });
});

app.post('/api/recruitment/:id/assign', (req, res) => {
  res.json({ success: true, message: '招聘需求分配成功' });
});

app.post('/api/recruitment/:id/update-progress', (req, res) => {
  res.json({ success: true, message: '招聘进度更新成功' });
});

app.post('/api/recruitment/:id/complete', (req, res) => {
  res.json({ success: true, message: '招聘需求完成成功' });
});

// 启动服务器
app.listen(PORT, () => {
  console.log(`🚀 模拟API服务器已启动！`);
  console.log(`📡 服务地址: http://localhost:${PORT}`);
  console.log(`🔗 API文档: http://localhost:${PORT}/api`);
  console.log(`\n📋 可用的API端点:`);
  console.log(`   GET    /api/employees     - 获取员工列表`);
  console.log(`   POST   /api/employees     - 创建员工`);
  console.log(`   PUT    /api/employees/:id - 更新员工`);
  console.log(`   DELETE /api/employees/:id - 删除员工`);
  console.log(`   GET    /api/projects      - 获取项目列表`);
  console.log(`   POST   /api/projects      - 创建项目`);
  console.log(`   ... 更多API端点`);
  console.log(`\n✨ 前端系统: http://localhost:3001`);
});
