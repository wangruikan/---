import fs from 'node:fs';
import path from 'node:path';
import { expect, test, type APIRequestContext } from '@playwright/test';

const BASE_URL = process.env.API_BASE_URL || 'http://127.0.0.1:8000/api';
const USERNAME = process.env.E2E_USERNAME || 'admin';
const PASSWORD = process.env.E2E_PASSWORD || '123456';
const ACCOUNT_SET_ID = Number(process.env.E2E_ACCOUNT_SET_ID || 1);
const RUN_ID = Date.now();
const PROJECT_CODE = `API-EMP-${RUN_ID}`;
const ID_NUMBER = `11010119900101${String(RUN_ID).slice(-4)}`;
const PDF_PATH = path.resolve('tests/api/test-document.pdf');

interface ApiEnvelope<T> {
  success: boolean;
  message?: string;
  data: T;
}

interface IdentifiedResource {
  id: number;
}

interface EmployeeResource extends IdentifiedResource {
  name: string;
  id_number: string;
  phone?: string | null;
  employee_number: string;
}

interface DocumentListItem {
  config_id: number;
  uploaded: boolean;
  file_count: number;
  files: Array<IdentifiedResource & { original_filename: string }>;
}

interface ContractResource extends IdentifiedResource {
  employee_id: number | string;
  contract_type: string;
  status: string;
  original_filename: string;
}

let token = '';
let projectId: number | null = null;
let documentSetId: number | null = null;
let documentConfigId: number | null = null;
let employeeId: number | null = null;
const documentIds = new Set<number>();
const contractIds = new Set<number>();

function headers(): Record<string, string> {
  return {
    Authorization: `Bearer ${token}`,
    'X-Auth-Token': token,
    'X-Account-Set-Id': String(ACCOUNT_SET_ID),
    Accept: 'application/json',
  };
}

