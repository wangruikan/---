import fs from 'node:fs';
import path from 'node:path';
import { expect, test, type APIRequestContext } from '@playwright/test';

const BASE_URL = process.env.API_BASE_URL || 'http://127.0.0.1:8000/api';
const USERNAME = process.env.E2E_USERNAME || 'admin';
const PASSWORD = process.env.E2E_PASSWORD || '123456';
const ACCOUNT_SET_ID = Number(process.env.E2E_ACCOUNT_SET_ID || 1);
const RUN_ID = Date.now();
const PDF_PATH = path.resolve('tests/api/test-document.pdf');

interface ApiEnvelope<T> {
  success: boolean;
  message?: string;
  data: T;
}

interface IdentifiedResource {
  id: number;
}

interface DocumentItem {
  config_id: number;
  project_id: number;
  uploaded: boolean;
  file_count: number;
  is_history: boolean;
  files: Array<{ id: number; original_filename: string }>;
}

async function ignoreCleanup(request: APIRequestContext, url: string, token: string) {
  try {
    await request.delete(url, {
      headers: {
        Authorization: `Bearer ${token}`,
        'X-Auth-Token': token,
        'X-Account-Set-Id': String(ACCOUNT_SET_ID),
        Accept: 'application/json',
      },
      params: { current_account_set_id: ACCOUNT_SET_ID },
      failOnStatusCode: false,
    });
  } catch {
    // Cleanup must not hide the feature assertion that failed first.
  }
}

function projectPayload(name: string, code: string) {
  return {
    name,
    code,
    description: 'Employee document transfer history API test',
    start_date: '2026-01-01',
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
      remark: 'API test invoice information',
      company_name: `API Test Company ${RUN_ID}`,
      tax_number: `TAX${RUN_ID}`,
      company_address: 'API test address',
      company_phone: '010-12345678',
      bank_name: 'API test bank',
      bank_account: `6222${RUN_ID}`,
      bank_code: 'APITEST001',
    }],
    social_security_regions: [],
    medical_insurance_regions: [],
    housing_fund_regions: [],
    other_insurance_policies: [],
    large_medical_insurance_configs: [],
    current_account_set_id: ACCOUNT_SET_ID,
  };
}

