/**
 *
 * @author      Yosef.
 * @copyright   2018-2024 Yosef.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 * @package     assets/javascript/payment_settings_custom_attributes.js
 */

jQuery(document).ready(function (jQuery) {
	var multiselect = jQuery('#woocommerce_emerchantpay_checkout_transaction_types'); // Multiselect element
	var hiddenField = jQuery('#woocommerce_emerchantpay_checkout_transaction_types_to_label'); // Hidden field
	var container = jQuery('<div id="transaction-labels-container"></div>'); // Container for key-value table

	hiddenField.after(container); // Add the container after the hidden field

	// Parse the current JSON value or initialize an empty object
	var currentMapping = {};
	try {
		currentMapping = JSON.parse(hiddenField.val()) || {};
	} catch (e) {
		currentMapping = {};
	}

	// Function to render the key-value table
	function renderTable() {
		container.empty(); // Clear the container

		// Iterate over selected options in the multiselect
		multiselect.find('option:selected').each(function () {
			var key = jQuery(this).val(); // Option value as key
			var value = currentMapping[key] || jQuery(this).text(); // Use existing value or default to option text

			// Create a row for the key-value pair
			var row = jQuery('<div class="transaction-label-row"></div>');
			var keyElement = jQuery('<span class="transaction-label-key"></span>').text(key); // Key display
			var input = jQuery('<input type="text" class="transaction-label-input" />').val(value);

			// Update the mapping on input change
			input.on('input', function () {
				currentMapping[key] = jQuery(this).val();
				updateHiddenField();
			});

			row.append(keyElement).append(input); // Add key and input to the row
			container.append(row); // Add the row to the container
		});
	}

	// Function to update the hidden field with the current mapping
	function updateHiddenField() {
		hiddenField.val(JSON.stringify(currentMapping));
	}

	// Event listener for multiselect changes
	multiselect.on('change', function () {
		// Update the mapping based on selected options
		multiselect.find('option').each(function () {
			var key = jQuery(this).val();
			if (jQuery(this).is(':selected')) {
				if (!currentMapping[key]) {
					currentMapping[key] = jQuery(this).text();
				}
			} else {
				delete currentMapping[key];
			}
		});

		renderTable(); // Re-render the table
		updateHiddenField(); // Update the hidden field
	});

	// Initial render of the table
	renderTable();
});

