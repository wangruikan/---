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

interface CreateOptionsResponse {
  success: boolean;
  data: {
    projects: Array<Record<string, unknown>>;
    invoice_projects: Array<Record<string, unknown>>;
    invoice_content_configs: Array<Record<string, unknown>>;
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

function normalizeProject(project: Record<string, unknown>) {
  return {
    id: Number(project.id),
    name: project.name,
    invoice_infos: Array.isArray(project.invoice_infos) ? project.invoice_infos : [],
  };
}

test.describe('发票申请创建选项聚合 API', () => {
  test('未登录请求返回 401', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/invoice-applications/create-options`, {
      params: {
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });

    expect(response.status()).toBe(401);
  });

  test('聚合接口与原三个接口数据一致', async ({ request }) => {
    const token = await login(request);
    const headers = authHeaders(token);

    const startedAt = Date.now();
    const createOptionsResponse = await request.get(
      `${BASE_URL}/invoice-applications/create-options`,
      {
        headers,
        params: {
          current_account_set_id: ACCOUNT_SET_ID,
        },
      }
    );
    const elapsed = Date.now() - startedAt;

    expect(createOptionsResponse.status()).toBe(200);
    expect(elapsed).toBeLessThan(RESPONSE_TIME_LIMIT);
    assertJsonResponse(createOptionsResponse);

    const createOptions = (await createOptionsResponse.json()) as CreateOptionsResponse;
    expect(createOptions.success).toBe(true);
    expect(Array.isArray(createOptions.data.projects)).toBe(true);
    expect(Array.isArray(createOptions.data.invoice_projects)).toBe(true);
    expect(Array.isArray(createOptions.data.invoice_content_configs)).toBe(true);

    const [projectsResponse, invoiceProjectsResponse, contentConfigsResponse] = await Promise.all([
      request.get(`${BASE_URL}/projects`, {
        headers,
        params: {
          all: true,
          current_account_set_id: ACCOUNT_SET_ID,
        },
      }),
      request.get(`${BASE_URL}/invoice-projects/all`, {
        headers,
        params: {
          current_account_set_id: ACCOUNT_SET_ID,
        },
      }),
      request.get(`${BASE_URL}/invoice-content-configs/all`, {
        headers,
        params: {
          current_account_set_id: ACCOUNT_SET_ID,
        },
      }),
    ]);

    expect(projectsResponse.status()).toBe(200);
    expect(invoiceProjectsResponse.status()).toBe(200);
    expect(contentConfigsResponse.status()).toBe(200);

    const projectsBody = await projectsResponse.json();
    const invoiceProjectsBody = await invoiceProjectsResponse.json();
    const contentConfigsBody = await contentConfigsResponse.json();
    const legacyProjects = Array.isArray(projectsBody.data)
      ? projectsBody.data
      : (projectsBody.data?.data || []);

    expect(createOptions.data.projects.map(normalizeProject)).toEqual(
      legacyProjects.map(normalizeProject)
    );
    expect(createOptions.data.invoice_projects).toEqual(invoiceProjectsBody.data || []);
    expect(createOptions.data.invoice_content_configs).toEqual(contentConfigsBody.data || []);
  });
});
