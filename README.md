# Mandrill Form
A simple add-on for [Statamic](http://statamic.com) CMS to enable users to easily create an email form which using the [Mandrill](http://mandrillapp.com) API for email delivery.

This is a work in progres. Feel free to fork and fix bugs if found!

## Requirements
* [Statamic](http://statamic.com) 1.5+
* [Mandrill](http://mandrillapp.com) API key

## Installation
* Copy `_add-ons/mandrill_form` to into `_add-ons` in the document root of your Statamic site.
* Copy `_config/_add-ons/mandrill_form` to into `_add-ons` in the document root of your Statamic site.
* Copy `_themes/mandrill_form` to into `_themes` in the document root of your Statamic site.

## Template Tags
* `mandrill_form`

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
* `send_user_email` (bool)[planned]
* `user_email_template_plain_text` (string)[planned]
* `user_email_template_html` (string)[planned]
* `enable_spam_killah` (bool)
* `spam_killah_redirect` (string)
* `success_redirect` (string)
* `error_redirect` (string)

## Usage Examples

This is a simple sample of the `mandrill_form` tag pair:

    <h1>Mandrill Form Sample Template</h1>
    {{ mandrill_form
        form_name="sample"
        to_email="mreiner77@gmail.com"
        to_name="Testing"
        cc=""
        bcc=""
        from_email="test@test.com"
        from_name="Mandril Formm Test"
        msg_header=""
        msg_footer=""
        subject="Mandrill Form Test"
        form_id="mandrill-form"
        form_class=""
        html_template="email.html"
        plain_text_template="email.txt"
        required_fields=""
        use_merge_vars="1"
        send_user_email="false"
        user_email_template_plain_text=""
        user_email_template_html=""
        enable_spam_killah="true"
        spam_killah_redirect=""
        success_redirect=""
        error_redirect=""
    }}
        {{# Error checking #}}
        {{ if error }}
            <h1>Whoops, there were errors!</h1>
            <ul>
            {{ errors }}
                <li>{{ error }}</li>
            {{ /errors }}
            </ul>
        {{ endif }}
        {{# Success message #}}
        {{ if success }}
            <h2>Success!</h2>
            {{# redirect to="/rsvp/thanks" #}}
        {{ else }}
        {{# Start form #}}
        <fieldset>
            <p>
                <label for="first_name">First Name</label>
                <input type="text" name="first_name" />
            </p>
            <p>
                <label for="last_name">Last Name</label>
                <input type="text" name="last_name" />
            </p>
            <p>
                <label for="email">Email Address</label>
                <input type="email" name="email" />
            </p>
            <p>
                <label for="message">Message</label>
                <textarea name="message" id="message" cols="30" rows="10"></textarea>
            </p>
            <p>
                <input type="submit" name="submit" value="Submit" />
            </p>
        </fieldset>
        {{# End form #}}
        {{ endif }}
    {{ /mandrill_form }}

## Changelog
### v1.0
### v0.9

