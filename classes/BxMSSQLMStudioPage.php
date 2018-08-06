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

 require_once('BxMSSQLMTransfers.php');

class BxMSSQLMStudioPage extends BxTemplStudioModule
{    
	/**
	 *  @var ref $_oModule main module reference
	 */
	protected $_oModule;
	
	function __construct($sModule = "", $sPage = "")
    {
		$this -> MODULE = 'bx_mssql_migration';
		parent::__construct($sModule, $sPage);

		$this -> _oModule = BxDolModule::getInstance($sModule);

		$this->aMenuItems = array(
            array('name' => 'settings', 'icon' => 'cogs', 'title' => '_bx_mssql_migration_cpt_settings'),
            array('name' => 'config', 'icon' => 'exchange', 'title' => '_bx_mssql_migration_cpt_transfer_data'),
        );	
		
		$this -> _oModule -> _oTemplate -> addStudioJs(array('transfer.js', 'BxDolGrid.js'));
		$this -> _oModule -> _oTemplate -> addStudioCss(array('main.css'));
     } 
	
	public function saveData($sPath)
	{
			if ($this -> _oModule -> initDb())
				$this -> _oModule -> createMigration();
			else
				return MsgBox( _t('_bx_mssql_migration_error_data_was_not_set'), 2);
              

		return MsgBox( _t('_bx_mssql_migration_data_was_set'), 2);			
	}
	
	 protected function getSettings()
    {
        $aForm = array(
			'params' => array(
				'db' => array(
					'submit_name' => 'save'
				)
			),
            'inputs' => array (
                'path' => array(
                    'type' => 'text',
                    'name' => 'path',
                    'caption' => $this -> _oModule -> _oDb -> isConfigInstalled() ? _t('_bx_mssql_migration_defined_path') : _t('_bx_mssql_migration_cpt_put_path'),
                    'value' => $this -> _oModule -> _oDb -> getExtraParam('root'),
					'attrs_wrap'
                ),   
				
				'save' => array (
                    'type' => 'submit',
                    'name' => 'save',
                    'value' => _t('_bx_mssql_migration_cpt_' . ($this -> _oModule -> _oDb -> getExtraParam('root') ? 'update' : 'save'))					
                )
            )
        );
        
		
		if ($this -> _oModule -> _oDb -> isConfigInstalled())
				$aForm['inputs']['save']['attrs'] = array('onclick' => "javascript:return confirm('" . bx_js_string(_t('_bx_mssql_migration_reupload_data')) . "');");
		
		$oForm = new BxTemplStudioFormView($aForm);	
		
		$sMessage = '';
		if ($oForm -> isSubmitted()) 
			$sMessage = $this -> saveData($oForm -> getCleanValue('path'));		
		
        return $sMessage . $oForm -> getCode();
    }
	
	protected function getConfig()
	{		
		$oGrid = BxMSSQLMTransfers::getObjectInstance('bx_mssql_migration_transfers'); 
		if ($oGrid)	
			return $oGrid -> getCode(); // print grid object 		
        
		return MsgBox(_t('_bx_mssql_migration_installation_problem'));
	}
}

/** @} */
