<?php
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}
/**
 *
 * SugarCRM Community Edition is a customer relationship management program developed by
 * SugarCRM, Inc. Copyright (C) 2004-2013 SugarCRM Inc.
 *
 * SuiteCRM is an extension to SugarCRM Community Edition developed by SalesAgility Ltd.
 * Copyright (C) 2011 - 2018 SalesAgility Ltd.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY SUGARCRM, SUGARCRM DISCLAIMS THE WARRANTY
 * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more
 * details.
 *
 * You should have received a copy of the GNU Affero General Public License along with
 * this program; if not, see http://www.gnu.org/licenses or write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301 USA.
 *
 * You can contact SugarCRM, Inc. headquarters at 10050 North Wolfe Road,
 * SW2-130, Cupertino, CA 95014, USA. or at email address contact@sugarcrm.com.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "Powered by
 * SugarCRM" logo and "Supercharged by SuiteCRM" logo. If the display of the logos is not
 * reasonably feasible for technical reasons, the Appropriate Legal Notices must
 * display the words "Powered by SugarCRM" and "Supercharged by SuiteCRM".
 */

/*********************************************************************************

 * Description:  Contains field arrays that are used for caching
 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc.
 * All Rights Reserved.
 * Contributor(s): ______________________________________..
 ********************************************************************************/
$fields_array['Prospect'] = array ('column_fields' => array("id"
        ,"date_entered"
        ,"date_modified"
        ,"modified_user_id"
        ,"assigned_user_id"
        , "created_by"
        ,"salutation"
        ,"first_name"
        ,"last_name"
        ,"title"
        ,"department"
        ,"birthdate"
        ,"do_not_call"
        ,"phone_home"
        ,"phone_mobile"
        ,"phone_work"
        ,"phone_other"
        ,"phone_fax"
        ,"email1"
        ,"email2"
        ,"assistant"
        ,"assistant_phone"
        ,"email_opt_out"
        ,"primary_address_street"
        ,"primary_address_city"
        ,"primary_address_state"
        ,"primary_address_postalcode"
        ,"primary_address_country"
        ,"alt_address_street"
        ,"alt_address_city"
        ,"alt_address_state"
        ,"alt_address_postalcode"
        ,"alt_address_country"
        ,"description"
        ,"tracker_key"
        ,'invalid_email'
        ,'lead_id'
        ,'account_name'
        ),
        'list_fields' =>  array('full_name','id', 'first_name', 'last_name', 'account_name', 'account_id', 'title', 'email1','email2', 'phone_work', 'assigned_user_name', 'assigned_user_id','email_and_name1','email_and_name2'
,'invalid_email'
,'lead_id'
        ),
    'required_fields' =>   array("last_name"=>1),
);
