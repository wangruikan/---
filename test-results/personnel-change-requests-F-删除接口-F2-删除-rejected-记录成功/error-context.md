# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: personnel-change-requests.spec.ts >> F. 删除接口 >> F2. 删除 rejected 记录成功
- Location: tests\api\personnel-change-requests.spec.ts:827:7

# Error details

```
Error: expect(received).toBe(expected) // Object.is equality

Expected: 200
Received: 500
```

# Test source

```ts
  730 |     });
  731 |     expect(res.status).toBe(400);
  732 |     assertFail(res.body);
  733 |   });
  734 | 
  735 |   test('E12. 缺少 personnel_change_request_id 返回 422', async ({ request }) => {
  736 |     const api = new PersonnelChangeRequestApi(request, authToken);
  737 |     const res = await api.completeSubmission({ current_account_set_id: ACCOUNT_SET_A });
  738 |     expect(res.status).toBe(422);
  739 |     assertFail(res.body);
  740 |   });
  741 | 
  742 |   test('E13. 缺少 current_account_set_id 返回 422', async ({ request }) => {
  743 |     const api = new PersonnelChangeRequestApi(request, authToken);
  744 |     const res = await api.completeSubmission({ personnel_change_request_id: ID.PENDING_ADD });
  745 |     expect(res.status).toBe(422);
  746 |     assertFail(res.body);
  747 |   });
  748 | 
  749 |   test('E14. current_account_set_id 不存在返回 422', async ({ request }) => {
  750 |     const api = new PersonnelChangeRequestApi(request, authToken);
  751 |     const res = await api.completeSubmission({
  752 |       personnel_change_request_id: ID.PENDING_ADD,
  753 |       current_account_set_id: '999999',
  754 |     });
  755 |     expect(res.status).toBe(422);
  756 |     assertFail(res.body);
  757 |   });
  758 | 
  759 |   test('E15. personnel_change_request_id 不存在返回 422', async ({ request }) => {
  760 |     const api = new PersonnelChangeRequestApi(request, authToken);
  761 |     const res = await api.completeSubmission({
  762 |       personnel_change_request_id: '999999',
  763 |       current_account_set_id: ACCOUNT_SET_A,
  764 |     });
  765 |     expect(res.status).toBe(422);
  766 |     assertFail(res.body);
  767 |   });
  768 | 
  769 |   test('E16. 先上传附件再提交，验证附件带入审批流程', async ({ request }) => {
  770 |     const api = new PersonnelChangeRequestApi(request, authToken);
  771 |     // 查看 WITH_ATTACHMENT 的当前状态
  772 |     const before = await api.show(ID.WITH_ATTACHMENT);
  773 |     if (before.body.data.status !== 'pending') { test.skip(); return; }
  774 | 
  775 |     // 先上传一个附件
  776 |     await api.uploadAttachment(ID.WITH_ATTACHMENT, smallFile(), 'flow-attach.txt');
  777 | 
  778 |     // 再提交
  779 |     const res = await api.completeSubmission({
  780 |       personnel_change_request_id: ID.WITH_ATTACHMENT,
  781 |       current_account_set_id: ACCOUNT_SET_A,
  782 |     });
  783 |     if (res.status === 200) {
  784 |       expect(res.body.data.approval_instance).toBeTruthy();
  785 |     }
  786 |   });
  787 | 
  788 |   test('E17. 提交其他账套的 request_id', async ({ request }) => {
  789 |     const api = new PersonnelChangeRequestApi(request, authToken);
  790 |     const res = await api.completeSubmission({
  791 |       personnel_change_request_id: ID.OTHER_ACCOUNT,
  792 |       current_account_set_id: ACCOUNT_SET_A,
  793 |     });
  794 |     if (res.status === 200) {
  795 |       console.error('🚨 跨账套越权：complete-submission 可对其他账套 request_id 发起审批');
  796 |     }
  797 |   });
  798 | 
  799 |   test('E18. 状态流转验证 - pending -> in_approval', async ({ request }) => {
  800 |     const api = new PersonnelChangeRequestApi(request, authToken);
  801 |     const res = await api.show(ID.SUBMIT_PENDING);
  802 |     expect(res.body.data.status).toBe('in_approval');
  803 |     expect(res.body.data.approval_flow_id).toBeTruthy();
  804 |   });
  805 | 
  806 |   test('E18b. 状态流转验证 - rejected -> in_approval', async ({ request }) => {
  807 |     const api = new PersonnelChangeRequestApi(request, authToken);
  808 |     const res = await api.show(ID.SUBMIT_REJECTED);
  809 |     expect(res.body.data.status).toBe('in_approval');
  810 |   });
  811 | });
  812 | 
  813 | // ============================================================
  814 | // F. 删除接口 DELETE /api/personnel-change-requests/{id}
  815 | // ============================================================
  816 | test.describe('F. 删除接口', () => {
  817 |   test('F1. 删除 pending 记录成功', async ({ request }) => {
  818 |     const api = new PersonnelChangeRequestApi(request, authToken);
  819 |     const res = await api.destroy(ID.DELETE_PENDING);
  820 |     expect(res.status).toBe(200);
  821 |     assertSuccess(res.body);
  822 |     expect(res.body.message).toContain('删除');
  823 |     assertJsonHeaders(res.headers);
  824 |     assertResponseTime(res.elapsed);
  825 |   });
  826 | 
  827 |   test('F2. 删除 rejected 记录成功', async ({ request }) => {
  828 |     const api = new PersonnelChangeRequestApi(request, authToken);
  829 |     const res = await api.destroy(ID.DELETE_REJECTED);
> 830 |     expect(res.status).toBe(200);
      |                        ^ Error: expect(received).toBe(expected) // Object.is equality
  831 |     assertSuccess(res.body);
  832 |   });
  833 | 
  834 |   test('F3. 删除后再查详情返回 404', async ({ request }) => {
  835 |     const api = new PersonnelChangeRequestApi(request, authToken);
  836 |     const res = await api.show(ID.DELETE_PENDING);
  837 |     expect(res.status).toBe(404);
  838 |   });
  839 | 
  840 |   test('F4. 删除后列表中不再出现该记录', async ({ request }) => {
  841 |     const api = new PersonnelChangeRequestApi(request, authToken);
  842 |     const res = await api.list({ current_account_set_id: ACCOUNT_SET_A, per_page: 100 });
  843 |     const found = res.body.data.data.find((r: any) => r.id === Number(ID.DELETE_PENDING));
  844 |     expect(found).toBeUndefined();
  845 |   });
  846 | 
  847 |   test('F5. 删除 in_approval 记录返回 403', async ({ request }) => {
  848 |     const api = new PersonnelChangeRequestApi(request, authToken);
  849 |     const res = await api.destroy(ID.IN_APPROVAL_ADD);
  850 |     expect(res.status).toBe(403);
  851 |     assertFail(res.body);
  852 |     expect(res.body.message).toContain('驳回');
  853 |   });
  854 | 
  855 |   test('F6. 删除 approved 记录返回 403', async ({ request }) => {
  856 |     const api = new PersonnelChangeRequestApi(request, authToken);
  857 |     const res = await api.destroy(ID.APPROVED_REMOVE);
  858 |     expect(res.status).toBe(403);
  859 |     assertFail(res.body);
  860 |   });
  861 | 
  862 |   test('F7. 删除不存在 ID 的行为', async ({ request }) => {
  863 |     const api = new PersonnelChangeRequestApi(request, authToken);
  864 |     const res = await api.destroy('999999');
  865 |     expect([404, 500]).toContain(res.status);
  866 |     if (res.status === 500) {
  867 |       console.warn('⚠️ 删除不存在 ID 返回 500 而非 404，建议改进错误处理');
  868 |     }
  869 |   });
  870 | 
  871 |   test('F8. 删除其他账套的记录', async ({ request }) => {
  872 |     const api = new PersonnelChangeRequestApi(request, authToken);
  873 |     const res = await api.destroy(ID.OTHER_ACCOUNT);
  874 |     if (res.status === 200) {
  875 |       console.error('🚨 跨账套越权：delete 可删除其他账套数据');
  876 |     }
  877 |   });
  878 | });
  879 | 
  880 | // ============================================================
  881 | // G. 账套隔离与越权专项
  882 | // ============================================================
  883 | test.describe('G. 账套隔离与越权专项', () => {
  884 |   test('G1. show 接口跨账套读取', async ({ request }) => {
  885 |     const api = new PersonnelChangeRequestApi(request, authToken);
  886 |     const res = await api.show(ID.OTHER_ACCOUNT);
  887 |     if (res.status === 200 && res.body.success) {
  888 |       console.error(`🚨 [G1] show 可跨账套读取: id=${ID.OTHER_ACCOUNT}, account_set_id=${res.body.data.account_set_id}`);
  889 |       expect(res.body.data.account_set_id).toBe(Number(ACCOUNT_SET_B));
  890 |     }
  891 |   });
  892 | 
  893 |   test('G2. upload-attachment 跨账套操作', async ({ request }) => {
  894 |     const api = new PersonnelChangeRequestApi(request, authToken);
  895 |     const res = await api.uploadAttachment(ID.OTHER_ACCOUNT, smallFile(), 'g2-cross.txt');
  896 |     if (res.status === 200) {
  897 |       console.error(`🚨 [G2] upload-attachment 可对其他账套 request_id 操作: id=${ID.OTHER_ACCOUNT}`);
  898 |     }
  899 |   });
  900 | 
  901 |   test('G3. complete-submission 跨账套发起审批', async ({ request }) => {
  902 |     const api = new PersonnelChangeRequestApi(request, authToken);
  903 |     const res = await api.completeSubmission({
  904 |       personnel_change_request_id: ID.OTHER_ACCOUNT,
  905 |       current_account_set_id: ACCOUNT_SET_A,
  906 |     });
  907 |     if (res.status === 200) {
  908 |       console.error(`🚨 [G3] complete-submission 可对其他账套 request_id 发起审批: id=${ID.OTHER_ACCOUNT}`);
  909 |     }
  910 |   });
  911 | 
  912 |   test('G4. delete 跨账套删除', async ({ request }) => {
  913 |     const api = new PersonnelChangeRequestApi(request, authToken);
  914 |     const res = await api.destroy(ID.OTHER_ACCOUNT);
  915 |     if (res.status === 200) {
  916 |       console.error(`🚨 [G4] delete 可删除其他账套数据: id=${ID.OTHER_ACCOUNT}`);
  917 |     }
  918 |   });
  919 | 
  920 |   test('G5. 综合越权报告', async ({ request }) => {
  921 |     const api = new PersonnelChangeRequestApi(request, authToken);
  922 |     const findings: string[] = [];
  923 | 
  924 |     const showRes = await api.show(ID.OTHER_ACCOUNT);
  925 |     if (showRes.status === 200 && showRes.body.success) {
  926 |       findings.push('show: 可跨账套读取');
  927 |     }
  928 | 
  929 |     const listRes = await api.list({ current_account_set_id: ACCOUNT_SET_B, per_page: 100 });
  930 |     if (listRes.status === 200 && listRes.body.success) {
```