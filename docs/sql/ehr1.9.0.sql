CREATE TABLE `dingtalk_attendance_schedule` (
  `plan_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '排班id',
  `schedule_date` DATE NOT NULL DEFAULT '0000-00-00' COMMENT '排班日期',
  `check_type` varchar(50) NOT NULL DEFAULT '' COMMENT '打卡类型，OnDuty表示上班打卡，OffDuty表示下班打卡',
  `approve_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '审批id，结果集中没有的话表示没有审批单',
  `user_id` varchar(100) NOT NULL DEFAULT '' COMMENT '人员ID',
  `class_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '考勤班次id',
  `class_setting_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '班次配置id，结果集中没有的话表示使用全局班次配置',
  `plan_check_time` TIMESTAMP not NULL DEFAULT '0000-00-00 00:00:00' COMMENT '打卡时间',
  `group_id` bigint(20) NOT NULL DEFAULT 0 COMMENT '考勤组id',
  `dingtalk_department_id` bigint(20)  NOT NULL DEFAULT 0 COMMENT '钉钉部门ID',
  `dingtalk_department_name` VARCHAR(100)  NOT NULL DEFAULT 0 COMMENT '钉钉部门名称',
  `dingtalk_subroot_id` bigint(20)  NOT NULL DEFAULT 0 COMMENT '钉钉一级部门ID',
  `dingtalk_subroot_name` VARCHAR(100)  NOT NULL DEFAULT 0 COMMENT '钉钉一级部门名称',
  `status` int(11) NOT NULL DEFAULT '0',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`plan_id`),
  INDEX idx_scheduledate(`schedule_date`),
  INDEX idx_userid_scheduledate(user_id,`schedule_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `dingtalk_attendance_result` (
  `id` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '唯一标识id',
  `group_id` bigint(20) NOT NULL DEFAULT 0 COMMENT '考勤组id',
  `plan_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '排班id',
  `record_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '打卡记录ID',
  `work_date` DATE NOT NULL DEFAULT '0000-00-00' COMMENT '工作日',
  `user_id` varchar(100) NOT NULL DEFAULT '' COMMENT '人员ID',
  `check_type` varchar(50) NOT NULL DEFAULT '' COMMENT '打卡类型，OnDuty表示上班打卡，OffDuty表示下班打卡',
  `time_result`  VARCHAR(50) NOT NULL DEFAULT '' COMMENT '时间结果Normal：正常;Early：早退;Late：迟到;SeriousLate：严重迟到；Absenteeism：旷工迟到；NotSigned：未打卡',
  `location_result` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '位置结果Normal：范围内；Outside：范围外；NotSigned：未打卡',
  `approve_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '审批id，结果集中没有的话表示没有审批单',
  `proc_inst_id` varchar(50) NOT NULL DEFAULT '' COMMENT '关联的审批实例id',
  `base_check_time` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '计算迟到和早退，基准时间',
  `user_check_time` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '实际打卡时间',
  `source_type` varchar(50) NOT NULL DEFAULT '' COMMENT '数据来源ATM：考勤机;BEACON：IBeacon;DING_ATM：钉钉考勤机;USER：用户打卡;BOSS：老板改签;APPROVE：审批系统;SYSTEM：考勤系统;AUTO_CHECK：自动打卡 ',
  `dingtalk_department_id` bigint(20)  NOT NULL DEFAULT 0 COMMENT '钉钉部门ID',
  `dingtalk_department_name` VARCHAR(100)  NOT NULL DEFAULT 0 COMMENT '钉钉部门名称',
  `dingtalk_subroot_id` bigint(20)  NOT NULL DEFAULT 0 COMMENT '钉钉一级部门ID',
  `dingtalk_subroot_name` VARCHAR(100)  NOT NULL DEFAULT 0 COMMENT '钉钉一级部门名称',
  `status` int(11) NOT NULL DEFAULT '0',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX idx_scheduledate_userid(`work_date`,`user_id`),
  INDEX idx_userid(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `dingtalk_attendance_record` (
  `id` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '唯一标识id',
  `work_date` DATE NOT NULL DEFAULT '0000-00-00' COMMENT '工作日',
  `user_id` varchar(100) NOT NULL DEFAULT '' COMMENT '人员ID',
  `check_type` varchar(50) NOT NULL DEFAULT '' COMMENT '打卡类型，OnDuty表示上班打卡，OffDuty表示下班打卡',
  `source_type` varchar(50) NOT NULL DEFAULT '' COMMENT '数据来源ATM：考勤机;BEACON：IBeacon;DING_ATM：钉钉考勤机;USER：用户打卡;BOSS：老板改签;APPROVE：审批系统;SYSTEM：考勤系统;AUTO_CHECK：自动打卡 ',
  `device_id` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '设备id',
  `user_address` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '用户打卡地址',
  `record_ext` TEXT COMMENT '记录详情',
  `status` int(11) NOT NULL DEFAULT '0',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX idx_scheduledate_userid(`work_date`,`user_id`),
  INDEX idx_userid(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



CREATE TABLE `dingcan_order` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `supplier` int(11)  NOT NULL DEFAULT 0 COMMENT '1美餐 2竹蒸笼',
  `meal_date` DATE NOT NULL DEFAULT '0000-00-00' COMMENT '日期',
  `meal_time` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '饭点',
  `order_id` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '订餐ID',
  `kael_id` bigint(20) NOT NULL DEFAULT 0 COMMENT 'kael用户ID',
  `dingtalk_department_id` bigint(20)  NOT NULL DEFAULT 0 COMMENT '钉钉部门ID',
  `dingtalk_department_name` VARCHAR(100)  NOT NULL DEFAULT 0 COMMENT '钉钉部门名称',
  `dingtalk_subroot_id` bigint(20)  NOT NULL DEFAULT 0 COMMENT '钉钉一级部门ID',
  `dingtalk_subroot_name` VARCHAR(100)  NOT NULL DEFAULT 0 COMMENT '钉钉一级部门名称',
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '订餐金额',
  `order_ext` TEXT,
  `status` int(11) NOT NULL DEFAULT '0',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE INDEX idx_supplier_orderid(`supplier`,`order_id`),
  INDEX idx_kaelid_mealdate(`kael_id`,`meal_date`),
  INDEX idx_mealtime(`meal_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `work_day_config` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `day` DATE NOT NULL DEFAULT '0000-00-00' COMMENT '日期',
  `is_work_day` TINYINT(4) NOT NULL DEFAULT 0 COMMENT '是否工作日',
  `is_allow_dingcan` TINYINT(4) NOT NULL DEFAULT 0 COMMENT '是否允许订餐',
  `status` int(11) NOT NULL DEFAULT '0',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE INDEX idx_day(`day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


