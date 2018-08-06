<?php defined('BX_DOL') or die('hack attempt');
/**
 * Copyright (c) UNA, Inc - https://una.io
 * MIT License - https://opensource.org/licenses/MIT
 *
 * @defgroup    G-Med G-Med Migration
 * @ingroup     UnaModules
 *
 * @{
 */

class BxMSSQLMAlertsResponse extends BxBaseModTextAlertsResponse
{
    public function __construct()
    {
        $this -> MODULE = 'bx_mssql_migration';
        parent::__construct();
    }

    public function response($oAlert)
    {
        parent::response($oAlert);		
		
		if (('system' == $oAlert -> sUnit && 'encrypt_password_after' == $oAlert -> sAction) || ('account' == $oAlert -> sUnit && 'edit' == $oAlert -> sAction))
						BxDolService::call($this -> MODULE, 'encrypt_password', array($oAlert));
    }
}

/** @} */
