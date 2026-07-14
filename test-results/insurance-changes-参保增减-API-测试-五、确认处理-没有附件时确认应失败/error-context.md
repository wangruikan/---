# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: insurance-changes.spec.ts >> 参保增减 API 测试 >> 五、确认处理 >> 没有附件时确认应失败
- Location: tests\api\insurance-changes.spec.ts:504:9

# Error details

```
Error: expect(received).toContain(expected) // indexOf

Expected value: 200
Received array: [400, 422]
```

# Test source

```ts
  422 | 
  423 |       const id = listData.data[0].id;
  424 |       const exePath = path.join(__dirname, 'test-invalid.exe');
  425 |       fs.writeFileSync(exePath, 'fake exe content');
  426 | 
  427 |       try {
  428 |         const uploadRes = await uploadFiles(
  429 |           authToken,
  430 |           `/insurance-changes/${id}/upload-attachment`,
  431 |           [exePath],
  432 |           ['test.exe'],
  433 |           ['application/octet-stream']
  434 |         );
  435 |         expect(uploadRes.status).toBe(422);
  436 |         const data = JSON.parse(uploadRes.body);
  437 |         expect(data.success).toBe(false);
  438 |       } finally {
  439 |         fs.unlinkSync(exePath);
  440 |       }
  441 |     });
  442 |   });
  443 | 
  444 |   // ============================================================
  445 |   // 五、确认处理（整单确认）
  446 |   // ============================================================
  447 |   test.describe('五、确认处理', () => {
  448 |     test('process 接口等价于 confirm-process', async ({ request }) => {
  449 |       const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
  450 |         headers: authHeaders(),
  451 |         params: { account_set_id: ACCOUNT_SET_ID, status: 'submitted', per_page: 10 },
  452 |       });
  453 |       const listData = await listRes.json();
  454 |       const withAttachment = listData.data.find(
  455 |         (item: any) => item.attachments && item.attachments.length > 0
  456 |       );
  457 |       if (!withAttachment) {
  458 |         test.skip();
  459 |         return;
  460 |       }
  461 | 
  462 |       const id = withAttachment.id;
  463 |       const response = await request.post(`${BASE_URL}/insurance-changes/${id}/process`, {
  464 |         headers: authHeaders(),
  465 |         data: {},
  466 |       });
  467 |       expect([200, 400, 422]).toContain(response.status());
  468 |       const data = await response.json();
  469 |       expect(data).toHaveProperty('success');
  470 |     });
  471 | 
  472 |     test('整单确认成功后状态为 completed', async ({ request }) => {
  473 |       const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
  474 |         headers: authHeaders(),
  475 |         params: { account_set_id: ACCOUNT_SET_ID, status: 'submitted', per_page: 10 },
  476 |       });
  477 |       const listData = await listRes.json();
  478 |       const withAttachment = listData.data.find(
  479 |         (item: any) => item.attachments && item.attachments.length > 0
  480 |       );
  481 |       if (!withAttachment) {
  482 |         test.skip();
  483 |         return;
  484 |       }
  485 | 
  486 |       const id = withAttachment.id;
  487 |       const response = await request.put(`${BASE_URL}/insurance-changes/${id}/confirm-process`, {
  488 |         headers: authHeaders(),
  489 |         data: {},
  490 |       });
  491 |       if (response.status() !== 200) {
  492 |         console.log('确认失败，可能缺少条件:', await response.json());
  493 |         test.skip();
  494 |         return;
  495 |       }
  496 |       const data = await response.json();
  497 |       expect(data.success).toBe(true);
  498 |       expect(data.data.status).toBe('completed');
  499 |       expect(data.data.fully_confirmed).toBe(1);
  500 |       expect(data.data.processed_at).toBeTruthy();
  501 |       expect(data.data.completed_at).toBeTruthy();
  502 |     });
  503 | 
  504 |     test('没有附件时确认应失败', async ({ request }) => {
  505 |       const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
  506 |         headers: authHeaders(),
  507 |         params: { account_set_id: ACCOUNT_SET_ID, status: 'pending', per_page: 10 },
  508 |       });
  509 |       const listData = await listRes.json();
  510 |       const noAttachment = listData.data.find(
  511 |         (item: any) => !item.attachments || item.attachments.length === 0
  512 |       );
  513 |       if (!noAttachment) {
  514 |         test.skip();
  515 |         return;
  516 |       }
  517 | 
  518 |       const response = await request.put(
  519 |         `${BASE_URL}/insurance-changes/${noAttachment.id}/confirm-process`,
  520 |         { headers: authHeaders(), data: {} }
  521 |       );
> 522 |       expect([400, 422]).toContain(response.status());
      |                          ^ Error: expect(received).toContain(expected) // indexOf
  523 |     });
  524 | 
  525 |     test('已完成记录重复确认应失败', async ({ request }) => {
  526 |       const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
  527 |         headers: authHeaders(),
  528 |         params: { account_set_id: ACCOUNT_SET_ID, status: 'completed', per_page: 1 },
  529 |       });
  530 |       const listData = await listRes.json();
  531 |       if (listData.data.length === 0) {
  532 |         test.skip();
  533 |         return;
  534 |       }
  535 | 
  536 |       const id = listData.data[0].id;
  537 |       const response = await request.put(`${BASE_URL}/insurance-changes/${id}/confirm-process`, {
  538 |         headers: authHeaders(),
  539 |         data: {},
  540 |       });
  541 |       expect([400, 422]).toContain(response.status());
  542 |     });
  543 |   });
  544 | 
  545 |   // ============================================================
  546 |   // 六、自动导入
  547 |   // ============================================================
  548 |   test.describe('六、自动导入', () => {
  549 |     test('缺少必填字段返回 422', async ({ request }) => {
  550 |       const response = await request.post(`${BASE_URL}/insurance-changes/auto-import`, {
  551 |         headers: authHeaders(),
  552 |         data: {},
  553 |       });
  554 |       expect(response.status()).toBe(422);
  555 |     });
  556 | 
  557 |     test('不存在的 employee_id 返回 422', async ({ request }) => {
  558 |       const response = await request.post(`${BASE_URL}/insurance-changes/auto-import`, {
  559 |         headers: authHeaders(),
  560 |         data: {
  561 |           employee_id: 999999,
  562 |           project_id: 1,
  563 |           account_set_id: ACCOUNT_SET_ID,
  564 |         },
  565 |       });
  566 |       expect(response.status()).toBe(422);
  567 |     });
  568 |   });
  569 | 
  570 |   // ============================================================
  571 |   // 七、汇总与导出
  572 |   // ============================================================
  573 |   test.describe('七、汇总与导出', () => {
  574 |     test('获取汇总列表成功', async ({ request }) => {
  575 |       const response = await request.get(`${BASE_URL}/insurance-changes/summaries`, {
  576 |         headers: authHeaders(),
  577 |         params: { account_set_id: ACCOUNT_SET_ID },
  578 |       });
  579 |       expect(response.status()).toBe(200);
  580 |       const data = await response.json();
  581 |       expect(data.success).toBe(true);
  582 |       expect(Array.isArray(data.data)).toBe(true);
  583 |     });
  584 | 
  585 |     test('generate-summary 缺参数返回 422', async ({ request }) => {
  586 |       const response = await request.post(`${BASE_URL}/insurance-changes/generate-summary`, {
  587 |         headers: authHeaders(),
  588 |         data: {},
  589 |       });
  590 |       expect(response.status()).toBe(422);
  591 |     });
  592 | 
  593 |     test('generate-summary 成功', async ({ request }) => {
  594 |       const response = await request.post(`${BASE_URL}/insurance-changes/generate-summary`, {
  595 |         headers: authHeaders(),
  596 |         data: { account_set_id: ACCOUNT_SET_ID },
  597 |       });
  598 |       expect(response.status()).toBe(200);
  599 |       const data = await response.json();
  600 |       expect(data.success).toBe(true);
  601 |       expect(Array.isArray(data.data)).toBe(true);
  602 |     });
  603 | 
  604 |     test('export-summary 成功', async ({ request }) => {
  605 |       const response = await request.post(`${BASE_URL}/insurance-changes/export-summary`, {
  606 |         headers: authHeaders(),
  607 |         data: { account_set_id: ACCOUNT_SET_ID },
  608 |       });
  609 |       expect(response.status()).toBe(200);
  610 |       const data = await response.json();
  611 |       expect(data.success).toBe(true);
  612 |       expect(data).toHaveProperty('data');
  613 |       expect(data).toHaveProperty('filename');
  614 |     });
  615 |   });
  616 | 
  617 |   // ============================================================
  618 |   // 八、契约与稳定性
  619 |   // ============================================================
  620 |   test.describe('八、契约与稳定性', () => {
  621 |     test('列表响应结构契约', async ({ request }) => {
  622 |       const response = await request.get(`${BASE_URL}/insurance-changes`, {
```