# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: personnel-change-requests.spec.ts >> C. 详情接口 >> C1. 查询 pending 记录成功
- Location: tests\api\personnel-change-requests.spec.ts:411:7

# Error details

```
Error: expect(received).toBe(expected) // Object.is equality

Expected: "pending"
Received: "in_approval"
```

# Test source

```ts
  316 |     const res = await api.list({ current_account_set_id: ACCOUNT_SET_A, status: 'pending' });
  317 |     assertSuccess(res.body);
  318 |     expect(res.body.data.data.length).toBeGreaterThan(0);
  319 |     for (const item of res.body.data.data) {
  320 |       expect(item.status).toBe('pending');
  321 |     }
  322 |   });
  323 | 
  324 |   test('B9b. status 筛选正确 - rejected', async ({ request }) => {
  325 |     const api = new PersonnelChangeRequestApi(request, authToken);
  326 |     const res = await api.list({ current_account_set_id: ACCOUNT_SET_A, status: 'rejected' });
  327 |     assertSuccess(res.body);
  328 |     expect(res.body.data.data.length).toBeGreaterThan(0);
  329 |     for (const item of res.body.data.data) {
  330 |       expect(item.status).toBe('rejected');
  331 |     }
  332 |   });
  333 | 
  334 |   test('B10. 多条件组合筛选正确', async ({ request }) => {
  335 |     const api = new PersonnelChangeRequestApi(request, authToken);
  336 |     const res = await api.list({
  337 |       current_account_set_id: ACCOUNT_SET_A,
  338 |       status: 'pending',
  339 |       change_type: 'add',
  340 |       project_id: '4',
  341 |     });
  342 |     assertSuccess(res.body);
  343 |     for (const item of res.body.data.data) {
  344 |       expect(item.status).toBe('pending');
  345 |       expect(item.change_type).toBe('add');
  346 |       expect(item.project_id).toBe(4);
  347 |     }
  348 |   });
  349 | 
  350 |   test('B11. per_page 生效', async ({ request }) => {
  351 |     const api = new PersonnelChangeRequestApi(request, authToken);
  352 |     const res = await api.list({ current_account_set_id: ACCOUNT_SET_A, per_page: 2 });
  353 |     assertSuccess(res.body);
  354 |     expect(res.body.data.data.length).toBeLessThanOrEqual(2);
  355 |     expect(res.body.data.per_page).toBe(2);
  356 |   });
  357 | 
  358 |   test('B12. page 翻页数据正确', async ({ request }) => {
  359 |     const api = new PersonnelChangeRequestApi(request, authToken);
  360 |     const page1 = await api.list({ current_account_set_id: ACCOUNT_SET_A, per_page: 2, page: 1 });
  361 |     const page2 = await api.list({ current_account_set_id: ACCOUNT_SET_A, per_page: 2, page: 2 });
  362 |     assertSuccess(page1.body);
  363 |     assertSuccess(page2.body);
  364 |     if (page1.body.data.total > 2) {
  365 |       const ids1 = page1.body.data.data.map((r: any) => r.id);
  366 |       const ids2 = page2.body.data.data.map((r: any) => r.id);
  367 |       const overlap = ids1.filter((id: number) => ids2.includes(id));
  368 |       expect(overlap.length).toBe(0);
  369 |     }
  370 |   });
  371 | 
  372 |   test('B13. current_account_set_id=A 只返回 A 账套数据', async ({ request }) => {
  373 |     const api = new PersonnelChangeRequestApi(request, authToken);
  374 |     const res = await api.list({ current_account_set_id: ACCOUNT_SET_A, per_page: 100 });
  375 |     assertSuccess(res.body);
  376 |     for (const item of res.body.data.data) {
  377 |       expect(item.account_set_id).toBe(Number(ACCOUNT_SET_A));
  378 |     }
  379 |   });
  380 | 
  381 |   test('B14. 缺少 current_account_set_id 时的行为记录', async ({ request }) => {
  382 |     const api = new PersonnelChangeRequestApi(request, authToken);
  383 |     const res = await api.list({});
  384 |     // 记录行为
  385 |     if (res.status === 200 && res.body.success) {
  386 |       const items = res.body.data.data;
  387 |       const hasOtherAccount = items.some((r: any) => r.account_set_id !== Number(ACCOUNT_SET_A));
  388 |       if (hasOtherAccount) {
  389 |         console.warn('⚠️ 账套隔离风险：缺少 current_account_set_id 时返回了其他账套数据');
  390 |       }
  391 |     }
  392 |   });
  393 | 
  394 |   test('B15. A 账套 token + B 账套参数尝试读取其他账套数据', async ({ request }) => {
  395 |     const api = new PersonnelChangeRequestApi(request, authToken);
  396 |     const res = await api.list({ current_account_set_id: ACCOUNT_SET_B, per_page: 100 });
  397 |     if (res.status === 200 && res.body.success) {
  398 |       const items = res.body.data.data;
  399 |       const hasBData = items.some((r: any) => r.account_set_id === Number(ACCOUNT_SET_B));
  400 |       if (hasBData) {
  401 |         console.error('🚨 严重越权：A 账套 token 可以通过参数读取 B 账套数据');
  402 |       }
  403 |     }
  404 |   });
  405 | });
  406 | 
  407 | // ============================================================
  408 | // C. 详情接口 GET /api/personnel-change-requests/{id}
  409 | // ============================================================
  410 | test.describe('C. 详情接口', () => {
  411 |   test('C1. 查询 pending 记录成功', async ({ request }) => {
  412 |     const api = new PersonnelChangeRequestApi(request, authToken);
  413 |     const res = await api.show(ID.PENDING_ADD);
  414 |     expect(res.status).toBe(200);
  415 |     assertSuccess(res.body);
> 416 |     expect(res.body.data.status).toBe('pending');
      |                                  ^ Error: expect(received).toBe(expected) // Object.is equality
  417 |     assertJsonHeaders(res.headers);
  418 |     assertResponseTime(res.elapsed);
  419 |   });
  420 | 
  421 |   test('C2. 查询 rejected 记录成功', async ({ request }) => {
  422 |     const api = new PersonnelChangeRequestApi(request, authToken);
  423 |     const res = await api.show(ID.REJECTED_REMOVE);
  424 |     expect(res.status).toBe(200);
  425 |     assertSuccess(res.body);
  426 |     expect(res.body.data.status).toBe('rejected');
  427 |   });
  428 | 
  429 |   test('C3. 返回包含 project / creator / attachments', async ({ request }) => {
  430 |     const api = new PersonnelChangeRequestApi(request, authToken);
  431 |     const res = await api.show(ID.PENDING_ADD);
  432 |     assertSuccess(res.body);
  433 |     expect(res.body.data).toHaveProperty('project');
  434 |     expect(res.body.data).toHaveProperty('creator');
  435 |     expect(res.body.data).toHaveProperty('attachments');
  436 |     expect(res.body.data.project).toHaveProperty('id');
  437 |     expect(res.body.data.project).toHaveProperty('name');
  438 |     expect(res.body.data.creator).toHaveProperty('id');
  439 |     expect(Array.isArray(res.body.data.attachments)).toBe(true);
  440 |   });
  441 | 
  442 |   test('C4. personnel_list 为数组', async ({ request }) => {
  443 |     const api = new PersonnelChangeRequestApi(request, authToken);
  444 |     const res = await api.show(ID.PENDING_ADD);
  445 |     assertSuccess(res.body);
  446 |     expect(Array.isArray(res.body.data.personnel_list)).toBe(true);
  447 |     expect(res.body.data.personnel_list.length).toBeGreaterThan(0);
  448 |   });
  449 | 
  450 |   test('C5. 附件字段结构正确', async ({ request }) => {
  451 |     const api = new PersonnelChangeRequestApi(request, authToken);
  452 |     const res = await api.show(ID.WITH_ATTACHMENT);
  453 |     assertSuccess(res.body);
  454 |     if (res.body.data.attachments.length > 0) {
  455 |       const att = res.body.data.attachments[0];
  456 |       expect(att).toHaveProperty('file_name');
  457 |       expect(att).toHaveProperty('file_path');
  458 |       expect(att).toHaveProperty('file_type');
  459 |       expect(att).toHaveProperty('file_size');
  460 |       expect(typeof att.file_size).toBe('number');
  461 |     }
  462 |   });
  463 | 
  464 |   test('C6. 查询不存在 ID 返回 404', async ({ request }) => {
  465 |     const api = new PersonnelChangeRequestApi(request, authToken);
  466 |     const res = await api.show('999999');
  467 |     expect(res.status).toBe(404);
  468 |     assertFail(res.body);
  469 |     assertResponseTime(res.elapsed);
  470 |   });
  471 | 
  472 |   test('C7. 尝试读取其他账套的申请', async ({ request }) => {
  473 |     const api = new PersonnelChangeRequestApi(request, authToken);
  474 |     const res = await api.show(ID.OTHER_ACCOUNT);
  475 |     if (res.status === 200 && res.body.success) {
  476 |       console.error(`🚨 跨账套越权：show 接口可读取其他账套记录 id=${ID.OTHER_ACCOUNT}`);
  477 |       expect(res.body.data.account_set_id).toBe(Number(ACCOUNT_SET_B));
  478 |     }
  479 |     // 如果返回 404/403，说明有隔离
  480 |   });
  481 | });
  482 | 
  483 | // ============================================================
  484 | // D. 上传附件接口 POST /api/personnel-change-requests/upload-attachment
  485 | // ============================================================
  486 | test.describe('D. 上传附件接口', () => {
  487 |   test('D1. 给 pending 记录上传附件成功', async ({ request }) => {
  488 |     const api = new PersonnelChangeRequestApi(request, authToken);
  489 |     const res = await api.uploadAttachment(ID.UPLOAD_TARGET, smallFile(), 'test-pending.txt');
  490 |     expect(res.status).toBe(200);
  491 |     assertSuccess(res.body);
  492 |     expect(res.body.message).toContain('成功');
  493 |     assertJsonHeaders(res.headers);
  494 |     assertResponseTime(res.elapsed);
  495 |   });
  496 | 
  497 |   test('D2. 给 rejected 记录上传附件成功', async ({ request }) => {
  498 |     const api = new PersonnelChangeRequestApi(request, authToken);
  499 |     const res = await api.uploadAttachment(ID.REJECTED_REMOVE, smallFile(), 'test-rejected.txt');
  500 |     expect(res.status).toBe(200);
  501 |     assertSuccess(res.body);
  502 |   });
  503 | 
  504 |   test('D3. multipart 格式正确', async ({ request }) => {
  505 |     const api = new PersonnelChangeRequestApi(request, authToken);
  506 |     const res = await api.uploadAttachment(ID.UPLOAD_TARGET, smallFile(), 'format-test.txt');
  507 |     expect([200, 422]).toContain(res.status);
  508 |   });
  509 | 
  510 |   test('D4. 响应 success=true，message 正确', async ({ request }) => {
  511 |     const api = new PersonnelChangeRequestApi(request, authToken);
  512 |     const res = await api.uploadAttachment(ID.UPLOAD_TARGET, smallFile(), 'msg-test.txt');
  513 |     if (res.status === 200) {
  514 |       assertSuccess(res.body);
  515 |       expect(res.body.message).toContain('成功');
  516 |     }
```