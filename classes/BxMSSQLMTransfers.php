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

class BxMSSQLMTransfers extends BxTemplGrid
{
    public function __construct ($aOptions, $oTemplate = false)
    {
    	$this -> MODULE = 'bx_mssql_migration';
		$this -> _oModule = BxDolModule::getInstance('bx_mssql_migration');
		$this -> _aConfirmMessages = array(
												'run' => _t('_bx_mssql_migration_start_transfer_confirmation'),
												'update' => _t('_bx_mssql_migration_update_confirmation'),
												'clean' => _t('_bx_mssql_migration_clean_confirmation'),
												'remove' => _t('_bx_mssql_migration_remove_content_confirmation'),
												'remove_profiles' => _t('_bx_mssql_migration_remove_profile_content_confirmation')
											);				
        parent::__construct ($aOptions, $oTemplate);
    }
	
	protected function _getCellStatusText($mixedValue, $sKey, $aField, $aRow)
	{        
		$sAttr = $this->_convertAttrs($aField, 'attr_cell', false, isset($aField['width']) ? 'width:' . $aField['width'] : false);				
		
		if ($aRow['status'] != 'finished') 
			$mixedValue = _t('_bx_mssql_migration_status_' . $aRow['status']);
		
		return '<td ' . $sAttr . '><span class="' . ($aRow['status'] == 'finished' ? 'bx-migrate-not-finished' : ($aRow['status'] == 'error' ? 'bx-migrate-error' : '')) . '">' . $mixedValue . '</span></td>';
	} 
	
	protected function _getCellModule($mixedValue, $sKey, $aField, $aRow)
	{
		$aNumber = explode(',', $aRow['number']);

		if (sizeof($aNumber) > 1)
			$sTitle = call_user_func_array('_t', array_merge(array('_bx_mssql_migration_data_' . $mixedValue), $aNumber));
		else
			$sTitle = _t('_bx_mssql_migration_data_' . $mixedValue);
			
		return '<td>' .  $sTitle . '</td>';
	} 
	
	public function performActionRun()
	{
		$aElements = bx_get('ids');
		echoJson(array(
			'msg' => $this-> _oModule -> actionStartTransfer($aElements), 
			'grid' => $this -> getCode(false),
			'blink' => $aElements,
		));		   
	}

    public function performActionUpdate()
    {
        $sMessage = MsgBox( _t('_bx_mssql_migration_data_was_set'));
        if ($this -> _oModule -> initDb())
            $this -> _oModule -> createMigration();
        else
            $sMessage = MsgBox( _t('_bx_mssql_migration_error_data_was_not_set'));


        echoJson(array(
            'msg' => $sMessage,
            'grid' => $this -> getCode(false)
        ));
    }

	protected function _getFilterControls ()
    {
        return '';
    }
	
	public function performActionRemoveProfiles()
	{
		$this -> performActionRemove();
	}
	
	public function performActionRemove()
	{
		$sModule = bx_get('ids')[0];
		$iNumber = $this-> _oModule -> actionPerformAction($sModule, 'remove');
		echoJson(array(
			'msg' => $iNumber ? _t('_bx_mssql_migration_successfully_removed', $iNumber) : _t('_bx_mssql_migration_nothing_removed'),
			'grid' => $this -> getCode(false),
			'blink' => $sModule,
		));	
	}
	
	protected function _getActionRemove ($sType, $sKey, $a, $isSmall = false, $isDisabled = false, $aRow = array())
    {
		if ($aRow['module'] == 'profiles')
			$a['attr']['bx_grid_action_single'] = 'remove_profiles';
		return parent::_getActionDefault($sType, $sKey, $a, $isSmall, $isDisabled, $aRow);
    }
	
	public function performActionClean()
	{
		$sModule = bx_get('ids')[0];
		echoJson(array(
			'msg' => $this-> _oModule -> actionPerformAction($sModule, 'clean') ? _t('_bx_mssql_migration_successfully_cleaned') : _t('_bx_mssql_migration_nothing_removed'),
			'grid' => $this -> getCode(false),
			'blink' => $sModule,
		));	
	}
}

/** @} */
