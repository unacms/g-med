SET @sName = 'bx_mssql_migration';

-- STUDIO: page & widget
INSERT INTO `sys_std_pages`(`index`, `name`, `header`, `caption`, `icon`) VALUES
(3, @sName, '_bx_mssql_migration_wgt_cpt', '_bx_mssql_migration_wgt_cpt', 'bx_mssql_migration@modules/boonex/mssql_migration/|std-icon.svg');
SET @iPageId = LAST_INSERT_ID();

SET @iParentPageId = (SELECT `id` FROM `sys_std_pages` WHERE `name` = 'home');
SET @iParentPageOrder = (SELECT MAX(`order`) FROM `sys_std_pages_widgets` WHERE `page_id` = @iParentPageId);
INSERT INTO `sys_std_widgets` (`page_id`, `module`, `url`, `click`, `icon`, `caption`, `cnt_notices`, `cnt_actions`) VALUES
(@iPageId, @sName, '{url_studio}module.php?name=bx_mssql_migration', '', 'bx_mssql_migration@modules/boonex/mssql_migration/|std-icon.svg', '_bx_mssql_migration_wgt_cpt', '', 'a:4:{s:6:"module";s:6:"system";s:6:"method";s:11:"get_actions";s:6:"params";a:0:{}s:5:"class";s:18:"TemplStudioModules";}');

INSERT INTO `sys_std_pages_widgets` (`page_id`, `widget_id`, `order`) VALUES
(@iParentPageId, LAST_INSERT_ID(), IF(ISNULL(@iParentPageOrder), 1, @iParentPageOrder + 1));


CREATE TABLE IF NOT EXISTS `bx_mssql_transfers` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`module` varchar(50) NOT NULL,
	`number` varchar(50) NOT NULL,
	`status` enum('not_started','started','finished','error') NOT NULL,
	`status_text` varchar(255) NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE  `module` (  `module` )
);

CREATE TABLE IF NOT EXISTS `bx_mssql_config` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`name` varchar(100) NOT NULL,
	`value` varchar(150) NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE `name` (`name`)
);
