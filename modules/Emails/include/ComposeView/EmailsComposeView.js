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

(function ($) {
  /**
   *
   * @param options
   * @returns {jQuery|HTMLElement}
   * @constructor
   */
  $.fn.EmailsComposeView = function (options) {
    "use strict";
    var self = $(this);
    var opts = $.extend({}, $.fn.EmailsComposeView.defaults, options);

    /**
     * Constructor
     */
    self.construct = function () {
      "use strict";

      if (self.length === 0) {
        console.error('EmailsComposeView - Invalid Selector');
        return
      }

      if (self.attr('id').length === 0) {
        console.warn('EmailsComposeView - expects element to have an id. EmailsComposeView has generated one.');
        self.attr('id', self.generateID());
      }

      if (typeof opts.tinyMceOptions.setup === "undefined") {
        opts.tinyMceOptions.setup = self.tinyMceSetup;
      }

      if (typeof opts.tinyMceOptions.selector === "undefined") {
        opts.tinyMceOptions.selector = $(self).find('#description_html');
      }

      /**
       * Used to preview email. It also doubles as a means to get the plain text version
       * using $('#'+self.attr('id') + ' .html_preview').text();s
       */
      $('<div></div>').addClass('hidden').addClass('html_preview').appendTo($(self));

      $('<input>')
        .attr('name', 'description_html')
        .attr('type', 'hidden')
        .attr('id', 'description_html')
        .appendTo($(self));

      if (typeof tinymce === "undefined") {
        console.error('EmailsComposeView - Missing Dependency: Cannot find tinyMCE.');

        // copy plain to html
        $(self).find('#description_html').closest('.edit-view-row-item').addClass('hidden');
        $(self).find('textarea#description_html').on("keyup", function (e) {
          $(self).find('input#description_html').val($(self).find('textarea#description').val().replace('\n', '<br>'));
        });
      } else {
        $(self).find('[data-label="description_html"]').closest('.edit-view-row-item').addClass('hidden');
        tinymce.init(opts.tinyMceOptions);
      }

      // Handle sent email submission
      self.submit(self.onSendEmail);

      // Handle toolbar button events
      $(self).find('.btn-send-email').click(self.sendEmail);
      $(self).find('.btn-attach-file').click(self.attachFile);
      $(self).find('.btn-attach-notes').click(self.attachNote);
      $(self).find('.btn-attach-document').click(self.attachDocument);
      $(self).find('.btn-save-draft').click(self.saveDraft);
      $(self).find('.btn-disregard-draft').click(self.disregardDraft);

      var file = $('<input />')
        .attr('name', file);

      $(self).on('remove', self.destruct);

      $(self).trigger("constructEmailsComposeView", [self]);
    };

    /**
     * @destructor
     */
    self.destruct = function(event) {
      // TODO: Find a better way only display one tiny mce
      // Remove the hanging tinyMCE div
      $('.mce-panel').remove();
      var length = tinyMCE.editors.length;
      for (var i=length; i>0; i--) {
        tinyMCE.editors[i-1].remove();
      };
      return true;
    };

    /**
     * @return string UUID
     */
    self.generateID = function () {
      "use strict";
      var characters = ['a', 'b', 'c', 'd', 'e', 'f', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
      var format = '0000000-0000-0000-0000-00000000000';
      var z = Array.prototype.map.call(format, function ($obj) {
        var min = 0;
        var max = characters.length - 1;

        if ($obj == '0') {
          var index = Math.round(Math.random() * (max - min) + min);
          $obj = characters[index];
        }

        return $obj;
      }).toString().replace(/(,)/g, '');
      return z;
    };

    /**
     * confirms if form is valid
     * @returns {boolean}
     */
    self.isValid = function () {
      "use strict";
      var valid = self.isToValid() &&
        self.isCcValid() &&
        self.isBccValid() &&
        self.isSubjectValid() &&
        self.isBodyValid();

      return valid;
    };

    /**
     * validates form and displays error
     * @returns {boolean}
     */
    self.validate = function () {
      var valid = self.isValid();
      if(valid === false) {
        if(typeof messageBox !== "undefined") {
          var mb = messageBox({size:'lg'});
          mb.setTitle(SUGAR.language.translate('', 'ERR_INVALID_REQUIRED_FIELDS'));
          mb.setBody(self.translatedErrorMessage);

          mb.on('ok', function() {
            mb.remove();
          });

          mb.on('cancel', function() {
            mb.remove();
          });

          mb.show();
        } else {
          alert(self.translatedErrorMessage);
        }
      }
      return valid;
    }

    /**
     * Is the To field valid
     * @returns {boolean}
     */
    self.isToValid = function () {
      "use strict";
      var emailAddresses = $(self).find('[name=to_addrs_names]').val().split('/[,;]/');

      if (self.isValidEmailAddresses(emailAddresses)) {
        return true;
      }

      self.setValidationMessage('to_addrs_names', 'LBL_HAS_INVALID_EMAIL_TO');
      return false;
    };

    /**
     * Is the CC field valid
     * @returns {boolean}
     */
    self.isCcValid = function () {
      "use strict";

      var cc = $(self).find('[name=cc_addrs_names]').val();
      var emailAddresses = cc.split('/[,;]/');

      if (self.isValidEmailAddresses(emailAddresses) || cc === '') {
        return true;
      }

      self.setValidationMessage('cc_addrs_names', 'LBL_HAS_INVALID_EMAIL_CC');
      return false;
    };

    /**
     * Is the BCC field valid
     * @returns {boolean}
     */
    self.isBccValid = function () {
      "use strict";
      var bcc = $(self).find('[name=bcc_addrs_names]').val();
      var emailAddresses = bcc.split('/[,;]/');

      if (self.isValidEmailAddresses(emailAddresses) || bcc === '') {
        return true;
      }

      self.setValidationMessage('bcc_addrs_names', 'LBL_HAS_INVALID_EMAIL_BCC');
      return false;
    };

    /**
     * Is the Subject field valid
     * @returns {boolean}
     */
    self.isSubjectValid = function () {
      "use strict";

      if ($(self).find('[name=name]').val() !== '') {
        return true;
      }

      self.setValidationMessage('name', 'LBL_HAS_EMPTY_EMAIL_SUBJECT');
      return false;
    };

    /**
     * Is the Body field valid
     * @returns {boolean}
     */
    self.isBodyValid = function () {
      "use strict";

      if ($(self).find('#description').val() !== '') {
        return true;
      }

      self.setValidationMessage('description_html', 'LBL_HAS_EMPTY_EMAIL_BODY');
      return false;
    };

    /**
     *
     * @param field string name
     * @param label string eg LBL_OK
     */
    self.setValidationMessage = function (field, label) {
      "use strict";
      self.translatedErrorMessage = SUGAR.language.translate('Emails', label);
    };

    /**
     * Determines if a set of email addresses are valid
     * @param emailAddresses array|object eg ['a@example.com', 'b@example.com']
     * @returns {boolean}
     */
    self.isValidEmailAddresses = function (emailAddresses) {
      "use strict";
      if (typeof emailAddresses === 'object' || typeof emailAddresses === 'array') {
        for (var i = 0; i < emailAddresses.length; i++) {
          emailAddresses[i] = (emailAddresses[i] !== '') && isValidEmail(emailAddresses[i]);
        }
        if (emailAddresses.indexOf(false) === -1) {
          return true;
        }
      }

      return false;
    };

    /**
     *
     * @param editor
     */
    self.tinyMceSetup = function (editor) {
      editor.on('init', function(ed) {
        this.getDoc().body.style.fontName = 'tahoma';
        this.getDoc().body.style.fontSize = '13px';
      });

      editor.on('change', function (e) {
        // copy html to plain
        $(self).find('.html_preview').html(editor.getContent());
        $(self).find('input#description_html').val(editor.getContent());
        $(self).find('textarea#description').val($(self).find('.html_preview').text());
      });
    };

    /**
     *
     * @event onSendEmail
     * @event sentEmailError
     * @event sentEmailAlways
     * @event sentEmail
     * @returns {boolean}
     */
    self.onSendEmail = function (event) {
       $(self).trigger("sendEmail", [self]);

       // Tell the user we are sending an email
      var mb = messageBox();
      mb.hideHeader();
      mb.hideFooter();
      mb.setBody('<div class="email-in-progress"><img src="themes/'+SUGAR.themes.theme_name+'/images/loading.gif"></div>');
      mb.show();

      var fileCount = 0;
      // Use FormData v2 to send form data via ajax
       var formData = new FormData($(this));

       $(this).find('input').each(function(i,v) {
         if($(v).attr('type').toLowerCase() === 'file') {
           for(var i = 0; i < v.files.length; i++) {
             var file =  v.files[i];
             var reader  = new FileReader();
             reader.readAsDataURL(file);
             formData.append($(v).attr('name'), file);
             fileCount++
           }
         } else {
           formData.append($(v).attr('name'), $(v).val());
         }
       });


       // TODO: add ajax loader

      $.ajax({
        type: "POST",
        data: formData,
        cache: false,
        processData: false,  // tell jQuery not to process the data
        contentType: false,   // tell jQuery not to set contentType
        url: $(this).attr('action'),
      }).done(function (data) {
        "use strict";
        mb.remove();
        // If the user is view the form own its own
        if ($(self).find('input[type="hidden"][name="return_module"]').val() !== '') {
          var redirect_location = 'index.php?module=' + $('#' + self.attr('id') + ' input[type="hidden"][name="return_module"]').val() +
            '&action=' +
            $(self).find('input[type="hidden"][name="return_action"]').val();
            location.href = redirect_location;
        } else {
           $(self).trigger("sentEmail", [self, data]);
        }
      }).fail(function (data) {
        "use strict";
        mb.showHeader();
        mb.setBody(SUGAR.language.translate($(self).find('input[type="hidden"][name="module"]').val(), 'LBL_ERROR_SENDING_EMAIL'));
         $(self).trigger("sentEmailError", [self, data]);
      }).always(function (data) {
         $(self).trigger("sentEmailAlways", [self, data]);
      });



      return false;
    };


    /**
     * @event sendEmail
     * @returns {boolean}
     */
    self.sendEmail = function () {
      "use strict";
      if (self.validate()) {
        $(self).submit();
      }
      return false;
    }


    /**
     * @event attachFile
     * @returns {boolean}
     */
    self.attachFile = function (event) {
      "use strict";
      event.preventDefault();
       $(self).trigger("attachFile", [self]);

      // Add the file input onto the page
       var id = self.generateID();

       var fileGroupContainer = $('<div></div>')
         .addClass('attachment-group-container')
         .appendTo(self.find('.file-attachments'));

       var fileInput = $('<input>')
         .attr('type', 'file')
         .attr('id', 'file_'+id)
         .attr('name', 'email_attachment[]')
         .attr('multiple','true')
         .appendTo(fileGroupContainer);


       var fileLabel = $('<label></label>')
         .attr('for', 'file_'+id)
         .addClass('attachment-blank')
         .html('<span class="glyphicon glyphicon-paperclip"></span>')
         .appendTo(fileGroupContainer);

       // use the label to open file dialog
      fileLabel.click();

      // handle when the a file is selected
      fileInput.change(function(event) {

        if(event.target.files.length === 0) {
          fileGroupContainer.remove();
          return false;
        }
        if(event.target.files.length > 1) {
          $(fileLabel.addClass('label-with-multiple-files'));
        } else {
          $(fileLabel.removeClass('label-with-multiple-files'));
        }

        fileLabel.html('');
        fileLabel.empty();

        if(fileGroupContainer.find('.attachment-remove').length === 0) {
          var removeAttachment = $('<a class="attachment-remove"><span class="glyphicon glyphicon-remove"></span></a>');
          fileGroupContainer.append(removeAttachment);
          // handle when user removes attachment
          removeAttachment.click(function(event) {
            fileGroupContainer.remove();
          });
        }

        for(var i = 0; i < event.target.files.length; i++) {
          var file =  event.target.files[i];
          var name = file.name;
          var size = file.size;
          var type = file.type;

          var fileContainer = $('<div class="attachment-file-container"></div>');
          fileContainer.appendTo(fileLabel);
          // Create icons based on file type
          if(type.indexOf('image') !== -1) {
            fileContainer.addClass('file-image');
            fileContainer.append('<span class="attachment-type glyphicon glyphicon-picture"></span>');
          } else if(type.indexOf('audio') !== -1) {
            fileContainer.addClass('file-audio');
            fileContainer.append('<span class="attachment-type glyphicon glyphicon-music"></span>');
          } else if(type.indexOf('video') !== -1) {
            fileContainer.addClass('file-video');
            fileContainer.append('<span class="attachment-type glyphicon glyphicon-film"></span>');
          } else if(type.indexOf('zip') !== -1) {
            fileContainer.addClass('file-video');
            fileContainer.append('<span class="attachment-type glyphicon glyphicon-compressed"></span>');
          } else {
            fileContainer.addClass('file-other');
            fileContainer.append('<span class="attachment-type glyphicon glyphicon-file"></span>');
          }
          fileContainer.append('<span class="attachment-name"> '+name+' </span>');
          fileContainer.append('<span class="attachment-size"> '+ self.humanReadableFileSize(size, true) +' </span>');

          fileLabel.removeClass('attachment-blank');

        }

      });

      return false;
    };


    /**
     * @event attachNote
     * @returns {boolean}
     */
    self.attachNote = function () {
      "use strict";
       $(self).trigger("attachNote", [self]);
      alert('attachNote placeholder');
      return false;
    };


    /**
     * @event attachDocument
     * @returns {boolean}
     */
    self.attachDocument = function () {
      "use strict";
       $(self).trigger("attachDocument", [self]);
      alert('attachDocument placeholder');
      return false;
    };

    /**
     * @event saveDraft
     * @returns {boolean}
     */
    self.saveDraft = function () {
      "use strict";
       $(self).trigger("saveDraft", [self]);
      alert('saveDraft placeholder');
      return false;
    };

    /**
     *
     * @event disregardDraft
     * @returns {boolean}
     */
    self.disregardDraft = function () {
      "use strict";
      $(self).trigger("disregardDraft", [self]);
      return false;
    };

    self.humanReadableFileSize = function(bytes, si) {
      var thresh = si ? 1000 : 1024;
      if(Math.abs(bytes) < thresh) {
        return bytes + ' B';
      }
      var units = si
        ? ['kB','MB','GB','TB','PB','EB','ZB','YB']
        : ['KiB','MiB','GiB','TiB','PiB','EiB','ZiB','YiB'];
      var u = -1;
      do {
        bytes /= thresh;
        ++u;
      } while(Math.abs(bytes) >= thresh && u < units.length - 1);
      return bytes.toFixed(1)+' '+units[u];
    };

    self.construct();

    return $(self);
  };



  $.fn.EmailsComposeView.defaults = {
    "tinyMceOptions": {
      mode: "specific_textareas",
      plugins: "fullscreen",
      menubar: false,
      toolbar: ['fontselect | fontsizeselect | bold italic underline | styleselect'],
      formats: {
        bold: {inline: 'b'},
        italic: {inline: 'i'},
        underline: {inline: 'u'}
      }
    }
  }

}(jQuery));
