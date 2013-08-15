# Mandrill Form - Statamic Plug-In
A simple plug-in for [Statamic](http://statamic.com) CMS to enable users to easily create a form, send an email to configurable email address(es) using the [Mandrill](http://mandrillapp.com) API for email delivery and log the results.

This is a work in progres. Feel free to fork and fix bugs if found!

## Requirements
* [Statamic](http://statamic.com) 1.5+
* [Mandrill](http://mandrillapp.com) API key

## Installation
* Copy `_add-ons/mandrill_form` to into `_add-ons` in the document root of your Statamic site.
* Copy `_config/_add-ons/mandrill_form` to into `_config/add-ons` in the document root of your Statamic site.
* Copy `_themes/mandrill_form` to into `_themes` in the document root of your Statamic site.

## Config
Set your Mandrill API key in the plugin config at `_config/add-ons/mandrill_form/mandrill_form.yaml`.

Other config values are a fallback, but can be overrident by the template tag pair parameters below.

## Template Tags
* `mandrill_form`
* `success`
* `error`
* `errors`

## Template Tag Parameters

### `mandrill_form`

* `form_name` (string)
* `to_email` (string)(required)
* `to_name` (string)
* `cc` (string)
* `bcc` (string)
* `from_email` (string)
* `from_name` (string)
* `msg_header` (string)
* `msg_footer` (string)
* `subject` (string)
* `form_id` (string)
* `form_class` (string)
* `html_template` (string)
* `plain_text_template` (string)
* `required_fields` (string)
* `required_fields` (string)
* `use_merge_vars` (bool)
* `enable_spam_killah` (bool)
* `spam_killah_redirect` (string)
* `success_redirect` (string)
* `error_redirect` (string)
* `send_user_email` (bool)
* `user_email` (string)
* `user_to_name` (string)
* `user_subject` (string)
* `user_html_template` (string)
* `user_plain_text_template` (string)

### `success` (boolean)

This is a boolean based on status of the Mandril API request.

### `error` (boolean)

If API call failed or required fields failed, this will be set to true.

### `errors`

If `error` is true, `errors` will contain messages for failed validations or the API failure message.

## Usage Examples

This is a simple sample of the `mandrill_form` tag pair:

    <h1>Mandrill Form Sample Template</h1>
    {{ mandrill_form
        form_name="sample"
        to_email="test@test.com"
        to_name="Testing"
        cc=""
        bcc=""
        from_email="test@test.com"
        from_name="Mandril Formm Test"
        subject="Mandrill Form Test"
        send_user_email="true"
        user_email="email"
        user_to_name="first_name"
        user_html_template=""
        user_plain_text_template=""
        user_subject="User Email Test"
        form_id="mandrill-form"
        form_class=""
        html_template="email.html"
        plain_text_template="email.txt"
        required_fields="first_name|last_name|options"
        required_fields_messages="First Name is a required field.|Last Name is a required field.|Please select an option."
        use_merge_vars="1"
        enable_spam_killah="true"
        spam_killah_redirect=""
        success_redirect=""
        error_redirect=""
        enable_logging="true"
    }}
        {{ if error }}
            <h1>Whoops, there were errors!</h1>
            <ul>
            {{ errors }}
                <li>{{ error }}</li>
            {{ /errors }}
            </ul>
        {{ endif }}
        {{ if success }}
            <h2>Success!</h2>
            {{# redirect to="/rsvp/thanks" #}}
        {{ else }}
        {{# Start form #}}
        <fieldset>
            <p>
                <label for="first_name">First Name</label>
                <input type="text" name="first_name" value="{{ post }}{{ first_name }}{{ /post }}" />
            </p>
            <p>
                <label for="last_name">Last Name</label>
                <input type="text" name="last_name" value="{{ post }}{{ last_name }}{{ /post }}" />
            </p>
            <p>
                <label for="email">Email Address</label>
                <input type="email" name="email" value="{{ post }}{{ email }}{{ /post }}" />
            </p>
            <p>
                <label for="message">Message</label>
                <textarea name="message" id="message" cols="30" rows="10">{{ post }}{{ message }}{{ /post }}</textarea>
            </p>
            <p>
                <select name="options" id="options">
                    <option value="">Please select an option</option>
                    <option value="1">One</option>
                    <option value="2">Two</option>
                    <option value="3">Three</option>
                    <option value="4">Four</option>
                    <option value="5">Five</option>
                </select>
            </p>
            <p>
                <input type="submit" name="submit" value="Submit" />
            </p>
        </fieldset>
        {{# End form #}}
        {{ endif }}
    {{ /mandrill_form }}

## Logging

The plug-in will automatically log (2) types of datasets for you:

1. __Mandrill API repsonses__: These will be stored by default in `_logs`. Errors will be in a file named `mandrill_error.log` and success responses will be stored in a file named `mandrill_success.log`.
2. __POST data for each form submission in CSV format__: By default, this will be stored in the `_logs` directory in a file with the same name as the form. For example, if the parameter `form_name=sample` in the tag pair, a file named `sample.csv` will be created in `_logs`. New form submissions will be appended to this file. If you choose to clear this file, you could simply delete or move it out of the `_logs` directory and the file will be created again on the next form submission.

## Changelog

### v1.1
* Added user email option.

### v1.0
Initial release!

### v0.9
* Initial public release. Rewrote most of the plugin.

### v0.6
* Can pre-populate input fields on error with the {{ post }}{{ /post }} tag pair, i.e. {{ post }}{{ email }}{{ /post }} would repopulate the input field named `email`.

