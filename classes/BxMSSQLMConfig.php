<?php defined('BX_DOL') or die('hack attempt');
/**
 * Copyright (c) UNA, Inc - https://una.io
 * MIT License - https://opensource.org/licenses/MIT
 *
 * @defgroup    G-Med Migration  G-Med Migration
 * @ingroup     UnaModules
 *
 * @{
 */

class BxMSSQLMConfig extends BxBaseModGeneralConfig
{

	/**
	 *  @var array modules for transfer from G-Med to una with parameters
	 *  	[table_name] -  (string) table from which to get data
	 *  	[migration_class] - (string) class name/file name for data migration
	 *  	[dependencies] -  (array) list of the modules which should be migrated before transferring selected module 
	 *  	[plugins] - (array) list of the modules which should be installed on UNA before transferring selected module
	 */
	 
	public $_aMigrationModules = array(
				'profiles' => array(
                    'table_name'     => 'Users',
                    'migration_class' => 'BxMSSQLMProfiles',
            		'dependencies' => array(
			        ),
					'plugins' => array(
						'bx_persons' => 'Persons',
			        ),
                ),
				'profile_fields' => array(
                    'table_name'		=> '',
                    'migration_class'	=> 'BxMSSQLMProfilesFields',
			        'dependencies' => array(
                        'profiles',
                    ),
					'plugins' => array(
						'bx_persons' => 'Persons',
			        ),
                ),
				'posts' => array(
                    'table_name'		=> 'Channels_Posts',
                    'migration_class'	=> 'BxMSSQLMPosts',
					'type'				=> 'post',
					'keywords'			=> 'bx_posts_meta_keywords',
			        'dependencies' => array(
                		'profiles',
                     ),
					 'plugins' => array(
						'bx_persons'	=> 'Persons',
						'bx_posts'		=> 'Posts'			
			        ),
                ),/*
				'polls' => array(
                    'table_name'		=> 'PostTest',
                    'migration_class'	=> 'BxMSSQLMPolls',
					'type'				=> 'bx_poll',
					'keywords'			=> 'bx_polls_meta_keywords',
			        'dependencies' => array(
                		'profiles',
                     ),
					 'plugins' => array(
						'bx_persons'	=> 'Persons',
						'bx_polls'		=> 'Polls'	
			        ),
                ),
		*/
             );
			 
	public function __construct($aModule)
	{
		parent::__construct($aModule);		
	}

}

/** @} */
