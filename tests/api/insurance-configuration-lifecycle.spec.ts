import { expect, test, type APIRequestContext } from '@playwright/test';

const BASE_URL = process.env.API_BASE_URL || 'http://127.0.0.1:8000/api';
const USERNAME = process.env.E2E_USERNAME || 'admin';
const PASSWORD = process.env.E2E_PASSWORD || '123456';
const ACCOUNT_SET_ID = Number(process.env.E2E_ACCOUNT_SET_ID || 1);
const RUN_ID = Date.now();

interface ApiEnvelope<T> {
  success: boolean;
  data: T;
  message?: string;
}

interface Resource {
  id: number;
  [key: string]: unknown;
}

let token = '';
let socialRegionId: number | null = null;
let socialTypeId: number | null = null;
let medicalRegionId: number | null = null;
let medicalTypeId: number | null = null;
let largeMedicalId: number | null = null;
let housingRegionId: number | null = null;
let housingConfigId: number | null = null;
let otherTypeId: number | null = null;
let otherPolicyId: number | null = null;

function headers(): Record<string, string> {
  return {
    Authorization: `Bearer ${token}`,
    'X-Auth-Token': token,
    'X-Account-Set-Id': String(ACCOUNT_SET_ID),
    Accept: 'application/json',
  };
}

async function cleanupDelete(request: APIRequestContext, url: string, params: Record<string, number> = {}) {
  try {
    await request.delete(url, {
      headers: headers(),
      params,
      failOnStatusCode: false,
    });
  } catch {
    // Keep cleanup failures from replacing the primary test failure.
  }
}

