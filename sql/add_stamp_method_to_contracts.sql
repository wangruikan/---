-- 给 employee_contracts 表添加 stamp_method 字段
ALTER TABLE employee_contracts ADD COLUMN stamp_method VARCHAR(20) DEFAULT 'online' COMMENT '盖章方式：online-线上盖章，offline-线下盖章' AFTER contract_type;
