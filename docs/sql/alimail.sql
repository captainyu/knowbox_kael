alter table `dingtalk_user`
  add `email_created_ali` tinyint(4) NOT NULL DEFAULT '0' COMMENT '邮箱创建状态 0创建中 1已创建 2创建异常 3注销中 4已注销' AFTER `email_created`;
