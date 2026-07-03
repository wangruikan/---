import { test, expect, APIRequestContext, APIResponse } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

const BASE_URL = process.env.API_BASE_URL || 'http://localhost:8000/api';
const USERNAME = process.env.E2E_USERNAME || 'admin';
const PASSWORD = process.env.E2E_PASSWORD || '123456';
const ACCOUNT_SET_ID = Number(process.env.E2E_ACCOUNT_SET_ID || 1);
const TEST_FILE_PATH = path.join(__dirname, 'test-image.png');

interface LoginResponse {
  success: boolean;
  data: {
    token: string;
    user: {
      id: number;
      name: string;
      role: string;
    };
  };
  message: string;
}

interface ProcessApprovalItem {
  id: number;
  title: string;
  category: 'social_insurance' | 'housing_fund';
  status: string;
  has_payment_request: boolean;
  payment_request_status?: string | null;
}

interface PaymentApplicationItem {
  id: number;
  payment_type: string;
  upload_later: boolean | number;
  can_supplement_attachment: boolean;
  supplement_deadline_at: string | null;
  supplement_remaining_seconds: number;
}

function assertJsonResponse(response: APIResponse) {
  expect(response.headers()['content-type']).toContain('application/json');
}

async function login(request: APIRequestContext): Promise<LoginResponse['data']> {
  const response = await request.post(`${BASE_URL}/auth/login`, {
    data: { username: USERNAME, password: PASSWORD },
  });

  expect(response.status()).toBe(200);
  assertJsonResponse(response);

  const body = (await response.json()) as LoginResponse;
  expect(body.success).toBe(true);
  expect(body.data.token).toBeTruthy();

  return body.data;
}

function authHeaders(token: string) {
  return {
    Authorization: `Bearer ${token}`,
    'X-Account-Set-Id': String(ACCOUNT_SET_ID),
  };
}

async function findFreshInsuranceSummary(
  request: APIRequestContext,
  token: string
): Promise<ProcessApprovalItem | null> {
  const candidates: ProcessApprovalItem[] = [];

  for (const category of ['social_insurance', 'housing_fund'] as const) {
    const response = await request.get(`${BASE_URL}/process-approvals`, {
      headers: authHeaders(token),
      params: {
        current_account_set_id: ACCOUNT_SET_ID,
        category,
        status: 'approved',
      },
    });

    expect(response.status()).toBe(200);
    assertJsonResponse(response);

    const body = await response.json();
    const items = (body.data?.data || []) as ProcessApprovalItem[];
    candidates.push(...items.filter(item => item.status === 'approved' && item.has_payment_request === false));
  }

  return candidates.find(item => !item.payment_request_status) || null;
}

async function getInsurancePaymentApplication(
  request: APIRequestContext,
  token: string,
  paymentRequestId: number
): Promise<PaymentApplicationItem | null> {
  const response = await request.get(`${BASE_URL}/payment-applications`, {
    headers: authHeaders(token),
    params: {
      current_account_set_id: ACCOUNT_SET_ID,
      payment_type: 'insurance',
      per_page: 100,
    },
  });

  expect(response.status()).toBe(200);
  assertJsonResponse(response);

  const body = await response.json();
  const items = (body.data?.data || []) as PaymentApplicationItem[];
  return items.find(item => item.id === paymentRequestId) || null;
}

