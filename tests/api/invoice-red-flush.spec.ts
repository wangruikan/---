import { test, expect, APIRequestContext } from '@playwright/test';

const BASE_URL = 'http://localhost:8000/api';
const ACCOUNT_SET_ID = 1;

let authToken: string;
let sourceInvoiceId: number; // 用于红冲的源发票ID
let redFlushedInvoiceId: number; // 红冲创建的新发票ID

// 登录获取 token
async function login(request: APIRequestContext): Promise<string> {
  const response = await request.post(`${BASE_URL}/auth/login`, {
    data: {
      username: 'admin',
      password: '123456',
    },
  });

  expect(response.status()).toBe(200);
  const data = await response.json();
  expect(data.success).toBe(true);
  expect(data.data.token).toBeDefined();
  return data.data.token;
}

// 辅助函数：创建一个可红冲的历史发票
async function createApprovedInvoice(request: APIRequestContext, token: string): Promise<number> {
  // 先获取一个项目
  const projectsRes = await request.get(`${BASE_URL}/projects`, {
    headers: { Authorization: `Bearer ${token}` },
    params: { per_page: 1, current_account_set_id: ACCOUNT_SET_ID },
  });
  const projectsData = await projectsRes.json();
  const projectId = projectsData.data.data[0]?.id;

  // 创建发票申请
  const createRes = await request.post(`${BASE_URL}/invoice-applications`, {
    headers: { Authorization: `Bearer ${token}` },
    data: {
      task_name: '红冲测试发票-源',
      year: 2025,
      month: 6,
      project_name: '测试项目',
      project_id: projectId,
      status: 'normal',
      current_account_set_id: ACCOUNT_SET_ID,
    },
  });

  const createData = await createRes.json();
  console.log('创建发票响应:', createRes.status(), createData);
  return createData.data?.id;
}

