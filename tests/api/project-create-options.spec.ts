import { test, expect, APIRequestContext, APIResponse } from '@playwright/test';

const BASE_URL = process.env.API_BASE_URL || 'http://localhost:8000/api';
const USERNAME = process.env.E2E_USERNAME || 'admin';
const PASSWORD = process.env.E2E_PASSWORD || '123456';
const ACCOUNT_SET_ID = Number(process.env.E2E_ACCOUNT_SET_ID || 1);
const RESPONSE_TIME_LIMIT = Number(process.env.RESPONSE_TIME_LIMIT || 3000);

interface LoginResponse {
  success: boolean;
  data: {
    token: string;
  };
}

interface ProjectCreateOptionsResponse {
  success: boolean;
  data: {
    social_security_regions: Array<Record<string, unknown>>;
    housing_fund_regions: Array<Record<string, unknown>>;
    medical_insurance_regions: Array<Record<string, unknown>>;
    other_insurance_policies: Array<Record<string, unknown>>;
  };
}

function authHeaders(token: string) {
  return {
    Authorization: `Bearer ${token}`,
    'X-Account-Set-Id': String(ACCOUNT_SET_ID),
  };
}

function assertJsonResponse(response: APIResponse) {
  expect(response.headers()['content-type']).toContain('application/json');
}

async function login(request: APIRequestContext): Promise<string> {
  const response = await request.post(`${BASE_URL}/auth/login`, {
    data: {
      username: USERNAME,
      password: PASSWORD,
    },
  });

  expect(response.status()).toBe(200);
  const body = (await response.json()) as LoginResponse;
  expect(body.success).toBe(true);
  expect(body.data.token).toBeTruthy();
  return body.data.token;
}

function normalizeById(items: Array<Record<string, unknown>>, nestedKeys: string[] = []) {
  return items
    .map((item) => {
      const normalized: Record<string, unknown> = { ...item, id: Number(item.id) };
      for (const key of nestedKeys) {
        normalized[key] = Array.isArray(item[key])
          ? (item[key] as Array<Record<string, unknown>>)
              .map((nestedItem) => ({ ...nestedItem, id: Number(nestedItem.id) }))
              .sort((a, b) => Number(a.id) - Number(b.id))
          : [];
      }
      return normalized;
    })
    .sort((a, b) => Number(a.id) - Number(b.id));
}

function normalizeProjectOption(project: Record<string, unknown>) {
  return {
    id: Number(project.id),
    name: project.name,
    code: project.code ?? null,
  };
}

test.describe('项目管理创建选项聚合 API', () => {
  test('未登录请求返回 401', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/projects/create-options`, {
      params: {
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });

    expect(response.status()).toBe(401);
  });

  test('聚合接口与原项目保险选项接口数据一致', async ({ request }) => {
    const token = await login(request);
    const headers = authHeaders(token);

    const startedAt = Date.now();
    const optionsResponse = await request.get(`${BASE_URL}/projects/create-options`, {
      headers,
      params: {
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });
    const elapsed = Date.now() - startedAt;

    expect(optionsResponse.status()).toBe(200);
    expect(elapsed).toBeLessThan(RESPONSE_TIME_LIMIT);
    assertJsonResponse(optionsResponse);

    const options = (await optionsResponse.json()) as ProjectCreateOptionsResponse;
    expect(options.success).toBe(true);
    expect(Array.isArray(options.data.social_security_regions)).toBe(true);
    expect(Array.isArray(options.data.housing_fund_regions)).toBe(true);
    expect(Array.isArray(options.data.medical_insurance_regions)).toBe(true);
    expect(Array.isArray(options.data.other_insurance_policies)).toBe(true);

    const [
      socialSecurityResponse,
      housingFundResponse,
      medicalInsuranceResponse,
      otherInsuranceResponse,
    ] = await Promise.all([
      request.get(`${BASE_URL}/projects/available/social-security-regions`, {
        headers,
        params: { current_account_set_id: ACCOUNT_SET_ID },
      }),
      request.get(`${BASE_URL}/projects/available/housing-fund-regions`, {
        headers,
        params: { current_account_set_id: ACCOUNT_SET_ID },
      }),
      request.get(`${BASE_URL}/projects/available/medical-insurance-regions`, {
        headers,
        params: { current_account_set_id: ACCOUNT_SET_ID },
      }),
      request.get(`${BASE_URL}/projects/available/other-insurance-policies`, {
        headers,
        params: { current_account_set_id: ACCOUNT_SET_ID },
      }),
    ]);

    expect(socialSecurityResponse.status()).toBe(200);
    expect(housingFundResponse.status()).toBe(200);
    expect(medicalInsuranceResponse.status()).toBe(200);
    expect(otherInsuranceResponse.status()).toBe(200);

    const socialSecurityBody = await socialSecurityResponse.json();
    const housingFundBody = await housingFundResponse.json();
    const medicalInsuranceBody = await medicalInsuranceResponse.json();
    const otherInsuranceBody = await otherInsuranceResponse.json();

    expect(normalizeById(options.data.social_security_regions, ['social_security_types'])).toEqual(
      normalizeById(socialSecurityBody.data || [], ['social_security_types'])
    );
    expect(normalizeById(options.data.housing_fund_regions)).toEqual(
      normalizeById(housingFundBody.data || [])
    );
    expect(normalizeById(options.data.medical_insurance_regions, ['medical_insurance_types'])).toEqual(
      normalizeById(medicalInsuranceBody.data || [], ['medical_insurance_types'])
    );
    expect(normalizeById(options.data.other_insurance_policies)).toEqual(
      normalizeById(otherInsuranceBody.data || [])
    );
  });

  test('项目列表可同时返回筛选下拉所需的轻量项目选项', async ({ request }) => {
    const token = await login(request);
    const headers = authHeaders(token);

    const response = await request.get(`${BASE_URL}/projects`, {
      headers,
      params: {
        all: true,
        include_filter_options: true,
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });

    expect(response.status()).toBe(200);
    assertJsonResponse(response);

    const body = await response.json();
    expect(body.success).toBe(true);
    expect(Array.isArray(body.data?.data)).toBe(true);
    expect(Array.isArray(body.filter_options)).toBe(true);

    expect(body.filter_options.map(normalizeProjectOption)).toEqual(
      body.data.data.map(normalizeProjectOption)
    );
  });
});
