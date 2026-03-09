SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
--  Table structure for `sys_access_resources`
-- ----------------------------
DROP TABLE IF EXISTS `sys_access_resources`;
CREATE TABLE `sys_access_resources` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `alias` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `model_id` int(11) NOT NULL,
  `resource_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `deleted` int(1) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `unico` (`model_id`,`resource_id`,`user_id`,`group_id`) USING BTREE,
  KEY `model_id` (`model_id`) USING BTREE,
  KEY `resource_id` (`resource_id`) USING BTREE,
  KEY `user_id` (`user_id`) USING BTREE,
  KEY `group_id` (`group_id`) USING BTREE,
  KEY `deleted` (`deleted`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
--  Table structure for `sys_actions`
-- ----------------------------
DROP TABLE IF EXISTS `sys_actions`;
CREATE TABLE `sys_actions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `controller` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `action` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `response` enum('html','json','javascript') COLLATE utf8_unicode_ci NOT NULL,
  `permission_id` int(11) NOT NULL,
  `min_access_level` enum('Any','Reader','Contributed','Administrator','Owner') COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `unico` (`controller`,`action`) USING BTREE,
  KEY `controller` (`controller`),
  KEY `action` (`action`)
) ENGINE=InnoDB AUTO_INCREMENT=86 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
--  Records of `sys_actions`
-- ----------------------------
BEGIN;
INSERT INTO `sys_actions` (`id`, `controller`, `action`, `response`, `permission_id`, `min_access_level`)
VALUES
  (1,'Main','index','html',-1,'Any'),
  (2,'Modulos','modules','javascript',-1,'Any'),
  (3,'Menu','tree_grid','json',5,'Administrator'),
  (4,'Menu','load','json',5,'Administrator'),
  (5,'Modulos','combobox','json',5,'Administrator'),
  (6,'Menu','save','json',5,'Administrator'),
  (7,'Modulos','grid','json',5,'Administrator'),
  (8,'Permisos','combobox','json',5,'Administrator'),
  (9,'Modulos','load','json',5,'Administrator'),
  (10,'Modulos','save','json',5,'Administrator'),
  (11,'Modulos','delete','json',5,'Administrator'),
  (12,'Permisos','tree_grid','json',5,'Administrator'),
  (13,'Permisos','load','json',5,'Administrator'),
  (14,'Permisos','save','json',5,'Administrator'),
  (15,'Roles','grid','json',5,'Administrator'),
  (16,'Roles','load','json',5,'Administrator'),
  (17,'Roles','save','json',5,'Administrator'),
  (18,'Roles','delete','json',5,'Administrator'),
  (19,'Usuarios','grid','json',4,'Administrator'),
  (20,'Usuarios','load','json',4,'Administrator'),
  (21,'Usuarios','save','json',4,'Administrator'),
  (22,'Usuarios','delete','json',4,'Administrator'),
  (23,'Recursos','combobox','json',5,'Administrator'),
  (24,'Recursos','grid','json',5,'Administrator'),
  (25,'Main','login','html',0,'Any'),
  (26,'Usuarios','login','json',0,'Any'),
  (27,'Menu','delete','json',5,'Administrator'),
  (28,'Menu','move','json',5,'Administrator'),
  (32,'Usuarios','logout','json',-1,'Any'),
  (33,'Usuarios','_','json',1,'Owner'),
  (34,'Permisos','delete','json',5,'Administrator'),
  (35,'Permisos','move','json',5,'Administrator'),
  (36,'Usuarios','password','json',4,'Administrator'),
  (37,'Main','changePassword','html',-1,'Any'),
  (38,'Usuarios','upasswd','json',-1,'Any'),
  (39,'Usuarios','profile','json',-1,'Any'),
  (40,'Patients','grid','json',-1,'Any'),
  (64,'Patients','delete','json',5,'Administrator'),
  (65,'Providers','grid','json',5,'Administrator'),
  (66,'Clinics','grid','json',5,'Administrator'),
  (67,'Gfe','grid','json',5,'Administrator'),
  (68,'Agreement','grid','json',-1,'Any'),
  (69,'Agreement','load','json',-1,'Any'),
  (70,'Agreement','save','json',-1,'Any'),
  (71,'Agreement','availableStates','json',-1,'Any'),
  (72,'Agreement','enableStates','json',-1,'Any'),
  (73,'Patients','reviews','json',-1,'Any'),
  (74,'Patients','amounts','json',-1,'Any'),
  (75,'Patients','load','json',-1,'Any'),
  (76,'Patients','get_agreement','json',-1,'Any'),
  (77,'Patients','treatments','json',-1,'Any'),
  (78,'Patients','gfe','json',-1,'Any');
  (79,'Brand','loadCatalog','json',-1,'Any'),
  (80,'Brand','applyRequest','json',-1,'Any'),
  (81,'Brand','save','json',-1,'Any'),
  (82,'Brand','delete','json',-1,'Any'),
  (83,'Inventory','grid','json',-1,'Any'),
  (84,'Inventory','update','json',-1,'Any'),
  (85,'Inventory','save','json',-1,'Any');
