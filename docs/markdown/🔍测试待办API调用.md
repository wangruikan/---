# 🔍 测试待办API调用

## 问题诊断

### 现状
- ✅ 数据库中确实有工资表审批的待办记录
- ✅ 用户5有7条待办（包括2条工资表审批）
- ❌ 前端"我的待办"列表中看不到

### 可能原因
1. 浏览器缓存（最可能）
2. API请求参数问题
3. 响应数据解析问题

---

## 🔧 解决步骤

### 第1步：强制刷新浏览器

**重要！必须先清除缓存：**

1. 按 `Ctrl + Shift + Delete` 打开清除浏览器数据
2. 选择"缓存的图片和文件"
3. 时间范围选择"全部"
4. 点击"清除数据"
5. 然后按 `Ctrl + F5` 强制刷新页面

---

### 第2步：在浏览器控制台测试API

1. 打开审批管理页面
2. 按 `F12` 打开开发者工具
3. 切换到 `Console` 标签
4. 复制粘贴以下代码并回车：

```javascript
// 测试获取待办列表
fetch('/api/approvals/my-tasks?page=1&per_page=20&current_account_set_id=1', {
  method: 'GET',
  headers: {
    'Authorization': 'Bearer ' + localStorage.getItem('token'),
    'X-Account-Set-Id': localStorage.getItem('current_account_set_id'),
    'Accept': 'application/json'
  }
})
.then(res => res.json())
.then(data => {
  console.log('待办列表总数:', data.total)
  console.log('待办数据:', data.data)
  
  // 筛选出工资表审批
  const salaryApprovals = data.data.filter(item => 
    item.instance && item.instance.business_type === '工资表审批'
  )
  
  console.log('工资表审批待办数量:', salaryApprovals.length)
  console.log('工资表审批详情:', salaryApprovals)
})
.catch(err => console.error('API调用失败:', err))
```

### 预期结果

控制台应该输出：
```
待办列表总数: 7
待办数据: (7) [{...}, {...}, ...]
工资表审批待办数量: 2
工资表审批详情: (2) [{...}, {...}]
```

---

### 第3步：检查Network请求

1. 在开发者工具中切换到 `Network` 标签
2. 刷新"审批管理"页面或切换到"我的待办"标签
3. 找到 `my-tasks` 请求
4. 点击查看详情：
   - **Request URL**: 应该包含 `current_account_set_id=1`
   - **Request Headers**: 应该包含 `X-Account-Set-Id: 1`
   - **Response**: 查看返回的数据

---

### 第4步：查看返回数据

在 Network → my-tasks → Response 中，应该看到类似这样的数据：

```json
{
  "success": true,
  "data": [
    {
      "id": 305,
      "instance_id": 103,
      "step_name": "经办",
      "approver_id": 5,
      "approver_name": "e1",
      "status": "pending",
      "instance": {
        "id": 103,
        "business_type": "工资表审批",
        "business_id": 8,
        "current_step": 1,
        "total_steps": 3,
        "status": "pending",
        "creator": {
          "id": 5,
          "name": "e1"
        }
      },
      "business_data": {
        "id": 8,
        "project_id": 4,
        "month": "2025-10",
        "approval_type": "online",
        "project": {
          "id": 4,
          "name": "公园项目"
        },
        "submitter": {
          "id": 5,
          "name": "e1"
        }
      }
    },
    ... 其他待办 ...
  ],
  "total": 7
}
```

---

## 🐛 如果还是看不到

### 可能原因1：前端过滤了数据

检查前端代码是否有过滤逻辑：

```javascript
// 在 src/views/Approvals/index.vue 中搜索
approvals.value = response.data || []
```

在这行代码前后，看看是否有 `.filter()` 或其他过滤逻辑。

### 可能原因2：业务数据加载失败

查看浏览器控制台是否有错误：
```
Error: Cannot read property 'project' of undefined
```

如果有这类错误，说明 `business_data` 没有正确加载。

### 可能原因3：条件渲染问题

检查表格中是否有 `v-if` 条件隐藏了某些行。

---

## 📊 数据库验证（已确认✅）

```sql
-- 查看用户5的待办
SELECT ar.id, ar.instance_id, ar.step_name, ar.status, 
       ai.business_type, ai.business_id
FROM approval_records ar
JOIN approval_instances ai ON ar.instance_id = ai.id
WHERE ar.approver_id = 5 
AND ar.status = 'pending'
AND ai.account_set_id = 1;
```

**结果：7条记录，包括2条工资表审批（instance_id: 103, 104）**

---

## ✅ 最可能的解决方案

**99%的可能性是浏览器缓存问题！**

请务必：
1. ✅ 清除浏览器缓存（Ctrl + Shift + Delete）
2. ✅ 关闭浏览器
3. ✅ 重新打开浏览器
4. ✅ 重新登录
5. ✅ 进入审批管理 → 我的待办

应该就能看到了！🎉

---

## 🔍 调试命令（后端）

如果还是不行，可以在后端添加日志：

```php
// app/Http/Controllers/ApprovalFlowController.php
public function myTasks(Request $request)
{
    $user = $request->user();
    $accountSetId = $request->header('X-Account-Set-Id') ?: $request->input('current_account_set_id');

    \Log::info('我的待办查询', [
        'user_id' => $user->id,
        'account_set_id' => $accountSetId,
        'headers' => $request->headers->all()
    ]);

    // ... 原有代码 ...

    \Log::info('我的待办结果', [
        'total' => $tasks->total(),
        'count' => $tasks->count()
    ]);

    // ... 原有代码 ...
}
```

然后查看日志：
```bash
Get-Content storage/logs/laravel.log -Tail 20 | Select-String "我的待办"
```

---

## 📞 如果问题依然存在

请提供以下信息：

1. 浏览器控制台的截图（包括 Console 和 Network）
2. `my-tasks` API 的完整响应数据
3. 是否有任何错误信息
4. 当前登录的用户ID

我会进一步帮您排查！

