import { test, expect, APIRequestContext, APIResponse } from '@playwright/test';

const BASE_URL = process.env.API_BASE_URL || 'http://localhost:8000/api';
const USERNAME = process.env.E2E_USERNAME || 'admin';
const PASSWORD = process.env.E2E_PASSWORD || '123456';
const ACCOUNT_SET_ID = Number(process.env.E2E_ACCOUNT_SET_ID || 1);
const RESPONSE_TIME_LIMIT = Number(process.env.RESPONSE_TIME_LIMIT || 5000);

type ReminderStatusType = 'expired' | 'expiring_soon';

interface LoginResponse {
  success: boolean;
  data: {
    token: string;
  };
  message: string;
}

interface ExpiredIdCardEmployee {
  id: number;
  name: string;
  employee_number: string | null;
  id_number: string | null;
  id_card_valid_until: string;
  expired_days: number;
  status_type: ReminderStatusType;
  status_label: string;
  days_label: string;
}

interface ExpiredIdCardsResponse {
  success: boolean;
  data: ExpiredIdCardEmployee[];
  count: number;
  expired_count: number;
  expiring_soon_count: number;
}

function assertJsonResponse(response: APIResponse) {
  expect(response.headers()['content-type']).toContain('application/json');
}

function authHeaders(token: string) {
  return {
    Authorization: `Bearer ${token}`,
    'X-Account-Set-Id': String(ACCOUNT_SET_ID),
  };
}

async function login(request: APIRequestContext): Promise<string> {
  const response = await request.post(`${BASE_URL}/auth/login`, {
    data: { username: USERNAME, password: PASSWORD },
  });

  expect(response.status()).toBe(200);
  assertJsonResponse(response);

  const body = (await response.json()) as LoginResponse;
  expect(body.success).toBe(true);
  expect(body.data.token).toBeTruthy();

  return body.data.token;
}

class EmployeeReminderApiClient {
  constructor(
    private readonly request: APIRequestContext,
    private readonly token: string
  ) {}

