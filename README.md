# Vebra Alto Wrapper plugin for Craft CMS 3.x - DEPRECATED

Integration with the estate agency software vebraalto.com

## Requirements

This plugin requires Craft CMS 3.0.0-beta.23 or later.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require https://github.com/Jegard/vebra-alto-wrapper

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for Vebra Alto Wrapper.

## Vebra Alto Wrapper Overview

This plugin allows you to import properties from vebra alto as entries in craft cms 3.
read api details here: http://webservices.vebra.com/export/xsd/v9/Client_Feed_API_v9_UserGuide.pdf

## Configuring Vebra Alto Wrapper

First fill in your vebra api details into the plugin settings

![GitHub Logo](/resources/img/step1.jpg)

## Using Vebra Alto Wrapper

Then select which location you would like to import (this plugin can handle multiple locations) and select which section you want to import the properties to

![GitHub Logo](/resources/img/step2.jpg)

Then choose which fields you want the desired data to go. Please not 'images' and 'brochure' must be an assets field and propertyType must be a categories field containing a 'For Let' and a 'For Sale' category.

Once all links have been saved you can then periodically update properties via a cron job

![GitHub Logo](/resources/img/step3.jpg)

## Vebra Alto Wrapper Roadmap

Some things to do, and ideas for potential features:

* Release it

Brought to you by [Luca Jegard](https://github.com/Jegard)