test.describe('发票红冲 API 测试', () => {
  test.beforeAll(async ({ request }) => {
    authToken = await login(request);
    console.log('登录成功，token:', authToken.substring(0, 20) + '...');
  });

  // 测试 A: 候选列表接口可正常返回
  test('A. 获取红冲候选列表', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/invoice-applications/red-flush-candidates`, {
      headers: { Authorization: `Bearer ${authToken}` },
      params: {
        current_account_set_id: ACCOUNT_SET_ID,
        before_year: 2026,
        before_month: 6,
      },
    });

    console.log('红冲候选列表响应状态:', response.status());
    expect(response.status()).toBe(200);

    const data = await response.json();
    console.log('红冲候选列表返回数量:', data.data?.length || 0);
    expect(data.success).toBe(true);
    expect(Array.isArray(data.data)).toBe(true);

    // 验证过滤条件：所有返回的发票必须满足条件
    for (const invoice of data.data) {
      expect(invoice.approval_status).toBe('approved');
      expect(invoice.is_completed).toBe(true);
      expect(invoice.invoice_number).toBeTruthy();
      expect(invoice.status).not.toBe('red_flushed');
      // 验证年月在 before_year/before_month 之前
      const isBefore =
        invoice.year < 2026 || (invoice.year === 2026 && invoice.month < 6);
      expect(isBefore).toBe(true);
    }
  });

  // 测试 H: 候选列表 keyword 搜索
  test('H. 候选列表 keyword 搜索', async ({ request }) => {
    // 先获取不带 keyword 的列表
    const allRes = await request.get(`${BASE_URL}/invoice-applications/red-flush-candidates`, {
      headers: { Authorization: `Bearer ${authToken}` },
      params: {
        current_account_set_id: ACCOUNT_SET_ID,
        before_year: 2026,
        before_month: 6,
      },
    });
    const allData = await allRes.json();

    if (allData.data.length === 0) {
      console.log('没有候选数据，跳过 keyword 搜索测试');
      return;
    }

    // 取第一个候选的 application_no 做搜索
    const firstCandidate = allData.data[0];
    const keyword = firstCandidate.application_no?.substring(0, 5) || '';

    if (!keyword) {
      console.log('候选没有 application_no，跳过');
      return;
    }

    const searchRes = await request.get(`${BASE_URL}/invoice-applications/red-flush-candidates`, {
      headers: { Authorization: `Bearer ${authToken}` },
      params: {
        current_account_set_id: ACCOUNT_SET_ID,
        before_year: 2026,
        before_month: 6,
        keyword: keyword,
      },
    });

    const searchData = await searchRes.json();
    console.log(`keyword="${keyword}" 搜索结果数量:`, searchData.data?.length || 0);
    expect(searchRes.status()).toBe(200);
    expect(searchData.data.length).toBeGreaterThan(0);

    // 验证搜索结果中包含匹配的记录
    const found = searchData.data.some((item: any) =>
      item.application_no?.includes(keyword) ||
      item.company_name?.includes(keyword) ||
      item.invoice_number?.includes(keyword) ||
      item.project_name?.includes(keyword)
    );
    expect(found).toBe(true);
  });

  // 准备测试数据：找到一个可用的源发票
  test('准备: 找到可用的红冲源发票', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/invoice-applications/red-flush-candidates`, {
      headers: { Authorization: `Bearer ${authToken}` },
      params: {
        current_account_set_id: ACCOUNT_SET_ID,
        before_year: 2026,
        before_month: 6,
      },
    });

    const data = await response.json();
    console.log('可用候选数量:', data.data?.length || 0);

    if (data.data && data.data.length > 0) {
      sourceInvoiceId = data.data[0].id;
      console.log('选中的源发票 ID:', sourceInvoiceId);
      console.log('源发票详情:', {
        application_no: data.data[0].application_no,
        year: data.data[0].year,
        month: data.data[0].month,
        status: data.data[0].status,
        approval_status: data.data[0].approval_status,
        is_completed: data.data[0].is_completed,
        invoice_number: data.data[0].invoice_number,
      });
    } else {
      console.log('警告: 没有可用的红冲候选发票，后续测试可能跳过');
    }
  });

  // 测试 B: 成功创建红冲发票
  test('B. 成功创建红冲发票', async ({ request }) => {
    if (!sourceInvoiceId) {
      console.log('跳过: 没有可用的源发票');
      return;
    }

    // 先获取源发票详情
    const sourceRes = await request.get(`${BASE_URL}/invoice-applications/${sourceInvoiceId}`, {
      headers: { Authorization: `Bearer ${authToken}` },
    });
    const sourceData = await sourceRes.json();
    const source = sourceData.data;
    console.log('源发票详情:', {
      id: source.id,
      year: source.year,
      month: source.month,
      status: source.status,
    });

    // 创建红冲发票：年月必须大于源发票
    const redFlushYear = source.year === 2026 ? 2026 : source.year + 1;
    const redFlushMonth = source.year === 2026 ? source.month + 1 : 1;

    const createRes = await request.post(`${BASE_URL}/invoice-applications`, {
      headers: { Authorization: `Bearer ${authToken}` },
      data: {
        task_name: `红冲-${source.application_no}`,
        year: redFlushYear,
        month: redFlushMonth,
        project_name: source.project_name || '红冲测试',
        status: 'red_flushed',
        red_flush_source_id: sourceInvoiceId,
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });

    console.log('创建红冲发票响应状态:', createRes.status());
    const createData = await createRes.json();
    console.log('创建红冲发票响应:', createData);

    expect(createRes.status()).toBe(200);
    expect(createData.success).toBe(true);
    expect(createData.data.status).toBe('red_flushed');

    redFlushedInvoiceId = createData.data.id;
    console.log('红冲发票创建成功，ID:', redFlushedInvoiceId);
  });

  // 测试 C: 回查源发票状态已变成 red_flushed
  test('C. 源发票状态已变成 red_flushed', async ({ request }) => {
    if (!sourceInvoiceId) {
      console.log('跳过: 没有源发票');
      return;
    }

    const response = await request.get(`${BASE_URL}/invoice-applications/${sourceInvoiceId}`, {
      headers: { Authorization: `Bearer ${authToken}` },
    });

    const data = await response.json();
    console.log('源发票当前状态:', data.data.status);
    expect(data.data.status).toBe('red_flushed');
  });

  // 测试 D: 再次对同一个 source 发起红冲，应失败
  test('D. 重复红冲同一源发票应失败', async ({ request }) => {
    if (!sourceInvoiceId) {
      console.log('跳过: 没有源发票');
      return;
    }

    const response = await request.post(`${BASE_URL}/invoice-applications`, {
      headers: { Authorization: `Bearer ${authToken}` },
      data: {
        task_name: '重复红冲测试',
        year: 2026,
        month: 7,
        project_name: '测试项目',
        status: 'red_flushed',
        red_flush_source_id: sourceInvoiceId,
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });

    console.log('重复红冲响应状态:', response.status());
    const data = await response.json();
    console.log('重复红冲响应:', data);

    expect(response.status()).toBe(422);
    expect(data.success).toBe(false);
    expect(data.message).toBe('该发票已经是红冲状态');
  });

  // 测试 E: 缺少 red_flush_source_id 时应返回 422
  test('E. 缺少 red_flush_source_id 应返回 422', async ({ request }) => {
    const response = await request.post(`${BASE_URL}/invoice-applications`, {
      headers: { Authorization: `Bearer ${authToken}` },
      data: {
        task_name: '缺少source测试',
        year: 2026,
        month: 7,
        project_name: '测试项目',
        status: 'red_flushed',
        // 故意不传 red_flush_source_id
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });

    console.log('缺少source响应状态:', response.status());
    const data = await response.json();
    console.log('缺少source响应:', data);

    expect(response.status()).toBe(422);
    expect(data.success).toBe(false);
    expect(data.message).toBe('请选择需要红冲的发票');
  });

  // 测试 F: 使用当前月 source 或未来月 source 时应返回 422
  test('F. 使用当前月或未来月 source 应返回 422', async ({ request }) => {
    // 获取一个年月 >= 2026-06 的发票作为 source
    const candidatesRes = await request.get(`${BASE_URL}/invoice-applications`, {
      headers: { Authorization: `Bearer ${authToken}` },
      params: {
        current_account_set_id: ACCOUNT_SET_ID,
        per_page: 100,
      },
    });
    const candidatesData = await candidatesRes.json();

    // 找一个年月 >= 2026-06 的发票
    const futureInvoice = candidatesData.data?.data?.find(
      (item: any) => item.year > 2026 || (item.year === 2026 && item.month >= 6)
    );

    if (!futureInvoice) {
      console.log('跳过: 没有当前月或未来月的发票');
      return;
    }

    console.log('使用发票作为source:', {
      id: futureInvoice.id,
      year: futureInvoice.year,
      month: futureInvoice.month,
    });

    const response = await request.post(`${BASE_URL}/invoice-applications`, {
      headers: { Authorization: `Bearer ${authToken}` },
      data: {
        task_name: '未来月source测试',
        year: 2026,
        month: 6,
        project_name: '测试项目',
        status: 'red_flushed',
        red_flush_source_id: futureInvoice.id,
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });

    console.log('未来月source响应状态:', response.status());
    const data = await response.json();
    console.log('未来月source响应:', data);

    // 如果 source 年月 >= 请求年月，应该返回 422
    if (
      futureInvoice.year > 2026 ||
      (futureInvoice.year === 2026 && futureInvoice.month >= 6)
    ) {
      expect(response.status()).toBe(422);
      expect(data.message).toBe('只能选择当前月份之前的历史发票进行红冲');
    }
  });

  // 测试 G: 使用未完成/未审批/无发票号的 source 应返回 422
  test('G. 使用未完成/未审批/无发票号的 source 应返回 422', async ({ request }) => {
    // 获取一个不满足条件的发票
    const allRes = await request.get(`${BASE_URL}/invoice-applications`, {
      headers: { Authorization: `Bearer ${authToken}` },
      params: {
        current_account_set_id: ACCOUNT_SET_ID,
        per_page: 100,
      },
    });
    const allData = await allRes.json();

    // 找一个未完成或未审批或无发票号的发票
    const invalidSource = allData.data?.data?.find(
      (item: any) =>
        item.approval_status !== 'approved' ||
        !item.is_completed ||
        !item.invoice_number
    );

    if (!invalidSource) {
      console.log('跳过: 没有不满足条件的发票');
      return;
    }

    console.log('使用不满足条件的发票:', {
      id: invalidSource.id,
      approval_status: invalidSource.approval_status,
      is_completed: invalidSource.is_completed,
      invoice_number: invalidSource.invoice_number,
    });

    const response = await request.post(`${BASE_URL}/invoice-applications`, {
      headers: { Authorization: `Bearer ${authToken}` },
      data: {
        task_name: '不满足条件source测试',
        year: 2026,
        month: 7,
        project_name: '测试项目',
        status: 'red_flushed',
        red_flush_source_id: invalidSource.id,
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });

    console.log('不满足条件source响应状态:', response.status());
    const data = await response.json();
    console.log('不满足条件source响应:', data);

    expect(response.status()).toBe(422);
    expect(data.message).toBe('只能选择已完成且已通过审批的发票进行红冲');
  });

  // 测试: 使用不存在的 red_flush_source_id 应返回 404
  test('使用不存在的 red_flush_source_id 应返回 404', async ({ request }) => {
    const response = await request.post(`${BASE_URL}/invoice-applications`, {
      headers: { Authorization: `Bearer ${authToken}` },
      data: {
        task_name: '不存在source测试',
        year: 2026,
        month: 7,
        project_name: '测试项目',
        status: 'red_flushed',
        red_flush_source_id: 999999,
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });

    console.log('不存在source响应状态:', response.status());
    const data = await response.json();
    console.log('不存在source响应:', data);

    expect(response.status()).toBe(404);
    expect(data.message).toBe('红冲发票不存在');
  });
});
