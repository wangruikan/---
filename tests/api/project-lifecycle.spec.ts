import { expect, test } from '@playwright/test';

const BASE_URL = process.env.API_BASE_URL || 'http://127.0.0.1:8000/api';
const USERNAME = process.env.E2E_USERNAME || 'admin';
const PASSWORD = process.env.E2E_PASSWORD || '123456';
const ACCOUNT_SET_ID = Number(process.env.E2E_ACCOUNT_SET_ID || 1);
const RUN_ID = Date.now();
const PROJECT_CODE = `API-PROJECT-${RUN_ID}`;

let token: string;
let projectId: number | null = null;

function headers(): Record<string, string> {
  return {
    Authorization: `Bearer ${token}`,
    'X-Auth-Token': token,
    'X-Account-Set-Id': String(ACCOUNT_SET_ID),
    Accept: 'application/json',
  };
}

function projectPayload(name: string) {
  return {
    name,
    code: PROJECT_CODE,
    description: 'Playwright API project lifecycle',
    start_date: '2026-07-01',
    end_date: '2027-12-31',
    salary_payment_date: 15,
    salary_payment_month: 'current',
    insurance_import_month: 'none',
    requires_attendance: false,
    require_attendance: false,
    delivery_frequency: 'monthly',
    delivery_method: 'electronic',
    registration_form_type: 'onboarding',
    invoice_infos: [{
      remark: '默认开票信息',
      company_name: `API测试公司${RUN_ID}`,
      tax_number: `TAX${RUN_ID}`,
      company_address: '测试地址1号',
      company_phone: '010-12345678',
      bank_name: '测试银行',
      bank_account: `6222${RUN_ID}`,
      bank_code: 'TESTBANK001',
    }],
    social_security_regions: [],
    medical_insurance_regions: [],
    housing_fund_regions: [],
    other_insurance_policies: [],
    large_medical_insurance_configs: [],
    current_account_set_id: ACCOUNT_SET_ID,
  };
}

test.describe('Project API lifecycle', () => {
  test.beforeAll(async ({ request }) => {
    const response = await request.post(`${BASE_URL}/auth/login`, {
      data: { username: USERNAME, password: PASSWORD },
    });
    expect(response.status()).toBe(200);
    token = (await response.json()).data.token;
  });

  test.afterAll(async ({ request }) => {
    if (!projectId) return;
    await request.delete(`${BASE_URL}/projects/${projectId}`, { headers: headers() });
  });

  test('create, configure, terminate and delete a project', async ({ request }) => {
    const preview = await request.get(`${BASE_URL}/projects/generate-code-preview`, {
      headers: headers(),
      params: { name: `项目预览${RUN_ID}`, current_account_set_id: ACCOUNT_SET_ID },
    });
    expect(preview.status()).toBe(200);
    expect((await preview.json()).data.code).toBeTruthy();

    const create = await request.post(`${BASE_URL}/projects`, {
      headers: headers(),
      data: projectPayload(`API项目${RUN_ID}`),
    });
    expect(create.status()).toBe(200);
    const created = await create.json();
    expect(created.success).toBe(true);
    expect(created.data.code).toBe(PROJECT_CODE);
    expect(created.data.account_set_id).toBe(ACCOUNT_SET_ID);
    projectId = Number(created.data.id);

    const duplicate = await request.post(`${BASE_URL}/projects`, {
      headers: headers(),
      data: projectPayload(`重复编号项目${RUN_ID}`),
    });
    expect(duplicate.status()).toBe(422);
    expect((await duplicate.json()).message).toContain('相同编号');

    const list = await request.get(`${BASE_URL}/projects`, {
      headers: headers(),
      params: { search: PROJECT_CODE, current_account_set_id: ACCOUNT_SET_ID, per_page: 20 },
    });
    expect(list.status()).toBe(200);
    const listBody = await list.json();
    expect(listBody.data.data.map((project: { id: number }) => project.id)).toContain(projectId);

    const update = await request.put(`${BASE_URL}/projects/${projectId}`, {
      headers: headers(),
      data: projectPayload(`API项目已更新${RUN_ID}`),
    });
    expect(update.status()).toBe(200);
    expect((await update.json()).data.name).toBe(`API项目已更新${RUN_ID}`);

    for (const [uri, data] of [
      [`projects/${projectId}/social-security-regions`, { region_ids: [] }],
      [`projects/${projectId}/medical-insurance-regions`, { region_ids: [] }],
      [`projects/${projectId}/housing-fund-regions`, { region_ids: [] }],
      [`projects/${projectId}/other-insurance-policies`, { policy_ids: [] }],
    ] as const) {
      const response = await request.post(`${BASE_URL}/${uri}`, {
        headers: headers(),
        data: { ...data, current_account_set_id: ACCOUNT_SET_ID },
      });
      expect(response.status(), uri).toBe(200);
    }

    const saveNotices = await request.post(`${BASE_URL}/projects/${projectId}/contract-notices`, {
      headers: headers(),
      data: {
        notice_file_ids: [],
        notice_placeholder_positions: {},
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });
    expect(saveNotices.status()).toBe(200);

    const notices = await request.get(`${BASE_URL}/projects/${projectId}/contract-notices`, {
      headers: headers(),
      params: { current_account_set_id: ACCOUNT_SET_ID },
    });
    expect(notices.status()).toBe(200);
    expect((await notices.json()).data.notice_file_ids).toEqual([]);

    const saveFields = await request.post(`${BASE_URL}/projects/${projectId}/placeholder-fields`, {
      headers: headers(),
      data: {
        placeholder_fields: [{ key: 'employee_name', label: '姓名' }],
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });
    expect(saveFields.status()).toBe(200);

    const fields = await request.get(`${BASE_URL}/projects/${projectId}/placeholder-fields`, {
      headers: headers(),
      params: { current_account_set_id: ACCOUNT_SET_ID },
    });
    expect(fields.status()).toBe(200);
    expect((await fields.json()).data).toContainEqual({ key: 'employee_name', label: '姓名' });

    const saveRoles = await request.post(`${BASE_URL}/projects/${projectId}/role-users`, {
      headers: headers(),
      data: {
        insurance_user_ids: [],
        salary_user_ids: [],
        delivery_user_ids: [],
        role_manager_user_ids: [],
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });
    expect(saveRoles.status()).toBe(200);

    const roles = await request.get(`${BASE_URL}/projects/${projectId}/role-users`, {
      headers: headers(),
      params: { current_account_set_id: ACCOUNT_SET_ID },
    });
    expect(roles.status()).toBe(200);
    expect((await roles.json()).data.roles).toHaveProperty('insurance');

    const terminate = await request.put(`${BASE_URL}/projects/${projectId}/terminate`, {
      headers: headers(),
      data: { current_account_set_id: ACCOUNT_SET_ID },
    });
    expect(terminate.status()).toBe(200);
    expect((await terminate.json()).data.status).toBe('terminated');

    const deletedProjectId = projectId;
    const remove = await request.delete(`${BASE_URL}/projects/${deletedProjectId}`, {
      headers: headers(),
      params: { current_account_set_id: ACCOUNT_SET_ID },
    });
    expect(remove.status()).toBe(200);
    projectId = null;

    const after = await request.get(`${BASE_URL}/projects`, {
      headers: headers(),
      params: { search: PROJECT_CODE, current_account_set_id: ACCOUNT_SET_ID, per_page: 20 },
    });
    expect(after.status()).toBe(200);
    expect((await after.json()).data.data.map((project: { id: number }) => project.id)).not.toContain(deletedProjectId);
  });
});