test.describe('Active insurance configuration API lifecycle', () => {
  test.beforeAll(async ({ request }) => {
    const response = await request.post(`${BASE_URL}/auth/login`, {
      data: { username: USERNAME, password: PASSWORD },
    });
    expect(response.status()).toBe(200);
    token = (await response.json()).data.token;
  });

  test.afterAll(async ({ request }) => {
    if (otherPolicyId) {
      await cleanupDelete(request, `${BASE_URL}/other-insurance/policies/${otherPolicyId}`, {
        current_account_set_id: ACCOUNT_SET_ID,
      });
    }
    if (otherTypeId) {
      await cleanupDelete(request, `${BASE_URL}/other-insurance/types/${otherTypeId}`, {
        current_account_set_id: ACCOUNT_SET_ID,
      });
    }
    if (housingConfigId) await cleanupDelete(request, `${BASE_URL}/housing-fund-configs/${housingConfigId}`);
    if (housingRegionId) await cleanupDelete(request, `${BASE_URL}/housing-fund-regions/${housingRegionId}`);
    if (largeMedicalId) await cleanupDelete(request, `${BASE_URL}/large-medical-insurance/${largeMedicalId}`);
    if (medicalTypeId) {
      await cleanupDelete(request, `${BASE_URL}/medical-insurance/types/${medicalTypeId}`, {
        account_set_id: ACCOUNT_SET_ID,
      });
    }
    if (medicalRegionId) {
      await cleanupDelete(request, `${BASE_URL}/medical-insurance/${medicalRegionId}`, {
        account_set_id: ACCOUNT_SET_ID,
      });
    }
    if (socialTypeId) await cleanupDelete(request, `${BASE_URL}/social-security/types/${socialTypeId}`);
    if (socialRegionId) await cleanupDelete(request, `${BASE_URL}/social-security/${socialRegionId}`);
  });

  test('creates, updates, reads and deletes all insurance configuration types', async ({ request }) => {
    const socialName = `API Social ${RUN_ID}`;
    const socialCreate = await request.post(`${BASE_URL}/social-security`, {
      headers: headers(),
      data: {
        name: socialName,
        code: `SS${RUN_ID}`,
        company: 'API Social Company',
        min_base_amount: 3000,
        max_base_amount: 30000,
        account_set_id: ACCOUNT_SET_ID,
      },
    });
    expect(socialCreate.status(), await socialCreate.text()).toBe(200);
    socialRegionId = Number(((await socialCreate.json()) as ApiEnvelope<Resource>).data.id);

    const socialTypeCreate = await request.post(`${BASE_URL}/social-security/${socialRegionId}/types`, {
      headers: headers(),
      data: {
        name: `API Pension ${RUN_ID}`,
        employee_ratio: 0.08,
        company_ratio: 0.16,
        only_company_pay: false,
      },
    });
    expect(socialTypeCreate.status(), await socialTypeCreate.text()).toBe(200);
    socialTypeId = Number(((await socialTypeCreate.json()) as ApiEnvelope<Resource>).data.id);

    const socialTypeUpdate = await request.put(`${BASE_URL}/social-security/types/${socialTypeId}`, {
      headers: headers(),
      data: {
        name: `API Pension Updated ${RUN_ID}`,
        employee_ratio: 0.09,
        company_ratio: 0.17,
        only_company_pay: false,
      },
    });
    expect(socialTypeUpdate.status(), await socialTypeUpdate.text()).toBe(200);
    expect(Number((await socialTypeUpdate.json()).data.employee_ratio)).toBeCloseTo(0.09);

    const socialUpdatedName = `${socialName} Updated`;
    const socialUpdate = await request.put(`${BASE_URL}/social-security/${socialRegionId}`, {
      headers: headers(),
      data: { name: socialUpdatedName },
    });
    expect(socialUpdate.status(), await socialUpdate.text()).toBe(200);

    const socialShow = await request.get(`${BASE_URL}/social-security/${socialRegionId}`, {
      headers: headers(),
    });
    expect(socialShow.status()).toBe(200);
    expect((await socialShow.json()).data.name).toBe(socialUpdatedName);

    const socialHistory = await request.get(`${BASE_URL}/social-security/${socialRegionId}/limit-histories`, {
      headers: headers(),
    });
    expect(socialHistory.status()).toBe(200);

    const medicalName = `API Medical ${RUN_ID}`;
    const medicalCreate = await request.post(`${BASE_URL}/medical-insurance`, {
      headers: headers(),
      data: {
        name: medicalName,
        code: `MI${RUN_ID}`,
        company: 'API Medical Company',
        min_base_amount: 3500,
        max_base_amount: 35000,
        type_name: `API Basic Medical ${RUN_ID}`,
        type_employee_ratio: 0.02,
        type_company_ratio: 0.08,
        account_set_id: ACCOUNT_SET_ID,
      },
    });
    expect(medicalCreate.status(), await medicalCreate.text()).toBe(200);
    medicalRegionId = Number(((await medicalCreate.json()) as ApiEnvelope<Resource>).data.id);

    const medicalTypeCreate = await request.post(`${BASE_URL}/medical-insurance/${medicalRegionId}/types`, {
      headers: headers(),
      data: {
        name: `API Supplemental Medical ${RUN_ID}`,
        employee_ratio: 0.005,
        company_ratio: 0.01,
        account_set_id: ACCOUNT_SET_ID,
      },
    });
    expect(medicalTypeCreate.status(), await medicalTypeCreate.text()).toBe(200);
    medicalTypeId = Number(((await medicalTypeCreate.json()) as ApiEnvelope<Resource>).data.id);

    const medicalTypeUpdate = await request.put(`${BASE_URL}/medical-insurance/types/${medicalTypeId}`, {
      headers: headers(),
      data: {
        name: `API Supplemental Updated ${RUN_ID}`,
        employee_ratio: 0.006,
        company_ratio: 0.012,
        account_set_id: ACCOUNT_SET_ID,
      },
    });
    expect(medicalTypeUpdate.status(), await medicalTypeUpdate.text()).toBe(200);

    const largeCreate = await request.post(`${BASE_URL}/large-medical-insurance`, {
      headers: headers(),
      data: {
        region_name: medicalName,
        account_set_id: ACCOUNT_SET_ID,
        calculation_type: 'fixed',
        payment_cycle: 'year',
        annual_payment_month: 7,
        company_amount: 120.15,
        employee_amount: 30.15,
        status: 1,
        remarks: 'API annual large medical test',
      },
    });
    expect(largeCreate.status(), await largeCreate.text()).toBe(200);
    largeMedicalId = Number(((await largeCreate.json()) as ApiEnvelope<Resource>).data.id);

    const medicalUpdatedName = `${medicalName} Updated`;
    const medicalUpdate = await request.put(`${BASE_URL}/medical-insurance/${medicalRegionId}`, {
      headers: headers(),
      data: {
        name: medicalUpdatedName,
        min_base_amount: 3500,
        max_base_amount: 35000,
        account_set_id: ACCOUNT_SET_ID,
      },
    });
    expect(medicalUpdate.status(), await medicalUpdate.text()).toBe(200);

    const largeList = await request.get(`${BASE_URL}/large-medical-insurance`, {
      headers: headers(),
      params: { account_set_id: ACCOUNT_SET_ID },
    });
    expect(largeList.status()).toBe(200);
    const largeItems = ((await largeList.json()) as ApiEnvelope<Resource[]>).data;
    expect(largeItems.find((item) => Number(item.id) === largeMedicalId)?.region_name).toBe(medicalUpdatedName);

    const largeUpdate = await request.put(`${BASE_URL}/large-medical-insurance/${largeMedicalId}`, {
      headers: headers(),
      data: {
        payment_cycle: 'year',
        annual_payment_month: 8,
        company_amount: 125.15,
        employee_amount: 35.15,
      },
    });
    expect(largeUpdate.status(), await largeUpdate.text()).toBe(200);
    expect(Number((await largeUpdate.json()).data.annual_payment_month)).toBe(8);

    const largeHistory = await request.get(`${BASE_URL}/large-medical-insurance/${largeMedicalId}/histories`, {
      headers: headers(),
      params: { account_set_id: ACCOUNT_SET_ID },
    });
    expect(largeHistory.status()).toBe(200);

    const housingName = `API Housing ${RUN_ID}`;
    const housingRegionCreate = await request.post(`${BASE_URL}/housing-fund-regions`, {
      headers: headers(),
      data: {
        region_name: housingName,
        account_number: `HF${RUN_ID}`,
        company_name: 'API Housing Company',
        account_set_id: ACCOUNT_SET_ID,
      },
    });
    expect(housingRegionCreate.status(), await housingRegionCreate.text()).toBe(201);
    housingRegionId = Number(((await housingRegionCreate.json()) as ApiEnvelope<Resource>).data.id);

    const housingConfigCreate = await request.post(`${BASE_URL}/housing-fund-configs`, {
      headers: headers(),
      data: {
        region_id: housingRegionId,
        config_name: `API Housing Config ${RUN_ID}`,
        base_amount: 8000,
        min_base_amount: 2500,
        max_base_amount: 30000,
        employee_ratio: 0.12,
        company_ratio: 0.12,
        account_set_id: ACCOUNT_SET_ID,
      },
    });
    expect(housingConfigCreate.status(), await housingConfigCreate.text()).toBe(201);
    housingConfigId = Number(((await housingConfigCreate.json()) as ApiEnvelope<Resource>).data.id);

    const housingConfigs = await request.get(`${BASE_URL}/housing-fund-regions/${housingRegionId}/configs`, {
      headers: headers(),
    });
    expect(housingConfigs.status()).toBe(200);
    expect((await housingConfigs.json()).data.map((item: Resource) => Number(item.id))).toContain(housingConfigId);

    const housingRegionUpdate = await request.put(`${BASE_URL}/housing-fund-regions/${housingRegionId}`, {
      headers: headers(),
      data: {
        region_name: `${housingName} Updated`,
        account_number: `HF${RUN_ID}`,
        company_name: 'API Housing Company Updated',
      },
    });
    expect(housingRegionUpdate.status(), await housingRegionUpdate.text()).toBe(200);

    const housingConfigUpdate = await request.put(`${BASE_URL}/housing-fund-configs/${housingConfigId}`, {
      headers: headers(),
      data: {
        region_id: housingRegionId,
        config_name: `API Housing Config Updated ${RUN_ID}`,
        min_base_amount: 2500,
        max_base_amount: 30000,
        employee_ratio: 0.1,
        company_ratio: 0.1,
      },
    });
    expect(housingConfigUpdate.status(), await housingConfigUpdate.text()).toBe(200);

    const housingHistory = await request.get(`${BASE_URL}/housing-fund-regions/${housingRegionId}/limit-histories`, {
      headers: headers(),
    });
    expect(housingHistory.status()).toBe(200);

    const otherTypeCreate = await request.post(`${BASE_URL}/other-insurance/types`, {
      headers: headers(),
      data: {
        name: `API Commercial Type ${RUN_ID}`,
        description: 'API commercial insurance type',
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });
    expect(otherTypeCreate.status(), await otherTypeCreate.text()).toBe(200);
    otherTypeId = Number(((await otherTypeCreate.json()) as ApiEnvelope<Resource>).data.id);

    const policyPayload = {
      policy_number: `POLICY-${RUN_ID}`,
      policy_name: `API Commercial Policy ${RUN_ID}`,
      insurance_company: 'API Insurance Company',
      coverage_amount: 100000,
      employee_per_capita_cost: 88.15,
      quota: 100,
      contact_name: 'API Contact',
      contact_phone: '13800000000',
      personnel_name_list: [],
      start_date: '2026-01-01',
      end_date: '2027-12-31',
      description: 'API commercial policy',
    };
    const policyCreate = await request.post(`${BASE_URL}/other-insurance/types/${otherTypeId}/policies`, {
      headers: headers(),
      data: { ...policyPayload, current_account_set_id: ACCOUNT_SET_ID },
    });
    expect(policyCreate.status(), await policyCreate.text()).toBe(200);
    otherPolicyId = Number(((await policyCreate.json()) as ApiEnvelope<Resource>).data.id);

    const policyList = await request.get(`${BASE_URL}/other-insurance/types/${otherTypeId}/policies`, {
      headers: headers(),
      params: { current_account_set_id: ACCOUNT_SET_ID },
    });
    expect(policyList.status()).toBe(200);
    expect((await policyList.json()).data.map((item: Resource) => Number(item.id))).toContain(otherPolicyId);

    const policyUpdate = await request.put(`${BASE_URL}/other-insurance/policies/${otherPolicyId}`, {
      headers: headers(),
      data: {
        ...policyPayload,
        policy_name: `API Commercial Policy Updated ${RUN_ID}`,
        employee_per_capita_cost: 99.15,
        status: 'active',
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });
    expect(policyUpdate.status(), await policyUpdate.text()).toBe(200);

    const otherTypeUpdate = await request.put(`${BASE_URL}/other-insurance/types/${otherTypeId}`, {
      headers: headers(),
      data: {
        name: `API Commercial Type Updated ${RUN_ID}`,
        description: 'Updated by Playwright API test',
        current_account_set_id: ACCOUNT_SET_ID,
      },
    });
    expect(otherTypeUpdate.status(), await otherTypeUpdate.text()).toBe(200);

    const deletePolicy = await request.delete(`${BASE_URL}/other-insurance/policies/${otherPolicyId}`, {
      headers: headers(),
      params: { current_account_set_id: ACCOUNT_SET_ID },
    });
    expect(deletePolicy.status(), await deletePolicy.text()).toBe(200);
    otherPolicyId = null;

    const deleteOtherType = await request.delete(`${BASE_URL}/other-insurance/types/${otherTypeId}`, {
      headers: headers(),
      params: { current_account_set_id: ACCOUNT_SET_ID },
    });
    expect(deleteOtherType.status(), await deleteOtherType.text()).toBe(200);
    otherTypeId = null;

    const deleteHousingConfig = await request.delete(`${BASE_URL}/housing-fund-configs/${housingConfigId}`, {
      headers: headers(),
    });
    expect(deleteHousingConfig.status(), await deleteHousingConfig.text()).toBe(200);
    housingConfigId = null;

    const deleteHousingRegion = await request.delete(`${BASE_URL}/housing-fund-regions/${housingRegionId}`, {
      headers: headers(),
    });
    expect(deleteHousingRegion.status(), await deleteHousingRegion.text()).toBe(200);
    housingRegionId = null;

    const deleteLarge = await request.delete(`${BASE_URL}/large-medical-insurance/${largeMedicalId}`, {
      headers: headers(),
    });
    expect(deleteLarge.status(), await deleteLarge.text()).toBe(200);
    largeMedicalId = null;

    const deleteMedicalType = await request.delete(`${BASE_URL}/medical-insurance/types/${medicalTypeId}`, {
      headers: headers(),
      params: { account_set_id: ACCOUNT_SET_ID },
    });
    expect(deleteMedicalType.status(), await deleteMedicalType.text()).toBe(200);
    medicalTypeId = null;

    const deleteMedicalRegion = await request.delete(`${BASE_URL}/medical-insurance/${medicalRegionId}`, {
      headers: headers(),
      params: { account_set_id: ACCOUNT_SET_ID },
    });
    expect(deleteMedicalRegion.status(), await deleteMedicalRegion.text()).toBe(200);
    medicalRegionId = null;

    const deleteSocialType = await request.delete(`${BASE_URL}/social-security/types/${socialTypeId}`, {
      headers: headers(),
    });
    expect(deleteSocialType.status(), await deleteSocialType.text()).toBe(200);
    socialTypeId = null;

    const deleteSocialRegion = await request.delete(`${BASE_URL}/social-security/${socialRegionId}`, {
      headers: headers(),
    });
    expect(deleteSocialRegion.status(), await deleteSocialRegion.text()).toBe(200);
    socialRegionId = null;
  });
});
