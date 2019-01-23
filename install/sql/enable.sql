SET @sName = 'bx_mssql_migration';

-- SETTINGS
SET @iTypeOrder = (SELECT MAX(`order`) FROM `sys_options_types` WHERE `group` = 'modules');
INSERT INTO `sys_options_types`(`group`, `name`, `caption`, `icon`, `order`) VALUES
('modules',  @sName, '_bx_mssql_migration_wgt_cpt', 'bx_mssql_migration@modules/boonex/bx_mssql_migration/|std-icon.svg', IF(ISNULL(@iTypeOrder), 1, @iTypeOrder + 1));
SET @iTypeId = LAST_INSERT_ID();

INSERT INTO `sys_options_categories` (`type_id`, `name`, `caption`, `order`)
VALUES (@iTypeId,  @sName, '_bx_mssql_migration_wgt_cpt', 1);
SET @iCategId = LAST_INSERT_ID();

INSERT INTO `sys_options` (`name`, `value`, `category_id`, `caption`, `type`, `check`, `check_error`, `extra`, `order`) VALUES
('bx_mssql_migration_healthy_day', '180', @iCategId, '_bx_mssql_migration_healthy_day_profile_id', 'digit', '', '', '', 0),
('bx_mssql_migration_memberships', '10', @iCategId, '_bx_mssql_migration_memberships', 'select', '', '', 'a:4:{s:6:"module";s:6:"system";s:6:"method";s:15:"get_memberships";s:6:"params";a:4:{s:11:"purchasable";b:0;s:6:"active";b:1;s:9:"translate";b:1;s:22:"filter_out_auto_levels";b:1;}s:5:"class";s:16:"TemplAclServices";}', 0);


-- GRIDS:
INSERT INTO `sys_objects_grid` (`object`, `source_type`, `source`, `table`, `field_id`, `field_order`, `field_active`, `paginate_url`, `paginate_per_page`, `paginate_simple`, `paginate_get_start`, `paginate_get_per_page`, `filter_fields`, `filter_fields_translatable`, `filter_mode`, `sorting_fields`, `sorting_fields_translatable`, `visible_for_levels`, `override_class_name`, `override_class_file`) VALUES
('bx_mssql_migration_transfers', 'Sql', 'SELECT `id`, `module`, `number`, `status`, `status_text`, '''' as `datatype` FROM `bx_mssql_transfers`', 'bx_mssql_transfers', 'module', 'id', '', '', 0, '', 'start', '', 'path', '', 'auto', 'module', '', 2147483647, 'BxMSSQLMTransfers', 'modules/boonex/mssql_migration/classes/BxMSSQLMTransfers.php');

INSERT INTO `sys_grid_fields` (`object`, `name`, `title`, `width`, `translatable`, `chars_limit`, `params`, `order`) VALUES
('bx_mssql_migration_transfers', 'checkbox', '', '1%', 0, 0, '', 1),
('bx_mssql_migration_transfers', 'module', '_bx_mssql_migration_modules_name', '30%', 0, 0, '', 2),
('bx_mssql_migration_transfers', 'number', '_bx_mssql_migration_modules_records_number', '10%', 0, 0, '', 3),
('bx_mssql_migration_transfers', 'status_text', '_bx_mssql_migration_modules_status', '45%', 0, 0, '', 4),
('bx_mssql_migration_transfers', 'actions', '', '14%', 0, 0, '', 5),
('bx_mssql_migration_transfers_path', 'title', '_bx_mssql_migration_modules_path', '100%', 0, 0, '', 0);

INSERT INTO `sys_grid_actions` (`object`, `type`, `name`, `title`, `icon`, `icon_only`, `confirm`, `order`) VALUES
('bx_mssql_migration_transfers', 'bulk', 'run', '_bx_mssql_migration_start_transfer', '', 0, 1, 1),
('bx_mssql_migration_transfers', 'independent', 'update', '_bx_mssql_migration_update_transfer', '', 0, 1, 2),
('bx_mssql_migration_transfers', 'single', 'remove', '_bx_mssql_migration_remove_content', 'trash', 1, 1, 2),
('bx_mssql_migration_transfers', 'single', 'clean', '_bx_mssql_migration_clean', 'eraser ', 1, 1, 1);

INSERT INTO `sys_alerts_handlers` (`name`, `class`, `file`, `service_call`) VALUES
(@sName, 'BxMSSQLMAlertsResponse', 'modules/boonex/mssql_migration/classes/BxMSSQLMAlertsResponse.php', '');
SET @iHandler := LAST_INSERT_ID();

INSERT INTO `sys_alerts` (`unit`, `action`, `handler_id`) VALUES
('system', 'encrypt_password_after', @iHandler),
('account', 'edit', @iHandler);
