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
 
define('BX_MIG_SUCCESSFUL', 1);
define('BX_MIG_FAILED', 0);

/**
 * Base class for all migration classes
 * contains general variables and function for modules' migration classes
 */
class BxMSSQLMData
{      
	/**
	* @var ref $_oMainModule the reference to the main module object
	*/	
	protected $_oMainModule;
	
	/**
	* @var  ref $_mDb the reference to G-Med's database connect
	*/
	protected $_mDb;
	
	/**
	* @var string  $_sPrefix migration module database prefix
	*/
	protected $_sPrefix;
	
	/**
	* @var ref $_oDb connect to UNA database
	*/
	protected $_oDb;

	/**
	* @var string $_sModuleName module name, it is set by inherited object and equal to value from {@link $_aMigrationModules}
	*@uses BxMSSQLMConfig::_aMigrationModules modules array
	*/
	protected $_sModuleName = '';
	
	/**
	* @var string $_sTableWithTransKey the main a module's table name into which transferring data from G-Med's module
	* it is individual for each module and set in constructor of each class
	*/
	protected $_sTableWithTransKey;
	
	/**
	* @var int $_iTransferred number of transferred records
	*/	
	protected $_iTransferred = 0;
	
	/**
	* @var ref $_oLanguage  reference on @uses BxDolStudioLanguagesUtils::getInstance() 
	*/		
	protected $_oLanguage = null;
	
	/**
	* @var array $_aLanguages list of the installed languages in appropriate format 
	*/		
	protected $_aLanguages = array();
	
	/**
	* @var string $_sImagePhotoFiles path to the photo module's data files in G-Med
	*/
	protected $_sImagePhotoFiles = '';
	
	/**
	* @var string $_sTransferFieldIdent field name which is created in table into which records are added, it allows to set connect between ids from G-Med and UNA's tables
	*/
	protected $_sTransferFieldIdent = 'mig_id';
	
	/**
	 *  Base class constructor
	 *  
	 *  @param ref $oMainModule main module 
	 *  @param ref $oDb connect with G-Med database
	 *  @return void
	 */
	public function __construct(&$oMainModule, &$oDb)
	{
	     $this -> _sPrefix = $oMainModule -> _aModule['db_prefix'];
	     $this -> _oMainModule = $oMainModule;
		 $this -> _oConfig = $oMainModule -> _oConfig;
	     $this -> _mDb  = $oDb;
	     $this -> _oDb = $this -> _oMainModule -> _oDb;		 
		 $this -> _sTableWithTransKey = '';
		 $this -> _oLanguage = BxDolStudioLanguagesUtils::getInstance();
		 $this -> _aLanguages = $this -> _oDb -> getPairs("SELECT * FROM `sys_localization_languages` WHERE `Enabled`='1'", 'Name', 'ID');
		 $this -> _sImagePhotoFiles = $this -> _oDb -> getExtraParam('root') . 'modules' . DIRECTORY_SEPARATOR . 'boonex' . DIRECTORY_SEPARATOR . "photos" . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR . "files" . DIRECTORY_SEPARATOR;		 
	}
	/**
	* Main migration function, executes required operations for transfer 
	* @return boolean 
	*/
	public function runMigration()
	{
	    $this -> setResultStatus(_t('_bx_mssql_migration_define_migration_method'));
	    return BX_MIG_FAILED;
	}

	/**
	* Gets total records for transferring
	* @return boolean
	*/
	public function getTotalRecords()
	{
	    $this -> setResultStatus(_t('_bx_mssql_migration_define_total_method'));
	    return BX_MIG_FAILED;		
	}

	/**
	* Set Migration Status
	* @param string $sStatus message
	*/         
	protected function setResultStatus($sStatus, $sModule = '')
	{
	    $sQuery = $this -> _oDb -> prepare("UPDATE `{$this -> _sPrefix}transfers` SET `status_text` = ? WHERE `module` = ? ", $sStatus, $this -> _sModuleName);
	    $this -> _oDb -> query($sQuery);
	}