COMMIT;

-- ----------------------------
--  Table structure for `sys_groups`
-- ----------------------------
DROP TABLE IF EXISTS `sys_groups`;
CREATE TABLE `sys_groups` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` varchar(50) CHARACTER SET utf8 NOT NULL,
  `name` varchar(100) CHARACTER SET utf8 NOT NULL,
  `description` varchar(255) CHARACTER SET utf8 NOT NULL,
  `active` int(1) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `deleted` int(1) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(1) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(1) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uid` (`uid`) USING BTREE,
  KEY `deleted` (`deleted`) USING BTREE,
  KEY `created` (`created`) USING BTREE,
  KEY `active` (`active`),
  KEY `organization_id` (`organization_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
--  Records of `sys_groups`
-- ----------------------------
BEGIN;
INSERT INTO `sys_groups` VALUES ('1', '8f14e45fceea167a5a36dedd4bea2543', 'Propietario', '', '1', '0', '0', '0000-00-00 00:00:00', '0', '0000-00-00 00:00:00', '0'), ('2', 'd3d9446802a44259755d38e6d163e820', 'Default', '', '1', '0', '0', '0000-00-00 00:00:00', '0', '0000-00-00 00:00:00', '0'), ('3', 'bd8e6e4a-e2c8-44e6-99f3-a374ef600bf3', 'Administrador', 'Rol Administrador', '1', '1', '0', '0000-00-00 00:00:00', '0', '2020-08-10 20:47:20', '1'), ('4', '7822eadc-3cb8-40f0-a8b0-b7014b39c431', 'Provider', 'Rol provider', '1', '1', '0', '2020-08-10 20:47:46', '1', '2020-08-10 20:47:46', '1');
COMMIT;

-- ----------------------------
--  Table structure for `sys_groups_permissions`
-- ----------------------------
DROP TABLE IF EXISTS `sys_groups_permissions`;
CREATE TABLE `sys_groups_permissions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `access_level` enum('Deny','Reader','Contributed','Administrator','Owner') COLLATE utf8_unicode_ci NOT NULL,
  `access_resources` enum('Assigned','Own','Both') COLLATE utf8_unicode_ci NOT NULL,
  `organization_id` int(11) NOT NULL,
  `deleted` int(1) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `unico` (`permission_id`,`group_id`,`organization_id`) USING BTREE,
  KEY `deleted` (`deleted`) USING BTREE,
  KEY `organization_id` (`organization_id`) USING BTREE,
  KEY `access_resources` (`access_resources`) USING BTREE,
  KEY `access_level` (`access_level`) USING BTREE,
  KEY `permission_id` (`permission_id`) USING BTREE,
  KEY `group_id` (`group_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
--  Records of `sys_groups_permissions`
-- ----------------------------
BEGIN;
INSERT INTO `sys_groups_permissions` VALUES ('1', '1', '1', 'Owner', 'Both', '0', '0', '0000-00-00 00:00:00', '0', '0000-00-00 00:00:00', '0'), ('2', '2', '3', 'Administrator', 'Own', '0', '0', '0000-00-00 00:00:00', '0', '0000-00-00 00:00:00', '0'), ('3', '3', '4', 'Contributed', 'Assigned', '1', '1', '0000-00-00 00:00:00', '0', '2020-08-10 19:08:05', '1'), ('4', '3', '6', 'Contributed', 'Both', '1', '1', '2020-08-10 20:38:18', '1', '2020-08-10 20:38:18', '1'), ('5', '3', '1', 'Administrator', 'Both', '1', '0', '2020-08-10 20:47:20', '1', '2020-08-10 20:47:20', '1'), ('6', '4', '6', 'Contributed', 'Both', '1', '0', '2020-08-10 20:47:46', '1', '2020-08-10 20:47:46', '1');
COMMIT;

-- ----------------------------
--  Table structure for `sys_menus`
-- ----------------------------
DROP TABLE IF EXISTS `sys_menus`;
CREATE TABLE `sys_menus` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `uid` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `parent_id` int(4) unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8 NOT NULL,
  `description` varchar(255) CHARACTER SET utf8 NOT NULL,
  `order` int(2) NOT NULL,
  `icon` varchar(255) CHARACTER SET utf8 NOT NULL,
  `module_id` int(11) NOT NULL,
  `script` text CHARACTER SET utf8 NOT NULL,
  `active` int(1) NOT NULL,
  `deleted` int(1) NOT NULL,
  `lft` int(4) NOT NULL,
  `rght` int(4) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uid` (`uid`) USING BTREE,
  KEY `parent_id` (`parent_id`) USING BTREE,
  KEY `lft` (`lft`) USING BTREE,
  KEY `rght` (`rght`) USING BTREE,
  KEY `deleted` (`deleted`) USING BTREE,
  KEY `active` (`active`) USING BTREE,
  KEY `order` (`order`) USING BTREE,
  KEY `modulo_id` (`module_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
--  Records of `sys_menus`
-- ----------------------------
BEGIN;
INSERT INTO `sys_menus` VALUES ('1', 'cee03296-696a-4187-8ec8-ba091b93146f', '0', 'Menu', '', '1', '', '0', ' ', '1', '0', '1', '30', '0000-00-00 00:00:00', '0', '0000-00-00 00:00:00', '0'), ('7', '598329ac-5226-4391-bac3-c435d455c44c', '1', 'Panel', '', '3', '', '0', ' ', '1', '0', '2', '21', '0000-00-00 00:00:00', '0', '0000-00-00 00:00:00', '0'), ('8', '64755b76-9ed4-43be-a36d-cf9e61d316d2', '7', 'Catálogos', '', '1', '', '9', ' ', '1', '0', '3', '4', '2020-08-05 13:09:49', '1', '2020-08-05 13:09:52', '1'), ('9', 'b9c82f93-342e-40b4-b655-3b837fa814a3', '7', '-', '', '2', '', '0', ' ', '1', '0', '5', '6', '2020-08-05 13:09:37', '1', '2020-08-05 13:09:39', '1'), ('10', 'a03ed7b4-f166-48cb-8be9-2b1184ad45be', '7', 'Roles', '', '3', '', '5', ' ', '1', '0', '7', '8', '0000-00-00 00:00:00', '0', '0000-00-00 00:00:00', '0'), ('11', 'e1a49e6b-601b-41fd-9d5c-0db4ec362df6', '7', 'Permisos', '', '4', '', '4', ' ', '1', '0', '9', '10', '0000-00-00 00:00:00', '0', '0000-00-00 00:00:00', '0'), ('12', 'cc655b9b-1ae2-4f59-9aba-4e3cb4523b17', '7', 'Módulos', '', '5', '', '2', ' ', '1', '0', '11', '12', '0000-00-00 00:00:00', '0', '0000-00-00 00:00:00', '0'), ('13', '7b3c84e8-5ce5-4665-bd96-c0af0ff8e82f', '7', 'Menú', '', '6', '', '3', ' ', '1', '0', '13', '14', '0000-00-00 00:00:00', '0', '2020-08-05 12:33:34', '1'), ('14', '717b9d9f-10b0-49b5-b0f5-99a23f23eeed', '7', '-', '', '7', '', '0', ' ', '1', '0', '15', '16', '0000-00-00 00:00:00', '0', '0000-00-00 00:00:00', '0'), ('15', '60adc0b0-3a35-4690-bd21-f3f1d59ce7ab', '7', 'Usuarios', '', '8', '', '1', ' ', '1', '0', '17', '18', '0000-00-00 00:00:00', '0', '0000-00-00 00:00:00', '0'), ('16', 'cb66ebfb-4b0b-4527-aa2d-699f545a456e', '1', 'Usuario', '', '4', '', '0', ' ', '1', '0', '22', '29', '0000-00-00 00:00:00', '0', '0000-00-00 00:00:00', '0'), ('17', '28d0b57f-f1ee-47ab-9cb6-06b98e5b9be0', '16', 'Perfil', '', '1', '', '0', 'Ext.create(\'Wnd.Perfil\',{}).show(e.target);', '1', '0', '23', '24', '0000-00-00 00:00:00', '0', '2020-08-05 17:05:04', '1'), ('18', '62045d75-d7af-40bc-b560-c1aaccde00c4', '16', '-', '', '2', '', '0', ' ', '1', '0', '25', '26', '0000-00-00 00:00:00', '0', '0000-00-00 00:00:00', '0'), ('19', 'de331e4f-ebd4-4db1-8101-a6eef7c4bc6e', '16', 'Cerrar Sesión', '', '3', '', '0', 'App.logout();', '1', '0', '27', '28', '0000-00-00 00:00:00', '0', '0000-00-00 00:00:00', '0'), ('20', 'ab0b5b8d-ffdb-48bc-b8ae-15f09eaff60d', '7', 'Reportes', '', '9', '', '12', '', '1', '0', '19', '20', '2020-09-25 13:06:38', '1', '2020-09-25 13:06:38', '1');
COMMIT;

-- ----------------------------
--  Table structure for `sys_models`
-- ----------------------------
DROP TABLE IF EXISTS `sys_models`;
CREATE TABLE `sys_models` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` varchar(50) CHARACTER SET utf8 NOT NULL,
  `name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `model` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `table` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `active` int(1) NOT NULL,
  `deleted` int(1) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `unico` (`uid`) USING BTREE,
  UNIQUE KEY `model` (`model`),
  UNIQUE KEY `table` (`table`),
  KEY `active` (`active`) USING BTREE,
  KEY `deleted` (`deleted`) USING BTREE,
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
--  Table structure for `sys_modules`
-- ----------------------------
DROP TABLE IF EXISTS `sys_modules`;
CREATE TABLE `sys_modules` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` varchar(50) CHARACTER SET utf8 NOT NULL,
  `name` varchar(255) CHARACTER SET utf8 NOT NULL,
  `description` varchar(255) CHARACTER SET utf8 NOT NULL,
  `permission_id` int(11) NOT NULL,
  `url` varchar(255) CHARACTER SET utf8 NOT NULL,
  `file` varchar(255) CHARACTER SET utf8 NOT NULL,
  `controller` varchar(255) CHARACTER SET utf8 NOT NULL,
  `active` int(1) NOT NULL,
  `deleted` int(1) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uid` (`uid`) USING BTREE,
  KEY `deleted` (`deleted`) USING BTREE,
  KEY `active` (`active`) USING BTREE,
  KEY `permiso_id` (`permission_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
--  Records of `sys_modules`
-- ----------------------------
BEGIN;
INSERT INTO `sys_modules` (`id`, `uid`, `name`, `description`, `permission_id`, `url`, `file`, `controller`, `active`, `deleted`, `created`, `createdby`, `modified`, `modifiedby`)
VALUES 
  (1,'cc655b9b-1ae2-4f59-9aba-4e3cb4523b17','Usuarios','Módulos de Usuarios',4,'./usuarios','usuarios_module.js','Admin.Tab.Usuarios',1,0,'2021-01-01 00:00:00',0,'2020-08-10 20:39:19',1),
  (2,'7b3c84e8-5ce5-4665-bd96-c0af0ff8e82f','Módulos','Administrador de Módulos',5,'./modulos','modulos_module.js','Admin.Tab.Modulos',1,0,'2021-01-01 00:00:00',0,'2020-08-10 20:39:00',1),
  (3,'cb66ebfb-4b0b-4527-aa2d-699f545a456e','Administrador de Menús','Módulo para Administrador de Menús',5,'./menus','menu_module.js','Admin.Tab.Menu',1,0,'2021-01-01 00:00:00',0,'2020-08-10 20:38:44',1),
  (4,'28d0b57f-f1ee-47ab-9cb6-06b98e5b9be0','Permisos','Módulo para Administrador de Permisos',5,'./permisos','permisos_module.js','Admin.Tab.Permisos',1,0,'2021-01-01 00:00:00',0,'2020-08-10 20:39:08',1),
  (5,'62045d75-d7af-40bc-b560-c1aaccde00c4','Roles','Módulo para administrar Roles de Usuarios',5,'./roles','roles_module.js','Admin.Tab.Roles',1,0,'2021-01-01 00:00:00',0,'2020-08-10 20:39:13',1),
  (6,'598329ac-5226-4391-bac3-c435d455c44c','Perfil','Módulo para mostrar los Datos del Usuario.',3,'./perfil','perfil_module.js','Admin.Tab.Perfil',1,0,'2021-01-01 00:00:00',0,'2020-08-10 20:39:03',1),
  (9,'667237c4-72be-49c6-b5ac-0370ee05511a','Catálogos','Catálogos de Todo el Sistema',7,'','catalogos_module.js','Admin.Tab.Catalogos',1,0,'2020-08-05 13:09:20',1,'2020-08-10 20:38:49',1),
  (13,'85fa7541-4a4f-4ab3-be2c-328b734443fc','Patients','Patients List',2,'','patients_module.js','Admin.Tab.Patients',1,0,'2021-04-03 20:15:01',1,'2021-04-03 20:15:01',1),
  (14,'d1b12f1b-22eb-4a9b-aebd-6d7f76d090bc','Providers','',2,'','providers_module.js','Admin.Tab.Providers',1,0,'2021-04-04 04:33:32',1,'2021-04-04 04:33:32',1),
  (15,'276e96a0-f99f-480f-8050-52f31cd695b3','Clinics','',2,'','clinics_module.js','Admin.Tab.Clinics',1,0,'2021-04-04 04:49:19',1,'2021-04-04 04:49:19',1),
  (16,'fbb5a0d6-6287-49de-9a7a-1cc7f058bc1b','GFE & Certificates','',2,'','gfe_module.js','Admin.Tab.GFE',1,0,'2021-04-04 05:06:54',1,'2021-04-04 05:06:54',1),
  (17,'fbb5a0d6-6287-49de-9a7a-1cc7rtu67c1b','Agreements','',1,'','agreements_module.js','Admin.Tab.Agreements',1,0,'2021-04-04 04:30:00',0,'2021-04-04 04:30:00',0),
  (18,'fbb5a0d6-6287-49de-9a7a-1cc7rtu97c5f','Network','',1,'','network_module.js','Admin.Tab.Network',1,0,'2021-04-04 04:00:00',0,'2021-04-04 04:00:00',0),
  (19,'fdf4a0d6-6287-49de-9a7a-1cc3rtu97c5f','Brands','',1,'','req_brands_module.js','Admin.Tab.Brands',1,0,'2021-04-04 04:00:00',0,'2021-04-04 04:00:00',0),
  (20,'gdf4a0d6-6287-49de-9a7a-2cc4ety97f5f','Certified Injectors','',1,'','injectors_module.js','Admin.Tab.Injectors',1,0,'2021-04-04 04:00:00',0,'2021-04-04 04:00:00',0),
  (21,'wcf4a0d6-3247-50de-9a7a-2cc4ety37f5f','Inventory','',1,'','inventory_module.js','Admin.Tab.Inventory',1,0,'2021-04-04 04:00:00',0,'2021-07-07 00:00:00',0);
COMMIT;

-- ----------------------------
--  Table structure for `sys_organizations`
-- ----------------------------
DROP TABLE IF EXISTS `sys_organizations`;
CREATE TABLE `sys_organizations` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` varchar(50) CHARACTER SET utf8 NOT NULL,
  `name` varchar(100) CHARACTER SET utf8 NOT NULL,
  `description` varchar(255) CHARACTER SET utf8 NOT NULL,
  `active` int(1) NOT NULL,
  `deleted` int(1) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(1) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(1) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uid` (`uid`) USING BTREE,
  KEY `deleted` (`deleted`) USING BTREE,
  KEY `created` (`created`) USING BTREE,
  KEY `active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
--  Records of `sys_organizations`
-- ----------------------------
BEGIN;
INSERT INTO `sys_organizations` VALUES ('1', '8f14e45fceea167a5a36dedd4bea2543', 'SpaLiveMD', '', '1', '0', '0000-00-00 00:00:00', '0', '0000-00-00 00:00:00', '0');
COMMIT;

-- ----------------------------
--  Table structure for `sys_permissions`
-- ----------------------------
DROP TABLE IF EXISTS `sys_permissions`;
CREATE TABLE `sys_permissions` (
  `id` int(1) unsigned NOT NULL AUTO_INCREMENT,
  `uid` varchar(50) CHARACTER SET utf8 NOT NULL,
  `parent_id` int(1) unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8 NOT NULL,
  `description` varchar(255) CHARACTER SET utf8 NOT NULL,
  `order` int(11) NOT NULL,
  `active` int(1) unsigned NOT NULL,
  `deleted` int(1) NOT NULL,
  `lft` int(1) NOT NULL,
  `rght` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uid` (`uid`) USING BTREE,
  KEY `parent_id` (`parent_id`) USING BTREE,
  KEY `tree` (`lft`,`rght`) USING BTREE,
  KEY `active` (`active`) USING BTREE,
  KEY `orden` (`order`) USING BTREE,
  KEY `deleted` (`deleted`) USING BTREE,
  KEY `lft` (`lft`) USING BTREE,
  KEY `rght` (`rght`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
--  Records of `sys_permissions`
-- ----------------------------
BEGIN;
INSERT INTO `sys_permissions` VALUES ('1', 'c4ca4238a0b923820dcc509a6f75849b', '0', 'Todos los Permisos', '', '1', '1', '0', '1', '14', '0000-00-00 00:00:00', '0', '0000-00-00 00:00:00', '0'),
 ('2', '0c6fbd2a-a493-465c-8f1f-9901d967c8f7', '1', 'Administración', 'Todos los módulos Administrativos', '1', '1', '0', '2', '9', '0000-00-00 00:00:00', '0', '2020-08-10 20:25:57', '1'),
 ('3', '0ed25f08-9333-45bd-9e2f-ed3d1f3dde91', '1', 'Perfil', 'Datos del Usuario.', '2', '1', '0', '10', '11', '0000-00-00 00:00:00', '0', '0000-00-00 00:00:00', '0'),
 ('4', 'd3c33529-680d-40e2-b70b-eec1bfb0ca85', '2', 'Usuarios', 'Gestión de Usuarios.', '2', '1', '0', '5', '6', '0000-00-00 00:00:00', '0', '2020-08-10 20:29:09', '1'),
 ('5', 'b13d0da7-47d6-4645-9117-18c5c19dedf4', '2', 'Panel', 'Todos los módulos para la gestión del Panel', '1', '1', '0', '3', '4', '2020-08-10 20:25:17', '1', '2020-08-10 20:25:46', '1'), ('6', '3d85e520-1421-4fcb-8cdd-54d140d1e56e', '1', 'Embarques', 'Todos los permisos sobre Embarques', '3', '1', '0', '12', '13', '2020-08-10 20:27:54', '1', '2020-08-10 20:29:04', '1'), ('7', 'fc0acc08-4c26-4fd6-a0e7-3fd788a2d6f8', '2', 'Catálogos', 'Gestión de Catálogos', '3', '1', '0', '7', '8', '2020-08-10 20:36:04', '1', '2020-08-10 20:36:04', '1');
COMMIT;

-- ----------------------------
--  Table structure for `sys_sessions`
-- ----------------------------
DROP TABLE IF EXISTS `sys_sessions`;
CREATE TABLE `sys_sessions` (
  `id` char(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `created` datetime DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `data` blob,
  `expires` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`) USING BTREE,
  KEY `expires` (`expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Records of `sys_sessions`
-- ----------------------------
BEGIN;
INSERT INTO `sys_sessions` VALUES ('58ofcpu8qd0phqio1lhc50phil', '2020-10-05 23:29:08', '2020-10-05 23:29:31', 0x436f6e6669677c613a313a7b733a343a2274696d65223b693a313630313934303537313b7d5f53657373696f6e7c613a313a7b733a31353a226368616e67655f70617373776f7264223b623a303b7d5f557365727c613a343a7b733a323a226964223b693a313b733a333a22756964223b733a32333a2235653539366131643930636564392e3637343438373232223b733a383a22757365726e616d65223b733a363a226d6173746572223b733a31353a226f7267616e697a6174696f6e5f6964223b693a313b7d, '1633476571'), ('acr57pkncqs4kjbgju2t53jp02', '2020-10-05 23:35:12', '2020-10-05 23:45:14', 0x436f6e6669677c613a313a7b733a343a2274696d65223b693a313630313934313531343b7d5f53657373696f6e7c613a313a7b733a31353a226368616e67655f70617373776f7264223b623a303b7d5f557365727c613a343a7b733a323a226964223b693a313b733a333a22756964223b733a32333a2235653539366131643930636564392e3637343438373232223b733a383a22757365726e616d65223b733a363a226d6173746572223b733a31353a226f7267616e697a6174696f6e5f6964223b693a313b7d, '1633477514'), ('e03cbjl57m0naiiatbvt5agqrk', '2020-12-11 17:40:47', '2020-12-11 17:41:39', 0x436f6e6669677c613a313a7b733a343a2274696d65223b693a313630373730383439393b7d5f53657373696f6e7c613a313a7b733a31353a226368616e67655f70617373776f7264223b623a303b7d5f557365727c613a343a7b733a323a226964223b693a313b733a333a22756964223b733a32333a2235653539366131643930636564392e3637343438373232223b733a383a22757365726e616d65223b733a363a226d6173746572223b733a31353a226f7267616e697a6174696f6e5f6964223b693a313b7d, '1639244499'), ('gme2oajhevq7bi7636heqvkre5', '2020-10-05 19:55:50', '2020-10-05 19:59:22', 0x436f6e6669677c613a313a7b733a343a2274696d65223b693a313630313932373936313b7d5f53657373696f6e7c613a313a7b733a31353a226368616e67655f70617373776f7264223b623a303b7d5f557365727c613a343a7b733a323a226964223b693a313b733a333a22756964223b733a32333a2235653539366131643930636564392e3637343438373232223b733a383a22757365726e616d65223b733a363a226d6173746572223b733a31353a226f7267616e697a6174696f6e5f6964223b693a313b7d, '1633463962'), ('gmko8meojv8kohp2cghh8c9ipf', '2021-02-17 16:24:58', '2021-02-23 15:33:37', 0x436f6e6669677c613a313a7b733a343a2274696d65223b693a313631343039343431373b7d5f53657373696f6e7c613a313a7b733a31353a226368616e67655f70617373776f7264223b623a303b7d5f557365727c613a343a7b733a323a226964223b693a313b733a333a22756964223b733a32333a2235653539366131643930636564392e3637343438373232223b733a383a22757365726e616d65223b733a363a226d6173746572223b733a31353a226f7267616e697a6174696f6e5f6964223b693a313b7d, '1645630417'), ('i9l9mkff2v7a62n0n53d7aqfea', '2021-03-31 04:27:09', '2021-03-31 04:27:09', 0x436f6e6669677c613a313a7b733a343a2274696d65223b693a313631373136343832393b7d5f53657373696f6e7c613a313a7b733a31353a226368616e67655f70617373776f7264223b623a303b7d5f557365727c613a343a7b733a323a226964223b693a313b733a333a22756964223b733a32333a2235653539366131643930636564392e3637343438373232223b733a383a22757365726e616d65223b733a363a226d6173746572223b733a31353a226f7267616e697a6174696f6e5f6964223b693a313b7d, '1648700829'), ('q2ht115nav73i3bdl5v4mbtgut', '2021-02-16 16:02:59', '2021-02-16 16:56:41', 0x436f6e6669677c613a313a7b733a343a2274696d65223b693a313631333439343630313b7d5f53657373696f6e7c613a313a7b733a31353a226368616e67655f70617373776f7264223b623a303b7d5f557365727c613a343a7b733a323a226964223b693a313b733a333a22756964223b733a32333a2235653539366131643930636564392e3637343438373232223b733a383a22757365726e616d65223b733a363a226d6173746572223b733a31353a226f7267616e697a6174696f6e5f6964223b693a313b7d, '1645030601'), ('rkuvqtnlpbp7dfo47mol5gutvm', '2020-10-06 15:06:27', '2020-10-06 16:09:11', 0x436f6e6669677c613a313a7b733a343a2274696d65223b693a313630323030303535313b7d5f53657373696f6e7c613a313a7b733a31353a226368616e67655f70617373776f7264223b623a303b7d5f557365727c613a343a7b733a323a226964223b693a313b733a333a22756964223b733a32333a2235653539366131643930636564392e3637343438373232223b733a383a22757365726e616d65223b733a363a226d6173746572223b733a31353a226f7267616e697a6174696f6e5f6964223b693a313b7d, '1633536551');
COMMIT;

-- ----------------------------
--  Table structure for `sys_users_admin`
-- ----------------------------
DROP TABLE IF EXISTS `sys_users_admin`;
CREATE TABLE `sys_users_admin` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` varchar(100) CHARACTER SET utf8 NOT NULL,
  `username` varchar(100) CHARACTER SET utf8 NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8 NOT NULL,
  `active` int(1) NOT NULL,
  `last_login` datetime NOT NULL,
  `organization_id` int(11) NOT NULL,
  `deleted` int(1) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uid` (`uid`) USING BTREE,
  UNIQUE KEY `correo` (`username`) USING BTREE,
  KEY `organization_id` (`organization_id`),
  KEY `deleted` (`deleted`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
--  Records of `sys_users_admin`
-- ----------------------------
BEGIN;
INSERT INTO `sys_users_admin` VALUES ('1', '5e596a1d90ced9.67448722', 'master', 'Super Usuario', 'ccfca5f3c96a5671a678f72e4d6754f8e1965f87d353a2936b714a606dc9981d', '1', '2021-03-30 22:27:09', '1', '0', '0000-00-00 00:00:00', '0', '2020-08-06 20:13:30', '-1');
COMMIT;

-- ----------------------------
--  Table structure for `sys_users_groups`
-- ----------------------------
DROP TABLE IF EXISTS `sys_users_groups`;
CREATE TABLE `sys_users_groups` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `deleted` int(1) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `unico` (`user_id`,`group_id`) USING BTREE,
  KEY `user_id` (`user_id`),
  KEY `group_id` (`group_id`),
  KEY `organization_id` (`organization_id`),
  KEY `deleted` (`deleted`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
--  Records of `sys_users_groups`
-- ----------------------------
BEGIN;
INSERT INTO `sys_users_groups` VALUES ('1', '1', '1', '1', '0', '0000-00-00 00:00:00', '0', '0000-00-00 00:00:00', '0');
COMMIT;

-- ----------------------------
--  Table structure for `sys_users_permissions`
-- ----------------------------
DROP TABLE IF EXISTS `sys_users_permissions`;
CREATE TABLE `sys_users_permissions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `access_level` enum('Deny','Reader','Contributed','Administrator','Owner') COLLATE utf8_unicode_ci NOT NULL,
  `access_resources` enum('Assigned','Own','Both') COLLATE utf8_unicode_ci NOT NULL,
  `organization_id` int(11) NOT NULL,
  `deleted` int(1) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `unico` (`permission_id`,`user_id`) USING BTREE,
  KEY `deleted` (`deleted`) USING BTREE,
  KEY `organization_id` (`organization_id`) USING BTREE,
  KEY `type` (`access_resources`) USING BTREE,
  KEY `access_level` (`access_level`) USING BTREE,
  KEY `permission_id` (`permission_id`) USING BTREE,
  KEY `user_id` (`user_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
--  Table structure for `sys_users_temp_passwords`
-- ----------------------------
DROP TABLE IF EXISTS `sys_users_temp_passwords`;
CREATE TABLE `sys_users_temp_passwords` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` varchar(50) CHARACTER SET utf8 NOT NULL,
  `user_id` int(11) NOT NULL,
  `password` varchar(255) CHARACTER SET utf8 NOT NULL,
  `expires` int(10) NOT NULL,
  `deleted` tinyint(1) NOT NULL,
  `created` datetime NOT NULL,
  `createdby` int(11) NOT NULL,
  `modified` datetime NOT NULL,
  `modifiedby` int(11) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uid` (`uid`) USING BTREE,
  KEY `user_id` (`user_id`),
  KEY `deleted` (`deleted`),
  KEY `expires` (`expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
