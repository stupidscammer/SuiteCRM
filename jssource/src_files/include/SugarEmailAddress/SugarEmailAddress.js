/*********************************************************************************
 * SugarCRM Community Edition is a customer relationship management program developed by
 * SugarCRM, Inc. Copyright (C) 2004-2013 SugarCRM Inc.

 * SuiteCRM is an extension to SugarCRM Community Edition developed by Salesagility Ltd.
 * Copyright (C) 2011 - 2014 Salesagility Ltd.
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
 ********************************************************************************/

(function () {
  //Do not double define
  if (SUGAR.EmailAddressWidget) return;

  SUGAR.EmailAddressWidget = function (module) {
    if (!SUGAR.EmailAddressWidget.count[module]) SUGAR.EmailAddressWidget.count[module] = 0;
    this.count = SUGAR.EmailAddressWidget.count[module];
    SUGAR.EmailAddressWidget.count[module]++;
    this.module = module;
    this.id = this.module + this.count;
    if (document.getElementById(module + '_email_widget_id'))
      document.getElementById(module + '_email_widget_id').value = this.id;
    SUGAR.EmailAddressWidget.instances[this.id] = this;
  }

  SUGAR.EmailAddressWidget.instances = {};
  SUGAR.EmailAddressWidget.count = {};

  SUGAR.EmailAddressWidget.prototype = {
    emailTemplate: '<tr id="emailAddressRow">' +
    '<td nowrap="NOWRAP"><input type="text" title="email address 0" name="emailAddress{$index}" id="emailAddress0" size="30"/></td>' +
    '<td><span>&nbsp;</span><img id="removeButton0" name="0" src="index.php?entryPoint=getImage&amp;themeName=Sugar&amp;imageName=delete_inline.gif"/></td>' +
    '<td align="center"><input type="radio" name="emailAddressPrimaryFlag" id="emailAddressPrimaryFlag0" value="emailAddress0" enabled="true" checked="true"/></td>' +
    '<td align="center"><input type="checkbox" name="emailAddressOptOutFlag[]" id="emailAddressOptOutFlag0" value="emailAddress0" enabled="true"/></td>' +
    '<td align="center"><input type="checkbox" name="emailAddressInvalidFlag[]" id="emailAddressInvalidFlag0" value="emailAddress0" enabled="true"/></td>' +
    '<td><input type="hidden" name="emailAddressVerifiedFlag0" id="emailAddressVerifiedFlag0" value="true"/></td>' +
    '<td><input type="hidden" name="emailAddressVerifiedValue0" id="emailAddressVerifiedValue0" value=""/></td></tr>',
    totalEmailAddresses: 0,
    replyToFlagObject: new Object(),
    verifying: false,
    enterPressed: false,
    tabPressed: false,
    emailView: "",
    emailIsRequired: false,
    tabIndex: -1,

    isIE: function() {

    var ua = window.navigator.userAgent;
    var msie = ua.indexOf("MSIE ");

    if (msie > 0 || !!navigator.userAgent.match(/Trident.*rv\:11\./))  // If Internet Explorer, return version number
    {
      return true;
    }

    return false;
  },
    prefillEmailAddresses: function (tableId, o) {
      for (i = 0; i < o.length; i++) {
        o[i].email_address = o[i].email_address.replace('&#039;', "'");
        this.addEmailAddress(tableId, o[i].email_address, o[i].primary_address, o[i].reply_to_address, o[i].opt_out, o[i].invalid_email, o[i].email_address_id);
      }
    },

    retrieveEmailAddress: function (event) {
      var callbackFunction = function success(data) {
        var vals = jQuery.parseJSON(data.responseText);
        var target = vals.target;
        event = this.getEvent(event);

        if (vals.email) {
          var email = vals.email;
          if (email != '' && /\d+$/.test(target)) {
            var matches = target.match(/\d+$/);
            var targetNumber = matches[0];
            var optOutEl = $('#' + this.id + 'emailAddressOptOutFlag' + targetNumber);
            if (optOutEl) {
              optOutEl.checked = email['opt_out'] == 1 ? true : false;
            }
            var invalidEl = $('#' + this.id + 'emailAddressInvalidFlag' + targetNumber);
            if (invalidEl) {
              invalidEl.checked = email['invalid_email'] == 1 ? true : false;
            }
          }
        }
        //Set the verified flag to true
        var index = /[a-z]*\d?emailAddress(\d+)/i.exec(target)[1];

        var verifyElementFlag = $('#' + this.id + 'emailAddressVerifiedFlag' + index);

        if (verifyElementFlag.parentNode.childNodes.length > 1) {
          verifyElementFlag.parentNode.removeChild(verifyElementFlag.parentNode.lastChild);
        }

        var verifiedTextNode = document.createElement('span');
        verifiedTextNode.innerHTML = '';
        verifyElementFlag.parentNode.appendChild(verifiedTextNode);
        verifyElementFlag.value = "true";
        this.verifyElementValue = $('#' +this.id + 'emailAddressVerifiedValue' + index);
        this.verifyElementValue.value = $('#' +this.id + 'emailAddress' + index).value;
        this.verifying = false;

        // If Enter key or Save button was pressed then we proceed to attempt a form submission
        var savePressed = false;
        if (event) {
          var elm = document.activeElement || event.explicitOriginalTarget;
          if (typeof elm.type != 'undefined' && /submit|button/.test(elm.type.toLowerCase())) {
            //if we are in here, then the element has been recognized as a button or submit type, so check the id
            //to make sure it is related to a submit button that should lead to a form submit

            //note that the document.activeElement and explicitOriginalTarget calls do not work consistantly across
            // all browsers, so we have to include this check after we are sure that the calls returned something as opposed to in the coindition above.
            // Also, since this function is called on blur of the email widget, we can't rely on a third object as a flag (a var or hidden form input)
            // since this function will fire off before the click event from a button is executed, which means the 3rd object will not get updated prior to this function running.
            if (/save|full|cancel|change/.test(elm.value.toLowerCase())) {
              //this is coming from either a save, full form, cancel, or view change log button, we should set savePressed = true;
              savePressed = true;
            }
          }
        }


        if (savePressed || this.enterPressed) {
          setTimeout("SUGAR.EmailAddressWidget.instances." + this.id + ".forceSubmit()", 2100);
        } else if (this.tabPressed) {
          $('#' +this.id + 'emailAddressPrimaryFlag' + index).focus();
        }
      }

      var event = this.getEvent(event);
      var targetEl = this.getEventElement(event);
      var index = /[a-z]*\d?emailAddress(\d+)/i.exec(targetEl.id)[1];
      var verifyElementFlag = $('#' +this.id + 'emailAddressVerifiedFlag' + index);

      if (this.verifyElementValue == null || typeof(this.verifyElementValue) == 'undefined') {
        //we can't do anything without this value, so just return
        return false;
      }

      this.verifyElementValue = $('#' +this.id + 'emailAddressVerifiedValue' + index);
      verifyElementFlag.value = (trim(targetEl.value) == '' || targetEl.value == this.verifyElementValue.value) ? "true" : "false"

      //Remove the span element if it is present
      if (verifyElementFlag.parentNode.childNodes.length > 1) {
        verifyElementFlag.parentNode.removeChild(verifyElementFlag.parentNode.lastChild);
      }

      if (/emailAddress\d+$/.test(targetEl.id) && isValidEmail(targetEl.value) && !this.verifying && verifyElementFlag.value == "false") {
        verifiedTextNode = document.createElement('span');
        verifyElementFlag.parentNode.appendChild(verifiedTextNode);
        verifiedTextNode.innerHTML = SUGAR.language.get('app_strings', 'LBL_VERIFY_EMAIL_ADDRESS');
        this.verifying = true;
        var cObj = jQuery.get('index.php?module=Contacts&action=RetrieveEmail&target=' + targetEl.id + '&email=' + targetEl.value)
          .done(callbackFunction)
          .fail(callbackFunction);
      }
    },

    handleKeyDown: function (event) {
      var e = this.getEvent(event);
      var eL = this.getEventElement(e);
      if ((kc = e["keyCode"])) {
        this.enterPressed = (kc == 13) ? true : false;
        this.tabPressed = (kc == 9) ? true : false;

        if (this.enterPressed || this.tabPressed) {
          this.retrieveEmailAddress(e);
          if (this.enterPressed)
            this.freezeEvent(e);
        }
      }
    }, //handleKeyDown()

    getEvent: function (event) {
      return (event ? event : window.event);
    },//getEvent

    getEventElement: function (e) {
      return (e.srcElement ? e.srcElement : (e.target ? e.target : e.currentTarget));
    },//getEventElement

    freezeEvent: function (e) {
      if (e.preventDefault) e.preventDefault();
      e.returnValue = false;
      e.cancelBubble = true;
      if (e.stopPropagation) e.stopPropagation();
      return false;
    },//freezeEvent

    addEmailAddress: function (tableId, address, primaryFlag, replyToFlag, optOutFlag, invalidFlag, emailId) {
      var _eaw = this;
      if (_eaw.addInProgress) {
        return;
      }

      _eaw.addInProgress = true;

      if (!address) {
        address = "";
      }

      // TODO Remove
      console.log(_eaw);
      console.log(tableId, address, primaryFlag, replyToFlag, optOutFlag, invalidFlag, emailId);

      // Clone from hidden template on the page
      var lineContainer = $('.template.email-address-line-container').clone();
      lineContainer.removeClass('template');
      lineContainer.removeClass('hidden');


      // Set up line item
      // use the value if the tabindex value for email has been passed in from metadata (defined in include/EditView/EditView.tpl
      // else default to 0
      var tabIndexCount = 0;
      if(typeof(SUGAR.TabFields) !='undefined' && typeof(SUGAR.TabFields['email1']) != 'undefined'){
        tabIndexCount = SUGAR.TabFields['email1'];
      }


      // Email Field
      var emailField = lineContainer.find('input[type=email]');
      emailField.attr('name', _eaw.id + 'emailAddress' + _eaw.totalEmailAddresses);
      emailField.attr('id', _eaw.id + 'emailAddress' + _eaw.totalEmailAddresses);
      emailField.attr('tabindex', tabIndexCount);
      emailField.attr('enabled', "true");
      if(address != '') {
        emailField.attr('value', address);
      }
      emailField.eaw = _eaw;
      emailField.on('blur', function(e) {
        emailField.eaw.retrieveEmailAddress(e);
      });
      emailField.on('keydown', function(e) {
        emailField.eaw.handleKeyDown(e);
      });

      // Remove button
      var removeButton = lineContainer.find('button#email-address-remove-button');
      removeButton.attr('name', _eaw.totalEmailAddresses);
      removeButton.attr('id', _eaw.id + "removeButton" + _eaw.totalEmailAddresses);
      removeButton.attr('tabindex', tabIndexCount);
      removeButton.attr('enabled', "true");
      removeButton.click((function(eaw) {
        return function() {
          eaw.removeEmailAddress(_eaw.name);
        }
      })(_eaw));


      // Record id
      var recordId = lineContainer.find('input#record-id');
      recordId.attr('name', _eaw.id + "emailAddressId" + _eaw.totalEmailAddresses);
      recordId.attr('id', _eaw.id + 'emailAddressId' + _eaw.totalEmailAddresses);
      recordId.attr('value', typeof(emailId) != 'undefined' ? emailId : '');
      recordId.attr('enabled', "true");


      // Primary checkbox
      var primaryCheckbox = lineContainer.find('input#email-address-primary-flag');
      primaryCheckbox.attr('name', _eaw.id + 'emailAddressPrimaryFlag');
      primaryCheckbox.attr('id', _eaw.id + 'emailAddressPrimaryFlag' + _eaw.totalEmailAddresses);
      primaryCheckbox.attr('value',  _eaw.id + 'emailAddress' + _eaw.totalEmailAddresses);
      primaryCheckbox.attr('tabindex', tabIndexCount);
      primaryCheckbox.attr('enabled', "true");
      primaryCheckbox.prop("checked", (primaryFlag == '1'));

      // CL Fix for 17651 (added OR condition check to see if this is the first email added)
      if(primaryFlag == '1' || (_eaw.totalEmailAddresses == 0)) {
        primaryCheckbox.attr("checked", 'true');("checked", 'true');
        primaryCheckbox.attr("title", SUGAR.language.get('app_strings', 'LBL_EMAIL_PRIM_TITLE'));
      }

      // Reply to checkbox
      var replyToCheckbox = lineContainer.find('input#email-address-reply-to-flag');
      if(replyToCheckbox.length == 1) {
        replyToCheckbox.attr('name', _eaw.id + '"emailAddressReplyToFlag');
        replyToCheckbox.attr('id', _eaw.id + 'emailAddressReplyToFlag' + _eaw.totalEmailAddresses);
        replyToCheckbox.attr('value',  _eaw.id + 'emailAddress' + _eaw.totalEmailAddresses);
        replyToCheckbox.attr('tabindex', tabIndexCount);
        replyToCheckbox.attr('enabled', "true");
        replyToCheckbox.prop("checked", (replyToFlag == '1'));
        _eaw.replyToFlagObject[replyToCheckbox.attr('id')] = (replyToFlag == '1');
        replyToCheckbox.click(function() {
          var form = document.forms[_eaw.emailView];
          if (!form) {
            form = document.forms['editContactForm'];
          }
          var nav = new String(navigator.appVersion);

          if(nav.match(/MSIE/gim)) {
            for(i=0; i<form.elements.length; i++) {
              var id = new String(form.elements[i].id);
              if(id.match(/emailAddressReplyToFlag/gim) && form.elements[i].type == 'radio' && id != _eaw.id) {
                form.elements[i].checked = false;
              }
            }
          }
          for(i=0; i<form.elements.length; i++) {
            var id = new String(form.elements[i].id);
            if(id.match(/emailAddressReplyToFlag/gim) && form.elements[i].type == 'radio' && id != _eaw.id) {
              _eaw.replyToFlagObject[_eaw.id] = false;
            }
          } // for
          if (_eaw.replyToFlagObject[this.id]) {
            _eaw.replyToFlagObject[this.id] = false;
            this.checked = false;
          } else {
            _eaw.replyToFlagObject[this.id] = true;
            this.checked = true;
          } // else
        });
      }


      // Opt Out checkbox
      var optOutCheckbox = lineContainer.find('input#email-address-opt-out-flag');
      if(optOutCheckbox.length == 1) {
        optOutCheckbox.attr('name', _eaw.id + 'emailAddressOptOutFlag[]');
        optOutCheckbox.attr('id', _eaw.id + 'emailAddressOptOutFlag' + _eaw.totalEmailAddresses);
        optOutCheckbox.attr('value',  _eaw.id + 'emailAddress' + _eaw.totalEmailAddresses);
        optOutCheckbox.attr('tabindex', tabIndexCount);
        optOutCheckbox.attr('enabled', "true");
        optOutCheckbox.prop("checked", (optOutFlag == '1'));
        optOutCheckbox.click(function() {
          _eaw.toggleCheckbox(this);
        });
      }


      // Invalid checkbox
      var invalidCheckbox = lineContainer.find('input#email-address-invalid-flag');
      if(invalidCheckbox.length == 1) {
        invalidCheckbox.attr('name', _eaw.id + 'emailAddressInvalidFlag[]');
        invalidCheckbox.attr('id', _eaw.id + 'emailAddressInvalidFlag' + _eaw.totalEmailAddresses);
        invalidCheckbox.attr('value',  _eaw.id + 'emailAddress' + _eaw.totalEmailAddresses);
        invalidCheckbox.attr('tabindex', tabIndexCount);
        invalidCheckbox.attr('enabled', "true");
        invalidCheckbox.prop("checked", (invalidFlag == '1'));
        invalidCheckbox.click(function() {
          _eaw.toggleCheckbox(this);
        });
      }


      // Verified flag
      var verifiedField = lineContainer.find('input#verired-flag');
      verifiedField.attr('name', _eaw.id + 'emailAddressVerifiedFlag');
      verifiedField.attr('id', _eaw.id + 'emailAddressVerifiedFlag' + _eaw.totalEmailAddresses);
      verifiedField.attr('value', 'true');


      //  Verified email value
      var verifiedEmailValueField = lineContainer.find('input#verired-email-value');
      verifiedEmailValueField.attr('name', _eaw.id + 'emailAddressVerifiedFlag');
      verifiedEmailValueField.attr('id', _eaw.id + 'emailAddressVerifiedFlag' + _eaw.totalEmailAddresses);
      verifiedEmailValueField.attr('value', 'true');


      // Add line item to lines container
      $(lineContainer).appendTo('.email-address-lines-container');

      // Add validation to field
      _eaw.EmailAddressValidation(_eaw.emailView, _eaw.id + 'emailAddress' + _eaw.totalEmailAddresses, _eaw.emailIsRequired, SUGAR.language.get('app_strings', 'LBL_EMAIL_ADDRESS_BOOK_EMAIL_ADDR'));
      _eaw.totalEmailAddresses += 1;
      _eaw.numberEmailAddresses = _eaw.totalEmailAddresses;
      _eaw.addInProgress = false;
    }, //addEmailAddress



    EmailAddressValidation: function (ev, fn, r, stR) {
      $(document).ready(function() {
        addToValidate(ev, fn, 'email', r, stR);
      });
    },

    removeEmailAddress: function (index) {
      removeFromValidate(this.emailView, this.id + 'emailAddress' + index);
      var oNodeToRemove = $('#' +this.id + 'emailAddressRow' + index);
      // var form = Dom.getAncestorByTagName(oNodeToRemove, "form");
      var form = $(this).closest("form");
      oNodeToRemove.parentNode.removeChild(oNodeToRemove);

      var removedIndex = parseInt(index);
      //If we are not deleting the last email address, we need to shift the numbering to fill the gap
      if (this.totalEmailAddresses != removedIndex) {
        for (var x = removedIndex + 1; x < this.totalEmailAddresses; x++) {
          $('#' +this.id + 'emailAddress' + x).setAttribute("name", this.id + "emailAddress" + (x - 1));
          $('#' +this.id + 'emailAddress' + x).setAttribute("id", this.id + "emailAddress" + (x - 1));

          if ($('#' +this.id + 'emailAddressInvalidFlag' + x)) {
            $('#' +this.id + 'emailAddressInvalidFlag' + x).setAttribute("value", this.id + "emailAddress" + (x - 1));
            $('#' +this.id + 'emailAddressInvalidFlag' + x).setAttribute("id", this.id + "emailAddressInvalidFlag" + (x - 1));
          }

          if ($('#' +this.id + 'emailAddressOptOutFlag' + x)) {
            $('#' +this.id + 'emailAddressOptOutFlag' + x).setAttribute("value", this.id + "emailAddress" + (x - 1));
            $('#' +this.id + 'emailAddressOptOutFlag' + x).setAttribute("id", this.id + "emailAddressOptOutFlag" + (x - 1));
          }

          if ($('#' +this.id + 'emailAddressPrimaryFlag' + x)) {
            $('#' +this.id + 'emailAddressPrimaryFlag' + x).setAttribute("id", this.id + "emailAddressPrimaryFlag" + (x - 1));
          }

          $('#' +this.id + 'emailAddressVerifiedValue' + x).setAttribute("id", this.id + "emailAddressVerifiedValue" + (x - 1));
          $('#' +this.id + 'emailAddressVerifiedFlag' + x).setAttribute("id", this.id + "emailAddressVerifiedFlag" + (x - 1));

          var rButton = $('#' +this.id + 'removeButton' + x);
          rButton.setAttribute("name", (x - 1));
          rButton.setAttribute("id", this.id + "removeButton" + (x - 1));
          $('#' +this.id + 'emailAddressRow' + x).setAttribute("id", this.id + 'emailAddressRow' + (x - 1));
        }
      }

      this.totalEmailAddresses--;


      // CL Fix for 17651
      if (this.totalEmailAddresses == 0) {
        return;
      }

      var primaryFound = false;
      for (x = 0; x < this.totalEmailAddresses; x++) {
        if ($('#' +this.id + 'emailAddressPrimaryFlag' + x).checked) {
          primaryFound = true;
        }
      }

      if (!primaryFound) {
        $('#' +this.id + 'emailAddressPrimaryFlag0').checked = true;
        $('#' +this.id + 'emailAddressPrimaryFlag0').value = this.id + 'emailAddress0';
      }

    },

    toggleCheckbox: function (el) {
      var form = document.forms[this.emailView];
      if (!form) {
        form = document.forms['editContactForm'];
      }

      if (SUGAR.EmailAddressWidget.prototype.isIE()) {
        for (var i = 0; i < form.elements.length; i++) {
          var id = new String(form.elements[i].id);
          if (id.match(/emailAddressInvalidFlag/gim) && form.elements[i].type == 'checkbox' && id != el.id) {
            form.elements[i].checked = false;
          }
        }

        el.checked = true;
      }
    },

    forceSubmit: function () {
      var theForm = $('#' +this.emailView);
      if (theForm) {
        theForm.action.value = 'Save';
        if (!check_form(this.emailView)) {
          return false;
        }
        if (this.emailView == 'EditView') {
          //this is coming from regular edit view form
          theForm.submit();
        } else if (this.emailView.indexOf('DCQuickCreate') > 0) {
          //this is coming from the DC Quick Create Tool Bar, so call save on form
          DCMenu.save(theForm.id);
        } else if (this.emailView.indexOf('QuickCreate') >= 0) {
          //this is a subpanel create or edit form
          SUGAR.subpanelUtils.inlineSave(theForm.id, theForm.module.value + '_subpanel_save_button');
        }
      }
    } //forceSubmit
  };
  emailAddressWidgetLoaded = true;
})();
