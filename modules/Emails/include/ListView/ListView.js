/**
 *
 * SugarCRM Community Edition is a customer relationship management program developed by
 * SugarCRM, Inc. Copyright (C) 2004-2013 SugarCRM Inc.
 *
 * SuiteCRM is an extension to SugarCRM Community Edition developed by SalesAgility Ltd.
 * Copyright (C) 2011 - 2017 SalesAgility Ltd.
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
 * FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more
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
 * reasonably feasible for  technical reasons, the Appropriate Legal Notices must
 * display the words  "Powered by SugarCRM" and "Supercharged by SuiteCRM".
 */

$(document).ready(function(){
  "use strict";


  // TODO remove mass update from tinymce


  $('[data-action=emails-compose]').click(function() {
   "use strict";
   var emailComposeView = null;
   var composeBox = $('<div></div>').appendTo('#content');
   composeBox.messageBox({
     "showHeader": false,
     "showFooter": false,
     "size": 'lg',
     "backdrop": false
   });
   composeBox.setBody(SUGAR.language.translate('', 'LBL_EMAIL_LOADING'));
   composeBox.show();

   $.ajax({
     type: "GET",
     cache: false,
     url: 'index.php?module=Emails&action=ComposeView'
   }).done(function (data) {
     if(data.length === 0) {
       console.error("Unable to display ComposeView");
       composeBox.setBody(SUGAR.language.translate('', 'ERR_AJAX_LOAD'));
       return;
     }

     composeBox.setBody(data);
     emailComposeView = composeBox.controls.modal.body.find('.compose-view').EmailsComposeView();
     $(emailComposeView).on('sentEmail', function(event, composeView) {
       composeBox.hide();
       composeBox.remove();
     });

     $(emailComposeView).on('disregardDraft', function(event, composeView) {
       composeBox.hide();
       composeBox.remove();
     });

   }).fail(function (data) {
     composeBox.controls.modal.content.html(SUGAR.language.translate('', 'LBL_EMAIL_ERROR_GENERAL_TITLE'));
     // TODO: Work out how to show security messages
   });



  });

 $('[data-action=emails-configure]').click(function(){
   alert('configure email placeholder');
 });

 $('[data-action=emails-check-new-email]').click(function(){
   alert('check new email placeholder');
 });

 $('[data-action=emails-open-folder]').click(function(){
   alert('open folder email placeholder');
 });

 // look for new
  $('.email-indicator .email-new').each(function(i, v){
    $(this).closest('tr').addClass('email-new-record');
  });
});