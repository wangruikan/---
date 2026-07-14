# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: insurance-changes.spec.ts >> 参保增减 API 测试 >> 三、子任务 items >> 子任务 category 值在允许范围内
- Location: tests\api\insurance-changes.spec.ts:293:9

# Error details

```
Error: expect(received).toContain(expected) // indexOf

Expected value: "other_policy:11"
Received array: ["social_security", "medical_insurance", "housing_fund", "large_medical_insurance", "other_insurance"]
```

# Test source

```ts
  219 |       });
  220 |       const listData = await listRes.json();
  221 |       if (listData.data.length === 0) {
  222 |         test.skip();
  223 |         return;
  224 |       }
  225 | 
  226 |       const id = listData.data[0].id;
  227 |       const response = await request.get(`${BASE_URL}/insurance-changes/${id}`, {
  228 |         headers: authHeaders(),
  229 |       });
  230 |       expect(response.status()).toBe(200);
  231 |       const data = await response.json();
  232 |       expect(data.success).toBe(true);
  233 |       expect(data.data.id).toBe(id);
  234 |     });
  235 | 
  236 |     test('详情返回快照字段', async ({ request }) => {
  237 |       const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
  238 |         headers: authHeaders(),
  239 |         params: { account_set_id: ACCOUNT_SET_ID, per_page: 10 },
  240 |       });
  241 |       const listData = await listRes.json();
  242 |       const withInsurance = listData.data.find(
  243 |         (item: any) => item.social_security_region_id || item.medical_insurance_region_id
  244 |       );
  245 |       if (!withInsurance) {
  246 |         test.skip();
  247 |         return;
  248 |       }
  249 | 
  250 |       const response = await request.get(`${BASE_URL}/insurance-changes/${withInsurance.id}`, {
  251 |         headers: authHeaders(),
  252 |       });
  253 |       const data = await response.json();
  254 |       expect(data.data).toHaveProperty('social_security_types');
  255 |       expect(data.data).toHaveProperty('medical_insurance_types');
  256 |       expect(data.data).toHaveProperty('housing_fund_params');
  257 |       expect(data.data).toHaveProperty('other_insurance_policies');
  258 |     });
  259 | 
  260 |     test('获取不存在的详情应返回 404', async ({ request }) => {
  261 |       const response = await request.get(`${BASE_URL}/insurance-changes/999999`, {
  262 |         headers: authHeaders(),
  263 |       });
  264 |       expect(response.status()).toBe(404);
  265 |     });
  266 |   });
  267 | 
  268 |   // ============================================================
  269 |   // 三、子任务 items
  270 |   // ============================================================
  271 |   test.describe('三、子任务 items', () => {
  272 |     test('获取子任务列表成功', async ({ request }) => {
  273 |       const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
  274 |         headers: authHeaders(),
  275 |         params: { account_set_id: ACCOUNT_SET_ID, per_page: 10 },
  276 |       });
  277 |       const listData = await listRes.json();
  278 |       if (listData.data.length === 0) {
  279 |         test.skip();
  280 |         return;
  281 |       }
  282 | 
  283 |       const id = listData.data[0].id;
  284 |       const response = await request.get(`${BASE_URL}/insurance-changes/${id}/items`, {
  285 |         headers: authHeaders(),
  286 |       });
  287 |       expect(response.status()).toBe(200);
  288 |       const data = await response.json();
  289 |       expect(data.success).toBe(true);
  290 |       expect(Array.isArray(data.data)).toBe(true);
  291 |     });
  292 | 
  293 |     test('子任务 category 值在允许范围内', async ({ request }) => {
  294 |       const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
  295 |         headers: authHeaders(),
  296 |         params: { account_set_id: ACCOUNT_SET_ID, per_page: 10 },
  297 |       });
  298 |       const listData = await listRes.json();
  299 |       const withItems = listData.data.find(
  300 |         (item: any) => item.change_items && item.change_items.length > 0
  301 |       );
  302 |       if (!withItems) {
  303 |         test.skip();
  304 |         return;
  305 |       }
  306 | 
  307 |       const response = await request.get(`${BASE_URL}/insurance-changes/${withItems.id}/items`, {
  308 |         headers: authHeaders(),
  309 |       });
  310 |       const data = await response.json();
  311 |       const allowedCategories = [
  312 |         'social_security',
  313 |         'medical_insurance',
  314 |         'housing_fund',
  315 |         'large_medical_insurance',
  316 |         'other_insurance',
  317 |       ];
  318 |       for (const item of data.data) {
> 319 |         expect(allowedCategories).toContain(item.category);
      |                                   ^ Error: expect(received).toContain(expected) // indexOf
  320 |       }
  321 |     });
  322 |   });
  323 | 
  324 |   // ============================================================
  325 |   // 四、附件上传与删除
  326 |   // ============================================================
  327 |   test.describe('四、附件上传与删除', () => {
  328 |     test('上传附件成功', async ({ request }) => {
  329 |       const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
  330 |         headers: authHeaders(),
  331 |         params: { account_set_id: ACCOUNT_SET_ID, status: 'pending', per_page: 1 },
  332 |       });
  333 |       const listData = await listRes.json();
  334 |       if (listData.data.length === 0) {
  335 |         test.skip();
  336 |         return;
  337 |       }
  338 | 
  339 |       const id = listData.data[0].id;
  340 |       const uploadRes = await uploadFiles(
  341 |         authToken,
  342 |         `/insurance-changes/${id}/upload-attachment`,
  343 |         [TEST_PNG_PATH],
  344 |         ['test.png'],
  345 |         ['image/png']
  346 |       );
  347 |       expect(uploadRes.status).toBe(200);
  348 |       const data = JSON.parse(uploadRes.body);
  349 |       expect(data.success).toBe(true);
  350 |       expect(data.message).toContain('成功上传');
  351 |       expect(data.data.uploaded_files.length).toBeGreaterThan(0);
  352 |       expect(data.data.change.status).toBe('submitted');
  353 |     });
  354 | 
  355 |     test('上传附件后状态变为 submitted', async ({ request }) => {
  356 |       const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
  357 |         headers: authHeaders(),
  358 |         params: { account_set_id: ACCOUNT_SET_ID, status: 'pending', per_page: 1 },
  359 |       });
  360 |       const listData = await listRes.json();
  361 |       if (listData.data.length === 0) {
  362 |         test.skip();
  363 |         return;
  364 |       }
  365 | 
  366 |       const id = listData.data[0].id;
  367 |       const uploadRes = await uploadFiles(
  368 |         authToken,
  369 |         `/insurance-changes/${id}/upload-attachment`,
  370 |         [TEST_PDF_PATH],
  371 |         ['test.pdf'],
  372 |         ['application/pdf']
  373 |       );
  374 |       const uploadData = JSON.parse(uploadRes.body);
  375 |       if (!uploadData.success) {
  376 |         test.skip();
  377 |         return;
  378 |       }
  379 | 
  380 |       const detailRes = await request.get(`${BASE_URL}/insurance-changes/${id}`, {
  381 |         headers: authHeaders(),
  382 |       });
  383 |       const detailData = await detailRes.json();
  384 |       expect(detailData.data.status).toBe('submitted');
  385 |       expect(detailData.data.attachment_uploaded_at).toBeTruthy();
  386 |     });
  387 | 
  388 |     test('删除附件成功', async ({ request }) => {
  389 |       const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
  390 |         headers: authHeaders(),
  391 |         params: { account_set_id: ACCOUNT_SET_ID, status: 'submitted', per_page: 10 },
  392 |       });
  393 |       const listData = await listRes.json();
  394 |       const withAttachment = listData.data.find(
  395 |         (item: any) => item.attachments && item.attachments.length > 0
  396 |       );
  397 |       if (!withAttachment) {
  398 |         test.skip();
  399 |         return;
  400 |       }
  401 | 
  402 |       const attachmentId = withAttachment.attachments[0].id;
  403 |       const response = await request.delete(
  404 |         `${BASE_URL}/insurance-changes/attachments/${attachmentId}`,
  405 |         { headers: authHeaders() }
  406 |       );
  407 |       expect(response.status()).toBe(200);
  408 |       const data = await response.json();
  409 |       expect(data.success).toBe(true);
  410 |     });
  411 | 
  412 |     test('上传不合法文件类型应返回 422', async ({ request }) => {
  413 |       const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
  414 |         headers: authHeaders(),
  415 |         params: { account_set_id: ACCOUNT_SET_ID, status: 'pending', per_page: 1 },
  416 |       });
  417 |       const listData = await listRes.json();
  418 |       if (listData.data.length === 0) {
  419 |         test.skip();
```