function projectPayload() {
  return {
    name: `API Employee Project ${RUN_ID}`,
    code: PROJECT_CODE,
    description: 'Employee, document and contract API lifecycle test',
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
      remark: 'Default invoice information',
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

async function ignoreCleanup(request: APIRequestContext, method: 'delete', url: string) {
  try {
    await request.fetch(url, {
      method,
      headers: headers(),
      params: { current_account_set_id: ACCOUNT_SET_ID },
      failOnStatusCode: false,
    });
  } catch {
    // Cleanup should not hide the test result that caused an early exit.
  }
}

test.describe('Employee, document and contract API lifecycle', () => {
  test.beforeAll(async ({ request }) => {
    expect(fs.existsSync(PDF_PATH)).toBe(true);

    const response = await request.post(`${BASE_URL}/auth/login`, {
      data: { username: USERNAME, password: PASSWORD },
    });
    expect(response.status()).toBe(200);
    token = (await response.json()).data.token;
  });

  test.afterAll(async ({ request }) => {
    for (const contractId of contractIds) {
      await ignoreCleanup(request, 'delete', `${BASE_URL}/employees/contracts/${contractId}`);
    }
    for (const documentId of documentIds) {
      if (employeeId) {
        await ignoreCleanup(request, 'delete', `${BASE_URL}/employees/${employeeId}/documents/${documentId}`);
      }
    }
    if (employeeId) {
      await ignoreCleanup(request, 'delete', `${BASE_URL}/employees/${employeeId}`);
    }
    if (projectId && documentConfigId) {
      await ignoreCleanup(request, 'delete', `${BASE_URL}/projects/${projectId}/document-configs/${documentConfigId}`);
    }
    if (projectId && documentSetId) {
      await ignoreCleanup(request, 'delete', `${BASE_URL}/projects/${projectId}/document-config-sets/${documentSetId}`);
    }
    if (projectId) {
      await ignoreCleanup(request, 'delete', `${BASE_URL}/projects/${projectId}`);
    }
  });

  test('runs the active employee document and contract workflow end to end', async ({ request }) => {
    const binaryResponseDefects: string[] = [];
    const createProject = await request.post(`${BASE_URL}/projects`, {
      headers: headers(),
      data: projectPayload(),
    });
    expect(createProject.status(), await createProject.text()).toBe(200);
    const projectBody = await createProject.json() as ApiEnvelope<IdentifiedResource>;
    expect(projectBody.success).toBe(true);
    projectId = Number(projectBody.data.id);

    const createSet = await request.post(`${BASE_URL}/projects/${projectId}/document-config-sets`, {
      headers: headers(),
      data: {
        set_name: `API document set ${RUN_ID}`,
        is_default: true,
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });
    expect(createSet.status(), await createSet.text()).toBe(200);
    documentSetId = Number(((await createSet.json()) as ApiEnvelope<IdentifiedResource>).data.id);

    const createConfig = await request.post(`${BASE_URL}/projects/${projectId}/document-configs`, {
      headers: headers(),
      data: {
        document_set_id: documentSetId,
        document_name: 'API identity document',
        document_type: 'pdf',
        is_required: true,
        sort_order: 1,
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });
    expect(createConfig.status(), await createConfig.text()).toBe(200);
    documentConfigId = Number(((await createConfig.json()) as ApiEnvelope<IdentifiedResource>).data.id);

    const createEmployee = await request.post(`${BASE_URL}/employees`, {
      headers: headers(),
      data: {
        name: `API Employee ${RUN_ID}`,
        id_number: ID_NUMBER,
        gender: 'male',
        birth_date: '1990-01-01',
        hire_date: '2026-07-01',
        contract_start_date: '2026-07-01',
        id_card_valid_from: '2020-01-01',
        id_card_valid_until: '2030-01-01',
        project_ids: [projectId],
        project_document_set_id: documentSetId,
        social_security_region_id: null,
        medical_insurance_region_id: null,
        housing_fund_region_id: null,
        other_insurance_policy_ids: [],
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });
    expect(createEmployee.status(), await createEmployee.text()).toBe(200);
    const employeeBody = await createEmployee.json() as ApiEnvelope<EmployeeResource>;
    expect(employeeBody.success).toBe(true);
    expect(employeeBody.data.employee_number).toBeTruthy();
    employeeId = Number(employeeBody.data.id);

    const updateEmployee = await request.put(`${BASE_URL}/employees/${employeeId}`, {
      headers: headers(),
      data: {
        phone: `138${String(RUN_ID).slice(-8)}`,
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });
    expect(updateEmployee.status(), await updateEmployee.text()).toBe(200);

    const employeeDetail = await request.get(`${BASE_URL}/employees/${employeeId}`, {
      headers: headers(),
      params: { current_account_set_id: ACCOUNT_SET_ID },
    });
    expect(employeeDetail.status()).toBe(200);
    const detailBody = await employeeDetail.json() as ApiEnvelope<EmployeeResource>;
    expect(detailBody.data.id_number).toBe(ID_NUMBER);
    expect(detailBody.data.phone).toBe(`138${String(RUN_ID).slice(-8)}`);

    const initialDocuments = await request.get(`${BASE_URL}/employees/${employeeId}/documents`, {
      headers: headers(),
      params: { current_account_set_id: ACCOUNT_SET_ID },
    });
    expect(initialDocuments.status()).toBe(200);
    const initialDocumentBody = await initialDocuments.json() as ApiEnvelope<DocumentListItem[]>;
    expect(initialDocumentBody.data).toContainEqual(expect.objectContaining({
      config_id: documentConfigId,
      uploaded: false,
      file_count: 0,
    }));

    const pdf = fs.readFileSync(PDF_PATH);
    for (const filename of [`identity-${RUN_ID}.pdf`, `identity-back-${RUN_ID}.pdf`]) {
      const upload = await request.post(`${BASE_URL}/employees/${employeeId}/documents/upload`, {
        headers: headers(),
        multipart: {
          document_config_id: String(documentConfigId),
          upload_source: 'pc',
          file: {
            name: filename,
            mimeType: 'application/pdf',
            buffer: pdf,
          },
        },
      });
      expect(upload.status(), await upload.text()).toBe(200);
      const uploadBody = await upload.json() as ApiEnvelope<IdentifiedResource & { file_count: number }>;
      documentIds.add(Number(uploadBody.data.id));
    }

    const uploadedDocuments = await request.get(`${BASE_URL}/employees/${employeeId}/documents`, {
      headers: headers(),
    });
    expect(uploadedDocuments.status()).toBe(200);
    const uploadedDocumentBody = await uploadedDocuments.json() as ApiEnvelope<DocumentListItem[]>;
    const documentItem = uploadedDocumentBody.data.find((item) => item.config_id === documentConfigId);
    expect(documentItem).toMatchObject({ uploaded: true, file_count: 2 });
    expect(documentItem?.files).toHaveLength(2);

    const firstDocumentId = [...documentIds][0];
    try {
      const preview = await request.get(`${BASE_URL}/employees/${employeeId}/documents/${firstDocumentId}/preview`, {
        headers: headers(),
      });
      expect(preview.status()).toBe(200);
      expect(preview.headers()['content-type']).toContain('application/pdf');
      expect((await preview.body()).subarray(0, 4).toString()).toBe('%PDF');
    } catch (error) {
      binaryResponseDefects.push(`employee document preview: ${String(error)}`);
    }

    const download = await request.get(`${BASE_URL}/employees/${employeeId}/documents/${firstDocumentId}/download`, {
      headers: headers(),
    });
    expect(download.status()).toBe(200);
    expect(download.headers()['content-disposition']).toContain('attachment');
    expect((await download.body()).subarray(0, 4).toString()).toBe('%PDF');

    const deleteDocument = await request.delete(`${BASE_URL}/employees/${employeeId}/documents/${firstDocumentId}`, {
      headers: headers(),
    });
    expect(deleteDocument.status()).toBe(200);
    documentIds.delete(firstDocumentId);

    const remainingDocuments = await request.get(`${BASE_URL}/employees/${employeeId}/documents`, {
      headers: headers(),
    });
    const remainingBody = await remainingDocuments.json() as ApiEnvelope<DocumentListItem[]>;
    expect(remainingBody.data.find((item) => item.config_id === documentConfigId)).toMatchObject({
      uploaded: true,
      file_count: 1,
    });

    const createContract = await request.post(`${BASE_URL}/employees/contracts`, {
      headers: headers(),
      multipart: {
        employee_id: String(employeeId),
        current_account_set_id: String(ACCOUNT_SET_ID),
        contract_type: 'labor',
        stamp_method: 'online',
        notes: 'Playwright API contract lifecycle',
        contract_file: {
          name: `labor-contract-${RUN_ID}.pdf`,
          mimeType: 'application/pdf',
          buffer: pdf,
        },
      },
    });
    expect(createContract.status(), await createContract.text()).toBe(200);
    const contractBody = await createContract.json() as ApiEnvelope<ContractResource>;
    expect(contractBody.data).toMatchObject({
      contract_type: 'labor',
      status: 'draft',
    });
    expect(Number(contractBody.data.employee_id)).toBe(employeeId);
    const contractId = Number(contractBody.data.id);
    contractIds.add(contractId);

    const contractList = await request.get(`${BASE_URL}/employees/${employeeId}/contracts`, {
      headers: headers(),
      params: { current_account_set_id: ACCOUNT_SET_ID },
    });
    expect(contractList.status()).toBe(200);
    const contractListBody = await contractList.json() as ApiEnvelope<ContractResource[]>;
    expect(contractListBody.data.map((contract) => contract.id)).toContain(contractId);

    try {
      const contractDownload = await request.get(`${BASE_URL}/employees/contracts/${contractId}/download`, {
        failOnStatusCode: false,
      });
      expect(contractDownload.status()).toBe(200);
      expect((await contractDownload.body()).subarray(0, 4).toString()).toBe('%PDF');
    } catch (error) {
      binaryResponseDefects.push(`employee contract download: ${String(error)}`);
    }

    const submitContract = await request.post(`${BASE_URL}/employees/contracts/${contractId}/submit`, {
      headers: headers(),
    });
    expect(submitContract.status(), await submitContract.text()).toBe(200);
    expect(((await submitContract.json()) as ApiEnvelope<ContractResource>).data.status).toBe('pending_sign');

    const signContract = await request.post(`${BASE_URL}/employees/contracts/${contractId}/employee-sign`, {
      headers: headers(),
    });
    expect(signContract.status(), await signContract.text()).toBe(200);
    expect(((await signContract.json()) as ApiEnvelope<ContractResource>).data.status).toBe('employee_signed');

    const completeContract = await request.post(`${BASE_URL}/employees/contracts/${contractId}/complete`, {
      headers: headers(),
    });
    expect(completeContract.status(), await completeContract.text()).toBe(200);
    expect(((await completeContract.json()) as ApiEnvelope<ContractResource>).data.status).toBe('completed');

    const deleteContract = await request.delete(`${BASE_URL}/employees/contracts/${contractId}`, {
      headers: headers(),
    });
    expect(deleteContract.status()).toBe(200);
    contractIds.delete(contractId);

    expect(binaryResponseDefects, binaryResponseDefects.join('\n\n')).toEqual([]);
  });
});
