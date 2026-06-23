# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: invoice-red-flush.spec.ts >> 发票红冲 API 测试 >> F. 使用当前月或未来月 source 应返回 422
- Location: tests\api\invoice-red-flush.spec.ts:296:7

# Error details

```
Error: expect(received).toBe(expected) // Object.is equality

Expected: "只能选择当前月份之前的历史发票进行红冲"
Received: "只能选择已完成且已通过审批的发票进行红冲"
```

# Test source

```ts
  246 |       return;
  247 |     }
  248 | 
  249 |     const response = await request.post(`${BASE_URL}/invoice-applications`, {
  250 |       headers: { Authorization: `Bearer ${authToken}` },
  251 |       data: {
  252 |         task_name: '重复红冲测试',
  253 |         year: 2026,
  254 |         month: 7,
  255 |         project_name: '测试项目',
  256 |         status: 'red_flushed',
  257 |         red_flush_source_id: sourceInvoiceId,
  258 |         current_account_set_id: ACCOUNT_SET_ID,
  259 |       },
  260 |     });
  261 | 
  262 |     console.log('重复红冲响应状态:', response.status());
  263 |     const data = await response.json();
  264 |     console.log('重复红冲响应:', data);
  265 | 
  266 |     expect(response.status()).toBe(422);
  267 |     expect(data.success).toBe(false);
  268 |     expect(data.message).toBe('该发票已经是红冲状态');
  269 |   });
  270 | 
  271 |   // 测试 E: 缺少 red_flush_source_id 时应返回 422
  272 |   test('E. 缺少 red_flush_source_id 应返回 422', async ({ request }) => {
  273 |     const response = await request.post(`${BASE_URL}/invoice-applications`, {
  274 |       headers: { Authorization: `Bearer ${authToken}` },
  275 |       data: {
  276 |         task_name: '缺少source测试',
  277 |         year: 2026,
  278 |         month: 7,
  279 |         project_name: '测试项目',
  280 |         status: 'red_flushed',
  281 |         // 故意不传 red_flush_source_id
  282 |         current_account_set_id: ACCOUNT_SET_ID,
  283 |       },
  284 |     });
  285 | 
  286 |     console.log('缺少source响应状态:', response.status());
  287 |     const data = await response.json();
  288 |     console.log('缺少source响应:', data);
  289 | 
  290 |     expect(response.status()).toBe(422);
  291 |     expect(data.success).toBe(false);
  292 |     expect(data.message).toBe('请选择需要红冲的发票');
  293 |   });
  294 | 
  295 |   // 测试 F: 使用当前月 source 或未来月 source 时应返回 422
  296 |   test('F. 使用当前月或未来月 source 应返回 422', async ({ request }) => {
  297 |     // 获取一个年月 >= 2026-06 的发票作为 source
  298 |     const candidatesRes = await request.get(`${BASE_URL}/invoice-applications`, {
  299 |       headers: { Authorization: `Bearer ${authToken}` },
  300 |       params: {
  301 |         current_account_set_id: ACCOUNT_SET_ID,
  302 |         per_page: 100,
  303 |       },
  304 |     });
  305 |     const candidatesData = await candidatesRes.json();
  306 | 
  307 |     // 找一个年月 >= 2026-06 的发票
  308 |     const futureInvoice = candidatesData.data?.data?.find(
  309 |       (item: any) => item.year > 2026 || (item.year === 2026 && item.month >= 6)
  310 |     );
  311 | 
  312 |     if (!futureInvoice) {
  313 |       console.log('跳过: 没有当前月或未来月的发票');
  314 |       return;
  315 |     }
  316 | 
  317 |     console.log('使用发票作为source:', {
  318 |       id: futureInvoice.id,
  319 |       year: futureInvoice.year,
  320 |       month: futureInvoice.month,
  321 |     });
  322 | 
  323 |     const response = await request.post(`${BASE_URL}/invoice-applications`, {
  324 |       headers: { Authorization: `Bearer ${authToken}` },
  325 |       data: {
  326 |         task_name: '未来月source测试',
  327 |         year: 2026,
  328 |         month: 6,
  329 |         project_name: '测试项目',
  330 |         status: 'red_flushed',
  331 |         red_flush_source_id: futureInvoice.id,
  332 |         current_account_set_id: ACCOUNT_SET_ID,
  333 |       },
  334 |     });
  335 | 
  336 |     console.log('未来月source响应状态:', response.status());
  337 |     const data = await response.json();
  338 |     console.log('未来月source响应:', data);
  339 | 
  340 |     // 如果 source 年月 >= 请求年月，应该返回 422
  341 |     if (
  342 |       futureInvoice.year > 2026 ||
  343 |       (futureInvoice.year === 2026 && futureInvoice.month >= 6)
  344 |     ) {
  345 |       expect(response.status()).toBe(422);
> 346 |       expect(data.message).toBe('只能选择当前月份之前的历史发票进行红冲');
      |                            ^ Error: expect(received).toBe(expected) // Object.is equality
  347 |     }
  348 |   });
  349 | 
  350 |   // 测试 G: 使用未完成/未审批/无发票号的 source 应返回 422
  351 |   test('G. 使用未完成/未审批/无发票号的 source 应返回 422', async ({ request }) => {
  352 |     // 获取一个不满足条件的发票
  353 |     const allRes = await request.get(`${BASE_URL}/invoice-applications`, {
  354 |       headers: { Authorization: `Bearer ${authToken}` },
  355 |       params: {
  356 |         current_account_set_id: ACCOUNT_SET_ID,
  357 |         per_page: 100,
  358 |       },
  359 |     });
  360 |     const allData = await allRes.json();
  361 | 
  362 |     // 找一个未完成或未审批或无发票号的发票
  363 |     const invalidSource = allData.data?.data?.find(
  364 |       (item: any) =>
  365 |         item.approval_status !== 'approved' ||
  366 |         !item.is_completed ||
  367 |         !item.invoice_number
  368 |     );
  369 | 
  370 |     if (!invalidSource) {
  371 |       console.log('跳过: 没有不满足条件的发票');
  372 |       return;
  373 |     }
  374 | 
  375 |     console.log('使用不满足条件的发票:', {
  376 |       id: invalidSource.id,
  377 |       approval_status: invalidSource.approval_status,
  378 |       is_completed: invalidSource.is_completed,
  379 |       invoice_number: invalidSource.invoice_number,
  380 |     });
  381 | 
  382 |     const response = await request.post(`${BASE_URL}/invoice-applications`, {
  383 |       headers: { Authorization: `Bearer ${authToken}` },
  384 |       data: {
  385 |         task_name: '不满足条件source测试',
  386 |         year: 2026,
  387 |         month: 7,
  388 |         project_name: '测试项目',
  389 |         status: 'red_flushed',
  390 |         red_flush_source_id: invalidSource.id,
  391 |         current_account_set_id: ACCOUNT_SET_ID,
  392 |       },
  393 |     });
  394 | 
  395 |     console.log('不满足条件source响应状态:', response.status());
  396 |     const data = await response.json();
  397 |     console.log('不满足条件source响应:', data);
  398 | 
  399 |     expect(response.status()).toBe(422);
  400 |     expect(data.message).toBe('只能选择已完成且已通过审批的发票进行红冲');
  401 |   });
  402 | 
  403 |   // 测试: 使用不存在的 red_flush_source_id 应返回 404
  404 |   test('使用不存在的 red_flush_source_id 应返回 404', async ({ request }) => {
  405 |     const response = await request.post(`${BASE_URL}/invoice-applications`, {
  406 |       headers: { Authorization: `Bearer ${authToken}` },
  407 |       data: {
  408 |         task_name: '不存在source测试',
  409 |         year: 2026,
  410 |         month: 7,
  411 |         project_name: '测试项目',
  412 |         status: 'red_flushed',
  413 |         red_flush_source_id: 999999,
  414 |         current_account_set_id: ACCOUNT_SET_ID,
  415 |       },
  416 |     });
  417 | 
  418 |     console.log('不存在source响应状态:', response.status());
  419 |     const data = await response.json();
  420 |     console.log('不存在source响应:', data);
  421 | 
  422 |     expect(response.status()).toBe(404);
  423 |     expect(data.message).toBe('红冲发票不存在');
  424 |   });
  425 | });
  426 | 
```