GotoWebinar Sync 8.x-1.x
________________________

### Module Description

This module integrates with the GotoWebinar API to sync webinars to your Drupal website.

### Content Type Setup

You will need a content type that will be used to sync the webinar data from GotoWebinar to your Drupal site. This content type will need some required fields.

- **Webinar Title field** - typically you will use the title field for this
- **Webinar Key field** - this needs to be a plain text field and is used to relate the webinar data from the API with the individual piece of content on your Drupal site. Note: If you are using grouping (see below) you will need to set the cardinality of this field to unlimited
- **Webinar URL field** - this needs to be a link field where you will store a registration link for this webinar
- **Webinar Date Range field** - this field needs to be able to store the webinar date information. Note: If you are using grouping (see below) you will need to set the cardinality of this field to unlimited

There are also some optional fields if you would like to store some additional data from the GotoWebinar api:

- **Webinar Description** - This might be the body field or another long text field
- **Webinar Experience Type** - This is a field to store the Experience Type (CLASSIC, SIMULIVE, or BROADCAST)
- **Webinar Recurrence Type** - This is a field to store the Recurrence Type (single_session, series or sequence)

### Module Installation

1. Download and install this module like you would any other module (composer is the preferred method)
2. On the module configuration page verify the public URL of your website
3. Copy the generated oauth link from the description (you will need it soon)
4. Go to https://goto-developer.logmeininc.com and log in with your GotoWebinar account
5. Create a development app (make sure to select GotoWebinar for the product API and paste in the oauth link from step 3)
6. Copy the App Consumer Key and Consumer Secret and paste them into the module configuration page
7. Save the configuration page and click the generated Authenticate with GotoWebinar link
8. Enter your GotoWebinar credentials on the oauth page (if required) or click the Allow button to be redirected back to the configuration page
9. Select the content type you want to use for the webinar sync and save the configuration page
10. Map the fields to the fields on the content type you are using for the webinar sync and save the configuration page (see notes below about grouping option)
11. Create a webinar from within the GotoWebinar interface and it should immediately create the corresponding content on your Drupal site

### Grouping Webinars

If you are creating recurring webinars the default is to create a new piece of content on your Drupal site for each instance of the webinar. This might be what you want, however, this can become tedious to manage if you need to change something on the webinar (as you would need to manually make the changes on each piece of content on your Drupal site).

There is a group option that will merge recurring webinars into a single piece of content. It adds each date as an item in the date field. This means if you edit the content you will see all of the dates listed in the date field. This makes managing edits to your webinars much easier.

### Known Limitations

There are a few known limitations to the module. Some of these would be great future features/fixes:

1. If you are using the grouping option and you delete a webinar from within GotoWebinar, it will not be deleted on your Drupal site
2. If you uninstall the module it will not uninstall the webhooks from the GotoWebinar API. It's recommended to create an app only for your Drupal site and if it's no longer needed, to delete the app from the GotoWebinar Developer Center
3. The module does not provide the ability to sync any registration data. This is supported through the Gotowebinar API but has not been added to the module
