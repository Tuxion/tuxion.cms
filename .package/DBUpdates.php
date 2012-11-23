<?php namespace core; if(!defined('TX')) die('No direct access.');

//Make sure we have the things we need for this class.
tx('Component')->check('update');
tx('Component')->load('update', 'classes\\BaseDBUpdates', false);

class DBUpdates extends \components\update\classes\BaseDBUpdates
{
  
  protected
    $is_core = true,
    $updates = array(
      '3.2.0' => '3.3.0'
    );
  
  public function install_3_2_0($dummydata, $forced)
  {
    
    if($forced === true){
      tx('Sql')->query('DROP TABLE IF EXISTS `#__core_config`');
      tx('Sql')->query('DROP TABLE IF EXISTS `#__core_ip_addresses`');
      tx('Sql')->query('DROP TABLE IF EXISTS `#__core_languages`');
      tx('Sql')->query('DROP TABLE IF EXISTS `#__core_sites`');
      tx('Sql')->query('DROP TABLE IF EXISTS `#__core_site_domains`');
      tx('Sql')->query('DROP TABLE IF EXISTS `#__core_users`');
    }
    
    tx('Sql')->query('
      CREATE TABLE `#__core_config` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `key` varchar(255) NOT NULL,
        `value` varchar(255) DEFAULT NULL,
        `site_id` int(10) unsigned NOT NULL,
        `autoload` tinyint(1) NOT NULL DEFAULT \'0\',
        PRIMARY KEY (`id`),
        KEY `option_id` (`key`)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8
    ');
    tx('Sql')->query('
      CREATE TABLE `#__core_ip_addresses` (
        `address` varchar(255) NOT NULL,
        `login_level` int(10) unsigned NOT NULL,
        PRIMARY KEY (`address`)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8
    ');
    tx('Sql')->query('
      CREATE TABLE `#__core_languages` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `code` varchar(10) NOT NULL,
        `shortcode` varchar(10) NOT NULL,
        PRIMARY KEY (`id`)
      ) ENGINE=MyISAM  DEFAULT CHARSET=utf8
    ');
    tx('Sql')->query('
      CREATE TABLE `#__core_sites` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `title` varchar(255) NOT NULL,
        `path_base` varchar(255) NOT NULL,
        `url_path` varchar(255) NOT NULL,
        PRIMARY KEY (`id`)
      ) ENGINE=MyISAM  DEFAULT CHARSET=utf8
    ');
    tx('Sql')->query('
      CREATE TABLE `#__core_site_domains` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `site_id` int(10) unsigned NOT NULL,
        `domain` varchar(300) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `domain` (`domain`),
        KEY `site_id` (`site_id`)
      ) ENGINE=MyISAM  DEFAULT CHARSET=utf8
    ');
    tx('Sql')->query('
      CREATE TABLE `#__core_users` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `email` varchar(255) NOT NULL,
        `password` varchar(255) DEFAULT NULL,
        `level` int(3) NOT NULL DEFAULT \'1\',
        `session` char(32) DEFAULT NULL,
        `ipa` varchar(15) DEFAULT NULL,
        `hashing_algorithm` varchar(255) DEFAULT NULL,
        `salt` varchar(255) DEFAULT NULL,
        `dt_last_login` datetime DEFAULT NULL,
        `dt_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `username` varchar(255) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `username` (`username`)
      ) ENGINE=MyISAM  DEFAULT CHARSET=utf8
    ');
    
  }
  
}

