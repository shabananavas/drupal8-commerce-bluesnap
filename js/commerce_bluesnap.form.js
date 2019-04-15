/**
 * @file
 * Javascript to submit card details to BlueSnap in a PCI-compliant way.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  /**
   * Attaches the commerceBluesnapForm behavior.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the commerceBluesnapForm behavior.
   * @prop {Drupal~behaviorDetach} detach
   *   Detaches the commerceBluesnapForm behavior.
   *
   * @see Drupal.commerceBluesnap
   */
  Drupal.behaviors.commerceBluesnapForm = {
    attach: function (context) {
      $('.bluesnap-form', context).once('bluesnap-processed').each(function () {
        // Initialize the card field variables.
        var isCardNumberComplete = false;
        var isCVVComplete = false;
        var isExpiryComplete = false;

        // Get the form.
        var $form = $(this).closest('form');
        // Get the Hosted Payment Fields token.
        var $hostedPaymentFieldsToken = $('#bluesnap-token', $form).val();

        /**
         * Takes token and bsObj as inputs and initiates Hosted Payment Fields.
         */
        $(document).ready(function () {
          // Mount the payment fields from BlueSnap.
          bluesnap.hostedPaymentFieldsCreation($hostedPaymentFieldsToken, bluesnapObject);

          /**
           * Form submit.
           */
          $form.on('submit', function (event) {
            if ($('.bluesnap-form', context).length) {
              event.preventDefault();

              // Submit the card details to BlueSnap.
              bluesnap.submitCredentials(function (callback) {
                // On error.
                if (null != callback.error) {
                  var errorArray = callback.error;
                  $(errorArray).each(function (index, error) {
                    // Set the current field complete status to false.
                    bluesnapSetFieldStatus(error.tagId, false);

                    // Display the errors on the form.
                    bluesnapErrorDisplay(bluesnapGetErrorText(error.errorCode));
                    bluesnapGoToError(error.tagId);
                  });
                }
                // If we're successful, make the actual submit to the server.
                else {
                  // Submit the form.
                  $form.get(0).submit();
                }
              });
            }
          });
        });

        /**
         * Defining bsObj: object.
         *
         * Stores Hosted Payment Fields event handlers, styling, placeholder
         * text, etc.
         */
        var bluesnapObject = {
          onFieldEventHandler: {
            onFocus: function (tagId) {
              // Handle focus.
              changeImpactedElement(tagId, 'hosted-field-valid hosted-field-invalid', 'hosted-field-focus');
            },
            onBlur: function (tagId) {
              // Handle blur.
              changeImpactedElement(tagId, 'hosted-field-focus');
            },
            onError: function (tagId, errorCode, errorDescription) {
              // Handle a change in validation.
              changeImpactedElement(tagId, 'hosted-field-valid hosted-field-focus', 'hosted-field-invalid');
              $('#' + tagId + '-help').removeClass('helper-text-green').text(errorCode + ' - ' + errorDescription);

              // Set the current field complete status to false.
              bluesnapSetFieldStatus(tagId, false);

              // Display the errors on the form.
              bluesnapErrorDisplay(bluesnapGetErrorText(errorCode));
              bluesnapGoToError(tagId);
            },
            onType: function (tagId, cardType, cardData) {
              if (null != cardData) {
                $('#' + tagId + '-help').addClass('helper-text-green').text(JSON.stringify(cardData));
              }
            },
            onValid: function (tagId) {
              // Handle a change in validation.
              changeImpactedElement(tagId, 'hosted-field-focus hosted-field-invalid', 'hosted-field-valid');
              $('#' + tagId + '-help').text('');

              // Set the current field complete status to true.
              bluesnapSetFieldStatus(tagId, true);

              // Unset any errors if no elements have the invalid class.
              if (bluesnapNoErrorsExist()) {
                $form.find('#payment-errors').html('');
              }
            }
          },
          // Styling is optional.
          style: {
            // Styling all inputs.
            'input': {
              'font-size': '14px',
              'font-family': 'Helvetica Neue,Helvetica,Arial,sans-serif',
              'line-height': '1.42857143',
              'color': '#555'
            },
            ':focus': {
              'color': '#555'
            },
            '.invalid': {
              'color': 'red'
            }
          },
          ccnPlaceHolder: '1234 5678 9012 3456',
          cvvPlaceHolder: '123',
          expPlaceHolder: 'MM/YY',
          expDropDownSelector: false
        };

        /**
         * Adds/removes the provided class(es) to Hosted Payment Fields element.
         */
        function changeImpactedElement(tagId, removeClass, addClass) {
          removeClass = removeClass || '';
          addClass = addClass || '';
          $('[data-bluesnap=' + tagId + ']')
            .removeClass(removeClass)
            .addClass(addClass);
        }

        /**
         * Sets the card field complete status to TRUE/FALSE.
         *
         * @param tagId
         *   The field id.
         * @param status
         *   The status to set it to.
         */
        function bluesnapSetFieldStatus(tagId, status) {
          switch (tagId) {
            case 'ccn':
              isCardNumberComplete = status;

            case 'exp':
              isExpiryComplete = status;

            case 'cvv':
              isCVVComplete = status;
          }
        }

        // Helper for displaying the error messages within the form.
        var bluesnapErrorDisplay = function (error_message) {
          // Display the message error in the payment form.
          $form.find('#payment-errors').html(Drupal.theme('commerceBluesnapError', error_message));
        };

        // Scroll to the error element so the user can see the problem.
        var bluesnapGoToError = function (id) {
          if ($(id).length) {
            $(window).scrollTop($(id).position().top);
          }
        };

        /**
         * Returns TRUE if all fields are valid on the form.
         */
        var bluesnapNoErrorsExist = function () {
          return !$('.hosted-field-invalid').length
            && isCardNumberComplete
            && isCVVComplete
            && isExpiryComplete;
        };

        /**
         * Takes error code (recvd from BlueSnap) and returns associated text.
         *
         * @param int errorCode
         *   The BlueSnap error code.
         *
         * @returns string
         *   The error text.
         */
        function bluesnapGetErrorText(errorCode) {
          switch (errorCode) {
            case '001':
              return Drupal.t('Please enter a valid card number.');

            case '002':
              return Drupal.t('Please enter a valid CVV/CVC number.');

            case '003':
              return Drupal.t('Please enter a valid expiration date.');

            case '22013':
              return Drupal.t('The card type is not supported by the merchant.');

            case '400':
              return Drupal.t('Session expired. Please refresh the page to continue.');

            case '403':
            case '404':
            case '500':
              return Drupal.t('Internal server error. Please try again later.');

            default:
              break;
          }
        }

        /**
         * @extends Drupal.theme.
         */
        $.extend(Drupal.theme, {
          commerceBluesnapError: function (message) {
            return $('<div class="messages messages--error"></div>').html(message);
          }
        });
      });
    }
  }
})(jQuery, Drupal, drupalSettings);
