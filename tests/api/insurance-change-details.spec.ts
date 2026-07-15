import { test, expect, APIRequestContext } from '@playwright/test';

const BASE_URL = 'http://localhost:8000/api';
const USERNAME = process.env.E2E_USERNAME || 'admin';
const PASSWORD = process.env.E2E_PASSWORD || '123456';
const ACCOUNT_SET_ID = Number(process.env.E2E_ACCOUNT_SET_ID || 1);

let authToken: string;
let currentUserId: number;

async function login(
  request: APIRequestContext,
  username: string,
  password: string
): Promise<{ token: string; userId: number }> {
  const response = await request.post(`${BASE_URL}/auth/login`, {
    data: { username, password },
  });
  expect(response.status()).toBe(200);
  const data = await response.json();
  expect(data.success).toBe(true);
  return { token: data.data.token, userId: data.data.user?.id };
}

function authHeaders(token?: string) {
  return {
    Authorization: `Bearer ${token || authToken}`,
    'X-Account-Set-Id': String(ACCOUNT_SET_ID),
  };
}

function getCurrentYearMonth(): string {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
}

function getHistoryYearMonth(): string {
  const now = new Date();
  const d = new Date(now.getFullYear(), now.getMonth() - 2, 1);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

test.describe('参保明细 API 测试', () => {
  test.beforeAll(async ({ request }) => {
    const { token, userId } = await login(request, USERNAME, PASSWORD);
    authToken = token;
    currentUserId = userId;
  });

  // ============================================================
  // 一、当前月明细
  // ============================================================
  test.describe('一、当前月明细', () => {
    test('不传月份返回当前月数据', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/insurance-changes/details`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(Array.isArray(data.data)).toBe(true);
      expect(data.source).toBe('current_month');
    });

    test('传当前月 YYYY-MM 走实时逻辑', async ({ request }) => {
      const month = getCurrentYearMonth();
      const response = await request.get(`${BASE_URL}/insurance-changes/details`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, month },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.source).toBe('current_month');
    });

    test('当前月明细包含员工信息', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/insurance-changes/details`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID },
      });
      const data = await response.json();
      if (data.data.length === 0) {
        test.skip();
        return;
      }
      const row = data.data[0];
      expect(row).toHaveProperty('employee_id');
      expect(row).toHaveProperty('employee_name');
    });

    test('当前月明细包含各类基数字段', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/insurance-changes/details`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID },
      });
      const data = await response.json();
      if (data.data.length === 0) {
        test.skip();
        return;
      }
      const row = data.data[0];
      expect(row).toHaveProperty('employee_social_security_base');
      expect(row).toHaveProperty('employee_medical_insurance_base');
      expect(row).toHaveProperty('employee_housing_fund_base');
    });

    test('当前月明细包含金额字段', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/insurance-changes/details`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID },
      });
      const data = await response.json();
      if (data.data.length === 0) {
        test.skip();
        return;
      }
      const row = data.data[0];
      expect(row).toHaveProperty('social_security_company_amount');
      expect(row).toHaveProperty('social_security_employee_amount');
      expect(row).toHaveProperty('medical_insurance_company_amount');
      expect(row).toHaveProperty('medical_insurance_employee_amount');
      expect(row).toHaveProperty('housing_fund_company_amount');
      expect(row).toHaveProperty('housing_fund_employee_amount');
      expect(row).toHaveProperty('company_total');
      expect(row).toHaveProperty('employee_total');
    });

    test('金额字段为数字且非负', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/insurance-changes/details`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID },
      });
      const data = await response.json();
      if (data.data.length === 0) {
        test.skip();
        return;
      }
      const row = data.data[0];
      const amountFields = [
        'social_security_company_amount',
        'social_security_employee_amount',
        'medical_insurance_company_amount',
        'medical_insurance_employee_amount',
        'housing_fund_company_amount',
        'housing_fund_employee_amount',
        'company_total',
        'employee_total',
      ];
      for (const field of amountFields) {
        if (row[field] !== null && row[field] !== undefined) {
          expect(typeof row[field]).toBe('number');
          expect(row[field]).toBeGreaterThanOrEqual(0);
        }
      }
    });

    test('明细包含 employee_type 字段', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/insurance-changes/details`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID },
      });
      const data = await response.json();
      if (data.data.length === 0) {
        test.skip();
        return;
      }
      const row = data.data[0];
      expect(row).toHaveProperty('employee_type');
      expect(['正常', '补交']).toContain(row.employee_type);
    });
  });

  // ============================================================
  // 二、历史月明细
  // ============================================================
  test.describe('二、历史月明细', () => {
    test('传历史月走归档逻辑', async ({ request }) => {
      const month = getHistoryYearMonth();
      const response = await request.get(`${BASE_URL}/insurance-changes/details`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, month },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.source).toBe('historical_archive');
      expect(data.archive_month).toBeTruthy();
    });

    test('历史月明细结构合理', async ({ request }) => {
      const month = getHistoryYearMonth();
      const response = await request.get(`${BASE_URL}/insurance-changes/details`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, month },
      });
      const data = await response.json();
      if (data.data.length === 0) {
        test.skip();
        return;
      }
      const row = data.data[0];
      expect(row).toHaveProperty('employee_id');
      expect(row).toHaveProperty('employee_name');
      expect(row).toHaveProperty('employee_social_security_base');
    });
  });

  // ============================================================
  // 三、明细筛选
  // ============================================================
  test.describe('三、明细筛选', () => {
    test('按 region_name 筛选成功', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/insurance-changes/details`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, region_name: '全部' },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
    });

    test('按 project_id 筛选只返回当前项目', async ({ request }) => {
      const allResponse = await request.get(`${BASE_URL}/insurance-changes/details`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID },
      });
      const allData = await allResponse.json();
      const projectId = allData.data.find((row: any) => row.project_id)?.project_id;
      if (!projectId) {
        test.skip();
        return;
      }

      const response = await request.get(`${BASE_URL}/insurance-changes/details`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, project_id: projectId },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.data.every((row: any) => Number(row.project_id) === Number(projectId))).toBe(true);
    });

    test('缺少 account_set_id 应返回 422 或 200', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/insurance-changes/details`, {
        headers: authHeaders(),
      });
      expect([200, 400, 422]).toContain(response.status());
    });
  });

  // ============================================================
  // 四、金额计算校验
  // ============================================================
  test.describe('四、金额计算校验', () => {
    test('company_total 应等于各项公司金额之和', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/insurance-changes/details`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID },
      });
      const data = await response.json();
      const validRow = data.data.find(
        (row: any) => row.company_total > 0 && row.employee_type === '正常'
      );
      if (!validRow) {
        test.skip();
        return;
      }

      const expected =
        (validRow.social_security_company_amount || 0) +
        (validRow.medical_insurance_company_amount || 0) +
        (validRow.housing_fund_company_amount || 0) +
        (validRow.large_medical_company_amount || 0);
      expect(validRow.company_total).toBeCloseTo(expected, 2);
    });

    test('employee_total 应等于各项个人金额之和', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/insurance-changes/details`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID },
      });
      const data = await response.json();
      const validRow = data.data.find(
        (row: any) => row.employee_total > 0 && row.employee_type === '正常'
      );
      if (!validRow) {
        test.skip();
        return;
      }

      const expected =
        (validRow.social_security_employee_amount || 0) +
        (validRow.medical_insurance_employee_amount || 0) +
        (validRow.housing_fund_employee_amount || 0) +
        (validRow.large_medical_employee_amount || 0);
      expect(validRow.employee_total).toBeCloseTo(expected, 2);
    });
  });

  // ============================================================
  // 五、补交明细
  // ============================================================
  test.describe('五、补交明细', () => {
    test('补交行应有 need_ 字段标记', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/insurance-changes/details`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID },
      });
      const data = await response.json();
      const supplementRow = data.data.find((row: any) => row.employee_type === '补交');
      if (!supplementRow) {
        test.skip();
        return;
      }
      expect(supplementRow).toHaveProperty('need_social_security');
      expect(supplementRow).toHaveProperty('need_medical_insurance');
      expect(supplementRow).toHaveProperty('need_provident_fund');
      expect(supplementRow).toHaveProperty('need_large_medical');
    });
  });

  // ============================================================
  // 六、契约检查
  // ============================================================
  test.describe('六、契约检查', () => {
    test('明细接口响应时间合理', async ({ request }) => {
      const start = Date.now();
      await request.get(`${BASE_URL}/insurance-changes/details`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID },
      });
      const elapsed = Date.now() - start;
      expect(elapsed).toBeLessThan(15000);
    });
  });
});
