# TW Receiver

## About
A TiddlyWiki plugin used for saving to a PHP based server.

### Features
 - Simple automated backups
 - Challenge Digest Authentication (enhanced security)
 -- This simple mechanism avoids passing the password in plain text. Instead the server is queried for a challenge token and that token is then combined with the password to form a new string that is both unique and temporary.
 - Data Integrity Signing (enhanced security)
 -- This practice creates a unique signature of the wiki text with the secret key. Checking the validity of this signature ensures the integrity of the wiki data and helps  prevent tampering in transit.

#### A note on Security
There is no way to securely transmit over HTTP. Using HTTP your password and content can be viewed and changed. Use of HTTPS (TLS) is strongly recommended.
Think of the other security enhancements as low budget security. This will prevent a number of attacks, but it is not a replacement for proper HTTPS.
Try out HTTPS, check out https://letsencrypt.org/

## Getting Started

### Setup
 1. **Tools > Import** the **plugin_sendwheel_tw-receiver.json** file into your wiki
 2. Save and refresh your wiki
 3. Enable the plugin in **Control Panel > Saving > TW Receiver** 
 4. place the **tw-receiver-server.php** file in the same directory as your wiki.html on the server
 5. Set a strong secure key (password) in plugin and on server

You will likely have to make server side adjustments; things like setting directory permissions or ini configurations like max upload sizes. See Environment Tests for help.

#### Environment Tests
Accessing tw-receiver-server.php directly will perform some access and configuration tests and report. 
For example `https://example.com/tw-receiver-server.php`

#### Usage
 - Most of the default settings can and likely should be used. The security enhancements of this plugin can be disabled, but have minimal cost to use.
 - While the secret key can be stored directly in the tw-receiver-server.php file, it is a better practice to use an external ini. This requires placing the ini in a **non** web accessible folder outside of the web root, and setting it's path in $extSecKeyPath. This is disabled by default only because not setting this up correctly is worse than not using it at all.

## Contributing
If you want to contribute to this plugin in any way or want to report any security issues, please do.

### Credits
Client side components partially based on the [upload.js](https://github.com/Jermolene/TiddlyWiki5/blob/master/core/modules/savers/upload.js) core module which was based on work by [bidix](https://github.com/tobibeer/bidix). 