  async getExpiredIdCards() {
    const startedAt = Date.now();
    const response = await this.request.get(`${BASE_URL}/employees/expired-id-cards`, {
      headers: authHeaders(this.token),
      params: {
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });
    const elapsed = Date.now() - startedAt;
    const body = (await response.json()) as ExpiredIdCardsResponse;

    return { response, body, elapsed };
  }

  async listActiveEmployees(perPage = 10) {
    const response = await this.request.get(`${BASE_URL}/employees`, {
      headers: authHeaders(this.token),
      params: {
        current_account_set_id: ACCOUNT_SET_ID,
        personnel_status: 'active',
        per_page: perPage,
      },
    });

    const body = await response.json();
    return { response, body };
  }

  async updateEmployee(id: number, data: Record<string, unknown>) {
    const response = await this.request.put(`${BASE_URL}/employees/${id}`, {
      headers: authHeaders(this.token),
      data,
    });

    const body = await response.json();
    return { response, body };
  }
}

function atStartOfDay(date: Date) {
  return new Date(date.getFullYear(), date.getMonth(), date.getDate());
}

function normalizeDateOnly(date: string) {
  if (date.includes('T')) {
    return formatDateOnly(new Date(date));
  }

  return date.slice(0, 10);
}

function parseDateOnly(date: string) {
  return atStartOfDay(new Date(`${normalizeDateOnly(date)}T00:00:00`));
}

function addOneMonth(date: Date) {
  return new Date(date.getFullYear(), date.getMonth() + 1, date.getDate());
}

function diffInDays(from: Date, to: Date) {
  return Math.round((to.getTime() - from.getTime()) / (24 * 60 * 60 * 1000));
}

function formatDateOnly(date: Date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

test.describe('身份证到期提醒 API', () => {
  test('未登录请求返回 401', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/employees/expired-id-cards`, {
      params: {
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });

    expect(response.status()).toBe(401);
  });

  test('返回一个月内到期/已过期的员工列表与统计', async ({ request }) => {
    const token = await login(request);
    const api = new EmployeeReminderApiClient(request, token);

    const { response, body, elapsed } = await api.getExpiredIdCards();

    expect(response.status()).toBe(200);
    expect(elapsed).toBeLessThan(RESPONSE_TIME_LIMIT);
    assertJsonResponse(response);

    expect(body.success).toBe(true);
    expect(Array.isArray(body.data)).toBe(true);
    expect(typeof body.count).toBe('number');
    expect(typeof body.expired_count).toBe('number');
    expect(typeof body.expiring_soon_count).toBe('number');
    expect(body.count).toBe(body.data.length);
    expect(body.count).toBe(body.expired_count + body.expiring_soon_count);

    const today = atStartOfDay(new Date());
    const warningDate = addOneMonth(today);

    let expiredCount = 0;
    let expiringSoonCount = 0;

    for (const item of body.data) {
      expect(item.id).toBeTruthy();
      expect(typeof item.name).toBe('string');
      expect(item.name.length).toBeGreaterThan(0);
      expect(typeof item.id_card_valid_until).toBe('string');
      expect(typeof item.expired_days).toBe('number');
      expect(['expired', 'expiring_soon']).toContain(item.status_type);
      expect(typeof item.status_label).toBe('string');
      expect(item.status_label.length).toBeGreaterThan(0);
      expect(typeof item.days_label).toBe('string');
      expect(item.days_label.length).toBeGreaterThan(0);

      const expiryDate = parseDateOnly(item.id_card_valid_until);
      expect(expiryDate.getTime()).toBeLessThanOrEqual(warningDate.getTime());

      if (expiryDate.getTime() < today.getTime()) {
        const expiredDays = diffInDays(expiryDate, today);
        expiredCount += 1;

        expect(item.status_type).toBe('expired');
        expect(item.status_label).toBe('已过期');
        expect(item.expired_days).toBe(expiredDays);
        expect(item.days_label).toBe(`已过期 ${expiredDays} 天`);
      } else if (expiryDate.getTime() === today.getTime()) {
        expiringSoonCount += 1;

        expect(item.status_type).toBe('expiring_soon');
        expect(item.status_label).toBe('今日到期');
        expect(item.expired_days).toBe(0);
        expect(item.days_label).toBe('今日到期');
      } else {
        const remainingDays = diffInDays(today, expiryDate);
        expiringSoonCount += 1;

        expect(item.status_type).toBe('expiring_soon');
        expect(item.status_label).toBe('即将到期');
        expect(item.expired_days).toBe(remainingDays);
        expect(item.days_label).toBe(`剩余 ${remainingDays} 天`);
      }
    }

    expect(body.expired_count).toBe(expiredCount);
    expect(body.expiring_soon_count).toBe(expiringSoonCount);

    for (let index = 0; index < body.data.length - 1; index += 1) {
      expect(
        normalizeDateOnly(body.data[index].id_card_valid_until) <=
          normalizeDateOnly(body.data[index + 1].id_card_valid_until)
      ).toBe(true);
    }
  });

  test('临时设置员工身份证有效期后，应出现在提醒列表中', async ({ request }) => {
    const token = await login(request);
    const api = new EmployeeReminderApiClient(request, token);
    const employeeList = await api.listActiveEmployees(1);

    expect(employeeList.response.status()).toBe(200);
    expect(employeeList.body.success).toBe(true);
    expect(Array.isArray(employeeList.body.data?.data)).toBe(true);

    const employee = employeeList.body.data.data[0];
    if (!employee) {
      test.skip();
      return;
    }

    const targetExpiry = formatDateOnly(
      atStartOfDay(new Date(Date.now() + 7 * 24 * 60 * 60 * 1000))
    );

    try {
      const updateResult = await api.updateEmployee(employee.id, {
        id_card_valid_until: targetExpiry,
      });

      expect(updateResult.response.status()).toBe(200);
      expect(updateResult.body.success).toBe(true);

      const reminderResult = await api.getExpiredIdCards();
      expect(reminderResult.response.status()).toBe(200);

      const matchedEmployee = reminderResult.body.data.find((item) => item.id === employee.id);
      expect(matchedEmployee).toBeTruthy();
      expect(normalizeDateOnly(matchedEmployee!.id_card_valid_until)).toBe(targetExpiry);
      expect(matchedEmployee?.status_type).toBe('expiring_soon');
      expect(matchedEmployee?.status_label).toBe('即将到期');
      expect(matchedEmployee?.expired_days).toBe(7);
      expect(matchedEmployee?.days_label).toBe('剩余 7 天');
    } finally {
      const restoreResult = await api.updateEmployee(employee.id, {
        id_card_valid_until: employee.id_card_valid_until ?? null,
      });

      expect(restoreResult.response.status()).toBe(200);
      expect(restoreResult.body.success).toBe(true);
    }
  });
});
