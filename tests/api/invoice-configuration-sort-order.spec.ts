import { expect, test, type APIRequestContext } from '@playwright/test';

const BASE_URL = process.env.API_BASE_URL || 'http://127.0.0.1:8000/api';
const USERNAME = process.env.E2E_USERNAME || 'admin';
const PASSWORD = process.env.E2E_PASSWORD || '123456';
const ACCOUNT_SET_ID = Number(process.env.E2E_ACCOUNT_SET_ID || 1);
const RUN_ID = Date.now();

interface ApiEnvelope<T> {
  success: boolean;
  message?: string;
  data: T;
}

interface ConfigItem {
  id: number;
  project_name: string;
  sort_order: number;
}

function headers(token: string) {
  return {
    Authorization: `Bearer ${token}`,
    'X-Auth-Token': token,
    'X-Account-Set-Id': String(ACCOUNT_SET_ID),
    Accept: 'application/json',
  };
}

async function ignoreDelete(request: APIRequestContext, url: string, token: string) {
  try {
    await request.delete(url, {
      headers: headers(token),
      params: { current_account_set_id: ACCOUNT_SET_ID },
      failOnStatusCode: false,
    });
  } catch {
    // Cleanup must not hide the feature assertion that failed first.
  }
}

function relativeOrder(items: ConfigItem[], ids: number[]) {
  return items.filter(item => ids.includes(Number(item.id))).map(item => Number(item.id));
}

test('发票项目和开票内容配置均可保存自定义顺序', async ({ request }) => {
  const login = await request.post(`${BASE_URL}/auth/login`, {
    data: { username: USERNAME, password: PASSWORD },
  });
  expect(login.status(), await login.text()).toBe(200);
  const token = ((await login.json()) as ApiEnvelope<{ token: string }>).data.token;

  const invoiceProjectIds: number[] = [];
  const contentConfigIds: number[] = [];

  try {
    for (const suffix of ['A', 'B']) {
      const response = await request.post(`${BASE_URL}/invoice-projects`, {
        headers: headers(token),
        data: {
          project_name: `API 排序发票项目 ${suffix} ${RUN_ID}`,
          remark: `排序测试 ${suffix}`,
          current_account_set_id: ACCOUNT_SET_ID,
        },
      });
      expect(response.status(), await response.text()).toBe(200);
      invoiceProjectIds.push(Number(((await response.json()) as ApiEnvelope<ConfigItem>).data.id));
    }

    const invoiceProjectSort = await request.post(`${BASE_URL}/invoice-projects/sort`, {
      headers: headers(token),
      data: {
        items: [
          { id: invoiceProjectIds[1], sort_order: 1 },
          { id: invoiceProjectIds[0], sort_order: 2 },
        ],
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });
    expect(invoiceProjectSort.status(), await invoiceProjectSort.text()).toBe(200);
    expect(((await invoiceProjectSort.json()) as ApiEnvelope<unknown>).success).toBe(true);

    const invoiceProjectAll = await request.get(`${BASE_URL}/invoice-projects/all`, {
      headers: headers(token),
      params: { current_account_set_id: ACCOUNT_SET_ID },
    });
    expect(invoiceProjectAll.status(), await invoiceProjectAll.text()).toBe(200);
    const invoiceProjectItems = ((await invoiceProjectAll.json()) as ApiEnvelope<ConfigItem[]>).data;
    expect(relativeOrder(invoiceProjectItems, invoiceProjectIds)).toEqual([
      invoiceProjectIds[1],
      invoiceProjectIds[0],
    ]);

    for (const suffix of ['A', 'B']) {
      const response = await request.post(`${BASE_URL}/invoice-content-configs`, {
        headers: headers(token),
        data: {
          project_name: `API 排序开票内容 ${suffix} ${RUN_ID}`,
          tax_rate: 0.03,
          current_account_set_id: ACCOUNT_SET_ID,
        },
      });
      expect(response.status(), await response.text()).toBe(200);
      contentConfigIds.push(Number(((await response.json()) as ApiEnvelope<ConfigItem>).data.id));
    }

    const contentConfigSort = await request.post(`${BASE_URL}/invoice-content-configs/sort`, {
      headers: headers(token),
      data: {
        items: [
          { id: contentConfigIds[1], sort_order: 1 },
          { id: contentConfigIds[0], sort_order: 2 },
        ],
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });
    expect(contentConfigSort.status(), await contentConfigSort.text()).toBe(200);
    expect(((await contentConfigSort.json()) as ApiEnvelope<unknown>).success).toBe(true);

    const createOptions = await request.get(`${BASE_URL}/invoice-applications/create-options`, {
      headers: headers(token),
      params: { current_account_set_id: ACCOUNT_SET_ID },
    });
    expect(createOptions.status(), await createOptions.text()).toBe(200);
    const createOptionsBody = (await createOptions.json()) as ApiEnvelope<{
      invoice_projects: ConfigItem[];
      invoice_content_configs: ConfigItem[];
    }>;
    expect(relativeOrder(createOptionsBody.data.invoice_projects, invoiceProjectIds)).toEqual([
      invoiceProjectIds[1],
      invoiceProjectIds[0],
    ]);
    expect(relativeOrder(createOptionsBody.data.invoice_content_configs, contentConfigIds)).toEqual([
      contentConfigIds[1],
      contentConfigIds[0],
    ]);
  } finally {
    for (const id of invoiceProjectIds) {
      await ignoreDelete(request, `${BASE_URL}/invoice-projects/${id}`, token);
    }
    for (const id of contentConfigIds) {
      await ignoreDelete(request, `${BASE_URL}/invoice-content-configs/${id}`, token);
    }
  }
});