test.describe('保险汇总付款申请 - 稍后上传回归', () => {
  test('upload_later=true 时应允许后补附件并可确认完成', async ({ request }) => {
    const auth = await login(request);
    const summary = await findFreshInsuranceSummary(request, auth.token);

    if (!summary) {
      test.skip();
      return;
    }

    console.log(`使用汇总单 ID=${summary.id}, category=${summary.category}`);

    const uniqueSuffix = `${Date.now()}`;
    const submitStartedAt = Date.now();
    const submitResponse = await request.post(`${BASE_URL}/insurance-payment-requests/submit`, {
      headers: authHeaders(auth.token),
      data: {
        process_approval_id: summary.id,
        amount: 1234.56,
        remarks: `API测试-稍后上传-${uniqueSuffix}`,
        current_account_set_id: ACCOUNT_SET_ID,
        upload_later: true,
        reimbursement_form_data: {
          applyDate: '2026-07-03',
          unitName: '回归测试单位',
          reimburser: 'API测试员',
          invoiceNumber: `INV-${uniqueSuffix}`,
          invoiceType: '增值税普通发票',
          invoiceAmount: 1234.56,
          taxRate: '6%',
          taxAmount: 69.88,
          deductionAmount: 0,
          amountExcludingTax: 1164.68,
          paymentDate: '2026-07-03',
          expenditureAmount: 1234.56,
          summary: '保险汇总稍后上传回归测试',
        },
      },
    });
    const submitElapsed = Date.now() - submitStartedAt;

    expect(submitResponse.status()).toBe(200);
    expect(submitElapsed).toBeLessThan(5000);
    assertJsonResponse(submitResponse);

    const submitBody = await submitResponse.json();
    expect(submitBody.success).toBe(true);
    expect(submitBody.data).toHaveProperty('id');
    expect(submitBody.data.payment_type).toBe('insurance');
    expect(submitBody.data.upload_later === true || submitBody.data.upload_later === 1).toBe(true);

    const paymentRequestId = submitBody.data.id as number;

    const completeResponse = await request.post(`${BASE_URL}/insurance-payment-requests/complete-submission`, {
      headers: authHeaders(auth.token),
      data: { payment_request_id: paymentRequestId },
    });

    expect(completeResponse.status()).toBe(200);
    assertJsonResponse(completeResponse);

    const completeBody = await completeResponse.json();
    expect(completeBody.success).toBe(true);
    expect(completeBody.message).toBe('付款审批流程已创建');

    const paymentBeforeSupplement = await getInsurancePaymentApplication(request, auth.token, paymentRequestId);
    expect(paymentBeforeSupplement).not.toBeNull();
    expect(paymentBeforeSupplement?.upload_later === true || paymentBeforeSupplement?.upload_later === 1).toBe(true);
    expect(paymentBeforeSupplement?.can_supplement_attachment).toBe(true);
    expect(paymentBeforeSupplement?.supplement_deadline_at).toBeTruthy();
    expect((paymentBeforeSupplement?.supplement_remaining_seconds || 0)).toBeGreaterThan(0);

    const uploadResponse = await request.post(`${BASE_URL}/payment-request-attachments`, {
      headers: authHeaders(auth.token),
      multipart: {
        payment_request_id: String(paymentRequestId),
        file: {
          name: `supplement-${uniqueSuffix}.png`,
          mimeType: 'image/png',
          buffer: fs.readFileSync(TEST_FILE_PATH),
        },
      },
    });

    expect(uploadResponse.status()).toBe(200);
    assertJsonResponse(uploadResponse);

    const uploadBody = await uploadResponse.json();
    expect(uploadBody.success).toBe(true);
    expect(uploadBody.data.attachment_type).toBe('supplement');

    const confirmResponse = await request.put(
      `${BASE_URL}/payment-applications/${paymentRequestId}/supplement-attachment`,
      {
        headers: authHeaders(auth.token),
        data: { current_account_set_id: ACCOUNT_SET_ID },
      }
    );

    expect(confirmResponse.status()).toBe(200);
    assertJsonResponse(confirmResponse);

    const confirmBody = await confirmResponse.json();
    expect(confirmBody.success).toBe(true);
    expect(confirmBody.message).toBe('附件补传完成');

    const paymentAfterSupplement = await getInsurancePaymentApplication(request, auth.token, paymentRequestId);
    expect(paymentAfterSupplement).not.toBeNull();
    expect(paymentAfterSupplement?.upload_later === false || paymentAfterSupplement?.upload_later === 0).toBe(true);
    expect(paymentAfterSupplement?.can_supplement_attachment).toBe(false);
  });
});
