alter table dingtalk_department add column path_id varchar(1000) not null default '' comment '部门链id列表' after path_name;