#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
SQL文件兼容性修复脚本
自动修复SQL文件中的JSON字段和字符集问题
"""

import re
import sys
import os

def fix_sql_file(input_file, output_file=None):
    """
    修复SQL文件的兼容性问题
    """
    if not os.path.exists(input_file):
        print(f"错误: 文件 {input_file} 不存在")
        return False
    
    if output_file is None:
        output_file = input_file.replace('.sql', '_fixed.sql')
    
    try:
        with open(input_file, 'r', encoding='utf-8') as f:
            content = f.read()
        
        print(f"开始修复文件: {input_file}")
        
        # 1. 替换字符集排序规则
        content = content.replace('utf8mb4_0900_ai_ci', 'utf8mb4_unicode_ci')
        print("✓ 已修复字符集排序规则")
        
        # 2. 替换JSON字段类型
        content = re.sub(r'\bjson\s+NOT\s+NULL\b', 'text NOT NULL', content, flags=re.IGNORECASE)
        content = re.sub(r'\bjson\s+NULL\b', 'text NULL', content, flags=re.IGNORECASE)
        content = re.sub(r'`\s*json\s*`', '`text`', content, flags=re.IGNORECASE)
        print("✓ 已修复JSON字段类型")
        
        # 3. 修复特定的JSON字段定义
        json_patterns = [
            (r'`learning_resume`\s+json\s+NULL\s+COMMENT\s+\'学习简历\'', 
             '`learning_resume` text NULL COMMENT \'学习简历\''),
            (r'`work_experience`\s+json\s+NULL\s+COMMENT\s+\'工作经历\'', 
             '`work_experience` text NULL COMMENT \'工作经历\''),
            (r'`personnel_list`\s+json\s+NOT\s+NULL\s+COMMENT\s+\'人员列表', 
             '`personnel_list` text NOT NULL COMMENT \'人员列表'),
            (r'`attachments`\s+json\s+NULL', 
             '`attachments` text NULL'),
        ]
        
        for pattern, replacement in json_patterns:
            content = re.sub(pattern, replacement, content, flags=re.IGNORECASE)
        
        print("✓ 已修复特定JSON字段")
        
        # 4. 修复索引长度问题
        # 修复 personnel_change_requests 表的索引
        content = re.sub(
            r'UNIQUE\s+INDEX\s+`unique_project_month_type`\s*\(\s*`project_id`\s*,\s*`month`\s*,\s*`change_type`\s*,\s*`deleted_at`\s*\)',
            'UNIQUE INDEX `unique_project_month_type`(`project_id`, `month`(50), `change_type`, `deleted_at`)',
            content,
            flags=re.IGNORECASE
        )
        
        # 修复其他可能过长的索引
        content = re.sub(
            r'INDEX\s+([^(]+)\(\s*`([^`]+)`\s*,\s*`month`\s*\)',
            r'INDEX \1(`\2`, `month`(50))',
            content,
            flags=re.IGNORECASE
        )
        
        print("✓ 已修复索引长度问题")
        
        # 5. 保存修复后的文件
        with open(output_file, 'w', encoding='utf-8') as f:
            f.write(content)
        
        print(f"✓ 修复完成! 输出文件: {output_file}")
        
        # 6. 统计修复结果
        json_count = len(re.findall(r'\bjson\b', content, re.IGNORECASE))
        if json_count > 0:
            print(f"⚠️  警告: 仍有 {json_count} 个JSON字段可能需要手动检查")
        else:
            print("✓ 所有JSON字段已修复")
        
        return True
        
    except Exception as e:
        print(f"错误: {str(e)}")
        return False

def main():
    """
    主函数
    """
    print("=== SQL文件兼容性修复工具 ===")
    
    # 获取输入文件
    if len(sys.argv) > 1:
        input_file = sys.argv[1]
    else:
        input_file = input("请输入SQL文件路径: ").strip()
    
    # 获取输出文件（可选）
    output_file = None
    if len(sys.argv) > 2:
        output_file = sys.argv[2]
    
    # 执行修复
    success = fix_sql_file(input_file, output_file)
    
    if success:
        print("\n修复成功! 现在可以将修复后的SQL文件导入到云服务器了。")
    else:
        print("\n修复失败! 请检查错误信息。")

if __name__ == "__main__":
    main()
