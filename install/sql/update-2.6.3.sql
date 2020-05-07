ALTER TABLE glpi_plugin_metademands_configs ADD `display_type` tinyint(1) default 1;

ALTER TABLE `glpi_plugin_metademands_tickettasks` add `users_id_validate` int(11) NOT NULL default '0';
ALTER TABLE `glpi_plugin_metademands_tickettasks` add `_add_validation` int(11) NOT NULL default '0';
ALTER TABLE `glpi_plugin_metademands_fields` CHANGE `plugin_metademands_tasks_id` `plugin_metademands_tasks_id` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `glpi_plugin_metademands_tickets_metademands` ADD `tickettemplates_id` INT(11) NOT NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_metademands_fields` add `hidden_link` varchar(255) NOT NULL default '';
ALTER TABLE `glpi_plugin_metademands_fields` add `to_hide` int(1) NOT NULL default '0';