	/**
	* Returns content id of transferred member from G-Med
	* @param int $iId G-Med's profile ID
	* @return int
	*/	
	protected function getContentId($iId){
		$sQuery = $this -> _oDb -> prepare("SELECT `p`.`content_id` FROM  `sys_accounts` AS  `a` 
											LEFT JOIN  `sys_profiles` AS  `p` ON `a`.`id` =  `p`.`account_id` 
											WHERE  `{$this -> _sTransferFieldIdent}` = ? AND  `p`.`type` =  'bx_persons' LIMIT 1", $iId);
											
		return $this -> _oDb -> getOne($sQuery);
	}

	/**
	* Returns person id of transferred member from G-Med
	* @param int $iId G-Med's profile ID
	* @return int
	*/	
	protected function getProfileId($iId)
	{
	    $sQuery = $this -> _oDb -> prepare("SELECT `p`.`id` FROM  `sys_accounts` AS  `a` 
											LEFT JOIN  `sys_profiles` AS  `p` ON `a`.`id` =  `p`.`account_id` 
											WHERE  `{$this -> _sTransferFieldIdent}` = ? AND  `p`.`type` =  'bx_persons' LIMIT 1", $iId);
											
		return $this -> _oDb -> getOne($sQuery);
	}

    protected function getAccountIdByContentId($iId){
        $sQuery = $this -> _oDb -> prepare("SELECT `account_id` 
                                            FROM `sys_profiles` 
											WHERE `content_id` = ? AND `type` = 'bx_persons' LIMIT 1", $iId);

        return $this -> _oDb -> getOne($sQuery);
    }

	/**
	* Check if the module was transfered
	* @param string $sModule name from the list @uses  BxMSSQLMConfig::_aMigrationModules
	* @return int
	*/	
	protected function isModuleContentTransferred($sModule)
	{
		return isset($this -> _oConfig -> _aModulesAliases[$sModule])
				? (int)$this -> _oDb -> getOne("SELECT `number` FROM `{$this -> _sPrefix}transfers` WHERE `status` != 'not_started' AND `module`=:module", array('module' => $this -> _oConfig -> _aModulesAliases[$sModule]))
				: false;
	}
	

	/**
	 *  Check if the list already exists in una pre values list 
	 *  
	 *  @param string $sName list name 
	 *  @return Boolean
	 */
	protected function isKeyPreKeyExits($sName)
	{
		return $this -> _oDb -> getOne("SELECT COUNT(*) FROM `sys_form_pre_lists` WHERE `key` = :key", array('key' => $sName)) == 1;
	}

	/**
	 *  Transfer fields lists with translations 
	 *  
	 *  @param string $sName field name 
	 *  @param string $sTitle translation
	 *  @param mixed $mixedValues  contains name of the list which already exists or array values for transfer
	 *  @param bool $bAdd don't add value to already exited fields list 
	 *  @return string list name for db insert with #!
	 */
	protected function transferPreValues($sName, $sTitle, $mixedValues, $bAdd = false)
		{
			if (empty($mixedValues))
				return '';
			
			if (is_string($mixedValues) && substr($mixedValues, 0, 2) == '#!')
				return $mixedValues;
						
			$i = 1;
			if (!$this -> isKeyPreKeyExits($sName))
			{
				$sQuery = $this -> _oDb -> prepare("INSERT INTO `sys_form_pre_lists` (`id`, `module`, `key`, `title`, `use_for_sets`, `extendable`) VALUES
												(NULL, 'bx_persons', ?, ?, 0, 1)", $sName, '_bx_' . $sName . '_pre_lists_cats');
				$this -> _oLanguage -> addLanguageString('_bx_' . $sName . '_pre_lists_cats', $sTitle);
				$this -> _oDb -> query($sQuery);
				
				$sQuery = $this -> _oDb -> prepare("
					INSERT INTO `sys_form_pre_values` SET
						`Key`	= ?, 
						`Value`	= ?,
						`Order`	= ?,
						`LKey`	= ?", $sName, 0, 0, '_sys_please_select');
				$this -> _oDb -> query($sQuery);
			}
			else
			{
				if (!$bAdd)
					return '';
				
				$i = $this -> _oDb -> getOne("SELECT MAX(`Order`) FROM `sys_form_pre_values` WHERE `Key` =:key", array('key' => $sName)) + 1;
			}
						
			
			foreach($mixedValues as $mixedKey => $mixedValue)
			{
				$sLangKey = "_bx_fields_cat_{$sName}_value_{$mixedKey}";
				
				if (is_array($mixedValue))
				{
					foreach($mixedValue as $sLang => $sValue)
					{
						if (isset($this -> _aLanguages[$sLang]))
							$this -> _oLanguage -> addLanguageString($sLangKey, $sValue, $this -> _aLanguages[$sLang]);
					}					
				}
				else
					$this -> _oLanguage -> addLanguageString($sLangKey, $mixedValue);
				
				$sQuery = $this -> _oDb -> prepare("
				INSERT INTO `sys_form_pre_values` SET
					`Key`	= ?, 
					`Value`	= ?,
					`Order`		= ?,
					`LKey`	= ?", $sName, $mixedKey, $i++, $sLangKey);
				
				$this -> _oDb -> query($sQuery);
			}
			
		return "#!{$sName}";
	}
	
	/**
	 *  Returns default language name/id
	 *  
	 *  @param boolean $bName return language name, otherwise id 
	 *  @return mixed default language
	 */
	protected function getDefaultLang($bName = true)
	{
		$aLang = $this -> _mDb -> getPairs("SELECT * FROM `sys_localization_languages` ORDER BY `id`", 'Name', 'ID'); 
		$sDefultLang = $this -> _mDb -> getParam('lang_default'); 
		return $bName ? $sDefultLang : $aLang[$sDefultLang];
	}

	/**
	* Create migration field in main table for transferring content from G-Med to UNA and contains id of the object in G-Med
	* @return mixed
         */
	protected function createMIDField()
	{
		if (!$this -> _sTableWithTransKey)
			return false;
		
		if ($this -> _oDb -> isFieldExists($this -> _sTableWithTransKey, $this -> _sTransferFieldIdent))
			return true;
		
		return $this -> _oDb -> query("ALTER TABLE `{$this -> _sTableWithTransKey}` ADD `{$this -> _sTransferFieldIdent}` int(11) unsigned NOT NULL default '0'");
	}
	
	/**
	 *  Returns last migration field value
	 *  
	 *  @return int
	 */
	protected function getLastMIDField($iExclude = 0, $sField = 'ID')
	{
		if (!$this -> _sTableWithTransKey)
			return false;

		$sExclude = '';
		if ((int)$iExclude)
           $sExclude = "AND `{$sField}` <> {$iExclude}";

		return (int)$this -> _oDb -> getOne("SELECT `{$this -> _sTransferFieldIdent}` FROM `{$this -> _sTableWithTransKey}` WHERE `{$this -> _sTransferFieldIdent}` <> 0 {$sExclude} ORDER BY `{$sField}` DESC LIMIT 1");
	}

	/**
	 *  Check if this record was already transferred
	 *  
	 *  @param int $iItemId object id in G-Med
	 *  @param string $sField field name with id values in G-Med
	 *  @return int 
	 */	
	protected function isItemExisted($iItemId, $sField = 'id')
	{
		if (!$this -> _sTableWithTransKey)
			return false;

		return (int)$this -> _oDb -> getOne("SELECT `{$sField}` FROM `{$this -> _sTableWithTransKey}` WHERE `{$this -> _sTransferFieldIdent}` = :item LIMIT 1", array('item' => $iItemId));
	}	
	
	/**
	 *  Set value for migrated record to migration field 
	 *  
	 *  @param int $iId object id in UNA
	 *  @param int $iItemId object id in G-Med
	 *  @param string $sField id field name 
	 *  @return int affected rows
	 */
	protected function setMID($iId, $iItemId, $sField ='id')
	{
		if (!$this -> _sTableWithTransKey)
			return false;

		return (int)$this -> _oDb -> query("UPDATE `{$this -> _sTableWithTransKey}` SET `{$this -> _sTransferFieldIdent}` = :item WHERE `{$sField}` = :id", array('id' => $iId, 'item' => $iItemId));
	}
	/**
	 *  Drop migration field from the table
	 *  
	 *  @return int affected rows
	 */
	public function dropMID()
	{
		if (!$this -> _sTableWithTransKey || !$this -> _oDb -> isFieldExists($this -> _sTableWithTransKey, $this -> _sTransferFieldIdent))
			return false;

		return (int)$this -> _oDb -> query("ALTER TABLE `{$this -> _sTableWithTransKey}` DROP `{$this -> _sTransferFieldIdent}`");
	}
	
	/**
	 *  Removes all transferred content from UNA
	 *  
	 *  @return void
	 */
	public function removeContent()
	{
		$this -> dropMID();
		$this -> _oDb -> updateTransferStatus($this -> _sModuleName, 'not_started');
		$this -> setResultStatus('');
	}
}
   
/** @} */
