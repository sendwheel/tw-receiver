
# TW Receiver

## About
A TiddlyWiki plugin used for saving to a PHP based server.

### Features
 - Simple automated backups
 -- Backup a definable number of wiki copies with time-stamps. Useful to review an old version, or to back out of a corrupt wiki.
 - Stale Instance Overwrite Protection
 -- This ensures the wiki you're working on isn't out of date with the server before saving changes. It avoids a scenario where changes made earlier in another window were not loaded into the current instance of the wiki and would be lost by overwrite.
 - Challenge Digest Authentication (enhanced security)
 -- This simple mechanism avoids passing the password in plain text. Instead the server is queried for a challenge token and that token is then combined with the password to form a new string that is both unique and temporary.
 - Data Integrity Signing (enhanced security)
 -- This practice creates a unique signature of the wiki text with the secret key. Checking the validity of this signature ensures the integrity of the wiki data and helps prevent tampering in transit.

#### A note on Security
There is no way to securely transmit over HTTP. Using HTTP your password and content can be viewed and changed. Use of HTTPS (TLS) is strongly recommended.
Think of the other security enhancements as low budget security. This will prevent a number of attacks, but it is not a replacement for proper HTTPS.
Try out HTTPS, check out https://letsencrypt.org/

## Getting Started

### Setup
 1. **Tools > Import** the **plugin_sendwheel_tw-receiver.json** file into your wiki
 2. Save and refresh your wiki
 3. Enable the plugin in **Control Panel > Saving > TW Receiver** 
 4. and set a strong secret key (password)
 5. place the **tw-receiver-server.php** file in the same directory as your wiki.html on the server
 6. Set **$userpassword** on line 20 to the same secret key you used on the plugin screen in step 4

You will likely have to make server side adjustments; things like setting directory permissions or ini configurations like max upload sizes. See Environment Tests for help.

#### Notes
 - Most of the default settings can and likely should be used. The security enhancements of this plugin can be disabled, but have minimal cost to use.
 - While a password can be stored directly in the tw-receiver-server.php file, it is a better practice to use an external ini. This requires placing the ini in a **non** web accessible folder outside of the web root, and setting it's path in $extSecKeyPath. This is disabled by default only because not setting this up correctly is worse than not using it at all. Using this replaces the use of $userpassword.

### Usage
#### Environment Tests
Accessing tw-receiver-server.php directly will perform some access and configuration tests and report. 
For example `https://example.com/tw-receiver-server.php`

#### Stale Overwrite Protection Configuration
If enabling this on an existing installation, some additional steps are required. These steps can be ignored for new installations.
1. On the server side in the tw-receiver-server.php file, set staleCheck=false
2. Enable "Static Overwrite Protection" client side with checkbox in the UI
3. Save and reload the wiki
4. Now set staleCheck=true in the tw-receiver-server.php file server side.
5. Stale Overwrite Protection is now successfully configured and operational


### Requirements
 - PHP >= 7

## Contributing
If you want to contribute to this plugin in any way or want to report any issues, please do.

### Credits
Client side components partially based on the [upload.js](https://github.com/Jermolene/TiddlyWiki5/blob/master/core/modules/savers/upload.js) core module which was based on work by [bidix](https://github.com/tobibeer/bidix). 
