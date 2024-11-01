=== Vtiger Webform to Gravity Forms Converter===

Contributors: VCATconsulting, shogathu, nida78
Requires at least: 5.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.1.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.txt

Convert Vtiger Webforms to Gravity Forms

== Description ==

This plugin is an add-on to the [Gravity Forms](https://www.gravityforms.com/ "visit Gravity Forms website") form builder plugin.
It offers the opportunity to convert Vtiger Webforms to Gravity Forms and post the data to [Vtiger CRM (open source version)](https://www.vtiger.com/de/open-source-crm/download-open-source/ "visit Vtiger CRM website").

It requires a Vtiger CRM Open Source installation and the [Gravity Forms](https://www.gravityforms.com/ "visit Gravity Forms website") plugin.

To use it, just paste the Vtiger Webform code into the input field and click the "Convert" button.
The plugin will then create a new Gravity Form with the fields from the Vtiger Webform.

This plugin support all default Vtiger Webform fields.

== Installation ==

1. Install and configure Gravity Forms plugin,
2. Find this Vtiger Webform to Gravity Forms Converter plugin in the "Add Plugins" page within your WordPress installation or Upload the Vtiger Webform to Gravity Forms Converter plugin to your blog,
3. Activate it,
4. Find the Vtiger Webform Converter in the admin menu under Forms!

== Screenshots ==

1. The input field where you can paste the Vtiger Webform code

== Frequently Asked Questions ==

= Can I change the form metadata before form creation?

Yes, the plugin offers a filter called `vwtgf_converter_form_meta` which you can use to edit form meta like disable honeypot.

You can find an example usage of this filter in [a small plugin in a GIST](https://gist.github.com/vcat-support/487c3fe6e711a6336a0927f83aaf2db5).

= Can I change the field metadata before form creation?

Yes, the plugin offers 2 filters called `vwtgf_converter_field_meta` and type specific 'vwtgf_converter_field_meta_{$input_type}' which you can use to change field meta like field length.

You can find an example usage of this filter in [a small plugin in a GIST](https://gist.github.com/vcat-support/bdfacfb8377362901e985af1165d57a3).

= Can I change the max upload file size and file extension for an upload field?

Yes, the plugin offers 2 filters called `vwtgf_converter_upload_file_size` and 'vwtgf_converter_upload_file_extensions' to customize the upload field.

= Is it possible to update an existing form?

Yes, just paste the code of the webform into the input field and click the "Convert", if the form already exists, the plugin will update the form.
The identifier for the form is the form publicid from vtiger webform which does not change after updating the webform in Vtiger.
Keep in mind, that the plugin overwrites the form fields and settings, so if you have made changes in the Gravity Forms editor, you will lose them.

= Can I use the default Gravity Forms time field?

Vtiger create the time field as a normal input field. If you want to use the time field from Gravity Forms, you add the field manually after the conversion and change the admin label to the same as the Vtiger field.
Then you can  delete the Vtiger time field in Gravity Forms.

= Can I rearrange the fields?

Yes, you can drag and drop the fields in the gravity form editor.
IMPORTANT: After updating the form, the fields will be rearranged to the original order of the Vtiger webform.

= I accidentally deleted an important field, can I re-add it?

Yes, you can re-add the field by pasting the Vtiger webform code again and click the "Convert" button.
Or you can add the field manually in the Gravity Forms editor, but keep in mind to set the admin label to the right value.

== Changelog ==

= 1.1.2 =

* Fix a bug where admin notice was not shown after form conversion/update

= 1.1.1 =

* Fix some wording

= 1.1.0 =

* Add own sanitization for the Vtiger Webform code with wp_kses
* Add 2 new filters to change the allowed tags and attributes for the Vtiger Webform code
* Make some code improvements

= 1.0.1 =

* Update Github Actions

= 1.0.0 =

* First stable version
