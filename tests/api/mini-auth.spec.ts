import { test, expect, APIRequestContext } from '@playwright/test';

const BASE_URL = 'http://localhost:8000/api';
const MINI_PHONE = process.env.MINI_PHONE || '13800000001';
const MINI_PASSWORD = process.env.MINI_PASSWORD || '011234';

test.describe('小程序登录认证', () => {
  // ============================================================
  // 一、正常登录
  // ============================================================
  test.describe('一、正常登录', () => {
    test('手机号+密码登录成功', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/login`, {
        data: { phone: MINI_PHONE, password: MINI_PASSWORD },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.data).toHaveProperty('token');
      expect(data.data).toHaveProperty('employee');
      expect(data.data.employee).toHaveProperty('id');
      expect(data.data.employee).toHaveProperty('name');
      expect(data.data.employee).toHaveProperty('phone');
      expect(data.data.employee).toHaveProperty('id_number');
    });

    test('登录返回 registration_form_type', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/login`, {
        data: { phone: MINI_PHONE, password: MINI_PASSWORD },
      });
      const data = await response.json();
      expect(data.data.employee).toHaveProperty('registration_form_type');
      expect(['onboarding', 'registration']).toContain(
        data.data.employee.registration_form_type
      );
    });

    test('登录返回 contract_status', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/login`, {
        data: { phone: MINI_PHONE, password: MINI_PASSWORD },
      });
      const data = await response.json();
      expect(data.data.employee).toHaveProperty('contract_status');
    });
  });

  // ============================================================
  // 二、登录失败
  // ============================================================
  test.describe('二、登录失败', () => {
    test('密码错误返回 401', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/login`, {
        data: { phone: MINI_PHONE, password: '000000' },
      });
      expect(response.status()).toBe(401);
      const data = await response.json();
      expect(data.success).toBe(false);
      expect(data.message).toContain('密码错误');
    });

    test('不存在的手机号返回 401', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/login`, {
        data: { phone: '19999999999', password: '123456' },
      });
      expect(response.status()).toBe(401);
      const data = await response.json();
      expect(data.success).toBe(false);
    });

    test('缺少 phone 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/login`, {
        data: { password: MINI_PASSWORD },
      });
      expect(response.status()).toBe(422);
    });

    test('缺少 password 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/mini/login`, {
        data: { phone: MINI_PHONE },
      });
      expect(response.status()).toBe(422);
    });
  });

  // ============================================================
  // 三、未认证访问
  // ============================================================
  test.describe('三、未认证访问', () => {
    test('无 token 访问受保护接口返回 401', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/mini/onboarding-form`);
      expect(response.status()).toBe(401);
    });

    test('无效 token 访问受保护接口返回 401', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/mini/onboarding-form`, {
        headers: { Authorization: 'Bearer invalid_token_here' },
      });
      expect(response.status()).toBe(401);
    });
  });
});