test('员工调动后保留旧项目资料，并使用新项目资料方案', async ({ request }) => {
  expect(fs.existsSync(PDF_PATH)).toBe(true);

  const login = await request.post(`${BASE_URL}/auth/login`, {
    data: { username: USERNAME, password: PASSWORD },
  });
  expect(login.status(), await login.text()).toBe(200);
  const token = ((await login.json()) as ApiEnvelope<{ token: string }>).data.token;
  const headers = {
    Authorization: `Bearer ${token}`,
    'X-Auth-Token': token,
    'X-Account-Set-Id': String(ACCOUNT_SET_ID),
    Accept: 'application/json',
  };

  let employeeId: number | null = null;
  let sourceProjectId: number | null = null;
  let sourceSetId: number | null = null;
  let sourceConfigId: number | null = null;
  let targetProjectId: number | null = null;
  let targetSetId: number | null = null;
  let targetConfigId: number | null = null;
  const documentIds: number[] = [];

  try {
    const createSourceProject = await request.post(`${BASE_URL}/projects`, {
      headers,
      data: projectPayload(`API Source Project ${RUN_ID}`, `API-SOURCE-${RUN_ID}`),
    });
    expect(createSourceProject.status(), await createSourceProject.text()).toBe(200);
    sourceProjectId = Number(((await createSourceProject.json()) as ApiEnvelope<IdentifiedResource>).data.id);

    const createTargetProject = await request.post(`${BASE_URL}/projects`, {
      headers,
      data: projectPayload(`API Target Project ${RUN_ID}`, `API-TARGET-${RUN_ID}`),
    });
    expect(createTargetProject.status(), await createTargetProject.text()).toBe(200);
    targetProjectId = Number(((await createTargetProject.json()) as ApiEnvelope<IdentifiedResource>).data.id);

    const createSourceSet = await request.post(`${BASE_URL}/projects/${sourceProjectId}/document-config-sets`, {
      headers,
      data: {
        set_name: `Source set ${RUN_ID}`,
        is_default: true,
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });
    expect(createSourceSet.status(), await createSourceSet.text()).toBe(200);
    sourceSetId = Number(((await createSourceSet.json()) as ApiEnvelope<IdentifiedResource>).data.id);

    const createTargetSet = await request.post(`${BASE_URL}/projects/${targetProjectId}/document-config-sets`, {
      headers,
      data: {
        set_name: `Target set ${RUN_ID}`,
        is_default: true,
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });
    expect(createTargetSet.status(), await createTargetSet.text()).toBe(200);
    targetSetId = Number(((await createTargetSet.json()) as ApiEnvelope<IdentifiedResource>).data.id);

    const createSourceConfig = await request.post(`${BASE_URL}/projects/${sourceProjectId}/document-configs`, {
      headers,
      data: {
        document_set_id: sourceSetId,
        document_name: '原项目身份证资料',
        document_type: 'pdf',
        is_required: true,
        sort_order: 1,
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });
    expect(createSourceConfig.status(), await createSourceConfig.text()).toBe(200);
    sourceConfigId = Number(((await createSourceConfig.json()) as ApiEnvelope<IdentifiedResource>).data.id);

    const createTargetConfig = await request.post(`${BASE_URL}/projects/${targetProjectId}/document-configs`, {
      headers,
      data: {
        document_set_id: targetSetId,
        document_name: '新项目上岗资料',
        document_type: 'pdf',
        is_required: true,
        sort_order: 1,
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });
    expect(createTargetConfig.status(), await createTargetConfig.text()).toBe(200);
    targetConfigId = Number(((await createTargetConfig.json()) as ApiEnvelope<IdentifiedResource>).data.id);

    const createEmployee = await request.post(`${BASE_URL}/employees`, {
      headers,
      data: {
        name: `API Transfer Employee ${RUN_ID}`,
        id_number: `11010119900101${String(RUN_ID).slice(-4)}`,
        gender: 'male',
        birth_date: '1990-01-01',
        hire_date: '2026-07-01',
        contract_start_date: '2026-07-01',
        id_card_valid_from: '2020-01-01',
        id_card_valid_until: '2030-01-01',
        project_ids: [sourceProjectId],
        project_document_set_id: sourceSetId,
        social_security_region_id: null,
        medical_insurance_region_id: null,
        housing_fund_region_id: null,
        other_insurance_policy_ids: [],
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });
    expect(createEmployee.status(), await createEmployee.text()).toBe(200);
    employeeId = Number(((await createEmployee.json()) as ApiEnvelope<IdentifiedResource>).data.id);

    const pdf = fs.readFileSync(PDF_PATH);
    const sourceUpload = await request.post(`${BASE_URL}/employees/${employeeId}/documents/upload`, {
      headers,
      multipart: {
        document_config_id: String(sourceConfigId),
        upload_source: 'pc',
        file: {
          name: `source-${RUN_ID}.pdf`,
          mimeType: 'application/pdf',
          buffer: pdf,
        },
      },
    });
    expect(sourceUpload.status(), await sourceUpload.text()).toBe(200);
    documentIds.push(Number(((await sourceUpload.json()) as ApiEnvelope<IdentifiedResource>).data.id));

    const transfer = await request.put(`${BASE_URL}/employees/${employeeId}`, {
      headers,
      data: {
        project_ids: [targetProjectId],
        project_document_set_id: targetSetId,
        transfer_date: '2026-08-01',
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });
    expect(transfer.status(), await transfer.text()).toBe(200);

    const documentsAfterTransfer = await request.get(`${BASE_URL}/employees/${employeeId}/documents`, {
      headers,
      params: { current_account_set_id: ACCOUNT_SET_ID },
    });
    expect(documentsAfterTransfer.status(), await documentsAfterTransfer.text()).toBe(200);
    const afterTransfer = (await documentsAfterTransfer.json()) as ApiEnvelope<DocumentItem[]>;
    expect(afterTransfer.data.find((item) => item.config_id === targetConfigId)).toMatchObject({
      project_id: targetProjectId,
      uploaded: false,
      file_count: 0,
      is_history: false,
    });
    expect(afterTransfer.data.find((item) => item.config_id === sourceConfigId)).toMatchObject({
      project_id: sourceProjectId,
      uploaded: true,
      file_count: 1,
      is_history: true,
      files: [expect.objectContaining({ original_filename: `source-${RUN_ID}.pdf` })],
    });

    const targetUpload = await request.post(`${BASE_URL}/employees/${employeeId}/documents/upload`, {
      headers,
      multipart: {
        document_config_id: String(targetConfigId),
        upload_source: 'pc',
        file: {
          name: `target-${RUN_ID}.pdf`,
          mimeType: 'application/pdf',
          buffer: pdf,
        },
      },
    });
    expect(targetUpload.status(), await targetUpload.text()).toBe(200);
    documentIds.push(Number(((await targetUpload.json()) as ApiEnvelope<IdentifiedResource>).data.id));

    const documentsAfterTargetUpload = await request.get(`${BASE_URL}/employees/${employeeId}/documents`, {
      headers,
      params: { current_account_set_id: ACCOUNT_SET_ID },
    });
    expect(documentsAfterTargetUpload.status(), await documentsAfterTargetUpload.text()).toBe(200);
    const afterTargetUpload = (await documentsAfterTargetUpload.json()) as ApiEnvelope<DocumentItem[]>;
    expect(afterTargetUpload.data.find((item) => item.config_id === targetConfigId)).toMatchObject({
      uploaded: true,
      file_count: 1,
      is_history: false,
    });
    expect(afterTargetUpload.data.find((item) => item.config_id === sourceConfigId)).toMatchObject({
      uploaded: true,
      file_count: 1,
      is_history: true,
    });
  } finally {
    for (const documentId of documentIds) {
      if (employeeId) {
        await ignoreCleanup(request, `${BASE_URL}/employees/${employeeId}/documents/${documentId}`, token);
      }
    }
    if (employeeId) {
      await ignoreCleanup(request, `${BASE_URL}/employees/${employeeId}`, token);
    }
    if (sourceProjectId && sourceConfigId) {
      await ignoreCleanup(request, `${BASE_URL}/projects/${sourceProjectId}/document-configs/${sourceConfigId}`, token);
    }
    if (sourceProjectId && sourceSetId) {
      await ignoreCleanup(request, `${BASE_URL}/projects/${sourceProjectId}/document-config-sets/${sourceSetId}`, token);
    }
    if (sourceProjectId) {
      await ignoreCleanup(request, `${BASE_URL}/projects/${sourceProjectId}`, token);
    }
    if (targetProjectId && targetConfigId) {
      await ignoreCleanup(request, `${BASE_URL}/projects/${targetProjectId}/document-configs/${targetConfigId}`, token);
    }
    if (targetProjectId && targetSetId) {
      await ignoreCleanup(request, `${BASE_URL}/projects/${targetProjectId}/document-config-sets/${targetSetId}`, token);
    }
    if (targetProjectId) {
      await ignoreCleanup(request, `${BASE_URL}/projects/${targetProjectId}`, token);
    }
  }
});
