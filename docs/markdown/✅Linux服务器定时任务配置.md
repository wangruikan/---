# Linux 服务器定时任务配置指南

## ✅ Linux 配置超级简单！只需1分钟

### 第1步：编辑 Crontab

登录 Linux 服务器，执行：

```bash
crontab -e
```

### 第2步：添加一行定时任务

在打开的编辑器中，**添加以下一行**：

```bash
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

**请将 `/path/to/your/project` 改为您项目的实际路径！**

例如，如果您的项目在 `/var/www/html/re_li_zi_yuan`，则改为：

```bash
* * * * * cd /var/www/html/re_li_zi_yuan && php artisan schedule:run >> /dev/null 2>&1
```

### 第3步：保存退出

- 如果是 `vi/vim` 编辑器：按 `ESC`，输入 `:wq`，回车
- 如果是 `nano` 编辑器：按 `Ctrl + O` 保存，`Ctrl + X` 退出

### ✅ 完成！

就这么简单！配置完成后，每分钟会自动检查是否有定时任务需要执行。

---

## 🔍 验证是否配置成功

### 方法1：查看 Crontab 列表
```bash
crontab -l
```

应该能看到刚才添加的那一行。

### 方法2：查看日志
等待1-2分钟后，查看 Laravel 日志：

```bash
tail -f /path/to/your/project/storage/logs/laravel.log
```

### 方法3：手动测试
```bash
cd /path/to/your/project
php artisan schedule:run
```

如果没有报错，说明配置正确。

---

## 📋 Cron 表达式说明

```
* * * * *  命令
│ │ │ │ │
│ │ │ │ └─── 星期几 (0-7, 0和7都是周日)
│ │ │ └───── 月份 (1-12)
│ │ └─────── 日期 (1-31)
│ └───────── 小时 (0-23)
└─────────── 分钟 (0-59)
```

`* * * * *` 表示：每分钟执行一次

---

## 💡 常见问题

### Q1：如何查看 Cron 是否在运行？
```bash
# 查看 Cron 服务状态
sudo systemctl status cron       # Ubuntu/Debian
sudo systemctl status crond      # CentOS/RHEL

# 如果未启动，启动它
sudo systemctl start cron        # Ubuntu/Debian
sudo systemctl start crond       # CentOS/RHEL

# 设置开机自启
sudo systemctl enable cron       # Ubuntu/Debian
sudo systemctl enable crond      # CentOS/RHEL
```

### Q2：找不到 php 命令？
使用 PHP 的完整路径：

```bash
# 先找到 PHP 路径
which php
# 输出例如：/usr/bin/php

# 修改 Crontab，使用完整路径
* * * * * cd /var/www/html/re_li_zi_yuan && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

### Q3：想看执行日志？
将输出重定向到日志文件：

```bash
* * * * * cd /var/www/html/re_li_zi_yuan && php artisan schedule:run >> /var/www/html/re_li_zi_yuan/storage/logs/cron.log 2>&1
```

然后查看日志：
```bash
tail -f /var/www/html/re_li_zi_yuan/storage/logs/cron.log
```

### Q4：权限问题？
确保 Laravel 的 storage 和 bootstrap/cache 目录有写权限：

```bash
cd /var/www/html/re_li_zi_yuan
sudo chmod -R 775 storage bootstrap/cache
sudo chown -R www-data:www-data storage bootstrap/cache  # www-data 改为您的用户
```

---

## 🎯 完整示例

假设您的项目路径是 `/var/www/html/re_li_zi_yuan`：

```bash
# 1. 编辑 Crontab
crontab -e

# 2. 添加以下内容（根据实际路径修改）
* * * * * cd /var/www/html/re_li_zi_yuan && php artisan schedule:run >> /dev/null 2>&1

# 3. 保存退出（vim: ESC 后输入 :wq）

# 4. 验证
crontab -l

# 5. 手动测试
cd /var/www/html/re_li_zi_yuan
php artisan schedule:run

# 6. 查看 Laravel 日志
tail -f storage/logs/laravel.log
```

---

## ✅ 配置后的效果

配置完成后，系统会：

- ✅ 每分钟自动检查是否有定时任务需要执行
- ✅ 每月1日早上8点自动生成交付记录
- ✅ 每月最后一天晚上20点自动检查未交付
- ✅ 执行所有其他 Laravel 定时任务

**完全自动，无需手动干预！**

---

## 🔥 快速复制粘贴（改路径即可）

```bash
# 编辑 Crontab
crontab -e

# 添加以下内容（⚠️ 修改项目路径）
* * * * * cd /var/www/html/re_li_zi_yuan && php artisan schedule:run >> /dev/null 2>&1
```

**就这一行！配置完成！**

---

## 🆚 Linux vs Windows 配置对比

| 项目 | Linux | Windows |
|------|-------|---------|
| 配置方式 | Crontab（一行命令） | 任务计划程序（图形界面） |
| 配置时间 | 1分钟 | 5分钟 |
| 难度 | ⭐ 超简单 | ⭐⭐ 简单 |
| 稳定性 | ⭐⭐⭐⭐⭐ 非常稳定 | ⭐⭐⭐⭐ 稳定 |

**Linux 配置更简单！**

---

## 📞 需要帮助？

如果配置后定时任务不执行：

1. **检查 Cron 服务是否运行**：`sudo systemctl status cron`
2. **检查路径是否正确**：`cd /your/project/path && php artisan schedule:run`
3. **检查权限**：确保 storage 目录可写
4. **查看日志**：`tail -f storage/logs/laravel.log`

一般来说，配置后立即生效，非常简单！✅

